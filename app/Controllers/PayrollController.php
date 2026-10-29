<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Pdf;
use App\Core\Qr;
use App\Core\Validator;
use App\Core\View;
use App\Models\OrgModel;
use App\Models\PayrollModel;
use App\Models\SalaryModel;

/**
 * Payroll: periode per unit, kalkulasi slip, rekap, slip PDF ber-QR,
 * dan alur HR → Finance → dibayar.
 */
final class PayrollController extends Controller
{
    private PayrollModel $payroll;
    private OrgModel $org;

    public function __construct()
    {
        $this->payroll = new PayrollModel();
        $this->org = new OrgModel();
    }

    /* ============================================================== daftar */

    public function index(): void
    {
        $filters = [
            'unit'   => (int) ($_GET['unit'] ?? 0),
            'year'   => (int) ($_GET['year'] ?? 0),
            'status' => (string) ($_GET['status'] ?? ''),
            'q'      => trim((string) ($_GET['q'] ?? '')),
        ];
        if ($filters['status'] !== '' && !isset(PayrollModel::STATUSES[$filters['status']])) {
            $filters['status'] = '';
        }

        $perPage = 10;
        $total = $this->payroll->countPeriods($filters);
        $paginator = new Paginator($total, $perPage, min(max(1, (int) ($_GET['page'] ?? 1)), max(1, (int) ceil($total / $perPage))));

        $units = $this->org->units();
        $years = range((int) date('Y'), (int) date('Y') - 3);

        $this->view('payroll/index', [
            'title'      => 'Payroll',
            'pageTitle'  => 'Payroll',
            'periods'    => $this->payroll->periods($filters, $perPage, $paginator->offset()),
            'paginator'  => $paginator,
            'filters'    => $filters,
            'units'      => $units,
            'years'      => $years,
            'stats'      => $this->payroll->stats(),
            'trend'      => $this->payroll->trend(6),
            'statuses'   => PayrollModel::STATUSES,
            'settings'   => (new SalaryModel())->settings(),
            'headcounts' => $this->headcountMap($units),
            'isHr'       => Auth::hasRole('HR'),
            'isFin'      => Auth::hasRole('FIN'),
        ] + $this->withInput());
    }

