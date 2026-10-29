<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Pengirim email native (tanpa library eksternal).
 *
 * Tiga driver, dipilih lewat Pengaturan → Email:
 *  - smtp : koneksi socket langsung ke server SMTP (SSL atau STARTTLS, AUTH LOGIN/PLAIN)
 *  - mail : fungsi mail() bawaan PHP (cocok untuk shared hosting dengan sendmail)
 *  - log  : tidak mengirim apa pun, hanya menulis ke storage/logs/mail.log (mode uji)
 *
 * Semua email masuk ke tabel `email_queue` lebih dulu, lalu diproses oleh
 * penjadwal (job `email_queue`) atau langsung saat dikirim (default).
 */
final class Mailer
{
    public const STATUSES = [
        'PENDING'   => 'Menunggu',
        'SENT'      => 'Terkirim',
        'FAILED'    => 'Gagal',
        'CANCELLED' => 'Dibatalkan',
    ];

    public const DRIVERS = [
        'smtp' => 'SMTP server',
        'mail' => 'mail() PHP (sendmail)',
        'log'  => 'Log berkas (mode uji)',
    ];

    /** Jumlah percobaan maksimum sebelum ditandai gagal permanen. */
    public const MAX_ATTEMPTS = 3;

    /* ============================================================ pengaturan */

    public static function enabled(): bool
    {
        return Settings::bool('mail_enabled', true);
    }

    public static function driver(): string
    {
        $driver = strtolower((string) Settings::get('mail_driver', 'log'));
        return isset(self::DRIVERS[$driver]) ? $driver : 'log';
    }

    /** Apakah email benar-benar dikirim keluar (bukan hanya dicatat)? */
    public static function delivers(): bool
    {
        return self::enabled() && in_array(self::driver(), ['smtp', 'mail'], true);
    }

    /** @return array<string,mixed> */
    public static function config(): array
    {
        return [
            'driver'     => self::driver(),
            'host'       => (string) Settings::get('mail_host', ''),
            'port'       => (int) Settings::get('mail_port', 587),
            'encryption' => strtolower((string) Settings::get('mail_encryption', 'tls')),
            'username'   => (string) Settings::get('mail_username', ''),
            'password'   => (string) Settings::get('mail_password', ''),
            'from_email' => (string) (Settings::get('mail_from_email') ?: (Settings::get('company_email') ?: 'no-reply@asiaraya.co.id')),
            'from_name'  => (string) (Settings::get('mail_from_name') ?: (Settings::get('company_name') ?: 'HRIS')),
            'reply_to'   => (string) Settings::get('mail_reply_to', ''),
            'timeout'    => max(5, (int) Settings::get('mail_timeout', 15)),
        ];
    }

    /* ================================================================ antrean */

