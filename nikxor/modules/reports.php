<?php
/**
 * NiKXor - Mobile Shop Management System
 * Comprehensive Financial & Sales Reports (گزارشات مالی دکان)
 * URL: http://localhost/nikxor/modules/reports.php
 */

$pageTitle = 'گزارشات مالی و حسابداری دکان';
require_once __DIR__ . '/../includes/header.php';

// Date range filters
$quick = $_GET['quick'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

if ($quick === 'today') {
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d');
} elseif ($quick === 'this_week') {
    $startDate = date('Y-m-d', strtotime('-6 days'));
    $endDate = date('Y-m-d');
} elseif ($quick === 'this_month') {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-d');
} elseif (empty($startDate) && empty($endDate)) {
    // Default to this month
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-d');
}

// Queries with date filters
$saleParams = [':s' => $startDate . ' 00:00:00', ':e' => $endDate . ' 23:59:59'];

// Sales stats in period
$salesStmt = $pdo->prepare("SELECT 
    COUNT(*) AS total_invoices,
    SUM(total_amount) AS total_sales_amount,
    SUM(paid_amount) AS total_paid_amount,
    SUM(remaining_amount) AS total_remaining_amount,
    SUM(profit) AS total_gross_profit
    FROM sales 
    WHERE created_at BETWEEN :s AND :e");
$salesStmt->execute($saleParams);
$salesStats = $salesStmt->fetch();

$totalInvoices        = (int)($salesStats['total_invoices'] ?? 0);
$totalSalesAmount     = (float)($salesStats['total_sales_amount'] ?? 0);
$totalPaidAmount      = (float)($salesStats['total_paid_amount'] ?? 0);
$totalRemainingAmount = (float)($salesStats['total_remaining_amount'] ?? 0);
$totalGrossProfit     = (float)($salesStats['total_gross_profit'] ?? 0);

// Expenses and losses in period (تفکیک مستقل مصارف جاری از ضرر و خسارات اجناس)
$expStmt = $pdo->prepare("SELECT 
    COALESCE(SUM(CASE WHEN expense_type = 'ضرر' THEN amount ELSE 0 END), 0) AS total_losses,
    COALESCE(SUM(CASE WHEN expense_type != 'ضرر' OR expense_type IS NULL THEN amount ELSE 0 END), 0) AS total_expenses
    FROM expenses 
    WHERE (expense_date BETWEEN :s AND :e) OR (expense_date IS NULL AND created_at BETWEEN :s_full AND :e_full)");
$expStmt->execute([
    ':s' => $startDate, 
    ':e' => $endDate,
    ':s_full' => $startDate . ' 00:00:00',
    ':e_full' => $endDate . ' 23:59:59'
]);
$expStats = $expStmt->fetch();

$totalExpenses = (float)($expStats['total_expenses'] ?? 0);
$totalLosses   = (float)($expStats['total_losses'] ?? 0);
$totalDeductions = $totalExpenses + $totalLosses;

// Net Profit in period
$netProfit = $totalGrossProfit - $totalDeductions;

// Top sold items in period
$topItemsStmt = $pdo->prepare("SELECT 
    product_name,
    SUM(quantity) AS sold_qty,
    SUM(total_price) AS total_revenue,
    SUM(profit) AS total_item_profit
    FROM sale_items 
    WHERE created_at BETWEEN :s AND :e
    GROUP BY product_name 
    ORDER BY sold_qty DESC LIMIT 10");
$topItemsStmt->execute($saleParams);
$topItems = $topItemsStmt->fetchAll();

// Installments collected in period
$instStmt = $pdo->prepare("SELECT SUM(amount) AS total FROM installments WHERE payment_date BETWEEN :s AND :e");
$instStmt->execute([':s' => $startDate, ':e' => $endDate]);
$installmentsCollected = (float)($instStmt->fetch()['total'] ?? 0);

// Mudaraba Payouts in period (پرداختی‌ها و تسویه‌ها با شرکای مضاربت در این بازه)
$mudPayoutRecords = [];
$mudarabaPayoutInPeriod = 0;
try {
    $recStmt = $pdo->prepare("SELECT * FROM capital 
        WHERE transaction_type = 'برداشت' 
          AND (source_description LIKE '%مضاربت%' OR source_description LIKE '%شریک%')
          AND (
              (transaction_date IS NOT NULL AND transaction_date BETWEEN :s AND :e)
              OR (transaction_date IS NULL AND created_at BETWEEN :s_full AND :e_full)
          )
        ORDER BY COALESCE(transaction_date, created_at) DESC, id DESC");
    $recStmt->execute([
        ':s'      => $startDate, 
        ':e'      => $endDate,
        ':s_full' => $startDate . ' 00:00:00',
        ':e_full' => $endDate . ' 23:59:59'
    ]);
    $mudPayoutRecords = $recStmt->fetchAll();
    foreach ($mudPayoutRecords as $mpr) {
        $mudarabaPayoutInPeriod += (float)$mpr['amount'];
    }
} catch (Exception $e) {
    // Fallback if transaction_date doesn't exist yet
    try {
        $mudPayoutStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total_mud_payout FROM capital WHERE transaction_type = 'برداشت' AND (source_description LIKE '%مضاربت%' OR source_description LIKE '%شریک%')");
        $mudPayoutStmt->execute();
        $mudarabaPayoutInPeriod = (float)$mudPayoutStmt->fetchColumn();
    } catch (Exception $e2) {}
}

// Detailed list of Mudaraba contracts
$mudContracts = [];
$totalMudActiveCap = 0;
$totalMudPaidProfit = 0;
$totalMudReturnedCap = 0;
$totalMudSales = 0;
$totalMudProfit = 0;
try {
    $mudContracts = $pdo->query("SELECT * FROM mudaraba_contracts ORDER BY id DESC")->fetchAll();
    foreach ($mudContracts as $mc) {
        if ($mc['status'] === 'active') {
            $totalMudActiveCap += (float)$mc['capital_amount'];
        }
        $totalMudSales       += (float)$mc['total_sales_amount'];
        $totalMudProfit      += (float)$mc['total_profit_amount'];
        $totalMudPaidProfit  += (float)($mc['paid_profit_to_partner'] ?? 0);
        $totalMudReturnedCap += (float)($mc['returned_capital'] ?? 0);
    }
} catch (Exception $mEx) {}
?>

<div class="card no-print" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <h3 class="card-title" style="margin-bottom: 4px;">
                <i class="fa-solid fa-chart-line ml-1"></i> فیلتر گزارشات مالی و سود دکان
            </h3>
            <span style="font-size: 12px; color: #64748b;">
                بازه زمانی: از <?= to_persian_num($startDate) ?> تا <?= to_persian_num($endDate) ?>
            </span>
        </div>

        <div style="display: flex; gap: 6px;">
            <a href="<?= BASE_URL ?>modules/reports.php?quick=today" class="btn btn-sm <?= $quick === 'today' ? 'btn-primary' : 'btn-secondary' ?>">امروز</a>
            <a href="<?= BASE_URL ?>modules/reports.php?quick=this_week" class="btn btn-sm <?= $quick === 'this_week' ? 'btn-primary' : 'btn-secondary' ?>">۷ روز اخیر</a>
            <a href="<?= BASE_URL ?>modules/reports.php?quick=this_month" class="btn btn-sm <?= ($quick === 'this_month' || empty($quick)) ? 'btn-primary' : 'btn-secondary' ?>">این ماه</a>
            <button type="button" onclick="window.print()" class="btn btn-sm btn-primary">
                <i class="fa-solid fa-print ml-1"></i> چاپ گزارش
            </button>
        </div>
    </div>

    <!-- Custom date filter -->
    <form method="GET" action="" style="margin-top: 14px; display: flex; gap: 10px; align-items: end; flex-wrap: wrap;">
        <div>
            <label class="form-label" style="font-size: 11.5px;">از تاریخ:</label>
            <input type="date" name="start_date" class="form-control" value="<?= sanitize($startDate) ?>" required>
        </div>
        <div>
            <label class="form-label" style="font-size: 11.5px;">تا تاریخ:</label>
            <input type="date" name="end_date" class="form-control" value="<?= sanitize($endDate) ?>" required>
        </div>
        <div>
            <button type="submit" class="btn btn-secondary" style="height: 38px;">
                <i class="fa-solid fa-magnifying-glass"></i> اعمال تاریخ
            </button>
        </div>
    </form>
</div>

<!-- Report Summary Cards Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title"><?= __('period_sales') ?></span>
            <div class="stat-icon icon-sales"><i class="fa-solid fa-cart-shopping"></i></div>
        </div>
        <div class="stat-value"><?= format_money($totalSalesAmount) ?></div>
        <div class="stat-desc"><?= to_persian_num($totalInvoices) ?> <?= __('nav_invoices') ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title"><?= __('period_gross_profit') ?></span>
            <div class="stat-icon icon-profit"><i class="fa-solid fa-arrow-trend-up"></i></div>
        </div>
        <div class="stat-value" style="color: #15803d;"><?= format_money($totalGrossProfit) ?></div>
        <div class="stat-desc"><?= __('card_today_sales_profit') ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title"><?= __('period_expenses') ?></span>
            <div class="stat-icon icon-debt" style="background: rgba(245, 158, 11, 0.15); color: #d97706;"><i class="fa-solid fa-receipt"></i></div>
        </div>
        <div class="stat-value" style="color: #d97706;"><?= format_money($totalExpenses) ?></div>
        <div class="stat-desc"><?= __('nav_expenses') ?></div>
    </div>

    <div class="stat-card" style="border-right: 4px solid #ef4444;">
        <div class="stat-header">
            <span class="stat-title"><?= __('period_losses') ?></span>
            <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #dc2626;"><i class="fa-solid fa-triangle-exclamation"></i></div>
        </div>
        <div class="stat-value" style="color: #dc2626;"><?= format_money($totalLosses) ?></div>
        <div class="stat-desc"><?= __('card_damages') ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title"><?= $netProfit >= 0 ? __('period_net_profit') : __('period_net_loss') ?></span>
            <div class="stat-icon" style="background: <?= $netProfit >= 0 ? 'rgba(22, 163, 74, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; color: <?= $netProfit >= 0 ? '#15803d' : '#dc2626' ?>;"><i class="fa-solid <?= $netProfit >= 0 ? 'fa-sack-dollar' : 'fa-arrow-trend-down' ?>"></i></div>
        </div>
        <div class="stat-value" style="color: <?= $netProfit >= 0 ? '#15803d' : '#dc2626' ?>; font-size: 24px;">
            <?= $netProfit >= 0 ? '+' : '-' ?><?= format_money(abs($netProfit)) ?>
        </div>
        <div class="stat-desc">
            <?= $netProfit >= 0 ? __('card_overall_profit') : __('card_overall_loss') ?>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
    <!-- Cash Flow in Period -->
    <div class="card" style="position: relative;">
        <div class="card-header">
            <h3 class="card-title" style="display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-money-bill-transfer ml-1"></i> وضعیت نقدینگی و قرضه ها در این بازه
                <?php if (is_feature_locked('liquidity_report')): ?>
                    <span class="badge-lock-tag" onclick="showLockModal('liquidity_report', 'Cash Flow & Debts Period Report', 'وضعیت نقدینگی و قرضه‌ها در این دوره');" style="cursor: pointer;">
                        <i class="fa-solid fa-lock text-amber-500"></i> قفل
                    </span>
                <?php endif; ?>
            </h3>
        </div>
        <?php if (is_feature_locked('liquidity_report')): ?>
            <div class="locked-blur-wrapper" style="min-height: 220px;">
                <div class="locked-blur-content">
                    <table class="table">
                        <tbody>
                            <tr><td>مجموع نقدینگی دریافت شده از فروشات:</td><td><strong>120,000 افغانی</strong></td></tr>
                            <tr><td>اقساط و وصولی های دریافتی از قرضه ها:</td><td><strong>45,000 افغانی</strong></td></tr>
                            <tr><td>مجموع کل پول نقد وارد شده به صندوق:</td><td><strong>165,000 افغانی</strong></td></tr>
                            <tr><td>قرضه های پرداخت نشده مشتریان:</td><td><strong>30,000 افغانی</strong></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="locked-overlay-card" style="padding: 16px;">
                    <h4 style="font-size: 13.5px; margin-bottom: 6px;"><i class="fa-solid fa-lock text-amber-500"></i> این گزینه قفل می‌باشد</h4>
                    <p style="font-size: 12px; margin-bottom: 6px;">جهت درخواست این گزینه با احمد نجیم نیک تماس بگیرید</p>
                    <p class="en-notice" style="font-size: 10.5px; margin-bottom: 8px;">This feature is currently locked. To request access, contact Ahmad Najeem Nik.</p>
                    <a href="https://wa.me/93783788278?text=Hello%20Ahmad%20Najeem%20Nik,%20I%20would%20like%20to%20unlock%20Cash%20Flow%20Report" target="_blank" class="btn-whatsapp-direct" style="padding: 6px 12px; font-size: 11px;">
                        <i class="fa-brands fa-whatsapp"></i> واتس‌اپ احمد نجیم نیک
                    </a>
                </div>
            </div>
        <?php else: ?>
            <table class="table">
                <tbody>
                    <tr>
                        <td>مجموع نقدینگی دریافت شده از فروشات:</td>
                        <td><strong style="color: #15803d;"><?= format_money($totalPaidAmount) ?></strong></td>
                    </tr>
                    <tr>
                        <td>اقساط و وصولی های دریافتی از قرضه ها:</td>
                        <td><strong style="color: #15803d;"><?= format_money($installmentsCollected) ?></strong></td>
                    </tr>
                    <tr>
                        <td>مجموع کل پول نقد وارد شده به صندوق:</td>
                        <td><strong style="color: #0f172a; font-size: 14px;"><?= format_money($totalPaidAmount + $installmentsCollected) ?></strong></td>
                    </tr>
                    <tr>
                        <td>مصارف نقدی جاری دکان (پرداخت‌شده از دخل):</td>
                        <td><strong style="color: #ea580c;"><?= format_money($totalExpenses) ?></strong></td>
                    </tr>
                    <tr>
                        <td>ارزش ضرر و خسارات اجناس گدام (افت ارزش موجودی):</td>
                        <td><strong style="color: #dc2626;"><?= format_money($totalLosses) ?></strong></td>
                    </tr>
                    <tr>
                        <td>پرداختی‌ها و تسویه با شرکای مضاربت در این بازه:</td>
                        <td><strong style="color: #dc2626;"><?= format_money($mudarabaPayoutInPeriod) ?></strong></td>
                    </tr>
                    <tr style="background: #f8fafc;">
                        <td><strong>خالص نقدینگی نهایی صندوق (دخل نقدی):</strong></td>
                        <?php $netCash = ($totalPaidAmount + $installmentsCollected) - $totalExpenses - $mudarabaPayoutInPeriod; ?>
                        <td><strong style="color: <?= $netCash >= 0 ? '#15803d' : '#b91c1c' ?>; font-size: 15px;"><?= format_money($netCash) ?></strong></td>
                    </tr>
                    <tr>
                        <td>قرضه های ثبت شده و پرداخت نشده مشتریان در این بازه:</td>
                        <td><strong style="color: #b91c1c;"><?= format_money($totalRemainingAmount) ?></strong></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Top Sold Products -->
    <div class="card" style="position: relative;">
        <div class="card-header">
            <h3 class="card-title" style="display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-fire ml-1"></i> پرفروش ترین اجناس در این دوره
                <?php if (is_feature_locked('top_selling_report')): ?>
                    <span class="badge-lock-tag" onclick="showLockModal('top_selling_report', 'Top Selling Products Report', 'پرفروش‌ترین اجناس در این دوره');" style="cursor: pointer;">
                        <i class="fa-solid fa-lock text-amber-500"></i> قفل
                    </span>
                <?php endif; ?>
            </h3>
        </div>
        <?php if (is_feature_locked('top_selling_report')): ?>
            <div class="locked-blur-wrapper" style="min-height: 220px;">
                <div class="locked-blur-content">
                    <table class="table">
                        <thead><tr><th>اسم جنس</th><th>تعداد</th><th>مجموع فروش</th><th>مفاد</th></tr></thead>
                        <tbody>
                            <tr><td>سامسونگ A55 5G</td><td>۱۲</td><td>240,000</td><td>18,000</td></tr>
                            <tr><td>چارجر اصل 25W سامسونگ</td><td>۳۵</td><td>17,500</td><td>5,250</td></tr>
                            <tr><td>آیفون 13 نرمال 128GB</td><td>۴</td><td>180,000</td><td>12,000</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="locked-overlay-card" style="padding: 16px;">
                    <h4 style="font-size: 13.5px; margin-bottom: 6px;"><i class="fa-solid fa-lock text-amber-500"></i> این گزینه قفل می‌باشد</h4>
                    <p style="font-size: 12px; margin-bottom: 6px;">جهت درخواست این گزینه با احمد نجیم نیک تماس بگیرید</p>
                    <p class="en-notice" style="font-size: 10.5px; margin-bottom: 8px;">This feature is currently locked. To request access and unlock this feature, please contact Ahmad Najeem Nik.</p>
                    <a href="https://wa.me/93783788278?text=Hello%20Ahmad%20Najeem%20Nik,%20I%20would%20like%20to%20unlock%20Top%20Selling%20Products%20Report" target="_blank" class="btn-whatsapp-direct" style="padding: 6px 12px; font-size: 11px;">
                        <i class="fa-brands fa-whatsapp"></i> واتس‌اپ احمد نجیم نیک
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>اسم جنس</th>
                            <th style="width: 70px; text-align: center;">تعداد</th>
                            <th style="width: 90px;">مجموع فروش</th>
                            <th style="width: 80px;">مفاد حاصله</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topItems)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #64748b; padding: 16px;">
                                    در این بازه زمانی فروشی ثبت نگردیده است.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topItems as $top): ?>
                                <tr>
                                    <td><strong><?= sanitize($top['product_name']) ?></strong></td>
                                    <td style="text-align: center;"><span class="badge badge-secondary"><?= to_persian_num($top['sold_qty']) ?></span></td>
                                    <td><?= format_money($top['total_revenue']) ?></td>
                                    <td><strong style="color: #15803d;"><?= format_money($top['total_item_profit']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Mudaraba Foreign Investment & Partnership Report Section -->
<div class="card" style="margin-top: 24px;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h3 class="card-title" style="display: flex; align-items: center; gap: 8px; color: #047857; margin: 0;">
            <i class="fa-solid fa-handshake-angle"></i>
            صورتحساب و راپور مالی مضاربت و سرمایه‌گذاری خارجی
        </h3>
        <span class="badge" style="background: #ecfdf5; color: #047857; font-size: 12px; font-weight: 700;">
            <?= count($mudContracts) ?> شریک و پروژه مضاربت
        </span>
    </div>

    <!-- Quick stats cards for Mudaraba -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 18px; margin-top: 10px;">
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px; text-align: center;">
            <div style="font-size: 11.5px; color: #166534; font-weight: 700;">سرمایه فعال مضاربت</div>
            <div style="font-size: 18px; font-weight: 800; color: #15803d; margin-top: 2px;"><?= format_money($totalMudActiveCap) ?></div>
        </div>
        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 12px; text-align: center;">
            <div style="font-size: 11.5px; color: #1e40af; font-weight: 700;">مجموع فروش کالای مضاربت</div>
            <div style="font-size: 18px; font-weight: 800; color: #2563eb; margin-top: 2px;"><?= format_money($totalMudSales) ?></div>
        </div>
        <div style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 10px; padding: 12px; text-align: center;">
            <div style="font-size: 11.5px; color: #6b21a8; font-weight: 700;">مجموع سود حاصله</div>
            <div style="font-size: 18px; font-weight: 800; color: #7e22ce; margin-top: 2px;"><?= format_money($totalMudProfit) ?></div>
        </div>
        <div style="background: #fff7ed; border: 1px solid #fed7aa; border-radius: 10px; padding: 12px; text-align: center;">
            <div style="font-size: 11.5px; color: #9a3412; font-weight: 700;">کل سود پرداخت‌شده به شرکا</div>
            <div style="font-size: 18px; font-weight: 800; color: #ea580c; margin-top: 2px;"><?= format_money($totalMudPaidProfit) ?></div>
        </div>
        <div style="background: #fdf2f8; border: 1px solid #fbcfe8; border-radius: 10px; padding: 12px; text-align: center;">
            <div style="font-size: 11.5px; color: #9d174d; font-weight: 700;">اصل سرمایه مسترد شده</div>
            <div style="font-size: 18px; font-weight: 800; color: #db2777; margin-top: 2px;"><?= format_money($totalMudReturnedCap) ?></div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 35px;">#</th>
                    <th>نام شریک مضاربت</th>
                    <th>پروژه / کالای تجاری</th>
                    <th>سرمایه اولیه</th>
                    <th>نسبت سود</th>
                    <th>فروشات</th>
                    <th>سود کل</th>
                    <th>سهم سود شریک</th>
                    <th>سود پرداخت شده</th>
                    <th>سرمایه مسترد شده</th>
                    <th>مانده طلب شریک</th>
                    <th>وضعیت</th>
                    <th>سند بل</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($mudContracts)): ?>
                    <tr>
                        <td colspan="13" style="text-align: center; color: #64748b; padding: 16px;">
                            هیچ قرارداد مضاربتی ثبت نگردیده است.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $mIdx = 1;
                    foreach ($mudContracts as $mc): 
                        $pShare = ((float)$mc['total_profit_amount'] * (float)$mc['partner_profit_rate']) / 100;
                        $remProfit = max(0, $pShare - (float)($mc['paid_profit_to_partner'] ?? 0));
                        $remCap = max(0, (float)$mc['capital_amount'] - (float)($mc['returned_capital'] ?? 0));
                        $totalRem = $remProfit + $remCap;
                    ?>
                    <tr>
                        <td><?= to_persian_num($mIdx++) ?></td>
                        <td><strong><?= sanitize($mc['partner_name']) ?></strong></td>
                        <td><?= sanitize($mc['project_title']) ?></td>
                        <td><strong><?= format_money($mc['capital_amount'], false) ?></strong></td>
                        <td><?= (float)$mc['partner_profit_rate'] ?>٪</td>
                        <td><?= format_money($mc['total_sales_amount'], false) ?></td>
                        <td style="color: #0284c7; font-weight: 700;"><?= format_money($mc['total_profit_amount'], false) ?></td>
                        <td style="color: #7e22ce; font-weight: 700;"><?= format_money($pShare, false) ?></td>
                        <td style="color: #166534; font-weight: 700;"><?= format_money($mc['paid_profit_to_partner'], false) ?></td>
                        <td style="color: #2563eb; font-weight: 700;"><?= format_money($mc['returned_capital'], false) ?></td>
                        <td style="color: <?= $totalRem > 0 ? '#b91c1c' : '#166534' ?>; font-weight: 800;">
                            <?= $totalRem > 0 ? format_money($totalRem, false) : 'تسویه کامل' ?>
                        </td>
                        <td>
                            <?php if ($mc['status'] === 'settled'): ?>
                                <span class="badge badge-secondary">تسویه شده</span>
                            <?php else: ?>
                                <span class="badge badge-success">جاری</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>print/mudaraba.php?id=<?= $mc['id'] ?>" target="_blank" class="btn btn-sm btn-secondary" style="padding: 2px 7px; font-size: 11px;" title="چاپ سند رسمی قرارداد مضاربت">
                                <i class="fa-solid fa-print"></i> بل
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Payout Receipts Log in Period -->
    <div style="margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h4 style="font-size: 14px; font-weight: 800; color: #166534; margin: 0; display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-receipt"></i>
                تاریخچه پرداختی‌ها و اسناد تسویه با شرکای مضاربت در این بازه
            </h4>
            <span style="font-size: 12px; color: #64748b;">
                مجموع پرداختی دوره: <strong style="color: #dc2626;"><?= format_money($mudarabaPayoutInPeriod) ?></strong> (کسر شده از سرمایه دکان)
            </span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th style="width: 35px;">#</th>
                        <th>تاریخ رسید</th>
                        <th>شرح و تفصیلات پرداخت</th>
                        <th>نوعیت حسابداری</th>
                        <th>مبلغ پرداختی (کسر از سرمایه دکان)</th>
                        <th>وضعیت سند</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mudPayoutRecords)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #64748b; padding: 14px; font-size: 12.5px;">
                                در این بازه زمانی هیچ پرداختی برای شرکای مضاربت ثبت نشده است.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $rIdx = 1;
                        foreach ($mudPayoutRecords as $mRec): 
                            $rDate = !empty($mRec['transaction_date']) ? $mRec['transaction_date'] : date('Y-m-d', strtotime($mRec['created_at']));
                        ?>
                        <tr>
                            <td><?= to_persian_num($rIdx++) ?></td>
                            <td><strong><?= to_persian_num($rDate) ?></strong></td>
                            <td style="font-size: 12.5px; color: #1e293b;"><?= sanitize($mRec['source_description']) ?></td>
                            <td><span class="badge badge-danger">برداشت از سرمایه دکان</span></td>
                            <td style="color: #dc2626; font-weight: 800; font-size: 13.5px;">
                                -<?= format_money($mRec['amount']) ?>
                            </td>
                            <td>
                                <span class="badge badge-success" style="font-size: 11px;">
                                    <i class="fa-solid fa-circle-check ml-1"></i> تایید و تسلیم شده
                                </span>
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
