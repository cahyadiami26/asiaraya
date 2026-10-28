<?php
/**
 * Unit Organisasi: Logo/Branch/Department/Position + Format ID Karyawan (HR).
 */
$page_title = 'Unit Organisasi'; $active_menu = 'organization';
require_once __DIR__ . '/includes/header.php';
require_role('HR');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = $_POST['act'] ?? '';
    $back = 'organization.php#' . ($act === 'idformat' ? 'idfmt' : strtolower(explode('_', $act)[1] ?? ''));

    if ($act === 'save_branch') {
        $id = (int)($_POST['id'] ?? 0);
        $d = [trim($_POST['code']), trim($_POST['name']), trim($_POST['address']), trim($_POST['city']), trim($_POST['phone']),
              $_POST['latitude'] ?: null, $_POST['longitude'] ?: null, (int)$_POST['radius_meter'], isset($_POST['is_active']) ? 1 : 0];
        if ($id) exec_sql('UPDATE branches SET code=?,name=?,address=?,city=?,phone=?,latitude=?,longitude=?,radius_meter=?,is_active=? WHERE id=?', array_merge($d, [$id]));
        else exec_sql('INSERT INTO branches (code,name,address,city,phone,latitude,longitude,radius_meter,is_active) VALUES (?,?,?,?,?,?,?,?,?)', $d);
        flash('success', 'Branch disimpan.');
    } elseif ($act === 'del_branch') { exec_sql('DELETE FROM branches WHERE id=?', [(int)$_POST['id']]); flash('success', 'Branch dihapus.'); }
    elseif ($act === 'save_dept') {
        $id = (int)($_POST['id'] ?? 0);
        $d = [trim($_POST['name']), $_POST['branch_id'] ?: null, isset($_POST['is_active']) ? 1 : 0];
        if ($id) exec_sql('UPDATE departments SET name=?,branch_id=?,is_active=? WHERE id=?', array_merge($d, [$id]));
        else exec_sql('INSERT INTO departments (name,branch_id,is_active) VALUES (?,?,?)', $d);
        flash('success', 'Departemen disimpan.');
    } elseif ($act === 'del_dept') { exec_sql('DELETE FROM departments WHERE id=?', [(int)$_POST['id']]); flash('success', 'Departemen dihapus.'); }
    elseif ($act === 'save_pos') {
        $id = (int)($_POST['id'] ?? 0);
        $d = [trim($_POST['name']), $_POST['department_id'] ?: null, isset($_POST['is_active']) ? 1 : 0];
        if ($id) exec_sql('UPDATE positions SET name=?,department_id=?,is_active=? WHERE id=?', array_merge($d, [$id]));
        else exec_sql('INSERT INTO positions (name,department_id,is_active) VALUES (?,?,?)', $d);
        flash('success', 'Posisi disimpan.');
    } elseif ($act === 'del_pos') { exec_sql('DELETE FROM positions WHERE id=?', [(int)$_POST['id']]); flash('success', 'Posisi dihapus.'); }
    elseif ($act === 'idformat') {
        $len = max(2, min(8, (int)$_POST['number_length']));
        $sample = trim($_POST['prefix']) . ($_POST['year_part'] ? trim($_POST['separator']) . date('Y') : '') . trim($_POST['separator']) . str_pad((string)(int)$_POST['next_number'], $len, '0', STR_PAD_LEFT);
        exec_sql('UPDATE id_formats SET prefix=?,separator=?,year_part=?,number_length=?,next_number=?,sample=? WHERE id=1',
            [trim($_POST['prefix']), trim($_POST['separator']), isset($_POST['year_part']) ? 1 : 0, $len, (int)$_POST['next_number'], $sample]);
        flash('success', 'Format ID karyawan diperbarui. Contoh: ' . $sample);
    }
    redirect($back);
}

$branches = all('SELECT * FROM branches ORDER BY name');
$depts = all('SELECT d.*, b.name branch_name FROM departments d LEFT JOIN branches b ON b.id=d.branch_id ORDER BY d.name');
$positions = all('SELECT p.*, d.name dept_name FROM positions p LEFT JOIN departments d ON d.id=p.department_id ORDER BY p.name');
$fmt = one('SELECT * FROM id_formats WHERE id=1');
?>
<h1 class="page-title mb-3"><i class="bi bi-diagram-3 me-2"></i>Unit Organisasi &amp; Format ID</h1>

