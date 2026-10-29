<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Paginator;
use App\Core\Settings;
use App\Core\Validator;
use App\Models\EmployeeModel;
use App\Models\OffboardingModel;
use App\Models\OrgModel;

/**
 * Offboarding & exit management.
 *  - /offboarding        : antrean HR, pembuatan pengajuan, export CSV
 *  - /offboarding/{id}   : detail proses, checklist serah terima, exit interview
 *  - /me/offboarding     : portal karyawan (pengunduran diri, status, umpan balik)
 */
final class OffboardingController extends Controller
{
    private OffboardingModel $offboarding;
    private OrgModel $org;

    private EmployeeModel $employees;

    public function __construct()
    {
        $this->offboarding = new OffboardingModel();
        $this->org = new OrgModel();
        $this->employees = new EmployeeModel();
    }

    /* ------------------------------------------------------------ karyawan */

    public function mine(): void
    {
        $employee = (array) $this->employee();
        $employeeId = (int) $employee['id'];
        $isManager = (int) Database::value(
            'SELECT COUNT(*) FROM employees WHERE direct_manager_id = ? AND flag = ?',
            [$employeeId, 'ACTIVE']
        ) > 0;

        $active = $this->offboarding->activeForEmployee($employeeId);

        $this->view('offboarding/mine', [
            'title'        => 'Pengunduran Diri Saya',
            'pageTitle'    => 'Pengunduran Diri Saya',
            'employee'     => $employee,
            'profile'      => $this->employees->find($employeeId) ?? $employee,
            'requests'     => $this->offboarding->employeeRequests($employeeId),
            'active'       => $active,
            'progress'     => $active ? $this->offboarding->progress((int) $active['id']) : ['done' => 0, 'total' => 0, 'pct' => 0],
            'clearance'    => $active ? $this->offboarding->clearance((int) $active['id']) : [],
            'teamRequests' => $isManager ? $this->offboarding->pendingForManager($employeeId) : [],
            'isManager'    => $isManager,
            'types'        => OffboardingModel::TYPES,
            'reasons'      => OffboardingModel::REASONS,
            'statuses'     => OffboardingModel::STATUSES,
            'tones'        => OffboardingModel::STATUS_TONES,
            'typeTones'    => OffboardingModel::TYPE_TONES,
            'recommendations' => OffboardingModel::RECOMMENDATIONS,
            'ownerRoles'   => OffboardingModel::OWNER_ROLES,
            'noticeDays'   => (int) Settings::get('resign_notice_days', 30),
            'colleagues'   => $this->org->employeeOptions(),
        ] + $this->withInput());
    }

