<?php
/**
 * Halaman verifikasi publik slip gaji (hasil pindai QR).
 * @var array|null $item
 * @var array $components
 * @var string $company
 */
$valid = $item !== null;
$net = $valid ? (float) $item['net_salary'] : 0.0;
$masked = '';
if ($valid) {
    $parts = preg_split('/\s+/', trim((string) $item['employee_name'])) ?: [];
    $masked = trim((string) ($parts[0] ?? '') . ' ' . (isset($parts[1]) ? mb_substr((string) $parts[1], 0, 1) . '.' : ''));
}
?>
<main class="auth-main" style="max-width:560px;margin:0 auto;padding:34px 18px 48px">
    <div class="flex" style="gap:10px;align-items:center;margin-bottom:18px">
        <div class="brand-mark"><?= icon('shield') ?></div>
        <div class="brand-text">
            <b><?= e($company) ?></b>
            <span>Verifikasi Dokumen Payroll</span>
        </div>
    </div>

    <?php if ($valid): ?>
        <section class="card">
            <div class="card__head" style="border-bottom:1px solid var(--line)">
                <div>
                    <h2 style="display:flex;align-items:center;gap:8px;color:var(--teal-700)">
                        <?= icon('check') ?> Dokumen Sah
                    </h2>
                    <div class="sub">Slip gaji ini diterbitkan oleh sistem HRIS dan terverifikasi.</div>
                </div>
            </div>
            <div class="card__body flex-col gap-8">
                <div class="sim-line"><span>No. Slip</span><b class="mono"><?= e($item['slip_number'] ?? '-') ?></b></div>
                <div class="sim-line"><span>Karyawan</span><b><?= e($masked) ?> · <?= e($item['employee_code']) ?></b></div>
                <div class="sim-line"><span>Unit / Departemen</span><b><?= e($item['unit_name'] ?? '-') ?> · <?= e($item['department_name'] ?: '-') ?></b></div>
                <div class="sim-line"><span>Periode</span><b><?= e(bulan_id((int) $item['period_month'])) ?> <?= (int) $item['period_year'] ?></b></div>
                <div class="sim-line"><span>Tanggal Bayar</span><b><?= tanggal($item['pay_date']) ?></b></div>
                <div class="sim-line sim-total sim-net"><span>Gaji Bersih</span><b><?= rupiah($net) ?></b></div>
                <div class="flex" style="gap:6px;margin-top:8px">
                    <?= status_badge($item['period_status']) ?>
                    <?= status_badge($item['payment_status']) ?>
                </div>
            </div>
            <div class="card__foot small muted" style="padding:10px 16px">
                Halaman ini hanya menampilkan ringkasan. Rincian lengkap ada pada PDF slip yang dipegang karyawan.
            </div>
        </section>
    <?php else: ?>
        <section class="card">
            <div class="card__body">
                <div class="empty-state">
                    <div class="empty-icon" style="color:var(--rose-600)"><?= icon('alert') ?></div>
                    <h3>Dokumen tidak valid</h3>
                    <p>Tautan verifikasi tidak cocok dengan data slip mana pun. Dokumen mungkin telah diubah atau bukan diterbitkan sistem ini.</p>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="center small muted" style="margin-top:16px">
        <a href="<?= url('login') ?>">Masuk ke HRIS</a>
    </div>
</main>
