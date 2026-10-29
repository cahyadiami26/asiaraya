<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Dokumen karyawan (Batch 19).
 *
 * Berkas disimpan di storage/uploads/documents/{employee_id}/ — di luar
 * document root — dan hanya dapat diunduh lewat route ber-otorisasi.
 */
final class DocumentModel
{
    /** Jenis dokumen + label + apakah lazim punya masa berlaku. */
    public const TYPES = [
        'KTP'        => ['label' => 'KTP / Identitas', 'expiry' => false, 'icon' => 'user'],
        'NPWP'       => ['label' => 'NPWP', 'expiry' => false, 'icon' => 'file'],
        'KK'         => ['label' => 'Kartu Keluarga', 'expiry' => false, 'icon' => 'users'],
        'IJAZAH'     => ['label' => 'Ijazah / Transkrip', 'expiry' => false, 'icon' => 'award'],
        'SERTIFIKAT' => ['label' => 'Sertifikat / Lisensi', 'expiry' => true, 'icon' => 'award'],
        'KONTRAK'    => ['label' => 'Kontrak Kerja', 'expiry' => true, 'icon' => 'file'],
        'SK'         => ['label' => 'Surat Keputusan', 'expiry' => false, 'icon' => 'edit'],
        'BPJS_KES'   => ['label' => 'BPJS Kesehatan', 'expiry' => false, 'icon' => 'shield'],
        'BPJS_TK'    => ['label' => 'BPJS Ketenagakerjaan', 'expiry' => false, 'icon' => 'shield'],
        'SIM'        => ['label' => 'SIM / Surat Izin', 'expiry' => true, 'icon' => 'file'],
        'PASPOR'     => ['label' => 'Paspor', 'expiry' => true, 'icon' => 'file'],
        'FOTO'       => ['label' => 'Foto Formal', 'expiry' => false, 'icon' => 'camera'],
        'LAINNYA'    => ['label' => 'Dokumen Lainnya', 'expiry' => false, 'icon' => 'file'],
    ];

    public const STATUSES = [
        'UNVERIFIED' => ['label' => 'Belum diverifikasi', 'tone' => 'amber'],
        'VERIFIED'   => ['label' => 'Terverifikasi', 'tone' => 'teal'],
        'REJECTED'   => ['label' => 'Ditolak', 'tone' => 'rose'],
    ];

