<?php
/**
 * Employee directory.
 * @var array $rows, $units, $departments, $statuses, $stats, $filters, $paginator
 * @var int $total
 */
$isContractView = ($filters['contract'] ?? '') === 'expiring';
$pageTitleText = $isContractView ? 'Kontrak & Status' : 'Data Karyawan';
$queryBase = array_filter([
    'q'          => $filters['q'] ?: null,
    'unit'       => $filters['unit'] ?: null,
    'department' => $filters['department'] ?: null,
    'status'     => $filters['status'] ?: null,
    'flag'       => $filters['flag'] ?: null,
    'contract'   => $isContractView ? 'expiring' : null,
    'sort'       => $filters['sort'] ?: null,
], static fn ($v) => $v !== null && $v !== '');
$link = static fn (int $page): string => url('employees') . '?' . http_build_query($queryBase + ['page' => $page]);
?>
<div class="page-head">
    <div>
        <h1><?= e($pageTitleText) ?></h1>
        <div class="sub">
            <?= $isContractView
                ? 'Karyawan aktif dengan kontrak berakhir dalam 45 hari ke depan.'
                : 'Basis data kepegawaian lengkap: identitas, penempatan, kontrak, dan data payroll.' ?>
        </div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('organisation') ?>"><?= icon('building') ?> Unit Organisasi</a>
        <a class="btn btn-primary" href="<?= url('employees/create') ?>"><?= icon('plus') ?> Tambah Karyawan</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Karyawan Aktif</span>
        <span class="v"><?= number_id($stats['active']) ?></span>
        <span class="s"><?= number_id($stats['permanent']) ?> PKWTT · <?= number_id($stats['contract']) ?> PKWT</span>
    </div>
    <div class="mini-stat">
        <span class="k">Kontrak ≤ 45 Hari</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($stats['expiring_45']) ?></span>
        <span class="s">Perlu perpanjangan</span>
    </div>
    <div class="mini-stat">
        <span class="k">Habis Kontrak</span>
        <span class="v" style="color:var(--rose-600)"><?= number_id($stats['expired']) ?></span>
        <span class="s">Belum diperpanjang</span>
    </div>
    <div class="mini-stat">
        <span class="k">Resign</span>
        <span class="v"><?= number_id($stats['resign']) ?></span>
        <span class="s">Data tetap tersimpan</span>
    </div>
</div>

