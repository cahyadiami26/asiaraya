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
use App\Controllers\AttendanceController;
use App\Controllers\AuthController;
use App\Controllers\ComponentController;
use App\Controllers\DashboardController;
use App\Controllers\EmployeeController;
use App\Controllers\MediaController;
use App\Controllers\LeaveController;
use App\Controllers\OvertimeController;
use App\Controllers\OrgController;
use App\Controllers\PlaceholderController;
use App\Controllers\ScheduleController;
use App\Controllers\SettingsController;
use App\Controllers\ShiftController;
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

/* ---------------------------------------------------------- Batch 2: organisasi */
$router->get('/organisation', [OrgController::class, 'index'], ['roles' => ['HR'], 'name' => 'org.index']);
$router->post('/organisation/units', [OrgController::class, 'storeBusinessUnit'], ['roles' => ['HR'], 'name' => 'org.units.store']);
$router->add('POST|PUT', '/organisation/units/{id}', [OrgController::class, 'updateBusinessUnit'], ['roles' => ['HR'], 'name' => 'org.units.update']);
$router->add('POST|DELETE', '/organisation/units/{id}/delete', [OrgController::class, 'destroyBusinessUnit'], ['roles' => ['HR'], 'name' => 'org.units.delete']);
$router->post('/organisation/departments', [OrgController::class, 'storeDepartment'], ['roles' => ['HR'], 'name' => 'org.departments.store']);
$router->add('POST|PUT', '/organisation/departments/{id}', [OrgController::class, 'updateDepartment'], ['roles' => ['HR'], 'name' => 'org.departments.update']);
$router->add('POST|DELETE', '/organisation/departments/{id}/delete', [OrgController::class, 'destroyDepartment'], ['roles' => ['HR'], 'name' => 'org.departments.delete']);
$router->post('/organisation/positions', [OrgController::class, 'storePosition'], ['roles' => ['HR'], 'name' => 'org.positions.store']);
$router->add('POST|PUT', '/organisation/positions/{id}', [OrgController::class, 'updatePosition'], ['roles' => ['HR'], 'name' => 'org.positions.update']);
$router->add('POST|DELETE', '/organisation/positions/{id}/delete', [OrgController::class, 'destroyPosition'], ['roles' => ['HR'], 'name' => 'org.positions.delete']);
$router->post('/organisation/id-format', [OrgController::class, 'saveIdFormat'], ['roles' => ['HR'], 'name' => 'org.idformat.save']);

/* --------------------------------------------------------- Batch 2: perusahaan */
$router->get('/company', [OrgController::class, 'company'], ['roles' => ['HR'], 'name' => 'company.edit']);
$router->post('/company', [OrgController::class, 'saveCompany'], ['roles' => ['HR'], 'name' => 'company.update']);

/* ---------------------------------------------------------- Batch 2: karyawan */
$router->get('/employees', [EmployeeController::class, 'index'], ['roles' => ['HR'], 'name' => 'employees.index']);
$router->get('/employees/contracts', [EmployeeController::class, 'index'], ['roles' => ['HR'], 'name' => 'employees.contracts', 'params' => ['contract' => 'expiring', 'flag' => 'ACTIVE', 'sort' => 'contract']]);
$router->get('/employees/create', [EmployeeController::class, 'create'], ['roles' => ['HR'], 'name' => 'employees.create']);
$router->post('/employees', [EmployeeController::class, 'store'], ['roles' => ['HR'], 'name' => 'employees.store']);
$router->get('/employees/{id}', [EmployeeController::class, 'show'], ['roles' => ['HR'], 'name' => 'employees.show']);
$router->get('/employees/{id}/edit', [EmployeeController::class, 'edit'], ['roles' => ['HR'], 'name' => 'employees.edit']);
$router->add('POST|PUT', '/employees/{id}', [EmployeeController::class, 'update'], ['roles' => ['HR'], 'name' => 'employees.update']);
$router->post('/employees/{id}/resign', [EmployeeController::class, 'resign'], ['roles' => ['HR'], 'name' => 'employees.resign']);

/* ------------------------------------------------------ Batch 3: shift & jadwal */
$router->get('/shifts', [ShiftController::class, 'index'], ['roles' => ['HR'], 'name' => 'shifts.index']);
$router->post('/shifts', [ShiftController::class, 'store'], ['roles' => ['HR'], 'name' => 'shifts.store']);
$router->add('POST|PUT', '/shifts/{id}', [ShiftController::class, 'update'], ['roles' => ['HR'], 'name' => 'shifts.update']);
$router->add('POST|DELETE', '/shifts/{id}/delete', [ShiftController::class, 'destroy'], ['roles' => ['HR'], 'name' => 'shifts.delete']);

