<?php
/**
 * Demo dataset for PT Asia Raya Group.
 * Idempotent: skips when employees already exist (unless --fresh is used).
 */
declare(strict_types=1);

use App\Core\Database;
use App\Core\Settings;

return static function (): array {
    $log = [];
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    $password = password_hash('Password123!', PASSWORD_DEFAULT);
    mt_srand(20261029);

    /* ---------------- Company & org ---------------- */
    $companyId = Database::insert('companies', [
        'name'       => 'PT Asia Raya Group',
        'legal_name' => 'PT Asia Raya Group',
        'address'    => 'Menara Asia Raya, Jl. Jend. Sudirman Kav. 52-53',
        'city'       => 'Jakarta Selatan',
        'province'   => 'DKI Jakarta',
        'postal_code'=> '12190',
        'phone'      => '+62 21 5150 8000',
        'email'      => 'hr@asiaraya.co.id',
        'website'    => 'https://asiaraya.co.id',
        'npwp'       => '01.234.567.8-091.000',
        'timezone'   => 'Asia/Jakarta',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $units = [
        ['JKT', 'Jakarta HQ (Pusat)', 'Head Office', 'Menara Asia Raya, Jakarta Selatan', 'Jakarta', -6.2241000, 106.8089000, 50, 25, 28],
        ['SBY', 'Surabaya Plant', 'Manufacturing', 'Kawasan Industri Rungkut, Surabaya', 'Surabaya', -7.3365000, 112.7509000, 80, 25, 28],
        ['MDN', 'Medan Branch', 'Branch Office', 'Jl. Gatot Subroto, Medan', 'Medan', 3.5952000, 98.6722000, 60, 25, 28],
        ['BDG', 'Bandung Branch', 'Branch Office', 'Jl. Asia Afrika, Bandung', 'Bandung', -6.9175000, 107.6191000, 60, 25, 28],
    ];
    $buIds = [];
    foreach ($units as [$code, $name, $type, $address, $city, $lat, $lng, $radius, $cutoff, $payDay]) {
        $buIds[$code] = Database::insert('business_units', [
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'address' => $address,
            'city' => $city,
            'latitude' => $lat,
            'longitude' => $lng,
            'geofence_radius' => $radius,
            'payroll_cutoff_day' => $cutoff,
            'payroll_pay_day' => $payDay,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $departmentMap = [
        'JKT' => [
            ['HRGA', 'HR & General Affairs'],
            ['FIN', 'Finance & Accounting'],
            ['IT', 'Teknologi Informasi'],
            ['OPS', 'Operasional'],
            ['MKT', 'Marketing & Komunikasi'],
        ],
        'SBY' => [
            ['SCM', 'Supply Chain & Gudang'],
            ['PRD', 'Produksi'],
            ['QA', 'Quality Assurance'],
        ],
        'MDN' => [
            ['OPS', 'Operasional & Kualitas'],
            ['LOG', 'Logistik'],
        ],
        'BDG' => [
            ['COM', 'Komersial & Pemasaran'],
            ['SLS', 'Sales'],
        ],
    ];
    $deptIds = [];
    foreach ($departmentMap as $buCode => $depts) {
        foreach ($depts as [$code, $name]) {
            $deptIds[$buCode . '-' . $code] = Database::insert('departments', [
                'business_unit_id' => $buIds[$buCode],
                'code' => $code,
                'name' => $name,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    $positionMap = [
        ['HR Super Admin', 'Manager', 18000000],
        ['Finance Analyst', 'Analyst', 9500000],
        ['Finance Manager', 'Manager', 17500000],
        ['Software Engineer', 'Engineer', 14000000],
        ['IT Dept Head', 'Head', 24000000],
        ['Supervisor Logistik', 'Supervisor', 11000000],
        ['Sales Representative', 'Staff', 7500000],
        ['Koordinator QA', 'Supervisor', 12500000],
        ['Branch Manager', 'Manager', 19000000],
        ['Staff Operasional', 'Staff', 6500000],
        ['Operator Produksi', 'Operator', 5500000],
        ['Supply Chain Officer', 'Staff', 8500000],
        ['Marketing Executive', 'Staff', 8000000],
    ];
    $positionIds = [];
    foreach ($positionMap as $i => [$name, $level, $salary]) {
        $positionIds[$name] = Database::insert('positions', [
            'department_id' => null,
            'code' => 'POS-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
            'name' => $name,
            'level' => $level,
            'basic_salary' => $salary,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /* ---------------- Shift templates ---------------- */
    $shiftIds = [];
    $shifts = [
        ['SHIFT-PAGI', 'Shift Pagi', 'NORMAL', '08:00', '17:00', 60, 15, '1,2,3,4,5', '#0d9488'],
        ['SHIFT-SIANG', 'Shift Siang', 'NORMAL', '13:00', '22:00', 60, 15, '1,2,3,4,5', '#0ea5e9'],
        ['SHIFT-MALAM', 'Shift Malam', 'NORMAL', '22:00', '07:00', 60, 15, '1,2,3,4,5,6', '#6366f1'],
        ['SHIFT-WFH', 'WFH Fleksibel', 'WFH', '08:30', '17:30', 60, 30, '1,2,3,4,5', '#a855f7'],
        ['SHIFT-PABRIK', 'Shift Pabrik A', 'NORMAL', '07:00', '15:00', 45, 10, '1,2,3,4,5,6', '#f59e0b'],
    ];
    foreach ($shifts as [$code, $name, $type, $in, $out, $break, $tolerance, $days, $color]) {
        $shiftIds[$code] = Database::insert('work_shifts', [
            'business_unit_id' => null,
            'name' => $name,
            'code' => $code,
            'operational_type' => $type,
            'check_in_time' => $in,
            'check_out_time' => $out,
            'break_minutes' => $break,
            'late_tolerance_minutes' => $tolerance,
            'work_days' => $days,
            'color' => $color,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /* ---------------- Employees ---------------- */
    $people = [
        // code, name, email, role, BU, dept, position, manager(index), salary, status, contract_end, flag, gender
        ['ARG-JKT-0001', 'Siti Rahmawati', 'hr@asiaraya.co.id', 'HR', 'JKT', 'HRGA', 'HR Super Admin', null, 18000000, 'PKWTT', null, 'ACTIVE', 'P'],
        ['ARG-JKT-0002', 'Anisa Wijaya', 'finance@asiaraya.co.id', 'FIN', 'JKT', 'FIN', 'Finance Manager', null, 17500000, 'PKWTT', null, 'ACTIVE', 'P'],
        ['ARG-JKT-0100', 'Budi Santoso', 'budi.santoso@asiaraya.co.id', 'KARYAWAN', 'JKT', 'IT', 'IT Dept Head', null, 24000000, 'PKWTT', null, 'ACTIVE', 'L'],
        ['ARG-JKT-0421', 'Dimas Aditya', 'dimas.aditya@asiaraya.co.id', 'KARYAWAN', 'JKT', 'IT', 'Software Engineer', 2, 14000000, 'PKWTT', null, 'ACTIVE', 'L'],
        ['ARG-JKT-1102', 'Fajar Maulana', 'fajar.maulana@asiaraya.co.id', 'KARYAWAN', 'JKT', 'FIN', 'Finance Analyst', 1, 9500000, 'PKWT', date('Y-m-d', strtotime('+30 days')), 'ACTIVE', 'L'],
        ['ARG-SBY-0121', 'Hendra Pratama', 'hendra.pratama@asiaraya.co.id', 'KARYAWAN', 'SBY', 'SCM', 'Supervisor Logistik', null, 11000000, 'PKWTT', null, 'ACTIVE', 'L'],
        ['ARG-BDG-0319', 'Nadia Wulandari', 'nadia.wulandari@asiaraya.co.id', 'KARYAWAN', 'BDG', 'COM', 'Sales Representative', null, 7500000, 'PKWT', date('Y-m-d', strtotime('+90 days')), 'ACTIVE', 'P'],
        ['ARG-MDN-0104', 'Tri Saputra', 'tri.saputra@asiaraya.co.id', 'KARYAWAN', 'MDN', 'OPS', 'Koordinator QA', null, 12500000, 'PKWT', null, 'ACTIVE', 'L'],
        ['ARG-SBY-0202', 'Rina Kusuma', 'rina.kusuma@asiaraya.co.id', 'KARYAWAN', 'SBY', 'PRD', 'Operator Produksi', 5, 5500000, 'PKWT', date('Y-m-d', strtotime('+45 days')), 'ACTIVE', 'P'],
        ['ARG-SBY-0203', 'Agus Setiawan', 'agus.setiawan@asiaraya.co.id', 'KARYAWAN', 'SBY', 'PRD', 'Operator Produksi', 5, 5600000, 'PKWT', null, 'ACTIVE', 'L'],
        ['ARG-SBY-0204', 'Maya Sari', 'maya.sari@asiaraya.co.id', 'KARYAWAN', 'SBY', 'QA', 'Koordinator QA', 5, 9000000, 'PKWTT', null, 'ACTIVE', 'P'],
        ['ARG-SBY-0205', 'Rizky Pratama', 'rizky.pratama@asiaraya.co.id', 'KARYAWAN', 'SBY', 'QA', 'Staff Operasional', 5, 6500000, 'PKWT', date('Y-m-d', strtotime('+30 days')), 'ACTIVE', 'L'],
        ['ARG-JKT-0425', 'Sinta Dewi', 'sinta.dewi@asiaraya.co.id', 'KARYAWAN', 'JKT', 'IT', 'Software Engineer', 2, 13500000, 'PKWTT', null, 'ACTIVE', 'P'],
        ['ARG-JKT-0426', 'Yoga Pratama', 'yoga.pratama@asiaraya.co.id', 'KARYAWAN', 'JKT', 'IT', 'Software Engineer', 2, 13000000, 'PKWT', date('Y-m-d', strtotime('+180 days')), 'ACTIVE', 'L'],
        ['ARG-JKT-0430', 'Intan Permata', 'intan.permata@asiaraya.co.id', 'KARYAWAN', 'JKT', 'HRGA', 'Staff Operasional', 0, 7000000, 'PKWTT', null, 'ACTIVE', 'P'],
        ['ARG-JKT-0431', 'Dedi Kurniawan', 'dedi.kurniawan@asiaraya.co.id', 'KARYAWAN', 'JKT', 'HRGA', 'Staff Operasional', 0, 6800000, 'PKWT', null, 'ACTIVE', 'L'],
        ['ARG-JKT-1103', 'Lestari Ningsih', 'lestari.ningsih@asiaraya.co.id', 'KARYAWAN', 'JKT', 'FIN', 'Finance Analyst', 1, 9200000, 'PKWTT', null, 'ACTIVE', 'P'],
        ['ARG-JKT-1104', 'Bayu Nugroho', 'bayu.nugroho@asiaraya.co.id', 'KARYAWAN', 'JKT', 'FIN', 'Staff Operasional', 1, 7200000, 'PKWT', null, 'ACTIVE', 'L'],
        ['ARG-JKT-0501', 'Ayu Lestari', 'ayu.lestari@asiaraya.co.id', 'KARYAWAN', 'JKT', 'MKT', 'Marketing Executive', 0, 8200000, 'PKWTT', null, 'ACTIVE', 'P'],
        ['ARG-JKT-0502', 'Rangga Saputra', 'rangga.saputra@asiaraya.co.id', 'KARYAWAN', 'JKT', 'MKT', 'Marketing Executive', 0, 8000000, 'PKWT', null, 'ACTIVE', 'L'],
        ['ARG-MDN-0105', 'Sri Handayani', 'sri.handayani@asiaraya.co.id', 'KARYAWAN', 'MDN', 'LOG', 'Supply Chain Officer', 7, 8600000, 'PKWT', null, 'ACTIVE', 'P'],
        ['ARG-MDN-0106', 'Joko Susilo', 'joko.susilo@asiaraya.co.id', 'KARYAWAN', 'MDN', 'LOG', 'Staff Operasional', 7, 6300000, 'PKWT', date('Y-m-d', strtotime('+20 days')), 'ACTIVE', 'L'],
        ['ARG-MDN-0107', 'Fitri Ramadhani', 'fitri.ramadhani@asiaraya.co.id', 'KARYAWAN', 'MDN', 'OPS', 'Staff Operasional', 7, 6400000, 'PKWTT', null, 'ACTIVE', 'P'],
        ['ARG-BDG-0320', 'Iwan Setiadi', 'iwan.setiadi@asiaraya.co.id', 'KARYAWAN', 'BDG', 'SLS', 'Sales Representative', 6, 7400000, 'PKWT', null, 'ACTIVE', 'L'],
        ['ARG-BDG-0321', 'Dewi Anggraini', 'dewi.anggraini@asiaraya.co.id', 'KARYAWAN', 'BDG', 'COM', 'Marketing Executive', 6, 7900000, 'PKWTT', null, 'ACTIVE', 'P'],
        ['ARG-BDG-0322', 'Taufik Hidayat', 'taufik.hidayat@asiaraya.co.id', 'KARYAWAN', 'BDG', 'SLS', 'Sales Representative', 6, 7300000, 'PKWT', date('Y-m-d', strtotime('+60 days')), 'ACTIVE', 'L'],
        ['ARG-SBY-0206', 'Wahyu Santoso', 'wahyu.santoso@asiaraya.co.id', 'KARYAWAN', 'SBY', 'PRD', 'Operator Produksi', 5, 5700000, 'PKWT', null, 'ACTIVE', 'L'],
        ['ARG-SBY-0207', 'Nur Aisyah', 'nur.aisyah@asiaraya.co.id', 'KARYAWAN', 'SBY', 'SCM', 'Supply Chain Officer', 5, 8400000, 'PKWTT', null, 'ACTIVE', 'P'],
        // Resigned / expired examples
        ['ARG-JKT-0432', 'Gilang Ramadhan', 'gilang.ramadhan@asiaraya.co.id', 'KARYAWAN', 'JKT', 'OPS', 'Staff Operasional', 0, 6900000, 'PKWT', date('Y-m-d', strtotime('-10 days')), 'EXPIRED', 'L'],
        ['ARG-SBY-0208', 'Putri Ayuningtyas', 'putri.ayuningtyas@asiaraya.co.id', 'KARYAWAN', 'SBY', 'QA', 'Staff Operasional', 5, 6200000, 'PKWT', date('Y-m-d', strtotime('-90 days')), 'RESIGN', 'P'],
    ];

    $employeeIds = [];
    $seq = [];
    foreach ($people as $index => $p) {
        [$code, $name, $email, $role, $bu, $dept, $position, $managerIndex, $salary, $status, $contractEnd, $flag, $gender] = $p;
        $seq[$bu] = ($seq[$bu] ?? 0) + 1;
        $username = strtolower(preg_replace('/[^a-z]+/i', '.', $name));
        $contractStart = date('Y-m-d', strtotime('-' . (12 + ($index * 3)) . ' months'));
        $id = Database::insert('employees', [
            'employee_code' => $code,
            'username'      => $username,
            'full_name'     => $name,
            'nickname'      => explode(' ', $name)[0],
            'email'         => $email,
            'phone'         => '08' . mt_rand(1000000000, 9999999999),
            'address'       => 'Jl. ' . ['Melati', 'Kenanga', 'Sudirman', 'Diponegoro', 'Cendana', 'Anggrek'][mt_rand(0, 5)] . ' No. ' . mt_rand(1, 120) . ', Indonesia',
            'birth_date'    => date('Y-m-d', strtotime('-' . mt_rand(24, 45) . ' years')),
            'gender'        => $gender === 'L' ? 'Laki-laki' : 'Perempuan',
            'marital_status'=> ['Lajang', 'Menikah'][mt_rand(0, 1)],
            'religion'      => 'Islam',
            'password_hash' => $password,
            'role'          => $role,
            'direct_manager_id' => $managerIndex === null ? null : ($employeeIds[$managerIndex] ?? null),
            'business_unit_id' => $buIds[$bu],
            'department_id' => $deptIds[$bu . '-' . $dept],
            'position_id'   => $positionIds[$position],
            'basic_salary'  => $salary,
            'employment_status' => $status,
            'contract_start'=> $contractStart,
            'contract_end'  => $contractEnd,
            'join_date'     => date('Y-m-d', strtotime('-' . (14 + $index * 2) . ' months')),
            'resign_date'   => $flag === 'RESIGN' ? date('Y-m-d', strtotime('-60 days')) : null,
            'flag'          => $flag,
            'bank_name'     => ['BCA', 'Mandiri', 'BNI', 'BRI'][mt_rand(0, 3)],
            'bank_account_name' => $name,
            'bank_account_number' => (string) mt_rand(1000000000, 9999999999),
            'npwp'          => mt_rand(10, 99) . '.' . mt_rand(100, 999) . '.' . mt_rand(100, 999) . '.' . mt_rand(1, 9) . '-' . mt_rand(100, 999) . '.000',
            'bpjs_kesehatan_no' => '0001' . mt_rand(100000000, 999999999),
            'bpjs_tk_no'    => '1102' . mt_rand(10000000, 99999999),
            'tax_status'    => ['TK/0', 'K/0', 'K/1', 'K/2'][mt_rand(0, 3)],
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
        $employeeIds[$index] = $id;
        // map by code for later lookups
        $employeeIds[$code] = $id;
    }

    // Department heads
    Database::update('departments', ['head_employee_id' => $employeeIds[2]], 'id = ?', [$deptIds['JKT-IT']]);
    Database::update('departments', ['head_employee_id' => $employeeIds[1]], 'id = ?', [$deptIds['JKT-FIN']]);
    Database::update('departments', ['head_employee_id' => $employeeIds[5]], 'id = ?', [$deptIds['SBY-SCM']]);
    Database::update('departments', ['head_employee_id' => $employeeIds[0]], 'id = ?', [$deptIds['JKT-HRGA']]);

    /* ---------------- Bulk workforce (makes dashboards realistic) ---------------- */
    $firstNames = ['Agus', 'Bambang', 'Citra', 'Dewi', 'Eko', 'Fajar', 'Gita', 'Hadi', 'Indah', 'Joko',
        'Kartika', 'Lukman', 'Mega', 'Nanda', 'Oki', 'Putra', 'Ratna', 'Slamet', 'Teguh', 'Umi',
        'Vina', 'Wawan', 'Yani', 'Zainal', 'Arif', 'Bella', 'Chandra', 'Dian', 'Erwin', 'Farah',
        'Gunawan', 'Hesti', 'Iwan', 'Jihan', 'Krisna', 'Lina', 'Mira', 'Naufal', 'Oscar', 'Prita',
        'Rudi', 'Sari', 'Tio', 'Ulfa', 'Vera', 'Wulan', 'Yudi', 'Zulaikha'];
    $lastNames = ['Pratama', 'Saputra', 'Wijaya', 'Lestari', 'Nugroho', 'Handayani', 'Kusuma', 'Setiawan',
        'Anggraini', 'Hidayat', 'Ramadhani', 'Susanto', 'Permata', 'Santoso', 'Wulandari', 'Firmansyah',
        'Aisyah', 'Gunadi', 'Maulana', 'Rahmawati'];

    $deptKeys = array_keys($deptIds);
    $positionList = array_keys($positionIds);
    $bulkStart = count($people);
    $bulkCount = 88;

    for ($i = 0; $i < $bulkCount; $i++) {
        $buCode = ['JKT', 'SBY', 'MDN', 'BDG'][$i % 4];
        $deptKey = $deptKeys[($i * 3) % count($deptKeys)];
        if (!str_starts_with($deptKey, $buCode . '-')) {
            $ownDepts = array_values(array_filter($deptKeys, static fn ($k) => str_starts_with($k, $buCode . '-')));
            $deptKey = $ownDepts[$i % count($ownDepts)];
        }
        $position = $positionList[($i * 5) % count($positionList)];
        if (in_array($position, ['HR Super Admin', 'IT Dept Head', 'Finance Manager', 'Branch Manager'], true)) {
            $position = 'Staff Operasional';
        }
        $firstName = $firstNames[($i * 7) % count($firstNames)];
        $lastName = $lastNames[($i * 11) % count($lastNames)];
        $name = $firstName . ' ' . $lastName;
        $seq[$buCode] = ($seq[$buCode] ?? 0) + 1;
        $code = sprintf('ARG-%s-%04d', $buCode, 500 + $seq[$buCode]);
        $employment = $i % 5 === 0 ? 'PKWT' : 'PKWTT';
        $flag = 'ACTIVE';
        $contractEnd = null;
        if ($employment === 'PKWT') {
            $contractEnd = date('Y-m-d', strtotime('+' . (10 + ($i * 3) % 150) . ' days'));
        }
        if ($i % 29 === 0) {
            $flag = 'RESIGN';
            $contractEnd = date('Y-m-d', strtotime('-' . (30 + $i) . ' days'));
        }
        $salary = (int) round(((int) $positionMap[array_search($position, $positionList, true)][2]) * (0.9 + (($i % 7) * 0.04)));
        $email = strtolower(preg_replace('/[^a-z]+/i', '.', $name)) . ($i + 1) . '@asiaraya.co.id';

        $employeeIds[$bulkStart + $i] = Database::insert('employees', [
            'employee_code' => $code,
            'username'      => strtolower(preg_replace('/[^a-z]+/i', '.', $name)) . ($i + 1),
            'full_name'     => $name,
            'nickname'      => $firstName,
            'email'         => $email,
            'phone'         => '08' . mt_rand(1000000000, 9999999999),
            'address'       => 'Jl. ' . $lastNames[($i * 3) % count($lastNames)] . ' No. ' . mt_rand(1, 200) . ', Indonesia',
            'birth_date'    => date('Y-m-d', strtotime('-' . mt_rand(22, 48) . ' years')),
            'gender'        => $i % 2 === 0 ? 'Laki-laki' : 'Perempuan',
            'marital_status'=> $i % 3 === 0 ? 'Menikah' : 'Lajang',
            'password_hash' => $password,
            'role'          => 'KARYAWAN',
            'direct_manager_id' => $i % 4 === 0 ? $employeeIds['ARG-JKT-0001'] : $employeeIds[5],
            'business_unit_id' => $buIds[$buCode],
            'department_id' => $deptIds[$deptKey],
            'position_id'   => $positionIds[$position],
            'basic_salary'  => max(4800000, $salary),
            'employment_status' => $employment,
            'contract_start'=> date('Y-m-d', strtotime('-' . (6 + ($i % 24)) . ' months')),
            'contract_end'  => $contractEnd,
            'join_date'     => $i < 6 ? date('Y-m-0' . ($i + 1)) : date('Y-m-d', strtotime('-' . (2 + ($i % 40)) . ' months')),
            'resign_date'   => $flag === 'RESIGN' ? date('Y-m-d', strtotime('-15 days')) : null,
            'flag'          => $flag,
            'bank_name'     => ['BCA', 'Mandiri', 'BNI', 'BRI'][$i % 4],
            'bank_account_name' => $name,
            'bank_account_number' => (string) mt_rand(1000000000, 9999999999),
            'npwp'          => mt_rand(10, 99) . '.' . mt_rand(100, 999) . '.' . mt_rand(100, 999) . '.' . mt_rand(1, 9) . '-' . mt_rand(100, 999) . '.000',
            'bpjs_kesehatan_no' => '0001' . mt_rand(100000000, 999999999),
            'bpjs_tk_no'    => '1102' . mt_rand(10000000, 99999999),
            'tax_status'    => ['TK/0', 'K/0', 'K/1', 'K/2'][$i % 4],
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    /* ---------------- Schedules (30 days window) ---------------- */
    $activeEmployees = Database::all("SELECT id, business_unit_id FROM employees WHERE flag = 'ACTIVE'");
    $startSchedule = date('Y-m-d', strtotime('-14 days'));
    $endSchedule = date('Y-m-d', strtotime('+30 days'));
    foreach ($activeEmployees as $i => $emp) {
        $shiftCode = $i % 11 === 0 ? 'SHIFT-WFH' : ($i % 7 === 0 ? 'SHIFT-SIANG' : 'SHIFT-PAGI');
        Database::insert('schedules', [
            'employee_id' => (int) $emp['id'],
            'shift_id'    => $shiftIds[$shiftCode],
            'start_date'  => $startSchedule,
            'end_date'    => $endSchedule,
            'source'      => 'ASSIGNED',
            'note'        => 'Jadwal bulanan hasil generate otomatis',
            'created_by'  => $employeeIds['ARG-JKT-0001'],
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    /* ---------------- Attendance (last 25 working days + today) ---------------- */
    $shiftById = [];
    foreach (Database::all('SELECT * FROM work_shifts') as $s) {
        $shiftById[(int) $s['id']] = $s;
    }
    $scheduleByEmployee = [];
    foreach (Database::all('SELECT * FROM schedules') as $s) {
        $scheduleByEmployee[(int) $s['employee_id']] = $s;
    }

    $holidays = ['2026-08-17', '2026-12-25'];
    $days = [];
    for ($d = 1; $d <= 40 && count($days) < 26; $d++) {
        $date = date('Y-m-d', strtotime('-' . $d . ' days'));
        $dow = (int) date('N', strtotime($date));
        if ($dow >= 6 || in_array($date, $holidays, true)) {
            continue;
        }
        $days[] = $date;
    }
    $days = array_merge([$today], $days);

    foreach ($activeEmployees as $idx => $emp) {
        $empId = (int) $emp['id'];
        $schedule = $scheduleByEmployee[$empId] ?? null;
        if (!$schedule) {
            continue;
        }
        $shift = $shiftById[(int) $schedule['shift_id']] ?? null;
        if (!$shift) {
            continue;
        }
        foreach ($days as $dayIdx => $date) {
            // A few absences / leaves to make reports interesting
            $roll = mt_rand(1, 100);
            $status = 'PRESENT';
            $lateMinutes = 0;
            $checkIn = null;
            $checkOut = null;
            $operational = $shift['operational_type'];

            if ($roll <= 3) {
                $status = 'ABSENT';
            } elseif ($roll <= 6) {
                $status = 'LEAVE';
            } else {
                $arrival = mt_rand(-14, 28); // minutes vs shift start
                $startTs = strtotime($date . ' ' . $shift['check_in_time']);
                $checkInTs = $startTs + ($arrival * 60);
                if ($arrival > (int) $shift['late_tolerance_minutes']) {
                    $status = 'LATE';
                    $lateMinutes = $arrival;
                }
                $checkIn = date('Y-m-d H:i:s', $checkInTs);
                if ($date === $today && $empId !== $employeeIds['ARG-JKT-0421']) {
                    // today's shift is still running for most people
                    $checkOut = null;
                } else {
                    $outTs = strtotime($date . ' ' . $shift['check_out_time']);
                    $checkOut = date('Y-m-d H:i:s', $outTs + mt_rand(-10, 55) * 60);
                }
            }

            $distance = mt_rand(8, 48);
            Database::insert('attendance', [
                'employee_id'     => $empId,
                'work_date'       => $date,
                'shift_id'        => (int) $shift['id'],
                'operational_type'=> $operational,
                'check_in_at'     => $checkIn,
                'check_in_lat'    => $schedule ? (-6.2241 + (mt_rand(-40, 40) / 100000)) : null,
                'check_in_lng'    => $schedule ? (106.8089 + (mt_rand(-40, 40) / 100000)) : null,
                'check_in_distance' => $checkIn ? $distance : null,
                'check_in_accuracy' => $checkIn ? mt_rand(5, 25) : null,
                'check_in_status' => $checkIn ? ($lateMinutes > 0 ? 'LATE' : 'ON_TIME') : null,
                'check_out_at'    => $checkOut,
                'check_out_distance' => $checkOut ? $distance + 4 : null,
                'late_minutes'    => $lateMinutes,
                'work_minutes'    => $checkIn && $checkOut ? (int) round((strtotime($checkOut) - strtotime($checkIn)) / 60) - (int) $shift['break_minutes'] : 0,
                'status'          => $status,
                'source'          => $operational === 'WFH' ? 'PWA' : 'PWA',
                'ip_address'      => '10.20.' . mt_rand(1, 30) . '.' . mt_rand(2, 240),
                'device_info'     => 'Chrome / Android',
                'created_at'      => ($checkIn ?? $date . ' 08:00:00'),
                'updated_at'      => $now,
            ]);
        }
    }
    $log[] = 'Presensi demo 26 hari kerja dibuat.';

    /* ---------------- Overtime requests ---------------- */
    $overtimeSeed = [
        [3, date('Y-m-d'), '17:30', '20:30', 3, 'Migrasi Core Banking - cut over window', 'PENDING', 2],
        [4, date('Y-m-d', strtotime('-1 day')), '18:00', '20:00', 2, 'Closing laporan keuangan bulanan', 'MANAGER_APPROVED', 1],
        [5, date('Y-m-d', strtotime('-2 days')), '16:00', '19:00', 3, 'Stock opname gudang', 'APPROVED', null],
        [8, date('Y-m-d'), '21:00', '23:00', 2, 'Perawatan mesin lini 2', 'PENDING', 5],
        [12, date('Y-m-d', strtotime('-3 days')), '17:00', '19:00', 2, 'Rilis aplikasi internal', 'APPROVED', 2],
        [22, date('Y-m-d'), '18:00', '20:00', 2, 'Audit stok cabang', 'PENDING', 7],
    ];
    foreach ($overtimeSeed as [$empIdx, $date, $start, $end, $hours, $reason, $status, $mgrIdx]) {
        Database::insert('overtime_requests', [
            'employee_id' => $employeeIds[$empIdx],
            'work_date'   => $date,
            'planned_start' => $start,
            'planned_end' => $end,
            'planned_hours' => $hours,
            'type'        => 'REQUEST',
            'reason'      => $reason,
            'status'      => $status,
            'compensation_amount' => in_array($status, ['MANAGER_APPROVED', 'HR_APPROVED', 'APPROVED'], true)
                ? round(((float) $people[$empIdx][8] / 173) * (1.5 + ($hours - 1) * 2), 2)
                : 0,
            'manager_id'  => $mgrIdx === null ? null : $employeeIds[$mgrIdx],
            'manager_approved_at' => in_array($status, ['MANAGER_APPROVED', 'HR_APPROVED', 'APPROVED'], true) ? $now : null,
            'hr_id'       => in_array($status, ['HR_APPROVED', 'APPROVED'], true) ? $employeeIds['ARG-JKT-0001'] : null,
            'hr_approved_at' => in_array($status, ['HR_APPROVED', 'APPROVED'], true) ? $now : null,
            'created_by'  => $employeeIds[$empIdx],
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    /* ---------------- Leave types & balances ---------------- */
    $leaveTypeSeed = [
        ['CT', 'Cuti Tahunan', 12, 1, 12, 3, 0, 1, 0, '#0d9488'],
        ['CS', 'Cuti Sakit', 12, 1, 12, 0, 1, 0, 0, '#0ea5e9'],
        ['CI', 'Cuti Izin Penting', 3, 1, 3, 1, 0, 0, 0, '#f59e0b'],
        ['CM', 'Cuti Melahirkan', 90, 1, 90, 30, 1, 0, 0, '#a855f7'],
        ['CTU', 'Cuti Tanpa Upah', 0, 0, 30, 3, 0, 0, 1, '#e11d48'],
    ];
    $leaveTypeIds = [];
    foreach ($leaveTypeSeed as [$code, $name, $quota, $paid, $max, $notice, $attach, $carry, $deduct, $color]) {
        $leaveTypeIds[$code] = Database::insert('leave_types', [
            'code' => $code, 'name' => $name, 'quota_days' => $quota, 'is_paid' => $paid,
            'max_per_request' => $max, 'min_notice_days' => $notice, 'requires_attachment' => $attach,
            'carry_over' => $carry, 'deduct_salary' => $deduct, 'color' => $color, 'is_active' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    $year = (int) date('Y');
    foreach ($activeEmployees as $emp) {
        foreach ($leaveTypeIds as $code => $typeId) {
            $quota = match ($code) {
                'CT' => 12, 'CS' => 12, 'CI' => 3, 'CM' => 90, default => 0,
            };
            Database::insert('leave_balances', [
                'employee_id' => (int) $emp['id'],
                'leave_type_id' => $typeId,
                'period_year' => $year,
                'quota' => $quota,
                'used' => mt_rand(0, 4),
                'carry' => 0,
                'updated_at' => $now,
            ]);
        }
    }

    $leaveSeed = [
        [3, 'CT', date('Y-m-d', strtotime('+5 days')), date('Y-m-d', strtotime('+7 days')), 3, 'Liburan keluarga', 'PENDING', 2],
        [4, 'CS', date('Y-m-d', strtotime('+2 days')), date('Y-m-d', strtotime('+3 days')), 2, 'Demam, surat dokter terlampir', 'MANAGER_APPROVED', 1],
        [8, 'CT', date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('-9 days')), 2, 'Acara keluarga', 'APPROVED', 5],
        [21, 'CTU', date('Y-m-d', strtotime('+14 days')), date('Y-m-d', strtotime('+16 days')), 3, 'Keperluan pribadi', 'PENDING', 7],
        [6, 'CT', date('Y-m-d', strtotime('+20 days')), date('Y-m-d', strtotime('+22 days')), 3, 'Pulang kampung', 'PENDING', null],
    ];
    foreach ($leaveSeed as [$empIdx, $code, $start, $end, $days, $reason, $status, $mgrIdx]) {
        Database::insert('leave_requests', [
            'employee_id' => $employeeIds[$empIdx],
            'leave_type_id' => $leaveTypeIds[$code],
            'start_date' => $start,
            'end_date' => $end,
            'days' => $days,
            'reason' => $reason,
            'status' => $status,
            'manager_id' => $mgrIdx === null ? null : $employeeIds[$mgrIdx],
            'manager_approved_at' => in_array($status, ['MANAGER_APPROVED', 'APPROVED'], true) ? $now : null,
            'hr_id' => $status === 'APPROVED' ? $employeeIds['ARG-JKT-0001'] : null,
            'hr_approved_at' => $status === 'APPROVED' ? $now : null,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    /* ---------------- Shift change request ---------------- */
    Database::insert('shift_change_requests', [
        'employee_id' => $employeeIds[5],
        'current_shift_id' => $shiftIds['SHIFT-PAGI'],
        'requested_shift_id' => $shiftIds['SHIFT-MALAM'],
        'start_date' => date('Y-m-d', strtotime('+1 day')),
        'end_date' => date('Y-m-d', strtotime('+4 days')),
        'reason' => 'Tukar shift dengan rekan tim gudang (urgensi pengiriman)',
        'status' => 'MANAGER_APPROVED',
        'manager_id' => $employeeIds[0],
        'manager_approved_at' => $now,
        'created_at' => $now, 'updated_at' => $now,
    ]);

    /* ---------------- Salary components ---------------- */
    $componentSeed = [
        ['TUNJ_JABATAN', 'Tunjangan Jabatan', 'BENEFIT', 'FIXED', 1500000, 'BASIC', 1, 1, 0, 1],
        ['TUNJ_TRANSPORT', 'Tunjangan Transportasi', 'BENEFIT', 'FIXED', 750000, 'BASIC', 1, 0, 0, 2],
        ['TUNJ_MAKAN', 'Tunjangan Makan', 'BENEFIT', 'FIXED', 600000, 'BASIC', 1, 0, 0, 3],
        ['TUNJ_KINERJA', 'Tunjangan Kinerja', 'BENEFIT', 'PERCENT', 10, 'BASIC', 1, 1, 0, 4],
        ['POT_ABSEN', 'Potongan Absensi', 'DEDUCTION', 'FIXED', 0, 'BASIC', 0, 0, 0, 5],
        ['POT_KOPERASI', 'Potongan Koperasi', 'DEDUCTION', 'FIXED', 150000, 'BASIC', 0, 0, 0, 6],
    ];
    $componentIds = [];
    foreach ($componentSeed as [$code, $name, $type, $calc, $amount, $base, $taxable, $bpjs, $applyDefault, $order]) {
        $componentIds[$code] = Database::insert('salary_components', [
            'code' => $code, 'name' => $name, 'type' => $type, 'calc_type' => $calc,
            'amount' => $amount, 'percent_base' => $base, 'taxable' => $taxable,
            'bpjs_base' => $bpjs, 'apply_default' => $applyDefault, 'sort_order' => $order,
            'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    foreach (Database::all("SELECT id FROM employees WHERE flag = 'ACTIVE'") as $emp) {
        foreach (['TUNJ_JABATAN', 'TUNJ_TRANSPORT', 'TUNJ_MAKAN', 'TUNJ_KINERJA'] as $code) {
            Database::insert('employee_components', [
                'employee_id' => (int) $emp['id'],
                'component_id' => $componentIds[$code],
                'amount_override' => null,
                'effective_date' => date('Y-m-01'),
                'is_active' => 1,
                'created_at' => $now,
            ]);
        }
    }

    /* ---------------- Settings ---------------- */
    $settings = [
        // company group
        ['company_name', 'PT Asia Raya Group', 'company'],
        ['company_legal_name', 'PT Asia Raya Group', 'company'],
        ['company_address', 'Menara Asia Raya, Jl. Jend. Sudirman Kav. 52-53, Jakarta Selatan 12190', 'company'],
        ['company_phone', '+62 21 5150 8000', 'company'],
        ['company_email', 'hr@asiaraya.co.id', 'company'],
        ['company_website', 'https://asiaraya.co.id', 'company'],
        ['company_npwp', '01.234.567.8-091.000', 'company'],
        ['company_logo', '', 'company'],
        ['timezone', 'Asia/Jakarta', 'company'],
        ['currency', 'IDR', 'company'],
        // employee id format
        ['employee_id_format', 'ARG-{BU}-{SEQ:4}', 'company'],
        ['employee_id_company_prefix', 'ARG', 'company'],
        ['employee_id_scope', 'BU', 'company'],
        // attendance
        ['attendance_work_start', '08:00', 'attendance'],
        ['attendance_work_end', '17:00', 'attendance'],
        ['attendance_late_tolerance', '15', 'attendance'],
        ['attendance_selfie_required', '1', 'attendance'],
        ['attendance_geofence_required', '1', 'attendance'],
        ['attendance_wfh_bypass_geofence', '1', 'attendance'],
        ['attendance_late_deduction_enabled', '1', 'attendance'],
        ['attendance_late_deduction_type', 'PER_MINUTE', 'attendance'],
        ['attendance_late_deduction_value', '2500', 'attendance'],
        ['attendance_absent_deduction_enabled', '1', 'attendance'],
        ['attendance_absent_deduct_salary', '0', 'attendance'],
        ['attendance_absent_leave_type_id', (string) ($leaveTypeIds['CT'] ?? ''), 'attendance'],
        ['attendance_absent_leave_days', '1', 'attendance'],
        ['attendance_photo_max_width', '720', 'attendance'],
        ['attendance_photo_quality', '70', 'attendance'],
        // overtime
        ['overtime_enabled', '1', 'overtime'],
        ['overtime_first_hour_multiplier', '1.5', 'overtime'],
        ['overtime_next_hour_multiplier', '2', 'overtime'],
        ['overtime_holiday_multiplier', '2', 'overtime'],
        ['overtime_max_hours', '4', 'overtime'],
        ['overtime_min_minutes', '30', 'overtime'],
        ['overtime_max_compensation', '3500000', 'overtime'],
        ['overtime_divisor', '173', 'overtime'],
        // payroll
        ['payroll_cutoff_day', '25', 'payroll'],
        ['payroll_pay_day', '28', 'payroll'],
        ['payroll_auto_cutoff', '1', 'payroll'],
        ['payroll_pph_enabled', '1', 'payroll'],
        ['payroll_pph_method', 'TER', 'payroll'],
        ['payroll_pph_rate', '5', 'payroll'],
        // bpjs
        ['bpjs_kesehatan_enabled', '1', 'bpjs'],
        ['bpjs_kes_employee_pct', '1', 'bpjs'],
        ['bpjs_kes_company_pct', '4', 'bpjs'],
        ['bpjs_kes_max_wage', '12000000', 'bpjs'],
        ['bpjs_tk_enabled', '1', 'bpjs'],
        ['bpjs_jht_employee_pct', '2', 'bpjs'],
        ['bpjs_jht_company_pct', '3.7', 'bpjs'],
        ['bpjs_jp_employee_pct', '1', 'bpjs'],
        ['bpjs_jp_company_pct', '2', 'bpjs'],
        ['bpjs_jp_max_wage', '10000000', 'bpjs'],
        ['bpjs_jkk_pct', '0.24', 'bpjs'],
        ['bpjs_jkm_pct', '0.3', 'bpjs'],
        // leave
        ['leave_default_quota', '12', 'leave'],
        ['leave_allow_negative', '0', 'leave'],
        // google
        ['google_login_enabled', '1', 'company'],
        ['google_client_id', '', 'company'],
        ['google_client_secret', '', 'company'],
    ];
    foreach ($settings as [$key, $value, $group]) {
        Settings::set($key, $value, $group);
    }

    /* ---------------- Holidays ---------------- */
    foreach ([
        [date('Y') . '-01-01', 'Tahun Baru Masehi'],
        [date('Y') . '-08-17', 'Hari Kemerdekaan RI'],
        [date('Y') . '-12-25', 'Hari Raya Natal'],
    ] as [$date, $name]) {
        Database::insert('holidays', ['holiday_date' => $date, 'name' => $name, 'is_national' => 1]);
    }

    $totalEmployees = (int) Database::value('SELECT COUNT(*) FROM employees');
    $log[] = 'Data organisasi, ' . $totalEmployees . ' karyawan, presensi, lembur, cuti, dan komponen gaji dibuat.';
    $log[] = 'Login demo: hr@asiaraya.co.id / Password123! (HR), finance@asiaraya.co.id (FIN), dimas.aditya@asiaraya.co.id (Karyawan)';

    return $log;
};
