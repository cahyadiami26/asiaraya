<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Sidebar navigation definition.
 *
 * Every entry declares the named route it points at and a stable key. Items and
 * groups can be hidden through `app/config/menu.php` (defaults for deployment)
 * or through the in-app setting **Pengaturan → Menu Navigasi**, which stores the
 * hidden keys in the `settings` table. Hiding a menu never changes RBAC: a
 * hidden URL stays reachable for roles that are allowed to open it.
 */
final class Nav
{
    /** Item yang tidak boleh disembunyikan (agar HR dapat kembali ke pengaturan). */
    public const ALWAYS_ON = ['dashboard', 'settings.index'];

    /** @var array<string,mixed>|null konfigurasi app/config/menu.php (cache per request) */
    private static ?array $config = null;

    /** @var array<int,string>|null cache kunci item yang disembunyikan */
    private static ?array $hiddenItems = null;

    /** @var array<int,string>|null cache kunci grup yang disembunyikan */
    private static ?array $hiddenGroups = null;

    /**
     * Definisi menu. `key` dipakai sebagai identitas untuk pengaturan tampilan;
     * bila tidak diisi, nama route dipakai.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function groups(): array
    {
        return [
            [
                'key' => 'main',
                'label' => null,
                'items' => [
                    ['route' => 'dashboard', 'label' => 'Dashboard Utama', 'icon' => 'grid', 'roles' => ['HR', 'FIN', 'KARYAWAN']],
                ],
            ],
            [
                'key' => 'org',
                'label' => 'Manajemen Organisasi',
                'roles' => ['HR'],
                'items' => [
                    ['route' => 'org.index', 'label' => 'Unit Organisasi & Format ID', 'icon' => 'building', 'roles' => ['HR']],
                    ['route' => 'company.edit', 'label' => 'Pengaturan Perusahaan', 'icon' => 'settings', 'roles' => ['HR']],
                ],
            ],
            [
                'key' => 'hr',
                'label' => 'Kepegawaian',
                'roles' => ['HR'],
                'items' => [
                    ['route' => 'employees.index', 'label' => 'Data Karyawan', 'icon' => 'users', 'roles' => ['HR']],
                    ['route' => 'employees.contracts', 'label' => 'Kontrak & Status', 'icon' => 'file', 'roles' => ['HR'], 'badge_key' => 'contracts'],
                    ['route' => 'documents.index', 'label' => 'Dokumen Karyawan', 'icon' => 'layers', 'roles' => ['HR'], 'badge_key' => 'documents'],
                    ['route' => 'careers.index', 'label' => 'Riwayat Jabatan', 'icon' => 'trending', 'roles' => ['HR']],
                ],
            ],
            [
                'key' => 'ops',
                'label' => 'Operasional Kerja',
                'roles' => ['HR', 'KARYAWAN'],
                'items' => [
                    ['route' => 'shifts.index', 'label' => 'Jadwal Kerja & Shift', 'icon' => 'calendar', 'roles' => ['HR']],
                    ['route' => 'schedules.index', 'label' => 'Penjadwalan Karyawan', 'icon' => 'calendar-check', 'roles' => ['HR']],
                    ['route' => 'schedules.requests', 'label' => 'Perubahan Jadwal', 'icon' => 'refresh', 'roles' => ['HR', 'KARYAWAN'], 'badge_key' => 'shift'],
                    ['route' => 'attendance.live', 'label' => 'Presensi & GPS Live Log', 'icon' => 'map-pin', 'roles' => ['HR'], 'live' => true],
                    ['route' => 'attendance.daily', 'label' => 'Presensi Harian', 'icon' => 'list', 'roles' => ['HR']],
                    ['route' => 'attendance.rekap', 'label' => 'Rekap Presensi Bulanan', 'icon' => 'receipt', 'roles' => ['HR']],
                    ['route' => 'overtime.index', 'label' => 'Lembur (Overtime)', 'icon' => 'clock', 'roles' => ['HR'], 'badge_key' => 'overtime'],
                    ['route' => 'overtime.schedules', 'label' => 'Manajemen Lembur Terjadwal', 'icon' => 'calendar-check', 'roles' => ['HR']],
                    ['route' => 'leave.index', 'key' => 'leave.manage', 'label' => 'Manajemen Cuti', 'icon' => 'file', 'roles' => ['HR'], 'badge_key' => 'leave'],
                    ['route' => 'leave.balances', 'label' => 'Saldo Cuti Karyawan', 'icon' => 'layers', 'roles' => ['HR']],
                    ['route' => 'leave.types', 'label' => 'Jenis & Jatah Cuti', 'icon' => 'settings', 'roles' => ['HR']],
                ],
            ],
            [
                'key' => 'payroll',
                'label' => 'Penggajian',
                'roles' => ['HR', 'FIN'],
                'items' => [
                    ['route' => 'payroll.index', 'label' => 'Payroll', 'icon' => 'wallet', 'roles' => ['HR', 'FIN']],
                    ['route' => 'components.index', 'label' => 'Komponen Gaji', 'icon' => 'layers', 'roles' => ['HR']],
                    ['route' => 'claims.index', 'label' => 'Klaim & Reimbursement', 'icon' => 'wallet', 'roles' => ['HR', 'FIN'], 'badge_key' => 'claims'],
                    ['route' => 'reports.index', 'label' => 'Laporan', 'icon' => 'receipt', 'roles' => ['HR', 'FIN']],
                ],
            ],
            [
                'key' => 'tax',
                'label' => 'Pajak & Jaminan Sosial',
                'roles' => ['HR', 'FIN'],
                'items' => [
                    ['route' => 'tax.index', 'label' => 'PPh 21 & 1721-A1', 'icon' => 'percent', 'roles' => ['HR', 'FIN']],
                    ['route' => 'bpjs.index', 'label' => 'Rekap BPJS', 'icon' => 'shield', 'roles' => ['HR', 'FIN']],
                ],
            ],
            [
                'key' => 'recruit',
                'label' => 'Rekrutmen & Onboarding',
                'roles' => ['HR'],
                'items' => [
                    ['route' => 'recruitment.index', 'label' => 'Lowongan & Pipeline', 'icon' => 'briefcase', 'roles' => ['HR'], 'badge_key' => 'recruitment'],
                    ['route' => 'recruitment.candidates', 'label' => 'Data Pelamar', 'icon' => 'users', 'roles' => ['HR']],
                ],
            ],
            [
                'key' => 'performance',
                'label' => 'Kinerja & Pengembangan',
                'roles' => ['HR', 'FIN', 'KARYAWAN'],
                'items' => [
                    ['route' => 'performance.index', 'label' => 'Penilaian Kinerja', 'icon' => 'award', 'roles' => ['HR'], 'badge_key' => 'performance'],
                    ['route' => 'performance.periods', 'label' => 'Periode Penilaian', 'icon' => 'calendar-check', 'roles' => ['HR']],
                    ['route' => 'performance.indicators', 'label' => 'Indikator Penilaian', 'icon' => 'layers', 'roles' => ['HR']],
                    ['route' => 'performance.team', 'label' => 'Penilaian Tim', 'icon' => 'users', 'roles' => ['HR', 'FIN', 'KARYAWAN'], 'badge_key' => 'performance_team'],
                    ['route' => 'me.performance', 'label' => 'Penilaian Saya', 'icon' => 'award', 'roles' => ['HR', 'FIN', 'KARYAWAN']],
                    ['route' => 'trainings.index', 'label' => 'Pelatihan & Sertifikasi', 'icon' => 'briefcase', 'roles' => ['HR'], 'badge_key' => 'training'],
                    ['route' => 'trainings.catalog', 'label' => 'Katalog Pelatihan', 'icon' => 'layers', 'roles' => ['HR']],
                    ['route' => 'me.trainings', 'label' => 'Pelatihan Saya', 'icon' => 'award', 'roles' => ['HR', 'FIN', 'KARYAWAN']],
                ],
            ],
            [
                'key' => 'offboarding',
                'label' => 'Offboarding & Exit',
                'roles' => ['HR', 'FIN', 'KARYAWAN'],
                'items' => [
                    ['route' => 'offboarding.index', 'label' => 'Offboarding & Exit', 'icon' => 'logout', 'roles' => ['HR'], 'badge_key' => 'offboarding'],
                    ['route' => 'me.offboarding', 'label' => 'Pengunduran Diri Saya', 'icon' => 'file', 'roles' => ['HR', 'FIN', 'KARYAWAN']],
                ],
            ],
            [
                'key' => 'comms',
                'label' => 'Komunikasi & Informasi',
                'roles' => ['HR', 'FIN', 'KARYAWAN'],
                'items' => [
                    ['route' => 'announcements.index', 'label' => 'Pengumuman', 'icon' => 'megaphone', 'roles' => ['HR', 'FIN', 'KARYAWAN'], 'badge_key' => 'announcements'],
                    ['route' => 'notifications', 'label' => 'Notifikasi', 'icon' => 'bell', 'roles' => ['HR', 'FIN', 'KARYAWAN'], 'badge_key' => 'notifications'],
                    ['route' => 'announcements.manage', 'label' => 'Kelola Pengumuman', 'icon' => 'edit', 'roles' => ['HR']],
                ],
            ],
            [
                'key' => 'portal',
                'label' => 'Portal Karyawan',
                'roles' => ['KARYAWAN'],
                'items' => [
                    ['route' => 'me.profile', 'label' => 'Profil Saya', 'icon' => 'user', 'roles' => ['KARYAWAN']],
                    ['route' => 'me.attendance', 'label' => 'Presensi Saya', 'icon' => 'map-pin', 'roles' => ['KARYAWAN']],
                    ['route' => 'me.schedule', 'label' => 'Jadwal Saya', 'icon' => 'calendar', 'roles' => ['KARYAWAN']],
                    ['route' => 'me.overtime', 'label' => 'Lembur Saya', 'icon' => 'clock', 'roles' => ['KARYAWAN']],
                    ['route' => 'me.leave', 'label' => 'Cuti Saya', 'icon' => 'file', 'roles' => ['KARYAWAN']],
                    ['route' => 'leave.index', 'key' => 'leave.approvals', 'label' => 'Persetujuan Cuti Tim', 'icon' => 'user', 'roles' => ['KARYAWAN']],
                    ['route' => 'me.claims', 'label' => 'Klaim Saya', 'icon' => 'wallet', 'roles' => ['KARYAWAN']],
                    ['route' => 'me.payslip', 'label' => 'Slip Gaji', 'icon' => 'receipt', 'roles' => ['KARYAWAN']],
                    ['route' => 'me.documents', 'label' => 'Dokumen Saya', 'icon' => 'file', 'roles' => ['KARYAWAN']],
                    ['route' => 'me.career', 'label' => 'Riwayat Jabatan Saya', 'icon' => 'trending', 'roles' => ['KARYAWAN']],
                    ['route' => 'me.tax', 'label' => 'Bukti Potong Pajak', 'icon' => 'receipt', 'roles' => ['KARYAWAN']],
                ],
            ],
            [
                'key' => 'system',
                'label' => 'Sistem & Audit',
                'roles' => ['HR'],
                'items' => [
                    ['route' => 'logs.index', 'label' => 'Log Aktivitas', 'icon' => 'activity', 'roles' => ['HR']],
                    ['route' => 'attendance.settings', 'label' => 'Pengaturan Presensi', 'icon' => 'map-pin', 'roles' => ['HR']],
                    ['route' => 'mail.index', 'label' => 'Antrean Email', 'icon' => 'mail', 'roles' => ['HR'], 'badge_key' => 'mail_pending'],
                    ['route' => 'scheduler.index', 'label' => 'Penjadwal & Cron', 'icon' => 'clock', 'roles' => ['HR']],
                    ['route' => 'settings.index', 'label' => 'Pengaturan HR & Payroll', 'icon' => 'sliders', 'roles' => ['HR']],
                    ['route' => 'system.reset', 'label' => 'Reset Sistem', 'icon' => 'database', 'roles' => ['HR']],
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------- konfigurasi */

