<?php
/**
 * Detail satu email pada antrean.
 * @var array $row, $statuses, $settings
 */
$status = (string) $row['status'];
$tone = match ($status) {
    'SENT'      => 'teal',
    'PENDING'   => 'amber',
    'FAILED'    => 'rose',
    default     => 'slate',
};
?>
<div class="page-head">
    <div>
        <h1><?= e($row['subject']) ?></h1>
        <div class="sub">Email #<?= (int) $row['id'] ?> · template <span class="mono"><?= e($row['template']) ?></span> · <?= e($statuses[$status] ?? $status) ?></div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-ghost" href="<?= url('mail') ?>"><?= icon('arrow-left') ?> Kembali ke Antrean</a>
        <?php if ($status !== 'SENT'): ?>
            <form method="post" action="<?= url('mail/' . (int) $row['id'] . '/retry') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-teal" type="submit"><?= icon('refresh') ?> Kirim Ulang</button>
            </form>
        <?php endif; ?>
        <?php if ($status === 'PENDING'): ?>
            <form method="post" action="<?= url('mail/' . (int) $row['id'] . '/cancel') ?>" data-confirm="Batalkan email ini?">
                <?= csrf_field() ?>
                <button class="btn btn-soft" type="submit"><?= icon('x') ?> Batalkan</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($status === 'FAILED' && !empty($row['error'])): ?>
    <div class="alert alert-error" style="margin-bottom:14px">
        <?= icon('alert') ?>
        <div>
            <b>Pengiriman gagal setelah <?= (int) $row['attempts'] ?> percobaan.</b><br>
            <span class="small"><?= e((string) $row['error']) ?></span>
        </div>
    </div>
<?php elseif ($status === 'PENDING'): ?>
    <div class="alert alert-info" style="margin-bottom:14px">
        <?= icon('info') ?>
        <div>Email menunggu jadwal kirim berikutnya: <b><?= $row['next_attempt_at'] ? tanggal((string) $row['next_attempt_at'], 'd M Y H:i') : 'secepatnya' ?></b>.</div>
    </div>
<?php endif; ?>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Pratinjau Email</h2>
                <div class="sub">Tampilan yang diterima penerima (HTML).</div></div>
            <span class="badge badge-<?= $tone ?>"><?= e($statuses[$status] ?? $status) ?></span>
        </div>
        <div class="card__body" style="padding:12px">
            <?php if (!empty($row['body_html'])): ?>
                <iframe title="Pratinjau email" sandbox
                        style="width:100%;height:560px;border:1px solid var(--line);border-radius:10px;background:#fff"
                        srcdoc="<?= e((string) $row['body_html']) ?>"></iframe>
            <?php else: ?>
                <div class="empty-state"><div class="empty-icon"><?= icon('mail') ?></div><h3>Isi email tidak tersimpan</h3>
                    <p>Baris antrean ini dibuat sebelum isi email disimpan.</p></div>
            <?php endif; ?>
        </div>
        <?php if (!empty($row['body_text'])): ?>
            <div class="card__foot">
                <details class="demo-box" style="margin:0">
                    <summary>Versi teks biasa (fallback)</summary>
                    <pre class="small mono" style="white-space:pre-wrap;margin:8px 0 0;max-height:240px;overflow:auto"><?= e((string) $row['body_text']) ?></pre>
                </details>
            </div>
        <?php endif; ?>
    </section>

    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head"><div><h2>Detail Pengiriman</h2></div></div>
            <div class="card__body">
                <div class="detail-list">
                    <div class="detail-list__row"><span>Penerima</span><b><?= e($row['recipient_name'] ?: '-') ?></b></div>
                    <div class="detail-list__row"><span>Alamat</span><b class="mono" style="font-size:11px"><?= e($row['recipient_email']) ?></b></div>
                    <div class="detail-list__row"><span>Karyawan</span><b><?= $row['employee_id'] ? '#' . (int) $row['employee_id'] : '—' ?></b></div>
                    <div class="detail-list__row"><span>Template</span><b class="mono" style="font-size:11px"><?= e($row['template']) ?></b></div>
                    <div class="detail-list__row"><span>Status</span><b><?= e($statuses[$status] ?? $status) ?></b></div>
                    <div class="detail-list__row"><span>Percobaan</span><b><?= (int) $row['attempts'] ?>×</b></div>
                    <div class="detail-list__row"><span>Driver</span><b><?= e($row['driver'] ?: '—') ?></b></div>
                    <div class="detail-list__row"><span>Dibuat</span><b><?= tanggal((string) $row['created_at'], 'd M Y H:i') ?></b></div>
                    <div class="detail-list__row"><span>Terkirim</span><b><?= $row['sent_at'] ? tanggal((string) $row['sent_at'], 'd M Y H:i:s') : '—' ?></b></div>
                    <div class="detail-list__row"><span>Jadwal berikut</span><b><?= $row['next_attempt_at'] ? tanggal((string) $row['next_attempt_at'], 'd M Y H:i') : '—' ?></b></div>
                </div>
                <?php if (!empty($row['error'])): ?>
                    <div style="margin-top:12px;padding:10px 12px;border-radius:9px;background:#fdf6f6;border:1px solid #f6dcdc;font-size:11.4px;color:#9a3b3b;line-height:1.6">
                        <b>Pesan kesalahan terakhir</b><br><?= e((string) $row['error']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Konfigurasi Aktif</h2></div></div>
            <div class="card__body small muted" style="line-height:1.7">
                <p>Driver: <b><?= e(\App\Core\Mailer::DRIVERS[$settings['driver']]) ?></b><?= $settings['enabled'] ? '' : ' · email dinonaktifkan' ?>.</p>
                <?php if ($settings['driver'] === 'smtp'): ?>
                    <p>Server: <span class="mono"><?= e($settings['host']) ?>:<?= (int) $settings['port'] ?> (<?= e(strtoupper($settings['encryption'])) ?>)</span></p>
                <?php endif; ?>
                <p>Pengirim: <span class="mono"><?= e($settings['from_email']) ?></span></p>
                <a class="btn btn-outline btn-sm btn-block" style="margin-top:10px" href="<?= url('settings?tab=email') ?>"><?= icon('sliders') ?> Ubah Konfigurasi</a>
            </div>
        </section>
    </div>
</div>
