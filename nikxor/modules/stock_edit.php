<?php
/**
 * NiKXor - Mobile Shop Management System
 * Edit Product in Stock / گدام
 * URL: http://localhost/nikxor/modules/stock_edit.php?id=X
 */

$pageTitle = 'ویرایش مشخصات جنس در گدام';
require_once __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM stock WHERE id = :id");
$stmt->execute([':id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    set_flash('جنس مورد نظر در گدام یافت نشد.', 'danger');
    redirect(BASE_URL . 'modules/stock.php');
}

$error = '';
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$partners = $pdo->query("SELECT * FROM partners WHERE status = 'active' ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست، لطفاً دوباره ارسال کنید.';
    } else {
        $name              = trim($_POST['name'] ?? '');
        $categoryId        = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $specifications    = trim($_POST['specifications'] ?? '');
        $quantity          = (int)normalize_digits($_POST['quantity'] ?? '0');
        $purchasePrice     = (float)normalize_digits($_POST['purchase_price'] ?? '0');
        $salePrice         = (float)normalize_digits($_POST['sale_price'] ?? '0');
        $expenses          = (float)normalize_digits($_POST['expenses'] ?? '0');
        $countryOfOrigin   = trim($_POST['country_of_origin'] ?? 'چین');
        $supplierName      = trim($_POST['supplier_name'] ?? '');
        $warehouseLocation = trim($_POST['warehouse_location'] ?? '');
        $partnerId         = !empty($_POST['partner_id']) ? (int)$_POST['partner_id'] : null;
        $purchaseDate      = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : date('Y-m-d');

        $capitalAmount = ($purchasePrice + $expenses) * $quantity;

        if (empty($name)) {
            $error = 'نام جنس نمی تواند خالی باشد.';
        } elseif ($quantity < 0) {
            $error = 'تعداد موجودی نامعتبر است.';
        } else {
            try {
                $updStmt = $pdo->prepare("UPDATE stock SET 
                    name = :name,
                    category_id = :cat_id,
                    specifications = :spec,
                    quantity = :qty,
                    purchase_price = :buy,
                    sale_price = :sale,
                    expenses = :exp,
                    country_of_origin = :orig,
                    supplier_name = :supp,
                    warehouse_location = :loc,
                    capital_amount = :cap,
                    partner_id = :pid,
                    purchase_date = :pdate
                    WHERE id = :id");

                $updStmt->execute([
                    ':name'   => $name,
                    ':cat_id' => $categoryId,
                    ':spec'   => $specifications,
                    ':qty'    => $quantity,
                    ':buy'    => $purchasePrice,
                    ':sale'   => $salePrice,
                    ':exp'    => $expenses,
                    ':orig'   => $countryOfOrigin,
                    ':supp'   => $supplierName,
                    ':loc'    => $warehouseLocation,
                    ':cap'    => $capitalAmount,
                    ':pid'    => $partnerId,
                    ':pdate'  => $purchaseDate,
                    ':id'     => $id
                ]);

                log_activity($currentUser['id'], 'ویرایش جنس', "ویرایش مشخصات جنس «{$name}» با شناسه {$id}");
                set_flash("مشخصات جنس «{$name}» با موفقیت به روز گردید.", 'success');
                redirect(BASE_URL . 'modules/stock.php');
            } catch (Exception $e) {
                $error = 'خطا در ویرایش: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="card" style="max-width: 900px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-pen-to-square ml-1"></i> <?= __('edit_stock_title') ?>: <?= sanitize($item['name']) ?>
        </h3>
        <a href="<?= BASE_URL ?>modules/stock.php" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left ml-1"></i> <?= __('back_to_list') ?>
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?= sanitize($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrf_field() ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="name"><?= __('product_name_label') ?> <span style="color: #ef4444;">*</span></label>
                <input type="text" id="name" name="name" class="form-control" required 
                       value="<?= sanitize($_POST['name'] ?? $item['name']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="category_id"><?= __('category_label') ?></label>
                <select id="category_id" name="category_id" class="form-control">
                    <option value=""><?= $currentLang === 'en' ? '-- Uncategorized --' : ($currentLang === 'ps' ? '-- بې کټګورۍ --' : '-- بدون دسته بندی --') ?></option>
                    <?php 
                    $selectedCat = $_POST['category_id'] ?? $item['category_id'];
                    foreach ($categories as $cat): 
                    ?>
                        <option value="<?= $cat['id'] ?>" <?= ($selectedCat == $cat['id']) ? 'selected' : '' ?>>
                            <?= sanitize($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="warehouse_location"><?= __('stock_location_label') ?></label>
                <input type="text" id="warehouse_location" name="warehouse_location" class="form-control" 
                       value="<?= sanitize($_POST['warehouse_location'] ?? $item['warehouse_location']) ?>">
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="specifications"><?= __('specs_label') ?></label>
                <textarea id="specifications" name="specifications" class="form-control" rows="2"><?= sanitize($_POST['specifications'] ?? $item['specifications']) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="quantity"><?= __('stock_qty_label') ?> <span style="color: #ef4444;">*</span></label>
                <input type="number" id="quantity" name="quantity" class="form-control numeric-input qty-input" min="0" required 
                       value="<?= sanitize($_POST['quantity'] ?? $item['quantity']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="purchase_price"><?= __('purchase_price_label') ?> (<?= get_currency_label() ?>): <span style="color: #ef4444;">*</span></label>
                <input type="number" step="any" id="purchase_price" name="purchase_price" class="form-control numeric-input price-input" min="0" required 
                       value="<?= sanitize($_POST['purchase_price'] ?? $item['purchase_price']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="sale_price"><?= __('selling_price_label') ?> (<?= get_currency_label() ?>):</label>
                <input type="number" step="any" id="sale_price" name="sale_price" class="form-control numeric-input" min="0" 
                       value="<?= sanitize($_POST['sale_price'] ?? ($item['sale_price'] ?? '')) ?>" placeholder="<?= __('selling_price_placeholder') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="expenses"><?= __('extra_expenses_label') ?></label>
                <input type="number" step="any" id="expenses" name="expenses" class="form-control numeric-input" min="0" 
                       value="<?= sanitize($_POST['expenses'] ?? $item['expenses']) ?>">
            </div>

            <!-- Live Cost & Profit Calculation Card (محاسبه زنده نرخ تمام شد و مفاد) -->
            <div id="livePricingBox" style="grid-column: 1 / -1; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 10px; padding: 14px; margin-top: 4px;">
                <div style="font-weight: 800; font-size: 13px; color: #1e293b; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                    <span><i class="fa-solid fa-calculator text-primary ml-1"></i> <?= __('live_calc_title') ?></span>
                    <span id="marginBadge" class="badge" style="background: #e2e8f0; color: #475569; font-size: 11px;"><?= $currentLang === 'en' ? 'Calculating...' : ($currentLang === 'ps' ? 'حساب روان دی' : 'در حال محاسبه') ?></span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px;">
                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px;">
                        <span style="font-size: 11px; color: #64748b; display: block;"><?= __('unit_cost_label') ?></span>
                        <strong id="liveUnitCost" style="font-size: 15px; color: #0f172a;">۰ افغانی</strong>
                        <small style="font-size: 10px; color: #94a3b8; display: block;"><?= __('unit_cost_hint') ?></small>
                    </div>
                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px;">
                        <span style="font-size: 11px; color: #64748b; display: block;"><?= __('unit_profit_label') ?></span>
                        <strong id="liveUnitProfit" style="font-size: 15px; color: #15803d;">۰ افغانی</strong>
                        <small style="font-size: 10px; color: #94a3b8; display: block;"><?= __('unit_profit_hint') ?></small>
                    </div>
                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px;">
                        <span style="font-size: 11px; color: #64748b; display: block;"><?= __('total_capital_label') ?></span>
                        <strong id="liveTotalCapital" style="font-size: 15px; color: #0284c7;">۰ افغانی</strong>
                        <small style="font-size: 10px; color: #94a3b8; display: block;"><?= __('total_capital_hint') ?></small>
                    </div>
                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px;">
                        <span style="font-size: 11px; color: #64748b; display: block;"><?= __('total_profit_label') ?></span>
                        <strong id="liveTotalProfit" style="font-size: 15px; color: #16a34a;">۰ افغانی</strong>
                        <small style="font-size: 10px; color: #94a3b8; display: block;"><?= __('total_profit_hint') ?></small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="country_of_origin"><?= __('country_origin_label') ?></label>
                <input type="text" id="country_of_origin" name="country_of_origin" class="form-control" 
                       value="<?= sanitize($_POST['country_of_origin'] ?? $item['country_of_origin']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="supplier_name"><?= __('supplier_name_label') ?></label>
                <input type="text" id="supplier_name" name="supplier_name" class="form-control" 
                       value="<?= sanitize($_POST['supplier_name'] ?? $item['supplier_name']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="partner_id"><?= __('related_partner_label') ?></label>
                <select id="partner_id" name="partner_id" class="form-control">
                    <option value=""><?= __('general_shop_capital') ?></option>
                    <?php 
                    $selectedPartner = $_POST['partner_id'] ?? $item['partner_id'];
                    foreach ($partners as $part): 
                    ?>
                        <option value="<?= $part['id'] ?>" <?= ($selectedPartner == $part['id']) ? 'selected' : '' ?>>
                            <?= sanitize($part['name']) ?> (<?= to_persian_num($part['share_percentage']) ?>٪ سهم)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="purchase_date"><?= __('purchase_date_label') ?></label>
                <input type="date" id="purchase_date" name="purchase_date" class="form-control" 
                       value="<?= sanitize($_POST['purchase_date'] ?? $item['purchase_date']) ?>">
            </div>
        </div>

        <div style="margin-top: 24px; display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
                <i class="fa-solid fa-check"></i> <?= __('btn_save_changes') ?>
            </button>
            <a href="<?= BASE_URL ?>modules/stock.php" class="btn btn-secondary"><?= __('cancel') ?></a>
        </div>
    </form>
</div>

<script>
function updateLivePricing() {
    var qty = parseFloat(document.getElementById('quantity').value) || 0;
    var buy = parseFloat(document.getElementById('purchase_price').value) || 0;
    var exp = parseFloat(document.getElementById('expenses').value) || 0;
    var sale = parseFloat(document.getElementById('sale_price').value) || 0;

    var unitCost = buy + exp;
    var unitProfit = sale > 0 ? (sale - unitCost) : 0;
    var totalCap = unitCost * qty;
    var totalProf = unitProfit * qty;

    document.getElementById('liveUnitCost').textContent = unitCost.toLocaleString() + ' ' + appCurrency;
    document.getElementById('liveTotalCapital').textContent = totalCap.toLocaleString() + ' ' + appCurrency;

    var pElem = document.getElementById('liveUnitProfit');
    var tpElem = document.getElementById('liveTotalProfit');
    var badge = document.getElementById('marginBadge');

    if (sale <= 0) {
        pElem.textContent = isEnLang ? 'Not set' : 'تعیین‌نشده';
        pElem.style.color = '#64748b';
        tpElem.textContent = isEnLang ? 'Not set' : 'تعیین‌نشده';
        tpElem.style.color = '#64748b';
        badge.textContent = isEnLang ? 'Sale price not entered' : 'نرخ فروش وارد نشده';
        badge.style.background = '#f1f5f9';
        badge.style.color = '#475569';
    } else if (unitProfit >= 0) {
        var pct = unitCost > 0 ? ((unitProfit / unitCost) * 100).toFixed(1) : 0;
        pElem.textContent = '+' + unitProfit.toLocaleString() + ' ' + appCurrency;
        pElem.style.color = '#15803d';
        tpElem.textContent = '+' + totalProf.toLocaleString() + ' ' + appCurrency;
        tpElem.style.color = '#15803d';
        badge.textContent = (isEnLang ? 'Profit: ' : 'مفاد: ') + pct + '% (+' + unitProfit.toLocaleString() + ' ' + appCurrency + ')';
        badge.style.background = '#dcfce7';
        badge.style.color = '#166534';
    } else {
        pElem.textContent = unitProfit.toLocaleString() + ' ' + appCurrency;
        pElem.style.color = '#dc2626';
        tpElem.textContent = totalProf.toLocaleString() + ' ' + appCurrency;
        tpElem.style.color = '#dc2626';
        badge.textContent = (isEnLang ? 'Warning: Loss per unit (' : 'هشدار: ضرر در هر دانه (') + Math.abs(unitProfit).toLocaleString() + ' ' + appCurrency + ')';
        badge.style.background = '#fee2e2';
        badge.style.color = '#991b1b';
    }
}

['quantity', 'purchase_price', 'expenses', 'sale_price'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', updateLivePricing);
        el.addEventListener('change', updateLivePricing);
    }
});

document.addEventListener('DOMContentLoaded', updateLivePricing);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
