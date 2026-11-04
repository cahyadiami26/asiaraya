<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Employee ID (NIK) generator.
 *
 * The format is configurable from Pengaturan → Unit Organisasi & Format ID:
 *   {COMPANY}  company prefix (default ARG)
 *   {BU}       business unit code (JKT, SBY, …)
 *   {DEPT}     department code
 *   {YEAR}     four digit year      {YY} two digit year     {MONTH} two digit month
 *   {SEQ}      running number       {SEQ:4} zero padded to 4 digits
 *
 * The running number is kept in `id_sequences` per scope (business unit, or
 * global when `employee_id_scope` = GLOBAL). Existing data is used to seed the
 * counter, so the generator also works on an already populated database.
 */
final class EmployeeCode
{
    public const DEFAULT_FORMAT = 'ARG-{BU}-{SEQ:4}';

    public static function format(): string
    {
        $format = trim((string) Settings::get('employee_id_format', self::DEFAULT_FORMAT));
        return $format !== '' ? $format : self::DEFAULT_FORMAT;
    }

    public static function companyPrefix(): string
    {
        $prefix = trim((string) Settings::get('employee_id_company_prefix', 'ARG'));
        return $prefix !== '' ? strtoupper($prefix) : 'ARG';
    }

    public static function scope(): string
    {
        $scope = strtoupper((string) Settings::get('employee_id_scope', 'BU'));
        return in_array($scope, ['BU', 'GLOBAL'], true) ? $scope : 'BU';
    }

    /** Placeholder catalogue shown in the settings UI. */
    public static function placeholders(): array
    {
        return [
            '{COMPANY}' => 'Prefiks perusahaan',
            '{BU}'      => 'Kode business unit',
            '{DEPT}'   => 'Kode departemen',
            '{YEAR}'   => 'Tahun 4 digit',
            '{YY}'     => 'Tahun 2 digit',
            '{MONTH}'  => 'Bulan 2 digit',
            '{SEQ}'    => 'Nomor urut (4 digit)',
            '{SEQ:6}'  => 'Nomor urut 6 digit',
        ];
    }

    /** Validate a custom format; returns a list of human readable errors. */
    public static function validate(string $format): array
    {
        $errors = [];
        if (trim($format) === '') {
            $errors[] = 'Format ID tidak boleh kosong.';
            return $errors;
        }
        if (!str_contains($format, '{SEQ')) {
            $errors[] = 'Format harus mengandung token nomor urut, misalnya {SEQ:4}.';
        }
        if (preg_match('/[^A-Za-z0-9\-_\/{}\.:]/', $format)) {
            $errors[] = 'Format hanya boleh berisi huruf, angka, tanda hubung, garis miring, dan token {…}.';
        }
        preg_match_all('/\{([A-Z]+)(?::(\d+))?\}/', $format, $matches, PREG_SET_ORDER);
        $allowed = ['COMPANY', 'BU', 'DEPT', 'YEAR', 'YY', 'MONTH', 'SEQ'];
        foreach ($matches as $match) {
            if (!in_array($match[1], $allowed, true)) {
                $errors[] = 'Token tidak dikenal: {' . $match[1] . '}.';
            }
        }
        if (strlen($format) > 60) {
            $errors[] = 'Format maksimal 60 karakter.';
        }
        return $errors;
    }

