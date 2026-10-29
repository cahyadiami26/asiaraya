<?php
/**
 * Rekap presensi bulanan.
 * @var array $rows, $totals, $trend, $filters, $units, $departments, $latePolicy, $paginator
 * @var string $month, $monthLabel, $prevMonth, $nextMonth
 */
$query = array_filter([
    'month'      => $month,
    'unit'       => $filters['unit'] ?: null,
    'department' => $filters['department'] ?: null,
    'q'          => $filters['q'] ?: null,
]);
$link = static fn (int $page): string => url('attendance/rekap') . '?' . http_build_query($query + ['page' => $page]);
$maxPresent = max(1, ...array_map(static fn ($d) => (int) $d['present'], $trend ?: [['present' => 1]]));
$policyLabel = match (strtoupper($latePolicy['type'])) {
    'PER_DAY'     => 'per hari terlambat',
    'FIXED'       => 'flat per kejadian',
    'PERCENT'     => '% dari gaji pokok',
    'PROGRESSIVE' => 'progresif per tier keterlambatan',
    default       => 'per menit',
};
$isProgressive = strtoupper($latePolicy['type']) === 'PROGRESSIVE';
?>
<div class="page-head">
    <div>
        <h1>Rekap Presensi Bulanan</h1>
        <div class="sub">Akumulasi kehadiran, keterlambatan, dan potongan denda per karyawan — <?= e($monthLabel) ?>.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('attendance/daily') ?>"><?= icon('list') ?> Presensi Harian</a>
        <a class="btn btn-primary" href="<?= url('attendance/rekap') . '?' . http_build_query($query + ['export' => 'csv']) ?>"><?= icon('download') ?> Export CSV</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Rata-rata Kehadiran</span>
        <span class="v"><?= number_id($totals['rate'], 1) ?>%</span>
        <span class="s"><?= number_id($totals['present']) ?> catatan hadir</span>
    </div>
    <div class="mini-stat">
        <span class="k">Terlambat</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($totals['late']) ?></span>
        <span class="s"><?= number_id($totals['late_minutes']) ?> menit total</span>
    </div>
    <div class="mini-stat">
        <span class="k">Alpha</span>
        <span class="v" style="color:var(--rose-600)"><?= number_id($totals['absent']) ?></span>
        <span class="s"><?= number_id($totals['leave']) ?> cuti/izin</span>
    </div>
    <div class="mini-stat">
        <span class="k">Total Jam Kerja</span>
        <span class="v"><?= number_id($totals['work_hours'], 1) ?></span>
        <span class="s"><?= number_id($totals['employees']) ?> karyawan aktif</span>
    </div>
</div>

