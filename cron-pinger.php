<?php
/**
 * Cron-Pinger
 *
 * Picks up to N un-pinged rows from affiliate_traffic (recent visits),
 * runs ICMP ping against each visitor IP, stores ping_ms and ping_checked_at.
 *
 * Trigger:
 *   - Server-side cron (every minute) — preferred. crontab entry:
 *       * * * * * curl -s 'https://shaver.trustednutraproduct.com/cron-pinger.php?key=shaver_ping_2026' > /dev/null 2>&1
 *   - Or piggybacked on the JS heartbeat that pages already fire
 *     (orders/analytics/etc.) — config.js calls this URL on page load if it
 *     hasn't run in the last 60 seconds.
 *
 * Auth: ?key=<KEY> (see CRON_KEY below). Don't expose this URL publicly.
 *
 * Output: plain-text summary so cron logs / browser visits show what happened.
 *
 * Tuning knobs:
 *   BATCH_SIZE     — rows processed per invocation (keeps each run short)
 *   PING_TIMEOUT_S — per-IP timeout in seconds
 *   ROW_AGE_HOURS  — how far back to look for un-pinged rows (avoids
 *                    pinging old, possibly-recycled IPs)
 */

require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

// ---- Config ---------------------------------------------------------------
const CRON_KEY       = 'shaver_ping_2026';
const BATCH_SIZE     = 50;
const PING_TIMEOUT_S = 1;     // 1 second per ping
const ROW_AGE_HOURS  = 6;     // only ping visits from last 6 hours
const NO_RESPONSE    = -1;    // sentinel for "ping timed out / no reply"

// ---- Auth -----------------------------------------------------------------
$key = $_GET['key'] ?? '';
if ($key !== CRON_KEY) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

// ---- Capability check (graceful skip if exec disabled) --------------------
$disabled = array_map('trim', explode(',', ini_get('disable_functions')));
$canExec  = function_exists('shell_exec') && !in_array('shell_exec', $disabled);
if (!$canExec) {
    echo "Cron-Pinger: exec() disabled — cannot ping. (No fallback yet.)\n";
    exit;
}

$pdo = getDB();

// ---- Pick rows to process -------------------------------------------------
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

// ---- De-dupe IPs within this batch (one ping per unique IP) ---------------
$ipToRowIds = [];
foreach ($rows as $r) {
    $ip = $r['ip_address'];
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        // Mark invalid IP rows as checked so we don't re-pick them
        $ipToRowIds['__invalid__'][] = (int)$r['id'];
        continue;
    }
    if (!isset($ipToRowIds[$ip])) $ipToRowIds[$ip] = [];
    $ipToRowIds[$ip][] = (int)$r['id'];
}

$invalidIds = $ipToRowIds['__invalid__'] ?? [];
unset($ipToRowIds['__invalid__']);

// ---- Ping each unique IP --------------------------------------------------
$updateStmt = $pdo->prepare("UPDATE affiliate_traffic SET ping_ms = ?, ping_checked_at = NOW() WHERE id = ?");

$startedAt = microtime(true);
$summary   = ['ok' => 0, 'no_response' => 0, 'total_ips' => count($ipToRowIds)];

foreach ($ipToRowIds as $ip => $ids) {
    $latency = pingHost($ip);
    foreach ($ids as $id) {
        $updateStmt->execute([$latency, $id]);
    }
    if ($latency === NO_RESPONSE) $summary['no_response']++;
    else                          $summary['ok']++;
}

// Mark invalid-IP rows as checked too (with NO_RESPONSE so they're filtered)
foreach ($invalidIds as $id) {
    $updateStmt->execute([NO_RESPONSE, $id]);
}

$elapsed = round(microtime(true) - $startedAt, 2);

echo "Cron-Pinger\n";
echo "============\n";
echo "Rows picked ............ " . count($rows) . "\n";
echo "Unique IPs pinged ...... " . $summary['total_ips'] . "\n";
echo "Responded .............. " . $summary['ok'] . "\n";
echo "No response ............ " . $summary['no_response'] . "\n";
echo "Invalid IPs ............ " . count($invalidIds) . "\n";
echo "Wall time .............. {$elapsed}s\n";

// ---- Helper ---------------------------------------------------------------
/**
 * Ping a single host. Returns latency in ms (int), or NO_RESPONSE (-1) on timeout.
 *
 * Linux: ping -c 1 -W 1
 * Output line we parse: "rtt min/avg/max/mdev = 0.xxx/<avg>/0.xxx/0.xxx ms"
 * Fallback: "time=<ms> ms"
 */
function pingHost(string $ip): int {
    $timeout = PING_TIMEOUT_S;
    $cmd = 'ping -c 1 -W ' . (int)$timeout . ' -q ' . escapeshellarg($ip) . ' 2>&1';
    $output = (string)@shell_exec($cmd);

    if ($output === '') return NO_RESPONSE;

    if (preg_match('#min/avg/max[^=]*=\s*[\d.]+/([\d.]+)/#', $output, $m)) {
        return (int)round((float)$m[1]);
    }
    if (preg_match('#time=([\d.]+)\s*ms#', $output, $m)) {
        return (int)round((float)$m[1]);
    }
    return NO_RESPONSE;
}
