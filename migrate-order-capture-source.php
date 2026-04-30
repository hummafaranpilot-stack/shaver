<?php
/**
 * Migration: Add capture_source + flag_upsell_level columns to orders
 *
 * Lets us distinguish CSV-uploaded orders from URL-captured orders
 * (auto-grabbed when buyer lands on upsell1.html / upsell2.html with
 * order_id in the URL params), and tag which upsell level the row
 * came from (0 = original purchase, 1 = upsell1 sale, etc.).
 *
 * Visit once. Idempotent.
 */

require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = getDB();
$results = [];

function ensureColumn(PDO $pdo, string $col, string $ddl, array &$results): void {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE '" . str_replace("'", '', $col) . "'");
        if ($stmt->fetch()) {
            $results[] = "[SKIP] orders.$col already exists";
        } else {
            $pdo->exec("ALTER TABLE orders ADD COLUMN $ddl");
            $results[] = "[OK] Added orders.$col";
        }
    } catch (Exception $e) {
        $results[] = "[FAIL] orders.$col: " . $e->getMessage();
    }
}

ensureColumn($pdo, 'capture_source',
    "`capture_source` VARCHAR(20) NOT NULL DEFAULT 'csv' COMMENT 'csv | url_capture | clickbank_api | manual'",
    $results);

ensureColumn($pdo, 'flag_upsell_level',
    "`flag_upsell_level` TINYINT NOT NULL DEFAULT 0 COMMENT '0=initial purchase, 1=upsell1 sale, 2=upsell2 sale, 3=upsell3 sale'",
    $results);

ensureColumn($pdo, 'order_id_global',
    "`order_id_global` VARCHAR(40) DEFAULT NULL COMMENT 'BG global order ID (e.g. A0NZ2UOR)'",
    $results);

// Verify
try {
    $cols = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN, 0);
    $results[] = "";
    $results[] = "Verification:";
    foreach (['capture_source', 'flag_upsell_level', 'order_id_global'] as $c) {
        $results[] = "  $c .................. " . (in_array($c, $cols) ? 'OK' : 'MISSING');
    }
} catch (Exception $e) {
    $results[] = "[FAIL] Verification: " . $e->getMessage();
}

echo "Migration: orders capture-source columns\n";
echo "==========================================\n\n";
echo implode("\n", $results) . "\n\n";
echo "Done.\n";
