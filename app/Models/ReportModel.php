<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Settings;

/**
 * Laporan lintas modul: presensi, lembur, cuti, payroll, dan log aktivitas.
 * Semua query memakai rentang tanggal/filter yang sama agar konsisten dengan
 * data operasional (bukan salinan/agregat terjadwal).
 */
final class ReportModel
{
    public const TYPES = [
        'attendance' => 'Rekap Presensi',
        'overtime'   => 'Rekap Lembur',
        'leave'      => 'Rekap Cuti',
        'payroll'    => 'Rekap Payroll',
    ];

    public const TYPE_ICONS = [
        'attendance' => 'map-pin',
        'overtime'   => 'clock',
        'leave'      => 'calendar-check',
        'payroll'    => 'wallet',
    ];

    /* ============================================================== utilitas */

    /** @return array{0:string,1:string} tanggal awal & akhir bulan */
    public function monthBounds(string $month): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $start = $month . '-01';
        return [$start, date('Y-m-t', strtotime($start))];
    }

    public function monthLabel(string $month): string
    {
        [$year, $mon] = array_map('intval', explode('-', $month . '-01'));
        return bulan_id($mon) . ' ' . $year;
    }

    private function employeeWhere(array $filters, array &$params): string
    {
        $clauses = ["e.flag = 'ACTIVE'"];
        if (!empty($filters['unit'])) {
            $clauses[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (!empty($filters['department'])) {
            $clauses[] = 'e.department_id = ?';
            $params[] = (int) $filters['department'];
        }
        if (($filters['q'] ?? '') !== '') {
            $clauses[] = '(e.full_name LIKE ? OR e.employee_code LIKE ?)';
            $like = Database::like((string) $filters['q']);
            array_push($params, $like, $like);
        }
        return implode(' AND ', $clauses);
    }

    /* ============================================================== presensi */

    public function attendance(array $filters): array
    {
        [$start, $end] = $this->monthBounds((string) ($filters['month'] ?? date('Y-m')));
        $params = [$start, $end];
        $where = $this->employeeWhere($filters, $params);

        $rows = Database::all(
            "SELECT e.id, e.employee_code, e.full_name, bu.name AS unit_name, d.name AS department_name,
                    COUNT(a.id) AS records,
                    COALESCE(SUM(CASE WHEN a.status IN ('PRESENT','LATE') THEN 1 ELSE 0 END), 0) AS present_days,
                    COALESCE(SUM(CASE WHEN a.status = 'LATE' THEN 1 ELSE 0 END), 0) AS late_days,
                    COALESCE(SUM(a.late_minutes), 0) AS late_minutes,
                    COALESCE(SUM(CASE WHEN a.status = 'ABSENT' THEN 1 ELSE 0 END), 0) AS absent_days,
                    COALESCE(SUM(CASE WHEN a.status = 'LEAVE' THEN 1 ELSE 0 END), 0) AS leave_days,
                    COALESCE(SUM(a.work_minutes), 0) AS work_minutes,
                    COALESCE(SUM(CASE WHEN a.check_out_at IS NULL AND a.status IN ('PRESENT','LATE') THEN 1 ELSE 0 END), 0) AS missing_checkout
             FROM employees e
             LEFT JOIN attendance a ON a.employee_id = e.id AND a.work_date BETWEEN ? AND ?
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE {$where}
             GROUP BY e.id, e.employee_code, e.full_name, bu.name, d.name
             ORDER BY e.full_name",
            $params
        );

        foreach ($rows as &$row) {
            $base = (int) $row['present_days'] + (int) $row['absent_days'] + (int) $row['leave_days'];
            $row['attendance_rate'] = $base > 0 ? round((int) $row['present_days'] / $base * 100, 1) : 0.0;
            $row['work_hours'] = round((int) $row['work_minutes'] / 60, 1);
        }
        unset($row);

        return [
            'rows'   => $rows,
            'start'  => $start,
            'end'    => $end,
            'totals' => $this->sumRows($rows, [
                'present_days', 'late_days', 'late_minutes', 'absent_days', 'leave_days', 'work_hours', 'missing_checkout',
            ]),
        ];
    }

    /* ================================================================ lembur */

    public function overtime(array $filters): array
    {
        [$start, $end] = $this->monthBounds((string) ($filters['month'] ?? date('Y-m')));
        $params = [$start, $end];
        $where = $this->employeeWhere($filters, $params);

        $rows = Database::all(
            "SELECT e.id, e.employee_code, e.full_name, bu.name AS unit_name, d.name AS department_name,
                    COUNT(ot.id) AS requests,
                    COALESCE(SUM(CASE WHEN ot.status = 'APPROVED' THEN 1 ELSE 0 END), 0) AS approved,
                    COALESCE(SUM(CASE WHEN ot.status = 'PENDING' THEN 1 ELSE 0 END), 0) AS pending,
                    COALESCE(SUM(CASE WHEN ot.status = 'MANAGER_APPROVED' THEN 1 ELSE 0 END), 0) AS manager_approved,
                    COALESCE(SUM(CASE WHEN ot.status = 'REJECTED' THEN 1 ELSE 0 END), 0) AS rejected,
                    COALESCE(SUM(CASE WHEN ot.status = 'APPROVED' THEN COALESCE(ot.actual_hours, ot.planned_hours) ELSE 0 END), 0) AS approved_hours,
                    COALESCE(SUM(CASE WHEN ot.status = 'APPROVED' THEN ot.compensation_amount ELSE 0 END), 0) AS compensation
             FROM employees e
             LEFT JOIN overtime_requests ot ON ot.employee_id = e.id AND ot.work_date BETWEEN ? AND ?
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE {$where}
             GROUP BY e.id, e.employee_code, e.full_name, bu.name, d.name
             ORDER BY approved_hours DESC, e.full_name",
            $params
        );

        foreach ($rows as &$row) {
            $row['approved_hours'] = round((float) $row['approved_hours'], 2);
            $row['compensation'] = round((float) $row['compensation'], 2);
        }
        unset($row);

        $byUnit = $this->overtimeByUnit($start, $end);

        return [
            'rows'   => $rows,
            'start'  => $start,
            'end'    => $end,
            'by_unit' => $byUnit,
            'totals' => $this->sumRows($rows, ['requests', 'approved', 'pending', 'manager_approved', 'rejected', 'approved_hours', 'compensation']),
        ];
    }

    private function overtimeByUnit(string $start, string $end): array
    {
        return Database::all(
            "SELECT bu.name AS unit_name,
                    COALESCE(SUM(COALESCE(ot.actual_hours, ot.planned_hours)), 0) AS hours,
                    COALESCE(SUM(ot.compensation_amount), 0) AS compensation,
                    COUNT(*) AS requests
             FROM overtime_requests ot
             JOIN employees e ON e.id = ot.employee_id
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             WHERE ot.status = 'APPROVED' AND ot.work_date BETWEEN ? AND ?
             GROUP BY bu.name ORDER BY hours DESC",
            [$start, $end]
        );
    }

    /* ================================================================== cuti */

    public function leave(array $filters): array
    {
        $year = (int) ($filters['year'] ?? date('Y'));
        $yStart = $year . '-01-01';
        $yEnd = $year . '-12-31';

        $params = [];
        $where = $this->employeeWhere($filters, $params);
        if (!empty($filters['leave_type'])) {
            $where .= ' AND lb.leave_type_id = ?';
            $params[] = (int) $filters['leave_type'];
        }

        // Urutan placeholder: 4 tanggal pada subquery SELECT, filter karyawan, lalu tahun.
        $sqlParams = array_merge([$yStart, $yEnd, $yStart, $yEnd], $params, [$year]);

        $rows = Database::all(
            "SELECT e.id, e.employee_code, e.full_name, bu.name AS unit_name, d.name AS department_name,
                    lt.name AS leave_type, lt.code AS leave_code,
                    COALESCE(lb.quota, lt.quota_days) AS quota_days,
                    COALESCE(lb.carry, 0) AS carry_over,
                    COALESCE(lb.used, 0) AS used_days,
                    (COALESCE(lb.quota, lt.quota_days) + COALESCE(lb.carry, 0) - COALESCE(lb.used, 0)) AS remaining_days,
                    (SELECT COALESCE(SUM(lr.days), 0) FROM leave_requests lr
                     WHERE lr.employee_id = e.id AND lr.leave_type_id = lt.id
                       AND lr.status = 'APPROVED' AND lr.start_date BETWEEN ? AND ?) AS approved_days,
                    (SELECT COUNT(*) FROM leave_requests lr
                     WHERE lr.employee_id = e.id AND lr.leave_type_id = lt.id
                       AND lr.status = 'PENDING' AND lr.start_date BETWEEN ? AND ?) AS pending_requests
             FROM leave_balances lb
             JOIN employees e ON e.id = lb.employee_id
             JOIN leave_types lt ON lt.id = lb.leave_type_id
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE {$where} AND lb.period_year = ?
             ORDER BY e.full_name, lt.name",
            $sqlParams
        );

        return [
            'rows'   => $rows,
            'year'   => $year,
            'totals' => $this->sumRows($rows, ['quota_days', 'carry_over', 'used_days', 'remaining_days', 'approved_days', 'pending_requests']),
        ];
    }

    /** Ringkasan cuti per jenis untuk grafik laporan. */
    public function leaveByType(int $year, array $filters = []): array
    {
        $params = [];
        $where = $this->employeeWhere($filters, $params);
        $params[] = $year;

        return Database::all(
            "SELECT lt.name AS leave_type, COUNT(*) AS employees,
                    COALESCE(SUM(lb.used), 0) AS used_days,
                    COALESCE(SUM(COALESCE(lb.quota, lt.quota_days) + COALESCE(lb.carry, 0)), 0) AS entitlement
             FROM leave_balances lb
             JOIN employees e ON e.id = lb.employee_id
             JOIN leave_types lt ON lt.id = lb.leave_type_id
             WHERE {$where} AND lb.period_year = ?
             GROUP BY lt.name ORDER BY used_days DESC",
            $params
        );
    }

    /* =============================================================== payroll */

    public function payroll(array $filters): array
    {
        $year = (int) ($filters['year'] ?? date('Y'));
        $params = [$year, 'DRAFT'];
        $clauses = ['p.period_year = ?', 'p.status <> ?'];

        if (!empty($filters['unit'])) {
            $clauses[] = 'p.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        $where = ' WHERE ' . implode(' AND ', $clauses);

        $periods = Database::all(
            "SELECT p.*, bu.name AS unit_name, bu.code AS unit_code
             FROM payroll_periods p LEFT JOIN business_units bu ON bu.id = p.business_unit_id"
            . $where
            . ' ORDER BY p.period_year DESC, p.period_month DESC, bu.name',
            $params
        );

        $itemParams = [$year];
        $itemClauses = ['p.period_year = ?', "p.status <> 'DRAFT'"];
        if (!empty($filters['unit'])) {
            $itemClauses[] = 'p.business_unit_id = ?';
            $itemParams[] = (int) $filters['unit'];
        }
        if (!empty($filters['period'])) {
            $itemClauses[] = 'p.id = ?';
            $itemParams[] = (int) $filters['period'];
        }

        $items = Database::all(
            "SELECT pi.*, p.period_month, p.period_year, p.status AS period_status, bu.name AS unit_name
             FROM payroll_items pi
             JOIN payroll_periods p ON p.id = pi.period_id
             LEFT JOIN business_units bu ON bu.id = p.business_unit_id
             WHERE " . implode(' AND ', $itemClauses) . '
             ORDER BY p.period_year DESC, p.period_month DESC, pi.employee_name'
            . (empty($filters['period']) ? ' LIMIT 500' : ''),
            $itemParams
        );

        $byUnit = Database::all(
            "SELECT bu.name AS unit_name, COUNT(DISTINCT p.id) AS periods,
                    COALESCE(SUM(p.total_employee), 0) AS employees,
                    COALESCE(SUM(p.total_gross), 0) AS gross,
                    COALESCE(SUM(p.total_deduction), 0) AS deduction,
                    COALESCE(SUM(p.total_net), 0) AS net,
                    COALESCE(SUM(p.total_bpjs_company), 0) AS bpjs_company
             FROM payroll_periods p LEFT JOIN business_units bu ON bu.id = p.business_unit_id"
            . $where . " AND p.status = 'PAID'"
            . ' GROUP BY bu.name ORDER BY net DESC',
            $params
        );

        return [
            'periods' => $periods,
            'items'   => $items,
            'by_unit' => $byUnit,
            'year'    => $year,
            'totals'  => $this->sumRows($periods, ['total_employee', 'total_gross', 'total_deduction', 'total_net', 'total_bpjs_company']),
        ];
    }

    /* ============================================================ log aktivitas */

    public function logs(array $filters, int $limit = 40, int $offset = 0): array
    {
        [$where, $params] = $this->logWhere($filters);

        return Database::all(
            "SELECT al.*, e.full_name, e.employee_code, e.role
             FROM activity_logs al
             LEFT JOIN employees e ON e.id = al.employee_id"
            . $where
            . ' ORDER BY al.id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $params
        );
    }

    public function countLogs(array $filters): int
    {
        [$where, $params] = $this->logWhere($filters);
        return (int) Database::value('SELECT COUNT(*) FROM activity_logs al' . $where, $params);
    }

    private function logWhere(array $filters): array
    {
        $clauses = [];
        $params = [];

        if (!empty($filters['group'])) {
            $clauses[] = 'al.action LIKE ?';
            $params[] = (string) $filters['group'] . '%';
        }
        if (!empty($filters['employee'])) {
            $clauses[] = 'al.employee_id = ?';
            $params[] = (int) $filters['employee'];
        }
        if (!empty($filters['from'])) {
            $clauses[] = 'al.created_at >= ?';
            $params[] = (string) $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $clauses[] = 'al.created_at <= ?';
            $params[] = (string) $filters['to'] . ' 23:59:59';
        }
        if (($filters['q'] ?? '') !== '') {
            $like = Database::like((string) $filters['q']);
            $clauses[] = '(al.action LIKE ? OR al.entity LIKE ? OR al.meta LIKE ? OR al.ip_address LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        return [$clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    public function logGroups(): array
    {
        return [
            'login'      => 'Login & Sesi',
            'payroll'    => 'Payroll',
            'component'  => 'Komponen Gaji',
            'settings'   => 'Pengaturan',
            'employee'   => 'Data Karyawan',
            'attendance' => 'Presensi',
            'overtime'   => 'Lembur',
            'leave'      => 'Cuti',
            'schedule'   => 'Jadwal & Shift',
            'organisation' => 'Organisasi',
        ];
    }

    public function logStats(): array
    {
        return [
            'total'   => (int) Database::value('SELECT COUNT(*) FROM activity_logs'),
            'today'   => (int) Database::value('SELECT COUNT(*) FROM activity_logs WHERE created_at >= ?', [date('Y-m-d') . ' 00:00:00']),
            'users'   => (int) Database::value('SELECT COUNT(DISTINCT employee_id) FROM activity_logs WHERE employee_id IS NOT NULL'),
            'failed'  => (int) Database::value("SELECT COUNT(*) FROM activity_logs WHERE action LIKE '%failed%' OR action LIKE '%gagal%'"),
            'oldest'  => (string) (Database::value('SELECT MIN(created_at) FROM activity_logs') ?? ''),
            'top'     => Database::all(
                'SELECT action, COUNT(*) AS total FROM activity_logs GROUP BY action ORDER BY total DESC LIMIT 6'
            ),
        ];
    }

    /** Hapus log lebih tua dari N hari. @return int jumlah baris terhapus */
    public function purgeLogs(int $days): int
    {
        $days = max(30, $days);
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        return Database::delete('activity_logs', 'created_at < ?', [$cutoff]);
    }

    /* ================================================================== util */

    private function sumRows(array $rows, array $fields): array
    {
        $totals = [];
        foreach ($fields as $field) {
            $totals[$field] = 0.0;
        }
        foreach ($rows as $row) {
            foreach ($fields as $field) {
                $totals[$field] += (float) ($row[$field] ?? 0);
            }
        }
        foreach ($totals as $field => $value) {
            $totals[$field] = round($value, 2);
        }
        return $totals;
    }

    /** Nama penandatangan laporan (dari pengaturan, fallback label peran). */
    public function signers(): array
    {
        return [
            'hr'  => (string) (Settings::get('report_signer_hr') ?: 'HR Department'),
            'fin' => (string) (Settings::get('report_signer_fin') ?: 'Finance Division'),
        ];
    }
}
