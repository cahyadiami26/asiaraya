<?php
use App\Core\Session;
foreach (Session::flashes() as $flash):
    $tone = in_array($flash['type'], ['success', 'error', 'warning'], true) ? $flash['type'] : 'info';
    $iconName = $tone === 'success' ? 'check' : ($tone === 'info' ? 'info' : 'alert');
?>
    <div class="alert alert-<?= $tone ?>" role="alert">
        <?= icon($iconName) ?>
        <div><?= $flash['message'] ?></div>
        <button type="button" class="alert__close" aria-label="Tutup"><?= icon('x') ?></button>
    </div>
<?php endforeach; ?>
