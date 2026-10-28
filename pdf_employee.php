<?php
/**
 * Kartu data karyawan (employee card) PDF: identitas, unit organisasi, gaji pokok,
 * kontak & atasan langsung. Akses: HR bebas; karyawan bisa mencetak kartunya sendiri.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pdf.php';
require_login();

$u = current_user();
$id = (int)($_GET['id'] ?? 0);
if (!in_array($u['role'], ['HR', 'FIN'], true)) {
    $id = (int)($u['employee_id'] ?? 0); // karyawan hanya kartu dirinya
}

$emp = one("SELECT e.*, b.name branch_name, b.code branch_code, d.name dept_name, p.name pos_name,
                   m.full_name mgr_name, m.employee_code mgr_code, m.email mgr_email, m.phone mgr_phone
            FROM employees e
            LEFT JOIN branches b ON b.id=e.branch_id
            LEFT JOIN departments d ON d.id=e.department_id
            LEFT JOIN positions p ON p.id=e.position_id
            LEFT JOIN employees m ON m.id=e.direct_manager_id
            WHERE e.id=?", [$id]);
if (!$emp) exit('Data karyawan tidak ditemukan.');

$co = company();

/* Foto karyawan */
$photo = '';
if (!empty($emp['photo_path']) && is_file(BASE_PATH . '/' . $emp['photo_path'])) {
    $type = pathinfo($emp['photo_path'], PATHINFO_EXTENSION) === 'png' ? 'png' : 'jpeg';
    $photo = '<img src="data:image/' . $type . ';base64,' . base64_encode(file_get_contents(BASE_PATH . '/' . $emp['photo_path'])) . '" style="width:88px;height:105px;object-fit:cover;border:2px solid #0d3b66;border-radius:6px"/>';
} else {
    $photo = '<div style="width:88px;height:105px;border:2px dashed #9ab;background:#eef3f9;border-radius:6px;text-align:center;font-size:8pt;color:#678;padding-top:38px">FOTO<br>3x4</div>';
}

/* riwayat singkat: cuti & lembur 12 bulan terakhir */
$eid = (int)$emp['id'];
$lvCount = (int)val("SELECT COUNT(*) FROM leave_requests WHERE employee_id=? AND status='APPROVED'", [$eid]);
$otCount = (int)val("SELECT COUNT(*) FROM overtime_requests WHERE employee_id=? AND status='APPROVED'", [$eid]);
$attDays = (int)val("SELECT COUNT(*) FROM attendance_logs WHERE employee_id=?", [$eid]);
$lastLog = one("SELECT log_date, check_in_at, check_out_at FROM attendance_logs WHERE employee_id=? ORDER BY log_date DESC LIMIT 1", [$eid]);

$rows = [
    ['Nama Lengkap', $emp['full_name']],
    ['Kode Karyawan', $emp['employee_code']],
    ['Foto / KTP No.', $emp['photo_path'] ? 'tersimpan di sistem' : 'lengkapi via Profil'],
    ['Alamat', $emp['address'] ?: '-'],
    ['No. HP', $emp['phone'] ?: '-'],
    ['Email', $emp['email'] ?: '-'],
    ['Total Hari Hadir', $attDays . ' hari'],
    ['Pengajuan Cuti Disetujui', $lvCount . ' kali'],
    ['Lembur Disetujui', $otCount . ' kali'],
    ['Absensi Terakhir', $lastLog ? date_id($lastLog['log_date']) . ' · in ' . ($lastLog['check_in_at'] ? date('H:i', strtotime($lastLog['check_in_at'])) : '-')
                                  . ' · out ' . ($lastLog['check_out_at'] ? date('H:i', strtotime($lastLog['check_out_at'])) : '-') : 'belum ada'],
    ['Branch', ($emp['branch_name'] ?? '-') . ' (' . ($emp['branch_code'] ?? '-') . ')'],
    ['Departemen', $emp['dept_name'] ?? '-'],
    ['Jabatan', $emp['pos_name'] ?? '-'],
    ['Atasan Langsung', ($emp['mgr_name'] ?? '-') . ($emp['mgr_code'] ? ' · ' . $emp['mgr_code'] : '')],
    ['Email / HP Atasan', trim(($emp['mgr_email'] ?? '') . ' / ' . ($emp['mgr_phone'] ?? ''), ' /') ?: '-'],
    ['Tanggal Masuk', $emp['hire_date'] ? date_id($emp['hire_date']) : '-'],
    ['Status Kerja', $emp['employment_status'] ?: '-'],
    ['Masa Kontrak', ($emp['contract_start'] ? date_id($emp['contract_start']) : '-') . ' s/d ' . ($emp['contract_end'] ? date_id($emp['contract_end']) : '—')],
    ['Gaji Pokok', rupiah($emp['base_salary'])],
];

