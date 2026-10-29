<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Pdf;
use App\Core\Qr;
use App\Core\View;
use App\Models\OrgModel;
use App\Models\TaxModel;

/**
 * Pajak penghasilan (PPh 21) & bukti potong 1721-A1 — Batch 20.
 *
 * HR/FIN melihat rekap tahunan seluruh karyawan, mencetak bukti potong resmi
 * ber-QR, menerbitkan sekaligus, dan mengunduh CSV siap impor e-SPT. Karyawan
 * hanya dapat melihat & mengunduh bukti potongnya sendiri.
 */
final class TaxController extends Controller
{
    private TaxModel $tax;
    private OrgModel $org;

    public function __construct()
    {
        $this->tax = new TaxModel();
        $this->org = new OrgModel();
    }

    /* ============================================================ rekap HR */

    public function index(): void
    {
        $filters = $this->tax->filtersFromRequest();
        $rows = $this->tax->rows($filters);

        $this->view('tax/index', [
            'title'      => 'PPh 21 & 1721-A1',
            'pageTitle'  => 'Pajak Penghasilan & Bukti Potong',
            'filters'    => $filters,
            'rows'       => $rows,
            'summary'    => $this->tax->summary($rows),
            'monthly'    => $this->tax->monthly($filters),
            'byPtkp'     => $this->tax->byPtkp($rows),
            'years'      => $this->tax->years(),
            'units'      => $this->org->units(),
            'issued'     => $this->tax->issuedCount((int) $filters['year']),
            'canManage'  => Auth::hasRole(['HR', 'FIN']),
        ] + $this->withInput());
    }

    /* =========================================================== per karyawan */

    public function show(string $id): void
    {
        $year = $this->yearParam();
        $detail = $this->tax->employeeDetail((int) $id, $year);
        if (!$detail) {
            $this->abort(404, 'Karyawan tidak ditemukan.');
        }
        if ((int) $detail['computed']['months'] === 0) {
            flash('error', 'Belum ada payroll final untuk ' . $detail['employee']['full_name'] . ' pada tahun ' . $year . '.');
            redirect('/tax?year=' . $year);
        }

        $this->view('tax/show', [
            'title'     => 'PPh 21 · ' . $detail['employee']['full_name'],
            'pageTitle' => 'Bukti Potong 1721-A1',
            'detail'    => $detail,
            'year'      => $year,
            'years'     => $this->tax->years(),
            'slip'      => $detail['slip'],
            'canManage' => Auth::hasRole(['HR', 'FIN']),
        ] + $this->withInput());
    }

    /** Terbitkan / perbarui bukti potong satu karyawan. */
    public function issue(string $id): void
    {
        $year = $this->yearParam();
        $detail = $this->tax->employeeDetail((int) $id, $year);
        if (!$detail || (int) $detail['computed']['months'] === 0) {
            flash('error', 'Data payroll untuk karyawan ini belum tersedia.');
            redirect('/tax?year=' . $year);
        }

        $note = trim((string) ($_POST['note'] ?? ''));
        $slipId = $this->tax->issue($detail, $year, (int) $this->employeeId(), $note);

        Audit::log('tax.issue', 'tax_slip', $slipId, [
            'employee' => $detail['employee']['employee_code'],
            'year'     => $year,
            'tax_due'  => $detail['computed']['tax_due'],
        ]);

        flash('success', 'Bukti potong 1721-A1 ' . $detail['employee']['full_name'] . ' tahun ' . $year . ' siap diunduh.');
        redirect('/tax/' . (int) $id . '?year=' . $year);
    }

    /** Terbitkan sekaligus untuk semua karyawan sesuai filter. */
    public function issueAll(): void
    {
        $filters = $this->tax->filtersFromRequest();
        $result = $this->tax->issueAll($filters, (int) $this->employeeId());

        Audit::log('tax.issue_all', 'tax_slip', null, [
            'year'   => $filters['year'],
            'issued' => $result['issued'],
        ]);

        $message = $result['issued'] . ' bukti potong diterbitkan untuk tahun ' . $filters['year'] . '.';
        if ($result['skipped'] > 0) {
            $message .= ' ' . $result['skipped'] . ' karyawan dilewati karena belum punya payroll final.';
        }
        flash('success', $message);
        redirect('/tax?' . http_build_query(array_filter([
            'year' => $filters['year'],
            'unit' => $filters['unit'] ?: null,
            'q'    => $filters['q'] ?: null,
        ])));
    }

    /** Tarik kembali bukti potong (mis. salah terbit). */
    public function revoke(string $id): void
    {
        $year = $this->yearParam();
        $slip = $this->tax->slip((int) $id, $year);
        if ($slip && $this->tax->revoke((int) $id, $year)) {
            Audit::log('tax.revoke', 'tax_slip', (int) $slip['id'], [
                'employee' => $slip['employee_name'],
                'year'     => $year,
            ]);
            flash('success', 'Bukti potong ' . $slip['slip_number'] . ' ditarik kembali.');
        } else {
            flash('error', 'Bukti potong tidak ditemukan.');
        }
        redirect('/tax/' . (int) $id . '?year=' . $year);
    }

    /* ================================================================ cetak */

