<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Settings;

/**
 * Shift & schedule management.
 *
 * A schedule row is a *range* (start_date..end_date) of one shift for one
 * employee. Assigning a shift for a sub-range therefore has to split the rows it
 * overlaps — `assign()` does that, so a single day can be changed inside a
 * month-long rotation without losing the rest of the range.
 */
final class ScheduleModel
{
    public const DAY_LABELS = [
        1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min',
    ];

    /* =====================================================================
     | Shift master data
     |===================================================================*/
    public function shifts(array $filters = []): array
    {
        $sql = "SELECT s.*, bu.name AS unit_name, bu.code AS unit_code,
                       (SELECT COUNT(*) FROM schedules sc WHERE sc.shift_id = s.id AND sc.end_date >= ?) AS upcoming_assignments,
                       (SELECT COUNT(DISTINCT sc.employee_id) FROM schedules sc WHERE sc.shift_id = s.id AND sc.end_date >= ?) AS employee_count
                FROM work_shifts s
                LEFT JOIN business_units bu ON bu.id = s.business_unit_id
                WHERE 1 = 1";
        $today = date('Y-m-d');
        $params = [$today, $today];

        if (!empty($filters['unit'])) {
            $sql .= ' AND (s.business_unit_id = ? OR s.business_unit_id IS NULL)';
            $params[] = (int) $filters['unit'];
        }
        if (($filters['active'] ?? '') === '1') {
            $sql .= ' AND s.is_active = 1';
        }
        $sql .= ' ORDER BY s.is_active DESC, s.check_in_time, s.name';

        return Database::all($sql, $params);
    }

    public function findShift(int $id): ?array
    {
        return Database::one(
            'SELECT s.*, bu.name AS unit_name FROM work_shifts s
             LEFT JOIN business_units bu ON bu.id = s.business_unit_id
             WHERE s.id = ?',
            [$id]
        );
    }

    public function shiftMembers(int $shiftId, int $limit = 12): array
    {
        return Database::all(
            "SELECT DISTINCT e.id, e.full_name, e.employee_code, e.photo, e.email, bu.name AS unit_name
             FROM schedules sc
             JOIN employees e ON e.id = sc.employee_id
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             WHERE sc.shift_id = ? AND sc.end_date >= ?
             ORDER BY e.full_name LIMIT " . max(1, $limit),
            [$shiftId, date('Y-m-d')]
        );
    }

    public function shiftUsage(int $shiftId): array
    {
        return [
            'schedules' => (int) Database::value('SELECT COUNT(*) FROM schedules WHERE shift_id = ?', [$shiftId]),
            'upcoming'  => (int) Database::value('SELECT COUNT(*) FROM schedules WHERE shift_id = ? AND end_date >= ?', [$shiftId, date('Y-m-d')]),
            'requests'  => (int) Database::value('SELECT COUNT(*) FROM shift_change_requests WHERE requested_shift_id = ?', [$shiftId]),
        ];
    }

    public function shiftCodeExists(string $code, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM work_shifts WHERE UPPER(code) = ?';
        $params = [strtoupper($code)];
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (bool) Database::value($sql, $params);
    }

