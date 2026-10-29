<?php
/**
 * Global view/HTTP helpers. Plain functions keep templates terse.
 */
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Core\Settings;

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return $GLOBALS['config'][$key] ?? $default;
    }
}

if (!function_exists('e')) {
    /** HTML-escape for output. */
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('base_path')) {
    function base_path(): string
    {
        static $base = null;
        if ($base !== null) {
            return $base;
        }
        $configured = (string) config('app_url', '');
        if ($configured !== '') {
            $path = parse_url($configured, PHP_URL_PATH) ?: '';
            return $base = rtrim($path, '/');
        }
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($dir === '.' || $dir === '/' || str_contains($dir, '/public/') || str_ends_with($dir, '/public')) {
            $dir = str_contains($dir, '/public') ? preg_replace('#/public$#', '', $dir) : $dir;
            $dir = $dir === '/' || $dir === '.' ? '' : $dir;
        }
        return $base = $dir;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }
        return base_path() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('upload_url')) {
    function upload_url(?string $path): string
    {
        if (!$path) {
            return '';
        }
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }
        return url('media/' . ltrim($path, '/'));
    }
}

if (!function_exists('current_path')) {
    function current_path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = base_path();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('is_path')) {
    function is_path(string $pattern): bool
    {
        $current = current_path();
        if ($pattern === '/') {
            return $current === '/';
        }
        return $current === $pattern || str_starts_with($current, rtrim($pattern, '/') . '/');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): never
    {
        $target = preg_match('#^https?://#', $path) ? $path : url($path);
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header('Location: ' . $target);
            exit;
        }
        echo '<meta http-equiv="refresh" content="0;url=' . e($target) . '">';
        exit;
    }
}

if (!function_exists('back')) {
    function back(string $fallback = '/'): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        redirect($ref !== '' ? $ref : $fallback);
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old'][$key] ?? $default;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }
}

if (!function_exists('auth')) {
    function auth(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('auth_id')) {
    function auth_id(): ?int
    {
        $user = Auth::user();
        return $user ? (int) $user['id'] : null;
    }
}

if (!function_exists('has_role')) {
    function has_role(string|array $roles): bool
    {
        return Auth::hasRole($roles);
    }
}

if (!function_exists('can')) {
    function can(string $ability): bool
    {
        return Auth::can($ability);
    }
}

if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}

if (!function_exists('rupiah')) {
    function rupiah(mixed $amount, bool $prefix = true): string
    {
        $value = (float) ($amount ?? 0);
        $formatted = number_format($value, 0, ',', '.');
        return $prefix ? 'Rp ' . $formatted : $formatted;
    }
}

if (!function_exists('rupiah_short')) {
    function rupiah_short(mixed $amount): string
    {
        $value = (float) $amount;
        if ($value >= 1_000_000_000) {
            return 'Rp ' . rtrim(rtrim(number_format($value / 1_000_000_000, 1, ',', '.'), '0'), ',') . ' M';
        }
        if ($value >= 1_000_000) {
            return 'Rp ' . rtrim(rtrim(number_format($value / 1_000_000, 1, ',', '.'), '0'), ',') . ' jt';
        }
        if ($value >= 1_000) {
            return 'Rp ' . number_format($value / 1000, 0, ',', '.') . ' rb';
        }
        return rupiah($value);
    }
}

if (!function_exists('number_id')) {
    function number_id(mixed $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }
}

if (!function_exists('tanggal')) {
    function tanggal(?string $datetime, string $format = 'd M Y'): string
    {
        if (!$datetime || str_starts_with((string) $datetime, '0000')) {
            return '-';
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return '-';
        }
        $months = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $replace = [
            'd' => date('d', $ts),
            'j' => date('j', $ts),
            'M' => $months[(int) date('n', $ts)],
            'm' => date('m', $ts),
            'Y' => date('Y', $ts),
            'y' => date('y', $ts),
            'H' => date('H', $ts),
            'i' => date('i', $ts),
            's' => date('s', $ts),
        ];
        return preg_replace_callback('/d|j|M|m|Y|y|H|i|s/', static fn ($m) => $replace[$m[0]], $format);
    }
}

if (!function_exists('tanggal_panjang')) {
    function tanggal_panjang(?string $date): string
    {
        if (!$date) {
            return '-';
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return '-';
        }
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return $days[(int) date('w', $ts)] . ', ' . date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    }
}

