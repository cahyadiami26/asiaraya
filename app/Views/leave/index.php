<?php
/**
 * Manajemen cuti (HR): antrean persetujuan, saldo ringkas, kalender cuti.
 * @var array $rows, $stats, $balanceStats, $filters, $types, $units, $departments, $employees, $years, $byType, $upcoming, $policy, $paginator
 */
use App\Models\LeaveModel;

$tabs = [
    'ALL'              => 'Semua',
    'OPEN'             => 'Perlu Tindakan',
    'PENDING'          => 'Menunggu Atasan',
    'MANAGER_APPROVED' => 'Menunggu HR',
    'APPROVED'         => 'Disetujui',
    'REJECTED'         => 'Ditolak',
];
$current = $filters['status'] ?: 'ALL';
$year = (int) $filters['year'];

$baseQuery = array_filter([
    'status'     => $current !== 'ALL' ? $current : null,
    'type'       => $filters['type'] ?: null,
    'unit'       => $filters['unit'] ?: null,
    'department' => $filters['department'] ?: null,
    'employee'   => $filters['employee'] ?: null,
    'year'       => $year,
    'q'          => $filters['q'] ?: null,
]);
$link = static fn (int $page): string => url('leave') . '?' . http_build_query($baseQuery + ['page' => $page]);
$maxDays = 1.0;
foreach ($byType as $typeRow) {
    $maxDays = max($maxDays, (float) $typeRow['days']);
}
$stepOf = static fn (string $status): int => ['PENDING' => 1, 'MANAGER_APPROVED' => 2, 'APPROVED' => 3][$status] ?? 0;
?>
<div class="page-head">
    <div>
        <h1><?= $isHr ? 'Manajemen Cuti' : 'Persetujuan Cuti Tim' ?></h1>
        <div class="sub">
            <?= $isHr
                ? 'Antrean persetujuan dua tahap, saldo tahunan, dan rekap cuti ' . $year . '.'
                : 'Pengajuan cuti anggota tim Anda yang menunggu keputusan atasan langsung.' ?>
        </div>
    </div>
    <div class="page-head__actions">
        <?php if ($isHr): ?>
            <a class="btn btn-outline" href="<?= url('leave/balances?year=' . $year) ?>"><?= icon('layers') ?> Saldo Cuti</a>
            <a class="btn btn-outline" href="<?= url('leave/types') ?>"><?= icon('settings') ?> Jenis &amp; Jatah</a>
            <a class="btn btn-primary" href="<?= url('leave/export?year=' . $year) ?>"><?= icon('download') ?> Export CSV</a>
        <?php else: ?>
            <a class="btn btn-outline" href="<?= url('me/leave') ?>"><?= icon('file') ?> Cuti Saya</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($isHr && $balanceStats['negative'] > 0): ?>
    <div class="alert alert-warning" style="margin-bottom:16px">
        <?= icon('alert') ?>
        <div>
            <b><?= number_id($balanceStats['negative']) ?> baris saldo minus</b> — cuti melebihi jatah.
            <a class="link-quiet" href="<?= url('leave/balances?year=' . $year . '&status=NEGATIVE') ?>">Tinjau saldo</a>
            <?php if (!$policy['allow_negative']): ?>
                · pengajuan baru otomatis ditolak sampai saldo diperbaiki.
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($isHr): ?>
<div class="kpi-grid" style="margin-bottom:16px">
    <div class="kpi">
        <div class="kpi__label"><span>Menunggu Atasan</span><span class="kpi__icon amber"><?= icon('clock') ?></span></div>
        <div class="kpi__value" style="color:var(--amber-600)"><?= number_id($stats['pending']) ?></div>
        <div class="kpi__trend" style="font-weight:500;color:var(--muted)">Tahap 1 · atasan langsung</div>
    </div>
    <div class="kpi">
        <div class="kpi__label"><span>Menunggu HR</span><span class="kpi__icon indigo"><?= icon('shield') ?></span></div>
        <div class="kpi__value" style="color:var(--indigo-600, #4f46e5)"><?= number_id($stats['awaiting_hr']) ?></div>
        <div class="kpi__trend" style="font-weight:500;color:var(--muted)">Tahap 2 · keputusan final</div>
    </div>
    <div class="kpi">
        <div class="kpi__label"><span>Cuti Disetujui <?= $year ?></span><span class="kpi__icon teal"><?= icon('calendar-check') ?></span></div>
        <div class="kpi__value"><?= number_id($stats['approved_days'], 1) ?><span class="unit">hari</span></div>
        <div class="kpi__trend"><?= number_id($stats['people']) ?> karyawan · <?= number_id($stats['approved']) ?> pengajuan</div>
    </div>
    <div class="kpi">
        <div class="kpi__label"><span>Saldo Terpakai</span><span class="kpi__icon"><?= icon('layers') ?></span></div>
        <div class="kpi__value" style="font-size:22px"><?= number_id($balanceStats['used'], 1) ?><span class="unit">hari</span></div>
        <div class="kpi__trend" style="font-weight:500;color:var(--muted)">
            dari <?= number_id($balanceStats['quota'], 1) ?> hari jatah · <?= number_id($balanceStats['low']) ?> saldo menipis
        </div>
    </div>
