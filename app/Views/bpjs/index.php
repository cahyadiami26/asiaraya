<?php
/**
 * Rekap BPJS Kesehatan & Ketenagakerjaan — Batch 20.
 * @var array $filters, $summary, $rows, $yearly, $coverage, $months, $years, $units, $programs
 */
$query = array_filter([
    'year'  => $filters['year'],
    'month' => $filters['month'] ?: null,
    'unit'  => $filters['unit'] ?: null,
]);
$periodLabel = (int) $filters['month'] > 0 ? bulan_id((int) $filters['month']) . ' ' . (int) $filters['year'] : 'Tahun ' . (int) $filters['year'];
$maxYearly = 0.0;
foreach ($yearly as $month) {
    $maxYearly = max($maxYearly, (float) $month['grand_total']);
}
$tone = static fn (string $tone): string => in_array($tone, ['teal', 'indigo', 'amber', 'rose', 'slate'], true) ? $tone : 'slate';
?>
<div class="page-head">
    <div>
        <h1>Rekap BPJS</h1>
        <div class="sub">Iuran Kesehatan &amp; Ketenagakerjaan per program — porsi karyawan dan perusahaan, langsung dari komponen payroll.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('bpjs/csv?' . http_build_query($query)) ?>"><?= icon('download') ?> Export CSV</a>
        <a class="btn btn-primary" href="<?= url('bpjs/pdf?' . http_build_query($query)) ?>" target="_blank"><?= icon('printer') ?> Cetak Rekap</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Peserta</span>
        <span class="v"><?= number_id($summary['employees']) ?></span>
        <span class="s"><?= e($periodLabel) ?></span>
    </div>
    <div class="mini-stat">
        <span class="k">Iuran Perusahaan</span>
        <span class="v" style="font-size:15px"><?= rupiah($summary['company_total']) ?></span>
        <span class="s">beban perusahaan</span>
    </div>
    <div class="mini-stat">
        <span class="k">Iuran Karyawan</span>
        <span class="v" style="font-size:15px"><?= rupiah($summary['employee_total']) ?></span>
        <span class="s">dipotong dari gaji</span>
    </div>
    <div class="mini-stat">
        <span class="k">Total Setoran</span>
        <span class="v" style="font-size:15px;color:var(--teal-700)"><?= rupiah($summary['grand_total']) ?></span>
        <span class="s">perusahaan + karyawan</span>
    </div>
    <div class="mini-stat">
        <span class="k">Nomor Peserta</span>
        <span class="v" style="color:<?= $coverage['missing'] > 0 ? 'var(--amber-600)' : 'var(--teal-700)' ?>"><?= number_id($coverage['active'] - $coverage['missing']) ?>/<?= number_id($coverage['active']) ?></span>
        <span class="s"><?= number_id($coverage['missing']) ?> belum lengkap</span>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Rincian per Peserta</h2>
                <div class="sub"><?= number_id(count($rows)) ?> karyawan pada <?= e($periodLabel) ?>.</div></div>
        </div>

        <form class="filter-bar" method="get" action="<?= url('bpjs') ?>">
            <select class="select" name="year" data-auto-submit style="min-width:104px">
                <?php foreach ($years as $year): ?>
                    <option value="<?= (int) $year ?>" <?= (int) $filters['year'] === (int) $year ? 'selected' : '' ?>><?= (int) $year ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="month" data-auto-submit style="min-width:150px">
                <option value="">Semua bulan</option>
                <?php foreach ($months as $month): ?>
                    <option value="<?= (int) $month ?>" <?= (int) $filters['month'] === (int) $month ? 'selected' : '' ?>><?= e(bulan_id((int) $month)) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="unit" data-auto-submit style="min-width:160px">
                <option value="">Semua unit</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= (int) $unit['id'] ?>" <?= (int) $filters['unit'] === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th>Karyawan</th><th class="num">Upah</th>
                    <th class="num">KES</th><th class="num">JHT</th><th class="num">JP</th>
                    <th class="num">JKK</th><th class="num">JKM</th><th class="num">Total</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <div class="name-cell">
                                <span class="avatar sm" style="background:#e8f0f7;color:var(--navy-800)"><?= strtoupper(mb_substr((string) $row['employee_name'], 0, 2)) ?></span>
                                <div>
                                    <b><?= e($row['employee_name']) ?></b>
                                    <span class="mono"><?= e($row['employee_code']) ?></span>
                                    <span><?= e($row['unit_name'] ?: '—') ?>
                                        <?= $row['kes_no'] || $row['tk_no'] ? '' : ' · nomor peserta belum ada' ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="num small"><?= rupiah($row['wage']) ?></td>
                        <?php foreach (['KES', 'JHT', 'JP'] as $key): ?>
                            <td class="num small">
                                <?= rupiah((float) $row[$key . '_ER'] + (float) $row[$key . '_EE']) ?>
                                <div class="muted" style="font-size:9.4px">P <?= rupiah($row[$key . '_ER']) ?> · K <?= rupiah($row[$key . '_EE']) ?></div>
                            </td>
                        <?php endforeach; ?>
                        <td class="num small"><?= rupiah($row['JKK_ER']) ?></td>
                        <td class="num small"><?= rupiah($row['JKM_ER']) ?></td>
                        <td class="num"><b><?= rupiah($row['grand_total']) ?></b>
                            <div class="muted" style="font-size:9.4px">P <?= rupiah($row['company_total']) ?> · K <?= rupiah($row['employee_total']) ?></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="8">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('shield') ?></div>
                            <h3>Belum ada iuran BPJS pada periode ini</h3>
                            <p>Iuran terbentuk otomatis saat payroll dihitung dan disetujui Finance. Pastikan pengaturan BPJS aktif.</p>
                            <a class="btn btn-sm btn-primary" style="margin-top:10px" href="<?= url('settings?section=payroll') ?>"><?= icon('settings') ?> Pengaturan Payroll</a>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
                <?php if ($rows): ?>
                    <tfoot>
                    <tr>
                        <th>TOTAL <?= number_id(count($rows)) ?> peserta</th>
                        <th class="num"><?= rupiah($summary['wage']) ?></th>
                        <th class="num" colspan="5">
                            Perusahaan <?= rupiah($summary['company_total']) ?> · Karyawan <?= rupiah($summary['employee_total']) ?>
                        </th>
                        <th class="num"><?= rupiah($summary['grand_total']) ?></th>
                    </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <div class="card__foot small muted" style="padding:10px 16px">
            P = porsi perusahaan, K = porsi karyawan. Batas upah KES Rp 12 juta &amp; JP Rp 10 juta diterapkan saat payroll dihitung.
        </div>
    </section>

    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head"><div><h2>Per Program</h2>
                <div class="sub">Akumulasi <?= e($periodLabel) ?>.</div></div></div>
            <div class="list-rows">
                <?php foreach ($summary['programs'] as $program): ?>
                    <div class="list-row">
                        <div>
                            <b><?= e($program['label']) ?></b>
                            <div class="muted" style="font-size:10px"><?= number_id($program['participants']) ?> peserta · <?= e($program['note']) ?></div>
                        </div>
                        <div style="text-align:right">
                            <b><?= rupiah($program['total']) ?></b>
                            <div class="muted" style="font-size:9.6px">P <?= rupiah($program['company']) ?> · K <?= rupiah($program['employee']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Tren Bulanan</h2>
                <div class="sub">Total setoran per masa payroll <?= (int) $filters['year'] ?>.</div></div></div>
            <div class="card__body flex-col gap-10">
                <?php foreach ($yearly as $month): ?>
                    <?php $pct = $maxYearly > 0 ? max(3, (int) round((float) $month['grand_total'] / $maxYearly * 100)) : 0; ?>
                    <div>
                        <div class="flex-between" style="font-size:11px;margin-bottom:3px">
                            <span><?= e($month['label']) ?></span><b><?= rupiah($month['grand_total']) ?></b>
                        </div>
                        <div class="progress thin"><span style="width:<?= $pct ?>%"></span></div>
                        <div class="muted" style="font-size:9.6px;margin-top:2px">
                            <?= number_id($month['employees']) ?> peserta · P <?= rupiah($month['company_total']) ?> · K <?= rupiah($month['employee_total']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$yearly): ?>
                    <div class="empty-state" style="padding:10px 0"><h3>Belum ada periode final</h3></div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Kelengkapan Data</h2>
                <div class="sub">Nomor peserta untuk pendaftaran/klaim.</div></div></div>
            <div class="card__body small" style="line-height:1.75">
                <div class="flex-between"><span>Karyawan aktif</span><b><?= number_id($coverage['active']) ?></b></div>
                <div class="flex-between"><span>Tanpa no. BPJS Kesehatan</span><b style="color:var(--amber-600)"><?= number_id($coverage['no_kes']) ?></b></div>
                <div class="flex-between"><span>Tanpa no. BPJS TK</span><b style="color:var(--amber-600)"><?= number_id($coverage['no_tk']) ?></b></div>
                <a class="btn btn-sm btn-outline" style="margin-top:10px" href="<?= url('employees') ?>"><?= icon('users') ?> Lengkapi Data Karyawan</a>
            </div>
        </section>
    </div>
</div>