    /** Render a code using the current format and a concrete sequence number. */
    public static function render(int $sequence, ?int $businessUnitId = null, ?int $departmentId = null, ?string $format = null, ?string $companyPrefix = null): string
    {
        $format = $format ?? self::format();
        $company = strtoupper($companyPrefix ?? self::companyPrefix());

        $unitCode = '';
        if ($businessUnitId) {
            $unitCode = strtoupper((string) Database::value('SELECT code FROM business_units WHERE id = ?', [$businessUnitId], ''));
        }
        $deptCode = '';
        if ($departmentId) {
            $deptCode = strtoupper((string) Database::value('SELECT code FROM departments WHERE id = ?', [$departmentId], ''));
        }

        $replacements = [
            '{COMPANY}' => $company,
            '{BU}'      => $unitCode ?: 'HQ',
            '{DEPT}'    => $deptCode ?: 'GEN',
            '{YEAR}'    => date('Y'),
            '{YY}'      => date('y'),
            '{MONTH}'   => date('m'),
        ];

        $code = strtr($format, $replacements);

        $code = preg_replace_callback('/\{SEQ(?::(\d+))?\}/', static function (array $m) use ($sequence): string {
            $digits = isset($m[1]) && $m[1] !== '' ? (int) $m[1] : 4;
            return str_pad((string) $sequence, $digits, '0', STR_PAD_LEFT);
        }, $code);

        return strtoupper(trim((string) $code));
    }

    /** Scope key used in id_sequences. */
    public static function scopeKey(?int $businessUnitId): string
    {
        if (self::scope() === 'GLOBAL') {
            return 'employee:GLOBAL';
        }
        return 'employee:BU:' . (int) $businessUnitId;
    }

    /**
     * Ubah format menjadi pola regex untuk membaca nomor urut dari kode lama.
     *
     * Hanya token `{SEQ}` yang ditangkap (grup bernama `seq`); token lain
     * dicocokkan sesuai bentuknya. Mengembalikan null bila format tidak valid
     * atau tidak memuat `{SEQ}`.
     */
    private static function sequencePattern(string $format): ?string
    {
        $regex = '';
        $offset = 0;
        $hasSeq = false;

        while (preg_match('/\{([A-Z]+)(?::(\d+))?\}/', $format, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $regex .= preg_quote(substr($format, $offset, $m[0][1] - $offset), '/');
            $offset = $m[0][1] + strlen($m[0][0]);

            switch ($m[1][0]) {
                case 'SEQ':
                    // Token SEQ kedua (bila ada) tidak perlu ditangkap lagi.
                    $regex .= $hasSeq ? '(?:\d+)' : '(?P<seq>\d+)';
                    $hasSeq = true;
                    break;
                case 'YEAR':
                    $regex .= '\d{4}';
                    break;
                case 'YY':
                case 'MONTH':
                    $regex .= '\d{2}';
                    break;
                case 'COMPANY':
                case 'BU':
                case 'DEPT':
                    $regex .= '[A-Z0-9]+';
                    break;
                default:
                    return null;
            }
        }

        if (!$hasSeq) {
            return null;
        }

        $regex .= preg_quote(substr($format, $offset), '/');

        return '/^' . $regex . '$/i';
    }

    /**
     * Nomor urut yang dipakai sebuah kode (null bila tidak dapat dibaca).
     *
     * Kode dibaca memakai pola format yang sedang aktif, bukan sekadar digit
     * terakhir — sehingga format seperti `{YEAR}-{SEQ:4}` atau
     * `{BU}-{SEQ:3}-{MONTH}` tidak salah hitung.
     */
    public static function sequenceOf(string $code, ?string $format = null): ?int
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }

        $pattern = self::sequencePattern($format ?? self::format());
        if ($pattern !== null && preg_match($pattern, $code, $m) && isset($m['seq']) && $m['seq'] !== '') {
            return (int) $m['seq'];
        }

