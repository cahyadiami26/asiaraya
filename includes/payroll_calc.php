<?php
/**
 * Engine perhitungan gaji (payroll).
 * Dipakai oleh payroll_process.php, pdf_payroll.php, myslips.php.
 */
require_once __DIR__ . '/functions.php';

/** Jumlah hari kerja dalam sebulan (Senin-Sabtu) untuk branch pada periode tsb. */
function workdays_in_month(int $year, int $month): int
{
    $days = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
    $cnt = 0;
    for ($i = 1; $i <= $days; $i++) {
        if ((int)date('N', mktime(0, 0, 0, $month, $i, $year)) <= 6) $cnt++;
    }
    return $cnt;
}

/** Kompensasi lembur bertingkat: jam ke-1, ke-2, dst sesuai overtime_settings + cap harian. */
function overtime_pay(float $hours, array $os): float
{
    $rate1  = (float)$os['rate_hour1'];
    $rate2  = (float)$os['rate_hour2'];
    $rateX  = (float)$os['rate_hour_more'];
    $capDay = (float)$os['max_comp_per_day'];

    // distribusikan proporsional per hari: pakai rata-rata jam/hari dari request?
    // Sederhana: hitung sebagai satu rangkaian jam (jam 1 -> rate1, jam 2 -> rate2, >2 -> rateX),
    // lalu terapkan cap per-hari berdasarkan estimasi jumlah hari lembur.
    $h = max(0, $hours);
    $pay = 0;
    $rem = $h;
    $firstHour = min($rem, 1);  $pay += $firstHour * $rate1; $rem -= $firstHour;
    $secHour   = min($rem, 1);  $pay += $secHour * $rate2;   $rem -= $secHour;
    $pay       += $rem * $rateX;

    // Terapkan batas kompensasi maks HARIAN: bagi total dengan jumlah hari (estimasi jam/8 dibulatkan ke atas, min 1)
    $estDays = max(1, (int)ceil($h / max(0.5, (float)$os['max_hours_per_day'])));
    $capTotal = $capDay * $estDays;
    return round(min($pay, $capTotal), 0);
}

/**
 * Hitung lengkap satu payslip karyawan untuk satu periode.
 * @param array $emp  baris employees (id, base_salary, branch_id, ...)
 * @param array $per  baris payroll_periods (period_month, period_year, branch_id)
 * @return array data siap insert ke payslips + breakdown komponen
 */
