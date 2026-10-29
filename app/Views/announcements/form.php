<?php
/**
 * Form tulis/ubah pengumuman.
 * @var array|null $item
 * @var array $categories, $audiences, $targets, $roles, $errors, $old
 */
$val = static function (string $key, string $default = '') use ($old, $item): string {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if ($item !== null && array_key_exists($key, $item) && $item[$key] !== null) {
        return (string) $item[$key];
    }
    return $default;
};

$currentTarget = $val('audience_target', '');
if ($currentTarget === '' && $item !== null) {
    $currentTarget = ($item['audience'] ?? 'ALL') === 'ALL'
        ? 'ALL'
        : ($item['audience'] . ':' . $item['audience_value']);
}

$when = $val('publish_when', $item === null ? 'now' : (($item['status'] ?? 'DRAFT') === 'PUBLISHED' ? 'keep' : 'draft'));
$toLocal = static function (?string $datetime): string {
    return $datetime ? date('Y-m-d\TH:i', (int) strtotime($datetime)) : '';
};
$isEdit = $item !== null;
$action = $isEdit ? url('announcements/' . (int) $item['id']) : url('announcements');
?>
<div class="page-head">
    <div>
        <div class="small muted" style="margin-bottom:4px">
            <a href="<?= url('announcements/manage') ?>">Kelola Pengumuman</a> / <?= $isEdit ? 'Ubah' : 'Baru' ?>
        </div>
        <h1><?= $isEdit ? 'Ubah Pengumuman' : 'Tulis Pengumuman' ?></h1>
        <div class="sub">Tentukan isi, audiens, dan jadwal terbit. Notifikasi lonceng dikirim otomatis saat pengumuman terbit.</div>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

    <div class="split-grid wide-left">
        <div class="flex-col gap-16">
            <section class="card">
                <div class="card__head"><div><h2>Isi Pengumuman</h2>
                    <div class="sub">Gunakan baris kosong untuk paragraf baru, "- " untuk daftar, dan **teks** untuk penekanan.</div></div></div>
                <div class="card__body flex-col gap-12">
                    <div class="field">
                        <label>Judul <span class="req">*</span></label>
                        <input class="input" type="text" name="title" maxlength="180" required
                               value="<?= e($val('title')) ?>" placeholder="Contoh: Perubahan jam kerja selama bulan Ramadan">
                    </div>
                    <div class="form-grid two">
                        <div class="field">
                            <label>Kategori <span class="req">*</span></label>
                            <select class="select" name="category" required>
                                <?php foreach ($categories as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $val('category', 'INFO') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Audiens <span class="req">*</span></label>
                            <select class="select" name="audience_target" required>
                                <option value="ALL" <?= $currentTarget === 'ALL' ? 'selected' : '' ?>>Semua karyawan aktif</option>
                                <?php if ($targets['units']): ?>
                                    <optgroup label="Unit bisnis">
                                        <?php foreach ($targets['units'] as $unit): $value = 'UNIT:' . (int) $unit['id']; ?>
                                            <option value="<?= $value ?>" <?= $currentTarget === $value ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if ($targets['departments']): ?>
                                    <optgroup label="Departemen">
                                        <?php foreach ($targets['departments'] as $dept): $value = 'DEPARTMENT:' . (int) $dept['id']; ?>
                                            <option value="<?= $value ?>" <?= $currentTarget === $value ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if ($targets['positions']): ?>
                                    <optgroup label="Jabatan">
                                        <?php foreach ($targets['positions'] as $pos): $value = 'POSITION:' . (int) $pos['id']; ?>
                                            <option value="<?= $value ?>" <?= $currentTarget === $value ? 'selected' : '' ?>><?= e($pos['name']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <optgroup label="Peran sistem">
                                    <?php foreach ($roles as $roleKey => $roleLabel): $value = 'ROLE:' . $roleKey; ?>
                                        <option value="<?= $value ?>" <?= $currentTarget === $value ? 'selected' : '' ?>><?= e($roleLabel) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                    <div class="field">
                        <label>Isi <span class="req">*</span></label>
                        <textarea class="textarea" name="body" rows="14" required
                                  placeholder="Tuliskan isi pengumuman secara lengkap dan jelas…"><?= e($val('body')) ?></textarea>
                        <span class="hint">Minimal 20 karakter. Karyawan hanya melihat pengumuman yang sesuai audiensnya.</span>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><div><h2>Lampiran</h2>
                    <div class="sub">Opsional — PDF, JPG, atau PNG maksimal 5 MB (mis. surat edaran atau poster).</div></div></div>
                <div class="card__body">
                    <?php if ($isEdit && !empty($item['attachment'])): ?>
                        <div class="file-chip">
                            <?= icon('file') ?>
                            <div>
                                <b><?= e(basename((string) $item['attachment'])) ?></b>
                                <a href="<?= e(url('media/' . $item['attachment'])) ?>" target="_blank" rel="noopener">Lihat lampiran saat ini</a>
                            </div>
                            <label class="switch-inline" style="margin-left:auto">
                                <input type="checkbox" name="remove_attachment" value="1">
                                <span>Hapus</span>
                            </label>
                        </div>
                        <div class="divider"></div>
                    <?php endif; ?>
                    <div class="field">
                        <label><?= $isEdit && !empty($item['attachment']) ? 'Ganti lampiran' : 'Unggah lampiran' ?></label>
                        <input class="input" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                </div>
            </section>
        </div>

        <div class="flex-col gap-16">
            <section class="card">
                <div class="card__head"><div><h2>Publikasi</h2></div></div>
                <div class="card__body flex-col gap-12">
                    <div class="field">
                        <label>Waktu terbit</label>
                        <select class="select" name="publish_when" data-publish-when>
                            <?php if ($isEdit && ($item['status'] ?? '') === 'PUBLISHED'): ?>
                                <option value="keep" <?= $when === 'keep' ? 'selected' : '' ?>>Biarkan seperti sekarang (terbit)</option>
                            <?php endif; ?>
                            <option value="now" <?= $when === 'now' ? 'selected' : '' ?>>Terbitkan sekarang</option>
                            <option value="schedule" <?= $when === 'schedule' ? 'selected' : '' ?>>Jadwalkan</option>
                            <option value="draft" <?= $when === 'draft' ? 'selected' : '' ?>>Simpan sebagai draf</option>
                        </select>
                    </div>
                    <div class="field" data-publish-at <?= $when === 'schedule' ? '' : 'hidden' ?>>
                        <label>Tanggal &amp; jam terbit</label>
                        <input class="input" type="datetime-local" name="publish_at" value="<?= e($toLocal($item['publish_at'] ?? null)) ?>">
                    </div>
                    <div class="field">
                        <label>Berlaku sampai (opsional)</label>
                        <input class="input" type="datetime-local" name="expires_at" value="<?= e($toLocal($item['expires_at'] ?? null)) ?>">
                        <span class="hint">Setelah tanggal ini pengumuman tidak tampil lagi di feed.</span>
                    </div>
                    <div class="divider"></div>
                    <label class="switch-inline">
                        <input type="checkbox" name="is_pinned" value="1" <?= (int) $val('is_pinned', (string) ($item['is_pinned'] ?? 0)) === 1 ? 'checked' : '' ?>>
                        <span>Tandai penting (pin di atas feed)</span>
                    </label>
                    <label class="switch-inline">
                        <input type="checkbox" name="notify" value="1" <?= (int) $val('notify', (string) ($item['notify'] ?? 1)) === 1 ? 'checked' : '' ?>>
                        <span>Kirim notifikasi lonceng ke audiens</span>
                    </label>
                </div>
                <div class="card__foot" style="padding:12px 16px;display:flex;gap:8px;justify-content:flex-end">
                    <a class="btn btn-ghost" href="<?= url('announcements/manage') ?>">Batal</a>
                    <button class="btn btn-primary" type="submit"><?= icon($isEdit ? 'check' : 'send') ?> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan Pengumuman' ?></button>
                </div>
            </section>

            <?php if ($isEdit): ?>
                <section class="card">
                    <div class="card__head"><div><h2>Informasi</h2></div></div>
                    <div class="card__body">
                        <div class="meta-list">
                            <div><span>Dibuat</span><b><?= tanggal($item['created_at'], 'd M Y H:i') ?></b></div>
                            <div><span>Terakhir diubah</span><b><?= tanggal($item['updated_at'] ?? $item['created_at'], 'd M Y H:i') ?></b></div>
                            <div><span>Status</span><b><?= e(\App\Models\AnnouncementModel::STATUSES[$item['status']] ?? $item['status']) ?></b></div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
(function () {
    var select = document.querySelector('[data-publish-when]');
    var box = document.querySelector('[data-publish-at]');
    if (!select || !box) { return; }
    var sync = function () { box.hidden = select.value !== 'schedule'; };
    select.addEventListener('change', sync);
    sync();
})();
</script>
