<?php
/**
 * Pipeline pelamar satu lowongan (HR).
 * @var array $job, $funnel, $grouped, $statuses, $statusTones, $stages, $stageTones, $pipeline, $workModes, $sources
 */
$total = max(1, array_sum($funnel));
$accepted = (int) ($funnel['HIRED'] ?? 0);
$headcount = max(1, (int) $job['headcount']);
$filledPct = min(100, (int) round($accepted / $headcount * 100));
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
        <h1><?= e($job['title']) ?></h1>
        <div class="sub">
            <?= e($job['code']) ?> ·
            <?= e($job['position_name'] ?: 'Posisi belum ditentukan') ?> ·
            <?= e($job['unit_name'] ?: 'Semua unit') ?>
        </div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('recruitment') ?>"><?= icon('arrow-left') ?> Lowongan</a>
        <a class="btn btn-outline" href="<?= url('recruitment/candidates?job=' . (int) $job['id']) ?>"><?= icon('users') ?> Pelamar</a>
        <span class="badge badge-<?= $statusTones[$job['status']] ?? 'slate' ?>"><?= e($statuses[$job['status']] ?? $job['status']) ?></span>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Total Pelamar</span>
        <span class="v"><?= number_id(array_sum($funnel)) ?></span>
        <span class="s"><?= number_id($funnel['APPLIED'] ?? 0) ?> belum diseleksi</span>
    </div>
    <div class="mini-stat">
        <span class="k">Dalam Proses</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id(($funnel['SCREENING'] ?? 0) + ($funnel['INTERVIEW'] ?? 0) + ($funnel['OFFER'] ?? 0)) ?></span>
        <span class="s">seleksi berkas → penawaran</span>
    </div>
    <div class="mini-stat">
        <span class="k">Diterima</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id($accepted) ?></span>
        <span class="s">dari kuota <?= number_id($headcount) ?> orang</span>
    </div>
    <div class="mini-stat">
        <span class="k">Tidak Lolos</span>
        <span class="v" style="color:var(--rose-600)"><?= number_id(($funnel['REJECTED'] ?? 0) + ($funnel['WITHDRAWN'] ?? 0)) ?></span>
        <span class="s">ditolak / mengundurkan diri</span>
    </div>
</div>

