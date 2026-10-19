<?php
/**
 * NiKXor - Mobile Shop Management System
 * Debts & Loans Module (قرضه ها: طلب من از مردم / قرضداری های من / قرض الحسنه)
 * URL: http://localhost/nikxor/modules/debts.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();

$error = '';
$success = '';

// Handle Manual Debt Registration (New Debt / Payable / Hasana)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_debt') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $personName  = trim($_POST['person_name'] ?? '');
        $personPhone = trim($_POST['person_phone'] ?? '');
        $debtType    = trim($_POST['debt_type'] ?? 'receivable'); // receivable (طلب من) or payable (قرضداری من)
        $isHasana    = isset($_POST['is_hasana']) ? 1 : 0;
        $amount      = (float)normalize_digits($_POST['total_amount'] ?? '0');
        $initialPaid = (float)normalize_digits($_POST['initial_paid'] ?? '0');
        $dueDate     = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $notes       = trim($_POST['notes'] ?? '');
        $exactNow    = date('Y-m-d H:i:s');

        if (empty($personName) || $amount <= 0) {
            $error = 'لطفاً نام شخص و مبلغ قرضه را به درستی وارد نمایید.';
        } else {
            try {
                $rem = max(0, $amount - $initialPaid);
                $status = ($rem <= 0) ? 'تسویه' : 'باز';

                $stmt = $pdo->prepare("INSERT INTO debts 
                    (customer_name, customer_phone, total_amount, paid_amount, remaining_amount, status, notes, debt_type, is_hasana, due_date, created_at) 
                    VALUES (:name, :phone, :tot, :paid, :rem, :status, :notes, :dtype, :hasana, :due, :cdate)");
                
                $stmt->execute([
                    ':name'   => $personName,
                    ':phone'  => $personPhone,
                    ':tot'    => $amount,
                    ':paid'   => $initialPaid,
                    ':rem'    => $rem,
                    ':status' => $status,
                    ':notes'  => $notes,
                    ':dtype'  => $debtType,
                    ':hasana' => $isHasana,
                    ':due'    => $dueDate,
                    ':cdate'  => $exactNow
                ]);

                $newDebtId = $pdo->lastInsertId();

                if ($initialPaid > 0) {
                    $iStmt = $pdo->prepare("INSERT INTO installments (debt_id, amount, payment_date, notes, payment_reason, capital_amount, profit_amount, created_by) 
                                            VALUES (:did, :amt, :pdate, :notes, :reason, :cap, :prof, :uid)");
                    $iStmt->execute([
                        ':did'    => $newDebtId,
                        ':amt'    => $initialPaid,
                        ':pdate'  => date('Y-m-d'),
                        ':notes'  => 'پیش‌پرداخت اولیه هنگام ثبت حساب',
                        ':reason' => 'پیش‌پرداخت نقد اولیه',
                        ':cap'    => $initialPaid,
                        ':prof'   => 0,
                        ':uid'    => $currentUser['id']
                    ]);
                }

                $logTitle = ($debtType === 'payable') ? 'ثبت بدهی دکان' : 'ثبت طلب از شخص';
                if ($isHasana) $logTitle .= ' (قرض‌الحسنه)';
                log_activity($currentUser['id'], $logTitle, "ثبت مبلغ " . format_money($amount) . " به نام {$personName}");

                set_flash('قرضه مورد نظر با موفقیت در سیستم ثبت گردید.', 'success');
                redirect(BASE_URL . "modules/debts.php?type={$debtType}");
            } catch (Exception $e) {
                $error = 'خطا در ثبت قرضه: ' . $e->getMessage();
            }
        }
    }
}

// Handle Payment / Installment Receipt (با تفکیک دقیق اصل سرمایه دکان و مفاد خالص)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_installment') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $debtId  = (int)($_POST['debt_id'] ?? 0);
        $amount  = (float)normalize_digits($_POST['amount'] ?? '0');
        $payDate = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
        $notes   = trim($_POST['notes'] ?? '');

        // از درک چی بوده (دلیل و منبع پرداخت)
        $reasonCategory = trim($_POST['payment_reason'] ?? '');
        $customReason   = trim($_POST['custom_reason'] ?? '');
        if ($reasonCategory === 'سایر' && !empty($customReason)) {
            $paymentReason = $customReason;
        } elseif (!empty($reasonCategory)) {
            $paymentReason = $reasonCategory;
        } else {
            $paymentReason = 'وصولی حساب قرضه';
        }

        // تفکیک سرمایه و مفاد خالص
        $capitalAmount = (float)normalize_digits($_POST['capital_amount'] ?? '0');
        $profitAmount  = (float)normalize_digits($_POST['profit_amount'] ?? '0');

        if ($debtId <= 0 || $amount <= 0) {
            $error = 'لطفاً مبلغ پرداختی را به طور معتبر وارد نمایید.';
        } else {
            $dStmt = $pdo->prepare("SELECT d.*, s.total_amount AS sale_total, s.profit AS sale_profit 
                                    FROM debts d 
                                    LEFT JOIN sales s ON d.sale_id = s.id 
                                    WHERE d.id = :id");
            $dStmt->execute([':id' => $debtId]);
            $debt = $dStmt->fetch();

            if (!$debt) {
                $error = 'پرونده قرضه یافت نشد.';
            } else {
                // اگر کاربر فیلدهای تفکیک را دست نزد، محاسبه هوشمند خودکار انجام گیرد
                if (($capitalAmount + $profitAmount) <= 0) {
                    if (!empty($debt['sale_id']) && (float)($debt['sale_total'] ?? 0) > 0) {
                        $pRatio = max(0, (float)$debt['sale_profit'] / (float)$debt['sale_total']);
                        $profitAmount = round($amount * $pRatio, 2);
                        $capitalAmount = round($amount - $profitAmount, 2);
                    } else {
                        $capitalAmount = $amount;
                        $profitAmount  = 0;
                    }
                } else {
                    // تطبیق با مجموع مبلغ پرداختی
                    $splitTotal = $capitalAmount + $profitAmount;
                    if (abs($splitTotal - $amount) > 0.05 && $splitTotal > 0) {
                        $capitalAmount = round($amount * ($capitalAmount / $splitTotal), 2);
                        $profitAmount  = round($amount - $capitalAmount, 2);
                    }
                }

                try {
                    $pdo->beginTransaction();

                    $newPaid = (float)$debt['paid_amount'] + $amount;
                    $newRemaining = max(0, (float)$debt['total_amount'] - $newPaid);
                    $newStatus = ($newRemaining <= 0) ? 'تسویه' : 'باز';

                    // 1. Insert installment record with categorized capital & profit
                    $iStmt = $pdo->prepare("INSERT INTO installments 
                        (debt_id, amount, payment_date, notes, payment_reason, capital_amount, profit_amount, created_by) 
                        VALUES (:did, :amt, :pdate, :notes, :reason, :cap, :prof, :uid)");
                    $iStmt->execute([
                        ':did'    => $debtId,
                        ':amt'    => $amount,
                        ':pdate'  => $payDate,
                        ':notes'  => $notes ?: 'دریافت / پرداخت قسط قرضه',
                        ':reason' => $paymentReason,
                        ':cap'    => $capitalAmount,
                        ':prof'   => $profitAmount,
                        ':uid'    => $currentUser['id']
                    ]);

                    // 2. Update debt record
                    $uDebt = $pdo->prepare("UPDATE debts SET paid_amount = :paid, remaining_amount = :rem, status = :status WHERE id = :id");
                    $uDebt->execute([
                        ':paid'   => $newPaid,
                        ':rem'    => $newRemaining,
                        ':status' => $newStatus,
                        ':id'     => $debtId
                    ]);

                    // 3. If linked to sale, sync sales table paid_amount
                    if (!empty($debt['sale_id'])) {
                        $uSale = $pdo->prepare("UPDATE sales SET paid_amount = paid_amount + :amt, remaining_amount = :rem WHERE id = :sid");
                        $uSale->execute([
                            ':amt' => $amount,
                            ':rem' => $newRemaining,
                            ':sid' => $debt['sale_id']
                        ]);
                    }

                    $pdo->commit();
                    log_activity($currentUser['id'], 'رسیدگی قرضه با تفکیک', "ثبت مبلغ " . format_money($amount) . " (سرمایه: " . format_money($capitalAmount) . " | مفاد: " . format_money($profitAmount) . " | علت: {$paymentReason}) بابت حساب {$debt['customer_name']}");
                    set_flash("مبلغ " . format_money($amount) . " با موفقیت در حساب درج شد (سهم اصل سرمایه: " . format_money($capitalAmount) . " | سهم مفاد خالص: " . format_money($profitAmount) . ").", 'success');
                    redirect(BASE_URL . 'modules/debts.php?type=' . ($debt['debt_type'] ?? 'receivable'));
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'خطا در ثبت رسیدگی: ' . $e->getMessage();
                }
            }
        }
    }
}

// Handle Edit Debt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_debt') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $editId      = (int)($_POST['debt_id'] ?? 0);
        $personName  = trim($_POST['person_name'] ?? '');
        $personPhone = trim($_POST['person_phone'] ?? '');
        $totalAmount = (float)normalize_digits($_POST['total_amount'] ?? '0');
        $dueDate     = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $status      = trim($_POST['status'] ?? 'باز');
        $notes       = trim($_POST['notes'] ?? '');

        if ($editId <= 0 || empty($personName) || $totalAmount <= 0) {
            $error = 'لطفاً نام شخص و مبلغ معتبر وارد نمایید.';
        } else {
            try {
                $ex = $pdo->prepare("SELECT * FROM debts WHERE id = :id");
                $ex->execute([':id' => $editId]);
                $curr = $ex->fetch();
                if ($curr) {
                    $paid = (float)$curr['paid_amount'];
                    $rem = max(0, $totalAmount - $paid);
                    if ($rem <= 0) {
                        $status = 'تسویه';
                    }

                    $uStmt = $pdo->prepare("UPDATE debts SET 
                        customer_name = :name,
                        customer_phone = :phone,
                        total_amount = :tot,
                        remaining_amount = :rem,
                        due_date = :due,
                        status = :status,
                        notes = :notes
                        WHERE id = :id");
                    $uStmt->execute([
                        ':name'   => $personName,
                        ':phone'  => $personPhone,
                        ':tot'    => $totalAmount,
                        ':rem'    => $rem,
                        ':due'    => $dueDate,
                        ':status' => $status,
                        ':notes'  => $notes,
                        ':id'     => $editId
                    ]);

                    log_activity($currentUser['id'], 'ویرایش حساب قرضه', "ویرایش حساب قرضه #{$editId} به نام {$personName}");
                    set_flash("حساب قرضه «{$personName}» با موفقیت ویرایش گردید.", 'success');
                    redirect(BASE_URL . 'modules/debts.php?type=' . ($curr['debt_type'] ?? 'receivable'));
                }
            } catch (Exception $e) {
                $error = 'خطا در ویرایش قرضه: ' . $e->getMessage();
            }
        }
    }
}

// Handle Single Delete Debt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_debt') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $delId = (int)($_POST['debt_id'] ?? 0);
        if ($delId > 0) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM installments WHERE debt_id = :id")->execute([':id' => $delId]);
                $pdo->prepare("DELETE FROM debts WHERE id = :id")->execute([':id' => $delId]);
                $pdo->commit();

                log_activity($currentUser['id'], 'حذف حساب قرضه', "حذف حساب قرضه #{$delId}");
                set_flash("حساب قرضه مورد نظر با موفقیت حذف گردید.", 'success');
                redirect(BASE_URL . 'modules/debts.php?type=' . ($activeType ?? 'receivable'));
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'خطا در حذف قرضه: ' . $e->getMessage();
            }
        }
    }
}

// Handle Bulk Delete Debts (سلکت و حذف گروهی)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete_debts') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $selectedIds = $_POST['selected_ids'] ?? [];
        if (!is_array($selectedIds) || empty($selectedIds)) {
            set_flash('هیچ قرضه‌ای برای حذف انتخاب نشده است.', 'warning');
            redirect(BASE_URL . 'modules/debts.php?type=' . ($activeType ?? 'receivable'));
        }

        $validIds = array_values(array_filter(array_map('intval', $selectedIds), fn($id) => $id > 0));
        if (empty($validIds)) {
            set_flash('شناسه‌های ارسالی معتبر نمی‌باشند.', 'danger');
            redirect(BASE_URL . 'modules/debts.php?type=' . ($activeType ?? 'receivable'));
        }

        $placeholders = implode(',', array_fill(0, count($validIds), '?'));
        try {
            $pdo->beginTransaction();
            $delInst = $pdo->prepare("DELETE FROM installments WHERE debt_id IN ($placeholders)");
            $delInst->execute($validIds);

            $delDebts = $pdo->prepare("DELETE FROM debts WHERE id IN ($placeholders)");
            $delDebts->execute($validIds);
            $deletedCount = $delDebts->rowCount();

            $pdo->commit();
            log_activity($currentUser['id'], 'حذف گروهی قرضه‌ها', "حذف {$deletedCount} حساب قرضه انتخاب شده");
            set_flash("تعداد " . to_persian_num($deletedCount) . " حساب قرضه با موفقیت حذف گردیدند.", 'success');
            redirect(BASE_URL . 'modules/debts.php?type=' . ($activeType ?? 'receivable'));
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'خطا در حذف گروهی قرضه‌ها: ' . $e->getMessage();
        }
    }
}

// Active Tab & Filters
$activeType   = $_GET['type'] ?? 'receivable'; // receivable, payable, hasana
$statusFilter = $_GET['status'] ?? 'باز';
$search       = trim($_GET['q'] ?? '');

$sql = "SELECT d.*, s.invoice_no, s.total_amount AS sale_total, s.profit AS sale_profit 
        FROM debts d 
        LEFT JOIN sales s ON d.sale_id = s.id 
        WHERE 1=1";
$params = [];

if ($activeType === 'hasana') {
    $sql .= " AND d.is_hasana = 1";
} elseif ($activeType === 'payable') {
    $sql .= " AND d.debt_type = 'payable' AND (d.is_hasana IS NULL OR d.is_hasana = 0)";
} else {
    $sql .= " AND (d.debt_type = 'receivable' OR d.debt_type IS NULL) AND (d.is_hasana IS NULL OR d.is_hasana = 0)";
}

if ($statusFilter !== 'all') {
    $sql .= " AND d.status = :status";
    $params[':status'] = $statusFilter;
}

if ($search !== '') {
    $sql .= " AND (d.customer_name LIKE :q OR d.customer_phone LIKE :q OR s.invoice_no LIKE :q)";
    $params[':q'] = "%{$search}%";
}

$sql .= " ORDER BY d.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$debts = $stmt->fetchAll();

// Collect all installments and statistics for the loaded debts
$debtIds = array_column($debts, 'id');
$installmentsMap = [];
$debtStatsMap = [];

if (!empty($debtIds)) {
    $inClause = implode(',', array_map('intval', $debtIds));
    $iStmt = $pdo->query("SELECT i.*, u.fullname AS creator_name 
                          FROM installments i 
                          LEFT JOIN users u ON i.created_by = u.id 
                          WHERE i.debt_id IN ($inClause) 
                          ORDER BY i.payment_date DESC, i.id DESC");
    while ($inst = $iStmt->fetch()) {
        $did = $inst['debt_id'];
        $installmentsMap[$did][] = $inst;
        if (!isset($debtStatsMap[$did])) {
            $debtStatsMap[$did] = ['capital' => 0, 'profit' => 0, 'total' => 0];
        }
        $debtStatsMap[$did]['capital'] += (float)($inst['capital_amount'] ?? 0);
        $debtStatsMap[$did]['profit']  += (float)($inst['profit_amount'] ?? 0);
        $debtStatsMap[$did]['total']   += (float)($inst['amount'] ?? 0);
    }
}

// Calculations for top statistics
$totReceivables = (float)($pdo->query("SELECT SUM(remaining_amount) AS tot FROM debts WHERE (debt_type = 'receivable' OR debt_type IS NULL) AND status = 'باز'")->fetch()['tot'] ?? 0);
$totPayables    = (float)($pdo->query("SELECT SUM(remaining_amount) AS tot FROM debts WHERE debt_type = 'payable' AND status = 'باز'")->fetch()['tot'] ?? 0);
$totHasana      = (float)($pdo->query("SELECT SUM(remaining_amount) AS tot FROM debts WHERE is_hasana = 1 AND status = 'باز'")->fetch()['tot'] ?? 0);

$pageTitle = 'مدیریت قرضه ها، طلبات و تفکیک سرمایه و مفاد';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Statistics Overview -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 20px;">
    <!-- 1. طلب من از مردم -->
    <a href="?type=receivable" style="text-decoration: none; color: inherit;">
        <div class="stat-card" style="border: 2px solid <?= $activeType === 'receivable' ? '#0284c7' : '#e2e8f0' ?>;">
            <div class="stat-header">
                <span class="stat-title">طلب من از مردم (مشتریان)</span>
                <div class="stat-icon icon-debt" style="background: #e0f2fe; color: #0284c7;">
                    <i class="fa-solid fa-arrow-down-long"></i>
                </div>
            </div>
            <div class="stat-value" style="color: #0284c7;"><?= format_money($totReceivables) ?></div>
            <div class="stat-desc">مبالغی که دکان باید از خریداران وصول کند</div>
        </div>
    </a>

    <!-- 2. قرضداری های من -->
    <a href="?type=payable" style="text-decoration: none; color: inherit;">
        <div class="stat-card" style="border: 2px solid <?= $activeType === 'payable' ? '#dc2626' : '#e2e8f0' ?>;">
            <div class="stat-header">
                <span class="stat-title">قرضداری‌های من (طلب مردم از من)</span>
                <div class="stat-icon icon-debt" style="background: #fee2e2; color: #dc2626;">
                    <i class="fa-solid fa-arrow-up-long"></i>
                </div>
            </div>
            <div class="stat-value" style="color: #dc2626;"><?= format_money($totPayables) ?></div>
            <div class="stat-desc">طلب شرکت‌ها، عمده‌فروشان و اشخاص از دکان</div>
        </div>
    </a>

    <!-- 3. قرض‌الحسنه -->
    <a href="?type=hasana" style="text-decoration: none; color: inherit;">
        <div class="stat-card" style="border: 2px solid <?= $activeType === 'hasana' ? '#10b981' : '#e2e8f0' ?>;">
            <div class="stat-header">
                <span class="stat-title">قرض‌الحسنه (اعتمادی و بدون سود)</span>
                <div class="stat-icon icon-profit" style="background: #d1fae5; color: #10b981;">
                    <i class="fa-solid fa-handshake-angle"></i>
                </div>
            </div>
            <div class="stat-value" style="color: #10b981;"><?= format_money($totHasana) ?></div>
            <div class="stat-desc">کمک‌های مالی اعتمادی و بدون هیچ‌گونه سود</div>
        </div>
    </a>
</div>

<!-- Header Control Card -->
<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <h3 class="card-title" style="margin: 0;">
                <?php if ($activeType === 'payable'): ?>
                    <i class="fa-solid fa-file-invoice-dollar ml-1" style="color: #dc2626;"></i> دفتر قرضداری‌های من به مردم
                <?php elseif ($activeType === 'hasana'): ?>
                    <i class="fa-solid fa-handshake-angle ml-1" style="color: #10b981;"></i> دفتر قرض‌الحسنه (بدون سود)
                <?php else: ?>
                    <i class="fa-solid fa-hand-holding-dollar ml-1" style="color: #0284c7;"></i> دفتر طلبات من از مردم و مشتریان
                <?php endif; ?>
            </h3>
        </div>

        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="button" class="btn btn-primary" onclick="openNewDebtModal();">
                <i class="fa-solid fa-plus"></i> ثبت قرضه / طلب جدید
            </button>
            <a href="?type=<?= $activeType ?>&status=باز" class="btn btn-sm <?= $statusFilter === 'باز' ? 'btn-primary' : 'btn-secondary' ?>">
                حساب‌های باز
            </a>
            <a href="?type=<?= $activeType ?>&status=تسویه" class="btn btn-sm <?= $statusFilter === 'تسویه' ? 'btn-primary' : 'btn-secondary' ?>">
                تسویه شده
            </a>
            <a href="?type=<?= $activeType ?>&status=all" class="btn btn-sm <?= $statusFilter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">
                همه
            </a>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span><?= sanitize($error) ?></span>
    </div>
<?php endif; ?>

<!-- Bulk Selection Action Bar for Debts -->
<div id="debtBulkActionBar" class="card" style="display: none; background: #eff6ff; border: 1.5px solid #3b82f6; margin-bottom: 16px; padding: 12px 18px; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.12);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <span style="font-weight: 800; color: #1e3a8a; font-size: 14px;">
                <i class="fa-solid fa-square-check text-blue-600 ml-1"></i> <span id="debtSelectedCountText">۰</span> حساب قرضه انتخاب گردید
            </span>
            <button type="button" onclick="selectAllDebtRows(true)" class="btn btn-sm btn-secondary" style="background: #ffffff;">
                <i class="fa-solid fa-list-check ml-1"></i> انتخاب همه
            </button>
            <button type="button" onclick="selectAllDebtRows(false)" class="btn btn-sm btn-secondary" style="background: #ffffff;">
                <i class="fa-solid fa-xmark ml-1"></i> لغو انتخاب‌ها
            </button>
        </div>
        <div>
            <button type="button" onclick="confirmBulkDeleteDebts()" class="btn btn-danger btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                <i class="fa-solid fa-trash-can"></i> حذف موارد انتخاب شده
            </button>
        </div>
    </div>
</div>

<!-- Hidden Form for Bulk Deletion of Debts -->
<form id="debtBulkDeleteForm" method="POST" action="" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk_delete_debts">
    <div id="debtBulkDeleteInputsContainer"></div>
</form>

<!-- Debts Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 38px; text-align: center;">
                        <input type="checkbox" id="selectAllDebtsHeader" onchange="toggleAllDebts(this)" title="انتخاب همه">
                    </th>
                    <th style="width: 40px;">#</th>
                    <th>طرف حساب / شخص</th>
                    <th>شماره تماس</th>
                    <th>نوعیت حساب</th>
                    <th>مجموع قرضه</th>
                    <th>رسیدگی شده و تفکیک (سرمایه / سود)</th>
                    <th>باقی‌مانده</th>
                    <th>وضعیت</th>
                    <th>تاریخ ثبت</th>
                    <th style="min-width: 170px;">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($debts)): ?>
                    <tr>
                        <td colspan="11" style="text-align: center; color: #64748b; padding: 28px;">
                            هیچ رکوردی در این بخش یافت نشد.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $dNum = 1;
                    foreach ($debts as $debt): 
                        $dId = $debt['id'];
                        $stats = $debtStatsMap[$dId] ?? ['capital' => (float)$debt['paid_amount'], 'profit' => 0, 'total' => (float)$debt['paid_amount']];
                    ?>
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" class="debt-chk" value="<?= $debt['id'] ?>" onchange="updateDebtBulkBar()">
                            </td>
                            <td><?= to_persian_num($dNum++) ?></td>
                            <td>
                                <strong><?= sanitize($debt['customer_name']) ?></strong>
                                <?php if (!empty($debt['invoice_no'])): ?>
                                    <div style="font-size: 11px; margin-top: 2px;">
                                        بل: <a href="<?= BASE_URL ?>print/invoice.php?id=<?= $debt['sale_id'] ?>" target="_blank" style="color: #0284c7; text-decoration: underline;"><?= sanitize($debt['invoice_no']) ?></a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= sanitize($debt['customer_phone'] ?: '-') ?></td>
                            <td>
                                <?php if (!empty($debt['is_hasana'])): ?>
                                    <span class="badge" style="background: #dcfce7; color: #15803d; border: 1px solid #86efac;">قرض‌الحسنه</span>
                                <?php elseif ($debt['debt_type'] === 'payable'): ?>
                                    <span class="badge badge-danger">قرضداری من</span>
                                <?php else: ?>
                                    <span class="badge badge-primary">طلب من</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= format_money($debt['total_amount']) ?></strong></td>
                            <td>
                                <div style="font-weight: 700; color: #15803d;"><?= format_money($debt['paid_amount']) ?></div>
                                <?php if ((float)$debt['paid_amount'] > 0): ?>
                                    <div style="display: flex; gap: 4px; margin-top: 4px; flex-wrap: wrap;">
                                        <span style="background: #e0f2fe; color: #0369a1; font-size: 10.5px; padding: 2px 6px; border-radius: 4px; border: 1px solid #bae6fd;" title="سهم اصل سرمایه دکان">
                                            سرمایه: <?= format_money($stats['capital']) ?>
                                        </span>
                                        <span style="background: #f0fdf4; color: #15803d; font-size: 10.5px; padding: 2px 6px; border-radius: 4px; border: 1px solid #bbf7d0;" title="سهم مفاد خالص دکان">
                                            مفاد: <?= format_money($stats['profit']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: <?= (float)$debt['remaining_amount'] > 0 ? '#b91c1c' : '#15803d' ?>;">
                                    <?= format_money($debt['remaining_amount']) ?>
                                </strong>
                            </td>
                            <td>
                                <?php if ($debt['status'] === 'تسویه'): ?>
                                    <span class="badge badge-success">تسویه کامل</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">جاری (باز)</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 11px; color: #64748b;">
                                <?= !empty($debt['created_at']) ? to_persian_num(date('H:i - Y/m/d', strtotime($debt['created_at']))) : '-' ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px; align-items: center; flex-wrap: wrap;">
                                    <?php if ((float)$debt['remaining_amount'] > 0): ?>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                onclick="openPayModal(<?= $debt['id'] ?>, '<?= sanitize(addslashes($debt['customer_name'])) ?>', <?= (float)$debt['remaining_amount'] ?>, '<?= $debt['debt_type'] ?>', <?= (float)($debt['sale_total'] ?? 0) ?>, <?= (float)($debt['sale_profit'] ?? 0) ?>);" 
                                                title="رسیدگی و ثبت قسط">
                                            <i class="fa-solid fa-money-bill-wave"></i> رسیدگی
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-secondary" 
                                            onclick="openHistoryModal(<?= $debt['id'] ?>);" 
                                            title="مشاهده تاریخچه اقساط و تفکیک">
                                        <i class="fa-solid fa-list-check"></i> تفکیک
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary" 
                                            onclick='openEditDebtModal(<?= json_encode([
                                                'id' => (int)$debt['id'],
                                                'customer_name' => $debt['customer_name'],
                                                'customer_phone' => $debt['customer_phone'] ?? '',
                                                'total_amount' => (float)$debt['total_amount'],
                                                'paid_amount' => (float)$debt['paid_amount'],
                                                'remaining_amount' => (float)$debt['remaining_amount'],
                                                'due_date' => $debt['due_date'] ?? '',
                                                'status' => $debt['status'],
                                                'notes' => $debt['notes'] ?? '',
                                                'debt_type' => $debt['debt_type'] ?? 'receivable'
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);'
                                            title="ویرایش حساب قرضه">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <form method="POST" action="" onsubmit="return confirm('آیا از حذف این حساب قرضه اطمینان کامل دارید؟ تمام اقساط آن نیز حذف خواهد شد.');" style="display: inline; margin: 0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_debt">
                                        <input type="hidden" name="debt_id" value="<?= $debt['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="حذف حساب قرضه">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal 1: Register New Debt / Loan -->
<div id="newDebtModal" class="feature-modal-backdrop" onclick="if(event.target === this) closeNewDebtModal();">
    <div class="feature-modal-box" style="max-width: 520px; text-align: right;">
        <button type="button" class="feature-modal-close" onclick="closeNewDebtModal();">&times;</button>
        <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 16px; color: #0f172a;">
            <i class="fa-solid fa-plus-circle ml-1" style="color: #0284c7;"></i> ثبت قرضه / طلب جدید
        </h3>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_debt">

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label">نوعیت این حساب:</label>
                <select name="debt_type" class="form-control" id="modalDebtType">
                    <option value="receivable" <?= $activeType === 'receivable' ? 'selected' : '' ?>>طلب من از طرف حساب (او به من بدهکار است)</option>
                    <option value="payable" <?= $activeType === 'payable' ? 'selected' : '' ?>>قرضداری من به طرف حساب (من به او بدهکارم)</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 12px; background: #f0fdf4; padding: 10px; border-radius: 6px; border: 1px solid #bbf7d0;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                    <input type="checkbox" name="is_hasana" value="1" <?= $activeType === 'hasana' ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                    <span style="font-weight: 700; color: #166534; font-size: 13px;">این یک «قرض‌الحسنه» (اعتمادی و بدون سود) است</span>
                </label>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group">
                    <label class="form-label">نام طرف حساب:</label>
                    <input type="text" name="person_name" class="form-control" required placeholder="نام و تخلص">
                </div>
                <div class="form-group">
                    <label class="form-label">شماره تماس:</label>
                    <input type="text" name="person_phone" class="form-control numeric-input" placeholder="07xxxxxxxx">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group">
                    <label class="form-label">مبلغ کل قرضه (افغانی):</label>
                    <input type="number" step="any" name="total_amount" class="form-control numeric-input" required placeholder="0">
                </div>
                <div class="form-group">
                    <label class="form-label">مبلغ پرداخت شده اولیه:</label>
                    <input type="number" step="any" name="initial_paid" class="form-control numeric-input" value="0" placeholder="0">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label">تاریخ موعد بازپرداخت (اختیاری):</label>
                <input type="date" name="due_date" class="form-control">
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label">یادداشت و جزئیات:</label>
                <input type="text" name="notes" class="form-control" placeholder="علت قرضه، کالای خریداری شده، یا ضمانت">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 10px;">
                <i class="fa-solid fa-floppy-disk ml-1"></i> ذخیره در دفتر حسابات
            </button>
        </form>
    </div>
</div>

<!-- Modal 2: Pay Installment Modal with Categorization & Capital/Profit Split -->
<div id="payModal" class="feature-modal-backdrop" onclick="if(event.target === this) closePayModal();">
    <div class="feature-modal-box" style="max-width: 520px; text-align: right;">
        <button type="button" class="feature-modal-close" onclick="closePayModal();">&times;</button>
        <h3 id="payModalTitle" style="font-size: 16px; font-weight: 800; margin-bottom: 14px; color: #0f172a;">
            <i class="fa-solid fa-money-bill-transfer ml-1" style="color: #10b981;"></i> رسیدگی و ثبت قسط
        </h3>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="pay_installment">
            <input type="hidden" id="payDebtId" name="debt_id" value="0">
            <input type="hidden" id="paySaleTotal" value="0">
            <input type="hidden" id="paySaleProfit" value="0">

            <!-- Summary header -->
            <div style="background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <span style="font-size: 13px; color: #64748b;">طرف حساب: <strong id="payCustomerName" style="color: #0f172a;">-</strong></span>
                    <span style="font-size: 13px; color: #dc2626;">باقی‌مانده: <strong id="payRemainingAmt">۰ افغانی</strong></span>
                </div>
            </div>

            <!-- Amount Input -->
            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label" style="font-weight: 700; color: #0f172a;">مبلغ پرداختی این قسط (افغانی):</label>
                <input type="number" step="any" id="payAmount" name="amount" class="form-control numeric-input" required min="1" oninput="recalcBreakdown();" style="font-size: 16px; font-weight: 700; color: #0f172a;">
            </div>

            <!-- Category / Reason (از درک چی بوده) -->
            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-weight: 700; color: #0f172a;">
                    <i class="fa-solid fa-tag ml-1" style="color: #0284c7;"></i> از درک چی بوده؟ (دلیل / منبع پرداخت):
                </label>
                <select name="payment_reason" id="payReasonSelect" class="form-control" onchange="toggleCustomReason(this.value);" style="font-size: 13px;">
                    <option value="وصولی نسیه فروش موبایل">وصولی نسیه فروش موبایل (فروشگاهی)</option>
                    <option value="وصولی نسیه لوازم جانبی">وصولی نسیه لوازم جانبی و قطعات</option>
                    <option value="تسویه حساب بل دکان">تسویه حساب بل دکان</option>
                    <option value="برگشت قرض نقدی / دستی">برگشت قرض نقدی / دستی</option>
                    <option value="بازپرداخت بدهی دکان به عمده‌فروش">بازپرداخت بدهی دکان به عمده‌فروش (سرمایه)</option>
                    <option value="سایر">سایر موارد (تایپ یادداشت دستی)</option>
                </select>
                <div id="customReasonWrapper" style="display: none; margin-top: 8px;">
                    <input type="text" name="custom_reason" id="customReasonInput" class="form-control" placeholder="توضیح کوتاه منبع این پرداخت...">
                </div>
            </div>

            <!-- Capital vs Net Profit Breakdown Card -->
            <div style="background: #f0f9ff; border: 1.5px solid #bae6fd; border-radius: 8px; padding: 14px; margin-bottom: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 13px; font-weight: 800; color: #0369a1;">
                        <i class="fa-solid fa-scale-balanced ml-1"></i> کته‌گوری‌بندی سرمایه و مفاد خالص:
                    </span>
                    <span id="splitRatioBadge" style="font-size: 11px; background: #ffffff; padding: 2px 8px; border-radius: 4px; color: #0284c7; border: 1px solid #7dd3fc;">
                        توزیع متعادل
                    </span>
                </div>

                <!-- Quick Presets -->
                <div style="display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-sm" onclick="setSplitMode('auto');" style="background: #0284c7; color: #ffffff; font-size: 11px; padding: 3px 8px; border: none; border-radius: 4px;">
                        <i class="fa-solid fa-wand-magic-sparkles ml-1"></i> محاسبه هوشمند سود بل
                    </button>
                    <button type="button" class="btn btn-sm" onclick="setSplitMode('full_capital');" style="background: #e2e8f0; color: #334155; font-size: 11px; padding: 3px 8px; border: none; border-radius: 4px;">
                        ۱۰۰٪ اصل سرمایه دکان
                    </button>
                    <button type="button" class="btn btn-sm" onclick="setSplitMode('full_profit');" style="background: #e2e8f0; color: #334155; font-size: 11px; padding: 3px 8px; border: none; border-radius: 4px;">
                        ۱۰۰٪ مفاد خالص دکان
                    </button>
                </div>

                <!-- Two Interactive Inputs -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <label class="form-label" style="font-size: 12px; color: #0369a1; margin-bottom: 4px;">
                            سهم اصل سرمایه دکان:
                        </label>
                        <input type="number" step="any" id="payCapital" name="capital_amount" class="form-control numeric-input" style="background: #ffffff; border-color: #7dd3fc; font-weight: 700; color: #0369a1;" oninput="syncFromCapital();">
                        <span style="font-size: 10px; color: #64748b;">(هزینه خرید/سرمایه اولیه کالا)</span>
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 12px; color: #15803d; margin-bottom: 4px;">
                            سهم مفاد خالص دکان:
                        </label>
                        <input type="number" step="any" id="payProfit" name="profit_amount" class="form-control numeric-input" style="background: #ffffff; border-color: #86efac; font-weight: 700; color: #15803d;" oninput="syncFromProfit();">
                        <span style="font-size: 10px; color: #64748b;">(سود حاصله از این وصولی)</span>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                <div class="form-group">
                    <label class="form-label">تاریخ رسیدگی:</label>
                    <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">توضیحات رسید / شماره حواله:</label>
                    <input type="text" name="notes" class="form-control" placeholder="شماره رسید، حواله، یا نام شخص">
                </div>
            </div>

            <button type="submit" class="btn btn-success" style="width: 100%; justify-content: center; padding: 12px; font-weight: 700; font-size: 14px;">
                <i class="fa-solid fa-check ml-1"></i> ثبت پرداخت با تفکیک سرمایه و مفاد
            </button>
        </form>
    </div>
</div>

<!-- Modal 3: View Installment History & Breakdown (تفکیک و تاریخچه اقساط) -->
<div id="historyModal" class="feature-modal-backdrop" onclick="if(event.target === this) closeHistoryModal();">
    <div class="feature-modal-box" style="max-width: 680px; text-align: right;">
        <button type="button" class="feature-modal-close" onclick="closeHistoryModal();">&times;</button>
        <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 14px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-receipt" style="color: #0284c7;"></i>
            <span>ریز اقساط و تفکیک سرمایه/مفاد - <strong id="histCustomerName" style="color: #0284c7;"></strong></span>
        </h3>

        <!-- Summary Cards -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 16px;">
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px;">
                <div style="font-size: 11px; color: #64748b;">مجموع کل پرداختی:</div>
                <div id="histTotPaid" style="font-size: 15px; font-weight: 800; color: #0f172a; margin-top: 4px;">۰ افغانی</div>
            </div>
            <div style="background: #e0f2fe; border: 1px solid #bae6fd; border-radius: 8px; padding: 10px 14px;">
                <div style="font-size: 11px; color: #0369a1;">کل اصل سرمایه وصول شده:</div>
                <div id="histTotCapital" style="font-size: 15px; font-weight: 800; color: #0284c7; margin-top: 4px;">۰ افغانی</div>
            </div>
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 14px;">
                <div style="font-size: 11px; color: #166534;">کل مفاد خالص بدست‌آمده:</div>
                <div id="histTotProfit" style="font-size: 15px; font-weight: 800; color: #15803d; margin-top: 4px;">۰ افغانی</div>
            </div>
        </div>

        <!-- History Table -->
        <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
            <table class="table" style="font-size: 12.5px;">
                <thead style="position: sticky; top: 0; background: #ffffff; z-index: 1;">
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th>تاریخ</th>
                        <th>از درک چی بوده</th>
                        <th>سهم سرمایه</th>
                        <th>سهم مفاد</th>
                        <th>کل قسط</th>
                        <th>یادداشت / ثبت‌کننده</th>
                    </tr>
                </thead>
                <tbody id="histTableBody">
                    <!-- Populated by JavaScript -->
                </tbody>
            </table>
        </div>

        <div style="margin-top: 14px; text-align: left;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeHistoryModal();">
                بستن پنجره
            </button>
        </div>
    </div>
</div>

<!-- Modal 4: Edit Debt Modal (ویرایش مشخصات قرضه) -->
<div id="editDebtModal" class="feature-modal-backdrop" onclick="if(event.target === this) closeEditDebtModal();">
    <div class="feature-modal-box" style="max-width: 520px; text-align: right;">
        <button type="button" class="feature-modal-close" onclick="closeEditDebtModal();">&times;</button>
        <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 16px; color: #0f172a;">
            <i class="fa-solid fa-pen-to-square ml-1" style="color: #0284c7;"></i> ویرایش حساب قرضه
        </h3>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_debt">
            <input type="hidden" name="debt_id" id="editDebtId" value="0">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group">
                    <label class="form-label">نام طرف حساب:</label>
                    <input type="text" name="person_name" id="editPersonName" class="form-control" required placeholder="نام شخص">
                </div>
                <div class="form-group">
                    <label class="form-label">شماره تماس:</label>
                    <input type="text" name="person_phone" id="editPersonPhone" class="form-control numeric-input" placeholder="07xxxxxxxx">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group">
                    <label class="form-label">مجموع کل قرضه (افغانی):</label>
                    <input type="number" step="any" name="total_amount" id="editTotalAmount" class="form-control numeric-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">وضعیت حساب:</label>
                    <select name="status" id="editStatus" class="form-control">
                        <option value="باز">جاری (باز)</option>
                        <option value="تسویه">تسویه کامل</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label">موعد بازپرداخت:</label>
                <input type="date" name="due_date" id="editDueDate" class="form-control">
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label">یادداشت و جزئیات:</label>
                <input type="text" name="notes" id="editNotes" class="form-control">
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center; padding: 10px;">
                    <i class="fa-solid fa-check ml-1"></i> ذخیره تغییرات
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeEditDebtModal();">
                    انصراف
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Embedded Installments JSON Map for Fast Instant Client-Side Viewing -->
<script>
const installmentsData = <?= json_encode($installmentsMap, JSON_UNESCAPED_UNICODE) ?>;
const debtsData = <?= json_encode(array_column($debts, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;

function openEditDebtModal(data) {
    document.getElementById('editDebtId').value = data.id;
    document.getElementById('editPersonName').value = data.customer_name || '';
    document.getElementById('editPersonPhone').value = data.customer_phone || '';
    document.getElementById('editTotalAmount').value = data.total_amount || 0;
    document.getElementById('editDueDate').value = data.due_date || '';
    document.getElementById('editStatus').value = data.status || 'باز';
    document.getElementById('editNotes').value = data.notes || '';
    document.getElementById('editDebtModal').style.display = 'flex';
}

function closeEditDebtModal() {
    document.getElementById('editDebtModal').style.display = 'none';
}

// Bulk Selection Logic for Debts
function toggleAllDebts(headerMaster) {
    const checkboxes = document.querySelectorAll('.debt-chk');
    checkboxes.forEach(cb => cb.checked = headerMaster.checked);
    updateDebtBulkBar();
}

function selectAllDebtRows(checkAll) {
    const checkboxes = document.querySelectorAll('.debt-chk');
    checkboxes.forEach(cb => cb.checked = checkAll);
    const master = document.getElementById('selectAllDebtsHeader');
    if (master) master.checked = checkAll;
    updateDebtBulkBar();
}

function updateDebtBulkBar() {
    const checkboxes = document.querySelectorAll('.debt-chk:checked');
    const bar = document.getElementById('debtBulkActionBar');
    const countText = document.getElementById('debtSelectedCountText');
    const count = checkboxes.length;

    if (count > 0) {
        bar.style.display = 'block';
        countText.textContent = count.toLocaleString('fa-IR');
    } else {
        bar.style.display = 'none';
    }
}

function confirmBulkDeleteDebts() {
    const checkboxes = document.querySelectorAll('.debt-chk:checked');
    if (checkboxes.length === 0) {
        alert('لطفاً حداقل یک قرضه را برای حذف انتخاب نمایید.');
        return;
    }

    if (!confirm(`آیا از حذف تعداد ${checkboxes.length} حساب قرضه انتخاب شده به صورت گروهی اطمینان کامل دارید؟ تمامی اقساط مربوطه نیز حذف خواهند شد.`)) {
        return;
    }

    const container = document.getElementById('debtBulkDeleteInputsContainer');
    container.innerHTML = '';
    checkboxes.forEach(cb => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'selected_ids[]';
        hidden.value = cb.value;
        container.appendChild(hidden);
    });

    document.getElementById('debtBulkDeleteForm').submit();
}

function openNewDebtModal() {
    document.getElementById('newDebtModal').style.display = 'flex';
}
function closeNewDebtModal() {
    document.getElementById('newDebtModal').style.display = 'none';
}

function openPayModal(id, name, remaining, type, saleTotal, saleProfit) {
    document.getElementById('payDebtId').value = id;
    document.getElementById('payCustomerName').textContent = name;
    document.getElementById('payRemainingAmt').textContent = Number(remaining).toLocaleString('en-US') + ' افغانی';
    document.getElementById('payAmount').value = remaining;
    document.getElementById('paySaleTotal').value = saleTotal || 0;
    document.getElementById('paySaleProfit').value = saleProfit || 0;

    document.getElementById('payModalTitle').innerHTML = '<i class="fa-solid fa-money-bill-transfer ml-1" style="color: #10b981;"></i> ' + 
        ((type === 'payable') ? 'پرداخت بدهی به ' + name : 'دریافت طلب از ' + name);

    // Initial breakdown calculation
    recalcBreakdown();

    document.getElementById('payModal').style.display = 'flex';
}
function closePayModal() {
    document.getElementById('payModal').style.display = 'none';
}

function toggleCustomReason(val) {
    const wrap = document.getElementById('customReasonWrapper');
    if (val === 'سایر') {
        wrap.style.display = 'block';
        document.getElementById('customReasonInput').focus();
    } else {
        wrap.style.display = 'none';
    }
}

function recalcBreakdown() {
    const amt = parseFloat(document.getElementById('payAmount').value) || 0;
    const sTot = parseFloat(document.getElementById('paySaleTotal').value) || 0;
    const sProf = parseFloat(document.getElementById('paySaleProfit').value) || 0;

    if (amt <= 0) {
        document.getElementById('payCapital').value = 0;
        document.getElementById('payProfit').value = 0;
        document.getElementById('splitRatioBadge').textContent = 'مبلغ صفر';
        return;
    }

    if (sTot > 0 && sProf > 0) {
        const profitRatio = sProf / sTot;
        const prof = Math.round(amt * profitRatio);
        const cap = amt - prof;
        document.getElementById('payCapital').value = cap;
        document.getElementById('payProfit').value = prof;
        const capPct = Math.round((cap / amt) * 100);
        const profPct = 100 - capPct;
        document.getElementById('splitRatioBadge').textContent = `سرمایه: ${capPct}% | مفاد: ${profPct}%`;
    } else {
        document.getElementById('payCapital').value = amt;
        document.getElementById('payProfit').value = 0;
        document.getElementById('splitRatioBadge').textContent = '۱۰۰٪ اصل سرمایه';
    }
}

function setSplitMode(mode) {
    const amt = parseFloat(document.getElementById('payAmount').value) || 0;
    if (mode === 'auto') {
        recalcBreakdown();
    } else if (mode === 'full_capital') {
        document.getElementById('payCapital').value = amt;
        document.getElementById('payProfit').value = 0;
        document.getElementById('splitRatioBadge').textContent = '۱۰۰٪ اصل سرمایه';
    } else if (mode === 'full_profit') {
        document.getElementById('payCapital').value = 0;
        document.getElementById('payProfit').value = amt;
        document.getElementById('splitRatioBadge').textContent = '۱۰۰٪ مفاد خالص';
    }
}

function syncFromCapital() {
    const amt = parseFloat(document.getElementById('payAmount').value) || 0;
    const cap = parseFloat(document.getElementById('payCapital').value) || 0;
    const prof = Math.max(0, amt - cap);
    document.getElementById('payProfit').value = prof;
    if (amt > 0) {
        const capPct = Math.round((cap / amt) * 100);
        document.getElementById('splitRatioBadge').textContent = `سرمایه: ${capPct}% | مفاد: ${100 - capPct}%`;
    }
}

function syncFromProfit() {
    const amt = parseFloat(document.getElementById('payAmount').value) || 0;
    const prof = parseFloat(document.getElementById('payProfit').value) || 0;
    const cap = Math.max(0, amt - prof);
    document.getElementById('payCapital').value = cap;
    if (amt > 0) {
        const capPct = Math.round((cap / amt) * 100);
        document.getElementById('splitRatioBadge').textContent = `سرمایه: ${capPct}% | مفاد: ${100 - capPct}%`;
    }
}

function openHistoryModal(debtId) {
    const debt = debtsData[debtId] || {};
    const records = installmentsData[debtId] || [];

    document.getElementById('histCustomerName').textContent = debt.customer_name || 'پرونده قرضه';

    let totPaid = 0;
    let totCapital = 0;
    let totProfit = 0;

    let html = '';
    if (records.length === 0) {
        html = '<tr><td colspan="7" style="text-align: center; color: #64748b; padding: 20px;">هیچ پرداختی تا کنون ثبت نشده است.</td></tr>';
    } else {
        records.forEach((r, idx) => {
            const amt = parseFloat(r.amount) || 0;
            const cap = parseFloat(r.capital_amount) || 0;
            const prof = parseFloat(r.profit_amount) || 0;
            totPaid += amt;
            totCapital += cap;
            totProfit += prof;

            html += `<tr>
                <td>${idx + 1}</td>
                <td><strong>${r.payment_date || '-'}</strong></td>
                <td><span class="badge" style="background: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1;">${r.payment_reason || 'وصولی قرضه'}</span></td>
                <td style="color: #0284c7; font-weight: 700;">${Number(cap).toLocaleString('en-US')} افغانی</td>
                <td style="color: #15803d; font-weight: 700;">${Number(prof).toLocaleString('en-US')} افغانی</td>
                <td style="font-weight: 800;">${Number(amt).toLocaleString('en-US')} افغانی</td>
                <td style="font-size: 11px; color: #64748b;">${r.notes || '-'} <span style="font-size: 10px; color: #94a3b8;">(${r.creator_name || 'سیستم'})</span></td>
            </tr>`;
        });
    }

    document.getElementById('histTotPaid').textContent = Number(totPaid).toLocaleString('en-US') + ' افغانی';
    document.getElementById('histTotCapital').textContent = Number(totCapital).toLocaleString('en-US') + ' افغانی';
    document.getElementById('histTotProfit').textContent = Number(totProfit).toLocaleString('en-US') + ' افغانی';
    document.getElementById('histTableBody').innerHTML = html;

    document.getElementById('historyModal').style.display = 'flex';
}

function closeHistoryModal() {
    document.getElementById('historyModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
