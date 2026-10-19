<?php
/**
 * NiKXor - Mobile Shop Management System
 * Warehouse Stock Management Module
 * URL: http://localhost/nikxor/modules/stock.php
 */

$pageTitle = 'مدیریت گدام و لست اجناس';
require_once __DIR__ . '/../includes/header.php';

// Handle Item Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('خطای اعتبار سنجی امنیتی نشست!', 'danger');
        redirect(BASE_URL . 'modules/stock.php');
    }

    // 1. Single Item Delete
    if ($_POST['action'] === 'delete') {
        $deleteId = (int)($_POST['id'] ?? 0);
        if ($deleteId > 0) {
            try {
                $pStmt = $pdo->prepare("SELECT name FROM stock WHERE id = :id");
                $pStmt->execute([':id' => $deleteId]);
                $prodName = $pStmt->fetchColumn() ?: "شناسه {$deleteId}";

                // Safely unlink references in sale_items, expenses, and losses so historical records remain intact
                try {
                    $pdo->prepare("UPDATE sale_items SET stock_id = NULL WHERE stock_id = :id")->execute([':id' => $deleteId]);
                } catch (Exception $ex1) {}
                try {
                    $pdo->prepare("UPDATE expenses SET stock_id = NULL WHERE stock_id = :id")->execute([':id' => $deleteId]);
                } catch (Exception $ex2) {}
                try {
                    $pdo->prepare("UPDATE losses SET stock_id = NULL WHERE stock_id = :id")->execute([':id' => $deleteId]);
                } catch (Exception $ex3) {}

                $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                if ($driver === 'sqlite') {
                    $pdo->exec("PRAGMA foreign_keys = OFF;");
                } elseif ($driver === 'mysql') {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                }

                $delStmt = $pdo->prepare("DELETE FROM stock WHERE id = :id");
                $delStmt->execute([':id' => $deleteId]);

                if ($driver === 'sqlite') {
                    $pdo->exec("PRAGMA foreign_keys = ON;");
                } elseif ($driver === 'mysql') {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                }

                log_activity($currentUser['id'], 'حذف جنس از گدام', "حذف جنس «{$prodName}» با شناسه {$deleteId}");
                set_flash("جنس «{$prodName}» با موفقیت از گدام حذف گردید.", 'success');
            } catch (Exception $e) {
                set_flash('خطا در حذف جنس: ' . $e->getMessage(), 'danger');
            }
        }
        redirect(BASE_URL . 'modules/stock.php');
    }

    // Keep Damaged Item in stock (نگهداری جنس با خط‌خوردگی در گدام)
    if ($_POST['action'] === 'keep_damaged') {
        $itemId = (int)($_POST['id'] ?? 0);
        if ($itemId > 0) {
            $pStmt = $pdo->prepare("SELECT name FROM stock WHERE id = :id");
            $pStmt->execute([':id' => $itemId]);
            $prodName = $pStmt->fetchColumn() ?: "شناسه {$itemId}";
            set_flash("جنس «{$prodName}» طبق تایید شما به صورت خط‌خورده در گدام نگهداری شد.", 'info');
        }
        redirect(BASE_URL . 'modules/stock.php');
    }

    // Clear Damage Flag (رفع علامت خط‌خوردگی و خسارت)
    if ($_POST['action'] === 'clear_damage') {
        $itemId = (int)($_POST['id'] ?? 0);
        if ($itemId > 0) {
            $pStmt = $pdo->prepare("SELECT name FROM stock WHERE id = :id");
            $pStmt->execute([':id' => $itemId]);
            $prodName = $pStmt->fetchColumn() ?: "شناسه {$itemId}";
            $pdo->prepare("UPDATE stock SET is_damaged = 0, damage_notes = '' WHERE id = :id")->execute([':id' => $itemId]);
            log_activity($currentUser['id'], 'رفع علامت خسارت جنس', "رفع علامت خط‌خوردگی جنس «{$prodName}» در گدام");
            set_flash("نشان خسارت از جنس «{$prodName}» برداشته شد و به وضعیت عادی بازگشت.", 'success');
        }
        redirect(BASE_URL . 'modules/stock.php');
    }

    // Clear Damaged Quantity (تسویه ضایعات و ابقای موجودی سالم)
    if ($_POST['action'] === 'clear_damaged_qty') {
        $itemId = (int)($_POST['id'] ?? 0);
        if ($itemId > 0) {
            $pStmt = $pdo->prepare("SELECT name, quantity, damaged_qty FROM stock WHERE id = :id");
            $pStmt->execute([':id' => $itemId]);
            $itemData = $pStmt->fetch();
            $prodName = $itemData['name'] ?? "شناسه {$itemId}";
            $dQty = (int)($itemData['damaged_qty'] ?? 0);

            $pdo->prepare("UPDATE stock SET damaged_qty = 0, is_damaged = 0, damage_notes = '' WHERE id = :id")->execute([':id' => $itemId]);
            log_activity($currentUser['id'], 'تسویه ضایعات جنس', "تسویه و پاکسازی ضایعات ({$dQty} عدد) جنس «{$prodName}» در گدام");
            set_flash("ضایعات جنس «{$prodName}» با موفقیت تسویه شد و موجودی سالم در گدام حفظ گردید.", 'success');
        }
        redirect(BASE_URL . 'modules/stock.php');
    }

    // 2. Bulk Delete Selected Items (سلکت کرده حذف کنم)
    if ($_POST['action'] === 'bulk_delete') {
        $selectedIds = $_POST['selected_ids'] ?? [];
        if (!is_array($selectedIds) || empty($selectedIds)) {
            set_flash('هیچ جنسی برای حذف انتخاب نشده است.', 'warning');
            redirect(BASE_URL . 'modules/stock.php');
        }

        $validIds = array_values(array_filter(array_map('intval', $selectedIds), fn($id) => $id > 0));
        if (empty($validIds)) {
            set_flash('شناسه‌های ارسالی معتبر نمی‌باشند.', 'danger');
            redirect(BASE_URL . 'modules/stock.php');
        }

        $placeholders = implode(',', array_fill(0, count($validIds), '?'));
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $pdo->exec("PRAGMA foreign_keys = OFF;");
            } elseif ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            }

            $pdo->beginTransaction();
            try {
                $upd1 = $pdo->prepare("UPDATE sale_items SET stock_id = NULL WHERE stock_id IN ($placeholders)");
                $upd1->execute($validIds);
            } catch (Exception $ex1) {}
            try {
                $upd2 = $pdo->prepare("UPDATE expenses SET stock_id = NULL WHERE stock_id IN ($placeholders)");
                $upd2->execute($validIds);
            } catch (Exception $ex2) {}
            try {
                $upd3 = $pdo->prepare("UPDATE losses SET stock_id = NULL WHERE stock_id IN ($placeholders)");
                $upd3->execute($validIds);
            } catch (Exception $ex3) {}

            $delStmt = $pdo->prepare("DELETE FROM stock WHERE id IN ($placeholders)");
            $delStmt->execute($validIds);
            $delCount = $delStmt->rowCount();
            $pdo->commit();

            if ($driver === 'sqlite') {
                $pdo->exec("PRAGMA foreign_keys = ON;");
            } elseif ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            }

            log_activity($currentUser['id'], 'حذف گروهی اجناس', "حذف {$delCount} قلم جنس انتخاب شده از گدام");
            set_flash("تعداد " . to_persian_num($delCount) . " قلم جنس انتخاب شده با موفقیت از گدام حذف گردید.", 'success');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('خطا در حذف گروهی اجناس: ' . $e->getMessage(), 'danger');
        }
        redirect(BASE_URL . 'modules/stock.php');
    }

    // 3. Delete All Items (پاکسازی کامل گدام - همه را یکجا حذف کنم)
    if ($_POST['action'] === 'delete_all') {
        $confirmPhrase = trim($_POST['confirm_phrase'] ?? '');
        if ($confirmPhrase !== 'حذف همه' && $confirmPhrase !== 'DELETE ALL' && $confirmPhrase !== '1') {
            set_flash('جهت پاکسازی کامل گدام، عبارت تأییدیه «حذف همه» الزامی است.', 'danger');
            redirect(BASE_URL . 'modules/stock.php');
        }

        try {
            $totalDeleted = (int)$pdo->query("SELECT COUNT(*) FROM stock")->fetchColumn();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $pdo->exec("PRAGMA foreign_keys = OFF;");
            } elseif ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            }

            $pdo->beginTransaction();
            try {
                $pdo->exec("UPDATE sale_items SET stock_id = NULL");
            } catch (Exception $ex1) {}
            try {
                $pdo->exec("UPDATE expenses SET stock_id = NULL");
            } catch (Exception $ex2) {}
            try {
                $pdo->exec("UPDATE losses SET stock_id = NULL");
            } catch (Exception $ex3) {}

            $pdo->exec("DELETE FROM stock");
            try {
                $pdo->exec("DELETE FROM sqlite_sequence WHERE name='stock'");
            } catch (Exception $seqEx) {}
            $pdo->commit();

            if ($driver === 'sqlite') {
                $pdo->exec("PRAGMA foreign_keys = ON;");
            } elseif ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            }

            log_activity($currentUser['id'], 'پاکسازی کامل گدام', "حذف یکجای تمام اجناس موجود در گدام ({$totalDeleted} قلم)");
            set_flash("تمام اجناس موجود در گدام (" . to_persian_num($totalDeleted) . " قلم جنس) با موفقیت به طور یکجا حذف شدند.", 'success');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('خطا در پاکسازی گدام: ' . $e->getMessage(), 'danger');
        }
        redirect(BASE_URL . 'modules/stock.php');
    }
}

