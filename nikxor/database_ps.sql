-- =====================================================================
-- د نیک‌زور د موبایل او برېښنايي توکو د پلورنځي د مدیریت او محاسبې سیسټم
-- د ډیټابیس بڼه: سل په سلو کې پښتو بڼه (له هر ډول پرديو تورو او ګډوډۍ پرته)
-- د ډیټابیس انجن: مای ایس کیو ایل / ماریا ډي بي
-- د کرکټرونو کوډ: یو ټي ایف-۸ ایم بي ۴ (utf8mb4_unicode_ci)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `nikxor_db_ps` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nikxor_db_ps`;

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- ۱. د کاروونکو جدول (د سیسټم حسابونه)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `fullname` VARCHAR(100) NOT NULL,
  `role` VARCHAR(30) DEFAULT 'کارکوونکی',
  `status` VARCHAR(20) DEFAULT 'فعال',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `username`, `password`, `fullname`, `role`, `status`) VALUES
(1, 'nikadmin', '$2y$10$w09aVeqxXN1JtE.1nI92iOlzW66W9Pq32c02mK3m1hHjF4hXG105e', 'احمد ضیا (عمومي مشر او امتیاز لرونکی)', 'عمومي مشر', 'فعال'),
(2, 'admin', '$2y$10$w2444z0A9dVnFHPlaDuVeeyMedoWGomkhcSmRXZj.JuLd2JtIX67O', 'د سیسټم او معلوماتي ټکنالوژۍ مدیر', 'د سیسټم مدیر', 'فعال'),
(3, 'cashier1', '$2y$10$w2444z0A9dVnFHPlaDuVeeyMedoWGomkhcSmRXZj.JuLd2JtIX67O', 'رحمت الله نیازی', 'خزانه‌دار', 'فعال');

-- -----------------------------------------------------
-- ۲. د توکو د ډلبندۍ جدول
-- -----------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `is_sample` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cat_name_ps` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `name`, `description`, `is_sample`) VALUES
(1, 'د سامسنګ ځیرک موبایلونه', 'د سامسنګ ګلکسي ټول اصلي او معیاري لوړپوړي، قاتېدونکي او منځنۍ کچې موبایلونه', 1),
(2, 'د اپل آیفون ځیرک موبایلونه', 'د اپل شرکت اصلي آیفون موبایلونه له اصلي بکس، ګمرکي اسنادو او اصلي تضمین سره', 1),
(3, 'اضافي لوازم او وسایل', 'ګړندي چارجران، با سیم او بې سیم غوږۍ، قوي کیبلونه او لوړ ظرفیت لرونکي پاوربانکونه', 1),
(4, 'پرزې او داخلي برخې', 'اصلي سکرینونه، غښتلي بیټرۍ، د کمرې ښیښې او فابریکي چوکاټونه', 1),
(5, 'پوښونه او د ښیښې ساتندوی ګلسونه', 'د ضربې ضد سیلیکوني پوښونه، مقناطیسي پوښونه او د محرمیت ساتونکي بشپړ ګلسونه', 1);

-- -----------------------------------------------------
-- ۳. د پلورنځي د شریکانو جدول
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
  `partner_type` VARCHAR(30) DEFAULT 'عمومي شریک',
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `partners` (`id`, `name`, `phone`, `share_percentage`, `investment_amount`, `join_date`, `status`, `partner_type`, `notes`) VALUES
(1, 'اشکان', '۰۷۹۱۵۱۰۰۴۶', 50.00, 500000.00, '2026-01-01', 'فعال', 'عمومي شریک', 'د هټۍ لومړنی او بنسټیز شریک او د کورنۍ پانګونې مدیر'),
(2, 'ګل رحمن بارکزی', '۰۷۰۰۱۱۲۲۳۳', 25.00, 250000.00, '2026-02-15', 'فعال', 'د مضاربت شریک', 'له دوبۍ څخه د وارداتي بارونو مالي برابرونکی');