if (!function_exists('hari')) {
    function hari(?string $date): string
    {
        $ts = $date ? strtotime($date) : time();
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return $days[(int) date('w', $ts)];
    }
}

if (!function_exists('bulan_id')) {
    function bulan_id(int $month): string
    {
        $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return $months[$month] ?? '-';
    }
}

if (!function_exists('waktu_lalu')) {
    function waktu_lalu(?string $datetime): string
    {
        if (!$datetime) {
            return '-';
        }
        $diff = time() - strtotime($datetime);
        if ($diff < 60) {
            return 'baru saja';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . ' menit lalu';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . ' jam lalu';
        }
        if ($diff < 604800) {
            return floor($diff / 86400) . ' hari lalu';
        }
        return tanggal($datetime);
    }
}

if (!function_exists('initials')) {
    function initials(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '?';
        }
        $parts = preg_split('/\s+/', $name) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $second = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
        return mb_strtoupper($first . $second);
    }
}

if (!function_exists('avatar_color')) {
    function avatar_color(?string $seed): string
    {
        $palette = ['#1d4ed8', '#0f766e', '#b45309', '#9333ea', '#be123c', '#0e7490', '#4d7c0f', '#7c2d12'];
        $index = abs(crc32((string) $seed)) % count($palette);
        return $palette[$index];
    }
}

if (!function_exists('input')) {
    function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }
}

if (!function_exists('str_limit')) {
    function str_limit(?string $value, int $limit = 40, string $end = '…'): string
    {
        $value = (string) $value;
        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit) . $end : $value;
    }
}

if (!function_exists('active_class')) {
    function active_class(string $pattern, string $class = 'active'): string
    {
        return is_path($pattern) ? $class : '';
    }
}

