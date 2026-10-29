<?php
/**
 * Main application shell (sidebar + topbar + content).
 * @var string $content
 * @var array|null $currentUser
 */
use App\Core\Nav;
use App\Core\Session;

$currentUser = $currentUser ?? auth();
$badges = $badges ?? [];
$navGroups = Nav::forUser($currentUser, $badges);
$flashMessages = Session::flashes();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b2135">
    <meta name="description" content="HRIS Enterprise PT Asia Raya Group — presensi geofence, jadwal shift, cuti, lembur, dan payroll.">
    <title><?= e($pageTitle ?? 'Dashboard') ?> · <?= e(setting('company_name', config('app_name'))) ?></title>
    <link rel="manifest" href="<?= url('manifest.webmanifest') ?>">
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= asset('img/icon-192.png') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=<?= APP_BUILD ?>">
    <?= $head ?? '' ?>
</head>
<body>
<div class="app-shell">
    <?= \App\Core\View::partial('partials.sidebar', ['navGroups' => $navGroups, 'currentUser' => $currentUser]) ?>
    <div class="sidebar-backdrop" data-nav-toggle></div>

    <div class="app-main">
        <?= \App\Core\View::partial('partials.mobile-top', ['currentUser' => $currentUser]) ?>
        <?= \App\Core\View::partial('partials.topbar', ['currentUser' => $currentUser]) ?>

        <main class="page">
            <?php if ($flashMessages): ?>
                <div class="flash-stack">
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
        </main>
    </div>
</div>

<?= \App\Core\View::partial('partials.bottom-nav', ['currentUser' => $currentUser]) ?>

<script>window.HRIS_BASE = <?= json_encode(base_path()) ?>;</script>
<script src="<?= asset('js/app.js') ?>?v=<?= APP_BUILD ?>"></script>
<?= $scripts ?? '' ?>
</body>
</html>
