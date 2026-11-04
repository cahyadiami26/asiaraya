<?php
/**
 * Token demo untuk REST API /api/v1 (khusus data demo).
 *
 * Token di bawah ini hanya dibuat oleh installer demo (Schema::install(true, …))
 * agar pengembang aplikasi Android bisa langsung mencoba endpoint tanpa login.
 * Pada instalasi produksi tabel api_tokens kosong dan token dibuat lewat
 * POST /api/v1/auth/login.
 */
declare(strict_types=1);

use App\Core\Database;

return static function (): array {
    $log = [];

    if (!Database::tableExists('api_tokens')) {
        return ['Tabel api_tokens belum ada — token demo dilewati.'];
    }

    $tokens = [
        ['hr@asiaraya.co.id', 'DEMO-API-HR-TOKEN-0001', 'Demo HR (Android)'],
        ['dimas.aditya@asiaraya.co.id', 'DEMO-API-EMPLOYEE-TOKEN-0004', 'Demo Karyawan (Android)'],
        ['budi.santoso@asiaraya.co.id', 'DEMO-API-MANAGER-TOKEN-0002', 'Demo Atasan (Android)'],
    ];

    $created = 0;
    foreach ($tokens as [$email, $raw, $label]) {
        $employeeId = (int) Database::value('SELECT id FROM employees WHERE LOWER(email) = ? LIMIT 1', [strtolower($email)], 0);
        if ($employeeId <= 0) {
            continue;
        }

        $exists = (int) Database::value('SELECT COUNT(*) FROM api_tokens WHERE token_hash = ?', [hash('sha256', $raw)], 0);
        if ($exists > 0) {
            continue;
        }

        Database::insert('api_tokens', [
            'employee_id'        => $employeeId,
            'name'               => $label,
            'token_hash'         => hash('sha256', $raw),
            'refresh_hash'       => hash('sha256', $raw . '-refresh'),
            'abilities'          => '*',
            'device_name'        => 'Demo Android',
            'device_platform'    => 'android',
            'app_version'        => '1.0.0-demo',
            'last_used_at'       => date('Y-m-d H:i:s'),
            'last_ip'            => '127.0.0.1',
            'expires_at'         => date('Y-m-d H:i:s', strtotime('+365 days')),
            'refresh_expires_at' => date('Y-m-d H:i:s', strtotime('+365 days')),
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);
        $created++;
    }

    if ($created > 0) {
        $log[] = 'Token demo REST API dibuat: ' . $created . ' perangkat (lihat readme §2.25).';
    } else {
        $log[] = 'Token demo REST API sudah ada — dilewati.';
    }

    return $log;
};
