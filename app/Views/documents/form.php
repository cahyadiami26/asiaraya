<?php
/**
 * Unggah / ubah dokumen karyawan (HR) — Batch 19.
 * @var array|null $document, $employee
 * @var array $types, $employees, $errors, $old
 * @var string $backUrl
 */
use App\Models\DocumentModel;

$isEdit = is_array($document);
$action = $isEdit ? url('documents/' . $document['id']) : url('documents');
$val = static function (string $key, mixed $default = '') use ($old, $document) {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    if (is_array($document) && array_key_exists($key, $document)) {
        return $document[$key];
    }
    return $default;
};
$err = static fn (string $key): string => isset($errors[$key]) ? '<span class="err">' . e($errors[$key]) . '</span>' : '';
$selectedEmployee = (int) $val('employee_id', is_array($employee) ? (int) $employee['id'] : 0);
$typeNow = (string) $val('doc_type', 'KTP');
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Ubah Dokumen' : 'Unggah Dokumen' ?></h1>
        <div class="sub">
            <?= $isEdit
                ? e($document['title']) . ' · ' . e($document['full_name'])
                : 'Berkas PDF, JPG, atau PNG maksimal 5 MB. Disimpan di luar folder publik.' ?>
        </div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url($backUrl) ?>"><?= icon('arrow-left') ?> Kembali</a>
        <?php if ($isEdit): ?>
            <a class="btn btn-soft" href="<?= url('documents/' . (int) $document['id'] . '/download') ?>" target="_blank"><?= icon('eye') ?> Buka Berkas</a>
        <?php endif; ?>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2><?= $isEdit ? 'Detail Dokumen' : 'Data Dokumen' ?></h2>
                <div class="sub">Lengkapi metadata agar mudah dicari saat audit.</div></div>
        </div>
        <form class="card__body" method="post" action="<?= $action ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

            <div class="form-grid">
                <?php if (!$isEdit): ?>
                    <div class="field full">
                        <label>Karyawan <span class="req">*</span></label>
                        <select class="select" name="employee_id" required>
                            <option value="">Pilih karyawan…</option>
                            <?php foreach ($employees as $row): ?>
                                <option value="<?= (int) $row['id'] ?>" <?= $selectedEmployee === (int) $row['id'] ? 'selected' : '' ?>>
                                    <?= e($row['full_name']) ?> · <?= e($row['employee_code']) ?><?= $row['flag'] !== 'ACTIVE' ? ' (nonaktif)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('employee_id') ?>
                    </div>
                <?php else: ?>
                    <div class="field full">
                        <label>Karyawan</label>
                        <input class="input" value="<?= e($document['full_name'] . ' · ' . $document['employee_code']) ?>" readonly>
                        <span class="hint">Pemilik dokumen tidak dapat dipindahkan. Hapus lalu unggah ulang bila salah.</span>
                    </div>
                <?php endif; ?>

                <div class="field">
                    <label>Jenis Dokumen <span class="req">*</span></label>
                    <select class="select" name="doc_type" id="doc-type" required>
                        <?php foreach ($types as $key => $meta): ?>
                            <option value="<?= e($key) ?>" <?= $typeNow === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('doc_type') ?>
                </div>

                <div class="field">
                    <label>Nomor Dokumen</label>
                    <input class="input" name="document_number" maxlength="80" value="<?= e($val('document_number')) ?>" placeholder="Nomor KTP / NPWP / sertifikat">
                    <?= $err('document_number') ?>
                </div>

                <div class="field full">
                    <label>Judul Dokumen <span class="req">*</span></label>
                    <input class="input" name="title" maxlength="160" required value="<?= e($val('title')) ?>" placeholder="Contoh: KTP — Budi Santoso">
                    <?= $err('title') ?>
                </div>

                <div class="field">
                    <label>Tanggal Terbit</label>
                    <input class="input" type="date" name="issued_at" value="<?= e($val('issued_at')) ?>">
                    <?= $err('issued_at') ?>
                </div>

                <div class="field">
                    <label>Berlaku Sampai</label>
                    <input class="input" type="date" name="expires_at" value="<?= e($val('expires_at')) ?>">
                    <span class="hint">Kosongkan bila dokumen berlaku selamanya (mis. ijazah).</span>
                    <?= $err('expires_at') ?>
                </div>

                <div class="field full">
                    <label>Catatan</label>
                    <input class="input" name="note" maxlength="255" value="<?= e($val('note')) ?>" placeholder="Keterangan tambahan (opsional)">
                    <?= $err('note') ?>
                </div>

                <div class="field full">
                    <label>Berkas <?= $isEdit ? '(kosongkan bila tidak diganti)' : '<span class="req">*</span>' ?></label>
                    <input class="input" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" <?= $isEdit ? '' : 'required' ?>>
                    <span class="hint">PDF, JPG, atau PNG · maksimal 5 MB<?= $isEdit ? ' · unggahan baru mengembalikan status ke belum diverifikasi' : '' ?>.</span>
                    <?= $err('file') ?>
                </div>

                <div class="field full">
                    <label class="checkbox">
                        <input type="checkbox" name="is_confidential" value="1" <?= (int) $val('is_confidential', 0) === 1 ? 'checked' : '' ?>>
                        <span>Dokumen rahasia — hanya HR yang dapat melihat &amp; mengunduh</span>
                    </label>
                    <?php if (!$isEdit): ?>
                        <label class="checkbox" style="margin-top:8px">
                            <input type="checkbox" name="verify_now" value="1" checked>
                            <span>Langsung tandai terverifikasi (unggahan HR)</span>
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-actions" style="margin-top:16px">
                <a class="btn btn-ghost" href="<?= url($backUrl) ?>">Batal</a>
                <button class="btn btn-primary" type="submit"><?= icon('check') ?> <?= $isEdit ? 'Simpan Perubahan' : 'Unggah Dokumen' ?></button>
            </div>
        </form>
    </section>

    <div class="flex-col gap-16">
        <?php if ($isEdit): ?>
            <section class="card">
                <div class="card__head"><div><h2>Verifikasi</h2>
                    <div class="sub">Status saat ini: <?= e(DocumentModel::statusLabel((string) $document['status'])) ?>.</div></div></div>
                <form class="card__body" method="post" action="<?= url('documents/' . (int) $document['id'] . '/verify') ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label>Catatan verifikasi</label>
                        <input class="input" name="note" maxlength="255" value="<?= e((string) ($document['note'] ?? '')) ?>" placeholder="Alasan penolakan / catatan pemeriksaan">
                        <span class="hint">Wajib diisi bila menolak dokumen.</span>
                    </div>
                    <div class="flex gap-8" style="margin-top:12px">
                        <button class="btn btn-teal btn-block" type="submit" name="status" value="VERIFIED"><?= icon('check') ?> Verifikasi</button>
                        <button class="btn btn-soft btn-block" type="submit" name="status" value="REJECTED"><?= icon('x') ?> Tolak</button>
                    </div>
                </form>
                <?php if (!empty($document['verified_at'])): ?>
                    <div class="card__foot small muted" style="padding:10px 16px">
                        Diproses <?= tanggal((string) $document['verified_at'], 'd M Y H:i') ?>
                        <?= $document['verifier_name'] ? ' oleh ' . e($document['verifier_name']) : '' ?>.
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="card">
            <div class="card__head"><div><h2>Panduan Berkas</h2></div></div>
            <div class="card__body small muted" style="line-height:1.7">
                <p><b>KTP &amp; KK</b> — pindai jelas, seluruh sudut terlihat, ukuran wajar (&lt; 1 MB).</p>
                <p><b>Ijazah &amp; sertifikat</b> — isi tanggal terbit dan masa berlaku bila ada (lisensi/SIM/paspor).</p>
                <p><b>Kontrak kerja</b> — simpan versi terbaru; gunakan tanggal berlaku sampai sesuai akhir kontrak.</p>
                <p>Berkas tersimpan di <span class="mono">storage/uploads/documents</span> dan hanya bisa diunduh lewat aplikasi.</p>
            </div>
        </section>
    </div>
</div>
