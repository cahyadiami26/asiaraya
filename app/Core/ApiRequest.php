<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Pembaca request untuk REST API /api/v1.
 *
 * Aplikasi Android mengirim JSON (bukan form), sehingga badan request dibaca
 * dari php://input. Lingkungan pratinjau (php-wasm) sudah mem-parse JSON di
 * bridge Node dan menaruhnya di $_POST, jadi keduanya digabung: JSON menang
 * bila ada, $_POST dipakai sebagai cadangan.
 */
final class ApiRequest
{
    private static ?array $body = null;
    private static ?string $requestId = null;

    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function path(): string
    {
        return current_path();
    }

    /** Header request (nama tanpa awalan HTTP_, tidak peka huruf besar/kecil). */
    public static function header(string $name): ?string
    {
        $key = strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER['HTTP_' . $key] ?? $_SERVER[$key] ?? null;

        if ($value === null && function_exists('getallheaders')) {
            foreach ((array) getallheaders() as $headerName => $headerValue) {
                if (strcasecmp((string) $headerName, $name) === 0) {
                    $value = $headerValue;
                    break;
                }
            }
        }

        if (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        }

        return $value === null ? null : trim((string) $value);
    }

    /** Bearer token dari header Authorization (fallback X-Api-Token / access_token). */
    public static function bearer(): ?string
    {
        $header = (string) self::header('authorization');
        if ($header !== '' && preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return $m[1];
        }

        $alt = (string) self::header('x-api-token');
        if ($alt !== '') {
            return $alt;
        }

        $query = (string) ($_GET['access_token'] ?? '');
        return $query !== '' ? $query : null;
    }

    /** Badan request sebagai array (JSON, multipart, atau urlencoded). */
    public static function body(): array
    {
        if (self::$body !== null) {
            return self::$body;
        }

        $data = is_array($_POST) ? $_POST : [];
        $raw = '';

        try {
            $stream = @file_get_contents('php://input');
            if (is_string($stream)) {
                $raw = $stream;
            }
        } catch (\Throwable) {
            $raw = '';
        }

        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $data = array_replace($data, $json);
            }
        }

        return self::$body = $data;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $body = self::body();
        $value = $body[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public static function has(string $key): bool
    {
        $body = self::body();
        return array_key_exists($key, $body);
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public static function queryInt(string $key, int $default, int $min, int $max): int
    {
        $value = self::query($key);
        if ($value === null || $value === '' || !is_numeric($value)) {
            return $default;
        }
        return max($min, min($max, (int) $value));
    }

    public static function page(): int
    {
        return self::queryInt('page', 1, 1, 10000);
    }

    public static function perPage(int $default = 20, int $max = 100): int
    {
        $value = self::queryInt('per_page', $default, 1, $max);
        return $value > 0 ? $value : $default;
    }

    public static function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    /** Idempotency-Key untuk aksi tulis yang tidak boleh dobel (presensi). */
    public static function idempotencyKey(): ?string
    {
        $key = self::header('idempotency-key') ?? self::header('x-idempotency-key');
        if ($key === null || $key === '') {
            return null;
        }
        $key = preg_replace('/[^A-Za-z0-9._\-]/', '', $key) ?? '';
        return $key === '' ? null : substr($key, 0, 80);
    }

    /** Satu ID request untuk korelasi log aplikasi Android ↔ server. */
    public static function requestId(): string
    {
        if (self::$requestId !== null) {
            return self::$requestId;
        }

        $incoming = self::header('x-request-id');
        if ($incoming !== null && $incoming !== '' && preg_match('/^[A-Za-z0-9._\-]{6,64}$/', $incoming)) {
            return self::$requestId = $incoming;
        }

        return self::$requestId = 'req_' . bin2hex(random_bytes(8));
    }
}
