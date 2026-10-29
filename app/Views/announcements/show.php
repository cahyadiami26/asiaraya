<?php
/**
 * Detail pengumuman.
 * @var array $item, $categories, $tones, $statuses, $readStats, $user
 * @var string $audienceLabel
 * @var int $audienceSize
 * @var array $audienceBreakdown
 * @var bool $isHR
 */
use App\Models\AnnouncementModel;

$tone = AnnouncementModel::CATEGORY_TONES[$item['category']] ?? 'slate';
$iconName = AnnouncementModel::CATEGORY_ICONS[$item['category']] ?? 'info';
$published = $item['status'] === 'PUBLISHED';
$scheduled = $published && $item['publish_at'] && $item['publish_at'] > date('Y-m-d H:i:s');
$expired = $published && $item['expires_at'] && $item['expires_at'] < date('Y-m-d H:i:s');
$attachment = $item['attachment'] ? url('media/' . $item['attachment']) : null;
$isImage = $item['attachment'] && preg_match('/\.(jpe?g|png|webp)$/i', (string) $item['attachment']);

/** Isi pengumuman: paragraf, daftar "- ", dan penekanan **tebal**. */
$renderBody = static function (string $body): string {
    $lines = preg_split('/\R/', trim($body)) ?: [];
    $html = '';
    $inList = false;

    foreach ($lines as $line) {
        $line = rtrim($line);
        if ($line === '') {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            continue;
        }
        $safe = e($line);
        $safe = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $safe) ?? $safe;

        if (preg_match('/^\s*[-*]\s+(.*)$/u', $line, $m) === 1) {
            if (!$inList) { $html .= '<ul>'; $inList = true; }
            $itemHtml = e($m[1]);
            $itemHtml = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $itemHtml) ?? $itemHtml;
            $html .= '<li>' . $itemHtml . '</li>';
            continue;
        }
        if ($inList) { $html .= '</ul>'; $inList = false; }
        if (preg_match('/^#{1,3}\s+(.*)$/u', $line, $m) === 1) {
            $html .= '<h4>' . e($m[1]) . '</h4>';
            continue;
        }
        $html .= '<p>' . $safe . '</p>';
    }
    if ($inList) { $html .= '</ul>'; }

    return $html;
};
?>
<div class="page-head">
    <div>
        <div class="small muted" style="margin-bottom:4px">
            <a href="<?= url('announcements') ?>">Pengumuman</a>
            <?php if ($isHR): ?> / <a href="<?= url('announcements/manage') ?>">Kelola</a><?php endif; ?>
            / Detail
        </div>
        <div class="chip-row" style="margin-bottom:6px">
            <span class="chip chip-<?= $tone ?>"><?= e($categories[$item['category']] ?? $item['category']) ?></span>
            <?php if ((int) $item['is_pinned'] === 1): ?><span class="chip chip-rose"><?= icon('alert', 'ico') ?> Penting</span><?php endif; ?>
            <?php if ($isHR): ?>
                <span class="badge badge-<?= AnnouncementModel::STATUS_TONES[$item['status']] ?? 'slate' ?>"><?= e($statuses[$item['status']] ?? $item['status']) ?></span>
            <?php endif; ?>
            <?php if ($scheduled): ?><span class="chip chip-slate">Terjadwal</span><?php endif; ?>
            <?php if ($expired): ?><span class="chip chip-slate">Kedaluwarsa</span><?php endif; ?>
        </div>
        <h1 style="font-size:23px;max-width:780px;line-height:1.25"><?= e($item['title']) ?></h1>
        <div class="sub">
            <?= icon('user', 'ico') ?> <?= e($item['author_name'] ?? 'HR Departemen') ?>
            · <?= tanggal($item['publish_at'] ?? $item['created_at'], 'd M Y H:i') ?>
            · untuk <?= e($audienceLabel) ?>
        </div>
    </div>
    <div class="page-head__actions">
        <?php if ($isHR): ?>
            <a class="btn btn-outline" href="<?= url('announcements/' . (int) $item['id'] . '/edit') ?>"><?= icon('edit') ?> Ubah</a>
            <?php if ($item['status'] !== 'PUBLISHED'): ?>
                <form method="post" action="<?= url('announcements/' . (int) $item['id'] . '/publish') ?>"><?= csrf_field() ?>
                    <button class="btn btn-primary" type="submit"><?= icon('send') ?> Terbitkan</button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= url('announcements/' . (int) $item['id'] . '/archive') ?>"><?= csrf_field() ?>
                    <button class="btn btn-outline" type="submit"><?= icon('database') ?> Arsipkan</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="split-grid wide-left">
    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__body">
                <div class="article-body"><?= $renderBody((string) $item['body']) ?></div>

                <?php if ($attachment): ?>
                    <div class="divider"></div>
                    <div class="small muted" style="margin-bottom:6px">Lampiran</div>
                    <?php if ($isImage): ?>
                        <a href="<?= e($attachment) ?>" target="_blank" rel="noopener">
                            <img src="<?= e($attachment) ?>" alt="Lampiran pengumuman"
                                 style="max-width:100%;border:1px solid var(--line);border-radius:10px">
                        </a>
                    <?php else: ?>
                        <a class="btn btn-outline btn-sm" href="<?= e($attachment) ?>" target="_blank" rel="noopener">
                            <?= icon('file') ?> Buka lampiran (PDF)
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="card__foot flex-between wrap" style="padding:10px 16px">
                <span class="small muted">
                    <?= icon('check', 'ico') ?> Pengumuman ini tercatat sudah Anda baca
                </span>
                <a class="btn btn-sm btn-ghost" href="<?= url('announcements') ?>"><?= icon('arrow-left') ?> Semua pengumuman</a>
            </div>
        </section>
    </div>

    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head"><div><h2>Detail</h2></div></div>
            <div class="card__body">
                <div class="meta-list">
                    <div><span>Kategori</span><b><?= e($categories[$item['category']] ?? $item['category']) ?></b></div>
                    <div><span>Audiens</span><b><?= e($audienceLabel) ?></b></div>
                    <div><span>Jumlah penerima</span><b><?= number_id($audienceSize) ?> karyawan</b></div>
                    <?php foreach (($audienceBreakdown ?? []) as $group): ?>
                        <?php if ($group['type'] === 'ALL' || !$group['names']): continue; endif; ?>
                        <div>
                            <span><?= e($group['label']) ?></span>
                            <b style="font-weight:600;text-align:right">
                                <?= e(implode(', ', array_slice($group['names'], 0, 4))) ?><?= count($group['names']) > 4 ? ' +' . (count($group['names']) - 4) . ' lagi' : '' ?>
                            </b>
                        </div>
                    <?php endforeach; ?>
                    <div><span>Terbit</span><b><?= $item['publish_at'] ? tanggal($item['publish_at'], 'd M Y H:i') : '—' ?></b></div>
                    <div><span>Berakhir</span><b><?= $item['expires_at'] ? tanggal($item['expires_at'], 'd M Y H:i') : 'Tanpa batas' ?></b></div>
                    <div><span>Terakhir diubah</span><b><?= tanggal($item['updated_at'] ?? $item['created_at'], 'd M Y H:i') ?></b></div>
                </div>
            </div>
        </section>

        <?php if ($readStats !== null): ?>
            <section class="card">
                <div class="card__head"><div><h2>Keterbacaan</h2>
                    <div class="sub">Berapa karyawan yang sudah membuka pengumuman ini.</div></div></div>
                <div class="card__body">
                    <div class="flex-between" style="margin-bottom:6px">
                        <b style="font-size:20px"><?= number_id($readStats['reads']) ?></b>
                        <span class="small muted">dari <?= number_id($readStats['audience']) ?> penerima</span>
                    </div>
                    <div class="progress"><span style="width:<?= (int) $readStats['percent'] ?>%"></span></div>
                    <div class="small muted" style="margin-top:8px">
                        <?= (int) $readStats['percent'] ?>% sudah membaca
                        <?php if ($readStats['last']): ?> · terakhir <?= waktu_lalu($readStats['last']) ?><?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($isHR): ?>
            <section class="card">
                <div class="card__head"><div><h2>Kelola</h2></div></div>
                <div class="card__body flex-col gap-10">
                    <a class="btn btn-outline btn-sm" href="<?= url('announcements/' . (int) $item['id'] . '/edit') ?>"><?= icon('edit') ?> Ubah isi &amp; audiens</a>
                    <a class="btn btn-ghost btn-sm" href="<?= url('announcements/manage') ?>"><?= icon('list') ?> Daftar pengumuman</a>
                    <form method="post" action="<?= url('announcements/' . (int) $item['id'] . '/delete') ?>"
                          onsubmit="return confirm('Hapus pengumuman ini? Tindakan tidak dapat dibatalkan.');">
                        <?= csrf_field() ?>
                        <button class="btn btn-ghost btn-sm" type="submit" style="color:var(--rose-600)"><?= icon('trash') ?> Hapus permanen</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
