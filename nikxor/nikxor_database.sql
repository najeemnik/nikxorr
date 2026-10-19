-- ===================================================
-- NiKXor - Mobile Shop Management System
-- Shop Name: فروشگاه ضیا
-- Target Database: MySQL (XAMPP / phpMyAdmin)
-- Database Name: nikxor_db
-- ===================================================

CREATE DATABASE IF NOT EXISTS `nikxor_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nikxor_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Users Table
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `fullname` VARCHAR(100) NOT NULL,
  `role` VARCHAR(20) DEFAULT 'staff',
  `status` VARCHAR(20) DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Admin Users (nikadmin: afghanwashere / admin: admin123)
INSERT INTO `users` (`id`, `username`, `password`, `fullname`, `role`, `status`, `created_at`) VALUES
(1, 'nikadmin', '$2y$10$w09aVeqxXN1JtE.1nI92iOlzW66W9Pq32c02mK3m1hHjF4hXG105e', 'احمد ضیا (مدیر کل)', 'admin', 'active', NOW()),
(2, 'admin', '$2y$10$w2444z0A9dVnFHPlaDuVeeyMedoWGomkhcSmRXZj.JuLd2JtIX67O', 'مدیر سیستم (Admin)', 'admin', 'active', NOW());

-- 2. Categories Table (with index on name column)
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `is_sample` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_category_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `name`, `description`, `is_sample`) VALUES
(1, 'مبایل سامسونگ', 'انواع گوشی های سامسونگ گلکسی سری S و A', 1),
(2, 'مبایل آیفون', 'گوشی های اصلی اپل آیفون با ضمانت کارتن', 1),
(3, 'اکسسیری', 'چارجر، هندزفری، کیبل و پاوربانک', 1),
(4, 'قطعات و پرزه', 'ال سی دی، بتری، گلس کمره و بدنه', 1),
(5, 'کاور و گلس', 'انواع پوش، کاور سیلیکونی و محافظ صفحه', 1);

-- 3. Partners Table
DROP TABLE IF EXISTS `partners`;
CREATE TABLE `partners` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30),
  `share_percentage` DECIMAL(5,2) DEFAULT 0.00,
  `investment_amount` DECIMAL(15,2) DEFAULT 0.00,
  `join_date` DATE,
  `status` VARCHAR(20) DEFAULT 'active',
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `partners` (`id`, `name`, `phone`, `share_percentage`, `investment_amount`, `join_date`, `status`, `notes`) VALUES
(1, 'اشکان', '0791510046', 50.00, 500000.00, '2026-01-01', 'active', 'شریک اصلی دکان');

-- 4. Stock Table
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
  `supplier_name` VARCHAR(100) DEFAULT 'شرکت وارداتی',
  `warehouse_location` VARCHAR(100) DEFAULT 'الماری ۱',
  `capital_amount` DECIMAL(15,2) DEFAULT 0.00,
  `partner_id` INT NULL,
  `purchase_date` DATE,
  `barcode` VARCHAR(50) DEFAULT '',
  `sale_price` DECIMAL(15,2) DEFAULT 0.00,
  `mudaraba_id` INT NULL,
  `min_quantity` INT DEFAULT 3,
  `is_sample` TINYINT(1) DEFAULT 0,
  `damaged_qty` INT DEFAULT 0,
  `is_damaged` TINYINT(1) DEFAULT 0,
  `damage_notes` VARCHAR(255) DEFAULT '',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_stock_name` (`name`),
  INDEX `idx_stock_cat` (`category_id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample 20 products
INSERT INTO `stock` (`name`, `category_id`, `specifications`, `quantity`, `purchase_price`, `expenses`, `country_of_origin`, `supplier_name`, `warehouse_location`, `capital_amount`, `partner_id`, `purchase_date`, `is_sample`) VALUES
('سامسونگ گلکسی S24 Ultra (نمونه)', 1, '256GB / 12GB RAM - Titanium Black - Dual SIM', 4, 68000.00, 500.00, 'ویتنام', 'دبی الکترونیکس', 'ویترین مرکزی', 274000.00, 1, CURDATE(), 1),
('آیفون 15 پرو مکس (نمونه)', 2, '256GB - Natural Titanium - ZAA با کارتن اصلی', 3, 74000.00, 600.00, 'امریکا', 'واردات دبی', 'ویترین مرکزی', 223800.00, 1, CURDATE(), 1),
('سامسونگ گلکسی A55 5G (نمونه)', 1, '128GB / 8GB RAM - Awesome Iceblue', 6, 24500.00, 200.00, 'ویتنام', 'کابل فون', 'الماری ۲ طبقه ۱', 148200.00, 1, CURDATE(), 1),
('آیفون 13 نرمال (نمونه)', 2, '128GB - Starlight White - سلامت بتری 100%', 5, 42000.00, 400.00, 'جاپان', 'واردات دبی', 'ویترین ۲', 212000.00, 1, CURDATE(), 1),
('سامسونگ گلکسی A15 (نمونه)', 1, '128GB / 6GB RAM - Blue Black', 8, 11500.00, 150.00, 'هند', 'کابل فون', 'الماری ۲ طبقه ۲', 93200.00, 1, CURDATE(), 1),
('آیفون 14 پرو (نمونه)', 2, '256GB - Deep Purple - سیمکارت خور فیزیکی', 2, 61000.00, 500.00, 'امریکا', 'واردات دبی', 'ویترین مرکزی', 123000.00, 1, CURDATE(), 1),
('چارجر اورجینال سامسونگ 25W (نمونه)', 3, 'سه پین اورجینال ویتنام با کیبل Type-C', 25, 650.00, 20.00, 'ویتنام', 'چین امپورت', 'الماری اکسسیری', 16750.00, 1, CURDATE(), 1),
('چارجر 20W اپل آیفون اورجینال (نمونه)', 3, 'پک اصلی با گارانتی تعویض', 20, 950.00, 30.00, 'چین', 'چین امپورت', 'الماری اکسسیری', 19600.00, 1, CURDATE(), 1),
('کیبل فست شارژ تایپ سی انکر (نمونه)', 3, 'طول ۱ متر روکش بافته ضد قطعی Anker', 30, 320.00, 10.00, 'چین', 'چین امپورت', 'الماری اکسسیری', 9900.00, 1, CURDATE(), 1),
('پاوربانک ۲۰ هزار میلی آمپر شیائومی (نمونه)', 3, '20000mAh 22.5W Fast Charge دو خروجی', 10, 1400.00, 50.00, 'چین', 'چین امپورت', 'الماری ۱ طبقه ۳', 14500.00, 1, CURDATE(), 1),
('ایربادز بی‌سیم سامسونگ Galaxy Buds 2 (نمونه)', 3, 'نویز کنسلینگ فعال ANC - رنگ زیتونی', 4, 4500.00, 100.00, 'ویتنام', 'دبی الکترونیکس', 'ویترین صوتی', 18400.00, 1, CURDATE(), 1),
('هندزفری سیمی اورجینال آیفون Lightning (نمونه)', 3, 'پک شرکتی اصلی با کیفیت صدای بیس دار', 18, 480.00, 15.00, 'چین', 'چین امپورت', 'الماری اکسسیری', 8910.00, 1, CURDATE(), 1),
('ال سی دی اورجینال آیفون 11 (نمونه)', 4, 'روکاری اورجینال تست شده با تاچ سالم', 3, 3800.00, 100.00, 'چین', 'پرزه جات کابل', 'بخش پرزه جات', 11700.00, 1, CURDATE(), 1),
('ال سی دی سامسونگ A32 اورجینال سرویس پک (نمونه)', 4, 'Super AMOLED با فریم اصلی ویتنام', 3, 4200.00, 100.00, 'ویتنام', 'پرزه جات کابل', 'بخش پرزه جات', 12900.00, 1, CURDATE(), 1),
('بتری اصلی آیفون 12 پرو مکس (نمونه)', 4, 'تقویتی با آی‌سی اورجینال ۱۰۰ درصد', 7, 1650.00, 50.00, 'چین', 'پرزه جات کابل', 'بخش پرزه جات', 11900.00, 1, CURDATE(), 1),
('گلس پرایوسی آیفون 15 پرو (نمونه)', 5, 'ضد جاسوسی 9D تمام صفحه فول چسب', 40, 90.00, 5.00, 'چین', 'چین امپورت', 'کشو گلس ۱', 3800.00, 1, CURDATE(), 1),
('گلس سوپر دی سامسونگ A54 (نمونه)', 5, 'Super D شفاف ضد خط و خش', 50, 60.00, 5.00, 'چین', 'چین امپورت', 'کشو گلس ۲', 3250.00, 1, CURDATE(), 1),
('کاور سیلیکونی اصل آیفون 13 (نمونه)', 5, 'مخمل داخل اورجینال در ۵ رنگ متنوع', 22, 180.00, 10.00, 'چین', 'چین امپورت', 'استند کاور', 4180.00, 1, CURDATE(), 1),
('کاور مگ سیف شفاف سامسونگ S23 Ultra (نمونه)', 5, 'ضد زردی با حلقه مگنتی قوی', 15, 240.00, 10.00, 'چین', 'چین امپورت', 'استند کاور', 3750.00, 1, CURDATE(), 1),
('سوزن سیمکارت و تبدیل خشاب (نمونه)', 3, 'بسته ۱۰۰ عددی استیل مقاوم', 10, 50.00, 2.00, 'چین', 'چین امپورت', 'کشو میز', 520.00, 1, CURDATE(), 1);

-- 5. Customers Table
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

-- 6. Sales Table
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
  INDEX `idx_invoice_no` (`invoice_no`),
  INDEX `idx_sale_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Sale Items Table
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
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Debts Table
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
  `debt_type` VARCHAR(30) DEFAULT 'receivable',
  `person_type` VARCHAR(30) DEFAULT 'customer',
  `is_hasana` TINYINT(1) DEFAULT 0,
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Installments Table
DROP TABLE IF EXISTS `installments`;
CREATE TABLE `installments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `debt_id` INT NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `payment_date` DATE,
  `payment_reason` VARCHAR(150) DEFAULT '',
  `capital_amount` DECIMAL(15,2) DEFAULT 0.00,
  `profit_amount` DECIMAL(15,2) DEFAULT 0.00,
  `notes` TEXT,
  `created_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`debt_id`) REFERENCES `debts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Partnership Transactions Table
DROP TABLE IF EXISTS `partnership_transactions`;
CREATE TABLE `partnership_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `partner_id` INT NOT NULL,
  `transaction_type` VARCHAR(30) NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `transaction_date` DATE,
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Capital Table
DROP TABLE IF EXISTS `capital`;
CREATE TABLE `capital` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `transaction_type` VARCHAR(50) NOT NULL DEFAULT 'سرمایه',
  `source_description` VARCHAR(255),
  `transaction_date` DATE,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `capital` (`amount`, `transaction_type`, `source_description`, `transaction_date`) VALUES
