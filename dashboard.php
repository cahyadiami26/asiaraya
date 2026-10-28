<?php
/**
 * Dashboard adaptif: KARYAWAN | HR | FIN
 */
$page_title = 'Dashboard';
$active_menu = 'dashboard';
require_once __DIR__ . '/includes/header.php';

$u = current_user();
$today = date('Y-m-d');
$month = (int)date('n'); $year = (int)date('Y');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <div class="text-muted-sm"><?= e(date_id($today, true)) ?> · <?= e(date('H:i')) ?> WIB</div>
  </div>
  <span class="badge bg-primary rounded-pill px-3 py-2"><?= e($u['role']) ?></span>
</div>

<?php if ($u['role'] === 'EMPLOYEE' || $emp): ?>
<!-- ================= DASHBOARD KARYAWAN ================= -->
<?php if ($emp): 
  $attToday = one('SELECT * FROM attendance_logs WHERE employee_id=? AND log_date=?', [$emp['id'], $today]);
  $schedToday = one('SELECT s.*, sh.name shift_name, b.name branch FROM schedules s
        JOIN shifts sh ON sh.id=s.shift_id JOIN branches b ON b.id=s.branch_id
        WHERE s.employee_id=? AND ? BETWEEN s.start_date AND s.end_date', [$emp['id'], $today]);
  $cntOT = all("SELECT status, COUNT(*) c FROM overtime_requests WHERE employee_id=? GROUP BY status", [$emp['id']]);
  $cntLV = all("SELECT status, COUNT(*) c FROM leave_requests WHERE employee_id=? GROUP BY status", [$emp['id']]);
  $otMap = array_column($cntOT, 'c', 'status'); $lvMap = array_column($cntLV, 'c', 'status');
  $lastSlip = one('SELECT p.*, pp.period_month, pp.period_year, br.name branch FROM payslips p
        JOIN payroll_periods pp ON pp.id=p.period_id JOIN branches br ON br.id=pp.branch_id
        WHERE p.employee_id=? ORDER BY p.id DESC', [$emp['id']]);
  $monthAtt = all('SELECT COALESCE(SUM(late_minutes),0) late, COUNT(*) present FROM attendance_logs WHERE employee_id=? AND MONTH(log_date)=? AND YEAR(log_date)=?', [$emp['id'], $month, $year]);
  $quota = all('SELECT lt.name, lq.quota_days, lq.used_days FROM leave_quotas lq JOIN leave_types lt ON lt.id=lq.leave_type_id WHERE lq.employee_id=? AND lq.year=?', [$emp['id'], $year]);
?>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="stat-card bg-grad-blue"><i class="bi bi-calendar-check icon"></i>
    <div><div class="num"><?= $schedToday ? time_hm($schedToday['check_in_time']) : '-' ?></div>
    <div class="lbl">Jam masuk hari ini<br><?= e($schedToday['shift_name'] ?? 'Tidak ada jadwal') ?></div></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card bg-grad-green"><i class="bi bi-geo-alt icon"></i>
    <div><div class="num"><?= $attToday && $attToday['check_in_at'] ? 'HADIR' : 'BELUM' ?></div>
    <div class="lbl">Status absensi hari ini<br><?= $attToday && $attToday['check_in_at'] ? e(date('H:i', strtotime($attToday['check_in_at']))) . ' WIB' : 'Silakan check-in' ?></div></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card bg-grad-orange"><i class="bi bi-clock-history icon"></i>
    <div><div class="num"><?= (int)$monthAtt[0]['late'] ?> mnt</div>
    <div class="lbl">Total terlambat<br><?= e(month_name($month)) ?> (<?= (int)$monthAtt[0]['present'] ?> hadir)</div></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card bg-grad-purple"><i class="bi bi-cash-stack icon"></i>
    <div><div class="num"><?= $lastSlip ? rupiah($lastSlip['net_pay']) : 'Rp 0' ?></div>
    <div class="lbl">Take home pay terakhir<br><?= $lastSlip ? e(month_name((int)$lastSlip['period_month']) . ' ' . $lastSlip['period_year']) : 'Belum ada slip' ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between"><span><i class="bi bi-stopwatch me-1"></i>Status Pengajuan Lembur</span>
        <a class="small" href="overtime.php">Ajukan &raquo;</a></div>
      <div class="card-body py-2">
        <?php foreach (['PENDING_MGR'=>'Menunggu Manager','PENDING_HR'=>'Menunggu HR','APPROVED'=>'Disetujui','REJECTED'=>'Ditolak'] as $k=>$lbl): ?>
          <div class="d-flex justify-content-between border-bottom py-2 small">
            <span><?= $lbl ?></span><span><?= status_badge($k) ?> <b class="ms-1"><?= (int)($otMap[$k] ?? 0) ?></b></span></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between"><span><i class="bi bi-umbrella me-1"></i>Status Pengajuan Cuti</span>
        <a class="small" href="leave.php">Ajukan &raquo;</a></div>
      <div class="card-body py-2">
        <?php foreach (['PENDING_MGR'=>'Menunggu Manager','PENDING_HR'=>'Menunggu HR','APPROVED'=>'Disetujui','REJECTED'=>'Ditolak'] as $k=>$lbl): ?>
          <div class="d-flex justify-content-between border-bottom py-2 small">
            <span><?= $lbl ?></span><span><?= status_badge($k) ?> <b class="ms-1"><?= (int)($lvMap[$k] ?? 0) ?></b></span></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-person-badge me-1"></i>Data Karyawan Saya</div>
      <div class="card-body small">
        <table class="table table-sm mb-0"><tbody>
        <tr><th style="width:38%">ID Karyawan</th><td><b><?= e($emp['employee_code']) ?></b></td></tr>
        <tr><th>Nama</th><td><?= e($emp['full_name']) ?></td></tr>
        <tr><th>Jabatan</th><td><?= e($emp['pos_name'] ?? '-') ?></td></tr>
        <tr><th>Departemen</th><td><?= e($emp['dept_name'] ?? '-') ?></td></tr>
        <tr><th>Branch</th><td><?= e($emp['branch_name'] ?? '-') ?></td></tr>
        <tr><th>Manager</th><td><?= e(val('SELECT full_name FROM employees WHERE id=?', [$emp['direct_manager_id']]) ?? '-') ?></td></tr>
        <tr><th>Status</th><td><?= e($emp['employment_status']) ?> · <?= status_badge($emp['flag']) ?></td></tr>
        </tbody></table>
        <a href="my_profile.php" class="btn btn-sm btn-outline-primary mt-2"><i class="bi bi-eye me-1"></i>Lihat Lengkap</a>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-calendar-week me-1"></i>Jadwal Kerja Aktif</div>
      <div class="card-body small">
        <?php if ($schedToday): ?>
          <div class="sched-day today mb-2">
            <b><?= e($schedToday['shift_name']) ?></b><br>
            <i class="bi bi-box-arrow-in-right"></i> <?= time_hm($schedToday['check_in_time']) ?> →
            <i class="bi bi-box-arrow-right"></i> <?= time_hm($schedToday['check_out_time']) ?><br>
            <i class="bi bi-coffee"></i> Istirahat: <?= time_hm($schedToday['break_time']) ?><br>
            📍 <?= e($schedToday['branch']) ?>
          </div>
          Periode: <?= e(date_id($schedToday['start_date'])) ?> – <?= e(date_id($schedToday['end_date'])) ?>
          <div class="mt-2"><a href="schedule.php" class="btn btn-sm btn-outline-primary me-1">Lihat Jadwal</a>
          <a href="schedule_requests.php" class="btn btn-sm btn-outline-warning">Ubah Jadwal</a></div>
        <?php else: ?>
          <div class="text-muted">Belum ada jadwal aktif. Hubungi HR.</div>
          <a href="schedule.php" class="btn btn-sm btn-outline-primary mt-2">Lihat Jadwal</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-wallet2 me-1"></i>Gaji, Benefit &amp; Slip</div>
      <div class="card-body small">
        <div class="mb-2">Gaji pokok: <b class="text-success"><?= rupiah($emp['base_salary']) ?></b></div>
        <?php $comps = all('SELECT pc.name, pc.type FROM employee_components ec JOIN payroll_components pc ON pc.id=ec.component_id WHERE ec.employee_id=? AND pc.is_active=1', [$emp['id']]);
        $ben = array_filter($comps, fn($c)=>$c['type']==='benefit');
        $ded = array_filter($comps, fn($c)=>$c['type']==='deduction'); ?>
        <div class="mb-1"><span class="badge bg-success-subtle text-success">Benefit (<?= count($ben) ?>)</span>
          <?= e(implode(', ', array_map(fn($c)=>$c['name'], $ben)) ?: '-') ?></div>
        <div class="mb-2"><span class="badge bg-danger-subtle text-danger">Deduction (<?= count($ded) ?>)</span>
          <?= e(implode(', ', array_map(fn($c)=>$c['name'], $ded)) ?: '-') ?></div>
        <?php if ($lastSlip): ?>
          <div class="alert alert-info py-2 mb-2 small">Slip terakhir: <b><?= e(month_name((int)$lastSlip['period_month'])) ?> <?= $lastSlip['period_year'] ?></b> — <?= status_badge($lastSlip['status']) ?></div>
        <?php endif; ?>
        <a href="myslips.php" class="btn btn-sm btn-primary"><i class="bi bi-filetext me-1"></i>Slip Gaji Saya</a>
      </div>
    </div>
  </div>
  <div class="col-12">
    <div class="card">
      <div class="card-header"><i class="bi bi-hourglass-split me-1"></i>Sisa Jatah Cuti <?= $year ?></div>
      <div class="card-body py-2">
        <div class="row g-2">
        <?php foreach ($quota as $q): ?>
          <div class="col-6 col-md-3"><div class="border rounded p-2 text-center small">
            <div class="text-muted"><?= e($q['name']) ?></div>
            <div class="fs-5 fw-bold text-primary"><?= (float)$q['quota_days'] - (float)$q['used_days'] ?></div>
            <div class="small text-muted">dari <?= (float)$q['quota_days'] ?> hari</div>
          </div></div>
        <?php endforeach; ?>
        <?php if (!$quota): ?><div class="text-muted small">Belum ada kuota cuti.</div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php elseif ($u['role'] === 'HR'): ?>
<!-- ================= DASHBOARD HR ================= -->
<?php
$totEmp = val('SELECT COUNT(*) FROM employees WHERE flag="active"');
$todayAtt = val('SELECT COUNT(*) FROM attendance_logs WHERE log_date=?', [$today]);
$lateToday = val('SELECT COALESCE(SUM(CASE WHEN late_minutes>0 THEN 1 ELSE 0 END),0) FROM attendance_logs WHERE log_date=?', [$today]);
$pending = [
  'Lembur' => val("SELECT COUNT(*) FROM overtime_requests WHERE status IN ('PENDING_MGR','PENDING_HR')"),
  'Cuti' => val("SELECT COUNT(*) FROM leave_requests WHERE status IN ('PENDING_MGR','PENDING_HR')"),
  'Ubah Jadwal' => val("SELECT COUNT(*) FROM schedule_requests WHERE status IN ('PENDING_MGR','PENDING_HR')"),
];
$resign = val('SELECT COUNT(*) FROM employees WHERE flag="resign"');
$contractSoon = all('SELECT e.full_name, e.employee_code, e.contract_end FROM employees e WHERE e.flag="active" AND e.contract_end IS NOT NULL AND e.contract_end <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) ORDER BY e.contract_end');
$openPeriods = all('SELECT pp.*, b.name branch FROM payroll_periods pp JOIN branches b ON b.id=pp.branch_id WHERE pp.status="OPEN" ORDER BY pp.period_year DESC, pp.period_month DESC');
?>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="stat-card bg-grad-blue"><i class="bi bi-people icon"></i><div><div class="num"><?= (int)$totEmp ?></div><div class="lbl">Karyawan Aktif</div></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card bg-grad-green"><i class="bi bi-geo-alt icon"></i><div><div class="num"><?= (int)$todayAtt ?></div><div class="lbl">Hadir Hari Ini</div></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card bg-grad-orange"><i class="bi bi-exclamation-triangle icon"></i><div><div class="num"><?= (int)$lateToday ?></div><div class="lbl">Terlambat Hari Ini</div></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card bg-grad-red"><i class="bi bi-inboxes icon"></i><div><div class="num"><?= array_sum($pending) ?></div><div class="lbl">Antrian Approval</div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-check2-square me-1"></i>Approval Tertunda</div>
      <div class="card-body">
        <?php foreach ($pending as $k=>$v): ?>
        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
          <span><?= e($k) ?></span>
          <span><span class="badge bg-<?= $v>0?'danger':'secondary' ?>"><?= (int)$v ?></span>
          <a class="small ms-2" href="approvals.php">Proses</a></span>
        </div><?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-file-earmark-x me-1"></i>Kontrak Akan Berakhir (60 hari)</div>
      <div class="card-body small">
        <?php if ($contractSoon): foreach ($contractSoon as $c): ?>
          <div class="d-flex justify-content-between border-bottom py-2">
            <span><b><?= e($c['full_name']) ?></b><br><span class="text-muted"><?= e($c['employee_code']) ?></span></span>
            <span class="text-danger fw-bold"><?= e(date_id($c['contract_end'])) ?></span>
          </div>
        <?php endforeach; else: ?><div class="text-muted">Tidak ada kontrak mendekati berakhir.</div><?php endif; ?>
        <div class="mt-2 text-muted-sm">Resign total: <b><?= (int)$resign ?></b> karyawan</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-cash-stack me-1"></i>Periode Payroll Open</div>
      <div class="card-body small">
        <?php if ($openPeriods): foreach ($openPeriods as $p): ?>
          <div class="d-flex justify-content-between border-bottom py-2">
            <span><?= e($p['branch']) ?></span>
            <span><?= e(month_name((int)$p['period_month'])) ?> <?= (int)$p['period_year'] ?> · <?= status_badge($p['status']) ?></span>
          </div>
        <?php endforeach; else: ?><div class="text-muted">Tidak ada periode open.</div><?php endif; ?>
        <a href="payroll_process.php" class="btn btn-sm btn-primary mt-2">Proses Payroll</a>
        <a href="reports.php" class="btn btn-sm btn-outline-secondary mt-2">Cetak Laporan</a>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ================= DASHBOARD FINANCE ================= -->
<?php
$sent = all('SELECT pp.*, b.name branch, (SELECT COUNT(*) FROM payslips ps WHERE ps.period_id=pp.id AND ps.status="SENT_FINANCE") n_sent,
              (SELECT SUM(net_pay) FROM payslips ps WHERE ps.period_id=pp.id AND ps.status="SENT_FINANCE") total_sent
            FROM payroll_periods pp JOIN branches b ON b.id=pp.branch_id
            WHERE pp.status IN ("SENT_FINANCE","LOCKED") ORDER BY pp.period_year DESC, pp.period_month DESC');
$totalPaidMonth = val('SELECT COALESCE(SUM(net_pay),0) FROM payslips WHERE status="PAID" AND MONTH(paid_at)=? AND YEAR(paid_at)=?', [$month, $year]);
$paidCount = val('SELECT COUNT(*) FROM payslips WHERE status="PAID"');
?>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-4"><div class="stat-card bg-grad-purple"><i class="bi bi-bank icon"></i><div><div class="num"><?= count($sent) ?></div><div class="lbl">Periode Menunggu Pembayaran</div></div></div></div>
  <div class="col-6 col-lg-4"><div class="stat-card bg-grad-green"><i class="bi bi-check-circle icon"></i><div><div class="num"><?= (int)$paidCount ?></div><div class="lbl">Slip Sudah PAID</div></div></div></div>
  <div class="col-12 col-lg-4"><div class="stat-card bg-grad-blue"><i class="bi bi-cash icon"></i><div><div class="num"><?= rupiah($totalPaidMonth) ?></div><div class="lbl">Total Dibayar <?= e(month_name($month)) ?></div></div></div></div>
</div>
<div class="card">
  <div class="card-header"><i class="bi bi-envelope-arrow-up me-1"></i>Data Payroll dari HR (siap dibayar)</div>
  <div class="card-body table-responsive">
    <table class="table table-hover align-middle">
      <thead><tr><th>Branch</th><th>Periode</th><th>Jml Slip</th><th>Total Take Home</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($sent as $p): ?>
        <tr>
          <td><?= e($p['branch']) ?></td>
          <td><?= e(month_name((int)$p['period_month'])) ?> <?= (int)$p['period_year'] ?></td>
          <td><?= (int)$p['n_sent'] ?></td>
          <td class="fw-bold text-end"><?= rupiah($p['total_sent']) ?></td>
          <td><?= status_badge($p['status']) ?></td>
          <td><a class="btn btn-sm btn-primary" href="finance_payroll.php?period=<?= (int)$p['id'] ?>">Proses Bayar</a></td>
        </tr>
      <?php endforeach; if (!$sent): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada data payroll yang dikirim HR.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
