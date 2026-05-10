<?php
/**
 * FB Traffic Detector — capture.php
 * POST endpoint that receives client payload, enriches with server-side data,
 * computes verdict, saves visitor log under logs/<domain_key>/.
 */

require_once __DIR__ . '/config.php';

// ===== CORS =====
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Max-Age: 600');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'POST only']); exit;
}

// ===== Body =====
$contentLen = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLen > TRAFFIC_MAX_BODY_KB * 1024) {
    http_response_code(413);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Body too large']); exit;
}

$raw = @file_get_contents('php://input');
if (!$raw || strlen($raw) > TRAFFIC_MAX_BODY_KB * 1024) {
    http_response_code(413);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Body invalid']); exit;
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid JSON']); exit;
}

// ===== Domain key validation =====
$domainKey = isset($payload['domain_key']) ? preg_replace('/[^a-z0-9]/i', '', (string)$payload['domain_key']) : '';
if (!$domainKey) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing domain_key']); exit;
}
$domain = traffic_find_domain($domainKey);
if (!$domain || ($domain['status'] ?? 'active') !== 'active') {
    traffic_log_error("Reject: unknown/disabled key=$domainKey ip=" . traffic_get_client_ip());
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Domain not registered or disabled']); exit;
}

// ===== Rate limit per IP =====
$ip = traffic_get_client_ip();
$rateFile = TRAFFIC_LOGS_DIR . '.rate_' . sha1($ip);
if (!traffic_rate_check($rateFile, TRAFFIC_RATE_LIMIT_PER_HOUR, 3600)) {
    http_response_code(429);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Rate limited']); exit;
}

// ===== Build server-side sections =====
$section2 = build_section2_headers();
$section3 = build_section3_ua_decode($section2['user_agent']);
$section4 = build_section4_ipqs($ip);
$section8_server = build_section8_server($section2, $section3);
$section9_server = build_section9_server($section2, $section3);

// Merge client + server portions
$s8_full = array_merge(($payload['section8_facebook_client'] ?? []), $section8_server);
$s9_full = array_merge(($payload['section9_bot_checks_client'] ?? []), $section9_server);

// ===== Verdict =====
$verdict = compute_verdict(
    $payload['section1_url_params'] ?? [],
    $section2,
    $section3,
    $section4,
    $payload['section5_browser_device'] ?? [],
    $payload['section6_webgl_canvas'] ?? [],
    $payload['section7_behavioral'] ?? [],
    $s8_full,
    $s9_full,
    $domainKey
);

