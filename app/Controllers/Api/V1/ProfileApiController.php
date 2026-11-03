<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Core\ApiRequest;
use App\Core\Database;
use App\Models\DocumentModel;
use App\Models\LeaveModel;

/**
 * Profil karyawan untuk aplikasi Android.
 *
 * GET /api/v1/me/profile?year=YYYY
 *
 * Berbeda dari `GET /api/v1/auth/me` (sesi login + penghitung beranda),
 * endpoint ini mengembalikan **data kepegawaian lengkap** pemilik token:
 * identitas, masa kerja & kontrak, penempatan organisasi, atasan langsung,
 * kontak, data pribadi, data payroll/bank, saldo cuti, dan ringkasan dokumen.
 *
 * Hanya data milik pemilik token yang bisa dibaca — tidak ada parameter
 * employee lain, sehingga tidak ada risiko membocorkan data rekan kerja.
 */
final class ProfileApiController extends ApiController
{
    use ApiPresenter;

    /** GET /api/v1/me/profile */
    public function show(): void
    {
        $user = $this->requireAuth();
        $employeeId = (int) $user['id'];

        $employee = $this->findEmployee($employeeId);
        if ($employee === null) {
            $this->fail(404, 'Data karyawan tidak ditemukan.', [], 'NOT_FOUND');
        }

        $year = ApiRequest::queryInt('year', (int) date('Y'), 2000, 2100);
        $leave = new LeaveModel();
        $isManager = $this->hasSubordinates($employeeId);

        $this->ok([
            'profile'      => $this->profileIdentity($employee),
            'employment'   => $this->employmentPayload($employee),
            'organization' => $this->organizationPayload($employee),
            'manager'      => $this->managerPayload($employee),
            'contact'      => [
                'email'         => (string) ($employee['email'] ?? ''),
                'username'      => $employee['username'] ?? null,
                'phone'         => $employee['phone'] ?? null,
                'address'       => $employee['address'] ?? null,
                'google_linked' => (int) ($employee['google_linked'] ?? 0) === 1,
            ],
            'personal'     => $this->personalPayload($employee),
            'payroll'      => $this->payrollPayload($employee),
            'leave'        => [
                'year'     => $year,
                'summary'  => $leave->mySummary($employeeId, $year),
                'balances' => array_map(
                    static fn (array $row): array => [
                        'leave_type_id' => (int) $row['id'],
                        'type_name'     => (string) $row['name'],
                        'type_code'     => (string) $row['code'],
                        'color'         => $row['color'] ?? null,
                        'year'          => $year,
                        'quota'         => (float) $row['quota'],
                        'carry_over'    => (float) $row['carry'],
                        'used'          => (float) $row['used'],
                        'remaining'     => (float) $row['remaining'],
                        'unlimited'     => (bool) ($row['unlimited'] ?? false),
                        'progress'      => round((float) ($row['progress'] ?? 100), 1),
                    ],
                    $leave->myBalances($employeeId, $year)
                ),
            ],
            'documents'    => $this->documentSummary($employeeId),
            'permissions'  => [
                'role'                    => (string) ($employee['role'] ?? 'KARYAWAN'),
                'role_label'              => role_label((string) ($employee['role'] ?? 'KARYAWAN')),
                'can_approve'             => in_array((string) ($employee['role'] ?? ''), ['HR', 'FIN'], true) || $isManager,
                'is_manager'              => $isManager,
                'editable_fields'         => ['phone', 'address'],
                'email_change_requires_hr' => true,
            ],
            'server_time'  => date('c'),
        ], ['year' => $year]);
    }

    /* ------------------------------------------------------------- internal */

    /** Baris karyawan + nama unit/departemen/jabatan. Password tidak diambil. */
    private function findEmployee(int $employeeId): ?array
    {
        return Database::one(
            'SELECT e.id, e.employee_code, e.username, e.full_name, e.nickname,
                    e.email, e.phone, e.address, e.birth_date, e.gender,
                    e.marital_status, e.religion, e.photo, e.avatar, e.role, e.flag,
                    e.direct_manager_id, e.business_unit_id, e.department_id, e.position_id,
                    e.basic_salary, e.employment_status, e.contract_start, e.contract_end,
                    e.join_date, e.resign_date,
                    e.bank_name, e.bank_account_name, e.bank_account_number,
                    e.npwp, e.bpjs_kesehatan_no, e.bpjs_tk_no, e.tax_status,
                    CASE WHEN e.google_id IS NULL THEN 0 ELSE 1 END AS google_linked,
                    bu.name AS unit_name, bu.code AS unit_code,
                    d.name AS department_name, d.code AS department_code,
                    p.name AS position_name, p.code AS position_code
               FROM employees e
               LEFT JOIN business_units bu ON bu.id = e.business_unit_id
               LEFT JOIN departments d ON d.id = e.department_id
               LEFT JOIN positions p ON p.id = e.position_id
              WHERE e.id = ?',
            [$employeeId]
        );
    }

