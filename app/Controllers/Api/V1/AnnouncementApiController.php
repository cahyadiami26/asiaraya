<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Core\ApiRequest;
use App\Core\Audit;
use App\Models\AnnouncementModel;

/**
 * Pengumuman / informasi perusahaan.
 *
 * GET  /api/v1/announcements?category=&unread=1&q=&page=
 * GET  /api/v1/announcements/{id}
 * POST /api/v1/announcements/{id}/read
 */
final class AnnouncementApiController extends ApiController
{
    use ApiPresenter;

    private AnnouncementModel $announcements;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->announcements = new AnnouncementModel();
    }

    public function index(): void
    {
        $page = ApiRequest::page();
        $perPage = ApiRequest::perPage(20);

        $filters = [
            'category' => (string) ApiRequest::query('category', ''),
            'q'        => (string) ApiRequest::query('q', ''),
            'unread'   => ApiRequest::boolean(ApiRequest::query('unread', false)),
        ];

        $rows = $this->announcements->feed($this->user, $filters, $perPage, ($page - 1) * $perPage);
        $total = $this->announcements->countFeed($this->user, $filters);

        $this->paginated(
            array_map(fn (array $row): array => $this->announcementPayload($row), $rows),
            $total,
            $page,
            $perPage,
            ['unread_count' => $this->announcements->unreadCount($this->user)]
        );
    }

    public function show(string $id): void
    {
        $announcement = $this->announcements->find((int) $id);
        if (!$announcement || !$this->announcements->canView($announcement, $this->user)) {
            $this->fail(404, 'Pengumuman tidak ditemukan.', [], 'NOT_FOUND');
        }

        $payload = $this->announcementPayload($announcement, true);
        $payload['is_read'] = $this->announcements->isRead((int) $announcement['id'], (int) $this->user['id']);

        $this->ok($payload);
    }

    public function read(string $id): void
    {
        $announcement = $this->announcements->find((int) $id);
        if (!$announcement || !$this->announcements->canView($announcement, $this->user)) {
            $this->fail(404, 'Pengumuman tidak ditemukan.', [], 'NOT_FOUND');
        }

        $this->announcements->markRead((int) $announcement['id'], (int) $this->user['id']);
        Audit::log('api.announcement.read', 'announcement', (int) $announcement['id']);

        $this->ok([
            'message'      => 'Pengumuman ditandai sudah dibaca.',
            'unread_count' => $this->announcements->unreadCount($this->user),
        ]);
    }
}
