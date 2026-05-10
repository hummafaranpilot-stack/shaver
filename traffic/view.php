<?php
/**
 * FB Traffic Detector — view.php
 * Per-domain visitor log viewer.
 *   /traffic/view.php?d=DOMAIN_KEY
 *   /traffic/view.php?d=DOMAIN_KEY&f=FILE.json (single visitor JSON view)
 */
require_once __DIR__ . '/config.php';

$domainKey = preg_replace('/[^a-z0-9]/i', '', $_GET['d'] ?? '');
if (!$domainKey) { header('Location: ' . traffic_self_base() . '/'); exit; }

$domain = traffic_find_domain($domainKey);
if (!$domain) {
    http_response_code(404);
    echo '<p>Unknown domain key. <a href="' . htmlspecialchars(traffic_self_base()) . '/">Back</a></p>';
    exit;
}

$dir = TRAFFIC_LOGS_DIR . $domainKey . '/';
$base = traffic_self_base();

// ----- single-visitor JSON detail view -----
if (!empty($_GET['f'])) {
    $f = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $_GET['f']);
    $path = $dir . $f;
    if (!is_file($path) || strpos(realpath($path), realpath($dir)) !== 0) {
        http_response_code(404); echo 'Not found'; exit;
    }
    $json = @file_get_contents($path);
    header('Content-Type: application/json');
    echo $json;
    exit;
}

// ----- list view -----
$files = is_dir($dir) ? (@scandir($dir, SCANDIR_SORT_DESCENDING) ?: []) : [];
$files = array_values(array_filter($files, function ($f) { return substr($f, 0, 8) === 'visitor_'; }));

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$total   = count($files);
$pages   = max(1, (int)ceil($total / $perPage));
$slice   = array_slice($files, ($page - 1) * $perPage, $perPage);

$filter  = $_GET['v'] ?? ''; // verdict filter

// load summaries
$rows = [];
foreach ($slice as $f) {
    $raw = @file_get_contents($dir . $f, false, null, 0, 16384);
    if (!$raw) continue;
    $obj = @json_decode($raw, true);
    if (!is_array($obj)) continue;
    $verdict = $obj['verdict'] ?? [];
    if ($filter && ($verdict['result'] ?? '') !== $filter) continue;
    $rows[] = [
        'file'    => $f,
        'time'    => $obj['meta']['captured_at'] ?? '',
        'ts'      => $obj['meta']['captured_at_ts'] ?? null,
        'ip'      => $obj['meta']['ip'] ?? '',
        'country' => $obj['section4_ip_info']['country_code'] ?? '',
        'city'    => $obj['section4_ip_info']['city'] ?? '',
        'fraud'   => $obj['section4_ip_info']['fraud_score'] ?? '',
        'fbclid'  => $obj['section1_url_params']['fbclid'] ?? '',
        'utm_src' => $obj['section1_url_params']['utm_source'] ?? '',
        'os'      => $obj['section3_ua_decode']['os_name'] ?? '',
        'osv'     => $obj['section3_ua_decode']['os_version'] ?? '',
        'browser' => $obj['section3_ua_decode']['browser_name'] ?? '',
        'device'  => $obj['section3_ua_decode']['device_model'] ?? '',
        'tos'     => $obj['section7_behavioral']['time_on_page_s'] ?? '',
        'verdict' => $verdict['result'] ?? '',
        'label'   => $verdict['label'] ?? '',
        'score'   => $verdict['points_earned'] ?? '',
        'risk'    => $verdict['risk_score'] ?? '',
        'partial' => !empty($obj['meta']['partial']),
    ];
}

