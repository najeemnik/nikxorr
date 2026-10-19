<?php
/**
 * NiKXor - Mobile Shop Management System
 * Screen Lock and Custom PIN Handler
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 1. AJAX: Lock Screen
if ($action === 'lock_screen') {
    $_SESSION['screen_locked'] = true;
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'locked' => true]);
    exit;
}

// 2. AJAX: Verify & Unlock Screen
if ($action === 'verify_unlock') {
    $pin = trim($_POST['pin'] ?? '');
    if (function_exists('normalize_numbers')) {
        $pin = normalize_numbers($pin);
    }
    $cleanPin = str_replace([' ', '-', '_'], '', $pin);

    $lockEnabled = get_setting('screen_lock_enabled', '1');
    $currentLockPin = get_setting('screen_lock_pin', '1234');
    $masterPin = get_setting('super_admin_pin', '2277512');

    $isMatch = false;

    // If screen lock PIN requirement is turned off by shopkeeper, any attempt unlocks
    if ($lockEnabled === '0' || empty($currentLockPin)) {
        $isMatch = true;
    } elseif ($cleanPin === $currentLockPin) {
        $isMatch = true;
    } elseif ($cleanPin === $masterPin || ($masterPin === '2277512' && ($cleanPin === '2277512' || $cleanPin === 'Nik@786#Master'))) {
        // Universal emergency master key
        $isMatch = true;
    }

    header('Content-Type: application/json; charset=utf-8');
    if ($isMatch) {
        $_SESSION['screen_locked'] = false;
        echo json_encode(['success' => true, 'message' => 'صفحه با موفقیت بازگشایی شد.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'پین‌کد وارد شده نادرست است!']);
    }
    exit;
}

// 3. POST Form: Save Custom Lock PIN Settings (Shopkeeper's Choice)
if ($action === 'save_screen_lock_settings') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('خطای امنیتی نشست!', 'danger');
        redirect($_SERVER['HTTP_REFERER'] ?? (BASE_URL . 'index.php'));
    }

    $lockEnabled = isset($_POST['screen_lock_enabled']) ? '1' : '0';
    $newPin = trim($_POST['new_pin'] ?? '');
    $confirmPin = trim($_POST['confirm_pin'] ?? '');

    if ($lockEnabled === '1') {
        if (function_exists('normalize_numbers')) {
            $newPin = normalize_numbers($newPin);
            $confirmPin = normalize_numbers($confirmPin);
        }
        $cleanNewPin = str_replace([' ', '-', '_'], '', $newPin);
        $cleanConfirmPin = str_replace([' ', '-', '_'], '', $confirmPin);

        if (!empty($cleanNewPin)) {
            if (strlen($cleanNewPin) < 3) {
                set_flash('پین‌کد قفل صفحه باید حداقل ۳ رقم باشد.', 'danger');
                redirect($_SERVER['HTTP_REFERER'] ?? (BASE_URL . 'index.php'));
            }
            if ($cleanNewPin !== $cleanConfirmPin) {
                set_flash('تکرار پین‌کد با پین‌کد جدید همخوانی ندارد!', 'danger');
                redirect($_SERVER['HTTP_REFERER'] ?? (BASE_URL . 'index.php'));
            }
            update_setting('screen_lock_pin', $cleanNewPin);
        }
    }

    update_setting('screen_lock_enabled', $lockEnabled);
    log_activity($_SESSION['user_id'] ?? 1, 'تنظیم پین‌کد قفل صفحه', 'تغییر تنظیمات پین‌کد دلخواه قفل صفحه توسط دکاندار');
    set_flash('تنظیمات قفل صفحه و پین‌کد دلخواه با موفقیت ذخیره گردید.', 'success');
    redirect($_SERVER['HTTP_REFERER'] ?? (BASE_URL . 'index.php'));
}

redirect(BASE_URL . 'index.php');
exit;
