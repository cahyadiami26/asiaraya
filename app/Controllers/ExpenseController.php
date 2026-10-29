<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Paginator;
use App\Core\Upload;
use App\Core\Validator;
use App\Models\ExpenseModel;
use App\Models\OrgModel;

/**
 * Klaim & reimbursement.
 *  - /me/claims            : portal karyawan (ajukan, pantau, batalkan) + inbox atasan
 *  - /claims               : antrean HR/Finance (filter, bulk, export)
 *  - /claims/categories    : master kategori (HR)
 */
final class ExpenseController extends Controller
{
    private ExpenseModel $expenses;
    private OrgModel $org;

    public function __construct()
    {
        $this->expenses = new ExpenseModel();
        $this->org = new OrgModel();
    }

    /* ---------------------------------------------------------- karyawan */

    public function mine(): void
    {
        $employee = $this->employee();
        $employeeId = (int) $employee['id'];
        $isManager = (int) Database::value(
            'SELECT COUNT(*) FROM employees WHERE direct_manager_id = ? AND flag = ?',
            [$employeeId, 'ACTIVE']
        ) > 0;

        $this->view('claims/mine', [
            'title'      => 'Klaim Saya',
            'pageTitle'  => 'Klaim Saya',
            'claims'     => $this->expenses->employeeClaims($employeeId, 60),
            'stats'      => $this->expenses->employeeStats($employeeId),
            'categories' => $this->expenses->categories(true),
            'teamClaims' => $isManager ? $this->expenses->pendingForManager($employeeId) : [],
            'isManager'  => $isManager,
            'statuses'   => ExpenseModel::STATUSES,
            'tones'      => ExpenseModel::STATUS_TONES,
            'maxAge'     => (int) \App\Core\Settings::get('claim_max_age_days', 90),
        ] + $this->withInput());
    }

