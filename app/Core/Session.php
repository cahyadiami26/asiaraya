<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session handling with hardened cookie defaults and role/user memoisation.
 */
final class Session
{
    public static function start(): void
    {
        // The sandbox preview bridge (tools/preview) runs PHP in a long lived
        // process where the SAPI cannot manage cookies; it supplies $_SESSION
        // itself and sets this flag. PHP-FPM never sets it.
        if (!empty($GLOBALS['HRIS_EXTERNAL_SESSION'])) {
            $_SESSION = $_SESSION ?? [];
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $config = $GLOBALS['config'];
        if (PHP_SAPI !== 'cli') {
            session_name($config['session_name'] ?? 'ASRAYA_HRIS');
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        @session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);
        return $value;
    }

    public static function regenerate(): void
    {
        if (!empty($GLOBALS['HRIS_EXTERNAL_SESSION'])) {
            // Ask the external session host for a fresh id (rotation on login).
            $GLOBALS['HRIS_SESSION_ROTATE'] = true;
            return;
        }

        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function flashes(): array
    {
        $items = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $items;
    }
}
