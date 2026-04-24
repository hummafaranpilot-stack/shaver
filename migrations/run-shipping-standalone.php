<?php
/**
 * Migration: Make bg_tracking_script nullable for shipping-only domains
 * Visit this URL once to apply. Idempotent — safe to re-run.
 */

require_once __DIR__ . '/../config.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = getDB();
$results = [];

// 1. Make bg_tracking_script nullable
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM `domains` WHERE Field = 'bg_tracking_script'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($col && $col['Null'] === 'NO') {
        $pdo->exec("ALTER TABLE `domains` MODIFY `bg_tracking_script` TEXT DEFAULT NULL");
        $results[] = "[OK] Made `bg_tracking_script` nullable";
    } else {
        $results[] = "[SKIP] `bg_tracking_script` is already nullable";
    }
} catch (Exception $e) {
    $results[] = "[FAIL] " . $e->getMessage();
}

// 2. Verify
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM `domains` WHERE Field = 'bg_tracking_script'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    $ok = $col && $col['Null'] === 'YES';
    $results[] = "";
    $results[] = "Verification:";
    $results[] = "  bg_tracking_script nullable .... " . ($ok ? 'OK' : 'FAILED');
} catch (Exception $e) {
    $results[] = "[FAIL] Verification: " . $e->getMessage();
}

echo "Migration: shipping-standalone\n";
echo "==============================\n\n";
echo implode("\n", $results) . "\n\n";
echo "Done.\n";
