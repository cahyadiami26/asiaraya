<?php
/**
 * Jadwal sesi pelatihan (HR).
 * @var array $sessions, $filters, $stats, $programs, $paginator, $statuses, $statusTones, $modes, $categories, $categoryTones
 * @var array|null $editing
 * @var array $errors, $old
 * @var callable $pageLink
 */
$val = static function (string $key, string $default = '') use ($old, $editing): string {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if ($editing !== null && array_key_exists($key, $editing) && $editing[$key] !== null) {
        return (string) $editing[$key];
    }
    return $default;
};
$isEdit = $editing !== null;
$today = date('Y-m-d');
$sessionTone = static fn (string $status): string => $statusTones[$status] ?? 'slate';
$categoryChip = static function (string $category) use ($categories, $categoryTones): string {
    return '<span class="chip chip-' . ($categoryTones[$category] ?? 'slate') . '">' . e($categories[$category] ?? $category) . '</span>';
};
?>
<div class="page-head">
    <div>
        <h1>Pelatihan &amp; Sertifikasi</h1>
        <div class="sub">Kelola jadwal pelatihan, kuota peserta, kehadiran, serta sertifikat karyawan.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('trainings/catalog') ?>"><?= icon('layers') ?> Katalog Pelatihan</a>
        <a class="btn btn-primary" href="#form-sesi"><?= icon('plus') ?> Sesi Baru</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Program Aktif</span>
        <span class="v"><?= number_id($stats['programs']) ?></span>
        <span class="s"><?= number_id($stats['sessions']) ?> sesi dibuka / berlangsung</span>
    </div>
    <div class="mini-stat">
        <span class="k">Peserta Terdaftar</span>
        <span class="v" style="color:var(--indigo-600)"><?= number_id($stats['registered']) ?></span>
        <span class="s"><?= number_id($stats['attended']) ?> sudah menyelesaikan pelatihan</span>
    </div>
    <div class="mini-stat">
        <span class="k">Jam Pelatihan</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id($stats['hours'], 1) ?></span>
        <span class="s">tingkat kehadiran <?= (int) $stats['attendance_rate'] ?>%</span>
    </div>
    <div class="mini-stat">
        <span class="k">Biaya Pelatihan</span>
        <span class="v" style="color:var(--amber-600)"><?= rupiah_short($stats['cost']) ?></span>
        <span class="s">akumulasi peserta hadir</span>
    </div>
</div>

<?php if ((int) $stats['pending'] > 0): ?>
    <div class="alert alert-warning" style="margin-bottom:14px">
        <?= icon('alert') ?>
        <div><b><?= number_id($stats['pending']) ?> pendaftaran mandiri</b> menunggu persetujuan HR. Buka detail sesi untuk menyetujui peserta.</div>
    </div>