// ===== Save =====
$record = [
    'meta' => [
        'domain_key'     => $domainKey,
        'domain_label'   => $domain['label'] ?? null,
        'domain_url'     => $domain['domain_url'] ?? null,
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
    'section8_facebook'      => $s8_full,
    'section9_bot_checks'    => $s9_full,
    'verdict'                => $verdict,
];

$dir = TRAFFIC_LOGS_DIR . $domainKey . '/';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$fname = 'visitor_' . date('Y-m-d') . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.json';
$fpath = $dir . $fname;
@file_put_contents($fpath, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

// Trim if we exceed cap
trim_logs($dir, TRAFFIC_MAX_LOG_FILES_PER_DOMAIN);

header('Content-Type: application/json');
echo json_encode([
    'ok'       => true,
    'file'     => $fname,
    'verdict'  => $verdict,
    'data'     => $record,
]);
exit;

// =====================================================================
// SECTION 2 — HTTP headers
// =====================================================================
function build_section2_headers(): array {
    $get = function ($k) {
        return isset($_SERVER[$k]) ? (string)$_SERVER[$k] : 'absent';
    };
    return [
        'host'                       => $get('HTTP_HOST'),
        'user_agent'                 => $get('HTTP_USER_AGENT'),
        'accept'                     => $get('HTTP_ACCEPT'),
        'accept_language'            => $get('HTTP_ACCEPT_LANGUAGE'),
        'accept_encoding'            => $get('HTTP_ACCEPT_ENCODING'),
        'referer'                    => $get('HTTP_REFERER'),
        'sec_fetch_site'             => $get('HTTP_SEC_FETCH_SITE'),
        'sec_fetch_mode'             => $get('HTTP_SEC_FETCH_MODE'),
        'sec_fetch_dest'             => $get('HTTP_SEC_FETCH_DEST'),
        'sec_ch_ua'                  => $get('HTTP_SEC_CH_UA'),
        'sec_ch_ua_mobile'           => $get('HTTP_SEC_CH_UA_MOBILE'),
        'sec_ch_ua_platform'         => $get('HTTP_SEC_CH_UA_PLATFORM'),
        'connection'                 => $get('HTTP_CONNECTION'),
        'x_requested_with'           => $get('HTTP_X_REQUESTED_WITH'),
        'upgrade_insecure_requests'  => $get('HTTP_UPGRADE_INSECURE_REQUESTS'),
        'dnt'                        => $get('HTTP_DNT'),
    ];
}

// =====================================================================
// SECTION 3 — UA decode
// =====================================================================
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

    // iOS
    if (preg_match('/iPhone OS (\d+_\d+(?:_\d+)?)/', $ua, $m)) $out['ios_version'] = str_replace('_', '.', $m[1]);
    elseif (preg_match('/CPU OS (\d+_\d+(?:_\d+)?) like Mac/', $ua, $m)) $out['ios_version'] = str_replace('_', '.', $m[1]);
    // Android
    if (preg_match('/Android (\d+(?:\.\d+)*)/', $ua, $m)) $out['android_version'] = $m[1];

    // FB IAB-style key/value pairs in brackets
    if (preg_match('/\[FBAN\/([^;\]]+);/', $ua, $m)) $out['fban'] = $m[1];
    if (preg_match('/FBAV\/([^;\]]+)/', $ua, $m)) $out['fbav'] = $m[1];
    if (preg_match('/FBBV\/([^;\]]+)/', $ua, $m)) $out['fbbv'] = $m[1];
    if (preg_match('/FBDV\/([^;\]]+)/', $ua, $m)) $out['fbdv'] = $m[1];
    if (preg_match('/FBMD\/([^;\]]+)/', $ua, $m)) $out['fbmd'] = $m[1];
    if (preg_match('/FBSN\/([^;\]]+)/', $ua, $m)) $out['fbsn'] = $m[1];
    if (preg_match('/FBSV\/([^;\]]+)/', $ua, $m)) $out['fbsv'] = $m[1];
    if (preg_match('/FBSS\/([^;\]]+)/', $ua, $m)) $out['fbss'] = $m[1];
    if (preg_match('/FBID\/([^;\]]+)/', $ua, $m)) $out['fbid'] = $m[1];
    if (preg_match('/FBLC\/([^;\]]+)/', $ua, $m)) $out['fblc'] = $m[1];
    if (preg_match('/FBOP\/([^;\]]+)/', $ua, $m)) $out['fbop'] = $m[1];

    // Browser
    if (stripos($ua, 'FBAN/FBIOS') !== false || stripos($ua, 'FBAN/FB4A') !== false) {
        $out['browser_name'] = 'Facebook In-App';
    } elseif (preg_match('/Instagram (\d+\.\d+)/', $ua)) {
        $out['browser_name'] = 'Instagram In-App';
    } elseif (preg_match('/Edg\/(\d+)/', $ua, $m)) { $out['browser_name'] = 'Edge'; $out['browser_version'] = $m[1]; }
    elseif (preg_match('/Chrome\/(\d+)/', $ua, $m)) { $out['browser_name'] = 'Chrome'; $out['browser_version'] = $m[1]; }
    elseif (preg_match('/Firefox\/(\d+)/', $ua, $m)) { $out['browser_name'] = 'Firefox'; $out['browser_version'] = $m[1]; }
    elseif (preg_match('/Version\/(\d+).*Safari/', $ua, $m)) { $out['browser_name'] = 'Safari'; $out['browser_version'] = $m[1]; }
    if (!$out['browser_version'] && $out['fbav']) $out['browser_version'] = $out['fbav'];

    // OS
    if ($out['ios_version']) {
        $out['os_name'] = stripos($ua, 'iPad') !== false ? 'iPadOS' : 'iOS';
        $out['os_version'] = $out['ios_version'];
    } elseif ($out['android_version']) {
        $out['os_name'] = 'Android';
        $out['os_version'] = $out['android_version'];
    } elseif (stripos($ua, 'Mac OS X') !== false) {
        if (preg_match('/Mac OS X (\d+[._]\d+(?:[._]\d+)?)/', $ua, $m)) $out['os_version'] = str_replace('_', '.', $m[1]);
        $out['os_name'] = 'macOS';
    } elseif (stripos($ua, 'Windows NT') !== false) {
        if (preg_match('/Windows NT ([\d.]+)/', $ua, $m)) $out['os_version'] = $m[1];
        $out['os_name'] = 'Windows';
    } elseif (stripos($ua, 'Linux') !== false) {
        $out['os_name'] = 'Linux';
    }

    // FBDV → model name
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
        'iPad13,16'=>'iPad Air (5th gen)','iPad13,17'=>'iPad Air (5th gen)','iPad14,1'=>'iPad mini (6th gen)',
        'iPad14,3'=>'iPad Pro 11" (4th gen)','iPad14,5'=>'iPad Pro 12.9" (6th gen)',
    ];
    if ($fbdv && isset($map[$fbdv])) return $map[$fbdv];
    if ($fbdv && stripos($fbdv, 'iPhone') === 0) return 'iPhone (' . $fbdv . ')';
    if ($fbdv && stripos($fbdv, 'iPad') === 0)   return 'iPad ('   . $fbdv . ')';
    if ($fbdv) return $fbdv;
    if (stripos($ua, 'iPhone') !== false) return 'iPhone';
    if (stripos($ua, 'iPad')   !== false) return 'iPad';
    if (preg_match('/Android.*; ([^;]+) Build\//', $ua, $m)) return trim($m[1]);
    return null;
}