    /**
     * Masukkan email ke antrean (dan opsional langsung kirim).
     *
     * @param array{email:string,name?:string,employee_id?:int} $to
     * @return int id antrean
     */
    public static function queue(
        array $to,
        string $subject,
        string $html,
        string $text = '',
        string $template = 'generic',
        bool $sendNow = true
    ): int {
        $email = trim((string) ($to['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 0;
        }
        if (!Database::tableExists('email_queue')) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $id = Database::insert('email_queue', [
            'recipient_email' => $email,
            'recipient_name'  => (string) ($to['name'] ?? ''),
            'employee_id'     => isset($to['employee_id']) ? (int) $to['employee_id'] : null,
            'template'        => substr($template, 0, 40),
            'subject'         => mb_substr($subject, 0, 200),
            'body_html'       => $html,
            'body_text'       => $text,
            'status'          => 'PENDING',
            'attempts'        => 0,
            'next_attempt_at' => $now,
            'created_at'      => $now,
        ]);

        if ($sendNow) {
            self::deliver($id);
        }

        return $id;
    }

    /**
     * Kirim satu baris antrean. Mengembalikan status akhir.
     *
     * @return array{status:string,driver:?string,error:?string}
     */
    public static function deliver(int $queueId): array
    {
        $row = Database::one('SELECT * FROM email_queue WHERE id = ?', [$queueId]);
        if (!$row) {
            return ['status' => 'MISSING', 'driver' => null, 'error' => 'Antrean tidak ditemukan.'];
        }
        if ((string) $row['status'] === 'SENT') {
            return ['status' => 'SENT', 'driver' => (string) $row['driver'], 'error' => null];
        }

        // Email dimatikan: jangan tandai terkirim, biarkan tetap menunggu.
        if (!self::enabled()) {
            return ['status' => 'SKIPPED', 'driver' => null, 'error' => 'Email otomatis sedang dinonaktifkan.'];
        }

        $attempts = (int) $row['attempts'] + 1;
        $html = (string) ($row['body_html'] ?? '');
        $text = (string) ($row['body_text'] ?? '');
        if ($text === '') {
            $text = trim(strip_tags($html));
        }

        $result = self::sendRaw(
            (string) $row['recipient_email'],
            (string) ($row['recipient_name'] ?? ''),
            (string) $row['subject'],
            $html,
            $text
        );

        if ($result['ok']) {
            Database::update('email_queue', [
                'status'   => 'SENT',
                'attempts' => $attempts,
                'driver'   => $result['driver'],
                'error'    => null,
                'sent_at'  => date('Y-m-d H:i:s'),
            ], 'id = ?', [$queueId]);

            return ['status' => 'SENT', 'driver' => $result['driver'], 'error' => null];
        }

        $final = $attempts >= self::MAX_ATTEMPTS;
        Database::update('email_queue', [
            'status'          => $final ? 'FAILED' : 'PENDING',
            'attempts'        => $attempts,
            'driver'          => $result['driver'],
            'error'           => mb_substr((string) $result['error'], 0, 480),
            'next_attempt_at' => date('Y-m-d H:i:s', time() + (60 * 5 * $attempts)),
        ], 'id = ?', [$queueId]);

        return ['status' => $final ? 'FAILED' : 'PENDING', 'driver' => $result['driver'], 'error' => $result['error']];
    }

    /**
     * Proses antrean yang jatuh tempo.
     *
     * @return array{processed:int,sent:int,failed:int,skipped:int}
     */
    public static function processQueue(int $limit = 25): array
    {
        $summary = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];

        if (!Database::tableExists('email_queue')) {
            return $summary;
        }

        if (!self::enabled()) {
            $summary['skipped'] = (int) Database::value(
                "SELECT COUNT(*) FROM email_queue WHERE status = 'PENDING'",
                [],
                0
            );
            return $summary;
        }

        $rows = Database::all(
            "SELECT id FROM email_queue
              WHERE status = 'PENDING'
                AND (next_attempt_at IS NULL OR next_attempt_at <= ?)
              ORDER BY id ASC LIMIT " . max(1, $limit),
            [date('Y-m-d H:i:s')]
        );

        foreach ($rows as $row) {
            $summary['processed']++;
            $result = self::deliver((int) $row['id']);
            if ($result['status'] === 'SENT') {
                $summary['sent']++;
            } elseif ($result['status'] === 'FAILED') {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    /** Batalkan antrean yang belum terkirim (mis. penerima sudah tidak aktif). */
    public static function cancelPendingForEmployee(int $employeeId): int
    {
        if (!Database::tableExists('email_queue')) {
            return 0;
        }
        return Database::update('email_queue', [
            'status' => 'CANCELLED',
            'error'  => 'Penerima tidak aktif / dihapus.',
        ], "employee_id = ? AND status = 'PENDING'", [$employeeId]);
    }

    /* ======================================================== render template */

    /**
     * Render template email (dibungkus layout).
     *
     * @param array<string,mixed> $data
     */
    public static function render(string $template, array $data = []): string
    {
        // Email dibuka di luar aplikasi: tautan harus absolut.
        if (!empty($data['actionUrl'])) {
            $data['actionUrl'] = self::absolute((string) $data['actionUrl']);
        }

        $data['company'] = [
            'name'    => (string) setting('company_name', 'PT Asia Raya Group'),
            'legal'   => (string) setting('company_legal_name', ''),
            'address' => (string) setting('company_address', ''),
            'phone'   => (string) setting('company_phone', ''),
            'email'   => (string) setting('company_email', ''),
            'website' => (string) setting('company_website', ''),
        ];
        $data['appUrl'] = self::absolute('/');
        $data['year'] = date('Y');

        $body = View::partial('mail/' . $template, $data);

        return View::partial('mail/layout', $data + ['content' => $body]);
    }

    /**
     * Ubah path aplikasi menjadi URL absolut (dipakai tombol di dalam email).
     */
    public static function absolute(string $path = ''): string
    {
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }

        $relative = url($path);

        $configured = rtrim((string) config('app_url', ''), '/');
        if ($configured !== '') {
            $basePath = base_path();
            if ($basePath !== '' && str_starts_with($relative, $basePath)) {
                $relative = substr($relative, strlen($basePath));
            }
            return $configured . '/' . ltrim($relative, '/');
        }

        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));

        return ($https ? 'https' : 'http') . '://' . $host . '/' . ltrim($relative, '/');
    }

    /**
     * Kirim email berbasis template. Bila template tidak ada, memakai 'generic'.
     *
     * @param array{email:string,name?:string,employee_id?:int} $to
     * @param array<string,mixed> $data
     */
    public static function sendTemplate(
        array $to,
        string $template,
        string $subject,
        array $data = [],
        bool $sendNow = true
    ): int {
        $email = trim((string) ($to['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 0;
        }
        if (!View::exists('mail/' . $template)) {
            $template = 'generic';
        }

        // Tautan aksi harus absolut di kedua bagian (HTML & teks biasa).
        if (!empty($data['actionUrl'])) {
            $data['actionUrl'] = self::absolute((string) $data['actionUrl']);
        }

        $html = self::render($template, $data);
        $text = self::plainText($data);

        return self::queue($to, $subject, $html, $text, $template, $sendNow);
    }

    /** Versi teks sederhana untuk fallback plain-text. */
    private static function plainText(array $data): string
    {
        $lines = [];
        foreach (['heading', 'greeting', 'message', 'note'] as $key) {
            if (!empty($data[$key])) {
                $lines[] = trim(strip_tags((string) $data[$key]));
            }
        }
        if (!empty($data['lines']) && is_array($data['lines'])) {
            foreach ($data['lines'] as $line) {
                $lines[] = '- ' . trim(strip_tags((string) $line));
            }
        }
        if (!empty($data['actionUrl'])) {
            $lines[] = '';
            $lines[] = (string) ($data['actionLabel'] ?? 'Buka tautan') . ': ' . $data['actionUrl'];
        }
        return implode("\n", $lines);
    }

    /* ============================================================ transport */

    /**
     * Kirim email sesuai driver aktif.
     *
     * @return array{ok:bool,driver:string,error:?string}
     */
    public static function sendRaw(string $to, string $toName, string $subject, string $html, string $text = ''): array
    {
        $driver = self::driver();

        if (!self::enabled()) {
            self::logMail($to, $subject, 'DIMATIKAN', $html);
            return ['ok' => false, 'driver' => 'disabled', 'error' => 'Email otomatis sedang dinonaktifkan.'];
        }

        if ($driver === 'log') {
            self::logMail($to, $subject, 'LOG', $html);
            return ['ok' => true, 'driver' => 'log', 'error' => null];
        }

        if ($driver === 'mail') {
            $sent = self::sendWithMailFunction($to, $toName, $subject, $html, $text);
            self::logMail($to, $subject, $sent ? 'MAIL()' : 'MAIL() GAGAL', $html);
            return [
                'ok'     => $sent,
                'driver' => 'mail',
                'error'  => $sent ? null : 'Fungsi mail() menolak pengiriman (periksa konfigurasi sendmail).',
            ];
        }

        $result = self::smtpSend($to, $toName, $subject, $html, $text);
        self::logMail($to, $subject, $result['ok'] ? 'SMTP' : 'SMTP GAGAL: ' . (string) $result['error'], $html);

        return ['ok' => $result['ok'], 'driver' => 'smtp', 'error' => $result['error']];
    }

    /**
     * Kirim email percobaan tanpa menyentuh antrean.
     *
     * @return array{ok:bool,driver:string,error:?string}
     */
    public static function sendTest(string $to): array
    {
        $html = self::render('test', [
            'heading'  => 'Email uji berhasil',
            'greeting' => 'Halo,',
            'message'  => 'Konfigurasi email HRIS berjalan. Pesan ini dikirim melalui driver '
                . self::DRIVERS[self::driver()] . '.',
            'lines'    => [
                'Server: ' . (self::driver() === 'smtp' ? (self::config()['host'] . ':' . self::config()['port']) : 'lokal'),
                'Waktu: ' . date('d M Y H:i:s'),
            ],
            'note'     => 'Tidak ada tindakan lanjutan yang diperlukan.',
        ]);

        return self::sendRaw($to, '', 'Uji konfigurasi email HRIS', $html, strip_tags($html));
    }

    /* ------------------------------------------------------------- SMTP */

    /**
     * @return array{ok:bool,error:?string}
     */
    private static function smtpSend(string $to, string $toName, string $subject, string $html, string $text): array
    {
        $cfg = self::config();

        if ($cfg['host'] === '') {
            return ['ok' => false, 'error' => 'Host SMTP belum diisi pada Pengaturan → Email.'];
        }

        $secure = $cfg['encryption'] === 'ssl' ? 'ssl://' : '';
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $secure . $cfg['host'] . ':' . $cfg['port'],
            $errno,
            $errstr,
            $cfg['timeout'],
            STREAM_CLIENT_CONNECT
        );

        if (!is_resource($socket)) {
            return ['ok' => false, 'error' => 'Tidak dapat terhubung ke ' . $cfg['host'] . ':' . $cfg['port'] . ' — ' . ($errstr ?: 'koneksi gagal')];
        }

        stream_set_timeout($socket, $cfg['timeout']);

        try {
            if (self::smtpExpect($socket, [220]) === null) {
                return ['ok' => false, 'error' => 'Server SMTP tidak memberi salam 220.'];
            }

            $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
            $ehlo = self::smtpCommand($socket, 'EHLO ' . $host, [250]);
            if ($ehlo === null) {
                self::smtpCommand($socket, 'HELO ' . $host, [250]);
            }

            if ($cfg['encryption'] === 'tls') {
                if (self::smtpCommand($socket, 'STARTTLS', [220]) === null) {
                    return ['ok' => false, 'error' => 'Server menolak STARTTLS. Coba enkripsi SSL atau none.'];
                }
                $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($crypto !== true) {
                    return ['ok' => false, 'error' => 'Negosiasi TLS gagal pada port ' . $cfg['port'] . '.'];
                }
                self::smtpCommand($socket, 'EHLO ' . $host, [250]);
            }

            if ($cfg['username'] !== '') {
                $auth = self::smtpCommand($socket, 'AUTH LOGIN', [334]);
                if ($auth === null) {
                    $auth = self::smtpCommand($socket, 'AUTH PLAIN ' . base64_encode("\0" . $cfg['username'] . "\0" . $cfg['password']), [235]);
                    if ($auth === null) {
                        return ['ok' => false, 'error' => 'Autentikasi SMTP gagal. Periksa username/password.'];
                    }
                } else {
                    if (self::smtpCommand($socket, base64_encode($cfg['username']), [334]) === null) {
                        return ['ok' => false, 'error' => 'Autentikasi SMTP ditolak pada tahap username.'];
                    }
                    if (self::smtpCommand($socket, base64_encode($cfg['password']), [235]) === null) {
                        return ['ok' => false, 'error' => 'Password SMTP ditolak.'];
                    }
                }
            }

            if (self::smtpCommand($socket, 'MAIL FROM:<' . $cfg['from_email'] . '>', [250]) === null) {
                return ['ok' => false, 'error' => 'Alamat pengirim ' . $cfg['from_email'] . ' ditolak server.'];
            }
            if (self::smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]) === null) {
                return ['ok' => false, 'error' => 'Penerima ' . $to . ' ditolak server.'];
            }
            if (self::smtpCommand($socket, 'DATA', [354]) === null) {
                return ['ok' => false, 'error' => 'Server tidak siap menerima isi pesan (DATA).'];
            }

            $data = self::buildMessage($to, $toName, $subject, $html, $text);
            // Dot-stuffing: baris yang dimulai titik harus di-escape.
            $data = preg_replace('/^\./m', '..', $data) ?? $data;

            fwrite($socket, $data . "\r\n.\r\n");
            if (self::smtpExpect($socket, [250]) === null) {
                return ['ok' => false, 'error' => 'Server menolak isi pesan setelah DATA.'];
            }

            self::smtpCommand($socket, 'QUIT', [221]);

            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'SMTP error: ' . $e->getMessage()];
        } finally {
            if (is_resource($socket)) {
                @fclose($socket);
            }
        }
    }

    /** Kirim perintah dan cek kode respons yang diterima. */
    private static function smtpCommand($socket, string $command, array $expect): ?string
    {
        fwrite($socket, $command . "\r\n");
        return self::smtpExpect($socket, $expect);
    }

    /** Baca respons multi-baris dan kembalikan teks bila kodenya sesuai. */
    private static function smtpExpect($socket, array $expect): ?string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        if ($response === '') {
            return null;
        }
        $code = (int) substr($response, 0, 3);
        return in_array($code, $expect, true) ? $response : null;
    }

    /** Susun pesan MIME (multipart teks + HTML). */
    private static function buildMessage(string $to, string $toName, string $subject, string $html, string $text): string
    {
        $cfg = self::config();
        $boundary = 'HRIS-' . bin2hex(random_bytes(12));
        $from = self::encodeHeader($cfg['from_name']) . ' <' . $cfg['from_email'] . '>';
        $recipient = $toName !== '' ? self::encodeHeader($toName) . ' <' . $to . '>' : $to;

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $from,
            'To: ' . $recipient,
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(10)) . '@hris.local>',
            'MIME-Version: 1.0',
        ];

        if ($cfg['reply_to'] !== '') {
            $headers[] = 'Reply-To: ' . $cfg['reply_to'];
        }
        $headers[] = 'X-Mailer: HRIS Asia Raya';

        $plain = $text !== '' ? $text : strip_tags($html);

        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($plain) . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($html) . "\r\n"
            . '--' . $boundary . "--";

        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /** Encode subjek/nama panjang ke MIME encoded-word UTF-8. */
    private static function encodeHeader(string $value): string
    {
        $value = trim(str_replace(["\r", "\n"], ' ', $value));
        if ($value === '') {
            return '';
        }
        return preg_match('/[\x80-\xFF]/', $value)
            ? '=?UTF-8?B?' . base64_encode($value) . '?='
            : $value;
    }

    /* --------------------------------------------------------- mail() */

    private static function sendWithMailFunction(string $to, string $toName, string $subject, string $html, string $text): bool
    {
        $cfg = self::config();
        $boundary = 'HRIS-' . bin2hex(random_bytes(12));
        $headers = [
            'MIME-Version: 1.0',
            'From: ' . self::encodeHeader($cfg['from_name']) . ' <' . $cfg['from_email'] . '>',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: HRIS Asia Raya',
        ];
        if ($cfg['reply_to'] !== '') {
            $headers[] = 'Reply-To: ' . $cfg['reply_to'];
        }

        $body = '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($text !== '' ? $text : strip_tags($html))
            . "\r\n--" . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($html)
            . "\r\n--" . $boundary . "--";

        $recipient = $toName !== '' ? self::encodeHeader($toName) . ' <' . $to . '>' : $to;

        return @mail($recipient, self::encodeHeader($subject), $body, implode("\r\n", $headers));
    }

    /* ---------------------------------------------------------- log */

    /** Catat setiap upaya pengiriman ke storage/logs/mail.log. */
    private static function logMail(string $to, string $subject, string $status, string $html): void
    {
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf("[%s] %-26s %s → %s\n", date('Y-m-d H:i:s'), $status, $to, $subject);
        @file_put_contents($dir . '/mail.log', $line, FILE_APPEND);

        if (self::driver() === 'log') {
            @file_put_contents($dir . '/mail-outbox.log', "\n=== " . trim($line) . "\n" . $html . "\n", FILE_APPEND);
        }
    }

    /* ======================================================== helper karyawan */

    /**
     * Kirim email ke seorang karyawan (dilewati bila nonaktif/tanpa email).
     *
     * @param array<string,mixed> $employee baris employees (minimal id, email, full_name)
     * @param array<string,mixed> $data
     */
    public static function toEmployee(
        array $employee,
        string $template,
        string $subject,
        array $data = [],
        bool $sendNow = true
    ): int {
        if (($employee['email'] ?? '') === '' || !filter_var((string) $employee['email'], FILTER_VALIDATE_EMAIL)) {
            return 0;
        }
        if (isset($employee['flag']) && (string) $employee['flag'] !== 'ACTIVE') {
            return 0;
        }

        return self::sendTemplate(
            [
                'email'       => (string) $employee['email'],
                'name'        => (string) ($employee['full_name'] ?? ''),
                'employee_id' => isset($employee['id']) ? (int) $employee['id'] : null,
            ],
            $template,
            $subject,
            $data,
            $sendNow
        );
    }

    /** Statistik antrean untuk halaman pengaturan/penjadwal. */
    public static function stats(): array
    {
        if (!Database::tableExists('email_queue')) {
            return ['pending' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0, 'today' => 0, 'last' => null];
        }

        $row = Database::one(
            "SELECT
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'SENT' THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled
              FROM email_queue"
        ) ?? [];

        return [
            'pending'   => (int) ($row['pending'] ?? 0),
            'sent'      => (int) ($row['sent'] ?? 0),
            'failed'    => (int) ($row['failed'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
            'today'     => (int) Database::value(
                'SELECT COUNT(*) FROM email_queue WHERE sent_at >= ?',
                [date('Y-m-d 00:00:00')],
                0
            ),
            'last'      => Database::value('SELECT MAX(sent_at) FROM email_queue WHERE status = ?', ['SENT']),
        ];
    }
}
