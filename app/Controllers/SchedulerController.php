<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Scheduler;
use App\Models\SchedulerModel;

/**
 * Penjadwal tugas (cron) — Batch 18.
 *
 * HR dapat menjalankan job secara manual, melihat riwayat, dan mengatur
 * jendela pengingat. Untuk produksi, daftarkan `php public/cron.php` pada
 * crontab server agar job berjalan tanpa bergantung pada kunjungan halaman.
 */
final class SchedulerController extends Controller
{
    private SchedulerModel $scheduler;

    public function __construct()
    {
        $this->scheduler = new SchedulerModel();
    }

    public function index(): void
    {
        $this->view('scheduler/index', [
            'title'     => 'Penjadwal & Cron',
            'pageTitle' => 'Penjadwal & Cron',
            'jobs'      => $this->scheduler->jobs(),
            'history'   => $this->scheduler->history(15),
            'stats'     => $this->scheduler->stats(),
            'settings'  => $this->scheduler->settings(),
            'cronLines' => Scheduler::cronLines(),
            'cronUrl'   => url('cron.php'),
        ] + $this->withInput());
    }

    /** Jalankan satu job sekarang. */
    public function run(string $job): void
    {
        if (!isset(Scheduler::JOBS[$job])) {
            flash('error', 'Job tidak dikenal.');
            redirect('/scheduler');
        }

        $result = $this->scheduler->run($job, 'MANUAL');

        if ($result['status'] === 'ERROR') {
            flash('error', $result['label'] . ' gagal: ' . $result['message']);
        } else {
            flash($result['processed'] > 0 ? 'success' : 'info',
                $result['label'] . ': ' . ($result['message'] ?: 'selesai tanpa data baru.')
                . ' (' . $result['duration_ms'] . ' ms)');
        }

        redirect('/scheduler#job-' . $job);
    }

    /** Jalankan seluruh job yang aktif. */
    public function runAll(): void
    {
        $results = $this->scheduler->runAll('MANUAL');

        if (!$results) {
            flash('warning', 'Semua job sedang dinonaktifkan.');
            redirect('/scheduler');
        }

        $errors = array_filter($results, static fn (array $row): bool => $row['status'] === 'ERROR');
        $processed = array_sum(array_map(static fn (array $row): int => (int) $row['processed'], $results));

        if ($errors) {
            flash('warning', count($results) . ' job dijalankan, ' . count($errors)
                . ' gagal. Periksa riwayat eksekusi di bawah.');
        } else {
            flash('success', count($results) . ' job dijalankan, ' . $processed . ' data diproses.');
        }

        redirect('/scheduler');
    }

    /** Simpan preferensi jadwal & pengingat. */
    public function saveSettings(): void
    {
        $summary = $this->scheduler->saveSettings($_POST);

        Audit::log('cron.settings', 'settings', null, $summary);
        flash('success', 'Pengaturan penjadwal disimpan.');
        redirect('/scheduler');
    }

    /** Buat token baru untuk akses cron lewat HTTP. */
    public function rotateSecret(): void
    {
        $secret = $this->scheduler->regenerateSecret();

        Audit::log('cron.secret', 'settings', null, ['rotated' => true]);
        flash('success', 'Token cron baru dibuat. Perbarui URL cron di server: ?token=' . $secret);
        redirect('/scheduler');
    }

    /**
     * Endpoint cron via HTTP tanpa login (memakai token rahasia).
     *
     * GET /cron?token=xxx          → jalankan semua job aktif
     * GET /cron?token=xxx&job=...  → jalankan satu job
     * Tambahkan &format=text untuk respons teks biasa.
     */
    public function http(): void
    {
        $expected = (string) \App\Core\Settings::get('cron_secret', '');
        $given = (string) ($_GET['token'] ?? ($_SERVER['HTTP_X_CRON_TOKEN'] ?? ''));
        $plain = (string) ($_GET['format'] ?? '') === 'text';

        $respond = static function (int $code, array $payload, string $text) use ($plain): void {
            http_response_code($code);
            header('Cache-Control: no-store');
            if ($plain) {
                header('Content-Type: text/plain; charset=utf-8');
                echo $text;
            } else {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            exit;
        };

        if ($expected === '') {
            $respond(403, ['ok' => false, 'message' => 'Token cron belum dibuat. Buka Penjadwal & Cron → Buat Token Cron.'],
                'Token cron belum dibuat.');
        }
        if ($given === '' || !hash_equals($expected, $given)) {
            $respond(401, ['ok' => false, 'message' => 'Token cron tidak valid.'], 'Token cron tidak valid.');
        }

        $job = (string) ($_GET['job'] ?? '');
        if ($job !== '' && !isset(Scheduler::JOBS[$job])) {
            $respond(404, ['ok' => false, 'message' => 'Job tidak dikenal: ' . $job], 'Job tidak dikenal.');
        }

        $results = $job !== ''
            ? [$this->scheduler->run($job, 'CRON')]
            : $this->scheduler->runAll('CRON');

        $lines = [];
        foreach ($results as $row) {
            $lines[] = sprintf('[%s] %s — %d data (%d ms): %s',
                $row['status'], $row['job'], (int) $row['processed'], (int) $row['duration_ms'], $row['message']);
        }

        $respond(200, [
            'ok'      => true,
            'time'    => date('c'),
            'results' => $results,
        ], implode("\n", $lines) ?: 'Tidak ada job aktif.');
    }

    /** Hapus riwayat eksekusi lama. */
    public function prune(): void
    {
        $days = max(7, min(730, (int) ($_POST['days'] ?? 90)));
        $deleted = $this->scheduler->prune($days);

        Audit::log('cron.prune', 'cron_runs', null, ['days' => $days, 'deleted' => $deleted]);
        flash($deleted > 0 ? 'success' : 'info', $deleted > 0
            ? $deleted . ' baris riwayat eksekusi lebih tua dari ' . $days . ' hari dihapus.'
            : 'Tidak ada riwayat eksekusi yang melewati ' . $days . ' hari.');
        redirect('/scheduler');
    }
}
