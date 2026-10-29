<?php
/**
 * Konfigurasi lingkungan.
 *
 * Berkas ini SENGAJA ikut disertakan di repository sebagai profil
 * pratinjau/demo: SQLite + demo_mode, tanpa kredensial apa pun.
 *
 * PENTING untuk produksi: jangan menulis kata sandi/host sungguhan di sini
 * karena repository ini publik. Pakai environment variables yang selalu
 * menang atas nilai di bawah:
 *
 *   HRIS_APP_ENV, HRIS_APP_URL, HRIS_DB_DRIVER, HRIS_DB_HOST, HRIS_DB_PORT,
 *   HRIS_DB_DATABASE, HRIS_DB_USERNAME, HRIS_DB_PASSWORD,
 *   HRIS_GOOGLE_CLIENT_ID, HRIS_GOOGLE_CLIENT_SECRET, HRIS_DEMO_MODE, dst.
 *
 * Contoh (Apache SetEnv / systemd Environment / docker -e):
 *   HRIS_DB_DRIVER=mysql HRIS_DB_DATABASE=asiaraya_hris
 *   HRIS_DB_USERNAME=asiaraya HRIS_DB_PASSWORD=••••
 *
 * Lihat app/config/env.example.php untuk daftar kunci lengkap.
 */
return [
    'app_env'    => 'development',
    'demo_mode'  => true,
    'db_driver'  => 'sqlite',
    'app_url'    => '',
];
