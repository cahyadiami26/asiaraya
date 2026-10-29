<?php
/**
 * Front controller — PT Asia Raya Group HRIS.
 * Native PHP (no framework, no Composer). Web server document root points here.
 *
 * Routes are added batch by batch. Sidebar entries whose route is not yet
 * registered are rendered as disabled ("Segera") instead of dead links.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\ApprovalController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\PlaceholderController;
use App\Core\Router;

$router = Router::instance();

/* ------------------------------------------------------------------ Public */
$router->get('/login', [AuthController::class, 'showLogin'], ['auth' => false, 'csrf' => false, 'name' => 'login']);
$router->post('/login', [AuthController::class, 'login'], ['auth' => false, 'name' => 'login.post']);
$router->get('/login/google', [AuthController::class, 'googleRedirect'], ['auth' => false, 'csrf' => false, 'name' => 'login.google']);
$router->get('/login/google/callback', [AuthController::class, 'googleCallback'], ['auth' => false, 'csrf' => false]);
$router->get('/forgot-password', [AuthController::class, 'showForgot'], ['auth' => false, 'csrf' => false, 'name' => 'password.request']);
$router->post('/forgot-password', [AuthController::class, 'sendReset'], ['auth' => false, 'name' => 'password.email']);
$router->post('/logout', [AuthController::class, 'logout'], ['name' => 'logout']);

/* --------------------------------------------------------------- Dashboard */
$router->get('/', [DashboardController::class, 'index'], ['name' => 'home']);
$router->get('/dashboard', [DashboardController::class, 'index'], ['name' => 'dashboard']);
$router->get('/search', [DashboardController::class, 'search'], ['name' => 'search']);
$router->get('/notifications', [DashboardController::class, 'notifications'], ['name' => 'notifications']);
$router->post('/notifications/read', [DashboardController::class, 'markNotificationsRead'], ['name' => 'notifications.read']);

/* ------------------------------------------------------------- Self service */
$router->get('/me/profile', [DashboardController::class, 'profile'], ['name' => 'me.profile']);
$router->post('/me/profile', [DashboardController::class, 'updateProfile'], ['name' => 'me.profile.update']);

/* --------------------------------------------------- Approval engine */
$router->post('/overtime/{id}/decide', [ApprovalController::class, 'decideOvertime'], ['roles' => ['HR', 'KARYAWAN'], 'name' => 'overtime.decide']);
$router->post('/leave/{id}/decide', [ApprovalController::class, 'decideLeave'], ['roles' => ['HR', 'KARYAWAN'], 'name' => 'leave.decide']);
$router->post('/schedules/requests/{id}/decide', [ApprovalController::class, 'decideShiftChange'], ['roles' => ['HR', 'KARYAWAN'], 'name' => 'schedules.decide']);

/* ------------------------------------- Modules scheduled for later batches */
$pending = [
    '/organisation'   => ['Unit Organisasi & Format ID', 'Batch 2'],
    '/company'        => ['Pengaturan Perusahaan', 'Batch 2'],
    '/employees/create' => ['Tambah Karyawan', 'Batch 2'],
    '/employees'      => ['Data Karyawan', 'Batch 2'],
    '/shifts'         => ['Jadwal Kerja & Shift', 'Batch 3'],
    '/schedules'      => ['Penjadwalan Karyawan', 'Batch 3'],
    '/schedules/requests' => ['Persetujuan Perubahan Jadwal', 'Batch 3'],
    '/attendance/live'   => ['Live Monitoring Presensi', 'Batch 4'],
    '/attendance/daily'  => ['Presensi Harian', 'Batch 4'],
    '/attendance/rekap'  => ['Rekap Presensi Bulanan', 'Batch 4'],
    '/attendance/settings' => ['Pengaturan Presensi', 'Batch 4'],
    '/me/attendance'  => ['Presensi Saya', 'Batch 4'],
    '/me/schedule'    => ['Jadwal Saya', 'Batch 3'],
    '/overtime'       => ['Manajemen Lembur', 'Batch 5'],
    '/overtime/schedules' => ['Lembur Terjadwal', 'Batch 5'],
    '/me/overtime'    => ['Lembur Saya', 'Batch 5'],
    '/leave'          => ['Manajemen Cuti', 'Batch 6'],
    '/me/leave'       => ['Cuti Saya', 'Batch 6'],
    '/payroll'        => ['Payroll', 'Batch 8'],
    '/components'     => ['Komponen Gaji', 'Batch 7'],
    '/reports'        => ['Laporan', 'Batch 9'],
    '/reports/payroll'=> ['Laporan Payroll', 'Batch 9'],
    '/me/payslip'     => ['Slip Gaji Saya', 'Batch 8'],
    '/logs'           => ['Log Aktivitas', 'Batch 9'],
    '/settings'       => ['Pengaturan HR & Payroll', 'Batch 7'],
];
foreach ($pending as $path => [$label, $batch]) {
    $router->get($path, [PlaceholderController::class, 'show'], [
        'name'   => 'soon.' . trim(str_replace('/', '.', $path), '.'),
        'csrf'   => false,
        'params' => ['module' => $label, 'batch' => $batch],
    ]);
}
$router->get('/coming-soon', [PlaceholderController::class, 'show'], ['name' => 'coming-soon', 'csrf' => false]);

$router->dispatch();
