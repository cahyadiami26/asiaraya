<?php
/**
 * Daftar periode payroll + pembuatan periode baru.
 * @var array $periods, $filters, $units, $years, $stats, $trend, $statuses, $settings, $headcounts
 * @var \App\Core\Paginator $paginator
 * @var bool $isHr, $isFin
 * @var array $old, $errors
 */
$baseQuery = array_filter([
    'unit'   => $filters['unit'] ?: null,
    'year'   => $filters['year'] ?: null,
    'status' => $filters['status'] ?: null,
    'q'      => $filters['q'] ?: null,
]);
$link = static fn (int $page): string => url('payroll') . '?' . http_build_query($baseQuery + ['page' => $page]);
$maxTrend = max(1, ...array_map(static fn ($t) => (float) $t['net'], $trend ?: [['net' => 1]]));
?>
<div class="page-head">
    <div>
        <h1>Payroll</h1>
        <div class="sub">Periode per unit, kalkulasi slip dari presensi &amp; lembur, alur HR → Finance → dibayar.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('components') ?>"><?= icon('layers') ?> Komponen Gaji</a>
        <a class="btn btn-primary" href="#period-form" data-open-form><?= icon('plus') ?> Periode Baru</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Periode Aktif</span>
        <span class="v"><?= number_id($stats['total']) ?></span>
        <span class="s"><?= number_id($stats['paid']) ?> lunas · <?= number_id($stats['draft']) ?> draft</span>
    </div>
    <div class="mini-stat">
        <span class="k">Menunggu Finance</span>
        <span class="v" style="color:var(--amber-600, #b45309)"><?= number_id($stats['pending_finance']) ?></span>
        <span class="s"><?= number_id($stats['sent']) ?> dikirim · <?= number_id($stats['approved']) ?> disetujui</span>
    </div>
    <div class="mini-stat">
        <span class="k">Net Dibayar <?= date('Y') ?></span>
        <span class="v" style="font-size:16px"><?= rupiah($stats['ytd']['net'] ?? 0) ?></span>
        <span class="s"><?= number_id($stats['ytd']['employees'] ?? 0) ?> slip lunas</span>
    </div>
    <div class="mini-stat">
        <span class="k">BPJS Perusahaan <?= date('Y') ?></span>
        <span class="v" style="font-size:16px"><?= rupiah($stats['ytd']['bpjs_company'] ?? 0) ?></span>
        <span class="s">akumulasi periode lunas</span>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Daftar Periode</h2>
                <div class="sub"><?= number_id($paginator->total) ?> periode tercatat pada filter ini.</div></div>
            <?php if ($stats['latest']): ?>
                <span class="badge badge-slate">Terakhir: <?= e($stats['latest']['unit_name'] ?? '-') ?> · <?= e(bulan_id((int) $stats['latest']['period_month'])) ?> <?= (int) $stats['latest']['period_year'] ?></span>
            <?php endif; ?>
        </div>

        <form class="filter-bar" method="get" action="<?= url('payroll') ?>">
            <select class="select" name="unit" data-auto-submit style="min-width:150px">
                <option value="">Semua unit</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= (int) $unit['id'] ?>" <?= $filters['unit'] === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="year" data-auto-submit style="min-width:110px">
                <option value="">Semua tahun</option>
                <?php foreach ($years as $year): ?>
                    <option value="<?= $year ?>" <?= $filters['year'] === $year ? 'selected' : '' ?>><?= $year ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="status" data-auto-submit style="min-width:150px">
                <option value="">Semua status</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="input-group" style="max-width:190px">
                <?= icon('search') ?>
                <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Unit / catatan…">
            </div>
            <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th>Periode</th><th>Unit</th><th>Cut-off → Bayar</th>
                    <th class="num">Karyawan</th><th class="num">Bruto</th><th class="num">Net</th>
                    <th>Status</th><th style="width:70px"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($periods as $period): ?>
                    <tr>
                        <td>
                            <b><?= e(bulan_id((int) $period['period_month'])) ?> <?= (int) $period['period_year'] ?></b>
                            <div class="muted" style="font-size:10px"><?= tanggal($period['start_date']) ?> – <?= tanggal($period['end_date']) ?></div>
                        </td>
                        <td class="small">
                            <b><?= e($period['unit_name'] ?? '-') ?></b>
                            <div class="mono muted" style="font-size:10px"><?= e($period['unit_code'] ?? '') ?></div>
                        </td>
                        <td class="small">
                            <?= tanggal($period['cutoff_date']) ?>
                            <div class="muted" style="font-size:10px">bayar <?= tanggal($period['pay_date']) ?></div>
                        </td>
                        <td class="num small"><?= number_id($period['total_employee']) ?></td>
                        <td class="num small"><?= rupiah($period['total_gross']) ?></td>
                        <td class="num"><b><?= rupiah($period['total_net']) ?></b></td>
                        <td><?= status_badge($period['status']) ?></td>
                        <td><a class="btn btn-sm btn-ghost" href="<?= url('payroll/' . $period['id']) ?>" title="Buka"><?= icon('chevron-right') ?></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$periods): ?>
                    <tr><td colspan="8">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('wallet') ?></div>
                            <h3>Belum ada periode payroll</h3>
                            <p>Buat periode untuk unit dan bulan tertentu, lalu jalankan kalkulasi.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card__foot flex-between wrap" style="padding:10px 16px">
            <span class="small muted">Menampilkan <?= number_id($paginator->from()) ?>–<?= number_id($paginator->to()) ?> dari <?= number_id($paginator->total) ?> periode</span>
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

    <div class="flex-col gap-16">
        <?php if ($isHr): ?>
            <section class="card" id="period-form">
                <div class="card__head"><div><h2>Periode Baru</h2>
                    <div class="sub">Satu periode = satu unit + satu bulan.</div></div></div>
                <form class="card__body" method="post" action="<?= url('payroll') ?>">
                    <?= csrf_field() ?>
                    <div class="flex-col gap-12">
                        <div class="field">
                            <label>Unit Organisasi <span class="req">*</span></label>
                            <select class="select" name="business_unit_id" required>
                                <option value="">Pilih unit…</option>
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?= (int) $unit['id'] ?>">
                                        <?= e($unit['name']) ?> · <?= number_id($headcounts[(int) $unit['id']] ?? 0) ?> karyawan
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-grid">
                            <div class="field">
                                <label>Bulan <span class="req">*</span></label>
                                <select class="select" name="period_month" required>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= $m === (int) date('n') ? 'selected' : '' ?>><?= e(bulan_id($m)) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Tahun <span class="req">*</span></label>
                                <select class="select" name="period_year" required>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?= $year ?>" <?= $year === (int) date('Y') ? 'selected' : '' ?>><?= $year ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label>Catatan</label>
                            <input class="input" name="notes" maxlength="200" placeholder="opsional">
                        </div>
                        <div class="field">
                            <span class="hint">Cut-off tanggal <b><?= (int) $settings['cutoff_day'] ?></b>, pembayaran tanggal <b><?= (int) $settings['pay_day'] ?></b>
                                (ubah di <a href="<?= url('settings?tab=payroll') ?>">Pengaturan Payroll</a>).</span>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-block" type="submit" style="margin-top:12px"><?= icon('plus') ?> Buat Periode</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="card">
            <div class="card__head"><div><h2>Tren Net Gaji</h2>
                <div class="sub">Periode terhitung terakhir.</div></div></div>
            <div class="card__body">
                <?php if (!$trend): ?>
                    <div class="empty-state" style="padding:12px 0">
                        <div class="empty-icon"><?= icon('trending') ?></div>
                        <h3>Belum ada data</h3>
                        <p>Jalankan kalkulasi periode pertama.</p>
                    </div>
                <?php else: ?>
                    <div class="flex-col gap-10">
                        <?php foreach (array_reverse($trend) as $row): ?>
                            <?php $pct = max(4, (int) round((float) $row['net'] / $maxTrend * 100)); ?>
                            <div>
                                <div class="flex-between" style="font-size:11px;margin-bottom:3px">
                                    <span><?= e(bulan_id((int) $row['period_month'])) ?> <?= (int) $row['period_year'] ?></span>
                                    <b><?= rupiah($row['net']) ?></b>
                                </div>
                                <div class="progress thin"><span style="width:<?= $pct ?>%"></span></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Alur Persetujuan</h2></div></div>
            <div class="card__body small muted" style="line-height:1.6">
                <p><b>HR</b> membuat periode → menjalankan <b>Hitung</b> → tanda tangan &amp; <b>Kirim ke Finance</b>.</p>
                <p><b>Finance</b> memverifikasi → <b>Setujui</b> → <b>Tandai Dibayar</b>.</p>
                <p>Slip gaji ber-QR dapat diunduh karyawan setelah status <b>Disetujui Finance</b>.</p>
            </div>
        </section>
    </div>
</div>

<script>
document.querySelectorAll('[data-open-form]').forEach(function (link) {
    link.addEventListener('click', function () {
        var panel = document.getElementById('period-form');
        if (panel) { panel.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    });
});
</script>
