<?php
/**
 * Migration: Add matched_order_id column to affiliate_traffic
 * Run once via browser: https://shaver.trustednutraproduct.com/migrate-matched-order.php
 * DELETE THIS FILE after running!
 */
require_once __DIR__ . '/config.php';
$pdo = getDB();

echo "<pre>\n";

$migrations = [
    "ADD COLUMN matched_order_id" => "ALTER TABLE `affiliate_traffic` ADD COLUMN `matched_order_id` VARCHAR(20) DEFAULT NULL AFTER `sessid2`",
    "ADD INDEX idx_matched_order"  => "ALTER TABLE `affiliate_traffic` ADD KEY `idx_matched_order` (`matched_order_id`)"
];

foreach ($migrations as $label => $sql) {
    try {
        $pdo->exec($sql);
        echo "<span style='color:green;'>[OK]</span> $label\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "<span style='color:orange;'>[SKIP]</span> $label — already exists\n";
        } else {
            echo "<span style='color:red;'>[ERROR]</span> $label — " . $e->getMessage() . "\n";
        }
    }
}

echo "\n<strong>Done! DELETE this file now.</strong>\n</pre>";
