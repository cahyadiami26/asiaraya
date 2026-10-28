<?php
/**
 * Halaman depan: redirect sesuai status login.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (!is_logged_in()) {
    redirect('login.php');
}
redirect('dashboard.php');
