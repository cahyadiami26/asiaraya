<?php
/**
 * Generator PDF Absensi (DOMPDF manual):
 *  - type=log    : log absensi satu hari per branch (check-in/out, GPS jarak, foto status)
 *  - type=daily  : rekap kehadiran harian per branch/departemen
 *  - type=monthly: rekap bulanan per branch/departemen
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pdf.php';
require_login();
require_role('HR');

$co = company();
$type = $_GET['type'] ?? 'daily';

function th(string $t, string $style = ''): string { return '<th style="' . $style . '">' . htmlspecialchars($t) . '</th>'; }
function td(string $v, string $style = ''): string { return '<td style="' . $style . '">' . $v . '</td>'; }

$baseCss = "body{font-family:DejaVu Sans,Arial,sans-serif;font-size:10pt;color:#222}
table{border-collapse:collapse;width:100%}
td,th{border:1px solid #b6c2d2;padding:4px 6px;font-size:8.5pt}
th{background:#0d3b66;color:#fff;text-align:left}.num{text-align:right}";

/* ---------- LOG HARIAN ---------- */
if ($type === 'log') {
    $d = $_GET['date'] ?? date('Y-m-d');
    $fBranch = (int)($_GET['branch'] ?? 0);
    $rows = all("SELECT a.*, e.full_name, e.employee_code, b.name branch_name
                 FROM attendance_logs a JOIN employees e ON e.id=a.employee_id
                 LEFT JOIN branches b ON b.id=a.branch_id
                 WHERE DATE(a.log_date)=?" . ($fBranch ? ' AND a.branch_id=' . $fBranch : '') .
                 " ORDER BY b.name, e.full_name", [$d]);
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' . $baseCss . '</style></head><body>'
        . pdf_header_html($co, 'LOG ABSENSI HARIAN', 'Tanggal: ' . date_id($d, true))
        . '<table><thead><tr>' . th('#') . th('Karyawan') . th('Branch') . th('Check-In') . th('Jarak (m)')
        . th('Selfie') . th('Check-Out') . th('Jarak (m)') . th('Selfie') . th('Telat') . th('Jam Kerja') . th('Lembur')
        . '</tr></thead><tbody>';
    $i = 0;
    foreach ($rows as $r) {
        $i++;
        $html .= '<tr>' . td($i)
            . td('<b>' . e($r['full_name']) . '</b><br><span style="color:#777">' . e($r['employee_code']) . '</span>')
            . td(e($r['branch_name'] ?? '-'))
            . td($r['check_in_at'] ? date('H:i', strtotime($r['check_in_at'])) : '-')
            . td((string)($r['check_in_distance'] ?? '-'))
            . td($r['check_in_photo'] ? '✔' : '—')
            . td($r['check_out_at'] ? date('H:i', strtotime($r['check_out_at'])) : '-')
            . td((string)($r['check_out_distance'] ?? '-'))
            . td($r['check_out_photo'] ? '✔' : '—')
            . td((int)$r['late_minutes'] > 0 ? '<b style="color:#b00">' . (int)$r['late_minutes'] . ' mnt</b>' : '0')
            . td(number_format((float)$r['work_hours'], 1))
            . td(number_format((float)$r['overtime_hours'], 1)) . '</tr>';
    }
    if (!$rows) $html .= td('Tidak ada log pada tanggal ini.', 'colspan="12" style="text-align:center;color:#777"');
    $html .= '</tbody></table></body></html>';
    render_pdf($html, 'log-absensi-' . $d . '.pdf', 'L');
}

/* ---------- REKAP HARIAN ----------
   Jika parameter mode=monthly tidak ada -> matriks status satu hari. */
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year'] ?? date('Y'));
$fBranch = (int)($_GET['branch'] ?? 0);
$fDept = (int)($_GET['dept'] ?? 0);

if ($type === 'daily') {
    $d = $_GET['date'] ?? date('Y-m-d');
    $rows = all("SELECT e.id, e.full_name, e.employee_code, br.name branch_name, dep.name dept_name,
                        a.check_in_at, a.check_out_at, a.late_minutes,
                        (SELECT COUNT(*) FROM leave_requests lr WHERE lr.employee_id=e.id AND lr.status='APPROVED' AND ? BETWEEN lr.start_date AND lr.end_date) on_leave
                 FROM employees e
                 LEFT JOIN branches br ON br.id=e.branch_id
                 LEFT JOIN departments dep ON dep.id=e.department_id
                 LEFT JOIN attendance_logs a ON a.employee_id=e.id AND a.log_date=?
                 WHERE e.flag='active'" . ($fBranch ? ' AND e.branch_id=' . $fBranch : '') .
                 ($fDept ? ' AND e.department_id=' . $fDept : '') . ' ORDER BY br.name, dep.name, e.full_name', [$d, $d]);
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' . $baseCss . '
      .st{ text-align:center;font-weight:bold}</style></head><body>'
        . pdf_header_html($co, 'REKAP KEHADIRAN HARIAN', date_id($d, true))
        . '<table><thead><tr>' . th('#') . th('Karyawan') . th('Branch') . th('Departemen')
        . th('Status') . th('In') . th('Out') . th('Telat (mnt)') . '</tr></thead><tbody>';
    $i = 0; $nP = $nL = $nLv = $nA = 0;
    foreach ($rows as $r) {
        $i++;
        if (!empty($r['check_in_at'])) { $st = '<b style="color:#0a7a2f">HADIR</b>'; $nP++; }
        elseif ((int)$r['on_leave'] > 0) { $st = '<b style="color:#0b5ed7">CUTI</b>'; $nLv++; }
        else { $st = '<b style="color:#b00">ALPA</b>'; $nA++; }
        if ((int)$r['late_minutes'] > 0) $nL++;
        $html .= '<tr>' . td($i) . td('<b>' . e($r['full_name']) . '</b><br><span style="color:#777">' . e($r['employee_code']) . '</span>')
            . td(e($r['branch_name'] ?? '-')) . td(e($r['dept_name'] ?? '-'))
            . td($st, 'class="st"')
            . td($r['check_in_at'] ? date('H:i', strtotime($r['check_in_at'])) : '-')
            . td($r['check_out_at'] ? date('H:i', strtotime($r['check_out_at'])) : '-')
            . td((int)$r['late_minutes'] ?: '0') . '</tr>';
    }
    $html .= '<tr style="background:#f2f6fb;font-weight:bold">' . td('', 'colspan="4"')
        . td("Hadir $nP · Cuti $nLv · Alpa $nA · Telat $nL") . td('', 'colspan="3"') . '</tr>';
    $html .= '</tbody></table></body></html>';
    render_pdf($html, 'rekap-harian-' . $d . '.pdf');
}

