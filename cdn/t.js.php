<?php
/**
 * CDN Proxy — disguises shaver as a normal tracking tag
 * Usage: <script src="https://cdn.trustednutraproduct.com/t.js?v=DOMAIN_KEY"></script>
 */

// Get domain key from ?v= param
$domainKey = $_GET['v'] ?? '';
if (empty($domainKey)) {
    header('Content-Type: application/javascript; charset=utf-8');
    echo '/* tracking tag: no config */';
    exit;
}

// Load check.php from shaver with the domain key
$_GET['d'] = $domainKey;

// Override the script name so API URL builds correctly for this CDN domain
$_SERVER['SCRIPT_NAME'] = '/t.js';

// Buffer the output so we can clean "shaver" references
ob_start();

// Include the real check.php from the shaver subdomain
$shaverPath = '/home/u373133718/domains/shaver.trustednutraproduct.com/public_html/check.php';

if (file_exists($shaverPath)) {
    include $shaverPath;
} else {
    header('Content-Type: application/javascript; charset=utf-8');
    echo '/* tracking config not found */';
    exit;
}

$output = ob_get_clean();

// Replace "Shaver" branding with generic names in output
$output = str_replace('[Shaver]', '[T]', $output);
$output = str_replace('Multi-Tenant Shaver', 'Multi-Tenant Tracking', $output);
$output = str_replace('_shaver_debug', '_t_debug', $output);
$output = str_replace('_shaver_cleaned', '_t_cleaned', $output);
$output = str_replace('_shaver_aff_id', '_t_aid', $output);
$output = str_replace('_shaver_sub_id', '_t_sid', $output);
$output = str_replace('_shaver_cb_hop', '_t_cb_hop', $output);
$output = str_replace('_shaver_cb_hopId', '_t_cb_hid', $output);
$output = str_replace('_shaver_scrollY', '_t_scrollY', $output);
$output = str_replace('_shaver_pre_engagement', '_t_pre_eng', $output);
$output = str_replace('shaver.trustednutraproduct.com', 'cdn.trustednutraproduct.com', $output);
$output = str_replace('Shaver', 'Tracker', $output);
$output = str_replace('shaver', 'tracker', $output);

echo $output;
