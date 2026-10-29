<?php
/**
 * Pusat laporan: presensi, lembur, cuti, payroll.
 * @var string $type, $monthLabel
 * @var array $types, $icons, $filters, $report, $units, $departments, $leaveTypes
 * @var bool $canSeePayroll
 */
$query = array_filter([
    'type'       => $type,
    'month'      => $filters['month'],
    'year'       => $type === 'leave' || $type === 'payroll' ? $filters['year'] : null,
    'unit'       => $filters['unit'] ?: null,
    'department' => $filters['department'] ?: null,
    'leave_type' => $filters['leave_type'] ?: null,
    'period'     => $filters['period'] ?: null,
    'q'          => $filters['q'] ?: null,
], static fn ($v) => $v !== null && $v !== '');
$qs = http_build_query($query);
$exportBase = url('reports/' . $type);
$tabUrl = static fn (string $key): string => url('reports?type=' . $key . ($key === 'attendance' || $key === 'overtime' ? '&month=' . urlencode($filters['month']) : '&year=' . (int) $filters['year']));
$totals = $report['totals'] ?? [];
?>
<div class="page-head">
    <div>
        <h1>Laporan &amp; Cetak</h1>
        <div class="sub">Rekap operasional lintas modul — siap dicetak PDF atau diolah di Excel.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= e($exportBase . '/csv?' . $qs) ?>"><?= icon('download') ?> Export CSV</a>
        <a class="btn btn-primary" href="<?= e($exportBase . '/pdf?' . $qs) ?>" target="_blank"><?= icon('printer') ?> Cetak PDF</a>
    </div>
</div>

<div class="tabs">
    <?php foreach ($types as $key => $label): ?>
        <a class="tab <?= $type === $key ? 'active' : '' ?>" href="<?= e($tabUrl($key)) ?>">
            <?= icon($icons[$key] ?? 'file') ?> <?= e($label) ?>
        </a>
    <?php endforeach; ?>
</div>

<form class="filter-bar" method="get" action="<?= url('reports') ?>" style="margin-bottom:16px">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <?php if ($type === 'attendance' || $type === 'overtime'): ?>
        <div class="input-group" style="max-width:190px">
            <?= icon('calendar') ?>
            <input class="input" type="month" name="month" value="<?= e($filters['month']) ?>">
        </div>
    <?php elseif ($type === 'leave' || $type === 'payroll'): ?>
        <select class="select" name="year" data-auto-submit style="min-width:110px">
            <?php foreach (range((int) date('Y'), (int) date('Y') - 4) as $year): ?>
                <option value="<?= $year ?>" <?= (int) $filters['year'] === $year ? 'selected' : '' ?>><?= $year ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <select class="select" name="unit" data-auto-submit style="min-width:150px">
        <option value="">Semua unit</option>
        <?php foreach ($units as $unit): ?>
            <option value="<?= (int) $unit['id'] ?>" <?= (int) $filters['unit'] === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="select" name="department" data-auto-submit style="min-width:150px">
        <option value="">Semua departemen</option>
        <?php foreach ($departments as $department): ?>
            <option value="<?= (int) $department['id'] ?>" <?= (int) $filters['department'] === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($type === 'leave'): ?>
        <select class="select" name="leave_type" data-auto-submit style="min-width:150px">
            <option value="">Semua jenis cuti</option>
            <?php foreach ($leaveTypes as $leaveType): ?>
                <option value="<?= (int) $leaveType['id'] ?>" <?= (int) $filters['leave_type'] === (int) $leaveType['id'] ? 'selected' : '' ?>><?= e($leaveType['name']) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <?php if ($type === 'payroll'): ?>
        <select class="select" name="period" data-auto-submit style="min-width:200px">
            <option value="">Ringkasan per periode</option>
            <?php foreach ($report['periods'] as $period): ?>
                <option value="<?= (int) $period['id'] ?>" <?= (int) $filters['period'] === (int) $period['id'] ? 'selected' : '' ?>>
                    <?= e(bulan_id((int) $period['period_month'])) ?> <?= (int) $period['period_year'] ?> · <?= e($period['unit_name'] ?? '-') ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <div class="input-group" style="max-width:190px">
        <?= icon('search') ?>
        <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Nama / NIK…">
    </div>
    <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
</form>

