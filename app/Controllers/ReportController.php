<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Pdf;
use App\Core\View;
use App\Models\OrgModel;
use App\Models\ReportModel;

/**
 * Laporan & cetak: presensi, lembur, cuti, payroll — tampilan web,
 * export CSV (Excel-ready), dan PDF landscape ber-kop.
 */
final class ReportController extends Controller
{
    private ReportModel $reports;
    private OrgModel $org;

    public function __construct()
    {
        $this->reports = new ReportModel();
        $this->org = new OrgModel();
    }

    public function index(): void
    {
        $type = (string) ($_GET['type'] ?? 'attendance');
        if (!isset(ReportModel::TYPES[$type])) {
            $type = 'attendance';
        }

        $filters = $this->filters($type);
        $data = $this->dataset($type, $filters);

        $this->view('reports/index', [
            'title'      => 'Laporan',
            'pageTitle'  => 'Laporan',
            'type'       => $type,
            'types'      => ReportModel::TYPES,
            'icons'      => ReportModel::TYPE_ICONS,
            'filters'    => $filters,
            'report'     => $data,
            'units'      => $this->org->units(),
            'departments' => $this->org->departments(),
            'leaveTypes' => (new \App\Models\LeaveModel())->types(),
            'monthLabel' => $this->reports->monthLabel((string) $filters['month']),
            'canSeePayroll' => \App\Core\Auth::hasRole(['HR', 'FIN']),
        ] + $this->withInput());
    }

    /** Alias agar tautan dashboard `/reports/payroll` tetap berfungsi. */
    public function payroll(): void
    {
        $_GET['type'] = 'payroll';
        $this->index();
    }

