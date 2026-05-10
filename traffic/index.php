<?php
/**
 * FB Traffic Detector — index.php
 * Domain registration UI + dashboard listing all registered domains.
 */
require_once __DIR__ . '/config.php';

$message = '';
$generated = null;
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $domains = traffic_load_domains();

    if ($action === 'register') {
        $label = trim($_POST['label'] ?? '');
        $url   = trim($_POST['domain_url'] ?? '');
        $pages = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $_POST['pages'] ?? ''))));

        if (!$label || !$url) {
            $message = '<div class="msg err">Label and Domain URL are required.</div>';
        } else {
            $key = traffic_generate_domain_key();
            $entry = [
                'key'        => $key,
                'label'      => $label,
                'domain_url' => $url,
                'pages'      => $pages,
                'status'     => 'active',
                'created_at' => date('c'),
            ];
            $domains[] = $entry;
            if (traffic_save_domains($domains)) {
                $generated = $entry;
                $message = '<div class="msg ok">Domain registered. Snippet generated below — copy and paste it on your pages.</div>';
            } else {
                $message = '<div class="msg err">Failed to save domain (file write error).</div>';
            }
        }
    } elseif ($action === 'edit') {
        $key = $_POST['key'] ?? '';
        foreach ($domains as &$d) {
            if (($d['key'] ?? '') === $key) {
                $d['label'] = trim($_POST['label'] ?? $d['label']);
                $d['domain_url'] = trim($_POST['domain_url'] ?? $d['domain_url']);
                $d['pages'] = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $_POST['pages'] ?? ''))));
                break;
            }
        }
        unset($d);
        traffic_save_domains($domains);
        $message = '<div class="msg ok">Domain updated.</div>';
    } elseif ($action === 'toggle') {
        $key = $_POST['key'] ?? '';
        foreach ($domains as &$d) {
            if (($d['key'] ?? '') === $key) {
                $d['status'] = ($d['status'] ?? 'active') === 'active' ? 'disabled' : 'active';
                break;
            }
        }
        unset($d);
        traffic_save_domains($domains);
        $message = '<div class="msg ok">Domain status updated.</div>';
    } elseif ($action === 'delete') {
        $key = $_POST['key'] ?? '';
        $domains = array_values(array_filter($domains, function ($d) use ($key) { return ($d['key'] ?? '') !== $key; }));
        traffic_save_domains($domains);
        $message = '<div class="msg ok">Domain deleted.</div>';
    }
}

$domains = traffic_load_domains();

// stats per domain
function domain_stats(string $key): array {
    $dir = TRAFFIC_LOGS_DIR . $key . '/';
    if (!is_dir($dir)) return ['count' => 0, 'last' => null];
    $files = @scandir($dir, SCANDIR_SORT_DESCENDING) ?: [];
    $files = array_values(array_filter($files, function ($f) { return substr($f, 0, 8) === 'visitor_'; }));
    $last = null;
    if (!empty($files)) {
        $mt = @filemtime($dir . $files[0]);
        if ($mt) $last = $mt;
    }
    return ['count' => count($files), 'last' => $last];
}

function rel_time(?int $ts): string {
    if (!$ts) return '—';
    $d = time() - $ts;
    if ($d < 60) return $d . 's ago';
    if ($d < 3600) return floor($d / 60) . 'm ago';
    if ($d < 86400) return floor($d / 3600) . 'h ago';
    return floor($d / 86400) . 'd ago';
}

