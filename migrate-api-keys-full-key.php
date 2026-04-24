<?php
/**
 * Migration: Store full API key alongside the hash
 *
 * Originally keys were hashed-only (SHA-256) so they could be verified
 * but never re-read. User now wants the ability to copy keys from the
 * admin UI at any time, so this migration adds a `full_key` column.
 *
 * Existing hashed-only keys cannot be recovered — they will show
 * "Not retrievable" in the UI until regenerated.
 *
 * Visit once. Idempotent.
 */

require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = getDB();
$results = [];

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM api_keys LIKE 'full_key'");
    $exists = $stmt->fetch() !== false;

    if ($exists) {
        $results[] = "[SKIP] Column `full_key` already exists";
    } else {
        $pdo->exec("ALTER TABLE api_keys ADD COLUMN `full_key` VARCHAR(64) NULL DEFAULT NULL AFTER `key_hash`");
        $results[] = "[OK] Added `full_key` column to api_keys";
    }
} catch (Exception $e) {
    $results[] = "[FAIL] " . $e->getMessage();
}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM api_keys LIKE 'full_key'");
    $ok = $stmt->fetch() !== false;
    $results[] = "";
    $results[] = "Verification:";
    $results[] = "  Column exists ............. " . ($ok ? 'OK' : 'FAILED');

    $legacy = (int)$pdo->query("SELECT COUNT(*) FROM api_keys WHERE full_key IS NULL")->fetchColumn();
    $results[] = "  Legacy keys (unrecoverable): " . $legacy;
    if ($legacy > 0) {
        $results[] = "  → These keys still authenticate, but cannot be displayed.";
        $results[] = "  → Regenerate them from api.html to enable copy.";
    }
} catch (Exception $e) {
    $results[] = "[FAIL] Verification: " . $e->getMessage();
}

echo "Migration: api-keys full_key column\n";
echo "====================================\n\n";
echo implode("\n", $results) . "\n\n";
echo "Done.\n";