    public function submitMine(): void
    {
        $employee = (array) $this->employee();

        $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('last_working_date', 'Hari terakhir kerja')
              ->date('last_working_date', 'Hari terakhir kerja')
              ->required('reason_category', 'Alasan utama')
              ->in('reason_category', 'Alasan utama', array_keys(OffboardingModel::REASONS));
        }, '/me/offboarding');

        $result = $this->offboarding->create([
            'employee_id'       => (int) $employee['id'],
            'type'              => 'RESIGN',
            'reason_category'   => (string) input('reason_category', 'OTHER'),
            'reason'            => (string) input('reason', ''),
            'submit_date'       => date('Y-m-d'),
            'last_working_date' => (string) input('last_working_date'),
            'handover_to'       => (int) input('handover_to', 0),
        ], (int) $employee['id'], false);

        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/me/offboarding');
        }

        Audit::log('offboarding.submit', 'offboarding_request', (int) $result['id'], ['status' => $result['status']]);
        flash('success', 'Pengajuan ' . $result['number'] . ' terkirim. '
            . ($result['status'] === 'SUBMITTED'
                ? 'Menunggu persetujuan atasan langsung.'
                : 'Langsung diteruskan ke HR.'));
        redirect('/me/offboarding');
    }

    public function withdrawMine(string $id): void
    {
        $result = $this->offboarding->withdraw((int) $id, (int) $this->employeeId());
        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('offboarding.withdraw', 'offboarding_request', (int) $id);
            flash('success', 'Pengajuan offboarding dibatalkan.');
        }
        redirect('/me/offboarding');
    }

    public function feedbackMine(string $id): void
    {
        $result = $this->offboarding->saveFeedback((int) $id, (int) $this->employeeId(), [
            'exit_feedback'        => (string) input('exit_feedback', ''),
            'exit_would_recommend' => (string) input('exit_would_recommend', ''),
        ]);
        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('offboarding.feedback', 'offboarding_request', (int) $id);
            flash('success', 'Umpan balik keluar tersimpan. Terima kasih.');
        }
        redirect('/me/offboarding');
    }

    /* ---------------------------------------------------------------- HR */

    public function index(): void
    {
        $this->requireRole('HR');

        $filters = [
            'status'     => (string) ($_GET['status'] ?? 'OPEN'),
            'type'       => (string) ($_GET['type'] ?? ''),
            'unit'       => (int) ($_GET['unit'] ?? 0),
            'department' => (int) ($_GET['department'] ?? 0),
            'q'          => trim((string) ($_GET['q'] ?? '')),
        ];
        if ($filters['status'] !== 'OPEN' && !isset(OffboardingModel::STATUSES[$filters['status']])) {
            $filters['status'] = '';
        }
        if ($filters['type'] !== '' && !isset(OffboardingModel::TYPES[$filters['type']])) {
            $filters['type'] = '';
        }

        $perPage = 20;
        $total = $this->offboarding->countRequests($filters);
        $paginator = new Paginator($total, $perPage, min(max(1, (int) ($_GET['page'] ?? 1)), max(1, (int) ceil($total / $perPage))));

        $link = static function (int $page) use ($filters): string {
            $query = array_filter([
                'status'     => $filters['status'] ?: null,
                'type'       => $filters['type'] ?: null,
                'unit'       => $filters['unit'] ?: null,
                'department' => $filters['department'] ?: null,
                'q'          => $filters['q'] ?: null,
                'page'       => $page > 1 ? $page : null,
            ], static fn ($v) => $v !== null && $v !== '');
            return url('offboarding') . ($query ? '?' . http_build_query($query) : '');
        };

        $this->view('offboarding/index', [
            'title'       => 'Offboarding & Exit',
            'pageTitle'   => 'Offboarding & Exit',
            'requests'    => $this->offboarding->requests($filters, $perPage, $paginator->offset()),
            'paginator'   => $paginator,
            'pageLink'    => $link,
            'filters'     => $filters,
            'stats'       => $this->offboarding->stats(),
            'expiring'    => $this->offboarding->expiringContracts(90, 6),
            'units'       => $this->org->units(),
            'departments' => $this->org->departments(),
            'employees'   => $this->org->employeeOptions(),
            'types'       => OffboardingModel::TYPES,
            'typeTones'   => OffboardingModel::TYPE_TONES,
            'statuses'    => OffboardingModel::STATUSES,
            'tones'       => OffboardingModel::STATUS_TONES,
            'reasons'     => OffboardingModel::REASONS,
            'noticeDays'  => (int) Settings::get('resign_notice_days', 30),
        ] + $this->withInput());
    }

    public function store(): void
    {
        $this->requireRole('HR');

        $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('employee_id', 'Karyawan')
              ->numeric('employee_id', 'Karyawan', 1)
              ->required('type', 'Jenis offboarding')
              ->in('type', 'Jenis offboarding', array_keys(OffboardingModel::TYPES))
              ->required('last_working_date', 'Hari terakhir kerja')
              ->date('last_working_date', 'Hari terakhir kerja')
              ->date('submit_date', 'Tanggal pengajuan');
        }, '/offboarding');

        $result = $this->offboarding->create([
            'employee_id'       => (int) input('employee_id', 0),
            'type'              => (string) input('type', 'RESIGN'),
            'reason_category'   => (string) input('reason_category', ''),
            'reason'            => (string) input('reason', ''),
            'submit_date'       => (string) input('submit_date', date('Y-m-d')),
            'last_working_date' => (string) input('last_working_date'),
            'handover_to'       => (int) input('handover_to', 0),
        ], (int) $this->employeeId(), true);

        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/offboarding');
        }

        Audit::log('offboarding.create', 'offboarding_request', (int) $result['id'], ['type' => input('type')]);
        flash('success', 'Pengajuan ' . $result['number'] . ' dibuat. Checklist serah terima akan aktif setelah persetujuan HR.');
        redirect('/offboarding/' . (int) $result['id']);
    }

    public function export(): void
    {
        $this->requireRole('HR');

        $filters = [
            'status'     => (string) ($_GET['status'] ?? ''),
            'type'       => (string) ($_GET['type'] ?? ''),
            'unit'       => (int) ($_GET['unit'] ?? 0),
            'department' => (int) ($_GET['department'] ?? 0),
            'q'          => trim((string) ($_GET['q'] ?? '')),
        ];
        if ($filters['status'] === 'OPEN') {
            $filters['status'] = '';
        }
        $rows = $this->offboarding->requests($filters, 5000, 0);

        $filename = 'offboarding-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'No. Pengajuan', 'NIK', 'Nama Karyawan', 'Unit', 'Departemen', 'Posisi',
            'Jenis', 'Alasan', 'Tanggal Pengajuan', 'Hari Terakhir Kerja', 'Notice (hari)',
            'Status', 'Persetujuan Atasan', 'Persetujuan HR', 'Serah Terima',
            'Exit Interview', 'Rekomendasi', 'Penyelesaian (Rp)', 'Catatan',
        ], ',', '"', '');

        foreach ($rows as $row) {
            $label = static fn (?string $status): string => OffboardingModel::STATUSES[$status ?? ''] ?? (string) $status;
            fputcsv($out, [
                $row['request_number'],
                $row['employee_code'],
                $row['full_name'],
                $row['unit_name'] ?? '',
                $row['department_name'] ?? '',
                $row['position_name'] ?? '',
                OffboardingModel::TYPES[$row['type']] ?? $row['type'],
                OffboardingModel::REASONS[$row['reason_category'] ?? ''] ?? '',
                $row['submit_date'],
                $row['last_working_date'],
                (string) (int) $row['notice_days'],
                $label((string) $row['status']),
                $row['manager_approved_at'] ? tanggal($row['manager_approved_at'], 'd M Y H:i') : '',
                $row['hr_approved_at'] ? tanggal($row['hr_approved_at'], 'd M Y H:i') : '',
                (int) $row['clearance_done'] . '/' . (int) $row['clearance_total'],
                $row['exit_interview_at'] ? tanggal($row['exit_interview_at'], 'd M Y H:i') : '',
                OffboardingModel::RECOMMENDATIONS[$row['exit_would_recommend'] ?? ''] ?? '',
                (string) (float) $row['settlement_amount'],
                (string) ($row['reason'] ?? ''),
            ], ',', '"', '');
        }

        fclose($out);
        exit;
    }

    public function show(string $id): void
    {
        $request = $this->offboarding->find((int) $id);
        if (!$request) {
            $this->abort(404, 'Pengajuan offboarding tidak ditemukan.');
        }

        $user = (array) $this->employee();
        $isOwner = (int) $request['employee_id'] === (int) $user['id'];
        $isManager = Auth::isManagerOf((int) $request['employee_id']);
        $isHr = Auth::hasRole('HR');
        if (!$isOwner && !$isManager && !$isHr) {
            $this->abort(403, 'Anda tidak memiliki akses ke pengajuan ini.');
        }

        $status = (string) $request['status'];

        $this->view('offboarding/show', [
            'title'       => 'Offboarding ' . $request['request_number'],
            'pageTitle'   => 'Detail Offboarding',
            'request'     => $request,
            'clearance'   => $this->offboarding->clearance((int) $id),
            'stages'      => $this->offboarding->stages((int) $id),
            'progress'    => $this->offboarding->progress((int) $id),
            'isOwner'     => $isOwner,
            'isManager'   => $isManager,
            'isHr'        => $isHr,
            'canApproveManager' => in_array($status, ['SUBMITTED'], true) && ($isManager || $isHr),
            'canApproveHr'      => $isHr && in_array($status, ['SUBMITTED', 'MANAGER_APPROVED'], true),
            'canComplete'       => $isHr && $status === 'IN_PROGRESS',
            'canReject'         => ($isHr || $isManager) && in_array($status, ['SUBMITTED', 'MANAGER_APPROVED', 'IN_PROGRESS'], true),
            'canWithdraw'       => $isOwner && in_array($status, ['SUBMITTED', 'MANAGER_APPROVED', 'IN_PROGRESS'], true),
            'canCancel'         => $isHr && in_array($status, ['DRAFT', 'SUBMITTED', 'MANAGER_APPROVED', 'IN_PROGRESS'], true),
            'canDelete'         => $isHr && in_array($status, ['DRAFT', 'REJECTED', 'WITHDRAWN', 'CANCELLED'], true),
            'types'       => OffboardingModel::TYPES,
            'typeTones'   => OffboardingModel::TYPE_TONES,
            'statuses'    => OffboardingModel::STATUSES,
            'tones'       => OffboardingModel::STATUS_TONES,
            'reasons'     => OffboardingModel::REASONS,
            'categories'  => OffboardingModel::CLEARANCE_CATEGORIES,
            'clearanceStatuses' => OffboardingModel::CLEARANCE_STATUSES,
            'clearanceTones'    => OffboardingModel::CLEARANCE_TONES,
            'ownerRoles'  => OffboardingModel::OWNER_ROLES,
            'recommendations' => OffboardingModel::RECOMMENDATIONS,
            'employees'   => $this->org->employeeOptions(),
        ] + $this->withInput());
    }

    public function update(string $id): void
    {
        $this->requireRole('HR');
        $request = $this->offboarding->find((int) $id);
        if (!$request) {
            $this->abort(404, 'Pengajuan offboarding tidak ditemukan.');
        }

        $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('last_working_date', 'Hari terakhir kerja')
              ->date('last_working_date', 'Hari terakhir kerja')
              ->date('submit_date', 'Tanggal pengajuan');
        }, '/offboarding/' . (int) $id);

        $lastWorking = (string) input('last_working_date');
        $submitDate = (string) input('submit_date', (string) $request['submit_date']);
        if (strtotime($lastWorking) < strtotime($submitDate)) {
            flash('error', 'Hari terakhir kerja tidak boleh sebelum tanggal pengajuan.');
            back('/offboarding/' . (int) $id);
        }

        $this->offboarding->update((int) $id, [
            'last_working_date' => $lastWorking,
            'submit_date'       => $submitDate,
            'notice_days'       => (int) round((strtotime($lastWorking) - strtotime($submitDate)) / 86400),
            'handover_to'       => (int) input('handover_to', 0) > 0 ? (int) input('handover_to', 0) : null,
            'reason'            => trim((string) input('reason', '')) ?: null,
            'reason_category'   => isset(OffboardingModel::REASONS[(string) input('reason_category', '')])
                ? (string) input('reason_category', '') : null,
        ]);

        Audit::log('offboarding.update', 'offboarding_request', (int) $id);
        flash('success', 'Data offboarding diperbarui.');
        redirect('/offboarding/' . (int) $id);
    }

    public function approveManager(string $id): void
    {
        $request = $this->offboarding->find((int) $id);
        if (!$request) {
            $this->abort(404, 'Pengajuan offboarding tidak ditemukan.');
        }
        $this->requireManagerOrHr((int) $request['employee_id']);

        $result = $this->offboarding->approveManager((int) $id, (int) $this->employeeId(), (string) input('note', ''));
        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('offboarding.approve_manager', 'offboarding_request', (int) $id);
            flash('success', 'Disetujui atasan. Pengajuan diteruskan ke HR.');
        }
        redirect('/offboarding/' . (int) $id);
    }

    public function approveHr(string $id): void
    {
        $this->requireRole('HR');

        $result = $this->offboarding->approveHr((int) $id, (int) $this->employeeId(), (string) input('note', ''));
        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('offboarding.approve_hr', 'offboarding_request', (int) $id);
            flash('success', 'Pengajuan disetujui HR. Checklist serah terima sudah dibuat.');
        }
        redirect('/offboarding/' . (int) $id);
    }

    public function reject(string $id): void
    {
        $request = $this->offboarding->find((int) $id);
        if (!$request) {
            $this->abort(404, 'Pengajuan offboarding tidak ditemukan.');
        }
        $this->requireManagerOrHr((int) $request['employee_id']);

        $result = $this->offboarding->reject((int) $id, (int) $this->employeeId(), (string) input('reject_reason', ''));
        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('offboarding.reject', 'offboarding_request', (int) $id);
            flash('success', 'Pengajuan ditolak. Karyawan menerima notifikasi.');
        }
        redirect('/offboarding/' . (int) $id);
    }

    public function cancel(string $id): void
    {
        $this->requireRole('HR');

        $result = $this->offboarding->cancel((int) $id, (int) $this->employeeId());
        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('offboarding.cancel', 'offboarding_request', (int) $id);
            flash('success', 'Pengajuan dibatalkan HR.');
        }
        redirect('/offboarding/' . (int) $id);
    }

    public function complete(string $id): void
    {
        $this->requireRole('HR');

        $result = $this->offboarding->complete((int) $id, (int) $this->employeeId());
        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('offboarding.complete', 'offboarding_request', (int) $id);
            flash('success', 'Offboarding selesai. Data karyawan ditandai resign.');
        }
        redirect('/offboarding/' . (int) $id);
    }

    public function destroy(string $id): void
    {
        $this->requireRole('HR');

        $result = $this->offboarding->delete((int) $id);
        if ($result['error'] !== null) {
            flash('error', $result['error']);
            redirect('/offboarding/' . (int) $id);
        }

        Audit::log('offboarding.delete', 'offboarding_request', (int) $id);
        flash('success', 'Pengajuan offboarding dihapus.');
        redirect('/offboarding');
    }

    public function addClearance(string $id): void
    {
        $this->requireRole('HR');
        $request = $this->offboarding->find((int) $id);
        if (!$request) {
            $this->abort(404, 'Pengajuan offboarding tidak ditemukan.');
        }

        $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('item', 'Nama item serah terima')
              ->in('category', 'Kategori', array_keys(OffboardingModel::CLEARANCE_CATEGORIES))
              ->in('owner_role', 'Penanggung jawab', array_keys(OffboardingModel::OWNER_ROLES));
        }, '/offboarding/' . (int) $id);

        $this->offboarding->addClearance((int) $id, [
            'item'       => (string) input('item', ''),
            'category'   => (string) input('category', 'OTHER'),
            'owner_role' => (string) input('owner_role', 'HR'),
            'note'       => (string) input('note', ''),
        ]);

        Audit::log('offboarding.clearance_add', 'offboarding_request', (int) $id);
        flash('success', 'Item serah terima ditambahkan.');
        redirect('/offboarding/' . (int) $id);
    }

    public function updateClearance(string $id, string $itemId): void
    {
        $request = $this->offboarding->find((int) $id);
        if (!$request) {
            $this->abort(404, 'Pengajuan offboarding tidak ditemukan.');
        }
        $this->requireManagerOrHr((int) $request['employee_id']);

        $result = $this->offboarding->setClearanceStatus(
            (int) $itemId,
            (int) $id,
            (string) input('status', 'DONE'),
            (string) input('note', ''),
            (int) $this->employeeId()
        );

        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('offboarding.clearance_update', 'offboarding_request', (int) $id, ['item' => (int) $itemId]);
            flash('success', 'Status item serah terima diperbarui.');
        }
        redirect('/offboarding/' . (int) $id);
    }

    public function removeClearance(string $id, string $itemId): void
    {
        $this->requireRole('HR');

        $result = $this->offboarding->removeClearance((int) $itemId, (int) $id);
        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('offboarding.clearance_remove', 'offboarding_request', (int) $id, ['item' => (int) $itemId]);
            flash('success', 'Item serah terima dihapus.');
        }
        redirect('/offboarding/' . (int) $id);
    }

    public function completeClearance(string $id): void
    {
        $this->requireRole('HR');

        $count = $this->offboarding->completeAllClearance((int) $id, (int) $this->employeeId());
        Audit::log('offboarding.clearance_all', 'offboarding_request', (int) $id, ['items' => $count]);
        flash('success', $count > 0 ? $count . ' item serah terima ditandai selesai.' : 'Tidak ada item yang tersisa.');
        redirect('/offboarding/' . (int) $id);
    }

    public function interview(string $id): void
    {
        $this->requireRole('HR');

        $result = $this->offboarding->saveInterview((int) $id, [
            'exit_interview_at'    => (string) input('exit_interview_at', ''),
            'exit_interviewer_id'  => (int) input('exit_interviewer_id', 0),
            'exit_note'            => (string) input('exit_note', ''),
            'exit_would_recommend' => (string) input('exit_would_recommend', ''),
            'settlement_amount'    => (string) input('settlement_amount', '0'),
            'settlement_note'      => (string) input('settlement_note', ''),
        ], (int) $this->employeeId());

        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('offboarding.interview', 'offboarding_request', (int) $id);
            flash('success', 'Exit interview & penyelesaian akhir disimpan.');
        }
        redirect('/offboarding/' . (int) $id);
    }
}