// Fetch categories for filter
$cats = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Query filtering
$search = trim($_GET['q'] ?? '');
$categoryFilter = (int)($_GET['category_id'] ?? 0);
$filterType = $_GET['filter'] ?? '';

$sql = "SELECT s.*, c.name AS category_name, p.name AS partner_name, 
               m.partner_name AS mudaraba_partner_name, m.project_title AS mudaraba_project_title,
               COALESCE(s.damaged_qty, 0) AS damaged_qty,
               COALESCE(l.loss_count, 0) AS loss_count,
               COALESCE(l.total_lost_qty, 0) AS total_lost_qty,
               l.last_loss_cat
        FROM stock s 
        LEFT JOIN categories c ON s.category_id = c.id 
        LEFT JOIN partners p ON s.partner_id = p.id 
        LEFT JOIN mudaraba_contracts m ON s.mudaraba_id = m.id 
        LEFT JOIN (
            SELECT stock_id, COUNT(*) AS loss_count, SUM(loss_qty) AS total_lost_qty, MAX(category) AS last_loss_cat
            FROM expenses 
            WHERE expense_type = 'ضرر' AND stock_id IS NOT NULL 
            GROUP BY stock_id
        ) l ON s.id = l.stock_id
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (s.name LIKE :q OR s.specifications LIKE :q OR s.supplier_name LIKE :q OR m.partner_name LIKE :q)";
    $params[':q'] = "%{$search}%";
}

