<?php
/**
 * Live monitoring presensi (HR).
 * @var array $logs, $summary, $missing, $filters, $units, $geofences
 * @var string $date
 */
$counts = $summary['counts'];
$rate = $summary['rate'];
?>
<div class="page-head">
    <div>
        <h1>Live Monitoring Presensi</h1>
        <div class="sub">
            <span class="live-pill"><span class="dot"></span> Pembaruan otomatis 30 detik</span>
            <span class="muted">· Sinkronisasi terakhir <?= e($summary['last_sync']) ?> WIB</span>
        </div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('attendance/daily?date=' . $date) ?>"><?= icon('list') ?> Presensi Harian</a>
        <a class="btn btn-primary" href="<?= url('attendance/rekap') ?>"><?= icon('receipt') ?> Rekap Bulanan</a>
    </div>
</div>

<div class="kpi-grid" style="margin-bottom:16px">
    <div class="kpi">
        <div class="kpi__label">
            <span>Kehadiran Hari Ini</span>
            <span class="kpi__icon"><?= icon('users') ?></span>
        </div>
        <div class="kpi__value"><?= number_id($rate, 1) ?><span class="unit">%</span></div>
        <div class="kpi__trend"><?= number_id($summary['present']) ?> dari <?= number_id($summary['active']) ?> karyawan aktif</div>
        <div class="kpi__foot">
            <span>Tepat waktu: <b><?= number_id($counts['PRESENT']) ?></b></span>
            <span>Terlambat: <b><?= number_id($counts['LATE']) ?></b></span>
        </div>
    </div>
    <div class="kpi">
        <div class="kpi__label">
            <span>Masih di Kantor</span>
            <span class="kpi__icon teal"><?= icon('map-pin') ?></span>
        </div>
        <div class="kpi__value" style="color:var(--teal-700)"><?= number_id($counts['on_site']) ?>
            <span class="unit">orang</span></div>
        <div class="kpi__trend" style="color:var(--muted);font-weight:500">Sudah check-in, belum check-out</div>
        <div class="kpi__foot"><span>Live geofence aktif</span></div>
    </div>
    <div class="kpi">
        <div class="kpi__label">
            <span>Belum Presensi</span>
            <span class="kpi__icon amber"><?= icon('clock') ?></span>
        </div>
        <div class="kpi__value" style="color:var(--amber-600)"><?= number_id($summary['no_record']) ?></div>
        <div class="kpi__trend" style="font-weight:500;color:var(--muted)">Belum ada catatan masuk</div>
        <div class="kpi__foot">
            <span>Cuti/Izin: <b><?= number_id($counts['LEAVE']) ?></b></span>
            <span>Alpha: <b><?= number_id($counts['ABSENT']) ?></b></span>
        </div>
    </div>
    <div class="kpi">
        <div class="kpi__label">
            <span>Penerapan Geofence</span>
            <span class="kpi__icon indigo"><?= icon('shield') ?></span>
        </div>
        <div class="kpi__value" style="font-size:20px"><?= count($geofences) ?> <span class="unit">unit</span></div>
        <div class="kpi__trend" style="font-weight:500;color:var(--muted)">Radius dipantau per unit</div>
        <div class="kpi__foot">
            <?php $ready = count(array_filter($geofences, static fn ($u) => $u['latitude'] !== null)); ?>
            <span><b><?= $ready ?></b> unit punya koordinat</span>
        </div>
    </div>
</div>

<section class="card" style="margin-bottom:16px">
    <div class="card__head">
        <div><h2>Kehadiran per Unit</h2>
            <div class="sub">Persentase kehadiran berdasarkan jadwal aktif hari ini.</div></div>
    </div>
    <div class="card__body">
        <div class="bu-monitor">
            <?php foreach ($summary['units'] as $unit): ?>
                <div class="bu-monitor__item">
                    <div class="bu-name">
                        <?= icon('building') ?>
                        <?= e($unit['name']) ?>
                        <span class="bu-pct"><?= number_id($unit['rate'], 1) ?>%</span>
                    </div>
                    <div class="progress"><span style="width:<?= min(100, (float) $unit['rate']) ?>%;background:<?= (float) $unit['rate'] >= 90 ? 'var(--teal-500)' : ((float) $unit['rate'] >= 70 ? 'var(--amber-500)' : 'var(--rose-500)') ?>"></span></div>
                    <div class="bu-stats">
                        <span><b><?= number_id($unit['present']) ?></b> hadir</span>
                        <span><b><?= number_id($unit['late']) ?></b> terlambat</span>
                        <span><b><?= number_id($unit['on_site']) ?></b> di kantor</span>
                        <span><b><?= number_id($unit['headcount']) ?></b> total</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($missing): ?>
