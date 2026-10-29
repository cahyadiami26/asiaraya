<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Penjadwal tugas (cron) native.
 *
 * Setiap eksekusi dicatat ke tabel `cron_runs` agar HR bisa melihat riwayat,
 * jumlah data yang diproses, dan pesan kesalahan tanpa membuka log server.
 *
 * Tiga cara menjalankan:
 *  1. Cron server  : `php public/cron.php` (semua job) atau `php public/cron.php email_queue`
 *  2. HTTP         : `/cron.php?token=<cron_secret>` dari layanan cron eksternal
 *  3. Otomatis     : sapuan ringan sekali per menit saat ada halaman dibuka (bisa dimatikan)
 */
final class Scheduler
{
    /** Definisi job. `interval` dalam menit dipakai oleh mode otomatis. */
    public const JOBS = [
        'email_queue' => [
            'label'    => 'Kirim antrean email',
            'desc'     => 'Mengirim email berstatus menunggu ke penerima sesuai driver email aktif.',
            'interval' => 5,
            'toggle'   => 'cron_job_email_queue',
            'icon'     => 'send',
        ],
        'contract_expiry' => [
            'label'    => 'Pengingat kontrak berakhir',
            'desc'     => 'Email + notifikasi ke karyawan yang kontrak kerjanya berakhir dalam jendela H-N.',
            'interval' => 720,
            'toggle'   => 'cron_job_contract',
            'icon'     => 'file',
        ],
        'leave_expiry' => [
            'label'    => 'Pengingat sisa cuti',
            'desc'     => 'Mengingatkan karyawan yang masih punya sisa cuti menjelang akhir tahun.',
            'interval' => 1440,
            'toggle'   => 'cron_job_leave',
            'icon'     => 'calendar',
        ],
        'announcement_publish' => [
            'label'    => 'Notifikasi pengumuman terjadwal',
            'desc'     => 'Mengirim notifikasi lonceng (dan email bila diaktifkan) saat jadwal tayang pengumuman tiba.',
            'interval' => 15,
            'toggle'   => 'cron_job_announcement',
            'icon'     => 'megaphone',
        ],
    ];

    /* ============================================================ informasi */

    /** @return array<string,bool> */
    public static function enabledMap(): array
    {
        $map = [];
        foreach (self::JOBS as $key => $job) {
            $map[$key] = Settings::bool((string) $job['toggle'], true);
        }
        return $map;
    }

    public static function isEnabled(string $job): bool
    {
        $config = self::JOBS[$job] ?? null;
        return $config !== null && Settings::bool((string) $config['toggle'], true);
    }

    /** Jadwal cron yang disarankan, dipakai pada halaman penjadwal. */
    public static function cronLines(): string
    {
        $base = rtrim((string) config('app_url', ''), '/');
        $host = $base !== '' ? $base : 'https://hris.contoh.co.id';
        return "# setiap 5 menit — antrean email\n*/5 * * * * php " . BASE_PATH . "/public/cron.php email_queue\n\n"
            . "# setiap hari 07:30 — pengingat kontrak & cuti\n30 7 * * * php " . BASE_PATH . "/public/cron.php contract_expiry\n"
            . "30 7 * * * php " . BASE_PATH . "/public/cron.php leave_expiry\n\n"
            . "# alternatif tanpa akses CLI (token disimpan di Pengaturan → Email & Penjadwal)\n"
            . "*/5 * * * * curl -s \"" . $host . "/cron.php?token=TOKEN_RAHASIA\" >/dev/null";
    }

    /* ============================================================== eksekusi */

