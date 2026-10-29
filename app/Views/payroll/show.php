<?php
/**
 * Detail periode payroll: alur status, rekap departemen, daftar slip.
 * @var array $period, $items, $recap, $filters, $statuses
 * @var bool $isHr, $isFin
 * @var array $old, $errors
 */
$status = (string) $period['status'];
$steps = [
    'DRAFT'            => 'Dibuat',
    'CALCULATED'       => 'Dihitung',
    'SENT_TO_FINANCE'  => 'Di Finance',
    'FINANCE_APPROVED' => 'Disetujui',
    'PAID'             => 'Dibayar',
];
$currentIndex = array_search($status, array_keys($steps), true);
$currentIndex = $currentIndex === false ? 0 : (int) $currentIndex;
$summary = $recap['summary'] ?? [];
$departmentOptions = array_map(static fn ($row) => (string) $row['department_name'], $recap['departments']);
$canCalculate = $isHr && in_array($status, ['DRAFT', 'CALCULATED'], true);
$canSubmit = $isHr && $status === 'CALCULATED' && (int) $period['total_employee'] > 0;
$canReopen = $isHr && in_array($status, ['SENT_TO_FINANCE', 'FINANCE_APPROVED'], true);
$canApprove = $isFin && $status === 'SENT_TO_FINANCE';
$canPay = $isFin && $status === 'FINANCE_APPROVED';
?>
<div class="page-head">
    <div>
        <div class="sub" style="margin-bottom:2px">
            <a href="<?= url('payroll') ?>" style="color:inherit">Payroll</a> / <?= e($period['unit_name'] ?? '-') ?>
        </div>
        <h1><?= e(bulan_id((int) $period['period_month'])) ?> <?= (int) $period['period_year'] ?> · <?= e($period['unit_name'] ?? '-') ?></h1>
        <div class="sub">
            <span><?= tanggal($period['start_date']) ?> – <?= tanggal($period['end_date']) ?></span>
            <span class="dot" style="background:var(--line-strong)"></span>
            <span>Cut-off <?= tanggal($period['cutoff_date']) ?></span>
            <span class="dot" style="background:var(--line-strong)"></span>
            <span>Bayar <?= tanggal($period['pay_date']) ?></span>
        </div>
    </div>
    <div class="page-head__actions">
        <?= status_badge($status) ?>
        <a class="btn btn-outline" href="<?= url('payroll/' . $period['id'] . '/export') ?>"><?= icon('download') ?> Export CSV</a>
    </div>
</div>