<?php if ($type === 'attendance'): ?>
    <div class="mini-stat-grid">
        <div class="mini-stat"><span class="k">Total Hadir</span><span class="v"><?= number_id($totals['present_days'] ?? 0) ?></span><span class="s">hari-karyawan · <?= e($monthLabel) ?></span></div>
        <div class="mini-stat"><span class="k">Tidak Hadir</span><span class="v" style="color:var(--rose-600)"><?= number_id($totals['absent_days'] ?? 0) ?></span><span class="s"><?= number_id($totals['leave_days'] ?? 0) ?> hari cuti</span></div>
        <div class="mini-stat"><span class="k">Keterlambatan</span><span class="v"><?= number_id($totals['late_minutes'] ?? 0) ?></span><span class="s">menit · <?= number_id($totals['late_days'] ?? 0) ?> hari</span></div>
        <div class="mini-stat"><span class="k">Jam Kerja</span><span class="v"><?= number_id($totals['work_hours'] ?? 0, 1) ?></span><span class="s">jam tercatat</span></div>
        <div class="mini-stat"><span class="k">Tanpa Check-out</span><span class="v"><?= number_id($totals['missing_checkout'] ?? 0) ?></span><span class="s">perlu tindak lanjut</span></div>
    </div>

    <section class="card">
        <div class="card__head"><div><h2>Rekap Presensi Karyawan</h2>
            <div class="sub"><?= e($monthLabel) ?> · <?= number_id(count($report['rows'])) ?> karyawan</div></div></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Karyawan</th><th>Unit / Departemen</th><th class="num">Hadir</th><th class="num">Telat</th>
                    <th class="num">Absen</th><th class="num">Cuti</th><th class="num">Jam Kerja</th><th class="num">Kehadiran</th></tr></thead>
                <tbody>
                <?php foreach ($report['rows'] as $row): ?>
                    <tr>
                        <td><b><?= e($row['full_name']) ?></b><div class="mono muted" style="font-size:10px"><?= e($row['employee_code']) ?></div></td>
                        <td class="small"><?= e($row['unit_name'] ?? '-') ?><div class="muted" style="font-size:10px"><?= e($row['department_name'] ?? '-') ?></div></td>
                        <td class="num small"><?= number_id($row['present_days']) ?></td>
                        <td class="num small"><?= number_id($row['late_days']) ?><div class="muted" style="font-size:9.6px"><?= number_id($row['late_minutes']) ?>m</div></td>
                        <td class="num small"><?= number_id($row['absent_days']) ?></td>
                        <td class="num small"><?= number_id($row['leave_days']) ?></td>
                        <td class="num small"><?= number_id($row['work_hours'], 1) ?></td>
                        <td class="num small"><b><?= number_id($row['attendance_rate'], 1) ?>%</b></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$report['rows']): ?>
                    <tr><td colspan="8" class="table-empty"><?= icon('search') ?>Tidak ada data pada filter ini.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php elseif ($type === 'overtime'): ?>
    <div class="mini-stat-grid">
        <div class="mini-stat"><span class="k">Pengajuan</span><span class="v"><?= number_id($totals['requests'] ?? 0) ?></span><span class="s"><?= number_id($totals['approved'] ?? 0) ?> disetujui</span></div>
        <div class="mini-stat"><span class="k">Jam Disetujui</span><span class="v"><?= number_id($totals['approved_hours'] ?? 0, 1) ?></span><span class="s">jam lembur</span></div>
        <div class="mini-stat"><span class="k">Kompensasi</span><span class="v" style="font-size:16px"><?= rupiah($totals['compensation'] ?? 0) ?></span><span class="s">nilai lembur disetujui</span></div>
        <div class="mini-stat"><span class="k">Antrean</span><span class="v"><?= number_id(((int) ($totals['pending'] ?? 0)) + ((int) ($totals['manager_approved'] ?? 0))) ?></span><span class="s"><?= number_id($totals['rejected'] ?? 0) ?> ditolak</span></div>
    </div>

    <div class="split-grid wide-left">
        <section class="card">
            <div class="card__head"><div><h2>Rekap Lembur Karyawan</h2>
                <div class="sub"><?= e($monthLabel) ?></div></div></div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Karyawan</th><th>Unit</th><th class="num">Ajuan</th><th class="num">Disetujui</th>
                        <th class="num">Jam</th><th class="num">Kompensasi</th></tr></thead>
                    <tbody>
                    <?php foreach ($report['rows'] as $row): ?>
                        <tr>
                            <td><b><?= e($row['full_name']) ?></b><div class="mono muted" style="font-size:10px"><?= e($row['employee_code']) ?></div></td>
                            <td class="small"><?= e($row['unit_name'] ?? '-') ?><div class="muted" style="font-size:10px"><?= e($row['department_name'] ?? '-') ?></div></td>
                            <td class="num small"><?= number_id($row['requests']) ?></td>
                            <td class="num small"><?= number_id($row['approved']) ?></td>
                            <td class="num small"><?= number_id($row['approved_hours'], 1) ?></td>
                            <td class="num small"><?= rupiah($row['compensation']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$report['rows']): ?>
                        <tr><td colspan="6" class="table-empty"><?= icon('clock') ?>Belum ada lembur pada periode ini.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <section class="card">
            <div class="card__head"><div><h2>Lembur per Unit</h2><div class="sub">Hanya yang disetujui.</div></div></div>
            <div class="card__body flex-col gap-10">
                <?php foreach ($report['by_unit'] as $row): ?>
                    <div>
                        <div class="flex-between" style="font-size:11px;margin-bottom:3px">
                            <span><?= e($row['unit_name'] ?? '-') ?></span><b><?= number_id($row['hours'], 1) ?> jam</b>
                        </div>
                        <div class="progress thin"><span style="width:<?= max(4, (int) round((float) $row['hours'] / max(1.0, (float) (($report['by_unit'][0]['hours'] ?? 1))) * 100)) ?>%"></span></div>
                        <div class="muted" style="font-size:9.6px;margin-top:2px"><?= rupiah($row['compensation']) ?> · <?= number_id($row['requests']) ?> pengajuan</div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$report['by_unit']): ?>
                    <div class="empty-state" style="padding:10px 0"><h3>Belum ada data</h3></div>
                <?php endif; ?>
            </div>
        </section>
    </div>

