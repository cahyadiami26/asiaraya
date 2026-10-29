<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Upload;
use App\Core\Validator;
use App\Models\AnnouncementModel;
use App\Models\OrgModel;

/**
 * Papan pengumuman internal.
 *  - /announcements          : feed untuk semua karyawan (terfilter audiens)
 *  - /announcements/{id}     : detail + tandai dibaca
 *  - /announcements/manage   : kelola (HR) — draf, jadwal terbit, arsip
 */
final class AnnouncementController extends Controller
{
    private AnnouncementModel $announcements;
    private OrgModel $org;

    public function __construct()
    {
        $this->announcements = new AnnouncementModel();
        $this->org = new OrgModel();
    }

    /* ------------------------------------------------------------- karyawan */

    public function index(): void
    {
        $user = $this->employee();
        $filters = [
            'category' => (string) ($_GET['category'] ?? ''),
            'q'        => trim((string) ($_GET['q'] ?? '')),
            'unread'   => (int) ($_GET['unread'] ?? 0) === 1,
        ];
        if (!isset(AnnouncementModel::CATEGORIES[$filters['category']])) {
            $filters['category'] = '';
        }

        $paginator = Paginator::fromRequest($this->announcements->countFeed($user, $filters), 8);

        $this->view('announcements/index', [
            'title'       => 'Pengumuman',
            'pageTitle'   => 'Pengumuman',
            'user'        => $user,
            'items'       => $this->announcements->feed($user, $filters, $paginator->perPage, $paginator->offset()),
            'paginator'   => $paginator,
            'filters'     => $filters,
            'categories'  => AnnouncementModel::CATEGORIES,
            'unreadTotal' => $this->announcements->unreadCount($user),
            'pageLink'    => static function (int $page) use ($filters): string {
                $query = array_filter([
                    'category' => $filters['category'] ?: null,
                    'q'        => $filters['q'] ?: null,
                    'unread'   => $filters['unread'] ? 1 : null,
                    'page'     => $page > 1 ? $page : null,
                ], static fn ($v) => $v !== null && $v !== '');
                return url('announcements' . ($query ? '?' . http_build_query($query) : ''));
            },
        ]);
    }

    public function show(string $id): void
    {
        $user = $this->employee();
        $announcement = $this->announcements->find((int) $id);

        if (!$this->announcements->canView($announcement, $user)) {
            $this->abort(404, 'Pengumuman tidak ditemukan atau belum tersedia untuk Anda.');
        }

        $isLive = $announcement['status'] === 'PUBLISHED'
            && (empty($announcement['publish_at']) || $announcement['publish_at'] <= date('Y-m-d H:i:s'));
        if ($isLive) {
            $this->announcements->markRead((int) $announcement['id'], (int) $user['id']);
        }

        $isHR = Auth::hasRole('HR');

        $this->view('announcements/show', [
            'title'      => (string) $announcement['title'],
            'pageTitle'  => 'Pengumuman',
            'user'       => $user,
            'item'       => $announcement,
            'categories' => AnnouncementModel::CATEGORIES,
            'tones'      => AnnouncementModel::CATEGORY_TONES,
            'statuses'   => AnnouncementModel::STATUSES,
            'audienceLabel' => AnnouncementModel::audienceLabel($announcement),
            'audienceSize'  => $this->announcements->audienceSize($announcement),
            'readStats'  => $isHR || Auth::hasRole('FIN') ? $this->announcements->readStats($announcement) : null,
            'isHR'       => $isHR,
        ]);
    }

    public function markRead(string $id): void
    {
        $user = $this->employee();
        $announcement = $this->announcements->find((int) $id);
        if ($announcement && $this->announcements->canView($announcement, $user)) {
            $this->announcements->markRead((int) $id, (int) $user['id']);
        }
        back('/announcements');
    }

