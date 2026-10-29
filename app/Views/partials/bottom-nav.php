<?php if (!auth()): return; endif; ?>
<nav class="bottom-nav" aria-label="Navigasi cepat">
    <a href="<?= url('dashboard') ?>" class="<?= is_path('/dashboard') || is_path('/') ? 'active' : '' ?>"><?= icon('home') ?>Beranda</a>
    <a href="<?= url('me/attendance') ?>" class="<?= is_path('/me/attendance') ? 'active' : '' ?>"><?= icon('map-pin') ?>Presensi</a>
    <a href="<?= url('me/schedule') ?>" class="<?= is_path('/me/schedule') ? 'active' : '' ?>"><?= icon('calendar') ?>Jadwal</a>
    <a href="<?= url('me/leave') ?>" class="<?= is_path('/me/leave') ? 'active' : '' ?>"><?= icon('file') ?>Cuti</a>
    <a href="<?= url('me/payslip') ?>" class="<?= is_path('/me/payslip') ? 'active' : '' ?>"><?= icon('receipt') ?>Slip</a>
</nav>
