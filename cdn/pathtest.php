<?php
// Temporary file to find correct paths — DELETE after testing
echo "<pre>";
echo "CDN __DIR__: " . __DIR__ . "\n";
echo "CDN parent:  " . dirname(__DIR__) . "\n\n";

// Check common Hostinger paths
$checks = [
    __DIR__ . '/../check.php',
    dirname(__DIR__) . '/check.php',
    '/home/' . get_current_user() . '/domains/shaver.trustednutraproduct.com/public_html/check.php',
    '/home/' . get_current_user() . '/public_html/shaver/check.php',
];

foreach ($checks as $path) {
    echo $path . " => " . (file_exists($path) ? "EXISTS ✓" : "NOT FOUND") . "\n";
}

// Also try glob
$glob = glob('/home/*/domains/shaver.trustednutraproduct.com/public_html/check.php');
echo "\nGlob results:\n";
foreach ($glob as $g) echo "  " . $g . "\n";
if (empty($glob)) echo "  (none found)\n";

echo "</pre>";