// =====================================================================
// SECTION 4 — IPQS
// =====================================================================
function build_section4_ipqs(string $ip): array {
    $r = traffic_ipqs_lookup($ip);
    $blank = [
        'ip' => $ip,
        'ip_type' => null, 'isp' => null, 'asn' => null, 'organization' => null,
        'connection_type' => null, 'network_gen' => null,
        'city' => null, 'region' => null, 'country' => null, 'country_code' => null,
        'postal_code' => null, 'latitude' => null, 'longitude' => null, 'timezone_from_ip' => null,
        'is_proxy' => null, 'is_vpn' => null, 'is_tor' => null, 'is_crawler' => null, 'is_datacenter' => null,
        'fraud_score' => null, 'recent_abuse' => null, 'abuse_velocity' => null,
        'host' => null, 'mobile' => null,
    ];
    if (!$r) return $blank;
    if (!empty($r['is_local'])) {
        return array_merge($blank, ['country_code' => 'LOCAL', 'city' => 'Local']);
    }
    return [
        'ip' => $ip,
        'ip_type'        => $r['mobile'] ?? null ? 'Mobile' : ($r['is_datacenter'] ?? false ? 'Datacenter' : ($r['is_crawler'] ?? false ? 'Crawler' : 'Residential')),
        'isp'            => $r['ISP']        ?? $r['isp']         ?? null,
        'asn'            => $r['ASN']        ?? $r['asn']         ?? null,
        'organization'   => $r['organization']?? null,
        'connection_type'=> $r['connection_type'] ?? null,
        'network_gen'    => $r['network']    ?? null,
        'city'           => $r['city']       ?? null,
        'region'         => $r['region']     ?? null,
        'country'        => $r['country']    ?? $r['country_code'] ?? null,
        'country_code'   => $r['country_code']?? null,
        'postal_code'    => $r['zip_code']   ?? null,
        'latitude'       => $r['latitude']   ?? null,
        'longitude'      => $r['longitude']  ?? null,
        'timezone_from_ip'=> $r['timezone']  ?? null,
        'is_proxy'       => (bool)($r['proxy']        ?? false),
        'is_vpn'         => (bool)($r['vpn']          ?? false),
        'is_tor'         => (bool)($r['tor']          ?? false),
        'is_crawler'     => (bool)($r['is_crawler']   ?? false),
        'is_datacenter'  => (bool)($r['is_datacenter'] ?? false),
        'fraud_score'    => $r['fraud_score']  ?? null,
        'recent_abuse'   => (bool)($r['recent_abuse'] ?? false),
        'abuse_velocity' => $r['abuse_velocity'] ?? null,
        'host'           => $r['host']         ?? null,
        'mobile'         => (bool)($r['mobile']        ?? false),
    ];
}

