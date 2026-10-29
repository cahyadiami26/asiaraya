<?php
/**
 * Manajemen lembur (HR).
 * @var array $rows, $stats, $filters, $units, $departments, $employees, $daily, $top, $byUnit, $policy, $preview, $paginator
 * @var string $month, $monthLabel, $prevMonth, $nextMonth
 */
use App\Models\OvertimeModel;

$tabs = [
    'ALL'              => 'Semua',
    'OPEN'             => 'Perlu Tindakan',
    'PENDING'          => 'Menunggu Atasan',
    'MANAGER_APPROVED' => 'Menunggu HR',
    'APPROVED'         => 'Disetujui',
    'REJECTED'         => 'Ditolak',
];
$current = $filters['status'] ?: 'ALL';

$baseQuery = array_filter([
    'status'     => $current !== 'ALL' ? $current : null,
    'type'       => $filters['type'] ?: null,
    'unit'       => $filters['unit'] ?: null,
    'department' => $filters['department'] ?: null,
    'employee'   => $filters['employee'] ?: null,
    'month'      => $month,
    'q'          => $filters['q'] ?: null,
]);
$link = static fn (int $page): string => url('overtime') . '?' . http_build_query($baseQuery + ['page' => $page]);
$csv = static fn (): string => url('overtime/export') . '?' . http_build_query($baseQuery);

$maxHours = 1.0;
foreach ($daily as $day) {
    $maxHours = max($maxHours, (float) $day['hours']);
}
$stepOf = static fn (string $status): int => ['PENDING' => 1, 'MANAGER_APPROVED' => 2, 'APPROVED' => 3][$status] ?? 0;
$monthTotalHours = array_sum(array_column($daily, 'hours'));
?>
<div class="page-head">
    <div>
        <h1>Manajemen Lembur</h1>
        <div class="sub">Pengajuan karyawan, persetujuan dua tahap, penjadwalan massal, dan kompensasi lembur.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('overtime/schedules') ?>"><?= icon('calendar-check') ?> Lembur Terjadwal</a>
        <a class="btn btn-soft" href="#ot-form" data-open-form><?= icon('plus') ?> Tambah Lembur</a>
        <a class="btn btn-primary" href="<?= e($csv()) ?>"><?= icon('download') ?> Export CSV</a>
    </div>
</div>

<?php if (!$policy['enabled']): ?>
    <div class="alert alert-warning" style="margin-bottom:16px">
        <?= icon('alert') ?>
        <div>Modul lembur sedang <b>dinonaktifkan</b> pada pengaturan. Pengajuan baru akan ditolak sampai modul diaktifkan kembali.</div>
    </div>
<?php endif; ?>

<div class="kpi-grid" style="margin-bottom:16px">
    <div class="kpi">
        <div class="kpi__label"><span>Menunggu Atasan</span><span class="kpi__icon amber"><?= icon('clock') ?></span></div>
        <div class="kpi__value" style="color:var(--amber-600)"><?= number_id($stats['pending']) ?></div>
        <div class="kpi__trend" style="font-weight:500;color:var(--muted)">Tahap 1 · atasan langsung</div>
    </div>
    <div class="kpi">
        <div class="kpi__label"><span>Menunggu HR</span><span class="kpi__icon indigo"><?= icon('shield') ?></span></div>
        <div class="kpi__value" style="color:var(--indigo-600, #4f46e5)"><?= number_id($stats['awaiting_hr']) ?></div>
        <div class="kpi__trend" style="font-weight:500;color:var(--muted)">Tahap 2 · keputusan final</div>
    </div>
    <div class="kpi">
        <div class="kpi__label"><span>Jam Lembur Disetujui</span><span class="kpi__icon teal"><?= icon('activity') ?></span></div>
        <div class="kpi__value"><?= number_id($stats['hours'], 1) ?><span class="unit">jam</span></div>
        <div class="kpi__trend"><?= number_id($stats['approved']) ?> pengajuan · <?= number_id($stats['people']) ?> karyawan</div>
    </div>
    <div class="kpi">
        <div class="kpi__label"><span>Kompensasi Lembur</span><span class="kpi__icon"><?= icon('wallet') ?></span></div>
        <div class="kpi__value" style="font-size:22px"><?= rupiah($stats['amount']) ?></div>
        <div class="kpi__trend" style="font-weight:500;color:var(--muted)">Maks <?= rupiah($policy['max_amount']) ?> / pengajuan</div>
    </div>
