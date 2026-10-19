<?php
/**
 * NiKXor - Mobile Shop Management System
 * Database Configuration & Connection Handler
 * 
 * Supports standard MySQL PDO connection for XAMPP
 * and provides seamless SQLite fallback if MySQL daemon is not running.
 */

// Database credentials for XAMPP
defined('DB_HOST') or define('DB_HOST', '127.0.0.1');
defined('DB_PORT') or define('DB_PORT', '3306');
defined('DB_NAME') or define('DB_NAME', 'nikxor_db');
defined('DB_USER') or define('DB_USER', 'root');
defined('DB_PASS') or define('DB_PASS', '');
defined('DB_CHARSET') or define('DB_CHARSET', 'utf8mb4');

/**
 * Automatically provisions database tables and seed data if missing in MySQL or SQLite
 */
function auto_provision_database_tables($pdo) {
    if (!$pdo) return;
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $hasStock = false;
        if ($driver === 'sqlite') {
            $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='stock'")->fetch();
            $hasStock = !empty($check);
        } else {
            $check = $pdo->query("SHOW TABLES LIKE 'stock'")->fetch();
            $hasStock = !empty($check);
        }

        if ($hasStock) {
            return;
        }

        // Attempt 1: Execute nikxor_database.sql file if running on MySQL
        $sqlPath = __DIR__ . '/../nikxor_database.sql';
        if ($driver === 'mysql' && file_exists($sqlPath)) {
            $sqlContent = file_get_contents($sqlPath);
            $lines = explode("\n", $sqlContent);
            $stmt = '';
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0) {
                    continue;
                }
                $stmt .= $line . "\n";
                if (substr(rtrim($trimmed), -1) === ';') {
                    try {
                        $pdo->exec($stmt);
                    } catch (Exception $ex) {}
                    $stmt = '';
                }
            }
            if (!empty(trim($stmt))) {
                try {
                    $pdo->exec($stmt);
                } catch (Exception $ex) {}
            }
        }

        // Re-check if stock table is now present
        if ($driver === 'sqlite') {
            $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='stock'")->fetch();
            $hasStock = !empty($check);
        } else {
            $check = $pdo->query("SHOW TABLES LIKE 'stock'")->fetch();
            $hasStock = !empty($check);
        }

        // Attempt 2: Built-in resilient schema creator for both MySQL and SQLite
        if (!$hasStock) {
            provision_embedded_schema($pdo, $driver);
        }

        // Always run schema migration checks to ensure latest columns are present
        ensure_schema_migrations($pdo);
    } catch (Exception $e) {
        // Table provisioning fallback
    }
}

/**
 * Built-in resilient table generation and seeding (Zero-Configuration fallback)
 */
