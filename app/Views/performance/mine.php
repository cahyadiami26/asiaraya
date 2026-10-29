<?php
/**
 * Penilaian kinerja karyawan: self assessment + hasil.
 * @var array|null $current
 * @var array $scores, $history, $statuses, $statusTones, $ratings, $ratingTones, $scale, $categories, $catTones
 * @var array $errors, $old
 */
use App\Models\PerformanceModel;

$fmt = static fn (?float $value): string => PerformanceModel::scoreLabel($value);
$oldScores = is_array($old['scores'] ?? null) ? $old['scores'] : [];
$grouped = [];
foreach ($scores as $score) {
    $grouped[$score['category']][] = $score;
}

$status = $current['status'] ?? null;
$isFinal = $status === 'FINAL';
$canFill = $current !== null && $status === 'PENDING'
    && in_array($current['period_status'], ['SELF', 'MANAGER'], true);
$pendingOthers = $current !== null && in_array($status, ['SELF_DONE', 'MANAGER_DONE'], true);
?>
<div class="page-head">
    <div>
        <h1>Penilaian Kinerja Saya</h1>
        <div class="sub">Lengkapi self assessment saat periode dibuka, lalu pantau hasil penilaian atasan dan HR.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('performance/team') ?>"><?= icon('users') ?> Penilaian Tim</a>
    </div>
</div>

<?php if ($current === null): ?>
    <section class="card">
        <div class="empty-state" style="padding:44px 20px">
            <div class="empty-icon"><?= icon('award') ?></div>
            <h3>Belum ada periode penilaian untuk Anda</h3>
            <p>HR akan membuka periode penilaian secara berkala. Anda akan menerima notifikasi saat self assessment dibuka.</p>
        </div>
    </section>
