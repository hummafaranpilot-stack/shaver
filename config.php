<?php
/**
 * Multi-Tenant Shaver - Configuration
 */

// ================================================================
// DATABASE CREDENTIALS (Update these for your hosting)
// ================================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'u373133718_shaver');
define('DB_USER', 'u373133718_shaver');
define('DB_PASS', 'Sadaqat547$$$');

// ================================================================
// IPQUALITYSCORE API (Fraud Detection - 35 req/day per key)
// ================================================================
define('IPQS_API_KEYS', [
    'tEViP4U22CdbrwKLreXxYfU7X3Ot66X9',
    '46hzjwtx9TllJaZWZrLv5a0ZnBC29Gnd'
]);
define('IPQS_DAILY_LIMIT', 70);

// ================================================================
// CLICKBANK API
// ================================================================
// Prefer runtime-overridable .cb_key file (written from the UI) so rotating
// the API key doesn't require redeploying config.php. Falls back to the
// hardcoded default if the file is missing or unreadable.
$_cbKeyFile = __DIR__ . '/.cb_key';
$_cbKeyDefault = 'API-YYIAC3IWOBI922NOMAEPS71RSJEMSYRRSVDO';
if (is_file($_cbKeyFile) && is_readable($_cbKeyFile)) {
    $_cbKeyValue = trim(@file_get_contents($_cbKeyFile));
    define('CB_API_KEY', $_cbKeyValue !== '' ? $_cbKeyValue : $_cbKeyDefault);
} else {
    define('CB_API_KEY', $_cbKeyDefault);
}
unset($_cbKeyFile, $_cbKeyDefault, $_cbKeyValue);
define('CB_ACCOUNT', 'tnproduct');

// ================================================================
// SMTP EMAIL (SendGrid)
// ================================================================
define('SMTP_HOST', 'smtp.sendgrid.net');
define('SMTP_PORT', 587);
define('SMTP_USER', 'apikey');
define('SMTP_PASS', trim(file_get_contents(__DIR__ . '/.smtp_key')));
define('SMTP_FROM_EMAIL', 'contact@trustednutraproduct.com');
define('SMTP_FROM_NAME', 'Trusted Nutra Products');

// ================================================================
// FACEBOOK CONVERSIONS API (CAPI)
// ================================================================
define('FB_PIXEL_ID',         '2461255981056616');
define('FB_ACCESS_TOKEN',     file_exists(__DIR__ . '/.fb_token') ? trim(file_get_contents(__DIR__ . '/.fb_token')) : '');
define('FB_TEST_EVENT_CODE',  ''); // empty = production mode (no test_event_code in CAPI payload)

// Whitelist by domain LABEL (case-insensitive). CAPI only fires for these.
// Match by label so adding a new branded domain doesn't require knowing its
// auto-increment ID — just add the label here.
define('FB_WEIGHTLOSS_DOMAINS', [
    'MetaTrim v2',
    'MetaTrim v3',
    'KetoWater',
    'KetoFlow',
]);

// ================================================================
// TIMEZONE
// ================================================================
date_default_timezone_set('Asia/Karachi');

// ================================================================
// ERROR REPORTING
// ================================================================
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ================================================================
// CORS HEADERS
// ================================================================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ================================================================
// DATABASE CONNECTION (Singleton)
// ================================================================
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            // Set MySQL session timezone to PKT so CURRENT_TIMESTAMP and date filters align
            $pdo->exec("SET time_zone = '+05:00'");
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'error' => 'Database connection failed']));
        }
    }
    return $pdo;
}
