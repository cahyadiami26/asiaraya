<?php $companyName = setting('company_name', 'PT Asia Raya Group'); ?>
<div class="mobile-top">
    <button type="button" class="icon-btn" data-nav-toggle aria-label="Buka menu"><?= icon('menu') ?></button>
    <div class="brand-mark"><?= icon('layers') ?></div>
    <div class="brand-text">
        <b><?= e(str_replace('PT ', '', $companyName)) ?></b>
        <span>Enterprise HRIS</span>
    </div>
    <a class="icon-btn" href="<?= url('notifications') ?>" style="margin-left:auto" aria-label="Notifikasi">
        <?= icon('bell') ?>
        <?php if ((int) ($unreadNotifications ?? 0) > 0): ?>
            <span class="icon-btn__count"><?= (int) $unreadNotifications > 9 ? '9+' : (int) $unreadNotifications ?></span>
        <?php endif; ?>
    </a>
    <a class="icon-btn" href="<?= url('logout') ?>" aria-label="Keluar" onclick="event.preventDefault();document.getElementById('logout-form-m').submit()"><?= icon('logout') ?></a>
</div>
<form id="logout-form-m" method="post" action="<?= url('logout') ?>" class="hidden"><?= csrf_field() ?></form>
