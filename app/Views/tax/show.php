<?php
/**
 * Rincian PPh 21 & bukti potong 1721-A1 per karyawan — Batch 20.
 * @var array $detail, $years
 * @var array|null $slip
 * @var int $year
 * @var bool $canManage
 */
$employee = $detail['employee'];
$calc = $detail['computed'];
$breakdown = $detail['breakdown'];
$months = $detail['months'];
$isFinal = (bool) ($calc['tax_final'] ?? false);
$diffColor = !$isFinal
    ? 'var(--muted)'
    : ((float) $calc['tax_diff'] > 0 ? 'var(--rose-600)' : ((float) $calc['tax_diff'] < 0 ? 'var(--teal-700)' : 'var(--muted)'));
$diffLabel = !$isFinal
    ? 'Selisih berjalan'
    : ((float) $calc['tax_diff'] > 0 ? 'Kurang bayar' : ((float) $calc['tax_diff'] < 0 ? 'Lebih potong' : 'Sesuai'));
$pctTerutang = (float) $calc['gross'] > 0 ? (float) $calc['tax_due'] / (float) $calc['gross'] * 100 : 0.0;
?>
<div class="page-head">
    <div>
        <h1><?= e($employee['full_name']) ?></h1>
        <div class="sub">
            <?= e($employee['employee_code']) ?> · <?= e($calc['position_name'] ?: '—') ?> · <?= e($calc['unit_name'] ?: '—') ?>
            · Tahun pajak <?= (int) $year ?>
        </div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('tax?year=' . (int) $year) ?>"><?= icon('chevron-left') ?> Rekap Pajak</a>
        <a class="btn btn-primary" href="<?= url('tax/' . (int) $employee['id'] . '/pdf?year=' . (int) $year) ?>" target="_blank"><?= icon('printer') ?> Cetak 1721-A1</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Bruto Setahun</span>
        <span class="v" style="font-size:15px"><?= rupiah($calc['gross']) ?></span>
        <span class="s"><?= number_id($calc['months']) ?> bulan payroll</span>
    </div>
    <div class="mini-stat">
        <span class="k">Neto</span>
        <span class="v" style="font-size:15px"><?= rupiah($calc['netto']) ?></span>
        <span class="s">setelah biaya jabatan &amp; pensiun</span>
    </div>
    <div class="mini-stat">
        <span class="k">PKP</span>
        <span class="v" style="font-size:15px"><?= rupiah($calc['pkp']) ?></span>
        <span class="s">PTKP <?= e($calc['tax_status']) ?> · <?= rupiah($calc['ptkp']) ?></span>
    </div>
    <div class="mini-stat">
        <span class="k">PPh 21 Terutang</span>
        <span class="v" style="font-size:15px"><?= rupiah($calc['tax_due']) ?></span>
        <span class="s">efektif <?= number_format($pctTerutang, 1, ',', '.') ?>% dari bruto</span>
    </div>
    <div class="mini-stat">
        <span class="k"><?= e($diffLabel) ?></span>
        <span class="v" style="font-size:15px;color:<?= $diffColor ?>"><?= rupiah(abs((float) $calc['tax_diff'])) ?></span>
        <span class="s"><?= $isFinal ? 'dipotong ' . rupiah($calc['tax_withheld']) : 'proyeksi · baru final pada masa Desember' ?></span>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Perhitungan PPh 21 Setahun</h2>
                <div class="sub">Metode progresif Pasal 17 (UU HPP) — sama dengan mesin payroll.</div></div>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Uraian</th><th class="num">Jumlah (Rp)</th></tr></thead>
                <tbody>
                <tr><td>Penghasilan bruto setahun</td><td class="num"><b><?= rupiah($calc['gross']) ?></b></td></tr>
                <tr><td class="muted">( − ) Biaya jabatan 5% (maks Rp 6.000.000)</td><td class="num" style="color:var(--rose-600)">(<?= rupiah($calc['biaya_jabatan']) ?>)</td></tr>
                <tr><td class="muted">( − ) Iuran pensiun &amp; JHT karyawan</td><td class="num" style="color:var(--rose-600)">(<?= rupiah($calc['pension']) ?>)</td></tr>
                <tr><td><b>Penghasilan neto</b></td><td class="num"><b><?= rupiah($calc['netto']) ?></b></td></tr>
                <tr><td class="muted">( − ) PTKP <?= e($calc['tax_status']) ?></td><td class="num" style="color:var(--rose-600)">(<?= rupiah($calc['ptkp']) ?>)</td></tr>
                <tr><td><b>Penghasilan Kena Pajak (dibulatkan ke bawah ribuan)</b></td><td class="num"><b><?= rupiah($calc['pkp']) ?></b></td></tr>
                </tbody>
                <tfoot>
                <tr><th>PPh 21 terutang setahun</th><th class="num"><?= rupiah($calc['tax_due']) ?></th></tr>
                </tfoot>
            </table>
        </div>

        <div class="card__head" style="border-top:1px solid var(--line)">
            <div><h2>Lapisan Tarif</h2><div class="sub">PKP <?= rupiah($calc['pkp']) ?> dibagi per lapisan.</div></div>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Lapisan PKP</th><th class="num">Tarif</th><th class="num">Dasar (Rp)</th><th class="num">Pajak (Rp)</th></tr></thead>
                <tbody>
                <?php foreach ($calc['layers'] as $layer): ?>
                    <tr>
                        <td class="small"><?= e($layer['range']) ?></td>
                        <td class="num small"><?= (int) round((float) $layer['rate']) ?>%</td>
                        <td class="num small"><?= rupiah($layer['base']) ?></td>
                        <td class="num small"><b><?= rupiah($layer['tax']) ?></b></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$calc['layers']): ?>
                    <tr><td colspan="4" class="table-empty"><?= icon('check') ?>PKP nihil — tidak ada pajak terutang.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head"><div><h2>Bukti Potong 1721-A1</h2>
                <div class="sub">Diserahkan ke karyawan sebagai kredit pajak.</div></div></div>
            <div class="card__body flex-col gap-12">
                <?php if ($slip): ?>
                    <div class="flex-between">
                        <span class="muted small">Nomor</span>
                        <b class="mono"><?= e($slip['slip_number']) ?></b>
                    </div>
                    <div class="flex-between">
                        <span class="muted small">Diterbitkan</span>
                        <b><?= tanggal($slip['issued_at'], 'd M Y H:i') ?></b>
                    </div>
                    <div class="flex-between">
                        <span class="muted small">Status</span>
                        <span class="badge badge-teal"><?= icon('check') ?> Sah</span>
                    </div>
                    <?php if (!empty($slip['note'])): ?>
                        <div class="muted small" style="border-left:2px solid var(--line);padding-left:10px"><?= e($slip['note']) ?></div>
                    <?php endif; ?>
                    <a class="btn btn-primary" href="<?= url('tax/' . (int) $employee['id'] . '/pdf?year=' . (int) $year . '&download=1') ?>"><?= icon('download') ?> Unduh PDF</a>
                    <a class="btn btn-outline" href="<?= url('tax/' . (int) $employee['id'] . '/pdf?year=' . (int) $year) ?>" target="_blank"><?= icon('eye') ?> Pratinjau</a>
                    <?php if ($canManage): ?>
                        <form method="post" action="<?= url('tax/' . (int) $employee['id'] . '/revoke') ?>"
                              data-confirm="Tarik kembali bukti potong <?= e($slip['slip_number']) ?>?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="year" value="<?= (int) $year ?>">
                            <button class="btn btn-sm btn-ghost" type="submit" style="color:var(--rose-600)"><?= icon('x') ?> Tarik Bukti Potong</button>
                        </form>
                    <?php endif; ?>
                <?php elseif ($canManage): ?>
                    <p class="muted small" style="margin:0">
                        Bukti potong belum diterbitkan. Angka di bawah akan dibekukan sebagai snapshot saat terbit —
                        revisi payroll setelahnya tidak mengubah dokumen.
                    </p>
                    <form method="post" action="<?= url('tax/' . (int) $employee['id'] . '/issue') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="year" value="<?= (int) $year ?>">
                        <div class="field">
                            <label class="label" for="note">Catatan (opsional)</label>
                            <input class="input" id="note" name="note" maxlength="200" placeholder="mis. diserahkan langsung ke karyawan">
                        </div>
                        <button class="btn btn-primary" style="margin-top:10px" type="submit"><?= icon('receipt') ?> Terbitkan 1721-A1</button>
                    </form>
                <?php else: ?>
                    <p class="muted small" style="margin:0">Bukti potong belum diterbitkan oleh HR/Finance.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Identitas Pajak</h2>
                <div class="sub">Data acuan pemotongan.</div></div></div>
            <div class="list-rows">
                <div class="list-row"><span class="muted small">NPWP</span><b class="mono"><?= e($calc['npwp'] ?: '—') ?></b></div>
                <div class="list-row"><span class="muted small">Status PTKP</span><b><?= e($calc['tax_status']) ?></b></div>
                <div class="list-row"><span class="muted small">Departemen</span><b><?= e($calc['department_name'] ?: '—') ?></b></div>
                <div class="list-row"><span class="muted small">Unit</span><b><?= e($calc['unit_name'] ?: '—') ?></b></div>
                <div class="list-row"><span class="muted small">BPJS Kesehatan</span><b class="mono"><?= e($employee['bpjs_kesehatan_no'] ?: '—') ?></b></div>
                <div class="list-row"><span class="muted small">BPJS TK</span><b class="mono"><?= e($employee['bpjs_tk_no'] ?: '—') ?></b></div>
            </div>
            <?php if (empty($calc['npwp'])): ?>
                <div class="card__foot small" style="padding:10px 16px;color:var(--amber-600)">
                    <?= icon('alert') ?> NPWP belum diisi — tarif PPh 21 dapat menjadi 20% lebih tinggi pada pelaporan.
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head"><div><h2>Rincian Penghasilan Bruto</h2>
            <div class="sub">Komponen tunjangan yang menjadi objek pajak.</div></div></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Komponen</th><th class="num">Setahun (Rp)</th></tr></thead>
                <tbody>
                <tr><td>Gaji pokok</td><td class="num"><?= rupiah($calc['basic_total']) ?></td></tr>
                <tr><td>Tunjangan tetap / komponen</td><td class="num"><?= rupiah($breakdown['tunjangan']) ?></td></tr>
                <tr><td>Lembur</td><td class="num"><?= rupiah($breakdown['lembur']) ?></td></tr>
                <tr><td>THR / bonus</td><td class="num"><?= rupiah($breakdown['thr']) ?></td></tr>
                <tr><td>Tunjangan lain</td><td class="num"><?= rupiah($breakdown['lain']) ?></td></tr>
                </tbody>
                <tfoot>
                <tr><th>Total bruto</th><th class="num"><?= rupiah($calc['gross']) ?></th></tr>
                </tfoot>
            </table>
        </div>
        <div class="card__foot small muted" style="padding:10px 16px">
            Reimbursement/klaim sebesar <?= rupiah($calc['claim_total']) ?> tidak dihitung sebagai penghasilan (natura penggantian biaya).
        </div>
    </section>

    <section class="card">
        <div class="card__head"><div><h2>Riwayat Bulanan</h2>
            <div class="sub"><?= number_id(count($months)) ?> slip pada tahun <?= (int) $year ?>.</div></div></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Masa</th><th class="num">Bruto</th><th class="num">PPh</th><th class="num">Net</th></tr></thead>
                <tbody>
                <?php foreach ($months as $month): ?>
                    <tr>
                        <td class="small"><b><?= e(bulan_id((int) $month['month'])) ?></b></td>
                        <td class="num small"><?= rupiah($month['gross_salary']) ?></td>
                        <td class="num small"><?= rupiah($month['pph_amount']) ?></td>
                        <td class="num small"><?= rupiah($month['net_salary']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