<section class="card" style="margin-bottom:16px">
    <div class="card__head">
        <div><h2>Tren Kehadiran Harian</h2>
            <div class="sub">Jumlah catatan hadir per hari kerja pada bulan ini.</div></div>
        <div class="flex" style="gap:6px;align-items:center">
            <a class="btn btn-sm btn-ghost" href="<?= url('attendance/rekap?month=' . $prevMonth) ?>"><?= icon('chevron-left') ?></a>
            <span class="badge badge-slate" style="height:24px"><?= e($monthLabel) ?></span>
            <a class="btn btn-sm btn-ghost" href="<?= url('attendance/rekap?month=' . $nextMonth) ?>"><?= icon('chevron-right') ?></a>
        </div>
    </div>
    <div class="card__body">
        <div class="trend-chart">
            <?php foreach ($trend as $day): ?>
                <?php
                $height = (int) round(((int) $day['present'] / $maxPresent) * 100);
                $isWeekend = $day['dow'] >= 6;
                ?>
                <div class="trend-bar <?= $isWeekend ? 'is-weekend' : '' ?>" title="<?= tanggal($day['date']) ?>: <?= (int) $day['present'] ?> hadir, <?= (int) $day['late'] ?> terlambat">
                    <span class="trend-bar__fill" style="height:<?= max(2, $height) ?>%;<?= (int) $day['present'] === 0 ? 'background:#e2e8f0' : '' ?>"></span>
                    <span class="trend-bar__label"><?= (int) $day['day'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="flex wrap small muted" style="gap:14px;margin-top:10px">
            <span><span class="dot" style="background:var(--teal-500)"></span> Hadir</span>
            <span><span class="dot" style="background:#e2e8f0"></span> Tanpa catatan</span>
            <span><span class="dot" style="background:var(--slate-50)"></span> Akhir pekan</span>
            <span style="margin-left:auto">
                Kebijakan denda:
                <?php if ($isProgressive): ?>
                    <?= e($policyLabel) ?>
                    <?php if (!empty($latePolicy['tiers'])): ?>
                        (<?= e(implode(' · ', array_map(
                            static fn (array $t): string => $t['max'] === null
                                ? '>' . max(0, (int) $t['min'] - 1) . 'm ' . rupiah($t['amount'])
                                : (int) $t['min'] . '–' . (int) $t['max'] . 'm ' . rupiah($t['amount']),
                            $latePolicy['tiers']
                        ))) ?>)
                    <?php endif; ?>
                <?php else: ?>
                    <?= rupiah($latePolicy['value']) ?> <?= e($policyLabel) ?>
                <?php endif; ?>
                · toleransi <?= (int) $latePolicy['tolerance'] ?> menit
            </span>
        </div>
    </div>
</section>

<section class="card">
    <form class="filter-bar" method="get" action="<?= url('attendance/rekap') ?>">
        <input class="input" type="month" name="month" value="<?= e($month) ?>" style="width:160px" data-auto-submit>
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
        <div class="input-group" style="max-width:220px">
            <?= icon('search') ?>
            <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Cari nama / ID…">
        </div>
        <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
        <a class="btn btn-sm btn-ghost" href="<?= url('attendance/rekap') ?>">Reset</a>
    </form>

    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th>Karyawan</th><th>Unit / Departemen</th>
                <th class="num">Hadir</th><th class="num">Telat</th><th class="num">Alpha</th><th class="num">Cuti</th>
                <th class="num">Menit Telat</th><th class="num">Jam Kerja</th>
                <th class="num">Kehadiran</th><th class="num">Denda</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $rate = (float) $row['attendance_rate']; ?>
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
                    <td class="num"><?= number_id($row['present_days']) ?></td>
                    <td class="num"><?= number_id($row['late_days']) ?></td>
                    <td class="num"><?= number_id($row['absent_days']) ?></td>
                    <td class="num"><?= number_id($row['leave_days']) ?></td>
                    <td class="num small"><?= number_id($row['late_minutes']) ?></td>
                    <td class="num small"><?= number_id($row['work_hours'], 1) ?></td>
                    <td class="num">
                        <span style="font-weight:650;color:<?= $rate >= 95 ? 'var(--teal-700)' : ($rate >= 85 ? 'var(--amber-600)' : 'var(--rose-600)') ?>"><?= number_id($rate, 1) ?>%</span>
                        <div class="progress thin" style="margin-top:4px;width:64px;margin-left:auto">
                            <span style="width:<?= min(100, $rate) ?>%;background:<?= $rate >= 95 ? 'var(--teal-500)' : ($rate >= 85 ? 'var(--amber-500)' : 'var(--rose-500)') ?>"></span>
                        </div>
                    </td>
                    <td class="num small">
                        <?php if ($row['late_deduction'] > 0): ?>
                            <span <?= !empty($row['late_day_detail']) ? 'title="Rincian keterlambatan: ' . e($row['late_day_detail']) . '"' : '' ?>>
                                <?= rupiah($row['late_deduction']) ?>
                            </span>
                            <?php if (!empty($row['late_tier_label'])): ?>
                                <div class="muted" style="font-size:9.4px;font-weight:400"><?= e($row['late_tier_label']) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="10" class="table-empty"><?= icon('receipt') ?>Belum ada data pada periode ini.</td></tr>
            <?php endif; ?>
            </tbody>
            <?php if ($rows): ?>
                <tfoot>
                <tr>
                    <td colspan="2">Total (<?= number_id($totals['employees']) ?> karyawan)</td>
                    <td class="num"><?= number_id($totals['present']) ?></td>
                    <td class="num"><?= number_id($totals['late']) ?></td>
                    <td class="num"><?= number_id($totals['absent']) ?></td>
                    <td class="num"><?= number_id($totals['leave']) ?></td>
                    <td class="num"><?= number_id($totals['late_minutes']) ?></td>
                    <td class="num"><?= number_id($totals['work_hours'], 1) ?></td>
                    <td class="num"><?= number_id($totals['rate'], 1) ?>%</td>
                    <td class="num">—</td>
                </tr>
                </tfoot>
            <?php endif; ?>
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
