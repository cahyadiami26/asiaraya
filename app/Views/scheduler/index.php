<?php
/**
 * Penjadwal tugas (cron) — Batch 18.
 * @var array $jobs, $history, $stats, $settings
 * @var string $cronLines, $cronUrl
 */
$statusTone = static fn (string $status): string => match ($status) {
    'OK'      => 'teal',
    'RUNNING' => 'amber',
    'ERROR'   => 'rose',
    default   => 'slate',
};
$statusLabel = static fn (string $status): string => match ($status) {
    'OK'      => 'Sukses',
    'RUNNING' => 'Berjalan',
    'ERROR'   => 'Gagal',
    default   => $status,
};
$sourceLabel = static fn (string $source): string => match ($source) {
    'AUTO'   => 'Otomatis',
    'CRON'   => 'Cron server',
    'MANUAL' => 'Manual',
    default  => $source,
};
$lastOf = static function (array $job): string {
    if (!$job['last']) {
        return 'belum pernah';
    }
    return tanggal((string) $job['last']['started_at'], 'd M Y H:i');
};
?>
<div class="page-head">
    <div>
        <h1>Penjadwal &amp; Cron</h1>
        <div class="sub">Job otomatis HRIS: antrean email, pengingat kontrak, pengingat sisa cuti, dan notifikasi pengumuman terjadwal.</div>
    </div>
    <div class="page-head__actions">
        <form method="post" action="<?= url('scheduler/run-all') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-teal" type="submit"><?= icon('refresh') ?> Jalankan Semua Job</button>
        </form>
        <a class="btn btn-outline" href="<?= url('mail') ?>"><?= icon('mail') ?> Antrean Email</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat"><span class="k">Total Eksekusi</span><span class="v"><?= number_id($stats['total']) ?></span><span class="s">tersimpan di cron_runs</span></div>
    <div class="mini-stat"><span class="k">Hari Ini</span><span class="v"><?= number_id($stats['today']) ?></span><span class="s">eksekusi</span></div>
    <div class="mini-stat"><span class="k">Gagal</span><span class="v" style="color:var(--rose-600)"><?= number_id($stats['failed']) ?></span><span class="s">perlu diperiksa</span></div>
    <div class="mini-stat"><span class="k">Eksekusi Terakhir</span><span class="v" style="font-size:15px"><?= $stats['last'] ? tanggal((string) $stats['last'], 'd M H:i') : '—' ?></span><span class="s"><?= $settings['auto'] ? 'mode otomatis aktif' : 'mode otomatis nonaktif' ?></span></div>
</div>

<?php if ($stats['last_error']): ?>
    <div class="alert alert-error" style="margin-bottom:14px">
        <?= icon('alert') ?>
        <div><b>Eksekusi gagal terakhir:</b> <span class="small"><?= e(mb_substr((string) $stats['last_error'], 0, 220)) ?></span></div>
    </div>
<?php endif; ?>

