<?php
/**
 * NiKXor - Mobile Shop Management System
 * Shop Expenses Module (مدیریت مصارف دکان - تفکیک شده از ضرر)
 * URL: http://localhost/nikxor/modules/expenses.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();

$pageTitle = 'مدیریت مصارف دکان';
$error = '';
$success = '';

// Self-healing: ensure expenses table has required columns
try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $cols = $pdo->query("PRAGMA table_info(expenses)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $cols = array_map('strtolower', $cols ?: []);
        if (!in_array('expense_type', $cols)) $pdo->exec("ALTER TABLE expenses ADD COLUMN expense_type VARCHAR(20) DEFAULT 'مصارف'");
    } else {
        $cols = $pdo->query("SHOW COLUMNS FROM `expenses`")->fetchAll(PDO::FETCH_COLUMN, 0);
        $cols = array_map('strtolower', $cols ?: []);
        if (!in_array('expense_type', $cols)) {
            $pdo->exec("ALTER TABLE `expenses` ADD COLUMN `expense_type` VARCHAR(20) NOT NULL DEFAULT 'مصارف'");
        }
    }
} catch (Exception $e) {}

// Handle Add Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $category    = trim($_POST['category'] ?? 'متفرقه');
        $amount      = (float)clean_number($_POST['amount'] ?? '0', true);
        $expenseDate = !empty($_POST['expense_date']) ? $_POST['expense_date'] : date('Y-m-d');
        $description = trim($_POST['description'] ?? '');
        $exactNow    = date('Y-m-d H:i:s');

        if (is_feature_locked('expenses_module') || is_feature_locked('shop_expenses_entry')) {
            $error = 'بخش مصارف دکان در حال حاضر قفل می‌باشد! جهت فعال‌سازی می‌توانید با پین‌کد ۲۲۷۷۵۱۲ وارد پنل سوپر ادمین شده و قفل آن را باز فرمایید.';
        } elseif ($amount <= 0) {
            $error = 'لطفاً مبلغ مصرف را به درستی وارد نمایید.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO expenses (expense_type, category, amount, expense_date, description, created_by, created_at) 
                                       VALUES ('مصارف', :cat, :amt, :edate, :desc, :uid, :cdate)");
                $stmt->execute([
                    ':cat'   => $category,
                    ':amt'   => $amount,
                    ':edate' => $expenseDate,
                    ':desc'  => $description,
                    ':uid'   => $currentUser['id'],
                    ':cdate' => $exactNow
                ]);
            } catch (Exception $insEx) {
                $stmt = $pdo->prepare("INSERT INTO expenses (category, amount, expense_date, description, created_by, created_at) 
                                       VALUES (:cat, :amt, :edate, :desc, :uid, :cdate)");
                $stmt->execute([
                    ':cat'   => $category,
                    ':amt'   => $amount,
                    ':edate' => $expenseDate,
                    ':desc'  => $description,
                    ':uid'   => $currentUser['id'],
                    ':cdate' => $exactNow
                ]);
            }

            log_activity($currentUser['id'], 'ثبت مصرف دکان', "ثبت مصرف در بخش {$category} به مبلغ " . format_money($amount));
            set_flash("مصرف دکان با موفقیت ثبت گردید.", 'success');
            redirect(BASE_URL . 'modules/expenses.php');
        }
    }
}

// Handle Delete Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_expense') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('خطای امنیتی نشست!', 'danger');
    } else {
        $delId = (int)($_POST['expense_id'] ?? 0);
        if ($delId > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = :id");
                $stmt->execute([':id' => $delId]);
                log_activity($currentUser['id'], 'حذف مصرف دکان', "حذف رکورد مصرف شناسه {$delId}");
                set_flash('مورد با موفقیت حذف گردید.', 'success');
            } catch (Exception $e) {
                set_flash('خطا در حذف رکورد: ' . $e->getMessage(), 'danger');
            }
        }
    }
    redirect(BASE_URL . 'modules/expenses.php');
}

// Filter parameters
$catFilter  = trim($_GET['category'] ?? '');
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');

$where = [];
$params = [];

$hasExpenseType = true;
try {
    $pdo->query("SELECT expense_type FROM expenses LIMIT 1");
    $where[] = "e.expense_type = 'مصارف'";
} catch (Exception $e) {
    $hasExpenseType = false;
    $where[] = "1=1";
}

if ($catFilter !== '') {
    $where[] = "e.category = :cat";
    $params[':cat'] = $catFilter;
}
if ($dateFrom !== '') {
    $where[] = "e.expense_date >= :dfrom";
    $params[':dfrom'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = "e.expense_date <= :dto";
    $params[':dto'] = $dateTo;
}

$whereSql = "WHERE " . implode(" AND ", $where);

// Fetch list of expenses (fail-safe)
$expenses = [];
try {
    $sql = "SELECT e.*, u.fullname as user_name FROM expenses e LEFT JOIN users u ON e.created_by = u.id {$whereSql} ORDER BY e.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll();
} catch (Exception $ex) {
    $expenses = [];
}

// Totals for Expenses (fail-safe)
$totExpAll   = 0.0;
$totExpMonth = 0.0;
$totExpToday = 0.0;

try {
    if ($hasExpenseType) {
        $totExpAll = (float)($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_type = 'مصارف'")->fetchColumn());
        
        $curMonth = date('Y-m');
        $stmtM = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_type = 'مصارف' AND expense_date LIKE ?");
        $stmtM->execute([$curMonth . '%']);
        $totExpMonth = (float)$stmtM->fetchColumn();

        $curToday = date('Y-m-d');
        $stmtT = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_type = 'مصارف' AND expense_date = ?");
        $stmtT->execute([$curToday]);
        $totExpToday = (float)$stmtT->fetchColumn();
    } else {
        $totExpAll = (float)($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses")->fetchColumn());
    }
} catch (Exception $e) {}

$isExpensesLocked = is_feature_locked('expenses_module') || is_feature_locked('shop_expenses_entry');

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($isExpensesLocked): ?>
<div class="card" style="border: 2px dashed #f59e0b; background: #fffbeb; padding: 18px 24px; margin-bottom: 20px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 44px; height: 44px; background: #fef3c7; color: #b45309; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                <i class="fa-solid fa-lock"></i>
            </div>
            <div>
                <div style="font-size: 15px; font-weight: 800; color: #92400e;">گزینه مصارف دکان در حال حاضر قفل می‌باشد</div>
                <div style="font-size: 12.5px; color: #b45309; margin-top: 2px;">
                    طبق تنظیمات امنیتی، ثبت و ویرایش مصارف قفل است. کنترل و بازگشایی آن در پنل سوپر ادمین (پین ۲۲۷۷۵۱۲) انجام می‌شود.
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 8px;">
            <?php if (!empty($_SESSION['super_admin_verified']) || ($currentUser['username'] ?? '') === 'nikadmin'): ?>
                <a href="<?= BASE_URL ?>admin/secret_panel_786.php" class="btn btn-warning btn-sm" style="font-weight: 700; background: #f59e0b; color: #0f172a; border: none;">
                    <i class="fa-solid fa-unlock-keyhole ml-1"></i> بازگشایی در پنل سوپر ادمین
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-warning btn-sm" onclick="showLockModal('expenses_module', 'Shop Expenses Module', 'مدیریت مصارف دکان')" style="font-weight: 700;">
                    <i class="fa-solid fa-shield-halved ml-1"></i> مشاهده جزئیات لایسنس
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <h1 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-receipt text-primary"></i>
                مدیریت مصارف جاری دکان (تفکیک شده از ضرر)
            </h1>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">
                ثبت و محاسبه مصارف عملیاتی، کرایه، بل برق، نان، معاشات و ترانسپورت دکان
            </p>
        </div>

        <div style="display: flex; gap: 8px;">
            <a href="<?= BASE_URL ?>modules/losses.php" class="btn btn-secondary" style="color: #b91c1c; border-color: #fca5a5; background: #fff5f5;">
                <i class="fa-solid fa-triangle-exclamation ml-1"></i> رفتن به ضرر و خسارات اجناس
            </a>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fa-solid fa-print ml-1"></i> چاپ جدول
            </button>
        </div>
    </div>
</div>

<!-- METRIC STATS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; text-align: center;">
        <div style="font-size: 12px; color: #64748b; font-weight: 700; margin-bottom: 4px;">
            <i class="fa-solid fa-calendar-day ml-1"></i> مصارف امروز دکان
        </div>
        <div style="font-size: 22px; font-weight: 800; color: #0284c7;"><?= format_money($totExpToday) ?></div>
        <div style="font-size: 11px; color: #64748b; margin-top: 4px;">تاریخ: <?= date('Y/m/d') ?></div>
    </div>

    <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 16px; text-align: center;">
        <div style="font-size: 12px; color: #1e40af; font-weight: 700; margin-bottom: 4px;">
            <i class="fa-solid fa-calendar-days ml-1"></i> مجموع مصارف ماه جاری
        </div>
        <div style="font-size: 22px; font-weight: 800; color: #1d4ed8;"><?= format_money($totExpMonth) ?></div>
        <div style="font-size: 11px; color: #1e40af; margin-top: 4px;">مصارف این ماه</div>
    </div>

    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 16px; text-align: center;">
        <div style="font-size: 12px; color: #92400e; font-weight: 700; margin-bottom: 4px;">
            <i class="fa-solid fa-vault ml-1"></i> مجموع کل مصارف عمومی
        </div>
        <div style="font-size: 22px; font-weight: 800; color: #b45309;"><?= format_money($totExpAll) ?></div>
        <div style="font-size: 11px; color: #92400e; margin-top: 4px;">از ابتدای ثبت سیستم</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    <!-- Add Expense Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-plus-circle ml-1"></i> ثبت مصرف جدید
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
            <input type="hidden" name="action" value="add_expense">

            <div class="form-group">
                <label class="form-label" for="category">بخش / کتگوری مصرف: <span style="color: #ef4444;">*</span></label>
                <select name="category" id="category" class="form-control" required>
                    <option value="کرایه دکان">کرایه دکان</option>
                    <option value="بل برق">بل برق</option>
                    <option value="نان و چای و غذا">نان و چای و غذا</option>
                    <option value="اینترنت و مخابرات">اینترنت و مخابرات</option>
                    <option value="معاش شاگرد و پرسنل">معاش شاگرد و پرسنل</option>
                    <option value="مالیه و شاروالی">مالیه و شاروالی</option>
                    <option value="ترانسپورت و کرایه موتر">ترانسپورت و کرایه موتر</option>
                    <option value="وسایل و لوازم مصرفی دکان">وسایل و لوازم مصرفی دکان</option>
                    <option value="متفرقه">متفرقه</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="amount">مبلغ مصرف (افغانی): <span style="color: #ef4444;">*</span></label>
                <input type="number" step="any" min="1" id="amount" name="amount" class="form-control" required placeholder="مثلاً: ۵۰۰" style="font-weight: bold; color: #0284c7;">
            </div>

            <div class="form-group">
                <label class="form-label" for="expense_date">تاریخ مصرف:</label>
                <input type="date" id="expense_date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="description">شرح و جزئیات مصرف:</label>
                <textarea id="description" name="description" class="form-control" rows="2" placeholder="توضیحات اختیاری در مورد این هزینه..."></textarea>
            </div>

            <?php if ($isExpensesLocked): ?>
                <button type="button" class="btn btn-warning" onclick="showLockModal('expenses_module', 'Shop Expenses Module', 'مدیریت مصارف دکان')" style="width: 100%; padding: 11px; font-weight: 700; background: #fef3c7; color: #92400e; border: 1px solid #fde68a;">
                    <i class="fa-solid fa-lock ml-1 text-amber-600"></i> این بخش قفل است (مشاهده لایسنس)
                </button>
            <?php else: ?>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px; font-weight: 700;">
                    <i class="fa-solid fa-check ml-1"></i> ثبت نهایی مصرف
                </button>
            <?php endif; ?>
        </form>
    </div>

    <!-- Expenses List Table -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <h3 class="card-title">
                <i class="fa-solid fa-list ml-1"></i> تاریخچه مصارف ثبت شده دکان
            </h3>
            <span class="badge badge-secondary"><?= count($expenses) ?> رکورد مصرف</span>
        </div>

        <!-- FILTER -->
        <form method="GET" action="" style="background: #f8fafc; padding: 10px; border-radius: 8px; margin-bottom: 14px; display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 1; min-width: 130px;">
                <label style="font-size: 11px; font-weight: 700; color: #64748b;">کتگوری مصرف:</label>
                <select name="category" class="form-control" style="font-size: 12px; padding: 6px;">
                    <option value="">همه موارد</option>
                    <option value="کرایه دکان" <?= $catFilter === 'کرایه دکان' ? 'selected' : '' ?>>کرایه دکان</option>
                    <option value="بل برق" <?= $catFilter === 'بل برق' ? 'selected' : '' ?>>بل برق</option>
                    <option value="نان و چای و غذا" <?= $catFilter === 'نان و چای و غذا' ? 'selected' : '' ?>>نان و چای</option>
                    <option value="اینترنت و مخابرات" <?= $catFilter === 'اینترنت و مخابرات' ? 'selected' : '' ?>>اینترنت</option>
                    <option value="معاش شاگرد و پرسنل" <?= $catFilter === 'معاش شاگرد و پرسنل' ? 'selected' : '' ?>>معاش شاگرد</option>
                    <option value="مالیه و شاروالی" <?= $catFilter === 'مالیه و شاروالی' ? 'selected' : '' ?>>مالیه</option>
                    <option value="ترانسپورت و کرایه موتر" <?= $catFilter === 'ترانسپورت و کرایه موتر' ? 'selected' : '' ?>>ترانسپورت</option>
                </select>
            </div>
            <div style="flex: 1; min-width: 120px;">
                <label style="font-size: 11px; font-weight: 700; color: #64748b;">از تاریخ:</label>
                <input type="date" name="date_from" class="form-control" style="font-size: 12px; padding: 6px;" value="<?= sanitize($dateFrom) ?>">
            </div>
            <div style="flex: 1; min-width: 120px;">
                <label style="font-size: 11px; font-weight: 700; color: #64748b;">تا تاریخ:</label>
                <input type="date" name="date_to" class="form-control" style="font-size: 12px; padding: 6px;" value="<?= sanitize($dateTo) ?>">
            </div>
            <button type="submit" class="btn btn-sm btn-primary" style="height: 35px; padding: 0 12px;">فیلتر</button>
            <a href="<?= BASE_URL ?>modules/expenses.php" class="btn btn-sm btn-secondary" style="height: 35px; padding: 0 10px;" title="حذف فیلتر"><i class="fa-solid fa-rotate-left"></i></a>
        </form>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">#</th>
                        <th>بخش مصرف</th>
                        <th style="text-align: center;">مبلغ</th>
                        <th>تاریخ</th>
                        <th>توضیحات</th>
                        <th>ثبت‌کننده</th>
                        <th style="width: 60px; text-align: center;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #94a3b8;">
                                <i class="fa-solid fa-receipt" style="font-size: 28px; display: block; margin-bottom: 6px; color: #cbd5e1;"></i>
                                هیچ مصرفی مطابق جستجو یافت نشد.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $idx => $ex): ?>
                            <tr>
                                <td style="text-align: center; font-weight: 700; color: #64748b;"><?= $idx + 1 ?></td>
                                <td>
                                    <span class="badge" style="background: #f1f5f9; color: #334155; font-weight: 700;">
                                        <?= sanitize($ex['category']) ?>
                                    </span>
                                </td>
                                <td style="text-align: center; font-weight: 800; color: #0f172a;">
                                    <?= format_money($ex['amount'], false) ?>
                                </td>
                                <td style="font-size: 12px; color: #64748b;">
                                    <?= sanitize($ex['expense_date']) ?>
                                </td>
                                <td style="font-size: 12px; color: #475569; max-width: 200px;">
                                    <?= sanitize($ex['description'] ?: '-') ?>
                                </td>
                                <td style="font-size: 12px; color: #64748b;">
                                    <?= sanitize($ex['user_name'] ?: 'سیستم') ?>
                                </td>
                                <td style="text-align: center;">
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('آیا از حذف این مصرف مطمئن هستید؟');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_expense">
                                        <input type="hidden" name="expense_id" value="<?= $ex['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" style="padding: 3px 8px;" title="حذف">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
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
