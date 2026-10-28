<?php
/**
 * API Absensi (AJAX dari halaman PWA attendance.php).
 * Validasi GPS vs radius branch, simpan selfie yang sudah dikompresi klien.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'msg' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}
verify_csrf();

$in = json_decode(file_get_contents('php://input'), true);
$type = $in['type'] ?? '';
$lat = isset($in['lat']) ? (float)$in['lat'] : null;
$lng = isset($in['lng']) ? (float)$in['lng'] : null;
$photo = $in['photo'] ?? '';
$eid = employee_id();

if (!$eid || !in_array($type, ['in', 'out'], true) || $lat === null || $lng === null) {
    echo json_encode(['ok' => false, 'msg' => 'Data tidak lengkap (perlu GPS & tipe absen).']);
    exit;
}

$emp = one('SELECT * FROM employees WHERE id=? AND flag="active"', [$eid]);
if (!$emp) { echo json_encode(['ok' => false, 'msg' => 'Data karyawan tidak aktif.']); exit; }

$aset = attendance_settings();
$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');

/* Jadwal hari ini utk tentukan branch & jam */
$sched = one('SELECT s.*, b.name branch_name, b.latitude, b.longitude, b.radius_meter
              FROM schedules s JOIN branches b ON b.id=s.branch_id
              WHERE s.employee_id=? AND ? BETWEEN s.start_date AND s.end_date LIMIT 1', [$eid, $today]);
$branchId = $sched['branch_id'] ?? $emp['branch_id'];
$b = one('SELECT * FROM branches WHERE id=?', [$branchId]);

/* Verifikasi jarak GPS */
$maxDist = null;
if ($b && $b['latitude'] !== null) {
    $dist = distance_meters($lat, $lng, (float)$b['latitude'], (float)$b['longitude']);
    $maxDist = $dist;
    if ($dist > (int)$b['radius_meter']) {
        echo json_encode(['ok' => false, 'msg' => "Lokasi di luar radius kantor {$b['name']} (jarak {$dist}m > " . (int)$b['radius_meter'] . "m). Absensi ditolak."]);
        exit;
    }
}

/* Simpan foto selfie */
$photoPath = null;
if ($photo) {
    $kb = strlen($photo) * 0.75 / 1024;
    if ($kb > (int)$aset['selfie_max_kb'] * 1.5) {
        echo json_encode(['ok' => false, 'msg' => 'Foto terlalu besar setelah kompresi. Silakan ambil ulang.']);
        exit;
    }
    $photoPath = save_base64_image($photo, 'selfie');
}

$log = one('SELECT * FROM attendance_logs WHERE employee_id=? AND log_date=?', [$eid, $today]);

if ($type === 'in') {
    if ($log && $log['check_in_at']) {
        echo json_encode(['ok' => false, 'msg' => 'Anda sudah check-in hari ini pada ' . date('H:i', strtotime($log['check_in_at'])) . '.']);
        exit;
    }
    /* Hitung keterlambatan terhadap jadwal */
    $late = 0;
    $planIn = $sched['check_in_time'] ?? '08:00:00';
    $deadline = date('Y-m-d') . ' ' . $planIn;
    $limitTs = strtotime($deadline) + (int)$aset['grace_minutes'] * 60;
    if (strtotime($now) > $limitTs) {
        $late = (int)floor((strtotime($now) - strtotime($deadline)) / 60);
    }
    if ($log) {
        exec_sql('UPDATE attendance_logs SET check_in_at=?, check_in_lat=?, check_in_lng=?, check_in_distance=?, check_in_photo=?, late_minutes=?, branch_id=?, source="PWA" WHERE id=?',
            [$now, $lat, $lng, $maxDist, $photoPath, $late, $branchId, $log['id']]);
    } else {
        exec_sql('INSERT INTO attendance_logs (employee_id,branch_id,log_date,check_in_at,check_in_lat,check_in_lng,check_in_distance,check_in_photo,late_minutes,source) VALUES (?,?,?,?,?,?,?,?,?, "PWA")',
            [$eid, $branchId, $today, $now, $lat, $lng, $maxDist, $photoPath, $late]);
    }
    $msg = 'Check-in berhasil pukul ' . date('H:i', strtotime($now));
    $msg .= $late > 0 ? ". <b>Terlambat $late menit</b> (potongan gaji diterapkan saat payroll)." : ' — Tepat waktu 👍';
    echo json_encode(['ok' => true, 'msg' => $msg]);
    audit('CHECK_IN', "$eid @ $now late=$late");
} else {
    if (!$log || !$log['check_in_at']) {
        echo json_encode(['ok' => false, 'msg' => 'Anda belum check-in hari ini.']);
        exit;
    }
    if ($log['check_out_at']) {
        echo json_encode(['ok' => false, 'msg' => 'Anda sudah check-out pada ' . date('H:i', strtotime($log['check_out_at'])) . '.']);
        exit;
    }
    $workHours = round((strtotime($now) - strtotime($log['check_in_at'])) / 3600, 2);
    $ot = 0;
    if ($sched) {
        $planOut = strtotime(date('Y-m-d') . ' ' . $sched['check_out_time']);
        if (strtotime($now) > $planOut + 1800) $ot = round((strtotime($now) - $planOut) / 3600, 2);
    }
    exec_sql('UPDATE attendance_logs SET check_out_at=?, check_out_lat=?, check_out_lng=?, check_out_distance=?, check_out_photo=?, work_hours=?, overtime_hours=? WHERE id=?',
        [$now, $lat, $lng, $maxDist, $photoPath, $workHours, $ot, $log['id']]);
    echo json_encode(['ok' => true, 'msg' => 'Check-out berhasil pukul ' . date('H:i', strtotime($now)) . '. Total kerja ' . $workHours . ' jam.' . ($ot > 0 ? ' Lembur otomatis: ' . $ot . ' jam (ajukan lewat menu Lembur).' : '')]);
    audit('CHECK_OUT', "$eid @ $now");
}
