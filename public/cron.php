<?php
/**
 * Penjadwal baris perintah — PT Asia Raya Group HRIS.
 *
 * Pemakaian dari crontab server:
 *   *\/5 * * * * php /path/ke/hris/public/cron.php email_queue
 *   30 7 * * *  php /path/ke/hris/public/cron.php contract_expiry
 *  30 7 * * *  php /path/ke/hris/public/cron.php leave_expiry
 *   0 * * * *  php /path/ke/hris/public/cron.php            (semua job aktif)
 *
 * Pemakaian via HTTP (bila tidak ada akses CLI):
 *   curl "https://hris.perusahaan.co.id/cron.php?token=TOKEN"
 *
 * Token dibuat di halaman Penjadwal & Cron dan boleh juga dikirim lewat
 * header "X-Cron-Token". Tanpa token yang cocok, akses HTTP ditolak.
 */
declare(strict_types=1);

// Cron tidak punya sesi pengguna; beri tahu Session::start() agar tidak
// mencoba mengirim cookie.
$GLOBALS['HRIS_EXTERNAL_SESSION'] = true;

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Scheduler;
use App\Core\Settings;

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    $expected = (string) Settings::get('cron_secret', '');
    $given = (string) ($_GET['token'] ?? ($_SERVER['HTTP_X_CRON_TOKEN'] ?? ''));

    if ($expected === '' || $given === '' || !hash_equals($expected, $given)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Akses ditolak. Buat token cron di menu Penjadwal & Cron.\n";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
}

$arguments = $isCli ? array_slice($argv ?? [], 1) : [];
$job = trim((string) ($arguments[0] ?? ($_GET['job'] ?? '')));

if ($job !== '' && !isset(Scheduler::JOBS[$job])) {
    $message = 'Job tidak dikenal: ' . $job . "\n"
        . 'Pilihan: ' . implode(', ', array_keys(Scheduler::JOBS)) . "\n";
    if ($isCli) {
        fwrite(STDERR, $message);
    } else {
        http_response_code(404);
        echo $message;
    }
    exit(2);
}

$results = $job !== ''
    ? [Scheduler::run($job, 'CRON')]
    : Scheduler::runAll('CRON');

$lines = [];
foreach ($results as $row) {
    $lines[] = sprintf(
        '[%s] %s — %d data diproses (%d ms): %s',
        $row['status'],
        $row['job'],
        (int) $row['processed'],
        (int) $row['duration_ms'],
        $row['message']
    );
}

if (!$lines) {
    $lines[] = 'Tidak ada job aktif (semua dinonaktifkan di Pengaturan → Penjadwal).';
}

echo '[' . date('Y-m-d H:i:s') . '] ' . ($isCli ? 'cron' : 'http-cron') . "\n";
echo implode("\n", $lines) . "\n";

exit(0);
