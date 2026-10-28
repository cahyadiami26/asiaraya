<?php
/**
 * Slip gaji saya (semua role dengan employee): daftar payslip yang sudah terbit
 * (FINAL/SENT_FINANCE/PAID) + tombol cetak PDF.
 */
$page_title = 'Slip Gaji Saya'; $active_menu = 'myslips';
require_once __DIR__ . '/includes/header.php';

$eid = employee_id();
if (!$eid) {
    echo '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>Akun Anda belum tertaut data karyawan.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$slips = all("SELECT ps.*, p.period_month, p.period_year, b.name branch_name
              FROM payslips ps
              JOIN payroll_periods p ON p.id=ps.period_id
              JOIN branches b ON b.id=p.branch_id
              WHERE ps.employee_id=? AND ps.status IN ('FINAL','SENT_FINANCE','PAID')
              ORDER BY p.period_year DESC, p.period_month DESC", [$eid]);
?>
<h1 class="page-title mb-3"><i class="bi bi-filetext me-2"></i>Slip Gaji Saya</h1>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-body">
      <div class="kpi-label">Total Slip Terbit</div>
      <div class="kpi-value"><?= count($slips) ?></div>
      <hr>
      <?php $paid = array_filter($slips, fn($s) => $s['status'] === 'PAID'); ?>
      <div class="small text-muted">Sudah dibayar: <b><?= count($paid) ?></b> periode ·
        Menunggu bayar: <b><?= count($slips) - count($paid) ?></b></div>
      <div class="alert alert-light border small mt-3 mb-0">
        <i class="bi bi-info-circle me-1"></i>Slip hanya dapat dilihat setelah HR memfinalisasi periode payroll.
        Status <span class="badge bg-success">Paid</span> berarti gaji telah ditransfer Finance.
      </div>
    </div></div>
  </div>

  <div class="col-lg-8">
    <?php if (!$slips): ?>
      <div class="alert alert-secondary"><i class="bi bi-inbox me-1"></i>Belum ada slip gaji yang terbit untuk Anda.</div>
    <?php else: ?>
    <div class="row g-3">
      <?php foreach ($slips as $s): ?>
        <div class="col-md-6">
          <div class="card h-100 slip-card">
            <div class="card-body d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-bold"><?= e(month_name((int)$s['period_month'])) ?> <?= (int)$s['period_year'] ?></div>
                  <div class="small text-muted"><?= e($s['branch_name']) ?></div>
                </div>
                <?= status_badge($s['status']) ?>
              </div>
              <div class="my-3">
                <div class="small text-muted">Take Home Pay</div>
                <div class="fs-4 fw-bold text-primary"><?= rupiah($s['net_pay']) ?></div>
              </div>
              <div class="small text-muted mb-2">
                Bruto <?= money($s['gross_pay']) ?> − Potongan <?= money($s['total_deduction']) ?>
                <?php if ($s['paid_at']): ?><br>Dibayar: <?= e(date_id($s['paid_at'])) ?><?php endif; ?>
              </div>
              <a class="btn btn-sm btn-outline-dark mt-auto" href="pdf_payroll.php?type=slip&slip=<?= (int)$s['id'] ?>">
                <i class="bi bi-file-earmark-pdf me-1"></i>Unduh / Cetak Slip</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