<div class="split-grid wide-left">
    <div class="flex-col gap-12">
        <section class="card">
            <div class="card__head">
                <div><h2>Pipeline Pelamar</h2>
                    <div class="sub">Klik kartu pelamar untuk memproses tahapan, wawancara, atau menerima sebagai karyawan.</div></div>
            </div>
            <div class="card__body">
                <div class="pipe-grid">
                    <?php foreach ($pipeline as $stage): ?>
                        <?php $items = $grouped[$stage] ?? []; ?>
                        <div class="pipe-col">
                            <div class="pipe-head">
                                <span class="badge badge-<?= $stageTones[$stage] ?? 'slate' ?>"><?= e($stages[$stage]) ?></span>
                                <b><?= number_id(count($items)) ?></b>
                            </div>
                            <div class="pipe-list">
                                <?php foreach ($items as $candidate): ?>
                                    <a class="pipe-card" href="<?= url('recruitment/candidates/' . (int) $candidate['id']) ?>">
                                        <b><?= e($candidate['full_name']) ?></b>
                                        <span class="pipe-card__meta">
                                            <?= e($candidate['education'] ?: 'Pendidikan —') ?> ·
                                            <?= number_id($candidate['experience_years'], 1) ?> th
                                        </span>
                                        <?= $stars($candidate['rating'] !== null ? (int) $candidate['rating'] : null) ?>
                                        <?php if (!empty($candidate['interview_at'])): ?>
                                            <span class="pipe-card__time"><?= icon('calendar') ?> <?= tanggal($candidate['interview_at'], 'd M Y H:i') ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                                <?php if (!$items): ?>
                                    <div class="pipe-empty">Belum ada pelamar</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if (($funnel['HIRED'] ?? 0) + ($funnel['REJECTED'] ?? 0) + ($funnel['WITHDRAWN'] ?? 0) > 0): ?>
                <div class="card__foot">
                    <div class="flex-between" style="flex-wrap:wrap;gap:8px">
                        <span class="small muted">Hasil akhir</span>
                        <span class="flex gap-4" style="flex-wrap:wrap">
                            <span class="chip chip-teal">Diterima <?= number_id($funnel['HIRED'] ?? 0) ?></span>
                            <span class="chip chip-rose">Ditolak <?= number_id($funnel['REJECTED'] ?? 0) ?></span>
                            <span class="chip chip-slate">Mengundurkan diri <?= number_id($funnel['WITHDRAWN'] ?? 0) ?></span>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card__head">
                <div><h2>Funnel Seleksi</h2>
                    <div class="sub">Distribusi pelamar pada setiap tahap.</div></div>
            </div>
            <div class="card__body">
                <?php foreach ($stages as $key => $label): ?>
                    <?php $count = (int) ($funnel[$key] ?? 0); ?>
                    <div class="dist-row">
                        <span class="small" style="min-width:130px"><?= e($label) ?></span>
                        <div class="dist-bar">
                            <span style="width:<?= (int) round($count / $total * 100) ?>%;background:var(--<?= $stageTones[$key] === 'slate' ? 'slate-400' : $stageTones[$key] . '-500' ?>)"></span>
                        </div>
                        <b class="small"><?= number_id($count) ?></b>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="flex-col gap-12">
        <section class="card">
            <div class="card__head"><div><h2>Informasi Lowongan</h2></div></div>
            <div class="card__body">
                <div class="detail-list">
                    <div class="detail-list__row"><span>Kode</span><b class="mono"><?= e($job['code']) ?></b></div>
                    <div class="detail-list__row"><span>Posisi</span><b><?= e($job['position_name'] ?: '—') ?></b></div>
                    <div class="detail-list__row"><span>Departemen</span><b><?= e($job['department_name'] ?: '—') ?></b></div>
                    <div class="detail-list__row"><span>Unit</span><b><?= e($job['unit_name'] ?: '—') ?></b></div>
                    <div class="detail-list__row"><span>Jenis</span><b><?= e($job['employment_type']) ?></b></div>
                    <div class="detail-list__row"><span>Mode kerja</span><b><?= e($workModes[$job['work_mode']] ?? $job['work_mode']) ?></b></div>
                    <div class="detail-list__row"><span>Lokasi</span><b><?= e($job['location'] ?: '—') ?></b></div>
                    <div class="detail-list__row"><span>Rentang gaji</span><b>
                        <?php if ((float) $job['salary_min'] > 0 || (float) $job['salary_max'] > 0): ?>
                            <?= rupiah_short($job['salary_min']) ?> – <?= (float) $job['salary_max'] > 0 ? rupiah_short($job['salary_max']) : '…' ?>
                        <?php else: ?>Dirahasiakan<?php endif; ?>
                    </b></div>
                    <div class="detail-list__row"><span>Periode</span><b><?= tanggal($job['open_date'], 'd M Y') ?><?= $job['close_date'] ? ' – ' . tanggal($job['close_date'], 'd M Y') : '' ?></b></div>
                    <div class="detail-list__row"><span>Hiring manager</span><b><?= e($job['manager_name'] ?: '—') ?></b></div>
                </div>

                <?php if (!empty($job['description'])): ?>
                    <h3 class="sub-title">Deskripsi</h3>
                    <p class="small" style="line-height:1.65;white-space:pre-line"><?= e($job['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($job['requirements'])): ?>
                    <h3 class="sub-title">Kualifikasi</h3>
                    <p class="small" style="line-height:1.65;white-space:pre-line"><?= e($job['requirements']) ?></p>
                <?php endif; ?>
                <?php if (!empty($job['note'])): ?>
                    <div class="alert alert-info" style="margin-top:10px"><?= icon('info') ?><div><?= e($job['note']) ?></div></div>
                <?php endif; ?>

                <div style="margin-top:12px">
                    <div class="flex-between small"><span class="muted">Kuota terisi</span><span><b><?= number_id($accepted) ?></b> / <?= number_id($headcount) ?></span></div>
                    <div class="progress thin" style="margin-top:5px"><span style="width:<?= $filledPct ?>%"></span></div>
                </div>
            </div>
            <div class="card__foot flex gap-4" style="flex-wrap:wrap;justify-content:flex-end">
                <?php if ($job['status'] === 'DRAFT' || $job['status'] === 'ON_HOLD'): ?>
                    <form method="post" action="<?= url('recruitment/jobs/' . (int) $job['id'] . '/open') ?>">
                        <?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit"><?= icon('send') ?> Buka Lowongan</button>
                    </form>
                <?php elseif ($job['status'] === 'OPEN'): ?>
                    <form method="post" action="<?= url('recruitment/jobs/' . (int) $job['id'] . '/hold') ?>">
                        <?= csrf_field() ?><button class="btn btn-sm btn-outline" type="submit"><?= icon('clock') ?> Tahan</button>
                    </form>
                    <form method="post" action="<?= url('recruitment/jobs/' . (int) $job['id'] . '/close') ?>" onsubmit="return confirm('Tutup lowongan ini? Pelamar pada pipeline tetap dapat diproses.');">
                        <?= csrf_field() ?><button class="btn btn-sm btn-teal" type="submit"><?= icon('lock') ?> Tutup</button>
                    </form>
                <?php elseif ($job['status'] === 'ON_HOLD'): ?>
                    <form method="post" action="<?= url('recruitment/jobs/' . (int) $job['id'] . '/close') ?>">
                        <?= csrf_field() ?><button class="btn btn-sm btn-outline" type="submit"><?= icon('lock') ?> Tutup</button>
                    </form>
                <?php endif; ?>
                <?php if ((int) $job['applicants'] === 0): ?>
                    <form method="post" action="<?= url('recruitment/jobs/' . (int) $job['id'] . '/delete') ?>" onsubmit="return confirm('Hapus lowongan ini?');">
                        <?= csrf_field() ?><input type="hidden" name="_method" value="DELETE">
                        <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)"><?= icon('trash') ?> Hapus</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <div><h2>Pelamar Terbaru</h2>
                    <div class="sub">5 pelamar terakhir yang masuk.</div></div>
            </div>
            <div class="card__body">
                <div class="list-rows">
                    <?php $recent = array_merge($grouped['APPLIED'] ?? [], $grouped['SCREENING'] ?? []); ?>
                    <?php foreach (array_slice($recent, 0, 5) as $candidate): ?>
                        <a class="list-row" href="<?= url('recruitment/candidates/' . (int) $candidate['id']) ?>">
                            <div class="avatar" style="background:<?= avatar_color($candidate['full_name']) ?>"><?= e(initials($candidate['full_name'])) ?></div>
                            <div class="list-row__body">
                                <b><?= e($candidate['full_name']) ?></b>
                                <span class="muted small"><?= e($sources[$candidate['source']] ?? $candidate['source']) ?> · <?= tanggal($candidate['created_at'], 'd M Y') ?></span>
                            </div>
                            <span class="badge badge-<?= $stageTones[$candidate['status']] ?? 'slate' ?>"><?= e($stages[$candidate['status']]) ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?>
                        <p class="muted small">Belum ada pelamar baru.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</div>
