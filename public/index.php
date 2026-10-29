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

use App\Controllers\AnnouncementController;
use App\Controllers\ApprovalController;
use App\Controllers\AttendanceController;
use App\Controllers\AuthController;
use App\Controllers\ComponentController;
use App\Controllers\DashboardController;
use App\Controllers\EmployeeController;
use App\Controllers\ExpenseController;
use App\Controllers\MediaController;
use App\Controllers\NotificationController;
use App\Controllers\LeaveController;
use App\Controllers\LogController;
use App\Controllers\MailController;
use App\Controllers\OvertimeController;
use App\Controllers\OffboardingController;
use App\Controllers\OrgController;
use App\Controllers\PayrollController;
use App\Controllers\PerformanceController;
use App\Controllers\RecruitmentController;
use App\Controllers\TrainingController;
use App\Controllers\PlaceholderController;
use App\Controllers\ReportController;
use App\Controllers\ScheduleController;
use App\Controllers\SchedulerController;
use App\Controllers\SettingsController;
use App\Controllers\ShiftController;
use App\Controllers\SystemController;
use App\Core\Router;

$router = Router::instance();

/* ------------------------------------------------------------------ Public */
$router->get('/login', [AuthController::class, 'showLogin'], ['auth' => false, 'csrf' => false, 'name' => 'login']);
$router->post('/login', [AuthController::class, 'login'], ['auth' => false, 'name' => 'login.post']);
$router->get('/login/google', [AuthController::class, 'googleRedirect'], ['auth' => false, 'csrf' => false, 'name' => 'login.google']);
$router->get('/login/google/callback', [AuthController::class, 'googleCallback'], ['auth' => false, 'csrf' => false]);
$router->get('/forgot-password', [AuthController::class, 'showForgot'], ['auth' => false, 'csrf' => false, 'name' => 'password.request']);
$router->post('/forgot-password', [AuthController::class, 'sendReset'], ['auth' => false, 'name' => 'password.email']);
$router->get('/reset-password', [AuthController::class, 'showReset'], ['auth' => false, 'csrf' => false, 'name' => 'password.reset']);
$router->post('/reset-password', [AuthController::class, 'resetPassword'], ['auth' => false, 'name' => 'password.update']);
$router->post('/logout', [AuthController::class, 'logout'], ['name' => 'logout']);

/* --------------------------------------------------------------- Dashboard */
$router->get('/', [DashboardController::class, 'index'], ['name' => 'home']);
$router->get('/dashboard', [DashboardController::class, 'index'], ['name' => 'dashboard']);
$router->get('/search', [DashboardController::class, 'search'], ['name' => 'search']);
/* rute notifikasi & pengumuman didaftarkan pada blok Batch 12 di bawah */

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
$router->post('/settings/advanced', [SettingsController::class, 'saveAdvanced'], ['roles' => ['HR'], 'name' => 'settings.advanced.save']);

/* ------------------------------------------------------- Batch 8: payroll */
$router->get('/payroll', [PayrollController::class, 'index'], ['roles' => ['HR', 'FIN'], 'name' => 'payroll.index']);
$router->post('/payroll', [PayrollController::class, 'store'], ['roles' => ['HR'], 'name' => 'payroll.store']);
$router->get('/payroll/items/{id}', [PayrollController::class, 'item'], ['name' => 'payroll.item']);
$router->get('/payroll/items/{id}/pdf', [PayrollController::class, 'slipPdf'], ['name' => 'payroll.item.pdf']);
$router->get('/payroll/{id}', [PayrollController::class, 'show'], ['roles' => ['HR', 'FIN'], 'name' => 'payroll.show']);
$router->get('/payroll/{id}/export', [PayrollController::class, 'export'], ['roles' => ['HR', 'FIN'], 'name' => 'payroll.export']);
$router->post('/payroll/{id}/calculate', [PayrollController::class, 'calculate'], ['roles' => ['HR'], 'name' => 'payroll.calculate']);
$router->post('/payroll/{id}/submit', [PayrollController::class, 'submit'], ['roles' => ['HR'], 'name' => 'payroll.submit']);
$router->post('/payroll/{id}/approve', [PayrollController::class, 'approve'], ['roles' => ['FIN'], 'name' => 'payroll.approve']);
$router->post('/payroll/{id}/pay', [PayrollController::class, 'pay'], ['roles' => ['FIN'], 'name' => 'payroll.pay']);
$router->post('/payroll/{id}/reopen', [PayrollController::class, 'reopen'], ['roles' => ['HR'], 'name' => 'payroll.reopen']);
$router->post('/payroll/{id}/delete', [PayrollController::class, 'destroy'], ['roles' => ['HR'], 'name' => 'payroll.delete']);