    /**
     * Isi `app/config/menu.php` yang sudah dinormalisasi.
     *
     * @return array{hidden:array<int,string>,hidden_groups:array<int,string>,locked:array<int,string>}
     */
    public static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $file = APP_PATH . '/config/menu.php';
        $loaded = is_file($file) ? require $file : [];
        $loaded = is_array($loaded) ? $loaded : [];
        $normalize = static function (mixed $value): array {
            if (!is_array($value)) {
                return [];
            }
            $keys = [];
            foreach ($value as $key) {
                if (is_string($key) && trim($key) !== '') {
                    $keys[] = trim($key);
                }
            }

            return array_values(array_unique($keys));
        };

        return self::$config = [
            'hidden'        => $normalize($loaded['hidden'] ?? []),
            'hidden_groups' => $normalize($loaded['hidden_groups'] ?? []),
            'locked'        => $normalize($loaded['locked'] ?? []),
        ];
    }

    /** Kunci item yang tidak boleh disembunyikan. @return array<int,string> */
    public static function lockedItems(): array
    {
        return array_values(array_unique(array_merge(self::ALWAYS_ON, self::config()['locked'])));
    }

    /**
     * Grup yang tidak boleh disembunyikan karena memuat item terkunci.
     *
     * @return array<int,string>
     */
    public static function lockedGroups(): array
    {
        $locked = self::lockedItems();
        $groups = [];
        foreach (self::groups() as $group) {
            foreach ($group['items'] as $item) {
                if (in_array(self::itemKey($item), $locked, true)) {
                    $groups[] = self::groupKey($group);
                    break;
                }
            }
        }

        return array_values(array_unique($groups));
    }

    /**
     * Item yang disembunyikan.
     *
     * Bila pengaturan aplikasi sudah pernah disimpan, nilainya **menimpa**
     * (bukan digabung dengan) daftar di `app/config/menu.php`; tombol
     * "Bawaan Config" menghapus penimpaan tersebut.
     *
     * @return array<int,string>
     */
    public static function hiddenItems(): array
    {
        if (self::$hiddenItems !== null) {
            return self::$hiddenItems;
        }

        $keys = self::storedList('menu_hidden_items', self::config()['hidden']);
        $keys = array_values(array_diff(array_unique($keys), self::lockedItems()));

        return self::$hiddenItems = $keys;
    }

    /** @return array<int,string> grup yang disembunyikan seluruhnya */
    public static function hiddenGroups(): array
    {
        if (self::$hiddenGroups !== null) {
            return self::$hiddenGroups;
        }

        $keys = self::storedList('menu_hidden_groups', self::config()['hidden_groups']);
        $keys = array_values(array_diff(array_unique($keys), self::lockedGroups()));

        return self::$hiddenGroups = $keys;
    }

    /** Apakah pengaturan menu di tabel `settings` sudah menimpa nilai config. */
    public static function hasOverride(): bool
    {
        return Settings::get('menu_hidden_items') !== null || Settings::get('menu_hidden_groups') !== null;
    }

    /**
     * Daftar dari tabel `settings`; nilai config hanya dipakai selama pengaturan
     * aplikasi belum pernah disimpan.
     *
     * @param array<int,string> $fallback
     * @return array<int,string>
     */
    private static function storedList(string $key, array $fallback): array
    {
        $raw = Settings::get($key);
        if ($raw === null) {
            return $fallback;
        }
        $decoded = json_decode((string) $raw, true);

        return self::stringList(is_array($decoded) ? $decoded : []);
    }

    /** Kosongkan cache (dipakai setelah pengaturan menu disimpan). */
    public static function flush(): void
    {
        self::$config = null;
        self::$hiddenItems = null;
        self::$hiddenGroups = null;
    }

    public static function itemKey(array $item): string
    {
        $key = (string) ($item['key'] ?? $item['route'] ?? '');

        return $key !== '' ? $key : (string) ($item['label'] ?? '');
    }

    public static function groupKey(array $group): string
    {
        $key = (string) ($group['key'] ?? '');
        if ($key !== '') {
            return $key;
        }
        $label = (string) ($group['label'] ?? '');

        return $label !== '' ? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $label)) : 'main';
    }

    /** @return array<int,string> */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $entry) {
            if (is_string($entry) && trim($entry) !== '') {
                $out[] = trim($entry);
            }
        }

        return $out;
    }

    /**
     * Katalog lengkap untuk halaman pengaturan tampilan menu.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function catalog(): array
    {
        $hiddenItems = self::hiddenItems();
        $hiddenGroups = self::hiddenGroups();
        $lockedItems = self::lockedItems();
        $lockedGroups = self::lockedGroups();
        $configHidden = self::config()['hidden'];
        $configGroups = self::config()['hidden_groups'];

        $groups = [];
        foreach (self::groups() as $group) {
            $groupKey = self::groupKey($group);
            $groupHidden = in_array($groupKey, $hiddenGroups, true);
            $items = [];
            foreach ($group['items'] as $item) {
                $key = self::itemKey($item);
                $locked = in_array($key, $lockedItems, true);
                $hidden = !$locked && in_array($key, $hiddenItems, true);
                $items[] = [
                    'key'    => $key,
                    'label'  => (string) $item['label'],
                    'route'  => (string) $item['route'],
                    'icon'   => (string) ($item['icon'] ?? 'grid'),
                    'roles'  => (array) $item['roles'],
                    'locked' => $locked,
                    'hidden' => $hidden,
                    'by_config' => $hidden && in_array($key, $configHidden, true),
                ];
            }

            $groups[] = [
                'key'    => $groupKey,
                'label'  => $group['label'],
                'roles'  => (array) ($group['roles'] ?? []),
                'locked' => in_array($groupKey, $lockedGroups, true),
                'hidden' => $groupHidden,
                'by_config' => $groupHidden && in_array($groupKey, $configGroups, true),
                'items'  => $items,
            ];
        }

        return $groups;
    }

    /**
     * Ringkasan jumlah item/grup untuk kartu statistik.
     *
     * @return array{items:int,visible:int,hidden:int,groups:int,hidden_groups:int}
     */
    public static function stats(): array
    {
        $items = 0;
        $visible = 0;
        $groups = 0;
        $hiddenGroups = 0;
        foreach (self::catalog() as $group) {
            $groups++;
            if ($group['hidden']) {
                $hiddenGroups++;
                $items += count($group['items']);
                continue;
            }
            foreach ($group['items'] as $item) {
                $items++;
                if (!$item['hidden']) {
                    $visible++;
                }
            }
        }

        return [
            'items'         => $items,
            'visible'       => $visible,
            'hidden'        => $items - $visible,
            'groups'        => $groups,
            'hidden_groups' => $hiddenGroups,
        ];
    }

    /** Resolve groups/items visible for the current user. */
    public static function forUser(?array $user, array $badges = []): array
    {
        if (!$user) {
            return [];
        }
        $role = (string) $user['role'];
        $router = Router::instance();
        $hiddenItems = self::hiddenItems();
        $hiddenGroups = self::hiddenGroups();
        $groups = [];

        foreach (self::groups() as $group) {
            if (in_array(self::groupKey($group), $hiddenGroups, true)) {
                continue;
            }
            $items = [];
            foreach ($group['items'] as $item) {
                if (!in_array($role, $item['roles'], true)) {
                    continue;
                }
                if (in_array(self::itemKey($item), $hiddenItems, true)) {
                    continue;
                }
                $ready = $router->has($item['route']);
                $item['url'] = $ready
                    ? Router::route($item['route'])
                    : url('coming-soon?module=' . rawurlencode($item['label']));
                $item['path'] = $ready ? $router->path($item['route']) : null;
                $item['active'] = $ready && $item['path'] !== null && is_path($item['path']);
                $item['ready'] = $ready;
                $item['live'] = (bool) ($item['live'] ?? false);
                $item['badge'] = isset($item['badge_key']) ? (int) ($badges[$item['badge_key']] ?? 0) : 0;
                $items[] = $item;
            }
            if (!$items) {
                continue;
            }
            // Portal karyawan hanya untuk peran KARYAWAN (item lain sudah difilter peran).
            if (self::groupKey($group) === 'portal' && $role !== 'KARYAWAN') {
                continue;
            }
            $groups[] = ['label' => $group['label'], 'items' => $items];
        }

        return $groups;
    }
}
