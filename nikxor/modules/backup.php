<?php
/**
 * NiKXor - Database Backup & Recovery Module
 * 100% Multilingual Isolated: English, Dari, Pashto
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();
$currentLang = get_current_lang();
$currentDir  = get_current_dir();

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
        set_flash(__t('SQLite download is locked in trial mode. Contact Ahmad Najeem Nik (+93783788278) to unlock full access.', 'امکان دانلود دیتابیس SQLite در نسخه آزمایشی قفل است. جهت تهیه لایسنس با احمد نجیم نیک (+93783788278) تماس بگیرید.', 'د SQLite ډاونلوډ قفل دی. له احمد نجیم نیک سره اړیکه ونیسئ.'), 'warning');
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
        set_flash(__t('SQL dump export is locked in trial mode. Contact Ahmad Najeem Nik (+93783788278) to unlock full access.', 'امکان استخراج اسکریپت SQL در نسخه آزمایشی قفل است. جهت تهیه لایسنس با احمد نجیم نیک (+93783788278) تماس بگیرید.', 'د SQL سکریپټ راایستل قفل دي.'), 'warning');
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
        set_flash(__t('Backup creation is locked in preview mode. Contact Ahmad Najeem Nik (+93783788278) to activate license.', 'امکان ایجاد نسخه پشتیبان در نسخه پایه قفل می‌باشد. جهت خرید لایسنس بک‌آپ، با احمد نجیم نیک (+93783788278) تماس بگیرید.', 'د شاتړ جوړول قفل دي.'), 'warning');
        redirect(BASE_URL . 'modules/backup.php');
    }
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash(__t('Security session error!', 'خطای امنیتی نشست!', 'د ناستې امنیتي تېروتنه!'), 'danger');
    } else {
        $sqlitePath = __DIR__ . '/../nikxor_db.sqlite';
        if (file_exists($sqlitePath)) {
            $dest = $backupDir . '/backup_' . date('Ymd_His') . '.sqlite';
            if (@copy($sqlitePath, $dest)) {
                log_activity($currentUser['id'], 'ایجاد بک‌آپ', 'ایجاد نسخه پشتیبان محلی دیتابیس');
                set_flash(__t('Database backup snapshot created and saved successfully.', 'نسخه پشتیبان با موفقیت تهیه و ذخیره گردید.', 'د ډیټابیس شاتړ په بریالیتوب جوړ شو.'), 'success');
            } else {
                set_flash(__t('Error copying database backup file!', 'خطا در کپی فایل پشتیبان!', 'د شاتړ د فایل په کاپي کولو کې تېروتنه!'), 'danger');
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
                <?= __t('Database Backup & Data Security (Database Backup)', 'پشتیبان‌گیری دیتابیس و امنیت اطلاعات', 'د ډیټابیس شاتړ او د معلوماتو خوندیتوب') ?>
                <?php if ($isLocked): ?>
                    <?= render_lock_badge('backup_module', __t('Locked', 'این گزینه قفل است', 'دا برخه قفل ده')) ?>
                <?php endif; ?>
            </h3>
            <span style="font-size: 12px; color: #64748b;"><?= __t('Automatic snapshot creation, local database downloads, and disaster recovery protection', 'تهیه نسخه پشتیبان خودکار، دانلود فایل پایگاه داده و محافظت در برابر حذف ناخواسته', 'اتومات شاتړ، د ډیټابیس ډاونلوډ او د معلوماتو ساتنه') ?></span>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if ($isLocked): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="showLockModal('backup_module', 'Cloud & Local Database Backup Module', '<?= __t('Cloud & Local Database Backup Module', 'پشتیبان‌گیری ابری و محلی (بک‌آپ)', 'د ډیټابیس شاتړ اخیستل') ?>');">
                    <i class="fa-solid fa-lock ml-1 text-amber-500"></i> <?= __t('Activate Backup License', 'فعال‌سازی بخش بک‌آپ', 'د شاتړ لایسنس فعالول') ?>
                </button>
            <?php else: ?>
                <form method="POST" action="" style="display: inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_snapshot">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-camera ml-1"></i> <?= __t('Create New Snapshot', 'ایجاد اسنپ‌شات جدید', 'نوی شاتړ جوړ کړئ') ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Overview Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px;">
        <div class="card" style="padding: 16px; border-left: 4px solid #0284c7;">
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;"><?= __t('Current Store Database Size', 'حجم فعلی دیتابیس فروشگاه', 'د هټۍ د ډیټابیس اندازه') ?></div>
            <div style="font-size: 20px; font-weight: 800; color: #0f172a;"><?= $dbSize ?></div>
            <div style="font-size: 11px; color: #64748b; margin-top: 4px;"><?= __t('Engine: SQLite 3 / MySQL Compatible', 'فرمت: SQLite 3 / MySQL Ready', 'بڼه: SQLite 3') ?></div>
        </div>

        <div class="card" style="padding: 16px; border-left: 4px solid #10b981;">
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;"><?= __t('Available Local Backups Count', 'تعداد نسخه‌های پشتیبان موجود', 'د شته شاتړونو شمېر') ?></div>
            <div style="font-size: 20px; font-weight: 800; color: #10b981;"><?= $currentLang === 'en' ? count($existingBackups) : to_persian_num(count($existingBackups)) ?> <span style="font-size: 12px; font-weight: normal; color: #94a3b8;"><?= __t('snapshots', 'نسخه', 'نسخې') ?></span></div>
            <div style="font-size: 11px; color: #64748b; margin-top: 4px;"><?= __t('Stored securely in server storage', 'ذخیره شده در پوشه backups', 'په سرور کې خوندي شوي') ?></div>
        </div>

        <div class="card" style="padding: 16px; border-left: 4px solid #f59e0b;">
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;"><?= __t('Synchronization & Safety Status', 'وضعیت همگام‌سازی و امنیت', 'امنیتي او د همغږۍ حالت') ?></div>
            <div style="font-size: 16px; font-weight: 800; color: #d97706;"><?= __t('Ready for Download & Archive', 'آماده دانلود و بایگانی', 'ډاونلوډ او ارشیف ته چمتو') ?></div>
            <div style="font-size: 11px; color: #64748b; margin-top: 4px;"><?= __t('Portable to USB drive or offsite PC', 'قابل انتقال به فلش یا کامپیوتر دیگر', 'فلش ډیسک ته د لېږد وړ') ?></div>
        </div>
    </div>

    <?php if ($isLocked): ?>
        <div style="background: #fffbeb; border: 1px solid #fef3c7; border-right: 4px solid #f59e0b; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-lock" style="color: #d97706; font-size: 16px;"></i>
                <span style="font-size: 12.5px; font-weight: 700; color: #92400e;">
                    <?= __t('Backup system is in preview mode. To generate snapshots or export database files, please activate your license.', 'سیستم بک‌آپ در حالت پیش‌نمایش قرار دارد. جهت ایجاد اسنپ‌شات یا دانلود فایل‌های پشتیبان، نیاز به خرید لایسنس دارید.', 'د شاتړ سیسټم په ازمایښتي حالت کې دی.') ?>
                </span>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="showLockModal('backup_restore', 'Advanced Backup & Restore', '<?= __t('Advanced Backup & Restore', 'پشتیبان‌گیری و حفاظت اطلاعات', 'د شاتړ او معلوماتو خوندیتوب') ?>');" style="font-size: 11.5px; color: #b45309; border-color: #fcd34d;">
                <i class="fa-solid fa-key ml-1"></i> <?= __t('Activate License', 'فعال‌سازی لایسنس', 'لایسنس فعال کړئ') ?>
            </button>
        </div>
    <?php endif; ?>

    <!-- Container -->
    <div style="position: relative;">
        <!-- Action Buttons -->
        <div class="card" style="margin-bottom: 20px; padding: 20px;">
            <h4 style="font-size: 14.5px; font-weight: 800; color: #0f172a; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-download" style="color: #0284c7;"></i>
                <?= __t('Export & Direct File Download', 'خروجی گرفتن و دانلود مستقیم فایل‌ها', 'مستقیم ډیټابیس او فایلونه ډاونلوډ کړئ') ?>
            </h4>

            <div style="display: flex; gap: 14px; flex-wrap: wrap;">
                <a href="<?= BASE_URL ?>modules/backup.php?download=sqlite" class="btn btn-primary" style="padding: 10px 18px; font-size: 13px;">
                    <i class="fa-solid fa-database ml-1"></i> <?= __t('Download SQLite Database File (.sqlite)', 'دانلود فایل دیتابیس SQLite (.sqlite)', 'د SQLite ډیټابیس فایل ډاونلوډ (.sqlite)') ?>
                </a>
                <a href="<?= BASE_URL ?>modules/backup.php?download=sql" class="btn btn-secondary" style="padding: 10px 18px; font-size: 13px; background: #0f172a; color: #ffffff; border-color: #334155;">
                    <i class="fa-solid fa-file-code ml-1 text-amber-400"></i> <?= __t('Download Complete SQL Script (.sql)', 'دانلود اسکریپت کامل SQL (.sql)', 'بشپړ SQL سکریپټ ډاونلوډ کړئ (.sql)') ?>
                </a>
            </div>
            <p style="font-size: 11.5px; color: #64748b; margin-top: 10px; margin-bottom: 0;">
                <?= __t('It is recommended to download a backup copy at least once a week and store it on an external flash drive or secure cloud repository.', 'توصیه می‌شود حداقل هفته‌ای یک بار فایل پشتیبان را دانلود نموده و در فلش دیسک یا فضای ابری نگهداری فرمایید.', 'سپارښتنه کیږي چې لږترلږه په اونۍ کې یو ځل شاتړ ډاونلوډ کړئ او په بهرني فلش کې یې وساتئ.') ?>
            </p>
        </div>

        <!-- Previous Backups Table -->
        <div class="card">
            <div class="card-header" style="padding: 14px 18px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h4 style="font-size: 14px; font-weight: 700; margin: 0; color: #0f172a;">
                    <i class="fa-solid fa-clock-rotate-left ml-1" style="color: #64748b;"></i>
                    <?= __t('Local Backup Snapshots Archive', 'آرشیو نسخه‌های پشتیبان محلی', 'د ځایي شاتړونو ارشیف') ?>
                </h4>
                <span style="font-size: 12px; color: #94a3b8;"><?= __t('Server Storage: nikxor/backups/', 'پوشه سرور: nikxor/backups/', 'سرور پوښۍ: nikxor/backups/') ?></span>
            </div>
            <div class="table-responsive">
                <table class="table" style="font-size: 13px;">
                    <thead>
                        <tr style="background: #f8fafc;">
                            <th style="width: 50px; text-align: center;">#</th>
                            <th><?= __t('Backup File Name', 'نام فایل پشتیبان', 'د شاتړ د فایل نوم') ?></th>
                            <th><?= __t('File Size', 'حجم فایل', 'د فایل اندازه') ?></th>
                            <th><?= __t('Created Date & Time', 'تاریخ و ساعت ایجاد', 'د جوړېدو وخت او نېټه') ?></th>
                            <th style="text-align: center; width: 120px;"><?= __t('Actions', 'عملیات', 'کړنې') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($existingBackups)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 25px; color: #94a3b8;">
                                    <?= __t('No snapshot backups recorded on the server yet. Click "Create New Snapshot" above to generate your first backup.', 'هنوز نسخه پشتیبان اسنپ‌شات در سرور ثبت نشده است. با زدن دکمه ایجاد اسنپ‌شات، اولین بک‌آپ را بسازید.', 'تر اوسه هېڅ شاتړ نه دی ثبت شوی. پورته بټن ووهئ تر څو لومړی شاتړ جوړ کړئ.') ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $bIdx = 1; foreach ($existingBackups as $bk): ?>
                                <tr>
                                    <td style="text-align: center; color: #64748b;"><?= $currentLang === 'en' ? ($bIdx++) : to_persian_num($bIdx++) ?></td>
                                    <td>
                                        <code style="font-size: 12px; background: #f1f5f9; padding: 2px 8px; border-radius: 4px;"><?= sanitize($bk['name']) ?></code>
                                    </td>
                                    <td><?= sanitize($bk['size']) ?></td>
                                    <td><?= sanitize($bk['time']) ?></td>
                                    <td style="text-align: center;">
                                        <a href="<?= BASE_URL ?>modules/backup.php?download=sqlite" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 3px 8px;">
                                            <i class="fa-solid fa-download ml-1"></i> <?= __t('Download', 'دریافت', 'ډاونلوډ') ?>
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