// =====================================================================
// SECTION 8 server portion — UA-derived FB flags
// =====================================================================
function build_section8_server(array $s2, array $s3): array {
    $ua = $s2['user_agent'] ?? '';
    return [
        'ua_has_fban_fbios'  => stripos($ua, 'FBAN/FBIOS') !== false,
        'ua_has_fb4a'        => stripos($ua, 'FBAN/FB4A')  !== false,
        'ua_has_fbdv_iphone' => $s3['fbdv'] && stripos($s3['fbdv'], 'iPhone') === 0,
        'ua_has_fbdv_android'=> stripos($ua, 'FB4A') !== false,
        'ua_has_messenger'   => stripos($ua, 'Messenger') !== false || stripos($ua, 'FBAN/MessengerForiOS') !== false,
        'ua_has_instagram'   => stripos($ua, 'Instagram') !== false,
        'fb_capi_match'      => 'N/A',
    ];
}

// =====================================================================
// SECTION 9 server portion — header consistency
// =====================================================================
function build_section9_server(array $s2, array $s3): array {
    $isiOS = ($s3['os_name'] === 'iOS' || $s3['os_name'] === 'iPadOS');
    $isAndroid = ($s3['os_name'] === 'Android');
    $secCh = $s2['sec_ch_ua'] ?? 'absent';
    $secChMobile = $s2['sec_ch_ua_mobile'] ?? 'absent';

    $iosOk = $isiOS ? ($secCh === 'absent') : true;
    $androidOk = ($isAndroid && (stripos($s2['user_agent'] ?? '', 'Chrome') !== false))
        ? ($secCh !== 'absent')
        : true;

    return [
        'ios_webkit_consistency'    => $iosOk ? 'PASS' : 'FAIL',
        'sec_ch_ua_consistency'     => $androidOk ? 'PASS' : 'FAIL',
    ];
}

