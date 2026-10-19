<?php
/**
 * NiKXor - Mobile Shop Management System
 * Dedicated Trend A5 Invoice / بل رسمی فروشگاه
 * Dimensions: Standard A5 Portrait (148mm x 210mm - Half of A4)
 * Fully customizable via Secret Admin Studio
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Authentication
auth_require();

$saleId = (int)($_GET['id'] ?? 0);
$isDemo = (isset($_GET['demo']) && $_GET['demo'] == '1');
$sale   = null;
$items  = [];

if ($saleId > 0) {
    $stmt = $pdo->prepare("SELECT s.*, u.fullname as staff_name 
                           FROM sales s 
                           LEFT JOIN users u ON s.created_by = u.id 
                           WHERE s.id = :id");
    $stmt->execute([':id' => $saleId]);
    $sale = $stmt->fetch();

    if ($sale) {
        $itemStmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = :sid ORDER BY id ASC");
        $itemStmt->execute([':sid' => $saleId]);
        $items = $itemStmt->fetchAll();
    }
}

// Fallback to demo mode for quick live preview from the admin studio
if (!$sale && ($isDemo || $saleId === 0)) {
    $sale = [
        'id'               => 786,
        'invoice_no'       => 'INV-' . date('Ymd') . '-001',
        'created_at'       => date('Y-m-d H:i:s'),
        'customer_name'    => 'احمد شفیق نوروزی',
        'customer_phone'   => '0788123456',
        'payment_type'     => 'نقد',
        'paid_amount'      => 38500,
        'remaining_amount' => 0,
        'total_amount'     => 38500,
        'notes'            => 'تحویل همراه با کارتن، کابل اصلی و ضمانت‌نامه ۲۴ ساعته تست',
        'created_by'       => 1,
        'staff_name'       => 'مدیر فروش'
    ];
    $items = [
        [
            'product_name'    => 'گوشی سامسونگ گلکسی S23 Ultra',
            'specifications'  => 'رنگ مشکی فانتوم Phantom Black - حافظه 256GB - رم 12GB - ساخت ویتنام اصل',
            'quantity'        => 1,
            'unit_sale_price' => 36500,
            'total_price'     => 36500
        ],
        [
            'product_name'    => 'کیس محافظ ژله ای ضد ضربه + گلس شیشه ای مات',
            'specifications'  => 'Super D Matte 9D Full Cover Armor Case',
            'quantity'        => 1,
            'unit_sale_price' => 2000,
            'total_price'     => 2000
        ]
    ];
}

if (!$sale) {
    die('<div style="font-family: sans-serif; text-align: center; margin-top: 50px; direction: rtl;">بل مورد نظر یافت نشد. <a href="../modules/sales.php">بازگشت به فروشات</a></div>');
}

// Load Customizable Studio Settings
$shopName          = get_setting('shop_name', DEFAULT_SHOP_NAME);
$invoiceTitle      = get_setting('invoice_title', 'بل رسمی فروش کالا و خدمات');
$shopSlogan        = get_setting('shop_slogan', 'مرکز پخش انواع گوشی های هوشمند، لوازم جانبی و پرزه جات اصلی');
$shopLogo          = get_setting('shop_logo', '');
$phoneCentral      = get_setting('shop_phone_central', '0791510046 — اشکان');
$phoneSecondary    = get_setting('shop_phone_secondary', '0747230143 — احمد ضیا');
$showPhoneSec      = (get_setting('invoice_show_phone_secondary', '1') == '1');
$phoneWhatsapp     = get_setting('shop_phone_whatsapp', '008619584867173');
$showWhatsapp      = (get_setting('invoice_show_whatsapp', '1') == '1');
$addressCentral    = get_setting('shop_address_central', 'کابل مقابل پل خشتی موبایل سنتر منزل چهارم دوکان A323');
$addressSecondary  = get_setting('shop_address_secondary', 'موبایل سنتر منزل سوم دوکان A248');
$showAddressSec    = (get_setting('invoice_show_address_secondary', '1') == '1');
$currencyTitle     = get_setting('currency_title', 'افغانی');
$footerNote        = get_setting('invoice_footer_note', 'اجناس الکترونیکی بدون کارتن و فاکتور مسترد یا تعویض نمی‌گردد.');
$showTerms         = (get_setting('invoice_show_terms', '1') == '1');
$thankYouMsg       = get_setting('invoice_thank_you', 'با تشکر از خریداری و حسن اعتماد شما به این فروشگاه');
$showSignatures    = (get_setting('invoice_show_signatures', '1') == '1');
$sigShopText       = get_setting('invoice_signature_shop_text', 'امضاء یا مهر فروشگاه');
$sigCustText       = get_setting('invoice_signature_cust_text', 'امضاء یا شست مشتری');
$showBarcode       = (get_setting('invoice_show_barcode', '1') == '1');

// Visual Settings
$logoPosition      = get_setting('invoice_logo_position', 'right'); // right, center, left, none
$watermarkEnabled  = (get_setting('invoice_watermark_enabled', '1') == '1');
$watermarkOpacity  = get_setting('invoice_watermark_opacity', '0.07');
$watermarkSize     = get_setting('invoice_watermark_size', 'medium');
$accentColor       = get_setting('invoice_accent_color', '#0f172a');
$borderStyle       = get_setting('invoice_border_style', 'modern');

$defaultFormat     = get_setting('invoice_format', 'a5');
$format            = $_GET['format'] ?? $defaultFormat;
$autoPrint         = (int)get_setting('auto_print_invoice', '0');

$saleDate = date('Y/m/d', strtotime($sale['created_at']));
$saleTime = date('H:i:s', strtotime($sale['created_at']));

// Handle Microsoft Word / Doc export
if ((isset($_GET['export']) && $_GET['export'] === 'doc') || ($format === 'doc' && isset($_GET['download']))) {
    header("Content-Type: application/vnd.ms-word; charset=UTF-8");
    header("Content-Disposition: attachment; filename=invoice_" . preg_replace('/[^a-zA-Z0-9_-]/', '', $sale['invoice_no']) . ".doc");
    header("Cache-Control: private, max-age=0, must-revalidate");
    header("Pragma: public");
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="utf-8"><title>بل فروش ' . htmlspecialchars($sale['invoice_no']) . '</title>';
    echo '<style>
        body { font-family: Tahoma, Arial, sans-serif; direction: rtl; text-align: right; margin: 20px; font-size: 11pt; color: #1e293b; }
        .tbl { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .tbl th, .tbl td { border: 1px solid #333333; padding: 6px 8px; font-size: 10pt; text-align: center; }
        .tbl th { background-color: #f1f5f9; font-weight: bold; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid ' . htmlspecialchars($accentColor) . '; padding-bottom: 10px; }
        .meta-table { width: 100%; margin-bottom: 15px; }
        .meta-table td { padding: 4px; font-size: 10pt; }
        .total-box { margin-top: 15px; border: 1px solid #333; padding: 10px; background: #f8fafc; }
    </style></head><body>';
    
    echo '<div class="header">';
    echo '<h1 style="margin: 0; font-size: 18pt; color: ' . htmlspecialchars($accentColor) . ';">' . htmlspecialchars($shopName) . '</h1>';
    echo '<p style="margin: 5px 0; font-size: 11pt;">' . htmlspecialchars($invoiceTitle) . '</p>';
    if (!empty($shopSlogan)) {
        echo '<p style="margin: 2px 0; font-size: 9pt; color: #666;">' . htmlspecialchars($shopSlogan) . '</p>';
    }
    echo '<p style="margin: 4px 0; font-size: 9pt; color: #555;">شماره تماس: ' . htmlspecialchars($phoneCentral) . ($showPhoneSec && !empty($phoneSecondary) ? ' | ' . htmlspecialchars($phoneSecondary) : '') . ($showWhatsapp && !empty($phoneWhatsapp) ? ' | واتساپ: ' . htmlspecialchars($phoneWhatsapp) : '') . '</p>';
    echo '<p style="margin: 2px 0; font-size: 9pt; color: #555;">آدرس: ' . htmlspecialchars($addressCentral) . ($showAddressSec && !empty($addressSecondary) ? ' — ' . htmlspecialchars($addressSecondary) : '') . '</p>';
    echo '</div>';

    echo '<table class="meta-table">';
    echo '<tr>';
    echo '<td><strong>شماره بل:</strong> ' . htmlspecialchars($sale['invoice_no']) . '</td>';
    echo '<td><strong>تاریخ و ساعت:</strong> ' . htmlspecialchars($saleDate) . ' ' . htmlspecialchars($saleTime) . '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td><strong>نام مشتری:</strong> ' . htmlspecialchars($sale['customer_name']) . '</td>';
    echo '<td><strong>شماره تماس:</strong> ' . htmlspecialchars($sale['customer_phone'] ?: '-') . '</td>';
    echo '</tr>';
    echo '</table>';

    echo '<table class="tbl">';
    echo '<thead><tr>';
    echo '<th style="width: 30px;">#</th>';
    echo '<th>شرح کالا و مشخصات</th>';
    echo '<th style="width: 60px;">تعداد</th>';
    echo '<th style="width: 100px;">قیمت فی (' . htmlspecialchars($currencyTitle) . ')</th>';
    echo '<th style="width: 110px;">مجموع (' . htmlspecialchars($currencyTitle) . ')</th>';
    echo '</tr></thead><tbody>';
    
    $idx = 1;
    foreach ($items as $it) {
        echo '<tr>';
        echo '<td>' . $idx++ . '</td>';
        echo '<td style="text-align: right;"><strong>' . htmlspecialchars($it['product_name']) . '</strong>' . (!empty($it['specifications']) ? '<br><small style="color: #666;">' . htmlspecialchars($it['specifications']) . '</small>' : '') . '</td>';
        echo '<td>' . (int)$it['quantity'] . '</td>';
        echo '<td>' . number_format((float)($it['unit_sale_price'] ?? 0)) . '</td>';
        echo '<td>' . number_format((float)($it['total_price'] ?? 0)) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    echo '<div class="total-box">';
    echo '<p style="margin: 3px 0; font-size: 13pt;"><strong>مبلغ کل قابل پرداخت:</strong> ' . number_format($sale['total_amount']) . ' ' . htmlspecialchars($currencyTitle) . '</p>';
    echo '<p style="margin: 3px 0;"><strong>نوع پرداخت:</strong> ' . htmlspecialchars($sale['payment_type']) . '</p>';
    echo '<p style="margin: 3px 0;"><strong>مبلغ پرداخت شده:</strong> ' . number_format($sale['paid_amount']) . ' ' . htmlspecialchars($currencyTitle) . '</p>';
    if ((float)$sale['remaining_amount'] > 0) {
        echo '<p style="margin: 3px 0; color: #b91c1c;"><strong>باقی مانده طلب:</strong> ' . number_format($sale['remaining_amount']) . ' ' . htmlspecialchars($currencyTitle) . '</p>';
    }
    echo '</div>';

    if ($showTerms && !empty($footerNote)) {
        echo '<p style="margin-top: 15px; font-size: 9pt; color: #555; text-align: center;">' . htmlspecialchars($footerNote) . '</p>';
    }
    if (!empty($thankYouMsg)) {
        echo '<p style="margin-top: 5px; font-size: 10pt; font-weight: bold; text-align: center; color: ' . htmlspecialchars($accentColor) . ';">' . htmlspecialchars($thankYouMsg) . '</p>';
    }

    echo '</body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($invoiceTitle) ?> - <?= sanitize($sale['invoice_no']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/print.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="../assets/css/print.css?v=<?= APP_VERSION ?>">
    <style>
        :root {
            --inv-accent: <?= sanitize($accentColor) ?>;
            --inv-accent-light: <?= sanitize($accentColor) ?>15;
            --inv-border-color: #cbd5e1;
        }

        /* Embedded Standalone Print & Invoice CSS - Guarantees 100% beautiful styling even without external CSS */
        <?php
        $inlineCssPath = __DIR__ . '/../assets/css/print.css';
        if (file_exists($inlineCssPath)) {
            echo file_get_contents($inlineCssPath);
        }
        ?>

        /* Standalone button styles for top action bar */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 14px;
            font-size: 12.5px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.15s ease;
            white-space: nowrap;
            font-family: inherit;
        }
        .btn-primary {
            background-color: var(--inv-accent);
            color: #ffffff;
            border-color: var(--inv-accent);
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
        .btn-secondary {
            background-color: #f8fafc;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .btn-secondary:hover {
            background-color: #e2e8f0;
            color: #0f172a;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 11.5px;
        }
    </style>
</head>
<body class="invoice-screen-wrapper">

<!-- Action Buttons Bar (Hidden on Print) -->
<div class="invoice-actions no-print" style="width: <?= ($format === 'thermal') ? '80mm' : '148mm' ?>;">
    <div style="display: flex; gap: 6px; align-items: center;">
        <a href="<?= BASE_URL ?>modules/invoices.php" class="btn btn-secondary btn-sm" title="مشاهده لیست تمام بل‌ها">
            <i class="fa-solid fa-list ml-1"></i> آرشیف بل‌ها
        </a>
        <a href="<?= BASE_URL ?>modules/sales.php" class="btn btn-secondary btn-sm" title="فروش کالا جدید">
            <i class="fa-solid fa-plus ml-1"></i> بل جدید
        </a>
        <a href="<?= BASE_URL ?>admin/secret_panel_786.php" class="btn btn-secondary btn-sm" title="تنظیمات بل در پنل محرمانه" style="background: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1;">
            <i class="fa-solid fa-sliders ml-1"></i> تنظیمات بل
        </a>
    </div>
    <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
        <button type="button" onclick="window.print()" class="btn btn-primary btn-sm" style="background: var(--inv-accent); border-color: var(--inv-accent);" title="چاپ مستقیم یا ذخیره PDF">
            <i class="fa-solid fa-print ml-1"></i> پرینت A5
        </button>
        <a href="?id=<?= $saleId ?>&export=doc<?= $isDemo ? '&demo=1' : '' ?>" class="btn btn-secondary btn-sm" style="background: #1e3a8a; color: #ffffff;" title="دانلود نسخه Word">
            <i class="fa-solid fa-file-word ml-1"></i> Word
        </a>
        <a href="?id=<?= $saleId ?>&format=<?= ($format === 'thermal' ? 'a5' : 'thermal') ?><?= $isDemo ? '&demo=1' : '' ?>" class="btn btn-secondary btn-sm" title="تغییر ظاهر بل">
            <i class="fa-solid fa-receipt ml-1"></i> <?= ($format === 'thermal' ? 'بل A5' : 'فیش ۸۰mm') ?>
        </a>
    </div>
</div>

<!-- A5 Invoice Sheet (148mm x 210mm) -->
<div class="invoice-container border-<?= sanitize($borderStyle) ?>" style="<?= ($format === 'thermal') ? 'width: 80mm; min-height: auto; padding: 4mm;' : '' ?>">

    <!-- Watermark Layer (If Enabled and Logo Exists) -->
    <?php if ($watermarkEnabled && !empty($shopLogo) && file_exists(__DIR__ . '/../uploads/' . $shopLogo)): ?>
        <div class="inv-watermark-wrapper" style="opacity: <?= (float)$watermarkOpacity ?>;">
            <img src="<?= BASE_URL ?>uploads/<?= sanitize($shopLogo) ?>" alt="واترمارک بل" class="inv-watermark-img size-<?= sanitize($watermarkSize) ?>">
        </div>
    <?php endif; ?>

    <div class="inv-content-layer">
        <div>
            <!-- HEADER SECTION -->
            <div class="inv-header logo-<?= sanitize($logoPosition) ?>">
                <?php if ($logoPosition === 'center'): ?>
                    <!-- Center Logo Header -->
                    <div class="inv-brand">
                        <?php if (!empty($shopLogo) && file_exists(__DIR__ . '/../uploads/' . $shopLogo)): ?>
                            <div class="inv-logo-box" style="margin-bottom: 4px;">
                                <img src="<?= BASE_URL ?>uploads/<?= sanitize($shopLogo) ?>" alt="لوگوی فروشگاه">
                            </div>
                        <?php endif; ?>
                        <div class="inv-title">
                            <h1><?= sanitize($shopName) ?></h1>
                            <?php if (!empty($shopSlogan)): ?>
                                <p><?= sanitize($shopSlogan) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="inv-badge-box">
                        <div class="bill-label"><?= sanitize($invoiceTitle) ?></div>
                    </div>
                <?php elseif ($logoPosition === 'left'): ?>
                    <!-- Left Logo Header -->
                    <div class="inv-badge-box">
                        <div class="bill-label"><?= sanitize($invoiceTitle) ?></div>
                    </div>
                    <div class="inv-brand">
                        <div class="inv-title">
                            <h1><?= sanitize($shopName) ?></h1>
                            <?php if (!empty($shopSlogan)): ?>
                                <p><?= sanitize($shopSlogan) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($shopLogo) && file_exists(__DIR__ . '/../uploads/' . $shopLogo)): ?>
                            <div class="inv-logo-box">
                                <img src="<?= BASE_URL ?>uploads/<?= sanitize($shopLogo) ?>" alt="لوگوی فروشگاه">
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($logoPosition === 'none'): ?>
                    <!-- Typographic Header Without Logo -->
                    <div class="inv-brand">
                        <div class="inv-title">
                            <h1><?= sanitize($shopName) ?></h1>
                            <?php if (!empty($shopSlogan)): ?>
                                <p><?= sanitize($shopSlogan) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="inv-badge-box">
                        <div class="bill-label"><?= sanitize($invoiceTitle) ?></div>
                    </div>
                <?php else: ?>
                    <!-- Default Right Logo Header -->
                    <div class="inv-brand">
                        <?php if (!empty($shopLogo) && file_exists(__DIR__ . '/../uploads/' . $shopLogo)): ?>
                            <div class="inv-logo-box">
                                <img src="<?= BASE_URL ?>uploads/<?= sanitize($shopLogo) ?>" alt="لوگوی فروشگاه">
                            </div>
                        <?php else: ?>
                            <div class="inv-logo-box" style="background: var(--inv-accent); color: #ffffff;">
                                <i class="fa-solid fa-mobile-screen-button"></i>
                            </div>
                        <?php endif; ?>
                        <div class="inv-title">
                            <h1><?= sanitize($shopName) ?></h1>
                            <?php if (!empty($shopSlogan)): ?>
                                <p><?= sanitize($shopSlogan) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="inv-badge-box">
                        <div class="bill-label"><?= sanitize($invoiceTitle) ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- CONTACT INFORMATION & ADDRESSES -->
            <div class="inv-meta-grid">
                <div class="inv-meta-col">
                    <p>
                        <strong><i class="fa-solid fa-phone ml-1 text-primary"></i> شماره های تماس:</strong>
                        <span><?= sanitize($phoneCentral) ?></span>
                        <?php if ($showPhoneSec && !empty($phoneSecondary)): ?>
                            <span style="display: block; margin-top: 1px;"><?= sanitize($phoneSecondary) ?></span>
                        <?php endif; ?>
                    </p>
                    <?php if ($showWhatsapp && !empty($phoneWhatsapp)): ?>
                        <p style="margin-top: 2px;">
                            <strong><i class="fa-brands fa-whatsapp ml-1" style="color: #16a34a;"></i> واتساپ:</strong>
                            <span><?= sanitize($phoneWhatsapp) ?></span>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="inv-meta-col">
                    <p>
                        <strong><i class="fa-solid fa-location-dot ml-1 text-primary"></i> آدرس:</strong>
                        <span><?= sanitize($addressCentral) ?></span>
                    </p>
                    <?php if ($showAddressSec && !empty($addressSecondary)): ?>
                        <p style="margin-top: 2px;">
                            <strong>شعبه دوم:</strong>
                            <span><?= sanitize($addressSecondary) ?></span>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- INVOICE DETAILS STRIP (No, Date, Customer, Phone) -->
            <div class="inv-details-strip">
                <div class="inv-detail-item">
                    <span>شماره بل:</span>
                    <strong><?= sanitize($sale['invoice_no']) ?></strong>
                </div>
                <div class="inv-detail-item">
                    <span>تاریخ و ساعت:</span>
                    <strong><?= to_persian_num($saleDate) ?> - <?= to_persian_num($saleTime) ?></strong>
                </div>
                <div class="inv-detail-item">
                    <span>مشتری محترم:</span>
                    <strong><?= sanitize($sale['customer_name']) ?></strong>
                </div>
                <div class="inv-detail-item">
                    <span>شماره تماس:</span>
                    <strong><?= sanitize($sale['customer_phone'] ?: '-') ?></strong>
                </div>
            </div>

            <!-- ITEMS TABLE -->
            <table class="inv-table">
                <thead>
                    <tr>
                        <th style="width: 26px;">#</th>
                        <th>شرح کالا و جنس</th>
                        <th>مشخصات، رنگ و سریال</th>
                        <th style="width: 42px;">تعداد</th>
                        <th style="width: 75px;">قیمت فی (<?= sanitize($currencyTitle) ?>)</th>
                        <th style="width: 80px;">مجموع (<?= sanitize($currencyTitle) ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rNum = 1;
                    foreach ($items as $item): 
                    ?>
                        <tr>
                            <td><?= to_persian_num($rNum++) ?></td>
                            <td class="text-right"><strong><?= sanitize($item['product_name']) ?></strong></td>
                            <td class="text-right" style="font-size: 8.5px; color: #475569;"><?= sanitize($item['specifications'] ?: '-') ?></td>
                            <td><?= to_persian_num($item['quantity']) ?></td>
                            <td><?= format_money($item['unit_sale_price']) ?></td>
                            <td><strong><?= format_money($item['total_price']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- SUMMARY ROW: Payment Status & Grand Total -->
            <div class="inv-summary-row">
                <div class="inv-payment-status">
                    <div>
                        <span style="font-size: 10px; color: #475569;">وضعیت تسویه: </span>
                        <?php if ($sale['payment_type'] === 'نقد'): ?>
                            <span class="payment-badge cash">نقدی (تصفیه کامل)</span>
                        <?php elseif ($sale['payment_type'] === 'قرض'): ?>
                            <span class="payment-badge debt">قرضه</span>
                        <?php else: ?>
                            <span class="payment-badge installment">اقساط</span>
                        <?php endif; ?>
                    </div>

                    <?php if ((float)$sale['remaining_amount'] > 0): ?>
                        <div style="margin-top: 3px; font-size: 10px;">
                            <span>پرداخت نقدی: <strong><?= format_money($sale['paid_amount']) ?></strong></span> | 
                            <span style="color: #b91c1c;">باقی مانده طلب: <strong><?= format_money($sale['remaining_amount']) ?></strong></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($sale['notes'])): ?>
                        <div style="font-size: 9px; color: #475569; margin-top: 3px;">
                            <strong>یادداشت:</strong> <?= sanitize($sale['notes']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="inv-grand-total">
                    <div class="total-label">مجموع کل قابل پرداخت:</div>
                    <div class="total-amount"><?= format_money($sale['total_amount']) ?> <span style="font-size: 10.5px; font-weight: normal;"><?= sanitize($currencyTitle) ?></span></div>
                </div>
            </div>
        </div>

        <!-- BOTTOM SECTION: Terms, Signatures, Barcode & Thank you -->
        <div>
            <?php if ($showTerms && !empty($footerNote)): ?>
                <div class="inv-notes">
                    <i class="fa-solid fa-circle-info ml-1 text-primary"></i> <strong>شرایط و ضوابط:</strong> <?= sanitize($footerNote) ?>
                </div>
            <?php endif; ?>

            <?php if ($showSignatures || $showBarcode): ?>
                <div class="inv-bottom-strip">
                    <?php if ($showSignatures): ?>
                        <div class="inv-signatures">
                            <div class="inv-sig-line">
                                <span><?= sanitize($sigShopText) ?></span>
                                <div class="dots"></div>
                            </div>
                            <div class="inv-sig-line">
                                <span><?= sanitize($sigCustText) ?></span>
                                <div class="dots"></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>

                    <?php if ($showBarcode): ?>
                        <div class="inv-barcode">
                            <div class="barcode-lines">
                                <i style="width: 2px;"></i><i style="width: 3px; background: transparent;"></i>
                                <i style="width: 4px;"></i><i style="width: 2px; background: transparent;"></i>
                                <i style="width: 2px;"></i><i style="width: 1px; background: transparent;"></i>
                                <i style="width: 5px;"></i><i style="width: 3px; background: transparent;"></i>
                                <i style="width: 2px;"></i><i style="width: 2px; background: transparent;"></i>
                                <i style="width: 3px;"></i><i style="width: 1px; background: transparent;"></i>
                                <i style="width: 4px;"></i><i style="width: 2px; background: transparent;"></i>
                                <i style="width: 2px;"></i><i style="width: 3px; background: transparent;"></i>
                                <i style="width: 3px;"></i><i style="width: 1px; background: transparent;"></i>
                                <i style="width: 2px;"></i><i style="width: 4px; background: transparent;"></i>
                                <i style="width: 3px;"></i>
                            </div>
                            <span style="font-size: 8px; font-family: monospace; color: #475569;"><?= sanitize($sale['invoice_no']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($thankYouMsg)): ?>
                <div class="inv-footer-msg">
                    <?= sanitize($thankYouMsg) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($autoPrint === 1): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        window.print();
    }, 500);
});
</script>
<?php endif; ?>

</body>
</html>
