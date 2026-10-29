<?php
/**
 * Reset sistem: bersihkan data ujicoba, pertahankan akun administrator.
 * @var array $admin, $overview, $labels, $lastReset, $errors, $old
 * @var string $uploadHuman
 */
$op = (int) $overview['operational'];
$master = (int) $overview['master'];
$otherEmployees = (int) $overview['employees_other'];
$uploadCount = (int) $overview['uploads'];
$selected = static fn (string $key, bool $default = true): string =>
    array_key_exists($key, $old) ? (!empty($old[$key]) ? 'checked' : '') : ($default ? 'checked' : '');
?>
<div class="page-head">
    <div>
        <h1>Reset Sistem</h1>
        <div class="sub">Hapus seluruh jejak data ujicoba/demo dan mulai dari kondisi bersih, tanpa kehilangan akses administrator.</div>
    </div>
    <div class="page-head__actions">
        <span class="badge badge-rose"><?= icon('alert') ?> Tindakan permanen</span>
    </div>
</div>

<?php if ($lastReset['at']): ?>
    <div class="alert alert-info" style="margin-bottom:14px">
        <?= icon('info') ?>
        <div>Reset terakhir: <b><?= tanggal($lastReset['at'], 'd M Y H:i') ?></b><?= $lastReset['by'] ? ' oleh ' . e($lastReset['by']) : '' ?>.</div>
    </div>
<?php endif; ?>

<div class="reset-hero">
    <div class="reset-hero__icon"><?= icon('shield') ?></div>
    <div class="reset-hero__body">
        <b>Akun administrator yang dipertahankan</b>
        <div class="flex" style="gap:10px;margin-top:6px;align-items:center;flex-wrap:wrap">
            <span class="avatar" style="width:34px;height:34px;font-size:11.6px;background:<?= avatar_color($admin['full_name']) ?>"><?= e(initials($admin['full_name'])) ?></span>
            <div>
                <div style="font-weight:650"><?= e($admin['full_name']) ?> <span class="muted mono small"><?= e($admin['employee_code']) ?></span></div>
                <div class="muted small"><?= e($admin['email']) ?> · <?= e(role_label($admin['role'])) ?></div>
            </div>
        </div>
        <div class="small muted" style="margin-top:8px">
            Login, kata sandi, dan peran akun ini <b>tidak diubah</b>. Seluruh karyawan lain akan dihapus bila opsi terkait dipilih.
        </div>
    </div>
</div>

<div class="mini-stat-grid" style="margin-top:14px">
    <div class="mini-stat">
        <span class="k">Data Operasional</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($op) ?></span>
        <span class="s">baris pada <?= number_id($overview['operational_tables']) ?> tabel</span>
    </div>
    <div class="mini-stat">
        <span class="k">Master Data</span>
        <span class="v" style="color:var(--indigo-600)"><?= number_id($master) ?></span>
        <span class="s">baris pada <?= number_id($overview['master_tables']) ?> tabel</span>
    </div>
    <div class="mini-stat">
        <span class="k">Karyawan Lain</span>
        <span class="v"><?= number_id($otherEmployees) ?></span>
        <span class="s">dari total <?= number_id($overview['employees']) ?> akun</span>
    </div>
    <div class="mini-stat">
        <span class="k">Berkas Unggahan</span>
        <span class="v"><?= number_id($uploadCount) ?></span>
        <span class="s"><?= e($uploadHuman ?? '-') ?> di storage/uploads</span>
    </div>
