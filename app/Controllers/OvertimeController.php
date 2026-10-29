<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Paginator;
use App\Core\Validator;
use App\Models\OvertimeModel;
use App\Models\OrgModel;

/**
 * Lembur: pengajuan mandiri, penjadwalan massal oleh HR, keputusan, dan rekap.
 */
final class OvertimeController extends Controller
{
    private OvertimeModel $overtime;
    private OrgModel $org;

    public function __construct()
    {
        $this->overtime = new OvertimeModel();
        $this->org = new OrgModel();
    }

    /* ------------------------------------------------------------------- HR list */

    public function index(): void
    {
        $filters = $this->filters();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 15;
        $total = $this->overtime->count($filters);
        $paginator = new Paginator($total, $perPage, min($page, max(1, (int) ceil($total / $perPage))));

        $month = $filters['month'] ?: date('Y-m');

        $this->view('overtime/index', [
            'title'      => 'Manajemen Lembur',
            'pageTitle'  => 'Manajemen Lembur',
            'rows'       => $this->overtime->requests($filters, $perPage, $paginator->offset()),
            'paginator'  => $paginator,
            'filters'    => $filters,
            'stats'      => $this->overtime->stats($filters),
            'daily'      => $this->overtime->dailyHours($month),
            'units'      => $this->org->units(),
            'departments' => $this->org->departments(),
            'employees'  => $this->overtime->employeeOptions(),
            'month'      => $month,
            'monthLabel' => $this->monthLabel($month),
            'prevMonth'  => $this->overtime->adjacentMonth($month, -1),
            'nextMonth'  => $this->overtime->adjacentMonth($month, +1),
            'policy'     => $this->overtime->settings(),
            'preview'    => $this->overtime->policyPreview(),
            'top'        => $this->overtime->topEmployees($month, 5),
            'byUnit'     => $this->overtime->byUnit($month),
        ] + $this->withInput());
    }

