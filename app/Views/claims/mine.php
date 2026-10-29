<?php
/**
 * Portal klaim karyawan: ajukan, pantau status, dan (bila atasan) setujui klaim tim.
 * @var array $claims, $stats, $categories, $teamClaims, $statuses, $tones, $errors, $old
 * @var bool $isManager
 * @var int $maxAge
 */
$badge = static fn (string $status): string => '<span class="badge badge-' . ($tones[$status] ?? 'slate') . '">'
    . e($statuses[$status] ?? $status) . '</span>';
$today = date('Y-m-d');
$minDate = $maxAge > 0 ? date('Y-m-d', strtotime('-' . $maxAge . ' days')) : null;
?>
<div class="page-head">
    <div>
        <h1>Klaim Saya</h1>
        <div class="sub">Ajukan penggantian biaya operasional, pantau persetujuan, dan lihat riwayat pembayaran.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-primary" href="#form-klaim"><?= icon('plus') ?> Ajukan Klaim</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Total Klaim</span>
        <span class="v"><?= number_id($stats['total']) ?></span>
        <span class="s">seluruh periode</span>
    </div>
    <div class="mini-stat">
        <span class="k">Nilai Diproses</span>
        <span class="v" style="font-size:15px"><?= rupiah($stats['open_amount']) ?></span>
        <span class="s">menunggu persetujuan / payroll</span>
    </div>
    <div class="mini-stat">
        <span class="k">Dibayarkan Bulan Ini</span>
        <span class="v" style="font-size:15px;color:var(--teal-700)"><?= rupiah($stats['paid_this_month']) ?></span>
        <span class="s"><?= tanggal(date('Y-m-01'), 'F Y') ?></span>
    </div>
    <div class="mini-stat">
        <span class="k">Total Diterima</span>
        <span class="v" style="font-size:15px"><?= rupiah($stats['paid_amount']) ?></span>
        <span class="s">akumulasi reimbursement</span>
    </div>
</div>

