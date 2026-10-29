<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\View;

abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $data['currentUser'] = Auth::user();
        $data['pageTitle'] = $data['title'] ?? config('app_name');

        // Lonceng notifikasi & badge pengumuman tersedia di semua halaman.
        if (Auth::check()) {
            $context = $this->notificationContext();
            $data += [
                'unreadNotifications' => $context['unreadNotifications'],
                'recentNotifications' => $context['recentNotifications'],
                'unreadAnnouncements' => $context['unreadAnnouncements'],
            ];
            $data['badges'] = ($data['badges'] ?? []) + $context['badges'];
        }

        View::render($template, $data, $layout);
    }

    /**
     * Ringkasan notifikasi untuk topbar (dipakai setiap render halaman).
     * Dibungkus try/catch agar basis data lama tanpa tabel notifikasi tetap aman.
     *
     * @return array{unreadNotifications:int,recentNotifications:array,unreadAnnouncements:int,badges:array}
     */
    private function notificationContext(): array
    {
        try {
            $employeeId = (int) Auth::id();
            $notifications = new \App\Models\NotificationModel();
            $announcements = new \App\Models\AnnouncementModel();

            $unreadAnnouncements = $announcements->unreadCount(Auth::user());
            $unreadNotifications = $notifications->unreadCount($employeeId);

            $performance = new \App\Models\PerformanceModel();
            $badges = [
                'announcements' => $unreadAnnouncements,
                'notifications' => $unreadNotifications,
                'performance_team' => $performance->teamPendingCount($employeeId),
            ];
            if (Auth::hasRole('HR')) {
                $training = new \App\Models\TrainingModel();
                $badges['training'] = $training->pendingNominationCount();
                $badges['recruitment'] = (new \App\Models\RecruitmentModel())->freshCount();
                $badges['offboarding'] = (new \App\Models\OffboardingModel())->pendingCount();
                $badges['mail_pending'] = \App\Core\Database::tableExists('email_queue')
                    ? (int) \App\Core\Database::value("SELECT COUNT(*) FROM email_queue WHERE status = 'PENDING'", [], 0)
                    : 0;
                $badges['performance'] = (int) \App\Core\Database::value(
                    "SELECT COUNT(*) FROM performance_reviews r
                       JOIN review_periods p ON p.id = r.period_id
                      WHERE r.status = 'MANAGER_DONE' AND p.status IN ('MANAGER','CALIBRATION')",
                    [],
                    0
                );
            }

            return [
                'unreadNotifications' => $unreadNotifications,
                'recentNotifications' => $notifications->recent($employeeId, 5),
                'unreadAnnouncements' => $unreadAnnouncements,
                'badges'              => $badges,
            ];
        } catch (\Throwable) {
            return [
                'unreadNotifications' => 0,
                'recentNotifications' => [],
                'unreadAnnouncements' => 0,
                'badges'              => [],
            ];
        }
    }

    protected function redirect(string $path): never
    {
        redirect($path);
    }

    protected function json(mixed $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function abort(int $status, string $message = ''): never
    {
        http_response_code($status);
        $this->view('errors/error', ['code' => $status, 'message' => $message, 'title' => 'Terjadi Kesalahan']);
        exit;
    }

    protected function requireRole(string|array $roles): void
    {
        if (!Auth::hasRole($roles)) {
            $this->abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }
    }

    protected function requireManagerOrHr(int $employeeId): void
    {
        if (Auth::hasRole('HR') || Auth::isManagerOf($employeeId)) {
            return;
        }
        $this->abort(403, 'Hanya atasan langsung atau HR yang dapat melakukan aksi ini.');
    }

    /**
     * Validate, remember errors + old input, then redirect back.
     * Returns validated payload when it passes (caller continues).
     */
    protected function validateOrRedirect(callable $rules, string $fallback = '/'): array
    {
        $data = $_POST;
        $validator = new Validator($data);
        $rules($validator);

        if ($validator->fails()) {
            Session::set('_errors', $validator->errors());
            Session::set('_old', $data);
            flash('error', $validator->firstError() ?? 'Periksa kembali data yang diisi.');
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            redirect($referer !== '' ? $referer : $fallback);
        }

        Session::forget('_old');
        Session::forget('_errors');
        return $data;
    }

    /** Data for form re-render. */
    protected function withInput(array $extra = []): array
    {
        return [
            'errors' => Session::pull('_errors', []),
            'old'    => Session::pull('_old', []),
        ] + $extra;
    }

    protected function employee(): ?array
    {
        return Auth::user();
    }

    protected function employeeId(): int
    {
        $id = Auth::id();
        if (!$id) {
            $this->abort(401, 'Sesi berakhir.');
        }
        return $id;
    }
}
