<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Settings;

/**
 * Aggregations for both dashboards (HR and employee). All queries stay inside
 * the portable SQL subset so they run on MySQL and SQLite alike.
 */
final class DashboardModel
{
    /* ------------------------------------------------------------ workforce */
    public function workforce(): array
    {
        $total = (int) Database::value("SELECT COUNT(*) FROM employees WHERE flag = 'ACTIVE'");
        $newThisMonth = (int) Database::value(
            "SELECT COUNT(*) FROM employees WHERE flag = 'ACTIVE' AND join_date >= ?",
            [date('Y-m-01')]
        );
        $contract = (int) Database::value(
            "SELECT COUNT(*) FROM employees WHERE flag = 'ACTIVE' AND employment_status LIKE 'PKWT%'"
        );
        $permanent = (int) Database::value(
            "SELECT COUNT(*) FROM employees WHERE flag = 'ACTIVE' AND employment_status LIKE 'PKWTT%'"
        );
        $units = Database::all(
            "SELECT bu.id, bu.code, bu.name, bu.city,
                    SUM(CASE WHEN e.flag = 'ACTIVE' THEN 1 ELSE 0 END) AS active_count,
                    COUNT(e.id) AS total_count
             FROM business_units bu
             LEFT JOIN employees e ON e.business_unit_id = bu.id
             WHERE bu.is_active = 1
             GROUP BY bu.id, bu.code, bu.name, bu.city
             ORDER BY bu.id"
        );
        $departments = (int) Database::value('SELECT COUNT(*) FROM departments WHERE is_active = 1');
        $expiring = (int) Database::value(
            "SELECT COUNT(*) FROM employees
             WHERE flag = 'ACTIVE' AND contract_end IS NOT NULL
               AND contract_end BETWEEN ? AND ?",
            [date('Y-m-d'), date('Y-m-d', strtotime('+60 days'))]
        );

        return [
            'total' => $total,
            'new_this_month' => $newThisMonth,
            'contract' => $contract,
            'permanent' => $permanent,
            'units' => $units,
            'unit_count' => count($units),
            'department_count' => $departments,
            'expiring_contracts' => $expiring,
        ];
    }

    /* ---------------------------------------------------------- attendance */
    public function attendanceToday(?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');

        $rows = Database::all(
            "SELECT a.status, a.operational_type, COUNT(*) AS total
             FROM attendance a
             JOIN employees e ON e.id = a.employee_id AND e.flag = 'ACTIVE'
             WHERE a.work_date = ?
             GROUP BY a.status, a.operational_type",
            [$date]
        );

        $counts = ['PRESENT' => 0, 'LATE' => 0, 'ABSENT' => 0, 'LEAVE' => 0, 'WFH' => 0];
        foreach ($rows as $row) {
            $status = $row['status'];
            $total = (int) $row['total'];
            if ($status === 'PRESENT' || $status === 'LATE') {
                $counts[$status] += $total;
                if ($row['operational_type'] === 'WFH') {
                    $counts['WFH'] += $total;
                }
            } elseif (isset($counts[$status])) {
                $counts[$status] += $total;
            }
        }

        $activeTotal = (int) Database::value("SELECT COUNT(*) FROM employees WHERE flag = 'ACTIVE'");
        $recorded = array_sum([$counts['PRESENT'], $counts['LATE'], $counts['ABSENT'], $counts['LEAVE']]);
        $present = $counts['PRESENT'] + $counts['LATE'];
        $rate = $activeTotal > 0 ? round(($present / max(1, $activeTotal)) * 100, 1) : 0.0;

        $onTime = $counts['PRESENT'];
        $late = $counts['LATE'];

        // per business unit today
        $units = Database::all(
            "SELECT bu.id, bu.code, bu.name, bu.latitude, bu.longitude, bu.geofence_radius,
                    COUNT(e.id) AS headcount,
                    SUM(CASE WHEN a.status IN ('PRESENT','LATE') THEN 1 ELSE 0 END) AS present,
                    SUM(CASE WHEN a.status = 'LATE' THEN 1 ELSE 0 END) AS late,
                    SUM(CASE WHEN a.status = 'ABSENT' THEN 1 ELSE 0 END) AS absent,
                    SUM(CASE WHEN a.status = 'LEAVE' THEN 1 ELSE 0 END) AS on_leave,
                    SUM(CASE WHEN a.operational_type = 'WFH' AND a.status IN ('PRESENT','LATE') THEN 1 ELSE 0 END) AS wfh
             FROM business_units bu
             LEFT JOIN employees e ON e.business_unit_id = bu.id AND e.flag = 'ACTIVE'
             LEFT JOIN attendance a ON a.employee_id = e.id AND a.work_date = ?
             WHERE bu.is_active = 1
             GROUP BY bu.id, bu.code, bu.name, bu.latitude, bu.longitude, bu.geofence_radius
             ORDER BY bu.id",
            [$date]
        );
        foreach ($units as &$unit) {
            $head = (int) $unit['headcount'];
            $unit['rate'] = $head > 0 ? round(((int) $unit['present'] / $head) * 100, 1) : 0.0;
        }
        unset($unit);

        return [
            'date' => $date,
            'counts' => $counts,
            'recorded' => $recorded,
            'rate' => $rate,
            'on_time' => $onTime,
            'late' => $late,
            'active_total' => $activeTotal,
            'units' => $units,
            'logs' => $this->latestPunches(3, $date, (int) ($units[0]['id'] ?? 0) ?: null),
        ];
    }

