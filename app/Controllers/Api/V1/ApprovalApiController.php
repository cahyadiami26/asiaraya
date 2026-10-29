<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Core\ApiRequest;
use App\Core\ApprovalEngine;
use App\Core\Audit;
use App\Core\Validator;
use App\Models\ExpenseModel;

/**
 * Kotak masuk persetujuan untuk atasan langsung dan HR.
 *
 * GET  /api/v1/approvals?type=leave|overtime|schedule|claim&page=
 * GET  /api/v1/approvals/summary
 * POST /api/v1/approvals/{type}/{id}/decide
 *
 * Aturan tahap (atasan → HR) dijalankan App\Core\ApprovalEngine, sama dengan
 * halaman web; klaim memakai ExpenseModel::decide().
 */
final class ApprovalApiController extends ApiController
{
    use ApiPresenter;

    private ApprovalEngine $engine;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->engine = new ApprovalEngine();
    }

    public function index(): void
    {
        $type = strtolower((string) ApiRequest::query('type', 'all'));
        $page = ApiRequest::page();
        $perPage = ApiRequest::perPage(20, 100);
        $offset = ($page - 1) * $perPage;

        if (!in_array($type, ['all', 'leave', 'overtime', 'schedule', 'claim'], true)) {
            $this->fail(422, 'Tipe persetujuan tidak dikenal.', ['type' => 'Gunakan leave/overtime/schedule/claim.'], 'VALIDATION_ERROR');
        }

        $this->assertCanApprove();

        $types = $type === 'all' ? ['leave', 'overtime', 'schedule', 'claim'] : [$type];
        $items = [];
        $total = 0;

        foreach ($types as $current) {
            if ($current === 'claim') {
                $rows = $this->claimInbox($perPage, $offset);
                $count = $this->claimCount();
                foreach ($rows as $row) {
                    $items[] = $this->claimApprovalItem($row);
                }
            } else {
                $rows = $this->engine->pendingFor($current, $this->user, $perPage, $offset);
                $count = $this->engine->countPending($current, $this->user);
                foreach ($rows as $row) {
                    $items[] = $this->approvalItem($current, $row);
                }
            }
            $total += $count;
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));

        $this->paginated($items, $total, $page, $perPage, [
            'counts' => $this->engine->inboxCounts($this->user),
        ]);
    }

    public function summary(): void
    {
        $this->assertCanApprove();

        $this->ok([
            'counts' => $this->engine->inboxCounts($this->user),
            'total'  => array_sum($this->engine->inboxCounts($this->user)),
        ]);
    }

    public function decide(string $type, string $id): void
    {
        $this->assertCanApprove();
        $data = $this->body();

        $this->validate($data, static function (Validator $v) use ($data): void {
            $v->required('decision', 'Keputusan')
              ->in('decision', 'Keputusan', ['APPROVED', 'REJECTED', 'approve', 'reject'])
              ->custom(mb_strlen((string) ($data['note'] ?? '')) > 255, 'note', 'Catatan maksimal 255 karakter.');
        });

        $decision = strtoupper((string) $data['decision']) === 'REJECTED' ? 'REJECTED' : 'APPROVED';
        $note = (string) ($data['note'] ?? '');
        $type = strtolower($type);

        if ($type === 'claim') {
            $result = (new ExpenseModel())->decide((int) $id, $decision, [
                'id'   => (int) $this->user['id'],
                'role' => (string) $this->user['role'],
                'note' => $note,
            ]);

            if ($result['error'] !== null) {
                $this->fail(422, (string) $result['error'], [], 'DECISION_REJECTED');
            }

            Audit::log('api.approval.claim', 'expense_claim', (int) $id, ['status' => $result['status']]);

            $this->ok([
                'type'   => 'claim',
                'status' => $result['status'],
                'stage'  => $result['stage'],
                'message' => $decision === 'APPROVED'
                    ? 'Klaim disetujui' . ($result['stage'] === 'MANAGER' ? ' dan diteruskan ke HR/Finance.' : '.')
                    : 'Klaim ditolak.',
            ]);
        }

        if (!array_key_exists($type, ApprovalEngine::TABLES)) {
            $this->fail(422, 'Tipe persetujuan tidak dikenal.', ['type' => 'Gunakan leave/overtime/schedule/claim.'], 'VALIDATION_ERROR');
        }

        $request = $this->engine->find($type, (int) $id);
        if (!$request) {
            $this->fail(404, 'Pengajuan tidak ditemukan.', [], 'NOT_FOUND');
        }

        $result = $this->engine->decide($type, $request, $decision, $note, $this->user);
        if (!$result['ok']) {
            $this->fail(422, (string) $result['error'], [], 'DECISION_REJECTED');
        }

        $this->ok([
            'type'    => $type,
            'status'  => $result['status'],
            'stage'   => $result['stage'],
            'message' => $result['message'],
        ]);
    }

    /* ------------------------------------------------------------- internal */

    /** Hanya atasan langsung (punya bawahan) dan HR yang boleh membuka inbox. */
    private function assertCanApprove(): void
    {
        if (in_array((string) $this->user['role'], ['HR', 'FIN'], true)) {
            return;
        }

        $hasTeam = (int) \App\Core\Database::value(
            'SELECT COUNT(*) FROM employees WHERE direct_manager_id = ? AND flag = ?',
            [(int) $this->user['id'], 'ACTIVE'],
            0
        ) > 0;

        if (!$hasTeam) {
            $this->fail(403, 'Anda tidak memiliki tim yang perlu disetujui.', [], 'FORBIDDEN');
        }
    }

    private function approvalItem(string $type, array $row): array
    {
        $base = [
            'type'          => $type,
            'type_label'    => ApprovalEngine::LABELS[$type],
            'id'            => (int) $row['id'],
            'employee'      => [
                'id'            => (int) $row['employee_id'],
                'full_name'     => $row['full_name'] ?? null,
                'employee_code' => $row['employee_code'] ?? null,
                'department'    => $row['department_name'] ?? null,
                'position'      => $row['position_name'] ?? null,
                'photo_url'     => $this->mediaUrl($row['photo'] ?? null),
            ],
            'status'        => (string) $row['status'],
            'status_label'  => $this->statusLabel((string) $row['status']),
            'reason'        => $row['reason'] ?? null,
            'created_at'    => $this->isoDateTime($row['created_at'] ?? null),
            'decide_url'    => url('api/v1/approvals/' . $type . '/' . (int) $row['id'] . '/decide'),
        ];

        if ($type === 'leave') {
            $base['detail'] = [
                'leave_type' => $row['leave_type_name'] ?? null,
                'start_date' => $this->isoDate($row['start_date'] ?? null),
                'end_date'   => $this->isoDate($row['end_date'] ?? null),
                'days'       => (float) ($row['days'] ?? 0),
            ];
        } elseif ($type === 'overtime') {
            $base['detail'] = [
                'work_date'     => $this->isoDate($row['work_date'] ?? null),
                'planned_start' => $this->hhmm($row['planned_start'] ?? null),
                'planned_end'   => $this->hhmm($row['planned_end'] ?? null),
                'hours'         => (float) ($row['planned_hours'] ?? 0),
            ];
        } else {
            $base['detail'] = [
                'start_date'      => $this->isoDate($row['start_date'] ?? null),
                'end_date'        => $this->isoDate($row['end_date'] ?? null),
                'current_shift'   => $row['current_shift_name'] ?? null,
                'requested_shift' => $row['shift_name'] ?? null,
            ];
        }

        return $base;
    }

    private function claimApprovalItem(array $row): array
    {
        return [
            'type'         => 'claim',
            'type_label'   => 'Klaim Biaya',
            'id'           => (int) $row['id'],
            'employee'     => [
                'id'            => (int) $row['employee_id'],
                'full_name'     => $row['full_name'] ?? null,
                'employee_code' => $row['employee_code'] ?? null,
                'department'    => $row['department_name'] ?? null,
                'position'      => null,
                'photo_url'     => $this->mediaUrl($row['photo'] ?? null),
            ],
            'status'       => (string) $row['status'],
            'status_label' => $this->claimStatusLabel((string) $row['status']),
            'reason'       => $row['description'] ?? null,
            'created_at'   => $this->isoDateTime($row['created_at'] ?? null),
            'decide_url'   => url('api/v1/approvals/claim/' . (int) $row['id'] . '/decide'),
            'detail'       => [
                'claim_number' => $row['claim_number'] ?? null,
                'category'     => $row['category_name'] ?? null,
                'claim_date'   => $this->isoDate($row['claim_date'] ?? null),
                'amount'       => $this->money($row['amount'] ?? 0),
                'receipt_url'  => $this->mediaUrl($row['receipt'] ?? null),
            ],
        ];
    }

    private function claimInbox(int $limit, int $offset): array
    {
        return (new ExpenseModel())->claims(
            $this->user['role'] === 'HR' || $this->user['role'] === 'FIN'
                ? ['status' => 'AWAITING']
                : ['manager' => (int) $this->user['id']],
            $limit,
            $offset
        );
    }

    private function claimCount(): int
    {
        return (new ExpenseModel())->countClaims(
            $this->user['role'] === 'HR' || $this->user['role'] === 'FIN'
                ? ['status' => 'AWAITING']
                : ['manager' => (int) $this->user['id']]
        );
    }
}
