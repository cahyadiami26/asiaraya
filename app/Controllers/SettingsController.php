<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Settings;
use App\Core\Validator;
use App\Models\SalaryModel;

/**
 * Pengaturan HR & Payroll: siklus payroll, PPh 21, iuran BPJS, dan
 * integrasi login. Identitas perusahaan diatur di /company.
 */
final class SettingsController extends Controller
{
    private SalaryModel $salary;

    public function __construct()
    {
        $this->salary = new SalaryModel();
    }

    public function index(): void
    {
        $tab = (string) ($_GET['tab'] ?? 'payroll');
        $tabs = [
            'payroll'     => 'Payroll',
            'pph'         => 'PPh 21',
            'bpjs'        => 'BPJS',
            'integration' => 'Integrasi & Login',
            'advanced'    => 'Laporan & Audit',
        ];
        if (!isset($tabs[$tab])) {
            $tab = 'payroll';
        }

        $this->view('settings/index', [
            'title'     => 'Pengaturan HR & Payroll',
            'pageTitle' => 'Pengaturan HR & Payroll',
            'tab'       => $tab,
            'tabs'      => $tabs,
            'company'   => [
                'name'     => (string) Settings::get('company_name', config('app_name')),
                'legal'    => (string) Settings::get('company_legal_name', ''),
                'address'  => (string) Settings::get('company_address', ''),
                'phone'    => (string) Settings::get('company_phone', ''),
                'email'    => (string) Settings::get('company_email', ''),
                'website'  => (string) Settings::get('company_website', ''),
                'npwp'     => (string) Settings::get('company_npwp', ''),
                'logo'     => (string) Settings::get('company_logo', ''),
                'timezone' => (string) Settings::get('timezone', 'Asia/Jakarta'),
                'google_login_enabled' => Settings::bool('google_login_enabled', true),
                'google_client_id'     => (string) (Settings::get('google_client_id') ?: config('google_client_id', '')),
                'google_redirect'      => url('login/google/callback'),
            ],
            'payroll'   => $this->salary->settings(),
            'sample'    => $this->salary->simulationSample(5),
            'stats'     => $this->salary->stats(),
            'preview'   => $this->samplePreview(),
            'advanced'  => [
                'signer_hr'  => (string) (Settings::get('report_signer_hr') ?: ''),
                'signer_fin' => (string) (Settings::get('report_signer_fin') ?: ''),
                'retention'  => (int) Settings::get('audit_retention_days', 180),
                'log_total'  => (int) Database::value('SELECT COUNT(*) FROM activity_logs'),
                'log_oldest' => (string) (Database::value('SELECT MIN(created_at) FROM activity_logs') ?? ''),
            ],
        ] + $this->withInput());
    }

