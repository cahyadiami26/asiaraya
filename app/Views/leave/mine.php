<?php
/**
 * Cuti saya (self service).
 * @var array $balances, $summary, $rows, $types, $typeQuotas, $policy, $years, $old
 * @var int $year
 * @var string $today
 */
use App\Models\LeaveModel;

$statusOrder = ['PENDING' => 1, 'MANAGER_APPROVED' => 2, 'APPROVED' => 3];
$openCount = $summary['open'];
$noticeToday = [];
foreach ($typeQuotas as $id => $meta) {
    $noticeToday[$id] = date('Y-m-d', strtotime('+' . (int) $meta['notice'] . ' days'));
}
?>
<div class="page-head">
    <div>
        <h1>Cuti Saya</h1>
        <div class="sub">Saldo cuti, pengajuan baru, dan status persetujuan atasan &amp; HR.</div>
    </div>
    <div class="page-head__actions">
        <select class="select" onchange="window.location.href='<?= url('me/leave') ?>?year=' + this.value" style="height:34px;font-size:12px;min-width:104px">
            <?php foreach ($years as $option): ?>
                <option value="<?= (int) $option ?>" <?= $year === (int) $option ? 'selected' : '' ?>><?= (int) $option ?></option>
            <?php endforeach; ?>
        </select>
        <a class="btn btn-primary" href="#leave-request" data-open-form><?= icon('plus') ?> Ajukan Cuti</a>
    </div>
</div>

<div class="leave-balance-grid">
    <?php foreach ($balances as $balance): ?>
        <div class="leave-balance-card" style="--type-color:<?= e($balance['color']) ?>">
            <div class="leave-balance-card__head">
                <span class="type-dot" style="background:<?= e($balance['color']) ?>"></span>
                <b><?= e($balance['name']) ?></b>
                <?php if ((int) $balance['is_paid'] === 0): ?><span class="badge badge-rose" style="height:17px;font-size:9px">Tanpa upah</span><?php endif; ?>
            </div>
            <div class="leave-balance-card__value">
                <?php if ($balance['unlimited']): ?>
                    <span style="font-size:19px">Tanpa jatah</span>
                <?php else: ?>
                    <?= number_id($balance['remaining'], 1) ?><span class="unit">hari</span>
                <?php endif; ?>
            </div>
            <div class="progress thin" style="margin:6px 0 5px">
                <span style="width:<?= (float) $balance['progress'] ?>%;background:<?= e($balance['color']) ?>"></span>
            </div>
            <div class="leave-balance-card__meta">
                <span>Jatah <b><?= number_id($balance['quota'], 1) ?></b></span>
                <span>Terpakai <b><?= number_id($balance['used'], 1) ?></b></span>
                <?php if ((float) $balance['carry'] > 0): ?><span>Carry <b><?= number_id($balance['carry'], 1) ?></b></span><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$balances): ?>
        <div class="leave-balance-card">
            <div class="leave-balance-card__head"><b>Belum ada jenis cuti aktif</b></div>
            <div class="leave-balance-card__meta"><span>Hubungi HR untuk pengaturan jatah cuti.</span></div>
        </div>
    <?php endif; ?>
</div>

<div class="mini-stat-grid">
    <div class="mini-stat">
        <span class="k">Sisa Saldo <?= $year ?></span>
        <span class="v"><?= number_id($summary['remaining'], 1) ?></span>
        <span class="s">hari dari semua jenis berjatah</span>
    </div>
    <div class="mini-stat">
        <span class="k">Cuti Terpakai</span>
        <span class="v" style="color:var(--teal-700)"><?= number_id($summary['approved_days'], 1) ?></span>
        <span class="s">hari disetujui pada <?= $year ?></span>
    </div>
    <div class="mini-stat">
        <span class="k">Pengajuan Berjalan</span>
        <span class="v" style="color:var(--amber-600)"><?= number_id($openCount) ?></span>
        <span class="s">menunggu atasan / HR</span>
    </div>
    <div class="mini-stat">
        <span class="k">Total Pengajuan</span>
        <span class="v"><?= number_id($summary['total']) ?></span>
        <span class="s">pada tahun <?= $year ?></span>
    </div>
