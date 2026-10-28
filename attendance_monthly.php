<?php
/**
 * Rekap Bulanan Kehadiran (HR) per branch & departemen + export PDF.
 */
$page_title = 'Rekap Bulanan'; $active_menu = 'att_monthly';
require_once __DIR__ . '/includes/header.php';
require_role('HR');

$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year'] ?? date('Y'));
$fBranch = (int)($_GET['branch'] ?? 0);
$fDept = (int)($_GET['dept'] ?? 0);

$sql = "SELECT e.id, e.full_name, e.employee_code, br.name branch_name, dep.name dept_name,
               COUNT(a.id) present_days,
               COALESCE(SUM(a.late_minutes),0) late_minutes,
               COALESCE(SUM(CASE WHEN a.late_minutes>0 THEN 1 ELSE 0 END),0) late_days,
               COALESCE(SUM(a.overtime_hours),0) ot_hours,
               COALESCE(SUM(a.work_hours),0) work_hours,
               (SELECT COALESCE(SUM(lr.days_count),0) FROM leave_requests lr JOIN leave_types lt ON lt.id=lr.leave_type_id
                 WHERE lr.employee_id=e.id AND lr.status='APPROVED' AND MONTH(LEAST(lr.end_date, ?))=? AND YEAR(lr.start_date)=?) lv_paid,
               (SELECT COALESCE(SUM(lr.days_count),0) FROM leave_requests lr JOIN leave_types lt ON lt.id=lr.leave_type_id
                 WHERE lr.employee_id=e.id AND lr.status='APPROVED' AND lt.is_paid=0 AND MONTH(lr.start_date)=? AND YEAR(lr.start_date)=?) lv_unpaid
        FROM employees e
        LEFT JOIN branches br ON br.id=e.branch_id
        LEFT JOIN departments dep ON dep.id=e.department_id
        LEFT JOIN attendance_logs a ON a.employee_id=e.id AND MONTH(a.log_date)=? AND YEAR(a.log_date)=?
        WHERE e.flag='active'";
$lastDay = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . date('t', strtotime("$year-$month-01"));
$p = [$lastDay, $month, $year, $month, $year, $month, $year];
if ($fBranch) { $sql .= ' AND e.branch_id=?'; $p[] = $fBranch; }
if ($fDept)   { $sql .= ' AND e.department_id=?'; $p[] = $fDept; }
$rows = all($sql . ' ORDER BY br.name, dep.name, e.full_name', $p);

// jumlah hari kerja dalam bulan (Sen-Sab sederhana) utk hitung alpa
$workdaysInMonth = 0;
for ($i = 1; $i <= (int)date('t', strtotime("$year-$month-01")); $i++) {
    if ((int)date('N', strtotime("$year-$month-$i")) <= 6) $workdaysInMonth++;
}
$branches = all('SELECT * FROM branches WHERE is_active=1 ORDER BY name');
$depts = all('SELECT * FROM departments WHERE is_active=1 ORDER BY name');
$years = range((int)date('Y') - 2, (int)date('Y') + 1);
?>
<h1 class="page-title mb-3"><i class="bi bi-calendar-month me-2"></i>Rekap Kehadiran Bulanan</h1>

<div class="card mb-3"><div class="card-body py-2">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-2"><label class="form-label small mb-0">Bulan</label><select class="form-select form-select-sm" name="month">
      <?php for ($m = 1; $m <= 12; $m++): ?><option value="<?= $m ?>" <?= $month==$m?'selected':'' ?>><?= e(month_name($m)) ?></option><?php endfor; ?></select></div>
    <div class="col-md-1"><label class="form-label small mb-0">Tahun</label><select class="form-select form-select-sm" name="year">
      <?php foreach ($years as $y): ?><option <?= $year==$y?'selected':'' ?>><?= $y ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label small mb-0">Per Branch</label><select class="form-select form-select-sm" name="branch"><option value="0">Semua</option>
      <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $fBranch==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label small mb-0">Per Departemen</label><select class="form-select form-select-sm" name="dept"><option value="0">Semua</option>
      <?php foreach ($depts as $m2): ?><option value="<?= (int)$m2['id'] ?>" <?= $fDept==$m2['id']?'selected':'' ?>><?= e($m2['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><button class="btn btn-sm btn-primary">Tampilkan</button>
      <a class="btn btn-sm btn-outline-dark" href="pdf_attendance.php?type=monthly&month=<?= $month ?>&year=<?= $year ?>&branch=<?= $fBranch ?>&dept=<?= $fDept ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a></div>
  </form>
</div></div>

<div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>
  Hari kerja bulan ini diasumsikan <b><?= $workdaysInMonth ?></b> hari (Senin–Sabtu). Alpa = hari kerja − hadir − cuti.</div>

<div class="card"><div class="card-body table-responsive">
<input class="form-control form-control-sm mb-2 no-print" placeholder="Cari..." data-table-filter="#tblMonthly" style="max-width:280px">
<table class="table table-hover align-middle mb-0" id="tblMonthly">
<thead><tr><th>Karyawan</th><th>Branch</th><th>Departemen</th><th>Hadir</th><th>Telat (hari/menit)</th><th>Cuti Gaji</th><th>Cuti Unpaid</th><th>Lembur (jam)</th><th>Alpa</th></tr></thead>
<tbody>
<?php foreach ($rows as $r):
  $alpa = max(0, $workdaysInMonth - (int)$r['present_days'] - (float)$r['lv_paid'] - (float)$r['lv_unpaid']); ?>
<tr>
  <td><b><?= e($r['full_name']) ?></b><br><span class="small text-muted"><?= e($r['employee_code']) ?></span></td>
  <td class="small"><?= e($r['branch_name'] ?? '-') ?></td>
  <td class="small"><?= e($r['dept_name'] ?? '-') ?></td>
  <td><span class="badge bg-success-subtle text-success"><?= (int)$r['present_days'] ?></span></td>
  <td class="small"><?= (int)$r['late_days'] ?> hr / <span class="text-danger fw-semibold"><?= (int)$r['late_minutes'] ?> mnt</span></td>
  <td><?= (float)$r['lv_paid'] ?: '-' ?></td>
  <td><?= (float)$r['lv_unpaid'] ?: '-' ?></td>
  <td><?= number_format((float)$r['ot_hours'], 1) ?></td>
  <td><?= $alpa > 0 ? '<span class="badge bg-danger">' . $alpa . '</span>' : '<span class="text-success">0</span>' ?></td>
</tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="9" class="text-center text-muted py-4">Tidak ada data.</td></tr><?php endif; ?>
</tbody></table>
</div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
