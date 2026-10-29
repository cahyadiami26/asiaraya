<?php
/**
 * Template email umum. Variabel opsional:
 * @var string $heading, $greeting, $message, $note
 * @var array $lines
 * @var string $actionUrl, $actionLabel
 * @var string $badge, $badgeTone
 */
$heading = (string) ($heading ?? 'Pemberitahuan');
$greeting = (string) ($greeting ?? 'Halo,');
$message = (string) ($message ?? '');
$note = (string) ($note ?? '');
$lines = is_array($lines ?? null) ? $lines : [];
$actionUrl = (string) ($actionUrl ?? '');
$actionLabel = (string) ($actionLabel ?? 'Buka HRIS');
$badge = (string) ($badge ?? '');
?>
<h1 style="margin:0 0 6px;font-size:19px;line-height:1.3;color:#0f2438;font-weight:700;"><?= e($heading) ?></h1>

<?php if ($badge !== ''): ?>
  <div style="margin:0 0 12px;">
    <span style="display:inline-block;padding:3px 9px;border-radius:999px;background:#e6f6f3;color:#0f766e;font-size:10.5px;font-weight:600;letter-spacing:.2px;"><?= e($badge) ?></span>
  </div>
<?php endif; ?>

<p style="margin:0 0 10px;font-size:13.2px;line-height:1.65;color:#334960;"><?= e($greeting) ?></p>

<?php if ($message !== ''): ?>
  <p style="margin:0 0 14px;font-size:13.2px;line-height:1.65;color:#334960;"><?= nl2br(e($message)) ?></p>
<?php endif; ?>

<?php if ($lines): ?>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;background:#f6fafc;border:1px solid #e2ecf3;border-radius:10px;">
    <tr>
      <td style="padding:12px 16px;">
        <?php foreach ($lines as $line): ?>
          <div style="font-size:12.4px;line-height:1.7;color:#42586e;">
            <span style="color:#0d9488;font-weight:700;">&bull;</span>
            <span style="padding-left:4px;"><?= e((string) $line) ?></span>
          </div>
        <?php endforeach; ?>
      </td>
    </tr>
  </table>
<?php endif; ?>

<?php if ($actionUrl !== ''): ?>
  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">
    <tr>
      <td style="border-radius:9px;background:#0d9488;">
        <a href="<?= e($actionUrl) ?>" style="display:inline-block;padding:11px 22px;font-size:12.8px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:9px;"><?= e($actionLabel) ?></a>
      </td>
    </tr>
  </table>
  <p style="margin:0 0 14px;font-size:10.8px;line-height:1.6;color:#7d8ea1;word-break:break-all;">
    Bila tombol tidak berfungsi, salin tautan berikut ke browser Anda:<br>
    <span style="color:#0f766e;"><?= e($actionUrl) ?></span>
  </p>
<?php endif; ?>

<?php if ($note !== ''): ?>
  <p style="margin:0 0 6px;font-size:11.4px;line-height:1.6;color:#7d8ea1;border-left:3px solid #dbe6ee;padding-left:10px;"><?= nl2br(e($note)) ?></p>
<?php endif; ?>
