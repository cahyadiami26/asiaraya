<?php
/**
 * Pengaturan perusahaan.
 * @var array $company, $units, $stats, $errors, $old
 */
$val = static function (string $key, mixed $default = '') use ($old, $company) {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    if (array_key_exists($key, $company)) {
        return $company[$key];
    }
    return $default;
};
$err = static fn (string $key): string => isset($errors[$key]) ? '<span class="err">' . e($errors[$key]) . '</span>' : '';
?>
<div class="page-head">
    <div>
        <h1>Pengaturan Perusahaan</h1>
        <div class="sub">Identitas legal, kontak, dan preferensi regional yang dipakai pada slip gaji, PDF, dan halaman login.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('organisation') ?>"><?= icon('building') ?> Unit Organisasi</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Business Unit</span>
        <span class="v"><?= number_id($stats['units']) ?></span>
        <span class="s">Entitas aktif</span>
    </div>
    <div class="mini-stat">
        <span class="k">Departemen</span>
        <span class="v"><?= number_id($stats['departments']) ?></span>
        <span class="s">Struktur organisasi</span>
    </div>
    <div class="mini-stat">
        <span class="k">Karyawan Aktif</span>
        <span class="v"><?= number_id($stats['employees']) ?></span>
        <span class="s">Jiwa</span>
    </div>
    <div class="mini-stat">
        <span class="k">Zona Waktu</span>
        <span class="v" style="font-size:14px"><?= e($val('timezone', 'Asia/Jakarta')) ?></span>
        <span class="s">Semua cap waktu presensi</span>
    </div>
</div>

