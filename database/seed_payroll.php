<?php
declare(strict_types=1);

use App\Core\Database;
use App\Models\PayrollModel;
use App\Models\TaxModel;

/**
 * Data demo Batch 20 — payroll, PPh 21 & BPJS.
 *
 * Modul pajak/BPJS membaca periode payroll yang sudah final, sementara instalasi
 * demo belum memiliki payroll sama sekali. Seeder ini karena itu:
 *
 *   1. melengkapi presensi bulan-bulan berjalan yang belum punya data (agar
 *      slip gaji punya hari hadir, lembur, dan denda keterlambatan yang wajar),
 *   2. membuat periode payroll per unit lalu menjalankan mesin kalkulasi asli
 *      (`PayrollModel::calculate()`), sehingga PPh 21 & BPJS dihitung dengan
 *      mesin yang sama seperti pemakaian nyata,
 *   3. memindahkan status periode mengikuti alur nyata (CALCULATED →
 *      FINANCE_APPROVED → PAID) beserta tanda tangan HR/Finance, dan
 *   4. menerbitkan sebagian bukti potong 1721-A1 supaya halaman pajak, PDF,
 *      serta verifikasi QR dapat diuji — sisanya dibiarkan "belum terbit"
 *      agar aksi "Terbitkan Semua" tetap punya pekerjaan.
 *
 * @return array<int,string> baris log
 */
