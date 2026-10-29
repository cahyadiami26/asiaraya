<?php
/**
 * Detail offboarding: ringkasan pengajuan, checklist serah terima, exit
 * interview, penyelesaian akhir, dan riwayat tahapan.
 * @var array $request, $clearance, $assets, $assetStats, $bast, $stages, $progress
 * @var array $types, $typeTones, $statuses, $tones
 * @var array $assetCategories, $assetConditions, $assetStatuses, $assetTones
 * @var array $reasons, $categories, $clearanceStatuses, $clearanceTones, $ownerRoles
 * @var array $recommendations, $employees, $errors, $old
 * @var bool $isOwner, $isManager, $isHr, $isFin, $canApproveManager, $canApproveHr, $canComplete
 * @var bool $canReject, $canWithdraw, $canCancel, $canDelete
 */
$status = (string) $request['status'];
$isClosed = in_array($status, ['COMPLETED', 'REJECTED', 'WITHDRAWN', 'CANCELLED'], true);
$daysLeft = (int) floor((strtotime((string) $request['last_working_date']) - strtotime(date('Y-m-d'))) / 86400);
$steps = [
    'SUBMITTED'        => 'Pengajuan',
    'MANAGER_APPROVED' => 'Persetujuan Atasan',
    'IN_PROGRESS'      => 'Serah Terima',
    'COMPLETED'        => 'Selesai',
];
$stepIndex = array_search($status, array_keys($steps), true);
if ($status === 'MANAGER_APPROVED') {
    $stepIndex = 1;
}
$stepDone = static function (int $index) use ($stepIndex, $isClosed, $status): bool {
    if ($status === 'COMPLETED') {
        return true;
    }
    return $stepIndex !== false && $index < $stepIndex;
};
?>
<div class="page-head">
    <div>
        <h1><?= e($request['request_number']) ?></h1>
        <div class="sub">
            <?= e($request['full_name']) ?> · <?= e($request['employee_code']) ?> ·
            <?= e($types[$request['type']] ?? $request['type']) ?>
        </div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= $isHr ? url('offboarding') : url('me/offboarding') ?>"><?= icon('arrow-left') ?> <?= $isHr ? 'Daftar Offboarding' : 'Pengunduran Diri Saya' ?></a>
        <span class="badge badge-<?= $tones[$status] ?? 'slate' ?>"><?= e($statuses[$status] ?? $status) ?></span>
        <span class="badge badge-<?= $typeTones[$request['type']] ?? 'slate' ?>"><?= e($types[$request['type']] ?? $request['type']) ?></span>
    </div>
</div>

<?php if ($status === 'COMPLETED'): ?>
    <div class="alert alert-success" style="margin-bottom:14px">
        <?= icon('check') ?>
        <div>
            <b>Offboarding selesai<?= $request['completed_at'] ? ' pada ' . tanggal($request['completed_at'], 'd M Y H:i') : '' ?>.</b>
            Karyawan telah dinonaktifkan per <b><?= tanggal($request['last_working_date']) ?></b>.
            <?php if ((float) $request['settlement_amount'] > 0): ?>
                Penyelesaian akhir: <b><?= rupiah($request['settlement_amount']) ?></b>.
            <?php endif; ?>
        </div>
        <a class="btn btn-sm btn-outline" href="<?= url('employees/' . (int) $request['employee_id']) ?>" style="margin-left:auto"><?= icon('user') ?> Data Karyawan</a>
    </div>
<?php elseif ($status === 'REJECTED'): ?>
    <div class="alert alert-error" style="margin-bottom:14px">
        <?= icon('x') ?>
        <div><b>Pengajuan ditolak.</b> <?= e($request['reject_reason'] ?? '') ?></div>
    </div>
<?php elseif ($status === 'WITHDRAWN' || $status === 'CANCELLED'): ?>
    <div class="alert alert-warning" style="margin-bottom:14px">
        <?= icon('info') ?>
        <div>
            <b><?= $status === 'WITHDRAWN' ? 'Pengajuan dibatalkan oleh karyawan.' : 'Pengajuan dibatalkan HR.' ?></b>
            Karyawan tetap berstatus aktif.
        </div>
    </div>
<?php endif; ?>

