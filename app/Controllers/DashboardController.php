<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Core\Validator;
use App\Models\DashboardModel;

final class DashboardController extends Controller
{
    private DashboardModel $model;

    public function __construct()
    {
        $this->model = new DashboardModel();
    }

    public function index(): void
    {
        $user = $this->employee();
        if (!$user) {
            redirect('/login');
        }

        $badges = $this->model->badges($user);

        if ($user['role'] === 'HR') {
            $this->renderHrDashboard($user, $badges);
            return;
        }
        if ($user['role'] === 'FIN') {
            $this->renderFinanceDashboard($user, $badges);
            return;
        }
        $this->renderEmployeeDashboard($user, $badges);
    }

    /* ------------------------------------------------------------------ HR */
    private function renderHrDashboard(array $user, array $badges): void
    {
        $workforce = $this->model->workforce();
        $attendance = $this->model->attendanceToday();
        $approvals = $this->model->pendingApprovals();
        $payroll = $this->model->payroll();
        $composition = $this->model->salaryComposition();
        $monitoring = $this->model->employeeMonitoring(5);
        $critical = $this->model->criticalDates();

        $greeting = $this->greeting();
        $this->view('dashboard/hr', [
            'title'       => 'Dashboard Utama',
            'pageTitle'   => 'Dashboard Utama',
            'user'        => $user,
            'workforce'   => $workforce,
            'attendance'  => $attendance,
            'approvals'   => $approvals,
            'payroll'     => $payroll,
            'composition' => $composition,
            'monitoring'  => $monitoring,
            'critical'    => $critical,
            'badges'      => $badges,
            'greeting'    => $greeting,
            'latePolicy'  => [
                'tolerance' => (int) setting('attendance_late_tolerance', 15),
                'enabled'   => setting('attendance_late_deduction_enabled', '1') === '1',
                'value'     => (float) setting('attendance_late_deduction_value', 2500),
                'type'      => (string) setting('attendance_late_deduction_type', 'PER_MINUTE'),
            ],
        ]);
    }

    /* ----------------------------------------------------------------- FIN */
    private function renderFinanceDashboard(array $user, array $badges): void
    {
        $payroll = $this->model->payroll();
        $periods = Database::all(
            "SELECT p.*, bu.name AS unit_name, bu.code AS unit_code
             FROM payroll_periods p LEFT JOIN business_units bu ON bu.id = p.business_unit_id
             ORDER BY p.period_year DESC, p.period_month DESC, p.id DESC LIMIT 6"
        );
        $pendingPayment = Database::all(
            "SELECT pi.*, p.period_month, p.period_year, p.status AS period_status, bu.name AS unit_name
             FROM payroll_items pi
             JOIN payroll_periods p ON p.id = pi.period_id
             LEFT JOIN business_units bu ON bu.id = p.business_unit_id
             WHERE p.status IN ('SENT_TO_FINANCE','FINANCE_APPROVED')
             ORDER BY pi.id DESC LIMIT 8"
        );

        $this->view('dashboard/finance', [
            'title'          => 'Dashboard Finance',
            'pageTitle'      => 'Dashboard Finance',
            'user'           => $user,
            'payroll'        => $payroll,
            'periods'        => $periods,
            'pendingPayment' => $pendingPayment,
            'badges'         => $badges,
            'greeting'       => $this->greeting(),
        ]);
    }

    /* ------------------------------------------------------------ Employee */
    private function renderEmployeeDashboard(array $user, array $badges): void
    {
        $employeeId = (int) $user['id'];
        $attendance = $this->model->myAttendance($employeeId);
        $requests = $this->model->myRequests($employeeId);
        $payroll = $this->model->myPayroll($employeeId);
        $schedule = $this->model->employeeScheduleNext($employeeId, 5);

        $this->view('dashboard/employee', [
            'title'      => 'Dashboard Karyawan',
            'pageTitle'  => 'Dashboard Karyawan',
            'user'       => $user,
            'attendance' => $attendance,
            'requests'   => $requests,
            'payroll'    => $payroll,
            'schedule'   => $schedule,
            'badges'     => $badges,
            'greeting'   => $this->greeting(),
        ]);
    }

