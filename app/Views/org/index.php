<?php
/**
 * Unit Organisasi & Format ID.
 * @var string $tab
 * @var array|null $editing
 * @var array $units, $departments, $positions, $stats, $employees, $idFormat
 * @var array $errors, $old
 * @var int $unitFilter
 */
$tab = $tab ?? 'units';
$editing = $editing ?? null;
$isEdit = is_array($editing);
$action = match ($tab) {
    'units'       => $isEdit ? url('organisation/units/' . $editing['id']) : url('organisation/units'),
    'departments' => $isEdit ? url('organisation/departments/' . $editing['id']) : url('organisation/departments'),
    'positions'   => $isEdit ? url('organisation/positions/' . $editing['id']) : url('organisation/positions'),
    default       => url('organisation/id-format'),
};
$tabs = [
    'units'       => ['label' => 'Business Unit', 'icon' => 'building'],
    'departments' => ['label' => 'Departemen', 'icon' => 'layers'],
    'positions'   => ['label' => 'Posisi & Level', 'icon' => 'briefcase'],
    'id-format'   => ['label' => 'Format ID Karyawan', 'icon' => 'key'],
];
$val = static function (string $key, mixed $default = '') use ($old, $editing) {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    if (is_array($editing) && array_key_exists($key, $editing)) {
        return $editing[$key];
    }
    return $default;
};
?>
<div class="page-head">
    <div>
        <h1>Unit Organisasi</h1>
        <div class="sub">Struktur entitas, departemen, dan posisi PT Asia Raya Group beserta aturan penomoran karyawan.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('employees') ?>"><?= icon('users') ?> Data Karyawan</a>
        <a class="btn btn-primary" href="<?= url('organisation?tab=' . $tab . '#org-form') ?>" data-open-form><?= icon('plus') ?> Tambah <?= $tab === 'units' ? 'Unit' : ($tab === 'departments' ? 'Departemen' : 'Posisi') ?></a>
    </div>
</div>

<div class="stat-strip" style="margin-bottom:16px">
    <div><span class="k">Business Unit</span><span class="v"><?= number_id($stats['units']) ?> aktif</span></div>
    <div><span class="k">Departemen</span><span class="v"><?= number_id($stats['departments']) ?></span></div>
    <div><span class="k">Posisi</span><span class="v"><?= number_id($stats['positions']) ?></span></div>
    <div><span class="k">Karyawan Aktif</span><span class="v"><?= number_id($stats['employees']) ?> jiwa</span></div>
    <div><span class="k">Format ID</span><span class="v mono"><?= e($idFormat['format']) ?></span></div>
</div>

<nav class="tabs">
    <?php foreach ($tabs as $key => $meta): ?>
        <a class="tab <?= $tab === $key ? 'active' : '' ?>" href="<?= url('organisation?tab=' . $key) ?>">
            <?= icon($meta['icon'], 'ico') ?> <?= e($meta['label']) ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php /* ============================================================ UNITS */ ?>
