<?php
/**
 * NiKXor - Mobile Shop Management System
 * Add Product to Stock / گدام
 * URL: http://localhost/nikxor/modules/stock_add.php
 */

$pageTitle = 'ثبت جنس جدید در گدام';
require_once __DIR__ . '/../includes/header.php';

$error = '';
$success = '';

// Fetch categories, partners & active mudaraba contracts for selection dropdowns
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$partners = $pdo->query("SELECT * FROM partners WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$mudarabaContracts = $pdo->query("SELECT id, partner_name, project_title FROM mudaraba_contracts WHERE status = 'active' ORDER BY id DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست، لطفاً فرم را مجدداً ارسال نمایید.';
    } else {
        $name              = trim($_POST['name'] ?? '');
        $categoryId        = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $specifications    = trim($_POST['specifications'] ?? '');
        $quantity          = (int)normalize_digits($_POST['quantity'] ?? '0');
        $purchasePrice     = (float)normalize_digits($_POST['purchase_price'] ?? '0');
        $salePrice         = (float)normalize_digits($_POST['sale_price'] ?? '0');
        $expenses          = (float)normalize_digits($_POST['expenses'] ?? '0');
        $barcode           = trim($_POST['barcode'] ?? '');
        $countryOfOrigin   = trim($_POST['country_of_origin'] ?? '');
        $supplierName      = trim($_POST['supplier_name'] ?? '');
        $warehouseLocation = trim($_POST['warehouse_location'] ?? '');
        $partnerId         = !empty($_POST['partner_id']) ? (int)$_POST['partner_id'] : null;
        $mudarabaId        = !empty($_POST['mudaraba_id']) ? (int)$_POST['mudaraba_id'] : null;
        $purchaseDate      = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : date('Y-m-d');
        $exactNow          = date('Y-m-d H:i:s');

        // Capital amount = (purchasePrice + expenses) * quantity
        $capitalAmount = ($purchasePrice + $expenses) * $quantity;

        if (empty($name)) {
            $error = 'لطفاً نام جنس را وارد نمایید.';
        } elseif ($quantity < 0) {
            $error = 'تعداد موجودی نمی تواند منفی باشد.';
        } elseif ($purchasePrice < 0) {
            $error = 'نرخ خریداری معتبر نیست.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO stock 
                    (name, category_id, specifications, quantity, purchase_price, sale_price, expenses, barcode, country_of_origin, supplier_name, warehouse_location, capital_amount, partner_id, mudaraba_id, purchase_date, created_at) 
                    VALUES (:name, :cat_id, :spec, :qty, :buy, :sale, :exp, :barcode, :orig, :supp, :loc, :cap, :pid, :mid, :pdate, :cdate)");
                
                $stmt->execute([
                    ':name'    => $name,
                    ':cat_id'  => $categoryId,
                    ':spec'    => $specifications,
                    ':qty'     => $quantity,
                    ':buy'     => $purchasePrice,
                    ':sale'    => $salePrice,
                    ':exp'     => $expenses,
                    ':barcode' => $barcode,
                    ':orig'    => $countryOfOrigin,
                    ':supp'    => $supplierName,
                    ':loc'     => $warehouseLocation,
                    ':cap'     => $capitalAmount,
                    ':pid'     => $partnerId,
                    ':mid'     => $mudarabaId,
                    ':pdate'   => $purchaseDate,
                    ':cdate'   => $exactNow
                ]);


                $newId = $pdo->lastInsertId();
                if ($mudarabaId > 0 && $capitalAmount > 0) {
                    $pdo->prepare("UPDATE mudaraba_contracts SET capital_amount = capital_amount + :cap WHERE id = :mid")
                        ->execute([':cap' => $capitalAmount, ':mid' => $mudarabaId]);
                }
                log_activity($currentUser['id'], 'ثبت جنس جدید', "ثبت جنس «{$name}» به تعداد {$quantity} عدد در گدام");

                set_flash("جنس «{$name}» با موفقیت در گدام ثبت گردید.", 'success');
                redirect(BASE_URL . 'modules/stock.php');
            } catch (Exception $e) {
                $error = 'خطا در ذخیره جنس: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="card" style="max-width: 900px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-plus-circle ml-1"></i> <?= __('register_new_stock_title') ?>
        </h3>
        <a href="<?= BASE_URL ?>modules/stock.php" class="btn btn-secondary btn-sm">
            <i class="fa-solid <?= $currentDir === 'rtl' ? 'fa-arrow-right' : 'fa-arrow-left' ?> ml-1"></i> <?= __('cancel') ?>
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
            <!-- Product Name -->
            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="name">
                    <?= __('product_name_label') ?> <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" id="name" name="name" class="form-control" required 
                       value="<?= sanitize($_POST['name'] ?? '') ?>" 
                       placeholder="<?= __('search_item_placeholder') ?>">
            </div>

            <!-- Category -->
            <div class="form-group">
                <label class="form-label" for="category_id"><?= __('category_label') ?></label>
                <div style="display: flex; gap: 6px;">
                    <select id="category_id" name="category_id" class="form-control">
                        <option value="">-- <?= __('all_categories') ?> --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                <?= sanitize($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <a href="<?= BASE_URL ?>modules/categories.php" class="btn btn-secondary" title="<?= __('manage') ?>" target="_blank">
                        <i class="fa-solid fa-gear"></i>
                    </a>
                </div>
            </div>

            <!-- Warehouse Location -->
            <div class="form-group">
                <label class="form-label" for="warehouse_location" style="display: flex; justify-content: space-between; align-items: center;">
                    <span><?= __('warehouse_loc_label') ?></span>
                    <?php if (is_feature_locked('warehouse_position')): ?>
                        <span class="badge-lock-tag" onclick="showLockModal('warehouse_position', 'Warehouse/Shelf Position', '<?= __('location') ?>');" style="cursor: pointer; font-size: 10px; padding: 1px 6px;"><i class="fa-solid fa-lock text-amber-500"></i> <?= __('locked') ?></span>
                    <?php endif; ?>
                </label>
                <input type="text" id="warehouse_location" name="warehouse_location" class="form-control" 
                       value="<?= sanitize($_POST['warehouse_location'] ?? '') ?>" 
                       placeholder="A1, B2, Shelf...">
            </div>

            <!-- Specifications -->
            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="specifications"><?= __('specs_label') ?></label>
                <textarea id="specifications" name="specifications" class="form-control" rows="2" 
                          placeholder="256GB / 8GB RAM - Black - 100% Battery"><?= sanitize($_POST['specifications'] ?? '') ?></textarea>
            </div>

            <!-- Quantity -->
            <div class="form-group">
                <label class="form-label" for="quantity">
                    <?= __('quantity_label') ?> <span style="color: #ef4444;">*</span>
                </label>
                <input type="number" id="quantity" name="quantity" class="form-control numeric-input qty-input" min="0" required 
                       value="<?= sanitize($_POST['quantity'] ?? '1') ?>">
            </div>

            <!-- Purchase Price -->
            <div class="form-group">
                <label class="form-label" for="purchase_price">
                    <?= sprintf(__('purchase_price_label'), get_currency_label()) ?> <span style="color: #ef4444;">*</span>
                </label>
                <input type="number" step="any" id="purchase_price" name="purchase_price" class="form-control numeric-input price-input" min="0" required 
                       value="<?= sanitize($_POST['purchase_price'] ?? '') ?>" placeholder="0">
            </div>

            <!-- Sale Price -->
            <div class="form-group">
                <label class="form-label" for="sale_price"><?= sprintf(__('sale_price_label'), get_currency_label()) ?></label>
                <input type="number" step="any" id="sale_price" name="sale_price" class="form-control numeric-input" min="0" 
                       value="<?= sanitize($_POST['sale_price'] ?? '') ?>" placeholder="0">
            </div>

            <!-- Expenses per item -->
            <div class="form-group">
                <label class="form-label" for="expenses"><?= __('expenses_label') ?></label>
                <input type="number" step="any" id="expenses" name="expenses" class="form-control numeric-input" min="0" 
                       value="<?= sanitize($_POST['expenses'] ?? '0') ?>" placeholder="0">
            </div>

            <!-- Live Cost & Profit Calculation Card -->
            <div id="livePricingBox" style="grid-column: 1 / -1; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 10px; padding: 14px; margin-top: 4px;">
                <div style="font-weight: 800; font-size: 13px; color: #1e293b; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                    <span><i class="fa-solid fa-calculator text-primary ml-1"></i> <?= __('live_cost_calc_title') ?></span>
                    <span id="marginBadge" class="badge" style="background: #e2e8f0; color: #475569; font-size: 11px;"><?= __('waiting_for_price') ?></span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px;">
                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px;">
                        <span style="font-size: 11px; color: #64748b; display: block;"><?= __('unit_cost_label') ?></span>
                        <strong id="liveUnitCost" style="font-size: 15px; color: #0f172a;">0 <?= get_currency_label() ?></strong>
                        <small style="font-size: 10px; color: #94a3b8; display: block;"><?= __('unit_cost_formula') ?></small>
                    </div>
                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px;">
                        <span style="font-size: 11px; color: #64748b; display: block;"><?= __('est_profit_label') ?></span>
                        <strong id="liveUnitProfit" style="font-size: 15px; color: #15803d;">0 <?= get_currency_label() ?></strong>
                        <small style="font-size: 10px; color: #94a3b8; display: block;"><?= __('est_profit_formula') ?></small>
                    </div>
                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px;">
                        <span style="font-size: 11px; color: #64748b; display: block;"><?= __('total_capital_label') ?></span>
                        <strong id="liveTotalCapital" style="font-size: 15px; color: #0284c7;">0 <?= get_currency_label() ?></strong>
                        <small style="font-size: 10px; color: #94a3b8; display: block;"><?= __('total_capital_formula') ?></small>
                    </div>
                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px;">
                        <span style="font-size: 11px; color: #64748b; display: block;"><?= __('total_batch_profit') ?></span>
                        <strong id="liveTotalProfit" style="font-size: 15px; color: #16a34a;">0 <?= get_currency_label() ?></strong>
                        <small style="font-size: 10px; color: #94a3b8; display: block;"><?= __('total_batch_profit_formula') ?></small>
                    </div>
                </div>
            </div>

            <!-- Barcode / IMEI -->
            <div class="form-group">
                <label class="form-label" for="barcode"><?= __('barcode_label') ?></label>
                <input type="text" id="barcode" name="barcode" class="form-control" 
                       value="<?= sanitize($_POST['barcode'] ?? '') ?>" placeholder="IMEI / Barcode">
            </div>

            <!-- Country of Origin -->
            <div class="form-group">
                <label class="form-label" for="country_of_origin"><?= __('country_origin_label') ?></label>
                <input type="text" id="country_of_origin" name="country_of_origin" class="form-control" 
                       value="<?= sanitize($_POST['country_of_origin'] ?? '') ?>" placeholder="Dubai, China...">
            </div>

            <!-- Supplier -->
            <div class="form-group">
                <label class="form-label" for="supplier_name"><?= __('supplier_label') ?></label>
                <input type="text" id="supplier_name" name="supplier_name" class="form-control" 
                       value="<?= sanitize($_POST['supplier_name'] ?? '') ?>" placeholder="Supplier / Vendor">
            </div>

            <!-- Mudaraba Contract Link -->
            <div class="form-group" style="grid-column: 1 / -1; background: #f0fdf4; border: 1.5px dashed #86efac; padding: 12px; border-radius: 8px;">
                <label class="form-label" for="mudaraba_id" style="color: #047857; font-weight: 800; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-handshake-angle"></i> <?= __('mudaraba_question') ?>
                </label>
                <select id="mudaraba_id" name="mudaraba_id" class="form-control" style="background: white; border-color: #86efac; font-weight: 600;">
                    <option value=""><?= __('mudaraba_default_opt') ?></option>
                    <?php foreach ($mudarabaContracts as $mc): ?>
                        <option value="<?= $mc['id'] ?>" <?= (isset($_POST['mudaraba_id']) && $_POST['mudaraba_id'] == $mc['id']) ? 'selected' : '' ?>>
                            <?= sanitize($mc['partner_name']) ?> (<?= sanitize($mc['project_title']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Associated Partner -->
            <div class="form-group">
                <label class="form-label" for="partner_id"><?= __('nav_partners') ?>:</label>
                <select id="partner_id" name="partner_id" class="form-control">
                    <option value="">-- <?= __('card_shop_capital') ?> --</option>
                    <?php foreach ($partners as $part): ?>
                        <option value="<?= $part['id'] ?>" <?= (isset($_POST['partner_id']) && $_POST['partner_id'] == $part['id']) ? 'selected' : '' ?>>
                            <?= sanitize($part['name']) ?> (<?= to_persian_num($part['share_percentage']) ?>% share)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Purchase Date -->
            <div class="form-group">
                <label class="form-label" for="purchase_date"><?= __('date') ?>:</label>
                <input type="date" id="purchase_date" name="purchase_date" class="form-control" 
                       value="<?= sanitize($_POST['purchase_date'] ?? date('Y-m-d')) ?>">
            </div>
        </div>

        <div style="margin-top: 24px; display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
                <i class="fa-solid fa-floppy-disk"></i> <?= __('save_item_btn') ?>
            </button>
            <a href="<?= BASE_URL ?>modules/stock.php" class="btn btn-secondary"><?= __('cancel') ?></a>
        </div>
    </form>
</div>

<script>
var appCurrency = <?= json_encode(get_currency_label()) ?>;
var isEnLang = <?= json_encode($currentLang === 'en') ?>;

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
        badge.textContent = (isEnLang ? 'Warning: Unit Loss (' : 'هشدار: ضرر در هر دانه (') + Math.abs(unitProfit).toLocaleString() + ' ' + appCurrency + ')';
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