<section class="card" style="margin-bottom:16px">
    <div class="card__body">
        <div class="flex-between wrap gap-12">
            <div class="flex" style="gap:0;flex:1;min-width:280px">
                <?php foreach ($steps as $key => $label): ?>
                    <?php $index = array_search($key, array_keys($steps), true); ?>
                    <div style="flex:1;text-align:center;position:relative">
                        <div style="height:4px;border-radius:3px;background:<?= $index <= $currentIndex ? 'var(--teal-500)' : '#e2e8f0' ?>"></div>
                        <div class="small" style="margin-top:6px;font-weight:<?= $index === $currentIndex ? '700' : '500' ?>;color:<?= $index <= $currentIndex ? 'var(--teal-700)' : 'var(--muted)' ?>">
                            <?= e($label) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="flex wrap" style="gap:6px">
                <?php if ($canCalculate): ?>
                    <form method="post" action="<?= url('payroll/' . $period['id'] . '/calculate') ?>"
                          data-confirm="<?= (int) $period['total_employee'] > 0 ? 'Hitung ulang periode ini? Semua slip akan dibuat ulang.' : 'Hitung slip untuk seluruh karyawan aktif unit ini?' ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary btn-sm" type="submit"><?= icon('refresh') ?> <?= (int) $period['total_employee'] > 0 ? 'Hitung Ulang' : 'Hitung Payroll' ?></button>
                    </form>
                <?php endif; ?>
                <?php if ($canSubmit): ?>
                    <form method="post" action="<?= url('payroll/' . $period['id'] . '/submit') ?>"
                          data-confirm="Tanda tangani sebagai HR dan kirim payroll ini ke Finance?">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary btn-sm" type="submit"><?= icon('send') ?> Kirim ke Finance</button>
                    </form>
                <?php endif; ?>
                <?php if ($canReopen): ?>
                    <form method="post" action="<?= url('payroll/' . $period['id'] . '/reopen') ?>"
                          data-confirm="Tarik kembali periode ini ke HR? Tanda tangan akan dihapus.">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline" type="submit"><?= icon('arrow-left') ?> Tarik Kembali</button>
                    </form>
                <?php endif; ?>
                <?php if ($canApprove): ?>
                    <form method="post" action="<?= url('payroll/' . $period['id'] . '/approve') ?>"
                          data-confirm="Setujui payroll ini sebagai Finance?">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary btn-sm" type="submit"><?= icon('check') ?> Setujui</button>
                    </form>
                <?php endif; ?>
                <?php if ($canPay): ?>
                    <form method="post" action="<?= url('payroll/' . $period['id'] . '/pay') ?>"
                          data-confirm="Tandai seluruh slip pada periode ini sebagai LUNAS?">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary btn-sm" type="submit"><?= icon('credit-card') ?> Tandai Dibayar</button>
                    </form>
                <?php endif; ?>
                <?php if ($canCalculate): ?>
                    <form method="post" action="<?= url('payroll/' . $period['id'] . '/delete') ?>"
                          data-confirm="Hapus periode payroll ini beserta seluruh slip?">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)"><?= icon('trash') ?> Hapus</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($period['hr_signature_name'] || $period['fin_signature_name']): ?>
            <div class="flex wrap gap-20" style="margin-top:14px;padding-top:12px;border-top:1px dashed var(--line-strong);font-size:11px">
                <div>
                    <span class="muted" style="display:block;font-size:9.5px;letter-spacing:.08em;text-transform:uppercase;font-weight:700">Tanda Tangan HR</span>
                    <b><?= e($period['hr_signature_name'] ?: '—') ?></b>
                    <span class="muted"><?= $period['hr_signature_at'] ? tanggal($period['hr_signature_at'], 'd M Y H:i') : '' ?></span>
                </div>
                <div>
                    <span class="muted" style="display:block;font-size:9.5px;letter-spacing:.08em;text-transform:uppercase;font-weight:700">Tanda Tangan Finance</span>
                    <b><?= e($period['fin_signature_name'] ?: '—') ?></b>
                    <span class="muted"><?= $period['fin_signature_at'] ? tanggal($period['fin_signature_at'], 'd M Y H:i') : '' ?></span>
                </div>
                <div>
                    <span class="muted" style="display:block;font-size:9.5px;letter-spacing:.08em;text-transform:uppercase;font-weight:700">Dibayar</span>
                    <b><?= e($period['paid_by_name'] ?: '—') ?></b>
                    <span class="muted"><?= $period['paid_at'] ? tanggal($period['paid_at'], 'd M Y H:i') : '' ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<div class="mini-stat-grid">
    <div class="mini-stat"><span class="k">Karyawan</span><span class="v"><?= number_id($summary['employees'] ?? 0) ?></span><span class="s">slip pada periode ini</span></div>
    <div class="mini-stat"><span class="k">Bruto</span><span class="v" style="font-size:15px"><?= rupiah($summary['gross'] ?? 0) ?></span><span class="s">termasuk lembur <?= rupiah($summary['overtime'] ?? 0) ?></span></div>
    <div class="mini-stat"><span class="k">Potongan</span><span class="v" style="font-size:15px;color:var(--rose-600)"><?= rupiah($summary['deduction'] ?? 0) ?></span><span class="s">PPh <?= rupiah($summary['pph'] ?? 0) ?> · BPJS <?= rupiah(((float) ($summary['bpjs_kes_employee'] ?? 0)) + ((float) ($summary['bpjs_tk_employee'] ?? 0))) ?></span></div>
    <div class="mini-stat"><span class="k">Net Dibayarkan</span><span class="v" style="font-size:15px;color:var(--teal-700)"><?= rupiah($summary['net'] ?? 0) ?></span><span class="s">transfer ke karyawan</span></div>
    <div class="mini-stat"><span class="k">BPJS Perusahaan</span><span class="v" style="font-size:15px"><?= rupiah($summary['bpjs_company'] ?? 0) ?></span><span class="s">biaya di luar gaji</span></div>
</div>

<?php if (!$items): ?>
    <section class="card">
        <div class="card__body">
            <div class="empty-state">
                <div class="empty-icon"><?= icon('wallet') ?></div>
                <h3>Slip belum dihitung</h3>
                <p><?= $canCalculate ? 'Klik "Hitung Payroll" untuk memproses presensi, lembur, komponen gaji, BPJS, dan PPh 21.' : 'HR belum menjalankan kalkulasi untuk periode ini.' ?></p>
            </div>
        </div>
    </section>
