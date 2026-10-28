<?php
/**
 * Setting Payroll (HR): PPh (enable, tarif, PTKP) & BPJS Kesehatan/Ketenagakerjaan
 * (persentase perusahaan/karyawan, plafon upah dasar).
 */
$page_title = 'Setting PPH & BPJS'; $active_menu = 'payroll_setting';
require_once __DIR__ . '/includes/header.php';
require_role('HR');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    exec_sql('UPDATE payroll_settings SET pph_enabled=?, pph_rate_percent=?, pthp_monthly=?, bpjs_enabled=?,
              bpjs_kesehatan_company_percent=?, bpjs_kesehatan_employee_percent=?, bpjs_kesehatan_max_base=?,
              bpjs_jht_company_percent=?, bpjs_jht_employee_percent=?,
              bpjs_jp_company_percent=?, bpjs_jp_employee_percent=?, bpjs_jp_max_base=? WHERE id=1', [
        isset($_POST['pph_enabled']) ? 1 : 0,
        (float)$_POST['pph_rate_percent'], (float)$_POST['pthp_monthly'],
        isset($_POST['bpjs_enabled']) ? 1 : 0,
        (float)$_POST['bpjs_kesehatan_company_percent'], (float)$_POST['bpjs_kesehatan_employee_percent'],
        (float)$_POST['bpjs_kesehatan_max_base'],
        (float)$_POST['bpjs_jht_company_percent'], (float)$_POST['bpjs_jht_employee_percent'],
        (float)$_POST['bpjs_jp_company_percent'], (float)$_POST['bpjs_jp_employee_percent'],
        (float)$_POST['bpjs_jp_max_base'],
    ]);
    audit('PAYROLL_SETTINGS', 'Perubahan setting PPH & BPJS');
    flash('success', 'Setting payroll disimpan.');
    redirect('payroll_settings.php');
}

$s = payroll_settings();
if (!$s) { exec_sql('INSERT INTO payroll_settings (id) VALUES (1)'); $s = payroll_settings(); }
?>
<h1 class="page-title mb-3"><i class="bi bi-percent me-2"></i>Setting PPh & BPJS</h1>