if ($categoryFilter > 0) {
    $sql .= " AND s.category_id = :cat_id";
    $params[':cat_id'] = $categoryFilter;
}

if ($filterType === 'low') {
    $sql .= " AND s.quantity <= 3";
} elseif ($filterType === 'mudaraba') {
    $sql .= " AND s.mudaraba_id IS NOT NULL";
} elseif ($filterType === 'shop') {
    $sql .= " AND s.mudaraba_id IS NULL";
} elseif ($filterType === 'damaged') {
    $sql .= " AND (s.is_damaged = 1 OR s.damaged_qty > 0 OR l.loss_count > 0)";
}

$sql .= " ORDER BY s.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Summary stats of current filtered list - calculated on HEALTHY available stock
$totalHealthyQty = 0;
$totalHealthyVal = 0;
$totalDamagedQty = 0;
$totalDamagedVal = 0;
foreach ($items as $it) {
    $unitTotalCost = (float)$it['purchase_price'] + (float)$it['expenses'];
    $hQty = (int)$it['quantity'];
    $dQty = max((int)($it['damaged_qty'] ?? 0), (int)($it['total_lost_qty'] ?? 0));

    $totalHealthyQty += $hQty;
    $totalHealthyVal += ($unitTotalCost * $hQty);
    $totalDamagedQty += $dQty;
    $totalDamagedVal += ($unitTotalCost * $dQty);
}
?>

