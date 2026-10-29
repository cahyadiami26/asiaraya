<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Settings;

/**
 * Penilaian kinerja (performance review).
 *
 * Alur: HR menyiapkan periode + indikator → periode dibuka (generate satu baris
 * penilaian per karyawan beserta snapshot bobot indikator) → karyawan mengisi
 * self assessment → atasan menilai → HR mengkalibrasi & menetapkan hasil.
 *
 * Nilai akhir = self_score × bobot_self + manager_score × (100 − bobot_self),
 * dibulatkan dua desimal; huruf mutu diturunkan dari nilai akhir.
 */
final class PerformanceModel
{
    public const PERIOD_TYPES = [
        'TAHUNAN'    => 'Tahunan',
        'SEMESTERAN' => 'Semesteran',
        'TRIWULAN'   => 'Triwulan',
        'PROBATION'  => 'Masa Percobaan',
    ];

    public const PERIOD_STATUSES = [
        'DRAFT'       => 'Draf',
        'SELF'        => 'Self Assessment',
        'MANAGER'     => 'Penilaian Atasan',
        'CALIBRATION' => 'Kalibrasi HR',
        'CLOSED'      => 'Ditutup',
    ];

    public const PERIOD_TONES = [
        'DRAFT'       => 'slate',
        'SELF'        => 'indigo',
        'MANAGER'     => 'amber',
        'CALIBRATION' => 'teal',
        'CLOSED'      => 'slate',
    ];

    public const STATUSES = [
        'PENDING'      => 'Menunggu Self Assessment',
        'SELF_DONE'    => 'Menunggu Penilaian Atasan',
        'MANAGER_DONE' => 'Menunggu Kalibrasi HR',
        'FINAL'        => 'Final',
    ];

    public const STATUS_TONES = [
        'PENDING'      => 'slate',
        'SELF_DONE'    => 'indigo',
        'MANAGER_DONE' => 'amber',
        'FINAL'        => 'teal',
    ];

    public const CATEGORIES = [
        'KOMPETENSI' => 'Kompetensi',
        'HASIL'      => 'Hasil Kerja',
        'PERILAKU'   => 'Perilaku',
    ];

    public const CATEGORY_TONES = [
        'KOMPETENSI' => 'indigo',
        'HASIL'      => 'teal',
        'PERILAKU'   => 'amber',
    ];

    public const RATING_LABELS = [
        'A' => 'Sangat Baik',
        'B' => 'Baik',
        'C' => 'Cukup',
        'D' => 'Kurang',
        'E' => 'Sangat Kurang',
    ];

    public const RATING_TONES = [
        'A' => 'teal',
        'B' => 'indigo',
        'C' => 'slate',
        'D' => 'amber',
        'E' => 'rose',
    ];

    public const SCALE_LABELS = [
        1 => 'Sangat kurang',
        2 => 'Kurang',
        3 => 'Cukup',
        4 => 'Baik',
        5 => 'Sangat baik',
    ];

    /* ------------------------------------------------------------- periode */

