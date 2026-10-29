<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Reset sistem: membersihkan seluruh jejak data ujicoba/demo dan menyisakan
 * satu akun administrator (HR) yang sedang login agar dapat masuk kembali.
 *
 * Yang DIPERTAHANKAN:
 *  - akun administrator (baris employees milik pengguna yang menjalankan reset)
 *  - tabel `settings` (konfigurasi perusahaan, kebijakan, versi skema)
 *  - tabel `companies` (identitas badan hukum)
 *
 * Yang DIHAPUS (opsional per kelompok):
 *  - data operasional  : presensi, lembur, cuti, payroll, klaim, penilaian,
 *                        pelatihan, rekrutmen, offboarding, pengumuman, notifikasi, log
 *  - data master       : unit, departemen, posisi, shift, komponen gaji, jenis cuti,
 *                        kategori klaim, katalog pelatihan, indikator, hari libur
 *  - karyawan lain     : semua baris employees selain administrator
 *  - berkas unggahan   : isi storage/uploads (kecuali foto administrator)
 */
final class SystemModel
{
    /** Tabel operasional (transaksi) — dikosongkan pada reset data. */
    private const OPERATIONAL = [
        'attendance',
        'overtime_requests',
        'leave_requests',
        'leave_balances',
        'schedules',
        'shift_change_requests',
        'payroll_item_components',
        'payroll_items',
        'payroll_periods',
        'employee_components',
        'expense_claims',
        'training_participants',
        'training_sessions',
        'performance_scores',
        'performance_reviews',
        'review_periods',
        'candidate_stages',
        'candidates',
        'job_vacancies',
        'offboarding_bast',
        'offboarding_assets',
        'offboarding_clearance',
        'offboarding_stages',
        'offboarding_requests',
        'announcements',
        'announcement_reads',
        'announcement_targets',
        'notifications',
        'email_queue',
        'cron_runs',
        'activity_logs',
        'remember_tokens',
        'password_resets',
    ];

    /** Master data. */
    private const MASTER = [
        'business_units',
        'departments',
        'positions',
        'work_shifts',
        'salary_components',
        'leave_types',
        'expense_categories',
        'trainings',
        'review_indicators',
        'holidays',
        'id_sequences',
    ];

    /** Label ramah untuk laporan hasil reset. */
    public const LABELS = [
        'attendance'              => 'Presensi',
        'overtime_requests'       => 'Pengajuan lembur',
        'leave_requests'          => 'Pengajuan cuti',
        'leave_balances'          => 'Saldo cuti',
        'schedules'               => 'Jadwal kerja',
        'shift_change_requests'   => 'Perubahan jadwal',
        'payroll_item_components' => 'Komponen slip gaji',
        'payroll_items'           => 'Slip gaji',
        'payroll_periods'         => 'Periode payroll',
        'employee_components'     => 'Penugasan komponen gaji',
        'expense_claims'          => 'Klaim & reimbursement',
        'training_participants'   => 'Peserta pelatihan',
        'training_sessions'       => 'Sesi pelatihan',
        'performance_scores'      => 'Skor penilaian',
        'performance_reviews'     => 'Penilaian kinerja',
        'review_periods'          => 'Periode penilaian',
        'candidate_stages'        => 'Riwayat tahap pelamar',
        'candidates'              => 'Pelamar',
        'job_vacancies'           => 'Lowongan',
        'offboarding_bast'        => 'BAST offboarding',
        'offboarding_assets'      => 'Aset offboarding',
        'offboarding_clearance'   => 'Checklist offboarding',
        'offboarding_stages'      => 'Riwayat offboarding',
        'offboarding_requests'    => 'Pengajuan offboarding',
        'announcements'           => 'Pengumuman',
        'announcement_reads'      => 'Pelacakan baca pengumuman',
        'announcement_targets'    => 'Target pengumuman',
        'email_queue'             => 'Antrean email',
        'cron_runs'               => 'Riwayat penjadwal',
        'notifications'           => 'Notifikasi',
        'activity_logs'           => 'Log aktivitas',
        'remember_tokens'         => 'Token "ingat perangkat"',
        'password_resets'         => 'Token reset kata sandi',
        'business_units'          => 'Unit organisasi',
        'departments'             => 'Departemen',
        'positions'               => 'Posisi',
        'work_shifts'             => 'Shift kerja',
        'salary_components'       => 'Komponen gaji',
        'leave_types'             => 'Jenis cuti',
        'expense_categories'      => 'Kategori klaim',
        'trainings'               => 'Katalog pelatihan',
        'review_indicators'       => 'Indikator penilaian',
        'holidays'                => 'Hari libur',
        'id_sequences'            => 'Sequence nomor ID',
    ];

