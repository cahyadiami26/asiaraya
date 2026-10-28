<?php
/**
 * Data Saya (read-only untuk karyawan).
 */
$page_title = 'Data Karyawan Saya'; $active_menu = 'profile';
require_once __DIR__ . '/includes/header.php';

$eid = employee_id();
if (!$eid) { echo '<div class="alert alert-warning">Akun Anda belum terhubung dengan data karyawan.</div>'; require __DIR__.'/includes/footer.php'; exit; }

$emp = one('SELECT e.*, b.name branch_name, b.code branch_code, d.name dept_name, p.name pos_name, m.full_name mgr_name, m.employee_code mgr_code
            FROM employees e
            LEFT JOIN branches b ON b.id=e.branch_id LEFT JOIN departments d ON d.id=e.department_id
            LEFT JOIN positions p ON p.id=e.position_id LEFT JOIN employees m ON m.id=e.direct_manager_id
            WHERE e.id=?', [$eid]);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="page-title"><i class="bi bi-person-vcard me-2"></i>Data Karyawan Saya</h1>
  <a class="btn btn-outline-primary" href="pdf_employee.php?id=<?= (int)$eid ?>"><i class="bi bi-file-earmark-pdf me-1"></i>Cetak PDF</a>
</div>

<div class="row g-3">
  <div class="col-md-3">
    <div class="card text-center p-4">
      <?php if (!empty($emp['photo_path'])): ?>
        <img src="<?= e($emp['photo_path']) ?>" class="rounded-circle mx-auto mb-3" style="width:110px;height:110px;object-fit:cover;border:4px solid var(--primary)">
      <?php else: ?>
        <div class="avatar-circle mx-auto mb-3" style="width:110px;height:110px;font-size:2.5rem;background:var(--primary);color:#fff"><?= e(strtoupper(substr($emp['full_name'],0,1))) ?></div>
      <?php endif; ?>
      <h5 class="mb-0 fw-bold"><?= e($emp['full_name']) ?></h5>
      <div class="text-muted-sm"><?= e($emp['pos_name'] ?? '-') ?></div>
      <div class="mt-2"><span class="badge bg-dark"><?= e($emp['employee_code']) ?></span></div>
      <div class="mt-2"><?= status_badge($emp['flag']) ?> <span class="badge bg-info text-dark"><?= e($emp['employment_status']) ?></span></div>
    </div>
  </div>
  <div class="col-md-9">
    <div class="card">
      <div class="card-header"><i class="bi bi-info-circle me-1"></i>Informasi Pribadi</div>
      <div class="card-body">
        <div class="row g-3 small">
          <div class="col-md-6"><label class="text-muted mb-0">Alamat</label><div class="fw-semibold"><?= e($emp['address'] ?? '-') ?></div></div>
          <div class="col-md-3"><label class="text-muted mb-0">No. HP</label><div class="fw-semibold"><?= e($emp['phone'] ?? '-') ?></div></div>
          <div class="col-md-3"><label class="text-muted mb-0">Email</label><div class="fw-semibold"><?= e($emp['email'] ?? '-') ?></div></div>
          <div class="col-md-3"><label class="text-muted mb-0">Direct Manager</label><div class="fw-semibold"><?= e($emp['mgr_name'] ?? '-') ?> (<?= e($emp['mgr_code'] ?? '-') ?>)</div></div>
          <div class="col-md-3"><label class="text-muted mb-0">Branch</label><div class="fw-semibold"><?= e($emp['branch_name'] ?? '-') ?></div></div>
          <div class="col-md-3"><label class="text-muted mb-0">Departemen</label><div class="fw-semibold"><?= e($emp['dept_name'] ?? '-') ?></div></div>
          <div class="col-md-3"><label class="text-muted mb-0">Posisi</label><div class="fw-semibold"><?= e($emp['pos_name'] ?? '-') ?></div></div>
          <div class="col-md-3"><label class="text-muted mb-0">Gaji Pokok</label><div class="fw-bold text-success"><?= rupiah($emp['base_salary']) ?></div></div>
          <div class="col-md-3"><label class="text-muted mb-0">Awal Kontrak</label><div class="fw-semibold"><?= e(date_id($emp['contract_start'])) ?></div></div>
          <div class="col-md-3"><label class="text-muted mb-0">Akhir Kontrak</label><div class="fw-semibold"><?= e(date_id($emp['contract_end'])) ?></div></div>
          <div class="col-md-3"><label class="text-muted mb-0">Tanggal Masuk</label><div class="fw-semibold"><?= e(date_id($emp['hire_date'])) ?></div></div>
        </div>
        <hr>
        <div class="small text-muted"><i class="bi bi-lock me-1"></i>Data bersifat read-only. Hubungi HR untuk perbaikan data.</div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
