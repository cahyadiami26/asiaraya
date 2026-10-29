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

        if ($actorId <= 0) {
            return ['error' => 'Sesi berakhir.'];
        }
        if ($actorId === (int) $request['employee_id']) {
            return ['error' => 'Anda tidak dapat memutus pengajuan milik sendiri.'];
        }

        $managerId = Database::value('SELECT direct_manager_id FROM employees WHERE id = ?', [(int) $request['employee_id']]);
        $isManager = $managerId !== null && (int) $managerId === $actorId;

        if ($isManager && $request['status'] === 'PENDING') {
            return ['stage' => 'MANAGER'];
        }
        if ($role === 'HR' && in_array($request['status'], ['PENDING', 'MANAGER_APPROVED'], true)) {
            return ['stage' => 'HR'];
        }

        return ['error' => 'Anda tidak berhak memproses pengajuan ini pada tahap saat ini.'];
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

        $note = mb_substr(trim($note), 0, 255);
        $now = date('Y-m-d H:i:s');
        $actorId = (int) $actor['id'];

        // Efek samping final dijalankan sebelum baris diperbarui (butuh status lama).
        if ($type === 'leave') {
            $leave = new LeaveModel();
            if ($stage === 'HR' && $approved) {
                $leave->applyApproval((int) $request['id']);
            } elseif ($stage === 'HR' && $request['status'] === 'APPROVED') {
                $leave->revertApproval((int) $request['id']);
            }
        }

        if ($type === 'schedule' && $stage === 'HR' && $approved) {
            (new ScheduleModel())->assign(
                (int) $request['employee_id'],
                (int) $request['requested_shift_id'],
                (string) $request['start_date'],
                (string) $request['end_date'],
                'REQUEST',
                $note !== '' ? $note : 'Disetujui HR',
                $actorId
            );
        }

        if ($stage === 'MANAGER') {
            $status = $approved ? 'MANAGER_APPROVED' : 'REJECTED';
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

        $message = $this->notify((int) $request['employee_id'], $status, $type, (int) $request['id']);
        Audit::log('approval.' . $stage, $table, (int) $request['id'], ['status' => $status, 'channel' => (string) ($actor['channel'] ?? 'web')]);

        return [
            'ok'      => true,
            'status'  => $status,
            'stage'   => $stage,
            'request' => $request,
            'message' => $message,
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
        $where = [];

        if ((string) ($actor['role'] ?? '') === 'HR') {
            $where[] = "r.status IN ('PENDING','MANAGER_APPROVED')";
        } else {
            $where[] = "r.status = 'PENDING'";
            $where[] = 'e.direct_manager_id = ?';
            $params[] = (int) $actor['id'];
        }

        $where[] = 'r.employee_id <> ?';
        $params[] = (int) $actor['id'];

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
                 WHERE ' . implode(' AND ', $where) . '
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
        $where = [];

        if ((string) ($actor['role'] ?? '') === 'HR') {
            $where[] = "r.status IN ('PENDING','MANAGER_APPROVED')";
        } else {
            $where[] = "r.status = 'PENDING'";
            $where[] = 'e.direct_manager_id = ?';
            $params[] = (int) $actor['id'];
        }

        $where[] = 'r.employee_id <> ?';
        $params[] = (int) $actor['id'];

        return (int) Database::value(
            'SELECT COUNT(*) FROM ' . $table . ' r
               JOIN employees e ON e.id = r.employee_id
              WHERE ' . implode(' AND ', $where),
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
            ? $expenses->countClaims(['status' => 'AWAITING'])
            : count($expenses->pendingForManager((int) $actor['id']));

        return $counts;
    }

    private function notify(int $employeeId, string $status, string $type, int $id): string
    {
        $verb = match ($status) {
            'APPROVED'         => 'telah disetujui final oleh HR',
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
