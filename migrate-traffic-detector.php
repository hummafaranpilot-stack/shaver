<?php
/**
 * Migration: FB Traffic Detector tables
 *   - traffic_domains : registered lander domains (independent of shaver `domains`)
 *   - traffic_visits  : per-visit fingerprint + verdict, with full JSON payload
 *
 * Visit once via browser. Idempotent. Delete file after success.
 */

require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = getDB();
$results = [];

function ensureTable(PDO $pdo, string $name, string $ddl, array &$results): void {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$name'");
        if ($stmt->fetch()) {
            $results[] = "[SKIP] table $name already exists";
        } else {
            $pdo->exec($ddl);
            $results[] = "[OK] Created table $name";
        }
    } catch (Exception $e) {
        $results[] = "[FAIL] $name: " . $e->getMessage();
    }
}

ensureTable($pdo, 'traffic_domains', "
CREATE TABLE `traffic_domains` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `domain_key` VARCHAR(64) NOT NULL UNIQUE COMMENT 'URL-safe identifier embedded in snippet src',
  `label` VARCHAR(255) NOT NULL COMMENT 'Display name e.g. Meta Trim Lander 1',
  `domain_url` VARCHAR(500) NOT NULL COMMENT 'Lander domain root e.g. https://my-lander.com',
  `pages` TEXT DEFAULT NULL COMMENT 'Newline-separated page paths, informational only',
  `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
", $results);

ensureTable($pdo, 'traffic_visits', "
CREATE TABLE `traffic_visits` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `traffic_domain_id` INT NOT NULL,
  `domain_key` VARCHAR(64) NOT NULL,
  `captured_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `country_code` VARCHAR(10) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT NULL,
  `city` VARCHAR(120) DEFAULT NULL,
  `region` VARCHAR(120) DEFAULT NULL,
  `isp` VARCHAR(255) DEFAULT NULL,
  `fraud_score` SMALLINT DEFAULT NULL,
  `is_proxy` TINYINT(1) DEFAULT 0,
  `is_vpn` TINYINT(1) DEFAULT 0,
  `is_tor` TINYINT(1) DEFAULT 0,
  `is_datacenter` TINYINT(1) DEFAULT 0,
  `is_mobile_ip` TINYINT(1) DEFAULT 0,
  `os_name` VARCHAR(50) DEFAULT NULL,
  `os_version` VARCHAR(50) DEFAULT NULL,
  `browser_name` VARCHAR(50) DEFAULT NULL,
  `browser_version` VARCHAR(20) DEFAULT NULL,
  `device_model` VARCHAR(120) DEFAULT NULL,
  `fbclid` VARCHAR(255) DEFAULT NULL,
  `utm_source` VARCHAR(120) DEFAULT NULL,
  `utm_medium` VARCHAR(120) DEFAULT NULL,
  `utm_campaign` VARCHAR(255) DEFAULT NULL,
  `utm_content` VARCHAR(255) DEFAULT NULL,
  `utm_term` VARCHAR(255) DEFAULT NULL,
  `referer_domain` VARCHAR(255) DEFAULT NULL,
  `is_facebook_referrer` TINYINT(1) DEFAULT 0,
  `time_on_page_s` DECIMAL(8,2) DEFAULT NULL,
  `max_scroll_depth_pct` SMALLINT DEFAULT NULL,
  `verdict` ENUM('PASS','SUSPICIOUS','FAIL') DEFAULT NULL,
  `verdict_label` VARCHAR(120) DEFAULT NULL,
  `risk_score` SMALLINT DEFAULT NULL,
  `points_earned` SMALLINT DEFAULT NULL,
  `points_possible` SMALLINT DEFAULT NULL,
  `checks_passed` SMALLINT DEFAULT NULL,
  `checks_total` SMALLINT DEFAULT NULL,
  `is_partial` TINYINT(1) DEFAULT 0,
  `full_data` JSON NOT NULL COMMENT 'Complete 9-section payload + verdict',
  KEY `idx_domain_captured` (`traffic_domain_id`, `captured_at`),
  KEY `idx_domain_key_captured` (`domain_key`, `captured_at`),
  KEY `idx_verdict` (`verdict`),
  KEY `idx_fbclid` (`fbclid`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_country` (`country_code`),
  KEY `idx_captured` (`captured_at`),
  CONSTRAINT `fk_traffic_visits_domain` FOREIGN KEY (`traffic_domain_id`) REFERENCES `traffic_domains`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
", $results);

// Verification
$results[] = "";
$results[] = "Verification:";
try {
    foreach (['traffic_domains', 'traffic_visits'] as $tbl) {
        $exists = $pdo->query("SHOW TABLES LIKE '$tbl'")->fetch();
        $results[] = "  $tbl ........... " . ($exists ? 'OK' : 'MISSING');
    }
} catch (Exception $e) {
    $results[] = "[FAIL] Verification: " . $e->getMessage();
}

echo "Migration: traffic-detector\n";
echo "===========================\n\n";
echo implode("\n", $results) . "\n\n";
echo "Done. You can delete this file after a successful run.\n";
