<?php
/**
 * Migration: Add fulfillment_name column to orders table
 * Run once via browser: https://shaver.trustednutraproduct.com/migrate-fulfillment-name.php
 * DELETE THIS FILE after running!
 */
require_once __DIR__ . '/config.php';
$pdo = getDB();

try {
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'fulfillment_name'");
    if ($stmt->rowCount() > 0) {
        echo "<h3 style='color:orange;'>Column `fulfillment_name` already exists. No changes made.</h3>";
    } else {
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `fulfillment_name` VARCHAR(255) DEFAULT NULL AFTER `fulfillment_status`");
        echo "<h3 style='color:green;'>&#10004; Column `fulfillment_name` added to orders table successfully!</h3>";
    }
} catch (Exception $e) {
    echo "<h3 style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
