<?php
/**
 * CDN Proxy for standalone shipping script
 * Usage: <script src="https://cdn.trustednutraproduct.com/s.js?v=DOMAIN_KEY"></script>
 *
 * Deploy this file to the CDN domain's docroot as "s.js.php" (same folder as t.js.php).
 * Then add this rewrite to .htaccess: RewriteRule ^s\.js$ s.js.php [L,QSA]
 */

// Include the real s.js.php from the shaver subdomain
$sjsPath = '/home/u373133718/domains/shaver.trustednutraproduct.com/public_html/cdn/s.js.php';

if (file_exists($sjsPath)) {
    include $sjsPath;
} else {
    header('Content-Type: application/javascript; charset=utf-8');
    echo '/* s.js: shipping config not found */';
    exit;
}
