<?php
/**
 * Copy this file to app/config/env.php and adjust for your server.
 * app/config/env.php is git-ignored and must never be committed.
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
