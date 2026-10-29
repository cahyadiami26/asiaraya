<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Key/value application settings stored in the `settings` table, cached per request.
 */
final class Settings
{
    private static array $values = [];
    private static bool $loaded = false;

    public static function boot(): void
    {
        self::$loaded = false;
        self::$values = [];
    }

    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;
        if (!Database::tableExists('settings')) {
            return;
        }
        foreach (Database::all('SELECT setting_key, setting_value FROM settings') as $row) {
            self::$values[$row['setting_key']] = $row['setting_value'];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        return self::$values[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return $value === null || $value === '' ? $default : (int) $value;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $value = self::get($key);
        return $value === null || $value === '' ? $default : (float) $value;
    }

    public static function json(string $key, array $default = []): array
    {
        $value = self::get($key);
        if (!$value) {
            return $default;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : $default;
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        self::load();
        $value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
        $existing = Database::value('SELECT id FROM settings WHERE setting_key = ?', [$key]);
        if ($existing) {
            Database::update('settings', ['setting_value' => $value, 'setting_group' => $group, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$existing]);
        } else {
            Database::insert('settings', [
                'setting_key'   => $key,
                'setting_value' => $value,
                'setting_group' => $group,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }
        self::$values[$key] = $value;
    }

    public static function many(array $pairs, string $group = 'general'): void
    {
        foreach ($pairs as $key => $value) {
            self::set($key, $value, $group);
        }
    }

    public static function all(): array
    {
        self::load();
        return self::$values;
    }
}
