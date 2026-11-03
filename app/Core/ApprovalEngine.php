<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\LeaveModel;
use App\Models\OvertimeModel;
use App\Models\ScheduleModel;

/**
 * Mesin persetujuan dua tahap untuk cuti, lembur, dan perubahan jadwal.
 *
 * Tahap 1 — atasan langsung : PENDING → MANAGER_APPROVED
 * Tahap 2 — HR             : MANAGER_APPROVED → APPROVED (final)
 * Penolakan pada tahap mana pun mengakhiri pengajuan sebagai REJECTED.
 *
 * Pengecualian: bila pemohon sendiri adalah satu-satunya approver final
 * (mis. HR satu-satunya di perusahaan), tahap HR tidak mungkin diputus tanpa
 * menyetujui pengajuan sendiri — sehingga persetujuan atasan langsung langsung
 * menjadi keputusan final (PENDING → APPROVED).
 *
 * Dipakai bersama oleh halaman web (ApprovalController) dan REST API
 * (Api\V1\ApprovalApiController) supaya aturan & efek sampingnya identik.
 */
final class ApprovalEngine
{
    public const TABLES = [
        'leave'    => 'leave_requests',
        'overtime' => 'overtime_requests',
        'schedule' => 'shift_change_requests',
    ];

    public const LABELS = [
        'leave'    => 'Pengajuan Cuti',
        'overtime' => 'Pengajuan Lembur',
        'schedule' => 'Perubahan Jadwal',
    ];

    public const STATUS_LABELS = [
        'PENDING'          => 'Menunggu',
        'MANAGER_APPROVED' => 'Disetujui Atasan',
        'APPROVED'         => 'Disetujui',
        'REJECTED'         => 'Ditolak',
        'CANCELLED'        => 'Dibatalkan',
    ];

    /** Peran yang berhak memutus tahap final cuti, lembur, dan perubahan jadwal. */
    public const FINAL_ROLES = ['HR'];

    /** @var array<int, ?int> */
    private static array $managerCache = [];

    /** @var array<int, ?string> */
    private static array $roleCache = [];

    /** @var array<string, bool> */
    private static array $approverCache = [];

    /** Peran final per jenis pengajuan; klaim biaya juga boleh diputus Finance. */
    public static function finalRoles(string $type): array
    {
        return $type === 'claim' ? ['HR', 'FIN'] : self::FINAL_ROLES;
    }

    /** Peran karyawan pada tabel employees (null bila tidak ada). */
    public static function roleOf(int $employeeId): ?string
    {
        if (array_key_exists($employeeId, self::$roleCache)) {
            return self::$roleCache[$employeeId];
        }
        if ($employeeId <= 0) {
            return self::$roleCache[$employeeId] = null;
        }
        $role = Database::value('SELECT role FROM employees WHERE id = ?', [$employeeId]);

        return self::$roleCache[$employeeId] = $role !== null ? (string) $role : null;
    }

    /**
     * Atasan langsung yang sah: aktif dan bukan karyawan itu sendiri.
     * Mengembalikan null bila belum ditetapkan, nonaktif, atau menunjuk diri sendiri.
     */
    public static function managerOf(int $employeeId): ?int
    {
        if (array_key_exists($employeeId, self::$managerCache)) {
            return self::$managerCache[$employeeId];
        }
        if ($employeeId <= 0) {
            return self::$managerCache[$employeeId] = null;
        }
        $row = Database::one(
            'SELECT m.id FROM employees e
               JOIN employees m ON m.id = e.direct_manager_id
              WHERE e.id = ? AND m.flag = ? AND m.id <> e.id',
            [$employeeId, 'ACTIVE']
        );

        return self::$managerCache[$employeeId] = $row !== null ? (int) $row['id'] : null;
    }

    /** Masih ada approver final lain (aktif) selain karyawan ini? */
    public static function hasOtherFinalApprover(int $employeeId, array $roles): bool
    {
        $roles = array_values(array_unique(array_map('strval', $roles)));
        if ($roles === []) {
            return false;
        }
        $key = $employeeId . '|' . implode(',', $roles);
        if (array_key_exists($key, self::$approverCache)) {
            return self::$approverCache[$key];
        }

        $placeholders = implode(', ', array_fill(0, count($roles), '?'));
        $found = Database::value(
            'SELECT id FROM employees WHERE role IN (' . $placeholders . ') AND flag = ? AND id <> ? LIMIT 1',
            array_merge($roles, ['ACTIVE', $employeeId])
        );

        return self::$approverCache[$key] = $found !== null;
    }