    public function createShift(array $data): int
    {
        return Database::insert('work_shifts', $this->shiftPayload($data) + [
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateShift(int $id, array $data): void
    {
        Database::update('work_shifts', $this->shiftPayload($data) + [
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    private function shiftPayload(array $data): array
    {
        $days = array_values(array_filter(array_map('intval', (array) ($data['work_days'] ?? [])), static fn ($d) => $d >= 1 && $d <= 7));
        sort($days);
        if ($days === []) {
            $days = [1, 2, 3, 4, 5];
        }

        return [
            'business_unit_id'       => !empty($data['business_unit_id']) ? (int) $data['business_unit_id'] : null,
            'name'                   => trim((string) ($data['name'] ?? '')),
            'code'                   => strtoupper(trim((string) ($data['code'] ?? ''))),
            'operational_type'       => strtoupper((string) ($data['operational_type'] ?? 'NORMAL')),
            'check_in_time'          => substr((string) ($data['check_in_time'] ?? '08:00'), 0, 8),
            'check_out_time'         => substr((string) ($data['check_out_time'] ?? '17:00'), 0, 8),
            'break_minutes'          => max(0, min(180, (int) ($data['break_minutes'] ?? 60))),
            'late_tolerance_minutes' => max(0, min(120, (int) ($data['late_tolerance_minutes'] ?? 15))),
            'work_days'              => implode(',', $days),
            'color'                  => $this->safeColor((string) ($data['color'] ?? '#0d9488')),
            'is_active'              => isset($data['is_active']) ? 1 : 0,
        ];
    }

    private function safeColor(string $color): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : '#0d9488';
    }

    public function deleteShift(int $id): void
    {
        Database::delete('work_shifts', 'id = ?', [$id]);
    }

    /** Working duration in hours (handles overnight shifts). */
    public static function durationHours(array $shift): float
    {
        $in = self::minutes((string) ($shift['check_in_time'] ?? '08:00'));
        $out = self::minutes((string) ($shift['check_out_time'] ?? '17:00'));
        if ($out <= $in) {
            $out += 24 * 60;
        }
        $worked = $out - $in - (int) ($shift['break_minutes'] ?? 0);
        return round(max(0, $worked) / 60, 2);
    }

    /** "08:00" → 480 */
    public static function minutes(string $time): int
    {
        $parts = explode(':', $time);
        return ((int) ($parts[0] ?? 0)) * 60 + (int) ($parts[1] ?? 0);
    }

    public static function workDayList(?string $csv): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $csv)), static fn ($d) => $d >= 1 && $d <= 7));
    }

    public static function dayLabels(?string $csv): string
    {
        $days = self::workDayList($csv);
        if (count($days) === 7) {
            return 'Setiap hari';
        }
        if ($days === [1, 2, 3, 4, 5]) {
            return 'Senin–Jumat';
        }
        if ($days === [1, 2, 3, 4, 5, 6]) {
            return 'Senin–Sabtu';
        }
        return implode(', ', array_map(static fn ($d) => self::DAY_LABELS[$d] ?? $d, $days));
    }

