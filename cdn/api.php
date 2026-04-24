<?php
/**
 * CDN API Proxy — forwards API calls to shaver's api.php
 * The JS from t.js will call this domain's /api.php automatically
 */

$shaverPath = '/home/u373133718/domains/shaver.trustednutraproduct.com/public_html/api.php';

if (file_exists($shaverPath)) {
    include $shaverPath;
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'API not available']);
}
