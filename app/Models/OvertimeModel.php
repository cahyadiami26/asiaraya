<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Settings;

/**
 * Lembur (overtime) dan lembur terjadwal (scheduled overtime).
 *
 * Dua jalur sumber data pada tabel `overtime_requests`:
 *  - type = REQUEST   → pengajuan mandiri karyawan, approval dua tahap (atasan → HR)
 *  - type = SCHEDULED → penugasan massal oleh HR, langsung APPROVED kecuali diminta draft
 *
 * Model ini juga menjadi satu-satunya sumber rumus kompensasi lembur:
 * upah per jam = gaji pokok / divisor, jam pertama ×1.5, jam berikutnya ×2,
 * dikali multiplier hari libur bila tanggal jatuh pada akhir pekan/hari besar.
 */
final class OvertimeModel
{
    public const STATUS_OPEN = ['PENDING', 'MANAGER_APPROVED'];

    public const STATUS_LABELS = [
        'PENDING'          => 'Menunggu Atasan',
        'MANAGER_APPROVED' => 'Menunggu HR',
        'APPROVED'         => 'Disetujui',
        'REJECTED'         => 'Ditolak',
        'CANCELLED'        => 'Dibatalkan',
    ];

    /* ------------------------------------------------------------------ policy */

    public function settings(): array
    {
        return [
            'enabled'      => Settings::bool('overtime_enabled', true),
            'divisor'      => (float) (Settings::get('overtime_divisor') ?: 173),
            'first'        => (float) (Settings::get('overtime_first_hour_multiplier') ?: 1.5),
            'next'         => (float) (Settings::get('overtime_next_hour_multiplier') ?: 2),
            'holiday'      => (float) (Settings::get('overtime_holiday_multiplier') ?: 2),
            'max_hours'    => (float) (Settings::get('overtime_max_hours') ?: 4),
            'min_minutes'  => (int) (Settings::get('overtime_min_minutes') ?: 30),
            'max_amount'   => (float) (Settings::get('overtime_max_compensation') ?: 0),
        ];
    }

    /** Nama hari besar bila tanggal terdaftar pada tabel holidays. */
    public function holiday(string $date): ?string
    {
        $name = Database::value('SELECT name FROM holidays WHERE holiday_date = ?', [$date]);
        return $name !== null ? (string) $name : null;
    }

    /** Akhir pekan (Sabtu/Minggu) atau hari besar → tarif libur. */
    public function isHolidayRate(string $date): bool
    {
        $dow = (int) date('N', strtotime($date));
        return $dow >= 6 || $this->holiday($date) !== null;
    }

    /**
     * Rincian kompensasi lembur.
     * @return array{amount: float, hourly: float, hours: float, capped: bool, multiplier: float, breakdown: array<int, array{label: string, hours: float, rate: float, amount: float}>}
     */
    public function compensation(int $employeeId, float $hours, ?string $date = null): array
    {
        $salary = (float) Database::value('SELECT basic_salary FROM employees WHERE id = ?', [$employeeId]);
        return $this->compute($salary, $hours, $date);
    }

    /** Perhitungan kompensasi dari gaji pokok yang sudah diketahui (tanpa query). */
    public function compute(float $salary, float $hours, ?string $date = null): array
    {
        $policy = $this->settings();
        $hourly = $policy['divisor'] > 0 ? $salary / $policy['divisor'] : 0.0;

        $requested = max(0.0, round($hours, 2));
        $limit = (float) $policy['max_hours'];
        $hoursCapped = $limit > 0 && $requested > $limit + 0.0001;
        $hours = $hoursCapped ? $limit : $requested;
        $holidayRate = $date !== null ? $this->isHolidayRate($date) : false;
        $multiplier = $holidayRate ? $policy['holiday'] : 1.0;

        $firstHours = min(1.0, $hours);
        $restHours = max(0.0, $hours - 1.0);
        $firstRate = $hourly * $policy['first'] * $multiplier;
        $nextRate = $hourly * $policy['next'] * $multiplier;

        $breakdown = [];
        if ($firstHours > 0) {
            $breakdown[] = [
                'label'  => 'Jam pertama (×' . $this->trim($policy['first'] * $multiplier) . ')',
                'hours'  => $firstHours,
                'rate'   => $firstRate,
                'amount' => $firstHours * $firstRate,
            ];
        }
        if ($restHours > 0) {
            $breakdown[] = [
                'label'  => 'Jam berikutnya (×' . $this->trim($policy['next'] * $multiplier) . ')',
                'hours'  => $restHours,
                'rate'   => $nextRate,
                'amount' => $restHours * $nextRate,
            ];
        }

        $calculated = array_sum(array_column($breakdown, 'amount'));
        $cap = (float) $policy['max_amount'];
        $amountCapped = $cap > 0 && $calculated > $cap + 0.0001;
        $amount = $amountCapped ? $cap : $calculated;

        return [
            'amount'          => round($amount, 2),
            'amount_uncapped' => round($calculated, 2),
            'amount_capped'   => $amountCapped,
            'cap'             => round($cap, 2),
            'hourly'          => round($hourly, 2),
            'hours'           => round($hours, 2),
            'requested_hours' => $requested,
            'hours_capped'    => $hoursCapped,
            'capped'          => $hoursCapped || $amountCapped,
            'multiplier'      => $multiplier,
            'holiday'         => $holidayRate,
            'breakdown'       => $breakdown,
        ];
    }

