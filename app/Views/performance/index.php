<?php
/**
 * Monitor penilaian kinerja (HR).
 * @var array $reviews, $filters, $stats, $periods, $units, $statuses, $statusTones, $ratings, $ratingTones, $paginator
 * @var callable $pageLink
 * @var array $distribution
 */
use App\Models\PerformanceModel;

$badge = static fn (string $status): string => '<span class="badge badge-' . ($statusTones[$status] ?? 'slate') . '">'
    . e($statuses[$status] ?? $status) . '</span>';
$ratingChip = static function (?string $rating) use ($ratings, $ratingTones): string {
    if ($rating === null || $rating === '') {
        return '<span class="muted small">—</span>';
    }
    return '<span class="rating-chip rating-' . ($ratingTones[$rating] ?? 'slate') . '">' . e($rating) . '</span>';
};
$dist = $stats['distribution'];
$distTotal = max(1, array_sum($dist));
$exportQuery = http_build_query(array_filter([
    'period' => $filters['period'] ?: null,
    'status' => $filters['status'] ?: null,
    'unit'   => $filters['unit'] ?: null,
    'rating' => $filters['rating'] ?: null,
    'q'      => $filters['q'] ?: null,
], static fn ($v) => $v !== null && $v !== ''));
?>
<div class="page-head">
    <div>
        <h1>Penilaian Kinerja</h1>
        <div class="sub">Monitor self assessment, penilaian atasan, dan kalibrasi hasil akhir setiap karyawan.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('performance/indicators') ?>"><?= icon('layers') ?> Indikator</a>
        <a class="btn btn-outline" href="<?= url('performance/periods') ?>"><?= icon('calendar') ?> Periode</a>
        <a class="btn btn-primary" href="<?= url('performance/export' . ($exportQuery ? '?' . $exportQuery : '')) ?>"><?= icon('download') ?> Export CSV</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Total Penilaian</span>
        <span class="v"><?= number_id($stats['total']) ?></span>
        <span class="s"><?= number_id($stats['periods']) ?> periode · <?= number_id($stats['open']) ?> berjalan</span>
    </div>
    <div class="mini-stat">
        <span class="k">Self Assessment</span>
        <span class="v" style="color:var(--indigo-600)"><?= number_id($stats['self_done']) ?></span>
        <span class="s"><?= $stats['total'] > 0 ? (int) round($stats['self_done'] / $stats['total'] * 100) : 0 ?>% terkirim</span>
    </div>
    <div class="mini-stat">
        <span class="k">Dinilai Atasan</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($stats['manager_done']) ?></span>
        <span class="s">menunggu kalibrasi HR</span>
    </div>
    <div class="mini-stat">
        <span class="k">Sudah Final</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id($stats['finalized']) ?></span>
        <span class="s">nilai rata-rata <?= PerformanceModel::scoreLabel($stats['avg_final']) ?></span>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Daftar Penilaian</h2>
                <div class="sub"><?= number_id($paginator->total) ?> penilaian sesuai filter.</div></div>
        </div>

        <form class="filter-bar" method="get" action="<?= url('performance') ?>">
            <select class="select" name="period" data-auto-submit style="min-width:200px">
                <option value="">Semua periode</option>
                <?php foreach ($periods as $period): ?>
                    <option value="<?= (int) $period['id'] ?>" <?= (int) $filters['period'] === (int) $period['id'] ? 'selected' : '' ?>>
                        <?= e($period['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="status" data-auto-submit style="min-width:170px">
                <option value="">Semua status</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="rating" data-auto-submit style="min-width:130px">
                <option value="">Semua mutu</option>
                <?php foreach ($ratings as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filters['rating'] === $key ? 'selected' : '' ?>><?= e($key) ?> — <?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="unit" data-auto-submit style="min-width:140px">
                <option value="">Semua unit</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= (int) $unit['id'] ?>" <?= (int) $filters['unit'] === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="input-group" style="max-width:190px">
                <?= icon('search') ?>
                <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Nama / NIK…">
            </div>
            <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
            <?php if ($exportQuery): ?>
                <a class="btn btn-sm btn-ghost" href="<?= url('performance') ?>"><?= icon('x') ?> Reset</a>
            <?php endif; ?>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th>Karyawan</th><th>Periode</th><th class="num">Self</th><th class="num">Atasan</th>
                    <th class="num">Akhir</th><th>Mutu</th><th>Status</th><th style="width:70px"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($reviews as $review): ?>
                    <tr>
                        <td>
                            <b><?= e($review['full_name']) ?></b>
                            <div class="muted mono" style="font-size:9.6px"><?= e($review['employee_code']) ?></div>
                        </td>
                        <td class="small">
                            <?= e($review['period_name']) ?>
                            <div class="muted" style="font-size:9.8px"><?= e($review['department_name'] ?? $review['unit_name'] ?? '-') ?></div>
                        </td>
                        <td class="num small"><?= PerformanceModel::scoreLabel($review['self_score'] !== null ? (float) $review['self_score'] : null) ?></td>
                        <td class="num small"><?= PerformanceModel::scoreLabel($review['manager_score'] !== null ? (float) $review['manager_score'] : null) ?></td>
                        <td class="num"><b><?= PerformanceModel::scoreLabel($review['final_score'] !== null ? (float) $review['final_score'] : null) ?></b></td>
                        <td><?= $ratingChip($review['rating'] !== null ? (string) $review['rating'] : null) ?></td>
                        <td><?= $badge((string) $review['status']) ?></td>
                        <td>
                            <a class="btn btn-sm btn-ghost" href="<?= url('performance/' . (int) $review['id']) ?>" title="Detail"><?= icon('arrow-right') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$reviews): ?>
                    <tr><td colspan="8">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('award') ?></div>
                            <h3>Belum ada penilaian</h3>
                            <p>Buat periode penilaian lalu buka agar karyawan dapat mengisi self assessment.</p>
                            <a class="btn btn-primary btn-sm" href="<?= url('performance/periods') ?>"><?= icon('plus') ?> Buat Periode</a>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card__foot flex-between wrap" style="padding:10px 16px">
            <span class="small muted">Menampilkan <?= number_id($paginator->from()) ?>–<?= number_id($paginator->to()) ?> dari <?= number_id($paginator->total) ?></span>
            <?php if ($paginator->lastPage() > 1): ?>
                <div class="pagination" style="padding:0">
                    <?php if ($paginator->hasPrevious()): ?><a href="<?= e(($pageLink)($paginator->page - 1)) ?>"><?= icon('chevron-left') ?></a><?php endif; ?>
                    <?php foreach ($paginator->window(5) as $page): ?>
                        <?php if ($page === $paginator->page): ?>
                            <span class="active"><?= $page ?></span>
                        <?php else: ?>
                            <a href="<?= e(($pageLink)($page)) ?>"><?= $page ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($paginator->hasNext()): ?><a href="<?= e(($pageLink)($paginator->page + 1)) ?>"><?= icon('chevron-right') ?></a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head"><div><h2>Distribusi Mutu</h2>
                <div class="sub">Sebaran huruf mutu seluruh penilaian final.</div></div></div>
            <div class="card__body">
                <?php foreach ($dist as $letter => $count): ?>
                    <div class="dist-row">
                        <span class="rating-chip rating-<?= $ratingTones[$letter] ?? 'slate' ?>"><?= e($letter) ?></span>
                        <div class="dist-bar">
                            <span style="width:<?= (int) round($count / $distTotal * 100) ?>%;background:var(--<?= $letter === 'A' ? 'teal-500' : ($letter === 'B' ? 'indigo-500' : ($letter === 'C' ? 'muted-2' : ($letter === 'D' ? 'amber-500' : 'rose-500'))) ?>)"></span>
                        </div>
                        <b class="small"><?= number_id($count) ?></b>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Periode Berjalan</h2>
                <a href="<?= url('performance/periods') ?>" class="small" style="font-weight:650">Kelola</a></div></div>
            <div class="card__body" style="padding:6px 16px 14px">
                <div class="list-rows">
                    <?php foreach (array_slice($periods, 0, 4) as $period): ?>
                        <div class="list-row" style="padding:9px 0">
                            <div class="list-row__body">
                                <b><?= e($period['name']) ?></b>
                                <span><?= tanggal($period['start_date'], 'd M Y') ?>–<?= tanggal($period['end_date'], 'd M Y') ?>
                                    · <?= number_id($period['participants']) ?> peserta</span>
                            </div>
                            <span class="badge badge-<?= \App\Models\PerformanceModel::PERIOD_TONES[$period['status']] ?? 'slate' ?>">
                                <?= e(\App\Models\PerformanceModel::PERIOD_STATUSES[$period['status']] ?? $period['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$periods): ?>
                        <div class="empty-state" style="padding:18px"><p>Belum ada periode penilaian.</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</div>
