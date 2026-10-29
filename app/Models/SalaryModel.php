<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Settings;

/**
 * Komponen gaji, penugasan ke karyawan, serta mesin hitung payroll:
 * take-home estimation, BPJS (Kesehatan & Ketenagakerjaan), dan PPh 21.
 *
 * Mesin ini dipakai halaman Komponen Gaji/Pengaturan untuk simulasi dan akan
 * dipakai ulang oleh modul Payroll (Batch 8) agar angkanya konsisten.
 */
final class SalaryModel
{
    /** Komponen bawaan yang tidak boleh dihapus (dipakai payroll). */
    public const LOCKED_CODES = ['BASIC', 'BPJS_KES_EE', 'BPJS_JHT_EE', 'BPJS_JP_EE', 'PPH21'];

    public const CALC_TYPES = [
        'FIXED'   => 'Nominal tetap',
        'PERCENT' => 'Persentase',
        'PRORATE' => 'Prorata hari kerja',
    ];

    public const PERCENT_BASES = [
        'BASIC' => 'Gaji pokok',
        'GROSS' => 'Bruto (pokok + tunjangan)',
    ];

    /* ================================================================ settings */

    public function settings(): array
    {
        return [
            'cutoff_day'   => (int) (Settings::get('payroll_cutoff_day') ?: 25),
            'pay_day'      => (int) (Settings::get('payroll_pay_day') ?: 28),
            'auto_cutoff'  => Settings::bool('payroll_auto_cutoff', true),
            'pph_enabled'  => Settings::bool('payroll_pph_enabled', true),
            'pph_method'   => strtoupper((string) (Settings::get('payroll_pph_method') ?: 'TER')),
            'pph_rate'     => (float) (Settings::get('payroll_pph_rate') ?: 5),
            'pph_round'    => (int) (Settings::get('payroll_pph_round') ?: 100),
            'bpjs_kes_on'  => Settings::bool('bpjs_kesehatan_enabled', true),
            'bpjs_tk_on'   => Settings::bool('bpjs_tk_enabled', true),
            'kes_company'  => (float) (Settings::get('bpjs_kes_company_pct') ?: 4),
            'kes_employee' => (float) (Settings::get('bpjs_kes_employee_pct') ?: 1),
            'kes_max'      => (float) (Settings::get('bpjs_kes_max_wage') ?: 12000000),
            'jht_company'  => (float) (Settings::get('bpjs_jht_company_pct') ?: 3.7),
            'jht_employee' => (float) (Settings::get('bpjs_jht_employee_pct') ?: 2),
            'jp_company'   => (float) (Settings::get('bpjs_jp_company_pct') ?: 2),
            'jp_employee'  => (float) (Settings::get('bpjs_jp_employee_pct') ?: 1),
            'jp_max'       => (float) (Settings::get('bpjs_jp_max_wage') ?: 10000000),
            'jkk'          => (float) (Settings::get('bpjs_jkk_pct') ?: 0.24),
            'jkm'          => (float) (Settings::get('bpjs_jkm_pct') ?: 0.3),
        ];
    }

    /** PTKP setahun menurut status pajak. */
    public function ptkp(string $status): float
    {
        return match (strtoupper(trim($status))) {
            'TK/0'  => 54000000,
            'TK/1'  => 58500000,
            'TK/2'  => 63000000,
            'TK/3'  => 67500000,
            'K/0'   => 58500000,
            'K/1'   => 63000000,
            'K/2'   => 67500000,
            'K/3'   => 72000000,
            default => 54000000,
        };
    }

    /** Lapisan tarif progresif PPh 21 (UU HPP). */
    public function brackets(): array
    {
        return [
            ['limit' => 60000000,  'rate' => 5],
            ['limit' => 250000000, 'rate' => 15],
            ['limit' => 500000000, 'rate' => 25],
            ['limit' => 5000000000, 'rate' => 30],
            ['limit' => PHP_INT_MAX, 'rate' => 35],
        ];
    }

    /* ============================================================== components */

    public function components(bool $activeOnly = false): array
    {
        $sql = "SELECT sc.*,
                       (SELECT COUNT(*) FROM employee_components ec WHERE ec.component_id = sc.id AND ec.is_active = 1) AS assign_count,
                       (SELECT COALESCE(SUM(ec.amount_override), 0) FROM employee_components ec WHERE ec.component_id = sc.id AND ec.is_active = 1) AS override_total
                FROM salary_components sc";
        if ($activeOnly) {
            $sql .= ' WHERE sc.is_active = 1';
        }
        $sql .= ' ORDER BY sc.type DESC, sc.sort_order, sc.id';
        return Database::all($sql);
    }

