<?php
/**
 * Shift master data.
 * @var array $shifts, $members, $units, $errors, $old
 * @var array|null $editing
 * @var array $stats, $dayLabels
 * @var int $unitFilter, $tolerance
 */
use App\Models\ScheduleModel;

$editing = $editing ?? null;
$isEdit = is_array($editing);
$action = $isEdit ? url('shifts/' . $editing['id']) : url('shifts');
$val = static function (string $key, mixed $default = '') use ($old, $editing) {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    if (is_array($editing) && array_key_exists($key, $editing)) {
        return $editing[$key];
    }
    return $default;
};
$selectedDays = $val('work_days', '1,2,3,4,5');
if (is_array($selectedDays)) {
    $selectedDays = implode(',', $selectedDays);
}
$selectedDays = array_map('intval', array_filter(explode(',', (string) $selectedDays)));
$types = ['NORMAL' => 'Normal (non-shift)', 'SHIFT' => 'Shift bergilir', 'WFH' => 'Work from home', 'FIELD' => 'Kerja lapangan'];
$err = static fn (string $key): string => isset($errors[$key]) ? '<span class="err">' . e($errors[$key]) . '</span>' : '';
?>
<div class="page-head">
    <div>
        <h1>Jadwal Kerja &amp; Shift</h1>
        <div class="sub">Definisikan jam kerja, toleransi keterlambatan, dan hari kerja untuk setiap pola shift.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('schedules') ?>"><?= icon('calendar-check') ?> Penjadwalan</a>
        <a class="btn btn-primary" href="<?= url('shifts?tambah=1#shift-form') ?>" data-open-form><?= icon('plus') ?> Tambah Shift</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Shift Aktif</span>
        <span class="v"><?= number_id($stats['shifts']) ?></span>
        <span class="s">Pola jam kerja terdaftar</span>
    </div>
    <div class="mini-stat">
        <span class="k">Terjadwal Minggu Ini</span>
        <span class="v"><?= number_id($stats['scheduled']) ?> <span style="font-size:11px;color:var(--muted)">/ <?= number_id($stats['active_now']) ?></span></span>
        <span class="s"><?= number_id($stats['unscheduled']) ?> karyawan belum terjadwal</span>
    </div>
    <div class="mini-stat">
        <span class="k">Total Penugasan</span>
        <span class="v"><?= number_id($stats['assignments']) ?></span>
        <span class="s">Rentang jadwal aktif</span>
    </div>
    <div class="mini-stat">
        <span class="k">Pengajuan Perubahan</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($stats['pending']) ?></span>
        <span class="s">Menunggu keputusan</span>
    </div>
</div>

