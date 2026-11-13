-- ==============================================================================
-- Campus Canteen Pre-Order & Token System
-- Database Schema & Initial Seed Data
-- ==============================================================================
-- Engine: MySQL 5.7+ / 8.0+ / MariaDB 10.3+
-- Encoding: UTF-8 Unicode (utf8mb4)
--
-- DEFAULT ADMIN CREDENTIALS:
-- Username: admin
-- Password: Admin@Campus2026!
-- (Alternative test password if reset needed: admin123)
-- Admin Login URL: /admin-login.php (Hidden from public student portal)
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `canteen_db`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `canteen_db`;

-- ------------------------------------------------------------------------------
-- 1. Admins & Staff Users Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. Login Rate Limiting Table (Prevent Brute-Force Attacks on /admin-login.php)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ip_attempt` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. Menu Categories Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `display_order` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. Menu Items Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `menu_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `price` DECIMAL(8, 2) NOT NULL,
  `prep_time_minutes` INT UNSIGNED NOT NULL DEFAULT 10,
  `stock_qty` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_available` TINYINT(1) NOT NULL DEFAULT 1,
  `image_url` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_category` (`category_id`),
  INDEX `idx_availability` (`is_available`),
  INDEX `idx_price` (`price`),
  CONSTRAINT `fk_menu_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. Orders Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `token_number` VARCHAR(20) NOT NULL UNIQUE,
  `student_name` VARCHAR(100) NOT NULL,
  `roll_number` VARCHAR(50) NOT NULL,
  `pickup_time` TIME NOT NULL,
  `pickup_date` DATE NOT NULL,
  `payment_method` ENUM('UPI', 'COUNTER') NOT NULL DEFAULT 'COUNTER',
  `payment_status` ENUM('PENDING_VERIFICATION', 'PAID', 'PAY_ON_PICKUP', 'CANCELLED') NOT NULL DEFAULT 'PAY_ON_PICKUP',
  `utr_number` VARCHAR(12) NULL,
  `total_amount` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `status` ENUM('PREPARING', 'READY', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'PREPARING',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_order_status` (`status`),
  INDEX `idx_pickup_date` (`pickup_date`),
  INDEX `idx_utr` (`utr_number`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. Order Items (Itemized Snapshot of Orders)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `menu_item_id` INT UNSIGNED NOT NULL,
  `item_name` VARCHAR(100) NOT NULL,
  `unit_price` DECIMAL(8, 2) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `subtotal` DECIMAL(8, 2) NOT NULL,
  `status` ENUM('ACTIVE', 'CANCELLED') NOT NULL DEFAULT 'ACTIVE',
  INDEX `idx_order_id` (`order_id`),
  INDEX `idx_menu_item_id` (`menu_item_id`),
  CONSTRAINT `fk_order_parent` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_menu_item` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 7. Store Settings Table (Store Online/Offline Status)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `store_settings` (
  `key_name` VARCHAR(50) PRIMARY KEY,
  `key_value` VARCHAR(255) NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- SEED DATA
-- ==============================================================================

INSERT INTO `store_settings` (`key_name`, `key_value`) VALUES
('is_store_online', '1')
ON DUPLICATE KEY UPDATE `key_value` = VALUES(`key_value`);

-- Admin User:
-- Password 'Admin@Campus2026!' hashed with standard bcrypt cost=10
INSERT INTO `admins` (`id`, `username`, `password_hash`, `full_name`, `role`)
VALUES (
  1,
  'admin',
  '$2y$10$tydjLMqcFg3ZBTWI1RmZfeYLlpynKYUqEPRjW/ZJiUQ5X2Oc2YjpS',
  'Campus Bistro Kitchen Manager',
  'admin'
)
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- Categories
INSERT INTO `categories` (`id`, `name`, `slug`, `display_order`) VALUES
(1, 'Snacks', 'snacks', 1),
(2, 'Beverages', 'beverages', 2),
(3, 'Meals', 'meals', 3)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 12 Authentic Indian College Canteen Seed Items with Realistic Food Photos
INSERT INTO `menu_items` (`id`, `category_id`, `name`, `description`, `price`, `prep_time_minutes`, `stock_qty`, `is_available`, `image_url`) VALUES
(1, 3, 'Masala Dosa', 'Crisp golden dosa filled with spiced potato masala, served with coconut chutney and piping hot sambar.', 60.00, 12, 25, 1, 'https://images.unsplash.com/photo-1668236543090-82eba5ee5976?w=600&auto=format&fit=crop&q=80'),
(2, 1, 'Samosa (2 Pcs)', 'Flaky golden triangular pastries stuffed with spicy potato & green pea filling, served with tamarind and mint chutney.', 30.00, 5, 40, 1, 'https://images.unsplash.com/photo-1601050690597-df0568f70950?w=600&auto=format&fit=crop&q=80'),
(3, 3, 'Pav Bhaji', 'Rich, buttery spiced mashed vegetable curry served with two toasted soft pav buns and sliced red onions.', 70.00, 10, 20, 1, 'https://images.unsplash.com/photo-1626132647523-66f5bf380027?w=600&auto=format&fit=crop&q=80'),
(4, 3, 'Veg Biryani with Raita', 'Aromatic basmati rice cooked with garden-fresh veggies and fragrant whole spices, paired with cooling cucumber raita.', 110.00, 15, 15, 1, 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=600&auto=format&fit=crop&q=80'),
(5, 1, 'Vada Pav', 'Mumbai street-style spiced potato batata vada nestled in pav with fiery garlic dry chutney and fried green chili.', 25.00, 5, 35, 1, 'assets/images/vada-pav.jpg'),
(6, 3, 'Paneer Butter Masala Combo', 'Creamy cottage cheese cubes in rich tomato-cashew gravy served with 2 freshly made whole-wheat tawa parathas.', 130.00, 15, 12, 1, 'https://images.unsplash.com/photo-1631452180519-c014fe946bc7?w=600&auto=format&fit=crop&q=80'),
(7, 2, 'Masala Chai', 'Signature Indian milk tea brewed with crushed ginger, green cardamom, cloves, and premium Assam tea leaves.', 20.00, 5, 50, 1, 'assets/images/masala-chai.jpg'),
(8, 2, 'Cold Coffee with Ice Cream', 'Thick blended espresso cold coffee topped with a creamy scoop of rich vanilla ice cream and chocolate drizzle.', 60.00, 5, 30, 1, 'https://images.unsplash.com/photo-1517701550927-30cf4ba1dba5?w=600&auto=format&fit=crop&q=80'),
(9, 3, 'Chole Bhature', 'Spicy Punjabi chickpea curry paired with two fluffy, deep-fried golden bhature, pickled carrots, and green chili.', 85.00, 12, 18, 1, 'assets/images/chole-bhature.jpg'),
(10, 1, 'Maggi Double Masala', 'Classic campus favorite noodles cooked with onions, capsicum, sweet corn, and an extra sachet of magic masala.', 45.00, 8, 25, 1, 'assets/images/maggi.jpg'),
(11, 2, 'Fresh Lime Soda', 'Invigorating chilled soda with freshly squeezed lemon juice, black salt, and roasted cumin (sweet or salted).', 35.00, 5, 40, 1, 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=600&auto=format&fit=crop&q=80'),
(12, 2, 'Sweet Mango Lassi', 'Velvety chilled yogurt smoothie infused with sweet Alphonso mango pulp and garnished with crushed pistachios.', 50.00, 5, 20, 1, 'https://images.unsplash.com/photo-1546173159-315724a31696?w=600&auto=format&fit=crop&q=80')
ON DUPLICATE KEY UPDATE 
  `name` = VALUES(`name`),
  `price` = VALUES(`price`),
  `stock_qty` = VALUES(`stock_qty`),
  `image_url` = VALUES(`image_url`);

