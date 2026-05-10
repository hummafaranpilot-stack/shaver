<?php
/**
 * FB Traffic Detector — config + helpers
 * Standalone tool, runs at /traffic on shaver.trustednutraproduct.com
 */

// ----- IPQS API keys (failover, copied from /config.php) -----
define('TRAFFIC_IPQS_KEYS', [
    'tEViP4U22CdbrwKLreXxYfU7X3Ot66X9',
    '46hzjwtx9TllJaZWZrLv5a0ZnBC29Gnd',
]);

// ----- Paths -----
define('TRAFFIC_DIR',      __DIR__);
define('TRAFFIC_LOGS_DIR', __DIR__ . '/logs/');
define('TRAFFIC_DOMAINS',  __DIR__ . '/domains.json');
define('TRAFFIC_ERR_LOG',  __DIR__ . '/logs/error.log');

// ----- Limits -----
define('TRAFFIC_MAX_LOG_FILES_PER_DOMAIN', 10000);
define('TRAFFIC_RATE_LIMIT_PER_HOUR',      100);
define('TRAFFIC_MAX_BODY_KB',              200);
define('TRAFFIC_IPQS_CACHE_TTL',           3600);   // 1h cache per IP
define('TRAFFIC_FBCLID_RECENT_SCAN',       1000);   // last N logs to dedup

// ----- Bootstrap -----
if (!is_dir(TRAFFIC_LOGS_DIR)) {
    @mkdir(TRAFFIC_LOGS_DIR, 0755, true);
}

// =====================================================================
// Domain registry
// =====================================================================
function traffic_load_domains(): array {
    if (!file_exists(TRAFFIC_DOMAINS)) return [];
    $raw = @file_get_contents(TRAFFIC_DOMAINS);
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function traffic_save_domains(array $domains): bool {
    $tmp = TRAFFIC_DOMAINS . '.tmp';
    $json = json_encode($domains, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return @rename($tmp, TRAFFIC_DOMAINS);
}

function traffic_find_domain(string $key): ?array {
    foreach (traffic_load_domains() as $d) {
        if (($d['key'] ?? '') === $key) return $d;
    }
    return null;
}

function traffic_generate_domain_key(): string {
    return bin2hex(random_bytes(6)); // 12-char hex
}

// =====================================================================
// IPQS lookup (with failover + per-IP cache)
// =====================================================================
function traffic_ipqs_lookup(string $ip): ?array {
    $cache = TRAFFIC_LOGS_DIR . '.ipqs_' . sha1($ip) . '.json';
    if (file_exists($cache) && (time() - filemtime($cache)) < TRAFFIC_IPQS_CACHE_TTL) {
        $cached = json_decode(@file_get_contents($cache), true);
        if (is_array($cached)) return $cached;
    }

    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        $local = ['success' => true, 'is_local' => true, 'country_code' => 'LOCAL'];
        @file_put_contents($cache, json_encode($local));
        return $local;
    }

    foreach (TRAFFIC_IPQS_KEYS as $key) {
        $url = 'https://www.ipqualityscore.com/api/json/ip/' . $key . '/' . urlencode($ip)
             . '?strictness=1&allow_public_access_points=true';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($err || $code !== 200 || !$body) {
            traffic_log_error("IPQS http=$code err=$err ip=$ip");
            continue;
        }
        $data = json_decode($body, true);
        if (is_array($data) && !empty($data['success'])) {
            @file_put_contents($cache, json_encode($data));
            return $data;
        }
    }
    return null;
}

// =====================================================================
// Misc helpers
// =====================================================================
function traffic_get_client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

function traffic_log_error(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    @file_put_contents(TRAFFIC_ERR_LOG, $line, FILE_APPEND | LOCK_EX);
}

function traffic_self_origin(): string {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'shaver.trustednutraproduct.com';
    return $proto . '://' . $host;
}

function traffic_self_base(): string {
    return traffic_self_origin() . '/traffic';
}
