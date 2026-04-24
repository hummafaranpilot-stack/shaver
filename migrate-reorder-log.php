<?php
/**
 * Migration: Create reorder_mail_log table for tracking reorder email history
 */
require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE TABLE IF NOT EXISTS `reorder_mail_log` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `domain_id` INT UNSIGNED NOT NULL,
        `order_id` VARCHAR(50) NOT NULL,
        `recipients` TEXT NOT NULL,
        `coupon_code` VARCHAR(100) DEFAULT NULL,
        `coupon_type` VARCHAR(20) DEFAULT NULL,
        `coupon_amount` DECIMAL(10,2) DEFAULT NULL,
        `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_domain_order` (`domain_id`, `order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo "<h2 style='color:green;'>Migration successful!</h2>";
    echo "<p>Created table <code>reorder_mail_log</code></p>";
} catch (Exception $e) {
    echo "<h2 style='color:red;'>Migration failed</h2><p>" . $e->getMessage() . "</p>";
}
