<?php
/**
 * Log Absensi (HR) - tabel log per karyawan/branch/tanggal + koreksi manual.
 */
$page_title = 'Log Absensi'; $active_menu = 'att_log';
require_once __DIR__ . '/includes/header.php';
require_role('HR');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'fix') {
    verify_csrf();
    $id = (int)$_POST['id'];
    exec_sql('UPDATE attendance_logs SET check_in_at=?, check_out_at=?, late_minutes=?, notes=? WHERE id=?',
        [$_POST['check_in_at'] ?: null, $_POST['check_out_at'] ?: null, (int)$_POST['late_minutes'], trim($_POST['notes']), $id]);
    audit('ATT_FIX', "log #$id dikoreksi manual");
    flash('success', 'Log absensi dikoreksi.');
    redirect('attendance_log.php');
}

$fBranch = (int)($_GET['branch'] ?? 0);
$fDate = $_GET['date'] ?? date('Y-m-d');
$sql = 'SELECT a.*, e.full_name, e.employee_code, b.name branch_name FROM attendance_logs a
        JOIN employees e ON e.id=a.employee_id LEFT JOIN branches b ON b.id=a.branch_id WHERE a.log_date=?';
$p = [$fDate];
if ($fBranch) { $sql .= ' AND a.branch_id=?'; $p[] = $fBranch; }
$rows = all($sql . ' ORDER BY e.full_name', $p);
$branches = all('SELECT * FROM branches WHERE is_active=1 ORDER BY name');
?>
<h1 class="page-title mb-3"><i class="bi bi-clock-history me-2"></i>Log Absensi Harian</h1>

<div class="card mb-3"><div class="card-body py-2">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-3"><label class="form-label small mb-0">Tanggal</label><input type="date" class="form-control form-control-sm" name="date" value="<?= e($fDate) ?>"></div>
    <div class="col-md-3"><label class="form-label small mb-0">Branch</label><select class="form-select form-select-sm" name="branch"><option value="0">Semua</option>
      <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $fBranch==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><button class="btn btn-sm btn-primary">Tampilkan</button>
      <a class="btn btn-sm btn-outline-dark" href="pdf_attendance.php?type=log&date=<?= e($fDate) ?>&branch=<?= $fBranch ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a></div>
  </form>
</div></div>

<div class="card"><div class="card-body table-responsive">
<table class="table table-hover align-middle">
<thead><tr><th>Tgl</th><th>Karyawan</th><th>Branch</th><th>Check-In</th><th>Check-Out</th><th>Telat</th><th>Lembur*</th><th>Jarak GPS</th><th>Foto</th><th>Sumber</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td class="small"><?= e(date_id($r['log_date'])) ?></td>
  <td><b><?= e($r['full_name']) ?></b><br><span class="small text-muted"><?= e($r['employee_code']) ?></span></td>
  <td class="small"><?= e($r['branch_name'] ?? '-') ?></td>
  <td><?= $r['check_in_at'] ? e(date('H:i', strtotime($r['check_in_at']))) : '<span class="text-danger">-</span>' ?></td>
  <td><?= $r['check_out_at'] ? e(date('H:i', strtotime($r['check_out_at']))) : '<span class="text-warning">belum</span>' ?></td>
  <td><?= (int)$r['late_minutes'] > 0 ? '<span class="badge bg-danger">' . (int)$r['late_minutes'] . ' mnt</span>' : '<span class="text-success small">ontime</span>' ?></td>
  <td class="small"><?= (float)$r['overtime_hours'] ?: '-' ?></td>
  <td class="small"><?= $r['check_in_distance'] !== null ? (int)$r['check_in_distance'] . ' m' : '-' ?></td>
  <td>
    <?php if ($r['check_in_photo']): ?><a href="<?= e($r['check_in_photo']) ?>" target="_blank" title="Foto check-in"><i class="bi bi-person-check text-success"></i></a><?php endif; ?>
    <?php if ($r['check_out_photo']): ?><a href="<?= e($r['check_out_photo']) ?>" target="_blank" title="Foto check-out"><i class="bi bi-camera text-primary"></i></a><?php endif; ?>
  </td>
  <td><span class="badge bg-secondary"><?= e($r['source']) ?></span></td>
  <td><button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#mFix"
      onclick='fixRow(<?= json_encode($r, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button></td>
</tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="11" class="text-center text-muted py-4">Tidak ada log pada tanggal tsb.</td></tr><?php endif; ?>
</tbody></table>
</div></div>

<div class="modal fade" id="mFix"><div class="modal-content"><form method="post" class="modal-body">
  <?= csrf_field() ?><input type="hidden" name="act" value="fix"><input type="hidden" name="id" id="x_id">
  <h5>Koreksi Manual Absensi</h5>
  <div class="mb-2 small text-muted" id="x_who"></div>
  <div class="row g-2">
    <div class="col-md-6"><label class="form-label small">Check-In (YYYY-MM-DD HH:MM:SS)</label><input class="form-control" name="check_in_at" id="x_in"></div>
    <div class="col-md-6"><label class="form-label small">Check-Out</label><input class="form-control" name="check_out_at" id="x_out"></div>
    <div class="col-md-4"><label class="form-label small">Telat (menit)</label><input type="number" class="form-control" name="late_minutes" id="x_late"></div>
    <div class="col-md-8"><label class="form-label small">Catatan</label><input class="form-control" name="notes" id="x_notes"></div>
  </div>
  <div class="text-end mt-3"><button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
  <button class="btn btn-primary btn-sm">Simpan Koreksi</button></div>
</form></div></div>
<script>
function fixRow(r){
  document.getElementById('x_id').value=r.id;
  document.getElementById('x_who').textContent=r.full_name+' ('+r.employee_code+') — '+r.log_date;
  document.getElementById('x_in').value=r.check_in_at||'';
  document.getElementById('x_out').value=r.check_out_at||'';
  document.getElementById('x_late').value=r.late_minutes;
  document.getElementById('x_notes').value=r.notes||'';
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
