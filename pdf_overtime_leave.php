<?php
/**
 * Generator PDF Laporan Lembur & Cuti (HR):
 *  - type=overtime : rekap pengajuan lembur per bulan/branch (landscape)
 *  - type=leave    : rekap pengajuan cuti per bulan/branch (portrait)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pdf.php';
require_login();
require_role('HR');

$co = company();
$type = $_GET['type'] ?? 'overtime';
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year'] ?? date('Y'));
$fBranch = (int)($_GET['branch'] ?? 0);

function th(string $t, string $style = ''): string { return '<th style="' . $style . '">' . htmlspecialchars($t) . '</th>'; }
function td(string $v, string $style = ''): string { return '<td style="' . $style . '">' . $v . '</td>'; }

$baseCss = "body{font-family:DejaVu Sans,Arial,sans-serif;font-size:10pt;color:#222}
table{border-collapse:collapse;width:100%}
td,th{border:1px solid #b6c2d2;padding:4px 6px;font-size:8.5pt}
th{background:#0d3b66;color:#fff;text-align:left}.num{text-align:right}";

/** Estimasi kompensasi lembur (sinkron dengan includes/payroll_calc.php). */
function overtime_pay_row(float $hours, array $os): float
{
    $h = max(0, $hours);
    $pay = min($h, 1) * (float)$os['rate_hour1'];
    $rem = $h - 1;
    if ($rem > 0) { $sec = min($rem, 1); $pay += $sec * (float)$os['rate_hour2']; $rem -= $sec; }
    if ($rem > 0) $pay += $rem * (float)$os['rate_hour_more'];
    $capDays = (float)$os['max_comp_per_day'];
    $estDays = max(1, (int)ceil($h / max(0.5, (float)$os['max_hours_per_day'])));
    return round(min($pay, $capDays * $estDays));
}

/* ==================== REKAP LEMBUR ==================== */
if ($type === 'overtime') {
    $os = overtime_settings();
    $sql = "SELECT o.*, e.full_name, e.employee_code, br.name branch_name, dep.name dept_name,
                   m.full_name mgr_name
            FROM overtime_requests o
            JOIN employees e ON e.id=o.employee_id
            LEFT JOIN branches br ON br.id=e.branch_id
            LEFT JOIN departments dep ON dep.id=e.department_id
            LEFT JOIN employees m ON m.id=e.direct_manager_id
            WHERE MONTH(o.work_date)=? AND YEAR(o.work_date)=?";
    $p = [$month, $year];
    if ($fBranch) { $sql .= ' AND e.branch_id=?'; $p[] = $fBranch; }
    $rows = all($sql . ' ORDER BY o.work_date, br.name, e.full_name', $p);

    $estPay = 0.0; $apprH = 0.0;
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' . $baseCss . '</style></head><body>'
        . pdf_header_html($co, 'REKAP PENGAJUAN LEMBUR', month_name($month) . ' ' . $year)
        . '<table><thead><tr>' . th('#') . th('Tanggal') . th('Karyawan') . th('Unit') . th('Waktu')
        . th('Jam') . th('Estimasi Kompensasi') . th('Alasan') . th('Status') . th('Catatan Approver')
        . '</tr></thead><tbody>';
    $i = 0;
    foreach ($rows as $r) {
        $i++;
        $comp = $r['status'] === 'APPROVED' ? overtime_pay_row((float)$r['duration_hours'], $os) : 0;
        if ($r['status'] === 'APPROVED') { $apprH += (float)$r['duration_hours']; $estPay += $comp; }
        $note = trim((string)$r['manager_note'] . ' / ' . (string)$r['hr_note'], ' /');
        $html .= '<tr>' . td($i)
            . td(date_id($r['work_date']))
            . td('<b>' . e($r['full_name']) . '</b><br><span style="color:#777">' . e($r['employee_code']) . '</span>')
            . td(e($r['branch_name'] ?? '-') . '<br><span style="color:#777">' . e($r['dept_name'] ?? '-') . '</span>')
            . td(substr($r['start_time'], 0, 5) . '–' . substr($r['end_time'], 0, 5))
            . td(number_format((float)$r['duration_hours'], 1), 'class="num"')
            . td($comp ? money($comp) : '-', 'class="num"')
            . td(e(mb_strimwidth((string)$r['reason'], 0, 70, '…')))
            . td(strtoupper(str_replace('PENDING_', 'MENUNGGU ', $r['status'])))
            . td($note ? e(mb_strimwidth($note, 0, 60, '…')) : '—')
            . '</tr>';
    }
    if (!$rows) $html .= td('Tidak ada pengajuan lembur pada periode ini.', 'colspan="10" style="text-align:center;color:#777"');
    $html .= '<tr style="background:#f2f6fb;font-weight:bold">' . td('', 'colspan="5"')
        . td('TOTAL DISETUJUI: ' . number_format($apprH, 1) . ' jam', 'class="num"')
        . td(money($estPay), 'class="num"') . td('', 'colspan="3"') . '</tr>';
    $html .= '</tbody></table>'
        . '<div style="margin-top:8px;font-size:8pt;color:#555">Tarif kompensasi sistem: jam-1 ' . money($os['rate_hour1'])
        . ' · jam-2 ' . money($os['rate_hour2']) . ' · dst ' . money($os['rate_hour_more'])
        . ' · maks ' . money($os['max_comp_per_day']) . '/hari.</div>'
        . '<table style="margin-top:22px;width:100%"><tr>'
        . td('Dibuat oleh HR,<br><br><br><b>________________</b>', 'width:33%;border:none')
        . td('Disetujui Manager,<br><br><br><b>________________</b>', 'width:33%;border:none;text-align:center')
        . td('Finance,<br><br><br><b>________________</b>', 'width:33%;border:none;text-align:right')
        . '</tr></table></body></html>';
    render_pdf($html, 'rekap-lembur-' . $year . sprintf('%02d', $month) . '.pdf', 'L');
}