    /**
     * Hitungan baris per tabel + jumlah berkas unggahan.
     *
     * @return array<string,mixed>
     */
    public function overview(): array
    {
        $rows = [];
        foreach (array_merge(self::OPERATIONAL, self::MASTER) as $table) {
            $rows[$table] = $this->count($table);
        }

        $employees = $this->count('employees');
        $uploads = $this->uploadFiles();

        return [
            'rows'            => $rows,
            'operational'     => array_sum(array_map(fn (string $t): int => $rows[$t] ?? 0, self::OPERATIONAL)),
            'master'          => array_sum(array_map(fn (string $t): int => $rows[$t] ?? 0, self::MASTER)),
            'employees'       => $employees,
            'employees_other' => max(0, $employees - 1),
            'uploads'         => count($uploads),
            'upload_bytes'    => array_sum($uploads),
            'operational_tables' => count(self::OPERATIONAL),
            'master_tables'   => count(self::MASTER),
        ];
    }

    private function count(string $table): int
    {
        try {
            if (!Database::tableExists($table)) {
                return 0;
            }
            return (int) Database::value('SELECT COUNT(*) FROM ' . $table, [], 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Daftar berkas unggahan (path absolut => ukuran byte).
     *
     * @return array<string,int>
     */
    private function uploadFiles(?string $keepPhoto = null): array
    {
        $root = rtrim((string) config('upload_path'), '/');
        if ($root === '' || !is_dir($root)) {
            return [];
        }

        $keep = $keepPhoto !== null && $keepPhoto !== '' ? basename($keepPhoto) : null;
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            if ($keep !== null && $file->getFilename() === $keep) {
                continue;
            }
            // Jangan hapus berkas penjaga direktori (.gitkeep dan sejenisnya).
            if (str_starts_with($file->getFilename(), '.')) {
                continue;
            }
            $files[$file->getPathname()] = (int) $file->getSize();
        }

        return $files;
    }

    /**
     * Jalankan reset.
     *
     * @param array{data?:bool,master?:bool,employees?:bool,uploads?:bool} $options
     * @return array{log:array<int,string>,summary:array<string,int>}
     */
    public function reset(array $options, array $admin): array
    {
        $log = [];
        $summary = ['rows' => 0, 'tables' => 0, 'employees' => 0, 'uploads' => 0, 'upload_bytes' => 0];
        $adminId = (int) $admin['id'];

        if (!empty($options['data'])) {
            foreach (self::OPERATIONAL as $table) {
                if (!Database::tableExists($table)) {
                    continue;
                }
                $deleted = Database::delete($table, '1 = 1');
                $summary['rows'] += $deleted;
                if ($deleted > 0) {
                    $summary['tables']++;
                }
            }
            $log[] = 'Data operasional dibersihkan: ' . $summary['rows'] . ' baris dari ' . $summary['tables'] . ' tabel.';
        }

        if (!empty($options['master'])) {
            // Kosongkan rujukan organisasi milik administrator sebelum master dihapus.
            Database::update('employees', [
                'business_unit_id'  => null,
                'department_id'     => null,
                'position_id'       => null,
                'direct_manager_id' => null,
            ], 'id = ?', [$adminId]);

            $masterRows = 0;
            foreach (self::MASTER as $table) {
                if (!Database::tableExists($table)) {
                    continue;
                }
                $masterRows += Database::delete($table, '1 = 1');
            }
            $summary['rows'] += $masterRows;
            $log[] = 'Master data organisasi dihapus: ' . $masterRows . ' baris.';
        }

        if (!empty($options['employees'])) {
            // Lepas relasi atasan pada akun admin agar tidak menunjuk karyawan terhapus.
            Database::update('employees', ['direct_manager_id' => null], 'id = ?', [$adminId]);

            $removed = Database::delete('employees', 'id <> ?', [$adminId]);
            if (!empty($options['master'])) {
                Database::update('employees', [
                    'business_unit_id' => null,
                    'department_id'    => null,
                    'position_id'      => null,
                ], 'id = ?', [$adminId]);
            }
            $summary['employees'] = $removed;
            $log[] = 'Karyawan dihapus: ' . $removed . ' akun (akun administrator dipertahankan).';
        }

        if (!empty($options['uploads'])) {
            foreach ($this->uploadFiles((string) ($admin['photo'] ?? '')) as $path => $size) {
                if (@unlink($path)) {
                    $summary['uploads']++;
                    $summary['upload_bytes'] += $size;
                }
            }
            $log[] = 'Berkas unggahan dihapus: ' . $summary['uploads'] . ' file (' . $this->formatBytes($summary['upload_bytes']) . ').';
        }

        // Cache & sesi sisa ujicoba
        try {
            \App\Core\Settings::set('last_reset_at', date('Y-m-d H:i:s'), 'general');
            \App\Core\Settings::set('last_reset_by', (string) ($admin['email'] ?? ''), 'general');
        } catch (\Throwable) {
            // abaikan
        }

        return ['log' => $log, 'summary' => $summary];
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1, ',', '.') . ' KB';
        }
        return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
    }
}