    /** HR membuat entri lembur manual (satu karyawan). */
    public function store(): void
    {
        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('employee_id', 'Karyawan')
                ->required('work_date', 'Tanggal')
                ->required('planned_start', 'Jam mulai')
                ->required('planned_end', 'Jam selesai')
                ->date('work_date', 'Tanggal');
        });

        $type = strtoupper((string) ($data['type'] ?? 'REQUEST')) === 'SCHEDULED' ? 'SCHEDULED' : 'REQUEST';

        $result = $this->overtime->create([
            'employee_id'   => (int) $data['employee_id'],
            'work_date'     => (string) $data['work_date'],
            'planned_start' => (string) $data['planned_start'],
            'planned_end'   => (string) $data['planned_end'],
            'reason'        => (string) ($data['reason'] ?? ''),
            'type'          => $type,
            'auto_approve'  => (int) ($data['auto_approve'] ?? 0),
        ], $this->employeeId());

        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/overtime');
        }

        $record = $this->overtime->find((int) $result['id']);
        Audit::log('overtime.create', 'overtime_request', (int) $result['id'], [
            'type' => $type,
            'hours' => (float) ($record['planned_hours'] ?? 0),
        ]);

        if ((string) ($record['status'] ?? '') === 'APPROVED') {
            $this->notify((int) $record['employee_id'], 'approved', $record);
            flash('success', 'Lembur terjadwal untuk ' . $record['full_name'] . ' berhasil ditambahkan.');
        } else {
            flash('success', 'Pengajuan lembur untuk ' . $record['full_name'] . ' dibuat dan menunggu persetujuan.');
        }
        redirect('/overtime');
    }

    public function update(string $id): void
    {
        $row = $this->overtime->find((int) $id);
        if (!$row) {
            $this->abort(404, 'Data lembur tidak ditemukan.');
        }

        $result = $this->overtime->update((int) $id, [
            'planned_start' => (string) input('planned_start', $row['planned_start']),
            'planned_end'   => (string) input('planned_end', $row['planned_end']),
            'reason'        => (string) input('reason', $row['reason']),
        ]);

        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/overtime');
        }

        Audit::log('overtime.update', 'overtime_request', (int) $id);
        flash('success', 'Detail lembur ' . $row['full_name'] . ' diperbarui.');
        redirect('/overtime');
    }

    /** Simpan jam lembur aktual (setelah pekerjaan selesai). */
    public function actual(string $id): void
    {
        $row = $this->overtime->find((int) $id);
        if (!$row) {
            $this->abort(404, 'Data lembur tidak ditemukan.');
        }

        $hours = (float) input('actual_hours', 0);
        $result = $this->overtime->setActual((int) $id, $hours);
        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/overtime');
        }

        Audit::log('overtime.actual', 'overtime_request', (int) $id, ['hours' => $result['hours'] ?? $hours]);
        flash('success', 'Jam lembur aktual ' . $row['full_name'] . ' disimpan (' . number_format((float) ($result['hours'] ?? $hours), 2, ',', '.') . ' jam).');
        back('/overtime');
    }

    public function cancel(string $id): void
    {
        $row = $this->overtime->find((int) $id);
        if (!$row) {
            $this->abort(404, 'Data lembur tidak ditemukan.');
        }

        $result = $this->overtime->cancel((int) $id, (string) input('note', ''), 'HR');
        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/overtime');
        }

        Audit::log('overtime.cancel', 'overtime_request', (int) $id);
        $this->notify((int) $row['employee_id'], 'cancelled', $row);
        flash('success', 'Lembur ' . $row['full_name'] . ' dibatalkan.');
        back('/overtime');
    }

    /** Keputusan massal tahap HR. */
    public function bulkDecide(): void
    {
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
        if (!$ids) {
            flash('error', 'Pilih minimal satu pengajuan lembur.');
            back('/overtime');
        }

        $decision = strtoupper((string) input('decision', 'APPROVED')) === 'REJECTED' ? 'REJECTED' : 'APPROVED';
        $note = substr(trim((string) input('note', '')), 0, 255);
        $done = $this->overtime->decideMany($ids, $decision === 'APPROVED', $this->employeeId(), $note);

        foreach ($ids as $id) {
            $row = $this->overtime->find($id);
            if ($row) {
                $this->notify((int) $row['employee_id'], $decision === 'APPROVED' ? 'approved' : 'rejected', $row);
            }
        }

        Audit::log('overtime.bulk-decide', 'overtime_request', null, ['ids' => $ids, 'decision' => $decision]);
        flash($decision === 'APPROVED' ? 'success' : 'warning', $done . ' pengajuan lembur ' . ($decision === 'APPROVED' ? 'disetujui' : 'ditolak') . '.');
        back('/overtime');
    }

    /* -------------------------------------------------------- scheduled overtime */

    public function schedules(): void
    {
        $filters = $this->filters();
        $filters['type'] = 'SCHEDULED';
        if (($filters['month'] ?? '') === '') {
            $filters['month'] = date('Y-m');
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $total = $this->overtime->count($filters);
        $paginator = new Paginator($total, $perPage, min($page, max(1, (int) ceil($total / $perPage))));

        $month = (string) $filters['month'];

        $this->view('overtime/schedules', [
            'title'       => 'Lembur Terjadwal',
            'pageTitle'   => 'Manajemen Lembur Terjadwal',
            'rows'        => $this->overtime->requests($filters, $perPage, $paginator->offset()),
            'paginator'   => $paginator,
            'filters'     => $filters,
            'stats'       => $this->overtime->stats($filters),
            'units'       => $this->org->units(),
            'departments' => $this->org->departments(),
            'employees'   => $this->overtime->employeeOptions(),
            'month'       => $month,
            'monthLabel'  => $this->monthLabel($month),
            'prevMonth'   => $this->overtime->adjacentMonth($month, -1),
            'nextMonth'   => $this->overtime->adjacentMonth($month, +1),
            'policy'      => $this->overtime->settings(),
            'byUnit'      => $this->overtime->byUnit($month),
            'daily'       => $this->overtime->dailyHours($month),
        ] + $this->withInput());
    }

    public function storeScheduled(): void
    {
        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('date_from', 'Tanggal mulai')
                ->required('planned_start', 'Jam mulai')
                ->required('planned_end', 'Jam selesai')
                ->date('date_from', 'Tanggal mulai')
                ->date('date_to', 'Tanggal selesai');
        });

        $scope = strtolower((string) ($_POST['scope'] ?? 'unit'));
        $selected = array_map('intval', (array) ($_POST['employee_ids'] ?? []));
        if ($scope === 'selected' && !$selected) {
            flash('error', 'Pilih minimal satu karyawan untuk penjadwalan lembur.');
            back('/overtime/schedules');
        }
        if ($scope === 'unit' && (int) ($_POST['unit'] ?? 0) <= 0) {
            flash('error', 'Pilih unit terlebih dahulu untuk penjadwalan lembur.');
            back('/overtime/schedules');
        }
        if ($scope === 'department' && (int) ($_POST['department'] ?? 0) <= 0) {
            flash('error', 'Pilih departemen terlebih dahulu untuk penjadwalan lembur.');
            back('/overtime/schedules');
        }

        $result = $this->overtime->scheduleBulk([
            'employee_ids'  => $scope === 'selected' ? $selected : [],
            'unit'          => (int) ($_POST['unit'] ?? 0),
            'department'    => (int) ($_POST['department'] ?? 0),
            'date_from'     => (string) $data['date_from'],
            'date_to'       => (string) ($data['date_to'] ?? $data['date_from']),
            'planned_start' => (string) $data['planned_start'],
            'planned_end'   => (string) $data['planned_end'],
            'reason'        => (string) ($data['reason'] ?? ''),
            'skip_weekend'  => (int) ($_POST['skip_weekend'] ?? 0),
            'skip_holiday'  => (int) ($_POST['skip_holiday'] ?? 0),
            'auto_approve'  => (int) ($_POST['auto_approve'] ?? 1),
        ], $this->employeeId());

        if ($result['created'] === 0) {
            $reason = $result['conflicts'][0] ?? 'Tidak ada karyawan yang memenuhi kriteria.';
            flash('error', 'Tidak ada lembur yang dijadwalkan. ' . $reason);
            back('/overtime/schedules');
        }

        foreach ($result['ids'] as $index => $recordId) {
            if ($index >= 200) {
                break;
            }
            $row = $this->overtime->find($recordId);
            if ($row && (string) $row['status'] === 'APPROVED') {
                $this->notify((int) $row['employee_id'], 'scheduled', $row);
            }
        }

        Audit::log('overtime.schedule-bulk', 'overtime_request', null, [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ]);

        $message = $result['created'] . ' lembur terjadwal dibuat untuk ' . $result['employees'] . ' karyawan.';
        if ($result['skipped'] > 0) {
            $message .= ' ' . $result['skipped'] . ' dilewati (bentrok/duplikat).';
        }
        flash($result['skipped'] > 0 ? 'warning' : 'success', $message);

        back('/overtime/schedules');
    }

    /**
     * Approver final (HR) menyimpan kebijakan lembur: batas jam, batas maksimum
     * kompensasi, multiplier, durasi minimum, dan status modul.
     */
    public function savePolicy(): void
    {
        $result = $this->overtime->savePolicy([
            'enabled'     => (int) ($_POST['enabled'] ?? 0),
            'max_hours'   => (float) ($_POST['max_hours'] ?? 0),
            'max_amount'  => (float) ($_POST['max_amount'] ?? 0),
            'min_minutes' => (int) ($_POST['min_minutes'] ?? 0),
            'divisor'     => (float) ($_POST['divisor'] ?? 0),
            'first'       => (float) ($_POST['first'] ?? 0),
            'next'        => (float) ($_POST['next'] ?? 0),
            'holiday'     => (float) ($_POST['holiday'] ?? 0),
        ], $this->employeeId());

        if (!$result['saved']) {
            flash('error', reset($result['errors']) ?: 'Kebijakan lembur gagal disimpan.');
            back('/overtime');
        }

        $policy = $this->overtime->settings();
        Audit::log('overtime.policy', 'settings', null, [
            'max_hours'  => $policy['max_hours'],
            'max_amount' => $policy['max_amount'],
        ]);

        flash('success', 'Kebijakan lembur diperbarui: maksimum '
            . number_id($policy['max_hours']) . ' jam/hari dan kompensasi maksimum '
            . ($policy['max_amount'] > 0 ? rupiah($policy['max_amount']) : 'tanpa batas') . '.');
        redirect('/overtime');
    }

    /* ------------------------------------------------------------------- export */

    public function export(): void
    {
        $filters = $this->filters();
        $rows = $this->overtime->exportRows($filters);

        $filename = 'lembur-' . ($filters['month'] ?: date('Y-m')) . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'Tanggal', 'ID Karyawan', 'Nama', 'Unit', 'Departemen', 'Jenis',
            'Mulai', 'Selesai', 'Jam Rencana', 'Jam Aktual', 'Status',
            'Kompensasi (Rp)', 'Alasan', 'Disetujui HR',
        ], ';', '"', '');

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['work_date'],
                $row['employee_code'],
                $row['full_name'],
                $row['unit_name'] ?? '',
                $row['department_name'] ?? '',
                $row['type'] === 'SCHEDULED' ? 'Terjadwal' : 'Pengajuan',
                substr((string) $row['planned_start'], 0, 5),
                substr((string) $row['planned_end'], 0, 5),
                number_format((float) $row['planned_hours'], 2, ',', ''),
                $row['actual_hours'] !== null ? number_format((float) $row['actual_hours'], 2, ',', '') : '',
                OvertimeModel::STATUS_LABELS[$row['status']] ?? $row['status'],
                number_format((float) $row['compensation_amount'], 0, ',', ''),
                $row['reason'] ?? '',
                $row['hr_name'] ?? '',
            ], ';', '"', '');
        }
        fclose($out);
        exit;
    }

    /* -------------------------------------------------------------- self service */

    public function mine(): void
    {
        $employeeId = $this->employeeId();
        $month = (string) ($_GET['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $rows = $this->overtime->mine($employeeId, 60);
        $open = array_values(array_filter($rows, static fn ($row) => in_array((string) $row['status'], OvertimeModel::STATUS_OPEN, true)));

        $this->view('overtime/mine', [
            'title'      => 'Lembur Saya',
            'pageTitle'  => 'Lembur Saya',
            'rows'       => $rows,
            'open'       => $open,
            'summary'    => $this->overtime->mySummary($employeeId, $month),
            'policy'      => $this->overtime->settings(),
            'basicSalary' => (float) (Database::value('SELECT basic_salary FROM employees WHERE id = ?', [$employeeId]) ?? 0),
            'preview'     => $this->overtime->policyPreview((float) (Database::value('SELECT basic_salary FROM employees WHERE id = ?', [$employeeId]) ?? 0)),
            'month'      => $month,
            'monthLabel' => $this->monthLabel($month),
            'today'      => date('Y-m-d'),
        ] + $this->withInput());
    }

    public function request(): void
    {
        $employeeId = $this->employeeId();
        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('work_date', 'Tanggal lembur')
                ->required('planned_start', 'Jam mulai')
                ->required('planned_end', 'Jam selesai')
                ->date('work_date', 'Tanggal lembur')
                ->custom(mb_strlen((string) ($_POST['reason'] ?? '')) > 255, 'reason', 'Alasan lembur maksimal 255 karakter.');
        }, '/me/overtime');

        $result = $this->overtime->create([
            'employee_id'   => $employeeId,
            'work_date'     => (string) $data['work_date'],
            'planned_start' => (string) $data['planned_start'],
            'planned_end'   => (string) $data['planned_end'],
            'reason'        => (string) ($data['reason'] ?? ''),
            'type'          => 'REQUEST',
        ], $employeeId);

        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/me/overtime');
        }

        Audit::log('overtime.request', 'overtime_request', (int) $result['id']);
        $this->notifyManager($employeeId, (int) $result['id']);
        flash('success', 'Pengajuan lembur terkirim dan menunggu persetujuan atasan langsung.');
        redirect('/me/overtime');
    }

    public function myCancel(string $id): void
    {
        $employeeId = $this->employeeId();
        $row = Database::one('SELECT * FROM overtime_requests WHERE id = ?', [(int) $id]);
        if (!$row) {
            $this->abort(404, 'Pengajuan lembur tidak ditemukan.');
        }
        if ((int) $row['employee_id'] !== $employeeId) {
            $this->abort(403, 'Anda hanya dapat membatalkan pengajuan lembur milik sendiri.');
        }
        if (!in_array((string) $row['status'], ['PENDING'], true)) {
            flash('error', 'Pengajuan yang sudah diproses atasan tidak dapat dibatalkan sendiri. Hubungi HR.');
            back('/me/overtime');
        }

        $this->overtime->cancel((int) $id, 'Dibatalkan karyawan', 'Karyawan');
        Audit::log('overtime.cancel-self', 'overtime_request', (int) $id);
        flash('success', 'Pengajuan lembur dibatalkan.');
        redirect('/me/overtime');
    }

    /* ------------------------------------------------------------------ internal */

    private function filters(): array
    {
        $month = (string) ($_GET['month'] ?? '');
        if ($month !== '' && !preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = '';
        }

        return [
            'status'     => strtoupper((string) ($_GET['status'] ?? 'ALL')),
            'type'       => strtoupper((string) ($_GET['type'] ?? '')),
            'unit'       => (int) ($_GET['unit'] ?? 0),
            'department' => (int) ($_GET['department'] ?? 0),
            'employee'   => (int) ($_GET['employee'] ?? 0),
            'month'      => $month,
            'q'          => trim((string) ($_GET['q'] ?? '')),
        ];
    }

    private function monthLabel(string $month): string
    {
        $ts = strtotime($month . '-01');
        if ($ts === false) {
            return $month;
        }
        $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    }

    /** Notifikasi sederhana ke karyawan terkait. */
    private function notify(int $employeeId, string $event, array $row): void
    {
        $date = tanggal((string) $row['work_date']);
        $hours = (float) ($row['actual_hours'] ?: $row['planned_hours']);
        $messages = [
            'approved'  => ['Lembur disetujui', 'Lembur ' . $hours . ' jam pada ' . $date . ' telah disetujui.'],
            'rejected'  => ['Lembur ditolak', 'Pengajuan lembur pada ' . $date . ' ditolak.'],
            'cancelled' => ['Lembur dibatalkan', 'Lembur pada ' . $date . ' dibatalkan oleh HR.'],
            'scheduled' => ['Lembur terjadwal', 'Anda dijadwalkan lembur ' . $hours . ' jam pada ' . $date . '.'],
        ];
        [$title, $message] = $messages[$event] ?? ['Pembaruan lembur', 'Status lembur pada ' . $date . ' diperbarui.'];

        try {
            Database::insert('notifications', [
                'employee_id' => $employeeId,
                'title'       => $title,
                'message'     => $message,
                'link'        => '/me/overtime',
                'type'        => $event === 'rejected' || $event === 'cancelled' ? 'warning' : 'info',
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // notifikasi bersifat pelengkap; abaikan kegagalan penyimpanan
        }
    }

    /** Beri tahu atasan langsung saat karyawan mengajukan lembur. */
    private function notifyManager(int $employeeId, int $requestId): void
    {
        $managerId = Database::value('SELECT direct_manager_id FROM employees WHERE id = ?', [$employeeId]);
        if (!$managerId) {
            return;
        }
        try {
            Database::insert('notifications', [
                'employee_id' => (int) $managerId,
                'title'       => 'Pengajuan lembur baru',
                'message'     => 'Menunggu persetujuan Anda sebagai atasan langsung.',
                'link'        => '/overtime?status=OPEN',
                'type'        => 'info',
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }
}
