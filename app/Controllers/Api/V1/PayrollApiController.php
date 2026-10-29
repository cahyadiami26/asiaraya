<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Core\ApiRequest;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Qr;
use App\Core\View;
use App\Models\PayrollModel;

/**
 * Slip gaji karyawan.
 *
 * GET /api/v1/payslips?year=YYYY
 * GET /api/v1/payslips/{id}
 * GET /api/v1/payslips/{id}/pdf      (bearer token atau URL bertanda tangan)
 */
final class PayrollApiController extends ApiController
{
    use ApiPresenter;

    private PayrollModel $payroll;

    public function __construct()
    {
        parent::__construct();
        $this->payroll = new PayrollModel();
    }

    public function index(): void
    {
        $user = $this->requireAuth();
        $year = (int) ApiRequest::query('year', 0);

        $slips = $this->payroll->mySlips((int) $user['id']);
        if ($year >= 2000) {
            $slips = array_values(array_filter(
                $slips,
                static fn (array $row): bool => (int) $row['period_year'] === $year
            ));
        }

        $items = array_map(function (array $row): array {
            $payload = $this->payslipPayload($row);
            $payload['download_url'] = $this->signedDownload((int) $row['id'], (int) $this->user['id']);
            return $payload;
        }, $slips);

        $this->ok($items, [
            'year'  => $year > 0 ? $year : null,
            'years' => array_values(array_unique(array_map(static fn (array $row): int => (int) $row['period_year'], $slips))),
            'total_net' => array_sum(array_map(static fn (array $row): float => (float) $row['net_salary'], $slips)),
        ]);
    }

    public function show(string $id): void
    {
        $user = $this->requireAuth();
        $slip = $this->payroll->findMySlip((int) $user['id'], (int) $id);

        if (!$slip) {
            $this->fail(404, 'Slip gaji tidak ditemukan.', [], 'NOT_FOUND');
        }

        $components = $this->payroll->itemComponents((int) $id);
        $payload = $this->payslipPayload($slip, true, $components);
        $payload['download_url'] = $this->signedDownload((int) $id, (int) $user['id']);
        $payload['verify_url'] = $this->payroll->verifyUrl((int) $id);

        $this->ok($payload);
    }

    public function pdf(string $id): void
    {
        $item = $this->payroll->findItem((int) $id);
        if (!$item) {
            $this->fail(404, 'Slip gaji tidak ditemukan.', [], 'NOT_FOUND');
        }

        $ownerId = (int) $item['employee_id'];
        $actorId = $this->downloadActor('payslip', (int) $id);

        if ($actorId !== $ownerId && !Auth::hasRole(['HR', 'FIN'])) {
            $this->fail(403, 'Anda hanya dapat mengunduh slip gaji milik sendiri.', [], 'FORBIDDEN');
        }
        if (!in_array((string) $item['period_status'], ['FINANCE_APPROVED', 'PAID'], true) && $actorId === $ownerId) {
            $this->fail(409, 'Slip gaji belum final dan belum dapat diunduh.', [], 'NOT_FINAL');
        }

        $components = $this->payroll->itemComponents((int) $id);
        $verifyUrl = $this->payroll->verifyUrl((int) $id);

        $html = View::partial('payroll/slip_pdf', [
            'item'      => $item,
            'components' => $components,
            'verifyUrl' => $verifyUrl,
            'qrPath'    => Qr::png($verifyUrl, 260, 6),
            'company'   => [
                'name'    => (string) setting('company_name', 'PT Asia Raya Group'),
                'legal'   => (string) setting('company_legal_name', ''),
                'address' => (string) setting('company_address', ''),
                'phone'   => (string) setting('company_phone', ''),
                'email'   => (string) setting('company_email', ''),
                'npwp'    => (string) setting('company_npwp', ''),
            ],
            'printedAt' => date('d M Y H:i'),
        ]);

        $filename = str_replace('/', '-', (string) ($item['slip_number'] ?? 'slip')) . '.pdf';

        // Default attachment; ?download=0 atau ?inline=1 menghasilkan tampilan inline.
        $download = true;
        if (array_key_exists('download', $_GET)) {
            $raw = (string) $_GET['download'];
            $download = $raw === '' ? true : ApiRequest::boolean($raw);
        }
        if (ApiRequest::boolean(ApiRequest::query('inline', false))) {
            $download = false;
        }

        Audit::log('api.payroll.slip', 'payroll_item', (int) $id, [
            'download' => $download,
            'signed'   => \App\Core\ApiAuth::current() === null,
        ]);

        $this->pdfResponse($html, $filename, $download);
    }

    /** URL unduhan bertanda tangan (berlaku 5 menit) untuk DownloadManager Android. */
    private function signedDownload(int $itemId, int $employeeId): string
    {
        return \App\Core\ApiAuth::signedUrl(
            'api/v1/payslips/' . $itemId . '/pdf',
            'payslip',
            $itemId,
            $employeeId,
            true
        );
    }
}