    public function csv(string $type): void
    {
        if (!isset(ReportModel::TYPES[$type])) {
            $this->abort(404, 'Jenis laporan tidak ditemukan.');
        }

        $filters = $this->filters($type);
        $data = $this->dataset($type, $filters);
        [$header, $rows] = $this->tabular($type, $data, $filters);

        $filename = 'laporan-' . $type . '-' . $this->fileStamp($type, $filters) . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, $row, ',', '"', '');
        }
        fclose($out);

        Audit::log('report.export', 'report', null, ['type' => $type, 'format' => 'csv', 'rows' => count($rows)]);
        exit;
    }

    public function pdf(string $type): void
    {
        if (!isset(ReportModel::TYPES[$type])) {
            $this->abort(404, 'Jenis laporan tidak ditemukan.');
        }

        $filters = $this->filters($type);
        $data = $this->dataset($type, $filters);
        [$header, $rows] = $this->tabular($type, $data, $filters);

        $html = View::partial('reports/pdf', [
            'type'     => $type,
            'title'    => ReportModel::TYPES[$type],
            'header'   => $header,
            'rows'     => array_slice($rows, 0, 400),
            'total'    => count($rows),
            'filters'  => $filters,
            'report'   => $data,
            'subtitle' => $this->subtitle($type, $filters),
            'signers'  => $this->reports->signers(),
            'company'  => [
                'name'    => (string) setting('company_name', 'PT Asia Raya Group'),
                'legal'   => (string) setting('company_legal_name', ''),
                'address' => (string) setting('company_address', ''),
                'phone'   => (string) setting('company_phone', ''),
                'email'   => (string) setting('company_email', ''),
                'npwp'    => (string) setting('company_npwp', ''),
            ],
            'printedAt' => date('d M Y H:i'),
            'printedBy' => (string) (auth()['full_name'] ?? ''),
        ]);

        Audit::log('report.export', 'report', null, ['type' => $type, 'format' => 'pdf', 'rows' => count($rows)]);
        Pdf::send($html, 'laporan-' . $type . '-' . $this->fileStamp($type, $filters) . '.pdf', false, 'landscape');
    }

    /* ============================================================== internal */

    private function filters(string $type): array
    {
        $month = (string) ($_GET['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        return [
            'type'       => $type,
            'month'      => $month,
            'year'       => (int) ($_GET['year'] ?? date('Y')),
            'unit'       => (int) ($_GET['unit'] ?? 0),
            'department' => (int) ($_GET['department'] ?? 0),
            'leave_type' => (int) ($_GET['leave_type'] ?? 0),
            'period'     => (int) ($_GET['period'] ?? 0),
            'q'          => trim((string) ($_GET['q'] ?? '')),
        ];
    }

    private function dataset(string $type, array $filters): array
    {
        return match ($type) {
            'overtime' => $this->reports->overtime($filters),
            'leave'    => $this->reports->leave($filters) + ['by_type' => $this->reports->leaveByType((int) $filters['year'], $filters)],
            'payroll'  => $this->reports->payroll($filters),
            default    => $this->reports->attendance($filters),
        };
    }

    /**
     * Susun header + baris tabel laporan (dipakai CSV & PDF).
     * @return array{0:array<int,string>,1:array<int,array<int,mixed>>}
     */
    private function tabular(string $type, array $data, array $filters = []): array
    {
        $rows = [];
        $lines = $data['rows'] ?? [];

        if ($type === 'attendance') {
            $header = ['NIK', 'Nama', 'Unit', 'Departemen', 'Hadir', 'Terlambat (hari)', 'Terlambat (menit)',
                'Absen', 'Cuti', 'Jam Kerja', 'Kehadiran (%)', 'Tanpa Check-out'];
            foreach ($lines as $row) {
                $rows[] = [
                    $row['employee_code'], $row['full_name'], $row['unit_name'] ?? '-', $row['department_name'] ?? '-',
                    (int) $row['present_days'], (int) $row['late_days'], (int) $row['late_minutes'],
                    (int) $row['absent_days'], (int) $row['leave_days'], (float) $row['work_hours'],
                    (float) $row['attendance_rate'], (int) $row['missing_checkout'],
                ];
            }
            $totals = $data['totals'];
            $rows[] = ['', 'TOTAL', '', '', (int) $totals['present_days'], (int) $totals['late_days'],
                (int) $totals['late_minutes'], (int) $totals['absent_days'], (int) $totals['leave_days'],
                (float) $totals['work_hours'], '', (int) $totals['missing_checkout']];

            return [$header, $rows];
        }

        if ($type === 'overtime') {
            $header = ['NIK', 'Nama', 'Unit', 'Departemen', 'Pengajuan', 'Disetujui', 'Menunggu Atasan',
                'Menunggu HR', 'Ditolak', 'Jam Disetujui', 'Kompensasi (Rp)'];
            foreach ($lines as $row) {
                $rows[] = [
                    $row['employee_code'], $row['full_name'], $row['unit_name'] ?? '-', $row['department_name'] ?? '-',
                    (int) $row['requests'], (int) $row['approved'], (int) $row['pending'],
                    (int) $row['manager_approved'], (int) $row['rejected'],
                    (float) $row['approved_hours'], (float) $row['compensation'],
                ];
            }
            $totals = $data['totals'];
            $rows[] = ['', 'TOTAL', '', '', (int) $totals['requests'], (int) $totals['approved'],
                (int) $totals['pending'], (int) $totals['manager_approved'], (int) $totals['rejected'],
                (float) $totals['approved_hours'], (float) $totals['compensation']];

            return [$header, $rows];
        }

        if ($type === 'leave') {
            $header = ['NIK', 'Nama', 'Unit', 'Departemen', 'Jenis Cuti', 'Jatah', 'Carry Over',
                'Terpakai', 'Sisa', 'Disetujui (hari)', 'Pengajuan Menunggu'];
            foreach ($lines as $row) {
                $rows[] = [
                    $row['employee_code'], $row['full_name'], $row['unit_name'] ?? '-', $row['department_name'] ?? '-',
                    $row['leave_type'], (float) $row['quota_days'], (float) $row['carry_over'],
                    (float) $row['used_days'], (float) $row['remaining_days'],
                    (float) $row['approved_days'], (int) $row['pending_requests'],
                ];
            }
            $totals = $data['totals'];
            $rows[] = ['', 'TOTAL', '', '', '', (float) $totals['quota_days'], (float) $totals['carry_over'],
                (float) $totals['used_days'], (float) $totals['remaining_days'],
                (float) $totals['approved_days'], (int) $totals['pending_requests']];

            return [$header, $rows];
        }

        // payroll
        $header = ['Periode', 'Unit', 'Status', 'Karyawan', 'Bruto (Rp)', 'Potongan (Rp)', 'Net (Rp)', 'BPJS Perusahaan (Rp)', 'Tanggal Bayar'];
        if (!empty($filters['period'])) {
            $header = ['No. Slip', 'NIK', 'Karyawan', 'Departemen', 'Bruto (Rp)', 'Potongan (Rp)', 'Net (Rp)', 'Status Bayar'];
            foreach ($data['items'] as $row) {
                $rows[] = [
                    $row['slip_number'], $row['employee_code'], $row['employee_name'], $row['department_name'] ?? '-',
                    (float) $row['gross_salary'], (float) $row['total_deduction'], (float) $row['net_salary'],
                    $row['payment_status'],
                ];
            }
            if ($rows) {
                $gross = $deduction = $net = 0.0;
                foreach ($data['items'] as $item) {
                    $gross += (float) $item['gross_salary'];
                    $deduction += (float) $item['total_deduction'];
                    $net += (float) $item['net_salary'];
                }
                $rows[] = ['TOTAL', '', count($data['items']) . ' slip', '', round($gross, 2), round($deduction, 2), round($net, 2), ''];
            }
            return [$header, $rows];
        }

        foreach ($data['periods'] as $row) {
            $rows[] = [
                bulan_id((int) $row['period_month']) . ' ' . (int) $row['period_year'],
                $row['unit_name'] ?? '-',
                $row['status'],
                (int) $row['total_employee'],
                (float) $row['total_gross'],
                (float) $row['total_deduction'],
                (float) $row['total_net'],
                (float) $row['total_bpjs_company'],
                $row['pay_date'] ?? '-',
            ];
        }

        if ($rows) {
            $rows[] = [
                'TOTAL', '', '', (int) $data['totals']['total_employee'],
                (float) $data['totals']['total_gross'], (float) $data['totals']['total_deduction'],
                (float) $data['totals']['total_net'], (float) $data['totals']['total_bpjs_company'], '',
            ];
        }

        return [$header, $rows];
    }

    private function subtitle(string $type, array $filters): string
    {
        $parts = [];
        if ($type === 'attendance' || $type === 'overtime') {
            $parts[] = 'Periode ' . $this->reports->monthLabel((string) $filters['month']);
        } elseif ($type === 'leave') {
            $parts[] = 'Tahun ' . (int) $filters['year'];
        } else {
            $parts[] = 'Tahun ' . (int) $filters['year'];
        }

        if ((int) $filters['unit'] > 0) {
            foreach ($this->org->units() as $unit) {
                if ((int) $unit['id'] === (int) $filters['unit']) {
                    $parts[] = 'Unit: ' . $unit['name'];
                }
            }
        }
        if ((int) $filters['department'] > 0) {
            foreach ($this->org->departments() as $department) {
                if ((int) $department['id'] === (int) $filters['department']) {
                    $parts[] = 'Departemen: ' . $department['name'];
                }
            }
        }
        if (($filters['q'] ?? '') !== '') {
            $parts[] = 'Pencarian: ' . $filters['q'];
        }

        return implode(' · ', $parts);
    }

    private function fileStamp(string $type, array $filters): string
    {
        return match ($type) {
            'attendance', 'overtime' => str_replace('-', '', (string) $filters['month']),
            default => (string) (int) $filters['year'],
        };
    }
}
