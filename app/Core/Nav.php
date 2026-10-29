<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Sidebar navigation definition.
 *
 * Every entry declares the named route it points at. Entries whose route is
 * not registered yet are rendered as "soon" so the menu can grow batch by
 * batch without dead links.
 */
final class Nav
{
    public static function groups(): array
    {
        return [
            [
                'label' => null,
                'items' => [
                    ['route' => 'dashboard', 'label' => 'Dashboard Utama', 'icon' => 'grid', 'roles' => ['HR', 'FIN', 'KARYAWAN']],
                ],
            ],
            [
                'label' => 'Manajemen Organisasi',
                'roles' => ['HR'],
                'items' => [
                    ['route' => 'org.index', 'label' => 'Unit Organisasi & Format ID', 'icon' => 'building', 'roles' => ['HR']],
                    ['route' => 'company.edit', 'label' => 'Pengaturan Perusahaan', 'icon' => 'settings', 'roles' => ['HR']],
                ],
            ],
            [
                'label' => 'Kepegawaian',
                'roles' => ['HR'],
                'items' => [
                    ['route' => 'employees.index', 'label' => 'Data Karyawan', 'icon' => 'users', 'roles' => ['HR']],
                    ['route' => 'employees.contracts', 'label' => 'Kontrak & Status', 'icon' => 'file', 'roles' => ['HR'], 'badge_key' => 'contracts'],
                ],
            ],
            [
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
                    ['route' => 'leave.index', 'label' => 'Manajemen Cuti', 'icon' => 'file', 'roles' => ['HR'], 'badge_key' => 'leave'],
                    ['route' => 'leave.balances', 'label' => 'Saldo Cuti Karyawan', 'icon' => 'layers', 'roles' => ['HR']],
                    ['route' => 'leave.types', 'label' => 'Jenis & Jatah Cuti', 'icon' => 'settings', 'roles' => ['HR']],
                ],
            ],
            [
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
                'label' => 'Komunikasi & Informasi',
                'roles' => ['HR', 'FIN', 'KARYAWAN'],
                'items' => [
                    ['route' => 'announcements.index', 'label' => 'Pengumuman', 'icon' => 'megaphone', 'roles' => ['HR', 'FIN', 'KARYAWAN'], 'badge_key' => 'announcements'],
                    ['route' => 'notifications', 'label' => 'Notifikasi', 'icon' => 'bell', 'roles' => ['HR', 'FIN', 'KARYAWAN'], 'badge_key' => 'notifications'],
                    ['route' => 'announcements.manage', 'label' => 'Kelola Pengumuman', 'icon' => 'edit', 'roles' => ['HR']],
                ],
            ],
            [
                'label' => 'Portal Karyawan',
                'roles' => ['KARYAWAN'],
                'items' => [
                    ['route' => 'me.profile', 'label' => 'Profil Saya', 'icon' => 'user', 'roles' => ['KARYAWAN']],
                    ['route' => 'me.attendance', 'label' => 'Presensi Saya', 'icon' => 'map-pin', 'roles' => ['KARYAWAN']],
                    ['route' => 'me.schedule', 'label' => 'Jadwal Saya', 'icon' => 'calendar', 'roles' => ['KARYAWAN']],
                    ['route' => 'me.overtime', 'label' => 'Lembur Saya', 'icon' => 'clock', 'roles' => ['KARYAWAN']],
                    ['route' => 'me.leave', 'label' => 'Cuti Saya', 'icon' => 'file', 'roles' => ['KARYAWAN']],
                    ['route' => 'leave.index', 'label' => 'Persetujuan Cuti Tim', 'icon' => 'user', 'roles' => ['KARYAWAN']],
                    ['route' => 'me.claims', 'label' => 'Klaim Saya', 'icon' => 'wallet', 'roles' => ['KARYAWAN']],
                    ['route' => 'me.payslip', 'label' => 'Slip Gaji', 'icon' => 'receipt', 'roles' => ['KARYAWAN']],
                ],
            ],
            [
                'label' => 'Sistem & Audit',
                'roles' => ['HR'],
                'items' => [
                    ['route' => 'logs.index', 'label' => 'Log Aktivitas', 'icon' => 'activity', 'roles' => ['HR']],
                    ['route' => 'attendance.settings', 'label' => 'Pengaturan Presensi', 'icon' => 'map-pin', 'roles' => ['HR']],
                    ['route' => 'settings.index', 'label' => 'Pengaturan HR & Payroll', 'icon' => 'sliders', 'roles' => ['HR']],
                ],
            ],
        ];
    }

    /** Resolve groups/items visible for the current user. */
    public static function forUser(?array $user, array $badges = []): array
    {
        if (!$user) {
            return [];
        }
        $role = $user['role'];
        $router = Router::instance();
        $groups = [];

        foreach (self::groups() as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                if (!in_array($role, $item['roles'], true)) {
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
            // Hide the personal portal group for non-employee roles, and vice versa.
            if (($group['label'] ?? '') === 'Portal Karyawan' && $role !== 'KARYAWAN') {
                continue;
            }
            $groups[] = ['label' => $group['label'], 'items' => $items];
        }

        return $groups;
    }
}
