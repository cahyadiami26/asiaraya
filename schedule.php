<?php
/**
 * Jadwal Kerja / Shift.
 * - HR   : CRUD shift & jadwal, assign branch & karyawan (massal), lihat per branch/karyawan.
 * - Karyawan: melihat jadwal miliknya + request ubah jadwal (link).
 * Alur persetujuan perubahan jadwal: direct manager -> HR (di schedule_requests.php).
 */
$page_title = 'Jadwal Kerja'; $active_menu = 'schedule';
require_once __DIR__ . '/includes/header.php';

$isHR = is_hr();

/* ---------- Aksi POST (khusus HR) ---------- */
if ($isHR && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = $_POST['act'] ?? '';
    if ($act === 'save_shift') {
        $id = (int)($_POST['id'] ?? 0);
        $d = [trim($_POST['name']), $_POST['start_time'], $_POST['end_time'], $_POST['break_time'] ?: '01:00:00', isset($_POST['is_active']) ? 1 : 0];
        if ($id) exec_sql('UPDATE shifts SET name=?,start_time=?,end_time=?,break_time=?,is_active=? WHERE id=?', array_merge($d, [$id]));
        else exec_sql('INSERT INTO shifts (name,start_time,end_time,break_time,is_active) VALUES (?,?,?,?,?)', $d);
        flash('success', 'Shift disimpan.');
    } elseif ($act === 'del_shift') {
        exec_sql('DELETE FROM shifts WHERE id=?', [(int)$_POST['id']]); flash('success', 'Shift dihapus.');
    } elseif ($act === 'assign') {
        $shiftId = (int)$_POST['shift_id'];
        $branchId = (int)$_POST['branch_id'];
        $sd = $_POST['start_date']; $ed = $_POST['end_date'];
        $cin = $_POST['check_in_time']; $cout = $_POST['check_out_time']; $brk = $_POST['break_time'] ?: '01:00:00';
        $empIds = array_map('intval', (array)($_POST['employee_ids'] ?? []));
        if (!$empIds && !empty($_POST['all_branch'])) {
            $empIds = array_column(all('SELECT id FROM employees WHERE branch_id=? AND flag="active"', [$branchId]), 'id');
        }
        $n = 0;
        foreach ($empIds as $eid) {
            exec_sql('DELETE FROM schedules WHERE employee_id=? AND start_date<=? AND end_date>=?', [$eid, $ed, $sd]);
            exec_sql('INSERT INTO schedules (shift_id,branch_id,employee_id,start_date,end_date,check_in_time,check_out_time,break_time,created_by) VALUES (?,?,?,?,?,?,?,?,?)',
                [$shiftId, $branchId, $eid, $sd, $ed, $cin, $cout, $brk, current_user()['id']]);
            $n++;
        }
        audit('ASSIGN_SCHEDULE', "$n karyawan, periode $sd s/d $ed");
        flash('success', "Jadwal di-assign ke <b>$n</b> karyawan.");
    } elseif ($act === 'del_sched') {
        exec_sql('DELETE FROM schedules WHERE id=?', [(int)$_POST['id']]); flash('success', 'Jadwal dihapus.');
    }
    redirect('schedule.php');
}

$branches = all('SELECT * FROM branches WHERE is_active=1 ORDER BY name');
$shifts = all('SELECT * FROM shifts WHERE is_active=1 ORDER BY name');
$fBranch = (int)($_GET['branch'] ?? 0);
$fEmp = (int)($_GET['emp'] ?? 0);
$week = $_GET['week'] ?? date('Y-m-d'); // hari acuan minggu
$monday = date('Y-m-d', strtotime($week . ' -' . (date('N', strtotime($week)) - 1) . ' days'));
$sunday = date('Y-m-d', strtotime($monday . ' +6 days'));

