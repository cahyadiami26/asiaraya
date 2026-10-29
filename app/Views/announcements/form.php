<?php
/**
 * Form tulis/ubah pengumuman.
 * @var array|null $item
 * @var array $categories, $audiences, $targets, $roles, $statuses, $errors, $old
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

// Target terpilih: dari input lama (validasi gagal) atau dari database.
$savedTargets = $targets['saved'] ?? [];
$oldTargets = $old['audience_targets'] ?? null;
$selectedTargets = is_array($oldTargets) ? array_map('strval', $oldTargets) : $savedTargets;

$isAll = in_array('ALL', $selectedTargets, true) || !$selectedTargets;
$employeeTargets = [];
foreach ($selectedTargets as $key) {
    if (str_starts_with($key, 'EMPLOYEE:')) {
        $employeeTargets[] = (int) substr($key, 9);
    }
}
$hasEmployeeTargets = $employeeTargets !== [];

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
                    <div class="field">
                        <label>Kategori <span class="req">*</span></label>
                        <select class="select" name="category" required style="max-width:280px">
                            <?php foreach ($categories as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $val('category', 'INFO') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php
                    $picked = static fn (string $key): bool => in_array($key, $selectedTargets, true);
                    $groups = [
                        ['key' => 'UNIT', 'title' => 'Unit bisnis', 'icon' => 'building',
                         'items' => array_map(static fn ($u) => ['value' => (string) (int) $u['id'], 'label' => $u['name'], 'sub' => $u['code'] ?? ''], $targets['units'] ?? [])],
                        ['key' => 'DEPARTMENT', 'title' => 'Departemen', 'icon' => 'layers',
                         'items' => array_map(static fn ($d) => ['value' => (string) (int) $d['id'], 'label' => $d['name'], 'sub' => $d['code'] ?? ''], $targets['departments'] ?? [])],
                        ['key' => 'POSITION', 'title' => 'Jabatan', 'icon' => 'briefcase',
                         'items' => array_map(static fn ($o) => ['value' => (string) (int) $o['id'], 'label' => $o['name'], 'sub' => $o['code'] ?? ''], $targets['positions'] ?? [])],
                        ['key' => 'ROLE', 'title' => 'Peran sistem', 'icon' => 'shield',
                         'items' => array_map(static fn ($k) => ['value' => $k, 'label' => $roles[$k] ?? $k, 'sub' => ''], array_keys($roles))],
                        ['key' => 'EMPLOYEE', 'title' => 'Karyawan tertentu', 'icon' => 'user', 'search' => true,
                         'items' => array_map(static fn ($e) => ['value' => (string) (int) $e['id'], 'label' => $e['full_name'], 'sub' => $e['employee_code'] ?? ''], $targets['employees'] ?? [])],
                    ];
                    ?>

                    <div class="field">
                        <label>Audiens <span class="req">*</span></label>
                        <div class="audience-mode" data-audience-mode>
                            <label class="audience-mode__opt">
                                <input type="radio" name="audience_mode" value="all" <?= $isAll ? 'checked' : '' ?>>
                                <span><?= icon('users') ?><b>Semua karyawan aktif</b>
                                    <i>Seluruh karyawan, termasuk yang bergabung kemudian.</i></span>
                            </label>
                            <label class="audience-mode__opt">
                                <input type="radio" name="audience_mode" value="targeted" <?= $isAll ? '' : 'checked' ?>>
                                <span><?= icon('filter') ?><b>Target tertentu</b>
                                    <i>Pilih unit, departemen, jabatan, peran, dan/atau karyawan. Bisa dikombinasikan.</i></span>
                            </label>
                        </div>

                        <div class="audience-panel" data-audience-panel <?= $isAll ? 'hidden' : '' ?>>
                            <?php foreach ($groups as $group): ?>
                                <?php
                                $groupPicked = 0;
                                foreach ($group['items'] as $option) {
                                    if ($picked($group['key'] . ':' . $option['value'])) {
                                        $groupPicked++;
                                    }
                                }
                                $open = $groupPicked > 0;
                                ?>
                                <details class="target-group" <?= $open ? 'open' : '' ?>>
                                    <summary>
                                        <span class="target-group__icon"><?= icon($group['icon']) ?></span>
                                        <span class="target-group__title"><?= e($group['title']) ?></span>
                                        <span class="target-group__count" data-count><?= $groupPicked > 0 ? $groupPicked . ' dipilih' : count($group['items']) . ' tersedia' ?></span>
                                    </summary>
                                    <div class="target-group__body">
                                        <?php if (!empty($group['search'])): ?>
                                            <input class="input" type="search" data-target-search placeholder="Cari nama atau NIK…"
                                                   style="margin-bottom:8px" aria-label="Cari <?= e($group['title']) ?>">
                                        <?php endif; ?>
                                        <div class="target-list">
                                            <?php foreach ($group['items'] as $option): ?>
                                                <?php $value = $group['key'] . ':' . $option['value']; ?>
                                                <label class="target-item" data-name="<?= e(mb_strtolower($option['label'] . ' ' . $option['sub'])) ?>">
                                                    <input type="checkbox" name="audience_targets[]" value="<?= e($value) ?>"
                                                           <?= $picked($value) ? 'checked' : '' ?>>
                                                    <span>
                                                        <b><?= e($option['label']) ?></b>
                                                        <?php if ($option['sub'] !== ''): ?><i class="mono"><?= e($option['sub']) ?></i><?php endif; ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                            <?php if (!$group['items']): ?>
                                                <div class="small muted" style="padding:6px 2px">Belum ada data.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </details>
                            <?php endforeach; ?>

                            <div class="small muted" style="margin-top:6px">
                                Penerima akhir adalah gabungan seluruh pilihan di atas (tidak duplikat).
                                Kosongkan semua pilihan untuk memakai “Semua karyawan aktif”.
                            </div>
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
                            <div><span>Status</span><b><?= e($statuses[$item['status']] ?? $item['status']) ?></b></div>
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
    if (select && box) {
        var syncWhen = function () { box.hidden = select.value !== 'schedule'; };
        select.addEventListener('change', syncWhen);
        syncWhen();
    }

    /* ---------- audiens: mode semua vs target tertentu ---------- */
    var mode = document.querySelector('[data-audience-mode]');
    var panel = document.querySelector('[data-audience-panel]');
    var syncMode = function () {
        if (!mode || !panel) { return; }
        var picked = mode.querySelector('input[name="audience_mode"]:checked');
        var isTargeted = !!picked && picked.value === 'targeted';
        panel.hidden = !isTargeted;
        panel.querySelectorAll('input[name="audience_targets[]"]').forEach(function (box) {
            box.disabled = !isTargeted;
        });
    };
    if (mode) {
        mode.addEventListener('change', syncMode);
        syncMode();
    }

    /* ---------- hitung jumlah terpilih per kelompok ---------- */
    var refreshCounts = function (group) {
        var label = group.querySelector('[data-count]');
        if (!label) { return; }
        var boxes = group.querySelectorAll('input[name="audience_targets[]"]');
        var checked = 0;
        boxes.forEach(function (b) { if (b.checked) { checked++; } });
        label.textContent = checked > 0
            ? checked + ' dipilih'
            : (boxes.length + ' tersedia');
        label.classList.toggle('is-on', checked > 0);
    };

    document.querySelectorAll('.target-group').forEach(function (group) {
        refreshCounts(group);
        group.addEventListener('change', function (e) {
            if (e.target.matches('input[name="audience_targets[]"]')) { refreshCounts(group); }
        });
    });

    /* ---------- pencarian karyawan ---------- */
    document.querySelectorAll('[data-target-search]').forEach(function (input) {
        var group = input.closest('.target-group');
        if (!group) { return; }
        input.addEventListener('input', function () {
            var term = input.value.trim().toLowerCase();
            group.querySelectorAll('.target-item').forEach(function (item) {
                var name = item.getAttribute('data-name') || '';
                item.hidden = term !== '' && name.indexOf(term) === -1;
            });
        });
    });
})();
</script>
