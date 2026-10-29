<?php
/** @var array|null $currentUser */
$activeUnit = setting('active_business_unit_label', 'Jakarta HQ (Pusat)');
$unread = $unreadNotifications ?? 0;
?>
<header class="topbar">
    <button type="button" class="icon-btn" data-nav-toggle aria-label="Menu" style="display:none"><?= icon('menu') ?></button>

    <form class="topbar__search" action="<?= url('search') ?>" method="get" role="search">
        <input type="search" name="q" placeholder="Cari NIK, Nama Karyawan, Departemen, atau Jabatan (Ctrl + K)…"
               aria-label="Pencarian global" value="<?= e($_GET['q'] ?? '') ?>">
        <?= icon('search') ?>
        <kbd>Ctrl K</kbd>
    </form>

    <div class="topbar__right">
        <button type="button" class="bu-switcher" data-dropdown aria-haspopup="true">
            <?= icon('building') ?>
            <span><?= e($activeUnit) ?></span>
            <?= icon('chevron-down', 'ico') ?>
        </button>

        <div class="dropdown hide-sm">
            <button type="button" class="icon-btn" data-dropdown aria-haspopup="true" aria-label="Notifikasi" data-bell>
                <?= icon('bell') ?>
                <?php if ($unread): ?>
                    <span class="icon-btn__count" data-bell-count><?= (int) $unread > 9 ? '9+' : (int) $unread ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown__menu notif-menu">
                <div class="menu-head notif-menu__head">
                    <span>Notifikasi</span>
                    <?php if ($unread): ?><span class="chip chip-dot"><?= (int) $unread ?> baru</span><?php endif; ?>
                </div>
                <div class="notif-menu__list" data-bell-list>
                    <?php foreach (($recentNotifications ?? []) as $n): ?>
                        <?php
                        $meta = \App\Models\NotificationModel::meta((string) $n['type']);
                        ?>
                        <form method="post" action="<?= url('notifications/' . (int) $n['id'] . '/read') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="notif-menu__item<?= $n['read_at'] ? '' : ' is-unread' ?>">
                                <span class="notif-menu__icon <?= e($meta['tone']) ?>"><?= icon($meta['icon']) ?></span>
                                <span class="notif-menu__text">
                                    <b><?= e($n['title']) ?></b>
                                    <i><?= e(waktu_lalu($n['created_at'])) ?></i>
                                </span>
                                <?php if (!$n['read_at']): ?><span class="notif-menu__dot"></span><?php endif; ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                    <?php if (empty($recentNotifications)): ?>
                        <div class="notif-menu__empty"><?= icon('bell') ?><span>Belum ada notifikasi.</span></div>
                    <?php endif; ?>
                </div>
                <div class="sep"></div>
                <a href="<?= url('announcements') ?>"><?= icon('info') ?> Pengumuman perusahaan</a>
                <a href="<?= url('notifications') ?>"><?= icon('list') ?> Lihat semua notifikasi</a>
            </div>
        </div>

        <div class="dropdown">
            <button type="button" class="user-chip" data-dropdown aria-haspopup="true">
                <div class="user-chip__meta">
                    <b><?= e($currentUser['full_name'] ?? 'Tamu') ?></b>
                    <span><?= e(role_label($currentUser['role'] ?? '')) ?></span>
                </div>
                <div class="avatar" style="background:<?= e(avatar_color($currentUser['email'] ?? '')) ?>">
                    <?php if (!empty($currentUser['avatar'])): ?>
                        <img src="<?= e($currentUser['avatar']) ?>" alt="">
                    <?php else: ?>
                        <?= e(initials($currentUser['full_name'] ?? 'AR')) ?>
                    <?php endif; ?>
                </div>
                <?= icon('chevron-down') ?>
            </button>
            <div class="dropdown__menu">
                <div class="menu-head">Akun Saya</div>
                <a href="<?= url('me/profile') ?>"><?= icon('user') ?> Profil Karyawan</a>
                <a href="<?= url('me/attendance') ?>"><?= icon('map-pin') ?> Presensi Saya</a>
                <a href="<?= url('me/payslip') ?>"><?= icon('receipt') ?> Slip Gaji</a>
                <div class="sep"></div>
                <form method="post" action="<?= url('logout') ?>">
                    <?= csrf_field() ?>
                    <button type="submit"><?= icon('logout') ?> Keluar dari sistem</button>
                </form>
            </div>
        </div>
    </div>
</header>
