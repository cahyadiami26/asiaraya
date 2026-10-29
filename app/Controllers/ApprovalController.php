<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\ScheduleModel;

/**
 * Two-stage approval engine shared by overtime, leave and shift-change.
 *
 * Stage 1 — direct manager  : PENDING → MANAGER_APPROVED
 * Stage 2 — HR              : MANAGER_APPROVED → APPROVED (final)
 * A rejection at either stage terminates the request as REJECTED.
 */
final class ApprovalController extends Controller
{
    public function decideOvertime(string $id): void
    {
        $request = Database::one('SELECT * FROM overtime_requests WHERE id = ?', [(int) $id]);
        if (!$request) {
            $this->abort(404, 'Pengajuan lembur tidak ditemukan.');
        }

        $decision = strtoupper((string) input('decision', 'APPROVED'));
        $stage = $this->resolveStage($request, (int) $request['employee_id']);
        $this->applyDecision('overtime_requests', $request, $decision, $stage, '/overtime');
    }

    public function decideLeave(string $id): void
    {
        $request = Database::one('SELECT * FROM leave_requests WHERE id = ?', [(int) $id]);
        if (!$request) {
            $this->abort(404, 'Pengajuan cuti tidak ditemukan.');
        }

        $decision = strtoupper((string) input('decision', 'APPROVED'));
        $stage = $this->resolveStage($request, (int) $request['employee_id']);

        if ($decision === 'APPROVED' && $stage === 'HR') {
            // Final approval consumes the leave balance.
            $balance = Database::one(
                'SELECT * FROM leave_balances WHERE employee_id = ? AND leave_type_id = ? AND period_year = ?',
                [(int) $request['employee_id'], (int) $request['leave_type_id'], (int) date('Y', strtotime($request['start_date']))]
            );
            if ($balance) {
                Database::update('leave_balances', [
                    'used' => (float) $balance['used'] + (float) $request['days'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [(int) $balance['id']]);
            } else {
                Database::insert('leave_balances', [
                    'employee_id'   => (int) $request['employee_id'],
                    'leave_type_id' => (int) $request['leave_type_id'],
                    'period_year'   => (int) date('Y', strtotime($request['start_date'])),
                    'quota'         => 0,
                    'used'          => (float) $request['days'],
                    'carry'         => 0,
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $this->applyDecision('leave_requests', $request, $decision, $stage, '/leave');
    }

    public function decideShiftChange(string $id): void
    {
        $request = Database::one('SELECT * FROM shift_change_requests WHERE id = ?', [(int) $id]);
        if (!$request) {
            $this->abort(404, 'Pengajuan perubahan jadwal tidak ditemukan.');
        }

        $decision = strtoupper((string) input('decision', 'APPROVED'));
        $stage = $this->resolveStage($request, (int) $request['employee_id']);

        if ($decision === 'APPROVED' && $stage === 'HR') {
            // HR approval rewrites only the requested window; the rest of the
            // existing rotation is preserved (schedules are split as needed).
            (new ScheduleModel())->assign(
                (int) $request['employee_id'],
                (int) $request['requested_shift_id'],
                (string) $request['start_date'],
                (string) $request['end_date'],
                'REQUEST',
                substr((string) input('note', ''), 0, 255) ?: 'Disetujui HR',
                Auth::id()
            );
        }

        $this->applyDecision('shift_change_requests', $request, $decision, $stage, '/schedules/requests');
    }

    /** Decide who is acting: direct manager or HR. */
    private function resolveStage(array $request, int $employeeId): string
    {
        $user = Auth::user();
        if (!$user) {
            $this->abort(401, 'Sesi berakhir.');
        }

        if (Auth::isManagerOf($employeeId) && $request['status'] === 'PENDING') {
            return 'MANAGER';
        }
        if ($user['role'] === 'HR' && in_array($request['status'], ['PENDING', 'MANAGER_APPROVED'], true)) {
            return 'HR';
        }
        $this->abort(403, 'Anda tidak berhak memproses pengajuan ini pada tahap saat ini.');
    }

    private function applyDecision(string $table, array $request, string $decision, string $stage, string $fallback): void
    {
        $approved = $decision === 'APPROVED';
        $now = date('Y-m-d H:i:s');
        $note = substr((string) input('note', ''), 0, 255);

        if ($stage === 'MANAGER') {
            $status = $approved ? 'MANAGER_APPROVED' : 'REJECTED';
            $update = [
                'status'              => $status,
                'manager_id'          => Auth::id(),
                'manager_approved_at' => $now,
                'manager_note'        => $note ?: null,
                'updated_at'          => $now,
            ];
        } else {
            $status = $approved ? 'APPROVED' : 'REJECTED';
            $update = [
                'status'         => $status,
                'hr_id'          => Auth::id(),
                'hr_approved_at' => $now,
                'hr_note'        => $note ?: null,
                'updated_at'     => $now,
            ];
        }

        Database::update($table, $update, 'id = ?', [(int) $request['id']]);

        if ($table === 'overtime_requests' && $status === 'APPROVED') {
            $hours = (float) ($request['actual_hours'] ?: $request['planned_hours']);
            $compensation = $this->overtimeCompensation((int) $request['employee_id'], $hours, (string) ($request['work_date'] ?? null));
            Database::update('overtime_requests', ['compensation_amount' => $compensation], 'id = ?', [(int) $request['id']]);
        }

        $this->notify((int) $request['employee_id'], $status, $table, (int) $request['id']);
        Audit::log('approval.' . $stage, $table, (int) $request['id'], ['status' => $status]);

        $label = match (true) {
            $status === 'APPROVED' => 'disetujui (final)',
            $status === 'MANAGER_APPROVED' => 'disetujui atasan, menunggu HR',
            default => 'ditolak',
        };
        flash($status === 'REJECTED' ? 'warning' : 'success', 'Pengajuan ' . $label . '.');
        back($fallback);
    }

    /**
     * Kompensasi lembur — didelegasikan ke OvertimeModel agar rumus (multiplier,
     * tarif hari libur, batas maksimum) konsisten dengan halaman lembur.
     */
    private function overtimeCompensation(int $employeeId, float $hours, ?string $date = null): float
    {
        return (new \App\Models\OvertimeModel())->compensation($employeeId, $hours, $date)['amount'];
    }

    private function notify(int $employeeId, string $status, string $table, int $id): void
    {
        $titles = [
            'overtime_requests'      => 'Pengajuan Lembur',
            'leave_requests'         => 'Pengajuan Cuti',
            'shift_change_requests'  => 'Perubahan Jadwal',
        ];
        $verb = match ($status) {
            'APPROVED' => 'telah disetujui final oleh HR',
            'MANAGER_APPROVED' => 'telah disetujui atasan langsung dan menunggu HR',
            default => 'ditolak',
        };
        try {
            Database::insert('notifications', [
                'employee_id' => $employeeId,
                'title'       => ($titles[$table] ?? 'Pengajuan') . ' ' . $verb,
                'message'     => 'Diperbarui pada ' . date('d M Y H:i') . ' WIB.',
                'link'        => '/me/attendance',
                'type'        => $status === 'REJECTED' ? 'warning' : 'info',
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // notifications are best-effort
        }
    }
}