<?php if ($tab === 'units'): ?>

    <details class="form-panel" id="org-form" <?= $isEdit ? 'open' : '' ?>>
        <summary>
            <span class="form-panel__title"><?= icon($isEdit ? 'edit' : 'plus', 'ico') ?>
                <?= $isEdit ? 'Ubah Business Unit — ' . e($editing['name']) : 'Tambah Business Unit' ?></span>
            <span class="muted small">Klik untuk <?= $isEdit ? 'menutup' : 'membuka' ?> formulir</span>
        </summary>
        <form method="post" action="<?= $action ?>" enctype="multipart/form-data" class="form-panel__body">
            <?= csrf_field() ?>
            <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>
            <div class="form-grid three">
                <div class="field">
                    <label>Nama Unit <span class="req">*</span></label>
                    <input class="input" name="name" value="<?= e($val('name')) ?>" placeholder="Contoh: Asia Raya Jakarta" required>
                </div>
                <div class="field">
                    <label>Kode Unit <span class="req">*</span></label>
                    <input class="input mono" name="code" value="<?= e($val('code')) ?>" placeholder="JKT" maxlength="10" required>
                    <span class="hint">Dipakai pada token {BU} di ID karyawan.</span>
                </div>
                <div class="field">
                    <label>Tipe Unit</label>
                    <select class="select" name="type">
                        <?php foreach (['Head Office', 'Branch Office', 'Factory', 'Warehouse', 'Store', 'Representative Office'] as $type): ?>
                            <option value="<?= e($type) ?>" <?= $val('type') === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Kota</label>
                    <input class="input" name="city" value="<?= e($val('city')) ?>" placeholder="Jakarta Selatan">
                </div>
                <div class="field">
                    <label>Telepon</label>
                    <input class="input" name="phone" value="<?= e($val('phone')) ?>" placeholder="021-xxxxxxx">
                </div>
                <div class="field">
                    <label>Logo Unit</label>
                    <input class="input" type="file" name="logo" accept="image/jpeg,image/png,image/webp">
                    <span class="hint">JPG/PNG/WEBP maksimal 4 MB.</span>
                </div>
                <div class="field full">
                    <label>Alamat</label>
                    <input class="input" name="address" value="<?= e($val('address')) ?>" placeholder="Jl. Jenderal Sudirman No. 21">
                </div>
                <div class="field">
                    <label>Latitude</label>
                    <input class="input" name="latitude" value="<?= e($val('latitude')) ?>" placeholder="-6.2088000">
                </div>
                <div class="field">
                    <label>Longitude</label>
                    <input class="input" name="longitude" value="<?= e($val('longitude')) ?>" placeholder="106.8456000">
                </div>
                <div class="field">
                    <label>Radius Geofence (meter)</label>
                    <input class="input" name="geofence_radius" type="number" min="10" max="2000" value="<?= e($val('geofence_radius', 50)) ?>">
                </div>
                <div class="field">
                    <label>Tanggal Cut-off Payroll</label>
                    <input class="input" name="payroll_cutoff_day" type="number" min="1" max="31" value="<?= e($val('payroll_cutoff_day', 25)) ?>">
                </div>
                <div class="field">
                    <label>Tanggal Pembayaran Gaji</label>
                    <input class="input" name="payroll_pay_day" type="number" min="1" max="31" value="<?= e($val('payroll_pay_day', 28)) ?>">
                </div>
                <div class="field">
                    <label>Status</label>
                    <label class="switch" style="height:38px">
                        <input type="checkbox" name="is_active" value="1" <?= (!$isEdit || (int) $val('is_active', 1) === 1) ? 'checked' : '' ?>>
                        <span class="switch__track"></span>
                        <span>Unit aktif</span>
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <?php if ($isEdit): ?>
                    <a class="btn btn-ghost" href="<?= url('organisation?tab=units') ?>">Batal</a>
                <?php endif; ?>
                <button class="btn btn-primary" type="submit"><?= icon('check') ?> <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Unit' ?></button>
            </div>
        </form>
    </details>

    <section class="card">
        <div class="card__head">
            <div><h2>Daftar Business Unit</h2>
                <div class="sub"><?= count($units) ?> entitas terdaftar · koordinat dipakai untuk validasi geofence presensi.</div></div>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th>Unit</th>
                    <th>Kode</th>
                    <th>Tipe</th>
                    <th class="num">Departemen</th>
                    <th class="num">Karyawan</th>
                    <th>Geofence</th>
                    <th>Payroll</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($units as $unit): ?>
                    <tr>
                        <td>
                            <div class="name-cell">
                                <?php if (!empty($unit['logo'])): ?>
                                    <img class="avatar" src="<?= url('media/units/' . basename($unit['logo'])) ?>" alt="" style="object-fit:cover">
                                <?php else: ?>
                                    <span class="avatar" style="background:<?= e(avatar_color($unit['code'])) ?>"><?= e(substr($unit['code'], 0, 2)) ?></span>
                                <?php endif; ?>
                                <span>
                                    <b><?= e($unit['name']) ?></b>
                                    <span><?= e(trim(($unit['city'] ?? '') . ' · ' . ($unit['type'] ?? ''), ' ·')) ?></span>
                                </span>
                            </div>
                        </td>
                        <td><span class="tag-soft mono"><?= e($unit['code']) ?></span></td>
                        <td class="small muted"><?= e($unit['type'] ?: '-') ?></td>
                        <td class="num"><?= number_id($unit['department_count']) ?></td>
                        <td class="num"><?= number_id($unit['employee_count']) ?></td>
                        <td class="small">
                            <?php if ($unit['latitude'] !== null && $unit['longitude'] !== null): ?>
                                <span class="mono"><?= e(number_format((float) $unit['latitude'], 4)) ?>, <?= e(number_format((float) $unit['longitude'], 4)) ?></span>
                                <span class="muted"> · <?= (int) $unit['geofence_radius'] ?> m</span>
                            <?php else: ?>
                                <span class="muted">Belum diset</span>
                            <?php endif; ?>
                        </td>
                        <td class="small muted">Cut-off <?= (int) $unit['payroll_cutoff_day'] ?> · Bayar <?= (int) $unit['payroll_pay_day'] ?></td>
                        <td><?= (int) $unit['is_active'] === 1 ? '<span class="badge badge-teal">Aktif</span>' : '<span class="badge badge-slate">Nonaktif</span>' ?></td>
                        <td class="text-right">
                            <div class="flex" style="gap:6px;justify-content:flex-end">
                                <a class="btn btn-sm btn-outline" href="<?= url('organisation?tab=units&edit=' . $unit['id'] . '#org-form') ?>"><?= icon('edit') ?> Ubah</a>
                                <form class="inline-form" method="post" action="<?= url('organisation/units/' . $unit['id'] . '/delete') ?>" data-confirm="Hapus business unit <?= e($unit['name']) ?>? Tindakan ini permanen.">
                                    <?= csrf_field() ?><?= method_field('DELETE') ?>
                                    <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)"><?= icon('trash') ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$units): ?>
                    <tr><td colspan="9" class="table-empty"><?= icon('building') ?>Belum ada business unit. Tambahkan unit pertama di formulir di atas.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php /* ====================================================== DEPARTMENTS */ ?>
