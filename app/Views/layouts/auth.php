<?php
/** Minimal shell for authentication screens. @var string $content */
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
<?= $content ?>
<script src="<?= asset('js/app.js') ?>?v=<?= APP_BUILD ?>"></script>
</body>
</html>