    /**
     * Pemohon adalah approver final dan tidak ada approver final lain, sehingga
     * persetujuan atasan langsung adalah keputusan terakhir yang mungkin.
     */
    public static function managerStageIsFinal(int $employeeId, array $roles): bool
    {
        $role = self::roleOf($employeeId);

        return $role !== null
            && in_array($role, $roles, true)
            && !self::hasOtherFinalApprover($employeeId, $roles);
    }

    /**
     * Pengajuan masih harus menunggu atasan langsung, sehingga approver final
     * (HR/Finance selain pemohon) belum boleh memutusnya.
     */
    public static function awaitingManager(array $request, array $roles): bool
    {
        if ((string) ($request['status'] ?? '') !== 'PENDING') {
            return false;
        }
        $employeeId = (int) ($request['employee_id'] ?? 0);
        if (!in_array((string) self::roleOf($employeeId), $roles, true)) {
            return false;
        }

        return self::managerOf($employeeId) !== null;
    }

    /**
     * Alasan pengajuan tidak dapat dibuat karena tidak akan ada approver sama
     * sekali; null berarti aman diajukan.
     */
    public static function submissionError(int $employeeId, string $type): ?string
    {
        if (self::managerOf($employeeId) !== null) {
            return null;
        }
        $roles = self::finalRoles($type);
        if (!in_array((string) self::roleOf($employeeId), $roles, true)) {
            return null;
        }
        if (self::hasOtherFinalApprover($employeeId, $roles)) {
            return null;
        }

        return 'Atasan langsung Anda belum ditetapkan, dan belum ada ' . implode('/', $roles)
            . ' lain yang dapat menyetujui. Minta admin menetapkan atasan langsung Anda pada menu Karyawan, lalu ajukan kembali.';
    }

    public function table(string $type): ?string
    {
        return self::TABLES[$type] ?? null;
    }

    public function find(string $type, int $id): ?array
    {
        $table = $this->table($type);
        if ($table === null) {
            return null;
        }
        return Database::one('SELECT * FROM ' . $table . ' WHERE id = ?', [$id]);
    }

    /** Tahap yang boleh diambil aktor ini; ['error' => ...] bila tidak berhak. */
    public function stage(string $type, array $request, array $actor): array
    {
        $actorId = (int) ($actor['id'] ?? 0);
        $role = (string) ($actor['role'] ?? '');
        $employeeId = (int) ($request['employee_id'] ?? 0);
        $status = (string) ($request['status'] ?? '');

        if ($actorId <= 0) {
            return ['error' => 'Sesi berakhir.'];
        }
        // Tidak seorang pun boleh memutus pengajuannya sendiri.
        if ($actorId === $employeeId) {
            return ['error' => 'Anda tidak dapat memutus pengajuan milik sendiri.'];
        }

        $managerId = self::managerOf($employeeId);

        if ($managerId !== null && $managerId === $actorId && $status === 'PENDING') {
            return ['stage' => 'MANAGER'];
        }

        if ($role === 'HR' && in_array($status, ['PENDING', 'MANAGER_APPROVED'], true)) {
            // Pengajuan rekan HR tetap harus lewat atasan langsung lebih dahulu.
            if (self::awaitingManager($request, self::FINAL_ROLES)) {
                return ['error' => 'Pengajuan masih menunggu keputusan atasan langsung.'];
            }

            return ['stage' => 'HR'];
        }

        return ['error' => 'Anda tidak berhak memproses pengajuan ini pada tahap saat ini.'];
    }

    /** Apakah aktor boleh memutus pengajuan ini sekarang? (dipakai tombol UI) */
    public static function canAct(string $type, array $request, ?array $actor): bool
    {
        if (!$actor) {
            return false;
        }

        return !isset((new self())->stage($type, $request, $actor)['error']);
    }

