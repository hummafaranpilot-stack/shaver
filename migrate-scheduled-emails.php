<?php
/**
 * Migration: Create scheduled_emails table
 * Run once via browser: https://shaver.trustednutraproduct.com/migrate-scheduled-emails.php
 * DELETE THIS FILE after running!
 */
require_once __DIR__ . '/config.php';
$pdo = getDB();

$migrations = [
    "CREATE TABLE IF NOT EXISTS `scheduled_emails` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `domain_id` INT NOT NULL,
        `order_id` VARCHAR(20) NOT NULL,
        `email_type` VARCHAR(30) NOT NULL DEFAULT 'reorder',
        `recipients` TEXT NOT NULL,
        `scheduled_at` DATETIME NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `sent_at` DATETIME NULL,
        `error_message` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_status_scheduled` (`status`, `scheduled_at`),
        INDEX `idx_domain_order` (`domain_id`, `order_id`)
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
