<?php
/**
 * Migration: initiatecheckout_fired column on affiliate_traffic
 *
 * Guards the server-side CAPI InitiateCheckout event so it fires
 * exactly once per visit, even if the buyer clicks multiple BuyNow
 * pricing-card images (which would otherwise emit duplicate
 * buynow_click events).
 *
 * Visit once. Idempotent.
 */

require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = getDB();
$results = [];

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM affiliate_traffic LIKE 'initiatecheckout_fired'");
    if ($stmt->fetch()) {
        $results[] = "[SKIP] affiliate_traffic.initiatecheckout_fired already exists";
    } else {
        $pdo->exec("
            ALTER TABLE affiliate_traffic
            ADD COLUMN `initiatecheckout_fired` TINYINT(1) NOT NULL DEFAULT 0
            COMMENT 'CAPI InitiateCheckout fired guard'
        ");
        $results[] = "[OK] Added affiliate_traffic.initiatecheckout_fired";
    }
} catch (Exception $e) {
    $results[] = "[FAIL] " . $e->getMessage();
}

try {
    $cols = $pdo->query("SHOW COLUMNS FROM affiliate_traffic")->fetchAll(PDO::FETCH_COLUMN, 0);
    $results[] = "";
    $results[] = "Verification:";
    $results[] = "  initiatecheckout_fired ... " . (in_array('initiatecheckout_fired', $cols) ? 'OK' : 'MISSING');
} catch (Exception $e) {
    $results[] = "[FAIL] Verification: " . $e->getMessage();
}

echo "Migration: fb-initiatecheckout\n";
echo "================================\n\n";
echo implode("\n", $results) . "\n\n";
echo "Done.\n";
