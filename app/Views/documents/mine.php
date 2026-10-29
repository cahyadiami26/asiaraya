<?php
/**
 * Dokumen saya (portal karyawan) — Batch 19.
 * @var array $documents, $summary, $types, $statuses, $errors, $old
 */
use App\Models\DocumentModel;

$err = static fn (string $key): string => isset($errors[$key]) ? '<span class="err">' . e($errors[$key]) . '</span>' : '';
$sizeLabel = static function (int $bytes): string {
    if ($bytes <= 0) {
        return '—';
    }
    return $bytes >= 1048576
        ? number_format($bytes / 1048576, 1, ',', '.') . ' MB'
        : number_format($bytes / 1024, 0, ',', '.') . ' KB';
};
?>
<div class="page-head">
    <div>
        <h1>Dokumen Saya</h1>
        <div class="sub">Arsip dokumen pribadi Anda beserta status verifikasi HR.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-primary" href="#unggah"><?= icon('upload') ?> Unggah Dokumen</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat"><span class="k">Total Dokumen</span><span class="v"><?= number_id($summary['total']) ?></span><span class="s">tersimpan di arsip HR</span></div>
    <div class="mini-stat"><span class="k">Menunggu Verifikasi</span><span class="v" style="color:var(--amber-600)"><?= number_id($summary['unverified']) ?></span><span class="s">diproses HR 1–2 hari kerja</span></div>
    <div class="mini-stat"><span class="k">Akan Kedaluwarsa</span><span class="v" style="color:var(--amber-600)"><?= number_id($summary['expiring']) ?></span><span class="s">dalam <?= (int) DocumentModel::EXPIRING_DAYS ?> hari</span></div>
    <div class="mini-stat"><span class="k">Kedaluwarsa</span><span class="v" style="color:var(--rose-600)"><?= number_id($summary['expired']) ?></span><span class="s">perlu diperbarui</span></div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head"><div><h2>Arsip Saya</h2>
            <div class="sub">Hanya dokumen non-rahasia yang ditampilkan di halaman ini.</div></div></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Dokumen</th><th>Berlaku</th><th>Status</th><th style="width:60px"></th></tr></thead>
                <tbody>
                <?php foreach ($documents as $doc): ?>
                    <?php $expiry = DocumentModel::expiryState($doc['expires_at'] ?? null); ?>
                    <tr>
                        <td>
                            <div class="name-cell">
                                <span class="avatar sm" style="background:#e8f0f7;color:var(--navy-800)"><?= icon(str_contains((string) $doc['mime_type'], 'pdf') ? 'file' : 'camera') ?></span>
                                <div>
                                    <b><?= e($doc['title']) ?></b>
                                    <span><?= e(DocumentModel::typeLabel((string) $doc['doc_type'])) ?>
                                        <?php if (!empty($doc['document_number'])): ?> · <span class="mono"><?= e($doc['document_number']) ?></span><?php endif; ?>
                                        · <?= e($sizeLabel((int) $doc['file_size'])) ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="small">
                            <b style="color:<?= $expiry['state'] === 'expired' ? 'var(--rose-600)' : ($expiry['state'] === 'soon' ? 'var(--amber-600)' : 'var(--ink-2)') ?>"><?= e($expiry['label']) ?></b>
                            <div class="muted" style="font-size:10px"><?= $doc['expires_at'] ? 's.d. ' . tanggal((string) $doc['expires_at']) : 'tanpa tanggal' ?></div>
                        </td>
                        <td><span class="badge badge-<?= DocumentModel::statusTone((string) $doc['status']) ?>"><?= e(DocumentModel::statusLabel((string) $doc['status'])) ?></span></td>
                        <td><a class="icon-btn" href="<?= url('documents/' . (int) $doc['id'] . '/download') ?>" target="_blank" title="Buka"><?= icon('eye') ?></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$documents): ?>
                    <tr><td colspan="4">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('file') ?></div>
                            <h3>Belum ada dokumen</h3>
                            <p>Unggah KTP, ijazah, atau sertifikat Anda melalui formulir di samping.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card__foot small muted" style="padding:10px 16px">
            Dokumen bertanda rahasia hanya dapat dibuka HR. Hubungi HR bila ada dokumen yang perlu diperbarui.
        </div>
    </section>

    <section class="card" id="unggah">
        <div class="card__head"><div><h2>Unggah Dokumen</h2>
            <div class="sub">PDF, JPG, atau PNG maksimal 5 MB.</div></div></div>
        <form class="card__body" method="post" action="<?= url('me/documents') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="flex-col gap-12">
                <div class="field">
                    <label>Jenis Dokumen <span class="req">*</span></label>
                    <select class="select" name="doc_type" required>
                        <?php foreach ($types as $key => $meta): ?>
                            <option value="<?= e($key) ?>"><?= e($meta['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('doc_type') ?>
                </div>
                <div class="field">
                    <label>Judul Dokumen <span class="req">*</span></label>
                    <input class="input" name="title" maxlength="160" required placeholder="Contoh: Ijazah S1 Informatika">
                    <?= $err('title') ?>
                </div>
                <div class="field">
                    <label>Nomor Dokumen</label>
                    <input class="input" name="document_number" maxlength="80" placeholder="Opsional">
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label>Tanggal Terbit</label>
                        <input class="input" type="date" name="issued_at">
                    </div>
                    <div class="field">
                        <label>Berlaku Sampai</label>
                        <input class="input" type="date" name="expires_at">
                    </div>
                </div>
                <div class="field">
                    <label>Berkas <span class="req">*</span></label>
                    <input class="input" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required>
                    <?= $err('file') ?>
                </div>
                <div class="field">
                    <label>Catatan</label>
                    <input class="input" name="note" maxlength="255" placeholder="Keterangan tambahan (opsional)">
                </div>
            </div>
            <div class="form-actions" style="margin-top:14px">
                <button class="btn btn-primary btn-block" type="submit"><?= icon('upload') ?> Kirim untuk Diverifikasi</button>
            </div>
        </form>
    </section>
</div>
