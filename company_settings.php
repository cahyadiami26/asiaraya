<?php
/**
 * Informasi Perusahaan + upload Logo (HR).
 */
$page_title = 'Info Perusahaan'; $active_menu = 'company';
require_once __DIR__ . '/includes/header.php';
require_role('HR');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $logo = $_POST['current_logo'] ?? '';
    if (!empty($_FILES['logo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg']) && $_FILES['logo']['size'] < 2 * 1024 * 1024) {
            $dest = 'uploads/logo/logo_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['logo']['tmp_name'], BASE_PATH . '/' . $dest);
            $logo = $dest;
        } else flash('warning', 'Logo gagal diupload (max 2MB, png/jpg).');
    }
    exec_sql('UPDATE company_settings SET name=?,address=?,phone=?,email=?,website=?,tax_number=?,currency=?,timezone=?,logo_path=? WHERE id=1', [
        trim($_POST['name']), trim($_POST['address']), trim($_POST['phone']), trim($_POST['email']),
        trim($_POST['website']), trim($_POST['tax_number']), trim($_POST['currency']), trim($_POST['timezone']), $logo ?: null,
    ]);
    audit('UPDATE_COMPANY', 'Perubahan informasi perusahaan');
    flash('success', 'Informasi perusahaan disimpan.');
    redirect('company_settings.php');
}
$co = one('SELECT * FROM company_settings WHERE id=1');
?>
<h1 class="page-title mb-3"><i class="bi bi-buildings me-2"></i>Informasi Perusahaan</h1>
<div class="card" style="max-width:820px">
  <div class="card-body">
    <form method="post" enctype="multipart/form-data" class="row g-3">
      <?= csrf_field() ?>
      <div class="col-md-9">
        <label class="form-label small fw-bold">Nama Perusahaan</label>
        <input class="form-control" name="name" value="<?= e($co['name']) ?>" required>
      </div>
      <div class="col-md-3 text-center">
        <img id="logoPrev" src="<?= $co['logo_path'] ? e($co['logo_path']) : 'assets/img/placeholder-logo.png' ?>"
             style="width:80px;height:80px;object-fit:contain;border:1px solid #ddd;border-radius:12px;padding:6px;background:#fff">
        <input type="hidden" name="current_logo" value="<?= e($co['logo_path']) ?>">
      </div>
      <div class="col-md-3"><label class="form-label small fw-bold">Ganti Logo</label>
        <input type="file" name="logo" class="form-control form-control-sm" accept="image/png,image/jpeg" data-preview="#logoPrev"></div>
      <div class="col-md-9"><label class="form-label small fw-bold">Alamat</label>
        <textarea class="form-control" name="address" rows="2"><?= e($co['address']) ?></textarea></div>
      <div class="col-md-4"><label class="form-label small fw-bold">Telepon</label><input class="form-control" name="phone" value="<?= e($co['phone']) ?>"></div>
      <div class="col-md-4"><label class="form-label small fw-bold">Email</label><input type="email" class="form-control" name="email" value="<?= e($co['email']) ?>"></div>
      <div class="col-md-4"><label class="form-label small fw-bold">Website</label><input class="form-control" name="website" value="<?= e($co['website']) ?>"></div>
      <div class="col-md-4"><label class="form-label small fw-bold">NPWP</label><input class="form-control" name="tax_number" value="<?= e($co['tax_number']) ?>"></div>
      <div class="col-md-4"><label class="form-label small fw-bold">Mata Uang</label><input class="form-control" name="currency" value="<?= e($co['currency']) ?>"></div>
      <div class="col-md-4"><label class="form-label small fw-bold">Timezone</label><input class="form-control" name="timezone" value="<?= e($co['timezone']) ?>"></div>
      <div class="col-12"><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button></div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
