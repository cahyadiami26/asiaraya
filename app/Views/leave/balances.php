<?php
/**
 * Saldo cuti per karyawan (HR).
 * @var array $rows, $stats, $filters, $types, $units, $departments, $employees, $years, $policy, $paginator
 */
$year = (int) $filters['year'];
$baseQuery = array_filter([
    'year'       => $year,
    'type'       => $filters['type'] ?: null,
    'unit'       => $filters['unit'] ?: null,
    'department' => $filters['department'] ?: null,
    'status'     => ($filters['status'] ?? '') ?: null,
    'q'          => $filters['q'] ?: null,
]);
$link = static fn (int $page): string => url('leave/balances') . '?' . http_build_query($baseQuery + ['page' => $page]);
$statusTabs = ['' => 'Semua', 'LOW' => 'Menipis (≤2)', 'NEGATIVE' => 'Minus'];
$current = $filters['status'] ?? '';
?>
<div class="page-head">
    <div>
        <h1>Saldo Cuti</h1>
        <div class="sub">Jatah, carry over, dan pemakaian cuti per karyawan untuk tahun <?= $year ?>.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('leave/types') ?>"><?= icon('settings') ?> Jenis &amp; Jatah</a>
        <a class="btn btn-primary" href="#generate-form" data-open-form><?= icon('refresh') ?> Generate Saldo Tahunan</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Baris Saldo</span>
        <span class="v"><?= number_id($stats['records']) ?></span>
        <span class="s">karyawan × jenis cuti</span>
    </div>
    <div class="mini-stat">
        <span class="k">Total Jatah</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id($stats['quota'], 1) ?></span>
        <span class="s">termasuk carry over</span>
    </div>
    <div class="mini-stat">
        <span class="k">Terpakai</span>
        <span class="v"><?= number_id($stats['used'], 1) ?></span>
        <span class="s">
            <?= $stats['quota'] > 0 ? number_id($stats['used'] / $stats['quota'] * 100, 1) : '0' ?>% dari jatah
        </span>
    </div>
    <div class="mini-stat">
        <span class="k">Perlu Perhatian</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($stats['low']) ?></span>
        <span class="s"><?= number_id($stats['negative']) ?> saldo minus</span>
    </div>
</div>

<details class="form-panel" id="generate-form" <?= $old || !empty($_GET['generate']) ? 'open' : '' ?>>
    <summary>
        <span class="form-panel__title"><?= icon('refresh', 'ico') ?> Generate Saldo Tahunan</span>
        <span class="muted small">Karyawan aktif × jenis cuti aktif</span>
    </summary>
    <form method="post" action="<?= url('leave/balances/generate') ?>" class="form-panel__body">
        <?= csrf_field() ?>
        <div class="form-grid three">
            <div class="field">
                <label>Tahun <span class="req">*</span></label>
                <input class="input" type="number" name="year" min="2000" max="2100" value="<?= e($old['year'] ?? $year) ?>" required>
            </div>
            <div class="field">
                <label>Batasi Unit</label>
                <select class="select" name="unit">
                    <option value="">Semua unit</option>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= (int) $unit['id'] ?>"><?= e($unit['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Opsi</label>
                <label class="switch" style="height:38px">
                    <input type="checkbox" name="overwrite_quota" value="1">
                    <span class="switch__track"></span>
                    <span>Setel ulang jatah baris yang sudah ada</span>
                </label>
            </div>
        </div>
        <div class="alert alert-info" style="margin-top:12px">
            <?= icon('info') ?>
            <div>Baris saldo yang sudah ada akan <b>dilewati</b> (pemakaian tidak dihapus), kecuali opsi setel ulang dipilih —
                opsi itu hanya memperbarui nilai jatah, bukan pemakaian.</div>
        </div>
        <div class="form-actions" style="margin-top:12px">
            <button class="btn btn-primary" type="submit"><?= icon('check') ?> Generate</button>
            <a class="btn btn-ghost" href="<?= url('leave/balances') ?>">Batal</a>
        </div>
    </form>
</details>