-- -----------------------------------------------------
-- ۴. د ګودام او د توکو زېرمې جدول
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
  `supplier_name` VARCHAR(100) DEFAULT 'نړیوال سوداګریز شرکت',
  `warehouse_location` VARCHAR(100) DEFAULT 'لومړۍ المارۍ',
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
  INDEX `idx_stock_name_ps` (`name`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stock` (`id`, `name`, `category_id`, `specifications`, `quantity`, `purchase_price`, `expenses`, `country_of_origin`, `supplier_name`, `warehouse_location`, `capital_amount`, `partner_id`, `purchase_date`, `barcode`, `sale_price`, `min_quantity`, `is_sample`) VALUES
(1, 'سامسنګ ګلکسي ایس ۲۴ الټرا (نمونه)', 1, '۲۵۶ ګېګابایټه زېرمه / ۱۲ ګېګابایټه رېم - تور ټایټانیم - دوه فعال سیمکارټه', 4, 68000.00, 500.00, 'ویتنام', 'د دوبۍ نړیوال برېښنايي شرکت', 'د پلورنځي مرکزي نندارتون', 274000.00, 1, '2026-03-01', '۸۸۰۶۰۹۵۳۲۱۰۱', 72000.00, 3, 1),
(2, 'د اپل آیفون ۱۵ پرو مکس (نمونه)', 2, '۲۵۶ ګېګابایټه زېرمه - طبیعي ټایټانیم - دوه سیمکارټه له اصلي فابریکي بکس سره', 3, 74000.00, 600.00, 'د امریکا متحده ایالتونه', 'د دوبۍ د وارداتو شرکت', 'د پلورنځي مرکزي نندارتون', 223800.00, 1, '2026-03-01', '۱۹۵۹۴۹۰۱۲۳۴۵', 79000.00, 2, 1),
(3, 'سامسنګ ګلکسي اې ۵۵ پینځم نسل (نمونه)', 1, '۱۲۸ ګېګابایټه زېرمه / ۸ ګېګابایټه رېم - ځلېدونکی یخ اسماني رنګ', 6, 24500.00, 200.00, 'ویتنام', 'د کابل فون شرکت', 'دویمه المارۍ، لومړۍ تخته', 148200.00, 1, '2026-03-02', '۸۸۰۶۰۹۵۴۵۶۷۸', 26000.00, 3, 1),
(4, 'د اپل آیفون ۱۳ معیاري (نمونه)', 2, '۱۲۸ ګېګابایټه زېرمه - سپین ځلېدونکی - د بیټرۍ روغتیا سل په سلو کې', 5, 42000.00, 400.00, 'جاپان', 'د دوبۍ د وارداتو شرکت', 'دویم نندارتون', 212000.00, 1, '2026-03-02', '۱۹۴۲۵۲۶۹۸۷۱۲', 44500.00, 3, 1),
(5, 'سامسنګ ګلکسي اې ۱۵ (نمونه)', 1, '۱۲۸ ګېګابایټه زېرمه / ۶ ګېګابایټه رېم - تور او تیاره شین رنګ', 8, 11500.00, 150.00, 'هند', 'د کابل فون شرکت', 'دویمه المارۍ، دویمه تخته', 93200.00, 1, '2026-03-03', '۸۸۰۶۰۹۵۹۸۷۴۱', 12500.00, 4, 1),
(6, 'د اپل آیفون ۱۴ پرو (نمونه)', 2, '۲۵۶ ګېګابایټه زېرمه - تپ بنفش رنګ - اصلي فزیکي سیمکارټ لرونکی', 2, 61000.00, 500.00, 'د امریکا متحده ایالتونه', 'د دوبۍ د وارداتو شرکت', 'د پلورنځي مرکزي نندارتون', 123000.00, 1, '2026-03-03', '۱۹۴۲۵۳۱۲۳۴۵۶', 66000.00, 2, 1),
(7, 'د سامسنګ ۲۵ واټه چټک دیوالي چارجر (نمونه)', 3, 'درې پلګ لرونکی د ویتنام جوړ له اصلي ټایپ سي کیبل سره', 25, 650.00, 20.00, 'ویتنام', 'د چین د وارداتو شرکت', 'د لوازمو المارۍ', 16750.00, 1, '2026-03-04', '۸۸۰۶۰۹۱۲۳۸۹۰', 850.00, 10, 1),
(8, 'د اپل ۲۰ واټه اصلي بریښنايي اډاپټر (نمونه)', 3, 'اصلي فابریکي بسته بندي د بدلولو له رسمي تضمین سره', 20, 950.00, 30.00, 'چین', 'د چین د وارداتو شرکت', 'د لوازمو المارۍ', 19600.00, 1, '2026-03-04', '۱۹۴۲۵۲۱۵۶۷۸۹', 1200.00, 8, 1),
(9, 'د انکر اوبدل شوی ټایپ سي چټک کیبل (نمونه)', 3, 'یو متر اوږدوالی د شلېدو ضد د قوي الیافو پوښښ', 30, 320.00, 10.00, 'چین', 'د چین د وارداتو شرکت', 'د لوازمو المارۍ', 9900.00, 1, '2026-03-05', '۸۴۸۰۶۱۰۱۲۳۴۵', 450.00, 15, 1),
(10, 'د شیومي ۲۰ زره میلی امپیره پاوربانک (نمونه)', 3, '۲۰ زره میلی امپیره وړتیا ۲۲.۵ واټه چټک چارج دوه اړخیزه وتنې', 10, 1400.00, 50.00, 'چین', 'د چین د وارداتو شرکت', 'لومړۍ المارۍ، درېیمه تخته', 14500.00, 1, '2026-03-05', '۶۹۳۴۱۷۷۷۲۳۴۵', 1750.00, 5, 1),
(11, 'د سامسنګ ګلکسي بادز ۲ بې سیم غوږۍ (نمونه)', 3, 'د چاپېریال د اضافي غږونو د لرې کولو وړتیا - زیتوني رنګ', 4, 4500.00, 100.00, 'ویتنام', 'د دوبۍ نړیوال برېښنايي شرکت', 'د غږیزو وسایلو نندارتون', 18400.00, 1, '2026-03-06', '۸۸۰۶۰۹۲۳۴۵۶۷', 5200.00, 2, 1),
(12, 'د اپل د لایټنینګ اصلي مزي لرونکې غوږۍ (نمونه)', 3, 'اصلي شرکتي کڅوړه د لوړ کیفیت لرونکي غږ او بېس سره', 18, 480.00, 15.00, 'چین', 'د چین د وارداتو شرکت', 'د لوازمو المارۍ', 8910.00, 1, '2026-03-06', '۱۹۰۱۹۸۰۰۱۷۲۹', 650.00, 8, 1),
(13, 'د آیفون ۱۱ اصلي سکرین (نمونه)', 4, 'اصلي فابریکي سکرین له ډېر حساس ټچ او آزمویل شوې ښیښې سره', 3, 3800.00, 100.00, 'چین', 'د کابل د پرزو شرکت', 'د ترمیم او پرزو څانګه', 11700.00, 1, '2026-03-07', '۷۰۰۱۰۰۲۰۰۳۰۱', 4500.00, 2, 1),
(14, 'د سامسنګ اې ۳۲ سوپر امولډ اصلي سکرین (نمونه)', 4, 'سوپر امولډ د اصلي فریم او چوکاټ سره د ویتنام فابریکي تولید', 3, 4200.00, 100.00, 'ویتنام', 'د کابل د پرزو شرکت', 'د ترمیم او پرزو څانګه', 12900.00, 1, '2026-03-07', '۷۰۰۱۰۰۲۰۰۳۰۲', 4900.00, 2, 1),
(15, 'د آیفون ۱۲ پرو مکس قوي شوې بیټرۍ (نمونه)', 4, 'غښتلی ۳۶۸۷ میلی امپیره ظرفیت له اصلي تختې او سرېښ سره', 7, 1650.00, 50.00, 'چین', 'د کابل د پرزو شرکت', 'د ترمیم او پرزو څانګه', 11900.00, 1, '2026-03-08', '۷۰۰۱۰۰۲۰۰۳۰۳', 2100.00, 3, 1),
(16, 'د آیفون ۱۵ پرو د محرمیت ساتونکی ګلس (نمونه)', 5, 'نه ماتېدونکی مات ضد جاسوسي ګلس له بشپړ سرېښ سره', 40, 90.00, 5.00, 'چین', 'د چین د وارداتو شرکت', 'د ګلسونو لومړۍ دراز', 3800.00, 1, '2026-03-08', '۷۰۰۱۰۰۲۰۰۳۰۴', 180.00, 15, 1),
(17, 'د سامسنګ اې ۵۴ د سوپر ډي شفاف ګلس (نمونه)', 5, 'روښانه کریستالي د ګرولو ضد حرارت لیدلی کلک ګلس', 50, 60.00, 5.00, 'چین', 'د چین د وارداتو شرکت', 'د ګلسونو دویمه دراز', 3250.00, 1, '2026-03-09', '۷۰۰۱۰۰۲۰۰۳۰۵', 120.00, 20, 1),
(18, 'د آیفون ۱۳ سیلیکوني نرم اصلي پوښ (نمونه)', 5, 'دننه د کتان ټوکر لرونکی په ۵ ښکلو او بېلابېلو رنګونو کې', 22, 180.00, 10.00, 'چین', 'د چین د وارداتو شرکت', 'د پوښونو ځړونځی', 4180.00, 1, '2026-03-09', '۷۰۰۱۰۰۲۰۰۳۰۶', 320.00, 10, 1),
(19, 'د سامسنګ ایس ۲۳ الټرا مقناطیسي روښانه پوښ (نمونه)', 5, 'د ژېړېدو ضد قوي روښانه پوښ د شا له قوي مقناطیسي حلقې سره', 15, 240.00, 10.00, 'چین', 'د چین د وارداتو شرکت', 'د پوښونو ځړونځی', 3750.00, 1, '2026-03-10', '۷۰۰۱۰۰۲۰۰۳۰۷', 420.00, 8, 1),
(20, 'د سیمکارټ ایستلو وسپنیز ستنې سل عددي کڅوړه (نمونه)', 3, 'د زنګ ضد کلکې فولادي ستنې د سیمکارټ د خولې د اسانه خلاصولو لپاره', 10, 50.00, 2.00, 'چین', 'د چین د وارداتو شرکت', 'د مدیریت مېز دراز', 520.00, 1, '2026-03-10', '۷۰۰۱۰۰۲۰۰۳۰۸', 100.00, 5, 1);

-- -----------------------------------------------------
-- ۵. د پیرودونکو جدول
-- -----------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30),
  `customer_type` VARCHAR(50) DEFAULT 'پرچون پلور',
  `company` VARCHAR(100) DEFAULT '',
  `address` TEXT,
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `customers` (`id`, `name`, `phone`, `customer_type`, `company`, `address`, `notes`) VALUES
(1, 'محمد کریم نیازی', '۰۷۸۸۱۱۲۲۳۳', 'پرچون پلور', '', 'کابل، د افغانانو کوڅه، سوداګریز سړک', 'د لوړپوړو موبایلونو تلپاتې پیرودونکی'),
(2, 'حمیدالله وردګ', '۰۷۷۷۴۴۵۵۶۶', 'ټولیز / عمده', 'د وردګ موبایل سوداګریز شرکت', 'د هوايي ډګر څلورلارې، مرکزي سرای، ۴۲مه هټۍ', 'ولایتونو ته د موبایلونو لوی برابرونکی'),
(3, 'فرهاد الکوزی', '۰۷۹۹۸۸۷۷۶۶', 'شرکت / اداره', 'د کابل مخابراتي خدمتونو ټولنه', 'څلورمه کوڅه، د افغان سوداګریز کمپلکس', 'د لوازمو او پرزو کلنی او لوی پیرودونکی');

-- -----------------------------------------------------
-- ۶. د پلور او بلونو جدول
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
  `payment_type` VARCHAR(30) NOT NULL DEFAULT 'نغدې',
  `notes` TEXT,
  `created_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_inv_no_ps` (`invoice_no`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sales` (`id`, `invoice_no`, `customer_name`, `customer_phone`, `total_amount`, `paid_amount`, `remaining_amount`, `profit`, `payment_type`, `notes`, `created_by`, `created_at`) VALUES
(1, 'بل-۱۴۰۵-۰۰۰۱', 'محمد کریم نیازی', '۰۷۸۸۱۱۲۲۳۳', 72850.00, 72850.00, 0.00, 3680.00, 'نغدې', 'له تضمین پاڼې سره بشپړه نغدي تادیه', 1, '2026-03-12 11:30:00'),
(2, 'بل-۱۴۰۵-۰۰۰۲', 'حمیدالله وردګ', '۰۷۷۷۴۴۵۵۶۶', 52000.00, 30000.00, 22000.00, 2600.00, 'قسط واره', 'ټولیز فرمایش د مخکینۍ تادیې سره او پاتې برخه په حساب کې ثبت شوه', 1, '2026-03-13 15:45:00');

-- -----------------------------------------------------
-- ۷. په بلونو کې د پلورل شویو توکو جدول
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
(1, 1, 1, 'سامسنګ ګلکسي ایس ۲۴ الټرا (نمونه)', '۲۵۶ ګېګابایټه زېرمه / ۱۲ ګېګابایټه رېم - تور ټایټانیم - دوه فعال سیمکارټه', 1, 68000.00, 500.00, 72000.00, 72000.00, 3500.00),
(2, 1, 7, 'د سامسنګ ۲۵ واټه چټک دیوالي چارجر (نمونه)', 'درې پلګ لرونکی د ویتنام جوړ له اصلي ټایپ سي کیبل سره', 1, 650.00, 20.00, 850.00, 850.00, 180.00),
(3, 2, 3, 'سامسنګ ګلکسي اې ۵۵ پینځم نسل (نمونه)', '۱۲۸ ګېګابایټه زېرمه / ۸ ګېګابایټه رېم - ځلېدونکی یخ اسماني رنګ', 2, 24500.00, 200.00, 26000.00, 52000.00, 2600.00);

-- -----------------------------------------------------
-- ۸. د پورونو او د خلکو د حسابونو جدول
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
  `status` VARCHAR(30) DEFAULT 'خلاص',
  `debt_type` VARCHAR(30) DEFAULT 'د هټۍ طلب (د پیرودونکي پور)',
  `person_type` VARCHAR(30) DEFAULT 'پیرودونکی',
  `is_hasana` TINYINT(1) DEFAULT 0,
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `debts` (`id`, `sale_id`, `customer_name`, `customer_phone`, `total_amount`, `paid_amount`, `remaining_amount`, `due_date`, `status`, `debt_type`, `person_type`, `is_hasana`, `notes`) VALUES
(1, 2, 'حمیدالله وردګ', '۰۷۷۷۴۴۵۵۶۶', 22000.00, 5000.00, 17000.00, '2026-04-15', 'خلاص', 'د هټۍ طلب (د پیرودونکي پور)', 'ټولیز پلورونکی / شرکت', 0, 'د دویم بل پاتې شونې چې راتلونکې میاشت کې به تادیه شي');

-- -----------------------------------------------------
-- ۹. د پورونو د قسطونو او تادیاتو جدول
-- -----------------------------------------------------
DROP TABLE IF EXISTS `installments`;
CREATE TABLE `installments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `debt_id` INT NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `payment_date` DATE,
  `payment_reason` VARCHAR(150) DEFAULT 'میاشتنی قسط',
  `capital_amount` DECIMAL(15,2) DEFAULT 0.00,
  `profit_amount` DECIMAL(15,2) DEFAULT 0.00,
  `notes` TEXT,
  `created_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`debt_id`) REFERENCES `debts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `installments` (`id`, `debt_id`, `amount`, `payment_date`, `payment_reason`, `capital_amount`, `profit_amount`, `notes`, `created_by`) VALUES
(1, 1, 5000.00, '2026-03-20', 'د لومړۍ میاشتې قسط', 4500.00, 500.00, 'په هټۍ کې د خزانه‌دار لخوا نغده ترلاسه شوه', 1);

-- -----------------------------------------------------
-- ۱۰. د شریکانو د راکړې ورکړې جدول
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
(1, 1, 'د پانګې زیاتوالی', 500000.00, '2026-01-01', 'د هټۍ د پیل او د نندارتون جوړولو لپاره د عمومي شریک د پانګې وېش', 1),
(2, 1, 'د ګټې اخیستنه', 25000.00, '2026-02-28', 'د سلواغې د میاشتې د خالصې ګټې اخیستل');

-- -----------------------------------------------------
-- ۱۱. د پلورنځي د عمومي پانګې جدول
-- -----------------------------------------------------
DROP TABLE IF EXISTS `capital`;
CREATE TABLE `capital` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `transaction_type` VARCHAR(50) NOT NULL DEFAULT 'لومړنۍ پانګه',
  `source_description` VARCHAR(255),
  `transaction_date` DATE,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `capital` (`id`, `amount`, `transaction_type`, `source_description`, `transaction_date`) VALUES
(1, 1000000.00, 'لومړنۍ پانګه', 'په کابل کې د ضیا د موبایل پلورنځي د پیل لومړنۍ ثبت شوې پانګه', '2026-01-01');

-- -----------------------------------------------------
-- ۱۲. د پلورنځي د لګښتونو او تاوانونو جدول
-- -----------------------------------------------------
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `expense_type` VARCHAR(30) NOT NULL DEFAULT 'روان لګښتونه',
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
(1, 'روان لګښتونه', 'د هټۍ کرایه', 35000.00, '2026-03-01', 'د موبایل سنتر د مرکزي پلورنځي د کب د میاشتې کرایه', 1),
(2, 'روان لګښتونه', 'برېښنا او خدمتونه', 4200.00, '2026-03-05', 'د تجارتي برېښنا بیه، د ښاروالۍ صفايي او انټرنیټ لګښت', 1),
(3, 'روان لګښتونه', 'ډوډۍ او چای', 2800.00, '2026-03-10', 'د مراجعینو هرکلی، چای، قهوه او د کارکوونکو د غرمې خواړه', 1);

-- -----------------------------------------------------
-- ۱۳. د سیسټم د عمومي تنظیماتو جدول
-- -----------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` VARCHAR(100) PRIMARY KEY,
  `setting_value` TEXT,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('shop_name', 'د ضیا د موبایل او برېښنايي توکو پلورنځی'),
