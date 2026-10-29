<?php
/**
 * Presensi Saya — PWA check-in/out dengan GPS geofence + selfie.
 * @var array $context, $history, $totals, $summary, $employee, $geofences, $errors, $old
 * @var string $month, $monthLabel, $prevMonth, $nextMonth
 */
$attendance = $context['attendance'] ?? null;
$schedule = $context['schedule'] ?? null;
$unit = $context['unit'] ?? ['name' => null, 'lat' => null, 'lng' => null, 'radius' => 50];
$settings = $context['settings'] ?? ['geofence_required' => true, 'selfie_required' => true, 'photo_max_width' => 720, 'photo_quality' => 70];
$canIn = (bool) ($context['can_check_in'] ?? false);
$canOut = (bool) ($context['can_check_out'] ?? false);
$demoMode = (bool) config('demo_mode');

$statusLabel = 'Belum presensi';
$statusTone = 'slate';
if ($attendance && $attendance['check_in_at'] && empty($attendance['check_out_at'])) {
    $statusLabel = 'Sedang bekerja';
    $statusTone = 'teal';
} elseif ($attendance && $attendance['check_out_at']) {
    $statusLabel = 'Presensi selesai';
    $statusTone = 'indigo';
} elseif ($attendance && $attendance['status'] === 'LEAVE') {
    $statusLabel = 'Cuti / Izin';
    $statusTone = 'amber';
} elseif ($attendance && $attendance['status'] === 'ABSENT') {
    $statusLabel = 'Alpha';
    $statusTone = 'rose';
}
$dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
?>
<div class="page-head">
    <div>
        <h1>Presensi Saya</h1>
        <div class="sub">
            <?= e($dayNames[(int) date('N')]) ?>, <?= tanggal_panjang(date('Y-m-d')) ?> ·
            <?= e($context['unit']['name'] ?? 'Unit belum ditentukan') ?>
        </div>
    </div>
    <div class="page-head__actions">
        <span class="badge badge-<?= e($statusTone) ?>" style="height:28px;padding:0 12px"><?= e($statusLabel) ?></span>
        <a class="btn btn-outline" href="<?= url('me/schedule') ?>"><?= icon('calendar') ?> Jadwal Saya</a>
    </div>
</div>