<form method="post" action="<?= url('company') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="split-grid">
        <div class="flex-col gap-16">

            <section class="card">
                <div class="card__head"><div><h2>Identitas Perusahaan</h2>
                    <div class="sub">Nama legal akan tercetak pada dokumen resmi dan slip gaji.</div></div></div>
                <div class="card__body">
                    <div class="form-grid">
                        <div class="field">
                            <label>Nama Perusahaan <span class="req">*</span></label>
                            <input class="input" name="name" value="<?= e($val('name', 'PT Asia Raya Group')) ?>" required maxlength="150">
                            <?= $err('name') ?>
                        </div>
                        <div class="field">
                            <label>Nama Legal</label>
                            <input class="input" name="legal_name" value="<?= e($val('legal_name')) ?>" maxlength="200" placeholder="PT Asia Raya Group">
                        </div>
                        <div class="field">
                            <label>NPWP Perusahaan</label>
                            <input class="input mono" name="npwp" value="<?= e($val('npwp')) ?>" maxlength="40">
                        </div>
                        <div class="field">
                            <label>Email Perusahaan <span class="req">*</span></label>
                            <input class="input" type="email" name="email" value="<?= e($val('email', 'hr@asiaraya.co.id')) ?>" required maxlength="150">
                            <?= $err('email') ?>
                        </div>
                        <div class="field">
                            <label>Telepon</label>
                            <input class="input" name="phone" value="<?= e($val('phone')) ?>" maxlength="50" placeholder="021-5xxxxxx">
                        </div>
                        <div class="field">
                            <label>Website</label>
                            <input class="input" name="website" value="<?= e($val('website')) ?>" maxlength="150" placeholder="https://asiaraya.co.id">
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><div><h2>Alamat Kantor Pusat</h2></div></div>
                <div class="card__body">
                    <div class="form-grid">
                        <div class="field full">
                            <label>Alamat</label>
                            <input class="input" name="address" value="<?= e($val('address')) ?>" maxlength="255" placeholder="Jl. Jenderal Sudirman No. 21">
                        </div>
                        <div class="field">
                            <label>Kota</label>
                            <input class="input" name="city" value="<?= e($val('city')) ?>" maxlength="100" placeholder="Jakarta Selatan">
                        </div>
                        <div class="field">
                            <label>Provinsi</label>
                            <input class="input" name="province" value="<?= e($val('province')) ?>" maxlength="100" placeholder="DKI Jakarta">
                        </div>
                        <div class="field">
                            <label>Kode Pos</label>
                            <input class="input mono" name="postal_code" value="<?= e($val('postal_code')) ?>" maxlength="10" placeholder="12190">
                        </div>
                        <div class="field">
                            <label>Zona Waktu</label>
                            <select class="select" name="timezone">
                                <?php foreach (['Asia/Jakarta' => 'WIB — Asia/Jakarta', 'Asia/Makassar' => 'WITA — Asia/Makassar', 'Asia/Jayapura' => 'WIT — Asia/Jayapura'] as $tz => $label): ?>
                                    <option value="<?= e($tz) ?>" <?= $val('timezone', 'Asia/Jakarta') === $tz ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Mata Uang</label>
                            <select class="select" name="currency">
                                <?php foreach (['IDR' => 'IDR — Rupiah', 'USD' => 'USD — US Dollar'] as $code => $label): ?>
                                    <option value="<?= e($code) ?>" <?= $val('currency', 'IDR') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </section>

            <div class="card">
                <div class="card__body">
                    <div class="form-actions">
                        <a class="btn btn-ghost" href="<?= url('dashboard') ?>">Batal</a>
                        <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan Pengaturan</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-col gap-16">
            <section class="card">
                <div class="card__head"><div><h2>Logo Perusahaan</h2>
                    <div class="sub">Tampil di sidebar, halaman login, dan kop slip gaji.</div></div></div>
                <div class="card__body">
                    <div style="display:grid;place-items:center;padding:8px 0 14px">
                        <?php if (!empty($company['logo'])): ?>
                            <img src="<?= url('media/company/' . basename($company['logo'])) ?>" alt="Logo perusahaan"
                                 style="max-width:170px;max-height:110px;object-fit:contain">
                        <?php else: ?>
                            <div class="brand-mark" style="width:64px;height:64px;border-radius:16px">
                                <?= icon('building') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="field">
                        <label>Unggah Logo Baru</label>
                        <input class="input file-input" type="file" name="logo" accept="image/jpeg,image/png,image/webp">
                        <span class="hint">JPG/PNG/WEBP, maksimal 4 MB. Disarankan latar transparan.</span>
                    </div>
                    <?php if (!empty($company['logo'])): ?>
                        <label class="checkbox small" style="margin-top:10px"><input type="checkbox" name="remove_logo" value="1"> Hapus logo saat ini</label>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><div><h2>Ringkasan Entitas</h2></div></div>
                <div class="list-rows">
                    <?php foreach ($units as $unit): ?>
                        <div class="list-row">
                            <span class="avatar" style="background:<?= e(avatar_color($unit['code'])) ?>"><?= e(substr($unit['code'], 0, 2)) ?></span>
                            <div class="list-row__body">
                                <b><?= e($unit['name']) ?></b>
                                <span><?= e($unit['city'] ?: $unit['type']) ?> · <?= (int) $unit['employee_count'] ?> karyawan</span>
                            </div>
                            <?= (int) $unit['is_active'] === 1 ? '<span class="badge badge-teal">Aktif</span>' : '<span class="badge badge-slate">Off</span>' ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$units): ?>
                        <div class="table-empty">Belum ada business unit.</div>
                    <?php endif; ?>
                </div>
                <div class="card__foot" style="padding:12px 16px">
                    <a class="link-quiet small" href="<?= url('organisation?tab=units') ?>">Kelola business unit <?= icon('arrow-right', 'ico') ?></a>
                </div>
            </section>

            <section class="card">
                <div class="card__body">
                    <div class="small" style="font-weight:700;margin-bottom:6px">Digunakan oleh</div>
                    <ul class="small muted" style="padding-left:16px;line-height:1.75">
                        <li>Kop dan footer PDF slip gaji serta laporan payroll.</li>
                        <li>Halaman login dan metadata PWA.</li>
                        <li>Perhitungan tanggal cut-off dan pembayaran payroll per unit.</li>
                    </ul>
                </div>
            </section>
        </div>
    </div>
</form>