/* ==================== REKAP CUTI ==================== */
function money_days(float $d): string { return rtrim(rtrim(number_format($d, 1, ',', ''), '0'), ','); }

$sql = "SELECT lr.*, lt.name type_name, lt.is_paid, e.full_name, e.employee_code,
                br.name branch_name, dep.name dept_name, m.full_name mgr_name
         FROM leave_requests lr
         JOIN leave_types lt ON lt.id=lr.leave_type_id
         JOIN employees e ON e.id=lr.employee_id
         LEFT JOIN branches br ON br.id=e.branch_id
         LEFT JOIN departments dep ON dep.id=e.department_id
         LEFT JOIN employees m ON m.id=e.direct_manager_id
         WHERE MONTH(lr.start_date)=? AND YEAR(lr.start_date)=?";
$p = [$month, $year];
if ($fBranch) { $sql .= ' AND e.branch_id=?'; $p[] = $fBranch; }
$rows = all($sql . ' ORDER BY lr.start_date, br.name, e.full_name', $p);

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' . $baseCss . '</style></head><body>'
    . pdf_header_html($co, 'REKAP PENGAJUAN CUTI', month_name($month) . ' ' . $year)
    . '<table><thead><tr>' . th('#') . th('Karyawan') . th('Jenis Cuti') . th('Tanggal') . th('Lama')
    . th('Keperluan') . th('Status') . th('Atasan') . th('Keputusan')
    . '</tr></thead><tbody>';
$i = 0; $paidDays = 0; $unpaidDays = 0;
foreach ($rows as $r) {
    $i++;
    if ($r['status'] === 'APPROVED') {
        if ($r['is_paid']) $paidDays += (float)$r['days_count']; else $unpaidDays += (float)$r['days_count'];
    }
    $keputusan = strtoupper($r['status']);
    if ($r['status'] === 'PENDING_MGR') $keputusan = 'MENUNGGU MANAGER';
    if ($r['status'] === 'PENDING_HR')  $keputusan = 'MENUNGGU HR';
    $html .= '<tr>' . td($i)
        . td('<b>' . e($r['full_name']) . '</b><br><span style="color:#777">' . e($r['employee_code'])
           . '<br>' . e($r['branch_name'] ?? '-') . ' · ' . e($r['dept_name'] ?? '-') . '</span>')
        . td(e($r['type_name']) . ($r['is_paid'] ? '' : ' <span style="color:#b60">(unpaid)</span>'))
        . td(date('d/m/Y', strtotime($r['start_date'])) . '–' . date('d/m/Y', strtotime($r['end_date'])))
        . td(number_format((float)$r['days_count'], 1) . ' hr', 'class="num"')
        . td(e(mb_strimwidth((string)$r['purpose'], 0, 60, '…')))
        . td(strtoupper($r['status']))
        . td(e($r['mgr_name'] ?? '-'))
        . td($keputusan . ($r['manager_note'] || $r['hr_note'] ? '<br><span style="font-size:7.5pt;color:#555">'
           . e(mb_strimwidth(trim((string)$r['manager_note'] . ' / ' . (string)$r['hr_note'], ' /'), 0, 50, '…')) . '</span>' : ''))
        . '</tr>';
}
if (!$rows) $html .= td('Tidak ada pengajuan cuti pada periode ini.', 'colspan="9" style="text-align:center;color:#777"');
$html .= '<tr style="background:#f2f6fb;font-weight:bold">' . td('', 'colspan="4"')
    . td('Cuti disetujui: ' . money_days($paidDays) . ' berbayar / ' . money_days($unpaidDays) . ' unpaid', 'colspan="5"')
    . '</tr></tbody></table>'
    . '<table style="margin-top:22px;width:100%"><tr>'
    . td('Dibuat oleh HR,<br><br><br><b>________________</b>', 'width:33%;border:none')
    . td('Mengetahui Manajemen,<br><br><br><b>________________</b>', 'width:33%;border:none;text-align:center')
    . td('', 'width:33%;border:none') . '</tr></table></body></html>';

render_pdf($html, 'rekap-cuti-' . $year . sprintf('%02d', $month) . '.pdf');
