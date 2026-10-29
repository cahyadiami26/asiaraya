<?php
/** Finance dashboard — payroll reception & payment. */
$last = $payroll['last'];
?>
<div class="page-head" style="margin-bottom:14px">
    <div>
        <h1><?= e($greeting) ?>, <?= e($user['full_name']) ?></h1>
        <div class="sub">
            <span><?= tanggal_panjang(date('Y-m-d')) ?></span>
            <span class="dot" style="background:var(--line-strong)"></span>
            <span>Finance Gateway · Finalisasi &amp; Pembayaran Payroll</span>
        </div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('reports') ?>"><?= icon('printer') ?> Cetak Laporan</a>
        <a class="btn btn-primary" href="<?= url('payroll') ?>"><?= icon('wallet') ?> Payroll Masuk</a>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi">
        <div class="kpi__label"><span>Payroll Menunggu Pembayaran</span><span class="kpi__icon amber"><?= icon('clock') ?></span></div>
        <div class="kpi__value"><?= number_id(count($pendingPayment)) ?> <span class="unit">slip</span></div>
        <div class="kpi__trend" style="color:var(--muted);font-weight:500">Perlu verifikasi finance</div>
        <div class="kpi__foot"><span>Status</span><b>Menunggu Finalisasi</b></div>
    </div>
    <div class="kpi">
        <div class="kpi__label"><span>Total Payroll Terakhir</span><span class="kpi__icon navy"><?= icon('wallet') ?></span></div>
        <div class="kpi__value" style="font-size:21px"><?= rupiah($last['total_net'] ?? 0) ?></div>
        <div style="margin-top:6px"><?= status_badge($last['status'] ?? 'DRAFT') ?></div>
        <div class="kpi__foot">
            <span>Periode <b><?= $last ? e(bulan_id((int) $last['period_month'])) . ' ' . (int) $last['period_year'] : '-' ?></b></span>
            <span><?= (int) ($last['total_employee'] ?? 0) ?> karyawan</span>
        </div>
    </div>
    <div class="kpi">
        <div class="kpi__label"><span>Transfer Disbursement</span><span class="kpi__icon indigo"><?= icon('credit-card') ?></span></div>
        <div class="kpi__value" style="font-size:21px"><?= rupiah($payroll['gross']) ?></div>
        <div class="kpi__trend" style="color:var(--muted);font-weight:500">Estimasi bruto periode berjalan</div>
        <div class="kpi__foot"><span>H2H</span><b>Mandiri &amp; BCA</b></div>
    </div>
    <div class="kpi">
        <div class="kpi__label"><span>Kewajiban PPh &amp; BPJS</span><span class="kpi__icon rose"><?= icon('shield') ?></span></div>
        <div class="kpi__value" style="font-size:21px"><?= rupiah($payroll['pph'] + $payroll['bpjs_employee'] + $payroll['bpjs_company']) ?></div>
        <div class="kpi__trend" style="color:var(--muted);font-weight:500">PPh 21 + BPJS perusahaan/karyawan</div>
        <div class="kpi__foot"><span>Cut-off</span><b><?= tanggal($payroll['cutoff_date']) ?></b></div>
    </div>
</div>

<section class="card mt-16">
    <div class="card__head">
        <div>
            <h2>Siap Dibayarkan</h2>
            <div class="sub">Data payroll yang telah diteruskan HR dan menunggu finalisasi pembayaran finance.</div>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>No. Slip</th><th>Karyawan</th><th>Unit</th><th class="num">Gaji Bersih</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pendingPayment as $item): ?>
                <tr>
                    <td class="mono small"><?= e($item['slip_number'] ?? '-') ?></td>
                    <td><b><?= e($item['employee_name']) ?></b><div class="small muted mono"><?= e($item['employee_code']) ?></div></td>
                    <td class="small"><?= e($item['unit_name'] ?? '-') ?></td>
                    <td class="num"><b><?= rupiah($item['net_salary']) ?></b></td>
                    <td><?= status_badge($item['payment_status']) ?></td>
                    <td class="text-right"><a class="btn btn-outline btn-sm" href="<?= url('payroll/items/' . $item['id']) ?>">Detail</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pendingPayment): ?>
                <tr><td colspan="6"><div class="table-empty"><?= icon('wallet') ?>Belum ada data payroll yang diteruskan ke finance.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card mt-16">
    <div class="card__head"><h2>Riwayat Periode Payroll</h2></div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Periode</th><th>Unit</th><th>Cut-off</th><th class="num">Karyawan</th><th class="num">Net</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($periods as $p): ?>
                <tr>
                    <td><b><?= e(bulan_id((int) $p['period_month'])) ?> <?= (int) $p['period_year'] ?></b></td>
                    <td class="small"><?= e($p['unit_name'] ?? '-') ?></td>
                    <td class="small"><?= tanggal($p['cutoff_date']) ?></td>
                    <td class="num"><?= (int) $p['total_employee'] ?></td>
                    <td class="num"><?= rupiah($p['total_net']) ?></td>
                    <td><?= status_badge($p['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$periods): ?>
                <tr><td colspan="6"><div class="table-empty">Belum ada periode payroll.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
