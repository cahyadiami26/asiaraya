<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\EmployeeCode;
use App\Core\Paginator;
use App\Core\Upload;
use App\Core\Validator;
use App\Models\EmployeeModel;
use App\Models\OrgModel;
use App\Models\RecruitmentModel;

/**
 * Rekrutmen & onboarding (ATS).
 *  - /recruitment                      : daftar lowongan + formulir (HR)
 *  - /recruitment/jobs/{id}            : pipeline pelamar satu lowongan (HR)
 *  - /recruitment/candidates           : daftar seluruh pelamar + input pelamar baru
 *  - /recruitment/candidates/{id}      : detail pelamar, tahapan, wawancara, hire
 */
final class RecruitmentController extends Controller
{
    private RecruitmentModel $recruitment;
    private OrgModel $org;
    private EmployeeModel $employees;

    public function __construct()
    {
        $this->recruitment = new RecruitmentModel();
        $this->org = new OrgModel();
        $this->employees = new EmployeeModel();
    }

    /* ------------------------------------------------------------ lowongan */

    public function index(): void
    {
        $this->requireRole('HR');

        $filters = [
            'status' => (string) ($_GET['status'] ?? ''),
            'unit'   => (int) ($_GET['unit'] ?? 0),
            'q'      => trim((string) ($_GET['q'] ?? '')),
        ];
        if (!isset(RecruitmentModel::JOB_STATUSES[$filters['status']])) {
            $filters['status'] = '';
        }

        $editing = null;
        $editId = (int) ($_GET['edit'] ?? 0);
        if ($editId > 0) {
            $editing = $this->recruitment->findJob($editId);
        }

        $this->view('recruitment/index', [
            'title'        => 'Rekrutmen & Onboarding',
            'pageTitle'    => 'Rekrutmen & Onboarding',
            'jobs'         => $this->recruitment->jobs($filters),
            'filters'      => $filters,
            'stats'        => $this->recruitment->stats(),
            'units'        => $this->org->units(),
            'departments'  => $this->org->departments(null),
            'positions'    => $this->org->positions(null),
            'managers'     => $this->org->employeeOptions(),
            'editing'      => $editing,
            'statuses'     => RecruitmentModel::JOB_STATUSES,
            'statusTones'  => RecruitmentModel::JOB_TONES,
            'workModes'    => RecruitmentModel::WORK_MODES,
            'employmentTypes' => RecruitmentModel::EMPLOYMENT_TYPES,
        ] + $this->withInput());
    }

    public function storeJob(): void
    {
        $this->requireRole('HR');
        $data = $this->validatedJob();

        if ($this->recruitment->jobByCode($data['code']) !== null) {
            flash('error', 'Kode lowongan "' . $data['code'] . '" sudah dipakai.');
            back('/recruitment');
        }

        $id = $this->recruitment->createJob($data, $this->employeeId());
        Audit::log('recruitment.job_create', 'job_vacancy', $id, ['code' => $data['code']]);
        flash('success', 'Lowongan "' . $data['title'] . '" dibuat sebagai draf. Buka lowongan untuk mulai menerima pelamar.');
        redirect('/recruitment/jobs/' . $id);
    }

    public function updateJob(string $id): void
    {
        $this->requireRole('HR');
        $job = $this->recruitment->findJob((int) $id);
        if (!$job) {
            $this->abort(404, 'Lowongan tidak ditemukan.');
        }

        $data = $this->validatedJob();
        if ($this->recruitment->jobByCode($data['code'], (int) $id) !== null) {
            flash('error', 'Kode lowongan "' . $data['code'] . '" sudah dipakai lowongan lain.');
            back('/recruitment/jobs/' . (int) $id);
        }

        $this->recruitment->updateJob((int) $id, $data);
        Audit::log('recruitment.job_update', 'job_vacancy', (int) $id);
        flash('success', 'Lowongan diperbarui.');
        redirect('/recruitment/jobs/' . (int) $id);
    }

