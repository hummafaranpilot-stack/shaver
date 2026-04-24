<?php
/**
 * Migration: Add ipqs_raw column to affiliate_traffic table
 * Stores full IPQS API response for detailed fraud analysis display
 */
require_once __DIR__ . '/config.php';

try {
    $pdo = getDB();
    $pdo->exec("ALTER TABLE affiliate_traffic ADD COLUMN ipqs_raw JSON DEFAULT NULL AFTER fraud_flags");
    echo "<h2 style='color:green'>✅ ipqs_raw column added to affiliate_traffic!</h2>";
} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ Migration failed</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