$router->get('/schedules', [ScheduleController::class, 'index'], ['roles' => ['HR'], 'name' => 'schedules.index']);
$router->post('/schedules/assign', [ScheduleController::class, 'assign'], ['roles' => ['HR'], 'name' => 'schedules.assign']);
$router->add('POST|DELETE', '/schedules/{id}/delete', [ScheduleController::class, 'removeAssignment'], ['roles' => ['HR'], 'name' => 'schedules.delete']);
$router->get('/schedules/requests', [ScheduleController::class, 'requests'], ['roles' => ['HR', 'KARYAWAN'], 'name' => 'schedules.requests']);
$router->get('/me/schedule', [ScheduleController::class, 'mine'], ['name' => 'me.schedule']);
$router->post('/me/schedule/request', [ScheduleController::class, 'storeRequest'], ['name' => 'me.schedule.request']);

/* -------------------------------------------------- Batch 4: presensi & GPS */
$router->get('/me/attendance', [AttendanceController::class, 'mine'], ['name' => 'me.attendance']);
$router->post('/attendance/punch', [AttendanceController::class, 'punch'], ['name' => 'attendance.punch']);
$router->get('/attendance/live', [AttendanceController::class, 'live'], ['roles' => ['HR'], 'name' => 'attendance.live']);
$router->get('/attendance/daily', [AttendanceController::class, 'daily'], ['roles' => ['HR'], 'name' => 'attendance.daily']);
$router->post('/attendance/mark-absent', [AttendanceController::class, 'markAbsent'], ['roles' => ['HR'], 'name' => 'attendance.mark-absent']);
$router->get('/attendance/rekap', [AttendanceController::class, 'rekap'], ['roles' => ['HR'], 'name' => 'attendance.rekap']);
$router->get('/attendance/settings', [AttendanceController::class, 'settings'], ['roles' => ['HR'], 'name' => 'attendance.settings']);
$router->post('/attendance/settings', [AttendanceController::class, 'saveSettings'], ['roles' => ['HR'], 'name' => 'attendance.settings.save']);

/* ------------------------------------------------- Batch 5: lembur & terjadwal */
$router->get('/overtime', [OvertimeController::class, 'index'], ['roles' => ['HR'], 'name' => 'overtime.index']);
$router->post('/overtime', [OvertimeController::class, 'store'], ['roles' => ['HR'], 'name' => 'overtime.store']);
$router->get('/overtime/schedules', [OvertimeController::class, 'schedules'], ['roles' => ['HR'], 'name' => 'overtime.schedules']);
$router->post('/overtime/schedules', [OvertimeController::class, 'storeScheduled'], ['roles' => ['HR'], 'name' => 'overtime.schedules.store']);
$router->get('/overtime/export', [OvertimeController::class, 'export'], ['roles' => ['HR', 'FIN'], 'name' => 'overtime.export']);
$router->post('/overtime/bulk-decide', [OvertimeController::class, 'bulkDecide'], ['roles' => ['HR'], 'name' => 'overtime.bulk-decide']);
$router->post('/overtime/policy', [OvertimeController::class, 'savePolicy'], ['roles' => ['HR'], 'name' => 'overtime.policy']);
$router->post('/overtime/{id}/actual', [OvertimeController::class, 'actual'], ['roles' => ['HR'], 'name' => 'overtime.actual']);
$router->post('/overtime/{id}/cancel', [OvertimeController::class, 'cancel'], ['roles' => ['HR'], 'name' => 'overtime.cancel']);
$router->add('POST|PUT', '/overtime/{id}', [OvertimeController::class, 'update'], ['roles' => ['HR'], 'name' => 'overtime.update']);
$router->get('/me/overtime', [OvertimeController::class, 'mine'], ['name' => 'me.overtime']);
$router->post('/me/overtime', [OvertimeController::class, 'request'], ['name' => 'me.overtime.request']);
$router->post('/me/overtime/{id}/cancel', [OvertimeController::class, 'myCancel'], ['name' => 'me.overtime.cancel']);