    /** Ambang "akan kedaluwarsa" (hari). */
    public const EXPIRING_DAYS = 60;

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type]['label'] ?? ucfirst(strtolower($type));
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status]['label'] ?? $status;
    }

    public static function statusTone(string $status): string
    {
        return self::STATUSES[$status]['tone'] ?? 'slate';
    }

    /** Label masa berlaku sebuah dokumen. */
    public static function expiryState(?string $expiresAt): array
    {
        if (!$expiresAt) {
            return ['state' => 'none', 'label' => 'Tanpa masa berlaku', 'days' => null];
        }
        $days = (int) floor((strtotime($expiresAt . ' 23:59:59') - strtotime(date('Y-m-d'))) / 86400);
        if ($days < 0) {
            return ['state' => 'expired', 'label' => 'Kedaluwarsa', 'days' => $days];
        }
        if ($days <= self::EXPIRING_DAYS) {
            return ['state' => 'soon', 'label' => 'Berakhir dalam ' . $days . ' hari', 'days' => $days];
        }
        return ['state' => 'safe', 'label' => 'Berlaku', 'days' => $days];
    }

    /* ================================================================ filter */

    /** @return array<string,mixed> */
    public function filtersFromRequest(): array
    {
        $type = (string) ($_GET['type'] ?? '');
        $status = (string) ($_GET['status'] ?? '');
        $expiry = (string) ($_GET['expiry'] ?? '');

        return [
            'q'        => trim((string) ($_GET['q'] ?? '')),
            'type'     => isset(self::TYPES[$type]) ? $type : '',
            'status'   => isset(self::STATUSES[$status]) ? $status : '',
            'expiry'   => in_array($expiry, ['expiring', 'expired', 'none'], true) ? $expiry : '',
            'employee' => (int) ($_GET['employee'] ?? 0),
            'unit'     => (int) ($_GET['unit'] ?? 0),
        ];
    }

    /** @return array{0:string,1:array} */
    private function where(array $filters, string $alias = 'd'): array
    {
        $clauses = [];
        $params = [];
        $today = date('Y-m-d');
        $limit = date('Y-m-d', strtotime('+' . self::EXPIRING_DAYS . ' days'));

        if (($filters['q'] ?? '') !== '') {
            $clauses[] = '(d.title LIKE ? OR d.document_number LIKE ? OR e.full_name LIKE ? OR e.employee_code LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (($filters['type'] ?? '') !== '') {
            $clauses[] = 'd.doc_type = ?';
            $params[] = $filters['type'];
        }
        if (($filters['status'] ?? '') !== '') {
            $clauses[] = 'd.status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['employee'] ?? 0) > 0) {
            $clauses[] = 'd.employee_id = ?';
            $params[] = (int) $filters['employee'];
        }
        if (($filters['unit'] ?? 0) > 0) {
            $clauses[] = 'e.business_unit_id = ?';
            $params[] = (int) $filters['unit'];
        }
        if (($filters['expiry'] ?? '') === 'expiring') {
            $clauses[] = 'd.expires_at IS NOT NULL AND d.expires_at >= ? AND d.expires_at <= ?';
            array_push($params, $today, $limit);
        } elseif (($filters['expiry'] ?? '') === 'expired') {
            $clauses[] = 'd.expires_at IS NOT NULL AND d.expires_at < ?';
            $params[] = $today;
        } elseif (($filters['expiry'] ?? '') === 'none') {
            $clauses[] = 'd.expires_at IS NULL';
        }

        return [$clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    public function count(array $filters): int
    {
        [$where, $params] = $this->where($filters);
        return (int) Database::value(
            'SELECT COUNT(*) FROM employee_documents d
               JOIN employees e ON e.id = d.employee_id' . $where,
            $params,
            0
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function paginate(array $filters, int $limit = 15, int $offset = 0): array
    {
        [$where, $params] = $this->where($filters);
        $order = ($filters['expiry'] ?? '') !== ''
            ? 'ORDER BY d.expires_at ASC, d.id DESC'
            : 'ORDER BY d.id DESC';

        return Database::all(
            'SELECT d.*, e.full_name, e.employee_code, e.photo, e.flag, bu.name AS unit_name,
                    u.full_name AS uploader_name, v.full_name AS verifier_name
               FROM employee_documents d
               JOIN employees e ON e.id = d.employee_id
               LEFT JOIN business_units bu ON bu.id = e.business_unit_id
               LEFT JOIN employees u ON u.id = d.uploaded_by
               LEFT JOIN employees v ON v.id = d.verified_by' . $where . '
              ' . $order . '
              LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $params
        );
    }

    public function find(int $id): ?array
    {
        return Database::one(
            'SELECT d.*, e.full_name, e.employee_code, e.photo, e.email,
                    bu.name AS unit_name, dep.name AS department_name,
                    u.full_name AS uploader_name, v.full_name AS verifier_name
               FROM employee_documents d
               JOIN employees e ON e.id = d.employee_id
               LEFT JOIN business_units bu ON bu.id = e.business_unit_id
               LEFT JOIN departments dep ON dep.id = e.department_id
               LEFT JOIN employees u ON u.id = d.uploaded_by
               LEFT JOIN employees v ON v.id = d.verified_by
              WHERE d.id = ?',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function forEmployee(int $employeeId, bool $includeConfidential = true, int $limit = 100): array
    {
        $sql = 'SELECT d.*, v.full_name AS verifier_name
                  FROM employee_documents d
                  LEFT JOIN employees v ON v.id = d.verified_by
                 WHERE d.employee_id = ?';
        $params = [$employeeId];
        if (!$includeConfidential) {
            $sql .= ' AND d.is_confidential = 0';
        }
        $sql .= ' ORDER BY d.expires_at IS NULL, d.expires_at ASC, d.id DESC LIMIT ' . max(1, $limit);

        return Database::all($sql, $params);
    }

    /* ================================================================ statistik */

    /** @return array<string,mixed> */
    public function stats(): array
    {
        $today = date('Y-m-d');
        $limit = date('Y-m-d', strtotime('+' . self::EXPIRING_DAYS . ' days'));

        $row = Database::one(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'VERIFIED' THEN 1 ELSE 0 END) AS verified,
                    SUM(CASE WHEN status = 'UNVERIFIED' THEN 1 ELSE 0 END) AS unverified,
                    SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) AS rejected,
                    SUM(CASE WHEN expires_at IS NOT NULL AND expires_at >= ? AND expires_at <= ? THEN 1 ELSE 0 END) AS expiring,
                    SUM(CASE WHEN expires_at IS NOT NULL AND expires_at < ? THEN 1 ELSE 0 END) AS expired,
                    SUM(CASE WHEN is_confidential = 1 THEN 1 ELSE 0 END) AS confidential
               FROM employee_documents",
            [$today, $limit, $today]
        ) ?? [];

        $employeesWithDocs = (int) Database::value('SELECT COUNT(DISTINCT employee_id) FROM employee_documents', [], 0);
        $activeEmployees = (int) Database::value("SELECT COUNT(*) FROM employees WHERE flag = 'ACTIVE'", [], 0);

        return [
            'total'       => (int) ($row['total'] ?? 0),
            'verified'    => (int) ($row['verified'] ?? 0),
            'unverified'  => (int) ($row['unverified'] ?? 0),
            'rejected'    => (int) ($row['rejected'] ?? 0),
            'expiring'    => (int) ($row['expiring'] ?? 0),
            'expired'     => (int) ($row['expired'] ?? 0),
            'confidential' => (int) ($row['confidential'] ?? 0),
            'employees'   => $employeesWithDocs,
            'coverage'    => $activeEmployees > 0 ? round($employeesWithDocs / $activeEmployees * 100, 1) : 0.0,
            'active_employees' => $activeEmployees,
        ];
    }

    /** Distribusi per jenis dokumen (untuk panel ringkasan). */
    public function byType(): array
    {
        $rows = Database::all(
            'SELECT doc_type, COUNT(*) AS total FROM employee_documents GROUP BY doc_type ORDER BY total DESC'
        );
        $total = array_sum(array_map(static fn (array $row): int => (int) $row['total'], $rows)) ?: 1;

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'type'  => (string) $row['doc_type'],
                'label' => self::typeLabel((string) $row['doc_type']),
                'total' => (int) $row['total'],
                'pct'   => (int) round((int) $row['total'] / $total * 100),
            ];
        }
        return $result;
    }

    /** Dokumen yang mendekati/melewati masa berlaku. */
    public function expiringSoon(int $limit = 6): array
    {
        $today = date('Y-m-d');
        $limitDate = date('Y-m-d', strtotime('+' . self::EXPIRING_DAYS . ' days'));

        return Database::all(
            'SELECT d.*, e.full_name, e.employee_code
               FROM employee_documents d
               JOIN employees e ON e.id = d.employee_id
              WHERE d.expires_at IS NOT NULL
                AND d.expires_at <= ?
                AND e.flag = \'ACTIVE\'
              ORDER BY d.expires_at ASC
              LIMIT ' . max(1, $limit),
            [$limitDate]
        );
    }

    /** Ringkasan dokumen untuk satu karyawan. */
    public function summaryFor(int $employeeId): array
    {
        $row = Database::one(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'UNVERIFIED' THEN 1 ELSE 0 END) AS unverified,
                    SUM(CASE WHEN expires_at IS NOT NULL AND expires_at < ? THEN 1 ELSE 0 END) AS expired,
                    SUM(CASE WHEN expires_at IS NOT NULL AND expires_at >= ? AND expires_at <= ? THEN 1 ELSE 0 END) AS expiring
               FROM employee_documents WHERE employee_id = ?",
            [date('Y-m-d'), date('Y-m-d'), date('Y-m-d', strtotime('+' . self::EXPIRING_DAYS . ' days')), $employeeId]
        ) ?? [];

        return [
            'total'      => (int) ($row['total'] ?? 0),
            'unverified' => (int) ($row['unverified'] ?? 0),
            'expired'    => (int) ($row['expired'] ?? 0),
            'expiring'   => (int) ($row['expiring'] ?? 0),
        ];
    }

    /* ================================================================== mutasi */

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        return Database::insert('employee_documents', [
            'employee_id'     => (int) $data['employee_id'],
            'doc_type'        => (string) $data['doc_type'],
            'title'           => (string) $data['title'],
            'document_number' => $data['document_number'] ?: null,
            'file_path'       => (string) $data['file_path'],
            'file_name'       => (string) ($data['file_name'] ?? ''),
            'file_size'       => (int) ($data['file_size'] ?? 0),
            'mime_type'       => $data['mime_type'] ?: null,
            'issued_at'       => $data['issued_at'] ?: null,
            'expires_at'      => $data['expires_at'] ?: null,
            'status'          => (string) ($data['status'] ?? 'UNVERIFIED'),
            'is_confidential' => !empty($data['is_confidential']) ? 1 : 0,
            'note'            => $data['note'] ?: null,
            'uploaded_by'     => $data['uploaded_by'] ?? null,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string,mixed> $data */
    public function updateMeta(int $id, array $data): void
    {
        Database::update('employee_documents', [
            'doc_type'        => (string) $data['doc_type'],
            'title'           => (string) $data['title'],
            'document_number' => $data['document_number'] ?: null,
            'issued_at'       => $data['issued_at'] ?: null,
            'expires_at'      => $data['expires_at'] ?: null,
            'is_confidential' => !empty($data['is_confidential']) ? 1 : 0,
            'note'            => $data['note'] ?: null,
            'updated_at'      => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    /** Ganti berkas (versi baru) pada baris yang sama. */
    public function replaceFile(int $id, string $path, string $name, int $size, ?string $mime): void
    {
        Database::update('employee_documents', [
            'file_path'  => $path,
            'file_name'  => $name,
            'file_size'  => $size,
            'mime_type'  => $mime,
            'status'     => 'UNVERIFIED',
            'verified_by' => null,
            'verified_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    public function verify(int $id, int $by, string $status, ?string $note = null): void
    {
        Database::update('employee_documents', [
            'status'      => $status,
            'verified_by' => $by,
            'verified_at' => date('Y-m-d H:i:s'),
            'note'        => $note !== null && $note !== '' ? mb_substr($note, 0, 255) : null,
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    /** Hapus baris; mengembalikan path berkas agar bisa di-unlink. */
    public function delete(int $id): ?string
    {
        $row = Database::one('SELECT file_path FROM employee_documents WHERE id = ?', [$id]);
        Database::delete('employee_documents', 'id = ?', [$id]);
        return $row['file_path'] ?? null;
    }

    /** Hapus seluruh dokumen seorang karyawan (dipakai saat karyawan dihapus/reset). */
    public function deleteForEmployee(int $employeeId): array
    {
        $paths = array_map(
            static fn (array $row): string => (string) $row['file_path'],
            Database::all('SELECT file_path FROM employee_documents WHERE employee_id = ?', [$employeeId])
        );
        Database::delete('employee_documents', 'employee_id = ?', [$employeeId]);
        return $paths;
    }

    /** Pilihan karyawan untuk form unggah. */
    public function employeeOptions(): array
    {
        return Database::all(
            "SELECT e.id, e.full_name, e.employee_code, e.flag, bu.name AS unit_name
               FROM employees e
               LEFT JOIN business_units bu ON bu.id = e.business_unit_id
              ORDER BY e.flag = 'ACTIVE' DESC, e.full_name"
        );
    }

    /** Dokumen belum diverifikasi untuk badge navigasi. */
    public function pendingCount(): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM employee_documents WHERE status = 'UNVERIFIED'",
            [],
            0
        );
    }
}
