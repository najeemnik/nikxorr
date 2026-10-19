-- =====================================================================
-- سامانه مدیریت و حسابداری فروشگاه موبایل و لوازم الکترونیکی نیک‌زور
-- نسخه دیتابیس: نسخه ۱۰۰٪ فارسی / دری (بدون کمترین واژه یا عبارت انگلیسی)
-- موتور دیتابیس: مای‌اس‌کیو‌ال / ماریا دی‌بی
-- کدگذاری حروف: یو‌تی‌اف-۸ ام‌بی۴ (utf8mb4_unicode_ci)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `nikxor_db_da` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nikxor_db_da`;

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- ۱. جدول کاربران (حساب‌های کاربری سیستم)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `fullname` VARCHAR(100) NOT NULL,
  `role` VARCHAR(30) DEFAULT 'کارمند',
  `status` VARCHAR(20) DEFAULT 'فعال',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `username`, `password`, `fullname`, `role`, `status`) VALUES
(1, 'nikadmin', '$2y$10$w09aVeqxXN1JtE.1nI92iOlzW66W9Pq32c02mK3m1hHjF4hXG105e', 'احمد ضیا (مدیر کل و صاحب امتیاز)', 'مدیر کل', 'فعال'),
(2, 'admin', '$2y$10$w2444z0A9dVnFHPlaDuVeeyMedoWGomkhcSmRXZj.JuLd2JtIX67O', 'مدیر سیستم و فناوری', 'مدیر سیستم', 'فعال'),
(3, 'cashier1', '$2y$10$w2444z0A9dVnFHPlaDuVeeyMedoWGomkhcSmRXZj.JuLd2JtIX67O', 'فرید احمد صدیقی', 'صندوقدار', 'فعال');

-- -----------------------------------------------------
-- ۲. جدول دسته‌بندی اجناس
-- -----------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `is_sample` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cat_name_da` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `name`, `description`, `is_sample`) VALUES
(1, 'گوشی‌های هوشمند سامسونگ', 'انواع گوشی‌های اصلی سامسونگ گلکسی سری‌های پرچمدار، تاشو و میان‌رده', 1),
(2, 'گوشی‌های هوشمند آیفون', 'گوشی‌های اصلی شرکت اپل همراه با کارتن اصلی، اسناد گمرکی و ضمانت اصالت', 1),
(3, 'لوازم جانبی و اکسسوری', 'انواع چارجرهای سریع، هندزفری‌های سیمی و بلوتوثی، کیبل‌های مقاوم و پاوربانک', 1),
(4, 'قطعات و پرزه‌جات داخلی', 'صفحه نمایش اصلی، بتری تقویت‌شده، شیشه کمره، قاب و شاسی فابریک', 1),
(5, 'کاور و محافظ صفحه (گلس)', 'انواع پوش سیلیکونی ضد ضربه، کاور مگنتی و محافظ صفحه ضد جاسوسی تمام‌چسب', 1);

-- -----------------------------------------------------
-- ۳. جدول شرکاء دکان
-- -----------------------------------------------------
DROP TABLE IF EXISTS `partners`;
CREATE TABLE `partners` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30),
  `share_percentage` DECIMAL(5,2) DEFAULT 0.00,
  `investment_amount` DECIMAL(15,2) DEFAULT 0.00,
  `join_date` DATE,
  `status` VARCHAR(20) DEFAULT 'فعال',
  `partner_type` VARCHAR(30) DEFAULT 'شریک عمومی',
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `partners` (`id`, `name`, `phone`, `share_percentage`, `investment_amount`, `join_date`, `status`, `partner_type`, `notes`) VALUES
(1, 'اشکان', '۰۷۹۱۵۱۰۰۴۶', 50.00, 500000.00, '2026-01-01', 'فعال', 'شریک عمومی', 'شریک اصلی دکان و مدیر سرمایه‌گذاری داخلی'),
(2, 'محمود سروری', '۰۷۰۰۱۱۲۲۳۳', 25.00, 250000.00, '2026-02-15', 'فعال', 'شریک مضاربت', 'تامین‌کننده مالی محموله‌های وارداتی از امارات');

