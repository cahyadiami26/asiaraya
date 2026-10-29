<?php
declare(strict_types=1);

namespace App\Core;

/**
 * QR code untuk validasi slip gaji.
 *
 * Prioritas: pustaka online (api.qrserver.com) sesuai kebutuhan proyek, lalu
 * fallback lokal phpqrcode (vendor/phpqrcode) bila server tidak dapat
 * menjangkau internet — misalnya pada sandbox pratinjau.
 */
final class Qr
{
    private const ENDPOINT = 'https://api.qrserver.com/v1/create-qr-code/';

    /** @return string|null path absolut file PNG, atau null bila gagal */
    public static function png(string $data, int $size = 220, int $margin = 8): ?string
    {
        $dir = STORAGE_PATH . '/uploads/qr';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        $file = $dir . '/' . substr(sha1($data . '|' . $size . '|' . $margin), 0, 24) . '.png';
        if (is_file($file) && (int) @filesize($file) > 100) {
            return $file;
        }

        if (self::download($data, $size, $margin, $file)) {
            return $file;
        }
        if (self::local($data, $file)) {
            return $file;
        }

        return null;
    }

    private static function download(string $data, int $size, int $margin, string $file): bool
    {
        $url = self::ENDPOINT . '?size=' . $size . 'x' . $size
            . '&margin=' . $margin . '&format=png&color=0b2135&bgcolor=ffffff&data=' . rawurlencode($data);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT        => 4,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT      => 'AsiaRaya-HRIS/1.0',
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (is_string($body) && $status === 200 && self::looksLikePng($body)) {
                if (@file_put_contents($file, $body) !== false) {
                    return true;
                }
            }
        }

        $context = stream_context_create(['http' => ['timeout' => 3], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $body = @file_get_contents($url, false, $context);
        if (is_string($body) && self::looksLikePng($body)) {
            return @file_put_contents($file, $body) !== false;
        }

        return false;
    }

    private static function looksLikePng(string $body): bool
    {
        return strlen($body) > 100 && str_starts_with($body, "\x89PNG\r\n\x1a\n");
    }

    private static function local(string $data, string $file): bool
    {
        if (!class_exists('QRcode')) {
            $lib = VENDOR_PATH . '/phpqrcode/qrlib.php';
            if (!is_file($lib)) {
                return false;
            }
            require_once $lib;
        }
        if (!class_exists('QRcode')) {
            return false;
        }

        try {
            $level = defined('QR_ECLEVEL_M') ? QR_ECLEVEL_M : 0;
            \QRcode::png($data, $file, $level, 6, 2);
        } catch (\Throwable) {
            return false;
        }

        return is_file($file) && (int) @filesize($file) > 100;
    }
}
