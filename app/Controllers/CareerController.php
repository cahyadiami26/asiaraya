<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Paginator;
use App\Core\Validator;
use App\Models\CareerModel;
use App\Models\OrgModel;

/**
 * Riwayat jabatan, mutasi, dan promosi (Batch 19).
 *
 * Pencatatan perubahan sekaligus dapat memperbarui data karyawan (unit,
 * departemen, jabatan, status, gaji pokok) sehingga master kepegawaian selalu
 * konsisten dengan riwayat.
 */
final class CareerController extends Controller
{
    private CareerModel $careers;
    private OrgModel $org;

    public function __construct()
    {
        $this->careers = new CareerModel();
        $this->org = new OrgModel();
    }

    public function index(): void
    {
        $filters = $this->careers->filtersFromRequest();
        $perPage = 20;
        $total = $this->careers->count($filters);
        $paginator = new Paginator($total, $perPage, min(
            max(1, (int) ($_GET['page'] ?? 1)),
            max(1, (int) ceil($total / $perPage))
        ));

        $this->view('careers/index', [
            'title'      => 'Riwayat Jabatan & Mutasi',
            'pageTitle'  => 'Riwayat Jabatan & Mutasi',
            'rows'       => $this->careers->paginate($filters, $perPage, $paginator->offset()),
            'paginator'  => $paginator,
            'filters'    => $filters,
            'stats'      => $this->careers->stats(),
            'types'      => CareerModel::TYPES,
            'years'      => $this->careers->years(),
            'units'      => $this->org->units(),
            'mostChanged' => $this->careers->mostChanged(5),
        ] + $this->withInput());
    }

    public function create(): void
    {
        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        $employee = $employeeId > 0 ? $this->employeeRow($employeeId) : null;

        $this->view('careers/form', [
            'title'       => 'Catat Perubahan Jabatan',
            'pageTitle'   => 'Catat Perubahan Jabatan',
            'employee'    => $employee,
            'types'       => CareerModel::TYPES,
            'units'       => $this->org->units(),
            'departments' => $this->org->departments(),
            'positions'   => $this->org->positions(),
            'statuses'    => EmployeeController::STATUSES,
            'history'     => $employee ? $this->careers->forEmployee((int) $employee['id'], 5) : [],
            'employees'   => $this->employeeOptions(),
        ] + $this->withInput());
    }

    public function store(): void
    {
        $data = $this->validateOrRedirect(function (Validator $v): void {
            $v->required('employee_id', 'Karyawan');
            $v->required('change_type', 'Jenis perubahan');
            $v->in('change_type', 'Jenis perubahan', array_keys(CareerModel::TYPES));
            $v->required('effective_date', 'Tanggal efektif');
            $v->date('effective_date', 'Tanggal efektif');
            $v->date('end_date', 'Tanggal berakhir');
            $v->custom(mb_strlen((string) ($_POST['sk_number'] ?? '')) > 80, 'sk_number', 'Nomor SK maksimal 80 karakter.');
            $v->custom(mb_strlen((string) ($_POST['note'] ?? '')) > 255, 'note', 'Catatan maksimal 255 karakter.');
            $v->numeric('basic_salary', 'Gaji pokok', 0);
        }, '/careers/create');

        $employee = $this->employeeRow((int) $data['employee_id']);
        if (!$employee) {
            flash('error', 'Karyawan tidak ditemukan.');
            redirect('/careers');
        }

        if (!empty($_POST['end_date']) && strtotime((string) $_POST['end_date']) < strtotime((string) $data['effective_date'])) {
            flash('error', 'Tanggal berakhir tidak boleh sebelum tanggal efektif.');
            back('/careers/create?employee_id=' . (int) $employee['id']);
        }

        $apply = !empty($_POST['apply']);

        $id = $this->careers->create(
            [
                'change_type'       => (string) $data['change_type'],
                'effective_date'    => (string) $data['effective_date'],
                'end_date'          => ($data['end_date'] ?? '') ?: null,
                'business_unit_id'  => (int) ($data['business_unit_id'] ?? 0),
                'department_id'     => (int) ($data['department_id'] ?? 0),
                'position_id'       => (int) ($data['position_id'] ?? 0),
                'employment_status' => (string) ($data['employment_status'] ?? ''),
                'basic_salary'      => ($data['basic_salary'] ?? '') !== '' ? $data['basic_salary'] : '',
                'sk_number'         => (string) ($data['sk_number'] ?? ''),
                'note'              => (string) ($data['note'] ?? ''),
            ],
            $employee,
            $this->employeeId(),
            $apply
        );

        Audit::log('career.create', 'employee_job_history', $id, [
            'employee' => $employee['employee_code'],
            'type'     => $data['change_type'],
            'apply'    => $apply,
        ]);

        // Notifikasi ke karyawan terkait.
        try {
            Database::insert('notifications', [
                'employee_id' => (int) $employee['id'],
                'title'       => CareerModel::typeLabel((string) $data['change_type']) . ' tercatat',
                'message'     => 'Berlaku sejak ' . tanggal((string) $data['effective_date'])
                    . (!empty($data['sk_number']) ? ' (SK ' . $data['sk_number'] . ')' : '') . '.',
                'link'        => '/me/career',
                'type'        => 'success',
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // best-effort
        }

        flash('success', 'Riwayat ' . strtolower(CareerModel::typeLabel((string) $data['change_type']))
            . ' untuk ' . $employee['full_name'] . ' tersimpan'
            . ($apply ? ' dan data karyawan diperbarui.' : '.'));
        redirect('/employees/' . (int) $employee['id'] . '#career');
    }

    public function destroy(string $id): void
    {
        $row = $this->careers->find((int) $id);
        if (!$row) {
            flash('error', 'Riwayat tidak ditemukan.');
            redirect('/careers');
        }

        $this->careers->delete((int) $id);
        Audit::log('career.delete', 'employee_job_history', (int) $id, [
            'employee' => $row['employee_code'],
            'type'     => $row['change_type'],
        ]);

        flash('success', 'Satu baris riwayat ' . $row['full_name'] . ' dihapus.');
        redirect('/careers');
    }

    /** Halaman karyawan: riwayat jabatan saya. */
    public function mine(): void
    {
        $employeeId = $this->employeeId();
        $employee = $this->employeeRow($employeeId);

        $this->view('careers/mine', [
            'title'     => 'Riwayat Jabatan Saya',
            'pageTitle' => 'Riwayat Jabatan Saya',
            'employee'  => $employee,
            'history'   => $this->careers->forEmployee($employeeId, 50),
            'types'     => CareerModel::TYPES,
        ] + $this->withInput());
    }

    /* ============================================================= bantuan */

    private function employeeRow(int $id): ?array
    {
        return Database::one(
            'SELECT e.*, bu.name AS unit_name, d.name AS department_name, p.name AS position_name
               FROM employees e
               LEFT JOIN business_units bu ON bu.id = e.business_unit_id
               LEFT JOIN departments d ON d.id = e.department_id
               LEFT JOIN positions p ON p.id = e.position_id
              WHERE e.id = ?',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    private function employeeOptions(): array
    {
        return Database::all(
            "SELECT e.id, e.full_name, e.employee_code, e.flag, e.basic_salary,
                    p.name AS position_name, bu.name AS unit_name
               FROM employees e
               LEFT JOIN positions p ON p.id = e.position_id
               LEFT JOIN business_units bu ON bu.id = e.business_unit_id
              ORDER BY e.flag = 'ACTIVE' DESC, e.full_name"
        );
    }
}