    /**
     * Jalankan satu job dan catat hasilnya.
     *
     * @return array{job:string,label:string,status:string,processed:int,message:string,duration_ms:int,run_id:int}
     */
    public static function run(string $job, string $source = 'MANUAL'): array
    {
        if (!isset(self::JOBS[$job])) {
            return [
                'job'         => $job,
                'label'       => $job,
                'status'      => 'ERROR',
                'processed'   => 0,
                'message'     => 'Job tidak dikenal.',
                'duration_ms' => 0,
                'run_id'      => 0,
            ];
        }

        $label = (string) self::JOBS[$job]['label'];
        $runId = self::startRun($job, $source);
        $startedAt = microtime(true);
        $status = 'OK';
        $processed = 0;
        $message = '';

        try {
            $result = match ($job) {
                'email_queue'          => self::jobEmailQueue(),
                'contract_expiry'      => self::jobContractExpiry(),
                'leave_expiry'         => self::jobLeaveExpiry(),
                'announcement_publish' => self::jobAnnouncementPublish(),
                default                => ['processed' => 0, 'message' => 'Tidak ada implementasi.'],
            };
            $processed = (int) ($result['processed'] ?? 0);
            $message = (string) ($result['message'] ?? '');
        } catch (\Throwable $e) {
            $status = 'ERROR';
            $message = mb_substr($e->getMessage(), 0, 480);
            error_log('cron ' . $job . ' gagal: ' . $e->getMessage());
        }

        $duration = (int) round((microtime(true) - $startedAt) * 1000);
        self::finishRun($runId, $status, $processed, $message, $duration);

        try {
            Audit::log('cron.run', 'cron_runs', $runId ?: null, [
                'job'       => $job,
                'status'    => $status,
                'processed' => $processed,
                'source'    => $source,
            ]);
        } catch (\Throwable) {
            // audit bersifat opsional
        }

        return [
            'job'         => $job,
            'label'       => $label,
            'status'      => $status,
            'processed'   => $processed,
            'message'     => $message,
            'duration_ms' => $duration,
            'run_id'      => $runId,
        ];
    }

    /**
     * Jalankan semua job yang aktif.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function runAll(string $source = 'MANUAL'): array
    {
        $results = [];
        foreach (array_keys(self::JOBS) as $job) {
            if (!self::isEnabled($job)) {
                continue;
            }
            $results[] = self::run($job, $source);
        }
        return $results;
    }

    /**
     * Sapuan otomatis ringan: paling banyak satu rangkaian per menit.
     * Dipanggil dari bootstrap untuk request web biasa.
     */
    public static function autoRun(): void
    {
        if (PHP_SAPI === 'cli' || !Settings::bool('cron_auto', true)) {
            return;
        }
        if (!Database::tableExists('cron_runs')) {
            return;
        }

        $lock = self::lockPath();
        if (is_file($lock) && (time() - (int) @filemtime($lock)) < 60) {
            return;
        }
        if (!self::acquireLock($lock)) {
            return;
        }

        foreach (self::JOBS as $job => $config) {
            if (!self::isEnabled((string) $job)) {
                continue;
            }
            if (!self::due((string) $job, (int) $config['interval'])) {
                continue;
            }
            self::run((string) $job, 'AUTO');
        }
    }

    /** Apakah job sudah waktunya berjalan lagi? */
    public static function due(string $job, int $intervalMinutes): bool
    {
        $last = Database::value(
            'SELECT MAX(started_at) FROM cron_runs WHERE job = ?',
            [$job]
        );
        if (!$last) {
            return true;
        }
        return (time() - (int) strtotime((string) $last)) >= max(1, $intervalMinutes) * 60;
    }

    /* ============================================================== riwayat */

