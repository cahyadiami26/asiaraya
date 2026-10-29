<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Models\SalaryModel;

/**
 * Rekap PPh 21 tahunan & bukti potong 1721-A1 (Batch 20).
 *
 * Sumber data adalah `payroll_item_components` + `payroll_items` pada periode
 * payroll yang sudah final (disetujui Finance atau dibayar). Perhitungan PPh
 * setahun memakai PTKP, biaya jabatan 5% (maks Rp 6 juta), iuran pensiun/JHT
 * karyawan sebagai pengurang, lalu lapisan progresif UU HPP — persis seperti
 * kalkulasi payroll sehingga angka rekap dan slip selalu konsisten.
 */
final class TaxModel
{
    /** Status periode payroll yang dianggap final untuk pelaporan pajak. */
    public const FINAL_STATUSES = ['FINANCE_APPROVED', 'PAID'];

    /** Status bukti potong. */
    public const SLIP_ISSUED = 'ISSUED';

    private SalaryModel $salary;

    public function __construct()
    {
        $this->salary = new SalaryModel();
    }

    /* ================================================================ filter */

    /** @return array{year:int,unit:int,q:string} */
    public function filtersFromRequest(): array
    {
        // Filter dibaca dari GET (halaman) maupun POST (aksi massal), supaya
        // "Terbitkan Semua" menghormati unit/pencarian yang sedang aktif.
        $year = (int) ($_POST['year'] ?? $_GET['year'] ?? 0);
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }

