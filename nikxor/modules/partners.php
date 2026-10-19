<?php
/**
 * NiKXor - Mobile Shop Management System
 * Partners and Profit Sharing Module (شریک ها و تقسیم مفاد)
 * URL: http://localhost/nikxor/modules/partners.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();

$error = '';
$success = '';

// Handle Add Partner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_partner') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $name        = trim($_POST['name'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $share       = (float)normalize_digits($_POST['share_percentage'] ?? '0');
        $investment  = (float)normalize_digits($_POST['investment_amount'] ?? '0');
        $joinDate    = !empty($_POST['join_date']) ? $_POST['join_date'] : date('Y-m-d');
        $notes       = trim($_POST['notes'] ?? '');

        if (empty($name)) {
            $error = 'لطفاً نام شریک را وارد نمایید.';
        } elseif ($share < 0 || $share > 100) {
            $error = 'فیصدی سهم باید عددی بین ۰ تا ۱۰۰ باشد.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO partners (name, phone, share_percentage, investment_amount, join_date, status, notes) 
                                   VALUES (:name, :phone, :share, :inv, :jdate, 'active', :notes)");
            $stmt->execute([
                ':name'  => $name,
                ':phone' => $phone,
                ':share' => $share,
                ':inv'   => $investment,
                ':jdate' => $joinDate,
                ':notes' => $notes
            ]);

            log_activity($currentUser['id'], 'ثبت شریک جدید', "ثبت شریک جدید «{$name}» با سهم {$share}٪");
            set_flash("شریک جدید «{$name}» با موفقیت اضافه شد.", 'success');
            redirect(BASE_URL . 'modules/partners.php');
        }
    }
}

// Handle Edit Partner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_partner') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $partnerId   = (int)($_POST['partner_id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $share       = (float)normalize_digits($_POST['share_percentage'] ?? '0');
        $investment  = (float)normalize_digits($_POST['investment_amount'] ?? '0');
        $status      = trim($_POST['status'] ?? 'active');
        $notes       = trim($_POST['notes'] ?? '');

        if ($partnerId <= 0 || empty($name)) {
            $error = 'اطلاعات ارسالی معتبر نیست.';
        } elseif ($share < 0 || $share > 100) {
            $error = 'فیصدی سهم باید عددی بین ۰ تا ۱۰۰ باشد.';
        } else {
            $stmt = $pdo->prepare("UPDATE partners SET 
                name = :name, phone = :phone, share_percentage = :share, 
                investment_amount = :inv, status = :status, notes = :notes 
                WHERE id = :id");
            $stmt->execute([
                ':name'   => $name,
                ':phone'  => $phone,
                ':share'  => $share,
                ':inv'    => $investment,
                ':status' => $status,
                ':notes'  => $notes,
                ':id'     => $partnerId
            ]);

            log_activity($currentUser['id'], 'ویرایش سهم شریک', "ویرایش مشخصات و سهم شریک «{$name}» به {$share}٪");
            set_flash("مشخصات و سهم شریک «{$name}» با موفقیت بروزرسانی شد.", 'success');
            redirect(BASE_URL . 'modules/partners.php');
        }
    }
}

// Handle Delete Partner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_partner') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('خطای امنیتی نشست!', 'danger');
    } else {
        $delId = (int)($_POST['partner_id'] ?? 0);
        if ($delId > 0) {
            $pStmt = $pdo->prepare("SELECT name FROM partners WHERE id = :id");
            $pStmt->execute([':id' => $delId]);
            $pName = $pStmt->fetchColumn() ?: "شناسه {$delId}";
            $stmt = $pdo->prepare("DELETE FROM partners WHERE id = :id");
            $stmt->execute([':id' => $delId]);
            log_activity($currentUser['id'], 'حذف شریک', "حذف شریک «{$pName}» با شناسه {$delId}");
            set_flash("شریک «{$pName}» با موفقیت حذف گردید.", 'success');
        }
    }
    redirect(BASE_URL . 'modules/partners.php');
}


// Fetch partners
$partners = $pdo->query("SELECT * FROM partners ORDER BY id ASC")->fetchAll();

// Calculate total shop net profit to date
$metrics = get_dashboard_metrics();
$totalNetProfit = $metrics['overall_net_profit'];

// Total capital
$totalCapitalStmt = $pdo->query("SELECT SUM(amount) AS total FROM capital WHERE transaction_type = 'سرمایه'");
$totalCapital = (float)($totalCapitalStmt->fetch()['total'] ?? 0);

$pageTitle = 'مدیریت شریک ها و سهم سرمایه';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <h3 class="card-title" style="margin-bottom: 4px;">
                <i class="fa-solid fa-users-gear ml-1"></i> حساب شرکا و تسهیم مفاد
            </h3>
            <p style="font-size: 12.5px; color: #64748b;">
                مجموع مفاد خالص قابل تقسیم دکان: <strong style="color: #15803d; font-size: 15px;"><?= format_money($totalNetProfit) ?></strong> | 
                سرمایه عمومی: <strong><?= format_money($totalCapital) ?></strong>
            </p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="<?= BASE_URL ?>modules/mudaraba.php" class="btn btn-primary btn-sm" style="background: #10b981; border-color: #10b981;">
                <i class="fa-solid fa-handshake-angle ml-1"></i> حساب اختصاصی مضاربت (کالای خارجی)
            </a>
            <?php if (is_feature_locked('capital_turnover')): ?>
                <button type="button" onclick="showLockModal('capital_turnover', 'Capital Turnover & Cash Flow Management', 'مدیریت گردش سرمایه دکان');" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-vault ml-1"></i> مدیریت گردش سرمایه دکان
                    <span class="badge-lock-tag" style="padding: 1px 6px; font-size: 10px;"><i class="fa-solid fa-lock text-amber-500"></i> قفل</span>
                </button>
            <?php else: ?>
                <a href="<?= BASE_URL ?>modules/capital.php" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-vault ml-1"></i> مدیریت گردش سرمایه دکان
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    <!-- Add Partner Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-user-plus ml-1"></i> ثبت شریک جدید
            </h3>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= sanitize($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_partner">

            <div class="form-group">
                <label class="form-label" for="name">
                    نام شریک: <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" id="name" name="name" class="form-control" required placeholder="نام کامل شریک">
            </div>

            <div class="form-group">
                <label class="form-label" for="phone">شماره تماس:</label>
                <input type="text" id="phone" name="phone" class="form-control numeric-input" placeholder="07xxxxxxxx">
            </div>

            <div class="form-group">
                <label class="form-label" for="share_percentage">
                    فیصدی سهم از مفاد (٪): <span style="color: #ef4444;">*</span>
                </label>
                <input type="number" step="any" id="share_percentage" name="share_percentage" class="form-control numeric-input" min="0" max="100" required placeholder="مثلاً 50">
            </div>

            <div class="form-group">
                <label class="form-label" for="investment_amount">مبلغ سرمایه گذاری اولیه (افغانی):</label>
                <input type="number" step="any" id="investment_amount" name="investment_amount" class="form-control numeric-input" value="0" placeholder="مبلغ آورده نقدی">
            </div>

            <div class="form-group">
                <label class="form-label" for="join_date">تاریخ آغاز همکاری:</label>
                <input type="date" id="join_date" name="join_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="notes">توضیحات و شرایط شراکت:</label>
                <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="توضیحات درباره قرارداد شراکت"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                <i class="fa-solid fa-check ml-1"></i> ثبت مشخصات شریک
            </button>
        </form>
    </div>

    <!-- Partners Table & Profit Share Breakdown -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-chart-pie ml-1"></i> وضعیت سهم و مفاد شرکا
            </h3>
            <span class="badge badge-secondary"><?= to_persian_num(count($partners)) ?> شریک ثبت شده</span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>نام شریک</th>
                        <th>تماس</th>
                        <th>درصد سهم</th>
                        <th>آورده اولیه</th>
                        <th>سهم از مفاد فعلی</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($partners)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #64748b; padding: 20px;">
                                هیچ شریکی ثبت نشده است.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($partners as $part): 
                            $sharePct = (float)$part['share_percentage'];
                            $partnerProfitShare = ($totalNetProfit > 0) ? ($totalNetProfit * ($sharePct / 100)) : 0;
                        ?>
                            <tr>
                                <td>
                                    <strong><?= sanitize($part['name']) ?></strong>
                                    <?php if (!empty($part['notes'])): ?>
                                        <div style="font-size: 11px; color: #64748b;"><?= sanitize($part['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= sanitize($part['phone'] ?: '-') ?></td>
                                <td>
                                    <span class="badge badge-primary" style="font-size: 12px;"><?= to_persian_num($sharePct) ?>٪</span>
                                </td>
                                <td><?= format_money($part['investment_amount']) ?></td>
                                <td>
                                    <strong style="color: <?= $partnerProfitShare >= 0 ? '#15803d' : '#b91c1c' ?>;">
                                        <?= format_money($partnerProfitShare) ?>
                                    </strong>
                                </td>
                                <td style="white-space: nowrap;">
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="openEditPartnerModal(<?= htmlspecialchars(json_encode($part), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="fa-solid fa-pen-to-square"></i> ویرایش سهم
                                    </button>
                                    <form method="POST" action="" style="display: inline-block;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_partner">
                                        <input type="hidden" name="partner_id" value="<?= $part['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="آیا از حذف شریک «<?= sanitize($part['name']) ?>» اطمینان کامل دارید؟" title="حذف شریک">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Partner Modal -->
<div id="editPartnerModal" class="feature-modal-backdrop" onclick="if(event.target === this) closeEditPartnerModal();">
    <div class="feature-modal-box" style="max-width: 460px; text-align: right;">
        <button type="button" class="feature-modal-close" onclick="closeEditPartnerModal();">&times;</button>
        <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 14px; color: #0f172a;">
            <i class="fa-solid fa-pen-to-square ml-1" style="color: #0284c7;"></i> ویرایش مشخصات و درصد سهم شریک
        </h3>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_partner">
            <input type="hidden" id="editPartnerId" name="partner_id" value="0">

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label">نام شریک:</label>
                <input type="text" id="editPartnerName" name="name" class="form-control" required>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label">شماره تماس:</label>
                <input type="text" id="editPartnerPhone" name="phone" class="form-control numeric-input">
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label">درصد سهم از مفاد (٪):</label>
                <input type="number" step="any" id="editPartnerShare" name="share_percentage" class="form-control numeric-input" min="0" max="100" required>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label">مبلغ آورده / سرمایه‌گذاری (افغانی):</label>
                <input type="number" step="any" id="editPartnerInv" name="investment_amount" class="form-control numeric-input">
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label">یادداشت و توافقات:</label>
                <textarea id="editPartnerNotes" name="notes" class="form-control" rows="2"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 10px;">
                <i class="fa-solid fa-floppy-disk ml-1"></i> ذخیره تغییرات سهم
            </button>
        </form>
    </div>
</div>

<script>
function openEditPartnerModal(part) {
    document.getElementById('editPartnerId').value = part.id;
    document.getElementById('editPartnerName').value = part.name;
    document.getElementById('editPartnerPhone').value = part.phone || '';
    document.getElementById('editPartnerShare').value = part.share_percentage;
    document.getElementById('editPartnerInv').value = part.investment_amount || 0;
    document.getElementById('editPartnerNotes').value = part.notes || '';
    document.getElementById('editPartnerModal').style.display = 'flex';
}
function closeEditPartnerModal() {
    document.getElementById('editPartnerModal').style.display = 'none';
}
</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
