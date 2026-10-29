<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Paginator;
use App\Core\Settings;
use App\Core\Validator;
use App\Models\AttendanceModel;
use App\Models\DashboardModel;
use App\Models\EmployeeModel;
use App\Models\OrgModel;
use App\Models\ScheduleModel;

final class AttendanceController extends Controller
{
    private AttendanceModel $attendance;
    private OrgModel $org;
    private EmployeeModel $employees;
    private DashboardModel $dashboard;

    public function __construct()
    {
        $this->attendance = new AttendanceModel();
        $this->org = new OrgModel();
        $this->employees = new EmployeeModel();
        $this->dashboard = new DashboardModel();
    }

    /* =====================================================================
     | Portal karyawan — presensi PWA
     |===================================================================*/
    public function mine(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            $this->abort(401, 'Sesi berakhir.');
        }

        $month = (string) ($_GET['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        [$year, $mon] = array_map('intval', explode('-', $month));

        $context = $this->attendance->context((int) $employee['id']);
        $history = $this->attendance->mine((int) $employee['id'], $year, $mon);

        $this->view('attendance/mine', [
            'title'      => 'Presensi Saya',
            'pageTitle'  => 'Presensi Saya',
            'context'    => $context,
            'history'    => $history['rows'],
            'totals'     => $history['totals'],
            'summary'    => $this->attendance->myMonthSummary((int) $employee['id']),
            'month'      => $month,
            'monthLabel' => $this->monthLabel($month),
            'prevMonth'  => date('Y-m', strtotime($month . '-01 -1 month')),
            'nextMonth'  => date('Y-m', strtotime($month . '-01 +1 month')),
            'employee'   => $employee,
            'badges'     => $this->dashboard->badges($employee),
            'geofences'  => $this->attendance->unitGeofences(),
        ] + $this->withInput());
    }

    /** POST /attendance/punch — check-in / check-out (JSON from the PWA page). */
    public function punch(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            $this->json(['ok' => false, 'error' => 'Sesi berakhir. Silakan login ulang.'], 401);
        }

        $action = strtolower((string) input('action', 'check-in'));
        $payload = [
            'lat'      => input('lat'),
            'lng'      => input('lng'),
            'accuracy' => input('accuracy'),
            'photo'    => input('photo'),
            'note'     => (string) input('note', ''),
            'device'   => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ];

        $result = $action === 'check-out'
            ? $this->attendance->checkOut((int) $employee['id'], $payload)
            : $this->attendance->checkIn((int) $employee['id'], $payload);

        if (!$result['ok']) {
            $this->json([
                'ok'     => false,
                'error'  => $result['error'],
                'action' => $action,
            ], 422);
        }

        Audit::log('attendance.' . $action, 'attendance', (int) ($result['data']['id'] ?? 0), [
            'status'   => $result['data']['status'] ?? null,
            'distance' => $result['data']['distance'] ?? null,
        ]);

        $this->json([
            'ok'      => true,
            'action'  => $action,
            'message' => $result['message'],
            'data'    => $result['data'] ?? [],
        ]);
    }

    /* =====================================================================
     | HR — monitoring live
     |===================================================================*/
    public function live(): void
    {
        $date = (string) ($_GET['date'] ?? date('Y-m-d'));
        if (strtotime($date) === false) {
            $date = date('Y-m-d');
        }

        $filters = [
            'date' => $date,
            'unit' => (int) ($_GET['unit'] ?? 0),
            'type' => (string) ($_GET['type'] ?? ''),
            'q'    => trim((string) ($_GET['q'] ?? '')),
        ];

        $this->view('attendance/live', [
            'title'      => 'Live Monitoring Presensi',
            'pageTitle'  => 'Live Monitoring Presensi',
            'logs'       => $this->attendance->liveLog($filters, 80),
            'summary'    => $this->attendance->liveSummary($date),
            'missing'    => $this->attendance->notCheckedIn($date),
            'filters'    => $filters,
            'date'       => $date,
            'units'      => $this->org->units(),
            'badges'     => $this->dashboard->badges($this->employee() ?? []),
            'geofences'  => $this->attendance->unitGeofences(),
        ] + $this->withInput());
    }

