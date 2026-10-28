<?php
/**
 * Layout header + sidebar navigasi (Bootstrap 5, responsive).
 * $page_title, $active_menu opsional.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_login();

$u = current_user();
$emp = !empty($u['employee_id']) ? one('SELECT e.*, b.name branch_name, d.name dept_name, p.name pos_name FROM employees e
        LEFT JOIN branches b ON b.id=e.branch_id LEFT JOIN departments d ON d.id=e.department_id
        LEFT JOIN positions p ON p.id=e.position_id WHERE e.id=?', [$u['employee_id']]) : null;
$co = company();
$title = $page_title ?? 'Dashboard';

$NAV = [];
$NAV[] = ['index.php', 'bi-speedometer2', 'Dashboard', 'dashboard'];

/* KARYAWAN (role karyawan & HR): data diri read-only */
$NAV[] = ['my_profile.php', 'bi-person-vcard', 'Data Saya', 'profile'];

if ($u['role'] === 'HR') {
    $NAV[] = ['section', 'Karyawan & Organisasi'];
    $NAV[] = ['employees.php', 'bi-people', 'Data Karyawan', 'employees'];
    $NAV[] = ['organization.php', 'diagram-3', 'Unit Organisasi', 'organization'];
    $NAV[] = ['company_settings.php', 'buildings', 'Info Perusahaan', 'company'];
}

$NAV[] = ['section', 'Kehadiran & Jadwal'];
$NAV[] = ['schedule.php', 'bi-calendar-week', 'Jadwal / Shift', 'schedule'];
$NAV[] = ['schedule_requests.php', 'bi-arrow-left-right', 'Ubah Jadwal', 'sched_req'];
$NAV[] = ['attendance.php', 'bi-geo-alt', 'Absensi (PWA)', 'attendance'];
if ($u['role'] === 'HR') {
    $NAV[] = ['attendance_log.php', 'bi-clock-history', 'Log Absensi', 'att_log'];
    $NAV[] = ['attendance_daily.php', 'bi-calendar-day', 'Rekap Harian', 'att_daily'];
    $NAV[] = ['attendance_monthly.php', 'bi-calendar-month', 'Rekap Bulanan', 'att_monthly'];
    $NAV[] = ['attendance_settings.php', 'bi-gear', 'Setting Absensi', 'att_setting'];
}

$NAV[] = ['section', 'Pengajuan & Persetujuan'];
$NAV[] = ['overtime.php', 'bi-stopwatch', 'Lembur', 'overtime'];
$NAV[] = ['leave.php', 'bi-umbrella', 'Cuti', 'leave'];
if ($u['role'] === 'HR') {
    $NAV[] = ['approvals.php', 'bi-check2-square', 'Approval Center', 'approvals',
              (int)val("SELECT COUNT(*) FROM overtime_requests WHERE status IN ('PENDING_MGR','PENDING_HR')")
            + (int)val("SELECT COUNT(*) FROM leave_requests WHERE status IN ('PENDING_MGR','PENDING_HR')")
            + (int)val("SELECT COUNT(*) FROM schedule_requests WHERE status IN ('PENDING_MGR','PENDING_HR')")];
} elseif ($emp && $emp['direct_manager_id']) {
    // manager melihat antrian approval timnya juga lewat approvals.php
    $NAV[] = ['approvals.php', 'bi-check2-square', 'Approval Tim', 'approvals',
              (int)val("SELECT COUNT(*) FROM overtime_requests o JOIN employees e ON e.id=o.employee_id WHERE e.direct_manager_id=? AND o.status='PENDING_MGR'", [$emp['id']])
            + (int)val("SELECT COUNT(*) FROM leave_requests l JOIN employees e ON e.id=l.employee_id WHERE e.direct_manager_id=? AND l.status='PENDING_MGR'", [$emp['id']])
            + (int)val("SELECT COUNT(*) FROM schedule_requests s JOIN employees e ON e.id=s.employee_id WHERE e.direct_manager_id=? AND s.status='PENDING_MGR'", [$emp['id']])];
}