<div class="row g-3">
<!-- ================= BRANCHES ================= -->
<div class="col-lg-6" id="branch">
  <div class="card h-100">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-bank2 me-1"></i>Branch / Kantor</span>
      <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#mBranch" onclick="fillBranch(null)">+ Tambah</button>
    </div>
    <div class="card-body p-0 table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead><tr><th>Kode</th><th>Nama</th><th>Kota</th><th>GPS / Radius</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($branches as $b): ?>
        <tr>
          <td><span class="badge bg-dark"><?= e($b['code']) ?></span></td>
          <td class="fw-semibold"><?= e($b['name']) ?><div class="small text-muted"><?= e($b['address']) ?></div></td>
          <td><?= e($b['city']) ?></td>
          <td class="small"><?= $b['latitude'] !== null ? e(substr($b['latitude'],0,9)) . ', ' . e(substr($b['longitude'],0,9)) : '<span class="text-danger">belum diatur</span>' ?><br><span class="text-muted">⌀ <?= (int)$b['radius_meter'] ?> m</span></td>
          <td><?= $b['is_active'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>' ?></td>
          <td class="text-nowrap">
            <button class="btn btn-sm btn-outline-secondary" onclick='fillBranch(<?= json_encode($b, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline" data-confirm="Hapus branch ini?">
              <?= csrf_field() ?><input type="hidden" name="act" value="del_branch"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ================= FORMAT ID ================= -->
<div class="col-lg-6" id="idfmt">
  <div class="card">
    <div class="card-header"><i class="bi bi-hash me-1"></i>Format ID Karyawan</div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <?= csrf_field() ?><input type="hidden" name="act" value="idformat">
        <div class="col-4"><label class="form-label small">Prefix</label><input class="form-control" name="prefix" value="<?= e($fmt['prefix']) ?>" required></div>
        <div class="col-4"><label class="form-label small">Pemisah</label><input class="form-control" name="separator" value="<?= e($fmt['separator']) ?>"></div>
        <div class="col-4"><label class="form-label small">Panjang Nomor</label><input type="number" min="2" max="8" class="form-control" name="number_length" value="<?= (int)$fmt['number_length'] ?>"></div>
        <div class="col-4"><label class="form-label small">Nomor Berikutnya</label><input type="number" class="form-control" name="next_number" value="<?= (int)$fmt['next_number'] ?>"></div>
        <div class="col-4 d-flex align-items-end"><div class="form-check">
          <input class="form-check-input" type="checkbox" name="year_part" id="yp" <?= $fmt['year_part'] ? 'checked' : '' ?>>
          <label class="form-check-label small" for="yp">Sertakan Tahun</label></div></div>
        <div class="col-4 d-flex align-items-end"><div class="text-primary fw-bold fs-6">Contoh:<br><code><?= e(preview_employee_code()) ?></code></div></div>
        <div class="col-12"><button class="btn btn-primary btn-sm">Simpan Format</button></div>
      </form>
    </div>
  </div>

  <!-- ================= DEPARTMENTS ================= -->
  <div class="card mt-3" id="department">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-people me-1"></i>Departemen</span>
      <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#mDept" onclick="fillDept(null)">+ Tambah</button>
    </div>
    <div class="card-body p-0 table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead><tr><th>Nama</th><th>Branch</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($depts as $d): ?>
        <tr><td class="fw-semibold"><?= e($d['name']) ?></td><td><?= e($d['branch_name'] ?? '-') ?></td>
          <td><?= $d['is_active'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>' ?></td>
          <td class="text-nowrap">
            <button class="btn btn-sm btn-outline-secondary" onclick='fillDept(<?= json_encode($d, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline" data-confirm="Hapus departemen ini?"><?= csrf_field() ?><input type="hidden" name="act" value="del_dept"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
          </td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ================= POSITIONS ================= -->
<div class="col-12" id="position">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-briefcase me-1"></i>Position / Jabatan</span>
      <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#mPos" onclick="fillPos(null)">+ Tambah</button>
    </div>
    <div class="card-body p-0 table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead><tr><th>Nama Jabatan</th><th>Departemen</th><th>Jml Karyawan</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($positions as $p): $n = (int)val('SELECT COUNT(*) FROM employees WHERE position_id=? AND flag="active"', [$p['id']]); ?>
        <tr><td class="fw-semibold"><?= e($p['name']) ?></td><td><?= e($p['dept_name'] ?? '-') ?></td>
          <td><span class="badge bg-info text-dark"><?= $n ?> orang</span></td>
          <td><?= $p['is_active'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>' ?></td>
          <td class="text-nowrap" style="width:110px">
            <button class="btn btn-sm btn-outline-secondary" onclick='fillPos(<?= json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline" data-confirm="Hapus posisi ini?"><?= csrf_field() ?><input type="hidden" name="act" value="del_pos"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
          </td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>

<!-- ===== Modals ===== -->
<div class="modal fade" id="mBranch"><div class="modal-content"><form method="post" class="modal-body">
  <?= csrf_field() ?><input type="hidden" name="act" value="save_branch"><input type="hidden" name="id" id="b_id" value="0">
  <h5 class="mb-3">Branch</h5>
  <div class="row g-3">
    <div class="col-4"><label class="form-label small">Kode</label><input class="form-control" name="code" id="b_code" required></div>
    <div class="col-8"><label class="form-label small">Nama</label><input class="form-control" name="name" id="b_name" required></div>
    <div class="col-12"><label class="form-label small">Alamat</label><input class="form-control" name="address" id="b_addr"></div>
    <div class="col-6"><label class="form-label small">Kota</label><input class="form-control" name="city" id="b_city"></div>
    <div class="col-6"><label class="form-label small">Telepon</label><input class="form-control" name="phone" id="b_phone"></div>
    <div class="col-4"><label class="form-label small">Latitude</label><input class="form-control" name="latitude" id="b_lat" step="any"></div>
    <div class="col-4"><label class="form-label small">Longitude</label><input class="form-control" name="longitude" id="b_lng" step="any"></div>
    <div class="col-4"><label class="form-label small">Radius (m)</label><input type="number" class="form-control" name="radius_meter" id="b_rad" value="300"></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="b_act" checked><label class="form-check-label small" for="b_act">Aktif</label></div></div>
  </div>
  <div class="text-end mt-3"><button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button> <button class="btn btn-primary btn-sm">Simpan</button></div>
</form></div></div>

<div class="modal fade" id="mDept"><div class="modal-content"><form method="post" class="modal-body">
  <?= csrf_field() ?><input type="hidden" name="act" value="save_dept"><input type="hidden" name="id" id="d_id" value="0">
  <h5 class="mb-3">Departemen</h5>
  <div class="row g-3">
    <div class="col-12"><label class="form-label small">Nama</label><input class="form-control" name="name" id="d_name" required></div>
    <div class="col-12"><label class="form-label small">Branch</label><select class="form-select" name="branch_id" id="d_branch"><option value="">-</option>
      <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="d_act" checked><label class="form-check-label small" for="d_act">Aktif</label></div></div>
  </div>
  <div class="text-end mt-3"><button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button> <button class="btn btn-primary btn-sm">Simpan</button></div>
</form></div></div>

<div class="modal fade" id="mPos"><div class="modal-content"><form method="post" class="modal-body">
  <?= csrf_field() ?><input type="hidden" name="act" value="save_pos"><input type="hidden" name="id" id="p_id" value="0">
  <h5 class="mb-3">Position</h5>
  <div class="row g-3">
    <div class="col-12"><label class="form-label small">Nama Jabatan</label><input class="form-control" name="name" id="p_name" required></div>
    <div class="col-12"><label class="form-label small">Departemen</label><select class="form-select" name="department_id" id="p_dept"><option value="">-</option>
      <?php foreach ($depts as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="p_act" checked><label class="form-check-label small" for="p_act">Aktif</label></div></div>
  </div>
  <div class="text-end mt-3"><button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button> <button class="btn btn-primary btn-sm">Simpan</button></div>
</form></div></div>

<script>
const setV=(id,val)=>{const el=document.getElementById(id); if(!el)return;
  if(el.type==='checkbox'){el.checked = val===null?true:(String(val)==='1');} else el.value = val ?? '';};
function fillBranch(r){setV('b_id',r?r.id:0);setV('b_code',r?.code);setV('b_name',r?.name);setV('b_addr',r?.address);
  setV('b_city',r?.city);setV('b_phone',r?.phone);setV('b_lat',r?.latitude);setV('b_lng',r?.longitude);
  setV('b_rad',r?r.radius_meter:300);setV('b_act',r?r.is_active:1);}
function fillDept(r){setV('d_id',r?r.id:0);setV('d_name',r?.name);setV('d_branch',r?.branch_id||'');setV('d_act',r?r.is_active:1);}
function fillPos(r){setV('p_id',r?r.id:0);setV('p_name',r?.name);setV('p_dept',r?.department_id||'');setV('p_act',r?r.is_active:1);}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
