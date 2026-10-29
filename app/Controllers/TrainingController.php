<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Paginator;
use App\Core\Upload;
use App\Core\Validator;
use App\Models\TrainingModel;

/**
 * Pelatihan & pengembangan.
 *  - /trainings                 : jadwal sesi pelatihan (HR)
 *  - /trainings/catalog         : master program pelatihan (HR)
 *  - /trainings/{id}            : peserta, presensi & sertifikat satu sesi (HR)
 *  - /me/trainings              : jadwal, riwayat & pendaftaran karyawan
 */
final class TrainingController extends Controller
{
    private TrainingModel $training;

    public function __construct()
    {
        $this->training = new TrainingModel();
    }

    /* ----------------------------------------------------------------- HR */

    public function index(): void
    {
        $this->requireRole('HR');

        $filters = $this->filters();

        $editing = null;
        $editId = (int) ($_GET['edit'] ?? 0);
        if ($editId > 0) {
            $editing = $this->training->findSession($editId);
        }

        $paginator = Paginator::fromRequest($this->training->countSessions($filters), 12);

        $this->view('training/index', [
            'title'       => 'Pelatihan & Sertifikasi',
            'pageTitle'   => 'Pelatihan & Sertifikasi',
            'sessions'    => $this->training->sessions($filters, $paginator->perPage, $paginator->offset()),
            'paginator'   => $paginator,
            'filters'     => $filters,
            'stats'       => $this->training->stats(),
            'programs'    => $this->training->programs(true),
            'editing'     => $editing,
            'statuses'    => TrainingModel::SESSION_STATUSES,
            'statusTones' => TrainingModel::SESSION_TONES,
            'modes'       => TrainingModel::MODES,
            'categories'  => TrainingModel::CATEGORIES,
            'categoryTones' => TrainingModel::CATEGORY_TONES,
            'pageLink'    => static function (int $page) use ($filters): string {
                $query = array_filter([
                    'status'  => $filters['status'] ?: null,
                    'program' => $filters['program'] ?: null,
                    'mode'    => $filters['mode'] ?: null,
                    'q'       => $filters['q'] ?: null,
                    'page'    => $page > 1 ? $page : null,
                ], static fn ($v) => $v !== null && $v !== '');
                return url('trainings' . ($query ? '?' . http_build_query($query) : ''));
            },
        ] + $this->withInput());
    }

    public function storeSession(): void
    {
        $this->requireRole('HR');
        $data = $this->validatedSession();

        $id = $this->training->createSession($data, $this->employeeId());

        Audit::log('training.session_create', 'training_session', $id, ['program' => $data['training_id']]);
        flash('success', 'Sesi pelatihan dibuat sebagai draf. Buka pendaftaran bila jadwal sudah final.');
        redirect('/trainings');
    }

    public function updateSession(string $id): void
    {
        $this->requireRole('HR');
        $session = $this->training->findSession((int) $id);
        if (!$session) {
            $this->abort(404, 'Sesi pelatihan tidak ditemukan.');
        }

        $data = $this->validatedSession();
        $this->training->updateSession((int) $id, $data);

        Audit::log('training.session_update', 'training_session', (int) $id);
        flash('success', 'Sesi pelatihan diperbarui.');
        redirect('/trainings/' . (int) $id);
    }

    public function openSession(string $id): void
    {
        $this->requireRole('HR');
        $session = $this->training->findSession((int) $id);
        if (!$session) {
            $this->abort(404, 'Sesi pelatihan tidak ditemukan.');
        }
        if ($session['status'] !== 'DRAFT') {
            flash('error', 'Hanya sesi berstatus draf yang dapat dibuka.');
            back('/trainings');
        }

        $this->training->setSessionStatus((int) $id, 'OPEN');
        $notified = $this->notifyOpen($session);

        Audit::log('training.session_open', 'training_session', (int) $id);
        flash('success', 'Pendaftaran dibuka. ' . number_id($notified) . ' karyawan aktif menerima notifikasi.');
        redirect('/trainings/' . (int) $id);
    }

