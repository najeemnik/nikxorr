<?php
/**
 * NiKXor - Mobile Shop Management System
 * Category Management Module
 * Full control for shopkeeper to add, edit, and delete categories
 * URL: http://localhost/nikxor/modules/categories.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();

$error = '';
$success = '';

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست، لطفاً مجدداً ارسال نمایید.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            $error = 'نام دسته بندی نمی تواند خالی باشد.';
        } else {
            // Check uniqueness
            $chk = $pdo->prepare("SELECT id FROM categories WHERE name = :name");
            $chk->execute([':name' => $name]);
            if ($chk->fetch()) {
                $error = 'دسته بندی با این نام قبلاً ایجاد شده است.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, description, is_sample) VALUES (:name, :desc, 0)");
                $stmt->execute([':name' => $name, ':desc' => $description]);
                log_activity($currentUser['id'], 'افزودن دسته', "ایجاد دسته بندی جدید «{$name}»");
                set_flash("دسته بندی «{$name}» با موفقیت افزوده شد.", 'success');
                redirect(BASE_URL . 'modules/categories.php');
            }
        }
    }
}

// Handle Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $catId = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name) || $catId <= 0) {
            $error = 'اطلاعات ارسالی نامعتبر است.';
        } else {
            // Check duplicate name on another category
            $chk = $pdo->prepare("SELECT id FROM categories WHERE name = :name AND id != :id");
            $chk->execute([':name' => $name, ':id' => $catId]);
            if ($chk->fetch()) {
                $error = 'دسته بندی دیگری با این نام وجود دارد.';
            } else {
                $stmt = $pdo->prepare("UPDATE categories SET name = :name, description = :desc WHERE id = :id");
                $stmt->execute([':name' => $name, ':desc' => $description, ':id' => $catId]);
                log_activity($currentUser['id'], 'ویرایش دسته', "ویرایش دسته بندی «{$name}» با شناسه {$catId}");
                set_flash("دسته بندی «{$name}» با موفقیت ویرایش گردید.", 'success');
                redirect(BASE_URL . 'modules/categories.php');
            }
        }
    }
}

// Handle Delete Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست!';
    } else {
        $catId = (int)($_POST['id'] ?? 0);
        
        // Count products currently in this category
        $pCountStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM stock WHERE category_id = :id");
        $pCountStmt->execute([':id' => $catId]);
        $productCount = (int)($pCountStmt->fetch()['total'] ?? 0);

        if ($productCount > 0) {
            set_flash("این دسته بندی دارای {$productCount} قلم جنس در گدام است. برای حذف آن، ابتدا اجناس مربوطه را به دسته دیگری انتقال دهید یا حذف نمایید.", 'warning');
        } else {
            $delStmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
            $delStmt->execute([':id' => $catId]);
            log_activity($currentUser['id'], 'حذف دسته', "حذف دسته بندی با شناسه {$catId}");
            set_flash('دسته بندی با موفقیت حذف گردید.', 'success');
        }
        redirect(BASE_URL . 'modules/categories.php');
    }
}

// Check if editing specific category
$editCat = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
    $stmt->execute([':id' => $editId]);
    $editCat = $stmt->fetch();
}

// Fetch all categories with product counts
$categories = $pdo->query("SELECT c.*, COUNT(s.id) AS product_count 
                           FROM categories c 
                           LEFT JOIN stock s ON c.id = s.category_id 
                           GROUP BY c.id 
                           ORDER BY c.name ASC")->fetchAll();

$pageTitle = 'مدیریت دسته بندی های دکان';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    <!-- Add or Edit Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?php if ($editCat): ?>
                    <i class="fa-solid fa-pen-to-square ml-1"></i> ویرایش دسته بندی
                <?php else: ?>
                    <i class="fa-solid fa-folder-plus ml-1"></i> ایجاد دسته بندی جدید
                <?php endif; ?>
            </h3>
            <?php if ($editCat): ?>
                <a href="<?= BASE_URL ?>modules/categories.php" class="btn btn-sm btn-secondary">انصراف</a>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= sanitize($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editCat ? 'edit' : 'add' ?>">
            <?php if ($editCat): ?>
                <input type="hidden" name="id" value="<?= $editCat['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label" for="name">
                    نام دسته بندی: <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" id="name" name="name" class="form-control" required 
                       value="<?= sanitize($editCat ? $editCat['name'] : '') ?>" 
                       placeholder="مثلاً: مبایل شیائومی، قاب و کاور...">
            </div>

            <div class="form-group">
                <label class="form-label" for="description">توضیحات (اختیاری):</label>
                <textarea id="description" name="description" class="form-control" rows="3" 
                          placeholder="توضیح کوتاه درباره این بخش"><?= sanitize($editCat ? $editCat['description'] : '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                <i class="fa-solid fa-floppy-disk ml-1"></i> <?= $editCat ? 'ذخیره تغییرات' : 'افزودن دسته بندی' ?>
            </button>
        </form>

        <div style="margin-top: 20px; font-size: 11.5px; color: #64748b; line-height: 1.5; background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
            <i class="fa-solid fa-circle-info ml-1" style="color: #0284c7;"></i>
            شما کنترل کامل روی دسته بندی ها دارید و می توانید آنها را مطابق سلیقه و نیاز دکان خود تغییر نام داده، اضافه کرده یا حذف نمایید.
        </div>
    </div>

    <!-- Categories List Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-tags ml-1"></i> لست دسته بندی های تعریف شده
            </h3>
            <span class="badge badge-secondary"><?= to_persian_num(count($categories)) ?> دسته</span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>نام دسته بندی</th>
                        <th>توضیحات</th>
                        <th style="width: 90px; text-align: center;">تعداد اجناس</th>
                        <th style="width: 90px; text-align: center;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #64748b; padding: 20px;">
                                هنوز هیچ دسته بندی ثبت نشده است.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $cNum = 1;
                        foreach ($categories as $cat): 
                        ?>
                            <tr>
                                <td><?= to_persian_num($cNum++) ?></td>
                                <td>
                                    <strong><?= sanitize($cat['name']) ?></strong>
                                    <?php if (!empty($cat['is_sample'])): ?>
                                        <span class="badge badge-secondary" style="font-size: 10px; margin-right: 4px;">نمونه</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 12px; color: #475569;"><?= sanitize($cat['description'] ?: '-') ?></td>
                                <td style="text-align: center;">
                                    <a href="<?= BASE_URL ?>modules/stock.php?category_id=<?= $cat['id'] ?>" class="badge badge-primary" style="text-decoration: none;">
                                        <?= to_persian_num($cat['product_count']) ?> جنس
                                    </a>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <a href="<?= BASE_URL ?>modules/categories.php?edit=<?= $cat['id'] ?>" class="btn btn-sm btn-secondary" title="ویرایش">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <form method="POST" action="" style="display: inline-block;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="آیا از حذف دسته بندی «<?= sanitize($cat['name']) ?>» اطمینان دارید؟" title="حذف">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
