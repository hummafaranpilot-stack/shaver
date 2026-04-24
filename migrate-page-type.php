<?php
/**
 * Migration: Add page_type and sessid2 columns to affiliate_traffic
 * Run once via browser: https://shaver.trustednutraproduct.com/migrate-page-type.php
 * DELETE THIS FILE after running!
 */
require_once __DIR__ . '/config.php';
$pdo = getDB();

echo "<pre>\n";

$migrations = [
    "ADD COLUMN page_type" => "ALTER TABLE `affiliate_traffic` ADD COLUMN `page_type` VARCHAR(20) NOT NULL DEFAULT 'landing' AFTER `page_url`",
    "ADD COLUMN sessid2"   => "ALTER TABLE `affiliate_traffic` ADD COLUMN `sessid2` VARCHAR(100) DEFAULT NULL AFTER `page_type`",
    "ADD INDEX idx_page_type" => "ALTER TABLE `affiliate_traffic` ADD KEY `idx_page_type` (`page_type`)",
    "ADD INDEX idx_sessid2"   => "ALTER TABLE `affiliate_traffic` ADD KEY `idx_sessid2` (`sessid2`)"
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
