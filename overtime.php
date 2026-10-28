<?php
/**
 * Overtime / Lembur: pengajuan karyawan + approval berjenjang (Manager -> HR) + setting kompensasi.
 */
$page_title = 'Lembur'; $active_menu = 'overtime';
require_once __DIR__ . '/includes/header.php';

$u = current_user();
$eid = employee_id();
$isHR = is_hr();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = $_POST['act'] ?? '';

    /* Karyawan mengajukan lembur */
    if ($act === 'create' && $eid) {
        $start = $_POST['start_time']; $end = $_POST['end_time'];
        $dur = (strtotime($_POST['work_date'] . ' ' . $end) - strtotime($_POST['work_date'] . ' ' . $start)) / 3600;
        if ($end < $start) $dur += 24; // lewat tengah malam
        $os = overtime_settings();
        $dur = min($dur, (float)$os['max_hours_per_day']);
        exec_sql('INSERT INTO overtime_requests (employee_id, work_date, start_time, end_time, duration_hours, reason) VALUES (?,?,?,?,?,?)',
            [$eid, $_POST['work_date'], $start, $end, round($dur, 2), trim($_POST['reason'])]);
        flash('success', 'Pengajuan lembur dikirim ke direct manager Anda.');
        redirect('overtime.php');
    }

    /* Approval berjenjang */
    if (in_array($act, ['approve', 'reject'], true)) {
        $id = (int)$_POST['id'];
        $req = one('SELECT * FROM overtime_requests WHERE id=?', [$id]);
        if (!$req) exit('Data tidak ditemukan.');
        $note = trim($_POST['note'] ?? '');
        if ($req['status'] === 'PENDING_MGR') {
            if (!is_manager_of((int)$req['employee_id']) && !$isHR) exit('Anda bukan approver tahap ini.');
            $new = $act === 'approve' ? 'PENDING_HR' : 'REJECTED';
            exec_sql('UPDATE overtime_requests SET status=?, manager_note=?, manager_action_at=NOW() WHERE id=?', [$new, $note, $id]);
        } elseif ($req['status'] === 'PENDING_HR') {
            require_role('HR');
            $new = $act === 'approve' ? 'APPROVED' : 'REJECTED';
            exec_sql('UPDATE overtime_requests SET status=?, hr_note=?, hr_action_at=NOW() WHERE id=?', [$new, $note, $id]);
        } else exit('Request sudah final.');
        audit('OT_' . strtoupper($act), "#$id");
        flash('success', 'Keputusan tersimpan.');
        redirect('overtime.php' . ($isHR ? '' : ''));
    }

    /* Setting lembur (HR) */
    if ($act === 'save_ot_setting') {
        require_role('HR');
        exec_sql('UPDATE overtime_settings SET rate_hour1=?, rate_hour2=?, rate_hour_more=?, max_hours_per_day=?, max_comp_per_day=?, enabled=? WHERE id=1', [
            (float)$_POST['rate_hour1'], (float)$_POST['rate_hour2'], (float)$_POST['rate_hour_more'],
            (float)$_POST['max_hours_per_day'], (float)$_POST['max_comp_per_day'], isset($_POST['enabled']) ? 1 : 0]);
        audit('OT_SETTINGS', 'Perubahan setting lembur');
        flash('success', 'Setting lembur disimpan.');
        redirect('overtime.php#setting');
    }
}

/* estimasi kompensasi berjenjang utk durasi jam */
function ot_compensation(array $os, float $hours): float
{
    $comp = 0; $rem = $hours;
    if ($rem > 0) { $comp += $os['rate_hour1'] * min(1, $rem); $rem -= 1; }
    if ($rem > 0) { $comp += $os['rate_hour2'] * min(1, $rem); $rem -= 1; }
    if ($rem > 0) { $comp += $os['rate_hour_more'] * $rem; }
    return min($comp, (float)$os['max_comp_per_day']);
}

$os = overtime_settings();

