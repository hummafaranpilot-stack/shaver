<?php
/**
 * Migration: header_snippets
 *
 * Tag-Manager-lite — admins create named "snippets" with arbitrary HTML/JS,
 * each snippet gets a unique URL (using a random 12-char key, not a
 * predictable sequential ID), they paste that URL as a <script src="...">
 * on whatever landing pages need the code. Editing the code in the
 * snippet auto-deploys to every page using it.
 *
 * Visit once. Idempotent.
 */

require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = getDB();
$results = [];

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `header_snippets` (
            `id`           INT AUTO_INCREMENT PRIMARY KEY,
            `snippet_key`  VARCHAR(16) NOT NULL COMMENT 'random hex, used in <script src=...?v=KEY>',
            `label`        VARCHAR(150) NOT NULL,
            `code`         MEDIUMTEXT DEFAULT NULL,
            `enabled`      TINYINT(1) NOT NULL DEFAULT 1,
            `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_key` (`snippet_key`),
            INDEX `idx_enabled` (`enabled`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $results[] = "[OK] Created/verified `header_snippets` table";
} catch (Exception $e) {
    $results[] = "[FAIL] " . $e->getMessage();
}

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'header_snippets'");
    $exists = $stmt->fetch() !== false;
    $results[] = "";
    $results[] = "Verification:";
    $results[] = "  Table exists ............ " . ($exists ? 'OK' : 'FAILED');
    if ($exists) {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM header_snippets")->fetchColumn();
        $results[] = "  Current row count ....... $cnt";
    }
} catch (Exception $e) {
    $results[] = "[FAIL] Verification: " . $e->getMessage();
}

echo "Migration: header-snippets\n";
echo "===========================\n\n";
echo implode("\n", $results) . "\n\n";
echo "Done.\n";
