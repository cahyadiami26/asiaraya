<?php
/**
 * Daftar seluruh pelamar (HR).
 * @var array $candidates, $filters, $jobs, $openJobs, $stats, $stages, $stageTones, $sources, $educations, $paginator
 * @var callable $pageLink
 * @var array $errors, $old
 */
$stars = static function (?int $rating): string {
    if (!$rating) {
        return '<span class="muted small">—</span>';
    }
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<span class="star' . ($i <= $rating ? ' is-on' : '') . '">★</span>';
    }
    return '<span class="star-row">' . $out . '</span>';
};
$exportQuery = http_build_query(array_filter([
    'job'    => $filters['job'] ?: null,
    'status' => $filters['status'] ?: null,
    'source' => $filters['source'] ?: null,
    'q'      => $filters['q'] ?: null,
], static fn ($v) => $v !== null && $v !== ''));
$defaultJob = (int) ($old['job_id'] ?? ($filters['job'] ?: 0));
?>
<div class="page-head">
    <div>
        <h1>Data Pelamar</h1>
        <div class="sub">Seluruh pelamar lintas lowongan beserta tahapan seleksi dan hasil wawancara.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('recruitment') ?>"><?= icon('briefcase') ?> Lowongan</a>
        <a class="btn btn-outline" href="<?= url('recruitment/export' . ($exportQuery ? '?' . $exportQuery : '')) ?>"><?= icon('download') ?> Export CSV</a>
        <a class="btn btn-primary" href="#form-pelamar"><?= icon('plus') ?> Pelamar Baru</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Total Pelamar</span>
        <span class="v"><?= number_id($stats['applicants']) ?></span>
        <span class="s"><?= number_id($stats['fresh']) ?> belum diseleksi</span>
    </div>
    <div class="mini-stat">
        <span class="k">Dalam Proses</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($stats['in_pipeline']) ?></span>
        <span class="s">berkas · wawancara · penawaran</span>
    </div>
    <div class="mini-stat">
        <span class="k">Diterima</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id($stats['hired']) ?></span>
        <span class="s">konversi <?= (int) $stats['conversion'] ?>% dari pelamar</span>
    </div>
    <div class="mini-stat">
        <span class="k">Rata-rata Waktu Rekrut</span>
        <span class="v"><?= $stats['avg_days'] !== null ? number_id($stats['avg_days']) . ' <span class="muted" style="font-size:13px">hari</span>' : '—' ?></span>
        <span class="s">dari pelamar masuk sampai diterima</span>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Pipeline Pelamar</h2>
                <div class="sub"><?= number_id($paginator->total) ?> pelamar sesuai filter.</div></div>
        </div>

        <form class="filter-bar" method="get" action="<?= url('recruitment/candidates') ?>">
            <select class="select" name="job" data-auto-submit style="min-width:190px">
                <option value="">Semua lowongan</option>
                <?php foreach ($jobs as $job): ?>
                    <option value="<?= (int) $job['id'] ?>" <?= (int) $filters['job'] === (int) $job['id'] ? 'selected' : '' ?>><?= e($job['title']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="status" data-auto-submit style="min-width:160px">
                <option value="">Semua tahap</option>
                <?php foreach ($stages as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="source" data-auto-submit style="min-width:150px">
                <option value="">Semua sumber</option>
                <?php foreach ($sources as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filters['source'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="input-group" style="max-width:190px">
                <?= icon('search') ?>
                <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Nama / email / telepon…">
            </div>
            <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
            <?php if ($exportQuery): ?>
                <a class="btn btn-sm btn-ghost" href="<?= url('recruitment/candidates') ?>"><?= icon('x') ?> Reset</a>
            <?php endif; ?>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th>Pelamar</th><th>Lowongan</th><th>Sumber</th><th class="num">Exp.</th>
                    <th>Tahap</th><th>Rating</th><th>Masuk</th><th style="width:56px"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($candidates as $candidate): ?>
                    <tr>
                        <td>
                            <div class="flex" style="gap:9px;align-items:center">
                                <div class="avatar" style="background:<?= avatar_color($candidate['full_name']) ?>"><?= e(initials($candidate['full_name'])) ?></div>
                                <div>
                                    <b><?= e($candidate['full_name']) ?></b>
                                    <div class="muted" style="font-size:9.8px"><?= e($candidate['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="small">
                            <?= e($candidate['job_title']) ?>
                            <div class="muted mono" style="font-size:9.4px"><?= e($candidate['job_code']) ?></div>
                        </td>
                        <td class="small"><?= e($sources[$candidate['source']] ?? $candidate['source']) ?></td>
                        <td class="num small"><?= number_id($candidate['experience_years'], 1) ?> th</td>
                        <td><span class="badge badge-<?= $stageTones[$candidate['status']] ?? 'slate' ?>"><?= e($stages[$candidate['status']] ?? $candidate['status']) ?></span></td>
                        <td class="small"><?= $stars($candidate['rating'] !== null ? (int) $candidate['rating'] : null) ?></td>
                        <td class="small muted"><?= tanggal($candidate['created_at'], 'd M Y') ?></td>
                        <td>
                            <a class="btn btn-sm btn-ghost" href="<?= url('recruitment/candidates/' . (int) $candidate['id']) ?>" title="Detail pelamar"><?= icon('eye') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$candidates): ?>
                    <tr><td colspan="8">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('users') ?></div>
                            <h3>Belum ada pelamar</h3>
                            <p>Tambahkan pelamar melalui formulir di samping, atau buka lowongan terlebih dahulu.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($paginator->lastPage() > 1): ?>
            <div class="card__foot flex-between">
                <span class="muted small">Menampilkan <?= $paginator->from() ?>–<?= $paginator->to() ?> dari <?= number_id($paginator->total) ?></span>
                <div class="flex gap-4">
                    <?php if ($paginator->page > 1): ?>
                        <a class="btn btn-sm btn-outline" href="<?= $pageLink($paginator->page - 1) ?>"><?= icon('chevron-left') ?> Sebelumnya</a>
                    <?php endif; ?>
                    <span class="btn btn-sm btn-ghost"><?= $paginator->page ?> / <?= $paginator->lastPage() ?></span>
                    <?php if ($paginator->page < $paginator->lastPage()): ?>
                        <a class="btn btn-sm btn-outline" href="<?= $pageLink($paginator->page + 1) ?>">Berikutnya <?= icon('chevron-right') ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <section class="card" id="form-pelamar">
        <div class="card__head">
            <div><h2>Pelamar Baru</h2>
                <div class="sub">Catat pelamar yang masuk lewat email, portal, atau referensi karyawan.</div></div>
        </div>
        <?php if (!$openJobs): ?>
            <div class="card__body">
                <div class="empty-state" style="padding:18px 0">
                    <div class="empty-icon"><?= icon('briefcase') ?></div>
                    <h3>Belum ada lowongan dibuka</h3>
                    <p>Buka lowongan terlebih dahulu agar pelamar dapat dicatat.</p>
                    <a class="btn btn-sm btn-primary" href="<?= url('recruitment') ?>">Kelola Lowongan</a>
                </div>
            </div>
        <?php else: ?>
            <form method="post" action="<?= url('recruitment/candidates') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="card__body flex-col gap-12">
                    <div class="field">
                        <label>Lowongan <span class="req">*</span></label>
                        <select class="select" name="job_id" required>
                            <option value="">Pilih lowongan…</option>
                            <?php foreach ($openJobs as $job): ?>
                                <option value="<?= (int) $job['id'] ?>" <?= $defaultJob === (int) $job['id'] ? 'selected' : '' ?>>
                                    <?= e($job['title']) ?> · <?= e($job['code']) ?><?= $job['status'] === 'ON_HOLD' ? ' (ditahan)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grid two">
                        <div class="field">
                            <label>Nama lengkap <span class="req">*</span></label>
                            <input class="input" type="text" name="full_name" maxlength="150" required value="<?= e($old['full_name'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Email <span class="req">*</span></label>
                            <input class="input" type="email" name="email" maxlength="150" required value="<?= e($old['email'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Telepon</label>
                            <input class="input" type="text" name="phone" maxlength="30" value="<?= e($old['phone'] ?? '') ?>" placeholder="08xx">
                        </div>
                        <div class="field">
                            <label>Sumber</label>
                            <select class="select" name="source">
                                <?php foreach ($sources as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= ($old['source'] ?? 'WEBSITE') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Pendidikan</label>
                            <select class="select" name="education">
                                <option value="">—</option>
                                <?php foreach ($educations as $education): ?>
                                    <option value="<?= e($education) ?>" <?= ($old['education'] ?? '') === $education ? 'selected' : '' ?>><?= e($education) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Pengalaman (tahun)</label>
                            <input class="input" type="number" name="experience_years" min="0" max="60" step="0.5" value="<?= e($old['experience_years'] ?? '0') ?>">
                        </div>
                        <div class="field">
                            <label>Rating seleksi</label>
                            <select class="select" name="rating">
                                <option value="0">Belum dinilai</option>
                                <?php for ($r = 1; $r <= 5; $r++): ?>
                                    <option value="<?= $r ?>" <?= (int) ($old['rating'] ?? 0) === $r ? 'selected' : '' ?>><?= str_repeat('★', $r) ?> (<?= $r ?>)</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid two">
                        <div class="field">
                            <label>Tanggal lahir</label>
                            <input class="input" type="date" name="birth_date" value="<?= e($old['birth_date'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Jenis kelamin</label>
                            <select class="select" name="gender">
                                <option value="">—</option>
                                <option value="L" <?= ($old['gender'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= ($old['gender'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Perusahaan saat ini</label>
                            <input class="input" type="text" name="current_company" maxlength="150" value="<?= e($old['current_company'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Posisi saat ini</label>
                            <input class="input" type="text" name="current_position" maxlength="120" value="<?= e($old['current_position'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label>Ekspektasi gaji (Rp)</label>
                        <input class="input" type="number" name="expected_salary" min="0" step="100000" value="<?= e($old['expected_salary'] ?? '0') ?>">
                    </div>
                    <div class="field">
                        <label>CV / resume</label>
                        <input class="input" type="file" name="cv" accept="application/pdf,image/jpeg,image/png">
                        <span class="hint">PDF/JPG/PNG maksimal 4 MB.</span>
                    </div>
                    <div class="field">
                        <label>Catatan pelamar</label>
                        <textarea class="textarea" name="cover_note" rows="3" maxlength="500" placeholder="Ringkasan motivasi atau catatan dari sumber referensi."><?= e($old['cover_note'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="card__foot" style="padding:12px 16px;display:flex;justify-content:flex-end">
                    <button class="btn btn-primary" type="submit"><?= icon('plus') ?> Simpan Pelamar</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</div>
