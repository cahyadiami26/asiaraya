<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

/**
 * Serves files stored outside the document root (storage/uploads).
 * Only the `folder/file` shape is accepted, and the resolved real path must
 * stay inside the upload directory — no traversal, no executable types.
 */
final class MediaController extends Controller
{
    private const MIMES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf',
    ];

    public function show(string $folder = '', string $file = ''): void
    {
        if (!Auth::check()) {
            http_response_code(403);
            exit;
        }

        $folder = preg_replace('/[^a-z0-9_-]/i', '', $folder) ?? '';
        $file = preg_replace('/[^a-z0-9._-]/i', '', $file) ?? '';

        if ($folder === '' || $file === '' || str_contains($file, '..')) {
            http_response_code(404);
            exit;
        }

        $root = realpath((string) config('upload_path'));
        $path = realpath(rtrim((string) config('upload_path'), '/') . '/' . $folder . '/' . $file);

        if ($root === false || $path === false || !str_starts_with($path, $root) || !is_file($path)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Berkas tidak ditemukan.';
            exit;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = self::MIMES[$extension] ?? null;
        if ($mime === null) {
            http_response_code(403);
            exit;
        }

        $mtime = (string) filemtime($path);
        $etag = '"' . md5($path . $mtime) . '"';
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        if ($mime === 'application/pdf') {
            header('Content-Disposition: inline; filename="' . basename($path) . '"');
            header('X-Content-Type-Options: nosniff');
        }
        header('Cache-Control: private, max-age=86400');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');

        if ($ifNoneMatch === $etag) {
            http_response_code(304);
            exit;
        }

        readfile($path);
        exit;
    }
}
