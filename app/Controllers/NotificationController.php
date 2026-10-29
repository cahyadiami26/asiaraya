<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Paginator;
use App\Models\NotificationModel;

/**
 * Pusat notifikasi in-app.
 *  - /notifications            : inbox lengkap (filter belum dibaca / tipe / cari)
 *  - /notifications/{id}/read  : tandai satu notifikasi dibaca
 *  - /notifications/read       : tandai semua dibaca
 *  - /notifications/latest     : JSON ringkas untuk lonceng (polling ringan)
 */
final class NotificationController extends Controller
{
    private NotificationModel $notifications;

    public function __construct()
    {
        $this->notifications = new NotificationModel();
    }

    public function index(): void
    {
        $user = $this->employee();
        $employeeId = (int) $user['id'];

        $filters = [
            'unread' => (int) ($_GET['unread'] ?? 0) === 1,
            'type'   => (string) ($_GET['type'] ?? ''),
            'q'      => trim((string) ($_GET['q'] ?? '')),
        ];

        $paginator = Paginator::fromRequest($this->notifications->count($employeeId, $filters), 25);

        $this->view('notifications/index', [
            'title'      => 'Notifikasi',
            'pageTitle'  => 'Notifikasi',
            'user'       => $user,
            'items'      => $this->notifications->list($employeeId, $filters, $paginator->perPage, $paginator->offset()),
            'paginator'  => $paginator,
            'filters'    => $filters,
            'types'      => NotificationModel::TYPES,
            'counts'     => $this->notifications->typeCounts($employeeId),
            'pageLink'   => static function (int $page) use ($filters): string {
                $query = array_filter([
                    'unread' => $filters['unread'] ? 1 : null,
                    'type'   => $filters['type'] ?: null,
                    'q'      => $filters['q'] ?: null,
                    'page'   => $page > 1 ? $page : null,
                ], static fn ($v) => $v !== null && $v !== '');
                return url('notifications' . ($query ? '?' . http_build_query($query) : ''));
            },
        ]);
    }

    public function read(string $id): void
    {
        $employeeId = $this->employeeId();
        $item = $this->notifications->find((int) $id, $employeeId);

        if (!$item) {
            back('/notifications');
        }

        $this->notifications->markRead((int) $id, $employeeId);

        if (!empty($item['link'])) {
            redirect((string) $item['link']);
        }
        back('/notifications');
    }

    public function readAll(): void
    {
        $count = $this->notifications->markAllRead($this->employeeId());
        flash('success', $count > 0
            ? $count . ' notifikasi ditandai sudah dibaca.'
            : 'Semua notifikasi sudah dibaca.');
        back('/notifications');
    }

    /** Ringkasan JSON untuk lonceng (dipanggil berkala dari app.js). */
    public function latest(): void
    {
        if (!Auth::check()) {
            $this->json(['unread' => 0, 'items' => []], 401);
        }

        $employeeId = (int) Auth::id();
        $items = array_map(static function (array $row): array {
            return [
                'id'      => (int) $row['id'],
                'title'   => (string) $row['title'],
                'message' => (string) ($row['message'] ?? ''),
                'link'    => $row['link'] ? url(ltrim((string) $row['link'], '/')) : null,
                'type'    => (string) $row['type'],
                'unread'  => !$row['read_at'],
                'time'    => waktu_lalu($row['created_at']),
            ];
        }, $this->notifications->recent($employeeId, 6));

        $this->json([
            'unread' => $this->notifications->unreadCount($employeeId),
            'items'  => $items,
        ]);
    }
}
