<?php
/**
 * NiKXor - Mobile Shop Management System
 * Mudaraba Partnership & Foreign Sourcing Module (حساب اختصاصی مضاربت و تجارت کالای خارجی)
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

$pageTitle = 'حساب اختصاصی مضاربت و سرمایه‌گذاری خارجی';
$error = '';
$success = '';

// Self-healing schema for Mudaraba & Capital tables across SQLite & MySQL (XAMPP)
function ensure_mudaraba_full_schema($db) {
    if (!$db) return;
    try {
        if (function_exists('harmonize_mysql_collations')) {
            harmonize_mysql_collations($db);
        }
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isSqlite = ($driver === 'sqlite');

        // 1. Check & heal mudaraba_contracts table
        $mColsNeeded = [
            'partner_name'           => "VARCHAR(150) NOT NULL DEFAULT ''",
            'phone'                  => "VARCHAR(50) DEFAULT ''",
            'project_title'          => "VARCHAR(200) NOT NULL DEFAULT ''",
            'country_source'         => "VARCHAR(100) DEFAULT ''",
            'capital_amount'         => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            'partner_profit_rate'    => "DECIMAL(5,2) NOT NULL DEFAULT 50.00",
            'shop_profit_rate'       => "DECIMAL(5,2) NOT NULL DEFAULT 50.00",
            'total_sales_amount'     => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            'total_profit_amount'    => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            'paid_profit_to_partner' => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            'returned_capital'       => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            'start_date'             => "DATE NULL",
            'settlement_date'        => "DATE NULL",
            'status'                 => "VARCHAR(30) DEFAULT 'active'",
            'notes'                  => "TEXT NULL",
            'created_at'             => "DATETIME DEFAULT CURRENT_TIMESTAMP"
        ];

        $mExisting = [];
        try {
            if ($isSqlite) {
                $cols = $db->query("PRAGMA table_info(mudaraba_contracts)")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) $mExisting[] = strtolower($c['name']);
            } else {
                $cols = $db->query("SHOW COLUMNS FROM `mudaraba_contracts`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) $mExisting[] = strtolower($c['Field']);
            }
        } catch (Exception $e) {}

        foreach ($mColsNeeded as $col => $def) {
            if (!in_array(strtolower($col), $mExisting)) {
                try {
                    if ($isSqlite) {
                        $db->exec("ALTER TABLE mudaraba_contracts ADD COLUMN `{$col}` {$def}");
                    } else {
                        $colType = $def;
                        if (strpos($colType, 'VARCHAR') !== false || strpos($colType, 'TEXT') !== false) {
                            $colType .= " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
                        }
                        $db->exec("ALTER TABLE `mudaraba_contracts` ADD `{$col}` {$colType}");
                    }
                } catch (Exception $e) {}
            }
        }

        // 2. Check & heal capital table
        $cColsNeeded = [
            'amount'             => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            'transaction_type'   => "VARCHAR(50) NOT NULL DEFAULT 'سرمایه'",
            'source_description' => "VARCHAR(255) NULL",
            'transaction_date'   => "DATE NULL",
            'created_at'         => "DATETIME DEFAULT CURRENT_TIMESTAMP"
        ];

        $cExisting = [];
        try {
            if ($isSqlite) {
                $cols = $db->query("PRAGMA table_info(capital)")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) $cExisting[] = strtolower($c['name']);
            } else {
                $cols = $db->query("SHOW COLUMNS FROM `capital`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) $cExisting[] = strtolower($c['Field']);
            }
        } catch (Exception $e) {}

        foreach ($cColsNeeded as $col => $def) {
            if (!in_array(strtolower($col), $cExisting)) {
                try {
                    if ($isSqlite) {
                        $db->exec("ALTER TABLE capital ADD COLUMN `{$col}` {$def}");
                    } else {
                        $colType = $def;
                        if (strpos($colType, 'VARCHAR') !== false || strpos($colType, 'TEXT') !== false) {
                            $colType .= " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
                        }
                        $db->exec("ALTER TABLE `capital` ADD `{$col}` {$colType}");
                    }
                } catch (Exception $e) {}
            }
        }

        // 3. Check & heal stock table for mudaraba_id & damaged_qty
        $sColsNeeded = [
            'mudaraba_id' => "INT NULL DEFAULT NULL",
            'damaged_qty' => "INT NOT NULL DEFAULT 0",
            'is_damaged'  => "TINYINT(1) NOT NULL DEFAULT 0"
        ];
        $sExisting = [];
        try {
            if ($isSqlite) {
                $cols = $db->query("PRAGMA table_info(stock)")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) $sExisting[] = strtolower($c['name']);
            } else {
                $cols = $db->query("SHOW COLUMNS FROM `stock`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) $sExisting[] = strtolower($c['Field']);
            }
        } catch (Exception $e) {}

        foreach ($sColsNeeded as $col => $def) {
            if (!in_array(strtolower($col), $sExisting)) {
                try {
                    if ($isSqlite) {
                        $db->exec("ALTER TABLE stock ADD COLUMN `{$col}` {$def}");
                    } else {
                        $db->exec("ALTER TABLE `stock` ADD `{$col}` {$def}");
                    }
                } catch (Exception $e) {}
            }
        }
    } catch (Exception $ge) {}
}
ensure_mudaraba_full_schema($pdo);
if (function_exists('harmonize_mysql_collations')) {
    harmonize_mysql_collations($pdo);
}

// Fetch all categories for stock goods registration
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// 1. Handle Add New Mudaraba Contract + Auto Save Goods into Warehouse (Stock)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_mudaraba') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error!', 'خطای امنیتی نشست!', 'د ناستې امنیتي تېروتنه!');
    } else {
        $partnerName       = trim($_POST['partner_name'] ?? '');
        $phone             = trim($_POST['phone'] ?? '');
        $countrySource     = trim($_POST['country_source'] ?? 'دوبی (امارات)');
        $partnerRate       = (float)clean_number($_POST['partner_profit_rate'] ?? '50', true);
        $shopRate          = (float)clean_number($_POST['shop_profit_rate'] ?? '50', true);
        $startDate         = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
        $settleDate        = !empty($_POST['settlement_date']) ? $_POST['settlement_date'] : null;
        $notes             = trim($_POST['notes'] ?? '');

        // Product / Warehouse fields
        $productName       = trim($_POST['product_name'] ?? '');
        $categoryId        = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $specifications    = trim($_POST['specifications'] ?? '');
        $quantity          = (int)clean_number($_POST['quantity'] ?? '0', false);
        $purchasePrice     = (float)clean_number($_POST['purchase_price'] ?? '0', true);
        $salePrice         = (float)clean_number($_POST['sale_price'] ?? '0', true);
        $expenses          = (float)clean_number($_POST['expenses'] ?? '0', true);
        $barcode           = trim($_POST['barcode'] ?? '');
        $warehouseLocation = trim($_POST['warehouse_location'] ?? '');

        $unitCost          = $purchasePrice + $expenses;
        $calculatedCapital = $unitCost * $quantity;
        $manualCapital     = (float)clean_number($_POST['capital_amount'] ?? '0', true);
        $capitalAmount     = ($calculatedCapital > 0) ? $calculatedCapital : $manualCapital;

        $projectTitle = !empty($productName) 
            ? ($productName . ' (' . ($currentLang === 'en' ? $quantity . ' units' : to_persian_num($quantity) . ' عدد') . ')') 
            : trim($_POST['project_title'] ?? __t('Commercial Mudaraba Goods Import', 'خرید کالای تجاری مضاربت', 'د مضاربت سوداګریز توکي'));

        if (empty($partnerName)) {
            $error = __t('Please enter investor partner name.', 'لطفاً نام شریک سرمایه‌گذار مضاربت را وارد نمایید.', 'د مضاربت د شریک نوم ولیکئ.');
        } elseif (empty($projectTitle)) {
            $error = __t('Please specify goods name or project title.', 'لطفاً نام جنس یا عنوان پروژه مضاربت را مشخص نمایید.', 'د جنس یا پروژې نوم ولیکئ.');
        } elseif ($capitalAmount <= 0) {
            $error = __t('Mudaraba capital must be greater than zero.', 'مقدار سرمایه مضاربت باید بیشتر از صفر باشد.', 'د مضاربت پانګه باید له صفر زیاته وي.');
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Insert Mudaraba Contract
                $stmt = $pdo->prepare("INSERT INTO mudaraba_contracts 
                    (partner_name, phone, project_title, country_source, capital_amount, partner_profit_rate, shop_profit_rate, start_date, settlement_date, status, notes)
                    VALUES (:pname, :phone, :title, :country, :capital, :prate, :srate, :sdate, :edate, 'active', :notes)");
                $stmt->execute([
                    ':pname'   => $partnerName,
                    ':phone'   => $phone,
                    ':title'   => $projectTitle,
                    ':country' => $countrySource,
                    ':capital' => $capitalAmount,
                    ':prate'   => $partnerRate,
                    ':srate'   => $shopRate,
                    ':sdate'   => $startDate,
                    ':edate'   => $settleDate,
                    ':notes'   => $notes
                ]);

                $contractId = (int)$pdo->lastInsertId();

                // 2. Automatically save goods into warehouse (stock table) if product name and quantity provided
                if (!empty($productName) && $quantity > 0) {
                    $stockStmt = $pdo->prepare("INSERT INTO stock 
                        (name, category_id, specifications, quantity, purchase_price, sale_price, expenses, barcode, country_of_origin, supplier_name, warehouse_location, capital_amount, mudaraba_id, purchase_date, created_at)
                        VALUES (:name, :cat, :spec, :qty, :buy, :sale, :exp, :barcode, :orig, :supp, :loc, :cap, :mid, :pdate, :cdate)");
                    
                    $stockStmt->execute([
                        ':name'    => $productName,
                        ':cat'     => $categoryId,
                        ':spec'    => $specifications,
                        ':qty'     => $quantity,
                        ':buy'     => $purchasePrice,
                        ':sale'    => $salePrice,
                        ':exp'     => $expenses,
                        ':barcode' => $barcode,
                        ':orig'    => $countrySource,
                        ':supp'    => "خرید از پول مضاربت با " . $partnerName,
                        ':loc'     => $warehouseLocation,
                        ':cap'     => $capitalAmount,
                        ':mid'     => $contractId,
                        ':pdate'   => $startDate,
                        ':cdate'   => date('Y-m-d H:i:s')
                    ]);
                }

                $pdo->commit();
                log_activity($currentUser['id'], 'ثبت قرارداد و مال مضاربت', "ثبت قرارداد مضاربت «{$partnerName}» و ثبت جنس «{$productName}» به تعداد {$quantity} در گدام");
                set_flash(sprintf(__t('Mudaraba contract with «%s» registered and inventory stored directly in warehouse.', 'قرارداد مضاربت با «%s» ثبت شد و جنس مربوطه مستقیماً در گدام ذخیره گردید.', 'له «%s» سره د مضاربت تړون ثبت او توکي ګودام ته داخل شول.'), $partnerName), 'success');
                redirect(BASE_URL . 'modules/mudaraba.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = __t('Error registering contract and stock: ', 'خطا در ثبت قرارداد و گدام: ', 'د تړون په ثبت کې تېروتنه: ') . $e->getMessage();
            }
        }
    }
}

// 2. Handle Adding Extra Goods into Existing Mudaraba Contract
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_contract_item') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error!', 'خطای امنیتی نشست!', 'د ناستې امنیتي تېروتنه!');
    } else {
        $contractId        = (int)($_POST['contract_id'] ?? 0);
        $productName       = trim($_POST['product_name'] ?? '');
        $categoryId        = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $specifications    = trim($_POST['specifications'] ?? '');
        $quantity          = (int)clean_number($_POST['quantity'] ?? '0', false);
        $purchasePrice     = (float)clean_number($_POST['purchase_price'] ?? '0', true);
        $salePrice         = (float)clean_number($_POST['sale_price'] ?? '0', true);
        $expenses          = (float)clean_number($_POST['expenses'] ?? '0', true);
        $barcode           = trim($_POST['barcode'] ?? '');
        $countrySource     = trim($_POST['country_source'] ?? '');
        $warehouseLocation = trim($_POST['warehouse_location'] ?? '');
        $purchaseDate      = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : date('Y-m-d');

        if ($contractId <= 0 || empty($productName) || $quantity <= 0) {
            $error = __t('Please enter valid product name and quantity.', 'لطفاً نام جنس و تعداد معتبر را وارد نمایید.', 'د توکي نوم او شمېر سم ولیکئ.');
        } else {
            try {
                $pdo->beginTransaction();

                $cStmt = $pdo->prepare("SELECT partner_name, country_source FROM mudaraba_contracts WHERE id = :id");
                $cStmt->execute([':id' => $contractId]);
                $cRow = $cStmt->fetch();

                if (!$cRow) {
                    throw new Exception(__t('Mudaraba contract not found.', 'قرارداد مضاربت یافت نشد.', 'د مضاربت تړون ونه موندل شو.'));
                }

                $partnerName = $cRow['partner_name'];
                if (empty($countrySource)) {
                    $countrySource = $cRow['country_source'];
                }

                $itemCapital = ($purchasePrice + $expenses) * $quantity;

                $stockStmt = $pdo->prepare("INSERT INTO stock 
                    (name, category_id, specifications, quantity, purchase_price, sale_price, expenses, barcode, country_of_origin, supplier_name, warehouse_location, capital_amount, mudaraba_id, purchase_date, created_at)
                    VALUES (:name, :cat, :spec, :qty, :buy, :sale, :exp, :barcode, :orig, :supp, :loc, :cap, :mid, :pdate, :cdate)");
                
                $stockStmt->execute([
                    ':name'    => $productName,
                    ':cat'     => $categoryId,
                    ':spec'    => $specifications,
                    ':qty'     => $quantity,
                    ':buy'     => $purchasePrice,
                    ':sale'    => $salePrice,
                    ':exp'     => $expenses,
                    ':barcode' => $barcode,
                    ':orig'    => $countrySource,
                    ':supp'    => "خرید از پول مضاربت با " . $partnerName,
                    ':loc'     => $warehouseLocation,
                    ':cap'     => $itemCapital,
                    ':mid'     => $contractId,
                    ':pdate'   => $purchaseDate,
                    ':cdate'   => date('Y-m-d H:i:s')
                ]);

                $updC = $pdo->prepare("UPDATE mudaraba_contracts SET capital_amount = capital_amount + :cap WHERE id = :id");
                $updC->execute([':cap' => $itemCapital, ':id' => $contractId]);

                $pdo->commit();
                log_activity($currentUser['id'], 'افزودن جنس به مضاربت', "افزودن جنس «{$productName}» به تعداد {$quantity} به قرارداد مضاربت #{$contractId}");
                set_flash(sprintf(__t('Item «%s» added to Mudaraba contract and stored in warehouse.', 'جنس «%s» با موفقیت به قرارداد مضاربت افزوده و در گدام ذخیره شد.', 'توکی «%s» په تړون او ګودام کې ثبت شو.'), $productName), 'success');
                redirect(BASE_URL . 'modules/mudaraba.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = __t('Error registering item: ', 'خطا در ثبت جنس: ', 'د توکي په ثبت کې تېروتنه: ') . $e->getMessage();
            }
        }
    }
}

// 3. Handle Record Sales / Profit Progress on Mudaraba Contract
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_progress') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error!', 'خطای امنیتی نشست!', 'د ناستې امنیتي تېروتنه!');
    } else {
        $contractId   = (int)($_POST['contract_id'] ?? 0);
        $addSales     = (float)clean_number($_POST['add_sales'] ?? '0', true);
        $addProfit    = (float)clean_number($_POST['add_profit'] ?? '0', true);

        if ($contractId > 0 && ($addSales > 0 || $addProfit > 0)) {
            $stmt = $pdo->prepare("UPDATE mudaraba_contracts 
                SET total_sales_amount = total_sales_amount + :sales,
                    total_profit_amount = total_profit_amount + :profit
                WHERE id = :id");
            $stmt->execute([
                ':sales'  => $addSales,
                ':profit' => $addProfit,
                ':id'     => $contractId
            ]);

            log_activity($currentUser['id'], 'ثبت فروش مضاربت', "ثبت فروش {$addSales} و مفاد {$addProfit} برای قرارداد مضاربت #{$contractId}");
            set_flash(__t('Sales revenue and profit added to Mudaraba account successfully.', 'فروش و مفاد با موفقیت به حساب مضاربت افزوده شد.', 'پلور او ګټه په بریا سره اضافه شوه.'), 'success');
            redirect(BASE_URL . 'modules/mudaraba.php');
        } else {
            $error = __t('Invalid sales or profit values entered.', 'مقادیر وارد شده نامعتبر است.', 'ناسم ارقام داخل شوي.');
        }
    }
}

// 4. Handle Payout / Settlement to Partner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'payout_partner') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash(__t('Security session error!', 'خطای امنیتی نشست!', 'د ناستې امنیتي تېروتنه!'), 'danger');
        redirect(BASE_URL . 'modules/mudaraba.php');
    } else {
        $contractId  = (int)($_POST['contract_id'] ?? 0);
        $payProfit   = (float)clean_number($_POST['payout_profit'] ?? '0', true);
        $returnCap   = (float)clean_number($_POST['return_capital'] ?? '0', true);
        $markSettled = !empty($_POST['mark_settled']) ? 'settled' : 'active';
        $pDate       = !empty($_POST['payout_date']) ? $_POST['payout_date'] : date('Y-m-d');

        if ($contractId > 0 && ($payProfit > 0 || $returnCap > 0 || $markSettled === 'settled')) {
            try {
                if (function_exists('harmonize_mysql_collations')) {
                    harmonize_mysql_collations($pdo);
                }
                ensure_mudaraba_full_schema($pdo);

                $cStmt = $pdo->prepare("SELECT partner_name, project_title FROM mudaraba_contracts WHERE id = :id");
                $cStmt->execute([':id' => $contractId]);
                $cData = $cStmt->fetch();
                $pName = $cData['partner_name'] ?? "Partner #{$contractId}";
                $pProj = $cData['project_title'] ?? "Mudaraba Project";

                $statusSql = ($markSettled === 'settled') ? ", status = :mstatus, settlement_date = :sdate" : "";
                $stmt = $pdo->prepare("UPDATE mudaraba_contracts 
                    SET paid_profit_to_partner = COALESCE(paid_profit_to_partner, 0) + :profit,
                        returned_capital = COALESCE(returned_capital, 0) + :cap
                        {$statusSql}
                    WHERE id = :id");
                $updParams = [
                    ':profit'    => $payProfit,
                    ':cap'       => $returnCap,
                    ':id'        => $contractId
                ];
                if ($markSettled === 'settled') {
                    $updParams[':mstatus'] = 'settled';
                    $updParams[':sdate']   = $pDate;
                }
                $stmt->execute($updParams);

                $totalPaidOut = $payProfit + $returnCap;
                if ($totalPaidOut > 0) {
                    $descParts = [];
                    if ($returnCap > 0) {
                        $descParts[] = "استرداد اصل سرمایه: " . format_money($returnCap);
                    }
                    if ($payProfit > 0) {
                        $descParts[] = "پرداخت سهم سود: " . format_money($payProfit);
                    }
                    $capDesc = "پرداخت وجه مضاربت به «{$pName}» ({$pProj}) — " . implode(' | ', $descParts);

                    try {
                        $capStmt = $pdo->prepare("INSERT INTO capital (amount, transaction_type, source_description, transaction_date, created_at)
                                                  VALUES (:amt, :ttype, :desc, :tdate, :cdate)");
                        $capStmt->execute([
                            ':amt'   => $totalPaidOut,
                            ':ttype' => 'برداشت',
                            ':desc'  => $capDesc,
                            ':tdate' => $pDate,
                            ':cdate' => date('Y-m-d H:i:s')
                        ]);
                    } catch (Exception $capEx) {
                        try {
                            $pdo->prepare("INSERT INTO capital (amount, transaction_type, source_description) VALUES (?, ?, ?)")
                                ->execute([$totalPaidOut, 'برداشت', $capDesc]);
                        } catch (Exception $capFallback) {}
                    }
                }

                log_activity($currentUser['id'], 'پرداخت به شریک مضاربت', "پرداخت سود {$payProfit} و استرداد سرمایه {$returnCap} برای قرارداد مضاربت #{$contractId} («{$pName}»)");
                set_flash(sprintf(__t('Payment of %s disbursed to partner and logged in capital ledger.', 'مبلغ پرداختی (%s) با موفقیت در حساب مضاربت ثبت و از سرمایه اصلی دکان کسر گردید.', 'د (%s) تادیه شریک ته ورکړل شوه او له پانګې کمه شوه.'), format_money($totalPaidOut)), 'success');
                redirect(BASE_URL . 'modules/mudaraba.php');
            } catch (Exception $e) {
                set_flash(__t('Error registering partner payout: ', 'خطا در ثبت پرداخت به شریک: ', 'د تادیې په ثبت کې تېروتنه: ') . $e->getMessage(), 'danger');
                redirect(BASE_URL . 'modules/mudaraba.php');
            }
        }
    }
}

// 5. Handle Delete Mudaraba Contract
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_contract') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash(__t('Security session error!', 'خطای امنیتی نشست!', 'د ناستې امنیتي تېروتنه!'), 'danger');
    } else {
        $delId = (int)($_POST['contract_id'] ?? 0);
        if ($delId > 0) {
            $pdo->prepare("UPDATE stock SET mudaraba_id = NULL WHERE mudaraba_id = :id")->execute([':id' => $delId]);
            $pdo->prepare("DELETE FROM mudaraba_contracts WHERE id = :id")->execute([':id' => $delId]);
            log_activity($currentUser['id'], 'حذف قرارداد مضاربت', "حذف قرارداد مضاربت #{$delId}");
            set_flash(__t('Mudaraba contract deleted successfully.', 'قرارداد مضاربت با موفقیت حذف گردید.', 'د مضاربت تړون په بریالیتوب ړنګ شو.'), 'success');
            redirect(BASE_URL . 'modules/mudaraba.php');
        }
    }
}

// 6. Handle Edit Contract
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_contract') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash(__t('Security session error!', 'خطای امنیتی نشست!', 'د ناستې امنیتي تېروتنه!'), 'danger');
        redirect(BASE_URL . 'modules/mudaraba.php');
    } else {
        $cId         = (int)($_POST['contract_id'] ?? 0);
        $partnerName = trim($_POST['partner_name'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $title       = trim($_POST['project_title'] ?? '');
        $country     = trim($_POST['country_source'] ?? '');
        $capital     = (float)clean_number($_POST['capital_amount'] ?? '0', true);
        $pRate       = (float)clean_number($_POST['partner_profit_rate'] ?? '50', true);
        $sRate       = (float)clean_number($_POST['shop_profit_rate'] ?? '50', true);
        $paidProfit  = (float)clean_number($_POST['paid_profit_to_partner'] ?? '0', true);
        $returnedCap = (float)clean_number($_POST['returned_capital'] ?? '0', true);
        $salesAmt    = (float)clean_number($_POST['total_sales_amount'] ?? '0', true);
        $profitAmt   = (float)clean_number($_POST['total_profit_amount'] ?? '0', true);
        $sDate       = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
        $eDate       = !empty($_POST['settlement_date']) ? $_POST['settlement_date'] : null;
        $status      = trim($_POST['status'] ?? 'active');
        $notes       = trim($_POST['notes'] ?? '');

        if ($cId <= 0 || empty($partnerName) || empty($title)) {
            set_flash(__t('Please specify partner name and project title.', 'لطفاً نام شریک و عنوان پروژه را مشخص نمایید.', 'د شریک او پروژې نوم ولیکئ.'), 'danger');
            redirect(BASE_URL . 'modules/mudaraba.php');
        } else {
            try {
                $oldStmt = $pdo->prepare("SELECT * FROM mudaraba_contracts WHERE id = :id");
                $oldStmt->execute([':id' => $cId]);
                $oldContract = $oldStmt->fetch();

                $oldPayoutTotal = ((float)($oldContract['paid_profit_to_partner'] ?? 0)) + ((float)($oldContract['returned_capital'] ?? 0));
                $newPayoutTotal = $paidProfit + $returnedCap;
                $payoutDelta    = $newPayoutTotal - $oldPayoutTotal;

                $stmt = $pdo->prepare("UPDATE mudaraba_contracts SET 
                    partner_name = :pname,
                    phone = :phone,
                    project_title = :title,
                    country_source = :country,
                    capital_amount = :capital,
                    partner_profit_rate = :prate,
                    shop_profit_rate = :srate,
                    paid_profit_to_partner = :paidprofit,
                    returned_capital = :retcap,
                    total_sales_amount = :salesamt,
                    total_profit_amount = :profitamt,
                    start_date = :sdate,
                    settlement_date = :edate,
                    status = :status,
                    notes = :notes
                    WHERE id = :id");
                $stmt->execute([
                    ':pname'      => $partnerName,
                    ':phone'      => $phone,
                    ':title'      => $title,
                    ':country'    => $country,
                    ':capital'    => $capital,
                    ':prate'      => $pRate,
                    ':srate'      => $sRate,
                    ':paidprofit' => $paidProfit,
                    ':retcap'     => $returnedCap,
                    ':salesamt'   => $salesAmt,
                    ':profitamt'  => $profitAmt,
                    ':sdate'      => $sDate,
                    ':edate'      => $eDate,
                    ':status'     => $status,
                    ':notes'      => $notes,
                    ':id'         => $cId
                ]);

                if (abs($payoutDelta) > 0.01) {
                    if ($payoutDelta > 0) {
                        $capStmt = $pdo->prepare("INSERT INTO capital (amount, transaction_type, source_description, transaction_date, created_at)
                                                  VALUES (:amt, 'برداشت', :desc, :tdate, :cdate)");
                        $capStmt->execute([
                            ':amt'   => $payoutDelta,
                            ':desc'  => "اصلاح مالی مضاربت #{$cId} («{$partnerName}»): افزایش پرداخت به شریک",
                            ':tdate' => date('Y-m-d'),
                            ':cdate' => date('Y-m-d H:i:s')
                        ]);
                    } else {
                        $capStmt = $pdo->prepare("INSERT INTO capital (amount, transaction_type, source_description, transaction_date, created_at)
                                                  VALUES (:amt, 'سرمایه', :desc, :tdate, :cdate)");
                        $capStmt->execute([
                            ':amt'   => abs($payoutDelta),
                            ':desc'  => "اصلاح مالی مضاربت #{$cId} («{$partnerName}»): تعدیل و برگشت به سرمایه دکان",
                            ':tdate' => date('Y-m-d'),
                            ':cdate' => date('Y-m-d H:i:s')
                        ]);
                    }
                }

                log_activity($currentUser['id'], 'ویرایش قرارداد مضاربت', "ویرایش مشخصات و حساب مضاربت #{$cId} («{$partnerName}»)");
                set_flash(__t('Mudaraba contract details updated successfully.', 'مشخصات و حساب قرارداد مضاربت با موفقیت ذخیره و به‌روزرسانی گردید.', 'د مضاربت تړون معلومات په بریالیتوب سم شول.'), 'success');
                redirect(BASE_URL . 'modules/mudaraba.php');
            } catch (Exception $e) {
                set_flash(__t('Error editing contract: ', 'خطا در ویرایش قرارداد: ', 'د تړون په سمون کې تېروتنه: ') . $e->getMessage(), 'danger');
                redirect(BASE_URL . 'modules/mudaraba.php');
            }
        }
    }
}

// 7. Handle Bulk Delete Mudaraba Contracts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete_contracts') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash(__t('Security session error!', 'خطای امنیتی نشست!', 'د ناستې امنیتي تېروتنه!'), 'danger');
        redirect(BASE_URL . 'modules/mudaraba.php');
    }

    $selectedIds = $_POST['selected_ids'] ?? [];
    if (!is_array($selectedIds) || empty($selectedIds)) {
        set_flash(__t('Please select at least one contract to delete.', 'لطفاً حداقل یک قرارداد مضاربت را برای حذف گروهی انتخاب نمایید.', 'لږترلږه یو تړون وټاکئ.'), 'warning');
        redirect(BASE_URL . 'modules/mudaraba.php');
    }

    $cleanIds = array_filter(array_map('intval', $selectedIds), function($id) { return $id > 0; });
    if (empty($cleanIds)) {
        set_flash(__t('Invalid contract IDs selected.', 'شناسه‌های انتخاب شده نامعتبر می‌باشند.', 'ناسم پیژندونه.'), 'danger');
        redirect(BASE_URL . 'modules/mudaraba.php');
    }

    try {
        $pdo->beginTransaction();
        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        
        $unlinkStmt = $pdo->prepare("UPDATE stock SET mudaraba_id = NULL WHERE mudaraba_id IN ($placeholders)");
        $unlinkStmt->execute($cleanIds);

        $delStmt = $pdo->prepare("DELETE FROM mudaraba_contracts WHERE id IN ($placeholders)");
        $delStmt->execute($cleanIds);
        $deletedCount = $delStmt->rowCount();

        $pdo->commit();
        log_activity($currentUser['id'], 'حذف گروهی قراردادهای مضاربت', "حذف تعداد {$deletedCount} قرارداد مضاربت به صورت گروهی");
        set_flash(sprintf(__t('%s Mudaraba contract(s) deleted successfully.', 'تعداد %s قرارداد مضاربت با موفقیت حذف گردیدند.', '%s د مضاربت تړونونه ړنګ شول.'), ($currentLang === 'en' ? $deletedCount : to_persian_num($deletedCount))), 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash(__t('Error bulk deleting contracts: ', 'خطا در حذف گروهی قراردادها: ', 'د تړونونو په ړنګولو کې تېروتنه: ') . $e->getMessage(), 'danger');
    }
    redirect(BASE_URL . 'modules/mudaraba.php');
}

// Fetch all Mudaraba contracts
$contracts = $pdo->query("SELECT * FROM mudaraba_contracts ORDER BY id DESC")->fetchAll();

// Calculate aggregate metrics strictly isolated for Mudaraba
$totalActiveCapital  = 0;
$totalMudarabaSales  = 0;
$totalMudarabaProfit = 0;
$totalPartnerShare   = 0;
$totalShopShare      = 0;
$totalPaidToPartners = 0;

foreach ($contracts as $c) {
    if ($c['status'] === 'active') {
        $totalActiveCapital += (float)$c['capital_amount'];
    }
    $totalMudarabaSales  += (float)$c['total_sales_amount'];
    $totalMudarabaProfit += (float)$c['total_profit_amount'];
    
    $pShare = ((float)$c['total_profit_amount'] * (float)$c['partner_profit_rate']) / 100;
    $sShare = ((float)$c['total_profit_amount'] * (float)$c['shop_profit_rate']) / 100;
    
    $totalPartnerShare   += $pShare;
    $totalShopShare      += $sShare;
    $totalPaidToPartners += (float)$c['paid_profit_to_partner'];
}

$pendingPartnerPayout = max(0, $totalPartnerShare - $totalPaidToPartners);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
        <div>
            <h1 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-handshake-angle text-primary"></i>
                <?= __t('Dedicated Mudaraba Account (Foreign Sourcing & Goods Investment)', 'حساب اختصاصی مضاربت (خریداری و فروش کالای خارجی شرکتی)', 'د مضاربت ځانګړی حساب (بهرني توکي او پانګونه)') ?>
            </h1>
            <p style="color: #64748b; font-size: 13px; margin: 4px 0 0 0;">
                <?= __t('Log partner investments, import foreign electronics, auto-sync goods to warehouse, and distribute Shariah-compliant profits.', 'ثبت سرمایه شریک، خریداری کالا از خارج، ثبت مستقیم در گدام با برچسب اختصاصی، و تقسیم مفاد شرعی', 'د شریک پانګه، د بهرنیو توکو اخیستل او په ګودام کې ثبتول.') ?>
            </p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button onclick="document.getElementById('addContractModal').style.display='flex'" class="btn btn-primary" style="padding: 9px 18px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #1e40af, #2563eb); border: none; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);">
                <i class="fa-solid fa-file-circle-plus" style="font-size: 15px;"></i>
                <span><?= __t('New Mudaraba Contract & Goods Entry', 'عقد قرارداد جدید و ثبت مال در گدام', 'نوی قرارداد او په ګودام کې مال ثبتول') ?></span>
            </button>
            <a href="<?= BASE_URL ?>modules/stock.php?filter=mudaraba" class="btn" style="padding: 9px 16px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; background: #ecfdf5; color: #065f46; border: 1.5px solid #a7f3d0; box-shadow: 0 2px 6px rgba(16, 185, 129, 0.1);">
                <i class="fa-solid fa-boxes-stacked" style="color: #059669; font-size: 15px;"></i>
                <span><?= __t('View Mudaraba Stock in Warehouse', 'مشاهده مال‌های مضاربت در گدام', 'په ګودام کې د مضاربت توکي کتل') ?></span>
            </a>
            <a href="<?= BASE_URL ?>modules/partners.php" class="btn" style="padding: 9px 16px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; background: #f8fafc; color: #334155; border: 1.5px solid #cbd5e1;">
                <i class="fa-solid fa-user-group" style="color: #475569; font-size: 15px;"></i>
                <span><?= __t('General Store Partners', 'شرکاء عمومی دکان', 'د هټۍ عمومي شریکان') ?></span>
            </a>
        </div>
    </div>

    <!-- MUDARABA EXCLUSIVE METRICS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: linear-gradient(145deg, #ffffff, #f0fdf4); border: 1.5px solid #bbf7d0; border-radius: 14px; padding: 18px; box-shadow: 0 4px 12px rgba(22, 101, 52, 0.05); position: relative; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 12.5px; color: #166534; font-weight: 700;"><?= __t('Total Active Mudaraba Capital', 'مجموع سرمایه فعال مضاربت', 'ټوله فعاله مضاربت پانګه') ?></span>
                <div style="width: 36px; height: 36px; background: #dcfce7; color: #15803d; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px;">
                    <i class="fa-solid fa-vault"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: #15803d;"><?= format_money($totalActiveCapital) ?></div>
            <div style="font-size: 11.5px; color: #166534; margin-top: 6px;"><?= __t('Dedicated capital from foreign partners', 'سرمایه اختصاصی شرکاء خارجی', 'د بهرنیو شریکانو پانګه') ?></div>
        </div>

        <div style="background: linear-gradient(145deg, #ffffff, #eff6ff); border: 1.5px solid #bfdbfe; border-radius: 14px; padding: 18px; box-shadow: 0 4px 12px rgba(29, 78, 216, 0.05); position: relative; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 12.5px; color: #1e40af; font-weight: 700;"><?= __t('Mudaraba Total Goods Revenue', 'مجموع فروشات اجناس مضاربت', 'د توکو ټول پلور') ?></span>
                <div style="width: 36px; height: 36px; background: #dbeafe; color: #1d4ed8; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px;">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: #1d4ed8;"><?= format_money($totalMudarabaSales) ?></div>
            <div style="font-size: 11.5px; color: #1e40af; margin-top: 6px;"><?= __t('Auto-updated upon sales invoice completion', 'ثبت خودکار هنگام صدور بل فروش', 'د بل پر مهال اتومات ثبتېږي') ?></div>
        </div>

        <div style="background: linear-gradient(145deg, #ffffff, #faf5ff); border: 1.5px solid #e9d5ff; border-radius: 14px; padding: 18px; box-shadow: 0 4px 12px rgba(126, 34, 206, 0.05); position: relative; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 12.5px; color: #6b21a8; font-weight: 700;"><?= __t('Partners Profit Share', 'سهم مفاد شرکاء مضاربت', 'د شریکانو د ګټې ونډه') ?></span>
                <div style="width: 36px; height: 36px; background: #f3e8ff; color: #7e22ce; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px;">
                    <i class="fa-solid fa-sack-dollar"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: #7e22ce;"><?= format_money($totalPartnerShare) ?></div>
            <div style="font-size: 11.5px; color: #6b21a8; margin-top: 6px;"><?= __t('Unsettled partner balance: ', 'مانده تسویه نشده: ', 'پاتې نا تصفیه شوې: ') ?><strong style="color: <?= $pendingPartnerPayout > 0 ? '#b91c1c' : '#15803d' ?>;"><?= format_money($pendingPartnerPayout) ?></strong></div>
        </div>

        <div style="background: linear-gradient(145deg, #ffffff, #fffbeb); border: 1.5px solid #fde68a; border-radius: 14px; padding: 18px; box-shadow: 0 4px 12px rgba(180, 83, 9, 0.05); position: relative; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 12.5px; color: #92400e; font-weight: 700;"><?= __t('Store Net Profit from Mudaraba', 'سهم مفاد خالص دکان از مضاربت', 'له مضاربت څخه د هټۍ خالص ګټه') ?></span>
                <div style="width: 36px; height: 36px; background: #fef3c7; color: #b45309; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px;">
                    <i class="fa-solid fa-coins"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: #b45309;"><?= format_money($totalShopShare) ?></div>
            <div style="font-size: 11.5px; color: #92400e; margin-top: 6px;"><?= __t('Realized manager & marketing share', 'درآمد قطعی حاصل از عامل و فروشندگی', 'د هټۍ د کار او پلور ګټه') ?></div>
        </div>
    </div>

    <!-- CONTRACTS TABLE -->
    <div style="margin-top: 20px;">
        <h2 style="font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-list-check text-primary"></i> <?= __t('Mudaraba Contracts & Warehouse Inventory Records', 'لیست قراردادهای مضاربت و اجناس ثبت‌شده در گدام', 'د مضاربت تړونونه او په ګودام کې ثبت شوي توکي') ?>
        </h2>

        <!-- BULK SELECTION ACTION BAR -->
        <div id="mudarabaBulkActionBar" class="card" style="display: none; background: #eff6ff; border: 1.5px solid #3b82f6; margin-bottom: 16px; padding: 12px 18px; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.12);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <span style="font-weight: 800; color: #1e3a8a; font-size: 14px;">
                        <i class="fa-solid fa-square-check text-blue-600 ml-1"></i> <span id="mudarabaSelectedCountText">0</span> <?= __t('contracts selected', 'قرارداد مضاربت انتخاب گردید', 'ټاکل شوي تړونونه') ?>
                    </span>
                    <button type="button" onclick="selectAllMudarabaRows(true)" class="btn btn-sm btn-secondary" style="background: #ffffff;">
                        <i class="fa-solid fa-list-check ml-1"></i> <?= __t('Select All', 'انتخاب همه', 'ټول وټاکئ') ?>
                    </button>
                    <button type="button" onclick="selectAllMudarabaRows(false)" class="btn btn-sm btn-secondary" style="background: #ffffff;">
                        <i class="fa-solid fa-xmark ml-1"></i> <?= __t('Deselect All', 'لغو انتخاب‌ها', 'ټاکنه لغوه کړئ') ?>
                    </button>
                </div>
                <div>
                    <button type="button" onclick="confirmBulkDeleteMudaraba()" class="btn btn-danger btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                        <i class="fa-solid fa-trash-can"></i> <?= __t('Delete Selected Contracts', 'حذف قراردادهای انتخاب شده', 'ټاکل شوي تړونونه ړنګ کړئ') ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Hidden Form for Bulk Deletion of Mudaraba Contracts -->
        <form id="mudarabaBulkDeleteForm" method="POST" action="" style="display: none;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="bulk_delete_contracts">
            <div id="mudarabaBulkDeleteInputsContainer"></div>
        </form>

        <div class="table-responsive">
            <table class="table" style="min-width: 1200px;">
                <thead>
                    <tr>
                        <th style="width: 38px; text-align: center;">
                            <input type="checkbox" id="selectAllMudarabaHeader" onchange="toggleAllMudaraba(this)" title="<?= __t('Select All', 'انتخاب همه', 'ټول ټاکل') ?>">
                        </th>
                        <th style="width: 40px; text-align: center;">#</th>
                        <th style="min-width: 140px; white-space: nowrap;"><?= __t('Investor Partner', 'شریک سرمایه‌گذار', 'پانګوال شریک') ?></th>
                        <th style="min-width: 220px; white-space: nowrap;"><?= __t('Product Specs & Project Title', 'شرح کالا و مشخصات پروژه', 'د توکو مشخصات او پروژه') ?></th>
                        <th style="text-align: center; white-space: nowrap;"><?= __t('Origin Country', 'کشور مبدا', 'اصلي هېواد') ?></th>
                        <th style="text-align: center; white-space: nowrap;"><?= __t('Initial Capital', 'سرمایه اولیه', 'لومړنۍ پانګه') ?></th>
                        <th style="text-align: center; white-space: nowrap;"><?= __t('Profit Ratio (Partner / Shop)', 'نسبت مفاد (شریک / دکان)', 'د ګټې نسبت (شریک / هټۍ)') ?></th>
                        <th style="text-align: center; white-space: nowrap;"><?= __t('Sales to Date', 'فروش تا اکنون', 'تر دې دمه پلور') ?></th>
                        <th style="text-align: center; white-space: nowrap;"><?= __t('Realized Profit', 'مفاد تحقق‌یافته', 'ترلاسه شوې ګټه') ?></th>
                        <th style="text-align: center; white-space: nowrap;"><?= __t('Partner Share / Paid', 'سهم شریک / پرداختی', 'د شریک ونډه / ورکړل شوې') ?></th>
                        <th style="text-align: center; white-space: nowrap;"><?= __t('Status', 'وضعیت', 'حالت') ?></th>
                        <th style="text-align: center; min-width: 210px; white-space: nowrap;"><?= __t('Actions', 'عملیات', 'کړنې') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contracts)): ?>
                        <tr>
                            <td colspan="12" style="text-align: center; padding: 36px 12px; color: #94a3b8;">
                                <i class="fa-solid fa-handshake-slash" style="font-size: 32px; display: block; margin-bottom: 8px; color: #cbd5e1;"></i>
                                <?= __t('No Mudaraba contracts registered yet. Click "New Mudaraba Contract" above to register your first partnership.', 'تاکنون هیچ قرارداد مضاربتی ثبت نشده است. با کلیک بر روی «عقد قرارداد جدید و ثبت مال در گدام» اولین سرمایه‌گذاری را ثبت نمایید.', 'تر اوسه هېڅ تړون نه دی ثبت شوی. لومړی تړون ثبت کړئ.') ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($contracts as $idx => $c): 
                            $pShare = ((float)$c['total_profit_amount'] * (float)$c['partner_profit_rate']) / 100;
                            $remPShare = max(0, $pShare - (float)$c['paid_profit_to_partner']);
                            
                            $stkStmt = $pdo->prepare("SELECT s.*, c.name as category_name 
                                                      FROM stock s 
                                                      LEFT JOIN categories c ON s.category_id = c.id 
                                                      WHERE s.mudaraba_id = :mid 
                                                      ORDER BY s.id DESC");
                            $stkStmt->execute([':mid' => $c['id']]);
                            $cItems = $stkStmt->fetchAll();
                        ?>
                            <tr style="background: #ffffff;">
                                <td style="text-align: center;">
                                    <input type="checkbox" class="mudaraba-chk" value="<?= $c['id'] ?>" onchange="updateMudarabaBulkBar()">
                                </td>
                                <td style="text-align: center; font-weight: 700; color: #64748b;"><?= $currentLang === 'en' ? ($idx + 1) : to_persian_num($idx + 1) ?></td>
                                <td>
                                    <div style="font-weight: 700; color: #0f172a; font-size: 14px;">
                                        <?= sanitize($c['partner_name']) ?>
                                    </div>
                                    <?php if (!empty($c['phone'])): ?>
                                        <div style="font-size: 11.5px; color: #64748b; direction: ltr; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>; margin-top: 2px;">
                                            <i class="fa-solid fa-phone" style="font-size: 10px;"></i> <?= sanitize($c['phone']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="min-width: 220px;">
                                    <div style="font-weight: 800; color: #0369a1; font-size: 14px; margin-bottom: 4px;">
                                        <?= sanitize($c['project_title']) ?>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <div style="font-size: 12px; color: #475569; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa-regular fa-calendar-check text-slate-400"></i>
                                            <span><?= __t('Start Date:', 'تاریخ آغاز:', 'د پیل نېټه:') ?></span>
                                            <strong style="color: #0f172a; direction: ltr; font-family: inherit;"><?= $currentLang === 'en' ? $c['start_date'] : to_persian_num($c['start_date']) ?></strong>
                                        </div>
                                        <div>
                                            <button type="button" onclick="toggleContractStock(<?= $c['id'] ?>)" class="btn btn-sm btn-secondary" style="padding: 2px 8px; font-size: 11px; white-space: nowrap; background: #f8fafc; border: 1px solid #cbd5e1;">
                                                <i class="fa-solid fa-boxes-stacked ml-1 text-primary"></i> <?= sprintf(__t('Warehouse Items (%s)', 'اجناس در گدام (%s)', 'په ګودام کې توکي (%s)'), ($currentLang === 'en' ? count($cItems) : to_persian_num(count($cItems)))) ?>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <span class="badge" style="background: #f1f5f9; color: #334155; font-size: 12px;">
                                        <i class="fa-solid fa-globe ml-1 text-primary"></i> <?= sanitize(display_country_source($c['country_source'] ?: 'Foreign')) ?>
                                    </span>
                                </td>
                                <td style="text-align: center; font-weight: 800; color: #166534; font-size: 14px; white-space: nowrap;">
                                    <?= format_money($c['capital_amount'], false) ?>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <span style="font-weight: 700; color: #0f172a;">
                                        <?= $currentLang === 'en' ? ((float)$c['partner_profit_rate'] . '% / ' . (float)$c['shop_profit_rate'] . '%') : (to_persian_num((float)$c['partner_profit_rate']) . '٪ / ' . to_persian_num((float)$c['shop_profit_rate']) . '٪') ?>
                                    </span>
                                </td>
                                <td style="text-align: center; font-weight: 700; color: #0f172a; font-size: 13px; white-space: nowrap;">
                                    <?= format_money($c['total_sales_amount'], false) ?>
                                </td>
                                <td style="text-align: center; font-weight: 800; color: #0284c7; font-size: 13.5px; white-space: nowrap;">
                                    <?= format_money($c['total_profit_amount'], false) ?>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <div style="font-weight: 800; color: #7e22ce; font-size: 13px;">
                                        <?= __t('Partner Profit: ', 'سهم سود: ', 'د ګټې ونډه: ') ?><?= format_money($pShare, false) ?>
                                    </div>
                                    <div style="font-size: 11.5px; color: #15803d; font-weight: 700; margin-top: 2px;">
                                        <?= __t('Paid Profit: ', 'سود پرداختی: ', 'ورکړل شوې ګټه: ') ?><?= format_money($c['paid_profit_to_partner'], false) ?>
                                    </div>
                                    <?php if ((float)($c['returned_capital'] ?? 0) > 0): ?>
                                        <div style="font-size: 11px; color: #2563eb; font-weight: 700; margin-top: 1px;">
                                            <?= __t('Principal Returned: ', 'اصل مسترد: ', 'بېرته ورکړل شوې: ') ?><?= format_money($c['returned_capital'], false) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php 
                                    $remCapital = max(0, (float)$c['capital_amount'] - (float)($c['returned_capital'] ?? 0));
                                    $remProfit = max(0, $pShare - (float)($c['paid_profit_to_partner'] ?? 0));
                                    $totalRem = $remCapital + $remProfit;
                                    ?>
                                    <div style="font-size: 11.5px; color: <?= $totalRem > 0 ? '#b91c1c' : '#166534' ?>; font-weight: 800; margin-top: 3px; background: <?= $totalRem > 0 ? '#fef2f2' : '#f0fdf4' ?>; border-radius: 4px; padding: 2px 4px;">
                                        <?= __t('Total Due: ', 'طلب کل: ', 'ټولټال طلب: ') ?><?= format_money($totalRem, false) ?>
                                    </div>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <?php if ($c['status'] === 'active'): ?>
                                        <span class="badge badge-success"><?= __t('Active Contract', 'جاری و فعال', 'فعال تړون') ?></span>
                                    <?php elseif ($c['status'] === 'settled'): ?>
                                        <span class="badge badge-secondary"><?= __t('Fully Settled', 'تسویه شده', 'تصفیه شوی') ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-danger"><?= sanitize(display_mudaraba_status($c['status'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 4px; justify-content: center; flex-wrap: wrap;">
                                        <!-- Edit Contract Button -->
                                        <button type="button" class="btn btn-sm btn-secondary" style="background: #f1f5f9; color: #334155; padding: 4px 8px;"
                                                data-contract="<?= htmlspecialchars(json_encode([
                                                    'id' => (int)$c['id'],
                                                    'partner_name' => $c['partner_name'],
                                                    'phone' => $c['phone'] ?? '',
                                                    'project_title' => $c['project_title'],
                                                    'country_source' => $c['country_source'] ?? '',
                                                    'capital_amount' => (float)$c['capital_amount'],
                                                    'partner_profit_rate' => (float)$c['partner_profit_rate'],
                                                    'shop_profit_rate' => (float)$c['shop_profit_rate'],
                                                    'paid_profit_to_partner' => (float)($c['paid_profit_to_partner'] ?? 0),
                                                    'returned_capital' => (float)($c['returned_capital'] ?? 0),
                                                    'total_sales_amount' => (float)($c['total_sales_amount'] ?? 0),
                                                    'total_profit_amount' => (float)($c['total_profit_amount'] ?? 0),
                                                    'start_date' => $c['start_date'] ?? '',
                                                    'settlement_date' => $c['settlement_date'] ?? '',
                                                    'status' => $c['status'] ?? 'active',
                                                    'notes' => $c['notes'] ?? ''
                                                ]), ENT_QUOTES, 'UTF-8') ?>"
                                                onclick="openEditContractModalFromBtn(this)"
                                                title="<?= __t('Edit Contract Details & Accounts', 'ویرایش قرارداد و حساب مالی', 'د تړون او حساب سمول') ?>">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>

                                        <!-- Add More Stock to this contract -->
                                        <button type="button" class="btn btn-sm btn-secondary" style="background: #e0f2fe; color: #0369a1; border-color: #bae6fd; padding: 4px 8px;"
                                                data-add-stock="<?= htmlspecialchars(json_encode([
                                                    'id' => (int)$c['id'],
                                                    'partner_name' => $c['partner_name'],
                                                    'country_source' => $c['country_source'] ?? ''
                                                ]), ENT_QUOTES, 'UTF-8') ?>"
                                                onclick="openAddStockModalFromBtn(this)" title="<?= __t('Add New Item to Contract', 'افزودن جنس جدید به این قرارداد', 'تړون ته نوی جنس اضافه کړئ') ?>">
                                            <i class="fa-solid fa-plus"></i> <?= __t('Item', 'مال', 'مال') ?>
                                        </button>

                                        <!-- Update Sales Progress Button -->
                                        <button type="button" class="btn btn-sm btn-primary" style="padding: 4px 8px;"
                                                data-progress="<?= htmlspecialchars(json_encode([
                                                    'id' => (int)$c['id'],
                                                    'project_title' => $c['project_title'],
                                                    'partner_rate' => (float)$c['partner_profit_rate'],
                                                    'shop_rate' => (float)$c['shop_profit_rate']
                                                ]), ENT_QUOTES, 'UTF-8') ?>"
                                                onclick="openProgressModalFromBtn(this)" title="<?= __t('Log Manual Sales & Profit', 'ثبت دستی فروش و مفاد', 'پلور او ګټه ثبت کړئ') ?>">
                                            <i class="fa-solid fa-chart-line"></i>
                                        </button>

                                        <!-- Payout to Partner Button -->
                                        <button type="button" class="btn btn-sm btn-secondary" style="background: #10b981; color: white; padding: 4px 8px;"
                                                data-payout="<?= htmlspecialchars(json_encode([
                                                    'id' => (int)$c['id'],
                                                    'partner_name' => $c['partner_name'],
                                                    'project_title' => $c['project_title'],
                                                    'rem_profit' => (float)$remPShare,
                                                    'rem_cap' => max(0, (float)$c['capital_amount'] - (float)($c['returned_capital'] ?? 0)),
                                                    'paid_profit' => (float)($c['paid_profit_to_partner'] ?? 0),
                                                    'returned_capital' => (float)($c['returned_capital'] ?? 0)
                                                ]), ENT_QUOTES, 'UTF-8') ?>"
                                                onclick="openPayoutModalFromBtn(this)" title="<?= __t('Payout & Settle with Partner', 'پرداخت و تسویه با شریک', 'له شریک سره تصفیه او پیسې ورکول') ?>">
                                            <i class="fa-solid fa-money-bill-wave"></i>
                                        </button>

                                        <!-- Print Mudaraba Statement Button -->
                                        <a href="<?= BASE_URL ?>print/mudaraba.php?id=<?= $c['id'] ?>" target="_blank" class="btn btn-sm btn-secondary" style="padding: 4px 8px;" title="<?= __t('Print Mudaraba Contract', 'چاپ سند و قرارداد مضاربت', 'د مضاربت سند چاپ کړئ') ?>">
                                            <i class="fa-solid fa-print"></i>
                                        </a>

                                        <!-- Delete Button -->
                                        <form method="POST" action="" style="display: inline; margin: 0;" onsubmit="return confirm('<?= __t('Are you sure you want to delete this Mudaraba contract? (Warehouse goods will be unlinked from Mudaraba).', 'آیا از حذف این قرارداد مضاربت مطمئن هستید؟ (اجناس گدام از مضاربت جدا می‌شوند)', 'ایا باوري یاست چې دا تړون ړنګ کړئ؟') ?>');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_contract">
                                            <input type="hidden" name="contract_id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" style="padding: 4px 8px;" title="<?= __t('Delete Contract', 'حذف قرارداد', 'تړون ړنګ کړئ') ?>">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <!-- Expandable Stock Items Table for this Contract -->
                            <tr id="contract_stock_row_<?= $c['id'] ?>" style="display: none; background: #f8fafc;">
                                <td colspan="12" style="padding: 12px 18px;">
                                    <div style="background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.03);">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                            <h4 style="margin: 0; font-size: 13.5px; font-weight: 800; color: #047857; display: flex; align-items: center; gap: 6px;">
                                                <i class="fa-solid fa-boxes-stacked"></i>
                                                <?= sprintf(__t('Warehouse items tied to Mudaraba contract with «%s»:', 'اجناس موجود در گدام مربوط به حساب مضاربت با «%s»:', 'په ګودام کې له «%s» سره د مضاربت اړوند توکي:'), sanitize($c['partner_name'])) ?>
                                            </h4>
                                            <button type="button" onclick="openAddStockModal(<?= $c['id'] ?>, '<?= sanitize(addslashes($c['partner_name'])) ?>', '<?= sanitize(addslashes($c['country_source'])) ?>')" class="btn btn-sm btn-primary" style="font-size: 11.5px; padding: 3px 10px;">
                                                <i class="fa-solid fa-plus ml-1"></i> <?= __t('Register New Item for this Contract', 'ثبت قلم جنس جدید برای این قرارداد', 'د دې تړون لپاره نوی جنس ثبت کړئ') ?>
                                            </button>
                                        </div>

                                        <?php if (empty($cItems)): ?>
                                            <div style="padding: 12px; text-align: center; color: #94a3b8; font-size: 12.5px;">
                                                <?= __t('No inventory items logged for this contract yet. Use button above to register imported goods.', 'هنوز جنسی برای این قرارداد در گدام ثبت نشده است. می‌توانید با دکمه بالا جنس جدید ثبت نمایید.', 'تر اوسه هېڅ جنس نه دی ثبت شوی.') ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table" style="margin: 0; font-size: 12.5px;">
                                                    <thead>
                                                        <tr style="background: #f1f5f9;">
                                                            <th>#</th>
                                                            <th><?= __t('Product Name', 'اسم جنس', 'د توکي نوم') ?></th>
                                                            <th><?= __t('Category', 'دسته‌بندی', 'کټګوري') ?></th>
                                                            <th><?= __t('Specifications', 'مشخصات فنی (حافظه، رنگ، کارتن...)', 'مشخصات') ?></th>
                                                            <th style="text-align: center;"><?= __t('Stock Remaining', 'موجودی فعلی در گدام', 'اوسنۍ موجودي') ?></th>
                                                            <th style="text-align: center;"><?= __t('Cost Price', 'نرخ خرید', 'پېرود نرخ') ?></th>
                                                            <th style="text-align: center;"><?= __t('Selling Price', 'نرخ پیشنهادی فروش', 'د پلور نرخ') ?></th>
                                                            <th style="text-align: center;"><?= __t('Stock Total Value', 'ارزش موجودی گدام', 'د ګودام ارزښت') ?></th>
                                                            <th style="text-align: center;"><?= __t('Location', 'موقعیت در دکان', 'ځای په هټۍ کې') ?></th>
                                                            <th style="text-align: center;"><?= __t('Warehouse Tag', 'برچسب در گدام', 'په ګودام کې نښه') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $subIdx = 1;
                                                        foreach ($cItems as $sItem): 
                                                            $totalItemVal = ((float)$sItem['purchase_price'] + (float)$sItem['expenses']) * (int)$sItem['quantity'];
                                                        ?>
                                                            <tr>
                                                                <td><?= $currentLang === 'en' ? $subIdx++ : to_persian_num($subIdx++) ?></td>
                                                                <td style="font-weight: 700; color: #0f172a;">
                                                                    <?= sanitize($sItem['name']) ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge badge-secondary"><?= sanitize($sItem['category_name'] ? display_category_name($sItem['category_name']) : __t('Uncategorized', 'بدون دسته', 'بې کټګورۍ')) ?></span>
                                                                </td>
                                                                <td style="color: #475569; max-width: 200px;">
                                                                    <?= sanitize($sItem['specifications'] ?: '-') ?>
                                                                </td>
                                                                <td style="text-align: center; font-weight: 800;">
                                                                    <?php if ((int)$sItem['quantity'] <= 0): ?>
                                                                        <span class="badge badge-danger"><?= __t('Sold Out (0)', 'تمام شده (۰)', 'خلاص شوی (۰)') ?></span>
                                                                    <?php else: ?>
                                                                        <span style="color: #047857; font-size: 13.5px;"><?= display_qty_unit($sItem['quantity']) ?></span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td style="text-align: center;"><?= format_money($sItem['purchase_price'], false) ?></td>
                                                                <td style="text-align: center; color: #0284c7; font-weight: 700;"><?= format_money($sItem['sale_price'], false) ?></td>
                                                                <td style="text-align: center; font-weight: 800; color: #166534;"><?= format_money($totalItemVal, false) ?></td>
                                                                <td style="text-align: center;"><?= sanitize(display_stock_location($sItem['warehouse_location'] ?: '-')) ?></td>
                                                                <td style="text-align: center;">
                                                                    <span class="badge" style="background: #ecfdf5; color: #047857; border: 1px solid #6ee7b7; font-size: 11px;">
                                                                        <?= sprintf(__t('Mudaraba with %s', 'حساب مضاربت با %s', 'له %s سره مضاربت'), sanitize($c['partner_name'])) ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL 1: ADD MUDARABA CONTRACT & AUTO SAVE GOODS TO STOCK -->
<div id="addContractModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; justify-content: center; align-items: center; padding: 16px;">
    <div style="background: #ffffff; border-radius: 16px; max-width: 680px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); padding: 24px; position: relative; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-handshake text-primary"></i> <?= __t('New Mudaraba Contract & Goods Intake', 'عقد قرارداد جدید مضاربت و ثبت مال در گدام', 'د مضاربت نوی تړون او مال ثبتول') ?>
            </h3>
            <button type="button" onclick="document.getElementById('addContractModal').style.display='none'" style="background: none; border: none; font-size: 18px; color: #64748b; cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_mudaraba">

            <!-- Section 1: Partner Info -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 14px;">
                <div style="font-weight: 800; font-size: 13px; color: #1e293b; margin-bottom: 8px;">
                    <i class="fa-solid fa-user-tie text-primary ml-1"></i> <?= __t('1. Investor Partner Profile (Rabb-ul-Mal):', '۱. مشخصات شریک سرمایه‌گذار (رب‌المال):', '۱. د پانګوال شریک مشخصات:') ?>
                </div>
                <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Partner Name: *', 'نام شریک: *', 'د شریک نوم: *') ?></label>
                        <input type="text" name="partner_name" class="form-control" required placeholder="<?= __t('e.g. Haji Habibullah', 'مثلاً: حاجی نصرت‌الله', 'لکه: حاجي نصرت الله') ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Phone Number:', 'شماره تماس:', 'د تلیفون شمېره:') ?></label>
                        <input type="text" name="phone" class="form-control" placeholder="07xxxxxxxx" style="direction: ltr; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
                    </div>
                </div>
            </div>

            <!-- Section 2: Goods & Warehouse Specifications -->
            <div style="background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 8px; padding: 14px; margin-bottom: 14px;">
                <div style="font-weight: 800; font-size: 13px; color: #065f46; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">
                    <span><i class="fa-solid fa-boxes-stacked ml-1"></i> <?= __t('2. Purchased Commercial Goods (Auto-saved to Warehouse):', '۲. مشخصات مال خریداری‌شده (ثبت مستقیم در گدام):', '۲. پېرل شوي توکي (مستقیم ګودام ته داخلېږي):') ?></span>
                    <span class="badge" style="background: #dcfce7; color: #166534; font-size: 11px;"><?= __t('Direct Intake into Stock', 'ذخیره مستقیم در گدام دکان', 'مستقیم ګودام ته داخلول') ?></span>
                </div>

                <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Product Name & Model: *', 'نام جنس و مدل: *', 'د جنس نوم او ماډل: *') ?></label>
                        <input type="text" id="m_product_name" name="product_name" class="form-control" required placeholder="<?= __t('e.g. Samsung Galaxy A55 5G or iPhone 13', 'مثلاً: Samsung Galaxy A55 5G یا iPhone 13 Pro', 'لکه: سامسنګ ګلکسي یا آی فون') ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Warehouse Category:', 'دسته‌بندی در گدام:', 'په ګودام کې کټګوري:') ?></label>
                        <select name="category_id" class="form-control">
                            <option value=""><?= __t('-- Uncategorized --', '-- بدون دسته‌بندی --', '-- بې کټګورۍ --') ?></option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= sanitize(display_category_name($cat['name'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Technical Specifications (Storage, RAM, Color, Battery Health, Packaging):', 'مشخصات جنس (حافظه، رم، رنگ، سلامت بتری، کارتن و...):', 'د توکي مشخصات (حافظه، رنګ، کارتن...):') ?></label>
                    <textarea name="specifications" class="form-control" rows="2" placeholder="<?= __t('e.g. 256GB / 8GB RAM - Navy Blue - Brand new with official accessories', 'مثلاً: 256GB / 8GB RAM - رنگ سرمه‌ای - با کارتن و لوازم اورجینال', 'لکه: ۲۵۶ جي بي، رنګ، کارتن...') ?>"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Quantity Purchased: *', 'تعداد خریداری شده: *', 'د پېرل شوو شمېر: *') ?></label>
                        <input type="number" id="m_quantity" name="quantity" class="form-control" min="1" value="1" required oninput="recalcMudarabaCapital()">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Unit Cost Price (%s): *', 'نرخ خرید فی دانه (%s): *', 'د یوه دانې د پېرود نرخ (%s): *'), get_currency_label()) ?></label>
                        <input type="number" step="any" id="m_purchase_price" name="purchase_price" class="form-control" min="0" required placeholder="0" oninput="recalcMudarabaCapital()">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Target Sale Price (%s):', 'نرخ فروش فی دانه (%s):', 'د پلور نرخ (%s):'), get_currency_label()) ?></label>
                        <input type="number" step="any" name="sale_price" class="form-control" min="0" placeholder="0">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Freight & Customs Fee (%s):', 'مصارف سرجمع فی دانه (%s):', 'ګمرک او لګښت (%s):'), get_currency_label()) ?></label>
                        <input type="number" step="any" id="m_expenses" name="expenses" class="form-control" min="0" value="0" placeholder="0" oninput="recalcMudarabaCapital()">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Country of Origin:', 'کشور مبدأ خریداری:', 'اصلي هېواد:') ?></label>
                        <select name="country_source" class="form-control">
                            <option value="دوبی (امارات)"><?= __t('Dubai (UAE)', 'دوبی (امارات)', 'دوبۍ (امارات)') ?></option>
                            <option value="چین"><?= __t('China', 'چین', 'چین') ?></option>
                            <option value="ترکیه"><?= __t('Turkey', 'ترکیه', 'ترکیه') ?></option>
                            <option value="ایران"><?= __t('Iran', 'ایران', 'ایران') ?></option>
                            <option value="پاکستان"><?= __t('Pakistan', 'پاکستان', 'پاکستان') ?></option>
                            <option value="جاپان"><?= __t('Japan', 'جاپان', 'جاپان') ?></option>
                            <option value="سایر"><?= __t('Other Countries', 'سایر کشورها', 'نور هېوادونه') ?></option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Storage Shelf / Cabinet:', 'موقعیت در گدام/دکان:', 'ځای په ګودام کې:') ?></label>
                        <input type="text" name="warehouse_location" class="form-control" placeholder="<?= __t('e.g. Cabinet 1, Shelf B', 'قفسه A، الماری ۱...', 'المارۍ ۱...') ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 10px; margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;"><?= __t('Barcode or Serial / IMEI (Optional):', 'بارکد یا سریال / IMEI جنس (اختیاری):', 'بارکوډ یا سریال شمېره:') ?></label>
                    <input type="text" name="barcode" class="form-control" placeholder="Barcode / IMEI">
                </div>
            </div>

            <!-- Section 3: Financials & Shariah Profit Split -->
            <div style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 8px; padding: 14px; margin-bottom: 14px;">
                <div style="font-weight: 800; font-size: 13px; color: #581c87; margin-bottom: 8px;">
                    <i class="fa-solid fa-scale-balanced text-primary ml-1"></i> <?= __t('3. Mudaraba Capital & Shariah Profit Split Ratio:', '۳. سرمایه مضاربت و تقسیم مفاد شرعی:', '۳. د مضاربت پانګه او د ګټې شرعي وېش:') ?>
                </div>

                <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Total Partner Capital Amount (%s): *', 'مجموع سرمایه نقدی شریک (%s): *', 'د شریک ټوله پانګه (%s): *'), get_currency_label()) ?></label>
                        <input type="number" step="any" id="m_capital_amount" name="capital_amount" class="form-control" required style="font-weight: 800; color: #166534; font-size: 14px;">
                        <small style="color: #64748b; font-size: 11px;"><?= __t('* Calculated automatically from quantity x total unit cost.', '* خودبخود از ضرب تعداد در تمام‌شد جنس محاسبه می‌گردد.', '* اتومات له ټولټال لګښت څخه محاسبه کېږي.') ?></small>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Mudaraba Start Date:', 'تاریخ شروع مضاربت:', 'د پیل نېټه:') ?></label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div style="background: #ffffff; border: 1px solid #d8b4fe; border-radius: 8px; padding: 10px;">
                    <span style="font-size: 12px; font-weight: 700; color: #6b21a8; display: block; margin-bottom: 6px;">
                        <?= __t('Agreed Profit Sharing Ratio from Goods Sales:', 'نسبت توافقی تقسیم سود حاصل از فروش کالا:', 'د پلور د ګټې توافقي وېش:') ?>
                    </span>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <span style="font-size: 11.5px; color: #64748b;"><?= __t('Investor Partner Share (Rabb-ul-Mal):', 'سهم سود شریک (رب‌المال):', 'د شریک د ګټې سلنه:') ?></span>
                            <div style="display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                                <input type="number" id="mudaraba_p_rate" name="partner_profit_rate" class="form-control" value="50" min="0" max="100" style="text-align: center; font-weight: bold;" oninput="document.getElementById('mudaraba_s_rate').value = Math.max(0, 100 - parseFloat(this.value || 0));">
                                <span style="font-weight: bold;">%</span>
                            </div>
                        </div>
                        <div>
                            <span style="font-size: 11.5px; color: #64748b;"><?= __t('Store Manager Share (Mudarib):', 'سهم سود دکان (عامل / مضارب):', 'د هټۍ د ګټې سلنه:') ?></span>
                            <div style="display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                                <input type="number" id="mudaraba_s_rate" name="shop_profit_rate" class="form-control" value="50" min="0" max="100" style="text-align: center; font-weight: bold;">
                                <span style="font-weight: bold;">%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Special Terms & Agreement Notes:', 'توضیحات و شرایط ویژه قرارداد:', 'د تړون ځانګړي شرایط:') ?></label>
                <textarea name="notes" class="form-control" rows="2" placeholder="<?= __t('Optional notes regarding settlement deadlines, currency exchange rates...', 'توضیحات اختیاری در مورد توافقات، نرخ ارز، نحوه تصفیه...', 'د تصفیې او نرخونو په اړه یادښت...') ?>"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="document.getElementById('addContractModal').style.display='none'" class="btn btn-secondary">
                    <?= __t('Cancel', 'انصراف', 'لغوه') ?>
                </button>
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
                    <i class="fa-solid fa-check ml-1"></i> <?= __t('Save Contract & Register Goods to Stock', 'ثبت قرارداد و ذخیره جنس در گدام', 'تړون او توکي په ګودام کې ثبت کړئ') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: ADD EXTRA GOODS TO EXISTING CONTRACT -->
<div id="addStockModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; justify-content: center; align-items: center; padding: 16px;">
    <div style="background: #ffffff; border-radius: 16px; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); padding: 24px; position: relative; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">
                <i class="fa-solid fa-plus text-primary ml-1"></i> <?= __t('Add Imported Item to Contract: ', 'ثبت جنس جدید برای قرارداد مضاربت: ', 'تړون ته نوی جنس اضافه کړئ: ') ?><span id="add_stock_partner_name" style="color: #0284c7;"></span>
            </h3>
            <button type="button" onclick="document.getElementById('addStockModal').style.display='none'" style="background: none; border: none; font-size: 18px; color: #64748b; cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_contract_item">
            <input type="hidden" id="add_stock_contract_id" name="contract_id" value="">

            <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Product Name & Model: *', 'نام جنس و مدل: *', 'د جنس نوم او ماډل: *') ?></label>
                    <input type="text" name="product_name" class="form-control" required placeholder="<?= __t('e.g. Fast Charger, Battery, Case...', 'مثلاً: شارژر فست، باتری، قاب...', 'لکه: چارجر، بیټرۍ...') ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Category:', 'دسته‌بندی:', 'کټګوري:') ?></label>
                    <select name="category_id" class="form-control">
                        <option value=""><?= __t('-- Uncategorized --', '-- بدون دسته‌بندی --', '-- بې کټګورۍ --') ?></option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= sanitize(display_category_name($cat['name'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Specifications (Color, Model, Serial):', 'مشخصات جنس (رنگ، مدل، سریال):', 'مشخصات (رنګ، ماډل):') ?></label>
                <input type="text" name="specifications" class="form-control" placeholder="<?= __t('Appearance and technical specs', 'مشخصات ظاهری و فنی', 'تخنیکي مشخصات') ?>">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Quantity (units): *', 'تعداد (عدد): *', 'شمېر (دانې): *') ?></label>
                    <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Cost Price (%s): *', 'نرخ خرید (%s): *', 'د پېرود نرخ (%s): *'), get_currency_label()) ?></label>
                    <input type="number" step="any" name="purchase_price" class="form-control" min="0" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Selling Price (%s):', 'نرخ فروش (%s):', 'د پلور نرخ (%s):'), get_currency_label()) ?></label>
                    <input type="number" step="any" name="sale_price" class="form-control" min="0">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Storage Location in Shop:', 'موقعیت در گدام:', 'ځای په ګودام کې:') ?></label>
                    <input type="text" name="warehouse_location" class="form-control" placeholder="<?= __t('Shelf, Cabinet...', 'قفسه، الماری...', 'المارۍ...') ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Purchase Date:', 'تاریخ خریداری:', 'د پېرود نېټه:') ?></label>
                    <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="document.getElementById('addStockModal').style.display='none'" class="btn btn-secondary">
                    <?= __t('Cancel', 'انصراف', 'لغوه') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus ml-1"></i> <?= __t('Add Item to Warehouse', 'افزودن جنس به گدام', 'توکی ګودام ته داخل کړئ') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 3: RECORD SALES PROGRESS -->
<div id="progressModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; justify-content: center; align-items: center; padding: 16px;">
    <div style="background: #ffffff; border-radius: 16px; max-width: 460px; width: 100%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); padding: 24px; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">
                <i class="fa-solid fa-chart-line text-primary ml-1"></i> <?= __t('Log Manual Sales & Profit for Mudaraba', 'ثبت دستی فروش و مفاد مضاربت', 'د پلور او ګټې لاسي ثبتول') ?>
            </h3>
            <button type="button" onclick="document.getElementById('progressModal').style.display='none'" style="background: none; border: none; font-size: 18px; color: #64748b; cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_progress">
            <input type="hidden" id="progress_contract_id" name="contract_id" value="">

            <div style="background: #f8fafc; padding: 10px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; color: #475569;">
                <?= __t('Contract: ', 'قرارداد: ', 'تړون: ') ?><strong id="progress_project_title" style="color: #0284c7;"></strong>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Sales Revenue Added (%s):', 'مبلغ فروش اضافه شده (%s):', 'اضافه شوی پلور (%s):'), get_currency_label()) ?></label>
                <input type="number" step="any" min="0" name="add_sales" class="form-control" required placeholder="0">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Net Profit Realized from this Sale (%s):', 'مفاد خالص تحقق‌یافته از این فروش (%s):', 'له دې پلور خالص ګټه (%s):'), get_currency_label()) ?></label>
                <input type="number" step="any" min="0" id="progress_add_profit" name="add_profit" class="form-control" required placeholder="0" style="color: #0284c7; font-weight: bold;" oninput="recalcProgressSplit();">
            </div>

            <div id="progress_split_box" style="display: none; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px; margin-bottom: 14px; font-size: 12px;">
                <div style="color: #166534; font-weight: 700; margin-bottom: 4px;"><?= __t('Profit split according to contract ratio:', 'تقسیم مفاد بر اساس درصد قرارداد:', 'د تړون له مخې د ګټې وېش:') ?></div>
                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
                    <span><?= __t('Partner Share', 'سهم سرمایه‌گذار', 'د شریک ونډه') ?> (<span id="progress_p_rate">50</span>%): <strong id="progress_p_share" style="color: #7e22ce;">0</strong> <?= get_currency_label() ?></span>
                    <span><?= __t('Store Share', 'سهم دکان', 'د هټۍ ونډه') ?> (<span id="progress_s_rate">50</span>%): <strong id="progress_s_share" style="color: #166534;">0</strong> <?= get_currency_label() ?></span>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="document.getElementById('progressModal').style.display='none'" class="btn btn-secondary">
                    <?= __t('Cancel', 'انصراف', 'لغوه') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus ml-1"></i> <?= __t('Add to Sales Statistics', 'افزودن به آمار فروش', 'د پلور په شمېرو کې ثبت کړئ') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 4: PAYOUT TO PARTNER -->
<div id="payoutModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; justify-content: center; align-items: center; padding: 16px;">
    <div style="background: #ffffff; border-radius: 16px; max-width: 460px; width: 100%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); padding: 24px; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">
                <i class="fa-solid fa-money-bill-wave text-success ml-1"></i> <?= __t('Disburse Payment & Settle with Partner', 'پرداخت و تسویه با شریک مضاربت', 'شریک ته پیسې ورکول او تصفیه کول') ?>
            </h3>
            <button type="button" onclick="document.getElementById('payoutModal').style.display='none'" style="background: none; border: none; font-size: 18px; color: #64748b; cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="payout_partner">
            <input type="hidden" id="payout_contract_id" name="contract_id" value="">

            <div style="background: #f1f5f9; padding: 10px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; color: #334155;">
                <?= __t('Partner: ', 'شریک: ', 'شریک: ') ?><strong id="payout_partner_name"></strong><br>
                <span style="font-size: 11.5px; color: #64748b;">
                    <?= __t('Remaining Profit: ', 'مانده سهم سود: ', 'پاتې ګټه: ') ?><strong id="payout_rem_profit" style="color: #7e22ce;"></strong> <?= get_currency_label() ?> | 
                    <?= __t('Remaining Principal Capital: ', 'مانده اصل سرمایه: ', 'پاتې اصلي پانګه: ') ?><strong id="payout_rem_cap" style="color: #166534;"></strong> <?= get_currency_label() ?>
                </span>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Profit Payout to Partner (%s):', 'پرداخت سود به شریک (%s):', 'شریک ته د ګټې تادیه (%s):'), get_currency_label()) ?></label>
                <input type="number" step="any" min="0" id="payout_profit_inp" name="payout_profit" class="form-control" value="0" oninput="updatePayoutTotal();">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Principal Capital Return (%s):', 'استرداد / بازگرداندن اصل سرمایه (%s):', 'د اصلي پانګې بېرته ورکړه (%s):'), get_currency_label()) ?></label>
                <input type="number" step="any" min="0" id="return_cap_inp" name="return_capital" class="form-control" value="0" oninput="updatePayoutTotal();">
            </div>

            <!-- Preview Total Payout -->
            <div style="background: #ecfdf5; border: 1.5px solid #a7f3d0; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 12.5px; font-weight: 700; color: #065f46;">
                    <i class="fa-solid fa-coins ml-1"></i> <?= __t('Total Payout (Deducted from Store Capital):', 'مجموع پرداختی به شریک (کسر از سرمایه دکان):', 'ټولټال ورکړه (له پانګې کمه شوې):') ?>
                </span>
                <strong id="payout_total_preview" style="font-size: 15px; font-weight: 900; color: #047857;">0 <?= get_currency_label() ?></strong>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Payment / Receipt Date:', 'تاریخ پرداخت / رسید:', 'د ورکړې نېټه:') ?></label>
                <input type="date" name="payout_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px; margin-bottom: 14px; font-size: 12px; color: #166534;">
                <i class="fa-solid fa-shield-check ml-1"></i> <?= __t('Per accounting standards, this disbursement will deduct directly from general store capital to balance ledger cash flow.', 'با ثبت پرداخت، این مبلغ مطابق استاندارد حسابداری مستقیماً از سرمایه عمومی دکان کسر و ترازنامه متوازن می‌شود.', 'دا پیسې به د محاسبې د معیار له مخې د هټۍ له عمومي پانګې کمې شي.') ?>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                    <input type="checkbox" name="mark_settled" value="1">
                    <span><?= __t('Mark contract as fully settled and closed', 'قرارداد کاملاً تسویه و خاتمه یافته علامت‌گذاری شود', 'تړون په بشپړه توګه تصفیه او ختم وټاکئ') ?></span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="document.getElementById('payoutModal').style.display='none'" class="btn btn-secondary">
                    <?= __t('Cancel', 'انصراف', 'لغوه') ?>
                </button>
                <button type="submit" class="btn btn-primary" style="background: #10b981; border-color: #10b981;">
                    <i class="fa-solid fa-check ml-1"></i> <?= __t('Confirm & Disburse Funds', 'ثبت پرداخت وجه', 'ورکړه ثبت کړئ') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 5: EDIT MUDARABA CONTRACT -->
<div id="editContractModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; justify-content: center; align-items: center; padding: 16px;">
    <div style="background: #ffffff; border-radius: 16px; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); padding: 24px; position: relative; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-pen-to-square text-primary"></i> <?= __t('Edit Mudaraba Contract', 'ویرایش قرارداد مضاربت', 'د مضاربت تړون سمول') ?>
            </h3>
            <button type="button" onclick="closeEditContractModal();" style="background: none; border: none; font-size: 18px; color: #64748b; cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_contract">
            <input type="hidden" name="contract_id" id="edit_contract_id" value="0">

            <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Partner Name (Investor): *', 'نام شریک (سرمایه‌گذار): *', 'د شریک نوم: *') ?></label>
                    <input type="text" name="partner_name" id="edit_c_partner_name" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Phone Number:', 'شماره تماس:', 'د تلیفون شمېره:') ?></label>
                    <input type="text" name="phone" id="edit_c_phone" class="form-control" style="direction: ltr; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Project Title & Description: *', 'شرح کالا و عنوان پروژه: *', 'د پروژې نوم او تفصیل: *') ?></label>
                <input type="text" name="project_title" id="edit_c_project_title" class="form-control" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Country of Origin:', 'کشور مبدا:', 'اصلي هېواد:') ?></label>
                    <input type="text" name="country_source" id="edit_c_country_source" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Total Principal Capital (%s): *', 'مقدار کل سرمایه (%s): *', 'ټوله اصلي پانګه (%s): *'), get_currency_label()) ?></label>
                    <input type="number" step="any" name="capital_amount" id="edit_c_capital_amount" class="form-control" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Partner Profit Share (%):', 'سهم مفاد شریک (٪):', 'د شریک د ګټې ونډه (%):') ?></label>
                    <input type="number" step="any" name="partner_profit_rate" id="edit_c_partner_rate" class="form-control" required oninput="document.getElementById('edit_c_shop_rate').value = Math.max(0, 100 - parseFloat(this.value || 0));">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Store Profit Share (%):', 'سهم مفاد دکان (٪):', 'د هټۍ د ګټې ونډه (%):') ?></label>
                    <input type="number" step="any" name="shop_profit_rate" id="edit_c_shop_rate" class="form-control" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Start Date:', 'تاریخ شروع:', 'د پیل نېټه:') ?></label>
                    <input type="date" name="start_date" id="edit_c_start_date" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Settlement Due Date:', 'موعد تسویه:', 'د تصفیې نېټه:') ?></label>
                    <input type="date" name="settlement_date" id="edit_c_settlement_date" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Contract Status:', 'وضعیت قرارداد:', 'د تړون حالت:') ?></label>
                    <select name="status" id="edit_c_status" class="form-control">
                        <option value="active"><?= __t('Active & Operational', 'جاری و فعال', 'فعال او جاري') ?></option>
                        <option value="settled"><?= __t('Fully Settled', 'تسویه شده', 'تصفیه شوی') ?></option>
                        <option value="cancelled"><?= __t('Cancelled', 'لغو شده', 'لغوه شوی') ?></option>
                    </select>
                </div>
            </div>

            <!-- Financials and Paid-out adjustments -->
            <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 10px; padding: 12px; margin-bottom: 14px;">
                <div style="font-weight: 800; font-size: 13px; color: #1e293b; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-coins text-amber-500"></i> <?= __t('Financial Ledger, Paid Profit & Returned Capital:', 'مبالغ مالی، سود پرداختی و استرداد سرمایه (رسیدها):', 'مالي مبالغ او ورکړل شوې پیسې:') ?>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12px; color: #047857;">
                            <?= __t('Profit Disbursed to Partner:', 'سود پرداخت شده به شریک (رسید):', 'شریک ته ورکړل شوې ګټه:') ?>
                        </label>
                        <input type="number" step="any" min="0" name="paid_profit_to_partner" id="edit_c_paid_profit" class="form-control" style="font-weight: bold; color: #047857;">
                        <span style="font-size: 11px; color: #64748b;"><?= __t('(Profits already handed over to partner)', '(مفادی که تا کنون به شریک تسلیم شده)', '(هغه ګټه چې ورکړل شوې)') ?></span>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12px; color: #2563eb;">
                            <?= __t('Principal Capital Returned:', 'اصل سرمایه استرداد شده (پس داده شده):', 'بېرته ورکړل شوې اصلي پانګه:') ?>
                        </label>
                        <input type="number" step="any" min="0" name="returned_capital" id="edit_c_returned_capital" class="form-control" style="font-weight: bold; color: #2563eb;">
                        <span style="font-size: 11px; color: #64748b;"><?= __t('(Portion of capital refunded to partner)', '(بخشی از اصل سرمایه که مسترد شده)', '(بېرته ورکړل شوې برخه)') ?></span>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12px; color: #475569;">
                            <?= __t('Total Sales Logged:', 'مجموع فروشات ثبت شده:', 'ټول ثبت شوی پلور:') ?>
                        </label>
                        <input type="number" step="any" min="0" name="total_sales_amount" id="edit_c_total_sales" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12px; color: #475569;">
                            <?= __t('Total Project Gross Profit:', 'مجموع مفاد کل پروژه:', 'د پروژې ټوله ګټه:') ?>
                        </label>
                        <input type="number" step="any" min="0" name="total_profit_amount" id="edit_c_total_profit" class="form-control">
                    </div>
                </div>
                <div style="margin-top: 8px; font-size: 11.5px; color: #0369a1; background: #e0f2fe; padding: 6px 10px; border-radius: 6px;">
                    <i class="fa-solid fa-info-circle ml-1"></i> <?= __t('Adjusting payouts above automatically balances store capital records in accordance with accounting principles.', 'با ویرایش مبالغ بالا، سیستم مطابق اصول حسابداری گردش سرمایه دکان را به صورت خودکار متوازن و تنظیم می‌نماید.', 'د دې ارقامو بدلون اتومات د هټۍ له پانګې سره توازن پیدا کوي.') ?>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Agreement Notes & Remarks:', 'یادداشت و توضیحات:', 'توضیحات او یادښت:') ?></label>
                <input type="text" name="notes" id="edit_c_notes" class="form-control">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="closeEditContractModal();" class="btn btn-secondary">
                    <?= __t('Cancel', 'انصراف', 'لغوه') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check ml-1"></i> <?= __t('Save Changes', 'ذخیره تغییرات', 'بدلونونه خوندي کړئ') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const isEnglish = <?= json_encode($currentLang === 'en') ?>;
const currencyLabel = <?= json_encode(get_currency_label()) ?>;

function openEditContractModalFromBtn(btn) {
    try {
        const raw = btn.getAttribute('data-contract');
        const data = JSON.parse(raw);
        openEditContractModal(data);
    } catch (e) {
        console.error("Error opening edit contract modal:", e);
    }
}

function openEditContractModal(data) {
    document.getElementById('edit_contract_id').value = data.id || 0;
    document.getElementById('edit_c_partner_name').value = data.partner_name || '';
    document.getElementById('edit_c_phone').value = data.phone || '';
    document.getElementById('edit_c_project_title').value = data.project_title || '';
    document.getElementById('edit_c_country_source').value = data.country_source || '';
    document.getElementById('edit_c_capital_amount').value = data.capital_amount || 0;
    document.getElementById('edit_c_partner_rate').value = data.partner_profit_rate || 50;
    document.getElementById('edit_c_shop_rate').value = data.shop_profit_rate || 50;
    if (document.getElementById('edit_c_paid_profit')) {
        document.getElementById('edit_c_paid_profit').value = data.paid_profit_to_partner || 0;
    }
    if (document.getElementById('edit_c_returned_capital')) {
        document.getElementById('edit_c_returned_capital').value = data.returned_capital || 0;
    }
    if (document.getElementById('edit_c_total_sales')) {
        document.getElementById('edit_c_total_sales').value = data.total_sales_amount || 0;
    }
    if (document.getElementById('edit_c_total_profit')) {
        document.getElementById('edit_c_total_profit').value = data.total_profit_amount || 0;
    }
    document.getElementById('edit_c_start_date').value = data.start_date || '';
    document.getElementById('edit_c_settlement_date').value = data.settlement_date || '';
    document.getElementById('edit_c_status').value = data.status || 'active';
    document.getElementById('edit_c_notes').value = data.notes || '';
    document.getElementById('editContractModal').style.display = 'flex';
}

function closeEditContractModal() {
    document.getElementById('editContractModal').style.display = 'none';
}

// Bulk Selection Logic for Mudaraba Contracts
function toggleAllMudaraba(headerMaster) {
    const checkboxes = document.querySelectorAll('.mudaraba-chk');
    checkboxes.forEach(cb => cb.checked = headerMaster.checked);
    updateMudarabaBulkBar();
}

function selectAllMudarabaRows(checkAll) {
    const checkboxes = document.querySelectorAll('.mudaraba-chk');
    checkboxes.forEach(cb => cb.checked = checkAll);
    const master = document.getElementById('selectAllMudarabaHeader');
    if (master) master.checked = checkAll;
    updateMudarabaBulkBar();
}

function updateMudarabaBulkBar() {
    const checkboxes = document.querySelectorAll('.mudaraba-chk:checked');
    const bar = document.getElementById('mudarabaBulkActionBar');
    const countText = document.getElementById('mudarabaSelectedCountText');
    const count = checkboxes.length;

    if (count > 0) {
        bar.style.display = 'block';
        countText.textContent = isEnglish ? count.toLocaleString('en-US') : count.toLocaleString('fa-IR');
    } else {
        bar.style.display = 'none';
    }
}

function confirmBulkDeleteMudaraba() {
    const checkboxes = document.querySelectorAll('.mudaraba-chk:checked');
    if (checkboxes.length === 0) {
        alert(isEnglish ? 'Please select at least one contract to delete.' : 'لطفاً حداقل یک قرارداد مضاربت را برای حذف انتخاب نمایید.');
        return;
    }

    const msg = isEnglish 
        ? `Are you sure you want to delete ${checkboxes.length} selected Mudaraba contract(s)? Warehouse goods will be unlinked from Mudaraba.`
        : `آیا از حذف تعداد ${checkboxes.length} قرارداد مضاربت انتخاب شده به صورت گروهی اطمینان کامل دارید؟ اجناس گدام از این مضاربت‌ها جدا خواهند شد.`;

    if (!confirm(msg)) {
        return;
    }

    const container = document.getElementById('mudarabaBulkDeleteInputsContainer');
    container.innerHTML = '';
    checkboxes.forEach(cb => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'selected_ids[]';
        hidden.value = cb.value;
        container.appendChild(hidden);
    });

    document.getElementById('mudarabaBulkDeleteForm').submit();
}

function toggleContractStock(contractId) {
    var row = document.getElementById('contract_stock_row_' + contractId);
    if (!row) return;
    if (row.style.display === 'none' || row.style.display === '') {
        row.style.display = 'table-row';
    } else {
        row.style.display = 'none';
    }
}

function recalcMudarabaCapital() {
    var qty = parseFloat(document.getElementById('m_quantity').value || 0);
    var buy = parseFloat(document.getElementById('m_purchase_price').value || 0);
    var exp = parseFloat(document.getElementById('m_expenses').value || 0);
    var total = qty * (buy + exp);
    if (total > 0) {
        document.getElementById('m_capital_amount').value = total;
    }
}

function openAddStockModalFromBtn(btn) {
    try {
        const data = JSON.parse(btn.getAttribute('data-add-stock') || '{}');
        openAddStockModal(data.id, data.partner_name, data.country_source);
    } catch (e) {
        console.error("Error opening add stock modal:", e);
    }
}

function openAddStockModal(contractId, partnerName, countrySource) {
    document.getElementById('add_stock_contract_id').value = contractId || 0;
    document.getElementById('add_stock_partner_name').textContent = partnerName || '';
    document.getElementById('addStockModal').style.display = 'flex';
}

let currentProgressPartnerRate = 50;
let currentProgressShopRate = 50;

function openProgressModalFromBtn(btn) {
    try {
        const data = JSON.parse(btn.getAttribute('data-progress') || '{}');
        document.getElementById('progress_contract_id').value = data.id || 0;
        document.getElementById('progress_project_title').textContent = data.project_title || '';
        currentProgressPartnerRate = parseFloat(data.partner_rate || 50);
        currentProgressShopRate = parseFloat(data.shop_rate || 50);
        document.getElementById('progress_p_rate').textContent = currentProgressPartnerRate;
        document.getElementById('progress_s_rate').textContent = currentProgressShopRate;
        document.getElementById('progress_add_profit').value = '';
        document.getElementById('progress_split_box').style.display = 'none';
        document.getElementById('progressModal').style.display = 'flex';
    } catch (e) {
        console.error("Error opening progress modal:", e);
    }
}

function openProgressModal(id, title) {
    document.getElementById('progress_contract_id').value = id || 0;
    document.getElementById('progress_project_title').textContent = title || '';
    document.getElementById('progressModal').style.display = 'flex';
}

function recalcProgressSplit() {
    var prof = parseFloat(document.getElementById('progress_add_profit').value || 0);
    var box = document.getElementById('progress_split_box');
    if (prof > 0) {
        box.style.display = 'block';
        var pShare = (prof * currentProgressPartnerRate) / 100;
        var sShare = (prof * currentProgressShopRate) / 100;
        document.getElementById('progress_p_share').textContent = isEnglish ? Math.round(pShare).toLocaleString('en-US') : Math.round(pShare).toLocaleString('fa-IR');
        document.getElementById('progress_s_share').textContent = isEnglish ? Math.round(sShare).toLocaleString('en-US') : Math.round(sShare).toLocaleString('fa-IR');
    } else {
        box.style.display = 'none';
    }
}

function openPayoutModalFromBtn(btn) {
    try {
        const data = JSON.parse(btn.getAttribute('data-payout') || '{}');
        document.getElementById('payout_contract_id').value = data.id || 0;
        document.getElementById('payout_partner_name').textContent = data.partner_name || '';
        document.getElementById('payout_rem_profit').textContent = isEnglish ? (data.rem_profit || 0).toLocaleString('en-US') : (data.rem_profit || 0).toLocaleString('fa-IR');
        document.getElementById('payout_rem_cap').textContent = isEnglish ? (data.rem_cap || 0).toLocaleString('en-US') : (data.rem_cap || 0).toLocaleString('fa-IR');
        document.getElementById('payout_profit_inp').value = (data.rem_profit > 0) ? data.rem_profit : 0;
        document.getElementById('return_cap_inp').value = 0;
        updatePayoutTotal();
        document.getElementById('payoutModal').style.display = 'flex';
    } catch (e) {
        console.error("Error opening payout modal:", e);
    }
}

function openPayoutModal(id, partnerName, remProfit, remCap) {
    document.getElementById('payout_contract_id').value = id || 0;
    document.getElementById('payout_partner_name').textContent = partnerName || '';
    document.getElementById('payout_rem_profit').textContent = isEnglish ? (remProfit || 0).toLocaleString('en-US') : (remProfit || 0).toLocaleString('fa-IR');
    document.getElementById('payout_rem_cap').textContent = isEnglish ? (remCap || 0).toLocaleString('en-US') : (remCap || 0).toLocaleString('fa-IR');
    document.getElementById('payout_profit_inp').value = (remProfit > 0) ? remProfit : 0;
    document.getElementById('return_cap_inp').value = 0;
    updatePayoutTotal();
    document.getElementById('payoutModal').style.display = 'flex';
}

function updatePayoutTotal() {
    var pProfit = parseFloat(document.getElementById('payout_profit_inp').value || 0);
    var rCap = parseFloat(document.getElementById('return_cap_inp').value || 0);
    var tot = pProfit + rCap;
    var el = document.getElementById('payout_total_preview');
    if (el) el.textContent = (isEnglish ? Math.round(tot).toLocaleString('en-US') : Math.round(tot).toLocaleString('fa-IR')) + ' ' + currencyLabel;
}

<?php if (!empty($_GET['contract_id'])): ?>
window.addEventListener('DOMContentLoaded', function() {
    toggleContractStock(<?= (int)$_GET['contract_id'] ?>);
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
