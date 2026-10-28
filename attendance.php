<?php
/**
 * Absensi Online (PWA): GPS + Selfie (kompresi client-side) -> API.
 */
$page_title = 'Absensi'; $active_menu = 'attendance';
require_once __DIR__ . '/includes/header.php';

$eid = employee_id();
if (!$eid) { echo '<div class="alert alert-warning">Akun Anda belum terhubung dengan data karyawan, absensi tidak tersedia.</div>'; require __DIR__ . '/includes/footer.php'; exit; }

$aset = attendance_settings();
$today = date('Y-m-d');
$log = one('SELECT * FROM attendance_logs WHERE employee_id=? AND log_date=?', [$eid, $today]);
$sched = one('SELECT s.*, sh.name shift_name, b.name branch_name, b.latitude, b.longitude, b.radius_meter
              FROM schedules s JOIN shifts sh ON sh.id=s.shift_id JOIN branches b ON b.id=s.branch_id
              WHERE s.employee_id=? AND ? BETWEEN s.start_date AND s.end_date LIMIT 1', [$eid, $today]);
$branch = $sched ?: one('SELECT b.* FROM employees e JOIN branches b ON b.id=e.branch_id WHERE e.id=?', [$eid]);
$cfg = [
    'hasIn'   => !empty($log['check_in_at']),
    'hasOut'  => !empty($log['check_out_at']),
    'checkin' => $log['check_in_at'] ? date('H:i', strtotime($log['check_in_at'])) : null,
    'checkout'=> $log['check_out_at'] ? date('H:i', strtotime($log['check_out_at'])) : null,
    'quality' => (int)$aset['selfie_quality'],
    'maxSide' => (int)$aset['selfie_resize'],
    'maxKb'   => (int)$aset['selfie_max_kb'],
    'csrf'    => csrf_token(),
];
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h1 class="page-title"><i class="bi bi-geo-alt me-2"></i>Absensi Online <span class="badge bg-success-subtle text-success ms-2 align-middle" style="font-size:.65rem">PWA READY</span></h1>
  <button class="btn btn-outline-primary btn-sm" onclick="installPWA()" id="btnInstall"><i class="bi bi-download me-1"></i>Pasang Aplikasi</button>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header fw-bold"><i class="bi bi-calendar3 me-1"></i>Jadwal & Lokasi Hari Ini</div>
      <div class="card-body small">
        <?php if ($sched): ?>
          <div class="sched-day today">
            <b><?= e($sched['shift_name']) ?></b> · 📍<?= e($sched['branch_name']) ?><br>
            Masuk <b><?= time_hm($sched['check_in_time']) ?></b> — Pulang <b><?= time_hm($sched['check_out_time']) ?></b><br>
            Istirahat: <?= time_hm($sched['break_time']) ?> · Toleransi telat: <?= (int)$aset['grace_minutes'] ?> menit
          </div>
        <?php else: ?>
          <div class="text-muted">Tidak ada jadwal shift hari ini (libur). Absensi tetap dapat dilakukan bila diperlukan.</div>
        <?php endif; ?>
        <hr class="my-2">
        <div id="gpsStatus" class="small text-muted"><i class="bi bi-signal me-1"></i>Mengambil koordinat GPS…</div>
      </div>
    </div>

    <div class="card">
      <div class="card-header fw-bold"><i class="bi bi-camera me-1"></i>Kamera Selfie</div>
      <div class="card-body text-center">
        <div class="camera-shell" id="camShell">
          <video id="video" autoplay playsinline muted></video>
          <canvas id="canvas" hidden></canvas>
        </div>
        <img id="preview" class="selfie-preview d-none" alt="preview selfie">
        <div class="mt-2 small text-muted" id="photoInfo">Foto otomatis dikompres (≤ <?= (int)$aset['selfie_max_kb'] ?>KB) sebelum upload.</div>
        <div class="d-flex justify-content-center gap-2 mt-3">
          <button class="btn btn-outline-secondary btn-sm" onclick="startCam()"><i class="bi bi-camera-video me-1"></i>Buka Kamera</button>
          <button class="btn btn-dark btn-sm" onclick="capture()"><i class="bi bi-crop me-1"></i>Ambil Foto</button>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card">
      <div class="card-header fw-bold"><i class="bi bi-clock me-1"></i>Check-In / Check-Out — <?= e(date_id($today, true)) ?></div>
      <div class="card-body">
        <div class="display-6 fw-bold text-center my-2" id="liveClock"><?= date('H:i:s') ?></div>
        <div class="row g-3 text-center">
          <div class="col-md-6">
            <div class="border rounded p-3 h-100 <?= $log['check_in_at'] ? 'bg-success-subtle' : '' ?>">
              <div class="small text-uppercase text-muted fw-bold">Check-In</div>
              <div class="fs-4 fw-bold my-1"><?= $cfg['checkin'] ?? '--:--' ?></div>
              <?php if ($log['check_in_at']): ?>
                <span class="badge bg-success">Sudah absen masuk</span>
                <?php if ((int)$log['late_minutes'] > 0): ?><span class="badge bg-danger mt-1">Telat <?= (int)$log['late_minutes'] ?> mnt</span><?php endif; ?>
                <?php if ($log['check_in_photo']): ?><a class="d-block small mt-1" href="<?= e($log['check_in_photo']) ?>" target="_blank">📷 lihat foto</a><?php endif; ?>
              <?php else: ?>
                <button class="btn pulse-btn mt-2" id="btnIn" onclick="doAttd('in')"><i class="bi bi-box-arrow-in-right"></i></button>
                <div class="small text-muted mt-1">Tekan untuk check-in</div>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-md-6">
            <div class="border rounded p-3 h-100 <?= $log['check_out_at'] ? 'bg-primary-subtle' : '' ?>">
              <div class="small text-uppercase text-muted fw-bold">Check-Out</div>
              <div class="fs-4 fw-bold my-1"><?= $cfg['checkout'] ?? '--:--' ?></div>
              <?php if ($log['check_out_at']): ?>
                <span class="badge bg-primary">Selesai bekerja</span>
                <?php if ($log['check_out_photo']): ?><a class="d-block small mt-1" href="<?= e($log['check_out_photo']) ?>" target="_blank">📷 lihat foto</a><?php endif; ?>
              <?php elseif ($log['check_in_at']): ?>
                <button class="btn pulse-btn mt-2" style="background:#1d7a8c" id="btnOut" onclick="doAttd('out')"><i class="bi bi-box-arrow-right"></i></button>
                <div class="small text-muted mt-1">Tekan untuk check-out</div>
              <?php else: ?>
                <div class="text-muted small mt-3">Lakukan check-in terlebih dahulu.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div id="attMsg"></div>
        <div class="alert alert-info small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>
          Sistem memverifikasi lokasi GPS Anda terhadap radius kantor dan menyimpan foto selfie sebagai bukti kehadiran.</div>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header fw-bold"><i class="bi bi-list-check me-1"></i>Status Kehadiran Bulan Ini</div>
      <div class="card-body p-0 table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead><tr><th>Tanggal</th><th>In</th><th>Out</th><th>Telat</th><th>Foto</th></tr></thead>
          <tbody>
          <?php
          $rows = all('SELECT * FROM attendance_logs WHERE employee_id=? AND MONTH(log_date)=? AND YEAR(log_date)=? ORDER BY log_date DESC LIMIT 15',
                      [$eid, (int)date('n'), (int)date('Y')]);
          foreach ($rows as $r): ?>
            <tr><td><?= e(date_id($r['log_date'])) ?></td>
            <td><?= $r['check_in_at'] ? e(date('H:i', strtotime($r['check_in_at']))) : '-' ?></td>
            <td><?= $r['check_out_at'] ? e(date('H:i', strtotime($r['check_out_at']))) : '-' ?></td>
            <td><?= (int)$r['late_minutes'] > 0 ? '<span class="badge bg-danger">' . (int)$r['late_minutes'] . ' mnt</span>' : '<span class="text-success">on-time</span>' ?></td>
            <td><?= $r['check_in_photo'] ? '<a href="' . e($r['check_in_photo']) . '" target="_blank"><i class="bi bi-image"></i></a>' : '-' ?></td></tr>
          <?php endforeach; if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-3">Belum ada log bulan ini.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
const CFG = <?= json_encode($cfg) ?>;
let lat=null, lng=null, photoB64=null, deferredPrompt=null;

/* jam live */
setInterval(()=>{document.getElementById('liveClock').textContent=new Date().toLocaleTimeString('id-ID');},1000);

/* PWA install prompt */
window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();deferredPrompt=e;});
function installPWA(){ if(deferredPrompt){deferredPrompt.prompt();}else{alert('Buka via browser mobile → menu ⋮ → Add to Home Screen.');} }

