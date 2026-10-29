<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Pdf;
use App\Core\View;
use App\Models\BpjsModel;
use App\Models\OrgModel;

/**
 * Rekap iuran BPJS Kesehatan & Ketenagakerjaan — Batch 20.
 *
 * Menampilkan porsi karyawan vs perusahaan per program (KES, JHT, JP, JKK,
 * JKM), tren bulanan, dan kelengkapan nomor peserta. Angka mengikuti komponen
 * payroll yang dihitung PayrollModel sehingga konsisten dengan slip gaji.
 */
final class BpjsController extends Controller
{
    private BpjsModel $bpjs;
    private OrgModel $org;

    public function __construct()
    {
        $this->bpjs = new BpjsModel();
        $this->org = new OrgModel();
    }

    public function index(): void
    {
        $filters = $this->bpjs->resolveMonth($this->bpjs->filtersFromRequest());
        $months = $this->bpjs->months((int) $filters['year']);
        if ((int) $filters['month'] > 0 && !in_array((int) $filters['month'], $months, true)) {
            $filters['month'] = $months ? (int) end($months) : 0;
        }

        $summary = $this->bpjs->summary($filters);

        $this->view('bpjs/index', [
            'title'     => 'Rekap BPJS',
            'pageTitle' => 'Rekap BPJS Kesehatan & Ketenagakerjaan',
            'filters'   => $filters,
            'summary'   => $summary,
            'rows'      => $this->bpjs->employees($filters),
            'yearly'    => $this->bpjs->yearly($filters),
            'coverage'  => $this->bpjs->coverage(),
            'months'    => $months,
            'years'     => $this->bpjs->years(),
            'units'     => $this->org->units(),
            'programs'  => BpjsModel::PROGRAMS,
        ] + $this->withInput());
    }

    /** CSV rincian peserta per bulan — siap dikirim ke BPJS/Finance. */
    public function csv(): void
    {
        $filters = $this->bpjs->resolveMonth($this->bpjs->filtersFromRequest());
        $rows = $this->bpjs->employees($filters);
        $summary = $this->bpjs->summary($filters);

        $header = ['NO', 'NIK', 'NAMA', 'UNIT', 'NO. BPJS KESEHATAN', 'NO. BPJS TK', 'UPAH (Rp)',
            'KES PERUSAHAAN', 'KES KARYAWAN', 'JHT PERUSAHAAN', 'JHT KARYAWAN',
            'JP PERUSAHAAN', 'JP KARYAWAN', 'JKK', 'JKM',
            'TOTAL PERUSAHAAN', 'TOTAL KARYAWAN', 'GRAND TOTAL'];

        $out = [];
        $no = 0;
        foreach ($rows as $row) {
            $no++;
            $out[] = [
                $no,
                (string) $row['employee_code'],
                (string) $row['employee_name'],
                (string) ($row['unit_name'] ?? '-'),
                (string) ($row['kes_no'] ?? ''),
                (string) ($row['tk_no'] ?? ''),
                (float) $row['wage'],
                (float) $row['KES_ER'],
                (float) $row['KES_EE'],
                (float) $row['JHT_ER'],
                (float) $row['JHT_EE'],
                (float) $row['JP_ER'],
                (float) $row['JP_EE'],
                (float) $row['JKK_ER'],
                (float) $row['JKM_ER'],
                (float) $row['company_total'],
                (float) $row['employee_total'],
                (float) $row['grand_total'],
            ];
        }
        $out[] = ['', '', 'TOTAL', '', '', '', (float) $summary['wage'], '', '', '', '', '', '', '', '',
            (float) $summary['company_total'], (float) $summary['employee_total'], (float) $summary['grand_total']];

        $stamp = $filters['year'] . ((int) $filters['month'] > 0 ? sprintf('%02d', $filters['month']) : '');
        $filename = 'rekap-bpjs-' . $stamp . '.csv';

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

        Audit::log('bpjs.export', 'report', null, [
            'year'   => $filters['year'],
            'month'  => $filters['month'],
            'rows'   => count($rows),
            'format' => 'csv',
        ]);
        exit;
    }

    public function pdf(): void
    {
        $filters = $this->bpjs->resolveMonth($this->bpjs->filtersFromRequest());
        $rows = $this->bpjs->employees($filters);
        $summary = $this->bpjs->summary($filters);

        $period = (int) $filters['month'] > 0
            ? bulan_id((int) $filters['month']) . ' ' . $filters['year']
            : 'Tahun ' . $filters['year'];

        $html = View::partial('bpjs/pdf', [
            'rows'     => array_slice($rows, 0, 400),
            'summary'  => $summary,
            'programs' => BpjsModel::PROGRAMS,
            'filters'  => $filters,
            'period'   => $period,
            'subtitle' => $this->subtitle($filters),
            'company'  => [
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

        Audit::log('bpjs.export', 'report', null, [
            'year'   => $filters['year'],
            'month'  => $filters['month'],
            'rows'   => count($rows),
            'format' => 'pdf',
        ]);

        $stamp = $filters['year'] . ((int) $filters['month'] > 0 ? sprintf('%02d', $filters['month']) : '');
        Pdf::send($html, 'rekap-bpjs-' . $stamp . '.pdf', false, 'landscape');
    }

    private function subtitle(array $filters): string
    {
        $parts = [];
        if ((int) $filters['unit'] > 0) {
            foreach ($this->org->units() as $unit) {
                if ((int) $unit['id'] === (int) $filters['unit']) {
                    $parts[] = 'Unit: ' . $unit['name'];
                }
            }
        }
        $parts[] = 'Sumber: komponen payroll periode final';

        return implode(' · ', $parts);
    }
}