    public function findComponent(int $id): ?array
    {
        return Database::one('SELECT * FROM salary_components WHERE id = ?', [$id]);
    }

    public function componentCodeExists(string $code, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM salary_components WHERE UPPER(code) = UPPER(?)';
        $params = [$code];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int) Database::value($sql, $params) > 0;
    }

    public function createComponent(array $data): int
    {
        return Database::insert('salary_components', $data + [
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateComponent(int $id, array $data): void
    {
        Database::update('salary_components', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    public function componentUsage(int $id): array
    {
        return [
            'assignments' => (int) Database::value('SELECT COUNT(*) FROM employee_components WHERE component_id = ?', [$id]),
        ];
    }

    public function deleteComponent(int $id): void
    {
        Database::delete('salary_components', 'id = ?', [$id]);
    }

    public function maxSortOrder(): int
    {
        return (int) Database::value('SELECT COALESCE(MAX(sort_order), 0) FROM salary_components');
    }

    /* ============================================================= assignments */

    /** Penugasan komponen beserta data karyawan & komponen. */
    public function assignments(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $params = [];
        $where = $this->assignmentWhere($filters, $params);
        $rows = Database::all(
            "SELECT ec.*, sc.code, sc.name, sc.type, sc.calc_type, sc.amount, sc.percent_base, sc.taxable, sc.bpjs_base,
                    e.full_name, e.employee_code, e.email, e.photo, e.basic_salary,
                    bu.name AS unit_name, d.name AS department_name
             FROM employee_components ec
             JOIN employees e ON e.id = ec.employee_id
             JOIN salary_components sc ON sc.id = ec.component_id
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id"
            . $where
            . ' ORDER BY e.full_name, sc.sort_order LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $params
        );
        return array_map([$this, 'decorateAssignment'], $rows);
    }

    public function countAssignments(array $filters = []): int
    {
        $params = [];
        return (int) Database::value(
            'SELECT COUNT(*) FROM employee_components ec
             JOIN employees e ON e.id = ec.employee_id
             JOIN salary_components sc ON sc.id = ec.component_id'
            . $this->assignmentWhere($filters, $params),
            $params
        );
    }

    private function assignmentWhere(array $filters, array &$params): string
    {
        $clauses = [];

        if (!empty($filters['component'])) {
            $clauses[] = 'ec.component_id = ?';
            $params[] = (int) $filters['component'];
        }
        if (!empty($filters['unit'])) {
            $clauses[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (!empty($filters['department'])) {
            $clauses[] = 'e.department_id = ?';
            $params[] = (int) $filters['department'];
        }
        if (!empty($filters['employee'])) {
            $clauses[] = 'ec.employee_id = ?';
            $params[] = (int) $filters['employee'];
        }
        if (isset($filters['active']) && $filters['active'] !== '' && $filters['active'] !== null) {
            $clauses[] = 'ec.is_active = ?';
            $params[] = (int) $filters['active'];
        }
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . $filters['q'] . '%';
            $clauses[] = '(e.full_name LIKE ? OR e.employee_code LIKE ? OR sc.name LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        return $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';
    }

    private function decorateAssignment(array $row): array
    {
        $row['base_amount'] = $this->componentAmount($row, (float) $row['basic_salary']);
        $row['amount_override'] = $row['amount_override'] !== null ? (float) $row['amount_override'] : null;
        $row['effective_amount'] = $row['amount_override'] ?? $row['base_amount'];
        return $row;
    }

    /** Nilai komponen untuk satu karyawan (override > nominal > persentase). */
    public function componentAmount(array $component, float $basicSalary, float $gross = 0.0): float
    {
        $calc = strtoupper((string) ($component['calc_type'] ?? 'FIXED'));
        $amount = (float) ($component['amount'] ?? 0);

        if ($calc === 'PERCENT') {
            $base = strtoupper((string) ($component['percent_base'] ?? 'BASIC')) === 'GROSS' ? $gross : $basicSalary;
            return round($base * $amount / 100, 2);
        }

        return round($amount, 2);
    }

    /**
     * Tugaskan komponen ke banyak karyawan sekaligus.
     * Satu karyawan hanya punya satu baris aktif per komponen (diperbarui bila ada).
     *
     * @return array{created:int, updated:int, skipped:int, employees:int}
     */
    public function assignComponent(int $componentId, array $employeeIds, ?float $override, ?string $effective, ?string $endDate): array
    {
        $component = $this->findComponent($componentId);
        if (!$component) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'employees' => 0];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');

        foreach (array_unique($employeeIds) as $employeeId) {
            $employeeId = (int) $employeeId;
            if ($employeeId <= 0) {
                continue;
            }
            $exists = (int) Database::value(
                'SELECT id FROM employees WHERE id = ? AND flag = ?',
                [$employeeId, 'ACTIVE']
            );
            if ($exists === 0) {
                $skipped++;
                continue;
            }

            $current = Database::one(
                'SELECT * FROM employee_components WHERE employee_id = ? AND component_id = ? ORDER BY is_active DESC, id DESC LIMIT 1',
                [$employeeId, $componentId]
            );

            if ($current) {
                // Override kosong = pertahankan nilai khusus yang sudah ada,
                // agar penugasan massal tidak menghapus penyesuaian per karyawan.
                Database::update('employee_components', [
                    'amount_override' => $override ?? $current['amount_override'],
                    'effective_date'  => $effective ?: $current['effective_date'],
                    'end_date'        => $endDate ?: $current['end_date'],
                    'is_active'       => 1,
                ], 'id = ?', [(int) $current['id']]);
                $updated++;
                continue;
            }

            Database::insert('employee_components', [
                'employee_id'     => $employeeId,
                'component_id'    => $componentId,
                'amount_override' => $override,
                'effective_date'  => $effective ?: date('Y-m-d'),
                'end_date'        => $endDate,
                'is_active'       => 1,
                'created_at'      => $now,
            ]);
            $created++;
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'employees' => count($employeeIds)];
    }

    /** Terapkan komponen bertanda apply_default ke karyawan yang belum punya. */
    public function applyDefaults(?int $unitId = null): array
    {
        $defaults = Database::all('SELECT id FROM salary_components WHERE apply_default = 1 AND is_active = 1');
        if (!$defaults) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'employees' => 0, 'components' => 0];
        }

        $sql = "SELECT id FROM employees WHERE flag = 'ACTIVE'";
        $params = [];
        if ($unitId) {
            $sql .= ' AND business_unit_id = ?';
            $params[] = $unitId;
        }
        $employees = array_map(static fn ($row) => (int) $row['id'], Database::all($sql, $params));

        $totals = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        foreach ($defaults as $component) {
            $result = $this->assignComponent((int) $component['id'], $employees, null, date('Y-m-d'), null);
            $totals['created'] += $result['created'];
            $totals['updated'] += $result['updated'];
            $totals['skipped'] += $result['skipped'];
        }

        return $totals + ['employees' => count($employees), 'components' => count($defaults)];
    }

    public function updateAssignment(int $id, array $data): array
    {
        $row = Database::one('SELECT * FROM employee_components WHERE id = ?', [$id]);
        if (!$row) {
            return ['error' => 'Penugasan komponen tidak ditemukan.'];
        }

        $override = $data['amount_override'] ?? null;
        if ($override !== null && $override !== '') {
            $override = (float) $override;
            if ($override < 0) {
                return ['error' => 'Nilai override tidak boleh negatif.'];
            }
        } else {
            $override = null;
        }

        Database::update('employee_components', [
            'amount_override' => $override,
            'effective_date'  => ($data['effective_date'] ?? '') ?: $row['effective_date'],
            'end_date'        => ($data['end_date'] ?? '') ?: null,
            'is_active'       => (int) ($data['is_active'] ?? 1),
        ], 'id = ?', [$id]);

        return ['error' => null];
    }

    public function removeAssignment(int $id): array
    {
        $row = Database::one('SELECT * FROM employee_components WHERE id = ?', [$id]);
        if (!$row) {
            return ['error' => 'Penugasan komponen tidak ditemukan.'];
        }
        Database::delete('employee_components', 'id = ?', [$id]);
        return ['error' => null];
    }

    /** Komponen aktif milik satu karyawan pada tanggal tertentu. */
    public function employeeComponents(int $employeeId, ?string $date = null, ?float $basicSalary = null): array
    {
        $date = $date ?: date('Y-m-d');
        $salary = $basicSalary ?? (float) Database::value('SELECT basic_salary FROM employees WHERE id = ?', [$employeeId]);

        $rows = Database::all(
            "SELECT sc.*, ec.amount_override, ec.effective_date, ec.end_date, ec.id AS assignment_id
             FROM employee_components ec
             JOIN salary_components sc ON sc.id = ec.component_id
             WHERE ec.employee_id = ?
               AND ec.is_active = 1
               AND sc.is_active = 1
               AND (ec.effective_date IS NULL OR ec.effective_date <= ?)
               AND (ec.end_date IS NULL OR ec.end_date >= ?)
             ORDER BY sc.sort_order, sc.id",
            [$employeeId, $date, $date]
        );

        $result = [];
        foreach ($rows as $row) {
            $amount = $row['amount_override'] !== null
                ? (float) $row['amount_override']
                : $this->componentAmount($row, $salary);
            $row['basic_salary'] = $salary;
            $row['amount_resolved'] = round($amount, 2);
            $result[] = $row;
        }
        return $result;
    }

    /* ============================================================== calculation */

    /**
     * Hitung rincian gaji satu karyawan (tanpa potongan presensi/lembur — itu
     * ditangani modul Payroll). Semua angka dibulatkan ke rupiah penuh.
     *
     * @return array<string, mixed>
     */
    public function preview(int $employeeId, ?string $date = null): array
    {
        $employee = Database::one(
            "SELECT e.*, bu.name AS unit_name, d.name AS department_name
             FROM employees e
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE e.id = ?",
            [$employeeId]
        );
        if (!$employee) {
            return [];
        }

        $settings = $this->settings();
        $basic = (float) $employee['basic_salary'];
        $components = $this->employeeComponents($employeeId, $date, $basic);

        // Pass 1: komponen berbasis BASIC untuk mendapat bruto sementara.
        $benefits = [];
        $deductions = [];
        $grossBase = $basic;

        foreach ($components as $component) {
            if (strtoupper((string) $component['type']) === 'BENEFIT') {
                $grossBase += $component['amount_resolved'];
            }
        }

        foreach ($components as $component) {
            $type = strtoupper((string) $component['type']);
            $amount = $component['amount_resolved'];

            // Komponen persentase berbasis GROSS dihitung setelah tunjangan diketahui.
            if (strtoupper((string) $component['calc_type']) === 'PERCENT'
                && strtoupper((string) $component['percent_base']) === 'GROSS') {
                $amount = round($grossBase * (float) $component['amount'] / 100, 2);
            }

            $line = [
                'id'       => (int) $component['id'],
                'code'     => (string) $component['code'],
                'name'     => (string) $component['name'],
                'type'     => $type,
                'amount'   => round($amount, 2),
                'taxable'  => (int) $component['taxable'] === 1,
                'bpjs'     => (int) $component['bpjs_base'] === 1,
            ];

            if ($type === 'BENEFIT') {
                $benefits[] = $line;
            } else {
                $deductions[] = $line;
            }
        }

        $benefitTotal = array_sum(array_column($benefits, 'amount'));
        $gross = round($basic + $benefitTotal, 2);

        $bpjs = $this->bpjs($employee, $gross);
        $taxableBase = $basic;
        foreach ($benefits as $benefit) {
            if ($benefit['taxable']) {
                $taxableBase += $benefit['amount'];
            }
        }
        $taxableBase -= $bpjs['employee_total'];

        $pph = $this->pph21($employee, max(0, $taxableBase));
        $otherDeductions = array_sum(array_column($deductions, 'amount'));

        $employeeDeductions = round($bpjs['employee_total'] + ($settings['pph_enabled'] ? $pph['monthly'] : 0) + $otherDeductions, 2);
        $net = round($gross - $employeeDeductions, 2);

        return [
            'employee'  => $employee,
            'date'      => $date ?: date('Y-m-d'),
            'basic'     => $basic,
            'benefits'  => $benefits,
            'deductions' => $deductions,
            'benefit_total'   => $benefitTotal,
            'deduction_total' => $otherDeductions,
            'gross'     => $gross,
            'taxable_base' => round($taxableBase, 2),
            'bpjs'      => $bpjs,
            'pph'       => $pph,
            'employee_deductions' => $employeeDeductions,
            'net'       => $net,
            'employer_cost' => round($gross + $bpjs['company_total'], 2),
            'settings'  => $settings,
        ];
    }

    /**
     * Iuran BPJS karyawan & perusahaan.
     * Dasar: gaji pokok (BPJS tidak memakai tunjangan tidak tetap) dibatasi plafon upah.
     */
    public function bpjs(array $employee, float $gross): array
    {
        $s = $this->settings();
        $basic = (float) $employee['basic_salary'];

        $kesBase = $s['kes_max'] > 0 ? min($basic, $s['kes_max']) : $basic;
        $jpBase = $s['jp_max'] > 0 ? min($basic, $s['jp_max']) : $basic;

        $kesEmployee = $s['bpjs_kes_on'] ? round($kesBase * $s['kes_employee'] / 100, 2) : 0.0;
        $kesCompany = $s['bpjs_kes_on'] ? round($kesBase * $s['kes_company'] / 100, 2) : 0.0;
        $jhtEmployee = $s['bpjs_tk_on'] ? round($basic * $s['jht_employee'] / 100, 2) : 0.0;
        $jhtCompany = $s['bpjs_tk_on'] ? round($basic * $s['jht_company'] / 100, 2) : 0.0;
        $jpEmployee = $s['bpjs_tk_on'] ? round($jpBase * $s['jp_employee'] / 100, 2) : 0.0;
        $jpCompany = $s['bpjs_tk_on'] ? round($jpBase * $s['jp_company'] / 100, 2) : 0.0;
        $jkk = $s['bpjs_tk_on'] ? round($basic * $s['jkk'] / 100, 2) : 0.0;
        $jkm = $s['bpjs_tk_on'] ? round($basic * $s['jkm'] / 100, 2) : 0.0;

        $items = [
            ['code' => 'BPJS_KES_EE', 'label' => 'BPJS Kesehatan (karyawan)', 'rate' => $s['kes_employee'], 'base' => $kesBase, 'amount' => $kesEmployee, 'side' => 'EMPLOYEE'],
            ['code' => 'BPJS_JHT_EE', 'label' => 'JHT (karyawan)', 'rate' => $s['jht_employee'], 'base' => $basic, 'amount' => $jhtEmployee, 'side' => 'EMPLOYEE'],
            ['code' => 'BPJS_JP_EE', 'label' => 'JP (karyawan)', 'rate' => $s['jp_employee'], 'base' => $jpBase, 'amount' => $jpEmployee, 'side' => 'EMPLOYEE'],
            ['code' => 'BPJS_KES_ER', 'label' => 'BPJS Kesehatan (perusahaan)', 'rate' => $s['kes_company'], 'base' => $kesBase, 'amount' => $kesCompany, 'side' => 'COMPANY'],
            ['code' => 'BPJS_JHT_ER', 'label' => 'JHT (perusahaan)', 'rate' => $s['jht_company'], 'base' => $basic, 'amount' => $jhtCompany, 'side' => 'COMPANY'],
            ['code' => 'BPJS_JP_ER', 'label' => 'JP (perusahaan)', 'rate' => $s['jp_company'], 'base' => $jpBase, 'amount' => $jpCompany, 'side' => 'COMPANY'],
            ['code' => 'BPJS_JKK', 'label' => 'JKK (perusahaan)', 'rate' => $s['jkk'], 'base' => $basic, 'amount' => $jkk, 'side' => 'COMPANY'],
            ['code' => 'BPJS_JKM', 'label' => 'JKM (perusahaan)', 'rate' => $s['jkm'], 'base' => $basic, 'amount' => $jkm, 'side' => 'COMPANY'],
        ];

        $employeeTotal = round($kesEmployee + $jhtEmployee + $jpEmployee, 2);
        $companyTotal = round($kesCompany + $jhtCompany + $jpCompany + $jkk + $jkm, 2);

        return [
            'items'          => $items,
            'kes_base'       => round($kesBase, 2),
            'jp_base'        => round($jpBase, 2),
            'employee_total' => $employeeTotal,
            'company_total'  => $companyTotal,
            'total'          => round($employeeTotal + $companyTotal, 2),
            'gross'          => round($gross, 2),
        ];
    }

    /**
     * PPh 21 bulanan.
     * - TER : tarif efektif rata-rata per bracket bruto bulanan (PP 58/2023, disederhanakan).
     * - FLAT: satu persen dari pengaturan (simulasi cepat).
     * - PROGRESSIVE: proyeksi setahun (bruto × 12 − PTKP − biaya jabatan) lalu dibagi 12.
     */
    public function pph21(array $employee, float $taxableBase): array
    {
        $s = $this->settings();
        $status = (string) ($employee['tax_status'] ?? 'TK/0');

        if (!$s['pph_enabled']) {
            return [
                'method' => 'OFF', 'monthly' => 0.0, 'annual_tax' => 0.0,
                'ptkp' => $this->ptkp($status), 'status' => $status,
                'effective_rate' => 0.0, 'pkp' => 0.0, 'brackets' => [], 'note' => 'PPh 21 dinonaktifkan pada pengaturan.',
            ];
        }

        $method = in_array($s['pph_method'], ['TER', 'FLAT', 'PROGRESSIVE'], true) ? $s['pph_method'] : 'TER';
        $ptkp = $this->ptkp($status);
        $round = max(1, $s['pph_round']);
        $bracketsUsed = [];
        $pkp = 0.0;
        $annual = 0.0;

        if ($method === 'FLAT') {
            $tax = round($taxableBase * $s['pph_rate'] / 100 / $round) * $round;
            return [
                'method' => 'FLAT', 'monthly' => $tax, 'annual_tax' => round($tax * 12, 2),
                'ptkp' => $ptkp, 'status' => $status, 'effective_rate' => $s['pph_rate'],
                'pkp' => round($taxableBase, 2), 'brackets' => [], 'note' => 'Tarif flat ' . number_id($s['pph_rate'], 2) . '% dari dasar pengenaan.',
            ];
        }

        if ($method === 'TER') {
            $rate = $this->terRate($taxableBase);
            $tax = round($taxableBase * $rate / 100 / $round) * $round;
            return [
                'method' => 'TER', 'monthly' => $tax, 'annual_tax' => round($tax * 12, 2),
                'ptkp' => $ptkp, 'status' => $status, 'effective_rate' => $rate,
                'pkp' => round($taxableBase, 2), 'brackets' => [], 
                'note' => 'Tarif efektif rata-rata ' . number_id($rate, 2) . '% untuk bruto bulanan ' . rupiah($taxableBase) . '.',
            ];
        }

        // PROGRESSIVE
        $annualGross = $taxableBase * 12;
        $jabatan = min($annualGross * 5 / 100, 6000000);
        $pkp = max(0, $annualGross - $jabatan - $ptkp);
        $remaining = $pkp;
        $previousLimit = 0.0;

        foreach ($this->brackets() as $bracket) {
            if ($remaining <= 0) {
                break;
            }
            $layerSize = $bracket['limit'] - $previousLimit;
            $layer = min($remaining, $layerSize);
            $amount = $layer * $bracket['rate'] / 100;
            $bracketsUsed[] = [
                'range'  => rupiah($previousLimit) . ' – ' . ($bracket['limit'] === PHP_INT_MAX ? 'dst' : rupiah($bracket['limit'])),
                'rate'   => $bracket['rate'],
                'base'   => round($layer, 2),
                'tax'    => round($amount, 2),
            ];
            $annual += $amount;
            $remaining -= $layer;
            $previousLimit = $bracket['limit'];
        }

        $monthly = $pkp > 0 ? round(($annual / 12) / $round) * $round : 0.0;

        return [
            'method' => 'PROGRESSIVE', 'monthly' => $monthly, 'annual_tax' => round($annual, 2),
            'ptkp' => $ptkp, 'status' => $status,
            'effective_rate' => $annualGross > 0 ? round($annual / $annualGross * 100, 2) : 0.0,
            'pkp' => round($pkp, 2), 'annual_gross' => round($annualGross, 2),
            'jabatan' => round($jabatan, 2), 'brackets' => $bracketsUsed,
            'note' => 'Proyeksi setahun: bruto × 12 − biaya jabatan − PTKP ' . $status . '.',
        ];
    }

    /** Tarif efektif rata-rata (TER) sederhana untuk bruto bulanan. */
    public function terRate(float $monthlyGross): float
    {
        return match (true) {
            $monthlyGross <= 5400000  => 0.0,
            $monthlyGross <= 5650000  => 0.25,
            $monthlyGross <= 5950000  => 0.5,
            $monthlyGross <= 6300000  => 0.75,
            $monthlyGross <= 6750000  => 1.0,
            $monthlyGross <= 7500000  => 1.25,
            $monthlyGross <= 8550000  => 1.5,
            $monthlyGross <= 9650000  => 1.75,
            $monthlyGross <= 10050000 => 2.0,
            $monthlyGross <= 10350000 => 2.25,
            $monthlyGross <= 10700000 => 2.5,
            $monthlyGross <= 11050000 => 3.0,
            $monthlyGross <= 11600000 => 3.5,
            $monthlyGross <= 12500000 => 4.0,
            $monthlyGross <= 13750000 => 5.0,
            $monthlyGross <= 15100000 => 6.0,
            $monthlyGross <= 16950000 => 7.0,
            $monthlyGross <= 19750000 => 8.0,
            $monthlyGross <= 24150000 => 9.0,
            $monthlyGross <= 26450000 => 10.0,
            $monthlyGross <= 28000000 => 11.0,
            $monthlyGross <= 30050000 => 12.0,
            $monthlyGross <= 32400000 => 13.0,
            $monthlyGross <= 35400000 => 14.0,
            $monthlyGross <= 39100000 => 15.0,
            $monthlyGross <= 43850000 => 16.0,
            $monthlyGross <= 47800000 => 17.0,
            $monthlyGross <= 51400000 => 18.0,
            $monthlyGross <= 56300000 => 19.0,
            $monthlyGross <= 62200000 => 20.0,
            default => 21.0,
        };
    }

    /* ================================================================= stats */

    public function stats(): array
    {
        $row = Database::one(
            "SELECT COUNT(*) AS components,
                    COALESCE(SUM(CASE WHEN type = 'BENEFIT' THEN 1 ELSE 0 END), 0) AS benefits,
                    COALESCE(SUM(CASE WHEN type = 'DEDUCTION' THEN 1 ELSE 0 END), 0) AS deductions,
                    COALESCE(SUM(CASE WHEN apply_default = 1 THEN 1 ELSE 0 END), 0) AS defaults
             FROM salary_components WHERE is_active = 1"
        ) ?? [];

        $assignments = (int) Database::value('SELECT COUNT(*) FROM employee_components WHERE is_active = 1');
        $employeesWith = (int) Database::value('SELECT COUNT(DISTINCT employee_id) FROM employee_components WHERE is_active = 1');
        $employees = (int) Database::value("SELECT COUNT(*) FROM employees WHERE flag = 'ACTIVE'");

        return [
            'components'   => (int) ($row['components'] ?? 0),
            'benefits'     => (int) ($row['benefits'] ?? 0),
            'deductions'   => (int) ($row['deductions'] ?? 0),
            'defaults'     => (int) ($row['defaults'] ?? 0),
            'assignments'  => $assignments,
            'employees_with' => $employeesWith,
            'employees'    => $employees,
            'coverage'     => $employees > 0 ? round($employeesWith / $employees * 100, 1) : 0.0,
        ];
    }

    /** Rata-rata komponen per unit untuk grafik cakupan. */
    public function coverageByUnit(): array
    {
        return Database::all(
            "SELECT bu.id, bu.name, bu.code,
                    COUNT(DISTINCT e.id) AS headcount,
                    COUNT(DISTINCT CASE WHEN ec.is_active = 1 THEN e.id END) AS covered
             FROM business_units bu
             LEFT JOIN employees e ON e.business_unit_id = bu.id AND e.flag = 'ACTIVE'
             LEFT JOIN employee_components ec ON ec.employee_id = e.id
             GROUP BY bu.id, bu.name, bu.code
             ORDER BY bu.id"
        );
    }

    /** Karyawan aktif untuk pratinjau massal (simulasi cepat). */
    public function simulationSample(int $limit = 12): array
    {
        $rows = Database::all(
            "SELECT e.id, e.full_name, e.employee_code, e.basic_salary, e.tax_status, e.email,
                    bu.name AS unit_name
             FROM employees e
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             WHERE e.flag = 'ACTIVE'
             ORDER BY e.basic_salary DESC LIMIT " . max(1, $limit)
        );

        foreach ($rows as &$row) {
            $preview = $this->preview((int) $row['id']);
            $row['gross'] = $preview['gross'] ?? 0;
            $row['bpjs'] = $preview['bpjs']['employee_total'] ?? 0;
            $row['pph'] = $preview['pph']['monthly'] ?? 0;
            $row['net'] = $preview['net'] ?? 0;
            $row['employer_cost'] = $preview['employer_cost'] ?? 0;
        }
        unset($row);

        return $rows;
    }

    /* ============================================================== settings io */

    /**
     * Simpan pengaturan payroll/PPh/BPJS dengan validasi.
     * @return array{errors: array<string,string>, saved: bool}
     */
    public function savePayrollSettings(array $input): array
    {
        $errors = [];

        $cutoff = (int) ($input['cutoff_day'] ?? 0);
        $payDay = (int) ($input['pay_day'] ?? 0);
        $rate = (float) ($input['pph_rate'] ?? 0);
        $method = strtoupper((string) ($input['pph_method'] ?? 'TER'));

        if ($cutoff < 1 || $cutoff > 31) {
            $errors['cutoff_day'] = 'Tanggal cutoff harus antara 1 dan 31.';
        }
        if ($payDay < 1 || $payDay > 31) {
            $errors['pay_day'] = 'Tanggal pembayaran harus antara 1 dan 31.';
        }
        if (!in_array($method, ['TER', 'FLAT', 'PROGRESSIVE'], true)) {
            $errors['pph_method'] = 'Metode PPh 21 tidak dikenali.';
        }
        if ($rate < 0 || $rate > 35) {
            $errors['pph_rate'] = 'Tarif PPh harus antara 0 dan 35 persen.';
        }

        $pct = static function (string $key, float $min, float $max) use ($input, &$errors): ?float {
            $value = (float) ($input[$key] ?? 0);
            if ($value < $min || $value > $max) {
                $errors[$key] = 'Nilai persentase harus antara ' . $min . '% dan ' . $max . '%.';
                return null;
            }
            return $value;
        };

        $kesCompany = $pct('kes_company', 0, 10);
        $kesEmployee = $pct('kes_employee', 0, 5);
        $jhtCompany = $pct('jht_company', 0, 10);
        $jhtEmployee = $pct('jht_employee', 0, 10);
        $jpCompany = $pct('jp_company', 0, 10);
        $jpEmployee = $pct('jp_employee', 0, 5);
        $jkk = $pct('jkk', 0, 5);
        $jkm = $pct('jkm', 0, 5);

        $kesMax = (float) ($input['kes_max'] ?? 0);
        $jpMax = (float) ($input['jp_max'] ?? 0);
        if ($kesMax < 0 || $kesMax > 100000000) {
            $errors['kes_max'] = 'Plafon upah BPJS Kesehatan tidak valid.';
        }
        if ($jpMax < 0 || $jpMax > 100000000) {
            $errors['jp_max'] = 'Plafon upah JP tidak valid.';
        }

        if ($errors) {
            return ['errors' => $errors, 'saved' => false];
        }

        Settings::many([
            'payroll_cutoff_day'       => $cutoff,
            'payroll_pay_day'          => $payDay,
            'payroll_auto_cutoff'      => (int) ($input['auto_cutoff'] ?? 0),
            'payroll_pph_enabled'      => (int) ($input['pph_enabled'] ?? 0),
            'payroll_pph_method'       => $method,
            'payroll_pph_rate'         => $this->trim($rate),
            'bpjs_kesehatan_enabled'   => (int) ($input['kes_enabled'] ?? 0),
            'bpjs_tk_enabled'          => (int) ($input['tk_enabled'] ?? 0),
            'bpjs_kes_company_pct'     => $this->trim((float) $kesCompany),
            'bpjs_kes_employee_pct'    => $this->trim((float) $kesEmployee),
            'bpjs_kes_max_wage'        => $this->trim($kesMax),
            'bpjs_jht_company_pct'     => $this->trim((float) $jhtCompany),
            'bpjs_jht_employee_pct'    => $this->trim((float) $jhtEmployee),
            'bpjs_jp_company_pct'      => $this->trim((float) $jpCompany),
            'bpjs_jp_employee_pct'     => $this->trim((float) $jpEmployee),
            'bpjs_jp_max_wage'         => $this->trim($jpMax),
            'bpjs_jkk_pct'             => $this->trim((float) $jkk),
            'bpjs_jkm_pct'             => $this->trim((float) $jkm),
        ], 'payroll');

        return ['errors' => [], 'saved' => true];
    }

    private function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    /* ================================================================= helpers */

    public function componentOptions(bool $activeOnly = true): array
    {
        return $this->components($activeOnly);
    }

    public function employeeOptions(?int $unitId = null, ?int $departmentId = null): array
    {
        $sql = "SELECT e.id, e.full_name, e.employee_code, e.email, e.basic_salary, e.tax_status,
                       bu.name AS unit_name, d.name AS department_name
                FROM employees e
                LEFT JOIN business_units bu ON bu.id = e.business_unit_id
                LEFT JOIN departments d ON d.id = e.department_id
                WHERE e.flag = 'ACTIVE'";
        $params = [];
        if ($unitId) {
            $sql .= ' AND e.business_unit_id = ?';
            $params[] = $unitId;
        }
        if ($departmentId) {
            $sql .= ' AND e.department_id = ?';
            $params[] = $departmentId;
        }
        $sql .= ' ORDER BY e.full_name';
        return Database::all($sql, $params);
    }
}
