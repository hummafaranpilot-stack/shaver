<?php
/**
 * Migration: Add reorder_mail_sent column to orders table
 * Run once via browser: https://shaver.trustednutraproduct.com/migrate-reorder-mail.php
 * DELETE THIS FILE after running!
 */
require_once __DIR__ . '/config.php';
$pdo = getDB();

$migrations = [
    "ALTER TABLE `orders`
        ADD COLUMN `reorder_mail_sent` TINYINT(1) DEFAULT 0 AFTER `delay_mail_sent`"
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
