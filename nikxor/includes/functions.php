<?php
/**
 * NiKXor - Mobile Shop Management System
 * Core Utility & Business Logic Functions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

/**
 * Normalizes Persian (۰-۹) and Arabic (٠-٩) digits to standard ASCII English digits (0-9).
 * Crucial for Iranian and Afghan keyboard inputs.
 *
 * @param mixed $input
 * @return string
 */
function normalize_numbers($input) {
    if ($input === null || $input === '') {
        return '';
    }
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    $output = str_replace($persian, $english, (string)$input);
    $output = str_replace($arabic, $english, $output);
    return trim($output);
}

/**
 * Alias for normalize_numbers
 *
 * @param mixed $input
 * @return string
 */
function normalize_digits($input) {
    return normalize_numbers($input);
}

/**
 * Converts ASCII English digits (0-9) to Persian/Dari digits (۰-۹).
 *
 * @param mixed $input
 * @return string
 */
function to_persian_num($input) {
    if ($input === null || $input === '') {
        return '';
    }
    $lang = function_exists('get_current_lang') ? get_current_lang() : 'en';
    if ($lang === 'en') {
        return (string)$input;
    }
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return str_replace($english, $persian, (string)$input);
}

/**
 * Cleans and converts numeric input to float or integer after normalizing Persian/Arabic digits.
 *
 * @param mixed $input
 * @param bool $isFloat
 * @return int|float
 */
function clean_number($input, $isFloat = false) {
    $normalized = normalize_numbers($input);
    // Remove commas, spaces, or currency words
    $cleaned = preg_replace('/[^\d.-]/', '', $normalized);
    if ($cleaned === '' || !is_numeric($cleaned)) {
        return 0;
    }
    return $isFloat ? (float)$cleaned : (int)$cleaned;
}

/**
 * Formats a money amount with thousands separator and optional currency.
 *
 * @param float|int $amount
 * @param bool $showCurrency
 * @return string
 */
function format_money($amount, $showCurrency = true) {
    $val = (float)$amount;
    $formatted = number_format($val, 0, '.', ',');
    $lang = function_exists('get_current_lang') ? get_current_lang() : 'en';
    if ($lang !== 'en') {
        $formatted = to_persian_num($formatted);
    }
    if ($showCurrency) {
        $curr = function_exists('get_currency_label') ? get_currency_label() : (defined('CURRENCY_NAME') ? CURRENCY_NAME : 'AFN');
        return $formatted . ' ' . $curr;
    }
    return $formatted;
}

/**
 * Format currency alias
 */
function format_currency($amount, $showCurrency = true) {
    return format_money($amount, $showCurrency);
}

/**
 * Formats quantity.
 *
 * @param int|float $qty
 * @return string
 */
function format_qty($qty) {
    $formatted = number_format((float)$qty, 0, '.', ',');
    $lang = function_exists('get_current_lang') ? get_current_lang() : 'en';
    if ($lang !== 'en') {
        return to_persian_num($formatted);
    }
    return $formatted;
}

/**
 * Formats standard date/time to language-friendly presentation.
 *
 * @param string $datetime
 * @return string
 */
function format_date($datetime) {
    if (empty($datetime)) return '-';
    $timestamp = strtotime($datetime);
    if (!$timestamp) return htmlspecialchars($datetime);
    $d = date('Y/m/d H:i', $timestamp);
    $lang = function_exists('get_current_lang') ? get_current_lang() : 'en';
    if ($lang !== 'en') {
        return to_persian_num($d);
    }
    return $d;
}

/**
 * Sanitizes output for safe HTML rendering.
 *
 * @param string $string
 * @return string
 */
function sanitize($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * CSRF Protection Token Generator.
 * Generates an HMAC-signed token containing timestamp, random nonce, and server signature.
 * Stored in session, but remains cryptographically verifiable even if the session cookie
 * is partitioned or dropped in cross-origin iframe browsers.
 *
 * @return string
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $secret = defined('APP_SECRET_KEY') ? APP_SECRET_KEY : 'nikxor_default_secret_key_88';
        $time = time();
        $nonce = bin2hex(random_bytes(16));
        $signature = hash_hmac('sha256', $time . ':' . $nonce, $secret);
        $_SESSION['csrf_token'] = $time . '.' . $nonce . '.' . $signature;
    }
    return $_SESSION['csrf_token'];
}

/**
 * Outputs hidden inputs with the CSRF token and current session ID.
 * The session ID ensures that if cookies are stripped in an iframe, the session state is seamlessly retained.
 *
 * @return string
 */