    public function store(): void
    {
        $employee = $this->employee();

        $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('category_id', 'Kategori klaim')
              ->required('claim_date', 'Tanggal transaksi')
              ->date('claim_date', 'Tanggal transaksi')
              ->required('amount', 'Nominal klaim')
              ->numeric('amount', 'Nominal klaim', 1)
              ->required('description', 'Keterangan')
              ->custom(mb_strlen((string) ($_POST['description'] ?? '')) > 255, 'description', 'Keterangan maksimal 255 karakter.');
        }, '/me/claims');

        $upload = Upload::document('receipt', 'claims', 4 * 1024 * 1024);
        if ($upload['error'] !== null) {
            flash('error', $upload['error']);
            back('/me/claims');
        }

        $result = $this->expenses->submit([
            'category_id' => (int) input('category_id', 0),
            'claim_date'  => (string) input('claim_date', date('Y-m-d')),
            'amount'      => (string) input('amount', '0'),
            'description' => (string) input('description', ''),
            'receipt'     => $upload['path'],
        ], $employee);

        if ($result['error'] !== null) {
            if ($upload['path']) {
                Upload::delete($upload['path']);
            }
            flash('error', $result['error']);
            back('/me/claims');
        }

        Audit::log('claim.submit', 'expense_claim', (int) $result['id'], [
            'number' => $result['number'],
            'status' => $result['status'],
        ]);

        $message = 'Klaim ' . $result['number'] . ' terkirim. '
            . ($result['manager'] ? 'Menunggu persetujuan atasan langsung.' : 'Langsung masuk antrean HR/Finance.');
        flash('success', $message);
        redirect('/me/claims');
    }

    public function cancel(string $id): void
    {
        $result = $this->expenses->cancel((int) $id, (int) $this->employeeId());
        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('claim.cancel', 'expense_claim', (int) $id);
            flash('success', 'Klaim dibatalkan.');
        }
        redirect('/me/claims');
    }

    /* ------------------------------------------------------ HR & Finance */

    public function index(): void
    {
        $filters = [
            'status'     => (string) ($_GET['status'] ?? 'OPEN'),
            'category'   => (int) ($_GET['category'] ?? 0),
            'unit'       => (int) ($_GET['unit'] ?? 0),
            'department' => (int) ($_GET['department'] ?? 0),
            'month'      => (string) ($_GET['month'] ?? ''),
            'q'          => trim((string) ($_GET['q'] ?? '')),
        ];
        if ($filters['status'] !== 'OPEN' && !isset(ExpenseModel::STATUSES[$filters['status']])) {
            $filters['status'] = '';
        }

        $perPage = 20;
        $total = $this->expenses->countClaims($filters);
        $paginator = new Paginator($total, $perPage, min(max(1, (int) ($_GET['page'] ?? 1)), max(1, (int) ceil($total / $perPage))));

        $link = static function (int $page) use ($filters): string {
            $query = array_filter([
                'status'     => $filters['status'] ?: null,
                'category'   => $filters['category'] ?: null,
                'unit'       => $filters['unit'] ?: null,
                'department' => $filters['department'] ?: null,
                'month'      => $filters['month'] ?: null,
                'q'          => $filters['q'] ?: null,
                'page'       => $page > 1 ? $page : null,
            ], static fn ($v) => $v !== null && $v !== '');
            return url('claims') . ($query ? '?' . http_build_query($query) : '');
        };

        $this->view('claims/index', [
            'title'       => 'Klaim & Reimbursement',
            'pageTitle'   => 'Klaim & Reimbursement',
            'claims'      => $this->expenses->claims($filters, $perPage, $paginator->offset()),
            'paginator'   => $paginator,
            'pageLink'    => $link,
            'filters'     => $filters,
            'stats'       => $this->expenses->stats(),
            'categories'  => $this->expenses->categories(),
            'units'       => $this->org->units(),
            'departments' => $this->org->departments(),
            'statuses'    => ExpenseModel::STATUSES,
            'tones'       => ExpenseModel::STATUS_TONES,
            'canDecide'   => Auth::hasRole(['HR', 'FIN']),
            'canPay'      => Auth::hasRole('FIN'),
        ] + $this->withInput());
    }

    public function show(string $id): void
    {
        $claim = $this->expenses->find((int) $id);
        if (!$claim) {
            $this->abort(404, 'Klaim tidak ditemukan.');
        }

        $user = (array) $this->employee();
        $isOwner = (int) $claim['employee_id'] === (int) $user['id'];
        $isManager = Auth::isManagerOf((int) $claim['employee_id']);
        $isStaff = Auth::hasRole(['HR', 'FIN']);
        if (!$isOwner && !$isManager && !$isStaff) {
            $this->abort(403, 'Anda tidak memiliki akses ke klaim ini.');
        }

        $canDecide = !$isOwner && in_array($claim['status'], ['PENDING', 'MANAGER_APPROVED'], true)
            && ($isManager || $isStaff);

        $this->view('claims/show', [
            'title'     => 'Klaim ' . $claim['claim_number'],
            'pageTitle' => 'Detail Klaim',
            'claim'     => $claim,
            'statuses'  => ExpenseModel::STATUSES,
            'tones'     => ExpenseModel::STATUS_TONES,
            'isOwner'   => $isOwner,
            'isStaff'   => $isStaff,
            'canDecide' => $canDecide,
            'canPay'    => Auth::hasRole('FIN'),
        ] + $this->withInput());
    }

    public function decide(string $id): void
    {
        $decision = strtoupper((string) input('decision', 'APPROVED')) === 'REJECTED' ? 'REJECTED' : 'APPROVED';
        $result = $this->expenses->decide((int) $id, $decision, [
            'id'   => (int) $this->employeeId(),
            'role' => (string) Auth::role(),
            'note' => (string) input('note', ''),
        ]);

        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('claim.' . strtolower($decision), 'expense_claim', (int) $id, ['stage' => $result['stage'] ?? null]);
            flash($decision === 'APPROVED' ? 'success' : 'info',
                $decision === 'APPROVED'
                    ? 'Klaim disetujui' . (($result['stage'] ?? '') === 'MANAGER' ? ' dan diteruskan ke HR/Finance.' : ' dan siap dibayarkan.')
                    : 'Klaim ditolak. Pengaju telah diberi notifikasi.');
        }

        $back = (string) input('back', '');
        redirect($back !== '' && str_starts_with($back, '/') ? $back : '/claims/' . (int) $id);
    }

    /** Aksi massal dari antrean HR/Finance. */
    public function bulkDecide(): void
    {
        $this->requireRole(['HR', 'FIN']);

        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
        $decision = strtoupper((string) input('decision', 'APPROVED')) === 'REJECTED' ? 'REJECTED' : 'APPROVED';
        if (!$ids) {
            flash('error', 'Pilih minimal satu klaim.');
            back('/claims');
        }

        $actor = ['id' => (int) $this->employeeId(), 'role' => (string) Auth::role(), 'note' => (string) input('note', '')];
        $ok = 0;
        $skipped = 0;
        foreach ($ids as $claimId) {
            $result = $this->expenses->decide($claimId, $decision, $actor);
            if ($result['error'] === null) {
                $ok++;
            } else {
                $skipped++;
            }
        }

        Audit::log('claim.bulk_' . strtolower($decision), 'expense_claim', null, ['ok' => $ok, 'skipped' => $skipped]);
        flash($ok > 0 ? 'success' : 'warning',
            $ok . ' klaim diproses' . ($skipped > 0 ? ', ' . $skipped . ' dilewati (status tidak sesuai/klaim sendiri).' : '.'));
        redirect('/claims');
    }

    public function pay(string $id): void
    {
        $this->requireRole('FIN');
        $result = $this->expenses->markPaid((int) $id, (int) $this->employeeId());
        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('claim.pay', 'expense_claim', (int) $id);
            flash('success', 'Klaim ditandai sudah dibayarkan.');
        }
        redirect('/claims/' . (int) $id);
    }

    public function export(): void
    {
        $filters = [
            'status'     => (string) ($_GET['status'] ?? ''),
            'category'   => (int) ($_GET['category'] ?? 0),
            'unit'       => (int) ($_GET['unit'] ?? 0),
            'department' => (int) ($_GET['department'] ?? 0),
            'month'      => (string) ($_GET['month'] ?? ''),
            'q'          => trim((string) ($_GET['q'] ?? '')),
        ];
        $rows = $this->expenses->claims($filters, 5000, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="klaim-biaya-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['No. Klaim', 'Tanggal', 'NIK', 'Karyawan', 'Unit', 'Departemen', 'Kategori',
            'Keterangan', 'Nominal (Rp)', 'Status', 'Atasan', 'Disetujui Oleh', 'No. Slip', 'Dibayarkan'], ',', '"', '');

        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float) $row['amount'];
            fputcsv($out, [
                $row['claim_number'],
                $row['claim_date'],
                $row['employee_code'],
                $row['full_name'],
                $row['unit_name'] ?? '-',
                $row['department_name'] ?? '-',
                $row['category_name'],
                $row['description'],
                number_format((float) $row['amount'], 2, '.', ''),
                ExpenseModel::STATUSES[$row['status']] ?? $row['status'],
                $row['manager_name'] ?? '-',
                $row['approver_name'] ?? '-',
                $row['slip_number'] ?? '-',
                $row['paid_at'] ?? '-',
            ], ',', '"', '');
        }
        fputcsv($out, ['', '', '', '', '', '', '', 'TOTAL', number_format($total, 2, '.', ''), '', '', '', '', ''], ',', '"', '');
        fclose($out);

        Audit::log('claim.export', 'expense_claim', null, ['rows' => count($rows), 'total' => (int) round($total)]);
        exit;
    }

    /* --------------------------------------------------------- kategori */

    public function categories(): void
    {
        $this->requireRole('HR');

        $editing = null;
        $editId = (int) ($_GET['edit'] ?? 0);
        if ($editId > 0) {
            $editing = $this->expenses->findCategory($editId);
        }

        $this->view('claims/categories', [
            'title'     => 'Kategori Klaim',
            'pageTitle' => 'Kategori Klaim',
            'categories' => $this->expenses->categories(),
            'editing'   => $editing,
            'stats'     => $this->expenses->stats(),
        ] + $this->withInput());
    }

    public function storeCategory(): void
    {
        $this->requireRole('HR');
        $result = $this->expenses->saveCategory($_POST);
        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/claims/categories');
        }

        Audit::log('claim.category_create', 'expense_category', (int) $result['id'], ['code' => input('code', '')]);
        flash('success', 'Kategori klaim ditambahkan.');
        redirect('/claims/categories');
    }

    public function updateCategory(string $id): void
    {
        $this->requireRole('HR');
        $result = $this->expenses->saveCategory($_POST, (int) $id);
        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/claims/categories?edit=' . (int) $id);
        }

        Audit::log('claim.category_update', 'expense_category', (int) $id);
        flash('success', 'Kategori klaim diperbarui.');
        redirect('/claims/categories');
    }

    public function destroyCategory(string $id): void
    {
        $this->requireRole('HR');
        $result = $this->expenses->deleteCategory((int) $id);
        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('claim.category_delete', 'expense_category', (int) $id);
            flash('success', 'Kategori klaim dihapus.');
        }
        redirect('/claims/categories');
    }
}
