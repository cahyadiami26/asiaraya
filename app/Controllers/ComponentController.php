<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Paginator;
use App\Core\Validator;
use App\Models\OrgModel;
use App\Models\SalaryModel;

/**
 * Komponen gaji: master komponen (tunjangan/potongan), penugasan massal ke
 * karyawan, dan simulasi take-home pay (BPJS + PPh 21).
 */
final class ComponentController extends Controller
{
    private SalaryModel $salary;
    private OrgModel $org;

    public function __construct()
    {
        $this->salary = new SalaryModel();
        $this->org = new OrgModel();
    }

    public function index(): void
    {
        $filters = [
            'component'  => (int) ($_GET['component'] ?? 0),
            'unit'       => (int) ($_GET['unit'] ?? 0),
            'department' => (int) ($_GET['department'] ?? 0),
            'employee'   => (int) ($_GET['employee'] ?? 0),
            'active'     => isset($_GET['active']) && $_GET['active'] !== '' ? (int) $_GET['active'] : '',
            'q'          => trim((string) ($_GET['q'] ?? '')),
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $total = $this->salary->countAssignments($filters);
        $paginator = new Paginator($total, $perPage, min($page, max(1, (int) ceil($total / $perPage))));

        $editId = (int) ($_GET['edit'] ?? 0);
        $editAssign = (int) ($_GET['assignee'] ?? 0);

        $this->view('components/index', [
            'title'       => 'Komponen Gaji',
            'pageTitle'   => 'Komponen Gaji',
            'components'  => $this->salary->components(),
            'assignments' => $this->salary->assignments($filters, $perPage, $paginator->offset()),
            'paginator'   => $paginator,
            'filters'     => $filters,
            'stats'       => $this->salary->stats(),
            'coverage'    => $this->salary->coverageByUnit(),
            'sample'      => $this->salary->simulationSample(10),
            'settings'    => $this->salary->settings(),
            'units'       => $this->org->units(),
            'departments' => $this->org->departments(),
            'employees'   => $this->salary->employeeOptions(),
            'editing'     => $editId ? $this->salary->findComponent($editId) : null,
            'editingAssignment' => $editAssign
                ? Database::one(
                    "SELECT ec.*, sc.name AS component_name, sc.code, e.full_name
                     FROM employee_components ec
                     JOIN salary_components sc ON sc.id = ec.component_id
                     JOIN employees e ON e.id = ec.employee_id
                     WHERE ec.id = ?",
                    [$editAssign]
                )
                : null,
            'calcTypes'    => SalaryModel::CALC_TYPES,
            'percentBases' => SalaryModel::PERCENT_BASES,
        ] + $this->withInput());
    }

    /* ------------------------------------------------------------- components */

    public function store(): void
    {
        $data = $this->validateComponent();

        if ($this->salary->componentCodeExists((string) $data['code'])) {
            flash('error', 'Kode komponen "' . $data['code'] . '" sudah digunakan.');
            back('/components');
        }

        $data['sort_order'] = $this->salary->maxSortOrder() + 1;
        $id = $this->salary->createComponent($data);
        Audit::log('component.create', 'salary_component', $id, ['code' => $data['code']]);

        $applyDefaults = (int) ($_POST['apply_default'] ?? 0) === 1;
        $message = 'Komponen ' . $data['name'] . ' ditambahkan.';
        if ($applyDefaults) {
            $result = $this->salary->applyDefaults();
            $message .= ' Ditugaskan ke ' . $result['created'] . ' karyawan aktif.';
        }
        flash('success', $message);
        redirect('/components');
    }

    public function update(string $id): void
    {
        $component = $this->salary->findComponent((int) $id);
        if (!$component) {
            $this->abort(404, 'Komponen gaji tidak ditemukan.');
        }

        $data = $this->validateComponent();
        if ($this->salary->componentCodeExists((string) $data['code'], (int) $id)) {
            flash('error', 'Kode komponen "' . $data['code'] . '" sudah dipakai komponen lain.');
            back('/components');
        }

        $data['sort_order'] = (int) ($_POST['sort_order'] ?? $component['sort_order']);
        $this->salary->updateComponent((int) $id, $data);
        Audit::log('component.update', 'salary_component', (int) $id, ['code' => $data['code']]);

        $sync = (int) ($_POST['sync_defaults'] ?? 0) === 1;
        if ($sync && (int) $data['apply_default'] === 1) {
            $result = $this->salary->applyDefaults();
            flash('success', 'Komponen ' . $data['name'] . ' diperbarui dan ditugaskan ke ' . $result['created'] . ' karyawan.');
            redirect('/components');
        }

        flash('success', 'Komponen ' . $data['name'] . ' diperbarui.');
        redirect('/components');
    }

    public function destroy(string $id): void
    {
        $component = $this->salary->findComponent((int) $id);
        if (!$component) {
            $this->abort(404, 'Komponen gaji tidak ditemukan.');
        }

        if (in_array(strtoupper((string) $component['code']), SalaryModel::LOCKED_CODES, true)) {
            flash('error', 'Komponen sistem "' . $component['name'] . '" tidak dapat dihapus. Nonaktifkan bila tidak dipakai.');
            back('/components');
        }

        $usage = $this->salary->componentUsage((int) $id);
        if ($usage['assignments'] > 0) {
            flash('error', 'Komponen "' . $component['name'] . '" masih ditugaskan ke ' . $usage['assignments']
                . ' karyawan. Lepas penugasan atau nonaktifkan komponen terlebih dahulu.');
            back('/components');
        }

        $this->salary->deleteComponent((int) $id);
        Audit::log('component.delete', 'salary_component', (int) $id, ['code' => $component['code']]);
        flash('success', 'Komponen ' . $component['name'] . ' dihapus.');
        redirect('/components');
    }

    private function validateComponent(): array
    {
        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('code', 'Kode komponen')
                ->required('name', 'Nama komponen')
                ->required('type', 'Jenis komponen')
                ->in('type', 'Jenis komponen', ['BENEFIT', 'DEDUCTION'])
                ->required('calc_type', 'Tipe perhitungan')
                ->in('calc_type', 'Tipe perhitungan', ['FIXED', 'PERCENT', 'PRORATE'])
                ->numeric('amount', 'Nominal / persentase', 0)
                ->custom(mb_strlen((string) ($_POST['code'] ?? '')) > 30, 'code', 'Kode maksimal 30 karakter.')
                ->custom(mb_strlen((string) ($_POST['name'] ?? '')) > 120, 'name', 'Nama maksimal 120 karakter.');
        }, '/components');

