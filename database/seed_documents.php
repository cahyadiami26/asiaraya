<?php
declare(strict_types=1);

use App\Core\Database;

/**
 * Data demo Batch 19 — dokumen karyawan & riwayat jabatan.
 *
 * Dipakai oleh `database/seed.php` (instalasi baru) dan boleh dipanggil ulang
 * dengan `$reset = true` saat menyiapkan basis data pratinjau.
 *
 * Berkas dokumen demo ditulis sebagai PDF satu halaman sederhana ke
 * `storage/uploads/documents/<id karyawan>/` agar tautan pratinjau benar-benar
 * dapat dibuka tanpa menyertakan berkas biner di repositori.
 *
 * @return array<int,string> baris log
 */
return static function (bool $reset = false): array {
    $log = [];

    if (!Database::tableExists('employee_documents') || !Database::tableExists('employee_job_history')) {
        return ['Batch 19 dilewati: tabel dokumen/riwayat belum tersedia.'];
    }

    if ($reset) {
        Database::delete('employee_documents', '1 = 1');
        Database::delete('employee_job_history', '1 = 1');
    } elseif ((int) Database::value('SELECT COUNT(*) FROM employee_documents') > 0) {
        return ['Data demo dokumen sudah ada — seeding Batch 19 dilewati.'];
    }

    $employees = Database::all(
        "SELECT e.id, e.employee_code, e.full_name, e.npwp, e.join_date, e.contract_start, e.contract_end,
                e.employment_status, e.basic_salary, e.business_unit_id, e.department_id, e.position_id,
                bu.name AS unit_name, d.name AS department_name, p.name AS position_name
           FROM employees e
           LEFT JOIN business_units bu ON bu.id = e.business_unit_id
           LEFT JOIN departments d ON d.id = e.department_id
           LEFT JOIN positions p ON p.id = e.position_id
          WHERE e.flag = 'ACTIVE'
          ORDER BY e.id
          LIMIT 44"
    );

    if (!$employees) {
        return ['Batch 19 dilewati: belum ada karyawan aktif.'];
    }

    $hrId = (int) Database::value("SELECT id FROM employees WHERE role = 'HR' ORDER BY id LIMIT 1", [], 0);
    $now = date('Y-m-d H:i:s');
    $today = strtotime(date('Y-m-d'));

    /** PDF satu halaman sederhana tanpa pustaka eksternal. */
    $placeholderPdf = static function (string $title, array $lines): string {
        $escape = static fn (string $text): string => str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $text
        );

        $content = "BT /F1 17 Tf 56 780 Td (" . $escape($title) . ") Tj ET\n";
        $y = 745;
        foreach ($lines as $line) {
            $content .= "BT /F1 10.5 Tf 56 {$y} Td (" . $escape($line) . ") Tj ET\n";
            $y -= 17;
        }
        $content .= "BT /F1 9 Tf 56 90 Td (" . $escape('Berkas contoh untuk keperluan demo HRIS - bukan dokumen resmi.') . ") Tj ET\n";

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            4 => "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n" . '0 ' . (count($objects) + 1) . "\n" . "0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer' . "\n" . '<< /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>' . "\n"
            . 'startxref' . "\n" . $xref . "\n" . '%%EOF';

        return $pdf;
    };

    /** Tulis berkas demo, kembalikan metadata untuk kolom dokumen. */
    $writePdf = static function (string $relative, string $title, array $lines) use ($placeholderPdf): array {
        $absolute = rtrim((string) config('upload_path'), '/') . '/' . $relative;
        $directory = dirname($absolute);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
        $bytes = $placeholderPdf($title, $lines);
        @file_put_contents($absolute, $bytes);

        return [
            'file_path' => $relative,
            'file_name' => basename($relative),
            'file_size' => strlen($bytes),
            'mime_type' => 'application/pdf',
        ];
    };

    /* ------------------------------------------------- riwayat jabatan (demo) */

    $historyCount = 0;
    $initialPositions = ['Staff Operasional', 'Staff Administrasi', 'Junior Staff', 'Asisten Staff'];
    foreach (array_slice($employees, 0, 9) as $index => $employee) {
        $join = (string) ($employee['join_date'] ?: date('Y-m-d', strtotime('-3 years')));
        $joinTs = strtotime($join);
        $salary = (float) $employee['basic_salary'];
        $base = max(0, (int) round($salary * 0.82));
        $rows = [];

        $rows[] = [
            'change_type'       => 'INITIAL',
            'effective_date'    => $join,
            'end_date'          => null,
            'business_unit_id'  => (int) $employee['business_unit_id'],
            'department_id'     => (int) $employee['department_id'],
            'position_id'       => null,
            'unit_name'         => (string) ($employee['unit_name'] ?? ''),
            'department_name'   => (string) ($employee['department_name'] ?? ''),
            'position_name'     => $initialPositions[$index % count($initialPositions)],
            'employment_status' => 'PKWT',
            'basic_salary'      => $base,
            'previous_salary'   => null,
            'sk_number'         => 'SK/' . date('Y', $joinTs) . '/HR-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            'note'              => 'Pengangkatan pertama kali sebagai karyawan kontrak.',
        ];

        $promotionAt = strtotime('+9 months', $joinTs);
        if ($promotionAt < $today && $index % 2 === 0) {
            $rows[] = [
                'change_type'       => 'PROMOTION',
                'effective_date'    => date('Y-m-d', $promotionAt),
                'end_date'          => null,
                'business_unit_id'  => (int) $employee['business_unit_id'],
                'department_id'     => (int) $employee['department_id'],
                'position_id'       => (int) $employee['position_id'],
                'unit_name'         => (string) ($employee['unit_name'] ?? ''),
                'department_name'   => (string) ($employee['department_name'] ?? ''),
                'position_name'     => (string) ($employee['position_name'] ?? ''),
                'employment_status' => (string) $employee['employment_status'],
                'basic_salary'      => $salary,
                'previous_salary'   => $base,
                'sk_number'         => 'SK/' . date('Y', $promotionAt) . '/PROMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'note'              => 'Promosi berdasarkan hasil penilaian kinerja tahunan.',
            ];
        } elseif ($promotionAt < $today) {
            $rows[] = [
                'change_type'       => 'SALARY',
                'effective_date'    => date('Y-m-d', $promotionAt),
                'end_date'          => null,
                'business_unit_id'  => (int) $employee['business_unit_id'],
                'department_id'     => (int) $employee['department_id'],
                'position_id'       => (int) $employee['position_id'],
                'unit_name'         => (string) ($employee['unit_name'] ?? ''),
                'department_name'   => (string) ($employee['department_name'] ?? ''),
                'position_name'     => (string) ($employee['position_name'] ?? ''),
                'employment_status' => (string) $employee['employment_status'],
                'basic_salary'      => $salary,
                'previous_salary'   => $base,
                'sk_number'         => 'SK/' . date('Y', $promotionAt) . '/GAJI-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'note'              => 'Penyesuaian gaji pokok berkala.',
            ];
        }

        // Mutasi antar departemen dalam unit yang sama (sebelum promosi).
        $altDept = Database::one(
            'SELECT id, name FROM departments WHERE business_unit_id = ? AND id <> ? ORDER BY id LIMIT 1',
            [(int) $employee['business_unit_id'], (int) $employee['department_id']]
        );
        $mutationAt = strtotime('+5 months', $joinTs);
        if ($altDept && $mutationAt < $today && $index % 3 === 1) {
            $rows[] = [
                'change_type'       => 'MUTATION',
                'effective_date'    => date('Y-m-d', $mutationAt),
                'end_date'          => null,
                'business_unit_id'  => (int) $employee['business_unit_id'],
                'department_id'     => (int) $altDept['id'],
                'position_id'       => (int) $employee['position_id'],
                'unit_name'         => (string) ($employee['unit_name'] ?? ''),
                'department_name'   => (string) $altDept['name'],
                'position_name'     => $initialPositions[$index % count($initialPositions)],
                'employment_status' => 'PKWT',
                'basic_salary'      => $base,
                'previous_salary'   => null,
                'sk_number'         => 'SK/' . date('Y', $mutationAt) . '/MUTASI-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'note'              => 'Mutasi antar departemen untuk kebutuhan operasional.',
            ];
        }

        $contractEnd = (string) ($employee['contract_end'] ?? '');
        if ($contractEnd !== '' && strtotime($contractEnd) > $today && !empty($employee['contract_start'])) {
            $rows[] = [
                'change_type'       => 'CONTRACT',
                'effective_date'    => (string) $employee['contract_start'],
                'end_date'          => $contractEnd,
                'business_unit_id'  => (int) $employee['business_unit_id'],
                'department_id'     => (int) $employee['department_id'],
                'position_id'       => (int) $employee['position_id'],
                'unit_name'         => (string) ($employee['unit_name'] ?? ''),
                'department_name'   => (string) ($employee['department_name'] ?? ''),
                'position_name'     => (string) ($employee['position_name'] ?? ''),
                'employment_status' => 'PKWT',
                'basic_salary'      => $salary,
                'previous_salary'   => null,
                'sk_number'         => 'PKWT/' . date('Y', strtotime((string) $employee['contract_start'])) . '/' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'note'              => 'Perpanjangan kontrak kerja jangka waktu tertentu.',
            ];
        }

        foreach ($rows as $row) {
            if (strtotime($row['effective_date']) > $today) {
                continue;
            }
            Database::insert('employee_job_history', $row + [
                'employee_id' => (int) $employee['id'],
                'created_by'  => $hrId ?: null,
                'created_at'  => $now,
            ]);
            $historyCount++;
        }
    }

    if ($historyCount > 0) {
        $log[] = 'Batch 19: ' . $historyCount . ' catatan riwayat jabatan (pengangkatan, promosi, penyesuaian gaji, kontrak) dibuat.';
    }

    /* ---------------------------------------------------- dokumen (demo) */

    $documentCount = 0;
    $certExpiry = [12, 27, 41, 55, 9];
    foreach ($employees as $index => $employee) {
        if ($index >= 34) {
            break;
        }

        $employeeId = (int) $employee['id'];
        $name = (string) $employee['full_name'];
        $code = (string) $employee['employee_code'];
        $contractEnd = (string) ($employee['contract_end'] ?? '');
        $docs = [
            [
                'doc_type'        => 'KTP',
                'title'           => 'KTP — ' . $name,
                'document_number' => '3174' . str_pad((string) (100000000000 + $employeeId * 37), 12, '0', STR_PAD_LEFT),
                'issued_at'       => date('Y-m-d', strtotime('-' . (4 + $index % 6) . ' years')),
                'expires_at'      => null,
                'status'          => $index % 4 === 0 ? 'UNVERIFIED' : 'VERIFIED',
                'is_confidential' => 0,
                'note'            => '',
            ],
            [
                'doc_type'        => 'NPWP',
                'title'           => 'NPWP — ' . $name,
                'document_number' => (string) ($employee['npwp'] ?? ''),
                'issued_at'       => date('Y-m-d', strtotime('-' . (3 + $index % 5) . ' years')),
                'expires_at'      => null,
                'status'          => $index % 3 === 0 ? 'UNVERIFIED' : 'VERIFIED',
                'is_confidential' => 0,
                'note'            => '',
            ],
            [
                'doc_type'        => 'IJAZAH',
                'title'           => 'Ijazah S1 — ' . $name,
                'document_number' => 'IJ/' . date('Y') . '/' . str_pad((string) $employeeId, 4, '0', STR_PAD_LEFT),
                'issued_at'       => date('Y-m-d', strtotime('-' . (7 + $index % 8) . ' years')),
                'expires_at'      => null,
                'status'          => 'VERIFIED',
                'is_confidential' => 0,
                'note'            => 'Salinan legalisir diterima HR.',
            ],
        ];

        if ($contractEnd !== '') {
            $docs[] = [
                'doc_type'        => 'KONTRAK',
                'title'           => 'Kontrak Kerja — ' . $name,
                'document_number' => 'PKWT/' . $code . '/' . date('Y', strtotime((string) ($employee['contract_start'] ?: 'now'))),
                'issued_at'       => (string) ($employee['contract_start'] ?: null),
                'expires_at'      => $contractEnd,
                'status'          => 'VERIFIED',
                'is_confidential' => 0,
                'note'            => '',
            ];
        }

        if ($index % 3 === 0) {
            $docs[] = [
                'doc_type'        => 'SERTIFIKAT',
                'title'           => 'Sertifikat Pelatihan K3 — ' . $name,
                'document_number' => 'SERT/K3/' . date('Y') . '/' . str_pad((string) $employeeId, 3, '0', STR_PAD_LEFT),
                'issued_at'       => date('Y-m-d', strtotime('-' . (24 + $index) . ' months')),
                'expires_at'      => date('Y-m-d', strtotime('+' . $certExpiry[$index % count($certExpiry)] . ' days')),
                'status'          => 'VERIFIED',
                'is_confidential' => 0,
                'note'            => 'Masa berlaku 3 tahun sejak tanggal terbit.',
            ];
        }

        if ($index % 5 === 0) {
            $docs[] = [
                'doc_type'        => 'LAINNYA',
                'title'           => 'Surat Peringatan I — ' . $name,
                'document_number' => 'SP/HR/' . date('Y') . '/' . str_pad((string) $employeeId, 3, '0', STR_PAD_LEFT),
                'issued_at'       => date('Y-m-d', strtotime('-' . (5 + $index) . ' months')),
                'expires_at'      => date('Y-m-d', strtotime('+' . (6 - $index % 3) . ' months')),
                'status'          => 'VERIFIED',
                'is_confidential' => 1,
                'note'            => 'Disimpan HR — tidak ditampilkan pada portal karyawan.',
            ];
        }

        if ($index % 6 === 2) {
            $docs[] = [
                'doc_type'        => 'BPJS_KES',
                'title'           => 'Kartu BPJS Kesehatan — ' . $name,
                'document_number' => '0001' . str_pad((string) (100000000 + $employeeId * 13), 9, '0', STR_PAD_LEFT),
                'issued_at'       => date('Y-m-d', strtotime('-2 years')),
                'expires_at'      => null,
                'status'          => 'REJECTED',
                'is_confidential' => 0,
                'note'            => 'Hasil pindai buram, mohon unggah ulang dengan resolusi lebih baik.',
            ];
        }

        foreach ($docs as $doc) {
            $slug = strtolower((string) $doc['doc_type']) . '-' . $employeeId;
            $relative = 'documents/' . $employeeId . '/seed-' . $slug . '.pdf';
            $file = $writePdf($relative, (string) $doc['title'], [
                'Nama karyawan : ' . $name,
                'NIK           : ' . $code,
                'Jenis berkas  : ' . $doc['doc_type'],
                'Nomor         : ' . ((string) $doc['document_number'] !== '' ? $doc['document_number'] : '-'),
                'Tanggal terbit: ' . ($doc['issued_at'] ?: '-'),
                'Berlaku sampai: ' . ($doc['expires_at'] ?: 'tanpa masa berlaku'),
            ]);

            Database::insert('employee_documents', $file + [
                'employee_id'     => $employeeId,
                'doc_type'        => $doc['doc_type'],
                'title'           => $doc['title'],
                'document_number' => $doc['document_number'],
                'issued_at'       => $doc['issued_at'],
                'expires_at'      => $doc['expires_at'],
                'status'          => $doc['status'],
                'is_confidential' => (int) $doc['is_confidential'],
                'note'            => $doc['note'],
                'uploaded_by'     => $hrId ?: null,
                'verified_by'     => $doc['status'] === 'VERIFIED' ? ($hrId ?: null) : null,
                'verified_at'     => $doc['status'] === 'VERIFIED' ? date('Y-m-d H:i:s', strtotime('-' . ($index + 2) . ' days')) : null,
                'created_at'      => date('Y-m-d H:i:s', strtotime('-' . ($index + 3) . ' days')),
                'updated_at'      => $now,
            ]);
            $documentCount++;
        }
    }

    if ($documentCount > 0) {
        $log[] = 'Batch 19: ' . $documentCount . ' dokumen karyawan demo (KTP, NPWP, ijazah, kontrak, sertifikat, BPJS, surat peringatan) dibuat.';
    }

    return $log;
};
