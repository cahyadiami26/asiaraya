<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Riwayat jabatan / mutasi karyawan (Batch 19).
 *
 * Setiap baris menyimpan snapshot nama unit, departemen, dan jabatan pada saat
 * perubahan terjadi, sehingga riwayat tetap terbaca walau master organisasi
 * kelak diubah namanya atau dihapus.
 */
final class CareerModel
{
    public const TYPES = [
        'INITIAL'   => ['label' => 'Pengangkatan Awal', 'tone' => 'slate', 'icon' => 'pin'],
        'PROMOTION' => ['label' => 'Promosi', 'tone' => 'teal', 'icon' => 'trending'],
        'MUTATION'  => ['label' => 'Mutasi', 'tone' => 'indigo', 'icon' => 'refresh'],
        'DEMOTION'  => ['label' => 'Demosi', 'tone' => 'rose', 'icon' => 'trending-down'],
        'CONTRACT'  => ['label' => 'Perpanjangan Kontrak', 'tone' => 'amber', 'icon' => 'file'],
        'SALARY'    => ['label' => 'Penyesuaian Gaji', 'tone' => 'indigo', 'icon' => 'wallet'],
        'STATUS'    => ['label' => 'Perubahan Status', 'tone' => 'slate', 'icon' => 'user'],
    ];

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type]['label'] ?? ucfirst(strtolower($type));
    }

    public static function typeTone(string $type): string
    {
        return self::TYPES[$type]['tone'] ?? 'slate';
    }

    /* ================================================================ filter */

    /** @return array<string,mixed> */
    public function filtersFromRequest(): array
    {
        $type = (string) ($_GET['type'] ?? '');
        $year = (int) ($_GET['year'] ?? 0);

        return [
            'q'        => trim((string) ($_GET['q'] ?? '')),
            'type'     => isset(self::TYPES[$type]) ? $type : '',
            'year'     => $year >= 2000 && $year <= 2100 ? $year : 0,
            'employee' => (int) ($_GET['employee'] ?? 0),
            'unit'     => (int) ($_GET['unit'] ?? 0),
        ];
    }

    /** @return array{0:string,1:array} */
    private function where(array $filters): array
    {
        $clauses = [];
        $params = [];

        if (($filters['q'] ?? '') !== '') {
            $clauses[] = '(e.full_name LIKE ? OR e.employee_code LIKE ? OR h.sk_number LIKE ? OR h.position_name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (($filters['type'] ?? '') !== '') {
            $clauses[] = 'h.change_type = ?';
            $params[] = $filters['type'];
        }
        if (($filters['year'] ?? 0) > 0) {
            $clauses[] = 'h.effective_date >= ? AND h.effective_date <= ?';
            $params[] = $filters['year'] . '-01-01';
            $params[] = $filters['year'] . '-12-31';
        }
        if (($filters['employee'] ?? 0) > 0) {
            $clauses[] = 'h.employee_id = ?';
            $params[] = (int) $filters['employee'];
        }
        if (($filters['unit'] ?? 0) > 0) {
            $clauses[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }

        return [$clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    public function count(array $filters): int
    {
        [$where, $params] = $this->where($filters);
        return (int) Database::value(
            'SELECT COUNT(*) FROM employee_job_history h
               JOIN employees e ON e.id = h.employee_id' . $where,
            $params,
            0
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function paginate(array $filters, int $limit = 20, int $offset = 0): array
    {
        [$where, $params] = $this->where($filters);

        return Database::all(
            'SELECT h.*, e.full_name, e.employee_code, e.photo, e.flag,
                    c.full_name AS creator_name
               FROM employee_job_history h
               JOIN employees e ON e.id = h.employee_id
               LEFT JOIN employees c ON c.id = h.created_by' . $where . '
              ORDER BY h.effective_date DESC, h.id DESC
              LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $params
        );
    }

    public function find(int $id): ?array
    {
        return Database::one(
            'SELECT h.*, e.full_name, e.employee_code, e.photo,
                    c.full_name AS creator_name
               FROM employee_job_history h
               JOIN employees e ON e.id = h.employee_id
               LEFT JOIN employees c ON c.id = h.created_by
              WHERE h.id = ?',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function forEmployee(int $employeeId, int $limit = 20): array
    {
        return Database::all(
            'SELECT h.*, c.full_name AS creator_name
               FROM employee_job_history h
               LEFT JOIN employees c ON c.id = h.created_by
              WHERE h.employee_id = ?
              ORDER BY h.effective_date DESC, h.id DESC
              LIMIT ' . max(1, $limit),
            [$employeeId]
        );
    }

    /** Riwayat terakhir sebelum sebuah tanggal (untuk menghitung selisih gaji). */
    public function previousRecord(int $employeeId, string $effectiveDate): ?array
    {
        return Database::one(
            'SELECT * FROM employee_job_history
              WHERE employee_id = ? AND effective_date < ?
              ORDER BY effective_date DESC, id DESC
              LIMIT 1',
            [$employeeId, $effectiveDate]
        );
    }

    /* ================================================================= mutasi */

    /**
     * Catat perubahan jabatan/posisi.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $employee baris employees sebelum perubahan
     * @return int id riwayat
     */
    public function create(array $data, array $employee, int $createdBy, bool $apply = true): int
    {
        // Bidang yang dikosongkan mengikuti data karyawan saat ini agar setiap
        // baris riwayat tetap menampilkan posisi lengkap pada tanggal tersebut.
        $unitId = (int) ($data['business_unit_id'] ?? 0) ?: (int) ($employee['business_unit_id'] ?? 0);
        $deptId = (int) ($data['department_id'] ?? 0) ?: (int) ($employee['department_id'] ?? 0);
        $positionId = (int) ($data['position_id'] ?? 0) ?: (int) ($employee['position_id'] ?? 0);

        $unitName = (string) Database::value('SELECT name FROM business_units WHERE id = ?', [$unitId], '');
        $deptName = $deptId ? (string) Database::value('SELECT name FROM departments WHERE id = ?', [$deptId], '') : '';
        $positionName = $positionId ? (string) Database::value('SELECT name FROM positions WHERE id = ?', [$positionId], '') : '';

        $salary = $data['basic_salary'] !== '' && $data['basic_salary'] !== null
            ? (float) $data['basic_salary']
            : (float) ($employee['basic_salary'] ?? 0);
        $previousSalary = (float) ($employee['basic_salary'] ?? 0);

        $status = trim((string) ($data['employment_status'] ?? '')) ?: (string) ($employee['employment_status'] ?? '');

        $id = Database::insert('employee_job_history', [
            'employee_id'       => (int) $employee['id'],
            'change_type'       => (string) $data['change_type'],
            'effective_date'    => (string) $data['effective_date'],
            'end_date'          => ($data['end_date'] ?? null) ?: null,
            'business_unit_id'  => $unitId ?: null,
            'department_id'     => $deptId,
            'position_id'       => $positionId,
            'unit_name'         => $unitName ?: null,
            'department_name'   => $deptName ?: null,
            'position_name'     => $positionName ?: null,
            'employment_status' => $status ?: null,
            'basic_salary'      => $salary,
            'previous_salary'   => $previousSalary,
            'sk_number'         => trim((string) ($data['sk_number'] ?? '')) ?: null,
            'note'              => trim((string) ($data['note'] ?? '')) ?: null,
            'created_by'        => $createdBy,
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        if ($apply) {
            $payload = [
                'business_unit_id'  => $unitId ?: null,
                'employment_status' => $status ?: ($employee['employment_status'] ?? null),
                'basic_salary'      => $salary,
                'updated_at'        => date('Y-m-d H:i:s'),
            ];
            if ($deptId) {
                $payload['department_id'] = $deptId;
            }
            if ($positionId) {
                $payload['position_id'] = $positionId;
            }
            Database::update('employees', $payload, 'id = ?', [(int) $employee['id']]);
        }

        return $id;
    }

    public function delete(int $id): bool
    {
        return Database::delete('employee_job_history', 'id = ?', [$id]) > 0;
    }

    public function deleteForEmployee(int $employeeId): int
    {
        return Database::delete('employee_job_history', 'employee_id = ?', [$employeeId]);
    }

    /* ============================================================== statistik */

    public function stats(): array
    {
        $year = (int) date('Y');
        $row = Database::one(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN change_type = 'PROMOTION' THEN 1 ELSE 0 END) AS promotions,
                    SUM(CASE WHEN change_type = 'MUTATION' THEN 1 ELSE 0 END) AS mutations,
                    COUNT(DISTINCT employee_id) AS employees
               FROM employee_job_history"
        ) ?? [];

        return [
            'total'      => (int) ($row['total'] ?? 0),
            'promotions' => (int) ($row['promotions'] ?? 0),
            'mutations'  => (int) ($row['mutations'] ?? 0),
            'employees'  => (int) ($row['employees'] ?? 0),
            'this_year'  => (int) Database::value(
                'SELECT COUNT(*) FROM employee_job_history WHERE effective_date >= ?',
                [$year . '-01-01'],
                0
            ),
            'salary_up'  => (int) Database::value(
                'SELECT COUNT(*) FROM employee_job_history WHERE basic_salary > previous_salary',
                [],
                0
            ),
            'latest'     => Database::one(
                'SELECT h.*, e.full_name FROM employee_job_history h
                   JOIN employees e ON e.id = h.employee_id
                  ORDER BY h.effective_date DESC, h.id DESC LIMIT 1'
            ),
        ];
    }

    /** Tahun yang tersedia pada filter riwayat. */
    public function years(): array
    {
        $rows = Database::all(
            'SELECT DISTINCT SUBSTR(effective_date, 1, 4) AS y FROM employee_job_history ORDER BY y DESC'
        );
        $years = array_map(static fn (array $row): int => (int) $row['y'], $rows);
        $current = (int) date('Y');
        if (!in_array($current, $years, true)) {
            array_unshift($years, $current);
        }
        return $years;
    }

    /** Peta karyawan => jumlah perubahan (untuk panel "paling dinamis"). */
    public function mostChanged(int $limit = 5): array
    {
        return Database::all(
            'SELECT e.id, e.full_name, e.employee_code, COUNT(h.id) AS total,
                    MAX(h.effective_date) AS last_change
               FROM employee_job_history h
               JOIN employees e ON e.id = h.employee_id
              GROUP BY e.id, e.full_name, e.employee_code
              ORDER BY total DESC, last_change DESC
              LIMIT ' . max(1, $limit)
        );
    }
}
