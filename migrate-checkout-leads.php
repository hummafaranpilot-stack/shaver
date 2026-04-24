<?php
/**
 * Migration: Create checkout_leads table
 * Run once via browser: https://shaver.trustednutraproduct.com/migrate-checkout-leads.php
 * Delete this file after running.
 */

require_once __DIR__ . '/config.php';

try {
    $pdo = getDB();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `checkout_leads` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `checkout_uuid` VARCHAR(100) NOT NULL COMMENT 'Client-generated dedup key',
            `sessid2` VARCHAR(100) DEFAULT NULL COMMENT 'BuyGoods session ID from URL',
            `aff_id` VARCHAR(100) DEFAULT NULL,
            `sub_id` VARCHAR(100) DEFAULT NULL,
            `account_id` VARCHAR(20) DEFAULT NULL COMMENT 'BuyGoods account ID from URL',
            `product_codename` VARCHAR(100) DEFAULT NULL COMMENT 'Product code from URL',
            `email` VARCHAR(255) DEFAULT NULL,
            `phone` VARCHAR(50) DEFAULT NULL,
            `full_name` VARCHAR(255) DEFAULT NULL,
            `address` TEXT DEFAULT NULL,
            `country` VARCHAR(100) DEFAULT NULL,
            `state` VARCHAR(100) DEFAULT NULL,
            `city` VARCHAR(100) DEFAULT NULL,
            `zip` VARCHAR(20) DEFAULT NULL,
            `payment_method` VARCHAR(30) DEFAULT NULL COMMENT 'credit_card, paypal, apple_pay',
            `status` ENUM('started','form_filled','purchase_attempted','completed') DEFAULT 'started',
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` TEXT DEFAULT NULL,
            `checkout_url` TEXT DEFAULT NULL,
            `referrer` TEXT DEFAULT NULL,
            `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `last_updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `completed_at` DATETIME DEFAULT NULL,
            UNIQUE KEY `uq_checkout_uuid` (`checkout_uuid`),
            KEY `idx_status` (`status`),
            KEY `idx_email` (`email`),
            KEY `idx_sessid2` (`sessid2`),
            KEY `idx_account_product` (`account_id`, `product_codename`),
            KEY `idx_aff_id` (`aff_id`),
            KEY `idx_started_at` (`started_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    echo "<h2>Migration successful!</h2>";
    echo "<p><code>checkout_leads</code> table created.</p>";
    echo "<p><strong>Delete this file now.</strong></p>";

} catch (PDOException $e) {
    echo "<h2>Migration failed</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
