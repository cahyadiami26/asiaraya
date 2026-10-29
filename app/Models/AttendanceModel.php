<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Settings;

/**
 * Presensi: check-in / check-out dengan geofence + selfie, monitoring live,
 * presensi harian, rekap bulanan, dan aturan denda.
 *
 * Aturan yang dipakai konsisten di seluruh aplikasi:
 *   status   PRESENT | LATE | ABSENT | LEAVE | WFH (operational_type)
 *   toleransi attendance_late_tolerance (menit) dari jam masuk shift
 *   denda    attendance_late_deduction_type PER_MINUTE | PER_DAY | FIXED
 */
final class AttendanceModel
{
    public const EARTH_RADIUS = 6371000;

    /* =====================================================================
     | Konteks presensi (dipakai halaman presensi & validasi server)
     |===================================================================*/
    public function context(int $employeeId, ?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');

        $employee = Database::one(
            "SELECT e.id, e.full_name, e.employee_code, e.email, e.photo, e.business_unit_id,
                    bu.name AS unit_name, bu.code AS unit_code, bu.latitude, bu.longitude,
                    bu.geofence_radius, d.name AS department_name, p.name AS position_name
             FROM employees e
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN positions p ON p.id = e.position_id
             WHERE e.id = ?",
            [$employeeId]
        );
        if (!$employee) {
            return [];
        }

        $schedule = (new ScheduleModel())->shiftOn($employeeId, $date);
        $attendance = $this->forDay($employeeId, $date);

        $unitLat = $employee['latitude'] !== null ? (float) $employee['latitude'] : null;
        $unitLng = $employee['longitude'] !== null ? (float) $employee['longitude'] : null;

        $shiftStart = $schedule['check_in_time'] ?? (string) Settings::get('attendance_work_start', '08:00');
        $shiftEnd = $schedule['check_out_time'] ?? (string) Settings::get('attendance_work_end', '17:00');
        $tolerance = (int) ($schedule['late_tolerance_minutes'] ?? Settings::get('attendance_late_tolerance', 15));

        return [
            'date'         => $date,
            'employee'     => $employee,
            'schedule'     => $schedule,
            'attendance'   => $attendance,
            'shift_start'  => substr((string) $shiftStart, 0, 5),
            'shift_end'    => substr((string) $shiftEnd, 0, 5),
            'tolerance'    => $tolerance,
            'unit'         => [
                'name'    => $employee['unit_name'],
                'code'    => $employee['unit_code'],
                'lat'     => $unitLat,
                'lng'     => $unitLng,
                'radius'  => (int) ($employee['geofence_radius'] ?? 50),
            ],
            'operational'  => $this->operationalType($schedule),
            'settings'     => [
                'geofence_required'  => (bool) Settings::get('attendance_geofence_required', true),
                'selfie_required'    => (bool) Settings::get('attendance_selfie_required', true),
                'wfh_bypass'         => (bool) Settings::get('attendance_wfh_bypass_geofence', true),
                'photo_max_width'    => (int) Settings::get('attendance_photo_max_width', 720),
                'photo_quality'      => (int) Settings::get('attendance_photo_quality', 70),
            ],
            'can_check_in'  => !$attendance || empty($attendance['check_in_at']),
            'can_check_out' => $attendance && !empty($attendance['check_in_at']) && empty($attendance['check_out_at']),
        ];
    }

    /** Shift with operational_type WFH lets the employee bypass the geofence (when configured). */
    private function operationalType(?array $schedule): string
    {
        $type = strtoupper((string) ($schedule['operational_type'] ?? 'NORMAL'));
        return in_array($type, ['WFH', 'FIELD', 'SHIFT', 'NORMAL'], true) ? $type : 'NORMAL';
    }

    public function forDay(int $employeeId, string $date): ?array
    {
        return Database::one(
            'SELECT * FROM attendance WHERE employee_id = ? AND work_date = ? ORDER BY id DESC LIMIT 1',
            [$employeeId, $date]
        );
    }

