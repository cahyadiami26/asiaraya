<?php
/**
 * Jenis & jatah cuti (HR).
 * @var array $types, $policy, $stats, $editing, $errors, $old
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
$checked = static function (string $key, bool $default = false) use ($val): bool {
    return (string) $val($key, $default ? '1' : '0') === '1';
};
$isEdit = $editing !== null;
?>
<div class="page-head">
    <div>
        <h1>Jenis &amp; Jatah Cuti</h1>
        <div class="sub">Kategori cuti, jatah tahunan, batas per pengajuan, dan syarat lampiran.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('leave/balances') ?>"><?= icon('layers') ?> Saldo Karyawan</a>
        <a class="btn btn-primary" href="<?= url('leave') ?>"><?= icon('file') ?> Manajemen Cuti</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Jenis Aktif</span>
        <span class="v"><?= number_id(count(array_filter($types, static fn ($t) => (int) $t['is_active'] === 1))) ?></span>
        <span class="s">dari <?= count($types) ?> jenis terdaftar</span>
    </div>
    <div class="mini-stat">
        <span class="k">Total Jatah</span>
        <span class="v"><?= number_id(array_sum(array_map(static fn ($t) => (float) $t['quota_days'], $types)), 0) ?></span>
        <span class="s">hari per karyawan (semua jenis)</span>
    </div>
    <div class="mini-stat">
        <span class="k">Hari Terpakai</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id(array_sum(array_map(static fn ($t) => (float) $t['used_total'], $types)), 0) ?></span>
        <span class="s">akumulasi seluruh periode</span>
    </div>
    <div class="mini-stat">
        <span class="k">Saldo Menipis</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($stats['low']) ?></span>
        <span class="s">sisa ≤ 2 hari pada tahun ini</span>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Daftar Jenis Cuti</h2>
                <div class="sub">Jatah menentukan saldo awal saat generate tahunan.</div></div>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th>Jenis</th><th class="num">Jatah</th><th class="num">Maks/Pengajuan</th>
                    <th class="num">Notice</th><th>Ketentuan</th><th class="num">Terpakai</th><th>Status</th><th style="width:96px">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($types as $type): ?>
                    <tr>
                        <td>
                            <div class="name-cell">
                                <span class="type-dot" style="background:<?= e($type['color']) ?>"></span>
                                <span>
                                    <b><?= e($type['name']) ?></b>
                                    <span class="mono"><?= e($type['code']) ?></span>
                                </span>
                            </div>
                        </td>
                        <td class="num small"><?= (int) $type['quota_days'] === 0 ? 'tanpa jatah' : number_id($type['quota_days']) . ' hari' ?></td>
                        <td class="num small"><?= (int) $type['max_per_request'] > 0 ? number_id($type['max_per_request']) . ' hari' : '—' ?></td>
                        <td class="num small"><?= (int) $type['min_notice_days'] > 0 ? (int) $type['min_notice_days'] . ' hari' : 'bebas' ?></td>
                        <td class="small">
                            <div class="flex wrap" style="gap:4px">
                                <?= (int) $type['is_paid'] === 1 ? '<span class="badge badge-teal">Dibayar</span>' : '<span class="badge badge-rose">Tanpa upah</span>' ?>
                                <?php if ((int) $type['requires_attachment'] === 1): ?><span class="badge badge-indigo">Lampiran</span><?php endif; ?>
                                <?php if ((int) $type['carry_over'] === 1): ?><span class="badge badge-slate">Carry over</span><?php endif; ?>
                                <?php if ((int) $type['deduct_salary'] === 1): ?><span class="badge badge-amber">Potong gaji</span><?php endif; ?>
                            </div>
                        </td>
                        <td class="num small">
                            <?= number_id($type['used_total'], 1) ?> h
                            <div class="muted" style="font-size:9.6px"><?= number_id($type['approved_count']) ?> pengajuan</div>
                        </td>
                        <td><?= (int) $type['is_active'] === 1 ? '<span class="badge badge-teal">Aktif</span>' : '<span class="badge badge-slate">Nonaktif</span>' ?></td>
                        <td>
                            <div class="flex" style="gap:3px">
                                <a class="btn btn-sm btn-ghost" href="<?= url('leave/types?edit=' . $type['id']) ?>" title="Ubah"><?= icon('edit') ?></a>
                                <?php if ((int) $type['request_count'] === 0 && (int) $type['balance_count'] === 0): ?>
                                    <form class="inline-form" method="post" action="<?= url('leave/types/' . $type['id'] . '/delete') ?>"
                                          data-confirm="Hapus jenis cuti <?= e($type['name']) ?>?">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)" title="Hapus"><?= icon('trash') ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$types): ?>
                    <tr><td colspan="8" class="table-empty"><?= icon('file') ?>Belum ada jenis cuti.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card__foot" style="padding:11px 16px">
            <span class="small muted">
                Jenis yang sudah dipakai pengajuan tidak dapat dihapus — nonaktifkan agar riwayat tetap utuh.
            </span>
        </div>
    </section>

    <div class="flex-col gap-16">
        <section class="card" id="type-form">
            <div class="card__head">
                <div><h2><?= $isEdit ? 'Ubah Jenis Cuti' : 'Tambah Jenis Cuti' ?></h2>
                    <div class="sub"><?= $isEdit ? e($editing['name']) : 'Kategori baru untuk pengajuan karyawan.' ?></div></div>
                <?php if ($isEdit): ?>
                    <a class="btn btn-sm btn-ghost" href="<?= url('leave/types') ?>"><?= icon('x') ?> Batal</a>
                <?php endif; ?>
            </div>
            <form class="card__body" method="post" action="<?= url($isEdit ? 'leave/types/' . $editing['id'] : 'leave/types') ?>">
                <?= csrf_field() ?>
                <div class="form-grid">
                    <div class="field">
                        <label>Kode <span class="req">*</span></label>
                        <input class="input" name="code" maxlength="20" value="<?= e($val('code')) ?>" placeholder="CT" required>
                        <?php if (!empty($errors['code'])): ?><span class="err"><?= e($errors['code']) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label>Nama <span class="req">*</span></label>
                        <input class="input" name="name" maxlength="100" value="<?= e($val('name')) ?>" placeholder="Cuti Tahunan" required>
                    </div>
                    <div class="field">
                        <label>Jatah Tahunan (hari)</label>
                        <input class="input" type="number" name="quota_days" min="0" max="365" step="0.5" value="<?= e($val('quota_days', $policy['default_quota'])) ?>" required>
                        <span class="hint">Isi 0 untuk cuti tanpa jatah (mis. cuti tanpa upah).</span>
                    </div>
                    <div class="field">
                        <label>Maks per Pengajuan</label>
                        <input class="input" type="number" name="max_per_request" min="1" max="365" step="0.5" value="<?= e($val('max_per_request', 12)) ?>">
                    </div>
                    <div class="field">
                        <label>Minimal Pemberitahuan (hari)</label>
                        <input class="input" type="number" name="min_notice_days" min="0" max="90" value="<?= e($val('min_notice_days', 0)) ?>">
                        <span class="hint">0 = boleh diajukan hari yang sama.</span>
                    </div>
                    <div class="field">
                        <label>Warna Label</label>
                        <input class="input" type="color" name="color" value="<?= e($val('color', '#0d9488')) ?>" style="height:38px;padding:3px">
                    </div>
                </div>

                <div class="divider"></div>
                <div class="flex-col" style="gap:9px">
                    <label class="checkbox"><input type="checkbox" name="is_paid" value="1" <?= $checked('is_paid', true) ? 'checked' : '' ?>> <span>Cuti berbayar (tidak memotong gaji)</span></label>
                    <label class="checkbox"><input type="checkbox" name="requires_attachment" value="1" <?= $checked('requires_attachment') ? 'checked' : '' ?>> <span>Wajib lampiran (surat dokter / keterangan)</span></label>
                    <label class="checkbox"><input type="checkbox" name="carry_over" value="1" <?= $checked('carry_over') ? 'checked' : '' ?>> <span>Boleh carry over ke tahun berikutnya</span></label>
                    <label class="checkbox"><input type="checkbox" name="deduct_salary" value="1" <?= $checked('deduct_salary') ? 'checked' : '' ?>> <span>Potong gaji saat dipakai</span></label>
                    <label class="checkbox"><input type="checkbox" name="is_active" value="1" <?= $checked('is_active', true) ? 'checked' : '' ?>> <span>Aktif (bisa dipilih karyawan)</span></label>
                    <?php if ($isEdit): ?>
                        <label class="checkbox"><input type="checkbox" name="sync_quota" value="1"> <span>Sinkronkan jatah baru ke saldo <?= (int) date('Y') ?> dan setelahnya</span></label>
                    <?php endif; ?>
                </div>

                <div class="form-actions" style="margin-top:14px">
                    <button class="btn btn-primary btn-block" type="submit"><?= icon('check') ?> <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Jenis Cuti' ?></button>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Kebijakan Cuti</h2>
                <div class="sub">Berlaku untuk seluruh pengajuan.</div></div></div>
            <div class="card__body">
                <div class="ot-policy">
                    <div><span class="k">Jatah default</span><span class="v"><?= number_id($policy['default_quota'], 1) ?> hari</span></div>
                    <div><span class="k">Saldo minus</span><span class="v"><?= $policy['allow_negative'] ? 'Diizinkan' : 'Ditolak' ?></span></div>
                    <div><span class="k">Rentang maks</span><span class="v"><?= number_id($policy['max_range_days']) ?> hari</span></div>
                    <div><span class="k">Hari dihitung</span><span class="v">Senin–Jumat</span></div>
                </div>
                <div class="hint" style="margin-top:10px">
                    Akhir pekan dan hari besar pada tabel <span class="mono">holidays</span> tidak dihitung sebagai hari cuti.
                </div>
            </div>
        </section>
    </div>
</div>
