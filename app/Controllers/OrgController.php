<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\EmployeeCode;
use App\Core\Settings;
use App\Core\Upload;
use App\Core\Validator;
use App\Models\DashboardModel;
use App\Models\OrgModel;

final class OrgController extends Controller
{
    private OrgModel $org;
    private DashboardModel $dashboard;

    public function __construct()
    {
        $this->org = new OrgModel();
        $this->dashboard = new DashboardModel();
    }

    /* =====================================================================
     | Unit Organisasi & Format ID
     |===================================================================*/
    public function index(): void
    {
        $unitFilter = (int) ($_GET['unit'] ?? 0);
        $tab = (string) ($_GET['tab'] ?? 'units');
        $tab = in_array($tab, ['units', 'departments', 'positions', 'id-format'], true) ? $tab : 'units';
        $editId = (int) ($_GET['edit'] ?? 0);
        $units = $this->org->units();

        $editing = null;
        if ($editId) {
            $editing = match ($tab) {
                'units'       => $this->org->findUnit($editId),
                'departments' => $this->org->findDepartment($editId),
                'positions'   => $this->org->findPosition($editId),
                default       => null,
            };
        }

        $this->view('org/index', [
            'title'      => 'Unit Organisasi & Format ID',
            'pageTitle'  => 'Unit Organisasi',
            'tab'        => $tab,
            'editing'    => $editing,
            'units'      => $units,
            'departments'=> $this->org->departments($unitFilter ?: null),
            'positions'  => $this->org->positions(),
            'stats'      => $this->org->stats(),
            'employees'  => $this->org->employeeOptions(),
            'unitFilter' => $unitFilter,
            'badges'     => $this->dashboard->badges($this->employee() ?? []),
            'idFormat'   => [
                'format'  => EmployeeCode::format(),
                'prefix'  => EmployeeCode::companyPrefix(),
                'scope'   => EmployeeCode::scope(),
                'preview' => $this->org->idFormatPreview(),
            ],
        ] + $this->withInput());
    }

