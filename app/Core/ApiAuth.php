<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Autentikasi token untuk REST API /api/v1 (aplikasi Android).
 *
 * Token bersifat opaque (bukan JWT) supaya bisa dicabut seketika dari server:
 *   access token  : 8 jam, disimpan sebagai SHA-256 di api_tokens.token_hash
 *   refresh token : 30 hari, dirotasi setiap kali dipakai
 *
 * Setelah token valid, Auth::setUser() dipanggil sehingga model dan controller
 * yang sudah ada (Auth::id(), Auth::isManagerOf(), Audit, dsb.) bekerja tanpa
 * perubahan pada request API.
 */
final class ApiAuth
{
    /** Masa berlaku access token (menit). */
    public const ACCESS_TTL_MINUTES = 480;

    /** Masa berlaku refresh token (hari). */
    public const REFRESH_TTL_DAYS = 30;

    /** Batas umur URL unduhan bertanda tangan (detik). */
    public const DOWNLOAD_TTL_SECONDS = 300;

    private static ?array $current = null;

    private static bool $resolved = false;

    /** Karyawan pemilik token pada request ini (null bila belum/tidak valid). */
    public static function authenticate(): ?array
    {
        if (self::$resolved) {
            return self::$current['employee'] ?? null;
        }
        self::$resolved = true;

        $raw = ApiRequest::bearer();
        if ($raw === null || $raw === '' || !Database::tableExists('api_tokens')) {
            return null;
        }

        $row = Database::one('SELECT * FROM api_tokens WHERE token_hash = ? LIMIT 1', [self::hash($raw)]);
        if (!$row || !self::isUsable($row)) {
            return null;
        }

        $employee = Auth::findById((int) $row['employee_id']);
        if (!$employee) {
            return null;
        }

        self::touch($row);
        Auth::setUser($employee);

        self::$current = ['token' => $row, 'employee' => $employee];
        return $employee;
    }

    /** @return array{token:array,employee:array}|null */
    public static function current(): ?array
    {
        return self::$current;
    }

    public static function currentTokenRow(): ?array
    {
        return self::$current['token'] ?? null;
    }

    /** Buat pasangan access + refresh token baru untuk satu perangkat. */
    public static function issue(int $employeeId, array $meta = []): array
    {
        $access = 'ar_' . bin2hex(random_bytes(32));
        $refresh = 'arr_' . bin2hex(random_bytes(32));
        $now = time();
        $expires = date('Y-m-d H:i:s', $now + self::ACCESS_TTL_MINUTES * 60);
        $refreshExpires = date('Y-m-d H:i:s', $now + self::REFRESH_TTL_DAYS * 86400);

        Database::insert('api_tokens', [
            'employee_id'        => $employeeId,
            'name'               => substr(trim((string) ($meta['device_name'] ?? '')) ?: 'Android', 0, 80),
            'token_hash'         => self::hash($access),
            'refresh_hash'       => self::hash($refresh),
            'abilities'          => '*',
            'device_name'        => substr(trim((string) ($meta['device_name'] ?? '')), 0, 120) ?: null,
            'device_platform'    => substr(trim((string) ($meta['platform'] ?? 'android')), 0, 40) ?: 'android',
            'app_version'        => substr(trim((string) ($meta['app_version'] ?? '')), 0, 30) ?: null,
            'last_used_at'       => date('Y-m-d H:i:s'),
            'last_ip'            => ApiRequest::ip(),
            'expires_at'         => $expires,
            'refresh_expires_at' => $refreshExpires,
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        self::prune($employeeId);

        return [
            'token_type'         => 'Bearer',
            'access_token'       => $access,
            'expires_at'         => date('c', strtotime($expires)),
            'expires_in'         => self::ACCESS_TTL_MINUTES * 60,
            'refresh_token'      => $refresh,
            'refresh_expires_at' => date('c', strtotime($refreshExpires)),
        ];
    }

    /**
     * Rotasi token: refresh token lama langsung dicabut dan diganti pasangan baru.
     *
     * @return array{ok:bool,tokens?:array,error?:string}
     */
    public static function refresh(string $rawRefresh): array
    {
        if ($rawRefresh === '' || !Database::tableExists('api_tokens')) {
            return ['ok' => false, 'error' => 'Refresh token tidak valid.'];
        }

        $row = Database::one('SELECT * FROM api_tokens WHERE refresh_hash = ? LIMIT 1', [self::hash($rawRefresh)]);
        if (!$row || $row['revoked_at'] !== null) {
            return ['ok' => false, 'error' => 'Refresh token tidak valid atau sudah dicabut.'];
        }
        if ($row['refresh_expires_at'] !== null && strtotime((string) $row['refresh_expires_at']) <= time()) {
            return ['ok' => false, 'error' => 'Refresh token sudah kedaluwarsa. Silakan masuk kembali.'];
        }

        $employee = Auth::findById((int) $row['employee_id']);
        if (!$employee) {
            return ['ok' => false, 'error' => 'Akun tidak aktif.'];
        }

        $access = 'ar_' . bin2hex(random_bytes(32));
        $refresh = 'arr_' . bin2hex(random_bytes(32));
        $now = time();
        $expires = date('Y-m-d H:i:s', $now + self::ACCESS_TTL_MINUTES * 60);
        $refreshExpires = date('Y-m-d H:i:s', $now + self::REFRESH_TTL_DAYS * 86400);

        Database::update('api_tokens', [
            'token_hash'         => self::hash($access),
            'refresh_hash'       => self::hash($refresh),
            'expires_at'         => $expires,
            'refresh_expires_at' => $refreshExpires,
            'last_used_at'       => date('Y-m-d H:i:s'),
            'last_ip'            => ApiRequest::ip(),
            'updated_at'         => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int) $row['id']]);

        Audit::log('api.refresh', 'api_token', (int) $row['id'], ['employee' => $employee['employee_code']]);

        return [
            'ok'     => true,
            'tokens' => [
                'token_type'         => 'Bearer',
                'access_token'       => $access,
                'expires_at'         => date('c', strtotime($expires)),
                'expires_in'         => self::ACCESS_TTL_MINUTES * 60,
                'refresh_token'      => $refresh,
                'refresh_expires_at' => date('c', strtotime($refreshExpires)),
            ],
        ];
    }

    /** Cabut token pada request ini (logout perangkat). */
    public static function revokeCurrent(): bool
    {
        $row = self::currentTokenRow();
        if (!$row) {
            return false;
        }

        Database::update('api_tokens', [
            'revoked_at'   => date('Y-m-d H:i:s'),
            'refresh_hash' => null,
            'updated_at'   => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int) $row['id']]);

        Audit::log('api.logout', 'api_token', (int) $row['id']);
        self::$current = null;
        return true;
    }