<section class="card" style="margin-bottom:16px">
    <div class="card__head">
        <div><h2>Belum Check-in</h2>
            <div class="sub">Karyawan aktif tanpa catatan presensi pada tanggal terpilih.</div></div>
        <span class="badge badge-amber"><?= count($missing) ?> ditampilkan</span>
    </div>
    <div class="card__body" style="padding-top:10px">
        <div class="chip-row">
            <?php foreach ($missing as $person): ?>
                <a class="person-chip" href="<?= url('employees/' . $person['id']) ?>">
                    <span class="avatar sm" style="background:<?= e(avatar_color($person['email'])) ?>"><?= e(initials($person['full_name'])) ?></span>
                    <span>
                        <b><?= e($person['full_name']) ?></b>
                        <i><?= e($person['unit_name'] ?? '-') ?></i>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="card">
    <form class="filter-bar" method="get" action="<?= url('attendance/live') ?>">
        <input class="input" type="date" name="date" value="<?= e($date) ?>" style="width:158px" data-auto-submit>
        <select class="select" name="unit" data-auto-submit style="min-width:170px">
            <option value="">Semua unit</option>
            <?php foreach ($units as $unit): ?>
                <option value="<?= (int) $unit['id'] ?>" <?= (int) $filters['unit'] === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="select" name="type" data-auto-submit style="min-width:150px">
            <option value="">Semua status</option>
            <?php foreach (['PRESENT' => 'Tepat waktu', 'LATE' => 'Terlambat', 'ABSENT' => 'Alpha', 'LEAVE' => 'Cuti/Izin'] as $key => $label): ?>
                <option value="<?= $key ?>" <?= $filters['type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="input-group" style="max-width:240px">
            <?= icon('search') ?>
            <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Cari nama / ID…">
        </div>
        <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
        <span class="spacer"></span>
        <span class="small muted"><?= count($logs) ?> catatan</span>
    </form>

    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th>Karyawan</th><th>Unit / Departemen</th><th>Masuk</th><th>Keluar</th>
                <th>Geofence</th><th class="num">Terlambat</th><th>Selfie</th><th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <?php
                $distance = $log['check_in_distance'];
                $radius = (int) ($log['geofence_radius'] ?? 0);
                $inRadius = $distance === null ? null : ($radius > 0 ? $distance <= $radius : true);
                ?>
                <tr>
                    <td>
                        <div class="name-cell">
                            <?php if (!empty($log['photo'])): ?>
                                <img class="avatar" src="<?= url('media/employees/' . basename($log['photo'])) ?>" alt="" style="object-fit:cover">
                            <?php else: ?>
                                <span class="avatar" style="background:<?= e(avatar_color($log['email'])) ?>"><?= e(initials($log['full_name'])) ?></span>
                            <?php endif; ?>
                            <span>
                                <b><?= e($log['full_name']) ?></b>
                                <span class="mono"><?= e($log['employee_code']) ?></span>
                            </span>
                        </div>
                    </td>
                    <td class="small">
                        <?= e($log['unit_name'] ?? '-') ?>
                        <div class="muted" style="font-size:10.6px"><?= e($log['department_name'] ?? '—') ?><?= $log['shift_name'] ? ' · ' . e($log['shift_name']) : '' ?></div>
                    </td>
                    <td class="mono small">
                        <?= $log['check_in_at'] ? date('H:i:s', strtotime((string) $log['check_in_at'])) : '—' ?>
                        <?php if (!empty($log['check_in_accuracy'])): ?>
                            <div class="muted" style="font-size:9.6px">±<?= (int) $log['check_in_accuracy'] ?> m akurasi</div>
                        <?php endif; ?>
                    </td>
                    <td class="mono small"><?= $log['check_out_at'] ? date('H:i:s', strtotime((string) $log['check_out_at'])) : '<span class="live-pill" style="padding:2px 7px"><span class="dot"></span> aktif</span>' ?></td>
                    <td class="small">
                        <?php if ($distance !== null): ?>
                            <span class="badge badge-<?= $inRadius ? 'teal' : 'amber' ?>">
                                <?= icon($inRadius ? 'check' : 'alert') ?> <?= number_id((int) $distance) ?> m
                            </span>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="num small">
                        <?= (int) $log['late_minutes'] > 0
                            ? '<span style="color:var(--amber-600);font-weight:650">' . (int) $log['late_minutes'] . '′</span>'
                            : '<span class="muted">0</span>' ?>
                    </td>
                    <td>
                        <?php if (!empty($log['check_in_photo'])): ?>
                            <a href="<?= url('media/attendance/' . basename($log['check_in_photo'])) ?>" target="_blank" rel="noopener">
                                <img class="thumb" src="<?= url('media/attendance/' . basename($log['check_in_photo'])) ?>" alt="Selfie presensi">
                            </a>
                        <?php else: ?>
                            <span class="muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= status_badge($log['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?>
                <tr><td colspan="8">
                    <div class="empty-state">
                        <div class="empty-icon"><?= icon('map-pin') ?></div>
                        <h3>Belum ada presensi pada <?= tanggal($date) ?></h3>
                        <p>Catatan check-in karyawan akan muncul otomatis di sini saat mereka melakukan presensi.</p>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
setTimeout(function () { window.location.reload(); }, 30000);
</script>