$router->get('/me/payslip', [PayrollController::class, 'mine'], ['name' => 'me.payslip']);
$router->get('/verify/slip/{id}/{token}', [PayrollController::class, 'verify'], ['auth' => false, 'csrf' => false, 'name' => 'payroll.verify']);

/* --------------------------------------------------------------- Media files */
$router->get('/media/{folder}/{file}', [MediaController::class, 'show'], ['name' => 'media.show', 'csrf' => false]);

/* --------------------------------------------------- Approval engine */
$router->post('/overtime/{id}/decide', [ApprovalController::class, 'decideOvertime'], ['roles' => ['HR', 'KARYAWAN'], 'name' => 'overtime.decide']);
$router->post('/leave/{id}/decide', [ApprovalController::class, 'decideLeave'], ['roles' => ['HR', 'KARYAWAN'], 'name' => 'leave.decide']);
$router->post('/schedules/requests/{id}/decide', [ApprovalController::class, 'decideShiftChange'], ['roles' => ['HR', 'KARYAWAN'], 'name' => 'schedules.decide']);


/* ---------------------------------------- Batch 9: laporan & log aktivitas */
$router->get('/reports', [ReportController::class, 'index'], ['roles' => ['HR', 'FIN'], 'name' => 'reports.index']);
$router->get('/reports/payroll', [ReportController::class, 'payroll'], ['roles' => ['HR', 'FIN'], 'name' => 'reports.payroll']);
$router->get('/reports/{type}/csv', [ReportController::class, 'csv'], ['roles' => ['HR', 'FIN'], 'name' => 'reports.csv', 'csrf' => false]);
$router->get('/reports/{type}/pdf', [ReportController::class, 'pdf'], ['roles' => ['HR', 'FIN'], 'name' => 'reports.pdf', 'csrf' => false]);

$router->get('/logs', [LogController::class, 'index'], ['roles' => ['HR'], 'name' => 'logs.index']);
$router->get('/logs/export', [LogController::class, 'export'], ['roles' => ['HR'], 'name' => 'logs.export', 'csrf' => false]);
$router->post('/logs/purge', [LogController::class, 'purge'], ['roles' => ['HR'], 'name' => 'logs.purge']);


/* ----------------------------------- Batch 11: klaim & reimbursement */
$router->get('/claims/export', [ExpenseController::class, 'export'], ['roles' => ['HR', 'FIN'], 'name' => 'claims.export', 'csrf' => false]);
$router->get('/claims/categories', [ExpenseController::class, 'categories'], ['roles' => ['HR'], 'name' => 'claims.categories']);
$router->post('/claims/categories', [ExpenseController::class, 'storeCategory'], ['roles' => ['HR'], 'name' => 'claims.categories.store']);
$router->post('/claims/bulk-decide', [ExpenseController::class, 'bulkDecide'], ['roles' => ['HR', 'FIN'], 'name' => 'claims.bulk']);
$router->add('POST|PUT', '/claims/categories/{id}', [ExpenseController::class, 'updateCategory'], ['roles' => ['HR'], 'name' => 'claims.categories.update']);
$router->add('POST|DELETE', '/claims/categories/{id}/delete', [ExpenseController::class, 'destroyCategory'], ['roles' => ['HR'], 'name' => 'claims.categories.delete']);
$router->get('/claims', [ExpenseController::class, 'index'], ['roles' => ['HR', 'FIN'], 'name' => 'claims.index']);
$router->get('/claims/{id}', [ExpenseController::class, 'show'], ['name' => 'claims.show']);
$router->post('/claims/{id}/decide', [ExpenseController::class, 'decide'], ['roles' => ['HR', 'FIN', 'KARYAWAN'], 'name' => 'claims.decide']);
$router->post('/claims/{id}/pay', [ExpenseController::class, 'pay'], ['roles' => ['FIN'], 'name' => 'claims.pay']);

