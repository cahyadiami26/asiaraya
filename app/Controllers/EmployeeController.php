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
use App\Models\DashboardModel;
use App\Models\EmployeeModel;
use App\Models\OrgModel;

final class EmployeeController extends Controller
{
    private EmployeeModel $employees;
    private OrgModel $org;
    private DashboardModel $dashboard;

    public const ROLES = ['KARYAWAN', 'HR', 'FIN'];
    public const STATUSES = ['PKWTT', 'PKWTT (Probation)', 'PKWT', 'PKWT (Harian)', 'Magang', 'Outsourcing'];
    public const TAX_STATUSES = ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'];

    public function __construct()
    {
        $this->employees = new EmployeeModel();
        $this->org = new OrgModel();
        $this->dashboard = new DashboardModel();
    }

    /* ------------------------------------------------------------------ list */
    public function index(): void
    {
        $this->employees->refreshContractFlags();

        $filters = [
            'q'          => trim((string) ($_GET['q'] ?? '')),
            'unit'       => (int) ($_GET['unit'] ?? 0),
            'department' => (int) ($_GET['department'] ?? 0),
            'status'     => (string) ($_GET['status'] ?? ''),
            'flag'       => (string) ($_GET['flag'] ?? ''),
            'contract'   => (string) ($_GET['contract'] ?? ''),
            'sort'       => (string) ($_GET['sort'] ?? ''),
        ];

        $perPage = 15;
        $paginator = Paginator::fromRequest($this->employees->count($filters), $perPage);
        $result = $this->employees->paginate($filters, $perPage, $paginator->offset());

        $this->view('employees/index', [
            'title'     => $filters['contract'] === 'expiring' ? 'Kontrak & Status' : 'Data Karyawan',
            'pageTitle' => 'Data Karyawan',
            'rows'      => $result['rows'],
            'total'     => $result['total'],
            'paginator' => $paginator,
            'filters'   => $filters,
            'units'     => $this->org->units(),
            'departments' => $this->employees->departmentsOf($filters['unit'] ?: null),
            'statuses'  => self::STATUSES,
            'stats'     => $this->employees->stats(),
            'badges'    => $this->dashboard->badges($this->employee() ?? []),
        ] + $this->withInput());
    }

    /* ----------------------------------------------------------------- create */
    public function create(): void
    {
        $unitId = (int) ($_GET['unit'] ?? 0);
        $this->view('employees/form', [
            'title'        => 'Tambah Karyawan',
            'pageTitle'    => 'Tambah Karyawan',
            'employee'     => null,
            'units'        => $this->org->units(),
            'departments'  => $this->employees->departmentsOf(null),
            'positions'    => $this->employees->positionsOf(null),
            'managers'     => $this->employees->managerOptions(),
            'roles'        => self::ROLES,
            'statuses'     => self::STATUSES,
            'taxStatuses'  => self::TAX_STATUSES,
            'nextCode'     => EmployeeCode::preview($unitId ?: null),
            'idFormat'     => EmployeeCode::format(),
            'badges'       => $this->dashboard->badges($this->employee() ?? []),
        ] + $this->withInput());
    }

