<?php
/**
 * NiKXor - Mobile Shop Management System
 * General System Configuration
 */

// Buffer all outputs to prevent "headers already sent" errors on free hosting and XAMPP
if (ob_get_level() === 0) {
    @ob_start();
}

// Enforce UTF-8 internal encoding if mbstring is loaded
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

// Default Timezone (Asia/Kabul for Afghanistan)
date_default_timezone_set('Asia/Kabul');

// Application Constants
defined('APP_NAME') or define('APP_NAME', 'NiKXor');
defined('APP_VERSION') or define('APP_VERSION', '1.0.0');
defined('DEFAULT_SHOP_NAME') or define('DEFAULT_SHOP_NAME', 'فروشگاه ضیا');
defined('CURRENCY_NAME') or define('CURRENCY_NAME', 'افغانی');

// Root Paths
defined('BASE_DIR') or define('BASE_DIR', realpath(__DIR__ . '/..'));
defined('INCLUDES_DIR') or define('INCLUDES_DIR', BASE_DIR . '/includes');
defined('MODULES_DIR') or define('MODULES_DIR', BASE_DIR . '/modules');
defined('UPLOADS_DIR') or define('UPLOADS_DIR', BASE_DIR . '/uploads');

// Calculate dynamic BASE_URL so it works seamlessly under any folder name (e.g. /nikxor/, /myshop/, /) on XAMPP and web hosts
if (!defined('BASE_URL')) {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (empty($scriptName)) {
        define('BASE_URL', '/');
    } else {
        // Detect project subfolder relative to standard folders
        $subDirs = ['/modules/', '/admin/', '/includes/', '/config/', '/uploads/', '/assets/', '/print/', '/reports/', '/api/', '/ajax/'];
        $base = '';
        foreach ($subDirs as $sd) {
            $pos = strpos($scriptName, $sd);
            if ($pos !== false) {
                $base = substr($scriptName, 0, $pos + 1);
                break;
            }
        }
        if (empty($base)) {
            $dir = dirname($scriptName);
            $base = ($dir === '/' || $dir === '\\') ? '/' : rtrim($dir, '/\\') . '/';
        }
        define('BASE_URL', $base ?: '/');
    }
}

// Application Secret Key for cryptographic operations (CSRF, tokens)
defined('APP_SECRET_KEY') or define('APP_SECRET_KEY', 'nikxor_shop_zia_7869922334455667788_secure_salt');

// Secure and Universal Session Initialization (Compatible with Desktop, Mobile, XAMPP, and Web Hosting)
if (session_status() === PHP_SESSION_NONE) {
    // 1. Ensure dedicated writable session directory to avoid XAMPP tmp permissions/missing path issues
    $localSessionDir = BASE_DIR . '/sessions';
    if (!is_dir($localSessionDir)) {
        @mkdir($localSessionDir, 0777, true);
        @file_put_contents($localSessionDir . '/.htaccess', "Deny from all\n");
    }
    if (is_dir($localSessionDir) && is_writable($localSessionDir)) {
        @session_save_path($localSessionDir);
    }

    // 2. Fallback: Restore session ID if passed via query parameter (crucial when redirecting across XAMPP subfolders)
    if (!empty($_REQUEST['sid'])) {
        $candidateSid = preg_replace('/[^a-zA-Z0-9,-]/', '', (string)$_REQUEST['sid']);
        if (!empty($candidateSid) && strlen($candidateSid) >= 16) {
            session_id($candidateSid);
        }
    }

    session_name('NIKXOR_SESSION');

    // 3. Detect HTTPS strictly (Standard SSL, Cloudflare, Reverse Proxy, Port 443)
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
        (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
    );

    // 4. Set cross-origin and mobile/XAMPP friendly cookie parameters
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 86400 * 30, // 30 days persistence
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps, // True ONLY on HTTPS, False on local HTTP
            'httponly' => true,
            'samesite' => $isHttps ? 'None' : 'Lax',
        ]);
    } else {
        session_set_cookie_params(86400 * 30, '/', '', $isHttps, true);
    }

    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '0'); // Allow sid param fallback
    ini_set('session.cookie_path', '/');
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_samesite', 'None');
    } else {
        ini_set('session.cookie_secure', '0');
        ini_set('session.cookie_samesite', 'Lax');
    }

    @session_start();
}

// Load Multi-Language Localization System (English as Default, Dari, Pashto)
require_once INCLUDES_DIR . '/i18n.php';
init_app_language();