<?php elseif ($type === 'leave'): ?>
    <div class="mini-stat-grid">
        <div class="mini-stat"><span class="k">Total Jatah</span><span class="v"><?= number_id($totals['quota_days'] ?? 0) ?></span><span class="s">hari · tahun <?= (int) $filters['year'] ?></span></div>
        <div class="mini-stat"><span class="k">Terpakai</span><span class="v" style="color:var(--teal-700)"><?= number_id($totals['used_days'] ?? 0, 1) ?></span><span class="s">hari cuti</span></div>
        <div class="mini-stat"><span class="k">Sisa</span><span class="v"><?= number_id($totals['remaining_days'] ?? 0, 1) ?></span><span class="s">hari tersedia</span></div>
        <div class="mini-stat"><span class="k">Menunggu</span><span class="v"><?= number_id($totals['pending_requests'] ?? 0) ?></span><span class="s">pengajuan diproses</span></div>
    </div>

    <div class="split-grid wide-left">
        <section class="card">
            <div class="card__head"><div><h2>Saldo &amp; Pemakaian Cuti</h2>
                <div class="sub">Tahun <?= (int) $filters['year'] ?></div></div></div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Karyawan</th><th>Jenis</th><th class="num">Jatah</th><th class="num">Carry</th>
                        <th class="num">Terpakai</th><th class="num">Sisa</th><th class="num">Menunggu</th></tr></thead>
                    <tbody>
                    <?php foreach ($report['rows'] as $row): ?>
                        <tr>
                            <td><b><?= e($row['full_name']) ?></b><div class="mono muted" style="font-size:10px"><?= e($row['employee_code']) ?></div></td>
                            <td class="small"><?= e($row['leave_type']) ?></td>
                            <td class="num small"><?= number_id($row['quota_days']) ?></td>
                            <td class="num small"><?= number_id($row['carry_over']) ?></td>
                            <td class="num small"><?= number_id($row['used_days'], 1) ?></td>
                            <td class="num small"><b><?= number_id($row['remaining_days'], 1) ?></b></td>
                            <td class="num small"><?= number_id($row['pending_requests']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$report['rows']): ?>
                        <tr><td colspan="7" class="table-empty"><?= icon('calendar') ?>Belum ada saldo cuti pada filter ini.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <section class="card">
            <div class="card__head"><div><h2>Pemakaian per Jenis</h2><div class="sub">Akumulasi tahun <?= (int) $filters['year'] ?>.</div></div></div>
            <div class="card__body flex-col gap-10">
                <?php foreach (($report['by_type'] ?? []) as $row): ?>
                    <?php $pct = (float) $row['entitlement'] > 0 ? (int) round((float) $row['used_days'] / (float) $row['entitlement'] * 100) : 0; ?>
                    <div>
                        <div class="flex-between" style="font-size:11px;margin-bottom:3px">
                            <span><?= e($row['leave_type']) ?></span><b><?= number_id($row['used_days'], 1) ?> hari</b>
                        </div>
                        <div class="progress thin"><span style="width:<?= max(3, $pct) ?>%"></span></div>
                        <div class="muted" style="font-size:9.6px;margin-top:2px"><?= number_id($row['employees']) ?> karyawan · jatah <?= number_id($row['entitlement']) ?> hari</div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($report['by_type'])): ?>
                    <div class="empty-state" style="padding:10px 0"><h3>Belum ada data</h3></div>
                <?php endif; ?>
            </div>
        </section>
    </div>