<div class="punch-grid">
    <!-- ================================================= kamera & aksi -->
    <section class="card">
        <div class="card__head">
            <div><h2>Ambil Selfie Presensi</h2>
                <div class="sub">Foto otomatis diberi cap waktu, lalu dikompres sebelum dikirim.</div></div>
            <span class="live-pill"><span class="dot"></span> GPS aktif</span>
        </div>
        <div class="card__body">
            <div class="camera-frame" id="camera-frame">
                <video id="camera-video" autoplay playsinline muted></video>
                <img id="camera-photo" alt="Foto presensi" style="display:none">
                <div class="camera-placeholder" id="camera-placeholder">
                    <?= icon('camera') ?>
                    <div style="font-size:12px;font-weight:600">Kamera belum aktif</div>
                    <div style="font-size:10.6px;margin-top:3px">Izinkan akses kamera pada browser Anda</div>
                </div>
                <span class="camera-badge" id="camera-badge"><?= icon('clock', 'ico') ?> <span id="camera-time">--:--:--</span></span>
            </div>

            <div class="flex" style="gap:8px;margin-top:12px;flex-wrap:wrap">
                <button type="button" class="btn btn-soft" id="btn-capture"><?= icon('camera') ?> Ambil Foto</button>
                <button type="button" class="btn btn-ghost" id="btn-retake" style="display:none"><?= icon('refresh') ?> Ulangi</button>
                <label class="btn btn-ghost" style="margin-left:auto;cursor:pointer">
                    <?= icon('upload') ?> Unggah foto
                    <input type="file" id="file-photo" accept="image/*" capture="environment" style="display:none">
                </label>
            </div>

            <div class="divider"></div>

            <div class="geo-row" id="geo-status">
                <?= icon('map-pin') ?>
                <span id="geo-text">Mendeteksi lokasi…</span>
                <span class="tag-soft" id="geo-distance" style="margin-left:auto">—</span>
            </div>

            <div class="detail-list" style="margin-top:12px">
                <div class="detail-list__row"><span class="k">Shift hari ini</span>
                    <span class="v"><?= $schedule ? e($schedule['shift_name']) . ' · ' . e(substr($schedule['check_in_time'], 0, 5)) . '–' . e(substr($schedule['check_out_time'], 0, 5)) : 'Tidak ada jadwal' ?></span>
                </div>
                <div class="detail-list__row"><span class="k">Toleransi keterlambatan</span>
                    <span class="v"><?= (int) $context['tolerance'] ?> menit</span>
                </div>
                <div class="detail-list__row"><span class="k">Radius geofence</span>
                    <span class="v"><?= (int) $unit['radius'] ?> meter</span>
                </div>
                <?php if ($attendance && $attendance['check_in_at']): ?>
                    <div class="detail-list__row"><span class="k">Check-in</span>
                        <span class="v mono"><?= date('H:i:s', strtotime((string) $attendance['check_in_at'])) ?>
                            <?php if ($attendance['check_in_distance'] !== null): ?>
                                · <?= (int) $attendance['check_in_distance'] ?> m
                            <?php endif; ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($attendance && $attendance['check_out_at']): ?>
                    <div class="detail-list__row"><span class="k">Check-out</span>
                        <span class="v mono"><?= date('H:i:s', strtotime((string) $attendance['check_out_at'])) ?></span>
                    </div>
                    <div class="detail-list__row"><span class="k">Durasi kerja</span>
                        <span class="v"><?= intdiv((int) $attendance['work_minutes'], 60) ?> jam <?= (int) $attendance['work_minutes'] % 60 ?> menit</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="field" style="margin-top:12px">
                <label>Catatan <span class="muted small">(opsional)</span></label>
                <input class="input" id="punch-note" maxlength="255" placeholder="Contoh: kunjungan klien, kendala perjalanan">
            </div>

            <div class="punch-actions">
                <?php if ($canIn): ?>
                    <button type="button" class="btn btn-primary btn-lg btn-block" id="btn-checkin" data-action="check-in" disabled>
                        <?= icon('map-pin') ?> Check In Sekarang
                    </button>
                <?php elseif ($canOut): ?>
                    <button type="button" class="btn btn-teal btn-lg btn-block" id="btn-checkout" data-action="check-out" disabled>
                        <?= icon('check') ?> Check Out
                    </button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline btn-lg btn-block" disabled>
                        <?= icon('check') ?> Presensi hari ini selesai
                    </button>
                <?php endif; ?>
                <div class="small muted text-center" id="punch-hint">
                    <?= $settings['selfie_required'] ? 'Selfie wajib diambil' : 'Selfie opsional' ?> ·
                    <?= $settings['geofence_required'] ? 'validasi radius aktif' : 'validasi radius nonaktif' ?>
                </div>
                <?php if ($demoMode): ?>
                    <button type="button" class="btn btn-ghost btn-sm" id="btn-demo" style="width:100%">
                        <?= icon('info') ?> Simulasi presensi (mode demo sandbox)
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ==================================================== status & riwayat -->
    <div class="flex-col gap-16">
        <section class="card">
            <div class="card__head"><div><h2>Ringkasan <?= e($monthLabel) ?></h2>
                <div class="sub">Rekap kehadiran pribadi Anda.</div></div></div>
            <div class="card__body">
                <div class="mini-stat-grid" style="margin-bottom:0">
                    <div class="mini-stat">
                        <span class="k">Hadir</span>
                        <span class="v"><?= number_id($summary['present']) ?></span>
                        <span class="s"><?= number_id($summary['late']) ?> terlambat</span>
                    </div>
                    <div class="mini-stat">
                        <span class="k">Alpha</span>
                        <span class="v" style="color:var(--rose-600)"><?= number_id($summary['absent']) ?></span>
                        <span class="s">Tanpa keterangan</span>
                    </div>
                    <div class="mini-stat">
                        <span class="k">Jam Kerja</span>
                        <span class="v"><?= number_id($summary['work_hours'], 1) ?></span>
                        <span class="s">Total jam</span>
                    </div>
                    <div class="mini-stat">
                        <span class="k">Menit Terlambat</span>
                        <span class="v" style="color:var(--amber-600)"><?= number_id($summary['late_minutes']) ?></span>
                        <span class="s">Akumulasi</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <div><h2>Riwayat Presensi</h2>
                    <div class="sub"><?= e($monthLabel) ?></div></div>
                <div class="flex" style="gap:6px">
                    <a class="btn btn-sm btn-ghost" href="<?= url('me/attendance?month=' . $prevMonth) ?>"><?= icon('chevron-left') ?></a>
                    <a class="btn btn-sm btn-ghost" href="<?= url('me/attendance?month=' . $nextMonth) ?>"><?= icon('chevron-right') ?></a>
                </div>
            </div>
            <div class="table-wrap" style="max-height:420px;overflow-y:auto">
                <table class="data">
                    <thead><tr><th>Tanggal</th><th>Masuk</th><th>Keluar</th><th class="num">Terlambat</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $row): ?>
                        <tr>
                            <td class="small">
                                <b><?= tanggal($row['work_date'], 'd M') ?></b>
                                <span class="muted"><?= e($dayNames[(int) date('N', strtotime((string) $row['work_date']))]) ?></span>
                            </td>
                            <td class="mono small"><?= $row['check_in_at'] ? date('H:i', strtotime((string) $row['check_in_at'])) : '—' ?></td>
                            <td class="mono small"><?= $row['check_out_at'] ? date('H:i', strtotime((string) $row['check_out_at'])) : '—' ?></td>
                            <td class="num small"><?= (int) $row['late_minutes'] > 0 ? '<span style="color:var(--amber-600);font-weight:650">+' . (int) $row['late_minutes'] . '′</span>' : '—' ?></td>
                            <td><?= status_badge($row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$history): ?>
                        <tr><td colspan="5" class="table-empty"><?= icon('calendar') ?>Belum ada catatan presensi pada bulan ini.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card__foot small muted" style="padding:10px 16px">
                Total: <?= number_id($totals['present']) ?> hadir · <?= number_id($totals['late']) ?> terlambat ·
                <?= number_id($totals['absent']) ?> alpha · <?= number_id($totals['leave']) ?> cuti
            </div>
        </section>
    </div>
