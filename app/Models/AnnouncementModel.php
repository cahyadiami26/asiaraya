<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Papan pengumuman internal.
 *
 * Sebuah pengumuman punya audiens (semua / unit / departemen / jabatan / peran),
 * jadwal terbit, masa berlaku, penanda "penting" (pin), dan pelacakan sudah
 * dibaca per karyawan (tabel announcement_reads).
 */
final class AnnouncementModel
{
    public const CATEGORIES = [
        'INFO'      => 'Informasi',
        'KEBIJAKAN' => 'Kebijakan',
        'EVENT'     => 'Acara & Kegiatan',
        'DARURAT'   => 'Darurat',
        'PAYROLL'   => 'Payroll & Benefit',
    ];

    public const CATEGORY_TONES = [
        'INFO'      => 'indigo',
        'KEBIJAKAN' => 'teal',
        'EVENT'     => 'amber',
        'DARURAT'   => 'rose',
        'PAYROLL'   => 'slate',
    ];

    public const CATEGORY_ICONS = [
        'INFO'      => 'info',
        'KEBIJAKAN' => 'shield',
        'EVENT'     => 'calendar',
        'DARURAT'   => 'alert',
        'PAYROLL'   => 'wallet',
    ];

    public const AUDIENCES = [
        'ALL'        => 'Semua karyawan aktif',
        'UNIT'       => 'Unit bisnis tertentu',
        'DEPARTMENT' => 'Departemen tertentu',
        'POSITION'   => 'Jabatan tertentu',
        'ROLE'       => 'Peran sistem tertentu',
    ];

    public const STATUSES = [
        'DRAFT'     => 'Draf',
        'PUBLISHED' => 'Terbit',
        'ARCHIVED'  => 'Diarsipkan',
    ];

    public const STATUS_TONES = [
        'DRAFT'     => 'slate',
        'PUBLISHED' => 'teal',
        'ARCHIVED'  => 'amber',
    ];

    /* ------------------------------------------------------------ karyawan */

    /**
     * Potongan SQL audiens yang berlaku untuk seorang karyawan + parameter.
     *
     * @return array{0:string,1:array}
     */
    private function audienceScope(array $user): array
    {
        $now = date('Y-m-d H:i:s');
        $sql = "a.status = 'PUBLISHED'
                AND (a.publish_at IS NULL OR a.publish_at <= ?)
                AND (a.expires_at IS NULL OR a.expires_at >= ?)";
        $params = [$now, $now];

        // HR mengelola semua pengumuman, jadi feed-nya tidak dibatasi audiens.
        if (($user['role'] ?? '') === 'HR') {
            return [$sql, $params];
        }

        $clauses = ["a.audience = 'ALL'"];
        if (!empty($user['business_unit_id'])) {
            $clauses[] = "(a.audience = 'UNIT' AND a.audience_value = ?)";
            $params[] = (string) $user['business_unit_id'];
        }
        if (!empty($user['department_id'])) {
            $clauses[] = "(a.audience = 'DEPARTMENT' AND a.audience_value = ?)";
            $params[] = (string) $user['department_id'];
        }
        if (!empty($user['position_id'])) {
            $clauses[] = "(a.audience = 'POSITION' AND a.audience_value = ?)";
            $params[] = (string) $user['position_id'];
        }
        if (!empty($user['role'])) {
            $clauses[] = "(a.audience = 'ROLE' AND a.audience_value = ?)";
            $params[] = (string) $user['role'];
        }

        $sql .= ' AND (' . implode(' OR ', $clauses) . ')';

        return [$sql, $params];
    }

    public function feed(array $user, array $filters, int $limit = 10, int $offset = 0): array
    {
        [$scope, $params] = $this->audienceScope($user);

        $where = [$scope];
        if (($filters['category'] ?? '') !== '') {
            $where[] = 'a.category = ?';
            $params[] = $filters['category'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(a.title LIKE ? OR a.body LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['unread'])) {
            $where[] = 'NOT EXISTS (SELECT 1 FROM announcement_reads r WHERE r.announcement_id = a.id AND r.employee_id = ?)';
            $params[] = (int) $user['id'];
        }

        $sql = 'SELECT a.*, e.full_name AS author_name,
                       (SELECT COUNT(*) FROM announcement_reads r2 WHERE r2.announcement_id = a.id) AS read_count,
                       (SELECT r3.read_at FROM announcement_reads r3
                         WHERE r3.announcement_id = a.id AND r3.employee_id = ?) AS my_read_at
                  FROM announcements a
                  LEFT JOIN employees e ON e.id = a.created_by
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY a.is_pinned DESC, COALESCE(a.publish_at, a.created_at) DESC, a.id DESC
                 LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);

        array_unshift($params, (int) $user['id']);

        return Database::all($sql, $params);
    }

