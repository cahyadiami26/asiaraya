<?php
/**
 * Employee self-service dashboard.
 * @var array $user, $attendance, $requests, $payroll, $schedule
 */
$today = $attendance['today'];
$sched = $attendance['schedule'];
$summary = $attendance['summary'];
$lastItem = $payroll['last_item'];
$checkInTime = $today['check_in_at'] ?? null;
$checkOutTime = $today['check_out_at'] ?? null;
$operational = $sched['operational_type'] ?? 'NORMAL';
?>
<div class="page-head" style="margin-bottom:14px">
    <div>
        <h1><?= e($greeting) ?>, <?= e($user['nickname'] ?: $user['full_name']) ?></h1>
        <div class="sub">
            <span><?= tanggal_panjang(date('Y-m-d')) ?></span>
            <span class="dot" style="background:var(--line-strong)"></span>
            <span><?= e($user['position_name'] ?? '-') ?> · <?= e($user['department_name'] ?? '-') ?></span>
        </div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-primary" href="<?= url('me/attendance') ?>"><?= icon('camera') ?> Presensi Sekarang</a>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi">
        <div class="kpi__label"><span>Status Hari Ini</span><span class="kpi__icon"><?= icon('clock') ?></span></div>
        <div class="kpi__value" style="font-size:20px">
            <?php if ($checkInTime): ?>
                <?= e(substr((string) $checkInTime, 11, 5)) ?>
                <span class="unit">– <?= $checkOutTime ? e(substr((string) $checkOutTime, 11, 5)) : 'belum out' ?></span>
            <?php else: ?>
                Belum Presensi
            <?php endif; ?>
        </div>
        <div class="kpi__trend" style="color:var(--muted);font-weight:500">
            <?= e($sched['shift_name'] ?? 'Belum ada jadwal') ?>
            <?= $sched ? '(' . e(substr((string) $sched['check_in_time'], 0, 5)) . '–' . e(substr((string) $sched['check_out_time'], 0, 5)) . ')' : '' ?>
        </div>
        <div class="kpi__foot">
            <span>Tipe: <b><?= e($operational) ?></b></span>
            <span><?= $today ? status_badge($today['status']) : '<span class="badge badge-slate">-</span>' ?></span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi__label"><span>Kehadiran Bulan Ini</span><span class="kpi__icon indigo"><?= icon('calendar-check') ?></span></div>
        <div class="kpi__value"><?= (int) $summary['present'] + (int) $summary['late'] ?> <span class="unit">hari</span></div>
        <div class="kpi__trend" style="color:var(--muted);font-weight:500">
            <?= (int) $summary['late'] ?> terlambat · <?= (int) $summary['absent'] ?> alpha
        </div>
        <div class="kpi__foot">
            <span>Cuti: <b><?= (int) $summary['leave'] ?> hari</b></span>
            <span>Menit telat: <b><?= (int) $summary['late_minutes'] ?></b></span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi__label"><span>Saldo Cuti Tahunan</span><span class="kpi__icon amber"><?= icon('file') ?></span></div>
        <?php
        $annual = null;
        foreach ($requests['balances'] as $b) {
            if (strtoupper($b['leave_type_name']) === 'CUTI TAHUNAN' || $b['leave_type_name'] === 'Cuti Tahunan') { $annual = $b; break; }
        }
        $remaining = $annual ? (float) $annual['quota'] + (float) $annual['carry'] - (float) $annual['used'] : 0;
        ?>
        <div class="kpi__value"><?= number_id($remaining, 0) ?> <span class="unit">hari</span></div>
        <div class="kpi__trend" style="color:var(--muted);font-weight:500">
            Kuota <?= $annual ? number_id($annual['quota'], 0) : 0 ?> · Terpakai <?= $annual ? number_id($annual['used'], 0) : 0 ?>
        </div>
        <div class="kpi__foot">
            <span>Periode <b><?= date('Y') ?></b></span>
            <a href="<?= url('me/leave') ?>" class="small" style="font-weight:650">Ajukan →</a>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi__label"><span>Gaji Bersih Terakhir</span><span class="kpi__icon navy"><?= icon('wallet') ?></span></div>
        <div class="kpi__value" style="font-size:21px">
            <?= $lastItem ? rupiah($lastItem['net_salary']) : rupiah($user['basic_salary']) ?>
        </div>
        <div style="margin-top:6px">
            <?php if ($lastItem): ?>
                <?= status_badge($lastItem['period_status']) ?>
                <span class="small muted" style="margin-left:5px"><?= e(bulan_id((int) $lastItem['period_month'])) ?> <?= (int) $lastItem['period_year'] ?></span>
            <?php else: ?>
                <span class="badge badge-slate">Payroll belum terbit</span>
            <?php endif; ?>
        </div>
        <div class="kpi__foot">
            <span>Gaji pokok: <b><?= rupiah($user['basic_salary']) ?></b></span>
            <a href="<?= url('me/payslip') ?>" class="small" style="font-weight:650">Slip →</a>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:minmax(0,1.35fr) minmax(0,1fr);gap:16px;margin-top:16px" class="dash-grid">
    <section class="card">
        <div class="card__head">
            <div>
                <h2>Riwayat Presensi <?= e(bulan_id((int) date('n'))) ?></h2>
                <div class="sub">Rekap kehadiran, jam masuk-keluar, dan status verifikasi geofence.</div>
            </div>
            <a href="<?= url('me/attendance') ?>" class="small" style="font-weight:650">Lihat semua</a>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Tanggal</th><th>Shift</th><th>Masuk</th><th>Keluar</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($attendance['month'], 0, 8) as $row): ?>
                    <tr>
                        <td><b><?= tanggal($row['work_date']) ?></b><div class="small muted"><?= e(hari($row['work_date'])) ?></div></td>
                        <td class="small"><?= e($row['shift_name'] ?? '-') ?></td>
                        <td class="mono"><?= $row['check_in_at'] ? e(substr((string) $row['check_in_at'], 11, 5)) : '—' ?></td>
                        <td class="mono"><?= $row['check_out_at'] ? e(substr((string) $row['check_out_at'], 11, 5)) : '—' ?></td>
                        <td>
                            <?= status_badge($row['status']) ?>
                            <?php if ((int) $row['late_minutes'] > 0): ?>
                                <span class="small" style="color:var(--rose-600);display:block">+<?= (int) $row['late_minutes'] ?> menit</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$attendance['month']): ?>
                    <tr><td colspan="5"><div class="table-empty"><?= icon('calendar') ?>Belum ada presensi bulan ini.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head" style="padding:14px 16px">
                <h2>Jadwal Kerja Terdekat</h2>
                <a href="<?= url('me/schedule') ?>" class="small" style="font-weight:650">Ubah</a>
            </div>
            <div class="card__body" style="padding:10px 16px 14px">
                <?php foreach ($schedule as $i => $sc): ?>
                    <div class="cal-item">
                        <div class="cal-date" style="background:<?= e($sc['color']) ?>15;border-color:<?= e($sc['color']) ?>33">
                            <b style="color:<?= e($sc['color']) ?>"><?= e(date('d', strtotime($sc['start_date']))) ?></b>
                            <span style="color:<?= e($sc['color']) ?>"><?= e(date('M', strtotime($sc['start_date']))) ?></span>
                        </div>
                        <div class="cal-body">
                            <b><?= e($sc['shift_name']) ?></b>
                            <span><?= e(substr((string) $sc['check_in_time'], 0, 5)) ?>–<?= e(substr((string) $sc['check_out_time'], 0, 5)) ?>
                                · <?= e($sc['operational_type']) ?> · <?= tanggal($sc['start_date']) ?>–<?= tanggal($sc['end_date']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$schedule): ?>
                    <div class="empty-state" style="padding:20px"><p>Belum ada jadwal aktif.</p></div>
                <?php endif; ?>
            </div>
            <div class="card__foot" style="padding:10px 16px">
                <a href="<?= url('me/schedule') ?>" class="btn btn-soft btn-sm btn-block"><?= icon('refresh') ?> Ajukan Perubahan Jadwal</a>
            </div>
        </section>

        <section class="card">
            <div class="card__head" style="padding:14px 16px">
                <h2>Status Pengajuan</h2>
            </div>
            <div class="card__body" style="padding:6px 16px 14px">
                <div class="list-rows">
                    <?php foreach (array_slice($requests['overtime'], 0, 2) as $ot): ?>
                        <div class="list-row" style="padding:9px 0">
                            <span class="badge badge-slate" style="height:auto;padding:4px 7px"><?= icon('clock', 'ico') ?></span>
                            <div class="list-row__body">
                                <b>Lembur <?= tanggal($ot['work_date'], 'j M') ?></b>
                                <span><?= e(substr((string) $ot['planned_start'], 0, 5)) ?>–<?= e(substr((string) $ot['planned_end'], 0, 5)) ?> · <?= e(str_limit($ot['reason'], 34)) ?></span>
                            </div>
                            <?= status_badge($ot['status']) ?>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach (array_slice($requests['leave'], 0, 2) as $lr): ?>
                        <div class="list-row" style="padding:9px 0">
                            <span class="badge badge-slate" style="height:auto;padding:4px 7px"><?= icon('file', 'ico') ?></span>
                            <div class="list-row__body">
                                <b><?= e($lr['leave_type_name']) ?></b>
                                <span><?= tanggal($lr['start_date']) ?>–<?= tanggal($lr['end_date']) ?> (<?= number_id($lr['days'], 0) ?> hari)</span>
                            </div>
                            <?= status_badge($lr['status']) ?>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach (array_slice($requests['shift'], 0, 1) as $sr): ?>
                        <div class="list-row" style="padding:9px 0">
                            <span class="badge badge-slate" style="height:auto;padding:4px 7px"><?= icon('calendar', 'ico') ?></span>
                            <div class="list-row__body">
                                <b>Tukar Shift</b>
                                <span><?= e($sr['current_shift'] ?? '-') ?> → <?= e($sr['requested_shift'] ?? '-') ?></span>
                            </div>
                            <?= status_badge($sr['status']) ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$requests['overtime'] && !$requests['leave'] && !$requests['shift']): ?>
                        <div class="empty-state" style="padding:18px 6px">
                            <div class="empty-icon"><?= icon('file') ?></div>
                            <p>Belum ada pengajuan.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</div>

<style>@media (max-width:1180px){.dash-grid{grid-template-columns:1fr !important}}</style>