(1000000.00, 'سرمایه', 'سرمایه اولیه فروشگاه ضیا', '2026-01-01');

-- 12. Expenses Table
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `expense_type` VARCHAR(20) NOT NULL DEFAULT 'مصارف',
  `stock_id` INT NULL DEFAULT NULL,
  `purchase_price` DECIMAL(15,2) DEFAULT 0.00,
  `sale_price` DECIMAL(15,2) DEFAULT 0.00,
  `loss_qty` INT DEFAULT 1,
  `category` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `expense_date` DATE,
  `description` TEXT,
  `created_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Settings Table
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` VARCHAR(100) PRIMARY KEY,
  `setting_value` TEXT,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('shop_name', 'فروشگاه ضیا'),
('shop_phone_central', '0791510046 — اشکان'),
('shop_phone_secondary', '0747230143 — احمد ضیا'),
('shop_phone_whatsapp', '008619584867173'),
('shop_address_central', 'کابل مقابل پل خشتی موبایل سنتر منزل چهارم دوکان A323'),
('shop_address_secondary', 'موبایل سنتر منزل سوم دوکان A248'),
('currency_title', 'افغانی'),
('currency', 'افغانی'),
('currency_symbol', 'AFN'),
('invoice_footer_note', 'با تشکر از خریداری شما. اجناس بدون تکت و کارتن مسترد نمی گردد.'),
('auto_print_invoice', '0'),
('low_stock_threshold', '3'),
('screen_lock_enabled', '1'),
('screen_lock_pin', '1234'),
('super_admin_pin', '2277512');

