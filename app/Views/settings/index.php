<?php
/**
 * Pengaturan HR & Payroll — tab: perusahaan, payroll, PPh 21, BPJS.
 * @var string $tab
 * @var array $tabs, $company, $payroll, $sample, $stats, $preview
 * @var array $old, $errors
 */
$val = static function (string $key, mixed $default = '') use ($old): mixed {
    return array_key_exists($key, $old) ? $old[$key] : $default;
};
$tabUrl = static fn (string $key): string => url('settings?tab=' . $key);
$pphMethods = [
    'TER'         => ['Tarif Efektif Rata-rata (TER)', 'Sesuai PP 58/2023 — persentase berjenjang atas bruto bulanan.'],
    'FLAT'        => ['Tarif Flat', 'Satu persentase tetap dari dasar pengenaan. Cocok untuk simulasi internal.'],
    'PROGRESSIVE' => ['Progresif Tahunan', 'Proyeksi setahun: bruto × 12 − biaya jabatan 5% − PTKP, lalu lapisan UU HPP.'],
];
?>
<div class="page-head">
    <div>
        <h1>Pengaturan HR &amp; Payroll</h1>
        <div class="sub">Identitas perusahaan, siklus payroll, tarif PPh 21, dan iuran BPJS.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('components') ?>"><?= icon('layers') ?> Komponen Gaji</a>
    </div>
</div>

<div class="tabs" style="margin-bottom:16px">
    <?php foreach ($tabs as $key => $label): ?>
        <a class="tab <?= $tab === $key ? 'active' : '' ?>" href="<?= e($tabUrl($key)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'integration'): ?>
    <div class="split-grid">
        <section class="card">
            <div class="card__head"><div><h2>Integrasi &amp; Login</h2>
                <div class="sub">Google Sign-In untuk karyawan — akun dicocokkan dengan field email karyawan.</div></div></div>
            <form class="card__body" method="post" action="<?= url('settings/integration') ?>">
                <?= csrf_field() ?>
                <div class="flex-col gap-12">
                    <input type="hidden" name="google_login_enabled" value="0">
                    <label class="checkbox"><input type="checkbox" name="google_login_enabled" value="1" <?= $company['google_login_enabled'] ? 'checked' : '' ?>> <span>Izinkan tombol <b>Masuk dengan Google</b> pada halaman login</span></label>
                    <div class="field">
                        <label>Google OAuth Client ID</label>
                        <input class="input" name="google_client_id" maxlength="200" value="<?= e($val('google_client_id', $company['google_client_id'])) ?>" placeholder="1234567890-abc.apps.googleusercontent.com">
                        <span class="hint">Kosongkan untuk memakai nilai dari <span class="mono">app/config/config.php</span>.</span>
                    </div>
                    <div class="field">
                        <label>Authorized Redirect URI</label>
                        <input class="input mono" readonly value="<?= e($company['google_redirect']) ?>">
                        <span class="hint">Daftarkan URI ini pada Google Cloud Console → Credentials → OAuth client.</span>
                    </div>
                </div>
                <div class="form-actions" style="margin-top:14px">
                    <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan Integrasi</button>
                </div>
            </form>
        </section>

        <div class="flex-col gap-16">
            <section class="card">
                <div class="card__head"><div><h2>Identitas Perusahaan</h2>
                    <div class="sub">Dipakai pada kop slip gaji &amp; dokumen resmi.</div></div></div>
                <div class="list-rows">
                    <div class="list-row">
                        <div class="list-row__body">
                            <b><?= e($company['name']) ?></b>
                            <span><?= e($company['legal']) ?></span>
                            <span><?= e($company['address'] ?: 'Alamat belum diisi') ?></span>
                        </div>
                    </div>
                    <div class="list-row">
                        <div class="list-row__body"><b>Kontak</b>
                            <span><?= e($company['email']) ?> · <?= e($company['phone'] ?: '-') ?></span>
                            <span>NPWP <?= e($company['npwp'] ?: '-') ?></span>
                        </div>
                    </div>
                    <div class="list-row">
                        <div class="list-row__body"><b>Zona Waktu</b><span><?= e($company['timezone']) ?></span></div>
                        <span class="badge badge-slate"><?= e($company['logo'] !== '' ? 'Logo tersedia' : 'Tanpa logo') ?></span>
                    </div>
                </div>
                <div class="card__foot" style="padding:10px 16px">
                    <a class="btn btn-sm btn-outline btn-block" href="<?= url('company') ?>"><?= icon('building') ?> Ubah Identitas Perusahaan</a>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><div><h2>Status Login</h2></div></div>
                <div class="card__body small muted" style="line-height:1.6">
                    <p>Google Sign-In: <b><?= $company['google_login_enabled'] ? 'aktif' : 'nonaktif' ?></b><?= $company['google_client_id'] !== '' ? ' · Client ID terpasang' : ' · Client ID belum diisi' ?>.</p>
                    <p>Login email &amp; password tetap tersedia sebagai cadangan untuk seluruh peran.</p>
                </div>
            </section>
        </div>
    </div>

