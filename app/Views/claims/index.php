<?php
/**
 * Antrean klaim HR/Finance.
 * @var array $claims, $paginator, $filters, $stats, $categories, $units, $departments, $statuses, $tones
 * @var callable $pageLink
 * @var bool $canDecide, $canPay
 */
$badge = static fn (string $status): string => '<span class="badge badge-' . ($tones[$status] ?? 'slate') . '">'
    . e($statuses[$status] ?? $status) . '</span>';
$exportQuery = http_build_query(array_filter([
    'status'     => $filters['status'] ?: null,
    'category'   => $filters['category'] ?: null,
    'unit'       => $filters['unit'] ?: null,
    'department' => $filters['department'] ?: null,
    'month'      => $filters['month'] ?: null,
    'q'          => $filters['q'] ?: null,
], static fn ($v) => $v !== null && $v !== ''));
?>
<div class="page-head">
    <div>
        <h1>Klaim &amp; Reimbursement</h1>
        <div class="sub">Antrean penggantian biaya karyawan — persetujuan bertingkat dan integrasi otomatis ke payroll.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('claims/export' . ($exportQuery ? '?' . $exportQuery : '')) ?>"><?= icon('download') ?> Export CSV</a>
        <?php if (\App\Core\Auth::hasRole('HR')): ?>
            <a class="btn btn-primary" href="<?= url('claims/categories') ?>"><?= icon('sliders') ?> Kategori Klaim</a>
        <?php endif; ?>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Menunggu Atasan</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($stats['pending']) ?></span>
        <span class="s">tahap 1 persetujuan</span>
    </div>
    <div class="mini-stat">
        <span class="k">Menunggu HR/Finance</span>
        <span class="v" style="color:var(--indigo-600)"><?= number_id($stats['waiting_final']) ?></span>
        <span class="s">tahap akhir</span>
    </div>
    <div class="mini-stat">
        <span class="k">Siap Dibayar</span>
        <span class="v"><?= number_id($stats['approved']) ?></span>
        <span class="s"><?= rupiah($stats['approved_amount']) ?> disetujui</span>
    </div>
    <div class="mini-stat">
        <span class="k">Masuk Payroll</span>
        <span class="v"><?= number_id($stats['scheduled']) ?></span>
        <span class="s">menunggu periode dibayar</span>
    </div>
    <div class="mini-stat">
        <span class="k">Dibayarkan Bulan Ini</span>
        <span class="v" style="font-size:15px;color:var(--teal-700)"><?= rupiah($stats['paid_month_amount']) ?></span>
        <span class="s">total klaim bulan berjalan <?= rupiah($stats['month_amount']) ?></span>
    </div>
</div>

