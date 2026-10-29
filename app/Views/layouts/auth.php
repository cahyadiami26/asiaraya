<?php
/**
 * Shell autentikasi (login, lupa kata sandi, atur ulang kata sandi).
 * @var string $content
 */
$flashMessages = \App\Core\Session::flashes();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b2135">
    <title><?= e($title ?? 'Masuk') ?> · <?= e(setting('company_name', 'PT Asia Raya Group')) ?> HRIS</title>
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=<?= APP_BUILD ?>">
</head>
<body class="auth-body">
<?php if ($flashMessages): ?>
    <div class="auth-flash">
        <?php foreach ($flashMessages as $flash): ?>
            <?php
            $tone = $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'error' : ($flash['type'] === 'warning' ? 'warning' : 'info'));
            $iconName = $tone === 'success' ? 'check' : ($tone === 'error' ? 'alert' : ($tone === 'warning' ? 'alert' : 'info'));
            ?>
            <div class="alert alert-<?= $tone ?>" role="alert">
                <?= icon($iconName) ?>
                <div><?= e($flash['message']) ?></div>
                <button type="button" class="alert__close" aria-label="Tutup"><?= icon('x') ?></button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?= $content ?>
<script src="<?= asset('js/app.js') ?>?v=<?= APP_BUILD ?>"></script>
</body>
</html>
