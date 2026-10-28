<?php
/**
 * Periode Payroll per Branch (HR): buat/buka periode, kunci, lihat status.
 */
$page_title = 'Periode Payroll'; $active_menu = 'periods';
require_once __DIR__ . '/includes/header.php';
require_role('HR');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = $_POST['act'] ?? '';

    if ($act === 'create') {
        $branch = (int)$_POST['branch_id'];
        $month  = (int)$_POST['period_month'];
        $year   = (int)$_POST['period_year'];
        if (!$branch || $month < 1 || $month > 12 || $year < 2000) {
            flash('error', 'Data periode tidak valid.'); redirect('payroll_periods.php');
        }
        try {
            exec_sql('INSERT INTO payroll_periods (branch_id, period_month, period_year, status) VALUES (?,?,?,\'OPEN\')',
                     [$branch, $month, $year]);
            audit('PERIOD_CREATE', "branch #$branch $month/$year");
            flash('success', 'Periode dibuat dengan status OPEN.');
        } catch (PDOException $e) {
            flash('error', 'Periode untuk branch & bulan ini sudah ada.');
        }
        redirect('payroll_periods.php');
    }

    if ($act === 'lock') {
        $id = (int)$_POST['id'];
        $per = one('SELECT * FROM payroll_periods WHERE id=?', [$id]);
        if (!$per) exit('Periode tidak ditemukan.');
        if ($per['status'] !== 'OPEN') { flash('error', 'Hanya periode OPEN yang bisa dikunci.'); redirect('payroll_periods.php'); }
        exec_sql('UPDATE payroll_periods SET status=\'LOCKED\' WHERE id=?', [$id]);
        exec_sql("UPDATE payslips SET status='FINAL' WHERE period_id=? AND status='DRAFT'", [$id]);
        audit('PERIOD_LOCK', "#$id");
        flash('success', 'Periode dikunci. Payslip menjadi FINAL — lanjut ke Proses Payroll untuk kirim ke Finance.');
        redirect('payroll_periods.php');
    }

    if ($act === 'unlock') {
        $id = (int)$_POST['id'];
        $cnt = (int)val("SELECT COUNT(*) FROM payslips WHERE period_id=? AND status IN ('SENT_FINANCE','PAID')", [$id]);
        if ($cnt > 0) { flash('error', 'Tidak bisa dibuka: sudah ada payslip terkirim/dibayar.'); }
        else {
            exec_sql("UPDATE payroll_periods SET status='OPEN' WHERE id=?", [$id]);
            exec_sql("UPDATE payslips SET status='DRAFT' WHERE period_id=? AND status='FINAL'", [$id]);
            audit('PERIOD_UNLOCK', "#$id");
            flash('success', 'Periode dibuka kembali.');
        }
        redirect('payroll_periods.php');
    }

    if ($act === 'delete') {
        $id = (int)$_POST['id'];
        $sent = (int)val("SELECT COUNT(*) FROM payslips WHERE period_id=? AND status IN ('SENT_FINANCE','PAID')", [$id]);
        if ($sent > 0) flash('error', 'Periode tidak dapat dihapus (sudah dikirim ke finance).');
        else { exec_sql('DELETE FROM payroll_periods WHERE id=?', [$id]); audit('PERIOD_DELETE', "#$id"); flash('success', 'Periode dihapus.'); }
        redirect('payroll_periods.php');
    }
}

