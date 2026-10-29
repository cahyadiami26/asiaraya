<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Pembungkus dompdf (dipasang manual di vendor/dompdf, tanpa Composer).
 * Dipakai untuk slip gaji & laporan PDF.
 */
final class Pdf
{
    private static bool $booted = false;

    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        $autoload = VENDOR_PATH . '/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new \RuntimeException('Pustaka dompdf tidak ditemukan di vendor/dompdf.');
        }
        // Class global tambahan dompdf (Cpdf) berada di lib/ dan bukan PSR-4.
        $cpdf = VENDOR_PATH . '/dompdf/lib/Cpdf.php';
        if (is_file($cpdf) && !class_exists('Dompdf\\Cpdf')) {
            require_once $cpdf;
        }
        self::$booted = true;
    }

    /** @return array<string,mixed> */
    private static function options(): array
    {
        $cache = STORAGE_PATH . '/cache/dompdf';
        if (!is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }

        return [
            'defaultFont'         => 'DejaVu Sans',
            'defaultMediaType'    => 'print',
            'isRemoteEnabled'     => false,
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled'        => false,
            'isJavascriptEnabled' => false,
            'chroot'              => BASE_PATH,
            'tempDir'             => $cache,
            'fontCache'           => $cache,
            'logOutputFile'       => $cache . '/dompdf.log',
            'dpi'                 => 110,
            'fontHeightRatio'     => 1.0,
        ];
    }

    /** Render HTML menjadi string PDF. */
    public static function render(string $html, array $options = [], string $orientation = 'portrait'): string
    {
        self::boot();

        $dompdf = new \Dompdf\Dompdf(self::options() + $options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /** Render lalu kirim ke browser sebagai unduhan/inline. */
    public static function send(string $html, string $filename, bool $download = false, string $orientation = 'portrait'): never
    {
        $pdf = self::render($html, [], $orientation);

        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdf));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        echo $pdf;
        exit;
    }
}
