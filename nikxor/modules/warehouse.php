<?php
/**
 * NiKXor - Mobile Shop Management System
 * Warehouse Location & Shelf View (نمای گدام و قفسه ها)
 * URL: http://localhost/nikxor/modules/warehouse.php
 */

$pageTitle = 'نمای گدام و موقعیت اجناس';
require_once __DIR__ . '/../includes/header.php';

$isLocked = is_feature_locked('shelf_view');
?>

<?php if ($isLocked): ?>
<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title" style="display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-warehouse ml-1"></i> نمای تفکیکی قفسه‌ها و الماری‌ها
            <span class="badge-lock-tag"><i class="fa-solid fa-lock text-amber-500"></i> قفل می‌باشد</span>
        </h3>
        <a href="<?= BASE_URL ?>modules/stock.php" class="btn btn-secondary btn-sm">بازگشت به گدام</a>
    </div>
</div>

<div class="locked-blur-wrapper">
    <div class="locked-blur-content" style="padding: 20px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
            <div class="card" style="padding: 20px; border: 1px solid #cbd5e1;">
                <h4>الماری ویترین ۱</h4>
                <p>سامسونگ A55 - ۱۰ عدد</p>
                <p>آیفون 13 - ۲ عدد</p>
            </div>
            <div class="card" style="padding: 20px; border: 1px solid #cbd5e1;">
                <h4>الماری لوازم جانبی</h4>
                <p>شارژر ۲۰ وات - ۵۰ عدد</p>
                <p>هندزفری اصل - ۳۰ عدد</p>
            </div>
        </div>
    </div>
    <div class="locked-overlay-card">
        <h3><i class="fa-solid fa-lock mr-2"></i> این گزینه قفل می‌باشد</h3>
        <p style="font-size: 14px; margin-bottom: 8px;">جهت درخواست و فعال‌سازی این گزینه با احمد نجیم نیک تماس بگیرید</p>
        <p class="en-notice">This feature is currently locked. To request access and unlock this feature, please contact <strong>Ahmad Najeem Nik</strong>.</p>
        <a href="https://wa.me/93783788278?text=Hello%20Ahmad%20Najeem%20Nik,%20I%20would%20like%20to%20unlock%20Shelf%20Visual%20View" target="_blank" class="btn-whatsapp-direct">
            <i class="fa-brands fa-whatsapp"></i> واتس‌اپ احمد نجیم نیک (+93783788278)
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; exit; endif; ?>

// Fetch all stock grouped by location
$stmt = $pdo->query("SELECT s.*, c.name as category_name 
                     FROM stock s 
                     LEFT JOIN categories c ON s.category_id = c.id 
                     ORDER BY s.warehouse_location ASC, s.name ASC");
$allStock = $stmt->fetchAll();

$locations = [];
$totalAllVal = 0;
$totalAllQty = 0;

foreach ($allStock as $item) {
    $loc = trim($item['warehouse_location'] ?? 'نامشخص');
    if ($loc === '') $loc = 'تعیین نشده';

    if (!isset($locations[$loc])) {
        $locations[$loc] = [
            'items'     => [],
            'total_qty' => 0,
            'total_val' => 0.00
        ];
    }

    $unitCost = (float)$item['purchase_price'] + (float)$item['expenses'];
    $itemVal = $unitCost * (int)$item['quantity'];

    $locations[$loc]['items'][] = $item;
    $locations[$loc]['total_qty'] += (int)$item['quantity'];
    $locations[$loc]['total_val'] += $itemVal;

    $totalAllQty += (int)$item['quantity'];
    $totalAllVal += $itemVal;
}
?>

<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <h3 class="card-title" style="margin-bottom: 4px;">
                <i class="fa-solid fa-warehouse ml-1"></i> نمای تفکیکی گدام و الماری ها
            </h3>
            <p style="font-size: 12.5px; color: #64748b;">
                تفکیک موقعیت اجناس در الماری ها، ویترین ها و قفسه های دکان (مجموع کل: <?= to_persian_num($totalAllQty) ?> قلم جنس به ارزش <?= format_money($totalAllVal) ?>)
            </p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>modules/stock_add.php" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> ثبت جنس جدید
            </a>
            <a href="<?= BASE_URL ?>modules/stock.php" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-list"></i> لست جدول گدام
            </a>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 20px;">
    <?php if (empty($locations)): ?>
        <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #64748b;">
            هنوز جنسی در گدام ثبت نشده است.
        </div>
    <?php else: ?>
        <?php foreach ($locations as $locName => $locData): ?>
            <div class="card" style="border: 1px solid #cbd5e1; box-shadow: 0 2px 4px rgba(0,0,0,0.03);">
                <div class="card-header" style="background-color: #f8fafc; border-bottom: 2px solid #0f172a; padding: 12px 16px;">
                    <div>
                        <h4 style="font-size: 14px; font-weight: 800; color: #0f172a; margin: 0;">
                            <i class="fa-solid fa-boxes-packing ml-1"></i> <?= sanitize($locName) ?>
                        </h4>
                        <span style="font-size: 11.5px; color: #64748b;">
                            <?= to_persian_num(count($locData['items'])) ?> جنس مختلف
                        </span>
                    </div>
                    <div style="text-align: left;">
                        <span class="badge badge-primary"><?= to_persian_num($locData['total_qty']) ?> عدد</span>
                    </div>
                </div>

                <div style="padding: 12px 0;">
                    <table class="table" style="font-size: 12px; margin: 0;">
                        <thead>
                            <tr>
                                <th>اسم جنس</th>
                                <th style="width: 70px; text-align: center;">موجودی</th>
                                <th style="width: 85px;">نرخ تمام شد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($locData['items'] as $it): 
                                $unitCost = (float)$it['purchase_price'] + (float)$it['expenses'];
                            ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>modules/stock_edit.php?id=<?= $it['id'] ?>" style="color: #0f172a; font-weight: 600; text-decoration: none;">
                                            <?= sanitize($it['name']) ?>
                                        </a>
                                        <div style="font-size: 10px; color: #64748b;">
                                            <?= sanitize($it['category_name'] ?? 'بدون دسته') ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ((int)$it['quantity'] <= 0): ?>
                                            <span class="badge badge-danger">۰</span>
                                        <?php elseif ((int)$it['quantity'] <= 3): ?>
                                            <span class="badge badge-warning"><?= to_persian_num($it['quantity']) ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-success"><?= to_persian_num($it['quantity']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= format_money($unitCost) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="background-color: #f1f5f9; padding: 8px 16px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; font-size: 11.5px; font-weight: 700; color: #0f172a;">
                    <span>ارزش مجموعی موجودی این بخش:</span>
                    <span><?= format_money($locData['total_val']) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
