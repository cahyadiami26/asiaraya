<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Paginator;
use App\Core\Validator;
use App\Models\DashboardModel;
use App\Models\EmployeeModel;
use App\Models\OrgModel;
use App\Models\ScheduleModel;

/**
 * Penjadwalan karyawan (matrix per hari), penugasan massal per unit/departemen,
 * serta pengajuan & persetujuan perubahan jadwal.
 */
final class ScheduleController extends Controller
{
    private ScheduleModel $schedules;
    private OrgModel $org;
    private EmployeeModel $employees;
    private DashboardModel $dashboard;

    public function __construct()
    {
        $this->schedules = new ScheduleModel();
        $this->org = new OrgModel();
        $this->employees = new EmployeeModel();
        $this->dashboard = new DashboardModel();
    }

    /* =====================================================================
     | Penjadwalan
     |===================================================================*/
    public function index(): void
    {
        [$start, $end] = $this->windowFromRequest();

        $filters = [
            'q'          => trim((string) ($_GET['q'] ?? '')),
            'unit'       => (int) ($_GET['unit'] ?? 0),
            'department' => (int) ($_GET['department'] ?? 0),
            'employee'   => (int) ($_GET['employee'] ?? 0),
        ];

        $rowLimit = (int) ($_GET['rows'] ?? 40);
        $rowLimit = in_array($rowLimit, [25, 40, 80, 200], true) ? $rowLimit : 40;
        $matrix = $this->schedules->matrix($start, $end, $filters, $rowLimit);

        $perPage = 10;
        $listFilters = ['unit' => $filters['unit'] ?: null, 'department' => $filters['department'] ?: null, 'q' => $filters['q'] ?: null, 'upcoming' => 1];
        $paginator = Paginator::fromRequest($this->schedules->countAssignments($listFilters), $perPage);

        $this->view('schedules/index', [
            'title'       => 'Penjadwalan Karyawan',
            'pageTitle'   => 'Penjadwalan Karyawan',
            'matrix'      => $matrix,
            'start'       => $start,
            'end'         => $end,
            'filters'     => $filters,
            'units'       => $this->org->units(),
            'departments' => $this->employees->departmentsOf($filters['unit'] ?: null),
            'shifts'      => $this->schedules->shiftOptions(),
            'assignments' => $this->schedules->assignments($listFilters, $perPage, $paginator->offset()),
            'assignTotal' => $paginator->total,
            'paginator'   => $paginator,
            'stats'       => $this->schedules->stats(),
            'badges'      => $this->dashboard->badges($this->employee() ?? []),
            'rowLimit'    => $rowLimit,
            'prefill'     => [
                'employee' => $filters['employee'] ?: null,
                'shift'    => (int) ($_GET['shift'] ?? 0),
                'date'     => (string) ($_GET['date'] ?? ''),
            ],
        ] + $this->withInput());
    }

