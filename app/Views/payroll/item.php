<?php
/**
 * Detail satu slip gaji (tampilan web).
 * @var array $item, $components
 * @var bool $isHr, $isFin, $isOwner
 * @var string $verifyUrl
 */
$benefits = array_values(array_filter($components, static fn ($c) => $c['type'] === 'BENEFIT'));
$deductions = array_values(array_filter($components, static fn ($c) => $c['type'] === 'DEDUCTION'));
$periodLabel = bulan_id((int) $item['period_month']) . ' ' . (int) $item['period_year'];
?>
<div class="page-head">
    <div>
        <div class="sub" style="margin-bottom:2px">
            <a href="<?= url('payroll/' . $item['period_id']) ?>" style="color:inherit">Payroll <?= e($periodLabel) ?></a> / Slip
        </div>
        <h1><?= e($item['employee_name']) ?></h1>
        <div class="sub">
            <span class="mono"><?= e($item['slip_number']) ?></span>
            <span class="dot" style="background:var(--line-strong)"></span>
            <span><?= e($item['unit_name'] ?? '-') ?></span>
            <span class="dot" style="background:var(--line-strong)"></span>
            <span>Bayar <?= tanggal($item['pay_date']) ?></span>
        </div>
    </div>
    <div class="page-head__actions">
        <?= status_badge($item['payment_status']) ?>
        <a class="btn btn-outline" href="<?= url('payroll/items/' . $item['id'] . '/pdf') ?>" target="_blank"><?= icon('eye') ?> Lihat PDF</a>
        <a class="btn btn-primary" href="<?= url('payroll/items/' . $item['id'] . '/pdf?download=1') ?>"><?= icon('download') ?> Unduh PDF</a>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Rincian Slip Gaji</h2>
                <div class="sub">Periode <?= tanggal($item['start_date']) ?> – <?= tanggal($item['end_date']) ?></div></div>
            <span class="badge badge-slate"><?= e($item['department_name'] ?: 'Tanpa departemen') ?></span>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Komponen</th><th class="num">Tunjangan</th><th class="num">Potongan</th></tr></thead>
                <tbody>
                <?php
                $rows = max(count($benefits), count($deductions));
                for ($i = 0; $i < $rows; $i++):
                    $benefit = $benefits[$i] ?? null;
                    $deduction = $deductions[$i] ?? null;
                    ?>
                    <tr>
                        <td class="small">
                            <?php if ($benefit && $deduction): ?>
                                <b><?= e($benefit['name']) ?></b>
                                <div class="muted" style="font-size:10px"><?= e($deduction['name']) ?></div>
                            <?php elseif ($benefit): ?>
                                <b><?= e($benefit['name']) ?></b>
                            <?php elseif ($deduction): ?>
                                <b><?= e($deduction['name']) ?></b>
                            <?php endif; ?>
                        </td>
                        <td class="num small"><?= $benefit ? rupiah($benefit['amount']) : '—' ?></td>
                        <td class="num small" style="color:var(--rose-600)"><?= $deduction ? rupiah($deduction['amount']) : '—' ?></td>
                    </tr>
                <?php endfor; ?>
                <tr>
                    <td><b>Gaji Pokok</b></td>
                    <td class="num"><b><?= rupiah($item['basic_salary']) ?></b></td>
                    <td></td>
                </tr>
                <tr>
                    <td><b>Total</b></td>
                    <td class="num"><b><?= rupiah($item['gross_salary']) ?></b></td>
                    <td class="num"><b style="color:var(--rose-600)"><?= rupiah($item['total_deduction']) ?></b></td>
                </tr>
                <tr style="background:var(--teal-50, #effcf9)">
                    <td><b style="color:var(--teal-800, #115e59)">Gaji Bersih (Take Home)</b></td>
                    <td class="num" colspan="2"><b style="font-size:15px;color:var(--teal-800, #115e59)"><?= rupiah($item['net_salary']) ?></b></td>
                </tr>
                </tbody>
            </table>
        </div>
        <div class="card__foot small muted" style="padding:10px 16px">
            Slip ini sah tanpa tanda tangan basah. Verifikasi keaslian melalui QR pada PDF atau tautan
            <span class="mono"><?= e($verifyUrl) ?></span>.
        </div>
    </section>

    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head"><div><h2>Data Karyawan</h2></div></div>
            <div class="card__body flex-col gap-8">
                <div class="sim-line"><span>NIK</span><b class="mono"><?= e($item['employee_code']) ?></b></div>
                <div class="sim-line"><span>Jabatan</span><b><?= e($item['position_name'] ?: '-') ?></b></div>
                <div class="sim-line"><span>Status Pajak</span><b><?= e($item['tax_status'] ?: '-') ?></b></div>
                <div class="sim-line"><span>Bank</span><b><?= e($item['bank_name'] ?: '-') ?></b></div>
                <div class="sim-line"><span>No. Rekening</span><b class="mono"><?= e($item['bank_account_number'] ?: '-') ?></b></div>
                <div class="sim-line"><span>NPWP</span><b class="mono"><?= e($item['npwp'] ?: '-') ?></b></div>
                <div class="sim-line"><span>BPJS Kesehatan</span><b class="mono"><?= e($item['bpjs_kesehatan_no'] ?: '-') ?></b></div>
                <div class="sim-line"><span>BPJS TK</span><b class="mono"><?= e($item['bpjs_tk_no'] ?: '-') ?></b></div>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Rekap Presensi &amp; Lembur</h2></div></div>
            <div class="card__body flex-col gap-8">
                <div class="sim-line"><span>Hadir</span><b><?= (int) $item['present_days'] ?> hari</b></div>
                <div class="sim-line"><span>Tidak hadir</span><b><?= (int) $item['absent_days'] ?> hari</b></div>
                <div class="sim-line"><span>Terlambat</span><b><?= (int) $item['late_days'] ?> hari · <?= (int) $item['late_minutes'] ?> menit</b></div>
                <div class="sim-line"><span>Cuti tanpa upah</span><b><?= number_id((float) $item['unpaid_leave_days'], 1) ?> hari</b></div>
                <div class="sim-line"><span>Lembur</span><b><?= number_id((float) $item['overtime_hours'], 1) ?> jam · <?= rupiah($item['overtime_amount']) ?></b></div>
                <div class="sim-line"><span>Denda keterlambatan</span><b>− <?= rupiah($item['late_deduction']) ?></b></div>
            </div>
        </section>
    </div>
</div>
