<?php
/**
 * Request Ubah Jadwal + Persetujuan (Direct Manager -> HR).
 */
$page_title = 'Ubah Jadwal'; $active_menu = 'sched_req';
require_once __DIR__ . '/includes/header.php';

$u = current_user();
$eid = employee_id();
$isHR = is_hr();

/* ---- Karyawan: buat request ---- */
if (!$isHR && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'create') {
    verify_csrf();
    exec_sql('INSERT INTO schedule_requests (employee_id, schedule_id, requested_start, requested_end, requested_check_in, requested_check_out, requested_break, reason) VALUES (?,?,?,?,?,?,?,?)',
        [$eid, $_POST['schedule_id'] ?: null, $_POST['requested_start'], $_POST['requested_end'],
         $_POST['requested_check_in'], $_POST['requested_check_out'], $_POST['requested_break'] ?: '01:00:00', trim($_POST['reason'])]);
    flash('success', 'Pengajuan ubah jadwal dikirim ke direct manager Anda.');
    redirect('schedule_requests.php');
}

/* ---- Persetujuan (manager utk PENDING_MGR, HR utk PENDING_HR) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['act'] ?? '', ['approve', 'reject'], true)) {
    verify_csrf();
    $id = (int)$_POST['id'];
    $req = one('SELECT * FROM schedule_requests WHERE id=?', [$id]);
    if (!$req) exit('Data tidak ditemukan.');
    $decision = $_POST['act'];
    $note = trim($_POST['note'] ?? '');

    if ($req['status'] === 'PENDING_MGR') {
        if (!is_manager_of((int)$req['employee_id']) && !$isHR) exit('Anda bukan approver tahap ini.');
        $new = $decision === 'approve' ? 'PENDING_HR' : 'REJECTED';
        exec_sql('UPDATE schedule_requests SET status=?, manager_note=?, manager_action_at=NOW() WHERE id=?', [$new, $note, $id]);
        audit('SCHED_REQ_MGR', "#$id -> $new");
    } elseif ($req['status'] === 'PENDING_HR') {
        require_role('HR');
        $new = $decision === 'approve' ? 'APPROVED' : 'REJECTED';
        exec_sql('UPDATE schedule_requests SET status=?, hr_note=?, hr_action_at=NOW() WHERE id=?', [$new, $note, $id]);
        if ($decision === 'approve') {
            // terapkan perubahan ke jadwal aktif karyawan
            $emp = one('SELECT branch_id FROM employees WHERE id=?', [$req['employee_id']]);
            exec_sql('DELETE FROM schedules WHERE employee_id=? AND start_date<=? AND end_date>=?',
                [$req['employee_id'], $req['requested_end'], $req['requested_start']]);
            exec_sql('INSERT INTO schedules (shift_id,branch_id,employee_id,start_date,end_date,check_in_time,check_out_time,break_time,created_by)
                      SELECT COALESCE(shift_id,(SELECT id FROM shifts ORDER BY id LIMIT 1)),?,?,?, ?,?,?,?,? FROM schedules WHERE id=?',
                [$emp['branch_id'], $req['employee_id'], $req['requested_start'], $req['requested_end'],
                 $req['requested_check_in'], $req['requested_check_out'], $req['requested_break'], $u['id'], $req['schedule_id']]);
        }
        audit('SCHED_REQ_HR', "#$id -> $new");
    } else exit('Request sudah final.');
    flash('success', 'Keputusan tersimpan.');
    redirect('schedule_requests.php');
}

/* ---- Data list ---- */
if ($isHR) {
    $rows = all("SELECT r.*, e.full_name, e.employee_code FROM schedule_requests r JOIN employees e ON e.id=r.employee_id ORDER BY r.created_at DESC LIMIT 200");
} else {
    $mine = $eid ? all("SELECT r.*, e.full_name, e.employee_code FROM schedule_requests r JOIN employees e ON e.id=r.employee_id WHERE r.employee_id=? ORDER BY r.created_at DESC", [$eid]) : [];
    $asMgr = $eid ? all("SELECT r.*, e.full_name, e.employee_code FROM schedule_requests r JOIN employees e ON e.id=r.employee_id WHERE e.direct_manager_id=? AND r.status='PENDING_MGR' ORDER BY r.created_at DESC", [$eid]) : [];
    $rows = $mine;
}
$myScheds = $eid ? all('SELECT s.*, sh.name shift_name FROM schedules s JOIN shifts sh ON sh.id=s.shift_id WHERE s.employee_id=? ORDER BY start_date DESC LIMIT 30', [$eid]) : [];
?>
<h1 class="page-title mb-3"><i class="bi bi-arrow-left-right me-2"></i>Pengubahan Jadwal Kerja</h1>
<div class="small text-muted mb-3">Alur persetujuan: <span class="badge bg-warning text-dark">1. Direct Manager</span> → <span class="badge bg-info text-dark">2. HR</span> → jadwal otomatis diperbarui.</div>