-- 14. Activity Logs Table
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT,
  `ip_address` VARCHAR(50),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Mudaraba Contracts Table
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
  `status` VARCHAR(30) DEFAULT 'active',
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Custom Features Table
DROP TABLE IF EXISTS `custom_features`;
CREATE TABLE `custom_features` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `feature_key` VARCHAR(100) NOT NULL UNIQUE,
  `title_fa` VARCHAR(150),
  `title_en` VARCHAR(150),
  `description_fa` TEXT,
  `description_en` TEXT,
  `formula_expression` TEXT,
  `is_locked` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `custom_features` (`feature_key`, `title_fa`, `title_en`, `description_fa`, `description_en`, `is_locked`) VALUES
('low_stock_alert', 'هشدار کمبود موجودی', 'Low Stock Alert Notification', 'نمایش بنر و هشدارهای خودکار کسری کالا در سربرگ صفحات', 'Automated top alerts and banners for items with critical low quantity', 1),
('view_low_stock', 'مشاهده تمام اجناس کمبود', 'View All Low Stock Items', 'دسترسی به جدول تحلیلی و گزارش فیلتر شده کالاهای رو به اتمام', 'Detailed list and smart analytics of products nearing zero quantity', 1),
('preview_profit_specs', 'پیش‌نمایش مشخصات و مفاد', 'Live Profit & Specs Preview', 'محاسبه زنده حاشیه مفاد تخمینی و جزئیات تخنیکی کالا در هنگام فروش', 'Real-time estimated margin, markup calculation, and technical specs badge on POS', 1),
('shelf_view', 'نمای قفسه‌ها / الماری', 'Shelf & Cabinet Visualizer', 'نمایش گرید بصری الماری‌ها، ردیف‌ها و قفسه‌های دکان و انبار', 'Visual rack, shelf, and cabinet locator grid layout for products', 1),
('capital_turnover', 'مدیریت گردش سرمایه دکان', 'Capital Flow & Turnover Management', 'ارزیابی سرعت بازگشت سرمایه، نقدینگی و تزریق وجوه نقدی جدید', 'Capital rotation cycles, cash injections, and cash velocity index', 1),
('top_selling_report', 'پرفروش‌ترین اجناس در این دوره', 'Top Selling Products Analytics', 'نمودار و جدول پرفروش‌ترین برندها، مدل‌ها و لوازم جانبی', 'Rankings and statistics of best performing models and accessories', 1),
('cash_debt_period_report', 'وضعیت نقدینگی و قرضه‌ها در این بازه', 'Liquidity & Outstanding Debts in Period', 'گزارش تحلیلی نقدینگی موجود در برابر مطالبات و بدهی‌های معوقه', 'Cash flow health audit comparing collected vs pending debts for selected timeframe', 1),
('warehouse_position', 'موقعیت در گدام / دکان', 'Warehouse & In-Shop Location Tracking', 'تفکیک دقیق ردیف انبار، کشو، الماری و بخش فروشگاهی', 'Detailed warehouse aisle, bay, drawer, and showroom placement tracking', 1),
('expenses_module', 'مدیریت مصارف عمومی دکان', 'General Shop Expenses Module', 'ثبت هزینه‌های عمومی دکان (کرایه، برق، مصارف متفرقه) - ثبت ضرر فعال می‌ماند', 'General overhead expenses tracking (losses logging remains active)', 1),
('customers_module', 'مدیریت مشتریان VIP و پیشرفته', 'Advanced Customers & VIP Club', 'دسته‌بندی عمده و پرچون، امتیاز مشتریان و فیلترهای تکمیلی', 'Wholesale vs retail categorization, VIP tiering, and detailed profile filters', 1),
('ai_advisor', 'هوش مصنوعی حسابدار و تحلیلگر دکان', 'Smart AI Shop Accountant & Advisor', 'مشاوره هوشمند، پیش‌بینی فروش و پاسخ فوری به سوالات حسابداری و سود دکان', 'AI powered financial advisor answering queries, forecast demands, and profit audit', 1),
('backup_module', 'پشتیبان‌گیری ابری و محلی (بک‌آپ)', 'Cloud & Local Database Backup Module', 'تهیه نسخه پشتیبان خودکار و دستی از دیتابیس دکان و بازگردانی اطلاعات', 'Automated & manual database backup export and restoration system', 1);

SET FOREIGN_KEY_CHECKS = 1;
