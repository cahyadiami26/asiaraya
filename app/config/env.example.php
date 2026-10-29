<?php
/**
 * Salin berkas ini ke app/config/env.php bila ingin konfigurasi berbasis berkas.
 * app/config/env.php bawaan repo hanya berisi profil pratinjau (SQLite, demo_mode)
 * tanpa kredensial, jadi aman di-commit. Untuk produksi jangan menyimpan kata
 * sandi di berkas itu — gunakan environment variables HRIS_* (lihat config.php)
 * atau isi nilai di sini pada server Anda dan hindari men-commit rahasia.
 */
return [
    'app_env'     => 'production',          // production | development
    'app_url'     => 'https://hris.asiaraya.co.id', // '' when installed at domain root
    'app_key'     => 'ganti-dengan-32-karakter-acak',
    'timezone'    => 'Asia/Jakarta',
    'demo_mode'   => false,

    'db_driver'   => 'mysql',
    'db_host'     => '127.0.0.1',
    'db_port'     => '3306',
    'db_database' => 'asiaraya_hris',
    'db_username' => 'asiaraya',
    'db_password' => 'ubah-password-ini',

    // Google Workspace SSO (optional). Employees are matched by employees.email.
    'google_client_id'     => '',
    'google_client_secret' => '',
    'google_redirect_uri'  => '',           // default: <app_url>/login/google/callback

    // QR code for payslip validation: online API (default) or local phpqrcode
    'qr_mode' => 'online',
];
