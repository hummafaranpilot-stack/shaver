<?php
/**
 * Migration: Create cb_checkout_configs table
 * Run once via browser: https://shaver.trustednutraproduct.com/migrate-checkout-links.php
 * DELETE THIS FILE after running!
 */
require_once __DIR__ . '/config.php';
$pdo = getDB();

$sql = "CREATE TABLE IF NOT EXISTS `cb_checkout_configs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `domain_id` INT NOT NULL,
    `config_type` VARCHAR(20) DEFAULT 'main',
    `nickname` VARCHAR(100) NOT NULL,
    `packages` JSON NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_domain_type` (`domain_id`, `config_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

echo "<pre>\n";
try {
    $pdo->exec($sql);
    echo "[OK] Created 'cb_checkout_configs' table successfully.\n";
} catch (PDOException $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
echo "\nDone! DELETE this file now.\n</pre>";
