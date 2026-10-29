<?php
/**
 * Detail pelamar: profil, tahapan, wawancara, dan konversi ke karyawan (HR).
 * @var array $candidate, $history, $stages, $stageTones, $pipeline, $sources, $educations
 * @var array $interviewModes, $units, $departments, $positions, $managers, $employmentTypes
 * @var array $errors, $old
 */
$isHired = $candidate['status'] === 'HIRED';
$isClosed = in_array($candidate['status'], ['HIRED', 'REJECTED', 'WITHDRAWN'], true);
$nextStage = '';
foreach ($pipeline as $stage) {
    if (array_search($candidate['status'], $pipeline, true) !== false && array_search($stage, $pipeline, true) > array_search($candidate['status'], $pipeline, true)) {
        $nextStage = $stage;
        break;
    }
}
$stars = static function (?int $rating): string {
    if (!$rating) {
        return '<span class="muted small">Belum dinilai</span>';
    }
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<span class="star' . ($i <= $rating ? ' is-on' : '') . '">★</span>';
    }
    return '<span class="star-row">' . $out . '</span>';
};
?>
<div class="page-head">
    <div>
        <h1><?= e($candidate['full_name']) ?></h1>
        <div class="sub">
            <?= e($candidate['job_title']) ?> · <?= e($candidate['job_code']) ?> ·
            <?= e($sources[$candidate['source']] ?? $candidate['source']) ?>
        </div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('recruitment/jobs/' . (int) $candidate['job_id']) ?>"><?= icon('arrow-left') ?> Pipeline</a>
        <span class="badge badge-<?= $stageTones[$candidate['status']] ?? 'slate' ?>"><?= e($stages[$candidate['status']] ?? $candidate['status']) ?></span>
    </div>
</div>

