<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Core\ApiRequest;
use App\Core\Audit;
use App\Core\Validator;
use App\Models\LeaveModel;

/**
 * Cuti: jenis, saldo, pengajuan, dan pembatalan.
 *
 * GET  /api/v1/leave/types
 * GET  /api/v1/leave/balances?year=YYYY
 * GET  /api/v1/leaves?status=
 * POST /api/v1/leaves
 * POST /api/v1/leaves/{id}/cancel
 */
final class LeaveApiController extends ApiController
{
    use ApiPresenter;

    private LeaveModel $leave;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->leave = new LeaveModel();
    }

    public function types(): void
    {
        $types = array_map(static fn (array $type): array => [
            'id'                  => (int) $type['id'],
            'code'                => (string) $type['code'],
            'name'                => (string) $type['name'],
            'description'         => $type['description'] ?? null,
            'quota_days'          => (float) ($type['quota_days'] ?? 0),
            'is_paid'             => (int) ($type['is_paid'] ?? 1) === 1,
            'deduct_salary'       => (int) ($type['deduct_salary'] ?? 0) === 1,
            'requires_attachment' => (int) ($type['requires_attachment'] ?? 0) === 1,
            'max_per_request'     => (int) ($type['max_per_request'] ?? 0),
            'min_notice_days'     => (int) ($type['min_notice_days'] ?? 0),
            'color'               => $type['color'] ?? null,
        ], $this->leave->types(true));

        $this->ok($types, ['policy' => $this->leave->settings()]);
    }

    public function balances(): void
    {
        $employeeId = (int) $this->user['id'];
        $year = $this->yearParam();

        $balances = array_map(static fn (array $row): array => [
            'leave_type_id' => (int) $row['id'],
            'type_name'     => (string) $row['name'],
            'type_code'     => (string) $row['code'],
            'color'         => $row['color'] ?? null,
            'year'          => $year,
            'quota'         => (float) $row['quota'],
            'carry_over'    => (float) $row['carry'],
            'used'          => (float) $row['used'],
            'remaining'     => (float) $row['remaining'],
            'unlimited'     => (bool) ($row['unlimited'] ?? false),
            'progress'      => round((float) ($row['progress'] ?? 100), 1),
        ], $this->leave->myBalances($employeeId, $year));

        $this->ok($balances, [
            'year'    => $year,
            'summary' => $this->leave->mySummary($employeeId, $year),
        ]);
    }

    public function index(): void
    {
        $employeeId = (int) $this->user['id'];
        $limit = ApiRequest::perPage(20, 100);
        $status = strtoupper((string) ApiRequest::query('status', ''));

        $rows = $this->leave->myRequests($employeeId, $limit);
        if ($status !== '' && $status !== 'ALL') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => (string) $row['status'] === $status
            ));
        }

        $this->ok(
            array_map(fn (array $row): array => $this->leavePayload($row), $rows),
            ['summary' => $this->leave->mySummary($employeeId, $this->yearParam())]
        );
    }

    public function show(string $id): void
    {
        $employeeId = (int) $this->user['id'];
        $row = $this->leave->find((int) $id);

        if (!$row || (int) $row['employee_id'] !== $employeeId) {
            $this->fail(404, 'Pengajuan cuti tidak ditemukan.', [], 'NOT_FOUND');
        }

        $this->ok($this->leavePayload($row));
    }

    public function store(): void
    {
        $employeeId = (int) $this->user['id'];
        $data = $this->body();

        $this->validate($data, static function (Validator $v): void {
            $v->required('leave_type_id', 'Jenis cuti')
              ->required('start_date', 'Tanggal mulai')
              ->date('start_date', 'Tanggal mulai')
              ->date('end_date', 'Tanggal selesai')
              ->required('reason', 'Alasan cuti')
              ->custom(mb_strlen((string) ($data['reason'] ?? '')) > 255, 'reason', 'Alasan maksimal 255 karakter.');
        });

        $attachment = $this->attachment('attachment', 'leave', 'document');

        $result = $this->leave->create([
            'leave_type_id' => (int) $data['leave_type_id'],
            'start_date'    => (string) $data['start_date'],
            'end_date'      => (string) ($data['end_date'] ?? $data['start_date']),
            'reason'        => (string) $data['reason'],
            'attachment'    => $attachment,
        ], $employeeId);

        if ($result['error'] !== null) {
            $this->fail(422, (string) $result['error'], [], 'LEAVE_REJECTED');
        }

        Audit::log('api.leave.request', 'leave_request', (int) $result['id'], [
            'days' => $result['days'],
        ]);

        $row = $this->leave->find((int) $result['id']);

        $this->created([
            'leave'    => $row ? $this->leavePayload($row) : null,
            'warnings' => $result['warnings'] ?? [],
            'message'  => 'Pengajuan cuti dikirim ke atasan langsung.',
        ]);
    }

    public function cancel(string $id): void
    {
        $employeeId = (int) $this->user['id'];
        $row = $this->leave->find((int) $id);

        if (!$row || (int) $row['employee_id'] !== $employeeId) {
            $this->fail(404, 'Pengajuan cuti tidak ditemukan.', [], 'NOT_FOUND');
        }
        if ((string) $row['status'] === 'CANCELLED') {
            $this->fail(409, 'Pengajuan cuti ini sudah dibatalkan.', [], 'INVALID_STATE');
        }
        if ((string) $row['status'] === 'REJECTED') {
            $this->fail(409, 'Pengajuan yang sudah ditolak tidak dapat dibatalkan.', [], 'INVALID_STATE');
        }

        $note = trim((string) ApiRequest::input('note', ''));
        $result = $this->leave->cancel(
            (int) $row['id'],
            'Karyawan (Aplikasi Android)',
            $note !== '' ? $note : null
        );

        if ($result['error'] !== null) {
            $this->fail(422, (string) $result['error'], [], 'CANCEL_REJECTED');
        }

        Audit::log('api.leave.cancel', 'leave_request', (int) $row['id']);

        $this->ok(['message' => 'Pengajuan cuti dibatalkan.']);
    }
}
