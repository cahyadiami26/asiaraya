<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Offboarding & exit management.
 *
 * Tiga lapis data:
 *  - offboarding_requests : pengajuan keluar (resign, PHK, akhir kontrak, pensiun)
 *  - offboarding_clearance: checklist serah terima (pekerjaan, aset, dokumen, keuangan)
 *  - offboarding_stages   : riwayat perpindahan status (audit trail)
 *
 * Alur: DRAFT → SUBMITTED (menunggu atasan) → MANAGER_APPROVED (menunggu HR)
 *       → IN_PROGRESS (serah terima) → COMPLETED (karyawan dinonaktifkan),
 *       dengan REJECTED / WITHDRAWN / CANCELLED sebagai jalur keluar.
 */
final class OffboardingModel
{
    public const TYPES = [
        'RESIGN'       => 'Mengundurkan Diri',
        'TERMINATION'  => 'PHK / Terminasi',
        'CONTRACT_END' => 'Akhir Kontrak',
        'RETIREMENT'   => 'Pensiun',
        'ABANDON'      => 'Mangkir',
    ];

    public const TYPE_TONES = [
        'RESIGN'       => 'amber',
        'TERMINATION'  => 'rose',
        'CONTRACT_END' => 'indigo',
        'RETIREMENT'   => 'teal',
        'ABANDON'      => 'slate',
    ];

    public const STATUSES = [
        'DRAFT'            => 'Draf',
        'SUBMITTED'        => 'Menunggu Atasan',
        'MANAGER_APPROVED' => 'Menunggu HR',
        'IN_PROGRESS'      => 'Serah Terima',
        'COMPLETED'        => 'Selesai',
        'REJECTED'         => 'Ditolak',
        'WITHDRAWN'        => 'Dibatalkan Karyawan',
        'CANCELLED'        => 'Dibatalkan HR',
    ];

    public const STATUS_TONES = [
        'DRAFT'            => 'slate',
        'SUBMITTED'        => 'amber',
        'MANAGER_APPROVED' => 'indigo',
        'IN_PROGRESS'      => 'teal',
        'COMPLETED'        => 'slate',
        'REJECTED'         => 'rose',
        'WITHDRAWN'        => 'slate',
        'CANCELLED'        => 'slate',
    ];

    /** Status yang masih berjalan (memblokir pengajuan baru). */
    public const ACTIVE_STATUSES = ['DRAFT', 'SUBMITTED', 'MANAGER_APPROVED', 'IN_PROGRESS'];

    public const REASONS = [
        'CAREER'      => 'Peluang karier baru',
        'SALARY'      => 'Kompensasi & tunjangan',
        'RELOCATION'  => 'Relokasi / pindah domisili',
        'HEALTH'      => 'Alasan kesehatan',
        'FAMILY'      => 'Alasan keluarga',
        'ENVIRONMENT' => 'Lingkungan kerja',
        'PERFORMANCE' => 'Kinerja',
        'MISCONDUCT'  => 'Pelanggaran aturan',
        'CONTRACT'    => 'Kontrak berakhir',
        'OTHER'       => 'Lainnya',
    ];

    public const CLEARANCE_CATEGORIES = [
        'HANDOVER' => 'Serah Terima Pekerjaan',
        'ASSET'    => 'Aset & Perangkat',
        'DOCUMENT' => 'Dokumen',
        'FINANCE'  => 'Keuangan',
        'ACCESS'   => 'Akses Sistem',
        'OTHER'    => 'Lainnya',
    ];

    public const CLEARANCE_STATUSES = [
        'PENDING' => 'Belum Selesai',
        'DONE'    => 'Selesai',
        'WAIVED'  => 'Dikecualikan',
    ];

    public const CLEARANCE_TONES = [
        'PENDING' => 'amber',
        'DONE'    => 'teal',
        'WAIVED'  => 'slate',
    ];

    public const OWNER_ROLES = [
        'HR'      => 'HR',
        'MANAGER' => 'Atasan Langsung',
        'FINANCE' => 'Finance',
        'IT'      => 'IT',
    ];

    public const RECOMMENDATIONS = [
        'YES'   => 'Ya, bersedia',
        'MAYBE' => 'Mungkin',
        'NO'    => 'Tidak',
    ];

