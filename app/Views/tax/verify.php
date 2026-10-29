<?php
/**
 * Verifikasi publik bukti potong 1721-A1 (hasil pindai QR) — Batch 20.
 * @var array|null $detail
 * @var array|null $slip
 * @var int $year
 */
$valid = $detail !== null && $slip !== null;
$calc = $valid ? $detail['computed'] : null;
$masked = '';
if ($valid) {
    $parts = preg_split('/\s+/', trim((string) $detail['employee']['full_name'])) ?: [];
    $masked = trim((string) ($parts[0] ?? '') . ' ' . (isset($parts[1]) ? mb_substr((string) $parts[1], 0, 1) . '.' : ''));
}
?>
<main class="auth-main" style="max-width:600px;margin:0 auto;padding:34px 18px 48px">
    <div class="flex" style="gap:10px;align-items:center;margin-bottom:18px">
        <div class="brand-mark"><?= icon('shield') ?></div>
        <div class="brand-text">
            <b><?= e((string) setting('company_name', 'PT Asia Raya Group')) ?></b>
            <span>Verifikasi Bukti Potong PPh 21</span>
        </div>
    </div>

    <?php if ($valid): ?>
        <section class="card">
            <div class="card__head" style="border-bottom:1px solid var(--line)">
                <div>
                    <h2 style="display:flex;align-items:center;gap:8px;color:var(--teal-700)">
                        <?= icon('check') ?> Dokumen Sah
                    </h2>
                    <div class="sub">Bukti potong 1721-A1 ini diterbitkan resmi melalui sistem HRIS.</div>
                </div>
            </div>
            <div class="card__body flex-col gap-8">
                <div class="sim-line"><span>Nomor Bukti Potong</span><b class="mono"><?= e($slip['slip_number']) ?></b></div>
                <div class="sim-line"><span>Penerima Penghasilan</span><b><?= e($masked) ?> · <?= e($detail['employee']['employee_code']) ?></b></div>
                <div class="sim-line"><span>NPWP</span><b class="mono"><?= e($calc['npwp'] ?: '—') ?></b></div>
                <div class="sim-line"><span>Tahun Pajak</span><b><?= (int) $year ?></b></div>
                <div class="sim-line"><span>Tanggal Terbit</span><b><?= tanggal((string) $slip['issued_at']) ?></b></div>
                <div class="sim-line"><span>Status PTKP</span><b><?= e($calc['tax_status']) ?></b></div>
                <div class="sim-line"><span>Penghasilan Bruto</span><b><?= rupiah($calc['gross']) ?></b></div>
                <div class="sim-line sim-total sim-net"><span>PPh 21 Terutang</span><b><?= rupiah($calc['tax_due']) ?></b></div>
            </div>
            <div class="card__foot small muted" style="padding:10px 16px">
                Halaman ini hanya menampilkan ringkasan. Rincian lengkap ada pada PDF bukti potong yang dipegang karyawan.
            </div>
        </section>
    <?php else: ?>
        <section class="card">
            <div class="card__body">
                <div class="empty-state">
                    <div class="empty-icon" style="color:var(--rose-600)"><?= icon('alert') ?></div>
                    <h3>Dokumen tidak valid</h3>
                    <p>Bukti potong untuk tahun pajak <?= (int) $year ?> belum diterbitkan atau tautan tidak cocok dengan data mana pun.</p>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="center small muted" style="margin-top:16px">
        <a href="<?= url('login') ?>">Masuk ke HRIS</a>
    </div>
</main>
