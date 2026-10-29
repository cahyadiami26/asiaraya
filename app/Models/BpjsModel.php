<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Rekap iuran BPJS Kesehatan & Ketenagakerjaan (Batch 20).
 *
 * Angka diambil dari komponen payroll `kind = 'BPJS'` pada periode yang sudah
 * final, baik sisi karyawan (`type = 'EMPLOYEE'`) maupun sisi perusahaan
 * (`type = 'EMPLOYER'`), sehingga rekap selalu sama dengan slip gaji.
 */
final class BpjsModel
{
    public const FINAL_STATUSES = ['FINANCE_APPROVED', 'PAID'];

    /** Program BPJS beserta kode komponen gaji & tarif acuan. */
    public const PROGRAMS = [
        'KES' => [
            'label' => 'BPJS Kesehatan',
            'ee'    => 'BPJS_KES_EE',
            'er'    => 'BPJS_KES_ER',
            'tone'  => 'teal',
            'note'  => '4% perusahaan + 1% karyawan (batas upah Rp 12 juta).',
        ],
        'JHT' => [
            'label' => 'Jaminan Hari Tua',
            'ee'    => 'BPJS_JHT_EE',
            'er'    => 'BPJS_JHT_ER',
            'tone'  => 'indigo',
            'note'  => '3,7% perusahaan + 2% karyawan.',
        ],
        'JP' => [
            'label' => 'Jaminan Pensiun',
            'ee'    => 'BPJS_JP_EE',
            'er'    => 'BPJS_JP_ER',
            'tone'  => 'amber',
            'note'  => '2% perusahaan + 1% karyawan (batas upah Rp 10 juta).',
        ],
        'JKK' => [
            'label' => 'Jaminan Kecelakaan Kerja',
            'ee'    => null,
            'er'    => 'BPJS_JKK',
            'tone'  => 'rose',
            'note'  => '0,24% perusahaan (kategori risiko rendah).',
        ],
        'JKM' => [
            'label' => 'Jaminan Kematian',
            'ee'    => null,
            'er'    => 'BPJS_JKM',
            'tone'  => 'slate',
            'note'  => '0,3% perusahaan.',
        ],
    ];

    /**
     * Filter dari query string.
     *
     * `month` = -1 berarti parameter tidak dikirim (halaman memilih masa
     * terakhir yang tersedia), sedangkan 0 berarti pengguna memilih
     * "Semua bulan" secara eksplisit.
     *
     * @return array{year:int,month:int,unit:int}
     */
    public function filtersFromRequest(): array
    {
        $year = (int) ($_GET['year'] ?? 0);
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }
        if (!array_key_exists('month', $_GET)) {
            $month = -1;
        } else {
            $month = (int) ($_GET['month'] ?? 0);
            if ($month < 0 || $month > 12) {
                $month = -1;
            }
        }