</div>

<?php endif; ?>

<?php if ($isHr): ?>
<div class="split-grid wide-left" style="margin-bottom:16px">
    <section class="card">
        <div class="card__head">
            <div><h2>Cuti per Jenis</h2>
                <div class="sub">Hari cuti disetujui pada <?= $year ?>.</div></div>
            <span class="badge badge-slate"><?= number_id($stats['total']) ?> pengajuan</span>
        </div>
        <div class="card__body">
            <div class="leave-bars">
                <?php foreach ($byType as $typeRow): ?>
                    <div class="leave-bars__row">
                        <span class="leave-bars__label">
                            <span class="dot" style="background:<?= e($typeRow['color']) ?>"></span>
                            <?= e($typeRow['name']) ?>
                            <?php if ((int) $typeRow['quota_days'] === 0): ?>
                                <span class="badge badge-slate" style="height:16px;font-size:9px">tanpa jatah</span>
                            <?php endif; ?>
                        </span>
                        <span class="leave-bars__bar">
                            <span style="width:<?= (int) round((float) $typeRow['days'] / $maxDays * 100) ?>%;background:<?= e($typeRow['color']) ?>"></span>
                        </span>
                        <span class="leave-bars__value"><?= number_id($typeRow['days'], 1) ?> hari</span>
                        <span class="leave-bars__count"><?= number_id($typeRow['requests']) ?> pengajuan</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card__head">
            <div><h2>Cuti Berjalan</h2>
                <div class="sub">Sudah disetujui, mulai hari ini atau nanti.</div></div>
        </div>
        <div class="list-rows">
            <?php foreach ($upcoming as $item): ?>
                <div class="list-row">
                    <span class="avatar" style="background:<?= e(avatar_color($item['email'])) ?>"><?= e(initials($item['full_name'])) ?></span>
                    <div class="list-row__body">
                        <b><?= e($item['full_name']) ?></b>
                        <span>
                            <?= e($item['type_name']) ?> · <?= tanggal($item['start_date']) ?>
                            <?php if ($item['start_date'] !== $item['end_date']): ?>–<?= tanggal($item['end_date']) ?><?php endif; ?>
                        </span>
                    </div>
                    <span class="badge badge-teal"><?= number_id($item['days'], 1) ?> h</span>
                </div>
            <?php endforeach; ?>
            <?php if (!$upcoming): ?>
                <div class="list-row">
                    <div class="list-row__body">
                        <b>Belum ada cuti berjalan</b>
                        <span>Pengajuan yang disetujui HR akan muncul di sini.</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php endif; ?>