    /* =====================================================================
     | Schedule assignment
     |===================================================================*/
    public function assignments(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->scheduleWhere($filters);
        return Database::all(
            "SELECT sc.*, e.full_name, e.employee_code, e.photo, e.email,
                    bu.name AS unit_name, d.name AS department_name,
                    s.name AS shift_name, s.code AS shift_code, s.color AS shift_color,
                    s.check_in_time, s.check_out_time
             FROM schedules sc
             JOIN employees e ON e.id = sc.employee_id
             JOIN work_shifts s ON s.id = sc.shift_id
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE {$where}
             ORDER BY sc.start_date DESC, e.full_name
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public function countAssignments(array $filters): int
    {
        [$where, $params] = $this->scheduleWhere($filters);
        return (int) Database::value(
            "SELECT COUNT(*) FROM schedules sc
             JOIN employees e ON e.id = sc.employee_id
             WHERE {$where}",
            $params
        );
    }

    /** @return array{0:string,1:array} */
    private function scheduleWhere(array $filters): array
    {
        $clauses = ['1 = 1'];
        $params = [];

        if (!empty($filters['unit'])) {
            $clauses[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (!empty($filters['department'])) {
            $clauses[] = 'e.department_id = ?';
            $params[] = (int) $filters['department'];
        }
        if (!empty($filters['employee'])) {
            $clauses[] = 'sc.employee_id = ?';
            $params[] = (int) $filters['employee'];
        }
        if (!empty($filters['shift'])) {
            $clauses[] = 'sc.shift_id = ?';
            $params[] = (int) $filters['shift'];
        }
        if (!empty($filters['q'])) {
            $clauses[] = '(e.full_name LIKE ? OR e.employee_code LIKE ?)';
            $params[] = Database::like((string) $filters['q']);
            $params[] = Database::like((string) $filters['q']);
        }
        if (!empty($filters['from'])) {
            $clauses[] = 'sc.end_date >= ?';
            $params[] = (string) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $clauses[] = 'sc.start_date <= ?';
            $params[] = (string) $filters['to'];
        }
        if (!empty($filters['upcoming'])) {
            $clauses[] = 'sc.end_date >= ?';
            $params[] = date('Y-m-d');
        }

        return [implode(' AND ', $clauses), $params];
    }

    /**
     * Employees (with their shift per day) for a date window.
     *
     * @return array{start:string,end:string,days:array,rows:array}
     */
    public function matrix(string $start, string $end, array $filters, int $limit = 40): array
    {
        $startDate = new \DateTimeImmutable($start);
        $endDate = new \DateTimeImmutable($end);
        $days = [];
        for ($cursor = $startDate; $cursor <= $endDate; $cursor = $cursor->modify('+1 day')) {
            $days[] = $cursor->format('Y-m-d');
        }

        $employeeWhere = ["e.flag = 'ACTIVE'"];
        $params = [];
        if (!empty($filters['unit'])) {
            $employeeWhere[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (!empty($filters['department'])) {
            $employeeWhere[] = 'e.department_id = ?';
            $params[] = (int) $filters['department'];
        }
        if (!empty($filters['employee'])) {
            $employeeWhere[] = 'e.id = ?';
            $params[] = (int) $filters['employee'];
        }
        if (!empty($filters['q'])) {
            $employeeWhere[] = '(e.full_name LIKE ? OR e.employee_code LIKE ?)';
            $params[] = Database::like((string) $filters['q']);
            $params[] = Database::like((string) $filters['q']);
        }

        $employees = Database::all(
            "SELECT e.id, e.full_name, e.employee_code, e.photo, e.email,
                    bu.name AS unit_name, d.name AS department_name, p.name AS position_name
             FROM employees e
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN positions p ON p.id = e.position_id
             WHERE " . implode(' AND ', $employeeWhere) . "
             ORDER BY e.full_name
             LIMIT " . max(1, min(200, $limit)),
            $params
        );

        $ids = array_map(static fn ($e) => (int) $e['id'], $employees);
        $assignments = [];
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $rows = Database::all(
                "SELECT sc.*, s.name AS shift_name, s.code AS shift_code, s.color AS shift_color,
                        s.check_in_time, s.check_out_time, s.work_days, s.break_minutes
                 FROM schedules sc
                 JOIN work_shifts s ON s.id = sc.shift_id
                 WHERE sc.employee_id IN ({$placeholders})
                   AND sc.start_date <= ? AND sc.end_date >= ?
                 ORDER BY sc.start_date",
                array_merge($ids, [$end, $start])
            );
            foreach ($rows as $row) {
                $assignments[(int) $row['employee_id']][] = $row;
            }
        }

        $rows = [];
        foreach ($employees as $employee) {
            $cells = [];
            $byDay = [];
            foreach ($days as $day) {
                $cell = null;
                foreach ($assignments[(int) $employee['id']] ?? [] as $assignment) {
                    if ($assignment['start_date'] <= $day && $assignment['end_date'] >= $day) {
                        $cell = $assignment;
                        break;
                    }
                }
                $cells[$day] = $cell;
                if ($cell) {
                    $byDay[$cell['shift_id']] = $cell;
                }
            }
            $rows[] = [
                'employee'  => $employee,
                'cells'     => $cells,
                'shift_ids' => array_keys($byDay),
            ];
        }

        return ['start' => $startDate->format('Y-m-d'), 'end' => $endDate->format('Y-m-d'), 'days' => $days, 'rows' => $rows];
    }

    /**
     * Assign a shift for a date window, splitting any overlapping range so the
     * rest of an existing rotation is preserved.
     */
    public function assign(
        int $employeeId,
        int $shiftId,
        string $startDate,
        string $endDate,
        string $source = 'ASSIGNED',
        ?string $note = null,
        ?int $createdBy = null,
        bool $skipWorkingDays = false
    ): array {
        $shift = $this->findShift($shiftId);
        if (!$shift) {
            return ['created' => 0, 'skipped' => 0, 'error' => 'Shift tidak ditemukan.'];
        }
        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $windows = $this->splitByWorkingDays($startDate, $endDate, (string) $shift['work_days'], $skipWorkingDays);
        if ($windows === []) {
            return ['created' => 0, 'skipped' => 0, 'error' => 'Rentang tanggal tidak memiliki hari kerja pada shift ini.'];
        }

        $created = 0;
        Database::transaction(function () use ($employeeId, $shiftId, $source, $note, $createdBy, $windows, &$created) {
            foreach ($windows as [$from, $to]) {
                $this->clearWindow($employeeId, $from, $to);
                Database::insert('schedules', [
                    'employee_id' => $employeeId,
                    'shift_id'    => $shiftId,
                    'start_date'  => $from,
                    'end_date'    => $to,
                    'source'      => $source,
                    'note'        => $note,
                    'created_by'  => $createdBy,
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
                $created++;
            }
        });

        return ['created' => $created, 'skipped' => 0, 'error' => null];
    }

    /**
     * Remove every schedule slice inside the window (trimming the edges of
     * ranges that stick out on either side).
     */
    private function clearWindow(int $employeeId, string $from, string $to): void
    {
        $overlaps = Database::all(
            'SELECT * FROM schedules WHERE employee_id = ? AND start_date <= ? AND end_date >= ? ORDER BY start_date',
            [$employeeId, $to, $from]
        );

        foreach ($overlaps as $row) {
            $rowStart = (string) $row['start_date'];
            $rowEnd = (string) $row['end_date'];

            if ($rowStart >= $from && $rowEnd <= $to) {
                Database::delete('schedules', 'id = ?', [(int) $row['id']]);
                continue;
            }

            if ($rowStart < $from && $rowEnd > $to) {
                // window sits in the middle: keep left + right fragments
                Database::update('schedules', ['end_date' => date('Y-m-d', strtotime($from . ' -1 day')), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $row['id']]);
                Database::insert('schedules', [
                    'employee_id' => $employeeId,
                    'shift_id'    => (int) $row['shift_id'],
                    'start_date'  => date('Y-m-d', strtotime($to . ' +1 day')),
                    'end_date'    => $rowEnd,
                    'source'      => $row['source'],
                    'note'        => $row['note'],
                    'created_by'  => $row['created_by'],
                    'created_at'  => $row['created_at'] ?: date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
                continue;
            }

            if ($rowStart < $from) {
                Database::update('schedules', ['end_date' => date('Y-m-d', strtotime($from . ' -1 day')), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $row['id']]);
                continue;
            }

            Database::update('schedules', ['start_date' => date('Y-m-d', strtotime($to . ' +1 day')), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $row['id']]);
        }
    }

    /**
     * Split a date range into contiguous slices that only contain the shift's
     * working weekdays (FRI/SAT/SUN aware). One slice per contiguous run.
     *
     * @return array<int,array{0:string,1:string}>
     */
    private function splitByWorkingDays(string $from, string $to, string $workDaysCsv, bool $skip): array
    {
        if (!$skip) {
            return [[$from, $to]];
        }

        $allowed = self::workDayList($workDaysCsv);
        $windows = [];
        $cursor = new \DateTimeImmutable($from);
        $end = new \DateTimeImmutable($to);
        $runStart = null;
        $previous = null;

        while ($cursor <= $end) {
            $dow = (int) $cursor->format('N');
            if (in_array($dow, $allowed, true)) {
                if ($runStart === null) {
                    $runStart = $cursor->format('Y-m-d');
                }
                $previous = $cursor->format('Y-m-d');
            } elseif ($runStart !== null) {
                $windows[] = [$runStart, (string) $previous];
                $runStart = null;
            }
            $cursor = $cursor->modify('+1 day');
        }
        if ($runStart !== null) {
            $windows[] = [$runStart, (string) $previous];
        }
        return $windows;
    }

    public function removeAssignment(int $id): void
    {
        Database::delete('schedules', 'id = ?', [$id]);
    }

    /** Assignments of one employee overlapping a window (used by self service). */
    public function employeeSchedule(int $employeeId, string $from, string $to): array
    {
        return Database::all(
            'SELECT sc.*, s.name AS shift_name, s.code AS shift_code, s.color AS shift_color,
                    s.check_in_time, s.check_out_time, s.break_minutes, s.work_days, s.operational_type
             FROM schedules sc
             JOIN work_shifts s ON s.id = sc.shift_id
             WHERE sc.employee_id = ? AND sc.start_date <= ? AND sc.end_date >= ?
             ORDER BY sc.start_date',
            [$employeeId, $to, $from]
        );
    }

    /** Shift of one employee on a given day (for attendance/check-in). */
    public function shiftOn(int $employeeId, string $date): ?array
    {
        return Database::one(
            'SELECT sc.*, s.name AS shift_name, s.code AS shift_code, s.color AS shift_color,
                    s.check_in_time, s.check_out_time, s.break_minutes, s.late_tolerance_minutes,
                    s.operational_type, s.work_days
             FROM schedules sc
             JOIN work_shifts s ON s.id = sc.shift_id
             WHERE sc.employee_id = ? AND sc.start_date <= ? AND sc.end_date >= ?
             ORDER BY sc.start_date DESC LIMIT 1',
            [$employeeId, $date, $date]
        );
    }

    /** Expand a window into one row per day with the resolved shift. */
    public function dayGrid(int $employeeId, string $from, string $to): array
    {
        $assignments = $this->employeeSchedule($employeeId, $from, $to);
        $days = [];
        $cursor = new \DateTimeImmutable($from);
        $end = new \DateTimeImmutable($to);
        while ($cursor <= $end) {
            $date = $cursor->format('Y-m-d');
            $match = null;
            foreach ($assignments as $assignment) {
                if ($assignment['start_date'] <= $date && $assignment['end_date'] >= $date) {
                    $match = $assignment;
                    break;
                }
            }
            $days[] = [
                'date'     => $date,
                'dow'      => (int) $cursor->format('N'),
                'weekend'  => (int) $cursor->format('N') >= 6,
                'today'    => $date === date('Y-m-d'),
                'shift'    => $match,
                'is_work'  => $match !== null && in_array((int) $cursor->format('N'), self::workDayList($match['work_days'] ?? ''), true),
            ];
            $cursor = $cursor->modify('+1 day');
        }
        return $days;
    }

    /* =====================================================================
     | Shift change requests
     |===================================================================*/
    public function requests(array $filters, int $limit = 50, int $offset = 0): array
    {
        $clauses = ['1 = 1'];
        $params = [];

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'OPEN') {
                $clauses[] = "r.status IN ('PENDING', 'MANAGER_APPROVED')";
            } else {
                $clauses[] = 'r.status = ?';
                $params[] = $filters['status'];
            }
        }
        if (!empty($filters['employee'])) {
            $clauses[] = 'r.employee_id = ?';
            $params[] = (int) $filters['employee'];
        }
        if (!empty($filters['unit'])) {
            $clauses[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (!empty($filters['q'])) {
            $clauses[] = '(e.full_name LIKE ? OR e.employee_code LIKE ?)';
            $params[] = Database::like((string) $filters['q']);
            $params[] = Database::like((string) $filters['q']);
        }

        $where = implode(' AND ', $clauses);

        return Database::all(
            "SELECT r.*, e.full_name, e.employee_code, e.photo, e.email, e.direct_manager_id,
                    bu.name AS unit_name, d.name AS department_name,
                    cs.name AS current_shift_name, cs.check_in_time AS current_check_in, cs.check_out_time AS current_check_out,
                    rs.name AS requested_shift_name, rs.color AS requested_color, rs.check_in_time AS requested_check_in, rs.check_out_time AS requested_check_out,
                    m.full_name AS manager_name
             FROM shift_change_requests r
             JOIN employees e ON e.id = r.employee_id
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN work_shifts cs ON cs.id = r.current_shift_id
             LEFT JOIN work_shifts rs ON rs.id = r.requested_shift_id
             LEFT JOIN employees m ON m.id = r.manager_id
             WHERE {$where}
             ORDER BY CASE WHEN r.status IN ('PENDING','MANAGER_APPROVED') THEN 0 ELSE 1 END, r.start_date DESC, r.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public function countRequests(array $filters): int
    {
        $clauses = ['1 = 1'];
        $params = [];
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'OPEN') {
                $clauses[] = "r.status IN ('PENDING', 'MANAGER_APPROVED')";
            } else {
                $clauses[] = 'r.status = ?';
                $params[] = $filters['status'];
            }
        }
        if (!empty($filters['employee'])) {
            $clauses[] = 'r.employee_id = ?';
            $params[] = (int) $filters['employee'];
        }
        if (!empty($filters['unit'])) {
            $clauses[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (!empty($filters['q'])) {
            $clauses[] = '(e.full_name LIKE ? OR e.employee_code LIKE ?)';
            $params[] = Database::like((string) $filters['q']);
            $params[] = Database::like((string) $filters['q']);
        }
        return (int) Database::value(
            'SELECT COUNT(*) FROM shift_change_requests r JOIN employees e ON e.id = r.employee_id WHERE ' . implode(' AND ', $clauses),
            $params
        );
    }

    public function requestStats(): array
    {
        return [
            'pending'         => (int) Database::value("SELECT COUNT(*) FROM shift_change_requests WHERE status = 'PENDING'"),
            'manager'         => (int) Database::value("SELECT COUNT(*) FROM shift_change_requests WHERE status = 'PENDING'"),
            'awaiting_hr'     => (int) Database::value("SELECT COUNT(*) FROM shift_change_requests WHERE status = 'MANAGER_APPROVED'"),
            'approved'        => (int) Database::value("SELECT COUNT(*) FROM shift_change_requests WHERE status = 'APPROVED'"),
            'rejected'        => (int) Database::value("SELECT COUNT(*) FROM shift_change_requests WHERE status = 'REJECTED'"),
        ];
    }

    public function myRequests(int $employeeId, int $limit = 10): array
    {
        return Database::all(
            "SELECT r.*, cs.name AS current_shift_name, cs.code AS current_shift_code,
                    rs.name AS requested_shift_name, rs.code AS requested_shift_code,
                    rs.check_in_time AS requested_start, rs.check_out_time AS requested_end,
                    m.full_name AS manager_name, h.full_name AS hr_name
             FROM shift_change_requests r
             LEFT JOIN work_shifts cs ON cs.id = r.current_shift_id
             LEFT JOIN work_shifts rs ON rs.id = r.requested_shift_id
             LEFT JOIN employees m ON m.id = r.manager_id
             LEFT JOIN employees h ON h.id = r.hr_id
             WHERE r.employee_id = ?
             ORDER BY r.id DESC LIMIT " . max(1, $limit),
            [$employeeId]
        );
    }

    /** Reject overlapping open requests of the same employee. */
    public function hasOpenRequest(int $employeeId): bool
    {
        return (bool) Database::value(
            "SELECT id FROM shift_change_requests WHERE employee_id = ? AND status IN ('PENDING','MANAGER_APPROVED') LIMIT 1",
            [$employeeId]
        );
    }

    public function createRequest(int $employeeId, int $shiftId, string $from, string $to, string $reason): int
    {
        $current = $this->shiftOn($employeeId, $from);
        return Database::insert('shift_change_requests', [
            'employee_id'         => $employeeId,
            'current_schedule_id' => $current['id'] ?? null,
            'current_shift_id'    => $current['shift_id'] ?? null,
            'requested_shift_id'  => $shiftId,
            'start_date'          => $from,
            'end_date'            => $to,
            'reason'              => $reason !== '' ? substr($reason, 0, 255) : null,
            'status'              => 'PENDING',
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);
    }

    /* =====================================================================
     | Statistics
     |===================================================================*/
    public function stats(): array
    {
        $today = date('Y-m-d');
        $weekEnd = date('Y-m-d', strtotime('+6 days'));

        return [
            'shifts'      => (int) Database::value('SELECT COUNT(*) FROM work_shifts WHERE is_active = 1'),
            'scheduled'   => (int) Database::value(
                'SELECT COUNT(DISTINCT employee_id) FROM schedules WHERE start_date <= ? AND end_date >= ?',
                [$weekEnd, $today]
            ),
            'active_now'  => (int) Database::value(
                "SELECT COUNT(*) FROM employees WHERE flag = 'ACTIVE'"
            ),
            'assignments' => (int) Database::value('SELECT COUNT(*) FROM schedules WHERE end_date >= ?', [$today]),
            'unscheduled' => (int) Database::value(
                "SELECT COUNT(*) FROM employees e WHERE e.flag = 'ACTIVE' AND NOT EXISTS (
                    SELECT 1 FROM schedules sc WHERE sc.employee_id = e.id AND sc.start_date <= ? AND sc.end_date >= ?
                )",
                [$weekEnd, $today]
            ),
            'pending'     => (int) Database::value(
                "SELECT COUNT(*) FROM shift_change_requests WHERE status IN ('PENDING','MANAGER_APPROVED')"
            ),
            'coverage'    => $this->coverage($today, $weekEnd),
        ];
    }

    /** Per-unit headcount vs scheduled employees for the coming week. */
    public function coverage(string $from, string $to, int $limit = 6): array
    {
        return Database::all(
            "SELECT bu.id, bu.name, bu.code,
                    (SELECT COUNT(*) FROM employees e WHERE e.business_unit_id = bu.id AND e.flag = 'ACTIVE') AS headcount,
                    (SELECT COUNT(DISTINCT sc.employee_id) FROM schedules sc
                        JOIN employees e2 ON e2.id = sc.employee_id
                        WHERE e2.business_unit_id = bu.id AND sc.start_date <= ? AND sc.end_date >= ?) AS scheduled
             FROM business_units bu
             WHERE bu.is_active = 1
             ORDER BY bu.id
             LIMIT " . max(1, $limit),
            [$to, $from]
        );
    }

    public function shiftOptions(): array
    {
        return Database::all('SELECT id, name, code, color, check_in_time, check_out_time, work_days FROM work_shifts WHERE is_active = 1 ORDER BY check_in_time');
    }

    public function employeeOptions(array $filters = []): array
    {
        $where = ["e.flag = 'ACTIVE'"];
        $params = [];
        if (!empty($filters['unit'])) {
            $where[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (!empty($filters['department'])) {
            $where[] = 'e.department_id = ?';
            $params[] = (int) $filters['department'];
        }
        return Database::all(
            'SELECT e.id, e.full_name, e.employee_code, e.photo, e.business_unit_id, e.department_id,
                    bu.name AS unit_name, d.name AS department_name
             FROM employees e
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY e.full_name',
            $params
        );
    }

    /** Suggested default shift for a unit (first active shift). */
    public function defaultShiftId(): ?int
    {
        $id = Database::value('SELECT id FROM work_shifts WHERE is_active = 1 ORDER BY check_in_time LIMIT 1');
        return $id ? (int) $id : null;
    }

    /** Setting driven default late tolerance for new shifts. */
    public function defaultTolerance(): int
    {
        return (int) (Settings::get('attendance_late_tolerance', 15) ?: 15);
    }
}