<?php else: ?>
    <div class="split-grid wide-left">
        <div class="flex-col gap-16">
            <?php if ($canFill): ?>
                <form method="post" action="<?= url('me/performance/' . (int) $current['id']) ?>">
                    <?= csrf_field() ?>
                    <section class="card">
                        <div class="card__head">
                            <div><h2>Self Assessment — <?= e($current['period_name']) ?></h2>
                                <div class="sub">Nilai diri Anda secara jujur pada setiap indikator, lalu kirim untuk dinilai atasan.</div></div>
                            <span class="badge badge-slate">Menunggu Self Assessment</span>
                        </div>
                        <div class="card__body flex-col gap-16">
                            <?php foreach ($grouped as $category => $rows): ?>
                                <div>
                                    <div class="section-label"><?= e($categories[$category] ?? $category) ?></div>
                                    <?php foreach ($rows as $score): ?>
                                        <?php
                                        $indicatorId = (int) $score['indicator_id'];
                                        $selected = (int) ($oldScores[$indicatorId] ?? $score['self_score'] ?? 0);
                                        ?>
                                        <div class="assess-row">
                                            <div class="assess-row__info">
                                                <b><?= e($score['name']) ?></b>
                                                <?php if ($score['description']): ?><span><?= e($score['description']) ?></span><?php endif; ?>
                                                <div class="assess-row__meta">
                                                    <span class="chip chip-slate">Bobot <?= number_format((float) $score['weight'], 0, ',', '.') ?></span>
                                                </div>
                                            </div>
                                            <div class="score-input">
                                                <?php for ($value = 1; $value <= 5; $value++): ?>
                                                    <label class="<?= $selected === $value ? 'is-on' : '' ?>">
                                                        <input type="radio" name="scores[<?= $indicatorId ?>]" value="<?= $value ?>"
                                                            <?= $selected === $value ? 'checked' : '' ?> required>
                                                        <span><?= $value ?></span>
                                                        <i><?= e($scale[$value]) ?></i>
                                                    </label>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="card" style="margin-top:16px">
                        <div class="card__head"><div><h2>Catatan Pencapaian</h2>
                            <div class="sub">Ringkas pencapaian, kendala, dan rencana perbaikan periode ini.</div></div></div>
                        <div class="card__body">
                            <div class="field">
                                <label>Catatan untuk atasan</label>
                                <textarea class="textarea" name="self_note" rows="5" maxlength="500"
                                          placeholder="Contoh: menyelesaikan proyek A dua minggu lebih cepat, kendala koordinasi lintas unit…"><?= e((string) ($old['self_note'] ?? $current['self_note'] ?? '')) ?></textarea>
                                <span class="hint">Semua indikator wajib dinilai sebelum dapat dikirim.</span>
                            </div>
                        </div>
                        <div class="card__foot" style="padding:12px 16px;display:flex;justify-content:flex-end">
                            <button class="btn btn-primary" type="submit"><?= icon('send') ?> Kirim Self Assessment</button>
                        </div>
                    </section>
                </form>
            <?php else: ?>
                <section class="card">
                    <div class="card__head">
                        <div><h2>Rincian Penilaian — <?= e($current['period_name']) ?></h2>
                            <div class="sub">Nilai atasan ditampilkan setelah hasil final ditetapkan HR.</div></div>
                        <span class="badge badge-<?= $statusTones[$status] ?? 'slate' ?>"><?= e($statuses[$status] ?? $status) ?></span>
                    </div>
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr><th>Indikator</th><th>Kategori</th><th class="num">Bobot</th>
                                <th class="num">Nilai Saya</th><th class="num">Nilai Atasan</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($scores as $score): ?>
                                <tr>
                                    <td><b><?= e($score['name']) ?></b>
                                        <div class="muted mono" style="font-size:9.6px"><?= e($score['code']) ?></div></td>
                                    <td class="small"><span class="chip chip-<?= $catTones[$score['category']] ?? 'slate' ?>">
                                        <?= e($categories[$score['category']] ?? $score['category']) ?></span></td>
                                    <td class="num small"><?= number_format((float) $score['weight'], 0, ',', '.') ?></td>
                                    <td class="num"><b><?= $score['self_score'] !== null ? (int) $score['self_score'] : '—' ?></b></td>
                                    <td class="num">
                                        <?php if ($isFinal && $score['manager_score'] !== null): ?>
                                            <b><?= (int) $score['manager_score'] ?></b>
                                        <?php else: ?>
                                            <span class="muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($pendingOthers): ?>
                        <div class="card__body">
                            <div class="alert alert-info" style="margin:0">
                                <?= icon('clock') ?>
                                <div>Self assessment Anda sudah terkirim. <?= $status === 'SELF_DONE'
                                    ? 'Menunggu penilaian atasan langsung.'
                                    : 'Menunggu kalibrasi dan penetapan hasil oleh HR.' ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($isFinal): ?>
                        <div class="card__body flex-col gap-12">
                            <?php if ($current['manager_note']): ?>
                                <div>
                                    <div class="small muted" style="margin-bottom:3px">Umpan balik atasan</div>
                                    <div class="small" style="line-height:1.65"><?= nl2br(e($current['manager_note'])) ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if ($current['calibration_note']): ?>
                                <div>
                                    <div class="small muted" style="margin-bottom:3px">Catatan kalibrasi HR</div>
                                    <div class="small" style="line-height:1.65"><?= nl2br(e($current['calibration_note'])) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if (!$current['acknowledged_at']): ?>
                            <div class="card__foot" style="padding:12px 16px;display:flex;justify-content:flex-end">
                                <form method="post" action="<?= url('performance/' . (int) $current['id'] . '/acknowledge') ?>"><?= csrf_field() ?>
                                    <button class="btn btn-soft btn-sm" type="submit"><?= icon('check') ?> Konfirmasi Sudah Membaca</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>

        <div class="flex-col gap-16">
            <section class="card">
                <div class="card__head"><div><h2>Hasil Periode Ini</h2></div></div>
                <div class="card__body">
                    <div class="score-hero">
                        <div class="score-hero__value"><?= $isFinal ? $fmt($current['final_score'] !== null ? (float) $current['final_score'] : null) : '—' ?></div>
                        <?php if ($isFinal && $current['rating']): ?>
                            <span class="rating-chip rating-<?= $ratingTones[$current['rating']] ?? 'slate' ?>" style="height:24px;font-size:11px">
                                <?= e($current['rating']) ?> · <?= e($ratings[$current['rating']] ?? '') ?>
                            </span>
                        <?php else: ?>
                            <span class="small muted">Belum final</span>
                        <?php endif; ?>
                    </div>
                    <div class="meta-list" style="margin-top:14px">
                        <div><span>Periode</span><b><?= e($current['period_name']) ?></b></div>
                        <div><span>Rentang</span><b><?= tanggal($current['start_date'], 'd M Y') ?>–<?= tanggal($current['end_date'], 'd M Y') ?></b></div>
                        <div><span>Batas self</span><b><?= $current['self_due'] ? tanggal($current['self_due'], 'd M Y') : '—' ?></b></div>
                        <div><span>Nilai self</span><b><?= $fmt($current['self_score'] !== null ? (float) $current['self_score'] : null) ?></b></div>
                        <div><span>Bobot self</span><b><?= (int) $current['self_weight'] ?>%</b></div>
                        <div><span>Konfirmasi</span><b><?= $current['acknowledged_at'] ? tanggal($current['acknowledged_at'], 'd M Y') : 'Belum' ?></b></div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><div><h2>Alur</h2></div></div>
                <div class="card__body">
                    <div class="timeline">
                        <div class="timeline__item"><b>Self assessment</b>
                            <span><?= $current['self_submitted_at'] ? tanggal($current['self_submitted_at'], 'd M Y H:i') : 'Belum dikirim' ?></span></div>
                        <div class="timeline__item<?= $current['manager_submitted_at'] ? '' : ' muted' ?>"><b>Penilaian atasan</b>
                            <span><?= $current['manager_submitted_at'] ? tanggal($current['manager_submitted_at'], 'd M Y H:i') : 'Menunggu' ?></span></div>
                        <div class="timeline__item<?= $isFinal ? '' : ' muted' ?>"><b>Hasil final</b>
                            <span><?= $isFinal ? tanggal($current['calibrated_at'], 'd M Y H:i') : 'Menunggu kalibrasi HR' ?></span></div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <?php if ($history): ?>
        <section class="card" style="margin-top:16px">
            <div class="card__head"><div><h2>Riwayat Penilaian</h2>
                <div class="sub">Seluruh periode yang pernah Anda ikuti.</div></div></div>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                    <tr><th>Periode</th><th>Rentang</th><th class="num">Self</th><th class="num">Atasan</th>
                        <th class="num">Akhir</th><th>Mutu</th><th>Status</th><th style="width:60px"></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $row): ?>
                        <tr>
                            <td><b><?= e($row['period_name']) ?></b></td>
                            <td class="small muted"><?= tanggal($row['start_date'], 'd M Y') ?>–<?= tanggal($row['end_date'], 'd M Y') ?></td>
                            <td class="num small"><?= $fmt($row['self_score'] !== null ? (float) $row['self_score'] : null) ?></td>
                            <td class="num small"><?= $row['status'] === 'FINAL' ? $fmt($row['manager_score'] !== null ? (float) $row['manager_score'] : null) : '—' ?></td>
                            <td class="num"><b><?= $row['status'] === 'FINAL' ? $fmt($row['final_score'] !== null ? (float) $row['final_score'] : null) : '—' ?></b></td>
                            <td>
                                <?php if ($row['rating']): ?>
                                    <span class="rating-chip rating-<?= $ratingTones[$row['rating']] ?? 'slate' ?>"><?= e($row['rating']) ?></span>
                                <?php else: ?><span class="muted small">—</span><?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?= $statusTones[$row['status']] ?? 'slate' ?>"><?= e($statuses[$row['status']] ?? $row['status']) ?></span></td>
                            <td><a class="btn btn-sm btn-ghost" href="<?= url('performance/' . (int) $row['id']) ?>" title="Detail"><?= icon('arrow-right') ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>

<script>
(function () {
    document.querySelectorAll('.score-input').forEach(function (group) {
        group.addEventListener('change', function () {
            group.querySelectorAll('label').forEach(function (label) {
                label.classList.toggle('is-on', !!label.querySelector('input:checked'));
            });
        });
    });
})();
</script>