        $calcType = strtoupper((string) $data['calc_type']);
        $amount = (float) ($data['amount'] ?? 0);
        if ($calcType === 'PERCENT' && $amount > 100) {
            flash('error', 'Persentase komponen tidak boleh melebihi 100%.');
            back('/components');
        }

        return [
            'code'          => strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', (string) $data['code']) ?? ''),
            'name'          => substr(trim((string) $data['name']), 0, 120),
            'type'          => strtoupper((string) $data['type']),
            'calc_type'     => $calcType,
            'amount'        => $amount,
            'percent_base'  => in_array($_POST['percent_base'] ?? '', ['BASIC', 'GROSS'], true) ? (string) $_POST['percent_base'] : 'BASIC',
            'taxable'       => (int) ($_POST['taxable'] ?? 0),
            'bpjs_base'     => (int) ($_POST['bpjs_base'] ?? 0),
            'apply_default' => (int) ($_POST['apply_default'] ?? 0),
            'is_active'     => (int) ($_POST['is_active'] ?? 0),
        ];
    }

    /* ------------------------------------------------------------ assignments */

    public function assign(): void
    {
        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('component_id', 'Komponen');
            $v->date('effective_date', 'Tanggal berlaku');
            $v->date('end_date', 'Tanggal berakhir');
            $v->numeric('amount_override', 'Nilai override', 0);
        }, '/components');

        $componentId = (int) $data['component_id'];
        $component = $this->salary->findComponent($componentId);
        if (!$component) {
            flash('error', 'Komponen gaji tidak ditemukan.');
            back('/components');
        }

        $scope = strtolower((string) ($_POST['scope'] ?? 'selected'));
        $selected = array_map('intval', (array) ($_POST['employee_ids'] ?? []));
        $unitId = (int) ($_POST['unit'] ?? 0);
        $departmentId = (int) ($_POST['department'] ?? 0);

        if ($scope === 'selected' && !$selected) {
            flash('error', 'Pilih minimal satu karyawan untuk penugasan komponen.');
            back('/components');
        }
        if ($scope === 'unit' && $unitId <= 0) {
            flash('error', 'Pilih unit terlebih dahulu.');
            back('/components');
        }
        if ($scope === 'department' && $departmentId <= 0) {
            flash('error', 'Pilih departemen terlebih dahulu.');
            back('/components');
        }

        if ($scope !== 'selected') {
            $selected = array_map(
                static fn ($row) => (int) $row['id'],
                $this->salary->employeeOptions($unitId ?: null, $departmentId ?: null)
            );
        }

        $override = ($data['amount_override'] ?? '') !== '' ? (float) $data['amount_override'] : null;
        $result = $this->salary->assignComponent(
            $componentId,
            $selected,
            $override,
            (string) ($data['effective_date'] ?? '') ?: date('Y-m-d'),
            (string) ($data['end_date'] ?? '') ?: null
        );

        Audit::log('component.assign', 'employee_component', null, [
            'component' => $component['code'], 'created' => $result['created'], 'updated' => $result['updated'],
        ]);

        $message = $component['name'] . ': ' . $result['created'] . ' penugasan baru, '
            . $result['updated'] . ' diperbarui.';
        if ($result['skipped'] > 0) {
            $message .= ' ' . $result['skipped'] . ' karyawan dilewati (tidak aktif).';
        }
        flash($result['skipped'] > 0 ? 'warning' : 'success', $message);
        redirect('/components');
    }

    public function updateAssignment(string $id): void
    {
        $result = $this->salary->updateAssignment((int) $id, [
            'amount_override' => $_POST['amount_override'] ?? null,
            'effective_date'  => (string) ($_POST['effective_date'] ?? ''),
            'end_date'        => (string) ($_POST['end_date'] ?? ''),
            'is_active'       => (int) ($_POST['is_active'] ?? 1),
        ]);

        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/components');
        }

        Audit::log('component.assignment-update', 'employee_component', (int) $id);
        flash('success', 'Penugasan komponen diperbarui.');
        redirect('/components');
    }

    public function unassign(string $id): void
    {
        $result = $this->salary->removeAssignment((int) $id);
        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/components');
        }
        Audit::log('component.unassign', 'employee_component', (int) $id);
        flash('success', 'Penugasan komponen dilepas.');
        redirect('/components');
    }

    /** Terapkan semua komponen bertanda "default" ke karyawan aktif. */
    public function applyDefaults(): void
    {
        $result = $this->salary->applyDefaults((int) ($_POST['unit'] ?? 0) ?: null);
        if ($result['components'] === 0) {
            flash('error', 'Belum ada komponen yang ditandai "terapkan otomatis".');
            back('/components');
        }

        Audit::log('component.apply-defaults', 'employee_component', null, [
            'created' => $result['created'], 'updated' => $result['updated'],
        ]);

        $message = $result['created'] . ' penugasan dibuat dari ' . $result['components'] . ' komponen default';
        $message .= ' untuk ' . $result['employees'] . ' karyawan aktif.';
        if ($result['updated'] > 0) {
            $message .= ' ' . $result['updated'] . ' penugasan diperbarui.';
        }
        flash('success', $message);
        redirect('/components');
    }

    /** Pratinjau perhitungan satu karyawan (dipakai panel simulasi & modal). */
    public function simulate(string $id = ''): void
    {
        $employeeId = (int) ($id ?: ($_GET['employee'] ?? 0));
        if ($employeeId <= 0) {
            $this->json(['ok' => false, 'error' => 'Karyawan tidak dipilih.'], 422);
        }

        $preview = $this->salary->preview($employeeId, (string) ($_GET['date'] ?? '') ?: null);
        if (!$preview) {
            $this->json(['ok' => false, 'error' => 'Karyawan tidak ditemukan.'], 404);
        }

        $this->json([
            'ok'   => true,
            'data' => [
                'employee' => [
                    'id'   => (int) $preview['employee']['id'],
                    'name' => (string) $preview['employee']['full_name'],
                    'code' => (string) $preview['employee']['employee_code'],
                    'tax_status' => (string) $preview['employee']['tax_status'],
                    'unit' => (string) ($preview['employee']['unit_name'] ?? '-'),
                ],
                'basic'    => $preview['basic'],
                'benefits' => $preview['benefits'],
                'deductions' => $preview['deductions'],
                'gross'    => $preview['gross'],
                'bpjs'     => $preview['bpjs'],
                'pph'      => $preview['pph'],
                'net'      => $preview['net'],
                'employee_deductions' => $preview['employee_deductions'],
                'employer_cost' => $preview['employer_cost'],
                'taxable_base' => $preview['taxable_base'],
            ],
        ]);
    }
}
