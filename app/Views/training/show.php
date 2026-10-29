<?php
/**
 * Detail sesi pelatihan: peserta, presensi, nilai & sertifikat (HR).
 * @var array $session, $participants, $stats, $candidates, $statuses, $statusTones, $partStatuses, $partTones, $modes, $categories, $categoryTones
 * @var array $errors, $old
 */
$quota = (int) $session['quota'];
$filled = (int) $stats['registered'];
$pct = $quota > 0 ? min(100, (int) round($filled / $quota * 100)) : 0;
$canManage = in_array($session['status'], ['DRAFT', 'OPEN', 'ONGOING'], true);
$statusForm = static function (array $participant, string $status, string $label, string $iconName, string $class) use ($session): string {
    $action = url('trainings/' . (int) $session['id'] . '/participants/' . (int) $participant['id']);
    return '<form method="post" action="' . $action . '" style="display:inline">'
        . csrf_field()
        . '<input type="hidden" name="action" value="status">'
        . '<input type="hidden" name="status" value="' . e($status) . '">'
        . '<button class="btn btn-sm ' . $class . '" type="submit" title="' . e($label) . '">' . icon($iconName) . ' ' . e($label) . '</button>'
        . '</form>';
};
?>
<div class="page-head">
    <div>
        <h1><?= e($session['program_name']) ?></h1>
        <div class="sub">
            <?= e($session['program_code']) ?> ·
            <?= e($categories[$session['category']] ?? $session['category']) ?> ·
            <?= tanggal($session['start_date'], 'd M Y') ?><?= !empty($session['end_date']) && $session['end_date'] !== $session['start_date'] ? ' – ' . tanggal($session['end_date'], 'd M Y') : '' ?>
        </div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('trainings') ?>"><?= icon('arrow-left') ?> Jadwal</a>
        <a class="btn btn-outline" href="<?= url('trainings/' . (int) $session['id'] . '/export') ?>"><?= icon('download') ?> Export CSV</a>
        <span class="badge badge-<?= $statusTones[$session['status']] ?? 'slate' ?>"><?= e($statuses[$session['status']] ?? $session['status']) ?></span>
    </div>
</div>