</div>

<script>
(function () {
    var csrf = <?= json_encode(csrf_token()) ?>;
    var punchUrl = <?= json_encode(url('attendance/punch')) ?>;
    var unit = <?= json_encode([
        'lat' => $unit['lat'] !== null ? (float) $unit['lat'] : null,
        'lng' => $unit['lng'] !== null ? (float) $unit['lng'] : null,
        'radius' => (int) $unit['radius'],
        'name' => $unit['name'],
    ]) ?>;
    var options = {
        selfieRequired: <?= $settings['selfie_required'] ? 'true' : 'false' ?>,
        geofenceRequired: <?= $settings['geofence_required'] ? 'true' : 'false' ?>,
        photoWidth: <?= (int) $settings['photo_max_width'] ?>,
        photoQuality: <?= (int) $settings['photo_quality'] ?>/100,
        demo: <?= $demoMode ? 'true' : 'false' ?>
    };

    var state = { lat: null, lng: null, accuracy: null, photo: null, inside: false };
    var video = document.getElementById('camera-video');
    var photoImg = document.getElementById('camera-photo');
    var placeholder = document.getElementById('camera-placeholder');
    var btnCapture = document.getElementById('btn-capture');
    var btnRetake = document.getElementById('btn-retake');
    var btnCheckin = document.getElementById('btn-checkin');
    var btnCheckout = document.getElementById('btn-checkout');
    var geoText = document.getElementById('geo-text');
    var geoDistance = document.getElementById('geo-distance');
    var geoRow = document.getElementById('geo-status');
    var hint = document.getElementById('punch-hint');
    var stream = null;

    /* ------------------------------------------------------------ clock */
    function tick() {
        var now = new Date();
        var pad = function (n) { return String(n).padStart(2, '0'); };
        var el = document.getElementById('camera-time');
        if (el) { el.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds()); }
    }
    tick();
    setInterval(tick, 1000);

    /* --------------------------------------------------------- distance */
    function haversine(lat1, lng1, lat2, lng2) {
        var R = 6371000, rad = Math.PI / 180;
        var dLat = (lat2 - lat1) * rad, dLng = (lng2 - lng1) * rad;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return 2 * R * Math.asin(Math.min(1, Math.sqrt(a)));
    }

    function refreshGeo(distance) {
        if (distance === null) {
            geoRow.style.background = '#f8fafc';
            geoDistance.textContent = '—';
            return;
        }
        var inside = distance <= unit.radius;
        state.inside = inside;
        geoDistance.textContent = Math.round(distance) + ' m';
        if (inside) {
            geoRow.style.background = 'var(--teal-50)';
            geoRow.style.borderColor = '#c7f0e7';
            geoText.textContent = 'Anda berada di dalam area ' + (unit.name || 'kantor');
        } else {
            geoRow.style.background = '#fff8f0';
            geoRow.style.borderColor = '#fbe3b4';
            geoText.textContent = 'Di luar radius ' + unit.radius + ' m dari ' + (unit.name || 'kantor');
        }
        updateButtons();
    }

    function setCoords(lat, lng, accuracy) {
        state.lat = lat; state.lng = lng; state.accuracy = accuracy;
        var distance = (unit.lat !== null && unit.lng !== null) ? haversine(lat, lng, unit.lat, unit.lng) : null;
        refreshGeo(distance);
    }

    function geoFailure(message) {
        geoText.textContent = message;
        geoRow.style.background = 'var(--rose-50)';
        geoRow.style.borderColor = '#f6c9d0';
        geoDistance.textContent = '—';
        updateButtons();
    }

    function locate() {
        if (!navigator.geolocation) {
            geoFailure('Perangkat tidak mendukung GPS. Gunakan browser modern.');
            return;
        }
        navigator.geolocation.watchPosition(function (position) {
            setCoords(position.coords.latitude, position.coords.longitude, position.coords.accuracy);
        }, function (error) {
            var message = error.code === 1
                ? 'Izin lokasi ditolak. Aktifkan izin lokasi pada browser.'
                : 'Lokasi tidak terbaca (GPS lemah). Coba di area terbuka.';
            geoFailure(message);
        }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 5000 });
    }

    /* ------------------------------------------------------------ camera */
    function startCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            placeholder.querySelector('div').textContent = 'Kamera tidak tersedia — gunakan unggah foto';
            return;
        }
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 720 } }, audio: false })
            .then(function (media) {
                stream = media;
                video.srcObject = media;
                video.style.display = '';
                placeholder.style.display = 'none';
            })
            .catch(function () {
                placeholder.querySelector('div').textContent = 'Kamera diblokir — gunakan unggah foto';
            });
    }

    function capture() {
        if (!stream || !video.videoWidth) {
            document.getElementById('file-photo').click();
            return;
        }
        var width = Math.min(options.photoWidth, video.videoWidth || options.photoWidth);
        var scale = width / (video.videoWidth || width);
        var canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = Math.round((video.videoHeight || width) * scale);
        var ctx = canvas.getContext('2d');
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        state.photo = canvas.toDataURL('image/jpeg', options.photoQuality);
        showPhoto(state.photo);
    }

    function showPhoto(dataUrl) {
        photoImg.src = dataUrl;
        photoImg.style.display = '';
        video.style.display = 'none';
        placeholder.style.display = 'none';
        btnRetake.style.display = '';
        btnCapture.textContent = 'Foto siap';
        updateButtons();
    }

    function retake() {
        state.photo = null;
        photoImg.style.display = 'none';
        video.style.display = stream ? '' : 'none';
        placeholder.style.display = stream ? 'none' : 'grid';
        btnRetake.style.display = 'none';
        btnCapture.innerHTML = '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg> Ambil Foto';
        updateButtons();
    }

    /* file fallback */
    document.getElementById('file-photo').addEventListener('change', function (event) {
        var file = event.target.files && event.target.files[0];
        if (!file) { return; }
        var reader = new FileReader();
        reader.onload = function () {
            var img = new Image();
            img.onload = function () {
                var width = Math.min(options.photoWidth, img.width);
                var scale = width / img.width;
                var canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = Math.round(img.height * scale);
                canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                state.photo = canvas.toDataURL('image/jpeg', options.photoQuality);
                showPhoto(state.photo);
            };
            img.src = reader.result;
        };
        reader.readAsDataURL(file);
    });

    /* ---------------------------------------------------------- buttons */
    function updateButtons() {
        var button = btnCheckin || btnCheckout;
        if (!button) { return; }
        var ready = true;
        var reasons = [];
        if (options.selfieRequired && !state.photo) { ready = false; reasons.push('selfie belum diambil'); }
        if (state.lat === null) { ready = false; reasons.push('lokasi belum terbaca'); }
        if (options.geofenceRequired && state.lat !== null && !state.inside) { ready = false; reasons.push('di luar radius kantor'); }
        button.disabled = !ready;
        if (hint) {
            if (ready) {
                hint.textContent = 'Siap — tekan tombol untuk mengirim presensi.';
                hint.style.color = 'var(--teal-700)';
            } else {
                hint.textContent = 'Menunggu: ' + reasons.join(', ') + '.';
                hint.style.color = 'var(--muted)';
            }
        }
    }

    /* ----------------------------------------------------------- submit */
    function submit(button) {
        var action = button.dataset.action;
        button.disabled = true;
        var original = button.innerHTML;
        button.innerHTML = 'Mengirim presensi…';
        var payload = new URLSearchParams();
        payload.set('_token', csrf);
        payload.set('action', action);
        payload.set('lat', state.lat === null ? '' : state.lat);
        payload.set('lng', state.lng === null ? '' : state.lng);
        payload.set('accuracy', state.accuracy === null ? '' : state.accuracy);
        payload.set('note', document.getElementById('punch-note').value || '');
        if (state.photo) { payload.set('photo', state.photo); }

        fetch(punchUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            body: payload.toString(),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (data) { return { ok: response.ok, data: data }; });
        }).then(function (result) {
            if (result.ok && result.data.ok) {
                showToast('success', result.data.message || 'Presensi tercatat.');
                setTimeout(function () { window.location.reload(); }, 900);
                return;
            }
            var message = (result.data && result.data.error) || 'Presensi gagal. Coba lagi.';
            showToast('error', message);
            button.disabled = false;
            button.innerHTML = original;
        }).catch(function () {
            showToast('error', 'Koneksi gagal. Periksa jaringan Anda lalu coba lagi.');
            button.disabled = false;
            button.innerHTML = original;
        });
    }

    function showToast(type, message) {
        var stack = document.querySelector('.flash-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'flash-stack';
            var page = document.querySelector('.page');
            page.insertBefore(stack, page.firstChild);
        }
        var alert = document.createElement('div');
        alert.className = 'alert alert-' + (type === 'success' ? 'success' : 'error');
        alert.setAttribute('role', 'alert');
        alert.textContent = message;
        stack.appendChild(alert);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /* ------------------------------------------------------------- demo */
    var demoBtn = document.getElementById('btn-demo');
    if (demoBtn) {
        demoBtn.addEventListener('click', function () {
            if (unit.lat !== null) {
                setCoords(unit.lat, unit.lng, 8);
                geoText.textContent = 'Simulasi demo: lokasi diisi koordinat kantor ' + (unit.name || '');
            }
            var canvas = document.createElement('canvas');
            canvas.width = 480; canvas.height = 640;
            var ctx = canvas.getContext('2d');
            var gradient = ctx.createLinearGradient(0, 0, 480, 640);
            gradient.addColorStop(0, '#0b2135');
            gradient.addColorStop(1, '#0d9488');
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, 480, 640);
            ctx.fillStyle = 'rgba(255,255,255,.92)';
            ctx.font = 'bold 30px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('FOTO SIMULASI', 240, 300);
            ctx.font = '18px Inter, sans-serif';
            ctx.fillText('Mode demo sandbox', 240, 336);
            ctx.fillText(new Date().toLocaleString('id-ID'), 240, 368);
            state.photo = canvas.toDataURL('image/jpeg', 0.8);
            showPhoto(state.photo);
            updateButtons();
        });
    }

    if (btnCapture) { btnCapture.addEventListener('click', capture); }
    if (btnRetake) { btnRetake.addEventListener('click', retake); }
    [btnCheckin, btnCheckout].forEach(function (button) {
        if (button) { button.addEventListener('click', function () { submit(button); }); }
    });

    locate();
    startCamera();
    updateButtons();
})();
</script>
