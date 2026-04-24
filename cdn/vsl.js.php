<?php
/**
 * VSL Loader — CDN proxy that serves Vidalytics embed code from the database
 * Usage: <script src="https://cdn.trustednutraproduct.com/vsl.js?id=PAGE_ID"></script>
 *
 * Upload this file to: cdn.trustednutraproduct.com/vsl.js.php
 * Then add .htaccess rewrite: RewriteRule ^vsl\.js$ vsl.js.php [L,QSA]
 */

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');

$pageId = (int)($_GET['id'] ?? 0);

echo 'console.log("[VSL Loader] Starting, id=' . $pageId . '");' . "\n";

if (!$pageId) {
    echo 'console.error("[VSL Loader] No id provided");';
    exit;
}

// Connect to shaver DB directly (same server)
$shaverConfig = '/home/u373133718/domains/shaver.trustednutraproduct.com/public_html/config.php';

if (!file_exists($shaverConfig)) {
    echo 'console.error("[VSL Loader] Config file not found");';
    exit;
}

try {
    require_once $shaverConfig;
    $pdo = getDB();
    echo 'console.log("[VSL Loader] DB connected");' . "\n";
} catch (Exception $e) {
    echo 'console.error("[VSL Loader] DB error: ' . addslashes($e->getMessage()) . '");';
    exit;
}

$stmt = $pdo->prepare("SELECT embed_code FROM vsl_shuffle WHERE id = ? LIMIT 1");
$stmt->execute([$pageId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo 'console.error("[VSL Loader] No row found for id=' . $pageId . '");';
    exit;
}

if (empty($row['embed_code'])) {
    echo 'console.error("[VSL Loader] Row found but embed_code is empty");';
    exit;
}

$embedCode = $row['embed_code'];
$codeLen = strlen($embedCode);

echo 'console.log("[VSL Loader] Embed code loaded, length=' . $codeLen . ' chars");' . "\n";
echo 'console.log("[VSL Loader] Injecting via document.write...");' . "\n";

// Use document.write to inject the embed code directly into the page
echo 'document.write(' . json_encode($embedCode) . ');' . "\n";

echo 'console.log("[VSL Loader] document.write complete");' . "\n";
