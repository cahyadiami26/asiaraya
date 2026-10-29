<?php
/**
 * Autoloader manual (tanpa Composer) untuk pustaka pihak ketiga.
 *
 * Paket yang tersedia di folder ini dipasang manual sesuai kebutuhan proyek:
 *   dompdf/       — dompdf 3.1.6 (HTML → PDF), LGPL-2.1
 *   php-font-lib/ — dompdf/php-font-lib 1.0.2
 *   php-svg-lib/  — dompdf/php-svg-lib 1.0.2
 *   html5-php/    — masterminds/html5 2.12.0
 *   phpqrcode/    — phpqrcode (fallback QR lokal, dipasang manual)
 *
 * Tidak ada Composer, tidak ada jaringan: cukup `require vendor/autoload.php`.
 */
declare(strict_types=1);

if (!defined('ASRAYA_VENDOR_AUTOLOAD')) {
    define('ASRAYA_VENDOR_AUTOLOAD', true);

    spl_autoload_register(static function (string $class): void {
        $prefixes = [
            'Dompdf\\'       => __DIR__ . '/dompdf/src/',
            'FontLib\\'      => __DIR__ . '/php-font-lib/src/FontLib/',
            'Svg\\'          => __DIR__ . '/php-svg-lib/src/Svg/',
            'Masterminds\\'  => __DIR__ . '/html5-php/src/',
        ];

        foreach ($prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
            return;
        }
    });

    // Class global bawaan dompdf (bukan PSR-4) berada di lib/.
    require_once __DIR__ . '/dompdf/lib/Cpdf.php';
}