        return [
            'year' => $year,
            'unit' => (int) ($_POST['unit'] ?? $_GET['unit'] ?? 0),
            'q'    => trim((string) ($_POST['q'] ?? $_GET['q'] ?? '')),
        ];
    }

    /** @return array<int,int> */
    public function years(): array
    {
        $rows = Database::all(
            "SELECT DISTINCT period_year AS y FROM payroll_periods
              WHERE status IN ('FINANCE_APPROVED', 'PAID') ORDER BY y DESC"
        );
        $years = array_map(static fn (array $row): int => (int) $row['y'], $rows);
        $current = (int) date('Y');
        if (!in_array($current, $years, true)) {
            array_unshift($years, $current);
        }
        if (!in_array($current - 1, $years, true)) {
            $years[] = $current - 1;
        }
        $years = array_values(array_unique($years));
        rsort($years);

        return $years;
    }

    /** @return array<int,array<string,mixed>> */
    public function units(): array
    {
        return Database::all('SELECT id, name, code FROM business_units ORDER BY name');
    }

    /* ================================================================== data */

    /**
     * Baris rekap per karyawan untuk satu tahun pajak.
     *
     * @return array<int,array<string,mixed>>
     */
    public function rows(array $filters): array
    {
        $placeholders = implode(',', array_fill(0, count(self::FINAL_STATUSES), '?'));
        $params = array_merge(self::FINAL_STATUSES, [(int) $filters['year']]);
        $where = '';

        if (($filters['unit'] ?? 0) > 0) {
            $where .= ' AND e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where .= ' AND (e.full_name LIKE ? OR e.employee_code LIKE ? OR e.npwp LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }

        $rows = Database::all(
            "SELECT pi.employee_id,
                    MAX(pi.employee_name)    AS employee_name,
                    MAX(pi.employee_code)    AS employee_code,
                    MAX(pi.position_name)    AS position_name,
                    MAX(pi.department_name)  AS department_name,
                    MAX(e.npwp)              AS npwp,
                    MAX(e.tax_status)        AS tax_status,
                    MAX(bu.name)             AS unit_name,
                    MAX(ts.slip_number)      AS slip_number,
                    COUNT(DISTINCT pi.period_id) AS months,
                    SUM(pi.basic_salary)     AS basic_total,
                    SUM(COALESCE(tc.taxable_benefit, 0)) AS benefit_total,
                    SUM(COALESCE(tc.pension, 0))         AS pension_total,
                    SUM(COALESCE(tc.claim, 0))           AS claim_total,
                    SUM(pi.gross_salary)     AS gross_total,
                    SUM(pi.pph_amount)       AS withheld
               FROM payroll_items pi
               JOIN payroll_periods p ON p.id = pi.period_id
               LEFT JOIN employees e ON e.id = pi.employee_id
               LEFT JOIN business_units bu ON bu.id = e.business_unit_id
               LEFT JOIN tax_slips ts ON ts.employee_id = pi.employee_id AND ts.tax_year = p.period_year
               LEFT JOIN (
                    SELECT item_id,
                           SUM(CASE WHEN type = 'BENEFIT' AND kind <> 'CLAIM' THEN amount ELSE 0 END) AS taxable_benefit,
                           SUM(CASE WHEN type = 'DEDUCTION' AND code IN ('BPJS_JHT_EE', 'BPJS_JP_EE') THEN amount ELSE 0 END) AS pension,
                           SUM(CASE WHEN kind = 'CLAIM' THEN amount ELSE 0 END) AS claim
                      FROM payroll_item_components
                     GROUP BY item_id
               ) tc ON tc.item_id = pi.id
              WHERE p.status IN ({$placeholders}) AND p.period_year = ?{$where}
              GROUP BY pi.employee_id
              ORDER BY MAX(pi.employee_name)",
            $params
        );

        return array_map(fn (array $row): array => $this->compute($row), $rows);
    }

    /**
     * Hitung PPh 21 setahun untuk satu baris agregat.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public function compute(array $row): array
    {
        $status = (string) ($row['tax_status'] ?? 'TK/0');
        $gross = round((float) ($row['basic_total'] ?? 0) + (float) ($row['benefit_total'] ?? 0), 2);
        $biaya = $this->salary->biayaJabatan($gross);
        $pension = round((float) ($row['pension_total'] ?? 0), 2);
        $netto = round(max(0, $gross - $biaya - $pension), 2);
        $ptkp = $this->salary->ptkp($status);
        $pkp = (float) (floor(max(0, $netto - $ptkp) / 1000) * 1000);
        $progressive = $this->salary->progressiveTax($pkp);
        $taxDue = round((float) $progressive['tax']);
        $withheld = round((float) ($row['withheld'] ?? 0));
        $diff = round($taxDue - $withheld);

        return $row + [
            'tax_status'   => $status,
            'gross'        => $gross,
            'biaya_jabatan' => $biaya,
            'pension'      => $pension,
            'netto'        => $netto,
            'ptkp'         => $ptkp,
            'pkp'          => $pkp,
            'tax_due'      => $taxDue,
            'tax_withheld' => $withheld,
            'tax_diff'     => $diff,
            'tax_state'    => $diff > 0 ? 'LESS' : ($diff < 0 ? 'OVER' : 'SAME'),
            'layers'       => $progressive['layers'],
            'months'       => (int) ($row['months'] ?? 0),
            // PPh 21 baru final setelah 12 masa; sebelum itu selisih masih
            // berjalan dan akan disesuaikan pada masa Desember.
            'tax_final'    => (int) ($row['months'] ?? 0) >= 12,
        ];
    }

    /** @return array<string,mixed> */
    public function summary(array $rows): array
    {
        $sum = static fn (string $key): float => round(array_sum(array_map(
            static fn (array $row): float => (float) ($row[$key] ?? 0),
            $rows
        )), 2);

        $final = $rows !== [] && array_reduce(
            $rows,
            static fn (bool $carry, array $row): bool => $carry && (bool) ($row['tax_final'] ?? false),
            true
        );

        return [
            'final'        => $final,
            'employees'    => count($rows),
            'gross'        => $sum('gross'),
            'netto'        => $sum('netto'),
            'pkp'          => $sum('pkp'),
            'tax_due'      => $sum('tax_due'),
            'tax_withheld' => $sum('tax_withheld'),
            'tax_diff'     => $sum('tax_diff'),
            'less'         => count(array_filter($rows, static fn (array $row): bool => ($row['tax_state'] ?? '') === 'LESS')),
            'over'         => count(array_filter($rows, static fn (array $row): bool => ($row['tax_state'] ?? '') === 'OVER')),
        ];
    }

    /**
     * Rekap per masa pajak (bulan) — dipakai untuk SPT Masa PPh 21.
     *
     * @return array<int,array<string,mixed>>
     */
    public function monthly(array $filters): array
    {
        $placeholders = implode(',', array_fill(0, count(self::FINAL_STATUSES), '?'));
        $params = array_merge(self::FINAL_STATUSES, [(int) $filters['year']]);
        $where = '';
        if (($filters['unit'] ?? 0) > 0) {
            $where = ' AND p.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }

        $rows = Database::all(
            "SELECT p.period_month AS month,
                    COUNT(DISTINCT pi.employee_id) AS employees,
                    COALESCE(SUM(pi.basic_salary), 0) AS basic,
                    COALESCE(SUM(pi.gross_salary), 0) AS gross,
                    COALESCE(SUM(pi.pph_amount), 0) AS pph
               FROM payroll_items pi
               JOIN payroll_periods p ON p.id = pi.period_id
              WHERE p.status IN ({$placeholders}) AND p.period_year = ?{$where}
              GROUP BY p.period_month
              ORDER BY p.period_month",
            $params
        );

        return array_map(static fn (array $row): array => [
            'month'     => (int) $row['month'],
            'label'     => bulan_id((int) $row['month']),
            'employees' => (int) $row['employees'],
            'basic'     => (float) $row['basic'],
            'gross'     => (float) $row['gross'],
            'pph'       => (float) $row['pph'],
        ], $rows);
    }

    /** Sebaran karyawan per status PTKP. @return array<int,array<string,mixed>> */
    public function byPtkp(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $status = (string) ($row['tax_status'] ?? 'TK/0');
            $groups[$status] = $groups[$status] ?? ['status' => $status, 'employees' => 0, 'ptkp' => 0.0, 'tax_due' => 0.0];
            $groups[$status]['employees']++;
            $groups[$status]['ptkp'] += (float) $row['ptkp'];
            $groups[$status]['tax_due'] += (float) $row['tax_due'];
        }
        ksort($groups);

        return array_values($groups);
    }

    /* ============================================================= per karyawan */

    /**
     * Rincian satu karyawan: identitas, agregat setahun, komponen, dan daftar
     * slip bulanan yang dipakai.
     *
     * @return array<string,mixed>|null
     */
    public function employeeDetail(int $employeeId, int $year): ?array
    {
        $employee = Database::one(
            'SELECT e.*, bu.name AS unit_name, d.name AS department_name, p.name AS position_name
               FROM employees e
               LEFT JOIN business_units bu ON bu.id = e.business_unit_id
               LEFT JOIN departments d ON d.id = e.department_id
               LEFT JOIN positions p ON p.id = e.position_id
              WHERE e.id = ?',
            [$employeeId]
        );
        if (!$employee) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count(self::FINAL_STATUSES), '?'));

        $aggregate = Database::one(
            "SELECT COUNT(DISTINCT pi.period_id)       AS months,
                    COALESCE(SUM(pi.basic_salary), 0)  AS basic_total,
                    COALESCE(SUM(pi.gross_salary), 0)  AS gross_total,
                    COALESCE(SUM(pi.pph_amount), 0)    AS withheld,
                    COALESCE(SUM(pi.bpjs_kesehatan_employee), 0) AS kes_employee,
                    COALESCE(SUM(pi.bpjs_tk_employee), 0)        AS tk_employee
               FROM payroll_items pi
               JOIN payroll_periods p ON p.id = pi.period_id
              WHERE pi.employee_id = ? AND p.period_year = ? AND p.status IN ({$placeholders})",
            array_merge([$employeeId, $year], self::FINAL_STATUSES)
        ) ?? [];

        $components = Database::all(
            "SELECT pc.code, pc.kind, SUM(pc.amount) AS amount
               FROM payroll_item_components pc
               JOIN payroll_items pi ON pi.id = pc.item_id
               JOIN payroll_periods p ON p.id = pi.period_id
              WHERE pi.employee_id = ? AND p.period_year = ? AND p.status IN ({$placeholders})
                AND pc.type = 'BENEFIT'
              GROUP BY pc.code, pc.kind
              ORDER BY pc.kind, pc.code",
            array_merge([$employeeId, $year], self::FINAL_STATUSES)
        );

        $benefitTaxable = 0.0;
        $claim = 0.0;
        $breakdown = ['tunjangan' => 0.0, 'lembur' => 0.0, 'thr' => 0.0, 'lain' => 0.0];
        foreach ($components as $component) {
            $amount = (float) $component['amount'];
            $kind = strtoupper((string) $component['kind']);
            $code = strtoupper((string) $component['code']);
            if ($kind === 'CLAIM') {
                $claim += $amount;
                continue;
            }
            $benefitTaxable += $amount;
            if ($kind === 'OVERTIME') {
                $breakdown['lembur'] += $amount;
            } elseif ($code === 'THR' || str_contains($code, 'THR')) {
                $breakdown['thr'] += $amount;
            } elseif ($kind === 'COMPONENT') {
                $breakdown['tunjangan'] += $amount;
            } else {
                $breakdown['lain'] += $amount;
            }
        }

        $pension = (float) Database::value(
            "SELECT COALESCE(SUM(pc.amount), 0)
               FROM payroll_item_components pc
               JOIN payroll_items pi ON pi.id = pc.item_id
               JOIN payroll_periods p ON p.id = pi.period_id
              WHERE pi.employee_id = ? AND p.period_year = ? AND p.status IN ({$placeholders})
                AND pc.type = 'DEDUCTION' AND pc.code IN ('BPJS_JHT_EE', 'BPJS_JP_EE')",
            array_merge([$employeeId, $year], self::FINAL_STATUSES),
            0
        );

        $months = Database::all(
            "SELECT p.period_month AS month, p.period_year AS year,
                    pi.basic_salary, pi.benefit_total, pi.overtime_amount,
                    pi.gross_salary, pi.pph_amount, pi.net_salary
               FROM payroll_items pi
               JOIN payroll_periods p ON p.id = pi.period_id
              WHERE pi.employee_id = ? AND p.period_year = ? AND p.status IN ({$placeholders})
              ORDER BY p.period_month",
            array_merge([$employeeId, $year], self::FINAL_STATUSES)
        );

        $row = [
            'employee_id'   => $employeeId,
            'employee_name' => (string) $employee['full_name'],
            'employee_code' => (string) $employee['employee_code'],
            'npwp'          => (string) ($employee['npwp'] ?? ''),
            'tax_status'    => (string) ($employee['tax_status'] ?? 'TK/0'),
            'position_name' => (string) ($employee['position_name'] ?? ''),
            'department_name' => (string) ($employee['department_name'] ?? ''),
            'unit_name'     => (string) ($employee['unit_name'] ?? ''),
            'months'        => (int) ($aggregate['months'] ?? 0),
            'basic_total'   => (float) ($aggregate['basic_total'] ?? 0),
            'benefit_total' => $benefitTaxable,
            'pension_total' => $pension,
            'claim_total'   => $claim,
            'withheld'      => (float) ($aggregate['withheld'] ?? 0),
            'gross_total'   => (float) ($aggregate['gross_total'] ?? 0),
        ];

        $computed = $this->compute($row);

        $slip = $this->slip($employeeId, $year);

        return [
            'employee'   => $employee,
            'computed'   => $computed,
            'breakdown'  => $breakdown,
            'months'     => $months,
            'slip'       => $slip,
            'published'  => $slip !== null,
            'snapshot'   => $this->snapshotPayload($computed, $breakdown),
        ];
    }

    /**
     * Payload yang disimpan saat bukti potong diterbitkan.
     *
     * @return array<string,mixed>
     */
    private function snapshotPayload(array $computed, array $breakdown): array
    {
        return [
            'gross'        => (float) $computed['gross'],
            'biaya_jabatan' => (float) $computed['biaya_jabatan'],
            'pension'      => (float) $computed['pension'],
            'netto'        => (float) $computed['netto'],
            'ptkp'         => (float) $computed['ptkp'],
            'pkp'          => (float) $computed['pkp'],
            'tax_due'      => (float) $computed['tax_due'],
            'tax_withheld' => (float) $computed['tax_withheld'],
            'tax_diff'     => (float) $computed['tax_diff'],
            'months'       => (int) $computed['months'],
            'tax_status'   => (string) $computed['tax_status'],
            'breakdown'    => array_map(static fn ($value): float => round((float) $value, 2), $breakdown),
        ];
    }

    /* ============================================================ bukti potong */

    public function slip(int $employeeId, int $year): ?array
    {
        if (!Database::tableExists('tax_slips')) {
            return null;
        }

        return Database::one(
            'SELECT * FROM tax_slips WHERE employee_id = ? AND tax_year = ?',
            [$employeeId, $year]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function issuedSlips(int $year): array
    {
        if (!Database::tableExists('tax_slips')) {
            return [];
        }

        return Database::all(
            'SELECT * FROM tax_slips WHERE tax_year = ? ORDER BY slip_number',
            [$year]
        );
    }

    public function issuedCount(int $year): int
    {
        if (!Database::tableExists('tax_slips')) {
            return 0;
        }

        return (int) Database::value('SELECT COUNT(*) FROM tax_slips WHERE tax_year = ?', [$year], 0);
    }

    /** Nomor bukti potong berikutnya: 1.1-{tahun}-{urut}. */
    public function nextNumber(int $year): string
    {
        $count = $this->issuedCount($year);

        return sprintf('1.1-%d-%04d', $year, $count + 1);
    }

    /**
     * Terbitkan (atau perbarui) bukti potong 1721-A1.
     *
     * @param array<string,mixed> $detail hasil employeeDetail()
     */
    public function issue(array $detail, int $year, int $by, string $note = ''): int
    {
        $employee = $detail['employee'];
        $computed = $detail['computed'];
        $existing = $this->slip((int) $employee['id'], $year);
        $now = date('Y-m-d H:i:s');

        $payload = [
            'employee_id'  => (int) $employee['id'],
            'tax_year'     => $year,
            'slip_number'  => $existing['slip_number'] ?? $this->nextNumber($year),
            'months_count' => (int) $computed['months'],
            'gross'        => (float) $computed['gross'],
            'biaya_jabatan' => (float) $computed['biaya_jabatan'],
            'iuran_pensiun' => (float) $computed['pension'],
            'netto'        => (float) $computed['netto'],
            'ptkp'         => (float) $computed['ptkp'],
            'pkp'          => (float) $computed['pkp'],
            'tax_due'      => (float) $computed['tax_due'],
            'tax_withheld' => (float) $computed['tax_withheld'],
            'tax_diff'     => (float) $computed['tax_diff'],
            'tax_status'   => (string) $computed['tax_status'],
            'npwp'         => (string) ($employee['npwp'] ?? ''),
            'employee_name' => (string) $employee['full_name'],
            'position_name' => (string) ($computed['position_name'] ?? ''),
            'unit_name'    => (string) ($computed['unit_name'] ?? ''),
            'detail_snapshot' => json_encode($detail['snapshot'], JSON_UNESCAPED_UNICODE),
            'note'         => $note !== '' ? mb_substr($note, 0, 255) : ($existing['note'] ?? null),
            'issued_by'    => $by,
            'issued_at'    => $now,
            'updated_at'   => $now,
        ];

        if ($existing) {
            Database::update('tax_slips', $payload, 'id = ?', [(int) $existing['id']]);
            return (int) $existing['id'];
        }

        return Database::insert('tax_slips', $payload + ['created_at' => $now]);
    }

    public function revoke(int $employeeId, int $year): bool
    {
        if (!Database::tableExists('tax_slips')) {
            return false;
        }

        return Database::delete('tax_slips', 'employee_id = ? AND tax_year = ?', [$employeeId, $year]) > 0;
    }

    /**
     * Terbitkan sekaligus untuk semua karyawan pada filter (yang punya data).
     *
     * @return array{issued:int,skipped:int}
     */
    public function issueAll(array $filters, int $by): array
    {
        $issued = 0;
        $skipped = 0;
        foreach ($this->rows($filters) as $row) {
            $detail = $this->employeeDetail((int) $row['employee_id'], (int) $filters['year']);
            if (!$detail || $detail['computed']['months'] === 0) {
                $skipped++;
                continue;
            }
            $this->issue($detail, (int) $filters['year'], $by, 'Diterbitkan sekaligus dari rekap tahunan.');
            $issued++;
        }

        return ['issued' => $issued, 'skipped' => $skipped];
    }
}
