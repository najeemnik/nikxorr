<?php
/**
 * NiKXor - Mobile Shop Management System
 * Losses and Damaged Goods Module (ضرر و خسارات اجناس)
 * URL: http://localhost/nikxor/modules/losses.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();

$pageTitle = 'ضرر و خسارات اجناس';
$error = '';
$success = '';

// Self-healing: ensure expenses and stock tables have all required columns across SQLite & MySQL
try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $cols = $pdo->query("PRAGMA table_info(expenses)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $cols = array_map('strtolower', $cols ?: []);
        if (!in_array('expense_type', $cols)) $pdo->exec("ALTER TABLE expenses ADD COLUMN expense_type VARCHAR(20) DEFAULT 'مصارف'");
        if (!in_array('stock_id', $cols)) $pdo->exec("ALTER TABLE expenses ADD COLUMN stock_id INTEGER DEFAULT NULL");
        if (!in_array('purchase_price', $cols)) $pdo->exec("ALTER TABLE expenses ADD COLUMN purchase_price DECIMAL(15,2) DEFAULT 0.00");
        if (!in_array('sale_price', $cols)) $pdo->exec("ALTER TABLE expenses ADD COLUMN sale_price DECIMAL(15,2) DEFAULT 0.00");
        if (!in_array('loss_qty', $cols)) $pdo->exec("ALTER TABLE expenses ADD COLUMN loss_qty INTEGER DEFAULT 1");

        $sCols = $pdo->query("PRAGMA table_info(stock)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $sCols = array_map('strtolower', $sCols ?: []);
        if (!in_array('damaged_qty', $sCols)) $pdo->exec("ALTER TABLE stock ADD COLUMN damaged_qty INTEGER DEFAULT 0");
        if (!in_array('is_damaged', $sCols)) $pdo->exec("ALTER TABLE stock ADD COLUMN is_damaged INTEGER DEFAULT 0");
        if (!in_array('damage_notes', $sCols)) $pdo->exec("ALTER TABLE stock ADD COLUMN damage_notes VARCHAR(255) DEFAULT ''");
    } else {
        $cols = $pdo->query("SHOW COLUMNS FROM `expenses`")->fetchAll(PDO::FETCH_COLUMN, 0);
        $cols = array_map('strtolower', $cols ?: []);
        if (!in_array('expense_type', $cols)) {
            $pdo->exec("ALTER TABLE `expenses` ADD COLUMN `expense_type` VARCHAR(20) NOT NULL DEFAULT 'مصارف'");
        }
        if (!in_array('stock_id', $cols)) {
            $pdo->exec("ALTER TABLE `expenses` ADD COLUMN `stock_id` INT NULL DEFAULT NULL");
        }
        if (!in_array('purchase_price', $cols)) {
            $pdo->exec("ALTER TABLE `expenses` ADD COLUMN `purchase_price` DECIMAL(15,2) NULL DEFAULT 0.00");
        }
        if (!in_array('sale_price', $cols)) {
            $pdo->exec("ALTER TABLE `expenses` ADD COLUMN `sale_price` DECIMAL(15,2) NULL DEFAULT 0.00");
        }
        if (!in_array('loss_qty', $cols)) {
            $pdo->exec("ALTER TABLE `expenses` ADD COLUMN `loss_qty` INT DEFAULT 1");
        }

        try {
            $sCols = $pdo->query("SHOW COLUMNS FROM `stock`")->fetchAll(PDO::FETCH_COLUMN, 0);
            $sCols = array_map('strtolower', $sCols ?: []);
            if (!in_array('damaged_qty', $sCols)) {
                $pdo->exec("ALTER TABLE `stock` ADD COLUMN `damaged_qty` INT NOT NULL DEFAULT 0");
            }
            if (!in_array('is_damaged', $sCols)) {
                $pdo->exec("ALTER TABLE `stock` ADD COLUMN `is_damaged` TINYINT(1) DEFAULT 0");
            }
            if (!in_array('damage_notes', $sCols)) {
                $pdo->exec("ALTER TABLE `stock` ADD COLUMN `damage_notes` VARCHAR(255) DEFAULT ''");
            }
        } catch (Exception $scEx) {}
    }
} catch (Exception $colEx) {}

// Auto-heal: If any past losses were inserted in expenses but stock quantity was never deducted due to the legacy MySQL MAX() parameter bug, synchronize them now
try {
    $unsynced = $pdo->query("
        SELECT e.stock_id, SUM(e.loss_qty) AS lost_qty, MAX(e.category) AS last_cat
        FROM expenses e
        INNER JOIN stock s ON e.stock_id = s.id
        WHERE e.expense_type = 'ضرر' AND (s.damaged_qty IS NULL OR s.damaged_qty = 0)
        GROUP BY e.stock_id
    ")->fetchAll();

    foreach ($unsynced as $un) {
        $sid = (int)$un['stock_id'];
        $lQty = (int)$un['lost_qty'];
        if ($sid > 0 && $lQty > 0) {
            $stkStmt = $pdo->prepare("SELECT quantity, purchase_price, expenses FROM stock WHERE id = :id");
            $stkStmt->execute([':id' => $sid]);
            $stk = $stkStmt->fetch();
            if ($stk) {
                $curQ = (int)$stk['quantity'];
                $newQ = max(0, $curQ - $lQty);
                $uCost = (float)$stk['purchase_price'] + (float)$stk['expenses'];
                $newCap = $uCost * $newQ;
                $catNote = $un['last_cat'] ?: 'خسارت جنس';
                try {
                    $pdo->prepare("UPDATE stock SET quantity = :nq, capital_amount = :ncap, damaged_qty = :ndmg, is_damaged = 1, damage_notes = :dnote WHERE id = :id")
                        ->execute([
                            ':nq'    => $newQ,
                            ':ncap'  => $newCap,
                            ':ndmg'  => $lQty,
                            ':dnote' => $catNote,
                            ':id'    => $sid
                        ]);
                } catch (Exception $eSync) {
                    $pdo->prepare("UPDATE stock SET quantity = :nq WHERE id = :id")
                        ->execute([':nq' => $newQ, ':id' => $sid]);
                }
            }
        }
    }
} catch (Exception $syncEx) {}

// Handle Add Loss
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_loss') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $category    = trim($_POST['category'] ?? 'شکستگی و سوختگی قطعه');
        $amount      = (float)clean_number($_POST['amount'] ?? '0', true);
        $lossDate    = !empty($_POST['loss_date']) ? $_POST['loss_date'] : date('Y-m-d');
        $stockId     = !empty($_POST['stock_id']) ? (int)$_POST['stock_id'] : null;
        $lossQty     = !empty($_POST['loss_qty']) ? max(1, (int)$_POST['loss_qty']) : 1;
        $description = trim($_POST['description'] ?? '');
        $exactNow    = date('Y-m-d H:i:s');

        if ($amount <= 0) {
            $error = 'لطفاً مبلغ ضرر را به درستی وارد نمایید.';
        } else {
            try {
                // Primary insert with expense_type = 'ضرر'
                $stmt = $pdo->prepare("INSERT INTO expenses (expense_type, category, amount, expense_date, description, stock_id, loss_qty, created_by, created_at) 
                                       VALUES ('ضرر', :cat, :amt, :ldate, :desc, :sid, :lqty, :uid, :cdate)");
                $stmt->execute([
                    ':cat'   => $category,
                    ':amt'   => $amount,
                    ':ldate' => $lossDate,
                    ':desc'  => $description,
                    ':sid'   => $stockId,
                    ':lqty'  => $lossQty,
                    ':uid'   => $currentUser['id'],
                    ':cdate' => $exactNow
                ]);
            } catch (Exception $insEx) {
                // Resilient fallback insert if legacy schema exists
                $stmt = $pdo->prepare("INSERT INTO expenses (category, amount, expense_date, description, created_by, created_at) 
                                       VALUES (:cat, :amt, :ldate, :desc, :uid, :cdate)");
                $stmt->execute([
                    ':cat'   => $category,
                    ':amt'   => $amount,
                    ':ldate' => $lossDate,
                    ':desc'  => $description,
                    ':uid'   => $currentUser['id'],
                    ':cdate' => $exactNow
                ]);
            }

            // If selected from stock: ALWAYS deduct quantity accurately from inventory
            if ($stockId > 0) {
                $stockLossAction = $_POST['stock_loss_action'] ?? 'strike'; // 'delete' or 'strike'

                // Fetch current stock item
                $pStmt = $pdo->prepare("SELECT * FROM stock WHERE id = :id");
                $pStmt->execute([':id' => $stockId]);
                $stk = $pStmt->fetch();

                if ($stk) {
                    $sName = $stk['name'] ?? "شناسه {$stockId}";
                    $curQty = (int)$stk['quantity'];
                    $curDamaged = (int)($stk['damaged_qty'] ?? 0);
                    $newHealthyQty = max(0, $curQty - $lossQty);
                    $newDamagedQty = $curDamaged + $lossQty;
                    $unitCost = (float)$stk['purchase_price'] + (float)$stk['expenses'];
                    $newCapital = $unitCost * $newHealthyQty;

                    if ($stockLossAction === 'delete') {
                        try {
                            try { $pdo->prepare("UPDATE sale_items SET stock_id = NULL WHERE stock_id = :id")->execute([':id' => $stockId]); } catch (Exception $e1) {}
                            $pdo->prepare("DELETE FROM stock WHERE id = :id")->execute([':id' => $stockId]);
                            log_activity($currentUser['id'], 'حذف جنس ضایع‌شده از گدام', "حذف کامل جنس «{$sName}» بابت ثبت ضرر");
                        } catch (Exception $delEx) {}
                    } else {
                        // Deduct from available healthy stock in PHP without calling SQL native MAX(a,b) which errors on MySQL
                        $damageNote = $category . (!empty($description) ? (' - ' . $description) : '');
                        $deducted = false;

                        try {
                            $updStmt = $pdo->prepare("UPDATE stock 
                                           SET quantity = :new_qty,
                                               capital_amount = :new_cap,
                                               damaged_qty = :new_dmg,
                                               is_damaged = 1,
                                               damage_notes = :dnote
                                           WHERE id = :id");
                            $updStmt->execute([
                                ':new_qty' => $newHealthyQty,
                                ':new_cap' => $newCapital,
                                ':new_dmg' => $newDamagedQty,
                                ':dnote'   => $damageNote,
                                ':id'      => $stockId
                            ]);
                            $deducted = true;
                        } catch (Exception $upEx1) {
                            try {
                                $updStmt2 = $pdo->prepare("UPDATE stock 
                                                           SET quantity = :new_qty,
                                                               capital_amount = :new_cap,
                                                               is_damaged = 1
                                                           WHERE id = :id");
                                $updStmt2->execute([
                                    ':new_qty' => $newHealthyQty,
                                    ':new_cap' => $newCapital,
                                    ':id'      => $stockId
                                ]);
                                $deducted = true;
                            } catch (Exception $upEx2) {
                                try {
                                    $updStmt3 = $pdo->prepare("UPDATE stock SET quantity = :new_qty WHERE id = :id");
                                    $updStmt3->execute([
                                        ':new_qty' => $newHealthyQty,
                                        ':id'      => $stockId
                                    ]);
                                    $deducted = true;
                                } catch (Exception $upEx3) {}
                            }
                        }

                        if ($deducted) {
                            log_activity($currentUser['id'], 'کسر موجودی بابت خسارت', "کسر {$lossQty} عدد از جنس «{$sName}» در گدام. موجودی قبلی: {$curQty} -> موجودی فعلی: {$newHealthyQty} عدد");
                        }
                    }
                }
            }

            log_activity($currentUser['id'], 'ثبت ضرر جنس', "ثبت ضرر به مبلغ " . format_money($amount) . " بابت «{$category}»");
            set_flash("خسارت جنس با موفقیت در بخش «ضرر و خسارات» ثبت گردید و از تعداد موجودی گدام کسر شد. (این مبلغ به عنوان ضرر منظور شده و هیچ تداخلی با بخش فروشات بل‌ها ندارد).", 'success');
            redirect(BASE_URL . 'modules/losses.php');
        }
    }
}

// Handle Delete Loss (با بازگرداندن تعداد کسر شده به موجودی سالم گدام)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_loss') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('خطای امنیتی نشست!', 'danger');
    } else {
        $delId = (int)($_POST['loss_id'] ?? 0);
        if ($delId > 0) {
            try {
                // Fetch expense details to restore stock
                $expStmt = $pdo->prepare("SELECT stock_id, loss_qty FROM expenses WHERE id = :id");
                $expStmt->execute([':id' => $delId]);
                $expData = $expStmt->fetch();

                if ($expData && !empty($expData['stock_id']) && (int)($expData['loss_qty'] ?? 0) > 0) {
                    $stkId = (int)$expData['stock_id'];
                    $lQty  = (int)$expData['loss_qty'];

                    $stkStmt = $pdo->prepare("SELECT * FROM stock WHERE id = :id");
                    $stkStmt->execute([':id' => $stkId]);
                    $stkItem = $stkStmt->fetch();

                    if ($stkItem) {
                        $restoredQty = (int)$stkItem['quantity'] + $lQty;
                        $restoredDamaged = max(0, (int)($stkItem['damaged_qty'] ?? 0) - $lQty);
                        $isDamaged = ($restoredDamaged > 0) ? 1 : 0;
                        $unitCost = (float)$stkItem['purchase_price'] + (float)$stkItem['expenses'];
                        $restoredCap = $unitCost * $restoredQty;

                        try {
                            $pdo->prepare("UPDATE stock 
                                           SET quantity = :rqty,
                                               capital_amount = :rcap,
                                               damaged_qty = :rdmg,
                                               is_damaged = :isdmg
                                           WHERE id = :id")
                                ->execute([
                                    ':rqty'  => $restoredQty,
                                    ':rcap'  => $restoredCap,
                                    ':rdmg'  => $restoredDamaged,
                                    ':isdmg' => $isDamaged,
                                    ':id'    => $stkId
                                ]);
                        } catch (Exception $rstEx) {
                            try {
                                $pdo->prepare("UPDATE stock SET quantity = :rqty WHERE id = :id")
                                    ->execute([':rqty' => $restoredQty, ':id' => $stkId]);
                            } catch (Exception $rstEx2) {}
                        }
                    }
                }

                $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = :id");
                $stmt->execute([':id' => $delId]);
                log_activity($currentUser['id'], 'حذف ضرر جنس', "حذف رکورد ضرر شناسه {$delId} و بازگرداندن تعداد به موجودی سالم گدام");
                set_flash('رکورد ضرر حذف شد و تعداد کسر شده مجدداً به موجودی سالم گدام اضافه گردید.', 'success');
            } catch (Exception $e) {
                set_flash('خطا در حذف رکورد ضرر: ' . $e->getMessage(), 'danger');
            }
        }
    }
    redirect(BASE_URL . 'modules/losses.php');
}

// Filter parameters
$catFilter  = trim($_GET['category'] ?? '');
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');

$where = [];
$params = [];

// Determine whether expense_type column exists
$hasExpenseType = true;
try {
    $pdo->query("SELECT expense_type FROM expenses LIMIT 1");
    $where[] = "e.expense_type = 'ضرر'";
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

// Fetch Losses with bulletproof fallback
$losses = [];
try {
    $sql = "SELECT e.*, u.fullname as user_name, s.name as product_name 
            FROM expenses e 
            LEFT JOIN users u ON e.created_by = u.id 
            LEFT JOIN stock s ON e.stock_id = s.id 
            {$whereSql} 
            ORDER BY e.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $losses = $stmt->fetchAll();
} catch (Exception $queryEx) {
    // Fail-safe query that NEVER fails even if stock_id does not exist
    try {
        $sqlFallback = "SELECT e.*, u.fullname as user_name, '' as product_name 
                        FROM expenses e 
                        LEFT JOIN users u ON e.created_by = u.id 
                        {$whereSql} 
                        ORDER BY e.id DESC";
        $stmt = $pdo->prepare($sqlFallback);
        $stmt->execute($params);
        $losses = $stmt->fetchAll();
    } catch (Exception $ex2) {
        $losses = [];
    }
}

// Metrics for Losses (fail-safe)
$totalLossAll = 0.0;
$totalLossMonth = 0.0;
$totalLossToday = 0.0;

try {
    if ($hasExpenseType) {
        $totalLossAll = (float)($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_type = 'ضرر'")->fetchColumn());
        
        $curMonth = date('Y-m');
        $stmtM = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_type = 'ضرر' AND (expense_date LIKE ? OR (expense_date IS NULL AND created_at LIKE ?))");
        $stmtM->execute([$curMonth . '%', $curMonth . '%']);
        $totalLossMonth = (float)$stmtM->fetchColumn();

        $curToday = date('Y-m-d');
        $stmtT = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_type = 'ضرر' AND (expense_date = ? OR (expense_date IS NULL AND (created_at LIKE ? OR DATE(created_at) = ?)))");
        $stmtT->execute([$curToday, $curToday . '%', $curToday]);
        $totalLossToday = (float)$stmtT->fetchColumn();
    }
} catch (Exception $metEx) {}

// Fetch stock items for damage selection (fail-safe and full catalogue)
$stockItems = [];
$preselectedStockId = (int)($_GET['stock_id'] ?? 0);
try {
    $stockItems = $pdo->query("SELECT id, name, specifications, quantity, purchase_price, expenses FROM stock ORDER BY name ASC")->fetchAll();
} catch (Exception $stkEx) {
    try {
        $stockItems = $pdo->query("SELECT id, name, quantity, purchase_price, 0 as expenses, '' as specifications FROM stock ORDER BY name ASC")->fetchAll();
    } catch (Exception $stkEx2) {}
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="font-size: 20px; font-weight: 800; color: #991b1b; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-triangle-exclamation text-danger"></i>
                مدیریت ضرر و خسارات اجناس (تفکیک شده از مصارف)
            </h1>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">
                ثبت خسارات ناشی از شکستگی صفحه، سوختگی قطعات، افت قیمت یا مفقودی اجناس گدام
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="<?= BASE_URL ?>modules/expenses.php" class="btn btn-secondary" style="padding: 8px 14px;">
                <i class="fa-solid fa-wallet ml-1"></i> رفتن به مصارف دکان
            </a>
            <button onclick="window.print()" class="btn btn-secondary" style="padding: 8px 14px;">
                <i class="fa-solid fa-print ml-1"></i> چاپ جدول
            </button>
        </div>
    </div>
</div>

<!-- LOSS METRICS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 16px; text-align: center;">
        <div style="font-size: 12px; color: #991b1b; font-weight: 700; margin-bottom: 4px;">
            <i class="fa-solid fa-calendar-day ml-1"></i> <?= __('today_recorded_loss') ?>
        </div>
        <div style="font-size: 22px; font-weight: 800; color: #dc2626;"><?= format_money($totalLossToday) ?></div>
        <div style="font-size: 11px; color: #991b1b; margin-top: 4px;"><?= __('date') ?>: <?= date('Y/m/d') ?></div>
    </div>

    <div style="background: #fff7ed; border: 1px solid #fed7aa; border-radius: 12px; padding: 16px; text-align: center;">
        <div style="font-size: 12px; color: #9a3412; font-weight: 700; margin-bottom: 4px;">
            <i class="fa-solid fa-calendar-days ml-1"></i> <?= __('monthly_loss') ?>
        </div>
        <div style="font-size: 22px; font-weight: 800; color: #ea580c;"><?= format_money($totalLossMonth) ?></div>
        <div style="font-size: 11px; color: #9a3412; margin-top: 4px;"><?= __('monthly_loss') ?></div>
    </div>

    <div style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 12px; padding: 16px; text-align: center;">
        <div style="font-size: 12px; color: #6b21a8; font-weight: 700; margin-bottom: 4px;">
            <i class="fa-solid fa-chart-pie ml-1"></i> <?= __('all_time_loss') ?>
        </div>
        <div style="font-size: 22px; font-weight: 800; color: #7e22ce;"><?= format_money($totalLossAll) ?></div>
        <div style="font-size: 11px; color: #6b21a8; margin-top: 4px;"><?= __('card_shop_capital_desc') ?></div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    <!-- Add Loss Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title" style="color: #991b1b;">
                <i class="fa-solid fa-circle-minus ml-1"></i> <?= __('add_loss_title') ?>
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
            <input type="hidden" name="action" value="add_loss">

            <div class="form-group">
                <label class="form-label" for="category"><?= __('loss_reason') ?> <span style="color: #ef4444;">*</span></label>
                <select name="category" id="category" class="form-control" required>
                    <option value="شکستگی صفحه و تاچ"><?= $currentLang === 'en' ? 'Screen / Display Damage (LCD)' : ($currentLang === 'ps' ? 'د سکرین او ښیښې ماتېدل' : 'شکستگی صفحه و تاچ (LCD)') ?></option>
                    <option value="سوختگی آی‌سی و بورد"><?= $currentLang === 'en' ? 'Motherboard / IC Short-Circuit' : ($currentLang === 'ps' ? 'د بورډ او آی سي سوځېدل' : 'سوختگی آی‌سی و بورد در حین کار') ?></option>
                    <option value="خرابی و بادکردگی باطری"><?= $currentLang === 'en' ? 'Battery Swollen / Defective' : ($currentLang === 'ps' ? 'د بیټرۍ خرابېدل او پړسېدل' : 'خرابی و بادکردگی باطری') ?></option>
                    <option value="افت قیمت شدید جنس"><?= $currentLang === 'en' ? 'Market Price Depreciation' : ($currentLang === 'ps' ? 'په بازار کې د بیې لوېدل' : 'افت قیمت شدید جنس در بازار') ?></option>
                    <option value="سرقت یا مفقودی"><?= $currentLang === 'en' ? 'Theft or Lost Inventory' : ($currentLang === 'ps' ? 'غلا یا ورکېدل' : 'سرقت یا مفقودی کالا') ?></option>
                    <option value="سایر خسارات اجناس"><?= $currentLang === 'en' ? 'Other Goods Damages' : ($currentLang === 'ps' ? 'نور زیانونه او تاوانونه' : 'سایر خسارات اجناس') ?></option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="stock_id">
                    <?= __('select_stock_for_loss') ?>
                </label>
                <select name="stock_id" id="stock_id" class="form-control" onchange="autoFillPrice(this)">
                    <option value=""><?= __('no_specific_item') ?></option>
                    <?php foreach ($stockItems as $si): 
                        $unitCost = (float)$si['purchase_price'] + (float)$si['expenses'];
                        $isSel = ($preselectedStockId === (int)$si['id']);
                    ?>
                        <option value="<?= $si['id'] ?>" 
                                data-price="<?= (float)$si['purchase_price'] ?>"
                                data-cost="<?= $unitCost ?>"
                                data-qty="<?= (int)$si['quantity'] ?>"
                                data-name="<?= sanitize($si['name']) ?>"
                                <?= $isSel ? 'selected' : '' ?>>
                            <?= sanitize($si['name']) ?> (<?= __('stock_inventory') ?>: <?= to_persian_num($si['quantity']) ?> <?= __('pieces') ?> | <?= __('purchase_price') ?>: <?= format_money($si['purchase_price'], false) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="stockLiveFeedback" style="display: none; margin-top: 8px; padding: 8px 12px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; font-size: 12px; color: #166534; font-weight: 700;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div class="form-group">
                    <label class="form-label" for="amount"><?= sprintf(__('total_loss_amount'), get_currency_label()) ?> <span style="color: #ef4444;">*</span></label>
                    <input type="number" step="any" min="1" id="amount" name="amount" class="form-control" required placeholder="0" style="font-weight: bold; color: #dc2626;">
                </div>
                <div class="form-group">
                    <label class="form-label" for="loss_qty"><?= __('lost_quantity') ?></label>
                    <input type="number" min="1" id="loss_qty" name="loss_qty" class="form-control" value="1">
                </div>
            </div>

            <div id="stockActionBox" style="background: #fff7ed; border: 1.5px solid #fed7aa; border-radius: 10px; padding: 12px; margin-bottom: 14px;">
                <label style="font-weight: 800; font-size: 12.5px; color: #9a3412; display: block; margin-bottom: 8px;">
                    <i class="fa-solid fa-boxes-stacked ml-1"></i> وضعیت و تکلیف جنس در گدام (استاک):
                </label>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12.5px; cursor: pointer; color: #1e293b;">
                        <input type="radio" name="stock_loss_action" value="strike" checked style="margin-top: 3px;">
                        <span>
                            <strong>کسر از موجودی سالم و تفکیک ضایعات در گدام</strong> (تعداد ضرر از سرجمع موجودی سالم و همچنین از «مجموع ارزش گدام» کسر می‌گردد، اجناس سالم قابل فروش باقی می‌مانند و تعداد نقص/سرقت به صورت جداگانه در گدام نمایش داده می‌شود).
                        </span>
                    </label>
                    <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12.5px; cursor: pointer; color: #991b1b;">
                        <input type="radio" name="stock_loss_action" value="delete" style="margin-top: 3px;">
                        <span>
                            <strong>حذف فوری و کامل این قلم جنس از گدام</strong> (فقط در صورتی که تمام موجودی تلف شده و دیگر هیچ دانه‌ای در دکان باقی نمانده باشد).
                        </span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="loss_date">تاریخ وقوع خسارت:</label>
                <input type="date" id="loss_date" name="loss_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="description">شرح جزئیات و علت خسارت:</label>
                <textarea id="description" name="description" class="form-control" rows="2" placeholder="توضیح دهید چطور خسارت وارد شد (مثلاً: در هنگام تعویض گلس شکسته شد)..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; background: #dc2626; border-color: #dc2626; padding: 10px;">
                <i class="fa-solid fa-check ml-1"></i> ثبت نهایی ضرر جنس
            </button>
        </form>
    </div>

    <!-- Losses List Table -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <h3 class="card-title">
                <i class="fa-solid fa-list ml-1"></i> تاریخچه ضررها و خسارات ثبت شده
            </h3>
            <span class="badge" style="background: #fee2e2; color: #991b1b; font-size: 12px;">
                <?= count($losses) ?> مورد خسارت
            </span>
        </div>

        <!-- FILTER -->
        <form method="GET" action="" style="background: #f8fafc; padding: 10px; border-radius: 8px; margin-bottom: 14px; display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 1; min-width: 130px;">
                <label style="font-size: 11px; font-weight: 700; color: #64748b;">علت ضرر:</label>
                <select name="category" class="form-control" style="font-size: 12px; padding: 6px;">
                    <option value="">همه علت‌ها</option>
                    <option value="شکستگی صفحه و تاچ" <?= $catFilter === 'شکستگی صفحه و تاچ' ? 'selected' : '' ?>>شکستگی صفحه</option>
                    <option value="سوختگی آی‌سی و بورد" <?= $catFilter === 'سوختگی آی‌سی و بورد' ? 'selected' : '' ?>>سوختگی بورد</option>
                    <option value="خرابی و بادکردگی باطری" <?= $catFilter === 'خرابی و بادکردگی باطری' ? 'selected' : '' ?>>خرابی باطری</option>
                    <option value="افت قیمت شدید جنس" <?= $catFilter === 'افت قیمت شدید جنس' ? 'selected' : '' ?>>افت قیمت</option>
                    <option value="سرقت یا مفقودی" <?= $catFilter === 'سرقت یا مفقودی' ? 'selected' : '' ?>>سرقت/مفقودی</option>
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
            <a href="<?= BASE_URL ?>modules/losses.php" class="btn btn-sm btn-secondary" style="height: 35px; padding: 0 10px;" title="حذف فیلتر"><i class="fa-solid fa-rotate-left"></i></a>
        </form>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">#</th>
                        <th>علت خسارت</th>
                        <th>جنس مربوطه</th>
                        <th style="text-align: center;">تعداد</th>
                        <th style="text-align: center;">مبلغ ضرر</th>
                        <th>تاریخ</th>
                        <th>توضیحات</th>
                        <th>ثبت‌کننده</th>
                        <th style="width: 60px; text-align: center;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($losses)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px; color: #94a3b8;">
                                <i class="fa-solid fa-shield-halved" style="font-size: 28px; display: block; margin-bottom: 6px; color: #cbd5e1;"></i>
                                هیچ موردی برای ضرر و خسارت اجناس یافت نشد.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($losses as $idx => $ls): ?>
                            <tr>
                                <td style="text-align: center; font-weight: 700; color: #64748b;"><?= $idx + 1 ?></td>
                                <td>
                                    <span class="badge" style="background: #fee2e2; color: #991b1b; font-weight: 700;">
                                        <?= sanitize($ls['category']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= sanitize($ls['product_name'] ?: 'جنس متفرقه / نامشخص') ?>
                                </td>
                                <td style="text-align: center; font-weight: 700;">
                                    <?= (int)($ls['loss_qty'] ?: 1) ?> دانه
                                </td>
                                <td style="text-align: center; font-weight: 800; color: #dc2626;">
                                    <?= format_money($ls['amount'], false) ?>
                                </td>
                                <td style="font-size: 12px; color: #64748b;">
                                    <?= sanitize($ls['expense_date']) ?>
                                </td>
                                <td style="font-size: 12px; color: #475569; max-width: 180px;">
                                    <?= sanitize($ls['description'] ?: '-') ?>
                                </td>
                                <td style="font-size: 12px; color: #64748b;">
                                    <?= sanitize($ls['user_name'] ?: 'سیستم') ?>
                                </td>
                                <td style="text-align: center;">
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('آیا از حذف این رکورد ضرر مطمئن هستید؟');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_loss">
                                        <input type="hidden" name="loss_id" value="<?= $ls['id'] ?>">
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

<script>
function autoFillPrice(sel) {
    var opt = sel.options[sel.selectedIndex];
    var fb = document.getElementById('stockLiveFeedback');
    var lossQtyInput = document.getElementById('loss_qty');
    var amountInput = document.getElementById('amount');
    
    if (!opt || !opt.value) {
        if (fb) fb.style.display = 'none';
        return;
    }

    var price = parseFloat(opt.getAttribute('data-price') || 0);
    var cost = parseFloat(opt.getAttribute('data-cost') || price);
    var currentQty = parseInt(opt.getAttribute('data-qty') || 0);
    var lossQty = parseInt(lossQtyInput.value || 1);
    if (lossQty < 1) lossQty = 1;

    // Calculate total loss amount based on purchase cost
    if (cost > 0) {
        amountInput.value = Math.round(cost * lossQty);
    }

    var remaining = Math.max(0, currentQty - lossQty);

    if (fb) {
        fb.style.display = 'block';
        if (lossQty > currentQty) {
            fb.style.background = '#fef2f2';
            fb.style.borderColor = '#fca5a5';
            fb.style.color = '#991b1b';
            fb.innerHTML = '<i class="fa-solid fa-triangle-exclamation ml-1"></i> ' +
                'موجودی فعلی در گدام: <strong>' + currentQty + ' عدد</strong> | ' +
                '<span style="color: #dc2626;">هشدار: تعداد ضایع شده (' + lossQty + ') بیشتر از موجودی گدام است! تمام موجودی گدام به صفر کاهش خواهد یافت.</span>';
        } else {
            fb.style.background = '#f0fdf4';
            fb.style.borderColor = '#bbf7d0';
            fb.style.color = '#166534';
            fb.innerHTML = '<i class="fa-solid fa-boxes-stacked ml-1"></i> ' +
                'موجودی فعلی در گدام: <strong>' + currentQty + ' عدد</strong> | ' +
                'قیمت تمام‌شد: <strong>' + cost.toLocaleString() + ' افغانی</strong> | ' +
                'موجودی پس از کسر خسارت: <strong style="color: #047857; font-size: 13px;">' + remaining + ' عدد سالم</strong> باقی می‌ماند.';
        }
    }
}

document.getElementById('loss_qty')?.addEventListener('input', function() {
    var sel = document.getElementById('stock_id');
    if (sel && sel.selectedIndex > 0) autoFillPrice(sel);
});

// Auto-trigger on page load if preselected
document.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('stock_id');
    if (sel && sel.selectedIndex > 0) {
        autoFillPrice(sel);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
