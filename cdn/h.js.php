<?php
/**
 * CDN Proxy — header snippet loader
 *
 * Usage: <script src="https://cdn.trustednutraproduct.com/h.js?v=SNIPPET_KEY"></script>
 *
 * Forwards to shaver's header-output.php which serves the saved code
 * (HTML / JS / pixels) wrapped in a document.write() call.
 */

$shaverPath = '/home/u373133718/domains/shaver.trustednutraproduct.com/public_html/header-output.php';

if (!file_exists($shaverPath)) {
    header('Content-Type: application/javascript; charset=utf-8');
    echo "/* header-snippet: backend not found */\n";
    exit;
}

include $shaverPath;
