<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Pelatihan & pengembangan (training).
 *
 * Tiga lapis data:
 *  - trainings            : master program (kode, kategori, durasi, biaya)
 *  - training_sessions    : jadwal/angkatan sebuah program (kuota, pendaftaran)
 *  - training_participants: peserta per sesi beserta status, nilai, sertifikat
 *
 * Alur sesi: DRAFT → OPEN (pendaftaran dibuka) → ONGOING → COMPLETED, dengan
 * CANCELLED sebagai jalur pembatalan. Peserta hasil nominasi HR langsung
 * APPROVED, sedangkan pendaftaran mandiri karyawan masuk sebagai NOMINATED dan
 * menunggu persetujuan HR.
 */
final class TrainingModel
{
    public const CATEGORIES = [
        'TEKNIS'        => 'Teknis',
        'SOFT_SKILL'    => 'Soft Skill',
        'KEPEMIMPINAN'  => 'Kepemimpinan',
        'KEPATUHAN'     => 'Kepatuhan & Regulasi',
        'ONBOARDING'    => 'Onboarding',
    ];

    public const CATEGORY_TONES = [
        'TEKNIS'       => 'indigo',
        'SOFT_SKILL'   => 'amber',
        'KEPEMIMPINAN' => 'teal',
        'KEPATUHAN'    => 'rose',
        'ONBOARDING'   => 'slate',
    ];

    public const TYPES = [
        'INTERNAL' => 'Internal',
        'EKSTERNAL' => 'Eksternal',
        'ONLINE'   => 'Online',
    ];

    public const MODES = [
        'OFFLINE' => 'Tatap Muka',
        'ONLINE'  => 'Daring',
        'HYBRID'  => 'Hybrid',
    ];

    public const SESSION_STATUSES = [
        'DRAFT'     => 'Draf',
        'OPEN'      => 'Pendaftaran Dibuka',
        'ONGOING'   => 'Berlangsung',
        'COMPLETED' => 'Selesai',
        'CANCELLED' => 'Dibatalkan',
    ];

    public const SESSION_TONES = [
        'DRAFT'     => 'slate',
        'OPEN'      => 'indigo',
        'ONGOING'   => 'amber',
        'COMPLETED' => 'teal',
        'CANCELLED' => 'rose',
    ];

    public const PARTICIPANT_STATUSES = [
        'NOMINATED' => 'Menunggu Persetujuan',
        'APPROVED'  => 'Terdaftar',
        'ATTENDED'  => 'Hadir',
        'ABSENT'    => 'Tidak Hadir',
        'CANCELLED' => 'Dibatalkan',
    ];

    public const PARTICIPANT_TONES = [
        'NOMINATED' => 'amber',
        'APPROVED'  => 'indigo',
        'ATTENDED'  => 'teal',
        'ABSENT'    => 'rose',
        'CANCELLED' => 'slate',
    ];

    /** Status peserta yang masih dihitung sebagai bagian sesi. */
    private const ACTIVE_STATUSES = ['NOMINATED', 'APPROVED', 'ATTENDED', 'ABSENT'];

    /* ------------------------------------------------------------- program */

    public function programs(bool $onlyActive = false): array
    {
        $where = $onlyActive ? ' WHERE t.is_active = 1' : '';
        return Database::all(
            'SELECT t.*,
                    (SELECT COUNT(*) FROM training_sessions s WHERE s.training_id = t.id) AS sessions,
                    (SELECT COUNT(*) FROM training_participants p
                       JOIN training_sessions s ON s.id = p.session_id
                      WHERE s.training_id = t.id AND p.status = \'ATTENDED\') AS alumni
               FROM trainings t' . $where . '
              ORDER BY t.is_active DESC, t.name ASC'
        );
    }

    public function findProgram(int $id): ?array
    {
        return Database::one('SELECT * FROM trainings WHERE id = ?', [$id]);
    }

    public function programByCode(string $code, ?int $exceptId = null): ?array
    {
        return Database::one(
            'SELECT * FROM trainings WHERE code = ?' . ($exceptId !== null ? ' AND id <> ?' : '') . ' LIMIT 1',
            $exceptId !== null ? [$code, $exceptId] : [$code]
        );
    }

    public function programUsage(int $id): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM training_sessions WHERE training_id = ?', [$id], 0);
    }

