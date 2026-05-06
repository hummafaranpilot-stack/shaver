<?php
/**
 * Header Snippet Output
 *
 * Reads ?v=SNIPPET_KEY, looks up the saved code, and emits it as a
 * JavaScript file that document.write()s the code into the calling page.
 *
 * Called by cdn/h.js.php (and direct visits to this URL also work).
 *
 * Output: application/javascript
 *   - If snippet exists + enabled: a tiny IIFE that document.writes the code.
 *     document.write is used because the code may be a mix of <script>,
 *     <noscript>, <meta>, etc. (typical pixel snippets) — needs to inject
 *     verbatim into the DOM. The script tag should sit early in <head> so
 *     document.write happens during parse.
 *   - If snippet missing / disabled / no key: emits a no-op + console hint
 *     instead of erroring (silent for end users).
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=60'); // 1-min CDN cache

$key = isset($_GET['v']) ? trim((string)$_GET['v']) : '';

if ($key === '' || !preg_match('/^[a-f0-9]{6,32}$/i', $key)) {
    echo "/* header-snippet: missing or invalid key */\n";
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT label, code, enabled FROM header_snippets WHERE snippet_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
} catch (Exception $e) {
    echo "/* header-snippet: db error */\n";
    exit;
}

if (!$row) {
    echo "/* header-snippet: not found ($key) */\n";
    exit;
}

if ((int)$row['enabled'] !== 1) {
    echo "/* header-snippet: '" . addslashes($row['label']) . "' is disabled */\n";
    exit;
}

$code = (string)$row['code'];

// If code is empty, emit a no-op (no error)
if (trim($code) === '') {
    echo "/* header-snippet: '" . addslashes($row['label']) . "' is empty */\n";
    exit;
}

// Encode as a JS string literal so document.write can output it verbatim.
// json_encode handles all the escaping (quotes, newlines, backslashes,
// </script> sequences, unicode) safely.
$encoded = json_encode($code, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Defend against the literal "</script>" appearing inside the encoded string,
// which would close the surrounding <script> tag a browser is parsing right now.
$encoded = str_replace('</', '<\/', $encoded);

echo "/* header-snippet: " . addslashes($row['label']) . " */\n";
echo "(function(){\n";
echo "  try { document.write($encoded); } catch(e) { if (window.console) console.warn('[header-snippet] document.write failed', e); }\n";
echo "})();\n";
