<?php
/**
 * Penjadwalan karyawan: matriks per hari, penugasan massal, daftar rentang.
 * @var array $matrix, $units, $departments, $shifts, $assignments, $stats, $filters, $prefill, $paginator, $errors, $old
 * @var string $start, $end
 * @var int $assignTotal
 */
use App\Models\ScheduleModel;

$dayShort = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
$dayFull = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
$query = array_filter([
    'q'          => $filters['q'] ?: null,
    'unit'       => $filters['unit'] ?: null,
    'department' => $filters['department'] ?: null,
    'rows'       => ($rowLimit ?? 40) !== 40 ? ($rowLimit ?? 40) : null,
]);
$link = static fn (int $page): string => url('schedules') . '?' . http_build_query($query + ['start' => $start, 'end' => $end, 'page' => $page]);
$weekCount = count($matrix['days']);
?>
<div class="page-head">
    <div>
        <h1>Penjadwalan Karyawan</h1>
        <div class="sub">Terapkan shift per karyawan, unit, atau departemen. Perubahan satu hari otomatis memecah rentang jadwal yang ada.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('schedules/requests') ?>"><?= icon('refresh') ?> Perubahan Jadwal</a>
        <a class="btn btn-primary" href="#assign-form" data-open-form><?= icon('plus') ?> Tetapkan Jadwal</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Terjadwal Minggu Ini</span>
        <span class="v"><?= number_id($stats['scheduled']) ?> <span style="font-size:11px;color:var(--muted)">/ <?= number_id($stats['active_now']) ?></span></span>
        <span class="s"><?= number_id($stats['unscheduled']) ?> belum punya jadwal</span>
    </div>
    <div class="mini-stat">
        <span class="k">Rentang Aktif</span>
        <span class="v"><?= number_id($stats['assignments']) ?></span>
        <span class="s">Penugasan berlaku</span>
    </div>
    <div class="mini-stat">
        <span class="k">Pengajuan Menunggu</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($stats['pending']) ?></span>
        <span class="s">Perlu keputusan</span>
    </div>
    <div class="mini-stat">
        <span class="k">Rentang Tampil</span>
        <span class="v" style="font-size:14px"><?= $weekCount ?> hari</span>
        <span class="s"><?= tanggal($start) ?> – <?= tanggal($end) ?></span>
    </div>
</div>