/* ---------- REKAP BULANAN ---------- */
if ($type !== 'monthly') exit('Jenis tidak dikenal.');
$lastDay = sprintf('%04d-%02d-%02d', $year, $month, date('t', mktime(0, 0, 0, $month, 1, $year)));
$sql = "SELECT e.id, e.full_name, e.employee_code, br.name branch_name, dep.name dept_name,
               COUNT(a.id) present_days, COALESCE(SUM(a.late_minutes),0) late_minutes,
               COALESCE(SUM(CASE WHEN a.late_minutes>0 THEN 1 ELSE 0 END),0) late_days,
               COALESCE(SUM(a.overtime_hours),0) ot_hours,
               (SELECT COALESCE(SUM(lr.days_count),0) FROM leave_requests lr JOIN leave_types lt ON lt.id=lr.leave_type_id
                 WHERE lr.employee_id=e.id AND lr.status='APPROVED' AND lt.is_paid=1 AND MONTH(lr.start_date)=? AND YEAR(lr.start_date)=?) lv_paid,
               (SELECT COALESCE(SUM(lr.days_count),0) FROM leave_requests lr JOIN leave_types lt ON lt.id=lr.leave_type_id
                 WHERE lr.employee_id=e.id AND lr.status='APPROVED' AND lt.is_paid=0 AND MONTH(lr.start_date)=? AND YEAR(lr.start_date)=?) lv_unpaid
        FROM employees e
        LEFT JOIN branches br ON br.id=e.branch_id
        LEFT JOIN departments dep ON dep.id=e.department_id
        LEFT JOIN attendance_logs a ON a.employee_id=e.id AND MONTH(a.log_date)=? AND YEAR(a.log_date)=?
        WHERE e.flag='active'";
$p = [$month, $year, $month, $year, $month, $year];
if ($fBranch) { $sql .= ' AND e.branch_id=?'; $p[] = $fBranch; }
if ($fDept)   { $sql .= ' AND e.department_id=?'; $p[] = $fDept; }
$rows = all($sql . ' ORDER BY br.name, dep.name, e.full_name', $p);

$wd = 0;
for ($i = 1; $i <= (int)date('t', mktime(0, 0, 0, $month, 1, $year)); $i++)
    if ((int)date('N', mktime(0, 0, 0, $month, $i, $year)) <= 6) $wd++;

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' . $baseCss . '</style></head><body>'
    . pdf_header_html($co, 'REKAP KEHADIRAN BULANAN', month_name($month) . ' ' . $year)
    . '<table><thead><tr>' . th('#') . th('Karyawan') . th('Branch') . th('Departemen') . th('Hadir')
    . th('Telat (hr/mnt)') . th('Cuti Gaji') . th('Cuti Unpaid') . th('Lembur (jam)') . th('Alpa') . '</tr></thead><tbody>';
$i = 0;
foreach ($rows as $r) {
    $i++;
    $alpa = max(0, $wd - (int)$r['present_days'] - (float)$r['lv_paid'] - (float)$r['lv_unpaid']);
    $html .= '<tr>' . td($i) . td('<b>' . e($r['full_name']) . '</b><br><span style="color:#777">' . e($r['employee_code']) . '</span>')
        . td(e($r['branch_name'] ?? '-')) . td(e($r['dept_name'] ?? '-'))
        . td((int)$r['present_days']) . td((int)$r['late_days'] . ' / ' . (int)$r['late_minutes'])
        . td((float)$r['lv_paid'] ?: '-') . td((float)$r['lv_unpaid'] ?: '-')
        . td(number_format((float)$r['ot_hours'], 1))
        . td($alpa > 0 ? '<b style="color:#b00">' . $alpa . '</b>' : '0') . '</tr>';
}
$html .= '</tbody></table><div style="margin-top:8px;font-size:8.5pt;color:#555">Asumsi hari kerja: ' . $wd . ' hari (Senin–Sabtu).</div></body></html>';
render_pdf($html, 'rekap-bulanan-' . $year . sprintf('%02d', $month) . '.pdf');
