<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Schema installer.
 *
 * database/schema.sql is written for MySQL. The same file is translated to
 * SQLite at install time so the bundled preview environment can run without a
 * database server. MySQL stays the production target.
 */
final class Schema
{
    /**
     * Versi skema. Naikkan saat batch baru menambah tabel/kolom; bootstrap
     * akan menjalankan ulang installer (idempoten) untuk basis data lama.
     */
    public const VERSION = 17;

    /** Additional indexes (created separately so both engines accept them). */
    private const INDEXES = [
        'CREATE INDEX IF NOT EXISTS idx_employees_code ON employees (employee_code)',
        'CREATE INDEX IF NOT EXISTS idx_employees_bu ON employees (business_unit_id)',
        'CREATE INDEX IF NOT EXISTS idx_employees_dept ON employees (department_id)',
        'CREATE INDEX IF NOT EXISTS idx_employees_manager ON employees (direct_manager_id)',
        'CREATE INDEX IF NOT EXISTS idx_employees_flag ON employees (flag)',
        'CREATE INDEX IF NOT EXISTS idx_schedules_employee ON schedules (employee_id, start_date)',
        'CREATE INDEX IF NOT EXISTS idx_attendance_employee_date ON attendance (employee_id, work_date)',
        'CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance (work_date)',
        'CREATE INDEX IF NOT EXISTS idx_overtime_employee ON overtime_requests (employee_id, work_date)',
        'CREATE INDEX IF NOT EXISTS idx_overtime_status ON overtime_requests (status)',
        'CREATE INDEX IF NOT EXISTS idx_leave_employee ON leave_requests (employee_id, start_date)',
        'CREATE INDEX IF NOT EXISTS idx_leave_status ON leave_requests (status)',
        'CREATE INDEX IF NOT EXISTS idx_payroll_items_period ON payroll_items (period_id)',
        'CREATE INDEX IF NOT EXISTS idx_payroll_items_employee ON payroll_items (employee_id)',
        'CREATE INDEX IF NOT EXISTS idx_payroll_periods_bu ON payroll_periods (business_unit_id, period_year, period_month)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_payroll_period ON payroll_periods (business_unit_id, period_year, period_month)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_attendance_day ON attendance (employee_id, work_date)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_code ON employees (employee_code)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_setting_key ON settings (setting_key)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_leave_balance ON leave_balances (employee_id, leave_type_id, period_year)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_component_code ON salary_components (code)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_leave_type_code ON leave_types (code)',
        'CREATE INDEX IF NOT EXISTS idx_expense_claim_employee ON expense_claims (employee_id, claim_date)',
        'CREATE INDEX IF NOT EXISTS idx_expense_claim_status ON expense_claims (status)',
        'CREATE INDEX IF NOT EXISTS idx_expense_claim_payroll ON expense_claims (payroll_item_id)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_expense_claim_number ON expense_claims (claim_number)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_expense_category_code ON expense_categories (code)',
        'CREATE INDEX IF NOT EXISTS idx_announcement_status ON announcements (status, publish_at)',
        'CREATE INDEX IF NOT EXISTS idx_announcement_audience ON announcements (audience, audience_value)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_announcement_read ON announcement_reads (announcement_id, employee_id)',
        'CREATE INDEX IF NOT EXISTS idx_notification_employee ON notifications (employee_id, read_at)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_review_indicator_code ON review_indicators (code)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_performance_review ON performance_reviews (period_id, employee_id)',
        'CREATE INDEX IF NOT EXISTS idx_performance_manager ON performance_reviews (manager_id, status)',
        'CREATE INDEX IF NOT EXISTS idx_performance_employee ON performance_reviews (employee_id)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_performance_score ON performance_scores (review_id, indicator_id)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_training_code ON trainings (code)',
        'CREATE INDEX IF NOT EXISTS idx_training_session_training ON training_sessions (training_id)',
        'CREATE INDEX IF NOT EXISTS idx_training_session_status ON training_sessions (status, start_date)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_training_participant ON training_participants (session_id, employee_id)',
        'CREATE INDEX IF NOT EXISTS idx_training_participant_employee ON training_participants (employee_id, status)',
        'CREATE INDEX IF NOT EXISTS idx_training_participant_status ON training_participants (session_id, status)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_job_code ON job_vacancies (code)',
        'CREATE INDEX IF NOT EXISTS idx_job_status ON job_vacancies (status, close_date)',
        'CREATE INDEX IF NOT EXISTS idx_job_unit ON job_vacancies (business_unit_id)',
        'CREATE INDEX IF NOT EXISTS idx_candidate_job ON candidates (job_id, status)',
        'CREATE INDEX IF NOT EXISTS idx_candidate_status ON candidates (status)',
        'CREATE INDEX IF NOT EXISTS idx_candidate_email ON candidates (email)',
        'CREATE INDEX IF NOT EXISTS idx_candidate_stage ON candidate_stages (candidate_id, created_at)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_offboarding_number ON offboarding_requests (request_number)',
        'CREATE INDEX IF NOT EXISTS idx_offboarding_employee ON offboarding_requests (employee_id, status)',
        'CREATE INDEX IF NOT EXISTS idx_offboarding_status ON offboarding_requests (status, last_working_date)',
        'CREATE INDEX IF NOT EXISTS idx_clearance_request ON offboarding_clearance (request_id, status)',
        'CREATE INDEX IF NOT EXISTS idx_offboarding_stage ON offboarding_stages (request_id, created_at)',
        'CREATE INDEX IF NOT EXISTS idx_offboarding_asset ON offboarding_assets (request_id, status)',
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_offboarding_bast_number ON offboarding_bast (bast_number)',
        'CREATE INDEX IF NOT EXISTS idx_offboarding_bast_request ON offboarding_bast (request_id, issued_at)',
    ];