    public function openJob(string $id): void
    {
        $this->requireRole('HR');
        $job = $this->recruitment->findJob((int) $id);
        if (!$job) {
            $this->abort(404, 'Lowongan tidak ditemukan.');
        }
        if ($job['status'] === 'OPEN') {
            flash('info', 'Lowongan ini sudah dibuka.');
            back('/recruitment/jobs/' . (int) $id);
        }

        $this->recruitment->setJobStatus((int) $id, 'OPEN');
        Audit::log('recruitment.job_open', 'job_vacancy', (int) $id);

        $managerId = (int) ($job['hiring_manager_id'] ?? 0);
        if ($managerId > 0) {
            $this->recruitment->notify(
                $managerId,
                'Lowongan dibuka',
                'Lowongan ' . $job['title'] . ' (' . $job['code'] . ') kini menerima pelamar.',
                '/recruitment/jobs/' . (int) $id,
                'info'
            );
        }

        flash('success', 'Lowongan dibuka. Pelamar dapat dicatat pada pipeline.');
        redirect('/recruitment/jobs/' . (int) $id);
    }

    public function holdJob(string $id): void
    {
        $this->requireRole('HR');
        $this->recruitment->setJobStatus((int) $id, 'ON_HOLD');
        Audit::log('recruitment.job_hold', 'job_vacancy', (int) $id);
        flash('warning', 'Lowongan ditahan sementara. Pipeline tetap tersimpan.');
        redirect('/recruitment/jobs/' . (int) $id);
    }

    public function closeJob(string $id): void
    {
        $this->requireRole('HR');
        $this->recruitment->setJobStatus((int) $id, 'CLOSED');
        Audit::log('recruitment.job_close', 'job_vacancy', (int) $id);
        flash('success', 'Lowongan ditutup. Pelamar pada pipeline tetap dapat diproses.');
        redirect('/recruitment/jobs/' . (int) $id);
    }

    public function destroyJob(string $id): void
    {
        $this->requireRole('HR');
        $job = $this->recruitment->findJob((int) $id);
        if (!$job) {
            $this->abort(404, 'Lowongan tidak ditemukan.');
        }
        if ($this->recruitment->jobApplicantCount((int) $id) > 0) {
            flash('error', 'Lowongan sudah memiliki pelamar — tutup atau tahan lowongan alih-alih menghapus.');
            back('/recruitment/jobs/' . (int) $id);
        }

        $this->recruitment->deleteJob((int) $id);
        Audit::log('recruitment.job_delete', 'job_vacancy', (int) $id);
        flash('success', 'Lowongan dihapus.');
        redirect('/recruitment');
    }

    /* -------------------------------------------------------------- pipeline */

    public function job(string $id): void
    {
        $this->requireRole('HR');
        $job = $this->recruitment->findJob((int) $id);
        if (!$job) {
            $this->abort(404, 'Lowongan tidak ditemukan.');
        }

        $this->view('recruitment/job', [
            'title'       => $job['title'],
            'pageTitle'   => 'Pipeline Rekrutmen',
            'job'         => $job,
            'funnel'      => $this->recruitment->funnel((int) $id),
            'grouped'     => $this->recruitment->candidatesByStage((int) $id),
            'statuses'    => RecruitmentModel::JOB_STATUSES,
            'statusTones' => RecruitmentModel::JOB_TONES,
            'stages'      => RecruitmentModel::STAGES,
            'stageTones'  => RecruitmentModel::STAGE_TONES,
            'pipeline'    => RecruitmentModel::PIPELINE,
            'workModes'   => RecruitmentModel::WORK_MODES,
            'sources'     => RecruitmentModel::SOURCES,
        ] + $this->withInput());
    }

    /* -------------------------------------------------------------- pelamar */

