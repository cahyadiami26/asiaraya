<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Settings;

/**
 * Cuti (leave): master jenis & jatah, saldo per karyawan/tahun, pengajuan,
 * validasi hari kerja, serta efek persetujuan final (saldo + presensi).
 *
 * Aturan hari kerja: Sabtu/Minggu dan tanggal pada tabel `holidays` tidak
 * dihitung sebagai hari cuti. Dengan begitu pengajuan Jumat–Senin bernilai
 * 2 hari, bukan 4 hari.
 */
final class LeaveModel
{
    public const STATUS_LABELS = [
        'PENDING'          => 'Menunggu Atasan',
        'MANAGER_APPROVED' => 'Menunggu HR',
        'APPROVED'         => 'Disetujui',
        'REJECTED'         => 'Ditolak',
        'CANCELLED'        => 'Dibatalkan',
    ];

    public const STATUS_OPEN = ['PENDING', 'MANAGER_APPROVED'];

    /** Presensi yang dibuat sistem saat cuti disetujui final. */
    private const ATTENDANCE_SOURCE = 'LEAVE';

    /* ------------------------------------------------------------------- policy */

    public function settings(): array
    {
        return [
            'default_quota'  => (float) (Settings::get('leave_default_quota') ?: 12),
            'allow_negative' => Settings::bool('leave_allow_negative', false),
            'max_range_days' => (int) (Settings::get('leave_max_range_days') ?: 90),
        ];
    }

    /** Nama hari besar untuk tanggal tertentu (null bila hari kerja biasa). */
    public function holiday(string $date): ?string
    {
        $name = Database::value('SELECT name FROM holidays WHERE holiday_date = ?', [$date]);
        return $name !== null ? (string) $name : null;
    }

    /* -------------------------------------------------------------------- types */

    /** @return array<int, array<string, mixed>> */
    public function types(bool $activeOnly = false): array
    {
        $sql = "SELECT lt.*,
                       (SELECT COUNT(*) FROM leave_requests lr WHERE lr.leave_type_id = lt.id) AS request_count,
                       (SELECT COUNT(*) FROM leave_requests lr WHERE lr.leave_type_id = lt.id AND lr.status = 'APPROVED') AS approved_count,
                       (SELECT COUNT(*) FROM leave_balances lb WHERE lb.leave_type_id = lt.id) AS balance_count,
                       (SELECT COALESCE(SUM(lb.used), 0) FROM leave_balances lb WHERE lb.leave_type_id = lt.id) AS used_total
                FROM leave_types lt";
        if ($activeOnly) {
            $sql .= ' WHERE lt.is_active = 1';
        }
        $sql .= ' ORDER BY lt.is_active DESC, lt.id';
        return Database::all($sql);
    }

    public function findType(int $id): ?array
    {
        return Database::one('SELECT * FROM leave_types WHERE id = ?', [$id]);
    }