/* ------------------------------------------------------- Batch 6: cuti & saldo */
$router->get('/leave', [LeaveController::class, 'index'], ['roles' => ['HR', 'KARYAWAN'], 'name' => 'leave.index']);
$router->get('/leave/export', [LeaveController::class, 'export'], ['roles' => ['HR', 'FIN'], 'name' => 'leave.export']);
$router->get('/leave/types', [LeaveController::class, 'types'], ['roles' => ['HR'], 'name' => 'leave.types']);
$router->post('/leave/types', [LeaveController::class, 'storeType'], ['roles' => ['HR'], 'name' => 'leave.types.store']);
$router->add('POST|PUT', '/leave/types/{id}', [LeaveController::class, 'updateType'], ['roles' => ['HR'], 'name' => 'leave.types.update']);
$router->add('POST|DELETE', '/leave/types/{id}/delete', [LeaveController::class, 'destroyType'], ['roles' => ['HR'], 'name' => 'leave.types.delete']);
$router->get('/leave/balances', [LeaveController::class, 'balances'], ['roles' => ['HR'], 'name' => 'leave.balances']);
$router->post('/leave/balances', [LeaveController::class, 'saveBalance'], ['roles' => ['HR'], 'name' => 'leave.balances.save']);
$router->post('/leave/balances/generate', [LeaveController::class, 'generateBalances'], ['roles' => ['HR'], 'name' => 'leave.balances.generate']);
$router->post('/leave/{id}/cancel', [LeaveController::class, 'cancel'], ['roles' => ['HR'], 'name' => 'leave.cancel']);
$router->get('/me/leave', [LeaveController::class, 'mine'], ['name' => 'me.leave']);
$router->post('/me/leave', [LeaveController::class, 'request'], ['name' => 'me.leave.request']);
$router->post('/me/leave/{id}/cancel', [LeaveController::class, 'myCancel'], ['name' => 'me.leave.cancel']);

/* ------------------------------------ Batch 7: komponen gaji & pengaturan */
$router->get('/components', [ComponentController::class, 'index'], ['roles' => ['HR'], 'name' => 'components.index']);
$router->post('/components', [ComponentController::class, 'store'], ['roles' => ['HR'], 'name' => 'components.store']);
$router->post('/components/assign', [ComponentController::class, 'assign'], ['roles' => ['HR'], 'name' => 'components.assign']);
$router->post('/components/apply-defaults', [ComponentController::class, 'applyDefaults'], ['roles' => ['HR'], 'name' => 'components.apply-defaults']);
$router->get('/components/simulate/{id}', [ComponentController::class, 'simulate'], ['roles' => ['HR'], 'name' => 'components.simulate', 'csrf' => false]);
$router->add('POST|PUT', '/components/assignments/{id}', [ComponentController::class, 'updateAssignment'], ['roles' => ['HR'], 'name' => 'components.assignments.update']);
$router->add('POST|DELETE', '/components/assignments/{id}/delete', [ComponentController::class, 'unassign'], ['roles' => ['HR'], 'name' => 'components.assignments.delete']);
$router->add('POST|DELETE', '/components/{id}/delete', [ComponentController::class, 'destroy'], ['roles' => ['HR'], 'name' => 'components.delete']);
$router->add('POST|PUT', '/components/{id}', [ComponentController::class, 'update'], ['roles' => ['HR'], 'name' => 'components.update']);

$router->get('/settings', [SettingsController::class, 'index'], ['roles' => ['HR'], 'name' => 'settings.index']);
$router->post('/settings/integration', [SettingsController::class, 'saveIntegration'], ['roles' => ['HR'], 'name' => 'settings.integration.save']);
$router->post('/settings/payroll', [SettingsController::class, 'savePayroll'], ['roles' => ['HR'], 'name' => 'settings.payroll.save']);

/* --------------------------------------------------------------- Media files */
$router->get('/media/{folder}/{file}', [MediaController::class, 'show'], ['name' => 'media.show', 'csrf' => false]);

/* --------------------------------------------------- Approval engine */
$router->post('/overtime/{id}/decide', [ApprovalController::class, 'decideOvertime'], ['roles' => ['HR', 'KARYAWAN'], 'name' => 'overtime.decide']);
$router->post('/leave/{id}/decide', [ApprovalController::class, 'decideLeave'], ['roles' => ['HR', 'KARYAWAN'], 'name' => 'leave.decide']);
$router->post('/schedules/requests/{id}/decide', [ApprovalController::class, 'decideShiftChange'], ['roles' => ['HR', 'KARYAWAN'], 'name' => 'schedules.decide']);

/* ------------------------------------- Modules scheduled for later batches */
$pending = [
    '/payroll'        => ['Payroll', 'Batch 8'],
    '/reports'        => ['Laporan', 'Batch 9'],
    '/reports/payroll'=> ['Laporan Payroll', 'Batch 9'],
    '/me/payslip'     => ['Slip Gaji Saya', 'Batch 8'],
    '/logs'           => ['Log Aktivitas', 'Batch 9'],
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
