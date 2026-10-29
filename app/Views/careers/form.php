<?php
/**
 * Catat perubahan jabatan (HR) — Batch 19.
 * @var array|null $employee
 * @var array $employees, $types, $units, $departments, $positions, $statuses, $history, $errors, $old
 */
use App\Models\CareerModel;

$err = static fn (string $key): string => isset($errors[$key]) ? '<span class="err">' . e($errors[$key]) . '</span>' : '';
$selected = (int) ($old['employee_id'] ?? ($employee['id'] ?? 0));
$typeNow = (string) ($old['change_type'] ?? 'INITIAL');
?>
<div class="page-head">
    <div>
        <h1>Catat Perubahan Jabatan</h1>
        <div class="sub">Satu formulir untuk pengangkatan, promosi, mutasi, perpanjangan kontrak, hingga penyesuaian gaji.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('careers') ?>"><?= icon('arrow-left') ?> Kembali</a>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head"><div><h2>Data Perubahan</h2>
            <div class="sub">Kolom bertanda * wajib diisi.</div></div></div>
        <form class="card__body" method="post" action="<?= url('careers') ?>">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="field full">
                    <label>Karyawan <span class="req">*</span></label>
                    <select class="select" name="employee_id" required>
                        <option value="">Pilih karyawan…</option>
                        <?php foreach ($employees as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= $selected === (int) $row['id'] ? 'selected' : '' ?>>
                                <?= e($row['full_name']) ?> · <?= e($row['employee_code']) ?>
                                <?= $row['flag'] !== 'ACTIVE' ? ' (nonaktif)' : '' ?><?= $row['position_name'] ? ' — ' . e($row['position_name']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('employee_id') ?>
                </div>

                <div class="field">
                    <label>Jenis Perubahan <span class="req">*</span></label>
                    <select class="select" name="change_type" required>
                        <?php foreach ($types as $key => $meta): ?>
                            <option value="<?= e($key) ?>" <?= $typeNow === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('change_type') ?>
                </div>

                <div class="field">
                    <label>Nomor SK / Surat</label>
                    <input class="input" name="sk_number" maxlength="80" value="<?= e($old['sk_number'] ?? '') ?>" placeholder="Contoh: 042/SK-HR/X/2026">
                    <?= $err('sk_number') ?>
                </div>

                <div class="field">
                    <label>Tanggal Efektif <span class="req">*</span></label>
                    <input class="input" type="date" name="effective_date" required value="<?= e($old['effective_date'] ?? date('Y-m-d')) ?>">
                    <?= $err('effective_date') ?>
                </div>

                <div class="field">
                    <label>Berlaku Sampai</label>
                    <input class="input" type="date" name="end_date" value="<?= e($old['end_date'] ?? '') ?>">
                    <span class="hint">Untuk kontrak berjangka (PKWT).</span>
                    <?= $err('end_date') ?>
                </div>
            </div>

            <div class="divider" style="margin:16px 0"></div>

            <div class="form-grid">
                <div class="field">
                    <label>Unit Bisnis Baru</label>
                    <select class="select" name="business_unit_id" id="field-unit">
                        <option value="">— tidak berubah —</option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= (int) $unit['id'] ?>"><?= e($unit['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Departemen Baru</label>
                    <select class="select" name="department_id" id="field-department">
                        <option value="">— tidak berubah —</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= (int) $dept['id'] ?>" data-unit="<?= (int) $dept['business_unit_id'] ?>"><?= e($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Jabatan Baru</label>
                    <select class="select" name="position_id" id="field-position">
                        <option value="">— tidak berubah —</option>
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?= (int) $pos['id'] ?>"
                                    data-department="<?= (int) $pos['department_id'] ?>"
                                    data-salary="<?= (float) ($pos['basic_salary'] ?? 0) ?>"><?= e($pos['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Status Kepegawaian</label>
                    <select class="select" name="employment_status">
                        <option value="">— tidak berubah —</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= e($status) ?>"><?= e($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Gaji Pokok Baru</label>
                    <input class="input" type="number" name="basic_salary" id="field-salary" min="0" step="100000" value="<?= e($old['basic_salary'] ?? '') ?>" placeholder="Kosongkan bila tetap">
                    <span class="hint">Isi hanya bila ada penyesuaian.</span>
                    <?= $err('basic_salary') ?>
                </div>
                <div class="field">
                    <label>Catatan</label>
                    <input class="input" name="note" maxlength="255" value="<?= e($old['note'] ?? '') ?>" placeholder="Alasan / pertimbangan perubahan">
                    <?= $err('note') ?>
                </div>
            </div>

            <div class="alert alert-info" style="margin-top:16px">
                <?= icon('info') ?>
                <div>Centang <b>Perbarui data karyawan</b> agar unit, departemen, jabatan, status, dan gaji pokok di master kepegawaian langsung mengikuti catatan ini.</div>
            </div>

            <div class="flex gap-8 wrap" style="margin-top:14px;align-items:center">
                <label class="checkbox"><input type="checkbox" name="apply" value="1" checked><span>Perbarui data karyawan</span></label>
            </div>

            <div class="form-actions" style="margin-top:16px">
                <a class="btn btn-ghost" href="<?= url('careers') ?>">Batal</a>
                <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan Perubahan</button>
            </div>
        </form>
    </section>

    <div class="flex-col gap-16">
        <?php if ($employee): ?>
            <section class="card">
                <div class="card__head"><div><h2>Data Saat Ini</h2></div></div>
                <div class="card__body" style="padding-top:6px">
                    <div class="detail-list">
                        <div class="detail-list__row"><span>Nama</span><b><?= e($employee['full_name']) ?></b></div>
                        <div class="detail-list__row"><span>NIK</span><b class="mono"><?= e($employee['employee_code']) ?></b></div>
                        <div class="detail-list__row"><span>Unit</span><b><?= e($employee['unit_name'] ?: '—') ?></b></div>
                        <div class="detail-list__row"><span>Departemen</span><b><?= e($employee['department_name'] ?: '—') ?></b></div>
                        <div class="detail-list__row"><span>Jabatan</span><b><?= e($employee['position_name'] ?: '—') ?></b></div>
                        <div class="detail-list__row"><span>Status</span><b><?= e((string) ($employee['employment_status'] ?? '—')) ?></b></div>
                        <div class="detail-list__row"><span>Gaji pokok</span><b><?= rupiah((float) $employee['basic_salary']) ?></b></div>
                    </div>
                </div>
            </section>

            <?php if ($history): ?>
                <section class="card">
                    <div class="card__head"><div><h2>Riwayat Terakhir</h2>
                        <div class="sub">5 catatan terbaru.</div></div></div>
                    <div class="card__body" style="padding-top:6px">
                        <div class="timeline">
                            <?php foreach ($history as $item): ?>
                                <div class="timeline__item">
                                    <b><?= e(CareerModel::typeLabel((string) $item['change_type'])) ?></b>
                                    <span><?= tanggal((string) $item['effective_date']) ?>
                                        <?= $item['position_name'] ? ' · ' . e($item['position_name']) : '' ?>
                                        <?= $item['note'] ? ' — ' . e($item['note']) : '' ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <section class="card">
            <div class="card__head"><div><h2>Panduan Singkat</h2></div></div>
            <div class="card__body small muted" style="line-height:1.7">
                <p><b>Pengangkatan Awal</b> — catat saat karyawan pertama kali masuk; biasanya bertepatan dengan tanggal bergabung.</p>
                <p><b>Promosi / Demosi</b> — sertakan nomor SK dan jabatan baru.</p>
                <p><b>Mutasi</b> — pindah unit atau departemen tanpa mengubah level jabatan.</p>
                <p><b>Penyesuaian Gaji</b> — isi gaji pokok baru; nilai sebelumnya tersimpan otomatis untuk perbandingan.</p>
            </div>
        </section>
    </div>
</div>

<script>
(function () {
    var unit = document.getElementById('field-unit');
    var dept = document.getElementById('field-department');
    var position = document.getElementById('field-position');
    var salary = document.getElementById('field-salary');
    var salaryTouched = Boolean(salary && salary.value);
    if (!unit || !dept || !position) { return; }

    function filterDepartments() {
        var selected = unit.value;
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
        var selected = dept.value;
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

    if (salary) {
        salary.addEventListener('input', function () { salaryTouched = true; });
        position.addEventListener('change', function () {
            if (salaryTouched) { return; }
            var opt = position.options[position.selectedIndex];
            var value = opt ? parseFloat(opt.dataset.salary || '0') : 0;
            if (value > 0) { salary.value = value; }
        });
    }

    filterDepartments();
})();
</script>
