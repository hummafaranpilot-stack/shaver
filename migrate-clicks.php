<?php
/**
 * Migration: Add click categorization columns + heatmap data
 * Run once in browser, then delete this file.
 */
require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $queries = [
        // Click categorization columns
        "ALTER TABLE affiliate_traffic ADD COLUMN redirect_clicks INT DEFAULT 0 AFTER total_clicks",
        "ALTER TABLE affiliate_traffic ADD COLUMN buynow_clicks INT DEFAULT 0 AFTER redirect_clicks",
        "ALTER TABLE affiliate_traffic ADD COLUMN footer_clicks INT DEFAULT 0 AFTER buynow_clicks",

        // Click positions for heatmap (JSON array of {x, y, pageX, pageY, type, pageWidth, pageHeight})
        "ALTER TABLE affiliate_traffic ADD COLUMN click_positions JSON DEFAULT NULL AFTER footer_clicks",

        // Time from page load to shave redirect (milliseconds)
        "ALTER TABLE affiliate_traffic ADD COLUMN redirect_time_ms INT DEFAULT NULL AFTER click_positions",

        // Pre-redirect engagement snapshot (JSON: {duration, scrollDepth, redirectClicks, buynowClicks, footerClicks, firstClickTime, totalClicks})
        "ALTER TABLE affiliate_traffic ADD COLUMN pre_redirect_engagement JSON DEFAULT NULL AFTER redirect_time_ms",
    ];

    $results = [];
    foreach ($queries as $sql) {
        try {
            $pdo->exec($sql);
            $results[] = "OK: " . substr($sql, 0, 80) . "...";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                $results[] = "SKIP (exists): " . substr($sql, 0, 60) . "...";
            } else {
                $results[] = "ERROR: " . $e->getMessage();
            }
        }
    }

    echo "<h2>Migration: Click Categorization & Heatmap</h2><pre>";
    foreach ($results as $r) echo $r . "\n";
    echo "\n✅ Done! Delete this file.</pre>";

} catch (PDOException $e) {
    echo "<h2>Migration Failed</h2><pre>ERROR: " . $e->getMessage() . "</pre>";
}
