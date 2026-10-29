<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Core\ApiRequest;
use App\Core\Audit;
use App\Core\Validator;
use App\Models\ExpenseModel;

/**
 * Reimbursement / klaim biaya.
 *
 * GET  /api/v1/claim-categories
 * GET  /api/v1/claims?status=
 * GET  /api/v1/claims/{id}
 * POST /api/v1/claims
 * POST /api/v1/claims/{id}/cancel
 */
final class ClaimApiController extends ApiController
{
    use ApiPresenter;

    private ExpenseModel $expenses;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->expenses = new ExpenseModel();
    }

    public function categories(): void
    {
        $categories = array_map(static fn (array $row): array => [
            'id'               => (int) $row['id'],
            'code'             => (string) $row['code'],
            'name'             => (string) $row['name'],
            'description'      => $row['description'] ?? null,
            'max_amount'       => $row['max_amount'] !== null ? (float) $row['max_amount'] : null,
            'requires_receipt' => (int) $row['requires_receipt'] === 1,
            'color'            => $row['color'] ?? null,
        ], $this->expenses->categories(true));

        $this->ok($categories, [
            'max_age_days' => (int) setting('claim_max_age_days', 90),
        ]);
    }

    public function index(): void
    {
        $employeeId = (int) $this->user['id'];
        $status = strtoupper((string) ApiRequest::query('status', ''));
        $limit = ApiRequest::perPage(30, 100);

        $rows = $this->expenses->employeeClaims($employeeId, $limit);
        if ($status !== '' && $status !== 'ALL') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => (string) $row['status'] === $status
            ));
        }

        $this->ok(
            array_map(fn (array $row): array => $this->claimPayload($row), $rows),
            ['stats' => $this->expenses->employeeStats($employeeId)]
        );
    }

    public function show(string $id): void
    {
        $employeeId = (int) $this->user['id'];
        $claim = $this->expenses->find((int) $id);

        if (!$claim || (int) $claim['employee_id'] !== $employeeId) {
            $this->fail(404, 'Klaim tidak ditemukan.', [], 'NOT_FOUND');
        }

        $this->ok($this->claimPayload($claim));
    }

    public function store(): void
    {
        $user = $this->requireAuth();
        $data = $this->body();

        $this->validate($data, static function (Validator $v): void {
            $v->required('category_id', 'Kategori klaim')
              ->required('claim_date', 'Tanggal klaim')
              ->date('claim_date', 'Tanggal klaim')
              ->required('amount', 'Nominal')
              ->numeric('amount', 'Nominal', 1)
              ->required('description', 'Keterangan')
              ->custom(mb_strlen((string) ($data['description'] ?? '')) > 255, 'description', 'Keterangan maksimal 255 karakter.');
        });

        $receipt = $this->attachment('receipt', 'claims', 'document');

        $result = $this->expenses->submit([
            'category_id' => (int) $data['category_id'],
            'claim_date'  => (string) $data['claim_date'],
            'amount'      => (string) $data['amount'],
            'description' => (string) $data['description'],
            'receipt'     => $receipt,
        ], $user);

        if ($result['error'] !== null) {
            $this->fail(422, (string) $result['error'], [], 'CLAIM_REJECTED');
        }

        Audit::log('api.claim.submit', 'expense_claim', (int) $result['id'], [
            'amount' => (float) $data['amount'],
            'status' => $result['status'],
        ]);

        $claim = $this->expenses->find((int) $result['id']);

        $this->created([
            'claim'   => $claim ? $this->claimPayload($claim) : null,
            'message' => (int) $result['manager'] > 0
                ? 'Klaim dikirim ke atasan langsung untuk persetujuan.'
                : 'Klaim dikirim ke HR/Finance untuk persetujuan.',
        ]);
    }

    public function cancel(string $id): void
    {
        $employeeId = (int) $this->user['id'];

        $result = $this->expenses->cancel((int) $id, $employeeId);
        if ($result['error'] !== null) {
            $this->fail(422, (string) $result['error'], [], 'CANCEL_REJECTED');
        }

        Audit::log('api.claim.cancel', 'expense_claim', (int) $id);

        $this->ok(['message' => 'Klaim dibatalkan.']);
    }
}
