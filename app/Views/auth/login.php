<?php
/**
 * Secure sign-in — reference layout: brand gate on the left, credential card right.
 * @var array $company
 * @var array $stats
 * @var bool $googleReady
 * @var array $demoAccounts
 */
$oldIdentifier = $_SESSION['_old_identifier'] ?? '';
unset($_SESSION['_old_identifier']);
$activeUnitLabel = setting('company_city', 'Jakarta');
?>
<header class="auth-top">
    <div class="auth-brand">
        <div class="brand-mark"><?= icon('layers') ?></div>
        <div class="brand-text">
            <b><?= e($company['name']) ?></b>
            <span>Enterprise HRIS Gateway</span>
        </div>
    </div>
    <div class="auth-top__right">
        <span class="tag-soft"><?= e($activeUnitLabel) ?> Cluster</span>
        <span class="live-pill"><span class="dot"></span> Produksi</span>
    </div>
</header>

<main class="auth-main">
    <section class="auth-hero">
        <span class="auth-badge"><span class="dot" style="background:#4b7d63"></span> Core HR Gateway Active</span>
        <h1>Sistem Informasi Sumber Daya Manusia Terpadu</h1>
        <p class="lede">
            Infrastruktur digital sentral pengelolaan <?= $stats['employees'] ? number_id($stats['employees']) : '14.800' ?>+ talenta multisektor
            <?= e($company['name']) ?>. Menjamin akurasi kalkulasi remunerasi, presensi biometrik geofence,
            dan kepatuhan audit ketenagakerjaan secara real-time.
        </p>

        <div class="auth-visual">
            <img src="<?= asset('img/office-hero.jpg') ?>" alt="Pusat operasional digital Asia Raya">
            <div class="auth-visual__overlay">
                <span class="kicker">Pusat Operasional Digital</span>
                <b>Menara Asia Raya, Mega Kuningan Jakarta</b>
            </div>
            <div class="auth-visual__card">
                <div class="t">Login HRIS</div>
                <div class="v">Employee ID · 100642</div>
                <div class="bar"><i></i></div>
            </div>
        </div>

        <div class="auth-facts">
            <div class="auth-fact">
                <div class="k">Entitas Bisnis</div>
                <div class="v"><?= (int) $stats['units'] ?> Unit</div>
                <div class="s"><?= icon('check') ?> Terkonsolidasi</div>
            </div>
            <div class="auth-fact">
                <div class="k">SLA Payroll</div>
                <div class="v">99.98%</div>
                <div class="s"><?= icon('trending') ?> Akurasi H-0</div>
            </div>
            <div class="auth-fact">
                <div class="k">Audit Trail</div>
                <div class="v">Zero Fail</div>
                <div class="s"><?= icon('shield') ?> ISO 27001</div>
            </div>
        </div>

        <div class="auth-security">
            <?= icon('shield') ?>
            <div>
                <b>Protokol Keamanan Akses Internal</b>
                <span>Akses ke portal ini dipantau melalui pencatatan IP statis perusahaan, penandaan biometrik,
                serta enkripsi end-to-end berstandar perbankan komersial.</span>
            </div>
        </div>
    </section>

    <section class="auth-card">
        <div class="auth-card__tags">
            <span class="tag-navy">HRIS Enterprise Gateway</span>
            <span class="tag-soft"><?= e($activeUnitLabel) ?> Cluster</span>
            <span class="auth-card__secure"><span class="dot"></span> Secured</span>
        </div>

        <h2>Masuk ke Akun HRIS Anda</h2>
        <p class="sub">Sistem Terpadu Manajemen SDM, Presensi Geofence, &amp; Payroll <?= e($company['name']) ?>.</p>

        <div class="flash-stack" style="margin-top:14px">
            <?= \App\Core\View::partial('partials.flash-inline') ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error" role="alert">
                    <?= icon('alert') ?>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <a class="google-btn" href="<?= url('login/google') ?>">
            <span class="google-btn__icon">
                <svg width="22" height="22" viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.5l6.7-6.7C35.6 2.5 30.2 0 24 0 14.6 0 6.4 5.4 2.5 13.3l7.8 6.1C12.2 13.5 17.6 9.5 24 9.5Z"/>
                    <path fill="#4285F4" d="M46.1 24.5c0-1.6-.1-2.8-.4-4.1H24v8.4h12.5c-.3 2.1-1.6 5.2-4.6 7.3l7.6 5.9c4.5-4.2 6.6-10.3 6.6-17.5Z"/>
                    <path fill="#FBBC05" d="M10.3 28.6A14.6 14.6 0 0 1 9.5 24c0-1.6.3-3.2.8-4.6l-7.8-6.1A24 24 0 0 0 0 24c0 3.9.9 7.5 2.5 10.7l7.8-6.1Z"/>
                    <path fill="#34A853" d="M24 48c6.2 0 11.4-2 15.2-5.6l-7.6-5.9c-2 1.4-4.7 2.4-7.6 2.4-6.4 0-11.8-4-13.7-9.9l-7.8 6.1C6.4 42.6 14.6 48 24 48Z"/>
                </svg>
            </span>
            <span class="google-btn__text">
                <b>Masuk dengan Google Workspace</b>
                <span>Gunakan alamat surel resmi <?= e(explode('@', $company['email'])[1] ?? 'asiaraya.co.id') ?></span>
            </span>
            <?= icon('arrow-right') ?>
        </a>

        <div class="auth-divider"><span>Atau gunakan kredensial portal</span></div>

        <form method="post" action="<?= url('login') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="auth-field">
                <div class="auth-label-row">
                    <label class="small" for="identifier" style="font-weight:650;color:var(--ink-2)">
                        NIK / Username / Email Perusahaan <span style="color:var(--rose-500)">*</span>
                    </label>
                    <span>Format: ARG-XXXX-XXXX</span>
                </div>
                <div class="input-group" style="margin-top:5px">
                    <?= icon('user') ?>
                    <input class="input" type="text" id="identifier" name="identifier" required autocomplete="username"
                           value="<?= e($oldIdentifier) ?>" placeholder="Contoh: ARG-JKT-0421 atau email@asiaraya.co.id"
                           style="padding-left:38px">
                </div>
                <div class="hint" style="margin-top:4px;font-size:10.8px;color:var(--muted)">
                    Karyawan organik dan kontrak aktif menggunakan NIK induk terbitan Divisi HRD.
                </div>
            </div>

            <div class="auth-field">
                <div class="auth-label-row">
                    <label class="small" for="password" style="font-weight:650;color:var(--ink-2)">
                        Kata Sandi Karyawan <span style="color:var(--rose-500)">*</span>
                    </label>
                    <a href="<?= url('forgot-password') ?>" style="font-size:10.8px;font-weight:600">Lupa Kata Sandi?</a>
                </div>
                <div class="input-group" style="margin-top:5px">
                    <?= icon('lock') ?>
                    <input class="input" type="password" id="password" name="password" required autocomplete="current-password"
                           placeholder="Masukkan kata sandi terdaftar" style="padding-left:38px;padding-right:44px">
                    <span class="suffix">
                        <button type="button" class="icon-btn" style="width:30px;height:30px"
                                data-toggle-password="#password" aria-label="Tampilkan kata sandi"><?= icon('eye') ?></button>
                    </span>
                </div>
                <div class="flex-between" style="margin-top:4px">
                    <span class="hint" style="font-size:10.8px;color:var(--muted)">Min. 8 karakter alfanumerik kombinasi.</span>
                    <span class="hint" style="font-size:10.8px;color:var(--muted);display:flex;align-items:center;gap:4px"><?= icon('shield', 'ico') ?> Enkripsi Bcrypt</span>
                </div>
            </div>

            <div class="auth-meta">
                <label class="checkbox">
                    <input type="checkbox" name="remember" value="1">
                    <span>Ingat perangkat ini selama 30 hari</span>
                </label>
                <span class="small muted" style="display:flex;align-items:center;gap:5px" title="Sesi berakhir otomatis setelah 2 jam tanpa aktivitas"><?= icon('clock', 'ico') ?> Masa aktif 8 jam · idle 2 jam</span>
            </div>

            <button type="submit" class="btn btn-primary btn-block auth-submit">
                Masuk ke Sistem HRIS <?= icon('arrow-right') ?>
            </button>
        </form>

        <div class="auth-security-row">
            <div><?= icon('lock') ?><b>SSL 256-bit</b><span>Terenkripsi</span></div>
            <div><?= icon('shield') ?><b>ISO 27001</b><span>Tersertifikasi</span></div>
            <div><?= icon('key') ?><b>Brute-Force</b><span>Terproteksi</span></div>
        </div>

        <div class="auth-help">
            <?= icon('info') ?>
            <div>Kendala akses atau akun terkunci? Hubungi Divisi IT Support &amp; HR Helpdesk via
                <b>ext. 1042</b> atau surel <b><?= e(setting('support_email', 'helpdesk@asiaraya.co.id')) ?></b>.</div>
        </div>

        <?php if ($demoAccounts): ?>
            <details class="demo-box">
                <summary>Akun demo untuk pengujian (klik untuk melihat)</summary>
                <div class="demo-box__body">
                    <?php foreach ($demoAccounts as $acc): ?>
                        <button type="button" class="demo-cred"
                                data-cred-user="<?= e($acc['email']) ?>">
                            <span class="demo-cred__role"><?= e(role_label($acc['role'])) ?></span>
                            <span class="demo-cred__mail mono"><?= e($acc['email']) ?></span>
                            <span class="demo-cred__pass">Password123!</span>
                        </button>
                    <?php endforeach; ?>
                    <p class="hint" style="margin-top:6px">Klik salah satu akun untuk mengisi formulir otomatis. Hapus data demo sebelum produksi.</p>
                </div>
            </details>
            <script>
            document.querySelectorAll('[data-cred-user]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.getElementById('identifier').value = btn.getAttribute('data-cred-user');
                    document.getElementById('password').value = 'Password123!';
                    document.getElementById('password').focus();
                });
            });
            </script>
        <?php endif; ?>

        <div class="auth-footer">
            <span class="server"><span class="dot"></span> Server: Production Cluster <?= e($activeUnitLabel) ?></span>
            <span>Versi v<?= e(APP_VERSION) ?> (Build <?= e(str_replace('.', '.', APP_BUILD)) ?>)</span>
        </div>
    </section>
</main>

<div class="auth-legal">
    <span>© <?= date('Y') ?> <?= e($company['name']) ?>. Seluruh hak dilindungi.</span>
    <span>Kebijakan Privasi</span>
    <span>Syarat Penggunaan</span>
    <span>ISO 27001:2022 Certified</span>
</div>