    /** PDF 1721-A1 resmi (auto-terbit pada cetak pertama). */
    public function pdf(string $id): void
    {
        // HR/Finance boleh mencetak siapa pun; karyawan hanya bukti potongnya.
        if (!Auth::hasRole(['HR', 'FIN']) && (int) $this->employeeId() !== (int) $id) {
            $this->abort(403, 'Anda hanya dapat mengunduh bukti potong milik sendiri.');
        }

        $year = $this->yearParam();
        $detail = $this->tax->employeeDetail((int) $id, $year);
        if (!$detail || (int) $detail['computed']['months'] === 0) {
            $this->abort(404, 'Data pajak karyawan belum tersedia.');
        }

        $slip = $detail['slip'];
        if (!$slip && Auth::hasRole(['HR', 'FIN'])) {
            $slipId = $this->tax->issue($detail, $year, (int) $this->employeeId(), 'Diterbitkan otomatis saat cetak 1721-A1.');
            Audit::log('tax.issue', 'tax_slip', $slipId, ['employee' => $detail['employee']['employee_code'], 'year' => $year, 'auto' => true]);
            $slip = $this->tax->slip((int) $id, $year);
        }

        $verifyUrl = url('tax/verify/' . (int) $id . '/' . $year);
        $qrPath = Qr::png($verifyUrl, 240, 6);

        $html = View::partial('tax/pdf', [
            'detail'    => $detail,
            'slip'      => $slip,
            'year'      => $year,
            'verifyUrl' => $verifyUrl,
            'qrPath'    => $qrPath,
            'company'   => [
                'name'    => (string) setting('company_name', 'PT Asia Raya Group'),
                'legal'   => (string) setting('company_legal_name', ''),
                'address' => (string) setting('company_address', ''),
                'phone'   => (string) setting('company_phone', ''),
                'email'   => (string) setting('company_email', ''),
                'npwp'    => (string) setting('company_npwp', ''),
            ],
            'signers'   => (new \App\Models\ReportModel())->signers(),
            'printedAt' => date('d M Y H:i'),
            'printedBy' => (string) (auth()['full_name'] ?? ''),
        ]);

        $number = $slip['slip_number'] ?? ('1.1-' . $year . '-DRAFT');
        $download = isset($_GET['download']);

        Audit::log('tax.pdf', 'tax_slip', $slip['id'] ?? null, [
            'employee' => $detail['employee']['employee_code'],
            'year'     => $year,
            'download' => $download,
        ]);

        Pdf::send($html, '1721-A1-' . str_replace(['/', ' '], '-', (string) $number) . '.pdf', $download);
    }

    /** Endpoint verifikasi publik untuk QR pada bukti potong. */
    public function verify(string $id, string $year): void
    {
        $detail = $this->tax->employeeDetail((int) $id, (int) $year);
        $slip = $this->tax->slip((int) $id, (int) $year);

        $this->view('tax/verify', [
            'title'     => 'Verifikasi Bukti Potong',
            'pageTitle' => 'Verifikasi Bukti Potong',
            'detail'    => $detail,
            'slip'      => $slip,
            'year'      => (int) $year,
        ], 'layouts/auth');
    }

    /* ========================================================== e-SPT / CSV */

    /**
     * CSV siap impor e-SPT 1721-I: satu baris per karyawan setahun penuh.
     */
    public function export(): void
    {
        $filters = $this->tax->filtersFromRequest();
        $rows = $this->tax->rows($filters);

        $header = ['NO', 'NIK', 'NPWP', 'NAMA', 'STATUS PTKP', 'MASA (BULAN)', 'JUMLAH BRUTO',
            'BIAYA JABATAN', 'IURAN PENSIUN', 'JUMLAH NETO', 'PTKP', 'PKP',
            'PPH 21 TERUTANG', 'PPH 21 DIPOTONG', 'SELISIH'];

        $out = [];
        $no = 0;
        foreach ($rows as $row) {
            $no++;
            $out[] = [
                $no,
                (string) ($row['employee_code'] ?? ''),
                (string) ($row['npwp'] ?? ''),
                (string) ($row['employee_name'] ?? ''),
                (string) ($row['tax_status'] ?? ''),
                (int) $row['months'],
                (float) $row['gross'],
                (float) $row['biaya_jabatan'],
                (float) $row['pension'],
                (float) $row['netto'],
                (float) $row['ptkp'],
                (float) $row['pkp'],
                (float) $row['tax_due'],
                (float) $row['tax_withheld'],
                (float) $row['tax_diff'],
            ];
        }

        $summary = $this->tax->summary($rows);
        $out[] = ['', '', '', 'TOTAL', '', '', (float) $summary['gross'], '', '',
            (float) $summary['netto'], '', (float) $summary['pkp'],
            (float) $summary['tax_due'], (float) $summary['tax_withheld'], (float) $summary['tax_diff']];

        $filename = 'espt-1721-I-' . $filters['year'] . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');

        $handle = fopen('php://output', 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $header, ',', '"', '');
        foreach ($out as $line) {
            fputcsv($handle, $line, ',', '"', '');
        }
        fclose($handle);

        Audit::log('tax.export', 'tax_slip', null, [
            'year'   => $filters['year'],
            'unit'   => $filters['unit'],
            'rows'   => count($rows),
            'format' => 'espt-csv',
        ]);
        exit;
    }

    /* ======================================================= self service */

    /** Daftar bukti potong milik karyawan yang login. */
    public function mine(): void
    {
        $employeeId = (int) $this->employeeId();
        $slips = [];
        $years = $this->tax->years();
        foreach ($years as $year) {
            $slip = $this->tax->slip($employeeId, $year);
            if ($slip) {
                $slips[] = $slip;
            }
        }

        $this->view('tax/mine', [
            'title'     => 'Bukti Potong Saya',
            'pageTitle' => 'Bukti Potong Pajak Saya',
            'slips'     => $slips,
            'years'     => $years,
            'employeeId' => $employeeId,
        ] + $this->withInput());
    }

    private function yearParam(): int
    {
        $year = (int) ($_POST['year'] ?? $_GET['year'] ?? 0);
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }

        return $year;
    }
}
