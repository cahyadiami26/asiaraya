<?php
/**
 * Pembayaran Payroll (Finance): terima data payroll dari HR, proses pembayaran
 * per karyawan / sekaligus, ubah status menjadi PAID, cetak laporan PDF.
 */
$page_title = 'Pembayaran Payroll'; $active_menu = 'finance';
require_once __DIR__ . '/includes/header.php';
require_role('FIN');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = $_POST['act'] ?? '';

    if ($act === 'pay_one') {
        $slip = one('SELECT * FROM payslips WHERE id=?', [(int)$_POST['slip_id']]);
        if (!$slip) exit('Payslip tidak ditemukan.');
        if ($slip['status'] === 'PAID') { flash('error', 'Payslip sudah dibayar.'); redirect('finance_payroll.php?period=' . $slip['period_id']); }
        if ($slip['status'] !== 'SENT_FINANCE') { flash('error', 'Payslip belum diserahkan HR ke Finance.'); redirect('finance_payroll.php'); }
        exec_sql("UPDATE payslips SET status='PAID', paid_at=NOW() WHERE id=?", [$slip['id']]);
        audit('PAYROLL_PAID_ONE', "slip #{$slip['id']} net {$slip['net_pay']}");
        flash('success', 'Pembayaran tercatat untuk payslip #' . $slip['id'] . '.');
        redirect('finance_payroll.php?period=' . $slip['period_id']);
    }

    if ($act === 'pay_all') {
        $pid = (int)$_POST['period_id'];
        $per = one('SELECT * FROM payroll_periods WHERE id=?', [$pid]);
        if (!$per || $per['status'] !== 'SENT_FINANCE') exit('Periode tidak valid untuk pembayaran.');
        db()->beginTransaction();
        try {
            exec_sql("UPDATE payslips SET status='PAID', paid_at=NOW() WHERE period_id=? AND status='SENT_FINANCE'", [$pid]);
            exec_sql("UPDATE payroll_periods SET status='PAID', pay_date=CURDATE() WHERE id=?", [$pid]);
            db()->commit();
        } catch (Throwable $t) { db()->rollBack(); throw $t; }
        audit('PAYROLL_PAID_ALL', "periode #$pid");
        flash('success', 'Semua payslip pada periode ditandai PAID. Laporan dapat dicetak.');
        redirect('finance_payroll.php?period=' . $pid);
    }
}