    /** POST /schedules/assign — bulk assignment (employee / unit / department / all). */
    public function assign(): void
    {
        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('shift_id', 'Shift')
              ->required('start_date', 'Tanggal mulai')
              ->required('end_date', 'Tanggal akhir')
              ->date('start_date', 'Tanggal mulai')
              ->date('end_date', 'Tanggal akhir')
              ->in('target_mode', 'Target', ['employee', 'unit', 'department', 'all']);
        }, '/schedules');

        $shift = $this->schedules->findShift((int) $data['shift_id']);
        if (!$shift) {
            flash('error', 'Shift yang dipilih tidak ditemukan.');
            back('/schedules');
        }

        $from = (string) $data['start_date'];
        $to = (string) $data['end_date'];
        if ($from > $to) {
            flash('error', 'Tanggal akhir tidak boleh lebih awal dari tanggal mulai.');
            back('/schedules');
        }
        if ((strtotime($to) - strtotime($from)) > 366 * 86400) {
            flash('error', 'Rentang penjadwalan maksimal satu tahun.');
            back('/schedules');
        }

        $targets = $this->resolveTargets((string) $data['target_mode'], $data);
        if ($targets === []) {
            flash('error', 'Tidak ada karyawan aktif yang cocok dengan target penjadwalan.');
            back('/schedules');
        }

        $skipNonWorking = !empty($data['skip_non_working']);
        $source = (string) ($data['source'] ?? 'ASSIGNED');
        $note = trim((string) ($data['note'] ?? ''));

        $employees = 0;
        $slicesCreated = 0;
        $failed = [];
        foreach ($targets as $employeeId) {
            $result = $this->schedules->assign(
                (int) $employeeId,
                (int) $shift['id'],
                $from,
                $to,
                in_array($source, ['ASSIGNED', 'IMPORT', 'TEMPLATE'], true) ? $source : 'ASSIGNED',
                $note !== '' ? substr($note, 0, 255) : null,
                Auth::id(),
                $skipNonWorking
            );
            if ($result['error']) {
                $failed[] = $employeeId;
                continue;
            }
            $employees++;
            $slicesCreated += $result['created'];
        }

        Audit::log('schedule.assign', 'schedule', null, [
            'shift' => $shift['code'],
            'from'  => $from,
            'to'    => $to,
            'employees' => $employees,
        ]);

        if ($employees === 0) {
            flash('error', 'Penjadwalan gagal: ' . ($failed ? count($failed) . ' karyawan tidak dapat dijadwalkan.' : 'rentang tidak memiliki hari kerja.'));
            back('/schedules');
        }

        flash('success', 'Jadwal ' . $shift['name'] . ' diterapkan ke ' . $employees . ' karyawan ('
            . tanggal($from) . ' – ' . tanggal($to) . '). ');
        back('/schedules?start=' . $from . '&end=' . $to);
    }

    /** @return int[] */
    private function resolveTargets(string $mode, array $data): array
    {
        if ($mode === 'employee') {
            $ids = array_values(array_filter(array_map('intval', (array) ($data['employee_ids'] ?? []))));
            return array_slice(array_unique($ids), 0, 200);
        }

        $clauses = ["flag = 'ACTIVE'"];
        $params = [];
        if ($mode === 'unit' && !empty($data['unit_id'])) {
            $clauses[] = 'business_unit_id = ?';
            $params[] = (int) $data['unit_id'];
        }
        if ($mode === 'department' && !empty($data['department_id'])) {
            $clauses[] = 'department_id = ?';
            $params[] = (int) $data['department_id'];
        }

        $rows = Database::all(
            'SELECT id FROM employees WHERE ' . implode(' AND ', $clauses) . ' ORDER BY id LIMIT 200',
            $params
        );
        return array_map(static fn ($r) => (int) $r['id'], $rows);
    }

    /** POST /schedules/{id}/delete */
    public function removeAssignment(string $id): void
    {
        $assignment = Database::one(
            'SELECT sc.*, e.full_name, s.name AS shift_name FROM schedules sc
             JOIN employees e ON e.id = sc.employee_id
             JOIN work_shifts s ON s.id = sc.shift_id
             WHERE sc.id = ?',
            [(int) $id]
        );
        if (!$assignment) {
            $this->abort(404, 'Penugasan jadwal tidak ditemukan.');
        }

        $this->schedules->removeAssignment((int) $id);
        Audit::log('schedule.delete', 'schedule', (int) $id, [
            'employee_id' => (int) $assignment['employee_id'],
            'shift'       => $assignment['shift_name'],
            'range'       => $assignment['start_date'] . '..' . $assignment['end_date'],
        ]);
        flash('success', 'Penugasan ' . $assignment['shift_name'] . ' untuk ' . $assignment['full_name']
            . ' (' . tanggal($assignment['start_date']) . ' – ' . tanggal($assignment['end_date']) . ') dihapus.');
        back('/schedules');
    }

    /* =====================================================================
     | Persetujuan perubahan jadwal
     |===================================================================*/
    public function requests(): void
    {
        $status = (string) ($_GET['status'] ?? 'OPEN');
        $filters = [
            'status' => in_array($status, ['OPEN', 'PENDING', 'MANAGER_APPROVED', 'APPROVED', 'REJECTED', 'ALL'], true) ? $status : 'OPEN',
            'q'      => trim((string) ($_GET['q'] ?? '')),
            'unit'   => (int) ($_GET['unit'] ?? 0),
        ];
        if ($filters['status'] === 'ALL') {
            $filters['status'] = '';
        }

        $this->view('schedules/requests', [
            'title'      => 'Persetujuan Perubahan Jadwal',
            'pageTitle'  => 'Perubahan Jadwal',
            'requests'   => $this->schedules->requests($filters, 60),
            'stats'      => $this->schedules->requestStats(),
            'filters'    => $filters,
            'units'      => $this->org->units(),
            'badges'     => $this->dashboard->badges($this->employee() ?? []),
            'canApprove' => static fn (array $request): bool => self::canDecide($request),
        ]);
    }

    /** Manager of the employee at stage 1, HR at stage 2. */
    public static function canDecide(array $request): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        $status = (string) $request['status'];
        if ($status === 'PENDING' && Auth::isManagerOf((int) $request['employee_id'])) {
            return true;
        }
        return $user['role'] === 'HR' && in_array($status, ['PENDING', 'MANAGER_APPROVED'], true)
            && (int) $request['employee_id'] !== (int) $user['id'];
    }

    /* =====================================================================
     | Jadwal saya
     |===================================================================*/
    public function mine(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            $this->abort(401, 'Sesi berakhir.');
        }

        $from = (string) ($_GET['start'] ?? date('Y-m-d', strtotime('-3 days')));
        $to = (string) ($_GET['end'] ?? date('Y-m-d', strtotime('+25 days')));
        if (strtotime($from) === false || strtotime($to) === false || $from > $to) {
            $from = date('Y-m-d', strtotime('-3 days'));
            $to = date('Y-m-d', strtotime('+25 days'));
        }

        $todayShift = $this->schedules->shiftOn((int) $employee['id'], date('Y-m-d'));

        $this->view('schedules/mine', [
            'title'      => 'Jadwal Saya',
            'pageTitle'  => 'Jadwal Saya',
            'days'       => $this->schedules->dayGrid((int) $employee['id'], $from, $to),
            'todayShift' => $todayShift,
            'shifts'     => $this->schedules->shiftOptions(),
            'requests'   => $this->schedules->myRequests((int) $employee['id'], 12),
            'hasOpen'    => $this->schedules->hasOpenRequest((int) $employee['id']),
            'from'       => $from,
            'to'         => $to,
            'employee'   => $employee,
            'badges'     => $this->dashboard->badges($employee),
        ] + $this->withInput());
    }

    /** POST /me/schedule/request */
    public function storeRequest(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            $this->abort(401, 'Sesi berakhir.');
        }

        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('requested_shift_id', 'Shift pengganti')
              ->required('start_date', 'Tanggal mulai')
              ->required('end_date', 'Tanggal akhir')
              ->date('start_date', 'Tanggal mulai')
              ->date('end_date', 'Tanggal akhir');
        }, '/me/schedule');

        $shift = $this->schedules->findShift((int) $data['requested_shift_id']);
        if (!$shift || (int) $shift['is_active'] !== 1) {
            flash('error', 'Shift pengganti tidak valid.');
            back('/me/schedule');
        }

        $from = (string) $data['start_date'];
        $to = (string) $data['end_date'];
        if ($from > $to) {
            flash('error', 'Tanggal akhir tidak boleh lebih awal dari tanggal mulai.');
            back('/me/schedule');
        }
        if ($from < date('Y-m-d')) {
            flash('error', 'Perubahan jadwal hanya dapat diajukan untuk tanggal hari ini atau setelahnya.');
            back('/me/schedule');
        }
        if ($this->schedules->hasOpenRequest((int) $employee['id'])) {
            flash('error', 'Masih ada pengajuan perubahan jadwal yang belum selesai. Tunggu keputusan atasan/HR terlebih dahulu.');
            back('/me/schedule');
        }

        $current = $this->schedules->shiftOn((int) $employee['id'], $from);
        if ($current && (int) $current['shift_id'] === (int) $shift['id']) {
            flash('error', 'Shift yang diajukan sama dengan jadwal Anda saat ini.');
            back('/me/schedule');
        }

        $id = $this->schedules->createRequest(
            (int) $employee['id'],
            (int) $shift['id'],
            $from,
            $to,
            trim((string) ($data['reason'] ?? ''))
        );

        $managerId = $employee['direct_manager_id'] ?? null;
        if ($managerId) {
            Database::insert('notifications', [
                'employee_id' => (int) $managerId,
                'title'       => 'Pengajuan perubahan jadwal',
                'message'     => $employee['full_name'] . ' mengajukan perubahan ke ' . $shift['name']
                    . ' (' . tanggal($from) . ' – ' . tanggal($to) . ').',
                'link'        => '/schedules/requests',
                'type'        => 'info',
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        }

        Audit::log('schedule.request', 'shift_change_request', $id, ['shift' => $shift['code']]);
        flash('success', 'Pengajuan perubahan jadwal dikirim. Menunggu persetujuan atasan langsung'
            . ($managerId ? ' dan HR' : '') . '.');
        redirect('/me/schedule');
    }

    /* --------------------------------------------------------------- helpers */
    /** @return array{0:string,1:string} */
    private function windowFromRequest(): array
    {
        $start = (string) ($_GET['start'] ?? '');
        $end = (string) ($_GET['end'] ?? '');

        $valid = static fn (string $date): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            && strtotime($date) !== false;

        if ($valid($start) && $valid($end) && $start <= $end) {
            $span = (strtotime($end) - strtotime($start)) / 86400;
            if ($span <= 62) {
                return [$start, $end];
            }
        }

        // default: current week (Mon) through next week (Sun)
        $monday = new \DateTimeImmutable('monday this week');
        return [$monday->format('Y-m-d'), $monday->modify('+13 days')->format('Y-m-d')];
    }
}
