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

    /** Nama cookie "ingat saya" + masa berlakunya (hari). */
    public const REMEMBER_COOKIE = 'ASRAYA_REMEMBER';
    public const REMEMBER_DAYS = 30;

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

        $user = self::findById((int) $id);
        if (!$user) {
            Session::forget('auth_id');
            return self::$user = null;
        }

        return self::$user = $user;
    }

    /**
     * Profil lengkap karyawan aktif (dipakai sesi web dan token REST API).
     * Mengembalikan null bila tidak ada atau statusnya bukan ACTIVE.
     */
    public static function findById(int $id): ?array
    {
        if ($id <= 0 || !Database::tableExists('employees')) {
            return null;
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
            return null;
        }

        return $user;
    }

    /**
     * Menetapkan pengguna aktif tanpa menyentuh sesi web.
     * Dipakai ApiAuth setelah bearer token diverifikasi, sehingga model dan
     * controller yang memakai Auth::id()/isManagerOf() tetap bekerja.
     */
    public static function setUser(?array $user): void
    {
        self::$user = $user;
        self::$resolved = true;
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
        $employee = self::verifyCredentials($identifier, $password);
        if (!$employee) {
            return false;
        }

        self::login($employee);
        return true;
    }

    /**
     * Verifikasi kredensial tanpa membuat sesi (dipakai login REST API).
     * Menerima employee_code, email, atau username.
     */
    public static function verifyCredentials(string $identifier, string $password): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return null;
        }

        $employee = Database::one(
            'SELECT * FROM employees WHERE (employee_code = ? OR email = ? OR (username IS NOT NULL AND username = ?)) LIMIT 1',
            [$identifier, strtolower($identifier), strtolower($identifier)]
        );

        if (!$employee || empty($employee['password_hash'])) {
            return null;
        }
        if (!password_verify($password, $employee['password_hash'])) {
            return null;
        }
        if ($employee['flag'] !== 'ACTIVE') {
            return null;
        }

        return $employee;
    }

    /**
     * Batas sesi: absolut (session_life, default 8 jam) dan idle
     * (session_idle, default 2 jam). Kedaluwarsa → sesi dibersihkan,
     * pengguna diarahkan ke halaman masuk dengan pesan.
     */
    public static function guardSession(): void
    {
        $id = Session::get('auth_id');
        if (!$id) {
            return;
        }

        $now = time();
        $started = (int) Session::get('auth_at', $now);
        $seen = (int) Session::get('auth_seen', $started);
        $life = (int) ($GLOBALS['config']['session_life'] ?? 28800);
        $idle = (int) ($GLOBALS['config']['session_idle'] ?? 7200);

        if (($life > 0 && $now - $started > $life) || ($idle > 0 && $now - $seen > $idle)) {
            Session::forget('auth_id');
            Session::forget('auth_at');
            Session::forget('auth_seen');
            Session::regenerate();
            self::$user = null;
            self::$resolved = false;
            Audit::log('session.timeout', 'employee', (int) $id);
            flash('info', 'Sesi Anda berakhir karena tidak ada aktivitas. Silakan masuk kembali.');
            return;
        }

        // Simpan waktu aktivitas terakhir minimal sekali per menit agar hemat tulis.
        if ($now - $seen > 60 || $seen <= 0) {
            Session::set('auth_seen', $now);
        }
    }

    /** Aktifkan "ingat saya" untuk perangkat ini (token selector + validator). */
    public static function issueRememberCookie(int $employeeId): void
    {
        try {
            $selector = bin2hex(random_bytes(8));
            $validator = bin2hex(random_bytes(32));
            $expires = time() + self::REMEMBER_DAYS * 86400;

            Database::delete('remember_tokens', 'expires_at < ?', [date('Y-m-d H:i:s')]);
            Database::insert('remember_tokens', [
                'employee_id'    => $employeeId,
                'selector'       => $selector,
                'validator_hash' => hash('sha256', $validator),
                'expires_at'     => date('Y-m-d H:i:s', $expires),
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            self::setRememberCookie($selector . ':' . $validator, $expires);
        } catch (\Throwable) {
            // token "ingat saya" bersifat opsional; jangan gagalkan login
        }
    }

    /**
     * Coba masuk kembali dari cookie "ingat saya" (bila sesi sudah habis).
     * Token dirotasi setiap kali dipakai agar tidak bisa dipakai ulang.
     */
    public static function rememberFromCookie(): bool
    {
        $raw = (string) ($_COOKIE[self::REMEMBER_COOKIE] ?? '');
        if ($raw === '' || !str_contains($raw, ':')) {
            return false;
        }

        [$selector, $validator] = explode(':', $raw, 2);
        if (!preg_match('/^[a-f0-9]{16}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
            self::clearRememberCookie();
            return false;
        }

        try {
            $row = Database::one('SELECT * FROM remember_tokens WHERE selector = ?', [$selector]);
            if (!$row) {
                self::clearRememberCookie();
                return false;
            }

            $expired = strtotime((string) $row['expires_at']) < time();
            $matches = hash_equals((string) $row['validator_hash'], hash('sha256', $validator));
            Database::delete('remember_tokens', 'selector = ?', [$selector]);

            if ($expired || !$matches) {
                self::clearRememberCookie();
                return false;
            }

            $employee = Database::one('SELECT * FROM employees WHERE id = ? AND flag = ?', [(int) $row['employee_id'], 'ACTIVE']);
            if (!$employee) {
                self::clearRememberCookie();
                return false;
            }

            self::login($employee);
            self::issueRememberCookie((int) $employee['id']);
            Audit::log('login_remember', 'employee', (int) $employee['id']);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Cabut token perangkat ini (dipakai saat logout). */
    public static function revokeRememberCookie(): void
    {
        $raw = (string) ($_COOKIE[self::REMEMBER_COOKIE] ?? '');
        if ($raw !== '' && str_contains($raw, ':')) {
            [$selector] = explode(':', $raw, 2);
            if (preg_match('/^[a-f0-9]{16}$/', $selector)) {
                try {
                    Database::delete('remember_tokens', 'selector = ?', [$selector]);
                } catch (\Throwable) {
                    // abaikan
                }
            }
        }
        self::clearRememberCookie();
    }

    /** Cabut seluruh token perangkat milik satu karyawan (reset kata sandi). */
    public static function revokeAllRememberTokens(int $employeeId): void
    {
        try {
            Database::delete('remember_tokens', 'employee_id = ?', [$employeeId]);
        } catch (\Throwable) {
            // abaikan
        }
    }

    private static function setRememberCookie(string $value, int $expires): void
    {
        // Ditulis sebagai header eksplisit agar tetap terkirim baik pada PHP-FPM
        // maupun pada jembatan pratinjau php-wasm (SAPI cli + header kustom).
        $_COOKIE[self::REMEMBER_COOKIE] = $value;
        self::sendCookie($value, $expires);
    }

    private static function clearRememberCookie(): void
    {
        unset($_COOKIE[self::REMEMBER_COOKIE]);
        self::sendCookie('', time() - 3600);
    }

    private static function sendCookie(string $value, int $expires): void
    {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $cookie = self::REMEMBER_COOKIE . '=' . $value
            . '; Expires=' . gmdate('D, d M Y H:i:s', $expires) . ' GMT'
            . '; Max-Age=' . max(0, $expires - time())
            . '; Path=/'
            . ($secure ? '; Secure' : '')
            . '; HttpOnly; SameSite=Lax';

        // Jembatan pratinjau php-wasm hanya meneruskan satu header Set-Cookie,
        // jadi cookie tambahan dititipkan lewat globals untuk diteruskan bridge.
        if (!empty($GLOBALS['HRIS_EXTERNAL_SESSION'])) {
            $GLOBALS['HRIS_SET_COOKIES'][] = $cookie;
            return;
        }

        if (headers_sent()) {
            return;
        }
        header('Set-Cookie: ' . $cookie, false);
    }

    public static function login(array $employee): void
    {
        Session::regenerate();
        Session::set('auth_id', (int) $employee['id']);
        Session::set('auth_at', time());
        Session::set('auth_seen', time());
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
        self::revokeRememberCookie();
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
