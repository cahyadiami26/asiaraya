<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Employee directory: filtering, pagination, persistence. */
final class EmployeeModel
{
    /** Whitelisted columns accepted from the employee form. */
    private const FIELDS = [
        'employee_code', 'username', 'full_name', 'nickname', 'email', 'phone', 'address',
        'birth_date', 'gender', 'marital_status', 'religion', 'photo',
        'role', 'direct_manager_id', 'business_unit_id', 'department_id', 'position_id',
        'basic_salary', 'employment_status', 'contract_start', 'contract_end', 'join_date', 'flag',
        'bank_name', 'bank_account_name', 'bank_account_number', 'npwp',
        'bpjs_kesehatan_no', 'bpjs_tk_no', 'tax_status',
    ];

    /**
     * @param array{q?:string,unit?:int,department?:int,status?:string,flag?:string,contract?:string,sort?:string} $filters
     * @return array{rows:array,total:int}
     */
    public function paginate(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $order = $this->buildOrder($filters['sort'] ?? '');

        $total = (int) Database::value(
            "SELECT COUNT(*) FROM employees e
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE {$where}",
            $params
        );

        $rows = Database::all(
            "SELECT e.id, e.employee_code, e.full_name, e.email, e.phone, e.photo,
                    e.employment_status, e.flag, e.contract_end, e.join_date, e.basic_salary, e.role,
                    bu.name AS unit_name, bu.code AS unit_code,
                    d.name AS department_name, p.name AS position_name,
                    m.full_name AS manager_name
             FROM employees e
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN positions p ON p.id = e.position_id
             LEFT JOIN employees m ON m.id = e.direct_manager_id
             WHERE {$where}
             ORDER BY {$order}
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        return (int) Database::value(
            "SELECT COUNT(*) FROM employees e
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE {$where}",
            $params
        );
    }

    private function buildWhere(array $filters): array
    {
        $clauses = ['1 = 1'];
        $params = [];

        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $clauses[] = '(e.full_name LIKE ? OR e.employee_code LIKE ? OR e.email LIKE ? OR e.username LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($filters['unit'])) {
            $clauses[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (!empty($filters['department'])) {
            $clauses[] = 'e.department_id = ?';
            $params[] = (int) $filters['department'];
        }
        if (!empty($filters['status'])) {
            $clauses[] = 'e.employment_status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['flag'])) {
            $clauses[] = 'e.flag = ?';
            $params[] = $filters['flag'];
        }
        if (($filters['contract'] ?? '') === 'expiring') {
            $clauses[] = "e.flag = 'ACTIVE' AND e.contract_end IS NOT NULL AND e.contract_end BETWEEN ? AND ?";
            $params[] = date('Y-m-d');
            $params[] = date('Y-m-d', strtotime('+45 days'));
        }

        return [implode(' AND ', $clauses), $params];
    }

    private function buildOrder(string $sort): string
    {
        return match ($sort) {
            'name_desc'   => 'e.full_name DESC',
            'code'        => 'e.employee_code ASC',
            'salary_desc' => 'e.basic_salary DESC',
            'salary_asc'  => 'e.basic_salary ASC',
            'newest'      => 'e.join_date DESC, e.id DESC',
            'contract'    => 'e.contract_end ASC',
            default       => 'e.full_name ASC',
        };
    }

    public function find(int $id): ?array
    {
        return Database::one(
            'SELECT e.*, bu.name AS unit_name, bu.code AS unit_code, bu.logo AS unit_logo,
                    bu.city AS unit_city, bu.latitude AS unit_latitude, bu.longitude AS unit_longitude,
                    d.name AS department_name, d.code AS department_code,
                    p.name AS position_name, p.level AS position_level,
                    m.full_name AS manager_name, m.employee_code AS manager_code, m.email AS manager_email,
                    c.name AS company_name
             FROM employees e
             LEFT JOIN business_units bu ON bu.id = e.business_unit_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN positions p ON p.id = e.position_id
             LEFT JOIN employees m ON m.id = e.direct_manager_id
             LEFT JOIN companies c ON c.id = bu.company_id
             WHERE e.id = ?',
            [$id]
        );
    }

    /** Managers = active employees, optionally scoped to a unit. */
    public function managerOptions(?int $unitId = null, ?int $excludeId = null): array
    {
        $sql = "SELECT id, full_name, employee_code, role FROM employees WHERE flag = 'ACTIVE'";
        $params = [];
        if ($unitId) {
            $sql .= ' AND (business_unit_id = ? OR role = ?)';
            $params[] = $unitId;
            $params[] = 'HR';
        }
        if ($excludeId) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' ORDER BY full_name';
        return Database::all($sql, $params);
    }

    public function emailExists(string $email, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM employees WHERE LOWER(email) = ?';
        $params = [strtolower($email)];
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (bool) Database::value($sql, $params);
    }

    public function codeExists(string $code, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM employees WHERE UPPER(employee_code) = ?';
        $params = [strtoupper($code)];
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (bool) Database::value($sql, $params);
    }

    public function create(array $data): int
    {
        $record = $this->filter($data);
        $record['created_at'] = date('Y-m-d H:i:s');
        $record['updated_at'] = $record['created_at'];
        if (!empty($data['password'])) {
            $record['password_hash'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }
        return Database::insert('employees', $record);
    }

    public function update(int $id, array $data): void
    {
        $record = $this->filter($data);
        $record['updated_at'] = date('Y-m-d H:i:s');
        if (!empty($data['password'])) {
            $record['password_hash'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }
        Database::update('employees', $record, 'id = ?', [$id]);
    }

    /** Only keep known columns and normalise empty strings to NULL for dates. */
    private function filter(array $data): array
    {
        $record = [];
        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if ($value === '') {
                $value = null;
            }
            if (in_array($field, ['direct_manager_id', 'business_unit_id', 'department_id', 'position_id'], true)) {
                $value = $value === null ? null : (int) $value;
            }
            if ($field === 'basic_salary') {
                $value = (float) ($value ?? 0);
            }
            if (in_array($field, ['employee_code', 'username', 'email', 'role', 'employment_status', 'flag', 'tax_status'], true) && $value !== null) {
                $value = trim((string) $value);
            }
            $record[$field] = $value;
        }
        return $record;
    }

    /** Counters for the directory header. */
    public function stats(): array
    {
        return [
            'active'      => (int) Database::value("SELECT COUNT(*) FROM employees WHERE flag = 'ACTIVE'"),
            'permanent'   => (int) Database::value("SELECT COUNT(*) FROM employees WHERE flag = 'ACTIVE' AND employment_status LIKE 'PKWTT%'"),
            'contract'    => (int) Database::value("SELECT COUNT(*) FROM employees WHERE flag = 'ACTIVE' AND employment_status LIKE 'PKWT%'"),
            'resign'      => (int) Database::value("SELECT COUNT(*) FROM employees WHERE flag = 'RESIGN'"),
            'expired'     => (int) Database::value("SELECT COUNT(*) FROM employees WHERE flag = 'EXPIRED'"),
            'expiring_45' => (int) Database::value(
                "SELECT COUNT(*) FROM employees WHERE flag = 'ACTIVE' AND contract_end IS NOT NULL AND contract_end BETWEEN ? AND ?",
                [date('Y-m-d'), date('Y-m-d', strtotime('+45 days'))]
            ),
        ];
    }

    /** Departments of a unit (used by the cascading selects). */
    public function departmentsOf(?int $unitId): array
    {
        if (!$unitId) {
            return Database::all('SELECT id, name, code FROM departments WHERE is_active = 1 ORDER BY name');
        }
        return Database::all(
            'SELECT id, name, code FROM departments WHERE business_unit_id = ? AND is_active = 1 ORDER BY name',
            [$unitId]
        );
    }

    public function positionsOf(?int $departmentId): array
    {
        if (!$departmentId) {
            return Database::all('SELECT id, name, code FROM positions WHERE is_active = 1 ORDER BY name');
        }
        return Database::all(
            'SELECT id, name, code, basic_salary FROM positions WHERE department_id = ? AND is_active = 1 ORDER BY name',
            [$departmentId]
        );
    }

    public function recentActivity(int $employeeId, int $limit = 8): array
    {
        return Database::all(
            'SELECT * FROM activity_logs WHERE employee_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit),
            [$employeeId]
        );
    }

    /** Options for the unit filter. */
    public function unitOptions(): array
    {
        return Database::all('SELECT id, name, code FROM business_units WHERE is_active = 1 ORDER BY name');
    }

    /** Distinct employment statuses in use. */
    public function statusOptions(): array
    {
        $rows = Database::all('SELECT DISTINCT employment_status FROM employees ORDER BY employment_status');
        return array_values(array_filter(array_map(static fn ($r) => $r['employment_status'], $rows)));
    }

    /** Mark an employee as resigned (keeps history, frees the seat). */
    public function resign(int $id, string $date, ?string $reason = null): void
    {
        Database::update('employees', [
            'flag'        => 'RESIGN',
            'resign_date' => $date,
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        if ($reason !== null) {
            Database::insert('activity_logs', [
                'employee_id'   => \App\Core\Auth::id(),
                'action'        => 'employee.resign',
                'entity'        => 'employee',
                'entity_id'     => $id,
                'meta'          => json_encode(['reason' => $reason, 'resign_date' => $date], JSON_UNESCAPED_UNICODE),
                'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'    => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Auto-expire contracts that passed their end date.
     * Called when the directory is opened so the flag stays truthful.
     */
    public function refreshContractFlags(): int
    {
        return Database::run(
            "UPDATE employees SET flag = 'EXPIRED', updated_at = ?
             WHERE flag = 'ACTIVE' AND contract_end IS NOT NULL AND contract_end < ?",
            [date('Y-m-d H:i:s'), date('Y-m-d')]
        )->rowCount();
    }
}