// =====================================================================
// VERDICT — 16 weighted checks, max 125
// =====================================================================
function compute_verdict(array $s1, array $s2, array $s3, array $s4, array $s5, array $s6, array $s7, array $s8, array $s9, string $domainKey): array {
    $checks = [];
    $points = 0;
    $total  = 125;

    $add = function (string $name, int $pts, bool $pass) use (&$checks, &$points) {
        $checks[] = ['name' => $name, 'points' => $pts, 'pass' => $pass];
        if ($pass) $points += $pts;
    };

    // 1. UA has FBAN/FBIOS or FB4A
    $add('ua_facebook_app', 10, !empty($s8['ua_has_fban_fbios']) || !empty($s8['ua_has_fb4a']));

    // 2. Sec-Ch-* absence/presence consistent with platform
    $add('sec_ch_consistency', 5, ($s9['ios_webkit_consistency'] ?? '') === 'PASS' && ($s9['sec_ch_ua_consistency'] ?? 'PASS') === 'PASS');

    // 3. Referer is FB redirect domain (or empty for in-app)
    $refOk = !empty($s8['referer_is_facebook']) || empty($s2['referer']) || $s2['referer'] === 'absent';
    $add('referer_facebook', 10, $refOk);

    // 4. fbclid format valid
    $add('fbclid_format', 10, !empty($s8['fbclid_format_valid']));

    // 5. IP not proxy/vpn/tor/datacenter
    $cleanIp = !($s4['is_proxy'] || $s4['is_vpn'] || $s4['is_tor'] || $s4['is_datacenter']);
    $add('ip_clean', 15, $cleanIp);

    // 6. IP country matches campaign _XX_ code
    $geo = $s8['campaign_geo_target'] ?? null;
    $cc = $s4['country_code'] ?? null;
    $add('country_matches_campaign', 15, $geo && $cc && strtoupper($geo) === strtoupper($cc));

    // 7. Timezone matches IP country
    $tzClient = $s5['timezone'] ?? '';
    $tzIp = $s4['timezone_from_ip'] ?? '';
    $add('timezone_matches_ip', 10, $tzClient && $tzIp && $tzClient === $tzIp);

    // 8. FBLC matches campaign target (lang prefix from utm_term/content or from geo)
    $fblc = strtolower($s3['fblc'] ?? '');
    $expectLang = $geo ? strtolower(geo_to_lang_prefix($geo)) : '';
    $add('fblc_matches_target', 10, $fblc && $expectLang && strpos($fblc, $expectLang) === 0);

    // 9. navigator.language matches campaign target
    $navLang = strtolower($s5['navigator_language'] ?? '');
    $add('nav_lang_matches_target', 5, $navLang && $expectLang && strpos($navLang, $expectLang) === 0);

    // 10. navigator.webdriver undefined
    $add('webdriver_undefined', 5, ($s5['navigator_webdriver'] ?? null) === 'undefined' || ($s5['navigator_webdriver'] ?? null) === false);

    // 11. maxTouchPoints == 5 for mobile UA, 0 for desktop
    $isMobile = ($s3['os_name'] === 'iOS' || $s3['os_name'] === 'iPadOS' || $s3['os_name'] === 'Android');
    $mtp = (int)($s5['navigator_max_touch_points'] ?? 0);
    $add('touch_points_match', 5, $isMobile ? $mtp >= 1 : $mtp === 0);

    // 12. WebGL real GPU
    $rend = strtolower(($s6['webgl_unmasked_renderer'] ?? '') . ' ' . ($s6['webgl_renderer'] ?? ''));
    $realGpu = $rend && !preg_match('/swiftshader|llvmpipe|mesa offscreen|software/i', $rend);
    $add('webgl_real_gpu', 5, $realGpu);

    // 13. Behavioral natural
    $tos = (float)($s7['time_on_page_s'] ?? 0);
    $sd  = (float)($s7['max_scroll_depth_pct'] ?? 0);
    $varied = (count($s7['scroll_velocity_samples'] ?? []) > 3) || (count($s7['mouse_velocity_samples'] ?? []) > 3) || (($s7['total_touch_events'] ?? 0) > 1);
    $add('behavior_natural', 10, $tos >= 30 && $sd >= 20 && $varied);

    // 14. Multi-finger events on mobile
    $add('multi_finger_mobile', 3, $isMobile ? ($s7['multi_finger_events_count'] ?? 0) > 0 : true);

    // 15. fbclid not seen in recent logs
    $add('fbclid_unique', 2, fbclid_recently_unique($s1['fbclid'] ?? '', $domainKey));

    // 16. Fraud score < 25
    $fs = $s4['fraud_score'];
    $add('fraud_score_low', 5, is_numeric($fs) && $fs < 25);

    $passed = count(array_filter($checks, function ($c) { return $c['pass']; }));
    $flags  = array_values(array_map(function ($c) { return $c['name']; }, array_filter($checks, function ($c) { return !$c['pass']; })));

    if ($points >= 95)      { $result = 'PASS'; $label = label_for_pass($s3, $s8, $s4); }
    elseif ($points >= 60)  { $result = 'SUSPICIOUS'; $label = 'NEEDS MANUAL REVIEW'; }
    else                    { $result = 'FAIL'; $label = 'LIKELY SPOOFED OR BOT'; }

    return [
        'result'         => $result,
        'label'          => $label,
        'risk_score'     => max(0, 100 - (int)round(($points / $total) * 100)),
        'checks_passed'  => $passed,
        'checks_total'   => count($checks),
        'points_earned'  => $points,
        'points_possible'=> $total,
        'flags'          => $flags,
        'checks'         => $checks,
    ];
}

