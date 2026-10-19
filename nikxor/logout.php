<?php
/**
 * NiKXor - Mobile Shop Management System
 * Screen Lock Handler (replaces traditional logout)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$_SESSION['screen_locked'] = true;
unset($_SESSION['super_admin_verified']);

redirect(BASE_URL . 'index.php?locked=1');
exit;
