<?php
/**
 * Daftar dokumen karyawan (HR) — Batch 19.
 * @var array $rows, $filters, $stats, $byType, $expiring, $types, $statuses, $units, $employees
 * @var \App\Core\Paginator $paginator
 * @var \App\Models\DocumentModel $model
 */
use App\Models\DocumentModel;

$baseQuery = array_filter([
    'q'        => $filters['q'] ?: null,
    'type'     => $filters['type'] ?: null,
    'status'   => $filters['status'] ?: null,
    'expiry'   => $filters['expiry'] ?: null,
    'employee' => $filters['employee'] ?: null,
    'unit'     => $filters['unit'] ?: null,
]);
$link = static fn (int $page): string => url('documents') . '?' . http_build_query($baseQuery + ['page' => $page]);
$sizeLabel = static function (int $bytes): string {
    if ($bytes <= 0) {
        return '—';
    }
    return $bytes >= 1048576
        ? number_format($bytes / 1048576, 1, ',', '.') . ' MB'
        : number_format($bytes / 1024, 0, ',', '.') . ' KB';
};
$fileIcon = static function (?string $mime): string {
    return str_contains((string) $mime, 'pdf') ? 'file' : 'camera';
};
?>
<div class="page-head">
    <div>
        <h1>Dokumen Karyawan</h1>
        <div class="sub">Arsip digital KTP, NPWP, ijazah, kontrak, sertifikat, dan dokumen pendukung lain — lengkap dengan verifikasi dan pemantauan masa berlaku.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-primary" href="<?= url('documents/create') ?>"><?= icon('upload') ?> Unggah Dokumen</a>
        <a class="btn btn-outline" href="<?= url('documents?status=UNVERIFIED') ?>"><?= icon('check') ?> Antrean Verifikasi</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Total Dokumen</span>
        <span class="v"><?= number_id($stats['total']) ?></span>
        <span class="s"><?= number_id($stats['employees']) ?> karyawan memiliki arsip</span>
    </div>
    <div class="mini-stat">
        <span class="k">Belum Diverifikasi</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($stats['unverified']) ?></span>
        <span class="s"><?= number_id($stats['verified']) ?> sudah terverifikasi</span>
    </div>
    <div class="mini-stat">
        <span class="k">Akan Kedaluwarsa</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($stats['expiring']) ?></span>
        <span class="s">dalam <?= (int) DocumentModel::EXPIRING_DAYS ?> hari</span>
    </div>
    <div class="mini-stat">
        <span class="k">Cakupan Arsip</span>
        <span class="v"><?= number_format($stats['coverage'], 1, ',', '.') ?>%</span>
        <span class="s"><?= number_id($stats['expired']) ?> dokumen kedaluwarsa</span>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Arsip Dokumen</h2>
                <div class="sub"><?= number_id($paginator->total) ?> dokumen pada filter ini.</div></div>
        </div>

        <form class="filter-bar" method="get" action="<?= url('documents') ?>">
            <div class="input-group" style="max-width:200px">
                <?= icon('search') ?>
                <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Judul / nomor / nama…">
            </div>
            <select class="select" name="type" data-auto-submit style="min-width:150px">
                <option value="">Semua jenis</option>
                <?php foreach ($types as $key => $meta): ?>
                    <option value="<?= e($key) ?>" <?= $filters['type'] === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="status" data-auto-submit style="min-width:150px">
                <option value="">Semua status</option>
                <?php foreach ($statuses as $key => $meta): ?>
                    <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="expiry" data-auto-submit style="min-width:150px">
                <option value="">Semua masa berlaku</option>
                <option value="expiring" <?= $filters['expiry'] === 'expiring' ? 'selected' : '' ?>>Akan kedaluwarsa</option>
                <option value="expired" <?= $filters['expiry'] === 'expired' ? 'selected' : '' ?>>Sudah kedaluwarsa</option>
                <option value="none" <?= $filters['expiry'] === 'none' ? 'selected' : '' ?>>Tanpa masa berlaku</option>
            </select>
            <select class="select" name="employee" data-auto-submit style="min-width:170px">
                <option value="">Semua karyawan</option>
                <?php foreach ($employees as $row): ?>
                    <option value="<?= (int) $row['id'] ?>" <?= $filters['employee'] === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="unit" data-auto-submit style="min-width:150px">
                <option value="">Semua unit</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= (int) $unit['id'] ?>" <?= $filters['unit'] === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
            <?php if ($baseQuery): ?>
                <a class="btn btn-sm btn-ghost" href="<?= url('documents') ?>"><?= icon('x') ?> Reset</a>
            <?php endif; ?>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr><th>Dokumen</th><th>Karyawan</th><th>Masa Berlaku</th><th>Status</th><th style="width:150px"></th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $expiry = DocumentModel::expiryState($row['expires_at'] ?? null);
                    $tone = DocumentModel::statusTone((string) $row['status']);
                    $expiryColor = match ($expiry['state']) {
                        'expired' => 'var(--rose-600)',
                        'soon'    => 'var(--amber-600)',
                        'safe'    => 'var(--teal-700)',
                        default   => 'var(--muted)',
                    };
                    ?>
                    <tr>
                        <td>
                            <div class="name-cell">
                                <span class="avatar sm" style="background:#e8f0f7;color:var(--navy-800)"><?= icon($fileIcon($row['mime_type'])) ?></span>
                                <div>
                                    <b><?= e($row['title']) ?></b>
                                    <span>
                                        <?= e(DocumentModel::typeLabel((string) $row['doc_type'])) ?>
                                        <?php if (!empty($row['document_number'])): ?> · <span class="mono"><?= e($row['document_number']) ?></span><?php endif; ?>
                                        <?php if ((int) $row['is_confidential'] === 1): ?> · <span style="color:var(--rose-600)">rahasia</span><?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="small">
                            <a href="<?= url('employees/' . (int) $row['employee_id']) ?>" style="font-weight:600;color:var(--ink)"><?= e($row['full_name']) ?></a>
                            <div class="muted mono" style="font-size:10px"><?= e($row['employee_code']) ?> · <?= e($row['unit_name'] ?: '—') ?></div>
                        </td>
                        <td class="small">
                            <b style="color:<?= $expiryColor ?>"><?= e($expiry['label']) ?></b>
                            <div class="muted" style="font-size:10px">
                                <?= $row['expires_at'] ? 's.d. ' . tanggal((string) $row['expires_at']) : 'tanpa tanggal' ?>
                                · <?= e($sizeLabel((int) $row['file_size'])) ?>
                            </div>
                        </td>
                        <td><span class="badge badge-<?= $tone ?>"><?= e(DocumentModel::statusLabel((string) $row['status'])) ?></span></td>
                        <td>
                            <div class="flex gap-4" style="justify-content:flex-end">
                                <a class="icon-btn" href="<?= url('documents/' . (int) $row['id'] . '/download') ?>" target="_blank" title="Buka dokumen"><?= icon('eye') ?></a>
                                <a class="icon-btn" href="<?= url('documents/' . (int) $row['id'] . '/edit') ?>" title="Ubah metadata"><?= icon('edit') ?></a>
                                <?php if ((string) $row['status'] !== 'VERIFIED'): ?>
                                    <form method="post" action="<?= url('documents/' . (int) $row['id'] . '/verify') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="status" value="VERIFIED">
                                        <button class="icon-btn" type="submit" title="Tandai terverifikasi"><?= icon('check') ?></button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?= url('documents/' . (int) $row['id'] . '/delete') ?>"
                                      data-confirm="Hapus dokumen &quot;<?= e($row['title']) ?>&quot;? Berkas akan dihapus permanen.">
                                    <?= csrf_field() ?>
                                    <button class="icon-btn" type="submit" title="Hapus"><?= icon('trash') ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="5">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('file') ?></div>
                            <h3>Belum ada dokumen pada filter ini</h3>
                            <p>Unggah KTP, NPWP, ijazah, atau kontrak kerja agar arsip kepegawaian lengkap dan siap diaudit.</p>
                            <a class="btn btn-sm btn-primary" style="margin-top:10px" href="<?= url('documents/create') ?>"><?= icon('upload') ?> Unggah Dokumen</a>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card__foot flex-between wrap" style="padding:10px 16px">
            <span class="small muted">Menampilkan <?= number_id($paginator->from()) ?>–<?= number_id($paginator->to()) ?> dari <?= number_id($paginator->total) ?> dokumen</span>
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
            <div class="card__head"><div><h2>Perlu Perhatian</h2>
                <div class="sub">Dokumen yang mendekati atau melewati masa berlaku.</div></div></div>
            <div class="list-rows">
                <?php foreach ($expiring as $doc): ?>
                    <?php $state = DocumentModel::expiryState((string) $doc['expires_at']); ?>
                    <div class="list-row">
                        <div class="list-row__body">
                            <b><?= e($doc['title']) ?></b>
                            <span><?= e($doc['full_name']) ?> · <?= e(DocumentModel::typeLabel((string) $doc['doc_type'])) ?></span>
                            <span style="color:<?= $state['state'] === 'expired' ? 'var(--rose-600)' : 'var(--amber-600)' ?>;font-size:10.6px">
                                <?= e($state['label']) ?> · <?= tanggal((string) $doc['expires_at']) ?>
                            </span>
                        </div>
                        <a class="icon-btn" href="<?= url('documents/' . (int) $doc['id'] . '/edit') ?>" title="Perbarui"><?= icon('refresh') ?></a>
                    </div>
                <?php endforeach; ?>
                <?php if (!$expiring): ?>
                    <div class="empty-state" style="padding:20px">
                        <h3>Semua aman</h3>
                        <p>Tidak ada dokumen yang akan kedaluwarsa.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Komposisi Jenis</h2>
                <div class="sub">Distribusi arsip berdasarkan jenis dokumen.</div></div></div>
            <?php if ($byType): ?>
                <div class="card__body">
                    <?php foreach ($byType as $item): ?>
                        <div style="margin-bottom:10px">
                            <div class="flex-between small" style="margin-bottom:4px">
                                <b><?= e($item['label']) ?></b>
                                <span class="muted"><?= number_id($item['total']) ?></span>
                            </div>
                            <div class="progress"><span style="width:<?= (int) $item['pct'] ?>%"></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding:24px"><h3>Belum ada dokumen</h3></div>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Alur Verifikasi</h2></div></div>
            <div class="card__body" style="padding-top:6px">
                <div class="timeline" style="padding-left:4px">
                    <div class="timeline__item">
                        <b>Karyawan / HR mengunggah</b>
                        <span>Berkas PDF/JPG/PNG maksimal 5 MB disimpan di luar web root.</span>
                    </div>
                    <div class="timeline__item">
                        <b>Belum diverifikasi</b>
                        <span>Masuk antrean HR; karyawan dapat melihat statusnya di portal.</span>
                    </div>
                    <div class="timeline__item muted">
                        <b>Terverifikasi / Ditolak</b>
                        <span>HR memberi tanda sah atau meminta perbaikan beserta alasannya.</span>
                    </div>
                </div>
                <p class="small muted" style="margin-top:10px;line-height:1.6">
                    Dokumen bertanda <b>rahasia</b> hanya dapat dibuka HR — karyawan tidak melihatnya di portal.
                    Pengingat kedaluwarsa dikirim otomatis oleh job <span class="mono">document_expiry</span>
                    pada <a href="<?= url('scheduler') ?>">Penjadwal &amp; Cron</a>.
                </p>
            </div>
        </section>
    </div>
</div>