    public function store(): void
    {
        $data = $this->validateOrRedirect(function (Validator $v): void {
            $v->required('full_name', 'Nama lengkap')
              ->required('email', 'Email')
              ->email('email', 'Email')
              ->required('business_unit_id', 'Business unit')
              ->required('employment_status', 'Status kepegawaian')
              ->numeric('basic_salary', 'Gaji pokok', 0)
              ->in('role', 'Role akses', self::ROLES)
              ->in('employment_status', 'Status kepegawaian', self::STATUSES)
              ->date('join_date', 'Tanggal bergabung')
              ->date('contract_start', 'Awal kontrak')
              ->date('contract_end', 'Akhir kontrak')
              ->date('birth_date', 'Tanggal lahir')
              ->min('password', 'Kata sandi', 8);
        }, '/employees/create');

        if ($this->employees->emailExists((string) $data['email'])) {
            flash('error', 'Email ' . $data['email'] . ' sudah terdaftar pada karyawan lain.');
            back('/employees/create');
        }

        $code = strtoupper(trim((string) ($data['employee_code'] ?? '')));
        if ($code === '') {
            $code = EmployeeCode::next(
                !empty($data['business_unit_id']) ? (int) $data['business_unit_id'] : null,
                !empty($data['department_id']) ? (int) $data['department_id'] : null
            );
        } elseif ($this->employees->codeExists($code)) {
            flash('error', 'ID karyawan ' . $code . ' sudah digunakan.');
            back('/employees/create');
        }

        $photo = Upload::image('photo', 'employees', 640);
        if ($photo['error']) {
            flash('error', $photo['error']);
            back('/employees/create');
        }

        $payload = $this->payload($data);
        $payload['employee_code'] = $code;
        $payload['photo'] = $photo['path'];
        $payload['flag'] = 'ACTIVE';
        $payload['password'] = (string) ($data['password'] ?? '') ?: 'Password123!';

        // Probation/PKWTT often has no contract dates; PKWT requires an end date.
        if (in_array($payload['employment_status'], ['PKWT', 'PKWT (Harian)', 'Magang', 'Outsourcing'], true) && empty($payload['contract_end'])) {
            flash('error', 'Kontrak berjangka wajib memiliki tanggal akhir kontrak.');
            back('/employees/create');
        }

        $id = $this->employees->create($payload);

        Audit::log('employee.create', 'employee', $id, ['employee_code' => $code]);
        Database::insert('notifications', [
            'employee_id' => $id,
            'title'       => 'Selamat datang di HRIS Asia Raya',
            'message'     => 'Akun Anda telah dibuat. Lengkapi profil dan unduh aplikasi presensi.',
            'link'        => '/me/profile',
            'type'        => 'info',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        flash('success', 'Karyawan ' . $payload['full_name'] . ' (' . $code . ') berhasil ditambahkan. Kata sandi awal: '
            . ((string) ($data['password'] ?? '') ?: 'Password123!'));
        redirect('/employees/' . $id);
    }

    /* ------------------------------------------------------------------- show */
    public function show(string $id): void
    {
        $employee = $this->employees->find((int) $id);
        if (!$employee) {
            $this->abort(404, 'Data karyawan tidak ditemukan.');
        }

        $attendance = Database::all(
            'SELECT work_date, check_in_at, check_out_at, status, late_minutes, work_minutes
             FROM attendance WHERE employee_id = ? ORDER BY work_date DESC LIMIT 6',
            [(int) $id]
        );
        $attendanceStats = Database::one(
            "SELECT COUNT(*) AS days,
                    SUM(CASE WHEN status = 'LATE' THEN 1 ELSE 0 END) AS late_days,
                    SUM(CASE WHEN status = 'ABSENT' THEN 1 ELSE 0 END) AS absent_days
             FROM attendance WHERE employee_id = ? AND work_date >= ?",
            [(int) $id, date('Y-m-01')]
        ) ?? ['days' => 0, 'late_days' => 0, 'absent_days' => 0];

        $payslip = Database::one(
            'SELECT * FROM payroll_items WHERE employee_id = ? ORDER BY id DESC LIMIT 1',
            [(int) $id]
        );

        $leaveBalances = Database::all(
            'SELECT lb.*, lt.name AS type_name, lt.code AS type_code
             FROM leave_balances lb
             LEFT JOIN leave_types lt ON lt.id = lb.leave_type_id
             WHERE lb.employee_id = ? AND lb.period_year = ?
             ORDER BY lt.name',
            [(int) $id, (int) date('Y')]
        );

        $offboarding = new \App\Models\OffboardingModel();

        $this->view('employees/show', [
            'title'         => $employee['full_name'],
            'pageTitle'     => $employee['full_name'],
            'employee'      => $employee,
            'attendance'    => $attendance,
            'attendanceStats' => $attendanceStats,
            'payslip'       => $payslip,
            'leaveBalances' => $leaveBalances,
            'offboardingRequests' => $offboarding->employeeRequests((int) $id),
            'offboardingActive'   => $offboarding->activeForEmployee((int) $id),
            'activity'      => $this->employees->recentActivity((int) $id),
            'contract'      => $this->contractStatus($employee),
            'badges'        => $this->dashboard->badges($this->employee() ?? []),
        ]);
    }

    /* ------------------------------------------------------------------- edit */
    public function edit(string $id): void
    {
        $employee = $this->employees->find((int) $id);
        if (!$employee) {
            $this->abort(404, 'Data karyawan tidak ditemukan.');
        }

        $this->view('employees/form', [
            'title'       => 'Ubah Data Karyawan',
            'pageTitle'   => 'Ubah Data ' . $employee['full_name'],
            'employee'    => $employee,
            'units'       => $this->org->units(),
            'departments' => $this->employees->departmentsOf(null),
            'positions'   => $this->employees->positionsOf(null),
            'managers'    => $this->employees->managerOptions(null, (int) $employee['id']),
            'roles'       => self::ROLES,
            'statuses'    => self::STATUSES,
            'taxStatuses' => self::TAX_STATUSES,
            'nextCode'    => $employee['employee_code'],
            'idFormat'    => EmployeeCode::format(),
            'badges'      => $this->dashboard->badges($this->employee() ?? []),
        ] + $this->withInput());
    }

    public function update(string $id): void
    {
        $employee = $this->employees->find((int) $id);
        if (!$employee) {
            $this->abort(404, 'Data karyawan tidak ditemukan.');
        }

        $data = $this->validateOrRedirect(function (Validator $v): void {
            $v->required('full_name', 'Nama lengkap')
              ->required('email', 'Email')
              ->email('email', 'Email')
              ->required('business_unit_id', 'Business unit')
              ->required('employment_status', 'Status kepegawaian')
              ->numeric('basic_salary', 'Gaji pokok', 0)
              ->in('role', 'Role akses', self::ROLES)
              ->in('employment_status', 'Status kepegawaian', self::STATUSES)
              ->date('join_date', 'Tanggal bergabung')
              ->date('contract_start', 'Awal kontrak')
              ->date('contract_end', 'Akhir kontrak')
              ->min('password', 'Kata sandi', 8);
        }, '/employees/' . (int) $id . '/edit');

        if ($this->employees->emailExists((string) $data['email'], (int) $employee['id'])) {
            flash('error', 'Email ' . $data['email'] . ' sudah terdaftar pada karyawan lain.');
            back('/employees/' . (int) $id . '/edit');
        }

        $code = strtoupper(trim((string) ($data['employee_code'] ?? ''))) ?: $employee['employee_code'];
        if ($this->employees->codeExists($code, (int) $employee['id'])) {
            flash('error', 'ID karyawan ' . $code . ' sudah digunakan karyawan lain.');
            back('/employees/' . (int) $id . '/edit');
        }

        $payload = $this->payload($data);
        $payload['employee_code'] = $code;

        $photo = Upload::image('photo', 'employees', 640);
        if ($photo['error']) {
            flash('error', $photo['error']);
            back('/employees/' . (int) $id . '/edit');
        }
        if ($photo['path']) {
            Upload::delete($employee['photo']);
            $payload['photo'] = $photo['path'];
        }
        if (!empty($data['remove_photo']) && $employee['photo']) {
            Upload::delete($employee['photo']);
            $payload['photo'] = null;
        }
        if (!empty($data['password'])) {
            $payload['password'] = (string) $data['password'];
        }

        $this->employees->update((int) $id, $payload);
        Audit::log('employee.update', 'employee', (int) $id, ['employee_code' => $code]);
        flash('success', 'Data ' . $payload['full_name'] . ' berhasil diperbarui.');
        redirect('/employees/' . (int) $id);
    }

    /* ----------------------------------------------------------------- resign */
    public function resign(string $id): void
    {
        $employee = $this->employees->find((int) $id);
        if (!$employee) {
            $this->abort(404, 'Data karyawan tidak ditemukan.');
        }
        $date = (string) input('resign_date', date('Y-m-d'));
        $reason = trim((string) input('reason', ''));

        if (strtotime($date) === false) {
            flash('error', 'Tanggal resign tidak valid.');
            back('/employees/' . (int) $id);
        }
        if ((int) $id === (int) ($this->employee()['id'] ?? 0)) {
            flash('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
            back('/employees/' . (int) $id);
        }

        $this->employees->resign((int) $id, $date, $reason !== '' ? $reason : null);
        flash('success', $employee['full_name'] . ' ditandai resign per ' . tanggal($date) . '.');
        redirect('/employees/' . (int) $id);
    }

    /* ---------------------------------------------------------------- helpers */
    /** Map validated form input to employee columns. */
    private function payload(array $data): array
    {
        $fields = [
            'full_name', 'nickname', 'email', 'phone', 'address', 'birth_date', 'gender',
            'marital_status', 'religion', 'role', 'direct_manager_id', 'business_unit_id',
            'department_id', 'position_id', 'basic_salary', 'employment_status', 'contract_start',
            'contract_end', 'join_date', 'bank_name', 'bank_account_name', 'bank_account_number',
            'npwp', 'bpjs_kesehatan_no', 'bpjs_tk_no', 'tax_status', 'username',
        ];
        $payload = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }
        $payload['username'] = ($payload['username'] ?? '') !== ''
            ? $payload['username']
            : strtolower((string) preg_replace('/[^a-z0-9.]+/', '.', str_replace(' ', '.', mb_strtolower((string) ($payload['email'] ?? '')))));
        $payload['email'] = mb_strtolower((string) ($payload['email'] ?? ''));
        $payload['role'] = in_array($payload['role'] ?? '', self::ROLES, true) ? $payload['role'] : 'KARYAWAN';
        $payload['basic_salary'] = (float) ($payload['basic_salary'] ?? 0);

        // Empty selects become NULL instead of 0.
        foreach (['direct_manager_id', 'department_id', 'position_id', 'business_unit_id'] as $fk) {
            if (isset($payload[$fk]) && $payload[$fk] === '') {
                $payload[$fk] = null;
            }
        }
        return $payload;
    }

    /** Days left / state of the employment contract. */
    private function contractStatus(array $employee): array
    {
        $end = $employee['contract_end'] ?? null;
        if (!$end) {
            return ['state' => 'none', 'days' => null, 'label' => 'Tanpa tanggal akhir kontrak'];
        }
        $days = (int) floor((strtotime((string) $end) - strtotime(date('Y-m-d'))) / 86400);
        if ($days < 0) {
            return ['state' => 'expired', 'days' => $days, 'label' => 'Kontrak berakhir ' . abs($days) . ' hari lalu'];
        }
        if ($days <= 45) {
            return ['state' => 'soon', 'days' => $days, 'label' => 'Kontrak berakhir dalam ' . $days . ' hari'];
        }
        return ['state' => 'safe', 'days' => $days, 'label' => 'Kontrak aktif, ' . $days . ' hari tersisa'];
    }
}
