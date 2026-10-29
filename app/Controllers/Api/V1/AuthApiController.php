<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiAuth;
use App\Core\ApiController;
use App\Core\ApiRequest;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Settings;
use App\Core\Validator;
use App\Models\AnnouncementModel;
use App\Models\AttendanceModel;
use App\Models\ExpenseModel;
use App\Models\LeaveModel;
use App\Models\NotificationModel;
use App\Models\OvertimeModel;

/**
 * Login, sesi perangkat, profil, dan konfigurasi aplikasi.
 *
 * POST /api/v1/auth/login     → access + refresh token
 * POST /api/v1/auth/refresh   → rotasi token
 * POST /api/v1/auth/logout    → cabut token perangkat ini
 * GET  /api/v1/auth/me        → profil + ringkasan
 * GET  /api/v1/app/config     → konfigurasi & kebijakan untuk aplikasi Android
 */
final class AuthApiController extends ApiController
{
    use ApiPresenter;

    public function login(): void
    {
        $data = $this->body();

        $this->validate($data, static function (Validator $v): void {
            $v->required('identifier', 'Email / NIK / username')
              ->required('password', 'Kata sandi');
        });

        $this->assertLoginThrottle();

        $identifier = (string) $data['identifier'];
        $employee = Auth::verifyCredentials($identifier, (string) $data['password']);

        if ($employee === null) {
            $this->recordLoginFailure($identifier);
            $this->fail(401, 'Email/NIK atau kata sandi salah.', [], 'INVALID_CREDENTIALS');
        }

        $tokens = ApiAuth::issue((int) $employee['id'], [
            'device_name' => (string) ($data['device_name'] ?? ''),
            'platform'    => (string) ($data['platform'] ?? 'android'),
            'app_version' => (string) ($data['app_version'] ?? ''),
        ]);

        Database::update('employees', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $employee['id']]);
        Audit::log('api.login', 'employee', (int) $employee['id'], [
            'device'  => substr((string) ($data['device_name'] ?? ''), 0, 80),
            'version' => substr((string) ($data['app_version'] ?? ''), 0, 30),
        ]);

        $profile = Auth::findById((int) $employee['id']);

