<?php
/**
 * Assign Komponen Gaji ke Karyawan (HR): pilih karyawan -> centang komponen,
 * opsional override nominal per karyawan.
 */
$page_title = 'Assign Komponen'; $active_menu = 'assign_comp';
require_once __DIR__ . '/includes/header.php';
require_role('HR');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = $_POST['act'] ?? '';

    if ($act === 'save_assign') {
        $empId = (int)$_POST['employee_id'];
        $emp = one('SELECT id, full_name FROM employees WHERE id=?', [$empId]);
        if (!$emp) exit('Karyawan tidak ditemukan.');

        $picked = array_map('intval', $_POST['component_ids'] ?? []);
        $all = all('SELECT id FROM payroll_components WHERE is_active=1');
        exec_sql('DELETE FROM employee_components WHERE employee_id=?', [$empId]);
        foreach ($all as $c) {
            $cid = (int)$c['id'];
            if (!in_array($cid, $picked, true)) continue;
            $ovRaw = trim($_POST['override_' . $cid] ?? '');
            $ov = $ovRaw === '' ? null : (float)$ovRaw;
            exec_sql('INSERT INTO employee_components (employee_id, component_id, override_amount) VALUES (?,?,?)',
                     [$empId, $cid, $ov]);
        }
        audit('COMP_ASSIGN', "{$emp['full_name']}: " . count($picked) . " komponen");
        flash('success', 'Penugasan komponen untuk ' . $emp['full_name'] . ' disimpan (' . count($picked) . ' komponen).');
        redirect('payroll_component_assign.php?employee=' . $empId);
    }

    /* Salin penugasan dari karyawan lain (fitur bantu) */
    if ($act === 'copy_from') {
        $from = (int)$_POST['copy_from'];
        $to   = (int)$_POST['employee_id'];
        if ($from && $to && $from !== $to) {
            exec_sql('DELETE FROM employee_components WHERE employee_id=?', [$to]);
            exec_sql('INSERT INTO employee_components (employee_id, component_id, override_amount)
                      SELECT ?, component_id, override_amount FROM employee_components WHERE employee_id=?', [$to, $from]);
            audit('COMP_COPY', "dari #$from ke #$to");
            flash('success', 'Komponen disalin.');
        }
        redirect('payroll_component_assign.php?employee=' . $to);
    }
}