<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 class="card-title" style="margin-bottom: 4px;">
                <i class="fa-solid fa-boxes-stacked ml-1"></i> <?= __('stock_items_list') ?>
            </h3>
            <p style="font-size: 12.5px; color: #64748b;">
                <?= sprintf(__('stock_total_summary'), '<strong>' . to_persian_num(count($items)) . '</strong>', '<strong style="color: #166534;">' . to_persian_num($totalHealthyQty) . '</strong>', '<strong style="color: #0f172a;">' . format_money($totalHealthyVal) . '</strong>') ?>
                <?php if ($totalDamagedQty > 0): ?>
                    <span style="color: #b91c1c; margin-right: 6px;">
                        <?= sprintf(__('damaged_deduction_stat'), '<strong>' . to_persian_num($totalDamagedQty) . '</strong>', '<strong>' . format_money($totalDamagedVal) . '</strong>') ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="<?= BASE_URL ?>modules/stock_add.php" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> <?= __('btn_add_stock_top') ?>
            </a>
            <button type="button" onclick="openDeleteAllModal()" class="btn btn-danger" style="display: inline-flex; align-items: center; gap: 6px;" title="<?= __('btn_clear_stock_all') ?>">
                <i class="fa-solid fa-trash-can"></i> <?= __('btn_clear_stock_all') ?>
            </button>
            <a href="<?= BASE_URL ?>modules/categories.php" class="btn btn-secondary">
                <i class="fa-solid fa-tags"></i> <?= __('category') ?>
            </a>
            <a href="<?= BASE_URL ?>modules/losses.php" class="btn btn-secondary" style="color: #991b1b; border-color: #fca5a5; background: #fff5f5;" title="<?= __('nav_losses') ?>">
                <i class="fa-solid fa-triangle-exclamation ml-1"></i> <?= __('nav_losses') ?>
            </a>
            <?php if (is_feature_locked('shelf_view')): ?>
                <button type="button" onclick="showLockModal('shelf_view', 'Shelf & Cupboard Visual View', '<?= __('shelf_view_btn') ?>');" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-warehouse"></i> <?= __('shelf_view_btn') ?>
                    <span class="badge-lock-tag" style="padding: 1px 6px; font-size: 10px;"><i class="fa-solid fa-lock text-amber-500"></i> <?= __('locked') ?></span>
                </button>
            <?php else: ?>
                <a href="<?= BASE_URL ?>modules/warehouse.php" class="btn btn-secondary">
                    <i class="fa-solid fa-warehouse"></i> <?= __('shelf_view_btn') ?>
                </a>
            <?php endif; ?>
        </div>

    </div>

    <!-- Search & Filter Form -->
    <form method="GET" action="" style="margin-top: 16px; display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; align-items: end;">
        <div>
            <label class="form-label" style="font-size: 11.5px;"><?= __('search_item_label') ?></label>
            <input type="text" name="q" class="form-control" value="<?= sanitize($search) ?>" placeholder="<?= __('search_item_placeholder') ?>">
        </div>

        <div>
            <label class="form-label" style="font-size: 11.5px;"><?= __('category_label') ?></label>
            <select name="category_id" class="form-control">
                <option value=""><?= __('all_categories') ?></option>
                <?php foreach ($cats as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoryFilter === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= sanitize($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="form-label" style="font-size: 11.5px;"><?= __('filter_stock_type') ?></label>
            <select name="filter" class="form-control">
                <option value=""><?= __('filter_all_stock') ?></option>
                <option value="shop" <?= $filterType === 'shop' ? 'selected' : '' ?>><?= __('filter_shop_only') ?></option>
                <option value="mudaraba" <?= $filterType === 'mudaraba' ? 'selected' : '' ?>><?= __('filter_mudaraba_only') ?></option>
                <option value="low" <?= $filterType === 'low' ? 'selected' : '' ?>><?= __('filter_low_only') ?></option>
                <option value="damaged" <?= $filterType === 'damaged' ? 'selected' : '' ?>><?= __('filter_damaged_only') ?></option>
            </select>
        </div>

        <div>
            <button type="submit" class="btn btn-secondary" style="height: 38px;">
                <i class="fa-solid fa-filter"></i> <?= __('filter') ?>
            </button>
            <?php if ($search !== '' || $categoryFilter > 0 || $filterType !== ''): ?>
                <a href="<?= BASE_URL ?>modules/stock.php" class="btn btn-sm btn-secondary" style="margin-right: 4px;" title="<?= __('cancel') ?>">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Bulk Selection Action Bar -->
<div id="bulkActionBar" class="card" style="display: none; background: #eff6ff; border: 1.5px solid #3b82f6; margin-bottom: 16px; padding: 12px 18px; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.12);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <span style="font-weight: 800; color: #1e3a8a; font-size: 14px;">
                <i class="fa-solid fa-square-check text-blue-600 ml-1"></i> <span id="selectedCountText">0</span> <?= __('items') ?>
            </span>
            <button type="button" onclick="selectAllStockRows(true)" class="btn btn-sm btn-secondary" style="background: #ffffff;">
                <i class="fa-solid fa-list-check ml-1"></i> <?= __('select_all_page') ?>
            </button>
            <button type="button" onclick="selectAllStockRows(false)" class="btn btn-sm btn-secondary" style="background: #ffffff;">
                <i class="fa-solid fa-xmark ml-1"></i> <?= __('deselect_all') ?>
            </button>
        </div>
        <div>
            <button type="button" onclick="openBulkDeleteModal()" class="btn btn-danger btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                <i class="fa-solid fa-trash-can"></i> <?= __('delete_selected') ?>
            </button>
        </div>
    </div>
</div>

<!-- Hidden Form for Bulk Deletion -->
<form id="bulkDeleteForm" method="POST" action="" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk_delete">
    <div id="bulkDeleteInputsContainer"></div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table" id="stockTable">
            <thead>
                <tr>
                    <th style="width: 36px; text-align: center;">
                        <input type="checkbox" id="masterSelect" onchange="toggleMasterSelect(this)" title="<?= __('select_all_page') ?>" style="width: 17px; height: 17px; cursor: pointer;">
                    </th>
                    <th style="width: 35px;">#</th>
                    <th><?= __('product_name') ?></th>
                    <th><?= __('category') ?></th>
                    <th><?= __('specifications') ?></th>
                    <th><?= __('stock_inventory') ?></th>
                    <th><?= __('purchase_price') ?></th>
                    <th><?= __('expenses') ?></th>
                    <th><?= __('unit_cost') ?></th>
                    <th><?= __('total_value') ?></th>
                    <th><?= __('sale_price_and_profit') ?></th>
                    <th>
                        <?= __('location') ?>
                        <?php if (is_feature_locked('warehouse_position')): ?>
                            <span class="badge-lock-tag" onclick="showLockModal('warehouse_position', 'Warehouse/Shelf Position', '<?= __('location') ?>');" style="cursor: pointer; font-size: 10px; padding: 1px 5px;"><i class="fa-solid fa-lock text-amber-500"></i> <?= __('locked') ?></span>
                        <?php endif; ?>
                    </th>
                    <th><?= __('date_time') ?></th>
                    <th><?= __('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="13" style="text-align: center; color: #64748b; padding: 24px;">
                            <?= __('no_items_found') ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $rowNum = 1;
                    foreach ($items as $item): 
                        $unitCost = (float)$item['purchase_price'] + (float)$item['expenses'];
                        $healthyQty = (int)$item['quantity'];
                        $damagedQty = max((int)($item['damaged_qty'] ?? 0), (int)($item['total_lost_qty'] ?? 0));
                        $totalStockVal = $unitCost * $healthyQty; // ONLY healthy stock adds to warehouse asset value
                        $lossStockVal  = $unitCost * $damagedQty;
                        $isFullyDamaged = ($healthyQty <= 0 && $damagedQty > 0);
                    ?>
                        <!-- سطر جنس اصلی (دارای شماره ردیف و دیزاین پاکیزه و مرتب) -->
                        <tr id="stockRow_<?= $item['id'] ?>" style="<?= $isFullyDamaged ? 'background: #fff8f8;' : '' ?>">
                            <td style="text-align: center;">
                                <input type="checkbox" name="stock_ids[]" value="<?= $item['id'] ?>" class="stock-item-chk" onchange="syncSelectionState()" style="width: 17px; height: 17px; cursor: pointer;">
                            </td>
                            <td style="text-align: center; font-weight: 700; color: #64748b;"><?= to_persian_num($rowNum++) ?></td>
                            <td>
                                <div style="font-weight: 700; color: #0f172a; font-size: 13.5px;"><?= sanitize($item['name']) ?></div>
                                <?php if (!empty($item['mudaraba_id']) && !empty($item['mudaraba_partner_name'])): ?>
                                    <div style="margin-top: 4px;">
                                        <a href="<?= BASE_URL ?>modules/mudaraba.php?contract_id=<?= (int)$item['mudaraba_id'] ?>" style="text-decoration: none;" title="<?= __('nav_mudaraba') ?>">
                                            <span class="badge" style="background: #ecfdf5; color: #047857; border: 1px solid #6ee7b7; font-size: 11px; padding: 2px 7px; display: inline-flex; align-items: center; gap: 4px; font-weight: 700;">
                                                <i class="fa-solid fa-handshake-angle"></i> <?= __('mudaraba_partner_tag') ?> <?= sanitize($item['mudaraba_partner_name']) ?>
                                            </span>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($item['is_sample'])): ?>
                                    <span class="badge badge-secondary" style="font-size: 10px; margin-top: 3px;"><?= $currentLang === 'en' ? 'Sample' : 'نمونه' ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-secondary">
                                    <?= sanitize($item['category_name'] ?? ($currentLang === 'en' ? 'Uncategorized' : ($currentLang === 'ps' ? 'بې کټګورۍ' : 'بدون دسته'))) ?>
                                </span>
                            </td>
                            <td style="font-size: 12px; color: #475569; max-width: 180px;">
                                <?= sanitize($item['specifications'] ?: '-') ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <?php if ($healthyQty <= 0): ?>
                                    <span class="badge badge-danger" style="font-weight: 800;"><?= __('stock_out_of_stock') ?></span>
                                <?php elseif ($healthyQty <= 3): ?>
                                    <span class="badge badge-warning" style="font-weight: 800;"><?= display_qty_unit($healthyQty) ?> (<?= __('stock_low_stock_tag') ?>)</span>
                                <?php else: ?>
                                    <span class="badge badge-success" style="font-weight: 800;"><?= display_qty_unit($healthyQty) ?> <?= __('stock_in_stock_tag') ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space: nowrap;"><?= format_money($item['purchase_price']) ?></td>
                            <td style="white-space: nowrap;"><?= format_money($item['expenses']) ?></td>
                            <td style="white-space: nowrap;"><strong><?= format_money($unitCost) ?></strong></td>
                            <td style="white-space: nowrap;">
                                <strong style="color: #0f172a; font-size: 13.5px;"><?= format_money($totalStockVal) ?></strong>
                            </td>
                            <td style="white-space: nowrap;">
                                <?php if ((float)$item['sale_price'] > 0): 
                                    $unitProfit = (float)$item['sale_price'] - $unitCost;
                                ?>
                                    <div style="font-weight: 700; color: #0f172a;"><?= format_money($item['sale_price']) ?></div>
                                    <div style="font-size: 11px; color: <?= $unitProfit >= 0 ? '#15803d' : '#dc2626' ?>; font-weight: 700;">
                                        <?= $unitProfit >= 0 ? '+' : '' ?><?= format_money($unitProfit) ?> <?= __('stock_profit_suffix') ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 11.5px;"><?= __('stock_undefined_price') ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <?php if (is_feature_locked('warehouse_position')): ?>
                                    <span class="badge-lock-tag" onclick="showLockModal('warehouse_position', 'Warehouse/Shelf Position', <?= json_encode(__('warehouse_position') ?: 'موقعیت در گدام') ?>);" style="cursor: pointer; font-size: 11px;">
                                        <i class="fa-solid fa-lock text-amber-500"></i> <?= __('locked') ?>
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 11.5px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">
                                        <?= sanitize(display_stock_location($item['warehouse_location'] ?? 'نامشخص')) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 11.5px; color: #64748b; white-space: nowrap;">
                                <?= !empty($item['created_at']) ? to_persian_num(date('H:i - Y/m/d', strtotime($item['created_at']))) : '-' ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <a href="<?= BASE_URL ?>modules/losses.php?stock_id=<?= $item['id'] ?>" class="btn btn-sm" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 4px 8px;" title="<?= __('btn_add_loss') ?>">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                </a>
                                <a href="<?= BASE_URL ?>modules/stock_edit.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-secondary" title="<?= __('edit_stock_title') ?>">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <form method="POST" action="" style="display: inline-block;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" data-confirm="<?= $currentLang === 'en' ? 'Are you sure you want to delete this item from warehouse?' : ($currentLang === 'ps' ? 'ایا تاسو ډاډه یاست چې دا توکی ړنګ کړئ؟' : 'آیا از حذف این جنس از گدام اطمینان کامل دارید؟') ?>" title="<?= __('delete') ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>

                        <?php if ($damagedQty > 0): ?>
                            <!-- سطر جداگانه ضرر و خسارات -->
                            <tr style="background: #fffafa; border-top: 1px dashed #fecdd3; border-bottom: 1.5px solid #fee2e2;">
                                <td style="text-align: center; color: #cbd5e1;"></td>
                                <td style="text-align: center; color: #cbd5e1; font-size: 11px;">
                                    <i class="fa-solid fa-level-down-alt" style="transform: rotate(90deg); color: #f87171;"></i>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; padding-right: 8px; border-right: 2px solid #ef4444;">
                                        <span class="badge" style="background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; font-size: 10.5px; padding: 1px 7px; font-weight: 700;">
                                            <i class="fa-solid fa-triangle-exclamation ml-1"></i> <?= __('stock_damaged_row_title') ?>
                                        </span>
                                        <span style="color: #991b1b; font-weight: 700; font-size: 12.5px;">
                                            <?= sanitize($item['name']) ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($item['damage_notes']) || !empty($item['last_loss_cat'])): ?>
                                        <div style="font-size: 11px; color: #b91c1c; margin-top: 3px; padding-right: 10px;">
                                            <?= __('stock_damage_cause') ?> <strong><?= sanitize(display_loss_reason($item['damage_notes'] ?: $item['last_loss_cat'])) ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size: 11px; color: #94a3b8;"><?= $currentLang === 'en' ? 'Loss Item' : ($currentLang === 'ps' ? 'تاواني جنس' : 'تفکیک ضایعات') ?></span>
                                </td>
                                <td>
                                    <span style="font-size: 11px; color: #dc2626; font-weight: 600;"><?= $currentLang === 'en' ? 'Stock Deduction' : ($currentLang === 'ps' ? 'له ګودام کموالی' : 'کسری از موجودی اصلی') ?></span>
                                </td>
                                <td style="white-space: nowrap;">
                                    <span class="badge" style="background: #fee2e2; color: #991b1b; border: 1px solid #f87171; font-weight: 800; font-size: 11.5px;">
                                        -<?= display_qty_unit($damagedQty) ?> <?= $currentLang === 'en' ? 'Damaged' : ($currentLang === 'ps' ? 'تاواني' : 'کسر / ضایع') ?>
                                    </span>
                                </td>
                                <td style="color: #94a3b8; font-size: 12px; white-space: nowrap;"><?= format_money($item['purchase_price']) ?></td>
                                <td style="color: #94a3b8; font-size: 12px; white-space: nowrap;"><?= format_money($item['expenses']) ?></td>
                                <td style="color: #94a3b8; font-size: 12px; white-space: nowrap;"><?= format_money($unitCost) ?></td>
                                <td style="white-space: nowrap;">
                                    <strong style="color: #dc2626; font-size: 12.5px;">-<?= format_money($lossStockVal) ?></strong>
                                    <div style="font-size: 9.5px; color: #94a3b8;"><?= $currentLang === 'en' ? '(Loss value)' : ($currentLang === 'ps' ? '(له ګودام کم)' : '(کسر از ارزش گدام)') ?></div>
                                </td>
                                <td style="white-space: nowrap;">
                                    <span style="font-size: 11px; color: #dc2626; background: #fee2e2; padding: 2px 6px; border-radius: 4px;">
                                        <?= $currentLang === 'en' ? 'Damaged Row' : ($currentLang === 'ps' ? 'تاواني برخه' : 'بخش ضایعات') ?>
                                    </span>
                                </td>
                                <td style="font-size: 11px; color: #94a3b8; white-space: nowrap;">—</td>
                                <td style="white-space: nowrap;">
                                    <a href="<?= BASE_URL ?>modules/losses.php" class="btn btn-sm" style="background: #fff1f2; color: #be123c; border: 1px solid #fecdd3; padding: 2px 7px; font-size: 10.5px; font-weight: 700; text-decoration: none;" title="<?= __('stock_view_in_losses') ?>">
                                        <i class="fa-solid fa-arrow-up-right-from-square ml-1"></i> <?= __('stock_view_in_losses') ?>
                                    </a>
                                    <?php if ($healthyQty > 0): ?>
                                        <form method="POST" action="" style="display: inline; margin: 0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="clear_damaged_qty">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn btn-sm" style="background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; padding: 2px 6px; font-size: 10.5px;" title="<?= __('stock_settle_damage_btn') ?>">
                                                <i class="fa-solid fa-broom"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal 1: Confirm Bulk Delete -->
<div id="bulkDeleteModal" class="feature-modal-backdrop" onclick="if(event.target === this) closeBulkDeleteModal();">
    <div class="feature-modal-box" style="max-width: 440px; text-align: <?= get_current_dir() === 'rtl' ? 'right' : 'left' ?>; direction: <?= get_current_dir() ?>;">
        <button type="button" class="feature-modal-close" onclick="closeBulkDeleteModal();">&times;</button>
        <div style="text-align: center; margin-bottom: 12px;">
            <div style="width: 54px; height: 54px; background: #fee2e2; color: #ef4444; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 8px;">
                <i class="fa-solid fa-trash-can"></i>
            </div>
            <h3 style="font-size: 17px; font-weight: 800; color: #991b1b; margin: 0;"><?= __('confirm_bulk_delete_title') ?></h3>
        </div>
        <p style="font-size: 13.5px; color: #334155; line-height: 1.7; margin-bottom: 16px; text-align: center;">
            <?= __('confirm_bulk_delete_desc') ?>
            <br>
            <span style="font-size: 12px; color: #64748b;"><?= __('confirm_bulk_delete_safety_note') ?></span>
        </p>
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button type="button" onclick="executeBulkDeleteSubmit()" class="btn btn-danger" style="flex: 1; padding: 10px; font-weight: 700;">
                <i class="fa-solid fa-check ml-1"></i> <?= __('yes_delete_selected') ?>
            </button>
            <button type="button" onclick="closeBulkDeleteModal()" class="btn btn-secondary" style="flex: 1; padding: 10px;">
                <?= __('cancel_and_return') ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal 2: Confirm Delete All (پاکسازی کامل گدام) -->
<div id="deleteAllModal" class="feature-modal-backdrop" onclick="if(event.target === this) closeDeleteAllModal();">
    <div class="feature-modal-box" style="max-width: 480px; text-align: <?= get_current_dir() === 'rtl' ? 'right' : 'left' ?>; direction: <?= get_current_dir() ?>;">
        <button type="button" class="feature-modal-close" onclick="closeDeleteAllModal();">&times;</button>
        <div style="text-align: center; margin-bottom: 12px;">
            <div style="width: 60px; height: 60px; background: #fee2e2; color: #dc2626; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 8px;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3 style="font-size: 18px; font-weight: 800; color: #991b1b; margin: 0;"><?= __('confirm_delete_all_title') ?></h3>
        </div>
        <div class="alert alert-danger" style="font-size: 13px; line-height: 1.7; margin-bottom: 14px;">
            <?= __('confirm_delete_all_warning') ?>
        </div>
        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_all">
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" style="font-size: 13px; font-weight: 700;">
                    <?= __('confirm_delete_all_prompt') ?>
                </label>
                <input type="text" id="confirmPhraseInput" name="confirm_phrase" class="form-control" placeholder="<?= __('confirm_delete_all_placeholder') ?>" required oninput="checkDeleteAllPhrase(this.value)" autocomplete="off">
            </div>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button type="submit" id="confirmDeleteAllBtn" class="btn btn-danger" style="flex: 1; padding: 10px; font-weight: 700; opacity: 0.5; pointer-events: none;">
                    <i class="fa-solid fa-trash-can ml-1"></i> <?= __('btn_confirm_delete_all') ?>
                </button>
                <button type="button" onclick="closeDeleteAllModal()" class="btn btn-secondary" style="flex: 1; padding: 10px;">
                    <?= __('cancel') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function getSelectedStockCheckboxes() {
    return Array.from(document.querySelectorAll('.stock-item-chk:checked'));
}

function syncSelectionState() {
    const chks = document.querySelectorAll('.stock-item-chk');
    const checked = document.querySelectorAll('.stock-item-chk:checked');
    const bar = document.getElementById('bulkActionBar');
    const countText = document.getElementById('selectedCountText');
    const master = document.getElementById('masterSelect');

    if (countText) {
        countText.textContent = checked.length;
    }

    if (checked.length > 0) {
        bar.style.display = 'block';
    } else {
        bar.style.display = 'none';
    }

    if (master) {
        master.checked = (chks.length > 0 && checked.length === chks.length);
        master.indeterminate = (checked.length > 0 && checked.length < chks.length);
    }
}

function toggleMasterSelect(master) {
    const chks = document.querySelectorAll('.stock-item-chk');
    chks.forEach(c => {
        c.checked = master.checked;
    });
    syncSelectionState();
}

function selectAllStockRows(select) {
    const chks = document.querySelectorAll('.stock-item-chk');
    chks.forEach(c => {
        c.checked = select;
    });
    syncSelectionState();
}

function openBulkDeleteModal() {
    const checked = getSelectedStockCheckboxes();
    if (checked.length === 0) {
        alert(<?= json_encode(__('select_at_least_one_item')) ?>);
        return;
    }
    document.getElementById('modalBulkCountText').textContent = checked.length;
    document.getElementById('bulkDeleteModal').style.display = 'flex';
}

function closeBulkDeleteModal() {
    document.getElementById('bulkDeleteModal').style.display = 'none';
}

function executeBulkDeleteSubmit() {
    const checked = getSelectedStockCheckboxes();
    if (checked.length === 0) return;

    const container = document.getElementById('bulkDeleteInputsContainer');
    container.innerHTML = '';
    checked.forEach(c => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_ids[]';
        input.value = c.value;
        container.appendChild(input);
    });

    document.getElementById('bulkDeleteForm').submit();
}

function openDeleteAllModal() {
    const modal = document.getElementById('deleteAllModal');
    const input = document.getElementById('confirmPhraseInput');
    const btn = document.getElementById('confirmDeleteAllBtn');
    if (input) input.value = '';
    if (btn) {
        btn.style.opacity = '0.5';
        btn.style.pointerEvents = 'none';
    }
    modal.style.display = 'flex';
    setTimeout(() => { if (input) input.focus(); }, 100);
}

function closeDeleteAllModal() {
    document.getElementById('deleteAllModal').style.display = 'none';
}

function checkDeleteAllPhrase(val) {
    const btn = document.getElementById('confirmDeleteAllBtn');
    const trimmed = val.trim();
    if (trimmed === 'حذف همه' || trimmed === 'DELETE ALL' || trimmed === '1') {
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';
    } else {
        btn.style.opacity = '0.5';
        btn.style.pointerEvents = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
