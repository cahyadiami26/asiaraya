<?php
/**
 * Kelola pengumuman (HR).
 * @var array $items, $filters, $stats, $categories, $tones, $statuses, $statusTones, $paginator
 * @var callable $pageLink
 */
use App\Models\AnnouncementModel;
?>
<div class="page-head">
    <div>
        <h1>Kelola Pengumuman</h1>
        <div class="sub">Terbitkan informasi perusahaan ke audiens tertentu, jadwalkan, atau arsipkan bila sudah tidak relevan.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('announcements') ?>"><?= icon('eye') ?> Lihat Feed</a>
        <a class="btn btn-primary" href="<?= url('announcements/create') ?>"><?= icon('plus') ?> Tulis Pengumuman</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Terbit</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id($stats['published']) ?></span>
        <span class="s"><?= number_id($stats['pinned']) ?> ditandai penting</span>
    </div>
    <div class="mini-stat">
        <span class="k">Draf</span>
        <span class="v"><?= number_id($stats['draft']) ?></span>
        <span class="s"><?= number_id($stats['scheduled']) ?> terjadwal</span>
    </div>
    <div class="mini-stat">
        <span class="k">Diarsipkan</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($stats['archived']) ?></span>
        <span class="s">tidak tampil di feed</span>
    </div>
    <div class="mini-stat">
        <span class="k">Total Dibaca</span>
        <span class="v"><?= number_id($stats['reads']) ?></span>
        <span class="s">± <?= number_id($stats['avg_reads']) ?> per pengumuman</span>
    </div>
</div>

<section class="card">
    <div class="card__head">
        <div><h2>Daftar Pengumuman</h2>
            <div class="sub"><?= number_id($paginator->total) ?> pengumuman sesuai filter.</div></div>
    </div>

    <form class="filter-bar" method="get" action="<?= url('announcements/manage') ?>">
        <select class="select" name="status" data-auto-submit style="min-width:150px">
            <option value="">Semua status</option>
            <?php foreach ($statuses as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="select" name="category" data-auto-submit style="min-width:170px">
            <option value="">Semua kategori</option>
            <?php foreach ($categories as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $filters['category'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="input-group" style="max-width:230px">
            <?= icon('search') ?>
            <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Cari judul atau isi…">
        </div>
        <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
        <?php if ($filters['status'] || $filters['category'] || $filters['q']): ?>
            <a class="btn btn-sm btn-ghost" href="<?= url('announcements/manage') ?>"><?= icon('x') ?> Reset</a>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th>Judul</th><th>Kategori</th><th>Audiens</th><th>Status</th>
                <th>Terbit</th><th class="num">Dibaca</th><th style="width:120px"></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <a href="<?= url('announcements/' . (int) $item['id']) ?>"><b><?= e($item['title']) ?></b></a>
                        <div class="muted small" style="font-size:10px;max-width:280px">
                            <?php if ((int) $item['is_pinned'] === 1): ?><?= icon('alert', 'ico') ?> Penting · <?php endif; ?>
                            <?php
                            $plain = preg_replace('/^\s*[-*]\s+/m', '', str_replace('**', '', (string) $item['body'])) ?? '';
                            $plain = trim(preg_replace('/\s+/', ' ', $plain) ?? '');
                            ?><?= e(mb_substr($plain, 0, 90)) ?>…
                        </div>
                    </td>
                    <td><span class="chip chip-<?= $tones[$item['category']] ?? 'slate' ?>"><?= e($categories[$item['category']] ?? $item['category']) ?></span></td>
                    <td class="small"><?= e(AnnouncementModel::audienceLabel($item)) ?></td>
                    <td><span class="badge badge-<?= $statusTones[$item['status']] ?? 'slate' ?>"><?= e($statuses[$item['status']] ?? $item['status']) ?></span></td>
                    <td class="small muted">
                        <?php if ($item['publish_at']): ?>
                            <?= tanggal($item['publish_at'], 'd M Y H:i') ?>
                            <?php if ($item['publish_at'] > date('Y-m-d H:i:s')): ?>
                                <div class="chip chip-slate" style="margin-top:2px">Terjadwal</div>
                            <?php endif; ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="num small"><b><?= number_id($item['read_count']) ?></b></td>
                    <td>
                        <div class="flex" style="gap:4px;justify-content:flex-end">
                            <a class="btn btn-sm btn-ghost" href="<?= url('announcements/' . (int) $item['id'] . '/edit') ?>" title="Ubah"><?= icon('edit') ?></a>
                            <?php if ($item['status'] !== 'PUBLISHED'): ?>
                                <form method="post" action="<?= url('announcements/' . (int) $item['id'] . '/publish') ?>"><?= csrf_field() ?>
                                    <button class="btn btn-sm btn-ghost" type="submit" title="Terbitkan"><?= icon('send') ?></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= url('announcements/' . (int) $item['id'] . '/delete') ?>"
                                  onsubmit="return confirm('Hapus pengumuman ini secara permanen?');"><?= csrf_field() ?>
                                <button class="btn btn-sm btn-ghost" type="submit" title="Hapus" style="color:var(--rose-600)"><?= icon('trash') ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?>
                <tr><td colspan="7">
                    <div class="empty-state">
                        <div class="empty-icon"><?= icon('bell') ?></div>
                        <h3>Belum ada pengumuman</h3>
                        <p>Mulai dengan menulis pengumuman pertama untuk seluruh karyawan.</p>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card__foot flex-between wrap" style="padding:10px 16px">
        <span class="small muted">Menampilkan <?= number_id($paginator->from()) ?>–<?= number_id($paginator->to()) ?> dari <?= number_id($paginator->total) ?></span>
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