    /* ------------------------------------------------------------- Profile */
    public function profile(): void
    {
        $user = $this->employee();
        $employee = Database::one(
            'SELECT e.*, bu.name AS unit_name, d.name AS department_name, p.name AS position_name,
                    m.full_name AS manager_name, m.email AS manager_email
             FROM employees e
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN positions p ON p.id = e.position_id
             LEFT JOIN employees m ON m.id = e.direct_manager_id
             WHERE e.id = ?',
            [(int) $user['id']]
        );

        $this->view('dashboard/profile', [
            'title'    => 'Profil Saya',
            'pageTitle'=> 'Profil Saya',
            'user'     => $user,
            'employee' => $employee,
            'badges'   => $this->model->badges($user),
        ] + $this->withInput());
    }

    public function updateProfile(): void
    {
        $user = $this->employee();
        $data = $this->validateOrRedirect(static function (Validator $v) use ($user): void {
            $v->required('phone', 'Nomor HP')->required('address', 'Alamat');
            if (input('password') !== '') {
                $v->min('password', 'Kata sandi baru', 8)
                  ->same('password', 'password_confirmation', 'Konfirmasi kata sandi');
            }
            if (input('email') !== '' && strtolower((string) input('email')) !== strtolower((string) $user['email'])) {
                $v->custom(true, 'email', 'Email perusahaan tidak dapat diubah sendiri. Hubungi HR.');
            }
        }, '/me/profile');

        $update = [
            'phone'   => $data['phone'],
            'address' => $data['address'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (!empty($data['password'])) {
            $update['password_hash'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }

        Database::update('employees', $update, 'id = ?', [(int) $user['id']]);
        \App\Core\Audit::log('profile.update', 'employee', (int) $user['id']);
        flash('success', 'Profil berhasil diperbarui.');
        redirect('/me/profile');
    }

    /* --------------------------------------------------------------- misc */
    public function search(): void
    {
        $user = $this->employee();
        $q = trim((string) ($_GET['q'] ?? ''));
        $results = ['employees' => [], 'departments' => [], 'units' => []];
        if ($q !== '' && $user) {
            $like = '%' . $q . '%';
            $results['employees'] = Database::all(
                "SELECT e.id, e.full_name, e.employee_code, e.email, bu.name AS unit_name, d.name AS department_name
                 FROM employees e
                 LEFT JOIN business_units bu ON bu.id = e.business_unit_id
                 LEFT JOIN departments d ON d.id = e.department_id
                 WHERE (e.full_name LIKE ? OR e.employee_code LIKE ? OR e.email LIKE ?)
                 ORDER BY e.full_name LIMIT 12",
                [$like, $like, $like]
            );
            if ($user['role'] === 'HR') {
                $results['departments'] = Database::all(
                    'SELECT d.id, d.name, d.code, bu.name AS unit_name FROM departments d
                     LEFT JOIN business_units bu ON bu.id = d.business_unit_id
                     WHERE d.name LIKE ? OR d.code LIKE ? LIMIT 8',
                    [$like, $like]
                );
                $results['units'] = Database::all(
                    'SELECT id, name, code, city, latitude, longitude FROM business_units WHERE name LIKE ? OR code LIKE ? LIMIT 8',
                    [$like, $like]
                );
            }
        }

        $this->view('dashboard/search', [
            'title'   => 'Pencarian',
            'pageTitle' => 'Pencarian',
            'user'    => $user,
            'query'   => $q,
            'results' => $results,
            'badges'  => $this->model->badges($user),
        ]);
    }

    public function notifications(): void
    {
        $user = $this->employee();
        $items = Database::all(
            'SELECT * FROM notifications WHERE employee_id = ? ORDER BY id DESC LIMIT 50',
            [(int) $user['id']]
        );
        $this->view('dashboard/notifications', [
            'title' => 'Notifikasi',
            'pageTitle' => 'Notifikasi',
            'user' => $user,
            'items' => $items,
            'badges' => $this->model->badges($user),
        ]);
    }

    public function markNotificationsRead(): void
    {
        $user = $this->employee();
        Database::run(
            'UPDATE notifications SET read_at = ? WHERE employee_id = ? AND read_at IS NULL',
            [date('Y-m-d H:i:s'), (int) $user['id']]
        );
        redirect('/notifications');
    }

    private function greeting(): string
    {
        $hour = (int) date('G');
        if ($hour < 11) {
            return 'Selamat Pagi';
        }
        if ($hour < 15) {
            return 'Selamat Siang';
        }
        if ($hour < 19) {
            return 'Selamat Sore';
        }
        return 'Selamat Malam';
    }
}