    public function store(): void
    {
        $this->requireRole('HR');

        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('business_unit_id', 'Unit organisasi')
                ->numeric('business_unit_id', 'Unit organisasi', 1)
                ->required('period_month', 'Bulan')
                ->required('period_year', 'Tahun');
        }, '/payroll');

        $result = $this->payroll->createPeriod(
            (int) $data['business_unit_id'],
            (int) $data['period_year'],
            (int) $data['period_month'],
            trim((string) ($data['notes'] ?? '')) ?: null
        );

        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/payroll');
        }

        flash('success', 'Periode payroll dibuat. Klik "Hitung" untuk memproses slip karyawan.');
        redirect('/payroll/' . $result['id']);
    }

    public function destroy(string $id): void
    {
        $this->requireRole('HR');
        $result = $this->payroll->deletePeriod((int) $id);
        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/payroll');
        }
        flash('success', 'Periode payroll dihapus.');
        redirect('/payroll');
    }

    /* ============================================================ detail */

    public function show(string $id): void
    {
        $period = $this->payroll->find((int) $id);
        if (!$period) {
            $this->abort(404, 'Periode payroll tidak ditemukan.');
        }

        $filters = [
            'department' => (string) ($_GET['department'] ?? ''),
            'q'          => trim((string) ($_GET['q'] ?? '')),
        ];

        $items = $this->payroll->items((int) $id, $filters);
        $recap = $this->payroll->recapitulation((int) $id);

        $this->view('payroll/show', [
            'title'     => 'Payroll ' . $this->payroll->label((int) $period['period_month'], (int) $period['period_year']),
            'pageTitle' => 'Payroll',
            'period'    => $period,
            'items'     => $items,
            'recap'     => $recap,
            'filters'   => $filters,
            'statuses'  => PayrollModel::STATUSES,
            'isHr'      => Auth::hasRole('HR'),
            'isFin'     => Auth::hasRole('FIN'),
        ] + $this->withInput());
    }

    public function calculate(string $id): void
    {
        $this->requireRole('HR');
        $result = $this->payroll->calculate((int) $id);
        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/payroll/' . (int) $id);
        }
        flash('success', $result['count'] . ' slip berhasil dihitung dari presensi, lembur, komponen gaji, BPJS, dan PPh 21.');
        redirect('/payroll/' . (int) $id);
    }

    public function submit(string $id): void
    {
        $this->requireRole('HR');
        $result = $this->payroll->submitToFinance((int) $id);
        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/payroll/' . (int) $id);
        }
        flash('success', 'Payroll ditandatangani HR dan dikirim ke Finance untuk finalisasi.');
        redirect('/payroll/' . (int) $id);
    }

    public function approve(string $id): void
    {
        $this->requireRole('FIN');
        $result = $this->payroll->approveFinance((int) $id);
        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/payroll/' . (int) $id);
        }
        flash('success', 'Payroll disetujui Finance. Tandai pembayaran setelah transfer dilakukan.');
        redirect('/payroll/' . (int) $id);
    }

    public function pay(string $id): void
    {
        $this->requireRole('FIN');
        $result = $this->payroll->markPaid((int) $id);
        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/payroll/' . (int) $id);
        }
        flash('success', $result['paid'] . ' slip ditandai LUNAS. Karyawan dapat mengunduh slip gaji mereka.');
        redirect('/payroll/' . (int) $id);
    }

    public function reopen(string $id): void
    {
        $this->requireRole('HR');
        $result = $this->payroll->reopen((int) $id);
        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/payroll/' . (int) $id);
        }
        flash('success', 'Periode dikembalikan ke HR. Hitung ulang bila ada koreksi.');
        redirect('/payroll/' . (int) $id);
    }

    /** Export rekap periode ke CSV. */
    public function export(string $id): void
    {
        $period = $this->payroll->find((int) $id);
        if (!$period) {
            $this->abort(404, 'Periode payroll tidak ditemukan.');
        }

        $items = $this->payroll->items((int) $id);
        $filename = 'payroll-' . $period['unit_code'] . '-' . $period['period_year'] . sprintf('%02d', (int) $period['period_month']) . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['No. Slip', 'NIK', 'Nama', 'Departemen', 'Gaji Pokok', 'Hadir', 'Absen', 'Terlambat (menit)',
            'Lembur (jam)', 'Tunjangan', 'Bruto', 'BPJS Karyawan', 'PPh 21', 'Potongan', 'Gaji Bersih', 'Status'], ',', '"', '');
        foreach ($items as $row) {
            fputcsv($out, [
                $row['slip_number'],
                $row['employee_code'],
                $row['employee_name'],
                $row['department_name'],
                (float) $row['basic_salary'],
                (int) $row['present_days'],
                (int) $row['absent_days'],
                (int) $row['late_minutes'],
                (float) $row['overtime_hours'],
                (float) $row['benefit_total'],
                (float) $row['gross_salary'],
                (float) $row['bpjs_kesehatan_employee'] + (float) $row['bpjs_tk_employee'],
                (float) $row['pph_amount'],
                (float) $row['total_deduction'],
                (float) $row['net_salary'],
                $row['payment_status'],
            ], ',', '"', '');
        }
        fclose($out);

        Audit::log('payroll.export', 'payroll_period', (int) $id, ['rows' => count($items)]);
        exit;
    }

    /* =============================================================== item */

    public function item(string $id): void
    {
        $item = $this->payroll->findItem((int) $id);
        if (!$item || !$this->canView($item)) {
            $this->abort(404, 'Slip gaji tidak ditemukan.');
        }

        $this->view('payroll/item', [
            'title'      => 'Slip ' . ($item['slip_number'] ?? ''),
            'pageTitle'  => 'Detail Slip Gaji',
            'item'       => $item,
            'components' => $this->payroll->itemComponents((int) $id),
            'isHr'       => Auth::hasRole('HR'),
            'isFin'      => Auth::hasRole('FIN'),
            'isOwner'    => (int) $item['employee_id'] === (int) Auth::id(),
            'verifyUrl'  => $this->payroll->verifyUrl((int) $id),
        ]);
    }

    /** Unduh / tampilkan slip PDF ber-QR. */
    public function slipPdf(string $id): void
    {
        $item = $this->payroll->findItem((int) $id);
        if (!$item || !$this->canView($item)) {
            $this->abort(404, 'Slip gaji tidak ditemukan.');
        }

        $components = $this->payroll->itemComponents((int) $id);
        $verifyUrl = $this->payroll->verifyUrl((int) $id);
        $qrPath = Qr::png($verifyUrl, 260, 6);

        $html = View::partial('payroll/slip_pdf', [
            'item'       => $item,
            'components' => $components,
            'verifyUrl'  => $verifyUrl,
            'qrPath'     => $qrPath,
            'company'    => [
                'name'    => (string) setting('company_name', 'PT Asia Raya Group'),
                'legal'   => (string) setting('company_legal_name', ''),
                'address' => (string) setting('company_address', ''),
                'phone'   => (string) setting('company_phone', ''),
                'email'   => (string) setting('company_email', ''),
                'npwp'    => (string) setting('company_npwp', ''),
            ],
            'printedAt'  => date('d M Y H:i'),
        ]);

        $download = isset($_GET['download']);
        $filename = str_replace('/', '-', (string) ($item['slip_number'] ?? 'slip')) . '.pdf';

        Audit::log('payroll.slip', 'payroll_item', (int) $id, ['download' => $download]);
        Pdf::send($html, $filename, $download);
    }

    /* ======================================================= self service */

    public function mine(): void
    {
        $employeeId = $this->employeeId();
        $slips = $this->payroll->mySlips($employeeId);

        $this->view('payroll/mine', [
            'title'     => 'Slip Gaji Saya',
            'pageTitle' => 'Slip Gaji Saya',
            'slips'     => $slips,
            'totals'    => [
                'count' => count($slips),
                'net'   => array_sum(array_map(static fn ($s) => (float) $s['net_salary'], $slips)),
                'paid'  => count(array_filter($slips, static fn ($s) => (string) $s['period_status'] === 'PAID')),
            ],
            'verifyBase' => url('verify/slip'),
        ]);
    }

    /** Halaman verifikasi QR (publik, tanpa login). */
    public function verify(string $id, string $token): void
    {
        $item = $this->payroll->verifyItem((int) $id, $token);
        $components = $item ? $this->payroll->itemComponents((int) $id) : [];

        $this->view('payroll/verify', [
            'title'      => 'Verifikasi Slip Gaji',
            'pageTitle'  => 'Verifikasi Slip Gaji',
            'item'       => $item,
            'components' => $components,
            'company'    => (string) setting('company_name', 'PT Asia Raya Group'),
        ], 'layouts/auth');
    }

    /* ============================================================ internal */

    private function canView(array $item): bool
    {
        return Auth::hasRole('HR') || Auth::hasRole('FIN') || (int) $item['employee_id'] === (int) Auth::id();
    }

    /** @return array<int,int> jumlah karyawan aktif per unit */
    private function headcountMap(array $units): array
    {
        $map = [];
        foreach ($units as $unit) {
            $map[(int) $unit['id']] = $this->payroll->periodHeadcount((int) $unit['id']);
        }
        return $map;
    }
}
