<?php
/**
 * Migration: FB CAPI columns on affiliate_traffic
 *
 * Adds three columns supporting server-side Facebook Conversions API:
 *
 *   viewcontent_fired  TINYINT(1) — guard so updateSessionMetrics fires
 *                                   ViewContent CAPI exactly once per visit
 *   fbc                VARCHAR(255) — Facebook click ID cookie (_fbc),
 *                                     captured at log_traffic time, reused
 *                                     on later events for high EMQ
 *   fbp                VARCHAR(255) — Facebook browser ID cookie (_fbp),
 *                                     same purpose as fbc
 *
 * Visit once. Idempotent.
 */

require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = getDB();
$results = [];

function ensureCol(PDO $pdo, string $col, string $ddl, array &$results): void {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM affiliate_traffic LIKE '$col'");
        if ($stmt->fetch()) {
            $results[] = "[SKIP] affiliate_traffic.$col already exists";
        } else {
            $pdo->exec("ALTER TABLE affiliate_traffic ADD COLUMN $ddl");
            $results[] = "[OK] Added affiliate_traffic.$col";
        }
    } catch (Exception $e) {
        $results[] = "[FAIL] $col: " . $e->getMessage();
    }
}

ensureCol($pdo, 'viewcontent_fired',
    "`viewcontent_fired` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'CAPI ViewContent fired guard'",
    $results);

ensureCol($pdo, 'fbc',
    "`fbc` VARCHAR(255) DEFAULT NULL COMMENT 'Facebook _fbc cookie value'",
    $results);

ensureCol($pdo, 'fbp',
    "`fbp` VARCHAR(255) DEFAULT NULL COMMENT 'Facebook _fbp cookie value'",
    $results);

// Verify
try {
    $cols = $pdo->query("SHOW COLUMNS FROM affiliate_traffic")->fetchAll(PDO::FETCH_COLUMN, 0);
    $results[] = "";
    $results[] = "Verification:";
    foreach (['viewcontent_fired', 'fbc', 'fbp'] as $c) {
        $results[] = "  $c .................... " . (in_array($c, $cols) ? 'OK' : 'MISSING');
    }
} catch (Exception $e) {
    $results[] = "[FAIL] Verification: " . $e->getMessage();
}

echo "Migration: fb-capi-columns\n";
echo "===========================\n\n";
echo implode("\n", $results) . "\n\n";
echo "Done.\n";
