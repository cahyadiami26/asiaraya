<?php
/**
 * Indikator penilaian & bobot (HR).
 * @var array $indicators, $categories, $catTones
 * @var array|null $editing
 * @var array $errors, $old
 * @var float $weightTotal
 */
$val = static function (string $key, string $default = '') use ($old, $editing): string {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if ($editing !== null && array_key_exists($key, $editing) && $editing[$key] !== null) {
        return (string) $editing[$key];
    }
    return $default;
};
$checked = static function (string $key, bool $default = true) use ($val): bool {
    return (string) $val($key, $default ? '1' : '0') === '1';
};
$isEdit = $editing !== null;
$total = 0.0;
foreach ($indicators as $row) {
    if ((int) $row['is_active'] === 1) {
        $total += (float) $row['weight'];
    }
}
$nextSort = 10;
foreach ($indicators as $row) {
    $nextSort = max($nextSort, (int) $row['sort_order'] + 10);
}
?>
<div class="page-head">
    <div>
        <h1>Indikator Penilaian</h1>
        <div class="sub">Kompetensi, hasil kerja, dan perilaku yang dinilai beserta bobotnya. Bobot dihitung proporsional.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('performance/periods') ?>"><?= icon('calendar') ?> Periode Penilaian</a>
        <a class="btn btn-outline" href="<?= url('performance') ?>"><?= icon('award') ?> Monitor</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Indikator Aktif</span>
        <span class="v"><?= number_id(count(array_filter($indicators, static fn ($i) => (int) $i['is_active'] === 1))) ?></span>
        <span class="s">dari <?= count($indicators) ?> terdaftar</span>
    </div>
    <div class="mini-stat">
        <span class="k">Total Bobot</span>
        <span class="v" style="color:<?= abs($total - 100) < 0.01 ? 'var(--teal-700)' : 'var(--amber-600)' ?>"><?= number_format($total, 0, ',', '.') ?></span>
        <span class="s"><?= abs($total - 100) < 0.01 ? 'ideal (100)' : 'disarankan 100' ?></span>
    </div>
    <div class="mini-stat">
        <span class="k">Skala Nilai</span>
        <span class="v">1 – 5</span>
        <span class="s">Sangat kurang s.d. sangat baik</span>
    </div>
    <div class="mini-stat">
        <span class="k">Huruf Mutu</span>
        <span class="v" style="font-size:15px">A · B · C · D · E</span>
        <span class="s">A ≥ 4,50 · B ≥ 3,50 · C ≥ 2,50</span>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Daftar Indikator</h2>
                <div class="sub">Snapshot bobot diambil saat periode dibuka.</div></div>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr><th>Kode</th><th>Indikator</th><th>Kategori</th><th class="num">Bobot</th><th class="num">Urutan</th><th>Status</th><th style="width:96px">Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($indicators as $indicator): ?>
                    <tr>
                        <td><b class="mono" style="font-size:10.4px"><?= e($indicator['code']) ?></b></td>
                        <td>
                            <b><?= e($indicator['name']) ?></b>
                            <?php if ($indicator['description']): ?>
                                <div class="muted small" style="font-size:10px;max-width:280px"><?= e($indicator['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="chip chip-<?= $catTones[$indicator['category']] ?? 'slate' ?>"><?= e($categories[$indicator['category']] ?? $indicator['category']) ?></span></td>
                        <td class="num small"><b><?= number_format((float) $indicator['weight'], 0, ',', '.') ?></b></td>
                        <td class="num small muted"><?= (int) $indicator['sort_order'] ?></td>
                        <td>
                            <span class="badge badge-<?= (int) $indicator['is_active'] === 1 ? 'teal' : 'slate' ?>">
                                <?= (int) $indicator['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>
                        <td>
                            <div class="flex" style="gap:4px;justify-content:flex-end">
                                <a class="btn btn-sm btn-ghost" href="<?= url('performance/indicators?edit=' . (int) $indicator['id']) ?>" title="Ubah"><?= icon('edit') ?></a>
                                <form method="post" action="<?= url('performance/indicators/' . (int) $indicator['id'] . '/delete') ?>"
                                      onsubmit="return confirm('Hapus indikator ini? Indikator yang sudah dipakai tidak dapat dihapus.');">
                                    <?= csrf_field() ?><input type="hidden" name="_method" value="DELETE">
                                    <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)" title="Hapus"><?= icon('trash') ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$indicators): ?>
                    <tr><td colspan="7">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('layers') ?></div>
                            <h3>Belum ada indikator</h3>
                            <p>Tambahkan minimal satu indikator agar periode penilaian dapat dibuka.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card__head">
            <div><h2><?= $isEdit ? 'Ubah Indikator' : 'Indikator Baru' ?></h2>
                <div class="sub">Kode unik dipakai untuk referensi laporan.</div></div>
        </div>
        <form method="post" action="<?= $isEdit ? url('performance/indicators/' . (int) $editing['id']) : url('performance/indicators') ?>">
            <?= csrf_field() ?>
            <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
            <div class="card__body flex-col gap-12">
                <div class="form-grid two">
                    <div class="field">
                        <label>Kode <span class="req">*</span></label>
                        <input class="input mono" type="text" name="code" maxlength="30" required
                               value="<?= e($val('code')) ?>" placeholder="KOMP-01">
                    </div>
                    <div class="field">
                        <label>Kategori <span class="req">*</span></label>
                        <select class="select" name="category">
                            <?php foreach ($categories as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $val('category', 'KOMPETENSI') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label>Nama indikator <span class="req">*</span></label>
                    <input class="input" type="text" name="name" maxlength="150" required value="<?= e($val('name')) ?>"
                           placeholder="Contoh: Kualitas hasil kerja">
                </div>
                <div class="field">
                    <label>Penjelasan</label>
                    <textarea class="textarea" name="description" rows="2" maxlength="255"
                              placeholder="Apa yang diukur indikator ini…"><?= e($val('description')) ?></textarea>
                </div>
                <div class="form-grid two">
                    <div class="field">
                        <label>Bobot <span class="req">*</span></label>
                        <input class="input" type="number" name="weight" min="1" max="100" step="1" required
                               value="<?= e($val('weight', '10')) ?>">
                    </div>
                    <div class="field">
                        <label>Urutan</label>
                        <input class="input" type="number" name="sort_order" min="0" step="10"
                               value="<?= e($val('sort_order', (string) $nextSort)) ?>">
                    </div>
                </div>
                <label class="switch-inline">
                    <input type="checkbox" name="is_active" value="1" <?= $checked('is_active') ? 'checked' : '' ?>>
                    <span>Indikator aktif (dipakai periode baru)</span>
                </label>
            </div>
            <div class="card__foot" style="padding:12px 16px;display:flex;gap:8px;justify-content:flex-end">
                <?php if ($isEdit): ?>
                    <a class="btn btn-ghost" href="<?= url('performance/indicators') ?>">Batal</a>
                <?php endif; ?>
                <button class="btn btn-primary" type="submit"><?= icon($isEdit ? 'check' : 'plus') ?> <?= $isEdit ? 'Simpan' : 'Tambah Indikator' ?></button>
            </div>
        </form>
    </section>
</div>