<?php if (!$isHR): ?>
<div class="card mb-3"><div class="card-body">
  <h5 class="fw-bold"><i class="bi bi-plus-circle me-1"></i>Ajukan Ubah Jadwal</h5>
  <form method="post" class="row g-2 align-items-end">
    <?= csrf_field() ?><input type="hidden" name="act" value="create">
    <div class="col-md-3"><label class="form-label small">Jadwal saat ini</label>
      <select class="form-select form-select-sm" name="schedule_id"><option value="">-</option>
      <?php foreach ($myScheds as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['shift_name']) ?> (<?= e(date_id($s['start_date'])) ?>–<?= e(date_id($s['end_date'])) ?>)</option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label small">Tanggal Mulai</label><input type="date" class="form-control form-control-sm" name="requested_start" required></div>
    <div class="col-md-2"><label class="form-label small">Tanggal Akhir</label><input type="date" class="form-control form-control-sm" name="requested_end" required></div>
    <div class="col-md-2"><label class="form-label small">Check-In Baru</label><input type="time" class="form-control form-control-sm" name="requested_check_in" value="08:00" required></div>
    <div class="col-md-2"><label class="form-label small">Check-Out Baru</label><input type="time" class="form-control form-control-sm" name="requested_check_out" value="17:00" required></div>
    <div class="col-md-2"><label class="form-label small">Break</label><input type="time" class="form-control form-control-sm" name="requested_break" value="01:00"></div>
    <div class="col-md-8"><label class="form-label small">Alasan / Keperluan</label><input class="form-control form-control-sm" name="reason" placeholder="contoh: menyesuaikan jadwal training" required></div>
    <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Kirim Pengajuan</button></div>
  </form>
</div></div>

<?php if ($asMgr): ?>
<div class="card mb-3 border-warning">
  <div class="card-header bg-warning-subtle fw-bold"><i class="bi bi-inbox me-1"></i>Menunggu Persetujuan Anda (sebagai Direct Manager)</div>
  <div class="card-body p-0 table-responsive"><table class="table table-hover mb-0 align-middle">
    <thead><tr><th>Karyawan</th><th>Periode Baru</th><th>Jam Baru</th><th>Alasan</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($asMgr as $r): ?>
    <tr>
      <td><b><?= e($r['full_name']) ?></b><br><span class="small text-muted"><?= e($r['employee_code']) ?></span></td>
      <td class="small"><?= e(date_id($r['requested_start'])) ?> – <?= e(date_id($r['requested_end'])) ?></td>
      <td class="small"><?= time_hm($r['requested_check_in']) ?> – <?= time_hm($r['requested_check_out']) ?></td>
      <td class="small"><?= e($r['reason']) ?></td>
      <td style="min-width:260px">
        <form method="post" class="d-flex gap-1">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input class="form-control form-control-sm" name="note" placeholder="Catatan">
          <button class="btn btn-sm btn-success" name="act" value="approve"><i class="bi bi-check-lg"></i></button>
          <button class="btn btn-sm btn-danger" name="act" value="reject"><i class="bi bi-x-lg"></i></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="card">
  <div class="card-header fw-bold"><i class="bi bi-list-ul me-1"></i><?= $isHR ? 'Semua Pengajuan Ubah Jadwal' : 'Riwayat Pengajuan Saya' ?></div>
  <div class="card-body p-0 table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>#</th><?= $isHR ? '<th>Karyawan</th>' : '' ?><th>Periode Diminta</th><th>Jam Diminta</th><th>Alasan</th><th>Status</th><th>Catatan</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $final = in_array($r['status'], ['APPROVED','REJECTED']); ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <?php if ($isHR): ?><td><b><?= e($r['full_name']) ?></b><br><span class="small text-muted"><?= e($r['employee_code']) ?></span></td><?php endif; ?>
      <td class="small"><?= e(date_id($r['requested_start'])) ?> – <?= e(date_id($r['requested_end'])) ?></td>
      <td class="small"><?= time_hm($r['requested_check_in']) ?> – <?= time_hm($r['requested_check_out']) ?><br>break <?= time_hm($r['requested_break']) ?></td>
      <td class="small" style="max-width:180px"><?= e($r['reason']) ?></td>
      <td><?= status_badge($r['status']) ?></td>
      <td class="small text-muted">M: <?= e($r['manager_note'] ?? '-') ?><br>HR: <?= e($r['hr_note'] ?? '-') ?></td>
      <td>
        <?php if ($isHR && $r['status'] === 'PENDING_HR'): ?>
        <form method="post" class="d-flex gap-1">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input class="form-control form-control-sm" name="note" placeholder="Catatan HR" style="width:110px">
          <button class="btn btn-sm btn-success" name="act" value="approve"><i class="bi bi-check-lg"></i></button>
          <button class="btn btn-sm btn-danger" name="act" value="reject"><i class="bi bi-x-lg"></i></button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">Belum ada pengajuan.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