$fYear = (int)($_GET['year'] ?? date('Y'));
$years = range((int)date('Y') - 2, (int)date('Y') + 1);
$periods = all("SELECT p.*, b.name branch_name, b.code branch_code,
                      (SELECT COUNT(*) FROM payslips ps WHERE ps.period_id=p.id) slip_count,
                      (SELECT COUNT(*) FROM payslips ps WHERE ps.period_id=p.id AND ps.status='PAID') paid_count,
                      (SELECT COALESCE(SUM(net_pay),0) FROM payslips ps WHERE ps.period_id=p.id) total_net
               FROM payroll_periods p JOIN branches b ON b.id=p.branch_id
               WHERE p.period_year=? ORDER BY p.period_month DESC, b.name", [$fYear]);
$branches = all('SELECT * FROM branches WHERE is_active=1 ORDER BY name');
?>
<h1 class="page-title mb-3"><i class="bi bi-calendar-x me-2"></i>Periode Payroll per Branch</h1>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle me-1"></i>Buat Periode Baru</div>
      <div class="card-body">
        <form method="post" class="row g-2">
          <?= csrf_field() ?><input type="hidden" name="act" value="create">
          <div class="col-12"><label class="form-label small">Branch *</label>
            <select class="form-select form-select-sm" name="branch_id" required>
              <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?> (<?= e($b['code']) ?>)</option><?php endforeach; ?>
            </select></div>
          <div class="col-6"><label class="form-label small">Bulan *</label>
            <select class="form-select form-select-sm" name="period_month" required>
              <?php for ($m = 1; $m <= 12; $m++): ?><option value="<?= $m ?>" <?= $m == (int)date('n') ? '' : '' ?>><?= e(month_name($m)) ?></option><?php endfor; ?>
            </select></div>
          <div class="col-6"><label class="form-label small">Tahun *</label>
            <select class="form-select form-select-sm" name="period_year" required>
              <?php foreach ($years as $y): ?><option <?= $y == (int)date('Y') ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-12 d-grid"><button class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Buat Periode</button></div>
        </form>
        <hr>
        <div class="small text-muted">
          <b>Alur status periode:</b><br>
          <span class="badge bg-secondary">OPEN</span> → hitung payslip →
          <span class="badge bg-info">LOCKED</span> → kirim ke finance →
          <span class="badge bg-primary">SENT_FINANCE</span> → dibayar →
          <span class="badge bg-success">PAID</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold">Daftar Periode</span>
        <form class="d-flex gap-2" method="get">
          <select class="form-select form-select-sm" name="year" onchange="this.form.submit()">
            <?php foreach ($years as $y): ?><option <?= $y == $fYear ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
          </select>
        </form>
      </div>
      <div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Periode</th><th>Branch</th><th>Payslip</th><th>Total Net</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($periods as $p): ?>
            <tr>
              <td class="fw-semibold"><?= e(month_name((int)$p['period_month'])) ?> <?= (int)$p['period_year'] ?></td>
              <td class="small"><?= e($p['branch_name']) ?> <span class="badge bg-light text-dark border"><?= e($p['branch_code']) ?></span></td>
              <td class="small"><?= (int)$p['slip_count'] ?> slip<br><span class="text-success"><?= (int)$p['paid_count'] ?> paid</span></td>
              <td class="small fw-semibold"><?= rupiah($p['total_net']) ?></td>
              <td><?= status_badge($p['status']) ?></td>
              <td class="text-end text-nowrap">
                <?php if ($p['status'] === 'OPEN'): ?>
                  <a class="btn btn-sm btn-outline-primary" href="payroll_process.php?period=<?= (int)$p['id'] ?>" title="Proses gaji"><i class="bi bi-cash-stack"></i></a>
                  <form method="post" class="d-inline"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="act" value="lock"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-sm btn-outline-info" title="Kunci periode"><i class="bi bi-lock"></i></button></form>
                  <form method="post" class="d-inline" onsubmit="return confirm('Hapus periode ini beserta draft payslipnya?')">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button></form>
                <?php elseif ($p['status'] === 'LOCKED'): ?>
                  <a class="btn btn-sm btn-primary" href="payroll_process.php?period=<?= (int)$p['id'] ?>"><i class="bi bi-send me-1"></i>Kirim Finance</a>
                  <form method="post" class="d-inline"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="act" value="unlock"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-sm btn-outline-secondary" title="Buka kembali"><i class="bi bi-unlock"></i></button></form>
                <?php else: ?>
                  <a class="btn btn-sm btn-outline-dark" href="payroll_process.php?period=<?= (int)$p['id'] ?>"><i class="bi bi-eye me-1"></i>Lihat</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; if (!$periods): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada periode pada tahun <?= $fYear ?>.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
