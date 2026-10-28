<?php
/**
 * Approval Center (HR + Direct Manager): lembur, cuti, ubah jadwal dalam satu tempat.
 */
$page_title = 'Approval Center'; $active_menu = 'approvals';
require_once __DIR__ . '/includes/header.php';

$u = current_user();
$isHR = is_hr();
$eid = employee_id();

/* ---- aksi approve/reject generik ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['act'] ?? '', ['approve', 'reject'], true)) {
    verify_csrf();
    $kind = $_POST['kind'];           // ot | leave | sched
    $id = (int)$_POST['id'];
    $note = trim($_POST['note'] ?? '');
    $decision = $_POST['act'];

    $tables = ['ot' => 'overtime_requests', 'leave' => 'leave_requests', 'sched' => 'schedule_requests'];
    $tbl = $tables[$kind];
    $req = one("SELECT * FROM $tbl WHERE id=?", [$id]);
    if (!$req) exit('Data tidak ditemukan.');

    if ($req['status'] === 'PENDING_MGR') {
        if (!is_manager_of((int)$req['employee_id']) && !$isHR) exit('Anda bukan approver tahap ini.');
        $new = $decision === 'approve' ? 'PENDING_HR' : 'REJECTED';
        exec_sql("UPDATE $tbl SET status=?, manager_note=?, manager_action_at=NOW() WHERE id=?", [$new, $note, $id]);
    } elseif ($req['status'] === 'PENDING_HR') {
        require_role('HR');
        $new = $decision === 'approve' ? 'APPROVED' : 'REJECTED';
        exec_sql("UPDATE $tbl SET status=?, hr_note=?, hr_action_at=NOW() WHERE id=?", [$new, $note, $id]);
        if ($decision === 'approve') {
            if ($kind === 'leave') {
                $y = (int)date('Y', strtotime($req['start_date']));
                exec_sql('INSERT INTO leave_quotas (employee_id,leave_type_id,year,used_days) VALUES (?,?,?,?)
                          ON DUPLICATE KEY UPDATE used_days = used_days + VALUES(used_days)',
                    [$req['employee_id'], $req['leave_type_id'], $y, $req['days_count']]);
            } elseif ($kind === 'sched') {
                $empB = one('SELECT branch_id FROM employees WHERE id=?', [$req['employee_id']]);
                exec_sql('DELETE FROM schedules WHERE employee_id=? AND start_date<=? AND end_date>=?',
                    [$req['employee_id'], $req['requested_end'], $req['requested_start']]);
                exec_sql('INSERT INTO schedules (shift_id,branch_id,employee_id,start_date,end_date,check_in_time,check_out_time,break_time,created_by)
                          SELECT COALESCE(shift_id,(SELECT id FROM shifts ORDER BY id LIMIT 1)),?,?,?,?,?,?,?,? FROM schedules WHERE id=?',
                    [$empB['branch_id'], $req['employee_id'], $req['requested_start'], $req['requested_end'],
                     $req['requested_check_in'], $req['requested_check_out'], $req['requested_break'], $u['id'], $req['schedule_id']]);
            }
        }
    } else exit('Sudah final.');
    audit("APPROVAL_$kind", "#$id -> $decision");
    flash('success', 'Keputusan approval tersimpan.');
    redirect('approvals.php');
}

/* ---- ambil antrian ---- */
function queue_for($eid, $isHR, $kind, $tbl, $select, $join = '')
{
    if ($isHR) {
        return all("SELECT $select FROM $tbl t JOIN employees e ON e.id=t.employee_id $join WHERE t.status IN ('PENDING_MGR','PENDING_HR') ORDER BY t.created_at");
    }
    return all("SELECT $select FROM $tbl t JOIN employees e ON e.id=t.employee_id $join WHERE e.direct_manager_id=? AND t.status='PENDING_MGR' ORDER BY t.created_at", [$eid]);
}

$otRows = queue_for($eid, $isHR, 'ot', 'overtime_requests',
    't.*, e.full_name, e.employee_code', 'LEFT JOIN branches b ON b.id=e.branch_id');
$lvRows = queue_for($eid, $isHR, 'leave', 'leave_requests',
    't.*, e.full_name, e.employee_code, lt.name type_name, lt.is_paid', 'JOIN leave_types lt ON lt.id=t.leave_type_id');
$sqRows = queue_for($eid, $isHR, 'sched', 'schedule_requests',
    't.*, e.full_name, e.employee_code');

function appr_form(string $kind, int $id): string
{
    $csrf = csrf_field();
    return "<form method='post' class='d-flex gap-1'>
      <input type='hidden' name='_csrf' value='" . csrf_token() . "'>
      <input type='hidden' name='kind' value='$kind'><input type='hidden' name='id' value='$id'>
      <input class='form-control form-control-sm' name='note' placeholder='Catatan' style='min-width:90px'>
      <button class='btn btn-sm btn-success' name='act' value='approve' title='Setujui'><i class='bi bi-check-lg'></i></button>
      <button class='btn btn-sm btn-danger' name='act' value='reject' title='Tolak'><i class='bi bi-x-lg'></i></button>
    </form>";
}
?>
<h1 class="page-title mb-1"><i class="bi bi-check2-square me-2"></i>Approval Center</h1>
<div class="text-muted-sm mb-3">Alur persetujuan dua tingkat: <b>1) Direct Manager</b> → <b>2) HR</b>. <?= $isHR ? 'Anda melihat seluruh antrian yang menunggu tindakan.' : 'Berikut pengajuan tim Anda yang menunggu persetujuan Anda sebagai manager.' ?></div>

