<?php
/**
 * Create / edit employee.
 * @var array|null $employee
 * @var array $units, $departments, $positions, $managers, $roles, $statuses, $taxStatuses, $errors, $old
 * @var string $nextCode, $idFormat
 */
$isEdit = is_array($employee);
$action = $isEdit ? url('employees/' . $employee['id']) : url('employees');
$val = static function (string $key, mixed $default = '') use ($old, $employee) {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    if (is_array($employee) && array_key_exists($key, $employee)) {
        return $employee[$key];
    }
    return $default;
};
$err = static fn (string $key): string => isset($errors[$key])
    ? '<span class="err">' . e($errors[$key]) . '</span>'
    : '';
$unitId = (int) $val('business_unit_id', 0);
$deptId = (int) $val('department_id', 0);
$positionId = (int) $val('position_id', 0);
$photo = $isEdit ? ($employee['photo'] ?? null) : null;
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Ubah Data Karyawan' : 'Tambah Karyawan' ?></h1>
        <div class="sub">
            <?= $isEdit
                ? e($employee['full_name']) . ' · ' . e($employee['employee_code'])
                : 'Lengkapi data kepegawaian. ID karyawan dibuat otomatis mengikuti format ' . e($idFormat) . '.' ?>
        </div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= $isEdit ? url('employees/' . $employee['id']) : url('employees') ?>"><?= icon('arrow-left') ?> Kembali</a>
    </div>
</div>

