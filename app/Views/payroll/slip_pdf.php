<?php
/**
 * Template PDF slip gaji (dirender dompdf, tanpa layout aplikasi).
 * @var array $item, $components, $company
 * @var string $verifyUrl
 * @var string|null $qrPath
 * @var string $printedAt
 */
$benefits = array_values(array_filter($components, static fn ($c) => $c['type'] === 'BENEFIT'));
$deductions = array_values(array_filter($components, static fn ($c) => $c['type'] === 'DEDUCTION'));
$rupiah = static fn (mixed $value): string => 'Rp ' . number_format((float) $value, 0, ',', '.');
$periodLabel = bulan_id((int) $item['period_month']) . ' ' . (int) $item['period_year'];
$rows = max(count($benefits), count($deductions));
$qrSrc = $qrPath ? 'file://' . $qrPath : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Gaji <?= e($item['employee_code']) ?> · <?= e($periodLabel) ?></title>
    <style>
        @page { margin: 26px 30px 34px 30px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.6pt; color: #0b2135; margin: 0; }
        .muted { color: #64748b; }
        .mono { font-family: "DejaVu Sans Mono", monospace; }
        .head { border-bottom: 2px solid #0f766e; padding-bottom: 9px; }
        .head table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .brand { font-size: 13.5pt; font-weight: bold; letter-spacing: -.2pt; color: #0b2135; }
        .brand-sub { font-size: 7.6pt; color: #64748b; margin-top: 2px; line-height: 1.45; }
        .doc-title { text-align: right; }
        .doc-title b { display: block; font-size: 10.5pt; letter-spacing: .10em; text-transform: uppercase; color: #0f766e; }
        .doc-title span { font-size: 7.6pt; color: #64748b; }
        .meta { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .meta td { padding: 3px 0; font-size: 8.2pt; vertical-align: top; }
        .meta .k { color: #64748b; width: 96px; white-space: nowrap; padding-right: 12px; }
        .box { border: 1px solid #e2e8f0; border-radius: 5px; padding: 9px 11px; margin-top: 11px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { width: 50%; vertical-align: top; padding-right: 12px; }
        .grid td:last-child { padding-right: 0; padding-left: 12px; }
        .label { font-size: 7.2pt; letter-spacing: .09em; text-transform: uppercase; color: #94a3b8; font-weight: bold; margin-bottom: 3px; }
        .value { font-size: 9.2pt; font-weight: bold; }
        table.detail { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.detail th {
            text-align: left; font-size: 7.4pt; letter-spacing: .08em; text-transform: uppercase;
            color: #475569; border-bottom: 1px solid #cbd5e1; padding: 5px 6px; background: #f8fafc;
        }
        table.detail th.num, table.detail td.num { text-align: right; }
        table.detail td { padding: 4px 6px; border-bottom: 1px solid #eef2f7; font-size: 8.4pt; }
        table.detail tr.total td { border-top: 1px solid #0f766e; border-bottom: none; font-weight: bold; background: #f0fdfa; }
        .net { margin-top: 10px; border: 1.5px solid #0f766e; border-radius: 5px; background: #f0fdfa; padding: 9px 12px; }
        .net table { width: 100%; border-collapse: collapse; }
        .net .k { font-size: 8.6pt; font-weight: bold; color: #115e59; }
        .net .v { text-align: right; font-size: 13pt; font-weight: bold; color: #115e59; }
        .attendance { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .attendance td { width: 20%; padding: 6px 8px; border: 1px solid #e2e8f0; text-align: center; }
        .attendance .k { font-size: 6.8pt; letter-spacing: .06em; text-transform: uppercase; color: #94a3b8; }
        .attendance .v { font-size: 9pt; font-weight: bold; margin-top: 2px; }
        .sign { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .sign td { width: 33.33%; text-align: center; vertical-align: bottom; padding: 0 6px; }
        .sign .line { border-top: 1px solid #94a3b8; margin-top: 32px; padding-top: 4px; font-size: 8pt; }
        .sign .role { font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: .08em; }
        .qr { text-align: right; }
        .qr img { width: 74px; height: 74px; }
        .qr span { display: block; font-size: 6.4pt; color: #94a3b8; margin-top: 2px; }
        .foot { margin-top: 12px; border-top: 1px dashed #cbd5e1; padding-top: 6px; font-size: 6.9pt; color: #94a3b8; line-height: 1.5; }
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
                    <?= e($company['phone'] ?: '-') ?> · <?= e($company['email']) ?>
                    <?php if ($company['npwp']): ?><br>NPWP <?= e($company['npwp']) ?><?php endif; ?>
                </div>
            </td>
            <td class="doc-title" style="width:38%">
                <b>Slip Gaji</b>
                <span>Periode <?= e($periodLabel) ?></span><br>
                <span class="mono"><?= e($item['slip_number'] ?? '-') ?></span>
            </td>
        </tr>
    </table>
</div>

<table class="meta">
    <tr>
        <td class="k">Tanggal Bayar</td><td><?= tanggal($item['pay_date']) ?></td>
        <td class="k">Unit Kerja</td><td><?= e($item['unit_name'] ?? '-') ?></td>
    </tr>
    <tr>
        <td class="k">NPWP</td><td class="mono"><?= e($item['npwp'] ?: '-') ?></td>
        <td class="k">BPJS Kesehatan</td><td class="mono"><?= e($item['bpjs_kesehatan_no'] ?: '-') ?></td>
    </tr>
    <tr>
        <td class="k">Periode</td><td><?= tanggal($item['start_date']) ?> – <?= tanggal($item['end_date']) ?></td>
        <td class="k">Status</td><td><?= e(\App\Models\PayrollModel::STATUSES[(string) $item['period_status']] ?? $item['period_status']) ?></td>
    </tr>
</table>

<div class="box">
    <table class="grid">
        <tr>
            <td>
                <div class="label">Nama Karyawan</div>
                <div class="value"><?= e($item['employee_name']) ?></div>
                <div class="muted" style="margin-top:3px">NIK <?= e($item['employee_code']) ?></div>
                <div class="muted"><?= e($item['position_name'] ?: '-') ?> · <?= e($item['department_name'] ?: '-') ?></div>
            </td>
            <td>
                <div class="label">Pembayaran</div>
                <div class="value"><?= e($item['bank_name'] ?: 'Transfer bank') ?></div>
                <div class="muted" style="margin-top:3px"><?= e($item['bank_account_number'] ?: '-') ?></div>
                <div class="muted">a.n. <?= e($item['bank_account_name'] ?: $item['employee_name']) ?></div>
            </td>
        </tr>
    </table>
</div>

<table class="detail">
    <thead>
    <tr>
        <th style="width:34%">Komponen</th>
        <th class="num" style="width:16%">Tunjangan</th>
        <th style="width:34%">Komponen Potongan</th>
        <th class="num" style="width:16%">Potongan</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td><b>Gaji Pokok</b></td>
        <td class="num"><b><?= $rupiah($item['basic_salary']) ?></b></td>
        <td class="muted">—</td>
        <td class="num muted">—</td>
    </tr>
    <?php for ($i = 0; $i < $rows; $i++): ?>
        <?php $benefit = $benefits[$i] ?? null; $deduction = $deductions[$i] ?? null; ?>
        <tr>
            <td><?= $benefit ? e($benefit['name']) : '' ?></td>
            <td class="num"><?= $benefit ? $rupiah($benefit['amount']) : '' ?></td>
            <td><?= $deduction ? e($deduction['name']) : '' ?></td>
            <td class="num"><?= $deduction ? $rupiah($deduction['amount']) : '' ?></td>
        </tr>
    <?php endfor; ?>
    <tr class="total">
        <td>Total Bruto</td>
        <td class="num"><?= $rupiah($item['gross_salary']) ?></td>
        <td>Total Potongan</td>
        <td class="num"><?= $rupiah($item['total_deduction']) ?></td>
    </tr>
    </tbody>
</table>

<div class="net">
    <table>
        <tr>
            <td class="k">Gaji Bersih Diterima (Take Home Pay)</td>
            <td class="v"><?= $rupiah($item['net_salary']) ?></td>
        </tr>
    </table>
</div>

<table class="attendance">
    <tr>
        <td><div class="k">Hadir</div><div class="v"><?= (int) $item['present_days'] ?> hari</div></td>
        <td><div class="k">Tidak Hadir</div><div class="v"><?= (int) $item['absent_days'] ?> hari</div></td>
        <td><div class="k">Terlambat</div><div class="v"><?= (int) $item['late_minutes'] ?> menit</div></td>
        <td><div class="k">Lembur</div><div class="v"><?= number_id((float) $item['overtime_hours'], 1) ?> jam</div></td>
        <td><div class="k">Cuti Tanpa Upah</div><div class="v"><?= number_id((float) $item['unpaid_leave_days'], 1) ?> hari</div></td>
    </tr>
</table>

<table class="sign">
    <tr>
        <td>
            <div class="role">Dibuat oleh</div>
            <div class="line"><?= e($item['hr_signature_name'] ?? '') ?: 'HR Department' ?></div>
        </td>
        <td>
            <div class="role">Disetujui oleh</div>
            <div class="line"><?= e($item['fin_signature_name'] ?? '') ?: 'Finance Division' ?></div>
        </td>
        <td class="qr">
            <?php if ($qrSrc): ?>
                <img src="<?= e($qrSrc) ?>" alt="QR verifikasi">
                <span>Scan untuk verifikasi</span>
            <?php else: ?>
                <span class="mono"><?= e($item['slip_number'] ?? '') ?></span>
                <span>Verifikasi: <?= e($verifyUrl) ?></span>
            <?php endif; ?>
        </td>
    </tr>
</table>

<div class="foot">
    Dokumen ini dibuat otomatis oleh HRIS <?= e($company['name']) ?> pada <?= e($printedAt) ?> dan sah tanpa tanda tangan basah.
    Verifikasi keaslian: <span class="mono"><?= e($verifyUrl) ?></span>.
    Slip gaji bersifat rahasia — dilarang menyebarkan tanpa izin.
</div>

</body>
</html>
