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
        'UNIT'       => 'Unit bisnis',
        'DEPARTMENT' => 'Departemen',
        'POSITION'   => 'Jabatan',
        'ROLE'       => 'Peran sistem',
        'EMPLOYEE'   => 'Karyawan tertentu',
        'MULTI'      => 'Kombinasi target',
    ];

    /** Tipe target yang boleh dipilih HR pada form (MULTI hanya ringkasan). */
    public const TARGET_TYPES = ['UNIT', 'DEPARTMENT', 'POSITION', 'ROLE', 'EMPLOYEE'];

    public const TARGET_LABELS = [
        'UNIT'       => 'Unit bisnis',
        'DEPARTMENT' => 'Departemen',
        'POSITION'   => 'Jabatan',
        'ROLE'       => 'Peran',
        'EMPLOYEE'   => 'Karyawan',
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
     * Sumber kebenaran adalah tabel `announcement_targets` (boleh banyak baris,
     * satu pengumuman bisa menyasar unit + departemen + karyawan sekaligus).
     * Pengumuman lama yang belum punya baris target tetap dibaca dari kolom
     * `announcements.audience` / `audience_value` (kompatibilitas mundur).
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
        if (($user['role'] ?? '') === 'HR' || !Database::tableExists('announcement_targets')) {
            return [$sql, $params];
        }

        $matches = ["t.target_type = 'ALL'"];
        $unitId = (string) ($user['business_unit_id'] ?? '');
        if ($unitId !== '') {
            $matches[] = "(t.target_type = 'UNIT' AND t.target_value = ?)";
            $params[] = $unitId;
        }
        $deptId = (string) ($user['department_id'] ?? '');
        if ($deptId !== '') {
            $matches[] = "(t.target_type = 'DEPARTMENT' AND t.target_value = ?)";
            $params[] = $deptId;
        }
        $posId = (string) ($user['position_id'] ?? '');
        if ($posId !== '') {
            $matches[] = "(t.target_type = 'POSITION' AND t.target_value = ?)";
            $params[] = $posId;
        }
        $role = (string) ($user['role'] ?? '');
        if ($role !== '') {
            $matches[] = "(t.target_type = 'ROLE' AND t.target_value = ?)";
            $params[] = $role;
        }
        $employeeId = (string) ($user['id'] ?? '');
        if ($employeeId !== '') {
            $matches[] = "(t.target_type = 'EMPLOYEE' AND t.target_value = ?)";
            $params[] = $employeeId;
        }

        $viaTargets = 'EXISTS (SELECT 1 FROM announcement_targets t
                        WHERE t.announcement_id = a.id AND (' . implode(' OR ', $matches) . '))';

        // Pengumuman lama tanpa baris target: pakai kolom lama.
        $legacyClauses = ["a.audience = 'ALL'"];
        if ($unitId !== '') {
            $legacyClauses[] = "(a.audience = 'UNIT' AND a.audience_value = ?)";
            $params[] = $unitId;
        }
        if ($deptId !== '') {
            $legacyClauses[] = "(a.audience = 'DEPARTMENT' AND a.audience_value = ?)";
            $params[] = $deptId;
        }
        if ($posId !== '') {
            $legacyClauses[] = "(a.audience = 'POSITION' AND a.audience_value = ?)";
            $params[] = $posId;
        }
        if ($role !== '') {
            $legacyClauses[] = "(a.audience = 'ROLE' AND a.audience_value = ?)";
            $params[] = $role;
        }

        $legacy = '(NOT EXISTS (SELECT 1 FROM announcement_targets t9 WHERE t9.announcement_id = a.id)
                    AND (' . implode(' OR ', $legacyClauses) . '))';

        $sql .= ' AND (' . $viaTargets . ' OR ' . $legacy . ')';

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

        // Target berlapis (tabel announcement_targets) menjadi acuan utama.
        $targets = $this->targetsOf((int) $announcement['id']);
        if ($targets) {
            foreach ($targets as $target) {
                $type = (string) $target['target_type'];
                $value = (string) $target['target_value'];
                if ($type === 'ALL') {
                    return true;
                }
                if ($type === 'UNIT' && (string) ($user['business_unit_id'] ?? '') === $value) {
                    return true;
                }
                if ($type === 'DEPARTMENT' && (string) ($user['department_id'] ?? '') === $value) {
                    return true;
                }
                if ($type === 'POSITION' && (string) ($user['position_id'] ?? '') === $value) {
                    return true;
                }
                if ($type === 'ROLE' && (string) ($user['role'] ?? '') === $value) {
                    return true;
                }
                if ($type === 'EMPLOYEE' && (string) ($user['id'] ?? '') === $value) {
                    return true;
                }
            }
            return false;
        }

        // Fallback pengumuman lama (kolom audience/audience_value).
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

    /* ------------------------------------------------------------- target */

    /**
     * Baris target mentah sebuah pengumuman.
     *
     * @return array<int,array{target_type:string,target_value:string}>
     */
    public function targetsOf(int $announcementId): array
    {
        if (!Database::tableExists('announcement_targets')) {
            return [];
        }

        return Database::all(
            'SELECT target_type, target_value FROM announcement_targets
              WHERE announcement_id = ? ORDER BY id',
            [$announcementId]
        );
    }

    /**
     * Target yang tersimpan dalam format siap pakai form: "UNIT:4", "EMPLOYEE:12".
     *
     * @return array<int,string>
     */
    public function targetKeys(int $announcementId): array
    {
        $keys = [];
        foreach ($this->targetsOf($announcementId) as $row) {
            $type = (string) $row['target_type'];
            $value = (string) $row['target_value'];
            $keys[] = $type === 'ALL' ? 'ALL' : $type . ':' . $value;
        }
        return $keys;
    }

    /**
     * Ganti seluruh target sebuah pengumuman.
     *
     * @param array<int,array{type:string,value:string}> $targets
     */
    public function syncTargets(int $announcementId, array $targets): void
    {
        if (!Database::tableExists('announcement_targets')) {
            return;
        }

        Database::delete('announcement_targets', 'announcement_id = ?', [$announcementId]);

        $now = date('Y-m-d H:i:s');
        foreach ($this->normalizeTargets($targets) as $target) {
            Database::insert('announcement_targets', [
                'announcement_id' => $announcementId,
                'target_type'     => $target['type'],
                'target_value'    => $target['value'],
                'created_at'      => $now,
            ]);
        }
    }

    /**
     * Bersihkan, validasi, dan buang duplikat daftar target.
     *
     * @param array<int,array{type:string,value:string}> $targets
     * @return array<int,array{type:string,value:string}>
     */
    public function normalizeTargets(array $targets): array
    {
        $clean = [];
        foreach ($targets as $target) {
            $type = strtoupper(trim((string) ($target['type'] ?? '')));
            $value = trim((string) ($target['value'] ?? ''));

            if ($type === 'ALL') {
                $clean['ALL'] = ['type' => 'ALL', 'value' => ''];
                continue;
            }
            if (!in_array($type, self::TARGET_TYPES, true) || $value === '') {
                continue;
            }
            if ($type === 'ROLE' && !in_array($value, ['HR', 'FIN', 'KARYAWAN'], true)) {
                continue;
            }
            if (in_array($type, ['UNIT', 'DEPARTMENT', 'POSITION', 'EMPLOYEE'], true)) {
                if ((int) $value <= 0) {
                    continue;
                }
                $value = (string) (int) $value;
            }
            $key = $type . ':' . $value;
            $clean[$key] = ['type' => $type, 'value' => $value];
        }

        // "Semua karyawan" tidak perlu digabung dengan target lain.
        if (isset($clean['ALL'])) {
            return [$clean['ALL']];
        }

        return array_values($clean);
    }

    /**
     * Ringkasan audiens untuk disimpan di kolom `audience` (kompatibilitas).
     *
     * @param array<int,array{type:string,value:string}> $targets
     */
    public function audienceSummary(array $targets): array
    {
        $targets = $this->normalizeTargets($targets);
        if (!$targets) {
            return ['audience' => 'ALL', 'audience_value' => ''];
        }
        if (count($targets) === 1 && $targets[0]['type'] === 'ALL') {
            return ['audience' => 'ALL', 'audience_value' => ''];
        }
        if (count($targets) === 1) {
            return ['audience' => $targets[0]['type'], 'audience_value' => $targets[0]['value']];
        }
        return ['audience' => 'MULTI', 'audience_value' => ''];
    }

    /** Daftar karyawan aktif yang menjadi target sebuah pengumuman. */
    public function audienceEmployees(array $announcement): array
    {
        $base = 'SELECT DISTINCT e.id, e.full_name FROM employees e WHERE e.flag = \'ACTIVE\'';
        $targets = $this->targetsOf((int) ($announcement['id'] ?? 0));

        if (!$targets) {
            // Fallback pengumuman lama (kolom audience/audience_value).
            $audience = $announcement['audience'] ?? 'ALL';
            $value = (string) ($announcement['audience_value'] ?? '');
            if ($audience === 'UNIT' && $value !== '') {
                return Database::all($base . ' AND e.business_unit_id = ?', [(int) $value]);
            }
            if ($audience === 'DEPARTMENT' && $value !== '') {
                return Database::all($base . ' AND e.department_id = ?', [(int) $value]);
            }
            if ($audience === 'POSITION' && $value !== '') {
                return Database::all($base . ' AND e.position_id = ?', [(int) $value]);
            }
            if ($audience === 'ROLE' && $value !== '') {
                return Database::all($base . ' AND e.role = ?', [$value]);
            }
            return Database::all($base, []);
        }

        $groups = [];
        foreach ($targets as $target) {
            $type = (string) $target['target_type'];
            $value = (string) $target['target_value'];
            if ($type === 'ALL') {
                return Database::all($base, []);
            }
            $groups[$type][] = $value;
        }

        $column = [
            'UNIT'       => 'e.business_unit_id',
            'DEPARTMENT' => 'e.department_id',
            'POSITION'   => 'e.position_id',
            'EMPLOYEE'   => 'e.id',
            'ROLE'       => 'e.role',
        ];

        $clauses = [];
        $params = [];
        foreach ($groups as $type => $values) {
            if (!isset($column[$type])) {
                continue;
            }
            $placeholders = implode(', ', array_fill(0, count($values), '?'));
            $clauses[] = $column[$type] . ' IN (' . $placeholders . ')';
            foreach ($values as $value) {
                $params[] = in_array($type, ['EMPLOYEE', 'UNIT', 'DEPARTMENT', 'POSITION'], true)
                    ? (int) $value
                    : $value;
            }
        }

        if (!$clauses) {
            return Database::all($base, []);
        }

        return Database::all($base . ' AND (' . implode(' OR ', $clauses) . ')', $params);
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

    /**
     * Rincian target siap tampil: satu baris per kelompok.
     *
     * @return array<int,array{type:string,label:string,names:array<int,string>}>
     */
    public function audienceBreakdown(array $announcement): array
    {
        $id = (int) ($announcement['id'] ?? 0);
        $targets = $this->targetsOf($id);

        if (!$targets) {
            // Pengumuman lama: satu kelompok dari kolom audience.
            $audience = (string) ($announcement['audience'] ?? 'ALL');
            $value = (string) ($announcement['audience_value'] ?? '');
            if ($audience === 'ALL' || $value === '') {
                return [['type' => 'ALL', 'label' => 'Semua karyawan', 'names' => []]];
            }
            return [[
                'type'  => $audience,
                'label' => self::TARGET_LABELS[$audience] ?? 'Audiens',
                'names' => [self::targetName($audience, $value)],
            ]];
        }

        $groups = [];
        foreach ($targets as $target) {
            $type = (string) $target['target_type'];
            $value = (string) $target['target_value'];
            if ($type === 'ALL') {
                return [['type' => 'ALL', 'label' => 'Semua karyawan', 'names' => []]];
            }
            $groups[$type][] = self::targetName($type, $value);
        }

        $order = ['UNIT', 'DEPARTMENT', 'POSITION', 'ROLE', 'EMPLOYEE'];
        $out = [];
        foreach ($order as $type) {
            if (empty($groups[$type])) {
                continue;
            }
            $out[] = [
                'type'  => $type,
                'label' => self::TARGET_LABELS[$type] ?? $type,
                'names' => $groups[$type],
            ];
        }

        return $out;
    }

    /** Nama tampil satu nilai target. */
    private static function targetName(string $type, string $value): string
    {
        return match ($type) {
            'UNIT'       => (string) Database::value('SELECT name FROM business_units WHERE id = ?', [(int) $value], 'Unit #' . $value),
            'DEPARTMENT' => (string) Database::value('SELECT name FROM departments WHERE id = ?', [(int) $value], 'Departemen #' . $value),
            'POSITION'   => (string) Database::value('SELECT name FROM positions WHERE id = ?', [(int) $value], 'Jabatan #' . $value),
            'EMPLOYEE'   => (string) Database::value('SELECT full_name FROM employees WHERE id = ?', [(int) $value], 'Karyawan #' . $value),
            'ROLE'       => role_label($value),
            default      => $value,
        };
    }

    /** Label audiens ringkas untuk daftar/tabel. */
    public static function audienceLabel(array $announcement): string
    {
        $audience = (string) ($announcement['audience'] ?? 'ALL');
        $value = (string) ($announcement['audience_value'] ?? '');

        if ($audience === 'ALL' || ($value === '' && $audience !== 'MULTI')) {
            if ($audience === 'ALL') {
                return self::AUDIENCES['ALL'];
            }
        }

        // Pengumuman ber-target banyak: tampilkan ringkasannya.
        $model = new self();
        $breakdown = $model->audienceBreakdown($announcement);
        if (count($breakdown) === 1 && $breakdown[0]['type'] === 'ALL') {
            return self::AUDIENCES['ALL'];
        }

        $parts = [];
        foreach ($breakdown as $group) {
            $count = count($group['names']);
            $parts[] = $count === 1
                ? $group['names'][0]
                : $group['label'] . ' (' . $count . ')';
        }

        return implode(' · ', $parts);
    }
}