</div>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Jalankan Reset</h2>
                <div class="sub">Pilih kelompok data yang akan dihapus. Tindakan ini tidak dapat dibatalkan.</div></div>
        </div>
        <form method="post" action="<?= url('system/reset') ?>" data-confirm="Reset sistem sekarang? Seluruh data yang dipilih akan dihapus permanen.">
            <?= csrf_field() ?>
            <div class="card__body flex-col gap-12">
                <label class="reset-option">
                    <input type="checkbox" name="data" value="1" <?= $selected('data') ?>>
                    <div>
                        <b>Data operasional <span class="muted small">(<?= number_id($op) ?> baris)</span></b>
                        <div class="small muted">Presensi, lembur, cuti &amp; saldo, jadwal, payroll &amp; slip, klaim, penilaian kinerja, pelatihan, rekrutmen, offboarding (termasuk BAST &amp; register aset), pengumuman, notifikasi, dan log aktivitas.</div>
                    </div>
                </label>

                <label class="reset-option">
                    <input type="checkbox" name="master" value="1" <?= $selected('master') ?>>
                    <div>
                        <b>Master data organisasi <span class="muted small">(<?= number_id($master) ?> baris)</span></b>
                        <div class="small muted">Unit, departemen, posisi, shift, komponen gaji, jenis cuti, kategori klaim, katalog pelatihan, indikator penilaian, hari libur, dan sequence nomor ID.</div>
                    </div>
                </label>

                <label class="reset-option">
                    <input type="checkbox" name="employees" value="1" <?= $selected('employees') ?>>
                    <div>
                        <b>Seluruh karyawan kecuali administrator <span class="muted small">(<?= number_id($otherEmployees) ?> akun)</span></b>
                        <div class="small muted">Menghapus akun &amp; profil karyawan lain. Riwayat terkait juga dibersihkan lewat opsi data operasional.</div>
                    </div>
                </label>

                <label class="reset-option">
                    <input type="checkbox" name="uploads" value="1" <?= $selected('uploads') ?>>
                    <div>
                        <b>Berkas unggahan <span class="muted small">(<?= number_id($uploadCount) ?> file)</span></b>
                        <div class="small muted">Foto karyawan, bukti klaim, sertifikat pelatihan, CV pelamar, dan lampiran pengumuman di <span class="mono">storage/uploads</span>.</div>
                    </div>
                </label>

                <div class="alert alert-warning" style="margin:4px 0 0">
                    <?= icon('alert') ?>
                    <div class="small">
                        <b>Yang selalu dipertahankan:</b> akun administrator (login, sandi, peran), tabel pengaturan
                        (identitas perusahaan, kebijakan presensi/payroll/BPJS, versi skema), dan data badan hukum perusahaan.
                    </div>
                </div>

                <div class="form-grid two" style="margin-top:4px">
                    <div class="field">
                        <label>Ketik <b class="mono">RESET</b> untuk konfirmasi <span class="req">*</span></label>
                        <input class="input" name="confirm" required autocomplete="off" placeholder="RESET"
                               value="<?= e($old['confirm'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label>Kata sandi akun Anda <span class="req">*</span></label>
                        <input class="input" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                        <span class="hint">Diverifikasi ulang untuk mencegah tindakan tidak sengaja.</span>
                    </div>
                </div>
            </div>
            <div class="card__foot" style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
                <span class="small muted">Disarankan membuat cadangan basis data sebelum reset.</span>
                <button class="btn btn-danger" type="submit"><?= icon('trash') ?> Reset Sekarang</button>
            </div>
        </form>
    </section>

    <div class="flex-col gap-12">
        <section class="card">
            <div class="card__head"><div><h2>Sisa Data per Tabel</h2>
                <div class="sub">Hanya tabel yang berisi data.</div></div></div>
            <div class="card__body" style="padding:0;max-height:420px;overflow:auto">
                <?php
                $filled = array_filter($overview['rows'], static fn ($n) => (int) $n > 0);
                arsort($filled);
                ?>
                <?php foreach ($filled as $table => $count): ?>
                    <div class="flex-between small" style="padding:7px 16px;border-bottom:1px solid #eef2f7">
                        <span class="muted"><?= e($labels[$table] ?? $table) ?>
                            <span class="mono" style="font-size:9.4px;color:var(--muted-2)"><?= e($table) ?></span></span>
                        <b><?= number_id($count) ?></b>
                    </div>
                <?php endforeach; ?>
                <?php if (!$filled): ?>
                    <div class="empty-state" style="padding:22px">
                        <div class="empty-icon"><?= icon('database') ?></div>
                        <h3>Basis data sudah bersih</h3>
                        <p>Tidak ada data operasional maupun master yang tersisa.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Setelah Reset</h2></div></div>
            <div class="card__body flex-col gap-8 small muted">
                <div><b>1.</b> Anda tetap masuk dengan akun administrator yang sama.</div>
                <div><b>2.</b> Buat ulang unit, departemen, dan posisi di <b>Unit Organisasi</b>.</div>
                <div><b>3.</b> Atur shift, jenis cuti, komponen gaji, lalu tambahkan karyawan.</div>
                <div><b>4.</b> Pengaturan perusahaan, presensi, payroll, dan BPJS tidak berubah.</div>
                <div style="margin-top:6px"><a class="btn btn-sm btn-outline" href="<?= url('organisation') ?>"><?= icon('building') ?> Buka Unit Organisasi</a></div>
            </div>
        </section>
    </div>
</div>