function label_for_pass(array $s3, array $s8, array $s4): string {
    $os = $s3['os_name'] ?? '';
    $isFbApp = !empty($s8['ua_has_fban_fbios']) || !empty($s8['ua_has_fb4a']);
    $platform = $isFbApp ? 'FACEBOOK' : ($s8['referer_is_facebook'] ? 'FACEBOOK' : 'WEB');
    $device = ($os === 'iOS' || $os === 'iPadOS') ? 'iOS MOBILE'
            : ($os === 'Android' ? 'ANDROID MOBILE'
            : ($os === 'Windows' ? 'WINDOWS DESKTOP'
            : ($os === 'macOS' ? 'MAC DESKTOP' : 'UNKNOWN')));
    return "REAL $platform $device TRAFFIC";
}

function geo_to_lang_prefix(string $cc): string {
    static $map = [
        'US'=>'en','GB'=>'en','AU'=>'en','CA'=>'en','NZ'=>'en','IE'=>'en',
        'DE'=>'de','AT'=>'de','CH'=>'de','FR'=>'fr','BE'=>'fr',
        'ES'=>'es','MX'=>'es','AR'=>'es','CO'=>'es','CL'=>'es','PE'=>'es',
        'IT'=>'it','PT'=>'pt','BR'=>'pt','NL'=>'nl','PL'=>'pl','SE'=>'sv',
        'NO'=>'no','DK'=>'da','FI'=>'fi','JP'=>'ja','KR'=>'ko','CN'=>'zh',
        'RU'=>'ru','TR'=>'tr','IN'=>'en','PK'=>'en','ZA'=>'en',
    ];
    return $map[strtoupper($cc)] ?? 'en';
}

function fbclid_recently_unique(string $fbclid, string $domainKey): bool {
    if (!$fbclid) return false;
    $dir = TRAFFIC_LOGS_DIR . $domainKey . '/';
    if (!is_dir($dir)) return true;
    $files = @scandir($dir, SCANDIR_SORT_DESCENDING) ?: [];
    // fbclid is long + unique-looking (IwAR + 50-95 chars) — substring match is safe
    $needle = '"' . $fbclid . '"';
    $checked = 0;
    foreach ($files as $f) {
        if (substr($f, 0, 8) !== 'visitor_') continue;
        $full = @file_get_contents($dir . $f);
        if ($full !== false && strpos($full, $needle) !== false) return false;
        if (++$checked >= TRAFFIC_FBCLID_RECENT_SCAN) break;
    }
    return true;
}

// =====================================================================
// Rate limiting + log trim
// =====================================================================
function traffic_rate_check(string $file, int $limit, int $window): bool {
    $now = time();
    $arr = [];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        $arr = $raw ? array_filter(array_map('intval', explode(',', $raw)), function ($t) use ($now, $window) { return $t > $now - $window; }) : [];
    }
    if (count($arr) >= $limit) return false;
    $arr[] = $now;
    @file_put_contents($file, implode(',', $arr), LOCK_EX);
    return true;
}

function trim_logs(string $dir, int $cap): void {
    $files = @scandir($dir, SCANDIR_SORT_ASCENDING) ?: [];
    $files = array_values(array_filter($files, function ($f) { return substr($f, 0, 8) === 'visitor_'; }));
    if (count($files) <= $cap) return;
    $remove = count($files) - $cap;
    for ($i = 0; $i < $remove; $i++) @unlink($dir . $files[$i]);
}
