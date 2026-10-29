<?php
/**
 * Rekap PPh 21 tahunan & bukti potong 1721-A1 (HR/FIN) — Batch 20.
 * @var array $filters, $rows, $summary, $monthly, $byPtkp, $years, $units
 * @var int $issued
 * @var bool $canManage
 */
$query = array_filter([
    'year' => $filters['year'],
    'unit' => $filters['unit'] ?: null,
    'q'    => $filters['q'] ?: null,
]);
$qs = http_build_query($query);
$withSlip = 0;
foreach ($rows as $row) {
    if (!empty($row['slip_number'] ?? null)) {
        $withSlip++;
    }
}
$maxMonth = 0.0;
foreach ($monthly as $month) {
    $maxMonth = max($maxMonth, (float) $month['pph']);
}
$diffColor = static fn (float $diff): string => $diff > 0 ? 'var(--rose-600)' : ($diff < 0 ? 'var(--teal-700)' : 'var(--muted)');
?>
<div class="page-head">
    <div>
        <h1>Pajak Penghasilan &amp; Bukti Potong</h1>
        <div class="sub">Rekap PPh 21 setahun per karyawan, bukti potong resmi <b>1721-A1</b> ber-QR, dan ekspor CSV siap impor e-SPT.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('tax/export?' . $qs) ?>"><?= icon('download') ?> Export e-SPT (CSV)</a>
        <?php if ($canManage): ?>
            <form method="post" action="<?= url('tax/issue-all') ?>"
                  data-confirm="Terbitkan bukti potong 1721-A1 untuk semua karyawan pada filter ini?">
                <?= csrf_field() ?>
                <input type="hidden" name="year" value="<?= (int) $filters['year'] ?>">
                <input type="hidden" name="unit" value="<?= (int) $filters['unit'] ?>">
                <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
                <button class="btn btn-primary" type="submit" <?= $rows ? '' : 'disabled' ?>><?= icon('receipt') ?> Terbitkan Semua</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Karyawan</span>
        <span class="v"><?= number_id($summary['employees']) ?></span>
        <span class="s">tahun pajak <?= (int) $filters['year'] ?></span>
    </div>
    <div class="mini-stat">
        <span class="k">Bruto Setahun</span>
        <span class="v" style="font-size:15px"><?= rupiah($summary['gross']) ?></span>
        <span class="s">neto <?= rupiah($summary['netto']) ?></span>
    </div>
    <div class="mini-stat">
        <span class="k">PPh 21 Terutang</span>
        <span class="v" style="font-size:15px"><?= rupiah($summary['tax_due']) ?></span>
        <span class="s">PKP <?= rupiah($summary['pkp']) ?></span>
    </div>
    <div class="mini-stat">
        <span class="k">Sudah Dipotong</span>
        <span class="v" style="font-size:15px;color:var(--teal-700)"><?= rupiah($summary['tax_withheld']) ?></span>
        <span class="s">akumulasi payroll <?= (int) $filters['year'] ?></span>
    </div>
    <div class="mini-stat">
        <span class="k"><?= $summary['final'] ? 'Selisih' : 'Selisih Berjalan' ?></span>
        <span class="v" style="font-size:15px;color:<?= $summary['final'] ? $diffColor((float) $summary['tax_diff']) : 'var(--muted)' ?>"><?= $summary['final'] || $summary['tax_diff'] ? rupiah(abs($summary['tax_diff'])) : '—' ?></span>
        <span class="s">
            <?php if ($summary['final']): ?>
                <?= (int) $summary['less'] ?> kurang bayar · <?= (int) $summary['over'] ?> lebih potong
            <?php else: ?>
                tahun pajak belum lengkap · final pada masa Desember
            <?php endif; ?>
        </span>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Rekap Tahunan PPh 21</h2>
                <div class="sub"><?= number_id($summary['employees']) ?> karyawan · <?= number_id($withSlip) ?> bukti potong terbit</div></div>
        </div>

        <form class="filter-bar" method="get" action="<?= url('tax') ?>">
            <select class="select" name="year" data-auto-submit style="min-width:110px">
                <?php foreach ($years as $year): ?>
                    <option value="<?= (int) $year ?>" <?= (int) $filters['year'] === (int) $year ? 'selected' : '' ?>><?= (int) $year ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="unit" data-auto-submit style="min-width:160px">
                <option value="">Semua unit</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= (int) $unit['id'] ?>" <?= (int) $filters['unit'] === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="input-group" style="max-width:210px">
                <?= icon('search') ?>
                <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Nama / NIK / NPWP…">
            </div>
            <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
            <?php if ($filters['unit'] || $filters['q'] !== ''): ?>
                <a class="btn btn-sm btn-ghost" href="<?= url('tax?year=' . (int) $filters['year']) ?>"><?= icon('x') ?> Reset</a>
            <?php endif; ?>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th>Karyawan</th><th>PTKP</th>
                    <th class="num">Bruto</th><th class="num">Neto</th><th class="num">PKP</th>
                    <th class="num">PPh 21</th><th>Bukti Potong</th><th style="width:132px"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $hasSlip = !empty($row['slip_number'] ?? null); ?>
                    <tr>
                        <td>
                            <div class="name-cell">
                                <span class="avatar sm" style="background:#e8f0f7;color:var(--navy-800)"><?= strtoupper(mb_substr((string) $row['employee_name'], 0, 2)) ?></span>
                                <div>
                                    <b><?= e($row['employee_name']) ?></b>
                                    <span class="mono"><?= e($row['employee_code']) ?></span>
                                    <span><?= e($row['unit_name'] ?: '—') ?><?= !empty($row['npwp']) ? ' · NPWP ' . e($row['npwp']) : ' · NPWP belum ada' ?></span>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge badge-slate"><?= e($row['tax_status']) ?></span>
                            <div class="muted" style="font-size:9.6px;margin-top:2px"><?= number_id($row['months']) ?> bulan</div></td>
                        <td class="num small"><?= rupiah($row['gross']) ?></td>
                        <td class="num small"><?= rupiah($row['netto']) ?></td>
                        <td class="num small"><?= rupiah($row['pkp']) ?></td>
                        <td class="num"><b><?= rupiah($row['tax_due']) ?></b>
                            <div class="muted" style="font-size:9.6px">potong <?= rupiah($row['tax_withheld']) ?></div></td>
                        <td>
                            <?php if ($hasSlip): ?>
                                <span class="badge badge-teal"><?= icon('check') ?> Terbit</span>
                                <div class="mono muted" style="font-size:9.6px;margin-top:2px"><?= e($row['slip_number']) ?></div>
                            <?php else: ?>
                                <span class="badge badge-amber">Belum terbit</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex gap-4" style="justify-content:flex-end">
                                <a class="icon-btn" href="<?= url('tax/' . (int) $row['employee_id'] . '?year=' . (int) $filters['year']) ?>" title="Rincian perhitungan"><?= icon('eye') ?></a>
                                <a class="icon-btn" href="<?= url('tax/' . (int) $row['employee_id'] . '/pdf?year=' . (int) $filters['year']) ?>" target="_blank" title="Cetak 1721-A1"><?= icon('printer') ?></a>
                                <?php if ($canManage && !$hasSlip): ?>
                                    <form method="post" action="<?= url('tax/' . (int) $row['employee_id'] . '/issue?year=' . (int) $filters['year']) ?>">
                                        <?= csrf_field() ?>
                                        <button class="icon-btn" type="submit" title="Terbitkan bukti potong"><?= icon('receipt') ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="8">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('calculator') ?></div>
                            <h3>Belum ada payroll final pada tahun <?= (int) $filters['year'] ?></h3>
                            <p>Rekap PPh 21 dibentuk dari periode payroll berstatus <b>Finance Approved</b> atau <b>Paid</b>. Selesaikan payroll terlebih dahulu.</p>
                            <a class="btn btn-sm btn-primary" style="margin-top:10px" href="<?= url('payroll') ?>"><?= icon('wallet') ?> Buka Payroll</a>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
                <?php if ($rows): ?>
                    <tfoot>
                    <tr>
                        <th colspan="2">TOTAL <?= number_id($summary['employees']) ?> karyawan</th>
                        <th class="num"><?= rupiah($summary['gross']) ?></th>
                        <th class="num"><?= rupiah($summary['netto']) ?></th>
                        <th class="num"><?= rupiah($summary['pkp']) ?></th>
                        <th class="num"><?= rupiah($summary['tax_due']) ?></th>
                        <th colspan="2"><?= number_id($issued) ?> bukti potong terbit</th>
                    </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <div class="card__foot small muted" style="padding:10px 16px">
            Perhitungan: bruto − biaya jabatan 5% (maks Rp 6 juta) − iuran pensiun/JHT karyawan = neto; PKP dibulatkan ke bawah ribuan; tarif progresif UU HPP.
        </div>
    </section>

    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head"><div><h2>Setoran per Masa</h2>
                <div class="sub">PPh 21 dipotong tiap bulan.</div></div></div>
            <div class="card__body flex-col gap-10">
                <?php foreach ($monthly as $month): ?>
                    <?php $pct = $maxMonth > 0 ? max(3, (int) round((float) $month['pph'] / $maxMonth * 100)) : 0; ?>
                    <div>
                        <div class="flex-between" style="font-size:11px;margin-bottom:3px">
                            <span><?= e($month['label']) ?></span>
                            <b><?= rupiah($month['pph']) ?></b>
                        </div>
                        <div class="progress thin"><span style="width:<?= $pct ?>%"></span></div>
                        <div class="muted" style="font-size:9.6px;margin-top:2px"><?= number_id($month['employees']) ?> karyawan · bruto <?= rupiah($month['gross']) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$monthly): ?>
                    <div class="empty-state" style="padding:10px 0"><h3>Belum ada data</h3></div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Sebaran PTKP</h2>
                <div class="sub">Status kawin &amp; tanggungan.</div></div></div>
            <div class="list-rows">
                <?php foreach ($byPtkp as $group): ?>
                    <div class="list-row">
                        <div>
                            <b class="mono"><?= e($group['status']) ?></b>
                            <div class="muted" style="font-size:10px"><?= number_id($group['employees']) ?> karyawan · PTKP <?= rupiah($group['ptkp']) ?></div>
                        </div>
                        <div style="text-align:right">
                            <b><?= rupiah($group['tax_due']) ?></b>
                            <div class="muted" style="font-size:9.6px">PPh terutang</div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$byPtkp): ?>
                    <div class="list-row"><span class="muted small">Belum ada karyawan pada filter ini.</span></div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Selisih Pajak</h2>
                <div class="sub">Kurang/lebih potong saat penghitungan setahun.</div></div></div>
            <div class="card__body small" style="line-height:1.65">
                <div class="flex-between"><span>Kurang bayar</span><b style="color:<?= $summary['final'] ? 'var(--rose-600)' : 'var(--muted)' ?>"><?= number_id($summary['less']) ?> karyawan</b></div>
                <div class="flex-between"><span>Lebih potong (dikembalikan)</span><b style="color:<?= $summary['final'] ? 'var(--teal-700)' : 'var(--muted)' ?>"><?= number_id($summary['over']) ?> karyawan</b></div>
                <div class="flex-between"><span>Total selisih</span><b style="color:<?= $summary['final'] ? 'inherit' : 'var(--muted)' ?>"><?= rupiah(abs($summary['tax_diff'])) ?> <span class="muted" style="font-weight:600"><?= (float) $summary['tax_diff'] > 0 ? '(kurang bayar)' : ((float) $summary['tax_diff'] < 0 ? '(lebih potong)' : '') ?></span></b></div>
                <?php if (!$summary['final']): ?>
                    <div class="alert alert-warning" style="margin-top:10px;font-size:10.4px">
                        <?= icon('alert') ?>
                        <span>Angka di atas adalah <b>selisih berjalan</b>, bukan kurang/lebih potong final. Selama tahun pajak belum 12 masa, PPh 21 bulanan masih memakai proyeksi setahun sehingga hasilnya baru dinolkan pada masa Desember.</span>
                    </div>
                <?php endif; ?>
                <p class="muted" style="margin:8px 0 0">
                    Bukti potong yang sudah terbit menyimpan snapshot angka, sehingga revisi payroll berikutnya tidak mengubah dokumen yang telah diserahkan ke karyawan.
                </p>
            </div>
        </section>
    </div>
</div>
