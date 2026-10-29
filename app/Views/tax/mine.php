<?php
/**
 * Bukti potong pajak milik karyawan — Batch 20.
 * @var array $slips, $years
 * @var int $employeeId
 */
$totalTax = 0.0;
foreach ($slips as $slip) {
    $totalTax += (float) $slip['tax_due'];
}
$latest = $slips[0] ?? null;
?>
<div class="page-head">
    <div>
        <h1>Bukti Potong Pajak Saya</h1>
        <div class="sub">Dokumen 1721-A1 resmi sebagai kredit pajak saat melaporkan SPT Tahunan.</div>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Bukti Potong</span>
        <span class="v"><?= number_id(count($slips)) ?></span>
        <span class="s">dari <?= number_id(count($years)) ?> tahun pajak tersedia</span>
    </div>
    <div class="mini-stat">
        <span class="k">PPh Terakhir</span>
        <span class="v" style="font-size:15px"><?= $latest ? rupiah($latest['tax_due']) : '—' ?></span>
        <span class="s"><?= $latest ? 'tahun pajak ' . (int) $latest['tax_year'] : 'belum ada dokumen' ?></span>
    </div>
    <div class="mini-stat">
        <span class="k">Akumulasi PPh</span>
        <span class="v" style="font-size:15px"><?= rupiah($totalTax) ?></span>
        <span class="s">seluruh bukti potong terbit</span>
    </div>
</div>

<section class="card">
    <div class="card__head">
        <div><h2>Dokumen Tersedia</h2>
            <div class="sub">Unduh dan lampirkan pada pelaporan SPT Tahunan Anda.</div></div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th>Tahun Pajak</th><th>Nomor</th><th class="num">Bruto</th>
                <th class="num">PPh 21</th><th>Terbit</th><th style="width:110px"></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($slips as $slip): ?>
                <tr>
                    <td><b><?= (int) $slip['tax_year'] ?></b>
                        <div class="muted" style="font-size:10px"><?= number_id($slip['months_count']) ?> bulan</div></td>
                    <td class="mono small"><?= e($slip['slip_number']) ?></td>
                    <td class="num small"><?= rupiah($slip['gross']) ?></td>
                    <td class="num"><b><?= rupiah($slip['tax_due']) ?></b></td>
                    <td class="small"><?= tanggal($slip['issued_at']) ?></td>
                    <td>
                        <div class="flex gap-4" style="justify-content:flex-end">
                            <a class="icon-btn" href="<?= url('tax/' . (int) $employeeId . '/pdf?year=' . (int) $slip['tax_year']) ?>" target="_blank" title="Pratinjau"><?= icon('eye') ?></a>
                            <a class="icon-btn" href="<?= url('tax/' . (int) $employeeId . '/pdf?year=' . (int) $slip['tax_year'] . '&download=1') ?>" title="Unduh PDF"><?= icon('download') ?></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$slips): ?>
                <tr><td colspan="6">
                    <div class="empty-state">
                        <div class="empty-icon"><?= icon('receipt') ?></div>
                        <h3>Belum ada bukti potong</h3>
                        <p>HR/Finance menerbitkan bukti potong 1721-A1 setelah payroll Desember atau saat Anda berhenti bekerja.
                            Dokumen akan muncul di sini dan dapat diunduh kapan saja.</p>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card__foot small muted" style="padding:10px 16px">
        Butuh rekap slip gaji bulanan? Buka menu <a href="<?= url('me/payslip') ?>">Slip Gaji</a>.
    </div>
</section>