$tableRows = '';
for ($i = 0; $i < count($rows); $i += 2) {
    $a = $rows[$i];
    $b = $rows[$i + 1] ?? ['', ''];
    $tableRows .= '<tr>'
        . '<td style="background:#f2f6fb;width:22%;font-weight:bold">' . htmlspecialchars($a[0]) . '</td>'
        . '<td style="width:28%">' . nl2br(htmlspecialchars((string)$a[1])) . '</td>'
        . '<td style="background:#f2f6fb;width:22%;font-weight:bold">' . htmlspecialchars($b[0]) . '</td>'
        . '<td style="width:28%">' . ($b[1] !== '' ? nl2br(htmlspecialchars((string)$b[1])) : '') . '</td></tr>';
}

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
  body{font-family:DejaVu Sans,Arial,sans-serif;font-size:10pt;color:#222}
  table{border-collapse:collapse;width:100%}
  td,th{border:1px solid #b6c2d2;padding:5px 7px;font-size:9pt}
  th{background:#0d3b66;color:#fff;text-align:left}
  </style></head><body>'
  . pdf_header_html($co, 'KARTU DATA KARYAWAN', 'Employee Identity Card — Sistem HRIS')
  . '<table style="margin-bottom:10px"><tr>'
  . '<td rowspan="6" style="width:110px;text-align:center;border:none;padding:0">' . $photo . '</td>'
  . '<td style="background:#f2f6fb;width:20%;font-weight:bold;border:none;border-bottom:1px solid #dde">Nama</td>'
  . '<td style="border:none;border-bottom:1px solid #dde"><b style="font-size:12pt">' . htmlspecialchars($emp['full_name']) . '</b></td>'
  . '<td style="background:#f2f6fb;width:20%;font-weight:bold;border:none;border-bottom:1px solid #dde">Kode</td>'
  . '<td style="border:none;border-bottom:1px solid #dde"><b>' . htmlspecialchars($emp['employee_code']) . '</b></td></tr>'
  . '<tr><td style="background:#f2f6fb;font-weight:bold;border:none;border-bottom:1px solid #dde">Jabatan</td>'
  . '<td style="border:none;border-bottom:1px solid #dde">' . htmlspecialchars($emp['pos_name'] ?? '-') . '</td>'
  . '<td style="background:#f2f6fb;font-weight:bold;border:none;border-bottom:1px solid #dde">Departemen</td>'
  . '<td style="border:none;border-bottom:1px solid #dde">' . htmlspecialchars($emp['dept_name'] ?? '-') . '</td></tr>'
  . '<tr><td style="background:#f2f6fb;font-weight:bold;border:none;border-bottom:1px solid #dde">Branch</td>'
  . '<td style="border:none;border-bottom:1px solid #dde">' . htmlspecialchars($emp['branch_name'] ?? '-') . '</td>'
  . '<td style="background:#f2f6fb;font-weight:bold;border:none;border-bottom:1px solid #dde">Status</td>'
  . '<td style="border:none;border-bottom:1px solid #dde">' . htmlspecialchars(strtoupper($emp['flag'])) . ' · ' . htmlspecialchars($emp['employment_status']) . '</td></tr>'
  . '<tr><td style="background:#f2f6fb;font-weight:bold;border:none;border-bottom:1px solid #dde">Atasan</td>'
  . '<td style="border:none;border-bottom:1px solid #dde" colspan="3">' . htmlspecialchars($emp['mgr_name'] ?? '-') . '</td></tr>'
  . '<tr><td style="background:#f2f6fb;font-weight:bold;border:none;border-bottom:1px solid #dde">Email</td>'
  . '<td style="border:none;border-bottom:1px solid #dde">' . htmlspecialchars($emp['email'] ?? '-') . '</td>'
  . '<td style="background:#f2f6fb;font-weight:bold;border:none;border-bottom:1px solid #dde">No. HP</td>'
  . '<td style="border:none;border-bottom:1px solid #dde">' . htmlspecialchars($emp['phone'] ?? '-') . '</td></tr>'
  . '</table>'
  . '<table><thead><tr><th colspan="4">RINCIAN DATA DIRI</th></tr></thead><tbody>' . $tableRows . '</tbody></table>'
  . '<div style="margin-top:26px;width:45%;float:right;text-align:center;font-size:9pt">'
  . 'Diterbitkan oleh HR,<br>' . date_id(date('Y-m-d')) . '<br><br><br><br>'
  . '<b>________________________</b><br>Petugas HRIS'
  . '</div><div style="clear:both"></div>'
  . '<div style="margin-top:10px;font-size:7.5pt;color:#888">Dokumen ini dicetak dari sistem HRIS ' . htmlspecialchars($co['name']) .
      ' pada ' . date('d-m-Y H:i') . ' WIB. Data bersifat internal &amp; rahasia.</div>'
  . '</body></html>';

render_pdf($html, 'kartu-karyawan-' . preg_replace('/[^A-Za-z0-9]+/', '-', $emp['employee_code']) . '.pdf');
