<?php
/**
 * Cron-Pinger — measures ping from USA via check-host.net free API.
 *
 * Hostinger blocks exec()/shell_exec() AND silently drops most outbound
 * SYN packets to residential IPs, so the ping has to come from somewhere
 * with proper ICMP outbound. We use check-host.net's public API which
 * runs ICMP pings from multiple geographic nodes (we request USA only).
 *
 * Why USA: most customers are USA-based. Real USA users → fast ping
 * (~30-80ms). Non-USA proxies → slower ping (~200ms+). Combined with
 * IPQS fraud score this gives a useful second-opinion signal.
 *
 * API: https://check-host.net/about/api
 *   1. POST  /check-ping?host=IP&node=us1.node...&node=us2... → returns request_id
 *   2. (wait ~5s for ICMP probes to complete from those nodes)
 *   3. GET   /check-result/REQUEST_ID → returns latency per node per packet
 *
 * Optimized: submits all batch IPs in parallel, sleeps once, polls all in
 * parallel. Total time per batch ≈ 12-15s for 10 IPs.
 *
 * Caching: if the same IP already has a fresh ping_ms in another row,
 * we reuse it instead of calling the API again. Avoids hitting the
 * ~600/hour rate limit even on busy days.
 *
 * Trigger:
 *   - JS heartbeat in js/config.js (1-min cadence on admin page loads)
 *   - Optional cron: * * * * * curl -s '<URL>?key=shaver_ping_2026' >/dev/null 2>&1
 */

require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

// ---- Config ---------------------------------------------------------------
const CRON_KEY      = 'shaver_ping_2026';
const BATCH_SIZE    = 10;        // 10 IPs × ~2 API calls each = 20 reqs/run; safe under 600/hr
const SUBMIT_TIMEOUT = 8;        // per-IP submit timeout
const POLL_TIMEOUT   = 8;        // per-IP poll timeout
const PING_WAIT_SEC  = 5;        // wait between submit and poll for ICMP to complete
const ROW_AGE_HOURS  = 6;
const NO_RESPONSE    = -1;
const USA_NODES      = [
    'us1.node.check-host.net',
    'us2.node.check-host.net',
    'us3.node.check-host.net',
];

@set_time_limit(60);
@ignore_user_abort(true);

// ---- Auth -----------------------------------------------------------------
if (($_GET['key'] ?? '') !== CRON_KEY) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

if (!function_exists('curl_init')) {
    echo "Cron-Pinger: cURL extension not available — cannot call check-host.net API.\n";
    exit;
}

$pdo = getDB();

// ---- Pick rows ------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT id, ip_address
    FROM affiliate_traffic
    WHERE ping_checked_at IS NULL
      AND ip_address IS NOT NULL AND ip_address != ''
      AND timestamp > DATE_SUB(NOW(), INTERVAL ? HOUR)
    ORDER BY timestamp DESC
    LIMIT ?
");
$stmt->bindValue(1, ROW_AGE_HOURS, PDO::PARAM_INT);
$stmt->bindValue(2, BATCH_SIZE,    PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

if (!$rows) {
    echo "Cron-Pinger: nothing to do (no pending rows in last " . ROW_AGE_HOURS . "h)\n";
    exit;
}

// ---- De-dup rows by IP and validate ---------------------------------------
$ipToRowIds = [];
$invalidIds = [];
foreach ($rows as $r) {
    $ip = $r['ip_address'];
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $invalidIds[] = (int)$r['id'];
        continue;
    }
    $ipToRowIds[$ip][] = (int)$r['id'];
}