</div>

<details class="form-panel" id="leave-request" <?= ($old || !empty($_GET['ajukan'])) ? 'open' : '' ?>>
    <summary>
        <span class="form-panel__title"><?= icon('send', 'ico') ?> Formulir Pengajuan Cuti</span>
        <span class="muted small">Atasan langsung → HR</span>
    </summary>
    <form method="post" action="<?= url('me/leave') ?>" class="form-panel__body" enctype="multipart/form-data" id="leave-form">
        <?= csrf_field() ?>
        <div class="form-grid three">
            <div class="field">
                <label>Jenis Cuti <span class="req">*</span></label>
                <select class="select" name="leave_type_id" id="leave-type" required>
                    <option value="">Pilih jenis cuti…</option>
                    <?php foreach ($types as $type): ?>
                        <?php $meta = $typeQuotas[(int) $type['id']] ?? null; ?>
                        <option value="<?= (int) $type['id'] ?>"
                                data-quota="<?= (float) $type['quota_days'] ?>"
                                data-max="<?= (int) $type['max_per_request'] ?>"
                                data-notice="<?= (int) $type['min_notice_days'] ?>"
                                data-attachment="<?= (int) $type['requires_attachment'] ?>"
                                data-earliest="<?= e($noticeToday[(int) $type['id']] ?? $today) ?>"
                                <?= (int) ($old['leave_type_id'] ?? 0) === (int) $type['id'] ? 'selected' : '' ?>>
                            <?= e($type['name']) ?><?= (float) $type['quota_days'] > 0 ? ' · jatah ' . number_id($type['quota_days'], 0) . ' hari' : ' · tanpa jatah' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Tanggal Mulai <span class="req">*</span></label>
                <input class="input" type="date" name="start_date" id="leave-start" value="<?= e($old['start_date'] ?? $today) ?>" required>
            </div>
            <div class="field">
                <label>Tanggal Selesai <span class="req">*</span></label>
                <input class="input" type="date" name="end_date" id="leave-end" value="<?= e($old['end_date'] ?? $today) ?>" required>
                <span class="hint">Akhir pekan &amp; hari besar tidak dihitung.</span>
            </div>
        </div>

        <div class="field" style="margin-top:12px">
            <label>Alasan / Keterangan <span class="req">*</span></label>
            <textarea class="textarea" name="reason" rows="2" maxlength="255" required placeholder="mis. Acara keluarga di luar kota"><?= e($old['reason'] ?? '') ?></textarea>
        </div>

        <div class="field" style="margin-top:12px" id="attachment-field">
            <label>Lampiran <span class="muted small" id="attachment-hint">(opsional — PDF/JPG/PNG maks 5 MB)</span></label>
            <input class="input" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
        </div>

        <div class="ot-estimate" id="leave-estimate">
            <div>
                <span class="k">Hari Kerja Dihitung</span>
                <span class="v" id="estimate-days">1 hari</span>
            </div>
            <div>
                <span class="k">Sisa Saldo</span>
                <span class="v" id="estimate-balance">—</span>
            </div>
            <div class="hint" id="estimate-note" style="margin-left:auto;max-width:300px;text-align:right">
                Pengajuan melewati atasan langsung lalu disetujui HR. Saldo terpotong saat HR menyetujui.
            </div>
        </div>

        <div class="alert alert-info" style="margin-top:12px">
            <?= icon('info') ?>
            <div id="leave-rules">
                Pilih jenis cuti untuk melihat ketentuan minimal pemberitahuan dan batas per pengajuan.
            </div>
        </div>

        <div class="form-actions" style="margin-top:14px">
            <button class="btn btn-primary" type="submit"><?= icon('send') ?> Kirim Pengajuan</button>
            <a class="btn btn-ghost" href="<?= url('me/leave') ?>">Batal</a>
        </div>
    </form>
