<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Authentication + authorisation.
 *
 * Roles: HR (HR administrator), FIN (finance), KARYAWAN (employee).
 * Employees whose id appears as `direct_manager_id` also get an approval
 * inbox for their team (manager stage), independent of their role.
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    /** Permission matrix per role. */
    private const MATRIX = [
        'HR' => [
            'employee.view', 'employee.create', 'employee.update', 'employee.delete',
            'org.manage', 'company.manage', 'shift.manage', 'schedule.manage',
            'schedule.request.approve', 'attendance.view.all', 'attendance.settings',
            'overtime.approve', 'overtime.settings', 'overtime.schedule',
            'leave.approve', 'leave.manage', 'component.manage', 'payroll.manage',
            'payroll.process', 'payroll.send', 'report.view', 'setting.manage', 'audit.view',
        ],
        'FIN' => [
            'payroll.receive', 'payroll.pay', 'report.view', 'attendance.view.all',
        ],
        'KARYAWAN' => [
            'self.view', 'attendance.clock', 'leave.request', 'overtime.request', 'schedule.request',
        ],
    ];

    public static function boot(): void
    {
        self::$user = null;
        self::$resolved = false;
    }

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        $id = Session::get('auth_id');
        if (!$id || !Database::tableExists('employees')) {
            return self::$user = null;
        }

        $user = Database::one(
            'SELECT e.*, bu.name AS business_unit_name, bu.code AS business_unit_code,
                    d.name AS department_name, p.name AS position_name,
                    m.full_name AS manager_name
             FROM employees e
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN positions p ON p.id = e.position_id
             LEFT JOIN employees m ON m.id = e.direct_manager_id
             WHERE e.id = ?',
            [$id]
        );

        if (!$user || $user['flag'] !== 'ACTIVE') {
            Session::forget('auth_id');
            return self::$user = null;
        }

        return self::$user = $user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function hasRole(string|array $roles): bool
    {
        $role = self::role();
        if (!$role) {
            return false;
        }
        $roles = (array) $roles;
        if (in_array($role, $roles, true)) {
            return true;
        }
        // HR inherits finance visibility for reporting convenience.
        return false;
    }

    public static function can(string $ability): bool
    {
        $role = self::role();
        if (!$role) {
            return false;
        }
        $abilities = self::MATRIX[$role] ?? [];
        if (in_array($ability, $abilities, true)) {
            return true;
        }
        // Everyone can read their own data.
        return str_starts_with($ability, 'self.');
    }

    /** Attempt username / employee code / email login. */
    public static function attempt(string $identifier, string $password): bool
    {
        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return false;
        }

        $employee = Database::one(
            'SELECT * FROM employees WHERE (employee_code = ? OR email = ? OR (username IS NOT NULL AND username = ?)) LIMIT 1',
            [$identifier, strtolower($identifier), strtolower($identifier)]
        );

        if (!$employee || empty($employee['password_hash'])) {
            return false;
        }
        if (!password_verify($password, $employee['password_hash'])) {
            return false;
        }
        if ($employee['flag'] !== 'ACTIVE') {
            return false;
        }

        self::login($employee);
        return true;
    }

    public static function login(array $employee): void
    {
        Session::regenerate();
        Session::set('auth_id', (int) $employee['id']);
        Session::set('auth_at', time());
        self::$resolved = false;
        self::$user = null;
        Database::update('employees', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $employee['id']]);
        Audit::log('login', 'employee', (int) $employee['id']);
    }

    public static function loginViaGoogle(array $profile): ?array
    {
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        if ($email === '' || empty($profile['email_verified'])) {
            return null;
        }

        $employee = Database::one('SELECT * FROM employees WHERE LOWER(email) = ? LIMIT 1', [$email]);
        if (!$employee || $employee['flag'] !== 'ACTIVE') {
            return null;
        }

        Database::update('employees', [
            'google_id'      => $profile['sub'] ?? null,
            'avatar'         => $profile['picture'] ?? $employee['avatar'],
            'last_login_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int) $employee['id']]);

        self::login($employee);
        Audit::log('login_google', 'employee', (int) $employee['id']);
        return self::user();
    }

    public static function logout(): void
    {
        if (self::check()) {
            Audit::log('logout', 'employee', self::id());
        }
        Session::forget('auth_id');
        Session::forget('auth_at');
        self::$user = null;
        self::$resolved = false;
        Session::regenerate();
    }

    /** True when current user is the direct manager of $employeeId. */
    public static function isManagerOf(int $employeeId): bool
    {
        $id = self::id();
        if (!$id) {
            return false;
        }
        $managerId = Database::value('SELECT direct_manager_id FROM employees WHERE id = ?', [$employeeId]);
        return $managerId !== null && (int) $managerId === $id;
    }

    public static function isSelf(int $employeeId): bool
    {
        return self::id() === $employeeId;
    }
}
