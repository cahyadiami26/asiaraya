<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Settings;

/**
 * Payroll: periode per unit, kalkulasi slip dari presensi + lembur + komponen
 * gaji + BPJS + PPh 21, rekap, dan alur DRAFT → CALCULATED → SENT_TO_FINANCE
 * → FINANCE_APPROVED → PAID.
 */
final class PayrollModel
{
    /** Hari kerja acuan untuk upah harian (prorata). */
    public const WORK_DAYS = 22;

    public const STATUSES = [
        'DRAFT'            => 'Draft',
        'CALCULATED'       => 'Terhitung',
        'SENT_TO_FINANCE'  => 'Di Finance',
        'FINANCE_APPROVED' => 'Disetujui Finance',
        'PAID'             => 'Lunas',
    ];

    private SalaryModel $salary;
    private AttendanceModel $attendance;
    private ExpenseModel $expenses;

    public function __construct()
    {
        $this->salary = new SalaryModel();
        $this->attendance = new AttendanceModel();
        $this->expenses = new ExpenseModel();
    }

    /* ================================================================ periode */

    public function periods(array $filters = [], int $limit = 12, int $offset = 0): array
    {
        [$where, $params] = $this->periodWhere($filters);

        return Database::all(
            "SELECT p.*, bu.name AS unit_name, bu.code AS unit_code,
                    e.full_name AS created_by_name,
                    s.full_name AS sent_by_name,
                    f.full_name AS finance_by_name,
                    k.full_name AS paid_by_name
             FROM payroll_periods p
             LEFT JOIN business_units bu ON bu.id = p.business_unit_id
             LEFT JOIN employees e ON e.id = p.created_by
             LEFT JOIN employees s ON s.id = p.sent_by
             LEFT JOIN employees f ON f.id = p.finance_approved_by
             LEFT JOIN employees k ON k.id = p.paid_by"
            . $where
            . ' ORDER BY p.period_year DESC, p.period_month DESC, bu.name, p.id DESC'
            . ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $params
        );
    }

    public function countPeriods(array $filters = []): int
    {
        [$where, $params] = $this->periodWhere($filters);
        return (int) Database::value(
            'SELECT COUNT(*) FROM payroll_periods p LEFT JOIN business_units bu ON bu.id = p.business_unit_id' . $where,
            $params
        );
    }

    private function periodWhere(array $filters): array
    {
        $clauses = [];
        $params = [];

        if (!empty($filters['unit'])) {
            $clauses[] = 'p.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (!empty($filters['year'])) {
            $clauses[] = 'p.period_year = ?';
            $params[] = (int) $filters['year'];
        }
        if (!empty($filters['status'])) {
            $clauses[] = 'p.status = ?';
            $params[] = (string) $filters['status'];
        }
        if (($filters['q'] ?? '') !== '') {
            $clauses[] = '(bu.name LIKE ? OR p.notes LIKE ?)';
            $like = Database::like((string) $filters['q']);
            array_push($params, $like, $like);
        }

        return [$clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    public function find(int $id): ?array
    {
        return Database::one(
            "SELECT p.*, bu.name AS unit_name, bu.code AS unit_code,
                    e.full_name AS created_by_name,
                    pr.full_name AS processed_by_name,
                    s.full_name AS sent_by_name,
                    f.full_name AS finance_by_name,
                    k.full_name AS paid_by_name
             FROM payroll_periods p
             LEFT JOIN business_units bu ON bu.id = p.business_unit_id
             LEFT JOIN employees e ON e.id = p.created_by
             LEFT JOIN employees pr ON pr.id = p.processed_by
             LEFT JOIN employees s ON s.id = p.sent_by
             LEFT JOIN employees f ON f.id = p.finance_approved_by
             LEFT JOIN employees k ON k.id = p.paid_by
             WHERE p.id = ?",
            [$id]
        );
    }

    public function findItem(int $id): ?array
    {
        return Database::one(
            "SELECT pi.*, p.period_year, p.period_month, p.start_date, p.end_date, p.pay_date,
                    p.status AS period_status, p.business_unit_id,
                    p.hr_signature_name, p.fin_signature_name, p.paid_at, p.cutoff_date,
                    bu.name AS unit_name, bu.code AS unit_code,
                    e.photo, e.email, e.bank_name, e.bank_account_number, e.bank_account_name,
                    e.npwp, e.bpjs_kesehatan_no, e.bpjs_tk_no, e.tax_status, e.join_date
             FROM payroll_items pi
             JOIN payroll_periods p ON p.id = pi.period_id
             LEFT JOIN business_units bu ON bu.id = p.business_unit_id
             LEFT JOIN employees e ON e.id = pi.employee_id
             WHERE pi.id = ?",
            [$id]
        );
    }

    public function existingPeriod(int $unitId, int $year, int $month, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM payroll_periods WHERE business_unit_id = ? AND period_year = ? AND period_month = ?';
        $params = [$unitId, $year, $month];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int) Database::value($sql, $params) > 0;
    }

    /** @return array{id:int,error:?string} */
    public function createPeriod(int $unitId, int $year, int $month, ?string $notes = null): array
    {
        if ($unitId <= 0) {
            return ['id' => 0, 'error' => 'Pilih unit organisasi terlebih dahulu.'];
        }
        $unit = Database::one('SELECT * FROM business_units WHERE id = ?', [$unitId]);
        if (!$unit) {
            return ['id' => 0, 'error' => 'Unit organisasi tidak ditemukan.'];
        }
        if ($year < 2020 || $year > 2100 || $month < 1 || $month > 12) {
            return ['id' => 0, 'error' => 'Periode payroll tidak valid.'];
        }
        if ($this->existingPeriod($unitId, $year, $month)) {
            return ['id' => 0, 'error' => 'Periode ' . $this->label($month, $year) . ' untuk ' . $unit['name'] . ' sudah ada.'];
        }

        $settings = $this->salary->settings();
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));
        $cutoff = min((int) $settings['cutoff_day'], (int) date('t', strtotime($start)));
        $payDay = min((int) $settings['pay_day'], (int) date('t', strtotime($start)));