    public function countFeed(array $user, array $filters): int
    {
        [$scope, $params] = $this->audienceScope($user);

        $where = [$scope];
        if (($filters['category'] ?? '') !== '') {
            $where[] = 'a.category = ?';
            $params[] = $filters['category'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(a.title LIKE ? OR a.body LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['unread'])) {
            $where[] = 'NOT EXISTS (SELECT 1 FROM announcement_reads r WHERE r.announcement_id = a.id AND r.employee_id = ?)';
            $params[] = (int) $user['id'];
        }

        return (int) Database::value(
            'SELECT COUNT(*) FROM announcements a WHERE ' . implode(' AND ', $where),
            $params,
            0
        );
    }

    /** Jumlah pengumuman terbit yang belum dibaca karyawan ini. */
    public function unreadCount(?array $user, int $days = 60): int
    {
        if (!$user) {
            return 0;
        }
        try {
            $scope = $this->audienceScope($user);
            $params = $scope[1];
            $params[] = date('Y-m-d H:i:s', strtotime('-' . max(1, $days) . ' days'));
            $params[] = (int) $user['id'];

            return (int) Database::value(
                'SELECT COUNT(*) FROM announcements a
                  WHERE ' . $scope[0] . '
                    AND a.created_at >= ?
                    AND NOT EXISTS (SELECT 1 FROM announcement_reads r
                                     WHERE r.announcement_id = a.id AND r.employee_id = ?)',
                $params,
                0
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    public function find(int $id): ?array
    {
        return Database::one(
            'SELECT a.*, e.full_name AS author_name, u.full_name AS editor_name
               FROM announcements a
               LEFT JOIN employees e ON e.id = a.created_by
               LEFT JOIN employees u ON u.id = a.updated_by
              WHERE a.id = ?',
            [$id]
        );
    }

    /** Boleh dilihat karyawan ini? (status, jadwal, audiens) */
    public function canView(?array $announcement, array $user): bool
    {
        if (!$announcement) {
            return false;
        }

        // HR boleh membuka draf/arsip untuk keperluan penyuntingan.
        if (($user['role'] ?? '') === 'HR') {
            return true;
        }

        if (($announcement['status'] ?? '') !== 'PUBLISHED') {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        if (!empty($announcement['publish_at']) && $announcement['publish_at'] > $now) {
            return false;
        }
        if (!empty($announcement['expires_at']) && $announcement['expires_at'] < $now) {
            return false;
        }

        return match ($announcement['audience']) {
            'ALL'        => true,
            'UNIT'       => (string) ($user['business_unit_id'] ?? '') === (string) $announcement['audience_value'],
            'DEPARTMENT' => (string) ($user['department_id'] ?? '') === (string) $announcement['audience_value'],
            'POSITION'   => (string) ($user['position_id'] ?? '') === (string) $announcement['audience_value'],
            'ROLE'       => (string) ($user['role'] ?? '') === (string) $announcement['audience_value'],
            default      => false,
        };
    }

    public function isRead(int $announcementId, int $employeeId): bool
    {
        return (bool) Database::value(
            'SELECT COUNT(*) FROM announcement_reads WHERE announcement_id = ? AND employee_id = ?',
            [$announcementId, $employeeId],
            0
        );
    }

    public function markRead(int $announcementId, int $employeeId): void
    {
        if ($this->isRead($announcementId, $employeeId)) {
            return;
        }
        try {
            Database::insert('announcement_reads', [
                'announcement_id' => $announcementId,
                'employee_id'     => $employeeId,
                'read_at'         => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // best-effort; balapan klik ganda tidak boleh memutus halaman
        }
    }

    /** Tandai semua pengumuman yang terlihat sebagai sudah dibaca. */
    public function markAllRead(array $user): int
    {
        $scope = $this->audienceScope($user);
        $rows = Database::all('SELECT a.id FROM announcements a WHERE ' . $scope[0], $scope[1]);

        $count = 0;
        foreach ($rows as $row) {
            if (!$this->isRead((int) $row['id'], (int) $user['id'])) {
                $this->markRead((int) $row['id'], (int) $user['id']);
                $count++;
            }
        }
        return $count;
    }

    /* --------------------------------------------------------- manajemen HR */

    public function manage(array $filters, int $limit = 15, int $offset = 0): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'a.status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['category'] ?? '') !== '') {
            $where[] = 'a.category = ?';
            $params[] = $filters['category'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(a.title LIKE ? OR a.body LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        return Database::all(
            'SELECT a.*, e.full_name AS author_name,
                    (SELECT COUNT(*) FROM announcement_reads r WHERE r.announcement_id = a.id) AS read_count
               FROM announcements a
               LEFT JOIN employees e ON e.id = a.created_by
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY a.is_pinned DESC, COALESCE(a.publish_at, a.created_at) DESC, a.id DESC
              LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $params
        );
    }

    public function countManage(array $filters): int
    {
        $where = ['1 = 1'];
        $params = [];

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['category'] ?? '') !== '') {
            $where[] = 'category = ?';
            $params[] = $filters['category'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(title LIKE ? OR body LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        return (int) Database::value('SELECT COUNT(*) FROM announcements WHERE ' . implode(' AND ', $where), $params, 0);
    }

    public function stats(): array
    {
        $row = Database::one(
            "SELECT
                SUM(CASE WHEN status = 'DRAFT' THEN 1 ELSE 0 END) AS draft,
                SUM(CASE WHEN status = 'PUBLISHED' THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN status = 'ARCHIVED' THEN 1 ELSE 0 END) AS archived,
                SUM(CASE WHEN status = 'DRAFT' AND publish_at IS NOT NULL THEN 1 ELSE 0 END) AS scheduled,
                SUM(CASE WHEN status = 'PUBLISHED' AND is_pinned = 1 THEN 1 ELSE 0 END) AS pinned
              FROM announcements"
        ) ?? [];

        $reads = (int) Database::value('SELECT COUNT(*) FROM announcement_reads', [], 0);
        $published = (int) ($row['published'] ?? 0);
        $totalReads = 0;
        if ($published > 0) {
            $totalReads = (int) Database::value(
                "SELECT COALESCE(SUM(cnt), 0) FROM (
                    SELECT (SELECT COUNT(*) FROM announcement_reads r WHERE r.announcement_id = a.id) AS cnt
                      FROM announcements a WHERE a.status = 'PUBLISHED'
                 ) t"
            );
        }

        return [
            'draft'     => (int) ($row['draft'] ?? 0),
            'published' => $published,
            'archived'  => (int) ($row['archived'] ?? 0),
            'scheduled' => (int) ($row['scheduled'] ?? 0),
            'pinned'    => (int) ($row['pinned'] ?? 0),
            'reads'     => $reads,
            'avg_reads' => $published > 0 ? (int) round($totalReads / $published) : 0,
        ];
    }

    public function create(array $data, int $userId): int
    {
        $now = date('Y-m-d H:i:s');

        return Database::insert('announcements', [
            'title'          => $data['title'],
            'category'       => $data['category'],
            'body'           => $data['body'],
            'audience'       => $data['audience'],
            'audience_value' => $data['audience_value'],
            'is_pinned'      => (int) $data['is_pinned'],
            'status'         => $data['status'],
            'notify'         => (int) $data['notify'],
            'publish_at'     => $data['publish_at'],
            'expires_at'     => $data['expires_at'],
            'attachment'     => $data['attachment'],
            'created_by'     => $userId,
            'updated_by'     => $userId,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
    }

    public function update(int $id, array $data, int $userId): void
    {
        $payload = [
            'title'          => $data['title'],
            'category'       => $data['category'],
            'body'           => $data['body'],
            'audience'       => $data['audience'],
            'audience_value' => $data['audience_value'],
            'is_pinned'      => (int) $data['is_pinned'],
            'status'         => $data['status'],
            'notify'         => (int) $data['notify'],
            'publish_at'     => $data['publish_at'],
            'expires_at'     => $data['expires_at'],
            'updated_by'     => $userId,
            'updated_at'     => date('Y-m-d H:i:s'),
        ];
        if (array_key_exists('attachment', $data)) {
            $payload['attachment'] = $data['attachment'];
        }

        Database::update('announcements', $payload, 'id = ?', [$id]);
    }

    public function setStatus(int $id, string $status, int $userId): void
    {
        $payload = [
            'status'     => $status,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($status === 'PUBLISHED') {
            $payload['publish_at'] = date('Y-m-d H:i:s');
        }

        Database::update('announcements', $payload, 'id = ?', [$id]);
    }

    public function delete(int $id): void
    {
        Database::delete('announcement_reads', 'announcement_id = ?', [$id]);
        Database::delete('announcements', 'id = ?', [$id]);
    }

    /* ------------------------------------------------------ audiens & notif */

    /** Daftar karyawan aktif yang menjadi target sebuah pengumuman. */
    public function audienceEmployees(array $announcement): array
    {
        $sql = "SELECT id, full_name FROM employees WHERE flag = 'ACTIVE'";
        $params = [];

        $audience = $announcement['audience'] ?? 'ALL';
        $value = (string) ($announcement['audience_value'] ?? '');

        if ($audience === 'UNIT' && $value !== '') {
            $sql .= ' AND business_unit_id = ?';
            $params[] = (int) $value;
        } elseif ($audience === 'DEPARTMENT' && $value !== '') {
            $sql .= ' AND department_id = ?';
            $params[] = (int) $value;
        } elseif ($audience === 'POSITION' && $value !== '') {
            $sql .= ' AND position_id = ?';
            $params[] = (int) $value;
        } elseif ($audience === 'ROLE' && $value !== '') {
            $sql .= ' AND role = ?';
            $params[] = $value;
        }

        return Database::all($sql, $params);
    }

    public function audienceSize(array $announcement): int
    {
        return count($this->audienceEmployees($announcement));
    }

    /** Kirim notifikasi lonceng ke seluruh target (dipanggil saat terbit). */
    public function notifyAudience(array $announcement): int
    {
        if ((int) ($announcement['notify'] ?? 1) !== 1) {
            return 0;
        }

        // Pengumuman terjadwal belum mengirim notifikasi sampai waktunya tiba.
        if (!empty($announcement['publish_at']) && $announcement['publish_at'] > date('Y-m-d H:i:s')) {
            return 0;
        }

        $link = '/announcements/' . (int) $announcement['id'];
        $title = 'Pengumuman: ' . mb_substr((string) $announcement['title'], 0, 120);
        $message = mb_substr(trim(preg_replace('/\s+/', ' ', (string) $announcement['body'])), 0, 200);
        $type = ($announcement['category'] ?? 'INFO') === 'DARURAT' ? 'danger' : 'announcement';
        $now = date('Y-m-d H:i:s');

        $sent = 0;
        foreach ($this->audienceEmployees($announcement) as $employee) {
            try {
                Database::insert('notifications', [
                    'employee_id' => (int) $employee['id'],
                    'title'       => $title,
                    'message'     => $message,
                    'link'        => $link,
                    'type'        => $type,
                    'created_at'  => $now,
                ]);
                $sent++;
            } catch (\Throwable) {
                // best-effort
            }
        }
        return $sent;
    }

    /** Rasio keterbacaan sebuah pengumuman. */
    public function readStats(array $announcement): array
    {
        $reads = (int) Database::value(
            'SELECT COUNT(*) FROM announcement_reads WHERE announcement_id = ?',
            [(int) $announcement['id']],
            0
        );
        $audience = $this->audienceSize($announcement);
        $last = Database::value(
            'SELECT MAX(read_at) FROM announcement_reads WHERE announcement_id = ?',
            [(int) $announcement['id']]
        );

        return [
            'reads'    => $reads,
            'audience' => $audience,
            'percent'  => $audience > 0 ? min(100, (int) round($reads / $audience * 100)) : 0,
            'last'     => $last,
        ];
    }

    /** Label audiens yang siap ditampilkan. */
    public static function audienceLabel(array $announcement): string
    {
        $audience = $announcement['audience'] ?? 'ALL';
        $value = (string) ($announcement['audience_value'] ?? '');

        if ($audience === 'ALL' || $value === '') {
            return self::AUDIENCES['ALL'];
        }

        return match ($audience) {
            'UNIT'       => (string) Database::value('SELECT name FROM business_units WHERE id = ?', [(int) $value], 'Unit #' . $value),
            'DEPARTMENT' => (string) Database::value('SELECT name FROM departments WHERE id = ?', [(int) $value], 'Departemen #' . $value),
            'POSITION'   => (string) Database::value('SELECT name FROM positions WHERE id = ?', [(int) $value], 'Jabatan #' . $value),
            'ROLE'       => role_label($value),
            default      => self::AUDIENCES['ALL'],
        };
    }
}
