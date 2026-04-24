<?php
/**
 * Migration: Add coupon columns to scheduled_emails table
 */
require_once __DIR__ . '/config.php';
$pdo = getDB();

$sqls = [
    "ALTER TABLE `scheduled_emails` ADD COLUMN `coupon_code` VARCHAR(100) DEFAULT NULL AFTER `recipients`",
    "ALTER TABLE `scheduled_emails` ADD COLUMN `coupon_type` VARCHAR(20) DEFAULT NULL AFTER `coupon_code`",
    "ALTER TABLE `scheduled_emails` ADD COLUMN `coupon_amount` DECIMAL(10,2) DEFAULT NULL AFTER `coupon_type`"
];

echo "<pre>\n";
foreach ($sqls as $sql) {
    try {
        $pdo->exec($sql);
        echo "[OK] " . substr($sql, 0, 80) . "...\n";
    } catch (PDOException $e) {
        echo "[SKIP] " . $e->getMessage() . "\n";
    }
}
echo "\nDone! DELETE this file now.\n</pre>";