    /** @return array<int,array<string,mixed>> */
    public static function history(int $limit = 15): array
    {
        if (!Database::tableExists('cron_runs')) {
            return [];
        }
        return Database::all(
            'SELECT * FROM cron_runs ORDER BY id DESC LIMIT ' . max(1, $limit),
            []
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function historyFor(string $job, int $limit = 5): array
    {
        if (!Database::tableExists('cron_runs')) {
            return [];
        }
        return Database::all(
            'SELECT * FROM cron_runs WHERE job = ? ORDER BY id DESC LIMIT ' . max(1, $limit),
            [$job]
        );
    }

    public static function lastRun(string $job): ?array
    {
        $rows = self::historyFor($job, 1);
        return $rows[0] ?? null;
    }

    /** @return array{total:int,today:int,failed:int,last:?string,last_error:?string} */
    public static function stats(): array
    {
        if (!Database::tableExists('cron_runs')) {
            return ['total' => 0, 'today' => 0, 'failed' => 0, 'last' => null, 'last_error' => null];
        }

        return [
            'total'      => (int) Database::value('SELECT COUNT(*) FROM cron_runs', [], 0),
            'today'      => (int) Database::value('SELECT COUNT(*) FROM cron_runs WHERE started_at >= ?', [date('Y-m-d 00:00:00')], 0),
            'failed'     => (int) Database::value("SELECT COUNT(*) FROM cron_runs WHERE status = 'ERROR'", [], 0),
            'last'       => Database::value('SELECT MAX(started_at) FROM cron_runs'),
            'last_error' => Database::value("SELECT message FROM cron_runs WHERE status = 'ERROR' ORDER BY id DESC LIMIT 1"),
        ];
    }

    /** Hapus riwayat lebih tua dari N hari. */
    public static function prune(int $days = 90): int
    {
        if (!Database::tableExists('cron_runs')) {
            return 0;
        }
        return Database::delete(
            'cron_runs',
            'started_at < ?',
            [date('Y-m-d H:i:s', time() - max(1, $days) * 86400)]
        );
    }

    /* ================================================================= job */

    /** @return array{processed:int,message:string} */
    private static function jobEmailQueue(): array
    {
        $limit = max(1, (int) Settings::get('cron_email_batch', 25));
        $result = Mailer::processQueue($limit);

        if (!Mailer::enabled()) {
            return [
                'processed' => 0,
                'message'   => 'Email dinonaktifkan di Pengaturan → Email (' . $result['skipped'] . ' menunggu).',
            ];
        }

        return [
            'processed' => $result['sent'],
            'message'   => sprintf(
                'Diproses %d email: %d terkirim, %d gagal permanen.',
                $result['processed'],
                $result['sent'],
                $result['failed']
            ),
        ];
    }

    /** @return array{processed:int,message:string} */
    private static function jobContractExpiry(): array
    {
        $lead  = max(1, min(180, (int) Settings::get('cron_contract_lead_days', 30)));
        $today = date('Y-m-d');
        $until = date('Y-m-d', strtotime($today . ' +' . $lead . ' days'));

        $rows = Database::all(
            "SELECT e.id, e.employee_code, e.full_name, e.email, e.employment_status,
                    e.contract_start, e.contract_end,
                    d.name AS department, p.name AS position
               FROM employees e
               LEFT JOIN departments d ON d.id = e.department_id
               LEFT JOIN positions p ON p.id = e.position_id
              WHERE e.flag = 'ACTIVE'
                AND e.contract_end IS NOT NULL
                AND e.contract_end >= ?
                AND e.contract_end <= ?
              ORDER BY e.contract_end ASC",
            [$today, $until]
        );

        $sent = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $days = (int) ceil((strtotime((string) $row['contract_end']) - strtotime($today)) / 86400);
            $subject = 'Kontrak kerja berakhir ' . tanggal((string) $row['contract_end'], 'd M Y');
            $employeeId = (int) $row['id'];

            if (self::alreadyEmailed($employeeId, 'contract_expiry', $subject, 60)) {
                $skipped++;
                continue;
            }

            $email = trim((string) $row['email']);
            if ($email !== '') {
                Mailer::sendTemplate(
                    ['email' => $email, 'name' => (string) $row['full_name'], 'employee_id' => $employeeId],
                    'contract_expiry',
                    $subject,
                    [
                        'heading'   => 'Masa kontrak kerja akan berakhir',
                        'greeting'  => 'Halo ' . self::firstName((string) $row['full_name']) . ',',
                        'message'   => 'Kontrak kerja Anda tercatat berakhir pada '
                            . tanggal((string) $row['contract_end'], 'd M Y')
                            . ' (' . $days . ' hari lagi). Silakan koordinasikan perpanjangan dengan HR.',
                        'lines'     => array_values(array_filter([
                            'Nama: ' . $row['full_name'],
                            'NIK: ' . $row['employee_code'],
                            !empty($row['position']) ? 'Jabatan: ' . $row['position'] : null,
                            !empty($row['department']) ? 'Departemen: ' . $row['department'] : null,
                            'Status kerja: ' . $row['employment_status'],
                            'Kontrak mulai: ' . tanggal((string) $row['contract_start'], 'd M Y'),
                            'Kontrak berakhir: ' . tanggal((string) $row['contract_end'], 'd M Y'),
                        ])),
                        'badge'     => 'H-' . $days,
                        'actionUrl' => url('me/profile'),
                        'actionLabel' => 'Buka Profil Saya',
                        'note'      => 'Abaikan pesan ini bila perpanjangan kontrak sudah diproses HR.',
                    ]
                );
            }

            self::notifyEmployee(
                $employeeId,
                'Kontrak kerja berakhir ' . tanggal((string) $row['contract_end'], 'd M Y'),
                'Kontrak Anda berakhir dalam ' . $days . ' hari. Hubungi HR untuk proses perpanjangan.',
                '/me/profile',
                'contract'
            );
            $sent++;
        }

        return [
            'processed' => $sent,
            'message'   => sprintf(
                '%d karyawan mendekati akhir kontrak (H-%d). %d pengingat dikirim, %d sudah pernah dikirim.',
                count($rows),
                $lead,
                $sent,
                $skipped
            ),
        ];
    }

    /** @return array{processed:int,message:string} */
    private static function jobLeaveExpiry(): array
    {
        $year  = (int) date('Y');
        $today = date('Y-m-d');
        $lead  = max(1, min(180, (int) Settings::get('cron_leave_lead_days', 70)));
        $endOfYear = $year . '-12-31';
        $daysLeft = (int) ceil((strtotime($endOfYear) - strtotime($today)) / 86400);

        if ($daysLeft > $lead) {
            return [
                'processed' => 0,
                'message'   => 'Belum masuk jendela pengingat: ' . $daysLeft . ' hari menuju 31 Des ' . $year . ' (jendela H-' . $lead . ').',
            ];
        }

        $rows = Database::all(
            "SELECT b.employee_id, b.quota, b.used, b.carry,
                    e.full_name, e.email, e.employee_code,
                    lt.name AS leave_type, lt.carry_over
               FROM leave_balances b
               JOIN employees e ON e.id = b.employee_id
               JOIN leave_types lt ON lt.id = b.leave_type_id
              WHERE b.period_year = ?
                AND e.flag = 'ACTIVE'
                AND (b.quota + b.carry - b.used) > 0
              ORDER BY e.full_name ASC, lt.name ASC",
            [$year]
        );

        $sent = 0;
        $skipped = 0;
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['employee_id']][] = $row;
        }

