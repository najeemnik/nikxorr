<?php
/**
 * NiKXor - Smart AI Shop Accountant & Financial Advisor (هوش مصنوعی حسابدار دکان)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();

$pageTitle = 'هوش مصنوعی حسابدار و مشاور مالی دکان';
$isLocked = is_feature_locked('ai_advisor');

// Fetch real business metrics for live intelligence analysis
$totalStockCount = 0;
$totalStockValue = 0;
$lowStockItems = [];
$todaySalesSum = 0;
$monthSalesSum = 0;
$totalDebtsReceivable = 0;
$totalDebtsPayable = 0;
$totalExpensesSum = 0;
$topSellingItems = [];

try {
    // 1. Stock Metrics
    $sStmt = $pdo->query("SELECT COUNT(*) as total_items, SUM(quantity * purchase_price) as total_val FROM stock");
    $sRow = $sStmt->fetch();
    $totalStockCount = (int)($sRow['total_items'] ?? 0);
    $totalStockValue = (float)($sRow['total_val'] ?? 0);

    // Low stock items
    $lowThresh = (int)get_setting('low_stock_threshold', 3);
    try {
        $lsStmt = $pdo->prepare("SELECT name, quantity, COALESCE(min_quantity, :th) as min_quantity, purchase_price, sale_price FROM stock WHERE quantity <= COALESCE(min_quantity, :th) ORDER BY quantity ASC LIMIT 5");
        $lsStmt->execute([':th' => $lowThresh]);
        $lowStockItems = $lsStmt->fetchAll();
    } catch (Exception $e) {
        $lsStmt = $pdo->prepare("SELECT name, quantity, purchase_price, sale_price FROM stock WHERE quantity <= :th ORDER BY quantity ASC LIMIT 5");
        $lsStmt->execute([':th' => $lowThresh]);
        $lowStockItems = $lsStmt->fetchAll();
    }

    // 2. Sales Metrics
    $today = date('Y-m-d');
    $firstDayMonth = date('Y-m-01');

    $tsStmt = $pdo->prepare("SELECT SUM(total_amount) as s_sum FROM sales WHERE DATE(created_at) = :td");
    $tsStmt->execute([':td' => $today]);
    $todaySalesSum = (float)($tsStmt->fetch()['s_sum'] ?? 0);

    $msStmt = $pdo->prepare("SELECT SUM(total_amount) as m_sum FROM sales WHERE DATE(created_at) >= :fm");
    $msStmt->execute([':fm' => $firstDayMonth]);
    $monthSalesSum = (float)($msStmt->fetch()['m_sum'] ?? 0);

    // 3. Debts
    $drStmt = $pdo->query("SELECT SUM(remaining_amount) as r_sum FROM debts WHERE debt_type = 'receivable' AND status != 'پرداخت شده'");
    $totalDebtsReceivable = (float)($drStmt->fetch()['r_sum'] ?? 0);

    $dpStmt = $pdo->query("SELECT SUM(remaining_amount) as p_sum FROM debts WHERE debt_type = 'payable' AND status != 'پرداخت شده'");
    $totalDebtsPayable = (float)($dpStmt->fetch()['p_sum'] ?? 0);

    // 4. Expenses
    $expStmt = $pdo->query("SELECT SUM(amount) as e_sum FROM expenses WHERE expense_type = 'مصارف'");
    $totalExpensesSum = (float)($expStmt->fetch()['e_sum'] ?? 0);

    // 5. Top Selling
    $topStmt = $pdo->query("SELECT product_name, SUM(quantity) as total_sold, SUM(total_price) as total_revenue 
                            FROM sale_items 
                            GROUP BY product_name 
                            ORDER BY total_sold DESC 
                            LIMIT 5");
    $topSellingItems = $topStmt->fetchAll();

} catch (Exception $e) {}

// Calculate Smart Health Score (0 - 100)
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
        $aiQueryAnswer = "⚠️ هشدار فعال‌سازی لایسنس:\nسیستم هوش مصنوعی حسابدار و مشاور مالی دکان در حالت پیش‌نمایش می‌باشد. جهت آنالیز اختصاصی سود، تحلیل کسری گدام و پاسخگویی هوشمند، نیاز به فعال‌سازی لایسنس دارید.\n\nتوسعه‌دهنده: احمد نجیم نیک (+93783788278)";
    } elseif (csrf_verify($_POST['csrf_token'] ?? '')) {
        if (strpos($askedQuestion, 'سود') !== false || strpos($askedQuestion, 'مفاد') !== false) {
            $aiQueryAnswer = "بر اساس محاسبات حسابداری دکان شما، حجم فروش این ماه بالغ بر " . format_currency($monthSalesSum) . " بوده است. جهت بیشینه‌سازی سود خالص، پیشنهاد می‌شود روی اقلام جانبی با حاشیه سود بالای ۳۰٪ تمرکز نموده و تخفیف‌های بالای بل را برای خریدهای پرچون محدود نمایید.";
        } elseif (strpos($askedQuestion, 'کمبود') !== false || strpos($askedQuestion, 'گدام') !== false || strpos($askedQuestion, 'خرید') !== false) {
            $aiQueryAnswer = "در حال حاضر " . count($lowStockItems) . " قلم جنس در نقطه بحرانی موجودی قرار دارند. اولویت نخست تامین، اقلام با گردش سریع است تا توقف فروش رخ ندهد. ارزش فعلی موجودی گدام " . format_currency($totalStockValue) . " تخمین زده می‌شود.";
        } elseif (strpos($askedQuestion, 'قرضه') !== false || strpos($askedQuestion, 'طلب') !== false) {
            $aiQueryAnswer = "مجموع طلبات معوقه شما از مشتریان برابر با " . format_currency($totalDebtsReceivable) . " است. هوش مصنوعی توصیه می‌کند پیش از صدور بل قرضه جدید، حداقل ۳۰٪ پیش‌پرداخت نقدی دریافت کنید تا ریسک نقدینگی به حداقل برسد.";
        } else {
            $aiQueryAnswer = "تحلیل وضعیت کسب‌وکار شما نشان می‌دهد نمره سلامت تجارتی دکان {$healthScore} از ۱۰۰ است. موجودی کل گدام " . format_currency($totalStockValue) . " و مطالبات جاری " . format_currency($totalDebtsReceivable) . " می‌باشد. جهت پیشرفت، تعادل بین خرید نقد و وصول طلبات توصیه می‌شود.";
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
                هوش مصنوعی حسابدار و مشاور مالی دکان (Smart AI Advisor)
                <?php if ($isLocked): ?>
                    <?= render_lock_badge('ai_advisor', 'قفل هوشمند') ?>
                <?php endif; ?>
            </h3>
            <span style="font-size: 12px; color: #64748b;">تحلیل خودکار نقدینگی، پیش‌بینی هوشمند سفارشات، مانیتورینگ سود و پاسخگویی به سوالات مالی</span>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if ($isLocked): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="showLockModal('ai_advisor', 'Smart AI Shop Accountant & Advisor', 'هوش مصنوعی حسابدار و تحلیلگر دکان');">
                    <i class="fa-solid fa-lock ml-1 text-amber-500"></i> فعال‌سازی هوش مصنوعی
                </button>
            <?php else: ?>
                <span class="badge" style="background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; font-size: 11.5px; padding: 5px 10px;">
                    <i class="fa-solid fa-circle-check ml-1 text-emerald-500"></i> موتور هوش مصنوعی فعال است
                </span>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>modules/reports.php" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-chart-pie ml-1"></i> گزارشات کلاسیک
            </a>
        </div>
    </div>

    <?php if ($isLocked): ?>
        <div style="background: #fffbeb; border: 1px solid #fef3c7; border-right: 4px solid #f59e0b; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-lock" style="color: #d97706; font-size: 16px;"></i>
                <span style="font-size: 12.5px; font-weight: 700; color: #92400e;">
                    موتور هوش مصنوعی در حالت پیش‌نمایش قرار دارد. پرسش و پاسخ کامل و تحلیل‌های پیشرفته نیازمند فعال‌سازی لایسنس است.
                </span>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="showLockModal('ai_advisor', 'Smart AI Shop Accountant & Advisor', 'هوش مصنوعی حسابدار و تحلیلگر دکان');" style="font-size: 11.5px; color: #b45309; border-color: #fcd34d;">
                <i class="fa-solid fa-key ml-1"></i> فعال‌سازی لایسنس
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
                    شاخص سلامت و تاب‌آوری مالی دکان
                </div>
                <div style="position: relative; width: 120px; height: 120px; margin: 0 auto 14px; border-radius: 50%; background: conic-gradient(#10b981 <?= $healthScore * 3.6 ?>deg, #334155 0deg); display: flex; align-items: center; justify-content: center; box-shadow: 0 0 20px rgba(16, 185, 129, 0.25);">
                    <div style="width: 96px; height: 96px; border-radius: 50%; background: #0f172a; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <span style="font-size: 28px; font-weight: 800; color: #ffffff;"><?= to_persian_num($healthScore) ?></span>
                        <span style="font-size: 10px; color: #94a3b8;">از ۱۰۰</span>
                    </div>
                </div>
                <div style="font-size: 13.5px; font-weight: 700; color: <?= $healthScore >= 75 ? '#34d399' : '#fbbf24' ?>; margin-bottom: 4px;">
                    <?= $healthScore >= 75 ? 'وضعیت باثبات و پربازده' : 'نیازمند بهینه‌سازی جریان نقدی' ?>
                </div>
                <p style="font-size: 11.5px; color: #cbd5e1; line-height: 1.5; margin: 0;">
                    ارزیابی ترکیبی بر مبنای گردش کالا، تناسب بدهی‌ها و سرعت فروش روزانه
                </p>
            </div>

            <!-- AI Real-Time Insights Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px;">
                <div class="card" style="padding: 16px; border-top: 3px solid #38bdf8;">
                    <div style="font-size: 11.5px; color: #64748b; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-boxes-stacked" style="color: #0284c7;"></i>
                        ارزش تخمینی سرمایه در گدام
                    </div>
                    <div style="font-size: 18px; font-weight: 800; color: #0f172a;"><?= format_currency($totalStockValue) ?></div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">در قالب <?= to_persian_num($totalStockCount) ?> قلم جنس ثبت شده</div>
                </div>

                <div class="card" style="padding: 16px; border-top: 3px solid #f59e0b;">
                    <div style="font-size: 11.5px; color: #64748b; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-triangle-exclamation" style="color: #d97706;"></i>
                        هشدار اقلام کمبود موجودی
                    </div>
                    <div style="font-size: 18px; font-weight: 800; color: #d97706;"><?= to_persian_num(count($lowStockItems)) ?> <span style="font-size: 12px; font-weight: normal;">کالا</span></div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">رسیده به مرز حداقل تعیین شده</div>
                </div>

                <div class="card" style="padding: 16px; border-top: 3px solid #ef4444;">
                    <div style="font-size: 11.5px; color: #64748b; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-hand-holding-dollar" style="color: #dc2626;"></i>
                        مجموع مطالبات در دست بازار
                    </div>
                    <div style="font-size: 18px; font-weight: 800; color: #dc2626;"><?= format_currency($totalDebtsReceivable) ?></div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">قرضه‌های وصول نشده مشتریان</div>
                </div>

                <div class="card" style="padding: 16px; border-top: 3px solid #10b981;">
                    <div style="font-size: 11.5px; color: #64748b; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-calendar-check" style="color: #059669;"></i>
                        فروش ثبت شده در ماه جاری
                    </div>
                    <div style="font-size: 18px; font-weight: 800; color: #059669;"><?= format_currency($monthSalesSum) ?></div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">فروش امروز: <?= format_currency($todaySalesSum) ?></div>
                </div>
            </div>
        </div>

        <!-- 2 Column: Strategic AI Recommendations & Interactive Assistant -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- 1. Strategic Recommendations -->
            <div class="card" style="padding: 20px;">
                <h4 style="font-size: 14.5px; font-weight: 800; color: #0f172a; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-lightbulb" style="color: #f59e0b;"></i>
                    توصیه‌ها و تحلیل‌های خودکار هوش مصنوعی
                </h4>
                
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-right: 3px solid #0284c7; padding: 12px 14px; border-radius: 6px;">
                        <div style="font-size: 12.5px; font-weight: 700; color: #0f172a; margin-bottom: 3px;">
                            ۱. اولویت خرید و کسری انبار
                        </div>
                        <p style="font-size: 12px; color: #475569; margin: 0; line-height: 1.6;">
                            <?php if (!empty($lowStockItems)): ?>
                                اجناس «<?= sanitize($lowStockItems[0]['name']) ?>» با تعداد موجودی (<?= to_persian_num($lowStockItems[0]['quantity']) ?>) در خطر توقف فروش قرار دارند. توصیه می‌شود بلافاصله نسبت به شارژ انبار اقدام کنید.
                            <?php else: ?>
                                کلیه کالاهای کلیدی دکان فعلاً بالای حد نصاب حداقل هستند. وضعیت انبارداری متعادل است.
                            <?php endif; ?>
                        </p>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-right: 3px solid #10b981; padding: 12px 14px; border-radius: 6px;">
                        <div style="font-size: 12.5px; font-weight: 700; color: #0f172a; margin-bottom: 3px;">
                            ۲. سلامت گردش نقدینگی و تسویه‌حساب‌ها
                        </div>
                        <p style="font-size: 12px; color: #475569; margin: 0; line-height: 1.6;">
                            نسبت طلبات در دست مشتریان به ارزش کالا حدود <?= $totalStockValue > 0 ? to_persian_num(round(($totalDebtsReceivable / $totalStockValue) * 100)) : 0 ?>٪ است. پیشنهاد می‌شود مطالبات معوقه با موعد بیش از ۲۰ روز فورا پیگیری شوند.
                        </p>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-right: 3px solid #8b5cf6; padding: 12px 14px; border-radius: 6px;">
                        <div style="font-size: 12.5px; font-weight: 700; color: #0f172a; margin-bottom: 3px;">
                            ۳. کالاهای پرفروش و پرسود دوره
                        </div>
                        <p style="font-size: 12px; color: #475569; margin: 0; line-height: 1.6;">
                            <?php if (!empty($topSellingItems)): ?>
                                کالا «<?= sanitize($topSellingItems[0]['product_name']) ?>» بیشترین حجم فروش را ثبت کرده است. نگهداری حاشیه سود رقابتی روی این محصول توصیه می‌شود.
                            <?php else: ?>
                                برای ثبت دقیق‌تر رفتار مشتریان، تمام معاملات روزانه را از طریق بخش «فروختن جنس» وارد نمایید.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- 2. Interactive AI Assistant -->
            <div class="card" style="padding: 20px;">
                <h4 style="font-size: 14.5px; font-weight: 800; color: #0f172a; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-comments" style="color: #0284c7;"></i>
                    پرسش مستقیم از هوش مصنوعی حسابدار
                </h4>

                <form method="POST" action="" style="margin-bottom: 14px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="ask_ai">

                    <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                        <input type="text" name="question" value="<?= sanitize($askedQuestion) ?>" placeholder="سوال حسابداری خود را بپرسید (مثلا: وضعیت سود و کسری کالا)..." class="form-control" style="font-size: 12.5px;" required>
                        <button type="submit" class="btn btn-primary btn-sm" style="font-size: 12.5px; white-space: nowrap;">
                            <i class="fa-solid fa-paper-plane ml-1"></i> بپرس
                        </button>
                    </div>
                </form>

                <!-- Quick Query Chips -->
                <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px;">
                    <button type="button" class="badge" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; cursor: pointer; padding: 4px 8px; font-size: 11px;" onclick="document.querySelector('[name=question]').value='وضعیت سود و فروش دکان چگونه است؟';">
                        وضعیت سود و فروش دکان
                    </button>
                    <button type="button" class="badge" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; cursor: pointer; padding: 4px 8px; font-size: 11px;" onclick="document.querySelector('[name=question]').value='کدام اجناس در گدام رو به اتمام است؟';">
                        اجناس رو به اتمام
                    </button>
                    <button type="button" class="badge" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; cursor: pointer; padding: 4px 8px; font-size: 11px;" onclick="document.querySelector('[name=question]').value='چگونه ریسک قرضه‌ها را کنترل کنم؟';">
                        کنترل ریسک قرضه‌ها
                    </button>
                </div>

                <!-- AI Output Box -->
                <div style="background: #0f172a; color: #f8fafc; border-radius: 8px; padding: 16px; min-height: 140px; font-size: 12.5px; line-height: 1.7; border: 1px solid #1e293b;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; color: #38bdf8; font-weight: 700; font-size: 11.5px;">
                        <i class="fa-solid fa-robot"></i>
                        پاسخ تحلیلی هوش مصنوعی:
                    </div>
                    <?php if ($aiQueryAnswer): ?>
                        <div style="color: #e2e8f0;"><?= nl2br(sanitize($aiQueryAnswer)) ?></div>
                    <?php else: ?>
                        <div style="color: #94a3b8; font-style: italic;">
                            یک سوال حسابداری انتخاب کنید یا بنویسید تا هوش مصنوعی فوراً اطلاعات سیستم را کاوش نموده و تحلیل خود را ارائه نماید.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
