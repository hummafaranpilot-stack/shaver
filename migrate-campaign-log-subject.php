<?php
/**
 * Migration: Add subject column to reorder_mail_log
 * Run once via browser: https://shaver.trustednutraproduct.com/migrate-campaign-log-subject.php
 * DELETE THIS FILE after running!
 */
require_once __DIR__ . '/config.php';
$pdo = getDB();

$migrations = [
    "ALTER TABLE reorder_mail_log ADD COLUMN `subject` VARCHAR(255) DEFAULT NULL AFTER `recipients`",
    "ALTER TABLE reorder_mail_log ADD COLUMN `send_type` VARCHAR(20) DEFAULT 'manual' AFTER `subject`",
    "ALTER TABLE reorder_mail_log ADD COLUMN `campaign_day` INT DEFAULT NULL AFTER `send_type`"
];

echo "<pre>\n";
foreach ($migrations as $sql) {
    try {
        $pdo->exec($sql);
        echo "[OK] " . substr($sql, 0, 80) . "...\n";
    } catch (PDOException $e) {
        echo "[SKIP] " . $e->getMessage() . "\n";
    }
}
echo "\nDone! DELETE this file now.\n</pre>";
