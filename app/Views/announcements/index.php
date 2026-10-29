<?php
/**
 * Feed pengumuman untuk seluruh karyawan.
 * @var array $items, $filters, $categories, $paginator
 * @var int $unreadTotal
 * @var callable $pageLink
 */
use App\Core\Auth;

$isHR = Auth::hasRole('HR');
$excerpt = static function (string $body, int $limit = 210): string {
    $text = str_replace(['**', '#'], '', $body);
    $text = preg_replace('/^\s*[-*]\s+/m', '', $text) ?? $text;
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
    return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit) . '…' : $text;
};
?>

<div class="page-head">
    <div>
        <h1>Pengumuman</h1>
        <div class="sub">Informasi resmi perusahaan, kebijakan, kegiatan, dan pengumuman payroll — sesuai unit kerja Anda.</div>
    </div>
    <div class="page-head__actions">
        <?php if ($unreadTotal > 0): ?>
            <form method="post" action="<?= url('announcements/read-all') ?>"><?= csrf_field() ?>
                <button class="btn btn-outline" type="submit"><?= icon('check') ?> Tandai Semua Dibaca (<?= number_id($unreadTotal) ?>)</button>
            </form>
        <?php endif; ?>
        <?php if ($isHR): ?>
            <a class="btn btn-primary" href="<?= url('announcements/create') ?>"><?= icon('plus') ?> Tulis Pengumuman</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($unreadTotal > 0): ?>
    <div class="announce-banner">
        <?= icon('bell') ?>
        <div>
            <b><?= number_id($unreadTotal) ?> pengumuman belum dibaca</b>
            <span>Buka pengumuman untuk menandainya sudah dibaca, atau gunakan tombol "Tandai Semua Dibaca".</span>
        </div>
        <a class="btn btn-sm btn-outline" href="<?= url('announcements?unread=1') ?>">Lihat yang belum dibaca</a>
    </div>
<?php endif; ?>

<section class="card">
    <form class="filter-bar" method="get" action="<?= url('announcements') ?>">
        <div class="input-group" style="max-width:260px">
            <?= icon('search') ?>
            <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Cari judul atau isi pengumuman…">
        </div>
        <select class="select" name="category" data-auto-submit style="min-width:180px">
            <option value="">Semua kategori</option>
            <?php foreach ($categories as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $filters['category'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="switch-inline">
            <input type="checkbox" name="unread" value="1" <?= $filters['unread'] ? 'checked' : '' ?> data-auto-submit>
            <span>Hanya belum dibaca</span>
        </label>
        <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
        <?php if ($filters['category'] || $filters['q'] || $filters['unread']): ?>
            <a class="btn btn-sm btn-ghost" href="<?= url('announcements') ?>"><?= icon('x') ?> Reset</a>
        <?php endif; ?>
    </form>

    <div class="announce-list">
        <?php foreach ($items as $item): ?>
            <?php
            $tone = \App\Models\AnnouncementModel::CATEGORY_TONES[$item['category']] ?? 'slate';
            $iconName = \App\Models\AnnouncementModel::CATEGORY_ICONS[$item['category']] ?? 'info';
            $unread = empty($item['my_read_at']);
            ?>
            <article class="announce<?= (int) $item['is_pinned'] === 1 ? ' is-pinned' : '' ?><?= $unread ? ' is-unread' : '' ?>">
                <div class="announce__icon badge-<?= $tone ?>"><?= icon($iconName) ?></div>
                <div class="announce__body">
                    <div class="announce__meta">
                        <span class="chip chip-<?= $tone ?>"><?= e($categories[$item['category']] ?? $item['category']) ?></span>
                        <?php if ((int) $item['is_pinned'] === 1): ?>
                            <span class="chip chip-rose"><?= icon('alert', 'ico') ?> Penting</span>
                        <?php endif; ?>
                        <?php if ($unread): ?><span class="chip chip-dot">Baru</span><?php endif; ?>
                        <?php if ($item['publish_at'] && $item['publish_at'] > date('Y-m-d H:i:s')): ?>
                            <span class="chip chip-slate">Terbit <?= tanggal($item['publish_at'], 'd M Y H:i') ?></span>
                        <?php endif; ?>
                    </div>
                    <h3><a href="<?= url('announcements/' . (int) $item['id']) ?>"><?= e($item['title']) ?></a></h3>
                    <p><?= e($excerpt((string) $item['body'])) ?></p>
                    <div class="announce__foot">
                        <span><?= icon('user', 'ico') ?> <?= e($item['author_name'] ?? 'HR Departemen') ?></span>
                        <span><?= icon('calendar', 'ico') ?> <?= tanggal($item['publish_at'] ?? $item['created_at'], 'd M Y') ?></span>
                        <?php if (!empty($item['attachment'])): ?>
                            <span><?= icon('file', 'ico') ?> Lampiran</span>
                        <?php endif; ?>
                        <?php if (Auth::hasRole(['HR', 'FIN'])): ?>
                            <span><?= icon('eye', 'ico') ?> <?= number_id($item['read_count']) ?> dibaca</span>
                        <?php endif; ?>
                    </div>
                </div>
                <a class="announce__go" href="<?= url('announcements/' . (int) $item['id']) ?>" aria-label="Buka pengumuman"><?= icon('arrow-right') ?></a>
            </article>
        <?php endforeach; ?>

        <?php if (!$items): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= icon('bell') ?></div>
                <h3>Belum ada pengumuman</h3>
                <p><?= $filters['unread'] || $filters['category'] || $filters['q']
                    ? 'Tidak ada pengumuman yang cocok dengan filter ini.'
                    : 'Pengumuman dari HR akan muncul di sini.' ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="card__foot flex-between wrap" style="padding:10px 16px">
        <span class="small muted">Menampilkan <?= number_id($paginator->from()) ?>–<?= number_id($paginator->to()) ?> dari <?= number_id($paginator->total) ?> pengumuman</span>
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
