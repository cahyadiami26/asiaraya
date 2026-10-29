<?php /** "Module scheduled" screen. */ ?>
<div class="page-head">
    <div>
        <h1><?= e($module) ?></h1>
        <div class="sub">Modul ini akan diaktifkan pada tahap pengembangan berikutnya.</div>
    </div>
</div>

<section class="card" style="max-width:640px;margin:24px auto">
    <div class="card__body" style="text-align:center;padding:40px 30px">
        <div class="empty-icon" style="width:58px;height:58px;margin:0 auto 14px;background:var(--teal-50);color:var(--teal-700)">
            <?= icon('layers', 'ico') ?>
        </div>
        <h2 style="font-size:19px"><?= e($module) ?></h2>
        <p class="muted mt-8" style="font-size:12.8px;max-width:420px;margin:8px auto 0">
            Halaman ini belum termasuk dalam batch yang sedang dipasang.
            <?= $batch ? 'Dijadwalkan pada <b>' . e($batch) . '</b>.' : '' ?>
            Skema database, hak akses, dan navigasi sudah disiapkan sehingga modul dapat langsung diaktifkan.
        </p>
        <div class="flex" style="justify-content:center;gap:8px;margin-top:20px">
            <a class="btn btn-primary" href="<?= url('dashboard') ?>"><?= icon('home') ?> Kembali ke Dashboard</a>
            <a class="btn btn-outline" href="<?= url('me/profile') ?>">Profil Saya</a>
        </div>
    </div>
</section>
