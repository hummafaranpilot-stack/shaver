<?php
/**
 * Migration: Create tracked_sources table
 * Stores registered traffic sources per domain for monitoring
 * Run once via browser, then delete this file.
 */
require_once 'config.php';

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$migrations = [
    "CREATE tracked_sources table" => "
        CREATE TABLE IF NOT EXISTS `tracked_sources` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `domain_id` INT NOT NULL,
            `source_url` VARCHAR(500) NOT NULL,
            `label` VARCHAR(255) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_domain_source` (`domain_id`, `source_url`(255)),
            KEY `idx_domain` (`domain_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "
];

echo "<h2>Migration: Tracked Sources</h2><pre>";
foreach ($migrations as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ $name\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "⏭️ $name (already exists)\n";
        } else {
            echo "❌ $name: " . $e->getMessage() . "\n";
        }
    }
}
echo "</pre><p style='color:green;font-weight:bold;'>Done! Delete this file now.</p>";