<form method="post" action="<?= $action ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>

    <div class="split-grid wide-left">
        <div class="flex-col gap-16">

            <section class="card">
                <div class="card__head">
                    <div><h2>Identitas Karyawan</h2>
                        <div class="sub">Data ini tercetak pada slip gaji dan dokumen kepegawaian.</div></div>
                    <span class="tag-soft mono"><?= e($isEdit ? $employee['employee_code'] : $nextCode) ?></span>
                </div>
                <div class="card__body">
                    <div class="form-grid">
                        <div class="field">
                            <label>Nama Lengkap <span class="req">*</span></label>
                            <input class="input" name="full_name" value="<?= e($val('full_name')) ?>" required maxlength="150" placeholder="Nama sesuai KTP">
                            <?= $err('full_name') ?>
                        </div>
                        <div class="field">
                            <label>Nama Panggilan</label>
                            <input class="input" name="nickname" value="<?= e($val('nickname')) ?>" maxlength="60" placeholder="Nama di lingkungan kerja">
                        </div>
                        <div class="field">
                            <label>ID Karyawan</label>
                            <input class="input mono" name="employee_code" value="<?= e($isEdit ? $employee['employee_code'] : $nextCode) ?>" maxlength="30">
                            <span class="hint"><?= $isEdit ? 'Ubah hanya jika diperlukan.' : 'Dibuat otomatis, dapat disesuaikan.' ?></span>
                        </div>
                        <div class="field">
                            <label>Email Perusahaan <span class="req">*</span></label>
                            <input class="input" type="email" name="email" value="<?= e($val('email')) ?>" required maxlength="150" placeholder="nama@asiaraya.co.id">
                            <span class="hint">Digunakan untuk login (termasuk Google Sign-In).</span>
                            <?= $err('email') ?>
                        </div>
                        <div class="field">
                            <label>Nomor HP</label>
                            <input class="input" name="phone" value="<?= e($val('phone')) ?>" maxlength="30" placeholder="08xxxxxxxxxx">
                        </div>
                        <div class="field">
                            <label>Tanggal Lahir</label>
                            <input class="input" type="date" name="birth_date" value="<?= e($val('birth_date')) ?>">
                        </div>
                        <div class="field">
                            <label>Jenis Kelamin</label>
                            <select class="select" name="gender">
                                <?php foreach (['' => 'Belum ditentukan', 'Laki-laki' => 'Laki-laki', 'Perempuan' => 'Perempuan'] as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= (string) $val('gender') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Status Pernikahan</label>
                            <select class="select" name="marital_status">
                                <?php foreach (['' => 'Belum ditentukan', 'Belum Menikah' => 'Belum Menikah', 'Menikah' => 'Menikah', 'Cerai' => 'Cerai'] as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= (string) $val('marital_status') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Agama</label>
                            <select class="select" name="religion">
                                <?php foreach (['', 'Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'] as $religion): ?>
                                    <option value="<?= e($religion) ?>" <?= (string) $val('religion') === $religion ? 'selected' : '' ?>><?= $religion === '' ? 'Belum ditentukan' : e($religion) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field full">
                            <label>Alamat Domisili</label>
                            <input class="input" name="address" value="<?= e($val('address')) ?>" maxlength="255" placeholder="Jalan, nomor, kelurahan, kota">
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card__head">
                    <div><h2>Penempatan &amp; Atribusi</h2>
                        <div class="sub">Menentukan geofence presensi dan jalur persetujuan atasan langsung.</div></div>
                </div>
                <div class="card__body">
                    <div class="form-grid">
                        <div class="field">
                            <label>Business Unit <span class="req">*</span></label>
                            <select class="select" name="business_unit_id" id="field-unit" required>
                                <option value="">Pilih unit…</option>
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?= (int) $unit['id'] ?>" <?= $unitId === (int) $unit['id'] ? 'selected' : '' ?>>
                                        <?= e($unit['name']) ?> (<?= e($unit['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= $err('business_unit_id') ?>
                        </div>
                        <div class="field">
                            <label>Departemen</label>
                            <select class="select" name="department_id" id="field-department">
                                <option value="">Pilih departemen…</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= (int) $dept['id'] ?>" data-unit="<?= (int) ($dept['business_unit_id'] ?? 0) ?>" <?= $deptId === (int) $dept['id'] ? 'selected' : '' ?>>
                                        <?= e($dept['name']) ?> (<?= e($dept['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Posisi / Jabatan</label>
                            <select class="select" name="position_id" id="field-position">
                                <option value="">Pilih posisi…</option>
                                <?php foreach ($positions as $position): ?>
                                    <option value="<?= (int) $position['id'] ?>"
                                            data-department="<?= (int) ($position['department_id'] ?? 0) ?>"
                                            data-salary="<?= (float) ($position['basic_salary'] ?? 0) ?>"
                                            <?= $positionId === (int) $position['id'] ? 'selected' : '' ?>>
                                        <?= e($position['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="hint">Memilih posisi akan mengisi gaji pokok standar.</span>
                        </div>
                        <div class="field">
                            <label>Atasan Langsung</label>
                            <select class="select" name="direct_manager_id">
                                <option value="">Tidak ada atasan</option>
                                <?php foreach ($managers as $manager): ?>
                                    <option value="<?= (int) $manager['id'] ?>" <?= (int) $val('direct_manager_id') === (int) $manager['id'] ? 'selected' : '' ?>>
                                        <?= e($manager['full_name']) ?> · <?= e(role_label($manager['role'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="hint">Atasan menerima pengajuan cuti, lembur, dan tukar shift.</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card__head">
                    <div><h2>Kontrak &amp; Remunerasi</h2>
                        <div class="sub">Status PKWT, Magang, dan Outsourcing wajib memiliki tanggal akhir kontrak.</div></div>
                </div>
                <div class="card__body">
                    <div class="form-grid three">
                        <div class="field">
                            <label>Status Kepegawaian <span class="req">*</span></label>
                            <select class="select" name="employment_status" required>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= e($status) ?>" <?= $val('employment_status', 'PKWT') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $err('employment_status') ?>
                        </div>
                        <div class="field">
                            <label>Tanggal Bergabung</label>
                            <input class="input" type="date" name="join_date" value="<?= e($val('join_date', date('Y-m-d'))) ?>">
                            <?= $err('join_date') ?>
                        </div>
                        <div class="field">
                            <label>Gaji Pokok (Rp)</label>
                            <input class="input" type="number" name="basic_salary" id="field-salary" min="0" step="50000" value="<?= e((float) $val('basic_salary', 0)) ?>">
                            <?= $err('basic_salary') ?>
                        </div>
                        <div class="field">
                            <label>Awal Kontrak</label>
                            <input class="input" type="date" name="contract_start" value="<?= e($val('contract_start')) ?>">
                        </div>
                        <div class="field">
                            <label>Akhir Kontrak</label>
                            <input class="input" type="date" name="contract_end" value="<?= e($val('contract_end')) ?>">
                            <?= $err('contract_end') ?>
                        </div>
                        <div class="field">
                            <label>Status Pajak (PTKP)</label>
                            <select class="select" name="tax_status">
                                <?php foreach ($taxStatuses as $tax): ?>
                                    <option value="<?= e($tax) ?>" <?= $val('tax_status', 'TK/0') === $tax ? 'selected' : '' ?>><?= e($tax) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card__head">
                    <div><h2>Bank &amp; Jaminan Sosial</h2>
                        <div class="sub">Rekening payroll, BPJS, dan NPWP untuk pelaporan pajak.</div></div>
                </div>
                <div class="card__body">
                    <div class="form-grid three">
                        <div class="field">
                            <label>Nama Bank</label>
                            <input class="input" name="bank_name" value="<?= e($val('bank_name')) ?>" placeholder="BCA / Mandiri / BNI">
                        </div>
                        <div class="field">
                            <label>Nama Pemilik Rekening</label>
                            <input class="input" name="bank_account_name" value="<?= e($val('bank_account_name')) ?>" placeholder="Sesuai buku tabungan">
                        </div>
                        <div class="field">
                            <label>Nomor Rekening</label>
                            <input class="input mono" name="bank_account_number" value="<?= e($val('bank_account_number')) ?>">
                        </div>
                        <div class="field">
                            <label>NPWP</label>
                            <input class="input mono" name="npwp" value="<?= e($val('npwp')) ?>">
                        </div>
                        <div class="field">
                            <label>BPJS Kesehatan</label>
                            <input class="input mono" name="bpjs_kesehatan_no" value="<?= e($val('bpjs_kesehatan_no')) ?>">
                        </div>
                        <div class="field">
                            <label>BPJS Ketenagakerjaan</label>
                            <input class="input mono" name="bpjs_tk_no" value="<?= e($val('bpjs_tk_no')) ?>">
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="flex-col gap-16">
            <section class="card">
                <div class="card__head"><div><h2>Foto Karyawan</h2>
                    <div class="sub">JPG/PNG/WEBP, maksimal 4 MB.</div></div></div>
                <div class="card__body">
                    <div class="photo-field" style="margin-bottom:12px">
                        <?php if ($photo): ?>
                            <span class="avatar xl"><img src="<?= url('media/employees/' . basename($photo)) ?>" alt=""></span>
                        <?php else: ?>
                            <span class="avatar xl" style="background:<?= e(avatar_color((string) $val('email', 'new'))) ?>">
                                <?= e(initials((string) $val('full_name', 'Karyawan Baru'))) ?>
                            </span>
                        <?php endif; ?>
                        <div class="flex-col" style="gap:6px;flex:1;min-width:0">
                            <input class="input file-input" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                            <?php if ($photo): ?>
                                <label class="checkbox small"><input type="checkbox" name="remove_photo" value="1"> Hapus foto saat ini</label>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="hint small muted">Foto disimpan di luar document root dan disajikan melalui rute <span class="mono">/media</span>.</div>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><div><h2>Akun &amp; Akses</h2>
                    <div class="sub">Role menentukan menu dan hak akses HRIS.</div></div></div>
                <div class="card__body">
                    <div class="flex-col gap-12">
                        <div class="field">
                            <label>Role Akses</label>
                            <select class="select" name="role">
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= e($role) ?>" <?= $val('role', 'KARYAWAN') === $role ? 'selected' : '' ?>><?= e(role_label($role)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $err('role') ?>
                        </div>
                        <div class="field">
                            <label>Username</label>
                            <input class="input" name="username" value="<?= e($val('username')) ?>" maxlength="60" placeholder="otomatis dari email">
                        </div>
                        <div class="field">
                            <label><?= $isEdit ? 'Kata Sandi Baru' : 'Kata Sandi Awal' ?></label>
                            <input class="input" type="password" name="password" minlength="8" autocomplete="new-password" placeholder="<?= $isEdit ? 'Biarkan kosong jika tidak diubah' : 'Kosongkan untuk Password123!' ?>">
                            <?= $err('password') ?>
                        </div>
                        <?php if ($isEdit): ?>
                            <div class="small muted" style="line-height:1.6">
                                Login terakhir: <?= $employee['last_login_at'] ? tanggal($employee['last_login_at'], 'd M Y H:i') : 'belum pernah' ?><br>
                                Google ID: <?= $employee['google_id'] ? '<span class="badge badge-teal">Terhubung</span>' : '<span class="badge badge-slate">Belum terhubung</span>' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <div class="card">
                <div class="card__body">
                    <div class="form-actions" style="flex-direction:column;align-items:stretch;gap:8px">
                        <button class="btn btn-primary btn-block" type="submit"><?= icon('check') ?> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan Karyawan' ?></button>
                        <a class="btn btn-ghost btn-block" href="<?= $isEdit ? url('employees/' . $employee['id']) : url('employees') ?>">Batal</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    var unit = document.getElementById('field-unit');
    var dept = document.getElementById('field-department');
    var position = document.getElementById('field-position');
    var salary = document.getElementById('field-salary');
    if (!unit || !dept) { return; }

    function filterDepartments() {
        var selected = unit.value;
        var current = dept.value;
        var stillVisible = false;
        Array.prototype.forEach.call(dept.options, function (opt) {
            if (!opt.value) { return; }
            var match = !selected || opt.dataset.unit === selected;
            opt.hidden = !match;
            opt.disabled = !match;
            if (opt.selected && match) { stillVisible = true; }
        });
        if (!stillVisible) { dept.value = ''; }
        filterPositions();
    }

    function filterPositions() {
        if (!position) { return; }
        var selected = dept.value;
        var current = position.value;
        var stillVisible = false;
        Array.prototype.forEach.call(position.options, function (opt) {
            if (!opt.value) { return; }
            var optDept = opt.dataset.department || '0';
            var match = !selected || optDept === selected || optDept === '0';
            opt.hidden = !match;
            opt.disabled = !match;
            if (opt.selected && match) { stillVisible = true; }
        });
        if (!stillVisible) { position.value = ''; }
    }

    unit.addEventListener('change', filterDepartments);
    dept.addEventListener('change', filterPositions);

    if (position && salary) {
        position.addEventListener('change', function () {
            var opt = position.options[position.selectedIndex];
            var value = opt ? parseFloat(opt.dataset.salary || '0') : 0;
            if (value > 0) { salary.value = value; }
        });
    }

    filterDepartments();
})();
</script>
