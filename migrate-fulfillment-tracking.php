<?php
/**
 * Migration: Add sent_to_fulfillment and dispatch_tracking columns to orders table
 * Run once via browser, then delete this file.
 */
require_once __DIR__ . '/config.php';
$pdo = getDB();

$queries = [
    "ALTER TABLE orders ADD COLUMN sent_to_fulfillment TINYINT(1) DEFAULT 0 AFTER fulfillment_name",
    "ALTER TABLE orders ADD COLUMN dispatch_tracking VARCHAR(200) DEFAULT NULL AFTER sent_to_fulfillment",
];

echo "<h2>Migration: Fulfillment Tracking Columns</h2><pre>";

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: $sql\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "SKIP (already exists): $sql\n";
        } else {
            echo "ERROR: " . $e->getMessage() . "\n  SQL: $sql\n";
        }
    }
}

echo "\nDone! Delete this file now.</pre>";
