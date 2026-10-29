<?php
/**
 * Jadwal saya (self service).
 * @var array $days, $shifts, $requests, $employee
 * @var array|null $todayShift
 * @var bool $hasOpen
 * @var string $from, $to
 * @var array $old
 */
use App\Models\ScheduleModel;

$dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
$workDays = 0;
foreach ($days as $day) {
    if ($day['is_work']) {
        $workDays++;
    }
}
$today = date('Y-m-d');
?>
<div class="page-head">
    <div>
        <h1>Jadwal Saya</h1>
        <div class="sub">Pola shift, hari kerja, dan pengajuan perubahan jadwal Anda.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('schedules/requests') ?>"><?= icon('refresh') ?> Status Pengajuan</a>
        <a class="btn btn-primary" href="#request-form" data-open-form><?= icon('send') ?> Ajukan Perubahan</a>
    </div>
</div>

<div class="clock-card" style="margin-bottom:16px">
    <div>
        <div class="clock-date"><?= tanggal_panjang($today) ?></div>
        <?php if ($todayShift): ?>
            <div class="clock-time" style="font-size:26px;letter-spacing:-.02em"><?= e(substr($todayShift['check_in_time'], 0, 5)) ?> – <?= e(substr($todayShift['check_out_time'], 0, 5)) ?></div>
            <div class="small muted" style="margin-top:4px">
                <?= e($todayShift['shift_name']) ?> · <?= e(ScheduleModel::dayLabels($todayShift['work_days'])) ?>
                <?php if (!in_array((int) date('N'), ScheduleModel::workDayList($todayShift['work_days']), true)): ?>
                    · <span class="badge badge-slate">Hari libur shift</span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="clock-time" style="font-size:22px">Tidak ada jadwal hari ini</div>
            <div class="small muted" style="margin-top:4px">Hubungi HR jika Anda seharusnya bertugas.</div>
        <?php endif; ?>
    </div>
    <div class="text-right">
        <div class="small muted">Hari kerja dalam rentang</div>
        <div style="font-size:22px;font-weight:750;color:var(--navy-900)"><?= $workDays ?></div>
        <div class="small muted">dari <?= count($days) ?> hari</div>
    </div>
</div>

