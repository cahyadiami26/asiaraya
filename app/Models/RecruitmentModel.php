<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Rekrutmen & onboarding (applicant tracking).
 *
 * Tiga lapis data:
 *  - job_vacancies   : lowongan (unit/departemen/posisi, kuota, rentang gaji)
 *  - candidates      : pelamar beserta tahapan seleksi, jadwal wawancara, rating
 *  - candidate_stages: riwayat perpindahan tahap (audit pipeline)
 *
 * Tahapan: APPLIED → SCREENING → INTERVIEW → OFFER → HIRED, dengan REJECTED
 * dan WITHDRAWN sebagai jalur keluar. Pelamar yang diterima dapat langsung
 * dikonversi menjadi data karyawan (lihat RecruitmentController::hire()).
 */
final class RecruitmentModel
{
    public const JOB_STATUSES = [
        'DRAFT'   => 'Draf',
        'OPEN'    => 'Dibuka',
        'ON_HOLD' => 'Ditahan',
        'CLOSED'  => 'Ditutup',
    ];

    public const JOB_TONES = [
        'DRAFT'   => 'slate',
        'OPEN'    => 'teal',
        'ON_HOLD' => 'amber',
        'CLOSED'  => 'slate',
    ];

    public const WORK_MODES = [
        'ONSITE' => 'Di Kantor',
        'HYBRID' => 'Hybrid',
        'REMOTE' => 'Remote',
    ];

    public const EMPLOYMENT_TYPES = [
        'PKWTT'             => 'PKWTT (Tetap)',
        'PKWTT (Probation)' => 'PKWTT (Probation)',
        'PKWT'              => 'PKWT (Kontrak)',
        'Magang'            => 'Magang',
        'Outsourcing'       => 'Outsourcing',
    ];

    /** Urutan tahapan aktif pada pipeline. */
    public const PIPELINE = ['APPLIED', 'SCREENING', 'INTERVIEW', 'OFFER'];

    public const STAGES = [
        'APPLIED'   => 'Pelamar Baru',
        'SCREENING' => 'Seleksi Berkas',
        'INTERVIEW' => 'Wawancara',
        'OFFER'     => 'Penawaran',
        'HIRED'     => 'Diterima',
        'REJECTED'  => 'Ditolak',
        'WITHDRAWN' => 'Mengundurkan Diri',
    ];

    public const STAGE_TONES = [
        'APPLIED'   => 'indigo',
        'SCREENING' => 'amber',
        'INTERVIEW' => 'teal',
        'OFFER'     => 'indigo',
        'HIRED'     => 'teal',
        'REJECTED'  => 'rose',
        'WITHDRAWN' => 'slate',
    ];

    public const EDUCATIONS = ['SMA/SMK', 'D3', 'S1', 'S2', 'S3'];

    public const SOURCES = [
        'WEBSITE'    => 'Website Karier',
        'REFERRAL'   => 'Referensi Karyawan',
        'LINKEDIN'   => 'LinkedIn',
        'JOB_PORTAL' => 'Portal Lowongan',
        'WALK_IN'    => 'Walk-in',
        'OTHER'      => 'Lainnya',
    ];

    public const INTERVIEW_MODES = [
        'ONSITE' => 'Tatap Muka',
        'ONLINE' => 'Daring',
        'PHONE'  => 'Telepon',
    ];

    /* ------------------------------------------------------------ lowongan */

