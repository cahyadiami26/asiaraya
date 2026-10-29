<?php
/**
 * Detail penilaian kinerja.
 * @var array $review, $scores, $statuses, $statusTones, $ratings, $ratingTones, $scale, $periodStatuses, $periodTones
 * @var bool $isHr, $isManager, $isOwner
 */
use App\Models\PerformanceModel;

$statusBadge = '<span class="badge badge-' . ($statusTones[$review['status']] ?? 'slate') . '">'
    . e($statuses[$review['status']] ?? $review['status']) . '</span>';
$rating = $review['rating'] !== null ? (string) $review['rating'] : null;
$selfWeight = (int) $review['self_weight'];
$managerWeight = 100 - $selfWeight;
$canAssess = $review['status'] === 'SELF_DONE' && ($isManager || $isHr);
$canCalibrate = $isHr && $review['status'] !== 'FINAL' && $review['manager_score'] !== null;
$back = $isHr ? url('performance') : ($isManager ? url('performance/team') : url('me/performance'));
$fmt = static fn (?float $value): string => PerformanceModel::scoreLabel($value);
?>
<div class="page-head">
    <div>
        <div class="small muted" style="margin-bottom:4px">
            <a href="<?= $back ?>">Penilaian Kinerja</a> / Detail
        </div>
        <h1 style="font-size:22px"><?= e($review['full_name']) ?></h1>
        <div class="sub">
            <?= e($review['employee_code']) ?> · <?= e($review['period_name']) ?>
            · <?= tanggal($review['start_date'], 'd M Y') ?>–<?= tanggal($review['end_date'], 'd M Y') ?>
        </div>
    </div>
    <div class="page-head__actions">
        <?= $statusBadge ?>
        <?php if ($rating): ?>
            <span class="rating-chip rating-<?= $ratingTones[$rating] ?? 'slate' ?>"><?= e($rating) ?> · <?= e($ratings[$rating] ?? '') ?></span>
        <?php endif; ?>
        <?php if ($canAssess): ?>
            <a class="btn btn-primary" href="<?= url('performance/team/' . (int) $review['id']) ?>"><?= icon('edit') ?> Nilai Sekarang</a>
        <?php endif; ?>
    </div>
</div>