    public function latestPunches(int $limit = 5, ?string $date = null, ?int $businessUnitId = null): array
    {
        $date = $date ?: date('Y-m-d');
        $filter = $businessUnitId ? ' AND e.business_unit_id = ' . (int) $businessUnitId : '';
        return Database::all(
            "SELECT a.*, e.full_name, e.employee_code, bu.name AS unit_name, d.name AS department_name,
                    s.name AS shift_name, s.check_in_time, s.check_out_time
             FROM attendance a
             JOIN employees e ON e.id = a.employee_id
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN work_shifts s ON s.id = a.shift_id
             WHERE a.work_date = ? AND a.check_in_at IS NOT NULL" . $filter . "
             ORDER BY a.check_in_at DESC, a.id DESC
             LIMIT " . max(1, $limit),
            [$date]
        );
    }

    /* ----------------------------------------------------------- approvals */
    public function pendingApprovals(): array
    {
        $overtime = Database::all(
            "SELECT o.*, e.full_name, e.employee_code, e.role, d.name AS department_name, bu.name AS unit_name,
                    m.full_name AS manager_name
             FROM overtime_requests o
             JOIN employees e ON e.id = o.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN employees m ON m.id = e.direct_manager_id
             WHERE o.status IN ('PENDING', 'MANAGER_APPROVED')
             ORDER BY o.id DESC LIMIT 6"
        );
        $leave = Database::all(
            "SELECT l.*, lt.name AS leave_type_name, lt.is_paid, e.full_name, e.employee_code,
                    d.name AS department_name, m.full_name AS manager_name
             FROM leave_requests l
             JOIN leave_types lt ON lt.id = l.leave_type_id
             JOIN employees e ON e.id = l.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN employees m ON m.id = e.direct_manager_id
             WHERE l.status IN ('PENDING', 'MANAGER_APPROVED')
             ORDER BY l.id DESC LIMIT 6"
        );
        $shift = Database::all(
            "SELECT r.*, e.full_name, e.employee_code, s1.name AS current_shift, s2.name AS requested_shift,
                    d.name AS department_name
             FROM shift_change_requests r
             JOIN employees e ON e.id = r.employee_id
             LEFT JOIN work_shifts s1 ON s1.id = r.current_shift_id
             LEFT JOIN work_shifts s2 ON s2.id = r.requested_shift_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE r.status IN ('PENDING', 'MANAGER_APPROVED')
             ORDER BY r.id DESC LIMIT 4"
        );

        return [
            'overtime' => $overtime,
            'leave' => $leave,
            'shift' => $shift,
            'counts' => [
                'overtime' => (int) Database::value("SELECT COUNT(*) FROM overtime_requests WHERE status IN ('PENDING','MANAGER_APPROVED')"),
                'leave' => (int) Database::value("SELECT COUNT(*) FROM leave_requests WHERE status IN ('PENDING','MANAGER_APPROVED')"),
                'shift' => (int) Database::value("SELECT COUNT(*) FROM shift_change_requests WHERE status IN ('PENDING','MANAGER_APPROVED')"),
            ],
            'total' => (int) Database::value("SELECT COUNT(*) FROM overtime_requests WHERE status IN ('PENDING','MANAGER_APPROVED')")
                + (int) Database::value("SELECT COUNT(*) FROM leave_requests WHERE status IN ('PENDING','MANAGER_APPROVED')")
                + (int) Database::value("SELECT COUNT(*) FROM shift_change_requests WHERE status IN ('PENDING','MANAGER_APPROVED')"),
        ];
    }