    public function typeCodeExists(string $code, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM leave_types WHERE UPPER(code) = UPPER(?)';
        $params = [$code];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int) Database::value($sql, $params) > 0;
    }

    public function createType(array $data): int
    {
        return Database::insert('leave_types', $data + [
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateType(int $id, array $data): void
    {
        Database::update('leave_types', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    /** Guard: jenis cuti yang sudah dipakai tidak boleh dihapus. */
    public function typeUsage(int $id): array
    {
        return [
            'requests' => (int) Database::value('SELECT COUNT(*) FROM leave_requests WHERE leave_type_id = ?', [$id]),
            'balances' => (int) Database::value('SELECT COUNT(*) FROM leave_balances WHERE leave_type_id = ?', [$id]),
        ];
    }

    public function deleteType(int $id): void
    {
        Database::delete('leave_types', 'id = ?', [$id]);
    }

    /* ----------------------------------------------------------------- balances */

    public function yearOptions(): array
    {
        $first = (int) Database::value('SELECT MIN(period_year) FROM leave_balances');
        $current = (int) date('Y');
        $start = $first > 0 ? min($first, $current) : $current;
        $years = range($current + 1, $start);
        return array_values(array_unique($years));
    }

    private function balanceWhere(array $filters, array &$params): string
    {
        $clauses = [];

        if (!empty($filters['year'])) {
            $clauses[] = 'lb.period_year = ?';
            $params[] = (int) $filters['year'];
        }
        if (!empty($filters['type'])) {
            $clauses[] = 'lb.leave_type_id = ?';
            $params[] = (int) $filters['type'];
        }
        if (!empty($filters['unit'])) {
            $clauses[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (!empty($filters['department'])) {
            $clauses[] = 'e.department_id = ?';
            $params[] = (int) $filters['department'];
        }
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . $filters['q'] . '%';
            $clauses[] = '(e.full_name LIKE ? OR e.employee_code LIKE ?)';
            array_push($params, $like, $like);
        }
        if (($filters['status'] ?? '') === 'LOW') {
            $clauses[] = '(lb.quota + lb.carry - lb.used) <= 2';
        }
        if (($filters['status'] ?? '') === 'NEGATIVE') {
            $clauses[] = '(lb.quota + lb.carry - lb.used) < 0';
        }

        return $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';
    }

    public function balances(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $params = [];
        $sql = "SELECT lb.*, lt.name AS type_name, lt.code AS type_code, lt.color AS type_color, lt.is_paid,
                       e.full_name, e.employee_code, e.email, e.photo, e.flag,
                       bu.name AS unit_name, d.name AS department_name
                FROM leave_balances lb
                JOIN employees e ON e.id = lb.employee_id
                JOIN leave_types lt ON lt.id = lb.leave_type_id
                LEFT JOIN business_units bu ON bu.id = e.business_unit_id
                LEFT JOIN departments d ON d.id = e.department_id"
            . $this->balanceWhere($filters, $params)
            . ' ORDER BY e.full_name, lt.id LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
        return Database::all($sql, $params);
    }

    public function countBalances(array $filters = []): int
    {
        $params = [];
        $sql = 'SELECT COUNT(*) FROM leave_balances lb
                JOIN employees e ON e.id = lb.employee_id
                JOIN leave_types lt ON lt.id = lb.leave_type_id'
            . $this->balanceWhere($filters, $params);
        return (int) Database::value($sql, $params);
    }

    /** Ringkasan saldo per jenis untuk satu tahun (kartu KPI). */
    public function balanceStats(int $year, array $filters = []): array
    {
        $filters['year'] = $year;
        $params = [];
        $row = Database::one(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(lb.quota + lb.carry), 0) AS quota,
                    COALESCE(SUM(lb.used), 0) AS used,
                    COALESCE(SUM(CASE WHEN (lb.quota + lb.carry - lb.used) <= 2 THEN 1 ELSE 0 END), 0) AS low,
                    COALESCE(SUM(CASE WHEN (lb.quota + lb.carry - lb.used) < 0 THEN 1 ELSE 0 END), 0) AS negative
             FROM leave_balances lb
             JOIN employees e ON e.id = lb.employee_id
             JOIN leave_types lt ON lt.id = lb.leave_type_id'
            . $this->balanceWhere($filters, $params),
            $params
        ) ?? [];

        return [
            'records'  => (int) ($row['total'] ?? 0),
            'quota'    => (float) ($row['quota'] ?? 0),
            'used'     => (float) ($row['used'] ?? 0),
            'low'      => (int) ($row['low'] ?? 0),
            'negative' => (int) ($row['negative'] ?? 0),
        ];
    }

    public function balanceFor(int $employeeId, int $typeId, int $year): ?array
    {
        return Database::one(
            'SELECT * FROM leave_balances WHERE employee_id = ? AND leave_type_id = ? AND period_year = ?',
            [$employeeId, $typeId, $year]
        );
    }

    public function available(int $employeeId, int $typeId, int $year): float
    {
        $balance = $this->balanceFor($employeeId, $typeId, $year);
        if (!$balance) {
            return 0.0;
        }
        return round((float) $balance['quota'] + (float) $balance['carry'] - (float) $balance['used'], 1);
    }

    /** Buat baris saldo bila belum ada (quota diambil dari jenis atau default). */
    public function ensureBalance(int $employeeId, int $typeId, int $year, ?float $quota = null): array
    {
        $existing = $this->balanceFor($employeeId, $typeId, $year);
        if ($existing) {
            return $existing;
        }

        $type = $this->findType($typeId);
        $quota ??= (float) ($type['quota_days'] ?? $this->settings()['default_quota']);

        $id = Database::insert('leave_balances', [
            'employee_id'   => $employeeId,
            'leave_type_id' => $typeId,
            'period_year'   => $year,
            'quota'         => $quota,
            'used'          => 0,
            'carry'         => 0,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        return $this->balanceFor($employeeId, $typeId, $year)
            ?? ['id' => $id, 'employee_id' => $employeeId, 'leave_type_id' => $typeId, 'period_year' => $year, 'quota' => $quota, 'used' => 0, 'carry' => 0];
    }

    public function setBalance(int $employeeId, int $typeId, int $year, float $quota, float $carry = 0): void
    {
        $existing = $this->ensureBalance($employeeId, $typeId, $year, $quota);
        Database::update('leave_balances', [
            'quota'      => $quota,
            'carry'      => $carry,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int) $existing['id']]);
    }

    /**
     * Buat saldo untuk semua karyawan aktif × jenis cuti aktif pada satu tahun.
     * Baris yang sudah ada dilewati sehingga aman dijalankan ulang.
     *
     * @return array{created:int, skipped:int, employees:int, types:int}
     */
    public function generateYear(int $year, ?int $unitId = null, bool $overwriteQuota = false): array
    {
        $types = Database::all('SELECT * FROM leave_types WHERE is_active = 1 ORDER BY id');
        $sql = "SELECT id FROM employees WHERE flag = 'ACTIVE'";
        $params = [];
        if ($unitId) {
            $sql .= ' AND business_unit_id = ?';
            $params[] = $unitId;
        }
        $employees = Database::all($sql, $params);

        $created = 0;
        $skipped = 0;
        foreach ($employees as $employee) {
            foreach ($types as $type) {
                $existing = $this->balanceFor((int) $employee['id'], (int) $type['id'], $year);
                if ($existing) {
                    if ($overwriteQuota) {
                        Database::update('leave_balances', [
                            'quota'      => (float) $type['quota_days'],
                            'updated_at' => date('Y-m-d H:i:s'),
                        ], 'id = ?', [(int) $existing['id']]);
                    }
                    $skipped++;
                    continue;
                }
                $this->ensureBalance((int) $employee['id'], (int) $type['id'], $year, (float) $type['quota_days']);
                $created++;
            }
        }

        return [
            'created'   => $created,
            'skipped'   => $skipped,
            'employees' => count($employees),
            'types'     => count($types),
        ];
    }

    /** Total saldo per jenis untuk satu karyawan (halaman "Cuti Saya"). */
    public function myBalances(int $employeeId, int $year): array
    {
        $rows = Database::all(
            "SELECT lt.id, lt.name, lt.code, lt.color, lt.is_paid, lt.quota_days, lt.requires_attachment, lt.max_per_request,
                    lt.min_notice_days, lt.deduct_salary,
                    COALESCE(lb.quota, lt.quota_days) AS quota, COALESCE(lb.used, 0) AS used, COALESCE(lb.carry, 0) AS carry,
                    (COALESCE(lb.quota, lt.quota_days) + COALESCE(lb.carry, 0) - COALESCE(lb.used, 0)) AS remaining,
                    lb.id AS balance_id
             FROM leave_types lt
             LEFT JOIN leave_balances lb
                    ON lb.leave_type_id = lt.id AND lb.employee_id = ? AND lb.period_year = ?
             WHERE lt.is_active = 1
             ORDER BY lt.id",
            [$employeeId, $year]
        );

        foreach ($rows as &$row) {
            $row['quota'] = (float) $row['quota'];
            $row['used'] = (float) $row['used'];
            $row['carry'] = (float) $row['carry'];
            $row['remaining'] = round((float) $row['remaining'], 1);
            $row['unlimited'] = (float) $row['quota_days'] === 0.0;
            $row['progress'] = $row['quota'] > 0
                ? max(0, min(100, $row['remaining'] / $row['quota'] * 100))
                : 100;
        }
        unset($row);

        return $rows;
    }

    /* ---------------------------------------------------------------- requests */

    private function requestSelect(): string
    {
        return "SELECT lr.*, lt.name AS type_name, lt.code AS type_code, lt.color AS type_color,
                       lt.is_paid, lt.deduct_salary, lt.requires_attachment,
                       e.full_name, e.employee_code, e.email, e.photo, e.role,
                       bu.name AS unit_name, bu.code AS unit_code, d.name AS department_name,
                       m.full_name AS manager_name, h.full_name AS hr_name
                FROM leave_requests lr
                JOIN leave_types lt ON lt.id = lr.leave_type_id
                JOIN employees e ON e.id = lr.employee_id
                LEFT JOIN business_units bu ON bu.id = e.business_unit_id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN employees m ON m.id = lr.manager_id
                LEFT JOIN employees h ON h.id = lr.hr_id";
    }

    private function requestWhere(array $filters, array &$params): string
    {
        $clauses = [];

        $status = strtoupper((string) ($filters['status'] ?? ''));
        if ($status === 'OPEN') {
            $clauses[] = "lr.status IN ('PENDING','MANAGER_APPROVED')";
        } elseif ($status !== '' && $status !== 'ALL' && isset(self::STATUS_LABELS[$status])) {
            $clauses[] = 'lr.status = ?';
            $params[] = $status;
        }

        if (!empty($filters['type'])) {
            $clauses[] = 'lr.leave_type_id = ?';
            $params[] = (int) $filters['type'];
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
            $clauses[] = 'lr.employee_id = ?';
            $params[] = (int) $filters['employee'];
        }
        // Atasan langsung: hanya pengajuan anggota timnya sendiri.
        if (!empty($filters['manager'])) {
            $clauses[] = 'e.direct_manager_id = ?';
            $params[] = (int) $filters['manager'];
        }
        if (!empty($filters['from'])) {
            $clauses[] = 'lr.end_date >= ?';
            $params[] = (string) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $clauses[] = 'lr.start_date <= ?';
            $params[] = (string) $filters['to'];
        }
        if (!empty($filters['month'])) {
            [$start, $end] = $this->monthBounds((string) $filters['month']);
            $clauses[] = 'lr.start_date <= ? AND lr.end_date >= ?';
            $params[] = $end;
            $params[] = $start;
        }
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . $filters['q'] . '%';
            $clauses[] = '(e.full_name LIKE ? OR e.employee_code LIKE ? OR lr.reason LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        return $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';
    }

    public function requests(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $params = [];
        $sql = $this->requestSelect() . $this->requestWhere($filters, $params)
            . ' ORDER BY lr.status IN (\'PENDING\',\'MANAGER_APPROVED\') DESC, lr.start_date ASC, lr.id DESC'
            . ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
        return array_map([$this, 'decorate'], Database::all($sql, $params));
    }

    public function countRequests(array $filters = []): int
    {
        $params = [];
        $sql = 'SELECT COUNT(*) FROM leave_requests lr
                JOIN leave_types lt ON lt.id = lr.leave_type_id
                JOIN employees e ON e.id = lr.employee_id'
            . $this->requestWhere($filters, $params);
        return (int) Database::value($sql, $params);
    }

    public function find(int $id): ?array
    {
        $row = Database::one($this->requestSelect() . ' WHERE lr.id = ?', [$id]);
        return $row ? $this->decorate($row) : null;
    }

    private function decorate(array $row): array
    {
        $row['days'] = (float) $row['days'];
        $row['work_dates'] = $this->workingDays((string) $row['start_date'], (string) $row['end_date']);
        $row['range_days'] = count($row['work_dates']);
        $row['year'] = (int) date('Y', strtotime((string) $row['start_date']));
        $row['holiday_note'] = array_values(array_filter(array_map(
            fn (string $date) => $this->holiday($date),
            $this->calendarDates((string) $row['start_date'], (string) $row['end_date'])
        )));
        return $row;
    }

    public function stats(array $filters = []): array
    {
        $base = $filters;
        unset($base['status']);

        $counts = ['PENDING' => 0, 'MANAGER_APPROVED' => 0, 'APPROVED' => 0, 'REJECTED' => 0, 'CANCELLED' => 0];
        $params = [];
        $sql = 'SELECT lr.status, COUNT(*) AS total FROM leave_requests lr
                JOIN leave_types lt ON lt.id = lr.leave_type_id
                JOIN employees e ON e.id = lr.employee_id'
            . $this->requestWhere($base, $params) . ' GROUP BY lr.status';
        foreach (Database::all($sql, $params) as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        $sumParams = [];
        $sum = Database::one(
            "SELECT COALESCE(SUM(CASE WHEN lr.status = 'APPROVED' THEN lr.days ELSE 0 END), 0) AS approved_days,
                    COUNT(DISTINCT CASE WHEN lr.status = 'APPROVED' THEN lr.employee_id END) AS people,
                    COALESCE(SUM(CASE WHEN lr.leave_type_id IN (SELECT id FROM leave_types WHERE is_paid = 0) AND lr.status = 'APPROVED' THEN lr.days ELSE 0 END), 0) AS unpaid_days
             FROM leave_requests lr
             JOIN leave_types lt ON lt.id = lr.leave_type_id
             JOIN employees e ON e.id = lr.employee_id"
            . $this->requestWhere($base, $sumParams),
            $sumParams
        ) ?? [];

        return [
            'counts'       => $counts,
            'pending'      => $counts['PENDING'],
            'awaiting_hr'  => $counts['MANAGER_APPROVED'],
            'open'         => $counts['PENDING'] + $counts['MANAGER_APPROVED'],
            'approved'     => $counts['APPROVED'],
            'rejected'     => $counts['REJECTED'],
            'cancelled'    => $counts['CANCELLED'],
            'total'        => array_sum($counts),
            'approved_days' => (float) ($sum['approved_days'] ?? 0),
            'people'       => (int) ($sum['people'] ?? 0),
            'unpaid_days'  => (float) ($sum['unpaid_days'] ?? 0),
        ];
    }

    /** Cuti yang sedang/akan berjalan pada rentang tanggal (papan HR). */
    public function upcoming(int $limit = 8): array
    {
        $rows = Database::all(
            $this->requestSelect() . " WHERE lr.status = 'APPROVED' AND lr.end_date >= ?
              ORDER BY lr.start_date ASC LIMIT " . max(1, $limit),
            [date('Y-m-d')]
        );
        return array_map([$this, 'decorate'], $rows);
    }

    /** Jumlah pengajuan cuti per jenis pada satu tahun (grafik). */
    public function byType(int $year): array
    {
        return Database::all(
            "SELECT lt.id, lt.name, lt.code, lt.color, lt.quota_days,
                    COUNT(lr.id) AS requests,
                    COALESCE(SUM(CASE WHEN lr.status = 'APPROVED' THEN lr.days ELSE 0 END), 0) AS days
             FROM leave_types lt
             LEFT JOIN leave_requests lr
                    ON lr.leave_type_id = lt.id
                   AND lr.start_date BETWEEN ? AND ?
                   AND lr.status NOT IN ('REJECTED','CANCELLED')
             GROUP BY lt.id, lt.name, lt.code, lt.color, lt.quota_days
             ORDER BY days DESC, lt.id",
            [$year . '-01-01', $year . '-12-31']
        );
    }

    /* ------------------------------------------------------- working day helpers */

    /** Semua tanggal pada rentang (inklusif). */
    public function calendarDates(string $start, string $end): array
    {
        $from = strtotime($start);
        $to = strtotime($end);
        if ($from === false || $to === false || $to < $from) {
            return [];
        }
        $dates = [];
        $cursor = $from;
        $guard = 0;
        while ($cursor <= $to && $guard++ < 400) {
            $dates[] = date('Y-m-d', $cursor);
            $cursor = strtotime('+1 day', $cursor);
        }
        return $dates;
    }

    /** Tanggal yang benar-benar dihitung sebagai cuti (tanpa akhir pekan & hari besar). */
    public function workingDays(string $start, string $end): array
    {
        $holidays = $this->holidayMap($start, $end);
        $days = [];
        foreach ($this->calendarDates($start, $end) as $date) {
            $dow = (int) date('N', strtotime($date));
            if ($dow >= 6 || isset($holidays[$date])) {
                continue;
            }
            $days[] = $date;
        }
        return $days;
    }

    public function countWorkingDays(string $start, string $end): int
    {
        return count($this->workingDays($start, $end));
    }

    /** @return array<string, string> peta tanggal → nama hari besar */
    private function holidayMap(string $start, string $end): array
    {
        $rows = Database::all(
            'SELECT holiday_date, name FROM holidays WHERE holiday_date BETWEEN ? AND ?',
            [$start, $end]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['holiday_date']] = (string) $row['name'];
        }
        return $map;
    }

    /** Cuti lain milik karyawan yang beririsan dengan rentang (selain yang dibatalkan/ditolak). */
    public function overlaps(int $employeeId, string $start, string $end, ?int $ignoreId = null): ?array
    {
        $sql = "SELECT lr.id, lr.start_date, lr.end_date, lr.status, lt.name AS type_name
                FROM leave_requests lr
                JOIN leave_types lt ON lt.id = lr.leave_type_id
                WHERE lr.employee_id = ?
                  AND lr.status NOT IN ('REJECTED','CANCELLED')
                  AND lr.start_date <= ? AND lr.end_date >= ?";
        $params = [$employeeId, $end, $start];
        if ($ignoreId !== null) {
            $sql .= ' AND lr.id <> ?';
            $params[] = $ignoreId;
        }
        $sql .= ' ORDER BY lr.start_date LIMIT 1';
        return Database::one($sql, $params);
    }

    /* -------------------------------------------------------------- create flow */

    /**
     * Validasi + simpan pengajuan cuti karyawan.
     * @return array{id:?int,error:?string,days:?float,warnings:array<int,string>}
     */
    public function create(array $data, int $employeeId): array
    {
        $policy = $this->settings();
        $type = $this->findType((int) ($data['leave_type_id'] ?? 0));
        if (!$type) {
            return ['id' => null, 'error' => 'Jenis cuti tidak ditemukan.', 'days' => null, 'warnings' => []];
        }
        if ((int) $type['is_active'] !== 1) {
            return ['id' => null, 'error' => 'Jenis cuti "' . $type['name'] . '" sudah tidak aktif.', 'days' => null, 'warnings' => []];
        }

        $start = (string) ($data['start_date'] ?? '');
        $end = (string) ($data['end_date'] ?? '') ?: $start;
        if (strtotime($start) === false || strtotime($end) === false) {
            return ['id' => null, 'error' => 'Tanggal cuti tidak valid.', 'days' => null, 'warnings' => []];
        }
        if (strtotime($end) < strtotime($start)) {
            return ['id' => null, 'error' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.', 'days' => null, 'warnings' => []];
        }

        $range = (int) round((strtotime($end) - strtotime($start)) / 86400) + 1;
        if ($range > $policy['max_range_days']) {
            return ['id' => null, 'error' => 'Rentang pengajuan maksimal ' . $policy['max_range_days'] . ' hari.', 'days' => null, 'warnings' => []];
        }

        $workDates = $this->workingDays($start, $end);
        $days = (float) count($workDates);
        if ($days <= 0) {
            return ['id' => null, 'error' => 'Rentang tanggal yang dipilih hanya berisi akhir pekan/hari besar, tidak ada hari kerja.', 'days' => null, 'warnings' => []];
        }

        if ((int) $type['max_per_request'] > 0 && $days > (float) $type['max_per_request']) {
            return [
                'id' => null,
                'error' => 'Maksimal ' . (int) $type['max_per_request'] . ' hari per pengajuan untuk ' . $type['name'] . '.',
                'days' => $days,
                'warnings' => [],
            ];
        }

        $notice = (int) $type['min_notice_days'];
        if ($notice > 0 && strtotime($start) < strtotime('+' . $notice . ' days', strtotime(date('Y-m-d')))) {
            return [
                'id' => null,
                'error' => $type['name'] . ' membutuhkan pengajuan minimal ' . $notice . ' hari sebelum tanggal mulai (paling cepat '
                    . tanggal(date('Y-m-d', strtotime('+' . $notice . ' days'))) . ').',
                'days' => $days,
                'warnings' => [],
            ];
        }

        if (($data['reason'] ?? '') === '') {
            return ['id' => null, 'error' => 'Alasan cuti wajib diisi.', 'days' => $days, 'warnings' => []];
        }

        $attachment = $data['attachment'] ?? null;
        if ((int) $type['requires_attachment'] === 1 && !$attachment) {
            return ['id' => null, 'error' => $type['name'] . ' mewajibkan lampiran (surat dokter/keterangan resmi).', 'days' => $days, 'warnings' => []];
        }

        $overlap = $this->overlaps($employeeId, $start, $end);
        if ($overlap) {
            return [
                'id' => null,
                'error' => 'Sudah ada pengajuan ' . $overlap['type_name'] . ' pada '
                    . tanggal((string) $overlap['start_date']) . '–' . tanggal((string) $overlap['end_date'])
                    . ' yang beririsan dengan tanggal ini.',
                'days' => $days,
                'warnings' => [],
            ];
        }

        $year = (int) date('Y', strtotime($start));
        $warnings = [];
        $requiresBalance = (float) $type['quota_days'] > 0;
        if ($requiresBalance) {
            $balance = $this->balanceFor($employeeId, (int) $type['id'], $year);
            if (!$balance) {
                $this->ensureBalance($employeeId, (int) $type['id'], $year, (float) $type['quota_days']);
                $warnings[] = 'Saldo ' . $type['name'] . ' tahun ' . $year . ' dibuat otomatis sesuai jatah default.';
            }
            $available = $this->available($employeeId, (int) $type['id'], $year);
            if ($days > $available && !$policy['allow_negative']) {
                return [
                    'id' => null,
                    'error' => 'Saldo ' . $type['name'] . ' tidak cukup: tersisa ' . number_id($available, 1)
                        . ' hari, dibutuhkan ' . number_id($days, 1) . ' hari.',
                    'days' => $days,
                    'warnings' => [],
                ];
            }
            if ($days > $available) {
                $warnings[] = 'Pengajuan melebihi saldo (sisa ' . number_id($available, 1) . ' hari); saldo akan menjadi minus bila disetujui.';
            }
        }

        $now = date('Y-m-d H:i:s');
        $id = Database::insert('leave_requests', [
            'employee_id'   => $employeeId,
            'leave_type_id' => (int) $type['id'],
            'start_date'    => $start,
            'end_date'      => $end,
            'days'          => $days,
            'reason'        => substr(trim((string) $data['reason']), 0, 255),
            'attachment'    => $attachment,
            'status'        => 'PENDING',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return ['id' => $id, 'error' => null, 'days' => $days, 'warnings' => $warnings];
    }

    /* ------------------------------------------------------------ final effects */

    /**
     * Efek persetujuan final: kurangi saldo dan tandai presensi menjadi LEAVE.
     * Idempoten — dipanggil sekali saat status berubah menjadi APPROVED.
     */
    public function applyApproval(int $requestId): void
    {
        $request = Database::one('SELECT * FROM leave_requests WHERE id = ?', [$requestId]);
        if (!$request) {
            return;
        }

        $type = $this->findType((int) $request['leave_type_id']);
        $year = (int) date('Y', strtotime((string) $request['start_date']));
        $days = (float) $request['days'];

        if ($type && (float) $type['quota_days'] > 0) {
            $balance = $this->ensureBalance((int) $request['employee_id'], (int) $type['id'], $year, (float) $type['quota_days']);
            Database::update('leave_balances', [
                'used'       => round((float) $balance['used'] + $days, 1),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int) $balance['id']]);
        }

        $this->markAttendance($request);
    }

    /** Kembalikan saldo bila cuti yang sudah disetujui dibatalkan. */
    public function revertApproval(int $requestId): void
    {
        $request = Database::one('SELECT * FROM leave_requests WHERE id = ?', [$requestId]);
        if (!$request) {
            return;
        }

        $type = $this->findType((int) $request['leave_type_id']);
        $year = (int) date('Y', strtotime((string) $request['start_date']));
        if ($type && (float) $type['quota_days'] > 0) {
            $balance = $this->balanceFor((int) $request['employee_id'], (int) $type['id'], $year);
            if ($balance) {
                Database::update('leave_balances', [
                    'used'       => round(max(0, (float) $balance['used'] - (float) $request['days']), 1),
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [(int) $balance['id']]);
            }
        }

        Database::run(
            "DELETE FROM attendance WHERE employee_id = ? AND work_date BETWEEN ? AND ? AND source = ?",
            [(int) $request['employee_id'], (string) $request['start_date'], (string) $request['end_date'], self::ATTENDANCE_SOURCE]
        );
    }

    /** Tandai hari kerja pada rentang cuti sebagai LEAVE di tabel presensi. */
    public function markAttendance(array $request): int
    {
        $employeeId = (int) $request['employee_id'];
        $now = date('Y-m-d H:i:s');
        $marked = 0;

        foreach ($this->workingDays((string) $request['start_date'], (string) $request['end_date']) as $date) {
            $existing = Database::one(
                'SELECT id, status, check_in_at FROM attendance WHERE employee_id = ? AND work_date = ?',
                [$employeeId, $date]
            );

            if (!$existing) {
                Database::insert('attendance', [
                    'employee_id' => $employeeId,
                    'work_date'   => $date,
                    'status'      => 'LEAVE',
                    'source'      => self::ATTENDANCE_SOURCE,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
                $marked++;
                continue;
            }

            // Jangan menimpa presensi yang sudah ada (karyawan tetap masuk).
            if ($existing['check_in_at'] === null && in_array((string) $existing['status'], ['ABSENT'], true)) {
                Database::update('attendance', [
                    'status'     => 'LEAVE',
                    'source'     => self::ATTENDANCE_SOURCE,
                    'updated_at' => $now,
                ], 'id = ?', [(int) $existing['id']]);
                $marked++;
            }
        }

        return $marked;
    }

    /** Batalkan pengajuan (karyawan: hanya PENDING; HR: kapan saja kecuali sudah batal). */
    public function cancel(int $id, string $actor = 'Karyawan', ?string $note = null): array
    {
        $request = Database::one('SELECT * FROM leave_requests WHERE id = ?', [$id]);
        if (!$request) {
            return ['error' => 'Pengajuan cuti tidak ditemukan.'];
        }
        if ((string) $request['status'] === 'CANCELLED') {
            return ['error' => 'Pengajuan cuti ini sudah dibatalkan.'];
        }

        if ((string) $request['status'] === 'APPROVED') {
            $this->revertApproval($id);
        }

        Database::update('leave_requests', [
            'status'     => 'CANCELLED',
            'hr_note'    => substr((string) ($note ?: $request['hr_note'] ?: 'Dibatalkan ' . $actor), 0, 255),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        return ['error' => null];
    }

    /* ------------------------------------------------------------ self service */

    public function myRequests(int $employeeId, int $limit = 40): array
    {
        $rows = Database::all(
            $this->requestSelect() . ' WHERE lr.employee_id = ? ORDER BY lr.start_date DESC, lr.id DESC LIMIT ' . max(1, $limit),
            [$employeeId]
        );
        return array_map([$this, 'decorate'], $rows);
    }

    public function mySummary(int $employeeId, int $year): array
    {
        $row = Database::one(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN status = 'APPROVED' THEN days ELSE 0 END), 0) AS approved_days,
                    COALESCE(SUM(CASE WHEN status IN ('PENDING','MANAGER_APPROVED') THEN 1 ELSE 0 END), 0) AS open_count
             FROM leave_requests
             WHERE employee_id = ? AND start_date BETWEEN ? AND ?",
            [$employeeId, $year . '-01-01', $year . '-12-31']
        ) ?? [];

        $balances = $this->myBalances($employeeId, $year);
        $remaining = 0.0;
        foreach ($balances as $balance) {
            if (!$balance['unlimited']) {
                $remaining += $balance['remaining'];
            }
        }

        return [
            'year'          => $year,
            'total'         => (int) ($row['total'] ?? 0),
            'approved_days' => (float) ($row['approved_days'] ?? 0),
            'open'          => (int) ($row['open_count'] ?? 0),
            'remaining'     => round($remaining, 1),
        ];
    }

    /** Kuota default per jenis untuk tahun tertentu (dipakai form & validasi JS). */
    public function typeQuotas(): array
    {
        $map = [];
        foreach ($this->types(true) as $type) {
            $map[(int) $type['id']] = [
                'name'         => (string) $type['name'],
                'max'          => (int) $type['max_per_request'],
                'notice'       => (int) $type['min_notice_days'],
                'attachment'   => (int) $type['requires_attachment'],
                'quota'        => (float) $type['quota_days'],
            ];
        }
        return $map;
    }

    /* ------------------------------------------------------------------ helpers */

    /** @return array{0:string,1:string} */
    public function monthBounds(string $month): array
    {
        $ts = strtotime($month . '-01');
        if ($ts === false) {
            $ts = strtotime(date('Y-m-01'));
        }
        return [date('Y-m-01', $ts), date('Y-m-t', $ts)];
    }

    public function employeeOptions(?int $unitId = null, ?int $departmentId = null): array
    {
        $sql = "SELECT e.id, e.full_name, e.employee_code, e.email, bu.name AS unit_name
                FROM employees e
                LEFT JOIN business_units bu ON bu.id = e.business_unit_id
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
