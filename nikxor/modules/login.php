<?php
/**
 * NiKXor - Mobile Shop Management System
 * Login is removed. Direct redirect to shop dashboard.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Direct access to dashboard without login
redirect(BASE_URL . 'index.php');
exit;

$error = '';
$pinValue = '';

// Check if system has completed initial setup
$isSystemInitialized = (get_setting('system_initialized', '0') === '1');

// If user explicitly asks to run setup or if not initialized yet
$showFirstRun = !$isSystemInitialized;

if (isset($_GET['setup']) && $isSystemInitialized) {
    if (!empty($_SESSION['super_admin_verified'])) {
        $showFirstRun = true;
    } else {
        set_flash('جهت تنظیم مجدد پین‌کد سیستم، لطفاً ابتدا با پین‌کد سوپر ادمین (۲۲۷۷۵۱۲) وارد شوید.', 'warning');
        $showFirstRun = false;
    }
}

// 1. Handle First-Time Setup Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'first_time_setup') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست! لطفاً مجدداً امتحان کنید.';
    } else {
        $shopName   = trim($_POST['shop_name'] ?? DEFAULT_SHOP_NAME);
        $shopPhone  = trim($_POST['shop_phone'] ?? '0791510046');
        $newPin     = trim($_POST['new_pin'] ?? '');
        $confirmPin = trim($_POST['confirm_pin'] ?? '');

        // Normalize Persian/Arabic digits to English digits
        $newPinClean     = function_exists('normalize_numbers') ? normalize_numbers($newPin) : $newPin;
        $confirmPinClean = function_exists('normalize_numbers') ? normalize_numbers($confirmPin) : $confirmPin;

        // If user typed 2277512 in setup, immediately log into Super Admin panel!
        if ($newPinClean === '2277512') {
            update_setting('super_admin_pin', '2277512');
            update_setting('system_initialized', '1');
            $res = auth_login_pin('2277512');
            set_flash('ورود مستقیم به پنل محرمانه سوپر ادمین با موفقیت انجام شد.', 'success');
            redirect(BASE_URL . 'admin/secret_panel_786.php');
        }

        if (empty($newPinClean)) {
            $error = 'لطفاً پین‌کد دلخواه خود را برای ورود مدیر دکان وارد نمایید.';
        } elseif (strlen($newPinClean) < 4) {
            $error = 'پین‌کد ورود باید حداقل ۴ رقم باشد.';
        } elseif ($newPinClean !== $confirmPinClean) {
            $error = 'تکرار پین‌کد با پین‌کد وارد شده همخوانی ندارد!';
        } else {
            // Save Shop and PIN settings
            update_setting('shop_name', !empty($shopName) ? $shopName : DEFAULT_SHOP_NAME);
            update_setting('shop_phone_central', $shopPhone);
            update_setting('admin_pin', $newPinClean);
            update_setting('super_admin_pin', '2277512');
            update_setting('system_initialized', '1');

            // Lock shop expenses by default as required
            try {
                $pdo->exec("UPDATE custom_features SET is_locked = 1 WHERE feature_key = 'expenses_module'");
                update_setting('feat_lock_expenses_module', '1');
            } catch (Exception $e) {}

            // Update admin user password in users table
            try {
                $stmt = $pdo->prepare("UPDATE users SET password = :p WHERE username = 'admin' OR id = 2");
                $stmt->execute([':p' => password_hash($newPinClean, PASSWORD_DEFAULT)]);
            } catch (Exception $e) {}

            // Ensure nikadmin (super admin) exists with master credentials
            try {
                $stmt = $pdo->prepare("UPDATE users SET password = :p WHERE username = 'nikadmin' OR id = 1");
                $stmt->execute([':p' => password_hash('Nik@786#Master', PASSWORD_DEFAULT)]);
            } catch (Exception $e) {}

            // Auto-login as Shop Admin
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            if (!headers_sent()) {
                @session_regenerate_id(true);
            }

            $_SESSION['user_id']       = 2;
            $_SESSION['user_username'] = 'admin';
            $_SESSION['user_fullname'] = 'مدیر دکان';
            $_SESSION['user_role']     = 'admin';

            log_activity(2, 'راه‌اندازی سیستم', "تنظیم اولیه سیستم و تعیین پین‌کد مدیریت دکان انجام شد.");
            set_flash('سیستم فروشگاه با موفقیت راه‌اندازی گردید و پین‌کد شما با موفقیت ذخیره شد. خوش آمدید!', 'success');
            redirect(BASE_URL . 'index.php');
        }
    }
}

// 2. Handle Regular PIN Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'login')) {
    $pin = trim($_POST['pin'] ?? $_POST['password'] ?? '');

    if (empty($pin)) {
        $error = 'لطفاً پین‌کد ورود را وارد نمایید.';
    } else {
        $result = auth_login_pin($pin);
        if ($result['success']) {
            set_flash($result['message'], 'success');

            $redirectTarget = $result['redirect'];
            $currentSid = session_id();
            if (!empty($currentSid)) {
                $sep = (strpos($redirectTarget, '?') !== false) ? '&' : '?';
                $redirectTarget .= $sep . 'sid=' . urlencode($currentSid);
            }
            redirect($redirectTarget);
        } else {
            $error = $result['message'];
        }
    }
}

$shopName = get_setting('shop_name', DEFAULT_SHOP_NAME);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $showFirstRun ? 'راه‌اندازی اولیه و تعیین پین‌کد دکان' : 'ورود با پین‌کد امنیتی' ?> - <?= sanitize($shopName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
    <style>
        .pin-box-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .pin-input-field {
            font-size: 22px !important;
            letter-spacing: 6px;
            text-align: center;
            font-weight: 800;
            padding: 14px 44px 14px 14px !important;
            border: 2px solid #cbd5e1 !important;
            border-radius: 10px !important;
            transition: all 0.2s ease-in-out;
            background-color: #f8fafc;
        }
        .pin-input-field:focus {
            border-color: #0284c7 !important;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.15) !important;
        }
        .pin-toggle-btn {
            position: absolute;
            left: 12px;
            background: none;
            border: none;
            color: #64748b;
            font-size: 18px;
            cursor: pointer;
            padding: 4px 8px;
        }
        .pin-toggle-btn:hover {
            color: #0f172a;
        }
    </style>
</head>
<body class="login-body">

<div class="login-card" style="max-width: <?= $showFirstRun ? '520px' : '420px' ?>;">

    <?php if ($showFirstRun): ?>
        <!-- FIRST-TIME SETUP INTERFACE -->
        <div class="login-header">
            <div class="login-logo-box" style="background: #0284c7; color: #ffffff;">
                <i class="fa-solid fa-screwdriver-wrench"></i>
            </div>
            <h2 style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 10px;">
                راه‌اندازی اولیه سیستم دکان
            </h2>
            <p style="font-size: 13px; color: #64748b; margin-top: 4px;">
                سیستم برای اولین بار بر روی زمپ/هاست بارگذاری شده است. لطفاً پین‌کد اختصاصی مدیر دکان را تعیین نمایید:
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= sanitize($error) ?></span>
            </div>
        <?php endif; ?>

        <?php 
        $flash = get_flash();
        if ($flash): 
        ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?>" style="margin-bottom: 20px;">
                <i class="fa-solid fa-circle-info"></i>
                <span><?= sanitize($flash['text']) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="first_time_setup">

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-weight: 700; font-size: 13px;">
                    <i class="fa-solid fa-store ml-1 text-primary"></i> نام فروشگاه / دکان موبایل:
                </label>
                <input type="text" name="shop_name" class="form-control" value="<?= sanitize($shopName) ?>" required placeholder="مثلاً: فروشگاه موبایل ضیا">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-weight: 700; font-size: 13px;">
                    <i class="fa-solid fa-phone ml-1 text-primary"></i> شماره تماس اصلی دکان:
                </label>
                <input type="text" name="shop_phone" class="form-control" value="0791510046" placeholder="شماره تماس جهت درج در فاکتورها">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-weight: 700; font-size: 13px;">
                    <i class="fa-solid fa-key ml-1 text-primary"></i> تعیین پین‌کد دلخواه ورود مدیر دکان (۴ تا ۸ رقم):
                </label>
                <div class="pin-box-wrapper">
                    <input type="password" id="newPin" name="new_pin" class="form-control pin-input-field" 
                           required autofocus inputmode="numeric" placeholder="مثلاً: 788278" autocomplete="new-password">
                    <button type="button" class="pin-toggle-btn" onclick="togglePinId('newPin', 'eye1')">
                        <i id="eye1" class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" style="font-weight: 700; font-size: 13px;">
                    <i class="fa-solid fa-check-double ml-1 text-primary"></i> تکرار پین‌کد مدیر دکان:
                </label>
                <div class="pin-box-wrapper">
                    <input type="password" id="confirmPin" name="confirm_pin" class="form-control pin-input-field" 
                           required inputmode="numeric" placeholder="تکرار پین‌کد" autocomplete="new-password">
                    <button type="button" class="pin-toggle-btn" onclick="togglePinId('confirmPin', 'eye2')">
                        <i id="eye2" class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Super Admin PIN Notice -->
            <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px; margin-bottom: 18px; font-size: 12.5px; color: #92400e; line-height: 1.6;">
                <div style="font-weight: 800; color: #78350f; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-shield-halved text-amber-600"></i> رمز ورود اختصاصی سوپر ادمین:
                </div>
                پین‌کد ورود به کنترل پنل ارشد (سوپر ادمین) به صورت ثابت و محفوظ <strong>۲۲۷۷۵۱۲</strong> می‌باشد. در هر زمان و در هنگام لاگین، با وارد نمودن پین‌کد <strong>۲۲۷۷۵۱۲</strong> مستقیماً به پنل سوپر ادمین منتقل خواهید شد.
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 13px; font-size: 15px; font-weight: 700; border-radius: 10px; box-shadow: 0 4px 10px rgba(2, 132, 199, 0.3);">
                <i class="fa-solid fa-check-circle ml-1"></i> ثبت پین‌کد و شروع به کار با سیستم
            </button>
        </form>

    <?php else: ?>
        <!-- REGULAR PIN-ONLY LOGIN INTERFACE -->
        <div class="login-header">
            <div class="login-logo-box">
                <i class="fa-solid fa-lock-open"></i>
            </div>
            <h2><?= sanitize($shopName) ?></h2>
            <p>ورود اختصاصی با پین‌کد امنیتی (PIN Access)</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= sanitize($error) ?></span>
            </div>
        <?php endif; ?>

        <?php 
        $flash = get_flash();
        if ($flash): 
        ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?>" style="margin-bottom: 20px;">
                <i class="fa-solid fa-circle-info"></i>
                <span><?= sanitize($flash['text']) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="login">

            <div class="form-group" style="margin-bottom: 18px;">
                <label class="form-label" for="pinInput" style="font-weight: 700; font-size: 13.5px; margin-bottom: 8px; display: block; text-align: right;">
                    <i class="fa-solid fa-key ml-1 text-primary"></i> لطفاً پین‌کد را وارد کنید:
                </label>
                <div class="pin-box-wrapper">
                    <input type="password" id="pinInput" name="pin" class="form-control pin-input-field" 
                           required autofocus inputmode="numeric" placeholder="••••••" autocomplete="off"
                           maxlength="10">
                    <button type="button" class="pin-toggle-btn" onclick="togglePinId('pinInput', 'eyeIcon')" title="نمایش/پنهان پین">
                        <i id="eyeIcon" class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 13px; font-size: 15px; font-weight: 700; border-radius: 10px; box-shadow: 0 4px 10px rgba(2, 132, 199, 0.3);">
                <i class="fa-solid fa-arrow-right-to-bracket ml-1"></i> ورود به سیستم
            </button>
        </form>

        <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 11.5px; color: #64748b;">
                پین‌کد سوپر ادمین: ۲۲۷۷۵۱۲
            </span>
            <a href="?setup=1" style="font-size: 11.5px; color: #0284c7; text-decoration: none; font-weight: 700;">
                <i class="fa-solid fa-gear ml-1"></i> تنظیم مجدد پین
            </a>
        </div>
    <?php endif; ?>

    <div style="margin-top: 18px; text-align: center; border-top: 1px solid #f1f5f9; padding-top: 12px;">
        <span style="font-size: 11px; color: #94a3b8;">
            سیستم امنیتی <?= sanitize(APP_NAME) ?> v<?= APP_VERSION ?> | احمد نجیم نیک (+93783788278)
        </span>
    </div>
</div>

<script>
function togglePinId(inputId, iconId) {
    var inp = document.getElementById(inputId);
    var icon = document.getElementById(iconId);
    if (!inp || !icon) return;
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

<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