    /* ------------------------------------------------------------- payroll */
    /**
     * Latest payroll period (any unit) plus month-to-date estimation when no
     * payroll has been processed yet.
     */
    public function payroll(): array
    {
        $last = Database::one(
            "SELECT p.*, bu.name AS unit_name, bu.code AS unit_code
             FROM payroll_periods p
             LEFT JOIN business_units bu ON bu.id = p.business_unit_id
             ORDER BY p.period_year DESC, p.period_month DESC, p.id DESC LIMIT 1"
        );

        $activeCount = (int) Database::value("SELECT COUNT(*) FROM employees WHERE flag = 'ACTIVE'");
        $basicTotal = (float) Database::value("SELECT COALESCE(SUM(basic_salary), 0) FROM employees WHERE flag = 'ACTIVE'");

        // Benefit components assigned to active employees (fixed or percent of basic)
        $componentRows = Database::all(
            "SELECT sc.type, sc.calc_type, sc.amount, ec.amount_override, e.basic_salary, COUNT(*) AS cnt
             FROM employee_components ec
             JOIN salary_components sc ON sc.id = ec.component_id AND sc.is_active = 1
             JOIN employees e ON e.id = ec.employee_id AND e.flag = 'ACTIVE'
             WHERE ec.is_active = 1
             GROUP BY sc.type, sc.calc_type, sc.amount, ec.amount_override, e.basic_salary"
        );
        $benefit = 0.0;
        $deduction = 0.0;
        foreach ($componentRows as $row) {
            $base = (float) $row['basic_salary'];
            $amount = $row['amount_override'] !== null
                ? (float) $row['amount_override']
                : ($row['calc_type'] === 'PERCENT' ? $base * ((float) $row['amount'] / 100) : (float) $row['amount']);
            if ($row['type'] === 'BENEFIT') {
                $benefit += $amount;
            } else {
                $deduction += $amount;
            }
        }

        $overtimeMonth = (float) Database::value(
            "SELECT COALESCE(SUM(compensation_amount), 0) FROM overtime_requests
             WHERE status IN ('APPROVED','HR_APPROVED','MANAGER_APPROVED')
               AND work_date >= ? AND work_date <= ?",
            [date('Y-m-01'), date('Y-m-t')]
        );

        $gross = $basicTotal + $benefit;
        $pphRate = Settings::float('payroll_pph_rate', 5) / 100;
        $pph = Settings::bool('payroll_pph_enabled', true) ? $gross * $pphRate : 0.0;

        $bpjsKesEmployee = Settings::bool('bpjs_kesehatan_enabled', true)
            ? $gross * (Settings::float('bpjs_kes_employee_pct', 1) / 100) : 0.0;
        $bpjsTkEmployee = Settings::bool('bpjs_tk_enabled', true)
            ? $gross * ((Settings::float('bpjs_jht_employee_pct', 2) + Settings::float('bpjs_jp_employee_pct', 1)) / 100) : 0.0;
        $bpjsCompany = Settings::bool('bpjs_kesehatan_enabled', true)
            ? $gross * (Settings::float('bpjs_kes_company_pct', 4) / 100) : 0.0;
        if (Settings::bool('bpjs_tk_enabled', true)) {
            $bpjsCompany += $gross * ((
                Settings::float('bpjs_jht_company_pct', 3.7) +
                Settings::float('bpjs_jp_company_pct', 2) +
                Settings::float('bpjs_jkk_pct', 0.24) +
                Settings::float('bpjs_jkm_pct', 0.3)
            ) / 100);
        }

        $estimateNet = $gross - $deduction - $pph - $bpjsKesEmployee - $bpjsTkEmployee;

        $cutoffDay = Settings::int('payroll_cutoff_day', 25);
        $payDay = Settings::int('payroll_pay_day', 28);

        return [
            'last' => $last,
            'has_payroll' => $last !== null,
            'active_count' => $activeCount,
            'basic_total' => $basicTotal,
            'benefit_total' => $benefit,
            'deduction_total' => $deduction,
            'overtime_month' => $overtimeMonth,
            'gross' => $gross,
            'pph' => $pph,
            'bpjs_employee' => $bpjsKesEmployee + $bpjsTkEmployee,
            'bpjs_company' => $bpjsCompany,
            'net' => $estimateNet,
            'cutoff_date' => date('Y-m-' . str_pad((string) $cutoffDay, 2, '0', STR_PAD_LEFT)),
            'pay_date' => date('Y-m-' . str_pad((string) $payDay, 2, '0', STR_PAD_LEFT)),
            'period_label' => strtoupper(bulan_id((int) date('n'))) . ' ' . date('Y'),
            'next_period_label' => strtoupper(bulan_id((int) date('n', strtotime('+1 month')))) . ' ' . date('Y', strtotime('+1 month')),
        ];
    }