('shop_manager_name', 'احمد ضیا'),
('shop_phone_central', '۰۷۹۱۵۱۰۰۴۶ — اشکان'),
('shop_phone_secondary', '۰۷۴۷۲۳۰۱۴۳ — احمد ضیا'),
('shop_phone_whatsapp', '۰۰۸۶۱۹۵۸۴۸۶۷۱۷۳'),
('shop_address_central', 'کابل، د خښتي پله مخامخ، موبایل سنټر، څلورم پوړ، A323مه هټۍ'),
('shop_address_secondary', 'موبایل سنټر، درېیم پوړ، A248مه هټۍ'),
('currency', 'افغانۍ'),
('currency_title', 'د افغانستان افغانۍ'),
('currency_symbol', 'افغانۍ'),
('app_theme', 'gold'),
('invoice_footer_note', 'ستاسو له باور او پیرودلو څخه ډېره مننه. برېښنايي توکي له اصلي چاپ شوي بل او کارتن پرته نه بدلېږي.'),
('auto_print_invoice', '0'),
('low_stock_threshold', '3'),
('screen_lock_enabled', '1'),
('screen_lock_pin', '1234'),
('super_admin_pin', '2277512');

-- -----------------------------------------------------
-- ۱۴. د سیسټم د کړنو او راپورونو جدول
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
(1, 1, 'د سیسټم پیل', 'د پښتو ژبې معیاري ډیټابیس په بشپړ بریالیتوب سره ځای پر ځای شو', '127.0.0.1');

