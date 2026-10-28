<?php
/**
 * Web HRIS PT Asia Raya Group - Konfigurasi Utama
 * Salin file ini menjadi config/config.php lalu sesuaikan nilainya.
 */
define('APP_NAME', 'HRIS PT Asia Raya Group');
define('APP_VERSION', '1.0.0');

define('BASE_PATH', dirname(__DIR__));

/* Database (sesuaikan dengan server Anda) */
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'hris_asia_raya');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

/* Google Login (isi dari Google Cloud Console -> Credentials -> OAuth 2.0) */
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');

/* Upload */
define('UPLOAD_DIR', BASE_PATH . '/uploads');

/* dompdf manual install (tanpa composer) */
define('DOMPDF_DIR', BASE_PATH . '/lib/dompdf');

date_default_timezone_set('Asia/Jakarta');
