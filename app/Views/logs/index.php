<?php
/**
 * Log aktivitas sistem.
 * @var array $logs, $filters, $groups, $stats
 * @var \App\Core\Paginator $paginator
 * @var int $retention
 */
$baseQuery = array_filter([
    'group' => $filters['group'] ?: null,
    'from'  => $filters['from'] ?: null,
    'to'    => $filters['to'] ?: null,
    'q'     => $filters['q'] ?: null,
]);
$link = static fn (int $page): string => url('logs') . '?' . http_build_query($baseQuery + ['page' => $page]);
$exportUrl = url('logs/export') . ($baseQuery ? '?' . http_build_query($baseQuery) : '');
$groupLabel = static function (string $action) use ($groups): string {
    $prefix = explode('.', $action)[0] ?? '';
    return $groups[$prefix] ?? ucfirst($prefix ?: 'Sistem');
};
$tone = static function (string $action): string {
    if (str_contains($action, 'delete') || str_contains($action, 'purge') || str_contains($action, 'failed')) {
        return 'rose';
    }
    if (str_contains($action, 'create') || str_contains($action, 'login')) {
        return 'teal';
    }
    if (str_contains($action, 'update') || str_contains($action, 'save') || str_contains($action, 'approve')) {
        return 'indigo';
    }
    if (str_contains($action, 'export') || str_contains($action, 'calculate')) {
        return 'amber';
    }
    return 'slate';
};
?>
<div class="page-head">
    <div>
        <h1>Log Aktivitas</h1>
        <div class="sub">Jejak audit seluruh aksi pengguna: login, perubahan data, approval, payroll, dan ekspor.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= e($exportUrl) ?>"><?= icon('download') ?> Export CSV</a>
        <form method="post" action="<?= url('logs/purge') ?>"
              data-confirm="Hapus log lebih tua dari <?= (int) $retention ?> hari? Tindakan ini tidak dapat dibatalkan.">
            <?= csrf_field() ?>
            <button class="btn btn-soft" type="submit"><?= icon('trash') ?> Bersihkan &gt; <?= (int) $retention ?> hari</button>
        </form>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat"><span class="k">Total Log</span><span class="v"><?= number_id($stats['total']) ?></span><span class="s">sejak <?= $stats['oldest'] ? tanggal($stats['oldest']) : '-' ?></span></div>
    <div class="mini-stat"><span class="k">Hari Ini</span><span class="v"><?= number_id($stats['today']) ?></span><span class="s">aktivitas tercatat</span></div>
    <div class="mini-stat"><span class="k">Pengguna Aktif</span><span class="v"><?= number_id($stats['users']) ?></span><span class="s">akun pernah beraktivitas</span></div>
    <div class="mini-stat"><span class="k">Login Gagal</span><span class="v" style="color:var(--rose-600)"><?= number_id($stats['failed']) ?></span><span class="s">perlu perhatian keamanan</span></div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Riwayat Aktivitas</h2>
                <div class="sub"><?= number_id($paginator->total) ?> baris pada filter ini · retensi <?= (int) $retention ?> hari.</div></div>
        </div>

        <form class="filter-bar" method="get" action="<?= url('logs') ?>">
            <select class="select" name="group" data-auto-submit style="min-width:150px">
                <option value="">Semua modul</option>
                <?php foreach ($groups as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filters['group'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="input-group" style="max-width:150px">
                <?= icon('calendar') ?>
                <input class="input" type="date" name="from" value="<?= e($filters['from']) ?>" title="Dari tanggal">
            </div>
            <div class="input-group" style="max-width:150px">
                <?= icon('calendar') ?>
                <input class="input" type="date" name="to" value="<?= e($filters['to']) ?>" title="Sampai tanggal">
            </div>
            <div class="input-group" style="max-width:180px">
                <?= icon('search') ?>
                <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Aksi / entitas / IP…">
            </div>
            <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
            <?php if ($baseQuery): ?>
                <a class="btn btn-sm btn-ghost" href="<?= url('logs') ?>"><?= icon('x') ?> Reset</a>
            <?php endif; ?>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Entitas</th><th>Detail</th><th>IP</th></tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="small">
                            <b><?= tanggal($log['created_at'], 'd M Y') ?></b>
                            <div class="muted mono" style="font-size:9.8px"><?= tanggal($log['created_at'], 'H:i:s') ?></div>
                        </td>
                        <td class="small">
                            <?php if ($log['full_name']): ?>
                                <b><?= e($log['full_name']) ?></b>
                                <div class="muted" style="font-size:10px"><?= e($log['role'] ?? '-') ?> · <?= e($log['employee_code'] ?? '') ?></div>
                            <?php else: ?>
                                <span class="muted">Sistem</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $tone((string) $log['action']) ?>"><?= e($log['action']) ?></span>
                            <div class="muted" style="font-size:9.6px;margin-top:2px"><?= e($groupLabel((string) $log['action'])) ?></div>
                        </td>
                        <td class="small">
                            <?= e($log['entity'] ?: '-') ?>
                            <?php if ($log['entity_id']): ?><span class="mono muted">#<?= (int) $log['entity_id'] ?></span><?php endif; ?>
                        </td>
                        <td class="small" style="max-width:260px">
                            <?php if (!empty($log['meta'])): ?>
                                <details class="log-meta">
                                    <summary>Lihat</summary>
                                    <div class="mono" style="font-size:9.6px;word-break:break-all;color:var(--muted)"><?= e((string) $log['meta']) ?></div>
                                </details>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="mono small"><?= e($log['ip_address'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$logs): ?>
                    <tr><td colspan="6">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('activity') ?></div>
                            <h3>Tidak ada log pada filter ini</h3>
                            <p>Coba longgarkan rentang tanggal atau pilih "Semua modul".</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card__foot flex-between wrap" style="padding:10px 16px">
            <span class="small muted">Menampilkan <?= number_id($paginator->from()) ?>–<?= number_id($paginator->to()) ?> dari <?= number_id($paginator->total) ?> log</span>
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

    <section class="card">
        <div class="card__head"><div><h2>Aksi Terbanyak</h2>
            <div class="sub">6 aksi paling sering tercatat.</div></div></div>
        <div class="list-rows">
            <?php foreach ($stats['top'] as $row): ?>
                <div class="list-row">
                    <div class="list-row__body">
                        <b class="mono" style="font-size:11px"><?= e($row['action']) ?></b>
                        <span><?= e($groupLabel((string) $row['action'])) ?></span>
                    </div>
                    <span class="badge badge-slate"><?= number_id($row['total']) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$stats['top']): ?>
                <div class="empty-state" style="padding:14px"><h3>Belum ada aktivitas</h3></div>
            <?php endif; ?>
        </div>
        <div class="card__foot small muted" style="padding:10px 16px">
            Retensi log diatur di <a href="<?= url('settings?tab=advanced') ?>">Pengaturan → Lanjutan</a>.
        </div>
    </section>
</div>
