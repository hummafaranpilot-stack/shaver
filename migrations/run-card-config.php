<?php
/**
 * Migration: Add card_config JSON column to domains table.
 * Stores auto-detected card structure per domain.
 * Visit URL once to apply. Idempotent.
 */
require_once __DIR__ . '/../config.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = getDB();
$results = [];

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM `domains` LIKE 'card_config'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `domains` ADD COLUMN `card_config` JSON DEFAULT NULL COMMENT 'Auto-detected card structure [{slot,label}]' AFTER `shipping_enabled`");
        $results[] = "[OK] Added column `domains.card_config`";
    } else {
        $results[] = "[SKIP] Column `card_config` already exists";
    }
} catch (Exception $e) {
    $results[] = "[FAIL] " . $e->getMessage();
}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM `domains` LIKE 'card_config'");
    $ok = $stmt->rowCount() > 0;
    $results[] = "\nVerification:\n  card_config column .... " . ($ok ? 'OK' : 'FAILED');
} catch (Exception $e) {
    $results[] = "[FAIL] " . $e->getMessage();
}

echo "Migration: card-config\n======================\n\n" . implode("\n", $results) . "\n\nDone.\n";
