<?php
/**
 * Employee detail.
 * @var array $employee, $attendance, $attendanceStats, $leaveBalances, $activity, $contract
 * @var array $offboardingRequests
 * @var array|null $payslip, $offboardingActive
 */
$join = $employee['join_date'] ?? null;
$tenure = '-';
if ($join) {
    $diff = (new DateTimeImmutable($join))->diff(new DateTimeImmutable('today'));
    $tenure = $diff->y > 0 ? $diff->y . ' th ' . $diff->m . ' bl' : $diff->m . ' bulan';
}
$statusTone = ['soon' => 'amber-600', 'expired' => 'rose-600', 'safe' => 'teal-700', 'none' => 'muted'];
$contractColor = $statusTone[$contract['state']] ?? 'muted';
$isActive = ($employee['flag'] ?? '') === 'ACTIVE';
?>
<div class="page-head">
    <div>
        <div class="flex" style="gap:8px;align-items:center;flex-wrap:wrap">
            <h1><?= e($employee['full_name']) ?></h1>
            <?= role_badge($employee['role']) ?>
            <?= flag_badge($employee['flag']) ?>
        </div>
        <div class="sub">
            <span class="mono"><?= e($employee['employee_code']) ?></span>
            <span class="dot" style="background:var(--line-strong)"></span>
            <?= e($employee['position_name'] ?? 'Tanpa posisi') ?>
            <span class="dot" style="background:var(--line-strong)"></span>
            <?= e($employee['unit_name'] ?? 'Tanpa unit') ?>
        </div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('employees') ?>"><?= icon('arrow-left') ?> Direktori</a>
        <a class="btn btn-primary" href="<?= url('employees/' . $employee['id'] . '/edit') ?>"><?= icon('edit') ?> Ubah Data</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Masa Kerja</span>
        <span class="v"><?= e($tenure) ?></span>
        <span class="s">Bergabung <?= $join ? tanggal($join) : '—' ?></span>
    </div>
    <div class="mini-stat">
        <span class="k">Status Kontrak</span>
        <span class="v" style="font-size:14px;color:var(--<?= e($contractColor) ?>)"><?= e($contract['state'] === 'none' ? 'Tanpa batas' : ($contract['days'] < 0 ? 'Kedaluwarsa' : $contract['days'] . ' hari')) ?></span>
        <span class="s"><?= e($contract['label']) ?></span>
    </div>
    <div class="mini-stat">
        <span class="k">Presensi Bulan Ini</span>
        <span class="v"><?= number_id($attendanceStats['days'] ?? 0) ?> <span style="font-size:11px;color:var(--muted)">hari</span></span>
        <span class="s"><?= number_id($attendanceStats['late_days'] ?? 0) ?> terlambat · <?= number_id($attendanceStats['absent_days'] ?? 0) ?> alpha</span>
    </div>
    <div class="mini-stat">
        <span class="k">Gaji Pokok</span>
        <span class="v" style="font-size:15px"><?= rupiah($employee['basic_salary']) ?></span>
        <span class="s"><?= e($employee['employment_status']) ?></span>
    </div>
</div>