    public function startSession(string $id): void
    {
        $this->requireRole('HR');
        $session = $this->training->findSession((int) $id);
        if (!$session) {
            $this->abort(404, 'Sesi pelatihan tidak ditemukan.');
        }
        if (!in_array($session['status'], ['OPEN', 'DRAFT'], true)) {
            flash('error', 'Sesi ini tidak dapat dimulai dari status sekarang.');
            back('/trainings/' . (int) $id);
        }

        $this->training->setSessionStatus((int) $id, 'ONGOING');
        Audit::log('training.session_start', 'training_session', (int) $id);
        flash('success', 'Sesi ditandai berlangsung. Tandai kehadiran peserta setelah pelatihan selesai.');
        redirect('/trainings/' . (int) $id);
    }

    public function completeSession(string $id): void
    {
        $this->requireRole('HR');
        $session = $this->training->findSession((int) $id);
        if (!$session) {
            $this->abort(404, 'Sesi pelatihan tidak ditemukan.');
        }
        if ($session['status'] === 'COMPLETED') {
            flash('info', 'Sesi ini sudah berstatus selesai.');
            back('/trainings/' . (int) $id);
        }

        $this->training->setSessionStatus((int) $id, 'COMPLETED');
        $stats = $this->training->sessionStats((int) $id);

        Audit::log('training.session_complete', 'training_session', (int) $id, ['attended' => $stats['attended']]);
        flash('success', 'Sesi pelatihan ditutup. ' . number_id($stats['attended']) . ' peserta tercatat hadir.');
        redirect('/trainings/' . (int) $id);
    }

    public function cancelSession(string $id): void
    {
        $this->requireRole('HR');
        $session = $this->training->findSession((int) $id);
        if (!$session) {
            $this->abort(404, 'Sesi pelatihan tidak ditemukan.');
        }
        if ($session['status'] === 'COMPLETED') {
            flash('error', 'Sesi yang sudah selesai tidak dapat dibatalkan.');
            back('/trainings/' . (int) $id);
        }

        $this->training->setSessionStatus((int) $id, 'CANCELLED');
        foreach ($this->training->participants((int) $id) as $participant) {
            if (in_array($participant['status'], ['NOMINATED', 'APPROVED'], true)) {
                $this->notifyEmployee(
                    (int) $participant['employee_id'],
                    'Sesi pelatihan dibatalkan',
                    $session['program_name'] . ' pada ' . date('d/m/Y', strtotime((string) $session['start_date'])) . ' dibatalkan HR.',
                    '/me/trainings',
                    'warning'
                );
            }
        }

        Audit::log('training.session_cancel', 'training_session', (int) $id);
        flash('warning', 'Sesi pelatihan dibatalkan dan peserta telah diberi notifikasi.');
        redirect('/trainings/' . (int) $id);
    }

    public function destroySession(string $id): void
    {
        $this->requireRole('HR');
        $session = $this->training->findSession((int) $id);
        if (!$session) {
            $this->abort(404, 'Sesi pelatihan tidak ditemukan.');
        }
        if (!in_array($session['status'], ['DRAFT', 'CANCELLED'], true)) {
            flash('error', 'Hanya sesi draf atau batal yang dapat dihapus. Batalkan sesi terlebih dahulu.');
            back('/trainings/' . (int) $id);
        }

        $this->training->deleteSession((int) $id);
        Audit::log('training.session_delete', 'training_session', (int) $id);
        flash('success', 'Sesi pelatihan dihapus.');
        redirect('/trainings');
    }

    /* ------------------------------------------------------------- katalog */

    public function catalog(): void
    {
        $this->requireRole('HR');

        $editing = null;
        $editId = (int) ($_GET['edit'] ?? 0);
        if ($editId > 0) {
            $editing = $this->training->findProgram($editId);
        }

        $this->view('training/catalog', [
            'title'      => 'Katalog Pelatihan',
            'pageTitle'  => 'Katalog Pelatihan',
            'programs'   => $this->training->programs(),
            'editing'    => $editing,
            'categories' => TrainingModel::CATEGORIES,
            'categoryTones' => TrainingModel::CATEGORY_TONES,
            'types'      => TrainingModel::TYPES,
        ] + $this->withInput());
    }

