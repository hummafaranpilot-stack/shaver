<?php
/**
 * FB Traffic Detector — capture.php
 * POST endpoint receiving the snippet's payload. Enriches with server-side
 * sections (HTTP headers, UA decode, IPQS), computes verdict, persists to
 * the `traffic_visits` MySQL table.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../ipqs.php';

// CORS: snippet runs on arbitrary lander domains
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Expose-Headers: X-Fraud-Verdict');
header('Access-Control-Max-Age: 600');
header('Content-Type: application/json');

// Logs directory for auxiliary append-only files (blocked_ips.json, cross_origin.log).
// Visit data lives in MySQL — these are just security/audit trails.
$AUX_LOGS = __DIR__ . '/logs/';
if (!is_dir($AUX_LOGS)) @mkdir($AUX_LOGS, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']); exit;
}

$MAX_BODY_KB = 200;

$contentLen = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLen > $MAX_BODY_KB * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Body too large']); exit;
}

$raw = @file_get_contents('php://input');
if ($raw === false || $raw === '' || strlen($raw) > $MAX_BODY_KB * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Body invalid']); exit;
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']); exit;
}

$domainKey = isset($payload['domain_key']) ? preg_replace('/[^a-z0-9]/i', '', (string)$payload['domain_key']) : '';
if ($domainKey === '') {
    http_response_code(403);
    echo json_encode(['error' => 'Missing domain_key']); exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT id, label, domain_url, status FROM traffic_domains WHERE domain_key = ? LIMIT 1");
$stmt->execute([$domainKey]);
$domain = $stmt->fetch();
if (!$domain || $domain['status'] !== 'active') {
    http_response_code(403);
    echo json_encode(['error' => 'Domain not registered or disabled']); exit;
}

// =====================================================================
// Build server-side sections
// =====================================================================
$ip = traffic_get_ip();
$section2 = build_section2_headers();
$section3 = build_section3_ua_decode($section2['user_agent']);
$section4 = build_section4_ipqs($ip, $section2['user_agent']);
$section8_server = build_section8_server($section2, $section3);
$section9_server = build_section9_server($section2, $section3);

$s8 = array_merge($payload['section8_facebook_client'] ?? [], $section8_server);
$s9 = array_merge($payload['section9_bot_checks_client'] ?? [], $section9_server);

// Cross-origin sanity log (informational only — does not block):
// expected: capture.php is hosted on the detector subdomain, but section1._page_url
// is the lander on a different domain — that's fine, snippet is cross-origin by design.
// Anomaly: host header is something OTHER than the detector subdomain (could mean
// the snippet is being called from an unexpected proxy / mirror).
$expectedHosts = ['shaver.trustednutraproduct.com'];
$incomingHost  = strtolower($section2['host'] ?? '');
if ($incomingHost !== '' && !in_array($incomingHost, $expectedHosts, true)) {
    $pageUrl = $payload['section1_url_params']['_page_url'] ?? '';
    @file_put_contents(
        $AUX_LOGS . 'cross_origin.log',
        '[' . date('Y-m-d H:i:s') . '] host=' . $incomingHost
        . ' page=' . $pageUrl . ' ip=' . $ip . ' key=' . $domainKey . "\n",
        FILE_APPEND | LOCK_EX
    );
}

$verdict = compute_verdict(
    $payload['section1_url_params']    ?? [],
    $section2,
    $section3,
    $section4,
    $payload['section5_browser_device'] ?? [],
    $payload['section6_webgl_canvas']   ?? [],
    $payload['section7_behavioral']     ?? [],
    $s8, $s9, $domainKey, $pdo
);

// Auto-block plumbing: when the verdict tips into critical fraud, signal the
// snippet via response header + append an audit line to blocked_ips.json (JSONL).
if (!empty($verdict['block_recommended'])) {
    header('X-Fraud-Verdict: BLOCK');
    @file_put_contents(
        $AUX_LOGS . 'blocked_ips.json',
        json_encode([
            'timestamp'      => date('c'),
            'ip'             => $ip,
            'domain_key'     => $domainKey,
            'points_earned'  => $verdict['points_earned'] ?? null,
            'risk_score'     => $verdict['risk_score'] ?? null,
            'negative_flags' => $verdict['negative_flags'] ?? [],
            'user_agent'     => $section2['user_agent'] ?? null,
        ], JSON_UNESCAPED_SLASHES) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

$record = [
    'meta' => [
        'domain_key'     => $domainKey,
        'domain_label'   => $domain['label'],
        'domain_url'     => $domain['domain_url'],
        'captured_at'    => date('c'),
        'captured_at_ts' => time(),
        'ip'             => $ip,
        'partial'        => !empty($payload['partial']),
        'client_sent_s'  => $payload['_sent_at_s'] ?? null,
    ],
    'section1_url_params'    => $payload['section1_url_params'] ?? [],
    'section2_http_headers'  => $section2,
    'section3_ua_decode'     => $section3,
    'section4_ip_info'       => $section4,
    'section5_browser_device'=> $payload['section5_browser_device'] ?? [],
    'section6_webgl_canvas'  => $payload['section6_webgl_canvas'] ?? [],
    'section7_behavioral'    => $payload['section7_behavioral'] ?? [],
    'section8_facebook'      => $s8,
    'section9_bot_checks'    => $s9,
    'verdict'                => $verdict,
];

// =====================================================================
// Persist
// =====================================================================
$s1 = $payload['section1_url_params'] ?? [];
$s5 = $payload['section5_browser_device'] ?? [];
$s7 = $payload['section7_behavioral'] ?? [];

$ins = $pdo->prepare("
    INSERT INTO traffic_visits (
        traffic_domain_id, domain_key, captured_at, ip_address,
        country_code, country, city, region, isp,
        fraud_score, is_proxy, is_vpn, is_tor, is_datacenter, is_mobile_ip,
        os_name, os_version, browser_name, browser_version, device_model,
        fbclid, utm_source, utm_medium, utm_campaign, utm_content, utm_term,
        referer_domain, is_facebook_referrer,
        time_on_page_s, max_scroll_depth_pct,
        verdict, verdict_label, risk_score, points_earned, points_possible, checks_passed, checks_total,
        is_partial, full_data
    ) VALUES (
        ?, ?, NOW(), ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?,
        ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?
    )
");
$ins->execute([
    $domain['id'], $domainKey, $ip,
    $section4['country_code'] ?? null, $section4['country'] ?? null, $section4['city'] ?? null, $section4['region'] ?? null, $section4['isp'] ?? null,
    is_numeric($section4['fraud_score'] ?? null) ? (int)$section4['fraud_score'] : null,
    !empty($section4['is_proxy']) ? 1 : 0,
    !empty($section4['is_vpn']) ? 1 : 0,
    !empty($section4['is_tor']) ? 1 : 0,
    !empty($section4['is_datacenter']) ? 1 : 0,
    !empty($section4['mobile']) ? 1 : 0,
    $section3['os_name'] ?? null, $section3['os_version'] ?? null, $section3['browser_name'] ?? null, $section3['browser_version'] ?? null, $section3['device_model'] ?? null,
    !empty($s1['fbclid']) ? substr((string)$s1['fbclid'], 0, 255) : null,
    !empty($s1['utm_source']) ? substr((string)$s1['utm_source'], 0, 120) : null,
    !empty($s1['utm_medium']) ? substr((string)$s1['utm_medium'], 0, 120) : null,
    !empty($s1['utm_campaign']) ? substr((string)$s1['utm_campaign'], 0, 255) : null,
    !empty($s1['utm_content']) ? substr((string)$s1['utm_content'], 0, 255) : null,
    !empty($s1['utm_term']) ? substr((string)$s1['utm_term'], 0, 255) : null,
    !empty($s8['referer_domain']) ? substr((string)$s8['referer_domain'], 0, 255) : null,
    !empty($s8['referer_is_facebook']) ? 1 : 0,
    is_numeric($s7['time_on_page_s'] ?? null) ? (float)$s7['time_on_page_s'] : null,
    is_numeric($s7['max_scroll_depth_pct'] ?? null) ? (int)$s7['max_scroll_depth_pct'] : null,
    // verdict column is ENUM('PASS','SUSPICIOUS','FAIL'); BLOCK maps to FAIL
    // for the indexed column. Full result preserved in full_data JSON.
    in_array($verdict['result'] ?? null, ['PASS','SUSPICIOUS','FAIL'], true)
        ? $verdict['result']
        : (($verdict['result'] ?? null) === 'BLOCK' ? 'FAIL' : null),
    $verdict['label'] ?? null,
    $verdict['risk_score'] ?? null, $verdict['points_earned'] ?? null, $verdict['points_possible'] ?? null,
    $verdict['checks_passed'] ?? null, $verdict['checks_total'] ?? null,
    !empty($payload['partial']) ? 1 : 0,
    json_encode($record, JSON_UNESCAPED_SLASHES),
]);

echo json_encode([
    'ok'      => true,
    'id'      => (int)$pdo->lastInsertId(),
    'verdict' => $verdict,
    'data'    => $record,
]);
exit;

// =====================================================================
// Helpers
// =====================================================================

function traffic_get_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

function build_section2_headers(): array {
    $g = function ($k) { return isset($_SERVER[$k]) ? (string)$_SERVER[$k] : 'absent'; };
    return [
        'host'                       => $g('HTTP_HOST'),
        'user_agent'                 => $g('HTTP_USER_AGENT'),
        'accept'                     => $g('HTTP_ACCEPT'),
        'accept_language'            => $g('HTTP_ACCEPT_LANGUAGE'),
        'accept_encoding'            => $g('HTTP_ACCEPT_ENCODING'),
        'referer'                    => $g('HTTP_REFERER'),
        'sec_fetch_site'             => $g('HTTP_SEC_FETCH_SITE'),
        'sec_fetch_mode'             => $g('HTTP_SEC_FETCH_MODE'),
        'sec_fetch_dest'             => $g('HTTP_SEC_FETCH_DEST'),
        'sec_ch_ua'                  => $g('HTTP_SEC_CH_UA'),
        'sec_ch_ua_mobile'           => $g('HTTP_SEC_CH_UA_MOBILE'),
        'sec_ch_ua_platform'         => $g('HTTP_SEC_CH_UA_PLATFORM'),
        'connection'                 => $g('HTTP_CONNECTION'),
        'x_requested_with'           => $g('HTTP_X_REQUESTED_WITH'),
        'upgrade_insecure_requests'  => $g('HTTP_UPGRADE_INSECURE_REQUESTS'),
        'dnt'                        => $g('HTTP_DNT'),
    ];
}

function build_section3_ua_decode(string $ua): array {
    $out = [
        'ios_version' => null, 'android_version' => null,
        'fban' => null, 'fbav' => null, 'fbbv' => null, 'fbdv' => null,
        'fbmd' => null, 'fbsn' => null, 'fbsv' => null, 'fbss' => null,
        'fbid' => null, 'fblc' => null, 'fbop' => null,
        'browser_name' => null, 'browser_version' => null,
        'os_name' => null, 'os_version' => null, 'device_model' => null,
    ];
    if (!$ua || $ua === 'absent') return $out;

    if (preg_match('/iPhone OS (\d+_\d+(?:_\d+)?)/', $ua, $m)) $out['ios_version'] = str_replace('_', '.', $m[1]);
    elseif (preg_match('/CPU OS (\d+_\d+(?:_\d+)?) like Mac/', $ua, $m)) $out['ios_version'] = str_replace('_', '.', $m[1]);
    if (preg_match('/Android (\d+(?:\.\d+)*)/', $ua, $m)) $out['android_version'] = $m[1];

    foreach (['FBAN' => 'fban', 'FBAV' => 'fbav', 'FBBV' => 'fbbv', 'FBDV' => 'fbdv',
              'FBMD' => 'fbmd', 'FBSN' => 'fbsn', 'FBSV' => 'fbsv', 'FBSS' => 'fbss',
              'FBID' => 'fbid', 'FBLC' => 'fblc', 'FBOP' => 'fbop'] as $tag => $key) {
        if (preg_match('/' . $tag . '\/([^;\]]+)/', $ua, $m)) $out[$key] = $m[1];
    }

    if (stripos($ua, 'FBAN/FBIOS') !== false || stripos($ua, 'FBAN/FB4A') !== false) {
        $out['browser_name'] = 'Facebook In-App';
    } elseif (preg_match('/Instagram (\d+\.\d+)/', $ua)) {
        $out['browser_name'] = 'Instagram In-App';
    } elseif (preg_match('/Edg\/(\d+)/', $ua, $m)) { $out['browser_name'] = 'Edge'; $out['browser_version'] = $m[1]; }
    elseif (preg_match('/Chrome\/(\d+)/', $ua, $m)) { $out['browser_name'] = 'Chrome'; $out['browser_version'] = $m[1]; }
    elseif (preg_match('/Firefox\/(\d+)/', $ua, $m)) { $out['browser_name'] = 'Firefox'; $out['browser_version'] = $m[1]; }
    elseif (preg_match('/Version\/(\d+).*Safari/', $ua, $m)) { $out['browser_name'] = 'Safari'; $out['browser_version'] = $m[1]; }
    if (!$out['browser_version'] && $out['fbav']) $out['browser_version'] = $out['fbav'];

    if ($out['ios_version']) {
        $out['os_name'] = stripos($ua, 'iPad') !== false ? 'iPadOS' : 'iOS';
        $out['os_version'] = $out['ios_version'];
    } elseif ($out['android_version']) {
        $out['os_name'] = 'Android'; $out['os_version'] = $out['android_version'];
    } elseif (stripos($ua, 'Mac OS X') !== false) {
        if (preg_match('/Mac OS X (\d+[._]\d+(?:[._]\d+)?)/', $ua, $m)) $out['os_version'] = str_replace('_', '.', $m[1]);
        $out['os_name'] = 'macOS';
    } elseif (stripos($ua, 'Windows NT') !== false) {
        if (preg_match('/Windows NT ([\d.]+)/', $ua, $m)) $out['os_version'] = $m[1];
        $out['os_name'] = 'Windows';
    } elseif (stripos($ua, 'Linux') !== false) {
        $out['os_name'] = 'Linux';
    }

    $out['device_model'] = fbdv_to_model($out['fbdv'], $ua);
    return $out;
}

function fbdv_to_model(?string $fbdv, string $ua): ?string {
    static $map = [
        'iPhone8,1'=>'iPhone 6s','iPhone8,2'=>'iPhone 6s Plus','iPhone8,4'=>'iPhone SE',
        'iPhone9,1'=>'iPhone 7','iPhone9,3'=>'iPhone 7','iPhone9,2'=>'iPhone 7 Plus','iPhone9,4'=>'iPhone 7 Plus',
        'iPhone10,1'=>'iPhone 8','iPhone10,4'=>'iPhone 8','iPhone10,2'=>'iPhone 8 Plus','iPhone10,5'=>'iPhone 8 Plus',
        'iPhone10,3'=>'iPhone X','iPhone10,6'=>'iPhone X',
        'iPhone11,2'=>'iPhone XS','iPhone11,4'=>'iPhone XS Max','iPhone11,6'=>'iPhone XS Max','iPhone11,8'=>'iPhone XR',
        'iPhone12,1'=>'iPhone 11','iPhone12,3'=>'iPhone 11 Pro','iPhone12,5'=>'iPhone 11 Pro Max','iPhone12,8'=>'iPhone SE (2nd gen)',
        'iPhone13,1'=>'iPhone 12 mini','iPhone13,2'=>'iPhone 12','iPhone13,3'=>'iPhone 12 Pro','iPhone13,4'=>'iPhone 12 Pro Max',
        'iPhone14,2'=>'iPhone 13 Pro','iPhone14,3'=>'iPhone 13 Pro Max','iPhone14,4'=>'iPhone 13 mini','iPhone14,5'=>'iPhone 13',
        'iPhone14,6'=>'iPhone SE (3rd gen)','iPhone14,7'=>'iPhone 14','iPhone14,8'=>'iPhone 14 Plus',
        'iPhone15,2'=>'iPhone 14 Pro','iPhone15,3'=>'iPhone 14 Pro Max','iPhone15,4'=>'iPhone 15','iPhone15,5'=>'iPhone 15 Plus',
        'iPhone16,1'=>'iPhone 15 Pro','iPhone16,2'=>'iPhone 15 Pro Max',
        'iPhone17,1'=>'iPhone 16 Pro','iPhone17,2'=>'iPhone 16 Pro Max','iPhone17,3'=>'iPhone 16','iPhone17,4'=>'iPhone 16 Plus',
    ];
    if ($fbdv && isset($map[$fbdv])) return $map[$fbdv];
    if ($fbdv && stripos($fbdv, 'iPhone') === 0) return 'iPhone (' . $fbdv . ')';
    if ($fbdv && stripos($fbdv, 'iPad') === 0)   return 'iPad ('   . $fbdv . ')';
    if ($fbdv) return $fbdv;
    if (stripos($ua, 'iPhone') !== false) return 'iPhone';
    if (stripos($ua, 'iPad') !== false)   return 'iPad';
    if (preg_match('/Android.*; ([^;]+) Build\//', $ua, $m)) return trim($m[1]);
    return null;
}

function build_section4_ipqs(string $ip, string $ua): array {
    $blank = [
        'ip' => $ip, 'ip_type' => null, 'isp' => null, 'asn' => null, 'organization' => null,
        'connection_type' => null, 'network_gen' => null, 'city' => null, 'region' => null,
        'country' => null, 'country_code' => null, 'postal_code' => null,
        'latitude' => null, 'longitude' => null, 'timezone_from_ip' => null,
        'is_proxy' => null, 'is_vpn' => null, 'is_tor' => null, 'is_crawler' => null, 'is_datacenter' => null,
        'fraud_score' => null, 'recent_abuse' => null, 'abuse_velocity' => null,
        'host' => null, 'mobile' => null,
    ];
    try {
        $ipqs = new IPQS(IPQS_API_KEYS);
        $r = $ipqs->analyzeIP($ip, ['user_agent' => $ua]);
    } catch (Exception $e) { return $blank; }
    if (!$r) return $blank;
    if (!empty($r['is_local'])) {
        return array_merge($blank, ['country_code' => 'LOCAL', 'city' => 'Local']);
    }
    return [
        'ip' => $ip,
        'ip_type'         => !empty($r['mobile']) ? 'Mobile'
                             : (!empty($r['is_datacenter']) ? 'Datacenter'
                             : (!empty($r['is_crawler']) ? 'Crawler' : 'Residential')),
        'isp'             => $r['ISP']  ?? $r['isp'] ?? null,
        'asn'             => $r['ASN']  ?? $r['asn'] ?? null,
        'organization'    => $r['organization'] ?? null,
        'connection_type' => $r['connection_type'] ?? null,
        'network_gen'     => $r['network'] ?? null,
        'city'            => $r['city'] ?? null,
        'region'          => $r['region'] ?? null,
        'country'         => $r['country'] ?? $r['country_code'] ?? null,
        'country_code'    => $r['country_code'] ?? null,
        'postal_code'     => $r['zip_code'] ?? null,
        'latitude'        => $r['latitude'] ?? null,
        'longitude'       => $r['longitude'] ?? null,
        'timezone_from_ip'=> $r['timezone'] ?? null,
        'is_proxy'        => (bool)($r['proxy'] ?? false),
        'is_vpn'          => (bool)($r['vpn']   ?? false),
        'is_tor'          => (bool)($r['tor']   ?? false),
        'is_crawler'      => (bool)($r['is_crawler'] ?? false),
        'is_datacenter'   => (bool)($r['is_datacenter'] ?? false),
        'fraud_score'     => $r['fraud_score'] ?? null,
        'recent_abuse'    => (bool)($r['recent_abuse'] ?? false),
        'abuse_velocity'  => $r['abuse_velocity'] ?? null,
        'host'            => $r['host'] ?? null,
        'mobile'          => (bool)($r['mobile'] ?? false),
    ];
}

function build_section8_server(array $s2, array $s3): array {
    $ua = $s2['user_agent'] ?? '';
    return [
        'ua_has_fban_fbios'   => stripos($ua, 'FBAN/FBIOS') !== false,
        'ua_has_fb4a'         => stripos($ua, 'FBAN/FB4A')  !== false,
        'ua_has_fbdv_iphone'  => $s3['fbdv'] && stripos($s3['fbdv'], 'iPhone') === 0,
        'ua_has_fbdv_android' => stripos($ua, 'FB4A') !== false,
        'ua_has_messenger'    => stripos($ua, 'Messenger') !== false || stripos($ua, 'FBAN/MessengerForiOS') !== false,
        'ua_has_instagram'    => stripos($ua, 'Instagram') !== false,
        'fb_capi_match'       => 'N/A',
    ];
}

function build_section9_server(array $s2, array $s3): array {
    $isiOS     = ($s3['os_name'] === 'iOS' || $s3['os_name'] === 'iPadOS');
    $isAndroid = ($s3['os_name'] === 'Android');
    $secCh     = $s2['sec_ch_ua'] ?? 'absent';

    $iosOk     = $isiOS ? ($secCh === 'absent') : true;
    $androidOk = ($isAndroid && (stripos($s2['user_agent'] ?? '', 'Chrome') !== false))
        ? ($secCh !== 'absent') : true;

    return [
        'ios_webkit_consistency'  => $iosOk ? 'PASS' : 'FAIL',
        'sec_ch_ua_consistency'   => $androidOk ? 'PASS' : 'FAIL',
    ];
}

// =====================================================================
// PHILOSOPHY: This tracker thinks like Everflow / Voluum / RedTrack.
//
// Everflow does NOT verify label semantics:
//   - It does NOT parse utm_campaign for geo codes
//   - It does NOT compare creative names to landing pages
//   - It does NOT check audience labels for compliance
//
// Everflow ONLY verifies technical signals:
//   - IP fraud (proxy / VPN / datacenter / Tor / fraud_score / recent_abuse)
//   - Device fingerprint authenticity (UA spoofing detection)
//   - URL parameter FORMAT (not meaning)
//   - Click uniqueness (fbclid recency, IP frequency)
//   - Behavioral validity (real human engagement)
//
// We do the same. utm_campaign / utm_content / utm_term are stored in
// section1_url_params for reporting but never scored. Affiliates can name
// these whatever they want — that's their copy, not a fraud signal.
// =====================================================================
function compute_verdict(array $s1, array $s2, array $s3, array $s4, array $s5, array $s6, array $s7, array $s8, array $s9, string $domainKey, PDO $pdo): array {
    $checks = [];
    $points = 0;
    $total  = 100;     // max attainable from positive checks

    // $type: 'positive' (points awarded on triggered=true) | 'negative' (points subtracted on triggered=true)
    $add = function (string $name, int $pts, bool $triggered, string $type = 'positive') use (&$checks, &$points) {
        $checks[] = [
            'name'      => $name,
            'points'    => $pts,
            'triggered' => $triggered,
            'pass'      => ($type === 'positive') ? $triggered : !$triggered,
            'type'      => $type,
        ];
        if ($triggered) $points += $pts;
    };

    // ===== Cache common signals =====
    $ua          = $s2['user_agent'] ?? '';
    $isFBIOS     = stripos($ua, 'FBIOS') !== false;
    $isFB4A      = stripos($ua, 'FB4A')  !== false;
    $isFBApp     = $isFBIOS || $isFB4A;
    $isiPhoneUA  = stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false;
    $isMobileUA  = $isFBApp || $isiPhoneUA || stripos($ua, 'Mobile') !== false || stripos($ua, 'Android') !== false;

    $isProxy     = !empty($s4['is_proxy']);
    $isVpn       = !empty($s4['is_vpn']);
    $isTor       = !empty($s4['is_tor']);
    $isDc        = !empty($s4['is_datacenter']);
    $fraud       = is_numeric($s4['fraud_score'] ?? null) ? (int)$s4['fraud_score'] : null;
    $recentAbuse = !empty($s4['recent_abuse']);
    $ispLower    = strtolower((string)($s4['isp'] ?? ''));
    $hostingIsp  = (bool)preg_match('/(hosting|colocation|colo|server|cloud|data\s*center|datacenter)/i', $ispLower);

    $totalTouches      = (int)($s7['total_touch_events'] ?? 0);
    $totalMoves        = (int)($s7['total_mouse_move_events'] ?? 0);
    $totalClicks       = (int)($s7['total_click_events'] ?? 0);
    $totalScrolls      = (int)($s7['total_scroll_events'] ?? 0);
    $totalKeys         = (int)($s7['total_keypress_events'] ?? 0);
    $totalInteractions = $totalTouches + $totalClicks + $totalScrolls + $totalKeys;

    $wd            = $s5['navigator_webdriver'] ?? null;
    $webdriverUndef = ($wd === 'undefined' || $wd === false || $wd === null);
    $webdriverTrue  = ($wd === true || $wd === 'true');

    $hasFbclid    = !empty($s1['fbclid']);
    $fbclidUnique = fbclid_unique_in_db($s1['fbclid'] ?? '', $domainKey, $pdo);

    // =====================================================================
    // POSITIVE CHECKS  (max 100)
    // =====================================================================

    // 1. ua_facebook_app (15) — FBAN/FBIOS or FB4A in UA
    $add('ua_facebook_app', 15, $isFBApp);

    // 2. ua_format_valid (10) — UA carries FB IAB-style tags (FBAV / FBDV / FBSV / FBLC).
    //    iOS FB UA carries all four; Android FB4A carries at least FBAV — that's enough
    //    to call the format "valid". A spoofed UA missing all of these would fail.
    $hasFBTags = !empty($s3['fbav']) || !empty($s3['fbdv']) || !empty($s3['fbsv']) || !empty($s3['fblc']);
    $add('ua_format_valid', 10, $hasFBTags);

    // 3. referer_facebook (10) — l/m/lm.facebook.com or empty (in-app webview)
    $refOk = !empty($s8['referer_is_facebook']) || empty($s2['referer']) || $s2['referer'] === 'absent';
    $add('referer_facebook', 10, $refOk);

    // 4. fbclid_format_valid (10) — strict regex match (snippet already validated)
    $add('fbclid_format_valid', 10, !empty($s8['fbclid_format_valid']));

    // 5. fbclid_unique (5) — never seen before for this domain
    $add('fbclid_unique', 5, $hasFbclid && $fbclidUnique);

    // 6. ip_clean (15) — not proxy / VPN / Tor / datacenter
    $cleanIp = !($isProxy || $isVpn || $isTor || $isDc);
    $add('ip_clean', 15, $cleanIp);

    // 7. fraud_score_low (10)
    $add('fraud_score_low', 10, $fraud !== null && $fraud < 25);

    // 8. not_recent_abuse (5)
    $add('not_recent_abuse', 5, !$recentAbuse);

    // 9. webdriver_undefined (5)
    $add('webdriver_undefined', 5, $webdriverUndef);

    // 10. behavior_natural (10) — real human engagement
    $tos = (float)($s7['time_on_page_s'] ?? 0);
    $sd  = (float)($s7['max_scroll_depth_pct'] ?? 0);
    $add('behavior_natural', 10, $tos > 15 && $sd > 15 && $totalInteractions > 5);

    // 11. placement_valid (5) — placement value is in known FB placement list
    $placement = strtolower((string)($s1['placement'] ?? ''));
    $knownPlacements = [
        'feed','facebook_feed','facebook feed',
        'stories','facebook_stories','instagram_stories','messenger_stories',
        'reels','facebook_reels','instagram_reels',
        'right_hand_column','righthandcolumn','rhc',
        'search','facebook_search','instagram_search',
        'instream_video','video_feeds','facebook_video_feeds','instream','in_stream',
        'marketplace','facebook_marketplace',
        'audience_network_native','audience_network_rewarded_video','audience_network_classic',
        'an_classic','an_native','an_rewarded_video',
        'biz_discovery','business_explore','facebook_business_explore',
        'messenger_inbox','messenger_sponsored_messages',
        'instagram_feed','instagram_explore','instagram_shop','instagram_video_feed',
    ];
    $add('placement_valid', 5, $placement !== '' && in_array($placement, $knownPlacements, true));

    // =====================================================================
    // NEGATIVE CHECKS  (UA-spoof + fraud)
    // Each check fires only when the UA *claims* something the rest of the
    // fingerprint contradicts. No label parsing, no campaign-naming logic.
    // =====================================================================

    // IOS_UA_WITH_SEC_CH_HEADERS (-30) — iOS WebKit never sends Sec-Ch-* on its own
    $secCh = $s2['sec_ch_ua'] ?? 'absent';
    $add('IOS_UA_WITH_SEC_CH_HEADERS', -30,
        $isFBIOS && $secCh !== 'absent' && $secCh !== '',
        'negative');

    // IOS_UA_WITH_WINDOWS_PLATFORM (-30)  ← smoking gun
    $secPlat       = strtolower((string)($s2['sec_ch_ua_platform'] ?? ''));
    $isDesktopPlat = (stripos($secPlat, 'windows') !== false
                  || stripos($secPlat, 'linux')   !== false
                  || stripos($secPlat, 'macos')   !== false);
    $add('IOS_UA_WITH_WINDOWS_PLATFORM', -30,
        $isFBIOS && $isDesktopPlat,
        'negative');

    // NAVIGATOR_PLATFORM_MISMATCH (-25)  ← smoking gun
    $navPlat = $s5['navigator_platform'] ?? '';
    $add('NAVIGATOR_PLATFORM_MISMATCH', -25,
        $isiPhoneUA && $navPlat !== '' && $navPlat !== 'iPhone' && $navPlat !== 'iPad',
        'negative');

    // NAVIGATOR_VENDOR_MISMATCH (-25)  ← smoking gun
    $navVendor = $s5['navigator_vendor'] ?? '';
    $add('NAVIGATOR_VENDOR_MISMATCH', -25,
        $isFBIOS && $navVendor !== '' && $navVendor !== 'Apple Computer, Inc.',
        'negative');

    // DESKTOP_RESOLUTION_ON_MOBILE_UA (-20)
    $screenW = (int)($s5['screen_width'] ?? 0);
    $add('DESKTOP_RESOLUTION_ON_MOBILE_UA', -20,
        $isiPhoneUA && $screenW > 500,
        'negative');

    // GPU_VENDOR_MISMATCH (-20)
    $glVendor = $s6['webgl_unmasked_vendor'] ?? null;
    $add('GPU_VENDOR_MISMATCH', -20,
        $isiPhoneUA && $glVendor !== null && $glVendor !== '' && stripos($glVendor, 'Apple') === false,
        'negative');

    // DESKTOP_GPU_ON_MOBILE_UA (-20)
    $glRenderer = $s6['webgl_unmasked_renderer'] ?? '';
    $isDesktopGPU = $glRenderer !== '' && stripos($glRenderer, 'Apple') === false && (
        stripos($glRenderer, 'Intel')    !== false ||
        stripos($glRenderer, 'NVIDIA')   !== false ||
        stripos($glRenderer, 'AMD')      !== false ||
        stripos($glRenderer, 'Direct3D') !== false ||
        stripos($glRenderer, 'ANGLE')    !== false
    );
    $add('DESKTOP_GPU_ON_MOBILE_UA', -20, $isiPhoneUA && $isDesktopGPU, 'negative');

    // NO_TOUCH_ON_MOBILE_UA (-20) — real phones have no mouse
    $add('NO_TOUCH_ON_MOBILE_UA', -20,
        $isMobileUA && $totalTouches === 0 && $totalMoves > 10,
        'negative');

    // MAX_TOUCH_POINTS_ZERO_ON_MOBILE (-15)
    $mtp = (int)($s5['navigator_max_touch_points'] ?? 0);
    $add('MAX_TOUCH_POINTS_ZERO_ON_MOBILE', -15, $isMobileUA && $mtp === 0, 'negative');

    // WINDOWS_FONTS_ON_IPHONE (-10) — only flag when MULTIPLE matches (avoid false positives)
    $winFonts = ['Calibri','MS Gothic','Cambria','Segoe UI','Consolas','Segoe Print','SimSun','Microsoft Sans Serif'];
    $availFonts = is_array($s6['available_fonts'] ?? null) ? $s6['available_fonts'] : [];
    $winFontMatches = count(array_intersect($winFonts, $availFonts));
    $add('WINDOWS_FONTS_ON_IPHONE', -10, $isiPhoneUA && $winFontMatches >= 2, 'negative');

    // CHROME_PLUGINS_ON_IOS (-15)
    $chromePlugins = ['Chrome PDF Viewer','Chromium PDF Viewer','Microsoft Edge PDF Viewer','PDF Viewer'];
    $pluginsList   = is_array($s5['plugins_list'] ?? null) ? $s5['plugins_list'] : [];
    $hasChromePlugin = (bool)array_intersect($chromePlugins, $pluginsList);
    $add('CHROME_PLUGINS_ON_IOS', -15, $isFBIOS && $hasChromePlugin, 'negative');

    // WEBDRIVER_TRUE (-30)  ← smoking gun
    $add('WEBDRIVER_TRUE', -30, $webdriverTrue, 'negative');

    // HEADLESS_UA_MARKER (-30)  ← smoking gun
    $add('HEADLESS_UA_MARKER', -30,
        preg_match('/HeadlessChrome|PhantomJS|Selenium|Puppeteer/i', $ua) > 0,
        'negative');

    // DUPLICATE_FBCLID (-10)
    $add('DUPLICATE_FBCLID', -10, $hasFbclid && !$fbclidUnique, 'negative');

    // IP_HIGH_FRAUD (-25)
    $add('IP_HIGH_FRAUD', -25, $fraud !== null && $fraud >= 75, 'negative');

    // IP_PROXY_OR_VPN (-25)
    $add('IP_PROXY_OR_VPN', -25, $isProxy || $isVpn, 'negative');

    // IP_DATACENTER (-20)
    $add('IP_DATACENTER', -20, $isDc || $hostingIsp, 'negative');

    // IP_RECENT_ABUSE (-20)
    $add('IP_RECENT_ABUSE', -20, $recentAbuse, 'negative');

    // =====================================================================
    // Smoking-gun list: technical impossibilities — no real iOS FB user can
    // produce these signals. Any one of these forces minimum FAIL.
    // =====================================================================
    $smokingGunFlags = [
        'IOS_UA_WITH_WINDOWS_PLATFORM',
        'NAVIGATOR_PLATFORM_MISMATCH',
        'NAVIGATOR_VENDOR_MISMATCH',
        'WEBDRIVER_TRUE',
        'HEADLESS_UA_MARKER',
    ];

    $smokingGun    = false;
    $negativeFlags = [];
    $positiveFlags = []; // legacy: positive checks that did NOT pass
    $passedCount   = 0;
    foreach ($checks as $c) {
        if ($c['triggered'] && $c['type'] === 'negative') {
            $negativeFlags[] = $c['name'];
            if (in_array($c['name'], $smokingGunFlags, true)) $smokingGun = true;
        }
        if (!$c['triggered'] && $c['type'] === 'positive' && $c['points'] > 0) {
            $positiveFlags[] = $c['name'];
        }
        if ($c['triggered'] && $c['type'] === 'positive') $passedCount++;
    }

    // =====================================================================
    // Verdict
    // =====================================================================
    if ($points < 0) {
        $result = 'BLOCK';
        $label  = 'AUTO-BLOCK — critical fraud signals';
    } elseif ($smokingGun) {
        $result = 'FAIL';
        $label  = 'SMOKING GUN — ' . ($negativeFlags[0] ?? 'spoofed UA');
    } elseif ($points >= 80) {
        $result = 'PASS';
        $label  = label_for_pass($s3, $s8);
    } elseif ($points >= 50) {
        $result = 'SUSPICIOUS';
        $label  = 'NEEDS MANUAL REVIEW';
    } else {
        $result = 'FAIL';
        $label  = 'LIKELY SPOOFED OR BOT';
    }

    $blockRecommended = ($result === 'BLOCK');

    $riskScore = ($points <= 0)
        ? 100
        : max(0, 100 - (int)round(($points / $total) * 100));

    return [
        'result'                 => $result,
        'label'                  => $label,
        'risk_score'             => $riskScore,
        'checks_passed'          => $passedCount,
        'checks_total'           => count($checks),
        'points_earned'          => $points,
        'points_possible'        => $total,
        'smoking_gun_triggered'  => $smokingGun,
        'block_recommended'      => $blockRecommended,
        'flags'                  => $positiveFlags,
        'negative_flags'         => $negativeFlags,
        'checks'                 => $checks,
    ];
}

function label_for_pass(array $s3, array $s8): string {
    $os       = $s3['os_name'] ?? '';
    $isFbApp  = !empty($s8['ua_has_fban_fbios']) || !empty($s8['ua_has_fb4a']);
    $platform = $isFbApp ? 'FACEBOOK' : (!empty($s8['referer_is_facebook']) ? 'FACEBOOK' : 'WEB');
    $device   = ($os === 'iOS' || $os === 'iPadOS') ? 'iOS MOBILE'
              : ($os === 'Android' ? 'ANDROID MOBILE'
              : ($os === 'Windows' ? 'WINDOWS DESKTOP'
              : ($os === 'macOS' ? 'MAC DESKTOP' : 'UNKNOWN')));
    return "REAL $platform $device TRAFFIC";
}

function fbclid_unique_in_db(string $fbclid, string $domainKey, PDO $pdo): bool {
    if ($fbclid === '') return false;
    $stmt = $pdo->prepare("SELECT 1 FROM traffic_visits WHERE domain_key = ? AND fbclid = ? LIMIT 1");
    $stmt->execute([$domainKey, substr($fbclid, 0, 255)]);
    return !$stmt->fetch();
}