function csrf_field() {
    $token = csrf_token();
    $sid = session_id();
    return '<input type="hidden" name="csrf_token" value="' . sanitize($token) . '">' .
           '<input type="hidden" name="sid" value="' . sanitize($sid) . '">';
}

/**
 * Validates the CSRF token.
 * 1. Checks exact match against $_SESSION['csrf_token']
 * 2. Fallback: validates cryptographic HMAC signature and timestamp (valid for 4 hours)
 *    and restores it into the active session.
 *
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token($token) {
    if (empty($token) || !is_string($token)) {
        return false;
    }

    // 1. Direct session match
    if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }

    // 2. Cryptographic HMAC validation (valid for 4 hours)
    $secret = defined('APP_SECRET_KEY') ? APP_SECRET_KEY : 'nikxor_default_secret_key_88';
    $parts = explode('.', $token);
    if (count($parts) === 3) {
        [$time, $nonce, $signature] = $parts;
        if (is_numeric($time)) {
            $age = time() - (int)$time;
            if ($age >= 0 && $age <= 14400) {
                $expectedSignature = hash_hmac('sha256', $time . ':' . $nonce, $secret);
                if (hash_equals($expectedSignature, $signature)) {
                    $_SESSION['csrf_token'] = $token;
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * Alias for verify_csrf_token
 *
 * @param string|null $token
 * @return bool
 */
function csrf_verify($token) {
    return verify_csrf_token($token);
}

/**
 * Sets a flash message to display on the next page view.
 * Supports both set_flash($message, $type) and set_flash($type, $message).
 *
 * @param string $arg1
 * @param string $arg2
 */
function set_flash($arg1, $arg2 = 'info') {
    $validTypes = ['success', 'danger', 'warning', 'info', 'primary'];
    if (in_array($arg1, $validTypes)) {
        $type = $arg1;
        $message = $arg2;
    } elseif (in_array($arg2, $validTypes)) {
        $type = $arg2;
        $message = $arg1;
    } else {
        $type = 'info';
        $message = $arg1;
    }
    $_SESSION['flash_message'] = [
        'type' => $type,
        'text' => $message
    ];
}

/**
 * Retrieves and clears the flash message.
 *
 * @return array|null
 */
function get_flash() {
    if (!empty($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}

/**
 * Safe redirection helper.
 * Compatible with XAMPP, shared hosting (ProFreeHost), and mobile web views.
 *
 * @param string $url
 */
function redirect($url) {
    if (!preg_match('#^https?://#i', $url) && !str_starts_with($url, '/')) {
        $url = rtrim(BASE_URL, '/') . '/' . ltrim($url, '/');
    }

    // Flush and commit session data to disk before redirect to prevent race conditions on Windows/XAMPP
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }

    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        echo "<script>window.location.href=" . json_encode($url) . ";</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url, ENT_QUOTES) . "'></noscript>";
    }
    exit;
}

/**
 * Retrieves a setting value from the settings table.
 *
 * @param string $key
 * @param string $default
 * @return string
 */
function get_setting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1");
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Updates or creates a setting in the settings table.
 *
 * @param string $key
 * @param string $value
 * @return bool
 */
function update_setting($key, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_at)
                               VALUES (:key, :val, CURRENT_TIMESTAMP)
                               ON DUPLICATE KEY UPDATE setting_value = :val2, updated_at = CURRENT_TIMESTAMP");
        return $stmt->execute([':key' => $key, ':val' => $value, ':val2' => $value]);
    } catch (Exception $e) {
        // Fallback for SQLite which uses INSERT OR REPLACE
        try {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value, updated_at)
                                   VALUES (:key, :val, CURRENT_TIMESTAMP)");
            return $stmt->execute([':key' => $key, ':val' => $value]);
        } catch (Exception $e2) {
            return false;
        }
    }
}

/**
 * Records system activity log.
 *
 * @param int|null $user_id
 * @param string $action
 * @param string $details
 */
function log_activity($user_id, $action, $details = '') {
    global $pdo;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, created_at)
                               VALUES (:uid, :action, :details, :ip, CURRENT_TIMESTAMP)");
        $stmt->execute([
            ':uid'     => $user_id,
            ':action'  => $action,
            ':details' => $details,
            ':ip'      => $ip
        ]);
    } catch (Exception $e) {
        // Suppress errors during logging
    }
}

