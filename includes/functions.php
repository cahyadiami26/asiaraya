<?php
/**
 * Flash messages + util umum.
 */
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $page): void
{
    header('Location: ' . $page);
    exit;
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function money(float|string|null $n): string
{
    return number_format((float)($n ?? 0), 0, ',', '.');
}

function rupiah(float|string|null $n): string
{
    return 'Rp ' . money($n);
}

function date_id(?string $d, bool $withDay = false): string
{
    if (!$d) return '-';
    $ts = strtotime($d);
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $out = date('j', $ts) . ' ' . $bulan[(int)date('n',$ts)] . ' ' . date('Y', $ts);
    return $withDay ? $hari[(int)date('w',$ts)] . ', ' . $out : $out;
}

function time_hm(?string $t): string
{
    return $t ? substr($t, 0, 5) : '-';
}

/** Generate kode karyawan baru dari format id_formats */
function generate_employee_code(): string
{
    $fmt = one('SELECT * FROM id_formats WHERE id=1');
    $year = date('Y');
    $num = str_pad((string)$fmt['next_number'], (int)$fmt['number_length'], '0', STR_PAD_LEFT);
    $code = $fmt['prefix']
          . ($fmt['year_part'] ? $fmt['separator'] . $year : '')
          . $fmt['separator'] . $num;
    exec_sql('UPDATE id_formats SET next_number=next_number+1, sample=? WHERE id=1', [$code]);
    return $code;
}

/** Preview kode tanpa menaikkan counter */
function preview_employee_code(): string
{
    $fmt = one('SELECT * FROM id_formats WHERE id=1');
    $year = date('Y');
    $num = str_pad((string)$fmt['next_number'], (int)$fmt['number_length'], '0', STR_PAD_LEFT);
    return $fmt['prefix'] . ($fmt['year_part'] ? $fmt['separator'] . $year : '') . $fmt['separator'] . $num;
}

function company(): array
{
    static $c = null;
    if ($c === null) {
        $c = one('SELECT * FROM company_settings WHERE id=1') ?: ['name' => APP_NAME, 'logo_path' => null];
    }
    return $c;
}

function attendance_settings(): array
{
    return one('SELECT * FROM attendance_settings WHERE id=1') ?? [];
}
function overtime_settings(): array
{
    return one('SELECT * FROM overtime_settings WHERE id=1') ?? [];
}
function payroll_settings(): array
{
    return one('SELECT * FROM payroll_settings WHERE id=1') ?? [];
}

/** Hitung jarak dua koordinat (meter) - haversine */
function distance_meters(float $lat1, float $lng1, float $lat2, float $lng2): int
{
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
    return (int)round($R * 2 * atan2(sqrt($a), sqrt(1 - $a)));
}

/** Simpan upload gambar (base64 data-uri atau file) -> path relatif */
function save_base64_image(string $base64, string $folder): ?string
{
    if (preg_match('#^data:image/\w+;base64,#', $base64)) {
        $base64 = substr($base64, strpos($base64, ',') + 1);
    }
    $data = base64_decode($base64);
    if ($data === false || strlen($data) < 100) return null;
    $info = @getimagesizefromstring($data);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG])) return null;
    $dir = UPLOAD_DIR . '/' . $folder;
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = $folder . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
    $path = $dir . '/' . $name;
    imagejpeg(@imagecreatefromstring($data), $path, 80);
    return 'uploads/' . $folder . '/' . $name;
}

function audit(string $action, string $details = ''): void
{
    try {
        $uid = current_user()['id'] ?? null;
        exec_sql('INSERT INTO audit_logs (user_id, action, details) VALUES (?,?,?)', [$uid, $action, $details]);
    } catch (Throwable $t) { /* silent */ }
}

const MONTHS_ID = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

function month_name(int $m): string
{
    return MONTHS_ID[$m] ?? '';
}

function status_badge(string $s): string
{
    $map = [
        'PENDING_MGR'   => ['warning',  'Menunggu Manager'],
        'PENDING_HR'    => ['info',     'Menunggu HR'],
        'APPROVED'      => ['success',  'Disetujui'],
        'REJECTED'      => ['danger',   'Ditolak'],
        'OPEN'          => ['secondary','Open'],
        'LOCKED'        => ['info',     'Locked'],
        'SENT_FINANCE'  => ['primary',  'Dikirim ke Finance'],
        'PAID'          => ['success',  'Paid'],
        'DRAFT'         => ['secondary','Draft'],
        'FINAL'         => ['info',     'Final'],
        'active'        => ['success',  'Active'],
        'resign'        => ['secondary','Resign'],
    ];
    [$cls, $label] = $map[$s] ?? ['secondary', $s];
    return '<span class="badge bg-' . $cls . '">' . e($label) . '</span>';
}
