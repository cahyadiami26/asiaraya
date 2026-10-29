<?php
/**
 * Daftar lowongan rekrutmen (HR).
 * @var array $jobs, $filters, $stats, $units, $departments, $positions, $managers
 * @var array $statuses, $statusTones, $workModes, $employmentTypes
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
$today = date('Y-m-d');
?>
<div class="page-head">
    <div>
        <h1>Rekrutmen &amp; Onboarding</h1>
        <div class="sub">Kelola lowongan, pipeline pelamar, jadwal wawancara, hingga konversi pelamar menjadi karyawan.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('recruitment/candidates') ?>"><?= icon('users') ?> Data Pelamar</a>
        <a class="btn btn-primary" href="#form-lowongan"><?= icon('plus') ?> Lowongan Baru</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Lowongan Dibuka</span>
        <span class="v"><?= number_id($stats['open_jobs']) ?></span>
        <span class="s"><?= number_id($stats['draft_jobs']) ?> draf menunggu publikasi</span>
    </div>
    <div class="mini-stat">
        <span class="k">Total Pelamar</span>
        <span class="v" style="color:var(--indigo-600)"><?= number_id($stats['applicants']) ?></span>
        <span class="s"><?= number_id($stats['fresh']) ?> belum diseleksi</span>
    </div>
    <div class="mini-stat">
        <span class="k">Dalam Proses</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($stats['in_pipeline']) ?></span>
        <span class="s"><?= number_id($stats['offers']) ?> pada tahap penawaran</span>
    </div>
    <div class="mini-stat">
        <span class="k">Diterima</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id($stats['hired']) ?></span>
        <span class="s"><?= number_id($stats['hired_month']) ?> bulan ini · konversi <?= (int) $stats['conversion'] ?>%</span>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Daftar Lowongan</h2>
                <div class="sub"><?= number_id(count($jobs)) ?> lowongan sesuai filter. Lowongan dibuka tampil lebih dulu.</div></div>
        </div>

        <form class="filter-bar" method="get" action="<?= url('recruitment') ?>">
            <select class="select" name="status" data-auto-submit style="min-width:150px">
                <option value="">Semua status</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="unit" data-auto-submit style="min-width:170px">
                <option value="">Semua unit</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= (int) $unit['id'] ?>" <?= (int) $filters['unit'] === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="input-group" style="max-width:200px">
                <?= icon('search') ?>
                <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Judul / kode / lokasi…">
            </div>
            <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
            <?php if ($filters['status'] || $filters['unit'] || $filters['q']): ?>
                <a class="btn btn-sm btn-ghost" href="<?= url('recruitment') ?>"><?= icon('x') ?> Reset</a>
            <?php endif; ?>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th>Lowongan</th><th>Posisi &amp; Unit</th><th>Jenis</th>
                    <th class="num">Kuota</th><th class="num">Pelamar</th><th>Status</th>
                    <th style="width:150px">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($jobs as $job): ?>
                    <?php
                    $headcount = max(1, (int) $job['headcount']);
                    $hired = (int) $job['hired'];
                    $pct = min(100, (int) round($hired / $headcount * 100));
                    ?>
                    <tr>
                        <td>
                            <a class="link-quiet" href="<?= url('recruitment/jobs/' . (int) $job['id']) ?>"><b><?= e($job['title']) ?></b></a>
                            <div class="chip-row" style="margin-top:4px">
                                <span class="chip chip-slate"><?= e($job['code']) ?></span>
                                <?php if ($job['close_date']): ?>
                                    <span class="chip chip-amber">Tutup <?= tanggal($job['close_date'], 'd M Y') ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="small">
                            <?= e($job['position_name'] ?: '—') ?>
                            <div class="muted" style="font-size:9.8px"><?= e(($job['department_name'] ?: '—') . ' · ' . ($job['unit_name'] ?: '—')) ?></div>
                        </td>
                        <td class="small">
                            <?= e($job['employment_type']) ?>
                            <div class="muted" style="font-size:9.8px"><?= e($workModes[$job['work_mode']] ?? $job['work_mode']) ?><?= $job['location'] ? ' · ' . e($job['location']) : '' ?></div>
                        </td>
                        <td class="num small" style="min-width:92px">
                            <b><?= number_id($hired) ?></b> / <?= number_id($headcount) ?>
                            <div class="progress thin" style="margin-top:4px"><span style="width:<?= $pct ?>%"></span></div>
                        </td>
                        <td class="num small">
                            <b><?= number_id($job['applicants']) ?></b>
                            <?php if ((int) $job['fresh'] > 0): ?>
                                <div style="font-size:9.6px;color:var(--indigo-600);margin-top:3px"><?= number_id($job['fresh']) ?> baru</div>
                            <?php else: ?>
                                <div class="muted" style="font-size:9.6px;margin-top:3px"><?= number_id($job['in_pipeline']) ?> diproses</div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?= $statusTones[$job['status']] ?? 'slate' ?>"><?= e($statuses[$job['status']] ?? $job['status']) ?></span></td>
                        <td>
                            <div class="flex" style="gap:4px;justify-content:flex-end;flex-wrap:wrap">
                                <a class="btn btn-sm btn-ghost" href="<?= url('recruitment/jobs/' . (int) $job['id']) ?>" title="Pipeline pelamar"><?= icon('eye') ?></a>
                                <a class="btn btn-sm btn-ghost" href="<?= url('recruitment?edit=' . (int) $job['id']) ?>" title="Ubah lowongan"><?= icon('edit') ?></a>
                                <?php if ($job['status'] === 'DRAFT' || $job['status'] === 'ON_HOLD'): ?>
                                    <form method="post" action="<?= url('recruitment/jobs/' . (int) $job['id'] . '/open') ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-primary" type="submit" title="Buka lowongan"><?= icon('send') ?> Buka</button>
                                    </form>
                                <?php elseif ($job['status'] === 'OPEN'): ?>
                                    <form method="post" action="<?= url('recruitment/jobs/' . (int) $job['id'] . '/hold') ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline" type="submit" title="Tahan lowongan"><?= icon('clock') ?> Tahan</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$jobs): ?>
                    <tr><td colspan="7">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('briefcase') ?></div>
                            <h3>Belum ada lowongan</h3>
                            <p>Buat lowongan pertama melalui formulir di samping untuk mulai menerima pelamar.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" id="form-lowongan">
        <div class="card__head">
            <div><h2><?= $isEdit ? 'Ubah Lowongan' : 'Lowongan Baru' ?></h2>
                <div class="sub"><?= $isEdit ? 'Perubahan berlaku pada pipeline yang sedang berjalan.' : 'Lowongan dibuat sebagai draf, buka setelah detail disetujui.' ?></div></div>
        </div>
        <form method="post" action="<?= $isEdit ? url('recruitment/jobs/' . (int) $editing['id']) : url('recruitment') ?>">
            <?= csrf_field() ?>
            <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
            <div class="card__body flex-col gap-12">
                <div class="form-grid two">
                    <div class="field">
                        <label>Kode lowongan <span class="req">*</span></label>
                        <input class="input mono" type="text" name="code" maxlength="30" required value="<?= e($val('code')) ?>" placeholder="JOB-2026-001">
                    </div>
                    <div class="field">
                        <label>Kuota (orang) <span class="req">*</span></label>
                        <input class="input" type="number" name="headcount" min="1" max="99" value="<?= e($val('headcount', '1')) ?>">
                    </div>
                </div>
                <div class="field">
                    <label>Judul lowongan <span class="req">*</span></label>
                    <input class="input" type="text" name="title" maxlength="150" required value="<?= e($val('title')) ?>" placeholder="Contoh: Staff Accounting">
                </div>
                <div class="form-grid two">
                    <div class="field">
                        <label>Business unit</label>
                        <select class="select" name="business_unit_id">
                            <option value="0">— Pilih unit —</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= (int) $unit['id'] ?>" <?= (int) $val('business_unit_id', '0') === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Departemen</label>
                        <select class="select" name="department_id">
                            <option value="0">— Pilih departemen —</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= (int) $department['id'] ?>" <?= (int) $val('department_id', '0') === (int) $department['id'] ? 'selected' : '' ?>>
                                    <?= e($department['name']) ?><?= $department['unit_name'] ? ' · ' . e($department['unit_name']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Posisi</label>
                        <select class="select" name="position_id">
                            <option value="0">— Pilih posisi —</option>
                            <?php foreach ($positions as $position): ?>
                                <option value="<?= (int) $position['id'] ?>" <?= (int) $val('position_id', '0') === (int) $position['id'] ? 'selected' : '' ?>>
                                    <?= e($position['name']) ?><?= $position['department_name'] ? ' · ' . e($position['department_name']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Atasan / hiring manager</label>
                        <select class="select" name="hiring_manager_id">
                            <option value="0">— Belum ditentukan —</option>
                            <?php foreach ($managers as $manager): ?>
                                <option value="<?= (int) $manager['id'] ?>" <?= (int) $val('hiring_manager_id', '0') === (int) $manager['id'] ? 'selected' : '' ?>><?= e($manager['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-grid two">
                    <div class="field">
                        <label>Status kepegawaian <span class="req">*</span></label>
                        <select class="select" name="employment_type">
                            <?php foreach ($employmentTypes as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $val('employment_type', 'PKWT') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Mode kerja <span class="req">*</span></label>
                        <select class="select" name="work_mode">
                            <?php foreach ($workModes as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $val('work_mode', 'ONSITE') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Tanggal buka</label>
                        <input class="input" type="date" name="open_date" value="<?= e($val('open_date', $today)) ?>">
                    </div>
                    <div class="field">
                        <label>Tanggal tutup</label>
                        <input class="input" type="date" name="close_date" value="<?= e($val('close_date')) ?>">
                    </div>
                </div>
                <div class="field">
                    <label>Lokasi kerja</label>
                    <input class="input" type="text" name="location" maxlength="150" value="<?= e($val('location')) ?>" placeholder="Jakarta HQ / Remote / Surabaya Plant">
                </div>
                <div class="form-grid two">
                    <div class="field">
                        <label>Gaji minimum (Rp)</label>
                        <input class="input" type="number" name="salary_min" min="0" step="100000" value="<?= e($val('salary_min', '0')) ?>">
                    </div>
                    <div class="field">
                        <label>Gaji maksimum (Rp)</label>
                        <input class="input" type="number" name="salary_max" min="0" step="100000" value="<?= e($val('salary_max', '0')) ?>">
                        <span class="hint">Isi 0 bila nominal dirahasiakan.</span>
                    </div>
                </div>
                <div class="field">
                    <label>Deskripsi pekerjaan</label>
                    <textarea class="textarea" name="description" rows="3" maxlength="1000" placeholder="Tanggung jawab utama, cakupan kerja, dan target posisi."><?= e($val('description')) ?></textarea>
                </div>
                <div class="field">
                    <label>Kualifikasi</label>
                    <textarea class="textarea" name="requirements" rows="3" maxlength="1000" placeholder="Pendidikan, pengalaman, dan keterampilan yang dibutuhkan."><?= e($val('requirements')) ?></textarea>
                </div>
                <div class="field">
                    <label>Catatan internal</label>
                    <input class="input" type="text" name="note" maxlength="255" value="<?= e($val('note')) ?>" placeholder="Catatan untuk tim rekrutmen (tidak tampil ke pelamar).">
                </div>
            </div>
            <div class="card__foot" style="padding:12px 16px;display:flex;gap:8px;justify-content:flex-end">
                <?php if ($isEdit): ?>
                    <a class="btn btn-ghost" href="<?= url('recruitment') ?>">Batal</a>
                <?php endif; ?>
                <button class="btn btn-primary" type="submit"><?= icon($isEdit ? 'check' : 'plus') ?> <?= $isEdit ? 'Simpan Perubahan' : 'Buat Lowongan' ?></button>
            </div>
        </form>
    </section>
</div>
