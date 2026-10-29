<?php
/**
 * HR command centre.
 * @var array $user, $workforce, $attendance, $approvals, $payroll, $composition, $monitoring, $critical, $latePolicy
 */
$hour = (int) date('G');
$shiftLabel = $hour < 11 ? 'Pagi' : ($hour < 15 ? 'Siang' : ($hour < 19 ? 'Sore' : 'Malam'));
$totalApproval = (int) $approvals['total'];
?>
<div class="page-head" style="margin-bottom:14px">
    <div>
        <h1><?= e($greeting) ?>, <?= e($user['full_name']) ?></h1>
        <div class="sub">
            <span><?= tanggal_panjang(date('Y-m-d')) ?></span>
            <span class="dot" style="background:var(--line-strong)"></span>
            <span style="display:inline-flex;align-items:center;gap:5px"><?= icon('building', 'ico') ?>
                <?= e(implode(' · ', array_map(static fn ($u) => $u['city'] ?: $u['name'], $workforce['units']))) ?>
            </span>
            <span class="dot" style="background:var(--line-strong)"></span>
            <span class="muted">Sinkronisasi terakhir: <?= date('H:i') ?> WIB</span>
        </div>
    </div>
    <div class="page-head__actions">
        <span class="live-pill"><span class="dot"></span> Sistem Aktif</span>
    </div>
</div>

<!-- action bar -->
<div class="hero-card" style="padding:14px 18px;margin-bottom:16px">
    <div class="flex wrap" style="gap:8px">
        <a class="btn btn-outline" href="<?= url('reports/payroll') ?>"><?= icon('download') ?> Export Laporan</a>
        <a class="btn btn-soft" href="<?= url('payroll') ?>"><?= icon('play') ?> Proses Payroll Periode Ini</a>
    </div>
    <div class="hero-actions">
        <a class="btn btn-primary" href="<?= url('employees/create') ?>"><?= icon('plus') ?> Tambah Karyawan</a>
    </div>
</div>

<!-- KPI cards -->
<div class="kpi-grid">
    <div class="kpi">
        <div class="kpi__label">
            <span>Total Karyawan Aktif</span>
            <span class="kpi__icon navy"><?= icon('users') ?></span>
        </div>
        <div class="kpi__value"><?= number_id($workforce['total']) ?> <span class="unit">Jiwa</span></div>
        <div class="kpi__trend"><?= icon('trending') ?> +<?= (int) $workforce['new_this_month'] ?> penambahan bulan ini</div>
        <div class="kpi__foot">
            <span>Distribusi Entitas</span>
            <b><?= (int) $workforce['unit_count'] ?> Cabang Operasional</b>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi__label">
            <span>Tingkat Presensi Hari Ini</span>
            <span class="kpi__icon"><?= icon('user') ?></span>
        </div>
        <div class="kpi__value"><?= number_id($attendance['rate'], 1) ?><span class="unit">%</span>
            <span style="font-size:12px;font-weight:600;color:var(--teal-700);margin-left:4px">Kehadiran</span>
        </div>
        <div class="kpi__trend" style="color:var(--muted);font-weight:500">
            <?= number_id($attendance['on_time']) ?> Tepat Waktu · <?= number_id($attendance['late']) ?> Terlambat
        </div>
        <div class="kpi__foot">
            <span>Izin/Cuti: <b><?= (int) $attendance['counts']['LEAVE'] ?></b></span>
            <span>Alpha/Absen: <b><?= (int) $attendance['counts']['ABSENT'] ?></b></span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi__label">
            <span>Approval Tertunda</span>
            <span class="kpi__icon rose"><?= icon('bell') ?></span>
        </div>
        <div class="kpi__value" style="color:var(--rose-600)"><?= number_id($totalApproval) ?>
            <span class="unit" style="color:var(--muted)">Pengajuan</span></div>
        <div class="kpi__trend rose" style="font-weight:600">Perlu Ditinjau Segera</div>
        <div class="kpi__foot">
            <span><b><?= (int) $approvals['counts']['leave'] ?></b> Cuti</span>
            <span><b><?= (int) $approvals['counts']['overtime'] ?></b> Lembur</span>
            <span><b><?= (int) $approvals['counts']['shift'] ?></b> Tukar Shift</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi__label">
            <span><?= $payroll['has_payroll'] ? 'Payroll Terakhir' : 'Estimasi Payroll (' . e($payroll['period_label']) . ')' ?></span>
            <span class="kpi__icon indigo"><?= icon('wallet') ?></span>
        </div>
        <div class="kpi__value" style="font-size:22px">
            <?= rupiah($payroll['has_payroll'] ? $payroll['last']['total_net'] : $payroll['net']) ?>
        </div>
        <div style="margin-top:6px">
            <?php if ($payroll['has_payroll']): ?>
                <?= status_badge($payroll['last']['status']) ?>
                <span class="small muted" style="margin-left:6px"><?= e($payroll['last']['unit_name'] ?? 'Semua unit') ?></span>
            <?php else: ?>
                <span class="badge badge-slate">Draft Kalkulasi Otomatis</span>
            <?php endif; ?>
        </div>
        <div class="kpi__foot">
            <span>Cut-off: <b><?= tanggal($payroll['cutoff_date']) ?></b></span>
            <span>Disburse: <b><?= tanggal($payroll['pay_date']) ?></b></span>
        </div>
    </div>
