<?php
/**
 * Session & Authentication.
 * Role: EMPLOYEE | HR | FIN
 */
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('HRISARG');
    session_start();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function employee_id(): ?int
{
    $u = current_user();
    return $u && !empty($u['employee_id']) ? (int)$u['employee_id'] : null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function require_role(string ...$roles): void
{
    require_login();
    $u = current_user();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        flash('error', 'Anda tidak memiliki akses ke halaman ini.');
        redirect('index.php');
    }
}

function is_hr(): bool  { return is_logged_in() && current_user()['role'] === 'HR'; }
function is_fin(): bool { return is_logged_in() && current_user()['role'] === 'FIN'; }
function is_employee_role(): bool { return is_logged_in() && current_user()['role'] === 'EMPLOYEE'; }

/** Apakah user login adalah direct manager dari karyawan $empId? */
function is_manager_of(int $empId): bool
{
    $myEmp = employee_id();
    if (!$myEmp) return false;
    return (int)val('SELECT direct_manager_id FROM employees WHERE id=?', [$empId]) === $myEmp;
}

/** Cari user akun berdasarkan email saat login Google */
function find_user_by_email(string $email): ?array
{
    return one('SELECT * FROM users WHERE email=? AND is_active=1', [strtolower($email)]);
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    unset($user['password_hash']);
    $_SESSION['user'] = $user;
    if (!empty($user['id'])) {
        exec_sql('UPDATE users SET last_login_at=NOW() WHERE id=?', [$user['id']]);
    }
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** CSRF */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}
function verify_csrf(): void
{
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!$t || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        exit('Sesi tidak valid (CSRF). Silakan refresh halaman.');
    }
}