function rel_time(?int $ts): string {
    if (!$ts) return '—';
    $d = time() - $ts;
    if ($d < 60) return $d . 's ago';
    if ($d < 3600) return floor($d / 60) . 'm ago';
    if ($d < 86400) return floor($d / 3600) . 'h ago';
    return floor($d / 86400) . 'd ago';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Logs — <?= htmlspecialchars($domain['label']) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
* { box-sizing: border-box; }
body { margin: 0; font: 13px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f6f8; color: #1f2328; }
.wrap { max-width: 1400px; margin: 0 auto; padding: 20px; }
.head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
h1 { margin: 0; font-size: 20px; font-weight: 700; }
.crumb { font-size: 12px; color: #57606a; margin-bottom: 4px; }
.crumb a { color: #0969da; text-decoration: none; }
.card { background: #fff; border: 1px solid #d0d7de; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px; }
.toolbar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.btn { display: inline-block; padding: 5px 12px; background: #f6f8fa; color: #24292f; border: 1px solid #d0d7de; border-radius: 6px; font: inherit; font-size: 12px; cursor: pointer; text-decoration: none; }
.btn:hover { background: #eef0f3; }
.btn.active { background: #0969da; color: #fff; border-color: #0969da; }
.btn-primary { background: #1f883d; color: #fff; border-color: rgba(0,0,0,.1); }
.btn-primary:hover { background: #1a7333; }
table { width: 100%; border-collapse: collapse; font-size: 12px; background: #fff; }
th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eaeef2; vertical-align: top; }
th { background: #f6f8fa; font-weight: 600; font-size: 11px; color: #57606a; text-transform: uppercase; letter-spacing: .04em; position: sticky; top: 0; }
tr.row { cursor: pointer; }
tr.row:hover td { background: #fafbfc; }
.tag { display: inline-block; padding: 1px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; }
.tag.PASS { background: #dafbe1; color: #1a7333; }
.tag.SUSPICIOUS { background: #fff8c5; color: #7a5c00; }
.tag.FAIL { background: #ffebe9; color: #82071e; }
.tag.partial { background: #ddf4ff; color: #0550ae; margin-left: 6px; }
.muted { color: #57606a; }
.mono { font-family: ui-monospace, Menlo, Consolas, monospace; }
.detail-overlay { position: fixed; inset: 0; background: rgba(15,20,25,.6); z-index: 100; display: none; }
.detail-overlay.show { display: flex; align-items: flex-start; justify-content: center; padding: 30px 20px; overflow: auto; }
.detail-box { background: #fff; max-width: 1000px; width: 100%; border-radius: 8px; padding: 20px; }
.detail-box pre { background: #0d1117; color: #d1d9e0; padding: 14px; border-radius: 6px; font-size: 11px; line-height: 1.5; max-height: 70vh; overflow: auto; white-space: pre-wrap; word-break: break-all; }
.detail-box .x { float: right; cursor: pointer; font-size: 22px; color: #57606a; line-height: 1; }
.empty { text-align: center; padding: 40px; color: #57606a; background: #fff; border: 1px solid #d0d7de; border-radius: 8px; }
.pager { margin-top: 16px; display: flex; gap: 8px; align-items: center; }
.fbclid-cell { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: ui-monospace, Menlo, monospace; font-size: 11px; }
.score-bar { display: inline-block; width: 50px; height: 6px; background: #eaeef2; border-radius: 3px; overflow: hidden; vertical-align: middle; margin-right: 6px; }
.score-bar > span { display: block; height: 100%; background: #1a7333; }
</style>
</head>
<body>
<div class="wrap">
  <div class="crumb"><a href="<?= htmlspecialchars($base) ?>/">← All domains</a></div>
  <div class="head">
    <div>
      <h1><?= htmlspecialchars($domain['label']) ?></h1>
      <div class="muted"><?= htmlspecialchars($domain['domain_url']) ?> &middot; <span class="mono">key: <?= htmlspecialchars($domainKey) ?></span> &middot; <?= number_format($total) ?> total visit<?= $total == 1 ? '' : 's' ?></div>
    </div>
  </div>

  <div class="card">
    <div class="toolbar">
      <a href="?d=<?= htmlspecialchars($domainKey) ?>" class="btn <?= !$filter ? 'active' : '' ?>">All</a>
      <a href="?d=<?= htmlspecialchars($domainKey) ?>&v=PASS" class="btn <?= $filter === 'PASS' ? 'active' : '' ?>">PASS</a>
      <a href="?d=<?= htmlspecialchars($domainKey) ?>&v=SUSPICIOUS" class="btn <?= $filter === 'SUSPICIOUS' ? 'active' : '' ?>">SUSPICIOUS</a>
      <a href="?d=<?= htmlspecialchars($domainKey) ?>&v=FAIL" class="btn <?= $filter === 'FAIL' ? 'active' : '' ?>">FAIL</a>
    </div>
  </div>

  <?php if (empty($rows)): ?>
    <div class="empty">No visitors captured yet for this domain.</div>
  <?php else: ?>
    <table>
      <thead><tr>
        <th>Time</th><th>Verdict</th><th>Score</th><th>IP / Geo</th><th>UA</th><th>Source</th><th>fbclid</th><th>TOS</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="row" onclick="openDetail('<?= htmlspecialchars($r['file']) ?>')">
          <td><span class="muted"><?= rel_time($r['ts']) ?></span><br><span style="font-size:11px;color:#57606a;"><?= htmlspecialchars(substr($r['time'], 0, 19)) ?></span></td>
          <td>
            <span class="tag <?= htmlspecialchars($r['verdict']) ?>"><?= htmlspecialchars($r['verdict'] ?: '—') ?></span>
            <?php if ($r['partial']): ?><span class="tag partial">partial</span><?php endif; ?>
            <?php if ($r['label']): ?><br><span style="font-size:11px;color:#57606a;"><?= htmlspecialchars($r['label']) ?></span><?php endif; ?>
          </td>
          <td>
            <?php if (is_numeric($r['score'])): $pct = (int)round(($r['score'] / 125) * 100); ?>
              <span class="score-bar"><span style="width:<?= $pct ?>%;"></span></span>
              <span class="mono"><?= htmlspecialchars($r['score']) ?>/125</span><br>
              <span class="muted" style="font-size:11px;">risk <?= htmlspecialchars($r['risk']) ?></span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <span class="mono"><?= htmlspecialchars($r['ip']) ?></span><br>
            <span class="muted"><?= htmlspecialchars($r['country']) ?><?= $r['city'] ? ' · ' . htmlspecialchars($r['city']) : '' ?></span>
            <?php if (is_numeric($r['fraud'])): ?><br><span class="muted" style="font-size:11px;">fraud <?= htmlspecialchars($r['fraud']) ?></span><?php endif; ?>
          </td>
          <td>
            <?= htmlspecialchars($r['os']) ?> <?= htmlspecialchars($r['osv']) ?><br>
            <span class="muted"><?= htmlspecialchars($r['browser']) ?></span><?= $r['device'] ? '<br><span class="muted" style="font-size:11px;">' . htmlspecialchars($r['device']) . '</span>' : '' ?>
          </td>
          <td>
            <?= htmlspecialchars($r['utm_src']) ?: '<span class="muted">—</span>' ?>
          </td>
          <td class="fbclid-cell" title="<?= htmlspecialchars($r['fbclid']) ?>"><?= htmlspecialchars($r['fbclid']) ?: '<span class="muted">—</span>' ?></td>
          <td><?= htmlspecialchars($r['tos']) ?>s</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($pages > 1): ?>
    <div class="pager">
      <?php if ($page > 1): ?><a class="btn" href="?d=<?= htmlspecialchars($domainKey) ?>&page=<?= $page - 1 ?><?= $filter ? '&v=' . htmlspecialchars($filter) : '' ?>">← Prev</a><?php endif; ?>
      <span class="muted">Page <?= $page ?> / <?= $pages ?></span>
      <?php if ($page < $pages): ?><a class="btn" href="?d=<?= htmlspecialchars($domainKey) ?>&page=<?= $page + 1 ?><?= $filter ? '&v=' . htmlspecialchars($filter) : '' ?>">Next →</a><?php endif; ?>
    </div>
  <?php endif; ?>

</div>

<div class="detail-overlay" id="detail" onclick="if(event.target===this)closeDetail()">
  <div class="detail-box">
    <span class="x" onclick="closeDetail()">×</span>
    <h2 id="detail-title" style="margin:0 0 12px;">Visitor</h2>
    <div style="margin-bottom:10px;"><button class="btn btn-primary" onclick="copyDetail()">Copy JSON</button></div>
    <pre id="detail-json">Loading…</pre>
  </div>
</div>

<script>
var DETAIL_RAW = '';
function openDetail(f) {
  var box = document.getElementById('detail');
  var pre = document.getElementById('detail-json');
  document.getElementById('detail-title').textContent = f;
  pre.textContent = 'Loading…';
  box.classList.add('show');
  fetch('?d=<?= htmlspecialchars($domainKey) ?>&f=' + encodeURIComponent(f)).then(function (r) { return r.text(); }).then(function (t) {
    DETAIL_RAW = t;
    try { pre.textContent = JSON.stringify(JSON.parse(t), null, 2); }
    catch (e) { pre.textContent = t; }
  });
}
function closeDetail() { document.getElementById('detail').classList.remove('show'); }
function copyDetail() {
  navigator.clipboard.writeText(DETAIL_RAW).then(function () { alert('Copied'); });
}
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeDetail(); });
</script>
</body>
</html>
