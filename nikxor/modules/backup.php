<?php
/**
 * NiKXor - Database Backup & Recovery Module (پشتیبان‌گیری و نگهداری اطلاعات)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();

$pageTitle = 'پشتیبان‌گیری دیتابیس و امنیت اطلاعات';
$isLocked = is_feature_locked('backup_module');

// Directory for local backups
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0777, true);
}

// Handle Direct Download of SQLite file (Show warning if locked)
if (isset($_GET['download']) && $_GET['download'] === 'sqlite') {
    if ($isLocked) {
        set_flash('امکان دانلود دیتابیس SQLite در نسخه آزمایشی قفل است. جهت تهیه لایسنس با احمد نجیم نیک (+93783788278) تماس بگیرید.', 'warning');
        redirect(BASE_URL . 'modules/backup.php');
    }
    $sqlitePath = __DIR__ . '/../nikxor_db.sqlite';
    if (file_exists($sqlitePath)) {
        header('Content-Type: application/x-sqlite3');
        header('Content-Disposition: attachment; filename="nikxor_db_' . date('Y-m-d_H-i') . '.sqlite"');
        header('Content-Length: ' . filesize($sqlitePath));
        readfile($sqlitePath);
        exit;
    }
}

// Handle Export Full SQL Dump (Show warning if locked)
if (isset($_GET['download']) && $_GET['download'] === 'sql') {
    if ($isLocked) {
        set_flash('امکان استخراج اسکریپت SQL در نسخه آزمایشی قفل است. جهت تهیه لایسنس با احمد نجیم نیک (+93783788278) تماس بگیرید.', 'warning');
        redirect(BASE_URL . 'modules/backup.php');
    }
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="nikxor_backup_' . date('Y-m-d_H-i') . '.sql"');

    echo "-- NiKXor Shop Management System - Full SQL Dump\n";
    echo "-- Generated at: " . date('Y-m-d H:i:s') . "\n\n";

    $tables = ['users', 'categories', 'partners', 'stock', 'customers', 'sales', 'sale_items', 'debts', 'installments', 'partnership_transactions', 'capital', 'expenses', 'settings', 'custom_features'];
    foreach ($tables as $tbl) {
        try {
            $rows = $pdo->query("SELECT * FROM {$tbl}")->fetchAll(PDO::FETCH_ASSOC);
            echo "-- Table: {$tbl} (" . count($rows) . " rows)\n";
            foreach ($rows as $r) {
                $cols = array_keys($r);
                $escapedVals = array_map(function($v) use ($pdo) {
                    return $v === null ? 'NULL' : $pdo->quote($v);
                }, array_values($r));
                echo "INSERT INTO {$tbl} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $escapedVals) . ");\n";
            }
            echo "\n";
        } catch (Exception $e) {}
    }
    exit;
}

// Handle Create Snapshot (Show warning if locked)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_snapshot') {
    if ($isLocked) {
        set_flash('امکان ایجاد نسخه پشتیبان در نسخه پایه قفل می‌باشد. جهت خرید لایسنس بک‌آپ، با احمد نجیم نیک (+93783788278) تماس بگیرید.', 'warning');
        redirect(BASE_URL . 'modules/backup.php');
    }
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'خطای امنیتی نشست!');
    } else {
        $sqlitePath = __DIR__ . '/../nikxor_db.sqlite';
        if (file_exists($sqlitePath)) {
            $dest = $backupDir . '/backup_' . date('Ymd_His') . '.sqlite';
            if (@copy($sqlitePath, $dest)) {
                log_activity($currentUser['id'], 'ایجاد بک‌آپ', 'ایجاد نسخه پشتیبان محلی دیتابیس');
                set_flash('success', 'نسخه پشتیبان با موفقیت تهیه و ذخیره گردید.');
            } else {
                set_flash('danger', 'خطا در کپی فایل پشتیبان!');
            }
        }
    }
    redirect(BASE_URL . 'modules/backup.php');
}

// Fetch existing backup files
$existingBackups = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..' && substr($f, -7) === '.sqlite') {
            $filePath = $backupDir . '/' . $f;
            $existingBackups[] = [
                'name' => $f,
                'size' => round(filesize($filePath) / 1024, 2) . ' KB',
                'time' => date('Y/m/d H:i:s', filemtime($filePath))
            ];
        }
    }
}

// Database stats
$dbSize = '0 KB';
$sqliteMain = __DIR__ . '/../nikxor_db.sqlite';
if (file_exists($sqliteMain)) {
    $dbSize = round(filesize($sqliteMain) / 1024, 2) . ' KB';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="backup-module-page" style="position: relative;">

    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-cloud-arrow-down" style="color: #0284c7;"></i>
                پشتیبان‌گیری دیتابیس و امنیت اطلاعات (Database Backup)
                <?php if ($isLocked): ?>
                    <?= render_lock_badge('backup_module', 'این گزینه قفل است') ?>
                <?php endif; ?>
            </h3>
            <span style="font-size: 12px; color: #64748b;">تهیه نسخه پشتیبان خودکار، دانلود فایل پایگاه داده و محافظت در برابر حذف ناخواسته</span>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if ($isLocked): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="showLockModal('backup_module', 'Cloud & Local Database Backup Module', 'پشتیبان‌گیری ابری و محلی (بک‌آپ)');">
                    <i class="fa-solid fa-lock ml-1 text-amber-500"></i> فعال‌سازی بخش بک‌آپ
                </button>
            <?php else: ?>
                <form method="POST" action="" style="display: inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_snapshot">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-camera ml-1"></i> ایجاد اسنپ‌شات جدید
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Overview Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px;">
        <div class="card" style="padding: 16px; border-left: 4px solid #0284c7;">
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">حجم فعلی دیتابیس فروشگاه</div>
            <div style="font-size: 20px; font-weight: 800; color: #0f172a;"><?= $dbSize ?></div>
            <div style="font-size: 11px; color: #64748b; margin-top: 4px;">فرمت: SQLite 3 / MySQL Ready</div>
        </div>

        <div class="card" style="padding: 16px; border-left: 4px solid #10b981;">
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">تعداد نسخه‌های پشتیبان موجود</div>
            <div style="font-size: 20px; font-weight: 800; color: #10b981;"><?= to_persian_num(count($existingBackups)) ?> <span style="font-size: 12px; font-weight: normal; color: #94a3b8;">نسخه</span></div>
            <div style="font-size: 11px; color: #64748b; margin-top: 4px;">ذخیره شده در پوشه backups</div>
        </div>

        <div class="card" style="padding: 16px; border-left: 4px solid #f59e0b;">
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">وضعیت همگام‌سازی و امنیت</div>
            <div style="font-size: 16px; font-weight: 800; color: #d97706;">آماده دانلود و بایگانی</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 4px;">قابل انتقال به فلش یا کامپیوتر دیگر</div>
        </div>
    </div>

    <?php if ($isLocked): ?>
        <div style="background: #fffbeb; border: 1px solid #fef3c7; border-right: 4px solid #f59e0b; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-lock" style="color: #d97706; font-size: 16px;"></i>
                <span style="font-size: 12.5px; font-weight: 700; color: #92400e;">
                    سیستم بک‌آپ در حالت پیش‌نمایش قرار دارد. جهت ایجاد اسنپ‌شات یا دانلود فایل‌های پشتیبان، نیاز به خرید لایسنس دارید.
                </span>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="showLockModal('backup_restore', 'Advanced Backup & Restore', 'پشتیبان‌گیری و حفاظت اطلاعات');" style="font-size: 11.5px; color: #b45309; border-color: #fcd34d;">
                <i class="fa-solid fa-key ml-1"></i> فعال‌سازی لایسنس
            </button>
        </div>
    <?php endif; ?>

    <!-- Container -->
    <div style="position: relative;">
        <!-- Action Buttons -->
        <div class="card" style="margin-bottom: 20px; padding: 20px;">
            <h4 style="font-size: 14.5px; font-weight: 800; color: #0f172a; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-download" style="color: #0284c7;"></i>
                خروجی گرفتن و دانلود مستقیم فایل‌ها
            </h4>

            <div style="display: flex; gap: 14px; flex-wrap: wrap;">
                <a href="<?= BASE_URL ?>modules/backup.php?download=sqlite" class="btn btn-primary" style="padding: 10px 18px; font-size: 13px;">
                    <i class="fa-solid fa-database ml-1"></i> دانلود فایل دیتابیس SQLite (.sqlite)
                </a>
                <a href="<?= BASE_URL ?>modules/backup.php?download=sql" class="btn btn-secondary" style="padding: 10px 18px; font-size: 13px; background: #0f172a; color: #ffffff; border-color: #334155;">
                    <i class="fa-solid fa-file-code ml-1 text-amber-400"></i> دانلود اسکریپت کامل SQL (.sql)
                </a>
            </div>
            <p style="font-size: 11.5px; color: #64748b; margin-top: 10px; margin-bottom: 0;">
                توصیه می‌شود حداقل هفته‌ای یک بار فایل پشتیبان را دانلود نموده و در فلش دیسک یا فضای ابری نگهداری فرمایید.
            </p>
        </div>

        <!-- Previous Backups Table -->
        <div class="card">
            <div class="card-header" style="padding: 14px 18px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h4 style="font-size: 14px; font-weight: 700; margin: 0; color: #0f172a;">
                    <i class="fa-solid fa-clock-rotate-left ml-1" style="color: #64748b;"></i>
                    آرشیو نسخه‌های پشتیبان محلی
                </h4>
                <span style="font-size: 12px; color: #94a3b8;">پوشه سرور: nikxor/backups/</span>
            </div>
            <div class="table-responsive">
                <table class="table" style="font-size: 13px;">
                    <thead>
                        <tr style="background: #f8fafc;">
                            <th style="width: 50px; text-align: center;">#</th>
                            <th>نام فایل پشتیبان</th>
                            <th>حجم فایل</th>
                            <th>تاریخ و ساعت ایجاد</th>
                            <th style="text-align: center; width: 120px;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($existingBackups)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 25px; color: #94a3b8;">
                                    هنوز نسخه پشتیبان اسنپ‌شات در سرور ثبت نشده است. با زدن دکمه ایجاد اسنپ‌شات، اولین بک‌آپ را بسازید.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $bIdx = 1; foreach ($existingBackups as $bk): ?>
                                <tr>
                                    <td style="text-align: center; color: #64748b;"><?= to_persian_num($bIdx++) ?></td>
                                    <td>
                                        <code style="font-size: 12px; background: #f1f5f9; padding: 2px 8px; border-radius: 4px;"><?= sanitize($bk['name']) ?></code>
                                    </td>
                                    <td><?= sanitize($bk['size']) ?></td>
                                    <td><?= sanitize($bk['time']) ?></td>
                                    <td style="text-align: center;">
                                        <a href="<?= BASE_URL ?>modules/backup.php?download=sqlite" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 3px 8px;">
                                            <i class="fa-solid fa-download ml-1"></i> دریافت
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
