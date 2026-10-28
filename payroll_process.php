<?php
/**
 * Proses Payroll (HR): hitung gaji per periode (gaji pokok, rekap kehadiran,
 * keterlambatan, lembur, cuti, komponen benefit/deduction, PPh & BPJS),
 * tampilkan rekap per branch/departemen -> cetak PDF, terbitkan slip gaji,
 * lalu kirim ke Finance.
 */
$page_title = 'Proses Payroll'; $active_menu = 'process';
require_once __DIR__ . '/includes/header.php';
require_role('HR');
require_once __DIR__ . '/includes/payroll_calc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act  = $_POST['act'] ?? '';
    $pid  = (int)$_POST['period_id'];
    $per  = one('SELECT * FROM payroll_periods WHERE id=?', [$pid]);
    if (!$per) exit('Periode tidak ditemukan.');

    /* Hitung ulang / generate draft payslip seluruh karyawan aktif branch tsb */
    if ($act === 'calculate' && $per['status'] === 'OPEN') {
        $emps = all("SELECT * FROM employees WHERE flag='active' AND branch_id=? ORDER BY department_id, full_name", [$per['branch_id']]);
        $n = 0;
        foreach ($emps as $emp) { save_payslip($pid, compute_payslip($emp, $per)); $n++; }
        audit('PAYROLL_CALC', "periode #$pid ($n karyawan)");
        flash('success', "Perhitungan selesai: $n payslip (draft) untuk " . month_name((int)$per['period_month']) . ' ' . $per['period_year'] . '.');
        redirect('payroll_process.php?period=' . $pid);
    }

    /* Kunci + finalisasi */
    if ($act === 'finalize' && $per['status'] === 'OPEN') {
        $cnt = (int)val('SELECT COUNT(*) FROM payslips WHERE period_id=?', [$pid]);
        if ($cnt === 0) { flash('error', 'Belum ada payslip — jalankan perhitungan terlebih dahulu.'); redirect('payroll_process.php?period=' . $pid); }
        exec_sql("UPDATE payroll_periods SET status='LOCKED' WHERE id=?", [$pid]);
        exec_sql("UPDATE payslips SET status='FINAL' WHERE period_id=? AND status='DRAFT'", [$pid]);
        audit('PAYROLL_FINALIZE', "periode #$pid");
        flash('success', 'Payslip difinalisasi & periode dikunci.');
        redirect('payroll_process.php?period=' . $pid);
    }

    /* Kirim ke Finance */
    if ($act === 'send_finance' && $per['status'] === 'LOCKED') {
        exec_sql("UPDATE payroll_periods SET status='SENT_FINANCE' WHERE id=?", [$pid]);
        exec_sql("UPDATE payslips SET status='SENT_FINANCE' WHERE period_id=? AND status='FINAL'", [$pid]);
        audit('PAYROLL_SEND_FIN', "periode #$pid");
        flash('success', 'Data payroll dikirim ke Finance untuk pembayaran.');
        redirect('payroll_process.php?period=' . $pid);
    }

    /* Hapus draft payslip seorang karyawan pada periode (sebelum finalize) */
    if ($act === 'delete_slip' && $per['status'] === 'OPEN') {
        exec_sql("DELETE FROM payslips WHERE period_id=? AND employee_id=? AND status='DRAFT'", [$pid, (int)$_POST['employee_id']]);
        redirect('payroll_process.php?period=' . $pid);
    }
}

