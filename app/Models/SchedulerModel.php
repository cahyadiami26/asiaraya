<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Scheduler;
use App\Core\Settings;

/**
 * Data untuk halaman "Penjadwal & Cron": status tiap job, riwayat eksekusi,
 * dan preferensi jadwal (jendela pengingat, batch email, mode otomatis).
 */
final class SchedulerModel
{
    /**
     * Daftar job lengkap dengan status aktif & eksekusi terakhir.
     *
     * @return array<int,array<string,mixed>>
     */
    public function jobs(): array
    {
        $enabled = Scheduler::enabledMap();
        $jobs = [];

        foreach (Scheduler::JOBS as $key => $config) {
            $last = Scheduler::lastRun($key);
            $jobs[] = [
                'key'         => $key,
                'label'       => $config['label'],
                'desc'        => $config['desc'],
                'icon'        => $config['icon'],
                'interval'    => (int) $config['interval'],
                'interval_label' => $this->intervalLabel((int) $config['interval']),
                'enabled'     => $enabled[$key] ?? true,
                'last'        => $last,
                'due'         => Scheduler::due($key, (int) $config['interval']),
            ];
        }

        return $jobs;
    }

    public function run(string $job, string $source = 'MANUAL'): array
    {
        return Scheduler::run($job, $source);
    }

    /** @return array<int,array<string,mixed>> */
    public function runAll(string $source = 'MANUAL'): array
    {
        return Scheduler::runAll($source);
    }

    /** @return array<string,mixed> */
    public function settings(): array
    {
        return [
            'auto'              => Settings::bool('cron_auto', true),
            'contract_lead'     => (int) Settings::get('cron_contract_lead_days', 30),
            'leave_lead'        => (int) Settings::get('cron_leave_lead_days', 70),
            'document_lead'     => (int) Settings::get('cron_document_lead_days', 60),
            'email_batch'       => (int) Settings::get('cron_email_batch', 25),
            'announcement_mail' => Settings::bool('cron_announcement_email', false),
            'secret'            => (string) Settings::get('cron_secret', ''),
            'prune_days'        => (int) Settings::get('cron_prune_days', 90),
        ];
    }

    /** Simpan preferensi job. Mengembalikan ringkasan untuk audit. */
    public function saveSettings(array $post): array
    {
        $toggles = [];
        foreach (Scheduler::JOBS as $key => $config) {
            $toggles[(string) $config['toggle']] = isset($post['job_' . $key]) ? 1 : 0;
        }

        $pairs = $toggles + [
            'cron_auto'              => isset($post['cron_auto']) ? 1 : 0,
            'cron_contract_lead_days' => max(1, min(180, (int) ($post['contract_lead'] ?? 30))),
            'cron_leave_lead_days'    => max(1, min(180, (int) ($post['leave_lead'] ?? 70))),
            'cron_document_lead_days' => max(7, min(180, (int) ($post['document_lead'] ?? 60))),
            'cron_email_batch'        => max(1, min(200, (int) ($post['email_batch'] ?? 25))),
            'cron_announcement_email' => isset($post['announcement_mail']) ? 1 : 0,
            'cron_prune_days'         => max(7, min(730, (int) ($post['prune_days'] ?? 90))),
        ];

        Settings::many($pairs, 'cron');

        return [
            'auto'      => $pairs['cron_auto'] === 1,
            'jobs_on'   => array_keys(array_filter($toggles)),
            'jobs_off'  => array_keys(array_filter($toggles, static fn (int $v): bool => $v === 0)),
        ];
    }

    /** Buat / ganti token cron untuk akses HTTP tanpa login. */
    public function regenerateSecret(): string
    {
        $secret = bin2hex(random_bytes(20));
        Settings::set('cron_secret', $secret, 'cron');
        return $secret;
    }

    /** @return array<int,array<string,mixed>> */
    public function history(int $limit = 15): array
    {
        return Scheduler::history($limit);
    }

    public function prune(int $days = 90): int
    {
        return Scheduler::prune($days);
    }

    public function stats(): array
    {
        return Scheduler::stats();
    }

    private function intervalLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return 'setiap ' . $minutes . ' menit';
        }
        if ($minutes % 1440 === 0) {
            return 'setiap ' . ($minutes / 1440) . ' hari';
        }
        if ($minutes % 60 === 0) {
            return 'setiap ' . ($minutes / 60) . ' jam';
        }
        return 'setiap ' . $minutes . ' menit';
    }
}