-- -----------------------------------------------------
-- ۱۵. د بهرني مضاربت د قراردادونو جدول
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
(1, 'حاجي نورمحمد کندهاری', '۰۷۸۹۰۰۱۱۲۲', 'له دوبۍ څخه د موبایلونو مستقیم څلورم وارداتي بار', 'د متحده عربي امارات', 350000.00, 60.00, 40.00, 420000.00, 70000.00, 42000.00, 350000.00, '2026-02-01', 'فعال', 'په وارداتي خارجي توکو کې ګډه مضاربت پانګونه د ګټې له عادلانه وېش سره');

-- -----------------------------------------------------
-- ۱۶. د ځانګړو آسانتیاوو او ماډلونو جدول
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
('low_stock_alert', 'د موجودۍ د کمښت خبرداری', 'د مخونو په سر کې د لږ پاتې شویو توکو اتومات خبرتیاوې او بنرونه', 1),
('view_low_stock', 'د ټولو کمو پاتې توکو لیدل', 'د خلاصېدو په حال کې د توکو تحلیلي جدول او فلټر شوی راپور', 1),
('preview_profit_specs', 'د ځانګړنو او ګټې ژوندۍ مخکتنه', 'د پلور پر مهال د تخمیني ګټې او تخنیکي ځانګړنو ژوندی حساب', 1),
('shelf_view', 'د الماریو او قفسو بڼه', 'د هټۍ او ګودام د الماریو، لیکو او قفسو بصري نقشه', 1),
('capital_turnover', 'د هټۍ د پانګې د څرخ مدیریت', 'د پانګې د بېرته راګرځېدو سرعت او د نغدو پیسو د بهیر ارزونه', 1),
('top_selling_report', 'په دې دوره کې ډېر پلورل شوي توکي', 'د ډېرو پلورل شویو ماډلونو او لوازمو جدول او احصایه', 1),
('cash_debt_period_report', 'په دې موده کې د نغدو پیسو او پورونو حالت', 'د پاتې پورونو په وړاندې د شته نغدو پیسو تحلیلي راپور', 1),
('warehouse_position', 'په ګودام او هټۍ کې دقیق ځای', 'د ګودام د قطار، دراز، المارۍ او نندارتون دقیق ځای معلومول', 1),
('expenses_module', 'د هټۍ د عمومي لګښتونو مدیریت', 'د هټۍ د عمومي لګښتونو ثبتول لکه کرایه، برېښنا او نور', 1),
('customers_module', 'د ځانګړو پیرودونکو او ډلبندۍ مدیریت', 'د ټولیز او پرچون پیرودونکو وېش، د امتیازاتو او فلټرونو سیسټم', 1),
('ai_advisor', 'د هټۍ د محاسبې هوښیار سلاکار', 'هوښیاره مشوره، د پلور اټکل او د هټۍ د ګټې او مالي پوښتنو ځوابونه', 1),
('backup_module', 'ځايي او ورېځین شاتړ (بک اپ)', 'د هټۍ له معلوماتو څخه د خوندي نسخې اخیستل او بېرته راګرځول', 1);

SET FOREIGN_KEY_CHECKS = 1;