<section class="card">
    <form class="filter-bar" method="get" action="<?= url('leave') ?>">
        <div class="segmented">
            <?php foreach ($tabs as $key => $label): ?>
                <?php $tabQuery = array_filter(array_merge($baseQuery, ['status' => $key === 'ALL' ? null : $key])); ?>
                <a class="<?= $current === $key ? 'active' : '' ?>" href="<?= url('leave') . '?' . http_build_query($tabQuery) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <select class="select" name="year" data-auto-submit style="min-width:104px">
            <?php foreach ($years as $option): ?>
                <option value="<?= (int) $option ?>" <?= $year === (int) $option ? 'selected' : '' ?>><?= (int) $option ?></option>
            <?php endforeach; ?>
        </select>
        <select class="select" name="type" data-auto-submit style="min-width:150px">
            <option value="">Semua jenis</option>
            <?php foreach ($types as $type): ?>
                <option value="<?= (int) $type['id'] ?>" <?= (int) $filters['type'] === (int) $type['id'] ? 'selected' : '' ?>><?= e($type['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($isHr): ?>
            <select class="select" name="unit" data-auto-submit style="min-width:145px">
                <option value="">Semua unit</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= (int) $unit['id'] ?>" <?= (int) $filters['unit'] === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="department" data-auto-submit style="min-width:145px">
                <option value="">Semua departemen</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= (int) $dept['id'] ?>" <?= (int) $filters['department'] === (int) $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <div class="input-group" style="max-width:190px">
            <?= icon('search') ?>
            <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Nama / ID / alasan…">
        </div>
        <input type="hidden" name="status" value="<?= e($current) ?>">
        <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
    </form>

    <div class="request-list">
        <?php foreach ($rows as $row): ?>
            <?php
            $step = $stepOf($row['status']);
            $decidable = $canDecide($row);
            ?>
            <article class="request-card">
                <div class="request-card__main">
                    <div class="request-card__person">
                        <?php if (!empty($row['photo'])): ?>
                            <img class="avatar" src="<?= url('media/employees/' . basename($row['photo'])) ?>" alt="" style="object-fit:cover">
                        <?php else: ?>
                            <span class="avatar" style="background:<?= e(avatar_color($row['email'])) ?>"><?= e(initials($row['full_name'])) ?></span>
                        <?php endif; ?>
                        <div>
                            <b><?= e($row['full_name']) ?></b>
                            <span class="small muted">
                                <?= e($row['employee_code']) ?> · <?= e($row['unit_name'] ?? '-') ?><?= $row['department_name'] ? ' · ' . e($row['department_name']) : '' ?>
                            </span>
                        </div>
                        <span class="badge" style="background:<?= e($row['type_color']) ?>1a;color:<?= e($row['type_color']) ?>;border-color:<?= e($row['type_color']) ?>33">
                            <?= e($row['type_name']) ?>
                        </span>
                        <?php if ((int) $row['is_paid'] === 0): ?>
                            <span class="badge badge-rose">Tanpa upah</span>
                        <?php endif; ?>
                        <?= status_badge($row['status']) ?>
                    </div>

                    <div class="request-card__swap">
                        <div class="swap-side">
                            <span class="k">Mulai</span>
                            <b><?= tanggal($row['start_date']) ?></b>
                            <span class="small muted"><?= e(date('l', strtotime($row['start_date']))) ?></span>
                        </div>
                        <span class="swap-arrow"><?= icon('arrow-right', 'ico') ?></span>
                        <div class="swap-side">
                            <span class="k">Selesai</span>
                            <b><?= tanggal($row['end_date']) ?></b>
                            <span class="small muted"><?= e(date('l', strtotime($row['end_date']))) ?></span>
                        </div>
                        <div class="swap-side">
                            <span class="k">Hari Kerja</span>
                            <b><?= number_id($row['days'], 1) ?> hari</b>
                            <?php if ($row['range_days'] !== (int) $row['days']): ?>
                                <span class="small muted"><?= $row['range_days'] ?> tanggal efektif</span>
                            <?php else: ?>
                                <span class="small muted">Senin–Jumat</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($row['reason']): ?>
                        <div class="request-card__reason"><?= icon('info', 'ico') ?> <?= e($row['reason']) ?></div>
                    <?php endif; ?>

                    <?php if ($row['holiday_note']): ?>
                        <div class="request-card__reason">
                            <?= icon('sun', 'ico') ?> Melewati hari besar: <?= e(implode(', ', $row['holiday_note'])) ?> (tidak dihitung cuti)
                        </div>
                    <?php endif; ?>

                    <div class="flex wrap" style="gap:8px;margin-top:10px">
                        <div class="request-card__steps" style="margin-top:0">
                            <span class="step <?= $step >= 1 ? 'done' : '' ?>">1. Pengajuan</span>
                            <span class="step <?= $step >= 2 ? 'done' : '' ?>">2. Atasan <?= $row['manager_name'] ? '(' . e($row['manager_name']) . ')' : '' ?></span>
                            <span class="step <?= $step >= 3 ? 'done' : '' ?>">3. HR</span>
                        </div>
                        <?php if ($row['attachment']): ?>
                            <a class="btn btn-sm btn-ghost" href="<?= url('media/leave/' . basename((string) $row['attachment'])) ?>" target="_blank" rel="noopener">
                                <?= icon('file') ?> Lampiran
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="request-card__side">
                    <?php if ($decidable): ?>
                        <form method="post" action="<?= url('leave/' . $row['id'] . '/decide') ?>" class="flex-col" style="gap:7px">
                            <?= csrf_field() ?>
                            <input class="input" name="note" placeholder="Catatan (opsional)" maxlength="255" style="height:32px;font-size:11.5px">
                            <button class="btn btn-teal btn-block btn-sm" type="submit" name="decision" value="APPROVED"><?= icon('check') ?> Setujui</button>
                            <button class="btn btn-outline btn-block btn-sm" type="submit" name="decision" value="REJECTED"
                                    style="color:var(--rose-600);border-color:#f6c9d0"><?= icon('x') ?> Tolak</button>
                        </form>
                    <?php else: ?>
                        <div class="request-card__meta">
                            <?php if ($row['manager_approved_at']): ?>
                                <div><span class="k">Disetujui atasan</span><span class="v"><?= tanggal($row['manager_approved_at'], 'd M Y H:i') ?></span></div>
                            <?php endif; ?>
                            <?php if ($row['hr_approved_at']): ?>
                                <div><span class="k">Diputus HR</span><span class="v"><?= tanggal($row['hr_approved_at'], 'd M Y H:i') ?></span></div>
                            <?php endif; ?>
                            <?php if ($row['hr_note']): ?>
                                <div><span class="k">Catatan</span><span class="v"><?= e($row['hr_note']) ?></span></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($isHr && in_array($row['status'], ['APPROVED', 'MANAGER_APPROVED', 'PENDING'], true)): ?>
                        <form method="post" action="<?= url('leave/' . $row['id'] . '/cancel') ?>" style="margin-top:8px"
                              data-confirm="Batalkan cuti <?= e($row['full_name']) ?> pada <?= tanggal($row['start_date']) ?>? Saldo akan dikembalikan bila sudah disetujui.">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-ghost btn-block" type="submit" style="color:var(--rose-600)">
                                <?= icon('trash') ?> Batalkan Cuti
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if (!$rows): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= icon('file') ?></div>
                <h3>Tidak ada pengajuan pada filter ini</h3>
                <p>Pengajuan cuti karyawan akan muncul di sini untuk diproses.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="card__foot flex-between wrap" style="padding:10px 16px">
        <span class="small muted">Menampilkan <?= number_id($paginator->from()) ?>–<?= number_id($paginator->to()) ?> dari <?= number_id($paginator->total) ?> pengajuan</span>
        <?php if ($paginator->lastPage() > 1): ?>
            <div class="pagination" style="padding:0">
                <?php if ($paginator->hasPrevious()): ?><a href="<?= e($link($paginator->page - 1)) ?>"><?= icon('chevron-left') ?></a><?php endif; ?>
                <?php foreach ($paginator->window(5) as $page): ?>
                    <?php if ($page === $paginator->page): ?>
                        <span class="active"><?= $page ?></span>
                    <?php else: ?>
                        <a href="<?= e($link($page)) ?>"><?= $page ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($paginator->hasNext()): ?><a href="<?= e($link($paginator->page + 1)) ?>"><?= icon('chevron-right') ?></a><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
