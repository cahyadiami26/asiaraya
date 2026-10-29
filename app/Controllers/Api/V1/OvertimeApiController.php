<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Core\ApiRequest;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Validator;
use App\Models\OvertimeModel;

/**
 * Lembur: kebijakan, riwayat, pengajuan, dan pembatalan.
 *
 * GET  /api/v1/overtimes?month=YYYY-MM&status=
 * GET  /api/v1/overtimes/policy
 * POST /api/v1/overtimes
 * POST /api/v1/overtimes/{id}/cancel
 */
final class OvertimeApiController extends ApiController
{
    use ApiPresenter;

    private OvertimeModel $overtime;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->overtime = new OvertimeModel();
    }

    public function index(): void
    {
        $employeeId = (int) $this->user['id'];
        $month = (string) ApiRequest::query('month', '');
        $status = strtoupper((string) ApiRequest::query('status', ''));
        $limit = ApiRequest::perPage(30, 100);

        $rows = $this->overtime->mine($employeeId, max($limit, 60));

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => str_starts_with((string) $row['work_date'], $month)
            ));
        }
        if ($status !== '' && $status !== 'ALL') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => $status === 'OPEN'
                    ? in_array((string) $row['status'], ['PENDING', 'MANAGER_APPROVED'], true)
                    : (string) $row['status'] === $status
            ));
        }

        $summary = $this->overtime->mySummary($employeeId, $month !== '' ? $month : date('Y-m'));
        $policy = $this->overtime->settings();

        $this->ok(
            array_map(fn (array $row): array => $this->overtimePayload($row), array_slice($rows, 0, $limit)),
            [
                'summary' => $summary,
                'policy'  => [
                    'enabled'     => (bool) $policy['enabled'],
                    'min_minutes' => (int) $policy['min_minutes'],
                    'max_hours'   => (float) $policy['max_hours'],
                ],
            ]
        );
    }

    public function policy(): void
    {
        $employeeId = (int) $this->user['id'];
        $salary = (float) Database::value('SELECT basic_salary FROM employees WHERE id = ?', [$employeeId], 0);

        $this->ok([
            'policy'  => $this->overtime->settings(),
            'preview' => $this->overtime->policyPreview($salary),
            'rate'    => $this->overtime->compute($salary, 1.0),
        ]);
    }

    public function store(): void
    {
        $employeeId = (int) $this->user['id'];
        $data = $this->body();

        $this->validate($data, static function (Validator $v): void {
            $v->required('work_date', 'Tanggal lembur')
              ->required('planned_start', 'Jam mulai')
              ->required('planned_end', 'Jam selesai')
              ->date('work_date', 'Tanggal lembur')
              ->custom(mb_strlen((string) ($data['reason'] ?? '')) > 255, 'reason', 'Alasan lembur maksimal 255 karakter.');
        });

        $result = $this->overtime->create([
            'employee_id'   => $employeeId,
            'work_date'     => (string) $data['work_date'],
            'planned_start' => (string) $data['planned_start'],
            'planned_end'   => (string) $data['planned_end'],
            'reason'        => (string) ($data['reason'] ?? ''),
            'type'          => 'REQUEST',
        ], $employeeId);

        if ($result['error'] !== null) {
            $this->fail(422, (string) $result['error'], [], 'OVERTIME_REJECTED');
        }

        Audit::log('api.overtime.request', 'overtime_request', (int) $result['id'], [
            'date' => (string) $data['work_date'],
        ]);

        $row = $this->overtime->find((int) $result['id']);

        $this->created([
            'overtime' => $row ? $this->overtimePayload($row) : null,
            'message'  => 'Pengajuan lembur dikirim ke atasan langsung.',
        ]);
    }

    public function cancel(string $id): void
    {
        $employeeId = (int) $this->user['id'];
        $row = $this->overtime->find((int) $id);

        if (!$row || (int) $row['employee_id'] !== $employeeId) {
            $this->fail(404, 'Pengajuan lembur tidak ditemukan.', [], 'NOT_FOUND');
        }
        if (!in_array((string) $row['status'], ['PENDING', 'MANAGER_APPROVED'], true)) {
            $this->fail(409, 'Lembur yang sudah ' . strtolower($this->statusLabel((string) $row['status'])) . ' tidak dapat dibatalkan dari aplikasi.', [], 'INVALID_STATE');
        }

        $note = trim((string) ApiRequest::input('note', ''));
        $result = $this->overtime->cancel(
            (int) $row['id'],
            $note !== '' ? $note : 'Dibatalkan karyawan via aplikasi Android',
            'Karyawan (Android)'
        );

        if ($result['error'] !== null) {
            $this->fail(422, (string) $result['error'], [], 'CANCEL_REJECTED');
        }

        Audit::log('api.overtime.cancel', 'overtime_request', (int) $row['id']);

        $this->ok(['message' => 'Pengajuan lembur dibatalkan.']);
    }
}
