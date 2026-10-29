<?php
/**
 * Antrean email keluar — Batch 18.
 * @var array $rows, $filters, $stats, $templates, $statuses, $settings, $byTemplate
 * @var \App\Core\Paginator $paginator
 * @var int $maxAttempt
 */
$baseQuery = array_filter([
    'status'   => $filters['status'] ?: null,
    'template' => $filters['template'] ?: null,
    'q'        => $filters['q'] ?: null,
]);
$link = static fn (int $page): string => url('mail') . '?' . http_build_query($baseQuery + ['page' => $page]);

$statusTone = static fn (string $status): string => match ($status) {
    'SENT'      => 'teal',
    'PENDING'   => 'amber',
    'FAILED'    => 'rose',
    'CANCELLED' => 'slate',
    default     => 'slate',
};
$initials = static function (string $name, string $email): string {
    $source = trim($name) !== '' ? $name : $email;
    $parts = preg_split('/\s+/', trim($source)) ?: [];
    $first = mb_substr((string) ($parts[0] ?? $source), 0, 1);
    $second = count($parts) > 1 ? mb_substr((string) $parts[1], 0, 1) : mb_substr((string) ($parts[0] ?? ''), 1, 1);
    return mb_strtoupper($first . $second);
};
?>
<div class="page-head">
    <div>
        <h1>Antrean Email</h1>
        <div class="sub">Semua email otomatis HRIS tercatat di sini — pengingat kontrak, sisa cuti, pengumuman, dan reset kata sandi.</div>
    </div>
    <div class="page-head__actions">
        <form method="post" action="<?= url('mail/process') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-teal" type="submit"><?= icon('send') ?> Kirim Antrean Sekarang</button>
        </form>
        <a class="btn btn-outline" href="<?= url('settings?tab=email') ?>"><?= icon('settings') ?> Konfigurasi SMTP</a>
    </div>
</div>

<?php if (!$settings['enabled']): ?>
    <div class="alert alert-warning" style="margin-bottom:14px">
        <?= icon('alert') ?>
        <div>Email otomatis sedang <b>dinonaktifkan</b>. Email tetap tercatat di antrean tetapi tidak dikirim sampai diaktifkan di
            <a href="<?= url('settings?tab=email') ?>">Pengaturan → Email</a>.</div>
    </div>
<?php elseif ($settings['driver'] === 'log'): ?>
    <div class="alert alert-info" style="margin-bottom:14px">
        <?= icon('info') ?>
        <div>Driver aktif: <b>Log berkas</b> (mode uji). Email dicatat ke <span class="mono">storage/logs/mail.log</span> tanpa dikirim ke penerima.
            Ubah ke <b>SMTP</b> di <a href="<?= url('settings?tab=email') ?>">Pengaturan → Email</a> untuk pengiriman nyata.</div>
    </div>
