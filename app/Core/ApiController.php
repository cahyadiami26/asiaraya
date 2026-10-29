<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Basis controller REST API /api/v1.
 *
 * Format balasan (selalu JSON, kecuali unduhan PDF):
 *   sukses : {"success": true,  "data": ..., "meta": {...}}
 *   gagal  : {"success": false, "message": "...", "errors": {...}, "meta": {...}}
 *
 * Setiap request mendapat X-Request-Id supaya log aplikasi Android bisa
 * dicocokkan dengan log server.
 */
abstract class ApiController
{
    protected ?array $user = null;

    /** True bila request memakai bearer token (bukan URL bertanda tangan). */
    protected bool $tokenAuthenticated = false;

    public function __construct()
    {
        header('X-Request-Id: ' . ApiRequest::requestId());
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, max-age=0');

        // Jangan pernah membocorkan stack trace ke aplikasi mobile.
        set_exception_handler(static function (\Throwable $e): void {
            error_log('[api] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server. Coba beberapa saat lagi.',
                'errors'  => [],
                'meta'    => [
                    'request_id'  => ApiRequest::requestId(),
                    'server_time' => date('c'),
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        });
    }

    /* ---------------------------------------------------------------- auth */

    /** Wajib token valid; gagal → 401 JSON. */
    protected function requireAuth(): array
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $user = ApiAuth::authenticate();
        if ($user === null) {
            $this->fail(
                401,
                'Sesi API tidak valid atau kedaluwarsa. Silakan masuk kembali.',
                [],
                'UNAUTHENTICATED'
            );
        }

        $this->tokenAuthenticated = true;
        return $this->user = $user;
    }

    /**
     * Aktor untuk endpoint unduhan: bearer token, atau URL bertanda tangan
     * (dipakai DownloadManager Android yang tidak bisa mengirim header).
     * Mengembalikan employee id aktor, atau gagal 401.
     */
    protected function downloadActor(string $resource, int|string $id): int
    {
        $user = ApiAuth::authenticate();
        if ($user !== null) {
            $this->user = $user;
            $this->tokenAuthenticated = true;
            return (int) $user['id'];
        }

        $employeeId = (int) ApiRequest::query('employee', 0);
        $expires = ApiRequest::query('expires');
        $signature = ApiRequest::query('signature');

        if ($employeeId > 0 && $expires !== null && $signature !== null
            && ApiAuth::verifySignature($resource, $id, $employeeId, (string) $expires, (string) $signature)) {
            $employee = Auth::findById($employeeId);
            if ($employee === null) {
                $this->fail(401, 'Akun tidak aktif.', [], 'UNAUTHENTICATED');
            }
            Auth::setUser($employee);
            $this->user = $employee;
            $this->tokenAuthenticated = false;
            return $employeeId;
        }

        $this->fail(401, 'Unduhan memerlukan token yang valid atau tautan bertanda tangan.', [], 'UNAUTHENTICATED');
    }

    /* ------------------------------------------------------------ responses */

    protected function respond(mixed $data, int $status = 200, array $meta = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => $status < 400,
            'data'    => $data,
            'meta'    => $meta + $this->baseMeta(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    protected function ok(mixed $data, array $meta = []): never
    {
        $this->respond($data, 200, $meta);
    }

    protected function created(mixed $data, array $meta = []): never
    {
        $this->respond($data, 201, $meta);
    }

    protected function fail(int $status, string $message, array $errors = [], string $code = 'ERROR'): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'code'    => $code,
            'message' => $message,
            'errors'  => $errors,
            'meta'    => $this->baseMeta(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    /** Balasan daftar berhalaman dengan meta.pagination. */
    protected function paginated(array $items, int $total, int $page, int $perPage, array $meta = []): never
    {
        $pages = $perPage > 0 ? (int) ceil($total / $perPage) : 0;

        $this->respond($items, 200, $meta + [
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => $pages,
                'has_more'    => $page < $pages,
            ],
        ]);
    }

    /** Kirim PDF yang dirender dompdf langsung ke klien. */
    protected function pdfResponse(string $html, string $filename, bool $download = true): never
    {
        try {
            $pdf = Pdf::render($html);
        } catch (\Throwable $e) {
            error_log('[api] pdf render failed: ' . $e->getMessage());
            $this->fail(500, 'Dokumen PDF gagal dibuat. Coba lagi beberapa saat.', [], 'PDF_ERROR');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdf));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . addslashes($filename) . '"');
        header('Cache-Control: private, no-store, max-age=0');
        echo $pdf;
        exit;
    }

    /* ------------------------------------------------------------- helpers */

    protected function baseMeta(): array
    {
        return [
            'request_id'  => ApiRequest::requestId(),
            'server_time' => date('c'),
            'version'     => APP_VERSION,
        ];
    }

    protected function body(): array
    {
        return ApiRequest::body();
    }

    /**
     * Validasi payload JSON; gagal → 422 dengan peta field → pesan.
     *
     * @param callable(Validator):void $rules
     */
    protected function validate(array $data, callable $rules): array
    {
        $validator = new Validator($data);
        $rules($validator);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->fail(422, (string) ($validator->firstError() ?? 'Data tidak valid.'), $errors, 'VALIDATION_ERROR');
        }

        return $data;
    }

    /** Format tanggal Y-m-d; null bila tidak valid. */
    protected function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $time = strtotime($value);
        return $time === false ? null : date('Y-m-d', $time);
    }

    /** Parameter bulan YYYY-MM (default bulan berjalan). */
    protected function monthParam(): string
    {
        $month = (string) ApiRequest::query('month', '');
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : date('Y-m');
    }

    /** Parameter tahun 4 digit (default tahun berjalan). */
    protected function yearParam(int $default = 0): int
    {
        $year = (int) ApiRequest::query('year', 0);
        $default = $default > 0 ? $default : (int) date('Y');
        return $year >= 2000 && $year <= (int) date('Y') + 1 ? $year : $default;
    }

    protected function money(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    /**
     * Lampiran dari aplikasi: data URL base64 (kamera) atau berkas multipart.
     * Mengembalikan path relatif di storage/uploads (null bila tidak ada).
     */
    protected function attachment(string $field, string $folder, string $kind = 'image'): ?string
    {
        $dataUrl = (string) ApiRequest::input($field, '');

        if ($dataUrl !== '') {
            $stored = Upload::dataUrl($dataUrl, $folder);
            if ($stored['error'] !== null) {
                $this->fail(422, (string) $stored['error'], [$field => $stored['error']], 'UPLOAD_ERROR');
            }
            return $stored['path'];
        }

        if (!empty($_FILES[$field]['name'])) {
            $stored = $kind === 'document'
                ? Upload::document($field, $folder)
                : Upload::image($field, $folder);

            if ($stored['error'] !== null) {
                $this->fail(422, (string) $stored['error'], [$field => $stored['error']], 'UPLOAD_ERROR');
            }
            return $stored['path'];
        }

        return null;
    }
}