<form method="post">
<?= csrf_field() ?>
<div class="row g-3">
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-cash-coin me-1 text-primary"></i>PPh 21 (Pajak Penghasilan)</span>
        <div class="form-check form-switch m-0">
          <input class="form-check-input" type="checkbox" name="pph_enabled" id="pphEn" <?= !empty($s['pph_enabled']) ? 'checked' : '' ?>>
        </div>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label small">Tarif PPh (%) <span class="text-muted">— pemotongan atas (bruto − PTKP)</span></label>
          <div class="input-group input-group-sm">
            <input class="form-control" type="number" step="0.5" min="0" max="35" name="pph_rate_percent" value="<?= e($s['pph_rate_percent']) ?>">
            <span class="input-group-text">%</span>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small">PTKP Bulanan (Penghasilan Tidak Kena Pajak)</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text">Rp</span>
            <input class="form-control" type="number" step="50000" min="0" name="pthp_monthly" value="<?= e($s['pthp_monthly']) ?>">
          </div>
          <div class="form-text">Contoh: TK/1 ≈ Rp 4.500.000/bulan.</div>
        </div>
        <div class="alert alert-light border small mb-0">
          <i class="bi bi-calculator me-1"></i>Rumus sistem: <b>PPh = (Gaji pokok + benefit objek PPh + lembur − PTKP) × tarif%</b>.
          Komponen ditandai "Objek PPh" pada menu Komponen Gaji.
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-heart-pulse me-1 text-danger"></i>BPJS</span>
        <div class="form-check form-switch m-0">
          <input class="form-check-input" type="checkbox" name="bpjs_enabled" id="bpjsEn" <?= !empty($s['bpjs_enabled']) ? 'checked' : '' ?>>
        </div>
      </div>
      <div class="card-body">
        <h6 class="small fw-bold text-uppercase text-muted">BPJS Kesehatan</h6>
        <div class="row g-2 mb-3">
          <div class="col-4"><label class="form-label small">Perusahaan %</label>
            <input class="form-control form-control-sm" type="number" step="0.1" min="0" name="bpjs_kesehatan_company_percent" value="<?= e($s['bpjs_kesehatan_company_percent']) ?>"></div>
          <div class="col-4"><label class="form-label small">Karyawan %</label>
            <input class="form-control form-control-sm" type="number" step="0.1" min="0" name="bpjs_kesehatan_employee_percent" value="<?= e($s['bpjs_kesehatan_employee_percent']) ?>"></div>
          <div class="col-4"><label class="form-label small">Plafon Upah</label>
            <input class="form-control form-control-sm" type="number" step="100000" min="0" name="bpjs_kesehatan_max_base" value="<?= e($s['bpjs_kesehatan_max_base']) ?>"></div>
        </div>
        <h6 class="small fw-bold text-uppercase text-muted">BPJS Ketenagakerjaan — JHT</h6>
        <div class="row g-2 mb-3">
          <div class="col-6"><label class="form-label small">Perusahaan %</label>
            <input class="form-control form-control-sm" type="number" step="0.1" min="0" name="bpjs_jht_company_percent" value="<?= e($s['bpjs_jht_company_percent']) ?>"></div>
          <div class="col-6"><label class="form-label small">Karyawan %</label>
            <input class="form-control form-control-sm" type="number" step="0.1" min="0" name="bpjs_jht_employee_percent" value="<?= e($s['bpjs_jht_employee_percent']) ?>"></div>
        </div>
        <h6 class="small fw-bold text-uppercase text-muted">BPJS Ketenagakerjaan — JKK/JKM/Jp (digabung JP)</h6>
        <div class="row g-2 mb-2">
          <div class="col-4"><label class="form-label small">Perusahaan %</label>
            <input class="form-control form-control-sm" type="number" step="0.1" min="0" name="bpjs_jp_company_percent" value="<?= e($s['bpjs_jp_company_percent']) ?>"></div>
          <div class="col-4"><label class="form-label small">Karyawan %</label>
            <input class="form-control form-control-sm" type="number" step="0.1" min="0" name="bpjs_jp_employee_percent" value="<?= e($s['bpjs_jp_employee_percent']) ?>"></div>
          <div class="col-4"><label class="form-label small">Plafon Upah</label>
            <input class="form-control form-control-sm" type="number" step="100000" min="0" name="bpjs_jp_max_base" value="<?= e($s['bpjs_jp_max_base']) ?>"></div>
        </div>
        <div class="form-text">Persentase iuran karyawan dipotong dari gaji; persentase perusahaan dicatat sebagai biaya perusahaan.</div>
      </div>
    </div>
  </div>
</div>

<div class="d-grid gap-2 d-md-block mt-3">
  <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Setting</button>
  <a href="payroll_process.php" class="btn btn-outline-secondary">Ke Proses Payroll</a>
</div>
</form>

<div class="card mt-4">
  <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i>Ringkasan Aturan Potong / Jatah Cuti (dari Setting Absensi)</div>
  <div class="card-body row g-3 small">
    <?php $as = attendance_settings(); ?>
    <div class="col-md-4"><b>Potongan telat:</b> <?= rupiah($as['lat_cut_per_minute']) ?>/menit, maks <?= rupiah($as['lat_cut_max_per_day']) ?>/hari</div>
    <div class="col-md-4"><b>Sumber potongan alpa:</b> <?= $as['absence_deduction_type'] === 'salary' ? 'Potong gaji' : 'Potong jatah cuti' ?></div>
    <div class="col-md-4"><b>Nominal alpa:</b> <?= rupiah($as['absence_cut_amount']) ?>/hari
      <?= $as['absence_deduction_type']==='leave' && !empty($as['absence_deduct_leave_quota']) ? '(jatah cuti ikut berkurang)' : '' ?></div>
    <div class="col-12 text-muted">Ubah di <a href="attendance_settings.php">Setting Absensi</a>.</div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
