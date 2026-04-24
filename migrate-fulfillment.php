<?php
/**
 * Migration: Add fulfillment tracking columns to orders table
 * Run once via browser: https://shaver.trustednutraproduct.com/migrate-fulfillment.php
 * DELETE THIS FILE after running!
 */
require_once __DIR__ . '/config.php';
$pdo = getDB();

$migrations = [
    "ALTER TABLE `orders`
        ADD COLUMN `fulfillment_status` ENUM('pending','shipped','delayed','delivered') DEFAULT 'pending' AFTER `created_at`,
        ADD COLUMN `delay_reason` TEXT DEFAULT NULL AFTER `fulfillment_status`,
        ADD COLUMN `expected_delivery` DATE DEFAULT NULL AFTER `delay_reason`,
        ADD COLUMN `delivered_date` DATE DEFAULT NULL AFTER `expected_delivery`,
        ADD COLUMN `compensation_offered` TINYINT(1) DEFAULT 0 AFTER `delivered_date`,
        ADD COLUMN `compensation_amount` DECIMAL(10,2) DEFAULT 0 AFTER `compensation_offered`,
        ADD COLUMN `compensation_notes` TEXT DEFAULT NULL AFTER `compensation_amount`,
        ADD COLUMN `internal_notes` TEXT DEFAULT NULL AFTER `compensation_notes`"
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
