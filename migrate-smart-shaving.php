<?php
/**
 * Migration: Add Smart Shaving support
 * - shaving_sessions: shave_mode, smart_skip_next, smart_last_traffic_id
 * - affiliate_traffic: smart_skipped
 * - shaving_history: shave_mode
 */
require_once __DIR__ . '/config.php';
$pdo = getDB();

$queries = [
    // Smart shaving columns on shaving_sessions
    "ALTER TABLE shaving_sessions ADD COLUMN shave_mode ENUM('instant','smart') NOT NULL DEFAULT 'instant' AFTER mode",
    "ALTER TABLE shaving_sessions ADD COLUMN smart_skip_next TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE shaving_sessions ADD COLUMN smart_last_traffic_id INT DEFAULT NULL",

    // Flag on traffic log for smart-skipped visits
    "ALTER TABLE affiliate_traffic ADD COLUMN smart_skipped TINYINT(1) NOT NULL DEFAULT 0 AFTER was_shaved",

    // Archive shave_mode in history too
    "ALTER TABLE shaving_history ADD COLUMN shave_mode ENUM('instant','smart') NOT NULL DEFAULT 'instant' AFTER mode",
];

echo "<pre>\n";
foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: $sql\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "SKIP (already exists): $sql\n";
        } else {
            echo "ERROR: $sql\n  → " . $e->getMessage() . "\n";
        }
    }
}
echo "\nDone!\n</pre>";