    /** @param array<string,mixed> $data */
    public function createProgram(array $data, int $actorId): int
    {
        return Database::insert('trainings', $data + [
            'created_by' => $actorId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string,mixed> $data */
    public function updateProgram(int $id, array $data): void
    {
        Database::update('trainings', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    public function deleteProgram(int $id): bool
    {
        if ($this->programUsage($id) > 0) {
            return false;
        }
        Database::delete('trainings', 'id = ?', [$id]);
        return true;
    }

    /* --------------------------------------------------------------- sesi */

    /**
     * @param array{status:string,program:int,mode:string,q:string} $filters
     */
    private function sessionWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if ($filters['status'] !== '' && isset(self::SESSION_STATUSES[$filters['status']])) {
            $where[] = 's.status = ?';
            $params[] = $filters['status'];
        }
        if ($filters['program'] > 0) {
            $where[] = 's.training_id = ?';
            $params[] = $filters['program'];
        }
        if ($filters['mode'] !== '' && isset(self::MODES[$filters['mode']])) {
            $where[] = 's.mode = ?';
            $params[] = $filters['mode'];
        }
        if ($filters['q'] !== '') {
            $like = '%' . $filters['q'] . '%';
            $where[] = '(t.name LIKE ? OR t.code LIKE ? OR t.provider LIKE ? OR s.location LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    public function countSessions(array $filters): int
    {
        [$where, $params] = $this->sessionWhere($filters);
        return (int) Database::value(
            'SELECT COUNT(*) FROM training_sessions s JOIN trainings t ON t.id = s.training_id' . $where,
            $params,
            0
        );
    }

    public function sessions(array $filters, int $limit = 15, int $offset = 0): array
    {
        [$where, $params] = $this->sessionWhere($filters);
        $params[] = max(1, $limit);
        $params[] = max(0, $offset);

        return Database::all(
            'SELECT s.*, t.code AS program_code, t.name AS program_name, t.category, t.type,
                    t.provider, t.duration_hours, t.cost_per_participant, t.is_mandatory,
                    (SELECT COUNT(*) FROM training_participants p
                      WHERE p.session_id = s.id AND p.status <> \'CANCELLED\') AS participants,
                    (SELECT COUNT(*) FROM training_participants p
                      WHERE p.session_id = s.id AND p.status = \'ATTENDED\') AS attended,
                    (SELECT COUNT(*) FROM training_participants p
                      WHERE p.session_id = s.id AND p.status = \'NOMINATED\') AS pending
               FROM training_sessions s
               JOIN trainings t ON t.id = s.training_id' . $where . '
              ORDER BY CASE WHEN s.status IN (\'OPEN\',\'ONGOING\') THEN 0 ELSE 1 END,
                       s.start_date DESC, s.id DESC
              LIMIT ? OFFSET ?',
            $params
        );
    }

    public function findSession(int $id): ?array
    {
        return Database::one(
            'SELECT s.*, t.code AS program_code, t.name AS program_name, t.category, t.type,
                    t.provider, t.description, t.duration_hours, t.cost_per_participant,
                    t.is_mandatory, e.full_name AS creator_name
               FROM training_sessions s
               JOIN trainings t ON t.id = s.training_id
               LEFT JOIN employees e ON e.id = s.created_by
              WHERE s.id = ?',
            [$id]
        );
    }

    /** Ringkasan peserta satu sesi. */
    public function sessionStats(int $sessionId): array
    {
        $row = Database::one(
            'SELECT
                SUM(CASE WHEN p.status <> \'CANCELLED\' THEN 1 ELSE 0 END) AS registered,
                SUM(CASE WHEN p.status = \'NOMINATED\' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN p.status = \'APPROVED\' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN p.status = \'ATTENDED\' THEN 1 ELSE 0 END) AS attended,
                SUM(CASE WHEN p.status = \'ABSENT\' THEN 1 ELSE 0 END) AS absent,
                AVG(CASE WHEN p.score IS NOT NULL THEN p.score END) AS avg_score,
                SUM(CASE WHEN p.status = \'ATTENDED\' THEN 1 ELSE 0 END) AS certificates
               FROM training_participants p
              WHERE p.session_id = ?',
            [$sessionId]
        ) ?? [];

        $session = $this->findSession($sessionId);
        $attended = (int) ($row['attended'] ?? 0);
        $absent = (int) ($row['absent'] ?? 0);
        $denominator = $attended + $absent;

        return [
            'registered'   => (int) ($row['registered'] ?? 0),
            'pending'      => (int) ($row['pending'] ?? 0),
            'approved'     => (int) ($row['approved'] ?? 0),
            'attended'     => $attended,
            'absent'       => $absent,
            'avg_score'    => $row['avg_score'] !== null ? round((float) $row['avg_score'], 1) : null,
            'attendance_rate' => $denominator > 0 ? (int) round($attended / $denominator * 100) : 0,
            'cost'         => $attended * (float) ($session['cost_per_participant'] ?? 0),
            'hours'        => $attended * (float) ($session['duration_hours'] ?? 0),
        ];
    }

    /** @param array<string,mixed> $data */
    public function createSession(array $data, int $actorId): int
    {
        return Database::insert('training_sessions', $data + [
            'created_by' => $actorId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string,mixed> $data */
    public function updateSession(int $id, array $data): void
    {
        Database::update('training_sessions', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    public function setSessionStatus(int $id, string $status): void
    {
        Database::update('training_sessions', [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    public function deleteSession(int $id): void
    {
        Database::delete('training_participants', 'session_id = ?', [$id]);
        Database::delete('training_sessions', 'id = ?', [$id]);
    }

    /* ------------------------------------------------------------ peserta */

    public function participants(int $sessionId, string $status = ''): array
    {
        $params = [$sessionId];
        $filter = '';
        if ($status !== '' && isset(self::PARTICIPANT_STATUSES[$status])) {
            $filter = ' AND p.status = ?';
            $params[] = $status;
        }

        return Database::all(
            'SELECT p.*, e.employee_code, e.full_name, e.email, e.photo,
                    u.name AS unit_name, d.name AS department_name, pos.name AS position_name
               FROM training_participants p
               JOIN employees e ON e.id = p.employee_id
               LEFT JOIN business_units u ON u.id = e.business_unit_id
               LEFT JOIN departments d ON d.id = e.department_id
               LEFT JOIN positions pos ON pos.id = e.position_id
              WHERE p.session_id = ?' . $filter . '
              ORDER BY CASE p.status WHEN \'NOMINATED\' THEN 0 WHEN \'APPROVED\' THEN 1 WHEN \'ATTENDED\' THEN 2 ELSE 3 END,
                       e.full_name ASC',
            $params
        );
    }

    public function findParticipant(int $id): ?array
    {
        return Database::one(
            'SELECT p.*, e.full_name, e.employee_code, s.training_id, s.status AS session_status,
                    s.start_date, s.end_date, t.name AS program_name, t.duration_hours
               FROM training_participants p
               JOIN employees e ON e.id = p.employee_id
               JOIN training_sessions s ON s.id = p.session_id
               JOIN trainings t ON t.id = s.training_id
              WHERE p.id = ?',
            [$id]
        );
    }

    public function participantFor(int $sessionId, int $employeeId): ?array
    {
        return Database::one(
            'SELECT * FROM training_participants WHERE session_id = ? AND employee_id = ? LIMIT 1',
            [$sessionId, $employeeId]
        );
    }

    /** Karyawan aktif yang belum terdaftar pada sesi (untuk form nominasi). */
    public function availableEmployees(int $sessionId, string $q = ''): array
    {
        $params = [$sessionId];
        $like = '';
        if ($q !== '') {
            $like = ' AND (e.full_name LIKE ? OR e.employee_code LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        return Database::all(
            'SELECT e.id, e.employee_code, e.full_name, u.name AS unit_name
               FROM employees e
               LEFT JOIN business_units u ON u.id = e.business_unit_id
              WHERE e.flag = \'ACTIVE\'
                AND NOT EXISTS (
                    SELECT 1 FROM training_participants p
                     WHERE p.session_id = ? AND p.employee_id = e.id
                       AND p.status <> \'CANCELLED\'
                )' . $like . '
              ORDER BY e.full_name ASC
              LIMIT 400',
            $params
        );
    }

    /**
     * Tambahkan peserta secara massal (nominasi HR).
     *
     * @param int[] $employeeIds
     * @return array{added:int,skipped:int}
     */
    public function addParticipants(int $sessionId, array $employeeIds, string $source, int $actorId, bool $autoApprove = true): array
    {
        $session = $this->findSession($sessionId);
        if (!$session) {
            return ['added' => 0, 'skipped' => 0];
        }

        $added = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');

        foreach (array_unique(array_map('intval', $employeeIds)) as $employeeId) {
            if ($employeeId <= 0) {
                continue;
            }
            $exists = $this->participantFor($sessionId, $employeeId);
            if ($exists !== null && $exists['status'] !== 'CANCELLED') {
                $skipped++;
                continue;
            }

            $status = $autoApprove ? 'APPROVED' : 'NOMINATED';
            $payload = [
                'session_id'  => $sessionId,
                'employee_id' => $employeeId,
                'source'      => $source,
                'status'      => $status,
                'approved_by' => $autoApprove ? $actorId : null,
                'approved_at' => $autoApprove ? $now : null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];

            if ($exists !== null) {
                Database::update('training_participants', $payload, 'id = ?', [(int) $exists['id']]);
            } else {
                Database::insert('training_participants', $payload);
            }
            $added++;

            $this->notify(
                $employeeId,
                $status === 'APPROVED' ? 'Anda terdaftar dalam pelatihan' : 'Pendaftaran pelatihan menunggu persetujuan',
                $session['program_name'] . ' — ' . date('d/m/Y', strtotime((string) $session['start_date']))
                    . ($status === 'APPROVED' ? '. Cek detail jadwal di Pelatihan Saya.' : '. HR akan meninjau pendaftaran Anda.'),
                '/me/trainings',
                $status === 'APPROVED' ? 'success' : 'info'
            );
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    /** Pendaftaran mandiri karyawan. @return array{error:?string,participant:?array} */
    public function enroll(int $sessionId, int $employeeId): array
    {
        $session = $this->findSession($sessionId);
        if (!$session) {
            return ['error' => 'Sesi pelatihan tidak ditemukan.', 'participant' => null];
        }
        if ($session['status'] !== 'OPEN') {
            return ['error' => 'Pendaftaran sesi ini belum dibuka atau sudah ditutup.', 'participant' => null];
        }
        if (!empty($session['enroll_deadline']) && $session['enroll_deadline'] < date('Y-m-d')) {
            return ['error' => 'Batas waktu pendaftaran sudah lewat.', 'participant' => null];
        }

        $existing = $this->participantFor($sessionId, $employeeId);
        if ($existing !== null && $existing['status'] !== 'CANCELLED') {
            return ['error' => 'Anda sudah terdaftar pada sesi ini.', 'participant' => null];
        }

        $quota = (int) $session['quota'];
        if ($quota > 0) {
            $used = (int) Database::value(
                'SELECT COUNT(*) FROM training_participants WHERE session_id = ? AND status <> ?',
                [$sessionId, 'CANCELLED'],
                0
            );
            if ($used >= $quota) {
                return ['error' => 'Kuota sesi ini sudah penuh.', 'participant' => null];
            }
        }

        $now = date('Y-m-d H:i:s');
        if ($existing !== null) {
            Database::update('training_participants', [
                'source'      => 'SELF',
                'status'      => 'NOMINATED',
                'approved_by' => null,
                'approved_at' => null,
                'updated_at'  => $now,
            ], 'id = ?', [(int) $existing['id']]);
            $id = (int) $existing['id'];
        } else {
            $id = Database::insert('training_participants', [
                'session_id'  => $sessionId,
                'employee_id' => $employeeId,
                'source'      => 'SELF',
                'status'      => 'NOMINATED',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        return ['error' => null, 'participant' => ['id' => $id, 'program' => $session['program_name']]];
    }

    /** Batalkan keikutsertaan sendiri. @return array{error:?string} */
    public function cancelOwn(int $sessionId, int $employeeId): array
    {
        $row = $this->participantFor($sessionId, $employeeId);
        if ($row === null) {
            return ['error' => 'Anda tidak terdaftar pada sesi ini.'];
        }
        if (in_array($row['status'], ['ATTENDED', 'ABSENT'], true)) {
            return ['error' => 'Pelatihan sudah selesai dinilai, tidak dapat dibatalkan.'];
        }

        $session = $this->findSession($sessionId);
        if ($session && $session['status'] === 'ONGOING') {
            return ['error' => 'Sesi sedang berlangsung. Hubungi HR untuk perubahan keikutsertaan.'];
        }

        Database::update('training_participants', [
            'status'     => 'CANCELLED',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int) $row['id']]);

        return ['error' => null];
    }

    public function setParticipantStatus(int $id, string $status, int $actorId): void
    {
        $now = date('Y-m-d H:i:s');
        $payload = ['status' => $status, 'updated_at' => $now];

        if ($status === 'APPROVED') {
            $payload['approved_by'] = $actorId;
            $payload['approved_at'] = $now;
        }
        if (in_array($status, ['ATTENDED', 'ABSENT'], true)) {
            $payload['completed_at'] = $now;
        }

        Database::update('training_participants', $payload, 'id = ?', [$id]);

        $row = $this->findParticipant($id);
        if ($row === null) {
            return;
        }

        if ($status === 'APPROVED') {
            $this->notify(
                (int) $row['employee_id'],
                'Pendaftaran pelatihan disetujui',
                $row['program_name'] . ' — ' . date('d/m/Y', strtotime((string) $row['start_date'])) . '. Cek detail di Pelatihan Saya.',
                '/me/trainings',
                'success'
            );
        }
        if ($status === 'CANCELLED' && $row['status'] !== 'CANCELLED') {
            $this->notify(
                (int) $row['employee_id'],
                'Keikutsertaan pelatihan dibatalkan',
                'HR membatalkan keikutsertaan Anda pada ' . $row['program_name'] . '.',
                '/me/trainings',
                'warning'
            );
        }
    }

    /** @param array<string,mixed> $data */
    public function updateParticipantResult(int $id, array $data, int $actorId): void
    {
        Database::update('training_participants', $data + [
            'approved_by' => $actorId,
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    public function removeParticipant(int $id): void
    {
        Database::delete('training_participants', 'id = ?', [$id]);
    }

    /** Setujui semua pendaftaran mandiri pada satu sesi. */
    public function approveAll(int $sessionId, int $actorId): int
    {
        $rows = Database::all(
            'SELECT id FROM training_participants WHERE session_id = ? AND status = ?',
            [$sessionId, 'NOMINATED']
        );
        foreach ($rows as $row) {
            $this->setParticipantStatus((int) $row['id'], 'APPROVED', $actorId);
        }
        return count($rows);
    }

    /** Tandai seluruh peserta terdaftar sebagai hadir. */
    public function markAttendanceAll(int $sessionId, int $actorId): int
    {
        $rows = Database::all(
            'SELECT id FROM training_participants WHERE session_id = ? AND status IN (?, ?)',
            [$sessionId, 'APPROVED', 'NOMINATED']
        );
        foreach ($rows as $row) {
            $this->setParticipantStatus((int) $row['id'], 'ATTENDED', $actorId);
        }
        return count($rows);
    }

    /* ----------------------------------------------------------- karyawan */

    /** Riwayat & jadwal pelatihan satu karyawan. */
    public function employeeSessions(int $employeeId): array
    {
        return Database::all(
            'SELECT p.*, s.start_date, s.end_date, s.mode, s.location, s.status AS session_status,
                    t.code AS program_code, t.name AS program_name, t.category, t.type,
                    t.provider, t.duration_hours
               FROM training_participants p
               JOIN training_sessions s ON s.id = p.session_id
               JOIN trainings t ON t.id = s.training_id
              WHERE p.employee_id = ?
              ORDER BY CASE WHEN s.status IN (\'OPEN\',\'ONGOING\') THEN 0 ELSE 1 END,
                       s.start_date DESC, p.id DESC',
            [$employeeId]
        );
    }

    /** Sesi yang masih menerima pendaftaran untuk karyawan ini. */
    public function availableSessions(int $employeeId, int $limit = 12): array
    {
        $rows = Database::all(
            'SELECT s.*, t.code AS program_code, t.name AS program_name, t.category, t.type,
                    t.provider, t.duration_hours, t.cost_per_participant,
                    (SELECT COUNT(*) FROM training_participants p
                      WHERE p.session_id = s.id AND p.status <> \'CANCELLED\') AS participants,
                    (SELECT p.status FROM training_participants p
                      WHERE p.session_id = s.id AND p.employee_id = ?) AS my_status
               FROM training_sessions s
               JOIN trainings t ON t.id = s.training_id
              WHERE s.status = \'OPEN\'
                AND (s.enroll_deadline IS NULL OR s.enroll_deadline >= ?)
              ORDER BY s.start_date ASC, s.id ASC
              LIMIT ' . max(1, $limit),
            [$employeeId, date('Y-m-d')]
        );

        $available = [];
        foreach ($rows as $row) {
            $quota = (int) $row['quota'];
            $full = $quota > 0 && (int) $row['participants'] >= $quota;
            $mine = $row['my_status'];
            if ($full && $mine === null) {
                continue;
            }
            if ($mine !== null && $mine !== 'CANCELLED') {
                continue;
            }
            $row['slots_left'] = $quota > 0 ? max(0, $quota - (int) $row['participants']) : null;
            $available[] = $row;
        }

        return $available;
    }

    public function employeeStats(int $employeeId): array
    {
        $row = Database::one(
            'SELECT
                SUM(CASE WHEN p.status = \'ATTENDED\' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN p.status IN (\'NOMINATED\',\'APPROVED\') THEN 1 ELSE 0 END) AS upcoming,
                SUM(CASE WHEN p.certificate IS NOT NULL AND p.certificate <> \'\' THEN 1 ELSE 0 END) AS certificates,
                AVG(CASE WHEN p.score IS NOT NULL THEN p.score END) AS avg_score
               FROM training_participants p
              WHERE p.employee_id = ?',
            [$employeeId]
        ) ?? [];

        $hours = Database::value(
            'SELECT COALESCE(SUM(COALESCE(p.attended_hours, t.duration_hours)), 0)
               FROM training_participants p
               JOIN training_sessions s ON s.id = p.session_id
               JOIN trainings t ON t.id = s.training_id
              WHERE p.employee_id = ? AND p.status = \'ATTENDED\'',
            [$employeeId],
            0
        );

        return [
            'completed'    => (int) ($row['completed'] ?? 0),
            'upcoming'     => (int) ($row['upcoming'] ?? 0),
            'certificates' => (int) ($row['certificates'] ?? 0),
            'hours'        => (float) $hours,
            'avg_score'    => $row['avg_score'] !== null ? round((float) $row['avg_score'], 1) : null,
        ];
    }

    /* -------------------------------------------------------------- stats */

    public function stats(): array
    {
        $programs = (int) Database::value('SELECT COUNT(*) FROM trainings WHERE is_active = 1', [], 0);
        $sessions = (int) Database::value(
            'SELECT COUNT(*) FROM training_sessions WHERE status IN (\'OPEN\',\'ONGOING\')',
            [],
            0
        );
        $registered = (int) Database::value(
            'SELECT COUNT(*) FROM training_participants WHERE status IN (\'APPROVED\',\'ATTENDED\')',
            [],
            0
        );
        $attended = (int) Database::value(
            'SELECT COUNT(*) FROM training_participants WHERE status = \'ATTENDED\'',
            [],
            0
        );
        $absent = (int) Database::value(
            'SELECT COUNT(*) FROM training_participants WHERE status = \'ABSENT\'',
            [],
            0
        );
        $hours = (float) Database::value(
            'SELECT COALESCE(SUM(COALESCE(p.attended_hours, t.duration_hours)), 0)
               FROM training_participants p
               JOIN training_sessions s ON s.id = p.session_id
               JOIN trainings t ON t.id = s.training_id
              WHERE p.status = \'ATTENDED\'',
            [],
            0
        );
        $cost = (float) Database::value(
            'SELECT COALESCE(SUM(t.cost_per_participant), 0)
               FROM training_participants p
               JOIN training_sessions s ON s.id = p.session_id
               JOIN trainings t ON t.id = s.training_id
              WHERE p.status = \'ATTENDED\'',
            [],
            0
        );
        $pending = $this->pendingNominationCount();
        $denominator = $attended + $absent;

        return [
            'programs'    => $programs,
            'sessions'    => $sessions,
            'registered'  => $registered,
            'attended'    => $attended,
            'hours'       => $hours,
            'cost'        => $cost,
            'pending'     => $pending,
            'attendance_rate' => $denominator > 0 ? (int) round($attended / $denominator * 100) : 0,
        ];
    }

    /** Jumlah pendaftaran mandiri yang menunggu persetujuan HR (badge). */
    public function pendingNominationCount(): int
    {
        try {
            return (int) Database::value(
                'SELECT COUNT(*) FROM training_participants p
                   JOIN training_sessions s ON s.id = p.session_id
                  WHERE p.status = \'NOMINATED\' AND s.status IN (\'OPEN\',\'ONGOING\')',
                [],
                0
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /* ------------------------------------------------------------ helpers */

    private function notify(int $employeeId, string $title, string $message, string $link, string $type = 'info'): void
    {
        try {
            Database::insert('notifications', [
                'employee_id' => $employeeId,
                'title'       => mb_substr($title, 0, 150),
                'message'     => mb_substr($message, 0, 255),
                'link'        => $link,
                'type'        => $type,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // notifikasi best-effort
        }
    }
}
