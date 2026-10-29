<?php
/**
 * Pengaturan presensi.
 * @var array $settings, $units, $leaveTypes, $sample, $tiers, $tierMode, $errors, $old
 */
$val = static function (string $key, mixed $default = '') use ($old, $settings) {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    return $settings[$key] ?? $default;
};
$on = static fn (string $key, bool $default = true): bool => (string) $val($key, $default ? '1' : '0') === '1';
$type = (string) $val('attendance_late_deduction_type', 'PER_MINUTE');
$mode = (string) $val('attendance_late_tier_mode', $tierMode ?? 'DAILY');
$isProgressive = $type === 'PROGRESSIVE';
$hours = static function (int $minutes): string {
    if ($minutes === 0) { return '0'; }
    $h = $minutes / 60;
    $value = abs($h - round($h)) < 0.05 ? (int) round($h) : round($h, 1);
    return rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',') . ' jam';
};
?>
<div class="page-head">
    <div>
        <h1>Pengaturan Presensi</h1>
        <div class="sub">Aturan jam kerja, validasi geofence &amp; selfie, denda keterlambatan, serta penanganan alpha.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('organisation?tab=units') ?>"><?= icon('building') ?> Radius Geofence Unit</a>
        <a class="btn btn-soft" href="<?= url('attendance/daily') ?>"><?= icon('list') ?> Presensi Harian</a>
    </div>
</div>

