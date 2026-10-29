<?php
/**
 * Form penilaian atasan.
 * @var array $review, $scores, $scale, $categories, $catTones, $errors, $old
 * @var bool $isHr
 */
use App\Models\PerformanceModel;

$fmt = static fn (?float $value): string => PerformanceModel::scoreLabel($value);
$oldScores = is_array($old['scores'] ?? null) ? $old['scores'] : [];

$grouped = [];
foreach ($scores as $score) {
    $grouped[$score['category']][] = $score;
}
?>
<div class="page-head">
    <div>
        <div class="small muted" style="margin-bottom:4px">
            <a href="<?= url('performance/team') ?>">Penilaian Tim</a> / Nilai
        </div>
        <h1 style="font-size:22px"><?= e($review['full_name']) ?></h1>
        <div class="sub">
            <?= e($review['employee_code']) ?> · <?= e($review['period_name']) ?>
            · <?= tanggal($review['start_date'], 'd M Y') ?>–<?= tanggal($review['end_date'], 'd M Y') ?>
        </div>
    </div>
    <div class="page-head__actions">
        <span class="badge badge-indigo">Menunggu Penilaian Atasan</span>
        <a class="btn btn-outline" href="<?= url('performance/' . (int) $review['id']) ?>"><?= icon('eye') ?> Detail</a>
    </div>
</div>

<?php if ($isHr && (int) ($review['manager_id'] ?? 0) === 0): ?>
    <div class="alert alert-info" style="margin-bottom:14px">
        <?= icon('info') ?>
        <div>Karyawan ini tidak memiliki atasan langsung pada struktur organisasi, sehingga penilaian dilakukan oleh HR.</div>
    </div>
<?php endif; ?>

<form method="post" action="<?= url('performance/team/' . (int) $review['id']) ?>">
    <?= csrf_field() ?>
    <div class="split-grid wide-left">
        <div class="flex-col gap-16">
            <section class="card">
                <div class="card__head">
                    <div><h2>Penilaian Indikator</h2>
                        <div class="sub">Bandingkan hasil kerja nyata dengan ekspektasi; self assessment karyawan ditampilkan sebagai pembanding.</div></div>
                </div>
                <div class="card__body flex-col gap-16">
                    <?php foreach ($grouped as $category => $rows): ?>
                        <div>
                            <div class="section-label"><?= e($categories[$category] ?? $category) ?></div>
                            <?php foreach ($rows as $score): ?>
                                <?php
                                $indicatorId = (int) $score['indicator_id'];
                                $selected = (int) ($oldScores[$indicatorId] ?? $score['manager_score'] ?? 0);
                                ?>
                                <div class="assess-row">
                                    <div class="assess-row__info">
                                        <b><?= e($score['name']) ?></b>
                                        <?php if ($score['description']): ?>
                                            <span><?= e($score['description']) ?></span>
                                        <?php endif; ?>
                                        <div class="assess-row__meta">
                                            <span class="chip chip-slate">Bobot <?= number_format((float) $score['weight'], 0, ',', '.') ?></span>
                                            <span class="chip chip-<?= $catTones[$score['category']] ?? 'slate' ?>">
                                                Self: <?= $score['self_score'] !== null ? (int) $score['self_score'] : '—' ?>
                                            </span>
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
                    <?php if (!$scores): ?>
                        <div class="empty-state" style="padding:20px">
                            <p>Indikator penilaian belum tersedia pada periode ini.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><div><h2>Umpan Balik</h2>
                    <div class="sub">Catatan ini dibaca karyawan pada halaman penilaiannya.</div></div></div>
                <div class="card__body">
                    <div class="field">
                        <label>Catatan penilaian atasan</label>
                        <textarea class="textarea" name="note" rows="5" maxlength="500"
                                  placeholder="Kekuatan, area perbaikan, dan target periode berikutnya…"><?= e((string) ($old['note'] ?? $review['manager_note'] ?? '')) ?></textarea>
                    </div>
                </div>
                <div class="card__foot" style="padding:12px 16px;display:flex;justify-content:flex-end;gap:8px">
                    <a class="btn btn-ghost" href="<?= url('performance/team') ?>">Batal</a>
                    <button class="btn btn-primary" type="submit"><?= icon('check') ?> Kirim Penilaian</button>
                </div>
            </section>
        </div>

        <div class="flex-col gap-16">
            <section class="card">
                <div class="card__head"><div><h2>Self Assessment</h2>
                    <div class="sub">Dikirim <?= $review['self_submitted_at'] ? tanggal($review['self_submitted_at'], 'd M Y H:i') : '—' ?></div></div></div>
                <div class="card__body">
                    <div class="flex-between" style="margin-bottom:8px">
                        <span class="small muted">Nilai self</span>
                        <b style="font-size:18px"><?= $fmt($review['self_score'] !== null ? (float) $review['self_score'] : null) ?></b>
                    </div>
                    <div class="small" style="line-height:1.65">
                        <?= $review['self_note'] ? nl2br(e($review['self_note'])) : '<span class="muted">Karyawan tidak menulis catatan.</span>' ?>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><div><h2>Panduan Skala</h2></div></div>
                <div class="card__body">
                    <div class="meta-list">
                        <?php foreach ($scale as $value => $label): ?>
                            <div><span><?= $value ?></span><b style="text-align:left"><?= e($label) ?></b></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
</form>

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
