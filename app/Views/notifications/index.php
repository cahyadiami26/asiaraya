<?php
/**
 * Inbox notifikasi in-app.
 * @var array $items, $filters, $types, $counts, $paginator
 * @var callable $pageLink
 */
use App\Models\NotificationModel;

$iconFor = static fn (string $type): string => NotificationModel::meta($type)['icon'];
$toneFor = static fn (string $type): string => NotificationModel::meta($type)['tone'];
?>
<div class="page-head">
    <div>
        <h1>Notifikasi</h1>
        <div class="sub">Pemberitahuan persetujuan, payroll, klaim, dan pengumuman perusahaan.</div>
    </div>
    <div class="page-head__actions">
        <?php if ((int) $counts['unread'] > 0): ?>
            <form method="post" action="<?= url('notifications/read') ?>"><?= csrf_field() ?>
                <button class="btn btn-outline" type="submit"><?= icon('check') ?> Tandai Semua Dibaca</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Belum Dibaca</span>
        <span class="v" style="color:var(--rose-600)"><?= number_id($counts['unread']) ?></span>
        <span class="s">perlu perhatian</span>
    </div>
    <div class="mini-stat">
        <span class="k">Total Notifikasi</span>
        <span class="v"><?= number_id($counts['total']) ?></span>
        <span class="s">tersimpan di inbox</span>
    </div>
    <div class="mini-stat">
        <span class="k">Persetujuan</span>
        <span class="v"><?= number_id($counts['approval'] ?? 0) ?></span>
        <span class="s">cuti, lembur, klaim, jadwal</span>
    </div>
    <div class="mini-stat">
        <span class="k">Payroll &amp; Pengumuman</span>
        <span class="v"><?= number_id(($counts['payroll'] ?? 0) + ($counts['announcement'] ?? 0)) ?></span>
        <span class="s">slip gaji &amp; pengumuman</span>
    </div>
</div>

<section class="card">
    <div class="card__head">
        <div><h2>Inbox</h2>
            <div class="sub"><?= number_id($paginator->total) ?> notifikasi<?= $filters['unread'] ? ' belum dibaca' : '' ?>.</div></div>
    </div>

    <form class="filter-bar" method="get" action="<?= url('notifications') ?>">
        <div class="input-group" style="max-width:250px">
            <?= icon('search') ?>
            <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Cari notifikasi…">
        </div>
        <select class="select" name="type" data-auto-submit style="min-width:170px">
            <option value="">Semua tipe</option>
            <?php foreach ($types as $key => $meta): ?>
                <option value="<?= e($key) ?>" <?= $filters['type'] === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="switch-inline">
            <input type="checkbox" name="unread" value="1" <?= $filters['unread'] ? 'checked' : '' ?> data-auto-submit>
            <span>Hanya belum dibaca</span>
        </label>
        <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
        <?php if ($filters['q'] || $filters['type'] || $filters['unread']): ?>
            <a class="btn btn-sm btn-ghost" href="<?= url('notifications') ?>"><?= icon('x') ?> Reset</a>
        <?php endif; ?>
    </form>

    <div class="notif-list">
        <?php foreach ($items as $n): ?>
            <?php $unread = !$n['read_at']; ?>
            <div class="notif-item<?= $unread ? ' is-unread' : '' ?>">
                <span class="notif-item__icon <?= e($toneFor((string) $n['type'])) ?>"><?= icon($iconFor((string) $n['type'])) ?></span>
                <div class="notif-item__body">
                    <b><?= e($n['title']) ?></b>
                    <?php if (!empty($n['message'])): ?><span><?= e($n['message']) ?></span><?php endif; ?>
                    <div class="notif-item__meta">
                        <span><?= e(NotificationModel::meta((string) $n['type'])['label']) ?></span>
                        <span>·</span>
                        <span><?= waktu_lalu($n['created_at']) ?></span>
                    </div>
                </div>
                <div class="notif-item__actions">
                    <?php if ($unread): ?><span class="chip chip-dot">Baru</span><?php endif; ?>
                    <?php if (!empty($n['link'])): ?>
                        <form method="post" action="<?= url('notifications/' . (int) $n['id'] . '/read') ?>"><?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline" type="submit"><?= icon('arrow-right') ?> Buka</button>
                        </form>
                    <?php elseif ($unread): ?>
                        <form method="post" action="<?= url('notifications/' . (int) $n['id'] . '/read') ?>"><?= csrf_field() ?>
                            <button class="btn btn-sm btn-ghost" type="submit"><?= icon('check') ?> Tandai</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!$items): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= icon('bell') ?></div>
                <h3>Tidak ada notifikasi</h3>
                <p><?= $filters['unread'] ? 'Semua notifikasi sudah dibaca.' : 'Semua pemberitahuan akan muncul di sini.' ?></p>
            </div>
        <?php endif; ?>
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