/**
 * Generates a unique invoice number. Format: NZ-YYYYMMDD-XXXX
 *
 * @return string
 */
function generate_invoice_number() {
    global $pdo;
    $datePrefix = date('Ymd');
    try {
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM sales WHERE invoice_no LIKE 'NZ-" . $datePrefix . "-%'");
        $count = (int)($stmt->fetch()['total'] ?? 0) + 1;
    } catch (Exception $e) {
        $count = rand(100, 999);
    }
    return sprintf('NZ-%s-%04d', $datePrefix, $count);
}

/**
 * Calculates profit for a sold stock item.
 * Formula: (Sale Price - Purchase Price - proportional expenses) * Quantity
 *
 * @param float $sell_price
 * @param float $purchase_price
 * @param float $item_expense
 * @param int $quantity
 * @return float
 */
function calculate_item_profit($sell_price, $purchase_price, $item_expense, $quantity) {
    $netUnitProfit = $sell_price - $purchase_price - $item_expense;
    return $netUnitProfit * $quantity;
}

/**
 * Retrieves all categories with their product count.
 *
 * @return array
 */
function get_all_categories() {
    global $pdo;
    try {
        $sql = "SELECT c.*, COUNT(s.id) AS product_count 
                FROM categories c 
                LEFT JOIN stock s ON s.category_id = c.id 
                GROUP BY c.id 
                ORDER BY c.name ASC";
        return $pdo->query($sql)->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Retrieves all active partners.
 *
 * @return array
 */
function get_all_partners() {
    global $pdo;
    try {
        return $pdo->query("SELECT * FROM partners ORDER BY name ASC")->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Computes core dashboard metrics required:
 * 1. stock_value (ارزش مجموعی گدام)
 * 2. total_items_count (تعداد مجموعی اجناس)
 * 3. total_capital (سرمایه عمومی)
 * 4. today_sales (فروشات امروز - تفکیک شده و منحصراً بر اساس بل‌های فروش)
 * 5. today_sales_profit (مفاد ناخالص فروشات امروز)
 * 6. today_expenses (مصارف عملیاتی دکان امروز)
 * 7. today_losses (ضرر و خسارات اجناس امروز)
 * 8. today_net_profit (مفاد خالص نهایی امروز)
 * 9. overall_net_profit (مجموع مفاد عمومی)
 * 10. total_sales_profit (مفاد ناخالص فروش)
 * 11. total_outstanding_debts (قرضه های باقی مانده)
 *
 * @return array
 */
function get_dashboard_metrics() {
    global $pdo;
    $metrics = [
        'stock_value'             => 0,
        'total_items_count'       => 0,
        'total_capital'           => 0,
        'today_sales'             => 0,
        'today_sales_profit'      => 0,
        'today_expenses'          => 0,
        'today_losses'            => 0,
        'today_net_profit'        => 0,
        'overall_net_profit'      => 0,
        'total_sales_profit'      => 0,
        'total_expenses'          => 0,
        'total_losses'            => 0,
        'total_outstanding_debts' => 0,
        'damaged_items_count'     => 0,
    ];

    try {
        // 1. Stock count and value (محاسبه انحصاری بر اساس اجناس سالم و موجود در گدام)
        $stmt = $pdo->query("SELECT 
            SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as total_qty, 
            SUM(CASE WHEN quantity > 0 THEN quantity * (purchase_price + expenses) ELSE 0 END) as total_val,
            SUM(CASE WHEN damaged_qty > 0 THEN damaged_qty ELSE 0 END) as total_damaged
            FROM stock");
        $row = $stmt->fetch();
        $metrics['total_items_count']   = (int)($row['total_qty'] ?? 0);
        $metrics['stock_value']         = (float)($row['total_val'] ?? 0);
        $metrics['damaged_items_count'] = (int)($row['total_damaged'] ?? 0);

        // 2. Today's sales and gross sales profit (فقط و فقط فروشات واقعی بل‌ها - بدون تداخل با ضرر یا مصارف)
        $todayDate  = date('Y-m-d');
        $todayStart = $todayDate . ' 00:00:00';
        $todayEnd   = $todayDate . ' 23:59:59';
        
        $saleStmt = $pdo->prepare("SELECT 
            COALESCE(SUM(total_amount), 0) as today_sales, 
            COALESCE(SUM(profit), 0) as today_sales_profit 
            FROM sales 
            WHERE created_at BETWEEN :start AND :end");
        $saleStmt->execute([':start' => $todayStart, ':end' => $todayEnd]);
        $sRow = $saleStmt->fetch();
        $metrics['today_sales']        = (float)($sRow['today_sales'] ?? 0);
        $metrics['today_sales_profit'] = (float)($sRow['today_sales_profit'] ?? 0);

        // 3. Today's separate expenses and losses (تفکیک دقیق و مستقل مصارف جاری از ضرر و خسارات کالا)
        $expStmt = $pdo->prepare("SELECT 
            COALESCE(SUM(CASE WHEN expense_type = 'ضرر' THEN amount ELSE 0 END), 0) as today_losses,
            COALESCE(SUM(CASE WHEN expense_type != 'ضرر' OR expense_type IS NULL THEN amount ELSE 0 END), 0) as today_expenses
            FROM expenses 
            WHERE (expense_date = :tdate OR (expense_date IS NULL AND created_at BETWEEN :start AND :end))");
        $expStmt->execute([
            ':tdate' => $todayDate, 
            ':start' => $todayStart, 
            ':end'   => $todayEnd
        ]);
        $expRow = $expStmt->fetch();
        $metrics['today_expenses'] = (float)($expRow['today_expenses'] ?? 0);
        $metrics['today_losses']   = (float)($expRow['today_losses'] ?? 0);

        // 4. Net Profit Today = Gross Sales Profit - Operating Expenses - Damaged Goods Losses
        $metrics['today_net_profit'] = $metrics['today_sales_profit'] - $metrics['today_expenses'] - $metrics['today_losses'];

        // 5. Overall sales profit & all-time expenses/losses
        $totProfStmt = $pdo->query("SELECT COALESCE(SUM(profit), 0) as total_prof FROM sales");
        $metrics['total_sales_profit'] = (float)($totProfStmt->fetch()['total_prof'] ?? 0);

        $totExpStmt = $pdo->query("SELECT 
            COALESCE(SUM(CASE WHEN expense_type = 'ضرر' THEN amount ELSE 0 END), 0) as total_losses,
            COALESCE(SUM(CASE WHEN expense_type != 'ضرر' OR expense_type IS NULL THEN amount ELSE 0 END), 0) as total_expenses
            FROM expenses");
        $totExpRow = $totExpStmt->fetch();
        $metrics['total_losses']   = (float)($totExpRow['total_losses'] ?? 0);
        $metrics['total_expenses'] = (float)($totExpRow['total_expenses'] ?? 0);

        // Overall Net Profit = Total Gross Sales Profit - Total Expenses - Total Losses
        $metrics['overall_net_profit'] = $metrics['total_sales_profit'] - $metrics['total_expenses'] - $metrics['total_losses'];

        // 6. Outstanding debts
        $debtStmt = $pdo->query("SELECT COALESCE(SUM(remaining_amount), 0) as total_debts FROM debts WHERE status != 'پرداخت شده'");
        $metrics['total_outstanding_debts'] = (float)($debtStmt->fetch()['total_debts'] ?? 0);

        // 7. Total general capital (سرمایه عمومی دکان با کسر برداشت‌ها و پرداختی‌های مضاربت)
        $capInStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total_in FROM capital WHERE transaction_type != 'برداشت'");
        $capOutStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total_out FROM capital WHERE transaction_type = 'برداشت'");
        $baseCap = (float)($capInStmt->fetch()['total_in'] ?? 0) - (float)($capOutStmt->fetch()['total_out'] ?? 0);
        // نکته مهم حسابداری: ارزش اجناس در گدام ($metrics['stock_value']) پیشاپیش بر اساس اجناس سالم و موجود است
        // و جنس ضایع‌شده خودبخود از آن کسر شده است. لذا جهت جلوگیری از کسر مضاعف ضرر، سود نقدی فروشات منهای مصارف جاری منظور می‌گردد:
        $netCashFlowProfit = $metrics['total_sales_profit'] - $metrics['total_expenses'];
        $metrics['total_capital'] = $baseCap + $metrics['stock_value'] + $netCashFlowProfit;
        if ($metrics['total_capital'] < 0) {
            $metrics['total_capital'] = $metrics['stock_value'];
        }
    } catch (Exception $e) {
        // Safe fallback on uninitialized DB
    }

    return $metrics;
}

/**
 * Computes core dashboard metrics (alias for compatibility)
 *
 * @return array
 */
function get_dashboard_stats() {
    $m = get_dashboard_metrics();
    return [
        'total_stock_count'  => $m['total_items_count'],
        'total_stock_value'  => $m['stock_value'],
        'today_sales'        => $m['today_sales'],
        'today_profit'       => $m['today_net_profit'],
        'today_expenses'     => $m['today_expenses'],
        'today_losses'       => $m['today_losses'],
        'remaining_debt'     => $m['total_outstanding_debts'],
        'total_capital'      => $m['total_capital'],
    ];
}

/**
 * Standard registry of features that can be locked/unlocked from the Super Admin Panel.
 *
 * @return array
 */
function get_lockable_features() {
    global $pdo;
    $defaults = [
        'low_stock_alert' => [
            'key' => 'low_stock_alert',
            'title_fa' => 'هشدار کمبود موجودی',
            'title_en' => 'Low Stock Alert Notification',
            'desc_fa' => 'نمایش بنر و هشدارهای خودکار کسری کالا در سربرگ صفحات',
            'desc_en' => 'Automated top alerts and banners for items with critical low quantity',
            'default' => 1
        ],
        'view_low_stock' => [
            'key' => 'view_low_stock',
            'title_fa' => 'مشاهده تمام اجناس کمبود',
            'title_en' => 'View All Low Stock Items',
            'desc_fa' => 'دسترسی به جدول تحلیلی و گزارش فیلتر شده کالاهای رو به اتمام',
            'desc_en' => 'Detailed list and smart analytics of products nearing zero quantity',
            'default' => 1
        ],
        'preview_profit_specs' => [
            'key' => 'preview_profit_specs',
            'title_fa' => 'پیش‌نمایش مشخصات و مفاد',
            'title_en' => 'Live Profit & Specs Preview',
            'desc_fa' => 'محاسبه زنده حاشیه مفاد تخمینی و جزئیات تخنیکی کالا در هنگام فروش',
            'desc_en' => 'Real-time estimated margin, markup calculation, and technical specs badge on POS',
            'default' => 1
        ],
        'shelf_view' => [
            'key' => 'shelf_view',
            'title_fa' => 'نمای قفسه‌ها / الماری',
            'title_en' => 'Shelf & Cabinet Visualizer',
            'desc_fa' => 'نمایش گرید بصری الماری‌ها، ردیف‌ها و قفسه‌های دکان و انبار',
            'desc_en' => 'Visual rack, shelf, and cabinet locator grid layout for products',
            'default' => 1
        ],
        'capital_turnover' => [
            'key' => 'capital_turnover',
            'title_fa' => 'مدیریت گردش سرمایه دکان',
            'title_en' => 'Capital Flow & Turnover Management',
            'desc_fa' => 'ارزیابی سرعت بازگشت سرمایه، نقدینگی و تزریق وجوه نقدی جدید',
            'desc_en' => 'Capital rotation cycles, cash injections, and cash velocity index',
            'default' => 1
        ],
        'top_selling_report' => [
            'key' => 'top_selling_report',
            'title_fa' => 'پرفروش‌ترین اجناس در این دوره',
            'title_en' => 'Top Selling Products Analytics',
            'desc_fa' => 'نمودار و جدول پرفروش‌ترین برندها، مدل‌ها و لوازم جانبی',
            'desc_en' => 'Rankings and statistics of best performing models and accessories',
            'default' => 1
        ],
        'cash_debt_period_report' => [
            'key' => 'cash_debt_period_report',
            'title_fa' => 'وضعیت نقدینگی و قرضه‌ها در این بازه',
            'title_en' => 'Liquidity & Outstanding Debts in Period',
            'desc_fa' => 'گزارش تحلیلی نقدینگی موجود در برابر مطالبات و بدهی‌های معوقه',
            'desc_en' => 'Cash flow health audit comparing collected vs pending debts for selected timeframe',
            'default' => 1
        ],
        'warehouse_position' => [
            'key' => 'warehouse_position',
            'title_fa' => 'موقعیت در گدام / دکان',
            'title_en' => 'Warehouse & In-Shop Location Tracking',
            'desc_fa' => 'تفکیک دقیق ردیف انبار، کشو، الماری و بخش فروشگاهی',
            'desc_en' => 'Detailed warehouse aisle, bay, drawer, and showroom placement tracking',
            'default' => 1
        ],
        'expenses_module' => [
            'key' => 'expenses_module',
            'title_fa' => 'مدیریت مصارف عمومی دکان',
            'title_en' => 'General Shop Expenses Module',
            'desc_fa' => 'ثبت هزینه‌های عمومی دکان (کرایه، برق، مصارف متفرقه) - ثبت ضرر فعال می‌ماند',
            'desc_en' => 'General overhead expenses tracking (losses logging remains active)',
            'default' => 1
        ],
        'customers_module' => [
            'key' => 'customers_module',
            'title_fa' => 'مدیریت مشتریان VIP و پیشرفته',
            'title_en' => 'Advanced Customers & VIP Club',
            'desc_fa' => 'دسته‌بندی عمده و پرچون، امتیاز مشتریان و فیلترهای تکمیلی',
            'desc_en' => 'Wholesale vs retail categorization, VIP tiering, and detailed profile filters',
            'default' => 1
        ],
        'ai_advisor' => [
            'key' => 'ai_advisor',
            'title_fa' => 'هوش مصنوعی حسابدار و تحلیلگر دکان',
            'title_en' => 'Smart AI Shop Accountant & Advisor',
            'desc_fa' => 'مشاوره هوشمند، پیش‌بینی فروش و پاسخ فوری به سوالات حسابداری و سود دکان',
            'desc_en' => 'AI powered financial advisor answering queries, forecast demands, and profit audit',
            'default' => 1
        ],
        'backup_module' => [
            'key' => 'backup_module',
            'title_fa' => 'پشتیبان‌گیری ابری و محلی (بک‌آپ)',
            'title_en' => 'Cloud & Local Database Backup Module',
            'desc_fa' => 'تهیه نسخه پشتیبان خودکار و دستی از دیتابیس دکان و بازگردانی اطلاعات',
            'desc_en' => 'Automated & manual database backup export and restoration system',
            'default' => 1
        ],
    ];

    // Load any custom features added by super admin
    try {
        if ($pdo) {
            $stmt = $pdo->query("SELECT * FROM custom_features ORDER BY id ASC");
            if ($stmt) {
                while ($cf = $stmt->fetch()) {
                    $defaults[$cf['feature_key']] = [
                        'key' => $cf['feature_key'],
                        'title_fa' => $cf['title_fa'],
                        'title_en' => $cf['title_en'] ?: $cf['feature_key'],
                        'desc_fa' => $cf['description_fa'] ?: '',
                        'desc_en' => $cf['description_en'] ?: '',
                        'default' => (int)$cf['is_locked'],
                        'is_custom' => true,
                        'formula' => $cf['formula_expression'] ?: ''
                    ];
                }
            }
        }
    } catch (Exception $e) {}

    return $defaults;
}

/**
 * Checks if a specific feature is currently locked.
 *
 * @param string $featureKey
 * @return bool
 */
function is_feature_locked($featureKey) {
    global $pdo;
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT is_locked FROM custom_features WHERE feature_key = :k LIMIT 1");
            $stmt->execute([':k' => $featureKey]);
            $row = $stmt->fetch();
            if ($row !== false && isset($row['is_locked'])) {
                return ((int)$row['is_locked']) === 1;
            }
        } catch (Exception $e) {}
    }
    $features = get_lockable_features();
    $default = isset($features[$featureKey]) ? $features[$featureKey]['default'] : 1;
    $val = get_setting('feat_lock_' . $featureKey, (string)$default);
    return ((int)$val) === 1;
}

/**
 * Renders an inline or button lock badge that opens the Ahmad Najeem Nik unlock modal.
 *
 * @param string $featureKey
 * @param string $labelFa
 * @param string $extraClass
 * @return string
 */
function render_lock_badge($featureKey, $labelFa = '', $extraClass = '') {
    if (!is_feature_locked($featureKey)) {
        return '';
    }
    $features = get_lockable_features();
    $titleEn = isset($features[$featureKey]) ? $features[$featureKey]['title_en'] : $featureKey;
    $titleFa = isset($features[$featureKey]) ? $features[$featureKey]['title_fa'] : $labelFa;

    return '<span class="badge-lock-tag ' . $extraClass . '" onclick="event.preventDefault(); event.stopPropagation(); showLockModal(\'' . htmlspecialchars($featureKey, ENT_QUOTES) . '\', \'' . htmlspecialchars($titleEn, ENT_QUOTES) . '\', \'' . htmlspecialchars($titleFa, ENT_QUOTES) . '\'); return false;" title="این گزینه قفل می‌باشد">' .
           '<i class="fa-solid fa-lock text-amber-500 mr-1"></i> ' .
           ($labelFa ? htmlspecialchars($labelFa) : 'قفل') .
           '</span>';
}