<form method="post" action="<?= url('attendance/settings') ?>">
    <?= csrf_field() ?>
    <div class="split-grid">
        <div class="flex-col gap-16">

            <section class="card">
                <div class="card__head"><div><h2>Jam Kerja &amp; Toleransi</h2>
                    <div class="sub">Dipakai bila karyawan tidak memiliki jadwal shift pada hari tersebut.</div></div></div>
                <div class="card__body">
                    <div class="form-grid three">
                        <div class="field">
                            <label>Jam Masuk Standar <span class="req">*</span></label>
                            <input class="input" type="time" name="attendance_work_start" value="<?= e(substr((string) $val('attendance_work_start', '08:00'), 0, 5)) ?>" required>
                        </div>
                        <div class="field">
                            <label>Jam Pulang Standar <span class="req">*</span></label>
                            <input class="input" type="time" name="attendance_work_end" value="<?= e(substr((string) $val('attendance_work_end', '17:00'), 0, 5)) ?>" required>
                        </div>
                        <div class="field">
                            <label>Toleransi Terlambat (menit)</label>
                            <input class="input" type="number" name="attendance_late_tolerance" min="0" max="120" value="<?= e((int) $val('attendance_late_tolerance', 15)) ?>" required>
                            <span class="hint">Keterlambatan di bawah nilai ini dianggap tepat waktu.</span>
                        </div>
                    </div>
                    <div class="hint" style="margin-top:10px">
                        Setiap shift dapat menimpa nilai ini pada menu <a class="link-quiet" href="<?= url('shifts') ?>">Jadwal Kerja &amp; Shift</a>.
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><div><h2>Validasi Presensi</h2>
                    <div class="sub">Kontrol ketatnya bukti lokasi dan foto saat check-in/out.</div></div></div>
                <div class="card__body">
                    <div class="flex-col gap-12">
                        <label class="switch" style="justify-content:space-between;width:100%">
                            <span>
                                <b style="display:block;font-size:12.4px">Wajib selfie</b>
                                <span class="small muted">Karyawan harus mengambil foto saat presensi.</span>
                            </span>
                            <span style="display:flex;align-items:center;gap:10px">
                                <input type="checkbox" name="attendance_selfie_required" value="1" <?= $on('attendance_selfie_required') ? 'checked' : '' ?>>
                                <span class="switch__track"></span>
                            </span>
                        </label>
                        <div class="divider" style="margin:2px 0"></div>
                        <label class="switch" style="justify-content:space-between;width:100%">
                            <span>
                                <b style="display:block;font-size:12.4px">Wajib berada dalam radius geofence</b>
                                <span class="small muted">Presensi ditolak bila jarak melebihi radius unit.</span>
                            </span>
                            <span style="display:flex;align-items:center;gap:10px">
                                <input type="checkbox" name="attendance_geofence_required" value="1" <?= $on('attendance_geofence_required') ? 'checked' : '' ?>>
                                <span class="switch__track"></span>
                            </span>
                        </label>
                        <div class="divider" style="margin:2px 0"></div>
                        <label class="switch" style="justify-content:space-between;width:100%">
                            <span>
                                <b style="display:block;font-size:12.4px">Bebas geofence untuk WFH &amp; kerja lapangan</b>
                                <span class="small muted">Shift bertipe WFH/FIELD tetap dapat presensi dari luar kantor.</span>
                            </span>
                            <span style="display:flex;align-items:center;gap:10px">
                                <input type="checkbox" name="attendance_wfh_bypass_geofence" value="1" <?= $on('attendance_wfh_bypass_geofence') ? 'checked' : '' ?>>
                                <span class="switch__track"></span>
                            </span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><div><h2>Denda Keterlambatan</h2>
                    <div class="sub">Nilai ini dipakai pada rekap bulanan dan perhitungan payroll.</div></div></div>
                <div class="card__body">
                    <div class="form-grid three">
                        <div class="field">
                            <label>Status Denda</label>
                            <label class="switch" style="height:38px">
                                <input type="checkbox" name="attendance_late_deduction_enabled" value="1" <?= $on('attendance_late_deduction_enabled') ? 'checked' : '' ?>>
                                <span class="switch__track"></span>
                                <span>Aktif</span>
                            </label>
                        </div>
                        <div class="field">
                            <label>Perhitungan</label>
                            <select class="select" name="attendance_late_deduction_type" id="deduction-type" data-deduction-type>
                                <?php foreach ([
                                    'PER_MINUTE'  => 'Per menit terlambat',
                                    'PER_DAY'     => 'Per hari terlambat',
                                    'FIXED'       => 'Flat per kejadian',
                                    'PERCENT'     => 'Persentase gaji pokok',
                                    'PROGRESSIVE' => 'Progresif bertingkat (0–1, 1–2, 2–3, >3 jam)',
                                ] as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $type === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field" data-plain-value <?= $isProgressive ? 'hidden' : '' ?>>
                            <label>Nilai Denda (Rp)</label>
                            <input class="input" type="number" name="attendance_late_deduction_value" min="0" step="500" value="<?= e((float) $val('attendance_late_deduction_value', 2500)) ?>">
                            <span class="hint">Untuk tipe persentase, isi angka persen (mis. 0.5).</span>
                        </div>
                    </div>

                    <div class="tier-box" data-tier-box <?= $isProgressive ? '' : 'hidden' ?>>
                        <div class="tier-box__head">
                            <div>
                                <b>Denda Progresif Bertingkat</b>
                                <div class="small muted">Denda ditentukan oleh lamanya terlambat pada hari itu. Batas antar-tier mengikuti jam kerja nyata (60 menit = 1 jam).</div>
                            </div>
                            <div class="field" style="margin:0;min-width:210px">
                                <label style="font-size:10px">Dasar perhitungan</label>
                                <select class="select" name="attendance_late_tier_mode">
                                    <option value="DAILY" <?= $mode === 'DAILY' ? 'selected' : '' ?>>Per hari terlambat</option>
                                    <option value="MONTHLY" <?= $mode === 'MONTHLY' ? 'selected' : '' ?>>Akumulasi per bulan</option>
                                </select>
                            </div>
                        </div>

                        <div class="tier-grid">
                            <?php foreach (($tiers ?? []) as $index => $tier): ?>
                                <?php $n = $index + 1; ?>
                                <div class="tier-row">
                                    <div class="tier-row__badge">T<?= $n ?></div>
                                    <div class="tier-row__range">
                                        <?php if ($tier['max'] === null): ?>
                                            <?php $threshold = max(0, (int) $tier['min'] - 1); ?>
                                            <b>&gt; <?= $threshold ?> menit</b>
                                            <span>&gt; <?= e($hours($threshold)) ?></span>
                                        <?php else: ?>
                                            <b><?= (int) $tier['min'] ?>–<?= (int) $tier['max'] ?> menit</b>
                                            <span><?= e($hours((int) $tier['min'])) ?>–<?= e($hours((int) $tier['max'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($tier['max'] !== null): ?>
                                        <div class="field" style="margin:0">
                                            <label style="font-size:10px">Batas atas (menit)</label>
                                            <input class="input" type="number" name="attendance_late_tier_<?= $n ?>_max" min="1" max="1800" step="1"
                                                   value="<?= (int) $val('attendance_late_tier_' . $n . '_max', $tier['max']) ?>" data-tier-max="<?= $n ?>">
                                        </div>
                                    <?php else: ?>
                                        <div class="field" style="margin:0">
                                            <label style="font-size:10px">Batas atas</label>
                                            <input class="input" type="text" value="Tanpa batas" disabled>
                                        </div>
                                    <?php endif; ?>
                                    <div class="field" style="margin:0">
                                        <label style="font-size:10px">Denda (Rp)</label>
                                        <input class="input" type="number" name="attendance_late_tier_<?= $n ?>_amount" min="0" step="500"
                                               value="<?= e((float) $val('attendance_late_tier_' . $n . '_amount', $tier['amount'])) ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="small muted" style="margin-top:8px">
                            <b>Per hari</b>: setiap hari terlambat dikenakan denda sesuai tier-nya lalu dijumlahkan.
                            <b>Akumulasi per bulan</b>: total menit terlambat sebulan dipetakan ke satu tier dan dikenakan sekali.
                        </div>
                    </div>

                    <div class="divider"></div>
                    <div class="small" style="font-weight:700;margin-bottom:8px">
                        Simulasi denda — 3 hari terlambat dengan durasi yang sama tiap hari
                    </div>
                    <div class="table-wrap" style="border:1px solid var(--line);border-radius:10px">
                        <table class="data">
                            <thead><tr><th>Keterlambatan per hari</th><th class="num">Hari</th><th class="num">Total denda</th></tr></thead>
                            <tbody>
                            <?php foreach ($sample as $row): ?>
                                <tr>
                                    <td class="small"><?= number_id($row['minutes']) ?> menit
                                        <span class="muted">(<?= e($hours((int) $row['minutes'])) ?>)</span></td>
                                    <td class="num small"><?= (int) $row['days'] ?></td>
                                    <td class="num"><?= rupiah($row['amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="small muted" style="margin-top:6px">
                        Angka di atas mengikuti tipe perhitungan yang sedang <b>tersimpan</b>. Simpan pengaturan untuk memperbarui simulasi.
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><div><h2>Penanganan Alpha</h2>
                    <div class="sub">Karyawan tanpa presensi dapat dikonversi menjadi potongan cuti atau gaji.</div></div></div>
                <div class="card__body">
                    <div class="form-grid">
                        <div class="field">
                            <label>Aktifkan Penanganan Alpha</label>
                            <label class="switch" style="height:38px">
                                <input type="checkbox" name="attendance_absent_deduction_enabled" value="1" <?= $on('attendance_absent_deduction_enabled') ? 'checked' : '' ?>>
                                <span class="switch__track"></span>
                                <span>Otomatis pada rekap</span>
                            </label>
                        </div>
                        <div class="field">
                            <label>Potong Gaji</label>
                            <label class="switch" style="height:38px">
                                <input type="checkbox" name="attendance_absent_deduct_salary" value="1" <?= $on('attendance_absent_deduct_salary', false) ? 'checked' : '' ?>>
                                <span class="switch__track"></span>
                                <span>Hitung potongan gaji harian</span>
                            </label>
                        </div>
                        <div class="field">
                            <label>Jenis Cuti Potongan</label>
                            <select class="select" name="attendance_absent_leave_type_id">
                                <option value="0">Tidak memotong cuti</option>
                                <?php foreach ($leaveTypes as $leave): ?>
                                    <option value="<?= (int) $leave['id'] ?>" <?= (int) $val('attendance_absent_leave_type_id', 0) === (int) $leave['id'] ? 'selected' : '' ?>>
                                        <?= e($leave['name']) ?> (<?= e($leave['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Hari Cuti Terpotong per Alpha</label>
                            <input class="input" type="number" name="attendance_absent_leave_days" min="0" step="0.5" value="<?= e((float) $val('attendance_absent_leave_days', 1)) ?>">
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="flex-col gap-16">
            <section class="card">
                <div class="card__head"><div><h2>Kualitas Foto</h2>
                    <div class="sub">Foto dikompres di server agar hemat kuota.</div></div></div>
                <div class="card__body">
                    <div class="flex-col gap-12">
                        <div class="field">
                            <label>Lebar Maksimum (px)</label>
                            <input class="input" type="number" name="attendance_photo_max_width" min="240" max="1600" step="40" value="<?= e((int) $val('attendance_photo_max_width', 720)) ?>" required>
                        </div>
                        <div class="field">
                            <label>Kualitas JPEG (40–90)</label>
                            <input class="input" type="number" name="attendance_photo_quality" min="40" max="90" value="<?= e((int) $val('attendance_photo_quality', 70)) ?>" required>
                        </div>
                        <div class="hint">Foto disimpan di <span class="mono">storage/uploads/attendance</span> dan diberi cap waktu otomatis.</div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><div><h2>Geofence per Unit</h2>
                    <div class="sub">Titik pusat &amp; radius tiap entitas.</div></div></div>
                <div class="list-rows">
                    <?php foreach ($units as $unit): ?>
                        <div class="list-row">
                            <span class="avatar" style="background:<?= e(avatar_color($unit['code'])) ?>"><?= e(substr($unit['code'], 0, 2)) ?></span>
                            <div class="list-row__body">
                                <b><?= e($unit['name']) ?></b>
                                <span>
                                    <?php if ($unit['latitude'] !== null): ?>
                                        <span class="mono"><?= e(number_format((float) $unit['latitude'], 4)) ?>, <?= e(number_format((float) $unit['longitude'], 4)) ?></span>
                                        · <?= (int) $unit['geofence_radius'] ?> m
                                    <?php else: ?>
                                        Koordinat belum diatur
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?= (int) $unit['is_active'] === 1 ? '<span class="badge badge-teal">Aktif</span>' : '<span class="badge badge-slate">Off</span>' ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card__foot" style="padding:12px 16px">
                    <a class="link-quiet small" href="<?= url('organisation?tab=units') ?>">Ubah koordinat &amp; radius <?= icon('arrow-right', 'ico') ?></a>
                </div>
            </section>

            <div class="card">
                <div class="card__body">
                    <div class="form-actions" style="flex-direction:column;align-items:stretch;gap:8px">
                        <button class="btn btn-primary btn-block" type="submit"><?= icon('check') ?> Simpan Pengaturan</button>
                        <a class="btn btn-ghost btn-block" href="<?= url('dashboard') ?>">Batal</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    var type = document.querySelector('[data-deduction-type]');
    var tierBox = document.querySelector('[data-tier-box]');
    var plain = document.querySelector('[data-plain-value]');
    if (!type) { return; }

    var sync = function () {
        var progressive = type.value === 'PROGRESSIVE';
        if (tierBox) { tierBox.hidden = !progressive; }
        // Field nilai tunggal disembunyikan (bukan dinonaktifkan) agar nilai lama tetap terkirim.
        if (plain) { plain.hidden = progressive; }
    };
    type.addEventListener('change', sync);
    sync();

    /* Jaga agar batas tier tetap naik saat diisi. */
    document.querySelectorAll('[data-tier-max]').forEach(function (input) {
        input.addEventListener('change', function () {
            var order = Number(input.getAttribute('data-tier-max'));
            var fields = Array.prototype.slice.call(document.querySelectorAll('[data-tier-max]'))
                .sort(function (a, b) { return Number(a.getAttribute('data-tier-max')) - Number(b.getAttribute('data-tier-max')); });
            var index = fields.indexOf(input);
            var value = parseInt(input.value || '0', 10);
            if (index > 0) {
                var prev = parseInt(fields[index - 1].value || '0', 10);
                if (value <= prev) { input.value = String(prev + 1); }
            }
            if (index < fields.length - 1) {
                var next = fields[index + 1];
                var now = parseInt(input.value || '0', 10);
                if (parseInt(next.value || '0', 10) <= now) { next.value = String(now + 1); }
            }
            void order;
        });
    });
})();
</script>