<?php elseif ($tab === 'payroll'): ?>
    <div class="split-grid">
        <section class="card">
            <div class="card__head"><div><h2>Siklus Payroll</h2>
                <div class="sub">Menentukan periode potong gaji dan tanggal pembayaran.</div></div></div>
            <form class="card__body" method="post" action="<?= url('settings/payroll') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_tab" value="payroll">
                <div class="form-grid">
                    <div class="field">
                        <label>Tanggal Cutoff <span class="req">*</span></label>
                        <input class="input" type="number" name="cutoff_day" min="1" max="31" value="<?= (int) $val('cutoff_day', $payroll['cutoff_day']) ?>">
                        <span class="hint">Absensi setelah tanggal ini masuk periode berikutnya.</span>
                    </div>
                    <div class="field">
                        <label>Tanggal Pembayaran <span class="req">*</span></label>
                        <input class="input" type="number" name="pay_day" min="1" max="31" value="<?= (int) $val('pay_day', $payroll['pay_day']) ?>">
                    </div>
                    <div class="field full">
                        <input type="hidden" name="auto_cutoff" value="0">
                        <label class="checkbox"><input type="checkbox" name="auto_cutoff" value="1" <?= $payroll['auto_cutoff'] ? 'checked' : '' ?>> <span>Jalankan cutoff otomatis saat periode payroll dibuka</span></label>
                    </div>
                </div>
                <div class="form-actions" style="margin-top:14px">
                    <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan Siklus</button>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Estimasi Biaya Bulanan</h2>
                <div class="sub">Dihitung dari 5 karyawan bergaji tertinggi sebagai sampel.</div></div></div>
            <div class="list-rows">
                <?php foreach ($sample as $row): ?>
                    <div class="list-row">
                        <div class="list-row__body">
                            <b><?= e($row['full_name']) ?></b>
                            <span>Bruto <?= rupiah($row['gross']) ?> · take home <?= rupiah($row['net']) ?></span>
                        </div>
                        <span class="badge badge-slate"><?= rupiah($row['employer_cost']) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (!$sample): ?>
                    <div class="empty-state"><div class="empty-icon"><?= icon('wallet') ?></div><h3>Belum ada karyawan aktif</h3></div>
                <?php endif; ?>
            </div>
        </section>
    </div>

