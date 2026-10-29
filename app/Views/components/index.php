<?php
/**
 * Komponen gaji: master komponen, penugasan karyawan, simulasi take-home.
 * @var array $components, $assignments, $filters, $stats, $coverage, $sample, $settings
 * @var array $units, $departments, $employees, $calcTypes, $percentBases, $paginator
 * @var array|null $editing, $editingAssignment
 * @var array $old, $errors
 */
use App\Models\SalaryModel;

$baseQuery = array_filter([
    'component'  => $filters['component'] ?: null,
    'unit'       => $filters['unit'] ?: null,
    'department' => $filters['department'] ?: null,
    'employee'   => $filters['employee'] ?: null,
    'active'     => $filters['active'] === '' ? null : $filters['active'],
    'q'          => $filters['q'] ?: null,
]);
$link = static fn (int $page): string => url('components') . '?' . http_build_query($baseQuery + ['page' => $page]);
$benefits = array_values(array_filter($components, static fn ($c) => $c['type'] === 'BENEFIT'));
$deductions = array_values(array_filter($components, static fn ($c) => $c['type'] === 'DEDUCTION'));
$val = static function (string $key, mixed $default = '') use ($old, $editing) {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    if ($editing !== null && array_key_exists($key, $editing)) {
        return $editing[$key];
    }
    return $default;
};
$isEdit = $editing !== null;
?>
<div class="page-head">
    <div>
        <h1>Komponen Gaji</h1>
        <div class="sub">Tunjangan, potongan, penugasan karyawan, dan simulasi take-home pay (BPJS &amp; PPh 21).</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('settings?tab=pph') ?>"><?= icon('receipt') ?> PPh 21</a>
        <a class="btn btn-outline" href="<?= url('settings?tab=bpjs') ?>"><?= icon('shield') ?> BPJS</a>
        <a class="btn btn-primary" href="#assign-form" data-open-form><?= icon('users') ?> Tugaskan Komponen</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Komponen Aktif</span>
        <span class="v"><?= number_id($stats['components']) ?></span>
        <span class="s"><?= number_id($stats['benefits']) ?> tunjangan · <?= number_id($stats['deductions']) ?> potongan</span>
    </div>
    <div class="mini-stat">
        <span class="k">Penugasan</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id($stats['assignments']) ?></span>
        <span class="s">baris aktif</span>
    </div>
    <div class="mini-stat">
        <span class="k">Cakupan Karyawan</span>
        <span class="v"><?= number_id($stats['coverage'], 1) ?>%</span>
        <span class="s"><?= number_id($stats['employees_with']) ?> dari <?= number_id($stats['employees']) ?> aktif</span>
    </div>
    <div class="mini-stat">
        <span class="k">Komponen Default</span>
        <span class="v"><?= number_id($stats['defaults']) ?></span>
        <span class="s">diterapkan otomatis ke karyawan baru</span>
    </div>
</div>

<?php if ($editingAssignment): ?>
    <section class="card" style="margin-bottom:16px">
        <div class="card__head">
            <div><h2>Ubah Penugasan</h2>
                <div class="sub"><?= e($editingAssignment['full_name']) ?> · <?= e($editingAssignment['component_name']) ?></div></div>
            <a class="btn btn-sm btn-ghost" href="<?= url('components') ?>"><?= icon('x') ?> Tutup</a>
        </div>
        <form class="card__body" method="post" action="<?= url('components/assignments/' . $editingAssignment['id']) ?>">
            <?= csrf_field() ?>
            <div class="form-grid three">
                <div class="field">
                    <label>Nilai Override (Rp)</label>
                    <input class="input" type="number" name="amount_override" min="0" step="1000"
                           value="<?= $editingAssignment['amount_override'] !== null ? e((float) $editingAssignment['amount_override']) : '' ?>"
                           placeholder="Kosongkan untuk nilai komponen">
                    <span class="hint">Isi 0 untuk menjadikannya nol bagi karyawan ini.</span>
                </div>
                <div class="field">
                    <label>Berlaku Sejak</label>
                    <input class="input" type="date" name="effective_date" value="<?= e($editingAssignment['effective_date'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Berakhir</label>
                    <input class="input" type="date" name="end_date" value="<?= e($editingAssignment['end_date'] ?? '') ?>">
                </div>
            </div>
            <label class="checkbox" style="margin-top:10px">
                <input type="checkbox" name="is_active" value="1" <?= (int) $editingAssignment['is_active'] === 1 ? 'checked' : '' ?>>
                <span>Penugasan aktif (dihitung pada payroll)</span>
            </label>
            <div class="form-actions" style="margin-top:12px">
                <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan</button>
                <a class="btn btn-ghost" href="<?= url('components') ?>">Batal</a>
            </div>
        </form>
    </section>