    /**
     * Proses keputusan.
     *
     * @return array{ok:bool,error?:string,status?:string,stage?:string,request?:array,message?:string}
     */
    public function decide(string $type, array $request, string $decision, string $note, array $actor): array
    {
        $table = $this->table($type);
        if ($table === null) {
            return ['ok' => false, 'error' => 'Jenis pengajuan tidak dikenal.'];
        }

        $decision = strtoupper($decision) === 'REJECTED' ? 'REJECTED' : 'APPROVED';
        $approved = $decision === 'APPROVED';

        if (!in_array($request['status'], ['PENDING', 'MANAGER_APPROVED'], true)) {
            return ['ok' => false, 'error' => 'Pengajuan sudah diproses sebelumnya (' . (self::STATUS_LABELS[$request['status']] ?? $request['status']) . ').'];
        }

        $stageInfo = $this->stage($type, $request, $actor);
        if (isset($stageInfo['error'])) {
            return ['ok' => false, 'error' => $stageInfo['error']];
        }
        $stage = (string) $stageInfo['stage'];
        // Bila pemohon satu-satunya approver final, tahap atasan adalah final.
        $finalByManager = $stage === 'MANAGER'
            && self::managerStageIsFinal((int) $request['employee_id'], self::FINAL_ROLES);
        $isFinalStage = $stage === 'HR' || $finalByManager;

        $note = mb_substr(trim($note), 0, 255);
        $now = date('Y-m-d H:i:s');
        $actorId = (int) $actor['id'];

        // Efek samping final dijalankan sebelum baris diperbarui (butuh status lama).
        if ($type === 'leave') {
            $leave = new LeaveModel();
            if ($isFinalStage && $approved) {
                $leave->applyApproval((int) $request['id']);
            } elseif ($isFinalStage && $request['status'] === 'APPROVED') {
                $leave->revertApproval((int) $request['id']);
            }
        }

        if ($type === 'schedule' && $isFinalStage && $approved) {
            (new ScheduleModel())->assign(
                (int) $request['employee_id'],
                (int) $request['requested_shift_id'],
                (string) $request['start_date'],
                (string) $request['end_date'],
                'REQUEST',
                $note !== '' ? $note : 'Disetujui ' . ($finalByManager ? 'atasan langsung' : 'HR'),
                $actorId
            );
        }

        if ($stage === 'MANAGER') {
            $status = $approved ? ($finalByManager ? 'APPROVED' : 'MANAGER_APPROVED') : 'REJECTED';
            $update = [
                'status'              => $status,
                'manager_id'          => $actorId,
                'manager_approved_at' => $now,
                'manager_note'        => $note !== '' ? $note : null,
                'updated_at'          => $now,
            ];
        } else {
            $status = $approved ? 'APPROVED' : 'REJECTED';
            $update = [
                'status'         => $status,
                'hr_id'          => $actorId,
                'hr_approved_at' => $now,
                'hr_note'        => $note !== '' ? $note : null,
                'updated_at'     => $now,
            ];
        }

        Database::update($table, $update, 'id = ?', [(int) $request['id']]);

        if ($type === 'overtime' && $status === 'APPROVED') {
            $hours = (float) ($request['actual_hours'] ?: $request['planned_hours']);
            $compensation = (new OvertimeModel())->compensation(
                (int) $request['employee_id'],
                $hours,
                (string) ($request['work_date'] ?? '')
            )['amount'];

            Database::update('overtime_requests', ['compensation_amount' => $compensation], 'id = ?', [(int) $request['id']]);
        }

        $message = $this->notify((int) $request['employee_id'], $status, $type, (int) $request['id'], $finalByManager);
        Audit::log('approval.' . $stage, $table, (int) $request['id'], [
            'status'    => $status,
            'channel'   => (string) ($actor['channel'] ?? 'web'),
            'final_by'  => $finalByManager ? 'MANAGER' : $stage,
        ]);

        return [
            'ok'           => true,
            'status'       => $status,
            'stage'        => $stage,
            'final'        => $isFinalStage,
            'request'      => $request,
            'message'      => $message,
        ];
    }

