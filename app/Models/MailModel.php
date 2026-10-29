<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Mailer;

/**
 * Query antrean email & preferensi pengiriman (Batch 18).
 *
 * Pengiriman sebenarnya ada di App\Core\Mailer; model ini hanya menyiapkan
 * data untuk halaman Pengaturan → Email dan halaman Antrean Email.
 */
final class MailModel
{
    /** @return array<string,mixed> */
    public function settings(): array
    {
        $config = Mailer::config();

        return [
            'enabled'     => Mailer::enabled(),
            'driver'      => $config['driver'],
            'host'        => $config['host'],
            'port'        => (int) $config['port'],
            'encryption'  => $config['encryption'],
            'username'    => $config['username'],
            'password'    => $config['password'],
            'has_password' => $config['password'] !== '',
            'from_email'  => $config['from_email'],
            'from_name'   => $config['from_name'],
            'reply_to'    => $config['reply_to'],
            'timeout'     => (int) $config['timeout'],
            'delivers'    => Mailer::delivers(),
        ];
    }

    /** @return array{status:string,q:string,template:string} */
    public function filtersFromRequest(): array
    {
        $status = strtoupper(trim((string) ($_GET['status'] ?? '')));
        if ($status !== '' && !isset(Mailer::STATUSES[$status])) {
            $status = '';
        }

        $template = trim((string) ($_GET['template'] ?? ''));
        if ($template !== '' && !preg_match('/^[a-z0-9_]+$/', $template)) {
            $template = '';
        }

        return [
            'status'   => $status,
            'q'        => trim((string) ($_GET['q'] ?? '')),
            'template' => $template,
        ];
    }

    /** @return array{0:string,1:array} */
    private function where(array $filters): array
    {
        $clauses = [];
        $params = [];

        if (($filters['status'] ?? '') !== '') {
            $clauses[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['template'] ?? '') !== '') {
            $clauses[] = 'template = ?';
            $params[] = $filters['template'];
        }
        if (($filters['q'] ?? '') !== '') {
            $clauses[] = '(recipient_email LIKE ? OR recipient_name LIKE ? OR subject LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return [$clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    public function countQueue(array $filters): int
    {
        if (!Database::tableExists('email_queue')) {
            return 0;
        }
        [$where, $params] = $this->where($filters);
        return (int) Database::value('SELECT COUNT(*) FROM email_queue' . $where, $params, 0);
    }

    /** @return array<int,array<string,mixed>> */
    public function queue(array $filters, int $limit = 20, int $offset = 0): array
    {
        if (!Database::tableExists('email_queue')) {
            return [];
        }
        [$where, $params] = $this->where($filters);

        return Database::all(
            'SELECT id, recipient_email, recipient_name, employee_id, template, subject, status,
                    attempts, driver, error, next_attempt_at, sent_at, created_at
               FROM email_queue' . $where . '
              ORDER BY id DESC
              LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $params
        );
    }

    public function find(int $id): ?array
    {
        if (!Database::tableExists('email_queue')) {
            return null;
        }
        return Database::one('SELECT * FROM email_queue WHERE id = ?', [$id]);
    }

    /** Daftar template yang tersedia di app/Views/mail. */
    public function templates(): array
    {
        $dir = APP_PATH . '/Views/mail';
        $list = [];
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            if (in_array($name, ['layout', 'generic', 'test'], true)) {
                continue;
            }
            $list[$name] = match ($name) {
                'contract_expiry' => 'Pengingat kontrak berakhir',
                'leave_expiry'    => 'Pengingat sisa cuti',
                'announcement'    => 'Pengumuman perusahaan',
                'password_reset'  => 'Reset kata sandi',
                default           => ucwords(str_replace('_', ' ', $name)),
            };
        }
        asort($list);
        return $list;
    }

    /** Statistik antrean + konfigurasi aktif. */
    public function stats(): array
    {
        $stats = Mailer::stats();
        $stats['driver'] = Mailer::driver();
        $stats['driver_label'] = Mailer::DRIVERS[Mailer::driver()];
        $stats['enabled'] = Mailer::enabled();
        $stats['delivers'] = Mailer::delivers();
        return $stats;
    }

    /** Kirim ulang satu baris antrean (reset percobaan). */
    public function retry(int $id): array
    {
        $row = $this->find($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Email tidak ditemukan.'];
        }
        if (in_array((string) $row['status'], ['SENT'], true)) {
            return ['ok' => false, 'message' => 'Email ini sudah terkirim.'];
        }

        Database::update('email_queue', [
            'status'          => 'PENDING',
            'attempts'        => 0,
            'error'           => null,
            'next_attempt_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $result = Mailer::deliver($id);

        return match ($result['status']) {
            'SENT'    => ['ok' => true, 'message' => 'Email berhasil dikirim ulang melalui ' . (string) $result['driver'] . '.'],
            'SKIPPED' => ['ok' => false, 'message' => 'Email tetap menunggu: ' . (string) $result['error']
                . ' Aktifkan lebih dulu di Pengaturan → Email.'],
            'FAILED'  => ['ok' => false, 'message' => 'Pengiriman gagal: ' . (string) $result['error']],
            default   => ['ok' => true, 'message' => 'Email dimasukkan kembali ke antrean (percobaan berikutnya otomatis).'],
        };
    }

    public function cancel(int $id): bool
    {
        $row = $this->find($id);
        if (!$row || (string) $row['status'] !== 'PENDING') {
            return false;
        }
        Database::update('email_queue', [
            'status' => 'CANCELLED',
            'error'  => 'Dibatalkan manual oleh HR.',
        ], 'id = ?', [$id]);
        return true;
    }

    /** Hapus riwayat email terkirim/batal lebih tua dari N hari. */
    public function prune(int $days = 90): int
    {
        if (!Database::tableExists('email_queue')) {
            return 0;
        }
        return Database::delete(
            'email_queue',
            "status IN ('SENT', 'CANCELLED') AND created_at < ?",
            [date('Y-m-d H:i:s', time() - max(1, $days) * 86400)]
        );
    }

    /** Jumlah email per template (untuk kartu ringkasan). */
    public function byTemplate(int $limit = 5): array
    {
        if (!Database::tableExists('email_queue')) {
            return [];
        }
        return Database::all(
            'SELECT template, COUNT(*) AS total
               FROM email_queue
              GROUP BY template
              ORDER BY total DESC
              LIMIT ' . max(1, $limit),
            []
        );
    }
}