<?php if ($isHired): ?>
    <div class="alert alert-success" style="margin-bottom:14px">
        <?= icon('check') ?>
        <div>
            <b>Pelamar diterima<?= $candidate['hired_at'] ? ' pada ' . tanggal($candidate['hired_at'], 'd M Y H:i') : '' ?>.</b>
            <?php if (!empty($candidate['employee_code'])): ?>
                Data karyawan terhubung: <b><?= e($candidate['employee_code']) ?></b> — <?= e($candidate['employee_name']) ?>.
            <?php endif; ?>
        </div>
        <?php if (!empty($candidate['employee_id'])): ?>
            <a class="btn btn-sm btn-outline" href="<?= url('employees/' . (int) $candidate['employee_id']) ?>" style="margin-left:auto"><?= icon('user') ?> Buka Data Karyawan</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="split-grid wide-left">
    <div class="flex-col gap-12">
        <section class="card">
            <div class="card__head">
                <div><h2>Profil Pelamar</h2>
                    <div class="sub">Masuk pipeline <?= tanggal($candidate['created_at'], 'd M Y H:i') ?>.</div></div>
                <div class="card__head__actions">
                    <?php if (!empty($candidate['cv'])): ?>
                        <a class="btn btn-sm btn-outline" href="<?= e(upload_url($candidate['cv'])) ?>" target="_blank" rel="noopener"><?= icon('file') ?> Lihat CV</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card__body">
                <div class="flex" style="gap:14px;align-items:center;margin-bottom:14px">
                    <div class="avatar" style="width:48px;height:48px;font-size:15px;background:<?= avatar_color($candidate['full_name']) ?>"><?= e(initials($candidate['full_name'])) ?></div>
                    <div>
                        <b style="font-size:15px"><?= e($candidate['full_name']) ?></b>
                        <div class="muted small"><?= e($candidate['email']) ?><?= $candidate['phone'] ? ' · ' . e($candidate['phone']) : '' ?></div>
                        <div style="margin-top:4px"><?= $stars($candidate['rating'] !== null ? (int) $candidate['rating'] : null) ?></div>
                    </div>
                </div>

                <div class="detail-list">
                    <div class="detail-list__row"><span>Lowongan</span><b><?= e($candidate['job_title']) ?></b></div>
                    <div class="detail-list__row"><span>Unit / Departemen</span><b><?= e(($candidate['unit_name'] ?: '—') . ' · ' . ($candidate['department_name'] ?: '—')) ?></b></div>
                    <div class="detail-list__row"><span>Pendidikan</span><b><?= e($candidate['education'] ?: '—') ?></b></div>
                    <div class="detail-list__row"><span>Pengalaman</span><b><?= number_id($candidate['experience_years'], 1) ?> tahun</b></div>
                    <div class="detail-list__row"><span>Perusahaan saat ini</span><b><?= e($candidate['current_company'] ?: '—') ?><?= $candidate['current_position'] ? ' · ' . e($candidate['current_position']) : '' ?></b></div>
                    <div class="detail-list__row"><span>Ekspektasi gaji</span><b><?= (float) $candidate['expected_salary'] > 0 ? rupiah($candidate['expected_salary']) : 'Tidak disebutkan' ?></b></div>
                    <div class="detail-list__row"><span>Tanggal lahir</span><b><?= $candidate['birth_date'] ? tanggal($candidate['birth_date'], 'd M Y') : '—' ?></b></div>
                    <div class="detail-list__row"><span>Jenis kelamin</span><b><?= $candidate['gender'] === 'L' ? 'Laki-laki' : ($candidate['gender'] === 'P' ? 'Perempuan' : '—') ?></b></div>
                    <div class="detail-list__row"><span>Jadwal wawancara</span><b>
                        <?php if (!empty($candidate['interview_at'])): ?>
                            <?= tanggal($candidate['interview_at'], 'd M Y H:i') ?>
                            · <?= e($interviewModes[$candidate['interview_mode']] ?? '—') ?>
                            <?= $candidate['interviewer_name'] ? ' · ' . e($candidate['interviewer_name']) : '' ?>
                        <?php else: ?>Belum dijadwalkan<?php endif; ?>
                    </b></div>
                    <div class="detail-list__row"><span>Dicatat oleh</span><b>HR Rekrutmen</b></div>
                </div>

                <?php if (!empty($candidate['cover_note'])): ?>
                    <h3 class="sub-title">Catatan Pelamar</h3>
                    <p class="small" style="line-height:1.65;white-space:pre-line"><?= e($candidate['cover_note']) ?></p>
                <?php endif; ?>
                <?php if (!empty($candidate['note'])): ?>
                    <h3 class="sub-title">Catatan Internal</h3>
                    <p class="small" style="line-height:1.65;white-space:pre-line"><?= e($candidate['note']) ?></p>
                <?php endif; ?>
                <?php if ($candidate['status'] === 'REJECTED' && !empty($candidate['reject_reason'])): ?>
                    <div class="alert alert-error" style="margin-top:10px"><?= icon('x') ?><div>Alasan penolakan: <?= e($candidate['reject_reason']) ?></div></div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <div><h2>Perbarui Data Pelamar</h2>
                    <div class="sub">Perubahan data tidak mengubah tahapan seleksi.</div></div>
            </div>
            <form method="post" action="<?= url('recruitment/candidates/' . (int) $candidate['id']) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="PUT">
                <div class="card__body flex-col gap-12">
                    <div class="form-grid two">
                        <div class="field">
                            <label>Nama lengkap <span class="req">*</span></label>
                            <input class="input" type="text" name="full_name" maxlength="150" required value="<?= e($candidate['full_name']) ?>">
                        </div>
                        <div class="field">
                            <label>Email <span class="req">*</span></label>
                            <input class="input" type="email" name="email" maxlength="150" required value="<?= e($candidate['email']) ?>">
                        </div>
                        <div class="field">
                            <label>Telepon</label>
                            <input class="input" type="text" name="phone" maxlength="30" value="<?= e($candidate['phone'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Tanggal lahir</label>
                            <input class="input" type="date" name="birth_date" value="<?= e($candidate['birth_date'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Jenis kelamin</label>
                            <select class="select" name="gender">
                                <option value="">—</option>
                                <option value="L" <?= $candidate['gender'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= $candidate['gender'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Pendidikan</label>
                            <select class="select" name="education">
                                <option value="">—</option>
                                <?php foreach ($educations as $education): ?>
                                    <option value="<?= e($education) ?>" <?= $candidate['education'] === $education ? 'selected' : '' ?>><?= e($education) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Pengalaman (tahun)</label>
                            <input class="input" type="number" name="experience_years" min="0" max="60" step="0.5" value="<?= e($candidate['experience_years']) ?>">
                        </div>
                        <div class="field">
                            <label>Sumber</label>
                            <select class="select" name="source">
                                <?php foreach ($sources as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $candidate['source'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Perusahaan saat ini</label>
                            <input class="input" type="text" name="current_company" maxlength="150" value="<?= e($candidate['current_company'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Posisi saat ini</label>
                            <input class="input" type="text" name="current_position" maxlength="120" value="<?= e($candidate['current_position'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Ekspektasi gaji (Rp)</label>
                            <input class="input" type="number" name="expected_salary" min="0" step="100000" value="<?= e((string) (int) $candidate['expected_salary']) ?>">
                        </div>
                        <div class="field">
                            <label>Rating seleksi (1–5)</label>
                            <select class="select" name="rating">
                                <option value="0">Belum dinilai</option>
                                <?php for ($r = 1; $r <= 5; $r++): ?>
                                    <option value="<?= $r ?>" <?= (int) $candidate['rating'] === $r ? 'selected' : '' ?>><?= str_repeat('★', $r) ?> (<?= $r ?>)</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="field">
                        <label>Ganti CV</label>
                        <input class="input" type="file" name="cv" accept="application/pdf,image/jpeg,image/png">
                        <span class="hint">Kosongkan bila tidak ingin mengganti berkas.</span>
                    </div>
                    <div class="field">
                        <label>Catatan internal</label>
                        <textarea class="textarea" name="note" rows="2" maxlength="500"><?= e($candidate['note'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="card__foot" style="padding:12px 16px;display:flex;justify-content:flex-end">
                    <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan Perubahan</button>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="card__head">
                <div><h2>Riwayat Tahapan</h2>
                    <div class="sub">Jejak audit perpindahan tahap pelamar.</div></div>
            </div>
            <div class="card__body">
                <div class="timeline">
                    <?php foreach ($history as $stage): ?>
                        <div class="timeline__item">
                            <b><?= e($stages[$stage['to_status']] ?? $stage['to_status']) ?></b>
                            <span>
                                <?= tanggal($stage['created_at'], 'd M Y H:i') ?>
                                <?= $stage['actor_name'] ? ' · ' . e($stage['actor_name']) : '' ?>
                                <?php if (!empty($stage['note'])): ?>
                                    <br><?= e($stage['note']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$history): ?>
                        <p class="muted small">Belum ada riwayat.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <div class="flex-col gap-12">
        <?php if (!$isClosed): ?>
            <section class="card">
                <div class="card__head">
                    <div><h2>Pindahkan Tahap</h2>
                        <div class="sub">Catat hasil seleksi pada setiap perpindahan.</div></div>
                </div>
                <form method="post" action="<?= url('recruitment/candidates/' . (int) $candidate['id'] . '/stage') ?>">
                    <?= csrf_field() ?>
                    <div class="card__body flex-col gap-12">
                        <div class="field">
                            <label>Tahap berikutnya <span class="req">*</span></label>
                            <select class="select" name="status" required>
                                <option value="">Pilih tahap…</option>
                                <?php foreach ($pipeline as $stage): ?>
                                    <?php if ($stage === $candidate['status']) continue; ?>
                                    <option value="<?= e($stage) ?>" <?= $nextStage === $stage ? 'selected' : '' ?>><?= e($stages[$stage]) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Jadwal wawancara</label>
                            <input class="input" type="datetime-local" name="interview_at" value="<?= e($candidate['interview_at'] ? date('Y-m-d\TH:i', strtotime((string) $candidate['interview_at'])) : '') ?>">
                            <span class="hint">Wajib diisi bila memindahkan ke tahap wawancara.</span>
                        </div>
                        <div class="form-grid two">
                            <div class="field">
                                <label>Mode wawancara</label>
                                <select class="select" name="interview_mode">
                                    <?php foreach ($interviewModes as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= $candidate['interview_mode'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Pewawancara</label>
                                <select class="select" name="interviewer_id">
                                    <option value="0">— Belum ditentukan —</option>
                                    <?php foreach ($managers as $manager): ?>
                                        <option value="<?= (int) $manager['id'] ?>" <?= (int) $candidate['interviewer_id'] === (int) $manager['id'] ? 'selected' : '' ?>><?= e($manager['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label>Catatan tahap</label>
                            <textarea class="textarea" name="note" rows="2" maxlength="255" placeholder="Hasil screening, kesan wawancara, atau kesepakatan penawaran."></textarea>
                        </div>
                    </div>
                    <div class="card__foot" style="padding:12px 16px;display:flex;justify-content:flex-end">
                        <button class="btn btn-primary" type="submit"><?= icon('arrow-right') ?> Simpan Tahap</button>
                    </div>
                </form>
            </section>

            <section class="card">
                <div class="card__head">
                    <div><h2>Terima sebagai Karyawan</h2>
                        <div class="sub">Data karyawan dibuat otomatis dengan NIK dari format perusahaan.</div></div>
                </div>
                <form method="post" action="<?= url('recruitment/candidates/' . (int) $candidate['id'] . '/hire') ?>">
                    <?= csrf_field() ?>
                    <div class="card__body flex-col gap-12">
                        <div class="form-grid two">
                            <div class="field">
                                <label>Business unit <span class="req">*</span></label>
                                <select class="select" name="business_unit_id" required>
                                    <option value="">Pilih unit…</option>
                                    <?php foreach ($units as $unit): ?>
                                        <option value="<?= (int) $unit['id'] ?>" <?= (int) ($candidate['business_unit_id'] ?? 0) === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Departemen</label>
                                <select class="select" name="department_id">
                                    <option value="0">—</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?= (int) $department['id'] ?>" <?= (int) ($candidate['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Posisi</label>
                                <select class="select" name="position_id">
                                    <option value="0">—</option>
                                    <?php foreach ($positions as $position): ?>
                                        <option value="<?= (int) $position['id'] ?>" <?= (int) ($candidate['position_id'] ?? 0) === (int) $position['id'] ? 'selected' : '' ?>><?= e($position['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Atasan langsung</label>
                                <select class="select" name="direct_manager_id">
                                    <option value="0">—</option>
                                    <?php foreach ($managers as $manager): ?>
                                        <option value="<?= (int) $manager['id'] ?>" <?= (int) ($candidate['hiring_manager_id'] ?? 0) === (int) $manager['id'] ? 'selected' : '' ?>><?= e($manager['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Status kepegawaian <span class="req">*</span></label>
                                <select class="select" name="employment_status" required>
                                    <?php foreach ($employmentTypes as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= $candidate['employment_type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Gaji pokok (Rp)</label>
                                <input class="input" type="number" name="basic_salary" min="0" step="100000" value="<?= e((string) (int) $candidate['expected_salary']) ?>">
                            </div>
                            <div class="field">
                                <label>Tanggal bergabung <span class="req">*</span></label>
                                <input class="input" type="date" name="join_date" required value="<?= e($old['join_date'] ?? date('Y-m-d')) ?>">
                            </div>
                            <div class="field">
                                <label>Akhir kontrak</label>
                                <input class="input" type="date" name="contract_end" value="<?= e($old['contract_end'] ?? '') ?>">
                                <span class="hint">Wajib untuk PKWT/Magang/Outsourcing.</span>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <?= icon('info') ?>
                            <div>Karyawan baru mendapat kata sandi awal <b>Password123!</b> dan notifikasi selamat datang.</div>
                        </div>
                    </div>
                    <div class="card__foot" style="padding:12px 16px;display:flex;justify-content:flex-end">
                        <button class="btn btn-teal" type="submit"><?= icon('check') ?> Terima &amp; Buat Karyawan</button>
                    </div>
                </form>
            </section>

            <section class="card">
                <div class="card__head"><div><h2>Penolakan</h2></div></div>
                <form method="post" action="<?= url('recruitment/candidates/' . (int) $candidate['id'] . '/reject') ?>">
                    <?= csrf_field() ?>
                    <div class="card__body flex-col gap-12">
                        <div class="field">
                            <label>Alasan penolakan <span class="req">*</span></label>
                            <input class="input" type="text" name="reject_reason" maxlength="255" required placeholder="Mis. kualifikasi belum sesuai kebutuhan">
                        </div>
                    </div>
                    <div class="card__foot flex gap-4" style="padding:12px 16px;justify-content:flex-end;flex-wrap:wrap">
                        <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)"><?= icon('x') ?> Tolak Pelamar</button>
                    </div>
                </form>
                <form method="post" action="<?= url('recruitment/candidates/' . (int) $candidate['id'] . '/withdraw') ?>" style="padding:0 16px 14px">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline btn-block" type="submit" onclick="return confirm('Tandai pelamar mengundurkan diri?')"><?= icon('logout') ?> Mengundurkan Diri</button>
                </form>
            </section>
        <?php else: ?>
            <section class="card">
                <div class="card__head"><div><h2>Status Akhir</h2></div></div>
                <div class="card__body">
                    <p class="small muted">
                        <?php if ($isHired): ?>
                            Pelamar sudah diterima dan dikonversi menjadi karyawan. Tahapan tidak dapat diubah lagi.
                        <?php elseif ($candidate['status'] === 'REJECTED'): ?>
                            Pelamar ditolak. Riwayat seleksi tetap tersimpan untuk audit.
                        <?php else: ?>
                            Pelamar mengundurkan diri dari proses seleksi.
                        <?php endif; ?>
                    </p>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
