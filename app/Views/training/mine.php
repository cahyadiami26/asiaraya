<?php
/**
 * Pelatihan saya (semua peran).
 * @var array $enrollments, $available, $stats, $statuses, $statusTones, $sessionStatuses, $sessionTones, $categories, $categoryTones, $modes
 */
$active = array_values(array_filter($enrollments, static fn (array $row): bool => in_array($row['status'], ['NOMINATED', 'APPROVED'], true)));
$history = array_values(array_filter($enrollments, static fn (array $row): bool => !in_array($row['status'], ['NOMINATED', 'APPROVED'], true)));
?>
<div class="page-head">
    <div>
        <h1>Pelatihan Saya</h1>
        <div class="sub">Ikuti pelatihan yang dibuka HR, pantau kehadiran, nilai, dan unduh sertifikat Anda.</div>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Pelatihan Selesai</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id($stats['completed']) ?></span>
        <span class="s"><?= number_id($stats['upcoming']) ?> jadwal mendatang</span>
    </div>
    <div class="mini-stat">
        <span class="k">Jam Pelatihan</span>
        <span class="v"><?= number_id($stats['hours'], 1) ?></span>
        <span class="s">akumulasi jam hadir</span>
    </div>
    <div class="mini-stat">
        <span class="k">Sertifikat</span>
        <span class="v" style="color:var(--indigo-600)"><?= number_id($stats['certificates']) ?></span>
        <span class="s">berkas tersedia untuk diunduh</span>
    </div>
    <div class="mini-stat">
        <span class="k">Rata-rata Nilai</span>
        <span class="v" style="color:var(--amber-600)"><?= $stats['avg_score'] !== null ? number_id($stats['avg_score'], 1) : '—' ?></span>
        <span class="s">skala 0–100</span>
    </div>
</div>