    /** Stacked bar composition for the salary load card. */
    public function salaryComposition(): array
    {
        $payroll = $this->payroll();
        $basic = (float) $payroll['basic_total'];
        $allowance = (float) $payroll['benefit_total'];
        $overtime = (float) $payroll['overtime_month'];
        $deduction = (float) $payroll['deduction_total'] + (float) $payroll['pph'] + (float) $payroll['bpjs_employee'];

        $total = $basic + $allowance + $overtime + $deduction;
        if ($total <= 0) {
            $total = 1;
        }

        return [
            'segments' => [
                ['label' => 'Gaji Pokok', 'value' => $basic, 'pct' => round($basic / $total * 100, 1), 'color' => '#0b2135'],
                ['label' => 'Tunj. Tetap', 'value' => $allowance, 'pct' => round($allowance / $total * 100, 1), 'color' => '#0f766e'],
                ['label' => 'Lembur Disetujui', 'value' => $overtime, 'pct' => round($overtime / $total * 100, 1), 'color' => '#14b8a6'],
                ['label' => 'Denda & Potongan', 'value' => -1 * $deduction, 'pct' => round($deduction / $total * 100, 1), 'color' => '#be123c'],
            ],
            'total' => $total,
            'estimated_net' => (float) $payroll['net'],
        ];
    }

