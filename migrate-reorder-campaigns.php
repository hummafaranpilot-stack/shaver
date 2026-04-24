<?php
/**
 * Migration: Create reorder_campaigns table for daily recurring reorder emails
 * Run once via browser: https://shaver.trustednutraproduct.com/migrate-reorder-campaigns.php
 * DELETE THIS FILE after running!
 */
require_once __DIR__ . '/config.php';
$pdo = getDB();

$migrations = [
    "CREATE TABLE IF NOT EXISTS `reorder_campaigns` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `domain_id` INT NOT NULL,
        `order_id` VARCHAR(20) NOT NULL,
        `customer_email` VARCHAR(255) NOT NULL,
        `recipients` TEXT NOT NULL COMMENT 'JSON array of email addresses',
        `coupon_code` VARCHAR(100) DEFAULT NULL,
        `coupon_type` VARCHAR(20) DEFAULT NULL,
        `coupon_amount` DECIMAL(10,2) DEFAULT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, paused, reordered, completed, stopped',
        `sends_count` INT NOT NULL DEFAULT 0,
        `max_sends` INT NOT NULL DEFAULT 30 COMMENT 'Max daily emails before auto-stop',
        `next_send_at` DATETIME NOT NULL COMMENT 'When the next email should fire',
        `last_sent_at` DATETIME DEFAULT NULL,
        `last_error` TEXT DEFAULT NULL,
        `reorder_order_id` VARCHAR(20) DEFAULT NULL COMMENT 'Order ID if customer reordered',
        `stopped_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_status_next` (`status`, `next_send_at`),
        INDEX `idx_domain_order` (`domain_id`, `order_id`),
        INDEX `idx_customer_email` (`customer_email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

echo "<pre>\n";
foreach ($migrations as $sql) {
    try {
        $pdo->exec($sql);
        echo "[OK] " . substr($sql, 0, 60) . "...\n";
    } catch (PDOException $e) {
        echo "[SKIP] " . $e->getMessage() . "\n";
    }
}
echo "\nDone! DELETE this file now.\n</pre>";