        foreach ($grouped as $employeeId => $balances) {
            $first = $balances[0];
            $subject = 'Sisa cuti ' . $year . ' belum terpakai';
            if (self::alreadyEmailed((int) $employeeId, 'leave_expiry', $subject, 30)) {
                $skipped++;
                continue;
            }

            $lines = [];
            $totalRemaining = 0.0;
            $carryAllowed = false;
            foreach ($balances as $balance) {
                $remaining = (float) $balance['quota'] + (float) $balance['carry'] - (float) $balance['used'];
                $totalRemaining += $remaining;
                $lines[] = sprintf('%s: sisa %s hari (jatah %s + carry %s − terpakai %s)',
                    $balance['leave_type'],
                    number_id($remaining, 1),
                    number_id((float) $balance['quota'], 1),
                    number_id((float) $balance['carry'], 1),
                    number_id((float) $balance['used'], 1)
                );
                $carryAllowed = $carryAllowed || (int) $balance['carry_over'] === 1;
            }

            $email = trim((string) $first['email']);
            if ($email !== '') {
                Mailer::sendTemplate(
                    ['email' => $email, 'name' => (string) $first['full_name'], 'employee_id' => (int) $employeeId],
                    'leave_expiry',
                    $subject,
                    [
                        'heading'   => 'Sisa cuti ' . $year . ' akan berakhir',
                        'greeting'  => 'Halo ' . self::firstName((string) $first['full_name']) . ',',
                        'message'   => 'Tahun ' . $year . ' tersisa ' . $daysLeft . ' hari. Anda masih memiliki total '
                            . number_id($totalRemaining, 1) . ' hari cuti yang belum terpakai.',
                        'lines'     => $lines,
                        'badge'     => 'H-' . $daysLeft,
                        'actionUrl' => url('me/leave'),
                        'actionLabel' => 'Ajukan Cuti',
                        'note'      => $carryAllowed
                            ? 'Sebagian jenis cuti dapat dibawa ke tahun berikutnya sesuai kebijakan perusahaan. Sisa cuti di luar ketentuan hangus pada 31 Desember ' . $year . '.'
                            : 'Sisa cuti yang tidak diajukan sebelum 31 Desember ' . $year . ' hangus dan tidak dapat diuangkan.',
                    ]
                );
            }

            self::notifyEmployee(
                (int) $employeeId,
                'Sisa cuti ' . $year . ' belum terpakai',
                number_id($totalRemaining, 1) . ' hari cuti tersisa sebelum 31 Desember ' . $year . '.',
                '/me/leave',
                'leave'
            );
            $sent++;
        }

