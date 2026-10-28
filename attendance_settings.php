<?php
/**
 * Setting Absensi (HR): waktu check-in, grace period, kompresi selfie,
 * pemotongan gaji keterlambatan, potongan ketidak-hadiran (gaji/jatah cuti).
 */
$page_title = 'Setting Absensi'; $active_menu = 'att_setting';
require_once __DIR__ . '/includes/header.php';
require_role('HR');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    exec_sql('UPDATE attendance_settings SET grace_minutes=?, checkin_start=?, checkin_deadline=?, selfie_max_kb=?, selfie_quality=?, selfie_resize=?,
              lat_cut_per_minute=?, lat_cut_max_per_day=?, absence_deduction_type=?, absence_cut_amount=?, absence_deduct_leave_quota=? WHERE id=1', [
        (int)$_POST['grace_minutes'], $_POST['checkin_start'], $_POST['checkin_deadline'],
        (int)$_POST['selfie_max_kb'], (int)$_POST['selfie_quality'], (int)$_POST['selfie_resize'],
        (float)$_POST['lat_cut_per_minute'], (float)$_POST['lat_cut_max_per_day'],
        $_POST['absence_deduction_type'], (float)$_POST['absence_cut_amount'], isset($_POST['absence_deduct_leave_quota']) ? 1 : 0,
    ]);
    audit('ATT_SETTINGS', 'Perubahan setting absensi');
    flash('success', 'Setting absensi disimpan.');
    redirect('attendance_settings.php');
}
$s = attendance_settings();
?>
<h1 class="page-title mb-3"><i class="bi bi-gear me-2"></i>Pengaturan Waktu &amp; Disiplin Absensi</h1>

<form method="post" class="row g-4">
  <?= csrf_field() ?>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-clock me-1"></i>Waktu Absensi</div>
      <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label small fw-bold">Jam Mulai Valid Check-In</label><input type="time" class="form-control" name="checkin_start" value="<?= substr($s['checkin_start'], 0, 5) ?>"></div>
        <div class="col-md-6"><label class="form-label small fw-bold">Deadline Check-In</label><input type="time" class="form-control" name="checkin_deadline" value="<?= substr($s['checkin_deadline'], 0, 5) ?>"></div>
        <div class="col-md-6"><label class="form-label small fw-bold">Toleransi Terlambat (menit)</label><input type="number" class="form-control" name="grace_minutes" value="<?= (int)$s['grace_minutes'] ?>"></div>
        <div class="col-12 small text-muted">Karyawan yang check-in melewati jam masuk jadwal + toleransi dihitung terlambat sejak jam masuk jadwal.</div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-camera me-1"></i>Foto Selfie (kompresi sebelum upload)</div>
      <div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label small fw-bold">Maks Ukuran (KB)</label><input type="number" class="form-control" name="selfie_max_kb" value="<?= (int)$s['selfie_max_kb'] ?>"></div>
        <div class="col-md-4"><label class="form-label small fw-bold">Kualitas JPEG (%)</label><input type="number" min="30" max="95" class="form-control" name="selfie_quality" value="<?= (int)$s['selfie_quality'] ?>"></div>
        <div class="col-md-4"><label class="form-label small fw-bold">Resize Maks (px)</label><input type="number" class="form-control" name="selfie_resize" value="<?= (int)$s['selfie_resize'] ?>"></div>
        <div class="col-12 small text-muted">Browser akan me-resize &amp; mengompres foto sesuai nilai ini sebelum dikirim ke server — hemat kuota &amp; storage.</div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100 border-danger">
      <div class="card-header text-bg-danger"><i class="bi bi-scissors me-1"></i>Pemotongan Gaji Karena Keterlambatan</div>
      <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label small fw-bold">Potongan per Menit Terlambat (Rp)</label><input class="form-control" name="lat_cut_per_minute" inputmode="numeric" value="<?= money($s['lat_cut_per_minute']) ?>"></div>
        <div class="col-md-6"><label class="form-label small fw-bold">Maksimal Potongan / Hari (Rp)</label><input class="form-control" name="lat_cut_max_per_day" inputmode="numeric" value="<?= money($s['lat_cut_max_per_day']) ?>"></div>
        <div class="col-12 small text-muted">Rumus: menit_telat × tarif/menit, dibatasi maks harian. Akumulasi otomatis masuk slip gaji saat proses payroll.</div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100 border-warning">
      <div class="card-header text-bg-warning"><i class="bi bi-person-x me-1"></i>Potongan Karena Ketidakhadiran (Alpha)</div>
      <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label small fw-bold">Sumber Potongan</label>
          <select class="form-select" name="absence_deduction_type">
            <option value="salary" <?= $s['absence_deduction_type']==='salary'?'selected':'' ?>>Potong Gaji</option>
            <option value="leave" <?= $s['absence_deduction_type']==='leave'?'selected':'' ?>>Potong Jatah Cuti</option>
          </select></div>
        <div class="col-md-6"><label class="form-label small fw-bold">Nominal Potong / Hari Alpha (Rp)</label><input class="form-control" name="absence_cut_amount" inputmode="numeric" value="<?= money($s['absence_cut_amount']) ?>"></div>
        <div class="col-12"><div class="form-check">
          <input class="form-check-input" type="checkbox" name="absence_deduct_leave_quota" id="adq" <?= $s['absence_deduct_leave_quota'] ? 'checked' : '' ?>>
          <label class="form-check-label small" for="adq">Jika tipe = potong jatah cuti, kurangi kuota cuti tahunan karyawan (bila tersedia).</label>
        </div></div>
      </div>
    </div>
  </div>

  <div class="col-12"><button class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Simpan Semua Pengaturan</button></div>
</form>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