    public function storeProgram(): void
    {
        $this->requireRole('HR');

        $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('code', 'Kode pelatihan')
              ->required('name', 'Nama pelatihan')
              ->in('category', 'Kategori', array_keys(TrainingModel::CATEGORIES))
              ->in('type', 'Jenis', array_keys(TrainingModel::TYPES))
              ->numeric('duration_hours', 'Durasi', 0)
              ->numeric('cost_per_participant', 'Biaya per peserta', 0)
              ->custom((bool) ($_POST['code'] ?? '') && mb_strlen((string) $_POST['code']) > 30, 'code', 'Kode maksimal 30 karakter.');
        }, '/trainings/catalog');

        $data = $this->programPayload();
        if ($this->training->programByCode($data['code']) !== null) {
            flash('error', 'Kode pelatihan "' . $data['code'] . '" sudah dipakai.');
            back('/trainings/catalog');
        }

        $id = $this->training->createProgram($data, $this->employeeId());
        Audit::log('training.program_create', 'training', $id, ['code' => $data['code']]);
        flash('success', 'Program pelatihan "' . $data['name'] . '" ditambahkan ke katalog.');
        redirect('/trainings/catalog');
    }

    public function updateProgram(string $id): void
    {
        $this->requireRole('HR');
        $program = $this->training->findProgram((int) $id);
        if (!$program) {
            $this->abort(404, 'Program pelatihan tidak ditemukan.');
        }

        $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('code', 'Kode pelatihan')
              ->required('name', 'Nama pelatihan')
              ->in('category', 'Kategori', array_keys(TrainingModel::CATEGORIES))
              ->in('type', 'Jenis', array_keys(TrainingModel::TYPES))
              ->numeric('duration_hours', 'Durasi', 0)
              ->numeric('cost_per_participant', 'Biaya per peserta', 0);
        }, '/trainings/catalog');

        $data = $this->programPayload();
        if ($this->training->programByCode($data['code'], (int) $id) !== null) {
            flash('error', 'Kode pelatihan "' . $data['code'] . '" sudah dipakai program lain.');
            back('/trainings/catalog');
        }

        $this->training->updateProgram((int) $id, $data);
        Audit::log('training.program_update', 'training', (int) $id);
        flash('success', 'Program pelatihan diperbarui.');
        redirect('/trainings/catalog');
    }

    public function destroyProgram(string $id): void
    {
        $this->requireRole('HR');
        if (!$this->training->deleteProgram((int) $id)) {
            flash('error', 'Program sudah dipakai pada sesi pelatihan — nonaktifkan saja agar riwayat tetap utuh.');
            back('/trainings/catalog');
        }

        Audit::log('training.program_delete', 'training', (int) $id);
        flash('success', 'Program pelatihan dihapus dari katalog.');
        redirect('/trainings/catalog');
    }

    /* -------------------------------------------------------------- detail */

    public function show(string $id): void
    {
        $this->requireRole('HR');
        $session = $this->training->findSession((int) $id);
        if (!$session) {
            $this->abort(404, 'Sesi pelatihan tidak ditemukan.');
        }

        $this->view('training/show', [
            'title'       => $session['program_name'],
            'pageTitle'   => 'Detail Sesi Pelatihan',
            'session'     => $session,
            'participants' => $this->training->participants((int) $id),
            'stats'       => $this->training->sessionStats((int) $id),
            'candidates'  => $this->training->availableEmployees((int) $id),
            'statuses'    => TrainingModel::SESSION_STATUSES,
            'statusTones' => TrainingModel::SESSION_TONES,
            'partStatuses' => TrainingModel::PARTICIPANT_STATUSES,
            'partTones'   => TrainingModel::PARTICIPANT_TONES,
            'modes'       => TrainingModel::MODES,
            'categories'  => TrainingModel::CATEGORIES,
            'categoryTones' => TrainingModel::CATEGORY_TONES,
        ] + $this->withInput());
    }

    public function storeParticipants(string $id): void
    {
        $this->requireRole('HR');
        $session = $this->training->findSession((int) $id);
        if (!$session) {
            $this->abort(404, 'Sesi pelatihan tidak ditemukan.');
        }
        if (in_array($session['status'], ['COMPLETED', 'CANCELLED'], true)) {
            flash('error', 'Sesi yang sudah selesai atau batal tidak dapat menerima peserta baru.');
            back('/trainings/' . (int) $id);
        }

        $raw = $_POST['employee_ids'] ?? [];
        $employeeIds = is_array($raw) ? array_map('intval', $raw) : [];
        if (!$employeeIds) {
            flash('error', 'Pilih minimal satu karyawan untuk diikutsertakan.');
            back('/trainings/' . (int) $id);
        }

        $result = $this->training->addParticipants((int) $id, $employeeIds, 'HR', $this->employeeId(), true);

        Audit::log('training.participant_add', 'training_session', (int) $id, ['added' => $result['added']]);
        $message = number_id($result['added']) . ' peserta ditambahkan.';
        if ($result['skipped'] > 0) {
            $message .= ' ' . number_id($result['skipped']) . ' karyawan dilewati karena sudah terdaftar.';
        }
        flash($result['added'] > 0 ? 'success' : 'warning', $message);
        redirect('/trainings/' . (int) $id);
    }

    public function updateParticipantStatus(string $id, string $pid): void
    {
        $this->requireRole('HR');
        $participant = $this->training->findParticipant((int) $pid);
        if (!$participant || (int) $participant['session_id'] !== (int) $id) {
            $this->abort(404, 'Peserta tidak ditemukan pada sesi ini.');
        }

        $status = (string) input('status', '');
        if (!isset(TrainingModel::PARTICIPANT_STATUSES[$status])) {
            flash('error', 'Status peserta tidak dikenal.');
            back('/trainings/' . (int) $id);
        }

        $this->training->setParticipantStatus((int) $pid, $status, $this->employeeId());
        Audit::log('training.participant_status', 'training_participant', (int) $pid, ['status' => $status]);
        flash('success', 'Status peserta diperbarui menjadi "' . TrainingModel::PARTICIPANT_STATUSES[$status] . '".');
        back('/trainings/' . (int) $id);
    }

    /** Simpan nilai, jam hadir, nomor & berkas sertifikat satu peserta. */
    public function storeResult(string $id): void
    {
        $this->requireRole('HR');
        $session = $this->training->findSession((int) $id);
        if (!$session) {
            $this->abort(404, 'Sesi pelatihan tidak ditemukan.');
        }

        $participant = $this->training->findParticipant((int) input('participant_id', 0));
        if (!$participant || (int) $participant['session_id'] !== (int) $id) {
            flash('error', 'Pilih peserta yang terdaftar pada sesi ini.');
            back('/trainings/' . (int) $id);
        }

        $rawScore = trim((string) input('score', ''));
        $rawHours = trim((string) input('attended_hours', ''));

        if ($rawScore !== '' && (!is_numeric($rawScore) || (float) $rawScore < 0 || (float) $rawScore > 100)) {
            flash('error', 'Nilai pelatihan harus angka 0 sampai 100.');
            back('/trainings/' . (int) $id);
        }
        if ($rawHours !== '' && (!is_numeric($rawHours) || (float) $rawHours < 0 || (float) $rawHours > 999)) {
            flash('error', 'Jam hadir harus angka 0 sampai 999.');
            back('/trainings/' . (int) $id);
        }

        $upload = Upload::document('certificate', 'trainings', 4 * 1024 * 1024);
        if ($upload['error'] !== null) {
            flash('error', $upload['error']);
            back('/trainings/' . (int) $id);
        }

        $payload = [
            'score'          => $rawScore === '' ? null : round((float) $rawScore, 2),
            'attended_hours' => $rawHours === '' ? null : round((float) $rawHours, 2),
            'certificate_no' => trim((string) input('certificate_no', '')) ?: null,
            'note'           => trim((string) input('note', '')) ?: null,
        ];
        if ($upload['path'] !== null) {
            Upload::delete($participant['certificate'] ?? null);
            $payload['certificate'] = $upload['path'];
        }

        $this->training->updateParticipantResult((int) $participant['id'], $payload, $this->employeeId());
        Audit::log('training.participant_result', 'training_participant', (int) $participant['id'], ['score' => $payload['score']]);

        flash('success', 'Hasil pelatihan ' . $participant['full_name'] . ' tersimpan.');
        redirect('/trainings/' . (int) $id);
    }

    public function removeParticipant(string $id, string $pid): void
    {
        $this->requireRole('HR');
        $participant = $this->training->findParticipant((int) $pid);
        if (!$participant || (int) $participant['session_id'] !== (int) $id) {
            $this->abort(404, 'Peserta tidak ditemukan pada sesi ini.');
        }

        Upload::delete($participant['certificate'] ?? null);
        $this->training->removeParticipant((int) $pid);

        Audit::log('training.participant_remove', 'training_participant', (int) $pid);
        flash('success', 'Peserta dikeluarkan dari sesi.');
        redirect('/trainings/' . (int) $id);
    }

    public function approveAll(string $id): void
    {
        $this->requireRole('HR');
        $count = $this->training->approveAll((int) $id, $this->employeeId());

        Audit::log('training.participant_approve_all', 'training_session', (int) $id, ['count' => $count]);
        flash($count > 0 ? 'success' : 'info', $count > 0
            ? number_id($count) . ' pendaftaran disetujui dan karyawan telah diberi notifikasi.'
            : 'Tidak ada pendaftaran yang menunggu persetujuan.');
        redirect('/trainings/' . (int) $id);
    }

    public function attendanceAll(string $id): void
    {
        $this->requireRole('HR');
        $count = $this->training->markAttendanceAll((int) $id, $this->employeeId());

        Audit::log('training.attendance_all', 'training_session', (int) $id, ['count' => $count]);
        flash($count > 0 ? 'success' : 'info', $count > 0
            ? number_id($count) . ' peserta ditandai hadir.'
            : 'Tidak ada peserta terdaftar yang bisa ditandai hadir.');
        redirect('/trainings/' . (int) $id);
    }

    public function export(string $id): void
    {
        $this->requireRole('HR');
        $session = $this->training->findSession((int) $id);
        if (!$session) {
            $this->abort(404, 'Sesi pelatihan tidak ditemukan.');
        }

        $filename = 'peserta-pelatihan-' . (int) $id . '-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'Program', 'Kode Program', 'NIK', 'Karyawan', 'Unit', 'Departemen', 'Sumber',
            'Status', 'Nilai', 'Jam Hadir', 'No. Sertifikat', 'Sertifikat', 'Catatan',
        ], ',', '"', '');
        foreach ($this->training->participants((int) $id) as $row) {
            fputcsv($out, [
                $session['program_name'],
                $session['program_code'],
                $row['employee_code'],
                $row['full_name'],
                $row['unit_name'] ?? '-',
                $row['department_name'] ?? '-',
                $row['source'] === 'SELF' ? 'Pendaftaran Mandiri' : 'Nominasi HR',
                TrainingModel::PARTICIPANT_STATUSES[$row['status']] ?? $row['status'],
                $row['score'] !== null ? number_format((float) $row['score'], 1, '.', '') : '',
                $row['attended_hours'] !== null ? number_format((float) $row['attended_hours'], 1, '.', '') : '',
                $row['certificate_no'] ?? '',
                $row['certificate'] !== null && $row['certificate'] !== '' ? 'Ada' : 'Belum',
                $row['note'] ?? '',
            ], ',', '"', '');
        }
        fclose($out);
        exit;
    }

    /* ------------------------------------------------------------ karyawan */

    public function mine(): void
    {
        $employeeId = $this->employeeId();

        $this->view('training/mine', [
            'title'       => 'Pelatihan Saya',
            'pageTitle'   => 'Pelatihan Saya',
            'enrollments' => $this->training->employeeSessions($employeeId),
            'available'   => $this->training->availableSessions($employeeId),
            'stats'       => $this->training->employeeStats($employeeId),
            'statuses'    => TrainingModel::PARTICIPANT_STATUSES,
            'statusTones' => TrainingModel::PARTICIPANT_TONES,
            'sessionStatuses' => TrainingModel::SESSION_STATUSES,
            'sessionTones' => TrainingModel::SESSION_TONES,
            'categories'  => TrainingModel::CATEGORIES,
            'categoryTones' => TrainingModel::CATEGORY_TONES,
            'modes'       => TrainingModel::MODES,
        ] + $this->withInput());
    }

    public function enroll(string $id): void
    {
        $result = $this->training->enroll((int) $id, $this->employeeId());
        if ($result['error'] !== null) {
            flash('error', $result['error'], ',', '"', '');
            redirect('/me/trainings');
        }

        Audit::log('training.enroll', 'training_session', (int) $id);
        flash('success', 'Pendaftaran "' . ($result['participant']['program'] ?? 'pelatihan')
            . '" terkirim. HR akan menyetujui keikutsertaan Anda.');
        redirect('/me/trainings');
    }

    public function cancelEnrollment(string $id): void
    {
        $result = $this->training->cancelOwn((int) $id, $this->employeeId());
        if ($result['error'] !== null) {
            flash('error', $result['error']);
            redirect('/me/trainings');
        }

        Audit::log('training.enroll_cancel', 'training_session', (int) $id);
        flash('success', 'Pendaftaran pelatihan dibatalkan.');
        redirect('/me/trainings');
    }

    /* ---------------------------------------------------------------- util */

    /** @return array{status:string,program:int,mode:string,q:string} */
    private function filters(): array
    {
        $filters = [
            'status'  => (string) ($_GET['status'] ?? ''),
            'program' => (int) ($_GET['program'] ?? 0),
            'mode'    => (string) ($_GET['mode'] ?? ''),
            'q'       => trim((string) ($_GET['q'] ?? '')),
        ];

        if (!isset(TrainingModel::SESSION_STATUSES[$filters['status']])) {
            $filters['status'] = '';
        }
        if (!isset(TrainingModel::MODES[$filters['mode']])) {
            $filters['mode'] = '';
        }

        return $filters;
    }

    /** @return array<string,mixed> */
    private function validatedSession(): array
    {
        $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('training_id', 'Program pelatihan')
              ->numeric('training_id', 'Program pelatihan', 1)
              ->in('mode', 'Mode pelaksanaan', array_keys(TrainingModel::MODES))
              ->required('start_date', 'Tanggal mulai')
              ->date('start_date', 'Tanggal mulai')
              ->date('end_date', 'Tanggal selesai')
              ->date('enroll_deadline', 'Batas pendaftaran')
              ->numeric('quota', 'Kuota', 0)
              ->custom((string) ($_POST['end_date'] ?? '') !== '' && (string) $_POST['end_date'] < (string) ($_POST['start_date'] ?? ''),
                  'end_date', 'Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }, '/trainings');

        $endDate = trim((string) input('end_date', ''));
        $deadline = trim((string) input('enroll_deadline', ''));

        return [
            'training_id'     => (int) input('training_id'),
            'mode'            => (string) input('mode', 'OFFLINE'),
            'location'        => trim((string) input('location', '')) ?: null,
            'start_date'      => (string) input('start_date'),
            'end_date'        => $endDate !== '' ? $endDate : null,
            'enroll_deadline' => $deadline !== '' ? $deadline : null,
            'quota'           => max(0, (int) input('quota', 0)),
            'note'            => trim((string) input('note', '')) ?: null,
        ];
    }

    /** @return array<string,mixed> */
    private function programPayload(): array
    {
        return [
            'code'                 => mb_strtoupper(trim((string) input('code', ''))),
            'name'                 => trim((string) input('name', '')),
            'category'             => (string) input('category', 'TEKNIS'),
            'type'                 => (string) input('type', 'INTERNAL'),
            'provider'             => trim((string) input('provider', '')) ?: null,
            'description'          => trim((string) input('description', '')) ?: null,
            'duration_hours'       => round((float) input('duration_hours', 0), 2),
            'cost_per_participant' => round((float) input('cost_per_participant', 0), 2),
            'is_mandatory'         => input('is_mandatory') ? 1 : 0,
            'is_active'            => input('is_active') ? 1 : 0,
        ];
    }

    /** Notifikasi pembukaan pendaftaran ke seluruh karyawan aktif. */
    private function notifyOpen(array $session): int
    {
        $employees = Database::all(
            "SELECT id FROM employees WHERE flag = 'ACTIVE'"
        );

        $now = date('Y-m-d H:i:s');
        $sent = 0;
        foreach ($employees as $employee) {
            $this->notifyEmployee(
                (int) $employee['id'],
                'Pendaftaran pelatihan dibuka',
                $session['program_name'] . ' — ' . date('d/m/Y', strtotime((string) $session['start_date']))
                    . '. Daftar melalui menu Pelatihan Saya.',
                '/me/trainings',
                'info',
                $now
            );
            $sent++;
        }
        return $sent;
    }

    private function notifyEmployee(int $employeeId, string $title, string $message, string $link, string $type = 'info', ?string $createdAt = null): void
    {
        try {
            Database::insert('notifications', [
                'employee_id' => $employeeId,
                'title'       => mb_substr($title, 0, 150),
                'message'     => mb_substr($message, 0, 255),
                'link'        => $link,
                'type'        => $type,
                'created_at'  => $createdAt ?? date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // notifikasi best-effort
        }
    }
}
