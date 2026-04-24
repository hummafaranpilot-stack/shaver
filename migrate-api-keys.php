<?php
/**
 * Migration: API Keys
 *
 * Creates the `api_keys` table so admins can generate Bearer tokens
 * for the Shaver public REST API (api-v1.php).
 *
 * Visit once via browser; delete after. Idempotent — safe to re-run.
 */

require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = getDB();
$results = [];

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `api_keys` (
            `id`          INT AUTO_INCREMENT PRIMARY KEY,
            `label`       VARCHAR(100) NOT NULL,
            `domain_id`   INT DEFAULT NULL COMMENT 'NULL = all domains',
            `key_prefix`  VARCHAR(12) NOT NULL COMMENT 'first 8 hex chars for display',
            `key_hash`    VARCHAR(64) NOT NULL COMMENT 'SHA-256 of full key',
            `status`      ENUM('active','revoked') NOT NULL DEFAULT 'active',
            `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `last_used_at` TIMESTAMP NULL DEFAULT NULL,
            INDEX `idx_hash`   (`key_hash`),
            INDEX `idx_domain` (`domain_id`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $results[] = "[OK] Created/verified `api_keys` table";
} catch (Exception $e) {
    $results[] = "[FAIL] " . $e->getMessage();
}

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'api_keys'");
    $exists = $stmt->fetch() !== false;
    $results[] = "";
    $results[] = "Verification:";
    $results[] = "  Table exists .............. " . ($exists ? 'OK' : 'FAILED');
    if ($exists) {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM api_keys")->fetchColumn();
        $results[] = "  Current row count ......... " . $cnt;
    }
} catch (Exception $e) {
    $results[] = "[FAIL] Verification: " . $e->getMessage();
}

echo "Migration: api-keys\n";
echo "===================\n\n";
echo implode("\n", $results) . "\n\n";
echo "Done.\n";
