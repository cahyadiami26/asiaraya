<?php
/**
 * Detail klaim + jejak persetujuan.
 * @var array $claim, $statuses, $tones, $errors
 * @var bool $isOwner, $isStaff, $canDecide, $canPay
 */
$badge = static fn (string $status): string => '<span class="badge badge-' . ($tones[$status] ?? 'slate') . '">'
    . e($statuses[$status] ?? $status) . '</span>';
$receiptUrl = $claim['receipt'] ? url('media/' . $claim['receipt']) : null;
$isImage = $claim['receipt'] && preg_match('/\.(jpe?g|png|webp)$/i', (string) $claim['receipt']);
$back = $isStaff ? url('claims') : url('me/claims');
?>
<div class="page-head">
    <div>
        <div class="small muted" style="margin-bottom:4px">
            <a href="<?= $back ?>"><?= $isStaff ? 'Klaim & Reimbursement' : 'Klaim Saya' ?></a> / Detail
        </div>
        <h1 class="mono" style="font-size:22px"><?= e($claim['claim_number']) ?></h1>
        <div class="sub"><?= e($claim['category_name']) ?> · <?= tanggal($claim['claim_date'], 'd M Y') ?> · diajukan <?= tanggal($claim['created_at'], 'd M Y H:i') ?></div>
    </div>
    <div class="page-head__actions">
        <?= $badge((string) $claim['status']) ?>
    </div>
</div>

