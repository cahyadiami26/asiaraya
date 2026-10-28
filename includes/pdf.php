<?php
/**
 * Wrapper PDF generation memakai DOMPDF hasil install manual (tanpa composer).
 * Fallback: jika dompdf belum dipasang, keluar dalam bentuk HTML print-friendly.
 */
require_once __DIR__ . '/../config/config.php';

function dompdf_available(): bool
{
    return is_file(DOMPDF_DIR . '/autoload.inc.php');
}

/**
 * Render PDF. $filename: nama file unduhan. $orientation: P|L
 * Jika dompdf tidak tersedia, keluarkan HTML dengan tombol cetak.
 */
function render_pdf(string $html, string $filename = 'dokumen.pdf', string $orientation = 'P'): void
{
    if (dompdf_available()) {
        require_once DOMPDF_DIR . '/autoload.inc.php';
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientation === 'L' ? 'landscape' : 'portrait');
        $dompdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
        exit;
    }

    /* Fallback HTML agar tetap bisa dicetak (Ctrl+P -> Save as PDF) */
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>' . htmlspecialchars($filename) . '</title>'
        . '<style>body{font-family:Segoe UI,Arial,sans-serif;margin:2cm;color:#222}'
        . 'table{border-collapse:collapse;width:100%;font-size:12px}td,th{border:1px solid #999;padding:5px}'
        . '.no-print{position:fixed;top:8px;right:8px}@media print{.no-print{display:none}}'
        . 'img{max-width:64px}</style></head><body>'
        . '<div class="no-print"><button onclick="window.print()" style="padding:8px 16px;cursor:pointer">🖨 Cetak / Simpan PDF</button>'
        . ' <small>Install DOMPDF di /lib/dompdf untuk download PDF langsung.</small></div>'
        . $html . '</body></html>';
    exit;
}

/** Template kop surat perusahaan */
function pdf_header_html(array $co, string $title, string $subtitle = ''): string
{
    $logo = '';
    if (!empty($co['logo_path']) && is_file(BASE_PATH . '/' . $co['logo_path'])) {
        $type = pathinfo($co['logo_path'], PATHINFO_EXTENSION);
        $data = file_get_contents(BASE_PATH . '/' . $co['logo_path']);
        $logo = '<img src="data:image/' . ($type === 'png' ? 'png' : 'jpeg') . ';base64,' . base64_encode($data) . '" style="height:55px;width:55px;object-fit:contain"/>';
    }
    $name = htmlspecialchars($co['name'] ?? 'PT Asia Raya Group');
    $addr = htmlspecialchars($co['address'] ?? '');
    $contact = trim(($co['phone'] ?? '') . ' | ' . ($co['email'] ?? ''));
    return '
    <div style="width:100%;border-bottom:3px double #0d3b66;padding-bottom:8px;margin-bottom:14px">
      <table style="width:100%"><tr>
        <td style="width:70px">' . $logo . '</td>
        <td style="text-align:center">
          <div style="font-size:17pt;font-weight:bold;color:#0d3b66">' . $name . '</div>
          <div style="font-size:9pt">' . $addr . '</div>
          <div style="font-size:9pt">' . $contact . '</div>
        </td>
        <td style="width:70px"></td>
      </tr></table>
    </div>
    <div style="text-align:center;margin-bottom:12px">
      <span style="font-size:13pt;font-weight:bold;text-transform:uppercase">' . htmlspecialchars($title) . '</span><br>
      <span style="font-size:10pt;color:#555">' . htmlspecialchars($subtitle) . '</span>
    </div>';
}
