<?php
/**
 * Manajemen lembur terjadwal (penugasan massal oleh HR).
 * @var array $rows, $stats, $filters, $units, $departments, $employees, $daily, $byUnit, $policy, $paginator
 * @var string $month, $monthLabel, $prevMonth, $nextMonth
 */
use App\Models\OvertimeModel;

$baseQuery = array_filter([
    'unit'       => $filters['unit'] ?: null,
    'department' => $filters['department'] ?: null,
    'employee'   => $filters['employee'] ?: null,
    'month'      => $month,
    'q'          => $filters['q'] ?: null,
]);
$link = static fn (int $page): string => url('overtime/schedules') . '?' . http_build_query($baseQuery + ['page' => $page]);

$maxHours = 1.0;
foreach ($daily as $day) {
    $maxHours = max($maxHours, (float) $day['hours']);
}
$weekendDays = count(array_filter($daily, static fn ($day) => $day['dow'] >= 6 || $day['holiday']));
?>
<div class="page-head">
    <div>
        <h1>Lembur Terjadwal</h1>
        <div class="sub">Buat penugasan lembur massal per unit, departemen, atau karyawan terpilih dalam satu rentang tanggal.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('overtime') ?>"><?= icon('clock') ?> Manajemen Lembur</a>
        <a class="btn btn-primary" href="#bulk-form" data-open-form><?= icon('calendar-check') ?> Jadwalkan Lembur</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Entri Terjadwal</span>
        <span class="v"><?= number_id($stats['scheduled']) ?></span>
        <span class="s"><?= number_id($stats['total']) ?> total lembur pada filter</span>
    </div>
    <div class="mini-stat">
        <span class="k">Jam Terjadwal</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id($stats['hours'], 1) ?></span>
        <span class="s">Disetujui &amp; siap dibayar</span>
    </div>
    <div class="mini-stat">
        <span class="k">Karyawan Terlibat</span>
        <span class="v"><?= number_id($stats['people']) ?></span>
        <span class="s">Unik pada filter aktif</span>
    </div>
    <div class="mini-stat">
        <span class="k">Kompensasi</span>
        <span class="v" style="font-size:19px"><?= rupiah($stats['amount']) ?></span>
        <span class="s">Menunggu masuk payroll</span>
    </div>
</div>

