<?php
/**
 * Layout email HTML (inline CSS agar aman di Gmail/Outlook).
 * @var string $content
 * @var array $company
 * @var int|string $year
 */
$brand = (string) ($company['name'] ?? 'HRIS');
$initial = mb_strtoupper(mb_substr($brand, 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($brand) ?></title>
</head>
<body style="margin:0;padding:0;background:#eef2f7;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f2438;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;padding:26px 12px;">
  <tr>
    <td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(15,36,56,.08);">

        <!-- header -->
        <tr>
          <td style="background:#0f2f45;padding:20px 26px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="width:40px;vertical-align:middle;">
                  <div style="width:34px;height:34px;border-radius:9px;background:#0d9488;color:#ffffff;font-size:15px;font-weight:700;text-align:center;line-height:34px;font-family:'Segoe UI',Arial,sans-serif;"><?= e($initial) ?></div>
                </td>
                <td style="vertical-align:middle;padding-left:10px;">
                  <div style="font-size:14px;font-weight:700;color:#ffffff;letter-spacing:.2px;"><?= e($brand) ?></div>
                  <div style="font-size:10.5px;color:#9db3c6;letter-spacing:.4px;text-transform:uppercase;">Human Resource Information System</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- content -->
        <tr>
          <td style="padding:26px 26px 8px;">
            <?= $content ?>
          </td>
        </tr>

        <!-- footer -->
        <tr>
          <td style="padding:16px 26px 22px;">
            <div style="border-top:1px solid #e2e8f0;padding-top:14px;font-size:10.8px;line-height:1.6;color:#7d8ea1;">
              Email ini dikirim otomatis oleh sistem <?= e($brand) ?>.
              Mohon tidak membalas pesan ini.<br>
              <?php if (!empty($company['address'])): ?><?= e($company['address']) ?><br><?php endif; ?>
              <?php if (!empty($company['email']) || !empty($company['phone'])): ?>
                <?= e((string) ($company['email'] ?? '')) ?><?= !empty($company['phone']) ? ' · ' . e((string) $company['phone']) : '' ?><br>
              <?php endif; ?>
              &copy; <?= e((string) $year) ?> <?= e($brand) ?>
            </div>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