function compute_payslip(array $emp, array $per): array
{
    $eid   = (int)$emp['id'];
    $month = (int)$per['period_month'];
    $year  = (int)$per['period_year'];
    $as    = attendance_settings();
    $os    = overtime_settings();
    $ps    = payroll_settings();

    $basic = (float)$emp['base_salary'];

    /* ---- Kehadiran ---- */
    $att = one("SELECT COUNT(*) present_days, COALESCE(SUM(late_minutes),0) late_minutes,
                      COALESCE(SUM(overtime_hours),0) att_ot_hours
               FROM attendance_logs
               WHERE employee_id=? AND MONTH(log_date)=? AND YEAR(log_date)=?",
              [$eid, $month, $year]) ?? ['present_days' => 0, 'late_minutes' => 0, 'att_ot_hours' => 0];
    $present = (int)$att['present_days'];
    $lateMin = (int)$att['late_minutes'];

    /* ---- Lembur (approved) ---- */
    $ot = one("SELECT COALESCE(SUM(duration_hours),0) h FROM overtime_requests
               WHERE employee_id=? AND status='APPROVED' AND MONTH(work_date)=? AND YEAR(work_date)=?",
              [$eid, $month, $year]);
    $otHours = (float)($ot['h'] ?? 0);
    $otAmount = $os['enabled'] ? overtime_pay($otHours, $os) : 0;

    /* ---- Cuti (approved) ---- */
    $lv = one("SELECT
                 COALESCE(SUM(CASE WHEN lt.is_paid=1 THEN lr.days_count ELSE 0 END),0) paid_days,
                 COALESCE(SUM(CASE WHEN lt.is_paid=0 THEN lr.days_count ELSE 0 END),0) unpaid_days
               FROM leave_requests lr JOIN leave_types lt ON lt.id=lr.leave_type_id
               WHERE lr.employee_id=? AND lr.status='APPROVED'
                 AND lr.start_date<=? AND lr.end_date>=?",
              [$eid, sprintf('%04d-%02d-%02d', $year, $month, date('t', mktime(0,0,0,$month,1,$year))),
                   sprintf('%04d-%02d-01', $year, $month)]);
    $leavePaid   = (float)($lv['paid_days'] ?? 0);
    $leaveUnpaid = (float)($lv['unpaid_days'] ?? 0);

    /* ---- Potongan keterlambatan ---- */
    $cutPerMin = (float)$as['lat_cut_per_minute'];
    $cutMaxDay = (float)$as['lat_cut_max_per_day'];
    // potongan per hari: akumulasi menit terlambat dibagi hari hadir (min 1), cap harian x hari telat
    $lateDays = (int)val("SELECT COUNT(*) FROM attendance_logs WHERE employee_id=? AND late_minutes>0
                          AND MONTH(log_date)=? AND YEAR(log_date)=?", [$eid, $month, $year]);
    $lateDeduction = min($lateMin * $cutPerMin, $lateDays * $cutMaxDay);

    /* ---- Alpa & potongan ketidak hadiran ---- */
    $wd   = workdays_in_month($year, $month);
    $absent = max(0, $wd - $present - $leavePaid - $leaveUnpaid);
    $absenceDeduction = 0; $leaveQuotaCut = 0;
    if ($as['absence_deduction_type'] === 'salary') {
        $absenceDeduction = $absent * (float)$as['absence_cut_amount'];
    } elseif (!empty($as['absence_deduct_leave_quota'])) {
        // potong jatah cuti tahunan otomatis (dipakai saat finalize)
        $leaveQuotaCut = $absent;
    }

    /* ---- Komponen benefit & deduction ---- */
    $comps = all("SELECT c.*, ec.override_amount
                  FROM employee_components ec
                  JOIN payroll_components c ON c.id=ec.component_id
                  WHERE ec.employee_id=? AND c.is_active=1", [$eid]);
    $benefits = []; $deductions = [];
    foreach ($comps as $c) {
        if ($c['value_type'] === 'percent') {
            $amt = $basic * ((float)$c['percent_of_basic'] / 100);
        } else {
            $amt = $c['override_amount'] !== null ? (float)$c['override_amount'] : (float)$c['amount'];
        }
        $amt = round($amt);
        $row = ['id' => (int)$c['id'], 'name' => $c['name'], 'amount' => $amt,
                'pph' => (int)$c['include_in_pph'], 'bpjs' => (int)$c['include_in_bpjs']];
        if ($c['type'] === 'benefit') $benefits[] = $row; else $deductions[] = $row;
    }
    $benefitsTotal  = array_sum(array_column($benefits, 'amount'));
    $deductionsTotal = array_sum(array_column($deductions, 'amount'));

    /* ---- BPJS ---- */
    $bpjsBaseComp = $basic; // komponen yang ikut BPJS menambah base
    foreach ($comps as $i => $c) {
        if ((int)$c['include_in_bpjs']) {
            $bpjsBaseComp += ($c['value_type'] === 'percent')
                ? round($basic * ((float)$c['percent_of_basic'] / 100))
                : ($c['override_amount'] !== null ? (float)$c['override_amount'] : (float)$c['amount']);
        }
    }
    $kesBase = min($bpjsBaseComp, (float)$ps['bpjs_kesehatan_max_base']);
    $jpBase  = min($bpjsBaseComp, (float)$ps['bpjs_jp_max_base']);
    $bpjs = [
        'kes_company'  => $ps['bpjs_enabled'] ? round($kesBase * (float)$ps['bpjs_kesehatan_company_percent'] / 100) : 0,
        'kes_employee' => $ps['bpjs_enabled'] ? round($kesBase * (float)$ps['bpjs_kesehatan_employee_percent'] / 100) : 0,
        'jht_company'  => $ps['bpjs_enabled'] ? round($bpjsBaseComp * (float)$ps['bpjs_jht_company_percent'] / 100) : 0,
        'jht_employee' => $ps['bpjs_enabled'] ? round($bpjsBaseComp * (float)$ps['bpjs_jht_employee_percent'] / 100) : 0,
        'jp_company'   => $ps['bpjs_enabled'] ? round($jpBase * (float)$ps['bpjs_jp_company_percent'] / 100) : 0,
        'jp_employee'  => $ps['bpjs_enabled'] ? round($jpBase * (float)$ps['bpjs_jp_employee_percent'] / 100) : 0,
    ];

    /* ---- PPh ---- */
    $taxableExtra = 0;
    foreach ($comps as $c) {
        if ((int)$c['include_in_pph']) {
            $taxableExtra += ($c['value_type'] === 'percent')
                ? round($basic * ((float)$c['percent_of_basic'] / 100))
                : ($c['override_amount'] !== null ? (float)$c['override_amount'] : (float)$c['amount']);
        }
    }
    $grossMonthly = $basic + $benefitsTotal + $otAmount;      // penghasilan bruto
    $pph = 0;
    if ($ps['pph_enabled']) {
        $taxBase = max(0, $grossMonthly - (float)$ps['pthp_monthly']);
        $pph = round($taxBase * (float)$ps['pph_rate_percent'] / 100);
    }

    /* ---- Net ---- */
    $empBpjsTotal = $bpjs['kes_employee'] + $bpjs['jht_employee'] + $bpjs['jp_employee'];
    $totalDeduction = $lateDeduction + $absenceDeduction + $deductionsTotal + $pph + $empBpjsTotal;
    $net = $grossMonthly - $totalDeduction;

    return [
        'employee_id'        => $eid,
        'basic_salary'       => $basic,
        'present_days'       => $present,
        'absent_days'        => (int)$absent,
        'late_minutes'       => $lateMin,
        'late_deduction'     => round($lateDeduction),
        'absence_deduction'  => round($absenceDeduction),
        'overtime_hours'     => $otHours,
        'overtime_amount'    => round($otAmount),
        'leave_days_used'    => $leavePaid,
        'unpaid_leave_days'  => $leaveUnpaid,
        'benefits_total'     => $benefitsTotal,
        'deductions_total'   => $deductionsTotal,
        'pph_amount'         => $pph,
        'bpjs_kes_company'   => $bpjs['kes_company'],
        'bpjs_kes_employee'  => $bpjs['kes_employee'],
        'bpjs_jht_company'   => $bpjs['jht_company'],
        'bpjs_jht_employee'  => $bpjs['jht_employee'],
        'bpjs_jp_company'    => $bpjs['jp_company'],
        'bpjs_jp_employee'   => $bpjs['jp_employee'],
        'gross_pay'          => round($grossMonthly),
        'total_deduction'    => round($totalDeduction),
        'net_pay'            => round($net),
        'workdays'           => $wd,
        'leave_quota_cut'    => $leaveQuotaCut,
        'breakdown'          => [
            'benefits'   => $benefits,
            'deductions' => $deductions,
            'pph_taxable_extra' => $taxableExtra,
            'bpjs_base'  => $bpjsBaseComp,
        ],
    ];
}

/** Simpan/perbarui hasil hitungan ke tabel payslips (status DRAFT). */
function save_payslip(int $periodId, array $calc): void
{
    $json = json_encode([
        'benefits'   => $calc['benefits'],
        'deductions' => $calc['deductions'],
        'workdays'   => $calc['workdays'],
        'generated_at' => date('Y-m-d H:i:s'),
    ]);
    exec_sql("INSERT INTO payslips (period_id, employee_id, basic_salary, present_days, absent_days,
              late_minutes, late_deduction, absence_deduction, overtime_hours, overtime_amount,
              leave_days_used, unpaid_leave_days, benefits_total, deductions_total, pph_amount,
              bpjs_kes_company, bpjs_kes_employee, bpjs_jht_company, bpjs_jht_employee,
              bpjs_jp_company, bpjs_jp_employee, gross_pay, total_deduction, net_pay, breakdown_json, status)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'DRAFT')
              ON DUPLICATE KEY UPDATE
              basic_salary=VALUES(basic_salary), present_days=VALUES(present_days), absent_days=VALUES(absent_days),
              late_minutes=VALUES(late_minutes), late_deduction=VALUES(late_deduction),
              absence_deduction=VALUES(absence_deduction), overtime_hours=VALUES(overtime_hours),
              overtime_amount=VALUES(overtime_amount), leave_days_used=VALUES(leave_days_used),
              unpaid_leave_days=VALUES(unpaid_leave_days), benefits_total=VALUES(benefits_total),
              deductions_total=VALUES(deductions_total), pph_amount=VALUES(pph_amount),
              bpjs_kes_company=VALUES(bpjs_kes_company), bpjs_kes_employee=VALUES(bpjs_kes_employee),
              bpjs_jht_company=VALUES(bpjs_jht_company), bpjs_jht_employee=VALUES(bpjs_jht_employee),
              bpjs_jp_company=VALUES(bpjs_jp_company), bpjs_jp_employee=VALUES(bpjs_jp_employee),
              gross_pay=VALUES(gross_pay), total_deduction=VALUES(total_deduction),
              net_pay=VALUES(net_pay), breakdown_json=VALUES(breakdown_json)", [
        $periodId, $calc['employee_id'], $calc['basic_salary'], $calc['present_days'], $calc['absent_days'],
        $calc['late_minutes'], $calc['late_deduction'], $calc['absence_deduction'], $calc['overtime_hours'],
        $calc['overtime_amount'], $calc['leave_days_used'], $calc['unpaid_leave_days'], $calc['benefits_total'],
        $calc['deductions_total'], $calc['pph_amount'], $calc['bpjs_kes_company'], $calc['bpjs_kes_employee'],
        $calc['bpjs_jht_company'], $calc['bpjs_jht_employee'], $calc['bpjs_jp_company'], $calc['bpjs_jp_employee'],
        $calc['gross_pay'], $calc['total_deduction'], $calc['net_pay'], $json,
    ]);
}