$employees = all("SELECT e.id, e.full_name, e.employee_code, b.name branch_name
                  FROM employees e LEFT JOIN branches b ON b.id=e.branch_id
                  WHERE e.flag='active' ORDER BY b.name, e.full_name");
$components = all('SELECT * FROM payroll_components WHERE is_active=1 ORDER BY type, name');

$selId = (int)($_GET['employee'] ?? ($employees[0]['id'] ?? 0));
$selEmp = $selId ? one('SELECT e.*, b.name branch_name, d.name dept_name, p.name pos_name,
                        (SELECT COUNT(*) FROM employee_components ec WHERE ec.employee_id=e.id) comp_count
                        FROM employees e
                        LEFT JOIN branches b ON b.id=e.branch_id
                        LEFT JOIN departments d ON d.id=e.department_id
                        LEFT JOIN positions p ON p.id=e.position_id WHERE e.id=?', [$selId]) : null;
$assigned = [];
if ($selEmp) {
    foreach (all('SELECT component_id, override_amount FROM employee_components WHERE employee_id=?', [$selEmp['id']]) as $a) {
        $assigned[(int)$a['component_id']] = $a['override_amount'];
    }
}
?>
<h1 class="page-title mb-3"><i class="bi bi-person-plus me-2"></i>Assign Komponen ke Karyawan</h1>

<div class="row g-3">
  <div class="col-lg-3">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold small text-uppercase">Pilih Karyawan</div>
      <div class="list-group list-group-flush list-scroll" style="max-height:520px;overflow:auto">
        <?php foreach ($employees as $m): ?>
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $selId == $m['id'] ? 'active' : '' ?>"
             href="payroll_component_assign.php?employee=<?= (int)$m['id'] ?>">
            <span><small class="d-block"><?= e($m['full_name']) ?></small>
              <span class="small opacity-75"><?= e($m['branch_name'] ?? '-') ?></span></span>
            <?php $cnt = (int)val('SELECT COUNT(*) FROM employee_components WHERE employee_id=?', [$m['id']]); ?>
            <span class="badge rounded-pill <?= $cnt ? 'bg-warning text-dark' : 'bg-secondary' ?>"><?= $cnt ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-9">
    <?php if (!$selEmp): ?>
      <div class="alert alert-secondary">Pilih karyawan terlebih dahulu.</div>
    <?php else: ?>
    <div class="card mb-3">
      <div class="card-body d-flex flex-wrap gap-4 align-items-center">
        <div class="avatar-circle bg-primary text-white fs-4"><?= e(strtoupper(substr($selEmp['full_name'], 0, 1))) ?></div>
        <div>
          <div class="fw-bold fs-5"><?= e($selEmp['full_name']) ?></div>
          <div class="small text-muted"><?= e($selEmp['employee_code']) ?> · <?= e($selEmp['pos_name'] ?? '-') ?> ·
            <?= e($selEmp['dept_name'] ?? '-') ?> · <?= e($selEmp['branch_name'] ?? '-') ?></div>
        </div>
        <div class="ms-auto text-end">
          <div class="small text-muted">Gaji Pokok</div>
          <div class="fw-bold text-primary"><?= rupiah($selEmp['base_salary']) ?></div>
        </div>
      </div>
    </div>

    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="act" value="save_assign">
      <input type="hidden" name="employee_id" value="<?= (int)$selEmp['id'] ?>">
      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <span class="fw-semibold"><i class="bi bi-boxes me-1"></i>Komponen Aktif</span>
          <button class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Simpan Penugasan</button>
        </div>
        <div class="card-body table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th style="width:44px"></th><th>Komponen</th><th>Tipe</th><th>Nilai Default</th><th style="width:220px">Override Nominal (opsional)</th></tr></thead>
            <tbody>
            <?php foreach ($components as $c):
                $isChecked = array_key_exists((int)$c['id'], $assigned);
                $ovVal = $assigned[(int)$c['id']] ?? '';
                $defVal = $c['value_type'] === 'percent'
                    ? rtrim(rtrim(number_format((float)$c['percent_of_basic'], 2, ',', ''), '0'), ',') . '% × ' . rupiah($selEmp['base_salary'])
                        . ' = <b>' . money($selEmp['base_salary'] * (float)$c['percent_of_basic'] / 100) . '</b>'
                    : rupiah($c['amount']);
            ?>
              <tr>
                <td><input class="form-check-input" type="checkbox" name="component_ids[]" value="<?= (int)$c['id'] ?>" <?= $isChecked ? 'checked' : '' ?>></td>
                <td class="fw-semibold"><?= e($c['name']) ?>
                  <div class="small text-muted">
                    <i class="bi <?= !empty($c['include_in_pph']) ? 'bi-percent text-primary' : 'bi-dash text-muted' ?>"></i> PPh
                    <i class="bi <?= !empty($c['include_in_bpjs']) ? 'bi-heart text-info' : 'bi-dash text-muted' ?> ms-1"></i> BPJS
                  </div></td>
                <td><?= $c['type'] === 'benefit'
                        ? '<span class="badge bg-success-subtle text-success">Benefit</span>'
                        : '<span class="badge bg-danger-subtle text-danger">Deduction</span>' ?></td>
                <td class="small"><?= $defVal ?></td>
                <td><input class="form-control form-control-sm" type="number" step="500" min="0"
                           name="override_<?= (int)$c['id'] ?>" value="<?= e((string)$ovVal) ?>" placeholder="pakai default"></td>
              </tr>
            <?php endforeach; if (!$components): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">Belum ada komponen aktif. Buat di menu Komponen Gaji.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </form>

    <?php if (count($employees) > 1): ?>
    <div class="card mt-3 border-0 shadow-none">
      <div class="card-body">
        <form method="post" class="row g-2 align-items-end">
          <?= csrf_field() ?><input type="hidden" name="act" value="copy_from">
          <input type="hidden" name="employee_id" value="<?= (int)$selEmp['id'] ?>">
          <div class="col-md-5">
            <label class="form-label small">Salin penugasan dari karyawan lain</label>
            <select class="form-select form-select-sm" name="copy_from" required>
              <option value="">— pilih sumber —</option>
              <?php foreach ($employees as $m): if ($m['id'] == $selEmp['id']) continue; ?>
                <option value="<?= (int)$m['id'] ?>"><?= e($m['full_name']) ?> (<?= e($m['branch_name'] ?? '-') ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3"><button class="btn btn-sm btn-outline-secondary" onclick="return confirm('Timpa komponen karyawan ini dengan salinan?')">
            <i class="bi bi-clipboard-check me-1"></i>Salin & Timpa</button></div>
        </form>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