    public function candidates(): void
    {
        $this->requireRole('HR');

        $filters = $this->candidateFilters();
        $paginator = Paginator::fromRequest($this->recruitment->countCandidates($filters), 15);

        $this->view('recruitment/candidates', [
            'title'       => 'Data Pelamar',
            'pageTitle'   => 'Data Pelamar',
            'candidates'  => $this->recruitment->candidates($filters, $paginator->perPage, $paginator->offset()),
            'paginator'   => $paginator,
            'filters'     => $filters,
            'jobs'        => $this->recruitment->jobs(['status' => '', 'unit' => 0, 'q' => '']),
            'openJobs'    => $this->recruitment->openJobs(),
            'stats'       => $this->recruitment->stats(),
            'stages'      => RecruitmentModel::STAGES,
            'stageTones'  => RecruitmentModel::STAGE_TONES,
            'sources'     => RecruitmentModel::SOURCES,
            'educations'  => RecruitmentModel::EDUCATIONS,
            'pageLink'    => static function (int $page) use ($filters): string {
                $query = array_filter([
                    'job'    => $filters['job'] ?: null,
                    'status' => $filters['status'] ?: null,
                    'source' => $filters['source'] ?: null,
                    'q'      => $filters['q'] ?: null,
                    'page'   => $page > 1 ? $page : null,
                ], static fn ($v) => $v !== null && $v !== '');
                return url('recruitment/candidates' . ($query ? '?' . http_build_query($query) : ''));
            },
        ] + $this->withInput());
    }