    public function storeBusinessUnit(): void
    {
        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('name', 'Nama business unit')
              ->required('code', 'Kode unit')
              ->numeric('latitude', 'Latitude')
              ->numeric('longitude', 'Longitude')
              ->numeric('geofence_radius', 'Radius geofence')
              ->numeric('payroll_cutoff_day', 'Tanggal cut-off')
              ->numeric('payroll_pay_day', 'Tanggal pembayaran');
        }, '/organisation?tab=units');

        $code = strtoupper(trim((string) $data['code']));
        if (Database::value('SELECT id FROM business_units WHERE UPPER(code) = ?', [$code])) {
            flash('error', 'Kode unit "' . $code . '" sudah digunakan.');
            back('/organisation?tab=units');
        }

        $logo = Upload::image('logo', 'units', 400);
        if ($logo['error']) {
            flash('error', $logo['error']);
            back('/organisation?tab=units');
        }

        $id = Database::insert('business_units', [
            'company_id'         => 1,
            'code'               => $code,
            'name'               => trim((string) $data['name']),
            'type'               => trim((string) ($data['type'] ?? '')) ?: 'Branch Office',
            'logo'               => $logo['path'],
            'address'            => trim((string) ($data['address'] ?? '')) ?: null,
            'city'               => trim((string) ($data['city'] ?? '')) ?: null,
            'phone'              => trim((string) ($data['phone'] ?? '')) ?: null,
            'latitude'           => $data['latitude'] !== '' ? (float) $data['latitude'] : null,
            'longitude'          => $data['longitude'] !== '' ? (float) $data['longitude'] : null,
            'geofence_radius'    => (int) ($data['geofence_radius'] ?? 50),
            'payroll_cutoff_day' => (int) ($data['payroll_cutoff_day'] ?? 25),
            'payroll_pay_day'    => (int) ($data['payroll_pay_day'] ?? 28),
            'timezone'           => 'Asia/Jakarta',
            'is_active'          => 1,
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        EmployeeCode::resetSequence($id);
        Audit::log('org.unit.create', 'business_unit', $id, ['code' => $code]);
        flash('success', 'Business unit ' . $data['name'] . ' berhasil ditambahkan.');
        redirect('/organisation?tab=units');
    }

    public function updateBusinessUnit(string $id): void
    {
        $unit = $this->org->findUnit((int) $id);
        if (!$unit) {
            $this->abort(404, 'Business unit tidak ditemukan.');
        }

        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('name', 'Nama business unit')
              ->required('code', 'Kode unit')
              ->numeric('geofence_radius', 'Radius geofence');
        }, '/organisation?tab=units');

        $code = strtoupper(trim((string) $data['code']));
        $duplicate = Database::value('SELECT id FROM business_units WHERE UPPER(code) = ? AND id <> ?', [$code, (int) $id]);
        if ($duplicate) {
            flash('error', 'Kode unit "' . $code . '" sudah digunakan unit lain.');
            back('/organisation?tab=units');
        }

        $update = [
            'code'               => $code,
            'name'               => trim((string) $data['name']),
            'type'               => trim((string) ($data['type'] ?? '')) ?: $unit['type'],
            'address'            => trim((string) ($data['address'] ?? '')) ?: null,
            'city'               => trim((string) ($data['city'] ?? '')) ?: null,
            'phone'              => trim((string) ($data['phone'] ?? '')) ?: null,
            'latitude'           => ($data['latitude'] ?? '') !== '' ? (float) $data['latitude'] : null,
            'longitude'          => ($data['longitude'] ?? '') !== '' ? (float) $data['longitude'] : null,
            'geofence_radius'    => (int) ($data['geofence_radius'] ?? 50),
            'payroll_cutoff_day' => (int) ($data['payroll_cutoff_day'] ?? $unit['payroll_cutoff_day']),
            'payroll_pay_day'    => (int) ($data['payroll_pay_day'] ?? $unit['payroll_pay_day']),
            'is_active'          => isset($data['is_active']) ? 1 : 0,
            'updated_at'         => date('Y-m-d H:i:s'),
        ];

        $logo = Upload::image('logo', 'units', 400);
        if ($logo['error']) {
            flash('error', $logo['error']);
            back('/organisation?tab=units');
        }
        if ($logo['path']) {
            Upload::delete($unit['logo']);
            $update['logo'] = $logo['path'];
        }
        if (!empty($data['remove_logo']) && $unit['logo']) {
            Upload::delete($unit['logo']);
            $update['logo'] = null;
        }

        Database::update('business_units', $update, 'id = ?', [(int) $id]);
        Audit::log('org.unit.update', 'business_unit', (int) $id, ['code' => $code]);
        flash('success', 'Business unit ' . $data['name'] . ' diperbarui.');
        redirect('/organisation?tab=units');
    }

    public function destroyBusinessUnit(string $id): void
    {
        $unit = $this->org->findUnit((int) $id);
        if (!$unit) {
            $this->abort(404, 'Business unit tidak ditemukan.');
        }
        $deps = $this->org->unitHasDependents((int) $id);
        if ($deps['departments'] > 0 || $deps['employees'] > 0) {
            flash('error', 'Unit tidak dapat dihapus: masih memiliki ' . $deps['departments'] . ' departemen dan '
                . $deps['employees'] . ' karyawan. Nonaktifkan unit atau pindahkan data terlebih dahulu.');
            back('/organisation?tab=units');
        }

        Upload::delete($unit['logo']);
        Database::delete('business_units', 'id = ?', [(int) $id]);
        Database::run('DELETE FROM id_sequences WHERE scope_key = ?', ['employee:BU:' . (int) $id]);
        Audit::log('org.unit.delete', 'business_unit', (int) $id, ['code' => $unit['code']]);
        flash('success', 'Business unit ' . $unit['name'] . ' dihapus.');
        redirect('/organisation?tab=units');
    }

    /* ----------------------------------------------------------- department */
    public function storeDepartment(): void
    {
        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('name', 'Nama departemen')->required('code', 'Kode departemen')->required('business_unit_id', 'Business unit');
        }, '/organisation?tab=departments');

        $unitId = (int) $data['business_unit_id'];
        $code = strtoupper(trim((string) $data['code']));
        if (Database::value('SELECT id FROM departments WHERE UPPER(code) = ? AND business_unit_id = ?', [$code, $unitId])) {
            flash('error', 'Kode departemen "' . $code . '" sudah ada pada unit tersebut.');
            back('/organisation?tab=departments');
        }

        $id = Database::insert('departments', [
            'business_unit_id'  => $unitId,
            'code'              => $code,
            'name'              => trim((string) $data['name']),
            'head_employee_id'  => !empty($data['head_employee_id']) ? (int) $data['head_employee_id'] : null,
            'is_active'         => 1,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        Audit::log('org.department.create', 'department', $id, ['code' => $code]);
        flash('success', 'Departemen ' . $data['name'] . ' berhasil ditambahkan.');
        redirect('/organisation?tab=departments&unit=' . $unitId);
    }

    public function updateDepartment(string $id): void
    {
        $dept = $this->org->findDepartment((int) $id);
        if (!$dept) {
            $this->abort(404, 'Departemen tidak ditemukan.');
        }

        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('name', 'Nama departemen')->required('code', 'Kode departemen');
        }, '/organisation?tab=departments');

        $code = strtoupper(trim((string) $data['code']));
        $unitId = (int) ($data['business_unit_id'] ?? $dept['business_unit_id']);
        $duplicate = Database::value(
            'SELECT id FROM departments WHERE UPPER(code) = ? AND business_unit_id = ? AND id <> ?',
            [$code, $unitId, (int) $id]
        );
        if ($duplicate) {
            flash('error', 'Kode departemen "' . $code . '" sudah ada pada unit tersebut.');
            back('/organisation?tab=departments');
        }

        Database::update('departments', [
            'business_unit_id' => $unitId,
            'code'             => $code,
            'name'             => trim((string) $data['name']),
            'head_employee_id' => !empty($data['head_employee_id']) ? (int) $data['head_employee_id'] : null,
            'is_active'        => isset($data['is_active']) ? 1 : 0,
            'updated_at'       => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int) $id]);

        Audit::log('org.department.update', 'department', (int) $id);
        flash('success', 'Departemen diperbarui.');
        redirect('/organisation?tab=departments');
    }

    public function destroyDepartment(string $id): void
    {
        $dept = $this->org->findDepartment((int) $id);
        if (!$dept) {
            $this->abort(404, 'Departemen tidak ditemukan.');
        }
        $deps = $this->org->departmentHasDependents((int) $id);
        if ($deps['positions'] > 0 || $deps['employees'] > 0) {
            flash('error', 'Departemen tidak dapat dihapus: masih memiliki ' . $deps['positions'] . ' posisi dan '
                . $deps['employees'] . ' karyawan.');
            back('/organisation?tab=departments');
        }
        Database::delete('departments', 'id = ?', [(int) $id]);
        Audit::log('org.department.delete', 'department', (int) $id);
        flash('success', 'Departemen ' . $dept['name'] . ' dihapus.');
        redirect('/organisation?tab=departments');
    }

    /* ------------------------------------------------------------- position */
    public function storePosition(): void
    {
        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('name', 'Nama posisi')->numeric('basic_salary', 'Gaji pokok standar');
        }, '/organisation?tab=positions');

        $id = Database::insert('positions', [
            'department_id' => !empty($data['department_id']) ? (int) $data['department_id'] : null,
            'code'          => trim((string) ($data['code'] ?? '')) ?: 'POS-' . strtoupper(bin2hex(random_bytes(2))),
            'name'          => trim((string) $data['name']),
            'level'         => trim((string) ($data['level'] ?? '')) ?: null,
            'basic_salary'  => (float) ($data['basic_salary'] ?? 0),
            'is_active'     => 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        Audit::log('org.position.create', 'position', $id);
        flash('success', 'Posisi ' . $data['name'] . ' berhasil ditambahkan.');
        redirect('/organisation?tab=positions');
    }

    public function updatePosition(string $id): void
    {
        $position = $this->org->findPosition((int) $id);
        if (!$position) {
            $this->abort(404, 'Posisi tidak ditemukan.');
        }
        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('name', 'Nama posisi')->numeric('basic_salary', 'Gaji pokok standar');
        }, '/organisation?tab=positions');

        Database::update('positions', [
            'department_id' => !empty($data['department_id']) ? (int) $data['department_id'] : null,
            'code'          => trim((string) ($data['code'] ?? '')) ?: $position['code'],
            'name'          => trim((string) $data['name']),
            'level'         => trim((string) ($data['level'] ?? '')) ?: null,
            'basic_salary'  => (float) ($data['basic_salary'] ?? 0),
            'is_active'     => isset($data['is_active']) ? 1 : 0,
            'updated_at'    => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int) $id]);

        Audit::log('org.position.update', 'position', (int) $id);
        flash('success', 'Posisi diperbarui.');
        redirect('/organisation?tab=positions');
    }

    public function destroyPosition(string $id): void
    {
        $position = $this->org->findPosition((int) $id);
        if (!$position) {
            $this->abort(404, 'Posisi tidak ditemukan.');
        }
        $deps = $this->org->positionHasDependents((int) $id);
        if ($deps['employees'] > 0) {
            flash('error', 'Posisi tidak dapat dihapus: masih digunakan oleh ' . $deps['employees'] . ' karyawan.');
            back('/organisation?tab=positions');
        }
        Database::delete('positions', 'id = ?', [(int) $id]);
        Audit::log('org.position.delete', 'position', (int) $id);
        flash('success', 'Posisi ' . $position['name'] . ' dihapus.');
        redirect('/organisation?tab=positions');
    }

    /* --------------------------------------------------------- ID employee */
    public function saveIdFormat(): void
    {
        $format = strtoupper(trim((string) input('employee_id_format', '')));
        $prefix = strtoupper(trim((string) input('employee_id_company_prefix', 'ARG')));
        $scope = strtoupper((string) input('employee_id_scope', 'BU'));

        $errors = EmployeeCode::validate($format);
        if ($errors) {
            flash('error', $errors[0]);
            back('/organisation?tab=id-format');
        }
        if (!in_array($scope, ['BU', 'GLOBAL'], true)) {
            $scope = 'BU';
        }

        Settings::set('employee_id_format', $format, 'company');
        Settings::set('employee_id_company_prefix', $prefix !== '' ? $prefix : 'ARG', 'company');
        Settings::set('employee_id_scope', $scope, 'company');

        // Format changed: reset counters so the next generated ID follows the new pattern.
        Database::run('DELETE FROM id_sequences');
        Audit::log('org.idformat.update', 'setting', null, ['format' => $format, 'scope' => $scope]);

        flash('success', 'Format ID karyawan disimpan. Nomor urut direset mengikuti format baru.');
        redirect('/organisation?tab=id-format');
    }

    /* =====================================================================
     | Pengaturan perusahaan
     |===================================================================*/
    public function company(): void
    {
        $company = Database::one('SELECT * FROM companies ORDER BY id LIMIT 1') ?? [];
        $units = $this->org->units();

        $this->view('org/company', [
            'title'     => 'Pengaturan Perusahaan',
            'pageTitle' => 'Pengaturan Perusahaan',
            'company'   => $company,
            'units'     => $units,
            'stats'     => $this->org->stats(),
            'badges'    => $this->dashboard->badges($this->employee() ?? []),
        ] + $this->withInput());
    }

    public function saveCompany(): void
    {
        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('name', 'Nama perusahaan')
              ->required('email', 'Email perusahaan')
              ->email('email', 'Email perusahaan');
        }, '/company');

        $company = Database::one('SELECT * FROM companies ORDER BY id LIMIT 1');
        $record = [
            'name'        => trim((string) $data['name']),
            'legal_name'  => trim((string) ($data['legal_name'] ?? '')) ?: trim((string) $data['name']),
            'address'     => trim((string) ($data['address'] ?? '')) ?: null,
            'city'        => trim((string) ($data['city'] ?? '')) ?: null,
            'province'    => trim((string) ($data['province'] ?? '')) ?: null,
            'postal_code' => trim((string) ($data['postal_code'] ?? '')) ?: null,
            'phone'       => trim((string) ($data['phone'] ?? '')) ?: null,
            'email'       => strtolower(trim((string) $data['email'])),
            'website'     => trim((string) ($data['website'] ?? '')) ?: null,
            'npwp'        => trim((string) ($data['npwp'] ?? '')) ?: null,
            'timezone'    => (string) ($data['timezone'] ?? 'Asia/Jakarta'),
            'currency'    => strtoupper((string) ($data['currency'] ?? 'IDR')),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        $logo = Upload::image('logo', 'company', 512);
        if ($logo['error']) {
            flash('error', $logo['error']);
            back('/company');
        }
        if ($logo['path']) {
            if ($company && $company['logo']) {
                Upload::delete($company['logo']);
            }
            $record['logo'] = $logo['path'];
        }
        if (!empty($data['remove_logo']) && $company && $company['logo']) {
            Upload::delete($company['logo']);
            $record['logo'] = null;
        }

        if ($company) {
            Database::update('companies', $record, 'id = ?', [(int) $company['id']]);
        } else {
            $record['created_at'] = date('Y-m-d H:i:s');
            Database::insert('companies', $record);
        }

        // Company identity is also surfaced through settings (login screen, PDF header).
        Settings::set('company_name', $record['name'], 'company');
        Settings::set('company_legal_name', $record['legal_name'], 'company');
        Settings::set('company_address', trim(($record['address'] ?? '') . ', ' . ($record['city'] ?? ''), ', '), 'company');
        Settings::set('company_phone', (string) ($record['phone'] ?? ''), 'company');
        Settings::set('company_email', $record['email'], 'company');
        Settings::set('company_website', (string) ($record['website'] ?? ''), 'company');
        Settings::set('company_npwp', (string) ($record['npwp'] ?? ''), 'company');
        Settings::set('timezone', $record['timezone'], 'company');
        Settings::set('currency', $record['currency'], 'company');
        if (array_key_exists('logo', $record)) {
            Settings::set('company_logo', (string) $record['logo'], 'company');
        }

        Audit::log('company.update', 'company', $company['id'] ?? null);
        flash('success', 'Pengaturan perusahaan berhasil disimpan.');
        redirect('/company');
    }
}