    /* =====================================================================
     | HR — presensi harian
     |===================================================================*/
    public function daily(): void
    {
        $date = (string) ($_GET['date'] ?? date('Y-m-d'));
        if (strtotime($date) === false) {
            $date = date('Y-m-d');
        }

        $filters = [
            'date'       => $date,
            'unit'       => (int) ($_GET['unit'] ?? 0),
            'department' => (int) ($_GET['department'] ?? 0),
            'status'     => (string) ($_GET['status'] ?? ''),
            'q'          => trim((string) ($_GET['q'] ?? '')),
        ];

        if (isset($_GET['export'])) {
            $this->exportDaily($date, $filters);
        }

        $perPage = 25;
        $paginator = Paginator::fromRequest($this->attendance->countDaily($filters), $perPage);

        $this->view('attendance/daily', [
            'title'       => 'Presensi Harian',
            'pageTitle'   => 'Presensi Harian',
            'rows'        => $this->attendance->daily($date, $filters, $perPage, $paginator->offset())['rows'],
            'stats'       => $this->attendance->dailyStats($date, $filters),
            'paginator'   => $paginator,
            'filters'     => $filters,
            'date'        => $date,
            'units'       => $this->org->units(),
            'departments' => $this->employees->departmentsOf($filters['unit'] ?: null),
            'badges'      => $this->dashboard->badges($this->employee() ?? []),
        ] + $this->withInput());
    }

    /** POST /attendance/mark-absent — materialise ABSENT rows for a date. */
    public function markAbsent(): void
    {
        $date = (string) input('date', date('Y-m-d'));
        if (strtotime($date) === false) {
            flash('error', 'Tanggal tidak valid.');
            back('/attendance/daily');
        }

        $count = $this->attendance->markAbsent($date);
        Audit::log('attendance.mark_absent', 'attendance', null, ['date' => $date, 'rows' => $count]);
        flash($count > 0 ? 'success' : 'info', $count > 0
            ? $count . ' karyawan tanpa presensi ditandai ALPHA pada ' . tanggal($date) . '.'
            : 'Semua karyawan aktif sudah memiliki catatan presensi pada ' . tanggal($date) . '.');
        redirect('/attendance/daily?date=' . $date);
    }

