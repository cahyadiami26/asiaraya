<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Paginator;
use App\Core\Upload;
use App\Core\Validator;
use App\Models\LeaveModel;
use App\Models\OrgModel;

/**
 * Cuti: master jenis & jatah, saldo tahunan, pengajuan karyawan, dan antrean persetujuan.
 */
final class LeaveController extends Controller
{
    private LeaveModel $leave;
    private OrgModel $org;

    public function __construct()
    {
        $this->leave = new LeaveModel();
        $this->org = new OrgModel();
    }

    /* ------------------------------------------------------------- HR overview */

    public function index(): void
    {
        $user = $this->employee() ?? [];
        $isHr = ($user['role'] ?? '') === 'HR';

        $filters = $this->filters();
        $filters['year'] = (int) ($_GET['year'] ?? date('Y'));

        // Atasan langsung (non-HR) hanya melihat pengajuan anggota timnya.
        if (!$isHr) {
            $filters['manager'] = (int) ($user['id'] ?? 0);
            if (($filters['status'] ?? '') === '' || $filters['status'] === 'ALL') {
                $filters['status'] = 'OPEN';
            }
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 12;
        $total = $this->leave->countRequests($filters);
        $paginator = new Paginator($total, $perPage, min($page, max(1, (int) ceil($total / $perPage))));

        $this->view('leave/index', [
            'isHr'        => $isHr,
            'canDecide'   => fn (array $row): bool => self::canDecide($row),
            'title'       => 'Manajemen Cuti',
            'pageTitle'   => 'Manajemen Cuti',
            'rows'        => $this->leave->requests($filters, $perPage, $paginator->offset()),
            'paginator'   => $paginator,
            'filters'     => $filters,
            'stats'       => $this->leave->stats($filters),
            'balanceStats' => $this->leave->balanceStats((int) $filters['year']),
            'byType'      => $this->leave->byType((int) $filters['year']),
            'upcoming'    => $this->leave->upcoming(6),
            'types'       => $this->leave->types(true),
            'units'       => $this->org->units(),
            'departments' => $this->org->departments(),
            'employees'   => $this->leave->employeeOptions(),
            'years'       => $this->leave->yearOptions(),
            'policy'      => $this->leave->settings(),
        ] + $this->withInput());
    }

    /** Atasan di tahap 1, HR di tahap final (tidak boleh menyetujui cuti sendiri). */
    public static function canDecide(array $request): bool
    {
        $user = auth();
        if (!$user) {
            return false;
        }
        $status = (string) $request['status'];
        $employeeId = (int) $request['employee_id'];

        if ($status === 'PENDING' && \App\Core\Auth::isManagerOf($employeeId)) {
            return true;
        }

        return ($user['role'] ?? '') === 'HR'
            && in_array($status, LeaveModel::STATUS_OPEN, true)
            && $employeeId !== (int) $user['id'];
    }

    public function export(): void
    {
        $filters = $this->filters();
        $filters['year'] = (int) ($_GET['year'] ?? date('Y'));

        $params = [];
        $rows = Database::all(
            "SELECT lr.*, lt.name AS type_name, lt.is_paid, e.full_name, e.employee_code,
                    bu.name AS unit_name, d.name AS department_name, m.full_name AS manager_name, h.full_name AS hr_name
             FROM leave_requests lr
             JOIN leave_types lt ON lt.id = lr.leave_type_id
             JOIN employees e ON e.id = lr.employee_id
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN employees m ON m.id = lr.manager_id
             LEFT JOIN employees h ON h.id = lr.hr_id
             WHERE lr.start_date BETWEEN ? AND ?
             ORDER BY lr.start_date DESC",
            [$filters['year'] . '-01-01', $filters['year'] . '-12-31']
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cuti-' . $filters['year'] . '.csv"');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'ID', 'ID Karyawan', 'Nama', 'Unit', 'Departemen', 'Jenis Cuti', 'Dibayar',
            'Mulai', 'Selesai', 'Hari', 'Status', 'Atasan', 'HR', 'Alasan', 'Lampiran',
        ], ';', '"', '');

        foreach ($rows as $row) {
            fputcsv($out, [
                (int) $row['id'],
                $row['employee_code'],
                $row['full_name'],
                $row['unit_name'] ?? '',
                $row['department_name'] ?? '',
                $row['type_name'],
                (int) $row['is_paid'] === 1 ? 'Ya' : 'Tidak',
                $row['start_date'],
                $row['end_date'],
                number_format((float) $row['days'], 1, ',', ''),
                LeaveModel::STATUS_LABELS[$row['status']] ?? $row['status'],
                $row['manager_name'] ?? '',
                $row['hr_name'] ?? '',
                $row['reason'] ?? '',
                $row['attachment'] ? basename((string) $row['attachment']) : '',
            ], ';', '"', '');
        }
        fclose($out);
        exit;
    }

