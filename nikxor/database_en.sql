-- =====================================================================
-- NiKXor Mobile & Electronics Management System
-- DATABASE: ENGLISH VERSION (100% Native English - Zero Foreign Words)
-- Database Engine: MySQL / MariaDB (InnoDB, UTF-8 MB4)
-- Character Set: utf8mb4 / utf8mb4_unicode_ci
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `nikxor_db_en` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nikxor_db_en`;

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- 1. Users Table
-- -----------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `fullname` VARCHAR(100) NOT NULL,
  `role` VARCHAR(30) DEFAULT 'Staff',
  `status` VARCHAR(20) DEFAULT 'Active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `username`, `password`, `fullname`, `role`, `status`) VALUES
(1, 'nikadmin', '$2y$10$w09aVeqxXN1JtE.1nI92iOlzW66W9Pq32c02mK3m1hHjF4hXG105e', 'Ahmad Zia (General Manager)', 'General Manager', 'Active'),
(2, 'admin', '$2y$10$w2444z0A9dVnFHPlaDuVeeyMedoWGomkhcSmRXZj.JuLd2JtIX67O', 'System Administrator', 'System Manager', 'Active'),
(3, 'cashier1', '$2y$10$w2444z0A9dVnFHPlaDuVeeyMedoWGomkhcSmRXZj.JuLd2JtIX67O', 'David Miller', 'Cashier', 'Active');

-- -----------------------------------------------------
-- 2. Categories Table
-- -----------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `is_sample` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cat_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `name`, `description`, `is_sample`) VALUES
(1, 'Samsung Smartphones', 'Original Samsung Galaxy flagship S-Series, Foldables, and A-Series mid-range devices', 1),
(2, 'Apple iPhone Smartphones', 'Genuine Apple iPhone smartphones with original retail packaging and factory warranty', 1),
(3, 'Accessories & Peripherals', 'Wall chargers, braided cables, wireless earbuds, wired headsets, and high-capacity power banks', 1),
(4, 'Spare Parts & Internal Components', 'Original replacement LCD displays, high-capacity internal batteries, camera modules, and housing frames', 1),
(5, 'Cases & Tempered Glass Protectors', 'Shockproof silicone protective cases, magnetic armor covers, and 9D privacy tempered screen protectors', 1);

-- -----------------------------------------------------
-- 3. Partners Table
-- -----------------------------------------------------
DROP TABLE IF EXISTS `partners`;
CREATE TABLE `partners` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30),
  `share_percentage` DECIMAL(5,2) DEFAULT 0.00,
  `investment_amount` DECIMAL(15,2) DEFAULT 0.00,
  `join_date` DATE,
  `status` VARCHAR(20) DEFAULT 'Active',
  `partner_type` VARCHAR(30) DEFAULT 'General Partner',
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `partners` (`id`, `name`, `phone`, `share_percentage`, `investment_amount`, `join_date`, `status`, `partner_type`, `notes`) VALUES
(1, 'Ashkan', '+93791510046', 50.00, 500000.00, '2026-01-01', 'Active', 'General Partner', 'Primary equity and business development partner'),
(2, 'Michael Vance', '+93700112233', 25.00, 250000.00, '2026-02-15', 'Active', 'Limited Partner', 'Financing partner for international import shipments');

-- -----------------------------------------------------
-- 4. Stock Table
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
  `country_of_origin` VARCHAR(100) DEFAULT 'China',
  `supplier_name` VARCHAR(100) DEFAULT 'Global Trading Corporation',
  `warehouse_location` VARCHAR(100) DEFAULT 'Cabinet 1',
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
  INDEX `idx_stock_name` (`name`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stock` (`id`, `name`, `category_id`, `specifications`, `quantity`, `purchase_price`, `expenses`, `country_of_origin`, `supplier_name`, `warehouse_location`, `capital_amount`, `partner_id`, `purchase_date`, `barcode`, `sale_price`, `min_quantity`, `is_sample`) VALUES