/* ---------- Data jadwal seminggu ---------- */
if ($isHR) {
    $sql = 'SELECT s.*, sh.name shift_name, e.full_name, e.employee_code, b.name branch_name
            FROM schedules s JOIN shifts sh ON sh.id=s.shift_id JOIN employees e ON e.id=s.employee_id JOIN branches b ON b.id=s.branch_id
            WHERE s.start_date<=? AND s.end_date>=?';
    $p = [$sunday, $monday];
    if ($fBranch) { $sql .= ' AND s.branch_id=?'; $p[] = $fBranch; }
    if ($fEmp)    { $sql .= ' AND s.employee_id=?'; $p[] = $fEmp; }
    $scheds = all($sql . ' ORDER BY e.full_name', $p);
} else {
    $eid = employee_id();
    $scheds = $eid ? all('SELECT s.*, sh.name shift_name, e.full_name, e.employee_code, b.name branch_name
            FROM schedules s JOIN shifts sh ON sh.id=s.shift_id JOIN employees e ON e.id=s.employee_id JOIN branches b ON b.id=s.branch_id
            WHERE s.employee_id=? AND s.start_date<=? AND s.end_date>=? ORDER BY s.start_date', [$eid, $sunday, $monday]) : [];
}
$employees = $isHR ? all('SELECT id, full_name, employee_code, branch_id FROM employees WHERE flag="active" ORDER BY full_name') : [];

function day_cell(array $scheds, string $date): string
{
    $out = '';
    foreach ($scheds as $s) {
        if ($date >= $s['start_date'] && $date <= $s['end_date']) {
            $out .= '<span class="badge bg-primary-subtle text-primary border border-primary-subtle d-block mb-1">'
                 . time_hm($s['check_in_time']) . '-' . time_hm($s['check_out_time']) . '</span>'
                 . '<span class="small text-muted d-block">' . e($s['shift_name']) . ' · brk ' . time_hm($s['break_time']) . '</span>';
        }
    }
    return $out ?: '<span class="text-muted small">Libur / -</span>';
}
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h1 class="page-title"><i class="bi bi-calendar-week me-2"></i>Jadwal Kerja / Shift</h1>
  <?php if ($isHR): ?>
    <div>
      <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#mShift"><i class="bi bi-clock me-1"></i>Kelola Shift</button>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mAssign"><i class="bi bi-plus-lg me-1"></i>Assign Jadwal</button>
    </div>
  <?php else: ?>
    <a href="schedule_requests.php" class="btn btn-warning btn-sm"><i class="bi bi-arrow-left-right me-1"></i>Request Ubah Jadwal</a>
  <?php endif; ?>
</div>

<!-- Navigasi minggu + filter -->
<div class="card mb-3"><div class="card-body py-2">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-auto">
      <a class="btn btn-sm btn-outline-secondary" href="?week=<?= date('Y-m-d', strtotime($monday.' -7 days')) ?>&branch=<?= $fBranch ?>&emp=<?= $fEmp ?>"><i class="bi bi-chevron-left"></i></a>
      <a class="btn btn-sm btn-outline-primary" href="?week=<?= date('Y-m-d') ?>">Hari Ini</a>
      <a class="btn btn-sm btn-outline-secondary" href="?week=<?= date('Y-m-d', strtotime($monday.' +7 days')) ?>&branch=<?= $fBranch ?>&emp=<?= $fEmp ?>"><i class="bi bi-chevron-right"></i></a>
      <span class="fw-bold ms-2"><?= e(date_id($monday)) ?> – <?= e(date_id($sunday)) ?></span>
    </div>
    <?php if ($isHR): ?>
    <div class="col-md-2"><label class="form-label small mb-0">View per Branch</label>
      <select class="form-select form-select-sm" name="branch"><option value="0">Semua</option>
      <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $fBranch==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label small mb-0">View per Karyawan</label>
      <select class="form-select form-select-sm" name="emp"><option value="0">Semua</option>
      <?php foreach ($employees as $m): ?><option value="<?= (int)$m['id'] ?>" <?= $fEmp==$m['id']?'selected':'' ?>><?= e($m['full_name']) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="col-md-1"><input type="hidden" name="week" value="<?= e($week) ?>"><button class="btn btn-sm btn-primary w-100">Terapkan</button></div>
  </form>
</div></div>

<div class="card"><div class="card-body table-responsive">
<table class="table table-bordered align-top mb-0" style="min-width:900px">
<thead><tr>
  <?php if ($isHR && !$fEmp || $isHR): ?><th style="min-width:190px">Karyawan</th><?php endif; ?>
  <?php for ($i = 0; $i < 7; $i++): $d = date('Y-m-d', strtotime("$monday +$i days")); $hari=['','Sen','Sel','Rab','Kam','Jum','Sab','Min'][$i+1]; ?>
  <th class="<?= $d===date('Y-m-d')?'table-primary':'' ?>" style="min-width:120px"><?= $hari ?>, <?= e(date_id($d)) ?></th>
  <?php endfor; ?>
</tr></thead>
<tbody>
<?php
$byEmp = [];
foreach ($scheds as $s) $byEmp[$s['employee_id']][] = $s;
if (!$byEmp): ?>
<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada jadwal pada minggu ini.</td></tr>
<?php else: foreach ($byEmp as $eid => $list): $first = $list[0]; ?>
<tr>
  <td>
    <div class="fw-semibold"><?= e($first['full_name']) ?></div>
    <div class="small text-muted"><?= e($first['employee_code']) ?> · 📍<?= e($first['branch_name']) ?></div>
    <?php if ($isHR): ?>
    <form method="post" class="d-inline mt-1" data-confirm="Hapus seluruh jadwal baris ini pada rentang ini?">
      <?= csrf_field() ?><input type="hidden" name="act" value="del_sched"><input type="hidden" name="id" value="<?= (int)$first['id'] ?>">
      <button class="btn btn-sm btn-outline-danger py-0 px-1" title="Hapus jadwal"><i class="bi bi-trash small"></i></button>
    </form><?php endif; ?>
  </td>
  <?php for ($i = 0; $i < 7; $i++): $d = date('Y-m-d', strtotime("$monday +$i days")); ?>
  <td class="<?= $d===date('Y-m-d')?'table-primary':'' ?>"><?= day_cell($list, $d) ?></td>
  <?php endfor; ?>
</tr>
<?php endforeach; endif; ?>
</tbody></table>
</div></div>

<?php if ($isHR): ?>
<!-- ===== Modal Kelola Shift ===== -->
<div class="modal fade" id="mShift"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-body">
  <h5><i class="bi bi-clock me-1"></i>Master Shift</h5>
  <table class="table table-sm align-middle">
    <thead><tr><th>Nama</th><th>Mulai</th><th>Selesai</th><th>Istirahat</th><th></th></tr></thead>
    <tbody>
    <?php foreach (all('SELECT * FROM shifts ORDER BY id') as $sh): ?>
    <tr>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="save_shift"><input type="hidden" name="id" value="<?= (int)$sh['id'] ?>">
      <td><input class="form-control form-control-sm" name="name" value="<?= e($sh['name']) ?>"></td>
      <td><input type="time" class="form-control form-control-sm" name="start_time" value="<?= substr($sh['start_time'],0,5) ?>"></td>
      <td><input type="time" class="form-control form-control-sm" name="end_time" value="<?= substr($sh['end_time'],0,5) ?>"></td>
      <td><input type="time" class="form-control form-control-sm" name="break_time" value="<?= substr($sh['break_time'],0,5) ?>"></td>
      <td class="text-nowrap"><button class="btn btn-sm btn-outline-primary" title="Simpan"><i class="bi bi-check-lg"></i></button></td>
      </form>
      <td><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="del_shift"><input type="hidden" name="id" value="<?= (int)$sh['id'] ?>">
        <button class="btn btn-sm btn-outline-danger" data-confirm="Hapus shift?" onclick="return confirm('Hapus shift ini?')"><i class="bi bi-trash"></i></button></form></td>
    </tr>
    <?php endforeach; ?>
    <tr>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="save_shift"><input type="hidden" name="id" value="0">
      <td><input class="form-control form-control-sm" name="name" placeholder="Shift baru" required></td>
      <td><input type="time" class="form-control form-control-sm" name="start_time" required></td>
      <td><input type="time" class="form-control form-control-sm" name="end_time" required></td>
      <td><input type="time" class="form-control form-control-sm" name="break_time" value="01:00"></td>
      <td><button class="btn btn-sm btn-primary">+ Simpan</button></td>
      </form>
    </tr>
    </tbody>
  </table>
</div></div></div></div>

<!-- ===== Modal Assign Jadwal ===== -->
<div class="modal fade" id="mAssign"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post">
  <?= csrf_field() ?><input type="hidden" name="act" value="assign">
  <div class="modal-header"><h5 class="modal-title"><i class="bi bi-calendar-plus me-1"></i>Assign Jadwal / Shift</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-3">
    <div class="col-md-4"><label class="form-label small fw-bold">Shift</label>
      <select class="form-select" name="shift_id" id="as_shift" onchange="syncShift()" required>
      <?php foreach ($shifts as $s): ?><option value="<?= (int)$s['id'] ?>" data-in="<?= substr($s['start_time'],0,5) ?>" data-out="<?= substr($s['end_time'],0,5) ?>" data-brk="<?= substr($s['break_time'],0,5) ?>"><?= e($s['name']) ?> (<?= time_hm($s['start_time']) ?>–<?= time_hm($s['end_time']) ?>)</option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label small fw-bold">Assign Branch</label>
      <select class="form-select" name="branch_id" id="as_branch" onchange="filterEmp()" required>
      <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label small fw-bold">Tanggal Mulai</label><input type="date" class="form-control" name="start_date" value="<?= e($monday) ?>" required></div>
    <div class="col-md-4"><label class="form-label small fw-bold">Tanggal Akhir</label><input type="date" class="form-control" name="end_date" value="<?= e($sunday) ?>" required></div>
    <div class="col-md-4"><label class="form-label small fw-bold">Jam Check-In</label><input type="time" class="form-control" name="check_in_time" id="as_cin" required></div>
    <div class="col-md-4"><label class="form-label small fw-bold">Jam Check-Out</label><input type="time" class="form-control" name="check_out_time" id="as_cout" required></div>
    <div class="col-md-4"><label class="form-label small fw-bold">Break Time</label><input type="time" class="form-control" name="break_time" id="as_brk" value="01:00"></div>
    <div class="col-md-8"><div class="form-check"><input class="form-check-input" type="checkbox" name="all_branch" id="as_all" onchange="document.getElementById('empList').style.opacity=this.checked?.4:1">
      <label class="form-check-label small" for="as_all">Assign otomatis SEMUA karyawan aktif di branch tsb</label></div></div>
    <div class="col-12"><label class="form-label small fw-bold">Assign Karyawan</label>
      <div id="empList" style="max-height:200px;overflow:auto;border:1px solid #eee;border-radius:10px;padding:.6rem">
      <?php foreach ($employees as $m): ?>
        <label class="form-check form-check-inline"><input class="form-check-input emp-opt" type="checkbox" name="employee_ids[]" value="<?= (int)$m['id'] ?>" data-branch="<?= (int)$m['branch_id'] ?>">
        <span class="form-check-label small"><?= e($m['full_name']) ?> <span class="text-muted">(<?= e($m['employee_code']) ?>)</span></span></label>
      <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan Jadwal</button></div>
</form></div></div></div>
<script>
function syncShift(){const o=document.getElementById('as_shift').selectedOptions[0];
  document.getElementById('as_cin').value=o.dataset.in;document.getElementById('as_cout').value=o.dataset.out;
  document.getElementById('as_brk').value=(o.dataset.brk||'01:00').slice(0,5);}
function filterEmp(){const b=document.getElementById('as_branch').value;
  document.querySelectorAll('.emp-opt').forEach(c=>c.closest('label').style.display=(c.dataset.branch==b)?'':'none');}
document.addEventListener('DOMContentLoaded',()=>{syncShift();filterEmp();});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
