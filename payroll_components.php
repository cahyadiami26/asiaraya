<?php
/**
 * Komponen Gaji (HR): CRUD benefit & deduction, tipe nilai fix/persen gaji pokok,
 * flag ikut PPH / BPJS.
 */
$page_title = 'Komponen Gaji'; $active_menu = 'components';
require_once __DIR__ . '/includes/header.php';
require_role('HR');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = $_POST['act'] ?? '';

    if ($act === 'save') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name']);
        $type = $_POST['type'] === 'deduction' ? 'deduction' : 'benefit';
        $vt   = $_POST['value_type'] === 'percent' ? 'percent' : 'fix';
        $amt  = (float)($_POST['amount'] ?? 0);
        $pct  = (float)($_POST['percent_of_basic'] ?? 0);
        $pph  = isset($_POST['include_pph']) ? 1 : 0;
        $bpjs = isset($_POST['include_bpjs']) ? 1 : 0;
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($name === '') { flash('error', 'Nama komponen wajib diisi.'); redirect('payroll_components.php'); }

        if ($id > 0) {
            exec_sql('UPDATE payroll_components SET name=?, type=?, value_type=?, amount=?, percent_of_basic=?,
                      include_in_pph=?, include_in_bpjs=?, is_active=? WHERE id=?',
                     [$name, $type, $vt, $amt, $pct, $pph, $bpjs, $active, $id]);
            audit('COMPONENT_UPDATE', "#$id $name");
        } else {
            exec_sql('INSERT INTO payroll_components (name,type,value_type,amount,percent_of_basic,include_in_pph,include_in_bpjs,is_active)
                      VALUES (?,?,?,?,?,?,?,?)', [$name, $type, $vt, $amt, $pct, $pph, $bpjs, $active]);
            audit('COMPONENT_CREATE', $name);
        }
        flash('success', 'Komponen tersimpan.');
        redirect('payroll_components.php');
    }

    if ($act === 'delete') {
        $id = (int)$_POST['id'];
        exec_sql('DELETE FROM payroll_components WHERE id=?', [$id]);
        audit('COMPONENT_DELETE', "#$id");
        flash('success', 'Komponen dihapus.');
        redirect('payroll_components.php');
    }
}

$edit = null;
if (isset($_GET['edit'])) { $edit = one('SELECT * FROM payroll_components WHERE id=?', [(int)$_GET['edit']]); }