if ($u['role'] === 'HR') {
    $NAV[] = ['section', 'Payroll'];
    $NAV[] = ['payroll_components.php', 'bi-list-columns', 'Komponen Gaji', 'components'];
    $NAV[] = ['payroll_component_assign.php', 'bi-person-plus', 'Assign Komponen', 'assign_comp'];
    $NAV[] = ['payroll_settings.php', 'bi-percent', 'Setting PPH & BPJS', 'payroll_setting'];
    $NAV[] = ['payroll_periods.php', 'bi-calendar-x', 'Periode Payroll', 'periods'];
    $NAV[] = ['payroll_process.php', 'bi-cash-stack', 'Proses Payroll', 'process'];
}
if ($u['role'] === 'FIN') {
    $NAV[] = ['section', 'Finance'];
    $NAV[] = ['finance_payroll.php', 'bi-bank', 'Pembayaran Payroll', 'finance'];
}
$NAV[] = ['section', 'Dokumen Saya'];
$NAV[] = ['myslips.php', 'bi-filetext', 'Slip Gaji', 'myslips'];
if ($u['role'] !== 'EMPLOYEE') {
    $NAV[] = ['reports.php', 'bi-printer', 'Cetak Laporan', 'reports'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0d3b66">
<title><?= e($title) ?> · <?= e($co['name']) ?></title>
<link rel="manifest" href="manifest.json">
<link href="assets/vendor/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<nav class="topbar">
  <button class="btn btn-link text-white px-2 d-lg-none" id="btnSidebar"><i class="bi bi-list fs-4"></i></button>
  <a class="brand" href="index.php">
    <?php if (!empty($co['logo_path'])): ?><img src="<?= e($co['logo_path']) ?>" alt="logo"><?php endif; ?>
    <span><strong>HRIS</strong> <span class="d-none d-sm-inline"><?= e($co['name']) ?></span></span>
  </a>
  <div class="ms-auto d-flex align-items-center gap-2">
    <span class="badge bg-warning text-dark role-badge"><?= e($u['role']) ?></span>
    <div class="dropdown">
      <a class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
        <span class="avatar-circle"><?= e(strtoupper(substr($u['email'] ?? 'U', 0, 1))) ?></span>
        <span class="d-none d-md-inline ms-2 small"><?= e($u['name'] ?? $u['email']) ?></span>
      </a>
      <ul class="dropdown-menu dropdown-menu-end shadow">
        <li><span class="dropdown-item-text small text-muted"><?= e($emp['full_name'] ?? $u['email']) ?></span></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="my_profile.php"><i class="bi bi-person me-2"></i>Profil Saya</a></li>
        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="layout">
  <aside class="sidebar" id="sidebar">
    <div class="p-3 border-bottom sidebar-head">
      <div class="fw-bold small text-uppercase text-white-50">Menu Utama</div>
    </div>
    <ul class="nav flex-column py-2">
    <?php foreach ($NAV as $item): ?>
      <?php if ($item[0] === 'section'): ?>
        <li class="nav-section"><?= e($item[1]) ?></li>
      <?php else: ?>
        <li><a class="nav-link <?= ($item[3] === ($active_menu ?? '')) ? 'active' : '' ?>" href="<?= e($item[0]) ?>">
          <i class="bi bi-<?= e($item[1]) ?>"></i><span><?= e($item[2]) ?></span>
          <?php if (!empty($item[4])): ?><span class="badge rounded-pill bg-danger ms-auto"><?= (int)$item[4] ?></span><?php endif; ?>
        </a></li>
      <?php endif; ?>
    <?php endforeach; ?>
    </ul>
    <div class="sidebar-footer small text-white-50 p-3">v<?= APP_VERSION ?> · &copy; <?= date('Y') ?></div>
  </aside>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <main class="content">
    <?php foreach (get_flashes() as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-info-circle me-1"></i><?= e($f['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endforeach; ?>
