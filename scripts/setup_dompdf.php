<?php
/**
 * Download manual DOMPDF (tanpa Composer).
 * Jalankan: php scripts/setup_dompdf.php
 * Alternatif: unduh "dompdf_x.x.x_full_installed.zip" dari
 * https://github.com/dompdf/dompdf/releases lalu ekstrak ke /lib/dompdf
 * sehingga ada file lib/dompdf/autoload.inc.php
 */
$target = dirname(__DIR__) . '/lib/dompdf';
if (!is_dir($target)) mkdir($target, 0775, true);

$zipUrl = 'https://github.com/dompdf/dompdf/releases/download/v3.1.0/dompdf_3.1.0_full_installed.zip';
$zipPath = $target . '/dompdf.zip';

echo "Mendownload DOMPDF full-installed dari:\n$zipUrl\n";
$ok = false;
if (function_exists('curl_init')) {
    $ch = curl_init($zipUrl);
    $fp = fopen($zipPath, 'w');
    curl_setopt_array($ch, [CURLOPT_FILE => $fp, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 300]);
    $ok = curl_exec($ch); curl_close($ch); fclose($fp);
} else {
    ini_set('max_execution_time', 300);
    $data = @file_get_contents($zipUrl);
    if ($data) { file_put_contents($zipPath, $data); $ok = true; }
}

if ($ok && class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($zipPath) === true) {
        $zip->extractTo(dirname($target)); // zip berisi folder dompdf/
        $zip->close();
        @unlink($zipPath);
        echo "DOMPDF berhasil diekstrak ke $target\n";
    }
}

if (file_exists($target . '/autoload.inc.php')) {
    echo "OK: autoload.inc.php ditemukan. Aplikasi siap generate PDF.\n";
} else {
    echo "GAGAL. Unduh manual dari https://github.com/dompdf/dompdf/releases dan ekstrak ke $target\n";
}