    private function profileIdentity(array $employee): array
    {
        $flag = (string) ($employee['flag'] ?? 'ACTIVE');

        return [
            'id'            => (int) $employee['id'],
            'employee_code' => (string) $employee['employee_code'],
            'full_name'     => (string) $employee['full_name'],
            'nickname'      => $employee['nickname'] ?? null,
            'photo_url'     => $this->mediaUrl($employee['avatar'] ?? null) ?: $this->mediaUrl($employee['photo'] ?? null),
            'role'          => (string) ($employee['role'] ?? 'KARYAWAN'),
            'role_label'    => role_label((string) ($employee['role'] ?? 'KARYAWAN')),
            'status'        => [
                'code'  => $flag,
                'label' => match ($flag) {
                    'ACTIVE'  => 'Aktif',
                    'RESIGN'  => 'Resign',
                    'EXPIRED' => 'Habis kontrak',
                    default   => $flag,
                },
            ],
            'is_active'     => $flag === 'ACTIVE',
        ];
    }

    /** Status kepegawaian, tanggal bergabung, masa kerja, dan kontrak. */
    private function employmentPayload(array $employee): array
    {
        $joinDate = $this->isoDate($employee['join_date'] ?? null);
        $contractEnd = $this->isoDate($employee['contract_end'] ?? null);
        $daysRemaining = $contractEnd !== null
            ? (int) floor((strtotime($contractEnd) - strtotime(date('Y-m-d'))) / 86400)
            : null;

        return [
            'status'     => (string) ($employee['employment_status'] ?? ''),
            'join_date'  => $joinDate,
            'tenure'     => $this->tenure($joinDate),
            'contract'   => [
                'start'           => $this->isoDate($employee['contract_start'] ?? null),
                'end'             => $contractEnd,
                'remaining_days'  => $daysRemaining,
                'expiring_soon'   => $daysRemaining !== null && $daysRemaining >= 0 && $daysRemaining <= 60,
                'expired'         => $daysRemaining !== null && $daysRemaining < 0,
            ],
            'resign_date' => $this->isoDate($employee['resign_date'] ?? null),
        ];
    }

    /** @return array{years:int,months:int,days:int,total_days:int,label:string} */
    private function tenure(?string $joinDate): array
    {
        if ($joinDate === null) {
            return ['years' => 0, 'months' => 0, 'days' => 0, 'total_days' => 0, 'label' => '—'];
        }

        $start = new \DateTimeImmutable($joinDate);
        $today = new \DateTimeImmutable(date('Y-m-d'));
        if ($start > $today) {
            return ['years' => 0, 'months' => 0, 'days' => 0, 'total_days' => 0, 'label' => 'Belum mulai'];
        }

        $diff = $start->diff($today);
        $years = (int) $diff->y;
        $months = (int) $diff->m;
        $days = (int) $diff->d;

        $parts = [];
        if ($years > 0) {
            $parts[] = $years . ' tahun';
        }
        if ($months > 0) {
            $parts[] = $months . ' bulan';
        }
        if ($days > 0 && $years === 0) {
            $parts[] = $days . ' hari';
        }

        return [
            'years'      => $years,
            'months'     => $months,
            'days'       => $days,
            'total_days' => (int) $diff->days,
            'label'      => $parts === [] ? 'Baru bergabung' : implode(' ', $parts),
        ];
    }

    private function organizationPayload(array $employee): array
    {
        return [
            'business_unit' => [
                'id'   => isset($employee['business_unit_id']) ? (int) $employee['business_unit_id'] : null,
                'name' => $employee['unit_name'] ?? null,
                'code' => $employee['unit_code'] ?? null,
            ],
            'department'    => [
                'id'   => isset($employee['department_id']) ? (int) $employee['department_id'] : null,
                'name' => $employee['department_name'] ?? null,
                'code' => $employee['department_code'] ?? null,
            ],
            'position'      => [
                'id'   => isset($employee['position_id']) ? (int) $employee['position_id'] : null,
                'name' => $employee['position_name'] ?? null,
                'code' => $employee['position_code'] ?? null,
            ],
        ];
    }

