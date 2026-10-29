<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Settings;

/**
 * Klaim & reimbursement: pengajuan biaya karyawan (transport, kesehatan,
 * perjalanan dinas, dan lain-lain) dengan alur persetujuan atasan → HR/Finance
 * dan integrasi otomatis ke payroll (nominal disetujui ikut dibayarkan pada
 * slip periode berikutnya).
 */
final class ExpenseModel
{
    public const STATUSES = [
        'PENDING'          => 'Menunggu Atasan',
        'MANAGER_APPROVED' => 'Menunggu HR/Finance',
        'APPROVED'         => 'Disetujui',
        'SCHEDULED'        => 'Masuk Payroll',
        'PAID'             => 'Dibayarkan',
        'REJECTED'         => 'Ditolak',
        'CANCELLED'        => 'Dibatalkan',
    ];

    public const STATUS_TONES = [
        'PENDING'          => 'amber',
        'MANAGER_APPROVED' => 'indigo',
        'APPROVED'         => 'teal',
        'SCHEDULED'        => 'indigo',
        'PAID'             => 'teal',
        'REJECTED'         => 'rose',
        'CANCELLED'        => 'slate',
    ];

    /** Status yang masih berjalan (belum selesai/ditolak). */
    public const OPEN_STATUSES = ['PENDING', 'MANAGER_APPROVED', 'APPROVED', 'SCHEDULED'];

    public function __construct()
    {
        //
    }

    /* ============================================================ kategori */