-- -----------------------------------------------------
-- ۴. جدول انبار و گدام اجناس
-- -----------------------------------------------------
DROP TABLE IF EXISTS `stock`;
CREATE TABLE `stock` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `category_id` INT NULL,
  `specifications` TEXT,
  `quantity` INT NOT NULL DEFAULT 0,
  `purchase_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `expenses` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `country_of_origin` VARCHAR(100) DEFAULT 'چین',
  `supplier_name` VARCHAR(100) DEFAULT 'شرکت وارداتی بازرگانی',
  `warehouse_location` VARCHAR(100) DEFAULT 'الماری ۱',
  `capital_amount` DECIMAL(15,2) DEFAULT 0.00,
  `partner_id` INT NULL,
  `purchase_date` DATE,
  `barcode` VARCHAR(50) DEFAULT '',
  `sale_price` DECIMAL(15,2) DEFAULT 0.00,
  `mudaraba_id` INT NULL,
  `min_quantity` INT DEFAULT 3,
  `is_sample` TINYINT(1) DEFAULT 0,
  `damaged_qty` INT NOT NULL DEFAULT 0,
  `is_damaged` TINYINT(1) DEFAULT 0,
  `damage_notes` VARCHAR(255) DEFAULT '',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_stock_name_da` (`name`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stock` (`id`, `name`, `category_id`, `specifications`, `quantity`, `purchase_price`, `expenses`, `country_of_origin`, `supplier_name`, `warehouse_location`, `capital_amount`, `partner_id`, `purchase_date`, `barcode`, `sale_price`, `min_quantity`, `is_sample`) VALUES
(1, 'سامسونگ گلکسی اس۲۴ اولترا (نمونه)', 1, '۲۵۶ گیگابایت حافظه / ۱۲ گیگابایت رم - رنگ تیتانیوم مشکی - دو سیمکارت فعال', 4, 68000.00, 500.00, 'ویتنام', 'شرکت دبی الکترونیکس', 'ویترین مرکزی فروشگاه', 274000.00, 1, '2026-03-01', '۸۸۰۶۰۹۵۳۲۱۰۱', 72000.00, 3, 1),
(2, 'آیفون ۱۵ پرو مکس اپل (نمونه)', 2, '۲۵۶ گیگابایت حافظه - تیتانیوم طبیعی - دو سیمکارت فیزیکی با جعبه اصلی', 3, 74000.00, 600.00, 'ایالات متحده آمریکا', 'شرکت واردات دبی', 'ویترین مرکزی فروشگاه', 223800.00, 1, '2026-03-01', '۱۹۵۹۴۹۰۱۲۳۴۵', 79000.00, 2, 1),
(3, 'سامسونگ گلکسی آ۵۵ نسل پنجم (نمونه)', 1, '۱۲۸ گیگابایت حافظه / ۸ گیگابایت رم - رنگ آبی یخی درخشان', 6, 24500.00, 200.00, 'ویتنام', 'توزیع کابل فون', 'الماری ۲ طبقه ۱', 148200.00, 1, '2026-03-02', '۸۸۰۶۰۹۵۴۵۶۷۸', 26000.00, 3, 1),
(4, 'آیفون ۱۳ استاندارد اپل (نمونه)', 2, '۱۲۸ گیگابایت حافظه - سفید مهتابی - سلامت بتری ۱۰۰ درصد درجه یک', 5, 42000.00, 400.00, 'جاپان', 'شرکت واردات دبی', 'ویترین شماره ۲', 212000.00, 1, '2026-03-02', '۱۹۴۲۵۲۶۹۸۷۱۲', 44500.00, 3, 1),
(5, 'سامسونگ گلکسی آ۱۵ (نمونه)', 1, '۱۲۸ گیگابایت حافظه / ۶ گیگابایت رم - رنگ آبی تیره تیره', 8, 11500.00, 150.00, 'هند', 'توزیع کابل فون', 'الماری ۲ طبقه ۲', 93200.00, 1, '2026-03-03', '۸۸۰۶۰۹۵۹۸۷۴۱', 12500.00, 4, 1),
(6, 'آیفون ۱۴ پرو اپل (نمونه)', 2, '۲۵۶ گیگابایت حافظه - رنگ بنفش عمیق - سیمکارت خور فیزیکی بدون خط و خش', 2, 61000.00, 500.00, 'ایالات متحده آمریکا', 'شرکت واردات دبی', 'ویترین مرکزی فروشگاه', 123000.00, 1, '2026-03-03', '۱۹۴۲۵۳۱۲۳۴۵۶', 66000.00, 2, 1),
(7, 'چارجر دیواری ۲۵ وات سوپر فست سامسونگ (نمونه)', 3, 'سه پین اورجینال ویتنام با کیبل دوسر تایپ سی با ضمانت شارژ سریع', 25, 650.00, 20.00, 'ویتنام', 'شرکت وارداتی چین امپورت', 'الماری اکسسوری و لوازم جانبی', 16750.00, 1, '2026-03-04', '۸۸۰۶۰۹۱۲۳۸۹۰', 850.00, 10, 1),
(8, 'آداپتور چارجر ۲۰ وات اورجینال اپل (نمونه)', 3, 'بسته‌بندی رسمی شرکتی با ضمانت تعویض شش‌ماهه', 20, 950.00, 30.00, 'چین', 'شرکت وارداتی چین امپورت', 'الماری اکسسوری و لوازم جانبی', 19600.00, 1, '2026-03-04', '۱۹۴۲۵۲۱۵۶۷۸۹', 1200.00, 8, 1),
(9, 'کیبل فست شارژ بافته‌شده انکر (نمونه)', 3, 'طول ۱ متر با روکش الیاف بافته ضد پارگی و قطعی', 30, 320.00, 10.00, 'چین', 'شرکت وارداتی چین امپورت', 'الماری اکسسوری و لوازم جانبی', 9900.00, 1, '2026-03-05', '۸۴۸۰۶۱۰۱۲۳۴۵', 450.00, 15, 1),
(10, 'پاوربانک ۲۰ هزار میلی‌آمپر شیائومی (نمونه)', 3, 'ظرفیت ۲۰ هزار میلی‌آمپر ۲۲.۵ وات دو خروجی فست شارژ هوشمند', 10, 1400.00, 50.00, 'چین', 'شرکت وارداتی چین امپورت', 'الماری ۱ طبقه ۳', 14500.00, 1, '2026-03-05', '۶۹۳۴۱۷۷۷۲۳۴۵', 1750.00, 5, 1),
(11, 'هندزفری بلوتوثی گلکسی بادز ۲ سامسونگ (نمونه)', 3, 'قابلیت حذف نویز فعال محیطی - رنگ سبز زیتونی با محفظه شارژ', 4, 4500.00, 100.00, 'ویتنام', 'شرکت دبی الکترونیکس', 'ویترین تجهیزات صوتی', 18400.00, 1, '2026-03-06', '۸۸۰۶۰۹۲۳۴۵۶۷', 5200.00, 2, 1),
(12, 'هندزفری سیمی لایتنینگ اپل (نمونه)', 3, 'پک شرکتی اصلی با وضوح صدای استریو و بیس عمیق', 18, 480.00, 15.00, 'چین', 'شرکت وارداتی چین امپورت', 'الماری اکسسوری و لوازم جانبی', 8910.00, 1, '2026-03-06', '۱۹۰۱۹۸۰۰۱۷۲۹', 650.00, 8, 1),
(13, 'صفحه نمایش اصلی آیفون ۱۱ (نمونه)', 4, 'صفحه کامل روکاری شرکتی با تاچ بسیار حساس و تست‌شده', 3, 3800.00, 100.00, 'چین', 'شرکت پرزه‌جات کابل', 'بخش ترمیم و قطعات', 11700.00, 1, '2026-03-07', '۷۰۰۱۰۰۲۰۰۳۰۱', 4500.00, 2, 1),
(14, 'صفحه نمایش سامسونگ آ۳۲ سوپر امولد فابریک (نمونه)', 4, 'سوپر امولد همراه با شاسی و فریم فابریک مونتاژ ویتنام', 3, 4200.00, 100.00, 'ویتنام', 'شرکت پرزه‌جات کابل', 'بخش ترمیم و قطعات', 12900.00, 1, '2026-03-07', '۷۰۰۱۰۰۲۰۰۳۰۲', 4900.00, 2, 1),
(15, 'بتری تقویت‌شده آیفون ۱۲ پرو مکس (نمونه)', 4, 'سلول تقویت‌شده ۳۶۸۷ میلی‌آمپر با برد الکترونیکی اصلی و چسب فابریک', 7, 1650.00, 50.00, 'چین', 'شرکت پرزه‌جات کابل', 'بخش ترمیم و قطعات', 11900.00, 1, '2026-03-08', '۷۰۰۱۰۰۲۰۰۳۰۳', 2100.00, 3, 1),
(16, 'محافظ صفحه ضد جاسوسی ۹دی آیفون ۱۵ پرو (نمونه)', 5, 'گلس مات ضد انعکاس تمام‌صفحه فول چسب با حاشیه نشکن', 40, 90.00, 5.00, 'چین', 'شرکت وارداتی چین امپورت', 'کشو گلس ۱', 3800.00, 1, '2026-03-08', '۷۰۰۱۰۰۲۰۰۳۰۴', 180.00, 15, 1),
(17, 'محافظ صفحه شفاف سوپر دی سامسونگ آ۵۴ (نمونه)', 5, 'شیشه حرارت‌دیده کریستالی شفاف ضد خط و خش', 50, 60.00, 5.00, 'چین', 'شرکت وارداتی چین امپورت', 'کشو گلس ۲', 3250.00, 1, '2026-03-09', '۷۰۰۱۰۰۲۰۰۳۰۵', 120.00, 20, 1),
(18, 'کاور سیلیکونی اصل آیفون ۱۳ (نمونه)', 5, 'مخمل داخلی ضد خط افتادن در ۵ رنگ شیک و جذاب', 22, 180.00, 10.00, 'چین', 'شرکت وارداتی چین امپورت', 'استند نمایش کاور', 4180.00, 1, '2026-03-09', '۷۰۰۱۰۰۲۰۰۳۰۶', 320.00, 10, 1),
(19, 'کاور شفاف مگنتی ضد زردی سامسونگ اس۲۳ اولترا (نمونه)', 5, 'ضد زردشدگی همراه با حلقه آهنربایی مگنتی قوی', 15, 240.00, 10.00, 'چین', 'شرکت وارداتی چین امپورت', 'استند نمایش کاور', 3750.00, 1, '2026-03-10', '۷۰۰۱۰۰۲۰۰۳۰۷', 420.00, 8, 1),
(20, 'سوزن فلزی استیل خشاب سیمکارت بسته ۱۰۰ عددی (نمونه)', 3, 'سوزن‌های مقاوم ضد زنگ جهت تعویض آسان خشاب سیمکارت', 10, 50.00, 2.00, 'چین', 'شرکت وارداتی چین امپورت', 'کشو میز مدیریت', 520.00, 1, '2026-03-10', '۷۰۰۱۰۰۲۰۰۳۰۸', 100.00, 5, 1);

-- -----------------------------------------------------
-- ۵. جدول مشتریان دکان
-- -----------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30),
  `customer_type` VARCHAR(50) DEFAULT 'پرچون',
  `company` VARCHAR(100) DEFAULT '',
  `address` TEXT,
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `customers` (`id`, `name`, `phone`, `customer_type`, `company`, `address`, `notes`) VALUES
(1, 'محمد کریم رحمانی', '۰۷۸۸۱۱۲۲۳۳', 'پرچون', '', 'کابل، ده‌افغانان، کوچه تجارتی', 'خریدار دائمی گوشی‌های پرچمدار'),
(2, 'حمیدالله وردک', '۰۷۷۷۴۴۵۵۶۶', 'عمده', 'شرکت تجارتی موبایل وردک', 'میدان هوایی، سرای مرکزی، دکان شماره ۴۲', 'توزیع‌کننده عمده گوشی در ولایات'),
(3, 'فرهاد الکوزی', '۰۷۹۹۸۸۷۷۶۶', 'شرکت / موسسه', 'موسسه خدمات ارتباطی کابل', 'کوچه چهارم، مجتمع تجارتی افغان', 'خریدار عمده و سالانه لوازم جانبی و اکسسوری');

-- -----------------------------------------------------
-- ۶. جدول فروشات و بل‌ها
-- -----------------------------------------------------
DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(50) NOT NULL UNIQUE,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_phone` VARCHAR(30),
  `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `profit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `payment_type` VARCHAR(30) NOT NULL DEFAULT 'نقد',
  `notes` TEXT,
  `created_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_inv_no_da` (`invoice_no`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sales` (`id`, `invoice_no`, `customer_name`, `customer_phone`, `total_amount`, `paid_amount`, `remaining_amount`, `profit`, `payment_type`, `notes`, `created_by`, `created_at`) VALUES
(1, 'بل-۱۴۰۵-۰۰۰۱', 'محمد کریم رحمانی', '۰۷۸۸۱۱۲۲۳۳', 72850.00, 72850.00, 0.00, 3680.00, 'نقد', 'تسویه کامل نقدی همراه با تحویل کارت گارانتی فابریک', 1, '2026-03-12 11:30:00'),
(2, 'بل-۱۴۰۵-۰۰۰۲', 'حمیدالله وردک', '۰۷۷۷۴۴۵۵۶۶', 52000.00, 30000.00, 22000.00, 2600.00, 'قسط وار', 'سفارش عمده همراه با پیش‌پرداخت و ثبت متباقی در دفتر حسابات', 1, '2026-03-13 15:45:00');

-- -----------------------------------------------------
-- ۷. جدول اقلام فروخته‌شده در بل‌ها
-- -----------------------------------------------------
DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT NOT NULL,
  `stock_id` INT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `specifications` TEXT,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_purchase_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `unit_expense` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `unit_sale_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `profit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`stock_id`) REFERENCES `stock`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sale_items` (`id`, `sale_id`, `stock_id`, `product_name`, `specifications`, `quantity`, `unit_purchase_price`, `unit_expense`, `unit_sale_price`, `total_price`, `profit`) VALUES
(1, 1, 1, 'سامسونگ گلکسی اس۲۴ اولترا (نمونه)', '۲۵۶ گیگابایت حافظه / ۱۲ گیگابایت رم - رنگ تیتانیوم مشکی - دو سیمکارت فعال', 1, 68000.00, 500.00, 72000.00, 72000.00, 3500.00),
(2, 1, 7, 'چارجر دیواری ۲۵ وات سوپر فست سامسونگ (نمونه)', 'سه پین اورجینال ویتنام با کیبل دوسر تایپ سی', 1, 650.00, 20.00, 850.00, 850.00, 180.00),
(3, 2, 3, 'سامسونگ گلکسی آ۵۵ نسل پنجم (نمونه)', '۱۲۸ گیگابایت حافظه / ۸ گیگابایت رم - رنگ آبی یخی درخشان', 2, 24500.00, 200.00, 26000.00, 52000.00, 2600.00);

-- -----------------------------------------------------
-- ۸. جدول قرضه‌ها و حسابات اشخاص
-- -----------------------------------------------------
DROP TABLE IF EXISTS `debts`;
CREATE TABLE `debts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_phone` VARCHAR(30),
  `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `due_date` DATE,
  `status` VARCHAR(30) DEFAULT 'باز',
  `debt_type` VARCHAR(30) DEFAULT 'طلب دکان (قرضه مشتری)',
  `person_type` VARCHAR(30) DEFAULT 'مشتری',
  `is_hasana` TINYINT(1) DEFAULT 0,
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `debts` (`id`, `sale_id`, `customer_name`, `customer_phone`, `total_amount`, `paid_amount`, `remaining_amount`, `due_date`, `status`, `debt_type`, `person_type`, `is_hasana`, `notes`) VALUES
(1, 2, 'حمیدالله وردک', '۰۷۷۷۴۴۵۵۶۶', 22000.00, 5000.00, 17000.00, '2026-04-15', 'باز', 'طلب دکان (قرضه مشتری)', 'عمده فروش / شرکت', 0, 'باقیمانده بل شماره ۲ با موعد تسویه تا پایان ماه آینده');

-- -----------------------------------------------------
-- ۹. جدول اقساط و پرداختی‌های قرضه
-- -----------------------------------------------------
DROP TABLE IF EXISTS `installments`;
CREATE TABLE `installments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `debt_id` INT NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `payment_date` DATE,
  `payment_reason` VARCHAR(150) DEFAULT 'قسط ماهوار',
  `capital_amount` DECIMAL(15,2) DEFAULT 0.00,
  `profit_amount` DECIMAL(15,2) DEFAULT 0.00,
  `notes` TEXT,
  `created_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`debt_id`) REFERENCES `debts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `installments` (`id`, `debt_id`, `amount`, `payment_date`, `payment_reason`, `capital_amount`, `profit_amount`, `notes`, `created_by`) VALUES
(1, 1, 5000.00, '2026-03-20', 'قسط ماه اول', 4500.00, 500.00, 'وصول نقدی توسط صندوقدار در محل دکان', 1);

-- -----------------------------------------------------
-- ۱۰. جدول معاملات شرکاء
-- -----------------------------------------------------
DROP TABLE IF EXISTS `partnership_transactions`;
CREATE TABLE `partnership_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `partner_id` INT NOT NULL,
  `transaction_type` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `transaction_date` DATE,
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `partnership_transactions` (`id`, `partner_id`, `transaction_type`, `amount`, `transaction_date`, `notes`) VALUES
(1, 1, 'افزایش سرمایه', 500000.00, '2026-01-01', 'واریز سرمایه شریک اصلی جهت راه‌اندازی و دکوراسیون فروشگاه'),
(2, 1, 'برداشت مفاد', 25000.00, '2026-02-28', 'برداشت سهم مفاد خالص ماه دلو');

-- -----------------------------------------------------
-- ۱۱. جدول سرمایه عمومی دکان
-- -----------------------------------------------------
DROP TABLE IF EXISTS `capital`;
CREATE TABLE `capital` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `transaction_type` VARCHAR(50) NOT NULL DEFAULT 'سرمایه اولیه',
  `source_description` VARCHAR(255),
  `transaction_date` DATE,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `capital` (`id`, `amount`, `transaction_type`, `source_description`, `transaction_date`) VALUES
(1, 1000000.00, 'سرمایه اولیه', 'سرمایه اولیه تاسیس فروشگاه ضیا در کابل', '2026-01-01');

-- -----------------------------------------------------
-- ۱۲. جدول مصارف و هزینه‌های دکان
-- -----------------------------------------------------
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `expense_type` VARCHAR(30) NOT NULL DEFAULT 'مصارف جاری',
  `stock_id` INT NULL DEFAULT NULL,
  `purchase_price` DECIMAL(15,2) DEFAULT 0.00,
  `sale_price` DECIMAL(15,2) DEFAULT 0.00,
  `loss_qty` INT DEFAULT 1,
  `category` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `expense_date` DATE,
  `description` TEXT,
  `created_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`stock_id`) REFERENCES `stock`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `expenses` (`id`, `expense_type`, `category`, `amount`, `expense_date`, `description`, `created_by`) VALUES
(1, 'مصارف جاری', 'کرایه دکان', 35000.00, '2026-03-01', 'پرداخت کرایه ماه حوت فروشگاه مرکزی موبایل سنتر', 1),
(2, 'مصارف جاری', 'برق و خدمات', 4200.00, '2026-03-05', 'هزینه صرفیه برق تجارتی، صفایی شاروالی و اینترنت پرسرعت', 1),
(3, 'مصارف جاری', 'نان و چای', 2800.00, '2026-03-10', 'پذیرایی از مراجعین، چای، قهوه و غذای چاشت پرسونل', 1);

-- -----------------------------------------------------
-- ۱۳. جدول تنظیمات سراسری سیستم
-- -----------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` VARCHAR(100) PRIMARY KEY,
  `setting_value` TEXT,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('shop_name', 'فروشگاه موبایل و لوازم الکترونیکی ضیا'),
('shop_manager_name', 'احمد ضیا'),
('shop_phone_central', '۰۷۹۱۵۱۰۰۴۶ — اشکان'),
('shop_phone_secondary', '۰۷۴۷۲۳۰۱۴۳ — احمد ضیا'),
('shop_phone_whatsapp', '۰۰۸۶۱۹۵۸۴۸۶۷۱۷۳'),
('shop_address_central', 'کابل، مقابل پل خشتی، موبایل سنتر، منزل چهارم، دکان شماره A323'),
('shop_address_secondary', 'موبایل سنتر، منزل سوم، دکان شماره A248'),
('currency', 'افغانی'),
('currency_title', 'افغانی افغانستان'),
('currency_symbol', 'افغانی'),
('app_theme', 'gold'),
('invoice_footer_note', 'با سپاس از حسن انتخاب و اعتمادتان. اجناس الکترونیکی بدون تکت چاپی و کارتن اصلی مسترد نمی‌گردد.'),
('auto_print_invoice', '0'),
('low_stock_threshold', '3'),
('screen_lock_enabled', '1'),
('screen_lock_pin', '1234'),
('super_admin_pin', '2277512');

-- -----------------------------------------------------
-- ۱۴. جدول گزارش رویدادها و فعالیت‌ها
-- -----------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT,
  `ip_address` VARCHAR(50),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `details`, `ip_address`) VALUES
(1, 1, 'راه‌اندازی سیستم', 'دیتابیس نسخه فارسی استاندارد با موفقیت بارگذاری گردید', '127.0.0.1');

-- -----------------------------------------------------
-- ۱۵. جدول قراردادهای حساب مضاربت خارجی
-- -----------------------------------------------------
DROP TABLE IF EXISTS `mudaraba_contracts`;
CREATE TABLE `mudaraba_contracts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `partner_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(50) DEFAULT '',
  `project_title` VARCHAR(200) NOT NULL,
  `country_source` VARCHAR(100) DEFAULT '',
  `capital_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `partner_profit_rate` DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  `shop_profit_rate` DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  `total_sales_amount` DECIMAL(15,2) DEFAULT 0.00,
  `total_profit_amount` DECIMAL(15,2) DEFAULT 0.00,
  `paid_profit_to_partner` DECIMAL(15,2) DEFAULT 0.00,
  `returned_capital` DECIMAL(15,2) DEFAULT 0.00,
  `start_date` DATE,
  `settlement_date` DATE,
  `status` VARCHAR(30) DEFAULT 'فعال',
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `mudaraba_contracts` (`id`, `partner_name`, `phone`, `project_title`, `country_source`, `capital_amount`, `partner_profit_rate`, `shop_profit_rate`, `total_sales_amount`, `total_profit_amount`, `paid_profit_to_partner`, `returned_capital`, `start_date`, `status`, `notes`) VALUES
(1, 'حاجی نورمحمد قندهاری', '۰۷۸۹۰۰۱۱۲۲', 'واردات مستقیم گوشی از دبی بار چهارم', 'امارات متحده عربی', 350000.00, 60.00, 40.00, 420000.00, 70000.00, 42000.00, 350000.00, '2026-02-01', 'فعال', 'سرمایه‌گذاری مشترک مضاربت در کالای خارجی وارداتی با تقسیم عادلانه مفاد');

-- -----------------------------------------------------
-- ۱۶. جدول امکانات اختصاصی و ماژول‌ها
-- -----------------------------------------------------
DROP TABLE IF EXISTS `custom_features`;
CREATE TABLE `custom_features` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `feature_key` VARCHAR(100) NOT NULL UNIQUE,
  `title` VARCHAR(150),
  `description` TEXT,
  `formula_expression` TEXT,
  `is_locked` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `custom_features` (`feature_key`, `title`, `description`, `is_locked`) VALUES
('low_stock_alert', 'هشدار کمبود موجودی', 'نمایش بنر و هشدارهای خودکار کسری کالا در سربرگ صفحات', 1),
('view_low_stock', 'مشاهده تمام اجناس کمبود', 'دسترسی به جدول تحلیلی و گزارش فیلتر شده کالاهای رو به اتمام', 1),
('preview_profit_specs', 'پیش‌نمایش مشخصات و مفاد', 'محاسبه زنده حاشیه مفاد تخمینی و جزئیات تخنیکی کالا در هنگام فروش', 1),
('shelf_view', 'نمای قفسه‌ها و الماری', 'نمایش گرید بصری الماری‌ها، ردیف‌ها و قفسه‌های دکان و انبار', 1),
('capital_turnover', 'مدیریت گردش سرمایه دکان', 'ارزیابی سرعت بازگشت سرمایه، نقدینگی و تزریق وجوه نقدی جدید', 1),
('top_selling_report', 'پرفروش‌ترین اجناس در این دوره', 'نمودار و جدول پرفروش‌ترین برندها، مدل‌ها و لوازم جانبی', 1),
('cash_debt_period_report', 'وضعیت نقدینگی و قرضه‌ها در این بازه', 'گزارش تحلیلی نقدینگی موجود در برابر مطالبات و بدهی‌های معوقه', 1),
('warehouse_position', 'موقعیت دقیق در گدام و دکان', 'تفکیک دقیق ردیف انبار، کشو، الماری و بخش فروشگاهی', 1),
('expenses_module', 'مدیریت مصارف عمومی دکان', 'ثبت هزینه‌های عمومی دکان نظیر کرایه، برق، و مصارف متفرقه', 1),
('customers_module', 'مدیریت مشتریان ویژه و طبقه‌بندی', 'دسته‌بندی عمده و پرچون، امتیاز مشتریان و فیلترهای تکمیلی', 1),
('ai_advisor', 'هوش مصنوعی حسابدار و تحلیلگر دکان', 'مشاوره هوشمند، پیش‌بینی فروش و پاسخ فوری به سوالات مالی و سود دکان', 1),
('backup_module', 'پشتیبان‌گیری ابری و محلی', 'تهیه نسخه پشتیبان خودکار و دستی از دیتابیس دکان و بازگردانی اطلاعات', 1);

SET FOREIGN_KEY_CHECKS = 1;
