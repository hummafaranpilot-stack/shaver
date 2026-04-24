<?php
/**
 * Migration: Add checkout_urls columns to shave_snapshots table
 * Stores buy link URLs captured before and after shaving
 */
require_once __DIR__ . '/config.php';

try {
    $pdo = getDB();

    $pdo->exec("ALTER TABLE shave_snapshots ADD COLUMN before_checkout_urls JSON DEFAULT NULL AFTER before_url_params");
    $pdo->exec("ALTER TABLE shave_snapshots ADD COLUMN after_checkout_urls JSON DEFAULT NULL AFTER after_url_params");

    echo "<h2 style='color:green'>✅ checkout_urls columns added to shave_snapshots!</h2>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ Migration failed</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