    /** Cabut seluruh token milik satu karyawan (mis. dipakai saat reset password). */
    public static function revokeAll(int $employeeId): int
    {
        return Database::update('api_tokens', [
            'revoked_at'   => date('Y-m-d H:i:s'),
            'refresh_hash' => null,
            'updated_at'   => date('Y-m-d H:i:s'),
        ], 'employee_id = ? AND revoked_at IS NULL', [$employeeId]);
    }

    /** Daftar perangkat aktif (untuk keperluan keamanan akun di aplikasi). */
    public static function sessions(int $employeeId): array
    {
        return Database::all(
            'SELECT id, name, device_name, device_platform, app_version, last_used_at, last_ip,
                    expires_at, refresh_expires_at, created_at
             FROM api_tokens
             WHERE employee_id = ? AND revoked_at IS NULL AND expires_at > ?
             ORDER BY last_used_at DESC, id DESC',
            [$employeeId, date('Y-m-d H:i:s')]
        );
    }

    /* ------------------------------------------------------------- signing */

    /**
     * Tanda tangan URL unduhan (slip gaji, bukti potong) agar aplikasi Android
     * bisa menyerahkan tautan ke DownloadManager tanpa header Authorization.
     *
     * @return array{expires:int,signature:string}
     */
    public static function signature(string $resource, int|string $id, int $employeeId, int $ttl = self::DOWNLOAD_TTL_SECONDS): array
    {
        $expires = time() + max(30, $ttl);
        return [
            'expires'   => $expires,
            'signature' => hash_hmac('sha256', self::signaturePayload($resource, $id, $employeeId, $expires), self::key()),
        ];
    }

    public static function verifySignature(
        string $resource,
        int|string $id,
        int $employeeId,
        ?string $expires,
        ?string $signature
    ): bool {
        if ($expires === null || $signature === null || !ctype_digit($expires)) {
            return false;
        }
        if ((int) $expires < time()) {
            return false;
        }

        $expected = hash_hmac('sha256', self::signaturePayload($resource, $id, $employeeId, (int) $expires), self::key());
        return hash_equals($expected, $signature);
    }

    /** URL relatif siap pakai untuk satu sumber daya terproteksi. */
    public static function signedUrl(string $path, string $resource, int|string $id, int $employeeId, bool $download = false): string
    {
        $sign = self::signature($resource, $id, $employeeId);
        return url($path) . '?' . http_build_query(array_filter([
            'employee'  => $employeeId,
            'expires'   => $sign['expires'],
            'signature' => $sign['signature'],
            'download'  => $download ? 1 : null,
        ], static fn ($v) => $v !== null));
    }

    /* ------------------------------------------------------------- internal */

    private static function hash(string $raw): string
    {
        return hash('sha256', $raw);
    }

    private static function key(): string
    {
        return (string) config('app_key', 'asiaraya-hris');
    }

    private static function signaturePayload(string $resource, int|string $id, int $employeeId, int $expires): string
    {
        return implode('|', ['api-v1', $resource, (string) $id, (string) $employeeId, (string) $expires]);
    }

    private static function isUsable(array $row): bool
    {
        if ($row['revoked_at'] !== null) {
            return false;
        }
        $expires = strtotime((string) $row['expires_at']);
        return $expires !== false && $expires > time();
    }

    /** Perbarui last_used_at minimal sekali semenit agar hemat tulis. */
    private static function touch(array $row): void
    {
        $last = $row['last_used_at'] !== null ? strtotime((string) $row['last_used_at']) : false;
        if ($last !== false && time() - $last < 60) {
            return;
        }

        try {
            Database::update('api_tokens', [
                'last_used_at' => date('Y-m-d H:i:s'),
                'last_ip'      => ApiRequest::ip(),
                'updated_at'   => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int) $row['id']]);
        } catch (\Throwable) {
            // tidak kritikal
        }
    }

    /** Bersihkan token kedaluwarsa milik karyawan ini (maksimal 10 perangkat aktif). */
    private static function prune(int $employeeId): void
    {
        try {
            Database::delete(
                'api_tokens',
                'employee_id = ? AND (expires_at < ? OR (revoked_at IS NOT NULL AND revoked_at < ?))',
                [$employeeId, date('Y-m-d H:i:s'), date('Y-m-d H:i:s', time() - 7 * 86400)]
            );

            $rows = Database::all(
                'SELECT id FROM api_tokens WHERE employee_id = ? AND revoked_at IS NULL ORDER BY id DESC',
                [$employeeId]
            );
            foreach (array_slice($rows, 10) as $extra) {
                Database::update('api_tokens', [
                    'revoked_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [(int) $extra['id']]);
            }
        } catch (\Throwable) {
            // pembersihan bersifat opsional
        }
    }
}
