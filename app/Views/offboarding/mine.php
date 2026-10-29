<?php
/**
 * Portal karyawan: ajukan pengunduran diri, pantau serah terima, isi umpan
 * balik keluar, dan setujui pengajuan tim (bila atasan langsung).
 * @var array $employee, $profile, $requests, $clearance, $progress, $teamRequests
 * @var array $types, $reasons, $statuses, $tones, $typeTones, $recommendations, $ownerRoles, $colleagues
 * @var bool $isManager
 * @var int $noticeDays
 */
$active = $active ?? null;
$badge = static fn (string $status): string => '<span class="badge badge-' . ($tones[$status] ?? 'slate') . '">'
    . e($statuses[$status] ?? $status) . '</span>';
$typeBadge = static fn (string $type): string => '<span class="badge badge-' . ($typeTones[$type] ?? 'slate') . '">'
    . e($types[$type] ?? $type) . '</span>';
$isClosedActive = $active !== null && in_array($active['status'], ['COMPLETED', 'REJECTED', 'WITHDRAWN', 'CANCELLED'], true);
$daysLeft = $active ? (int) floor((strtotime((string) $active['last_working_date']) - strtotime(date('Y-m-d'))) / 86400) : 0;
$nextChecklist = null;
foreach ($clearance as $item) {
    if ($item['status'] === 'PENDING') {
        $nextChecklist = $item;
        break;
    }
}
?>
<div class="page-head">
    <div>
        <h1>Pengunduran Diri Saya</h1>
        <div class="sub">Ajukan pengunduran diri, pantau proses serah terima, dan sampaikan umpan balik keluar.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('me/profile') ?>"><?= icon('user') ?> Profil Saya</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Status Pengajuan</span>
        <span class="v" style="font-size:14px"><?= $active ? e($statuses[$active['status']] ?? $active['status']) : 'Belum ada' ?></span>
        <span class="s"><?= $active ? e($active['request_number']) : 'Karyawan aktif' ?></span>
    </div>
    <div class="mini-stat">
        <span class="k">Hari Terakhir Kerja</span>
        <span class="v" style="font-size:14px"><?= $active ? tanggal($active['last_working_date']) : '—' ?></span>
        <span class="s"><?= $active && !$isClosedActive ? ($daysLeft >= 0 ? $daysLeft . ' hari lagi' : 'sudah lewat') : 'belum dijadwalkan' ?></span>
    </div>
    <div class="mini-stat">
        <span class="k">Serah Terima</span>
        <span class="v"><?= (int) $progress['done'] ?>/<?= (int) $progress['total'] ?></span>
        <span class="s"><?= (int) $progress['pct'] ?>% selesai</span>
    </div>
    <div class="mini-stat">
        <span class="k">Masa Pemberitahuan</span>
        <span class="v"><?= number_id($active ? (int) $active['notice_days'] : $noticeDays) ?> <span class="muted" style="font-size:11px;font-weight:600">hari</span></span>
        <span class="s">standar <?= number_id($noticeDays) ?> hari</span>
    </div>
</div>

