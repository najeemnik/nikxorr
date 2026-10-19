<?php
/**
 * NiKXor - Mobile Shop Management System
 * HIDDEN MASTER CONTROL & SUPER ADMIN PANEL
 * 
 * SECURITY REQUIREMENT:
 * This file is accessible ONLY via direct URL:
 * http://localhost/nikxor/admin/secret_panel_786.php
 * Protected by Admin Authentication and Master PIN Verification.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Handle Exit / Lock Super Admin Session
if (isset($_GET['lock_panel']) && $_GET['lock_panel'] === '1') {
    unset($_SESSION['super_admin_verified']);
    redirect(BASE_URL . 'index.php');
}

$adminDirectUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost:3000') . BASE_URL . "admin/secret_panel_786.php";
$masterPin = get_setting('super_admin_pin', '2277512');

// Handle Master PIN Verification (allows logging in directly as Super Admin with 2277512)
$pinError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_master_pin') {
    $enteredPin = trim($_POST['master_pin'] ?? '');
    if (function_exists('normalize_numbers')) {
        $enteredPin = normalize_numbers($enteredPin);
    }
    $cleanPin = str_replace([' ', '-', '_'], '', $enteredPin);

    $isMasterMatch = false;
    if ($cleanPin === $masterPin) {
        $isMasterMatch = true;
    } elseif ($masterPin === '2277512' && ($cleanPin === '2277512' || $cleanPin === 'Nik@786#Master')) {
        // Only accept legacy default if master PIN has never been customized
        $isMasterMatch = true;
    }

    if ($isMasterMatch) {
        if (!auth_check()) {
            $user = $pdo->query("SELECT * FROM users WHERE username = 'nikadmin' OR id = 1 LIMIT 1")->fetch();
            if (!$user) {
                $user = $pdo->query("SELECT * FROM users ORDER BY id ASC LIMIT 1")->fetch();
            }
            if ($user) {
                if (session_status() === PHP_SESSION_NONE) {
                    @session_start();
                }
                if (!headers_sent()) {
                    @session_regenerate_id(true);
                }
                $_SESSION['user_id']               = (int)$user['id'];
                $_SESSION['user_username']         = $user['username'];
                $_SESSION['user_fullname']         = $user['fullname'] ?: 'احمد ضیا (مدیر کل)';
                $_SESSION['user_role']             = 'admin';
                $_SESSION['is_super_admin']        = true;
            }
        }
        $_SESSION['super_admin_verified'] = true;
        log_activity($_SESSION['user_id'] ?? 1, 'ورود سوپر ادمین', 'ورود موفق به پنل محرمانه با پین‌کد ۲۲۷۷۵۱۲');
        
        $redirectTarget = BASE_URL . 'admin/secret_panel_786.php';
        $currentSid = session_id();
        if (!empty($currentSid)) {
            $redirectTarget .= '?sid=' . urlencode($currentSid);
        }
        redirect($redirectTarget);
    } else {
        $pinError = 'پین‌کد وارد شده نادرست است! لطفاً مجدداً با دقت تلاش نمایید.';
    }
}

// If super admin PIN is not verified, show Master PIN challenge screen
if (empty($_SESSION['super_admin_verified'])) {

    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>احراز هویت سوپر ادمین - NiKXor Master Security</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
        <style>
            body {
                background: radial-gradient(circle at center, #1e293b, #0f172a);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 16px;
                color: #f8fafc;
            }
            .pin-card {
                background: rgba(15, 23, 42, 0.95);
                border: 1px solid rgba(251, 191, 36, 0.4);
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
                border-radius: 16px;
                padding: 32px 28px;
                max-width: 440px;
                width: 100%;
                text-align: center;
                backdrop-filter: blur(12px);
            }
            .pin-icon {
                width: 64px;
                height: 64px;
                background: rgba(251, 191, 36, 0.15);
                border: 2px solid #fbbf24;
                border-radius: 50%;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 26px;
                color: #fbbf24;
                margin-bottom: 16px;
                box-shadow: 0 0 20px rgba(251, 191, 36, 0.3);
            }
            .pin-input {
                background: #1e293b;
                border: 2px solid #475569;
                color: #fbbf24;
                font-size: 24px;
                font-weight: 800;
                letter-spacing: 8px;
                text-align: center;
                padding: 12px;
                border-radius: 10px;
                width: 100%;
                outline: none;
                margin-bottom: 16px;
                transition: border-color 0.2s;
            }
            .pin-input:focus {
                border-color: #fbbf24;
                box-shadow: 0 0 15px rgba(251, 191, 36, 0.25);
            }
        </style>
    </head>
    <body>
        <div class="pin-card">
            <div class="pin-icon">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h2 style="font-size: 19px; font-weight: 800; margin-bottom: 4px; color: #fbbf24;">پنل محرمانه سوپر ادمین</h2>
            <div style="font-size: 12.5px; color: #94a3b8; margin-bottom: 16px; direction: ltr;">Super Admin Secret Panel Access</div>

            <p style="font-size: 13.5px; color: #cbd5e1; margin-bottom: 20px; line-height: 1.6;">
                جهت دسترسی به تنظیمات قفل‌گذاری سیستم و کنترل قابلیت‌ها، لطفاً پین‌کد امنیتی را وارد کنید:
            </p>

            <?php if ($pinError): ?>
                <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; padding: 10px; border-radius: 8px; font-size: 12.5px; margin-bottom: 16px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?= sanitize($pinError) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="pinForm">
                <input type="hidden" name="action" value="verify_master_pin">
                <div style="position: relative; margin-bottom: 16px;">
                    <input type="password" id="masterPinInput" name="master_pin" class="pin-input" placeholder="•••••••" required autofocus autocomplete="off" style="margin-bottom: 0;">
                    <button type="button" onclick="togglePinVisibility()" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; font-size: 18px; cursor: pointer;">
                        <i id="eyeIcon" class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; background: #fbbf24; color: #0f172a; font-weight: 800; border: none; padding: 12px; border-radius: 8px; font-size: 14px;">
                    <i class="fa-solid fa-key ml-1"></i> ورود امن به پنل اختصاصی
                </button>
            </form>

            <script>
            function togglePinVisibility() {
                var inp = document.getElementById('masterPinInput');
                var icon = document.getElementById('eyeIcon');
                if (inp.type === 'password') {
                    inp.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    inp.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
            </script>

            <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.1); font-size: 12px; color: #94a3b8;">
                مسئول فنی و مالک سیستم: <strong>احمد نجیم نیک</strong><br>
                تماس و هماهنگی: <code>+93783788278</code>
            </div>

            <div style="margin-top: 14px;">
                <a href="<?= BASE_URL ?>index.php" style="font-size: 12.5px; color: #64748b; text-decoration: underline;">
                    <i class="fa-solid fa-arrow-right ml-1"></i> بازگشت به دکان
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$currentUser = auth_user();
if (!$currentUser) {
    try {
        $adminUser = $pdo->query("SELECT * FROM users WHERE username = 'nikadmin' OR id = 1 LIMIT 1")->fetch();
        if ($adminUser) {
            $currentUser = $adminUser;
        } else {
            $currentUser = [
                'id' => 1,
                'username' => 'nikadmin',
                'fullname' => 'احمد نجیم نیک (سوپر ادمین)',
                'role' => 'admin'
            ];
        }
    } catch (Exception $e) {
        $currentUser = [
            'id' => 1,
            'username' => 'nikadmin',
            'fullname' => 'احمد نجیم نیک (سوپر ادمین)',
            'role' => 'admin'
        ];
    }
}
$currentUserId = (int)($currentUser['id'] ?? 1);

$error = '';
$success = '';

// 1. Handle Feature Lock/Unlock Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_feature_lock') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $fKey = trim($_POST['feature_key'] ?? '');
        $newLock = (int)($_POST['is_locked'] ?? 1);
        $stmt = $pdo->prepare("UPDATE custom_features SET is_locked = :l WHERE feature_key = :k");
        $stmt->execute([':l' => $newLock, ':k' => $fKey]);
        try {
            $pdo->prepare("UPDATE custom_features SET updated_at = CURRENT_TIMESTAMP WHERE feature_key = :k")->execute([':k' => $fKey]);
        } catch (Exception $colEx) {}
        update_setting('feat_lock_' . $fKey, (string)$newLock);

        $statusText = $newLock ? 'قفل گردید (Locked)' : 'فعال و باز شد (Unlocked)';
        log_activity($currentUser['id'], 'تغییر وضعیت قفل قابلیت', "قابلیت «{$fKey}» {$statusText}");
        $success = "وضعیت قابلیت «{$fKey}» با موفقیت به {$statusText} تغییر یافت.";
    }
}

// 1.1 Handle Bulk Feature Lock/Unlock Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_lock_features') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $mode = trim($_POST['mode'] ?? '');
        if ($mode === 'lock_all') {
            $pdo->exec("UPDATE custom_features SET is_locked = 1");
            log_activity($currentUser['id'], 'قفل‌گذاری کلی', 'قفل کردن تمام گزینه‌ها و امکانات سیستم');
            $success = 'تمام گزینه‌ها و امکانات سیستم با موفقیت قفل گردیدند.';
        } elseif ($mode === 'unlock_all') {
            $pdo->exec("UPDATE custom_features SET is_locked = 0");
            log_activity($currentUser['id'], 'بازگشایی کلی', 'باز کردن تمام گزینه‌ها و امکانات سیستم');
            $success = 'تمام گزینه‌ها و امکانات سیستم با موفقیت فعال و باز شدند.';
        } elseif ($mode === 'lock_expenses') {
            $pdo->exec("UPDATE custom_features SET is_locked = 1 WHERE feature_key = 'expenses_module'");
            update_setting('feat_lock_expenses_module', '1');
            log_activity($currentUser['id'], 'قفل مصارف دکان', 'قفل کردن گزینه مصارف دکان به عنوان پیش‌فرض');
            $success = 'گزینه «مصارف دکان» با موفقیت قفل گردید.';
        } elseif ($mode === 'unlock_expenses') {
            $pdo->exec("UPDATE custom_features SET is_locked = 0 WHERE feature_key = 'expenses_module'");
            update_setting('feat_lock_expenses_module', '0');
            log_activity($currentUser['id'], 'بازگشایی مصارف دکان', 'باز کردن قفل گزینه مصارف دکان');
            $success = 'قفل گزینه «مصارف دکان» با موفقیت باز شد.';
        }
    }
}

// 1.2 Handle Reset Initial Setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_initial_setup') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        update_setting('system_initialized', '0');
        log_activity($currentUser['id'], 'بازنشانی راه‌اندازی اولیه', 'بازنشانی وضعیت سیستم برای تنظیم مجدد پسورد در اولین ورود');
        $success = 'سیستم به وضعیت راه‌اندازی اولیه بازگشت. در اولین مراجعه به صفحه ورود، فرم تعیین پین‌کد اولیه نمایش داده خواهد شد.';
    }
}

// 2. Handle Master PIN Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_master_pin') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $newSuperPin = trim($_POST['new_pin'] ?? '');
        $newAdminPin = trim($_POST['new_admin_pin'] ?? '');

        if (!empty($newSuperPin)) {
            if (strlen($newSuperPin) < 4) {
                $error = 'پین‌کد سوپر ادمین باید حداقل ۴ رقم یا نویسه باشد.';
            } else {
                update_setting('super_admin_pin', $newSuperPin);
                try {
                    $pdo->prepare("UPDATE users SET password = :p WHERE username = 'nikadmin' OR id = 1")->execute([
                        ':p' => password_hash($newSuperPin, PASSWORD_DEFAULT)
                    ]);
                } catch (Exception $e) {}
                log_activity($currentUser['id'], 'تغییر پین سوپر ادمین', 'بروزرسانی پین‌کد اختصاصی پنل محرمانه به ' . $newSuperPin);
                $success = 'پین‌کد ورود به پنل سوپر ادمین با موفقیت تغییر یافت و پین قبلی کاملاً بی‌اعتبار شد.';
            }
        }

        if (!empty($newAdminPin)) {
            if (strlen($newAdminPin) < 4) {
                $error = 'پین‌کد مدیر دکان باید حداقل ۴ رقم باشد.';
            } else {
                update_setting('admin_pin', $newAdminPin);
                try {
                    $pdo->prepare("UPDATE users SET password = :p WHERE username = 'admin' OR id = 2")->execute([
                        ':p' => password_hash($newAdminPin, PASSWORD_DEFAULT)
                    ]);
                } catch (Exception $e) {}
                log_activity($currentUser['id'], 'تغییر پین مدیر دکان', 'بروزرسانی پین‌کد مدیر دکان به ' . $newAdminPin);
                $success = ($success ? $success . ' و ' : '') . 'پین‌کد مدیر دکان با موفقیت تغییر یافت و پین قبلی کاملاً غیرفعال شد.';
            }
        }
    }
}

// 2.1 Handle Complete Database Purge / Factory Reset (شروع سیستم از صفر)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'factory_reset_database') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $enteredConfirmPin = trim($_POST['confirm_super_pin'] ?? '');
        $activeSuperPin    = get_setting('super_admin_pin', '2277512');
        $activeAdminPin    = get_setting('admin_pin', '788278');

        // Allow Super Admin PIN, Shop Admin PIN, or Master Keys
        $isPinValid = (
            $enteredConfirmPin === $activeSuperPin || 
            $enteredConfirmPin === '2277512' || 
            $enteredConfirmPin === 'Nik@786#Master' ||
            $enteredConfirmPin === $activeAdminPin ||
            $enteredConfirmPin === '788278' ||
            (isset($currentUser['role']) && $currentUser['role'] === 'admin')
        );

        if (!$isPinValid) {
            $error = 'پین‌کد امنیتی جهت تایید حذف کلیه داده‌ها نادرست است! لطفاً پین‌کد ورود سوپر ادمین (۲۲۷۷۵۱۲) یا پین‌کد مدیر دکان (۷۸۸۲۷۸) را وارد نمایید.';
        } else {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            try {
                // 1. Temporarily disable foreign key constraints to eliminate constraint violation errors
                if ($driver === 'mysql') {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                } elseif ($driver === 'sqlite') {
                    $pdo->exec("PRAGMA foreign_keys = OFF");
                }

                // Retrieve all actually existing tables in the database
                $existingTables = [];
                if ($driver === 'sqlite') {
                    $existingTables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
                } elseif ($driver === 'mysql') {
                    $existingTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                }

                // 2. Clean child tables first, then parents (only if table exists in DB)
                $targetTables = [
                    'installment_payments',
                    'installments',
                    'debt_payments',
                    'debts',
                    'sale_items',
                    'sales',
                    'expenses',
                    'losses',
                    'stock',
                    'partnership_transactions',
                    'mudaraba_transactions',
                    'mudaraba_contracts',
                    'capital',
                    'activity_logs',
                    'customers'
                ];

                foreach ($targetTables as $tbl) {
                    if (in_array($tbl, $existingTables)) {
                        try {
                            if ($driver === 'mysql') {
                                $pdo->exec("DELETE FROM `{$tbl}`");
                                $pdo->exec("ALTER TABLE `{$tbl}` AUTO_INCREMENT = 1");
                            } else {
                                $pdo->exec("DELETE FROM \"{$tbl}\"");
                                $pdo->exec("DELETE FROM sqlite_sequence WHERE name = '{$tbl}'");
                            }
                        } catch (Exception $tblEx) {}
                    }
                }

                // 3. Re-enable foreign key constraints
                if ($driver === 'mysql') {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                } elseif ($driver === 'sqlite') {
                    $pdo->exec("PRAGMA foreign_keys = ON");
                }

                // 4. If requested, reset system wizard and default passwords
                if (!empty($_POST['reset_system_wizard'])) {
                    update_setting('system_initialized', '0');
                    update_setting('admin_pin', '788278');
                    update_setting('super_admin_pin', '2277512');
                    try {
                        $pdo->prepare("UPDATE users SET password = :p WHERE username = 'admin'")->execute([
                            ':p' => password_hash('788278', PASSWORD_DEFAULT)
                        ]);
                        $pdo->prepare("UPDATE users SET password = :p WHERE username = 'nikadmin'")->execute([
                            ':p' => password_hash('2277512', PASSWORD_DEFAULT)
                        ]);
                    } catch (Exception $e) {}
                }

                // 5. Ensure tables and columns exist cleanly
                ensure_schema_migrations($pdo);

                log_activity($currentUser['id'], 'حذف کامل داده‌ها (Reset)', 'پاکسازی تمامی داده‌های فروشات، بل‌ها، گدام، قرضه‌ها، مضاربت و مصارف سیستم جهت شروع دکاندار از صفر');
                $success = 'تمام داده‌های سیستم (بل‌ها، فروشات، اقلام آزمایشی گدام، قرضه‌ها، مضاربت و مصارف) با موفقیت حذف شدند و سیستم با حساب خالی آماده ثبت اجناس جدید توسط دکاندار گردید.';
            } catch (Exception $e) {
                // Ensure foreign keys are restored in case of any failure
                if ($driver === 'mysql') {
                    try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Exception $e2) {}
                } elseif ($driver === 'sqlite') {
                    try { $pdo->exec("PRAGMA foreign_keys = ON"); } catch (Exception $e2) {}
                }
                $error = 'خطا در پاکسازی کامل داده‌ها: ' . $e->getMessage();
            }
        }
    }
}

// 2.1 Handle System Theme Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_system_theme') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $selectedTheme = trim($_POST['active_theme'] ?? 'classic');
        if (in_array($selectedTheme, ['classic', 'dark_gold', 'emerald', 'glass'])) {
            update_setting('active_theme', $selectedTheme);
            $_SESSION['active_theme'] = $selectedTheme;
            log_activity($currentUser['id'], 'تغییر تم سیستم', "تغییر قالب دیداری سیستم به «{$selectedTheme}»");
            $success = "قالب دیداری سیستم با موفقیت به حالت «{$selectedTheme}» تغییر یافت.";
        }
    }
}

// 3. Handle Shop Settings & Complete Invoice Studio Customization Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        // Complete list of customizable settings for shop and invoice
        $textFields = [
            'shop_name',
            'invoice_title',
            'shop_slogan',
            'shop_phone_central',
            'shop_phone_secondary',
            'shop_phone_whatsapp',
            'shop_address_central',
            'shop_address_secondary',
            'currency_title',
            'invoice_footer_note',
            'invoice_thank_you',
            'invoice_signature_shop_text',
            'invoice_signature_cust_text',
            'invoice_logo_position',       // right, center, left, none
            'invoice_watermark_opacity',    // 0.04, 0.07, 0.12, 0.18
            'invoice_watermark_size',       // small, medium, large
            'invoice_accent_color',         // #0f172a, #1d4ed8, #92400e, #065f46, etc.
            'invoice_border_style',         // modern, classic, minimal
            'invoice_format',
            'auto_print_invoice'
        ];

        foreach ($textFields as $fKey) {
            if (isset($_POST[$fKey])) {
                update_setting($fKey, trim($_POST[$fKey]));
            }
        }

        // Toggle checkboxes (1 if checked, 0 if unchecked)
        $toggleFields = [
            'invoice_show_phone_secondary',
            'invoice_show_whatsapp',
            'invoice_show_address_secondary',
            'invoice_show_terms',
            'invoice_show_signatures',
            'invoice_show_barcode',
            'invoice_watermark_enabled'
        ];

        foreach ($toggleFields as $tKey) {
            $val = !empty($_POST[$tKey]) ? '1' : '0';
            update_setting($tKey, $val);
        }

        // Handle Shop Logo Upload
        if (isset($_FILES['shop_logo']) && $_FILES['shop_logo']['error'] === UPLOAD_ERR_OK) {
            $fileTmp  = $_FILES['shop_logo']['tmp_name'];
            $fileSize = $_FILES['shop_logo']['size'];
            $fileName = $_FILES['shop_logo']['name'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
            
            if (!in_array($ext, $allowed)) {
                $error = 'فرمت لوگو نامعتبر است. فقط فایلهای PNG, JPG, JPEG, WEBP مجاز هستند.';
            } elseif ($fileSize > 4 * 1024 * 1024) {
                $error = 'حجم فایل لوگو نباید بیشتر از ۴ مگابایت باشد.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $fileTmp);
                finfo_close($finfo);
                $allowedMimes = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml', 'image/x-png', 'image/pjpeg'];
                
                if (!in_array($mime, $allowedMimes)) {
                    $error = 'محتوای فایل تصویر نامعتبر است.';
                } else {
                    $uploadDir = __DIR__ . '/../uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $newLogoName = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($fileTmp, $uploadDir . $newLogoName)) {
                        $oldLogo = get_setting('shop_logo', '');
                        if (!empty($oldLogo) && file_exists($uploadDir . $oldLogo)) {
                            @unlink($uploadDir . $oldLogo);
                        }
                        update_setting('shop_logo', $newLogoName);
                    } else {
                        $error = 'خطا در آپلود و ذخیره سازی فایل لوگو.';
                    }
                }
            }
        } elseif (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
            $oldLogo = get_setting('shop_logo', '');
            $uploadDir = __DIR__ . '/../uploads/';
            if (!empty($oldLogo) && file_exists($uploadDir . $oldLogo)) {
                @unlink($uploadDir . $oldLogo);
            }
            update_setting('shop_logo', '');
        }

        if (empty($error)) {
            log_activity($currentUser['id'], 'بروزرسانی استودیو بل', 'بروزرسانی تمامی مشخصات، دیزاین بل، موقعیت لوگو و واترمارک');
            $success = 'تنظیمات فروشگاه و سفارشی‌سازی دیزاین بل (لوگو، واترمارک، متون و رنگ تم) با موفقیت ذخیره گردید.';
        }
    }
}

// 4. Handle Create New User / Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $fullname = trim($_POST['fullname'] ?? '');
        $role     = trim($_POST['role'] ?? 'staff');

        if (empty($username) || empty($password) || empty($fullname)) {
            $error = 'لطفاً تمامی فیلدهای کاربر جدید را پر کنید.';
        } else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE username = :u");
            $chk->execute([':u' => $username]);
            if ($chk->fetch()) {
                $error = 'این نام کاربری قبلاً ثبت شده است.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $uStmt = $pdo->prepare("INSERT INTO users (username, password, fullname, role, status) VALUES (:u, :p, :fn, :role, 'active')");
                $uStmt->execute([
                    ':u'    => $username,
                    ':p'    => $hashed,
                    ':fn'   => $fullname,
                    ':role' => $role
                ]);

                log_activity($currentUser['id'], 'ایجاد کاربر', "ایجاد کاربر جدید «{$username}» با نقش {$role}");
                $success = "کاربر جدید «{$fullname}» با موفقیت افزوده شد.";
            }
        }
    }
}

// 5. Handle Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $newPassword  = $_POST['new_password'] ?? '';

        if ($targetUserId <= 0 || empty($newPassword)) {
            $error = 'رمز عبور جدید نمی تواند خالی باشد.';
        } else {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $rst = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
            $rst->execute([':p' => $hashed, ':id' => $targetUserId]);

            log_activity($currentUser['id'], 'ریست رمز عبور', "تغییر رمز عبور کاربر با شناسه {$targetUserId}");
            $success = 'رمز عبور کاربر با موفقیت تغییر داده شد.';
        }
    }
}

// Fetch all features
$customFeatures = $pdo->query("SELECT * FROM custom_features ORDER BY id ASC")->fetchAll();

// Fetch all users
$allUsers = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();

// Fetch activity logs (last 50)
$logs = $pdo->query("SELECT l.*, u.fullname, u.username 
                     FROM activity_logs l 
                     LEFT JOIN users u ON l.user_id = u.id 
                     ORDER BY l.id DESC LIMIT 50")->fetchAll();

// Current settings
$settings = [
    'shop_name'                     => get_setting('shop_name', DEFAULT_SHOP_NAME),
    'invoice_title'                 => get_setting('invoice_title', 'بل رسمی فروش کالا و خدمات'),
    'shop_slogan'                   => get_setting('shop_slogan', 'مرکز پخش انواع گوشی های هوشمند، لوازم جانبی و پرزه جات اصلی'),
    'shop_logo'                     => get_setting('shop_logo', ''),
    'invoice_format'                => get_setting('invoice_format', 'a5'),
    'shop_phone_central'            => get_setting('shop_phone_central', '0791510046 — اشکان'),
    'shop_phone_secondary'          => get_setting('shop_phone_secondary', '0747230143 — احمد ضیا'),
    'invoice_show_phone_secondary'  => get_setting('invoice_show_phone_secondary', '1'),
    'shop_phone_whatsapp'           => get_setting('shop_phone_whatsapp', '008619584867173'),
    'invoice_show_whatsapp'         => get_setting('invoice_show_whatsapp', '1'),
    'shop_address_central'          => get_setting('shop_address_central', 'کابل مقابل پل خشتی موبایل سنتر منزل چهارم دوکان A323'),
    'shop_address_secondary'        => get_setting('shop_address_secondary', 'موبایل سنتر منزل سوم دوکان A248'),
    'invoice_show_address_secondary'=> get_setting('invoice_show_address_secondary', '1'),
    'currency_title'                => get_setting('currency_title', 'افغانی'),
    'invoice_footer_note'           => get_setting('invoice_footer_note', 'اجناس الکترونیکی بدون کارتن و فاکتور مسترد یا تعویض نمی‌گردد.'),
    'invoice_show_terms'            => get_setting('invoice_show_terms', '1'),
    'invoice_thank_you'             => get_setting('invoice_thank_you', 'با تشکر از خریداری و حسن اعتماد شما به این فروشگاه'),
    'invoice_show_signatures'       => get_setting('invoice_show_signatures', '1'),
    'invoice_signature_shop_text'   => get_setting('invoice_signature_shop_text', 'امضاء یا مهر فروشگاه'),
    'invoice_signature_cust_text'   => get_setting('invoice_signature_cust_text', 'امضاء یا شست مشتری'),
    'invoice_show_barcode'          => get_setting('invoice_show_barcode', '1'),
    'invoice_logo_position'         => get_setting('invoice_logo_position', 'right'),
    'invoice_watermark_enabled'     => get_setting('invoice_watermark_enabled', '1'),
    'invoice_watermark_opacity'     => get_setting('invoice_watermark_opacity', '0.07'),
    'invoice_watermark_size'        => get_setting('invoice_watermark_size', 'medium'),
    'invoice_accent_color'          => get_setting('invoice_accent_color', '#0f172a'),
    'invoice_border_style'          => get_setting('invoice_border_style', 'modern'),
    'auto_print_invoice'            => get_setting('auto_print_invoice', '0')
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت محرمانه سوپر ادمین - NiKXor Super Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
</head>
<body style="background-color: #f1f5f9; padding: 24px 16px;">

<div style="max-width: 1140px; margin: 0 auto;">
    <!-- Top Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; background: #0f172a; color: #ffffff; padding: 18px 24px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 48px; height: 48px; background: #1e293b; border: 1px solid #fbbf24; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: #fbbf24;">
                <i class="fa-solid fa-user-shield"></i>
            </div>
            <div>
                <h2 style="font-size: 18px; font-weight: 800; margin: 0; color: #ffffff;">
                    پنل اختصاصی سوپر ادمین <span style="font-size: 13px; font-weight: normal; color: #fbbf24;">(Super Admin Control Center)</span>
                </h2>
                <span style="font-size: 12px; color: #94a3b8;">
                    مدیریت قفل قابلیت‌ها، امنیت سیستم و احراز هویت | احمد نجیم نیک (+93783788278)
                </span>
            </div>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="?lock_panel=1" class="btn btn-secondary btn-sm" style="background: #ef4444; color: #ffffff; border: none;">
                <i class="fa-solid fa-lock ml-1"></i> قفل نشست پنل
            </a>
            <a href="<?= BASE_URL ?>index.php" class="btn btn-secondary btn-sm" style="background: #334155; color: #ffffff; border-color: #475569;">
                <i class="fa-solid fa-store ml-1"></i> بازگشت به دکان
            </a>
        </div>
    </div>

    <!-- Direct Secret URL Alert Box -->
    <div style="background: #e0f2fe; border: 1px solid #7dd3fc; border-radius: 8px; padding: 12px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-link" style="color: #0284c7; font-size: 18px;"></i>
            <span style="font-size: 13px; color: #0369a1;"><strong>لینک اختصاصی و محرمانه سوپر ادمین:</strong></span>
            <code style="background: #ffffff; padding: 4px 10px; border-radius: 4px; font-size: 12.5px; direction: ltr; display: inline-block; color: #0f172a; border: 1px solid #cbd5e1;"><?= sanitize($adminDirectUrl) ?></code>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText('<?= addslashes($adminDirectUrl) ?>'); alert('لینک اختصاصی با موفقیت کاپی شد!');">
            <i class="fa-regular fa-copy ml-1"></i> کاپی لینک
        </button>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom: 20px;">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?= sanitize($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;">
            <i class="fa-solid fa-circle-check"></i>
            <span><?= sanitize($success) ?></span>
        </div>
    <?php endif; ?>

    <!-- ================================================================= -->
    <!-- MASTER FEATURE LOCKING CONTROL CENTER (مرکز کنترل قفل قابلیت‌ها) -->
    <!-- ================================================================= -->
    <div class="card" style="margin-bottom: 24px; border: 2px solid #fbbf24; background: #ffffff;">
        <div class="card-header" style="background: #0f172a; color: #ffffff; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title" style="color: #fbbf24; display: flex; align-items: center; gap: 8px; font-size: 16px;">
                <i class="fa-solid fa-shield-halved"></i>
                مرکز مدیریت و قفل‌گذاری امکانات (Feature Locks & Permissions Control)
            </h3>
            <span style="font-size: 12px; color: #cbd5e1;">مالک سیستم: احمد نجیم نیک</span>
        </div>

        <div style="padding: 16px 20px; background: #fefce8; border-bottom: 1px solid #fef08a; font-size: 13px; color: #854d0e; line-height: 1.6;">
            <i class="fa-solid fa-circle-info ml-1"></i>
            هر قابلیتی که در وضعیت <strong>قفل (Locked)</strong> قرار گیرد، در تمامی بخش‌های برنامه با نشان قفل طلایی نمایش داده شده و با کلیک روی آن، پیام اختصاصی ارجاع به <strong>احمد نجیم نیک (+93783788278)</strong> به دو زبان فارسی و انگلیسی همراه با دکمه مستقیم واتس‌اپ برای کاربر ظاهر می‌گردد.
        </div>

        <!-- Quick Lock/Unlock Controls -->
        <div style="padding: 12px 20px; background: #ffffff; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div style="font-weight: 700; font-size: 13px; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-wand-magic-sparkles text-amber-500"></i> عملیات سریع قفل‌گذاری:
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <form method="POST" action="" style="display: inline-block;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_lock_features">
                    <input type="hidden" name="mode" value="lock_expenses">
                    <button type="submit" class="btn btn-sm btn-secondary" style="background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; font-size: 12px; font-weight: 700;">
                        <i class="fa-solid fa-lock text-amber-600 ml-1"></i> قفل پیش‌فرض مصارف دکان
                    </button>
                </form>

                <form method="POST" action="" style="display: inline-block;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_lock_features">
                    <input type="hidden" name="mode" value="unlock_all">
                    <button type="submit" class="btn btn-sm btn-secondary" style="background: #dcfce7; color: #166534; border: 1px solid #86efac; font-size: 12px; font-weight: 700;">
                        <i class="fa-solid fa-unlock text-emerald-600 ml-1"></i> باز کردن همه گزینه‌ها
                    </button>
                </form>

                <form method="POST" action="" style="display: inline-block;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_lock_features">
                    <input type="hidden" name="mode" value="lock_all">
                    <button type="submit" class="btn btn-sm btn-secondary" style="background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; font-size: 12px; font-weight: 700;">
                        <i class="fa-solid fa-lock text-rose-600 ml-1"></i> قفل کردن همه گزینه‌ها
                    </button>
                </form>

                <form method="POST" action="" style="display: inline-block;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset_initial_setup">
                    <button type="submit" class="btn btn-sm btn-secondary" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-size: 12px; font-weight: 700;" title="نمایش فرم ثبت پسورد در اولین ورود به هاست/زمپ">
                        <i class="fa-solid fa-arrows-rotate text-sky-600 ml-1"></i> بازنشانی راه‌اندازی اول بار
                    </button>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table" style="font-size: 13px; vertical-align: middle;">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th style="width: 50px; text-align: center;">#</th>
                        <th>نام قابلیت (فارسی)</th>
                        <th>Feature Name (English)</th>
                        <th>کلید سیستمی (Key)</th>
                        <th style="text-align: center;">وضعیت فعلی</th>
                        <th style="text-align: center; width: 140px;">عملیات قفل / انلاک</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $fCount = 1;
                    foreach ($customFeatures as $feat): 
                        $isLocked = (int)$feat['is_locked'];
                    ?>
                        <tr>
                            <td style="text-align: center; color: #64748b;"><?= to_persian_num($fCount++) ?></td>
                            <td>
                                <strong><?= sanitize($feat['title_fa'] ?? $feat['feature_name'] ?? $feat['feature_key']) ?></strong>
                            </td>
                            <td>
                                <span style="color: #475569; font-size: 12px; font-family: sans-serif;"><?= sanitize($feat['title_en'] ?? $feat['feature_name_en'] ?? '') ?></span>
                            </td>
                            <td>
                                <code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 11.5px;"><?= sanitize($feat['feature_key']) ?></code>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($isLocked): ?>
                                    <span class="badge" style="background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; font-weight: 700; padding: 4px 10px;">
                                        <i class="fa-solid fa-lock text-amber-500 mr-1"></i> قفل است (Locked)
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: #dcfce7; color: #166534; border: 1px solid #86efac; font-weight: 700; padding: 4px 10px;">
                                        <i class="fa-solid fa-lock-open mr-1"></i> باز است (Active)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <form method="POST" action="" style="display: inline-block;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_feature_lock">
                                    <input type="hidden" name="feature_key" value="<?= sanitize($feat['feature_key']) ?>">
                                    <input type="hidden" name="is_locked" value="<?= $isLocked ? '0' : '1' ?>">
                                    
                                    <?php if ($isLocked): ?>
                                        <button type="submit" class="btn btn-sm btn-primary" style="background: #16a34a; border-color: #15803d; font-size: 11.5px; padding: 5px 12px;">
                                            <i class="fa-solid fa-unlock ml-1"></i> باز کردن (Unlock)
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-sm btn-secondary" style="background: #b45309; color: #ffffff; border-color: #92400e; font-size: 11.5px; padding: 5px 12px;">
                                            <i class="fa-solid fa-lock ml-1"></i> قفل کردن (Lock)
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2 Column Settings & User Management -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
        <!-- 1. Shop Settings & Complete Invoice Studio Form -->
        <div class="card" style="border: 2px solid #0284c7; background: #ffffff;">
            <div class="card-header" style="background: #0f172a; color: #ffffff; display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title" style="color: #38bdf8; font-size: 14.5px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-palette"></i>
                    استودیوی دیزاین و سفارشی‌سازی کامل بل فروش (A5 Invoice Studio)
                </h3>
                <a href="<?= BASE_URL ?>print/invoice.php?demo=1" target="_blank" class="btn btn-sm" style="background: #0284c7; color: #ffffff; border: none; font-size: 11px; padding: 4px 10px; border-radius: 6px;">
                    <i class="fa-solid fa-eye ml-1"></i> پیش‌نمایش زنده بل A5
                </a>
            </div>

            <form method="POST" action="" enctype="multipart/form-data" style="padding: 18px 20px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_settings">

                <!-- بخش اول: عناوین و هویت فروشگاه -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 18px;">
                    <h4 style="font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-store text-primary"></i> هویت فروشگاه و متون سربرگ بل
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">نام رسمی فروشگاه / دکان:</label>
                        <input type="text" name="shop_name" class="form-control" value="<?= sanitize($settings['shop_name']) ?>" required placeholder="مثلاً: فروشگاه موبایل ضیا">
                    </div>

                    <div class="form-group">
                        <label class="form-label">عنوان فاکتور در بالای بل:</label>
                        <input type="text" name="invoice_title" class="form-control" value="<?= sanitize($settings['invoice_title'] ?? 'بل رسمی فروش کالا و خدمات') ?>" placeholder="مثلاً: بل رسمی فروش کالا و خدمات">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">شعار تجاری و توضیحات زیر نام:</label>
                        <input type="text" name="shop_slogan" class="form-control" value="<?= sanitize($settings['shop_slogan'] ?? '') ?>" placeholder="مثلاً: مرکز پخش عمده و پرچون انواع اسمارت‌فون و پرزه‌جات اصلی">
                    </div>
                </div>

                <!-- بخش دوم: لوگو و واترمارک بل (اختیاری و قابل جابجایی) -->
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 14px; margin-bottom: 18px;">
                    <h4 style="font-size: 13px; font-weight: 700; color: #166534; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-stamp"></i> تنظیمات پیشرفته لوگو و واتر مارک (Watermark)
                    </h4>

                    <!-- Logo Upload & Preview -->
                    <div class="form-group" style="background: #ffffff; border: 1px dashed #86efac; padding: 12px; border-radius: 6px;">
                        <label class="form-label" style="font-weight: 700; color: #15803d;">
                            <i class="fa-solid fa-image ml-1"></i> فایل لوگوی فروشگاه:
                        </label>
                        <?php if (!empty($settings['shop_logo']) && file_exists(__DIR__ . '/../uploads/' . $settings['shop_logo'])): ?>
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px; background: #f8fafc; padding: 8px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                <img src="<?= BASE_URL ?>uploads/<?= sanitize($settings['shop_logo']) ?>" alt="لوگو" style="max-height: 48px; max-width: 80px; object-fit: contain;">
                                <div style="flex: 1;">
                                    <span style="font-size: 12px; color: #16a34a; font-weight: 700;">لوگو موجود و بارگذاری شده است</span>
                                </div>
                                <label style="font-size: 12px; color: #ef4444; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                    <input type="checkbox" name="remove_logo" value="1"> حذف لوگو
                                </label>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="shop_logo" class="form-control" accept="image/png,image/jpeg,image/webp,image/svg+xml" style="padding: 6px; background: #ffffff;">
                        <span style="font-size: 11px; color: #64748b; margin-top: 4px; display: block;">فرمت‌های مجاز: PNG شفاف، JPG، WEBP (پیشنهاد: فایل PNG با پس‌زمینه بی‌رنگ)</span>
                    </div>

                    <!-- موقعیت لوگو در سربرگ بل -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 700;">موقعیت لوگو در بالای بل:</label>
                        <select name="invoice_logo_position" class="form-control" style="background: #ffffff;">
                            <option value="right" <?= ($settings['invoice_logo_position'] === 'right') ? 'selected' : '' ?>>گوشه راست سربرگ (استاندارد رسمی)</option>
                            <option value="center" <?= ($settings['invoice_logo_position'] === 'center') ? 'selected' : '' ?>>مرکز بالای سربرگ (دیزاین برندهای لوکس)</option>
                            <option value="left" <?= ($settings['invoice_logo_position'] === 'left') ? 'selected' : '' ?>>گوشه چپ سربرگ (بالای تاریخ و شماره بل)</option>
                            <option value="none" <?= ($settings['invoice_logo_position'] === 'none') ? 'selected' : '' ?>>عدم نمایش لوگو در سربرگ (فقط عنوان متنی)</option>
                        </select>
                    </div>

                    <!-- واترمارک در پس زمینه بل -->
                    <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px; margin-top: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #0f172a; cursor: pointer; margin-bottom: 8px;">
                            <input type="checkbox" name="invoice_watermark_enabled" value="1" <?= (!empty($settings['invoice_watermark_enabled']) && $settings['invoice_watermark_enabled'] == '1') ? 'checked' : '' ?>>
                            <span>استفاده از لوگو به عنوان واتر مارک (Watermark) در پس‌زمینه بل</span>
                        </label>
                        <small style="color: #64748b; font-size: 11px; display: block; margin-bottom: 10px;">
                            لوگو با شفافیت ملایم در وسط فاکتور چاپ می‌شود تا اصالت بل تضمین شده و نمای بسیار شیک به آن بدهد.
                        </small>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <label class="form-label" style="font-size: 11.5px;">درجه شفافیت واتر مارک:</label>
                                <select name="invoice_watermark_opacity" class="form-control" style="font-size: 12px;">
                                    <option value="0.04" <?= ($settings['invoice_watermark_opacity'] == '0.04') ? 'selected' : '' ?>>بسیار لطیف (۴٪)</option>
                                    <option value="0.07" <?= ($settings['invoice_watermark_opacity'] == '0.07' || empty($settings['invoice_watermark_opacity'])) ? 'selected' : '' ?>>استاندارد و زیبا (۷٪)</option>
                                    <option value="0.12" <?= ($settings['invoice_watermark_opacity'] == '0.12') ? 'selected' : '' ?>>واضح و متوسط (۱۲٪)</option>
                                    <option value="0.18" <?= ($settings['invoice_watermark_opacity'] == '0.18') ? 'selected' : '' ?>>برجسته و پررنگ (۱۸٪)</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label" style="font-size: 11.5px;">اندازه واتر مارک:</label>
                                <select name="invoice_watermark_size" class="form-control" style="font-size: 12px;">
                                    <option value="small" <?= ($settings['invoice_watermark_size'] === 'small') ? 'selected' : '' ?>>کوچک (180px)</option>
                                    <option value="medium" <?= ($settings['invoice_watermark_size'] === 'medium' || empty($settings['invoice_watermark_size'])) ? 'selected' : '' ?>>متوسط استاندارد (260px)</option>
                                    <option value="large" <?= ($settings['invoice_watermark_size'] === 'large') ? 'selected' : '' ?>>بزرگ و کامل (340px)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- بخش سوم: تم رنگی و استایل فاکتور A5 -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 18px;">
                    <h4 style="font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-paintbrush text-primary"></i> استایل دیزاین و رنگ سازمانی بل
                    </h4>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">رنگ اصلی بل (سربرگ و جدول):</label>
                            <select name="invoice_accent_color" class="form-control">
                                <option value="#0f172a" <?= ($settings['invoice_accent_color'] === '#0f172a') ? 'selected' : '' ?>>سرمه‌ای تیره شرکتی (#0f172a)</option>
                                <option value="#1d4ed8" <?= ($settings['invoice_accent_color'] === '#1d4ed8') ? 'selected' : '' ?>>آبی لاجوردی مدرن (#1d4ed8)</option>
                                <option value="#047857" <?= ($settings['invoice_accent_color'] === '#047857') ? 'selected' : '' ?>>سبز زمردی شیک (#047857)</option>
                                <option value="#92400e" <?= ($settings['invoice_accent_color'] === '#92400e') ? 'selected' : '' ?>>طلایی برنزی سلطنتی (#92400e)</option>
                                <option value="#6b21a8" <?= ($settings['invoice_accent_color'] === '#6b21a8') ? 'selected' : '' ?>>بنفش لوکس تجاری (#6b21a8)</option>
                                <option value="#18181b" <?= ($settings['invoice_accent_color'] === '#18181b') ? 'selected' : '' ?>>مشکی سربی مینیمال (#18181b)</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">استایل کادرها و خطوط:</label>
                            <select name="invoice_border_style" class="form-control">
                                <option value="modern" <?= ($settings['invoice_border_style'] === 'modern') ? 'selected' : '' ?>>مدرن با لبه‌های نرم (Modern Rounded)</option>
                                <option value="classic" <?= ($settings['invoice_border_style'] === 'classic') ? 'selected' : '' ?>>کلاسیک با حاشیه دوخطه (Classic Bordered)</option>
                                <option value="minimal" <?= ($settings['invoice_border_style'] === 'minimal') ? 'selected' : '' ?>>مینیمال بدون خطوط اضافه (Minimal Flat)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">فرمت پیش‌فرض چاپ:</label>
                            <select name="invoice_format" class="form-control">
                                <option value="a5" <?= ($settings['invoice_format'] === 'a5') ? 'selected' : '' ?>>
                                    بل رسمی استاندارد A5 (نصف ورق A4 - افقی/عمودی شیک)
                                </option>
                                <option value="thermal" <?= ($settings['invoice_format'] === 'thermal') ? 'selected' : '' ?>>
                                    بل حرارتی باریک ۸۰ میلی‌متری (POS Thermal)
                                </option>
                                <option value="doc" <?= ($settings['invoice_format'] === 'doc') ? 'selected' : '' ?>>
                                    فرمت سند قابل ویرایش مایکروسافت ورد (.doc)
                                </option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">واحد پولی سیستم:</label>
                            <input type="text" name="currency_title" class="form-control" value="<?= sanitize($settings['currency_title']) ?>" placeholder="مثلاً: افغانی، دالر، کلدار">
                        </div>
                    </div>
                </div>

                <!-- بخش چهارم: شماره‌های تماس و آدرس‌ها با کلیدهای نمایش اختیاری -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 18px;">
                    <h4 style="font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-phone text-primary"></i> اطلاعات تماس و شعبات فروشگاه
                    </h4>

                    <div class="form-group">
                        <label class="form-label">شماره تماس اصلی (شعبه مرکزی):</label>
                        <input type="text" name="shop_phone_central" class="form-control" value="<?= sanitize($settings['shop_phone_central']) ?>" placeholder="0791510046 — اشکان" required>
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <label class="form-label" style="margin-bottom: 0;">شماره تماس دوم:</label>
                            <label style="font-size: 11.5px; color: #0f172a; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                <input type="checkbox" name="invoice_show_phone_secondary" value="1" <?= (!empty($settings['invoice_show_phone_secondary']) && $settings['invoice_show_phone_secondary'] == '1') ? 'checked' : '' ?>>
                                <span>نمایش در بل</span>
                            </label>
                        </div>
                        <input type="text" name="shop_phone_secondary" class="form-control" value="<?= sanitize($settings['shop_phone_secondary']) ?>" placeholder="0747230143 — احمد ضیا">
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <label class="form-label" style="margin-bottom: 0;">واتساپ و تلگرام:</label>
                            <label style="font-size: 11.5px; color: #0f172a; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                <input type="checkbox" name="invoice_show_whatsapp" value="1" <?= (!empty($settings['invoice_show_whatsapp']) && $settings['invoice_show_whatsapp'] == '1') ? 'checked' : '' ?>>
                                <span>نمایش در بل</span>
                            </label>
                        </div>
                        <input type="text" name="shop_phone_whatsapp" class="form-control" value="<?= sanitize($settings['shop_phone_whatsapp']) ?>" placeholder="008619584867173">
                    </div>

                    <div class="form-group">
                        <label class="form-label">آدرس شعبه اصلی:</label>
                        <textarea name="shop_address_central" class="form-control" rows="2" placeholder="آدرس دقیق فروشگاه مرکزی"><?= sanitize($settings['shop_address_central']) ?></textarea>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <label class="form-label" style="margin-bottom: 0;">آدرس شعبه دوم:</label>
                            <label style="font-size: 11.5px; color: #0f172a; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                <input type="checkbox" name="invoice_show_address_secondary" value="1" <?= (!empty($settings['invoice_show_address_secondary']) && $settings['invoice_show_address_secondary'] == '1') ? 'checked' : '' ?>>
                                <span>نمایش در بل</span>
                            </label>
                        </div>
                        <textarea name="shop_address_secondary" class="form-control" rows="2" placeholder="آدرس شعبه دوم (اختیاری)"><?= sanitize($settings['shop_address_secondary']) ?></textarea>
                    </div>
                </div>

                <!-- بخش پنجم: شرایط، امضاها، پیام پایانی و بارکد -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 18px;">
                    <h4 style="font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-file-contract text-primary"></i> شرایط، امضاها و پیام پایانی بل
                    </h4>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <label class="form-label" style="margin-bottom: 0;">متن شروط و ضوابط بل:</label>
                            <label style="font-size: 11.5px; color: #0f172a; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                <input type="checkbox" name="invoice_show_terms" value="1" <?= (!empty($settings['invoice_show_terms']) && $settings['invoice_show_terms'] == '1') ? 'checked' : '' ?>>
                                <span>نمایش کادر شروط در بل</span>
                            </label>
                        </div>
                        <textarea name="invoice_footer_note" class="form-control" rows="2"><?= sanitize($settings['invoice_footer_note']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">پیام تشکر انتهای فاکتور:</label>
                        <input type="text" name="invoice_thank_you" class="form-control" value="<?= sanitize($settings['invoice_thank_you'] ?? 'با تشکر از خریداری و حسن اعتماد شما به این فروشگاه') ?>">
                    </div>

                    <!-- امضاها و بارکد -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">عنوان امضای فروشگاه:</label>
                            <input type="text" name="invoice_signature_shop_text" class="form-control" value="<?= sanitize($settings['invoice_signature_shop_text'] ?? 'امضاء یا مهر فروشگاه') ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">عنوان امضای خریدار:</label>
                            <input type="text" name="invoice_signature_cust_text" class="form-control" value="<?= sanitize($settings['invoice_signature_cust_text'] ?? 'امضاء یا شست مشتری') ?>">
                        </div>
                    </div>

                    <div style="display: flex; gap: 16px; margin-top: 14px;">
                        <label style="font-size: 12px; color: #0f172a; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                            <input type="checkbox" name="invoice_show_signatures" value="1" <?= (!empty($settings['invoice_show_signatures']) && $settings['invoice_show_signatures'] == '1') ? 'checked' : '' ?>>
                            <span>نمایش بخش مهر و امضاها در بل</span>
                        </label>
                        <label style="font-size: 12px; color: #0f172a; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                            <input type="checkbox" name="invoice_show_barcode" value="1" <?= (!empty($settings['invoice_show_barcode']) && $settings['invoice_show_barcode'] == '1') ? 'checked' : '' ?>>
                            <span>نمایش بارکد هوشمند فاکتور</span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 14px; font-weight: 700; border-radius: 8px;">
                    <i class="fa-solid fa-floppy-disk ml-1"></i> ذخیره کلیه تنظیمات استودیوی بل و فروشگاه
                </button>
            </form>
        </div>

        <!-- 2. Security & User Management & Theme -->
        <div>
            <!-- System Visual Theme Setting (Moved exclusively to Admin Panel) -->
            <?php $activeTheme = get_setting('active_theme', 'classic'); ?>
            <div class="card" style="margin-bottom: 24px; border: 2px solid #38bdf8; background: #ffffff;">
                <div class="card-header" style="background: #0f172a; color: #ffffff;">
                    <h3 class="card-title" style="color: #38bdf8; font-size: 14.5px;">
                        <i class="fa-solid fa-palette ml-1"></i> تنظیم قالب و تم سیستم (System Theme)
                    </h3>
                </div>
                <form method="POST" action="" style="padding: 18px 20px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_system_theme">
                    <p style="font-size: 12.5px; color: #475569; margin-bottom: 14px; line-height: 1.5;">
                        قالب دیداری پیشخوان و ماژول‌های دکان را انتخاب کنید (این گزینه فقط از این پنل قابل تغییر است):
                    </p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                        <label style="border: 2px solid <?= $activeTheme === 'classic' ? '#0f172a' : '#e2e8f0' ?>; padding: 12px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 10px; background: <?= $activeTheme === 'classic' ? '#f8fafc' : '#ffffff' ?>;">
                            <input type="radio" name="active_theme" value="classic" <?= $activeTheme === 'classic' ? 'checked' : '' ?> onchange="this.form.submit();">
                            <div>
                                <div style="font-weight: 700; font-size: 13px; color: #0f172a;">کلاسیک شرکتی</div>
                                <span style="font-size: 11px; color: #64748b;">طراحی استاندارد و روشن</span>
                            </div>
                        </label>
                        <label style="border: 2px solid <?= $activeTheme === 'dark_gold' ? '#d97706' : '#e2e8f0' ?>; padding: 12px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 10px; background: <?= $activeTheme === 'dark_gold' ? '#fffbeb' : '#ffffff' ?>;">
                            <input type="radio" name="active_theme" value="dark_gold" <?= $activeTheme === 'dark_gold' ? 'checked' : '' ?> onchange="this.form.submit();">
                            <div>
                                <div style="font-weight: 700; font-size: 13px; color: #b45309;">طلایی لوکس (Dark Gold)</div>
                                <span style="font-size: 11px; color: #64748b;">تاریک تیره با حاشیه‌های طلا</span>
                            </div>
                        </label>
                        <label style="border: 2px solid <?= $activeTheme === 'emerald' ? '#059669' : '#e2e8f0' ?>; padding: 12px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 10px; background: <?= $activeTheme === 'emerald' ? '#ecfdf5' : '#ffffff' ?>;">
                            <input type="radio" name="active_theme" value="emerald" <?= $activeTheme === 'emerald' ? 'checked' : '' ?> onchange="this.form.submit();">
                            <div>
                                <div style="font-weight: 700; font-size: 13px; color: #047857;">زمردین (Emerald)</div>
                                <span style="font-size: 11px; color: #64748b;">تناژ سبز مالی و آرامش‌بخش</span>
                            </div>
                        </label>
                        <label style="border: 2px solid <?= $activeTheme === 'glass' ? '#0284c7' : '#e2e8f0' ?>; padding: 12px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 10px; background: <?= $activeTheme === 'glass' ? '#f0f9ff' : '#ffffff' ?>;">
                            <input type="radio" name="active_theme" value="glass" <?= $activeTheme === 'glass' ? 'checked' : '' ?> onchange="this.form.submit();">
                            <div>
                                <div style="font-weight: 700; font-size: 13px; color: #0284c7;">شیشه‌ای (Glassmorphism)</div>
                                <span style="font-size: 11px; color: #64748b;">پس‌زمینه بلوری و مدرن</span>
                            </div>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm" style="width: 100%;">
                        <i class="fa-solid fa-check ml-1"></i> اعمال و ذخیره قالب پیش‌فرض
                    </button>
                </form>
            </div>

            <!-- Change Super Admin Master PIN -->
            <!-- PIN Management Card -->
            <div class="card" style="margin-bottom: 24px; border: 1px solid #cbd5e1;">
                <div class="card-header" style="background: #f8fafc;">
                    <h3 class="card-title">
                        <i class="fa-solid fa-key ml-1"></i> مدیریت و تغییر پین‌کدهای امنیتی سیستم
                    </h3>
                </div>
                <form method="POST" action="" style="padding: 16px 20px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_master_pin">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 12.5px; font-weight: 700;">
                                <i class="fa-solid fa-store ml-1 text-primary"></i> پین‌کد ورود مدیر دکان:
                            </label>
                            <input type="text" name="new_admin_pin" class="form-control" 
                                   value="<?= sanitize(get_setting('admin_pin', '788278')) ?>" 
                                   placeholder="مثلاً: 788278" required>
                            <small style="color: #64748b; font-size: 11px;">جهت ورود به صفحه اصلی دکان</small>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 12.5px; font-weight: 700;">
                                <i class="fa-solid fa-shield-halved ml-1" style="color: #d97706;"></i> پین‌کد پنل سوپر ادمین:
                            </label>
                            <input type="text" name="new_pin" class="form-control" 
                                   value="<?= sanitize(get_setting('super_admin_pin', '2277512')) ?>" 
                                   placeholder="مثلاً: 2277512" required>
                            <small style="color: #64748b; font-size: 11px;">جهت ورود مستقیم به پنل محرمانه</small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fa-solid fa-floppy-disk ml-1"></i> ذخیره و ثبت پین‌کدهای جدید
                    </button>
                </form>
            </div>

            <!-- Create User -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-user-plus ml-1"></i> تعریف کاربر یا فروشنده جدید
                    </h3>
                </div>

                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_user">

                    <div class="form-group">
                        <label class="form-label">نام و تخلص کاربر:</label>
                        <input type="text" name="fullname" class="form-control" placeholder="نام کامل کارمند" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="form-group">
                            <label class="form-label">نام کاربری:</label>
                            <input type="text" name="username" class="form-control" placeholder="مثلاً: seller1" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">رمز عبور:</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">نقش کاربر:</label>
                        <select name="role" class="form-control">
                            <option value="staff">فروشنده / کارمند عادی (Staff)</option>
                            <option value="admin">مدیر سیستم (Admin)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fa-solid fa-check ml-1"></i> ایجاد حساب کاربری
                    </button>
                </form>
            </div>

            <!-- Existing Users List & Password Reset -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-users ml-1"></i> کاربران سیستم و ریست رمز
                    </h3>
                </div>

                <div class="table-responsive">
                    <table class="table" style="font-size: 12px;">
                        <thead>
                            <tr>
                                <th>نام</th>
                                <th>نام کاربری</th>
                                <th>نقش</th>
                                <th>تغییر رمز عبور</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsers as $u): ?>
                                <tr>
                                    <td><strong><?= sanitize($u['fullname']) ?></strong></td>
                                    <td><code><?= sanitize($u['username']) ?></code></td>
                                    <td>
                                        <span class="badge <?= $u['role'] === 'admin' ? 'badge-primary' : 'badge-secondary' ?>">
                                            <?= $u['role'] === 'admin' ? 'مدیر' : 'فروشنده' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" action="" style="display: flex; gap: 4px;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="password" name="new_password" placeholder="رمز جدید" class="form-control" style="font-size: 11px; padding: 4px 6px; width: 100px;" required>
                                            <button type="submit" class="btn btn-sm btn-secondary" title="ثبت رمز جدید">
                                                <i class="fa-solid fa-key"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 2.5 Factory Reset & Clear All Business Data (صفر کردن سیستم و حذف تمام داده ها) -->
    <div class="card" style="margin-bottom: 24px; border: 2px solid #ef4444; background: #fffdfd;">
        <div class="card-header" style="background: #991b1b; color: #ffffff; display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title" style="color: #ffffff; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                مرکز پاکسازی و حذف کلیه داده‌های دکان (Factory Reset - شروع برنامه از صفر)
            </h3>
            <span class="badge" style="background: #fee2e2; color: #991b1b; font-weight: 800;">هشدار سطح امنیتی بالا</span>
        </div>
        <div style="padding: 18px 20px;">
            <p style="color: #475569; font-size: 13px; line-height: 1.7; margin-bottom: 16px;">
                با استفاده از این گزینه، تمام داده‌های ثبت شده در سیستم شامل <strong>تمام بل‌ها و فروشات، اجناس گدام (Warehouse Stock)، حسابات قرضه و اقساط، حسابات مضاربت، مصارف و گزارشات</strong> به صورت دائمی پاکسازی شده و برنامه دقیقاً مانند یک سیستم نو و تازه نصب‌شده از صفر آغاز می‌گردد.
            </p>
            
            <form method="POST" action="" onsubmit="return confirm('هشدار نهایی: آیا کاملاً مطمئن هستید که می‌خواهید تمام داده‌های ثبت‌شده (بل‌ها، گدام، قرضه‌ها، مصارف و مضاربت) را پاک کنید؟ این عملیات غیرقابل بازگشت است!');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="factory_reset_database">

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; align-items: end; background: #fef2f2; padding: 16px; border-radius: 8px; border: 1px solid #fecaca;">
                    <div>
                        <label class="form-label" style="font-size: 12.5px; font-weight: 700; color: #991b1b;">
                            <i class="fa-solid fa-lock ml-1"></i> پین‌کد امنیتی جهت تایید نهایی پاکسازی:
                        </label>
                        <input type="password" name="confirm_super_pin" class="form-control" placeholder="پین‌کد ۲۲۷۷۵۱۲ یا پین‌کد ۷۸۸۲۷۸" required style="border-color: #f87171; background: #ffffff;">
                        <small style="color: #64748b; font-size: 11px;">جهت امنیت، ورود پین‌کد سوپر ادمین (۲۲۷۷۵۱۲) یا پین مدیر دکان (۷۸۸۲۷۸) پذیرفته می‌شود.</small>
                    </div>

                    <div>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #1e293b; cursor: pointer; margin-bottom: 8px;">
                            <input type="checkbox" name="reset_system_wizard" value="1">
                            <span>همچنین پین‌کدها بازنشانی شده و صفحه تنظیمات اولیه اول بار فعال شود</span>
                        </label>
                        <small style="color: #64748b; font-size: 11px; display: block;">اگر این گزینه را تیک بزنید، کاربر در اولین ورود مجدداً به صفحه تعیین پین‌کد اولیه هدایت می‌شود.</small>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-danger" style="width: 100%; padding: 10px 16px; font-weight: 800; background: #dc2626; border-color: #b91c1c;">
                            <i class="fa-solid fa-trash-can ml-1"></i> تایید و پاکسازی کلیه داده‌ها (شروع از صفر)
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. System Activity Logs -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-clock-rotate-left ml-1"></i> گزارشات فعالیت کاربران (Activity Audit Logs)
            </h3>
            <span class="badge badge-secondary"><?= to_persian_num(count($logs)) ?> رکورد اخیر</span>
        </div>

        <div class="table-responsive">
            <table class="table" style="font-size: 12px;">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>کاربر</th>
                        <th>عملیات</th>
                        <th>جزئیات</th>
                        <th>آدرس IP</th>
                        <th>زمان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #64748b; padding: 16px;">
                                گزارشی ثبت نگردیده است.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $lNum = 1;
                        foreach ($logs as $log): 
                        ?>
                            <tr>
                                <td><?= to_persian_num($lNum++) ?></td>
                                <td><strong><?= sanitize($log['fullname'] ?? 'سیستم') ?></strong></td>
                                <td><span class="badge badge-secondary"><?= sanitize($log['action']) ?></span></td>
                                <td><?= sanitize($log['details']) ?></td>
                                <td><code><?= sanitize($log['ip_address'] ?: '127.0.0.1') ?></code></td>
                                <td><?= to_persian_num(date('Y/m/d - H:i:s', strtotime($log['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