$router->get('/me/claims', [ExpenseController::class, 'mine'], ['name' => 'me.claims']);
$router->post('/me/claims', [ExpenseController::class, 'store'], ['name' => 'me.claims.store']);
$router->post('/me/claims/{id}/cancel', [ExpenseController::class, 'cancel'], ['name' => 'me.claims.cancel']);

/* ------------------------- Batch 12: pengumuman & notifikasi in-app */
$router->get('/announcements/manage', [AnnouncementController::class, 'manage'], ['roles' => ['HR'], 'name' => 'announcements.manage']);
$router->get('/announcements/create', [AnnouncementController::class, 'create'], ['roles' => ['HR'], 'name' => 'announcements.create']);
$router->post('/announcements', [AnnouncementController::class, 'store'], ['roles' => ['HR'], 'name' => 'announcements.store']);
$router->post('/announcements/read-all', [AnnouncementController::class, 'markAllRead'], ['name' => 'announcements.read.all']);
$router->get('/announcements', [AnnouncementController::class, 'index'], ['name' => 'announcements.index']);
$router->get('/announcements/{id}/edit', [AnnouncementController::class, 'edit'], ['roles' => ['HR'], 'name' => 'announcements.edit']);
$router->add('POST|PUT', '/announcements/{id}', [AnnouncementController::class, 'update'], ['roles' => ['HR'], 'name' => 'announcements.update']);
$router->post('/announcements/{id}/publish', [AnnouncementController::class, 'publish'], ['roles' => ['HR'], 'name' => 'announcements.publish']);
$router->post('/announcements/{id}/archive', [AnnouncementController::class, 'archive'], ['roles' => ['HR'], 'name' => 'announcements.archive']);
$router->add('POST|DELETE', '/announcements/{id}/delete', [AnnouncementController::class, 'destroy'], ['roles' => ['HR'], 'name' => 'announcements.delete']);
$router->post('/announcements/{id}/read', [AnnouncementController::class, 'markRead'], ['name' => 'announcements.read']);
$router->get('/announcements/{id}', [AnnouncementController::class, 'show'], ['name' => 'announcements.show']);

$router->get('/notifications/latest', [NotificationController::class, 'latest'], ['name' => 'notifications.latest', 'csrf' => false]);
$router->post('/notifications/read', [NotificationController::class, 'readAll'], ['name' => 'notifications.read']);
$router->post('/notifications/{id}/read', [NotificationController::class, 'read'], ['name' => 'notifications.item.read']);
$router->get('/notifications', [NotificationController::class, 'index'], ['name' => 'notifications']);

/* ------------------------------ Batch 13: penilaian kinerja */
$router->get('/performance/export', [PerformanceController::class, 'export'], ['roles' => ['HR'], 'name' => 'performance.export', 'csrf' => false]);
$router->get('/performance/periods', [PerformanceController::class, 'periods'], ['roles' => ['HR'], 'name' => 'performance.periods']);
$router->post('/performance/periods', [PerformanceController::class, 'storePeriod'], ['roles' => ['HR'], 'name' => 'performance.periods.store']);
$router->post('/performance/periods/{id}/open', [PerformanceController::class, 'openPeriod'], ['roles' => ['HR'], 'name' => 'performance.periods.open']);
$router->post('/performance/periods/{id}/calibration', [PerformanceController::class, 'calibration'], ['roles' => ['HR'], 'name' => 'performance.periods.calibration']);
$router->post('/performance/periods/{id}/close', [PerformanceController::class, 'closePeriod'], ['roles' => ['HR'], 'name' => 'performance.periods.close']);
$router->add('POST|PUT', '/performance/periods/{id}', [PerformanceController::class, 'updatePeriod'], ['roles' => ['HR'], 'name' => 'performance.periods.update']);
$router->add('POST|DELETE', '/performance/periods/{id}/delete', [PerformanceController::class, 'destroyPeriod'], ['roles' => ['HR'], 'name' => 'performance.periods.delete']);

