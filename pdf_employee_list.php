<?php
/**
 * PDF Daftar Data Karyawan (HR) — rekap seluruh karyawan dengan filter branch/departemen/flag.
 * Diakses dari reports.php (type=employees).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pdf.php';
require_login();
require_role('HR');

$co = company();
$fBranch = (int)($_GET['branch'] ?? 0);
$fDept   = (int)($_GET['dept'] ?? 0);
$fFlag   = $_GET['flag'] ?? '';

$where = ['1=1']; $params = [];
if ($fBranch) { $where[] = 'e.branch_id=?'; $params[] = $fBranch; }
if ($fDept)   { $where[] = 'e.department_id=?'; $params[] = $fDept; }
if ($fFlag !== '') { $where[] = 'e.flag=?'; $params[] = $fFlag; }

$rows = all("SELECT e.*, b.name branch_name, d.name dept_name, p.name pos_name, m.full_name mgr_name
             FROM employees e
             LEFT JOIN branches b ON b.id=e.branch_id
             LEFT JOIN departments d ON d.id=e.department_id
             LEFT JOIN positions p ON p.id=e.position_id
             LEFT JOIN employees m ON m.id=e.direct_manager_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY b.name, d.name, e.full_name", $params);

$subtitle = month_name((int)date('n')) . ' ' . date('Y');
$filterNote = [];
$nActive = 0; $nResign = 0;
foreach ($rows as $r) { $r['flag'] === 'active' ? $nActive++ : $nResign++; }
if ($fBranch) { $bn = val('SELECT name FROM branches WHERE id=?', [$fBranch]); $filterNote[] = 'Branch: ' . $bn; }
if ($fDept)   { $dn = val('SELECT name FROM departments WHERE id=?', [$fDept]); $filterNote[] = 'Departemen: ' . $dn; }
if ($fFlag !== '') $filterNote[] = 'Flag: ' . strtoupper($fFlag);
if ($filterNote) $subtitle .= '  ·  ' . implode(' · ', $filterNote);

$css = "body{font-family:DejaVu Sans,Arial,sans-serif;font-size:9.5pt;color:#222}
table{border-collapse:collapse;width:100%}
td,th{border:1px solid #b6c2d2;padding:4px 5px;font-size:8pt;vertical-align:top}
th{background:#0d3b66;color:#fff;text-align:left}
.num{text-align:right}";

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' . $css . '</style></head><body>'
    . pdf_header_html($co, 'DAFTAR DATA KARYAWAN', $subtitle)
    . '<table><thead><tr>'
    . '<th style="width:2%">#</th><th>ID</th><th>Nama</th><th>Kontak</th><th>Atasan</th>'
    . '<th>Branch</th><th>Departemen</th><th>Position</th><th class="num">Gaji Pokok</th>'
    . '<th>Status</th><th>Kontrak</th><th>Flag</th>'
    . '</tr></thead><tbody>';

$i = 0;
foreach ($rows as $r) {
    $i++;
    $flagColor = $r['flag'] === 'active' ? '#0a7d32' : '#b60000';
    $html .= '<tr>'
        . '<td>' . $i . '</td>'
        . '<td><b>' . e($r['employee_code']) . '</b></td>'
        . '<td>' . e($r['full_name']) . '</td>'
        . '<td>' . e($r['phone'] ?: '-') . '<br><span style="color:#777">' . e($r['email'] ?: '-') . '</span></td>'
        . '<td>' . e($r['mgr_name'] ?? '-') . '</td>'
        . '<td>' . e($r['branch_name'] ?? '-') . '</td>'
        . '<td>' . e($r['dept_name'] ?? '-') . '</td>'
        . '<td>' . e($r['pos_name'] ?? '-') . '</td>'
        . '<td class="num">' . money($r['base_salary']) . '</td>'
        . '<td>' . e($r['employment_status']) . '</td>'
        . '<td>' . e(date_id($r['contract_start'])) . '<br>s/d ' . e(date_id($r['contract_end'])) . '</td>'
        . '<td style="color:' . $flagColor . ';font-weight:bold">' . strtoupper($r['flag']) . '</td>'
        . '</tr>';
}
if (!$rows) {
    $html .= '<tr><td colspan="12" style="text-align:center;color:#777;padding:14px">Tidak ada data karyawan untuk filter ini.</td></tr>';
}
$html .= '</tbody></table>'
    . '<div style="margin-top:10px;font-size:8.5pt">Total: <b>' . count($rows) . '</b> karyawan'
    . ' &nbsp;(Active: <b>' . $nActive . '</b> · Resign: <b>' . $nResign . '</b>)</div>'
    . '<table style="margin-top:26px;width:100%"><tr>'
    . '<td style="width:33%;border:none">Dibuat oleh HR,<br><br><br><b>________________</b></td>'
    . '<td style="width:33%;border:none;text-align:center">Disetujui Manajemen,<br><br><br><b>________________</b></td>'
    . '<td style="width:34%;border:none;text-align:right">Tanggal Cetak: ' . date_id(date('Y-m-d')) . '</td>'
    . '</tr></table></body></html>';

audit('PRINT_EMPLOYEE_LIST', implode(',', array_slice(array_column($rows, 'employee_code'), 0, 5)));
render_pdf($html, 'daftar-karyawan-' . date('Ymd') . '.pdf', 'L');