    private function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /** Baris simulasi tarif untuk panel kebijakan (asumsi gaji 10 juta). */
    public function policyPreview(float $salary = 10000000): array
    {
        $policy = $this->settings();
        $hourly = $policy['divisor'] > 0 ? $salary / $policy['divisor'] : 0;
        $cap = (float) $policy['max_amount'];
        $maxIndex = max(1, min(8, (int) $policy['max_hours']));
        $rows = [];
        for ($index = 1; $index <= $maxIndex; $index++) {
            $firstHours = min(1, $index);
            $rest = max(0, $index - 1);
            $regular = $firstHours * $hourly * $policy['first'] + $rest * $hourly * $policy['next'];
            $holidayRate = $regular * $policy['holiday'];
            $rows[] = [
                'hours'    => $index,
                'amount'   => round($cap > 0 ? min($regular, $cap) : $regular, 2),
                'holiday'  => round($cap > 0 ? min($holidayRate, $cap) : $holidayRate, 2),
                'capped'   => $cap > 0 && ($regular > $cap || $holidayRate > $cap),
            ];
        }
        return $rows;
    }

    /**
     * Simpan kebijakan lembur oleh approver final (HR).
     * Nilai maksimum jam & maksimum kompensasi 0 berarti tanpa batas nominal.
     * @return array{errors: array<string, string>, saved: bool}
     */
    public function savePolicy(array $input, ?int $actorId = null): array
    {
        $errors = [];

        $maxHours = round((float) ($input['max_hours'] ?? 0), 2);
        $maxAmount = round((float) ($input['max_amount'] ?? 0), 2);
        $minMinutes = (int) ($input['min_minutes'] ?? 0);
        $divisor = round((float) ($input['divisor'] ?? 0), 2);
        $first = round((float) ($input['first'] ?? 0), 2);
        $next = round((float) ($input['next'] ?? 0), 2);
        $holiday = round((float) ($input['holiday'] ?? 0), 2);

        if ($maxHours < 1 || $maxHours > 12) {
            $errors['max_hours'] = 'Batas maksimum jam lembur harus antara 1 dan 12 jam per hari.';
        }
        if ($maxAmount < 0 || $maxAmount > 100000000) {
            $errors['max_amount'] = 'Batas maksimum kompensasi harus antara Rp0 (tanpa batas) dan Rp100.000.000.';
        }
        if ($minMinutes < 0 || ($maxHours > 0 && $minMinutes > $maxHours * 60)) {
            $errors['min_minutes'] = 'Durasi minimum tidak boleh melebihi batas maksimum jam per hari.';
        }
        if ($divisor < 1 || $divisor > 400) {
            $errors['divisor'] = 'Pembagi upah per jam harus antara 1 dan 400.';
        }
        foreach (['first' => $first, 'next' => $next, 'holiday' => $holiday] as $key => $value) {
            if ($value < 1 || $value > 5) {
                $errors[$key] = 'Nilai multiplier harus antara 1 dan 5.';
            }
        }
        if ($next + 0.001 < $first) {
            $errors['next'] = 'Multiplier jam berikutnya tidak boleh lebih kecil dari jam pertama.';
        }

        if ($errors) {
            return ['errors' => $errors, 'saved' => false];
        }

        Settings::many([
            'overtime_enabled'                => (int) ($input['enabled'] ?? 0),
            'overtime_max_hours'              => $this->trim($maxHours),
            'overtime_max_compensation'       => $this->trim($maxAmount),
            'overtime_min_minutes'            => $minMinutes,
            'overtime_divisor'                => $this->trim($divisor),
            'overtime_first_hour_multiplier'  => $this->trim($first),
            'overtime_next_hour_multiplier'   => $this->trim($next),
            'overtime_holiday_multiplier'     => $this->trim($holiday),
        ], 'overtime');

        return ['errors' => [], 'saved' => true];
    }