<?php else: ?>
    <div class="split-grid wide-left">
        <section class="card">
            <div class="card__head">
                <div><h2>Slip Karyawan</h2>
                    <div class="sub"><?= number_id(count($items)) ?> slip ditampilkan.</div></div>
            </div>
            <form class="filter-bar" method="get" action="<?= url('payroll/' . $period['id']) ?>">
                <select class="select" name="department" data-auto-submit style="min-width:170px">
                    <option value="">Semua departemen</option>
                    <?php foreach ($departmentOptions as $department): ?>
                        <option value="<?= e($department) ?>" <?= $filters['department'] === $department ? 'selected' : '' ?>><?= e($department) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="input-group" style="max-width:220px">
                    <?= icon('search') ?>
                    <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Nama / NIK / no. slip…">
                </div>
                <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
            </form>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                    <tr>
                        <th>Karyawan</th><th>Departemen</th><th class="num">Hadir</th>
                        <th class="num">Bruto</th><th class="num">Potongan</th><th class="num">Net</th>
                        <th>Status</th><th style="width:88px">Slip</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $row): ?>
                        <tr>
                            <td>
                                <b><?= e($row['employee_name']) ?></b>
                                <div class="mono muted" style="font-size:10px"><?= e($row['slip_number']) ?></div>
                            </td>
                            <td class="small"><?= e($row['department_name'] ?: '-') ?>
                                <div class="muted" style="font-size:10px"><?= e($row['position_name'] ?: '') ?></div>
                            </td>
                            <td class="num small">
                                <?= (int) $row['present_days'] ?>
                                <?php if ((int) $row['absent_days'] > 0): ?>
                                    <div class="muted" style="font-size:9.6px">absen <?= (int) $row['absent_days'] ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="num small"><?= rupiah($row['gross_salary']) ?></td>
                            <td class="num small" style="color:var(--rose-600)"><?= rupiah($row['total_deduction']) ?></td>
                            <td class="num"><b><?= rupiah($row['net_salary']) ?></b></td>
                            <td><?= status_badge($row['payment_status']) ?></td>
                            <td>
                                <div class="flex" style="gap:2px">
                                    <a class="btn btn-sm btn-ghost" href="<?= url('payroll/items/' . $row['id']) ?>" title="Detail slip"><?= icon('eye') ?></a>
                                    <a class="btn btn-sm btn-ghost" href="<?= url('payroll/items/' . $row['id'] . '/pdf') ?>" target="_blank" title="PDF ber-QR"><?= icon('printer') ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$items): ?>
                        <tr><td colspan="8" class="table-empty"><?= icon('search') ?>Tidak ada slip yang cocok dengan filter.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="flex-col gap-16">
            <section class="card">
                <div class="card__head"><div><h2>Rekap per Departemen</h2>
                    <div class="sub">Distribusi net gaji periode ini.</div></div></div>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Departemen</th><th class="num">Orang</th><th class="num">Net</th></tr></thead>
                        <tbody>
                        <?php foreach ($recap['departments'] as $row): ?>
                            <tr>
                                <td class="small"><b><?= e($row['department_name']) ?></b></td>
                                <td class="num small"><?= number_id($row['employees']) ?></td>
                                <td class="num small"><?= rupiah($row['net']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recap['departments']): ?>
                            <tr><td colspan="3" class="table-empty">Belum ada data.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><div><h2>Komposisi Potongan</h2></div></div>
                <div class="card__body">
                    <?php
                    $parts = [
                        'PPh 21'            => (float) ($summary['pph'] ?? 0),
                        'BPJS Karyawan'     => (float) ($summary['bpjs_kes_employee'] ?? 0) + (float) ($summary['bpjs_tk_employee'] ?? 0),
                        'Denda Terlambat'   => (float) ($summary['late_deduction'] ?? 0),
                        'Absen / Cuti UP'   => (float) ($summary['absent_deduction'] ?? 0) + (float) ($summary['leave_deduction'] ?? 0),
                    ];
                    $partTotal = max(1, array_sum($parts));
                    ?>
                    <?php foreach ($parts as $label => $value): ?>
                        <div style="margin-bottom:10px">
                            <div class="flex-between" style="font-size:11px;margin-bottom:3px">
                                <span><?= e($label) ?></span><b><?= rupiah($value) ?></b>
                            </div>
                            <div class="progress thin"><span style="width:<?= (int) round($value / $partTotal * 100) ?>%"></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
<?php endif; ?>