<?php if ($available): ?>
    <section class="card" style="margin-bottom:14px">
        <div class="card__head">
            <div><h2>Pendaftaran Dibuka</h2>
                <div class="sub"><?= number_id(count($available)) ?> sesi menerima pendaftaran. Kuota terbatas.</div></div>
        </div>
        <div class="card__body flex-col gap-12">
            <?php foreach ($available as $session): ?>
                <div class="assess-row">
                    <div class="assess-row__info">
                        <b><?= e($session['program_name']) ?></b>
                        <span>
                            <?= tanggal($session['start_date'], 'd M Y') ?>
                            <?= !empty($session['end_date']) && $session['end_date'] !== $session['start_date'] ? ' – ' . tanggal($session['end_date'], 'd M Y') : '' ?>
                            · <?= e($modes[$session['mode']] ?? $session['mode']) ?>
                            <?= $session['location'] ? ' · ' . e($session['location']) : '' ?>
                        </span>
                        <div class="assess-row__meta">
                            <span class="chip chip-<?= $categoryTones[$session['category']] ?? 'slate' ?>"><?= e($categories[$session['category']] ?? $session['category']) ?></span>
                            <span class="chip chip-slate"><?= number_id($session['duration_hours'], 1) ?> jam</span>
                            <?php if (!empty($session['enroll_deadline'])): ?>
                                <span class="chip chip-amber">Tutup <?= tanggal($session['enroll_deadline'], 'd M Y') ?></span>
                            <?php endif; ?>
                            <span class="chip chip-indigo">
                                <?= $session['slots_left'] !== null ? 'Sisa ' . number_id($session['slots_left']) . ' kursi' : 'Kuota terbuka' ?>
                            </span>
                        </div>
                    </div>
                    <form method="post" action="<?= url('me/trainings/' . (int) $session['id'] . '/enroll') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary btn-sm" type="submit"><?= icon('plus') ?> Daftar</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($active): ?>
    <section class="card" style="margin-bottom:14px">
        <div class="card__head">
            <div><h2>Jadwal Mendatang</h2>
                <div class="sub">Pendaftaran Anda yang sudah tercatat pada sesi pelatihan.</div></div>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr><th>Program</th><th>Jadwal</th><th>Pelaksanaan</th><th>Status</th><th style="width:130px">Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($active as $row): ?>
                    <tr>
                        <td>
                            <b><?= e($row['program_name']) ?></b>
                            <div class="chip-row" style="margin-top:4px">
                                <span class="chip chip-<?= $categoryTones[$row['category']] ?? 'slate' ?>"><?= e($categories[$row['category']] ?? $row['category']) ?></span>
                                <span class="chip chip-slate"><?= e($row['program_code']) ?></span>
                            </div>
                        </td>
                        <td class="small">
                            <b><?= tanggal($row['start_date'], 'd M Y') ?></b>
                            <?php if (!empty($row['end_date']) && $row['end_date'] !== $row['start_date']): ?>
                                <div class="muted" style="font-size:9.8px">s.d. <?= tanggal($row['end_date'], 'd M Y') ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?= e($modes[$row['mode']] ?? $row['mode']) ?>
                            <div class="muted" style="font-size:9.8px"><?= e($row['location'] ?: '—') ?></div>
                        </td>
                        <td>
                            <span class="badge badge-<?= $statusTones[$row['status']] ?? 'slate' ?>"><?= e($statuses[$row['status']] ?? $row['status']) ?></span>
                            <?php if ($row['session_status'] === 'CANCELLED'): ?>
                                <div class="muted" style="font-size:9.6px;margin-top:3px">sesi dibatalkan HR</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex" style="gap:5px;justify-content:flex-end">
                                <?php if ($row['status'] === 'NOMINATED' || ($row['status'] === 'APPROVED' && $row['session_status'] !== 'ONGOING')): ?>
                                    <form method="post" action="<?= url('me/trainings/' . (int) $row['session_id'] . '/cancel') ?>"
                                          onsubmit="return confirm('Batalkan pendaftaran pelatihan ini?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)"><?= icon('x') ?> Batalkan</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted small">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<section class="card">
    <div class="card__head">
        <div><h2>Riwayat Pelatihan</h2>
            <div class="sub">Kehadiran, nilai, dan sertifikat dari sesi yang sudah selesai.</div></div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr><th>Program</th><th>Jadwal</th><th class="num">Jam</th><th class="num">Nilai</th><th>Status</th><th>Sertifikat</th></tr>
            </thead>
            <tbody>
            <?php foreach ($history as $row): ?>
                <tr>
                    <td>
                        <b><?= e($row['program_name']) ?></b>
                        <div class="chip-row" style="margin-top:4px">
                            <span class="chip chip-<?= $categoryTones[$row['category']] ?? 'slate' ?>"><?= e($categories[$row['category']] ?? $row['category']) ?></span>
                            <?php if (!empty($row['certificate_no'])): ?>
                                <span class="chip chip-teal mono"><?= e($row['certificate_no']) ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="small"><?= tanggal($row['start_date'], 'd M Y') ?></td>
                    <td class="num small"><?= number_id($row['attended_hours'] !== null ? (float) $row['attended_hours'] : (float) $row['duration_hours'], 1) ?></td>
                    <td class="num small"><b><?= $row['score'] !== null ? number_id($row['score'], 1) : '—' ?></b></td>
                    <td><span class="badge badge-<?= $statusTones[$row['status']] ?? 'slate' ?>"><?= e($statuses[$row['status']] ?? $row['status']) ?></span></td>
                    <td class="small">
                        <?php if (!empty($row['certificate'])): ?>
                            <a class="link-quiet" href="<?= e(upload_url($row['certificate'])) ?>" target="_blank" rel="noopener"><?= icon('download') ?> Unduh</a>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$history): ?>
                <tr><td colspan="6">
                    <div class="empty-state">
                        <div class="empty-icon"><?= icon('award') ?></div>
                        <h3>Belum ada riwayat pelatihan</h3>
                        <p>Pelatihan yang Anda selesaikan akan muncul di sini beserta nilai dan sertifikatnya.</p>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