/* GPS */
function getGPS(cb){
  if(!navigator.geolocation){cb(false,'Perangkat tidak mendukung GPS');return;}
  navigator.geolocation.getCurrentPosition(p=>{lat=p.coords.latitude;lng=p.coords.longitude;
    document.getElementById('gpsStatus').innerHTML='<i class="bi bi-geo-alt-fill text-success me-1"></i>Lokasi terkunci: '+lat.toFixed(6)+', '+lng.toFixed(6)+' (akurasi ±'+Math.round(p.coords.accuracy)+'m)';
    cb(true);},
    err=>cb(false,'GPS ditolak/tidak tersedia: '+err.message),{enableHighAccuracy:true,timeout:15000,maximumAge:30000});
}
getGPS((ok,msg)=>{if(!msg)return;document.getElementById('gpsStatus').innerHTML='<span class="text-danger">'+msg+'</span>';});

/* Kamera */
let stream=null;
async function startCam(){
  try{
    stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user',width:{ideal:720}},audio:false});
    document.getElementById('video').srcObject=stream;
    document.getElementById('video').classList.remove('d-none');
    document.getElementById('preview').classList.add('d-none');
  }catch(e){alert('Kamera tidak dapat diakses: '+e.message+'\nAnda masih bisa absen tanpa foto bila kebijakan kantor mengizinkan.');}
}