<section class="card" style="margin-bottom:14px">
    <div class="card__body" style="padding:16px">
        <div class="flow-steps">
            <?php $i = 0; foreach ($steps as $key => $label): ?>
                <div class="flow-step <?= $stepDone($i) ? 'is-done' : '' ?> <?= $status === $key ? 'is-current' : '' ?>">
                    <span class="flow-step__dot"><?= $stepDone($i) && $status !== $key ? '✓' : $i + 1 ?></span>
                    <span class="flow-step__label"><?= e($label) ?></span>
                </div>
                <?php $i++; ?>
            <?php endforeach; ?>
            <?php if ($isClosed && !in_array($status, ['COMPLETED'], true)): ?>
                <div class="flow-step is-danger"><span class="flow-step__dot">!</span><span class="flow-step__label"><?= e($statuses[$status] ?? $status) ?></span></div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($isFin && !$isHr): ?>
    <div class="alert alert-info" style="margin-bottom:14px">
        <?= icon('info') ?>
        <div>Akses Finance: Anda dapat melihat proses ini dan menyelesaikan item serah terima kategori <b>Keuangan</b> saja.</div>
    </div>
<?php endif; ?>

<div class="split-grid wide-left">
    <div class="flex-col gap-12">
        <section class="card">
            <div class="card__head">
                <div><h2>Ringkasan Pengajuan</h2>
                    <div class="sub">Diajukan <?= tanggal($request['submit_date'] ?? $request['created_at'], 'd M Y') ?>.</div></div>
            </div>
            <div class="card__body">
                <div class="flex" style="gap:14px;align-items:center;margin-bottom:16px">
                    <div class="avatar" style="width:48px;height:48px;font-size:15px;background:<?= avatar_color($request['full_name']) ?>"><?= e(initials($request['full_name'])) ?></div>
                    <div style="flex:1">
                        <b style="font-size:15px"><?= e($request['full_name']) ?></b>
                        <div class="muted small">
                            <?= e($request['employee_code']) ?> · <?= e($request['position_name'] ?? '—') ?> ·
                            <?= e($request['unit_name'] ?? '—') ?><?= $request['department_name'] ? ' / ' . e($request['department_name']) : '' ?>
                        </div>
                        <div class="muted small" style="font-size:10.4px;margin-top:2px">
                            Bergabung <?= tanggal($request['join_date'], 'd M Y') ?> · <?= e($request['employee_email']) ?>
                        </div>
                    </div>
                    <div style="text-align:right">
                        <div class="muted small" style="font-size:9.8px;text-transform:uppercase;letter-spacing:.06em">Hari terakhir kerja</div>
                        <b style="font-size:13px"><?= tanggal($request['last_working_date']) ?></b>
                        <?php if (!$isClosed): ?>
                            <div class="small" style="font-size:10.4px;color:<?= $daysLeft <= 7 ? 'var(--rose-600)' : 'var(--muted)' ?>">
                                <?= $daysLeft >= 0 ? $daysLeft . ' hari lagi' : 'sudah lewat ' . abs($daysLeft) . ' hari' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="detail-list detail-list--2col">
                    <div class="detail-list__row"><span>Jenis offboarding</span><b><?= e($types[$request['type']] ?? $request['type']) ?></b></div>
                    <div class="detail-list__row"><span>Alasan utama</span><b><?= e($reasons[$request['reason_category'] ?? ''] ?? '—') ?></b></div>
                    <div class="detail-list__row"><span>Masa pemberitahuan</span><b><?= number_id((int) $request['notice_days']) ?> hari</b></div>
                    <div class="detail-list__row"><span>Penerima serah terima</span><b><?= e($request['handover_name'] ?? '—') ?></b></div>
                    <div class="detail-list__row"><span>Atasan langsung</span><b><?= e($request['manager_name'] ?? '—') ?></b></div>
                    <div class="detail-list__row"><span>Status kepegawaian</span><b><?= e($request['employment_status']) ?><?= $request['contract_end'] ? ' · s.d. ' . tanggal($request['contract_end']) : '' ?></b></div>
                    <div class="detail-list__row"><span>Persetujuan atasan</span><b><?= $request['manager_approved_at'] ? tanggal($request['manager_approved_at'], 'd M Y H:i') : '—' ?></b></div>
                    <div class="detail-list__row"><span>Persetujuan HR</span><b><?= $request['hr_approved_at'] ? tanggal($request['hr_approved_at'], 'd M Y H:i') . ($request['hr_name'] ? ' · ' . e($request['hr_name']) : '') : '—' ?></b></div>
                </div>

                <?php if (!empty($request['reason'])): ?>
                    <div style="margin-top:12px;padding:12px 14px;border-radius:10px;background:#f8fafc;border:1px solid var(--line)">
                        <div class="muted small" style="font-size:9.8px;text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin-bottom:4px">Catatan / kronologi</div>
                        <div class="small" style="white-space:pre-line"><?= e($request['reason']) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <div><h2>Checklist Serah Terima</h2>
                    <div class="sub"><?= $progress['done'] ?>/<?= $progress['total'] ?> item selesai — semua item wajib tuntas sebelum offboarding ditutup.</div></div>
                <?php if ($isHr && !$isClosed && $progress['total'] > 0): ?>
                    <form method="post" action="<?= url('offboarding/' . (int) $request['id'] . '/clearance-all') ?>"
                          data-confirm="Tandai semua item serah terima sebagai selesai?">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline" type="submit"><?= icon('check') ?> Tandai Semua Selesai</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="card__body" style="padding:0">
                <div style="padding:12px 16px 4px">
                    <div class="progress"><span style="width:<?= (int) $progress['pct'] ?>%"></span></div>
                    <div class="muted small" style="font-size:10.4px;margin-top:5px"><?= (int) $progress['pct'] ?>% selesai</div>
                </div>

                <?php foreach ($clearance as $item): ?>
                    <div class="clear-item <?= $item['status'] === 'DONE' ? 'is-done' : ($item['status'] === 'WAIVED' ? 'is-waived' : '') ?>">
                        <div class="clear-item__icon"><?= $item['status'] === 'PENDING' ? icon('clock') : icon('check') ?></div>
                        <div class="clear-item__body">
                            <b><?= e($item['item']) ?></b>
                            <div class="muted small" style="font-size:10.4px">
                                <?= e($categories[$item['category']] ?? $item['category']) ?> ·
                                PIC <?= e($ownerRoles[$item['owner_role']] ?? $item['owner_role']) ?>
                                <?php if ($item['done_at']): ?>
                                    · <?= tanggal($item['done_at'], 'd M Y H:i') ?><?= $item['done_name'] ? ' oleh ' . e($item['done_name']) : '' ?>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($item['note'])): ?>
                                <div class="muted small" style="font-size:10.2px;font-style:italic">“<?= e($item['note']) ?>”</div>
                            <?php endif; ?>
                        </div>
                        <div class="clear-item__actions">
                            <span class="badge badge-<?= $clearanceTones[$item['status']] ?? 'slate' ?>"><?= e($clearanceStatuses[$item['status']] ?? $item['status']) ?></span>
                            <?php if (!$isClosed && ($isHr || $isManager || ($isFin && $item['owner_role'] === 'FINANCE'))): ?>
                                <form method="post" action="<?= url('offboarding/' . (int) $request['id'] . '/clearance/' . (int) $item['id']) ?>">
                                    <?= csrf_field() ?>
                                    <select class="select" name="status" style="min-width:120px;height:30px;font-size:11px" onchange="this.form.requestSubmit()">
                                        <?php foreach ($clearanceStatuses as $key => $label): ?>
                                            <option value="<?= e($key) ?>" <?= $item['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="note" value="<?= e($item['note'] ?? '') ?>">
                                </form>
                            <?php endif; ?>
                            <?php if ($item['status'] === 'DONE' && $item['done_name']): ?>
                                <div class="muted" style="font-size:9.6px"><?= e($item['done_name']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (!$clearance): ?>
                    <div class="empty-state" style="padding:22px">
                        <div class="empty-icon"><?= icon('layers') ?></div>
                        <h3>Checklist belum dibuat</h3>
                        <p>Checklist otomatis dibuat saat HR menyetujui pengajuan.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($isHr && !$isClosed): ?>
                <form method="post" action="<?= url('offboarding/' . (int) $request['id'] . '/clearance') ?>" class="card__foot" style="padding:12px 16px;display:grid;grid-template-columns:1.6fr .9fr .9fr auto;gap:8px;align-items:end">
                    <?= csrf_field() ?>
                    <div class="field" style="margin:0"><label style="font-size:10px">Item baru</label><input class="input" name="item" placeholder="Contoh: Pengembalian kunci ruang server" required maxlength="150"></div>
                    <div class="field" style="margin:0"><label style="font-size:10px">Kategori</label>
                        <select class="select" name="category">
                            <?php foreach ($categories as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field" style="margin:0"><label style="font-size:10px">PIC</label>
                        <select class="select" name="owner_role">
                            <?php foreach ($ownerRoles as $key => $label): ?><option value="<?= e($key) ?>" <?= $key === 'HR' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-sm btn-primary" type="submit"><?= icon('plus') ?> Tambah</button>
                </form>
            <?php endif; ?>
        </section>


        <section class="card" id="aset">
            <div class="card__head">
                <div><h2>Aset &amp; Inventaris</h2>
                    <div class="sub">
                        <?= (int) $assetStats['returned'] ?>/<?= (int) $assetStats['total'] ?> jenis aset dikembalikan
                        (<?= number_id($assetStats['units']) ?> unit)<?= $assetStats['pending'] > 0 ? ' · ' . (int) $assetStats['pending'] . ' belum kembali' : '' ?>.
                    </div></div>
                <?php if ($isHr && !$isClosed && $assetStats['pending'] > 0): ?>
                    <form method="post" action="<?= url('offboarding/' . (int) $request['id'] . '/assets-all') ?>"
                          data-confirm="Tandai semua aset yang belum kembali sebagai sudah dikembalikan?">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline" type="submit"><?= icon('check') ?> Semua Dikembalikan</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="table-wrap" style="border-top:1px solid var(--line)">
                <table class="data">
                    <thead>
                    <tr>
                        <th>Aset / Inventaris</th><th>Kategori</th><th style="width:52px" class="num">Qty</th>
                        <th>Kondisi</th><th>Status</th><th>Catatan</th><th style="width:44px"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($assets as $asset): ?>
                        <tr>
                            <td class="small">
                                <b><?= e($asset['name']) ?></b>
                                <div class="muted mono" style="font-size:9.6px">
                                    <?= e($asset['asset_code'] ?: 'tanpa kode aset') ?>
                                    <?php if ($asset['returned_name']): ?> · <?= e($asset['returned_name']) ?>, <?= tanggal($asset['returned_at'], 'd M Y') ?><?php endif; ?>
                                </div>
                            </td>
                            <td class="small"><?= e($assetCategories[$asset['category']] ?? $asset['category']) ?></td>
                            <td class="num small"><?= (int) $asset['qty'] ?></td>
                            <td class="small"><?= e($assetConditions[$asset['condition_state']] ?? $asset['condition_state']) ?></td>
                            <td>
                                <?php $editable = !$isClosed && ($isHr || $isManager); ?>
                                <?php if (!$editable): ?>
                                    <span class="badge badge-<?= $assetTones[$asset['status']] ?? 'slate' ?>"><?= e($assetStatuses[$asset['status']] ?? $asset['status']) ?></span>
                                <?php else: ?>
                                    <form method="post" action="<?= url('offboarding/' . (int) $request['id'] . '/assets/' . (int) $asset['id']) ?>">
                                        <?= csrf_field() ?>
                                        <select class="select" name="status" style="min-width:132px;height:28px;font-size:10.6px"
                                                onchange="this.form.requestSubmit()" aria-label="Ubah status aset">
                                            <?php foreach ($assetStatuses as $key => $label): ?>
                                                <option value="<?= e($key) ?>" <?= $asset['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td class="small muted"><?= e($asset['note'] ?: '—') ?></td>
                            <td>
                                <?php if ($isHr && !$isClosed): ?>
                                    <form method="post" action="<?= url('offboarding/' . (int) $request['id'] . '/assets/' . (int) $asset['id'] . '/delete') ?>"
                                          data-confirm="Hapus <?= e($asset['name']) ?> dari register aset?">
                                        <?= csrf_field() ?>
                                        <?= method_field('DELETE') ?>
                                        <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)" title="Hapus"><?= icon('trash') ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$assets): ?>
                        <tr><td colspan="7">
                            <div class="empty-state" style="padding:20px">
                                <div class="empty-icon"><?= icon('briefcase') ?></div>
                                <h3>Register aset masih kosong</h3>
                                <p>Tambahkan aset/inventaris yang dipegang karyawan agar pengembaliannya tercatat.</p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($isHr && !$isClosed): ?>
                <form method="post" action="<?= url('offboarding/' . (int) $request['id'] . '/assets') ?>" class="card__foot"
                      style="padding:12px 16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(132px,1fr));gap:8px;align-items:end">
                    <?= csrf_field() ?>
                    <div class="field" style="margin:0"><label style="font-size:10px">Nama aset</label>
                        <input class="input" name="name" placeholder="Contoh: Laptop Lenovo ThinkPad" required maxlength="150"></div>
                    <div class="field" style="margin:0"><label style="font-size:10px">Kategori</label>
                        <select class="select" name="category">
                            <?php foreach ($assetCategories as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="field" style="margin:0"><label style="font-size:10px">Kode aset / SN</label>
                        <input class="input" name="asset_code" maxlength="60" placeholder="opsional"></div>
                    <div class="field" style="margin:0"><label style="font-size:10px">Qty</label>
                        <input class="input" type="number" name="qty" value="1" min="1" max="999"></div>
                    <button class="btn btn-sm btn-primary" type="submit"><?= icon('plus') ?> Tambah</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="card" id="bast">
            <div class="card__head">
                <div><h2>BAST — Berita Acara Serah Terima</h2>
                    <div class="sub">Dokumen bukti pengembalian aset/inventaris, ditandatangani karyawan, penerima, dan saksi.</div></div>
                <?php if ($bast): ?>
                    <div class="card__head__actions">
                        <a class="btn btn-sm btn-outline" href="<?= url('offboarding/' . (int) $request['id'] . '/bast') ?>" target="_blank" rel="noopener"><?= icon('file') ?> Lihat BAST</a>
                        <a class="btn btn-sm btn-primary" href="<?= url('offboarding/' . (int) $request['id'] . '/bast?download=1') ?>"><?= icon('download') ?> Unduh PDF</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($bast): ?>
                <div class="card__body">
                    <div class="alert alert-success" style="margin:0 0 12px">
                        <?= icon('check') ?>
                        <div>
                            <b>BAST <?= e($bast['bast_number']) ?></b> diterbitkan
                            <?= tanggal($bast['issued_at'], 'd M Y H:i') ?><?= $bast['issued_name'] ? ' oleh ' . e($bast['issued_name']) : '' ?>.
                        </div>
                    </div>
                    <div class="detail-list detail-list--2col">
                        <div class="detail-list__row"><span>Penerima</span><b><?= e($bast['receiver_name'] ?: 'HR Department') ?></b></div>
                        <div class="detail-list__row"><span>Jabatan penerima</span><b><?= e($bast['receiver_position'] ?: '—') ?></b></div>
                        <div class="detail-list__row"><span>Saksi</span><b><?= e($bast['witness_name'] ?: ($request['manager_name'] ?? '—')) ?></b></div>
                        <div class="detail-list__row"><span>Jumlah aset</span><b><?= count($bastItems) ?> jenis · <?= (int) $bastUnits ?> unit</b></div>
                    </div>
                    <?php if (!empty($bast['note'])): ?>
                        <div class="small muted" style="margin-top:8px">Keterangan: <?= e($bast['note']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($isHr && !$isClosed && in_array($status, ['IN_PROGRESS', 'COMPLETED'], true)): ?>
                <form method="post" action="<?= url('offboarding/' . (int) $request['id'] . '/bast') ?>"
                      <?= $bast ? 'style="border-top:1px solid var(--line)"' : '' ?>>
                    <?= csrf_field() ?>
                    <div class="card__body">
                        <div class="muted small" style="font-size:9.8px;text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin-bottom:8px">
                            <?= $bast ? 'Terbitkan ulang BAST' : 'Terbitkan BAST' ?>
                        </div>
                        <?php if ($assetStats['pending'] > 0): ?>
                            <div class="alert alert-warning" style="margin:0 0 12px">
                                <?= icon('alert') ?>
                                <div class="small">Masih ada <b><?= (int) $assetStats['pending'] ?></b> aset berstatus belum kembali.
                                    Selesaikan dulu, atau centang opsi di bawah untuk menerbitkan BAST dengan catatan.</div>
                            </div>
                        <?php endif; ?>
                        <div class="form-grid two">
                            <div class="field">
                                <label>Nama penerima</label>
                                <input class="input" name="receiver_name" maxlength="150"
                                       value="<?= e($bast['receiver_name'] ?? ($currentUser['full_name'] ?? '')) ?>" placeholder="Nama HR/IT yang menerima">
                            </div>
                            <div class="field">
                                <label>Jabatan penerima</label>
                                <input class="input" name="receiver_position" maxlength="120"
                                       value="<?= e($bast['receiver_position'] ?? 'HR Department') ?>" placeholder="Contoh: HR Manager">
                            </div>
                            <div class="field">
                                <label>Nama saksi</label>
                                <input class="input" name="witness_name" maxlength="150"
                                       value="<?= e($bast['witness_name'] ?? ($request['manager_name'] ?? '')) ?>" placeholder="Atasan langsung / saksi">
                            </div>
                            <div class="field">
                                <label>Keterangan (opsional)</label>
                                <input class="input" name="note" maxlength="500" value="<?= e($bast['note'] ?? '') ?>">
                            </div>
                        </div>
                        <?php if ($assetStats['pending'] > 0): ?>
                            <label class="flex small" style="gap:6px;margin-top:10px">
                                <input type="checkbox" name="force" value="1">
                                Terbitkan BAST dengan catatan aset belum kembali
                            </label>
                        <?php endif; ?>
                    </div>
                    <div class="card__foot" style="padding:12px 16px;display:flex;justify-content:flex-end">
                        <button class="btn <?= $bast ? 'btn-outline' : 'btn-primary' ?>" type="submit">
                            <?= icon('file') ?> <?= $bast ? 'Terbitkan Ulang BAST' : 'Terbitkan BAST' ?>
                        </button>
                    </div>
                </form>
            <?php elseif (!$bast && !$isHr): ?>
                <div class="card__body"><div class="small muted">BAST diterbitkan HR setelah proses serah terima dimulai.</div></div>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card__head">
                <div><h2>Exit Interview &amp; Penyelesaian Akhir</h2>
                    <div class="sub">Diisi HR saat wawancara keluar; umpan balik karyawan tampil di bawah.</div></div>
            </div>
            <?php if ($isHr): ?>
                <form method="post" action="<?= url('offboarding/' . (int) $request['id'] . '/interview') ?>">
                    <?= csrf_field() ?>
                    <div class="card__body">
                        <div class="form-grid two">
                            <div class="field">
                                <label>Jadwal wawancara</label>
                                <input class="input" type="datetime-local" name="exit_interview_at"
                                       value="<?= e($request['exit_interview_at'] ? date('Y-m-d\TH:i', strtotime((string) $request['exit_interview_at'])) : '') ?>">
                            </div>
                            <div class="field">
                                <label>Pewawancara</label>
                                <select class="select" name="exit_interviewer_id">
                                    <option value="0">— Pilih karyawan —</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= (int) $emp['id'] ?>" <?= (int) $request['exit_interviewer_id'] === (int) $emp['id'] ? 'selected' : '' ?>><?= e($emp['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Bersedia merekomendasikan?</label>
                                <select class="select" name="exit_would_recommend">
                                    <option value="">— Belum dinilai —</option>
                                    <?php foreach ($recommendations as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= $request['exit_would_recommend'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Penyelesaian akhir (Rp)</label>
                                <input class="input" type="number" name="settlement_amount" min="0" step="1000" value="<?= e((string) (float) $request['settlement_amount']) ?>">
                            </div>
                        </div>
                        <div class="field" style="margin-top:12px">
                            <label>Catatan wawancara</label>
                            <textarea class="textarea" name="exit_note" rows="2" maxlength="500"><?= e($request['exit_note'] ?? '') ?></textarea>
                        </div>
                        <div class="field" style="margin-top:12px">
                            <label>Rincian penyelesaian (gaji prorata, sisa cuti, potongan)</label>
                            <textarea class="textarea" name="settlement_note" rows="2" maxlength="500"><?= e($request['settlement_note'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="card__foot" style="padding:12px 16px;display:flex;justify-content:flex-end">
                        <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="card__body">
                    <div class="detail-list">
                        <div class="detail-list__row"><span>Jadwal wawancara</span><b><?= $request['exit_interview_at'] ? tanggal($request['exit_interview_at'], 'd M Y H:i') : 'Belum dijadwalkan' ?></b></div>
                        <div class="detail-list__row"><span>Pewawancara</span><b><?= e($request['interviewer_name'] ?? '—') ?></b></div>
                        <div class="detail-list__row"><span>Rekomendasi</span><b><?= e($recommendations[$request['exit_would_recommend'] ?? ''] ?? '—') ?></b></div>
                        <div class="detail-list__row"><span>Penyelesaian akhir</span><b><?= (float) $request['settlement_amount'] > 0 ? rupiah($request['settlement_amount']) : '—' ?></b></div>
                    </div>
                    <?php if (!empty($request['exit_note'])): ?>
                        <div class="small muted" style="margin-top:10px"><?= e($request['exit_note']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($request['exit_feedback'])): ?>
                <div class="card__body" style="border-top:1px solid var(--line)">
                    <div class="muted small" style="font-size:9.8px;text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin-bottom:4px">Umpan balik karyawan</div>
                    <div class="small" style="white-space:pre-line"><?= e($request['exit_feedback']) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($isOwner && !$isClosed): ?>
                <form method="post" action="<?= url('offboarding/' . (int) $request['id'] . '/feedback') ?>" style="border-top:1px solid var(--line)">
                    <?= csrf_field() ?>
                    <div class="card__body">
                        <div class="field">
                            <label>Umpan balik Anda selama bekerja</label>
                            <textarea class="textarea" name="exit_feedback" rows="3" maxlength="1500" placeholder="Hal yang sudah baik, tantangan, dan saran untuk manajemen."><?= e($request['exit_feedback'] ?? '') ?></textarea>
                        </div>
                        <div class="field" style="margin-top:10px;max-width:260px">
                            <label>Bersedia merekomendasikan perusahaan?</label>
                            <select class="select" name="exit_would_recommend">
                                <option value="">— Pilih —</option>
                                <?php foreach ($recommendations as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $request['exit_would_recommend'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="card__foot" style="padding:12px 16px;display:flex;justify-content:flex-end">
                        <button class="btn btn-outline" type="submit"><?= icon('send') ?> Kirim Umpan Balik</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <div class="flex-col gap-12">
        <?php if ($canApproveManager || $canApproveHr || $canComplete || $canReject || $canWithdraw || $canCancel): ?>
            <section class="card">
                <div class="card__head"><div><h2>Aksi</h2></div></div>
                <div class="card__body flex-col gap-10">
                    <?php if ($canApproveManager): ?>
                        <form method="post" action="<?= url('offboarding/' . (int) $request['id'] . '/manager-approve') ?>" class="flex-col gap-8">
                            <?= csrf_field() ?>
                            <div class="field" style="margin:0"><label style="font-size:10px">Catatan atasan (opsional)</label>
                                <input class="input" name="note" maxlength="255" placeholder="Pertimbangan persetujuan…"></div>
                            <button class="btn btn-primary" type="submit"><?= icon('check') ?> Setujui sebagai Atasan</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($canApproveHr): ?>
                        <form method="post" action="<?= url('offboarding/' . (int) $request['id'] . '/approve') ?>" class="flex-col gap-8">
                            <?= csrf_field() ?>
                            <div class="field" style="margin:0"><label style="font-size:10px">Catatan HR (opsional)</label>
                                <input class="input" name="note" maxlength="255" placeholder="Nomor surat, kesepakatan…"></div>
                            <button class="btn btn-primary" type="submit"><?= icon('check') ?> Setujui HR &amp; Buat Checklist</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($canComplete): ?>
                        <form method="post" action="<?= url('offboarding/' . (int) $request['id'] . '/complete') ?>"
                              data-confirm="Tutup offboarding dan tandai karyawan RESIGN per <?= tanggal($request['last_working_date']) ?>?">
                            <?= csrf_field() ?>
                            <button class="btn btn-primary" type="submit" style="width:100%" <?= $progress['pct'] < 100 ? 'disabled title="Selesaikan seluruh checklist terlebih dahulu"' : '' ?>>
                                <?= icon('logout') ?> Tutup Offboarding
                            </button>
                            <?php if ($progress['pct'] < 100): ?>
                                <div class="hint" style="margin-top:6px">Selesaikan checklist serah terima (<?= (int) $progress['pct'] ?>%) sebelum menutup proses.</div>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>

                    <?php if ($canWithdraw): ?>
                        <form method="post" action="<?= url('me/offboarding/' . (int) $request['id'] . '/withdraw') ?>"
                              data-confirm="Batalkan pengajuan offboarding ini?">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline" type="submit" style="width:100%"><?= icon('x') ?> Batalkan Pengajuan</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($canCancel): ?>
                        <form method="post" action="<?= url('offboarding/' . (int) $request['id'] . '/cancel') ?>"
                              data-confirm="Batalkan pengajuan ini atas nama HR?">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline" type="submit" style="width:100%"><?= icon('x') ?> Batalkan (HR)</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($canReject): ?>
                        <details>
                            <summary class="btn btn-ghost" style="width:100%;color:var(--rose-600);cursor:pointer"><?= icon('alert') ?> Tolak Pengajuan</summary>
                            <form method="post" action="<?= url('offboarding/' . (int) $request['id'] . '/reject') ?>" class="flex-col gap-8" style="margin-top:8px">
                                <?= csrf_field() ?>
                                <textarea class="textarea" name="reject_reason" rows="2" required maxlength="255" placeholder="Alasan penolakan (wajib)"></textarea>
                                <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)">Konfirmasi Tolak</button>
                            </form>
                        </details>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($isHr && !$isClosed): ?>
            <section class="card">
                <div class="card__head"><div><h2>Sesuaikan Jadwal</h2></div></div>
                <form method="post" action="<?= url('offboarding/' . (int) $request['id']) ?>">
                    <?= csrf_field() ?>
                    <?= method_field('PUT') ?>
                    <div class="card__body flex-col gap-10">
                        <div class="field" style="margin:0">
                            <label>Tanggal pengajuan</label>
                            <input class="input" type="date" name="submit_date" value="<?= e((string) ($request['submit_date'] ?? date('Y-m-d'))) ?>">
                        </div>
                        <div class="field" style="margin:0">
                            <label>Hari terakhir kerja</label>
                            <input class="input" type="date" name="last_working_date" value="<?= e((string) $request['last_working_date']) ?>" required>
                        </div>
                        <div class="field" style="margin:0">
                            <label>Penerima serah terima</label>
                            <select class="select" name="handover_to">
                                <option value="0">— Belum ditentukan —</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= (int) $emp['id'] ?>" <?= (int) $request['handover_to'] === (int) $emp['id'] ? 'selected' : '' ?>><?= e($emp['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field" style="margin:0">
                            <label>Alasan utama</label>
                            <select class="select" name="reason_category">
                                <option value="">—</option>
                                <?php foreach ($reasons as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $request['reason_category'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field" style="margin:0">
                            <label>Catatan</label>
                            <textarea class="textarea" name="reason" rows="2" maxlength="1000"><?= e($request['reason'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="card__foot" style="padding:12px 16px;display:flex;justify-content:flex-end">
                        <button class="btn btn-sm btn-outline" type="submit"><?= icon('check') ?> Simpan Perubahan</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="card">
            <div class="card__head"><div><h2>Riwayat Tahapan</h2></div></div>
            <div class="card__body">
                <div class="timeline">
                    <?php foreach ($stages as $stage): ?>
                        <div class="timeline__item">
                            <div class="timeline__dot"></div>
                            <div class="timeline__body">
                                <b><?= e($statuses[$stage['to_status']] ?? $stage['to_status']) ?></b>
                                <div class="muted small" style="font-size:10.4px">
                                    <?= tanggal($stage['created_at'], 'd M Y H:i') ?><?= $stage['actor_name'] ? ' · ' . e($stage['actor_name']) : '' ?>
                                </div>
                                <?php if (!empty($stage['note'])): ?>
                                    <div class="small" style="font-size:10.8px;margin-top:2px"><?= e($stage['note']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$stages): ?>
                        <div class="muted small">Belum ada riwayat.</div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if ($canDelete): ?>
            <section class="card">
                <div class="card__body" style="padding:12px 16px">
                    <form method="post" action="<?= url('offboarding/' . (int) $request['id'] . '/delete') ?>"
                          data-confirm="Hapus pengajuan <?= e($request['request_number']) ?> secara permanen?">
                        <?= csrf_field() ?>
                        <?= method_field('DELETE') ?>
                        <button class="btn btn-ghost" type="submit" style="width:100%;color:var(--rose-600)"><?= icon('trash') ?> Hapus Pengajuan</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
