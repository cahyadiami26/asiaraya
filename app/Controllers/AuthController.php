<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\GoogleAuth;
use App\Core\Mailer;
use App\Core\Session;
use App\Core\Validator;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }

        $company = [
            'name'    => setting('company_name', 'PT Asia Raya Group'),
            'phone'   => setting('company_phone', '+62 21 5150 8000'),
            'email'   => setting('company_email', 'hr@asiaraya.co.id'),
            'website' => setting('company_website', 'https://asiaraya.co.id'),
        ];

        $stats = [
            'employees' => Database::tableExists('employees')
                ? (int) Database::value("SELECT COUNT(*) FROM employees WHERE flag = 'ACTIVE'")
                : 0,
            'units' => Database::tableExists('business_units')
                ? (int) Database::value('SELECT COUNT(*) FROM business_units WHERE is_active = 1')
                : 0,
        ];

        $this->view('auth/login', [
            'title'        => 'Masuk ke Akun HRIS',
            'company'      => $company,
            'stats'        => $stats,
            'googleReady'  => GoogleAuth::isConfigured() && setting('google_login_enabled', '1') === '1',
            'demoAccounts' => $this->demoAccounts(),
            'error'        => Session::pull('login_error'),
        ], 'layouts/auth');
    }

    public function login(): void
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }

        $identifier = (string) input('identifier', input('username', ''));
        $password   = (string) input('password', '');
        $remember   = input('remember') === '1';

        $validator = new Validator(['identifier' => $identifier, 'password' => $password]);
        $validator->required('identifier', 'NIK / Username / Email')->required('password', 'Kata sandi');

        if ($validator->fails()) {
            Session::set('login_error', $validator->firstError());
            Session::set('_old_identifier', $identifier);
            redirect('/login');
        }

        // Proteksi brute force: blokir sementara setelah N kegagalan beruntun.
        $lock = $this->lockoutState($identifier);
        if ($lock['locked']) {
            Session::set('login_error', 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . $lock['minutes'] . ' menit atau hubungi HR/IT Helpdesk.');
            Session::set('_old_identifier', $identifier);
            $this->recordBlockedAttempt($identifier);
            redirect('/login');
        }

        if (!Auth::attempt($identifier, $password)) {
            $this->recordFailedAttempt($identifier);
            Session::set('login_error', 'Kredensial tidak cocok atau akun tidak aktif. Periksa NIK/email dan kata sandi.');
            Session::set('_old_identifier', $identifier);
            redirect('/login');
        }

        if ($remember) {
            Auth::issueRememberCookie((int) Auth::id());
        }

        $intended = Session::pull('intended');
        flash('success', 'Selamat datang kembali, ' . (Auth::user()['nickname'] ?: Auth::user()['full_name']) . '.');
        redirect($intended ?: '/dashboard');
    }

    public function googleRedirect(): void
    {
        if (!GoogleAuth::isConfigured()) {
            flash('warning', 'Login Google belum dikonfigurasi. Hubungi HR/IT untuk mengaktifkan integrasi Google Workspace.');
            redirect('/login');
        }
        $state = bin2hex(random_bytes(16));
        Session::set('google_state', $state);
        $authUrl = GoogleAuth::authUrl($state);
        redirect($authUrl);
    }

    public function googleCallback(): void
    {
        $state = (string) ($_GET['state'] ?? '');
        $code  = (string) ($_GET['code'] ?? '');
        $expected = (string) Session::pull('google_state', '');

        if ($expected === '' || !hash_equals($expected, $state)) {
            flash('error', 'Sesi login Google tidak valid. Silakan coba lagi.');
            redirect('/login');
        }
        if ($code === '') {
            flash('error', 'Login Google dibatalkan.');
            redirect('/login');
        }

        $profile = GoogleAuth::fetchProfile($code);
        if ($profile === null) {
            flash('error', 'Gagal memverifikasi akun Google. Pastikan email terdaftar sebagai karyawan aktif.');
            redirect('/login');
        }

        $user = Auth::loginViaGoogle($profile);
        if ($user === null) {
            flash('error', 'Email ' . e((string) ($profile['email'] ?? '')) . ' belum terdaftar sebagai karyawan aktif. Hubungi HR untuk pendaftaran email.');
            redirect('/login');
        }

        flash('success', 'Berhasil masuk dengan Google Workspace sebagai ' . $user['full_name'] . '.');
        redirect('/dashboard');
    }

    public function showForgot(): void
    {
        $this->view('auth/forgot', [
            'title' => 'Lupa Kata Sandi',
            'sent'  => (bool) Session::pull('reset_sent', false),
            'mail'  => (string) Session::pull('reset_mail', ''),
            'demoLink' => (string) Session::pull('reset_link', ''),
        ], 'layouts/auth');
    }

    /**
     * Kirim tautan reset. Tanpa SMTP: tautan ditulis ke storage/logs/password-reset.log
     * (dan ditampilkan langsung pada mode demo). Respons selalu sama agar email
     * tidak bisa ditebak keberadaannya.
     */
    public function sendReset(): void
    {
        $email = strtolower(trim((string) input('email', '')));
        Session::set('reset_sent', true);
        Session::set('reset_mail', $email);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::set('login_error', 'Masukkan alamat email perusahaan yang valid.');
            redirect('/forgot-password');
        }

        // Batasi frekuensi: maksimal 5 permintaan/jam dan 1/menit per email.
        $recent = (int) Database::value(
            'SELECT COUNT(*) FROM password_resets WHERE email = ? AND created_at >= ?',
            [$email, date('Y-m-d H:i:s', strtotime('-1 hour'))]
        );
        $last = (string) (Database::value('SELECT MAX(created_at) FROM password_resets WHERE email = ?', [$email]) ?? '');
        $tooSoon = $last !== '' && strtotime($last) > time() - 60;

        if ($recent >= 5 || $tooSoon) {
            flash('warning', 'Permintaan reset untuk email ini baru saja dibuat. Tunggu satu menit sebelum mencoba lagi.');
            redirect('/forgot-password');
        }

        $employee = Database::one(
            'SELECT id, full_name FROM employees WHERE LOWER(email) = ? AND flag = ?',
            [$email, 'ACTIVE']
        );

        if ($employee) {
            $token = bin2hex(random_bytes(32));
            Database::delete('password_resets', 'email = ? AND used_at IS NULL', [$email]);
            Database::insert('password_resets', [
                'email'      => $email,
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+60 minutes')),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $link = url('reset-password?token=' . $token);
            $logDir = STORAGE_PATH . '/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }
            @file_put_contents(
                $logDir . '/password-reset.log',
                '[' . date('Y-m-d H:i:s') . '] ' . $email . ' → ' . $link . PHP_EOL,
                FILE_APPEND
            );

            // Kirim tautan reset lewat email (Batch 18). Bila email dinonaktifkan
            // atau driver masih "log", tautan tetap tercatat di password-reset.log.
            try {
                Mailer::toEmployee(
                    ['id' => (int) $employee['id'], 'email' => $email, 'full_name' => (string) $employee['full_name'], 'flag' => 'ACTIVE'],
                    'password_reset',
                    'Atur ulang kata sandi HRIS',
                    [
                        'heading'        => 'Atur ulang kata sandi',
                        'greeting'       => 'Halo ' . explode(' ', trim((string) $employee['full_name']))[0] . ',',
                        'message'        => 'Kami menerima permintaan pengaturan ulang kata sandi untuk akun HRIS Anda. Klik tombol di bawah untuk membuat kata sandi baru.',
                        'actionUrl'      => $link,
                        'actionLabel'    => 'Atur Ulang Kata Sandi',
                        'expiresMinutes' => 60,
                        'note'           => 'Permintaan dibuat dari ' . ($_SERVER['REMOTE_ADDR'] ?? 'alamat tidak dikenal') . ' pada ' . date('d M Y H:i') . '.',
                    ]
                );
            } catch (\Throwable $e) {
                error_log('email reset password gagal: ' . $e->getMessage());
            }

            Audit::log('password.request', 'employee', (int) $employee['id'], ['email' => $email]);

            if (config('demo_mode')) {
                Session::set('reset_link', $link);
            }
        }

        redirect('/forgot-password');
    }

    /** Form kata sandi baru — token diverifikasi sebelum ditampilkan. */
    public function showReset(): void
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }

        $token = (string) ($_GET['token'] ?? '');
        $reset = $this->findReset($token);
        if (!$reset) {
            $this->view('auth/reset', [
                'title' => 'Tautan Tidak Valid',
                'valid' => false,
                'token' => '',
                'email' => '',
            ], 'layouts/auth');
            return;
        }

        $this->view('auth/reset', [
            'title' => 'Atur Kata Sandi Baru',
            'valid' => true,
            'token' => $token,
            'email' => $this->maskEmail((string) $reset['email']),
        ], 'layouts/auth');
    }

    /** Simpan kata sandi baru: token sekali pakai, cabut sesi "ingat saya". */
    public function resetPassword(): void
    {
        $token = (string) input('token', '');
        $reset = $this->findReset($token);
        if (!$reset) {
            flash('error', 'Tautan reset tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru.');
            redirect('/forgot-password');
        }

        $password = (string) input('password', '');
        $confirm = (string) input('password_confirmation', '');

        $validator = new Validator(['password' => $password, 'password_confirmation' => $confirm]);
        $validator->required('password', 'Kata sandi baru')->min('password', 'Kata sandi baru', 8)
                  ->same('password', 'password_confirmation', 'Konfirmasi kata sandi');

        if ($validator->fails()) {
            Session::set('_errors', $validator->errors());
            Session::set('login_error', $validator->firstError());
            redirect('/reset-password?token=' . urlencode($token));
        }

        $employee = Database::one(
            'SELECT id FROM employees WHERE LOWER(email) = ? AND flag = ?',
            [(string) $reset['email'], 'ACTIVE']
        );
        if (!$employee) {
            flash('error', 'Akun tidak ditemukan atau sudah tidak aktif. Hubungi HR.');
            redirect('/login');
        }

        try {
            Database::transaction(static function () use ($reset, $employee, $password): void {
                Database::update('employees', [
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ], 'id = ?', [(int) $employee['id']]);

                Database::update('password_resets', ['used_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $reset['id']]);
                Database::delete('password_resets', 'email = ? AND used_at IS NULL', [(string) $reset['email']]);
            });
        } catch (\Throwable $e) {
            flash('error', 'Kata sandi gagal disimpan. Silakan coba lagi.');
            redirect('/reset-password?token=' . urlencode($token));
        }

        Auth::revokeAllRememberTokens((int) $employee['id']);
        Audit::log('password.reset', 'employee', (int) $employee['id'], ['email' => $reset['email']]);
        flash('success', 'Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru.');
        redirect('/login');
    }

    /** Cari token reset yang masih berlaku (hash, belum dipakai, belum kedaluwarsa). */
    private function findReset(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        return Database::one(
            'SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at >= ? ORDER BY id DESC',
            [hash('sha256', $token), date('Y-m-d H:i:s')]
        );
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return $email;
        }
        $name = $parts[0];
        $visible = mb_substr($name, 0, 2);
        return $visible . str_repeat('•', max(3, mb_strlen($name) - 2)) . '@' . $parts[1];
    }

    /** Status blokir login untuk identifier + IP ini. */
    private function lockoutState(string $identifier): array
    {
        $max = (int) ($GLOBALS['config']['login_max_attempts'] ?? 5);
        $window = (int) ($GLOBALS['config']['login_lockout_minutes'] ?? 15);
        if ($max <= 0) {
            return ['locked' => false, 'minutes' => 0];
        }

        $since = date('Y-m-d H:i:s', time() - $window * 60);
        $needle = '%' . Database::like($identifier) . '%';
        $attempts = (int) Database::value(
            "SELECT COUNT(*) FROM activity_logs
             WHERE action = 'login_failed' AND created_at >= ? AND ip_address = ?
               AND meta LIKE ?",
            [$since, (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $needle]
        );

        return [
            'locked'  => $attempts >= $max,
            'minutes' => $window,
        ];
    }

    private function recordBlockedAttempt(string $identifier): void
    {
        try {
            Database::insert('activity_logs', [
                'employee_id' => null,
                'action'      => 'login_blocked',
                'entity'      => 'employee',
                'meta'        => json_encode(['identifier' => $identifier], JSON_UNESCAPED_UNICODE),
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'  => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // abaikan
        }
    }

    public function logout(): void
    {
        Auth::logout();
        flash('success', 'Anda telah keluar dari sistem HRIS.');
        redirect('/login');
    }

    private function recordFailedAttempt(string $identifier): void
    {
        try {
            Database::insert('activity_logs', [
                'employee_id' => null,
                'action'      => 'login_failed',
                'entity'      => 'employee',
                'meta'        => json_encode(['identifier' => $identifier]),
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'  => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // ignore
        }
    }

    /** Demo accounts surfaced on the login card (only when demo data exists). */
    private function demoAccounts(): array
    {
        if (!Database::tableExists('employees')) {
            return [];
        }
        $rows = Database::all(
            "SELECT email, role, full_name FROM employees
             WHERE email IN ('hr@asiaraya.co.id','finance@asiaraya.co.id','dimas.aditya@asiaraya.co.id')"
        );
        $order = ['HR' => 0, 'FIN' => 1, 'KARYAWAN' => 2];
        usort($rows, static fn ($a, $b) => ($order[$a['role']] ?? 9) <=> ($order[$b['role']] ?? 9));
        return $rows;
    }
}