    public function storeCandidate(): void
    {
        $this->requireRole('HR');

        $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('job_id', 'Lowongan')
              ->numeric('job_id', 'Lowongan', 1)
              ->required('full_name', 'Nama pelamar')
              ->required('email', 'Email')
              ->email('email', 'Email')
              ->in('source', 'Sumber pelamar', array_keys(RecruitmentModel::SOURCES))
              ->numeric('experience_years', 'Pengalaman', 0)
              ->numeric('expected_salary', 'Ekspektasi gaji', 0)
              ->date('birth_date', 'Tanggal lahir');
        }, '/recruitment/candidates');

        $email = mb_strtolower(trim((string) input('email', '')));
        if ($this->recruitment->candidateEmailExists($email)) {
            flash('error', 'Email ' . $email . ' sudah terdaftar pada pelamar lain.');
            back('/recruitment/candidates');
        }

        $job = $this->recruitment->findJob((int) input('job_id', 0));
        if (!$job) {
            flash('error', 'Lowongan yang dipilih tidak ditemukan.');
            back('/recruitment/candidates');
        }

        $upload = Upload::document('cv', 'recruitment', 4 * 1024 * 1024);
        if ($upload['error'] !== null) {
            flash('error', $upload['error']);
            back('/recruitment/candidates');
        }

        $education = trim((string) input('education', ''));
        $rating = (int) input('rating', 0);
        $id = $this->recruitment->createCandidate([
            'job_id'           => (int) $job['id'],
            'full_name'        => trim((string) input('full_name', '')),
            'email'            => $email,
            'phone'            => trim((string) input('phone', '')) ?: null,
            'birth_date'       => trim((string) input('birth_date', '')) ?: null,
            'gender'           => in_array(input('gender'), ['L', 'P'], true) ? (string) input('gender') : null,
            'education'        => in_array($education, RecruitmentModel::EDUCATIONS, true) ? $education : null,
            'experience_years' => round((float) input('experience_years', 0), 1),
            'current_company'  => trim((string) input('current_company', '')) ?: null,
            'current_position' => trim((string) input('current_position', '')) ?: null,
            'expected_salary'  => round((float) input('expected_salary', 0), 2),
            'source'           => (string) input('source', 'WEBSITE'),
            'rating'           => $rating >= 1 && $rating <= 5 ? $rating : null,
            'cv'               => $upload['path'],
            'cover_note'       => trim((string) input('cover_note', '')) ?: null,
        ], $this->employeeId());

        Audit::log('recruitment.candidate_create', 'candidate', $id, ['job' => (int) $job['id']]);
        flash('success', 'Pelamar ' . trim((string) input('full_name', '')) . ' ditambahkan ke pipeline ' . $job['title'] . '.');
        redirect('/recruitment/candidates/' . $id);
    }

    public function candidate(string $id): void
    {
        $this->requireRole('HR');
        $candidate = $this->recruitment->findCandidate((int) $id);
        if (!$candidate) {
            $this->abort(404, 'Pelamar tidak ditemukan.');
        }

        $this->view('recruitment/candidate', [
            'title'       => $candidate['full_name'],
            'pageTitle'   => 'Detail Pelamar',
            'candidate'   => $candidate,
            'history'     => $this->recruitment->stages((int) $id),
            'stages'      => RecruitmentModel::STAGES,
            'stageTones'  => RecruitmentModel::STAGE_TONES,
            'pipeline'    => RecruitmentModel::PIPELINE,
            'sources'     => RecruitmentModel::SOURCES,
            'educations'  => RecruitmentModel::EDUCATIONS,
            'interviewModes' => RecruitmentModel::INTERVIEW_MODES,
            'units'       => $this->org->units(),
            'departments' => $this->org->departments(null),
            'positions'   => $this->org->positions(null),
            'managers'    => $this->org->employeeOptions(),
            'employmentTypes' => RecruitmentModel::EMPLOYMENT_TYPES,
        ] + $this->withInput());
    }

    public function updateCandidate(string $id): void
    {
        $this->requireRole('HR');
        $candidate = $this->recruitment->findCandidate((int) $id);
        if (!$candidate) {
            $this->abort(404, 'Pelamar tidak ditemukan.');
        }

        $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('full_name', 'Nama pelamar')
              ->required('email', 'Email')
              ->email('email', 'Email')
              ->in('source', 'Sumber pelamar', array_keys(RecruitmentModel::SOURCES))
              ->numeric('experience_years', 'Pengalaman', 0)
              ->numeric('expected_salary', 'Ekspektasi gaji', 0)
              ->numeric('rating', 'Rating', 1)
              ->date('birth_date', 'Tanggal lahir');
        }, '/recruitment/candidates/' . (int) $id);

        $email = mb_strtolower(trim((string) input('email', '')));
        if ($this->recruitment->candidateEmailExists($email, (int) $id)) {
            flash('error', 'Email ' . $email . ' sudah dipakai pelamar lain.');
            back('/recruitment/candidates/' . (int) $id);
        }

        $upload = Upload::document('cv', 'recruitment', 4 * 1024 * 1024);
        if ($upload['error'] !== null) {
            flash('error', $upload['error']);
            back('/recruitment/candidates/' . (int) $id);
        }

        $rating = (int) input('rating', 0);
        $education = trim((string) input('education', ''));
        $payload = [
            'full_name'        => trim((string) input('full_name', '')),
            'email'            => $email,
            'phone'            => trim((string) input('phone', '')) ?: null,
            'birth_date'       => trim((string) input('birth_date', '')) ?: null,
            'gender'           => in_array(input('gender'), ['L', 'P'], true) ? (string) input('gender') : null,
            'education'        => in_array($education, RecruitmentModel::EDUCATIONS, true) ? $education : null,
            'experience_years' => round((float) input('experience_years', 0), 1),
            'current_company'  => trim((string) input('current_company', '')) ?: null,
            'current_position' => trim((string) input('current_position', '')) ?: null,
            'expected_salary'  => round((float) input('expected_salary', 0), 2),
            'source'           => (string) input('source', 'WEBSITE'),
            'rating'           => $rating >= 1 && $rating <= 5 ? $rating : null,
            'note'             => trim((string) input('note', '')) ?: null,
        ];
        if ($upload['path'] !== null) {
            Upload::delete($candidate['cv'] ?? null);
            $payload['cv'] = $upload['path'];
        }

        $this->recruitment->updateCandidate((int) $id, $payload);
        Audit::log('recruitment.candidate_update', 'candidate', (int) $id);
        flash('success', 'Data pelamar diperbarui.');
        redirect('/recruitment/candidates/' . (int) $id);
    }

    public function moveStage(string $id): void
    {
        $this->requireRole('HR');
        $candidate = $this->recruitment->findCandidate((int) $id);
        if (!$candidate) {
            $this->abort(404, 'Pelamar tidak ditemukan.');
        }
        if ($candidate['status'] === 'HIRED') {
            flash('error', 'Pelamar sudah diterima sebagai karyawan.');
            back('/recruitment/candidates/' . (int) $id);
        }

        $to = (string) input('status', '');
        $note = trim((string) input('note', ''));
        $extra = [];

        if ($to === 'INTERVIEW') {
            $interviewAt = trim((string) input('interview_at', ''));
            if ($interviewAt === '') {
                flash('error', 'Jadwal wawancara wajib diisi saat memindahkan pelamar ke tahap wawancara.');
                back('/recruitment/candidates/' . (int) $id);
            }
            $interviewerId = (int) input('interviewer_id', 0);
            $mode = (string) input('interview_mode', 'ONSITE');
            $extra['interview_at'] = $interviewAt;
            $extra['interviewer_id'] = $interviewerId > 0 ? $interviewerId : null;
            $extra['interview_mode'] = isset(RecruitmentModel::INTERVIEW_MODES[$mode]) ? $mode : 'ONSITE';
            if ($interviewerId > 0) {
                $this->recruitment->notify(
                    $interviewerId,
                    'Jadwal wawancara pelamar',
                    $candidate['full_name'] . ' — ' . $candidate['job_title'] . ' pada ' . date('d/m/Y H:i', strtotime($interviewAt)),
                    '/recruitment/candidates/' . (int) $id,
                    'info'
                );
            }
        }

        $result = $this->recruitment->moveStage((int) $id, $to, $note, $this->employeeId(), $extra);
        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/recruitment/candidates/' . (int) $id);
        }

        Audit::log('recruitment.stage', 'candidate', (int) $id, ['to' => $to]);
        flash('success', 'Tahap pelamar dipindahkan ke "' . (RecruitmentModel::STAGES[$to] ?? $to) . '".');
        redirect('/recruitment/candidates/' . (int) $id);
    }

    public function reject(string $id): void
    {
        $this->requireRole('HR');
        $reason = trim((string) input('reject_reason', ''));
        if ($reason === '') {
            flash('error', 'Alasan penolakan wajib diisi.');
            back('/recruitment/candidates/' . (int) $id);
        }

        $result = $this->recruitment->moveStage((int) $id, 'REJECTED', $reason, $this->employeeId(), [
            'reject_reason' => mb_substr($reason, 0, 255),
        ]);
        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('recruitment.reject', 'candidate', (int) $id);
            flash('success', 'Pelamar ditandai ditolak.');
        }
        redirect('/recruitment/candidates/' . (int) $id);
    }

    public function withdraw(string $id): void
    {
        $this->requireRole('HR');
        $result = $this->recruitment->moveStage((int) $id, 'WITHDRAWN', 'Pelamar mengundurkan diri.', $this->employeeId());
        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('recruitment.withdraw', 'candidate', (int) $id);
            flash('success', 'Pelamar ditandai mengundurkan diri.');
        }
        redirect('/recruitment/candidates/' . (int) $id);
    }

    /**
     * Terima pelamar & buat data karyawan baru.
     * Unit/departemen/posisi/gaji diambil dari formulir (prefill dari lowongan).
     */
    public function hire(string $id): void
    {
        $this->requireRole('HR');
        $candidate = $this->recruitment->findCandidate((int) $id);
        if (!$candidate) {
            $this->abort(404, 'Pelamar tidak ditemukan.');
        }
        if ($candidate['status'] === 'HIRED') {
            flash('info', 'Pelamar ini sudah dikonversi menjadi karyawan.');
            back('/recruitment/candidates/' . (int) $id);
        }

        $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('business_unit_id', 'Business unit')
              ->required('employment_status', 'Status kepegawaian')
              ->in('employment_status', 'Status kepegawaian', array_keys(RecruitmentModel::EMPLOYMENT_TYPES))
              ->required('join_date', 'Tanggal bergabung')
              ->date('join_date', 'Tanggal bergabung')
              ->numeric('basic_salary', 'Gaji pokok', 0)
              ->date('contract_end', 'Akhir kontrak');
        }, '/recruitment/candidates/' . (int) $id);

        $email = mb_strtolower((string) $candidate['email']);
        if ($this->employees->emailExists($email)) {
            flash('error', 'Email ' . $email . ' sudah dipakai karyawan lain. Perbarui email pelamar terlebih dahulu.');
            back('/recruitment/candidates/' . (int) $id);
        }

        $unitId = (int) input('business_unit_id', 0);
        $departmentId = (int) input('department_id', 0);
        $employmentStatus = (string) input('employment_status', 'PKWT');
        $contractEnd = trim((string) input('contract_end', ''));

        if (in_array($employmentStatus, ['PKWT', 'PKWT (Harian)', 'Magang', 'Outsourcing'], true) && $contractEnd === '') {
            flash('error', 'Kontrak berjangka wajib memiliki tanggal akhir kontrak.');
            back('/recruitment/candidates/' . (int) $id);
        }

        $code = EmployeeCode::next($unitId > 0 ? $unitId : null, $departmentId > 0 ? $departmentId : null);
        $username = strtolower((string) preg_replace('/[^a-z0-9.]+/', '.', str_replace(' ', '.', mb_strtolower($email))));

        $employeeId = $this->employees->create([
            'employee_code'     => $code,
            'username'          => $username,
            'full_name'         => (string) $candidate['full_name'],
            'email'             => $email,
            'phone'             => $candidate['phone'],
            'birth_date'        => $candidate['birth_date'],
            'gender'            => $candidate['gender'],
            'role'              => 'KARYAWAN',
            'flag'              => 'ACTIVE',
            'business_unit_id'  => $unitId,
            'department_id'     => $departmentId > 0 ? $departmentId : null,
            'position_id'       => (int) input('position_id', 0) ?: null,
            'direct_manager_id' => (int) input('direct_manager_id', 0) ?: null,
            'employment_status' => $employmentStatus,
            'basic_salary'      => (float) input('basic_salary', 0),
            'join_date'         => (string) input('join_date'),
            'contract_start'    => (string) input('join_date'),
            'contract_end'      => $contractEnd !== '' ? $contractEnd : null,
            'password'          => 'Password123!',
        ]);

        $this->recruitment->markHired((int) $id, $employeeId, 'Diterima & dikonversi menjadi karyawan ' . $code, $this->employeeId());

        $this->recruitment->notify(
            $employeeId,
            'Selamat bergabung di PT Asia Raya Group',
            'Akun HRIS Anda aktif. Lengkapi profil dan unduh aplikasi presensi.',
            '/me/profile',
            'success'
        );

        Audit::log('recruitment.hire', 'candidate', (int) $id, ['employee' => $employeeId, 'code' => $code]);
        flash('success', 'Selamat! ' . $candidate['full_name'] . ' resmi menjadi karyawan dengan ID ' . $code . '.');
        redirect('/employees/' . $employeeId);
    }

    public function export(): void
    {
        $this->requireRole('HR');
        $filters = $this->candidateFilters();
        $rows = $this->recruitment->candidates($filters, 5000, 0);

        $filename = 'pelamar-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'ID', 'Lowongan', 'Kode Lowongan', 'Nama Pelamar', 'Email', 'Telepon', 'Pendidikan',
            'Pengalaman (tahun)', 'Perusahaan Saat Ini', 'Ekspektasi Gaji', 'Sumber', 'Tahap',
            'Rating', 'Jadwal Wawancara', 'Tanggal Masuk Pipeline',
        ], ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($out, [
                (int) $row['id'],
                $row['job_title'],
                $row['job_code'],
                $row['full_name'],
                $row['email'],
                $row['phone'] ?? '',
                $row['education'] ?? '',
                number_format((float) $row['experience_years'], 1, '.', ''),
                $row['current_company'] ?? '',
                $row['expected_salary'] !== null ? number_format((float) $row['expected_salary'], 2, '.', '') : '',
                RecruitmentModel::SOURCES[$row['source']] ?? $row['source'],
                RecruitmentModel::STAGES[$row['status']] ?? $row['status'],
                $row['rating'] !== null ? (string) (int) $row['rating'] : '',
                $row['interview_at'] ?? '',
                $row['created_at'] ?? '',
            ], ',', '"', '');
        }
        fclose($out);
        exit;
    }

    /* ---------------------------------------------------------------- util */

    /** @return array{job:int,status:string,source:string,q:string} */
    private function candidateFilters(): array
    {
        $filters = [
            'job'    => (int) ($_GET['job'] ?? 0),
            'status' => (string) ($_GET['status'] ?? ''),
            'source' => (string) ($_GET['source'] ?? ''),
            'q'      => trim((string) ($_GET['q'] ?? '')),
        ];
        if (!isset(RecruitmentModel::STAGES[$filters['status']])) {
            $filters['status'] = '';
        }
        if (!isset(RecruitmentModel::SOURCES[$filters['source']])) {
            $filters['source'] = '';
        }
        return $filters;
    }

    /** @return array<string,mixed> */
    private function validatedJob(): array
    {
        $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('title', 'Judul lowongan')
              ->required('code', 'Kode lowongan')
              ->in('employment_type', 'Status kepegawaian', array_keys(RecruitmentModel::EMPLOYMENT_TYPES))
              ->in('work_mode', 'Mode kerja', array_keys(RecruitmentModel::WORK_MODES))
              ->numeric('headcount', 'Kuota', 1)
              ->numeric('salary_min', 'Gaji minimum', 0)
              ->numeric('salary_max', 'Gaji maksimum', 0)
              ->date('open_date', 'Tanggal buka')
              ->date('close_date', 'Tanggal tutup')
              ->custom((float) input('salary_max', 0) > 0 && (float) input('salary_max', 0) < (float) input('salary_min', 0),
                  'salary_max', 'Gaji maksimum tidak boleh lebih kecil dari gaji minimum.')
              ->custom((string) input('close_date', '') !== '' && (string) input('open_date', '') !== ''
                  && (string) input('close_date') < (string) input('open_date'),
                  'close_date', 'Tanggal tutup tidak boleh sebelum tanggal buka.');
        }, '/recruitment');

        $openDate = trim((string) input('open_date', ''));

        return [
            'code'              => mb_strtoupper(trim((string) input('code', ''))),
            'title'             => trim((string) input('title', '')),
            'business_unit_id'  => (int) input('business_unit_id', 0) ?: null,
            'department_id'     => (int) input('department_id', 0) ?: null,
            'position_id'       => (int) input('position_id', 0) ?: null,
            'employment_type'   => (string) input('employment_type', 'PKWT'),
            'work_mode'         => (string) input('work_mode', 'ONSITE'),
            'location'          => trim((string) input('location', '')) ?: null,
            'headcount'         => max(1, (int) input('headcount', 1)),
            'description'       => trim((string) input('description', '')) ?: null,
            'requirements'      => trim((string) input('requirements', '')) ?: null,
            'salary_min'        => round((float) input('salary_min', 0), 2),
            'salary_max'        => round((float) input('salary_max', 0), 2),
            'open_date'         => $openDate !== '' ? $openDate : date('Y-m-d'),
            'close_date'        => trim((string) input('close_date', '')) ?: null,
            'hiring_manager_id' => (int) input('hiring_manager_id', 0) ?: null,
            'note'              => trim((string) input('note', '')) ?: null,
        ];
    }
}
