<?php
/**
 * Migration: Fix shaver data integrity issues
 *
 * 1. Add UNIQUE constraint on (domain_id, session_uuid, page_type) to prevent duplicate traffic entries
 * 2. Add index on session_uuid for fast dedup lookups
 * 3. Add index on sessid2 for fast funnel matching
 * 4. Clean up existing duplicates before adding constraint
 */

require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $results = [];

    // Step 1: Remove duplicate entries (keep the earliest one per session_uuid + domain_id + page_type)
    $pdo->exec("
        DELETE t1 FROM affiliate_traffic t1
        INNER JOIN affiliate_traffic t2
        ON t1.domain_id = t2.domain_id
            AND t1.session_uuid = t2.session_uuid
            AND t1.page_type = t2.page_type
            AND t1.id > t2.id
        WHERE t1.session_uuid IS NOT NULL AND t1.session_uuid != ''
    ");
    $results[] = "Cleaned duplicate traffic entries";

    // Step 2: Add UNIQUE index on (domain_id, session_uuid, page_type)
    // First check if it already exists
    $existing = $pdo->query("SHOW INDEX FROM affiliate_traffic WHERE Key_name = 'uq_session_domain_pagetype'")->fetchAll();
    if (empty($existing)) {
        $pdo->exec("
            ALTER TABLE affiliate_traffic
            ADD UNIQUE INDEX uq_session_domain_pagetype (domain_id, session_uuid, page_type)
        ");
        $results[] = "Added UNIQUE index on (domain_id, session_uuid, page_type)";
    } else {
        $results[] = "UNIQUE index already exists";
    }

    // Step 3: Add index on session_uuid for dedup query performance
    $existing = $pdo->query("SHOW INDEX FROM affiliate_traffic WHERE Key_name = 'idx_session_uuid'")->fetchAll();
    if (empty($existing)) {
        $pdo->exec("ALTER TABLE affiliate_traffic ADD INDEX idx_session_uuid (session_uuid)");
        $results[] = "Added index on session_uuid";
    } else {
        $results[] = "session_uuid index already exists";
    }

    // Step 4: Add index on sessid2 for funnel lookup performance
    $existing = $pdo->query("SHOW INDEX FROM affiliate_traffic WHERE Key_name = 'idx_sessid2'")->fetchAll();
    if (empty($existing)) {
        $pdo->exec("ALTER TABLE affiliate_traffic ADD INDEX idx_sessid2 (sessid2)");
        $results[] = "Added index on sessid2";
    } else {
        $results[] = "sessid2 index already exists";
    }

    // Step 5: Add index on shaving_session_id for linking sessions to traffic
    $existing = $pdo->query("SHOW INDEX FROM affiliate_traffic WHERE Key_name = 'idx_shaving_session_id'")->fetchAll();
    if (empty($existing)) {
        $pdo->exec("ALTER TABLE affiliate_traffic ADD INDEX idx_shaving_session_id (shaving_session_id)");
        $results[] = "Added index on shaving_session_id";
    } else {
        $results[] = "shaving_session_id index already exists";
    }

    echo "<h2>Migration: Fix Shaver Data Integrity</h2>";
    echo "<ul>";
    foreach ($results as $r) {
        echo "<li style='color:green;'>✓ $r</li>";
    }
    echo "</ul>";
    echo "<p style='color:green; font-weight:bold;'>Migration completed successfully!</p>";
    echo "<p><strong>Delete this file after running.</strong></p>";

} catch (PDOException $e) {
    echo "<h2>Migration Error</h2>";
    echo "<p style='color:red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
}