    /* --------------------------------------------------- employee monitoring */
    public function employeeMonitoring(int $limit = 5, string $search = ''): array
    {
        $sql = "SELECT e.*, bu.name AS unit_name, bu.code AS unit_code, d.name AS department_name,
                       p.name AS position_name,
                       a.status AS attendance_status, a.check_in_at, a.check_out_at, a.late_minutes,
                       a.operational_type, s.check_in_time, s.check_out_time, s.name AS shift_name,
                       lt.name AS leave_type_name
                FROM employees e
                LEFT JOIN business_units bu ON bu.id = e.business_unit_id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN positions p ON p.id = e.position_id
                LEFT JOIN attendance a ON a.employee_id = e.id AND a.work_date = ?
                LEFT JOIN work_shifts s ON s.id = a.shift_id
                LEFT JOIN leave_requests lr ON lr.employee_id = e.id
                     AND lr.status = 'APPROVED' AND ? BETWEEN lr.start_date AND lr.end_date
                LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                WHERE e.flag = 'ACTIVE'";
        $params = [date('Y-m-d'), date('Y-m-d')];
        if ($search !== '') {
            $sql .= ' AND (e.full_name LIKE ? OR e.employee_code LIKE ? OR d.name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' ORDER BY e.id LIMIT ' . max(1, $limit);

        return Database::all($sql, $params);
    }

    /* ------------------------------------------------------- critical dates */
    public function criticalDates(int $days = 45): array
    {
        $items = [];

        $contracts = Database::all(
            "SELECT id, full_name, employee_code, contract_end, employment_status
             FROM employees
             WHERE flag = 'ACTIVE' AND contract_end IS NOT NULL
               AND contract_end BETWEEN ? AND ?
             ORDER BY contract_end ASC",
            [date('Y-m-d'), date('Y-m-d', strtotime('+' . $days . ' days'))]
        );
        foreach ($contracts as $c) {
            $items[] = [
                'date'  => $c['contract_end'],
                'title' => 'Kontrak ' . $c['employment_status'] . ' Berakhir',
                'sub'   => $c['full_name'] . ' (' . $c['employee_code'] . ')',
                'tone'  => 'rose',
                'type'  => 'contract',
            ];
        }

        $cutoffDay = Settings::int('payroll_cutoff_day', 25);
        $payDay = Settings::int('payroll_pay_day', 28);
        $items[] = [
            'date'  => date('Y-m-' . str_pad((string) $cutoffDay, 2, '0', STR_PAD_LEFT)),
            'title' => 'Cut-off Presensi & Payroll',
            'sub'   => 'Batas akhir approval overtime dan revisi data presensi',
            'tone'  => '',
            'type'  => 'payroll',
        ];
        $items[] = [
            'date'  => date('Y-m-' . str_pad((string) $payDay, 2, '0', STR_PAD_LEFT)),
            'title' => 'Disbursement Gaji All Branch',
            'sub'   => 'Transfer via Host-to-Host Mandiri & BCA',
            'tone'  => '',
            'type'  => 'payroll',
        ];

        $holidays = Database::all(
            'SELECT holiday_date, name FROM holidays WHERE holiday_date >= ? ORDER BY holiday_date LIMIT 3',
            [date('Y-m-d')]
        );
        foreach ($holidays as $h) {
            $items[] = [
                'date'  => $h['holiday_date'],
                'title' => 'Hari Libur Nasional',
                'sub'   => $h['name'],
                'tone'  => '',
                'type'  => 'holiday',
            ];
        }

        usort($items, static fn ($a, $b) => strcmp($a['date'], $b['date']));
        return array_slice($items, 0, 5);
    }

    /* ------------------------------------------------- employee self views */
    public function myAttendance(int $employeeId): array
    {
        $today = Database::one(
            "SELECT a.*, s.name AS shift_name, s.check_in_time, s.check_out_time, s.operational_type AS shift_type,
                    s.break_minutes, s.late_tolerance_minutes
             FROM attendance a
             LEFT JOIN work_shifts s ON s.id = a.shift_id
             WHERE a.employee_id = ? AND a.work_date = ?",
            [$employeeId, date('Y-m-d')]
        );

        $schedule = Database::one(
            "SELECT sc.*, s.name AS shift_name, s.code AS shift_code, s.check_in_time, s.check_out_time,
                    s.break_minutes, s.operational_type, s.late_tolerance_minutes, s.color
             FROM schedules sc
             JOIN work_shifts s ON s.id = sc.shift_id
             WHERE sc.employee_id = ? AND ? BETWEEN sc.start_date AND sc.end_date
             ORDER BY sc.id DESC LIMIT 1",
            [$employeeId, date('Y-m-d')]
        );

        $month = Database::all(
            "SELECT a.*, s.name AS shift_name
             FROM attendance a LEFT JOIN work_shifts s ON s.id = a.shift_id
             WHERE a.employee_id = ? AND a.work_date BETWEEN ? AND ?
             ORDER BY a.work_date DESC",
            [$employeeId, date('Y-m-01'), date('Y-m-t')]
        );

        $summary = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'late_minutes' => 0];
        foreach ($month as $row) {
            $key = strtolower($row['status']);
            if (isset($summary[$key])) {
                $summary[$key]++;
            }
            $summary['late_minutes'] += (int) $row['late_minutes'];
        }

        return ['today' => $today, 'schedule' => $schedule, 'month' => $month, 'summary' => $summary];
    }

    public function myRequests(int $employeeId): array
    {
        return [
            'overtime' => Database::all(
                "SELECT o.*, e.full_name AS manager_name FROM overtime_requests o
                 LEFT JOIN employees e ON e.id = o.manager_id
                 WHERE o.employee_id = ? ORDER BY o.id DESC LIMIT 5",
                [$employeeId]
            ),
            'leave' => Database::all(
                "SELECT l.*, lt.name AS leave_type_name, lt.color FROM leave_requests l
                 JOIN leave_types lt ON lt.id = l.leave_type_id
                 WHERE l.employee_id = ? ORDER BY l.id DESC LIMIT 5",
                [$employeeId]
            ),
            'shift' => Database::all(
                "SELECT r.*, s1.name AS current_shift, s2.name AS requested_shift
                 FROM shift_change_requests r
                 LEFT JOIN work_shifts s1 ON s1.id = r.current_shift_id
                 LEFT JOIN work_shifts s2 ON s2.id = r.requested_shift_id
                 WHERE r.employee_id = ? ORDER BY r.id DESC LIMIT 3",
                [$employeeId]
            ),
            'balances' => Database::all(
                "SELECT b.*, lt.name AS leave_type_name, lt.color, lt.is_paid
                 FROM leave_balances b JOIN leave_types lt ON lt.id = b.leave_type_id
                 WHERE b.employee_id = ? AND b.period_year = ?
                 ORDER BY lt.id",
                [$employeeId, (int) date('Y')]
            ),
        ];
    }