    public function periods(): array
    {
        return Database::all(
            'SELECT p.*, u.name AS unit_name,
                    (SELECT COUNT(*) FROM performance_reviews r WHERE r.period_id = p.id) AS participants,
                    (SELECT COUNT(*) FROM performance_reviews r WHERE r.period_id = p.id AND r.self_submitted_at IS NOT NULL) AS self_done,
                    (SELECT COUNT(*) FROM performance_reviews r WHERE r.period_id = p.id AND r.manager_submitted_at IS NOT NULL) AS manager_done,
                    (SELECT COUNT(*) FROM performance_reviews r WHERE r.period_id = p.id AND r.status = \'FINAL\') AS finalized
               FROM review_periods p
               LEFT JOIN business_units u ON u.id = p.business_unit_id
              ORDER BY p.start_date DESC, p.id DESC'
        );
    }

    public function findPeriod(int $id): ?array
    {
        return Database::one(
            'SELECT p.*, u.name AS unit_name, e.full_name AS creator_name
               FROM review_periods p
               LEFT JOIN business_units u ON u.id = p.business_unit_id
               LEFT JOIN employees e ON e.id = p.created_by
              WHERE p.id = ?',
            [$id]
        );
    }

    public function activePeriod(): ?array
    {
        return Database::one(
            "SELECT * FROM review_periods WHERE status IN ('SELF','MANAGER','CALIBRATION') ORDER BY start_date DESC, id DESC LIMIT 1"
        );
    }

    public function createPeriod(array $data, int $userId): int
    {
        $now = date('Y-m-d H:i:s');
        return Database::insert('review_periods', [
            'name'             => $data['name'],
            'type'             => $data['type'],
            'start_date'       => $data['start_date'],
            'end_date'         => $data['end_date'],
            'self_due'         => $data['self_due'],
            'manager_due'      => $data['manager_due'],
            'business_unit_id' => $data['business_unit_id'],
            'self_weight'      => $data['self_weight'],
            'status'           => 'DRAFT',
            'note'             => $data['note'],
            'created_by'       => $userId,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
    }

    public function updatePeriod(int $id, array $data): void
    {
        Database::update('review_periods', [
            'name'             => $data['name'],
            'type'             => $data['type'],
            'start_date'       => $data['start_date'],
            'end_date'         => $data['end_date'],
            'self_due'         => $data['self_due'],
            'manager_due'      => $data['manager_due'],
            'business_unit_id' => $data['business_unit_id'],
            'self_weight'      => $data['self_weight'],
            'note'             => $data['note'],
            'updated_at'       => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    public function deletePeriod(int $id): void
    {
        $reviewIds = array_column(
            Database::all('SELECT id FROM performance_reviews WHERE period_id = ?', [$id]),
            'id'
        );
        foreach ($reviewIds as $reviewId) {
            Database::delete('performance_scores', 'review_id = ?', [(int) $reviewId]);
        }
        Database::delete('performance_reviews', 'period_id = ?', [$id]);
        Database::delete('review_periods', 'id = ?', [$id]);
    }

    public function setPeriodStatus(int $id, string $status): void
    {
        Database::update('review_periods', [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    /**
     * Buka periode: buat baris penilaian + snapshot indikator untuk setiap
     * karyawan aktif (opsional dibatasi unit). Idempoten — peserta lama dilewati.
     *
     * @return array{created:int,skipped:int,error:?string}
     */
    public function openPeriod(int $periodId): array
    {
        $period = $this->findPeriod($periodId);
        if (!$period) {
            return ['created' => 0, 'skipped' => 0, 'error' => 'Periode tidak ditemukan.'];
        }
        if ($period['status'] !== 'DRAFT') {
            return ['created' => 0, 'skipped' => 0, 'error' => 'Periode sudah pernah dibuka.'];
        }

        $indicators = $this->indicators(true);
        if (!$indicators) {
            return ['created' => 0, 'skipped' => 0, 'error' => 'Belum ada indikator aktif. Tambahkan indikator penilaian terlebih dahulu.'];
        }

        $sql = "SELECT id, direct_manager_id, business_unit_id, department_id, position_id
                  FROM employees WHERE flag = 'ACTIVE'";
        $params = [];
        if (!empty($period['business_unit_id'])) {
            $sql .= ' AND business_unit_id = ?';
            $params[] = (int) $period['business_unit_id'];
        }
        $sql .= ' ORDER BY id';

        $employees = Database::all($sql, $params);
        if (!$employees) {
            return ['created' => 0, 'skipped' => 0, 'error' => 'Tidak ada karyawan aktif pada cakupan periode ini.'];
        }

        $existing = array_flip(array_map(
            static fn ($row) => (int) $row['employee_id'],
            Database::all('SELECT employee_id FROM performance_reviews WHERE period_id = ?', [$periodId])
        ));

        $now = date('Y-m-d H:i:s');
        $created = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            $employeeId = (int) $employee['id'];
            if (isset($existing[$employeeId])) {
                $skipped++;
                continue;
            }

            $reviewId = Database::insert('performance_reviews', [
                'period_id'        => $periodId,
                'employee_id'      => $employeeId,
                'manager_id'       => $employee['direct_manager_id'] !== null ? (int) $employee['direct_manager_id'] : null,
                'business_unit_id' => $employee['business_unit_id'] !== null ? (int) $employee['business_unit_id'] : null,
                'department_id'    => $employee['department_id'] !== null ? (int) $employee['department_id'] : null,
                'position_id'      => $employee['position_id'] !== null ? (int) $employee['position_id'] : null,
                'status'           => 'PENDING',
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            foreach ($indicators as $indicator) {
                Database::insert('performance_scores', [
                    'review_id'    => $reviewId,
                    'indicator_id' => (int) $indicator['id'],
                    'weight'       => (float) $indicator['weight'],
                ]);
            }
            $created++;
        }

        $this->setPeriodStatus($periodId, 'SELF');
        $this->notifyParticipants($periodId);

        return ['created' => $created, 'skipped' => $skipped, 'error' => null];
    }

    public function deleteReviews(int $periodId): int
    {
        $rows = Database::all('SELECT id FROM performance_reviews WHERE period_id = ?', [$periodId]);
        foreach ($rows as $row) {
            Database::delete('performance_scores', 'review_id = ?', [(int) $row['id']]);
        }
        return Database::delete('performance_reviews', 'period_id = ?', [$periodId]);
    }

    /* ----------------------------------------------------------- indikator */

    public function indicators(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM review_indicators';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order, id';
        return Database::all($sql);
    }

    public function findIndicator(int $id): ?array
    {
        return Database::one('SELECT * FROM review_indicators WHERE id = ?', [$id]);
    }

    public function indicatorUsed(int $id): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM performance_scores WHERE indicator_id = ?', [$id], 0);
    }

    public function weightTotal(): float
    {
        return round((float) Database::value('SELECT COALESCE(SUM(weight), 0) FROM review_indicators WHERE is_active = 1', [], 0), 2);
    }

    public function createIndicator(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        return Database::insert('review_indicators', [
            'code'        => $data['code'],
            'name'        => $data['name'],
            'description' => $data['description'] ?: null,
            'category'    => $data['category'],
            'weight'      => $data['weight'],
            'is_active'   => (int) $data['is_active'],
            'sort_order'  => (int) $data['sort_order'],
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    public function updateIndicator(int $id, array $data): void
    {
        Database::update('review_indicators', [
            'code'        => $data['code'],
            'name'        => $data['name'],
            'description' => $data['description'] ?: null,
            'category'    => $data['category'],
            'weight'      => $data['weight'],
            'is_active'   => (int) $data['is_active'],
            'sort_order'  => (int) $data['sort_order'],
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    public function deleteIndicator(int $id): void
    {
        Database::delete('review_indicators', 'id = ?', [$id]);
    }

    /* ------------------------------------------------------------ penilaian */

    public function review(int $id): ?array
    {
        return Database::one(
            'SELECT r.*, p.name AS period_name, p.type AS period_type, p.start_date, p.end_date,
                    p.self_due, p.manager_due, p.status AS period_status, p.self_weight,
                    e.full_name, e.employee_code, e.email, e.role AS employee_role,
                    u.name AS unit_name, d.name AS department_name, pos.name AS position_name,
                    m.full_name AS manager_name
               FROM performance_reviews r
               JOIN review_periods p ON p.id = r.period_id
               JOIN employees e ON e.id = r.employee_id
               LEFT JOIN business_units u ON u.id = r.business_unit_id
               LEFT JOIN departments d ON d.id = r.department_id
               LEFT JOIN positions pos ON pos.id = r.position_id
               LEFT JOIN employees m ON m.id = r.manager_id
              WHERE r.id = ?',
            [$id]
        );
    }

    public function scores(int $reviewId): array
    {
        return Database::all(
            'SELECT s.*, i.code, i.name, i.description, i.category, i.sort_order
               FROM performance_scores s
               JOIN review_indicators i ON i.id = s.indicator_id
              WHERE s.review_id = ?
              ORDER BY i.sort_order, i.id',
            [$reviewId]
        );
    }

    /** Daftar penilaian (HR) dengan filter. */
    public function reviews(array $filters, int $limit = 20, int $offset = 0): array
    {
        [$where, $params] = $this->reviewFilter($filters);

        return Database::all(
            'SELECT r.*, p.name AS period_name, e.full_name, e.employee_code,
                    u.name AS unit_name, d.name AS department_name, m.full_name AS manager_name
               FROM performance_reviews r
               JOIN review_periods p ON p.id = r.period_id
               JOIN employees e ON e.id = r.employee_id
               LEFT JOIN business_units u ON u.id = r.business_unit_id
               LEFT JOIN departments d ON d.id = r.department_id
               LEFT JOIN employees m ON m.id = r.manager_id
              WHERE ' . $where . '
              ORDER BY p.start_date DESC, e.full_name
              LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $params
        );
    }

    public function countReviews(array $filters): int
    {
        [$where, $params] = $this->reviewFilter($filters);
        return (int) Database::value(
            'SELECT COUNT(*) FROM performance_reviews r
               JOIN employees e ON e.id = r.employee_id
              WHERE ' . $where,
            $params,
            0
        );
    }

    /** @return array{0:string,1:array} */
    private function reviewFilter(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['period'])) {
            $where[] = 'r.period_id = ?';
            $params[] = (int) $filters['period'];
        }
        if (!empty($filters['status']) && isset(self::STATUSES[$filters['status']])) {
            $where[] = 'r.status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['unit'])) {
            $where[] = 'r.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(e.full_name LIKE ? OR e.employee_code LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['rating']) && isset(self::RATING_LABELS[$filters['rating']])) {
            $where[] = 'r.rating = ?';
            $params[] = (string) $filters['rating'];
        }

        return [implode(' AND ', $where), $params];
    }

    public function stats(): array
    {
        $row = Database::one(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN self_submitted_at IS NOT NULL THEN 1 ELSE 0 END) AS self_done,
                SUM(CASE WHEN manager_submitted_at IS NOT NULL THEN 1 ELSE 0 END) AS manager_done,
                SUM(CASE WHEN status = 'FINAL' THEN 1 ELSE 0 END) AS finalized,
                AVG(CASE WHEN status = 'FINAL' THEN final_score END) AS avg_final
              FROM performance_reviews"
        ) ?? [];

        $distribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0];
        foreach (Database::all("SELECT rating, COUNT(*) AS total FROM performance_reviews WHERE rating IS NOT NULL GROUP BY rating") as $rowRating) {
            if (isset($distribution[$rowRating['rating']])) {
                $distribution[$rowRating['rating']] = (int) $rowRating['total'];
            }
        }

        return [
            'periods'    => (int) Database::value('SELECT COUNT(*) FROM review_periods', [], 0),
            'open'       => (int) Database::value("SELECT COUNT(*) FROM review_periods WHERE status IN ('SELF','MANAGER','CALIBRATION')", [], 0),
            'total'      => (int) ($row['total'] ?? 0),
            'self_done'  => (int) ($row['self_done'] ?? 0),
            'manager_done' => (int) ($row['manager_done'] ?? 0),
            'finalized'  => (int) ($row['finalized'] ?? 0),
            'avg_final'  => $row['avg_final'] !== null ? round((float) $row['avg_final'], 2) : null,
            'distribution' => $distribution,
        ];
    }

    /** Riwayat penilaian seorang karyawan (terbaru dulu). */
    public function employeeReviews(int $employeeId): array
    {
        return Database::all(
            'SELECT r.*, p.name AS period_name, p.type AS period_type, p.start_date, p.end_date,
                    p.self_due, p.manager_due, p.status AS period_status, p.self_weight,
                    m.full_name AS manager_name
               FROM performance_reviews r
               JOIN review_periods p ON p.id = r.period_id
               LEFT JOIN employees m ON m.id = r.manager_id
              WHERE r.employee_id = ?
              ORDER BY p.start_date DESC, r.id DESC',
            [$employeeId]
        );
    }

    public function currentReview(int $employeeId): ?array
    {
        return Database::one(
            "SELECT r.*, p.name AS period_name, p.start_date, p.end_date, p.self_due, p.manager_due,
                    p.status AS period_status, p.self_weight
               FROM performance_reviews r
               JOIN review_periods p ON p.id = r.period_id
              WHERE r.employee_id = ? AND p.status <> 'DRAFT'
              ORDER BY p.start_date DESC, r.id DESC LIMIT 1",
            [$employeeId]
        );
    }

    /** Daftar bawahan langsung seorang atasan beserta penilaian periode berjalan. */
    public function teamReviews(int $managerId, ?int $periodId = null): array
    {
        $sql = 'SELECT r.*, p.name AS period_name, e.full_name, e.employee_code,
                       u.name AS unit_name, d.name AS department_name
                  FROM performance_reviews r
                  JOIN review_periods p ON p.id = r.period_id
                  JOIN employees e ON e.id = r.employee_id
                  LEFT JOIN business_units u ON u.id = r.business_unit_id
                  LEFT JOIN departments d ON d.id = r.department_id
                 WHERE r.manager_id = ?';
        $params = [$managerId];
        if ($periodId !== null) {
            $sql .= ' AND r.period_id = ?';
            $params[] = $periodId;
        }
        $sql .= ' ORDER BY p.start_date DESC, e.full_name';

        return Database::all($sql, $params);
    }

    public function teamPendingCount(int $managerId): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM performance_reviews r
               JOIN review_periods p ON p.id = r.period_id
              WHERE r.manager_id = ? AND r.status = 'SELF_DONE'
                AND p.status IN ('SELF','MANAGER')",
            [$managerId],
            0
        );
    }

    /* ------------------------------------------------------------ submissions */

    /**
     * Simpan self assessment.
     *
     * @param array<int,int> $scores indicator_id => nilai 1..5
     * @return array{error:?string,review:?array}
     */
    public function submitSelf(int $reviewId, int $employeeId, array $scores, string $note): array
    {
        $review = $this->review($reviewId);
        if (!$review || (int) $review['employee_id'] !== $employeeId) {
            return ['error' => 'Penilaian tidak ditemukan untuk akun Anda.', 'review' => null];
        }
        if ($review['status'] !== 'PENDING') {
            return ['error' => 'Self assessment periode ini sudah dikirim.', 'review' => $review];
        }
        if (!in_array($review['period_status'], ['SELF', 'MANAGER'], true)) {
            return ['error' => 'Periode penilaian belum dibuka untuk self assessment.', 'review' => $review];
        }

        $rows = $this->scores($reviewId);
        if (!$rows) {
            return ['error' => 'Indikator penilaian belum tersedia.', 'review' => $review];
        }

        $weighted = 0.0;
        $weightSum = 0.0;
        foreach ($rows as $row) {
            $indicatorId = (int) $row['indicator_id'];
            $score = (int) ($scores[$indicatorId] ?? 0);
            if ($score < 1 || $score > 5) {
                return ['error' => 'Semua indikator wajib dinilai antara 1 sampai 5.', 'review' => $review];
            }
            $weight = (float) $row['weight'];
            Database::update('performance_scores', ['self_score' => $score], 'id = ?', [(int) $row['id']]);
            $weighted += $score * $weight;
            $weightSum += $weight;
        }

        $selfScore = $weightSum > 0 ? round($weighted / $weightSum, 2) : 0.0;
        $now = date('Y-m-d H:i:s');

        Database::update('performance_reviews', [
            'self_score'        => $selfScore,
            'self_note'         => $note !== '' ? mb_substr($note, 0, 500) : null,
            'self_submitted_at' => $now,
            'status'            => 'SELF_DONE',
            'updated_at'        => $now,
        ], 'id = ?', [$reviewId]);

        // atasan langsung mendapat notifikasi untuk menilai
        if (!empty($review['manager_id'])) {
            $this->notify(
                (int) $review['manager_id'],
                'Penilaian kinerja menunggu Anda',
                $review['full_name'] . ' telah mengirim self assessment periode ' . $review['period_name'] . '.',
                '/performance/team/' . $reviewId,
                'approval'
            );
        }

        return ['error' => null, 'review' => $this->review($reviewId)];
    }

    /**
     * Simpan penilaian atasan (atau HR bila karyawan tanpa atasan langsung).
     *
     * @param array<int,int> $scores
     * @return array{error:?string,review:?array}
     */
    public function submitManager(int $reviewId, int $managerId, bool $isHr, array $scores, string $note): array
    {
        $review = $this->review($reviewId);
        if (!$review) {
            return ['error' => 'Penilaian tidak ditemukan.', 'review' => null];
        }
        if (!$isHr && (int) $review['manager_id'] !== $managerId) {
            return ['error' => 'Anda bukan atasan langsung karyawan ini.', 'review' => $review];
        }
        if ($review['status'] === 'FINAL') {
            return ['error' => 'Penilaian sudah final dan tidak dapat diubah.', 'review' => $review];
        }
        if ($review['status'] === 'PENDING') {
            return ['error' => 'Karyawan belum mengirim self assessment.', 'review' => $review];
        }

        $rows = $this->scores($reviewId);
        if (!$rows) {
            return ['error' => 'Indikator penilaian belum tersedia.', 'review' => $review];
        }

        $weighted = 0.0;
        $weightSum = 0.0;
        foreach ($rows as $row) {
            $indicatorId = (int) $row['indicator_id'];
            $score = (int) ($scores[$indicatorId] ?? $row['manager_score'] ?? 0);
            if ($score < 1 || $score > 5) {
                return ['error' => 'Semua indikator wajib dinilai antara 1 sampai 5.', 'review' => $review];
            }
            $weight = (float) $row['weight'];
            Database::update('performance_scores', ['manager_score' => $score], 'id = ?', [(int) $row['id']]);
            $weighted += $score * $weight;
            $weightSum += $weight;
        }

        $managerScore = $weightSum > 0 ? round($weighted / $weightSum, 2) : 0.0;
        $now = date('Y-m-d H:i:s');

        Database::update('performance_reviews', [
            'manager_score'        => $managerScore,
            'manager_note'         => $note !== '' ? mb_substr($note, 0, 500) : null,
            'manager_submitted_at' => $now,
            'status'               => 'MANAGER_DONE',
            'updated_at'           => $now,
        ], 'id = ?', [$reviewId]);

        return ['error' => null, 'review' => $this->review($reviewId)];
    }

    /**
     * Tetapkan hasil akhir (kalibrasi HR).
     *
     * @return array{error:?string,review:?array}
     */
    public function finalize(int $reviewId, ?float $overrideScore, string $note, int $userId): array
    {
        $review = $this->review($reviewId);
        if (!$review) {
            return ['error' => 'Penilaian tidak ditemukan.', 'review' => null];
        }
        if ($review['status'] === 'PENDING') {
            return ['error' => 'Karyawan belum mengirim self assessment.', 'review' => $review];
        }
        if ($review['manager_score'] === null) {
            return ['error' => 'Penilaian atasan belum tersedia.', 'review' => $review];
        }

        $selfWeight = (int) ($review['self_weight'] ?? Settings::get('performance_self_weight', 30));
        $selfWeight = max(0, min(100, $selfWeight));
        $selfScore = $review['self_score'] !== null ? (float) $review['self_score'] : null;
        $managerScore = (float) $review['manager_score'];

        if ($overrideScore !== null) {
            $final = round($overrideScore, 2);
        } elseif ($selfScore === null) {
            $final = $managerScore;
        } else {
            $final = round(
                ($selfScore * $selfWeight + $managerScore * (100 - $selfWeight)) / 100,
                2
            );
        }

        $now = date('Y-m-d H:i:s');
        Database::update('performance_reviews', [
            'final_score'       => $final,
            'rating'            => self::rating($final),
            'status'            => 'FINAL',
            'calibration_note'  => $note !== '' ? mb_substr($note, 0, 500) : null,
            'calibrated_by'     => $userId,
            'calibrated_at'     => $now,
            'updated_at'        => $now,
        ], 'id = ?', [$reviewId]);

        $this->notify(
            (int) $review['employee_id'],
            'Hasil penilaian kinerja tersedia',
            'Penilaian periode ' . $review['period_name'] . ' sudah final dengan nilai ' . number_format($final, 2, ',', '.') . '.',
            '/me/performance',
            'success'
        );

        return ['error' => null, 'review' => $this->review($reviewId)];
    }

    public function acknowledge(int $reviewId, int $employeeId): array
    {
        $review = $this->review($reviewId);
        if (!$review || (int) $review['employee_id'] !== $employeeId) {
            return ['error' => 'Penilaian tidak ditemukan untuk akun Anda.'];
        }
        if ($review['status'] !== 'FINAL') {
            return ['error' => 'Penilaian belum final.'];
        }
        if ($review['acknowledged_at'] !== null) {
            return ['error' => null];
        }
        Database::update('performance_reviews', [
            'acknowledged_at' => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ], 'id = ?', [$reviewId]);

        return ['error' => null];
    }

    /** Tandai periode masuk tahap kalibrasi. */
    public function markCalibration(int $periodId): int
    {
        $this->setPeriodStatus($periodId, 'CALIBRATION');
        return (int) Database::value(
            'SELECT COUNT(*) FROM performance_reviews WHERE period_id = ? AND status = ?',
            [$periodId, 'MANAGER_DONE'],
            0
        );
    }

    public function closePeriod(int $periodId): int
    {
        $this->setPeriodStatus($periodId, 'CLOSED');
        return (int) Database::value(
            'SELECT COUNT(*) FROM performance_reviews WHERE period_id = ? AND status <> ?',
            [$periodId, 'FINAL'],
            0
        );
    }

    /* ------------------------------------------------------------ helpers */

    public static function rating(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }
        return match (true) {
            $score >= 4.5 => 'A',
            $score >= 3.5 => 'B',
            $score >= 2.5 => 'C',
            $score >= 1.5 => 'D',
            default       => 'E',
        };
    }

    public static function scoreLabel(?float $score): string
    {
        if ($score === null) {
            return '—';
        }
        return number_format($score, 2, ',', '.');
    }

    /** Karyawan yang menerima notifikasi saat periode dibuka. */
    private function notifyParticipants(int $periodId): void
    {
        $period = $this->findPeriod($periodId);
        $rows = Database::all(
            'SELECT r.employee_id, p.name FROM performance_reviews r
               JOIN review_periods p ON p.id = r.period_id
              WHERE r.period_id = ? AND r.status = ?',
            [$periodId, 'PENDING']
        );
        foreach ($rows as $row) {
            $this->notify(
                (int) $row['employee_id'],
                'Self assessment penilaian kinerja dibuka',
                'Periode ' . ($period['name'] ?? $row['name']) . ' telah dibuka. Lengkapi self assessment Anda.',
                '/me/performance',
                'info'
            );
        }
    }

    private function notify(int $employeeId, string $title, string $message, string $link, string $type = 'info'): void
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