$router->get('/performance/indicators', [PerformanceController::class, 'indicators'], ['roles' => ['HR'], 'name' => 'performance.indicators']);
$router->post('/performance/indicators', [PerformanceController::class, 'storeIndicator'], ['roles' => ['HR'], 'name' => 'performance.indicators.store']);
$router->add('POST|PUT', '/performance/indicators/{id}', [PerformanceController::class, 'updateIndicator'], ['roles' => ['HR'], 'name' => 'performance.indicators.update']);
$router->add('POST|DELETE', '/performance/indicators/{id}/delete', [PerformanceController::class, 'destroyIndicator'], ['roles' => ['HR'], 'name' => 'performance.indicators.delete']);

$router->get('/performance/team', [PerformanceController::class, 'team'], ['name' => 'performance.team']);
$router->get('/performance/team/{id}', [PerformanceController::class, 'assess'], ['name' => 'performance.assess']);
$router->post('/performance/team/{id}', [PerformanceController::class, 'submitAssessment'], ['name' => 'performance.assess.submit']);

$router->get('/performance', [PerformanceController::class, 'index'], ['roles' => ['HR'], 'name' => 'performance.index']);
$router->post('/performance/{id}/calibrate', [PerformanceController::class, 'calibrate'], ['roles' => ['HR'], 'name' => 'performance.calibrate']);
$router->post('/performance/{id}/acknowledge', [PerformanceController::class, 'acknowledge'], ['name' => 'performance.acknowledge']);
$router->get('/performance/{id}', [PerformanceController::class, 'show'], ['name' => 'performance.show']);

$router->get('/me/performance', [PerformanceController::class, 'mine'], ['name' => 'me.performance']);
$router->post('/me/performance/{id}', [PerformanceController::class, 'submitSelf'], ['name' => 'me.performance.submit']);

/* -------------------------------------------------- Pelatihan & sertifikasi */
$router->get('/trainings', [TrainingController::class, 'index'], ['roles' => ['HR'], 'name' => 'trainings.index']);
$router->post('/trainings', [TrainingController::class, 'storeSession'], ['roles' => ['HR'], 'name' => 'trainings.store']);

$router->get('/trainings/catalog', [TrainingController::class, 'catalog'], ['roles' => ['HR'], 'name' => 'trainings.catalog']);
$router->post('/trainings/catalog', [TrainingController::class, 'storeProgram'], ['roles' => ['HR'], 'name' => 'trainings.catalog.store']);
$router->add('POST|PUT', '/trainings/catalog/{id}', [TrainingController::class, 'updateProgram'], ['roles' => ['HR'], 'name' => 'trainings.catalog.update']);
$router->add('POST|DELETE', '/trainings/catalog/{id}/delete', [TrainingController::class, 'destroyProgram'], ['roles' => ['HR'], 'name' => 'trainings.catalog.delete']);