    /** Great-circle distance in metres. */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * self::EARTH_RADIUS * asin(min(1, sqrt($a)));
    }

    /* =====================================================================
     | Check-in / check-out
     |===================================================================*/
    /**
     * @param array{lat?:float,lng?:float,accuracy?:float,photo?:?string,note?:string,device?:string} $input
     * @return array{ok:bool,error?:string,data?:array,message?:string}
     */
    public function checkIn(int $employeeId, array $input, ?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $context = $this->context($employeeId, $date);
        if (!$context) {
            return ['ok' => false, 'error' => 'Data karyawan tidak ditemukan.'];
        }
        if (!$context['can_check_in']) {
            return ['ok' => false, 'error' => 'Anda sudah melakukan check-in hari ini pada '
                . substr((string) $context['attendance']['check_in_at'], 11, 5) . '.'];
        }

        $lat = isset($input['lat']) && $input['lat'] !== '' ? (float) $input['lat'] : null;
        $lng = isset($input['lng']) && $input['lng'] !== '' ? (float) $input['lng'] : null;
        $accuracy = isset($input['accuracy']) && $input['accuracy'] !== '' ? (int) round((float) $input['accuracy']) : null;

        if ($lat === null || $lng === null) {
            return ['ok' => false, 'error' => 'Lokasi GPS tidak terbaca. Aktifkan izin lokasi pada perangkat Anda.'];
        }
        if (abs($lat) > 90 || abs($lng) > 180) {
            return ['ok' => false, 'error' => 'Koordinat GPS tidak valid.'];
        }

        $geo = $this->evaluateGeofence($context, $lat, $lng);
        if (!$geo['allowed']) {
            return ['ok' => false, 'error' => $geo['message'], 'data' => $geo];
        }

        $photo = null;
        if (!empty($input['photo'])) {
            $stored = \App\Core\Upload::dataUrl(
                (string) $input['photo'],
                'attendance',
                $context['settings']['photo_max_width'],
                $context['settings']['photo_quality']
            );
            if ($stored['error']) {
                return ['ok' => false, 'error' => $stored['error']];
            }
            $photo = $stored['path'];
        } elseif ($context['settings']['selfie_required']) {
            return ['ok' => false, 'error' => 'Foto selfie wajib diambil untuk presensi.'];
        }

        $now = date('Y-m-d H:i:s');
        [$status, $lateMinutes] = $this->punctuality($context, $now);

        $payload = [
            'employee_id'      => $employeeId,
            'work_date'        => $date,
            'shift_id'         => $context['schedule']['shift_id'] ?? null,
            'operational_type' => $context['operational'],
            'check_in_at'      => $now,
            'check_in_lat'     => $lat,
            'check_in_lng'     => $lng,
            'check_in_photo'   => $photo,
            'check_in_distance'=> $geo['distance'] !== null ? (int) round($geo['distance']) : null,
            'check_in_accuracy'=> $accuracy,
            'check_in_status'  => $status === 'LATE' ? 'LATE' : 'ON_TIME',
            'check_in_note'    => substr(trim((string) ($input['note'] ?? '')), 0, 255) ?: null,
            'late_minutes'     => $lateMinutes,
            'status'           => $status,
            'source'           => 'PWA',
            'ip_address'       => $_SERVER['REMOTE_ADDR'] ?? null,
            'device_info'      => substr((string) ($input['device'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255),
            'created_at'       => $now,
            'updated_at'       => $now,
        ];

        $id = Database::insert('attendance', $payload);

        $message = $status === 'LATE'
            ? 'Check-in tercatat ' . $lateMinutes . ' menit setelah jam masuk (' . $context['shift_start'] . ').'
            : 'Check-in tepat waktu. Selamat bekerja!';

        return [
            'ok'      => true,
            'data'    => ['id' => $id, 'status' => $status, 'late_minutes' => $lateMinutes] + $geo,
            'message' => $message,
        ];
    }

    /**
     * @param array{lat?:float,lng?:float,accuracy?:float,photo?:?string,note?:string,device?:string} $input
     * @return array{ok:bool,error?:string,data?:array,message?:string}
     */
    public function checkOut(int $employeeId, array $input, ?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $context = $this->context($employeeId, $date);
        if (!$context || !$context['attendance']) {
            return ['ok' => false, 'error' => 'Belum ada check-in hari ini.'];
        }
        $record = $context['attendance'];
        if (!empty($record['check_out_at'])) {
            return ['ok' => false, 'error' => 'Anda sudah melakukan check-out pada ' . substr((string) $record['check_out_at'], 11, 5) . '.'];
        }

        $lat = isset($input['lat']) && $input['lat'] !== '' ? (float) $input['lat'] : null;
        $lng = isset($input['lng']) && $input['lng'] !== '' ? (float) $input['lng'] : null;
        if ($lat !== null && $lng !== null) {
            $geo = $this->evaluateGeofence($context, $lat, $lng);
            if (!$geo['allowed']) {
                return ['ok' => false, 'error' => $geo['message'], 'data' => $geo];
            }
        } else {
            $geo = ['distance' => null, 'allowed' => true, 'message' => ''];
        }

        $photo = null;
        if (!empty($input['photo'])) {
            $stored = \App\Core\Upload::dataUrl(
                (string) $input['photo'],
                'attendance',
                $context['settings']['photo_max_width'],
                $context['settings']['photo_quality']
            );
            if ($stored['error']) {
                return ['ok' => false, 'error' => $stored['error']];
            }
            $photo = $stored['path'];
        }

        $now = date('Y-m-d H:i:s');
        $checkIn = strtotime((string) $record['check_in_at']);
        $minutes = max(0, (int) round((strtotime($now) - $checkIn) / 60) - (int) ($context['schedule']['break_minutes'] ?? 0));

        $shiftEnd = $this->shiftEndTimestamp($context, $date);
        $earlyMinutes = $shiftEnd !== null ? max(0, (int) round(($shiftEnd - strtotime($now)) / 60)) : 0;

        Database::update('attendance', [
            'check_out_at'        => $now,
            'check_out_lat'       => $lat,
            'check_out_lng'       => $lng,
            'check_out_photo'     => $photo,
            'check_out_distance'  => $geo['distance'] !== null ? (int) round($geo['distance']) : null,
            'early_leave_minutes' => $earlyMinutes,
            'work_minutes'        => $minutes,
            'updated_at'          => $now,
        ], 'id = ?', [(int) $record['id']]);

        $hours = floor($minutes / 60);
        $rest = $minutes % 60;
        $message = 'Check-out tercatat. Durasi kerja ' . $hours . ' jam ' . $rest . ' menit'
            . ($earlyMinutes > 0 ? ' (pulang ' . $earlyMinutes . ' menit lebih awal).' : '.');

        return [
            'ok'      => true,
            'data'    => ['work_minutes' => $minutes, 'early_leave_minutes' => $earlyMinutes] + $geo,
            'message' => $message,
        ];
    }

    /** Geofence decision for a position. */
    public function evaluateGeofence(array $context, float $lat, float $lng): array
    {
        $unit = $context['unit'];
        if ($unit['lat'] === null || $unit['lng'] === null) {
            return [
                'allowed'  => true,
                'distance' => null,
                'message'  => 'Koordinat unit belum diatur — presensi diterima tanpa validasi radius.',
                'bypassed' => true,
            ];
        }

        $distance = self::distance($lat, $lng, $unit['lat'], $unit['lng']);
        $radius = max(10, (int) $unit['radius']);
        $within = $distance <= $radius;

        $wfhBypass = $context['settings']['wfh_bypass'] && in_array($context['operational'], ['WFH', 'FIELD'], true);
        $enforced = $context['settings']['geofence_required'];

        if ($within) {
            return [
                'allowed'  => true,
                'distance' => round($distance, 1),
                'message'  => 'Anda berada ' . number_format($distance, 0, ',', '.') . ' m dari titik kantor (radius ' . $radius . ' m).',
                'bypassed' => false,
            ];
        }

        if ($wfhBypass) {
            return [
                'allowed'  => true,
                'distance' => round($distance, 1),
                'message'  => 'Presensi WFH/lapangan — validasi radius dilewati (' . number_format($distance, 0, ',', '.') . ' m dari kantor).',
                'bypassed' => true,
            ];
        }

        if (!$enforced) {
            return [
                'allowed'  => true,
                'distance' => round($distance, 1),
                'message'  => 'Di luar radius, tetapi validasi geofence dinonaktifkan oleh HR.',
                'bypassed' => true,
            ];
        }

        return [
            'allowed'  => false,
            'distance' => round($distance, 1),
            'message'  => 'Anda berada ' . number_format($distance, 0, ',', '.') . ' m dari ' . ($unit['name'] ?: 'kantor')
                . ', di luar radius ' . $radius . ' m. Mendekatlah ke lokasi kantor untuk melakukan presensi.',
            'bypassed' => false,
        ];
    }

    /** @return array{0:string,1:int} [status, late minutes] */
    private function punctuality(array $context, string $now): array
    {
        $start = $this->shiftStartTimestamp($context, (string) $context['date']);
        $tolerance = (int) $context['tolerance'];
        $lateMinutes = max(0, (int) round((strtotime($now) - $start) / 60));
        $effective = max(0, $lateMinutes - $tolerance);

        return [$effective > 0 ? 'LATE' : 'PRESENT', $lateMinutes];
    }

    private function shiftStartTimestamp(array $context, string $date): int
    {
        $timestamp = strtotime($date . ' ' . $context['shift_start'] . ':00');
        // night shift starting after 18:00 keeps the same date
        return $timestamp !== false ? $timestamp : time();
    }

    private function shiftEndTimestamp(array $context, string $date): ?int
    {
        $start = $this->shiftStartTimestamp($context, $date);
        $end = strtotime($date . ' ' . $context['shift_end'] . ':00');
        if ($end === false) {
            return null;
        }
        if ($end <= $start) {
            $end += 24 * 3600;
        }
        return $end;
    }

    /* =====================================================================
     | Monitoring live
     |===================================================================*/
    public function liveLog(array $filters = [], int $limit = 60): array
    {
        $date = $filters['date'] ?? date('Y-m-d');
        $clauses = ['a.work_date = ?'];
        $params = [$date];

        if (!empty($filters['unit'])) {
            $clauses[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (!empty($filters['type'])) {
            $clauses[] = 'a.status = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['q'])) {
            $clauses[] = '(e.full_name LIKE ? OR e.employee_code LIKE ?)';
            $params[] = Database::like((string) $filters['q']);
            $params[] = Database::like((string) $filters['q']);
        }

        return Database::all(
            "SELECT a.*, e.full_name, e.employee_code, e.email, e.photo,
                    bu.name AS unit_name, bu.code AS unit_code, bu.latitude AS unit_lat, bu.longitude AS unit_lng,
                    bu.geofence_radius,
                    d.name AS department_name, s.name AS shift_name, s.check_in_time, s.check_out_time
             FROM attendance a
             JOIN employees e ON e.id = a.employee_id
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN work_shifts s ON s.id = a.shift_id
             WHERE " . implode(' AND ', $clauses) . "
             ORDER BY COALESCE(a.check_out_at, a.check_in_at) DESC, a.id DESC
             LIMIT " . max(1, min(300, $limit)),
            $params
        );
    }

    /** Live counters + per-unit presence for the monitoring header. */
    public function liveSummary(?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');

        $active = (int) Database::value("SELECT COUNT(*) FROM employees WHERE flag = 'ACTIVE'");
        $rows = Database::all(
            "SELECT a.status, COUNT(*) AS total,
                    SUM(CASE WHEN a.check_out_at IS NULL THEN 1 ELSE 0 END) AS on_site
             FROM attendance a
             JOIN employees e ON e.id = a.employee_id AND e.flag = 'ACTIVE'
             WHERE a.work_date = ?
             GROUP BY a.status",
            [$date]
        );

        $counts = ['PRESENT' => 0, 'LATE' => 0, 'ABSENT' => 0, 'LEAVE' => 0, 'on_site' => 0];
        foreach ($rows as $row) {
            $key = $row['status'];
            if (isset($counts[$key])) {
                $counts[$key] += (int) $row['total'];
            }
            if ($key === 'PRESENT' || $key === 'LATE') {
                $counts['on_site'] += (int) $row['on_site'];
            }
        }

        $present = $counts['PRESENT'] + $counts['LATE'];
        $noRecord = max(0, $active - ($present + $counts['ABSENT'] + $counts['LEAVE']));

        $units = Database::all(
            "SELECT bu.id, bu.code, bu.name, bu.geofence_radius,
                    (SELECT COUNT(*) FROM employees e WHERE e.business_unit_id = bu.id AND e.flag = 'ACTIVE') AS headcount,
                    (SELECT COUNT(*) FROM attendance a JOIN employees e2 ON e2.id = a.employee_id
                        WHERE e2.business_unit_id = bu.id AND a.work_date = ? AND a.status IN ('PRESENT','LATE')) AS present,
                    (SELECT COUNT(*) FROM attendance a JOIN employees e3 ON e3.id = a.employee_id
                        WHERE e3.business_unit_id = bu.id AND a.work_date = ? AND a.status = 'LATE') AS late,
                    (SELECT COUNT(*) FROM attendance a JOIN employees e4 ON e4.id = a.employee_id
                        WHERE e4.business_unit_id = bu.id AND a.work_date = ? AND a.check_out_at IS NULL AND a.status IN ('PRESENT','LATE')) AS on_site
             FROM business_units bu
             WHERE bu.is_active = 1
             ORDER BY bu.id",
            [$date, $date, $date]
        );
        foreach ($units as &$unit) {
            $head = max(1, (int) $unit['headcount']);
            $unit['rate'] = round(((int) $unit['present'] / $head) * 100, 1);
        }
        unset($unit);

        return [
            'date'      => $date,
            'active'    => $active,
            'counts'    => $counts,
            'present'   => $present,
            'no_record' => $noRecord,
            'rate'      => $active > 0 ? round(($present / $active) * 100, 1) : 0.0,
            'units'     => $units,
            'last_sync' => date('H:i:s'),
        ];
    }

    /* =====================================================================
     | Presensi harian
     |===================================================================*/
    public function daily(string $date, array $filters, int $limit = 60, int $offset = 0): array
    {
        [$where, $params] = $this->dailyWhere($filters);
        $rows = Database::all(
            "SELECT e.id, e.full_name, e.employee_code, e.email, e.photo, e.business_unit_id, e.department_id,
                    bu.name AS unit_name, d.name AS department_name, p.name AS position_name,
                    a.id AS attendance_id, a.check_in_at, a.check_out_at, a.check_in_lat, a.check_in_lng,
                    a.check_in_photo, a.check_in_distance, a.check_in_status, a.late_minutes,
                    a.work_minutes, a.early_leave_minutes, a.status, a.source, a.operational_type,
                    s.name AS shift_name, s.check_in_time, s.check_out_time, s.late_tolerance_minutes
             FROM employees e
             LEFT JOIN attendance a ON a.employee_id = e.id AND a.work_date = ?
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN positions p ON p.id = e.position_id
             LEFT JOIN work_shifts s ON s.id = a.shift_id
             WHERE {$where}
             ORDER BY (a.check_in_at IS NULL), a.check_in_at DESC, e.full_name
             LIMIT {$limit} OFFSET {$offset}",
            array_merge([$date], $params)
        );

        return ['rows' => $rows, 'total' => $this->countDaily($filters)];
    }

    public function countDaily(array $filters): int
    {
        [$where, $params] = $this->dailyWhere($filters);
        return (int) Database::value("SELECT COUNT(*) FROM employees e WHERE {$where}", $params);
    }

    private function dailyWhere(array $filters): array
    {
        $clauses = ["e.flag = 'ACTIVE'"];
        $params = [];
        if (!empty($filters['unit'])) {
            $clauses[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (!empty($filters['department'])) {
            $clauses[] = 'e.department_id = ?';
            $params[] = (int) $filters['department'];
        }
        if (!empty($filters['q'])) {
            $clauses[] = '(e.full_name LIKE ? OR e.employee_code LIKE ?)';
            $params[] = Database::like((string) $filters['q']);
            $params[] = Database::like((string) $filters['q']);
        }
        if (!empty($filters['status'])) {
            $status = (string) $filters['status'];
            if ($status === 'NONE') {
                $clauses[] = 'NOT EXISTS (SELECT 1 FROM attendance a2 WHERE a2.employee_id = e.id AND a2.work_date = ?)';
                $params[] = (string) ($filters['date'] ?? date('Y-m-d'));
            } elseif ($status === 'ON_SITE') {
                $clauses[] = "EXISTS (SELECT 1 FROM attendance a3 WHERE a3.employee_id = e.id AND a3.work_date = ?
                              AND a3.check_in_at IS NOT NULL AND a3.check_out_at IS NULL AND a3.status IN ('PRESENT','LATE'))";
                $params[] = (string) ($filters['date'] ?? date('Y-m-d'));
            } else {
                $clauses[] = "EXISTS (SELECT 1 FROM attendance a4 WHERE a4.employee_id = e.id AND a4.work_date = ? AND a4.status = ?)";
                $params[] = (string) ($filters['date'] ?? date('Y-m-d'));
                $params[] = $status;
            }
        }
        return [implode(' AND ', $clauses), $params];
    }

    /** Daily headline numbers for the selected date. */
    public function dailyStats(string $date, array $filters = []): array
    {
        $filters['date'] = $date;
        $rows = $this->daily($date, $filters, 5000, 0)['rows'];

        $stats = ['total' => count($rows), 'present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'none' => 0,
                  'on_site' => 0, 'late_minutes' => 0, 'work_minutes' => 0];
        foreach ($rows as $row) {
            $status = $row['status'];
            if ($status === 'PRESENT') {
                $stats['present']++;
            } elseif ($status === 'LATE') {
                $stats['late']++;
                $stats['present']++;
            } elseif ($status === 'ABSENT') {
                $stats['absent']++;
            } elseif ($status === 'LEAVE') {
                $stats['leave']++;
            } else {
                $stats['none']++;
            }
            $stats['late_minutes'] += (int) $row['late_minutes'];
            $stats['work_minutes'] += (int) $row['work_minutes'];
            if (!empty($row['check_in_at']) && empty($row['check_out_at'])) {
                $stats['on_site']++;
            }
        }
        $stats['rate'] = $stats['total'] > 0 ? round(($stats['present'] / $stats['total']) * 100, 1) : 0.0;
        return $stats;
    }

    /* =====================================================================
     | Rekap bulanan
     |===================================================================*/
    public function monthly(int $year, int $month, array $filters, int $limit = 60, int $offset = 0): array
    {
        [$start, $end] = $this->monthBounds($year, $month);
        [$where, $params] = $this->rekapWhere($filters);

        $rows = Database::all(
            "SELECT e.id, e.full_name, e.employee_code, e.email, e.photo, e.basic_salary,
                    bu.name AS unit_name, d.name AS department_name, p.name AS position_name,
                    (SELECT COUNT(*) FROM attendance a WHERE a.employee_id = e.id AND a.work_date BETWEEN ? AND ? AND a.status IN ('PRESENT','LATE')) AS present_days,
                    (SELECT COUNT(*) FROM attendance a WHERE a.employee_id = e.id AND a.work_date BETWEEN ? AND ? AND a.status = 'LATE') AS late_days,
                    (SELECT COALESCE(SUM(a.late_minutes), 0) FROM attendance a WHERE a.employee_id = e.id AND a.work_date BETWEEN ? AND ?) AS late_minutes,
                    (SELECT COUNT(*) FROM attendance a WHERE a.employee_id = e.id AND a.work_date BETWEEN ? AND ? AND a.status = 'ABSENT') AS absent_days,
                    (SELECT COUNT(*) FROM attendance a WHERE a.employee_id = e.id AND a.work_date BETWEEN ? AND ? AND a.status = 'LEAVE') AS leave_days,
                    (SELECT COALESCE(SUM(a.work_minutes), 0) FROM attendance a WHERE a.employee_id = e.id AND a.work_date BETWEEN ? AND ?) AS work_minutes,
                    (SELECT COUNT(*) FROM attendance a WHERE a.employee_id = e.id AND a.work_date BETWEEN ? AND ? AND a.check_out_at IS NULL AND a.status IN ('PRESENT','LATE')) AS missing_checkout
             FROM employees e
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN positions p ON p.id = e.position_id
             WHERE {$where}
             ORDER BY e.full_name
             LIMIT {$limit} OFFSET {$offset}",
            array_merge([$start, $end, $start, $end, $start, $end, $start, $end, $start, $end, $start, $end, $start, $end], $params)
        );

        $lateValue = (float) Settings::get('attendance_late_deduction_value', 2500);
        $lateType = (string) Settings::get('attendance_late_deduction_type', 'PER_MINUTE');
        $deductionEnabled = (bool) Settings::get('attendance_late_deduction_enabled', true);

        foreach ($rows as &$row) {
            $row['attendance_rate'] = $this->rate((int) $row['present_days'], (int) $row['absent_days'], (int) $row['leave_days']);
            $row['work_hours'] = round(((int) $row['work_minutes']) / 60, 1);
            $row['late_deduction'] = $deductionEnabled
                ? round($this->lateDeduction((int) $row['late_minutes'], (int) $row['late_days'], $lateType, $lateValue), 2)
                : 0.0;
        }
        unset($row);

        return ['rows' => $rows, 'total' => $this->countRekap($filters), 'start' => $start, 'end' => $end];
    }

    public function countRekap(array $filters): int
    {
        [$where, $params] = $this->rekapWhere($filters);
        return (int) Database::value("SELECT COUNT(*) FROM employees e WHERE {$where}", $params);
    }

    private function rekapWhere(array $filters): array
    {
        $clauses = ["e.flag = 'ACTIVE'"];
        $params = [];
        if (!empty($filters['unit'])) {
            $clauses[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (!empty($filters['department'])) {
            $clauses[] = 'e.department_id = ?';
            $params[] = (int) $filters['department'];
        }
        if (!empty($filters['q'])) {
            $clauses[] = '(e.full_name LIKE ? OR e.employee_code LIKE ?)';
            $params[] = Database::like((string) $filters['q']);
            $params[] = Database::like((string) $filters['q']);
        }
        return [implode(' AND ', $clauses), $params];
    }

    private function rate(int $present, int $absent, int $leave): float
    {
        $base = $present + $absent + $leave;
        return $base > 0 ? round(($present / $base) * 100, 1) : 0.0;
    }

    /** Late deduction for one employee in a period. */
    public function lateDeduction(int $lateMinutes, int $lateDays, string $type, float $value): float
    {
        return match (strtoupper($type)) {
            'PER_DAY'  => $lateDays * $value,
            'FIXED'    => $lateDays > 0 ? $value : 0.0,
            'PERCENT'  => 0.0, // handled at payroll level (percentage of basic salary)
            default    => $lateMinutes * $value,
        };
    }

    /** @return array{0:string,1:string} */
    public function monthBounds(int $year, int $month): array
    {
        $year = max(2000, min(2100, $year));
        $month = max(1, min(12, $month));
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));
        return [$start, $end];
    }

    /** Monthly totals for the rekap header. */
    public function monthlyTotals(int $year, int $month, array $filters = []): array
    {
        [$start, $end] = $this->monthBounds($year, $month);
        $where = ["e.flag = 'ACTIVE'"];
        $params = [$start, $end];
        if (!empty($filters['unit'])) {
            $where[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (!empty($filters['department'])) {
            $where[] = 'e.department_id = ?';
            $params[] = (int) $filters['department'];
        }

        $row = Database::one(
            "SELECT COUNT(*) AS employees,
                    SUM(CASE WHEN a.status IN ('PRESENT','LATE') THEN 1 ELSE 0 END) AS present_records,
                    SUM(CASE WHEN a.status = 'LATE' THEN 1 ELSE 0 END) AS late_records,
                    SUM(CASE WHEN a.status = 'ABSENT' THEN 1 ELSE 0 END) AS absent_records,
                    SUM(CASE WHEN a.status = 'LEAVE' THEN 1 ELSE 0 END) AS leave_records,
                    COALESCE(SUM(a.late_minutes), 0) AS late_minutes,
                    COALESCE(SUM(a.work_minutes), 0) AS work_minutes
             FROM employees e
             LEFT JOIN attendance a ON a.employee_id = e.id AND a.work_date BETWEEN ? AND ?
             WHERE " . implode(' AND ', $where),
            $params
        ) ?? [];

        $present = (int) ($row['present_records'] ?? 0);
        $absent = (int) ($row['absent_records'] ?? 0);
        $leave = (int) ($row['leave_records'] ?? 0);

        return [
            'employees'   => (int) ($row['employees'] ?? 0),
            'present'     => $present,
            'late'        => (int) ($row['late_records'] ?? 0),
            'absent'      => $absent,
            'leave'       => $leave,
            'late_minutes'=> (int) ($row['late_minutes'] ?? 0),
            'work_hours'  => round(((int) ($row['work_minutes'] ?? 0)) / 60, 1),
            'rate'        => $this->rate($present, $absent, $leave),
            'start'       => $start,
            'end'         => $end,
        ];
    }

    /** Daily presence trend for the rekap chart. */
    public function dailyTrend(int $year, int $month, array $filters = []): array
    {
        [$start, $end] = $this->monthBounds($year, $month);
        $clauses = ["a.work_date BETWEEN ? AND ?"];
        $params = [$start, $end];
        if (!empty($filters['unit'])) {
            $clauses[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }

        $rows = Database::all(
            "SELECT a.work_date,
                    SUM(CASE WHEN a.status IN ('PRESENT','LATE') THEN 1 ELSE 0 END) AS present,
                    SUM(CASE WHEN a.status = 'LATE' THEN 1 ELSE 0 END) AS late,
                    SUM(CASE WHEN a.status = 'ABSENT' THEN 1 ELSE 0 END) AS absent,
                    SUM(CASE WHEN a.status = 'LEAVE' THEN 1 ELSE 0 END) AS leave_count
             FROM attendance a
             JOIN employees e ON e.id = a.employee_id
             WHERE " . implode(' AND ', $clauses) . '
             GROUP BY a.work_date
             ORDER BY a.work_date',
            $params
        );

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[$row['work_date']] = $row;
        }

        $trend = [];
        for ($day = 1; $day <= (int) date('t', strtotime($start)); $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $entry = $byDate[$date] ?? null;
            $trend[] = [
                'date'    => $date,
                'day'     => $day,
                'dow'     => (int) date('N', strtotime($date)),
                'present' => (int) ($entry['present'] ?? 0),
                'late'    => (int) ($entry['late'] ?? 0),
                'absent'  => (int) ($entry['absent'] ?? 0),
                'leave'   => (int) ($entry['leave_count'] ?? 0),
            ];
        }
        return $trend;
    }

    /** Personal attendance history for the employee portal. */
    public function mine(int $employeeId, int $year, int $month): array
    {
        [$start, $end] = $this->monthBounds($year, $month);
        $rows = Database::all(
            'SELECT a.*, s.name AS shift_name, s.check_in_time, s.check_out_time
             FROM attendance a
             LEFT JOIN work_shifts s ON s.id = a.shift_id
             WHERE a.employee_id = ? AND a.work_date BETWEEN ? AND ?
             ORDER BY a.work_date DESC',
            [$employeeId, $start, $end]
        );

        $totals = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'late_minutes' => 0, 'work_minutes' => 0];
        foreach ($rows as $row) {
            if (in_array($row['status'], ['PRESENT', 'LATE'], true)) {
                $totals['present']++;
            }
            if ($row['status'] === 'LATE') {
                $totals['late']++;
            }
            if ($row['status'] === 'ABSENT') {
                $totals['absent']++;
            }
            if ($row['status'] === 'LEAVE') {
                $totals['leave']++;
            }
            $totals['late_minutes'] += (int) $row['late_minutes'];
            $totals['work_minutes'] += (int) $row['work_minutes'];
        }

        return ['rows' => $rows, 'totals' => $totals, 'start' => $start, 'end' => $end];
    }

    /** Today's streak / summary card for the employee portal. */
    public function myMonthSummary(int $employeeId, ?string $month = null): array
    {
        $month = $month ?: date('Y-m');
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));

        $row = Database::one(
            "SELECT SUM(CASE WHEN status IN ('PRESENT','LATE') THEN 1 ELSE 0 END) AS present,
                    SUM(CASE WHEN status = 'LATE' THEN 1 ELSE 0 END) AS late,
                    SUM(CASE WHEN status = 'ABSENT' THEN 1 ELSE 0 END) AS absent,
                    COALESCE(SUM(late_minutes), 0) AS late_minutes,
                    COALESCE(SUM(work_minutes), 0) AS work_minutes
             FROM attendance WHERE employee_id = ? AND work_date BETWEEN ? AND ?",
            [$employeeId, $start, $end]
        ) ?? [];

        return [
            'present'      => (int) ($row['present'] ?? 0),
            'late'         => (int) ($row['late'] ?? 0),
            'absent'       => (int) ($row['absent'] ?? 0),
            'late_minutes' => (int) ($row['late_minutes'] ?? 0),
            'work_hours'   => round(((int) ($row['work_minutes'] ?? 0)) / 60, 1),
        ];
    }

    /* =====================================================================
     | Pengaturan & utilitas
     |===================================================================*/
    public function settingsKeys(): array
    {
        return [
            'attendance_work_start', 'attendance_work_end', 'attendance_late_tolerance',
            'attendance_selfie_required', 'attendance_geofence_required', 'attendance_wfh_bypass_geofence',
            'attendance_late_deduction_enabled', 'attendance_late_deduction_type', 'attendance_late_deduction_value',
            'attendance_absent_deduction_enabled', 'attendance_absent_deduct_salary',
            'attendance_absent_leave_type_id', 'attendance_absent_leave_days',
            'attendance_photo_max_width', 'attendance_photo_quality',
        ];
    }

    public function settings(): array
    {
        $values = [];
        foreach ($this->settingsKeys() as $key) {
            $values[$key] = Settings::get($key);
        }
        return $values;
    }

    /** Units with coordinates configured (settings helper). */
    public function unitGeofences(): array
    {
        return Database::all(
            'SELECT id, code, name, latitude, longitude, geofence_radius, is_active
             FROM business_units ORDER BY is_active DESC, id'
        );
    }

    /** Create ABSENT rows for employees with no record on the given date. */
    public function markAbsent(string $date): int
    {
        $employees = Database::all(
            "SELECT e.id FROM employees e
             WHERE e.flag = 'ACTIVE'
               AND NOT EXISTS (SELECT 1 FROM attendance a WHERE a.employee_id = e.id AND a.work_date = ?)",
            [$date]
        );

        $count = 0;
        foreach ($employees as $employee) {
            Database::insert('attendance', [
                'employee_id' => (int) $employee['id'],
                'work_date'   => $date,
                'status'      => 'ABSENT',
                'source'      => 'SYSTEM',
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
            $count++;
        }
        return $count;
    }

    /** Employees without any attendance row today (monitoring hint). */
    public function notCheckedIn(?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        return Database::all(
            "SELECT e.id, e.full_name, e.employee_code, e.photo, e.email, bu.name AS unit_name
             FROM employees e
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             WHERE e.flag = 'ACTIVE'
               AND NOT EXISTS (SELECT 1 FROM attendance a WHERE a.employee_id = e.id AND a.work_date = ?)
             ORDER BY e.full_name
             LIMIT 12",
            [$date]
        );
    }
}
