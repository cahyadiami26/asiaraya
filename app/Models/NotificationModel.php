<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Inbox notifikasi in-app (lonceng).
 *
 * Notifikasi dibuat oleh modul lain (cuti, lembur, payroll, klaim, pengumuman)
 * lewat Database::insert('notifications', ...). Model ini hanya membaca,
 * menandai dibaca, dan menyajikan ringkasan untuk lonceng.
 */
final class NotificationModel
{
    public const TYPES = [
        'info'         => ['label' => 'Informasi', 'icon' => 'info', 'tone' => ''],
        'success'      => ['label' => 'Berhasil', 'icon' => 'check', 'tone' => 'teal'],
        'warning'      => ['label' => 'Perhatian', 'icon' => 'alert', 'tone' => 'amber'],
        'danger'       => ['label' => 'Penting', 'icon' => 'alert', 'tone' => 'rose'],
        'payroll'      => ['label' => 'Payroll', 'icon' => 'wallet', 'tone' => 'teal'],
        'announcement' => ['label' => 'Pengumuman', 'icon' => 'bell', 'tone' => 'indigo'],
        'approval'     => ['label' => 'Persetujuan', 'icon' => 'check', 'tone' => 'indigo'],
        'contract'     => ['label' => 'Kontrak', 'icon' => 'file', 'tone' => 'amber'],
        'leave'        => ['label' => 'Cuti', 'icon' => 'calendar', 'tone' => 'teal'],
    ];

    /** Daftar notifikasi satu karyawan. */
    public function list(int $employeeId, array $filters, int $limit = 20, int $offset = 0): array
    {
        $where = ['employee_id = ?'];
        $params = [$employeeId];

        if (!empty($filters['unread'])) {
            $where[] = 'read_at IS NULL';
        }
        if (($filters['type'] ?? '') !== '' && isset(self::TYPES[$filters['type']])) {
            $where[] = 'type = ?';
            $params[] = $filters['type'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(title LIKE ? OR message LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        return Database::all(
            'SELECT * FROM notifications WHERE ' . implode(' AND ', $where) . '
             ORDER BY id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $params
        );
    }

    public function count(int $employeeId, array $filters): int
    {
        $where = ['employee_id = ?'];
        $params = [$employeeId];

        if (!empty($filters['unread'])) {
            $where[] = 'read_at IS NULL';
        }
        if (($filters['type'] ?? '') !== '' && isset(self::TYPES[$filters['type']])) {
            $where[] = 'type = ?';
            $params[] = $filters['type'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(title LIKE ? OR message LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        return (int) Database::value('SELECT COUNT(*) FROM notifications WHERE ' . implode(' AND ', $where), $params, 0);
    }

    public function unreadCount(int $employeeId): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM notifications WHERE employee_id = ? AND read_at IS NULL',
            [$employeeId],
            0
        );
    }

    /** Notifikasi terbaru untuk dropdown lonceng. */
    public function recent(int $employeeId, int $limit = 5): array
    {
        return Database::all(
            'SELECT * FROM notifications WHERE employee_id = ?
             ORDER BY (read_at IS NULL) DESC, id DESC LIMIT ' . max(1, $limit),
            [$employeeId]
        );
    }

    public function find(int $id, int $employeeId): ?array
    {
        return Database::one('SELECT * FROM notifications WHERE id = ? AND employee_id = ?', [$id, $employeeId]);
    }

    public function markRead(int $id, int $employeeId): void
    {
        Database::run(
            'UPDATE notifications SET read_at = ? WHERE id = ? AND employee_id = ? AND read_at IS NULL',
            [date('Y-m-d H:i:s'), $id, $employeeId]
        );
    }

    public function markAllRead(int $employeeId): int
    {
        return Database::run(
            'UPDATE notifications SET read_at = ? WHERE employee_id = ? AND read_at IS NULL',
            [date('Y-m-d H:i:s'), $employeeId]
        )->rowCount();
    }

    /** Ringkasan jumlah per tipe (untuk chip filter). */
    public function typeCounts(int $employeeId): array
    {
        $rows = Database::all(
            'SELECT type, COUNT(*) AS total, SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) AS unread
               FROM notifications WHERE employee_id = ? GROUP BY type',
            [$employeeId]
        );

        $counts = ['total' => 0, 'unread' => 0];
        foreach ($rows as $row) {
            $counts[$row['type']] = (int) $row['total'];
            $counts['total'] += (int) $row['total'];
            $counts['unread'] += (int) $row['unread'];
        }
        return $counts;
    }

    public static function meta(string $type): array
    {
        return self::TYPES[$type] ?? self::TYPES['info'];
    }
}
