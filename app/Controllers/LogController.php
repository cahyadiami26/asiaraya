<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Settings;
use App\Models\ReportModel;

/**
 * Log aktivitas sistem (audit trail) — khusus HR.
 */
final class LogController extends Controller
{
    private ReportModel $reports;

    public function __construct()
    {
        $this->reports = new ReportModel();
    }

    public function index(): void
    {
        $filters = [
            'group'    => (string) ($_GET['group'] ?? ''),
            'employee' => (int) ($_GET['employee'] ?? 0),
            'from'     => (string) ($_GET['from'] ?? ''),
            'to'       => (string) ($_GET['to'] ?? ''),
            'q'        => trim((string) ($_GET['q'] ?? '')),
        ];
        if ($filters['group'] !== '' && !isset($this->reports->logGroups()[$filters['group']])) {
            $filters['group'] = '';
        }

        $perPage = 30;
        $total = $this->reports->countLogs($filters);
        $paginator = new Paginator($total, $perPage, min(max(1, (int) ($_GET['page'] ?? 1)), max(1, (int) ceil($total / $perPage))));

        $this->view('logs/index', [
            'title'     => 'Log Aktivitas',
            'pageTitle' => 'Log Aktivitas',
            'logs'      => $this->reports->logs($filters, $perPage, $paginator->offset()),
            'paginator' => $paginator,
            'filters'   => $filters,
            'groups'    => $this->reports->logGroups(),
            'stats'     => $this->reports->logStats(),
            'retention' => (int) Settings::get('audit_retention_days', 180),
        ] + $this->withInput());
    }

    public function export(): void
    {
        $filters = [
            'group'    => (string) ($_GET['group'] ?? ''),
            'employee' => (int) ($_GET['employee'] ?? 0),
            'from'     => (string) ($_GET['from'] ?? ''),
            'to'       => (string) ($_GET['to'] ?? ''),
            'q'        => trim((string) ($_GET['q'] ?? '')),
        ];
        $rows = $this->reports->logs($filters, 5000, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="log-aktivitas-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Waktu', 'NIK', 'Pengguna', 'Peran', 'Aksi', 'Entitas', 'ID Entitas', 'Meta', 'IP', 'Perangkat'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['created_at'] ?? '',
                $row['employee_code'] ?? '',
                $row['full_name'] ?? 'Sistem',
                $row['role'] ?? '',
                $row['action'] ?? '',
                $row['entity'] ?? '',
                $row['entity_id'] ?? '',
                $row['meta'] ?? '',
                $row['ip_address'] ?? '',
                mb_substr((string) ($row['user_agent'] ?? ''), 0, 120),
            ], ',', '"', '');
        }
        fclose($out);

        Audit::log('log.export', 'activity_log', null, ['rows' => count($rows)]);
        exit;
    }

    /** Hapus log lebih tua dari batas retensi (minimal 30 hari). */
    public function purge(): void
    {
        $days = (int) Settings::get('audit_retention_days', 180);
        $deleted = $this->reports->purgeLogs($days);

        if ($deleted > 0) {
            Audit::log('log.purge', 'activity_log', null, ['days' => $days, 'deleted' => $deleted]);
            flash('success', $deleted . ' baris log lebih tua dari ' . $days . ' hari dihapus.');
        } else {
            flash('info', 'Tidak ada log yang melewati batas retensi ' . $days . ' hari.');
        }

        redirect('/logs');
    }
}
