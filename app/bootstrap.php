<?php
/**
 * Application bootstrap - no Composer, all dependencies vendored manually.
 */
declare(strict_types=1);

define('HRIS_START', microtime(true));
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('VENDOR_PATH', BASE_PATH . '/vendor');
define('APP_VERSION', '2.4.1');
define('APP_BUILD', '2026.10');

$GLOBALS['config'] = require APP_PATH . '/config/config.php';

date_default_timezone_set($GLOBALS['config']['timezone'] ?? 'Asia/Jakarta');
mb_internal_encoding('UTF-8');
setlocale(LC_NUMERIC, 'C');

if (($GLOBALS['config']['app_env'] ?? 'production') === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

/* ---------------------------------------------------------------------------
 | Minimal PSR-4 style autoloader for the app + vendored (manual) libraries
 |-------------------------------------------------------------------------*/
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'App\\'        => APP_PATH . '/',
        'Dompdf\\'     => VENDOR_PATH . '/dompdf/src/',
        'FontLib\\'    => VENDOR_PATH . '/php-font-lib/src/FontLib/',
        'Svg\\'        => VENDOR_PATH . '/php-svg-lib/src/Svg/',
        'Sabberworm\\CSS\\' => VENDOR_PATH . '/php-css-parser/src/',
        'Masterminds\\' => VENDOR_PATH . '/html5/src/',
    ];
    foreach ($prefixes as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $path = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) {
                require $path;
                return;
            }
        }
    }
});

// Legacy (non namespaced) QR library - phpqrcode
$qr = VENDOR_PATH . '/phpqrcode/qrlib.php';
if (is_file($qr)) {
    require_once $qr;
}

require APP_PATH . '/Core/helpers.php';
\App\Core\Session::start();
\App\Core\Database::boot($GLOBALS['config']);
\App\Core\Settings::boot();
\App\Core\Auth::boot();