<div class="split-grid wide-left">
    <div class="flex-col gap-16">

        <section class="card">
            <div class="card__head"><div><h2>Data Kepegawaian</h2>
                <div class="sub">Penempatan, atribusi, dan informasi pribadi.</div></div></div>
            <div class="card__body">
                <div class="form-grid">
                    <div class="detail-list">
                        <div class="detail-list__row"><span class="k">Nama panggilan</span><span class="v"><?= e($employee['nickname'] ?: '—') ?></span></div>
                        <div class="detail-list__row"><span class="k">Email</span><span class="v"><?= e($employee['email']) ?></span></div>
                        <div class="detail-list__row"><span class="k">Nomor HP</span><span class="v"><?= e($employee['phone'] ?: '—') ?></span></div>
                        <div class="detail-list__row"><span class="k">Tanggal lahir</span><span class="v"><?= $employee['birth_date'] ? tanggal($employee['birth_date']) : '—' ?></span></div>
                        <div class="detail-list__row"><span class="k">Jenis kelamin</span><span class="v"><?= e($employee['gender'] ?: '—') ?></span></div>
                        <div class="detail-list__row"><span class="k">Status pernikahan</span><span class="v"><?= e($employee['marital_status'] ?: '—') ?></span></div>
                        <div class="detail-list__row"><span class="k">Agama</span><span class="v"><?= e($employee['religion'] ?: '—') ?></span></div>
                    </div>
                    <div class="detail-list">
                        <div class="detail-list__row"><span class="k">Business unit</span><span class="v"><?= e($employee['unit_name'] ?? '—') ?></span></div>
                        <div class="detail-list__row"><span class="k">Departemen</span><span class="v"><?= e($employee['department_name'] ?? '—') ?></span></div>
                        <div class="detail-list__row"><span class="k">Posisi</span><span class="v"><?= e($employee['position_name'] ?? '—') ?></span></div>
                        <div class="detail-list__row"><span class="k">Atasan langsung</span><span class="v"><?= e($employee['manager_name'] ?? '—') ?></span></div>
                        <div class="detail-list__row"><span class="k">Status pajak</span><span class="v"><?= e($employee['tax_status']) ?></span></div>
                        <div class="detail-list__row"><span class="k">Alamat</span><span class="v" style="font-weight:500"><?= e($employee['address'] ?: '—') ?></span></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <div><h2>Presensi Terakhir</h2>
                    <div class="sub">Enam catatan presensi terbaru karyawan ini.</div></div>
                <a class="btn btn-sm btn-outline" href="<?= url('attendance/rekap') ?>">Rekap Bulanan</a>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Tanggal</th><th>Masuk</th><th>Keluar</th><th class="num">Menit Kerja</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($attendance as $log): ?>
                        <tr>
                            <td class="small"><?= tanggal($log['work_date']) ?></td>
                            <td class="mono small"><?= $log['check_in_at'] ? date('H:i', strtotime((string) $log['check_in_at'])) : '—' ?></td>
                            <td class="mono small"><?= $log['check_out_at'] ? date('H:i', strtotime((string) $log['check_out_at'])) : '—' ?></td>
                            <td class="num"><?= number_id($log['work_minutes']) ?></td>
                            <td><?= status_badge($log['status']) ?><?= (int) $log['late_minutes'] > 0 ? ' <span class="tag-soft">+' . (int) $log['late_minutes'] . ' mnt</span>' : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$attendance): ?>
                        <tr><td colspan="5" class="table-empty">Belum ada data presensi.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="split-grid" style="grid-template-columns:1fr 1fr;gap:16px">
            <section class="card">
                <div class="card__head"><div><h2>Kontrak &amp; Gaji</h2></div></div>
                <div class="card__body">
                    <div class="detail-list">
                        <div class="detail-list__row"><span class="k">Status</span><span class="v"><?= e($employee['employment_status']) ?></span></div>
                        <div class="detail-list__row"><span class="k">Mulai kontrak</span><span class="v"><?= $employee['contract_start'] ? tanggal($employee['contract_start']) : '—' ?></span></div>
                        <div class="detail-list__row"><span class="k">Akhir kontrak</span><span class="v"><?= $employee['contract_end'] ? tanggal($employee['contract_end']) : 'Tanpa batas' ?></span></div>
                        <div class="detail-list__row"><span class="k">Masa berlaku</span>
                            <span class="v"><span class="contract-pill <?= e($contract['state']) ?>"><?= icon($contract['state'] === 'expired' ? 'alert' : 'clock', 'ico') ?> <?= e($contract['label']) ?></span></span>
                        </div>
                        <div class="detail-list__row"><span class="k">Gaji pokok</span><span class="v"><?= rupiah($employee['basic_salary']) ?></span></div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><div><h2>Bank &amp; Jaminan</h2></div></div>
                <div class="card__body">
                    <div class="detail-list">
                        <div class="detail-list__row"><span class="k">Bank</span><span class="v"><?= e($employee['bank_name'] ?: '—') ?></span></div>
                        <div class="detail-list__row"><span class="k">Atas nama</span><span class="v"><?= e($employee['bank_account_name'] ?: '—') ?></span></div>
                        <div class="detail-list__row"><span class="k">No. rekening</span><span class="v mono"><?= e($employee['bank_account_number'] ?: '—') ?></span></div>
                        <div class="detail-list__row"><span class="k">NPWP</span><span class="v mono"><?= e($employee['npwp'] ?: '—') ?></span></div>
                        <div class="detail-list__row"><span class="k">BPJS Kesehatan</span><span class="v mono"><?= e($employee['bpjs_kesehatan_no'] ?: '—') ?></span></div>
                        <div class="detail-list__row"><span class="k">BPJS TK</span><span class="v mono"><?= e($employee['bpjs_tk_no'] ?: '—') ?></span></div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__body" style="text-align:center;padding-top:24px">
                <?php if (!empty($employee['photo'])): ?>
                    <span class="avatar xl" style="margin:0 auto 12px;display:block;overflow:hidden">
                        <img src="<?= url('media/employees/' . basename($employee['photo'])) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
                    </span>
                <?php else: ?>
                    <div class="avatar xl" style="margin:0 auto 12px;background:<?= e(avatar_color($employee['email'])) ?>"><?= e(initials($employee['full_name'])) ?></div>
                <?php endif; ?>
                <h2 style="font-size:16px"><?= e($employee['full_name']) ?></h2>
                <div class="small muted mono" style="margin-top:2px"><?= e($employee['employee_code']) ?></div>
                <div class="mt-12 flex" style="justify-content:center;gap:6px;flex-wrap:wrap">
                    <?= role_badge($employee['role']) ?>
                    <span class="badge badge-slate"><?= e($employee['employment_status']) ?></span>
                </div>
                <div class="divider"></div>
                <div class="flex-col gap-12" style="text-align:left">
                    <div class="flex gap-8 small"><span class="muted"><?= icon('mail', 'ico') ?></span> <?= e($employee['email']) ?></div>
                    <div class="flex gap-8 small"><span class="muted"><?= icon('phone', 'ico') ?></span> <?= e($employee['phone'] ?: '—') ?></div>
                    <div class="flex gap-8 small"><span class="muted"><?= icon('calendar', 'ico') ?></span> Bergabung <?= tanggal($employee['join_date']) ?></div>
                    <div class="flex gap-8 small"><span class="muted"><?= icon('clock', 'ico') ?></span> Login terakhir <?= $employee['last_login_at'] ? tanggal($employee['last_login_at'], 'd M Y H:i') : '—' ?></div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Slip Gaji Terakhir</h2></div></div>
            <?php if ($payslip): ?>
                <div class="card__body">
                    <div class="detail-list">
                        <div class="detail-list__row"><span class="k">No. slip</span><span class="v mono"><?= e($payslip['slip_number'] ?: '—') ?></span></div>
                        <div class="detail-list__row"><span class="k">Bruto</span><span class="v"><?= rupiah($payslip['gross_salary']) ?></span></div>
                        <div class="detail-list__row"><span class="k">Potongan</span><span class="v" style="color:var(--rose-600)">−<?= rupiah($payslip['total_deduction']) ?></span></div>
                        <div class="detail-list__row"><span class="k">Take home</span><span class="v" style="color:var(--teal-700);font-size:14px"><?= rupiah($payslip['net_salary']) ?></span></div>
                        <div class="detail-list__row"><span class="k">Status</span><span class="v"><?= status_badge($payslip['payment_status']) ?></span></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card__body small muted">Belum ada slip gaji yang diterbitkan.</div>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Saldo Cuti <?= date('Y') ?></h2></div></div>
            <div class="card__body">
                <?php if ($leaveBalances): ?>
                    <div class="flex-col gap-12">
                        <?php foreach ($leaveBalances as $balance): ?>
                            <?php
                            $quota = max(1, (float) $balance['quota'] + (float) ($balance['carry'] ?? 0));
                            $used = (float) $balance['used'];
                            $pct = min(100, (int) round($used / $quota * 100));
                            ?>
                            <div>
                                <div class="flex-between small" style="margin-bottom:4px">
                                    <span style="font-weight:600"><?= e($balance['type_name'] ?? 'Cuti') ?></span>
                                    <span class="muted"><?= number_id($used, 1) ?> / <?= number_id($quota, 0) ?> hari</span>
                                </div>
                                <div class="progress thin"><span style="width:<?= $pct ?>%;background:<?= $pct > 80 ? 'var(--rose-500)' : 'var(--teal-500)' ?>"></span></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="small muted">Belum ada kuota cuti tahun <?= date('Y') ?>.</div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <div><h2>Offboarding &amp; Exit</h2>
                    <div class="sub">Proses keluar terstruktur: persetujuan, serah terima, dan exit interview.</div></div>
                <?php if ($isActive && !$offboardingActive): ?>
                    <a class="btn btn-sm btn-outline" href="<?= url('offboarding?employee=' . (int) $employee['id']) ?>"><?= icon('plus') ?> Mulai Offboarding</a>
                <?php endif; ?>
            </div>
            <div class="card__body" style="padding:0">
                <?php if ($offboardingRequests): ?>
                    <?php foreach (array_slice($offboardingRequests, 0, 4) as $row): ?>
                        <?php
                        $tones = \App\Models\OffboardingModel::STATUS_TONES;
                        $labels = \App\Models\OffboardingModel::STATUSES;
                        $types = \App\Models\OffboardingModel::TYPES;
                        $isRunning = in_array($row['status'], \App\Models\OffboardingModel::ACTIVE_STATUSES, true);
                        ?>
                        <div class="list-row">
                            <div class="list-row__body">
                                <b><?= e($row['request_number']) ?> · <?= e($types[$row['type']] ?? $row['type']) ?></b>
                                <span>Hari terakhir kerja <?= tanggal($row['last_working_date']) ?>
                                    · diajukan <?= tanggal($row['submit_date'] ?? $row['created_at']) ?></span>
                            </div>
                            <span class="badge badge-<?= $tones[$row['status']] ?? 'slate' ?>"><?= e($labels[$row['status']] ?? $row['status']) ?></span>
                            <a class="btn btn-sm btn-ghost" href="<?= url('offboarding/' . (int) $row['id']) ?>"><?= icon('arrow-right') ?></a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="small muted" style="padding:14px 16px">
                        <?= $isActive
                            ? 'Belum ada proses offboarding. Gunakan tombol "Mulai Offboarding" agar persetujuan atasan, checklist serah terima, dan exit interview tercatat rapi.'
                            : 'Tidak ada riwayat offboarding tercatat untuk karyawan ini.' ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($isActive): ?>
            <details class="form-panel" style="margin-bottom:0">
                <summary>
                    <span class="form-panel__title" style="color:var(--rose-600)"><?= icon('logout', 'ico') ?> Nonaktifkan Karyawan</span>
                </summary>
                <form method="post" action="<?= url('employees/' . $employee['id'] . '/resign') ?>" class="form-panel__body"
                      data-confirm="Tandai <?= e($employee['full_name']) ?> sebagai resign? Riwayat tetap tersimpan.">
                    <?= csrf_field() ?>
                    <div class="flex-col gap-12">
                        <div class="field">
                            <label>Tanggal Resign</label>
                            <input class="input" type="date" name="resign_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="field">
                            <label>Alasan</label>
                            <input class="input" name="reason" placeholder="Mengundurkan diri, kontrak selesai, dll.">
                        </div>
                        <button class="btn btn-danger btn-block" type="submit"><?= icon('alert') ?> Tandai Resign</button>
                        <div class="hint">Data tidak dihapus — status berubah menjadi RESIGN dan akses login dinonaktifkan.</div>
                    </div>
                </form>
            </details>
        <?php endif; ?>

        <section class="card">
            <div class="card__head"><div><h2>Aktivitas Terakhir</h2></div></div>
            <div class="card__body" style="padding-top:4px">
                <?php if ($activity): ?>
                    <div class="timeline" style="margin-top:10px">
                        <?php foreach ($activity as $log): ?>
                            <div class="timeline__item">
                                <div class="small" style="font-weight:600"><?= e($log['action']) ?></div>
                                <div class="small muted"><?= tanggal($log['created_at'], 'd M Y H:i') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="small muted">Belum ada aktivitas tercatat.</div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
