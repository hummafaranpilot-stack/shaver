<?php
/**
 * Standalone Shipping CDN — lightweight, shipping-only script.
 *
 * Usage: <script src="https://cdn.trustednutraproduct.com/s.js?v=DOMAIN_KEY"></script>
 *
 * Unlike t.js (the full shaver tracker), this script serves ONLY the
 * shipping country selector + per-card override. No shaving, no affiliate
 * tracking, no cookie logic. ~5KB vs ~50KB.
 *
 * Use s.js when a domain only needs shipping config. When the domain
 * later gets full shaver tracking (t.js), remove s.js from the page —
 * t.js already includes the shipping bootstrap.
 */

header('Content-Type: application/javascript; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$domainKey = $_GET['v'] ?? '';
if (empty($domainKey)) {
    echo '/* s.js: no domain key — add ?v=YOUR_DOMAIN_KEY */';
    exit;
}

// Database + shared geo helpers
$shaverRoot = dirname(__DIR__);

// Resolve the correct path depending on whether we're called from CDN proxy
// or directly from the shaver subdomain.
$configPath = $shaverRoot . '/config.php';
if (!file_exists($configPath)) {
    // CDN proxy: the parent dir is the CDN docroot, config is on the shaver subdomain
    $configPath = '/home/u373133718/domains/shaver.trustednutraproduct.com/public_html/config.php';
}
if (!file_exists($configPath)) {
    echo '/* s.js: config not found */';
    exit;
}
require_once $configPath;

$geoPath = dirname($configPath) . '/lib/geo.php';
if (file_exists($geoPath)) {
    require_once $geoPath;
}

try {
    $pdo = getDB();

    // Look up domain
    $stmt = $pdo->prepare("SELECT id, shipping_enabled, card_config FROM domains WHERE domain_key = ? AND status = 'active'");
    $stmt->execute([$domainKey]);
    $domain = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$domain) {
        echo "/* s.js: domain not found or inactive: " . addslashes($domainKey) . " */";
        exit;
    }

    if (empty($domain['shipping_enabled']) || (int)$domain['shipping_enabled'] !== 1) {
        echo "/* s.js: shipping not enabled for this domain. Configure it in the Shipping tab. */";
        exit;
    }

    // Fetch shipping config rows
    $stmt = $pdo->prepare(
        "SELECT country_code, country_name,
                ship_1_bottle, ship_2_bottle, ship_3_bottle, ship_6_bottle
         FROM domain_shipping_config WHERE domain_id = ? ORDER BY country_name ASC"
    );
    $stmt->execute([$domain['id']]);
    $shippingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $needsCardDetection = empty($domain['card_config']);

    if (empty($shippingRows) && !$needsCardDetection) {
        // No rows AND cards already detected — nothing to do
        echo "/* s.js: no shipping rows configured. Add countries in the Shipping tab. */";
        exit;
    }

    // Server-side geo detection (skip if no rows — just detecting cards)
    $visitorGeo = null;
    if (!empty($shippingRows) && function_exists('tnp_detect_visitor_country')) {
        $visitorGeo = tnp_detect_visitor_country();
    }

} catch (Exception $e) {
    echo "/* s.js: database error */";
    exit;
}

// Output the shipping payload + bootstrap
echo "/* TNP Standalone Shipping - " . date('Y-m-d H:i:s') . " */\n";
?>
window.__TNP_SHIPPING__ = {
  domain_key: <?php echo json_encode($domainKey); ?>,
  rows: <?php echo json_encode($shippingRows); ?>,
  visitor: <?php echo json_encode($visitorGeo); ?>,
  cards_detected: <?php echo empty($domain['card_config']) ? 'false' : 'true'; ?>,
  api_url: 'https://shaver.trustednutraproduct.com/api.php'
};
<?php
// Inline the shipping bootstrap
$bootstrapPath = __DIR__ . '/shipping-bootstrap.js';
if (file_exists($bootstrapPath)) {
    echo file_get_contents($bootstrapPath);
} else {
    echo "/* s.js: shipping-bootstrap.js not found */";
}
