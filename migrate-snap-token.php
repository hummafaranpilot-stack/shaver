<?php
/**
 * Migration: Add snap_token column to shave_snapshots for reliable before/after matching
 * Run once then DELETE this file.
 */
require_once __DIR__ . '/config.php';
$pdo = getDB();

$sqls = [
    "ALTER TABLE `shave_snapshots` ADD COLUMN `snap_token` VARCHAR(30) DEFAULT '' AFTER `platform`",
    "ALTER TABLE `shave_snapshots` ADD INDEX `idx_snap_token` (`snap_token`)"
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