(1, 'Samsung Galaxy S24 Ultra (Sample)', 1, '256GB Storage / 12GB RAM - Titanium Black - Dual SIM Model', 4, 68000.00, 500.00, 'Vietnam', 'Dubai Global Electronics', 'Central Display Showcase', 274000.00, 1, '2026-03-01', '880609532101', 72000.00, 3, 1),
(2, 'Apple iPhone 15 Pro Max (Sample)', 2, '256GB Storage - Natural Titanium - Physical Dual SIM with Factory Box', 3, 74000.00, 600.00, 'United States', 'Dubai Import Logistics', 'Central Display Showcase', 223800.00, 1, '2026-03-01', '195949012345', 79000.00, 2, 1),
(3, 'Samsung Galaxy A55 5G (Sample)', 1, '128GB Storage / 8GB RAM - Awesome Ice Blue Edition', 6, 24500.00, 200.00, 'Vietnam', 'Kabul Phone Distribution', 'Cabinet 2 Shelf 1', 148200.00, 1, '2026-03-02', '880609545678', 26000.00, 3, 1),
(4, 'Apple iPhone 13 Standard (Sample)', 2, '128GB Storage - Starlight White - 100% Battery Health Grade A', 5, 42000.00, 400.00, 'Japan', 'Dubai Import Logistics', 'Showcase 2', 212000.00, 1, '2026-03-02', '194252698712', 44500.00, 3, 1),
(5, 'Samsung Galaxy A15 (Sample)', 1, '128GB Storage / 6GB RAM - Blue Black Edition', 8, 11500.00, 150.00, 'India', 'Kabul Phone Distribution', 'Cabinet 2 Shelf 2', 93200.00, 1, '2026-03-03', '880609598741', 12500.00, 4, 1),
(6, 'Apple iPhone 14 Pro (Sample)', 2, '256GB Storage - Deep Purple - Physical SIM Model with Clean IMEI', 2, 61000.00, 500.00, 'United States', 'Dubai Import Logistics', 'Central Display Showcase', 123000.00, 1, '2026-03-03', '194253123456', 66000.00, 2, 1),
(7, 'Samsung 25W Super Fast Wall Charger (Sample)', 3, 'Three-Pin UK Plug Vietnam Edition with USB Type-C to Type-C Cable', 25, 650.00, 20.00, 'Vietnam', 'China Import Trading Co', 'Accessories Cabinet', 16750.00, 1, '2026-03-04', '880609123890', 850.00, 10, 1),
(8, 'Apple 20W USB-C Power Adapter (Sample)', 3, 'Original Retail Packaging with 1-Year Replacement Warranty', 20, 950.00, 30.00, 'China', 'China Import Trading Co', 'Accessories Cabinet', 19600.00, 1, '2026-03-04', '194252156789', 1200.00, 8, 1),
(9, 'Anker Fast Charge USB-C Braided Cable (Sample)', 3, '1 Meter Length High-Durability Double-Braided Armor Cable', 30, 320.00, 10.00, 'China', 'China Import Trading Co', 'Accessories Cabinet', 9900.00, 1, '2026-03-05', '848061012345', 450.00, 15, 1),
(10, 'Xiaomi 20,000mAh Power Bank (Sample)', 3, '20,000mAh Capacity 22.5W Two-Way Fast Charge with Dual USB Outputs', 10, 1400.00, 50.00, 'China', 'China Import Trading Co', 'Cabinet 1 Shelf 3', 14500.00, 1, '2026-03-05', '693417772345', 1750.00, 5, 1),
(11, 'Samsung Galaxy Buds 2 Wireless Earbuds (Sample)', 3, 'Active Noise Cancellation ANC - Olive Green Edition with Wireless Case', 4, 4500.00, 100.00, 'Vietnam', 'Dubai Global Electronics', 'Audio Showcase', 18400.00, 1, '2026-03-06', '880609234567', 5200.00, 2, 1),
(12, 'Apple Lightning Wired EarPods (Sample)', 3, 'Original Factory Packaging with Deep Bass and Built-in Microphone', 18, 480.00, 15.00, 'China', 'China Import Trading Co', 'Accessories Cabinet', 8910.00, 1, '2026-03-06', '190198001729', 650.00, 8, 1),
(13, 'Apple iPhone 11 Replacement LCD Display (Sample)', 4, 'Original Refurbished Tested Screen Assembly with High Sensitivity Digitizer', 3, 3800.00, 100.00, 'China', 'Kabul Mobile Parts Co', 'Repair & Parts Section', 11700.00, 1, '2026-03-07', '700100200301', 4500.00, 2, 1),
(14, 'Samsung Galaxy A32 Original Service Pack AMOLED (Sample)', 4, 'Super AMOLED Assembly with Factory Pre-Installed Middle Frame', 3, 4200.00, 100.00, 'Vietnam', 'Kabul Mobile Parts Co', 'Repair & Parts Section', 12900.00, 1, '2026-03-07', '700100200302', 4900.00, 2, 1),
(15, 'Apple iPhone 12 Pro Max High-Capacity Battery (Sample)', 4, 'Reinforced 3687mAh Battery with Original TI IC Board and Adhesive Strips', 7, 1650.00, 50.00, 'China', 'Kabul Mobile Parts Co', 'Repair & Parts Section', 11900.00, 1, '2026-03-08', '700100200303', 2100.00, 3, 1),
(16, 'Apple iPhone 15 Pro 9D Privacy Tempered Glass (Sample)', 5, 'Anti-Spy Full Coverage 9D Edge-to-Edge Glue Tempered Screen Shield', 40, 90.00, 5.00, 'China', 'China Import Trading Co', 'Glass Drawer 1', 3800.00, 1, '2026-03-08', '700100200304', 180.00, 15, 1),
(17, 'Samsung Galaxy A54 Super D Tempered Glass (Sample)', 5, 'Crystal Clear HD Ultra-Thin Scratch-Resistant Tempered Protector', 50, 60.00, 5.00, 'China', 'China Import Trading Co', 'Glass Drawer 2', 3250.00, 1, '2026-03-09', '700100200305', 120.00, 20, 1),
(18, 'Apple iPhone 13 Silicone Shockproof Case (Sample)', 5, 'Original Microfiber Velvet Inner Lining in 5 Modern Colors', 22, 180.00, 10.00, 'China', 'China Import Trading Co', 'Cover Display Stand', 4180.00, 1, '2026-03-09', '700100200306', 320.00, 10, 1),
(19, 'Samsung Galaxy S23 Ultra MagSafe Clear Case (Sample)', 5, 'Anti-Yellowing Transparent Armor with Reinforced Strong Magnetic Ring', 15, 240.00, 10.00, 'China', 'China Import Trading Co', 'Cover Display Stand', 3750.00, 1, '2026-03-10', '700100200307', 420.00, 8, 1),
(20, 'Stainless Steel SIM Ejector Tools Pack of 100 (Sample)', 3, 'High-Grade Rustproof Stainless Steel SIM Tray Removal Pins', 10, 50.00, 2.00, 'China', 'China Import Trading Co', 'Front Desk Drawer', 520.00, 1, '2026-03-10', '700100200308', 100.00, 5, 1);