return static function (int $months = 9): array
{
    $log = [];

    if (!Database::tableExists('payroll_periods') || !Database::tableExists('payroll_items')) {
        return ['Batch 20 dilewati: tabel payroll belum tersedia.'];
    }
    if ((int) Database::value('SELECT COUNT(*) FROM payroll_periods') > 0) {
        return ['Data demo payroll sudah ada — seeding Batch 20 dilewati.'];
    }

    $now = date('Y-m-d H:i:s');
    $year = (int) date('Y');
    $currentMonth = (int) date('n');
    $today = date('Y-m-d');

    // Sembilan bulan (Februari–Oktober) memberi gambaran setahun berjalan tanpa
    // membuat dump demo membengkak; bulan berjalan tetap berstatus terhitung
    // agar alur payroll terlihat hidup.
    $months = max(2, min(12, $months));
    $firstMonth = max(1, $currentMonth - $months + 1);

    // Komponen tunjangan dibuat "berlaku bulan ini" oleh seeder utama; geser ke
    // awal rentang payroll agar setiap masa punya komposisi gaji yang sama.
    $rangeStart = sprintf('%04d-%02d-01', $year, $firstMonth);
    Database::run(
        'UPDATE employee_components SET effective_date = ? WHERE effective_date > ?',
        [$rangeStart, $rangeStart]
    );

    $hr = Database::one("SELECT id, full_name FROM employees WHERE role = 'HR' ORDER BY id LIMIT 1") ?? [];
    $fin = Database::one("SELECT id, full_name FROM employees WHERE role = 'FIN' ORDER BY id LIMIT 1") ?? [];
    $hrId = (int) ($hr['id'] ?? 0);
    $finId = (int) ($fin['id'] ?? 0);
    $hrName = (string) ($hr['full_name'] ?? 'HR Manager');
    $finName = (string) ($fin['full_name'] ?? 'Finance Manager');

    /* ------------------------------------------------ 1. presensi pendukung */

    $shifts = [];
    foreach (Database::all('SELECT * FROM work_shifts WHERE is_active = 1 ORDER BY id') as $shift) {
        $shifts[(int) $shift['id']] = $shift;
    }
    $defaultShift = $shifts ? reset($shifts) : null;
    $shiftByUnit = [];
    foreach ($shifts as $shift) {
        $unitId = (int) ($shift['business_unit_id'] ?? 0);
        if ($unitId > 0 && !isset($shiftByUnit[$unitId])) {
            $shiftByUnit[$unitId] = $shift;
        }
    }

    $scheduleShift = [];
    foreach (Database::all('SELECT employee_id, shift_id FROM schedules ORDER BY start_date') as $row) {
        $scheduleShift[(int) $row['employee_id']] = (int) $row['shift_id'];
    }

    $employees = Database::all(
        "SELECT id, business_unit_id, full_name FROM employees WHERE flag = 'ACTIVE' ORDER BY id"
    );

    // Hari libur nasional 2026 (seeder libur utama berjalan setelah blok ini).
    $holidays = [
        sprintf('%04d-01-01', $year) => true,
        sprintf('%04d-03-20', $year) => true,
        sprintf('%04d-03-21', $year) => true,
        sprintf('%04d-04-03', $year) => true,
        sprintf('%04d-05-01', $year) => true,
        sprintf('%04d-05-14', $year) => true,
        sprintf('%04d-06-01', $year) => true,
        sprintf('%04d-08-17', $year) => true,
        sprintf('%04d-12-25', $year) => true,
    ];
    foreach (Database::all('SELECT holiday_date FROM holidays') as $row) {
        $holidays[(string) $row['holiday_date']] = true;
    }

    $rangeStartDate = sprintf('%04d-%02d-01', $year, $firstMonth);
    $existing = [];
    foreach (Database::all(
        'SELECT employee_id, work_date FROM attendance WHERE work_date >= ? AND work_date <= ?',
        [$rangeStartDate, $today]
    ) as $row) {
        $existing[(int) $row['employee_id'] . '|' . (string) $row['work_date']] = true;
    }

    $inserted = 0;
    for ($month = $firstMonth; $month <= $currentMonth; $month++) {
        $cursor = strtotime(sprintf('%04d-%02d-01', $year, $month));
        $lastDay = (int) date('t', $cursor);
        $workdays = [];
        for ($day = 1; $day <= $lastDay; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            if ($date > $today) {
                break;
            }
            if ((int) date('N', strtotime($date)) >= 6 || isset($holidays[$date])) {
                continue;
            }
            $workdays[] = $date;
        }

        foreach ($employees as $index => $employee) {
            $employeeId = (int) $employee['id'];
            $shiftId = $scheduleShift[$employeeId] ?? 0;
            $shift = $shifts[$shiftId] ?? null;
            if (!$shift) {
                $shift = $shiftByUnit[(int) $employee['business_unit_id']] ?? $defaultShift;
            }
            if (!$shift) {
                continue;
            }

            foreach ($workdays as $dayIndex => $date) {
                if (isset($existing[$employeeId . '|' . $date])) {
                    continue;
                }

                $seed = ($employeeId * 37 + $dayIndex * 11 + $month * 7) % 100;
                $status = 'PRESENT';
                $lateMinutes = 0;
                $checkIn = null;
                $checkOut = null;

                if ($seed < 3) {
                    $status = 'ABSENT';
                } elseif ($seed < 6) {
                    $status = 'LEAVE';
                } else {
                    $arrival = ($seed % 23) - 8;
                    if ($seed % 31 === 0) {
                        $arrival = 130;
                    } elseif ($seed % 17 === 0) {
                        $arrival = 75;
                    } elseif ($seed % 11 === 0) {
                        $arrival = 35;
                    }
                    $lateMinutes = max(0, $arrival - (int) $shift['late_tolerance_minutes']);
                    $startTs = strtotime($date . ' ' . $shift['check_in_time']);
                    $checkIn = date('Y-m-d H:i:s', $startTs + ($arrival * 60));
                    $checkOut = date('Y-m-d H:i:s', strtotime($date . ' ' . $shift['check_out_time']) + (mt_rand(-5, 45) * 60));
                    if ($lateMinutes > 0) {
                        $status = 'LATE';
                    }
                }

                Database::insert('attendance', [
                    'employee_id'        => $employeeId,
                    'work_date'          => $date,
                    'shift_id'           => (int) $shift['id'],
                    'operational_type'   => (string) $shift['operational_type'],
                    'check_in_at'        => $checkIn,
                    'check_in_lat'       => $checkIn ? -6.2 + (mt_rand(-500, 500) / 100000) : null,
                    'check_in_lng'       => $checkIn ? 106.81 + (mt_rand(-500, 500) / 100000) : null,
                    'check_in_distance'  => $checkIn ? mt_rand(6, 45) : null,
                    'check_in_accuracy'  => $checkIn ? mt_rand(5, 22) : null,
                    'check_in_status'    => $checkIn ? ($lateMinutes > 0 ? 'LATE' : 'ON_TIME') : null,
                    'check_out_at'       => $checkOut,
                    'check_out_distance' => $checkOut ? mt_rand(8, 50) : null,
                    'late_minutes'       => $lateMinutes,
                    'work_minutes'       => $checkIn && $checkOut
                        ? (int) round((strtotime($checkOut) - strtotime($checkIn)) / 60) - (int) $shift['break_minutes']
                        : 0,
                    'status'             => $status,
                    'source'             => 'SEED20',
                    'ip_address'         => '10.30.' . (($employeeId % 20) + 1) . '.' . (($dayIndex % 200) + 20),
                    'device_info'        => 'Chrome / Android',
                    'created_at'         => $checkIn ?? ($date . ' 08:00:00'),
                    'updated_at'         => $now,
                ]);
                $existing[$employeeId . '|' . $date] = true;
                $inserted++;
            }
        }
    }

    if ($inserted > 0) {
        $log[] = 'Batch 20: ' . number_format($inserted, 0, ',', '.') . ' baris presensi pendukung payroll dibuat.';
    }

    /* --------------------------------------------------- 2. periode payroll */

    $model = new PayrollModel();
    $units = Database::all('SELECT id, name, code FROM business_units ORDER BY id');
    $periods = 0;
    $items = 0;
    $states = [];

    for ($month = $firstMonth; $month <= $currentMonth; $month++) {
        // Bulan berjalan: sudah dihitung dan (bila tanggal sudah lewat tengah
        // bulan) disetujui Finance; bulan sebelumnya lunas dibayar.
        if ($month === $currentMonth) {
            $target = (int) date('j') >= 15 ? 'FINANCE_APPROVED' : 'CALCULATED';
        } else {
            $target = 'PAID';
        }

        foreach ($units as $unit) {
            $created = $model->createPeriod((int) $unit['id'], $year, $month);
            if (!empty($created['error']) || empty($created['id'])) {
                continue;
            }
            $periodId = (int) $created['id'];
            $calculated = $model->calculate($periodId);
            if (!empty($calculated['error'])) {
                continue;
            }
            $periods++;
            $items += (int) ($calculated['count'] ?? 0);

            $period = $model->find($periodId) ?? [];
            $payDate = (string) ($period['pay_date'] ?? date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $month))));
            $stamp = $payDate . ' 17:00:00';

            if ($target === 'CALCULATED') {
                Database::update('payroll_periods', [
                    'processed_by' => $hrId ?: null,
                    'processed_at' => $stamp,
                    'updated_at'   => $now,
                ], 'id = ?', [$periodId]);
            } else {
                Database::update('payroll_periods', [
                    'status'              => 'FINANCE_APPROVED',
                    'processed_by'        => $hrId ?: null,
                    'processed_at'        => $stamp,
                    'sent_by'             => $hrId ?: null,
                    'sent_at'             => $stamp,
                    'hr_signature_name'   => $hrName,
                    'hr_signature_at'     => $stamp,
                    'finance_approved_by' => $finId ?: null,
                    'finance_approved_at' => $stamp,
                    'fin_signature_name'  => $finName,
                    'fin_signature_at'    => $stamp,
                    'updated_at'          => $now,
                ], 'id = ?', [$periodId]);

                if ($target === 'PAID') {
                    Database::update('payroll_items', [
                        'payment_status' => 'PAID',
                        'paid_at'        => $stamp,
                        'paid_by'        => $finId ?: null,
                    ], 'period_id = ?', [$periodId]);
                    Database::update('payroll_periods', [
                        'status'     => 'PAID',
                        'paid_by'    => $finId ?: null,
                        'paid_at'    => $stamp,
                        'updated_at' => $now,
                    ], 'id = ?', [$periodId]);
                }
            }

            $states[$target] = ($states[$target] ?? 0) + 1;
        }
    }

    if ($periods > 0) {
        $summary = [];
        foreach ($states as $status => $count) {
            $summary[] = $count . ' ' . strtolower(str_replace('_', ' ', $status));
        }
        $log[] = 'Batch 20: ' . $periods . ' periode payroll (' . implode(', ', $summary) . ') '
            . 'dengan ' . number_format($items, 0, ',', '.') . ' slip dihitung mesin payroll asli.';
    }

    /* ------------------------------------------- 3. bukti potong 1721-A1 */

    if (Database::tableExists('tax_slips')) {
        $tax = new TaxModel();
        $rows = $tax->rows(['year' => $year, 'unit' => 0, 'q' => '']);
        $issued = 0;
        foreach ($rows as $row) {
            $employeeId = (int) $row['employee_id'];
            // Sebagian karyawan dibuat "belum terbit" agar aksi Terbitkan Semua
            // tetap punya pekerjaan; akun demo karyawan selalu disertakan.
            $isDemoEmployee = (string) ($row['employee_code'] ?? '') === 'ARG-JKT-0421';
            if (!$isDemoEmployee && $employeeId % 3 === 0) {
                continue;
            }
            $detail = $tax->employeeDetail($employeeId, $year);
            if (!$detail || (int) $detail['computed']['months'] === 0) {
                continue;
            }
            $tax->issue($detail, $year, $hrId, 'Diterbitkan HR sebagai arsip pajak tahunan.');
            $issued++;
        }
        if ($issued > 0) {
            $log[] = 'Batch 20: ' . $issued . ' bukti potong 1721-A1 diterbitkan (sisanya dibiarkan untuk uji "Terbitkan Semua").';
        }
    }

    return $log;
};