/* Periode yang sudah dikirim ke finance + riwayat */
$periods = all("SELECT p.*, b.name branch_name,
                      (SELECT COUNT(*) FROM payslips ps WHERE ps.period_id=p.id AND ps.status='SENT_FINANCE') pending_count,
                      (SELECT COUNT(*) FROM payslips ps WHERE ps.period_id=p.id AND ps.status='PAID') paid_count,
                      (SELECT COALESCE(SUM(net_pay),0) FROM payslips ps WHERE ps.period_id=p.id AND ps.status='SENT_FINANCE') total_pending,
                      (SELECT COALESCE(SUM(net_pay),0) FROM payslips ps WHERE ps.period_id=p.id AND ps.status='PAID') total_paid
               FROM payroll_periods p JOIN branches b ON b.id=p.branch_id
               WHERE p.status IN ('SENT_FINANCE','PAID')
               ORDER BY FIELD(p.status,'SENT_FINANCE','PAID'), p.period_year DESC, p.period_month DESC, b.name");
$selId = (int)($_GET['period'] ?? ($periods[0]['id'] ?? 0));
$per = $selId ? one('SELECT p.*, b.name branch_name FROM payroll_periods p JOIN branches b ON b.id=p.branch_id WHERE p.id=?', [$selId]) : null;
$slips = $per ? all("SELECT ps.*, e.full_name, e.employee_code, d.name dept_name
                     FROM payslips ps JOIN employees e ON e.id=ps.employee_id
                     LEFT JOIN departments d ON d.id=e.department_id
                     WHERE ps.period_id=? ORDER BY CASE ps.status WHEN 'SENT_FINANCE' THEN 0 ELSE 1 END, d.name, e.full_name",
                    [$per['id']]) : [];
$pending = array_filter($slips, fn($s) => $s['status'] === 'SENT_FINANCE');
?>
<h1 class="page-title mb-3"><i class="bi bi-bank me-2"></i>Pembayaran Payroll</h1>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card h-100"><div class="card-body">
      <div class="kpi-label">Antrean Menunggu Bayar</div>
      <div class="kpi-value text-warning"><?= count(all("SELECT ps.id FROM payslips ps JOIN payroll_periods p ON p.id=ps.period_id WHERE ps.status='SENT_FINANCE'")) ?></div>
      <div class="small text-muted">payslip berstatus SENT_FINANCE</div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card h-100"><div class="card-body">
      <div class="kpi-label">Total Dibayar Bulan Ini</div>
      <div class="kpi-value text-success"><?= rupiah(val("SELECT COALESCE(SUM(net_pay),0) FROM payslips WHERE status='PAID' AND MONTH(paid_at)=? AND YEAR(paid_at)=?", [(int)date('n'), (int)date('Y')])) ?></div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card h-100"><div class="card-body">
      <div class="kpi-label">Status Keuangan Sistem</div>
      <div class="fs-5 mt-1">
        <?php $totAll = (float)val("SELECT COALESCE(SUM(net_pay),0) FROM payslips WHERE status IN ('SENT_FINANCE','PAID')") ?: 1;
              $paidAll = (float)val("SELECT COALESCE(SUM(net_pay),0) FROM payslips WHERE status='PAID'"); ?>
        <span class="badge bg-success"><?= round($paidAll / $totAll * 100) ?>% PAID</span>
        <span class="badge bg-warning text-dark"><?= round(($totAll - $paidAll) / $totAll * 100) ?>% PENDING</span>
      </div>
    </div></div>
  </div>
</div>

<div class="card mb-3"><div class="card-body py-2">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-6">
      <label class="form-label small mb-0">Pilih Periode (dari HR)</label>
      <select class="form-select form-select-sm" name="period" onchange="this.form.submit()">
        <?php if (!$periods): ?><option value="">— belum ada kiriman HR —</option><?php endif; ?>
        <?php foreach ($periods as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= $selId == $p['id'] ? 'selected' : '' ?>>
            <?= e($p['branch_name']) ?> — <?= e(month_name((int)$p['period_month'])) ?> <?= (int)$p['period_year'] ?>
            (<?= $p['status'] === 'SENT_FINANCE' ? $p['pending_count'] . ' menunggu' : 'lunas ' . $p['paid_count'] . ' slip' ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($per): ?>
    <div class="col-md-6 text-md-end">
      <?= status_badge($per['status']) ?>
      <a class="btn btn-sm btn-outline-dark" href="pdf_payroll.php?type=laporan&period=<?= (int)$per['id'] ?>"><i class="bi bi-printer me-1"></i>Cetak Laporan</a>
    </div>
    <?php endif; ?>
  </form>
</div></div>

<?php if (!$per): ?>
  <div class="alert alert-secondary"><i class="bi bi-info-circle me-1"></i>
    Belum ada data payroll yang dikirim HR. Data akan muncul di sini setelah HR menekan <b>Teruskan ke Finance</b>.</div>
<?php else: ?>
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
  <?php if ($per['status'] === 'SENT_FINANCE'): ?>
    <form method="post" onsubmit="return confirm('Bayar semua payslip pada periode ini (<?= money(array_sum(array_column($pending,'net_pay'))) ?>)?')">
      <?= csrf_field() ?><input type="hidden" name="act" value="pay_all"><input type="hidden" name="period_id" value="<?= (int)$per['id'] ?>">
      <button class="btn btn-success btn-sm" <?= $pending ? '' : 'disabled' ?>><i class="bi bi-cash-coin me-1"></i>Bayar Semua (<?= count($pending) ?> slip)</button>
    </form>
    <span class="small text-muted">Total kewajiban: <b><?= rupiah(array_sum(array_column($pending, 'net_pay'))) ?></b></span>
  <?php else: ?>
    <span class="badge bg-success">Periode lunas — dibayar <?= e(date_id($per['pay_date'])) ?></span>
  <?php endif; ?>
  <span class="ms-auto small text-muted">Branch: <b><?= e($per['branch_name']) ?></b> · <?= e(month_name((int)$per['period_month'])) ?> <?= (int)$per['period_year'] ?></span>
</div></div>

<div class="card"><div class="card-body table-responsive">
  <input class="form-control form-control-sm mb-2 no-print" placeholder="Cari..." data-table-filter="#tblPay" style="max-width:280px">
  <table class="table table-hover align-middle mb-0" id="tblPay">
    <thead class="small"><tr>
      <th>Karyawan</th><th>Departemen</th><th class="text-end">Bruto</th><th class="text-end">Potongan</th>
      <th class="text-end">Net</th><th>Status Bayar</th><th>Tgl Bayar</th><th class="text-end">Aksi</th>
    </tr></thead>
    <tbody>
    <?php foreach ($slips as $s): ?>
      <tr>
        <td><b><?= e($s['full_name']) ?></b><br><span class="small text-muted"><?= e($s['employee_code']) ?></span></td>
        <td class="small"><?= e($s['dept_name'] ?? '-') ?></td>
        <td class="text-end small"><?= money($s['gross_pay']) ?></td>
        <td class="text-end small text-danger">−<?= money($s['total_deduction']) ?></td>
        <td class="text-end fw-bold"><?= rupiah($s['net_pay']) ?></td>
        <td><?= status_badge($s['status']) ?></td>
        <td class="small"><?= $s['paid_at'] ? date_id($s['paid_at']) : '-' ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-primary" title="Slip PDF" href="pdf_payroll.php?type=slip&slip=<?= (int)$s['id'] ?>"><i class="bi bi-file-earmark-pdf"></i></a>
          <?php if ($s['status'] === 'SENT_FINANCE'): ?>
          <form method="post" class="d-inline" onsubmit="return confirm('Tandai <?= e($s['full_name']) ?> sebagai PAID (<?= rupiah($s['net_pay']) ?>)?')">
            <?= csrf_field() ?><input type="hidden" name="act" value="pay_one"><input type="hidden" name="slip_id" value="<?= (int)$s['id'] ?>">
            <button class="btn btn-sm btn-success"><i class="bi bi-check2-circle me-1"></i>Bayar</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; if (!$slips): ?>
      <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada payslip pada periode ini.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div></div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
