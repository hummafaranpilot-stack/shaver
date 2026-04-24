<?php
/**
 * Migration: Create orders table
 * Run once via browser: https://shaver.trustednutraproduct.com/migrate-orders.php
 * DELETE THIS FILE after running!
 */
require_once __DIR__ . '/config.php';
$pdo = getDB();

$sql = "CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `domain_id` INT NOT NULL,
  `order_id` VARCHAR(20) NOT NULL,
  `date_created` DATETIME DEFAULT NULL,
  `status` VARCHAR(30) DEFAULT NULL,
  `customer_name` VARCHAR(255) DEFAULT NULL,
  `customer_email` VARCHAR(255) DEFAULT NULL,
  `customer_phone` VARCHAR(50) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `state` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT NULL,
  `zip` VARCHAR(20) DEFAULT NULL,
  `affiliate_id` VARCHAR(50) DEFAULT NULL,
  `affiliate_name` VARCHAR(255) DEFAULT NULL,
  `affiliate_commission` DECIMAL(10,2) DEFAULT 0,
  `sub_id` VARCHAR(255) DEFAULT NULL,
  `sub_id2` VARCHAR(255) DEFAULT NULL,
  `sub_id3` VARCHAR(255) DEFAULT NULL,
  `sub_id4` VARCHAR(255) DEFAULT NULL,
  `sub_id5` VARCHAR(255) DEFAULT NULL,
  `product_names` TEXT DEFAULT NULL,
  `product_codenames` VARCHAR(255) DEFAULT NULL,
  `sku` VARCHAR(100) DEFAULT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `total_amount` DECIMAL(10,2) DEFAULT 0,
  `vendor_net` DECIMAL(10,2) DEFAULT 0,
  `taxes` DECIMAL(10,2) DEFAULT 0,
  `shipping_cost` DECIMAL(10,2) DEFAULT 0,
  `processing_fees` DECIMAL(10,2) DEFAULT 0,
  `production_cost` DECIMAL(10,2) DEFAULT 0,
  `handling_cost` DECIMAL(10,2) DEFAULT 0,
  `ip_address` VARCHAR(50) DEFAULT NULL,
  `referrer_url` TEXT DEFAULT NULL,
  `funnel_codename` VARCHAR(255) DEFAULT NULL,
  `flag_upsell` TINYINT(1) DEFAULT 0,
  `is_free` TINYINT(1) DEFAULT 0,
  `is_test` TINYINT(1) DEFAULT 0,
  `was_canceled` TINYINT(1) DEFAULT 0,
  `date_canceled` DATETIME DEFAULT NULL,
  `cancel_reason` TEXT DEFAULT NULL,
  `user_comments` TEXT DEFAULT NULL,
  `user_id` VARCHAR(50) DEFAULT NULL,
  `store_id` VARCHAR(20) DEFAULT NULL,
  `order_details` TEXT DEFAULT NULL,
  `external_order_id` VARCHAR(100) DEFAULT NULL,
  `external_order_id2` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_domain_order` (`domain_id`, `order_id`),
  INDEX `idx_domain_date` (`domain_id`, `date_created`),
  INDEX `idx_customer_email` (`customer_email`),
  INDEX `idx_affiliate` (`domain_id`, `affiliate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

echo "<pre>\n";
try {
    $pdo->exec($sql);
    echo "[OK] Created 'orders' table successfully.\n";
} catch (PDOException $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
echo "\nDone! DELETE this file now.\n</pre>";