</details>

<section class="card">
    <div class="card__head">
        <div><h2>Riwayat Pengajuan</h2>
            <div class="sub">40 pengajuan terakhir beserta progres persetujuan.</div></div>
        <span class="badge badge-slate"><?= number_id(count($rows)) ?> pengajuan</span>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th>Jenis</th><th>Tanggal</th><th class="num">Hari</th><th>Alasan</th>
                <th>Progres</th><th>Status</th><th>Lampiran</th><th style="width:56px">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $step = $statusOrder[$row['status']] ?? 0; ?>
                <tr>
                    <td class="small">
                        <span class="badge" style="background:<?= e($row['type_color']) ?>1a;color:<?= e($row['type_color']) ?>;border-color:<?= e($row['type_color']) ?>33">
                            <?= e($row['type_name']) ?>
                        </span>
                        <?php if ((int) $row['is_paid'] === 0): ?>
                            <div class="muted" style="font-size:9.6px;margin-top:2px">tanpa upah</div>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <b><?= tanggal($row['start_date']) ?></b>
                        <?php if ($row['start_date'] !== $row['end_date']): ?>
                            <div class="muted" style="font-size:10.4px">s.d. <?= tanggal($row['end_date']) ?></div>
                        <?php endif; ?>
                        <div class="muted" style="font-size:9.8px"><?= (int) $row['year'] ?></div>
                    </td>
                    <td class="num small"><?= number_id($row['days'], 1) ?></td>
                    <td class="small" style="max-width:220px"><?= e($row['reason'] ?: '—') ?></td>
                    <td>
                        <?php if ($row['status'] !== 'REJECTED' && $row['status'] !== 'CANCELLED'): ?>
                            <div class="ot-steps">
                                <span class="<?= $step >= 1 ? 'on' : '' ?>" title="Diajukan"></span>
                                <span class="<?= $step >= 2 ? 'on' : '' ?>" title="Atasan"></span>
                                <span class="<?= $step >= 3 ? 'on' : '' ?>" title="HR"></span>
                            </div>
                        <?php else: ?>
                            <span class="muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= status_badge($row['status']) ?>
                        <?php if ($row['hr_note']): ?>
                            <div class="muted" style="font-size:9.6px;margin-top:2px"><?= e($row['hr_note']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['attachment']): ?>
                            <a class="btn btn-sm btn-ghost" href="<?= url('media/leave/' . basename((string) $row['attachment'])) ?>" target="_blank" rel="noopener"><?= icon('file') ?></a>
                        <?php else: ?>
                            <span class="muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['status'] === 'PENDING'): ?>
                            <form class="inline-form" method="post" action="<?= url('me/leave/' . $row['id'] . '/cancel') ?>"
                                  data-confirm="Batalkan pengajuan cuti <?= tanggal($row['start_date']) ?>?">
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
                        <div class="empty-icon"><?= icon('file') ?></div>
                        <h3>Belum ada pengajuan cuti</h3>
                        <p>Pengajuan pertama Anda akan tampil di sini setelah dikirim.</p>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