/* ---------- Tampilan ---------- */
$periods = all("SELECT p.*, b.name branch_name FROM payroll_periods p JOIN branches b ON b.id=p.branch_id
                ORDER BY p.period_year DESC, p.period_month DESC, b.name");
$selId = (int)($_GET['period'] ?? ($periods[0]['id'] ?? 0));
$per = $selId ? one("SELECT p.*, b.name branch_name FROM payroll_periods p JOIN branches b ON b.id=p.branch_id WHERE p.id=?", [$selId]) : null;
$fDept = (int)($_GET['dept'] ?? 0);
?>
<h1 class="page-title mb-3"><i class="bi bi-cash-stack me-2"></i>Proses Payroll</h1>

<div class="card mb-3"><div class="card-body py-2">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-5">
      <label class="form-label small mb-0">Pilih Periode (Branch · Bulan)</label>
      <select class="form-select form-select-sm" name="period" onchange="this.form.submit()">
        <?php foreach ($periods as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= $selId == $p['id'] ? 'selected' : '' ?>>
            <?= e($p['branch_name']) ?> — <?= e(month_name((int)$p['period_month'])) ?> <?= (int)$p['period_year'] ?> (<?= e($p['status']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($per): ?>
    <div class="col-md-4">
      <label class="form-label small mb-0">Filter Departemen</label>
      <select class="form-select form-select-sm" name="dept" onchange="this.form.submit()">
        <option value="0">Semua Departemen</option>
        <?php foreach (all('SELECT * FROM departments WHERE is_active=1 ORDER BY name') as $d): ?>
          <option value="<?= (int)$d['id'] ?>" <?= $fDept == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 text-md-end">
      <?= status_badge($per['status']) ?>
      <a class="btn btn-sm btn-outline-dark" href="pdf_payroll.php?type=rekap&period=<?= (int)$per['id'] ?>&dept=<?= $fDept ?>">
        <i class="bi bi-file-earmark-pdf"></i> Cetak Rekap</a>
    </div>
    <?php endif; ?>
  </form>
</div></div>

<?php if (!$per): ?>
  <div class="alert alert-secondary">Belum ada periode. Buat terlebih dahulu di <a href="payroll_periods.php">Periode Payroll</a>.</div>
<?php else:
  $depts = all("SELECT DISTINCT d.id, d.name FROM payslips ps
                JOIN employees e ON e.id=ps.employee_id JOIN departments d ON d.id=e.department_id
                WHERE ps.period_id=? ORDER BY d.name", [$per['id']]);
  $slips = all("SELECT ps.*, e.full_name, e.employee_code, e.base_salary, d.name dept_name, p.name pos_name
               FROM payslips ps
               JOIN employees e ON e.id=ps.employee_id
               LEFT JOIN departments d ON d.id=e.department_id
               LEFT JOIN positions p ON p.id=e.position_id
               WHERE ps.period_id=?" . ($fDept ? ' AND e.department_id=' . $fDept : '') .
               " ORDER BY d.name, e.full_name", [$per['id']]);
  $totNet = array_sum(array_column($slips, 'net_pay'));
  $totGross = array_sum(array_column($slips, 'gross_pay'));
  $totDed = array_sum(array_column($slips, 'total_deduction'));
?>

<div class="row g-3 mb-1">
  <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
    <div class="kpi-label">Karyawan</div><div class="kpi-value"><?= count($slips) ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
    <div class="kpi-label">Total Bruto</div><div class="kpi-value text-primary fs-5"><?= rupiah($totGross) ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
    <div class="kpi-label">Total Potongan</div><div class="kpi-value text-danger fs-5"><?= rupiah($totDed) ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
    <div class="kpi-label">Total Net</div><div class="kpi-value text-success fs-5"><?= rupiah($totNet) ?></div></div></div></div>
</div>

<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
  <?php if ($per['status'] === 'OPEN'): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="calculate"><input type="hidden" name="period_id" value="<?= (int)$per['id'] ?>">
      <button class="btn btn-primary btn-sm"><i class="bi bi-calculator me-1"></i>Hitung / Hitung Ulang Gaji</button></form>
    <form method="post" onsubmit="return confirm('Finalisasi & kunci periode? Payslip tidak dapat diubah lagi.')">
      <?= csrf_field() ?><input type="hidden" name="act" value="finalize"><input type="hidden" name="period_id" value="<?= (int)$per['id'] ?>">
      <button class="btn btn-info btn-sm text-white" <?= count($slips) ? '' : 'disabled' ?>><i class="bi bi-lock me-1"></i>Finalize & Lock</button></form>
  <?php elseif ($per['status'] === 'LOCKED'): ?>
    <form method="post" onsubmit="return confirm('Kirim data payroll ini ke Finance?')">
      <?= csrf_field() ?><input type="hidden" name="act" value="send_finance"><input type="hidden" name="period_id" value="<?= (int)$per['id'] ?>">
      <button class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i>Teruskan ke Finance</button></form>
    <span class="small text-muted">Periode terkunci — hasil perhitungan siap diteruskan.</span>
  <?php elseif ($per['status'] === 'SENT_FINANCE'): ?>
    <span class="badge bg-primary">Menunggu pembayaran Finance</span>
    <a class="btn btn-sm btn-outline-secondary" href="finance_payroll.php">Lihat antrean Finance</a>
  <?php else: ?>
    <span class="badge bg-success">Sudah dibayar (PAID)</span>
  <?php endif; ?>
  <span class="ms-auto small text-muted">Periode: <b><?= e($per['branch_name']) ?></b> · <?= e(month_name((int)$per['period_month'])) ?> <?= (int)$per['period_year'] ?></span>
</div></div>

<div class="card"><div class="card-body table-responsive">
  <input class="form-control form-control-sm mb-2 no-print" placeholder="Cari karyawan..." data-table-filter="#tblSlips" style="max-width:280px">
  <table class="table table-hover align-middle mb-0" id="tblSlips">
    <thead class="small">
      <tr>
        <th>Karyawan</th><th>Hadir/Telat/Alpa</th><th>Lembur</th><th>Cuti</th>
        <th class="text-end">Bruto</th><th class="text-end">Potongan</th><th class="text-end">Net</th>
        <th>Status</th><th class="text-end">Aksi</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($slips as $s): ?>
      <tr>
        <td><b><?= e($s['full_name']) ?></b><br><span class="small text-muted"><?= e($s['employee_code']) ?> · <?= e($s['dept_name'] ?? '-') ?></span></td>
        <td class="small">
          <span class="text-success"><?= (int)$s['present_days'] ?> hari</span> /
          <span class="text-warning"><?= (int)$s['late_minutes'] ?> mnt</span> /
          <span class="text-danger"><?= (int)$s['absent_days'] ?> alpa</span>
          <div class="text-muted" style="font-size:.75rem">pot telat <?= money($s['late_deduction']) ?> · alpa <?= money($s['absence_deduction']) ?></div>
        </td>
        <td class="small"><?= number_format((float)$s['overtime_hours'], 1) ?> jam<br><span class="text-success"><?= money($s['overtime_amount']) ?></span></td>
        <td class="small">gaji <?= (float)$s['leave_days_used'] ?: '-' ?><br>unpaid <?= (float)$s['unpaid_leave_days'] ?: '-' ?></td>
        <td class="text-end small">
          <?= money($s['gross_pay']) ?>
          <div class="text-muted" style="font-size:.75rem">pokok <?= money($s['basic_salary']) ?> + benef <?= money($s['benefits_total']) ?></div>
        </td>
        <td class="text-end small text-danger">
          −<?= money($s['total_deduction']) ?>
          <div style="font-size:.75rem">PPh <?= money($s['pph_amount']) ?> · BPJS <?= money((float)$s['bpjs_kes_employee'] + (float)$s['bpjs_jht_employee'] + (float)$s['bpjs_jp_employee']) ?> · komp <?= money($s['deductions_total']) ?></div>
        </td>
        <td class="text-end fw-bold text-success"><?= money($s['net_pay']) ?></td>
        <td><?= status_badge($s['status']) ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-primary" title="Slip PDF" href="pdf_payroll.php?type=slip&slip=<?= (int)$s['id'] ?>"><i class="bi bi-file-earmark-pdf"></i></a>
          <?php if ($per['status'] === 'OPEN'): ?>
          <form method="post" class="d-inline" onsubmit="return confirm('Hapus draft payslip ini?')">
            <?= csrf_field() ?><input type="hidden" name="act" value="delete_slip">
            <input type="hidden" name="period_id" value="<?= (int)$per['id'] ?>"><input type="hidden" name="employee_id" value="<?= (int)$s['employee_id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; if (!$slips): ?>
      <tr><td colspan="9" class="text-center text-muted py-4">
        Belum ada payslip. Klik <b>Hitung / Hitung Ulang Gaji</b> untuk membuat draft.</td></tr>
    <?php endif; ?>
    </tbody>
    <?php if ($slips): ?>
    <tfoot class="table-light fw-semibold small"><tr>
      <td colspan="4">TOTAL <?= count($slips) ?> KARYAWAN</td>
      <td class="text-end"><?= money($totGross) ?></td>
      <td class="text-end text-danger">−<?= money($totDed) ?></td>
      <td class="text-end text-success"><?= money($totNet) ?></td><td colspan="2"></td>
    </tr></tfoot>
    <?php endif; ?>
  </table>
</div></div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
