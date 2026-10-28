<?php
/**
 * Generator PDF Payroll (DOMPDF manual):
 *  - type=slip    : slip gaji per karyawan (HR/FIN/karyawan yang bersangkutan)
 *  - type=rekap   : rekap data gaji per branch/dep (HR)
 *  - type=laporan : laporan payroll untuk finance (HR/FIN)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pdf.php';
require_login();

$u = current_user();
$type = $_GET['type'] ?? 'slip';
$co = company();

function th(string $t, string $style = ''): string { return '<th style="' . $style . '">' . htmlspecialchars($t) . '</th>'; }
function td(string $v, string $style = ''): string { return '<td style="' . $style . '">' . $v . '</td>'; }

$baseCss = "body{font-family:DejaVu Sans,Arial,sans-serif;font-size:10pt;color:#222}
table{border-collapse:collapse;width:100%}
td,th{border:1px solid #b6c2d2;padding:4px 6px;font-size:8.5pt}
th{background:#0d3b66;color:#fff;text-align:left}
.num{text-align:right}
.title{font-size:12pt;font-weight:bold;color:#0d3b66}";

/* ================= SLIP GAJI ================= */
if ($type === 'slip') {
    $slip = one("SELECT ps.*, p.period_month, p.period_year, p.branch_id, p.pay_date,
                        e.full_name, e.employee_code, e.address, e.phone, e.email,
                        b.name branch_name, d.name dept_name, pos.name pos_name, m.full_name manager_name
                 FROM payslips ps
                 JOIN payroll_periods p ON p.id=ps.period_id
                 JOIN employees e ON e.id=ps.employee_id
                 LEFT JOIN branches b ON b.id=p.branch_id
                 LEFT JOIN departments d ON d.id=e.department_id
                 LEFT JOIN positions pos ON pos.id=e.position_id
                 LEFT JOIN employees m ON m.id=e.direct_manager_id
                 WHERE ps.id=?", [(int)($_GET['slip'] ?? 0)]);
    if (!$slip) exit('Payslip tidak ditemukan.');

    /* akses: HR/FIN bebas; karyawan hanya slip dirinya; status harus sudah terbit */
    if (!in_array($u['role'], ['HR', 'FIN'], true)) {
        if ((int)$slip['employee_id'] !== (int)($u['employee_id'] ?? 0)) exit('Akses ditolak.');
        if ($slip['status'] === 'DRAFT') exit('Slip belum diterbitkan.');
    }

    $bd = json_decode($slip['breakdown_json'] ?? '{}', true) ?: [];
    $benefits = $bd['benefits'] ?? [];
    $deductions = $bd['deductions'] ?? [];
    $empBpjs = (float)$slip['bpjs_kes_employee'] + (float)$slip['bpjs_jht_employee'] + (float)$slip['bpjs_jp_employee'];
    $periodLabel = month_name((int)$slip['period_month']) . ' ' . $slip['period_year'];

    $rowsB = '';
    foreach ($benefits as $b) $rowsB .= td(e($b['name'])) . td(money($b['amount']), 'class="num"');
    if ((float)$slip['overtime_amount'] > 0)
        $rowsB .= td('Lembur (' . number_format((float)$slip['overtime_hours'], 1) . ' jam)') . td(money($slip['overtime_amount']), 'class="num"');
    if (!$rowsB) $rowsB = td('<i class="muted">Tidak ada</i>', 'colspan="2"');

    $rowsD = '';
    if ((float)$slip['late_deduction'] > 0)
        $rowsD .= td('Potongan keterlambatan (' . (int)$slip['late_minutes'] . ' menit)') . td(money($slip['late_deduction']), 'class="num"');
    if ((float)$slip['absence_deduction'] > 0)
        $rowsD .= td('Potongan ketidak hadiran (' . (int)$slip['absent_days'] . ' hari)') . td(money($slip['absence_deduction']), 'class="num"');
    foreach ($deductions as $d) $rowsD .= td(e($d['name'])) . td(money($d['amount']), 'class="num"');
    if ((float)$slip['pph_amount'] > 0) $rowsD .= td('PPh 21') . td(money($slip['pph_amount']), 'class="num"');
    if ((float)$slip['bpjs_kes_employee'] > 0) $rowsD .= td('BPJS Kesehatan (1%)') . td(money($slip['bpjs_kes_employee']), 'class="num"');
    if ((float)$slip['bpjs_jht_employee'] > 0) $rowsD .= td('BPJS JHT (3%)') . td(money($slip['bpjs_jht_employee']), 'class="num"');
    if ((float)$slip['bpjs_jp_employee'] > 0) $rowsD .= td('BPJS JP (1%)') . td(money($slip['bpjs_jp_employee']), 'class="num"');
    if (!$rowsD) $rowsD = td('<i>Tidak ada</i>', 'colspan="2"');

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' . $baseCss . '
      .box{border:1px solid #b6c2d2;padding:8px;margin-bottom:8px}
      .sign td{border:none;font-size:9pt;padding:2px 6px}
      .net{background:#eaf3ea;font-weight:bold}
      .muted{color:#777}</style></head><body>'
      . pdf_header_html($co, 'SLIP GAJI / PAYSLIP', 'Periode: ' . $periodLabel . ' · ' . $slip['branch_name'])
      . '<table style="margin-bottom:10px">'
      . '<tr>' . td('<b>Kode</b><br>' . e($slip['employee_code']), 'width:25%')
      . td('<b>Nama</b><br>' . e($slip['full_name']), 'width:35%')
      . td('<b>Jabatan</b><br>' . e($slip['pos_name'] ?? '-'), 'width:20%')
      . td('<b>Departemen</b><br>' . e($slip['dept_name'] ?? '-'), 'width:20%') . '</tr>'
      . '<tr>' . td('<b>Branch</b><br>' . e($slip['branch_name']))
      . td('<b>Atasan Langsung</b><br>' . e($slip['manager_name'] ?? '-'))
      . td('<b>Email</b><br>' . e($slip['email'] ?? '-'))
      . td('<b>No. HP</b><br>' . e($slip['phone'] ?? '-')) . '</tr></table>'

      . '<table><tr>' . th('PENGHASILAN', 'width:50%') . th('NOMINAL (Rp)', 'width:50%;text-align:right') . '</tr>'
      . td('Gaji Pokok') . td(money($slip['basic_salary']), 'class="num"')
      . $rowsB
      . '<tr style="background:#f2f6fb;font-weight:bold">' . td('TOTAL BRUTO') . td(money($slip['gross_pay']), 'class="num"') . '</tr></table><br>'

      . '<table><tr>' . th('POTONGAN', 'width:50%') . th('NOMINAL (Rp)', 'width:50%;text-align:right') . '</tr>'
      . $rowsD
      . '<tr style="background:#fdf2f2;font-weight:bold">' . td('TOTAL POTONGAN') . td(money($slip['total_deduction']), 'class="num"') . '</tr></table><br>'

      . '<table><tr>' . th('KEHADIRAN & CUTI') . th('NILAI') . th('IURAN PERUSAHAAN (catatan)') . th('NILAI') . '</tr>'
      . td('Hari Hadir') . td((int)$slip['present_days'] . ' hari', 'class="num"')
      . td('BPJS Kesehatan 4%') . td(money($slip['bpjs_kes_company']), 'class="num"') . '</tr>'
      . td('Telat') . td((int)$slip['late_minutes'] . ' menit', 'class="num"')
      . td('BPJS JHT 2%') . td(money($slip['bpjs_jht_company']), 'class="num"') . '</tr>'
      . td('Alpa') . td((int)$slip['absent_days'] . ' hari', 'class="num"')
      . td('BPJS JP 2%') . td(money($slip['bpjs_jp_company']), 'class="num"') . '</tr>'
      . td('Cuti digaji / unpaid') . td((float)$slip['leave_days_used'] . ' / ' . (float)$slip['unpaid_leave_days'] . ' hr', 'class="num"')
      . td('Status Pembayaran') . td(strtoupper($slip['status']) . ($slip['paid_at'] ? ' (' . date_id($slip['paid_at']) . ')' : ''), 'class="num"') . '</tr></table><br>'

      . '<table class="net"><tr>' . td('<span style="font-size:11pt">TAKE HOME PAY (Net)</span>', 'style="background:#eaf3ea;font-weight:bold;border:2px solid #0d3b66"')
      . td('<span style="font-size:13pt">' . rupiah($slip['net_pay']) . '</span>', 'class="num" style="background:#eaf3ea;font-weight:bold;border:2px solid #0d3b66;text-align:right"')
      . '</tr></table>'

      . '<table class="sign" style="margin-top:26px"><tr>'
      . td('Dibuat oleh,<br><br><br><b>________________</b><br>HR Payroll', 'width:33%')
      . td('Disetujui,<br><br><br><b>________________</b><br>Finance', 'width:33%;text-align:center')
      . td('Dicatat sebagai penerimaan,<br><br><br><b>________________</b><br>' . e($slip['full_name']), 'width:33%;text-align:right')
      . '</tr></table>'
      . '<div style="margin-top:14px;font-size:7.5pt;color:#888;text-align:center">Dokumen dihasilkan sistem HRIS ' . e($co['name']) .
        ' pada ' . date('d-m-Y H:i') . ' — slip tanpa tanda tangan basah tetap sah secara internal.</div></body></html>';

    render_pdf($html, 'slip-gaji-' . preg_replace('/[^A-Za-z0-9]+/', '-', $slip['employee_code']) . '-' . $slip['period_year'] . sprintf('%02d', $slip['period_month']) . '.pdf');
}

/* ================= REKAP GAJI PER BRANCH/DEPT (landscape) ================= */
if ($type === 'rekap' || $type === 'laporan') {
    require_role('HR', 'FIN');
    $per = one("SELECT p.*, b.name branch_name FROM payroll_periods p JOIN branches b ON b.id=p.branch_id WHERE p.id=?",
               [(int)($_GET['period'] ?? 0)]);
    if (!$per) exit('Periode tidak valid.');
    if ($per['status'] === 'OPEN') exit('Rekap dapat dicetak setelah payslip dihitung (minimal terkunci).');

    $fDept = (int)($_GET['dept'] ?? 0);
    $slips = all("SELECT ps.*, e.full_name, e.employee_code, d.name dept_name
                  FROM payslips ps JOIN employees e ON e.id=ps.employee_id
                  LEFT JOIN departments d ON d.id=e.department_id
                  WHERE ps.period_id=?" . ($fDept ? ' AND e.department_id=' . $fDept : '') .
                  " ORDER BY d.name, e.full_name", [$per['id']]);

    $titleTxt = $type === 'laporan' ? 'LAPORAN PAYROLL (FINANCE)' : 'REKAP DATA GAJI KARYAWAN';
    $sub = $per['branch_name'] . ' · ' . month_name((int)$per['period_month']) . ' ' . $per['period_year']
         . ($fDept ? ' · ' . (string)val('SELECT name FROM departments WHERE id=?', [$fDept]) : '');

    $rows = ''; $i = 0;
    $sum = array_fill_keys(['gross_pay','late_deduction','absence_deduction','overtime_amount','deductions_total','pph_amount','total_deduction','net_pay'], 0.0);
    foreach ($slips as $s) {
        $i++;
        foreach ($sum as $k => $_) $sum[$k] += (float)$s[$k];
        $rows .= '<tr>'
            . td($i) . td(e($s['employee_code']) . '<br><b>' . e($s['full_name']) . '</b><br><span style="color:#777;font-size:7.5pt">' . e($s['dept_name'] ?? '-') . '</span>')
            . td((int)$s['present_days']) . td((int)$s['late_minutes']) . td((int)$s['absent_days'])
            . td(money($s['basic_salary']), 'class="num"') . td(money($s['overtime_amount']), 'class="num"')
            . td(money($s['benefits_total']), 'class="num"') . td(money($s['late_deduction']), 'class="num"')
            . td(money($s['absence_deduction']), 'class="num"') . td(money($s['deductions_total']), 'class="num"')
            . td(money($s['pph_amount']), 'class="num"')
            . td(money((float)$s['bpjs_kes_employee'] + (float)$s['bpjs_jht_employee'] + (float)$s['bpjs_jp_employee']), 'class="num"')
            . td('<b>' . money($s['gross_pay']) . '</b>', 'class="num"')
            . td(money($s['total_deduction']), 'class="num"')
            . td('<b>' . money($s['net_pay']) . '</b>', 'class="num"')
            . td($s['status'] === 'PAID' ? 'PAID' . ($s['paid_at'] ? '<br><span style="font-size:7pt">' . date('d/m/Y', strtotime($s['paid_at'])) . '</span>' : '') : strtoupper($s['status']))
            . '</tr>';
    }
    if (!$slips) $rows = td('Belum ada payslip pada periode ini.', 'colspan="16" style="text-align:center;color:#777"');

    $totRow = '<tr style="background:#f2f6fb;font-weight:bold">' . td('', 'colspan="5"') . td('TOTAL ' . count($slips) . ' KARYAWAN', 'colspan="3"')
        . td('', 'colspan="5"')
        . td(money($sum['gross_pay']), 'class="num"') . td(money($sum['total_deduction']), 'class="num"')
        . td(money($sum['net_pay']), 'class="num"') . td('', 'colspan="0"') . '</tr>';

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' . $baseCss . '
        .num{text-align:right} thead th{font-size:7.5pt}</style></head><body>'
        . pdf_header_html($co, $titleTxt, $sub)
        . '<table><thead><tr>'
        . th('#', 'width:18px') . th('Karyawan') . th('Hadir') . th('Telat<br>(mnt)') . th('Alpa')
        . th('Gaji Pokok') . th('Lembur') . th('Benefit') . th('Pot.Telat') . th('Pot.Alpa') . th('Komp.Ded.')
        . th('PPh 21') . th('BPJS<br>Karyawan') . th('Bruto') . th('Total Pot.') . th('NET') . th('Status')
        . '</tr></thead><tbody>' . $rows . $totRow . '</tbody></table>'
        . '<table class="sign" style="margin-top:24px;width:100%"><tr>'
        . td('Jakarta, ' . date_id(date('Y-m-d')) . '<br>Dibuat oleh HR,<br><br><br><b>________________</b>', 'width:33%;border:none')
        . td('Disetujui Manager,<br><br><br><b>________________</b>', 'width:33%;border:none;text-align:center')
        . td('Diterima Finance,<br><br><br><b>________________</b>', 'width:33%;border:none;text-align:right')
        . '</tr></table></body></html>';

    render_pdf($html, strtolower($type) . '-payroll-' . preg_replace('/[^A-Za-z0-9]+/', '-', $per['branch_name']) . '-' . $per['period_year'] . sprintf('%02d', $per['period_month']) . '.pdf', 'L');
}

exit('Jenis dokumen tidak dikenal.');
