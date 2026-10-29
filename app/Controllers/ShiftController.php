<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Validator;
use App\Models\DashboardModel;
use App\Models\OrgModel;
use App\Models\ScheduleModel;

/** Shift master data (jam kerja, toleransi, hari kerja). */
final class ShiftController extends Controller
{
    private ScheduleModel $schedules;
    private OrgModel $org;
    private DashboardModel $dashboard;

    public function __construct()
    {
        $this->schedules = new ScheduleModel();
        $this->org = new OrgModel();
        $this->dashboard = new DashboardModel();
    }

    public function index(): void
    {
        $unitFilter = (int) ($_GET['unit'] ?? 0);
        $editId = (int) ($_GET['edit'] ?? 0);

        $this->view('shifts/index', [
            'title'      => 'Jadwal Kerja & Shift',
            'pageTitle'  => 'Jadwal Kerja & Shift',
            'shifts'     => $this->schedules->shifts(['unit' => $unitFilter ?: null]),
            'members'    => $this->membersFor($this->schedules->shifts()),
            'stats'      => $this->schedules->stats(),
            'units'      => $this->org->units(),
            'unitFilter' => $unitFilter,
            'editing'    => $editId ? $this->schedules->findShift($editId) : null,
            'dayLabels'  => ScheduleModel::DAY_LABELS,
            'tolerance'  => $this->schedules->defaultTolerance(),
            'badges'     => $this->dashboard->badges($this->employee() ?? []),
        ] + $this->withInput());
    }

    /** Assignees preview per shift (max 4 avatars per card). */
    private function membersFor(array $shifts): array
    {
        $members = [];
        foreach ($shifts as $shift) {
            $members[(int) $shift['id']] = $this->schedules->shiftMembers((int) $shift['id'], 4);
        }
        return $members;
    }

    public function store(): void
    {
        $data = $this->validate();

        if ($this->schedules->shiftCodeExists((string) $data['code'])) {
            flash('error', 'Kode shift "' . $data['code'] . '" sudah digunakan.');
            back('/shifts');
        }

        $data['is_active'] = 1;
        $id = $this->schedules->createShift($data);
        Audit::log('shift.create', 'work_shift', $id, ['code' => $data['code']]);
        flash('success', 'Shift ' . $data['name'] . ' berhasil ditambahkan.');
        redirect('/shifts');
    }

    public function update(string $id): void
    {
        $shift = $this->schedules->findShift((int) $id);
        if (!$shift) {
            $this->abort(404, 'Shift tidak ditemukan.');
        }

        $data = $this->validate();
        if ($this->schedules->shiftCodeExists((string) $data['code'], (int) $id)) {
            flash('error', 'Kode shift "' . $data['code'] . '" sudah digunakan shift lain.');
            back('/shifts');
        }

        $this->schedules->updateShift((int) $id, $data);
        Audit::log('shift.update', 'work_shift', (int) $id, ['code' => $data['code']]);
        flash('success', 'Shift ' . $data['name'] . ' diperbarui.');
        redirect('/shifts');
    }

    public function destroy(string $id): void
    {
        $shift = $this->schedules->findShift((int) $id);
        if (!$shift) {
            $this->abort(404, 'Shift tidak ditemukan.');
        }

        $usage = $this->schedules->shiftUsage((int) $id);
        if ($usage['schedules'] > 0) {
            flash('error', 'Shift "' . $shift['name'] . '" masih dipakai pada ' . $usage['schedules']
                . ' penugasan jadwal. Nonaktifkan shift alih-alih menghapusnya.');
            back('/shifts');
        }
        if ($usage['requests'] > 0) {
            flash('error', 'Shift "' . $shift['name'] . '" direferensikan oleh ' . $usage['requests'] . ' pengajuan perubahan jadwal.');
            back('/shifts');
        }

        $this->schedules->deleteShift((int) $id);
        Audit::log('shift.delete', 'work_shift', (int) $id, ['code' => $shift['code']]);
        flash('success', 'Shift ' . $shift['name'] . ' dihapus.');
        redirect('/shifts');
    }

    /** Shared validation for create & update. */
    private function validate(): array
    {
        $data = $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('name', 'Nama shift')
              ->required('code', 'Kode shift')
              ->required('check_in_time', 'Jam masuk')
              ->required('check_out_time', 'Jam pulang')
              ->numeric('break_minutes', 'Istirahat (menit)')
              ->numeric('late_tolerance_minutes', 'Toleransi keterlambatan')
              ->in('operational_type', 'Tipe operasional', ['NORMAL', 'SHIFT', 'WFH', 'FIELD']);
        }, '/shifts');

        if (($data['check_in_time'] ?? '') === ($data['check_out_time'] ?? '')) {
            flash('error', 'Jam masuk dan jam pulang tidak boleh sama.');
            back('/shifts');
        }

        return $data;
    }
}