<?php elseif ($tab === 'departments'): ?>

    <details class="form-panel" id="org-form" <?= $isEdit ? 'open' : '' ?>>
        <summary>
            <span class="form-panel__title"><?= icon($isEdit ? 'edit' : 'plus', 'ico') ?>
                <?= $isEdit ? 'Ubah Departemen — ' . e($editing['name']) : 'Tambah Departemen' ?></span>
            <span class="muted small">Klik untuk membuka formulir</span>
        </summary>
        <form method="post" action="<?= $action ?>" class="form-panel__body">
            <?= csrf_field() ?>
            <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>
            <div class="form-grid">
                <div class="field">
                    <label>Business Unit <span class="req">*</span></label>
                    <select class="select" name="business_unit_id" required>
                        <option value="">Pilih unit…</option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= (int) $unit['id'] ?>" <?= (int) $val('business_unit_id', $unitFilter) === (int) $unit['id'] ? 'selected' : '' ?>>
                                <?= e($unit['name']) ?> (<?= e($unit['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Nama Departemen <span class="req">*</span></label>
                    <input class="input" name="name" value="<?= e($val('name')) ?>" placeholder="Finance & Accounting" required>
                </div>
                <div class="field">
                    <label>Kode Departemen <span class="req">*</span></label>
                    <input class="input mono" name="code" value="<?= e($val('code')) ?>" placeholder="FIN" maxlength="10" required>
                    <span class="hint">Dipakai pada token {DEPT}.</span>
                </div>
                <div class="field">
                    <label>Kepala Departemen</label>
                    <select class="select" name="head_employee_id">
                        <option value="">Belum ditentukan</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= (int) $emp['id'] ?>" <?= (int) $val('head_employee_id') === (int) $emp['id'] ? 'selected' : '' ?>>
                                <?= e($emp['full_name']) ?> · <?= e($emp['employee_code']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Status</label>
                    <label class="switch" style="height:38px">
                        <input type="checkbox" name="is_active" value="1" <?= (!$isEdit || (int) $val('is_active', 1) === 1) ? 'checked' : '' ?>>
                        <span class="switch__track"></span>
                        <span>Departemen aktif</span>
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <?php if ($isEdit): ?>
                    <a class="btn btn-ghost" href="<?= url('organisation?tab=departments') ?>">Batal</a>
                <?php endif; ?>
                <button class="btn btn-primary" type="submit"><?= icon('check') ?> <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Departemen' ?></button>
            </div>
        </form>
    </details>

    <section class="card">
        <div class="card__head">
            <div><h2>Daftar Departemen</h2>
                <div class="sub"><?= count($departments) ?> departemen<?= $unitFilter ? ' pada unit terpilih' : '' ?>.</div></div>
            <form class="card__head__actions" method="get" action="<?= url('organisation') ?>">
                <input type="hidden" name="tab" value="departments">
                <select class="select" name="unit" data-auto-submit style="min-width:200px">
                    <option value="">Semua unit</option>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= (int) $unit['id'] ?>" <?= $unitFilter === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th>Departemen</th><th>Kode</th><th>Business Unit</th><th>Kepala</th>
                    <th class="num">Karyawan</th><th>Status</th><th class="text-right">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($departments as $dept): ?>
                    <tr>
                        <td><b><?= e($dept['name']) ?></b></td>
                        <td><span class="tag-soft mono"><?= e($dept['code']) ?></span></td>
                        <td class="small"><?= e($dept['unit_name'] ?? '-') ?> <span class="muted">(<?= e($dept['unit_code'] ?? '-') ?>)</span></td>
                        <td class="small"><?= e($dept['head_name'] ?? '—') ?></td>
                        <td class="num"><?= number_id($dept['employee_count']) ?></td>
                        <td><?= (int) $dept['is_active'] === 1 ? '<span class="badge badge-teal">Aktif</span>' : '<span class="badge badge-slate">Nonaktif</span>' ?></td>
                        <td class="text-right">
                            <div class="flex" style="gap:6px;justify-content:flex-end">
                                <a class="btn btn-sm btn-outline" href="<?= url('organisation?tab=departments&edit=' . $dept['id'] . '#org-form') ?>"><?= icon('edit') ?> Ubah</a>
                                <form class="inline-form" method="post" action="<?= url('organisation/departments/' . $dept['id'] . '/delete') ?>" data-confirm="Hapus departemen <?= e($dept['name']) ?>?">
                                    <?= csrf_field() ?><?= method_field('DELETE') ?>
                                    <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)"><?= icon('trash') ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$departments): ?>
                    <tr><td colspan="7" class="table-empty"><?= icon('layers') ?>Belum ada departemen pada filter ini.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php /* ======================================================== POSITIONS */ ?>
<?php elseif ($tab === 'positions'): ?>

    <details class="form-panel" id="org-form" <?= $isEdit ? 'open' : '' ?>>
        <summary>
            <span class="form-panel__title"><?= icon($isEdit ? 'edit' : 'plus', 'ico') ?>
                <?= $isEdit ? 'Ubah Posisi — ' . e($editing['name']) : 'Tambah Posisi' ?></span>
            <span class="muted small">Klik untuk membuka formulir</span>
        </summary>
        <form method="post" action="<?= $action ?>" class="form-panel__body">
            <?= csrf_field() ?>
            <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>
            <div class="form-grid three">
                <div class="field">
                    <label>Nama Posisi <span class="req">*</span></label>
                    <input class="input" name="name" value="<?= e($val('name')) ?>" placeholder="Finance Manager" required>
                </div>
                <div class="field">
                    <label>Kode</label>
                    <input class="input mono" name="code" value="<?= e($val('code')) ?>" placeholder="FIN-MGR" maxlength="15">
                </div>
                <div class="field">
                    <label>Level</label>
                    <select class="select" name="level">
                        <?php foreach (['', 'Staff', 'Supervisor', 'Assistant Manager', 'Manager', 'General Manager', 'Director'] as $level): ?>
                            <option value="<?= e($level) ?>" <?= $val('level') === $level ? 'selected' : '' ?>><?= $level === '' ? 'Tidak ditentukan' : e($level) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Departemen</label>
                    <select class="select" name="department_id">
                        <option value="">Lintas departemen</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= (int) $dept['id'] ?>" <?= (int) $val('department_id') === (int) $dept['id'] ? 'selected' : '' ?>>
                                <?= e($dept['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Gaji Pokok Standar (Rp)</label>
                    <input class="input" name="basic_salary" type="number" min="0" step="100000" value="<?= e($val('basic_salary', 0)) ?>">
                    <span class="hint">Menjadi nilai default saat menambah karyawan.</span>
                </div>
                <div class="field">
                    <label>Status</label>
                    <label class="switch" style="height:38px">
                        <input type="checkbox" name="is_active" value="1" <?= (!$isEdit || (int) $val('is_active', 1) === 1) ? 'checked' : '' ?>>
                        <span class="switch__track"></span>
                        <span>Posisi aktif</span>
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <?php if ($isEdit): ?>
                    <a class="btn btn-ghost" href="<?= url('organisation?tab=positions') ?>">Batal</a>
                <?php endif; ?>
                <button class="btn btn-primary" type="submit"><?= icon('check') ?> <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Posisi' ?></button>
            </div>
        </form>
    </details>

    <section class="card">
        <div class="card__head">
            <div><h2>Daftar Posisi</h2>
                <div class="sub"><?= count($positions) ?> posisi dengan standar gaji pokok.</div></div>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr><th>Posisi</th><th>Kode</th><th>Level</th><th>Departemen</th><th class="num">Gaji Pokok</th><th class="num">Karyawan</th><th class="text-right">Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($positions as $position): ?>
                    <tr>
                        <td><b><?= e($position['name']) ?></b></td>
                        <td><span class="tag-soft mono"><?= e($position['code'] ?: '-') ?></span></td>
                        <td class="small muted"><?= e($position['level'] ?: '-') ?></td>
                        <td class="small"><?= e($position['department_name'] ?? 'Lintas departemen') ?></td>
                        <td class="num"><?= rupiah($position['basic_salary']) ?></td>
                        <td class="num"><?= number_id($position['employee_count']) ?></td>
                        <td class="text-right">
                            <div class="flex" style="gap:6px;justify-content:flex-end">
                                <a class="btn btn-sm btn-outline" href="<?= url('organisation?tab=positions&edit=' . $position['id'] . '#org-form') ?>"><?= icon('edit') ?> Ubah</a>
                                <form class="inline-form" method="post" action="<?= url('organisation/positions/' . $position['id'] . '/delete') ?>" data-confirm="Hapus posisi <?= e($position['name']) ?>?">
                                    <?= csrf_field() ?><?= method_field('DELETE') ?>
                                    <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)"><?= icon('trash') ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$positions): ?>
                    <tr><td colspan="7" class="table-empty"><?= icon('briefcase') ?>Belum ada posisi terdaftar.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php /* ======================================================= ID FORMAT */ ?>
<?php else: ?>

    <div class="split-grid">
        <section class="card">
            <div class="card__head">
                <div><h2>Format ID Karyawan</h2>
                    <div class="sub">Nomor induk karyawan dibuat otomatis saat penambahan karyawan baru.</div></div>
                <span class="badge badge-indigo">Auto-generate</span>
            </div>
            <form method="post" action="<?= url('organisation/id-format') ?>" class="card__body">
                <?= csrf_field() ?>
                <div class="form-grid">
                    <div class="field">
                        <label>Prefiks Perusahaan</label>
                        <input class="input mono" name="employee_id_company_prefix" value="<?= e($idFormat['prefix']) ?>" maxlength="10" placeholder="ARG">
                        <span class="hint">Mengisi token {COMPANY}.</span>
                    </div>
                    <div class="field">
                        <label>Cakupan Nomor Urut</label>
                        <select class="select" name="employee_id_scope">
                            <option value="BU" <?= $idFormat['scope'] === 'BU' ? 'selected' : '' ?>>Per business unit (disarankan)</option>
                            <option value="GLOBAL" <?= $idFormat['scope'] === 'GLOBAL' ? 'selected' : '' ?>>Global perusahaan</option>
                        </select>
                    </div>
                    <div class="field full">
                        <label>Pola ID <span class="req">*</span></label>
                        <input class="input mono" name="employee_id_format" value="<?= e($idFormat['format']) ?>" maxlength="60" required>
                        <span class="hint">Contoh: <span class="mono">ARG-{BU}-{SEQ:4}</span> menghasilkan <span class="mono">ARG-JKT-0124</span>.</span>
                    </div>
                </div>

                <div class="divider"></div>
                <div class="small" style="font-weight:650;margin-bottom:8px">Token yang tersedia</div>
                <div class="flex wrap" style="gap:6px">
                    <?php foreach (\App\Core\EmployeeCode::placeholders() as $token => $label): ?>
                        <span class="tag-soft mono" title="<?= e($label) ?>"><?= e($token) ?></span>
                    <?php endforeach; ?>
                </div>

                <div class="form-actions" style="margin-top:16px">
                    <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan Format</button>
                </div>
            </form>
        </section>

        <div class="flex-col gap-16">
            <section class="card">
                <div class="card__head"><div><h2>Pratinjau Nomor Berikutnya</h2>
                    <div class="sub">Dihitung dari nomor urut terakhir tiap unit.</div></div></div>
                <div class="list-rows">
                    <?php foreach ($idFormat['preview'] as $preview): ?>
                        <div class="list-row">
                            <span class="avatar" style="background:<?= e(avatar_color($preview['unit'])) ?>"><?= icon('key') ?></span>
                            <div class="list-row__body">
                                <b class="mono"><?= e($preview['code']) ?></b>
                                <span><?= e($preview['unit']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$idFormat['preview']): ?>
                        <div class="table-empty">Tambahkan business unit untuk melihat pratinjau.</div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card">
                <div class="card__body">
                    <div class="small" style="font-weight:700;margin-bottom:6px">Catatan penting</div>
                    <ul class="small muted" style="padding-left:16px;line-height:1.75">
                        <li>Menyimpan format baru akan <b>mereset nomor urut</b>. ID karyawan yang sudah ada tidak berubah.</li>
                        <li>Panjang maksimal 60 karakter, hanya huruf, angka, <span class="mono">-</span>, <span class="mono">/</span>, dan token.</li>
                        <li>Token <span class="mono">{BU}</span> dan <span class="mono">{DEPT}</span> otomatis memakai kode unit/departemen karyawan.</li>
                        <li>Nomor urut selalu unik per cakupan dan dipakai berurutan.</li>
                    </ul>
                </div>
            </section>
        </div>
    </div>

<?php endif; ?>

<script>
document.querySelectorAll('[data-open-form]').forEach(function (link) {
    link.addEventListener('click', function () {
        var panel = document.getElementById('org-form');
        if (panel) { panel.open = true; }
    });
});
</script>
