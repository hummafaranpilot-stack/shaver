<?php
/**
 * Migration: Add visitor signal detection columns
 * Run once via browser: https://shaver.trustednutraproduct.com/migrate-visitor-signals.php
 * DELETE THIS FILE after running!
 */
require_once __DIR__ . '/config.php';
$pdo = getDB();

$migrations = [
    "ALTER TABLE `affiliate_traffic`
        ADD COLUMN `is_bot` TINYINT(1) DEFAULT 0 AFTER `bounce`,
        ADD COLUMN `bot_flags` VARCHAR(255) DEFAULT NULL AFTER `is_bot`,
        ADD COLUMN `is_iframe` TINYINT(1) DEFAULT 0 AFTER `bot_flags`,
        ADD COLUMN `has_adblock` TINYINT(1) DEFAULT NULL AFTER `is_iframe`,
        ADD COLUMN `js_error_count` INT DEFAULT 0 AFTER `has_adblock`,
        ADD COLUMN `js_errors` TEXT DEFAULT NULL AFTER `js_error_count`"
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
