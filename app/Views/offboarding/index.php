<?php
/**
 * Antrean offboarding HR: statistik, daftar pengajuan, kontrak akan berakhir,
 * dan formulir pembuatan pengajuan (resign/PHK/akhir kontrak/pensiun).
 * @var array $requests, $paginator, $filters, $stats, $expiring, $units, $departments
 * @var array $employees, $types, $typeTones, $statuses, $tones, $reasons
 * @var callable $pageLink
 * @var int $noticeDays
 */
$prefill = (int) ($_GET['employee'] ?? 0);
$badge = static fn (string $status): string => '<span class="badge badge-' . ($tones[$status] ?? 'slate') . '">'
    . e($statuses[$status] ?? $status) . '</span>';
$typeBadge = static fn (string $type): string => '<span class="badge badge-' . ($typeTones[$type] ?? 'slate') . '">'
    . e($types[$type] ?? $type) . '</span>';
$exportQuery = http_build_query(array_filter([
    'status'     => $filters['status'] !== 'OPEN' ? $filters['status'] : null,
    'type'       => $filters['type'] ?: null,
    'unit'       => $filters['unit'] ?: null,
    'department' => $filters['department'] ?: null,
    'q'          => $filters['q'] ?: null,
], static fn ($v) => $v !== null && $v !== ''));
$daysLeft = static fn (string $date): int => (int) floor((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
?>
<div class="page-head">
    <div>
        <h1>Offboarding &amp; Exit</h1>
        <div class="sub">Kelola pengunduran diri, PHK, akhir kontrak, dan serah terima karyawan keluar.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('offboarding/export' . ($exportQuery ? '?' . $exportQuery : '')) ?>"><?= icon('download') ?> Export CSV</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Menunggu Atasan</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($stats['submitted']) ?></span>
        <span class="s">belum disetujui atasan</span>
    </div>
    <div class="mini-stat">
        <span class="k">Menunggu HR</span>
        <span class="v" style="color:var(--indigo-600)"><?= number_id($stats['waiting_hr']) ?></span>
        <span class="s">siap diproses HR</span>
    </div>
    <div class="mini-stat">
        <span class="k">Serah Terima</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id($stats['in_progress']) ?></span>
        <span class="s">checklist berjalan</span>
    </div>
    <div class="mini-stat">
        <span class="k">Selesai Bulan Ini</span>
        <span class="v"><?= number_id($stats['completed_month']) ?></span>
        <span class="s">total <?= number_id($stats['completed']) ?> offboarding selesai</span>
    </div>
    <div class="mini-stat">
        <span class="k">Keluar 30 Hari</span>
        <span class="v"><?= number_id($stats['exit_30']) ?></span>
        <span class="s"><?= number_id($stats['exit_30_resign']) ?> pengunduran diri</span>
    </div>
    <div class="mini-stat">
        <span class="k">Rata-rata Notice</span>
        <span class="v"><?= number_id($stats['avg_notice']) ?> <span class="muted small" style="font-size:11px;font-weight:600">hari</span></span>
        <span class="s">standar perusahaan <?= number_id($noticeDays) ?> hari</span>
    </div>
</div>

<div class="split-grid wide-left">
    <div class="flex-col gap-12">
        <section class="card">
            <div class="card__head">
                <div><h2>Daftar Offboarding</h2>
                    <div class="sub"><?= number_id($paginator->total) ?> pengajuan sesuai filter, diurutkan berdasarkan hari terakhir kerja.</div></div>
            </div>

            <form class="filter-bar" method="get" action="<?= url('offboarding') ?>">
                <select class="select" name="status" data-auto-submit style="min-width:170px">
                    <option value="OPEN" <?= $filters['status'] === 'OPEN' ? 'selected' : '' ?>>Semua yang berjalan</option>
                    <option value="" <?= $filters['status'] === '' ? 'selected' : '' ?>>Semua status</option>
                    <?php foreach ($statuses as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="select" name="type" data-auto-submit style="min-width:150px">
                    <option value="">Semua jenis</option>
                    <?php foreach ($types as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="select" name="unit" data-auto-submit style="min-width:135px">
                    <option value="">Semua unit</option>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= (int) $unit['id'] ?>" <?= (int) $filters['unit'] === (int) $unit['id'] ? 'selected' : '' ?>><?= e($unit['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="select" name="department" data-auto-submit style="min-width:145px">
                    <option value="">Semua departemen</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= (int) $department['id'] ?>" <?= (int) $filters['department'] === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="input-group" style="max-width:190px">
                    <?= icon('search') ?>
                    <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="No. / nama karyawan…">
                </div>
                <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
                <?php if ($exportQuery): ?>
                    <a class="btn btn-sm btn-ghost" href="<?= url('offboarding') ?>"><?= icon('x') ?> Reset</a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
                <table class="data">
                    <thead>
                    <tr>
                        <th>No. Pengajuan</th><th>Karyawan</th><th>Jenis</th>
                        <th>Hari Terakhir</th><th>Notice</th><th>Serah Terima</th><th>Status</th><th style="width:60px"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($requests as $row): ?>
                        <?php
                        $left = $daysLeft((string) $row['last_working_date']);
                        $total = (int) $row['clearance_total'];
                        $done = (int) $row['clearance_done'];
                        $pct = $total > 0 ? (int) round($done / $total * 100) : 0;
                        ?>
                        <tr>
                            <td>
                                <a href="<?= url('offboarding/' . (int) $row['id']) ?>"><b class="mono" style="font-size:10.4px"><?= e($row['request_number']) ?></b></a>
                                <div class="muted small" style="font-size:10px"><?= tanggal($row['submit_date']) ?></div>
                            </td>
                            <td class="small">
                                <b><?= e($row['full_name']) ?></b>
                                <div class="muted mono" style="font-size:9.6px"><?= e($row['employee_code']) ?></div>
                                <div class="muted" style="font-size:9.8px"><?= e($row['unit_name'] ?? '-') ?> · <?= e($row['department_name'] ?? '-') ?></div>
                            </td>
                            <td><?= $typeBadge((string) $row['type']) ?></td>
                            <td class="small">
                                <b><?= tanggal($row['last_working_date']) ?></b>
                                <?php if (in_array($row['status'], ['SUBMITTED', 'MANAGER_APPROVED', 'IN_PROGRESS'], true)): ?>
                                    <div class="muted" style="font-size:9.8px;color:<?= $left <= 7 ? 'var(--rose-600)' : 'inherit' ?>">
                                        <?= $left >= 0 ? $left . ' hari lagi' : 'sudah lewat' ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="small num"><?= number_id((int) $row['notice_days']) ?> hari</td>
                            <td class="small" style="min-width:96px">
                                <?php if ($total > 0): ?>
                                    <div class="progress thin"><span style="width:<?= $pct ?>%"></span></div>
                                    <div class="muted" style="font-size:9.6px;margin-top:3px"><?= $done ?>/<?= $total ?> item</div>
                                <?php else: ?>
                                    <span class="muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $badge((string) $row['status']) ?></td>
                            <td><a class="btn btn-sm btn-ghost" href="<?= url('offboarding/' . (int) $row['id']) ?>" title="Detail"><?= icon('arrow-right') ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$requests): ?>
                        <tr><td colspan="8">
                            <div class="empty-state">
                                <div class="empty-icon"><?= icon('logout') ?></div>
                                <h3>Tidak ada pengajuan pada filter ini</h3>
                                <p>Longgarkan filter status atau mulai offboarding baru dari panel kanan.</p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card__foot flex-between wrap" style="padding:10px 16px">
                <span class="small muted">Menampilkan <?= number_id($paginator->from()) ?>–<?= number_id($paginator->to()) ?> dari <?= number_id($paginator->total) ?> pengajuan</span>
                <?php if ($paginator->lastPage() > 1): ?>
                    <div class="pagination" style="padding:0">
                        <?php if ($paginator->hasPrevious()): ?><a href="<?= e(($pageLink)($paginator->page - 1)) ?>"><?= icon('chevron-left') ?></a><?php endif; ?>
                        <?php foreach ($paginator->window(5) as $page): ?>
                            <?php if ($page === $paginator->page): ?>
                                <span class="active"><?= $page ?></span>
                            <?php else: ?>
                                <a href="<?= e(($pageLink)($page)) ?>"><?= $page ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($paginator->hasNext()): ?><a href="<?= e(($pageLink)($paginator->page + 1)) ?>"><?= icon('chevron-right') ?></a><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <div><h2>Kontrak Berakhir ≤ 90 Hari</h2>
                    <div class="sub">Karyawan aktif yang kontraknya segera habis — mulai proses lebih awal.</div></div>
            </div>
            <div class="card__body" style="padding:0">
                <?php foreach ($expiring as $emp): ?>
                    <?php $days = $daysLeft((string) $emp['contract_end']); ?>
                    <div class="list-row" style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--line)">
                        <div class="avatar" style="width:32px;height:32px;font-size:11px;background:<?= avatar_color($emp['full_name']) ?>"><?= e(initials($emp['full_name'])) ?></div>
                        <div style="flex:1;min-width:0">
                            <b style="font-size:12.4px"><?= e($emp['full_name']) ?></b>
                            <div class="muted" style="font-size:10px"><?= e($emp['employee_code']) ?> · <?= e($emp['department_name'] ?? '-') ?></div>
                        </div>
                        <div class="small" style="text-align:right;min-width:90px">
                            <b><?= tanggal($emp['contract_end']) ?></b>
                            <div style="font-size:9.8px;color:<?= $days <= 30 ? 'var(--rose-600)' : 'var(--muted)' ?>"><?= $days ?> hari lagi</div>
                        </div>
                        <?php if (!empty($emp['request_id'])): ?>
                            <a class="btn btn-sm btn-outline" href="<?= url('offboarding/' . (int) $emp['request_id']) ?>">Lihat</a>
                        <?php else: ?>
                            <a class="btn btn-sm btn-ghost" href="<?= url('offboarding?employee=' . (int) $emp['id']) ?>"><?= icon('plus') ?> Proses</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$expiring): ?>
                    <div class="empty-state" style="padding:22px">
                        <div class="empty-icon"><?= icon('calendar-check') ?></div>
                        <h3>Tidak ada kontrak yang segera berakhir</h3>
                        <p>Semua kontrak karyawan aktif masih berjalan lebih dari 90 hari.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="flex-col gap-12">
        <section class="card">
            <div class="card__head">
                <div><h2>Mulai Offboarding</h2>
                    <div class="sub">Untuk resign atas permintaan karyawan, PHK, atau akhir kontrak.</div></div>
            </div>
            <form method="post" action="<?= url('offboarding') ?>">
                <?= csrf_field() ?>
                <div class="card__body flex-col gap-12">
                    <div class="field">
                        <label>Karyawan <span class="req">*</span></label>
                        <select class="select" name="employee_id" required>
                            <option value="">Pilih karyawan…</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= (int) $emp['id'] ?>" <?= (int) ($old['employee_id'] ?? $prefill) === (int) $emp['id'] ? 'selected' : '' ?>>
                                    <?= e($emp['full_name']) ?> — <?= e($emp['employee_code']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grid two">
                        <div class="field">
                            <label>Jenis <span class="req">*</span></label>
                            <select class="select" name="type" required>
                                <?php foreach ($types as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= ($old['type'] ?? 'RESIGN') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Alasan</label>
                            <select class="select" name="reason_category">
                                <option value="">—</option>
                                <?php foreach ($reasons as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= ($old['reason_category'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Tanggal pengajuan</label>
                            <input class="input" type="date" name="submit_date" value="<?= e($old['submit_date'] ?? date('Y-m-d')) ?>">
                        </div>
                        <div class="field">
                            <label>Hari terakhir kerja <span class="req">*</span></label>
                            <input class="input" type="date" name="last_working_date" required value="<?= e($old['last_working_date'] ?? date('Y-m-d', strtotime('+' . $noticeDays . ' days'))) ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label>Penerima serah terima</label>
                        <select class="select" name="handover_to">
                            <option value="0">— Belum ditentukan —</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= (int) $emp['id'] ?>" <?= (int) ($old['handover_to'] ?? 0) === (int) $emp['id'] ? 'selected' : '' ?>><?= e($emp['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Catatan</label>
                        <textarea class="textarea" name="reason" rows="2" maxlength="1000" placeholder="Kronologi singkat atau kesepakatan dengan karyawan."><?= e($old['reason'] ?? '') ?></textarea>
                    </div>
                    <div class="alert alert-info" style="margin:0">
                        <?= icon('info') ?>
                        <div class="small">Pengajuan dari HR langsung masuk tahap <b>Menunggu HR</b> tanpa perlu persetujuan atasan.</div>
                    </div>
                </div>
                <div class="card__foot" style="padding:12px 16px;display:flex;justify-content:flex-end">
                    <button class="btn btn-primary" type="submit"><?= icon('plus') ?> Buat Pengajuan</button>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Alur Offboarding</h2></div></div>
            <div class="card__body flex-col gap-8 small muted">
                <div><b>1. Pengajuan</b> — karyawan mengajukan resign atau HR membuat pengajuan (PHK/akhir kontrak).</div>
                <div><b>2. Persetujuan atasan</b> — atasan langsung meninjau alasan &amp; tanggal keluar.</div>
                <div><b>3. Persetujuan HR</b> — checklist serah terima dibuat otomatis.</div>
                <div><b>4. Serah terima</b> — aset, dokumen, akses sistem, dan penyelesaian keuangan.</div>
                <div><b>5. Penutupan</b> — exit interview, penyelesaian akhir, status karyawan menjadi <b>RESIGN</b>.</div>
            </div>
        </section>
    </div>
</div>