<?php else: ?>
    <div class="mini-stat-grid">
        <div class="mini-stat"><span class="k">Periode</span><span class="v"><?= number_id(count($report['periods'])) ?></span><span class="s">tahun <?= (int) $filters['year'] ?></span></div>
        <div class="mini-stat"><span class="k">Slip</span><span class="v"><?= number_id($totals['total_employee'] ?? 0) ?></span><span class="s">karyawan terbayar</span></div>
        <div class="mini-stat"><span class="k">Bruto</span><span class="v" style="font-size:15px"><?= rupiah($totals['total_gross'] ?? 0) ?></span><span class="s">potongan <?= rupiah($totals['total_deduction'] ?? 0) ?></span></div>
        <div class="mini-stat"><span class="k">Net</span><span class="v" style="font-size:15px;color:var(--teal-700)"><?= rupiah($totals['total_net'] ?? 0) ?></span><span class="s">ditransfer</span></div>
        <div class="mini-stat"><span class="k">BPJS Perusahaan</span><span class="v" style="font-size:15px"><?= rupiah($totals['total_bpjs_company'] ?? 0) ?></span><span class="s">biaya tambahan</span></div>
    </div>

    <?php if ((int) $filters['period'] > 0): ?>
        <section class="card">
            <div class="card__head"><div><h2>Detail Slip pada Periode</h2>
                <div class="sub"><?= number_id(count($report['items'])) ?> slip · siap dicetak</div></div></div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>No. Slip</th><th>Karyawan</th><th>Departemen</th><th class="num">Bruto</th><th class="num">Potongan</th><th class="num">Net</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($report['items'] as $row): ?>
                        <tr>
                            <td class="mono small"><?= e($row['slip_number']) ?></td>
                            <td><b><?= e($row['employee_name']) ?></b><div class="mono muted" style="font-size:10px"><?= e($row['employee_code']) ?></div></td>
                            <td class="small"><?= e($row['department_name'] ?: '-') ?></td>
                            <td class="num small"><?= rupiah($row['gross_salary']) ?></td>
                            <td class="num small" style="color:var(--rose-600)"><?= rupiah($row['total_deduction']) ?></td>
                            <td class="num"><b><?= rupiah($row['net_salary']) ?></b></td>
                            <td><?= status_badge($row['payment_status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php else: ?>
        <div class="split-grid wide-left">
            <section class="card">
                <div class="card__head"><div><h2>Ringkasan Periode Payroll</h2>
                    <div class="sub">Seluruh unit pada tahun <?= (int) $filters['year'] ?>.</div></div></div>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Periode</th><th>Unit</th><th class="num">Karyawan</th><th class="num">Bruto</th>
                            <th class="num">Potongan</th><th class="num">Net</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($report['periods'] as $row): ?>
                            <tr>
                                <td><b><?= e(bulan_id((int) $row['period_month'])) ?> <?= (int) $row['period_year'] ?></b>
                                    <div class="muted" style="font-size:10px">bayar <?= tanggal($row['pay_date']) ?></div></td>
                                <td class="small"><?= e($row['unit_name'] ?? '-') ?></td>
                                <td class="num small"><?= number_id($row['total_employee']) ?></td>
                                <td class="num small"><?= rupiah($row['total_gross']) ?></td>
                                <td class="num small" style="color:var(--rose-600)"><?= rupiah($row['total_deduction']) ?></td>
                                <td class="num"><b><?= rupiah($row['total_net']) ?></b></td>
                                <td><?= status_badge($row['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$report['periods']): ?>
                            <tr><td colspan="7" class="table-empty"><?= icon('wallet') ?>Belum ada periode payroll pada tahun ini.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="card">
                <div class="card__head"><div><h2>Realisasi per Unit</h2><div class="sub">Hanya periode lunas.</div></div></div>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Unit</th><th class="num">Periode</th><th class="num">Net</th></tr></thead>
                        <tbody>
                        <?php foreach ($report['by_unit'] as $row): ?>
                            <tr>
                                <td class="small"><b><?= e($row['unit_name'] ?? '-') ?></b>
                                    <div class="muted" style="font-size:10px"><?= number_id($row['employees']) ?> slip</div></td>
                                <td class="num small"><?= number_id($row['periods']) ?></td>
                                <td class="num small"><?= rupiah($row['net']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$report['by_unit']): ?>
                            <tr><td colspan="3" class="table-empty">Belum ada payroll lunas.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card__foot small muted" style="padding:10px 16px">
                    Pilih salah satu periode pada filter untuk melihat rincian slip per karyawan.
                </div>
            </section>
        </div>
    <?php endif; ?>
<?php endif; ?>
