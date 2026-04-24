<?php
/**
 * One-shot migration runner: 2026-04-07-shipping
 *
 * Visit this URL once in a browser to apply the per-domain shipping
 * schema. Idempotent — safe to visit multiple times: it checks for the
 * column / table before adding them.
 *
 * Delete this file after running, or restrict access via .htaccess.
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');

$pdo = getDB();
$results = [];

// 1. Add shipping_enabled column to `domains` (if missing)
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM `domains` LIKE 'shipping_enabled'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `domains`
                    ADD COLUMN `shipping_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
        $results[] = "[OK] Added column `domains.shipping_enabled`";
    } else {
        $results[] = "[SKIP] Column `domains.shipping_enabled` already exists";
    }
} catch (Exception $e) {
    $results[] = "[FAIL] Adding `domains.shipping_enabled`: " . $e->getMessage();
}

// 2. Create domain_shipping_config table (if missing)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `domain_shipping_config` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `domain_id` INT NOT NULL,
        `country_code` CHAR(2) NOT NULL COMMENT 'ISO-3166 alpha-2',
        `country_name` VARCHAR(80) NOT NULL,
        `ship_1_bottle` DECIMAL(7,2) NOT NULL DEFAULT 0 COMMENT '0 = FREE Shipping',
        `ship_2_bottle` DECIMAL(7,2) NOT NULL DEFAULT 0,
        `ship_3_bottle` DECIMAL(7,2) NOT NULL DEFAULT 0,
        `ship_6_bottle` DECIMAL(7,2) NOT NULL DEFAULT 0 COMMENT '6+1 bottle card',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_domain_country` (`domain_id`, `country_code`),
        FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = "[OK] Table `domain_shipping_config` ready";
} catch (Exception $e) {
    $results[] = "[FAIL] Creating `domain_shipping_config`: " . $e->getMessage();
}

// 3. Verify
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM `domains` LIKE 'shipping_enabled'");
    $colOk = $stmt->rowCount() > 0;
    $stmt = $pdo->query("SHOW TABLES LIKE 'domain_shipping_config'");
    $tableOk = $stmt->rowCount() > 0;
    $results[] = "";
    $results[] = "Verification:";
    $results[] = "  domains.shipping_enabled .... " . ($colOk ? 'OK' : 'MISSING');
    $results[] = "  domain_shipping_config ...... " . ($tableOk ? 'OK' : 'MISSING');
} catch (Exception $e) {
    $results[] = "[FAIL] Verification: " . $e->getMessage();
}

echo "Migration: 2026-04-07-shipping\n";
echo "==============================\n\n";
echo implode("\n", $results) . "\n\n";
echo "Done. You can now delete this file or block /migrations/ via .htaccess.\n";