/* Kompres foto sisi klien sebelum upload */
function capture(){
  const v=document.getElementById('video');
  if(!stream||!v.videoWidth){alert('Buka kamera dulu.');return;}
  const c=document.getElementById('canvas');
  const maxSide=CFG.maxSide||640;
  let w=v.videoWidth,h=v.videoHeight;
  const scale=Math.min(1,maxSide/Math.max(w,h));
  c.width=Math.round(w*scale);c.height=Math.round(h*scale);
  const ctx=c.getContext('2d');
  ctx.drawImage(v,0,0,c.width,c.height);
  // mirror agar selfie tidak terbalik
  const out=document.createElement('canvas');out.width=c.width;out.height=c.height;
  const octx=out.getContext('2d');octx.translate(out.width,0);octx.scale(-1,1);octx.drawImage(c,0,0);
  photoB64=out.toDataURL('image/jpeg', (CFG.quality||70)/100);
  const kb=Math.round(photoB64.length*0.75/1024);
  document.getElementById('preview').src=photoB64;
  document.getElementById('preview').classList.remove('d-none');
  document.getElementById('video').classList.add('d-none');
  document.getElementById('photoInfo').innerHTML='Ukuran setelah kompresi: <b>'+kb+' KB</b> ('+out.width+'×'+out.height+')'+(kb>(CFG.maxKb||200)?' — <span class="text-danger">melebihi batas!</span> ambil ulang':' ✅');
  if(stream){stream.getTracks().forEach(t=>t.stop());stream=null;}
}

/* Submit ke API */
function doAttd(type){
  if(lat===null||lng===null){getGPS(ok=>{if(!ok){alert('Izinkan akses GPS untuk absensi.');}else doAttd(type);});return;}
  if(!photoB64){alert('Ambil foto selfie terlebih dahulu.');return;}
  const btn=document.querySelector(type==='in'?'#btnIn':'#btnOut'); if(btn)btn.disabled=true;
  fetch('api_attendance.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CFG.csrf},
    body:JSON.stringify({type,lat,lng,photo:photoB64})})
  .then(r=>r.json()).then(d=>{
    const box=document.getElementById('attMsg');
    box.innerHTML='<div class="alert alert-'+(d.ok?'success':'danger')+' mt-3 small">'+d.msg+'</div>';
    if(d.ok)setTimeout(()=>location.reload(),1500); else if(btn)btn.disabled=false;
  }).catch(e=>{alert('Gagal mengirim: '+e);if(btn)btn.disabled=false;});
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
