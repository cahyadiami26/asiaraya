<?php
/**
 * Template PDF BAST — Berita Acara Serah Terima Aset & Inventaris.
 * Dirender dompdf tanpa layout aplikasi.
 * @var array $request, $bast, $items, $types, $company
 * @var string $printedAt
 */
$totalUnits = 0;
foreach ($items as $item) {
    $totalUnits += (int) $item['qty'];
}
$pendingNote = false;
foreach ($items as $item) {
    if (in_array($item['status'], ['Belum Kembali'], true)) {
        $pendingNote = true;
    }
}
$fmtDate = static function (?string $value, bool $withTime = false): string {
    if (!$value) {
        return '-';
    }
    $ts = strtotime($value);
    return $ts === false ? (string) $value : date($withTime ? 'd M Y H:i' : 'd M Y', $ts);
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title><?= e($bast['bast_number']) ?></title>
    <style>
        @page { margin: 24px 28px 30px 28px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.8pt; color: #0b2135; margin: 0; line-height: 1.5; }
        .mono { font-family: "DejaVu Sans Mono", monospace; }
        .head { border-bottom: 2px solid #0f766e; padding-bottom: 9px; }
        .head table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .brand { font-size: 13pt; font-weight: bold; letter-spacing: -.2pt; }
        .brand-sub { font-size: 7.4pt; color: #64748b; margin-top: 2px; line-height: 1.45; }
        .doc-title { text-align: right; }
        .doc-title b { display: block; font-size: 9.6pt; letter-spacing: .09em; text-transform: uppercase; color: #0f766e; }
        .doc-title span { font-size: 7.6pt; color: #64748b; display: block; margin-top: 2px; }
        .doc-no { font-size: 9pt; font-weight: bold; margin-top: 5px; }
        h2.section { font-size: 8.4pt; letter-spacing: .08em; text-transform: uppercase; color: #475569; margin: 14px 0 6px; }
        table.meta { width: 100%; border-collapse: collapse; }
        table.meta td { padding: 2.4px 0; font-size: 8.4pt; vertical-align: top; }
        table.meta td.k { color: #64748b; width: 118px; }
        .box { border: 1px solid #e2e8f0; border-radius: 5px; padding: 8px 11px; margin-top: 6px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { width: 50%; vertical-align: top; padding-right: 14px; }
        .grid td:last-child { padding-right: 0; padding-left: 14px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th {
            text-align: left; font-size: 7.2pt; letter-spacing: .06em; text-transform: uppercase;
            color: #475569; border-bottom: 1px solid #cbd5e1; padding: 5px 5px; background: #f8fafc;
        }
        table.items td { padding: 3.6px 5px; border-bottom: 1px solid #eef2f7; font-size: 8.2pt; vertical-align: top; }
        table.items td.num, table.items th.num { text-align: center; width: 40px; }
        table.items td.qty { text-align: center; width: 46px; }
        table.items tr.total td { border-top: 1px solid #0f766e; border-bottom: none; font-weight: bold; background: #f0fdfa; }
        .statement { margin-top: 10px; font-size: 8.5pt; text-align: justify; }
        .note { margin-top: 8px; border-left: 3px solid #b45309; background: #fef6e7; padding: 6px 9px; font-size: 8pt; }
        .sign { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .sign td { width: 33.33%; text-align: center; vertical-align: bottom; padding: 0 8px; }
        .sign .role { font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: .07em; }
        .sign .line { border-top: 1px solid #94a3b8; margin-top: 30px; padding-top: 4px; font-size: 8.2pt; font-weight: bold; }
        .sign .sub { font-size: 7pt; color: #64748b; }
        .foot { margin-top: 10px; border-top: 1px dashed #cbd5e1; padding-top: 6px; font-size: 6.8pt; color: #94a3b8; line-height: 1.5; }
    </style>
</head>
<body>

<div class="head">
    <table>
        <tr>
            <td style="width:58%">
                <div class="brand"><?= e($company['name']) ?></div>
                <div class="brand-sub">
                    <?php if ($company['legal'] && $company['legal'] !== $company['name']): ?><?= e($company['legal']) ?><br><?php endif; ?>
                    <?= e($company['address'] ?: '-') ?><br>
                    <?= e($company['phone'] ?: '-') ?> · <?= e($company['email']) ?>
                </div>
            </td>
            <td class="doc-title">
                <b>Berita Acara Serah Terima</b>
                <span>Aset &amp; Inventaris Perusahaan</span>
                <div class="doc-no mono"><?= e($bast['bast_number']) ?></div>
            </td>
        </tr>
    </table>
</div>

<h2 class="section">A. Karyawan yang Menyerahkan</h2>
<div class="box">
    <table class="grid">
        <tr>
            <td>
                <table class="meta">
                    <tr><td class="k">Nama</td><td><b><?= e($request['full_name']) ?></b></td></tr>
                    <tr><td class="k">NIK</td><td class="mono"><?= e($request['employee_code']) ?></td></tr>
                    <tr><td class="k">Jabatan</td><td><?= e($request['position_name'] ?? '-') ?></td></tr>
                    <tr><td class="k">Departemen</td><td><?= e($request['department_name'] ?? '-') ?></td></tr>
                </table>
            </td>
            <td>
                <table class="meta">
                    <tr><td class="k">Unit kerja</td><td><?= e($request['unit_name'] ?? '-') ?></td></tr>
                    <tr><td class="k">Jenis keluar</td><td><?= e($types[$request['type']] ?? $request['type']) ?></td></tr>
                    <tr><td class="k">Tanggal pengajuan</td><td><?= $fmtDate($request['submit_date']) ?></td></tr>
                    <tr><td class="k">Hari terakhir kerja</td><td><b><?= $fmtDate($request['last_working_date']) ?></b></td></tr>
                </table>
            </td>
        </tr>
    </table>
</div>

<h2 class="section">B. Aset &amp; Inventaris yang Dikembalikan</h2>
<table class="items">
    <thead>
    <tr>
        <th class="num">No</th>
        <th>Kode Aset</th>
        <th>Nama Aset / Inventaris</th>
        <th>Kategori</th>
        <th class="qty">Jumlah</th>
        <th>Kondisi</th>
        <th>Status</th>
        <th>Catatan</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $index => $item): ?>
        <tr>
            <td class="num"><?= $index + 1 ?></td>
            <td class="mono"><?= e($item['code'] !== '' ? $item['code'] : '—') ?></td>
            <td><?= e($item['name']) ?></td>
            <td><?= e($item['category']) ?></td>
            <td class="qty"><?= (int) $item['qty'] ?></td>
            <td><?= e($item['condition']) ?></td>
            <td><?= e($item['status']) ?></td>
            <td><?= e($item['note'] !== '' ? $item['note'] : '—') ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?>
        <tr><td colspan="8">Tidak ada aset yang tercatat.</td></tr>
    <?php endif; ?>
    <tr class="total">
        <td class="num"></td>
        <td colspan="3">Total item</td>
        <td class="qty"><?= (int) $totalUnits ?></td>
        <td colspan="3"><?= count($items) ?> jenis aset/inventaris</td>
    </tr>
    </tbody>
</table>

<div class="statement">
    Pada hari ini, <b><?= $fmtDate($bast['issued_at']) ?></b>, telah dilakukan serah terima pengembalian
    aset dan inventaris perusahaan antara <b><?= e($request['full_name']) ?></b>
    (<?= e($request['employee_code']) ?>) selaku pihak yang menyerahkan dengan
    <b><?= e($bast['receiver_name'] ?: ($company['name'])) ?></b>
    <?= $bast['receiver_position'] ? '(' . e($bast['receiver_position']) . ')' : '' ?>
    selaku pihak yang menerima. Seluruh aset di atas telah diperiksa dan dicatat sesuai kondisi
    sebagaimana tercantum, dan dengan ini dinyatakan bahwa kewajiban pengembalian aset/inventaris
    perusahaan telah diselesaikan.
</div>

<?php if ($pendingNote): ?>
    <div class="note">
        <b>Catatan:</b> terdapat aset yang belum berstatus dikembalikan pada saat BAST diterbitkan.
        Pengembalian/penyelesaian aset tersebut menjadi tanggung jawab HR dan akan dicatat pada dokumen terpisah.
    </div>
<?php endif; ?>

<?php if (!empty($bast['note'])): ?>
    <div class="note" style="border-left-color:#0f766e;background:#f0fdfa">
        <b>Keterangan:</b> <?= e($bast['note']) ?>
    </div>
<?php endif; ?>

<table class="sign">
    <tr>
        <td>
            <div class="role">Yang Menyerahkan</div>
            <div class="line"><?= e($request['full_name']) ?></div>
            <div class="sub"><?= e($request['position_name'] ?? 'Karyawan') ?></div>
        </td>
        <td>
            <div class="role">Yang Menerima</div>
            <div class="line"><?= e($bast['receiver_name'] ?: 'HR Department') ?></div>
            <div class="sub"><?= e($bast['receiver_position'] ?: 'PT Asia Raya Group') ?></div>
        </td>
        <td>
            <div class="role">Saksi</div>
            <div class="line"><?= e($bast['witness_name'] ?: ($request['manager_name'] ?? 'HR Department')) ?></div>
            <div class="sub"><?= $bast['witness_name'] ? 'Saksi' : 'Atasan Langsung' ?></div>
        </td>
    </tr>
</table>

<div class="foot">
    Dokumen ini diterbitkan secara elektronik oleh sistem HRIS <?= e($company['name']) ?> pada
    <?= e($printedAt) ?> WIB · No. pengajuan <?= e($request['request_number']) ?> ·
    Penerbit: <?= e($bast['issued_name'] ?: 'HR Department') ?>.
    Dokumen sah tanpa tanda tangan basah selama tercatat pada sistem.
</div>

</body>
</html>
