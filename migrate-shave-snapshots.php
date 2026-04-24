<?php
/**
 * Migration: Create shave_snapshots table
 * Stores before/after cookie snapshots for shaved visits, matched by IP + User-Agent
 */
require_once __DIR__ . '/config.php';

try {
    $pdo = getDB();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shave_snapshots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            domain_id INT NOT NULL,
            session_id INT DEFAULT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(512) DEFAULT '',
            aff_id VARCHAR(100) DEFAULT '',
            sub_id VARCHAR(100) DEFAULT '',
            mode VARCHAR(20) DEFAULT 'remove',
            replace_aff_id VARCHAR(100) DEFAULT '',
            replace_sub_id VARCHAR(100) DEFAULT '',
            platform VARCHAR(20) DEFAULT 'buygoods',

            -- BEFORE phase
            before_url TEXT DEFAULT NULL,
            before_sessid2 VARCHAR(200) DEFAULT '',
            before_cookies JSON DEFAULT NULL,
            before_cookie_count INT DEFAULT 0,
            before_url_params JSON DEFAULT NULL,
            before_at DATETIME DEFAULT NULL,

            -- AFTER phase
            after_url TEXT DEFAULT NULL,
            after_sessid2 VARCHAR(200) DEFAULT '',
            after_cookies JSON DEFAULT NULL,
            after_cookie_count INT DEFAULT 0,
            after_url_params JSON DEFAULT NULL,
            after_at DATETIME DEFAULT NULL,

            -- Computed diff (filled when AFTER arrives)
            sessid2_changed TINYINT(1) DEFAULT 0,
            cookies_added JSON DEFAULT NULL,
            cookies_removed JSON DEFAULT NULL,
            cookies_changed JSON DEFAULT NULL,

            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_domain (domain_id),
            INDEX idx_ip_ua (ip_address, user_agent(100)),
            INDEX idx_session (session_id),
            INDEX idx_created (created_at),
            INDEX idx_before_pending (ip_address, after_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "<h2 style='color:green'>✅ shave_snapshots table created successfully!</h2>";
    echo "<p>Table ready for before/after snapshot storage.</p>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ Migration failed</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