<details class="form-panel" id="bulk-form" <?= $old || !empty($_GET['jadwalkan']) ? 'open' : '' ?>>
    <summary>
        <span class="form-panel__title"><?= icon('calendar-check', 'ico') ?> Jadwalkan Lembur Massal</span>
        <span class="muted small">Maks 31 hari per penjadwalan</span>
    </summary>
    <form method="post" action="<?= url('overtime/schedules') ?>" class="form-panel__body">
        <?= csrf_field() ?>
        <div class="form-grid three">
            <div class="field">
                <label>Cakupan Karyawan</label>
                <select class="select" name="scope" id="scope-select">
                    <option value="unit" <?= ($old['scope'] ?? 'unit') === 'unit' ? 'selected' : '' ?>>Semua karyawan aktif unit</option>
                    <option value="department" <?= ($old['scope'] ?? '') === 'department' ? 'selected' : '' ?>>Semua karyawan aktif departemen</option>
                    <option value="selected" <?= ($old['scope'] ?? '') === 'selected' ? 'selected' : '' ?>>Pilih karyawan tertentu</option>
                </select>
            </div>
            <div class="field">
                <label>Unit</label>
                <select class="select" name="unit">
                    <option value="">Semua unit</option>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= (int) $unit['id'] ?>" <?= (int) ($old['unit'] ?? 0) === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Departemen</label>
                <select class="select" name="department">
                    <option value="">Semua departemen</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= (int) $dept['id'] ?>" <?= (int) ($old['department'] ?? 0) === (int) $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Tanggal Mulai <span class="req">*</span></label>
                <input class="input" type="date" name="date_from" value="<?= e($old['date_from'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="field">
                <label>Tanggal Selesai <span class="req">*</span></label>
                <input class="input" type="date" name="date_to" value="<?= e($old['date_to'] ?? date('Y-m-d')) ?>" required>
                <span class="hint">Isi sama dengan tanggal mulai untuk satu hari saja.</span>
            </div>
            <div class="field">
                <label>Alasan / Pekerjaan</label>
                <input class="input" type="text" name="reason" maxlength="255" value="<?= e($old['reason'] ?? '') ?>" placeholder="mis. Stock opname akhir bulan">
            </div>
            <div class="field">
                <label>Jam Mulai <span class="req">*</span></label>
                <input class="input" type="time" name="planned_start" id="ot-start" value="<?= e($old['planned_start'] ?? '17:00') ?>" required>
            </div>
            <div class="field">
                <label>Jam Selesai <span class="req">*</span></label>
                <input class="input" type="time" name="planned_end" id="ot-end" value="<?= e($old['planned_end'] ?? '20:00') ?>" required>
            </div>
            <div class="field">
                <label>Preset Cepat</label>
                <div class="preset-row">
                    <button class="btn btn-sm btn-outline js-preset" type="button" data-hours="1">1 jam</button>
                    <button class="btn btn-sm btn-outline js-preset" type="button" data-hours="2">2 jam</button>
                    <button class="btn btn-sm btn-outline js-preset" type="button" data-hours="3">3 jam</button>
                    <button class="btn btn-sm btn-outline js-preset" type="button" data-hours="4">4 jam</button>
                </div>
            </div>
        </div>

        <div class="field" id="employee-picker" style="margin-top:12px; display:none">
            <label>Pilih Karyawan <span class="muted small">(Ctrl/Cmd + klik untuk multi pilih)</span></label>
            <select class="select multi-select" name="employee_ids[]" multiple size="8">
                <?php $picked = array_map('intval', (array) ($old['employee_ids'] ?? [])); ?>
                <?php foreach ($employees as $person): ?>
                    <option value="<?= (int) $person['id'] ?>" <?= in_array((int) $person['id'], $picked, true) ? 'selected' : '' ?>>
                        <?= e($person['full_name']) ?> · <?= e($person['employee_code']) ?><?= $person['unit_name'] ? ' · ' . e($person['unit_name']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="divider"></div>
        <div class="flex wrap" style="gap:18px">
            <label class="checkbox"><input type="checkbox" name="skip_weekend" value="1" <?= (int) ($old['skip_weekend'] ?? 1) === 1 ? 'checked' : '' ?>> <span>Lewati Sabtu &amp; Minggu</span></label>
            <label class="checkbox"><input type="checkbox" name="skip_holiday" value="1" <?= (int) ($old['skip_holiday'] ?? 0) === 1 ? 'checked' : '' ?>> <span>Lewati hari besar nasional</span></label>
            <label class="checkbox"><input type="checkbox" name="auto_approve" value="1" <?= (int) ($old['auto_approve'] ?? 1) === 1 ? 'checked' : '' ?>> <span>Langsung disetujui (tanpa approval atasan)</span></label>
        </div>

        <div class="alert alert-info" style="margin-top:14px">
            <?= icon('info') ?>
            <div>
                Setiap karyawan menerima satu entri lembur per tanggal. Entri yang bertabrakan jam dengan lembur lain
                atau melebihi batas <b><?= number_id($policy['max_hours']) ?> jam/hari</b> akan dilewati otomatis,
                dan karyawan mendapatkan notifikasi.
            </div>
        </div>

        <div class="form-actions" style="margin-top:14px">
            <button class="btn btn-primary" type="submit"><?= icon('check') ?> Buat Penugasan Lembur</button>
            <a class="btn btn-ghost" href="<?= url('overtime/schedules') ?>">Batal</a>
        </div>
    </form>
</details>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Daftar Lembur Terjadwal</h2>
                <div class="sub"><?= e($monthLabel) ?> · <?= number_id($paginator->total) ?> entri</div></div>
            <div class="flex" style="gap:6px;align-items:center">
                <a class="btn btn-sm btn-ghost" href="<?= url('overtime/schedules?month=' . $prevMonth) ?>"><?= icon('chevron-left') ?></a>
                <span class="badge badge-slate" style="height:24px"><?= e($monthLabel) ?></span>
                <a class="btn btn-sm btn-ghost" href="<?= url('overtime/schedules?month=' . $nextMonth) ?>"><?= icon('chevron-right') ?></a>
            </div>
        </div>

        <form class="filter-bar" method="get" action="<?= url('overtime/schedules') ?>">
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
            <div class="input-group" style="max-width:190px">
                <?= icon('search') ?>
                <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Nama / ID / alasan…">
            </div>
            <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th>Karyawan</th><th>Tanggal</th><th>Jam</th>
                    <th class="num">Jam</th><th class="num">Kompensasi</th><th>Status</th><th style="width:150px">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <div class="name-cell">
                                <span class="avatar" style="background:<?= e(avatar_color($row['email'])) ?>"><?= e(initials($row['full_name'])) ?></span>
                                <span>
                                    <b><?= e($row['full_name']) ?></b>
                                    <span class="mono"><?= e($row['employee_code']) ?></span>
                                </span>
                            </div>
                            <div class="small muted" style="font-size:10.4px;margin-top:2px"><?= e($row['unit_name'] ?? '-') ?></div>
                        </td>
                        <td class="small">
                            <b><?= tanggal($row['work_date']) ?></b>
                            <div class="muted" style="font-size:10.2px"><?= e(date('l', strtotime($row['work_date']))) ?></div>
                        </td>
                        <td class="mono small"><?= e($row['window']) ?><?= $row['crosses_midnight'] ? ' +1' : '' ?></td>
                        <td class="num small">
                            <?= number_id($row['planned_hours'], 1) ?>
                            <?php if ($row['actual_hours'] !== null): ?>
                                <div class="muted" style="font-size:9.6px">aktual <?= number_id($row['actual_hours'], 1) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="num small">
                            <?= $row['compensation_amount'] > 0 ? rupiah($row['compensation_amount']) : '<span class="muted">≈ ' . rupiah($row['estimated_amount']) . '</span>' ?>
                            <?php if (!empty($row['amount_capped'])): ?>
                                <div style="font-size:9.6px;color:var(--amber-600)" title="Dipotong batas maksimum kompensasi"><?= icon('shield') ?> batas</div>
                            <?php endif; ?>
                        </td>
                        <td><?= status_badge($row['status']) ?></td>
                        <td>
                            <div class="flex" style="gap:4px;flex-wrap:wrap">
                                <?php if ($row['status'] !== 'CANCELLED'): ?>
                                    <form class="inline-form" method="post" action="<?= url('overtime/' . $row['id'] . '/actual') ?>">
                                        <?= csrf_field() ?>
                                        <input class="input" type="number" name="actual_hours" min="0" max="12" step="0.25"
                                               value="<?= $row['actual_hours'] !== null ? e(number_format((float) $row['actual_hours'], 2, '.', '')) : '' ?>"
                                               placeholder="jam" style="width:62px;height:28px;font-size:11.4px" title="Jam aktual">
                                        <button class="btn btn-sm btn-outline" type="submit" title="Simpan jam aktual"><?= icon('check') ?></button>
                                    </form>
                                    <form class="inline-form" method="post" action="<?= url('overtime/' . $row['id'] . '/cancel') ?>"
                                          data-confirm="Batalkan lembur terjadwal <?= e($row['full_name']) ?> pada <?= tanggal($row['work_date']) ?>?">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-ghost" type="submit" title="Batalkan"><?= icon('x') ?></button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted small">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="7">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('calendar-check') ?></div>
                            <h3>Belum ada lembur terjadwal</h3>
                            <p>Gunakan formulir di atas untuk menugaskan lembur massal ke satu unit atau tim.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card__foot flex-between wrap" style="padding:10px 16px">
            <span class="small muted">Menampilkan <?= number_id($paginator->from()) ?>–<?= number_id($paginator->to()) ?> dari <?= number_id($paginator->total) ?> entri</span>
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
            <div class="card__head"><div><h2>Kalender Jam Lembur</h2>
                <div class="sub"><?= e($monthLabel) ?> · <?= $weekendDays ?> hari libur/akhir pekan</div></div></div>
            <div class="card__body">
                <div class="trend-chart">
                    <?php foreach ($daily as $day): ?>
                        <?php $height = (int) round(((float) $day['hours'] / $maxHours) * 100); ?>
                        <div class="trend-bar <?= $day['dow'] >= 6 || $day['holiday'] ? 'is-weekend' : '' ?>"
                             title="<?= tanggal($day['date']) ?>: <?= number_id($day['hours'], 1) ?> jam">
                            <span class="trend-bar__fill" style="height:<?= max(2, $height) ?>%;<?= (float) $day['hours'] === 0.0 ? 'background:#e2e8f0' : '' ?>"></span>
                            <span class="trend-bar__label"><?= (int) $day['day'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Jam per Unit</h2>
                <div class="sub">Status disetujui · <?= e($monthLabel) ?></div></div></div>
            <div class="list-rows">
                <?php foreach ($byUnit as $unit): ?>
                    <div class="list-row">
                        <span class="avatar" style="background:<?= e(avatar_color((string) $unit['code'])) ?>"><?= e(substr((string) $unit['code'], 0, 2)) ?></span>
                        <div class="list-row__body">
                            <b><?= e($unit['name'] ?? 'Tanpa unit') ?></b>
                            <span><?= number_id($unit['people']) ?> karyawan · <?= rupiah($unit['amount']) ?></span>
                        </div>
                        <span class="badge badge-teal"><?= number_id($unit['hours'], 1) ?> j</span>
                    </div>
                <?php endforeach; ?>
                <?php if (!$byUnit): ?>
                    <div class="list-row"><div class="list-row__body"><b>Belum ada data</b><span>Belum ada lembur disetujui pada periode ini.</span></div></div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Ketentuan Penjadwalan</h2>
                <div class="sub">Validasi otomatis saat penugasan dibuat.</div></div></div>
            <div class="card__body small">
                <div class="ot-policy">
                    <div><span class="k">Maks per hari</span><span class="v"><?= number_id($policy['max_hours']) ?> jam</span></div>
                    <div><span class="k">Min per entri</span><span class="v"><?= number_id($policy['min_minutes']) ?> menit</span></div>
                    <div><span class="k">Tarif libur</span><span class="v">×<?= number_id($policy['holiday'], 1) ?></span></div>
                    <div><span class="k">Batas kompensasi</span><span class="v"><?= rupiah($policy['max_amount']) ?></span></div>
                </div>
                <div class="divider"></div>
                <ul style="margin:0;padding-left:16px;line-height:1.7;color:var(--muted)">
                    <li>Entri terjadwal yang disetujui otomatis masuk rekap lembur dan payroll.</li>
                    <li>Jam aktual dapat diisi HR setelah pekerjaan selesai.</li>
                    <li>Penugasan pada hari besar dihitung dengan tarif libur.</li>
                    <li>Durasi di atas <b><?= number_id($policy['max_hours']) ?> jam/hari</b> ditolak, dan kompensasi per entri dibatasi <b><?= $policy['max_amount'] > 0 ? rupiah($policy['max_amount']) : 'tanpa batas' ?></b>.</li>
                </ul>
            </div>
        </section>
    </div>
</div>

<script>
(function () {
    var scope = document.getElementById('scope-select');
    var picker = document.getElementById('employee-picker');
    function syncScope() {
        if (!scope || !picker) { return; }
        picker.style.display = scope.value === 'selected' ? '' : 'none';
    }
    if (scope) { scope.addEventListener('change', syncScope); }
    syncScope();

    var start = document.getElementById('ot-start');
    var end = document.getElementById('ot-end');
    document.querySelectorAll('.js-preset').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!start || !end) { return; }
            var base = start.value || '17:00';
            var parts = base.split(':');
            var minutes = (parseInt(parts[0], 10) * 60 + parseInt(parts[1] || '0', 10) + Math.round(parseFloat(button.dataset.hours) * 60)) % 1440;
            var hh = String(Math.floor(minutes / 60)).padStart(2, '0');
            var mm = String(minutes % 60).padStart(2, '0');
            end.value = hh + ':' + mm;
        });
    });
})();
</script>
