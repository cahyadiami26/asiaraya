<?php
/**
 * PT Asia Raya Group - HRIS configuration.
 *
 * Real credentials live in app/config/env.php (created by the installer, gitignored).
 * Environment variables always win so the app can run containerised.
 */
$root = dirname(__DIR__, 2);

$defaults = [
    'app_name'    => 'HRIS Asia Raya',
    'app_env'     => 'production',
    'app_url'     => '',
    'app_key'     => 'change-me-please-32-chars-minimum',
    'timezone'    => 'Asia/Jakarta',
    'locale'      => 'id',
    'demo_mode'   => false,

    // Database: mysql (production) | sqlite (local preview only)
    'db_driver'      => 'mysql',
    'db_host'        => '127.0.0.1',
    'db_port'        => '3306',
    'db_database'    => 'asiaraya_hris',
    'db_username'    => 'root',
    'db_password'    => '',
    'db_charset'     => 'utf8mb4',
    'db_sqlite_path' => $root . '/storage/database.sqlite',

    'session_name' => 'ASRAYA_HRIS',
    'session_life' => 28800, // 8 jam — batas absolut satu sesi
    'session_idle' => 7200,  // 2 jam — keluar otomatis bila tidak ada aktivitas
    'login_max_attempts' => 5,   // gagal login sebelum diblokir sementara
    'login_lockout_minutes' => 15,

    'upload_path' => $root . '/storage/uploads',
    'upload_url'  => '/uploads',

    'google_client_id'     => '',
    'google_client_secret' => '',
    'google_redirect_uri'  => '',

    'qr_mode'  => 'online', // online | local | off
    'qr_api'   => 'https://api.qrserver.com/v1/create-qr-code/',
    'qr_cache' => true,

    'mail_from' => 'no-reply@asiaraya.co.id',
];

$file = __DIR__ . '/env.php';
$config = is_file($file) ? array_merge($defaults, (array) require $file) : $defaults;

// Environment overrides: HRIS_DB_HOST, HRIS_DB_DRIVER, ...
foreach ($defaults as $key => $unused) {
    $env = getenv('HRIS_' . strtoupper($key));
    if ($env !== false && $env !== '') {
        $config[$key] = $env;
    }
}

return $config;
