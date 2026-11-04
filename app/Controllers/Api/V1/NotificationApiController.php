<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Core\ApiRequest;
use App\Models\NotificationModel;

/**
 * Notifikasi dalam aplikasi.
 *
 * GET  /api/v1/notifications?unread=1&type=&page=
 * POST /api/v1/notifications/{id}/read
 * POST /api/v1/notifications/read-all
 */
final class NotificationApiController extends ApiController
{
    use ApiPresenter;

    private NotificationModel $notifications;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->notifications = new NotificationModel();
    }

    public function index(): void
    {
        $employeeId = (int) $this->user['id'];
        $page = ApiRequest::page();
        $perPage = ApiRequest::perPage(20);

        $filters = [
            'unread' => ApiRequest::boolean(ApiRequest::query('unread', false)),
            'type'   => (string) ApiRequest::query('type', ''),
        ];

        $rows = $this->notifications->list($employeeId, $filters, $perPage, ($page - 1) * $perPage);
        $total = $this->notifications->count($employeeId, $filters);

        $this->paginated(
            array_map(fn (array $row): array => $this->notificationPayload($row), $rows),
            $total,
            $page,
            $perPage,
            [
                'unread_count' => $this->notifications->unreadCount($employeeId),
                'types'        => $this->notifications->typeCounts($employeeId),
            ]
        );
    }

    public function read(string $id): void
    {
        $employeeId = (int) $this->user['id'];
        $notification = $this->notifications->find((int) $id, $employeeId);

        if (!$notification) {
            $this->fail(404, 'Notifikasi tidak ditemukan.', [], 'NOT_FOUND');
        }

        $this->notifications->markRead((int) $id, $employeeId);

        $this->ok([
            'message'      => 'Notifikasi ditandai sudah dibaca.',
            'unread_count' => $this->notifications->unreadCount($employeeId),
        ]);
    }

    public function readAll(): void
    {
        $employeeId = (int) $this->user['id'];
        $updated = $this->notifications->markAllRead($employeeId);

        $this->ok([
            'updated'      => $updated,
            'message'      => 'Semua notifikasi ditandai sudah dibaca.',
            'unread_count' => $this->notifications->unreadCount($employeeId),
        ]);
    }
}
