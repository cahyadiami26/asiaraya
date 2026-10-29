<?php
/**
 * Penilaian bawahan langsung (atasan).
 * @var array $reviews, $statuses, $statusTones, $ratings, $ratingTones
 * @var int $pending
 */
use App\Models\PerformanceModel;

$sorted = $reviews;
usort($sorted, static function ($a, $b) {
    $rank = ['SELF_DONE' => 0, 'MANAGER_DONE' => 1, 'PENDING' => 2, 'FINAL' => 3];
    return ($rank[$a['status']] ?? 9) <=> ($rank[$b['status']] ?? 9);
});
?>
<div class="page-head">
    <div>
        <h1>Penilaian Tim</h1>
        <div class="sub">Nilai self assessment bawahan langsung Anda. Hasil akhir ditetapkan HR setelah kalibrasi.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('me/performance') ?>"><?= icon('award') ?> Penilaian Saya</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Menunggu Anda</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($pending) ?></span>
        <span class="s">self assessment siap dinilai</span>
    </div>
    <div class="mini-stat">
        <span class="k">Total Bawahan</span>
        <span class="v"><?= number_id(count($reviews)) ?></span>
        <span class="s">pada seluruh periode</span>
    </div>
    <div class="mini-stat">
        <span class="k">Sudah Dinilai</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id(count(array_filter($reviews, static fn ($r) => $r['manager_submitted_at'] !== null))) ?></span>
        <span class="s">menunggu kalibrasi HR</span>
    </div>
    <div class="mini-stat">
        <span class="k">Final</span>
        <span class="v"><?= number_id(count(array_filter($reviews, static fn ($r) => $r['status'] === 'FINAL'))) ?></span>
        <span class="s">hasil sudah ditetapkan</span>
    </div>
</div>

<section class="card">
    <div class="card__head">
        <div><h2>Daftar Bawahan</h2>
            <div class="sub">Diurutkan dari yang menunggu penilaian Anda.</div></div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr><th>Karyawan</th><th>Periode</th><th class="num">Self</th><th class="num">Atasan</th>
                <th class="num">Akhir</th><th>Status</th><th style="width:150px">Aksi</th></tr>
            </thead>
            <tbody>
            <?php foreach ($sorted as $review): ?>
                <tr>
                    <td>
                        <b><?= e($review['full_name']) ?></b>
                        <div class="muted mono" style="font-size:9.6px"><?= e($review['employee_code']) ?></div>
                    </td>
                    <td class="small">
                        <?= e($review['period_name']) ?>
                        <div class="muted" style="font-size:9.8px"><?= e($review['department_name'] ?? $review['unit_name'] ?? '-') ?></div>
                    </td>
                    <td class="num small"><?= PerformanceModel::scoreLabel($review['self_score'] !== null ? (float) $review['self_score'] : null) ?></td>
                    <td class="num small"><?= PerformanceModel::scoreLabel($review['manager_score'] !== null ? (float) $review['manager_score'] : null) ?></td>
                    <td class="num"><b><?= PerformanceModel::scoreLabel($review['final_score'] !== null ? (float) $review['final_score'] : null) ?></b></td>
                    <td><span class="badge badge-<?= $statusTones[$review['status']] ?? 'slate' ?>"><?= e($statuses[$review['status']] ?? $review['status']) ?></span></td>
                    <td>
                        <div class="flex" style="gap:5px;justify-content:flex-end">
                            <?php if ($review['status'] === 'SELF_DONE'): ?>
                                <a class="btn btn-sm btn-primary" href="<?= url('performance/team/' . (int) $review['id']) ?>"><?= icon('edit') ?> Nilai</a>
                            <?php else: ?>
                                <a class="btn btn-sm btn-outline" href="<?= url('performance/' . (int) $review['id']) ?>"><?= icon('eye') ?> Detail</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$reviews): ?>
                <tr><td colspan="7">
                    <div class="empty-state">
                        <div class="empty-icon"><?= icon('users') ?></div>
                        <h3>Belum ada bawahan langsung</h3>
                        <p>Penilaian akan muncul di sini ketika HR membuka periode dan karyawan mengirim self assessment.</p>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
