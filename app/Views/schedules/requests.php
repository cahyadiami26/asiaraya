<?php
/**
 * Persetujuan perubahan jadwal.
 * @var array $requests, $stats, $filters, $units
 * @var callable $canApprove
 */
$statusTabs = [
    'OPEN'             => 'Perlu Tindakan',
    'MANAGER_APPROVED' => 'Menunggu HR',
    'APPROVED'         => 'Disetujui',
    'REJECTED'         => 'Ditolak',
    'ALL'              => 'Semua',
];
$current = $filters['status'] ?: 'ALL';
$stepLabels = ['PENDING' => 1, 'MANAGER_APPROVED' => 2, 'APPROVED' => 3, 'REJECTED' => 0];
?>
<div class="page-head">
    <div>
        <h1>Perubahan Jadwal</h1>
        <div class="sub">Alur dua tahap: atasan langsung → HR. Persetujuan HR langsung memperbarui jadwal karyawan.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('schedules') ?>"><?= icon('calendar-check') ?> Penjadwalan</a>
        <a class="btn btn-soft" href="<?= url('me/schedule') ?>"><?= icon('send') ?> Ajukan Perubahan</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Menunggu Atasan</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($stats['manager']) ?></span>
        <span class="s">Tahap 1</span>
    </div>
    <div class="mini-stat">
        <span class="k">Menunggu HR</span>
        <span class="v" style="color:var(--indigo-600, #4f46e5)"><?= number_id($stats['awaiting_hr']) ?></span>
        <span class="s">Tahap 2</span>
    </div>
    <div class="mini-stat">
        <span class="k">Disetujui</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id($stats['approved']) ?></span>
        <span class="s">Jadwal diperbarui</span>
    </div>
    <div class="mini-stat">
        <span class="k">Ditolak</span>
        <span class="v"><?= number_id($stats['rejected']) ?></span>
        <span class="s">Tidak mengubah jadwal</span>
    </div>
</div>

<section class="card">
    <form class="filter-bar" method="get" action="<?= url('schedules/requests') ?>">
        <div class="segmented">
            <?php foreach ($statusTabs as $key => $label): ?>
                <a class="<?= $current === $key ? 'active' : '' ?>" href="<?= url('schedules/requests?status=' . $key) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="input-group" style="max-width:280px">
            <?= icon('search') ?>
            <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Cari karyawan…">
        </div>
        <select class="select" name="unit" data-auto-submit style="min-width:170px">
            <option value="">Semua unit</option>
            <?php foreach ($units as $unit): ?>
                <option value="<?= (int) $unit['id'] ?>" <?= (int) $filters['unit'] === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="status" value="<?= e($current) ?>">
        <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Cari</button>
    </form>

    <div class="request-list">
        <?php foreach ($requests as $request): ?>
            <?php
            $step = $stepLabels[$request['status']] ?? 0;
            $decidable = $canApprove($request);
            ?>
            <article class="request-card">
                <div class="request-card__main">
                    <div class="request-card__person">
                        <span class="avatar" style="background:<?= e(avatar_color($request['email'])) ?>"><?= e(initials($request['full_name'])) ?></span>
                        <div>
                            <b><?= e($request['full_name']) ?></b>
                            <span class="small muted"><?= e($request['employee_code']) ?> · <?= e($request['unit_name'] ?? '-') ?><?= $request['department_name'] ? ' · ' . e($request['department_name']) : '' ?></span>
                        </div>
                        <?= status_badge($request['status']) ?>
                    </div>

                    <div class="request-card__swap">
                        <div class="swap-side">
                            <span class="k">Saat ini</span>
                            <b><?= e($request['current_shift_name'] ?: 'Belum dijadwalkan') ?></b>
                            <?php if ($request['current_check_in']): ?>
                                <span class="small muted mono"><?= e(substr($request['current_check_in'], 0, 5)) ?>–<?= e(substr($request['current_check_out'], 0, 5)) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="swap-arrow"><?= icon('arrow-right', 'ico') ?></span>
                        <div class="swap-side">
                            <span class="k">Diminta</span>
                            <b style="color:<?= e($request['requested_color'] ?: 'var(--teal-700)') ?>"><?= e($request['requested_shift_name']) ?></b>
                            <span class="small muted mono"><?= e(substr($request['requested_check_in'], 0, 5)) ?>–<?= e(substr($request['requested_check_out'], 0, 5)) ?></span>
                        </div>
                        <div class="swap-side">
                            <span class="k">Tanggal</span>
                            <b><?= tanggal($request['start_date']) ?></b>
                            <span class="small muted">s.d. <?= tanggal($request['end_date']) ?></span>
                        </div>
                    </div>

                    <?php if ($request['reason']): ?>
                        <div class="request-card__reason"><?= icon('info', 'ico') ?> <?= e($request['reason']) ?></div>
                    <?php endif; ?>

                    <div class="request-card__steps">
                        <span class="step <?= $step >= 1 ? 'done' : '' ?>">1. Pengajuan</span>
                        <span class="step <?= $step >= 2 ? 'done' : '' ?>">2. Atasan <?= $request['manager_name'] ? '(' . e($request['manager_name']) . ')' : '' ?></span>
                        <span class="step <?= $step >= 3 ? 'done' : '' ?>">3. HR</span>
                    </div>
                </div>

                <div class="request-card__side">
                    <?php if ($decidable): ?>
                        <form method="post" action="<?= url('schedules/requests/' . $request['id'] . '/decide') ?>" class="flex-col gap-8">
                            <?= csrf_field() ?>
                            <input class="input" name="note" placeholder="Catatan (opsional)" maxlength="255">
                            <button class="btn btn-teal btn-block" type="submit" name="decision" value="APPROVED">
                                <?= icon('check') ?> Setujui
                            </button>
                            <button class="btn btn-outline btn-block" type="submit" name="decision" value="REJECTED"
                                    style="color:var(--rose-600);border-color:#f6c9d0">
                                <?= icon('x') ?> Tolak
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="request-card__meta">
                            <?php if ($request['manager_approved_at']): ?>
                                <div><span class="k">Disetujui atasan</span><span class="v"><?= tanggal($request['manager_approved_at'], 'd M Y H:i') ?></span></div>
                            <?php endif; ?>
                            <?php if ($request['hr_approved_at']): ?>
                                <div><span class="k">Diputus HR</span><span class="v"><?= tanggal($request['hr_approved_at'], 'd M Y H:i') ?></span></div>
                            <?php endif; ?>
                            <?php if ($request['manager_note']): ?>
                                <div><span class="k">Catatan atasan</span><span class="v"><?= e($request['manager_note']) ?></span></div>
                            <?php endif; ?>
                            <?php if ($request['hr_note']): ?>
                                <div><span class="k">Catatan HR</span><span class="v"><?= e($request['hr_note']) ?></span></div>
                            <?php endif; ?>
                            <?php if ($request['status'] === 'PENDING'): ?>
                                <div class="small muted">Menunggu persetujuan atasan langsung.</div>
                            <?php endif; ?>
                            <?php if (in_array($request['status'], ['MANAGER_APPROVED', 'APPROVED', 'REJECTED'], true) && !$request['manager_note'] && !$request['hr_note']): ?>
                                <div class="small muted">Tidak ada catatan.</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if (!$requests): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= icon('calendar-check') ?></div>
                <h3>Tidak ada pengajuan pada filter ini</h3>
                <p>Pengajuan perubahan jadwal dari karyawan akan muncul di sini untuk diproses.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