    /**
     * Daftar pengajuan yang menunggu keputusan aktor pada satu jenis.
     * Atasan melihat tahap PENDING milik timnya; HR melihat PENDING & MANAGER_APPROVED.
     */
    public function pendingFor(string $type, array $actor, int $limit = 50, int $offset = 0): array
    {
        $table = $this->table($type);
        if ($table === null) {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $params = [];
        $where = $this->inboxWhere($actor, $params);

        $extra = '';
        if ($type === 'leave') {
            $extra = ', lt.name AS leave_type_name, r.start_date, r.end_date, r.days, r.reason';
            $join = 'LEFT JOIN leave_types lt ON lt.id = r.leave_type_id';
        } elseif ($type === 'overtime') {
            $extra = ', r.work_date, r.planned_start, r.planned_end, r.planned_hours, r.reason';
            $join = '';
        } else {
            $extra = ', r.start_date, r.end_date, r.reason, s.code AS shift_code, s.name AS shift_name,
                      r.requested_shift_id, r.current_shift_id,
                      cs.code AS current_shift_code, cs.name AS current_shift_name';
            $join = 'LEFT JOIN work_shifts s ON s.id = r.requested_shift_id
                     LEFT JOIN work_shifts cs ON cs.id = r.current_shift_id';
        }

        $sql = 'SELECT r.*, e.full_name, e.employee_code, e.photo, d.name AS department_name,
                       p.name AS position_name' . $extra . '
                  FROM ' . $table . ' r
                  JOIN employees e ON e.id = r.employee_id
                  LEFT JOIN departments d ON d.id = e.department_id
                  LEFT JOIN positions p ON p.id = e.position_id
                  ' . $join . '
                 WHERE ' . $where . '
                 ORDER BY r.created_at DESC, r.id DESC
                 LIMIT ' . $limit . ' OFFSET ' . $offset;

        return Database::all($sql, $params);
    }

    public function countPending(string $type, array $actor): int
    {
        $table = $this->table($type);
        if ($table === null) {
            return 0;
        }

        $params = [];
        $where = $this->inboxWhere($actor, $params);

        return (int) Database::value(
            'SELECT COUNT(*) FROM ' . $table . ' r
               JOIN employees e ON e.id = r.employee_id
              WHERE ' . $where,
            $params,
            0
        );
    }

    /** Ringkasan jumlah pengajuan menunggu per jenis + klaim. */
    public function inboxCounts(array $actor): array
    {
        $counts = [];
        foreach (array_keys(self::TABLES) as $type) {
            $counts[$type] = $this->countPending($type, $actor);
        }
        $expenses = new \App\Models\ExpenseModel();
        $counts['claim'] = in_array((string) ($actor['role'] ?? ''), ['HR', 'FIN'], true)
            ? $expenses->countClaims([
                'status'           => 'AWAITING',
                'final_ready'      => true,
                'exclude_employee' => (int) ($actor['id'] ?? 0),
            ])
            : count($expenses->pendingForManager((int) $actor['id']));

        return $counts;
    }

    /**
     * Klausa WHERE kotak masuk persetujuan.
     *
     * HR melihat PENDING & MANAGER_APPROVED, kecuali pengajuan rekan HR yang
     * masih menunggu atasan langsung (bukan kewenangan HR) dan pengajuan sendiri.
     *
     * @param array<int, mixed> $params
     */
    private function inboxWhere(array $actor, array &$params): string
    {
        $where = [];
        $actorId = (int) ($actor['id'] ?? 0);

        if ((string) ($actor['role'] ?? '') === 'HR') {
            $where[] = "r.status IN ('PENDING','MANAGER_APPROVED')";
            $where[] = "NOT (r.status = 'PENDING' AND e.role = 'HR' AND EXISTS (
                            SELECT 1 FROM employees m
                             WHERE m.id = e.direct_manager_id AND m.flag = 'ACTIVE' AND m.id <> e.id))";
        } else {
            $where[] = "r.status = 'PENDING'";
            $where[] = 'e.direct_manager_id = ?';
            $params[] = $actorId;
        }

        $where[] = 'r.employee_id <> ?';
        $params[] = $actorId;

        return implode(' AND ', $where);
    }

    private function notify(int $employeeId, string $status, string $type, int $id, bool $finalByManager = false): string
    {
        $verb = match ($status) {
            'APPROVED'         => $finalByManager
                ? 'telah disetujui final oleh atasan langsung'
                : 'telah disetujui final oleh HR',
            'MANAGER_APPROVED' => 'telah disetujui atasan langsung dan menunggu HR',
            default            => 'ditolak',
        };

        $message = (self::LABELS[$type] ?? 'Pengajuan') . ' ' . $verb . '.';

        try {
            Database::insert('notifications', [
                'employee_id' => $employeeId,
                'title'       => $message,
                'message'     => 'Diperbarui pada ' . date('d M Y H:i') . ' WIB.',
                'link'        => $type === 'leave' ? '/me/leave' : ($type === 'overtime' ? '/me/overtime' : '/me/schedule'),
                'type'        => $status === 'REJECTED' ? 'warning' : 'info',
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // notifikasi bersifat best-effort
        }

        return $message;
    }
}