        return [
            'processed' => $sent,
            'message'   => sprintf(
                'H-%d menuju akhir tahun: %d karyawan punya sisa cuti, %d pengingat dikirim, %d sudah pernah dikirim.',
                $daysLeft,
                count($grouped),
                $sent,
                $skipped
            ),
        ];
    }

    /**
     * Pengumuman terjadwal disimpan dengan status PUBLISHED + publish_at di masa
     * depan (tidak tampil di feed sampai waktunya). Job ini mengirim notifikasi
     * lonceng — dan opsional email — begitu jadwal tayang tiba. Idempoten: baris
     * notifikasi dengan tautan pengumuman dipakai sebagai penanda "sudah dikirim".
     *
     * @return array{processed:int,message:string}
     */
    private static function jobAnnouncementPublish(): array
    {
        $now = date('Y-m-d H:i:s');
        $rows = Database::all(
            "SELECT * FROM announcements
              WHERE status = 'PUBLISHED'
                AND notify = 1
                AND publish_at IS NOT NULL
                AND publish_at <= ?
                AND publish_at >= ?
                AND (expires_at IS NULL OR expires_at >= ?)
              ORDER BY publish_at ASC",
            [$now, date('Y-m-d H:i:s', time() - 3 * 86400), $now]
        );

        if (!$rows) {
            return ['processed' => 0, 'message' => 'Tidak ada pengumuman terjadwal yang jatuh tempo.'];
        }

        $model = new \App\Models\AnnouncementModel();
        $notified = 0;
        $emails = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $link = '/announcements/' . (int) $row['id'];
            $already = (int) Database::value(
                'SELECT COUNT(*) FROM notifications WHERE link = ? AND title LIKE ?',
                [$link, 'Pengumuman:%'],
                0
            );
            if ($already > 0) {
                $skipped++;
                continue;
            }

            try {
                $model->notifyAudience($row);
            } catch (\Throwable) {
                // best-effort
            }
            $notified++;

            if (Settings::bool('cron_announcement_email', false)) {
                $emails += self::emailAnnouncement($model, $row);
            }
        }

        return [
            'processed' => $notified,
            'message'   => sprintf(
                '%d pengumuman jatuh tempo: %d dinotifikasi%s, %d sudah pernah dinotifikasi.',
                count($rows),
                $notified,
                $emails > 0 ? ', ' . $emails . ' email dikirim' : '',
                $skipped
            ),
        ];
    }

    /** Kirim email pengumuman ke audiens (opsional). */
    private static function emailAnnouncement(\App\Models\AnnouncementModel $model, array $announcement): int
    {
        $subject = 'Pengumuman: ' . mb_substr((string) $announcement['title'], 0, 120);
        $sent = 0;

        foreach ($model->audienceEmployees($announcement) as $employee) {
            $email = trim((string) ($employee['email'] ?? ''));
            if ($email === '' || self::alreadyEmailed((int) $employee['id'], 'announcement', $subject, 30)) {
                continue;
            }
            Mailer::sendTemplate(
                ['email' => $email, 'name' => (string) $employee['full_name'], 'employee_id' => (int) $employee['id']],
                'announcement',
                $subject,
                [
                    'heading'   => (string) $announcement['title'],
                    'greeting'  => 'Halo ' . self::firstName((string) $employee['full_name']) . ',',
                    'message'   => mb_substr(trim((string) $announcement['body']), 0, 500),
                    'badge'     => (string) ($announcement['category'] ?? 'INFO'),
                    'actionUrl' => url('announcements/' . (int) $announcement['id']),
                    'actionLabel' => 'Baca Pengumuman',
                    'note'      => 'Pengumuman ini juga tersedia di menu Pengumuman pada HRIS.',
                ]
            );
            $sent++;
        }

        return $sent;
    }

    /* =============================================================== helper */

    private static function startRun(string $job, string $source): int
    {
        if (!Database::tableExists('cron_runs')) {
            return 0;
        }
        try {
            return Database::insert('cron_runs', [
                'job'        => $job,
                'status'     => 'RUNNING',
                'source'     => substr($source, 0, 20),
                'processed'  => 0,
                'started_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function finishRun(int $runId, string $status, int $processed, string $message, int $duration): void
    {
        if ($runId <= 0) {
            return;
        }
        try {
            Database::update('cron_runs', [
                'status'      => $status,
                'processed'   => $processed,
                'message'     => mb_substr($message, 0, 480),
                'duration_ms' => $duration,
                'finished_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$runId]);
        } catch (\Throwable) {
            // abaikan
        }
    }

    private static function alreadyEmailed(int $employeeId, string $template, string $subject, int $withinDays): bool
    {
        if (!Database::tableExists('email_queue')) {
            return false;
        }
        return (int) Database::value(
            "SELECT COUNT(*) FROM email_queue
              WHERE employee_id = ? AND template = ? AND subject = ?
                AND status <> 'CANCELLED'
                AND created_at >= ?",
            [
                $employeeId,
                $template,
                $subject,
                date('Y-m-d H:i:s', time() - max(1, $withinDays) * 86400),
            ],
            0
        ) > 0;
    }

    private static function notifyEmployee(int $employeeId, string $title, string $message, string $link, string $type): void
    {
        try {
            if (!Database::tableExists('notifications')) {
                return;
            }
            Database::insert('notifications', [
                'employee_id' => $employeeId,
                'title'       => mb_substr($title, 0, 150),
                'message'     => mb_substr($message, 0, 200),
                'link'        => $link,
                'type'        => $type,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // best-effort
        }
    }

    private static function firstName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        return $parts[0] ?? $fullName;
    }

    private static function lockPath(): string
    {
        $dir = STORAGE_PATH . '/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/cron.lock';
    }

    /** Kunci sederhana agar dua request tidak menjalankan job bersamaan. */
    private static function acquireLock(string $path): bool
    {
        $handle = @fopen($path, 'c');
        if ($handle === false) {
            return false;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }
        ftruncate($handle, 0);
        fwrite($handle, (string) time());
        fflush($handle);
        // Kunci dilepas otomatis saat request selesai (handle tetap terbuka).
        $GLOBALS['__cron_lock_handle'] = $handle;
        return true;
    }
}