    /* =====================================================================
     | HR — rekap bulanan
     |===================================================================*/
    public function rekap(): void
    {
        $month = (string) ($_GET['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        [$year, $mon] = array_map('intval', explode('-', $month));

        $filters = [
            'unit'       => (int) ($_GET['unit'] ?? 0),
            'department' => (int) ($_GET['department'] ?? 0),
            'q'          => trim((string) ($_GET['q'] ?? '')),
        ];

        if (isset($_GET['export'])) {
            $this->exportRekap($year, $mon, $filters);
        }

        $perPage = 25;
        $paginator = Paginator::fromRequest($this->attendance->countRekap($filters), $perPage);
        $result = $this->attendance->monthly($year, $mon, $filters, $perPage, $paginator->offset());

        $this->view('attendance/rekap', [
            'title'       => 'Rekap Presensi Bulanan',
            'pageTitle'   => 'Rekap Presensi',
            'rows'        => $result['rows'],
            'totals'      => $this->attendance->monthlyTotals($year, $mon, $filters),
            'trend'       => $this->attendance->dailyTrend($year, $mon, $filters),
            'paginator'   => $paginator,
            'filters'     => $filters,
            'month'       => $month,
            'monthLabel'  => $this->monthLabel($month),
            'prevMonth'   => date('Y-m', strtotime($month . '-01 -1 month')),
            'nextMonth'   => date('Y-m', strtotime($month . '-01 +1 month')),
            'units'       => $this->org->units(),
            'departments' => $this->employees->departmentsOf($filters['unit'] ?: null),
            'latePolicy'  => [
                'enabled' => (bool) Settings::get('attendance_late_deduction_enabled', true),
                'type'    => (string) Settings::get('attendance_late_deduction_type', 'PER_MINUTE'),
                'value'   => (float) Settings::get('attendance_late_deduction_value', 2500),
                'tolerance' => (int) Settings::get('attendance_late_tolerance', 15),
                'tiers'   => $this->attendance->lateTiers(),
                'tierMode' => $this->attendance->lateTierMode(),
            ],
            'badges'      => $this->dashboard->badges($this->employee() ?? []),
        ] + $this->withInput());
    }

    /* =====================================================================
     | HR — pengaturan presensi
     |===================================================================*/
    public function settings(): void
    {
        $leaveTypes = Database::all('SELECT id, code, name FROM leave_types ORDER BY name');

        $this->view('attendance/settings', [
            'title'      => 'Pengaturan Presensi',
            'pageTitle'  => 'Pengaturan Presensi',
            'settings'   => $this->attendance->settings(),
            'units'      => $this->attendance->unitGeofences(),
            'leaveTypes' => $leaveTypes,
            'badges'     => $this->dashboard->badges($this->employee() ?? []),
            'sample'     => $this->deductionSamples(),
            'tiers'      => $this->attendance->lateTiers(),
            'tierMode'   => $this->attendance->lateTierMode(),
        ] + $this->withInput());
    }

    public function saveSettings(): void
    {
        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('attendance_work_start', 'Jam masuk standar')
              ->required('attendance_work_end', 'Jam pulang standar')
              ->numeric('attendance_late_tolerance', 'Toleransi keterlambatan')
              ->numeric('attendance_late_deduction_value', 'Nilai denda')
              ->numeric('attendance_photo_max_width', 'Lebar maksimum foto')
              ->numeric('attendance_photo_quality', 'Kualitas foto')
              ->in('attendance_late_deduction_type', 'Tipe denda', ['PER_MINUTE', 'PER_DAY', 'FIXED', 'PERCENT', 'PROGRESSIVE'])
              ->numeric('attendance_late_tier_1_max', 'Batas tier 1')
              ->numeric('attendance_late_tier_2_max', 'Batas tier 2')
              ->numeric('attendance_late_tier_3_max', 'Batas tier 3')
              ->numeric('attendance_late_tier_1_amount', 'Denda tier 1')
              ->numeric('attendance_late_tier_2_amount', 'Denda tier 2')
              ->numeric('attendance_late_tier_3_amount', 'Denda tier 3')
              ->numeric('attendance_late_tier_4_amount', 'Denda tier 4');
        }, '/attendance/settings');

        if (($data['attendance_work_start'] ?? '') === ($data['attendance_work_end'] ?? '')) {
            flash('error', 'Jam masuk dan jam pulang standar tidak boleh sama.');
            back('/attendance/settings');
        }

        $quality = max(40, min(90, (int) $data['attendance_photo_quality']));
        $width = max(240, min(1600, (int) $data['attendance_photo_max_width']));

        // Batas tier harus naik: 0 < t1 < t2 < t3, minimal selisih 1 menit.
        $tier1 = max(1, min(600, (int) ($data['attendance_late_tier_1_max'] ?? 60)));
        $tier2 = max($tier1 + 1, min(1200, (int) ($data['attendance_late_tier_2_max'] ?? 120)));
        $tier3 = max($tier2 + 1, min(1800, (int) ($data['attendance_late_tier_3_max'] ?? 180)));
        $tierMode = strtoupper((string) ($data['attendance_late_tier_mode'] ?? 'DAILY'));
        if (!in_array($tierMode, ['DAILY', 'MONTHLY'], true)) {
            $tierMode = 'DAILY';
        }

        Settings::many([
            'attendance_work_start'             => substr((string) $data['attendance_work_start'], 0, 5),
            'attendance_work_end'               => substr((string) $data['attendance_work_end'], 0, 5),
            'attendance_late_tolerance'         => (string) max(0, (int) $data['attendance_late_tolerance']),
            'attendance_selfie_required'        => isset($data['attendance_selfie_required']) ? '1' : '0',
            'attendance_geofence_required'      => isset($data['attendance_geofence_required']) ? '1' : '0',
            'attendance_wfh_bypass_geofence'    => isset($data['attendance_wfh_bypass_geofence']) ? '1' : '0',
            'attendance_late_deduction_enabled' => isset($data['attendance_late_deduction_enabled']) ? '1' : '0',
            'attendance_late_deduction_type'    => (string) $data['attendance_late_deduction_type'],
            'attendance_late_deduction_value'   => (string) max(0, (float) ($data['attendance_late_deduction_value'] ?? 0)),
            'attendance_late_tier_mode'         => $tierMode,
            'attendance_late_tier_1_max'        => (string) $tier1,
            'attendance_late_tier_2_max'        => (string) $tier2,
            'attendance_late_tier_3_max'        => (string) $tier3,
            'attendance_late_tier_1_amount'     => (string) max(0, (float) ($data['attendance_late_tier_1_amount'] ?? 25000)),
            'attendance_late_tier_2_amount'     => (string) max(0, (float) ($data['attendance_late_tier_2_amount'] ?? 50000)),
            'attendance_late_tier_3_amount'     => (string) max(0, (float) ($data['attendance_late_tier_3_amount'] ?? 75000)),
            'attendance_late_tier_4_amount'     => (string) max(0, (float) ($data['attendance_late_tier_4_amount'] ?? 125000)),
            'attendance_absent_deduction_enabled' => isset($data['attendance_absent_deduction_enabled']) ? '1' : '0',
            'attendance_absent_deduct_salary'   => isset($data['attendance_absent_deduct_salary']) ? '1' : '0',
            'attendance_absent_leave_type_id'   => (string) (int) ($data['attendance_absent_leave_type_id'] ?? 0),
            'attendance_absent_leave_days'      => (string) max(0, (float) ($data['attendance_absent_leave_days'] ?? 1)),
            'attendance_photo_max_width'        => (string) $width,
            'attendance_photo_quality'          => (string) $quality,
        ], 'attendance');

        Audit::log('attendance.settings', 'setting', null, ['tolerance' => $data['attendance_late_tolerance']]);
        flash('success', 'Pengaturan presensi berhasil disimpan.');
        redirect('/attendance/settings');
    }