<section class="card">
    <form class="filter-bar" method="get" action="<?= url('leave/balances') ?>">
        <div class="segmented">
            <?php foreach ($statusTabs as $key => $label): ?>
                <?php $tabQuery = array_filter(array_merge($baseQuery, ['status' => $key ?: null])); ?>
                <a class="<?= $current === $key ? 'active' : '' ?>" href="<?= url('leave/balances') . '?' . http_build_query($tabQuery) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <select class="select" name="year" data-auto-submit style="min-width:104px">
            <?php foreach ($years as $option): ?>
                <option value="<?= (int) $option ?>" <?= $year === (int) $option ? 'selected' : '' ?>><?= (int) $option ?></option>
            <?php endforeach; ?>
        </select>
        <select class="select" name="type" data-auto-submit style="min-width:150px">
            <option value="">Semua jenis</option>
            <?php foreach ($types as $type): ?>
                <option value="<?= (int) $type['id'] ?>" <?= (int) $filters['type'] === (int) $type['id'] ? 'selected' : '' ?>><?= e($type['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="select" name="unit" data-auto-submit style="min-width:140px">
            <option value="">Semua unit</option>
            <?php foreach ($units as $unit): ?>
                <option value="<?= (int) $unit['id'] ?>" <?= (int) $filters['unit'] === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="select" name="department" data-auto-submit style="min-width:140px">
            <option value="">Semua departemen</option>
            <?php foreach ($departments as $dept): ?>
                <option value="<?= (int) $dept['id'] ?>" <?= (int) $filters['department'] === (int) $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="input-group" style="max-width:180px">
            <?= icon('search') ?>
            <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Nama / ID…">
        </div>
        <input type="hidden" name="status" value="<?= e($current) ?>">
        <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
    </form>

    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th>Karyawan</th><th>Unit / Departemen</th><th>Jenis Cuti</th>
                <th class="num">Jatah</th><th class="num">Carry</th><th class="num">Terpakai</th>
                <th class="num">Sisa</th><th style="width:210px">Sesuaikan</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $remaining = (float) $row['quota'] + (float) $row['carry'] - (float) $row['used']; ?>
                <tr>
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
                    </td>
                    <td class="small">
                        <?= e($row['unit_name'] ?? '-') ?>
                        <div class="muted" style="font-size:10.6px"><?= e($row['department_name'] ?? '—') ?></div>
                    </td>
                    <td class="small">
                        <span class="badge" style="background:<?= e($row['type_color']) ?>1a;color:<?= e($row['type_color']) ?>;border-color:<?= e($row['type_color']) ?>33">
                            <?= e($row['type_name']) ?>
                        </span>
                    </td>
                    <td class="num small"><?= number_id($row['quota'], 1) ?></td>
                    <td class="num small"><?= number_id($row['carry'], 1) ?></td>
                    <td class="num small"><?= number_id($row['used'], 1) ?></td>
                    <td class="num">
                        <span style="font-weight:700;color:<?= $remaining < 0 ? 'var(--rose-600)' : ($remaining <= 2 ? 'var(--amber-600)' : 'var(--teal-700)') ?>">
                            <?= number_id($remaining, 1) ?>
                        </span>
                        <div class="progress thin" style="margin-top:4px;width:52px;margin-left:auto">
                            <?php $pct = (float) $row['quota'] + (float) $row['carry'] > 0 ? max(0, min(100, $remaining / ((float) $row['quota'] + (float) $row['carry']) * 100)) : 0; ?>
                            <span style="width:<?= $pct ?>%;background:<?= $remaining < 0 ? 'var(--rose-500)' : ($remaining <= 2 ? 'var(--amber-500)' : 'var(--teal-500)') ?>"></span>
                        </div>
                    </td>
                    <td>
                        <form class="inline-form" method="post" action="<?= url('leave/balances') ?>" style="gap:3px">
                            <?= csrf_field() ?>
                            <input type="hidden" name="employee_id" value="<?= (int) $row['employee_id'] ?>">
                            <input type="hidden" name="leave_type_id" value="<?= (int) $row['leave_type_id'] ?>">
                            <input type="hidden" name="period_year" value="<?= (int) $row['period_year'] ?>">
                            <input class="input" type="number" name="quota" min="0" max="365" step="0.5"
                                   value="<?= e(number_format((float) $row['quota'], 1, '.', '')) ?>" style="width:56px;height:28px;font-size:11.4px" title="Jatah">
                            <input class="input" type="number" name="carry" min="0" max="365" step="0.5"
                                   value="<?= e(number_format((float) $row['carry'], 1, '.', '')) ?>" style="width:52px;height:28px;font-size:11.4px" title="Carry over">
                            <button class="btn btn-sm btn-outline" type="submit" title="Simpan jatah & carry"><?= icon('check') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="8">
                    <div class="empty-state">
                        <div class="empty-icon"><?= icon('layers') ?></div>
                        <h3>Belum ada saldo pada filter ini</h3>
                        <p>Gunakan <b>Generate Saldo Tahunan</b> untuk membuat saldo karyawan aktif.</p>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card__foot flex-between wrap" style="padding:10px 16px">
        <span class="small muted">Menampilkan <?= number_id($paginator->from()) ?>–<?= number_id($paginator->to()) ?> dari <?= number_id($paginator->total) ?> baris</span>
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
