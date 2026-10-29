<?php
/**
 * Riwayat jabatan & mutasi — daftar HR (Batch 19).
 * @var array $rows, $filters, $stats, $types, $years, $units, $mostChanged
 * @var \App\Core\Paginator $paginator
 */
use App\Models\CareerModel;

$baseQuery = array_filter([
    'q'      => $filters['q'] ?: null,
    'type'   => $filters['type'] ?: null,
    'year'   => $filters['year'] ?: null,
    'unit'   => $filters['unit'] ?: null,
]);
$paged = static fn (int $page): string => url('careers') . '?' . http_build_query($baseQuery + ['page' => $page]);
?>
<div class="page-head">
    <div>
        <h1>Riwayat Jabatan &amp; Mutasi</h1>
        <div class="sub">Jejak karier seluruh karyawan: pengangkatan, promosi, mutasi, perpanjangan kontrak, dan penyesuaian gaji.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-primary" href="<?= url('careers/create') ?>"><?= icon('plus') ?> Catat Perubahan</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat"><span class="k">Total Catatan</span><span class="v"><?= number_id($stats['total']) ?></span><span class="s"><?= number_id($stats['employees']) ?> karyawan tercatat</span></div>
    <div class="mini-stat"><span class="k">Promosi</span><span class="v" style="color:var(--teal-700)"><?= number_id($stats['promotions']) ?></span><span class="s"><?= number_id($stats['mutations']) ?> mutasi</span></div>
    <div class="mini-stat"><span class="k">Tahun Ini</span><span class="v"><?= number_id($stats['this_year']) ?></span><span class="s">perubahan tercatat</span></div>
    <div class="mini-stat"><span class="k">Perubahan Gaji</span><span class="v"><?= number_id($stats['salary_up']) ?></span>
        <span class="s"><?= $stats['latest'] ? 'terakhir ' . tanggal((string) $stats['latest']['effective_date'], 'd M Y') : 'belum ada' ?></span></div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Linimasa Perubahan</h2>
                <div class="sub"><?= number_id($paginator->total) ?> catatan pada filter ini.</div></div>
        </div>

        <form class="filter-bar" method="get" action="<?= url('careers') ?>">
            <div class="input-group" style="max-width:220px">
                <?= icon('search') ?>
                <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Nama / NIK / nomor SK…">
            </div>
            <select class="select" name="type" data-auto-submit style="min-width:160px">
                <option value="">Semua jenis</option>
                <?php foreach ($types as $key => $meta): ?>
                    <option value="<?= e($key) ?>" <?= $filters['type'] === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="year" data-auto-submit style="min-width:110px">
                <option value="">Semua tahun</option>
                <?php foreach ($years as $year): ?>
                    <option value="<?= $year ?>" <?= $filters['year'] === $year ? 'selected' : '' ?>><?= $year ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="unit" data-auto-submit style="min-width:150px">
                <option value="">Semua unit</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= (int) $unit['id'] ?>" <?= $filters['unit'] === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
            <?php if ($baseQuery): ?><a class="btn btn-sm btn-ghost" href="<?= url('careers') ?>"><?= icon('x') ?> Reset</a><?php endif; ?>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Karyawan</th><th>Perubahan</th><th>Efektif</th><th>Posisi</th><th style="width:52px"></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <div class="name-cell">
                                <span class="avatar sm"><?= e(initials((string) $row['full_name'])) ?></span>
                                <div>
                                    <a href="<?= url('employees/' . (int) $row['employee_id']) ?>" style="font-weight:600;color:var(--ink)"><?= e($row['full_name']) ?></a>
                                    <span class="mono" style="font-size:10px"><?= e($row['employee_code']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-<?= CareerModel::typeTone((string) $row['change_type']) ?>"><?= e(CareerModel::typeLabel((string) $row['change_type'])) ?></span>
                            <?php if (!empty($row['sk_number'])): ?><div class="muted mono" style="font-size:10px;margin-top:3px">SK <?= e($row['sk_number']) ?></div><?php endif; ?>
                        </td>
                        <td class="small">
                            <b><?= tanggal((string) $row['effective_date']) ?></b>
                            <?php if (!empty($row['end_date'])): ?><div class="muted" style="font-size:10px">s.d. <?= tanggal((string) $row['end_date']) ?></div><?php endif; ?>
                        </td>
                        <td class="small">
                            <b style="font-weight:600"><?= e($row['position_name'] ?: '—') ?></b>
                            <div class="muted" style="font-size:10px"><?= e($row['department_name'] ?: '—') ?> · <?= e($row['unit_name'] ?: '—') ?></div>
                            <?php if ((float) $row['basic_salary'] > 0): ?>
                                <div class="muted" style="font-size:10px"><?= rupiah((float) $row['basic_salary']) ?>/bulan</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" action="<?= url('careers/' . (int) $row['id'] . '/delete') ?>"
                                  data-confirm="Hapus catatan riwayat <?= e(CareerModel::typeLabel((string) $row['change_type'])) ?> untuk <?= e($row['full_name']) ?>?">
                                <?= csrf_field() ?>
                                <button class="icon-btn" type="submit" title="Hapus catatan"><?= icon('trash') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="5">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('trending') ?></div>
                            <h3>Belum ada riwayat</h3>
                            <p>Catat pengangkatan awal karyawan agar linimasa karier mulai terbentuk.</p>
                            <a class="btn btn-sm btn-primary" style="margin-top:10px" href="<?= url('careers/create') ?>"><?= icon('plus') ?> Catat Perubahan</a>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card__foot flex-between wrap" style="padding:10px 16px">
            <span class="small muted">Menampilkan <?= number_id($paginator->from()) ?>–<?= number_id($paginator->to()) ?> dari <?= number_id($paginator->total) ?> catatan</span>
            <?php if ($paginator->lastPage() > 1): ?>
                <div class="pagination" style="padding:0">
                    <?php if ($paginator->hasPrevious()): ?><a href="<?= e($paged($paginator->page - 1)) ?>"><?= icon('chevron-left') ?></a><?php endif; ?>
                    <?php foreach ($paginator->window(5) as $page): ?>
                        <?php if ($page === $paginator->page): ?><span class="active"><?= $page ?></span>
                        <?php else: ?><a href="<?= e($paged($page)) ?>"><?= $page ?></a><?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($paginator->hasNext()): ?><a href="<?= e($paged($paginator->page + 1)) ?>"><?= icon('chevron-right') ?></a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head"><div><h2>Paling Dinamis</h2>
                <div class="sub">Karyawan dengan perubahan jabatan terbanyak.</div></div></div>
            <div class="list-rows">
                <?php foreach ($mostChanged as $person): ?>
                    <div class="list-row">
                        <span class="avatar sm"><?= e(initials((string) $person['full_name'])) ?></span>
                        <div class="list-row__body">
                            <b><?= e($person['full_name']) ?></b>
                            <span class="mono" style="font-size:10px"><?= e($person['employee_code']) ?> · terakhir <?= tanggal((string) $person['last_change'], 'd M Y') ?></span>
                        </div>
                        <span class="badge badge-indigo"><?= number_id($person['total']) ?>×</span>
                    </div>
                <?php endforeach; ?>
                <?php if (!$mostChanged): ?>
                    <div class="empty-state" style="padding:20px"><h3>Belum ada data</h3></div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Jenis Perubahan</h2></div></div>
            <div class="card__body small" style="padding-top:6px">
                <div class="detail-list">
                    <?php foreach ($types as $key => $meta): ?>
                        <div class="detail-list__row">
                            <span><?= icon((string) $meta['icon']) ?> <?= e($meta['label']) ?></span>
                            <b><?= e(match ($key) {
                                'INITIAL'   => 'Awal masuk',
                                'PROMOTION' => 'Naik level',
                                'MUTATION'  => 'Pindah unit',
                                'DEMOTION'  => 'Turun level',
                                'CONTRACT'  => 'Perpanjangan',
                                'SALARY'    => 'Gaji pokok',
                                default     => 'Status kerja',
                            }) ?></b>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card__foot small muted" style="padding:10px 16px;line-height:1.6">
                Mengaktifkan opsi <b>perbarui data karyawan</b> saat mencatat akan menyelaraskan unit, jabatan, status, dan gaji pokok secara otomatis.
            </div>
        </section>
    </div>
</div>
