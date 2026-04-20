<?php
/**
 * DIAGNOSTIC: sub_id pipeline check
 *
 * Checks in order:
 *  1. Is the updated check.php deployed? (searches for the fix signature)
 *  2. Last 10 affiliate_traffic rows — do any have sub_id populated?
 *  3. Last 10 traffic rows for vigorxpro specifically
 *
 * DELETE THIS FILE AFTER DIAGNOSING.
 */
require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<title>Sub ID Diagnostic</title>
<style>
  body { font-family: monospace; padding: 20px; background: #f5f5f5; }
  h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 5px; margin-top: 30px; }
  .ok { color: #27ae60; font-weight: bold; }
  .bad { color: #e74c3c; font-weight: bold; }
  .warn { color: #e67e22; font-weight: bold; }
  table { border-collapse: collapse; width: 100%; background: white; margin-top: 10px; }
  th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
  th { background: #34495e; color: white; }
  tr:nth-child(even) { background: #f9f9f9; }
  .subid-yes { background: #d4edda !important; }
  .subid-no  { background: #f8d7da !important; }
  pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; overflow-x: auto; }
</style>
</head>
<body>

<h1>Sub ID Diagnostic — <?php echo date('Y-m-d H:i:s'); ?></h1>

<h2>1. check.php — Deployment Status</h2>
<?php
$checkPath = __DIR__ . '/check.php';
if (!file_exists($checkPath)) {
    echo '<p class="bad">✘ check.php NOT FOUND on this server!</p>';
} else {
    $mtime = date('Y-m-d H:i:s', filemtime($checkPath));
    $size  = filesize($checkPath);
    $src   = file_get_contents($checkPath);

    // Look for the FIXED line:  logTraffic('', subId, false, null, utmSource);
    $hasFix = strpos($src, "logTraffic('', subId,") !== false;
    // Look for the OLD buggy line: logTraffic('', '', false, null, utmSource);
    $hasBug = strpos($src, "logTraffic('', '', false, null, utmSource)") !== false;

    echo '<p>File size: ' . number_format($size) . ' bytes</p>';
    echo '<p>Last modified: <strong>' . $mtime . '</strong></p>';
    if ($hasFix) {
        echo '<p class="ok">✔ Fix signature FOUND — check.php has the new code.</p>';
    } else {
        echo '<p class="bad">✘ Fix signature NOT FOUND — check.php is OLD.</p>';
    }
    if ($hasBug) {
        echo '<p class="bad">✘ Old buggy line still present: logTraffic(\'\', \'\', false, ...) — deployment incomplete.</p>';
    } else {
        echo '<p class="ok">✔ Old buggy line is gone.</p>';
    }
}
?>

<h2>2. analytics.html — UI Deployment Status</h2>
<?php
$analyticsPath = __DIR__ . '/analytics.html';
if (!file_exists($analyticsPath)) {
    echo '<p class="bad">✘ analytics.html NOT FOUND!</p>';
} else {
    $mtime = date('Y-m-d H:i:s', filemtime($analyticsPath));
    $src   = file_get_contents($analyticsPath);

    // New code: has "title=\"Sub ID\"" marker we added
    $hasNewUI = strpos($src, 'title="Sub ID"') !== false;
    $hasOldUI = strpos($src, "currentDomainPlatform === 'clickbank' && t.subId") !== false;

    echo '<p>Last modified: <strong>' . $mtime . '</strong></p>';
    if ($hasNewUI) echo '<p class="ok">✔ New UI code (3-line AFF ID) is deployed.</p>';
    else           echo '<p class="bad">✘ New UI code NOT present.</p>';
    if ($hasOldUI) echo '<p class="warn">⚠ Old UI block still present — deployment may be stale.</p>';
}
?>

<h2>3. Last 15 affiliate_traffic rows (any domain)</h2>
<?php
try {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT t.id, t.domain_id, d.name AS domain_name, t.aff_id, t.sub_id,
               t.page_url, t.timestamp, t.ip_address
        FROM affiliate_traffic t
        LEFT JOIN domains d ON d.id = t.domain_id
        ORDER BY t.id DESC
        LIMIT 15
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo '<p class="warn">No traffic rows found at all.</p>';
    } else {
        echo '<table><tr><th>ID</th><th>Domain</th><th>AFF_ID</th><th>SUB_ID</th><th>Page URL</th><th>IP</th><th>Timestamp</th></tr>';
        foreach ($rows as $r) {
            $hasSub = !empty($r['sub_id']);
            $cls = $hasSub ? 'subid-yes' : 'subid-no';
            echo '<tr class="' . $cls . '">';
            echo '<td>' . $r['id'] . '</td>';
            echo '<td>' . htmlspecialchars($r['domain_name'] ?? ('ID:' . $r['domain_id'])) . '</td>';
            echo '<td>' . htmlspecialchars($r['aff_id'] ?: '(empty)') . '</td>';
            echo '<td><strong>' . htmlspecialchars($r['sub_id'] ?: '(empty)') . '</strong></td>';
            echo '<td>' . htmlspecialchars(substr($r['page_url'] ?? '', 0, 80)) . '</td>';
            echo '<td>' . htmlspecialchars($r['ip_address'] ?? '') . '</td>';
            echo '<td>' . $r['timestamp'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '<p style="margin-top:10px;font-size:12px;color:#666;">Green row = sub_id present. Red row = sub_id empty.</p>';
    }
} catch (Exception $e) {
    echo '<p class="bad">DB error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>

<h2>4. Last 15 rows for vigorxpro domain (if present)</h2>
<?php
try {
    $stmt = $pdo->prepare("
        SELECT t.id, d.name AS domain_name, t.aff_id, t.sub_id,
               t.page_url, t.timestamp, t.ip_address, t.referrer
        FROM affiliate_traffic t
        JOIN domains d ON d.id = t.domain_id
        WHERE d.name LIKE '%vigorxpro%' OR d.domain_key LIKE '%vigorxpro%'
        ORDER BY t.id DESC
        LIMIT 15
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo '<p class="warn">No rows found for vigorxpro domain (domain name / key does not contain "vigorxpro").</p>';
    } else {
        echo '<table><tr><th>ID</th><th>Domain</th><th>AFF_ID</th><th>SUB_ID</th><th>Page URL</th><th>Referrer</th><th>IP</th><th>Timestamp</th></tr>';
        foreach ($rows as $r) {
            $hasSub = !empty($r['sub_id']);
            $cls = $hasSub ? 'subid-yes' : 'subid-no';
            echo '<tr class="' . $cls . '">';
            echo '<td>' . $r['id'] . '</td>';
            echo '<td>' . htmlspecialchars($r['domain_name']) . '</td>';
            echo '<td>' . htmlspecialchars($r['aff_id'] ?: '(empty)') . '</td>';
            echo '<td><strong>' . htmlspecialchars($r['sub_id'] ?: '(empty)') . '</strong></td>';
            echo '<td>' . htmlspecialchars(substr($r['page_url'] ?? '', 0, 80)) . '</td>';
            echo '<td>' . htmlspecialchars(substr($r['referrer'] ?? '', 0, 40)) . '</td>';
            echo '<td>' . htmlspecialchars($r['ip_address'] ?? '') . '</td>';
            echo '<td>' . $r['timestamp'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
} catch (Exception $e) {
    echo '<p class="bad">DB error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>

<h2>5. How vigorxpro loads the tracker</h2>
<?php
try {
    $stmt = $pdo->prepare("SELECT id, name, domain_key, platform, status FROM domains WHERE name LIKE '%vigorxpro%' OR domain_key LIKE '%vigorxpro%' LIMIT 5");
    $stmt->execute();
    $domains = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($domains)) {
        echo '<p class="warn">No domain found matching "vigorxpro" in the domains table.</p>';
    } else {
        echo '<table><tr><th>ID</th><th>Name</th><th>domain_key</th><th>Platform</th><th>Status</th><th>Expected tracker URL</th></tr>';
        foreach ($domains as $d) {
            echo '<tr>';
            echo '<td>' . $d['id'] . '</td>';
            echo '<td>' . htmlspecialchars($d['name']) . '</td>';
            echo '<td><code>' . htmlspecialchars($d['domain_key']) . '</code></td>';
            echo '<td>' . htmlspecialchars($d['platform']) . '</td>';
            echo '<td>' . htmlspecialchars($d['status']) . '</td>';
            echo '<td><a href="/check.php?v=' . urlencode($d['domain_key']) . '" target="_blank">/check.php?v=' . htmlspecialchars($d['domain_key']) . '</a></td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '<p style="margin-top:10px;font-size:12px;color:#666;">Click the tracker URL to view the deployed JS. Ctrl+F for <code>logTraffic(\'\', subId,</code> to confirm fix is live.</p>';
    }
} catch (Exception $e) {
    echo '<p class="bad">DB error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>

<hr>
<p style="color:#999;font-size:11px;">Delete this file (diagnose-subid.php) after diagnosis.</p>

</body>
</html>