<?php elseif ($tab === 'pph'): ?>
    <div class="split-grid">
        <section class="card">
            <div class="card__head"><div><h2>PPh 21</h2>
                <div class="sub">Pemotongan pajak penghasilan karyawan tiap periode.</div></div></div>
            <form class="card__body" method="post" action="<?= url('settings/payroll') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_tab" value="pph">
                <input type="hidden" name="cutoff_day" value="<?= (int) $payroll['cutoff_day'] ?>">
                <input type="hidden" name="pay_day" value="<?= (int) $payroll['pay_day'] ?>">
                <input type="hidden" name="auto_cutoff" value="<?= $payroll['auto_cutoff'] ? 1 : 0 ?>">
                <input type="hidden" name="kes_enabled" value="<?= $payroll['bpjs_kes_on'] ? 1 : 0 ?>">
                <input type="hidden" name="tk_enabled" value="<?= $payroll['bpjs_tk_on'] ? 1 : 0 ?>">
                <input type="hidden" name="kes_company" value="<?= e($payroll['kes_company']) ?>">
                <input type="hidden" name="kes_employee" value="<?= e($payroll['kes_employee']) ?>">
                <input type="hidden" name="kes_max" value="<?= e($payroll['kes_max']) ?>">
                <input type="hidden" name="jht_company" value="<?= e($payroll['jht_company']) ?>">
                <input type="hidden" name="jht_employee" value="<?= e($payroll['jht_employee']) ?>">
                <input type="hidden" name="jp_company" value="<?= e($payroll['jp_company']) ?>">
                <input type="hidden" name="jp_employee" value="<?= e($payroll['jp_employee']) ?>">
                <input type="hidden" name="jp_max" value="<?= e($payroll['jp_max']) ?>">
                <input type="hidden" name="jkk" value="<?= e($payroll['jkk']) ?>">
                <input type="hidden" name="jkm" value="<?= e($payroll['jkm']) ?>">
                <div class="flex-col gap-12">
                    <input type="hidden" name="pph_enabled" value="0">
                    <label class="checkbox"><input type="checkbox" name="pph_enabled" value="1" <?= $payroll['pph_enabled'] ? 'checked' : '' ?>> <span>Aktifkan pemotongan PPh 21 pada payroll &amp; simulasi</span></label>
                    <div class="field">
                        <label>Metode Perhitungan</label>
                        <select class="select" name="pph_method" id="pph-method">
                            <?php foreach ($pphMethods as $key => [$label, $description]): ?>
                                <option value="<?= $key ?>" <?= $payroll['pph_method'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint">
                            <?php foreach ($pphMethods as $key => [$label, $description]): ?>
                                <span data-pph-hint="<?= $key ?>" <?= $payroll['pph_method'] === $key ? '' : 'hidden' ?>><?= e($description) ?></span>
                            <?php endforeach; ?>
                        </span>
                    </div>
                    <div class="field">
                        <label>Tarif Flat / Batas Atas (%)</label>
                        <input class="input" type="number" step="0.01" min="0" max="35" name="pph_rate" value="<?= e($payroll['pph_rate']) ?>">
                        <span class="hint">Dipakai hanya pada metode <b>FLAT</b>.</span>
                    </div>
                </div>
                <div class="form-actions" style="margin-top:14px">
                    <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan PPh 21</button>
                </div>
            </form>
        </section>

        <div class="flex-col gap-16">
            <section class="card">
                <div class="card__head"><div><h2>Contoh Perhitungan</h2>
                    <div class="sub">Dua sampel: gaji terendah &amp; tertinggi.</div></div></div>
                <div class="list-rows">
                    <?php foreach (['low' => 'Gaji terendah', 'high' => 'Gaji tertinggi'] as $key => $label): ?>
                        <?php $row = $preview[$key] ?? null; ?>
                        <?php if (!$row): continue; endif; ?>
                        <div class="list-row" style="align-items:flex-start">
                            <div class="list-row__body">
                                <b><?= e($label) ?> · <?= e($row['employee']['full_name']) ?></b>
                                <span>Bruto <?= rupiah($row['gross']) ?> · PTKP <?= e($row['pph']['status']) ?></span>
                                <span>PPh 21 sebulan: <b><?= rupiah($row['pph']['monthly']) ?></b> (<?= number_id($row['pph']['effective_rate'], 2) ?>%)</span>
                            </div>
                            <span class="badge badge-indigo"><?= e($row['pph']['method']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card__foot small muted" style="padding:10px 16px">
                    PTKP TK/0 <?= rupiah(54000000) ?> · K/0 <?= rupiah(58500000) ?> · K/1 <?= rupiah(63000000) ?> · K/2 <?= rupiah(67500000) ?> · K/3 <?= rupiah(72000000) ?>.
                </div>
            </section>

            <section class="card">
                <div class="card__head"><div><h2>Lapisan Tarif</h2>
                    <div class="sub">UU HPP — dipakai metode progresif.</div></div></div>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Lapisan PKP</th><th class="num">Tarif</th></tr></thead>
                        <tbody>
                        <tr><td class="small">s.d. Rp 60 juta</td><td class="num small">5%</td></tr>
                        <tr><td class="small">Rp 60 – 250 juta</td><td class="num small">15%</td></tr>
                        <tr><td class="small">Rp 250 – 500 juta</td><td class="num small">25%</td></tr>
                        <tr><td class="small">Rp 500 juta – 5 miliar</td><td class="num small">30%</td></tr>
                        <tr><td class="small">di atas Rp 5 miliar</td><td class="num small">35%</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

<?php elseif ($tab === 'bpjs'): ?>
    <div class="split-grid">
        <section class="card">
            <div class="card__head"><div><h2>Iuran BPJS</h2>
                <div class="sub">Persentase iuran karyawan dan perusahaan beserta plafon upah.</div></div></div>
            <form class="card__body" method="post" action="<?= url('settings/payroll') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_tab" value="bpjs">
                <input type="hidden" name="cutoff_day" value="<?= (int) $payroll['cutoff_day'] ?>">
                <input type="hidden" name="pay_day" value="<?= (int) $payroll['pay_day'] ?>">
                <input type="hidden" name="auto_cutoff" value="<?= $payroll['auto_cutoff'] ? 1 : 0 ?>">
                <input type="hidden" name="pph_enabled" value="<?= $payroll['pph_enabled'] ? 1 : 0 ?>">
                <input type="hidden" name="pph_method" value="<?= e($payroll['pph_method']) ?>">
                <input type="hidden" name="pph_rate" value="<?= e($payroll['pph_rate']) ?>">

                <h3 class="section-label">BPJS Kesehatan</h3>
                <input type="hidden" name="kes_enabled" value="0">
                <label class="checkbox" style="margin-bottom:10px"><input type="checkbox" name="kes_enabled" value="1" <?= $payroll['bpjs_kes_on'] ? 'checked' : '' ?>> <span>Aktifkan BPJS Kesehatan</span></label>
                <div class="form-grid three">
                    <div class="field">
                        <label>Karyawan (%)</label>
                        <input class="input" type="number" step="0.01" min="0" max="5" name="kes_employee" value="<?= e($payroll['kes_employee']) ?>">
                    </div>
                    <div class="field">
                        <label>Perusahaan (%)</label>
                        <input class="input" type="number" step="0.01" min="0" max="10" name="kes_company" value="<?= e($payroll['kes_company']) ?>">
                    </div>
                    <div class="field">
                        <label>Plafon Upah</label>
                        <input class="input" type="number" min="0" step="100000" name="kes_max" value="<?= e((float) $payroll['kes_max']) ?>">
                    </div>
                </div>

                <h3 class="section-label" style="margin-top:16px">BPJS Ketenagakerjaan</h3>
                <input type="hidden" name="tk_enabled" value="0">
                <label class="checkbox" style="margin-bottom:10px"><input type="checkbox" name="tk_enabled" value="1" <?= $payroll['bpjs_tk_on'] ? 'checked' : '' ?>> <span>Aktifkan JHT, JP, JKK, dan JKM</span></label>
                <div class="form-grid three">
                    <div class="field">
                        <label>JHT Karyawan (%)</label>
                        <input class="input" type="number" step="0.01" min="0" max="10" name="jht_employee" value="<?= e($payroll['jht_employee']) ?>">
                    </div>
                    <div class="field">
                        <label>JHT Perusahaan (%)</label>
                        <input class="input" type="number" step="0.01" min="0" max="10" name="jht_company" value="<?= e($payroll['jht_company']) ?>">
                    </div>
                    <div class="field">
                        <label>JKK Perusahaan (%)</label>
                        <input class="input" type="number" step="0.01" min="0" max="5" name="jkk" value="<?= e($payroll['jkk']) ?>">
                    </div>
                    <div class="field">
                        <label>JP Karyawan (%)</label>
                        <input class="input" type="number" step="0.01" min="0" max="5" name="jp_employee" value="<?= e($payroll['jp_employee']) ?>">
                    </div>
                    <div class="field">
                        <label>JP Perusahaan (%)</label>
                        <input class="input" type="number" step="0.01" min="0" max="10" name="jp_company" value="<?= e($payroll['jp_company']) ?>">
                    </div>
                    <div class="field">
                        <label>JKM Perusahaan (%)</label>
                        <input class="input" type="number" step="0.01" min="0" max="5" name="jkm" value="<?= e($payroll['jkm']) ?>">
                    </div>
                    <div class="field">
                        <label>Plafon Upah JP</label>
                        <input class="input" type="number" min="0" step="100000" name="jp_max" value="<?= e((float) $payroll['jp_max']) ?>">
                    </div>
                </div>
                <div class="form-actions" style="margin-top:16px">
                    <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan BPJS</button>
                </div>
            </form>
        </section>

        <div class="flex-col gap-16">
            <section class="card">
                <div class="card__head"><div><h2>Dampak pada Take-Home</h2>
                    <div class="sub">Contoh karyawan gaji tertinggi.</div></div></div>
                <?php $high = $preview['high'] ?? null; ?>
                <?php if ($high): ?>
                    <div class="card__body">
                        <div class="sim-line"><span><?= e($high['employee']['full_name']) ?> · gaji pokok</span><b><?= rupiah($high['basic']) ?></b></div>
                        <?php foreach ($high['bpjs']['items'] as $item): ?>
                            <?php if ($item['side'] !== 'EMPLOYEE') { continue; } ?>
                            <div class="sim-line"><span><?= e($item['label']) ?> (<?= number_id($item['rate'], 2) ?>%)</span><b>− <?= rupiah($item['amount']) ?></b></div>
                        <?php endforeach; ?>
                        <div class="sim-line"><span>PPh 21 (<?= e($high['pph']['method']) ?>)</span><b>− <?= rupiah($high['pph']['monthly']) ?></b></div>
                        <div class="sim-line sim-total sim-net"><span>Take home</span><b><?= rupiah($high['net']) ?></b></div>
                        <div class="sim-line sim-total"><span>Iuran perusahaan</span><b><?= rupiah($high['bpjs']['company_total']) ?></b></div>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><div class="empty-icon"><?= icon('shield') ?></div><h3>Belum ada sampel</h3></div>
                <?php endif; ?>
            </section>

            <section class="card">
                <div class="card__head"><div><h2>Referensi Regulasi</h2></div></div>
                <div class="card__body small muted" style="line-height:1.65">
                    <p>BPJS Kesehatan: 5% dari upah (4% perusahaan, 1% karyawan) dengan plafon upah Rp 12 juta.</p>
                    <p>BPJS Ketenagakerjaan: JHT 5,7% (3,7% + 2%), JP 3% (2% + 1%) dengan plafon upah Rp 10 juta, JKK 0,24%, JKM 0,3%.</p>
                    <p>PPh 21: PMK 168/2023 &amp; PP 58/2023 (TER bulanan), UU HPP untuk lapisan progresif.</p>
                </div>
            </section>
        </div>
    </div>
<?php elseif ($tab === 'advanced'): ?>
    <div class="split-grid">
        <section class="card">
            <div class="card__head"><div><h2>Penandatangan Laporan</h2>
                <div class="sub">Nama yang tercetak pada blok tanda tangan PDF laporan.</div></div></div>
            <form class="card__body" method="post" action="<?= url('settings/advanced') ?>">
                <?= csrf_field() ?>
                <div class="flex-col gap-12">
                    <div class="field">
                        <label>Penandatangan HR</label>
                        <input class="input" name="report_signer_hr" maxlength="120" value="<?= e($val('report_signer_hr', $advanced['signer_hr'])) ?>" placeholder="Contoh: Rani Puspita — HR Manager">
                        <span class="hint">Kosongkan untuk memakai label bawaan "HR Department".</span>
                    </div>
                    <div class="field">
                        <label>Penandatangan Finance</label>
                        <input class="input" name="report_signer_fin" maxlength="120" value="<?= e($val('report_signer_fin', $advanced['signer_fin'])) ?>" placeholder="Contoh: Budi Hartono — Finance Director">
                        <span class="hint">Kosongkan untuk memakai label bawaan "Finance Division".</span>
                    </div>
                    <div class="field">
                        <label>Retensi Log Audit (hari)</label>
                        <input class="input" type="number" min="30" max="1825" name="audit_retention_days" value="<?= e($val('audit_retention_days', (string) $advanced['retention'])) ?>">
                        <span class="hint">Antara 30–1825 hari. Tombol "Bersihkan" di halaman Log Aktivitas menghapus data lebih tua dari batas ini.</span>
                    </div>
                    <div class="field">
                        <label>Batas Usia Klaim Biaya (hari)</label>
                        <input class="input" type="number" min="7" max="365" name="claim_max_age_days" value="<?= e($val('claim_max_age_days', (string) $advanced['claim_age'])) ?>">
                        <span class="hint">Karyawan hanya dapat mengajukan klaim untuk transaksi maksimal N hari ke belakang (7–365 hari).</span>
                    </div>
                </div>
                <div class="form-actions" style="margin-top:14px">
                    <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan Pengaturan</button>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Status Log Audit</h2>
                <div class="sub">Ringkasan jejak aktivitas yang tersimpan.</div></div></div>
            <div class="card__body">
                <div class="flex-between small" style="padding:9px 0;border-bottom:1px solid var(--line)"><span class="muted">Total baris log</span><b><?= number_id($advanced['log_total']) ?></b></div>
                <div class="flex-between small" style="padding:9px 0;border-bottom:1px solid var(--line)"><span class="muted">Log terlama</span><b><?= $advanced['log_oldest'] ? tanggal($advanced['log_oldest'], 'd M Y H:i') : '—' ?></b></div>
                <div class="flex-between small" style="padding:9px 0"><span class="muted">Batas retensi aktif</span><b><?= (int) $advanced['retention'] ?> hari</b></div>
                <div class="flex gap-8" style="margin-top:14px">
                    <a class="btn btn-outline btn-sm" href="<?= url('logs') ?>"><?= icon('activity') ?> Buka Log Aktivitas</a>
                    <a class="btn btn-ghost btn-sm" href="<?= url('logs/export') ?>"><?= icon('download') ?> Export CSV</a>
                </div>
                <p class="small muted" style="margin-top:14px;line-height:1.6">Log mencatat login, perubahan data karyawan, approval cuti/lembur, perhitungan payroll, ekspor laporan, dan perubahan pengaturan. Hanya peran HR yang dapat melihat dan membersihkan log.</p>
            </div>
        </section>
    </div>
<?php endif; ?>

<script>
(function () {
    var method = document.getElementById('pph-method');
    if (!method) { return; }
    function sync() {
        document.querySelectorAll('[data-pph-hint]').forEach(function (node) {
            node.hidden = node.getAttribute('data-pph-hint') !== method.value;
        });
    }
    method.addEventListener('change', sync);
    sync();
})();
</script>
