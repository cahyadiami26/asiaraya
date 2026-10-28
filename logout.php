<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
logout_user();
flash('success', 'Anda telah keluar.');
redirect('login.php');
