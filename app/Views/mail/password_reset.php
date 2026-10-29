<?php
/**
 * Email reset kata sandi.
 * @var string $heading, $greeting, $message, $actionUrl, $actionLabel, $note
 * @var int $expiresMinutes
 * @var array $company
 */
$heading = (string) ($heading ?? 'Atur ulang kata sandi');
$greeting = (string) ($greeting ?? 'Halo,');
$message = (string) ($message ?? 'Kami menerima permintaan pengaturan ulang kata sandi untuk akun Anda.');
$actionUrl = (string) ($actionUrl ?? '');
$actionLabel = (string) ($actionLabel ?? 'Atur Ulang Kata Sandi');
$note = (string) ($note ?? '');
$expiresMinutes = (int) ($expiresMinutes ?? 60);
?>
<h1 style="margin:0 0 14px;font-size:19px;line-height:1.3;color:#0f2438;font-weight:700;"><?= e($heading) ?></h1>

<p style="margin:0 0 10px;font-size:13.2px;line-height:1.65;color:#334960;"><?= e($greeting) ?></p>
<p style="margin:0 0 16px;font-size:13.2px;line-height:1.65;color:#334960;"><?= e($message) ?></p>

<?php if ($actionUrl !== ''): ?>
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">
        <tr>
            <td style="border-radius:9px;background:#0f766e;">
                <a href="<?= e($actionUrl) ?>" style="display:inline-block;padding:12px 24px;font-size:12.8px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:9px;"><?= e($actionLabel) ?></a>
            </td>
        </tr>
    </table>
    <p style="margin:0 0 16px;font-size:10.8px;line-height:1.6;color:#7d8ea1;word-break:break-all;">
        Tautan berlaku <?= $expiresMinutes ?> menit dan hanya dapat dipakai satu kali. Bila tombol tidak berfungsi,
        salin tautan berikut:<br><span style="color:#0f766e;"><?= e($actionUrl) ?></span>
    </p>
<?php endif; ?>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 12px;background:#fdf6f6;border:1px solid #f6dcdc;border-radius:10px;">
    <tr>
        <td style="padding:11px 14px;font-size:11.3px;line-height:1.65;color:#9a3b3b;">
            <b>Keamanan akun:</b> tim <?= e((string) ($company['name'] ?? 'HRIS')) ?> tidak pernah meminta kata sandi
            melalui email maupun telepon. Abaikan pesan ini bila Anda tidak merasa meminta pengaturan ulang —
            kata sandi lama tetap berlaku.
        </td>
    </tr>
</table>

<?php if ($note !== ''): ?>
    <p style="margin:0;font-size:11.4px;line-height:1.6;color:#7d8ea1;"><?= nl2br(e($note)) ?></p>
<?php endif; ?>
