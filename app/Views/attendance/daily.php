<?php
/**
 * Presensi harian (HR).
 * @var array $rows, $stats, $filters, $units, $departments, $paginator
 * @var string $date
 */
$query = array_filter([
    'date'       => $date,
    'unit'       => $filters['unit'] ?: null,
    'department' => $filters['department'] ?: null,
    'status'     => $filters['status'] ?: null,
    'q'          => $filters['q'] ?: null,
]);
$link = static fn (int $page): string => url('attendance/daily') . '?' . http_build_query($query + ['page' => $page]);
?>
<div class="page-head">
    <div>
        <h1>Presensi Harian</h1>
        <div class="sub">Rincian kehadiran seluruh karyawan pada satu tanggal, termasuk jarak geofence dan sumber presensi.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('attendance/live?date=' . $date) ?>"><?= icon('map-pin') ?> Live Log</a>
        <a class="btn btn-outline" href="<?= url('attendance/daily') . '?' . http_build_query($query + ['export' => 'csv']) ?>"><?= icon('download') ?> Export CSV</a>
        <form class="inline-form" method="post" action="<?= url('attendance/mark-absent') ?>"
              data-confirm="Tandai ALPHA untuk semua karyawan aktif yang belum memiliki catatan presensi pada tanggal ini?">
            <?= csrf_field() ?>
            <input type="hidden" name="date" value="<?= e($date) ?>">
            <button class="btn btn-soft" type="submit"><?= icon('alert') ?> Tandai Alpha</button>
        </form>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Kehadiran</span>
        <span class="v"><?= number_id($stats['rate'], 1) ?>%</span>
        <span class="s"><?= number_id($stats['present']) ?> dari <?= number_id($stats['total']) ?> karyawan</span>
    </div>
    <div class="mini-stat">
        <span class="k">Terlambat</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($stats['late']) ?></span>
        <span class="s"><?= number_id($stats['late_minutes']) ?> menit akumulatif</span>
    </div>
    <div class="mini-stat">
        <span class="k">Masih di Kantor</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id($stats['on_site']) ?></span>
        <span class="s">Belum check-out</span>
    </div>
    <div class="mini-stat">
        <span class="k">Belum Presensi</span>
        <span class="v" style="color:var(--rose-600)"><?= number_id($stats['none']) ?></span>
        <span class="s"><?= number_id($stats['leave']) ?> cuti · <?= number_id($stats['absent']) ?> alpha</span>
    </div>
</div>

<section class="card">
    <form class="filter-bar" method="get" action="<?= url('attendance/daily') ?>">
        <input class="input" type="date" name="date" value="<?= e($date) ?>" style="width:158px" data-auto-submit>
        <select class="select" name="unit" data-auto-submit style="min-width:165px">
            <option value="">Semua unit</option>
            <?php foreach ($units as $unit): ?>
                <option value="<?= (int) $unit['id'] ?>" <?= (int) $filters['unit'] === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="select" name="department" data-auto-submit style="min-width:155px">
            <option value="">Semua departemen</option>
            <?php foreach ($departments as $dept): ?>
                <option value="<?= (int) $dept['id'] ?>" <?= (int) $filters['department'] === (int) $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="select" name="status" data-auto-submit style="min-width:150px">
            <option value="">Semua status</option>
            <?php foreach (['PRESENT' => 'Tepat waktu', 'LATE' => 'Terlambat', 'LEAVE' => 'Cuti/Izin', 'ABSENT' => 'Alpha', 'ON_SITE' => 'Masih di kantor', 'NONE' => 'Belum presensi'] as $key => $label): ?>
                <option value="<?= $key ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="input-group" style="max-width:220px">
            <?= icon('search') ?>
            <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Cari nama / ID…">
        </div>
        <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
        <a class="btn btn-sm btn-ghost" href="<?= url('attendance/daily') ?>">Reset</a>
    </form>

    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th>Karyawan</th><th>Unit / Departemen</th><th>Shift</th>
                <th>Masuk</th><th>Keluar</th><th class="num">Kerja</th>
                <th class="num">Terlambat</th><th>Geofence</th><th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $distance = $row['check_in_distance'];
                $hasRecord = !empty($row['attendance_id']);
                $late = (int) $row['late_minutes'];
                $workMinutes = (int) $row['work_minutes'];
                ?>
                <tr>
                    <td>
                        <div class="name-cell">
                            <?php if (!empty($row['photo'])): ?>
                                <img class="avatar" src="<?= url('media/employees/' . basename($row['photo'])) ?>" alt="" style="object-fit:cover">
                            <?php else: ?>
                                <span class="avatar" style="background:<?= e(avatar_color($row['email'])) ?>"><?= e(initials($row['full_name'])) ?></span>
                            <?php endif; ?>
                            <span>
                                <b><?= e($row['full_name']) ?></b>
                                <span class="mono"><?= e($row['employee_code']) ?></span>
                            </span>
                        </div>
                    </td>
                    <td class="small">
                        <?= e($row['unit_name'] ?? '-') ?>
                        <div class="muted" style="font-size:10.6px"><?= e($row['department_name'] ?? '—') ?></div>
                    </td>
                    <td class="small">
                        <?php if ($row['shift_name']): ?>
                            <?= e($row['shift_name']) ?>
                            <div class="muted mono" style="font-size:10.4px"><?= e(substr((string) $row['check_in_time'], 0, 5)) ?>–<?= e(substr((string) $row['check_out_time'], 0, 5)) ?></div>
                        <?php else: ?>
                            <span class="muted">Tanpa shift</span>
                        <?php endif; ?>
                    </td>
                    <td class="mono small">
                        <?= $row['check_in_at'] ? date('H:i', strtotime((string) $row['check_in_at'])) : '—' ?>
                        <?php if ($hasRecord && $row['check_in_photo']): ?>
                            <a href="<?= url('media/attendance/' . basename($row['check_in_photo'])) ?>" target="_blank" rel="noopener">
                                <img class="thumb sm" src="<?= url('media/attendance/' . basename($row['check_in_photo'])) ?>" alt="Selfie">
                            </a>
                        <?php endif; ?>
                    </td>
                    <td class="mono small"><?= $row['check_out_at'] ? date('H:i', strtotime((string) $row['check_out_at'])) : '—' ?></td>
                    <td class="num small"><?= $workMinutes > 0 ? intdiv($workMinutes, 60) . 'j ' . $workMinutes % 60 . 'm' : '—' ?></td>
                    <td class="num small">
                        <?= $late > 0 ? '<span style="color:var(--amber-600);font-weight:650">+' . $late . '′</span>' : '<span class="muted">—</span>' ?>
                    </td>
                    <td class="small">
                        <?php if ($distance !== null): ?>
                            <span class="badge badge-slate"><?= number_id((int) $distance) ?> m</span>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($hasRecord): ?>
                            <?= status_badge($row['status']) ?>
                            <div class="muted" style="font-size:9.8px;margin-top:2px"><?= e($row['source']) ?></div>
                        <?php else: ?>
                            <span class="badge badge-rose">Belum presensi</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="9" class="table-empty"><?= icon('users') ?>Tidak ada karyawan pada filter ini.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card__foot flex-between wrap" style="padding:10px 16px">
        <span class="small muted">Menampilkan <?= number_id($paginator->from()) ?>–<?= number_id($paginator->to()) ?> dari <?= number_id($paginator->total) ?> karyawan</span>
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
