<?php
/** @var array $navGroups */
$companyName = setting('company_name', 'PT Asia Raya Group');
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar__brand">
        <div class="brand-mark"><?= icon('layers') ?></div>
        <div class="brand-text">
            <b><?= e(str_replace('PT ', '', $companyName)) ?></b>
            <span>Enterprise HRIS</span>
        </div>
    </div>

    <nav class="sidebar__nav" aria-label="Navigasi utama">
        <?php foreach ($navGroups as $group): ?>
            <?php if (!empty($group['label'])): ?>
                <div class="nav-group__label"><?= e($group['label']) ?></div>
            <?php endif; ?>
            <?php foreach ($group['items'] as $item): ?>
                <a class="nav-item <?= $item['active'] ? 'active' : '' ?><?= $item['ready'] ? '' : ' is-soon' ?>"
                   href="<?= e($item['url']) ?>"
                   <?= $item['ready'] ? '' : 'title="Modul ini diaktifkan pada batch berikutnya"' ?>>
                    <?= icon($item['icon']) ?>
                    <span class="nav-item__label"><?= e($item['label']) ?></span>
                    <?php if ($item['live']): ?>
                        <span class="nav-live">Live</span>
                    <?php elseif (!empty($item['badge'])): ?>
                        <span class="nav-badge"><?= (int) $item['badge'] ?></span>
                    <?php elseif (!$item['ready']): ?>
                        <span class="nav-live" style="color:#8b9bb1;background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.08)">Segera</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar__footer">
        <span>v<?= e(APP_VERSION) ?> Production</span>
        <?= icon('shield') ?>
    </div>
</aside>
