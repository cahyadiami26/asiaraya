<?php
/**
 * PDF rekap BPJS (landscape) — Batch 20.
 * @var array $rows, $summary, $programs, $filters, $company, $signers
 * @var string $period, $subtitle, $printedAt, $printedBy
 */
$rp = static fn (mixed $value): string => number_format((float) $value, 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap BPJS · <?= e($period) ?></title>
    <style>
        @page { margin: 22px 24px 26px 24px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 7.4pt; color: #0b2135; margin: 0; }
        .muted { color: #64748b; }
        .mono { font-family: "DejaVu Sans Mono", monospace; }
        .head { border-bottom: 2px solid #0f766e; padding-bottom: 7px; }
        .head table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .brand { font-size: 12.4pt; font-weight: bold; }
        .brand-sub { font-size: 6.9pt; color: #64748b; margin-top: 2px; line-height: 1.45; }
        .doc { text-align: right; }
        .doc b { display: block; font-size: 10pt; letter-spacing: .09em; text-transform: uppercase; color: #0f766e; }
        .doc span { font-size: 7pt; color: #64748b; }
        .meta { margin-top: 6px; font-size: 7pt; color: #475569; }
        .cards { width: 100%; border-collapse: collapse; margin-top: 9px; }
        .cards td { width: 20%; border: 1px solid #e2e8f0; padding: 5px 7px; }
        .cards .k { font-size: 6.2pt; letter-spacing: .06em; text-transform: uppercase; color: #94a3b8; }
        .cards .v { font-size: 9pt; font-weight: bold; margin-top: 1px; }
        table.report { width: 100%; border-collapse: collapse; margin-top: 9px; }
        table.report thead th {
            background: #f1f5f9; border-top: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1;
            padding: 4px 4px; font-size: 6.3pt; text-transform: uppercase; letter-spacing: .04em;
            color: #334155; text-align: left;
        }
        table.report thead th.num { text-align: right; }
        table.report tbody td { padding: 3.2px 4px; border-bottom: 1px solid #eef2f7; font-size: 7pt; }
        table.report tbody td.num { text-align: right; }
        table.report tbody tr.total td { font-weight: bold; background: #f0fdfa; border-top: 1px solid #0f766e; font-size: 7.4pt; }
        table.report tbody tr:nth-child(even) td { background: #fcfdff; }
        .section-title { font-size: 6.8pt; font-weight: bold; letter-spacing: .10em; text-transform: uppercase; color: #0f766e; margin: 10px 0 0; }
        .foot { margin-top: 10px; border-top: 1px dashed #cbd5e1; padding-top: 5px; font-size: 6.4pt; color: #94a3b8; line-height: 1.5; }
        .sign { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .sign td { width: 33.33%; text-align: center; font-size: 7pt; vertical-align: bottom; }
        .sign .role { color: #94a3b8; font-size: 6.2pt; text-transform: uppercase; letter-spacing: .07em; }
        .sign .name { display: block; padding-top: 24px; font-weight: bold; }
        .sign .line { border-top: 1px solid #94a3b8; margin: 0 30px; }
    </style>
</head>
<body>

<div class="head">
    <table>
        <tr>
            <td style="width:62%">
                <div class="brand"><?= e($company['name']) ?></div>
                <div class="brand-sub">
                    <?php if ($company['legal'] && $company['legal'] !== $company['name']): ?><?= e($company['legal']) ?><br><?php endif; ?>
                    <?= e($company['address'] ?: '-') ?><br>
                    <?= e($company['phone'] ?: '-') ?> · <?= e($company['email']) ?><?php if ($company['npwp']): ?> · NPWP <?= e($company['npwp']) ?><?php endif; ?>
                </div>
            </td>
            <td class="doc" style="width:38%">
                <b>Rekap Iuran BPJS</b>
                <span>Kesehatan &amp; Ketenagakerjaan · <?= e($period) ?></span>
            </td>
        </tr>
    </table>
</div>

<div class="meta">
    <?= e($subtitle) ?> · Dicetak <b><?= e($printedAt) ?></b> oleh <b><?= e($printedBy ?: 'HR Department') ?></b>
    · <?= number_id(count($rows)) ?> peserta
</div>

<table class="cards">
    <tr>
        <td><div class="k">Peserta</div><div class="v"><?= number_id($summary['employees']) ?></div></td>
        <td><div class="k">Total Upah</div><div class="v">Rp <?= $rp($summary['wage']) ?></div></td>
        <td><div class="k">Iuran Perusahaan</div><div class="v">Rp <?= $rp($summary['company_total']) ?></div></td>
        <td><div class="k">Iuran Karyawan</div><div class="v">Rp <?= $rp($summary['employee_total']) ?></div></td>
        <td><div class="k">Grand Total</div><div class="v">Rp <?= $rp($summary['grand_total']) ?></div></td>
    </tr>
</table>

<div class="section-title">Rekap per Program</div>
<table class="report">
    <thead>
    <tr>
        <th>Program</th><th class="num">Peserta</th>
        <th class="num">Perusahaan (Rp)</th><th class="num">Karyawan (Rp)</th><th class="num">Total (Rp)</th>
        <th>Dasar Perhitungan</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($summary['programs'] as $program): ?>
        <tr>
            <td><b><?= e($program['label']) ?></b></td>
            <td class="num"><?= number_id($program['participants']) ?></td>
            <td class="num"><?= $rp($program['company']) ?></td>
            <td class="num"><?= $rp($program['employee']) ?></td>
            <td class="num"><b><?= $rp($program['total']) ?></b></td>
            <td class="muted"><?= e($program['note']) ?></td>
        </tr>
    <?php endforeach; ?>
    <tr class="total">
        <td>TOTAL</td>
        <td class="num"><?= number_id($summary['employees']) ?></td>
        <td class="num"><?= $rp($summary['company_total']) ?></td>
        <td class="num"><?= $rp($summary['employee_total']) ?></td>
        <td class="num"><?= $rp($summary['grand_total']) ?></td>
        <td></td>
    </tr>
    </tbody>
</table>

<div class="section-title">Rincian per Peserta</div>
<table class="report">
    <thead>
    <tr>
        <th>No</th><th>NIK</th><th>Nama</th><th>Unit</th><th>No. Peserta KES</th><th>No. Peserta TK</th>
        <th class="num">Upah</th>
        <th class="num">KES</th><th class="num">JHT</th><th class="num">JP</th><th class="num">JKK</th><th class="num">JKM</th>
        <th class="num">Perusahaan</th><th class="num">Karyawan</th><th class="num">Total</th>
    </tr>
    </thead>
    <tbody>
    <?php $no = 0; foreach ($rows as $row): $no++; ?>
        <tr>
            <td><?= $no ?></td>
            <td class="mono"><?= e($row['employee_code']) ?></td>
            <td><b><?= e($row['employee_name']) ?></b></td>
            <td><?= e($row['unit_name'] ?: '-') ?></td>
            <td class="mono"><?= e($row['kes_no'] ?: '-') ?></td>
            <td class="mono"><?= e($row['tk_no'] ?: '-') ?></td>
            <td class="num"><?= $rp($row['wage']) ?></td>
            <td class="num"><?= $rp((float) $row['KES_ER'] + (float) $row['KES_EE']) ?></td>
            <td class="num"><?= $rp((float) $row['JHT_ER'] + (float) $row['JHT_EE']) ?></td>
            <td class="num"><?= $rp((float) $row['JP_ER'] + (float) $row['JP_EE']) ?></td>
            <td class="num"><?= $rp($row['JKK_ER']) ?></td>
            <td class="num"><?= $rp($row['JKM_ER']) ?></td>
            <td class="num"><?= $rp($row['company_total']) ?></td>
            <td class="num"><?= $rp($row['employee_total']) ?></td>
            <td class="num"><b><?= $rp($row['grand_total']) ?></b></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
        <tr><td colspan="15" class="muted">Tidak ada iuran BPJS pada periode ini.</td></tr>
    <?php endif; ?>
    <?php if ($rows): ?>
        <tr class="total">
            <td colspan="6">TOTAL <?= number_id(count($rows)) ?> peserta</td>
            <td class="num"><?= $rp($summary['wage']) ?></td>
            <td class="num" colspan="5"></td>
            <td class="num"><?= $rp($summary['company_total']) ?></td>
            <td class="num"><?= $rp($summary['employee_total']) ?></td>
            <td class="num"><?= $rp($summary['grand_total']) ?></td>
        </tr>
    <?php endif; ?>
    </tbody>
</table>

<div class="foot">
    Angka diambil dari komponen payroll berkode BPJS pada periode berstatus Finance Approved/Paid. Porsi karyawan dipotong dari gaji;
    porsi perusahaan merupakan tambahan beban di luar gaji bersih. Batas upah: Kesehatan Rp 12.000.000, Jaminan Pensiun Rp 10.000.000.
</div>

<table class="sign">
    <tr>
        <td>
            <div class="role">Disusun oleh</div>
            <span class="name"><?= e($signers['hr'] ?? 'HR Department') ?></span>
            <div class="line"></div>
        </td>
        <td>
            <div class="role">Diperiksa</div>
            <span class="name">HR Department</span>
            <div class="line"></div>
        </td>
        <td>
            <div class="role">Disetujui</div>
            <span class="name"><?= e($signers['fin'] ?? 'Finance Division') ?></span>
            <div class="line"></div>
        </td>
    </tr>
</table>

</body>
</html>