<?php endif; ?>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Jadwal Pelatihan</h2>
                <div class="sub"><?= number_id($paginator->total) ?> sesi sesuai filter. Sesi aktif ditampilkan lebih dulu.</div></div>
        </div>

        <form class="filter-bar" method="get" action="<?= url('trainings') ?>">
            <select class="select" name="status" data-auto-submit style="min-width:170px">
                <option value="">Semua status</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="program" data-auto-submit style="min-width:210px">
                <option value="">Semua program</option>
                <?php foreach ($programs as $program): ?>
                    <option value="<?= (int) $program['id'] ?>" <?= (int) $filters['program'] === (int) $program['id'] ? 'selected' : '' ?>><?= e($program['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="select" name="mode" data-auto-submit style="min-width:140px">
                <option value="">Semua mode</option>
                <?php foreach ($modes as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filters['mode'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="input-group" style="max-width:200px">
                <?= icon('search') ?>
                <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Program / lokasi…">
            </div>
            <button class="btn btn-sm btn-outline" type="submit"><?= icon('filter') ?> Terapkan</button>
            <?php if ($filters['status'] || $filters['program'] || $filters['mode'] || $filters['q']): ?>
                <a class="btn btn-sm btn-ghost" href="<?= url('trainings') ?>"><?= icon('x') ?> Reset</a>
            <?php endif; ?>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th>Program</th><th>Jadwal</th><th>Pelaksanaan</th><th class="num">Peserta</th>
                    <th>Status</th><th style="width:150px">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($sessions as $session): ?>
                    <?php
                    $quota = (int) $session['quota'];
                    $filled = (int) $session['participants'];
                    $pct = $quota > 0 ? min(100, (int) round($filled / $quota * 100)) : 0;
                    ?>
                    <tr>
                        <td>
                            <b><?= e($session['program_name']) ?></b>
                            <div class="chip-row" style="margin-top:4px">
                                <?= $categoryChip((string) $session['category']) ?>
                                <span class="chip chip-slate"><?= e($session['program_code']) ?></span>
                                <?php if ((int) $session['is_mandatory'] === 1): ?>
                                    <span class="chip chip-rose">Wajib</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="small">
                            <b><?= tanggal($session['start_date'], 'd M Y') ?></b>
                            <?php if (!empty($session['end_date']) && $session['end_date'] !== $session['start_date']): ?>
                                <div class="muted" style="font-size:9.8px">s.d. <?= tanggal($session['end_date'], 'd M Y') ?></div>
                            <?php endif; ?>
                            <?php if (!empty($session['enroll_deadline'])): ?>
                                <div class="muted" style="font-size:9.8px">tutup <?= tanggal($session['enroll_deadline'], 'd M Y') ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?= e($modes[$session['mode']] ?? $session['mode']) ?>
                            <div class="muted" style="font-size:9.8px"><?= e($session['location'] ?: '—') ?></div>
                        </td>
                        <td class="num small" style="min-width:110px">
                            <b><?= number_id($filled) ?></b><?= $quota > 0 ? ' / ' . number_id($quota) : '' ?>
                            <?php if ($quota > 0): ?>
                                <div class="progress thin" style="margin-top:4px"><span style="width:<?= $pct ?>%"></span></div>
                            <?php endif; ?>
                            <?php if ((int) $session['pending'] > 0): ?>
                                <div style="font-size:9.6px;color:var(--amber-600);margin-top:3px"><?= number_id($session['pending']) ?> menunggu</div>
                            <?php else: ?>
                                <div class="muted" style="font-size:9.6px;margin-top:3px"><?= number_id($session['attended']) ?> hadir</div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?= $sessionTone((string) $session['status']) ?>"><?= e($statuses[$session['status']] ?? $session['status']) ?></span></td>
                        <td>
                            <div class="flex" style="gap:4px;justify-content:flex-end;flex-wrap:wrap">
                                <a class="btn btn-sm btn-ghost" href="<?= url('trainings/' . (int) $session['id']) ?>" title="Detail peserta"><?= icon('eye') ?></a>
                                <a class="btn btn-sm btn-ghost" href="<?= url('trainings?edit=' . (int) $session['id']) ?>" title="Ubah jadwal"><?= icon('edit') ?></a>
                                <?php if ($session['status'] === 'DRAFT'): ?>
                                    <form method="post" action="<?= url('trainings/' . (int) $session['id'] . '/open') ?>" onsubmit="return confirm('Buka pendaftaran sesi ini? Seluruh karyawan aktif akan menerima notifikasi.');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-primary" type="submit" title="Buka pendaftaran"><?= icon('send') ?> Buka</button>
                                    </form>
                                <?php elseif ($session['status'] === 'OPEN'): ?>
                                    <form method="post" action="<?= url('trainings/' . (int) $session['id'] . '/start') ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline" type="submit" title="Tandai berlangsung"><?= icon('activity') ?> Mulai</button>
                                    </form>
                                <?php elseif ($session['status'] === 'ONGOING'): ?>
                                    <form method="post" action="<?= url('trainings/' . (int) $session['id'] . '/complete') ?>"
                                          onsubmit="return confirm('Tutup sesi ini? Pastikan kehadiran & hasil peserta sudah dicatat.');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-teal" type="submit" title="Tutup sesi"><?= icon('check') ?> Selesai</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$sessions): ?>
                    <tr><td colspan="6">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('award') ?></div>
                            <h3>Belum ada sesi pelatihan</h3>
                            <p>Buat sesi dari formulir di samping, atau tambahkan program baru di katalog pelatihan.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($paginator->lastPage() > 1): ?>
            <div class="card__foot flex-between">
                <span class="muted small">Menampilkan <?= $paginator->from() ?>–<?= $paginator->to() ?> dari <?= number_id($paginator->total) ?></span>
                <div class="flex gap-4">
                    <?php if ($paginator->page > 1): ?>
                        <a class="btn btn-sm btn-outline" href="<?= $pageLink($paginator->page - 1) ?>"><?= icon('chevron-left') ?> Sebelumnya</a>
                    <?php endif; ?>
                    <span class="btn btn-sm btn-ghost"><?= $paginator->page ?> / <?= $paginator->lastPage() ?></span>
                    <?php if ($paginator->page < $paginator->lastPage()): ?>
                        <a class="btn btn-sm btn-outline" href="<?= $pageLink($paginator->page + 1) ?>">Berikutnya <?= icon('chevron-right') ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <section class="card" id="form-sesi">
        <div class="card__head">
            <div><h2><?= $isEdit ? 'Ubah Sesi' : 'Sesi Baru' ?></h2>
                <div class="sub"><?= $isEdit ? 'Perubahan jadwal berlaku untuk seluruh peserta.' : 'Sesi dibuat sebagai draf, pendaftaran dibuka setelah jadwal final.' ?></div></div>
        </div>
        <?php if (!$programs): ?>
            <div class="card__body">
                <div class="empty-state" style="padding:18px 0">
                    <div class="empty-icon"><?= icon('layers') ?></div>
                    <h3>Katalog masih kosong</h3>
                    <p>Tambahkan program pelatihan terlebih dahulu sebelum membuat sesi.</p>
                    <a class="btn btn-sm btn-primary" href="<?= url('trainings/catalog') ?>">Buka Katalog</a>
                </div>
            </div>
        <?php else: ?>
            <form method="post" action="<?= $isEdit ? url('trainings/' . (int) $editing['id']) : url('trainings') ?>">
                <?= csrf_field() ?>
                <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
                <div class="card__body flex-col gap-12">
                    <div class="field">
                        <label>Program pelatihan <span class="req">*</span></label>
                        <select class="select" name="training_id" required>
                            <option value="">Pilih program…</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?= (int) $program['id'] ?>" <?= (int) $val('training_id') === (int) $program['id'] ? 'selected' : '' ?>>
                                    <?= e($program['name']) ?> · <?= (int) $program['duration_hours'] ?> jam
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grid two">
                        <div class="field">
                            <label>Mode <span class="req">*</span></label>
                            <select class="select" name="mode">
                                <?php foreach ($modes as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $val('mode', 'OFFLINE') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Kuota peserta</label>
                            <input class="input" type="number" name="quota" min="0" max="500" value="<?= e($val('quota', '0')) ?>">
                            <span class="hint">Isi 0 untuk tanpa batas kuota.</span>
                        </div>
                        <div class="field">
                            <label>Tanggal mulai <span class="req">*</span></label>
                            <input class="input" type="date" name="start_date" required value="<?= e($val('start_date', $today)) ?>">
                        </div>
                        <div class="field">
                            <label>Tanggal selesai</label>
                            <input class="input" type="date" name="end_date" value="<?= e($val('end_date')) ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label>Lokasi / tautan</label>
                        <input class="input" type="text" name="location" maxlength="150" value="<?= e($val('location')) ?>"
                               placeholder="Ruang training Lt. 3 / https://meet.example.com/…">
                    </div>
                    <div class="field">
                        <label>Batas pendaftaran</label>
                        <input class="input" type="date" name="enroll_deadline" value="<?= e($val('enroll_deadline')) ?>">
                        <span class="hint">Setelah tanggal ini karyawan tidak dapat mendaftar mandiri.</span>
                    </div>
                    <div class="field">
                        <label>Catatan</label>
                        <textarea class="textarea" name="note" rows="3" maxlength="255" placeholder="Instruksi khusus, persiapan, atau kontak narasumber."><?= e($val('note')) ?></textarea>
                    </div>
                </div>
                <div class="card__foot" style="padding:12px 16px;display:flex;gap:8px;justify-content:flex-end">
                    <?php if ($isEdit): ?>
                        <a class="btn btn-ghost" href="<?= url('trainings') ?>">Batal</a>
                    <?php endif; ?>
                    <button class="btn btn-primary" type="submit"><?= icon($isEdit ? 'check' : 'plus') ?> <?= $isEdit ? 'Simpan Perubahan' : 'Buat Sesi' ?></button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</div>