</div>

<!-- main grid -->
<div style="display:grid;grid-template-columns:minmax(0,1fr) 366px;gap:16px;margin-top:16px" class="dash-grid">

    <div class="flex-col gap-16">
        <!-- live monitoring -->
        <section class="card">
            <div class="card__head">
                <div>
                    <h2>Live Monitoring Presensi &amp; Geofence GPS</h2>
                    <div class="sub">Pantauan verifikasi radius <?= (int) ($attendance['units'][0]['geofence_radius'] ?? 50) ?>m
                        kantor dan autentikasi biometrik wajah.</div>
                </div>
                <div class="card__head__actions">
                    <span class="small muted" style="font-weight:650">STATUS GEOFENCE:</span>
                    <span class="badge badge-teal">Terkunci Presisi</span>
                </div>
            </div>
            <div class="card__body" style="padding:14px 18px 16px">
                <div class="bu-monitor">
                    <?php foreach (array_slice($attendance['units'], 0, 3) as $unit): ?>
                        <div class="bu-monitor__item">
                            <div class="bu-name">
                                <?= icon('building') ?>
                                <?= e($unit['name']) ?>
                                <span class="bu-pct"><?= number_id($unit['rate'], 1) ?>%</span>
                            </div>
                            <div class="progress"><span style="width:<?= min(100, (float) $unit['rate']) ?>%"></span></div>
                            <div class="bu-stats">
                                <span>Hadir: <b><?= (int) $unit['present'] ?></b></span>
                                <span style="color:var(--rose-600)">Terlambat: <b><?= (int) $unit['late'] ?></b></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-12" style="display:flex;align-items:flex-start;gap:8px;font-size:11.5px;color:var(--muted);
                            background:#f8fafc;border:1px solid var(--line);border-radius:9px;padding:9px 11px">
                    <?= icon('clock', 'ico') ?>
                    <div>
                        <b style="color:var(--ink-2)">Log Terbaru (<?= date('H:i') ?> WIB):</b>
                        <?php if ($attendance['logs']): ?>
                            <?php $log = $attendance['logs'][0]; ?>
                            <?= e($log['full_name']) ?> (<?= e($log['department_name'] ?? '-') ?>)
                            check-in pukul <?= e(substr((string) $log['check_in_at'], 11, 5)) ?>
                            dengan jarak <?= (int) $log['check_in_distance'] ?>m dari perimeter HQ
                            (Selfie Verified).
                            <a href="<?= url('attendance/live') ?>" style="font-weight:600">Lihat Radar →</a>
                        <?php else: ?>
                            Belum ada presensi tercatat hari ini.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- salary composition -->
        <section class="card">
            <div class="card__head">
                <div>
                    <h2>Komposisi Beban Gaji &amp; Estimasi Potongan</h2>
                    <div class="sub">Kalkulasi real-time per jam kerja, denda keterlambatan, dan kontribusi BPJS.</div>
                </div>
                <div class="card__head__actions">
                    <span class="small muted">Siklus: 26 Sep – 25 Okt</span>
                </div>
            </div>
            <div class="card__body" style="padding:14px 18px 18px">
                <div class="comp-bar">
                    <?php foreach ($composition['segments'] as $segment): ?>
                        <?php if ($segment['pct'] > 0): ?>
                            <span style="width:<?= $segment['pct'] ?>%;background:<?= $segment['color'] ?>"
                                  title="<?= e($segment['label']) ?>: <?= rupiah($segment['value']) ?>"></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div class="comp-legend">
                    <?php foreach ($composition['segments'] as $segment): ?>
                        <div class="comp-legend__item">
                            <span class="swatch" style="background:<?= $segment['color'] ?>"></span>
                            <div>
                                <b><?= e($segment['label']) ?></b>
                                <?= $segment['value'] < 0 ? '-' : '' ?><?= rupiah(abs($segment['value'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- employee monitoring table -->
        <section class="card">
            <div class="card__head">
                <div>
                    <h2>Monitoring Status Tenaga Kerja</h2>
                    <div class="sub">Aktivitas terkini, status kontrak, dan kepatuhan shift hari ini.</div>
                </div>
                <div class="card__head__actions">
                    <a class="btn btn-outline btn-sm" href="<?= url('attendance/daily') ?>"><?= icon('filter') ?> Filter</a>
                    <a class="btn btn-outline btn-sm" href="<?= url('employees') ?>"><?= icon('list') ?> Kolom</a>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                    <tr>
                        <th>ID &amp; Nama Karyawan</th>
                        <th>Cabang / Divisi</th>
                        <th>Status Kontrak</th>
                        <th>Presensi (<?= tanggal(date('Y-m-d'), 'd M') ?>)</th>
                        <th style="width:44px"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($monitoring as $emp): ?>
                        <tr>
                            <td>
                                <div class="name-cell">
                                    <span class="avatar sm" style="background:<?= e(avatar_color($emp['email'])) ?>">
                                        <?= e(initials($emp['full_name'])) ?>
                                    </span>
                                    <div>
                                        <b><?= e($emp['full_name']) ?></b>
                                        <span class="mono"><?= e($emp['employee_code']) ?> · <?= e($emp['position_name'] ?? '-') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:600;color:var(--ink)"><?= e($emp['unit_name'] ?? '-') ?></div>
                                <div class="small muted"><?= e($emp['department_name'] ?? '-') ?></div>
                            </td>
                            <td>
                                <?php
                                $statusLabel = $emp['employment_status'];
                                $tone = str_starts_with($statusLabel, 'PKWT') ? 'badge-slate' : 'badge-teal';
                                ?>
                                <span class="badge <?= $tone ?>"><?= e($statusLabel) ?></span>
                                <?php if ($emp['contract_end']): ?>
                                    <div class="small muted">s.d. <?= tanggal($emp['contract_end']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $attStatus = $emp['attendance_status'];
                                if ($emp['leave_type_name']) {
                                    echo '<span class="badge badge-indigo">' . e($emp['leave_type_name']) . '</span>';
                                } elseif ($attStatus === 'LATE') {
                                    echo '<span class="badge badge-rose">' . e(substr((string) $emp['check_in_at'], 11, 5))
                                        . ' (+' . (int) $emp['late_minutes'] . 'm Denda)</span>';
                                } elseif ($attStatus === 'PRESENT') {
                                    echo '<span class="badge badge-teal">' . e(substr((string) $emp['check_in_at'], 11, 5))
                                        . ' (On Time)</span>';
                                } elseif ($attStatus === 'ABSENT') {
                                    echo '<span class="badge badge-rose">Tidak Hadir</span>';
                                } else {
                                    echo '<span class="badge badge-slate">Belum Presensi</span>';
                                }
                                if ($emp['operational_type'] === 'WFH') {
                                    echo ' <span class="badge badge-indigo">WFH</span>';
                                }
                                ?>
                                <?php if ($emp['check_out_at']): ?>
                                    <div class="small muted">Out <?= e(substr((string) $emp['check_out_at'], 11, 5)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" class="icon-btn" data-dropdown aria-label="Aksi">⋮</button>
                                    <div class="dropdown__menu">
                                        <a href="<?= url('employees/' . $emp['id']) ?>"><?= icon('eye') ?> Lihat Profil</a>
                                        <a href="<?= url('employees/' . $emp['id'] . '/edit') ?>"><?= icon('edit') ?> Ubah Data</a>
                                        <a href="<?= url('schedules?employee=' . $emp['id']) ?>"><?= icon('calendar') ?> Jadwal Kerja</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card__foot flex-between">
                <span class="small muted">Menampilkan <?= count($monitoring) ?> dari <?= number_id($workforce['total']) ?> data kepegawaian aktif</span>
                <a href="<?= url('employees') ?>" class="small" style="font-weight:650;display:inline-flex;align-items:center;gap:4px">
                    Buka Direktori Karyawan Lengkap <?= icon('arrow-right', 'ico') ?>
                </a>
            </div>
        </section>
    </div>

    <!-- right column -->
    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head" style="padding:14px 16px">
                <h2 style="display:flex;align-items:center;gap:8px">
                    Antrean Approval
                    <span class="nav-badge" style="background:var(--rose-50);color:var(--rose-600);border-color:#fbd0d9;font-size:10px">
                        <?= (int) $totalApproval ?>
                    </span>
                </h2>
                <a href="<?= url('overtime') ?>" class="small" style="font-weight:600">Semua</a>
            </div>
            <div class="card__body" style="padding:12px 14px 14px">
                <?php if (!$approvals['overtime'] && !$approvals['leave'] && !$approvals['shift']): ?>
                    <div class="empty-state" style="padding:22px 10px">
                        <div class="empty-icon"><?= icon('check') ?></div>
                        <h3>Tidak ada antrean</h3>
                        <p>Semua pengajuan telah diproses.</p>
                    </div>
                <?php endif; ?>

                <?php foreach (array_slice($approvals['overtime'], 0, 1) as $ot): ?>
                    <div class="approval-card">
                        <div class="approval-card__head">
                            <span class="approval-card__type"><span class="dot" style="background:var(--navy-700)"></span> Lembur Operasional</span>
                            <span class="small muted"><?= waktu_lalu($ot['created_at']) ?></span>
                        </div>
                        <div class="flex" style="gap:9px;align-items:flex-start">
                            <span class="avatar sm" style="background:<?= e(avatar_color($ot['employee_code'])) ?>"><?= e(initials($ot['full_name'])) ?></span>
                            <div style="min-width:0">
                                <b style="font-size:12.3px"><?= e($ot['full_name']) ?> (<?= e($ot['department_name'] ?? '-') ?>)</b>
                                <div class="approval-card__meta">
                                    <?= e(substr((string) $ot['planned_start'], 0, 5)) ?>–<?= e(substr((string) $ot['planned_end'], 0, 5)) ?> ·
                                    <?= e(str_limit($ot['reason'], 46)) ?><br>
                                    <span class="small">Atasan: <?= e($ot['manager_name'] ?? 'HR Langsung') ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="approval-card__actions">
                            <form method="post" action="<?= url('overtime/' . $ot['id'] . '/decide') ?>" class="inline-form" style="flex:1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="decision" value="APPROVED">
                                <input type="hidden" name="stage" value="HR">
                                <button class="btn btn-primary btn-sm btn-block" type="submit"><?= icon('check') ?> Setujui</button>
                            </form>
                            <form method="post" action="<?= url('overtime/' . $ot['id'] . '/decide') ?>" class="inline-form" style="flex:1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="decision" value="REJECTED">
                                <input type="hidden" name="stage" value="HR">
                                <button class="btn btn-outline btn-sm btn-block" type="submit">Tolak</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php foreach (array_slice($approvals['leave'], 0, 2) as $lr): ?>
                    <div class="approval-card">
                        <div class="approval-card__head">
                            <span class="approval-card__type"><span class="dot" style="background:var(--amber-500)"></span> <?= e($lr['leave_type_name']) ?></span>
                            <span class="small muted"><?= waktu_lalu($lr['created_at']) ?></span>
                        </div>
                        <div class="flex" style="gap:9px;align-items:flex-start">
                            <span class="avatar sm" style="background:<?= e(avatar_color($lr['employee_code'])) ?>"><?= e(initials($lr['full_name'])) ?></span>
                            <div style="min-width:0">
                                <b style="font-size:12.3px"><?= e($lr['full_name']) ?> (<?= e($lr['department_name'] ?? '-') ?>)</b>
                                <div class="approval-card__meta">
                                    Durasi: <?= number_id($lr['days'], 0) ?> Hari Kerja
                                    (<?= tanggal($lr['start_date'], 'j M') ?>–<?= tanggal($lr['end_date'], 'j M') ?>)
                                    <?= $lr['is_paid'] ? ' · <span class="badge badge-teal" style="height:16px">Dibayar</span>' : ' · <span class="badge badge-rose" style="height:16px">Tanpa Upah</span>' ?>
                                </div>
                            </div>
                        </div>
                        <div class="approval-card__actions">
                            <form method="post" action="<?= url('leave/' . $lr['id'] . '/decide') ?>" class="inline-form" style="flex:1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="decision" value="APPROVED">
                                <input type="hidden" name="stage" value="HR">
                                <button class="btn btn-primary btn-sm btn-block" type="submit"><?= icon('check') ?> Setujui</button>
                            </form>
                            <form method="post" action="<?= url('leave/' . $lr['id'] . '/decide') ?>" class="inline-form" style="flex:1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="decision" value="REJECTED">
                                <input type="hidden" name="stage" value="HR">
                                <button class="btn btn-outline btn-sm btn-block" type="submit">Tolak</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php foreach (array_slice($approvals['shift'], 0, 1) as $sr): ?>
                    <div class="approval-card">
                        <div class="approval-card__head">
                            <span class="approval-card__type"><span class="dot" style="background:var(--indigo-500)"></span> Tukar Shift</span>
                            <span class="small muted"><?= waktu_lalu($sr['created_at']) ?></span>
                        </div>
                        <div class="flex" style="gap:9px;align-items:flex-start">
                            <span class="avatar sm" style="background:<?= e(avatar_color($sr['employee_code'])) ?>"><?= e(initials($sr['full_name'])) ?></span>
                            <div style="min-width:0">
                                <b style="font-size:12.3px"><?= e($sr['full_name']) ?> (<?= e($sr['department_name'] ?? '-') ?>)</b>
                                <div class="approval-card__meta">
                                    <?= e($sr['current_shift'] ?? 'Belum ada') ?> → <?= e($sr['requested_shift'] ?? '-') ?><br>
                                    <span class="small"><?= tanggal($sr['start_date']) ?> s.d. <?= tanggal($sr['end_date']) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="approval-card__actions">
                            <form method="post" action="<?= url('schedules/requests/' . $sr['id'] . '/decide') ?>" class="inline-form" style="flex:1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="decision" value="APPROVED">
                                <button class="btn btn-primary btn-sm btn-block" type="submit"><?= icon('check') ?> Setujui</button>
                            </form>
                            <form method="post" action="<?= url('schedules/requests/' . $sr['id'] . '/decide') ?>" class="inline-form" style="flex:1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="decision" value="REJECTED">
                                <button class="btn btn-outline btn-sm btn-block" type="submit">Tolak</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head" style="padding:14px 16px">
                <h2 style="display:flex;align-items:center;gap:7px"><?= icon('shield', 'ico') ?> Kebijakan Denda Presensi</h2>
            </div>
            <div class="card__body" style="padding:12px 16px 16px">
                <div class="alert alert-info" style="margin-bottom:10px;padding:9px 11px;font-size:11.5px">
                    <?= icon('info') ?>
                    <div>
                        <b>Toleransi Waktu: <?= (int) $latePolicy['tolerance'] ?> Menit</b><br>
                        Keterlambatan 16–30 menit dikenakan penyesuaian tunjangan kehadiran 50%.
                        Di atas 30 menit dikenakan formula pemotongan prorata jam kerja harian otomatis.
                    </div>
                </div>
                <div class="flex-between" style="font-size:11px;color:var(--muted);border-top:1px dashed var(--line);padding-top:9px">
                    <span>SOP SK.DIR-04/HR/2024</span>
                    <a href="<?= url('attendance/settings') ?>" style="font-weight:650">Pelajari Rumus</a>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__head" style="padding:14px 16px">
                <h2 style="display:flex;align-items:center;gap:7px"><?= icon('calendar', 'ico') ?> Kalender Kritis HR</h2>
                <span class="small muted"><?= e(bulan_id((int) date('n'))) ?> <?= date('Y') ?></span>
            </div>
            <div class="card__body" style="padding:8px 16px 14px">
                <?php foreach ($critical as $item): ?>
                    <div class="cal-item">
                        <div class="cal-date <?= e($item['tone']) ?>">
                            <b><?= e(date('d', strtotime($item['date']))) ?></b>
                            <span><?= e(date('M', strtotime($item['date']))) ?></span>
                        </div>
                        <div class="cal-body">
                            <b><?= e($item['title']) ?></b>
                            <span><?= e($item['sub']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="card__foot" style="padding:10px 16px">
                <a href="<?= url('employees?contract=expiring') ?>" class="small" style="font-weight:650;display:inline-flex;align-items:center;gap:5px">
                    <?= icon('calendar-check', 'ico') ?> Buka Agenda Korporat Lengkap
                </a>
            </div>
        </section>
    </div>
</div>

<style>
@media (max-width: 1180px) {
  .dash-grid { grid-template-columns: 1fr !important; }
}
</style>
