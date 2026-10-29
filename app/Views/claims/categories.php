<?php
/**
 * Master kategori klaim (HR).
 * @var array $categories, $stats, $errors, $old
 * @var array|null $editing
 */
$val = static function (string $key, mixed $default = '') use ($old, $editing) {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    if ($editing !== null && array_key_exists($key, $editing)) {
        return $editing[$key];
    }
    return $default;
};
$checked = static fn (string $key, bool $default = false): bool => (string) $val($key, $default ? '1' : '0') === '1';
$isEdit = $editing !== null;
?>
<div class="page-head">
    <div>
        <h1>Kategori Klaim</h1>
        <div class="sub">Jenis penggantian biaya, plafon nominal, dan kewajiban bukti.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('claims') ?>"><?= icon('wallet') ?> Antrean Klaim</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Kategori Aktif</span>
        <span class="v"><?= number_id(count(array_filter($categories, static fn ($c) => (int) $c['is_active'] === 1))) ?></span>
        <span class="s">dari <?= count($categories) ?> kategori terdaftar</span>
    </div>
    <div class="mini-stat">
        <span class="k">Klaim Masuk</span>
        <span class="v"><?= number_id(array_sum(array_map(static fn ($c) => (int) $c['claim_count'], $categories))) ?></span>
        <span class="s">seluruh kategori</span>
    </div>
    <div class="mini-stat">
        <span class="k">Disetujui</span>
        <span class="v" style="font-size:15px"><?= rupiah(array_sum(array_map(static fn ($c) => (float) $c['approved_total'], $categories))) ?></span>
        <span class="s">termasuk yang sudah dibayar</span>
    </div>
    <div class="mini-stat">
        <span class="k">Menunggu Keputusan</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($stats['pending'] + $stats['waiting_final']) ?></span>
        <span class="s">atasan + HR/Finance</span>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Daftar Kategori</h2>
                <div class="sub">Plafon kosong berarti tanpa batas nominal per klaim.</div></div>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr><th>Kategori</th><th class="num">Plafon</th><th>Bukti</th><th class="num">Klaim</th>
                    <th class="num">Disetujui</th><th>Status</th><th style="width:90px">Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td>
                            <b><?= e($category['name']) ?></b>
                            <div class="mono muted" style="font-size:10px"><?= e($category['code']) ?></div>
                            <?php if ($category['description']): ?>
                                <div class="muted small" style="font-size:10px;max-width:240px"><?= e($category['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="num small"><?= $category['max_amount'] !== null ? rupiah((float) $category['max_amount']) : '—' ?></td>
                        <td><?= (int) $category['requires_receipt'] === 1
                            ? '<span class="badge badge-indigo">Wajib</span>'
                            : '<span class="badge badge-slate">Opsional</span>' ?></td>
                        <td class="num small"><?= number_id($category['claim_count']) ?></td>
                        <td class="num small"><?= rupiah($category['approved_total']) ?></td>
                        <td><?= (int) $category['is_active'] === 1
                            ? '<span class="badge badge-teal">Aktif</span>'
                            : '<span class="badge badge-slate">Nonaktif</span>' ?></td>
                        <td>
                            <div class="flex" style="gap:3px">
                                <a class="btn btn-sm btn-ghost" href="<?= url('claims/categories?edit=' . $category['id']) ?>" title="Ubah"><?= icon('edit') ?></a>
                                <?php if ((int) $category['claim_count'] === 0): ?>
                                    <form class="inline-form" method="post" action="<?= url('claims/categories/' . $category['id'] . '/delete') ?>"
                                          data-confirm="Hapus kategori <?= e($category['name']) ?>?">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)" title="Hapus"><?= icon('trash') ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$categories): ?>
                    <tr><td colspan="7" class="table-empty"><?= icon('layers') ?>Belum ada kategori klaim.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card__foot" style="padding:11px 16px">
            <span class="small muted">Kategori yang sudah dipakai tidak dapat dihapus — nonaktifkan agar riwayat tetap utuh.</span>
        </div>
    </section>

    <section class="card" id="kategori-form">
        <div class="card__head">
            <div><h2><?= $isEdit ? 'Ubah Kategori' : 'Tambah Kategori' ?></h2>
                <div class="sub"><?= $isEdit ? e($editing['name']) : 'Kategori baru untuk pengajuan karyawan.' ?></div></div>
            <?php if ($isEdit): ?>
                <a class="btn btn-sm btn-ghost" href="<?= url('claims/categories') ?>"><?= icon('x') ?> Batal</a>
            <?php endif; ?>
        </div>
        <form class="card__body" method="post" action="<?= url($isEdit ? 'claims/categories/' . $editing['id'] : 'claims/categories') ?>">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="field">
                    <label>Kode <span class="req">*</span></label>
                    <input class="input mono" name="code" maxlength="30" required placeholder="TRANSPORT"
                           value="<?= e($val('code')) ?>">
                    <?php if (!empty($errors['code'])): ?><span class="err"><?= e($errors['code']) ?></span><?php endif; ?>
                    <span class="hint">Huruf kapital, angka, garis bawah.</span>
                </div>
                <div class="field">
                    <label>Nama <span class="req">*</span></label>
                    <input class="input" name="name" maxlength="100" required placeholder="Transportasi Lokal"
                           value="<?= e($val('name')) ?>">
                    <?php if (!empty($errors['name'])): ?><span class="err"><?= e($errors['name']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label>Plafon per Klaim (Rp)</label>
                    <input class="input" type="number" name="max_amount" min="0" step="1000"
                           value="<?= e($val('max_amount')) ?>" placeholder="Kosong = tanpa batas">
                </div>
                <div class="field">
                    <label>Deskripsi</label>
                    <input class="input" name="description" maxlength="255" value="<?= e($val('description')) ?>"
                           placeholder="Contoh: Transport antar klien dalam kota">
                </div>
            </div>

            <div class="divider"></div>
            <div class="flex-col" style="gap:9px">
                <label class="checkbox"><input type="checkbox" name="requires_receipt" value="1" <?= $checked('requires_receipt', true) ? 'checked' : '' ?>> <span>Wajib lampiran bukti (nota/foto)</span></label>
                <label class="checkbox"><input type="checkbox" name="is_active" value="1" <?= $checked('is_active', true) ? 'checked' : '' ?>> <span>Aktif (bisa dipilih karyawan)</span></label>
            </div>

            <div class="form-actions" style="margin-top:14px">
                <button class="btn btn-primary btn-block" type="submit"><?= icon('check') ?> <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Kategori' ?></button>
            </div>
        </form>
    </section>
</div>