$router->get('/trainings/{id}/export', [TrainingController::class, 'export'], ['roles' => ['HR'], 'name' => 'trainings.export', 'csrf' => false]);
$router->post('/trainings/{id}/open', [TrainingController::class, 'openSession'], ['roles' => ['HR'], 'name' => 'trainings.open']);
$router->post('/trainings/{id}/start', [TrainingController::class, 'startSession'], ['roles' => ['HR'], 'name' => 'trainings.start']);
$router->post('/trainings/{id}/complete', [TrainingController::class, 'completeSession'], ['roles' => ['HR'], 'name' => 'trainings.complete']);
$router->post('/trainings/{id}/cancel', [TrainingController::class, 'cancelSession'], ['roles' => ['HR'], 'name' => 'trainings.cancel']);
$router->post('/trainings/{id}/participants', [TrainingController::class, 'storeParticipants'], ['roles' => ['HR'], 'name' => 'trainings.participants.store']);
$router->post('/trainings/{id}/results', [TrainingController::class, 'storeResult'], ['roles' => ['HR'], 'name' => 'trainings.results']);
$router->post('/trainings/{id}/participants/{pid}', [TrainingController::class, 'updateParticipantStatus'], ['roles' => ['HR'], 'name' => 'trainings.participants.status']);
$router->add('POST|DELETE', '/trainings/{id}/participants/{pid}/remove', [TrainingController::class, 'removeParticipant'], ['roles' => ['HR'], 'name' => 'trainings.participants.remove']);
$router->post('/trainings/{id}/approve-all', [TrainingController::class, 'approveAll'], ['roles' => ['HR'], 'name' => 'trainings.approve_all']);
$router->post('/trainings/{id}/attendance-all', [TrainingController::class, 'attendanceAll'], ['roles' => ['HR'], 'name' => 'trainings.attendance_all']);
$router->add('POST|PUT', '/trainings/{id}', [TrainingController::class, 'updateSession'], ['roles' => ['HR'], 'name' => 'trainings.update']);
$router->add('POST|DELETE', '/trainings/{id}/delete', [TrainingController::class, 'destroySession'], ['roles' => ['HR'], 'name' => 'trainings.delete']);
$router->get('/trainings/{id}', [TrainingController::class, 'show'], ['roles' => ['HR'], 'name' => 'trainings.show']);

$router->get('/me/trainings', [TrainingController::class, 'mine'], ['name' => 'me.trainings']);
$router->post('/me/trainings/{id}/enroll', [TrainingController::class, 'enroll'], ['name' => 'me.trainings.enroll']);
$router->post('/me/trainings/{id}/cancel', [TrainingController::class, 'cancelEnrollment'], ['name' => 'me.trainings.cancel']);

/* ------------------------------------------------ Rekrutmen & onboarding */
$router->get('/recruitment', [RecruitmentController::class, 'index'], ['roles' => ['HR'], 'name' => 'recruitment.index']);
$router->post('/recruitment', [RecruitmentController::class, 'storeJob'], ['roles' => ['HR'], 'name' => 'recruitment.jobs.store']);
$router->get('/recruitment/export', [RecruitmentController::class, 'export'], ['roles' => ['HR'], 'name' => 'recruitment.export', 'csrf' => false]);

$router->get('/recruitment/candidates', [RecruitmentController::class, 'candidates'], ['roles' => ['HR'], 'name' => 'recruitment.candidates']);
$router->post('/recruitment/candidates', [RecruitmentController::class, 'storeCandidate'], ['roles' => ['HR'], 'name' => 'recruitment.candidates.store']);
$router->post('/recruitment/candidates/{id}/stage', [RecruitmentController::class, 'moveStage'], ['roles' => ['HR'], 'name' => 'recruitment.candidates.stage']);
$router->post('/recruitment/candidates/{id}/reject', [RecruitmentController::class, 'reject'], ['roles' => ['HR'], 'name' => 'recruitment.candidates.reject']);
$router->post('/recruitment/candidates/{id}/withdraw', [RecruitmentController::class, 'withdraw'], ['roles' => ['HR'], 'name' => 'recruitment.candidates.withdraw']);
$router->post('/recruitment/candidates/{id}/hire', [RecruitmentController::class, 'hire'], ['roles' => ['HR'], 'name' => 'recruitment.candidates.hire']);
$router->add('POST|PUT', '/recruitment/candidates/{id}', [RecruitmentController::class, 'updateCandidate'], ['roles' => ['HR'], 'name' => 'recruitment.candidates.update']);
$router->get('/recruitment/candidates/{id}', [RecruitmentController::class, 'candidate'], ['roles' => ['HR'], 'name' => 'recruitment.candidates.show']);