    /* ----------------------------------------------------------------- lookups */

    public function find(int $id): ?array
    {
        return Database::one($this->baseSelect() . ' WHERE o.id = ?', [$id]);
    }

    private function baseSelect(): string
    {
        return "SELECT o.*, e.full_name, e.employee_code, e.email, e.photo, e.role, e.basic_salary,
                       bu.name AS unit_name, bu.code AS unit_code,
                       d.name AS department_name,
                       m.full_name AS manager_name,
                       h.full_name AS hr_name,
                       a.check_in_at, a.check_out_at
                FROM overtime_requests o
                JOIN employees e ON e.id = o.employee_id
                LEFT JOIN business_units bu ON bu.id = e.business_unit_id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN employees m ON m.id = o.manager_id
                LEFT JOIN employees h ON h.id = o.hr_id
                LEFT JOIN attendance a ON a.employee_id = o.employee_id AND a.work_date = o.work_date";
    }

    /** Bangun klausa WHERE dari filter (dipakai list, count, stats, export). */
    private function where(array $filters, array &$params): string
    {
        $clauses = [];

        $status = strtoupper((string) ($filters['status'] ?? ''));
        if ($status === 'OPEN') {
            $clauses[] = "o.status IN ('PENDING','MANAGER_APPROVED')";
        } elseif ($status !== '' && $status !== 'ALL' && isset(self::STATUS_LABELS[$status])) {
            $clauses[] = 'o.status = ?';
            $params[] = $status;
        }

        $type = strtoupper((string) ($filters['type'] ?? ''));
        if (in_array($type, ['REQUEST', 'SCHEDULED'], true)) {
            $clauses[] = 'o.type = ?';
            $params[] = $type;
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
            $clauses[] = 'o.employee_id = ?';
            $params[] = (int) $filters['employee'];
        }
        if (!empty($filters['from'])) {
            $clauses[] = 'o.work_date >= ?';
            $params[] = (string) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $clauses[] = 'o.work_date <= ?';
            $params[] = (string) $filters['to'];
        }
        if (!empty($filters['month'])) {
            [$start, $end] = $this->monthBounds((string) $filters['month']);
            $clauses[] = 'o.work_date BETWEEN ? AND ?';
            $params[] = $start;
            $params[] = $end;
        }
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . $filters['q'] . '%';
            $clauses[] = '(e.full_name LIKE ? OR e.employee_code LIKE ? OR o.reason LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        return $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';
    }

    /** @return array<int, array<string, mixed>> */
    public function requests(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $params = [];
        $sql = $this->baseSelect() . $this->where($filters, $params)
            . ' ORDER BY o.work_date DESC, o.id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
        return array_map([$this, 'decorate'], Database::all($sql, $params));
    }

    public function count(array $filters = []): int
    {
        $params = [];
        $sql = 'SELECT COUNT(*) FROM overtime_requests o
                JOIN employees e ON e.id = o.employee_id' . $this->where($filters, $params);
        return (int) Database::value($sql, $params);
    }

    public function exportRows(array $filters = []): array
    {
        $params = [];
        $sql = $this->baseSelect() . $this->where($filters, $params)
            . ' ORDER BY o.work_date DESC, e.employee_code ASC';
        return array_map([$this, 'decorate'], Database::all($sql, $params));
    }

    /** Tambahan nilai turunan: durasi, saran jam aktual dari presensi, hari libur. */
    private function decorate(array $row): array
    {
        $row['planned_hours'] = (float) $row['planned_hours'];
        $row['hours_effective'] = (float) ($row['actual_hours'] ?: $row['planned_hours']);
        $row['compensation_amount'] = (float) $row['compensation_amount'];
        $row['holiday_name'] = $this->holiday((string) $row['work_date']);
        $row['suggested_actual'] = $this->suggestActual($row);
        $calc = $this->compute((float) ($row['basic_salary'] ?? 0), $row['hours_effective'], (string) $row['work_date']);
        $row['estimated_amount'] = $row['compensation_amount'] > 0 ? $row['compensation_amount'] : $calc['amount'];
        $row['amount_uncapped'] = $calc['amount_uncapped'];
        $stored = (float) $row['compensation_amount'];
        $row['amount_capped'] = $stored > 0
            ? ($calc['cap'] > 0 && $stored <= $calc['cap'] + 0.01 && $calc['amount_uncapped'] > $calc['cap'] + 0.01)
            : $calc['amount_capped'];
        // Kompensasi lama yang disetujui sebelum kebijakan berubah bisa melebihi batas saat ini.
        $row['above_current_cap'] = $stored > 0 && $calc['cap'] > 0 && $stored > $calc['cap'] + 0.01;
        $row['hours_capped'] = $calc['hours_capped'];
        $row['cap'] = $calc['cap'];

        $start = (string) ($row['planned_start'] ?? '');
        $end = (string) ($row['planned_end'] ?? '');
        $row['window'] = $start !== '' && $end !== ''
            ? substr($start, 0, 5) . '–' . substr($end, 0, 5)
            : '—';
        $row['crosses_midnight'] = $this->crossesMidnight($start, $end);

        return $row;
    }

