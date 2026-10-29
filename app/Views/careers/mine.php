<?php
/**
 * Riwayat jabatan saya (portal karyawan) — Batch 19.
 * @var array|null $employee
 * @var array $history, $types
 */
use App\Models\CareerModel;

$current = $history[0] ?? null;
?>
<div class="page-head">
    <div>
        <h1>Riwayat Jabatan Saya</h1>
        <div class="sub">Jejak perjalanan karier Anda di perusahaan — pengangkatan, promosi, mutasi, dan penyesuaian gaji.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('me/documents') ?>"><?= icon('file') ?> Dokumen Saya</a>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Linimasa Karier</h2>
                <div class="sub"><?= count($history) ?> catatan perubahan tercatat.</div></div>
        </div>
        <div class="card__body">
            <?php if ($history): ?>
                <div class="timeline">
                    <?php foreach ($history as $item): ?>
                        <div class="timeline__item">
                            <b><?= e(CareerModel::typeLabel((string) $item['change_type'])) ?>
                                <?php if ((string) $item['change_type'] === 'PROMOTION'): ?>
                                    <span class="badge badge-teal" style="margin-left:6px">Naik</span>
                                <?php endif; ?>
                            </b>
                            <span>
                                <?= tanggal((string) $item['effective_date']) ?>
                                <?= !empty($item['end_date']) ? ' – ' . tanggal((string) $item['end_date']) : '' ?>
                                <?= $item['position_name'] ? ' · ' . e($item['position_name']) : '' ?>
                                <?= $item['department_name'] ? ' (' . e($item['department_name']) . ')' : '' ?>
                            </span>
                            <?php if (!empty($item['sk_number'])): ?>
                                <span class="muted mono" style="font-size:10px">SK <?= e($item['sk_number']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($item['note'])): ?>
                                <span class="muted" style="font-size:10.5px"><?= e($item['note']) ?></span>
                            <?php endif; ?>
                            <?php if ((float) $item['basic_salary'] > 0 && (float) $item['previous_salary'] > 0 && (float) $item['basic_salary'] !== (float) $item['previous_salary']): ?>
                                <span style="font-size:10.5px;color:<?= (float) $item['basic_salary'] > (float) $item['previous_salary'] ? 'var(--teal-700)' : 'var(--rose-600)' ?>">
                                    <?= rupiah((float) $item['previous_salary']) ?> → <?= rupiah((float) $item['basic_salary']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><?= icon('trending') ?></div>
                    <h3>Belum ada riwayat</h3>
                    <p>Riwayat jabatan akan muncul di sini setelah HR mencatat pengangkatan atau perubahan posisi Anda.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head"><div><h2>Posisi Saat Ini</h2></div></div>
            <div class="card__body" style="padding-top:6px">
                <div class="detail-list">
                    <div class="detail-list__row"><span>Jabatan</span><b><?= e((string) ($current['position_name'] ?? '—')) ?></b></div>
                    <div class="detail-list__row"><span>Departemen</span><b><?= e((string) ($current['department_name'] ?? '—')) ?></b></div>
                    <div class="detail-list__row"><span>Unit</span><b><?= e((string) ($current['unit_name'] ?? '—')) ?></b></div>
                    <div class="detail-list__row"><span>Status</span><b><?= e((string) ($current['employment_status'] ?? '—')) ?></b></div>
                    <div class="detail-list__row"><span>Berlaku sejak</span><b><?= $current ? tanggal((string) $current['effective_date']) : '—' ?></b></div>
                </div>
            </div>
            <div class="card__foot small muted" style="padding:10px 16px">
                Data di atas berasal dari master kepegawaian. Bila ada kekeliruan, hubungi HR.
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Jenis Catatan</h2></div></div>
            <div class="card__body small" style="padding-top:6px">
                <div class="detail-list">
                    <?php foreach ($types as $key => $meta): ?>
                        <div class="detail-list__row">
                            <span><?= icon((string) $meta['icon']) ?> <?= e($meta['label']) ?></span>
                            <b><?= (int) count(array_filter($history, static fn (array $h): bool => $h['change_type'] === $key)) ?>×</b>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>
</div>
