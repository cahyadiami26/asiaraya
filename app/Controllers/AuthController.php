<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\GoogleAuth;
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

        if (!Auth::attempt($identifier, $password)) {
            $this->recordFailedAttempt($identifier);
            Session::set('login_error', 'Kredensial tidak cocok atau akun tidak aktif. Periksa NIK/email dan kata sandi.');
            Session::set('_old_identifier', $identifier);
            redirect('/login');
        }

        if ($remember) {
            Session::set('remember_login', true);
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
            'sent'  => Session::pull('reset_sent', false),
        ], 'layouts/auth');
    }

    public function sendReset(): void
    {
        $email = strtolower((string) input('email', ''));
        $employee = $email !== '' ? Database::one('SELECT id FROM employees WHERE LOWER(email) = ? AND flag = ?', [$email, 'ACTIVE']) : null;

        if ($employee) {
            $token = bin2hex(random_bytes(32));
            Database::insert('password_resets', [
                'email'      => $email,
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+60 minutes')),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $link = url('reset-password?token=' . $token);
            // No SMTP in scope: write a mail file so IT can deliver it, and surface
            // the link in demo mode only.
            @file_put_contents(
                STORAGE_PATH . '/logs/password-reset.log',
                '[' . date('Y-m-d H:i:s') . '] ' . $email . ' → ' . $link . PHP_EOL,
                FILE_APPEND
            );
            if (config('demo_mode')) {
                flash('info', 'Mode demo: tautan reset tersedia di storage/logs/password-reset.log');
            }
        }

        Session::set('reset_sent', true);
        redirect('/forgot-password');
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
