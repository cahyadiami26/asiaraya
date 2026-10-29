<?php /** Profil karyawan (self service). */ ?>
<div class="page-head">
    <div>
        <h1>Profil Saya</h1>
        <div class="sub">Data kepegawaian, kontak, dan keamanan akun HRIS Anda.</div>
    </div>
    <div class="page-head__actions">
        <?= role_badge($employee['role']) ?>
        <?= flag_badge($employee['flag']) ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:340px minmax(0,1fr);gap:16px" class="dash-grid">
    <section class="card">
        <div class="card__body" style="text-align:center;padding-top:26px">
            <div class="avatar xl" style="margin:0 auto 12px;background:<?= e(avatar_color($employee['email'])) ?>">
                <?= e(initials($employee['full_name'])) ?>
            </div>
            <h2 style="font-size:17px"><?= e($employee['full_name']) ?></h2>
            <div class="small muted mono" style="margin-top:2px"><?= e($employee['employee_code']) ?></div>
            <div class="mt-12 flex" style="justify-content:center;gap:6px;flex-wrap:wrap">
                <span class="badge badge-slate"><?= e($employee['employment_status']) ?></span>
                <span class="badge badge-teal"><?= e($employee['position_name'] ?? '-') ?></span>
            </div>
            <div class="divider"></div>
            <div class="flex-col gap-12" style="text-align:left">
                <div class="flex gap-8 small"><span class="muted"><?= icon('building', 'ico') ?></span> <?= e($employee['unit_name'] ?? '-') ?></div>
                <div class="flex gap-8 small"><span class="muted"><?= icon('layers', 'ico') ?></span> <?= e($employee['department_name'] ?? '-') ?></div>
                <div class="flex gap-8 small"><span class="muted"><?= icon('user', 'ico') ?></span> Atasan: <?= e($employee['manager_name'] ?? 'Tidak ada') ?></div>
                <div class="flex gap-8 small"><span class="muted"><?= icon('mail', 'ico') ?></span> <?= e($employee['email']) ?></div>
                <div class="flex gap-8 small"><span class="muted"><?= icon('phone', 'ico') ?></span> <?= e($employee['phone'] ?: '-') ?></div>
                <div class="flex gap-8 small"><span class="muted"><?= icon('calendar', 'ico') ?></span> Bergabung <?= tanggal($employee['join_date']) ?></div>
            </div>
        </div>
    </section>

    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head"><div><h2>Data Pribadi &amp; Kontak</h2>
                <div class="sub">Email perusahaan dan data kontrak hanya dapat diubah oleh HR.</div></div></div>
            <div class="card__body">
                <form method="post" action="<?= url('me/profile') ?>">
                    <?= csrf_field() ?>
                    <div class="form-grid">
                        <div class="field">
                            <label>Nama Lengkap</label>
                            <input class="input" value="<?= e($employee['full_name']) ?>" disabled>
                        </div>
                        <div class="field">
                            <label>Email Perusahaan</label>
                            <input class="input" name="email" value="<?= e($employee['email']) ?>" readonly>
                            <span class="hint">Terhubung dengan Google Workspace untuk login.</span>
                        </div>
                        <div class="field">
                            <label>Nomor HP <span class="req">*</span></label>
                            <input class="input" name="phone" value="<?= e(old('phone', $employee['phone'])) ?>" required>
                            <?php if (!empty($errors['phone'])): ?><span class="err"><?= e($errors['phone']) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label>Status Pajak</label>
                            <input class="input" value="<?= e($employee['tax_status']) ?>" disabled>
                        </div>
                        <div class="field full">
                            <label>Alamat Domisili <span class="req">*</span></label>
                            <textarea class="textarea" name="address" required><?= e(old('address', $employee['address'])) ?></textarea>
                        </div>
                        <div class="field">
                            <label>Kata Sandi Saat Ini</label>
                            <input class="input" type="password" name="password_current" autocomplete="current-password"
                                   placeholder="Wajib diisi bila mengganti kata sandi">
                            <?php if (!empty($errors['password_current'])): ?><span class="err"><?= e($errors['password_current']) ?></span><?php endif; ?>
                            <span class="hint">Diverifikasi sebelum kata sandi baru disimpan.</span>
                        </div>
                        <div class="field">
                            <label>Kata Sandi Baru</label>
                            <input class="input" type="password" name="password" autocomplete="new-password" placeholder="Kosongkan bila tidak diubah">
                            <span class="hint">Minimal 8 karakter. Mengganti kata sandi akan mengeluarkan perangkat lain yang memakai "ingat saya".</span>
                        </div>
                        <div class="field">
                            <label>Konfirmasi Kata Sandi</label>
                            <input class="input" type="password" name="password_confirmation">
                            <?php if (!empty($errors['password'])): ?><span class="err"><?= e($errors['password']) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div class="form-actions mt-16">
                        <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Data Payroll &amp; Bank</h2></div>
            <div class="card__body">
                <div class="form-grid three">
                    <div><div class="small muted">Gaji Pokok</div><b><?= rupiah($employee['basic_salary']) ?></b></div>
                    <div><div class="small muted">Bank</div><b><?= e($employee['bank_name'] ?: '-') ?></b></div>
                    <div><div class="small muted">No. Rekening</div><b class="mono"><?= e($employee['bank_account_number'] ?: '-') ?></b></div>
                    <div><div class="small muted">NPWP</div><b class="mono small"><?= e($employee['npwp'] ?: '-') ?></b></div>
                    <div><div class="small muted">BPJS Kesehatan</div><b class="mono small"><?= e($employee['bpjs_kesehatan_no'] ?: '-') ?></b></div>
                    <div><div class="small muted">BPJS Ketenagakerjaan</div><b class="mono small"><?= e($employee['bpjs_tk_no'] ?: '-') ?></b></div>
                </div>
            </div>
        </section>
    </div>
</div>
<style>@media (max-width:1100px){.dash-grid{grid-template-columns:1fr !important}}</style>
