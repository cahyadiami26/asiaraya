<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiAuth;
use App\Core\ApiController;
use App\Core\ApiRequest;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Qr;
use App\Core\View;
use App\Models\ReportModel;
use App\Models\TaxModel;

/**
 * Bukti potong PPh 21 (1721-A1).
 *
 * GET /api/v1/tax-slips?year=YYYY
 * GET /api/v1/tax-slips/{year}
 * GET /api/v1/tax-slips/{year}/pdf     (bearer token atau URL bertanda tangan)
 */
final class TaxApiController extends ApiController
{
    use ApiPresenter;

    private TaxModel $tax;

    public function __construct()
    {
        parent::__construct();
        $this->tax = new TaxModel();
    }

    public function index(): void
    {
        $user = $this->requireAuth();
        $year = (int) ApiRequest::query('year', 0);
        $employeeId = (int) $user['id'];

        $slips = [];
        foreach ($this->tax->years() as $availableYear) {
            if ($year >= 2000 && (int) $availableYear !== $year) {
                continue;
            }
            $slip = $this->tax->slip($employeeId, (int) $availableYear);
            if ($slip) {
                $payload = $this->taxSlipPayload($slip);
                $payload['download_url'] = $this->signedDownload((int) $availableYear, $employeeId);
                $slips[] = $payload;
            }
        }

        $this->ok($slips, [
            'years' => array_map('intval', $this->tax->years()),
        ]);
    }

    public function show(string $year): void
    {
        $user = $this->requireAuth();
        $year = (int) $year;
        $employeeId = (int) $user['id'];

        $detail = $this->tax->employeeDetail($employeeId, $year);
        if (!$detail || (int) ($detail['computed']['months'] ?? 0) === 0) {
            $this->fail(404, 'Data pajak tahun ' . $year . ' belum tersedia.', [], 'NOT_FOUND');
        }

        $slip = $detail['slip'] ?? null;
        $payload = [
            'year'    => $year,
            'slip'    => $slip ? $this->taxSlipPayload($slip) : null,
            'employee'=> [
                'full_name'     => $detail['employee']['full_name'] ?? null,
                'employee_code' => $detail['employee']['employee_code'] ?? null,
                'npwp'          => $detail['employee']['npwp'] ?? null,
                'tax_status'    => $detail['employee']['tax_status'] ?? null,
                'business_unit' => $detail['employee']['unit_name'] ?? null,
            ],
            'computed' => $detail['computed'],
        ];

        if ($slip) {
            $payload['slip']['download_url'] = $this->signedDownload($year, $employeeId);
        }

        $this->ok($payload);
    }

    public function pdf(string $year): void
    {
        $year = (int) $year;

        // Signature URL terikat pada employee_id + tahun, jadi unduhan dari
        // DownloadManager Android tetap aman tanpa header Authorization.
        $actorId = $this->downloadActor('tax-slip', $year);

        $detail = $this->tax->employeeDetail($actorId, $year);
        if (!$detail || (int) ($detail['computed']['months'] ?? 0) === 0) {
            $this->fail(404, 'Data pajak tahun ' . $year . ' belum tersedia.', [], 'NOT_FOUND');
        }

        $slip = $detail['slip'];
        if (!$slip && Auth::hasRole(['HR', 'FIN'])) {
            $slipId = $this->tax->issue($detail, $year, $actorId, 'Diterbitkan otomatis saat cetak 1721-A1 (API).');
            Audit::log('api.tax.issue', 'tax_slip', $slipId, ['year' => $year]);
            $slip = $this->tax->slip($actorId, $year);
        }

        if (!$slip) {
            $this->fail(409, 'Bukti potong belum diterbitkan HR. Hubungi HR untuk menerbitkannya.', [], 'NOT_ISSUED');
        }

        $verifyUrl = url('tax/verify/' . $actorId . '/' . $year);

        $html = View::partial('tax/pdf', [
            'detail'    => $detail,
            'slip'      => $slip,
            'year'      => $year,
            'verifyUrl' => $verifyUrl,
            'qrPath'    => Qr::png($verifyUrl, 240, 6),
            'company'   => [
                'name'    => (string) setting('company_name', 'PT Asia Raya Group'),
                'legal'   => (string) setting('company_legal_name', ''),
                'address' => (string) setting('company_address', ''),
                'phone'   => (string) setting('company_phone', ''),
                'email'   => (string) setting('company_email', ''),
                'npwp'    => (string) setting('company_npwp', ''),
            ],
            'signers'   => (new ReportModel())->signers(),
            'printedAt' => date('d M Y H:i'),
            'printedBy' => (string) ($this->user['full_name'] ?? ''),
        ]);

        $number = $slip['slip_number'] ?? ('1.1-' . $year . '-DRAFT');
        $filename = '1721-A1-' . str_replace(['/', ' '], '-', (string) $number) . '.pdf';

        $download = true;
        if (array_key_exists('download', $_GET)) {
            $raw = (string) $_GET['download'];
            $download = $raw === '' ? true : ApiRequest::boolean($raw);
        }
        if (ApiRequest::boolean(ApiRequest::query('inline', false))) {
            $download = false;
        }

        Audit::log('api.tax.pdf', 'tax_slip', (int) ($slip['id'] ?? 0), [
            'year'   => $year,
            'signed' => ApiAuth::current() === null,
        ]);

        $this->pdfResponse($html, $filename, $download);
    }

    private function signedDownload(int $year, int $employeeId): string
    {
        return ApiAuth::signedUrl('api/v1/tax-slips/' . $year . '/pdf', 'tax-slip', $year, $employeeId, true);
    }
}