        return [
            'year'  => $year,
            'month' => $month,
            'unit'  => (int) ($_GET['unit'] ?? 0),
        ];
    }

    /**
     * Pilih masa pajak default (bulan terakhir yang punya payroll final) bila
     * pengguna belum menentukan bulan.
     *
     * @param array{year:int,month:int,unit:int} $filters
     * @return array{year:int,month:int,unit:int}
     */
    public function resolveMonth(array $filters): array
    {
        if ((int) $filters['month'] === -1) {
            $months = $this->months((int) $filters['year']);
            $filters['month'] = $months ? (int) end($months) : 0;
        }

        return $filters;
    }

    /** @return array<int,int> */
    public function years(): array
    {
        $rows = Database::all(
            "SELECT DISTINCT period_year AS y FROM payroll_periods
              WHERE status IN ('FINANCE_APPROVED', 'PAID') ORDER BY y DESC"
        );
        $years = array_map(static fn (array $row): int => (int) $row['y'], $rows);
        $current = (int) date('Y');
        if (!in_array($current, $years, true)) {
            array_unshift($years, $current);
        }
        $years = array_values(array_unique($years));
        rsort($years);

        return $years;
    }

    /** @return array<int,array<string,mixed>> */
    public function units(): array
    {
        return Database::all('SELECT id, name, code FROM business_units ORDER BY name');
    }

    /**
     * Bulan yang memiliki periode payroll final pada tahun tertentu.
     *
     * @return array<int,int>
     */
    public function months(int $year): array
    {
        $rows = Database::all(
            "SELECT DISTINCT period_month AS m FROM payroll_periods
              WHERE period_year = ? AND status IN ('FINANCE_APPROVED', 'PAID')
              ORDER BY m",
            [$year]
        );

        return array_map(static fn (array $row): int => (int) $row['m'], $rows);
    }

    /**
     * Rincian per karyawan untuk satu bulan (atau setahun bila bulan = 0).
     *
     * Komponen BPJS diagregasi lebih dulu per slip (derived table) supaya angka
     * upah tidak terganda oleh banyaknya komponen pada satu slip, sedangkan
     * upah memakai SUM agar total setahun tetap konsisten dengan iurannya.
     *
     * @param array{year:int,month:int,unit:int} $filters
     * @return array<int,array<string,mixed>>
     */
    public function employees(array $filters): array
    {
        $placeholders = implode(',', array_fill(0, count(self::FINAL_STATUSES), '?'));

        // Derived table: satu baris per slip, sudah dipisah per program.
        $aggregates = [];
        $codeList = [];
        foreach (self::PROGRAMS as $key => $program) {
            $aggregates[] = "SUM(CASE WHEN code = '{$program['er']}' THEN amount ELSE 0 END) AS {$key}_ER";
            $codeList[] = (string) $program['er'];
            if ($program['ee'] !== null) {
                $aggregates[] = "SUM(CASE WHEN code = '{$program['ee']}' THEN amount ELSE 0 END) AS {$key}_EE";
                $codeList[] = (string) $program['ee'];
            }
        }
        $in = implode(',', array_fill(0, count($codeList), '?'));

        $columns = [];
        foreach (array_keys(self::PROGRAMS) as $key) {
            // Wajib dibungkus SUM: satu karyawan bisa punya beberapa slip dalam
            // setahun, sementara query ini dikelompokkan per karyawan.
            $columns[] = "SUM(COALESCE(bp.{$key}_ER, 0)) AS {$key}_ER";
            if (self::PROGRAMS[$key]['ee'] !== null) {
                $columns[] = "SUM(COALESCE(bp.{$key}_EE, 0)) AS {$key}_EE";
            }
        }
        $aggregateSql = implode(",\n                           ", $aggregates);
        $columnSql = implode(",\n                       ", $columns);

        $params = array_merge($codeList, self::FINAL_STATUSES, [(int) $filters['year']]);
        $where = '';
        if (($filters['month'] ?? 0) > 0) {
            $where .= ' AND p.period_month = ?';
            $params[] = (int) $filters['month'];
        }
        if (($filters['unit'] ?? 0) > 0) {
            $where .= ' AND p.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }

        $sql = "SELECT pi.employee_id,
                       MAX(pi.employee_code)   AS employee_code,
                       MAX(pi.employee_name)   AS employee_name,
                       MAX(pi.position_name)   AS position_name,
                       MAX(pi.department_name) AS department_name,
                       MAX(bu.name)            AS unit_name,
                       MAX(e.bpjs_kesehatan_no) AS kes_no,
                       MAX(e.bpjs_tk_no)       AS tk_no,
                       MAX(p.period_month)     AS period_month,
                       SUM(pi.basic_salary)    AS wage,
                       SUM(pi.gross_salary)    AS gross,
                       {$columnSql}
                  FROM payroll_items pi
                  JOIN payroll_periods p ON p.id = pi.period_id
                  LEFT JOIN employees e ON e.id = pi.employee_id
                  LEFT JOIN business_units bu ON bu.id = e.business_unit_id
                  LEFT JOIN (
                        SELECT item_id,
                           {$aggregateSql}
                          FROM payroll_item_components
                         WHERE kind = 'BPJS' AND code IN ({$in})
                         GROUP BY item_id
                  ) bp ON bp.item_id = pi.id
                 WHERE p.status IN ({$placeholders}) AND p.period_year = ?{$where}
                 GROUP BY pi.employee_id
                 ORDER BY MAX(pi.employee_name)";

        $rows = Database::all($sql, $params);

        return array_map(static function (array $row): array {
            $company = 0.0;
            $employee = 0.0;
            foreach (array_keys(self::PROGRAMS) as $key) {
                $company += (float) ($row[$key . '_ER'] ?? 0);
                $employee += (float) ($row[$key . '_EE'] ?? 0);
            }
            $row['company_total'] = round($company, 2);
            $row['employee_total'] = round($employee, 2);
            $row['grand_total'] = round($company + $employee, 2);
            $row['wage'] = round((float) $row['wage'], 2);
            $row['gross'] = round((float) $row['gross'], 2);

            return $row;
        }, $rows);
    }

    /**
     * Rekap per program (total perusahaan, karyawan, dan jumlah peserta).
     *
     * @return array<string,mixed>
     */
    public function summary(array $filters): array
    {
        $rows = $this->employees($filters);
        $programs = [];
        $companyTotal = 0.0;
        $employeeTotal = 0.0;
        $wage = 0.0;

        foreach (self::PROGRAMS as $key => $program) {
            $er = 0.0;
            $ee = 0.0;
            $participants = 0;
            foreach ($rows as $row) {
                $erAmount = (float) ($row[$key . '_ER'] ?? 0);
                $eeAmount = (float) ($row[$key . '_EE'] ?? 0);
                $er += $erAmount;
                $ee += $eeAmount;
                if ($erAmount + $eeAmount > 0) {
                    $participants++;
                }
            }
            $programs[$key] = [
                'key'          => $key,
                'label'        => $program['label'],
                'tone'         => $program['tone'],
                'note'         => $program['note'],
                'company'      => round($er, 2),
                'employee'     => round($ee, 2),
                'total'        => round($er + $ee, 2),
                'participants' => $participants,
            ];
            $companyTotal += $er;
            $employeeTotal += $ee;
        }

        foreach ($rows as $row) {
            $wage += (float) $row['wage'];
        }

        return [
            'programs'       => $programs,
            'employees'      => count($rows),
            'wage'           => round($wage, 2),
            'company_total'  => round($companyTotal, 2),
            'employee_total' => round($employeeTotal, 2),
            'grand_total'    => round($companyTotal + $employeeTotal, 2),
        ];
    }

    /**
     * Rekap total per bulan dalam satu tahun (untuk tren & ringkasan).
     *
     * @return array<int,array<string,mixed>>
     */
    public function yearly(array $filters): array
    {
        $months = $this->months((int) $filters['year']);
        $rows = [];
        foreach ($months as $month) {
            $summary = $this->summary([
                'year'  => (int) $filters['year'],
                'month' => $month,
                'unit'  => (int) $filters['unit'],
            ]);
            $rows[] = [
                'month'          => $month,
                'label'          => bulan_id($month),
                'employees'      => $summary['employees'],
                'wage'           => $summary['wage'],
                'company_total'  => $summary['company_total'],
                'employee_total' => $summary['employee_total'],
                'grand_total'    => $summary['grand_total'],
            ];
        }

        return $rows;
    }

    /**
     * Kelengkapan data peserta (nomor BPJS belum diisi).
     *
     * @return array{active:int,no_kes:int,no_tk:int,missing:int}
     */
    public function coverage(): array
    {
        $row = Database::one(
            "SELECT COUNT(*) AS active,
                    SUM(CASE WHEN bpjs_kesehatan_no IS NULL OR bpjs_kesehatan_no = '' THEN 1 ELSE 0 END) AS no_kes,
                    SUM(CASE WHEN bpjs_tk_no IS NULL OR bpjs_tk_no = '' THEN 1 ELSE 0 END) AS no_tk
               FROM employees WHERE flag = 'ACTIVE'"
        ) ?? [];
        $noKes = (int) ($row['no_kes'] ?? 0);
        $noTk = (int) ($row['no_tk'] ?? 0);

        return [
            'active'  => (int) ($row['active'] ?? 0),
            'no_kes'  => $noKes,
            'no_tk'   => $noTk,
            'missing' => max($noKes, $noTk),
        ];
    }
}