    public function markAllRead(): void
    {
        $count = $this->announcements->markAllRead($this->employee());
        flash('success', $count > 0
            ? $count . ' pengumuman ditandai sudah dibaca.'
            : 'Semua pengumuman sudah berstatus dibaca.');
        back('/announcements');
    }

    /* -------------------------------------------------------------- HR only */

    public function manage(): void
    {
        $this->requireRole('HR');

        $filters = [
            'status'   => (string) ($_GET['status'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
            'q'        => trim((string) ($_GET['q'] ?? '')),
        ];
        if (!isset(AnnouncementModel::STATUSES[$filters['status']])) {
            $filters['status'] = '';
        }
        if (!isset(AnnouncementModel::CATEGORIES[$filters['category']])) {
            $filters['category'] = '';
        }

        $paginator = Paginator::fromRequest($this->announcements->countManage($filters), 15);

        $this->view('announcements/manage', [
            'title'      => 'Kelola Pengumuman',
            'pageTitle'  => 'Kelola Pengumuman',
            'items'      => $this->announcements->manage($filters, $paginator->perPage, $paginator->offset()),
            'paginator'  => $paginator,
            'filters'    => $filters,
            'stats'      => $this->announcements->stats(),
            'categories' => AnnouncementModel::CATEGORIES,
            'tones'      => AnnouncementModel::CATEGORY_TONES,
            'statuses'   => AnnouncementModel::STATUSES,
            'statusTones' => AnnouncementModel::STATUS_TONES,
            'pageLink'   => static function (int $page) use ($filters): string {
                $query = array_filter([
                    'status'   => $filters['status'] ?: null,
                    'category' => $filters['category'] ?: null,
                    'q'        => $filters['q'] ?: null,
                    'page'     => $page > 1 ? $page : null,
                ], static fn ($v) => $v !== null && $v !== '');
                return url('announcements/manage' . ($query ? '?' . http_build_query($query) : ''));
            },
        ]);
    }

    public function create(): void
    {
        $this->requireRole('HR');

        $this->view('announcements/form', [
            'title'      => 'Tulis Pengumuman',
            'pageTitle'  => 'Tulis Pengumuman',
            'item'       => null,
            'categories' => AnnouncementModel::CATEGORIES,
            'audiences'  => AnnouncementModel::AUDIENCES,
            'targets'    => $this->targets(),
            'roles'      => ['HR' => 'HR Administrator', 'FIN' => 'Finance', 'KARYAWAN' => 'Karyawan'],
        ] + $this->withInput());
    }

    public function edit(string $id): void
    {
        $this->requireRole('HR');
        $item = $this->announcements->find((int) $id);
        if (!$item) {
            flash('error', 'Pengumuman tidak ditemukan.');
            redirect('/announcements/manage');
        }

        $this->view('announcements/form', [
            'title'      => 'Ubah Pengumuman',
            'pageTitle'  => 'Ubah Pengumuman',
            'item'       => $item,
            'categories' => AnnouncementModel::CATEGORIES,
            'audiences'  => AnnouncementModel::AUDIENCES,
            'targets'    => $this->targets(),
            'roles'      => ['HR' => 'HR Administrator', 'FIN' => 'Finance', 'KARYAWAN' => 'Karyawan'],
        ] + $this->withInput());
    }

    public function store(): void
    {
        $this->requireRole('HR');
        $data = $this->validated();

        $upload = Upload::document('attachment', 'announcements', 5 * 1024 * 1024);
        if ($upload['error'] !== null) {
            flash('error', $upload['error']);
            back('/announcements/create');
        }

        $id = $this->announcements->create([
            'title'          => $data['title'],
            'category'       => $data['category'],
            'body'           => $data['body'],
            'audience'       => $data['audience'],
            'audience_value' => $data['audience_value'],
            'is_pinned'      => $data['is_pinned'],
            'status'         => $data['status'],
            'notify'         => $data['notify'],
            'publish_at'     => $data['publish_at'],
            'expires_at'     => $data['expires_at'],
            'attachment'     => $upload['path'],
        ], $this->employeeId());

        Audit::log('announcement.create', 'announcement', $id, [
            'title'  => $data['title'],
            'status' => $data['status'],
        ]);

        $this->afterSave($id, $data, $upload['path']);

        flash('success', $data['status'] === 'PUBLISHED'
            ? 'Pengumuman diterbitkan dan dikirim ke lonceng karyawan terkait.'
            : 'Draf pengumuman disimpan. Terbitkan saat sudah siap.');
        redirect('/announcements/' . $id);
    }

    public function update(string $id): void
    {
        $this->requireRole('HR');
        $existing = $this->announcements->find((int) $id);
        if (!$existing) {
            flash('error', 'Pengumuman tidak ditemukan.');
            redirect('/announcements/manage');
        }

        $data = $this->validated($existing);

        $upload = Upload::document('attachment', 'announcements', 5 * 1024 * 1024);
        if ($upload['error'] !== null) {
            flash('error', $upload['error']);
            back('/announcements/' . (int) $id . '/edit');
        }

        $payload = [
            'title'          => $data['title'],
            'category'       => $data['category'],
            'body'           => $data['body'],
            'audience'       => $data['audience'],
            'audience_value' => $data['audience_value'],
            'is_pinned'      => $data['is_pinned'],
            'status'         => $data['status'],
            'notify'         => $data['notify'],
            'publish_at'     => $data['publish_at'],
            'expires_at'     => $data['expires_at'],
        ];

        if ($upload['path'] !== null) {
            $payload['attachment'] = $upload['path'];
            if (!empty($existing['attachment'])) {
                Upload::delete((string) $existing['attachment']);
            }
        } elseif ((int) ($_POST['remove_attachment'] ?? 0) === 1 && !empty($existing['attachment'])) {
            $payload['attachment'] = null;
            Upload::delete((string) $existing['attachment']);
        }

        $this->announcements->update((int) $id, $payload, $this->employeeId());
        Audit::log('announcement.update', 'announcement', (int) $id, ['title' => $data['title']]);

        $this->afterSave((int) $id, $data, $payload['attachment'] ?? ($existing['attachment'] ?? null), (string) $existing['status']);

        flash('success', 'Perubahan pengumuman disimpan.');
        redirect('/announcements/' . (int) $id);
    }

    public function publish(string $id): void
    {
        $this->requireRole('HR');
        $item = $this->announcements->find((int) $id);
        if (!$item) {
            flash('error', 'Pengumuman tidak ditemukan.');
            redirect('/announcements/manage');
        }

        $this->announcements->setStatus((int) $id, 'PUBLISHED', $this->employeeId());
        $item['status'] = 'PUBLISHED';
        $item['publish_at'] = date('Y-m-d H:i:s');

        $sent = $this->announcements->notifyAudience($item);
        Audit::log('announcement.publish', 'announcement', (int) $id, ['notified' => $sent]);

        flash('success', 'Pengumuman diterbitkan. ' . $sent . ' karyawan menerima notifikasi.');
        redirect('/announcements/' . (int) $id);
    }

    public function archive(string $id): void
    {
        $this->requireRole('HR');
        $this->announcements->setStatus((int) $id, 'ARCHIVED', $this->employeeId());
        Audit::log('announcement.archive', 'announcement', (int) $id);
        flash('success', 'Pengumuman diarsipkan dan tidak tampil lagi di feed karyawan.');
        redirect('/announcements/manage');
    }

    public function destroy(string $id): void
    {
        $this->requireRole('HR');
        $item = $this->announcements->find((int) $id);
        if ($item) {
            if (!empty($item['attachment'])) {
                Upload::delete((string) $item['attachment']);
            }
            $this->announcements->delete((int) $id);
            Audit::log('announcement.delete', 'announcement', (int) $id, ['title' => $item['title']]);
            flash('success', 'Pengumuman dihapus.');
        }
        redirect('/announcements/manage');
    }

    /* ---------------------------------------------------------------- util */

    /**
     * Validasi bersama untuk create/update.
     *
     * @return array<string,mixed>
     */
    private function validated(?array $existing = null): array
    {
        $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('title', 'Judul pengumuman')
              ->custom(mb_strlen((string) ($_POST['title'] ?? '')) > 180, 'title', 'Judul maksimal 180 karakter.')
              ->required('body', 'Isi pengumuman')
              ->min('body', 'Isi pengumuman', 20)
              ->in('category', 'Kategori', array_keys(AnnouncementModel::CATEGORIES));
        }, '/announcements/manage');

        // Target audiens dikirim sebagai satu nilai: ALL | UNIT:1 | DEPARTMENT:2 | POSITION:3 | ROLE:HR
        $target = (string) input('audience_target', 'ALL');
        $audience = 'ALL';
        $audienceValue = '';
        if ($target !== '' && $target !== 'ALL') {
            [$audience, $audienceValue] = array_pad(explode(':', $target, 2), 2, '');
        }
        if (!isset(AnnouncementModel::AUDIENCES[$audience])) {
            flash('error', 'Audiens pengumuman tidak valid.');
            back('/announcements/manage');
        }
        if ($audience !== 'ALL' && $audienceValue === '') {
            flash('error', 'Pilih target audiens terlebih dahulu.');
            back('/announcements/manage');
        }

        $when = (string) input('publish_when', 'now'); // now | schedule | draft
        $publishAt = null;
        $status = 'DRAFT';

        if ($when === 'now') {
            $status = 'PUBLISHED';
            $publishAt = date('Y-m-d H:i:s');
        } elseif ($when === 'schedule') {
            $scheduled = trim((string) input('publish_at', ''));
            if ($scheduled === '' || strtotime($scheduled) === false) {
                flash('error', 'Isi tanggal & jam terbit untuk pengumuman terjadwal.');
                back('/announcements/manage');
            }
            $status = 'PUBLISHED';
            $publishAt = date('Y-m-d H:i:s', (int) strtotime($scheduled));
        } elseif ($when === 'keep' && $existing !== null && $existing['status'] !== 'DRAFT') {
            $status = (string) $existing['status'];
            $publishAt = $existing['publish_at'];
        }

        $expires = trim((string) input('expires_at', ''));
        $expiresAt = $expires === '' ? null : date('Y-m-d H:i:s', (int) strtotime($expires));
        if ($expiresAt !== null && $publishAt !== null && $expiresAt < $publishAt) {
            flash('error', 'Tanggal berakhir tidak boleh lebih awal dari jadwal terbit.');
            back('/announcements/manage');
        }

        return [
            'title'          => trim((string) input('title', '')),
            'category'       => (string) input('category', 'INFO'),
            'body'           => trim((string) input('body', '')),
            'audience'       => $audience,
            'audience_value' => $audienceValue,
            'is_pinned'      => (int) input('is_pinned', 0) === 1 ? 1 : 0,
            'notify'         => (int) input('notify', 0) === 1 ? 1 : 0,
            'status'         => $status,
            'publish_at'     => $publishAt,
            'expires_at'     => $expiresAt,
        ];
    }

    /** Kirim notifikasi bila pengumuman baru saja terbit. */
    private function afterSave(int $id, array $data, ?string $attachment, ?string $previousStatus = null): void
    {
        if ($data['status'] !== 'PUBLISHED') {
            return;
        }
        // Saat update, hanya kirim bila status berubah menjadi terbit.
        if ($previousStatus !== null && $previousStatus === 'PUBLISHED') {
            return;
        }
        $item = $this->announcements->find($id);
        if ($item) {
            $this->announcements->notifyAudience($item);
        }
    }

    /** Pilihan target audiens: unit, departemen, posisi. */
    private function targets(): array
    {
        return [
            'units'       => $this->org->units(),
            'departments' => $this->org->departments(),
            'positions'   => $this->org->positions(),
        ];
    }
}
