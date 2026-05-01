<?php
/**
 * Diagnostic: can this server run `ping`?
 *
 * Tests three things in order:
 *   1. Is exec() / shell_exec() callable at all?
 *   2. Does the OS have a `ping` binary?
 *   3. Does pinging a known-good public IP (Google DNS 8.8.8.8) actually
 *      return a latency value?
 *
 * If all three pass, the ping feature will work. If any fail, the
 * cron-pinger will silently skip (won't crash) and we'll need a fallback.
 */

require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

echo "Ping Capability Diagnostic\n";
echo "===========================\n\n";

// 1. exec() availability
$disabled = explode(',', ini_get('disable_functions'));
$disabled = array_map('trim', $disabled);

$execEnabled  = function_exists('exec')       && !in_array('exec', $disabled);
$shellEnabled = function_exists('shell_exec') && !in_array('shell_exec', $disabled);

echo "1. PHP exec() functions:\n";
echo "   exec() ........... " . ($execEnabled  ? 'ENABLED' : 'DISABLED') . "\n";
echo "   shell_exec() ..... " . ($shellEnabled ? 'ENABLED' : 'DISABLED') . "\n";
echo "\n";

if (!$execEnabled && !$shellEnabled) {
    echo "[STOP] Both exec() and shell_exec() are disabled. Ping feature cannot work.\n";
    echo "Fallback options: third-party ping API, or fsockopen TCP probe (less accurate).\n";
    exit;
}

// 2. ping binary present?
echo "2. Looking for `ping` binary...\n";
$which = trim((string)@shell_exec('which ping 2>/dev/null'));
echo "   which ping ....... " . ($which !== '' ? $which : '(not found via which)') . "\n";

// Try common Linux paths if `which` failed
if ($which === '') {
    foreach (['/bin/ping', '/usr/bin/ping', '/sbin/ping'] as $candidate) {
        if (is_executable($candidate)) {
            $which = $candidate;
            echo "   found at ......... $which\n";
            break;
        }
    }
}
$pingBin = $which !== '' ? $which : 'ping';

echo "\n";

// 3. Actually ping
echo "3. Pinging 8.8.8.8 (Google DNS) with 1s timeout...\n";

$startedAt = microtime(true);
$cmd = escapeshellarg($pingBin) . ' -c 1 -W 1 -q 8.8.8.8 2>&1';
$output = (string)@shell_exec($cmd);
$elapsed = round((microtime(true) - $startedAt) * 1000);

echo "   command ........... $cmd\n";
echo "   wall time ......... {$elapsed}ms\n";
echo "   output:\n";
echo "   " . str_replace("\n", "\n   ", trim($output)) . "\n";
echo "\n";

// Parse the latency from output (typical format: "rtt min/avg/max/mdev = 0.xxx/0.xxx/0.xxx/0.xxx ms")
$latencyMs = null;
if (preg_match('#min/avg/max[^=]*=\s*[\d.]+/([\d.]+)/[\d.]+#', $output, $m)) {
    $latencyMs = (float)$m[1];
} elseif (preg_match('#time=([\d.]+)\s*ms#', $output, $m)) {
    $latencyMs = (float)$m[1];
}

echo "4. Result:\n";
if ($latencyMs !== null) {
    echo "   [OK] Ping works. Latency to 8.8.8.8 = " . round($latencyMs, 1) . " ms\n";
    echo "\n";
    echo "Verdict: GO — feature will work. Set up cron-pinger.php\n";
} else {
    echo "   [FAIL] Could not parse a latency value from ping output.\n";
    echo "   Possible causes: ping is allowed but ICMP outbound is firewalled,\n";
    echo "   or the binary returned an unexpected format.\n";
    echo "\n";
    echo "Verdict: NO-GO via ping. Use TCP fsockopen fallback.\n";
}