-- -----------------------------------------------------
-- 5. Customers Table
-- -----------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30),
  `customer_type` VARCHAR(50) DEFAULT 'Retail',
  `company` VARCHAR(100) DEFAULT '',
  `address` TEXT,
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `customers` (`id`, `name`, `phone`, `customer_type`, `company`, `address`, `notes`) VALUES
(1, 'Mohammad Karim', '+93788112233', 'Retail', '', 'Kabul City Center, Commercial Street', 'Regular retail smartphone buyer'),
(2, 'Hamidullah Wardak', '+93777445566', 'Wholesale', 'Wardak Mobile Trading LLC', 'Market Plaza, Store Number 42', 'Authorized regional wholesaler'),
(3, 'Farhad Alokozay', '+93799887766', 'Corporate / Company', 'Kabul Tech Solutions Ltd', 'Fourth Street, IT Park Complex', 'Corporate bulk purchaser of accessories and repairs');

-- -----------------------------------------------------
-- 6. Sales Table
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
  `payment_type` VARCHAR(30) NOT NULL DEFAULT 'Cash',
  `notes` TEXT,
  `created_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_invoice_num` (`invoice_no`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sales` (`id`, `invoice_no`, `customer_name`, `customer_phone`, `total_amount`, `paid_amount`, `remaining_amount`, `profit`, `payment_type`, `notes`, `created_by`, `created_at`) VALUES
(1, 'INV-2026-0001', 'Mohammad Karim', '+93788112233', 72850.00, 72850.00, 0.00, 3680.00, 'Cash', 'Full cash payment upon delivery with warranty receipt', 1, '2026-03-12 11:30:00'),
(2, 'INV-2026-0002', 'Hamidullah Wardak', '+93777445566', 52000.00, 30000.00, 22000.00, 2600.00, 'Installment', 'Wholesale order with down payment and ledger installment agreement', 1, '2026-03-13 15:45:00');

-- -----------------------------------------------------
-- 7. Sale Items Table
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
(1, 1, 1, 'Samsung Galaxy S24 Ultra (Sample)', '256GB Storage / 12GB RAM - Titanium Black - Dual SIM Model', 1, 68000.00, 500.00, 72000.00, 72000.00, 3500.00),
(2, 1, 7, 'Samsung 25W Super Fast Wall Charger (Sample)', 'Three-Pin UK Plug Vietnam Edition with USB Type-C Cable', 1, 650.00, 20.00, 850.00, 850.00, 180.00),
(3, 2, 3, 'Samsung Galaxy A55 5G (Sample)', '128GB Storage / 8GB RAM - Awesome Ice Blue Edition', 2, 24500.00, 200.00, 26000.00, 52000.00, 2600.00);

-- -----------------------------------------------------
-- 8. Debts Table
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
  `status` VARCHAR(30) DEFAULT 'Open',
  `debt_type` VARCHAR(30) DEFAULT 'Receivable (Customer Debt)',
  `person_type` VARCHAR(30) DEFAULT 'Customer',
  `is_hasana` TINYINT(1) DEFAULT 0,
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `debts` (`id`, `sale_id`, `customer_name`, `customer_phone`, `total_amount`, `paid_amount`, `remaining_amount`, `due_date`, `status`, `debt_type`, `person_type`, `is_hasana`, `notes`) VALUES
(1, 2, 'Hamidullah Wardak', '+93777445566', 22000.00, 5000.00, 17000.00, '2026-04-15', 'Open', 'Receivable (Customer Debt)', 'Wholesaler / Supplier', 0, 'Remaining balance for Invoice INV-2026-0002 payable in two installments');

-- -----------------------------------------------------
-- 9. Installments Table
-- -----------------------------------------------------
DROP TABLE IF EXISTS `installments`;
CREATE TABLE `installments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `debt_id` INT NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `payment_date` DATE,
  `payment_reason` VARCHAR(150) DEFAULT 'Monthly Installment',
  `capital_amount` DECIMAL(15,2) DEFAULT 0.00,
  `profit_amount` DECIMAL(15,2) DEFAULT 0.00,
  `notes` TEXT,
  `created_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`debt_id`) REFERENCES `debts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `installments` (`id`, `debt_id`, `amount`, `payment_date`, `payment_reason`, `capital_amount`, `profit_amount`, `notes`, `created_by`) VALUES
(1, 1, 5000.00, '2026-03-20', 'First Monthly Installment', 4500.00, 500.00, 'Cash received at shop counter', 1);

-- -----------------------------------------------------
-- 10. Partnership Transactions Table
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
(1, 1, 'Capital Contribution', 500000.00, '2026-01-01', 'Initial partnership capital injection for shop launching'),
(2, 1, 'Profit Withdrawal', 25000.00, '2026-02-28', 'Monthly equity profit distribution for February');

-- -----------------------------------------------------
-- 11. Capital Table
-- -----------------------------------------------------
DROP TABLE IF EXISTS `capital`;
CREATE TABLE `capital` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `transaction_type` VARCHAR(50) NOT NULL DEFAULT 'Initial Capital',
  `source_description` VARCHAR(255),
  `transaction_date` DATE,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `capital` (`id`, `amount`, `transaction_type`, `source_description`, `transaction_date`) VALUES
(1, 1000000.00, 'Initial Capital', 'Initial founding capital for Zia Mobile & Electronics Center', '2026-01-01');

-- -----------------------------------------------------
-- 12. Expenses Table
-- -----------------------------------------------------
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `expense_type` VARCHAR(30) NOT NULL DEFAULT 'Operating Expense',
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
(1, 'Operating Expense', 'Shop Rent', 35000.00, '2026-03-01', 'Monthly shop lease payment for March 2026', 1),
(2, 'Operating Expense', 'Electricity & Utilities', 4200.00, '2026-03-05', 'Commercial electricity bill and municipal cleaning fees', 1),
(3, 'Operating Expense', 'Food & Refreshments', 2800.00, '2026-03-10', 'Staff refreshments, tea, and daily guest hospitality', 1);

-- -----------------------------------------------------
-- 13. Settings Table
-- -----------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` VARCHAR(100) PRIMARY KEY,
  `setting_value` TEXT,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('shop_name', 'Zia Mobile & Electronics Center'),
('shop_manager_name', 'Ahmad Zia'),
('shop_phone_central', '+93791510046 - Ashkan'),
('shop_phone_secondary', '+93747230143 - Ahmad Zia'),
('shop_phone_whatsapp', '+8619584867173'),
('shop_address_central', 'Opposite Pol-e Khishti, Mobile Center, 4th Floor, Shop A323, Kabul, Afghanistan'),
('shop_address_secondary', 'Mobile Center, 3rd Floor, Shop A248, Kabul, Afghanistan'),
('currency', 'AFN'),
('currency_title', 'Afghan Afghani'),
('currency_symbol', 'AFN'),
('app_theme', 'gold'),
('invoice_footer_note', 'Thank you for your business. Merchandise cannot be returned without original printed receipt and packaging.'),
('auto_print_invoice', '0'),
('low_stock_threshold', '3'),
('screen_lock_enabled', '1'),
('screen_lock_pin', '1234'),
('super_admin_pin', '2277512');

-- -----------------------------------------------------
-- 14. Activity Logs Table
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
(1, 1, 'System Initialization', 'English database schema and standard parameters configured', '127.0.0.1');

-- -----------------------------------------------------
-- 15. Mudaraba Contracts Table
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
  `status` VARCHAR(30) DEFAULT 'Active',
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `mudaraba_contracts` (`id`, `partner_name`, `phone`, `project_title`, `country_source`, `capital_amount`, `partner_profit_rate`, `shop_profit_rate`, `total_sales_amount`, `total_profit_amount`, `paid_profit_to_partner`, `returned_capital`, `start_date`, `status`, `notes`) VALUES
(1, 'Haji Noor Mohammad', '+93789001122', 'Dubai Direct Shipment Batch 4', 'United Arab Emirates', 350000.00, 60.00, 40.00, 420000.00, 70000.00, 42000.00, 350000.00, '2026-02-01', 'Active', 'Joint partnership on premium wholesale mobile electronics import');

-- -----------------------------------------------------
-- 16. Custom Features Table
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
('low_stock_alert', 'Low Stock Alert Notification', 'Automated top alerts and banners for items with critical low quantity', 1),
('view_low_stock', 'View All Low Stock Items', 'Detailed list and smart analytics of products nearing zero quantity', 1),
('preview_profit_specs', 'Live Profit & Specs Preview', 'Real-time estimated margin, markup calculation, and technical specs badge on POS', 1),
('shelf_view', 'Shelf & Cabinet Visualizer', 'Visual rack, shelf, and cabinet locator grid layout for products', 1),
('capital_turnover', 'Capital Flow & Turnover Management', 'Capital rotation cycles, cash injections, and cash velocity index', 1),
('top_selling_report', 'Top Selling Products Analytics', 'Rankings and statistics of best performing models and accessories', 1),
('cash_debt_period_report', 'Liquidity & Outstanding Debts in Period', 'Cash flow health audit comparing collected vs pending debts for selected timeframe', 1),
('warehouse_position', 'Warehouse & In-Shop Location Tracking', 'Detailed warehouse aisle, bay, drawer, and showroom placement tracking', 1),
('expenses_module', 'General Shop Expenses Module', 'General overhead expenses tracking (losses logging remains active)', 1),
('customers_module', 'Advanced Customers & VIP Club', 'Wholesale vs retail categorization, VIP tiering, and detailed profile filters', 1),
('ai_advisor', 'Smart AI Shop Accountant & Advisor', 'AI powered financial advisor answering queries, forecast demands, and profit audit', 1),
('backup_module', 'Cloud & Local Database Backup Module', 'Automated & manual database backup export and restoration system', 1);

SET FOREIGN_KEY_CHECKS = 1;
