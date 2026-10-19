<?php
/**
 * NiKXor - Mobile Shop Management System
 * Theme Switcher Handler
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

auth_require();

$allowedThemes = ['classic', 'dark_gold', 'emerald', 'glass'];
$theme = $_GET['theme'] ?? $_POST['theme'] ?? 'classic';

if (in_array($theme, $allowedThemes)) {
    update_setting('active_theme', $theme);
    $_SESSION['active_theme'] = $theme;
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'theme' => $theme]);
        exit;
    }
}

$referer = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . 'index.php');
header('Location: ' . $referer);
exit;
