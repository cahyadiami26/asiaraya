<?php
/**
 * Lupa kata sandi — minta tautan reset ke email perusahaan.
 * @var bool $sent
 * @var string $mail, $demoLink
 */
$companyName = setting('company_name', 'PT Asia Raya Group');
$support = setting('support_email', 'helpdesk@asiaraya.co.id');
$error = \App\Core\Session::pull('login_error');
?>
<header class="auth-top">
    <div class="auth-brand">
        <div class="brand-mark"><?= icon('layers') ?></div>
        <div class="brand-text">
            <b><?= e($companyName) ?></b>
            <span>Enterprise HRIS Gateway</span>
        </div>
    </div>
    <div class="auth-top__right">
        <a class="btn btn-sm btn-ghost" href="<?= url('login') ?>"><?= icon('arrow-left') ?> Kembali ke login</a>
    </div>
</header>

<main class="auth-center">
    <section class="auth-card">
        <div class="auth-card__tags">
            <span class="tag-navy">Pemulihan Akun</span>
            <span class="tag-soft">Aman &amp; Terverifikasi</span>
            <span class="auth-card__secure"><?= icon('lock') ?> Enkripsi</span>
        </div>

        <?php if ($sent): ?>
            <h2>Periksa email Anda</h2>
            <p class="sub">
                Bila <b><?= e($mail ?: 'alamat tersebut') ?></b> terdaftar sebagai karyawan aktif,
                tautan pengaturan kata sandi sudah dibuat dan berlaku <b>60 menit</b>.
                Satu tautan hanya dapat dipakai sekali.
            </p>

            <?php if ($demoLink): ?>
                <div class="alert alert-info" style="margin-top:14px">
                    <?= icon('info') ?>
                    <div>
                        <b>Mode demo:</b> tidak ada SMTP terpasang.
                        <a href="<?= e($demoLink) ?>">Buka tautan reset sekarang</a>.
                    </div>
                </div>
            <?php endif; ?>

            <div class="auth-security-row" style="margin-top:16px">
                <div><?= icon('clock') ?><b>60 menit</b><span>Masa berlaku</span></div>
                <div><?= icon('key') ?><b>Sekali pakai</b><span>Token unik</span></div>
                <div><?= icon('shield') ?><b>Tercatat</b><span>Audit trail</span></div>
            </div>

            <p class="hint" style="margin-top:14px;line-height:1.65">
                Tautan tidak masuk? Periksa folder spam, atau minta ulang tautan setelah satu menit.
                Untuk kendala akun, hubungi <b><?= e($support) ?></b>.
            </p>

            <a class="btn btn-outline btn-block" style="margin-top:14px" href="<?= url('forgot-password') ?>">
                <?= icon('refresh') ?> Kirim ulang tautan
            </a>
        <?php else: ?>
            <h2>Lupa kata sandi?</h2>
            <p class="sub">
                Masukkan email perusahaan yang terdaftar. Kami akan membuat tautan
                pengaturan ulang kata sandi yang berlaku 60 menit.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-top:14px"><?= icon('alert') ?><div><?= e($error) ?></div></div>
            <?php endif; ?>

            <form method="post" action="<?= url('forgot-password') ?>" style="margin-top:16px">
                <?= csrf_field() ?>
                <div class="auth-field">
                    <div class="auth-label-row">
                        <label class="small" for="email" style="font-weight:650;color:var(--ink-2)">
                            Email Perusahaan <span style="color:var(--rose-500)">*</span>
                        </label>
                        <span>Terhubung dengan NIK karyawan</span>
                    </div>
                    <div class="input-group" style="margin-top:5px">
                        <?= icon('mail') ?>
                        <input class="input" type="email" id="email" name="email" required autocomplete="email"
                               placeholder="nama@asiaraya.co.id" style="padding-left:38px">
                    </div>
                    <div class="hint" style="margin-top:4px;font-size:10.8px;color:var(--muted)">
                        Hanya email karyawan berstatus aktif yang diproses. Demi keamanan, hasilnya tidak membedakan email terdaftar.
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block auth-submit" style="margin-top:16px">
                    Kirim Tautan Reset <?= icon('arrow-right') ?>
                </button>
            </form>

            <div class="auth-help">
                <?= icon('info') ?>
                <div>Butuh bantuan cepat? Hubungi IT Support &amp; HR Helpdesk di
                    <b><?= e($support) ?></b>. Tim akan memverifikasi identitas Anda sebelum mengatur ulang akses.</div>
            </div>
        <?php endif; ?>

        <div class="auth-footer">
            <span class="server"><span class="dot"></span> Server: Production Cluster</span>
            <span>Versi v<?= e(APP_VERSION) ?></span>
        </div>
    </section>
</main>

<div class="auth-legal">
    <span>© <?= date('Y') ?> <?= e($companyName) ?>. Seluruh hak dilindungi.</span>
    <span>Kebijakan Privasi</span>
    <span>Syarat Penggunaan</span>
</div>
