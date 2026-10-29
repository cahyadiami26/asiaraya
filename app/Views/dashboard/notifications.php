<?php /** Notification inbox. */ ?>
<div class="page-head">
    <div><h1>Notifikasi</h1><div class="sub">Pemberitahuan pengajuan, approval, dan payroll.</div></div>
    <div class="page-head__actions">
        <form method="post" action="<?= url('notifications/read') ?>"><?= csrf_field() ?>
            <button class="btn btn-outline" type="submit"><?= icon('check') ?> Tandai Semua Dibaca</button>
        </form>
    </div>
</div>
<section class="card">
    <div class="list-rows">
        <?php foreach ($items as $n): ?>
            <div class="list-row">
                <span class="kpi__icon <?= $n['type'] === 'warning' ? 'amber' : ($n['type'] === 'danger' ? 'rose' : '') ?>">
                    <?= icon($n['type'] === 'payroll' ? 'wallet' : 'bell') ?>
                </span>
                <div class="list-row__body">
                    <b><?= e($n['title']) ?></b>
                    <span><?= e($n['message']) ?></span>
                </div>
                <div class="text-right">
                    <div class="small muted"><?= waktu_lalu($n['created_at']) ?></div>
                    <?php if (!$n['read_at']): ?><span class="badge badge-teal">Baru</span><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <div class="empty-state"><?= icon('bell', 'ico') ?><h3>Tidak ada notifikasi</h3><p>Semua pemberitahuan akan muncul di sini.</p></div>
        <?php endif; ?>
    </div>
</section>