<?php if ($active): ?>
    <section class="card" style="margin-bottom:14px">
        <div class="card__head">
            <div>
                <h2><?= e($active['request_number']) ?> <?= $typeBadge((string) $active['type']) ?> <?= $badge((string) $active['status']) ?></h2>
                <div class="sub">Diajukan <?= tanggal($active['submit_date'] ?? $active['created_at'], 'd M Y') ?> ·
                    hari terakhir kerja <b><?= tanggal($active['last_working_date']) ?></b></div>
            </div>
            <div class="card__head__actions">
                <a class="btn btn-sm btn-outline" href="<?= url('offboarding/' . (int) $active['id']) ?>"><?= icon('eye') ?> Detail Proses</a>
                <?php if (in_array($active['status'], ['SUBMITTED', 'MANAGER_APPROVED', 'IN_PROGRESS'], true)): ?>
                    <form method="post" action="<?= url('me/offboarding/' . (int) $active['id'] . '/withdraw') ?>"
                          data-confirm="Batalkan pengajuan pengunduran diri Anda?">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)"><?= icon('x') ?> Batalkan</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="card__body">
            <div class="progress"><span style="width:<?= (int) $progress['pct'] ?>%"></span></div>
            <div class="muted small" style="font-size:10.6px;margin-top:5px">
                <?= (int) $progress['done'] ?> dari <?= (int) $progress['total'] ?> item serah terima selesai.
                <?php if ($nextChecklist): ?>
                    Berikutnya: <b><?= e($nextChecklist['item']) ?></b> (<?= e($nextChecklist['owner_role']) ?>).
                <?php elseif ((int) $progress['total'] > 0): ?>
                    Semua item sudah tuntas — menunggu HR menutup proses.
                <?php endif; ?>
            </div>
        </div>

        <?php if ($active['status'] === 'REJECTED' && !empty($active['reject_reason'])): ?>
            <div class="card__body" style="border-top:1px solid var(--line)">
                <div class="alert alert-error" style="margin:0"><?= icon('x') ?><div>Pengajuan ditolak: <?= e($active['reject_reason']) ?></div></div>
            </div>
        <?php endif; ?>

        <?php if (!$isClosedActive): ?>
            <form method="post" action="<?= url('offboarding/' . (int) $active['id'] . '/feedback') ?>" style="border-top:1px solid var(--line)">
                <?= csrf_field() ?>
                <div class="card__body">
                    <div class="muted small" style="font-size:9.8px;text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin-bottom:8px">Umpan balik keluar (opsional)</div>
                    <div class="form-grid two">
                        <div class="field" style="grid-column:1/-1">
                            <textarea class="textarea" name="exit_feedback" rows="2" maxlength="1500"
                                      placeholder="Apa yang sudah baik dan apa yang bisa diperbaiki?"><?= e($active['exit_feedback'] ?? '') ?></textarea>
                        </div>
                        <div class="field" style="max-width:280px">
                            <label>Bersedia merekomendasikan perusahaan?</label>
                            <select class="select" name="exit_would_recommend">
                                <option value="">— Pilih —</option>
                                <?php foreach ($recommendations as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $active['exit_would_recommend'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field" style="justify-content:flex-end;display:flex">
                            <button class="btn btn-sm btn-outline" type="submit"><?= icon('send') ?> Kirim Umpan Balik</button>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<div class="split-grid wide-left">
    <div class="flex-col gap-12">
        <?php if (!$active): ?>
            <section class="card">
                <div class="card__head">
                    <div><h2>Ajukan Pengunduran Diri</h2>
                        <div class="sub">Pemberitahuan minimal <?= number_id($noticeDays) ?> hari sebelum hari terakhir kerja.</div></div>
                </div>
                <form method="post" action="<?= url('me/offboarding') ?>" data-confirm="Kirim pengajuan pengunduran diri?">
                    <?= csrf_field() ?>
                    <div class="card__body">
                        <div class="form-grid two">
                            <div class="field">
                                <label>Hari terakhir kerja <span class="req">*</span></label>
                                <input class="input" type="date" name="last_working_date" required
                                       min="<?= date('Y-m-d') ?>"
                                       value="<?= e($old['last_working_date'] ?? date('Y-m-d', strtotime('+' . $noticeDays . ' days'))) ?>">
                                <div class="hint">Tanggal ini akan dipakai sebagai tanggal resign di data kepegawaian.</div>
                            </div>
                            <div class="field">
                                <label>Alasan utama <span class="req">*</span></label>
                                <select class="select" name="reason_category" required>
                                    <option value="">Pilih alasan…</option>
                                    <?php foreach ($reasons as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= ($old['reason_category'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field" style="grid-column:1/-1">
                                <label>Penerima serah terima pekerjaan</label>
                                <select class="select" name="handover_to">
                                    <option value="0">— Belum ditentukan —</option>
                                    <?php foreach ($colleagues as $colleague): ?>
                                        <?php if ((int) $colleague['id'] === (int) $employee['id']) { continue; } ?>
                                        <option value="<?= (int) $colleague['id'] ?>" <?= (int) ($old['handover_to'] ?? 0) === (int) $colleague['id'] ? 'selected' : '' ?>>
                                            <?= e($colleague['full_name']) ?> — <?= e($colleague['employee_code']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field" style="grid-column:1/-1">
                                <label>Catatan / alasan lengkap</label>
                                <textarea class="textarea" name="reason" rows="3" maxlength="1000"
                                          placeholder="Jelaskan singkat alasan pengunduran diri dan rencana serah terima."><?= e($old['reason'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="alert alert-info" style="margin-top:12px">
                            <?= icon('info') ?>
                            <div class="small">
                                Pengajuan diteruskan ke atasan langsung
                                (<b><?= e($profile['manager_name'] ?? 'HR') ?></b>) lalu HR. Anda dapat membatalkan
                                pengajuan selama proses belum ditutup.
                            </div>
                        </div>
                    </div>
                    <div class="card__foot" style="padding:12px 16px;display:flex;justify-content:flex-end">
                        <button class="btn btn-primary" type="submit"><?= icon('send') ?> Kirim Pengajuan</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($isManager && $teamRequests): ?>
            <section class="card">
                <div class="card__head">
                    <div><h2>Persetujuan Tim</h2>
                        <div class="sub"><?= count($teamRequests) ?> pengajuan anggota tim menunggu keputusan Anda.</div></div>
                </div>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Karyawan</th><th>Jenis</th><th>Hari Terakhir</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($teamRequests as $row): ?>
                            <tr>
                                <td class="small">
                                    <b><?= e($row['full_name']) ?></b>
                                    <div class="muted mono" style="font-size:9.6px"><?= e($row['employee_code']) ?></div>
                                </td>
                                <td><?= $typeBadge((string) $row['type']) ?></td>
                                <td class="small"><?= tanggal($row['last_working_date']) ?></td>
                                <td><?= $badge((string) $row['status']) ?></td>
                                <td><a class="btn btn-sm btn-outline" href="<?= url('offboarding/' . (int) $row['id']) ?>">Tinjau</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <section class="card">
            <div class="card__head"><div><h2>Riwayat Pengajuan</h2>
                <div class="sub">Seluruh pengajuan offboarding atas nama Anda.</div></div></div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>No. Pengajuan</th><th>Jenis</th><th>Diajukan</th><th>Hari Terakhir</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($requests as $row): ?>
                        <tr>
                            <td><b class="mono" style="font-size:10.4px"><?= e($row['request_number']) ?></b></td>
                            <td><?= $typeBadge((string) $row['type']) ?></td>
                            <td class="small"><?= tanggal($row['submit_date'] ?? $row['created_at']) ?></td>
                            <td class="small"><?= tanggal($row['last_working_date']) ?></td>
                            <td><?= $badge((string) $row['status']) ?></td>
                            <td><a class="btn btn-sm btn-ghost" href="<?= url('offboarding/' . (int) $row['id']) ?>"><?= icon('arrow-right') ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$requests): ?>
                        <tr><td colspan="6">
                            <div class="empty-state">
                                <div class="empty-icon"><?= icon('file') ?></div>
                                <h3>Belum ada pengajuan</h3>
                                <p>Pengajuan pengunduran diri akan tampil di sini beserta status persetujuannya.</p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="flex-col gap-12">
        <section class="card">
            <div class="card__head"><div><h2>Alur Pengunduran Diri</h2></div></div>
            <div class="card__body flex-col gap-8 small muted">
                <div><b style="color:var(--navy-900)">1. Ajukan</b> — isi tanggal keluar dan alasan.</div>
                <div><b style="color:var(--navy-900)">2. Atasan</b> — atasan langsung menyetujui/menolak.</div>
                <div><b style="color:var(--navy-900)">3. HR</b> — verifikasi &amp; checklist serah terima dibuat.</div>
                <div><b style="color:var(--navy-900)">4. Serah terima</b> — aset, dokumen, akses, dan keuangan.</div>
                <div><b style="color:var(--navy-900)">5. Selesai</b> — surat pengalaman kerja diterbitkan HR.</div>
            </div>
        </section>

        <?php if ($clearance): ?>
            <section class="card">
                <div class="card__head"><div><h2>Checklist Serah Terima</h2></div></div>
                <div class="card__body" style="padding:0">
                    <?php foreach ($clearance as $item): ?>
                        <div class="list-row" style="align-items:flex-start">
                            <div style="margin-top:1px;color:<?= $item['status'] === 'PENDING' ? 'var(--amber-600)' : 'var(--teal-700)' ?>">
                                <?= $item['status'] === 'PENDING' ? icon('clock') : icon('check') ?>
                            </div>
                            <div class="list-row__body">
                                <b><?= e($item['item']) ?></b>
                                <span><?= e($item['status'] === 'PENDING' ? 'Menunggu ' . ($ownerRoles[$item['owner_role']] ?? $item['owner_role']) : 'Selesai') ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="card">
            <div class="card__head"><div><h2>Data Kepegawaian</h2></div></div>
            <div class="card__body">
                <div class="detail-list">
                    <div class="detail-list__row"><span>NIK</span><b class="mono"><?= e($profile['employee_code']) ?></b></div>
                    <div class="detail-list__row"><span>Jabatan</span><b><?= e($profile['position_name'] ?? '—') ?></b></div>
                    <div class="detail-list__row"><span>Unit</span><b><?= e($profile['unit_name'] ?? '—') ?></b></div>
                    <div class="detail-list__row"><span>Bergabung</span><b><?= tanggal($profile['join_date'] ?? null) ?></b></div>
                    <div class="detail-list__row"><span>Status kontrak</span><b><?= e($profile['employment_status'] ?? '—') ?></b></div>
                    <div class="detail-list__row"><span>Akhir kontrak</span><b><?= !empty($profile['contract_end']) ? tanggal($profile['contract_end']) : '—' ?></b></div>
                </div>
            </div>
        </section>
    </div>
</div>