<details class="form-panel" id="assign-form" <?= ($prefill['employee'] || $prefill['date']) ? 'open' : '' ?>>
    <summary>
        <span class="form-panel__title"><?= icon('calendar-check', 'ico') ?> Tetapkan Jadwal</span>
        <span class="muted small">Pilih target, shift, dan rentang tanggal</span>
    </summary>
    <form method="post" action="<?= url('schedules/assign') ?>" class="form-panel__body">
        <?= csrf_field() ?>
        <div class="form-grid three">
            <div class="field">
                <label>Shift <span class="req">*</span></label>
                <select class="select" name="shift_id" required>
                    <option value="">Pilih shift…</option>
                    <?php foreach ($shifts as $shift): ?>
                        <option value="<?= (int) $shift['id'] ?>" <?= (int) $prefill['shift'] === (int) $shift['id'] ? 'selected' : '' ?>>
                            <?= e($shift['name']) ?> · <?= e(substr($shift['check_in_time'], 0, 5)) ?>–<?= e(substr($shift['check_out_time'], 0, 5)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= isset($errors['shift_id']) ? '<span class="err">' . e($errors['shift_id']) . '</span>' : '' ?>
            </div>
            <div class="field">
                <label>Tanggal Mulai <span class="req">*</span></label>
                <input class="input" type="date" name="start_date" value="<?= e($prefill['date'] ?: ($old['start_date'] ?? $start)) ?>" required>
            </div>
            <div class="field">
                <label>Tanggal Akhir <span class="req">*</span></label>
                <input class="input" type="date" name="end_date" value="<?= e($prefill['date'] ?: ($old['end_date'] ?? $end)) ?>" required>
            </div>
            <div class="field">
                <label>Target Penugasan</label>
                <select class="select" name="target_mode" id="target-mode">
                    <option value="employee" <?= ($old['target_mode'] ?? 'employee') === 'employee' ? 'selected' : '' ?>>Karyawan tertentu</option>
                    <option value="unit" <?= ($old['target_mode'] ?? '') === 'unit' ? 'selected' : '' ?>>Semua karyawan satu business unit</option>
                    <option value="department" <?= ($old['target_mode'] ?? '') === 'department' ? 'selected' : '' ?>>Semua karyawan satu departemen</option>
                    <option value="all" <?= ($old['target_mode'] ?? '') === 'all' ? 'selected' : '' ?>>Seluruh karyawan aktif</option>
                </select>
            </div>
            <div class="field" data-target="unit">
                <label>Business Unit</label>
                <select class="select" name="unit_id">
                    <option value="">Pilih unit…</option>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= (int) $unit['id'] ?>"><?= e($unit['name']) ?> · <?= (int) $unit['employee_count'] ?> karyawan</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" data-target="department">
                <label>Departemen</label>
                <select class="select" name="department_id">
                    <option value="">Pilih departemen…</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= (int) $dept['id'] ?>"><?= e($dept['name']) ?> (<?= e($dept['unit_name'] ?? '-') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field full" data-target="employee">
                <label>Karyawan <span class="muted small">(Cmd/Ctrl + klik untuk memilih beberapa)</span></label>
                <select class="select" name="employee_ids[]" multiple size="5" id="employee-select">
                    <?php foreach ($matrix['rows'] as $row): ?>
                        <option value="<?= (int) $row['employee']['id'] ?>" <?= (int) $prefill['employee'] === (int) $row['employee']['id'] ? 'selected' : '' ?>>
                            <?= e($row['employee']['full_name']) ?> · <?= e($row['employee']['employee_code']) ?>
                            <?= $row['employee']['unit_name'] ? ' · ' . e($row['employee']['unit_name']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="hint">Menampilkan karyawan sesuai filter matriks di bawah (maks 120).</span>
            </div>
            <div class="field">
                <label>Catatan</label>
                <input class="input" name="note" value="<?= e($old['note'] ?? '') ?>" placeholder="Rotasi bulanan, penugasan proyek, dll.">
            </div>
            <div class="field">
                <label>Opsi</label>
                <label class="checkbox" style="margin-top:8px">
                    <input type="checkbox" name="skip_non_working" value="1" checked>
                    <span>Lewati hari non-kerja shift (Sabtu/Minggu sesuai pola)</span>
                </label>
            </div>
        </div>
        <div class="form-actions">
            <div class="hint" style="margin-right:auto">Penugasan baru menimpa jadwal pada rentang yang sama.</div>
            <button class="btn btn-primary" type="submit"><?= icon('check') ?> Terapkan Jadwal</button>
        </div>
    </form>
</details>

<section class="card" style="margin-bottom:16px">
    <form class="filter-bar" method="get" action="<?= url('schedules') ?>">
        <div class="input-group">
            <?= icon('search') ?>
            <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Cari nama atau ID karyawan…">
        </div>
        <select class="select" name="unit" data-auto-submit style="min-width:170px">
            <option value="">Semua unit</option>
            <?php foreach ($units as $unit): ?>
                <option value="<?= (int) $unit['id'] ?>" <?= (int) $filters['unit'] === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="select" name="department" data-auto-submit style="min-width:160px">
            <option value="">Semua departemen</option>
            <?php foreach ($departments as $dept): ?>
                <option value="<?= (int) $dept['id'] ?>" <?= (int) $filters['department'] === (int) $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="select" name="rows" data-auto-submit style="min-width:120px" title="Jumlah baris matriks">
            <?php foreach ([25 => '25 baris', 40 => '40 baris', 80 => '80 baris', 200 => 'Semua'] as $value => $label): ?>
                <option value="<?= $value ?>" <?= (int) ($rowLimit ?? 40) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="spacer"></span>
        <label class="small muted">Dari</label>
        <input class="input" type="date" name="start" value="<?= e($start) ?>" style="width:150px">
        <label class="small muted">s.d.</label>
        <input class="input" type="date" name="end" value="<?= e($end) ?>" style="width:150px">
        <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Tampilkan</button>
        <a class="btn btn-sm btn-ghost" href="<?= url('schedules') ?>">Reset</a>
    </form>

    <div class="matrix-wrap">
        <table class="matrix">
            <thead>
            <tr>
                <th class="matrix__sticky">Karyawan</th>
                <?php foreach ($matrix['days'] as $day): ?>
                    <?php $dow = (int) date('N', strtotime($day)); ?>
                    <th class="matrix__day <?= $dow >= 6 ? 'is-weekend' : '' ?> <?= $day === date('Y-m-d') ? 'is-today' : '' ?>">
                        <span class="matrix__dow"><?= $dayShort[$dow] ?></span>
                        <span class="matrix__date"><?= date('d/m', strtotime($day)) ?></span>
                    </th>
                <?php endforeach; ?>
                <th class="matrix__sum">Pola</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($matrix['rows'] as $row): ?>
                <?php $emp = $row['employee']; ?>
                <tr>
                    <td class="matrix__sticky">
                        <a class="matrix__person" href="<?= url('employees/' . $emp['id']) ?>">
                            <?php if (!empty($emp['photo'])): ?>
                                <img class="avatar sm" src="<?= url('media/employees/' . basename($emp['photo'])) ?>" alt="" style="object-fit:cover">
                            <?php else: ?>
                                <span class="avatar sm" style="background:<?= e(avatar_color($emp['email'])) ?>"><?= e(initials($emp['full_name'])) ?></span>
                            <?php endif; ?>
                            <span>
                                <b><?= e($emp['full_name']) ?></b>
                                <i><?= e($emp['unit_name'] ?? '-') ?><?= $emp['department_name'] ? ' · ' . e($emp['department_name']) : '' ?></i>
                            </span>
                        </a>
                    </td>
                    <?php foreach ($matrix['days'] as $day): ?>
                        <?php
                        $cell = $row['cells'][$day] ?? null;
                        $dow = (int) date('N', strtotime($day));
                        $linkHref = $cell
                            ? url('schedules?start=' . $start . '&end=' . $end . '&employee=' . $emp['id'] . '&shift=' . $cell['shift_id'] . '&date=' . $day . '#assign-form')
                            : url('schedules?start=' . $start . '&end=' . $end . '&employee=' . $emp['id'] . '&date=' . $day . '#assign-form');
                        ?>
                        <td class="matrix__cell <?= $dow >= 6 ? 'is-weekend' : '' ?>">
                            <?php if ($cell): ?>
                                <?php $isOff = !in_array($dow, ScheduleModel::workDayList($cell['work_days']), true); ?>
                                <a class="mx-chip <?= $isOff ? 'is-off' : '' ?>" href="<?= e($linkHref) ?>"
                                   title="<?= e($cell['shift_name']) ?> · <?= e(substr($cell['check_in_time'], 0, 5)) ?>–<?= e(substr($cell['check_out_time'], 0, 5)) ?>">
                                    <span class="mx-chip__bar" style="background:<?= e($cell['shift_color']) ?>"></span>
                                    <span class="mx-chip__code"><?= e($cell['shift_code']) ?></span>
                                    <span class="mx-chip__time"><?= e(substr($cell['check_in_time'], 0, 5)) ?></span>
                                </a>
                            <?php else: ?>
                                <a class="mx-empty" href="<?= e($linkHref) ?>" title="Tetapkan jadwal <?= e($emp['full_name']) ?> pada <?= tanggal($day) ?>">+</a>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="matrix__sum">
                        <?php $count = count($row['shift_ids']); ?>
                        <span class="tag-soft"><?= $count === 0 ? 'Tanpa jadwal' : $count . ' shift' ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$matrix['rows']): ?>
                <tr><td colspan="<?= count($matrix['days']) + 2 ?>" class="table-empty"><?= icon('users') ?>Tidak ada karyawan aktif pada filter ini.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card__foot small muted" style="padding:9px 16px">
        Menampilkan <?= count($matrix['rows']) ?> karyawan (maks <?= (int) ($rowLimit ?? 40) ?> baris — ubah pada filter).
        Klik sel untuk menetapkan shift pada tanggal tersebut. Chip berlatar pudar = jadwal ada, tetapi hari tersebut bukan hari kerja shift.
    </div>
</section>

<section class="card">
    <div class="card__head">
        <div><h2>Daftar Penugasan</h2>
            <div class="sub">Rentang jadwal yang masih berlaku (<?= number_id($assignTotal) ?> total).</div></div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr><th>Karyawan</th><th>Shift</th><th>Rentang</th><th>Durasi</th><th>Sumber</th><th>Catatan</th><th class="text-right">Aksi</th></tr>
            </thead>
            <tbody>
            <?php foreach ($assignments as $assignment): ?>
                <?php
                $span = (int) round((strtotime((string) $assignment['end_date']) - strtotime((string) $assignment['start_date'])) / 86400) + 1;
                $sourceLabel = ['ASSIGNED' => 'Manual', 'REQUEST' => 'Pengajuan', 'IMPORT' => 'Impor', 'TEMPLATE' => 'Template'][$assignment['source']] ?? $assignment['source'];
                ?>
                <tr>
                    <td>
                        <div class="name-cell">
                            <span class="avatar sm" style="background:<?= e(avatar_color($assignment['email'])) ?>"><?= e(initials($assignment['full_name'])) ?></span>
                            <span>
                                <b><?= e($assignment['full_name']) ?></b>
                                <span><?= e($assignment['employee_code']) ?> · <?= e($assignment['unit_name'] ?? '-') ?></span>
                            </span>
                        </div>
                    </td>
                    <td>
                        <span class="badge" style="background:<?= e($assignment['shift_color']) ?>1a;color:<?= e($assignment['shift_color']) ?>;border-color:<?= e($assignment['shift_color']) ?>33">
                            <?= e($assignment['shift_name']) ?>
                        </span>
                        <div class="small muted mono" style="margin-top:3px"><?= e(substr($assignment['check_in_time'], 0, 5)) ?>–<?= e(substr($assignment['check_out_time'], 0, 5)) ?></div>
                    </td>
                    <td class="small"><?= tanggal($assignment['start_date']) ?> – <?= tanggal($assignment['end_date']) ?></td>
                    <td class="small"><?= $span ?> hari</td>
                    <td><span class="tag-soft"><?= e($sourceLabel) ?></span></td>
                    <td class="small muted"><?= e($assignment['note'] ?: '—') ?></td>
                    <td class="text-right">
                        <form class="inline-form" method="post" action="<?= url('schedules/' . $assignment['id'] . '/delete') ?>"
                              data-confirm="Hapus penugasan ini? Jadwal karyawan pada rentang tersebut menjadi kosong.">
                            <?= csrf_field() ?><?= method_field('DELETE') ?>
                            <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)"><?= icon('trash') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$assignments): ?>
                <tr><td colspan="7" class="table-empty"><?= icon('calendar') ?>Belum ada penugasan jadwal yang berlaku.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($paginator->lastPage() > 1): ?>
        <div class="pagination">
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
</section>

<script>
(function () {
    var mode = document.getElementById('target-mode');
    if (!mode) { return; }
    function sync() {
        document.querySelectorAll('[data-target]').forEach(function (field) {
            var active = field.dataset.target === mode.value;
            field.style.display = active ? '' : 'none';
            field.querySelectorAll('select, input').forEach(function (input) {
                input.disabled = !active;
            });
        });
    }
    mode.addEventListener('change', sync);
    sync();

    document.querySelectorAll('[data-open-form]').forEach(function (link) {
        link.addEventListener('click', function () {
            var panel = document.getElementById('assign-form');
            if (panel) { panel.open = true; }
        });
    });
})();
</script>
