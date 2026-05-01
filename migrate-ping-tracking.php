<?php
/**
 * Migration: ICMP-ping latency tracking on affiliate_traffic
 *
 * Adds two columns:
 *   ping_ms          INT NULL — round-trip ms from server to visitor IP.
 *                                NULL = not yet pinged. -1 = pinged but no
 *                                response (firewalled / unreachable).
 *   ping_checked_at  TIMESTAMP NULL — when the ping was attempted.
 *
 * The cron-pinger.php script fills these in for new visits within 1-2 minutes
 * of the visit, with no impact on visitor page-load speed.
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

ensureCol($pdo, 'ping_ms',
    "`ping_ms` INT NULL DEFAULT NULL COMMENT 'server-to-IP ICMP ping ms; -1 = no response'",
    $results);

ensureCol($pdo, 'ping_checked_at',
    "`ping_checked_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'when the ping was attempted'",
    $results);

// Index for the cron picker (find unping'd recent rows fast)
try {
    $stmt = $pdo->query("SHOW INDEX FROM affiliate_traffic WHERE Key_name = 'idx_ping_pending'");
    if ($stmt->fetch()) {
        $results[] = "[SKIP] index idx_ping_pending already exists";
    } else {
        $pdo->exec("ALTER TABLE affiliate_traffic ADD INDEX idx_ping_pending (ping_checked_at, timestamp)");
        $results[] = "[OK] Added idx_ping_pending";
    }
} catch (Exception $e) {
    $results[] = "[FAIL] index: " . $e->getMessage();
}

// Verify
try {
    $cols = $pdo->query("SHOW COLUMNS FROM affiliate_traffic")->fetchAll(PDO::FETCH_COLUMN, 0);
    $results[] = "";
    $results[] = "Verification:";
    foreach (['ping_ms', 'ping_checked_at'] as $c) {
        $results[] = "  $c .................... " . (in_array($c, $cols) ? 'OK' : 'MISSING');
    }
    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM affiliate_traffic WHERE ping_checked_at IS NULL AND ip_address IS NOT NULL AND ip_address != ''")->fetchColumn();
    $results[] = "  Rows pending ping ........ $pendingCount";
} catch (Exception $e) {
    $results[] = "[FAIL] Verification: " . $e->getMessage();
}

echo "Migration: ping-tracking\n";
echo "==========================\n\n";
echo implode("\n", $results) . "\n\n";
echo "Done. Next: visit diagnose-ping.php to confirm exec() is allowed.\n";
