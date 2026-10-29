<?php
/**
 * Katalog program pelatihan (HR).
 * @var array $programs, $categories, $categoryTones, $types
 * @var array|null $editing
 * @var array $errors, $old
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
$isEdit = $editing !== null;
$active = count(array_filter($programs, static fn (array $p): bool => (int) $p['is_active'] === 1));
$mandatory = count(array_filter($programs, static fn (array $p): bool => (int) $p['is_mandatory'] === 1));
$alumni = array_sum(array_map(static fn (array $p): int => (int) $p['alumni'], $programs));
?>
<div class="page-head">
    <div>
        <h1>Katalog Pelatihan</h1>
        <div class="sub">Master program pelatihan: kategori, penyedia, durasi, dan biaya acuan per peserta.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('trainings') ?>"><?= icon('calendar-check') ?> Jadwal Pelatihan</a>
        <a class="btn btn-primary" href="#form-program"><?= icon('plus') ?> Program Baru</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Total Program</span>
        <span class="v"><?= number_id(count($programs)) ?></span>
        <span class="s"><?= number_id($active) ?> berstatus aktif</span>
    </div>
    <div class="mini-stat">
        <span class="k">Program Wajib</span>
        <span class="v" style="color:var(--rose-600)"><?= number_id($mandatory) ?></span>
        <span class="s">kepatuhan &amp; onboarding</span>
    </div>
    <div class="mini-stat">
        <span class="k">Alumni Pelatihan</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id($alumni) ?></span>
        <span class="s">peserta tercatat hadir</span>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Daftar Program</h2>
                <div class="sub">Program nonaktif tetap tersimpan untuk menjaga riwayat sesi.</div></div>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th>Program</th><th>Kategori</th><th>Jenis &amp; Penyedia</th>
                    <th class="num">Durasi</th><th class="num">Biaya</th><th class="num">Sesi</th>
                    <th class="num">Alumni</th><th style="width:90px">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($programs as $program): ?>
                    <tr>
                        <td>
                            <b><?= e($program['name']) ?></b>
                            <div class="chip-row" style="margin-top:4px">
                                <span class="chip chip-slate"><?= e($program['code']) ?></span>
                                <?php if ((int) $program['is_mandatory'] === 1): ?>
                                    <span class="chip chip-rose">Wajib</span>
                                <?php endif; ?>
                                <?php if ((int) $program['is_active'] !== 1): ?>
                                    <span class="chip chip-amber">Nonaktif</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><span class="chip chip-<?= $categoryTones[$program['category']] ?? 'slate' ?>"><?= e($categories[$program['category']] ?? $program['category']) ?></span></td>
                        <td class="small">
                            <?= e($types[$program['type']] ?? $program['type']) ?>
                            <div class="muted" style="font-size:9.8px"><?= e($program['provider'] ?: '—') ?></div>
                        </td>
                        <td class="num small"><?= number_id($program['duration_hours'], 1) ?> jam</td>
                        <td class="num small"><?= $program['cost_per_participant'] > 0 ? rupiah($program['cost_per_participant']) : '<span class="muted">Gratis</span>' ?></td>
                        <td class="num small"><?= number_id($program['sessions']) ?></td>
                        <td class="num small"><b><?= number_id($program['alumni']) ?></b></td>
                        <td>
                            <div class="flex" style="gap:4px;justify-content:flex-end">
                                <a class="btn btn-sm btn-ghost" href="<?= url('trainings/catalog?edit=' . (int) $program['id']) ?>" title="Ubah"><?= icon('edit') ?></a>
                                <form method="post" action="<?= url('trainings/catalog/' . (int) $program['id'] . '/delete') ?>"
                                      onsubmit="return confirm('Hapus program ini dari katalog?');">
                                    <?= csrf_field() ?><input type="hidden" name="_method" value="DELETE">
                                    <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)" title="Hapus"><?= icon('trash') ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$programs): ?>
                    <tr><td colspan="8">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('layers') ?></div>
                            <h3>Katalog masih kosong</h3>
                            <p>Tambahkan program pelatihan pertama melalui formulir di samping.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" id="form-program">
        <div class="card__head">
            <div><h2><?= $isEdit ? 'Ubah Program' : 'Program Baru' ?></h2>
                <div class="sub"><?= $isEdit ? 'Perubahan tidak mengubah riwayat sesi yang sudah ada.' : 'Program menjadi acuan durasi &amp; biaya pada setiap sesi.' ?></div></div>
        </div>
        <form method="post" action="<?= $isEdit ? url('trainings/catalog/' . (int) $editing['id']) : url('trainings/catalog') ?>">
            <?= csrf_field() ?>
            <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
            <div class="card__body flex-col gap-12">
                <div class="form-grid two">
                    <div class="field">
                        <label>Kode <span class="req">*</span></label>
                        <input class="input mono" type="text" name="code" maxlength="30" required value="<?= e($val('code')) ?>" placeholder="TRN-LEAD-01">
                    </div>
                    <div class="field">
                        <label>Kategori <span class="req">*</span></label>
                        <select class="select" name="category">
                            <?php foreach ($categories as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $val('category', 'TEKNIS') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label>Nama program <span class="req">*</span></label>
                    <input class="input" type="text" name="name" maxlength="150" required value="<?= e($val('name')) ?>" placeholder="Contoh: Leadership Fundamentals">
                </div>
                <div class="form-grid two">
                    <div class="field">
                        <label>Jenis <span class="req">*</span></label>
                        <select class="select" name="type">
                            <?php foreach ($types as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $val('type', 'INTERNAL') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Penyedia / narasumber</label>
                        <input class="input" type="text" name="provider" maxlength="120" value="<?= e($val('provider')) ?>" placeholder="Internal HRGA / lembaga pelatihan">
                    </div>
                    <div class="field">
                        <label>Durasi (jam)</label>
                        <input class="input" type="number" name="duration_hours" min="0" max="999" step="0.5" value="<?= e($val('duration_hours', '8')) ?>">
                    </div>
                    <div class="field">
                        <label>Biaya per peserta (Rp)</label>
                        <input class="input" type="number" name="cost_per_participant" min="0" step="1000" value="<?= e($val('cost_per_participant', '0')) ?>">
                    </div>
                </div>
                <div class="field">
                    <label>Deskripsi</label>
                    <textarea class="textarea" name="description" rows="3" maxlength="500" placeholder="Tujuan pelatihan, materi utama, dan prasyarat peserta."><?= e($val('description')) ?></textarea>
                </div>
                <div class="field">
                    <label class="checkbox">
                        <input type="checkbox" name="is_mandatory" value="1" <?= $val('is_mandatory', '0') === '1' ? 'checked' : '' ?>>
                        <span>Wajib diikuti (kepatuhan / onboarding)</span>
                    </label>
                    <label class="checkbox" style="margin-top:6px">
                        <input type="checkbox" name="is_active" value="1" <?= $isEdit ? ($val('is_active', '1') === '1' ? 'checked' : '') : 'checked' ?>>
                        <span>Program aktif dan dapat dipakai pada sesi baru</span>
                    </label>
                </div>
            </div>
            <div class="card__foot" style="padding:12px 16px;display:flex;gap:8px;justify-content:flex-end">
                <?php if ($isEdit): ?>
                    <a class="btn btn-ghost" href="<?= url('trainings/catalog') ?>">Batal</a>
                <?php endif; ?>
                <button class="btn btn-primary" type="submit"><?= icon($isEdit ? 'check' : 'plus') ?> <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Program' ?></button>
            </div>
        </form>
    </section>
</div>
