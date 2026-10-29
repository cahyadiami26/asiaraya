<?php
/**
 * Template PDF laporan (dompdf, A4 landscape).
 * @var string $type, $title, $subtitle, $printedAt, $printedBy
 * @var array $header, $rows, $signers, $company
 * @var int $total
 */
$moneyCols = [];
$percentCols = [];
foreach ($header as $index => $label) {
    if (str_contains($label, '(Rp)')) {
        $moneyCols[$index] = true;
    }
    if (str_contains($label, '(%)')) {
        $percentCols[$index] = true;
    }
}
$fmt = static function ($value, int $index) use ($moneyCols, $percentCols): string {
    if ($value === null || $value === '') {
        return '';
    }
    if (isset($moneyCols[$index])) {
        return number_format((float) $value, 0, ',', '.');
    }
    if (isset($percentCols[$index])) {
        return number_format((float) $value, 1, ',', '.');
    }
    if (is_float($value)) {
        return number_format($value, 1, ',', '.');
    }
    if (is_int($value)) {
        return number_format($value, 0, ',', '.');
    }
    return (string) $value;
};
$colCount = max(1, count($header));
$isTotalRow = static function (array $row): bool {
    foreach ($row as $cell) {
        if (is_string($cell) && strtoupper($cell) === 'TOTAL') {
            return true;
        }
    }
    return false;
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title><?= e($title) ?> — <?= e($company['name']) ?></title>
    <style>
        @page { margin: 24px 26px 30px 26px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8pt; color: #0b2135; margin: 0; }
        .muted { color: #64748b; }
        .head { border-bottom: 2px solid #0f766e; padding-bottom: 8px; }
        .head table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .brand { font-size: 12.5pt; font-weight: bold; color: #0b2135; }
        .brand-sub { font-size: 7pt; color: #64748b; margin-top: 2px; line-height: 1.45; }
        .doc { text-align: right; }
        .doc b { display: block; font-size: 10pt; letter-spacing: .09em; text-transform: uppercase; color: #0f766e; }
        .doc span { font-size: 7.4pt; color: #64748b; }
        .meta { margin-top: 8px; font-size: 7.4pt; color: #475569; }
        .meta b { color: #0b2135; }
        table.report { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.report thead th {
            background: #f1f5f9; border-bottom: 1px solid #cbd5e1; border-top: 1px solid #cbd5e1;
            padding: 5px 5px; font-size: 6.9pt; text-transform: uppercase; letter-spacing: .05em;
            color: #334155; text-align: left;
        }
        table.report thead th.num { text-align: right; }
        table.report tbody td { padding: 4px 5px; border-bottom: 1px solid #eef2f7; font-size: 7.4pt; }
        table.report tbody td.num { text-align: right; }
        table.report tbody tr.total td { font-weight: bold; background: #f0fdfa; border-top: 1px solid #0f766e; }
        .foot { margin-top: 12px; border-top: 1px dashed #cbd5e1; padding-top: 6px; font-size: 6.6pt; color: #94a3b8; line-height: 1.5; }
        .sign { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .sign td { width: 50%; text-align: center; font-size: 7.4pt; }
        .sign .role { color: #94a3b8; font-size: 6.6pt; text-transform: uppercase; letter-spacing: .07em; margin-top: 3px; }
        .sign .name { height: 42px; display: block; padding-top: 26px; font-weight: bold; }
        .sign .line { border-top: 1px solid #94a3b8; margin: 0 40px; }
    </style>
</head>
<body>

<div class="head">
    <table>
        <tr>
            <td style="width:65%">
                <div class="brand"><?= e($company['name']) ?></div>
                <div class="brand-sub">
                    <?php if ($company['legal'] && $company['legal'] !== $company['name']): ?><?= e($company['legal']) ?><br><?php endif; ?>
                    <?= e($company['address'] ?: '-') ?><br>
                    <?= e($company['phone'] ?: '-') ?> · <?= e($company['email']) ?><?php if ($company['npwp']): ?> · NPWP <?= e($company['npwp']) ?><?php endif; ?>
                </div>
            </td>
            <td class="doc" style="width:35%">
                <b><?= e($title) ?></b>
                <span><?= e($subtitle) ?></span>
            </td>
        </tr>
    </table>
</div>

<div class="meta">
    Dicetak <b><?= e($printedAt) ?></b> oleh <b><?= e($printedBy ?: 'HR Department') ?></b>
    · <?= number_id($total) ?> baris data<?= $total > count($rows) ? ' (menampilkan ' . number_id(count($rows)) . ' baris pertama)' : '' ?>
</div>

<table class="report">
    <thead>
    <tr>
        <?php foreach ($header as $label): ?>
            <th class="<?= str_contains($label, '(Rp)') || str_contains($label, '(%)') || in_array($label, ['Hadir', 'Telat', 'Absen', 'Cuti', 'Jam', 'Jam Kerja', 'Ajuan', 'Disetujui', 'Jatah', 'Carry', 'Terpakai', 'Sisa', 'Menunggu', 'Pengajuan', 'Karyawan', 'Bruto', 'Potongan', 'Net'], true) ? 'num' : '' ?>">
                <?= e($label) ?>
            </th>
        <?php endforeach; ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <tr class="<?= $isTotalRow($row) ? 'total' : '' ?>">
            <?php foreach (array_values($row) as $index => $cell): ?>
                <td class="<?= isset($moneyCols[$index]) || isset($percentCols[$index]) || is_int($cell) || is_float($cell) ? 'num' : '' ?>">
                    <?= e($fmt($cell, $index)) ?>
                </td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
        <tr><td colspan="<?= $colCount ?>" style="text-align:center;padding:14px;color:#94a3b8">Tidak ada data pada filter ini.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<table class="sign">
    <tr>
        <td>
            <span class="name"><?= e($signers['hr']) ?></span>
            <div class="line"></div>
            <div class="role">Disusun oleh — HR</div>
        </td>
        <td>
            <span class="name"><?= e($signers['fin']) ?></span>
            <div class="line"></div>
            <div class="role">Disetujui oleh — Finance</div>
        </td>
    </tr>
</table>

<div class="foot">
    Laporan ini dihasilkan otomatis oleh HRIS <?= e($company['name']) ?> dari data operasional terkini
    (presensi, lembur, cuti, dan payroll). Angka dapat berubah bila terdapat koreksi data setelah tanggal cetak.
</div>

</body>
</html>
