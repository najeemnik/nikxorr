<?php
/**
 * NiKXor - Customers Management Module (مشتریان و حسابات)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();

$pageTitle = 'مدیریت مشتریان و حسابات VIP';
$isLocked = is_feature_locked('customers_module');

// Handle Add Customer (Show purchase warning if locked)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_customer') {
    if ($isLocked) {
        set_flash('امکان ثبت مشتری در نسخه پایه قفل می‌باشد. جهت خرید لایسنس و فعال‌سازی ثبت مشتریان، لطفاً با احمد نجیم نیک (+93783788278) تماس بگیرید.', 'warning');
        redirect(BASE_URL . 'modules/customers.php');
    }

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'خطای امنیتی نشست! لطفاً مجدداً تلاش نمایید.');
        redirect(BASE_URL . 'modules/customers.php');
    }

    $name         = trim($_POST['name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $customerType = trim($_POST['customer_type'] ?? 'پرچون');
    $company      = trim($_POST['company'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');

    if (empty($name)) {
        set_flash('danger', 'نام مشتری الزامی است.');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO customers (name, phone, customer_type, company, address, notes) 
                                   VALUES (:name, :phone, :ctype, :company, :address, :notes)");
            $stmt->execute([
                ':name'    => $name,
                ':phone'   => $phone,
                ':ctype'   => $customerType,
                ':company' => $company,
                ':address' => $address,
                ':notes'   => $notes
            ]);
            log_activity($currentUser['id'], 'افزودن مشتری', "مشتری جدید «{$name}» با شماره «{$phone}» افزوده شد.");
            set_flash('success', "مشتری «{$name}» با موفقیت ثبت گردید.");
        } catch (Exception $e) {
            set_flash('danger', 'خطا در ثبت مشتری: ' . $e->getMessage());
        }
    }
    redirect(BASE_URL . 'modules/customers.php');
}

// Handle Delete Customer (Only if unlocked and admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocked && isset($_POST['action']) && $_POST['action'] === 'delete_customer') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'خطای امنیتی نشست!');
        redirect(BASE_URL . 'modules/customers.php');
    }
    if ($currentUser['role'] !== 'admin') {
        set_flash('danger', 'تنها مدیر سیستم دسترسی به حذف مشتری را دارد.');
        redirect(BASE_URL . 'modules/customers.php');
    }

    $cid = (int)($_POST['customer_id'] ?? 0);
    if ($cid > 0) {
        $cStmt = $pdo->prepare("SELECT name FROM customers WHERE id = :id");
        $cStmt->execute([':id' => $cid]);
        $cRow = $cStmt->fetch();

        if ($cRow) {
            $del = $pdo->prepare("DELETE FROM customers WHERE id = :id");
            $del->execute([':id' => $cid]);
            log_activity($currentUser['id'], 'حذف مشتری', "مشتری «{$cRow['name']}» حذف گردید.");
            set_flash('success', "مشتری «{$cRow['name']}» با موفقیت حذف گردید.");
        }
    }
    redirect(BASE_URL . 'modules/customers.php');
}

// Filters & Search
$search = trim($_GET['search'] ?? '');
$filterType = trim($_GET['type'] ?? '');

$sql = "SELECT c.*, 
        (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE customer_name = c.name) as total_sales,
        (SELECT COALESCE(SUM(remaining_amount), 0) FROM debts WHERE customer_name = c.name AND status != 'پرداخت شده') as total_debt
        FROM customers c WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (c.name LIKE :s1 OR c.phone LIKE :s2 OR c.company LIKE :s3)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
}

if (!empty($filterType)) {
    $sql .= " AND c.customer_type = :ct";
    $params[':ct'] = $filterType;
}

$sql .= " ORDER BY c.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customersList = $stmt->fetchAll();

// Statistics
$totalCustCount = count($customersList);
$wholesaleCount = 0;
$retailCount = 0;
$totalDebtsSum = 0;
foreach ($customersList as $c) {
    if (($c['customer_type'] ?? '') === 'عمده') $wholesaleCount++;
    else $retailCount++;
    $totalDebtsSum += (float)($c['total_debt'] ?? 0);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="customers-module-page" style="position: relative;">

    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-users" style="color: #3b82f6;"></i>
                باشگاه مشتریان و حسابات
                <?php if ($isLocked): ?>
                    <?= render_lock_badge('customers_module', 'نسخه VIP و عمده قفل است') ?>
                <?php endif; ?>
            </h3>
            <span style="font-size: 12px; color: #64748b;">مدیریت کامل خریداران عمده و پرچون، استعلام مطالبات و سابقه معاملات</span>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('addCustomerModal').style.display='flex';">
                <i class="fa-solid fa-user-plus ml-1"></i> ثبت مشتری جدید
            </button>
            <?php if ($isLocked): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="showLockModal('customers_module', 'Advanced Customers & VIP Club', 'مدیریت مشتریان VIP و پیشرفته');">
                    <i class="fa-solid fa-lock ml-1 text-amber-500"></i> لایسنس VIP
                </button>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>modules/debts.php" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-hand-holding-dollar ml-1"></i> مشاهده قرضه‌ها
            </a>
        </div>
    </div>

    <?php if ($isLocked): ?>
        <div style="background: #fffbeb; border: 1px solid #fef3c7; border-right: 4px solid #f59e0b; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-lock" style="color: #d97706; font-size: 16px;"></i>
                <span style="font-size: 12.5px; font-weight: 700; color: #92400e;">
                    ماژول مشتریان در حالت پیش‌نمایش فعال است. جهت ثبت و ذخیره داده‌ها نیاز به تهیه لایسنس دارید.
                </span>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="showLockModal('customers_module', 'Advanced Customers & VIP Club', 'مدیریت مشتریان VIP و پیشرفته');" style="font-size: 11.5px; color: #b45309; border-color: #fcd34d;">
                <i class="fa-solid fa-key ml-1"></i> فعال‌سازی لایسنس
            </button>
        </div>
    <?php endif; ?>

    <!-- Summary Metrics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
        <div class="card" style="padding: 14px 18px; border-left: 4px solid #3b82f6;">
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">مجموع مشتریان ثبت شده</div>
            <div style="font-size: 20px; font-weight: 800; color: #0f172a;"><?= to_persian_num($totalCustCount) ?> <span style="font-size: 12px; font-weight: normal; color: #94a3b8;">نفر</span></div>
        </div>
        <div class="card" style="padding: 14px 18px; border-left: 4px solid #10b981;">
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">مشتریان خریدار عمده</div>
            <div style="font-size: 20px; font-weight: 800; color: #10b981;"><?= to_persian_num($wholesaleCount) ?> <span style="font-size: 12px; font-weight: normal; color: #94a3b8;">شخص/شرکت</span></div>
        </div>
        <div class="card" style="padding: 14px 18px; border-left: 4px solid #8b5cf6;">
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">مشتریان پرچون عادی</div>
            <div style="font-size: 20px; font-weight: 800; color: #8b5cf6;"><?= to_persian_num($retailCount) ?> <span style="font-size: 12px; font-weight: normal; color: #94a3b8;">نفر</span></div>
        </div>
        <div class="card" style="padding: 14px 18px; border-left: 4px solid #f59e0b;">
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">مجموع طلبات معوقه از مشتریان</div>
            <div style="font-size: 20px; font-weight: 800; color: #d97706;"><?= format_currency($totalDebtsSum) ?></div>
        </div>
    </div>

    <!-- Container -->
    <div style="position: relative;">
        <!-- Search & Filter Card -->
        <div class="card" style="margin-bottom: 20px; padding: 14px 18px;">
            <form method="GET" action="" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                <div style="flex: 1; min-width: 220px; position: relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; right: 12px; top: 11px; color: #94a3b8; font-size: 13px;"></i>
                    <input type="text" name="search" value="<?= sanitize($search) ?>" class="form-control" placeholder="جستجوی نام، شماره تماس، نام شرکت..." style="padding-right: 34px; font-size: 12.5px;">
                </div>
                <div style="width: 160px;">
                    <select name="type" class="form-control" style="font-size: 12.5px;" onchange="this.form.submit();">
                        <option value="">همه انواع مشتری</option>
                        <option value="عمده" <?= $filterType === 'عمده' ? 'selected' : '' ?>>خریداران عمده</option>
                        <option value="پرچون" <?= $filterType === 'پرچون' ? 'selected' : '' ?>>خریداران پرچون</option>
                        <option value="همکار" <?= $filterType === 'همکار' ? 'selected' : '' ?>>همکاران تجارتی</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary btn-sm" style="font-size: 12.5px;">
                    <i class="fa-solid fa-filter ml-1"></i> فیلتر
                </button>
                <?php if (!empty($search) || !empty($filterType)): ?>
                    <a href="<?= BASE_URL ?>modules/customers.php" class="btn btn-secondary btn-sm" style="color: #ef4444; font-size: 12px;">
                        <i class="fa-solid fa-xmark ml-1"></i> پاک کردن فیلتر
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Customers Table -->
        <div class="card">
            <div class="table-responsive">
                <table class="table" style="font-size: 13px;">
                    <thead>
                        <tr style="background: #f8fafc;">
                            <th style="width: 50px; text-align: center;">#</th>
                            <th>نام و تخلص مشتری</th>
                            <th>شماره تماس</th>
                            <th>نوعیت مشتری</th>
                            <th>شرکت / موقعیت</th>
                            <th style="text-align: left;">مجموع خریدها</th>
                            <th style="text-align: left;">قرضه باقی‌مانده</th>
                            <th style="text-align: center; width: 140px;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customersList)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px; color: #94a3b8;">
                                    <i class="fa-solid fa-users-slash" style="font-size: 32px; margin-bottom: 8px; display: block; opacity: 0.5;"></i>
                                    هیچ مشتری در سیستم یافت نشد.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $cIndex = 1; foreach ($customersList as $cust): ?>
                                <tr>
                                    <td style="text-align: center; color: #64748b;"><?= to_persian_num($cIndex++) ?></td>
                                    <td>
                                        <div style="font-weight: 700; color: #0f172a;"><?= sanitize($cust['name']) ?></div>
                                        <?php if (!empty($cust['notes'])): ?>
                                            <span style="font-size: 11px; color: #64748b;"><?= sanitize($cust['notes']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($cust['phone'])): ?>
                                            <a href="tel:<?= sanitize($cust['phone']) ?>" style="color: #2563eb; font-family: monospace; font-size: 12px; direction: ltr; display: inline-block;">
                                                <i class="fa-solid fa-phone mr-1" style="font-size: 10px;"></i> <?= sanitize($cust['phone']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 11px;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($cust['customer_type'] ?? '') === 'عمده'): ?>
                                            <span class="badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc; font-size: 11px;">عمده</span>
                                        <?php elseif (($cust['customer_type'] ?? '') === 'همکار'): ?>
                                            <span class="badge" style="background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; font-size: 11px;">همکار</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-size: 11px;">پرچون</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-size: 12px; color: #334155;"><?= sanitize($cust['company'] ?: $cust['address'] ?: '-') ?></div>
                                    </td>
                                    <td style="text-align: left; font-weight: 700; color: #0f172a;">
                                        <?= format_currency($cust['total_sales']) ?>
                                    </td>
                                    <td style="text-align: left;">
                                        <?php if ((float)$cust['total_debt'] > 0): ?>
                                            <span style="color: #ef4444; font-weight: 700;"><?= format_currency($cust['total_debt']) ?></span>
                                        <?php else: ?>
                                            <span style="color: #10b981; font-size: 11.5px; font-weight: 600;">تسویه کامل</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; justify-content: center; gap: 6px;">
                                            <a href="<?= BASE_URL ?>modules/debts.php?customer_name=<?= urlencode($cust['name']) ?>" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 11px;" title="حساب قرضه‌ها">
                                                <i class="fa-solid fa-receipt"></i>
                                            </a>
                                            <?php if (!empty($cust['phone'])): ?>
                                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $cust['phone']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 11px; color: #16a34a;" title="واتس‌اپ">
                                                    <i class="fa-brands fa-whatsapp"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($currentUser['role'] === 'admin'): ?>
                                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('آیا از حذف این مشتری اطمینان دارید؟');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_customer">
                                                    <input type="hidden" name="customer_id" value="<?= $cust['id'] ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 11px; color: #ef4444;" title="حذف">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
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

</div>

<!-- Modal: Add New Customer (Can open, triggers lock warning on submit if locked) -->
<div id="addCustomerModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #ffffff; border-radius: 12px; max-width: 500px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden;">
        <div style="padding: 16px 20px; background: #0f172a; color: #ffffff; display: flex; justify-content: space-between; align-items: center;">
            <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #ffffff;">
                <i class="fa-solid fa-user-plus ml-1" style="color: #38bdf8;"></i> ثبت مشتری جدید
            </h4>
            <button type="button" onclick="document.getElementById('addCustomerModal').style.display='none';" style="background: none; border: none; color: #94a3b8; font-size: 18px; cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="" style="padding: 20px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_customer">

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-size: 12.5px; font-weight: 700;">نام و تخلص مشتری *</label>
                <input type="text" name="name" class="form-control" placeholder="مثال: حاجی حبیب الله" required style="font-size: 13px;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div class="form-group">
                    <label class="form-label" style="font-size: 12.5px; font-weight: 700;">شماره تماس</label>
                    <input type="text" name="phone" class="form-control" placeholder="07xxxxxxxx" style="font-size: 13px; direction: ltr; text-align: right;">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 12.5px; font-weight: 700;">نوعیت مشتری</label>
                    <select name="customer_type" class="form-control" style="font-size: 13px;">
                        <option value="پرچون">پرچون (عادی)</option>
                        <option value="عمده">عمده (تجارتی)</option>
                        <option value="همکار">همکار صنف</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-size: 12.5px; font-weight: 700;">نام شرکت / فروشگاه / برند</label>
                <input type="text" name="company" class="form-control" placeholder="مثال: فروشگاه برادران افغان" style="font-size: 13px;">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-size: 12.5px; font-weight: 700;">آدرس یا موقعیت</label>
                <input type="text" name="address" class="form-control" placeholder="کابل، سرای شهزاده یا دکان..." style="font-size: 13px;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" style="font-size: 12.5px; font-weight: 700;">یادداشت تکمیلی</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="یادداشت در مورد اعتبار، شرایط تسویه یا سابقه مشتری..." style="font-size: 12.5px;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('addCustomerModal').style.display='none';">
                    انصراف
                </button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-check ml-1"></i> ثبت و ذخیره مشتری
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
