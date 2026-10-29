<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\EmployeeCode;

/** Organisation structure: business units, departments, positions. */
final class OrgModel
{
    /* --------------------------------------------------------- business unit */
    public function units(): array
    {
        return Database::all(
            "SELECT bu.*,
                    (SELECT COUNT(*) FROM departments d WHERE d.business_unit_id = bu.id AND d.is_active = 1) AS department_count,
                    (SELECT COUNT(*) FROM employees e WHERE e.business_unit_id = bu.id AND e.flag = 'ACTIVE') AS employee_count,
                    (SELECT COUNT(*) FROM employees e2 WHERE e2.business_unit_id = bu.id) AS total_employee
             FROM business_units bu
             ORDER BY bu.is_active DESC, bu.id"
        );
    }

    public function findUnit(int $id): ?array
    {
        return Database::one('SELECT * FROM business_units WHERE id = ?', [$id]);
    }

    /* ------------------------------------------------------------ department */
    public function departments(?int $unitId = null): array
    {
        $sql = "SELECT d.*, bu.name AS unit_name, bu.code AS unit_code,
                       h.full_name AS head_name,
                       (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id AND e.flag = 'ACTIVE') AS employee_count
                FROM departments d
                LEFT JOIN business_units bu ON bu.id = d.business_unit_id
                LEFT JOIN employees h ON h.id = d.head_employee_id";
        $params = [];
        if ($unitId) {
            $sql .= ' WHERE d.business_unit_id = ?';
            $params[] = $unitId;
        }
        $sql .= ' ORDER BY bu.id, d.name';
        return Database::all($sql, $params);
    }

    public function findDepartment(int $id): ?array
    {
        return Database::one('SELECT * FROM departments WHERE id = ?', [$id]);
    }

    /* -------------------------------------------------------------- position */
    public function positions(?int $departmentId = null): array
    {
        $sql = "SELECT p.*, d.name AS department_name,
                       (SELECT COUNT(*) FROM employees e WHERE e.position_id = p.id AND e.flag = 'ACTIVE') AS employee_count
                FROM positions p
                LEFT JOIN departments d ON d.id = p.department_id";
        $params = [];
        if ($departmentId) {
            $sql .= ' WHERE p.department_id = ?';
            $params[] = $departmentId;
        }
        $sql .= ' ORDER BY p.name';
        return Database::all($sql, $params);
    }

    public function findPosition(int $id): ?array
    {
        return Database::one('SELECT * FROM positions WHERE id = ?', [$id]);
    }

    /* ----------------------------------------------------------------- misc */
    public function stats(): array
    {
        return [
            'units'       => (int) Database::value('SELECT COUNT(*) FROM business_units WHERE is_active = 1'),
            'departments' => (int) Database::value('SELECT COUNT(*) FROM departments WHERE is_active = 1'),
            'positions'   => (int) Database::value('SELECT COUNT(*) FROM positions WHERE is_active = 1'),
            'employees'   => (int) Database::value("SELECT COUNT(*) FROM employees WHERE flag = 'ACTIVE'"),
        ];
    }

    /** Active employees for the "kepala departemen" selector. */
    public function employeeOptions(): array
    {
        return Database::all(
            "SELECT id, full_name, employee_code FROM employees WHERE flag = 'ACTIVE' ORDER BY full_name"
        );
    }

    /** Example IDs rendered with the current format for the settings preview. */
    public function idFormatPreview(): array
    {
        $units = Database::all('SELECT id, code, name FROM business_units WHERE is_active = 1 ORDER BY id LIMIT 3');
        $preview = [];
        foreach ($units as $unit) {
            $dept = Database::one(
                'SELECT id, code FROM departments WHERE business_unit_id = ? AND is_active = 1 ORDER BY id LIMIT 1',
                [(int) $unit['id']]
            );
            $preview[] = [
                'unit' => $unit['code'] . ' — ' . $unit['name'],
                'code' => EmployeeCode::preview((int) $unit['id'], $dept ? (int) $dept['id'] : null),
            ];
        }
        return $preview;
    }

    /** Guard: a unit can only be removed when nothing references it. */
    public function unitHasDependents(int $id): array
    {
        return [
            'departments' => (int) Database::value('SELECT COUNT(*) FROM departments WHERE business_unit_id = ?', [$id]),
            'employees'   => (int) Database::value('SELECT COUNT(*) FROM employees WHERE business_unit_id = ?', [$id]),
        ];
    }

    public function departmentHasDependents(int $id): array
    {
        return [
            'positions' => (int) Database::value('SELECT COUNT(*) FROM positions WHERE department_id = ?', [$id]),
            'employees' => (int) Database::value('SELECT COUNT(*) FROM employees WHERE department_id = ?', [$id]),
        ];
    }

    public function positionHasDependents(int $id): array
    {
        return ['employees' => (int) Database::value('SELECT COUNT(*) FROM employees WHERE position_id = ?', [$id])];
    }
}
