<?php
/**
 * Pusat Cetak Laporan (HR/Finance): daftar laporan yang tersedia, pratinjau tabel
 * di halaman, dan tombol cetak PDF.
 *  - employees : data karyawan + gaji pokok (HR)
 *  - attendance: rekap kehadiran bulanan (HR)
 *  - overtime  : rekap lembur per bulan (HR)
 *  - leave     : rekap pengajuan cuti per bulan (HR)
 *  - payslip   : slip gaji per karyawan (semua role — milik sendiri; HR/FIN bebas)
 *  - payroll   : rekap gaji per periode branch (HR) / laporan finance (FIN)
 */
$page_title = 'Cetak Laporan'; $active_menu = 'reports';
require_once __DIR__ . '/includes/header.php';
require_login();

$u = current_user();
$isHR = in_array($u['role'], ['HR', 'FIN'], true);
$type = $_GET['type'] ?? ($isHR ? 'employees' : 'payslip');

$REPORTS = [
    'employees'  => ['label' => 'Data Karyawan & Gaji Pokok', 'icon' => 'bi-people',   'roles' => ['HR']],
    'attendance' => ['label' => 'Rekap Kehadiran Bulanan',    'icon' => 'bi-calendar3', 'roles' => ['HR']],
    'overtime'   => ['label' => 'Rekap Lembur',               'icon' => 'bi-stopwatch', 'roles' => ['HR']],
    'leave'      => ['label' => 'Rekap Pengajuan Cuti',       'icon' => 'bi-suitcase2', 'roles' => ['HR']],
    'payroll'    => ['label' => 'Rekap / Laporan Payroll',    'icon' => 'bi-cash-stack','roles' => ['HR', 'FIN']],
    'payslip'    => ['label' => 'Slip Gaji Saya',             'icon' => 'bi-filetext',  'roles' => ['EMPLOYEE', 'HR', 'FIN']],
];
if (!isset($REPORTS[$type])) $type = array_key_first($REPORTS);
if (!in_array($u['role'], $REPORTS[$type]['roles'], true)) {
    echo '<div class="alert alert-danger">Anda tidak berhak mengakses laporan tersebut.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$fMonth = (int)($_GET['month'] ?? (int)date('n'));
$fYear  = (int)($_GET['year'] ?? (int)date('Y'));
$fBranch = (int)($_GET['branch'] ?? 0);
$fDept = (int)($_GET['dept'] ?? 0);
$branches = all('SELECT * FROM branches WHERE is_active=1 ORDER BY name');
$depts = all('SELECT * FROM departments WHERE is_active=1 ORDER BY name');
$years = range((int)date('Y') - 2, (int)date('Y') + 1);

/* helper: jumlah hari kerja (Sen-Sab) — dipakai pratinjau rekap kehadiran */
function workdays_in_month_local(int $year, int $month): int
{
    $days = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
    $cnt = 0;
    for ($i = 1; $i <= $days; $i++)
        if ((int)date('N', mktime(0, 0, 0, $month, $i, $year)) <= 6) $cnt++;
    return $cnt;
}
?>
<div class="card mb-3 no-print"><div class="card-body py-2">
  <form class="row g-2 align-items-end" method="get">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <?php if ($type !== 'employees' && $type !== 'payslip'): ?>
    <div class="col-md-2"><label class="form-label small mb-0">Bulan</label>
      <select class="form-select form-select-sm" name="month"><?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= $m == $fMonth ? 'selected' : '' ?>><?= e(month_name($m)) ?></option><?php endfor; ?></select></div>
    <div class="col-md-2"><label class="form-label small mb-0">Tahun</label>
      <select class="form-select form-select-sm" name="year"><?php foreach ($years as $y): ?>
        <option <?= $y == $fYear ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <?php if (in_array($type, ['employees', 'attendance', 'overtime', 'leave', 'payroll'], true)): ?>
    <div class="col-md-3"><label class="form-label small mb-0">Branch</label>
      <select class="form-select form-select-sm" name="branch"><option value="0">Semua Branch</option>
      <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $fBranch == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label small mb-0">Departemen</label>
      <select class="form-select form-select-sm" name="dept"><option value="0">Semua Departemen</option>
      <?php foreach ($depts as $d): ?><option value="<?= (int)$d['id'] ?>" <?= $fDept == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="col-md-2 d-flex gap-1">
      <button class="btn btn-sm btn-primary flex-fill">Tampilkan</button>
    </div>
  </form>
</div></div>
<h1 class="page-title mb-3"><i class="bi bi-printer me-2"></i>Cetak Laporan</h1>

<div class="row g-3 mb-3 no-print">
  <?php foreach ($REPORTS as $k => $r): if (!in_array($u['role'], $r['roles'], true)) continue; ?>
    <div class="col-6 col-md-4 col-lg-2">
      <a class="card h-100 text-decoration-none report-tile <?= $type === $k ? 'border-primary shadow-sm' : '' ?>" href="reports.php?type=<?= e($k) ?>">
        <div class="card-body text-center py-3">
          <i class="bi <?= e($r['icon']) ?> fs-3 d-block mb-2 <?= $type === $k ? 'text-primary' : 'text-muted' ?>"></i>
          <span class="small fw-semibold"><?= e($r['label']) ?></span>
        </div></a>
    </div>
  <?php endforeach; ?>
</div>

<h4 class="mb-2"><?= e($REPORTS[$type]['label']) ?>
  <?php if ($type !== 'payslip'): ?>
  <a class="btn btn-sm btn-dark float-end" href="<?=
      ($type === 'employees' ? 'pdf_employee_list.php?branch=' . $fBranch . '&dept=' . $fDept :
      ($type === 'attendance' ? 'pdf_attendance.php?type=monthly&month=' . $fMonth . '&year=' . $fYear . '&branch=' . $fBranch . '&dept=' . $fDept :
      ($type === 'overtime' ? 'pdf_overtime_leave.php?type=overtime&month=' . $fMonth . '&year=' . $fYear . '&branch=' . $fBranch :
      ($type === 'leave' ? 'pdf_overtime_leave.php?type=leave&month=' . $fMonth . '&year=' . $fYear . '&branch=' . $fBranch :
      'javascript:void(0)' /* payroll ditangani di blok bawah */)))) ?>">
    <i class="bi bi-file-earmark-pdf me-1"></i>Cetak PDF</a>
  <?php endif; ?>
</h4>

<?php
/* ================= ISI LAPORAN ================= */
if ($type === 'employees') {
    $sql = "SELECT e.*, b.name branch_name, d.name dept_name, p.name pos_name, m.full_name mgr_name
            FROM employees e LEFT JOIN branches b ON b.id=e.branch_id
            LEFT JOIN departments d ON d.id=e.department_id
            LEFT JOIN positions p ON p.id=e.position_id
            LEFT JOIN employees m ON m.id=e.direct_manager_id WHERE 1=1";
    $pp = [];
    if ($fBranch) { $sql .= ' AND e.branch_id=?'; $pp[] = $fBranch; }
    if ($fDept)   { $sql .= ' AND e.department_id=?'; $pp[] = $fDept; }
    $rows = all($sql . " ORDER BY b.name, d.name, e.full_name", $pp);
    $tot = array_sum(array_column($rows, 'base_salary'));
?>
<div class="card"><div class="card-body table-responsive">
  <table class="table table-sm table-hover align-middle mb-0">
    <thead><tr class="small"><th>Kode</th><th>Nama</th><th>Unit</th><th>Jabatan</th><th>Atasan</th><th>Gaji Pokok</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><span class="badge bg-dark"><?= e($r['employee_code']) ?></span></td>
        <td class="fw-semibold"><?= e($r['full_name']) ?></td>
        <td class="small"><?= e($r['branch_name'] ?? '-') ?> · <?= e($r['dept_name'] ?? '-') ?></td>
        <td class="small"><?= e($r['pos_name'] ?? '-') ?></td>
        <td class="small"><?= e($r['mgr_name'] ?? '-') ?></td>
        <td class="text-end"><?= rupiah($r['base_salary']) ?></td>
        <td><?= status_badge($r['flag']) ?> <span class="small text-muted"><?= e($r['employment_status']) ?></span></td></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr class="table-light fw-bold small"><td colspan="5">TOTAL PAYROLL POTENSIAL (<?= count($rows) ?> karyawan)</td>
      <td class="text-end"><?= rupiah($tot) ?></td><td></td></tr></tfoot>
  </table>
</div></div>

<?php } elseif ($type === 'attendance') {
    $sql = "SELECT e.id, e.full_name, e.employee_code, br.name branch_name, dep.name dept_name,
                   COUNT(a.id) present_days, COALESCE(SUM(a.late_minutes),0) late_minutes,
                   COALESCE(SUM(CASE WHEN a.late_minutes>0 THEN 1 ELSE 0 END),0) late_days
            FROM employees e
            LEFT JOIN branches br ON br.id=e.branch_id
            LEFT JOIN departments dep ON dep.id=e.department_id
            LEFT JOIN attendance_logs a ON a.employee_id=e.id AND MONTH(a.log_date)=? AND YEAR(a.log_date)=?
            WHERE e.flag='active'";
    $pp = [$fMonth, $fYear];
    if ($fBranch) { $sql .= ' AND e.branch_id=?'; $pp[] = $fBranch; }
    if ($fDept)   { $sql .= ' AND e.department_id=?'; $pp[] = $fDept; }
    $rows = all($sql . ' GROUP BY e.id ORDER BY br.name, dep.name, e.full_name', $pp);
?>
<div class="card"><div class="card-body table-responsive">
  <table class="table table-sm mb-0">
    <thead><tr class="small"><th>#</th><th>Karyawan</th><th>Unit</th><th>Hadir</th><th>Telat (hari/menit)</th><th>Alpa*</th></tr></thead>
    <tbody>
    <?php $wd = workdays_in_month_local($fYear, $fMonth); $i = 0; foreach ($rows as $r): $i++;
        $alpa = max(0, $wd - (int)$r['present_days']); ?>
      <tr><td><?= $i ?></td>
        <td class="fw-semibold"><?= e($r['full_name']) ?> <span class="text-muted small"><?= e($r['employee_code']) ?></span></td>
        <td class="small"><?= e($r['branch_name'] ?? '-') ?> · <?= e($r['dept_name'] ?? '-') ?></td>
        <td><span class="badge bg-success-subtle text-success"><?= (int)$r['present_days'] ?></span></td>
        <td class="small"><?= (int)$r['late_days'] ?> hari / <?= (int)$r['late_minutes'] ?> menit</td>
        <td><?= $alpa > 0 ? '<span class="badge bg-danger-subtle text-danger">' . $alpa . '</span>' : '0' ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  <div class="small text-muted mt-2">* Alpa dihitung dari <?= $wd ?> hari kerja (Sen–Sab) dikurangi hari hadir; belum memperhitungkan cuti.</div>
</div></div>

<?php } elseif ($type === 'overtime') {
    $sql = "SELECT o.*, e.full_name, e.employee_code, br.name branch_name
            FROM overtime_requests o JOIN employees e ON e.id=o.employee_id
            LEFT JOIN branches br ON br.id=e.branch_id
            WHERE MONTH(o.work_date)=? AND YEAR(o.work_date)=?";
    $pp = [$fMonth, $fYear];
    if ($fBranch) { $sql .= ' AND e.branch_id=?'; $pp[] = $fBranch; }
    $rows = all($sql . ' ORDER BY o.work_date, e.full_name', $pp);
    $appr = array_filter($rows, fn($r) => $r['status'] === 'APPROVED');
?>
<div class="card"><div class="card-body table-responsive">
  <table class="table table-sm mb-0">
    <thead><tr class="small"><th>Tgl</th><th>Karyawan</th><th>Waktu</th><th>Jam</th><th>Alasan</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td class="small"><?= e(date_id($r['work_date'])) ?></td>
        <td class="fw-semibold small"><?= e($r['full_name']) ?></td>
        <td class="small"><?= e(substr($r['start_time'], 0, 5)) ?> – <?= e(substr($r['end_time'], 0, 5)) ?></td>
        <td><?= number_format((float)$r['duration_hours'], 1) ?></td>
        <td class="small text-muted"><?= e(mb_strimwidth((string)$r['reason'], 0, 60, '…')) ?></td>
        <td><?= status_badge($r['status']) ?></td></tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada pengajuan lembur pada periode ini.</td></tr>
    <?php endif; ?>
    </tbody>
    <tfoot><tr class="table-light fw-bold small"><td colspan="3">Disetujui: <?= count($appr) ?> request</td>
      <td><?= number_format(array_sum(array_column($appr, 'duration_hours')), 1) ?> jam</td><td colspan="2"></td></tr></tfoot>
  </table>
</div></div>

<?php } elseif ($type === 'leave') {
    $sql = "SELECT lr.*, lt.name type_name, lt.is_paid, e.full_name, e.employee_code, br.name branch_name
            FROM leave_requests lr JOIN leave_types lt ON lt.id=lr.leave_type_id
            JOIN employees e ON e.id=lr.employee_id
            LEFT JOIN branches br ON br.id=e.branch_id
            WHERE MONTH(lr.start_date)=? AND YEAR(lr.start_date)=?";
    $pp = [$fMonth, $fYear];
    if ($fBranch) { $sql .= ' AND e.branch_id=?'; $pp[] = $fBranch; }
    $rows = all($sql . ' ORDER BY lr.start_date, e.full_name', $pp);
?>
<div class="card"><div class="card-body table-responsive">
  <table class="table table-sm mb-0">
    <thead><tr class="small"><th>Tgl Mulai</th><th>Karyawan</th><th>Jenis Cuti</th><th>Lama</th><th>Keperluan</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td class="small"><?= e(date_id($r['start_date'])) ?> – <?= e(date_id($r['end_date'])) ?></td>
        <td class="fw-semibold small"><?= e($r['full_name']) ?></td>
        <td class="small"><?= e($r['type_name']) ?> <?= $r['is_paid'] ? '' : '<span class="badge bg-warning text-dark">unpaid</span>' ?></td>
        <td><?= (float)$r['days_count'] ?> hr</td>
        <td class="small text-muted"><?= e(mb_strimwidth((string)$r['purpose'], 0, 50, '…')) ?></td>
        <td><?= status_badge($r['status']) ?></td></tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada pengajuan cuti pada periode ini.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div></div>

<?php } elseif ($type === 'payroll') {
    $q = "SELECT p.*, b.name branch_name FROM payroll_periods p JOIN branches b ON b.id=p.branch_id WHERE p.status<>'OPEN'";
    $pp = [];
    if ($fBranch) { $q .= ' AND p.branch_id=?'; $pp[] = $fBranch; }
    $q .= ' AND p.period_month=? AND p.period_year=? ORDER BY b.name';
    $pp[] = $fMonth; $pp[] = $fYear;
    $periods = all($q, $pp);
?>
<div class="card"><div class="card-body">
  <?php if (!$periods): ?>
    <div class="alert alert-secondary mb-0">Belum ada periode terkunci/terkirim untuk <?= e(month_name($fMonth)) ?> <?= $fYear ?>.</div>
  <?php else: ?>
  <table class="table table-sm align-middle mb-3">
    <thead><tr class="small"><th>Branch</th><th>Periode</th><th>Slip</th><th>Total Net</th><th>Status</th><th class="text-end">PDF</th></tr></thead>
    <tbody>
    <?php foreach ($periods as $p):
        $totNet = (float)val('SELECT COALESCE(SUM(net_pay),0) FROM payslips WHERE period_id=?', [$p['id']]);
        $cnt = (int)val('SELECT COUNT(*) FROM payslips WHERE period_id=?', [$p['id']]);
        $pdfType = $u['role'] === 'FIN' ? 'laporan' : 'rekap'; ?>
      <tr><td class="fw-semibold"><?= e($p['branch_name']) ?></td>
        <td class="small"><?= e(month_name((int)$p['period_month'])) ?> <?= (int)$p['period_year'] ?></td>
        <td><?= $cnt ?></td><td class="text-end"><?= rupiah($totNet) ?></td>
        <td><?= status_badge($p['status']) ?></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-dark" target="_blank"
          href="pdf_payroll.php?type=<?= $pdfType ?>&period=<?= (int)$p['id'] ?>&dept=<?= $fDept ?>"><i class="bi bi-file-earmark-pdf"></i> Cetak</a></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div></div>

<?php } else { /* payslip saya */
    $eid = (int)($u['employee_id'] ?? 0);
    $slips = all("SELECT ps.*, p.period_month, p.period_year, b.name branch_name FROM payslips ps
                  JOIN payroll_periods p ON p.id=ps.period_id JOIN branches b ON b.id=p.branch_id
                  WHERE ps.employee_id=? AND ps.status IN ('FINAL','SENT_FINANCE','PAID')
                  ORDER BY p.period_year DESC, p.period_month DESC", [$eid]);
    if (!$eid) echo '<div class="alert alert-warning">Akun Anda belum tertaut data karyawan.</div>';
?>
<div class="card"><div class="card-body table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead><tr class="small"><th>Periode</th><th>Branch</th><th class="text-end">Bruto</th><th class="text-end">Potongan</th><th class="text-end">Net</th><th>Status</th><th class="text-end">PDF</th></tr></thead>
    <tbody>
    <?php foreach ($slips as $s): ?>
      <tr><td class="fw-semibold"><?= e(month_name((int)$s['period_month'])) ?> <?= (int)$s['period_year'] ?></td>
        <td class="small"><?= e($s['branch_name']) ?></td>
        <td class="text-end small"><?= money($s['gross_pay']) ?></td>
        <td class="text-end small text-danger">−<?= money($s['total_deduction']) ?></td>
        <td class="text-end fw-bold"><?= rupiah($s['net_pay']) ?></td>
        <td><?= status_badge($s['status']) ?></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-dark" target="_blank" href="pdf_payroll.php?type=slip&slip=<?= (int)$s['id'] ?>"><i class="bi bi-file-earmark-pdf"></i></a></td></tr>
    <?php endforeach; if (!$slips): ?>
      <tr><td colspan="7" class="text-center text-muted py-4">Belum ada slip gaji terbit.</td></tr>
    <?php endif; ?>
    </tbody></table>
</div></div>
<?php } ?>

<script>
/* tombol cetak PDF untuk laporan employees memakai generator kartu massal */
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