        $this->ok([
            'tokens'   => $tokens,
            'employee' => $this->employeePayload($profile ?? $employee),
        ]);
    }

    public function refresh(): void
    {
        $refreshToken = (string) ApiRequest::input('refresh_token', '');
        if ($refreshToken === '') {
            $this->fail(422, 'Refresh token wajib dikirim.', ['refresh_token' => 'Wajib diisi.'], 'VALIDATION_ERROR');
        }

        $result = ApiAuth::refresh($refreshToken);
        if (!$result['ok']) {
            $this->fail(401, (string) $result['error'], [], 'INVALID_REFRESH_TOKEN');
        }

        $this->ok(['tokens' => $result['tokens']]);
    }

    public function logout(): void
    {
        $this->requireAuth();
        ApiAuth::revokeCurrent();
        $this->ok(['message' => 'Anda telah keluar dari aplikasi.']);
    }

    public function me(): void
    {
        $user = $this->requireAuth();
        $employeeId = (int) $user['id'];

        $approvalEngine = new \App\Core\ApprovalEngine();
        $approvals = $approvalEngine->inboxCounts($user);

        $this->ok([
            'employee' => $this->employeePayload($user),
            'counters' => [
                'notifications_unread' => (new NotificationModel())->unreadCount($employeeId),
                'announcements_unread' => (new AnnouncementModel())->unreadCount($user),
                'approvals_pending'    => array_sum($approvals),
                'approvals'            => $approvals,
                'leave_open'           => (int) Database::value(
                    "SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status IN ('PENDING','MANAGER_APPROVED')",
                    [$employeeId],
                    0
                ),
                'overtime_open'        => (int) Database::value(
                    "SELECT COUNT(*) FROM overtime_requests WHERE employee_id = ? AND status IN ('PENDING','MANAGER_APPROVED')",
                    [$employeeId],
                    0
                ),
                'claims_open'          => (int) Database::value(
                    "SELECT COUNT(*) FROM expense_claims WHERE employee_id = ? AND status IN ('PENDING','MANAGER_APPROVED','APPROVED','SCHEDULED')",
                    [$employeeId],
                    0
                ),
            ],
            'attendance_today' => $this->attendanceTodayPayload((new AttendanceModel())->context($employeeId)),
            'permissions' => [
                'can_approve' => in_array((string) $user['role'], ['HR', 'FIN'], true) || $this->isManagerOfAnybody($employeeId),
                'role'        => (string) $user['role'],
            ],
            'server_time' => date('c'),
        ]);
    }

    /** GET /api/v1/app/config */
    public function appConfig(): void
    {
        $user = $this->requireAuth();
        $attendance = new AttendanceModel();
        $leave = new LeaveModel();
        $overtime = new OvertimeModel();

        $this->ok([
            'app' => [
                'name'          => (string) config('app_name', 'HRIS Asia Raya'),
                'version'       => APP_VERSION,
                'server_time'   => date('c'),
                'timezone'      => (string) config('timezone', 'Asia/Jakarta'),
                'locale'        => (string) config('locale', 'id'),
                'min_android_version' => (string) Settings::get('mobile_min_android_version', '1.0.0'),
            ],
            'company' => [
                'name'    => (string) setting('company_name', 'PT Asia Raya Group'),
                'legal'   => (string) setting('company_legal_name', ''),
                'address' => (string) setting('company_address', ''),
                'phone'   => (string) setting('company_phone', ''),
                'email'   => (string) setting('company_email', ''),
                'logo_url' => $this->mediaUrl((string) setting('company_logo', '')) ?: null,
            ],
            'employee' => $this->employeePayload($user),
            'features' => [
                'announcements'    => true,
                'schedule'         => true,
                'attendance'       => true,
                'overtime'         => (bool) $overtime->settings()['enabled'],
                'leave'            => true,
                'payslip'          => true,
                'claims'           => true,
                'tax_slip'         => true,
                'approvals'        => true,
                'offline_punch'    => true,
            ],
            'attendance' => [
                'work_start'        => (string) Settings::get('attendance_work_start', '08:00'),
                'work_end'          => (string) Settings::get('attendance_work_end', '17:00'),
                'late_tolerance'    => (int) Settings::get('attendance_late_tolerance', 15),
                'geofence_required' => Settings::bool('attendance_geofence_required', true),
                'selfie_required'   => Settings::bool('attendance_selfie_required', true),
                'wfh_bypass'        => Settings::bool('attendance_wfh_bypass_geofence', true),
                'photo_max_width'   => (int) Settings::get('attendance_photo_max_width', 720),
                'photo_quality'     => (int) Settings::get('attendance_photo_quality', 70),
                'punch_radius_m'    => (int) Database::value(
                    'SELECT geofence_radius FROM business_units WHERE id = ?',
                    [(int) ($user['business_unit_id'] ?? 0)],
                    50
                ),
                'unit' => [
                    'name' => $user['business_unit_name'] ?? null,
                    'lat'  => Database::value('SELECT latitude FROM business_units WHERE id = ?', [(int) ($user['business_unit_id'] ?? 0)]),
                    'lng'  => Database::value('SELECT longitude FROM business_units WHERE id = ?', [(int) ($user['business_unit_id'] ?? 0)]),
                ],
            ],
            'leave' => $leave->settings() + [
                'types' => array_map(static fn (array $t): array => [
                    'id'                  => (int) $t['id'],
                    'code'                => (string) $t['code'],
                    'name'                => (string) $t['name'],
                    'quota_days'          => (float) ($t['quota_days'] ?? 0),
                    'requires_attachment' => (int) ($t['requires_attachment'] ?? 0) === 1,
                    'max_per_request'     => (int) ($t['max_per_request'] ?? 0),
                    'min_notice_days'     => (int) ($t['min_notice_days'] ?? 0),
                ], $leave->types(true)),
            ],
            'overtime' => $overtime->settings(),
            'claims'   => [
                'max_age_days' => (int) Settings::get('claim_max_age_days', 90),
            ],
            'attendance_settings' => $attendance->settings(),
            'limits' => [
                'upload_max_mb'    => 5,
                'photo_max_mb'     => 4,
                'per_page_default' => 20,
                'per_page_max'     => 100,
            ],
        ]);
    }

    /* ------------------------------------------------------------- internal */

    private function isManagerOfAnybody(int $employeeId): bool
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM employees WHERE direct_manager_id = ? AND flag = ?',
            [$employeeId, 'ACTIVE'],
            0
        ) > 0;
    }

    /** Maksimal 5 kegagalan per menit per alamat IP. */
    private function assertLoginThrottle(): void
    {
        $attempts = (int) Database::value(
            "SELECT COUNT(*) FROM activity_logs
              WHERE action = 'api.login_failed' AND ip_address = ? AND created_at >= ?",
            [ApiRequest::ip(), date('Y-m-d H:i:s', time() - 60)],
            0
        );

        if ($attempts >= 5) {
            $this->fail(429, 'Terlalu banyak percobaan masuk. Coba lagi dalam satu menit.', [], 'TOO_MANY_ATTEMPTS');
        }
    }

    private function recordLoginFailure(string $identifier): void
    {
        try {
            Database::insert('activity_logs', [
                'employee_id' => null,
                'action'      => 'api.login_failed',
                'entity'      => 'employee',
                'meta'        => json_encode(['identifier' => $identifier], JSON_UNESCAPED_UNICODE),
                'ip_address'  => ApiRequest::ip(),
                'user_agent'  => ApiRequest::userAgent(),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // pencatatan bersifat best-effort
        }
    }
}