    /* =====================================================================
     | Export CSV (Excel-ready, dipisah titik koma agar rapi di locale ID)
     |===================================================================*/
    private function exportDaily(string $date, array $filters): never
    {
        $rows = $this->attendance->daily($date, $filters, 5000, 0)['rows'];

        $filename = 'presensi-harian-' . $date . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Tanggal', 'ID Karyawan', 'Nama', 'Unit', 'Departemen', 'Shift', 'Jam Masuk', 'Jam Keluar',
            'Status', 'Menit Terlambat', 'Menit Kerja', 'Jarak (m)', 'Sumber'], ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, [
                $date,
                $row['employee_code'],
                $row['full_name'],
                $row['unit_name'],
                $row['department_name'],
                $row['shift_name'],
                $row['check_in_at'] ? date('H:i', strtotime((string) $row['check_in_at'])) : '',
                $row['check_out_at'] ? date('H:i', strtotime((string) $row['check_out_at'])) : '',
                $row['status'] ?: 'NONE',
                (int) $row['late_minutes'],
                (int) $row['work_minutes'],
                $row['check_in_distance'] ?? '',
                $row['source'] ?: '',
            ], ';', '"', '');
        }
        fclose($out);
        exit;
    }

    private function exportRekap(int $year, int $month, array $filters): never
    {
        $result = $this->attendance->monthly($year, $month, $filters, 5000, 0);
        $filename = sprintf('rekap-presensi-%04d-%02d.csv', $year, $month);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Periode', 'ID Karyawan', 'Nama', 'Unit', 'Departemen', 'Hadir', 'Terlambat',
            'Alpha', 'Cuti', 'Menit Terlambat', 'Jam Kerja', 'Kehadiran (%)', 'Denda Terlambat (Rp)'], ';', '"', '');
        foreach ($result['rows'] as $row) {
            fputcsv($out, [
                sprintf('%04d-%02d', $year, $month),
                $row['employee_code'],
                $row['full_name'],
                $row['unit_name'],
                $row['department_name'],
                (int) $row['present_days'],
                (int) $row['late_days'],
                (int) $row['absent_days'],
                (int) $row['leave_days'],
                (int) $row['late_minutes'],
                $row['work_hours'],
                $row['attendance_rate'],
                (int) round((float) $row['late_deduction']),
            ], ';', '"', '');
        }
        fclose($out);
        exit;
    }

    /* --------------------------------------------------------------- helpers */
    private function monthLabel(string $month): string
    {
        $months = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni',
            '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];
        [$year, $mon] = explode('-', $month);
        return ($months[$mon] ?? $mon) . ' ' . $year;
    }

    /** Illustrative deduction table for the settings page. */
    private function deductionSamples(): array
    {
        $type = (string) Settings::get('attendance_late_deduction_type', 'PER_MINUTE');
        $value = (float) Settings::get('attendance_late_deduction_value', 2500);
        $model = $this->attendance;

        $samples = [];
        foreach ([5, 15, 30, 60, 120, 240] as $minutes) {
            $samples[] = [
                'minutes' => $minutes,
                'days'    => 3,
                'amount'  => $model->lateDeduction($minutes * 3, 3, $type, $value, [$minutes, $minutes, $minutes]),
            ];
        }
        return $samples;
    }
}