        // Kode lama yang tidak mengikuti format saat ini: pakai digit terakhir.
        if (preg_match('/(\d+)\s*$/', $code, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /** Highest sequence already used for the scope (used to seed the counter). */
    public static function existingMax(?int $businessUnitId): int
    {
        $sql = 'SELECT employee_code FROM employees';
        $params = [];
        if (self::scope() !== 'GLOBAL') {
            $sql .= ' WHERE business_unit_id = ?';
            $params[] = (int) $businessUnitId;
        }

        $format = self::format();
        $max = 0;
        foreach (Database::all($sql, $params) as $row) {
            $sequence = self::sequenceOf((string) $row['employee_code'], $format);
            if ($sequence !== null) {
                $max = max($max, $sequence);
            }
        }
        return $max;
    }

    /**
     * Reserve the next code. Increments the stored counter atomically enough for
     * the single-writer HR workflow (WAL/row lock not required).
     */
    public static function next(?int $businessUnitId, ?int $departmentId = null): string
    {
        $scopeKey = self::scopeKey($businessUnitId);
        $row = Database::one('SELECT * FROM id_sequences WHERE scope_key = ?', [$scopeKey]);

        // Counter bisa tertinggal dari data nyata (mis. setelah impor data atau
        // perubahan format), jadi nilai terpakai tertinggi selalu dijadikan lantai.
        $floor = self::existingMax($businessUnitId);

        if ($row) {
            $next = max((int) $row['last_number'], $floor) + 1;
            Database::update('id_sequences', [
                'last_number' => $next,
                'updated_at'  => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int) $row['id']]);
        } else {
            $next = $floor + 1;
            try {
                Database::insert('id_sequences', [
                    'scope_key'   => $scopeKey,
                    'last_number' => $next,
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
            } catch (\PDOException $e) {
                // Permintaan lain lebih dulu membuat baris scope ini
                // (scope_key unik) — ambil ulang lalu naikkan.
                $row = Database::one('SELECT * FROM id_sequences WHERE scope_key = ?', [$scopeKey]);
                if (!$row) {
                    throw $e;
                }
                $next = max((int) $row['last_number'], $floor) + 1;
                Database::update('id_sequences', [
                    'last_number' => $next,
                    'updated_at'  => date('Y-m-d H:i:s'),
                ], 'id = ?', [(int) $row['id']]);
            }
        }

        return self::render($next, $businessUnitId, $departmentId);
    }

    /**
     * Kode berikutnya yang dijamin belum dipakai karyawan mana pun.
     *
     * `next()` sudah mengonsumsi counter dan memakai lantai data nyata; loop di
     * sini menangani sisa kasus langka (kode bentrok dari scope/format lain).
     */
    public static function nextUnique(?int $businessUnitId, ?int $departmentId = null, int $maxAttempts = 100): string
    {
        $code = self::next($businessUnitId, $departmentId);
        for ($attempt = 1; $attempt < $maxAttempts && self::isTaken($code); $attempt++) {
            $code = self::next($businessUnitId, $departmentId);
        }

        return $code;
    }

    /** Apakah kode sudah dipakai karyawan (perbandingan tanpa beda huruf besar/kecil). */
    public static function isTaken(string $code): bool
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM employees WHERE UPPER(employee_code) = ?',
            [strtoupper(trim($code))],
            0
        ) > 0;
    }

    /**
     * Non-consuming preview for the employee form.
     *
     * Tanpa business unit, `{BU}` tidak punya nilai. Alih-alih memakai kode
     * karangan ("HQ") yang tidak pernah cocok dengan format, preview memakai
     * unit aktif pertama supaya bentuk ID yang ditampilkan benar-benar sama
     * dengan yang akan diterbitkan.
     */
    public static function preview(?int $businessUnitId, ?int $departmentId = null): string
    {
        $businessUnitId = $businessUnitId ?: self::defaultUnitId();

        $scopeKey = self::scopeKey($businessUnitId);
        $row = Database::one('SELECT last_number FROM id_sequences WHERE scope_key = ?', [$scopeKey]);
        $next = max((int) ($row['last_number'] ?? 0), self::existingMax($businessUnitId)) + 1;

        return self::render($next, $businessUnitId, $departmentId);
    }

    /** Unit aktif pertama — dipakai saat preview belum menentukan unit. */
    public static function defaultUnitId(): ?int
    {
        $id = Database::value('SELECT id FROM business_units WHERE is_active = 1 ORDER BY id LIMIT 1', [], 0);

        return $id ? (int) $id : null;
    }

    /** Reset the counter of a scope (used when the format changes). */
    public static function resetSequence(?int $businessUnitId = null): void
    {
        Database::run('DELETE FROM id_sequences WHERE scope_key = ?', [self::scopeKey($businessUnitId)]);
    }
}
