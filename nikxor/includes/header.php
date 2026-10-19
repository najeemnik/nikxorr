<?php
/**
 * NiKXor - Mobile Shop Management System
 * Standard Header Layout
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Enforce login for all normal application pages
auth_require();

$currentUser = auth_user();
$currentLang = get_current_lang();
$currentDir = get_current_dir();
$supportedLangs = get_supported_languages();
$currentLangInfo = $supportedLangs[$currentLang] ?? $supportedLangs['en'];

$shopName = function_exists('get_shop_title') ? get_shop_title() : get_setting('shop_name', DEFAULT_SHOP_NAME);
$shopLogo = get_setting('shop_logo', '');
$activeTheme = $_SESSION['active_theme'] ?? get_setting('active_theme', 'classic');

$pageTitleKeys = [
    'سیستم مدیریت دکان' => 'system_subname',
    'داشبورد عمومی دکان' => 'nav_dashboard',
    'فروختن جنس (بل جدید)' => 'nav_sales',
    'فروختن جنس (بل چند جنسی جدید)' => 'nav_sales',
    'آرشیف بل ها' => 'nav_invoices',
    'آرشیف بل‌ها' => 'nav_invoices',
    'مدیریت گدام و لست اجناس' => 'nav_stock',
    'ثبت جنس جدید در گدام' => 'btn_add_stock',
    'ویرایش مشخصات جنس در گدام' => 'edit_stock_title',
    'مدیریت قرضه ها و حسابات' => 'nav_debts',
    'مدیریت قرضه ها، طلبات و تفکیک سرمایه و مفاد' => 'nav_debts',
    'مشتریان و حسابات' => 'nav_customers',
    'مدیریت مشتریان و حسابات VIP' => 'nav_customers',
    'مصارف دکان' => 'nav_expenses',
    'مدیریت مصارف دکان' => 'nav_expenses',
    'ضرر و خسارات اجناس' => 'nav_losses',
    'مدیریت ضرر و خسارات اجناس' => 'nav_losses',
    'مدیریت ضرر و خسارات اجناس (تفکیک شده از مصارف)' => 'nav_losses',
    'شرکاء و سرمایه دکان' => 'nav_partners',
    'مدیریت شریک ها و سهم سرمایه' => 'nav_partners',
    'سرمایه و مضاربت (خارجی)' => 'nav_mudaraba',
    'حساب اختصاصی مضاربت و سرمایه‌گذاری خارجی' => 'nav_mudaraba',
    'گزارشات مالی و حسابداری دکان' => 'nav_reports',
    'هوش مصنوعی دکان' => 'nav_ai_advisor',
    'هوش مصنوعی حسابدار و مشاور مالی دکان' => 'nav_ai_advisor',
    'پشتیبان‌گیری (بک‌آپ)' => 'nav_backup',
    'پشتیبان‌گیری دیتابیس و امنیت اطلاعات' => 'nav_backup',
    'درباره برنامه و سازنده' => 'nav_about',
    'درباره سامانه حسابی NiKXor و سازنده' => 'nav_about',
    'کنترل پنل سوپر ادمین' => 'nav_super_admin',
    'مدیریت دسته بندی های دکان' => 'nav_categories',
    'نمای گدام و موقعیت اجناس' => 'nav_warehouse',
    'گردش و حساب سرمایه دکان' => 'nav_capital'
];
$displayTitle = isset($pageTitle) && isset($pageTitleKeys[$pageTitle]) ? __($pageTitleKeys[$pageTitle]) : (isset($pageTitle) ? __($pageTitle, $pageTitle) : __('system_subname'));
$currentScript = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="<?= sanitize($currentLang) ?>" dir="<?= sanitize($currentDir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($displayTitle) ?> - <?= sanitize($shopName) ?></title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Vazirmatn Font -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <!-- Core Application CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
</head>
<body class="theme-<?= sanitize($activeTheme) ?> dir-<?= sanitize($currentDir) ?>">

<div class="app-wrapper">
    <!-- Sidebar Navigation -->
    <aside class="app-sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon golden-aura-glow">
                <?php if (!empty($shopLogo) && file_exists(__DIR__ . '/../uploads/' . $shopLogo)): ?>
                    <img src="<?= BASE_URL ?>uploads/<?= sanitize($shopLogo) ?>" alt="<?= sanitize($shopName) ?>" style="width: 38px; height: 38px; object-fit: contain; border-radius: 6px;">
                <?php else: ?>
                    <i class="fa-solid fa-mobile-screen-button" style="color: #fbbf24;"></i>
                <?php endif; ?>
            </div>
            <div class="brand-info">
                <h1><?= sanitize($shopName) ?></h1>
                <span><?= __('system_subname') ?></span>
            </div>
            <button type="button" id="sidebarCloseBtn" class="sidebar-close-btn" aria-label="<?= __('close_menu') ?>" title="<?= __('close_menu') ?>">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <!-- 1. صفحه اصلی / Dashboard -->
            <a href="<?= BASE_URL ?>index.php" class="nav-link <?= ($currentScript === 'index.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-house" style="color: #38bdf8;"></i>
                <span><?= __('nav_dashboard') ?></span>
            </a>

            <!-- 2. فروختن جنس و بل جدید / Sales -->
            <a href="<?= BASE_URL ?>modules/sales.php" class="nav-link <?= ($currentScript === 'sales.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-cash-register" style="color: #34d399;"></i>
                <span><?= __('nav_sales') ?></span>
            </a>

            <!-- 2.1 آرشیف بل ها / Invoices -->
            <a href="<?= BASE_URL ?>modules/invoices.php" class="nav-link <?= ($currentScript === 'invoices.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-receipt" style="color: #60a5fa;"></i>
                <span><?= __('nav_invoices') ?></span>
            </a>
            
            <!-- 3. گدام و اجناس / Stock -->
            <a href="<?= BASE_URL ?>modules/stock.php" class="nav-link <?= (in_array($currentScript, ['stock.php', 'stock_add.php', 'stock_edit.php', 'warehouse.php', 'categories.php'])) ? 'active' : '' ?>">
                <i class="fa-solid fa-boxes-stacked" style="color: #fbbf24;"></i>
                <span><?= __('nav_stock') ?></span>
            </a>

            <!-- 4. قرضه ها و اقساط / Debts -->
            <a href="<?= BASE_URL ?>modules/debts.php" class="nav-link <?= ($currentScript === 'debts.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-hand-holding-dollar" style="color: #f87171;"></i>
                <span><?= __('nav_debts') ?></span>
            </a>

            <!-- 5. مشتریان / Customers -->
            <a href="<?= BASE_URL ?>modules/customers.php" class="nav-link <?= ($currentScript === 'customers.php') ? 'active' : '' ?>" style="display: flex; justify-content: space-between; align-items: center;">
                <span style="display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-users" style="color: #c084fc;"></i>
                    <span><?= __('nav_customers') ?></span>
                </span>
                <?php if (is_feature_locked('customers_module')): ?>
                    <span class="badge-lock-tag" style="padding: 1px 6px; font-size: 10px;"><i class="fa-solid fa-lock text-amber-500"></i> <?= __('locked') ?></span>
                <?php endif; ?>
            </a>

            <!-- 6. مصارف دکان / Expenses -->
            <a href="<?= BASE_URL ?>modules/expenses.php" class="nav-link <?= ($currentScript === 'expenses.php') ? 'active' : '' ?>" style="display: flex; justify-content: space-between; align-items: center;">
                <span style="display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-wallet" style="color: #f472b6;"></i>
                    <span><?= __('nav_expenses') ?></span>
                </span>
                <?php if (is_feature_locked('expenses_module')): ?>
                    <span class="badge-lock-tag" style="padding: 1px 6px; font-size: 10px;"><i class="fa-solid fa-lock text-amber-500"></i> <?= __('locked') ?></span>
                <?php endif; ?>
            </a>

            <!-- 6.1 ضرر و خسارات اجناس / Losses -->
            <a href="<?= BASE_URL ?>modules/losses.php" class="nav-link <?= ($currentScript === 'losses.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i>
                <span><?= __('nav_losses') ?></span>
            </a>

            <!-- 7. شریک ها و سرمایه عمومی / Partners -->
            <a href="<?= BASE_URL ?>modules/partners.php" class="nav-link <?= (in_array($currentScript, ['partners.php', 'capital.php'])) ? 'active' : '' ?>">
                <i class="fa-solid fa-vault" style="color: #2dd4bf;"></i>
                <span><?= __('nav_partners') ?></span>
            </a>

            <!-- 7.1 حساب اختصاصی مضاربت / Mudaraba -->
            <a href="<?= BASE_URL ?>modules/mudaraba.php" class="nav-link <?= ($currentScript === 'mudaraba.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-handshake-angle" style="color: #10b981;"></i>
                <span><?= __('nav_mudaraba') ?></span>
            </a>

            <!-- 8. گزارشات مالی / Reports -->
            <a href="<?= BASE_URL ?>modules/reports.php" class="nav-link <?= ($currentScript === 'reports.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie" style="color: #818cf8;"></i>
                <span><?= __('nav_reports') ?></span>
            </a>

            <!-- 9. هوش مصنوعی حسابدار دکان / AI Advisor -->
            <a href="<?= BASE_URL ?>modules/ai_advisor.php" class="nav-link <?= ($currentScript === 'ai_advisor.php') ? 'active' : '' ?>" style="display: flex; justify-content: space-between; align-items: center;">
                <span style="display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-wand-magic-sparkles" style="color: #38bdf8;"></i>
                    <span><?= __('nav_ai_advisor') ?></span>
                </span>
                <?php if (is_feature_locked('ai_advisor')): ?>
                    <span class="badge-lock-tag" style="padding: 1px 6px; font-size: 10px;"><i class="fa-solid fa-lock text-amber-500"></i> <?= __('locked') ?></span>
                <?php endif; ?>
            </a>

            <!-- 10. پشتیبان‌گیری دیتابیس / Backup -->
            <a href="<?= BASE_URL ?>modules/backup.php" class="nav-link <?= ($currentScript === 'backup.php') ? 'active' : '' ?>" style="display: flex; justify-content: space-between; align-items: center;">
                <span style="display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-database" style="color: #93c5fd;"></i>
                    <span><?= __('nav_backup') ?></span>
                </span>
                <?php if (is_feature_locked('backup_module')): ?>
                    <span class="badge-lock-tag" style="padding: 1px 6px; font-size: 10px;"><i class="fa-solid fa-lock text-amber-500"></i> <?= __('locked') ?></span>
                <?php endif; ?>
            </a>

            <!-- 11. درباره برنامه و سازنده / About -->
            <a href="<?= BASE_URL ?>modules/about.php" class="nav-link <?= ($currentScript === 'about.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-info" style="color: #94a3b8;"></i>
                <span><?= __('nav_about') ?></span>
            </a>

            <?php if (!empty($_SESSION['super_admin_verified']) || ($currentUser['username'] ?? '') === 'nikadmin'): ?>
                <a href="<?= BASE_URL ?>admin/secret_panel_786.php" class="nav-link" style="color: #d97706; font-weight: 700; background: rgba(251, 191, 36, 0.12); border-radius: 6px; margin-top: 6px;">
                    <i class="fa-solid fa-crown" style="color: #d97706;"></i>
                    <span><?= __('nav_super_admin') ?></span>
                </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-snippet">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fa-solid fa-store"></i>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?= sanitize(function_exists('display_user_fullname') ? display_user_fullname($currentUser['fullname']) : $currentUser['fullname']) ?></div>
                        <div class="user-role">
                            <?= __('shop_environment') ?>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="lockShopScreen();" class="logout-btn" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a;" title="<?= __('lock_shop_screen') ?>">
                    <i class="fa-solid fa-lock"></i>
                </button>
            </div>
        </div>
    </aside>

    <!-- Mobile Drawer Overlay -->
    <div id="mobileNavOverlay" class="mobile-overlay"></div>

    <!-- Main Content Area -->
    <main class="app-main">
        <header class="app-topbar">
            <div class="page-title" style="display: flex; align-items: center; gap: 10px;">
                <button type="button" id="mobileMenuToggle" class="mobile-menu-toggle" aria-label="<?= __('nav_dashboard') ?>" title="<?= __('nav_dashboard') ?>">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <?php if (!empty($shopLogo) && file_exists(__DIR__ . '/../uploads/' . $shopLogo)): ?>
                    <div class="golden-aura-glow" style="width: 32px; height: 32px;">
                        <img src="<?= BASE_URL ?>uploads/<?= sanitize($shopLogo) ?>" alt="Logo" style="width: 28px; height: 28px; object-fit: contain;">
                    </div>
                <?php endif; ?>
                <h2><?= sanitize($displayTitle) ?></h2>
            </div>
            <div class="topbar-actions" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <!-- Language Switcher Dropdown (English, Dari, Pashto) -->
                <div class="lang-selector-wrapper" style="position: relative; display: inline-block;">
                    <button type="button" id="langDropdownBtn" onclick="toggleLangDropdown(event)" class="btn btn-sm btn-lang" style="display: inline-flex; align-items: center; gap: 6px; background: #ffffff; color: #1e293b; border: 1px solid #cbd5e1; border-radius: 8px; padding: 7px 11px; font-weight: 700; font-size: 12.5px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); cursor: pointer;" title="<?= __('select_language') ?>">
                        <span style="font-size: 14px; line-height: 1;"><?= $currentLangInfo['flag'] ?></span>
                        <span style="font-size: 12px;"><?= $currentLangInfo['native'] ?></span>
                        <i class="fa-solid fa-chevron-down" style="font-size: 10px; color: #64748b; margin-top: 1px;"></i>
                    </button>
                    <div id="langDropdownMenu" class="lang-dropdown-menu" style="display: none; position: absolute; top: calc(100% + 6px); <?= $currentDir === 'rtl' ? 'left: 0;' : 'right: 0;' ?> background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.12); min-width: 175px; z-index: 1100; overflow: hidden; padding: 5px;">
                        <div style="font-size: 11px; font-weight: 800; color: #94a3b8; padding: 4px 8px 6px; border-bottom: 1px solid #f1f5f9; margin-bottom: 4px;">
                            <i class="fa-solid fa-globe ml-1"></i> <?= __('select_language') ?>
                        </div>
                        <?php foreach ($supportedLangs as $lCode => $lInfo): ?>
                            <a href="?lang=<?= $lCode ?>" class="lang-dropdown-item" style="display: flex; align-items: center; justify-content: space-between; padding: 8px 10px; border-radius: 6px; text-decoration: none; font-size: 12.5px; font-weight: <?= $lCode === $currentLang ? '800' : '600' ?>; color: <?= $lCode === $currentLang ? '#0284c7' : '#334155' ?>; background: <?= $lCode === $currentLang ? '#eff6ff' : 'transparent' ?>; margin-bottom: 2px;">
                                <span style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 15px;"><?= $lInfo['flag'] ?></span>
                                    <span><?= $lInfo['native'] ?></span>
                                </span>
                                <?php if ($lCode === $currentLang): ?>
                                    <i class="fa-solid fa-check" style="font-size: 11px; color: #0284c7;"></i>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!empty($_SESSION['super_admin_verified']) || ($currentUser['username'] ?? '') === 'nikadmin'): ?>
                    <a href="<?= BASE_URL ?>admin/secret_panel_786.php" class="btn btn-sm btn-super-admin" style="background: linear-gradient(135deg, #d97706, #b45309); color: #ffffff; font-weight: 800; border-radius: 8px; padding: 7px 12px; border: none; box-shadow: 0 2px 8px rgba(217, 119, 6, 0.25); display: inline-flex; align-items: center; gap: 6px;" title="<?= __('nav_super_admin') ?>">
                        <i class="fa-solid fa-crown" style="font-size: 13px;"></i>
                        <span class="mobile-hide-text"><?= __('nav_super_admin') ?></span>
                    </a>
                <?php endif; ?>
                <div class="topbar-clock">
                    <i class="fa-regular fa-clock" style="color: #0284c7; font-size: 13px;"></i>
                    <span style="font-family: inherit;"><?= to_persian_num(date('Y/m/d')) ?></span>
                    <span style="color: #cbd5e1;">|</span>
                    <span style="font-family: inherit; color: #0284c7;"><?= to_persian_num(date('H:i')) ?></span>
                </div>
                <a href="<?= BASE_URL ?>modules/sales.php" class="btn btn-sm" style="display: inline-flex; align-items: center; gap: 7px; background: linear-gradient(135deg, #0284c7, #0369a1); color: #ffffff; font-weight: 800; border-radius: 8px; padding: 7px 14px; border: none; box-shadow: 0 2px 8px rgba(2, 132, 199, 0.25);">
                    <i class="fa-solid fa-cart-shopping" style="font-size: 14px;"></i>
                    <span class="mobile-hide-text"><?= __('quick_sell') ?></span>
                </a>
                <!-- Screen Lock Button (Shopkeeper Security) -->
                <button type="button" onclick="lockShopScreen();" class="btn btn-sm" style="background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #92400e; border: 1.5px solid #fde68a; font-weight: 800; border-radius: 8px; padding: 7px 13px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(245, 158, 11, 0.15);" title="<?= __('lock_shop_screen') ?>">
                    <i class="fa-solid fa-shield-halved" style="color: #d97706; font-size: 13px;"></i>
                    <span class="mobile-hide-text"><?= __('lock_shop_screen') ?></span>
                </button>
                <button type="button" onclick="openScreenLockSettingsModal();" class="btn btn-sm" style="background: #ffffff; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; padding: 7px 10px; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,0.04);" title="<?= __('set_pin') ?>">
                    <i class="fa-solid fa-key" style="color: #64748b; font-size: 13px;"></i>
                </button>
            </div>
        </header>

        <script>
        function toggleLangDropdown(e) {
            if (e) e.stopPropagation();
            var menu = document.getElementById('langDropdownMenu');
            if (menu) {
                menu.style.display = (menu.style.display === 'none' || menu.style.display === '') ? 'block' : 'none';
            }
        }
        document.addEventListener('click', function(e) {
            var menu = document.getElementById('langDropdownMenu');
            var btn = document.getElementById('langDropdownBtn');
            if (menu && btn && !btn.contains(e.target) && !menu.contains(e.target)) {
                menu.style.display = 'none';
            }
        });
        </script>

        <div class="app-content">

            <?php 
            $flash = get_flash();
            if ($flash): 
            ?>
                <div class="alert alert-<?= sanitize($flash['type']) ?>">
                    <i class="fa-solid fa-circle-info"></i>
                    <span><?= sanitize($flash['text']) ?></span>
                </div>
            <?php endif; ?>
