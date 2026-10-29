<?php
/**
 * Periode penilaian (HR).
 * @var array $periods, $units, $types, $statuses, $statusTones, $indicators
 * @var array|null $editing
 * @var array $errors, $old
 * @var float $weightTotal
 * @var int $defaultSelfWeight
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
$indicatorWeight = $weightTotal;
?>
<div class="page-head">
    <div>
        <h1>Periode Penilaian</h1>
        <div class="sub">Siapkan siklus penilaian, tentukan bobot self assessment, lalu buka periode untuk seluruh karyawan.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('performance/indicators') ?>"><?= icon('layers') ?> Indikator</a>
        <a class="btn btn-outline" href="<?= url('performance') ?>"><?= icon('award') ?> Monitor Penilaian</a>
    </div>
</div>

<?php if ($indicatorWeight <= 0): ?>
    <div class="alert alert-warning" style="margin-bottom:14px">
        <?= icon('alert') ?>
        <div>Belum ada indikator aktif. Tambahkan indikator penilaian terlebih dahulu sebelum membuka periode.</div>
        <a class="btn btn-sm btn-outline" href="<?= url('performance/indicators') ?>">Atur Indikator</a>
    </div>
<?php elseif (abs($indicatorWeight - 100) > 0.01): ?>
    <div class="alert alert-info" style="margin-bottom:14px">
        <?= icon('info') ?>
        <div>Total bobot indikator aktif saat ini <b><?= number_format($indicatorWeight, 0, ',', '.') ?></b> (bukan 100). Nilai dihitung proporsional terhadap total bobot, jadi penilaian tetap valid.</div>
    </div>
<?php endif; ?>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Daftar Periode</h2>
                <div class="sub"><?= count($periods) ?> periode terdaftar. Urut dari yang terbaru.</div></div>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th>Periode</th><th>Cakupan</th><th>Rentang</th><th class="num">Peserta</th>
                    <th>Progres</th><th>Status</th><th style="width:150px">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($periods as $period): ?>
                    <tr>
                        <td>
                            <b><?= e($period['name']) ?></b>
                            <div class="muted small" style="font-size:9.8px">
                                <?= e($types[$period['type']] ?? $period['type']) ?> · bobot self <?= (int) $period['self_weight'] ?>%
                            </div>
                        </td>
                        <td class="small"><?= e($period['unit_name'] ?? 'Semua unit') ?></td>
                        <td class="small muted"><?= tanggal($period['start_date'], 'd M Y') ?><br><?= tanggal($period['end_date'], 'd M Y') ?></td>
                        <td class="num small"><b><?= number_id($period['participants']) ?></b></td>
                        <td class="small" style="min-width:120px">
                            <div class="progress thin"><span style="width:<?= (int) ($period['participants'] > 0 ? round($period['finalized'] / $period['participants'] * 100) : 0) ?>%"></span></div>
                            <div class="muted" style="font-size:9.6px;margin-top:3px">
                                self <?= number_id($period['self_done']) ?> · atasan <?= number_id($period['manager_done']) ?> · final <?= number_id($period['finalized']) ?>
                            </div>
                        </td>
                        <td><span class="badge badge-<?= $statusTones[$period['status']] ?? 'slate' ?>"><?= e($statuses[$period['status']] ?? $period['status']) ?></span></td>
                        <td>
                            <div class="flex" style="gap:4px;justify-content:flex-end;flex-wrap:wrap">
                                <a class="btn btn-sm btn-ghost" href="<?= url('performance/periods?edit=' . (int) $period['id']) ?>" title="Ubah"><?= icon('edit') ?></a>
                                <?php if ($period['status'] === 'DRAFT'): ?>
                                    <form method="post" action="<?= url('performance/periods/' . (int) $period['id'] . '/open') ?>"
                                          onsubmit="return confirm('Buka periode ini? Self assessment akan dikirim ke <?= (int) $period['participants'] > 0 ? 'peserta baru' : 'seluruh karyawan aktif' ?>.');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-primary" type="submit" title="Buka periode"><?= icon('send') ?> Buka</button>
                                    </form>
                                    <form method="post" action="<?= url('performance/periods/' . (int) $period['id'] . '/delete') ?>"
                                          onsubmit="return confirm('Hapus periode draf ini?');">
                                        <?= csrf_field() ?><input type="hidden" name="_method" value="DELETE">
                                        <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)" title="Hapus"><?= icon('trash') ?></button>
                                    </form>
                                <?php elseif ($period['status'] === 'SELF' || $period['status'] === 'MANAGER'): ?>
                                    <form method="post" action="<?= url('performance/periods/' . (int) $period['id'] . '/calibration') ?>"><?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline" type="submit" title="Masuk kalibrasi"><?= icon('sliders') ?> Kalibrasi</button>
                                    </form>
                                <?php elseif ($period['status'] === 'CALIBRATION'): ?>
                                    <form method="post" action="<?= url('performance/periods/' . (int) $period['id'] . '/close') ?>"
                                          onsubmit="return confirm('Tutup periode ini? Penilaian yang belum final tidak dapat diubah lagi.');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline" type="submit" title="Tutup periode"><?= icon('lock') ?> Tutup</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$periods): ?>
                    <tr><td colspan="7">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('calendar') ?></div>
                            <h3>Belum ada periode</h3>
                            <p>Buat periode penilaian pertama melalui formulir di samping.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card__head">
            <div><h2><?= $isEdit ? 'Ubah Periode' : 'Periode Baru' ?></h2>
                <div class="sub"><?= $isEdit ? 'Perubahan berlaku untuk penilaian yang belum final.' : 'Periode dibuat sebagai draf dan dapat dibuka nanti.' ?></div></div>
        </div>
        <form method="post" action="<?= $isEdit ? url('performance/periods/' . (int) $editing['id']) : url('performance/periods') ?>">
            <?= csrf_field() ?>
            <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
            <div class="card__body flex-col gap-12">
                <div class="field">
                    <label>Nama periode <span class="req">*</span></label>
                    <input class="input" type="text" name="name" maxlength="120" required
                           value="<?= e($val('name')) ?>" placeholder="Contoh: Penilaian Kinerja Tahunan 2026">
                </div>
                <div class="form-grid two">
                    <div class="field">
                        <label>Jenis <span class="req">*</span></label>
                        <select class="select" name="type">
                            <?php foreach ($types as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $val('type', 'TAHUNAN') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Cakupan unit</label>
                        <select class="select" name="business_unit_id">
                            <option value="0">Semua unit</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= (int) $unit['id'] ?>" <?= (int) $val('business_unit_id', '0') === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Tanggal mulai <span class="req">*</span></label>
                        <input class="input" type="date" name="start_date" required value="<?= e($val('start_date')) ?>">
                    </div>
                    <div class="field">
                        <label>Tanggal selesai <span class="req">*</span></label>
                        <input class="input" type="date" name="end_date" required value="<?= e($val('end_date')) ?>">
                    </div>
                    <div class="field">
                        <label>Batas self assessment</label>
                        <input class="input" type="date" name="self_due" value="<?= e($val('self_due')) ?>">
                    </div>
                    <div class="field">
                        <label>Batas penilaian atasan</label>
                        <input class="input" type="date" name="manager_due" value="<?= e($val('manager_due')) ?>">
                    </div>
                </div>
                <div class="field">
                    <label>Bobot self assessment (%)</label>
                    <input class="input" type="number" name="self_weight" min="0" max="70" step="5"
                           value="<?= e($val('self_weight', (string) $defaultSelfWeight)) ?>">
                    <span class="hint">Sisa bobot menjadi milik atasan. Contoh 30 → nilai akhir = 30% self + 70% atasan.</span>
                </div>
                <div class="field">
                    <label>Catatan</label>
                    <textarea class="textarea" name="note" rows="3" maxlength="255" placeholder="Konteks periode, mis. penilaian tahun buku 2026."><?= e($val('note')) ?></textarea>
                </div>
            </div>
            <div class="card__foot" style="padding:12px 16px;display:flex;gap:8px;justify-content:flex-end">
                <?php if ($isEdit): ?>
                    <a class="btn btn-ghost" href="<?= url('performance/periods') ?>">Batal</a>
                <?php endif; ?>
                <button class="btn btn-primary" type="submit"><?= icon($isEdit ? 'check' : 'plus') ?> <?= $isEdit ? 'Simpan Perubahan' : 'Buat Periode' ?></button>
            </div>
        </form>
    </section>
</div>