<?php endif; ?>

<div class="split-grid wide-left">
    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head">
                <div><h2>Tunjangan (Benefit)</h2>
                    <div class="sub">Menambah bruto karyawan.</div></div>
                <span class="badge badge-teal"><?= count($benefits) ?> komponen</span>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                    <tr>
                        <th>Komponen</th><th>Perhitungan</th><th class="num">Nilai</th>
                        <th>Pajak / BPJS</th><th class="num">Dipakai</th><th>Status</th><th style="width:96px">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($benefits as $component): ?>
                        <tr>
                            <td>
                                <b><?= e($component['name']) ?></b>
                                <div class="mono muted" style="font-size:10px"><?= e($component['code']) ?></div>
                            </td>
                            <td class="small">
                                <?= e($calcTypes[$component['calc_type']] ?? $component['calc_type']) ?>
                                <?php if ($component['calc_type'] === 'PERCENT'): ?>
                                    <div class="muted" style="font-size:10px">dari <?= e(strtolower($percentBases[$component['percent_base']] ?? $component['percent_base'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="num small">
                                <?= $component['calc_type'] === 'PERCENT' ? number_id($component['amount'], 2) . '%' : rupiah($component['amount']) ?>
                            </td>
                            <td class="small">
                                <div class="flex wrap" style="gap:3px">
                                    <?php if ((int) $component['taxable'] === 1): ?><span class="badge badge-indigo">Kena PPh</span><?php else: ?><span class="badge badge-slate">Bebas PPh</span><?php endif; ?>
                                    <?php if ((int) $component['bpjs_base'] === 1): ?><span class="badge badge-teal">Dasar BPJS</span><?php endif; ?>
                                </div>
                            </td>
                            <td class="num small"><?= number_id($component['assign_count']) ?></td>
                            <td><?= (int) $component['is_active'] === 1 ? '<span class="badge badge-teal">Aktif</span>' : '<span class="badge badge-slate">Nonaktif</span>' ?></td>
                            <td>
                                <div class="flex" style="gap:2px">
                                    <a class="btn btn-sm btn-ghost" href="<?= url('components?edit=' . $component['id']) ?>" title="Ubah"><?= icon('edit') ?></a>
                                    <?php if ((int) $component['assign_count'] === 0 && !in_array(strtoupper((string) $component['code']), SalaryModel::LOCKED_CODES, true)): ?>
                                        <form class="inline-form" method="post" action="<?= url('components/' . $component['id'] . '/delete') ?>"
                                              data-confirm="Hapus komponen <?= e($component['name']) ?>?">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)" title="Hapus"><?= icon('trash') ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$benefits): ?>
                        <tr><td colspan="7" class="table-empty"><?= icon('layers') ?>Belum ada komponen tunjangan.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <div><h2>Potongan (Deduction)</h2>
                    <div class="sub">Mengurangi take-home pay di luar BPJS &amp; PPh.</div></div>
                <span class="badge badge-amber"><?= count($deductions) ?> komponen</span>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                    <tr><th>Komponen</th><th>Perhitungan</th><th class="num">Nilai</th><th class="num">Dipakai</th><th>Status</th><th style="width:96px">Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($deductions as $component): ?>
                        <tr>
                            <td>
                                <b><?= e($component['name']) ?></b>
                                <div class="mono muted" style="font-size:10px"><?= e($component['code']) ?></div>
                            </td>
                            <td class="small">
                                <?= e($calcTypes[$component['calc_type']] ?? $component['calc_type']) ?>
                                <?php if ($component['calc_type'] === 'PERCENT'): ?>
                                    <div class="muted" style="font-size:10px">dari <?= e(strtolower($percentBases[$component['percent_base']] ?? $component['percent_base'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="num small"><?= $component['calc_type'] === 'PERCENT' ? number_id($component['amount'], 2) . '%' : rupiah($component['amount']) ?></td>
                            <td class="num small"><?= number_id($component['assign_count']) ?></td>
                            <td><?= (int) $component['is_active'] === 1 ? '<span class="badge badge-teal">Aktif</span>' : '<span class="badge badge-slate">Nonaktif</span>' ?></td>
                            <td>
                                <div class="flex" style="gap:2px">
                                    <a class="btn btn-sm btn-ghost" href="<?= url('components?edit=' . $component['id']) ?>" title="Ubah"><?= icon('edit') ?></a>
                                    <?php if ((int) $component['assign_count'] === 0): ?>
                                        <form class="inline-form" method="post" action="<?= url('components/' . $component['id'] . '/delete') ?>"
                                              data-confirm="Hapus komponen <?= e($component['name']) ?>?">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)" title="Hapus"><?= icon('trash') ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$deductions): ?>
                        <tr><td colspan="6" class="table-empty"><?= icon('layers') ?>Belum ada komponen potongan.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <div><h2>Penugasan Karyawan</h2>
                    <div class="sub">Komponen yang dihitung pada payroll tiap karyawan.</div></div>
                <span class="badge badge-slate"><?= number_id($paginator->total) ?> baris</span>
            </div>

            <form class="filter-bar" method="get" action="<?= url('components') ?>">
                <select class="select" name="component" data-auto-submit style="min-width:165px">
                    <option value="">Semua komponen</option>
                    <?php foreach ($components as $component): ?>
                        <option value="<?= (int) $component['id'] ?>" <?= (int) $filters['component'] === (int) $component['id'] ? 'selected' : '' ?>><?= e($component['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="select" name="unit" data-auto-submit style="min-width:140px">
                    <option value="">Semua unit</option>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= (int) $unit['id'] ?>" <?= (int) $filters['unit'] === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="select" name="department" data-auto-submit style="min-width:140px">
                    <option value="">Semua departemen</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= (int) $dept['id'] ?>" <?= (int) $filters['department'] === (int) $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="input-group" style="max-width:180px">
                    <?= icon('search') ?>
                    <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Nama / komponen…">
                </div>
                <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
            </form>

            <div class="table-wrap">
                <table class="data">
                    <thead>
                    <tr>
                        <th>Karyawan</th><th>Komponen</th><th class="num">Nilai</th>
                        <th>Periode</th><th class="num">Gaji Pokok</th><th>Status</th><th style="width:80px">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($assignments as $row): ?>
                        <tr>
                            <td>
                                <div class="name-cell">
                                    <span class="avatar" style="background:<?= e(avatar_color($row['email'])) ?>"><?= e(initials($row['full_name'])) ?></span>
                                    <span>
                                        <b><?= e($row['full_name']) ?></b>
                                        <span class="mono"><?= e($row['employee_code']) ?></span>
                                    </span>
                                </div>
                            </td>
                            <td class="small">
                                <b><?= e($row['name']) ?></b>
                                <div class="muted" style="font-size:10px">
                                    <?= $row['type'] === 'BENEFIT' ? 'Tunjangan' : 'Potongan' ?>
                                    · <?= $row['amount_override'] !== null ? 'override' : e(strtolower($calcTypes[$row['calc_type']] ?? $row['calc_type'])) ?>
                                </div>
                            </td>
                            <td class="num small">
                                <b><?= rupiah($row['effective_amount']) ?></b>
                                <?php if ($row['amount_override'] !== null): ?>
                                    <div class="muted" style="font-size:9.6px">standar <?= rupiah($row['base_amount']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?= tanggal($row['effective_date']) ?>
                                <?php if ($row['end_date']): ?>
                                    <div class="muted" style="font-size:9.8px">s.d. <?= tanggal($row['end_date']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="num small"><?= rupiah($row['basic_salary']) ?></td>
                            <td><?= (int) $row['is_active'] === 1 ? '<span class="badge badge-teal">Aktif</span>' : '<span class="badge badge-slate">Nonaktif</span>' ?></td>
                            <td>
                                <div class="flex" style="gap:2px">
                                    <a class="btn btn-sm btn-ghost" href="<?= url('components?assignee=' . $row['id']) ?>" title="Ubah penugasan"><?= icon('edit') ?></a>
                                    <form class="inline-form" method="post" action="<?= url('components/assignments/' . $row['id'] . '/delete') ?>"
                                          data-confirm="Lepas <?= e($row['name']) ?> dari <?= e($row['full_name']) ?>?">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)" title="Lepas"><?= icon('x') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$assignments): ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <div class="empty-icon"><?= icon('users') ?></div>
                                <h3>Belum ada penugasan pada filter ini</h3>
                                <p>Gunakan tombol <b>Tugaskan Komponen</b> atau terapkan komponen default.</p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card__foot flex-between wrap" style="padding:10px 16px">
                <span class="small muted">Menampilkan <?= number_id($paginator->from()) ?>–<?= number_id($paginator->to()) ?> dari <?= number_id($paginator->total) ?> penugasan</span>
                <?php if ($paginator->lastPage() > 1): ?>
                    <div class="pagination" style="padding:0">
                        <?php if ($paginator->hasPrevious()): ?><a href="<?= e($link($paginator->page - 1)) ?>"><?= icon('chevron-left') ?></a><?php endif; ?>
                        <?php foreach ($paginator->window(5) as $page): ?>
                            <?php if ($page === $paginator->page): ?>
                                <span class="active"><?= $page ?></span>
                            <?php else: ?>
                                <a href="<?= e($link($page)) ?>"><?= $page ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($paginator->hasNext()): ?><a href="<?= e($link($paginator->page + 1)) ?>"><?= icon('chevron-right') ?></a><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="flex-col gap-16">
        <section class="card" id="assign-form">
            <div class="card__head">
                <div><h2>Tugaskan Komponen</h2>
                    <div class="sub">Satu komponen ke banyak karyawan sekaligus.</div></div>
            </div>
            <form class="card__body" method="post" action="<?= url('components/assign') ?>">
                <?= csrf_field() ?>
                <div class="flex-col gap-12">
                    <div class="field">
                        <label>Komponen <span class="req">*</span></label>
                        <select class="select" name="component_id" required>
                            <option value="">Pilih komponen…</option>
                            <?php foreach ($components as $component): ?>
                                <?php if ((int) $component['is_active'] !== 1) continue; ?>
                                <option value="<?= (int) $component['id'] ?>">
                                    <?= e($component['name']) ?> · <?= $component['calc_type'] === 'PERCENT' ? number_id($component['amount'], 2) . '%' : rupiah($component['amount']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Cakupan</label>
                        <select class="select" name="scope" id="assign-scope">
                            <option value="selected">Karyawan terpilih</option>
                            <option value="unit">Semua karyawan aktif unit</option>
                            <option value="department">Semua karyawan aktif departemen</option>
                        </select>
                    </div>
                    <div class="field" id="assign-employees">
                        <label>Pilih Karyawan <span class="muted small">(Ctrl/Cmd + klik)</span></label>
                        <select class="select multi-select" name="employee_ids[]" multiple size="7">
                            <?php foreach ($employees as $person): ?>
                                <option value="<?= (int) $person['id'] ?>">
                                    <?= e($person['full_name']) ?> · <?= e($person['employee_code']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field" id="assign-unit" style="display:none">
                        <label>Unit</label>
                        <select class="select" name="unit">
                            <option value="">Pilih unit…</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= (int) $unit['id'] ?>"><?= e($unit['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field" id="assign-department" style="display:none">
                        <label>Departemen</label>
                        <select class="select" name="department">
                            <option value="">Pilih departemen…</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= (int) $dept['id'] ?>"><?= e($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grid">
                        <div class="field">
                            <label>Override Nilai (Rp)</label>
                            <input class="input" type="number" name="amount_override" min="0" step="1000" placeholder="opsional">
                        </div>
                        <div class="field">
                            <label>Berlaku Sejak</label>
                            <input class="input" type="date" name="effective_date" value="<?= date('Y-m-01') ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label>Berakhir (opsional)</label>
                        <input class="input" type="date" name="end_date">
                    </div>
                </div>
                <button class="btn btn-primary btn-block" type="submit" style="margin-top:12px"><?= icon('check') ?> Tugaskan</button>
            </form>
            <div class="card__foot" style="padding:11px 16px">
                <form method="post" action="<?= url('components/apply-defaults') ?>"
                      data-confirm="Terapkan semua komponen bertanda default ke seluruh karyawan aktif?">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-soft btn-block" type="submit"><?= icon('refresh') ?> Terapkan Komponen Default</button>
                </form>
            </div>
        </section>

        <section class="card" id="component-form">
            <div class="card__head">
                <div><h2><?= $isEdit ? 'Ubah Komponen' : 'Tambah Komponen' ?></h2>
                    <div class="sub"><?= $isEdit ? e($editing['name']) : 'Tunjangan atau potongan baru.' ?></div></div>
                <?php if ($isEdit): ?>
                    <a class="btn btn-sm btn-ghost" href="<?= url('components') ?>"><?= icon('x') ?></a>
                <?php endif; ?>
            </div>
            <form class="card__body" method="post" action="<?= url($isEdit ? 'components/' . $editing['id'] : 'components') ?>">
                <?= csrf_field() ?>
                <div class="flex-col gap-12">
                    <div class="form-grid">
                        <div class="field">
                            <label>Kode <span class="req">*</span></label>
                            <input class="input" name="code" maxlength="30" value="<?= e($val('code')) ?>" placeholder="TUNJ_JABATAN" required>
                        </div>
                        <div class="field">
                            <label>Nama <span class="req">*</span></label>
                            <input class="input" name="name" maxlength="120" value="<?= e($val('name')) ?>" placeholder="Tunjangan Jabatan" required>
                        </div>
                        <div class="field">
                            <label>Jenis <span class="req">*</span></label>
                            <select class="select" name="type" required>
                                <option value="BENEFIT" <?= $val('type', 'BENEFIT') === 'BENEFIT' ? 'selected' : '' ?>>Tunjangan (menambah)</option>
                                <option value="DEDUCTION" <?= $val('type') === 'DEDUCTION' ? 'selected' : '' ?>>Potongan (mengurangi)</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Tipe Perhitungan</label>
                            <select class="select" name="calc_type" id="calc-type">
                                <?php foreach ($calcTypes as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $val('calc_type', 'FIXED') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Nominal / Persentase <span class="req">*</span></label>
                            <input class="input" type="number" name="amount" min="0" step="0.01" value="<?= e($val('amount', 0)) ?>" required>
                        </div>
                        <div class="field">
                            <label>Dasar Persentase</label>
                            <select class="select" name="percent_base">
                                <?php foreach ($percentBases as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $val('percent_base', 'BASIC') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <label class="checkbox"><input type="checkbox" name="taxable" value="1" <?= in_array((string) $val('taxable', '1'), ['1', 1], true) ? 'checked' : '' ?>> <span>Objek PPh 21</span></label>
                    <label class="checkbox"><input type="checkbox" name="bpjs_base" value="1" <?= in_array((string) $val('bpjs_base', '0'), ['1', 1], true) ? 'checked' : '' ?>> <span>Termasuk dasar BPJS</span></label>
                    <label class="checkbox"><input type="checkbox" name="apply_default" value="1" <?= in_array((string) $val('apply_default', '0'), ['1', 1], true) ? 'checked' : '' ?>> <span>Terapkan otomatis ke karyawan</span></label>
                    <label class="checkbox"><input type="checkbox" name="is_active" value="1" <?= in_array((string) $val('is_active', '1'), ['1', 1], true) ? 'checked' : '' ?>> <span>Aktif</span></label>
                    <?php if ($isEdit && (int) $val('apply_default', '0') === 1): ?>
                        <label class="checkbox"><input type="checkbox" name="sync_defaults" value="1"> <span>Terapkan ulang ke karyawan yang belum punya</span></label>
                    <?php endif; ?>
                </div>
                <button class="btn btn-primary btn-block" type="submit" style="margin-top:12px"><?= icon('check') ?> <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Komponen' ?></button>
            </form>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Cakupan per Unit</h2>
                <div class="sub">Karyawan dengan minimal satu komponen aktif.</div></div></div>
            <div class="list-rows">
                <?php foreach ($coverage as $unit): ?>
                    <?php $pct = (int) $unit['headcount'] > 0 ? (int) round((int) $unit['covered'] / (int) $unit['headcount'] * 100) : 0; ?>
                    <div class="list-row">
                        <span class="avatar" style="background:<?= e(avatar_color((string) $unit['code'])) ?>"><?= e(substr((string) $unit['code'], 0, 2)) ?></span>
                        <div class="list-row__body">
                            <b><?= e($unit['name']) ?></b>
                            <span><?= number_id($unit['covered']) ?> / <?= number_id($unit['headcount']) ?> karyawan</span>
                            <div class="progress thin" style="margin-top:4px"><span style="width:<?= $pct ?>%"></span></div>
                        </div>
                        <span class="badge badge-<?= $pct >= 90 ? 'teal' : ($pct >= 50 ? 'amber' : 'rose') ?>"><?= $pct ?>%</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>

<section class="card" style="margin-top:16px">
    <div class="card__head">
        <div><h2>Simulasi Take-Home Pay</h2>
            <div class="sub">10 karyawan bergaji tertinggi · BPJS <?= $settings['pph_enabled'] ? 'dan PPh 21 ' . e($settings['pph_method']) : 'tanpa PPh' ?> sesuai pengaturan aktif.</div></div>
        <a class="btn btn-sm btn-ghost" href="<?= url('settings?tab=pph') ?>"><?= icon('sliders') ?> Ubah Kebijakan</a>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th>Karyawan</th><th class="num">Gaji Pokok</th><th class="num">Bruto</th>
                <th class="num">BPJS (karyawan)</th><th class="num">PPh 21</th>
                <th class="num">Take Home</th><th class="num">BPJS (perusahaan)</th><th class="num">Total Biaya</th><th style="width:86px"></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($sample as $row): ?>
                <tr>
                    <td>
                        <b><?= e($row['full_name']) ?></b>
                        <div class="muted" style="font-size:10px"><?= e($row['employee_code']) ?> · <?= e($row['tax_status']) ?> · <?= e($row['unit_name'] ?? '-') ?></div>
                    </td>
                    <td class="num small"><?= rupiah($row['basic_salary']) ?></td>
                    <td class="num small"><?= rupiah($row['gross']) ?></td>
                    <td class="num small"><?= rupiah($row['bpjs']) ?></td>
                    <td class="num small"><?= rupiah($row['pph']) ?></td>
                    <td class="num"><b style="color:var(--teal-700)"><?= rupiah($row['net']) ?></b></td>
                    <td class="num small"><?= rupiah(($row['employer_cost'] ?? 0) - ($row['gross'] ?? 0)) ?></td>
                    <td class="num small"><?= rupiah($row['employer_cost']) ?></td>
                    <td>
                        <button class="btn btn-sm btn-soft" type="button" data-simulate="<?= (int) $row['id'] ?>"><?= icon('calculator') ?> Simulasi</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$sample): ?>
                <tr><td colspan="9" class="table-empty"><?= icon('wallet') ?>Belum ada karyawan aktif.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card__foot small muted" style="padding:10px 16px">
        PPh 21: metode <b><?= e($settings['pph_method']) ?></b><?= $settings['pph_method'] === 'FLAT' ? ' (' . number_id($settings['pph_rate'], 2) . '%)' : '' ?> ·
        BPJS Kesehatan <?= number_id($settings['kes_employee'], 2) ?>% karyawan / <?= number_id($settings['kes_company'], 2) ?>% perusahaan (plafon <?= rupiah($settings['kes_max']) ?>) ·
        JHT <?= number_id($settings['jht_employee'], 2) ?>% / <?= number_id($settings['jht_company'], 2) ?>% ·
        JP <?= number_id($settings['jp_employee'], 2) ?>% / <?= number_id($settings['jp_company'], 2) ?>%
    </div>
</section>

<script>
(function () {
    var scope = document.getElementById('assign-scope');
    var employees = document.getElementById('assign-employees');
    var unit = document.getElementById('assign-unit');
    var department = document.getElementById('assign-department');
    function sync() {
        if (!scope) { return; }
        var value = scope.value;
        if (employees) { employees.style.display = value === 'selected' ? '' : 'none'; }
        if (unit) { unit.style.display = value === 'unit' ? '' : 'none'; }
        if (department) { department.style.display = value === 'department' ? '' : 'none'; }
    }
    if (scope) { scope.addEventListener('change', sync); }
    sync();
})();
</script>

<div class="modal-backdrop" id="sim-modal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="sim-title">
        <div class="modal__head">
            <div>
                <h3 id="sim-title">Simulasi Take-Home Pay</h3>
                <div class="sub" id="sim-sub">Memuat…</div>
            </div>
            <button class="btn btn-sm btn-ghost" type="button" data-sim-close aria-label="Tutup"><?= icon('x') ?></button>
        </div>
        <div class="modal__body" id="sim-body"></div>
        <div class="modal__foot">
            <span class="small muted">Angka mengikuti pengaturan payroll, BPJS, dan PPh 21 yang aktif.</span>
            <button class="btn btn-sm btn-outline" type="button" data-sim-close>Tutup</button>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('sim-modal');
    if (!modal) { return; }
    var body = document.getElementById('sim-body');
    var sub = document.getElementById('sim-sub');
    var base = <?= json_encode(url('components/simulate')) ?>;

    function money(value) {
        return 'Rp ' + Number(Math.round(value || 0)).toLocaleString('id-ID');
    }
    function rows(title, items, sign) {
        if (!items || !items.length) { return ''; }
        return '<div class="sim-group"><h4>' + title + '</h4>' + items.map(function (item) {
            return '<div class="sim-line"><span>' + item.name + '</span><b>' + (sign ? sign : '') + money(item.amount) + '</b></div>';
        }).join('') + '</div>';
    }

    function render(data) {
        var html = '';
        html += '<div class="sim-head"><b>' + data.employee.name + '</b><span>' + data.employee.code + ' · ' + data.employee.unit + ' · PTKP ' + data.employee.tax_status + '</span></div>';
        html += '<div class="sim-group"><div class="sim-line"><span>Gaji pokok</span><b>' + money(data.basic) + '</b></div></div>';
        html += rows('Tunjangan', data.benefits, '+ ');
        html += '<div class="sim-line sim-total"><span>Bruto</span><b>' + money(data.gross) + '</b></div>';
        html += rows('BPJS dipotong dari karyawan', (data.bpjs.items || []).filter(function (i) { return i.side === 'EMPLOYEE'; }), '− ');
        html += '<div class="sim-group"><h4>PPh 21 · ' + data.pph.method + '</h4>'
            + '<div class="sim-line"><span>Dasar pengenaan</span><b>' + money(data.taxable_base) + '</b></div>'
            + '<div class="sim-line"><span>PPh 21 sebulan</span><b>− ' + money(data.pph.monthly) + '</b></div>'
            + '<div class="sim-note">' + (data.pph.note || '') + '</div></div>';
        html += rows('Potongan lain', data.deductions, '− ');
        html += '<div class="sim-total sim-net"><span>Take home pay</span><b>' + money(data.net) + '</b></div>';
        html += rows('Iuran BPJS perusahaan', (data.bpjs.items || []).filter(function (i) { return i.side === 'COMPANY'; }), '');
        html += '<div class="sim-line sim-total"><span>Total biaya perusahaan</span><b>' + money(data.employer_cost) + '</b></div>';
        body.innerHTML = html;
    }

    function open(id) {
        modal.hidden = false;
        body.innerHTML = '<div class="sim-loading">Menghitung…</div>';
        sub.textContent = 'Karyawan #' + id;
        fetch(base + '/' + id, { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (payload) {
                if (!payload.ok) { throw new Error(payload.error || 'Gagal memuat simulasi.'); }
                sub.textContent = payload.data.employee.name + ' · ' + payload.data.employee.code;
                render(payload.data);
            })
            .catch(function (error) { body.innerHTML = '<div class="sim-loading">' + error.message + '</div>'; });
    }

    document.querySelectorAll('[data-simulate]').forEach(function (button) {
        button.addEventListener('click', function () { open(button.getAttribute('data-simulate')); });
    });
    modal.querySelectorAll('[data-sim-close]').forEach(function (button) {
        button.addEventListener('click', function () { modal.hidden = true; });
    });
    modal.addEventListener('click', function (event) { if (event.target === modal) { modal.hidden = true; } });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') { modal.hidden = true; } });
})();
</script>