$router->post('/recruitment/jobs/{id}/open', [RecruitmentController::class, 'openJob'], ['roles' => ['HR'], 'name' => 'recruitment.jobs.open']);
$router->post('/recruitment/jobs/{id}/hold', [RecruitmentController::class, 'holdJob'], ['roles' => ['HR'], 'name' => 'recruitment.jobs.hold']);
$router->post('/recruitment/jobs/{id}/close', [RecruitmentController::class, 'closeJob'], ['roles' => ['HR'], 'name' => 'recruitment.jobs.close']);
$router->add('POST|DELETE', '/recruitment/jobs/{id}/delete', [RecruitmentController::class, 'destroyJob'], ['roles' => ['HR'], 'name' => 'recruitment.jobs.delete']);
$router->add('POST|PUT', '/recruitment/jobs/{id}', [RecruitmentController::class, 'updateJob'], ['roles' => ['HR'], 'name' => 'recruitment.jobs.update']);
$router->get('/recruitment/jobs/{id}', [RecruitmentController::class, 'job'], ['roles' => ['HR'], 'name' => 'recruitment.jobs.show']);

/* ------------------------------------------------ Offboarding & exit */
$router->get('/offboarding', [OffboardingController::class, 'index'], ['roles' => ['HR'], 'name' => 'offboarding.index']);
$router->post('/offboarding', [OffboardingController::class, 'store'], ['roles' => ['HR'], 'name' => 'offboarding.store']);
$router->get('/offboarding/export', [OffboardingController::class, 'export'], ['roles' => ['HR'], 'name' => 'offboarding.export', 'csrf' => false]);

$router->post('/offboarding/{id}/manager-approve', [OffboardingController::class, 'approveManager'], ['name' => 'offboarding.approve_manager']);
$router->post('/offboarding/{id}/approve', [OffboardingController::class, 'approveHr'], ['roles' => ['HR'], 'name' => 'offboarding.approve_hr']);
$router->post('/offboarding/{id}/reject', [OffboardingController::class, 'reject'], ['name' => 'offboarding.reject']);
$router->post('/offboarding/{id}/cancel', [OffboardingController::class, 'cancel'], ['roles' => ['HR'], 'name' => 'offboarding.cancel']);
$router->post('/offboarding/{id}/complete', [OffboardingController::class, 'complete'], ['roles' => ['HR'], 'name' => 'offboarding.complete']);
$router->post('/offboarding/{id}/interview', [OffboardingController::class, 'interview'], ['roles' => ['HR'], 'name' => 'offboarding.interview']);
$router->post('/offboarding/{id}/assets', [OffboardingController::class, 'addAsset'], ['roles' => ['HR'], 'name' => 'offboarding.assets.store']);
$router->post('/offboarding/{id}/assets-all', [OffboardingController::class, 'returnAllAssets'], ['roles' => ['HR'], 'name' => 'offboarding.assets.all']);
$router->post('/offboarding/{id}/assets/{assetId}', [OffboardingController::class, 'updateAsset'], ['name' => 'offboarding.assets.update']);
$router->add('POST|DELETE', '/offboarding/{id}/assets/{assetId}/delete', [OffboardingController::class, 'removeAsset'], ['roles' => ['HR'], 'name' => 'offboarding.assets.delete']);
$router->post('/offboarding/{id}/bast', [OffboardingController::class, 'issueBast'], ['roles' => ['HR'], 'name' => 'offboarding.bast.issue']);
$router->get('/offboarding/{id}/bast', [OffboardingController::class, 'bastPdf'], ['name' => 'offboarding.bast.pdf', 'csrf' => false]);
$router->post('/offboarding/{id}/clearance', [OffboardingController::class, 'addClearance'], ['roles' => ['HR'], 'name' => 'offboarding.clearance.store']);
$router->post('/offboarding/{id}/clearance-all', [OffboardingController::class, 'completeClearance'], ['roles' => ['HR'], 'name' => 'offboarding.clearance.all']);
$router->post('/offboarding/{id}/clearance/{itemId}', [OffboardingController::class, 'updateClearance'], ['name' => 'offboarding.clearance.update']);
$router->add('POST|DELETE', '/offboarding/{id}/clearance/{itemId}/delete', [OffboardingController::class, 'removeClearance'], ['roles' => ['HR'], 'name' => 'offboarding.clearance.delete']);
$router->add('POST|PUT', '/offboarding/{id}', [OffboardingController::class, 'update'], ['roles' => ['HR'], 'name' => 'offboarding.update']);
$router->add('POST|DELETE', '/offboarding/{id}/delete', [OffboardingController::class, 'destroy'], ['roles' => ['HR'], 'name' => 'offboarding.delete']);
$router->get('/offboarding/{id}', [OffboardingController::class, 'show'], ['name' => 'offboarding.show']);