function provision_embedded_schema($pdo, $driver) {
    $isSqlite = ($driver === 'sqlite');
    $pk = $isSqlite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY";
    $opts = $isSqlite ? "" : " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $ins = $isSqlite ? "INSERT OR IGNORE INTO" : "INSERT IGNORE INTO";

    // 1. Users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id {$pk},
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        fullname VARCHAR(100) NOT NULL,
        role VARCHAR(20) DEFAULT 'admin',
        status VARCHAR(20) DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 2. Categories
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id {$pk},
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        is_sample TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 3. Partners
    $pdo->exec("CREATE TABLE IF NOT EXISTS partners (
        id {$pk},
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(30),
        share_percentage DECIMAL(5,2) DEFAULT 0.00,
        investment_amount DECIMAL(15,2) DEFAULT 0.00,
        join_date DATE,
        status VARCHAR(20) DEFAULT 'active',
        partner_type VARCHAR(30) DEFAULT 'general',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 4. Stock
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock (
        id {$pk},
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
        barcode VARCHAR(50) DEFAULT '',
        sale_price DECIMAL(15,2) DEFAULT 0.00,
        mudaraba_id INT NULL,
        min_quantity INT DEFAULT 3,
        is_sample TINYINT(1) DEFAULT 0,
        damaged_qty INT NOT NULL DEFAULT 0,
        is_damaged TINYINT(1) DEFAULT 0,
        damage_notes VARCHAR(255) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 5. Customers
    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id {$pk},
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(30),
        customer_type VARCHAR(50) DEFAULT 'پرچون',
        company VARCHAR(100) DEFAULT '',
        address TEXT,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 6. Sales
    $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
        id {$pk},
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
    ){$opts}");

    // 7. Sale Items
    $pdo->exec("CREATE TABLE IF NOT EXISTS sale_items (
        id {$pk},
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
    ){$opts}");

    // 8. Debts
    $pdo->exec("CREATE TABLE IF NOT EXISTS debts (
        id {$pk},
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
    ){$opts}");

    // 9. Installments
    $pdo->exec("CREATE TABLE IF NOT EXISTS installments (
        id {$pk},
        debt_id INT NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        payment_date DATE,
        payment_reason VARCHAR(150) DEFAULT '',
        capital_amount DECIMAL(15,2) DEFAULT 0.00,
        profit_amount DECIMAL(15,2) DEFAULT 0.00,
        notes TEXT,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 10. Partnership Transactions
    $pdo->exec("CREATE TABLE IF NOT EXISTS partnership_transactions (
        id {$pk},
        partner_id INT NOT NULL,
        transaction_type VARCHAR(30) NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        transaction_date DATE,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 11. Capital
    $pdo->exec("CREATE TABLE IF NOT EXISTS capital (
        id {$pk},
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        transaction_type VARCHAR(50) NOT NULL DEFAULT 'سرمایه',
        source_description VARCHAR(255),
        transaction_date DATE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 12. Expenses
    $pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
        id {$pk},
        expense_type VARCHAR(20) NOT NULL DEFAULT 'مصارف',
        category VARCHAR(100) NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        expense_date DATE,
        description TEXT,
        stock_id INT DEFAULT NULL,
        purchase_price DECIMAL(15,2) DEFAULT NULL,
        sale_price DECIMAL(15,2) DEFAULT NULL,
        loss_qty INT DEFAULT 1,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 13. Settings
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 14. Activity Logs
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id {$pk},
        user_id INT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        ip_address VARCHAR(50),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 15. Custom Features
    $pdo->exec("CREATE TABLE IF NOT EXISTS custom_features (
        id {$pk},
        feature_key VARCHAR(100) NOT NULL UNIQUE,
        title_fa VARCHAR(150),
        title_en VARCHAR(150),
        description_fa TEXT,
        description_en TEXT,
        formula_expression TEXT,
        is_locked TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 16. Mudaraba Contracts
    $pdo->exec("CREATE TABLE IF NOT EXISTS mudaraba_contracts (
        id {$pk},
        partner_name VARCHAR(150) NOT NULL,
        phone VARCHAR(50) DEFAULT '',
        project_title VARCHAR(200) NOT NULL,
        country_source VARCHAR(100) DEFAULT '',
        capital_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        partner_profit_rate DECIMAL(5,2) NOT NULL DEFAULT 50.00,
        shop_profit_rate DECIMAL(5,2) NOT NULL DEFAULT 50.00,
        total_sales_amount DECIMAL(15,2) DEFAULT 0.00,
        total_profit_amount DECIMAL(15,2) DEFAULT 0.00,
        paid_profit_to_partner DECIMAL(15,2) DEFAULT 0.00,
        returned_capital DECIMAL(15,2) DEFAULT 0.00,
        start_date DATE,
        settlement_date DATE,
        status VARCHAR(30) DEFAULT 'active',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // Seed default settings
    $defSettings = [
        'shop_name'             => 'فروشگاه ضیا',
        'shop_manager_name'     => 'احمد ضیا',
        'shop_phone'            => '0791510046',
        'shop_address'          => 'کابل، افغانستان',
        'currency'              => 'افغانی',
        'currency_symbol'       => 'AFN',
        'app_theme'             => 'gold',
        'low_stock_threshold'   => '3',
        'screen_lock_enabled'   => '1',
        'screen_lock_pin'       => '1234',
        'super_admin_pin'       => '2277512',
    ];
    $sStmt = $pdo->prepare("{$ins} settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defSettings as $k => $v) {
        $sStmt->execute([$k, $v]);
    }

    // Seed default admin users
    $pwdHash = password_hash('afghanwashere', PASSWORD_DEFAULT);
    $uStmt = $pdo->prepare("{$ins} users (id, username, password, fullname, role, status) VALUES (?, ?, ?, ?, ?, ?)");
    $uStmt->execute([1, 'nikadmin', $pwdHash, 'احمد ضیا (مدیر کل)', 'admin', 'active']);
    $uStmt->execute([2, 'admin', password_hash('admin123', PASSWORD_DEFAULT), 'مدیر سیستم', 'admin', 'active']);

    // Seed categories
    $cStmt = $pdo->prepare("{$ins} categories (id, name, description, is_sample) VALUES (?, ?, ?, 1)");
    $cStmt->execute([1, 'مبایل سامسونگ', 'انواع گوشی های سامسونگ گلکسی سری S و A']);
    $cStmt->execute([2, 'مبایل آیفون', 'گوشی های اصلی اپل آیفون با ضمانت کارتن']);
    $cStmt->execute([3, 'اکسسیری', 'چارجر، هندزفری، کیبل و پاوربانک']);
    $cStmt->execute([4, 'قطعات و پرزه', 'ال سی دی، بتری، گلس کمره و بدنه']);
    $cStmt->execute([5, 'کاور و گلس', 'انواع پوش، کاور سیلیکونی و محافظ صفحه']);

    // Seed partner Ashkan
    $pStmt = $pdo->prepare("{$ins} partners (id, name, phone, share_percentage, investment_amount, join_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $pStmt->execute([1, 'اشکان', '0791510046', 50.00, 500000.00, date('Y-01-01'), 'active', 'شریک اصلی دکان']);

    // Seed 10 sample stock products
    $sampleProducts = [
        [1, 'سامسونگ گلکسی S24 Ultra (نمونه)', 1, '256GB / 12GB RAM - Titanium Black', 4, 68000.00, 500.00, 'ویتنام', 'دبی الکترونیکس', 'ویترین مرکزی', 274000.00, 1, 72000.00],
        [2, 'آیفون 15 پرو مکس (نمونه)', 2, '256GB - Natural Titanium - ZAA', 3, 74000.00, 600.00, 'امریکا', 'واردات دبی', 'ویترین مرکزی', 223800.00, 1, 79000.00],
        [3, 'سامسونگ گلکسی A55 5G (نمونه)', 1, '128GB / 8GB RAM - Iceblue', 6, 24500.00, 200.00, 'ویتنام', 'کابل فون', 'الماری ۲', 148200.00, 1, 26000.00],
        [4, 'آیفون 13 نرمال (نمونه)', 2, '128GB - Starlight White', 5, 42000.00, 400.00, 'جاپان', 'واردات دبی', 'ویترین ۲', 212000.00, 1, 44500.00],
        [5, 'سامسونگ گلکسی A15 (نمونه)', 1, '128GB / 6GB RAM - Blue', 8, 11500.00, 150.00, 'هند', 'کابل فون', 'الماری ۲', 93200.00, 1, 12500.00],
        [6, 'چارجر اورجینال سامسونگ 25W (نمونه)', 3, 'سه پین ویتنام با کیبل Type-C', 25, 650.00, 20.00, 'ویتنام', 'چین امپورت', 'الماری اکسسیری', 16750.00, 1, 850.00],
        [7, 'چارجر 20W اپل آیفون اورجینال (نمونه)', 3, 'پک اصلی با گارانتی تعویض', 20, 950.00, 30.00, 'چین', 'چین امپورت', 'الماری اکسسیری', 19600.00, 1, 1200.00],
        [8, 'کیبل فست شارژ تایپ سی انکر (نمونه)', 3, 'طول ۱ متر روکش بافته Anker', 30, 320.00, 10.00, 'چین', 'چین امپورت', 'الماری اکسسیری', 9900.00, 1, 450.00],
        [9, 'پاوربانک ۲۰ هزار میلی آمپر شیائومی (نمونه)', 3, '20000mAh 22.5W Fast Charge', 10, 1400.00, 50.00, 'چین', 'چین امپورت', 'الماری ۱', 14500.00, 1, 1750.00],
        [10, 'ایرپاد پرو ۲ های‌کپی اپل (نمونه)', 3, 'نویزکنسلینگ فعال با پاپ‌آپ', 12, 1100.00, 50.00, 'چین', 'دبی امپورت', 'ویترین اکسسیری', 13800.00, 1, 1450.00]
    ];

    $stkStmt = $pdo->prepare("{$ins} stock (id, name, category_id, specifications, quantity, purchase_price, expenses, country_of_origin, supplier_name, warehouse_location, capital_amount, partner_id, sale_price, purchase_date, is_sample) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    foreach ($sampleProducts as $sp) {
        $stkStmt->execute([$sp[0], $sp[1], $sp[2], $sp[3], $sp[4], $sp[5], $sp[6], $sp[7], $sp[8], $sp[9], $sp[10], $sp[11], $sp[12], date('Y-m-d')]);
    }
}

/**
 * Harmonizes MySQL database and table collations to prevent Error 1267 (Illegal mix of collations)
 * Safely disables foreign key checks during conversion and converts all tables to utf8mb4_unicode_ci
 */
function harmonize_mysql_collations($pdo) {
    if (!$pdo) return;
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') return;
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
        $pdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
        
        $dbName = DB_NAME;
        try {
            $pdo->exec("ALTER DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (Exception $e) {}

        $tables = ['mudaraba_contracts', 'capital', 'stock', 'expenses', 'sales', 'sale_items', 'debts', 'installments', 'categories', 'partners', 'users', 'settings', 'activity_logs', 'custom_features', 'customers'];
        try {
            $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($allTables)) {
                $tables = array_unique(array_merge($tables, $allTables));
            }
        } catch (Exception $e) {}

        foreach ($tables as $t) {
            try {
                $pdo->exec("ALTER TABLE `{$t}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (Exception $e) {}
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    } catch (Exception $ge) {}
}

/**
 * Returns a singleton PDO connection
 *
 * @return PDO
 */
function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $pdoOptions = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Attempt 1: Try MySQL connection (Target environment: XAMPP)
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdoOptions);
        $pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
        $pdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
        harmonize_mysql_collations($pdo);
        auto_provision_database_tables($pdo);
        return $pdo;
    } catch (PDOException $mysqlEx) {
        // If database does not exist yet, try connecting without dbname to create it
        try {
            $dsnWithoutDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
            $tempPdo = new PDO($dsnWithoutDb, DB_USER, DB_PASS, $pdoOptions);
            // Server is reachable, create nikxor_db automatically
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $tempPdo->exec("USE `" . DB_NAME . "`");
            $tempPdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
            $tempPdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
            $pdo = $tempPdo;
            harmonize_mysql_collations($pdo);
            auto_provision_database_tables($pdo);
            return $pdo;
        } catch (PDOException $secondEx) {
            // MySQL is not running on this host (e.g. cloud sandbox or before XAMPP MySQL started)
            // Seamlessly fall back to local SQLite database so the application is immediately operational!
            $sqlitePath = __DIR__ . '/../nikxor_db.sqlite';
            try {
                $pdo = new PDO("sqlite:" . $sqlitePath, null, null, $pdoOptions);
                $pdo->exec("PRAGMA foreign_keys = ON;");
                auto_provision_database_tables($pdo);
                return $pdo;
            } catch (PDOException $sqliteEx) {
                die("<div style='font-family:tahoma;direction:rtl;padding:20px;color:#b91c1c;background:#fee2e2;border:1px solid #f87171;border-radius:8px;'>
                    <h3>خطا در برقراری ارتباط با دیتابیس</h3>
                    <p>امکان اتصال به MySQL و SQLite میسر نشد.</p>
                    <p>پیام خطا: " . htmlspecialchars($mysqlEx->getMessage()) . "</p>
                </div>");
            }
        }
    }
}

// Global PDO connection reference
$pdo = getDBConnection();

/**
 * Ensures required columns and tables exist across SQLite and MySQL environments
 */
function ensure_schema_migrations($db) {
    if (!$db) return;
    try {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isSqlite = ($driver === 'sqlite');
        $pk = $isSqlite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY";
        $opts = $isSqlite ? "" : " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        // 1. Ensure all auxiliary tables exist
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS mudaraba_contracts (
                id {$pk},
                partner_name VARCHAR(150) NOT NULL,
                phone VARCHAR(50) DEFAULT '',
                project_title VARCHAR(200) NOT NULL,
                country_source VARCHAR(100) DEFAULT '',
                capital_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                partner_profit_rate DECIMAL(5,2) NOT NULL DEFAULT 50.00,
                shop_profit_rate DECIMAL(5,2) NOT NULL DEFAULT 50.00,
                total_sales_amount DECIMAL(15,2) DEFAULT 0.00,
                total_profit_amount DECIMAL(15,2) DEFAULT 0.00,
                paid_profit_to_partner DECIMAL(15,2) DEFAULT 0.00,
                returned_capital DECIMAL(15,2) DEFAULT 0.00,
                start_date DATE,
                settlement_date DATE,
                status VARCHAR(30) DEFAULT 'active',
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ){$opts}");
        } catch (Exception $e) {}

        try {
            $db->exec("CREATE TABLE IF NOT EXISTS custom_features (
                id {$pk},
                feature_key VARCHAR(100) UNIQUE,
                title_fa VARCHAR(150),
                title_en VARCHAR(150),
                description_fa TEXT,
                description_en TEXT,
                formula_expression TEXT,
                is_locked INT DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ){$opts}");
        } catch (Exception $e) {}

        // 2. Comprehensive column checks for ALL tables
        $tablesRequiredColumns = [
            'expenses' => [
                'expense_type'   => "VARCHAR(20) NOT NULL DEFAULT 'مصارف'",
                'stock_id'       => "INT NULL DEFAULT NULL",
                'purchase_price' => "DECIMAL(15,2) NULL DEFAULT 0.00",
                'sale_price'     => "DECIMAL(15,2) NULL DEFAULT 0.00",
                'loss_qty'       => "INT DEFAULT 1",
                'category'       => "VARCHAR(100) NOT NULL DEFAULT 'متفرقه'",
                'amount'         => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
                'expense_date'   => "DATE NULL",
                'description'    => "TEXT NULL",
                'created_by'     => "INT NULL",
                'created_at'     => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'stock' => [
                'barcode'            => "VARCHAR(50) DEFAULT ''",
                'sale_price'         => "DECIMAL(15,2) DEFAULT 0.00",
                'mudaraba_id'        => "INT NULL DEFAULT NULL",
                'min_quantity'       => "INT DEFAULT 3",
                'is_sample'          => "TINYINT(1) DEFAULT 0",
                'damaged_qty'        => "INT NOT NULL DEFAULT 0",
                'is_damaged'         => "TINYINT(1) DEFAULT 0",
                'damage_notes'       => "VARCHAR(255) DEFAULT ''",
                'partner_id'         => "INT NULL DEFAULT NULL",
                'purchase_date'      => "DATE NULL",
                'capital_amount'     => "DECIMAL(15,2) DEFAULT 0.00",
                'warehouse_location' => "VARCHAR(100) DEFAULT 'الماری ۱'",
                'country_of_origin'  => "VARCHAR(100) DEFAULT 'چین'",
                'supplier_name'      => "VARCHAR(100) DEFAULT 'شرکت وارداتی'",
                'expenses'           => "DECIMAL(15,2) DEFAULT 0.00",
                'purchase_price'     => "DECIMAL(15,2) DEFAULT 0.00",
                'quantity'           => "INT DEFAULT 0",
                'specifications'     => "TEXT NULL",
                'category_id'        => "INT NULL DEFAULT NULL",
                'created_at'         => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'debts' => [
                'debt_type'        => "VARCHAR(30) DEFAULT 'receivable'",
                'person_type'      => "VARCHAR(30) DEFAULT 'customer'",
                'is_hasana'        => "TINYINT(1) DEFAULT 0",
                'due_date'         => "DATE NULL",
                'notes'            => "TEXT NULL",
                'remaining_amount' => "DECIMAL(15,2) DEFAULT 0.00",
                'paid_amount'      => "DECIMAL(15,2) DEFAULT 0.00",
                'total_amount'     => "DECIMAL(15,2) DEFAULT 0.00",
                'customer_phone'   => "VARCHAR(30) NULL",
                'customer_name'    => "VARCHAR(100) NOT NULL DEFAULT ''",
                'sale_id'          => "INT NULL DEFAULT NULL",
                'status'           => "VARCHAR(30) DEFAULT 'باز'",
                'created_at'       => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'installments' => [
                'payment_reason' => "VARCHAR(150) DEFAULT ''",
                'capital_amount' => "DECIMAL(15,2) DEFAULT 0.00",
                'profit_amount'  => "DECIMAL(15,2) DEFAULT 0.00",
                'notes'          => "TEXT NULL",
                'payment_date'   => "DATE NULL",
                'created_by'     => "INT NULL DEFAULT NULL",
                'created_at'     => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'customers' => [
                'customer_type' => "VARCHAR(50) DEFAULT 'پرچون'",
                'company'       => "VARCHAR(100) DEFAULT ''",
                'address'       => "TEXT NULL",
                'notes'         => "TEXT NULL",
                'phone'         => "VARCHAR(30) NULL",
                'name'          => "VARCHAR(100) NOT NULL DEFAULT ''",
                'created_at'    => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'categories' => [
                'description' => "TEXT NULL",
                'is_sample'   => "TINYINT(1) DEFAULT 0",
                'created_at'  => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'partners' => [
                'share_percentage'  => "DECIMAL(5,2) DEFAULT 0.00",
                'investment_amount' => "DECIMAL(15,2) DEFAULT 0.00",
                'join_date'         => "DATE NULL",
                'status'            => "VARCHAR(20) DEFAULT 'active'",
                'partner_type'      => "VARCHAR(30) DEFAULT 'general'",
                'notes'             => "TEXT NULL",
                'created_at'        => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'sales' => [
                'profit'         => "DECIMAL(15,2) DEFAULT 0.00",
                'payment_type'   => "VARCHAR(30) DEFAULT 'نقد'",
                'notes'          => "TEXT NULL",
                'customer_phone' => "VARCHAR(30) NULL",
                'created_by'     => "INT NULL DEFAULT NULL",
                'created_at'     => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'sale_items' => [
                'stock_id'            => "INT NULL DEFAULT NULL",
                'specifications'      => "TEXT NULL",
                'unit_purchase_price' => "DECIMAL(15,2) DEFAULT 0.00",
                'unit_expense'        => "DECIMAL(15,2) DEFAULT 0.00",
                'unit_sale_price'     => "DECIMAL(15,2) DEFAULT 0.00",
                'profit'              => "DECIMAL(15,2) DEFAULT 0.00",
                'created_at'          => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'custom_features' => [
                'title_en'           => "VARCHAR(150) NULL",
                'description_en'     => "TEXT NULL",
                'formula_expression' => "TEXT NULL",
                'updated_at'         => "DATETIME NULL"
            ],
            'mudaraba_contracts' => [
                'partner_name'           => "VARCHAR(150) NOT NULL DEFAULT ''",
                'phone'                  => "VARCHAR(50) DEFAULT ''",
                'project_title'          => "VARCHAR(200) NOT NULL DEFAULT ''",
                'country_source'         => "VARCHAR(100) DEFAULT ''",
                'capital_amount'         => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
                'partner_profit_rate'    => "DECIMAL(5,2) NOT NULL DEFAULT 50.00",
                'shop_profit_rate'       => "DECIMAL(5,2) NOT NULL DEFAULT 50.00",
                'total_sales_amount'     => "DECIMAL(15,2) DEFAULT 0.00",
                'total_profit_amount'    => "DECIMAL(15,2) DEFAULT 0.00",
                'paid_profit_to_partner' => "DECIMAL(15,2) DEFAULT 0.00",
                'returned_capital'       => "DECIMAL(15,2) DEFAULT 0.00",
                'start_date'             => "DATE NULL",
                'settlement_date'        => "DATE NULL",
                'status'                 => "VARCHAR(30) DEFAULT 'active'",
                'notes'                  => "TEXT NULL",
                'created_at'             => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'capital' => [
                'amount'             => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
                'transaction_type'   => "VARCHAR(50) NOT NULL DEFAULT 'سرمایه'",
                'source_description' => "VARCHAR(255) NULL",
                'transaction_date'   => "DATE NULL",
                'created_at'         => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ]
        ];

        foreach ($tablesRequiredColumns as $tbl => $cols) {
            try {
                $existingCols = [];
                if ($isSqlite) {
                    $info = $db->query("PRAGMA table_info(`{$tbl}`)")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($info as $r) {
                        $existingCols[] = strtolower($r['name']);
                    }
                } else {
                    try {
                        $info = $db->query("SHOW COLUMNS FROM `{$tbl}`")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($info as $r) {
                            $existingCols[] = strtolower($r['Field']);
                        }
                    } catch (Exception $e) {}
                }

                foreach ($cols as $colName => $colDef) {
                    if (empty($existingCols) || !in_array(strtolower($colName), $existingCols)) {
                        try {
                            if ($isSqlite) {
                                $db->exec("ALTER TABLE `{$tbl}` ADD COLUMN `{$colName}` {$colDef}");
                            } else {
                                $db->exec("ALTER TABLE `{$tbl}` ADD `{$colName}` {$colDef}");
                            }
                        } catch (Exception $alterEx) {}
                    }
                }
            } catch (Exception $tableEx) {}
        }

        // 3. Ensure custom_features rows exist
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

        $insWord = $isSqlite ? "INSERT OR IGNORE INTO" : "INSERT IGNORE INTO";
        $fInsert = $db->prepare("{$insWord} custom_features (feature_key, title_fa, title_en, description_fa, description_en, is_locked) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($defaultFeaturesList as $fRow) {
            try {
                $fInsert->execute($fRow);
            } catch (Exception $e) {}
        }
    } catch (Exception $e) {
        // Suppress errors during initial bootstrap
    }
}

ensure_schema_migrations($pdo);
