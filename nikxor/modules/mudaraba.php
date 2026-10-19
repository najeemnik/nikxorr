<?php
/**
 * NiKXor - Mobile Shop Management System
 * Mudaraba Partnership & Foreign Sourcing Module (حساب اختصاصی مضاربت و تجارت کالای خارجی)
 * URL: http://localhost/nikxor/modules/mudaraba.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();

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
        $error = 'خطای امنیتی نشست!';
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

        // Calculate capital from goods: (purchasePrice + expenses) * quantity
        $unitCost          = $purchasePrice + $expenses;
        $calculatedCapital = $unitCost * $quantity;
        $manualCapital     = (float)clean_number($_POST['capital_amount'] ?? '0', true);
        $capitalAmount     = ($calculatedCapital > 0) ? $calculatedCapital : $manualCapital;

        $projectTitle = !empty($productName) 
            ? ($productName . ' (' . to_persian_num($quantity) . ' عدد)') 
            : trim($_POST['project_title'] ?? 'خرید کالای تجاری مضاربت');

        if (empty($partnerName)) {
            $error = 'لطفاً نام شریک سرمایه‌گذار مضاربت را وارد نمایید.';
        } elseif (empty($projectTitle)) {
            $error = 'لطفاً نام جنس یا عنوان پروژه مضاربت را مشخص نمایید.';
        } elseif ($capitalAmount <= 0) {
            $error = 'مقدار سرمایه مضاربت باید بیشتر از صفر باشد.';
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
                set_flash("قرارداد مضاربت با «{$partnerName}» ثبت شد و جنس مربوطه مستقیماً با برچسب «حساب مضاربت با {$partnerName}» در گدام ذخیره گردید.", 'success');
                redirect(BASE_URL . 'modules/mudaraba.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'خطا در ثبت قرارداد و گدام: ' . $e->getMessage();
            }
        }
    }
}

// 2. Handle Adding Extra Goods into Existing Mudaraba Contract
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_contract_item') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
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
            $error = 'لطفاً نام جنس و تعداد معتبر را وارد نمایید.';
        } else {
            try {
                $pdo->beginTransaction();

                // Fetch contract partner name
                $cStmt = $pdo->prepare("SELECT partner_name, country_source FROM mudaraba_contracts WHERE id = :id");
                $cStmt->execute([':id' => $contractId]);
                $cRow = $cStmt->fetch();

                if (!$cRow) {
                    throw new Exception('قرارداد مضاربت یافت نشد.');
                }

                $partnerName = $cRow['partner_name'];
                if (empty($countrySource)) {
                    $countrySource = $cRow['country_source'];
                }

                $itemCapital = ($purchasePrice + $expenses) * $quantity;

                // Insert into stock
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

                // Update contract capital amount
                $updC = $pdo->prepare("UPDATE mudaraba_contracts SET capital_amount = capital_amount + :cap WHERE id = :id");
                $updC->execute([':cap' => $itemCapital, ':id' => $contractId]);

                $pdo->commit();
                log_activity($currentUser['id'], 'افزودن جنس به مضاربت', "افزودن جنس «{$productName}» به تعداد {$quantity} به قرارداد مضاربت #{$contractId}");
                set_flash("جنس «{$productName}» با موفقیت به قرارداد مضاربت افزوده و در گدام ذخیره شد.", 'success');
                redirect(BASE_URL . 'modules/mudaraba.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'خطا در ثبت جنس: ' . $e->getMessage();
            }
        }
    }
}

// 3. Handle Record Sales / Profit Progress on Mudaraba Contract
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_progress') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
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
            set_flash("فروش و مفاد با موفقیت به حساب مضاربت افزوده شد.", 'success');
            redirect(BASE_URL . 'modules/mudaraba.php');
        } else {
            $error = 'مقادیر وارد شده نامعتبر است.';
        }
    }
}

// 4. Handle Payout / Settlement to Partner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'payout_partner') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('خطای امنیتی نشست!', 'danger');
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
                $pName = $cData['partner_name'] ?? "شریک #{$contractId}";
                $pProj = $cData['project_title'] ?? "پروژه مضاربت";

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

                // Standard Accounting: Deduct total paid payout from the shop's main capital
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
                set_flash("مبلغ پرداختی (" . format_money($totalPaidOut) . ") با موفقیت در حساب مضاربت ثبت و از سرمایه اصلی دکان کسر گردید.", 'success');
                redirect(BASE_URL . 'modules/mudaraba.php');
            } catch (Exception $e) {
                // Auto-healing for XAMPP / MySQL environments: repair collations & schema and retry once
                if (function_exists('harmonize_mysql_collations')) {
                    harmonize_mysql_collations($pdo);
                }
                ensure_mudaraba_full_schema($pdo);
                try {
                    $statusSql = ($markSettled === 'settled') ? ", status = :mstatus, settlement_date = :sdate" : "";
                    $stmt = $pdo->prepare("UPDATE mudaraba_contracts 
                        SET paid_profit_to_partner = COALESCE(paid_profit_to_partner, 0) + :profit,
                            returned_capital = COALESCE(returned_capital, 0) + :cap
                            {$statusSql}
                        WHERE id = :id");
                    $updParams = [
                        ':profit' => $payProfit,
                        ':cap'    => $returnCap,
                        ':id'     => $contractId
                    ];
                    if ($markSettled === 'settled') {
                        $updParams[':mstatus'] = 'settled';
                        $updParams[':sdate']   = $pDate;
                    }
                    $stmt->execute($updParams);

                    $totalPaidOut = $payProfit + $returnCap;
                    if ($totalPaidOut > 0) {
                        $capDesc = "پرداخت وجه مضاربت به شریک #{$contractId}";
                        try {
                            $pdo->prepare("INSERT INTO capital (amount, transaction_type, source_description, transaction_date, created_at)
                                           VALUES (:amt, :ttype, :desc, :tdate, :cdate)")
                                ->execute([
                                    ':amt'   => $totalPaidOut,
                                    ':ttype' => 'برداشت',
                                    ':desc'  => $capDesc,
                                    ':tdate' => $pDate,
                                    ':cdate' => date('Y-m-d H:i:s')
                                ]);
                        } catch (Exception $ce) {
                            try {
                                $pdo->prepare("INSERT INTO capital (amount, transaction_type, source_description) VALUES (?, ?, ?)")
                                    ->execute([$totalPaidOut, 'برداشت', $capDesc]);
                            } catch (Exception $ce2) {}
                        }
                    }
                    set_flash("مبلغ پرداختی با موفقیت در حساب مضاربت ثبت و از سرمایه اصلی دکان کسر گردید.", 'success');
                    redirect(BASE_URL . 'modules/mudaraba.php');
                } catch (Exception $retryEx) {
                    set_flash('خطا در ثبت پرداخت مضاربت: ' . $retryEx->getMessage(), 'danger');
                    redirect(BASE_URL . 'modules/mudaraba.php');
                }
            }
        } else {
            set_flash('لطفاً مبلغ پرداختی یا وضعیت تسویه را مشخص فرمایید.', 'warning');
            redirect(BASE_URL . 'modules/mudaraba.php');
        }
    }
}

// 5. Handle Delete Contract
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_contract') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('خطای امنیتی نشست!', 'danger');
    } else {
        $delId = (int)($_POST['contract_id'] ?? 0);
        if ($delId > 0) {
            // Unlink any stock items
            $pdo->prepare("UPDATE stock SET mudaraba_id = NULL WHERE mudaraba_id = :id")->execute([':id' => $delId]);
            $pdo->prepare("DELETE FROM mudaraba_contracts WHERE id = :id")->execute([':id' => $delId]);
            log_activity($currentUser['id'], 'حذف قرارداد مضاربت', "حذف قرارداد مضاربت #{$delId}");
            set_flash("قرارداد مضاربت با موفقیت حذف گردید.", 'success');
            redirect(BASE_URL . 'modules/mudaraba.php');
        }
    }
}

// 6. Handle Edit Contract (شامل ویرایش مشخصات، سرمایه، سود پرداختی و اصل سرمایه بازگردانده شده)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_contract') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('خطای امنیتی نشست!', 'danger');
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
            set_flash('لطفاً نام شریک و عنوان پروژه را مشخص نمایید.', 'danger');
            redirect(BASE_URL . 'modules/mudaraba.php');
        } else {
            try {
                // Fetch old contract to calculate financial differential for shop capital
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

                // Standard Accounting Adjustment in Capital Table
                if (abs($payoutDelta) > 0.01) {
                    if ($payoutDelta > 0) {
                        // More money was paid out -> record withdrawal from shop capital
                        $capStmt = $pdo->prepare("INSERT INTO capital (amount, transaction_type, source_description, transaction_date, created_at)
                                                  VALUES (:amt, 'برداشت', :desc, :tdate, :cdate)");
                        $capStmt->execute([
                            ':amt'   => $payoutDelta,
                            ':desc'  => "اصلاح مالی مضاربت #{$cId} («{$partnerName}»): افزایش پرداخت به شریک",
                            ':tdate' => date('Y-m-d'),
                            ':cdate' => date('Y-m-d H:i:s')
                        ]);
                    } else {
                        // Payout was reduced -> return money back to shop capital
                        $capStmt = $pdo->prepare("INSERT INTO capital (amount, transaction_type, source_description, transaction_date, created_at)
                                                  VALUES (:amt, 'سرمایه', :desc, :tdate, :cdate)");
                        $capStmt->execute([
                            ':amt'   => abs($payoutDelta),
                            ':desc'  => "اصلاح مالی مضاربت #{$cId} («{$partnerName}»): تعدیل و بازگشت وجه به صندوق دکان",
                            ':tdate' => date('Y-m-d'),
                            ':cdate' => date('Y-m-d H:i:s')
                        ]);
                    }
                }

                log_activity($currentUser['id'], 'ویرایش قرارداد مضاربت', "ویرایش قرارداد و وضعیت مالی مضاربت #{$cId} با «{$partnerName}»");
                set_flash("قرارداد مضاربت با «{$partnerName}» و اطلاعات مالی آن با موفقیت بروزرسانی شد و در گردش سرمایه دکان اعمال گردید.", 'success');
                redirect(BASE_URL . 'modules/mudaraba.php');
            } catch (Exception $e) {
                // Auto-healing for XAMPP / MySQL environments: repair schema and retry once
                ensure_mudaraba_full_schema($pdo);
                try {
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
                    set_flash("قرارداد مضاربت با «{$partnerName}» با موفقیت بروزرسانی و جدول دیتابیس ترمیم گردید.", 'success');
                    redirect(BASE_URL . 'modules/mudaraba.php');
                } catch (Exception $retryEx) {
                    set_flash('خطا در ویرایش قرارداد مضاربت: ' . $retryEx->getMessage(), 'danger');
                    redirect(BASE_URL . 'modules/mudaraba.php');
                }
            }
        }
    }
}

// 7. Handle Bulk Delete Contracts (سلکت و حذف گروهی قراردادها)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete_contracts') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('خطای امنیتی نشست!', 'danger');
        redirect(BASE_URL . 'modules/mudaraba.php');
    }

    $selectedIds = $_POST['selected_ids'] ?? [];
    if (!is_array($selectedIds) || empty($selectedIds)) {
        set_flash('هیچ قراردادی برای حذف انتخاب نشده است.', 'warning');
        redirect(BASE_URL . 'modules/mudaraba.php');
    }

    $validIds = array_values(array_filter(array_map('intval', $selectedIds), fn($id) => $id > 0));
    if (empty($validIds)) {
        set_flash('شناسه‌های ارسالی معتبر نمی‌باشند.', 'danger');
        redirect(BASE_URL . 'modules/mudaraba.php');
    }

    $placeholders = implode(',', array_fill(0, count($validIds), '?'));
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE stock SET mudaraba_id = NULL WHERE mudaraba_id IN ($placeholders)")->execute($validIds);
        $delStmt = $pdo->prepare("DELETE FROM mudaraba_contracts WHERE id IN ($placeholders)");
        $delStmt->execute($validIds);
        $deletedCount = $delStmt->rowCount();
        $pdo->commit();

        log_activity($currentUser['id'], 'حذف گروهی قراردادهای مضاربت', "حذف {$deletedCount} قرارداد مضاربت انتخاب شده");
        set_flash("تعداد " . to_persian_num($deletedCount) . " قرارداد مضاربت با موفقیت حذف گردیدند.", 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('خطا در حذف گروهی قراردادها: ' . $e->getMessage(), 'danger');
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
    
    // Partner and shop shares
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
                حساب اختصاصی مضاربت (خریداری و فروش کالای خارجی شرکتی)
            </h1>
            <p style="color: #64748b; font-size: 13px; margin: 4px 0 0 0;">
                ثبت سرمایه شریک، خریداری کالا از خارج، ثبت مستقیم در گدام با برچسب اختصاصی، و تقسیم مفاد شرعی
            </p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button onclick="document.getElementById('addContractModal').style.display='flex'" class="btn btn-primary" style="padding: 9px 18px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #1e40af, #2563eb); border: none; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);">
                <i class="fa-solid fa-file-circle-plus" style="font-size: 15px;"></i>
                <span>عقد قرارداد جدید و ثبت مال در گدام</span>
            </button>
            <a href="<?= BASE_URL ?>modules/stock.php?filter=mudaraba" class="btn" style="padding: 9px 16px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; background: #ecfdf5; color: #065f46; border: 1.5px solid #a7f3d0; box-shadow: 0 2px 6px rgba(16, 185, 129, 0.1);">
                <i class="fa-solid fa-boxes-stacked" style="color: #059669; font-size: 15px;"></i>
                <span>مشاهده مال‌های مضاربت در گدام</span>
            </a>
            <a href="<?= BASE_URL ?>modules/partners.php" class="btn" style="padding: 9px 16px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; background: #f8fafc; color: #334155; border: 1.5px solid #cbd5e1;">
                <i class="fa-solid fa-user-group" style="color: #475569; font-size: 15px;"></i>
                <span>شرکاء عمومی دکان</span>
            </a>
        </div>
    </div>

    <!-- MUDARABA EXCLUSIVE METRICS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: linear-gradient(145deg, #ffffff, #f0fdf4); border: 1.5px solid #bbf7d0; border-radius: 14px; padding: 18px; box-shadow: 0 4px 12px rgba(22, 101, 52, 0.05); position: relative; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 12.5px; color: #166534; font-weight: 700;">مجموع سرمایه فعال مضاربت</span>
                <div style="width: 36px; height: 36px; background: #dcfce7; color: #15803d; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px;">
                    <i class="fa-solid fa-vault"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: #15803d;"><?= format_money($totalActiveCapital) ?></div>
            <div style="font-size: 11.5px; color: #166534; margin-top: 6px;">سرمایه اختصاصی شرکاء خارجی</div>
        </div>

        <div style="background: linear-gradient(145deg, #ffffff, #eff6ff); border: 1.5px solid #bfdbfe; border-radius: 14px; padding: 18px; box-shadow: 0 4px 12px rgba(29, 78, 216, 0.05); position: relative; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 12.5px; color: #1e40af; font-weight: 700;">مجموع فروشات اجناس مضاربت</span>
                <div style="width: 36px; height: 36px; background: #dbeafe; color: #1d4ed8; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px;">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: #1d4ed8;"><?= format_money($totalMudarabaSales) ?></div>
            <div style="font-size: 11.5px; color: #1e40af; margin-top: 6px;">ثبت خودکار هنگام صدور بل فروش</div>
        </div>

        <div style="background: linear-gradient(145deg, #ffffff, #faf5ff); border: 1.5px solid #e9d5ff; border-radius: 14px; padding: 18px; box-shadow: 0 4px 12px rgba(126, 34, 206, 0.05); position: relative; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 12.5px; color: #6b21a8; font-weight: 700;">سهم مفاد شرکاء مضاربت</span>
                <div style="width: 36px; height: 36px; background: #f3e8ff; color: #7e22ce; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px;">
                    <i class="fa-solid fa-sack-dollar"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: #7e22ce;"><?= format_money($totalPartnerShare) ?></div>
            <div style="font-size: 11.5px; color: #6b21a8; margin-top: 6px;">مانده تسویه نشده: <strong style="color: <?= $pendingPartnerPayout > 0 ? '#b91c1c' : '#15803d' ?>;"><?= format_money($pendingPartnerPayout) ?></strong></div>
        </div>

        <div style="background: linear-gradient(145deg, #ffffff, #fffbeb); border: 1.5px solid #fde68a; border-radius: 14px; padding: 18px; box-shadow: 0 4px 12px rgba(180, 83, 9, 0.05); position: relative; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 12.5px; color: #92400e; font-weight: 700;">سهم مفاد خالص دکان از مضاربت</span>
                <div style="width: 36px; height: 36px; background: #fef3c7; color: #b45309; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px;">
                    <i class="fa-solid fa-coins"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: #b45309;"><?= format_money($totalShopShare) ?></div>
            <div style="font-size: 11.5px; color: #92400e; margin-top: 6px;">درآمد قطعی حاصل از عامل و فروشندگی</div>
        </div>
    </div>

    <!-- CONTRACTS TABLE -->
    <div style="margin-top: 20px;">
        <h2 style="font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-list-check text-primary"></i> لیست قراردادهای مضاربت و اجناس ثبت‌شده در گدام
        </h2>

        <!-- BULK SELECTION ACTION BAR -->
        <div id="mudarabaBulkActionBar" class="card" style="display: none; background: #eff6ff; border: 1.5px solid #3b82f6; margin-bottom: 16px; padding: 12px 18px; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.12);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <span style="font-weight: 800; color: #1e3a8a; font-size: 14px;">
                        <i class="fa-solid fa-square-check text-blue-600 ml-1"></i> <span id="mudarabaSelectedCountText">۰</span> قرارداد مضاربت انتخاب گردید
                    </span>
                    <button type="button" onclick="selectAllMudarabaRows(true)" class="btn btn-sm btn-secondary" style="background: #ffffff;">
                        <i class="fa-solid fa-list-check ml-1"></i> انتخاب همه
                    </button>
                    <button type="button" onclick="selectAllMudarabaRows(false)" class="btn btn-sm btn-secondary" style="background: #ffffff;">
                        <i class="fa-solid fa-xmark ml-1"></i> لغو انتخاب‌ها
                    </button>
                </div>
                <div>
                    <button type="button" onclick="confirmBulkDeleteMudaraba()" class="btn btn-danger btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                        <i class="fa-solid fa-trash-can"></i> حذف قراردادهای انتخاب شده
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
                            <input type="checkbox" id="selectAllMudarabaHeader" onchange="toggleAllMudaraba(this)" title="انتخاب همه">
                        </th>
                        <th style="width: 40px; text-align: center;">#</th>
                        <th style="min-width: 140px; white-space: nowrap;">شریک سرمایه‌گذار</th>
                        <th style="min-width: 220px; white-space: nowrap;">شرح کالا و مشخصات پروژه</th>
                        <th style="text-align: center; white-space: nowrap;">کشور مبدا</th>
                        <th style="text-align: center; white-space: nowrap;">سرمایه اولیه</th>
                        <th style="text-align: center; white-space: nowrap;">نسبت مفاد (شریک / دکان)</th>
                        <th style="text-align: center; white-space: nowrap;">فروش تا اکنون</th>
                        <th style="text-align: center; white-space: nowrap;">مفاد تحقق‌یافته</th>
                        <th style="text-align: center; white-space: nowrap;">سهم شریک / پرداختی</th>
                        <th style="text-align: center; white-space: nowrap;">وضعیت</th>
                        <th style="text-align: center; min-width: 210px; white-space: nowrap;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contracts)): ?>
                        <tr>
                            <td colspan="12" style="text-align: center; padding: 36px 12px; color: #94a3b8;">
                                <i class="fa-solid fa-handshake-slash" style="font-size: 32px; display: block; margin-bottom: 8px; color: #cbd5e1;"></i>
                                تاکنون هیچ قرارداد مضاربتی ثبت نشده است. با کلیک بر روی «عقد قرارداد جدید و ثبت مال در گدام» اولین سرمایه‌گذاری را ثبت نمایید.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($contracts as $idx => $c): 
                            $pShare = ((float)$c['total_profit_amount'] * (float)$c['partner_profit_rate']) / 100;
                            $remPShare = max(0, $pShare - (float)$c['paid_profit_to_partner']);
                            
                            // Fetch stock items tied to this contract
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
                                <td style="text-align: center; font-weight: 700; color: #64748b;"><?= $idx + 1 ?></td>
                                <td>
                                    <div style="font-weight: 700; color: #0f172a; font-size: 14px;">
                                        <?= sanitize($c['partner_name']) ?>
                                    </div>
                                    <?php if (!empty($c['phone'])): ?>
                                        <div style="font-size: 11.5px; color: #64748b; direction: ltr; text-align: right; margin-top: 2px;">
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
                                            <span>تاریخ آغاز:</span>
                                            <strong style="color: #0f172a; direction: ltr; font-family: inherit;"><?= to_persian_num($c['start_date']) ?></strong>
                                        </div>
                                        <div>
                                            <button type="button" onclick="toggleContractStock(<?= $c['id'] ?>)" class="btn btn-sm btn-secondary" style="padding: 2px 8px; font-size: 11px; white-space: nowrap; background: #f8fafc; border: 1px solid #cbd5e1;">
                                                <i class="fa-solid fa-boxes-stacked ml-1 text-primary"></i> اجناس در گدام (<?= to_persian_num(count($cItems)) ?>)
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <span class="badge" style="background: #f1f5f9; color: #334155; font-size: 12px;">
                                        <i class="fa-solid fa-globe ml-1 text-primary"></i> <?= sanitize($c['country_source'] ?: 'خارجی') ?>
                                    </span>
                                </td>
                                <td style="text-align: center; font-weight: 800; color: #166534; font-size: 14px; white-space: nowrap;">
                                    <?= format_money($c['capital_amount'], false) ?>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <span style="font-weight: 700; color: #0f172a;">
                                        <?= (float)$c['partner_profit_rate'] ?>٪ / <?= (float)$c['shop_profit_rate'] ?>٪
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
                                        سهم سود: <?= format_money($pShare, false) ?>
                                    </div>
                                    <div style="font-size: 11.5px; color: #15803d; font-weight: 700; margin-top: 2px;">
                                        سود پرداختی: <?= format_money($c['paid_profit_to_partner'], false) ?>
                                    </div>
                                    <?php if ((float)($c['returned_capital'] ?? 0) > 0): ?>
                                        <div style="font-size: 11px; color: #2563eb; font-weight: 700; margin-top: 1px;">
                                            اصل مسترد: <?= format_money($c['returned_capital'], false) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php 
                                    $remCapital = max(0, (float)$c['capital_amount'] - (float)($c['returned_capital'] ?? 0));
                                    $remProfit = max(0, $pShare - (float)($c['paid_profit_to_partner'] ?? 0));
                                    $totalRem = $remCapital + $remProfit;
                                    ?>
                                    <div style="font-size: 11.5px; color: <?= $totalRem > 0 ? '#b91c1c' : '#166534' ?>; font-weight: 800; margin-top: 3px; background: <?= $totalRem > 0 ? '#fef2f2' : '#f0fdf4' ?>; border-radius: 4px; padding: 2px 4px;">
                                        طلب کل: <?= format_money($totalRem, false) ?>
                                    </div>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <?php if ($c['status'] === 'active'): ?>
                                        <span class="badge badge-success">جاری و فعال</span>
                                    <?php elseif ($c['status'] === 'settled'): ?>
                                        <span class="badge badge-secondary">تسویه شده</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger"><?= sanitize($c['status']) ?></span>
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
                                                title="ویرایش قرارداد و حساب مالی">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>

                                        <!-- Add More Stock to this contract -->
                                        <button type="button" class="btn btn-sm btn-secondary" style="background: #e0f2fe; color: #0369a1; border-color: #bae6fd; padding: 4px 8px;"
                                                data-add-stock="<?= htmlspecialchars(json_encode([
                                                    'id' => (int)$c['id'],
                                                    'partner_name' => $c['partner_name'],
                                                    'country_source' => $c['country_source'] ?? ''
                                                ]), ENT_QUOTES, 'UTF-8') ?>"
                                                onclick="openAddStockModalFromBtn(this)" title="افزودن جنس جدید به این قرارداد">
                                            <i class="fa-solid fa-plus"></i> مال
                                        </button>

                                        <!-- Update Sales Progress Button -->
                                        <button type="button" class="btn btn-sm btn-primary" style="padding: 4px 8px;"
                                                data-progress="<?= htmlspecialchars(json_encode([
                                                    'id' => (int)$c['id'],
                                                    'project_title' => $c['project_title'],
                                                    'partner_rate' => (float)$c['partner_profit_rate'],
                                                    'shop_rate' => (float)$c['shop_profit_rate']
                                                ]), ENT_QUOTES, 'UTF-8') ?>"
                                                onclick="openProgressModalFromBtn(this)" title="ثبت دستی فروش و مفاد">
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
                                                onclick="openPayoutModalFromBtn(this)" title="پرداخت و تسویه با شریک">
                                            <i class="fa-solid fa-money-bill-wave"></i>
                                        </button>

                                        <!-- Print Mudaraba Statement Button -->
                                        <a href="<?= BASE_URL ?>print/mudaraba.php?id=<?= $c['id'] ?>" target="_blank" class="btn btn-sm btn-secondary" style="padding: 4px 8px;" title="چاپ سند و قرارداد مضاربت">
                                            <i class="fa-solid fa-print"></i>
                                        </a>

                                        <!-- Delete Button -->
                                        <form method="POST" action="" style="display: inline; margin: 0;" onsubmit="return confirm('آیا از حذف این قرارداد مضاربت مطمئن هستید؟ (اجناس گدام از مضاربت جدا می‌شوند)');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_contract">
                                            <input type="hidden" name="contract_id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" style="padding: 4px 8px;" title="حذف قرارداد">
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
                                                اجناس موجود در گدام مربوط به حساب مضاربت با «<?= sanitize($c['partner_name']) ?>»:
                                            </h4>
                                            <button type="button" onclick="openAddStockModal(<?= $c['id'] ?>, '<?= sanitize(addslashes($c['partner_name'])) ?>', '<?= sanitize(addslashes($c['country_source'])) ?>')" class="btn btn-sm btn-primary" style="font-size: 11.5px; padding: 3px 10px;">
                                                <i class="fa-solid fa-plus ml-1"></i> ثبت قلم جنس جدید برای این قرارداد
                                            </button>
                                        </div>

                                        <?php if (empty($cItems)): ?>
                                            <div style="padding: 12px; text-align: center; color: #94a3b8; font-size: 12.5px;">
                                                هنوز جنسی برای این قرارداد در گدام ثبت نشده است. می‌توانید با دکمه بالا جنس جدید ثبت نمایید.
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table" style="margin: 0; font-size: 12.5px;">
                                                    <thead>
                                                        <tr style="background: #f1f5f9;">
                                                            <th>#</th>
                                                            <th>اسم جنس</th>
                                                            <th>دسته‌بندی</th>
                                                            <th>مشخصات فنی (حافظه، رنگ، کارتن...)</th>
                                                            <th style="text-align: center;">موجودی فعلی در گدام</th>
                                                            <th style="text-align: center;">نرخ خرید</th>
                                                            <th style="text-align: center;">نرخ پیشنهادی فروش</th>
                                                            <th style="text-align: center;">ارزش موجودی گدام</th>
                                                            <th style="text-align: center;">موقعیت در دکان</th>
                                                            <th style="text-align: center;">برچسب در گدام</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $subIdx = 1;
                                                        foreach ($cItems as $sItem): 
                                                            $totalItemVal = ((float)$sItem['purchase_price'] + (float)$sItem['expenses']) * (int)$sItem['quantity'];
                                                        ?>
                                                            <tr>
                                                                <td><?= $subIdx++ ?></td>
                                                                <td style="font-weight: 700; color: #0f172a;">
                                                                    <?= sanitize($sItem['name']) ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge badge-secondary"><?= sanitize($sItem['category_name'] ?? 'بدون دسته') ?></span>
                                                                </td>
                                                                <td style="color: #475569; max-width: 200px;">
                                                                    <?= sanitize($sItem['specifications'] ?: '-') ?>
                                                                </td>
                                                                <td style="text-align: center; font-weight: 800;">
                                                                    <?php if ((int)$sItem['quantity'] <= 0): ?>
                                                                        <span class="badge badge-danger">تمام شده (۰)</span>
                                                                    <?php else: ?>
                                                                        <span style="color: #047857; font-size: 13.5px;"><?= to_persian_num($sItem['quantity']) ?> عدد</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td style="text-align: center;"><?= format_money($sItem['purchase_price'], false) ?></td>
                                                                <td style="text-align: center; color: #0284c7; font-weight: 700;"><?= format_money($sItem['sale_price'], false) ?></td>
                                                                <td style="text-align: center; font-weight: 800; color: #166534;"><?= format_money($totalItemVal, false) ?></td>
                                                                <td style="text-align: center;"><?= sanitize($sItem['warehouse_location'] ?: '-') ?></td>
                                                                <td style="text-align: center;">
                                                                    <span class="badge" style="background: #ecfdf5; color: #047857; border: 1px solid #6ee7b7; font-size: 11px;">
                                                                        حساب مضاربت با <?= sanitize($c['partner_name']) ?>
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
    <div style="background: #ffffff; border-radius: 16px; max-width: 680px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); padding: 24px; position: relative;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-handshake text-primary"></i> عقد قرارداد جدید مضاربت و ثبت مال در گدام
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
                    <i class="fa-solid fa-user-tie text-primary ml-1"></i> ۱. مشخصات شریک سرمایه‌گذار (رب‌المال):
                </div>
                <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;">نام شریک: <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="partner_name" class="form-control" required placeholder="مثلاً: حاجی نصرت‌الله">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;">شماره تماس:</label>
                        <input type="text" name="phone" class="form-control" placeholder="۰۷۸۸۱۲۳۴۵۶">
                    </div>
                </div>
            </div>

            <!-- Section 2: Goods & Warehouse Specifications -->
            <div style="background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 8px; padding: 14px; margin-bottom: 14px;">
                <div style="font-weight: 800; font-size: 13px; color: #065f46; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">
                    <span><i class="fa-solid fa-boxes-stacked ml-1"></i> ۲. مشخصات مال خریداری‌شده (ثبت مستقیم در گدام):</span>
                    <span class="badge" style="background: #dcfce7; color: #166534; font-size: 11px;">ذخیره مستقیم در گدام دکان</span>
                </div>

                <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;">نام جنس و مدل: <span style="color: #ef4444;">*</span></label>
                        <input type="text" id="m_product_name" name="product_name" class="form-control" required placeholder="مثلاً: Samsung Galaxy A55 5G یا iPhone 13 Pro">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;">دسته‌بندی در گدام:</label>
                        <select name="category_id" class="form-control">
                            <option value="">-- بدون دسته‌بندی --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">مشخصات جنس (حافظه، رم، رنگ، سلامت بتری، کارتن و...):</label>
                    <textarea name="specifications" class="form-control" rows="2" placeholder="مثلاً: 256GB / 8GB RAM - رنگ سرمه‌ای - با کارتن و لوازم اورجینال"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;">تعداد خریداری شده: <span style="color: #ef4444;">*</span></label>
                        <input type="number" id="m_quantity" name="quantity" class="form-control" min="1" value="1" required oninput="recalcMudarabaCapital()">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;">نرخ خرید فی دانه (افغانی): <span style="color: #ef4444;">*</span></label>
                        <input type="number" step="any" id="m_purchase_price" name="purchase_price" class="form-control" min="0" required placeholder="قیمت خرید" oninput="recalcMudarabaCapital()">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;">نرخ فروش فی دانه (افغانی):</label>
                        <input type="number" step="any" name="sale_price" class="form-control" min="0" placeholder="قیمت فروش">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;">مصارف سرجمع فی دانه:</label>
                        <input type="number" step="any" id="m_expenses" name="expenses" class="form-control" min="0" value="0" placeholder="کرایه، گمرک" oninput="recalcMudarabaCapital()">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;">کشور مبدأ خریداری:</label>
                        <select name="country_source" class="form-control">
                            <option value="دوبی (امارات)">دوبی (امارات)</option>
                            <option value="چین">چین</option>
                            <option value="ترکیه">ترکیه</option>
                            <option value="ایران">ایران</option>
                            <option value="پاکستان">پاکستان</option>
                            <option value="جاپان">جاپان</option>
                            <option value="سایر">سایر کشورها</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;">موقعیت در گدام/دکان:</label>
                        <input type="text" name="warehouse_location" class="form-control" placeholder="قفسه A، الماری ۱...">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 10px; margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">بارکد یا سریال / IMEI جنس (اختیاری):</label>
                    <input type="text" name="barcode" class="form-control" placeholder="بارکد یا IMEI">
                </div>
            </div>

            <!-- Section 3: Financials & Shariah Profit Split -->
            <div style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 8px; padding: 14px; margin-bottom: 14px;">
                <div style="font-weight: 800; font-size: 13px; color: #581c87; margin-bottom: 8px;">
                    <i class="fa-solid fa-scale-balanced text-primary ml-1"></i> ۳. سرمایه مضاربت و تقسیم مفاد شرعی:
                </div>

                <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;">مجموع سرمایه نقدی شریک (افغانی): <span style="color: #ef4444;">*</span></label>
                        <input type="number" step="any" id="m_capital_amount" name="capital_amount" class="form-control" required style="font-weight: 800; color: #166534; font-size: 14px;">
                        <small style="color: #64748b; font-size: 11px;">* خودبخود از ضرب تعداد در تمام‌شد جنس محاسبه می‌گردد.</small>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;">تاریخ شروع مضاربت:</label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div style="background: #ffffff; border: 1px solid #d8b4fe; border-radius: 8px; padding: 10px;">
                    <span style="font-size: 12px; font-weight: 700; color: #6b21a8; display: block; margin-bottom: 6px;">
                        نسبت توافقی تقسیم سود حاصل از فروش کالا:
                    </span>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <span style="font-size: 11.5px; color: #64748b;">سهم سود شریک (رب‌المال):</span>
                            <div style="display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                                <input type="number" id="mudaraba_p_rate" name="partner_profit_rate" class="form-control" value="50" min="0" max="100" style="text-align: center; font-weight: bold;" oninput="document.getElementById('mudaraba_s_rate').value = Math.max(0, 100 - parseFloat(this.value || 0));">
                                <span style="font-weight: bold;">٪</span>
                            </div>
                        </div>
                        <div>
                            <span style="font-size: 11.5px; color: #64748b;">سهم سود دکان (عامل / مضارب):</span>
                            <div style="display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                                <input type="number" id="mudaraba_s_rate" name="shop_profit_rate" class="form-control" value="50" min="0" max="100" style="text-align: center; font-weight: bold;">
                                <span style="font-weight: bold;">٪</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;">توضیحات و شرایط ویژه قرارداد:</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="توضیحات اختیاری در مورد توافقات، نرخ ارز، نحوه تصفیه..."></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="document.getElementById('addContractModal').style.display='none'" class="btn btn-secondary">
                    انصراف
                </button>
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
                    <i class="fa-solid fa-check ml-1"></i> ثبت قرارداد و ذخیره جنس در گدام
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: ADD EXTRA GOODS TO EXISTING CONTRACT -->
<div id="addStockModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; justify-content: center; align-items: center; padding: 16px;">
    <div style="background: #ffffff; border-radius: 16px; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); padding: 24px; position: relative;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">
                <i class="fa-solid fa-plus text-primary ml-1"></i> ثبت جنس جدید برای قرارداد مضاربت: <span id="add_stock_partner_name" style="color: #0284c7;"></span>
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
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">نام جنس و مدل: <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="product_name" class="form-control" required placeholder="مثلاً: شارژر فست، باتری، قاب...">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">دسته‌بندی:</label>
                    <select name="category_id" class="form-control">
                        <option value="">-- بدون دسته‌بندی --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;">مشخصات جنس (رنگ، مدل، سریال):</label>
                <input type="text" name="specifications" class="form-control" placeholder="مشخصات ظاهری و فنی">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">تعداد (عدد): <span style="color: #ef4444;">*</span></label>
                    <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">نرخ خرید (افغانی): <span style="color: #ef4444;">*</span></label>
                    <input type="number" step="any" name="purchase_price" class="form-control" min="0" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">نرخ فروش (افغانی):</label>
                    <input type="number" step="any" name="sale_price" class="form-control" min="0">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">موقعیت در گدام:</label>
                    <input type="text" name="warehouse_location" class="form-control" placeholder="قفسه، الماری...">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">تاریخ خریداری:</label>
                    <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="document.getElementById('addStockModal').style.display='none'" class="btn btn-secondary">
                    انصراف
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus ml-1"></i> افزودن جنس به گدام
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 3: RECORD SALES PROGRESS -->
<div id="progressModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; justify-content: center; align-items: center; padding: 16px;">
    <div style="background: #ffffff; border-radius: 16px; max-width: 460px; width: 100%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">
                <i class="fa-solid fa-chart-line text-primary ml-1"></i> ثبت دستی فروش و مفاد مضاربت
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
                قرارداد: <strong id="progress_project_title" style="color: #0284c7;"></strong>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;">مبلغ فروش اضافه شده (افغانی):</label>
                <input type="number" step="any" min="0" name="add_sales" class="form-control" required placeholder="مثلاً: ۵۰,۰۰۰">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;">مفاد خالص تحقق‌یافته از این فروش (افغانی):</label>
                <input type="number" step="any" min="0" id="progress_add_profit" name="add_profit" class="form-control" required placeholder="مثلاً: ۸,۰۰۰" style="color: #0284c7; font-weight: bold;" oninput="recalcProgressSplit();">
            </div>

            <div id="progress_split_box" style="display: none; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px; margin-bottom: 14px; font-size: 12px;">
                <div style="color: #166534; font-weight: 700; margin-bottom: 4px;">تقسیم مفاد بر اساس درصد قرارداد:</div>
                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
                    <span>سهم سرمایه‌گذار (<span id="progress_p_rate">۵۰</span>٪): <strong id="progress_p_share" style="color: #7e22ce;">۰</strong> افغانی</span>
                    <span>سهم دکان (<span id="progress_s_rate">۵۰</span>٪): <strong id="progress_s_share" style="color: #166534;">۰</strong> افغانی</span>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="document.getElementById('progressModal').style.display='none'" class="btn btn-secondary">
                    انصراف
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus ml-1"></i> افزودن به آمار فروش
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 4: PAYOUT TO PARTNER -->
<div id="payoutModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; justify-content: center; align-items: center; padding: 16px;">
    <div style="background: #ffffff; border-radius: 16px; max-width: 460px; width: 100%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin: 0;">
                <i class="fa-solid fa-money-bill-wave text-success ml-1"></i> پرداخت و تسویه با شریک مضاربت
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
                شریک: <strong id="payout_partner_name"></strong><br>
                <span style="font-size: 11.5px; color: #64748b;">
                    مانده سهم سود: <strong id="payout_rem_profit" style="color: #7e22ce;"></strong> افغانی | 
                    مانده اصل سرمایه: <strong id="payout_rem_cap" style="color: #166534;"></strong> افغانی
                </span>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;">پرداخت سود به شریک (افغانی):</label>
                <input type="number" step="any" min="0" id="payout_profit_inp" name="payout_profit" class="form-control" value="0" oninput="updatePayoutTotal();">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;">استرداد / بازگرداندن اصل سرمایه (افغانی):</label>
                <input type="number" step="any" min="0" id="return_cap_inp" name="return_capital" class="form-control" value="0" oninput="updatePayoutTotal();">
            </div>

            <!-- پیش‌نمایش مجموع کل پرداختی -->
            <div style="background: #ecfdf5; border: 1.5px solid #a7f3d0; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 12.5px; font-weight: 700; color: #065f46;">
                    <i class="fa-solid fa-coins ml-1"></i> مجموع پرداختی به شریک (کسر از سرمایه دکان):
                </span>
                <strong id="payout_total_preview" style="font-size: 15px; font-weight: 900; color: #047857;">۰ افغانی</strong>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;">تاریخ پرداخت / رسید:</label>
                <input type="date" name="payout_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px; margin-bottom: 14px; font-size: 12px; color: #166534;">
                <i class="fa-solid fa-shield-check ml-1"></i> با ثبت پرداخت، این مبلغ مطابق استاندارد حسابداری مستقیماً از سرمایه عمومی دکان کسر و ترازنامه متوازن می‌شود.
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                    <input type="checkbox" name="mark_settled" value="1">
                    <span>قرارداد کاملاً تسویه و خاتمه یافته علامت‌گذاری شود</span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="document.getElementById('payoutModal').style.display='none'" class="btn btn-secondary">
                    انصراف
                </button>
                <button type="submit" class="btn btn-primary" style="background: #10b981; border-color: #10b981;">
                    <i class="fa-solid fa-check ml-1"></i> ثبت پرداخت وجه
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 5: EDIT MUDARABA CONTRACT -->
<div id="editContractModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; justify-content: center; align-items: center; padding: 16px;">
    <div style="background: #ffffff; border-radius: 16px; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); padding: 24px; position: relative;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-pen-to-square text-primary"></i> ویرایش قرارداد مضاربت
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
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">نام شریک (سرمایه‌گذار): <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="partner_name" id="edit_c_partner_name" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">شماره تماس:</label>
                    <input type="text" name="phone" id="edit_c_phone" class="form-control">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;">شرح کالا و عنوان پروژه: <span style="color: #ef4444;">*</span></label>
                <input type="text" name="project_title" id="edit_c_project_title" class="form-control" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">کشور مبدا:</label>
                    <input type="text" name="country_source" id="edit_c_country_source" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">مقدار کل سرمایه (افغانی): <span style="color: #ef4444;">*</span></label>
                    <input type="number" step="any" name="capital_amount" id="edit_c_capital_amount" class="form-control" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">سهم مفاد شریک (٪):</label>
                    <input type="number" step="any" name="partner_profit_rate" id="edit_c_partner_rate" class="form-control" required oninput="document.getElementById('edit_c_shop_rate').value = Math.max(0, 100 - parseFloat(this.value || 0));">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">سهم مفاد دکان (٪):</label>
                    <input type="number" step="any" name="shop_profit_rate" id="edit_c_shop_rate" class="form-control" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">تاریخ شروع:</label>
                    <input type="date" name="start_date" id="edit_c_start_date" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">موعد تسویه:</label>
                    <input type="date" name="settlement_date" id="edit_c_settlement_date" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">وضعیت قرارداد:</label>
                    <select name="status" id="edit_c_status" class="form-control">
                        <option value="active">جاری و فعال</option>
                        <option value="settled">تسویه شده</option>
                        <option value="cancelled">لغو شده</option>
                    </select>
                </div>
            </div>

            <!-- وضعیت مالی و پرداخت‌ها (رسیدها) -->
            <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 10px; padding: 12px; margin-bottom: 14px;">
                <div style="font-weight: 800; font-size: 13px; color: #1e293b; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-coins text-amber-500"></i> مبالغ مالی، سود پرداختی و استرداد سرمایه (رسیدها):
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12px; color: #047857;">
                            سود پرداخت شده به شریک (رسید):
                        </label>
                        <input type="number" step="any" min="0" name="paid_profit_to_partner" id="edit_c_paid_profit" class="form-control" style="font-weight: bold; color: #047857;">
                        <span style="font-size: 11px; color: #64748b;">(مفادی که تا کنون به شریک تسلیم شده)</span>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12px; color: #2563eb;">
                            اصل سرمایه استرداد شده (پس داده شده):
                        </label>
                        <input type="number" step="any" min="0" name="returned_capital" id="edit_c_returned_capital" class="form-control" style="font-weight: bold; color: #2563eb;">
                        <span style="font-size: 11px; color: #64748b;">(بخشی از اصل سرمایه که مسترد شده)</span>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12px; color: #475569;">
                            مجموع فروشات ثبت شده:
                        </label>
                        <input type="number" step="any" min="0" name="total_sales_amount" id="edit_c_total_sales" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12px; color: #475569;">
                            مجموع مفاد کل پروژه:
                        </label>
                        <input type="number" step="any" min="0" name="total_profit_amount" id="edit_c_total_profit" class="form-control">
                    </div>
                </div>
                <div style="margin-top: 8px; font-size: 11.5px; color: #0369a1; background: #e0f2fe; padding: 6px 10px; border-radius: 6px;">
                    <i class="fa-solid fa-info-circle ml-1"></i> با ویرایش مبالغ بالا، سیستم مطابق اصول حسابداری گردش سرمایه دکان را به صورت خودکار متوازن و تنظیم می‌نماید.
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;">یادداشت و توضیحات:</label>
                <input type="text" name="notes" id="edit_c_notes" class="form-control">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="closeEditContractModal();" class="btn btn-secondary">
                    انصراف
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check ml-1"></i> ذخیره تغییرات
                </button>
            </div>
        </form>
    </div>
</div>

<script>
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
        countText.textContent = count.toLocaleString('fa-IR');
    } else {
        bar.style.display = 'none';
    }
}

function confirmBulkDeleteMudaraba() {
    const checkboxes = document.querySelectorAll('.mudaraba-chk:checked');
    if (checkboxes.length === 0) {
        alert('لطفاً حداقل یک قرارداد مضاربت را برای حذف انتخاب نمایید.');
        return;
    }

    if (!confirm(`آیا از حذف تعداد ${checkboxes.length} قرارداد مضاربت انتخاب شده به صورت گروهی اطمینان کامل دارید؟ اجناس گدام از این مضاربت‌ها جدا خواهند شد.`)) {
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
        document.getElementById('progress_p_share').textContent = Math.round(pShare).toLocaleString('fa-IR');
        document.getElementById('progress_s_share').textContent = Math.round(sShare).toLocaleString('fa-IR');
    } else {
        box.style.display = 'none';
    }
}

function openPayoutModalFromBtn(btn) {
    try {
        const data = JSON.parse(btn.getAttribute('data-payout') || '{}');
        document.getElementById('payout_contract_id').value = data.id || 0;
        document.getElementById('payout_partner_name').textContent = data.partner_name || '';
        document.getElementById('payout_rem_profit').textContent = (data.rem_profit || 0).toLocaleString('fa-IR');
        document.getElementById('payout_rem_cap').textContent = (data.rem_cap || 0).toLocaleString('fa-IR');
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
    document.getElementById('payout_rem_profit').textContent = (remProfit || 0).toLocaleString('fa-IR');
    document.getElementById('payout_rem_cap').textContent = (remCap || 0).toLocaleString('fa-IR');
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
    if (el) el.textContent = Math.round(tot).toLocaleString('fa-IR') + ' افغانی';
}

// Auto open specific contract stock if requested via query param
<?php if (!empty($_GET['contract_id'])): ?>
window.addEventListener('DOMContentLoaded', function() {
    toggleContractStock(<?= (int)$_GET['contract_id'] ?>);
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