<div class="split-grid wide-left">
    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head"><div><h2>Daftar Job</h2>
                <div class="sub">Jalankan manual kapan saja — hasilnya tetap tercatat pada riwayat.</div></div></div>
            <div class="list-rows">
                <?php foreach ($jobs as $job): ?>
                    <div class="list-row" id="job-<?= e($job['key']) ?>" style="align-items:flex-start">
                        <span class="avatar sm" style="background:<?= $job['enabled'] ? 'var(--teal-600)' : '#94a3b8' ?>"><?= icon($job['icon']) ?></span>
                        <div class="list-row__body">
                            <b><?= e($job['label']) ?>
                                <?php if (!$job['enabled']): ?><span class="badge badge-slate" style="margin-left:6px">Nonaktif</span><?php endif; ?>
                                <?php if ($job['enabled'] && $job['due']): ?><span class="badge badge-amber" style="margin-left:6px">Jatuh tempo</span><?php endif; ?>
                            </b>
                            <span><?= e($job['desc']) ?></span>
                            <span class="muted" style="font-size:10.4px">
                                <?= e($job['interval_label']) ?> · terakhir: <?= e($lastOf($job)) ?>
                                <?php if ($job['last'] && $job['last']['status'] === 'OK'): ?>
                                    · <?= number_id((int) $job['last']['processed']) ?> data (<?= (int) $job['last']['duration_ms'] ?> ms)
                                <?php endif; ?>
                            </span>
                        </div>
                        <div style="display:flex;gap:6px;align-items:center;flex:none">
                            <?php if ($job['last']): ?>
                                <span class="badge badge-<?= $statusTone((string) $job['last']['status']) ?>"><?= e($statusLabel((string) $job['last']['status'])) ?></span>
                            <?php endif; ?>
                            <form method="post" action="<?= url('scheduler/run/' . $job['key']) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline" type="submit"><?= icon('send') ?> Jalankan</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <div><h2>Riwayat Eksekusi</h2>
                    <div class="sub">15 eksekusi terakhir beserta jumlah data yang diproses.</div></div>
                <form method="post" action="<?= url('scheduler/prune') ?>" data-confirm="Hapus riwayat lebih tua dari <?= (int) $settings['prune_days'] ?> hari?">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-ghost" type="submit"><?= icon('trash') ?> Bersihkan</button>
                </form>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                    <tr><th>Waktu</th><th>Job</th><th>Sumber</th><th class="num">Data</th><th class="num">Durasi</th><th>Status</th><th>Pesan</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $run): ?>
                        <tr>
                            <td class="small">
                                <b><?= tanggal((string) $run['started_at'], 'd M Y') ?></b>
                                <div class="muted mono" style="font-size:9.8px"><?= tanggal((string) $run['started_at'], 'H:i:s') ?></div>
                            </td>
                            <td class="small"><span class="mono" style="font-size:10.5px"><?= e($run['job']) ?></span></td>
                            <td class="small muted"><?= e($sourceLabel((string) $run['source'])) ?></td>
                            <td class="num"><?= number_id((int) $run['processed']) ?></td>
                            <td class="num muted"><?= (int) $run['duration_ms'] ?> ms</td>
                            <td><span class="badge badge-<?= $statusTone((string) $run['status']) ?>"><?= e($statusLabel((string) $run['status'])) ?></span></td>
                            <td class="small muted" style="max-width:280px"><?= e(mb_substr((string) $run['message'], 0, 140) ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$history): ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <div class="empty-icon"><?= icon('clock') ?></div>
                                <h3>Belum ada eksekusi</h3>
                                <p>Klik "Jalankan Semua Job" untuk uji coba, atau pasang cron server seperti panduan di samping.</p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head"><div><h2>Preferensi Job</h2>
                <div class="sub">Jendela pengingat dan job yang dijalankan otomatis.</div></div></div>
            <form class="card__body" method="post" action="<?= url('scheduler/settings') ?>">
                <?= csrf_field() ?>
                <div class="flex-col gap-10">
                    <?php foreach ($jobs as $job): ?>
                        <label class="switch" style="justify-content:space-between;width:100%">
                            <span>
                                <b style="display:block;font-size:12.2px"><?= e($job['label']) ?></b>
                                <span class="small muted"><?= e($job['interval_label']) ?></span>
                            </span>
                            <input type="checkbox" name="job_<?= e($job['key']) ?>" value="1" <?= $job['enabled'] ? 'checked' : '' ?>>
                            <span class="switch__track"></span>
                        </label>
                    <?php endforeach; ?>

                    <div class="divider" style="margin:4px 0"></div>

                    <label class="switch" style="justify-content:space-between;width:100%">
                        <span>
                            <b style="display:block;font-size:12.2px">Jalankan otomatis saat ada halaman dibuka</b>
                            <span class="small muted">Sapuan ringan maksimal sekali per menit (pengganti sementara cron server).</span>
                        </span>
                        <input type="checkbox" name="cron_auto" value="1" <?= $settings['auto'] ? 'checked' : '' ?>>
                        <span class="switch__track"></span>
                    </label>

                    <div class="form-grid">
                        <div class="field">
                            <label>Pengingat kontrak (H-)</label>
                            <input class="input" type="number" name="contract_lead" min="1" max="180" value="<?= (int) $settings['contract_lead'] ?>">
                        </div>
                        <div class="field">
                            <label>Pengingat cuti (H-)</label>
                            <input class="input" type="number" name="leave_lead" min="1" max="180" value="<?= (int) $settings['leave_lead'] ?>">
                        </div>
                        <div class="field">
                            <label>Batch email per eksekusi</label>
                            <input class="input" type="number" name="email_batch" min="1" max="200" value="<?= (int) $settings['email_batch'] ?>">
                        </div>
                        <div class="field">
                            <label>Retensi riwayat (hari)</label>
                            <input class="input" type="number" name="prune_days" min="7" max="730" value="<?= (int) $settings['prune_days'] ?>">
                        </div>
                    </div>

                    <label class="checkbox">
                        <input type="checkbox" name="announcement_mail" value="1" <?= $settings['announcement_mail'] ? 'checked' : '' ?>>
                        <span class="small">Kirim juga <b>email</b> saat pengumuman terjadwal diterbitkan</span>
                    </label>
                </div>
                <div class="form-actions" style="margin-top:14px">
                    <button class="btn btn-primary btn-block" type="submit"><?= icon('check') ?> Simpan Preferensi</button>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Cron Server</h2>
                <div class="sub">Cara paling andal agar job berjalan tanpa bergantung kunjungan.</div></div></div>
            <div class="card__body">
                <pre class="mono small" style="white-space:pre-wrap;background:#0f2f45;color:#d7e6f2;border-radius:9px;padding:12px;line-height:1.6;overflow:auto;margin:0"><?= e($cronLines) ?></pre>
                <p class="small muted" style="margin-top:10px;line-height:1.6">
                    Alternatif tanpa akses CLI memakai token rahasia:
                    <span class="mono" style="word-break:break-all"><?= e($cronUrl) ?>?token=<?= $settings['secret'] !== '' ? '••••••' : 'TOKEN_BELUM_DIBUAT' ?></span>
                </p>
                <form method="post" action="<?= url('scheduler/secret') ?>" data-confirm="Buat token baru? URL cron lama akan langsung tidak berlaku.">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline btn-block" type="submit"><?= icon('key') ?> <?= $settings['secret'] !== '' ? 'Ganti Token Cron' : 'Buat Token Cron' ?></button>
                </form>
                <?php if ($settings['secret'] !== ''): ?>
                    <div class="demo-box" style="margin-top:10px">
                        <summary style="font-size:10.6px;font-weight:650;color:var(--muted)">Token aktif (klik untuk menampilkan)</summary>
                        <div class="mono small" style="word-break:break-all;margin-top:6px"><?= e($settings['secret']) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Catatan Operasional</h2></div></div>
            <div class="card__body small muted" style="line-height:1.7">
                <p>Setiap job menulis satu baris pada tabel <span class="mono">cron_runs</span> — sukses, gagal, jumlah data, dan durasi.</p>
                <p>Pengingat kontrak &amp; cuti aman dijalankan berulang: email yang sama tidak dikirim dua kali dalam jendela retensi dedup.</p>
                <p>Pengingat sisa cuti hanya aktif pada jendela H-N menjelang 31 Desember.</p>
            </div>
        </section>
    </div>
</div>