</div>

<details class="form-panel" id="ot-form" <?= ($old || !empty($_GET['tambah'])) ? 'open' : '' ?>>
    <summary>
        <span class="form-panel__title"><?= icon('plus', 'ico') ?> Tambah Lembur Manual</span>
        <span class="muted small">Atur jam rencana · batas <?= number_id($policy['max_hours']) ?> jam / hari</span>
    </summary>
    <form method="post" action="<?= url('overtime') ?>" class="form-panel__body">
        <?= csrf_field() ?>
        <div class="form-grid three">
            <div class="field">
                <label>Karyawan <span class="req">*</span></label>
                <select class="select" name="employee_id" required>
                    <option value="">Pilih karyawan…</option>
                    <?php foreach ($employees as $person): ?>
                        <option value="<?= (int) $person['id'] ?>" <?= (int) ($old['employee_id'] ?? 0) === (int) $person['id'] ? 'selected' : '' ?>>
                            <?= e($person['full_name']) ?> · <?= e($person['employee_code']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Tanggal <span class="req">*</span></label>
                <input class="input" type="date" name="work_date" value="<?= e($old['work_date'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="field">
                <label>Jenis</label>
                <select class="select" name="type">
                    <option value="REQUEST" <?= ($old['type'] ?? 'REQUEST') === 'REQUEST' ? 'selected' : '' ?>>Pengajuan (approval 2 tahap)</option>
                    <option value="SCHEDULED" <?= ($old['type'] ?? '') === 'SCHEDULED' ? 'selected' : '' ?>>Penugasan terjadwal</option>
                </select>
            </div>
            <div class="field">
                <label>Jam Mulai <span class="req">*</span></label>
                <input class="input" type="time" name="planned_start" value="<?= e($old['planned_start'] ?? '17:00') ?>" required>
            </div>
            <div class="field">
                <label>Jam Selesai <span class="req">*</span></label>
                <input class="input" type="time" name="planned_end" value="<?= e($old['planned_end'] ?? '20:00') ?>" required>
                <span class="hint">Melewati tengah malam diperbolehkan (mis. 22:00–01:00).</span>
            </div>
            <div class="field">
                <label>Alasan / Pekerjaan</label>
                <input class="input" type="text" name="reason" value="<?= e($old['reason'] ?? '') ?>" maxlength="255" placeholder="mis. Closing laporan bulanan">
            </div>
        </div>
        <label class="checkbox" style="margin-top:10px">
            <input type="checkbox" name="auto_approve" value="1" <?= (int) ($old['auto_approve'] ?? 0) === 1 ? 'checked' : '' ?>>
            <span>Langsung setujui sebagai keputusan HR (tanpa menunggu atasan)</span>
        </label>
        <div class="form-actions" style="margin-top:14px">
            <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan Lembur</button>
            <a class="btn btn-ghost" href="<?= url('overtime') ?>">Batal</a>
        </div>
    </form>
</details>

<section class="card" style="margin-bottom:16px">
    <div class="card__head">
        <div><h2>Sebaran Jam Lembur</h2>
            <div class="sub">Total <?= number_id($monthTotalHours, 1) ?> jam pada <?= e($monthLabel) ?>.</div></div>
        <div class="flex" style="gap:6px;align-items:center">
            <a class="btn btn-sm btn-ghost" href="<?= url('overtime?month=' . $prevMonth) ?>"><?= icon('chevron-left') ?></a>
            <span class="badge badge-slate" style="height:24px"><?= e($monthLabel) ?></span>
            <a class="btn btn-sm btn-ghost" href="<?= url('overtime?month=' . $nextMonth) ?>"><?= icon('chevron-right') ?></a>
        </div>
    </div>
    <div class="card__body">
        <div class="trend-chart">
            <?php foreach ($daily as $day): ?>
                <?php $height = (int) round(((float) $day['hours'] / $maxHours) * 100); ?>
                <div class="trend-bar <?= $day['dow'] >= 6 || $day['holiday'] ? 'is-weekend' : '' ?>"
                     title="<?= tanggal($day['date']) ?>: <?= number_id($day['hours'], 1) ?> jam · <?= (int) $day['requests'] ?> pengajuan<?= $day['holiday'] ? ' · ' . e($day['holiday']) : '' ?>">
                    <span class="trend-bar__fill" style="height:<?= max(2, $height) ?>%;<?= (float) $day['hours'] === 0.0 ? 'background:#e2e8f0' : '' ?>"></span>
                    <span class="trend-bar__label"><?= (int) $day['day'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="flex wrap small muted" style="gap:14px;margin-top:10px">
            <span><span class="dot" style="background:var(--teal-500)"></span> Jam lembur terjadwal &amp; disetujui</span>
            <span><span class="dot" style="background:#dbe4ee"></span> Akhir pekan / hari besar</span>
            <span style="margin-left:auto">Tarif: gaji pokok ÷ <?= number_id($policy['divisor']) ?> · jam 1 ×<?= e(rtrim(rtrim(number_format($policy['first'], 2, '.', ''), '0'), '.')) ?> · berikutnya ×<?= e(rtrim(rtrim(number_format($policy['next'], 2, '.', ''), '0'), '.')) ?></span>
        </div>
    </div>
</section>

<div class="split-grid wide-left">
    <section class="card">
        <form class="filter-bar" method="get" action="<?= url('overtime') ?>">
            <div class="segmented">
                <?php foreach ($tabs as $key => $label): ?>
                    <?php $tabQuery = array_filter(array_merge($baseQuery, ['status' => $key === 'ALL' ? null : $key])); ?>
                    <a class="<?= $current === $key ? 'active' : '' ?>" href="<?= url('overtime') . '?' . http_build_query($tabQuery) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>
            <select class="select" name="unit" data-auto-submit style="min-width:150px">
                <option value="">Semua unit</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= (int) $unit['id'] ?>" <?= (int) $filters['unit'] === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="department" data-auto-submit style="min-width:150px">
                <option value="">Semua departemen</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= (int) $dept['id'] ?>" <?= (int) $filters['department'] === (int) $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="input" type="month" name="month" value="<?= e($month) ?>" style="width:150px" data-auto-submit>
            <select class="select" name="type" data-auto-submit style="min-width:130px">
                <option value="">Semua jenis</option>
                <option value="REQUEST" <?= $filters['type'] === 'REQUEST' ? 'selected' : '' ?>>Pengajuan</option>
                <option value="SCHEDULED" <?= $filters['type'] === 'SCHEDULED' ? 'selected' : '' ?>>Terjadwal</option>
            </select>
            <div class="input-group" style="max-width:190px">
                <?= icon('search') ?>
                <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Nama / ID / alasan…">
            </div>
            <input type="hidden" name="status" value="<?= e($current) ?>">
            <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
        </form>

        <form id="bulk-decide" method="post" action="<?= url('overtime/bulk-decide') ?>">
            <?= csrf_field() ?>
            <div class="filter-bar" style="border-top:0;background:#fff;border-bottom:1px solid var(--line)">
                <span class="small muted"><b><?= number_id($paginator->total) ?></b> pengajuan pada filter ini</span>
                <span class="spacer"></span>
                <input class="input" type="text" name="note" placeholder="Catatan keputusan (opsional)" style="max-width:230px;height:32px">
                <button class="btn btn-sm btn-teal" type="submit" name="decision" value="APPROVED"><?= icon('check') ?> Setujui Terpilih</button>
                <button class="btn btn-sm btn-danger" type="submit" name="decision" value="REJECTED"><?= icon('x') ?> Tolak Terpilih</button>
            </div>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th style="width:30px">
                        <input type="checkbox" id="check-all" form="bulk-decide" title="Pilih semua yang bisa diputus">
                    </th>
                    <th>Karyawan</th>
                    <th>Tanggal &amp; Jam</th>
                    <th class="num">Rencana</th>
                    <th class="num">Aktual</th>
                    <th class="num">Kompensasi</th>
                    <th>Status</th>
                    <th style="width:190px">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $isOpen = in_array((string) $row['status'], OvertimeModel::STATUS_OPEN, true);
                    $isApproved = (string) $row['status'] === 'APPROVED';
                    ?>
                    <tr>
                        <td>
                            <?php if ($isOpen): ?>
                                <input type="checkbox" name="ids[]" value="<?= (int) $row['id'] ?>" form="bulk-decide" class="row-check">
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="name-cell">
                                <?php if (!empty($row['photo'])): ?>
                                    <img class="avatar" src="<?= url('media/employees/' . basename($row['photo'])) ?>" alt="" style="object-fit:cover">
                                <?php else: ?>
                                    <span class="avatar" style="background:<?= e(avatar_color($row['email'])) ?>"><?= e(initials($row['full_name'])) ?></span>
                                <?php endif; ?>
                                <span>
                                    <b><?= e($row['full_name']) ?></b>
                                    <span class="mono"><?= e($row['employee_code']) ?></span>
                                </span>
                            </div>
                            <div class="small muted" style="font-size:10.4px;margin-top:2px">
                                <?= e($row['unit_name'] ?? '-') ?><?= $row['department_name'] ? ' · ' . e($row['department_name']) : '' ?>
                            </div>
                        </td>
                        <td class="small">
                            <b><?= tanggal($row['work_date']) ?></b>
                            <div class="mono muted" style="font-size:10.6px">
                                <?= e($row['window']) ?><?= $row['crosses_midnight'] ? ' (+1 hari)' : '' ?>
                            </div>
                            <?php if ($row['holiday_name']): ?>
                                <span class="badge badge-indigo" style="margin-top:3px"><?= icon('sun') ?> <?= e($row['holiday_name']) ?></span>
                            <?php endif; ?>
                            <span class="badge badge-<?= $row['type'] === 'SCHEDULED' ? 'indigo' : 'slate' ?>" style="margin-top:3px">
                                <?= $row['type'] === 'SCHEDULED' ? 'Terjadwal' : 'Pengajuan' ?>
                            </span>
                        </td>
                        <td class="num small">
                            <?= number_id($row['planned_hours'], 1) ?> j
                            <?php if (!empty($row['hours_capped'])): ?>
                                <div class="small" style="font-size:9.6px;color:var(--rose-600)">di atas batas</div>
                            <?php endif; ?>
                        </td>
                        <td class="num small">
                            <?php if ($row['actual_hours'] !== null): ?>
                                <b><?= number_id($row['actual_hours'], 1) ?> j</b>
                            <?php else: ?>
                                <span class="muted">—</span>
                                <?php if ($isApproved && $row['suggested_actual'] > 0): ?>
                                    <div class="muted" style="font-size:9.6px">saran <?= number_id($row['suggested_actual'], 1) ?> j</div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="num small">
                            <?php if ($isApproved): ?>
                                <b><?= rupiah($row['compensation_amount']) ?></b>
                            <?php else: ?>
                                <span class="muted" title="Estimasi bila disetujui">≈ <?= rupiah($row['estimated_amount']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($row['amount_capped'])): ?>
                                <div class="small" style="font-size:9.6px;color:var(--amber-600)" title="Kompensasi dipotong pada batas maksimum <?= rupiah($row['cap']) ?>">
                                    <?= icon('shield') ?> batas <?= rupiah($row['cap']) ?>
                                </div>
                            <?php elseif (!empty($row['above_current_cap'])): ?>
                                <div class="small" style="font-size:9.6px;color:var(--muted)"
                                     title="Disetujui sebelum kebijakan berubah; tidak dihitung ulang otomatis">
                                    di atas batas baru <?= rupiah($row['cap']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= status_badge($row['status']) ?>
                            <?php if ($row['manager_name'] && $row['status'] !== 'PENDING'): ?>
                                <div class="muted" style="font-size:9.6px;margin-top:2px">Atasan: <?= e($row['manager_name']) ?></div>
                            <?php endif; ?>
                            <?php if ($row['hr_name']): ?>
                                <div class="muted" style="font-size:9.6px">HR: <?= e($row['hr_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex" style="gap:4px;flex-wrap:wrap">
                                <?php if ($isOpen): ?>
                                    <form class="inline-form" method="post" action="<?= url('overtime/' . $row['id'] . '/decide') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="decision" value="APPROVED">
                                        <button class="btn btn-sm btn-teal" type="submit" title="Setujui (final HR)"><?= icon('check') ?></button>
                                    </form>
                                    <form class="inline-form" method="post" action="<?= url('overtime/' . $row['id'] . '/decide') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="decision" value="REJECTED">
                                        <button class="btn btn-sm btn-danger" type="submit" title="Tolak"><?= icon('x') ?></button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($row['status'] !== 'CANCELLED'): ?>
                                    <form class="inline-form" method="post" action="<?= url('overtime/' . $row['id'] . '/actual') ?>">
                                        <?= csrf_field() ?>
                                        <input class="input js-actual" type="number" name="actual_hours" min="0" max="12" step="0.25"
                                               value="<?= $row['actual_hours'] !== null ? e(number_format((float) $row['actual_hours'], 2, '.', '')) : '' ?>"
                                               placeholder="jam" style="width:64px;height:28px;font-size:11.4px" title="Jam lembur aktual">
                                        <button class="btn btn-sm btn-outline" type="submit" title="Simpan jam aktual"><?= icon('check') ?></button>
                                        <?php if ($row['suggested_actual'] > 0): ?>
                                            <button class="icon-btn js-fill-actual" type="button" data-value="<?= e(number_format((float) $row['suggested_actual'], 2, '.', '')) ?>" title="Isi dari presensi (<?= number_id($row['suggested_actual'], 2) ?> jam)"><?= icon('refresh') ?></button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>

                                <?php if (in_array((string) $row['status'], ['APPROVED', 'REJECTED', 'PENDING', 'MANAGER_APPROVED'], true)): ?>
                                    <form class="inline-form" method="post" action="<?= url('overtime/' . $row['id'] . '/cancel') ?>"
                                          data-confirm="Batalkan lembur <?= e($row['full_name']) ?> pada <?= tanggal($row['work_date']) ?>?">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-ghost" type="submit" title="Batalkan lembur"><?= icon('trash') ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="8">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('clock') ?></div>
                            <h3>Belum ada pengajuan lembur</h3>
                            <p>Pengajuan karyawan dan penugasan terjadwal akan tampil di sini.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card__foot flex-between wrap" style="padding:10px 16px">
            <span class="small muted">Menampilkan <?= number_id($paginator->from()) ?>–<?= number_id($paginator->to()) ?> dari <?= number_id($paginator->total) ?> pengajuan</span>
            <?php if ($paginator->lastPage() > 1): ?>
                <div class="pagination" style="padding:0">
                    <?php if ($paginator->hasPrevious()): ?><a href="<?= e($link($paginator->page - 1)) ?>"><?= icon('chevron-left') ?></a><?php endif; ?>
                    <?php foreach ($paginator->window(5) as $page): ?>
                        <?php if ($page === $paginator->page): ?>
                            <span class="active"><?= $page ?></span>
                        <?php else: ?>
                            <a href="<?= e($link($page)) ?>"><?= $page ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($paginator->hasNext()): ?><a href="<?= e($link($paginator->page + 1)) ?>"><?= icon('chevron-right') ?></a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head">
                <div><h2>Kebijakan Lembur</h2>
                    <div class="sub">Approver HR menentukan batas jam &amp; kompensasi maksimum.</div></div>
                <span class="badge badge-<?= $policy['enabled'] ? 'teal' : 'rose' ?>"><?= $policy['enabled'] ? 'Aktif' : 'Nonaktif' ?></span>
            </div>
            <div class="card__body">
                <div class="ot-policy">
                    <div><span class="k">Upah per jam</span><span class="v">Gaji pokok ÷ <?= number_id($policy['divisor']) ?></span></div>
                    <div><span class="k">Maks / hari</span><span class="v"><?= number_id($policy['max_hours']) ?> jam</span></div>
                    <div><span class="k">Jam pertama</span><span class="v">×<?= number_id($policy['first'], 1) ?></span></div>
                    <div><span class="k">Jam berikutnya</span><span class="v">×<?= number_id($policy['next'], 1) ?></span></div>
                    <div><span class="k">Hari libur</span><span class="v">×<?= number_id($policy['holiday'], 1) ?></span></div>
                    <div><span class="k">Min / pengajuan</span><span class="v"><?= number_id($policy['min_minutes']) ?> menit</span></div>
                    <div style="grid-column:1 / -1;<?= $policy['max_amount'] > 0 ? 'border-color:var(--teal-300, #5eead4);background:#f5fffd' : '' ?>">
                        <span class="k">Kompensasi maksimum / pengajuan</span>
                        <span class="v"><?= $policy['max_amount'] > 0 ? rupiah($policy['max_amount']) : 'Tanpa batas' ?></span>
                    </div>
                </div>

                <?php if ($policy['max_amount'] > 0): ?>
                    <div class="alert alert-info" style="margin-top:10px;padding:9px 11px">
                        <?= icon('shield') ?>
                        <div class="small">Nominal kompensasi dihitung ulang saat approval, lalu <b>dipotong maksimum <?= rupiah($policy['max_amount']) ?></b> per pengajuan. Nilai lebih besar tetap tercatat sebagai estimasi sebelum pemotongan.</div>
                    </div>
                <?php endif; ?>

                <div class="divider"></div>
                <div class="small" style="font-weight:700;margin-bottom:6px">Simulasi (gaji <?= rupiah(10000000) ?>)</div>
                <div class="table-wrap" style="border:1px solid var(--line);border-radius:10px">
                    <table class="data">
                        <thead><tr><th>Durasi</th><th class="num">Hari Kerja</th><th class="num">Hari Libur</th></tr></thead>
                        <tbody>
                        <?php foreach ($preview as $line): ?>
                            <tr>
                                <td class="small">
                                    <?= (int) $line['hours'] ?> jam
                                    <?php if (!empty($line['capped'])): ?>
                                        <span class="badge badge-amber" style="margin-left:4px">batas</span>
                                    <?php endif; ?>
                                </td>
                                <td class="num small"><?= rupiah($line['amount']) ?></td>
                                <td class="num small"><?= rupiah($line['holiday']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <details class="policy-edit" style="margin-top:12px">
                    <summary><?= icon('sliders', 'ico') ?> Ubah batas &amp; multiplier</summary>
                    <form method="post" action="<?= url('overtime/policy') ?>" class="policy-edit__body">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div class="field">
                                <label>Maksimum Jam / Hari <span class="req">*</span></label>
                                <input class="input" type="number" name="max_hours" min="1" max="12" step="0.5"
                                       value="<?= e(number_format((float) $policy['max_hours'], 1, '.', '')) ?>" required>
                                <span class="hint">Pengajuan, penugasan terjadwal, dan jam aktual di atas nilai ini ditolak.</span>
                            </div>
                            <div class="field">
                                <label>Maksimum Kompensasi (Rp)</label>
                                <input class="input" type="number" name="max_amount" min="0" max="100000000" step="50000"
                                       value="<?= e((float) $policy['max_amount']) ?>">
                                <span class="hint">Isi <b>0</b> untuk tanpa batas nominal.</span>
                            </div>
                            <div class="field">
                                <label>Durasi Minimum (menit)</label>
                                <input class="input" type="number" name="min_minutes" min="0" max="720" step="5"
                                       value="<?= e((int) $policy['min_minutes']) ?>">
                            </div>
                            <div class="field">
                                <label>Pembagi Upah per Jam</label>
                                <input class="input" type="number" name="divisor" min="1" max="400" step="1"
                                       value="<?= e($policy['divisor']) ?>" required>
                                <span class="hint">Standar 173 jam/bulan.</span>
                            </div>
                            <div class="field">
                                <label>Multiplier Jam Pertama</label>
                                <input class="input" type="number" name="first" min="1" max="5" step="0.1"
                                       value="<?= e($policy['first']) ?>" required>
                            </div>
                            <div class="field">
                                <label>Multiplier Jam Berikutnya</label>
                                <input class="input" type="number" name="next" min="1" max="5" step="0.1"
                                       value="<?= e($policy['next']) ?>" required>
                            </div>
                            <div class="field">
                                <label>Multiplier Hari Libur</label>
                                <input class="input" type="number" name="holiday" min="1" max="5" step="0.1"
                                       value="<?= e($policy['holiday']) ?>" required>
                            </div>
                            <div class="field">
                                <label>Status Modul</label>
                                <label class="switch" style="height:38px">
                                    <input type="checkbox" name="enabled" value="1" <?= $policy['enabled'] ? 'checked' : '' ?>>
                                    <span class="switch__track"></span>
                                    <span>Aktifkan pengajuan lembur</span>
                                </label>
                            </div>
                        </div>
                        <div class="hint" style="margin-top:8px">
                            Perubahan langsung berlaku untuk pengajuan/persetujuan berikutnya. Lembur yang sudah
                            <b>disetujui</b> tidak dihitung ulang.
                        </div>
                        <button class="btn btn-primary btn-sm" type="submit" style="margin-top:10px"><?= icon('check') ?> Simpan Kebijakan</button>
                    </form>
                </details>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Lembur per Unit</h2>
                <div class="sub"><?= e($monthLabel) ?> · status disetujui</div></div></div>
            <div class="list-rows">
                <?php foreach ($byUnit as $unit): ?>
                    <div class="list-row">
                        <span class="avatar" style="background:<?= e(avatar_color((string) $unit['code'])) ?>"><?= e(substr((string) $unit['code'], 0, 2)) ?></span>
                        <div class="list-row__body">
                            <b><?= e($unit['name'] ?? 'Tanpa unit') ?></b>
                            <span><?= number_id($unit['people']) ?> karyawan · <?= rupiah($unit['amount']) ?></span>
                        </div>
                        <span class="badge badge-slate"><?= number_id($unit['hours'], 1) ?> j</span>
                    </div>
                <?php endforeach; ?>
                <?php if (!$byUnit): ?>
                    <div class="list-row"><div class="list-row__body"><b>Belum ada lembur disetujui</b><span>Data akan muncul setelah pengajuan disetujui HR.</span></div></div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Jam Terbanyak</h2>
                <div class="sub">5 karyawan · <?= e($monthLabel) ?></div></div></div>
            <div class="list-rows">
                <?php foreach ($top as $index => $person): ?>
                    <div class="list-row">
                        <span class="rank-no"><?= $index + 1 ?></span>
                        <div class="list-row__body">
                            <b><?= e($person['full_name']) ?></b>
                            <span><?= e($person['unit_name'] ?? '-') ?> · <?= rupiah($person['amount']) ?></span>
                        </div>
                        <span class="badge badge-teal"><?= number_id($person['hours'], 1) ?> j</span>
                    </div>
                <?php endforeach; ?>
                <?php if (!$top): ?>
                    <div class="list-row"><div class="list-row__body"><b>Belum ada data</b><span>Belum ada lembur disetujui pada periode ini.</span></div></div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script>
(function () {
    var all = document.getElementById('check-all');
    var boxes = function () { return Array.prototype.slice.call(document.querySelectorAll('.row-check')); };
    if (all) {
        all.addEventListener('change', function () {
            boxes().forEach(function (box) { box.checked = all.checked; });
        });
    }
    document.querySelectorAll('.js-fill-actual').forEach(function (button) {
        button.addEventListener('click', function () {
            var form = button.closest('form');
            var input = form ? form.querySelector('.js-actual') : null;
            if (input) {
                input.value = button.getAttribute('data-value');
                input.focus();
            }
        });
    });
})();
</script>
