<?php
/**
 * NiKXor - Mobile Shop Management System
 * Multi-Item Sales POS Module (سیستم صدور بل چند جنسی با سرچ سریع)
 * URL: http://localhost/nikxor/modules/sales.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();

$error = '';
$success = '';

// Generate next invoice number
$nextInvNo = generate_invoice_number();

// Handle Multi-Item Sale Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_sale') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست، لطفاً مجدداً صفحه را بارگذاری و تلاش نمایید.';
    } else {
        $invoiceNo     = trim($_POST['invoice_no'] ?? $nextInvNo);
        $customerName  = trim($_POST['customer_name'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $paymentType   = trim($_POST['payment_type'] ?? 'نقد');
        $paidAmount    = (float)clean_number($_POST['paid_amount'] ?? '0', true);
        $notes         = trim($_POST['notes'] ?? '');
        $itemsJson     = $_POST['items_json'] ?? '';

        if (empty($customerName)) {
            $customerName = 'مشتری آزاد';
        }

        $items = json_decode($itemsJson, true);

        if (!is_array($items) || count($items) === 0) {
            $error = 'لطفاً حداقل یک قلم جنس را از طریق سرچ سریع به بل اضافه کنید.';
        } elseif (count($items) > 30) {
            $error = 'حداکثر تعداد اقلام مجاز در یک بل ۳۰ قلم می‌باشد.';
        } else {
            try {
                $pdo->beginTransaction();

                $grandTotal = 0.0;
                $grandProfit = 0.0;
                $processedItems = [];

                // Validate each item and check stock availability
                foreach ($items as $idx => $it) {
                    $pId   = (int)($it['id'] ?? 0);
                    $qty   = (int)clean_number($it['qty'] ?? 1);
                    $price = (float)clean_number($it['price'] ?? 0, true);

                    if ($pId <= 0 || $qty <= 0) {
                        throw new Exception("اطلاعات ردیف شماره " . ($idx + 1) . " ناقص است.");
                    }

                    // Query product
                    $pStmt = $pdo->prepare("SELECT * FROM stock WHERE id = :id");
                    $pStmt->execute([':id' => $pId]);
                    $prod = $pStmt->fetch();

                    if (!$prod) {
                        throw new Exception("جنس ردیف " . ($idx + 1) . " در دیتابیس یافت نشد.");
                    }

                    if ($prod['quantity'] < $qty) {
                        throw new Exception("موجودی جنس «{$prod['name']}» ناکافی است! موجودی فعلی: {$prod['quantity']} عدد، درخواستی: {$qty} عدد.");
                    }

                    $unitPurchase = (float)$prod['purchase_price'];
                    $unitExpense  = (float)$prod['expenses'];
                    $unitCost     = $unitPurchase + $unitExpense;
                    $lineTotal    = $price * $qty;
                    $lineProfit   = ($price - $unitCost) * $qty;

                    $grandTotal  += $lineTotal;
                    $grandProfit += $lineProfit;

                    $processedItems[] = [
                        'stock_id'   => $pId,
                        'name'       => $prod['name'],
                        'specs'      => $prod['specifications'] ?? '',
                        'qty'        => $qty,
                        'buy_price'  => $unitPurchase,
                        'unit_exp'   => $unitExpense,
                        'sale_price' => $price,
                        'total'      => $lineTotal,
                        'profit'     => $lineProfit
                    ];
                }

                // Calculate payment amounts
                if ($paymentType === 'نقد') {
                    $paidAmount = $grandTotal;
                    $remainingAmount = 0.0;
                } else {
                    if ($paidAmount > $grandTotal) {
                        $paidAmount = $grandTotal;
                    }
                    $remainingAmount = $grandTotal - $paidAmount;
                }

                $exactNow = date('Y-m-d H:i:s');

                // 1. Insert master sales record with exact timestamp
                $saleStmt = $pdo->prepare("INSERT INTO sales 
                    (invoice_no, customer_name, customer_phone, total_amount, paid_amount, remaining_amount, profit, payment_type, notes, created_by, created_at) 
                    VALUES (:inv, :cname, :cphone, :tot, :paid, :rem, :prof, :ptype, :notes, :uid, :cdate)");
                
                $saleStmt->execute([
                    ':inv'   => $invoiceNo,
                    ':cname' => $customerName,
                    ':cphone'=> $customerPhone,
                    ':tot'   => $grandTotal,
                    ':paid'  => $paidAmount,
                    ':rem'   => $remainingAmount,
                    ':prof'  => $grandProfit,
                    ':ptype' => $paymentType,
                    ':notes' => $notes,
                    ':uid'   => $currentUser['id'],
                    ':cdate' => $exactNow
                ]);

                $saleId = $pdo->lastInsertId();

                // 2. Insert items and decrement stock
                $insItemStmt = $pdo->prepare("INSERT INTO sale_items 
                    (sale_id, stock_id, product_name, specifications, quantity, unit_purchase_price, unit_expense, unit_sale_price, total_price, profit) 
                    VALUES (:sid, :stock_id, :pname, :specs, :qty, :buy, :exp, :sprice, :tot, :prof)");

                $updStockStmt = $pdo->prepare("UPDATE stock SET quantity = quantity - :sold WHERE id = :id");

                foreach ($processedItems as $pItem) {
                    $insItemStmt->execute([
                        ':sid'      => $saleId,
                        ':stock_id' => $pItem['stock_id'],
                        ':pname'    => $pItem['name'],
                        ':specs'    => $pItem['specs'],
                        ':qty'      => $pItem['qty'],
                        ':buy'      => $pItem['buy_price'],
                        ':exp'      => $pItem['unit_exp'],
                        ':sprice'   => $pItem['sale_price'],
                        ':tot'      => $pItem['total'],
                        ':prof'     => $pItem['profit']
                    ]);

                    $updStockStmt->execute([
                        ':sold' => $pItem['qty'],
                        ':id'   => $pItem['stock_id']
                    ]);

                    // Automatically accumulate sales & profit in Mudaraba contract if linked
                    if (!empty($pItem['stock_id'])) {
                        $mudCheck = $pdo->prepare("SELECT mudaraba_id FROM stock WHERE id = :id");
                        $mudCheck->execute([':id' => $pItem['stock_id']]);
                        $linkedMudarabaId = $mudCheck->fetchColumn();
                        if ($linkedMudarabaId) {
                            $updMud = $pdo->prepare("UPDATE mudaraba_contracts 
                                SET total_sales_amount = total_sales_amount + :sales, 
                                    total_profit_amount = total_profit_amount + :profit 
                                WHERE id = :mid");
                            $updMud->execute([
                                ':sales'  => $pItem['total'],
                                ':profit' => $pItem['profit'],
                                ':mid'    => $linkedMudarabaId
                            ]);
                        }
                    }
                }

                // 3. Create debt if customer has remaining balance
                if ($remainingAmount > 0) {
                    $debtStmt = $pdo->prepare("INSERT INTO debts 
                        (sale_id, customer_name, customer_phone, total_amount, paid_amount, remaining_amount, status, notes, debt_type, person_type, created_at) 
                        VALUES (:sid, :cname, :cphone, :tot, :paid, :rem, 'باز', :notes, 'receivable', 'customer', :cdate)");
                    
                    $debtStmt->execute([
                        ':sid'    => $saleId,
                        ':cname'  => $customerName,
                        ':cphone' => $customerPhone,
                        ':tot'    => $grandTotal,
                        ':paid'   => $paidAmount,
                        ':rem'    => $remainingAmount,
                        ':notes'  => ($paymentType === 'قسط وار' ? 'فروش قسطی - ' : 'فروش قرضه - ') . "بل چند جنسی {$invoiceNo}",
                        ':cdate'  => $exactNow
                    ]);

                    $debtId = $pdo->lastInsertId();

                    if ($paidAmount > 0) {
                        $instStmt = $pdo->prepare("INSERT INTO installments 
                            (debt_id, amount, payment_date, notes, created_by) 
                            VALUES (:did, :amt, :pdate, :notes, :uid)");
                        $instStmt->execute([
                            ':did'   => $debtId,
                            ':amt'   => $paidAmount,
                            ':pdate' => date('Y-m-d'),
                            ':notes' => 'پیش پرداخت نقد بل ' . $invoiceNo,
                            ':uid'   => $currentUser['id']
                        ]);
                    }
                }

                $pdo->commit();
                log_activity($currentUser['id'], 'فروش چند جنسی', "صدور بل {$invoiceNo} شامل " . count($processedItems) . " قلم به ارزش " . format_money($grandTotal));

                set_flash("بل فروش «{$invoiceNo}» با " . count($processedItems) . " قلم با موفقیت ثبت گردید.", 'success');
                redirect(BASE_URL . "print/invoice.php?id={$saleId}");
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'خطا در ثبت فروش: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all in-stock products for the fast live search engine
$allStockProducts = $pdo->query("SELECT s.id, s.name, s.barcode, s.specifications, s.quantity, s.sale_price, s.purchase_price, s.expenses, s.mudaraba_id, m.partner_name as mudaraba_partner_name, c.name as category_name 
                                  FROM stock s 
                                  LEFT JOIN categories c ON s.category_id = c.id 
                                  LEFT JOIN mudaraba_contracts m ON s.mudaraba_id = m.id 
                                  WHERE s.quantity > 0 
                                  ORDER BY s.name ASC")->fetchAll();

$isProfitSpecsLocked = is_feature_locked('preview_profit_specs');

$pageTitle = 'فروختن جنس (بل چند جنسی جدید)';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1.6fr 0.9fr; gap: 20px; margin-bottom: 24px;">
    <!-- Main POS Section -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title">
                <i class="fa-solid fa-cart-shopping ml-1"></i> <?= __('new_multiproduct_sale') ?>
            </h3>
            <div style="display: flex; align-items: center; gap: 8px;">
                <span class="badge badge-primary" style="font-size: 13px;"><?= sprintf(__('invoice_badge'), sanitize($nextInvNo)) ?></span>
                <span class="badge badge-secondary" style="font-size: 12px;">
                    <i class="fa-regular fa-clock ml-1"></i> <?= date('H:i') ?> - <?= date('Y/m/d') ?>
                </span>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= sanitize($error) ?></span>
            </div>
        <?php endif; ?>

        <form id="posForm" method="POST" action="" onsubmit="return validatePosSubmission();">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_sale">
            <input type="hidden" name="invoice_no" value="<?= sanitize($nextInvNo) ?>">
            <input type="hidden" id="items_json" name="items_json" value="[]">

            <!-- Customer Details Header (Standard Clean Inputs) -->
            <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 14px; margin-bottom: 16px;">
                <div class="form-group">
                    <label class="form-label" for="customer_name"><?= __('buyer_name_label') ?></label>
                    <input type="text" id="customer_name" name="customer_name" class="form-control" 
                           placeholder="<?= __('customer_name') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="customer_phone"><?= __('buyer_phone_label') ?></label>
                    <input type="text" id="customer_phone" name="customer_phone" class="form-control numeric-input" 
                           placeholder="07xxxxxxxx">
                </div>
            </div>

            <!-- Fast Live Search Bar (Persian, English & Pashto) -->
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>
                        <i class="fa-solid fa-magnifying-glass ml-1" style="color: #0284c7;"></i>
                        <strong><?= __('fast_search_label') ?></strong>
                    </span>
                    <span style="font-size: 11.5px; color: #64748b;"><?= __('fast_search_sub') ?></span>
                </label>
                
                <div class="pos-search-wrapper">
                    <div style="position: relative;">
                        <input type="text" id="pos_search_input" class="form-control" 
                               placeholder="<?= __('pos_search_placeholder') ?>" 
                               autocomplete="off" style="padding-left: 36px; font-size: 14.5px; border: 2px solid #38bdf8;">
                        <i class="fa-solid fa-barcode" style="position: absolute; <?= $currentDir === 'rtl' ? 'left: 12px;' : 'right: 12px;' ?> top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 16px;"></i>
                    </div>
                    <!-- Live Search Dropdown -->
                    <div id="pos_search_results" class="pos-search-dropdown"></div>
                </div>
            </div>

            <!-- Multi-Item Invoice Table (1 to 20+ items) -->
            <div class="table-responsive" style="border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 16px; min-height: 180px; background: #ffffff;">
                <table class="pos-items-table" id="posTable">
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center;">#</th>
                            <th style="text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;"><?= __('pos_item_name') ?></th>
                            <th style="width: 85px; text-align: center;"><?= __('pos_in_stock') ?></th>
                            <th style="width: 110px; text-align: center;"><?= __('quantity') ?></th>
                            <th style="width: 130px; text-align: center;"><?= sprintf(__('pos_unit_price'), get_currency_label()) ?></th>
                            <th style="width: 120px; text-align: center;"><?= __('pos_total_price') ?></th>
                            <th style="width: 45px; text-align: center;"><?= __('delete') ?></th>
                        </tr>
                    </thead>
                    <tbody id="posTableBody">
                        <tr id="emptyPosRow">
                            <td colspan="7" style="text-align: center; padding: 36px 12px; color: #94a3b8;">
                                <i class="fa-solid fa-basket-shopping" style="font-size: 28px; margin-bottom: 8px; display: block; color: #cbd5e1;"></i>
                                <?= __('empty_pos_notice') ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Additional Invoice Note -->
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="notes"><?= __('invoice_notes_label') ?></label>
                <input type="text" id="notes" name="notes" class="form-control" 
                       placeholder="<?= __('description') ?>">
            </div>
    </div>

    <!-- Financial Calculation & Checkout Column -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
        <!-- Payment & Checkout Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-calculator ml-1"></i> <?= __('checkout_card_title') ?>
                </h3>
            </div>

            <div style="display: flex; flex-direction: column; gap: 14px;">
                <!-- Summary List -->
                <div style="background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 10px;">
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: #64748b;">
                        <span><?= __('total_invoice_items') ?></span>
                        <strong id="posTotalQtyBadge" style="color: #0f172a;">0 <?= __('items') ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 15px; font-weight: 700; border-top: 1px dashed #cbd5e1; padding-top: 8px;">
                        <span><?= __('invoice_grand_total') ?></span>
                        <span id="posGrandTotalTxt" style="color: #0284c7; font-size: 18px;">0 <?= get_currency_label() ?></span>
                    </div>
                </div>

                <!-- Payment Type -->
                <div class="form-group">
                    <label class="form-label" for="payment_type"><?= __('payment_type_label') ?></label>
                    <select id="payment_type" name="payment_type" class="form-control" onchange="onPaymentTypeChange();">
                        <option value="نقد"><?= __('pay_cash') ?></option>
                        <option value="قرض"><?= __('pay_credit') ?></option>
                        <option value="قسط وار"><?= __('pay_installment') ?></option>
                    </select>
                </div>

                <!-- Paid Amount -->
                <div class="form-group" id="paidAmountGroup" style="display: none;">
                    <label class="form-label" for="paid_amount"><?= sprintf(__('paid_cash_amount'), get_currency_label()) ?></label>
                    <input type="text" id="paid_amount" name="paid_amount" class="form-control numeric-input" 
                           placeholder="0" oninput="recalcTotals();">
                </div>

                <!-- Remaining Debt -->
                <div id="remainingDebtBox" style="display: none; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px;">
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: #991b1b;">
                        <span><?= __('customer_balance_due') ?></span>
                        <strong id="posRemainingTxt" style="font-size: 16px;">0 <?= get_currency_label() ?></strong>
                    </div>
                    <span style="font-size: 11px; color: #b91c1c; display: block; margin-top: 4px;">
                        <?= __('balance_auto_debt_note') ?>
                    </span>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center; padding: 14px; font-size: 15px;">
                    <i class="fa-solid fa-receipt ml-1"></i> <?= __('complete_and_print_btn') ?>
                </button>
            </div>
        </div>

        <!-- Profit & Specs Preview Card (Lockable Feature) -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h4 class="card-title" style="font-size: 14px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-chart-line" style="color: #10b981;"></i> <?= __('live_profit_specs_title') ?>
                    <?php if ($isProfitSpecsLocked): ?>
                        <span class="badge-lock-tag" onclick="showLockModal('preview_profit_specs', 'Live Profit & Specs Preview', <?= json_encode(__('live_profit_specs_title')) ?>);">
                            <i class="fa-solid fa-lock"></i> <?= __('locked') ?>
                        </span>
                    <?php endif; ?>
                </h4>
            </div>

            <?php if ($isProfitSpecsLocked): ?>
                <div class="locked-blur-wrapper">
                    <div class="locked-blur-content" style="padding: 12px; display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span><?= __('estimated_trade_profit') ?></span>
                            <strong>+<?= to_persian_num('2,400') ?> <?= get_currency_label() ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span><?= __('gross_profit_margin') ?></span>
                            <strong><?= to_persian_num('14.5') ?>%</strong>
                        </div>
                    </div>
                    <div class="locked-overlay-card" style="padding: 16px;">
                        <h4 style="font-size: 14px;"><i class="fa-solid fa-lock mr-1"></i> <?= __('live_profit_locked') ?></h4>
                        <p class="en-notice" style="font-size: 11.5px; margin-bottom: 8px;">
                            <?= __('feature_locked_desc') ?>
                        </p>
                        <a href="https://wa.me/93783788278?text=Hello%20Ahmad%20Najeem%20Nik,%20I%20would%20like%20to%20unlock%20Live%20Profit%20Preview" target="_blank" class="btn-whatsapp-direct" style="padding: 6px 14px; font-size: 12px;">
                            <i class="fa-brands fa-whatsapp"></i> <?= __('contact_dev_whatsapp') ?>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div style="padding: 6px 0;">
                    <div style="display: flex; justify-content: space-between; font-size: 13.5px; margin-bottom: 6px;">
                        <span style="color: #64748b;"><?= __('live_invoice_profit_label') ?></span>
                        <strong id="posLiveProfitTxt" style="color: #15803d; font-size: 15px;">0 <?= get_currency_label() ?></strong>
                    </div>
                    <div style="font-size: 11px; color: #94a3b8;">
                        <?= __('live_profit_calc_hint') ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    </form>
</div>

<!-- Raw Stock Catalog Data for Fast Client-Side Search -->
<script>
var stockCatalog = <?= json_encode($allStockProducts, JSON_UNESCAPED_UNICODE) ?>;
var currentInvoiceItems = [];

var POS_CURRENCY = <?= json_encode(get_currency_label()) ?>;
var POS_MUDARABA_TAG = <?= json_encode(__('mudaraba_partner_tag')) ?>;
var POS_STOCK_PREFIX = <?= json_encode(__('stock_available_prefix')) ?>;
var POS_UNIT_LABEL = <?= json_encode(get_current_lang() === 'en' ? ' units' : (get_current_lang() === 'ps' ? ' دانې' : ' عدد')) ?>;
var POS_ITEM_LABEL = <?= json_encode(get_current_lang() === 'en' ? ' items' : (get_current_lang() === 'ps' ? ' قلمه' : ' قلم')) ?>;
var POS_EMPTY_SEARCH = <?= json_encode(__('no_items_matched_search')) ?>;
var POS_EMPTY_TABLE = <?= json_encode(__('no_items_in_pos_yet')) ?>;
var POS_PRICE_PLACEHOLDER = <?= json_encode(__('custom_unit_price_placeholder')) ?>;
var POS_DELETE_TITLE = <?= json_encode(__('delete')) ?>;
var POS_ALERT_MAX_STOCK = <?= json_encode(__('max_stock_reached_alert')) ?>;
var POS_ALERT_INSUFFICIENT = <?= json_encode(__('insufficient_stock_alert')) ?>;
var POS_ALERT_ADD_ITEM = <?= json_encode(__('add_at_least_one_item_alert')) ?>;

// DOM References
var searchInput   = document.getElementById('pos_search_input');
var searchResults = document.getElementById('pos_search_results');
var tableBody     = document.getElementById('posTableBody');
var grandTotalTxt = document.getElementById('posGrandTotalTxt');
var totalQtyBadge = document.getElementById('posTotalQtyBadge');
var itemsJsonInput= document.getElementById('items_json');
var paymentTypeSel= document.getElementById('payment_type');
var paidGroup     = document.getElementById('paidAmountGroup');
var paidInput     = document.getElementById('paid_amount');
var remainingBox  = document.getElementById('remainingDebtBox');
var remainingTxt  = document.getElementById('posRemainingTxt');
var liveProfitTxt = document.getElementById('posLiveProfitTxt');

// Fast Live Search (Supports Persian, Pashto, Dari & English)
searchInput.addEventListener('input', function() {
    var query = this.value.trim().toLowerCase();
    if (query.length === 0) {
        searchResults.style.display = 'none';
        searchResults.innerHTML = '';
        return;
    }

    // Filter products
    var matches = stockCatalog.filter(function(p) {
        var nameMatch  = (p.name || '').toLowerCase().includes(query);
        var specMatch  = (p.specifications || '').toLowerCase().includes(query);
        var barMatch   = (p.barcode || '').toLowerCase().includes(query);
        var catMatch   = (p.category_name || '').toLowerCase().includes(query);
        return nameMatch || specMatch || barMatch || catMatch;
    });

    if (matches.length === 0) {
        searchResults.innerHTML = '<div style="padding: 14px; text-align: center; color: #94a3b8; font-size: 13px;">' + POS_EMPTY_SEARCH + '</div>';
        searchResults.style.display = 'block';
        return;
    }

    var html = '';
    matches.slice(0, 15).forEach(function(p) {
        var mudTag = p.mudaraba_partner_name ? ' <span style="background: #ecfdf5; color: #047857; border: 1px solid #6ee7b7; border-radius: 4px; padding: 1px 5px; font-size: 10px; font-weight: 700;">' + POS_MUDARABA_TAG + escapeHtml(p.mudaraba_partner_name) + '</span>' : '';
        html += '<div class="pos-search-item" onclick="addItemToInvoice(' + p.id + ');">' +
                '  <div class="item-info">' +
                '    <span class="item-title">' + escapeHtml(p.name) + mudTag + '</span>' +
                '    <span class="item-meta">' + (p.category_name ? escapeHtml(p.category_name) + ' &bull; ' : '') + POS_STOCK_PREFIX + p.quantity + POS_UNIT_LABEL + (p.specifications ? ' &bull; ' + escapeHtml(p.specifications) : '') + '</span>' +
                '  </div>' +
                '  <div class="item-price">' + formatNumber(p.sale_price) + ' ' + POS_CURRENCY + '</div>' +
                '</div>';
    });

    searchResults.innerHTML = html;
    searchResults.style.display = 'block';
});

// Close search dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.style.display = 'none';
    }
});

// Add Item To Invoice Table
function addItemToInvoice(productId) {
    var product = stockCatalog.find(function(p) { return p.id == productId; });
    if (!product) return;

    // Check if already in table
    var existing = currentInvoiceItems.find(function(it) { return it.id == productId; });
    if (existing) {
        if (existing.qty < product.quantity) {
            existing.qty += 1;
        } else {
            alert(POS_ALERT_MAX_STOCK);
        }
    } else {
        currentInvoiceItems.push({
            id: product.id,
            name: product.name,
            specs: product.specifications || '',
            stock_qty: product.quantity,
            qty: 1,
            price: parseFloat(product.sale_price) || 0,
            unit_cost: (parseFloat(product.purchase_price) || 0) + (parseFloat(product.expenses) || 0)
        });
    }

    searchInput.value = '';
    searchResults.style.display = 'none';
    searchInput.focus();
    renderInvoiceTable();
}

// Remove item from invoice
function removeItemFromInvoice(index) {
    currentInvoiceItems.splice(index, 1);
    renderInvoiceTable();
}

// Update quantity
function updateItemQty(index, val) {
    var item = currentInvoiceItems[index];
    if (!item) return;
    val = ('' + val).replace(/[۰-۹]/g, function(d) { return d.charCodeAt(0) - 1776; });
    var num = parseInt(val, 10);
    if (isNaN(num) || num <= 0) num = 1;
    if (num > item.stock_qty) {
        alert(POS_ALERT_INSUFFICIENT + item.stock_qty);
        num = item.stock_qty;
    }
    item.qty = num;
    var qtyInp = document.getElementById('pos_qty_' + index);
    if (qtyInp && qtyInp.value != num) qtyInp.value = num;
    var lineTotalEl = document.getElementById('line_total_' + index);
    if (lineTotalEl) {
        lineTotalEl.textContent = formatNumber(item.qty * item.price);
    }
    recalcTotals();
}

// Live free price updater - supports unrestricted pricing, decimals, and live calculation
function updateItemPriceLive(index, val) {
    var item = currentInvoiceItems[index];
    if (!item) return;
    val = ('' + val).replace(/[۰-۹]/g, function(d) { return d.charCodeAt(0) - 1776; });
    var num = parseFloat(val);
    if (isNaN(num) || num < 0) num = 0;
    item.price = num;
    var lineTotalEl = document.getElementById('line_total_' + index);
    if (lineTotalEl) {
        lineTotalEl.textContent = formatNumber(item.qty * item.price);
    }
    recalcTotals();
}

// Render Table Rows
function renderInvoiceTable() {
    if (currentInvoiceItems.length === 0) {
        tableBody.innerHTML = '<tr id="emptyPosRow"><td colspan="7" style="text-align: center; padding: 36px 12px; color: #94a3b8;"><i class="fa-solid fa-basket-shopping" style="font-size: 28px; margin-bottom: 8px; display: block; color: #cbd5e1;"></i>' + POS_EMPTY_TABLE + '</td></tr>';
        recalcTotals();
        return;
    }

    var rows = '';
    currentInvoiceItems.forEach(function(item, idx) {
        var lineTotal = item.qty * item.price;
        rows += '<tr>' +
                '  <td style="text-align: center; color: #64748b; font-weight: 700;">' + (idx + 1) + '</td>' +
                '  <td>' +
                '    <div style="font-weight: 700; color: #0f172a; font-size: 13.5px;">' + escapeHtml(item.name) + '</div>' +
                '    ' + (item.specs ? '<div style="font-size: 11px; color: #64748b;">' + escapeHtml(item.specs) + '</div>' : '') +
                '  </td>' +
                '  <td style="text-align: center;"><span class="badge badge-secondary" style="font-size: 11px;">' + item.stock_qty + POS_UNIT_LABEL + '</span></td>' +
                '  <td style="text-align: center;">' +
                '    <div style="display: inline-flex; align-items: center; gap: 4px;">' +
                '      <button type="button" class="btn btn-sm btn-secondary" style="padding: 2px 8px;" onclick="updateItemQty(' + idx + ', ' + (item.qty - 1) + ');">-</button>' +
                '      <input type="number" id="pos_qty_' + idx + '" class="pos-qty-input" value="' + item.qty + '" min="1" max="' + item.stock_qty + '" oninput="updateItemQty(' + idx + ', this.value);">' +
                '      <button type="button" class="btn btn-sm btn-secondary" style="padding: 2px 8px;" onclick="updateItemQty(' + idx + ', ' + (item.qty + 1) + ');">+</button>' +
                '    </div>' +
                '  </td>' +
                '  <td style="text-align: center;">' +
                '    <input type="number" step="any" min="0" id="pos_price_' + idx + '" class="form-control" style="width: 125px; display: inline-block; text-align: center; font-weight: 700; color: #0f172a; padding: 6px 8px; border: 1.5px solid #94a3b8; border-radius: 6px;" value="' + (item.price > 0 ? item.price : '') + '" placeholder="' + POS_PRICE_PLACEHOLDER + '" oninput="updateItemPriceLive(' + idx + ', this.value);">' +
                '  </td>' +
                '  <td style="text-align: center; font-weight: 800; color: #0284c7; font-size: 14px;" id="line_total_' + idx + '">' +
                '    ' + formatNumber(lineTotal) +
                '  </td>' +
                '  <td style="text-align: center;">' +
                '    <button type="button" class="btn btn-sm btn-danger" style="padding: 4px 8px;" onclick="removeItemFromInvoice(' + idx + ');" title="' + POS_DELETE_TITLE + '">' +
                '      <i class="fa-solid fa-trash-can"></i>' +
                '    </button>' +
                '  </td>' +
                '</tr>';
    });

    tableBody.innerHTML = rows;
    recalcTotals();
}

// Recalculate invoice totals
function recalcTotals() {
    var totalQty = 0;
    var grandTotal = 0;
    var totalProfit = 0;

    currentInvoiceItems.forEach(function(item) {
        totalQty += item.qty;
        var lineTotal = item.qty * item.price;
        grandTotal += lineTotal;
        totalProfit += (item.price - item.unit_cost) * item.qty;
    });

    totalQtyBadge.textContent = totalQty + POS_ITEM_LABEL;
    grandTotalTxt.textContent = formatNumber(grandTotal) + ' ' + POS_CURRENCY;
    itemsJsonInput.value = JSON.stringify(currentInvoiceItems);

    if (liveProfitTxt) {
        liveProfitTxt.textContent = formatNumber(totalProfit) + ' ' + POS_CURRENCY;
    }

    var pType = paymentTypeSel.value;
    if (pType === 'نقد') {
        paidGroup.style.display = 'none';
        remainingBox.style.display = 'none';
        paidInput.value = grandTotal;
    } else {
        paidGroup.style.display = 'block';
        remainingBox.style.display = 'block';
        var paid = parseFloat(paidInput.value) || 0;
        var rem = Math.max(0, grandTotal - paid);
        remainingTxt.textContent = formatNumber(rem) + ' ' + POS_CURRENCY;
    }
}

function onPaymentTypeChange() {
    var pType = paymentTypeSel.value;
    if (pType !== 'نقد' && paidInput.value === '') {
        paidInput.value = '0';
    }
    recalcTotals();
}

function validatePosSubmission() {
    if (currentInvoiceItems.length === 0) {
        alert(POS_ALERT_ADD_ITEM);
        searchInput.focus();
        return false;
    }
    return true;
}

function formatNumber(num) {
    return Number(num).toLocaleString('en-US');
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
