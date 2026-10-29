<?php
/**
 * Lembur saya (self service karyawan).
 * @var array $rows, $open, $summary, $policy, $preview, $old
 * @var float $basicSalary
 * @var string $month, $monthLabel, $today
 */
use App\Models\OvertimeModel;

$hasOpenToday = false;
foreach ($open as $item) {
    if ((string) $item['work_date'] === $today) {
        $hasOpenToday = true;
        break;
    }
}
$statusOrder = ['PENDING' => 1, 'MANAGER_APPROVED' => 2, 'APPROVED' => 3];
?>
<div class="page-head">
    <div>
        <h1>Lembur Saya</h1>
        <div class="sub">Ajukan lembur, pantau persetujuan atasan &amp; HR, dan lihat kompensasi Anda.</div>
    </div>
    <div class="page-head__actions">
        <a class="btn btn-outline" href="<?= url('me/attendance') ?>"><?= icon('map-pin') ?> Presensi Saya</a>
        <a class="btn btn-primary" href="#ot-request" data-open-form><?= icon('plus') ?> Ajukan Lembur</a>
    </div>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Jam Lembur Disetujui</span>
        <span class="v"><?= number_id($summary['approved_hours'], 1) ?></span>
        <span class="s"><?= e($monthLabel) ?></span>
    </div>
    <div class="mini-stat">
        <span class="k">Menunggu Keputusan</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($summary['open']) ?></span>
        <span class="s">Pengajuan berjalan</span>
    </div>
    <div class="mini-stat">
        <span class="k">Kompensasi Lembur</span>
        <span class="v" style="font-size:19px"><?= rupiah($summary['amount']) ?></span>
        <span class="s">Masuk payroll periode ini</span>
    </div>
    <div class="mini-stat">
        <span class="k">Batas per Hari</span>
        <span class="v"><?= number_id($policy['max_hours']) ?> jam</span>
        <span class="s">Minimal <?= number_id($policy['min_minutes']) ?> menit</span>
    </div>
</div>

<?php if (!$policy['enabled']): ?>
    <div class="alert alert-warning" style="margin-bottom:16px">
        <?= icon('alert') ?>
        <div>Modul lembur sedang dinonaktifkan oleh HR. Pengajuan baru belum dapat dibuat.</div>
    </div>
<?php endif; ?>

<details class="form-panel" id="ot-request" <?= ($old || !empty($_GET['ajukan'])) ? 'open' : '' ?>>
    <summary>
        <span class="form-panel__title"><?= icon('send', 'ico') ?> Formulir Pengajuan Lembur</span>
        <span class="muted small">Atasan langsung → HR</span>
    </summary>
    <form method="post" action="<?= url('me/overtime') ?>" class="form-panel__body" id="ot-form">
        <?= csrf_field() ?>
        <div class="form-grid three">
            <div class="field">
                <label>Tanggal Lembur <span class="req">*</span></label>
                <input class="input" type="date" name="work_date" value="<?= e($old['work_date'] ?? $today) ?>" required <?= $policy['enabled'] ? '' : 'disabled' ?>>
            </div>
            <div class="field">
                <label>Jam Mulai <span class="req">*</span></label>
                <input class="input" type="time" name="planned_start" id="my-start" value="<?= e($old['planned_start'] ?? '17:00') ?>" required <?= $policy['enabled'] ? '' : 'disabled' ?>>
            </div>
            <div class="field">
                <label>Jam Selesai <span class="req">*</span></label>
                <input class="input" type="time" name="planned_end" id="my-end" value="<?= e($old['planned_end'] ?? '20:00') ?>" required <?= $policy['enabled'] ? '' : 'disabled' ?>>
            </div>
        </div>
        <div class="field" style="margin-top:12px">
            <label>Uraian Pekerjaan</label>
            <textarea class="textarea" name="reason" rows="2" maxlength="255" placeholder="mis. Menyelesaikan rekonsiliasi data untuk closing bulanan" <?= $policy['enabled'] ? '' : 'disabled' ?>><?= e($old['reason'] ?? '') ?></textarea>
        </div>

        <div class="ot-estimate" id="ot-estimate">
            <div>
                <span class="k">Durasi</span>
                <span class="v" id="estimate-hours">3,0 jam</span>
            </div>
            <div>
                <span class="k">Estimasi Kompensasi</span>
                <span class="v" id="estimate-amount">—</span>
            </div>
            <div class="hint" id="estimate-note" style="margin-left:auto;max-width:280px;text-align:right">
                Estimasi mengikuti tarif lembur yang berlaku. Nilai final dihitung saat pengajuan disetujui HR.
            </div>
        </div>

        <?php if ($hasOpenToday): ?>
            <div class="alert alert-info" style="margin-top:14px">
                <?= icon('info') ?>
                <div>Anda memiliki pengajuan lembur berjalan untuk hari ini. Pengajuan tambahan tetap dapat dibuat selama jamnya tidak bertabrakan.</div>
            </div>
        <?php endif; ?>

        <div class="form-actions" style="margin-top:14px">
            <button class="btn btn-primary" type="submit" <?= $policy['enabled'] ? '' : 'disabled' ?>><?= icon('send') ?> Kirim Pengajuan</button>
            <a class="btn btn-ghost" href="<?= url('me/overtime') ?>">Batal</a>
        </div>
    </form>