<div class="split-grid wide-left">
    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head">
                <div><h2>Rincian Indikator</h2>
                    <div class="sub">Nilai 1–5 per indikator; bobot mengikuti snapshot saat periode dibuka.</div></div>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                    <tr><th>Indikator</th><th>Kategori</th><th class="num">Bobot</th>
                        <th class="num">Self</th><th class="num">Atasan</th><th>Catatan</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($scores as $score): ?>
                        <tr>
                            <td>
                                <b><?= e($score['name']) ?></b>
                                <div class="muted mono" style="font-size:9.6px"><?= e($score['code']) ?></div>
                            </td>
                            <td class="small">
                                <span class="chip chip-<?= PerformanceModel::CATEGORY_TONES[$score['category']] ?? 'slate' ?>">
                                    <?= e(PerformanceModel::CATEGORIES[$score['category']] ?? $score['category']) ?>
                                </span>
                            </td>
                            <td class="num small"><?= number_format((float) $score['weight'], 0, ',', '.') ?></td>
                            <td class="num small"><?= $score['self_score'] !== null
                                ? '<b>' . (int) $score['self_score'] . '</b><div class="muted" style="font-size:9px">' . e($scale[(int) $score['self_score']] ?? '') . '</div>'
                                : '<span class="muted">—</span>' ?></td>
                            <td class="num small"><?= $score['manager_score'] !== null
                                ? '<b>' . (int) $score['manager_score'] . '</b><div class="muted" style="font-size:9px">' . e($scale[(int) $score['manager_score']] ?? '') . '</div>'
                                : '<span class="muted">—</span>' ?></td>
                            <td class="small muted"><?= e($score['note'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$scores): ?>
                        <tr><td colspan="6"><div class="table-empty"><?= icon('layers') ?> Indikator belum tersedia pada penilaian ini.</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Catatan Penilaian</h2></div></div>
            <div class="card__body flex-col gap-16">
                <div>
                    <div class="small muted" style="margin-bottom:3px">Self assessment<?= $review['self_submitted_at'] ? ' · ' . tanggal($review['self_submitted_at'], 'd M Y H:i') : '' ?></div>
                    <div class="small" style="line-height:1.65"><?= $review['self_note'] ? nl2br(e($review['self_note'])) : '<span class="muted">Belum ada catatan.</span>' ?></div>
                </div>
                <div class="divider"></div>
                <div>
                    <div class="small muted" style="margin-bottom:3px">Penilaian atasan<?= $review['manager_submitted_at'] ? ' · ' . tanggal($review['manager_submitted_at'], 'd M Y H:i') : '' ?></div>
                    <div class="small" style="line-height:1.65"><?= $review['manager_note'] ? nl2br(e($review['manager_note'])) : '<span class="muted">Belum ada catatan.</span>' ?></div>
                </div>
                <?php if ($review['calibration_note']): ?>
                    <div class="divider"></div>
                    <div>
                        <div class="small muted" style="margin-bottom:3px">Catatan kalibrasi HR<?= $review['calibrated_at'] ? ' · ' . tanggal($review['calibrated_at'], 'd M Y H:i') : '' ?></div>
                        <div class="small" style="line-height:1.65"><?= nl2br(e($review['calibration_note'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($canCalibrate): ?>
            <section class="card">
                <div class="card__head"><div><h2>Kalibrasi &amp; Penetapan Hasil</h2>
                    <div class="sub">Nilai akhir dihitung otomatis; isi nilai manual hanya bila perlu penyesuaian.</div></div></div>
                <form method="post" action="<?= url('performance/' . (int) $review['id'] . '/calibrate') ?>">
                    <?= csrf_field() ?>
                    <div class="card__body flex-col gap-12">
                        <div class="calib-preview">
                            <div><span>Self</span><b><?= $fmt($review['self_score'] !== null ? (float) $review['self_score'] : null) ?></b><i>bobot <?= $selfWeight ?>%</i></div>
                            <div><span>Atasan</span><b><?= $fmt($review['manager_score'] !== null ? (float) $review['manager_score'] : null) ?></b><i>bobot <?= $managerWeight ?>%</i></div>
                            <div><span>Usulan akhir</span><b style="color:var(--teal-700)"><?= $fmt($review['self_score'] !== null
                                ? round(((float) $review['self_score'] * $selfWeight + (float) $review['manager_score'] * $managerWeight) / 100, 2)
                                : (float) $review['manager_score']) ?></b><i>otomatis</i></div>
                        </div>
                        <div class="form-grid two">
                            <div class="field">
                                <label>Nilai akhir manual (opsional)</label>
                                <input class="input" type="number" name="final_score" min="1" max="5" step="0.01" placeholder="Kosongkan untuk otomatis">
                                <span class="hint">Isi hanya bila hasil kalibrasi perlu disesuaikan (1–5).</span>
                            </div>
                            <div class="field">
                                <label>Mutu</label>
                                <input class="input" type="text" value="Dihitung otomatis (A–E)" readonly>
                            </div>
                        </div>
                        <div class="field">
                            <label>Catatan kalibrasi</label>
                            <textarea class="textarea" name="note" rows="3" maxlength="500"
                                      placeholder="Alasan penyesuaian, kesepakatan rapat kalibrasi, dsb."></textarea>
                        </div>
                    </div>
                    <div class="card__foot" style="padding:12px 16px;display:flex;justify-content:flex-end;gap:8px">
                        <button class="btn btn-primary" type="submit"><?= icon('check') ?> Tetapkan Hasil Final</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </div>

    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head"><div><h2>Ringkasan Nilai</h2></div></div>
            <div class="card__body">
                <div class="score-hero">
                    <div class="score-hero__value"><?= $fmt($review['final_score'] !== null ? (float) $review['final_score'] : null) ?></div>
                    <?php if ($rating): ?>
                        <span class="rating-chip rating-<?= $ratingTones[$rating] ?? 'slate' ?>" style="height:24px;font-size:11px"><?= e($rating) ?> · <?= e($ratings[$rating] ?? '') ?></span>
                    <?php else: ?>
                        <span class="small muted">Belum final</span>
                    <?php endif; ?>
                </div>
                <div class="meta-list" style="margin-top:14px">
                    <div><span>Nilai self</span><b><?= $fmt($review['self_score'] !== null ? (float) $review['self_score'] : null) ?></b></div>
                    <div><span>Nilai atasan</span><b><?= $fmt($review['manager_score'] !== null ? (float) $review['manager_score'] : null) ?></b></div>
                    <div><span>Atasan langsung</span><b><?= e($review['manager_name'] ?? '—') ?></b></div>
                    <div><span>Departemen</span><b><?= e($review['department_name'] ?? '—') ?></b></div>
                    <div><span>Jabatan</span><b><?= e($review['position_name'] ?? '—') ?></b></div>
                    <div><span>Dikonfirmasi karyawan</span><b><?= $review['acknowledged_at'] ? tanggal($review['acknowledged_at'], 'd M Y') : 'Belum' ?></b></div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Alur Penilaian</h2></div></div>
            <div class="card__body">
                <div class="timeline">
                    <div class="timeline__item<?= $review['period_status'] === 'DRAFT' ? ' muted' : '' ?>">
                        <b>Periode dibuka</b>
                        <span><?= e($review['period_name']) ?></span>
                    </div>
                    <div class="timeline__item<?= $review['self_submitted_at'] ? '' : ' muted' ?>">
                        <b>Self assessment</b>
                        <span><?= $review['self_submitted_at'] ? tanggal($review['self_submitted_at'], 'd M Y H:i') : 'Menunggu karyawan' ?></span>
                    </div>
                    <div class="timeline__item<?= $review['manager_submitted_at'] ? '' : ' muted' ?>">
                        <b>Penilaian atasan</b>
                        <span><?= $review['manager_submitted_at'] ? tanggal($review['manager_submitted_at'], 'd M Y H:i') : 'Menunggu atasan langsung' ?></span>
                    </div>
                    <div class="timeline__item<?= $review['status'] === 'FINAL' ? '' : ' muted' ?>">
                        <b>Hasil final</b>
                        <span><?= $review['calibrated_at'] ? tanggal($review['calibrated_at'], 'd M Y H:i') : 'Menunggu kalibrasi HR' ?></span>
                    </div>
                </div>
            </div>
            <?php if ($isOwner && $review['status'] === 'FINAL' && !$review['acknowledged_at']): ?>
                <div class="card__foot" style="padding:12px 16px">
                    <form method="post" action="<?= url('performance/' . (int) $review['id'] . '/acknowledge') ?>"><?= csrf_field() ?>
                        <button class="btn btn-soft btn-sm btn-block" type="submit"><?= icon('check') ?> Konfirmasi Sudah Membaca</button>
                    </form>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($isHr): ?>
            <section class="card">
                <div class="card__head"><div><h2>Administrasi</h2></div></div>
                <div class="card__body flex-col gap-10">
                    <a class="btn btn-outline btn-sm" href="<?= url('performance?period=' . (int) $review['period_id']) ?>"><?= icon('list') ?> Semua peserta periode ini</a>
                    <?php if ($review['period_status'] === 'DRAFT'): ?>
                        <span class="small muted">Periode masih draf — buka periode dari halaman Periode Penilaian.</span>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