    /** @param array{status:string,unit:int,q:string} $filters */
    private function jobWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if ($filters['status'] !== '' && isset(self::JOB_STATUSES[$filters['status']])) {
            $where[] = 'j.status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['unit'] ?? 0) > 0) {
            $where[] = 'j.business_unit_id = ?';
            $params[] = $filters['unit'];
        }
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . $filters['q'] . '%';
            $where[] = '(j.title LIKE ? OR j.code LIKE ? OR j.location LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    public function jobs(array $filters): array
    {
        [$where, $params] = $this->jobWhere($filters);

        return Database::all(
            'SELECT j.*, u.name AS unit_name, d.name AS department_name, p.name AS position_name,
                    m.full_name AS manager_name,
                    (SELECT COUNT(*) FROM candidates c WHERE c.job_id = j.id) AS applicants,
                    (SELECT COUNT(*) FROM candidates c WHERE c.job_id = j.id
                      AND c.status IN (\'APPLIED\',\'SCREENING\',\'INTERVIEW\',\'OFFER\')) AS in_pipeline,
                    (SELECT COUNT(*) FROM candidates c WHERE c.job_id = j.id AND c.status = \'HIRED\') AS hired,
                    (SELECT COUNT(*) FROM candidates c WHERE c.job_id = j.id AND c.status = \'APPLIED\') AS fresh
               FROM job_vacancies j
               LEFT JOIN business_units u ON u.id = j.business_unit_id
               LEFT JOIN departments d ON d.id = j.department_id
               LEFT JOIN positions p ON p.id = j.position_id
               LEFT JOIN employees m ON m.id = j.hiring_manager_id' . $where . '
              ORDER BY CASE j.status WHEN \'OPEN\' THEN 0 WHEN \'ON_HOLD\' THEN 1 WHEN \'DRAFT\' THEN 2 ELSE 3 END,
                       j.open_date DESC, j.id DESC',
            $params
        );
    }

    public function findJob(int $id): ?array
    {
        return Database::one(
            'SELECT j.*, u.name AS unit_name, d.name AS department_name, p.name AS position_name,
                    m.full_name AS manager_name, e.full_name AS creator_name,
                    (SELECT COUNT(*) FROM candidates c WHERE c.job_id = j.id) AS applicants,
                    (SELECT COUNT(*) FROM candidates c WHERE c.job_id = j.id AND c.status = \'HIRED\') AS hired
               FROM job_vacancies j
               LEFT JOIN business_units u ON u.id = j.business_unit_id
               LEFT JOIN departments d ON d.id = j.department_id
               LEFT JOIN positions p ON p.id = j.position_id
               LEFT JOIN employees m ON m.id = j.hiring_manager_id
               LEFT JOIN employees e ON e.id = j.created_by
              WHERE j.id = ?',
            [$id]
        );
    }

    public function jobByCode(string $code, ?int $exceptId = null): ?array
    {
        return Database::one(
            'SELECT * FROM job_vacancies WHERE code = ?' . ($exceptId !== null ? ' AND id <> ?' : '') . ' LIMIT 1',
            $exceptId !== null ? [$code, $exceptId] : [$code]
        );
    }

    /** Funnel per tahap untuk satu lowongan. */
    public function funnel(int $jobId): array
    {
        $rows = Database::all(
            'SELECT status, COUNT(*) AS total FROM candidates WHERE job_id = ? GROUP BY status',
            [$jobId]
        );
        $counts = array_fill_keys(array_keys(self::STAGES), 0);
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        return $counts;
    }

    /** @param array<string,mixed> $data */
    public function createJob(array $data, int $actorId): int
    {
        return Database::insert('job_vacancies', $data + [
            'created_by' => $actorId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string,mixed> $data */
    public function updateJob(int $id, array $data): void
    {
        Database::update('job_vacancies', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    public function setJobStatus(int $id, string $status): void
    {
        Database::update('job_vacancies', [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    public function jobApplicantCount(int $id): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM candidates WHERE job_id = ?', [$id], 0);
    }

    public function deleteJob(int $id): void
    {
        Database::delete('job_vacancies', 'id = ?', [$id]);
    }

    public function openJobs(): array
    {
        return Database::all(
            'SELECT j.id, j.code, j.title, j.status, j.headcount,
                    (SELECT COUNT(*) FROM candidates c WHERE c.job_id = j.id AND c.status = \'HIRED\') AS hired
               FROM job_vacancies j
              WHERE j.status IN (\'OPEN\',\'ON_HOLD\')
              ORDER BY j.title ASC'
        );
    }

    /* ------------------------------------------------------------- pelamar */

    private function candidateWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (($filters['job'] ?? 0) > 0) {
            $where[] = 'c.job_id = ?';
            $params[] = $filters['job'];
        }
        if ($filters['status'] !== '' && isset(self::STAGES[$filters['status']])) {
            $where[] = 'c.status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['source'] ?? '') !== '' && isset(self::SOURCES[$filters['source']])) {
            $where[] = 'c.source = ?';
            $params[] = $filters['source'];
        }
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . $filters['q'] . '%';
            $where[] = '(c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    public function countCandidates(array $filters): int
    {
        [$where, $params] = $this->candidateWhere($filters);
        return (int) Database::value('SELECT COUNT(*) FROM candidates c' . $where, $params, 0);
    }

    public function candidates(array $filters, int $limit = 20, int $offset = 0): array
    {
        [$where, $params] = $this->candidateWhere($filters);
        $params[] = max(1, $limit);
        $params[] = max(0, $offset);

        return Database::all(
            'SELECT c.*, j.title AS job_title, j.code AS job_code, j.business_unit_id,
                    u.name AS unit_name, d.name AS department_name, p.name AS position_name
               FROM candidates c
               JOIN job_vacancies j ON j.id = c.job_id
               LEFT JOIN business_units u ON u.id = j.business_unit_id
               LEFT JOIN departments d ON d.id = j.department_id
               LEFT JOIN positions p ON p.id = j.position_id' . $where . '
              ORDER BY c.created_at DESC, c.id DESC
              LIMIT ? OFFSET ?',
            $params
        );
    }

    public function findCandidate(int $id): ?array
    {
        return Database::one(
            'SELECT c.*, j.title AS job_title, j.code AS job_code, j.status AS job_status,
                    j.business_unit_id, j.department_id, j.position_id, j.employment_type,
                    j.work_mode, j.location, j.salary_min, j.salary_max, j.hiring_manager_id,
                    u.name AS unit_name, d.name AS department_name, p.name AS position_name,
                    m.full_name AS manager_name, iv.full_name AS interviewer_name,
                    emp.full_name AS employee_name, emp.employee_code AS employee_code
               FROM candidates c
               JOIN job_vacancies j ON j.id = c.job_id
               LEFT JOIN business_units u ON u.id = j.business_unit_id
               LEFT JOIN departments d ON d.id = j.department_id
               LEFT JOIN positions p ON p.id = j.position_id
               LEFT JOIN employees m ON m.id = j.hiring_manager_id
               LEFT JOIN employees iv ON iv.id = c.interviewer_id
               LEFT JOIN employees emp ON emp.id = c.employee_id
              WHERE c.id = ?',
            [$id]
        );
    }

    public function candidatesByStage(int $jobId): array
    {
        $rows = $this->candidates(['job' => $jobId, 'status' => '', 'source' => '', 'q' => ''], 500, 0);
        $grouped = array_fill_keys(array_keys(self::STAGES), []);
        foreach ($rows as $row) {
            $grouped[$row['status']][] = $row;
        }
        return $grouped;
    }

    public function candidateEmailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM candidates WHERE LOWER(email) = ?';
        $params = [mb_strtolower($email)];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        return (int) Database::value($sql, $params, 0) > 0;
    }

    /** @param array<string,mixed> $data */
    public function createCandidate(array $data, int $actorId): int
    {
        $id = Database::insert('candidates', $data + [
            'status'     => 'APPLIED',
            'created_by' => $actorId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->logStage($id, null, 'APPLIED', 'Pelamar masuk ke pipeline.', $actorId);
        return $id;
    }

    /** @param array<string,mixed> $data */
    public function updateCandidate(int $id, array $data): void
    {
        Database::update('candidates', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    public function deleteCandidate(int $id): void
    {
        Database::delete('candidate_stages', 'candidate_id = ?', [$id]);
        Database::delete('candidates', 'id = ?', [$id]);
    }

    /** Riwayat tahapan satu pelamar. */
    public function stages(int $candidateId): array
    {
        return Database::all(
            'SELECT s.*, e.full_name AS actor_name
               FROM candidate_stages s
               LEFT JOIN employees e ON e.id = s.actor_id
              WHERE s.candidate_id = ?
              ORDER BY s.id ASC',
            [$candidateId]
        );
    }

    /**
     * Pindahkan pelamar ke tahap lain.
     *
     * @param array<string,mixed> $extra kolom tambahan (interview_at, interviewer_id, note, …)
     * @return array{error:?string,candidate:?array}
     */
    public function moveStage(int $id, string $to, string $note, int $actorId, array $extra = []): array
    {
        $candidate = $this->findCandidate($id);
        if (!$candidate) {
            return ['error' => 'Pelamar tidak ditemukan.', 'candidate' => null];
        }
        if (!isset(self::STAGES[$to])) {
            return ['error' => 'Tahap seleksi tidak dikenal.', 'candidate' => null];
        }
        if ($candidate['status'] === 'HIRED' && $to !== 'HIRED') {
            return ['error' => 'Pelamar sudah diterima dan dikonversi menjadi karyawan.', 'candidate' => null];
        }

        $from = (string) $candidate['status'];
        $payload = $extra + [
            'status'     => $to,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($to !== 'REJECTED') {
            $payload['reject_reason'] = null;
        }
        if (in_array($to, ['HIRED', 'REJECTED', 'WITHDRAWN'], true)) {
            // tahap akhir tidak lagi butuh jadwal wawancara aktif
            $payload['interview_at'] = $to === 'HIRED' ? ($candidate['interview_at'] ?? null) : $candidate['interview_at'];
        }

        Database::update('candidates', $payload, 'id = ?', [$id]);
        $this->logStage($id, $from, $to, $note, $actorId);

        $this->notifyStage($candidate, $to);

        return ['error' => null, 'candidate' => $this->findCandidate($id)];
    }

    private function logStage(int $candidateId, ?string $from, string $to, string $note, int $actorId): void
    {
        Database::insert('candidate_stages', [
            'candidate_id' => $candidateId,
            'from_status'  => $from,
            'to_status'    => $to,
            'note'         => mb_substr(trim($note), 0, 255) ?: null,
            'actor_id'     => $actorId > 0 ? $actorId : null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    /** Tandai pelamar diterima & hubungkan ke data karyawan. */
    public function markHired(int $candidateId, int $employeeId, string $note, int $actorId): void
    {
        $candidate = $this->findCandidate($candidateId);
        if (!$candidate) {
            return;
        }

        Database::update('candidates', [
            'status'      => 'HIRED',
            'employee_id' => $employeeId,
            'hired_at'    => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$candidateId]);

        $this->logStage($candidateId, (string) $candidate['status'], 'HIRED', $note, $actorId);
    }

    /* --------------------------------------------------------------- stats */

    public function stats(): array
    {
        $openJobs = (int) Database::value("SELECT COUNT(*) FROM job_vacancies WHERE status = 'OPEN'", [], 0);
        $draftJobs = (int) Database::value("SELECT COUNT(*) FROM job_vacancies WHERE status = 'DRAFT'", [], 0);
        $applicants = (int) Database::value('SELECT COUNT(*) FROM candidates', [], 0);
        $inPipeline = (int) Database::value(
            "SELECT COUNT(*) FROM candidates WHERE status IN ('APPLIED','SCREENING','INTERVIEW','OFFER')",
            [],
            0
        );
        $fresh = (int) Database::value("SELECT COUNT(*) FROM candidates WHERE status = 'APPLIED'", [], 0);
        $offers = (int) Database::value("SELECT COUNT(*) FROM candidates WHERE status = 'OFFER'", [], 0);
        $hired = (int) Database::value("SELECT COUNT(*) FROM candidates WHERE status = 'HIRED'", [], 0);
        $hiredThisMonth = (int) Database::value(
            "SELECT COUNT(*) FROM candidates WHERE status = 'HIRED' AND hired_at >= ?",
            [date('Y-m-01') . ' 00:00:00'],
            0
        );

        // rata-rata hari dari pelamar masuk sampai diterima
        $durations = Database::all(
            "SELECT created_at, hired_at FROM candidates WHERE status = 'HIRED' AND hired_at IS NOT NULL"
        );
        $avgDays = null;
        if ($durations) {
            $total = 0;
            foreach ($durations as $row) {
                $total += max(0, (int) floor((strtotime((string) $row['hired_at']) - strtotime((string) $row['created_at'])) / 86400));
            }
            $avgDays = (int) round($total / count($durations));
        }

        return [
            'open_jobs'    => $openJobs,
            'draft_jobs'   => $draftJobs,
            'applicants'   => $applicants,
            'in_pipeline'  => $inPipeline,
            'fresh'        => $fresh,
            'offers'       => $offers,
            'hired'        => $hired,
            'hired_month'  => $hiredThisMonth,
            'avg_days'     => $avgDays,
            'conversion'   => $applicants > 0 ? (int) round($hired / $applicants * 100) : 0,
        ];
    }

    /** Badge navigasi: pelamar baru yang menunggu seleksi berkas. */
    public function freshCount(): int
    {
        try {
            return (int) Database::value(
                "SELECT COUNT(*) FROM candidates c
                   JOIN job_vacancies j ON j.id = c.job_id
                  WHERE c.status = 'APPLIED' AND j.status = 'OPEN'",
                [],
                0
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /* ------------------------------------------------------------ helpers */

    private function notifyStage(array $candidate, string $to): void
    {
        $managerId = (int) ($candidate['hiring_manager_id'] ?? 0);
        if ($managerId <= 0) {
            return;
        }
        if (!in_array($to, ['INTERVIEW', 'OFFER'], true)) {
            return;
        }

        $this->notify(
            $managerId,
            $to === 'INTERVIEW' ? 'Pelamar masuk tahap wawancara' : 'Pelamar menerima penawaran',
            $candidate['full_name'] . ' — ' . $candidate['job_title'],
            '/recruitment/candidates/' . (int) $candidate['id'],
            $to === 'INTERVIEW' ? 'info' : 'success'
        );
    }

    public function notify(int $employeeId, string $title, string $message, string $link, string $type = 'info'): void
    {
        try {
            Database::insert('notifications', [
                'employee_id' => $employeeId,
                'title'       => mb_substr($title, 0, 150),
                'message'     => mb_substr($message, 0, 255),
                'link'        => $link,
                'type'        => $type,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // notifikasi best-effort
        }
    }
}