$base = traffic_self_base();
$editKey = $_GET['edit'] ?? '';
$editing = null;
foreach ($domains as $d) if (($d['key'] ?? '') === $editKey) { $editing = $d; break; }
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>FB Traffic Detector — Domains</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
* { box-sizing: border-box; }
body { margin: 0; font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f6f8; color: #1f2328; }
.wrap { max-width: 1100px; margin: 0 auto; padding: 24px 20px 60px; }
h1 { margin: 0 0 4px; font-size: 22px; font-weight: 700; }
.sub { color: #57606a; margin-bottom: 24px; font-size: 13px; }
.card { background: #fff; border: 1px solid #d0d7de; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 0 rgba(0,0,0,.02); }
h2 { margin: 0 0 16px; font-size: 16px; font-weight: 600; }
label { display: block; margin: 12px 0 4px; font-weight: 500; font-size: 13px; color: #1f2328; }
input[type=text], input[type=url], textarea { width: 100%; padding: 8px 10px; border: 1px solid #d0d7de; border-radius: 6px; font: inherit; }
textarea { min-height: 80px; resize: vertical; font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; }
.help { color: #57606a; font-size: 12px; margin-top: 4px; }
button, .btn { display: inline-block; padding: 7px 14px; background: #1f883d; color: #fff; border: 1px solid rgba(0,0,0,.1); border-radius: 6px; font: inherit; font-weight: 500; cursor: pointer; text-decoration: none; }
button:hover, .btn:hover { background: #1a7333; }
.btn-secondary { background: #f6f8fa; color: #24292f; border-color: #d0d7de; }
.btn-secondary:hover { background: #eef0f3; }
.btn-danger { background: #cf222e; }
.btn-danger:hover { background: #a40e26; }
.btn-sm { padding: 4px 10px; font-size: 12px; }
.row-actions form { display: inline-block; margin: 0 4px 0 0; }
.msg { padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; }
.msg.ok { background: #dafbe1; border: 1px solid #1f883d; color: #1a7333; }
.msg.err { background: #ffebe9; border: 1px solid #cf222e; color: #82071e; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #eaeef2; }
th { background: #f6f8fa; font-weight: 600; font-size: 12px; color: #57606a; text-transform: uppercase; letter-spacing: .04em; }
tr:hover td { background: #fafbfc; }
.snippet { background: #0d1117; color: #d1d9e0; padding: 14px; border-radius: 6px; font: 12px/1.6 ui-monospace, Menlo, Consolas, monospace; white-space: pre-wrap; word-break: break-all; position: relative; }
.copy-btn { position: absolute; top: 8px; right: 8px; padding: 4px 10px; background: #1f6feb; color: #fff; border: none; border-radius: 4px; font: inherit; font-size: 11px; cursor: pointer; }
.copy-btn:hover { background: #388bfd; }
.tag { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; }
.tag.active { background: #dafbe1; color: #1a7333; }
.tag.disabled { background: #ffebe9; color: #82071e; }
.muted { color: #57606a; }
.empty { text-align: center; padding: 30px; color: #57606a; }
.kbd { font-family: ui-monospace, Menlo, monospace; font-size: 12px; background: #f6f8fa; padding: 1px 6px; border-radius: 3px; border: 1px solid #d0d7de; }
</style>
</head>
<body>
<div class="wrap">
  <h1>FB Traffic Detector</h1>
  <div class="sub">Register a domain → get a snippet → paste on your landing pages → fingerprint &amp; bot-detect every visit.</div>

  <?= $message ?>

  <?php if ($generated): ?>
    <div class="card">
      <h2>Snippet for <em><?= htmlspecialchars($generated['label']) ?></em></h2>
      <div class="snippet" id="generated">&lt;script src="<?= htmlspecialchars($base) ?>/snippet.js?d=<?= htmlspecialchars($generated['key']) ?>" async&gt;&lt;/script&gt;<button class="copy-btn" onclick="copySnippet()">Copy</button></div>
      <p class="help">Paste this in the <code>&lt;head&gt;</code> or before <code>&lt;/body&gt;</code> on every page you want tracked. Add <span class="kbd">?debug=1</span> to a lander URL to see a Copy-JSON button after 30 sec.</p>
    </div>
    <script>
    function copySnippet() {
      var t = '<script src="<?= htmlspecialchars($base) ?>/snippet.js?d=<?= htmlspecialchars($generated['key']) ?>" async><\/script>';
      navigator.clipboard.writeText(t).then(function () {
        var b = document.querySelector('.copy-btn'); if (b) { b.textContent = 'Copied'; setTimeout(function () { b.textContent = 'Copy'; }, 1500); }
      });
    }
    </script>
  <?php endif; ?>

  <div class="card">
    <h2><?= $editing ? 'Edit Domain' : 'Register a Domain' ?></h2>
    <form method="post">
      <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'register' ?>">
      <?php if ($editing): ?><input type="hidden" name="key" value="<?= htmlspecialchars($editing['key']) ?>"><?php endif; ?>
      <label>Label</label>
      <input type="text" name="label" required maxlength="100" placeholder="e.g. Meta Trim Lander 1" value="<?= htmlspecialchars($editing['label'] ?? '') ?>">
      <label>Domain URL</label>
      <input type="url" name="domain_url" required maxlength="255" placeholder="https://your-lander-domain.com" value="<?= htmlspecialchars($editing['domain_url'] ?? '') ?>">
      <label>Pages <span class="muted">(optional, one URL or path per line — informational only)</span></label>
      <textarea name="pages" placeholder="/landing-page&#10;/checkout&#10;https://other-domain.com/special"><?= htmlspecialchars(implode("\n", $editing['pages'] ?? [])) ?></textarea>
      <div style="margin-top:14px;">
        <button type="submit"><?= $editing ? 'Save Changes' : 'Generate Snippet' ?></button>
        <?php if ($editing): ?><a href="<?= htmlspecialchars($base) ?>/" class="btn btn-secondary">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>Registered Domains</h2>
    <?php if (empty($domains)): ?>
      <div class="empty">No domains registered yet — add one above to get started.</div>
    <?php else: ?>
      <table>
        <thead><tr>
          <th>Label</th><th>Domain</th><th>Status</th><th>Visits</th><th>Last</th><th>Snippet</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($domains as $d):
          $st = domain_stats($d['key']);
          $status = $d['status'] ?? 'active';
        ?>
          <tr>
            <td><strong><?= htmlspecialchars($d['label']) ?></strong><br><span class="muted" style="font-size:11px;">key: <?= htmlspecialchars($d['key']) ?></span></td>
            <td><a href="<?= htmlspecialchars($d['domain_url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($d['domain_url']) ?></a><?php if (!empty($d['pages'])): ?><br><span class="muted" style="font-size:11px;"><?= count($d['pages']) ?> page<?= count($d['pages']) == 1 ? '' : 's' ?></span><?php endif; ?></td>
            <td><span class="tag <?= $status ?>"><?= $status ?></span></td>
            <td><?= number_format($st['count']) ?></td>
            <td><?= rel_time($st['last']) ?></td>
            <td><button class="btn btn-secondary btn-sm" onclick="copyKey('<?= htmlspecialchars($d['key']) ?>')">Copy</button></td>
            <td class="row-actions">
              <a href="<?= htmlspecialchars($base) ?>/view.php?d=<?= htmlspecialchars($d['key']) ?>" class="btn btn-secondary btn-sm">Logs</a>
              <a href="<?= htmlspecialchars($base) ?>/?edit=<?= htmlspecialchars($d['key']) ?>" class="btn btn-secondary btn-sm">Edit</a>
              <form method="post" style="display:inline;"><input type="hidden" name="action" value="toggle"><input type="hidden" name="key" value="<?= htmlspecialchars($d['key']) ?>"><button class="btn btn-secondary btn-sm" type="submit"><?= $status === 'active' ? 'Disable' : 'Enable' ?></button></form>
              <form method="post" style="display:inline;" onsubmit="return confirm('Delete this domain? Logs will remain on disk but no new captures will be allowed.');"><input type="hidden" name="action" value="delete"><input type="hidden" name="key" value="<?= htmlspecialchars($d['key']) ?>"><button class="btn btn-danger btn-sm" type="submit">Delete</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
<script>
function copyKey(key) {
  var t = '<script src="<?= htmlspecialchars($base) ?>/snippet.js?d=' + key + '" async><\/script>';
  navigator.clipboard.writeText(t).then(function () { alert('Snippet copied to clipboard.'); });
}
</script>
</body>
</html>