    /** Checklist default yang dibuat saat HR menyetujui pengajuan. */
    public const DEFAULT_CLEARANCE = [
        ['item' => 'Serah terima pekerjaan & dokumen proyek', 'category' => 'HANDOVER', 'owner_role' => 'MANAGER'],
        ['item' => 'Pengembalian laptop & perangkat kerja', 'category' => 'ASSET', 'owner_role' => 'IT'],
        ['item' => 'Pengembalian kartu identitas & akses kantor', 'category' => 'ASSET', 'owner_role' => 'HR'],
        ['item' => 'Penonaktifan akun email & akses sistem', 'category' => 'ACCESS', 'owner_role' => 'IT'],
        ['item' => 'Penyelesaian kasbon, klaim & pinjaman', 'category' => 'FINANCE', 'owner_role' => 'FINANCE'],
        ['item' => 'Penyerahan surat pengalaman kerja & dokumen kepegawaian', 'category' => 'DOCUMENT', 'owner_role' => 'HR'],
    ];

    /* ============================================================== daftar */

    /**
     * @param array{status:string,type:string,unit:int,department:int,q:string} $filters
     * @return array{0:string,1:array}
     */
    private function requestWhere(array $filters): array
    {
        $where = [];
        $params = [];

        $status = (string) ($filters['status'] ?? '');
        if ($status === 'OPEN') {
            $where[] = "r.status IN ('SUBMITTED','MANAGER_APPROVED','IN_PROGRESS')";
        } elseif ($status !== '' && isset(self::STATUSES[$status])) {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }

        if (($filters['type'] ?? '') !== '' && isset(self::TYPES[(string) $filters['type']])) {
            $where[] = 'r.type = ?';
            $params[] = (string) $filters['type'];
        }
        if ((int) ($filters['unit'] ?? 0) > 0) {
            $where[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if ((int) ($filters['department'] ?? 0) > 0) {
            $where[] = 'e.department_id = ?';
            $params[] = (int) $filters['department'];
        }
        if (($filters['q'] ?? '') !== '') {
            $like = Database::like((string) $filters['q']);
            $where[] = '(r.request_number LIKE ? OR e.full_name LIKE ? OR e.employee_code LIKE ? OR r.reason LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private const SELECT_COLUMNS = "r.*, e.full_name, e.employee_code, e.email AS employee_email,
            e.photo, e.employment_status, e.contract_end, e.flag AS employee_flag,
            e.business_unit_id, e.department_id, e.join_date,
            u.name AS unit_name, d.name AS department_name, p.name AS position_name,
            m.full_name AS manager_name, h.full_name AS hr_name,
            hi.full_name AS interviewer_name, ho.full_name AS handover_name,
            (SELECT COUNT(*) FROM offboarding_clearance c WHERE c.request_id = r.id) AS clearance_total,
            (SELECT COUNT(*) FROM offboarding_clearance c2 WHERE c2.request_id = r.id AND c2.status IN ('DONE','WAIVED')) AS clearance_done";

    private const JOINS = " LEFT JOIN employees e ON e.id = r.employee_id
            LEFT JOIN business_units u ON u.id = e.business_unit_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN positions p ON p.id = e.position_id
            LEFT JOIN employees m ON m.id = r.manager_id
            LEFT JOIN employees h ON h.id = r.hr_approved_by
            LEFT JOIN employees hi ON hi.id = r.exit_interviewer_id
            LEFT JOIN employees ho ON ho.id = r.handover_to";

    public function requests(array $filters, int $limit = 20, int $offset = 0): array
    {
        [$where, $params] = $this->requestWhere($filters);

        return Database::all(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM offboarding_requests r' . self::JOINS
            . $where . ' ORDER BY r.last_working_date ASC, r.id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $params
        );
    }

    public function countRequests(array $filters): int
    {
        [$where, $params] = $this->requestWhere($filters);

        return (int) Database::value(
            'SELECT COUNT(*) FROM offboarding_requests r LEFT JOIN employees e ON e.id = r.employee_id' . $where,
            $params,
            0
        );
    }

    public function find(int $id): ?array
    {
        return Database::one(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM offboarding_requests r' . self::JOINS . ' WHERE r.id = ?',
            [$id]
        );
    }

    /** Ringkasan angka untuk kepala halaman. */
    public function stats(): array
    {
        $row = Database::one(
            "SELECT
                SUM(CASE WHEN status = 'SUBMITTED' THEN 1 ELSE 0 END) AS submitted,
                SUM(CASE WHEN status = 'MANAGER_APPROVED' THEN 1 ELSE 0 END) AS waiting_hr,
                SUM(CASE WHEN status = 'IN_PROGRESS' THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = 'COMPLETED' AND completed_at >= ? THEN 1 ELSE 0 END) AS completed_month,
                SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) AS rejected
             FROM offboarding_requests",
            [date('Y-m-01') . ' 00:00:00']
        ) ?? [];

        $next30 = Database::one(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN type = 'RESIGN' THEN 1 ELSE 0 END) AS resign
               FROM offboarding_requests
              WHERE status IN ('SUBMITTED','MANAGER_APPROVED','IN_PROGRESS')
                AND last_working_date BETWEEN ? AND ?",
            [date('Y-m-d'), date('Y-m-d', strtotime('+30 days'))]
        ) ?? [];

        $avgNotice = (float) (Database::value(
            "SELECT COALESCE(AVG(notice_days), 0) FROM offboarding_requests WHERE status <> 'DRAFT'",
            [],
            0
        ) ?: 0);

        return [
            'submitted'       => (int) ($row['submitted'] ?? 0),
            'waiting_hr'      => (int) ($row['waiting_hr'] ?? 0),
            'in_progress'     => (int) ($row['in_progress'] ?? 0),
            'completed'       => (int) ($row['completed'] ?? 0),
            'completed_month' => (int) ($row['completed_month'] ?? 0),
            'rejected'        => (int) ($row['rejected'] ?? 0),
            'exit_30'         => (int) ($next30['total'] ?? 0),
            'exit_30_resign'  => (int) ($next30['resign'] ?? 0),
            'avg_notice'      => (int) round($avgNotice),
        ];
    }

    /** Jumlah pengajuan yang menunggu keputusan (badge navigasi). */
    public function pendingCount(): int
    {
        try {
            return (int) Database::value(
                "SELECT COUNT(*) FROM offboarding_requests WHERE status IN ('SUBMITTED','MANAGER_APPROVED')",
                [],
                0
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Karyawan aktif yang kontraknya berakhir dalam N hari. */
    public function expiringContracts(int $days = 90, int $limit = 8): array
    {
        return Database::all(
            "SELECT e.id, e.employee_code, e.full_name, e.contract_end, e.business_unit_id,
                    d.name AS department_name, u.name AS unit_name,
                    (SELECT r.id FROM offboarding_requests r
                      WHERE r.employee_id = e.id
                        AND r.status IN ('DRAFT','SUBMITTED','MANAGER_APPROVED','IN_PROGRESS')
                      ORDER BY r.id DESC LIMIT 1) AS request_id
               FROM employees e
               LEFT JOIN departments d ON d.id = e.department_id
               LEFT JOIN business_units u ON u.id = e.business_unit_id
              WHERE e.flag = 'ACTIVE' AND e.contract_end IS NOT NULL
                AND e.contract_end BETWEEN ? AND ?
              ORDER BY e.contract_end ASC LIMIT " . max(1, $limit),
            [date('Y-m-d'), date('Y-m-d', strtotime('+' . max(1, $days) . ' days'))]
        );
    }

    /* ------------------------------------------------------------- detail */

    public function clearance(int $requestId): array
    {
        return Database::all(
            'SELECT c.*, e.full_name AS done_name
               FROM offboarding_clearance c
               LEFT JOIN employees e ON e.id = c.done_by
              WHERE c.request_id = ?
              ORDER BY CASE c.category WHEN \'HANDOVER\' THEN 0 WHEN \'ASSET\' THEN 1 WHEN \'ACCESS\' THEN 2
                                       WHEN \'DOCUMENT\' THEN 3 WHEN \'FINANCE\' THEN 4 ELSE 5 END, c.id ASC',
            [$requestId]
        );
    }

    public function stages(int $requestId): array
    {
        return Database::all(
            'SELECT s.*, e.full_name AS actor_name
               FROM offboarding_stages s
               LEFT JOIN employees e ON e.id = s.actor_id
              WHERE s.request_id = ?
              ORDER BY s.id ASC',
            [$requestId]
        );
    }

    /** @return array{done:int,total:int,pct:int} */
    public function progress(int $requestId): array
    {
        $row = Database::one(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status IN ('DONE','WAIVED') THEN 1 ELSE 0 END) AS done
               FROM offboarding_clearance WHERE request_id = ?",
            [$requestId]
        ) ?? ['total' => 0, 'done' => 0];

        $total = (int) ($row['total'] ?? 0);
        $done = (int) ($row['done'] ?? 0);

        return ['done' => $done, 'total' => $total, 'pct' => $total > 0 ? (int) round($done / $total * 100) : 0];
    }

    public function employeeRequests(int $employeeId): array
    {
        return Database::all(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM offboarding_requests r' . self::JOINS
            . ' WHERE r.employee_id = ? ORDER BY r.id DESC LIMIT 20',
            [$employeeId]
        );
    }

    public function activeForEmployee(int $employeeId): ?array
    {
        return Database::one(
            "SELECT * FROM offboarding_requests
              WHERE employee_id = ? AND status IN ('DRAFT','SUBMITTED','MANAGER_APPROVED','IN_PROGRESS')
              ORDER BY id DESC LIMIT 1",
            [$employeeId]
        );
    }

    /** Pengajuan tim yang menunggu persetujuan atasan. */
    public function pendingForManager(int $managerId): array
    {
        return Database::all(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM offboarding_requests r' . self::JOINS
            . " WHERE r.status = 'SUBMITTED' AND (r.manager_id = ? OR e.direct_manager_id = ?)
                ORDER BY r.submit_date ASC, r.id ASC",
            [$managerId, $managerId]
        );
    }

    /* ============================================================== proses */

    private function nextNumber(): string
    {
        $prefix = 'EXIT-' . date('Y') . '-';
        $last = (string) (Database::value(
            'SELECT request_number FROM offboarding_requests WHERE request_number LIKE ? ORDER BY id DESC LIMIT 1',
            [$prefix . '%']
        ) ?? '');
        $sequence = $last !== '' ? (int) substr($last, -4) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Buat pengajuan baru.
     *
     * @param array<string,mixed> $data
     * @return array{id:?int,error:?string,number:?string,status:?string}
     */
    public function create(array $data, int $actorId, bool $byHr = false): array
    {
        $employee = Database::one('SELECT * FROM employees WHERE id = ?', [(int) ($data['employee_id'] ?? 0)]);
        if (!$employee) {
            return ['id' => null, 'error' => 'Karyawan tidak ditemukan.', 'number' => null, 'status' => null];
        }
        if ($employee['flag'] !== 'ACTIVE') {
            return ['id' => null, 'error' => 'Karyawan ' . $employee['full_name'] . ' berstatus ' . $employee['flag'] . ', tidak dapat diproses offboarding.', 'number' => null, 'status' => null];
        }
        if ($this->activeForEmployee((int) $employee['id']) !== null) {
            return ['id' => null, 'error' => 'Sudah ada pengajuan offboarding yang berjalan untuk ' . $employee['full_name'] . '.', 'number' => null, 'status' => null];
        }

        $lastWorking = (string) ($data['last_working_date'] ?? '');
        if ($lastWorking === '' || strtotime($lastWorking) === false) {
            return ['id' => null, 'error' => 'Tanggal hari terakhir kerja tidak valid.', 'number' => null, 'status' => null];
        }
        $submitDate = (string) ($data['submit_date'] ?? date('Y-m-d'));
        if (strtotime($submitDate) === false) {
            $submitDate = date('Y-m-d');
        }
        if (strtotime($lastWorking) < strtotime($submitDate)) {
            return ['id' => null, 'error' => 'Hari terakhir kerja tidak boleh sebelum tanggal pengajuan.', 'number' => null, 'status' => null];
        }

        $managerId = (int) ($employee['direct_manager_id'] ?? 0);
        $status = $byHr || $managerId === 0 ? 'MANAGER_APPROVED' : 'SUBMITTED';
        $now = date('Y-m-d H:i:s');
        $number = $this->nextNumber();

        $id = Database::insert('offboarding_requests', [
            'request_number'    => $number,
            'employee_id'       => (int) $employee['id'],
            'type'              => isset(self::TYPES[(string) ($data['type'] ?? '')]) ? (string) $data['type'] : 'RESIGN',
            'reason_category'   => isset(self::REASONS[(string) ($data['reason_category'] ?? '')]) ? (string) $data['reason_category'] : null,
            'reason'            => trim((string) ($data['reason'] ?? '')) ?: null,
            'submit_date'       => $submitDate,
            'last_working_date' => $lastWorking,
            'notice_days'       => (int) round((strtotime($lastWorking) - strtotime($submitDate)) / 86400),
            'handover_to'       => (int) ($data['handover_to'] ?? 0) > 0 ? (int) $data['handover_to'] : null,
            'status'            => $status,
            'manager_id'        => $managerId > 0 ? $managerId : null,
            'created_by'        => $actorId,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        $note = $byHr
            ? 'Pengajuan dibuat HR dengan jenis ' . self::TYPES[(string) ($data['type'] ?? 'RESIGN')] . '.'
            : 'Pengajuan diajukan karyawan.';
        $this->logStage($id, null, $status, $note, $actorId);

        if ($status === 'SUBMITTED' && $managerId > 0) {
            $this->notify($managerId, 'Pengajuan offboarding menunggu persetujuan',
                $employee['full_name'] . ' mengajukan pengunduran diri per ' . tanggal($lastWorking) . '.',
                '/offboarding/' . $id, 'warning');
        } else {
            $this->notifyRole('HR', 'Pengajuan offboarding baru',
                $employee['full_name'] . ' — ' . (self::TYPES[(string) ($data['type'] ?? 'RESIGN')] ?? 'Offboarding')
                . ' per ' . tanggal($lastWorking) . '.', '/offboarding/' . $id);
        }

        return ['id' => $id, 'error' => null, 'number' => $number, 'status' => $status];
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        Database::update('offboarding_requests', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    /** Setujui pada tahap atasan langsung. */
    public function approveManager(int $id, int $actorId, string $note = ''): array
    {
        $request = $this->find($id);
        if (!$request) {
            return ['error' => 'Pengajuan tidak ditemukan.'];
        }
        if ($request['status'] !== 'SUBMITTED') {
            return ['error' => 'Pengajuan tidak berada pada tahap persetujuan atasan.'];
        }

        $now = date('Y-m-d H:i:s');
        Database::update('offboarding_requests', [
            'status'              => 'MANAGER_APPROVED',
            'manager_approved_by' => $actorId,
            'manager_approved_at' => $now,
            'updated_at'          => $now,
        ], 'id = ?', [$id]);

        $this->logStage($id, 'SUBMITTED', 'MANAGER_APPROVED', $note !== '' ? $note : 'Disetujui atasan langsung.', $actorId);

        $this->notify((int) $request['employee_id'], 'Pengajuan offboarding disetujui atasan',
            'Pengajuan ' . $request['request_number'] . ' diteruskan ke HR untuk proses serah terima.',
            '/me/offboarding', 'info');
        $this->notifyRole('HR', 'Offboarding menunggu persetujuan HR',
            $request['full_name'] . ' — ' . $request['request_number'] . ' telah disetujui atasan.', '/offboarding/' . $id);

        return ['error' => null, 'status' => 'MANAGER_APPROVED'];
    }

    /** Setujui oleh HR: membuat checklist serah terima dan membuka proses. */
    public function approveHr(int $id, int $actorId, string $note = ''): array
    {
        $request = $this->find($id);
        if (!$request) {
            return ['error' => 'Pengajuan tidak ditemukan.'];
        }
        if (!in_array($request['status'], ['SUBMITTED', 'MANAGER_APPROVED'], true)) {
            return ['error' => 'Pengajuan ini tidak dapat disetujui pada status saat ini.'];
        }

        $now = date('Y-m-d H:i:s');
        Database::update('offboarding_requests', [
            'status'         => 'IN_PROGRESS',
            'hr_approved_by' => $actorId,
            'hr_approved_at' => $now,
            'submit_date'    => $request['submit_date'] ?: date('Y-m-d'),
            'updated_at'     => $now,
        ], 'id = ?', [$id]);

        $this->seedClearance($id);
        $this->logStage($id, (string) $request['status'], 'IN_PROGRESS',
            $note !== '' ? $note : 'Disetujui HR, checklist serah terima dibuka.', $actorId);

        $this->notify((int) $request['employee_id'], 'Pengajuan offboarding disetujui HR',
            'Silakan selesaikan checklist serah terima sebelum ' . tanggal($request['last_working_date']) . '.',
            '/me/offboarding', 'success');
        if (!empty($request['manager_id'])) {
            $this->notify((int) $request['manager_id'], 'Serah terima offboarding dimulai',
                $request['full_name'] . ' akan keluar per ' . tanggal($request['last_working_date']) . '. Mohon selesaikan item serah terima.',
                '/offboarding/' . $id);
        }
        $this->notifyRole('FINANCE', 'Offboarding memerlukan penyelesaian keuangan',
            $request['full_name'] . ' — periksa kasbon, klaim, dan potongan akhir.', '/offboarding/' . $id);

        return ['error' => null, 'status' => 'IN_PROGRESS'];
    }

    public function reject(int $id, int $actorId, string $reason): array
    {
        $request = $this->find($id);
        if (!$request) {
            return ['error' => 'Pengajuan tidak ditemukan.'];
        }
        if (!in_array($request['status'], ['DRAFT', 'SUBMITTED', 'MANAGER_APPROVED', 'IN_PROGRESS'], true)) {
            return ['error' => 'Pengajuan ini sudah selesai dan tidak dapat ditolak.'];
        }
        if (trim($reason) === '') {
            return ['error' => 'Alasan penolakan wajib diisi.'];
        }

        Database::update('offboarding_requests', [
            'status'        => 'REJECTED',
            'reject_reason' => mb_substr(trim($reason), 0, 255),
            'updated_at'    => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logStage($id, (string) $request['status'], 'REJECTED', trim($reason), $actorId);
        $this->notify((int) $request['employee_id'], 'Pengajuan offboarding ditolak',
            $request['request_number'] . ' ditolak: ' . trim($reason), '/me/offboarding', 'warning');

        return ['error' => null, 'status' => 'REJECTED'];
    }

    public function withdraw(int $id, int $employeeId): array
    {
        $request = $this->find($id);
        if (!$request) {
            return ['error' => 'Pengajuan tidak ditemukan.'];
        }
        if ((int) $request['employee_id'] !== $employeeId) {
            return ['error' => 'Anda hanya dapat membatalkan pengajuan sendiri.'];
        }
        if (!in_array($request['status'], ['DRAFT', 'SUBMITTED', 'MANAGER_APPROVED', 'IN_PROGRESS'], true)) {
            return ['error' => 'Pengajuan ini sudah selesai dan tidak dapat dibatalkan.'];
        }

        Database::update('offboarding_requests', [
            'status'     => 'WITHDRAWN',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logStage($id, (string) $request['status'], 'WITHDRAWN', 'Dibatalkan oleh karyawan.', $employeeId);
        if (!empty($request['manager_id'])) {
            $this->notify((int) $request['manager_id'], 'Pengajuan offboarding dibatalkan',
                $request['full_name'] . ' membatalkan pengajuan ' . $request['request_number'] . '.', '/offboarding/' . $id);
        }
        $this->notifyRole('HR', 'Pengajuan offboarding dibatalkan karyawan',
            $request['full_name'] . ' — ' . $request['request_number'] . '.', '/offboarding/' . $id);

        return ['error' => null, 'status' => 'WITHDRAWN'];
    }

    public function cancel(int $id, int $actorId): array
    {
        $request = $this->find($id);
        if (!$request) {
            return ['error' => 'Pengajuan tidak ditemukan.'];
        }
        if (!in_array($request['status'], ['DRAFT', 'SUBMITTED', 'MANAGER_APPROVED', 'IN_PROGRESS'], true)) {
            return ['error' => 'Pengajuan ini sudah selesai dan tidak dapat dibatalkan.'];
        }

        Database::update('offboarding_requests', [
            'status'     => 'CANCELLED',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->logStage($id, (string) $request['status'], 'CANCELLED', 'Dibatalkan oleh HR.', $actorId);
        $this->notify((int) $request['employee_id'], 'Pengajuan offboarding dibatalkan HR',
            $request['request_number'] . ' dibatalkan. Hubungi HR untuk informasi lanjutan.', '/me/offboarding', 'warning');

        return ['error' => null, 'status' => 'CANCELLED'];
    }

    /** Tutup proses: seluruh checklist selesai, karyawan dinonaktifkan. */
    public function complete(int $id, int $actorId): array
    {
        $request = $this->find($id);
        if (!$request) {
            return ['error' => 'Pengajuan tidak ditemukan.'];
        }
        if ($request['status'] !== 'IN_PROGRESS') {
            return ['error' => 'Hanya pengajuan pada tahap serah terima yang dapat diselesaikan.'];
        }

        $progress = $this->progress($id);
        if ($progress['total'] === 0) {
            return ['error' => 'Checklist serah terima belum tersedia.'];
        }
        if ($progress['done'] < $progress['total']) {
            return ['error' => 'Masih ada ' . ($progress['total'] - $progress['done']) . ' item serah terima yang belum selesai.'];
        }

        $now = date('Y-m-d H:i:s');
        Database::transaction(function () use ($id, $request, $actorId, $now): void {
            Database::update('offboarding_requests', [
                'status'       => 'COMPLETED',
                'completed_by' => $actorId,
                'completed_at' => $now,
                'updated_at'   => $now,
            ], 'id = ?', [$id]);

            Database::update('employees', [
                'flag'        => 'RESIGN',
                'resign_date' => $request['last_working_date'],
                'updated_at'  => $now,
            ], 'id = ?', [(int) $request['employee_id']]);
        });

        $this->logStage($id, 'IN_PROGRESS', 'COMPLETED',
            'Offboarding selesai, karyawan dinonaktifkan per ' . tanggal($request['last_working_date']) . '.', $actorId);

        $this->notify((int) $request['employee_id'], 'Offboarding selesai',
            'Terima kasih atas kontribusi Anda. Surat pengalaman kerja dapat diambil di HR.',
            '/me/offboarding', 'success');
        if (!empty($request['manager_id'])) {
            $this->notify((int) $request['manager_id'], 'Offboarding karyawan selesai',
                $request['full_name'] . ' telah keluar per ' . tanggal($request['last_working_date']) . '.', '/offboarding/' . $id);
        }
        $this->notifyRole('HR', 'Offboarding selesai',
            $request['full_name'] . ' — ' . $request['request_number'] . ' telah ditutup.', '/offboarding/' . $id);

        return ['error' => null, 'status' => 'COMPLETED'];
    }

    public function delete(int $id): array
    {
        $request = $this->find($id);
        if (!$request) {
            return ['error' => 'Pengajuan tidak ditemukan.'];
        }
        if (!in_array($request['status'], ['DRAFT', 'REJECTED', 'WITHDRAWN', 'CANCELLED'], true)) {
            return ['error' => 'Hanya pengajuan draf/ditolak/dibatalkan yang dapat dihapus.'];
        }

        Database::delete('offboarding_clearance', 'request_id = ?', [$id]);
        Database::delete('offboarding_stages', 'request_id = ?', [$id]);
        Database::delete('offboarding_requests', 'id = ?', [$id]);

        return ['error' => null];
    }

    /* --------------------------------------------------------- clearance */

    /** Buat checklist default (idempoten: dilewati bila sudah ada item). */
    public function seedClearance(int $requestId): void
    {
        $exists = (int) Database::value('SELECT COUNT(*) FROM offboarding_clearance WHERE request_id = ?', [$requestId], 0);
        if ($exists > 0) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach (self::DEFAULT_CLEARANCE as $item) {
            Database::insert('offboarding_clearance', [
                'request_id' => $requestId,
                'item'       => $item['item'],
                'category'   => $item['category'],
                'owner_role' => $item['owner_role'],
                'status'     => 'PENDING',
                'created_at' => $now,
            ]);
        }
    }

    /** @param array<string,mixed> $data */
    public function addClearance(int $requestId, array $data): int
    {
        return Database::insert('offboarding_clearance', [
            'request_id' => $requestId,
            'item'       => mb_substr(trim((string) ($data['item'] ?? '')), 0, 150),
            'category'   => isset(self::CLEARANCE_CATEGORIES[(string) ($data['category'] ?? '')]) ? (string) $data['category'] : 'OTHER',
            'owner_role' => isset(self::OWNER_ROLES[(string) ($data['owner_role'] ?? '')]) ? (string) $data['owner_role'] : 'HR',
            'status'     => 'PENDING',
            'note'       => trim((string) ($data['note'] ?? '')) ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function setClearanceStatus(int $itemId, int $requestId, string $status, string $note, int $actorId): array
    {
        if (!isset(self::CLEARANCE_STATUSES[$status])) {
            return ['error' => 'Status item tidak dikenal.'];
        }
        $item = Database::one('SELECT * FROM offboarding_clearance WHERE id = ? AND request_id = ?', [$itemId, $requestId]);
        if (!$item) {
            return ['error' => 'Item serah terima tidak ditemukan.'];
        }

        $done = $status === 'PENDING' ? null : $actorId;
        Database::update('offboarding_clearance', [
            'status'  => $status,
            'note'    => $note !== '' ? mb_substr($note, 0, 255) : $item['note'],
            'done_by' => $done,
            'done_at' => $done !== null ? date('Y-m-d H:i:s') : null,
        ], 'id = ?', [$itemId]);

        return ['error' => null];
    }

    public function removeClearance(int $itemId, int $requestId): array
    {
        $item = Database::one('SELECT * FROM offboarding_clearance WHERE id = ? AND request_id = ?', [$itemId, $requestId]);
        if (!$item) {
            return ['error' => 'Item serah terima tidak ditemukan.'];
        }
        Database::delete('offboarding_clearance', 'id = ?', [$itemId]);

        return ['error' => null];
    }

    /** Tandai semua item tersisa sebagai selesai. */
    public function completeAllClearance(int $requestId, int $actorId): int
    {
        return Database::update('offboarding_clearance', [
            'status'  => 'DONE',
            'done_by' => $actorId,
            'done_at' => date('Y-m-d H:i:s'),
        ], "request_id = ? AND status = 'PENDING'", [$requestId]);
    }

    /* ---------------------------------------------------- exit interview */

    /** @param array<string,mixed> $data */
    public function saveInterview(int $requestId, array $data, int $actorId): array
    {
        $request = $this->find($requestId);
        if (!$request) {
            return ['error' => 'Pengajuan tidak ditemukan.'];
        }

        $recommend = (string) ($data['exit_would_recommend'] ?? '');
        Database::update('offboarding_requests', [
            'exit_interview_at'     => trim((string) ($data['exit_interview_at'] ?? '')) ?: null,
            'exit_interviewer_id'   => (int) ($data['exit_interviewer_id'] ?? 0) > 0 ? (int) $data['exit_interviewer_id'] : null,
            'exit_note'             => trim((string) ($data['exit_note'] ?? '')) ?: null,
            'exit_would_recommend'  => isset(self::RECOMMENDATIONS[$recommend]) ? $recommend : null,
            'settlement_amount'     => (float) ($data['settlement_amount'] ?? 0),
            'settlement_note'       => trim((string) ($data['settlement_note'] ?? '')) ?: null,
            'updated_at'            => date('Y-m-d H:i:s'),
        ], 'id = ?', [$requestId]);

        $this->logStage($requestId, (string) $request['status'], (string) $request['status'],
            'Exit interview & penyelesaian akhir diperbarui.', $actorId);

        return ['error' => null];
    }

    /** Karyawan mengisi umpan balik keluar. */
    public function saveFeedback(int $requestId, int $employeeId, array $data): array
    {
        $request = $this->find($requestId);
        if (!$request) {
            return ['error' => 'Pengajuan tidak ditemukan.'];
        }
        if ((int) $request['employee_id'] !== $employeeId) {
            return ['error' => 'Anda hanya dapat mengisi umpan balik pengajuan sendiri.'];
        }

        $recommend = (string) ($data['exit_would_recommend'] ?? '');
        Database::update('offboarding_requests', [
            'exit_feedback'        => trim((string) ($data['exit_feedback'] ?? '')) ?: null,
            'exit_would_recommend' => isset(self::RECOMMENDATIONS[$recommend]) ? $recommend : $request['exit_would_recommend'],
            'updated_at'           => date('Y-m-d H:i:s'),
        ], 'id = ?', [$requestId]);

        return ['error' => null];
    }

    /* ---------------------------------------------------------------- misc */

    private function logStage(int $requestId, ?string $from, string $to, ?string $note, ?int $actorId): void
    {
        try {
            Database::insert('offboarding_stages', [
                'request_id'  => $requestId,
                'from_status' => $from,
                'to_status'   => $to,
                'note'        => $note !== null ? mb_substr($note, 0, 255) : null,
                'actor_id'    => $actorId,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // riwayat bersifat best-effort
        }
    }

    public function notify(int $employeeId, string $title, string $message, string $link, string $type = 'info'): void
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

    private function notifyRole(string $role, string $title, string $message, string $link): void
    {
        try {
            $ids = Database::all('SELECT id FROM employees WHERE role = ? AND flag = ?', [$role, 'ACTIVE']);
            foreach ($ids as $row) {
                $this->notify((int) $row['id'], $title, $message, $link);
            }
        } catch (\Throwable) {
            // abaikan
        }
    }
}
