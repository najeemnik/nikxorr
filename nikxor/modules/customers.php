<?php
/**
 * NiKXor - Customers Management Module (مشتریان و حسابات)
 * 100% Language Isolation Supported: English, Dari, Pashto
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();
$currentLang = get_current_lang();
$currentDir  = get_current_dir();

$pageTitle = 'مدیریت مشتریان و حسابات VIP';
$isLocked = is_feature_locked('customers_module');

// Handle Add Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_customer') {
    if ($isLocked) {
        set_flash(__t('Customer registration is locked in demo mode. Contact +93783788278 for VIP license activation.', 'امکان ثبت مشتری در نسخه پایه قفل می‌باشد. جهت خرید لایسنس و فعال‌سازی ثبت مشتریان، لطفاً با احمد نجیم نیک (+93783788278) تماس بگیرید.'), 'warning');
        redirect(BASE_URL . 'modules/customers.php');
    }

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('danger', __t('Security session error! Please try again.', 'خطای امنیتی نشست! لطفاً مجدداً تلاش نمایید.'));
        redirect(BASE_URL . 'modules/customers.php');
    }

    $name         = trim($_POST['name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $customerType = trim($_POST['customer_type'] ?? 'پرچون');
    $company      = trim($_POST['company'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');

    if (empty($name)) {
        set_flash('danger', __t('Customer name is required.', 'نام مشتری الزامی است.'));
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
            set_flash('success', sprintf(__t('Customer «%s» registered successfully.', 'مشتری «%s» با موفقیت ثبت گردید.'), $name));
        } catch (Exception $e) {
            set_flash('danger', __t('Error registering customer: ', 'خطا در ثبت مشتری: ') . $e->getMessage());
        }
    }
    redirect(BASE_URL . 'modules/customers.php');
}

// Handle Delete Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocked && isset($_POST['action']) && $_POST['action'] === 'delete_customer') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('danger', __t('Security session error!', 'خطای امنیتی نشست!'));
        redirect(BASE_URL . 'modules/customers.php');
    }
    if ($currentUser['role'] !== 'admin') {
        set_flash('danger', __t('Only system administrators can delete customer records.', 'تنها مدیر سیستم دسترسی به حذف مشتری را دارد.'));
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
            set_flash('success', sprintf(__t('Customer «%s» deleted successfully.', 'مشتری «%s» با موفقیت حذف گردید.'), $cRow['name']));
        }
    }
    redirect(BASE_URL . 'modules/customers.php');
}

// Filters & Search
$search = trim($_GET['search'] ?? '');
$filterType = trim($_GET['type'] ?? '');

$sql = "SELECT c.*, 
        (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE customer_name = c.name) as total_sales,
        (SELECT COALESCE(SUM(remaining_amount), 0) FROM debts WHERE customer_name = c.name AND status != 'پرداخت شده' AND status != 'تسویه') as total_debt
        FROM customers c WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (c.name LIKE :s1 OR c.phone LIKE :s2 OR c.company LIKE :s3)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
}

if (!empty($filterType)) {
    $sql .= " AND c.customer_type = :ctype";
    $params[':ctype'] = $filterType;
}

$sql .= " ORDER BY c.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customersList = $stmt->fetchAll();

// Quick Summary Counts
$totalCustCount = count($customersList);
$wholesaleCount = 0;
$retailCount = 0;
$totalDebtsSum = 0;

foreach ($customersList as $c) {
    if (($c['customer_type'] ?? '') === 'عمده') $wholesaleCount++;
    if (($c['customer_type'] ?? '') === 'پرچون') $retailCount++;
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
                <?= __t('Customer Accounts & VIP Directory', 'باشگاه مشتریان و حسابات', 'د پیرودونکو ټولنه او حسابونه') ?>
                <?php if ($isLocked): ?>
                    <?= render_lock_badge('customers_module', __t('VIP & Wholesale Locked', 'نسخه VIP و عمده قفل است', 'VIP او عمده بڼه قفل ده')) ?>
                <?php endif; ?>
            </h3>
            <span style="font-size: 12px; color: #64748b;"><?= __t('Complete management of wholesale & retail buyers, receivables, and transactions', 'مدیریت کامل خریداران عمده و پرچون، استعلام مطالبات و سابقه معاملات', 'د ټولیز او پرچون پیرودونکو بشپړ مدیریت او حسابونه') ?></span>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('addCustomerModal').style.display='flex';">
                <i class="fa-solid fa-user-plus ml-1"></i> <?= __t('Register New Customer', 'ثبت مشتری جدید', 'نوی پیرودونکی ثبتول') ?>
            </button>
            <?php if ($isLocked): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="showLockModal('customers_module', 'Advanced Customers & VIP Club', '<?= __t('Advanced Customers & VIP Club', 'مدیریت مشتریان VIP و پیشرفته') ?>');">
                    <i class="fa-solid fa-lock ml-1 text-amber-500"></i> <?= __t('VIP License', 'لایسنس VIP', 'VIP لایسنس') ?>
                </button>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>modules/debts.php" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-hand-holding-dollar ml-1"></i> <?= __t('View Debts & Receivables', 'مشاهده قرضه‌ها', 'پورونه او حسابونه کتل') ?>
            </a>
        </div>
    </div>

    <?php if ($isLocked): ?>
        <div style="background: #fffbeb; border: 1px solid #fef3c7; border-right: 4px solid #f59e0b; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-lock" style="color: #d97706; font-size: 16px;"></i>
                <span style="font-size: 12.5px; font-weight: 700; color: #92400e;">
                    <?= __t('Customer module is in preview mode. License is required to register and store new customer profiles.', 'ماژول مشتریان در حالت پیش‌نمایش فعال است. جهت ثبت و ذخیره داده‌ها نیاز به تهیه لایسنس دارید.', 'د پیرودونکو برخه په ازمایښتي حالت کې ده.') ?>
                </span>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="showLockModal('customers_module', 'Advanced Customers & VIP Club', '<?= __t('Advanced Customers & VIP Club', 'مدیریت مشتریان VIP و پیشرفته') ?>');" style="font-size: 11.5px; color: #b45309; border-color: #fcd34d;">
                <i class="fa-solid fa-key ml-1"></i> <?= __t('Activate License', 'فعال‌سازی لایسنس', 'لایسنس فعال کړئ') ?>
            </button>
        </div>
    <?php endif; ?>

    <!-- Summary Metrics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
        <div class="card" style="padding: 14px 18px; border-left: 4px solid #3b82f6;">
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;"><?= __t('Total Registered Customers', 'مجموع مشتریان ثبت شده', 'ټول ثبت شوي پیرودونکي') ?></div>
            <div style="font-size: 20px; font-weight: 800; color: #0f172a;"><?= $currentLang === 'en' ? $totalCustCount : to_persian_num($totalCustCount) ?> <span style="font-size: 12px; font-weight: normal; color: #94a3b8;"><?= __t('contacts', 'نفر', 'تنه') ?></span></div>
        </div>
        <div class="card" style="padding: 14px 18px; border-left: 4px solid #10b981;">
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;"><?= __t('Wholesale Buyers', 'مشتریان خریدار عمده', 'ټولیز پلورونکي') ?></div>
            <div style="font-size: 20px; font-weight: 800; color: #10b981;"><?= $currentLang === 'en' ? $wholesaleCount : to_persian_num($wholesaleCount) ?> <span style="font-size: 12px; font-weight: normal; color: #94a3b8;"><?= __t('accounts', 'شخص/شرکت', 'حسابونه') ?></span></div>
        </div>
        <div class="card" style="padding: 14px 18px; border-left: 4px solid #8b5cf6;">
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;"><?= __t('Retail Customers', 'مشتریان پرچون عادی', 'عادي پرچون پیرودونکي') ?></div>
            <div style="font-size: 20px; font-weight: 800; color: #8b5cf6;"><?= $currentLang === 'en' ? $retailCount : to_persian_num($retailCount) ?> <span style="font-size: 12px; font-weight: normal; color: #94a3b8;"><?= __t('contacts', 'نفر', 'تنه') ?></span></div>
        </div>
        <div class="card" style="padding: 14px 18px; border-left: 4px solid #f59e0b;">
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;"><?= __t('Total Outstanding Receivables', 'مجموع طلبات معوقه از مشتریان', 'له پیرودونکو پاتې ټول طلبونه') ?></div>
            <div style="font-size: 20px; font-weight: 800; color: #d97706;"><?= format_currency($totalDebtsSum) ?></div>
        </div>
    </div>

    <!-- Container -->
    <div style="position: relative;">
        <!-- Search & Filter Card -->
        <div class="card" style="margin-bottom: 20px; padding: 14px 18px;">
            <form method="GET" action="" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                <div style="flex: 1; min-width: 220px; position: relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; <?= $currentDir === 'rtl' ? 'right: 12px;' : 'left: 12px;' ?> top: 11px; color: #94a3b8; font-size: 13px;"></i>
                    <input type="text" name="search" value="<?= sanitize($search) ?>" class="form-control" placeholder="<?= __t('Search name, phone number, company...', 'جستجوی نام، شماره تماس، نام شرکت...', 'د نوم، تلیفون یا شرکت پلټنه...') ?>" style="<?= $currentDir === 'rtl' ? 'padding-right: 34px;' : 'padding-left: 34px;' ?> font-size: 12.5px;">
                </div>
                <div style="width: 170px;">
                    <select name="type" class="form-control" style="font-size: 12.5px;" onchange="this.form.submit();">
                        <option value=""><?= __t('All Customer Types', 'همه انواع مشتری', 'د پیرودونکو ټول ډولونه') ?></option>
                        <option value="عمده" <?= $filterType === 'عمده' ? 'selected' : '' ?>><?= __t('Wholesale Buyers', 'خریداران عمده', 'ټولیز پیرودونکي') ?></option>
                        <option value="پرچون" <?= $filterType === 'پرچون' ? 'selected' : '' ?>><?= __t('Retail Customers', 'خریداران پرچون', 'پرچون پیرودونکي') ?></option>
                        <option value="همکار" <?= $filterType === 'همکار' ? 'selected' : '' ?>><?= __t('Business Partners', 'همکاران تجارتی', 'سوداګریز همکاران') ?></option>
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary btn-sm" style="font-size: 12.5px;">
                    <i class="fa-solid fa-filter ml-1"></i> <?= __t('Filter', 'فیلتر', 'فلټر') ?>
                </button>
                <?php if (!empty($search) || !empty($filterType)): ?>
                    <a href="<?= BASE_URL ?>modules/customers.php" class="btn btn-secondary btn-sm" style="color: #ef4444; font-size: 12px;">
                        <i class="fa-solid fa-xmark ml-1"></i> <?= __t('Clear Filter', 'پاک کردن فیلتر', 'فلټر لرې کول') ?>
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
                            <th><?= __t('Customer Name', 'نام و تخلص مشتری', 'د پیرودونکي نوم') ?></th>
                            <th><?= __t('Phone Number', 'شماره تماس', 'د تلیفون شمېره') ?></th>
                            <th><?= __t('Classification', 'نوعیت مشتری', 'د پیرودونکي ډول') ?></th>
                            <th><?= __t('Company / Location', 'شرکت / موقعیت', 'شرکت / ځای') ?></th>
                            <th style="text-align: <?= $currentDir === 'rtl' ? 'left' : 'right' ?>;"><?= __t('Total Purchases', 'مجموع خریدها', 'ټول پلور') ?></th>
                            <th style="text-align: <?= $currentDir === 'rtl' ? 'left' : 'right' ?>;"><?= __t('Outstanding Balance', 'قرضه باقی‌مانده', 'پاتې پور') ?></th>
                            <th style="text-align: center; width: 140px;"><?= __t('Actions', 'عملیات', 'کړنې') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customersList)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px; color: #94a3b8;">
                                    <i class="fa-solid fa-users-slash" style="font-size: 32px; margin-bottom: 8px; display: block; opacity: 0.5;"></i>
                                    <?= __t('No customer records found in system.', 'هیچ مشتری در سیستم یافت نشد.', 'په سیسټم کې هېڅ پیرودونکی ونه موندل شو.') ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $cIndex = 1; foreach ($customersList as $cust): ?>
                                <tr>
                                    <td style="text-align: center; color: #64748b;"><?= $currentLang === 'en' ? $cIndex++ : to_persian_num($cIndex++) ?></td>
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
                                            <span class="badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc; font-size: 11px;"><?= __t('Wholesale', 'عمده', 'عمده') ?></span>
                                        <?php elseif (($cust['customer_type'] ?? '') === 'همکار'): ?>
                                            <span class="badge" style="background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; font-size: 11px;"><?= __t('Partner', 'همکار', 'همکار') ?></span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-size: 11px;"><?= __t('Retail', 'پرچون', 'پرچون') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-size: 12px; color: #334155;"><?= sanitize($cust['company'] ?: $cust['address'] ?: '-') ?></div>
                                    </td>
                                    <td style="text-align: <?= $currentDir === 'rtl' ? 'left' : 'right' ?>; font-weight: 700; color: #0f172a;">
                                        <?= format_currency($cust['total_sales']) ?>
                                    </td>
                                    <td style="text-align: <?= $currentDir === 'rtl' ? 'left' : 'right' ?>;">
                                        <?php if ((float)$cust['total_debt'] > 0): ?>
                                            <span style="color: #ef4444; font-weight: 700;"><?= format_currency($cust['total_debt']) ?></span>
                                        <?php else: ?>
                                            <span style="color: #10b981; font-size: 11.5px; font-weight: 600;"><?= __t('Fully Settled', 'تسویه کامل', 'بشپړه تصفیه') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; justify-content: center; gap: 6px;">
                                            <a href="<?= BASE_URL ?>modules/debts.php?customer_name=<?= urlencode($cust['name']) ?>" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 11px;" title="<?= __t('Debts Ledger', 'حساب قرضه‌ها', 'د پورونو کتاب') ?>">
                                                <i class="fa-solid fa-receipt"></i>
                                            </a>
                                            <?php if (!empty($cust['phone'])): ?>
                                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $cust['phone']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 11px; color: #16a34a;" title="WhatsApp">
                                                    <i class="fa-brands fa-whatsapp"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($currentUser['role'] === 'admin'): ?>
                                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('<?= __t('Are you sure you want to delete this customer record?', 'آیا از حذف این مشتری اطمینان دارید؟', 'ایا تاسو باوري یاست چې دا پیرودونکی ړنګ کړئ؟') ?>');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_customer">
                                                    <input type="hidden" name="customer_id" value="<?= $cust['id'] ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 11px; color: #ef4444;" title="<?= __t('Delete', 'حذف', 'ړنګول') ?>">
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

<!-- Modal: Add New Customer -->
<div id="addCustomerModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #ffffff; border-radius: 12px; max-width: 500px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
        <div style="padding: 16px 20px; background: #0f172a; color: #ffffff; display: flex; justify-content: space-between; align-items: center;">
            <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #ffffff;">
                <i class="fa-solid fa-user-plus ml-1" style="color: #38bdf8;"></i> <?= __t('Register New Customer', 'ثبت مشتری جدید', 'نوی پیرودونکی ثبتول') ?>
            </h4>
            <button type="button" onclick="document.getElementById('addCustomerModal').style.display='none';" style="background: none; border: none; color: #94a3b8; font-size: 18px; cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="" style="padding: 20px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_customer">

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-size: 12.5px; font-weight: 700;"><?= __t('Customer Full Name *', 'نام و تخلص مشتری *', 'د پیرودونکي بشپړ نوم *') ?></label>
                <input type="text" name="name" class="form-control" placeholder="<?= __t('e.g. Haji Habibullah', 'مثال: حاجی حبیب الله', 'لکه: حاجي حبیب الله') ?>" required style="font-size: 13px;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div class="form-group">
                    <label class="form-label" style="font-size: 12.5px; font-weight: 700;"><?= __t('Phone Number', 'شماره تماس', 'د تلیفون شمېره') ?></label>
                    <input type="text" name="phone" class="form-control" placeholder="07xxxxxxxx" style="font-size: 13px; direction: ltr; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 12.5px; font-weight: 700;"><?= __t('Customer Type', 'نوعیت مشتری', 'د پیرودونکي ډول') ?></label>
                    <select name="customer_type" class="form-control" style="font-size: 13px;">
                        <option value="پرچون"><?= __t('Retail (Individual)', 'پرچون (عادی)', 'پرچون (عادي)') ?></option>
                        <option value="عمده"><?= __t('Wholesale (Commercial)', 'عمده (تجارتی)', 'عمده (سوداګریز)') ?></option>
                        <option value="همکار"><?= __t('Trade Partner', 'همکار صنف', 'همکار') ?></option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-size: 12.5px; font-weight: 700;"><?= __t('Company / Store / Brand Name', 'نام شرکت / فروشگاه / برند', 'د شرکت / پلورنځي نوم') ?></label>
                <input type="text" name="company" class="form-control" placeholder="<?= __t('e.g. Afghan Brothers Electronics', 'مثال: فروشگاه برادران افغان', 'لکه: افغان برادران پلورنځی') ?>" style="font-size: 13px;">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-size: 12.5px; font-weight: 700;"><?= __t('Address / Market Location', 'آدرس یا موقعیت', 'پته یا ځای') ?></label>
                <input type="text" name="address" class="form-control" placeholder="<?= __t('Kabul, Shahzada Market or Shop #...', 'کابل، سرای شهزاده یا دکان...', 'کابل، شهزاده سرای یا هټۍ...') ?>" style="font-size: 13px;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" style="font-size: 12.5px; font-weight: 700;"><?= __t('Additional Notes', 'یادداشت تکمیلی', 'اضافي یادښت') ?></label>
                <textarea name="notes" class="form-control" rows="2" placeholder="<?= __t('Notes on credit limit, settlement terms, or history...', 'یادداشت در مورد اعتبار، شرایط تسویه یا سابقه مشتری...', 'د باور او حساب په اړه یادښت...') ?>" style="font-size: 12.5px;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('addCustomerModal').style.display='none';">
                    <?= __t('Cancel', 'انصراف', 'لغوه') ?>
                </button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-check ml-1"></i> <?= __t('Save Customer Profile', 'ثبت و ذخیره مشتری', 'پیرودونکی خوندي کړئ') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
