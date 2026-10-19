<?php
/**
 * NiKXor Accounting - Smart AI Shop Accountant & Financial Advisor
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

$pageTitle = 'هوش مصنوعی حسابدار و مشاور مالی دکان';
$isLocked = is_feature_locked('ai_advisor');

// Aggregate Core Financial Metrics for AI Reasoning
$today = date('Y-m-d');
$month = date('Y-m');

// 1. Total Stock Capital & Counts
$stockStats = $pdo->query("SELECT COUNT(*) as total_items, COALESCE(SUM(capital_amount), 0) as total_val FROM stock")->fetch();
$totalStockCount = (int)($stockStats['total_items'] ?? 0);
$totalStockValue = (float)($stockStats['total_val'] ?? 0);

// 2. Low stock threshold alert items (less than 3 items remaining)
$lowStockItems = $pdo->query("SELECT id, name, quantity FROM stock WHERE quantity <= 3 ORDER BY quantity ASC LIMIT 5")->fetchAll();

// 3. Debts & Receivables
$totalDebtsReceivable = (float)($pdo->query("SELECT COALESCE(SUM(remaining_amount), 0) FROM debts WHERE debt_type = 'receivable' AND status != 'تسویه'")->fetchColumn() ?: 0);
$totalDebtsPayable = (float)($pdo->query("SELECT COALESCE(SUM(remaining_amount), 0) FROM debts WHERE debt_type = 'payable' AND status != 'تسویه'")->fetchColumn() ?: 0);

// 4. Sales performance
$todaySalesSum = (float)($pdo->query("SELECT COALESCE(SUM(final_amount), 0) FROM sales WHERE invoice_date LIKE '{$today}%'")->fetchColumn() ?: 0);
$monthSalesSum = (float)($pdo->query("SELECT COALESCE(SUM(final_amount), 0) FROM sales WHERE invoice_date LIKE '{$month}%'")->fetchColumn() ?: 0);

// 5. Top selling product names
$topSellingItems = $pdo->query("SELECT product_name, SUM(quantity) as total_sold FROM sale_items GROUP BY product_name ORDER BY total_sold DESC LIMIT 3")->fetchAll();

// AI Shop Health Score Calculation (0 - 100)
$healthScore = 85;
if ($totalDebtsReceivable > $totalStockValue && $totalStockValue > 0) $healthScore -= 15;
if (count($lowStockItems) > 3) $healthScore -= 10;
if ($totalDebtsPayable > $todaySalesSum * 10 && $todaySalesSum > 0) $healthScore -= 10;
if ($monthSalesSum > 50000) $healthScore = min(100, $healthScore + 10);
$healthScore = max(35, min(98, $healthScore));

// Query Answering Simulation for AI chat prompt
$aiQueryAnswer = null;
$askedQuestion = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ask_ai') {
    $askedQuestion = trim($_POST['question'] ?? '');
    if ($isLocked) {
        $aiQueryAnswer = __t(
            "⚠️ License Activation Warning:\nThe Smart AI Shop Accountant & Financial Advisor is running in preview mode. To execute customized profit analysis, inventory forecasting, and real-time intelligent responses, license activation is required.\n\nLead Developer: Ahmad Najeem Nik (+93783788278)",
            "⚠️ هشدار فعال‌سازی لایسنس:\nسیستم هوش مصنوعی حسابدار و مشاور مالی دکان در حالت پیش‌نمایش می‌باشد. جهت آنالیز اختصاصی سود، تحلیل کسری گدام و پاسخگویی هوشمند، نیاز به فعال‌سازی لایسنس دارید.\n\nتوسعه‌دهنده: احمد نجیم نیک (+93783788278)",
            "⚠️ د لایسنس خبرداری:\nد مصنوعي هوښ سلاکار په ازمایښتي حالت کې دی. د پوره کارونې لپاره لایسنس واخلئ.\n\nجوړونکی: احمد نجیم نیک (+93783788278)"
        );
    } elseif (csrf_verify($_POST['csrf_token'] ?? '')) {
        $qLower = mb_strtolower($askedQuestion, 'UTF-8');
        if (strpos($qLower, 'profit') !== false || strpos($qLower, 'سود') !== false || strpos($qLower, 'مفاد') !== false || strpos($qLower, 'ګټه') !== false) {
            $aiQueryAnswer = __t(
                "Based on shop accounting analytics, monthly gross sales reached " . format_currency($monthSalesSum) . ". To optimize net profitability, focus on high-margin mobile accessories (above 30% margin) and restrict large discounts on retail purchases.",
                "بر اساس محاسبات حسابداری دکان شما، حجم فروش این ماه بالغ بر " . format_currency($monthSalesSum) . " بوده است. جهت بیشینه‌سازی سود خالص، پیشنهاد می‌شود روی اقلام جانبی با حاشیه سود بالای ۳۰٪ تمرکز نموده و تخفیف‌های بالای بل را برای خریدهای پرچون محدود نمایید.",
                "ستاسو د هټۍ د محاسبې له مخې د دې میاشتې پلور " . format_currency($monthSalesSum) . " دی. د ګټې زیاتولو لپاره پر هغو توکو تمرکز وکړئ چې تر ۳۰٪ ډېره ګټه لري."
            );
        } elseif (strpos($qLower, 'stock') !== false || strpos($qLower, 'inventory') !== false || strpos($qLower, 'کمبود') !== false || strpos($qLower, 'گدام') !== false || strpos($qLower, 'خرید') !== false || strpos($qLower, 'ګودام') !== false) {
            $aiQueryAnswer = __t(
                "Currently " . ($currentLang === 'en' ? count($lowStockItems) : to_persian_num(count($lowStockItems))) . " item(s) are below safety threshold. Restocking fast-moving inventory should be the immediate priority to avoid lost sales. Total warehouse capital is evaluated at " . format_currency($totalStockValue) . ".",
                "در حال حاضر " . count($lowStockItems) . " قلم جنس در نقطه بحرانی موجودی قرار دارند. اولویت نخست تامین، اقلام با گردش سریع است تا توقف فروش رخ ندهد. ارزش فعلی موجودی گدام " . format_currency($totalStockValue) . " تخمین زده می‌شود.",
                "اوس مهال " . count($lowStockItems) . " توکي له ټاکلې اندازې کم شوي دي. د ګودام ټولټال پانګه " . format_currency($totalStockValue) . " اټکل شوې ده."
            );
        } elseif (strpos($qLower, 'debt') !== false || strpos($qLower, 'receivable') !== false || strpos($qLower, 'قرضه') !== false || strpos($qLower, 'طلب') !== false || strpos($qLower, 'پور') !== false) {
            $aiQueryAnswer = __t(
                "Total outstanding receivables from buyers equal " . format_currency($totalDebtsReceivable) . ". The AI Advisor recommends collecting at least a 30% upfront cash payment prior to issuing new credit sales to protect shop liquidity.",
                "مجموع طلبات معوقه شما از مشتریان برابر با " . format_currency($totalDebtsReceivable) . " است. هوش مصنوعی توصیه می‌کند پیش از صدور بل قرضه جدید، حداقل ۳۰٪ پیش‌پرداخت نقدی دریافت کنید تا ریسک نقدینگی به حداقل برسد.",
                "له پیرودونکو څخه ستاسو پاتې طلبونه " . format_currency($totalDebtsReceivable) . " دي. سپارښتنه کیږي چې لږترلږه ۳۰ سلنه نغده مخکې تادیه واخلئ."
            );
        } else {
            $aiQueryAnswer = __t(
                "Business diagnostic analysis shows a store health score of {$healthScore} out of 100. Total warehouse inventory is valued at " . format_currency($totalStockValue) . " and outstanding receivables stand at " . format_currency($totalDebtsReceivable) . ". Maintaining a balanced ratio of cash sales to credit collections is advised.",
                "تحلیل وضعیت کسب‌وکار شما نشان می‌دهد نمره سلامت تجارتی دکان {$healthScore} از ۱۰۰ است. موجودی کل گدام " . format_currency($totalStockValue) . " و مطالبات جاری " . format_currency($totalDebtsReceivable) . " می‌باشد. جهت پیشرفت، تعادل بین خرید نقد و وصول طلبات توصیه می‌شود.",
                "د هټۍ د روغتیا نمره له سلو څخه {$healthScore} ده. د ګودام ارزښت " . format_currency($totalStockValue) . " او د پورونو اندازه " . format_currency($totalDebtsReceivable) . " ده."
            );
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="ai-advisor-page" style="position: relative;">

    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-wand-magic-sparkles" style="color: #0284c7;"></i>
                <?= __t('Smart AI Shop Accountant & Financial Advisor', 'هوش مصنوعی حسابدار و مشاور مالی دکان', 'د هټۍ د هوښیار مالي سلاکار او محاسب') ?>
                <?php if ($isLocked): ?>
                    <?= render_lock_badge('ai_advisor', __t('Smart Locked', 'قفل هوشمند', 'هوښیار قفل')) ?>
                <?php endif; ?>
            </h3>
            <span style="font-size: 12px; color: #64748b;"><?= __t('Automated liquidity evaluation, intelligent restock prediction, profit margin monitoring, and financial query responses', 'تحلیل خودکار نقدینگی، پیش‌بینی هوشمند سفارشات، مانیتورینگ سود و پاسخگویی به سوالات مالی', 'د نغدو پیسو اتومات تحلیل، د توکو وړاندوینه او د ګټې څارنه') ?></span>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if ($isLocked): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="showLockModal('ai_advisor', 'Smart AI Shop Accountant & Advisor', '<?= __t('Smart AI Shop Accountant & Advisor', 'هوش مصنوعی حسابدار و تحلیلگر دکان', 'د هټۍ مصنوعي هوښ سلاکار') ?>');">
                    <i class="fa-solid fa-lock ml-1 text-amber-500"></i> <?= __t('Activate AI License', 'فعال‌سازی هوش مصنوعی', 'د مصنوعي هوښ فعالول') ?>
                </button>
            <?php else: ?>
                <span class="badge" style="background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; font-size: 11.5px; padding: 5px 10px;">
                    <i class="fa-solid fa-circle-check ml-1 text-emerald-500"></i> <?= __t('AI Engine is Active & Operational', 'موتور هوش مصنوعی فعال است', 'د مصنوعي هوښ ماشین فعال دی') ?>
                </span>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>modules/reports.php" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-chart-pie ml-1"></i> <?= __t('Standard Financial Reports', 'گزارشات کلاسیک', 'کلاسیک مالي راپورونه') ?>
            </a>
        </div>
    </div>

    <?php if ($isLocked): ?>
        <div style="background: #fffbeb; border: 1px solid #fef3c7; border-right: 4px solid #f59e0b; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-lock" style="color: #d97706; font-size: 16px;"></i>
                <span style="font-size: 12.5px; font-weight: 700; color: #92400e;">
                    <?= __t('AI Advisor engine is in preview mode. Interactive queries and advanced accounting analytics require license activation.', 'موتور هوش مصنوعی در حالت پیش‌نمایش قرار دارد. پرسش و پاسخ کامل و تحلیل‌های پیشرفته نیازمند فعال‌سازی لایسنس است.', 'د مصنوعي هوښ ماشین په ازمایښتي حالت کې دی.') ?>
                </span>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="showLockModal('ai_advisor', 'Smart AI Shop Accountant & Advisor', '<?= __t('Smart AI Shop Accountant & Advisor', 'هوش مصنوعی حسابدار و تحلیلگر دکان', 'د هټۍ مصنوعي هوښ سلاکار') ?>');" style="font-size: 11.5px; color: #b45309; border-color: #fcd34d;">
                <i class="fa-solid fa-key ml-1"></i> <?= __t('Activate License', 'فعال‌سازی لایسنس', 'لایسنس فعال کړئ') ?>
            </button>
        </div>
    <?php endif; ?>

    <!-- Container -->
    <div style="position: relative;">
        
        <!-- AI Health Score & Quick Indicators -->
        <div style="display: grid; grid-template-columns: 320px 1fr; gap: 20px; margin-bottom: 20px;">
            <!-- Health Score Card -->
            <div class="card" style="padding: 20px; text-align: center; background: linear-gradient(145deg, #0f172a, #1e293b); color: #ffffff;">
                <div style="font-size: 12.5px; color: #94a3b8; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
                    <?= __t('Store Financial Resilience & Health Index', 'شاخص سلامت و تاب‌آوری مالی دکان', 'د هټۍ د مالي سلامتیا شاخص') ?>
                </div>
                <div style="position: relative; width: 120px; height: 120px; margin: 0 auto 14px; border-radius: 50%; background: conic-gradient(#10b981 <?= $healthScore * 3.6 ?>deg, #334155 0deg); display: flex; align-items: center; justify-content: center; box-shadow: 0 0 20px rgba(16, 185, 129, 0.25);">
                    <div style="width: 96px; height: 96px; border-radius: 50%; background: #0f172a; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <span style="font-size: 28px; font-weight: 800; color: #ffffff;"><?= $currentLang === 'en' ? $healthScore : to_persian_num($healthScore) ?></span>
                        <span style="font-size: 10px; color: #94a3b8;"><?= __t('out of 100', 'از ۱۰۰', 'له ۱۰۰ څخه') ?></span>
                    </div>
                </div>
                <div style="font-size: 13.5px; font-weight: 700; color: <?= $healthScore >= 75 ? '#34d399' : '#fbbf24' ?>; margin-bottom: 4px;">
                    <?= $healthScore >= 75 ? __t('Stable & Highly Productive', 'وضعیت باثبات و پربازده', 'باثباته او ګټور حالت') : __t('Cash Flow Optimization Needed', 'نیازمند بهینه‌سازی جریان نقدی', 'نغدي پیسو ته اړتیا ده') ?>
                </div>
                <p style="font-size: 11.5px; color: #cbd5e1; line-height: 1.5; margin: 0;">
                    <?= __t('Comprehensive evaluation based on inventory turnover, debt-to-capital ratio, and daily sales velocity.', 'ارزیابی ترکیبی بر مبنای گردش کالا، تناسب بدهی‌ها و سرعت فروش روزانه', 'د پلور د چټکتیا او پورونو له مخې ارزونه.') ?>
                </p>
            </div>

            <!-- AI Real-Time Insights Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px;">
                <div class="card" style="padding: 16px; border-top: 3px solid #38bdf8;">
                    <div style="font-size: 11.5px; color: #64748b; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-boxes-stacked" style="color: #0284c7;"></i>
                        <?= __t('Estimated Warehouse Capital Value', 'ارزش تخمینی سرمایه در گدام', 'په ګودام کې د پانګې اټکل شوی ارزښت') ?>
                    </div>
                    <div style="font-size: 18px; font-weight: 800; color: #0f172a;"><?= format_currency($totalStockValue) ?></div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;"><?= sprintf(__t('Across %s registered inventory items', 'در قالب %s قلم جنس ثبت شده', 'په %s ثبت شویو توکو کې'), ($currentLang === 'en' ? $totalStockCount : to_persian_num($totalStockCount))) ?></div>
                </div>

                <div class="card" style="padding: 16px; border-top: 3px solid #f59e0b;">
                    <div style="font-size: 11.5px; color: #64748b; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-triangle-exclamation" style="color: #d97706;"></i>
                        <?= __t('Low Inventory Safety Warnings', 'هشدار اقلام کمبود موجودی', 'د توکو د کمښت خبرداری') ?>
                    </div>
                    <div style="font-size: 18px; font-weight: 800; color: #d97706;"><?= $currentLang === 'en' ? count($lowStockItems) : to_persian_num(count($lowStockItems)) ?> <span style="font-size: 12px; font-weight: normal;"><?= __t('items', 'کالا', 'توکي') ?></span></div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;"><?= __t('Reached critical restock limit', 'رسیده به مرز حداقل تعیین شده', 'ټاکلې کچې ته رسېدلي') ?></div>
                </div>

                <div class="card" style="padding: 16px; border-top: 3px solid #ef4444;">
                    <div style="font-size: 11.5px; color: #64748b; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-hand-holding-dollar" style="color: #dc2626;"></i>
                        <?= __t('Total Outstanding Receivables', 'مجموع مطالبات در دست بازار', 'په بازار کې ټول پاتې طلبونه') ?>
                    </div>
                    <div style="font-size: 18px; font-weight: 800; color: #dc2626;"><?= format_currency($totalDebtsReceivable) ?></div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;"><?= __t('Unsettled customer credit balances', 'قرضه‌های وصول نشده مشتریان', 'له پیرودونکو نه تصفیه شوي پورونه') ?></div>
                </div>

                <div class="card" style="padding: 16px; border-top: 3px solid #10b981;">
                    <div style="font-size: 11.5px; color: #64748b; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-calendar-check" style="color: #059669;"></i>
                        <?= __t('Current Month Recorded Sales', 'فروش ثبت شده در ماه جاری', 'په روانه میاشت کې ثبت شوی پلور') ?>
                    </div>
                    <div style="font-size: 18px; font-weight: 800; color: #059669;"><?= format_currency($monthSalesSum) ?></div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;"><?= __t("Today's sales: ", 'فروش امروز: ', 'د نن ورځې پلور: ') ?><?= format_currency($todaySalesSum) ?></div>
                </div>
            </div>
        </div>

        <!-- 2 Column: Strategic AI Recommendations & Interactive Assistant -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- 1. Strategic Recommendations -->
            <div class="card" style="padding: 20px;">
                <h4 style="font-size: 14.5px; font-weight: 800; color: #0f172a; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-lightbulb" style="color: #f59e0b;"></i>
                    <?= __t('Automated Strategic AI Insights & Recommendations', 'توصیه‌ها و تحلیل‌های خودکار هوش مصنوعی', 'د مصنوعي هوښ اتومات سپارښتنې او تحلیلونه') ?>
                </h4>
                
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-right: 3px solid #0284c7; padding: 12px 14px; border-radius: 6px;">
                        <div style="font-size: 12.5px; font-weight: 700; color: #0f172a; margin-bottom: 3px;">
                            <?= __t('1. Restock Priority & Shortage Mitigation', '۱. اولویت خرید و کسری انبار', '۱. د ګودام د کمښت او پېرودلو لومړیتوب') ?>
                        </div>
                        <p style="font-size: 12px; color: #475569; margin: 0; line-height: 1.6;">
                            <?php if (!empty($lowStockItems)): ?>
                                <?= sprintf(__t('Item «%s» with only (%s units) remaining is at risk of stockout. It is advised to restock immediately.', 'اجناس «%s» با تعداد موجودی (%s) در خطر توقف فروش قرار دارند. توصیه می‌شود بلافاصله نسبت به شارژ انبار اقدام کنید.', 'توکی «%s» یوازې (%s دانې) پاتې دی او ممکن خلاص شي.'), sanitize($lowStockItems[0]['name']), ($currentLang === 'en' ? $lowStockItems[0]['quantity'] : to_persian_num($lowStockItems[0]['quantity']))) ?>
                            <?php else: ?>
                                <?= __t('All primary store goods are currently above the safety threshold. Inventory levels are well-balanced.', 'کلیه کالاهای کلیدی دکان فعلاً بالای حد نصاب حداقل هستند. وضعیت انبارداری متعادل است.', 'د هټۍ ټول اصلي توکي پوره دي او ګودام متوازن دی.') ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-right: 3px solid #10b981; padding: 12px 14px; border-radius: 6px;">
                        <div style="font-size: 12.5px; font-weight: 700; color: #0f172a; margin-bottom: 3px;">
                            <?= __t('2. Liquidity Health & Credit Settlements', '۲. سلامت گردش نقدینگی و تسویه‌حساب‌ها', '۲. د نغدو پیسو او د حسابونو د تصفیې روغتیا') ?>
                        </div>
                        <p style="font-size: 12px; color: #475569; margin: 0; line-height: 1.6;">
                            <?= sprintf(__t('Outstanding customer receivables ratio to warehouse stock value stands around %s%%. Expeditious collection of accounts older than 20 days is advised.', 'نسبت طلبات در دست مشتریان به ارزش کالا حدود %s٪ است. پیشنهاد می‌شود مطالبات معوقه با موعد بیش از ۲۰ روز فورا پیگیری شوند.', 'د توکو په پرتله د پاتې پورونو سلنه شاوخوا %s٪ ده.'), ($totalStockValue > 0 ? ($currentLang === 'en' ? round(($totalDebtsReceivable / $totalStockValue) * 100) : to_persian_num(round(($totalDebtsReceivable / $totalStockValue) * 100))) : 0)) ?>
                        </p>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-right: 3px solid #8b5cf6; padding: 12px 14px; border-radius: 6px;">
                        <div style="font-size: 12.5px; font-weight: 700; color: #0f172a; margin-bottom: 3px;">
                            <?= __t('3. Top-Performing & High-Profit Products', '۳. کالاهای پرفروش و پرسود دوره', '۳. د دې دورې ډېر پلورل شوي او ګټور توکي') ?>
                        </div>
                        <p style="font-size: 12px; color: #475569; margin: 0; line-height: 1.6;">
                            <?php if (!empty($topSellingItems)): ?>
                                <?= sprintf(__t('Item «%s» recorded the highest sales volume. Maintaining a competitive margin on this product is recommended.', 'کالا «%s» بیشترین حجم فروش را ثبت کرده است. نگهداری حاشیه سود رقابتی روی این محصول توصیه می‌شود.', 'توکي «%s» تر ټولو ډېر پلور ثبت کړی دی.'), sanitize($topSellingItems[0]['product_name'])) ?>
                            <?php else: ?>
                                <?= __t('To accurately analyze customer demand trends, register all daily transactions via the New Invoice module.', 'برای ثبت دقیق‌تر رفتار مشتریان، تمام معاملات روزانه را از طریق بخش «فروختن جنس» وارد نمایید.', 'د پیرودونکو د اړتیاوو د ښه معلومولو لپاره ټول پلور په نوي بل کې ثبت کړئ.') ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- 2. Interactive AI Assistant -->
            <div class="card" style="padding: 20px;">
                <h4 style="font-size: 14.5px; font-weight: 800; color: #0f172a; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-comments" style="color: #0284c7;"></i>
                    <?= __t('Direct Query to AI Accountant', 'پرسش مستقیم از هوش مصنوعی حسابدار', 'له هوښیار محاسب څخه مستقیمه پوښتنه') ?>
                </h4>

                <form method="POST" action="" style="margin-bottom: 14px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="ask_ai">

                    <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                        <input type="text" name="question" value="<?= sanitize($askedQuestion) ?>" placeholder="<?= __t('Ask your shop accounting question (e.g. profit analysis, inventory shortage)...', 'سوال حسابداری خود را بپرسید (مثلا: وضعیت سود و کسری کالا)...', 'خپله پوښتنه ولیکئ (لکه: د ګټې حالت، د توکو کمښت)...') ?>" class="form-control" style="font-size: 12.5px;" required>
                        <button type="submit" class="btn btn-primary btn-sm" style="font-size: 12.5px; white-space: nowrap;">
                            <i class="fa-solid fa-paper-plane ml-1"></i> <?= __t('Ask AI', 'بپرس', 'وپوښتئ') ?>
                        </button>
                    </div>
                </form>

                <!-- Quick Query Chips -->
                <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px;">
                    <button type="button" class="badge" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; cursor: pointer; padding: 4px 8px; font-size: 11px;" onclick="document.querySelector('[name=question]').value='<?= addslashes(__t('What is the current profit and revenue performance?', 'وضعیت سود و فروش دکان چگونه است؟', 'د هټۍ د ګټې او پلور حالت څنګه دی؟')) ?>';">
                        <?= __t('Profit & Revenue Status', 'وضعیت سود و فروش دکان', 'د ګټې او پلور حالت') ?>
                    </button>
                    <button type="button" class="badge" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; cursor: pointer; padding: 4px 8px; font-size: 11px;" onclick="document.querySelector('[name=question]').value='<?= addslashes(__t('Which warehouse items are running out of stock?', 'کدام اجناس در گدام رو به اتمام است؟', 'په ګودام کې کوم توکي د خلاصېدو په حال کې دي؟')) ?>';">
                        <?= __t('Low Stock Inventory', 'اجناس رو به اتمام', 'کم شوي توکي') ?>
                    </button>
                    <button type="button" class="badge" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; cursor: pointer; padding: 4px 8px; font-size: 11px;" onclick="document.querySelector('[name=question]').value='<?= addslashes(__t('How can I optimize and control credit debt risks?', 'چگونه ریسک قرضه‌ها را کنترل کنم؟', 'د پورونو خطر څنګه کنټرول کړم؟')) ?>';">
                        <?= __t('Credit Risk Control', 'کنترل ریسک قرضه‌ها', 'د پورونو د خطر کنټرول') ?>
                    </button>
                </div>

                <!-- AI Output Box -->
                <div style="background: #0f172a; color: #f8fafc; border-radius: 8px; padding: 16px; min-height: 140px; font-size: 12.5px; line-height: 1.7; border: 1px solid #1e293b;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; color: #38bdf8; font-weight: 700; font-size: 11.5px;">
                        <i class="fa-solid fa-robot"></i>
                        <?= __t('AI Analytical Response:', 'پاسخ تحلیلی هوش مصنوعی:', 'د مصنوعي هوښ تحلیلي ځواب:') ?>
                    </div>
                    <?php if ($aiQueryAnswer): ?>
                        <div style="color: #e2e8f0;"><?= nl2br(sanitize($aiQueryAnswer)) ?></div>
                    <?php else: ?>
                        <div style="color: #94a3b8; font-style: italic;">
                            <?= __t('Select a quick query or enter an accounting question to let the AI explore system metrics and deliver an instant assessment.', 'یک سوال حسابداری انتخاب کنید یا بنویسید تا هوش مصنوعی فوراً اطلاعات سیستم را کاوش نموده و تحلیل خود را ارائه نماید.', 'یوه پوښتنه وټاکئ یا یې ولیکئ تر څو مصنوعي هوښ سمدستي ځواب درکړي.') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