/* Data tampilan */
if ($isHR) {
    $rows = all("SELECT o.*, e.full_name, e.employee_code, b.name branch FROM overtime_requests o
                JOIN employees e ON e.id=o.employee_id LEFT JOIN branches b ON b.id=e.branch_id
                ORDER BY o.created_at DESC LIMIT 300");
} else {
    $mine = $eid ? all("SELECT o.*, e.full_name FROM overtime_requests o JOIN employees e ON e.id=o.employee_id
                        WHERE o.employee_id=? ORDER BY o.work_date DESC", [$eid]) : [];
    $asMgr = $eid ? all("SELECT o.*, e.full_name, e.employee_code FROM overtime_requests o JOIN employees e ON e.id=o.employee_id
                         WHERE e.direct_manager_id=? AND o.status='PENDING_MGR' ORDER BY o.work_date DESC", [$eid]) : [];
    $rows = $mine;
}
?>
<h1 class="page-title mb-3"><i class="bi bi-stopwatch me-2"></i>Kerja Lembur (Overtime)</h1>

<?php if (!$isHR): ?>
<div class="card mb-3"><div class="card-body">
  <h5 class="fw-bold"><i class="bi bi-plus-circle me-1"></i>Pengajuan Lembur Baru</h5>
  <form method="post" class="row g-2 align-items-end">
    <?= csrf_field() ?><input type="hidden" name="act" value="create">
    <div class="col-md-2"><label class="form-label small">Tanggal Lembur *</label><input type="date" class="form-control form-control-sm" name="work_date" value="<?= date('Y-m-d') ?>" required></div>
    <div class="col-md-2"><label class="form-label small">Jam Mulai *</label><input type="time" class="form-control form-control-sm" name="start_time" value="17:00" required></div>
    <div class="col-md-2"><label class="form-label small">Jam Selesai *</label><input type="time" class="form-control form-control-sm" name="end_time" value="19:00" required></div>
    <div class="col-md-4"><label class="form-label small">Uraian Pekerjaan *</label><input class="form-control form-control-sm" name="reason" placeholder="mis: closing laporan bulan" required></div>
    <div class="col-md-2"><button class="btn btn-primary btn-sm w-100" <?= $os['enabled'] ? '' : 'disabled title="Lembur dinonaktifkan HR"' ?>>Kirim Pengajuan</button></div>
    <div class="col-12 small text-muted">Maks lembur/hari: <b><?= (float)$os['max_hours_per_day'] ?> jam</b> · Kompensasi jam ke-1 Rp<?= money($os['rate_hour1']) ?>, ke-2 Rp<?= money($os['rate_hour2']) ?>, dst Rp<?= money($os['rate_hour_more']) ?>/jam, maks Rp<?= money($os['max_comp_per_day']) ?>/hari.</div>
  </form>
</div></div>

<?php if (!empty($asMgr)): ?>
<div class="card mb-3 border-warning">
  <div class="card-header bg-warning-subtle fw-bold"><i class="bi bi-inbox me-1"></i>Menunggu Persetujuan Anda (Direct Manager)</div>
  <div class="card-body p-0 table-responsive"><table class="table table-hover mb-0 align-middle">
    <thead><tr><th>Karyawan</th><th>Tanggal</th><th>Jam</th><th>Durasi</th><th>Estimasi Upah</th><th>Uraian</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($asMgr as $r): ?>
    <tr>
      <td><b><?= e($r['full_name']) ?></b><br><span class="small text-muted"><?= e($r['employee_code']) ?></span></td>
      <td class="small"><?= e(date_id($r['work_date'])) ?></td>
      <td class="small"><?= time_hm($r['start_time']) ?>–<?= time_hm($r['end_time']) ?></td>
      <td><?= number_format((float)$r['duration_hours'], 1) ?> jam</td>
      <td class="fw-bold text-success"><?= rupiah(ot_compensation($os, (float)$r['duration_hours'])) ?></td>
      <td class="small" style="max-width:160px"><?= e($r['reason']) ?></td>
      <td style="min-width:250px"><form method="post" class="d-flex gap-1">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <input class="form-control form-control-sm" name="note" placeholder="Catatan">
        <button class="btn btn-sm btn-success" name="act" value="approve"><i class="bi bi-check-lg"></i></button>
        <button class="btn btn-sm btn-danger" name="act" value="reject"><i class="bi bi-x-lg"></i></button>
      </form></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="card">
  <div class="card-header fw-bold d-flex justify-content-between"><span><i class="bi bi-list-ul me-1"></i><?= $isHR ? 'Semua Pengajuan Lembur' : 'Status Pengajuan Lembur Saya' ?></span>
    <?php if ($isHR): ?><a href="#setting" class="small"><i class="bi bi-gear"></i> Setting Lembur</a><?php endif; ?></div>
  <div class="card-body p-0 table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>#</th><?= $isHR ? '<th>Karyawan</th><th>Branch</th>' : '' ?><th>Tanggal</th><th>Jam</th><th>Durasi</th><th>Upah*</th><th>Status</th><th>Catatan Approver</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <?php if ($isHR): ?><td><b><?= e($r['full_name']) ?></b><br><span class="small text-muted"><?= e($r['employee_code']) ?></span></td>
      <td class="small"><?= e($r['branch'] ?? '-') ?></td><?php endif; ?>
      <td class="small"><?= e(date_id($r['work_date'])) ?></td>
      <td class="small"><?= time_hm($r['start_time']) ?>–<?= time_hm($r['end_time']) ?></td>
      <td><?= number_format((float)$r['duration_hours'], 1) ?> jam</td>
      <td class="text-end"><?= rupiah(ot_compensation($os, (float)$r['duration_hours'])) ?></td>
      <td><?= status_badge($r['status']) ?></td>
      <td class="small text-muted">M: <?= e($r['manager_note'] ?? '-') ?><br>HR: <?= e($r['hr_note'] ?? '-') ?></td>
      <td><?php if ($isHR && $r['status'] === 'PENDING_HR'): ?>
        <form method="post" class="d-flex gap-1">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input class="form-control form-control-sm" name="note" placeholder="Catatan HR" style="width:100px">
          <button class="btn btn-sm btn-success" name="act" value="approve"><i class="bi bi-check-lg"></i></button>
          <button class="btn btn-sm btn-danger" name="act" value="reject"><i class="bi bi-x-lg"></i></button>
        </form><?php endif; ?></td>
    </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="10" class="text-center text-muted py-4">Belum ada pengajuan.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
  <div class="card-footer small text-muted">*Upah lembur dihitung dari tarif bertingkat &amp; dibatasi maksimum harian. Hanya status APPROVED yang masuk payroll.</div>
</div>

<?php if ($isHR): ?>
<!-- ===== Setting Lembur ===== -->
<div class="card mt-4 border-info" id="setting">
  <div class="card-header text-bg-info"><i class="bi bi-sliders me-1"></i>Pengaturan Kompensasi Lembur</div>
  <div class="card-body">
    <form method="post" class="row g-3">
      <?= csrf_field() ?><input type="hidden" name="act" value="save_ot_setting">
      <div class="col-md-3"><label class="form-label small fw-bold">Tarif Jam ke-1 (Rp)</label><input class="form-control" name="rate_hour1" inputmode="numeric" value="<?= money($os['rate_hour1']) ?>"></div>
      <div class="col-md-3"><label class="form-label small fw-bold">Tarif Jam ke-2 (Rp)</label><input class="form-control" name="rate_hour2" inputmode="numeric" value="<?= money($os['rate_hour2']) ?>"></div>
      <div class="col-md-3"><label class="form-label small fw-bold">Tarif Jam ke-3 dst (Rp/jam)</label><input class="form-control" name="rate_hour_more" inputmode="numeric" value="<?= money($os['rate_hour_more']) ?>"></div>
      <div class="col-md-3"><label class="form-label small fw-bold">Maks Waktu Lembur / Hari (jam)</label><input type="number" step="0.5" class="form-control" name="max_hours_per_day" value="<?= (float)$os['max_hours_per_day'] ?>"></div>
      <div class="col-md-4"><label class="form-label small fw-bold">Maks Kompensasi / Hari (Rp)</label><input class="form-control" name="max_comp_per_day" inputmode="numeric" value="<?= money($os['max_comp_per_day']) ?>"></div>
      <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" name="enabled" id="otEn" <?= $os['enabled'] ? 'checked' : '' ?>>
        <label class="form-check-label fw-bold" for="otEn">Modul lembur aktif</label></div></div>
      <div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary w-100">Simpan Setting</button></div>
    </form>
  </div>
</div>
<script>
document.querySelectorAll('input[inputmode=numeric]').forEach(i=>{
  i.addEventListener('blur',()=>{i.value=i.value.replace(/[^\d]/g,'')});
  i.addEventListener('focus',()=>{if(i.value==='0')i.value=''});
});
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