$rows = all('SELECT c.*, (SELECT COUNT(*) FROM employee_components ec WHERE ec.component_id=c.id) assigned_count
             FROM payroll_components c ORDER BY c.type, c.name');
?>
<h1 class="page-title mb-3"><i class="bi bi-list-columns me-2"></i>Komponen Gaji</h1>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card sticky-lg-top" style="top:12px">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-<?= $edit ? 'pencil-square' : 'plus-circle' ?> me-1"></i>
        <?= $edit ? 'Edit Komponen' : 'Tambah Komponen' ?>
      </div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="act" value="save">
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div class="mb-2"><label class="form-label small">Nama Komponen *</label>
            <input class="form-control form-control-sm" name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="cth: Tunjangan Makan"></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small">Tipe</label>
              <select class="form-select form-select-sm" name="type" id="cType">
                <option value="benefit" <?= ($edit['type'] ?? '') === 'benefit' ? 'selected' : '' ?>>Benefit (+)</option>
                <option value="deduction" <?= ($edit['type'] ?? '') === 'deduction' ? 'selected' : '' ?>>Deduction (−)</option>
              </select></div>
            <div class="col-6"><label class="form-label small">Nilai</label>
              <select class="form-select form-select-sm" name="value_type" id="cVt">
                <option value="fix" <?= ($edit['value_type'] ?? 'fix') === 'fix' ? 'selected' : '' ?>>Fix Value (Rp)</option>
                <option value="percent" <?= ($edit['value_type'] ?? '') === 'percent' ? 'selected' : '' ?>>% Gaji Pokok</option>
              </select></div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6" id="boxFix"><label class="form-label small">Nominal (Rp)</label>
              <input class="form-control form-control-sm" name="amount" type="number" step="500" min="0" value="<?= e($edit['amount'] ?? 0) ?>"></div>
            <div class="col-6" id="boxPct"><label class="form-label small">% Gaji Pokok</label>
              <input class="form-control form-control-sm" name="percent_of_basic" type="number" step="0.5" min="0" max="100" value="<?= e($edit['percent_of_basic'] ?? 0) ?>"></div>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="include_pph" id="fPph" <?= !empty($edit['include_in_pph']) ? 'checked' : '' ?>>
            <label class="form-check-label small" for="fPph">Objek PPh</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="include_bpjs" id="fBpjs" <?= !empty($edit['include_in_bpjs']) ? 'checked' : '' ?>>
            <label class="form-check-label small" for="fBpjs">Base BPJS</label>
          </div>
          <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" name="is_active" id="fAct" <?= (!$edit || !empty($edit['is_active'])) ? 'checked' : '' ?>>
            <label class="form-check-label small" for="fAct">Aktif</label>
          </div>
          <div class="d-grid gap-2 mt-3">
            <button class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Simpan</button>
            <?php if ($edit): ?><a href="payroll_components.php" class="btn btn-outline-secondary btn-sm">Batal</a><?php endif; ?>
          </div>
        </form>
      </div>
      <div class="card-footer small text-muted">
        <i class="bi bi-info-circle me-1"></i>"Objek PPh" = tunjangan ikut dihitung sebagai penghasilan kena pajak.
        "Base BPJS" = menambah upah dasar iuran BPJS.
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card"><div class="card-body table-responsive">
      <input class="form-control form-control-sm mb-2 no-print" placeholder="Cari komponen..." data-table-filter="#tblComp" style="max-width:280px">
      <table class="table table-hover align-middle mb-0" id="tblComp">
        <thead><tr><th>Nama</th><th>Tipe</th><th>Nilai</th><th>PPh/BPJS</th><th>Dipakai</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="fw-semibold"><?= e($r['name']) ?></td>
            <td><?= $r['type'] === 'benefit'
                    ? '<span class="badge bg-success-subtle text-success">Benefit</span>'
                    : '<span class="badge bg-danger-subtle text-danger">Deduction</span>' ?></td>
            <td class="small"><?= $r['value_type'] === 'percent'
                    ? rtrim(rtrim(number_format((float)$r['percent_of_basic'], 2, ',', ''), '0'), ',') . '% gaji pokok'
                    : rupiah($r['amount']) ?></td>
            <td class="small">
              <i class="bi <?= !empty($r['include_in_pph']) ? 'bi-check-circle text-primary' : 'bi-dash text-muted' ?>" title="Objek PPh"></i>
              <i class="bi <?= !empty($r['include_in_bpjs']) ? 'bi-check-circle text-info' : 'bi-dash text-muted' ?> ms-1" title="Base BPJS"></i>
            </td>
            <td><span class="badge bg-secondary"><?= (int)$r['assigned_count'] ?> karyawan</span></td>
            <td><?= !empty($r['is_active']) ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>' ?></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="payroll_components.php?edit=<?= (int)$r['id'] ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" class="d-inline" onsubmit="return confirm('Hapus komponen <?= e($r['name']) ?>? Penugasan ke karyawan ikut terhapus.')">
                <?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; if (!$rows): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Belum ada komponen.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div>

<script>
(function(){
  const vt = document.getElementById('cVt');
  const toggle = () => {
    const pct = vt.value === 'percent';
    document.getElementById('boxPct').classList.toggle('d-none', !pct);
    document.getElementById('boxFix').classList.toggle('d-none', pct);
  };
  vt.addEventListener('change', toggle); toggle();
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
