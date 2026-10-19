<?php
/**
 * NiKXor - Mobile Shop Management System
 * Automated Database & System Installer
 * 
 * Target Environment: XAMPP (Apache + MySQL + PHP)
 * URL: http://localhost/nikxor/install.php
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$message = '';
$error = '';
$isInstalled = false;

$lockFile = __DIR__ . '/config/installed.lock';
$alreadyInstalled = file_exists($lockFile);
if (!$alreadyInstalled) {
    try {
        $checkStmt = $pdo->query("SELECT COUNT(*) FROM users");
        if ($checkStmt && (int)$checkStmt->fetchColumn() > 0) {
            $alreadyInstalled = true;
            @file_put_contents($lockFile, date('Y-m-d H:i:s'));
        }
    } catch (Exception $e) {}
}

if ($alreadyInstalled && !defined('CLI_INSTALL')) {
    if (!auth_check() || auth_user()['role'] !== 'admin') {
        die('<div style="font-family: sans-serif; text-align: center; margin-top: 60px; direction: rtl; max-width: 500px; margin-left: auto; margin-right: auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff;">' .
            '<h2 style="color: #0f172a; margin-bottom: 10px;">سیستم قبلاً با موفقیت نصب و فعال شده است</h2>' .
            '<p style="color: #64748b; font-size: 14px; line-height: 1.6;">جهت حفظ امنیت دکان و جلوگیری از دستکاری معلومات، صفحه نصب قفل گردیده است.</p>' .
            '<p style="margin-top: 20px;"><a href="index.php" style="background: #2563eb; color: #ffffff; padding: 8px 18px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: bold;">ورود به محیط دکان</a></p>' .
            '</div>');
    }
}

$isPost = (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') || isset($_GET['auto']) || defined('CLI_INSTALL');

if ($isPost) {
    try {
        $dbDriver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isSqlite = ($dbDriver === 'sqlite');

        $pkDef = $isSqlite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY";
        $tableOptions = $isSqlite ? "" : " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        // 1. Users Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id {$pkDef},
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            fullname VARCHAR(100) NOT NULL,
            role VARCHAR(20) DEFAULT 'staff',
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 2. Categories Table with INDEX on name column for faster searching
        $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
            id {$pkDef},
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            is_sample TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_category_name ON categories (name)");

        // 3. Partners Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS partners (
            id {$pkDef},
            name VARCHAR(100) NOT NULL,
            phone VARCHAR(30),
            share_percentage DECIMAL(5,2) DEFAULT 0.00,
            investment_amount DECIMAL(15,2) DEFAULT 0.00,
            join_date DATE,
            status VARCHAR(20) DEFAULT 'active',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 4. Stock / گدام Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS stock (
            id {$pkDef},
            name VARCHAR(150) NOT NULL,
            category_id INT NULL,
            specifications TEXT,
            quantity INT NOT NULL DEFAULT 0,
            purchase_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            expenses DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            country_of_origin VARCHAR(100) DEFAULT 'چین',
            supplier_name VARCHAR(100) DEFAULT 'شرکت وارداتی',
            warehouse_location VARCHAR(100) DEFAULT 'الماری ۱',
            capital_amount DECIMAL(15,2) DEFAULT 0.00,
            partner_id INT NULL,
            purchase_date DATE,
            is_sample TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_stock_name ON stock (name)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_stock_cat ON stock (category_id)");

        // 5. Customers Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
            id {$pkDef},
            name VARCHAR(100) NOT NULL,
            phone VARCHAR(30),
            address TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 6. Sales Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
            id {$pkDef},
            invoice_no VARCHAR(50) NOT NULL UNIQUE,
            customer_name VARCHAR(100) NOT NULL,
            customer_phone VARCHAR(30),
            total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            remaining_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            profit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            payment_type VARCHAR(30) NOT NULL DEFAULT 'نقد',
            notes TEXT,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_invoice_no ON sales (invoice_no)");

        // 7. Sale Items Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS sale_items (
            id {$pkDef},
            sale_id INT NOT NULL,
            stock_id INT NULL,
            product_name VARCHAR(150) NOT NULL,
            specifications TEXT,
            quantity INT NOT NULL DEFAULT 1,
            unit_purchase_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            unit_expense DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            unit_sale_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            total_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            profit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 8. Debts / قرضه ها Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS debts (
            id {$pkDef},
            sale_id INT NULL,
            customer_name VARCHAR(100) NOT NULL,
            customer_phone VARCHAR(30),
            total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            remaining_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            due_date DATE,
            status VARCHAR(30) DEFAULT 'باز',
            debt_type VARCHAR(30) DEFAULT 'receivable',
            person_type VARCHAR(30) DEFAULT 'customer',
            is_hasana TINYINT(1) DEFAULT 0,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 9. Installments / اقساط Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS installments (
            id {$pkDef},
            debt_id INT NOT NULL,
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            payment_date DATE,
            notes TEXT,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 10. Partnership Transactions Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS partnership_transactions (
            id {$pkDef},
            partner_id INT NOT NULL,
            transaction_type VARCHAR(30) NOT NULL,
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            transaction_date DATE,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 11. Capital / سرمایه Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS capital (
            id {$pkDef},
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            transaction_type VARCHAR(50) NOT NULL DEFAULT 'سرمایه',
            source_description VARCHAR(255),
            transaction_date DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 12. Expenses and Losses Table (مصارف و ضرر)
        $pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
            id {$pkDef},
            expense_type VARCHAR(20) NOT NULL DEFAULT 'مصارف',
            category VARCHAR(100) NOT NULL,
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            expense_date DATE,
            description TEXT,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 13. Settings Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 14. Activity Logs Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
            id {$pkDef},
            user_id INT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(50),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // 15. Custom Features Table for Super Admin Control Center
        $pdo->exec("CREATE TABLE IF NOT EXISTS custom_features (
            id {$pkDef},
            feature_key VARCHAR(100) NOT NULL UNIQUE,
            title_fa VARCHAR(150),
            title_en VARCHAR(150),
            description_fa TEXT,
            description_en TEXT,
            formula_expression TEXT,
            is_locked TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$tableOptions}");

        // Seed 0: Default Custom Features with Expenses locked by default
        $defaultFeaturesList = [
            ['low_stock_alert', 'هشدار کمبود موجودی', 'Low Stock Alert Notification', 'نمایش بنر و هشدارهای خودکار کسری کالا در سربرگ صفحات', 'Automated top alerts and banners for items with critical low quantity', 1],
            ['view_low_stock', 'مشاهده تمام اجناس کمبود', 'View All Low Stock Items', 'دسترسی به جدول تحلیلی و گزارش فیلتر شده کالاهای رو به اتمام', 'Detailed list and smart analytics of products nearing zero quantity', 1],
            ['preview_profit_specs', 'پیش‌نمایش مشخصات و مفاد', 'Live Profit & Specs Preview', 'محاسبه زنده حاشیه مفاد تخمینی و جزئیات تخنیکی کالا در هنگام فروش', 'Real-time estimated margin, markup calculation, and technical specs badge on POS', 1],
            ['shelf_view', 'نمای قفسه‌ها / الماری', 'Shelf & Cabinet Visualizer', 'نمایش گرید بصری الماری‌ها، ردیف‌ها و قفسه‌های دکان و انبار', 'Visual rack, shelf, and cabinet locator grid layout for products', 1],
            ['capital_turnover', 'مدیریت گردش سرمایه دکان', 'Capital Flow & Turnover Management', 'ارزیابی سرعت بازگشت سرمایه، نقدینگی و تزریق وجوه نقدی جدید', 'Capital rotation cycles, cash injections, and cash velocity index', 1],
            ['top_selling_report', 'پرفروش‌ترین اجناس در این دوره', 'Top Selling Products Analytics', 'نمودار و جدول پرفروش‌ترین برندها، مدل‌ها و لوازم جانبی', 'Rankings and statistics of best performing models and accessories', 1],
            ['cash_debt_period_report', 'وضعیت نقدینگی و قرضه‌ها در این بازه', 'Liquidity & Outstanding Debts in Period', 'گزارش تحلیلی نقدینگی موجود در برابر مطالبات و بدهی‌های معوقه', 'Cash flow health audit comparing collected vs pending debts for selected timeframe', 1],
            ['warehouse_position', 'موقعیت در گدام / دکان', 'Warehouse & In-Shop Location Tracking', 'تفکیک دقیق ردیف انبار، کشو، الماری و بخش فروشگاهی', 'Detailed warehouse aisle, bay, drawer, and showroom placement tracking', 1],
            ['expenses_module', 'مدیریت مصارف عمومی دکان', 'General Shop Expenses Module', 'ثبت هزینه‌های عمومی دکان (کرایه، برق، مصارف متفرقه) - ثبت ضرر فعال می‌ماند', 'General overhead expenses tracking (losses logging remains active)', 1],
            ['customers_module', 'مدیریت مشتریان VIP و پیشرفته', 'Advanced Customers & VIP Club', 'دسته‌بندی عمده و پرچون، امتیاز مشتریان و فیلترهای تکمیلی', 'Wholesale vs retail categorization, VIP tiering, and detailed profile filters', 1],
            ['ai_advisor', 'هوش مصنوعی حسابدار و تحلیلگر دکان', 'Smart AI Shop Accountant & Advisor', 'مشاوره هوشمند، پیش‌بینی فروش و پاسخ فوری به سوالات حسابداری و سود دکان', 'AI powered financial advisor answering queries, forecast demands, and profit audit', 1],
            ['backup_module', 'پشتیبان‌گیری ابری و محلی (بک‌آپ)', 'Cloud & Local Database Backup Module', 'تهیه نسخه پشتیبان خودکار و دستی از دیتابیس دکان و بازگردانی اطلاعات', 'Automated & manual database backup export and restoration system', 1]
        ];

        foreach ($defaultFeaturesList as $fRow) {
            try {
                $chkFeat = $pdo->prepare("SELECT id FROM custom_features WHERE feature_key = ?");
                $chkFeat->execute([$fRow[0]]);
                if (!$chkFeat->fetch()) {
                    $insFeat = $pdo->prepare("INSERT INTO custom_features (feature_key, title_fa, title_en, description_fa, description_en, is_locked) VALUES (?, ?, ?, ?, ?, ?)");
                    $insFeat->execute($fRow);
                }
            } catch (Exception $e) {}
        }

        // Seed 1: Create Default Admin Users if not exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'nikadmin' LIMIT 1");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $hashedPassword = password_hash('afghanwashere', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, role, status) VALUES (:u, :p, :fn, 'admin', 'active')");
            $stmt->execute([
                ':u'  => 'nikadmin',
                ':p'  => $hashedPassword,
                ':fn' => 'احمد ضیا (مدیر کل)'
            ]);
        }

        $stmtAdmin = $pdo->prepare("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
        $stmtAdmin->execute();
        if (!$stmtAdmin->fetch()) {
            $hashedAdmin = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, role, status) VALUES (:u, :p, :fn, 'admin', 'active')");
            $stmt->execute([
                ':u'  => 'admin',
                ':p'  => $hashedAdmin,
                ':fn' => 'مدیر سیستم (Admin)'
            ]);
        }

        // Seed 2: Default Shop Settings
        $defaultSettings = [
            'shop_name'             => 'فروشگاه ضیا',
            'shop_phone_central'    => '0791510046 — اشکان',
            'shop_phone_secondary'  => '0747230143 — احمد ضیا',
            'shop_phone_whatsapp'   => '008619584867173',
            'shop_address_central'  => 'کابل مقابل پل خشتی موبایل سنتر منزل چهارم دوکان A323',
            'shop_address_secondary'=> 'موبایل سنتر منزل سوم دوکان A248',
            'currency_title'        => 'افغانی',
            'invoice_footer_note'   => 'با تشکر از خریداری شما. اجناس بدون تکت و کارتن مسترد نمی گردد.',
            'auto_print_invoice'    => '0', // Set to 0 so it doesn't repeatedly trigger print dialogs during testing
            'low_stock_threshold'   => '3',
            'system_initialized'    => '0',
            'super_admin_pin'       => '2277512',
            'admin_pin'             => '788278'
        ];

        foreach ($defaultSettings as $sKey => $sVal) {
            update_setting($sKey, $sVal);
        }

        // Seed 3: Temporary Demo/Test Categories (marked clearly as is_sample = 1)
        // Shopkeeper has 100% control to edit/delete/add new ones.
        $sampleCategories = [
            'مبایل سامسونگ' => 'انواع گوشی های سامسونگ گلکسی سری S و A',
            'مبایل آیفون'   => 'گوشی های اصلی اپل آیفون با ضمانت کارتن',
            'اکسسیری'       => 'چارجر، هندزفری، کیبل و پاوربانک',
            'قطعات و پرزه'  => 'ال سی دی، بتری، گلس کمره و بدنه',
            'کاور و گلس'    => 'انواع پوش، کاور سیلیکونی و محافظ صفحه'
        ];

        $categoryIds = [];
        foreach ($sampleCategories as $cName => $cDesc) {
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = :name LIMIT 1");
            $stmt->execute([':name' => $cName]);
            $row = $stmt->fetch();
            if ($row) {
                $categoryIds[$cName] = $row['id'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, description, is_sample) VALUES (:n, :d, 1)");
                $stmt->execute([':n' => $cName, ':d' => $cDesc]);
                $categoryIds[$cName] = $pdo->lastInsertId();
            }
        }

        // Seed 4: Partner Sample (for demonstration)
        $partnerId = null;
        $stmt = $pdo->query("SELECT id FROM partners LIMIT 1");
        $partnerRow = $stmt->fetch();
        if ($partnerRow) {
            $partnerId = $partnerRow['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO partners (name, phone, share_percentage, investment_amount, join_date, status, notes) 
                                   VALUES ('اشکان', '0791510046', 50.00, 500000.00, '2026-01-01', 'active', 'شریک اصلی دکان')");
            $stmt->execute();
            $partnerId = $pdo->lastInsertId();

            // Initial capital record
            $pdo->exec("INSERT INTO capital (amount, transaction_type, source_description, transaction_date) 
                        VALUES (1000000, 'سرمایه', 'سرمایه اولیه فروشگاه ضیا', '2026-01-01')");
        }

        // Seed 5: Exactly 20 SAMPLE products as requested
        // Check if stock already has products
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM stock");
        $stockCount = (int)($stmt->fetch()['total'] ?? 0);

        if ($stockCount === 0) {
            $sampleProducts = [
                ['name' => 'سامسونگ گلکسی S24 Ultra (نمونه)', 'cat' => 'مبایل سامسونگ', 'spec' => '256GB / 12GB RAM - Titanium Black - Dual SIM', 'qty' => 4, 'buy' => 68000, 'exp' => 500, 'loc' => 'ویترین مرکزی', 'supp' => 'دبی الکترونیکس'],
                ['name' => 'آیفون 15 پرو مکس (نمونه)', 'cat' => 'مبایل آیفون', 'spec' => '256GB - Natural Titanium - ZAA با کارتن اصلی', 'qty' => 3, 'buy' => 74000, 'exp' => 600, 'loc' => 'ویترین مرکزی', 'supp' => 'واردات دبی'],
                ['name' => 'سامسونگ گلکسی A55 5G (نمونه)', 'cat' => 'مبایل سامسونگ', 'spec' => '128GB / 8GB RAM - Awesome Iceblue', 'qty' => 6, 'buy' => 24500, 'exp' => 200, 'loc' => 'الماری ۲ طبقه ۱', 'supp' => 'کابل فون'],
                ['name' => 'آیفون 13 نرمال (نمونه)', 'cat' => 'مبایل آیفون', 'spec' => '128GB - Starlight White - سلامت بتری 100%', 'qty' => 5, 'buy' => 42000, 'exp' => 400, 'loc' => 'ویترین ۲', 'supp' => 'واردات دبی'],
                ['name' => 'سامسونگ گلکسی A15 (نمونه)', 'cat' => 'مبایل سامسونگ', 'spec' => '128GB / 6GB RAM - Blue Black', 'qty' => 8, 'buy' => 11500, 'exp' => 150, 'loc' => 'الماری ۲ طبقه ۲', 'supp' => 'کابل فون'],
                ['name' => 'آیفون 14 پرو (نمونه)', 'cat' => 'مبایل آیفون', 'spec' => '256GB - Deep Purple - سیمکارت خور فیزیکی', 'qty' => 2, 'buy' => 61000, 'exp' => 500, 'loc' => 'ویترین مرکزی', 'supp' => 'واردات دبی'],
                ['name' => 'چارجر اورجینال سامسونگ 25W (نمونه)', 'cat' => 'اکسسیری', 'spec' => 'سه پین اورجینال ویتنام با کیبل Type-C', 'qty' => 25, 'buy' => 650, 'exp' => 20, 'loc' => 'الماری اکسسیری', 'supp' => 'چین امپورت'],
                ['name' => 'چارجر 20W اپل آیفون اورجینال (نمونه)', 'cat' => 'اکسسیری', 'spec' => 'پک اصلی با گارانتی تعویض', 'qty' => 20, 'buy' => 950, 'exp' => 30, 'loc' => 'الماری اکسسیری', 'supp' => 'چین امپورت'],
                ['name' => 'کیبل فست شارژ تایپ سی انکر (نمونه)', 'cat' => 'اکسسیری', 'spec' => 'طول ۱ متر روکش بافته ضد قطعی Anker', 'qty' => 30, 'buy' => 320, 'exp' => 10, 'loc' => 'الماری اکسسیری', 'supp' => 'چین امپورت'],
                ['name' => 'پاوربانک ۲۰ هزار میلی آمپر شیائومی (نمونه)', 'cat' => 'اکسسیری', 'spec' => '20000mAh 22.5W Fast Charge دو خروجی', 'qty' => 10, 'buy' => 1400, 'exp' => 50, 'loc' => 'الماری ۱ طبقه ۳', 'supp' => 'چین امپورت'],
                ['name' => 'ایربادز بی‌سیم سامسونگ Galaxy Buds 2 (نمونه)', 'cat' => 'اکسسیری', 'spec' => 'نویز کنسلینگ فعال ANC - رنگ زیتونی', 'qty' => 4, 'buy' => 4500, 'exp' => 100, 'loc' => 'ویترین صوتی', 'supp' => 'دبی الکترونیکس'],
                ['name' => 'هندزفری سیمی اورجینال آیفون Lightning (نمونه)', 'cat' => 'اکسسیری', 'spec' => 'پک شرکتی اصلی با کیفیت صدای بیس دار', 'qty' => 18, 'buy' => 480, 'exp' => 15, 'loc' => 'الماری اکسسیری', 'supp' => 'چین امپورت'],
                ['name' => 'ال سی دی اورجینال آیفون 11 (نمونه)', 'cat' => 'قطعات و پرزه', 'spec' => 'روکاری اورجینال تست شده با تاچ سالم', 'qty' => 3, 'buy' => 3800, 'exp' => 100, 'loc' => 'بخش پرزه جات', 'supp' => 'پرزه جات کابل'],
                ['name' => 'ال سی دی سامسونگ A32 اورجینال سرویس پک (نمونه)', 'cat' => 'قطعات و پرزه', 'spec' => 'Super AMOLED با فریم اصلی ویتنام', 'qty' => 3, 'buy' => 4200, 'exp' => 100, 'loc' => 'بخش پرزه جات', 'supp' => 'پرزه جات کابل'],
                ['name' => 'بتری اصلی آیفون 12 پرو مکس (نمونه)', 'cat' => 'قطعات و پرزه', 'spec' => 'تقویتی با آی‌سی اورجینال ۱۰۰ درصد', 'qty' => 7, 'buy' => 1650, 'exp' => 50, 'loc' => 'بخش پرزه جات', 'supp' => 'پرزه جات کابل'],
                ['name' => 'گلس پرایوسی آیفون 15 پرو (نمونه)', 'cat' => 'کاور و گلس', 'spec' => 'ضد جاسوسی 9D تمام صفحه فول چسب', 'qty' => 40, 'buy' => 90, 'exp' => 5, 'loc' => 'کشو گلس ۱', 'supp' => 'چین امپورت'],
                ['name' => 'گلس سوپر دی سامسونگ A54 (نمونه)', 'cat' => 'کاور و گلس', 'spec' => 'Super D شفاف ضد خط و خش', 'qty' => 50, 'buy' => 60, 'exp' => 5, 'loc' => 'کشو گلس ۲', 'supp' => 'چین امپورت'],
                ['name' => 'کاور سیلیکونی اصل آیفون 13 (نمونه)', 'cat' => 'کاور و گلس', 'spec' => 'مخمل داخل اورجینال در ۵ رنگ متنوع', 'qty' => 22, 'buy' => 180, 'exp' => 10, 'loc' => 'استند کاور', 'supp' => 'چین امپورت'],
                ['name' => 'کاور مگ سیف شفاف سامسونگ S23 Ultra (نمونه)', 'cat' => 'کاور و گلس', 'spec' => 'ضد زردی با حلقه مگنتی قوی', 'qty' => 15, 'buy' => 240, 'exp' => 10, 'loc' => 'استند کاور', 'supp' => 'چین امپورت'],
                ['name' => 'سوزن سیمکارت و تبدیل خشاب (نمونه)', 'cat' => 'اکسسیری', 'spec' => 'بسته ۱۰۰ عددی استیل مقاوم', 'qty' => 10, 'buy' => 50, 'exp' => 2, 'loc' => 'کشو میز', 'supp' => 'چین امپورت']
            ];

            $insertStock = $pdo->prepare("INSERT INTO stock 
                (name, category_id, specifications, quantity, purchase_price, expenses, country_of_origin, supplier_name, warehouse_location, capital_amount, partner_id, purchase_date, is_sample) 
                VALUES (:name, :cat_id, :spec, :qty, :buy, :exp, 'چین', :supp, :loc, :cap, :pid, :pdate, 1)");

            foreach ($sampleProducts as $sp) {
                $cId = $categoryIds[$sp['cat']] ?? null;
                $totCost = ($sp['buy'] + $sp['exp']) * $sp['qty'];
                $insertStock->execute([
                    ':name'   => $sp['name'],
                    ':cat_id' => $cId,
                    ':spec'   => $sp['spec'],
                    ':qty'    => $sp['qty'],
                    ':buy'    => $sp['buy'],
                    ':exp'    => $sp['exp'],
                    ':supp'   => $sp['supp'],
                    ':loc'    => $sp['loc'],
                    ':cap'    => $totCost,
                    ':pid'    => $partnerId,
                    ':pdate'  => date('Y-m-d')
                ]);
            }
        }

        $isInstalled = true;
        $message = "دیتابیس و تنظیمات سیستم NiKXor با موفقیت نصب و پیکربندی شدند!";
    } catch (Exception $e) {
        $error = "خطا در فرآیند نصب: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب دیتابیس - NiKXor (فروشگاه ضیا)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
</head>
<body class="login-body">

<div class="login-card" style="max-width: 540px;">
    <div class="login-header">
        <div class="login-logo-box">
            <i class="fa-solid fa-database"></i>
        </div>
        <h2>نصب خودکار سیستم NiKXor</h2>
        <p>پیکربندی جداول، حساب مدیریت و تنظیمات اولیه دکان (فروشگاه ضیا)</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div><?= sanitize($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($isInstalled): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <div>
                <strong><?= sanitize($message) ?></strong>
                <p style="margin-top: 6px; font-size: 12.5px;">جداول دیتابیس، ۲۰ قلم جنس نمونه جهت آزمایش، دسته بندی ها و حساب پیش فرض ایجاد گردیدند.</p>
            </div>
        </div>

        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
            <h4 style="font-size: 13.5px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">
                <i class="fa-solid fa-key ml-1"></i> مشخصات حساب مدیریت:
            </h4>
            <p style="font-size: 13px; color: #334155; margin-bottom: 4px;">نام کاربری: <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px;">nikadmin</code></p>
            <p style="font-size: 13px; color: #334155;">رمز عبور: <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px;">afghanwashere</code></p>
        </div>

        <a href="<?= BASE_URL ?>modules/login.php" class="btn btn-primary" style="width: 100%;">
            <i class="fa-solid fa-arrow-right-to-bracket"></i> ورود به سیستم دکان
        </a>
    <?php else: ?>
        <p style="color: #475569; font-size: 13px; margin-bottom: 20px; line-height: 1.6;">
            این اسکریپت دیتابیس <code>nikxor_db</code> را بررسی و تمامی ۱۴ جدول مورد نیاز (شامل گدام، فروش، بل، قرضه ها، اقساط، شرکا و ۲۰ جنس نمونه) را به صورت امن و بدون حذف داده های موجود ایجاد می کند.
        </p>

        <form method="POST" action="">
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                <i class="fa-solid fa-play"></i> شروع نصب و راه اندازی دیتابیس
            </button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