(function () {
    var type = document.getElementById('leave-type');
    var start = document.getElementById('leave-start');
    var end = document.getElementById('leave-end');
    var outDays = document.getElementById('estimate-days');
    var outBalance = document.getElementById('estimate-balance');
    var rules = document.getElementById('leave-rules');
    var note = document.getElementById('estimate-note');
    var attachmentField = document.getElementById('attachment-field');
    var attachmentHint = document.getElementById('attachment-hint');
    if (!type || !start || !end) { return; }

    var balances = <?= json_encode(array_map(static fn ($b) => [
        'id' => (int) $b['id'],
        'remaining' => (float) $b['remaining'],
        'unlimited' => (bool) $b['unlimited'],
    ], $balances), JSON_UNESCAPED_UNICODE) ?>;

    function meta() {
        var option = type.options[type.selectedIndex];
        if (!option || !option.value) { return null; }
        return {
            id: parseInt(option.value, 10),
            quota: parseFloat(option.getAttribute('data-quota') || '0'),
            max: parseInt(option.getAttribute('data-max') || '0', 10),
            notice: parseInt(option.getAttribute('data-notice') || '0', 10),
            attachment: option.getAttribute('data-attachment') === '1',
            earliest: option.getAttribute('data-earliest') || '',
            name: option.textContent.trim()
        };
    }

    function countWorkdays(a, b) {
        if (!a || !b) { return 0; }
        var from = new Date(a + 'T00:00:00');
        var to = new Date(b + 'T00:00:00');
        if (isNaN(from) || isNaN(to) || to < from) { return 0; }
        var days = 0;
        var cursor = new Date(from);
        var guard = 0;
        while (cursor <= to && guard++ < 400) {
            var dow = cursor.getDay();
            if (dow !== 0 && dow !== 6) { days++; }
            cursor.setDate(cursor.getDate() + 1);
        }
        return days;
    }

    function update() {
        var data = meta();
        var days = countWorkdays(start.value, end.value);
        outDays.textContent = days + ' hari';

        if (!data) {
            outBalance.textContent = '—';
            rules.textContent = 'Pilih jenis cuti untuk melihat ketentuan minimal pemberitahuan dan batas per pengajuan.';
            return;
        }

        var balance = null;
        balances.forEach(function (item) { if (item.id === data.id) { balance = item; } });

        if (balance && !balance.unlimited && data.quota > 0) {
            outBalance.textContent = balance.remaining.toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' hari';
        } else {
            outBalance.textContent = 'Tanpa jatah';
        }

        var parts = [];
        if (data.notice > 0) { parts.push('Diajukan minimal ' + data.notice + ' hari sebelumnya (paling cepat ' + data.earliest + ').'); }
        if (data.max > 0) { parts.push('Maksimal ' + data.max + ' hari per pengajuan.'); }
        if (data.attachment) { parts.push('Lampiran wajib diunggah.'); }
        if (balance && !balance.unlimited && data.quota > 0) {
            parts.push('Sisa saldo ' + balance.remaining.toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' hari.');
        }
        rules.textContent = parts.join(' ') || 'Tidak ada syarat khusus untuk jenis cuti ini.';

        if (attachmentHint) {
            attachmentHint.textContent = data.attachment ? '(wajib — PDF/JPG/PNG maks 5 MB)' : '(opsional — PDF/JPG/PNG maks 5 MB)';
        }
        if (attachmentField) {
            attachmentField.style.opacity = '1';
        }

        if (note) {
            var warn = [];
            if (days > 0 && data.max > 0 && days > data.max) { warn.push('Melebihi batas ' + data.max + ' hari per pengajuan.'); }
            if (balance && !balance.unlimited && data.quota > 0 && days > balance.remaining) { warn.push('Melebihi sisa saldo.'); }
            if (data.notice > 0 && start.value && start.value < data.earliest) { warn.push('Tanggal mulai lebih awal dari batas pemberitahuan.'); }
            note.innerHTML = warn.length
                ? '<span style="color:var(--rose-600);font-weight:600">' + warn.join(' ') + '</span>'
                : 'Pengajuan diteruskan ke atasan langsung lalu HR. Saldo terpotong saat HR menyetujui.';
        }
    }

    type.addEventListener('change', function () {
        var data = meta();
        if (data && data.earliest) { start.min = data.earliest; }
        if (start.value && end.value && end.value < start.value) { end.value = start.value; }
        update();
    });
    start.addEventListener('change', function () {
        if (end.value < start.value || !end.value) { end.value = start.value; }
        update();
    });
    end.addEventListener('change', update);
    update();
})();
</script>