        $id = Database::insert('payroll_periods', [
            'business_unit_id' => $unitId,
            'period_year'      => $year,
            'period_month'     => $month,
            'start_date'       => $start,
            'end_date'         => $end,
            'cutoff_date'      => sprintf('%04d-%02d-%02d', $year, $month, $cutoff),
            'pay_date'         => sprintf('%04d-%02d-%02d', $year, $month, $payDay),
            'status'           => 'DRAFT',
            'notes'            => $notes !== null ? substr($notes, 0, 255) : null,
            'created_by'       => \App\Core\Auth::id(),
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        Audit::log('payroll.create', 'payroll_period', $id, ['unit' => $unit['code'], 'period' => $this->label($month, $year)]);
        return ['id' => $id, 'error' => null];
    }

    /** @return array{error:?string} */
    public function deletePeriod(int $id): array
    {
        $period = $this->find($id);
        if (!$period) {
            return ['error' => 'Periode payroll tidak ditemukan.'];
        }
        if (in_array($period['status'], ['SENT_TO_FINANCE', 'FINANCE_APPROVED', 'PAID'], true)) {
            return ['error' => 'Periode yang sudah dikirim ke Finance tidak dapat dihapus.'];
        }

        $this->expenses->unlinkPeriod($id);
        Database::run('DELETE FROM payroll_item_components WHERE item_id IN (SELECT id FROM payroll_items WHERE period_id = ?)', [$id]);
        Database::delete('payroll_items', 'period_id = ?', [$id]);
        Database::delete('payroll_periods', 'id = ?', [$id]);
        Audit::log('payroll.delete', 'payroll_period', $id);
        return ['error' => null];
    }

    /* ============================================================= kalkulasi */

    /**
     * Hitung ulang seluruh slip pada periode.
     * @return array{error:?string,count:int}
     */
    public function calculate(int $id): array
    {
        $period = $this->find($id);
        if (!$period) {
            return ['error' => 'Periode payroll tidak ditemukan.', 'count' => 0];
        }
        if (in_array($period['status'], ['SENT_TO_FINANCE', 'FINANCE_APPROVED', 'PAID'], true)) {
            return ['error' => 'Periode sudah dikirim ke Finance. Batalkan pengiriman sebelum menghitung ulang.', 'count' => 0];
        }

        $employees = Database::all(
            "SELECT e.*, bu.name AS unit_name, bu.code AS unit_code, d.name AS department_name, p.name AS position_name
             FROM employees e
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN positions p ON p.id = e.position_id
             WHERE e.flag = 'ACTIVE' AND e.business_unit_id = ?
             ORDER BY e.full_name",
            [(int) $period['business_unit_id']]
        );

        if (!$employees) {
            return ['error' => 'Tidak ada karyawan aktif pada unit ini.', 'count' => 0];
        }

        $lateEnabled = Settings::bool('attendance_late_deduction_enabled', true);
        $lateType = (string) Settings::get('attendance_late_deduction_type', 'PER_MINUTE');
        $lateValue = (float) Settings::get('attendance_late_deduction_value', 2500);
        $absentEnabled = Settings::bool('attendance_absent_deduction_enabled', false)
            && (string) Settings::get('attendance_absent_deduct_salary', '0') === '1';
        $settings = $this->salary->settings();

        $start = (string) $period['start_date'];
        $end = (string) $period['end_date'];
        $now = date('Y-m-d H:i:s');

        // Klaim yang sebelumnya menempel pada slip periode ini dilepas dulu,
        // supaya hitung ulang tidak menghasilkan tautan ke slip yang dihapus.
        $this->expenses->unlinkPeriod($id);

        Database::run('DELETE FROM payroll_item_components WHERE item_id IN (SELECT id FROM payroll_items WHERE period_id = ?)', [$id]);
        Database::delete('payroll_items', 'period_id = ?', [$id]);

        $totals = ['employee' => 0, 'gross' => 0.0, 'deduction' => 0.0, 'net' => 0.0, 'bpjs_company' => 0.0];
        $sequence = 0;

        Database::transaction(function () use ($employees, $period, $start, $end, $lateEnabled, $lateType, $lateValue, $absentEnabled, $settings, $now, &$totals, &$sequence, $id): void {
            foreach ($employees as $employee) {
                $employeeId = (int) $employee['id'];
                $basic = (float) $employee['basic_salary'];
                $daily = self::WORK_DAYS > 0 ? $basic / self::WORK_DAYS : 0.0;

                $attendance = $this->attendanceTotals($employeeId, $start, $end);
                $overtime = $this->overtimeTotals($employeeId, $start, $end);
                $unpaidDays = $this->unpaidLeaveDays($employeeId, $start, $end);

                $preview = $this->salary->preview($employeeId, $end);
                $benefits = $preview['benefits'] ?? [];
                $deductionLines = $preview['deductions'] ?? [];

                $overtimeAmount = round((float) $overtime['amount'], 2);

                // Reimbursement: klaim disetujui dalam rentang periode ini.
                $claimRows = $this->expenses->approvedForRange($employeeId, $start, $end);
                $claimTotal = round(array_sum(array_map(static fn ($c) => (float) $c['amount'], $claimRows)), 2);

                $gross = round((float) ($preview['gross'] ?? $basic) + $overtimeAmount + $claimTotal, 2);

                $bpjsEmployee = (float) ($preview['bpjs']['employee_total'] ?? 0);
                $bpjsCompany = (float) ($preview['bpjs']['company_total'] ?? 0);
                $kesEmployee = 0.0;
                $tkEmployee = 0.0;
                foreach (($preview['bpjs']['items'] ?? []) as $bpjsItem) {
                    if (($bpjsItem['side'] ?? '') !== 'EMPLOYEE') {
                        continue;
                    }
                    if (str_starts_with((string) $bpjsItem['code'], 'BPJS_KES')) {
                        $kesEmployee += (float) $bpjsItem['amount'];
                    } else {
                        $tkEmployee += (float) $bpjsItem['amount'];
                    }
                }

                // PPh dihitung ulang dengan lembur (objek pajak) ikut diperhitungkan.
                $taxableBase = (float) ($preview['taxable_base'] ?? 0) + $overtimeAmount;
                $pph = $settings['pph_enabled'] ? $this->salary->pph21($employee, max(0, $taxableBase)) : ['monthly' => 0.0];
                $pphAmount = round((float) $pph['monthly'], 2);

                $lateDeduction = $lateEnabled
                    ? round($this->attendance->lateDeduction(
                        (int) $attendance['late_minutes'],
                        (int) $attendance['late_days'],
                        $lateType,
                        $lateValue,
                        (array) ($attendance['late_per_day'] ?? [])
                    ), 2)
                    : 0.0;
                $absentDeduction = $absentEnabled ? round($daily * (int) $attendance['absent_days'], 2) : 0.0;
                $leaveDeduction = round($daily * $unpaidDays, 2);
                $componentDeduction = round((float) ($preview['deduction_total'] ?? 0), 2);

                $totalDeduction = round($componentDeduction + $lateDeduction + $absentDeduction + $leaveDeduction
                    + $bpjsEmployee + $pphAmount, 2);
                $net = round($gross - $totalDeduction, 2);

                $sequence++;
                $unitCode = (string) ($period['unit_code'] ?? 'UNIT');
                $slip = sprintf('SLIP/%s/%04d%02d/%04d', preg_replace('/[^A-Z0-9]/', '', strtoupper($unitCode)) ?: 'UNIT', (int) $period['period_year'], (int) $period['period_month'], $sequence);

                $itemId = Database::insert('payroll_items', [
                    'period_id'        => $id,
                    'employee_id'      => $employeeId,
                    'slip_number'      => $slip,
                    'employee_code'    => (string) $employee['employee_code'],
                    'employee_name'    => (string) $employee['full_name'],
                    'department_name'  => (string) ($employee['department_name'] ?? ''),
                    'position_name'    => (string) ($employee['position_name'] ?? ''),
                    'basic_salary'     => round($basic, 2),
                    'present_days'     => (int) $attendance['present_days'],
                    'absent_days'      => (int) $attendance['absent_days'],
                    'late_days'        => (int) $attendance['late_days'],
                    'late_minutes'     => (int) $attendance['late_minutes'],
                    'late_deduction'   => $lateDeduction,
                    'unpaid_leave_days' => $unpaidDays,
                    'leave_deduction'  => $leaveDeduction,
                    'absent_deduction' => $absentDeduction,
                    'overtime_hours'   => round((float) $overtime['hours'], 2),
                    'overtime_amount'  => $overtimeAmount,
                    'benefit_total'    => round((float) ($preview['benefit_total'] ?? 0), 2),
                    'deduction_total'  => $componentDeduction,
                    'gross_salary'     => $gross,
                    'bpjs_kesehatan_employee' => round($kesEmployee, 2),
                    'bpjs_tk_employee' => round($tkEmployee, 2),
                    'bpjs_company'     => round($bpjsCompany, 2),
                    'pph_amount'       => $pphAmount,
                    'total_deduction'  => $totalDeduction,
                    'net_salary'       => $net,
                    'payment_status'   => 'UNPAID',
                    'created_at'       => $now,
                ]);

                $sort = 0;
                foreach ($benefits as $line) {
                    Database::insert('payroll_item_components', [
                        'item_id'    => $itemId,
                        'code'       => (string) ($line['code'] ?? ''),
                        'name'       => (string) $line['name'],
                        'type'       => 'BENEFIT',
                        'kind'       => 'COMPONENT',
                        'amount'     => round((float) $line['amount'], 2),
                        'sort_order' => ++$sort,
                    ]);
                }
                if ($overtimeAmount > 0) {
                    Database::insert('payroll_item_components', [
                        'item_id'    => $itemId,
                        'code'       => 'OVERTIME',
                        'name'       => 'Lembur (' . number_id((float) $overtime['hours'], 1) . ' jam)',
                        'type'       => 'BENEFIT',
                        'kind'       => 'OVERTIME',
                        'amount'     => $overtimeAmount,
                        'sort_order' => ++$sort,
                    ]);
                }
                if ($claimTotal > 0) {
                    Database::insert('payroll_item_components', [
                        'item_id'    => $itemId,
                        'code'       => 'REIMBURSEMENT',
                        'name'       => 'Reimbursement (' . count($claimRows) . ' klaim disetujui)',
                        'type'       => 'BENEFIT',
                        'kind'       => 'CLAIM',
                        'amount'     => $claimTotal,
                        'sort_order' => ++$sort,
                    ]);
                    $this->expenses->linkToPayroll(array_map(static fn ($c) => (int) $c['id'], $claimRows), $itemId);
                }
                foreach (($preview['bpjs']['items'] ?? []) as $bpjsItem) {
                    if (($bpjsItem['side'] ?? '') !== 'EMPLOYEE' || (float) $bpjsItem['amount'] <= 0) {
                        continue;
                    }
                    Database::insert('payroll_item_components', [
                        'item_id'    => $itemId,
                        'code'       => (string) $bpjsItem['code'],
                        'name'       => (string) $bpjsItem['label'],
                        'type'       => 'DEDUCTION',
                        'kind'       => 'BPJS',
                        'amount'     => round((float) $bpjsItem['amount'], 2),
                        'sort_order' => ++$sort,
                    ]);
                }
                if ($pphAmount > 0) {
                    Database::insert('payroll_item_components', [
                        'item_id'    => $itemId,
                        'code'       => 'PPH21',
                        'name'       => 'PPh 21 (' . (string) ($pph['method'] ?? 'TER') . ')',
                        'type'       => 'DEDUCTION',
                        'kind'       => 'TAX',
                        'amount'     => $pphAmount,
                        'sort_order' => ++$sort,
                    ]);
                }
                foreach ($deductionLines as $line) {
                    Database::insert('payroll_item_components', [
                        'item_id'    => $itemId,
                        'code'       => (string) ($line['code'] ?? ''),
                        'name'       => (string) $line['name'],
                        'type'       => 'DEDUCTION',
                        'kind'       => 'COMPONENT',
                        'amount'     => round((float) $line['amount'], 2),
                        'sort_order' => ++$sort,
                    ]);
                }
                foreach ([
                    ['ATT_LATE', 'Denda keterlambatan (' . (int) $attendance['late_minutes'] . ' menit)', $lateDeduction],
                    ['ATT_ABSENT', 'Potongan tidak hadir (' . (int) $attendance['absent_days'] . ' hari)', $absentDeduction],
                    ['ATT_LEAVE', 'Cuti tanpa upah (' . number_id($unpaidDays, 1) . ' hari)', $leaveDeduction],
                ] as [$code, $label, $amount]) {
                    if ($amount <= 0) {
                        continue;
                    }
                    Database::insert('payroll_item_components', [
                        'item_id'    => $itemId,
                        'code'       => $code,
                        'name'       => $label,
                        'type'       => 'DEDUCTION',
                        'kind'       => 'ATTENDANCE',
                        'amount'     => round($amount, 2),
                        'sort_order' => ++$sort,
                    ]);
                }

                $totals['employee']++;
                $totals['gross'] += $gross;
                $totals['deduction'] += $totalDeduction;
                $totals['net'] += $net;
                $totals['bpjs_company'] += $bpjsCompany;
            }
        });

        Database::update('payroll_periods', [
            'status'             => 'CALCULATED',
            'total_employee'     => $totals['employee'],
            'total_gross'        => round($totals['gross'], 2),
            'total_deduction'    => round($totals['deduction'], 2),
            'total_net'          => round($totals['net'], 2),
            'total_bpjs_company' => round($totals['bpjs_company'], 2),
            'processed_by'       => \App\Core\Auth::id(),
            'processed_at'       => $now,
            'updated_at'         => $now,
        ], 'id = ?', [$id]);

        Audit::log('payroll.calculate', 'payroll_period', $id, [
            'employee' => $totals['employee'],
            'net'      => (int) round($totals['net']),
        ]);

        return ['error' => null, 'count' => $totals['employee']];
    }

    /** @return array{present_days:int,absent_days:int,late_days:int,late_minutes:int,leave_days:int} */
    private function attendanceTotals(int $employeeId, string $start, string $end): array
    {
        $row = Database::one(
            "SELECT
                COALESCE(SUM(CASE WHEN status IN ('PRESENT','LATE') THEN 1 ELSE 0 END), 0) AS present_days,
                COALESCE(SUM(CASE WHEN status = 'ABSENT' THEN 1 ELSE 0 END), 0) AS absent_days,
                COALESCE(SUM(CASE WHEN status = 'LATE' THEN 1 ELSE 0 END), 0) AS late_days,
                COALESCE(SUM(late_minutes), 0) AS late_minutes,
                COALESCE(SUM(CASE WHEN status = 'LEAVE' THEN 1 ELSE 0 END), 0) AS leave_days
             FROM attendance
             WHERE employee_id = ? AND work_date BETWEEN ? AND ?",
            [$employeeId, $start, $end]
        ) ?? [];

        $perDay = Database::all(
            'SELECT late_minutes FROM attendance
              WHERE employee_id = ? AND work_date BETWEEN ? AND ? AND late_minutes > 0
              ORDER BY work_date',
            [$employeeId, $start, $end]
        );

        return [
            'present_days' => (int) ($row['present_days'] ?? 0),
            'absent_days'  => (int) ($row['absent_days'] ?? 0),
            'late_days'    => (int) ($row['late_days'] ?? 0),
            'late_minutes' => (int) ($row['late_minutes'] ?? 0),
            'leave_days'   => (int) ($row['leave_days'] ?? 0),
            'late_per_day' => array_map(static fn (array $r): int => (int) $r['late_minutes'], $perDay),
        ];
    }

    /** @return array{hours:float,amount:float} */
    private function overtimeTotals(int $employeeId, string $start, string $end): array
    {
        $row = Database::one(
            "SELECT COALESCE(SUM(COALESCE(actual_hours, planned_hours)), 0) AS hours,
                    COALESCE(SUM(compensation_amount), 0) AS amount
             FROM overtime_requests
             WHERE employee_id = ? AND status = 'APPROVED' AND work_date BETWEEN ? AND ?",
            [$employeeId, $start, $end]
        ) ?? [];

        return ['hours' => (float) ($row['hours'] ?? 0), 'amount' => (float) ($row['amount'] ?? 0)];
    }

    /** Hari cuti tanpa upah (jenis cuti yang memotong gaji) pada rentang periode. */
    private function unpaidLeaveDays(int $employeeId, string $start, string $end): float
    {
        $row = Database::one(
            "SELECT COALESCE(SUM(lr.days), 0) AS days
             FROM leave_requests lr
             JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.employee_id = ?
               AND lr.status = 'APPROVED'
               AND (lt.is_paid = 0 OR lt.deduct_salary = 1)
               AND lr.start_date <= ? AND lr.end_date >= ?",
            [$employeeId, $end, $start]
        ) ?? [];

        return round((float) ($row['days'] ?? 0), 1);
    }

    /* ================================================================= alur */

    /** @return array{error:?string} */
    public function submitToFinance(int $id): array
    {
        $period = $this->find($id);
        if (!$period) {
            return ['error' => 'Periode payroll tidak ditemukan.'];
        }
        if (!in_array($period['status'], ['DRAFT', 'CALCULATED'], true)) {
            return ['error' => 'Periode ini sudah dikirim ke Finance.'];
        }
        if ((int) $period['total_employee'] === 0) {
            return ['error' => 'Hitung periode terlebih dahulu sebelum dikirim ke Finance.'];
        }

        $user = \App\Core\Auth::user() ?? [];
        $now = date('Y-m-d H:i:s');

        Database::update('payroll_periods', [
            'status'              => 'SENT_TO_FINANCE',
            'sent_by'             => \App\Core\Auth::id(),
            'sent_at'             => $now,
            'hr_signature_name'   => (string) ($user['full_name'] ?? ''),
            'hr_signature_at'     => $now,
            'updated_at'          => $now,
        ], 'id = ?', [$id]);

        $this->notifyFinance($period);
        Audit::log('payroll.submit', 'payroll_period', $id, [
            'period' => $this->label((int) $period['period_month'], (int) $period['period_year']),
            'net'    => (int) round((float) $period['total_net']),
        ]);

        return ['error' => null];
    }

    /** @return array{error:?string} */
    public function approveFinance(int $id): array
    {
        $period = $this->find($id);
        if (!$period) {
            return ['error' => 'Periode payroll tidak ditemukan.'];
        }
        if ($period['status'] !== 'SENT_TO_FINANCE') {
            return ['error' => 'Hanya periode yang dikirim HR yang dapat disetujui Finance.'];
        }

        $user = \App\Core\Auth::user() ?? [];
        $now = date('Y-m-d H:i:s');

        Database::update('payroll_periods', [
            'status'              => 'FINANCE_APPROVED',
            'finance_approved_by' => \App\Core\Auth::id(),
            'finance_approved_at' => $now,
            'fin_signature_name'  => (string) ($user['full_name'] ?? ''),
            'fin_signature_at'    => $now,
            'updated_at'          => $now,
        ], 'id = ?', [$id]);

        Audit::log('payroll.finance-approve', 'payroll_period', $id);
        return ['error' => null];
    }

    /** @return array{error:?string,paid:int} */
    public function markPaid(int $id): array
    {
        $period = $this->find($id);
        if (!$period) {
            return ['error' => 'Periode payroll tidak ditemukan.', 'paid' => 0];
        }
        if ($period['status'] !== 'FINANCE_APPROVED') {
            return ['error' => 'Periode harus disetujui Finance sebelum ditandai dibayar.', 'paid' => 0];
        }

        $now = date('Y-m-d H:i:s');
        $paid = Database::update('payroll_items', [
            'payment_status' => 'PAID',
            'paid_at'        => $now,
            'paid_by'        => \App\Core\Auth::id(),
        ], 'period_id = ?', [$id]);

        Database::update('payroll_periods', [
            'status'     => 'PAID',
            'paid_by'    => \App\Core\Auth::id(),
            'paid_at'    => $now,
            'updated_at' => $now,
        ], 'id = ?', [$id]);

        $claims = $this->expenses->markPeriodPaid($id, \App\Core\Auth::id());

        $this->notifyEmployees($period);
        Audit::log('payroll.paid', 'payroll_period', $id, ['items' => $paid, 'claims' => $claims]);

        return ['error' => null, 'paid' => $paid];
    }

    /** @return array{error:?string} */
    public function reopen(int $id): array
    {
        $period = $this->find($id);
        if (!$period) {
            return ['error' => 'Periode payroll tidak ditemukan.'];
        }
        if (!in_array($period['status'], ['SENT_TO_FINANCE', 'FINANCE_APPROVED'], true)) {
            return ['error' => 'Periode ini tidak dapat dikembalikan ke HR.'];
        }

        Database::update('payroll_periods', [
            'status'              => 'CALCULATED',
            'sent_by'             => null,
            'sent_at'             => null,
            'finance_approved_by' => null,
            'finance_approved_at' => null,
            'hr_signature_name'   => null,
            'hr_signature_at'     => null,
            'fin_signature_name'  => null,
            'fin_signature_at'    => null,
            'updated_at'          => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        Audit::log('payroll.reopen', 'payroll_period', $id);
        return ['error' => null];
    }

    private function notifyFinance(array $period): void
    {
        $finance = Database::all("SELECT id FROM employees WHERE role = 'FIN' AND flag = 'ACTIVE'");
        $label = $this->label((int) $period['period_month'], (int) $period['period_year']);
        foreach ($finance as $person) {
            Database::insert('notifications', [
                'employee_id' => (int) $person['id'],
                'title'       => 'Payroll ' . $label . ' menunggu finalisasi',
                'message'     => ($period['unit_name'] ?? 'Unit') . ' · net ' . rupiah((float) $period['total_net']),
                'link'        => '/payroll/' . (int) $period['id'],
                'type'        => 'payroll',
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function notifyEmployees(array $period): void
    {
        $label = $this->label((int) $period['period_month'], (int) $period['period_year']);
        $items = Database::all('SELECT id, employee_id, net_salary FROM payroll_items WHERE period_id = ?', [(int) $period['id']]);
        foreach ($items as $item) {
            Database::insert('notifications', [
                'employee_id' => (int) $item['employee_id'],
                'title'       => 'Slip gaji ' . $label . ' sudah tersedia',
                'message'     => 'Gaji bersih ' . rupiah((float) $item['net_salary']) . ' telah dibayarkan.',
                'link'        => '/me/payslip',
                'type'        => 'payroll',
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /* ================================================================= item */

    public function items(int $periodId, array $filters = []): array
    {
        $clauses = ['pi.period_id = ?'];
        $params = [$periodId];

        if (!empty($filters['department'])) {
            $clauses[] = 'pi.department_name = ?';
            $params[] = (string) $filters['department'];
        }
        if (($filters['q'] ?? '') !== '') {
            $clauses[] = '(pi.employee_name LIKE ? OR pi.employee_code LIKE ? OR pi.slip_number LIKE ?)';
            $like = Database::like((string) $filters['q']);
            array_push($params, $like, $like, $like);
        }

        return Database::all(
            'SELECT pi.* FROM payroll_items pi WHERE ' . implode(' AND ', $clauses) . ' ORDER BY pi.employee_name',
            $params
        );
    }

    public function itemComponents(int $itemId): array
    {
        return Database::all(
            "SELECT * FROM payroll_item_components WHERE item_id = ?
             ORDER BY CASE WHEN type = 'BENEFIT' THEN 0 ELSE 1 END, sort_order, id",
            [$itemId]
        );
    }

    /** Rekap periode per departemen + ringkasan. */
    public function recapitulation(int $periodId): array
    {
        $departments = Database::all(
            "SELECT COALESCE(NULLIF(department_name, ''), 'Tanpa Departemen') AS department_name,
                    COUNT(*) AS employees,
                    COALESCE(SUM(gross_salary), 0) AS gross,
                    COALESCE(SUM(total_deduction), 0) AS deduction,
                    COALESCE(SUM(net_salary), 0) AS net,
                    COALESCE(SUM(pph_amount), 0) AS pph,
                    COALESCE(SUM(bpjs_kesehatan_employee + bpjs_tk_employee), 0) AS bpjs_employee,
                    COALESCE(SUM(bpjs_company), 0) AS bpjs_company,
                    COALESCE(SUM(overtime_amount), 0) AS overtime
             FROM payroll_items
             WHERE period_id = ?
             GROUP BY COALESCE(NULLIF(department_name, ''), 'Tanpa Departemen')
             ORDER BY net DESC",
            [$periodId]
        );

        $summary = Database::one(
            "SELECT COUNT(*) AS employees,
                    COALESCE(SUM(gross_salary), 0) AS gross,
                    COALESCE(SUM(total_deduction), 0) AS deduction,
                    COALESCE(SUM(net_salary), 0) AS net,
                    COALESCE(SUM(pph_amount), 0) AS pph,
                    COALESCE(SUM(bpjs_kesehatan_employee), 0) AS bpjs_kes_employee,
                    COALESCE(SUM(bpjs_tk_employee), 0) AS bpjs_tk_employee,
                    COALESCE(SUM(bpjs_company), 0) AS bpjs_company,
                    COALESCE(SUM(overtime_amount), 0) AS overtime,
                    COALESCE(SUM(late_deduction), 0) AS late_deduction,
                    COALESCE(SUM(absent_deduction), 0) AS absent_deduction,
                    COALESCE(SUM(leave_deduction), 0) AS leave_deduction
             FROM payroll_items WHERE period_id = ?",
            [$periodId]
        ) ?? [];

        return ['departments' => $departments, 'summary' => $summary];
    }

    /** Ringkasan lintas unit untuk dashboard payroll. */
    public function stats(): array
    {
        $statusCounts = [];
        foreach (Database::all('SELECT status, COUNT(*) AS total FROM payroll_periods GROUP BY status') as $row) {
            $statusCounts[(string) $row['status']] = (int) $row['total'];
        }

        $latest = Database::one(
            'SELECT p.*, bu.name AS unit_name FROM payroll_periods p
             LEFT JOIN business_units bu ON bu.id = p.business_unit_id
             ORDER BY p.period_year DESC, p.period_month DESC, p.id DESC LIMIT 1'
        );

        $ytd = Database::one(
            'SELECT COALESCE(SUM(total_gross), 0) AS gross, COALESCE(SUM(total_net), 0) AS net,
                    COALESCE(SUM(total_deduction), 0) AS deduction, COALESCE(SUM(total_bpjs_company), 0) AS bpjs_company,
                    COALESCE(SUM(total_employee), 0) AS employees
             FROM payroll_periods WHERE period_year = ? AND status = ?',
            [(int) date('Y'), 'PAID']
        ) ?? [];

        $pending = (int) Database::value(
            "SELECT COUNT(*) FROM payroll_periods WHERE status IN ('SENT_TO_FINANCE','FINANCE_APPROVED')"
        );

        return [
            'total'    => array_sum($statusCounts),
            'draft'    => $statusCounts['DRAFT'] ?? 0,
            'calculated' => $statusCounts['CALCULATED'] ?? 0,
            'sent'     => $statusCounts['SENT_TO_FINANCE'] ?? 0,
            'approved' => $statusCounts['FINANCE_APPROVED'] ?? 0,
            'paid'     => $statusCounts['PAID'] ?? 0,
            'pending_finance' => $pending,
            'latest'   => $latest,
            'ytd'      => $ytd,
        ];
    }

    /** @return array<int,array<string,mixed>> tren 6 periode terakhir yang sudah dihitung */
    public function trend(int $limit = 6): array
    {
        return Database::all(
            "SELECT period_year, period_month, COALESCE(SUM(total_gross),0) AS gross, COALESCE(SUM(total_net),0) AS net,
                    COALESCE(SUM(total_employee),0) AS employees
             FROM payroll_periods
             WHERE status IN ('CALCULATED','SENT_TO_FINANCE','FINANCE_APPROVED','PAID')
             GROUP BY period_year, period_month
             ORDER BY period_year DESC, period_month DESC LIMIT " . max(1, $limit)
        );
    }

    /** Slip milik satu karyawan (setelah disetujui finance / dibayar). */
    public function mySlips(int $employeeId): array
    {
        return Database::all(
            "SELECT pi.*, p.period_year, p.period_month, p.pay_date, p.status AS period_status, bu.name AS unit_name
             FROM payroll_items pi
             JOIN payroll_periods p ON p.id = pi.period_id
             LEFT JOIN business_units bu ON bu.id = p.business_unit_id
             WHERE pi.employee_id = ? AND p.status IN ('FINANCE_APPROVED','PAID')
             ORDER BY p.period_year DESC, p.period_month DESC LIMIT 24",
            [$employeeId]
        );
    }

    public function findMySlip(int $employeeId, int $itemId): ?array
    {
        foreach ($this->mySlips($employeeId) as $slip) {
            if ((int) $slip['id'] === $itemId) {
                return $slip;
            }
        }
        return null;
    }

    /* ============================================================== utilitas */

    public function label(int $month, int $year): string
    {
        return bulan_id($month) . ' ' . $year;
    }

    /** Token verifikasi QR slip (tanpa penyimpanan tambahan). */
    public function verifyToken(int $itemId): string
    {
        $key = (string) config('app_key', 'asiaraya');
        return substr(hash_hmac('sha256', 'payslip:' . $itemId, $key), 0, 32);
    }

    /** URL absolut (dipakai QR) agar dapat dipindai dari perangkat lain. */
    public function verifyUrl(int $itemId): string
    {
        $base = trim((string) config('app_url', ''));
        if ($base === '') {
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
                || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
            $base = ($https ? 'https://' : 'http://') . $host;
        }

        return rtrim($base, '/') . url('verify/slip/' . $itemId . '/' . $this->verifyToken($itemId));
    }

    public function verifyItem(int $itemId, string $token): ?array
    {
        if (!hash_equals($this->verifyToken($itemId), $token)) {
            return null;
        }
        return $this->findItem($itemId);
    }

    /** Daftar karyawan pada periode (untuk pratinjau sebelum hitung). */
    public function periodHeadcount(int $unitId): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM employees WHERE flag = 'ACTIVE' AND business_unit_id = ?", [$unitId]);
    }
}
