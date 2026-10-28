<?php
/**
 * Cuti: CRUD jenis cuti & jatah (HR), pengajuan karyawan, approval Manager -> HR.
 */
$page_title = 'Cuti'; $active_menu = 'leave';
require_once __DIR__ . '/includes/header.php';

$u = current_user();
$eid = employee_id();
$isHR = is_hr();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = $_POST['act'] ?? '';

    /* ---- HR: CRUD jenis cuti ---- */
    if ($act === 'save_type' && $isHR) {
        $id = (int)($_POST['id'] ?? 0);
        $d = [trim($_POST['name']), isset($_POST['is_paid']) ? 1 : 0, (float)$_POST['default_quota_days'], isset($_POST['requires_attachment']) ? 1 : 0, isset($_POST['is_active']) ? 1 : 0];
        if ($id) exec_sql('UPDATE leave_types SET name=?,is_paid=?,default_quota_days=?,requires_attachment=?,is_active=? WHERE id=?', array_merge($d, [$id]));
        else exec_sql('INSERT INTO leave_types (name,is_paid,default_quota_days,requires_attachment,is_active) VALUES (?,?,?,?,?)', $d);
        flash('success', 'Jenis cuti disimpan.'); redirect('leave.php#types');
    } elseif ($act === 'del_type' && $isHR) {
        exec_sql('DELETE FROM leave_types WHERE id=?', [(int)$_POST['id']]); flash('success', 'Jenis cuti dihapus.'); redirect('leave.php#types');
    }

    /* ---- HR: atur jatah/kuota cuti per karyawan ---- */
    elseif ($act === 'quota' && $isHR) {
        $y = (int)$_POST['year'];
        foreach ((array)$_POST['quota'] as $key => $v) { // key = empId_typeId
            [$e2, $t2] = explode('_', $key);
            exec_sql('INSERT INTO leave_quotas (employee_id,leave_type_id,year,quota_days) VALUES (?,?,?,?)
                      ON DUPLICATE KEY UPDATE quota_days=VALUES(quota_days)', [(int)$e2, (int)$t2, $y, (float)$v]);
        }
        flash('success', 'Jatah cuti diperbarui.'); redirect('leave.php#quota');
    }

    /* ---- Karyawan: ajukan cuti ---- */
    elseif ($act === 'create' && $eid) {
        $sd = $_POST['start_date']; $ed = $_POST['end_date'];
        $days = floor((strtotime($ed) - strtotime($sd)) / 86400) + 1;
        $type = one('SELECT * FROM leave_types WHERE id=?', [(int)$_POST['leave_type_id']]);
        $attach = null;
        if (!empty($_FILES['doc']['name'])) {
            $ext = strtolower(pathinfo($_FILES['doc']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png']) && $_FILES['doc']['size'] < 3 * 1024 * 1024) {
                $attach = 'uploads/attachments/cuti_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                move_uploaded_file($_FILES['doc']['tmp_name'], BASE_PATH . '/' . $attach);
            }
        }
        if ($type['requires_attachment'] && !$attach) {
            flash('error', 'Jenis cuti ini mewajibkan lampiran (surat dokter/surat keterangan).'); redirect('leave.php');
        }
        /* cek kuota utk jenis berbayar yang punya kuota */
        $q = one('SELECT * FROM leave_quotas WHERE employee_id=? AND leave_type_id=? AND year=?', [$eid, $type['id'], (int)date('Y')]);
        if ($q && $type['is_paid'] && (float)$q['quota_days'] > 0 && (float)$q['used_days'] + $days > (float)$q['quota_days']) {
            flash('error', 'Jatah cuti tidak cukup (sisa ' . ((float)$q['quota_days'] - (float)$q['used_days']) . ' hari).');
            redirect('leave.php');
        }
        exec_sql('INSERT INTO leave_requests (employee_id,leave_type_id,start_date,end_date,days_count,purpose,attachment_path) VALUES (?,?,?,?,?,?,?)',
            [$eid, $type['id'], $sd, $ed, $days, trim($_POST['purpose']), $attach]);
        flash('success', 'Pengajuan cuti dikirim ke direct manager Anda.'); redirect('leave.php');
    }

    /* ---- Approval berjenjang ---- */
    elseif (in_array($act, ['approve', 'reject'], true)) {
        $id = (int)$_POST['id'];
        $req = one('SELECT lr.*, lt.is_paid FROM leave_requests lr JOIN leave_types lt ON lt.id=lr.leave_type_id WHERE lr.id=?', [$id]);
        if (!$req) exit('Data tidak ditemukan.');
        $note = trim($_POST['note'] ?? '');
        if ($req['status'] === 'PENDING_MGR') {
            if (!is_manager_of((int)$req['employee_id']) && !$isHR) exit('Anda bukan approver tahap ini.');
            $new = $act === 'approve' ? 'PENDING_HR' : 'REJECTED';
            exec_sql('UPDATE leave_requests SET status=?, manager_note=?, manager_action_at=NOW() WHERE id=?', [$new, $note, $id]);
        } elseif ($req['status'] === 'PENDING_HR') {
            require_role('HR');
            $new = $act === 'approve' ? 'APPROVED' : 'REJECTED';
            exec_sql('UPDATE leave_requests SET status=?, hr_note=?, hr_action_at=NOW() WHERE id=?', [$new, $note, $id]);
            if ($act === 'approve') {
                $y = (int)date('Y', strtotime($req['start_date']));
                exec_sql('INSERT INTO leave_quotas (employee_id,leave_type_id,year,used_days) VALUES (?,?,?,?)
                          ON DUPLICATE KEY UPDATE used_days = used_days + VALUES(used_days)',
                    [$req['employee_id'], $req['leave_type_id'], $y, $req['days_count']]);
            }
        } else exit('Request sudah final.');
        audit('LEAVE_' . strtoupper($act), "#$id");
        flash('success', 'Keputusan tersimpan.'); redirect('leave.php');
    }
}

$types = all('SELECT * FROM leave_types ORDER BY id');
$rows = $isHR
    ? all("SELECT l.*, e.full_name, e.employee_code, lt.name type_name, lt.is_paid FROM leave_requests l JOIN employees e ON e.id=l.employee_id JOIN leave_types lt ON lt.id=l.leave_type_id ORDER BY l.created_at DESC LIMIT 300")
    : all("SELECT l.*, e.full_name, lt.name type_name, lt.is_paid FROM leave_requests l JOIN employees e ON e.id=l.employee_id JOIN leave_types lt ON lt.id=l.leave_type_id WHERE l.employee_id=? ORDER BY l.start_date DESC", [$eid]);
$asMgr = $eid ? all("SELECT l.*, e.full_name, e.employee_code, lt.name type_name FROM leave_requests l JOIN employees e ON e.id=l.employee_id JOIN leave_types lt ON lt.id=l.leave_type_id WHERE e.direct_manager_id=? AND l.status='PENDING_MGR'", [$eid]) : [];
$myQuotas = $eid ? all('SELECT lq.*, lt.name, lt.is_paid FROM leave_quotas lq JOIN leave_types lt ON lt.id=lq.leave_type_id WHERE lq.employee_id=? AND lq.year=?', [$eid, date('Y')]) : [];
?>
<h1 class="page-title mb-3"><i class="bi bi-umbrella me-2"></i>Manajemen Cuti</h1>

<?php if (!$isHR): ?>
<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card"><div class="card-body">
      <h5 class="fw-bold"><i class="bi bi-plus-circle me-1"></i>Pengajuan Cuti Baru</h5>
      <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
        <?= csrf_field() ?><input type="hidden" name="act" value="create">
        <div class="col-md-6"><label class="form-label small">Jenis Cuti *</label><select class="form-select form-select-sm" name="leave_type_id" required>
          <?php foreach ($types as $t) if ($t['is_active']): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?> (<?= $t['is_paid'] ? 'tetap digaji' : 'tidak digaji' ?><?= $t['requires_attachment'] ? ' · wajib lampiran' : '' ?>)</option><?php endif; ?></select></div>
        <div class="col-md-3"><label class="form-label small">Tanggal Mulai *</label><input type="date" class="form-control form-control-sm" name="start_date" required></div>
        <div class="col-md-3"><label class="form-label small">Tanggal Selesai *</label><input type="date" class="form-control form-control-sm" name="end_date" required></div>
        <div class="col-md-8"><label class="form-label small">Keperluan *</label><input class="form-control form-control-sm" name="purpose" placeholder="Alasan/keperluan cuti" required></div>
        <div class="col-md-4"><label class="form-label small">Lampiran (opsional)</label><input type="file" class="form-control form-control-sm" name="doc" accept=".pdf,.jpg,.png"></div>
        <div class="col-12"><button class="btn btn-primary btn-sm">Kirim Pengajuan</button></div>
      </form>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100"><div class="card-header fw-bold"><i class="bi bi-hourglass-split me-1"></i>Jatah Cuti Saya (<?= date('Y') ?>)</div>
      <div class="card-body p-0 table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Jenis</th><th>Kuota</th><th>Pakai</th><th>Sisa</th></tr></thead>
        <tbody><?php foreach ($myQuotas as $q): ?>
          <tr><td class="small"><?= e($q['name']) ?></td><td><?= (float)$q['quota_days'] ?></td>
          <td><?= (float)$q['used_days'] ?></td>
          <td><b class="<?= ((float)$q['quota_days']-(float)$q['used_days'])<=2?'text-danger':'text-success' ?>"><?= (float)$q['quota_days'] - (float)$q['used_days'] ?></b></td></tr>
        <?php endforeach; if (!$myQuotas): ?><tr><td colspan="4" class="text-muted small text-center py-3">Belum ada kuota.</td></tr><?php endif; ?></tbody>
      </table></div>
    </div>
  </div>
</div>

<?php if ($asMgr): ?>
<div class="card mb-3 border-warning">
  <div class="card-header bg-warning-subtle fw-bold"><i class="bi bi-inbox me-1"></i>Menunggu Persetujuan Anda (Direct Manager)</div>
  <div class="card-body p-0 table-responsive"><table class="table table-hover mb-0 align-middle">
    <thead><tr><th>Karyawan</th><th>Jenis</th><th>Tanggal</th><th>Hari</th><th>Keperluan</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach ($asMgr as $r): ?>
    <tr>
      <td><b><?= e($r['full_name']) ?></b><br><span class="small text-muted"><?= e($r['employee_code']) ?></span></td>
      <td class="small"><?= e($r['type_name']) ?></td>
      <td class="small"><?= e(date_id($r['start_date'])) ?> – <?= e(date_id($r['end_date'])) ?></td>
      <td><?= (float)$r['days_count'] ?></td>
      <td class="small"><?= e($r['purpose']) ?> <?= $r['attachment_path'] ? '<a href="'.e($r['attachment_path']).'" target="_blank"><i class="bi bi-paperclip"></i></a>' : '' ?></td>
      <td style="min-width:240px"><form method="post" class="d-flex gap-1">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <input class="form-control form-control-sm" name="note" placeholder="Catatan">
        <button class="btn btn-sm btn-success" name="act" value="approve"><i class="bi bi-check-lg"></i></button>
        <button class="btn btn-sm btn-danger" name="act" value="reject"><i class="bi bi-x-lg"></i></button>
      </form></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ===== Daftar pengajuan ===== -->
<div class="card mb-4">
  <div class="card-header fw-bold"><i class="bi bi-list-ul me-1"></i><?= $isHR ? 'Semua Pengajuan Cuti' : 'Status Pengajuan Cuti Saya' ?></div>
  <div class="card-body p-0 table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>#</th><?= $isHR ? '<th>Karyawan</th>' : '' ?><th>Jenis Cuti</th><th>Tanggal</th><th>Hari</th><th>Keperluan</th><th>Status</th><th>Catatan</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <?php if ($isHR): ?><td><b><?= e($r['full_name']) ?></b><br><span class="small text-muted"><?= e($r['employee_code']) ?></span></td><?php endif; ?>
      <td class="small"><?= e($r['type_name']) ?> <span class="badge bg-<?= $r['is_paid'] ? 'success-subtle text-success' : 'secondary' ?>"><?= $r['is_paid'] ? 'digaji' : 'unpaid' ?></span></td>
      <td class="small"><?= e(date_id($r['start_date'])) ?> – <?= e(date_id($r['end_date'])) ?></td>
      <td><?= (float)$r['days_count'] ?></td>
      <td class="small" style="max-width:170px"><?= e($r['purpose']) ?>
        <?php if ($r['attachment_path']): ?><a href="<?= e($r['attachment_path']) ?>" target="_blank" class="ms-1"><i class="bi bi-paperclip"></i></a><?php endif; ?></td>
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
    <?php endforeach; if (!$rows): ?><tr><td colspan="9" class="text-center text-muted py-4">Belum ada pengajuan cuti.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>

<?php if ($isHR): ?>
<div class="row g-3">
  <!-- ===== CRUD Jenis Cuti ===== -->
  <div class="col-lg-6" id="types">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-tags me-1"></i>Jenis Cuti</span>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#mType" onclick="fillType(null)">+ Tambah</button>
      </div>
      <div class="card-body p-0 table-responsive"><table class="table table-hover mb-0 align-middle">
        <thead><tr><th>Nama</th><th>Gaji</th><th>Jatah Default</th><th>Lampiran</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($types as $t): ?>
        <tr>
          <td class="fw-semibold"><?= e($t['name']) ?></td>
          <td><?= $t['is_paid'] ? '<span class="badge bg-success">Tetap Digaji</span>' : '<span class="badge bg-secondary">Tidak Digaji</span>' ?></td>
          <td><?= (float)$t['default_quota_days'] ?> hr</td>
          <td><?= $t['requires_attachment'] ? '<i class="bi bi-check-lg text-primary"></i>' : '-' ?></td>
          <td><?= $t['is_active'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>' ?></td>
          <td class="text-nowrap">
            <button class="btn btn-sm btn-outline-secondary" onclick='fillType(<?= json_encode($t, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="act" value="del_type"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus jenis cuti?')"><i class="bi bi-trash"></i></button></form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>
  </div>

  <!-- ===== Atur Jatah Cuti ===== -->
  <div class="col-lg-6" id="quota">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-calendar-check me-1"></i>Atur Jatah Cuti per Karyawan</div>
      <div class="card-body">
        <form method="post" class="mb-2 row g-2 align-items-end">
          <?= csrf_field() ?><input type="hidden" name="act" value="quota">
          <div class="col-auto"><label class="form-label small mb-0">Tahun</label><input type="number" name="year" class="form-control form-control-sm" value="<?= (int)($_GET['qyear'] ?? date('Y')) ?>"></div>
          <div class="col-auto"><label class="form-label small mb-0">Branch</label>
            <select class="form-select form-select-sm" name="qbranch" onchange="this.form.submit()">
              <option value="0">Semua</option>
              <?php $qb=(int)($_GET['qbranch']??0); $qy=(int)($_GET['qyear'] ?? date('Y')); foreach (all('SELECT * FROM branches ORDER BY name') as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $qb==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
            </select><input type="hidden" name="qyear" value="<?= $qy ?>"></div>
        </form>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="act" value="quota"><input type="hidden" name="year" value="<?= $qy ?>">
          <div style="max-height:320px;overflow:auto">
          <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Karyawan</th><?php $paidTypes = array_filter($types, fn($t)=>$t['is_paid']); foreach ($paidTypes as $t): ?><th class="small"><?= e($t['name']) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php
            $emps = all(($qb ? 'SELECT id, full_name, employee_code FROM employees WHERE flag="active" AND branch_id=? ORDER BY full_name' : 'SELECT id, full_name, employee_code FROM employees WHERE flag="active" ORDER BY full_name'), $qb ? [$qb] : []);
            foreach ($emps as $em): ?>
            <tr><td class="small"><b><?= e($em['full_name']) ?></b><br><span class="text-muted"><?= e($em['employee_code']) ?></span></td>
              <?php foreach ($paidTypes as $t):
                $qq = one('SELECT * FROM leave_quotas WHERE employee_id=? AND leave_type_id=? AND year=?', [$em['id'], $t['id'], date('Y')]); ?>
                <td><input type="number" step="0.5" min="0" style="width:70px" class="form-control form-control-sm"
                  name="quota[<?= (int)$em['id'] ?>_<?= (int)$t['id'] ?>]" value="<?= $qq ? htmlspecialchars((string)(float)$qq['quota_days']) : (string)(int)$t['default_quota_days'] ?>"></td>
              <?php endforeach; ?></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          </div>
          <button class="btn btn-primary btn-sm mt-2">Simpan Jatah Cuti</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="mType"><div class="modal-content"><form method="post" class="modal-body">
  <?= csrf_field() ?><input type="hidden" name="act" value="save_type"><input type="hidden" name="id" id="t_id" value="0">
  <h5>Jenis Cuti</h5>
  <div class="row g-3">
    <div class="col-12"><label class="form-label small fw-bold">Nama Jenis Cuti *</label><input class="form-control" name="name" id="t_name" required></div>
    <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_paid" id="t_paid" checked>
      <label class="form-check-label small" for="t_paid">Tetap Digaji saat cuti</label></div></div>
    <div class="col-md-6"><label class="form-label small fw-bold">Jatah Default (hari/thn, 0=tanpa kuota)</label><input type="number" class="form-control" name="default_quota_days" id="t_quota" value="12"></div>
    <div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="requires_attachment" id="t_att"><label class="form-check-label small" for="t_att">Wajib lampiran bukti</label></div></div>
    <div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="t_act" checked><label class="form-check-label small" for="t_act">Aktif</label></div></div>
  </div>
  <div class="text-end mt-3"><button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary btn-sm">Simpan</button></div>
</form></div></div>
<script>
function fillType(t){const s=(id,v)=>{const el=document.getElementById(id);
  if(el.type==='checkbox')el.checked = t?!!+t[id.slice(2)]: (id!=='t_att');
  else el.value = t ? t[id.slice(2)] : (id==='t_id'?0:(id==='t_quota'?12:''));};
  ['t_id','t_name','t_paid','t_quota','t_att','t_act'].forEach(i=>s(i));}
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