</details>

<div class="split-grid wide-left">
    <section class="card">
        <div class="card__head">
            <div><h2>Riwayat Lembur</h2>
                <div class="sub">60 entri terakhir · termasuk penugasan terjadwal dari HR.</div></div>
            <span class="badge badge-slate"><?= number_id(count($rows)) ?> entri</span>
        </div>

        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th>Tanggal</th><th>Jam</th><th class="num">Rencana</th><th class="num">Aktual</th>
                    <th class="num">Kompensasi</th><th>Jenis</th><th>Status</th><th style="width:64px">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $step = $statusOrder[$row['status']] ?? 0; ?>
                    <tr>
                        <td class="small">
                            <b><?= tanggal($row['work_date']) ?></b>
                            <div class="muted" style="font-size:10.2px"><?= e(date('l', strtotime($row['work_date']))) ?></div>
                            <?php if ($row['holiday_name']): ?>
                                <span class="badge badge-indigo" style="margin-top:3px"><?= e($row['holiday_name']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="mono small"><?= e($row['window']) ?><?= $row['crosses_midnight'] ? ' <span class="muted">+1</span>' : '' ?></td>
                        <td class="num small"><?= number_id($row['planned_hours'], 1) ?> j</td>
                        <td class="num small"><?= $row['actual_hours'] !== null ? number_id($row['actual_hours'], 1) . ' j' : '<span class="muted">—</span>' ?></td>
                        <td class="num small">
                            <?php if ($row['status'] === 'APPROVED' && $row['compensation_amount'] > 0): ?>
                                <b><?= rupiah($row['compensation_amount']) ?></b>
                            <?php elseif ($row['status'] === 'REJECTED' || $row['status'] === 'CANCELLED'): ?>
                                <span class="muted">—</span>
                            <?php else: ?>
                                <span class="muted" title="Estimasi">≈ <?= rupiah($row['estimated_amount']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <span class="badge badge-<?= $row['type'] === 'SCHEDULED' ? 'indigo' : 'slate' ?>">
                                <?= $row['type'] === 'SCHEDULED' ? 'Terjadwal' : 'Pengajuan' ?>
                            </span>
                        </td>
                        <td>
                            <?= status_badge($row['status']) ?>
                            <?php if ($row['status'] !== 'REJECTED' && $row['status'] !== 'CANCELLED'): ?>
                                <div class="ot-steps">
                                    <span class="<?= $step >= 1 ? 'on' : '' ?>" title="Diajukan"></span>
                                    <span class="<?= $step >= 2 ? 'on' : '' ?>" title="Atasan"></span>
                                    <span class="<?= $step >= 3 ? 'on' : '' ?>" title="HR"></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($row['hr_note']): ?>
                                <div class="muted" style="font-size:9.6px;margin-top:2px"><?= e($row['hr_note']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['status'] === 'PENDING'): ?>
                                <form class="inline-form" method="post" action="<?= url('me/overtime/' . $row['id'] . '/cancel') ?>"
                                      data-confirm="Batalkan pengajuan lembur <?= tanggal($row['work_date']) ?>?">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-ghost" type="submit" title="Batalkan pengajuan"><?= icon('x') ?></button>
                                </form>
                            <?php else: ?>
                                <span class="muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="8">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('clock') ?></div>
                            <h3>Belum ada riwayat lembur</h3>
                            <p>Pengajuan lembur pertama Anda akan tampil di sini setelah dikirim.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head"><div><h2>Tarif Lembur Anda</h2>
                <div class="sub">Dihitung dari gaji pokok ÷ <?= number_id($policy['divisor']) ?>.</div></div></div>
            <div class="card__body">
                <div class="table-wrap" style="border:1px solid var(--line);border-radius:10px">
                    <table class="data">
                        <thead><tr><th>Durasi</th><th class="num">Hari Kerja</th><th class="num">Hari Libur</th></tr></thead>
                        <tbody>
                        <?php foreach ($preview as $line): ?>
                            <tr>
                                <td class="small"><?= (int) $line['hours'] ?> jam</td>
                                <td class="num small"><?= rupiah($line['amount']) ?></td>
                                <td class="num small"><?= rupiah($line['holiday']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty(array_filter($preview, static fn ($l) => $l['amount'] > 0))): ?>
                            <tr><td colspan="3" class="small muted">Gaji pokok belum diatur, hubungi HR untuk perhitungan kompensasi.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="ot-policy" style="margin-top:12px">
                    <div><span class="k">Jam pertama</span><span class="v">×<?= number_id($policy['first'], 1) ?></span></div>
                    <div><span class="k">Berikutnya</span><span class="v">×<?= number_id($policy['next'], 1) ?></span></div>
                    <div><span class="k">Hari libur</span><span class="v">×<?= number_id($policy['holiday'], 1) ?></span></div>
                    <div><span class="k">Maks / hari</span><span class="v"><?= number_id($policy['max_hours']) ?> jam</span></div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Alur Persetujuan</h2>
                <div class="sub">Dua tahap sebelum lembur dibayarkan.</div></div></div>
            <div class="card__body">
                <div class="timeline">
                    <div class="timeline__item">
                        <b>1. Pengajuan Anda</b>
                        <span>Isi tanggal, jam, dan uraian pekerjaan.</span>
                    </div>
                    <div class="timeline__item">
                        <b>2. Atasan Langsung</b>
                        <span>Menyetujui atau menolak pengajuan.</span>
                    </div>
                    <div class="timeline__item">
                        <b>3. HR</b>
                        <span>Keputusan final &amp; perhitungan kompensasi.</span>
                    </div>
                    <div class="timeline__item muted">
                        <b>4. Payroll</b>
                        <span>Kompensasi masuk ke slip gaji periode berjalan.</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><div><h2>Catatan</h2></div></div>
            <div class="card__body small" style="color:var(--muted);line-height:1.7">
                <ul style="margin:0;padding-left:16px">
                    <li>Pengajuan minimal <?= number_id($policy['min_minutes']) ?> menit dan maksimal <?= number_id($policy['max_hours']) ?> jam per hari.</li>
                    <li>Lembur pada akhir pekan/hari besar memakai tarif libur.</li>
                    <li>Pengajuan yang sudah diproses atasan tidak dapat dibatalkan sendiri; hubungi HR.</li>
                    <li>Lembur bisa juga ditugaskan HR tanpa pengajuan (lembur terjadwal) dan langsung tercatat disetujui.</li>
                </ul>
            </div>
        </section>
    </div>
</div>

<script>
(function () {
    var start = document.getElementById('my-start');
    var end = document.getElementById('my-end');
    var outHours = document.getElementById('estimate-hours');
    var outAmount = document.getElementById('estimate-amount');
    var form = document.getElementById('ot-form');
    if (!start || !end || !form) { return; }

    var salary = <?= (float) ($basicSalary ?? 0) ?>;
    var divisor = <?= (float) $policy['divisor'] ?>;
    var first = <?= (float) $policy['first'] ?>;
    var next = <?= (float) $policy['next'] ?>;
    var holidayMul = <?= (float) $policy['holiday'] ?>;
    var maxHours = <?= (float) $policy['max_hours'] ?>;

    function money(value) {
        return 'Rp ' + Math.round(value).toLocaleString('id-ID');
    }

    function update() {
        if (!start.value || !end.value) { return; }
        var a = start.value.split(':');
        var b = end.value.split(':');
        var minutes = (parseInt(b[0], 10) * 60 + parseInt(b[1], 10)) - (parseInt(a[0], 10) * 60 + parseInt(a[1], 10));
        if (minutes <= 0) { minutes += 1440; }
        var hours = Math.min(minutes / 60, maxHours);
        outHours.textContent = hours.toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' jam';

        if (salary <= 0 || divisor <= 0) {
            outAmount.textContent = '—';
            return;
        }
        var hourly = salary / divisor;
        var firstHours = Math.min(1, hours);
        var rest = Math.max(0, hours - 1);
        outAmount.textContent = money(firstHours * hourly * first + rest * hourly * next);
    }

    start.addEventListener('change', update);
    end.addEventListener('change', update);
    update();
})();
</script>