$router->get('/me/offboarding', [OffboardingController::class, 'mine'], ['name' => 'me.offboarding']);
$router->post('/me/offboarding', [OffboardingController::class, 'submitMine'], ['name' => 'me.offboarding.store']);
$router->post('/me/offboarding/{id}/withdraw', [OffboardingController::class, 'withdrawMine'], ['name' => 'me.offboarding.withdraw']);
$router->post('/me/offboarding/{id}/feedback', [OffboardingController::class, 'feedbackMine'], ['name' => 'me.offboarding.feedback']);

/* ------------------------------------------ Email & penjadwal (Batch 18) */
$router->get('/mail', [MailController::class, 'index'], ['roles' => ['HR'], 'name' => 'mail.index']);
$router->post('/mail/process', [MailController::class, 'process'], ['roles' => ['HR'], 'name' => 'mail.process']);
$router->post('/mail/prune', [MailController::class, 'prune'], ['roles' => ['HR'], 'name' => 'mail.prune']);
$router->get('/mail/{id}', [MailController::class, 'show'], ['roles' => ['HR'], 'name' => 'mail.show']);
$router->post('/mail/{id}/retry', [MailController::class, 'retry'], ['roles' => ['HR'], 'name' => 'mail.retry']);
$router->post('/mail/{id}/cancel', [MailController::class, 'cancel'], ['roles' => ['HR'], 'name' => 'mail.cancel']);

$router->post('/settings/email', [SettingsController::class, 'saveEmail'], ['roles' => ['HR'], 'name' => 'settings.email.save']);
$router->post('/settings/email/test', [SettingsController::class, 'testEmail'], ['roles' => ['HR'], 'name' => 'settings.email.test']);

$router->get('/scheduler', [SchedulerController::class, 'index'], ['roles' => ['HR'], 'name' => 'scheduler.index']);
$router->post('/scheduler/run-all', [SchedulerController::class, 'runAll'], ['roles' => ['HR'], 'name' => 'scheduler.run_all']);
$router->post('/scheduler/run/{job}', [SchedulerController::class, 'run'], ['roles' => ['HR'], 'name' => 'scheduler.run']);
$router->post('/scheduler/settings', [SchedulerController::class, 'saveSettings'], ['roles' => ['HR'], 'name' => 'scheduler.settings']);
$router->post('/scheduler/secret', [SchedulerController::class, 'rotateSecret'], ['roles' => ['HR'], 'name' => 'scheduler.secret']);
$router->post('/scheduler/prune', [SchedulerController::class, 'prune'], ['roles' => ['HR'], 'name' => 'scheduler.prune']);

// Endpoint cron via HTTP (token, tanpa login) — untuk layanan cron eksternal.
$router->get('/cron', [SchedulerController::class, 'http'], ['auth' => false, 'csrf' => false, 'name' => 'cron.http']);

/* --------------------------------------------------- Reset sistem (Batch 17) */
$router->get('/system/reset', [SystemController::class, 'reset'], ['roles' => ['HR'], 'name' => 'system.reset']);
$router->post('/system/reset', [SystemController::class, 'runReset'], ['roles' => ['HR'], 'name' => 'system.reset.run']);

/* --------------------------------------------- Halaman modul "segera hadir" */
$router->get('/coming-soon', [PlaceholderController::class, 'show'], ['name' => 'coming-soon', 'csrf' => false]);

$router->dispatch();
