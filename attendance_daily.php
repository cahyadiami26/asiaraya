<?php
/**
 * Daily Attendance (HR): matriks kehadiran satu hari per branch & departemen.
 */
$page_title = 'Rekap Kehadiran Harian'; $active_menu = 'att_daily';
require_once __DIR__ . '/includes/header.php';
require_role('HR');

$d = $_GET['date'] ?? date('Y-m-d');
$fBranch = (int)($_GET['branch'] ?? 0);
$fDept = (int)($_GET['dept'] ?? 0);

$sql = "SELECT e.id, e.full_name, e.employee_code, dep.name dept_name, br.name branch_name,
               a.check_in_at, a.check_out_at, a.late_minutes,
               CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END has_sched,
               lr.id leave_id
        FROM employees e
        LEFT JOIN branches br ON br.id=e.branch_id
        LEFT JOIN departments dep ON dep.id=e.department_id
        LEFT JOIN attendance_logs a ON a.employee_id=e.id AND a.log_date=?
        LEFT JOIN schedules s ON s.employee_id=e.id AND ? BETWEEN s.start_date AND s.end_date
        LEFT JOIN leave_requests lr ON lr.employee_id=e.id AND lr.status='APPROVED' AND ? BETWEEN lr.start_date AND lr.end_date
        WHERE e.flag='active'";
$p = [$d, $d, $d];
if ($fBranch) { $sql .= ' AND e.branch_id=?'; $p[] = $fBranch; }
if ($fDept)   { $sql .= ' AND e.department_id=?'; $p[] = $fDept; }
$rows = all($sql . ' ORDER BY br.name, dep.name, e.full_name', $p);

$present = array_filter($rows, fn($r) => !empty($r['check_in_at']));
$late    = array_filter($rows, fn($r) => (int)$r['late_minutes'] > 0);
$onLeave = array_filter($rows, fn($r) => !empty($r['leave_id']));
$absent  = array_filter($rows, fn($r) => empty($r['check_in_at']) && empty($r['leave_id']) && $r['has_sched']);
$branches = all('SELECT * FROM branches WHERE is_active=1 ORDER BY name');
$depts = all('SELECT * FROM departments WHERE is_active=1 ORDER BY name');
?>
<h1 class="page-title mb-3"><i class="bi bi-calendar-day me-2"></i>Daily Attendance</h1>

<div class="card mb-3"><div class="card-body py-2">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-2"><label class="form-label small mb-0">Tanggal</label><input type="date" class="form-control form-control-sm" name="date" value="<?= e($d) ?>"></div>
    <div class="col-md-3"><label class="form-label small mb-0">Per Branch</label><select class="form-select form-select-sm" name="branch"><option value="0">Semua</option>
      <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $fBranch==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label small mb-0">Per Departemen</label><select class="form-select form-select-sm" name="dept"><option value="0">Semua</option>
      <?php foreach ($depts as $m): ?><option value="<?= (int)$m['id'] ?>" <?= $fDept==$m['id']?'selected':'' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><button class="btn btn-sm btn-primary">Tampilkan</button>
      <a class="btn btn-sm btn-outline-dark" href="pdf_attendance.php?type=daily&date=<?= e($d) ?>&branch=<?= $fBranch ?>&dept=<?= $fDept ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a></div>
  </form>
</div></div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="stat-card bg-grad-green"><div><div class="num"><?= count($present) ?></div><div class="lbl">Hadir</div></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card bg-grad-orange"><div><div class="num"><?= count($late) ?></div><div class="lbl">Terlambat</div></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card bg-grad-blue"><div><div class="num"><?= count($onLeave) ?></div><div class="lbl">Cuti Disetujui</div></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card bg-grad-red"><div><div class="num"><?= count($absent) ?></div><div class="lbl">Tanpa Keterangan (Alpa)</div></div></div></div>
</div>

<div class="card"><div class="card-body table-responsive">
<input class="form-control form-control-sm mb-2 no-print" placeholder="Cari nama..." data-table-filter="#tblDaily" style="max-width:280px">
<table class="table table-hover align-middle mb-0" id="tblDaily">
<thead><tr><th>Karyawan</th><th>Branch</th><th>Departemen</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Telat</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($rows as $r):
  if (!empty($r['check_in_at'])) $st = '<span class="badge bg-success">Hadir</span>';
  elseif (!empty($r['leave_id'])) $st = '<span class="badge bg-info text-dark">Cuti</span>';
  elseif ($r['has_sched']) $st = '<span class="badge bg-danger">Alpha?</span>';
  else $st = '<span class="badge bg-secondary">Non-Shift</span>';
?>
<tr>
  <td><b><?= e($r['full_name']) ?></b><br><span class="small text-muted"><?= e($r['employee_code']) ?></span></td>
  <td class="small"><?= e($r['branch_name'] ?? '-') ?></td>
  <td class="small"><?= e($r['dept_name'] ?? '-') ?></td>
  <td><?= $r['check_in_at'] ? e(date('H:i', strtotime($r['check_in_at']))) : '-' ?></td>
  <td><?= $r['check_out_at'] ? e(date('H:i', strtotime($r['check_out_at']))) : '-' ?></td>
  <td><?= (int)$r['late_minutes'] > 0 ? '<span class="text-danger fw-bold">' . (int)$r['late_minutes'] . ' mnt</span>' : '-' ?></td>
  <td><?= $st ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
