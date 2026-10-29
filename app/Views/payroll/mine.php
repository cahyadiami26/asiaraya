<?php
/**
 * Slip gaji milik karyawan yang login.
 * @var array $slips, $totals
 * @var string $verifyBase
 */
$latest = $slips[0] ?? null;
?>
<div class="page-head">
    <div>
        <h1>Slip Gaji Saya</h1>
        <div class="sub">Riwayat slip gaji yang telah disetujui Finance. Unduh PDF ber-QR untuk keperluan resmi.</div>
    </div>
    <div class="page-head__actions">
        <?php if ($latest): ?>
            <a class="btn btn-primary" href="<?= url('payroll/items/' . $latest['id'] . '/pdf?download=1') ?>"><?= icon('download') ?> Unduh Slip Terakhir</a>
        <?php endif; ?>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Slip Tersedia</span>
        <span class="v"><?= number_id($totals['count']) ?></span>
        <span class="s"><?= number_id($totals['paid']) ?> sudah dibayar</span>
    </div>
    <div class="mini-stat">
        <span class="k">Total Net Diterima</span>
        <span class="v" style="font-size:16px"><?= rupiah($totals['net']) ?></span>
        <span class="s">akumulasi slip terdaftar</span>
    </div>
    <div class="mini-stat">
        <span class="k">Slip Terakhir</span>
        <span class="v" style="font-size:14px"><?= $latest ? e(bulan_id((int) $latest['period_month'])) . ' ' . (int) $latest['period_year'] : '—' ?></span>
        <span class="s"><?= $latest ? rupiah($latest['net_salary']) : 'Belum ada slip' ?></span>
    </div>
</div>

<section class="card">
    <div class="card__head">
        <div><h2>Riwayat Slip</h2>
            <div class="sub">Ditampilkan setelah payroll disetujui Finance.</div></div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th>Periode</th><th>No. Slip</th><th class="num">Bruto</th>
                <th class="num">Potongan</th><th class="num">Net</th><th>Status</th><th style="width:96px">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($slips as $slip): ?>
                <tr>
                    <td>
                        <b><?= e(bulan_id((int) $slip['period_month'])) ?> <?= (int) $slip['period_year'] ?></b>
                        <div class="muted" style="font-size:10px">bayar <?= tanggal($slip['pay_date']) ?></div>
                    </td>
                    <td class="mono small"><?= e($slip['slip_number']) ?></td>
                    <td class="num small"><?= rupiah($slip['gross_salary']) ?></td>
                    <td class="num small" style="color:var(--rose-600)"><?= rupiah($slip['total_deduction']) ?></td>
                    <td class="num"><b><?= rupiah($slip['net_salary']) ?></b></td>
                    <td><?= status_badge($slip['payment_status']) ?></td>
                    <td>
                        <div class="flex" style="gap:2px">
                            <a class="btn btn-sm btn-ghost" href="<?= url('payroll/items/' . $slip['id']) ?>" title="Detail"><?= icon('eye') ?></a>
                            <a class="btn btn-sm btn-ghost" href="<?= url('payroll/items/' . $slip['id'] . '/pdf') ?>" target="_blank" title="PDF"><?= icon('printer') ?></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$slips): ?>
                <tr><td colspan="7">
                    <div class="empty-state">
                        <div class="empty-icon"><?= icon('receipt') ?></div>
                        <h3>Belum ada slip gaji</h3>
                        <p>Slip akan muncul di sini setelah payroll periode berjalan disetujui Finance.</p>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card__foot small muted" style="padding:10px 16px">
        Setiap slip memiliki QR verifikasi. Pindai QR pada PDF untuk memastikan keaslian dokumen melalui halaman verifikasi publik.
    </div>
</section>