    public static function install(bool $withSeed = true, bool $fresh = false): array
    {
        $log = [];
        $sqlFile = BASE_PATH . '/database/schema.sql';
        $sql = (string) file_get_contents($sqlFile);

        if (Database::isSqlite()) {
            $sql = self::toSqlite($sql);
        }

        foreach (self::split($sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            try {
                Database::pdo()->exec($statement);
            } catch (\PDOException $e) {
                if (!str_contains(strtolower($e->getMessage()), 'already exists')) {
                    throw $e;
                }
            }
        }
        $log[] = 'Tabel utama dibuat (' . count(self::split($sql)) . ' statement).';

        foreach (self::INDEXES as $index) {
            $stmt = Database::isSqlite() ? $index : str_replace('CREATE INDEX IF NOT EXISTS', 'CREATE INDEX', $index);
            $stmt = Database::isSqlite() ? $stmt : str_replace('CREATE UNIQUE INDEX IF NOT EXISTS', 'CREATE UNIQUE INDEX', $stmt);
            try {
                Database::pdo()->exec($stmt);
            } catch (\PDOException $e) {
                $message = strtolower($e->getMessage());
                if (!str_contains($message, 'already exists') && !str_contains($message, 'duplicate key name')) {
                    $log[] = 'Index dilewati: ' . substr($stmt, 0, 60) . ' → ' . $e->getMessage();
                }
            }
        }
        $log[] = 'Index performa dibuat.';

        if ($withSeed) {
            $log = array_merge($log, self::seed($fresh));
        }

        self::markInstalled();

        return $log;
    }

    /** Catat versi skema aktif agar upgrade hanya berjalan sekali. */
    private static function markInstalled(): void
    {
        try {
            \App\Core\Settings::set('schema_version', (string) self::VERSION, 'system');
        } catch (\Throwable) {
            // abaikan: versi hanya penanda, bukan data operasional
        }
    }

    /** Apakah basis data perlu di-upgrade ke versi skema terbaru. */
    public static function needsUpgrade(): bool
    {
        return (int) \App\Core\Settings::get('schema_version', 0) < self::VERSION;
    }

    public static function isInstalled(): bool
    {
        return Database::tableExists('employees') && (int) Database::value('SELECT COUNT(*) FROM employees') > 0;
    }

    private static function split(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $parts = preg_split('/;\s*(\n|$)/', $sql) ?: [];
        return array_filter(array_map('trim', $parts), static fn ($p) => $p !== '');
    }

    /** Translate the portable MySQL DDL into SQLite compatible DDL. */
    public static function toSqlite(string $sql): string
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = self::split($sql);
        $out = [];

        foreach ($statements as $statement) {
            if (!preg_match('/^CREATE TABLE/i', $statement)) {
                continue;
            }
            $open = strpos($statement, '(');
            $close = strrpos($statement, ')');
            if ($open === false || $close === false) {
                continue;
            }
            $head = trim(substr($statement, 0, $open));
            $body = substr($statement, $open + 1, $close - $open - 1);

            $lines = [];
            foreach (preg_split('/,\s*\n/', $body) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (preg_match('/^(UNIQUE\s+)?KEY\s+/i', $line) || preg_match('/^CONSTRAINT\s+/i', $line)) {
                    continue;
                }
                $line = preg_replace("/\s+COMMENT\s+'(?:[^'\\\\]|\\\\.)*'/i", '', $line) ?? $line;
                $line = str_ireplace(['UNSIGNED', 'CHARACTER SET utf8mb4', 'COLLATE utf8mb4_unicode_ci', 'COLLATE utf8mb4_general_ci'], '', $line);
                $line = preg_replace('/\s+ON UPDATE CURRENT_TIMESTAMP/i', '', $line) ?? $line;
                $line = preg_replace('/\bTINYINT\s*\(\s*\d+\s*\)/i', 'INTEGER', $line) ?? $line;
                $line = preg_replace('/\b(?:BIGINT|SMALLINT|MEDIUMINT|INT)\s*\(\s*\d+\s*\)/i', 'INTEGER', $line) ?? $line;
                $line = preg_replace('/\bINT\b/i', 'INTEGER', $line) ?? $line;
                $line = preg_replace('/\bDECIMAL\s*\(\s*\d+\s*,\s*\d+\s*\)/i', 'NUMERIC', $line) ?? $line;
                $line = preg_replace('/\bDOUBLE(\s*\(\s*\d+\s*,\s*\d+\s*\))?/i', 'NUMERIC', $line) ?? $line;
                $line = preg_replace('/\b(?:VARCHAR|CHAR)\s*\(\s*\d+\s*\)/i', 'TEXT', $line) ?? $line;
                $line = preg_replace('/\bENUM\s*\([^)]*\)/i', 'TEXT', $line) ?? $line;
                $line = preg_replace('/\b(?:DATETIME|TIMESTAMP|DATE|TIME|JSON|LONGTEXT|MEDIUMTEXT|TEXT)\b/i', 'TEXT', $line) ?? $line;
                $line = preg_replace('/\bAUTO_INCREMENT\b/i', '', $line) ?? $line;

                // INTEGER ... PRIMARY KEY on a column that was AUTO_INCREMENT → INTEGER PRIMARY KEY AUTOINCREMENT
                if (preg_match('/^\s*`?id`?\s+INTEGER/i', $line) && stripos(substr($statement, $open, 400), 'AUTO_INCREMENT') !== false && stripos($line, 'PRIMARY KEY') !== false) {
                    $line = 'id INTEGER PRIMARY KEY AUTOINCREMENT';
                } else {
                    $line = preg_replace('/\s+/', ' ', $line) ?? $line;
                }
                $lines[] = $line;
            }

            $head = preg_replace('/\s+/', ' ', $head) ?? $head;
            $out[] = $head . " (\n    " . implode(",\n    ", $lines) . "\n)";
        }

        return implode(";\n", $out) . ';';
    }

    /* ---------------------------------------------------------------------
     | Demo dataset
     |---------------------------------------------------------------------*/
    public static function seed(bool $fresh = false): array
    {
        $log = [];
        if (!$fresh && (int) Database::value('SELECT COUNT(*) FROM employees') > 0) {
            return ['Data demo sudah ada - seeding dilewati.'];
        }
        $seed = require BASE_PATH . '/database/seed.php';
        return array_merge($log, $seed());
    }
}