    public function myPayroll(int $employeeId): array
    {
        $lastItem = Database::one(
            "SELECT pi.*, p.period_month, p.period_year, p.status AS period_status, p.pay_date
             FROM payroll_items pi JOIN payroll_periods p ON p.id = pi.period_id
             WHERE pi.employee_id = ?
             ORDER BY p.period_year DESC, p.period_month DESC LIMIT 1",
            [$employeeId]
        );

        $components = [];
        if ($lastItem) {
            $components = Database::all(
                'SELECT * FROM payroll_item_components WHERE item_id = ? ORDER BY type DESC, sort_order',
                [(int) $lastItem['id']]
            );
        }

        return ['last_item' => $lastItem, 'components' => $components];
    }

/** Next shift assignments for the employee (upcoming days). */
    public function employeeScheduleNext(int $employeeId, int $limit = 5): array
    {
        return Database::all(
            "SELECT sc.*, s.name AS shift_name, s.code AS shift_code, s.check_in_time, s.check_out_time,
                    s.break_minutes, s.operational_type, s.color, bu.name AS unit_name
             FROM schedules sc
             JOIN work_shifts s ON s.id = sc.shift_id
             LEFT JOIN employees e ON e.id = sc.employee_id
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             WHERE sc.employee_id = ? AND sc.end_date >= ?
             ORDER BY sc.start_date ASC LIMIT " . max(1, $limit),
            [$employeeId, date('Y-m-d')]
        );
    }

    /** Badge counters used by the sidebar + topbar. */

    public function badges(array $user): array
    {
        $counts = $this->pendingApprovals()['counts'];
        $claims = 0;
        try {
            $claims = (int) Database::value(
                "SELECT COUNT(*) FROM expense_claims WHERE status IN ('PENDING','MANAGER_APPROVED')"
            );
        } catch (\Throwable) {
            // tabel klaim belum ada pada basis data lama
        }
        $announcements = 0;
        $notifications = 0;
        try {
            $announcements = (new \App\Models\AnnouncementModel())->unreadCount($user);
            $notifications = (int) Database::value(
                'SELECT COUNT(*) FROM notifications WHERE employee_id = ? AND read_at IS NULL',
                [(int) $user['id']],
                0
            );
        } catch (\Throwable) {
            // tabel pengumuman/notifikasi belum ada pada basis data lama
        }

        return [
            'overtime'  => $counts['overtime'],
            'leave'     => $counts['leave'],
            'shift'     => $counts['shift'],
            'claims'    => $claims,
            'announcements' => $announcements,
            'notifications' => $notifications,
            'performance'   => (int) Database::value(
                "SELECT COUNT(*) FROM performance_reviews r
                   JOIN review_periods p ON p.id = r.period_id
                  WHERE r.status = 'MANAGER_DONE' AND p.status IN ('MANAGER','CALIBRATION')",
                [],
                0
            ),
            'performance_team' => (int) Database::value(
                "SELECT COUNT(*) FROM performance_reviews r
                   JOIN review_periods p ON p.id = r.period_id
                  WHERE r.manager_id = ? AND r.status = 'SELF_DONE' AND p.status IN ('SELF','MANAGER')",
                [(int) $user['id']],
                0
            ),
            'training' => (new \App\Models\TrainingModel())->pendingNominationCount(),
            'contracts' => (int) Database::value(
                "SELECT COUNT(*) FROM employees
                 WHERE flag = 'ACTIVE' AND contract_end IS NOT NULL AND contract_end BETWEEN ? AND ?",
                [date('Y-m-d'), date('Y-m-d', strtotime('+45 days'))]
            ),
        ];
    }
}
