<?php
/**
 * Migration: 3 fire-once guard columns for custom CAPI engagement events.
 *
 *   engagedvisitor_fired  TINYINT(1) — session_duration >= 60s
 *   scrolldeep_fired      TINYINT(1) — max_scroll_depth >= 75
 *   videoengaged_fired    TINYINT(1) — video_play behavior event
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

ensureCol($pdo, 'engagedvisitor_fired',
    "`engagedvisitor_fired` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'CAPI EngagedVisitor fired guard (60s threshold)'",
    $results);
ensureCol($pdo, 'scrolldeep_fired',
    "`scrolldeep_fired` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'CAPI ScrollDeep fired guard (75% threshold)'",
    $results);
ensureCol($pdo, 'videoengaged_fired',
    "`videoengaged_fired` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'CAPI VideoEngaged fired guard (first video_play)'",
    $results);

try {
    $cols = $pdo->query("SHOW COLUMNS FROM affiliate_traffic")->fetchAll(PDO::FETCH_COLUMN, 0);
    $results[] = "";
    $results[] = "Verification:";
    foreach (['engagedvisitor_fired', 'scrolldeep_fired', 'videoengaged_fired'] as $c) {
        $results[] = "  $c .................. " . (in_array($c, $cols) ? 'OK' : 'MISSING');
    }
} catch (Exception $e) {
    $results[] = "[FAIL] Verification: " . $e->getMessage();
}

echo "Migration: fb-engagement-events\n";
echo "================================\n\n";
echo implode("\n", $results) . "\n\n";
echo "Done.\n";