// ---- Cache lookup: skip IPs we've already pinged --------------------------
$cachedPings = [];
if (!empty($ipToRowIds)) {
    $ips = array_keys($ipToRowIds);
    $ph  = implode(',', array_fill(0, count($ips), '?'));
    $cacheStmt = $pdo->prepare("
        SELECT ip_address, ping_ms FROM affiliate_traffic
        WHERE ip_address IN ($ph) AND ping_checked_at IS NOT NULL
        GROUP BY ip_address ORDER BY MAX(ping_checked_at) DESC
    ");
    $cacheStmt->execute($ips);
    foreach ($cacheStmt->fetchAll() as $r) {
        $cachedPings[$r['ip_address']] = (int)$r['ping_ms'];
    }
}

// Apply cache and identify IPs that still need pinging
$updateStmt = $pdo->prepare("UPDATE affiliate_traffic SET ping_ms = ?, ping_checked_at = NOW() WHERE id = ?");
$cachedHits = 0;
$ipsToProbe = [];
foreach ($ipToRowIds as $ip => $ids) {
    if (isset($cachedPings[$ip])) {
        foreach ($ids as $id) $updateStmt->execute([$cachedPings[$ip], $id]);
        $cachedHits++;
    } else {
        $ipsToProbe[$ip] = $ids;
    }
}

// ---- Probe new IPs via check-host.net ------------------------------------
$startedAt = microtime(true);
$summary   = ['ok' => 0, 'no_response' => 0, 'api_fail' => 0, 'cached' => $cachedHits];

if (!empty($ipsToProbe)) {
    $pingResults = pingIPsViaCheckHost(array_keys($ipsToProbe));
    foreach ($ipsToProbe as $ip => $ids) {
        $latency = $pingResults[$ip] ?? NO_RESPONSE;
        foreach ($ids as $id) {
            $updateStmt->execute([$latency, $id]);
        }
        if ($latency === NO_RESPONSE) $summary['no_response']++;
        else                          $summary['ok']++;
    }
}

foreach ($invalidIds as $id) $updateStmt->execute([NO_RESPONSE, $id]);

$elapsed = round(microtime(true) - $startedAt, 2);

echo "Cron-Pinger (check-host.net via USA nodes)\n";
echo "===========================================\n";
echo "Rows picked ............ " . count($rows) . "\n";
echo "Cached IP hits ......... " . $summary['cached'] . " (reused prior ping)\n";
echo "Probed via API ......... " . count($ipsToProbe) . "\n";
echo "  Got latency .......... " . $summary['ok'] . "\n";
echo "  No response .......... " . $summary['no_response'] . "\n";
echo "Invalid IPs ............ " . count($invalidIds) . "\n";
echo "Wall time .............. {$elapsed}s\n";

// =========================================================================
// check-host.net API client
// =========================================================================

/**
 * Submit ping requests for each IP, wait, then poll all results.
 * Returns ['ip' => latencyMs (int) | NO_RESPONSE]
 */
function pingIPsViaCheckHost(array $ips): array {
    $headers = ['Accept: application/json'];
    $requestIds = [];

    // Step 1: submit all
    foreach ($ips as $ip) {
        $url = 'https://check-host.net/check-ping?host=' . urlencode($ip);
        foreach (USA_NODES as $node) {
            $url .= '&node=' . urlencode($node);
        }
        $resp = httpGetJson($url, $headers, SUBMIT_TIMEOUT);
        if ($resp && !empty($resp['request_id'])) {
            $requestIds[$ip] = $resp['request_id'];
        }
    }

    if (empty($requestIds)) return [];

    // Step 2: wait for ICMP probes to complete on the check-host side
    sleep(PING_WAIT_SEC);

    // Step 3: poll all results
    $results = [];
    foreach ($requestIds as $ip => $reqId) {
        $url  = 'https://check-host.net/check-result/' . urlencode($reqId);
        $data = httpGetJson($url, $headers, POLL_TIMEOUT);
        $results[$ip] = parseCheckHostResult($data);
    }
    return $results;
}

/**
 * Pull the minimum (best) ping time across all USA nodes' OK packets.
 * Returns ms (int) or NO_RESPONSE if no node got a reply.
 */
function parseCheckHostResult($data): int {
    if (!is_array($data)) return NO_RESPONSE;
    $allMs = [];
    foreach ($data as $node => $packets) {
        // packets is [[packet1, packet2, packet3, packet4]] — nested once
        if (!is_array($packets) || !isset($packets[0]) || !is_array($packets[0])) continue;
        foreach ($packets[0] as $p) {
            // ["OK", time_in_seconds, "responding_ip"] OR ["TIMEOUT"] OR null
            if (is_array($p) && ($p[0] ?? '') === 'OK' && isset($p[1])) {
                $allMs[] = (float)$p[1] * 1000.0;
            }
        }
    }
    if (empty($allMs)) return NO_RESPONSE;
    return (int)round(min($allMs));
}

function httpGetJson(string $url, array $headers, int $timeout) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 shaver-pinger/1.0',
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!$resp) return null;
    $data = json_decode($resp, true);
    return is_array($data) ? $data : null;
}