<section class="card">
    <div class="card__head">
        <div><h2>Daftar Klaim</h2>
            <div class="sub"><?= number_id($paginator->total) ?> klaim sesuai filter. Prioritas: menunggu atasan → menunggu HR/Finance.</div></div>
    </div>

    <form class="filter-bar" method="get" action="<?= url('claims') ?>">
        <select class="select" name="status" data-auto-submit style="min-width:170px">
            <option value="OPEN" <?= $filters['status'] === 'OPEN' ? 'selected' : '' ?>>Semua yang berjalan</option>
            <option value="" <?= $filters['status'] === '' ? 'selected' : '' ?>>Semua status</option>
            <?php foreach ($statuses as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="select" name="category" data-auto-submit style="min-width:160px">
            <option value="">Semua kategori</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>" <?= (int) $filters['category'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="select" name="unit" data-auto-submit style="min-width:140px">
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
        <div class="input-group" style="max-width:170px">
            <?= icon('calendar') ?>
            <input class="input" type="month" name="month" value="<?= e($filters['month']) ?>" title="Bulan transaksi">
        </div>
        <div class="input-group" style="max-width:180px">
            <?= icon('search') ?>
            <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="No. klaim / nama…">
        </div>
        <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
        <?php if ($exportQuery): ?>
            <a class="btn btn-sm btn-ghost" href="<?= url('claims') ?>"><?= icon('x') ?> Reset</a>
        <?php endif; ?>
    </form>

    <?php if ($canDecide): ?>
    <form method="post" action="<?= url('claims/bulk-decide') ?>" id="bulk-claims">
        <?= csrf_field() ?>
    <?php endif; ?>

        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <?php if ($canDecide): ?><th style="width:34px"><input type="checkbox" data-check-all="#bulk-claims"></th><?php endif; ?>
                    <th>No. Klaim</th><th>Karyawan</th><th>Kategori</th><th>Tanggal</th>
                    <th class="num">Nominal</th><th>Status</th><th>Persetujuan</th><th style="width:70px"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($claims as $claim): ?>
                    <tr>
                        <?php if ($canDecide): ?>
                            <td>
                                <?php if (in_array($claim['status'], ['PENDING', 'MANAGER_APPROVED'], true)): ?>
                                    <input type="checkbox" name="ids[]" value="<?= (int) $claim['id'] ?>">
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td>
                            <a href="<?= url('claims/' . $claim['id']) ?>"><b class="mono" style="font-size:10.4px"><?= e($claim['claim_number']) ?></b></a>
                            <div class="muted small" style="font-size:10px;max-width:190px"><?= e($claim['description']) ?></div>
                        </td>
                        <td class="small">
                            <b><?= e($claim['full_name']) ?></b>
                            <div class="muted mono" style="font-size:9.6px"><?= e($claim['employee_code']) ?></div>
                        </td>
                        <td class="small">
                            <?= e($claim['category_name']) ?>
                            <div class="muted" style="font-size:9.8px"><?= e($claim['department_name'] ?? '-') ?></div>
                        </td>
                        <td class="small"><?= tanggal($claim['claim_date']) ?></td>
                        <td class="num small"><b><?= rupiah($claim['amount']) ?></b></td>
                        <td>
                            <?= $badge((string) $claim['status']) ?>
                            <?php if ($claim['slip_number']): ?>
                                <div class="muted mono" style="font-size:9.4px;margin-top:2px"><?= e($claim['slip_number']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small muted">
                            <?php if ($claim['manager_name']): ?>
                                <div title="Atasan"><?= icon('user', 'ico') ?> <?= e($claim['manager_name']) ?></div>
                            <?php endif; ?>
                            <?php if ($claim['approver_name']): ?>
                                <div title="HR/Finance"><?= icon('check', 'ico') ?> <?= e($claim['approver_name']) ?></div>
                            <?php endif; ?>
                            <?php if (!$claim['manager_name'] && !$claim['approver_name']): ?>—<?php endif; ?>
                        </td>
                        <td>
                            <a class="btn btn-sm btn-ghost" href="<?= url('claims/' . $claim['id']) ?>" title="Detail"><?= icon('arrow-right') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$claims): ?>
                    <tr><td colspan="<?= $canDecide ? 9 : 8 ?>">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('wallet') ?></div>
                            <h3>Tidak ada klaim pada filter ini</h3>
                            <p>Longgarkan filter status atau pilih "Semua status".</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php if ($canDecide): ?>
        <div class="card__body flex-between wrap" style="border-top:1px solid var(--line);gap:10px">
            <span class="small muted">Centang klaim yang ingin diproses sekaligus (status menunggu atasan/HR).</span>
            <div class="flex" style="gap:6px">
                <button class="btn btn-sm btn-primary" type="submit" name="decision" value="APPROVED"><?= icon('check') ?> Setujui Terpilih</button>
                <button class="btn btn-sm btn-ghost" type="submit" name="decision" value="REJECTED"
                        style="color:var(--rose-600)"><?= icon('x') ?> Tolak Terpilih</button>
            </div>
        </div>
    </form>
    <?php endif; ?>

    <div class="card__foot flex-between wrap" style="padding:10px 16px">
        <span class="small muted">Menampilkan <?= number_id($paginator->from()) ?>–<?= number_id($paginator->to()) ?> dari <?= number_id($paginator->total) ?> klaim</span>
        <?php if ($paginator->lastPage() > 1): ?>
            <div class="pagination" style="padding:0">
                <?php if ($paginator->hasPrevious()): ?><a href="<?= e(($pageLink)($paginator->page - 1)) ?>"><?= icon('chevron-left') ?></a><?php endif; ?>
                <?php foreach ($paginator->window(5) as $page): ?>
                    <?php if ($page === $paginator->page): ?>
                        <span class="active"><?= $page ?></span>
                    <?php else: ?>
                        <a href="<?= e(($pageLink)($page)) ?>"><?= $page ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($paginator->hasNext()): ?><a href="<?= e(($pageLink)($paginator->page + 1)) ?>"><?= icon('chevron-right') ?></a><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