    private function managerPayload(array $employee): ?array
    {
        $managerId = (int) ($employee['direct_manager_id'] ?? 0);
        if ($managerId <= 0) {
            return null;
        }

        $manager = Database::one(
            'SELECT e.id, e.full_name, e.email, e.flag, p.name AS position_name,
                    d.name AS department_name
               FROM employees e
               LEFT JOIN positions p ON p.id = e.position_id
               LEFT JOIN departments d ON d.id = e.department_id
              WHERE e.id = ?',
            [$managerId]
        );
        if ($manager === null) {
            return null;
        }

        return [
            'id'         => (int) $manager['id'],
            'full_name'  => (string) $manager['full_name'],
            'email'      => $manager['email'] ?? null,
            'position'   => $manager['position_name'] ?? null,
            'department' => $manager['department_name'] ?? null,
            'is_active'  => (string) ($manager['flag'] ?? '') === 'ACTIVE',
        ];
    }

    private function personalPayload(array $employee): array
    {
        $birthDate = $this->isoDate($employee['birth_date'] ?? null);
        $age = null;
        if ($birthDate !== null) {
            $birth = new \DateTimeImmutable($birthDate);
            $age = (int) $birth->diff(new \DateTimeImmutable(date('Y-m-d')))->y;
        }

        return [
            'birth_date'     => $birthDate,
            'age'            => $age,
            'gender'         => $employee['gender'] ?? null,
            'marital_status' => $employee['marital_status'] ?? null,
            'religion'       => $employee['religion'] ?? null,
        ];
    }

    private function payrollPayload(array $employee): array
    {
        $salary = (float) ($employee['basic_salary'] ?? 0);
        $account = (string) ($employee['bank_account_number'] ?? '');

        return [
            'basic_salary'       => $salary,
            'basic_salary_label' => rupiah($salary),
            'tax_status'         => (string) ($employee['tax_status'] ?? ''),
            'npwp'               => $employee['npwp'] ?? null,
            'bpjs_kesehatan_no'  => $employee['bpjs_kesehatan_no'] ?? null,
            'bpjs_tk_no'         => $employee['bpjs_tk_no'] ?? null,
            'bank'               => [
                'name'                  => $employee['bank_name'] ?? null,
                'account_name'          => $employee['bank_account_name'] ?? null,
                'account_number'        => $account !== '' ? $account : null,
                'account_number_masked' => $account !== '' ? $this->maskAccount($account) : null,
            ],
        ];
    }

    /** Samarkan nomor rekening: sisakan 4 digit terakhir. */
    private function maskAccount(string $account): string
    {
        $digits = preg_replace('/\s+/', '', $account) ?? '';
        if (mb_strlen($digits) <= 4) {
            return $digits;
        }

        return str_repeat('•', max(0, mb_strlen($digits) - 4)) . mb_substr($digits, -4);
    }

    /** Ringkasan dokumen karyawan (tanpa URL berkas — daftar dokumen belum di API). */
    private function documentSummary(int $employeeId): array
    {
        if (!Database::tableExists('employee_documents')) {
            return ['total' => 0, 'verified' => 0, 'unverified' => 0, 'rejected' => 0, 'expiring_soon' => 0];
        }

        $row = Database::one(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS verified,
                    COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS unverified,
                    COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS rejected,
                    COALESCE(SUM(CASE WHEN expires_at IS NOT NULL AND status <> ?
                                       AND expires_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) AS expiring
               FROM employee_documents
              WHERE employee_id = ?',
            [
                'VERIFIED',
                'UNVERIFIED',
                'REJECTED',
                'REJECTED',
                date('Y-m-d'),
                date('Y-m-d', strtotime('+' . DocumentModel::EXPIRING_DAYS . ' days')),
                $employeeId,
            ]
        ) ?? [];

        return [
            'total'         => (int) ($row['total'] ?? 0),
            'verified'      => (int) ($row['verified'] ?? 0),
            'unverified'    => (int) ($row['unverified'] ?? 0),
            'rejected'      => (int) ($row['rejected'] ?? 0),
            'expiring_soon' => (int) ($row['expiring'] ?? 0),
        ];
    }

    private function hasSubordinates(int $employeeId): bool
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM employees WHERE direct_manager_id = ? AND flag = ?',
            [$employeeId, 'ACTIVE'],
            0
        ) > 0;
    }
}
