<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Core\ApiRequest;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Validator;
use App\Models\ScheduleModel;

/**
 * Jadwal kerja & pengajuan perubahan shift.
 *
 * GET  /api/v1/schedule/today
 * GET  /api/v1/schedule?from=YYYY-MM-DD&to=YYYY-MM-DD
 * GET  /api/v1/shifts
 * GET  /api/v1/schedule-requests
 * POST /api/v1/schedule-requests
 * POST /api/v1/schedule-requests/{id}/cancel
 */
final class ScheduleApiController extends ApiController
{
    use ApiPresenter;

    private ScheduleModel $schedules;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->schedules = new ScheduleModel();
    }

    public function today(): void
    {
        $employeeId = (int) $this->user['id'];
        $today = date('Y-m-d');
        $shift = $this->schedules->shiftOn($employeeId, $today);

        $this->ok([
            'date'  => $today,
            'shift' => $this->shiftPayload($shift, $today),
            'next_days' => array_map(
                fn (array $day): array => [
                    'date'    => $day['date'],
                    'weekend' => (bool) $day['weekend'],
                    'is_work' => (bool) $day['is_work'],
                    'shift'   => $this->shiftPayload($day['shift'], $day['date']),
                ],
                array_slice($this->schedules->dayGrid($employeeId, $today, date('Y-m-d', strtotime('+6 days'))), 0, 7)
            ),
        ]);
    }

    public function index(): void
    {
        $employeeId = (int) $this->user['id'];

        $from = $this->dateOrNull(ApiRequest::query('from')) ?? date('Y-m-d', strtotime('-3 days'));
        $to = $this->dateOrNull(ApiRequest::query('to')) ?? date('Y-m-d', strtotime('+25 days'));

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        if ((strtotime($to) - strtotime($from)) / 86400 > 92) {
            $to = date('Y-m-d', strtotime($from . ' +92 days'));
        }

        $days = array_map(
            fn (array $day): array => [
                'date'    => $day['date'],
                'dow'     => (int) $day['dow'],
                'weekend' => (bool) $day['weekend'],
                'is_work' => (bool) $day['is_work'],
                'shift'   => $this->shiftPayload($day['shift'], $day['date']),
            ],
            $this->schedules->dayGrid($employeeId, $from, $to)
        );

        $this->ok($days, ['range' => ['from' => $from, 'to' => $to]]);
    }

    public function shifts(): void
    {
        $shifts = array_map(static fn (array $s): array => [
            'id'             => (int) $s['id'],
            'code'           => (string) $s['code'],
            'name'           => (string) $s['name'],
            'start_time'     => substr((string) $s['check_in_time'], 0, 5),
            'end_time'       => substr((string) $s['check_out_time'], 0, 5),
            'break_minutes'  => (int) ($s['break_minutes'] ?? 0),
            'late_tolerance' => (int) ($s['late_tolerance_minutes'] ?? 0),
            'operational'    => $s['operational_type'] ?? 'NORMAL',
            'color'          => $s['color'] ?? null,
        ], $this->schedules->shiftOptions());

        $this->ok($shifts);
    }

    public function requests(): void
    {
        $employeeId = (int) $this->user['id'];
        $limit = ApiRequest::perPage(20, 50);

        $rows = $this->schedules->myRequests($employeeId, $limit);

        $this->ok(
            array_map(fn (array $row): array => $this->scheduleRequestPayload($row), $rows),
            ['has_open_request' => $this->schedules->hasOpenRequest($employeeId)]
        );
    }

    public function storeRequest(): void
    {
        $employeeId = (int) $this->user['id'];
        $data = $this->body();

        $this->validate($data, static function (Validator $v): void {
            $v->required('requested_shift_id', 'Shift pengganti')
              ->required('start_date', 'Tanggal mulai')
              ->required('end_date', 'Tanggal akhir')
              ->date('start_date', 'Tanggal mulai')
              ->date('end_date', 'Tanggal akhir')
              ->custom(mb_strlen((string) ($data['reason'] ?? '')) > 255, 'reason', 'Alasan maksimal 255 karakter.');
        });

        $shift = $this->schedules->findShift((int) $data['requested_shift_id']);
        if (!$shift || (int) $shift['is_active'] !== 1) {
            $this->fail(422, 'Shift pengganti tidak valid.', ['requested_shift_id' => 'Shift tidak aktif.'], 'VALIDATION_ERROR');
        }

        $from = (string) $data['start_date'];
        $to = (string) ($data['end_date'] ?? $from);

        if ($from > $to) {
            $this->fail(422, 'Tanggal akhir tidak boleh lebih awal dari tanggal mulai.', [], 'VALIDATION_ERROR');
        }
        if ($from < date('Y-m-d')) {
            $this->fail(422, 'Perubahan jadwal hanya dapat diajukan untuk tanggal hari ini atau setelahnya.', [], 'VALIDATION_ERROR');
        }
        if ($this->schedules->hasOpenRequest($employeeId)) {
            $this->fail(409, 'Masih ada pengajuan perubahan jadwal yang belum selesai.', [], 'OPEN_REQUEST_EXISTS');
        }

        $current = $this->schedules->shiftOn($employeeId, $from);
        if ($current && (int) $current['shift_id'] === (int) $shift['id']) {
            $this->fail(422, 'Shift yang diajukan sama dengan jadwal Anda saat ini.', [], 'SAME_SHIFT');
        }

        $id = $this->schedules->createRequest(
            $employeeId,
            (int) $shift['id'],
            $from,
            $to,
            (string) ($data['reason'] ?? '')
        );

        Audit::log('api.schedule.request', 'shift_change_request', $id, [
            'shift' => $shift['code'],
            'from'  => $from,
            'to'    => $to,
        ]);

        $row = Database::one(
            "SELECT r.*, cs.name AS current_shift_name, rs.name AS requested_shift_name,
                    rs.code AS shift_code, rs.check_in_time AS requested_start, rs.check_out_time AS requested_end
               FROM shift_change_requests r
               LEFT JOIN work_shifts cs ON cs.id = r.current_shift_id
               LEFT JOIN work_shifts rs ON rs.id = r.requested_shift_id
              WHERE r.id = ?",
            [$id]
        );

        $this->created([
            'request' => $this->scheduleRequestPayload($row ?? []),
            'message' => 'Pengajuan perubahan jadwal dikirim ke atasan langsung.',
        ]);
    }

    public function cancelRequest(string $id): void
    {
        $employeeId = (int) $this->user['id'];
        $row = Database::one('SELECT * FROM shift_change_requests WHERE id = ?', [(int) $id]);

        if (!$row || (int) $row['employee_id'] !== $employeeId) {
            $this->fail(404, 'Pengajuan perubahan jadwal tidak ditemukan.', [], 'NOT_FOUND');
        }
        if (!in_array((string) $row['status'], ['PENDING'], true)) {
            $this->fail(409, 'Pengajuan pada status "' . $this->statusLabel((string) $row['status']) . '" tidak dapat dibatalkan.', [], 'INVALID_STATE');
        }

        Database::update('shift_change_requests', [
            'status'     => 'CANCELLED',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int) $row['id']]);

        Audit::log('api.schedule.cancel', 'shift_change_request', (int) $row['id']);

        $this->ok(['message' => 'Pengajuan perubahan jadwal dibatalkan.']);
    }
}