<details class="form-panel" id="shift-form" <?= $isEdit ? 'open' : '' ?>>
    <summary>
        <span class="form-panel__title"><?= icon($isEdit ? 'edit' : 'plus', 'ico') ?>
            <?= $isEdit ? 'Ubah Shift — ' . e($editing['name']) : 'Tambah Shift Baru' ?></span>
        <span class="muted small">Jam masuk, jam pulang, istirahat, dan toleransi</span>
    </summary>
    <form method="post" action="<?= $action ?>" class="form-panel__body">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>
        <div class="form-grid three">
            <div class="field">
                <label>Nama Shift <span class="req">*</span></label>
                <input class="input" name="name" value="<?= e($val('name')) ?>" placeholder="Shift Pagi" required maxlength="100">
                <?= $err('name') ?>
            </div>
            <div class="field">
                <label>Kode <span class="req">*</span></label>
                <input class="input mono" name="code" value="<?= e($val('code')) ?>" placeholder="SHIFT-PAGI" required maxlength="20">
                <?= $err('code') ?>
            </div>
            <div class="field">
                <label>Tipe Operasional</label>
                <select class="select" name="operational_type">
                    <?php foreach ($types as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $val('operational_type', 'NORMAL') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Jam Masuk <span class="req">*</span></label>
                <input class="input" type="time" name="check_in_time" value="<?= e($val('check_in_time', '08:00')) ?>" required>
            </div>
            <div class="field">
                <label>Jam Pulang <span class="req">*</span></label>
                <input class="input" type="time" name="check_out_time" value="<?= e($val('check_out_time', '17:00')) ?>" required>
            </div>
            <div class="field">
                <label>Istirahat (menit)</label>
                <input class="input" type="number" name="break_minutes" min="0" max="180" value="<?= e($val('break_minutes', 60)) ?>">
            </div>
            <div class="field">
                <label>Toleransi Terlambat (menit)</label>
                <input class="input" type="number" name="late_tolerance_minutes" min="0" max="120" value="<?= e($val('late_tolerance_minutes', $tolerance)) ?>">
                <span class="hint">Lewat batas ini presensi tercatat terlambat.</span>
            </div>
            <div class="field">
                <label>Warna Penanda</label>
                <input class="input" type="color" name="color" value="<?= e($val('color', '#0d9488')) ?>" style="padding:4px;height:38px">
            </div>
            <div class="field">
                <label>Business Unit</label>
                <select class="select" name="business_unit_id">
                    <option value="">Semua unit</option>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= (int) $unit['id'] ?>" <?= (int) $val('business_unit_id') === (int) $unit['id'] ? 'selected' : '' ?>>
                            <?= e($unit['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field full">
                <label>Hari Kerja</label>
                <div class="day-chips">
                    <?php foreach ($dayLabels as $day => $label): ?>
                        <label class="day-chip">
                            <input type="checkbox" name="work_days[]" value="<?= $day ?>" <?= in_array($day, $selectedDays, true) ? 'checked' : '' ?>>
                            <span><?= e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <span class="hint">Hari di luar pilihan ditandai sebagai hari libur pada matriks penjadwalan.</span>
            </div>
            <div class="field">
                <label>Status</label>
                <label class="switch" style="height:38px">
                    <input type="checkbox" name="is_active" value="1" <?= (!$isEdit || (int) $val('is_active', 1) === 1) ? 'checked' : '' ?>>
                    <span class="switch__track"></span>
                    <span>Shift aktif</span>
                </label>
            </div>
        </div>
        <div class="form-actions">
            <?php if ($isEdit): ?>
                <a class="btn btn-ghost" href="<?= url('shifts') ?>">Batal</a>
            <?php endif; ?>
            <button class="btn btn-primary" type="submit"><?= icon('check') ?> <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Shift' ?></button>
        </div>
    </form>
</details>

<div class="filter-bar" style="border:1px solid var(--line);border-radius:12px;background:#fff;margin-bottom:16px">
    <form method="get" action="<?= url('shifts') ?>" class="flex wrap" style="gap:8px;align-items:center;flex:1">
        <select class="select" name="unit" data-auto-submit style="min-width:190px">
            <option value="">Semua business unit</option>
            <?php foreach ($units as $unit): ?>
                <option value="<?= (int) $unit['id'] ?>" <?= $unitFilter === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
    </form>
    <span class="small muted"><?= count($shifts) ?> shift</span>
</div>

<div class="shift-grid">
    <?php foreach ($shifts as $shift): ?>
        <?php
        $duration = ScheduleModel::durationHours($shift);
        $members = $members[(int) $shift['id']] ?? [];
        $overnight = ($shift['check_out_time'] <= $shift['check_in_time']);
        ?>
        <article class="shift-card <?= (int) $shift['is_active'] === 1 ? '' : 'is-inactive' ?>">
            <div class="shift-card__top" style="--shift:<?= e($shift['color']) ?>">
                <div class="shift-card__time">
                    <span><?= e(substr($shift['check_in_time'], 0, 5)) ?></span>
                    <span class="shift-card__arrow">→</span>
                    <span><?= e(substr($shift['check_out_time'], 0, 5)) ?></span>
                </div>
                <span class="shift-card__dot" style="background:<?= e($shift['color']) ?>"></span>
            </div>
            <div class="shift-card__body">
                <div class="flex-between" style="align-items:flex-start">
                    <div>
                        <h3><?= e($shift['name']) ?></h3>
                        <div class="small muted mono"><?= e($shift['code']) ?></div>
                    </div>
                    <?= (int) $shift['is_active'] === 1 ? '<span class="badge badge-teal">Aktif</span>' : '<span class="badge badge-slate">Nonaktif</span>' ?>
                </div>

                <div class="shift-card__meta">
                    <span><?= icon('clock', 'ico') ?> <?= number_id($duration, 2) ?> jam kerja</span>
                    <span><?= icon('sun', 'ico') ?> <?= (int) $shift['break_minutes'] ?> mnt istirahat</span>
                    <span><?= icon('shield', 'ico') ?> Toleransi <?= (int) $shift['late_tolerance_minutes'] ?> mnt</span>
                </div>

                <div class="flex wrap" style="gap:5px;margin-top:9px">
                    <span class="tag-soft"><?= e(ScheduleModel::dayLabels($shift['work_days'])) ?></span>
                    <span class="tag-soft"><?= e($types[$shift['operational_type']] ?? $shift['operational_type']) ?></span>
                    <?php if ($overnight): ?><span class="tag-soft">Lintas hari</span><?php endif; ?>
                    <?php if (!empty($shift['unit_name'])): ?><span class="tag-soft"><?= e($shift['unit_name']) ?></span><?php endif; ?>
                </div>

                <div class="shift-card__people">
                    <?php if ($members): ?>
                        <div class="avatar-stack">
                            <?php foreach ($members as $member): ?>
                                <span class="avatar" style="background:<?= e(avatar_color($member['email'])) ?>" title="<?= e($member['full_name']) ?>"><?= e(initials($member['full_name'])) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <span class="small muted"><?= number_id($shift['employee_count']) ?> karyawan</span>
                    <?php else: ?>
                        <span class="small muted">Belum ada karyawan terjadwal</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="shift-card__foot">
                <a class="btn btn-sm btn-outline" href="<?= url('shifts?edit=' . $shift['id'] . '#shift-form') ?>"><?= icon('edit') ?> Ubah</a>
                <a class="btn btn-sm btn-ghost" href="<?= url('schedules?shift=' . $shift['id']) ?>"><?= icon('calendar-check') ?> Jadwalkan</a>
                <form class="inline-form" method="post" action="<?= url('shifts/' . $shift['id'] . '/delete') ?>" style="margin-left:auto"
                      data-confirm="Hapus shift <?= e($shift['name']) ?>?">
                    <?= csrf_field() ?><?= method_field('DELETE') ?>
                    <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)" title="Hapus"><?= icon('trash') ?></button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>

    <?php if (!$shifts): ?>
        <div class="empty-state" style="grid-column:1/-1">
            <div class="empty-icon"><?= icon('clock') ?></div>
            <h3>Belum ada shift</h3>
            <p>Tambahkan pola jam kerja pertama, lalu terapkan ke karyawan melalui halaman Penjadwalan.</p>
        </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('[data-open-form]').forEach(function (link) {
    link.addEventListener('click', function () {
        var panel = document.getElementById('shift-form');
        if (panel) { panel.open = true; }
    });
});
</script>
