<?php
/**
 * Migration: Add ClickBank platform support
 * - Expand platform column to VARCHAR(20) to support 'clickbank'
 * - Add cb_vendor column to domains
 * - Add cb_params JSON column to affiliate_traffic
 */
require_once __DIR__ . '/config.php';

try {
    $pdo = getDB();

    // 1. Expand platform column from ENUM to VARCHAR(20)
    $pdo->exec("ALTER TABLE `domains` MODIFY COLUMN `platform` VARCHAR(20) NOT NULL DEFAULT 'buygoods'");
    echo "✓ domains.platform expanded to VARCHAR(20)<br>";

    // 2. Add cb_vendor column to domains
    try {
        $pdo->exec("ALTER TABLE `domains` ADD COLUMN `cb_vendor` VARCHAR(100) DEFAULT NULL AFTER `ds24_product_id`");
        echo "✓ domains.cb_vendor added<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "⚠ domains.cb_vendor already exists<br>";
        } else {
            throw $e;
        }
    }

    // 3. Add cb_params JSON column to affiliate_traffic
    try {
        $pdo->exec("ALTER TABLE `affiliate_traffic` ADD COLUMN `cb_params` JSON DEFAULT NULL AFTER `sessid2`");
        echo "✓ affiliate_traffic.cb_params added<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "⚠ affiliate_traffic.cb_params already exists<br>";
        } else {
            throw $e;
        }
    }

    echo "<br><strong>Migration complete! Delete this file now.</strong>";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