<section class="card">
    <form class="filter-bar" method="get" action="<?= url('employees') ?>">
        <?php if ($isContractView): ?><input type="hidden" name="contract" value="expiring"><?php endif; ?>
        <div class="input-group">
            <?= icon('search') ?>
            <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Cari nama, ID karyawan, atau email…">
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
        <select class="select" name="status" data-auto-submit style="min-width:150px">
            <option value="">Semua status</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="select" name="flag" data-auto-submit style="min-width:130px">
            <option value="">Semua</option>
            <option value="ACTIVE" <?= $filters['flag'] === 'ACTIVE' ? 'selected' : '' ?>>Aktif</option>
            <option value="RESIGN" <?= $filters['flag'] === 'RESIGN' ? 'selected' : '' ?>>Resign</option>
            <option value="EXPIRED" <?= $filters['flag'] === 'EXPIRED' ? 'selected' : '' ?>>Habis kontrak</option>
        </select>
        <select class="select" name="sort" data-auto-submit style="min-width:150px">
            <option value="">Urut: Nama A-Z</option>
            <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Terbaru bergabung</option>
            <option value="code" <?= $filters['sort'] === 'code' ? 'selected' : '' ?>>ID karyawan</option>
            <option value="salary_desc" <?= $filters['sort'] === 'salary_desc' ? 'selected' : '' ?>>Gaji tertinggi</option>
            <option value="salary_asc" <?= $filters['sort'] === 'salary_asc' ? 'selected' : '' ?>>Gaji terendah</option>
            <option value="contract" <?= $filters['sort'] === 'contract' ? 'selected' : '' ?>>Kontrak terdekat</option>
        </select>
        <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
        <a class="btn btn-sm btn-ghost" href="<?= url($isContractView ? 'employees/contracts' : 'employees') ?>">Reset</a>
    </form>

    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th>Karyawan</th>
                <th>ID</th>
                <th>Unit / Departemen</th>
                <th>Posisi</th>
                <th>Status</th>
                <th>Kontrak</th>
                <th class="num">Gaji Pokok</th>
                <th class="text-right">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
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
                                <span><?= e($row['email']) ?></span>
                            </span>
                        </div>
                    </td>
                    <td><span class="tag-soft mono"><?= e($row['employee_code']) ?></span></td>
                    <td class="small">
                        <?= e($row['unit_name'] ?? '-') ?>
                        <div class="muted" style="font-size:10.8px"><?= e($row['department_name'] ?? '—') ?></div>
                    </td>
                    <td class="small">
                        <?= e($row['position_name'] ?? '-') ?>
                        <?php if (!empty($row['manager_name'])): ?>
                            <div class="muted" style="font-size:10.8px">Atasan: <?= e($row['manager_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-slate"><?= e($row['employment_status']) ?></span>
                        <div style="margin-top:4px"><?= flag_badge($row['flag']) ?></div>
                    </td>
                    <td class="small">
                        <?php if (!empty($row['contract_end'])): ?>
                            <?php
                            $days = (int) floor((strtotime((string) $row['contract_end']) - strtotime(date('Y-m-d'))) / 86400);
                            $tone = $days < 0 ? 'expired' : ($days <= 45 ? 'soon' : 'safe');
                            ?>
                            <div><?= tanggal($row['contract_end']) ?></div>
                            <span class="contract-pill <?= $tone ?>">
                                <?= icon($days < 0 ? 'alert' : 'clock', 'ico') ?>
                                <?= $days < 0 ? 'Lewat ' . abs($days) . ' hari' : ($days === 0 ? 'Berakhir hari ini' : $days . ' hari lagi') ?>
                            </span>
                        <?php else: ?>
                            <span class="muted">Tanpa batas</span>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= rupiah($row['basic_salary']) ?></td>
                    <td class="text-right">
                        <div class="flex" style="gap:6px;justify-content:flex-end">
                            <a class="btn btn-sm btn-outline" href="<?= url('employees/' . $row['id']) ?>"><?= icon('eye') ?> Detail</a>
                            <a class="btn btn-sm btn-ghost" href="<?= url('employees/' . $row['id'] . '/edit') ?>" title="Ubah data"><?= icon('edit') ?></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('users') ?></div>
                            <h3>Tidak ada karyawan pada filter ini</h3>
                            <p>Ubah kata kunci atau filter, atau tambahkan karyawan baru ke direktori.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card__foot flex-between wrap" style="padding:10px 16px">
        <span class="small muted">Menampilkan <?= number_id($paginator->from()) ?>–<?= number_id($paginator->to()) ?> dari <?= number_id($total) ?> karyawan</span>
        <?php if ($paginator->lastPage() > 1): ?>
            <div class="pagination" style="padding:0">
                <?php if ($paginator->hasPrevious()): ?>
                    <a href="<?= e($link($paginator->page - 1)) ?>" aria-label="Sebelumnya"><?= icon('chevron-left') ?></a>
                <?php endif; ?>
                <?php foreach ($paginator->window() as $page): ?>
                    <?php if ($page === $paginator->page): ?>
                        <span class="active"><?= $page ?></span>
                    <?php else: ?>
                        <a href="<?= e($link($page)) ?>"><?= $page ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($paginator->hasNext()): ?>
                    <a href="<?= e($link($paginator->page + 1)) ?>" aria-label="Berikutnya"><?= icon('chevron-right') ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
