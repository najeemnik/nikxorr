<?php
/**
 * NiKXor - Mobile Shop Management System
 * Shop Capital Management (سرمایه عمومی دکان)
 * URL: http://localhost/nikxor/modules/capital.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();

$error = '';
$success = '';

// Handle Capital Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_capital') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $amount = (float)normalize_digits($_POST['amount'] ?? '0');
        $transType = trim($_POST['transaction_type'] ?? 'سرمایه');
        $sourceDesc = trim($_POST['source_description'] ?? '');
        $transDate = !empty($_POST['transaction_date']) ? $_POST['transaction_date'] : date('Y-m-d');

        if ($amount <= 0) {
            $error = 'مبلغ وارد شده معتبر نیست.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO capital (amount, transaction_type, source_description, transaction_date) 
                                   VALUES (:amt, :ttype, :sdesc, :tdate)");
            $stmt->execute([
                ':amt'   => $amount,
                ':ttype' => $transType,
                ':sdesc' => $sourceDesc,
                ':tdate' => $transDate
            ]);

            log_activity($currentUser['id'], 'تغییر سرمایه', "ثبت {$transType} به مبلغ " . format_money($amount));
            set_flash("عملیات حساب سرمایه با موفقیت ثبت گردید.", 'success');
            redirect(BASE_URL . 'modules/capital.php');
        }
    }
}

// Fetch all capital transactions
$transactions = $pdo->query("SELECT * FROM capital ORDER BY id DESC")->fetchAll();

// Total Capital Calculations
$totalInjected = 0;
$totalWithdrawn = 0;

foreach ($transactions as $tx) {
    if ($tx['transaction_type'] === 'برداشت') {
        $totalWithdrawn += (float)$tx['amount'];
    } else {
        $totalInjected += (float)$tx['amount'];
    }
}
$currentNetCapital = $totalInjected - $totalWithdrawn;

$pageTitle = 'گردش و حساب سرمایه دکان';
require_once __DIR__ . '/../includes/header.php';

$isLocked = is_feature_locked('capital_turnover');
?>

<?php if ($isLocked): ?>
<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title" style="display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-vault ml-1"></i> مدیریت گردش سرمایه دکان
            <span class="badge-lock-tag"><i class="fa-solid fa-lock text-amber-500"></i> قفل می‌باشد</span>
        </h3>
        <a href="<?= BASE_URL ?>modules/partners.php" class="btn btn-secondary btn-sm">بازگشت به شرکا</a>
    </div>
</div>

<div class="locked-blur-wrapper">
    <div class="locked-blur-content" style="padding: 20px;">
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
            <div class="card" style="padding: 20px; border: 1px solid #cbd5e1;">
                <h4>گردش حساب سرمایه</h4>
                <p>مجموع سرمایه اولیه: ۵۰۰,۰۰۰ افغانی</p>
                <p>تزریق نقدینگی اخیر: ۱۰۰,۰۰۰ افغانی</p>
            </div>
            <div class="card" style="padding: 20px; border: 1px solid #cbd5e1;">
                <h4>تراکنش‌های بانکی و صندوق</h4>
                <p>تراکنش شماره ۱: افزایش سرمایه</p>
                <p>تراکنش شماره ۲: برداشت اضطراری</p>
            </div>
        </div>
    </div>
    <div class="locked-overlay-card">
        <h3><i class="fa-solid fa-lock mr-2"></i> این گزینه قفل می‌باشد</h3>
        <p style="font-size: 14px; margin-bottom: 8px;">جهت درخواست و فعال‌سازی این گزینه با احمد نجیم نیک تماس بگیرید</p>
        <p class="en-notice">This feature is currently locked. To request access and unlock this feature, please contact <strong>Ahmad Najeem Nik</strong>.</p>
        <a href="https://wa.me/93783788278?text=Hello%20Ahmad%20Najeem%20Nik,%20I%20would%20like%20to%20unlock%20Capital%20Turnover%20Management" target="_blank" class="btn-whatsapp-direct">
            <i class="fa-brands fa-whatsapp"></i> واتس‌اپ احمد نجیم نیک (+93783788278)
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; exit; endif; ?>


<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <div>
            <h3 class="card-title" style="margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-vault ml-1 text-primary"></i> سرمایه در گردش و صندوق عمومی دکان
            </h3>
            <p style="font-size: 13px; color: #64748b; margin-top: 4px;">
                موجودی خالص سرمایه: <strong style="color: #0f172a; font-size: 15px;"><?= format_money($currentNetCapital) ?></strong> 
                <span style="color: #cbd5e1; margin: 0 6px;">|</span>
                مجموع تزریق‌شده: <span style="color: #166534; font-weight: 700;"><?= format_money($totalInjected) ?></span> 
                <span style="color: #cbd5e1; margin: 0 6px;">|</span>
                مجموع برداشت‌ها: <span style="color: #991b1b; font-weight: 700;"><?= format_money($totalWithdrawn) ?></span>
            </p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="<?= BASE_URL ?>modules/partners.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                <i class="fa-solid fa-users"></i>
                <span>شرکاء عمومی دکان</span>
            </a>
            <a href="<?= BASE_URL ?>modules/mudaraba.php" class="btn btn-sm" style="background: #ecfdf5; color: #047857; border: 1.5px solid #a7f3d0; display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                <i class="fa-solid fa-handshake-angle"></i>
                <span>حساب اختصاصی مضاربت</span>
            </a>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; align-items: start;">
    <!-- Add Capital Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title" style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-plus-circle text-primary"></i> ثبت گردش سرمایه جدید
            </h3>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= sanitize($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_capital">

            <div class="form-group">
                <label class="form-label" for="transaction_type" style="font-weight: 700;">نوع عملیات:</label>
                <select id="transaction_type" name="transaction_type" class="form-control" style="font-weight: 600;">
                    <option value="سرمایه">تزریق سرمایه جدید / افزایش سرمایه دکان</option>
                    <option value="برداشت">برداشت از سرمایه</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="amount" style="font-weight: 700;">
                    مبلغ (افغانی): <span style="color: #ef4444;">*</span>
                </label>
                <input type="number" step="any" id="amount" name="amount" class="form-control numeric-input" required placeholder="مبلغ به افغانی" style="font-weight: 700; font-size: 15px;">
            </div>

            <div class="form-group">
                <label class="form-label" for="source_description" style="font-weight: 700;">توضیحات و منبع سرمایه:</label>
                <input type="text" id="source_description" name="source_description" class="form-control" placeholder="مثلاً: آورده نقدی احمد ضیا یا تسویه نقدی">
            </div>

            <div class="form-group">
                <label class="form-label" for="transaction_date" style="font-weight: 700;">تاریخ تراکنش:</label>
                <input type="date" id="transaction_date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px; padding: 10px; font-weight: 800; font-size: 14px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                <i class="fa-solid fa-check"></i>
                <span>ثبت در گردش حساب سرمایه</span>
            </button>
        </form>
    </div>

    <!-- Capital History Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title" style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-clock-rotate-left text-primary"></i> تاریخچه تغییرات سرمایه دکان
            </h3>
        </div>

        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                <thead>
                    <tr>
                        <th style="width: 45px; text-align: center; white-space: nowrap;">شماره</th>
                        <th style="width: 120px; text-align: center; white-space: nowrap;">نوع عملیات</th>
                        <th style="min-width: 130px; text-align: left; white-space: nowrap;">مبلغ (افغانی)</th>
                        <th style="min-width: 180px;">شرح و منبع تراکنش</th>
                        <th style="width: 125px; text-align: center; white-space: nowrap;">تاریخ تراکنش</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #64748b; padding: 28px;">
                                <i class="fa-solid fa-receipt" style="font-size: 28px; display: block; margin-bottom: 8px; color: #cbd5e1;"></i>
                                تاکنون هیچ تراکنشی در حساب سرمایه ثبت نشده است.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $tNum = 1;
                        foreach ($transactions as $tx): 
                        ?>
                            <tr>
                                <td style="text-align: center; font-weight: 700; color: #64748b; white-space: nowrap;"><?= to_persian_num($tNum++) ?></td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <?php if ($tx['transaction_type'] === 'برداشت'): ?>
                                        <span class="badge badge-danger" style="font-weight: 700; padding: 3px 8px;">برداشت</span>
                                    <?php else: ?>
                                        <span class="badge badge-success" style="font-weight: 700; padding: 3px 8px;">افزایش سرمایه</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: left; white-space: nowrap;">
                                    <strong style="font-size: 13.5px; <?= $tx['transaction_type'] === 'برداشت' ? 'color: #dc2626;' : 'color: #166534;' ?>">
                                        <?= $tx['transaction_type'] === 'برداشت' ? '-' : '+' ?><?= format_money($tx['amount']) ?>
                                    </strong>
                                </td>
                                <td style="font-size: 13px; line-height: 1.6; color: #334155;"><?= sanitize($tx['source_description'] ?: '-') ?></td>
                                <td style="text-align: center; white-space: nowrap; direction: ltr; font-family: inherit; font-size: 12.5px; font-weight: 700; color: #475569;">
                                    <?= to_persian_num(date('Y/m/d', strtotime($tx['transaction_date']))) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
