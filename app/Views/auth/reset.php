<?php
/**
 * Atur ulang kata sandi dengan token sekali pakai.
 * @var bool $valid
 * @var string $token, $email
 */
$companyName = setting('company_name', 'PT Asia Raya Group');
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
            <span class="tag-navy">Atur Ulang Kata Sandi</span>
            <span class="tag-soft">Token Sekali Pakai</span>
            <span class="auth-card__secure"><?= icon('shield') ?> Bcrypt</span>
        </div>

        <?php if (!$valid): ?>
            <h2>Tautan tidak berlaku</h2>
            <p class="sub">
                Tautan reset sudah dipakai, kedaluwarsa (lebih dari 60 menit), atau tidak valid.
                Silakan minta tautan baru dari halaman lupa kata sandi.
            </p>
            <a class="btn btn-primary btn-block auth-submit" style="margin-top:16px" href="<?= url('forgot-password') ?>">
                <?= icon('refresh') ?> Minta Tautan Baru
            </a>
            <p class="hint" style="margin-top:12px">
                Bila masalah berlanjut, hubungi HR/IT Helpdesk untuk reset manual oleh administrator.
            </p>
        <?php else: ?>
            <h2>Buat kata sandi baru</h2>
            <p class="sub">
                Akun <b><?= e($email) ?></b>. Gunakan minimal 8 karakter dan hindari kata sandi yang pernah dipakai.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-top:14px"><?= icon('alert') ?><div><?= e($error) ?></div></div>
            <?php endif; ?>

            <form method="post" action="<?= url('reset-password') ?>" style="margin-top:16px">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="auth-field">
                    <div class="auth-label-row">
                        <label class="small" for="password" style="font-weight:650;color:var(--ink-2)">
                            Kata Sandi Baru <span style="color:var(--rose-500)">*</span>
                        </label>
                        <span>Min. 8 karakter</span>
                    </div>
                    <div class="input-group" style="margin-top:5px">
                        <?= icon('lock') ?>
                        <input class="input" type="password" id="password" name="password" required minlength="8"
                               autocomplete="new-password" placeholder="Kata sandi baru" style="padding-left:38px;padding-right:44px">
                        <span class="suffix">
                            <button type="button" class="icon-btn" style="width:30px;height:30px"
                                    data-toggle-password="#password" aria-label="Tampilkan kata sandi"><?= icon('eye') ?></button>
                        </span>
                    </div>
                </div>

                <div class="auth-field">
                    <div class="auth-label-row">
                        <label class="small" for="password_confirmation" style="font-weight:650;color:var(--ink-2)">
                            Ulangi Kata Sandi <span style="color:var(--rose-500)">*</span>
                        </label>
                    </div>
                    <div class="input-group" style="margin-top:5px">
                        <?= icon('lock') ?>
                        <input class="input" type="password" id="password_confirmation" name="password_confirmation"
                               required minlength="8" autocomplete="new-password" placeholder="Ketik ulang kata sandi"
                               style="padding-left:38px">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block auth-submit" style="margin-top:16px">
                    Simpan Kata Sandi <?= icon('check') ?>
                </button>
            </form>

            <div class="auth-help">
                <?= icon('info') ?>
                <div>Setelah tersimpan, seluruh sesi "ingat saya" pada perangkat lain otomatis dicabut
                    dan Anda diminta masuk kembali dengan kata sandi baru.</div>
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