<?php if ($isManager && $teamClaims): ?>
    <section class="card" style="margin-bottom:16px">
        <div class="card__head">
            <div><h2>Persetujuan Klaim Tim</h2>
                <div class="sub"><?= number_id(count($teamClaims)) ?> klaim bawahan langsung menunggu keputusan Anda.</div></div>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr><th>Karyawan</th><th>Kategori</th><th>Tanggal</th><th class="num">Nominal</th><th>Keterangan</th><th style="width:180px">Keputusan</th></tr>
                </thead>
                <tbody>
                <?php foreach ($teamClaims as $claim): ?>
                    <tr>
                        <td>
                            <b><?= e($claim['full_name']) ?></b>
                            <div class="mono muted" style="font-size:10px"><?= e($claim['employee_code']) ?> · <?= e($claim['claim_number']) ?></div>
                        </td>
                        <td class="small"><?= e($claim['category_name']) ?></td>
                        <td class="small"><?= tanggal($claim['claim_date']) ?></td>
                        <td class="num small"><b><?= rupiah($claim['amount']) ?></b></td>
                        <td class="small muted" style="max-width:220px"><?= e($claim['description']) ?></td>
                        <td>
                            <div class="flex" style="gap:4px">
                                <form class="inline-form" method="post" action="<?= url('claims/' . $claim['id'] . '/decide') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="decision" value="APPROVED">
                                    <input type="hidden" name="back" value="/me/claims">
                                    <button class="btn btn-sm btn-primary" type="submit"><?= icon('check') ?> Setujui</button>
                                </form>
                                <form class="inline-form" method="post" action="<?= url('claims/' . $claim['id'] . '/decide') ?>"
                                      data-confirm="Tolak klaim <?= e($claim['claim_number']) ?> sebesar <?= rupiah($claim['amount']) ?>?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="decision" value="REJECTED">
                                    <input type="hidden" name="back" value="/me/claims">
                                    <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)"><?= icon('x') ?> Tolak</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Riwayat Klaim</h2>
                <div class="sub">Klik nomor klaim untuk melihat detail dan bukti yang dilampirkan.</div></div>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr><th>No. Klaim</th><th>Kategori</th><th>Tanggal</th><th class="num">Nominal</th><th>Status</th><th style="width:80px"></th></tr>
                </thead>
                <tbody>
                <?php foreach ($claims as $claim): ?>
                    <tr>
                        <td>
                            <a href="<?= url('claims/' . $claim['id']) ?>"><b class="mono" style="font-size:10.6px"><?= e($claim['claim_number']) ?></b></a>
                            <div class="muted small" style="font-size:10px;max-width:200px"><?= e($claim['description']) ?></div>
                        </td>
                        <td class="small"><?= e($claim['category_name']) ?></td>
                        <td class="small"><?= tanggal($claim['claim_date']) ?></td>
                        <td class="num small"><b><?= rupiah($claim['amount']) ?></b></td>
                        <td>
                            <?= $badge((string) $claim['status']) ?>
                            <?php if ($claim['slip_number']): ?>
                                <div class="muted mono" style="font-size:9.6px;margin-top:2px"><?= e($claim['slip_number']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (in_array($claim['status'], ['PENDING', 'MANAGER_APPROVED'], true)): ?>
                                <form class="inline-form" method="post" action="<?= url('me/claims/' . $claim['id'] . '/cancel') ?>"
                                      data-confirm="Batalkan klaim <?= e($claim['claim_number']) ?>?">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-ghost" type="submit" title="Batalkan"><?= icon('trash') ?></button>
                                </form>
                            <?php else: ?>
                                <a class="btn btn-sm btn-ghost" href="<?= url('claims/' . $claim['id']) ?>" title="Detail"><?= icon('arrow-right') ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$claims): ?>
                    <tr><td colspan="6">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('wallet') ?></div>
                            <h3>Belum ada klaim</h3>
                            <p>Ajukan penggantian biaya operasional lewat formulir di samping.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" id="form-klaim">
        <div class="card__head">
            <div><h2>Ajukan Klaim Baru</h2>
                <div class="sub">Lampirkan bukti (foto/PDF) agar proses persetujuan lebih cepat.</div></div>
        </div>
        <form class="card__body" method="post" action="<?= url('me/claims') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="field">
                <label>Kategori <span class="req">*</span></label>
                <select class="select" name="category_id" required>
                    <option value="">Pilih kategori…</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= (string) old('category_id') === (string) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?><?= $category['max_amount'] !== null ? ' — maks ' . rupiah((float) $category['max_amount']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['category_id'])): ?><span class="err"><?= e($errors['category_id']) ?></span><?php endif; ?>
                <?php if (!$categories): ?>
                    <span class="hint">Belum ada kategori aktif. Hubungi HR untuk membuka kategori klaim.</span>
                <?php endif; ?>
            </div>

            <div class="form-grid" style="margin-top:12px">
                <div class="field">
                    <label>Tanggal Transaksi <span class="req">*</span></label>
                    <input class="input" type="date" name="claim_date" required
                           max="<?= e($today) ?>" <?= $minDate ? 'min="' . e($minDate) . '"' : '' ?>
                           value="<?= e(old('claim_date', $today)) ?>">
                    <?php if (!empty($errors['claim_date'])): ?><span class="err"><?= e($errors['claim_date']) ?></span><?php endif; ?>
                    <span class="hint">Maksimal <?= (int) $maxAge ?> hari ke belakang.</span>
                </div>
                <div class="field">
                    <label>Nominal (Rp) <span class="req">*</span></label>
                    <input class="input" type="number" name="amount" min="1" step="100" required
                           value="<?= e(old('amount')) ?>" placeholder="150000">
                    <?php if (!empty($errors['amount'])): ?><span class="err"><?= e($errors['amount']) ?></span><?php endif; ?>
                </div>
            </div>

            <div class="field" style="margin-top:12px">
                <label>Keterangan <span class="req">*</span></label>
                <textarea class="textarea" name="description" maxlength="255" rows="2" required
                          placeholder="Contoh: Taksi ke klien Sudirman, 28 Okt 2026"><?= e(old('description')) ?></textarea>
                <?php if (!empty($errors['description'])): ?><span class="err"><?= e($errors['description']) ?></span><?php endif; ?>
            </div>

            <div class="field" style="margin-top:12px">
                <label>Bukti / Nota</label>
                <input class="input" type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png">
                <span class="hint">PDF/JPG/PNG, maksimal 4 MB. Wajib untuk kategori yang menandai "perlu bukti".</span>
            </div>

            <div class="form-actions" style="margin-top:14px">
                <button class="btn btn-primary btn-block" type="submit"><?= icon('check') ?> Kirim Klaim</button>
            </div>
        </form>
    </section>
</div>