if (!function_exists('query_params')) {
    function query_params(array $overrides = []): string
    {
        $params = array_merge($_GET, $overrides);
        $params = array_filter($params, static fn ($v) => $v !== null && $v !== '');
        return $params ? '?' . http_build_query($params) : '';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('status_badge')) {
    /** Renders a consistent pill badge for a workflow status. */
    function status_badge(?string $status, ?string $label = null): string
    {
        $status = strtoupper((string) $status);
        $map = [
            'PENDING'         => ['amber', 'Menunggu'],
            'MANAGER_APPROVED'=> ['teal', 'Disetujui Atasan'],
            'APPROVED'        => ['teal', 'Disetujui'],
            'HR_APPROVED'     => ['teal', 'Disetujui HR'],
            'REJECTED'        => ['rose', 'Ditolak'],
            'CANCELLED'       => ['slate', 'Dibatalkan'],
            'DRAFT'           => ['slate', 'Draft'],
            'CALCULATED'      => ['indigo', 'Terhitung'],
            'SENT_TO_FINANCE' => ['amber', 'Di Finance'],
            'FINANCE_APPROVED'=> ['teal', 'Disetujui Finance'],
            'PAID'            => ['teal', 'Lunas'],
            'UNPAID'          => ['amber', 'Belum Dibayar'],
            'ACTIVE'          => ['teal', 'Aktif'],
            'RESIGN'          => ['slate', 'Resign'],
            'EXPIRED'         => ['rose', 'Habis Kontrak'],
            'PRESENT'         => ['teal', 'Hadir'],
            'LATE'            => ['amber', 'Terlambat'],
            'ABSENT'          => ['rose', 'Tidak Hadir'],
            'LEAVE'           => ['indigo', 'Cuti'],
            'WFH'             => ['indigo', 'WFH'],
            'OFF'             => ['slate', 'Libur'],
            'SCHEDULED'       => ['indigo', 'Terjadwal'],
            'CONFIRMED'       => ['teal', 'Disetujui'],
        ];
        [$tone, $default] = $map[$status] ?? ['slate', ucfirst(strtolower(str_replace('_', ' ', $status ?: '-')))];
        return '<span class="badge badge-' . $tone . '">' . e($label ?? $default) . '</span>';
    }
}

if (!function_exists('icon')) {
    /** Inline SVG icon (Feather/Lucide outline style) - no CDN dependency. */
    function icon(string $name, string $class = 'ico'): string
    {
        $paths = [
            'grid'        => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
            'building'    => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2M10 21v-3h4v3"/>',
            'users'       => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'user'        => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'calendar'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
            'calendar-check' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M9 16l2 2 4-4"/>',
            'map-pin'     => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
            'camera'      => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
            'wallet'      => '<path d="M20 12V8a2 2 0 0 0-2-2H5a2 2 0 0 1 0-4h12a2 2 0 0 1 2 2v2"/><path d="M3 6v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/>',
            'file'        => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6"/>',
            'check'       => '<path d="M20 6 9 17l-5-5"/>',
            'x'           => '<path d="M18 6 6 18M6 6l12 12"/>',
            'plus'        => '<path d="M12 5v14M5 12h14"/>',
            'search'      => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'bell'        => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
            'logout'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
            'shield'      => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
            'settings'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
            'trending'    => '<path d="m23 6-9.5 9.5-5-5L1 18"/><path d="M17 6h6v6"/>',
            'trending-down' => '<path d="m23 18-9.5-9.5-5 5L1 6"/><path d="M17 18h6v-6"/>',
            'download'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5M12 15V3"/>',
            'upload'      => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5M12 3v12"/>',
            'filter'      => '<path d="M22 3H2l8 9.5V19l4 2v-8.5z"/>',
            'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
            'chevron-left'  => '<path d="m15 18-6-6 6-6"/>',
            'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
            'menu'        => '<path d="M3 6h18M3 12h18M3 18h18"/>',
            'briefcase'   => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
            'layers'      => '<path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 17l9 5 9-5"/>',
            'activity'    => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
            'credit-card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
            'receipt'     => '<path d="M4 2h16v20l-3-2-3 2-3-2-3 2-3-2Z"/><path d="M8 7h8M8 11h8M8 15h5"/>',
            'list'        => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
            'sliders'     => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
            'alert'       => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>',
            'info'        => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
            'lock'        => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'mail'        => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/>',
            'phone'       => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2Z"/>',
            'edit'        => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4Z"/>',
            'trash'       => '<path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
            'eye'         => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
            'key'         => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m11 12 9-9M17 6l3 3M14 9l3 3"/>',
            'arrow-right' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
            'arrow-left'  => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
            'refresh'     => '<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>',
            'printer'     => '<path d="M6 9V3h12v6"/><rect x="2" y="9" width="20" height="8" rx="2"/><path d="M6 17h12v4H6z"/>',
            'qr'          => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM19 19h2v2h-2zM19 14h2M14 19v2"/>',
            'smartphone'  => '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/>',
            'sun'         => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19"/>',
            'moon'        => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>',
            'database'    => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
            'home'        => '<path d="m3 10 9-7 9 7v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><path d="M9 21v-8h6v8"/>',
            'send'        => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
            'copy'        => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
            'award'       => '<circle cx="12" cy="8" r="6"/><path d="m8.2 13.4-1.2 8L12 18l5 3.4-1.2-8"/>',
        ];
        $body = $paths[$name] ?? $paths['info'];
        return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
    }
}

if (!function_exists('json_encode_pretty')) {
    function json_encode_pretty(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}

if (!function_exists('setting_groups')) {
    function setting_groups(): array
    {
        return [
            'company'    => 'Perusahaan',
            'attendance' => 'Presensi',
            'overtime'   => 'Lembur',
            'payroll'    => 'Payroll',
            'bpjs'       => 'BPJS & PPh',
            'leave'      => 'Cuti',
        ];
    }
}

if (!function_exists('role_label')) {
    function role_label(?string $role): string
    {
        return match ($role) {
            'HR'       => 'HR Administrator',
            'FIN'      => 'Finance',
            'KARYAWAN' => 'Karyawan',
            default    => 'Pengguna',
        };
    }
}

if (!function_exists('role_badge')) {
    function role_badge(?string $role): string
    {
        $map = ['HR' => 'indigo', 'FIN' => 'amber', 'KARYAWAN' => 'slate'];
        return '<span class="badge badge-' . ($map[$role] ?? 'slate') . '">' . e(role_label($role)) . '</span>';
    }
}

if (!function_exists('flag_badge')) {
    function flag_badge(?string $flag): string
    {
        return match ($flag) {
            'ACTIVE'  => '<span class="badge badge-teal">Aktif</span>',
            'RESIGN'  => '<span class="badge badge-slate">Resign</span>',
            'EXPIRED' => '<span class="badge badge-rose">Habis Kontrak</span>',
            default   => '<span class="badge badge-slate">-</span>',
        };
    }
}
