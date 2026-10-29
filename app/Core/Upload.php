<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Image upload handling for employee photos, company logos and unit logos.
 *
 * Files land outside the document root (storage/uploads) and are served through
 * the /media route (MediaController), so nothing executable is web-reachable.
 * Uses GD (bundled with every standard PHP build) for resizing/compression.
 */
final class Upload
{
    private const MAX_BYTES = 4 * 1024 * 1024; // 4 MB

    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Store an uploaded image.
     *
     * @return array{path:?string,error:?string}
     */
    public static function image(string $field, string $folder, int $maxWidth = 800, int $quality = 84): array
    {
        if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => null];
        }

        $file = $_FILES[$field];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran gambar melebihi batas server.',
                UPLOAD_ERR_PARTIAL => 'Unggahan gambar tidak lengkap, silakan coba lagi.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Server tidak dapat menyimpan berkas. Periksa permission storage/uploads.',
                default => 'Gagal mengunggah gambar (kode ' . $file['error'] . ').',
            };
            return ['path' => null, 'error' => $message];
        }

        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['path' => null, 'error' => 'Ukuran gambar maksimal 4 MB.'];
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            return ['path' => null, 'error' => 'Berkas bukan gambar yang valid.'];
        }

        $mime = $info['mime'] ?? '';
        if (!isset(self::ALLOWED[$mime])) {
            return ['path' => null, 'error' => 'Format gambar harus JPG, PNG, atau WEBP.'];
        }

        $extension = self::ALLOWED[$mime];
        $folder = trim($folder, '/');
        $directory = rtrim((string) config('upload_path'), '/') . '/' . $folder;
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return ['path' => null, 'error' => 'Folder penyimpanan tidak dapat dibuat.'];
        }

        $baseName = date('Ymd') . '-' . bin2hex(random_bytes(6));
        $relative = $folder . '/' . $baseName . '.' . ($extension === 'png' ? 'png' : 'jpg');
        $target = (string) config('upload_path') . '/' . $relative;

        if (!extension_loaded('gd')) {
            // No GD: keep the original bytes, extension checked above.
            if (!self::persist((string) $file['tmp_name'], $target)) {
                return ['path' => null, 'error' => 'Gagal menyimpan gambar.'];
            }
            return ['path' => $relative, 'error' => null];
        }

        [$width, $height] = $info;
        $scale = min(1, $maxWidth / max(1, $width));
        $newWidth = (int) round($width * $scale);
        $newHeight = (int) round($height * $scale);

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
            'image/png'  => @imagecreatefrompng($file['tmp_name']),
            'image/webp' => @imagecreatefromwebp($file['tmp_name']),
            default      => false,
        };

        if (!$source) {
            if (!self::persist((string) $file['tmp_name'], $target)) {
                return ['path' => null, 'error' => 'Gagal memproses gambar.'];
            }
            return ['path' => $relative, 'error' => null];
        }

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        if ($extension === 'png') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
            imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
        } else {
            // flatten transparency onto white for JPEG output
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $white);
        }

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $saved = $extension === 'png'
            ? imagepng($canvas, $target, 7)
            : imagejpeg($canvas, $target, $quality);

        imagedestroy($canvas);
        imagedestroy($source);

        if (!$saved) {
            return ['path' => null, 'error' => 'Gagal menyimpan gambar yang telah diproses.'];
        }

        return ['path' => $relative, 'error' => null];
    }

    /**
     * Move an uploaded temp file into place.
     * move_uploaded_file() only accepts genuine SAPI uploads; the wasm preview
     * bridge supplies files through a different channel, so fall back to a plain
     * rename/copy. The destination name is always generated server-side.
     */
    private static function persist(string $tmp, string $target): bool
    {
        if (is_uploaded_file($tmp)) {
            return @move_uploaded_file($tmp, $target);
        }
        if (@rename($tmp, $target)) {
            return true;
        }
        return @copy($tmp, $target);
    }

    /** Remove a previously stored file (ignores paths outside storage/uploads). */
    public static function delete(?string $relative): void
    {
        if (!$relative || str_contains($relative, '..')) {
            return;
        }
        $path = rtrim((string) config('upload_path'), '/') . '/' . ltrim($relative, '/');
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
