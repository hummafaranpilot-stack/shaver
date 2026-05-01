<?php
/**
 * Cron-Pinger — measures server-to-IP round-trip latency.
 *
 * Hostinger shared hosting disables exec()/shell_exec(), so we cannot run
 * the `ping` binary. Instead we use a TCP-connect timing fallback:
 *
 *   - stream_socket_client() to tcp://IP:80 (or 443 if 80 fails)
 *   - Measure microseconds from connect() to either handshake-completion
 *     (open port) or RST-received (closed port — TCP kernel reply still
 *     gives us the same one-round-trip information)
 *   - If both ports time out (firewalled IP), record -1 ("no response")
 *
 * This is functionally equivalent to ICMP ping for proxy/VPN detection:
 * round-trip is determined by physical path length, regardless of whether
 * the response is SYN-ACK or RST.
 *
 * Trigger:
 *   - JS heartbeat in js/config.js fires every minute on admin page loads
 *   - Or set up real cron:
 *     * * * * * curl -s 'https://shaver.trustednutraproduct.com/cron-pinger.php?key=shaver_ping_2026' > /dev/null 2>&1
 *
 * Auth: ?key=<KEY>
 *
 * Tuning:
 *   BATCH_SIZE     — rows per invocation
 *   TIMEOUT_MS     — per-IP timeout in ms (1500 = 1.5s)
 *   ROW_AGE_HOURS  — how far back to look for un-pinged rows
 */

require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

// ---- Config ---------------------------------------------------------------
const CRON_KEY      = 'shaver_ping_2026';
const BATCH_SIZE    = 50;
const TIMEOUT_MS    = 1500;
const ROW_AGE_HOURS = 6;
const NO_RESPONSE   = -1;
const TCP_PORTS     = [80, 443]; // try in order

// ---- Auth -----------------------------------------------------------------
if (($_GET['key'] ?? '') !== CRON_KEY) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$pdo = getDB();

// ---- Capability detection -------------------------------------------------
$disabled    = array_map('trim', explode(',', ini_get('disable_functions')));
$canExec     = function_exists('shell_exec') && !in_array('shell_exec', $disabled);
$canFsockopen = function_exists('stream_socket_client') && !in_array('stream_socket_client', $disabled);

if (!$canExec && !$canFsockopen) {
    echo "Cron-Pinger: both exec and stream_socket_client are disabled — no way to measure latency.\n";
    exit;
}

$mode = $canExec ? 'icmp' : 'tcp';

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
    echo "Cron-Pinger ($mode): nothing to do (no pending rows in last " . ROW_AGE_HOURS . "h)\n";
    exit;
}

// ---- De-dupe by IP --------------------------------------------------------
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

// ---- Probe each unique IP -------------------------------------------------
$updateStmt = $pdo->prepare("UPDATE affiliate_traffic SET ping_ms = ?, ping_checked_at = NOW() WHERE id = ?");
$startedAt  = microtime(true);
$summary    = ['ok' => 0, 'no_response' => 0, 'total_ips' => count($ipToRowIds)];

foreach ($ipToRowIds as $ip => $ids) {
    $latency = $canExec ? pingHostIcmp($ip) : pingHostTcp($ip);
    foreach ($ids as $id) {
        $updateStmt->execute([$latency, $id]);
    }
    if ($latency === NO_RESPONSE) $summary['no_response']++;
    else                          $summary['ok']++;
}

foreach ($invalidIds as $id) {
    $updateStmt->execute([NO_RESPONSE, $id]);
}

$elapsed = round(microtime(true) - $startedAt, 2);

echo "Cron-Pinger\n";
echo "============\n";
echo "Mode ................... $mode " . ($mode === 'tcp' ? '(exec disabled — using TCP-connect timing)' : '(ICMP via ping binary)') . "\n";
echo "Rows picked ............ " . count($rows) . "\n";
echo "Unique IPs probed ...... " . $summary['total_ips'] . "\n";
echo "Got latency ............ " . $summary['ok'] . "\n";
echo "No response ............ " . $summary['no_response'] . "\n";
echo "Invalid IPs ............ " . count($invalidIds) . "\n";
echo "Wall time .............. {$elapsed}s\n";

// ---- Probe implementations ------------------------------------------------

/**
 * ICMP ping via the system `ping` binary. Used when exec() is allowed.
 */
function pingHostIcmp(string $ip): int {
    $cmd = 'ping -c 1 -W 1 -q ' . escapeshellarg($ip) . ' 2>&1';
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

/**
 * TCP-connect timing fallback. Returns ms to either successful TCP handshake
 * (open port) or to ECONNREFUSED (closed port — kernel still replies in one
 * round-trip, which is what we want to measure).
 *
 * If both ports time out, the IP is firewalled (drops SYNs silently) and we
 * have no usable measurement → return NO_RESPONSE.
 */
function pingHostTcp(string $ip): int {
    $best = NO_RESPONSE;
    foreach (TCP_PORTS as $port) {
        $start  = microtime(true);
        $errno  = 0;
        $errstr = '';
        $sock   = @stream_socket_client(
            "tcp://{$ip}:{$port}",
            $errno,
            $errstr,
            TIMEOUT_MS / 1000.0,
            STREAM_CLIENT_CONNECT
        );
        $elapsedMs = (int)round((microtime(true) - $start) * 1000);

        if ($sock) {
            // Port is open — handshake completed, this IS our round-trip.
            @fclose($sock);
            return $elapsedMs;
        }

        // Port closed but kernel returned RST: errno is set, elapsed is small.
        // If we hit the timeout boundary, it's a silent drop (firewalled), skip.
        if ($errno !== 0 && $elapsedMs < (int)(TIMEOUT_MS * 0.85)) {
            if ($best === NO_RESPONSE || $elapsedMs < $best) {
                $best = $elapsedMs;
            }
        }
        // If timeout: try next port
    }
    return $best;
}