<?php if ((int) $stats['pending'] > 0): ?>
    <div class="alert alert-warning" style="margin-bottom:14px">
        <?= icon('alert') ?>
        <div><b><?= number_id($stats['pending']) ?> pendaftaran mandiri</b> menunggu persetujuan.</div>
        <div class="flex gap-4" style="margin-left:auto">
            <form method="post" action="<?= url('trainings/' . (int) $session['id'] . '/approve-all') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-primary" type="submit"><?= icon('check') ?> Setujui Semua</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Peserta Terdaftar</span>
        <span class="v"><?= number_id($filled) ?><?= $quota > 0 ? ' <span class="muted" style="font-size:13px">/ ' . number_id($quota) . '</span>' : '' ?></span>
        <span class="s"><?= $stats['approved'] > 0 ? number_id($stats['approved']) . ' menunggu pelaksanaan' : 'siap mengikuti sesi' ?></span>
    </div>
    <div class="mini-stat">
        <span class="k">Kehadiran</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id($stats['attended']) ?></span>
        <span class="s"><?= (int) $stats['attendance_rate'] ?>% dari peserta tercatat · <?= number_id($stats['absent']) ?> absen</span>
    </div>
    <div class="mini-stat">
        <span class="k">Rata-rata Nilai</span>
        <span class="v" style="color:var(--indigo-600)"><?= $stats['avg_score'] !== null ? number_id($stats['avg_score'], 1) : '—' ?></span>
        <span class="s">skala 0–100</span>
    </div>
    <div class="mini-stat">
        <span class="k">Biaya &amp; Jam</span>
        <span class="v"><?= rupiah_short($stats['cost']) ?></span>
        <span class="s"><?= number_id($stats['hours'], 1) ?> jam pelatihan</span>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Peserta Sesi</h2>
                <div class="sub"><?= number_id(count($participants)) ?> peserta terdaftar pada sesi ini.</div></div>
            <?php if ($canManage && (int) $stats['approved'] > 0): ?>
                <div class="card__head__actions">
                    <form method="post" action="<?= url('trainings/' . (int) $session['id'] . '/attendance-all') ?>"
                          onsubmit="return confirm('Tandai seluruh peserta terdaftar sebagai hadir?');">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline" type="submit"><?= icon('check') ?> Tandai Semua Hadir</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th>Peserta</th><th>Sumber</th><th>Status</th><th class="num">Nilai</th>
                    <th class="num">Jam</th><th>Sertifikat</th><th style="width:200px">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($participants as $participant): ?>
                    <tr>
                        <td>
                            <b><?= e($participant['full_name']) ?></b>
                            <div class="muted mono" style="font-size:9.6px"><?= e($participant['employee_code']) ?></div>
                            <div class="muted" style="font-size:9.6px"><?= e(($participant['department_name'] ?? '—') . ' · ' . ($participant['unit_name'] ?? '—')) ?></div>
                        </td>
                        <td class="small"><?= $participant['source'] === 'SELF' ? 'Mandiri' : 'Nominasi HR' ?></td>
                        <td><span class="badge badge-<?= $partTones[$participant['status']] ?? 'slate' ?>"><?= e($partStatuses[$participant['status']] ?? $participant['status']) ?></span></td>
                        <td class="num small"><b><?= $participant['score'] !== null ? number_id($participant['score'], 1) : '—' ?></b></td>
                        <td class="num small"><?= $participant['attended_hours'] !== null ? number_id($participant['attended_hours'], 1) : '—' ?></td>
                        <td class="small">
                            <?php if (!empty($participant['certificate'])): ?>
                                <a class="link-quiet" href="<?= e(upload_url($participant['certificate'])) ?>" target="_blank" rel="noopener"><?= icon('file') ?> Lihat</a>
                                <?php if (!empty($participant['certificate_no'])): ?>
                                    <div class="muted mono" style="font-size:9.4px"><?= e($participant['certificate_no']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex" style="gap:4px;justify-content:flex-end;flex-wrap:wrap">
                                <?php if ($canManage && $participant['status'] === 'NOMINATED'): ?>
                                    <?= $statusForm($participant, 'APPROVED', 'Setujui', 'check', 'btn-primary') ?>
                                    <?= $statusForm($participant, 'CANCELLED', 'Tolak', 'x', 'btn-ghost') ?>
                                <?php elseif ($canManage && $participant['status'] === 'APPROVED'): ?>
                                    <?= $statusForm($participant, 'ATTENDED', 'Hadir', 'check', 'btn-teal') ?>
                                    <?= $statusForm($participant, 'ABSENT', 'Absen', 'x', 'btn-outline') ?>
                                <?php elseif ($participant['status'] === 'CANCELLED' && $canManage): ?>
                                    <?= $statusForm($participant, 'APPROVED', 'Aktifkan', 'refresh', 'btn-outline') ?>
                                <?php endif; ?>
                                <form method="post" action="<?= url('trainings/' . (int) $session['id'] . '/participants/' . (int) $participant['id'] . '/remove') ?>"
                                      onsubmit="return confirm('Keluarkan peserta ini dari sesi? Data nilai & sertifikat ikut dihapus.');">
                                    <?= csrf_field() ?><input type="hidden" name="_method" value="DELETE">
                                    <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)" title="Keluarkan"><?= icon('trash') ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$participants): ?>
                    <tr><td colspan="7">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('users') ?></div>
                            <h3>Belum ada peserta</h3>
                            <p>Tambahkan karyawan melalui formulir nominasi, atau buka pendaftaran agar karyawan mendaftar sendiri.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($quota > 0): ?>
            <div class="card__foot">
                <div class="flex-between">
                    <span class="muted small">Kuota terisi</span>
                    <span class="small"><b><?= number_id($filled) ?></b> / <?= number_id($quota) ?> (<?= $pct ?>%)</span>
                </div>
                <div class="progress thin" style="margin-top:6px"><span style="width:<?= $pct ?>%"></span></div>
            </div>
        <?php endif; ?>
    </section>

    <div class="flex-col gap-12">
        <section class="card">
            <div class="card__head">
                <div><h2>Informasi Sesi</h2></div>
            </div>
            <div class="card__body">
                <div class="detail-list">
                    <div class="detail-list__row"><span>Program</span><b><?= e($session['program_name']) ?></b></div>
                    <div class="detail-list__row"><span>Jenis / Penyedia</span><b><?= e($session['type']) ?> · <?= e($session['provider'] ?: '—') ?></b></div>
                    <div class="detail-list__row"><span>Mode</span><b><?= e($modes[$session['mode']] ?? $session['mode']) ?></b></div>
                    <div class="detail-list__row"><span>Lokasi / tautan</span><b><?= e($session['location'] ?: '—') ?></b></div>
                    <div class="detail-list__row"><span>Durasi</span><b><?= number_id($session['duration_hours'], 1) ?> jam</b></div>
                    <div class="detail-list__row"><span>Biaya / peserta</span><b><?= $session['cost_per_participant'] > 0 ? rupiah($session['cost_per_participant']) : 'Gratis' ?></b></div>
                    <div class="detail-list__row"><span>Batas pendaftaran</span><b><?= $session['enroll_deadline'] ? tanggal($session['enroll_deadline'], 'd M Y') : 'Tanpa batas' ?></b></div>
                    <div class="detail-list__row"><span>Dibuat oleh</span><b><?= e($session['creator_name'] ?: '—') ?></b></div>
                </div>
                <?php if (!empty($session['note'])): ?>
                    <div class="alert alert-info" style="margin-top:10px">
                        <?= icon('info') ?><div><?= nl2br(e($session['note'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card__foot flex gap-4" style="flex-wrap:wrap;justify-content:flex-end">
                <?php if ($session['status'] === 'DRAFT'): ?>
                    <form method="post" action="<?= url('trainings/' . (int) $session['id'] . '/open') ?>" onsubmit="return confirm('Buka pendaftaran sesi ini? Seluruh karyawan aktif akan menerima notifikasi.');">
                        <?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit"><?= icon('send') ?> Buka Pendaftaran</button>
                    </form>
                <?php elseif ($session['status'] === 'OPEN'): ?>
                    <form method="post" action="<?= url('trainings/' . (int) $session['id'] . '/start') ?>">
                        <?= csrf_field() ?><button class="btn btn-sm btn-outline" type="submit"><?= icon('activity') ?> Tandai Berlangsung</button>
                    </form>
                <?php elseif ($session['status'] === 'ONGOING'): ?>
                    <form method="post" action="<?= url('trainings/' . (int) $session['id'] . '/complete') ?>" onsubmit="return confirm('Tutup sesi ini sebagai selesai?');">
                        <?= csrf_field() ?><button class="btn btn-sm btn-teal" type="submit"><?= icon('check') ?> Selesaikan Sesi</button>
                    </form>
                <?php endif; ?>
                <?php if (in_array($session['status'], ['DRAFT', 'OPEN', 'ONGOING'], true)): ?>
                    <form method="post" action="<?= url('trainings/' . (int) $session['id'] . '/cancel') ?>" onsubmit="return confirm('Batalkan sesi ini? Peserta akan menerima notifikasi.');">
                        <?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)"><?= icon('x') ?> Batalkan Sesi</button>
                    </form>
                <?php endif; ?>
                <?php if (in_array($session['status'], ['DRAFT', 'CANCELLED'], true)): ?>
                    <form method="post" action="<?= url('trainings/' . (int) $session['id'] . '/delete') ?>" onsubmit="return confirm('Hapus sesi ini beserta seluruh data peserta?');">
                        <?= csrf_field() ?><input type="hidden" name="_method" value="DELETE">
                        <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)"><?= icon('trash') ?> Hapus Sesi</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($canManage): ?>
            <section class="card">
                <div class="card__head">
                    <div><h2>Input Hasil &amp; Sertifikat</h2>
                        <div class="sub">Nilai 0–100, jam hadir, dan berkas sertifikat (PDF/JPG/PNG, maks 4 MB).</div></div>
                </div>
                <?php if (!$participants): ?>
                    <div class="card__body"><p class="muted small">Tambahkan peserta terlebih dahulu.</p></div>
                <?php else: ?>
                    <form method="post" action="<?= url('trainings/' . (int) $session['id'] . '/results') ?>">
                        <?= csrf_field() ?>
                        <div class="card__body flex-col gap-12">
                            <div class="field">
                                <label>Peserta <span class="req">*</span></label>
                                <select class="select" name="participant_id" required>
                                    <?php foreach ($participants as $participant): ?>
                                        <option value="<?= (int) $participant['id'] ?>">
                                            <?= e($participant['full_name']) ?> — <?= e($partStatuses[$participant['status']] ?? $participant['status']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="hint">Pilih peserta, isi hasil pelatihan, lalu simpan.</span>
                            </div>
                            <div class="form-grid two">
                                <div class="field">
                                    <label>Nilai (0–100)</label>
                                    <input class="input" type="number" name="score" min="0" max="100" step="0.5" placeholder="85">
                                </div>
                                <div class="field">
                                    <label>Jam hadir</label>
                                    <input class="input" type="number" name="attended_hours" min="0" max="999" step="0.5" placeholder="8">
                                </div>
                            </div>
                            <div class="field">
                                <label>Nomor sertifikat</label>
                                <input class="input mono" type="text" name="certificate_no" maxlength="60" placeholder="TRN/2026/0001">
                            </div>
                            <div class="field">
                                <label>Berkas sertifikat</label>
                                <input class="input" type="file" name="certificate" accept="application/pdf,image/jpeg,image/png">
                                <span class="hint">Mengunggah berkas baru akan menggantikan sertifikat sebelumnya.</span>
                            </div>
                            <div class="field">
                                <label>Catatan</label>
                                <input class="input" type="text" name="note" maxlength="255" placeholder="Mis. lulus dengan predikat baik">
                            </div>
                        </div>
                        <div class="card__foot" style="padding:12px 16px;display:flex;justify-content:flex-end">
                            <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan Hasil</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <section class="card">
                <div class="card__head">
                    <div><h2>Nominasi Peserta</h2>
                        <div class="sub">Peserta yang ditambahkan HR langsung berstatus terdaftar.</div></div>
                </div>
                <form method="post" action="<?= url('trainings/' . (int) $session['id'] . '/participants') ?>">
                    <?= csrf_field() ?>
                    <div class="card__body flex-col gap-12">
                        <div class="field">
                            <label>Pilih karyawan (bisa lebih dari satu)</label>
                            <select class="select" name="employee_ids[]" multiple size="10" style="height:auto">
                                <?php foreach ($candidates as $candidate): ?>
                                    <option value="<?= (int) $candidate['id'] ?>">
                                        <?= e($candidate['full_name']) ?> · <?= e($candidate['employee_code']) ?><?= $candidate['unit_name'] ? ' · ' . e($candidate['unit_name']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="hint">Tahan Ctrl (Windows) atau Cmd (macOS) untuk memilih beberapa karyawan. <?= number_id(count($candidates)) ?> karyawan tersedia.</span>
                        </div>
                    </div>
                    <div class="card__foot" style="padding:12px 16px;display:flex;justify-content:flex-end">
                        <button class="btn btn-outline" type="submit"><?= icon('plus') ?> Tambahkan Peserta</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>
