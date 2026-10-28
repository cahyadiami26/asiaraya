<?php
/**
 * Data Karyawan - CRUD penuh (HR).
 */
$page_title = 'Data Karyawan'; $active_menu = 'employees';
require_once __DIR__ . '/includes/header.php';
require_role('HR');

/* ---- aksi POST ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = $_POST['act'] ?? '';
    if ($act === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $code = trim($_POST['employee_code'] ?? '');
        $data = [
            trim($_POST['full_name']), trim($_POST['address']), trim($_POST['phone']),
            strtolower(trim($_POST['email'])),
            $_POST['direct_manager_id'] ?: null, $_POST['branch_id'] ?: null,
            $_POST['department_id'] ?: null, $_POST['position_id'] ?: null,
            (float)str_replace(',', '', $_POST['base_salary'] ?? 0),
            $_POST['employment_status'], $_POST['contract_start'] ?: null, $_POST['contract_end'] ?: null,
            $_POST['flag'], $_POST['hire_date'] ?: null,
        ];
        if ($id > 0) {
            if ($code === '') $code = val('SELECT employee_code FROM employees WHERE id=?', [$id]);
            exec_sql('UPDATE employees SET employee_code=?,full_name=?,address=?,phone=?,email=?,direct_manager_id=?,branch_id=?,department_id=?,position_id=?,base_salary=?,employment_status=?,contract_start=?,contract_end=?,flag=?,hire_date=? WHERE id=?',
                array_merge($data, [$code, $id]));
            flash('success', 'Data karyawan diperbarui.');
        } else {
            if ($code === '') $code = generate_employee_code();
            exec_sql('INSERT INTO employees (employee_code,full_name,address,phone,email,direct_manager_id,branch_id,department_id,position_id,base_salary,employment_status,contract_start,contract_end,flag,hire_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                array_merge([$code], $data));
            $newId = (int)last_id();
            // buat user akun default + kuota cuti tahunan
            $uname = strtolower(preg_replace('/[^a-z0-9]/i', '', explode(' ', $data[0])[0])) . $newId;
            exec_sql('INSERT INTO users (employee_id, username, password_hash, email, role) VALUES (?,?,?,?, "EMPLOYEE")',
                [$newId, $uname, password_hash('password', PASSWORD_DEFAULT), $data[3] ?: null]);
            $y = (int)date('Y');
            exec_sql('INSERT IGNORE INTO leave_quotas (employee_id, leave_type_id, year, quota_days) SELECT ?, id, ?, default_quota_days FROM leave_types WHERE is_paid=1 AND default_quota_days>0', [$newId, $y]);
            flash('success', 'Karyawan baru dibuat. ID: <b>' . $code . '</b>, username login: <b>' . $uname . '</b> (password: password).');
            audit('CREATE_EMPLOYEE', $code);
        }
        redirect('employees.php');
    } elseif ($act === 'delete') {
        $id = (int)$_POST['id'];
        exec_sql('UPDATE employees SET flag="resign" WHERE id=?', [$id]);
        exec_sql('UPDATE users SET is_active=0 WHERE employee_id=?', [$id]);
        flash('success', 'Karyawan ditandai RESIGN.');
        redirect('employees.php');
    }
}

$fBranch = $_GET['branch'] ?? ''; $fDept = $_GET['dept'] ?? ''; $fq = trim($_GET['q'] ?? ''); $fFlag = $_GET['flag'] ?? '';
$where = ['1=1']; $params = [];
if ($fBranch) { $where[] = 'e.branch_id=?'; $params[] = $fBranch; }
if ($fDept)   { $where[] = 'e.department_id=?'; $params[] = $fDept; }
if ($fFlag !== '') { $where[] = 'e.flag=?'; $params[] = $fFlag; }
if ($fq) { $where[] = '(e.full_name LIKE ? OR e.employee_code LIKE ? OR e.email LIKE ?)'; $params = array_merge($params, ["%$fq%", "%$fq%", "%$fq%"]); }

$rows = all("SELECT e.*, b.name branch_name, d.name dept_name, p.name pos_name, m.full_name mgr_name
             FROM employees e LEFT JOIN branches b ON b.id=e.branch_id LEFT JOIN departments d ON d.id=e.department_id
             LEFT JOIN positions p ON p.id=e.position_id LEFT JOIN employees m ON m.id=e.direct_manager_id
             WHERE " . implode(' AND ', $where) . " ORDER BY e.full_name", $params);

$branches = all('SELECT * FROM branches WHERE is_active=1 ORDER BY name');
$depts = all('SELECT * FROM departments WHERE is_active=1 ORDER BY name');
$positions = all('SELECT * FROM positions WHERE is_active=1 ORDER BY name');
$managers = all('SELECT id, full_name, employee_code FROM employees WHERE flag="active" ORDER BY full_name');

$edit = null;
if (isset($_GET['edit'])) { $edit = one('SELECT * FROM employees WHERE id=?', [(int)$_GET['edit']]); }
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h1 class="page-title"><i class="bi bi-people me-2"></i>Data Karyawan</h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#empModal" onclick="openEmp()"><i class="bi bi-plus-lg me-1"></i>Tambah Karyawan</button>
</div>

<div class="card mb-3"><div class="card-body py-2">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-3"><label class="form-label small mb-0">Cari</label><input class="form-control form-control-sm" name="q" value="<?= e($fq) ?>" placeholder="nama / id / email"></div>
    <div class="col-md-2"><label class="form-label small mb-0">Branch</label><select class="form-select form-select-sm" name="branch"><option value="">Semua</option>
      <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $fBranch==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label small mb-0">Departemen</label><select class="form-select form-select-sm" name="dept"><option value="">Semua</option>
      <?php foreach ($depts as $d): ?><option value="<?= (int)$d['id'] ?>" <?= $fDept==$d['id']?'selected':'' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label small mb-0">Status Flag</label><select class="form-select form-select-sm" name="flag"><option value="">Semua</option>
      <option value="active" <?= $fFlag==='active'?'selected':'' ?>>Active</option><option value="resign" <?= $fFlag==='resign'?'selected':'' ?>>Resign</option></select></div>
    <div class="col-md-3"><button class="btn btn-sm btn-primary me-1">Filter</button><a class="btn btn-sm btn-outline-secondary" href="employees.php">Reset</a>
      <a class="btn btn-sm btn-outline-dark" href="reports.php?type=employees"><i class="bi bi-printer"></i></a></div>
  </form>
</div></div>

<div class="card"><div class="card-body table-responsive">
<table class="table table-hover align-middle">
<thead><tr><th>ID Karyawan</th><th>Nama</th><th>Kontak</th><th>Manager</th><th>Unit</th><th>Gaji Pokok</th><th>Status</th><th>Kontrak</th><th>Flag</th><th class="no-print"></th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><span class="badge bg-dark"><?= e($r['employee_code']) ?></span></td>
  <td class="fw-semibold"><?= e($r['full_name']) ?></td>
  <td class="small"><?= e($r['phone']) ?><br><span class="text-muted"><?= e($r['email']) ?></span></td>
  <td class="small"><?= e($r['mgr_name'] ?? '-') ?></td>
   <td class="small"><?= e($r['branch_name'] ?? '-') ?><br><span class="text-muted"><?= e($r['dept_name'] ?? '-') ?> · <?= e($r['pos_name'] ?? '-') ?></span></td>
  <td class="text-end"><?= rupiah($r['base_salary']) ?></td>
  <td><span class="badge bg-secondary"><?= e($r['employment_status']) ?></span></td>
  <td class="small"><?= e(date_id($r['contract_start'])) ?><br>s/d <?= e(date_id($r['contract_end'])) ?></td>
  <td><?= status_badge($r['flag']) ?></td>
  <td class="text-nowrap no-print">
    <a class="btn btn-sm btn-outline-primary" title="Detail PDF" href="pdf_employee.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-file-earmark-pdf"></i></a>
    <button class="btn btn-sm btn-outline-secondary" onclick='openEmp(<?= json_encode($r, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
    <?php if ($r['flag']==='active'): ?>
    <form method="post" class="d-inline" data-confirm="Tandai <?= e($r['full_name']) ?> sebagai RESIGN?">
      <?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <button class="btn btn-sm btn-outline-danger" title="Resign"><i class="bi bi-person-dash"></i></button>
    </form><?php endif; ?>
  </td>
</tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="10" class="text-center text-muted py-4">Tidak ada data.</td></tr><?php endif; ?>
</tbody></table>
</div></div>

<!-- Modal form -->
<div class="modal fade" id="empModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
<form method="post" class="modal-body">
  <div class="modal-header border-0 pb-0"><h5 class="modal-title" id="empTitle">Tambah Karyawan</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
  <?= csrf_field() ?><input type="hidden" name="act" value="save"><input type="hidden" name="id" id="f_id" value="0">
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label small">ID Karyawan <span class="text-muted">(kosong = auto-generate)</span></label>
      <input class="form-control" name="employee_code" id="f_code" value="<?= e(preview_employee_code()) ?>"></div>
    <div class="col-md-8"><label class="form-label small">Nama Lengkap *</label><input class="form-control" name="full_name" id="f_name" required></div>
    <div class="col-12"><label class="form-label small">Alamat</label><textarea class="form-control" name="address" id="f_addr" rows="2"></textarea></div>
    <div class="col-md-4"><label class="form-label small">No. HP</label><input class="form-control" name="phone" id="f_phone"></div>
    <div class="col-md-8"><label class="form-label small">Email</label><input type="email" class="form-control" name="email" id="f_email"></div>
    <div class="col-md-4"><label class="form-label small">Direct Manager</label><select class="form-select" name="direct_manager_id" id="f_mgr"><option value="">- Tidak ada -</option>
      <?php foreach ($managers as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['full_name']) ?> (<?= e($m['employee_code']) ?>)</option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label small">Branch</label><select class="form-select" name="branch_id" id="f_branch"><option value="">-</option>
      <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label small">Departemen</label><select class="form-select" name="department_id" id="f_dept"><option value="">-</option>
      <?php foreach ($depts as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label small">Position</label><select class="form-select" name="position_id" id="f_pos"><option value="">-</option>
      <?php foreach ($positions as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label small">Gaji Pokok (Rp)</label><input class="form-control" name="base_salary" id="f_salary" inputmode="numeric" value="0"></div>
    <div class="col-md-4"><label class="form-label small">Status Karyawan</label><select class="form-select" name="employment_status" id="f_estat">
      <option>Permanent</option><option>Contract</option><option>Probation</option><option>Outsourcing</option><option>Intern</option></select></div>
    <div class="col-md-3"><label class="form-label small">Awal Kontrak</label><input type="date" class="form-control" name="contract_start" id="f_cs"></div>
    <div class="col-md-3"><label class="form-label small">Akhir Kontrak</label><input type="date" class="form-control" name="contract_end" id="f_ce"></div>
    <div class="col-md-3"><label class="form-label small">Tanggal Masuk</label><input type="date" class="form-control" name="hire_date" id="f_hd"></div>
    <div class="col-md-3"><label class="form-label small">Flag</label><select class="form-select" name="flag" id="f_flag"><option value="active">Active</option><option value="resign">Resign</option></select></div>
  </div>
  </div>
  <div class="modal-footer border-0 pt-0"><button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
  <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button></div>
</form>
</div></div></div>

<script>
function openEmp(r){
  const v = (id,val)=>document.getElementById(id).value = val ?? '';
  document.getElementById('empTitle').textContent = r ? 'Edit Karyawan' : 'Tambah Karyawan';
  const map = {f_id:'id',f_code:'employee_code',f_name:'full_name',f_addr:'address',f_phone:'phone',f_email:'email',
    f_mgr:'direct_manager_id',f_branch:'branch_id',f_dept:'department_id',f_pos:'position_id',f_salary:'base_salary',
    f_estat:'employment_status',f_cs:'contract_start',f_ce:'contract_end',f_flag:'flag',f_hd:'hire_date'};
  for (const [k,col] of Object.entries(map)) {
    let x = r ? r[col] : null;
    if (x === null) x = (k==='f_id'?0:(k==='f_code'?'<?= e(preview_employee_code()) ?>':(k==='f_salary'?0:'')));
    if (typeof x === 'string') x = x.split(' ')[0];
    v(k, x);
  }
}
<?php if ($edit): ?>document.addEventListener('DOMContentLoaded',()=>{ new bootstrap.Modal(document.getElementById('empModal')).show(); openEmp(<?= json_encode($edit, JSON_HEX_APOS|JSON_HEX_QUOT) ?>); });<?php endif; ?>
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