<?php endif; ?>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Menunggu</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($stats['pending']) ?></span>
        <span class="s">diproses tiap 5 menit</span>
    </div>
    <div class="mini-stat">
        <span class="k">Terkirim</span>
        <span class="v" style="color:var(--teal-600)"><?= number_id($stats['sent']) ?></span>
        <span class="s"><?= number_id($stats['today']) ?> hari ini</span>
    </div>
    <div class="mini-stat">
        <span class="k">Gagal</span>
        <span class="v" style="color:var(--rose-600)"><?= number_id($stats['failed']) ?></span>
        <span class="s">maks. <?= (int) $maxAttempt ?> percobaan</span>
    </div>
    <div class="mini-stat">
        <span class="k">Driver Aktif</span>
        <span class="v" style="font-size:15px"><?= e($stats['driver_label']) ?></span>
        <span class="s"><?= $settings['delivers'] ? 'siap mengirim' : 'belum mengirim nyata' ?></span>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Riwayat Pengiriman</h2>
                <div class="sub"><?= number_id($paginator->total) ?> email pada filter ini<?= $stats['last'] ? ' · terakhir terkirim ' . tanggal((string) $stats['last'], 'd M Y H:i') : '' ?>.</div></div>
        </div>

        <form class="filter-bar" method="get" action="<?= url('mail') ?>">
            <select class="select" name="status" data-auto-submit style="min-width:140px">
                <option value="">Semua status</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="template" data-auto-submit style="min-width:170px">
                <option value="">Semua template</option>
                <?php foreach ($templates as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filters['template'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="input-group" style="max-width:220px">
                <?= icon('search') ?>
                <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Penerima / subjek…">
            </div>
            <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
            <?php if ($baseQuery): ?>
                <a class="btn btn-sm btn-ghost" href="<?= url('mail') ?>"><?= icon('x') ?> Reset</a>
            <?php endif; ?>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th>Penerima</th>
                    <th>Subjek</th>
                    <th>Status</th>
                    <th>Waktu</th>
                    <th style="width:110px"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $status = (string) $row['status']; ?>
                    <tr>
                        <td>
                            <div class="name-cell">
                                <span class="avatar sm"><?= e($initials((string) $row['recipient_name'], (string) $row['recipient_email'])) ?></span>
                                <div>
                                    <b><?= e($row['recipient_name'] ?: $row['recipient_email']) ?></b>
                                    <span><?= e($row['recipient_email']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="small" style="max-width:280px">
                            <a href="<?= url('mail/' . (int) $row['id']) ?>" style="font-weight:600;color:var(--ink)"><?= e($row['subject']) ?></a>
                            <div class="muted" style="font-size:10px;margin-top:2px">
                                <span class="mono"><?= e($row['template']) ?></span>
                                <?php if ((int) $row['attempts'] > 0): ?> · <?= (int) $row['attempts'] ?>× percobaan<?php endif; ?>
                                <?php if (!empty($row['driver'])): ?> · <?= e((string) $row['driver']) ?><?php endif; ?>
                            </div>
                            <?php if ($status !== 'SENT' && !empty($row['error'])): ?>
                                <div style="font-size:10px;color:var(--rose-600);margin-top:3px"><?= e(mb_substr((string) $row['error'], 0, 90)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?= $statusTone($status) ?>"><?= e($statuses[$status] ?? $status) ?></span></td>
                        <td class="small">
                            <b><?= tanggal((string) ($row['sent_at'] ?: $row['created_at']), 'd M Y') ?></b>
                            <div class="muted mono" style="font-size:9.8px"><?= tanggal((string) ($row['sent_at'] ?: $row['created_at']), 'H:i:s') ?></div>
                        </td>
                        <td>
                            <div class="flex gap-4" style="justify-content:flex-end">
                                <a class="icon-btn" href="<?= url('mail/' . (int) $row['id']) ?>" title="Lihat detail"><?= icon('eye') ?></a>
                                <?php if ($status !== 'SENT'): ?>
                                    <form method="post" action="<?= url('mail/' . (int) $row['id'] . '/retry') ?>">
                                        <?= csrf_field() ?>
                                        <button class="icon-btn" type="submit" title="Kirim ulang"><?= icon('refresh') ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($status === 'PENDING'): ?>
                                    <form method="post" action="<?= url('mail/' . (int) $row['id'] . '/cancel') ?>" data-confirm="Batalkan email ini? Email tidak akan dikirim.">
                                        <?= csrf_field() ?>
                                        <button class="icon-btn" type="submit" title="Batalkan"><?= icon('x') ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="5">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('mail') ?></div>
                            <h3>Belum ada email pada filter ini</h3>
                            <p>Email akan muncul otomatis saat penjadwal mengirim pengingat, atau saat HR menerbitkan pengumuman bertarget.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card__foot flex-between wrap" style="padding:10px 16px">
            <span class="small muted">Menampilkan <?= number_id($paginator->from()) ?>–<?= number_id($paginator->to()) ?> dari <?= number_id($paginator->total) ?> email</span>
            <?php if ($paginator->lastPage() > 1): ?>
                <div class="pagination" style="padding:0">
                    <?php if ($paginator->hasPrevious()): ?><a href="<?= e($link($paginator->page - 1)) ?>"><?= icon('chevron-left') ?></a><?php endif; ?>
                    <?php foreach ($paginator->window(5) as $page): ?>
                        <?php if ($page === $paginator->page): ?>
                            <span class="active"><?= $page ?></span>
                        <?php else: ?>
                            <a href="<?= e($link($page)) ?>"><?= $page ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($paginator->hasNext()): ?><a href="<?= e($link($paginator->page + 1)) ?>"><?= icon('chevron-right') ?></a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head"><div><h2>Status Pengiriman</h2>
                <div class="sub">Alur satu email dari antrean ke penerima.</div></div></div>
            <div class="card__body" style="padding-top:6px">
                <div class="timeline" style="padding-left:4px">
                    <div class="timeline__item">
                        <b>Menunggu</b>
                        <span>Email masuk antrean. Dikirim saat job <span class="mono">email_queue</span> berjalan.</span>
                    </div>
                    <div class="timeline__item">
                        <b>Terkirim</b>
                        <span>Diterima server SMTP. Waktu kirim tercatat untuk audit.</span>
                    </div>
                    <div class="timeline__item muted">
                        <b>Gagal</b>
                        <span>Setelah <?= (int) $maxAttempt ?> percobaan, status menjadi gagal permanen dan dapat dikirim ulang manual.</span>
                    </div>
                    <div class="timeline__item muted">
                        <b>Dibatalkan</b>
                        <span>Dihentikan HR sebelum sempat dikirim (mis. penerima sudah resign).</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Template Terbanyak</h2>
                <div class="sub">Distribusi email berdasarkan template.</div></div></div>
            <?php if ($byTemplate): ?>
                <?php $maxTotal = max(array_map(static fn (array $r): int => (int) $r['total'], $byTemplate)) ?: 1; ?>
                <div class="card__body">
                    <?php foreach ($byTemplate as $item): ?>
                        <div style="margin-bottom:10px">
                            <div class="flex-between small" style="margin-bottom:4px">
                                <b><?= e($templates[$item['template']] ?? $item['template']) ?></b>
                                <span class="muted"><?= number_id((int) $item['total']) ?></span>
                            </div>
                            <div class="progress"><span style="width:<?= (int) round(((int) $item['total'] / $maxTotal) * 100) ?>%"></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding:22px"><h3>Belum ada data</h3></div>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Pemeliharaan</h2>
                <div class="sub">Antrean tersimpan permanen — bersihkan berkala.</div></div></div>
            <form class="card__body" method="post" action="<?= url('mail/prune') ?>"
                  data-confirm="Hapus riwayat email terkirim/dibatalkan lebih tua dari batas yang dipilih?">
                <?= csrf_field() ?>
                <div class="field">
                    <label>Hapus riwayat lebih tua dari</label>
                    <div class="input-group">
                        <input class="input" type="number" name="days" min="7" max="730" value="90">
                        <span class="badge badge-slate" style="align-self:center;margin-left:6px">hari</span>
                    </div>
                    <span class="hint">Email yang masih menunggu atau gagal tidak dihapus.</span>
                </div>
                <div class="form-actions" style="margin-top:12px">
                    <button class="btn btn-soft btn-block" type="submit"><?= icon('trash') ?> Bersihkan Riwayat</button>
                </div>
            </form>
        </section>
    </div>
</div>
