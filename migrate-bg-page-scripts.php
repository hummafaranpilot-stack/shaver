<?php
/**
 * Migration: Per-page BuyGoods tracking scripts
 *
 * Adds `domain_page_scripts` table so a single domain can define different
 * tracking payloads for landing vs upsell1/upsell2/upsell3 etc.
 *
 * Back-compat: old domains that have zero rows in this table continue
 * to use `domains.bg_product_codes` + landing-style iframe (current behavior).
 *
 * Visit this URL once to apply. Idempotent — safe to re-run.
 */

require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = getDB();
$results = [];

// 1. Create table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `domain_page_scripts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `domain_id` INT NOT NULL,
            `page_label` VARCHAR(100) DEFAULT '',
            `url_path_pattern` VARCHAR(255) NOT NULL,
            `bg_product_codes` VARCHAR(255) NOT NULL DEFAULT '',
            `is_upsell` TINYINT(1) NOT NULL DEFAULT 0,
            `bg_raw_script` TEXT DEFAULT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_domain` (`domain_id`),
            INDEX `idx_pattern` (`url_path_pattern`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $results[] = "[OK] Created/verified `domain_page_scripts` table";
} catch (Exception $e) {
    $results[] = "[FAIL] Create table: " . $e->getMessage();
}

// 2. Verify
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'domain_page_scripts'");
    $exists = $stmt->fetch() !== false;
    $results[] = "";
    $results[] = "Verification:";
    $results[] = "  Table exists .............. " . ($exists ? 'OK' : 'FAILED');

    if ($exists) {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM domain_page_scripts")->fetchColumn();
        $results[] = "  Current row count ......... " . $cnt;
    }
} catch (Exception $e) {
    $results[] = "[FAIL] Verification: " . $e->getMessage();
}

echo "Migration: bg-page-scripts\n";
echo "===========================\n\n";
echo implode("\n", $results) . "\n\n";
echo "Done.\n";
