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

    /** Highest sequence already used for the scope (used to seed the counter). */
    public static function existingMax(?int $businessUnitId): int
    {
        $sql = 'SELECT employee_code FROM employees';
        $params = [];
        if (self::scope() !== 'GLOBAL') {
            $sql .= ' WHERE business_unit_id = ?';
            $params[] = (int) $businessUnitId;
        }

        $max = 0;
        foreach (Database::all($sql, $params) as $row) {
            if (preg_match('/(\d+)\s*$/', (string) $row['employee_code'], $m)) {
                $max = max($max, (int) $m[1]);
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

        if ($row) {
            $next = (int) $row['last_number'] + 1;
            Database::update('id_sequences', [
                'last_number' => $next,
                'updated_at'  => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int) $row['id']]);
        } else {
            $next = self::existingMax($businessUnitId) + 1;
            Database::insert('id_sequences', [
                'scope_key'   => $scopeKey,
                'last_number' => $next,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
        }

        return self::render($next, $businessUnitId, $departmentId);
    }

    /** Non-consuming preview for the employee form. */
    public static function preview(?int $businessUnitId, ?int $departmentId = null): string
    {
        $scopeKey = self::scopeKey($businessUnitId);
        $row = Database::one('SELECT last_number FROM id_sequences WHERE scope_key = ?', [$scopeKey]);
        $next = $row ? (int) $row['last_number'] + 1 : self::existingMax($businessUnitId) + 1;
        return self::render($next, $businessUnitId, $departmentId);
    }

    /** Reset the counter of a scope (used when the format changes). */
    public static function resetSequence(?int $businessUnitId = null): void
    {
        Database::run('DELETE FROM id_sequences WHERE scope_key = ?', [self::scopeKey($businessUnitId)]);
    }
}