<div class="split-grid wide-left">
    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head">
                <div><h2>Rincian Klaim</h2>
                    <div class="sub">Nominal yang dimintakan penggantian.</div></div>
                <div class="text-right">
                    <div class="muted small">Nominal</div>
                    <b style="font-size:20px;letter-spacing:-.02em"><?= rupiah($claim['amount']) ?></b>
                </div>
            </div>
            <div class="card__body">
                <div class="form-grid three">
                    <div><div class="small muted">Karyawan</div><b><?= e($claim['full_name']) ?></b>
                        <div class="mono muted small" style="font-size:10px"><?= e($claim['employee_code']) ?></div></div>
                    <div><div class="small muted">Unit / Departemen</div><b class="small"><?= e($claim['unit_name'] ?? '-') ?></b>
                        <div class="muted small" style="font-size:10px"><?= e($claim['department_name'] ?? '-') ?></div></div>
                    <div><div class="small muted">Kategori</div><b class="small"><?= e($claim['category_name']) ?></b>
                        <div class="mono muted small" style="font-size:10px"><?= e($claim['category_code']) ?></div></div>
                    <div><div class="small muted">Tanggal Transaksi</div><b class="small"><?= tanggal($claim['claim_date'], 'd M Y') ?></b></div>
                    <div><div class="small muted">Masuk Payroll</div><b class="small mono"><?= e($claim['slip_number'] ?: '—') ?></b>
                        <?php if ($claim['period_month']): ?>
                            <div class="muted small" style="font-size:10px"><?= bulan_id((int) $claim['period_month']) ?> <?= (int) $claim['period_year'] ?></div>
                        <?php endif; ?></div>
                    <div><div class="small muted">Dibayarkan</div><b class="small"><?= $claim['paid_at'] ? tanggal($claim['paid_at'], 'd M Y H:i') : '—' ?></b></div>
                </div>

                <div class="divider"></div>
                <div>
                    <div class="small muted" style="margin-bottom:4px">Keterangan</div>
                    <div class="small" style="line-height:1.65"><?= nl2br(e($claim['description'])) ?></div>
                </div>

                <div class="divider"></div>
                <div>
                    <div class="small muted" style="margin-bottom:6px">Bukti / Nota</div>
                    <?php if ($receiptUrl): ?>
                        <?php if ($isImage): ?>
                            <a href="<?= e($receiptUrl) ?>" target="_blank">
                                <img src="<?= e($receiptUrl) ?>" alt="Bukti klaim"
                                     style="max-width:320px;border:1px solid var(--line);border-radius:10px">
                            </a>
                        <?php endif; ?>
                        <div style="margin-top:8px">
                            <a class="btn btn-sm btn-outline" href="<?= e($receiptUrl) ?>" target="_blank"><?= icon('file') ?> Buka Lampiran</a>
                        </div>
                    <?php else: ?>
                        <span class="small muted">Tidak ada lampiran.</span>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Riwayat Persetujuan</h2>
                <div class="sub">Alur atasan langsung → HR/Finance.</div></div></div>
            <div class="card__body">
                <div class="timeline">
                    <div class="timeline__item">
                        <b>Klaim diajukan</b>
                        <span><?= tanggal($claim['created_at'], 'd M Y H:i') ?> · <?= e($claim['full_name']) ?></span>
                    </div>

                    <div class="timeline__item <?= $claim['manager_approved_at'] ? '' : 'muted' ?>">
                        <b>Persetujuan atasan langsung</b>
                        <?php if ($claim['manager_approved_at']): ?>
                            <span><?= tanggal($claim['manager_approved_at'], 'd M Y H:i') ?>
                                · <?= e($claim['manager_name'] ?: 'Atasan') ?>
                                <?php if ($claim['status'] === 'REJECTED' && !$claim['approver_id']): ?>· <b style="color:var(--rose-600)">Ditolak</b><?php endif; ?>
                            </span>
                            <?php if ($claim['manager_note']): ?><span>Catatan: <?= e($claim['manager_note']) ?></span><?php endif; ?>
                        <?php elseif ($claim['status'] === 'CANCELLED'): ?>
                            <span>Dibatalkan oleh pengaju sebelum diproses.</span>
                        <?php else: ?>
                            <span>Menunggu keputusan atasan langsung.</span>
                        <?php endif; ?>
                    </div>

                    <div class="timeline__item <?= $claim['approved_at'] ? '' : 'muted' ?>">
                        <b>Persetujuan HR / Finance</b>
                        <?php if ($claim['approved_at']): ?>
                            <span><?= tanggal($claim['approved_at'], 'd M Y H:i') ?> · <?= e($claim['approver_name'] ?: 'HR/Finance') ?>
                                <?php if ($claim['status'] === 'REJECTED' && $claim['approver_id']): ?>· <b style="color:var(--rose-600)">Ditolak</b><?php endif; ?>
                            </span>
                            <?php if ($claim['approver_note']): ?><span>Catatan: <?= e($claim['approver_note']) ?></span><?php endif; ?>
                        <?php else: ?>
                            <span>Menunggu keputusan HR/Finance.</span>
                        <?php endif; ?>
                    </div>

                    <div class="timeline__item <?= $claim['paid_at'] ? '' : 'muted' ?>">
                        <b>Pembayaran</b>
                        <?php if ($claim['paid_at']): ?>
                            <span><?= tanggal($claim['paid_at'], 'd M Y H:i') ?>
                                <?= $claim['slip_number'] ? '· slip ' . e($claim['slip_number']) : '· transfer reimbursement' ?></span>
                        <?php elseif ($claim['status'] === 'SCHEDULED'): ?>
                            <span>Akan dibayarkan bersama payroll periode ini.</span>
                        <?php else: ?>
                            <span>Belum dibayarkan.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="flex-col gap-16">
        <?php if ($canDecide): ?>
            <section class="card">
                <div class="card__head"><div><h2>Keputusan</h2>
                    <div class="sub">Setujui atau tolak klaim ini.</div></div></div>
                <form class="card__body" method="post" action="<?= url('claims/' . $claim['id'] . '/decide') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="back" value="/claims/<?= (int) $claim['id'] ?>">
                    <div class="field">
                        <label>Catatan (opsional)</label>
                        <textarea class="textarea" name="note" rows="2" maxlength="255"
                                  placeholder="Alasan penolakan atau catatan persetujuan"><?= e(old('note')) ?></textarea>
                    </div>
                    <div class="flex-col gap-8" style="margin-top:12px">
                        <button class="btn btn-primary btn-block" type="submit" name="decision" value="APPROVED"><?= icon('check') ?> Setujui Klaim</button>
                        <button class="btn btn-outline btn-block" type="submit" name="decision" value="REJECTED"
                                style="color:var(--rose-600);border-color:#fbd0d9"><?= icon('x') ?> Tolak Klaim</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($canPay && $claim['status'] === 'APPROVED'): ?>
            <section class="card">
                <div class="card__head"><div><h2>Pembayaran</h2>
                    <div class="sub">Bayar di luar payroll (transfer langsung).</div></div></div>
                <form class="card__body" method="post" action="<?= url('claims/' . $claim['id'] . '/pay') ?>"
                      data-confirm="Tandai klaim <?= e($claim['claim_number']) ?> sebesar <?= rupiah($claim['amount']) ?> sudah ditransfer?">
                    <?= csrf_field() ?>
                    <p class="small muted" style="margin-bottom:12px;line-height:1.6">
                        Klaim yang dibiarkan akan otomatis ikut dibayarkan pada payroll periode berikutnya
                        (tercatat sebagai komponen <span class="mono">REIMBURSEMENT</span>).
                    </p>
                    <button class="btn btn-primary btn-block" type="submit"><?= icon('check') ?> Tandai Dibayarkan</button>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($isOwner && in_array($claim['status'], ['PENDING', 'MANAGER_APPROVED'], true)): ?>
            <section class="card">
                <div class="card__head"><div><h2>Batalkan</h2>
                    <div class="sub">Hanya selama belum disetujui final.</div></div></div>
                <form class="card__body" method="post" action="<?= url('me/claims/' . $claim['id'] . '/cancel') ?>"
                      data-confirm="Batalkan klaim <?= e($claim['claim_number']) ?>?">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline btn-block" type="submit" style="color:var(--rose-600)"><?= icon('x') ?> Batalkan Klaim</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="card">
            <div class="card__head"><div><h2>Ringkasan</h2></div></div>
            <div class="card__body">
                <div class="flex-between small" style="padding:8px 0;border-bottom:1px solid var(--line)">
                    <span class="muted">Status</span><?= $badge((string) $claim['status']) ?>
                </div>
                <div class="flex-between small" style="padding:8px 0;border-bottom:1px solid var(--line)">
                    <span class="muted">Kategori</span><b><?= e($claim['category_name']) ?></b>
                </div>
                <div class="flex-between small" style="padding:8px 0;border-bottom:1px solid var(--line)">
                    <span class="muted">Bukti wajib</span><b><?= (int) $claim['requires_receipt'] === 1 ? 'Ya' : 'Tidak' ?></b>
                </div>
                <div class="flex-between small" style="padding:8px 0">
                    <span class="muted">Nominal</span><b><?= rupiah($claim['amount']) ?></b>
                </div>
                <a class="btn btn-ghost btn-sm btn-block" style="margin-top:12px" href="<?= $back ?>"><?= icon('arrow-left') ?> Kembali ke daftar</a>
            </div>
        </section>
    </div>
</div>