    public function categories(bool $activeOnly = false): array
    {
        $sql = 'SELECT ec.*,
                       (SELECT COUNT(*) FROM expense_claims c WHERE c.category_id = ec.id) AS claim_count,
                       (SELECT COALESCE(SUM(c.amount), 0) FROM expense_claims c
                         WHERE c.category_id = ec.id AND c.status IN (\'APPROVED\', \'SCHEDULED\', \'PAID\')) AS approved_total
                FROM expense_categories ec';
        if ($activeOnly) {
            $sql .= ' WHERE ec.is_active = 1';
        }
        $sql .= ' ORDER BY ec.is_active DESC, ec.name';

        return Database::all($sql);
    }

    public function findCategory(int $id): ?array
    {
        return Database::one('SELECT * FROM expense_categories WHERE id = ?', [$id]);
    }

    /** @return array{id:?int,error:?string} */
    public function saveCategory(array $data, ?int $id = null): array
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        if ($code === '' || !preg_match('/^[A-Z0-9_]{3,30}$/', $code)) {
            return ['id' => null, 'error' => 'Kode kategori 3–30 karakter (huruf kapital, angka, garis bawah).'];
        }
        if ($name === '' || mb_strlen($name) > 100) {
            return ['id' => null, 'error' => 'Nama kategori wajib diisi (maksimal 100 karakter).'];
        }

        $duplicate = Database::value(
            'SELECT id FROM expense_categories WHERE code = ?' . ($id ? ' AND id <> ?' : ''),
            $id ? [$code, $id] : [$code]
        );
        if ($duplicate) {
            return ['id' => null, 'error' => 'Kode kategori "' . $code . '" sudah dipakai.'];
        }

        $maxAmount = trim((string) ($data['max_amount'] ?? ''));
        $payload = [
            'code'             => $code,
            'name'             => $name,
            'description'      => mb_substr(trim((string) ($data['description'] ?? '')), 0, 255) ?: null,
            'max_amount'       => $maxAmount === '' ? null : round((float) str_replace(['.', ','], ['', '.'], $maxAmount), 2),
            'requires_receipt' => (int) ($data['requires_receipt'] ?? 1) === 1 ? 1 : 0,
            'is_active'        => (int) ($data['is_active'] ?? 1) === 1 ? 1 : 0,
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        if ($id) {
            Database::update('expense_categories', $payload, 'id = ?', [$id]);
            return ['id' => $id, 'error' => null];
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        return ['id' => Database::insert('expense_categories', $payload), 'error' => null];
    }

    /** Hapus kategori hanya bila belum pernah dipakai. */
    public function deleteCategory(int $id): array
    {
        $used = (int) Database::value('SELECT COUNT(*) FROM expense_claims WHERE category_id = ?', [$id]);
        if ($used > 0) {
            return ['error' => 'Kategori sudah dipakai pada ' . number_id($used) . ' klaim. Nonaktifkan saja agar riwayat tetap utuh.'];
        }
        Database::delete('expense_categories', 'id = ?', [$id]);
        return ['error' => null];
    }

    /* =============================================================== klaim */

    /** @return array{0:string,1:array<int,mixed>} */
    private function claimWhere(array $filters): array
    {
        $clauses = [];
        $params = [];

        $status = (string) ($filters['status'] ?? '');
        if ($status === 'OPEN') {
            $clauses[] = "c.status IN ('PENDING','MANAGER_APPROVED','APPROVED','SCHEDULED')";
        } elseif ($status !== '' && isset(self::STATUSES[$status])) {
            $clauses[] = 'c.status = ?';
            $params[] = $status;
        }

        if (!empty($filters['category'])) {
            $clauses[] = 'c.category_id = ?';
            $params[] = (int) $filters['category'];
        }
        if (!empty($filters['employee'])) {
            $clauses[] = 'c.employee_id = ?';
            $params[] = (int) $filters['employee'];
        }
        if (!empty($filters['manager'])) {
            $clauses[] = "c.status = 'PENDING' AND e.direct_manager_id = ?";
            $params[] = (int) $filters['manager'];
        }
        if (!empty($filters['department'])) {
            $clauses[] = 'e.department_id = ?';
            $params[] = (int) $filters['department'];
        }
        if (!empty($filters['unit'])) {
            $clauses[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (!empty($filters['month']) && preg_match('/^\d{4}-\d{2}$/', (string) $filters['month'])) {
            $clauses[] = 'c.claim_date BETWEEN ? AND ?';
            $params[] = $filters['month'] . '-01';
            $params[] = date('Y-m-t', strtotime($filters['month'] . '-01'));
        }
        if (!empty($filters['from'])) {
            $clauses[] = 'c.claim_date >= ?';
            $params[] = (string) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $clauses[] = 'c.claim_date <= ?';
            $params[] = (string) $filters['to'];
        }
        if (($filters['q'] ?? '') !== '') {
            $like = Database::like((string) $filters['q']);
            $clauses[] = '(c.claim_number LIKE ? OR c.description LIKE ? OR e.full_name LIKE ? OR e.employee_code LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        return [$clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    private const BASE_SELECT = "SELECT c.*, ec.name AS category_name, ec.code AS category_code, ec.requires_receipt,
                e.full_name, e.employee_code, e.department_id, e.business_unit_id,
                bu.name AS unit_name, d.name AS department_name,
                m.full_name AS manager_name, a.full_name AS approver_name,
                pi.slip_number, p.period_month, p.period_year
         FROM expense_claims c
         JOIN employees e ON e.id = c.employee_id
         JOIN expense_categories ec ON ec.id = c.category_id
         LEFT JOIN business_units bu ON bu.id = e.business_unit_id
         LEFT JOIN departments d ON d.id = e.department_id
         LEFT JOIN employees m ON m.id = c.manager_id
         LEFT JOIN employees a ON a.id = c.approver_id
         LEFT JOIN payroll_items pi ON pi.id = c.payroll_item_id
         LEFT JOIN payroll_periods p ON p.id = pi.period_id";

    public function claims(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        [$where, $params] = $this->claimWhere($filters);

        return Database::all(
            self::BASE_SELECT . $where
            . ' ORDER BY CASE c.status WHEN \'PENDING\' THEN 0 WHEN \'MANAGER_APPROVED\' THEN 1 WHEN \'APPROVED\' THEN 2 ELSE 3 END,'
            . ' c.claim_date DESC, c.id DESC'
            . ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $params
        );
    }

    public function countClaims(array $filters = []): int
    {
        [$where, $params] = $this->claimWhere($filters);
        return (int) Database::value(
            'SELECT COUNT(*) FROM expense_claims c JOIN employees e ON e.id = c.employee_id' . $where,
            $params
        );
    }

    public function find(int $id): ?array
    {
        return Database::one(self::BASE_SELECT . ' WHERE c.id = ?', [$id]);
    }

    public function stats(): array
    {
        $row = Database::one(
            "SELECT
                SUM(CASE WHEN c.status = 'PENDING' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN c.status = 'MANAGER_APPROVED' THEN 1 ELSE 0 END) AS waiting_final,
                SUM(CASE WHEN c.status = 'APPROVED' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN c.status = 'SCHEDULED' THEN 1 ELSE 0 END) AS scheduled,
                SUM(CASE WHEN c.status = 'PAID' THEN 1 ELSE 0 END) AS paid,
                SUM(CASE WHEN c.status = 'REJECTED' THEN 1 ELSE 0 END) AS rejected,
                COALESCE(SUM(CASE WHEN c.status = 'APPROVED' THEN c.amount ELSE 0 END), 0) AS approved_amount,
                COALESCE(SUM(CASE WHEN c.status = 'PAID' AND c.claim_date >= ? THEN c.amount ELSE 0 END), 0) AS paid_month_amount,
                COALESCE(SUM(CASE WHEN c.claim_date >= ? THEN c.amount ELSE 0 END), 0) AS month_amount
             FROM expense_claims c",
            [date('Y-m-01'), date('Y-m-01')]
        ) ?? [];

        return [
            'pending'            => (int) ($row['pending'] ?? 0),
            'waiting_final'      => (int) ($row['waiting_final'] ?? 0),
            'approved'           => (int) ($row['approved'] ?? 0),
            'scheduled'          => (int) ($row['scheduled'] ?? 0),
            'paid'               => (int) ($row['paid'] ?? 0),
            'rejected'           => (int) ($row['rejected'] ?? 0),
            'approved_amount'    => (float) ($row['approved_amount'] ?? 0),
            'paid_month_amount'  => (float) ($row['paid_month_amount'] ?? 0),
            'month_amount'       => (float) ($row['month_amount'] ?? 0),
        ];
    }

    /** Klaim milik satu karyawan (portal). */
    public function employeeClaims(int $employeeId, int $limit = 50): array
    {
        return Database::all(
            self::BASE_SELECT . ' WHERE c.employee_id = ? ORDER BY c.claim_date DESC, c.id DESC LIMIT ' . max(1, $limit),
            [$employeeId]
        );
    }

    public function employeeStats(int $employeeId): array
    {
        $row = Database::one(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN c.status IN ('PENDING','MANAGER_APPROVED','APPROVED','SCHEDULED') THEN c.amount ELSE 0 END), 0) AS open_amount,
                COALESCE(SUM(CASE WHEN c.status = 'PAID' THEN c.amount ELSE 0 END), 0) AS paid_amount,
                COALESCE(SUM(CASE WHEN c.status = 'PAID' AND c.claim_date >= ? THEN c.amount ELSE 0 END), 0) AS paid_this_month
             FROM expense_claims c WHERE c.employee_id = ?",
            [date('Y-m-01'), $employeeId]
        ) ?? [];

        return [
            'total'           => (int) ($row['total'] ?? 0),
            'open_amount'     => (float) ($row['open_amount'] ?? 0),
            'paid_amount'     => (float) ($row['paid_amount'] ?? 0),
            'paid_this_month' => (float) ($row['paid_this_month'] ?? 0),
        ];
    }

    /** Klaim bawahan langsung yang menunggu persetujuan atasan (inbox manajer). */
    public function pendingForManager(int $managerId): array
    {
        return Database::all(
            self::BASE_SELECT . " WHERE c.status = 'PENDING' AND e.direct_manager_id = ? ORDER BY c.claim_date DESC",
            [$managerId]
        );
    }

    /* ============================================================== proses */

    private function nextNumber(array $employee): string
    {
        $unitCode = (string) Database::value(
            'SELECT code FROM business_units WHERE id = ?',
            [(int) ($employee['business_unit_id'] ?? 0)]
        );
        $unitCode = preg_replace('/[^A-Z0-9]/', '', strtoupper($unitCode ?: 'HQ')) ?: 'HQ';
        $prefix = 'KLM/' . $unitCode . '/' . date('Ym') . '/';

        $last = (string) (Database::value(
            'SELECT claim_number FROM expense_claims WHERE claim_number LIKE ? ORDER BY id DESC LIMIT 1',
            [$prefix . '%']
        ) ?? '');
        $sequence = $last !== '' ? (int) substr($last, -4) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Kirim klaim baru. Bila karyawan punya atasan langsung, status awal PENDING
     * (persetujuan atasan); tanpa atasan langsung langsung ke tahap HR/Finance.
     *
     * @return array{id:?int,error:?string,status:?string,number:?string,manager:?int}
     */
    public function submit(array $data, array $employee): array
    {
        $category = $this->findCategory((int) ($data['category_id'] ?? 0));
        if (!$category) {
            return ['id' => null, 'error' => 'Kategori klaim tidak ditemukan.', 'status' => null, 'number' => null, 'manager' => null];
        }
        if ((int) $category['is_active'] !== 1) {
            return ['id' => null, 'error' => 'Kategori "' . $category['name'] . '" sudah tidak aktif.', 'status' => null, 'number' => null, 'manager' => null];
        }

        $amount = round((float) str_replace(['.', ','], ['', '.'], (string) ($data['amount'] ?? '0')), 2);
        if ($amount <= 0) {
            return ['id' => null, 'error' => 'Nominal klaim harus lebih besar dari nol.', 'status' => null, 'number' => null, 'manager' => null];
        }
        if ($category['max_amount'] !== null && $amount > (float) $category['max_amount']) {
            return [
                'id' => null,
                'error' => 'Nominal melebihi batas ' . $category['name'] . ' (maksimal ' . rupiah((float) $category['max_amount']) . ').',
                'status' => null, 'number' => null, 'manager' => null,
            ];
        }

        $claimDate = (string) ($data['claim_date'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $claimDate) || strtotime($claimDate) === false) {
            return ['id' => null, 'error' => 'Tanggal klaim tidak valid.', 'status' => null, 'number' => null, 'manager' => null];
        }
        if (strtotime($claimDate) > strtotime(date('Y-m-d'))) {
            return ['id' => null, 'error' => 'Tanggal klaim tidak boleh melewati hari ini.', 'status' => null, 'number' => null, 'manager' => null];
        }
        $limitDays = (int) Settings::get('claim_max_age_days', 90);
        if ($limitDays > 0 && strtotime($claimDate) < strtotime('-' . $limitDays . ' days')) {
            return [
                'id' => null,
                'error' => 'Klaim hanya dapat diajukan maksimal ' . $limitDays . ' hari setelah tanggal transaksi.',
                'status' => null, 'number' => null, 'manager' => null,
            ];
        }

        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '' || mb_strlen($description) > 255) {
            return ['id' => null, 'error' => 'Keterangan klaim wajib diisi (maksimal 255 karakter).', 'status' => null, 'number' => null, 'manager' => null];
        }

        $receipt = $data['receipt'] ?? null;
        if ((int) $category['requires_receipt'] === 1 && !$receipt) {
            return ['id' => null, 'error' => $category['name'] . ' mewajibkan lampiran bukti (foto/PDF nota).', 'status' => null, 'number' => null, 'manager' => null];
        }

        $managerId = (int) ($employee['direct_manager_id'] ?? 0);
        if ($managerId === (int) $employee['id']) {
            $managerId = 0;
        }
        $status = $managerId > 0 ? 'PENDING' : 'MANAGER_APPROVED';
        $now = date('Y-m-d H:i:s');

        $id = Database::transaction(function () use ($employee, $category, $claimDate, $amount, $description, $receipt, $status, $now): int {
            return Database::insert('expense_claims', [
                'claim_number'    => $this->nextNumber($employee),
                'employee_id'     => (int) $employee['id'],
                'category_id'     => (int) $category['id'],
                'claim_date'      => $claimDate,
                'amount'          => $amount,
                'description'     => $description,
                'receipt'         => $receipt,
                'status'          => $status,
                'manager_id'      => null,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        });

        $claim = $this->find($id);
        if ($managerId > 0) {
            $this->notify($managerId, 'Klaim biaya menunggu persetujuan',
                $employee['full_name'] . ' mengajukan ' . $category['name'] . ' ' . rupiah($amount) . '.', '/claims/' . $id, 'info');
        } else {
            $this->notifyRole('HR', 'Klaim biaya baru menunggu persetujuan',
                $employee['full_name'] . ' — ' . $category['name'] . ' ' . rupiah($amount) . '.', '/claims/' . $id);
        }

        return [
            'id'      => $id,
            'error'   => null,
            'status'  => $status,
            'number'  => (string) ($claim['claim_number'] ?? ''),
            'manager' => $managerId,
        ];
    }

    /** Batalkan klaim milik sendiri selama belum disetujui final. */
    public function cancel(int $id, int $employeeId): array
    {
        $claim = $this->find($id);
        if (!$claim || (int) $claim['employee_id'] !== $employeeId) {
            return ['error' => 'Klaim tidak ditemukan.'];
        }
        if (!in_array($claim['status'], ['PENDING', 'MANAGER_APPROVED'], true)) {
            return ['error' => 'Klaim pada status "' . self::STATUSES[$claim['status']] . '" tidak dapat dibatalkan.'];
        }

        Database::update('expense_claims', [
            'status'     => 'CANCELLED',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        return ['error' => null];
    }

    /**
     * Putuskan klaim. Tahap 1 (atasan): PENDING → MANAGER_APPROVED.
     * Tahap 2 (HR/Finance): PENDING/MANAGER_APPROVED → APPROVED.
     * Penolakan di tahap mana pun mengakhiri klaim sebagai REJECTED.
     */
    public function decide(int $id, string $decision, array $actor): array
    {
        $claim = $this->find($id);
        if (!$claim) {
            return ['error' => 'Klaim tidak ditemukan.'];
        }
        if (!in_array($claim['status'], ['PENDING', 'MANAGER_APPROVED'], true)) {
            return ['error' => 'Klaim sudah diproses sebelumnya (' . self::STATUSES[$claim['status']] . ').'];
        }
        if ((int) $claim['employee_id'] === (int) $actor['id']) {
            return ['error' => 'Anda tidak dapat memutus klaim milik sendiri.'];
        }

        $approved = $decision === 'APPROVED';
        $now = date('Y-m-d H:i:s');
        $note = mb_substr(trim((string) ($actor['note'] ?? '')), 0, 255) ?: null;

        $isManager = (int) Database::value('SELECT direct_manager_id FROM employees WHERE id = ?', [(int) $claim['employee_id']]) === (int) $actor['id'];
        $isFinal = in_array($actor['role'] ?? '', ['HR', 'FIN'], true);

        if ($claim['status'] === 'PENDING' && $isManager) {
            $stage = 'MANAGER';
        } elseif ($isFinal) {
            $stage = 'FINAL';
        } else {
            return ['error' => 'Anda tidak berhak memproses klaim ini pada tahap saat ini.'];
        }

        if ($stage === 'MANAGER') {
            Database::update('expense_claims', [
                'status'              => $approved ? 'MANAGER_APPROVED' : 'REJECTED',
                'manager_id'          => (int) $actor['id'],
                'manager_approved_at' => $now,
                'manager_note'        => $note,
                'updated_at'          => $now,
            ], 'id = ?', [$id]);
            $status = $approved ? 'MANAGER_APPROVED' : 'REJECTED';
        } else {
            Database::update('expense_claims', [
                'status'         => $approved ? 'APPROVED' : 'REJECTED',
                'approver_id'    => (int) $actor['id'],
                'approved_at'    => $now,
                'approver_note'  => $note,
                'updated_at'     => $now,
            ], 'id = ?', [$id]);
            $status = $approved ? 'APPROVED' : 'REJECTED';
        }

        $label = $claim['category_name'] . ' ' . rupiah((float) $claim['amount']);
        $this->notify((int) $claim['employee_id'],
            $approved ? 'Klaim biaya disetujui' : 'Klaim biaya ditolak',
            $claim['claim_number'] . ' — ' . $label . ($note ? ' · Catatan: ' . $note : ''),
            '/claims/' . $id,
            $approved ? 'success' : 'warning');

        if ($approved && $stage === 'MANAGER') {
            $this->notifyRole('HR', 'Klaim menunggu persetujuan HR/Finance',
                $claim['full_name'] . ' — ' . $label . ' telah disetujui atasan.', '/claims/' . $id);
        }

        return ['error' => null, 'status' => $status, 'stage' => $stage];
    }

    /** Finance menandai klaim (yang tidak lewat payroll) sebagai dibayarkan. */
    public function markPaid(int $id, int $userId): array
    {
        $claim = $this->find($id);
        if (!$claim) {
            return ['error' => 'Klaim tidak ditemukan.'];
        }
        if (!in_array($claim['status'], ['APPROVED'], true)) {
            return ['error' => 'Hanya klaim berstatus Disetujui yang dapat ditandai dibayarkan.'];
        }

        Database::update('expense_claims', [
            'status'     => 'PAID',
            'paid_at'    => date('Y-m-d H:i:s'),
            'paid_by'    => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->notify((int) $claim['employee_id'], 'Reimbursement dibayarkan',
            $claim['claim_number'] . ' sebesar ' . rupiah((float) $claim['amount']) . ' telah dibayarkan.',
            '/claims/' . $id, 'success');

        return ['error' => null];
    }

    /* ============================================================== payroll */

    /** Klaim disetujui yang belum masuk payroll pada rentang tanggal tertentu. */
    public function approvedForRange(int $employeeId, string $start, string $end): array
    {
        return Database::all(
            "SELECT c.*, ec.name AS category_name
             FROM expense_claims c JOIN expense_categories ec ON ec.id = c.category_id
             WHERE c.employee_id = ? AND c.status = 'APPROVED' AND c.payroll_item_id IS NULL
               AND c.claim_date BETWEEN ? AND ?
             ORDER BY c.claim_date",
            [$employeeId, $start, $end]
        );
    }

    /** Tandai klaim sudah masuk slip payroll tertentu. */
    public function linkToPayroll(array $claimIds, int $itemId): void
    {
        foreach ($claimIds as $claimId) {
            Database::update('expense_claims', [
                'status'          => 'SCHEDULED',
                'payroll_item_id' => $itemId,
                'updated_at'      => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int) $claimId]);
        }
    }

    /**
     * Lepas semua klaim dari periode payroll (dipakai saat hitung ulang,
     * reopen, atau hapus periode) agar tidak menunjuk slip yang hilang.
     */
    public function unlinkPeriod(int $periodId): int
    {
        return Database::run(
            "UPDATE expense_claims
             SET status = 'APPROVED', payroll_item_id = NULL, paid_at = NULL, paid_by = NULL, updated_at = ?
             WHERE payroll_item_id IN (SELECT id FROM payroll_items WHERE period_id = ?)",
            [date('Y-m-d H:i:s'), $periodId]
        )->rowCount();
    }

    /** Payroll dibayarkan → klaim yang menempel ikut berstatus Dibayarkan. */
    public function markPeriodPaid(int $periodId, ?int $userId): int
    {
        return Database::run(
            "UPDATE expense_claims
             SET status = 'PAID', paid_at = ?, paid_by = ?, updated_at = ?
             WHERE payroll_item_id IN (SELECT id FROM payroll_items WHERE period_id = ?)",
            [date('Y-m-d H:i:s'), $userId, date('Y-m-d H:i:s'), $periodId]
        )->rowCount();
    }

    /* ================================================================ util */

    private function notify(int $employeeId, string $title, string $message, string $link, string $type = 'info'): void
    {
        try {
            Database::insert('notifications', [
                'employee_id' => $employeeId,
                'title'       => mb_substr($title, 0, 150),
                'message'     => mb_substr($message, 0, 255),
                'link'        => $link,
                'type'        => $type,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // notifikasi bersifat best-effort
        }
    }

    private function notifyRole(string $role, string $title, string $message, string $link): void
    {
        try {
            $ids = Database::all('SELECT id FROM employees WHERE role = ? AND flag = ?', [$role, 'ACTIVE']);
            foreach ($ids as $row) {
                $this->notify((int) $row['id'], $title, $message, $link);
            }
        } catch (\Throwable) {
            // abaikan
        }
    }
}