    private function crossesMidnight(string $start, string $end): bool
    {
        if ($start === '' || $end === '') {
            return false;
        }
        return strtotime('1970-01-01 ' . $end) <= strtotime('1970-01-01 ' . $start);
    }

    /** Jam lembur aktual menurut presensi: selisih check-out dengan jam selesai lembur. */
    private function suggestActual(array $row): float
    {
        if (empty($row['check_out_at']) || empty($row['planned_start']) || empty($row['planned_end'])) {
            return 0.0;
        }
        $date = (string) $row['work_date'];
        $endTs = strtotime($date . ' ' . substr((string) $row['planned_end'], 0, 8));
        $startTs = strtotime($date . ' ' . substr((string) $row['planned_start'], 0, 8));
        if ($endTs === false || $startTs === false) {
            return 0.0;
        }
        if ($endTs <= $startTs) {
            $endTs = strtotime('+1 day', $endTs);
        }
        $outTs = strtotime((string) $row['check_out_at']);
        if ($outTs === false || $outTs <= $endTs) {
            return 0.0;
        }
        return round(min(12, ($outTs - $endTs) / 3600), 2);
    }

    /* ------------------------------------------------------------------- stats */

    public function stats(array $filters = []): array
    {
        $base = $filters;
        unset($base['status']);

        $counts = ['PENDING' => 0, 'MANAGER_APPROVED' => 0, 'APPROVED' => 0, 'REJECTED' => 0, 'CANCELLED' => 0];
        $params = [];
        $sql = 'SELECT o.status, COUNT(*) AS total FROM overtime_requests o
                JOIN employees e ON e.id = o.employee_id' . $this->where($base, $params) . ' GROUP BY o.status';
        foreach (Database::all($sql, $params) as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        $sumParams = [];
        $sum = Database::one(
            'SELECT COALESCE(SUM(CASE WHEN o.status = \'APPROVED\' THEN COALESCE(o.actual_hours, o.planned_hours) ELSE 0 END), 0) AS approved_hours,
                    COALESCE(SUM(CASE WHEN o.status = \'APPROVED\' THEN o.compensation_amount ELSE 0 END), 0) AS approved_amount,
                    COALESCE(SUM(CASE WHEN o.type = \'SCHEDULED\' THEN 1 ELSE 0 END), 0) AS scheduled_count,
                    COUNT(DISTINCT o.employee_id) AS people
             FROM overtime_requests o
             JOIN employees e ON e.id = o.employee_id' . $this->where($base, $sumParams),
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
            'hours'        => round((float) ($sum['approved_hours'] ?? 0), 2),
            'amount'       => (float) ($sum['approved_amount'] ?? 0),
            'scheduled'    => (int) ($sum['scheduled_count'] ?? 0),
            'people'       => (int) ($sum['people'] ?? 0),
        ];
    }

    /** Sebaran jam lembur per hari untuk grafik bulanan. */
    public function dailyHours(string $month): array
    {
        [$start, $end] = $this->monthBounds($month);
        $rows = Database::all(
            "SELECT o.work_date, COUNT(*) AS requests,
                    COALESCE(SUM(COALESCE(o.actual_hours, o.planned_hours)), 0) AS hours,
                    COALESCE(SUM(o.compensation_amount), 0) AS amount
             FROM overtime_requests o
             WHERE o.work_date BETWEEN ? AND ?
               AND o.status NOT IN ('REJECTED','CANCELLED')
             GROUP BY o.work_date ORDER BY o.work_date",
            [$start, $end]
        );

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[(string) $row['work_date']] = $row;
        }

        $days = [];
        $cursor = strtotime($start);
        $last = strtotime($end);
        while ($cursor !== false && $cursor <= $last) {
            $date = date('Y-m-d', $cursor);
            $row = $byDate[$date] ?? null;
            $days[] = [
                'date'     => $date,
                'day'      => (int) date('j', $cursor),
                'dow'      => (int) date('N', $cursor),
                'hours'    => (float) ($row['hours'] ?? 0),
                'requests' => (int) ($row['requests'] ?? 0),
                'amount'   => (float) ($row['amount'] ?? 0),
                'holiday'  => $this->holiday($date),
            ];
            $cursor = strtotime('+1 day', $cursor);
        }
        return $days;
    }

    /** Rekap per unit untuk kartu ringkas. */
    public function byUnit(string $month): array
    {
        [$start, $end] = $this->monthBounds($month);
        return Database::all(
            "SELECT bu.id, bu.name, bu.code,
                    COUNT(DISTINCT o.employee_id) AS people,
                    COALESCE(SUM(COALESCE(o.actual_hours, o.planned_hours)), 0) AS hours,
                    COALESCE(SUM(o.compensation_amount), 0) AS amount
             FROM overtime_requests o
             JOIN employees e ON e.id = o.employee_id
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             WHERE o.work_date BETWEEN ? AND ? AND o.status = 'APPROVED'
             GROUP BY bu.id, bu.name, bu.code
             ORDER BY hours DESC",
            [$start, $end]
        );
    }

    /** Peringkat karyawan dengan jam lembur terbanyak (bulan berjalan). */
    public function topEmployees(string $month, int $limit = 5): array
    {
        [$start, $end] = $this->monthBounds($month);
        return Database::all(
            "SELECT e.id, e.full_name, e.employee_code, e.email, bu.name AS unit_name,
                    COALESCE(SUM(COALESCE(o.actual_hours, o.planned_hours)), 0) AS hours,
                    COALESCE(SUM(o.compensation_amount), 0) AS amount
             FROM overtime_requests o
             JOIN employees e ON e.id = o.employee_id
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             WHERE o.work_date BETWEEN ? AND ? AND o.status = 'APPROVED'
             GROUP BY e.id, e.full_name, e.employee_code, e.email, bu.name
             ORDER BY hours DESC LIMIT " . max(1, $limit),
            [$start, $end]
        );
    }

    /* --------------------------------------------------------------- mutations */

    /**
     * Buat pengajuan/penugasan lembur.
     * @return array{id: int|null, error: string|null}
     */
    public function create(array $data, ?int $actorId = null): array
    {
        $policy = $this->settings();
        if (!$policy['enabled']) {
            return ['id' => null, 'error' => 'Modul lembur sedang dinonaktifkan pada pengaturan.'];
        }

        $employeeId = (int) ($data['employee_id'] ?? 0);
        $employee = Database::one(
            "SELECT id, department_id, direct_manager_id, basic_salary, full_name, flag FROM employees WHERE id = ?",
            [$employeeId]
        );
        if (!$employee) {
            return ['id' => null, 'error' => 'Karyawan tidak ditemukan.'];
        }
        if ((string) $employee['flag'] !== 'ACTIVE') {
            return ['id' => null, 'error' => 'Karyawan tidak aktif sehingga tidak dapat dijadwalkan lembur.'];
        }

        $date = (string) ($data['work_date'] ?? '');
        if ($date === '' || strtotime($date) === false) {
            return ['id' => null, 'error' => 'Tanggal lembur tidak valid.'];
        }

        $start = $this->normalizeTime((string) ($data['planned_start'] ?? ''));
        $end = $this->normalizeTime((string) ($data['planned_end'] ?? ''));
        if ($start === null || $end === null) {
            return ['id' => null, 'error' => 'Jam mulai dan jam selesai lembur wajib diisi (format HH:MM).'];
        }

        $hours = $this->durationHours($start, $end);
        if ($hours <= 0) {
            return ['id' => null, 'error' => 'Jam selesai harus berbeda dengan jam mulai.'];
        }
        if ($hours * 60 < $policy['min_minutes']) {
            return ['id' => null, 'error' => 'Durasi lembur minimal ' . $policy['min_minutes'] . ' menit.'];
        }
        if ($policy['max_hours'] > 0 && $hours > $policy['max_hours'] + 0.0001) {
            return [
                'id' => null,
                'error' => 'Durasi ' . $this->trim($hours) . ' jam melebihi batas maksimum '
                    . $this->trim($policy['max_hours']) . ' jam per hari. Sesuaikan jam atau ubah batas pada kebijakan lembur.',
            ];
        }
        if ($hours > 12) {
            return ['id' => null, 'error' => 'Durasi lembur maksimal 12 jam per hari.'];
        }

        if ($this->overlaps($employeeId, $date, $start, $end)) {
            return ['id' => null, 'error' => 'Sudah ada pengajuan lembur lain pada jam tersebut untuk ' . $employee['full_name'] . '.'];
        }

        $type = strtoupper((string) ($data['type'] ?? 'REQUEST')) === 'SCHEDULED' ? 'SCHEDULED' : 'REQUEST';
        $autoApprove = $type === 'SCHEDULED' && (int) ($data['auto_approve'] ?? 1) === 1;
        $now = date('Y-m-d H:i:s');

        $record = [
            'employee_id'    => $employeeId,
            'department_id'  => $employee['department_id'] !== null ? (int) $employee['department_id'] : null,
            'work_date'      => $date,
            'planned_start'  => $start,
            'planned_end'    => $end,
            'planned_hours'  => $hours,
            'actual_hours'   => null,
            'type'           => $type,
            'reason'         => substr(trim((string) ($data['reason'] ?? '')), 0, 255) ?: null,
            'status'         => $autoApprove ? 'APPROVED' : 'PENDING',
            'compensation_amount' => 0,
            'manager_id'     => null,
            'created_by'     => $actorId,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];

        if ($autoApprove) {
            $record['hr_id'] = $actorId;
            $record['hr_approved_at'] = $now;
            $record['hr_note'] = 'Dijadwalkan HR';
            $record['compensation_amount'] = $this->compensation($employeeId, $hours, $date)['amount'];
        }

        $id = Database::insert('overtime_requests', $record);
        return ['id' => $id, 'error' => null];
    }

    /** HR memperbarui detail lembur yang belum final (atau menyetel ulang yang ditolak). */
    public function update(int $id, array $data): array
    {
        $row = Database::one('SELECT * FROM overtime_requests WHERE id = ?', [$id]);
        if (!$row) {
            return ['error' => 'Data lembur tidak ditemukan.'];
        }
        if (in_array((string) $row['status'], ['APPROVED', 'CANCELLED'], true)) {
            return ['error' => 'Lembur yang sudah disetujui/dibatalkan tidak dapat diubah. Gunakan pembatalan bila perlu.'];
        }

        $start = $this->normalizeTime((string) ($data['planned_start'] ?? $row['planned_start']));
        $end = $this->normalizeTime((string) ($data['planned_end'] ?? $row['planned_end']));
        if ($start === null || $end === null) {
            return ['error' => 'Format jam tidak valid.'];
        }
        $hours = $this->durationHours($start, $end);
        if ($hours <= 0 || $hours > 12) {
            return ['error' => 'Durasi lembur harus antara 1 menit dan 12 jam.'];
        }
        $policy = $this->settings();
        if ($policy['max_hours'] > 0 && $hours > $policy['max_hours'] + 0.0001) {
            return [
                'error' => 'Durasi ' . $this->trim($hours) . ' jam melebihi batas maksimum '
                    . $this->trim($policy['max_hours']) . ' jam per hari.',
            ];
        }
        if ($this->overlaps((int) $row['employee_id'], (string) $row['work_date'], $start, $end, $id)) {
            return ['error' => 'Jam tersebut bertabrakan dengan lembur lain karyawan ini.'];
        }

        Database::update('overtime_requests', [
            'planned_start' => $start,
            'planned_end'   => $end,
            'planned_hours' => $hours,
            'reason'        => substr(trim((string) ($data['reason'] ?? $row['reason'] ?? '')), 0, 255) ?: null,
            'updated_at'    => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        return ['error' => null];
    }

    /** Set jam lembur aktual (dipakai setelah pekerjaan selesai) + hitung ulang kompensasi. */
    public function setActual(int $id, float $hours): array
    {
        $row = Database::one('SELECT * FROM overtime_requests WHERE id = ?', [$id]);
        if (!$row) {
            return ['error' => 'Data lembur tidak ditemukan.'];
        }
        if ((string) $row['status'] === 'CANCELLED') {
            return ['error' => 'Lembur yang dibatalkan tidak dapat diisi jam aktualnya.'];
        }
        if ($hours < 0 || $hours > 12) {
            return ['error' => 'Jam aktual harus antara 0 dan 12 jam.'];
        }

        $policy = $this->settings();
        $hours = round($hours, 2);
        if ($policy['max_hours'] > 0 && $hours > $policy['max_hours'] + 0.0001) {
            return [
                'error' => 'Jam aktual ' . $this->trim($hours) . ' jam melebihi batas maksimum '
                    . $this->trim($policy['max_hours']) . ' jam per hari. Ubah batas pada kebijakan lembur bila perlu.',
            ];
        }
        $update = [
            'actual_hours' => $hours,
            'updated_at'   => date('Y-m-d H:i:s'),
        ];
        if ((string) $row['status'] === 'APPROVED') {
            $update['compensation_amount'] = $this->compensation(
                (int) $row['employee_id'],
                $hours,
                (string) $row['work_date']
            )['amount'];
        }
        Database::update('overtime_requests', $update, 'id = ?', [$id]);
        return ['error' => null, 'hours' => $hours];
    }

    /** Batalkan lembur (HR bisa kapan saja, karyawan hanya miliknya yang belum diputus). */
    public function cancel(int $id, ?string $note = null, string $actor = 'HR'): array
    {
        $row = Database::one('SELECT * FROM overtime_requests WHERE id = ?', [$id]);
        if (!$row) {
            return ['error' => 'Data lembur tidak ditemukan.'];
        }
        if ((string) $row['status'] === 'CANCELLED') {
            return ['error' => 'Lembur ini sudah dibatalkan.'];
        }
        Database::update('overtime_requests', [
            'status'     => 'CANCELLED',
            'hr_note'    => substr((string) ($note ?? $row['hr_note'] ?? 'Dibatalkan ' . $actor), 0, 255),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        return ['error' => null];
    }

    /** Keputusan massal oleh HR (tahap final). */
    public function decideMany(array $ids, bool $approved, int $actorId, string $note = ''): int
    {
        $done = 0;
        $now = date('Y-m-d H:i:s');
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $row = Database::one('SELECT * FROM overtime_requests WHERE id = ?', [$id]);
            if (!$row || !in_array((string) $row['status'], self::STATUS_OPEN, true)) {
                continue;
            }
            $update = [
                'status'         => $approved ? 'APPROVED' : 'REJECTED',
                'hr_id'          => $actorId,
                'hr_approved_at' => $now,
                'hr_note'        => substr($note, 0, 255) ?: null,
                'updated_at'     => $now,
            ];
            if ($approved) {
                $hours = (float) ($row['actual_hours'] ?: $row['planned_hours']);
                $update['compensation_amount'] = $this->compensation(
                    (int) $row['employee_id'],
                    $hours,
                    (string) $row['work_date']
                )['amount'];
            }
            Database::update('overtime_requests', $update, 'id = ?', [$id]);
            $done++;
        }
        return $done;
    }

    /**
     * Penjadwalan lembur massal: unit/departemen/karyawan tertentu pada rentang tanggal.
     * @return array{created: int, skipped: int, conflicts: array<int, string>, employees: int, ids: array<int, int>}
     */
    public function scheduleBulk(array $input, ?int $actorId = null): array
    {
        $employeeIds = array_values(array_unique(array_map('intval', (array) ($input['employee_ids'] ?? []))));
        if (!$employeeIds) {
            $employeeIds = array_map(
                static fn ($row) => (int) $row['id'],
                $this->employeeOptions(
                    !empty($input['unit']) ? (int) $input['unit'] : null,
                    !empty($input['department']) ? (int) $input['department'] : null
                )
            );
        }

        $from = (string) ($input['date_from'] ?? '');
        $to = (string) ($input['date_to'] ?? $from);
        if ($from === '' || strtotime($from) === false || strtotime($to) === false || strtotime($to) < strtotime($from)) {
            return ['created' => 0, 'skipped' => 0, 'conflicts' => ['Rentang tanggal tidak valid.'], 'employees' => 0];
        }
        if ((strtotime($to) - strtotime($from)) / 86400 > 31) {
            return ['created' => 0, 'skipped' => 0, 'conflicts' => ['Rentang penjadwalan maksimal 31 hari.'], 'employees' => 0];
        }

        $skipWeekend = (int) ($input['skip_weekend'] ?? 1) === 1;
        $skipHoliday = (int) ($input['skip_holiday'] ?? 0) === 1;

        $reason = substr(trim((string) ($input['reason'] ?? '')), 0, 255);
        $created = 0;
        $skipped = 0;
        $conflicts = [];
        $ids = [];

        $cursor = strtotime($from);
        $endTs = strtotime($to);
        while ($cursor !== false && $cursor <= $endTs) {
            $date = date('Y-m-d', $cursor);
            $dow = (int) date('N', $cursor);
            if (($skipWeekend && $dow >= 6) || ($skipHoliday && $this->holiday($date) !== null)) {
                $cursor = strtotime('+1 day', $cursor);
                continue;
            }

            foreach ($employeeIds as $employeeId) {
                $result = $this->create([
                    'employee_id'   => $employeeId,
                    'work_date'     => $date,
                    'planned_start' => $input['planned_start'] ?? '',
                    'planned_end'   => $input['planned_end'] ?? '',
                    'type'          => 'SCHEDULED',
                    'auto_approve'  => (int) ($input['auto_approve'] ?? 1),
                    'reason'        => $reason !== '' ? $reason : 'Lembur terjadwal',
                ], $actorId);

                if ($result['error'] !== null) {
                    $skipped++;
                    if (count($conflicts) < 6) {
                        $conflicts[] = tanggal($date) . ': ' . $result['error'];
                    }
                    continue;
                }
                $created++;
                $ids[] = (int) $result['id'];
            }
            $cursor = strtotime('+1 day', $cursor);
        }

        return ['created' => $created, 'skipped' => $skipped, 'conflicts' => $conflicts, 'employees' => count($employeeIds), 'ids' => $ids];
    }

    /** Cegah lembur bertumpuk pada karyawan yang sama. */
    public function overlaps(int $employeeId, string $date, string $start, string $end, ?int $ignoreId = null): bool
    {
        $startMin = $this->minutes($start);
        $endMin = $this->minutes($end);
        if ($endMin <= $startMin) {
            $endMin += 1440;
        }

        $rows = Database::all(
            "SELECT id, planned_start, planned_end FROM overtime_requests
             WHERE employee_id = ? AND work_date = ? AND status NOT IN ('REJECTED','CANCELLED')",
            [$employeeId, $date]
        );
        foreach ($rows as $row) {
            if ($ignoreId !== null && (int) $row['id'] === $ignoreId) {
                continue;
            }
            $otherStart = $this->minutes((string) $row['planned_start']);
            $otherEnd = $this->minutes((string) $row['planned_end']);
            if ($otherEnd <= $otherStart) {
                $otherEnd += 1440;
            }
            if ($startMin < $otherEnd && $otherStart < $endMin) {
                return true;
            }
        }
        return false;
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', substr($time, 0, 5)), 2, '0');
        return ((int) $h) * 60 + (int) $m;
    }

    public function durationHours(string $start, string $end): float
    {
        $diff = $this->minutes($end) - $this->minutes($start);
        if ($diff <= 0) {
            $diff += 1440;
        }
        return round($diff / 60, 2);
    }

    private function normalizeTime(string $value): ?string
    {
        $value = trim($value);
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $value, $m)) {
            return null;
        }
        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }

    /* ------------------------------------------------------- self service data */

    /** Ringkasan lembur karyawan pada satu bulan. */
    public function mySummary(int $employeeId, string $month): array
    {
        [$start, $end] = $this->monthBounds($month);
        $row = Database::one(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN status = 'APPROVED' THEN COALESCE(actual_hours, planned_hours) ELSE 0 END), 0) AS approved_hours,
                    COALESCE(SUM(CASE WHEN status IN ('PENDING','MANAGER_APPROVED') THEN 1 ELSE 0 END), 0) AS open_count,
                    COALESCE(SUM(CASE WHEN status = 'APPROVED' THEN compensation_amount ELSE 0 END), 0) AS amount
             FROM overtime_requests
             WHERE employee_id = ? AND work_date BETWEEN ? AND ?",
            [$employeeId, $start, $end]
        ) ?? [];

        return [
            'month'          => $month,
            'total'          => (int) ($row['total'] ?? 0),
            'approved_hours' => round((float) ($row['approved_hours'] ?? 0), 2),
            'open'           => (int) ($row['open_count'] ?? 0),
            'amount'         => (float) ($row['amount'] ?? 0),
        ];
    }

    /** Riwayat lembur milik karyawan (default 3 bulan terakhir). */
    public function mine(int $employeeId, int $limit = 40): array
    {
        $rows = Database::all(
            $this->baseSelect() . ' WHERE o.employee_id = ? ORDER BY o.work_date DESC, o.id DESC LIMIT ' . max(1, $limit),
            [$employeeId]
        );
        return array_map([$this, 'decorate'], $rows);
    }

    /* ----------------------------------------------------------------- helpers */

    public function employeeOptions(?int $unitId = null, ?int $departmentId = null): array
    {
        $sql = "SELECT e.id, e.full_name, e.employee_code, e.email, bu.name AS unit_name, d.name AS department_name
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

    /** @return array{0: string, 1: string} awal & akhir bulan (Y-m-d) */
    public function monthBounds(string $month): array
    {
        $ts = strtotime($month . '-01');
        if ($ts === false) {
            $ts = strtotime(date('Y-m-01'));
        }
        return [date('Y-m-01', $ts), date('Y-m-t', $ts)];
    }

    /** Bulan sebelumnya / berikutnya untuk navigasi. */
    public function adjacentMonth(string $month, int $delta): string
    {
        $ts = strtotime($month . '-01');
        if ($ts === false) {
            return date('Y-m');
        }
        return date('Y-m', strtotime(($delta >= 0 ? '+' : '-') . abs($delta) . ' month', $ts));
    }
}