    /** Integrasi & login: status Google Sign-In (identitas perusahaan diatur di /company). */
    public function saveIntegration(): void
    {
        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->custom(mb_strlen((string) ($_POST['google_client_id'] ?? '')) > 200, 'google_client_id', 'Client ID maksimal 200 karakter.');
            $v->custom((string) ($_POST['google_client_id'] ?? '') !== ''
                && !str_contains((string) $_POST['google_client_id'], '.apps.googleusercontent.com'),
                'google_client_id', 'Client ID Google biasanya berakhiran .apps.googleusercontent.com.');
        }, '/settings?tab=integration');

        Settings::many([
            'google_login_enabled' => (int) ($_POST['google_login_enabled'] ?? 0),
            'google_client_id'     => trim((string) ($data['google_client_id'] ?? '')),
        ], 'integration');

        Audit::log('settings.integration', 'settings', null, [
            'google_login' => (int) ($_POST['google_login_enabled'] ?? 0) === 1,
        ]);
        flash('success', 'Pengaturan integrasi & login disimpan.');
        redirect('/settings?tab=integration');
    }

    /** Simpan pengaturan payroll, PPh, dan BPJS sekaligus (satu sumber kebenaran). */
    public function savePayroll(): void
    {
        $current = $this->salary->settings();

        $input = [
            // payroll
            'cutoff_day'  => $_POST['cutoff_day'] ?? $current['cutoff_day'],
            'pay_day'     => $_POST['pay_day'] ?? $current['pay_day'],
            'auto_cutoff' => $_POST['auto_cutoff'] ?? $current['auto_cutoff'],
            // pph
            'pph_enabled' => $_POST['pph_enabled'] ?? $current['pph_enabled'],
            'pph_method'  => $_POST['pph_method'] ?? $current['pph_method'],
            'pph_rate'    => $_POST['pph_rate'] ?? $current['pph_rate'],
            // bpjs
            'kes_enabled'  => $_POST['kes_enabled'] ?? $current['bpjs_kes_on'],
            'tk_enabled'   => $_POST['tk_enabled'] ?? $current['bpjs_tk_on'],
            'kes_company'  => $_POST['kes_company'] ?? $current['kes_company'],
            'kes_employee' => $_POST['kes_employee'] ?? $current['kes_employee'],
            'kes_max'      => $_POST['kes_max'] ?? $current['kes_max'],
            'jht_company'  => $_POST['jht_company'] ?? $current['jht_company'],
            'jht_employee' => $_POST['jht_employee'] ?? $current['jht_employee'],
            'jp_company'   => $_POST['jp_company'] ?? $current['jp_company'],
            'jp_employee'  => $_POST['jp_employee'] ?? $current['jp_employee'],
            'jp_max'       => $_POST['jp_max'] ?? $current['jp_max'],
            'jkk'          => $_POST['jkk'] ?? $current['jkk'],
            'jkm'          => $_POST['jkm'] ?? $current['jkm'],
        ];

        $result = $this->salary->savePayrollSettings($input);
        if (!$result['saved']) {
            flash('error', reset($result['errors']) ?: 'Pengaturan payroll gagal disimpan.');
            back('/settings?tab=' . (string) ($_POST['_tab'] ?? 'payroll'));
        }

        $policy = $this->salary->settings();
        Audit::log('settings.payroll', 'settings', null, [
            'pph_method' => $policy['pph_method'],
            'bpjs_kes'   => $policy['kes_employee'],
        ]);

        $tab = (string) ($_POST['_tab'] ?? 'payroll');
        flash('success', 'Pengaturan ' . ($tab === 'bpjs' ? 'BPJS' : ($tab === 'pph' ? 'PPh 21' : 'payroll')) . ' disimpan. Perubahan langsung dipakai simulasi dan payroll berikutnya.');
        redirect('/settings?tab=' . $tab);
    }

    /** Pengaturan lanjutan: penandatangan laporan & retensi log audit. */
    public function saveAdvanced(): void
    {
        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->custom(mb_strlen((string) ($_POST['report_signer_hr'] ?? '')) > 120, 'report_signer_hr', 'Nama penandatangan maksimal 120 karakter.');
            $v->custom(mb_strlen((string) ($_POST['report_signer_fin'] ?? '')) > 120, 'report_signer_fin', 'Nama penandatangan maksimal 120 karakter.');
            $retention = (int) ($_POST['audit_retention_days'] ?? 0);
            $v->custom($retention < 30 || $retention > 1825, 'audit_retention_days', 'Retensi log harus antara 30 dan 1825 hari.');
        }, '/settings?tab=advanced');

        Settings::many([
            'report_signer_hr'     => trim((string) ($data['report_signer_hr'] ?? '')),
            'report_signer_fin'    => trim((string) ($data['report_signer_fin'] ?? '')),
            'audit_retention_days' => (int) ($data['audit_retention_days'] ?? 180),
        ], 'general');

        Audit::log('settings.advanced', 'settings', null, [
            'retention' => (int) ($data['audit_retention_days'] ?? 180),
        ]);
        flash('success', 'Pengaturan laporan & audit disimpan.');
        redirect('/settings?tab=advanced');
    }

    /** Dua contoh karyawan (gaji tertinggi & terendah) untuk pratinjau dampak. */
    private function samplePreview(): array
    {
        $rows = $this->salary->simulationSample(50);
        if (!$rows) {
            return [];
        }
        $sorted = $rows;
        usort($sorted, static fn ($a, $b) => (float) $a['gross'] <=> (float) $b['gross']);
        $low = $sorted[0];
        $high = $sorted[count($sorted) - 1];

        return [
            'low'  => $this->salary->preview((int) $low['id']),
            'high' => $this->salary->preview((int) $high['id']),
        ];
    }
}