<details class="form-panel" id="request-form" <?= ($old || $hasOpen === false && !empty($_GET['ajukan'])) ? 'open' : '' ?>>
    <summary>
        <span class="form-panel__title"><?= icon('send', 'ico') ?> Ajukan Perubahan Jadwal</span>
        <span class="muted small"><?= $hasOpen ? 'Ada pengajuan berjalan' : 'Atasan → HR' ?></span>
    </summary>
    <form method="post" action="<?= url('me/schedule/request') ?>" class="form-panel__body">
        <?= csrf_field() ?>
        <?php if ($hasOpen): ?>
            <div class="alert alert-info" style="margin-bottom:14px">
                <?= icon('info') ?>
                <div>Anda masih memiliki pengajuan yang sedang diproses. Pengajuan baru dapat dibuat setelah pengajuan sebelumnya diputus.</div>
            </div>
        <?php endif; ?>
        <div class="form-grid three">
            <div class="field">
                <label>Shift Pengganti <span class="req">*</span></label>
                <select class="select" name="requested_shift_id" required <?= $hasOpen ? 'disabled' : '' ?>>
                    <option value="">Pilih shift…</option>
                    <?php foreach ($shifts as $shift): ?>
                        <option value="<?= (int) $shift['id'] ?>" <?= (int) ($old['requested_shift_id'] ?? 0) === (int) $shift['id'] ? 'selected' : '' ?>>
                            <?= e($shift['name']) ?> · <?= e(substr($shift['check_in_time'], 0, 5)) ?>–<?= e(substr($shift['check_out_time'], 0, 5)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Tanggal Mulai <span class="req">*</span></label>
                <input class="input" type="date" name="start_date" min="<?= e($today) ?>" value="<?= e($old['start_date'] ?? $today) ?>" required <?= $hasOpen ? 'disabled' : '' ?>>
            </div>
            <div class="field">
                <label>Tanggal Akhir <span class="req">*</span></label>
                <input class="input" type="date" name="end_date" min="<?= e($today) ?>" value="<?= e($old['end_date'] ?? $today) ?>" required <?= $hasOpen ? 'disabled' : '' ?>>
            </div>
            <div class="field full">
                <label>Alasan</label>
                <input class="input" name="reason" maxlength="255" value="<?= e($old['reason'] ?? '') ?>"
                       placeholder="Contoh: tukar shift dengan rekan tim, keperluan keluarga" <?= $hasOpen ? 'disabled' : '' ?>>
                <span class="hint">Jelaskan singkat alasan agar atasan dan HR dapat memutuskan lebih cepat.</span>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" <?= $hasOpen ? 'disabled' : '' ?>><?= icon('send') ?> Kirim Pengajuan</button>
        </div>
    </form>
</details>

<div class="split-grid" style="grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:16px">
    <section class="card">
        <div class="card__head">
            <div><h2>Kalender Jadwal</h2>
                <div class="sub"><?= tanggal($from) ?> – <?= tanggal($to) ?></div></div>
            <form class="card__head__actions flex" method="get" action="<?= url('me/schedule') ?>" style="gap:6px">
                <input class="input" type="date" name="start" value="<?= e($from) ?>" style="width:145px">
                <input class="input" type="date" name="end" value="<?= e($to) ?>" style="width:145px">
                <button class="btn btn-sm btn-outline" type="submit"><?= icon('refresh') ?></button>
            </form>
        </div>
        <div class="day-list">
            <?php foreach ($days as $day): ?>
                <div class="day-row <?= $day['today'] ? 'is-today' : '' ?> <?= $day['weekend'] ? 'is-weekend' : '' ?>">
                    <div class="day-row__date">
                        <span class="dow"><?= e($dayNames[$day['dow']]) ?></span>
                        <span class="num"><?= date('d', strtotime($day['date'])) ?></span>
                        <span class="mono small muted"><?= date('M', strtotime($day['date'])) ?></span>
                    </div>
                    <div class="day-row__body">
                        <?php if ($day['shift']): ?>
                            <div class="flex wrap" style="gap:8px;align-items:center">
                                <span class="dot" style="background:<?= e($day['shift']['shift_color']) ?>"></span>
                                <b><?= e($day['shift']['shift_name']) ?></b>
                                <span class="small muted mono"><?= e(substr($day['shift']['check_in_time'], 0, 5)) ?>–<?= e(substr($day['shift']['check_out_time'], 0, 5)) ?></span>
                                <?php if ($day['is_work']): ?>
                                    <span class="badge badge-teal">Hari kerja</span>
                                <?php else: ?>
                                    <span class="badge badge-slate">Libur</span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span class="small muted">Tidak ada jadwal</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($day['today']): ?><span class="tag-soft">Hari ini</span><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <div class="card__head"><div><h2>Riwayat Pengajuan</h2>
            <div class="sub">12 pengajuan terakhir.</div></div></div>
        <div class="list-rows">
            <?php foreach ($requests as $request): ?>
                <div class="list-row" style="align-items:flex-start">
                    <span class="dot" style="background:<?= e($request['requested_color'] ?? '#0d9488') ?>;margin-top:6px"></span>
                    <div class="list-row__body">
                        <b><?= e($request['current_shift_name'] ?: 'Belum dijadwalkan') ?> → <?= e($request['requested_shift_name']) ?></b>
                        <span><?= tanggal($request['start_date']) ?> – <?= tanggal($request['end_date']) ?></span>
                        <?php if ($request['manager_note'] || $request['hr_note']): ?>
                            <span style="font-style:italic">“<?= e($request['hr_note'] ?: $request['manager_note']) ?>”</span>
                        <?php endif; ?>
                    </div>
                    <?= status_badge($request['status']) ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$requests): ?>
                <div class="table-empty">Belum ada pengajuan perubahan jadwal.</div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
document.querySelectorAll('[data-open-form]').forEach(function (link) {
    link.addEventListener('click', function () {
        var panel = document.getElementById('request-form');
        if (panel) { panel.open = true; }
    });
});
</script>