<ul class="nav nav-pills mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tabOt"><i class="bi bi-stopwatch me-1"></i>Lembur <span class="badge bg-danger ms-1"><?= count($otRows) ?></span></button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabLv"><i class="bi bi-umbrella me-1"></i>Cuti <span class="badge bg-danger ms-1"><?= count($lvRows) ?></span></button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabSq"><i class="bi bi-arrow-left-right me-1"></i>Ubah Jadwal <span class="badge bg-danger ms-1"><?= count($sqRows) ?></span></button></li>
</ul>

<div class="tab-content">
  <!-- LEMBUR -->
  <div class="tab-pane fade show active" id="tabOt">
    <div class="card"><div class="card-body table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Karyawan</th><th>Tanggal</th><th>Jam</th><th>Durasi</th><th>Uraian</th><th>Tahap</th><th>Aksi</th></tr></thead>
        <tbody><?php foreach ($otRows as $r): ?>
        <tr>
          <td><b><?= e($r['full_name']) ?></b><br><span class="small text-muted"><?= e($r['employee_code']) ?></span></td>
          <td class="small"><?= e(date_id($r['work_date'])) ?></td>
          <td class="small"><?= time_hm($r['start_time']) ?>–<?= time_hm($r['end_time']) ?></td>
          <td><?= number_format((float)$r['duration_hours'], 1) ?> jam</td>
          <td class="small" style="max-width:180px"><?= e($r['reason']) ?></td>
          <td><?= status_badge($r['status']) ?></td>
          <td style="min-width:240px"><?= appr_form('ot', (int)$r['id']) ?></td>
        </tr><?php endforeach; if (!$otRows): ?><tr><td colspan="7" class="text-center text-muted py-4">Antrian kosong.</td></tr><?php endif; ?></tbody>
      </table>
    </div></div>
  </div>

  <!-- CUTI -->
  <div class="tab-pane fade" id="tabLv">
    <div class="card"><div class="card-body table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Karyawan</th><th>Jenis</th><th>Tanggal</th><th>Hari</th><th>Keperluan</th><th>Tahap</th><th>Aksi</th></tr></thead>
        <tbody><?php foreach ($lvRows as $r): ?>
        <tr>
          <td><b><?= e($r['full_name']) ?></b><br><span class="small text-muted"><?= e($r['employee_code']) ?></span></td>
          <td class="small"><?= e($r['type_name']) ?> <span class="badge bg-<?= $r['is_paid']?'success':'secondary' ?>"><?= $r['is_paid']?'paid':'unpaid' ?></span></td>
          <td class="small"><?= e(date_id($r['start_date'])) ?> – <?= e(date_id($r['end_date'])) ?></td>
          <td><?= (float)$r['days_count'] ?></td>
          <td class="small" style="max-width:170px"><?= e($r['purpose']) ?> <?= $r['attachment_path'] ? '<a href="'.e($r['attachment_path']).'" target="_blank"><i class="bi bi-paperclip"></i></a>' : '' ?></td>
          <td><?= status_badge($r['status']) ?></td>
          <td style="min-width:240px"><?= appr_form('leave', (int)$r['id']) ?></td>
        </tr><?php endforeach; if (!$lvRows): ?><tr><td colspan="7" class="text-center text-muted py-4">Antrian kosong.</td></tr><?php endif; ?></tbody>
      </table>
    </div></div>
  </div>

  <!-- UBAH JADWAL -->
  <div class="tab-pane fade" id="tabSq">
    <div class="card"><div class="card-body table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Karyawan</th><th>Periode Diminta</th><th>Jam Baru</th><th>Alasan</th><th>Tahap</th><th>Aksi</th></tr></thead>
        <tbody><?php foreach ($sqRows as $r): ?>
        <tr>
          <td><b><?= e($r['full_name']) ?></b><br><span class="small text-muted"><?= e($r['employee_code']) ?></span></td>
          <td class="small"><?= e(date_id($r['requested_start'])) ?> – <?= e(date_id($r['requested_end'])) ?></td>
          <td class="small"><?= time_hm($r['requested_check_in']) ?>–<?= time_hm($r['requested_check_out']) ?> (break <?= time_hm($r['requested_break']) ?>)</td>
          <td class="small" style="max-width:180px"><?= e($r['reason']) ?></td>
          <td><?= status_badge($r['status']) ?></td>
          <td style="min-width:240px"><?= appr_form('sched', (int)$r['id']) ?></td>
        </tr><?php endforeach; if (!$sqRows): ?><tr><td colspan="6" class="text-center text-muted py-4">Antrian kosong.</td></tr><?php endif; ?></tbody>
      </table>
    </div></div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