    /** HR membatalkan cuti (mengembalikan saldo bila sudah disetujui). */
    public function cancel(string $id): void
    {
        $request = $this->leave->find((int) $id);
        if (!$request) {
            $this->abort(404, 'Pengajuan cuti tidak ditemukan.');
        }

        $result = $this->leave->cancel((int) $id, 'HR', (string) input('note', 'Dibatalkan HR'));
        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/leave');
        }

        Audit::log('leave.cancel', 'leave_request', (int) $id, ['was' => $request['status']]);
        $this->notify((int) $request['employee_id'], 'cancelled', $request);
        flash('success', 'Cuti ' . $request['full_name'] . ' dibatalkan' . ($request['status'] === 'APPROVED' ? ' dan saldo dikembalikan.' : '.'));
        back('/leave');
    }

    /* ------------------------------------------------------------ types & quota */

    public function types(): void
    {
        $editId = (int) ($_GET['edit'] ?? 0);

        $this->view('leave/types', [
            'title'     => 'Jenis & Jatah Cuti',
            'pageTitle' => 'Jenis & Jatah Cuti',
            'types'     => $this->leave->types(),
            'editing'   => $editId ? $this->leave->findType($editId) : null,
            'policy'    => $this->leave->settings(),
            'stats'     => $this->leave->balanceStats((int) date('Y'), ['status' => 'LOW']),
        ] + $this->withInput());
    }

    public function storeType(): void
    {
        $data = $this->validateType();

        if ($this->leave->typeCodeExists((string) $data['code'])) {
            flash('error', 'Kode jenis cuti "' . $data['code'] . '" sudah digunakan.');
            back('/leave/types');
        }

        $id = $this->leave->createType($data);
        Audit::log('leave.type-create', 'leave_type', $id, ['code' => $data['code']]);
        flash('success', 'Jenis cuti ' . $data['name'] . ' berhasil ditambahkan.');
        redirect('/leave/types');
    }

    public function updateType(string $id): void
    {
        $type = $this->leave->findType((int) $id);
        if (!$type) {
            $this->abort(404, 'Jenis cuti tidak ditemukan.');
        }

        $data = $this->validateType();
        if ($this->leave->typeCodeExists((string) $data['code'], (int) $id)) {
            flash('error', 'Kode jenis cuti "' . $data['code'] . '" sudah dipakai jenis lain.');
            back('/leave/types');
        }

        $this->leave->updateType((int) $id, $data);
        Audit::log('leave.type-update', 'leave_type', (int) $id, ['code' => $data['code']]);

        $sync = (int) ($_POST['sync_quota'] ?? 0) === 1;
        if ($sync) {
            $affected = Database::run(
                'UPDATE leave_balances SET quota = ?, updated_at = ? WHERE leave_type_id = ? AND period_year >= ?',
                [(float) $data['quota_days'], date('Y-m-d H:i:s'), (int) $id, (int) date('Y')]
            )->rowCount();
            flash('success', 'Jenis cuti ' . $data['name'] . ' diperbarui, jatah ' . $affected . ' baris saldo disinkronkan.');
            redirect('/leave/types');
        }

        flash('success', 'Jenis cuti ' . $data['name'] . ' diperbarui.');
        redirect('/leave/types');
    }

    public function destroyType(string $id): void
    {
        $type = $this->leave->findType((int) $id);
        if (!$type) {
            $this->abort(404, 'Jenis cuti tidak ditemukan.');
        }

        $usage = $this->leave->typeUsage((int) $id);
        if ($usage['requests'] > 0) {
            flash('error', 'Jenis cuti "' . $type['name'] . '" sudah dipakai pada ' . $usage['requests']
                . ' pengajuan. Nonaktifkan saja agar riwayat tetap utuh.');
            back('/leave/types');
        }
        if ($usage['balances'] > 0) {
            flash('error', 'Jenis cuti "' . $type['name'] . '" memiliki ' . $usage['balances']
                . ' baris saldo karyawan sehingga tidak dapat dihapus. Nonaktifkan sebagai gantinya.');
            back('/leave/types');
        }

        $this->leave->deleteType((int) $id);
        Audit::log('leave.type-delete', 'leave_type', (int) $id, ['code' => $type['code']]);
        flash('success', 'Jenis cuti ' . $type['name'] . ' dihapus.');
        redirect('/leave/types');
    }

    private function validateType(): array
    {
        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('code', 'Kode cuti')
                ->required('name', 'Nama jenis cuti')
                ->numeric('quota_days', 'Jatah hari', 0)
                ->numeric('max_per_request', 'Maksimal per pengajuan', 1)
                ->numeric('min_notice_days', 'Minimal pemberitahuan', 0)
                ->custom(mb_strlen((string) ($_POST['code'] ?? '')) > 20, 'code', 'Kode maksimal 20 karakter.')
                ->custom(mb_strlen((string) ($_POST['name'] ?? '')) > 100, 'name', 'Nama maksimal 100 karakter.');
        }, '/leave/types');

        $quota = (float) ($data['quota_days'] ?? 0);
        $max = (float) ($data['max_per_request'] ?? 0);
        if ($max > 0 && $quota > 0 && $max > $quota) {
            flash('warning', 'Maksimal per pengajuan melebihi jatah tahunan; pengajuan tetap dibatasi saldo.');
        }

        return [
            'code'                  => strtoupper(substr(trim((string) $data['code']), 0, 20)),
            'name'                  => substr(trim((string) $data['name']), 0, 100),
            'quota_days'            => $quota,
            'is_paid'               => (int) ($_POST['is_paid'] ?? 0),
            'max_per_request'       => $max > 0 ? $max : 0,
            'min_notice_days'       => (int) ($data['min_notice_days'] ?? 0),
            'requires_attachment'   => (int) ($_POST['requires_attachment'] ?? 0),
            'carry_over'            => (int) ($_POST['carry_over'] ?? 0),
            'deduct_salary'         => (int) ($_POST['deduct_salary'] ?? 0),
            'color'                 => $this->safeColor((string) ($_POST['color'] ?? '#0d9488')),
            'is_active'             => (int) ($_POST['is_active'] ?? 0),
        ];
    }

    private function safeColor(string $value): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtolower($value) : '#0d9488';
    }

    /* ----------------------------------------------------------------- balances */

    public function balances(): void
    {
        $filters = $this->filters();
        $filters['year'] = (int) ($_GET['year'] ?? date('Y'));

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 25;
        $total = $this->leave->countBalances($filters);
        $paginator = new Paginator($total, $perPage, min($page, max(1, (int) ceil($total / $perPage))));

        $this->view('leave/balances', [
            'title'       => 'Saldo Cuti',
            'pageTitle'   => 'Saldo Cuti',
            'rows'        => $this->leave->balances($filters, $perPage, $paginator->offset()),
            'paginator'   => $paginator,
            'filters'     => $filters,
            'stats'       => $this->leave->balanceStats((int) $filters['year'], $filters),
            'types'       => $this->leave->types(true),
            'units'       => $this->org->units(),
            'departments' => $this->org->departments(),
            'employees'   => $this->leave->employeeOptions(),
            'years'       => $this->leave->yearOptions(),
            'policy'      => $this->leave->settings(),
        ] + $this->withInput());
    }

    /** Simpan/ubah satu baris saldo (kuota + carry). */
    public function saveBalance(): void
    {
        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('employee_id', 'Karyawan')
                ->required('leave_type_id', 'Jenis cuti')
                ->required('period_year', 'Tahun')
                ->numeric('quota', 'Jatah hari', 0)
                ->numeric('carry', 'Sisa tahun lalu', 0);
        }, '/leave/balances');

        $employeeId = (int) $data['employee_id'];
        $typeId = (int) $data['leave_type_id'];
        $year = (int) $data['period_year'];
        $quota = (float) ($data['quota'] ?? 0);
        $carry = (float) ($data['carry'] ?? 0);

        $this->leave->setBalance($employeeId, $typeId, $year, $quota, $carry);
        Audit::log('leave.balance-save', 'leave_balance', null, [
            'employee' => $employeeId, 'type' => $typeId, 'year' => $year, 'quota' => $quota, 'carry' => $carry,
        ]);

        flash('success', 'Saldo cuti diperbarui (jatah ' . number_id($quota, 1) . ' hari, carry ' . number_id($carry, 1) . ' hari).');
        back('/leave/balances?year=' . $year);
    }

    /** Generate saldo satu tahun untuk semua karyawan aktif × jenis cuti aktif. */
    public function generateBalances(): void
    {
        $year = (int) input('year', (string) date('Y'));
        $unitId = (int) input('unit', '0');
        $overwrite = (int) input('overwrite_quota', '0') === 1;

        if ($year < 2000 || $year > 2100) {
            flash('error', 'Tahun tidak valid.');
            back('/leave/balances');
        }

        $result = $this->leave->generateYear($year, $unitId ?: null, $overwrite);
        Audit::log('leave.balance-generate', 'leave_balance', null, [
            'year' => $year, 'created' => $result['created'], 'skipped' => $result['skipped'],
        ]);

        $message = $result['created'] . ' baris saldo tahun ' . $year . ' dibuat untuk '
            . $result['employees'] . ' karyawan × ' . $result['types'] . ' jenis cuti.';
        if ($result['skipped'] > 0) {
            $message .= ' ' . $result['skipped'] . ' baris sudah ada dan dilewati.';
        }
        flash($overwrite ? 'warning' : 'success', $message);
        redirect('/leave/balances?year=' . $year);
    }

    /* ------------------------------------------------------------ self service */

    public function mine(): void
    {
        $employeeId = $this->employeeId();
        $year = (int) ($_GET['year'] ?? date('Y'));

        $this->view('leave/mine', [
            'title'      => 'Cuti Saya',
            'pageTitle'  => 'Cuti Saya',
            'balances'   => $this->leave->myBalances($employeeId, $year),
            'summary'    => $this->leave->mySummary($employeeId, $year),
            'rows'       => $this->leave->myRequests($employeeId),
            'types'      => $this->leave->types(true),
            'typeQuotas' => $this->leave->typeQuotas(),
            'policy'     => $this->leave->settings(),
            'year'       => $year,
            'years'      => $this->leave->yearOptions(),
            'today'      => date('Y-m-d'),
        ] + $this->withInput());
    }

    public function request(): void
    {
        $employeeId = $this->employeeId();

        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('leave_type_id', 'Jenis cuti')
                ->required('start_date', 'Tanggal mulai')
                ->date('start_date', 'Tanggal mulai')
                ->date('end_date', 'Tanggal selesai')
                ->required('reason', 'Alasan cuti')
                ->custom(mb_strlen((string) ($_POST['reason'] ?? '')) > 255, 'reason', 'Alasan maksimal 255 karakter.');
        }, '/me/leave');

        $upload = Upload::document('attachment', 'leave');
        if ($upload['error'] !== null) {
            flash('error', $upload['error']);
            back('/me/leave');
        }

        $result = $this->leave->create([
            'leave_type_id' => (int) $data['leave_type_id'],
            'start_date'    => (string) $data['start_date'],
            'end_date'      => (string) ($data['end_date'] ?? $data['start_date']),
            'reason'        => (string) $data['reason'],
            'attachment'    => $upload['path'],
        ], $employeeId);

        if ($result['error'] !== null) {
            if ($upload['path']) {
                Upload::delete($upload['path']);
            }
            flash('error', $result['error']);
            back('/me/leave');
        }

        Audit::log('leave.request', 'leave_request', (int) $result['id'], ['days' => $result['days']]);
        $this->notifyManager($employeeId, (int) $result['id'], (float) $result['days']);

        $message = 'Pengajuan cuti ' . number_id((float) $result['days'], 1) . ' hari terkirim dan menunggu persetujuan atasan langsung.';
        flash($result['warnings'] ? 'warning' : 'success', trim($message . ' ' . implode(' ', $result['warnings'])));
        redirect('/me/leave');
    }

    public function myCancel(string $id): void
    {
        $employeeId = $this->employeeId();
        $request = Database::one('SELECT * FROM leave_requests WHERE id = ?', [(int) $id]);
        if (!$request) {
            $this->abort(404, 'Pengajuan cuti tidak ditemukan.');
        }
        if ((int) $request['employee_id'] !== $employeeId) {
            $this->abort(403, 'Anda hanya dapat membatalkan pengajuan cuti milik sendiri.');
        }
        if ((string) $request['status'] !== 'PENDING') {
            flash('error', 'Pengajuan yang sudah diproses atasan tidak dapat dibatalkan sendiri. Hubungi HR.');
            back('/me/leave');
        }

        $this->leave->cancel((int) $id, 'Karyawan', 'Dibatalkan karyawan');
        Audit::log('leave.cancel-self', 'leave_request', (int) $id);
        flash('success', 'Pengajuan cuti dibatalkan.');
        redirect('/me/leave');
    }

    /* ------------------------------------------------------------------ internal */

    private function filters(): array
    {
        return [
            'status'     => strtoupper((string) ($_GET['status'] ?? 'ALL')),
            'type'       => (int) ($_GET['type'] ?? 0),
            'unit'       => (int) ($_GET['unit'] ?? 0),
            'department' => (int) ($_GET['department'] ?? 0),
            'employee'   => (int) ($_GET['employee'] ?? 0),
            'q'          => trim((string) ($_GET['q'] ?? '')),
        ];
    }

    private function notify(int $employeeId, string $event, array $request): void
    {
        $messages = [
            'cancelled' => ['Cuti dibatalkan', 'Cuti ' . tanggal((string) $request['start_date']) . ' dibatalkan oleh HR.'],
        ];
        [$title, $message] = $messages[$event] ?? ['Pembaruan cuti', 'Status cuti Anda diperbarui.'];

        try {
            Database::insert('notifications', [
                'employee_id' => $employeeId,
                'title'       => $title,
                'message'     => $message,
                'link'        => '/me/leave',
                'type'        => 'warning',
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }

    private function notifyManager(int $employeeId, int $requestId, float $days): void
    {
        $managerId = Database::value('SELECT direct_manager_id FROM employees WHERE id = ?', [$employeeId]);
        if (!$managerId) {
            return;
        }
        try {
            Database::insert('notifications', [
                'employee_id' => (int) $managerId,
                'title'       => 'Pengajuan cuti baru',
                'message'     => 'Menunggu persetujuan Anda (' . number_id($days, 1) . ' hari).',
                'link'        => '/leave?status=OPEN',
                'type'        => 'info',
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }
}
