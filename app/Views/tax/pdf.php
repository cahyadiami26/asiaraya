<?php
/**
 * Template PDF bukti potong 1721-A1 (dompdf, tanpa layout aplikasi) — Batch 20.
 * @var array $detail, $company, $signers
 * @var array|null $slip
 * @var int $year
 * @var string $verifyUrl, $printedAt, $printedBy
 * @var string|null $qrPath
 */
$employee = $detail['employee'];
$calc = $detail['computed'];
$breakdown = $detail['breakdown'];
$months = $detail['months'];
$rp = static fn (mixed $value): string => 'Rp ' . number_format((float) $value, 0, ',', '.');
$qrSrc = $qrPath ? 'file://' . $qrPath : null;
$nomor = (string) ($slip['slip_number'] ?? '1.1-' . $year . '-DRAFT');
$issuedAt = $slip ? tanggal((string) $slip['issued_at']) : tanggal(date('Y-m-d'));
$diff = (float) $calc['tax_diff'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bukti Potong 1721-A1 · <?= e($employee['full_name']) ?> · <?= (int) $year ?></title>
    <style>
        @page { margin: 26px 32px 30px 32px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.4pt; color: #0b2135; margin: 0; }
        .muted { color: #64748b; }
        .mono { font-family: "DejaVu Sans Mono", monospace; }
        .head { border-bottom: 2px solid #0f766e; padding-bottom: 8px; }
        .head table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .brand { font-size: 13pt; font-weight: bold; color: #0b2135; }
        .brand-sub { font-size: 7.2pt; color: #64748b; margin-top: 2px; line-height: 1.5; }
        .doc-title { text-align: right; }
        .doc-title b { display: block; font-size: 10.4pt; letter-spacing: .08em; text-transform: uppercase; color: #0f766e; }
        .doc-title span { font-size: 7.2pt; color: #64748b; }
        .form-no { margin-top: 6px; font-size: 7.2pt; color: #475569; }
        .banner { margin-top: 10px; border: 1px solid #e2e8f0; border-left: 3px solid #0f766e; background: #f8fafc; padding: 7px 10px; font-size: 7.4pt; line-height: 1.55; }
        .section { margin-top: 11px; }
        .section-title { font-size: 7.2pt; font-weight: bold; letter-spacing: .10em; text-transform: uppercase; color: #0f766e; margin-bottom: 4px; }
        table.kv { width: 100%; border-collapse: collapse; }
        table.kv td { padding: 3px 0; vertical-align: top; font-size: 8pt; }
        table.kv td.k { color: #64748b; width: 92px; padding-right: 10px; }
        table.kv td.v { font-weight: bold; }
        table.grid2 { width: 100%; border-collapse: collapse; }
        table.grid2 > tbody > tr > td { width: 50%; vertical-align: top; padding-right: 14px; }
        table.grid2 > tbody > tr > td:last-child { padding-right: 0; padding-left: 14px; border-left: 1px solid #e2e8f0; }
        table.detail { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.detail th { text-align: left; font-size: 6.8pt; letter-spacing: .06em; text-transform: uppercase; color: #475569; border-bottom: 1px solid #cbd5e1; padding: 4px 5px; background: #f8fafc; }
        table.detail th.num, table.detail td.num { text-align: right; }
        table.detail td { padding: 3.4px 5px; border-bottom: 1px solid #eef2f7; font-size: 8pt; }
        table.detail tr.sum td { border-top: 1px solid #cbd5e1; border-bottom: none; font-weight: bold; background: #f8fafc; }
        table.detail tr.grand td { border-top: 1.5px solid #0f766e; border-bottom: none; font-weight: bold; background: #f0fdfa; color: #115e59; font-size: 9pt; }
        .total-box { margin-top: 10px; border: 1.5px solid #0f766e; border-radius: 5px; background: #f0fdfa; padding: 8px 12px; }
        .total-box table { width: 100%; border-collapse: collapse; }
        .total-box .k { font-size: 8.4pt; font-weight: bold; color: #115e59; }
        .total-box .v { text-align: right; font-size: 12.4pt; font-weight: bold; color: #115e59; }
        .note { margin-top: 9px; font-size: 6.8pt; color: #64748b; line-height: 1.55; }
        .sign { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .sign td { width: 33.33%; text-align: center; font-size: 7.2pt; vertical-align: bottom; }
        .sign .role { color: #94a3b8; font-size: 6.4pt; text-transform: uppercase; letter-spacing: .06em; }
        .sign .name { display: block; padding-top: 26px; font-weight: bold; }
        .sign .line { border-top: 1px solid #94a3b8; margin: 0 26px; }
        .qr { text-align: center; }
        .qr img { width: 62px; height: 62px; }
        .qr small { display: block; font-size: 5.8pt; color: #94a3b8; margin-top: 2px; }
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
            <td class="doc-title" style="width:38%">
                <b>Bukti Pemotongan PPh 21</b>
                <span>Formulir 1721-A1 · Tahun Pajak <?= (int) $year ?></span>
                <div class="form-no">No. <span class="mono"><?= e($nomor) ?></span> · Terbit <?= e($issuedAt) ?></div>
            </td>
        </tr>
    </table>
</div>

<div class="banner">
    Bukti pemotongan Pajak Penghasilan Pasal 21 atas penghasilan yang diterima pegawai tetap dalam satu tahun pajak.
    Dokumen ini sah tanpa tanda tangan basah dan dapat diverifikasi melalui QR di kanan bawah.
    <?php if (!$slip): ?><b>Status: DRAF — belum diterbitkan resmi.</b><?php endif; ?>
</div>

<div class="section">
    <div class="section-title">A. Identitas Penerima Penghasilan</div>
    <table class="grid2">
        <tr>
            <td>
                <table class="kv">
                    <tr><td class="k">Nama</td><td class="v"><?= e($employee['full_name']) ?></td></tr>
                    <tr><td class="k">NIK / Kode</td><td class="v mono"><?= e($employee['employee_code']) ?></td></tr>
                    <tr><td class="k">NPWP</td><td class="v mono"><?= e($calc['npwp'] ?: '—') ?></td></tr>
                    <tr><td class="k">Status PTKP</td><td class="v"><?= e($calc['tax_status']) ?></td></tr>
                </table>
            </td>
            <td>
                <table class="kv">
                    <tr><td class="k">Jabatan</td><td class="v"><?= e($calc['position_name'] ?: '—') ?></td></tr>
                    <tr><td class="k">Unit / Dept.</td><td class="v"><?= e($calc['unit_name'] ?: '—') ?> / <?= e($calc['department_name'] ?: '—') ?></td></tr>
                    <tr><td class="k">Masa Kerja</td><td class="v"><?= number_id($calc['months']) ?> bulan dalam tahun ini</td></tr>
                    <tr><td class="k">Slip Payroll</td><td class="v"><?= number_id(count($months)) ?> slip</td></tr>
                </table>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">B. Rincian Penghasilan Bruto</div>
    <table class="detail">
        <thead><tr><th>Komponen Penghasilan</th><th class="num">Jumlah (Rp)</th></tr></thead>
        <tbody>
        <tr><td>Gaji pokok</td><td class="num"><?= $rp($calc['basic_total']) ?></td></tr>
        <tr><td>Tunjangan tetap (komponen gaji)</td><td class="num"><?= $rp($breakdown['tunjangan']) ?></td></tr>
        <tr><td>Upah lembur</td><td class="num"><?= $rp($breakdown['lembur']) ?></td></tr>
        <tr><td>THR / bonus tahunan</td><td class="num"><?= $rp($breakdown['thr']) ?></td></tr>
        <tr><td>Tunjangan lain</td><td class="num"><?= $rp($breakdown['lain']) ?></td></tr>
        <tr class="sum"><td>Jumlah penghasilan bruto</td><td class="num"><?= $rp($calc['gross']) ?></td></tr>
        </tbody>
    </table>
</div>

<div class="section">
    <div class="section-title">C. Pengurang &amp; Penghasilan Neto</div>
    <table class="detail">
        <tbody>
        <tr><td>Biaya jabatan 5% (maksimum Rp 6.000.000)</td><td class="num">(<?= $rp($calc['biaya_jabatan']) ?>)</td></tr>
        <tr><td>Iuran pensiun / JHT bagian karyawan</td><td class="num">(<?= $rp($calc['pension']) ?>)</td></tr>
        <tr class="sum"><td>Penghasilan neto setahun</td><td class="num"><?= $rp($calc['netto']) ?></td></tr>
        <tr><td>PTKP (<?= e($calc['tax_status']) ?>)</td><td class="num">(<?= $rp($calc['ptkp']) ?>)</td></tr>
        <tr class="sum"><td>Penghasilan Kena Pajak (dibulatkan)</td><td class="num"><?= $rp($calc['pkp']) ?></td></tr>
        </tbody>
    </table>
</div>

<div class="section">
    <div class="section-title">D. Perhitungan PPh 21 Terutang</div>
    <table class="detail">
        <thead>
        <tr><th>Lapisan Penghasilan Kena Pajak</th><th class="num">Tarif</th><th class="num">Dasar (Rp)</th><th class="num">Pajak (Rp)</th></tr>
        </thead>
        <tbody>
        <?php foreach ($calc['layers'] as $layer): ?>
            <tr>
                <td><?= e($layer['range']) ?></td>
                <td class="num"><?= (int) round((float) $layer['rate']) ?>%</td>
                <td class="num"><?= $rp($layer['base']) ?></td>
                <td class="num"><?= $rp($layer['tax']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$calc['layers']): ?>
            <tr><td colspan="4" class="muted">PKP nihil — tidak ada PPh 21 terutang.</td></tr>
        <?php endif; ?>
        <tr class="sum"><td colspan="3">PPh 21 terutang setahun</td><td class="num"><?= $rp($calc['tax_due']) ?></td></tr>
        <tr><td colspan="3">PPh 21 sudah dipotong melalui payroll</td><td class="num">(<?= $rp($calc['tax_withheld']) ?>)</td></tr>
        <tr class="grand">
            <td colspan="3"><?= $diff > 0 ? 'PPh 21 kurang dipotong (Pasal 21)' : ($diff < 0 ? 'PPh 21 lebih dipotong (dikembalikan)' : 'PPh 21 sesuai') ?></td>
            <td class="num"><?= $rp(abs($diff)) ?></td>
        </tr>
        </tbody>
    </table>
</div>

<div class="total-box">
    <table>
        <tr>
            <td class="k">PPh 21 terutang tahun pajak <?= (int) $year ?></td>
            <td class="v"><?= $rp($calc['tax_due']) ?></td>
        </tr>
    </table>
</div>

<div class="note">
    Perhitungan mengikuti Pasal 17 UU PPh sebagaimana diubah UU HPP: tarif 5% sampai Rp 60 juta, 15% Rp 60–250 juta,
    25% Rp 250–500 juta, 30% Rp 500 juta–5 miliar, dan 35% di atas Rp 5 miliar. Biaya jabatan 5% dibatasi Rp 6.000.000/tahun.
    Dokumen dibentuk dari slip payroll periode final dan disimpan sebagai snapshot saat diterbitkan.
    Dicetak <?= e($printedAt) ?> oleh <?= e($printedBy ?: 'HR Department') ?>.
</div>

<table class="sign">
    <tr>
        <td>
            <div class="role">Penerima Penghasilan</div>
            <span class="name"><?= e($employee['full_name']) ?></span>
            <div class="line"></div>
        </td>
        <td>
            <div class="role">Pemotong PPh 21</div>
            <span class="name"><?= e($signers['fin'] ?? 'Finance Division') ?></span>
            <div class="line"></div>
        </td>
        <td>
            <div class="qr">
                <?php if ($qrSrc): ?><img src="<?= e($qrSrc) ?>" alt="QR verifikasi"><?php endif; ?>
                <small>Verifikasi: <?= e($verifyUrl) ?></small>
            </div>
        </td>
    </tr>
</table>

</body>
</html>
