<?php
/**
 * Multi-Tenant Shaving Check Script (v3 — Cookie-Poll-Clear-Redirect)
 *
 * Loaded by product pages via: <script src="https://shaver.trustednutraproduct.com/check.php?d=DOMAIN_KEY">
 *
 * Flow:
 *  1) No aff_id or no shaver match → inject BG tracking normally
 *  2) Shaver match (remove)  → inject BG → wait cookies stable → clear all → redirect clean URL
 *  3) Shaver match (replace) → inject BG → wait cookies stable → clear all → redirect with replaced params
 */

header('Content-Type: application/javascript; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Database configuration
require_once __DIR__ . '/config.php';

// Shared geo helpers (also used by cdn/s.js.php)
require_once __DIR__ . '/lib/geo.php';

$domainKey = $_GET['d'] ?? '';
$sessions = [];
$domain = null;

if (empty($domainKey)) {
    echo "console.warn('[Shaver] No domain key provided in check.php?d=KEY');";
    exit;
}

try {
    $pdo = getDB();

    // Get domain config
    $stmt = $pdo->prepare("SELECT * FROM domains WHERE domain_key = ? AND status = 'active'");
    $stmt->execute([$domainKey]);
    $domain = $stmt->fetch();

    if (!$domain) {
        echo "console.warn('[Shaver] Domain not found or inactive: " . addslashes($domainKey) . "');";
        exit;
    }

    // Get active sessions for this domain
    $stmt = $pdo->prepare("SELECT id, aff_id, sub_id, mode, shave_mode, smart_skip_next, smart_last_traffic_id, replace_aff_id, replace_sub_id, cb_path_find, cb_path_replace FROM shaving_sessions WHERE domain_id = ? AND active = 1");
    $stmt->execute([$domain['id']]);
    $sessions = $stmt->fetchAll();

    // For smart sessions: pre-check if last shaved visitor converted (server-side)
    foreach ($sessions as &$s) {
        $s['smart_should_shave'] = true; // default: shave
        if ($s['shave_mode'] === 'smart' && $s['smart_skip_next'] && $s['smart_last_traffic_id']) {
            // Check if last shaved visitor reached upsell/thankyou
            $chk = $pdo->prepare("SELECT session_uuid, domain_id FROM affiliate_traffic WHERE id = ?");
            $chk->execute([$s['smart_last_traffic_id']]);
            $lastTraffic = $chk->fetch();
            if ($lastTraffic && $lastTraffic['session_uuid']) {
                $chk2 = $pdo->prepare("SELECT COUNT(*) FROM affiliate_traffic WHERE session_uuid = ? AND domain_id = ? AND page_type IN ('upsell','thankyou')");
                $chk2->execute([$lastTraffic['session_uuid'], $lastTraffic['domain_id']]);
                $converted = $chk2->fetchColumn() > 0;
                $s['smart_should_shave'] = $converted; // If converted, shave next; if not, skip next
            } else {
                $s['smart_should_shave'] = false; // No data yet, skip next
            }
        }
    }
    unset($s); // break reference

    // ====================================================================
    // PER-DOMAIN SHIPPING CONFIG (opt-in via shipping_enabled flag)
    // Only fetched/emitted when this domain has shipping_enabled=1.
    // Other domains get an empty array and zero new bytes in the payload.
    // ====================================================================
    $shippingEnabled = !empty($domain['shipping_enabled']) && (int)$domain['shipping_enabled'] === 1;
    $shippingRows = [];
    $visitorGeo = null;
    if ($shippingEnabled) {
        $stmt = $pdo->prepare(
            "SELECT country_code, country_name,
                    ship_1_bottle, ship_2_bottle, ship_3_bottle, ship_6_bottle
             FROM domain_shipping_config WHERE domain_id = ? ORDER BY country_name ASC"
        );
        $stmt->execute([$domain['id']]);
        $shippingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // If admin flipped the flag but never added rows, treat as disabled
        if (empty($shippingRows)) {
            $shippingEnabled = false;
        } else {
            $visitorGeo = tnp_detect_visitor_country();
        }
    }

} catch (PDOException $e) {
    echo "console.error('[Shaver] Database error');";
    exit;
}

// Prepare data for JavaScript
$domainId = (int)$domain['id'];
$sessionsJson = json_encode(array_map(function($s) {
    return [
        'id' => $s['id'],
        'affId' => $s['aff_id'],
        'subId' => $s['sub_id'] ?? '',
        'mode' => $s['mode'] ?? 'remove',
        'replaceMode' => ($s['mode'] === 'replace'),
        'cbReplaceMode' => ($s['mode'] === 'cb_replace'),
        'replaceAffId' => $s['replace_aff_id'] ?? '',
        'replaceSubId' => $s['replace_sub_id'] ?? '',
        'cbPathFind' => $s['cb_path_find'] ?? '',
        'cbPathReplace' => $s['cb_path_replace'] ?? '',
        'shaveMode' => $s['shave_mode'] ?? 'instant',
        'smartSkipNext' => (bool)$s['smart_skip_next'],
        'smartShouldShave' => (bool)$s['smart_should_shave']
    ];
}, $sessions));

$bgAccountId = addslashes($domain['bg_account_id'] ?? '');
$bgProductCodes = addslashes($domain['bg_product_codes'] ?? '');
$bgConversionToken = addslashes($domain['bg_conversion_token'] ?? '');
$platform = addslashes($domain['platform'] ?? 'buygoods');
$ds24ProductId = addslashes($domain['ds24_product_id'] ?? '');
$cbVendor = addslashes($domain['cb_vendor'] ?? '');

// Build API URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$apiUrl = $protocol . '://' . $host . $path . '/api.php';
?>
/**
 * Multi-Tenant Shaver v3 — Cookie-Poll-Clear-Redirect
 * Domain: <?php echo addslashes($domain['label']); ?> (<?php echo addslashes($domain['domain_url']); ?>)
 * Generated: <?php echo date('Y-m-d H:i:s'); ?>
 * Active Sessions: <?php echo count($sessions); ?>
 */
(function() {
    'use strict';

    // ============================================================
    // CONFIG (from PHP)
    // ============================================================
    var DOMAIN_ID = <?php echo $domainId; ?>;
    var DOMAIN_KEY = '<?php echo addslashes($domainKey); ?>';
    var sessions = <?php echo $sessionsJson; ?>;
    var API_URL = '<?php echo $apiUrl; ?>';
    var BG_ACCOUNT_ID = '<?php echo $bgAccountId; ?>';
    var BG_PRODUCT_CODES = '<?php echo $bgProductCodes; ?>';
    var BG_CONVERSION_TOKEN = '<?php echo $bgConversionToken; ?>';
    var PLATFORM = '<?php echo $platform; ?>';
    var DS24_PRODUCT_ID = '<?php echo $ds24ProductId; ?>';
    var CB_VENDOR = '<?php echo $cbVendor; ?>';

    // Silent mode — suppress all console output (enable via URL ?_shaver_debug=1)
    var _DEBUG = (window.location.search.indexOf('_shaver_debug=1') !== -1);
    var _log = _DEBUG ? console.log.bind(console) : function(){};

    // Timing config
    var STABLE_SECS    = 1.5;     // cookies must be stable for N seconds (BG beacon fires within ~1s on 5G)
    var MAX_WAIT_MS    = 8000;    // total timeout
    var POLL_MS        = 300;     // polling interval
    var SHAVER_FLAG    = '_shaver_cleaned';

    // ============================================================
    // CONSOLE LOGGING
    // ============================================================
    _log('%c[Shaver] Loaded', 'color:#3498db;font-weight:bold', sessions.length, 'active sessions for domain:', DOMAIN_KEY, '| Platform:', PLATFORM);
    if (PLATFORM === 'clickbank') {
        _log('%c[Shaver] ClickBank Mode', 'color:#e67e22;font-weight:bold', '| Vendor:', CB_VENDOR || 'Not set');
    } else if (PLATFORM === 'digistore24') {
        _log('%c[Shaver] DS24 Mode', 'color:#9b59b6;font-weight:bold', '| Product ID:', DS24_PRODUCT_ID || 'Not set');
    } else {
        _log('%c[Shaver] BuyGoods Mode', 'color:#3498db;font-weight:bold', '| Account:', BG_ACCOUNT_ID, '| Products:', BG_PRODUCT_CODES);
    }

    // ============================================================
    // LOOP PREVENTION — detect post-redirect clean visit
    // ============================================================
    var alreadyCleaned = false;
    var sstParam = (new URLSearchParams(window.location.search)).get('_sst') || '';
    try { alreadyCleaned = sessionStorage.getItem(SHAVER_FLAG) === '1'; } catch(e) {}
    if (alreadyCleaned || sstParam) {
        try { sessionStorage.removeItem(SHAVER_FLAG); } catch(e) {}
        _log('%c[Shaver] Post-redirect clean visit — normal BG tracking will load', 'color:#2ecc71;font-weight:bold');

        // Restore scroll position so user doesn't jump to top
        try {
            var savedScrollY = sessionStorage.getItem('_shaver_scrollY');
            if (savedScrollY) {
                sessionStorage.removeItem('_shaver_scrollY');
                var scrollTarget = parseInt(savedScrollY, 10);
                if (scrollTarget > 0) {
                    // Restore immediately + after DOM ready (in case page hasn't rendered yet)
                    window.scrollTo(0, scrollTarget);
                    document.addEventListener('DOMContentLoaded', function() { window.scrollTo(0, scrollTarget); });
                    // Also try after images/assets load in case layout shifts
                    window.addEventListener('load', function() { setTimeout(function() { window.scrollTo(0, scrollTarget); }, 50); });
                }
            }
        } catch(e) {}

        // Clean _sst param from URL silently (so BG never sees it)
        if (sstParam) {
            var cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete('_sst');
            try { history.replaceState(null, '', cleanUrl.toString()); } catch(e) {}
        }

        // Load pre-redirect engagement snapshot so it gets sent with first metrics update
        try {
            var preEngStr = sessionStorage.getItem('_shaver_pre_engagement');
            if (preEngStr) {
                sessionStorage.removeItem('_shaver_pre_engagement');
                window.__behaviorTracking.preRedirectEngagement = JSON.parse(preEngStr);
                _log('%c[Shaver] Loaded pre-redirect engagement snapshot', 'color:#9b59b6;font-weight:bold', window.__behaviorTracking.preRedirectEngagement);
            }
        } catch(e) {}

        // Send AFTER snapshot to server after BG sets new cookies (matched by snap_token)
        var capturedToken = sstParam;
        setTimeout(function() { sendAfterSnapshot(capturedToken); }, 5000);
        _log('%c[Shaver] Will send AFTER snapshot in 5s (token-matched on server)', 'color:#9b59b6;font-weight:bold');
    }

    // ============================================================
    // Anti-copy & iframe protection removed — all traffic allowed through

    // ============================================================
    // URL PARAMETER PARSING
    // ============================================================
    function getUrlParams() {
        var params = {};
        var search = window.location.search.substring(1);
        if (!search) return params;
        var pairs = search.split('&');
        for (var i = 0; i < pairs.length; i++) {
            var pair = pairs[i].split('=');
            var key = decodeURIComponent(pair[0]);
            var value = pair[1] ? decodeURIComponent(pair[1]) : '';
            params[key] = value;
        }
        return params;
    }

    // ============================================================
    // PAGE TYPE DETECTION (landing / upsell / thankyou)
    // ============================================================
    function detectPageType() {
        var path = window.location.pathname.toLowerCase();
        if (path.indexOf('/upsell') !== -1) return 'upsell';
        if (path.indexOf('/thankyou') !== -1 || path.indexOf('/thank-you') !== -1 ||
            path.indexOf('/thank_you') !== -1 || path.indexOf('/confirmation') !== -1 ||
            path.indexOf('/order-confirmation') !== -1) return 'thankyou';
        return 'landing';
    }
    var PAGE_TYPE = detectPageType();
    if (PAGE_TYPE !== 'landing') {
        _log('%c[Shaver] Page Type: ' + PAGE_TYPE.toUpperCase(), 'color:#2ecc71;font-weight:bold;font-size:12px');
    }

    // ============================================================
    // HASH PARAMETER PARSING (for DS24 #aff=username)
    // ============================================================
    function getHashParams() {
        var params = {};
        var hash = window.location.hash.substring(1);
        if (!hash) return params;
        var pairs = hash.split('&');
        for (var i = 0; i < pairs.length; i++) {
            var pair = pairs[i].split('=');
            var key = decodeURIComponent(pair[0]);
            var value = pair[1] ? decodeURIComponent(pair[1]) : '';
            params[key] = value;
        }
        return params;
    }

    // ============================================================
    // SESSION MATCHING
    // ============================================================
    function findSession(affId, subId) {
        var affLower = (affId || '').toLowerCase();
        var subLower = (subId || '').toLowerCase();
        for (var i = 0; i < sessions.length; i++) {
            var s = sessions[i];
            if ((s.affId || '').toLowerCase() === affLower) {
                if (s.subId && (s.subId || '').toLowerCase() !== subLower) continue;
                return s;
            }
        }
        return null;
    }

    // ============================================================
    // COOKIE UTILITIES (from test.html pattern)
    // ============================================================
    function cookieCount() {
        return document.cookie ? document.cookie.split(';').filter(function(c) { return c.trim(); }).length : 0;
    }

    function getDomains() {
        var h = window.location.hostname;
        var parts = h.split('.');
        var domains = ['', h, '.' + h];
        for (var i = 1; i < parts.length - 1; i++) {
            var d = parts.slice(i).join('.');
            domains.push(d, '.' + d);
        }
        var seen = {};
        return domains.filter(function(d) {
            if (seen[d]) return false;
            seen[d] = true;
            return true;
        });
    }

    function clearAllCookies() {
        var domains = getDomains();
        var paths = ['/', window.location.pathname, ''];
        var exp = 'Thu, 01 Jan 1970 00:00:00 UTC';
        var cookies = document.cookie.split(';');
        var cleared = 0;
        for (var ci = 0; ci < cookies.length; ci++) {
            var name = cookies[ci].split('=')[0].trim();
            if (!name) continue;
            for (var di = 0; di < domains.length; di++) {
                for (var pi = 0; pi < paths.length; pi++) {
                    document.cookie = name + '=; expires=' + exp + '; path=' + paths[pi] + (domains[di] ? '; domain=' + domains[di] : '');
                }
            }
            cleared++;
        }
        var remaining = cookieCount();
        _log('[Shaver] Cleared ' + cleared + ' cookies. ' + remaining + ' survived.');
        if (remaining > 0) {
            document.cookie.split(';').forEach(function(c) {
                var k = c.split('=')[0].trim();
                if (k) _log('%c[Shaver] SURVIVED: ' + k, 'color:#e74c3c');
            });
        }
        return remaining;
    }

    // ============================================================
    // DS24 COOKIE / STORAGE CLEARING
    // ============================================================
    function clearDS24Cookies() {
        var cookies = document.cookie.split(';');
        var clearedCount = 0;
        for (var i = 0; i < cookies.length; i++) {
            var cookie = cookies[i].trim();
            var name = cookie.split('=')[0];
            var nameLower = name.toLowerCase();
            if (nameLower.indexOf('digistore') !== -1 || nameLower.indexOf('ds24') !== -1 ||
                nameLower.indexOf('digi_') !== -1 || nameLower.indexOf('digistoreaff') !== -1) {
                clearedCount++;
                var hostname = window.location.hostname;
                var parts = hostname.split('.');
                for (var j = 0; j < parts.length - 1; j++) {
                    var domain = '.' + parts.slice(j).join('.');
                    document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; domain=' + domain;
                }
                document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
            }
        }
        if (clearedCount > 0) _log('%c[Shaver] DS24 Cookies Cleared: ' + clearedCount, 'color:#e74c3c;font-weight:bold');
    }

    function clearDS24Storage() {
        try {
            var keysToRemove = [];
            for (var i = 0; i < localStorage.length; i++) {
                var key = localStorage.key(i);
                if (key) {
                    var kl = key.toLowerCase();
                    if (kl.indexOf('digistore') !== -1 || kl.indexOf('ds24') !== -1 || kl.indexOf('digi_') !== -1) {
                        keysToRemove.push(key);
                    }
                }
            }
            keysToRemove.forEach(function(k) { localStorage.removeItem(k); });

            var sessionKeysToRemove = [];
            for (var i = 0; i < sessionStorage.length; i++) {
                var key = sessionStorage.key(i);
                if (key && key !== SHAVER_FLAG) {
                    var kl = key.toLowerCase();
                    if (kl.indexOf('digistore') !== -1 || kl.indexOf('ds24') !== -1 || kl.indexOf('digi_') !== -1) {
                        sessionKeysToRemove.push(key);
                    }
                }
            }
            sessionKeysToRemove.forEach(function(k) { sessionStorage.removeItem(k); });
            if (keysToRemove.length > 0 || sessionKeysToRemove.length > 0) {
                _log('%c[Shaver] DS24 Storage Cleared', 'color:#e74c3c;font-weight:bold');
            }
        } catch (e) {}
    }

    // ============================================================
    // COOKIE READER (required by BuyGoods)
    // ============================================================
    function ReadCookie(name) {
        name += '=';
        var parts = document.cookie.split(/;\s*/);
        for (var i = 0; i < parts.length; i++) {
            var part = parts[i];
            if (part.indexOf(name) === 0) return part.substring(name.length);
        }
        return '';
    }
    window.ReadCookie = ReadCookie;

    // ============================================================
    // TRACKING FUNCTIONS
    // ============================================================
    function trackVisit(session, affId, subId) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', API_URL, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify({
            action: 'track_visit',
            session_id: session.id,
            domain_id: DOMAIN_ID,
            aff_id: affId,
            sub_id: subId,
            page: window.location.href,
            referrer: document.referrer || 'direct'
        }));
    }

    function logTraffic(affId, subId, wasShaved, shavingSessionId, source, smartSkipped) {
        var trafficSource = source || document.referrer || 'direct';
        var sessid2Val = ReadCookie('sessid2');
        if (!sessid2Val) {
            var sp = new URLSearchParams(window.location.search);
            sessid2Val = sp.get('sessid2') || sp.get('sessid') || '';
        }

        var payload = JSON.stringify({
            action: 'log_traffic',
            domain_id: DOMAIN_ID,
            aff_id: affId || '',
            sub_id: subId,
            page_url: window.location.href,
            page_type: PAGE_TYPE,
            sessid2: sessid2Val || null,
            referrer: trafficSource,
            user_agent: navigator.userAgent,
            was_shaved: wasShaved,
            smart_skipped: smartSkipped || false,
            shaving_session_id: shavingSessionId,
            session_uuid: window.__behaviorTracking ? window.__behaviorTracking.sessionUUID : null,
            screen_width: window.screen.width,
            screen_height: window.screen.height,
            viewport_width: window.innerWidth,
            viewport_height: window.innerHeight,
            is_bot: window.__behaviorTracking ? window.__behaviorTracking.isBot : 0,
            bot_flags: window.__behaviorTracking ? window.__behaviorTracking.botFlags : null,
            is_iframe: window.__behaviorTracking ? window.__behaviorTracking.isIframe : 0
        });

        var xhr = new XMLHttpRequest();
        xhr.open('POST', API_URL, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.timeout = 5000;
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var result = JSON.parse(xhr.responseText);
                    if (result.success && result.traffic_id && window.__behaviorTracking) {
                        window.__behaviorTracking.trafficId = result.traffic_id;
                        window.__behaviorTracking.trafficLogged = true;
                        if (window.__behaviorTracking.eventQueue.length > 0) {
                            window.__behaviorTracking.eventQueue.forEach(function(event) {
                                logBehaviorEvent(event.eventType, event.eventData);
                            });
                            window.__behaviorTracking.eventQueue = [];
                        }
                    }
                } catch (e) {}
            }
        };
        xhr.send(payload);

        window.__pendingTrafficPayload = payload;
        window.addEventListener('beforeunload', function __trafficFallback() {
            if (!window.__behaviorTracking || !window.__behaviorTracking.trafficLogged) {
                if (navigator.sendBeacon) navigator.sendBeacon(API_URL, window.__pendingTrafficPayload);
            }
            window.removeEventListener('beforeunload', __trafficFallback);
        });

        // If sessid2 was empty, start polling for BG cookie and patch the record
        if (!sessid2Val && PLATFORM === 'buygoods') {
            relogSessid2();
        }
    }

    // Delayed sessid2 update — polls for BG cookie and patches the traffic row
    function relogSessid2() {
        var attempts = 0;
        var maxAttempts = 10; // 10 x 500ms = 5 seconds
        var interval = setInterval(function() {
            attempts++;
            var sessid2 = ReadCookie('sessid2');
            if (sessid2) {
                clearInterval(interval);
                var trafficId = window.__behaviorTracking ? window.__behaviorTracking.trafficId : null;
                var sessionUUID = window.__behaviorTracking ? window.__behaviorTracking.sessionUUID : null;
                if (trafficId || sessionUUID) {
                    var payload = JSON.stringify({
                        action: 'update_sessid2',
                        traffic_id: trafficId,
                        session_uuid: sessionUUID,
                        sessid2: sessid2
                    });
                    if (navigator.sendBeacon) {
                        navigator.sendBeacon(API_URL, payload);
                    } else {
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', API_URL, true);
                        xhr.setRequestHeader('Content-Type', 'application/json');
                        xhr.send(payload);
                    }
                    _log('[Shaver] sessid2 updated: ' + sessid2);
                }
            } else if (attempts >= maxAttempts) {
                clearInterval(interval);
                _log('[Shaver] sessid2 cookie never appeared after 5s');
            }
        }, 500);
    }

    function trackClick(session, affId, subId) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', API_URL, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify({
            action: 'track_click',
            session_id: session.id,
            domain_id: DOMAIN_ID,
            aff_id: affId,
            sub_id: subId,
            page: window.location.href
        }));
    }

    // ============================================================
    // SMART SHAVING STATE UPDATE
    // ============================================================
    function updateSmartState(sessionId, smartAction, trafficId) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', API_URL, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify({
            action: 'update_smart_state',
            session_id: sessionId,
            smart_action: smartAction, // 'shaved' or 'skipped'
            traffic_id: trafficId || null
        }));
        _log('[Shaver] Smart state updated:', smartAction, 'for session:', sessionId);
    }

    // ============================================================
    // BEHAVIOR TRACKING SYSTEM
    // ============================================================
    function getSessionUUID() {
        var uuid = null;
        try { uuid = sessionStorage.getItem('_behavior_session_id'); } catch (e) {}
        if (!uuid) {
            uuid = 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            try { sessionStorage.setItem('_behavior_session_id', uuid); } catch (e) {}
        }
        return uuid;
    }

    window.__behaviorTracking = {
        sessionUUID: getSessionUUID(),
        trafficId: null,
        landedAt: Date.now(),
        maxScrollDepth: 0,
        clickCount: 0,
        redirectClicks: 0,
        buynowClicks: 0,
        footerClicks: 0,
        videoPlays: 0,
        ctaBarClicks: 0,
        vslClicks: 0,
        clickPositions: [],
        hasReachedCheckout: false,
        eventQueue: [],
        isTabVisible: true,
        lastScrollTime: 0,
        firstClickTime: null,
        checkoutTime: null,
        checkoutUrl: null,
        pageLoadTime: window.performance ? (window.performance.timing.loadEventEnd - window.performance.timing.navigationStart) : null,
        isBot: 0,
        botFlags: null,
        isIframe: 0,
        hasAdblock: null,
        jsErrorCount: 0,
        jsErrors: [],
        preRedirectEngagement: null
    };

    // Bot detection
    (function() {
        var strongFlags = [];
        var weakFlags = [];
        if (navigator.webdriver) strongFlags.push('webdriver');
        if (/HeadlessChrome/.test(navigator.userAgent)) strongFlags.push('headless_chrome');
        if (window.callPhantom || window._phantom) strongFlags.push('phantomjs');
        if (navigator.plugins && navigator.plugins.length === 0) weakFlags.push('no_plugins');
        if (!navigator.languages || navigator.languages.length === 0) weakFlags.push('no_languages');
        if (/Chrome/.test(navigator.userAgent) && !window.chrome) weakFlags.push('missing_chrome');
        var allFlags = strongFlags.concat(weakFlags);
        // Confirmed bot = any strong signal OR 2+ weak signals together
        window.__behaviorTracking.isBot = (strongFlags.length > 0 || weakFlags.length >= 2) ? 1 : 0;
        window.__behaviorTracking.botFlags = allFlags.length > 0 ? allFlags.join(',') : null;
    })();

    // Iframe detection
    try { window.__behaviorTracking.isIframe = (window.self !== window.top) ? 1 : 0; }
    catch (e) { window.__behaviorTracking.isIframe = 1; }

    // Ad blocker detection
    function detectAdBlocker() {
        var bait = document.createElement('div');
        bait.id = 'ad-banner';
        bait.className = 'ad ads adsbox ad-placement doubleclick';
        bait.style.cssText = 'position:absolute;top:-10px;left:-10px;width:1px;height:1px;overflow:hidden;';
        bait.innerHTML = '&nbsp;';
        document.body.appendChild(bait);
        setTimeout(function() {
            var blocked = 0;
            try {
                if (!bait.offsetParent || bait.offsetHeight === 0 || bait.clientHeight === 0 ||
                    getComputedStyle(bait).display === 'none' || getComputedStyle(bait).visibility === 'hidden') {
                    blocked = 1;
                }
            } catch(e) { blocked = 1; }
            window.__behaviorTracking.hasAdblock = blocked;
            if (bait.parentNode) bait.parentNode.removeChild(bait);
        }, 150);
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', detectAdBlocker); }
    else { detectAdBlocker(); }

    // JS error tracking
    window.addEventListener('error', function(e) {
        window.__behaviorTracking.jsErrorCount++;
        if (window.__behaviorTracking.jsErrors.length < 5) {
            window.__behaviorTracking.jsErrors.push({ msg: (e.message || '').substring(0, 200), src: (e.filename || '').substring(0, 150), line: e.lineno || 0 });
        }
    });
    window.addEventListener('unhandledrejection', function(e) {
        window.__behaviorTracking.jsErrorCount++;
        if (window.__behaviorTracking.jsErrors.length < 5) {
            var reason = ''; try { reason = String(e.reason).substring(0, 200); } catch(ex) { reason = 'unknown'; }
            window.__behaviorTracking.jsErrors.push({ msg: reason, src: 'promise', line: 0 });
        }
    });

    function logBehaviorEvent(eventType, eventData) {
        if (!window.__behaviorTracking.trafficId) {
            window.__behaviorTracking.eventQueue.push({ eventType: eventType, eventData: eventData, timestamp: Date.now() });
            return;
        }
        var xhr = new XMLHttpRequest();
        xhr.open('POST', API_URL, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify({
            action: 'log_behavior_event', domain_id: DOMAIN_ID,
            traffic_id: window.__behaviorTracking.trafficId,
            session_uuid: window.__behaviorTracking.sessionUUID,
            event_type: eventType, event_data: eventData,
            timestamp: new Date().toISOString()
        }));
    }

    function updateSessionMetrics() {
        if (!window.__behaviorTracking.trafficId) return;
        var sessionDuration = Math.floor((Date.now() - window.__behaviorTracking.landedAt) / 1000);
        var payload = {
            action: 'update_session_metrics',
            traffic_id: window.__behaviorTracking.trafficId,
            session_duration: sessionDuration,
            max_scroll_depth: window.__behaviorTracking.maxScrollDepth,
            total_clicks: window.__behaviorTracking.clickCount,
            redirect_clicks: window.__behaviorTracking.redirectClicks,
            buynow_clicks: window.__behaviorTracking.buynowClicks,
            footer_clicks: window.__behaviorTracking.footerClicks,
            video_plays: window.__behaviorTracking.videoPlays,
            video_watch_time: window.__behaviorTracking.videoWatchTime || 0,
            magical_revealed: window.__behaviorTracking.magicalRevealed || 0,
            cta_bar_clicks: window.__behaviorTracking.ctaBarClicks,
            vsl_clicks: window.__behaviorTracking.vslClicks,
            click_positions: window.__behaviorTracking.clickPositions.length > 0 ? JSON.stringify(window.__behaviorTracking.clickPositions) : null,
            reached_checkout: window.__behaviorTracking.buynowClicks > 0 ? 1 : 0,
            checkout_url: window.__behaviorTracking.checkoutUrl || null,
            time_to_first_click: window.__behaviorTracking.firstClickTime ? Math.floor((window.__behaviorTracking.firstClickTime - window.__behaviorTracking.landedAt) / 1000) : null,
            time_to_checkout: window.__behaviorTracking.checkoutTime ? Math.floor((window.__behaviorTracking.checkoutTime - window.__behaviorTracking.landedAt) / 1000) : null,
            screen_width: window.screen.width, screen_height: window.screen.height,
            viewport_width: window.innerWidth, viewport_height: window.innerHeight,
            page_load_time: window.__behaviorTracking.pageLoadTime,
            bounce: window.__behaviorTracking.clickCount === 0 ? 1 : 0,
            has_adblock: window.__behaviorTracking.hasAdblock,
            js_error_count: window.__behaviorTracking.jsErrorCount,
            js_errors: window.__behaviorTracking.jsErrors.length > 0 ? JSON.stringify(window.__behaviorTracking.jsErrors) : null
        };
        // Include pre-redirect engagement if available (sent once after redirect)
        if (window.__behaviorTracking.preRedirectEngagement) {
            payload.pre_redirect_engagement = JSON.stringify(window.__behaviorTracking.preRedirectEngagement);
            payload.redirect_time_ms = window.__behaviorTracking.preRedirectEngagement.redirectTimeMs || null;
            window.__behaviorTracking.preRedirectEngagement = null; // send only once
        }
        var xhr = new XMLHttpRequest();
        xhr.open('POST', API_URL, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify(payload));
    }

    // Scroll tracking
    function setupScrollTracking() {
        var scrollTimeout, lastDepth = 0;
        window.addEventListener('scroll', function() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(function() {
                var scrollY = window.scrollY || window.pageYOffset;
                var docHeight = document.documentElement.scrollHeight;
                var viewportHeight = window.innerHeight;
                var scrollDepth = Math.min(100, Math.floor(((scrollY + viewportHeight) / docHeight) * 100));
                if (scrollDepth > window.__behaviorTracking.maxScrollDepth) {
                    window.__behaviorTracking.maxScrollDepth = scrollDepth;
                    if (scrollDepth >= 25 && lastDepth < 25) logBehaviorEvent('scroll', {scrollDepth: 25, milestone: true});
                    else if (scrollDepth >= 50 && lastDepth < 50) logBehaviorEvent('scroll', {scrollDepth: 50, milestone: true});
                    else if (scrollDepth >= 75 && lastDepth < 75) logBehaviorEvent('scroll', {scrollDepth: 75, milestone: true});
                    else if (scrollDepth >= 90 && lastDepth < 90) logBehaviorEvent('scroll', {scrollDepth: 90, milestone: true});
                    lastDepth = scrollDepth;
                }
            }, 300);
        });
    }

    // Click tracking — categorizes into redirect/buynow/ctabar/vsl + captures positions for heatmap
    function classifyClick(target) {
        var href = target.href || target.getAttribute('href') || '';

        // Check if inside a CTA bar (sticky desktop bar, mobile toast, desktop toast)
        var node = target;
        while (node && node !== document.body) {
            if (node.id === 'sticky-cta-desktop' || node.id === 'mobile-toast' || node.id === 'desktop-toast') {
                // VSL links inside CTA bars count as VSL, not ctabar
                if (href.indexOf('/vsl') !== -1 || target.classList.contains('desktop-toast-vsl') || target.classList.contains('mobile-toast-btn-secondary')) return 'vsl';
                // Close buttons don't count
                if (target.classList.contains('sticky-cta-close') || target.classList.contains('mobile-toast-close') || target.classList.contains('desktop-toast-close')) return 'other';
                return 'ctabar';
            }
            node = node.parentElement;
        }

        // VSL redirect links anywhere on the page
        if (target.classList.contains('vsl-redirect-link') || href.indexOf('/vsl') !== -1) return 'vsl';

        // BuyNow = actual checkout links (exclude offer-details / backoffice pages)
        if (href && href.indexOf('backoffice.buygoods.com') === -1 && href.indexOf('offer-details') === -1 &&
            (href.indexOf('buygoods.com') !== -1 || href.indexOf('checkout-ds24.com') !== -1 ||
            href.indexOf('digistore24.com') !== -1 || href.indexOf('clickbank.net') !== -1 ||
            href.indexOf('pay.clickbank.com') !== -1)) return 'buynow';

        // Redirect = hash anchor links (#bottomorder, #buynow, etc.)
        if (href && href.charAt(0) === '#') return 'redirect';
        if (href && href.indexOf('#') !== -1) {
            var hashPart = href.substring(href.indexOf('#'));
            if (hashPart.length > 1) return 'redirect';
        }
        return 'other';
    }

    function setupDetailedClickTracking() {
        document.addEventListener('click', function(e) {
            var target = e.target;
            while (target && target !== document.body) {
                if (target.tagName === 'A' || target.tagName === 'BUTTON' ||
                    (target.classList && (target.classList.contains('cp-btn') || target.classList.contains('mt-buy-now-btn')))) {
                    var clickType = classifyClick(target);
                    window.__behaviorTracking.clickCount++;
                    if (clickType === 'redirect') window.__behaviorTracking.redirectClicks++;
                    else if (clickType === 'buynow') window.__behaviorTracking.buynowClicks++;
                    else if (clickType === 'ctabar') window.__behaviorTracking.ctaBarClicks++;
                    else if (clickType === 'vsl') window.__behaviorTracking.vslClicks++;
                    if (!window.__behaviorTracking.firstClickTime) window.__behaviorTracking.firstClickTime = Date.now();

                    // Capture page-relative position for heatmap (max 50 positions)
                    var pageX = e.pageX || (e.clientX + (window.scrollX || window.pageXOffset || 0));
                    var pageY = e.pageY || (e.clientY + (window.scrollY || window.pageYOffset || 0));
                    if (window.__behaviorTracking.clickPositions.length < 50) {
                        window.__behaviorTracking.clickPositions.push({
                            x: pageX, y: pageY,
                            vpX: e.clientX, vpY: e.clientY,
                            type: clickType,
                            pw: document.documentElement.scrollWidth,
                            ph: document.documentElement.scrollHeight,
                            t: Math.floor((Date.now() - window.__behaviorTracking.landedAt) / 1000)
                        });
                    }

                    var buttonText = target.textContent ? target.textContent.trim() : '';
                    logBehaviorEvent('click', {
                        buttonText: buttonText.substring(0, 100), buttonId: target.id || '',
                        targetUrl: (target.href || '').substring(0, 200),
                        clickType: clickType,
                        clickX: e.clientX, clickY: e.clientY,
                        pageX: pageX, pageY: pageY,
                        pageWidth: document.documentElement.scrollWidth,
                        pageHeight: document.documentElement.scrollHeight,
                        scrollDepthAtClick: window.__behaviorTracking.maxScrollDepth,
                        timeFromLanding: Math.floor((Date.now() - window.__behaviorTracking.landedAt) / 1000)
                    });
                    break;
                }
                target = target.parentElement;
            }
        });
    }

    // Hover tracking on buy buttons
    function setupHoverTracking() {
        var hoverStartTime = null, hoveredButton = null;
        document.addEventListener('mouseover', function(e) {
            var target = e.target;
            while (target && target !== document.body) {
                if (target.classList && (target.classList.contains('cp-btn') || target.classList.contains('mt-buy-now-btn'))) {
                    hoverStartTime = Date.now(); hoveredButton = target; break;
                }
                target = target.parentElement;
            }
        });
        document.addEventListener('mouseout', function(e) {
            if (hoveredButton && hoverStartTime) {
                var duration = Date.now() - hoverStartTime;
                if (duration > 500) logBehaviorEvent('hover', { element: 'buy-btn', buttonText: (hoveredButton.textContent || '').trim().substring(0, 100), duration: duration });
                hoverStartTime = null; hoveredButton = null;
            }
        });
    }

    // Video play tracking — counts user-initiated plays on .mv-player videos
    function setupVideoTracking() {
        var tracked = {};
        document.querySelectorAll('.mv-player video').forEach(function(video) {
            video.addEventListener('play', function() {
                // Skip autoplay (muted autoplay doesn't count)
                if (video.muted && !video.dataset.userPlayed) return;
                var videoId = (video.closest('.mv-player') || {}).dataset ? video.closest('.mv-player').dataset.videoId || 'unknown' : 'unknown';
                window.__behaviorTracking.videoPlays++;
                if (!tracked[videoId]) {
                    tracked[videoId] = true;
                    logBehaviorEvent('video_play', { videoId: videoId, timeFromLanding: Math.floor((Date.now() - window.__behaviorTracking.landedAt) / 1000) });
                }
            });
            // Mark user-initiated unmute as user-played
            video.addEventListener('volumechange', function() {
                if (!video.muted) video.dataset.userPlayed = '1';
            });
        });
        // Also track clicks on play overlays (unmute buttons trigger user play)
        document.querySelectorAll('.mv-unmute-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var player = btn.closest('.mv-player');
                if (player) {
                    var vid = player.querySelector('video');
                    if (vid) vid.dataset.userPlayed = '1';
                }
            });
        });
    }

    // Checkout detection
    function setupCheckoutDetection() {
        var originalPushState = history.pushState;
        if (originalPushState) {
            history.pushState = function() {
                if (arguments[2]) checkIfCheckoutReached(arguments[2]);
                return originalPushState.apply(history, arguments);
            };
        }
        window.addEventListener('popstate', function() { checkIfCheckoutReached(window.location.href); });
        document.addEventListener('click', function(e) {
            var target = e.target;
            while (target && target !== document.body) {
                if (target.href && target.href.indexOf('backoffice.buygoods.com') === -1 && target.href.indexOf('offer-details') === -1 &&
                    (target.href.indexOf('buygoods.com') !== -1 || target.href.indexOf('checkout-ds24.com') !== -1 || target.href.indexOf('digistore24.com') !== -1 || target.href.indexOf('clickbank.net') !== -1 || target.href.indexOf('pay.clickbank.com') !== -1)) {
                    // Always update checkout URL to capture the latest clicked package
                    window.__behaviorTracking.checkoutUrl = target.href;
                    window.__behaviorTracking.checkoutTime = Date.now();
                    if (!window.__behaviorTracking.hasReachedCheckout) {
                        window.__behaviorTracking.hasReachedCheckout = true;
                        logBehaviorEvent('checkout_reached', {
                            checkoutUrl: target.href.substring(0, 200),
                            clickType: 'buynow',
                            timeToCheckout: Math.floor((Date.now() - window.__behaviorTracking.landedAt) / 1000),
                            scrollDepthAtCheckout: window.__behaviorTracking.maxScrollDepth,
                            clicksBeforeCheckout: window.__behaviorTracking.clickCount
                        });
                    }
                    // Immediately flush checkout_url via beacon before page navigates away
                    try {
                        navigator.sendBeacon(API_URL + '?action=update_session_metrics', JSON.stringify({
                            traffic_id: window.__behaviorTracking.trafficId,
                            buynow_clicks: window.__behaviorTracking.buynowClicks,
                            reached_checkout: 1,
                            checkout_url: target.href
                        }));
                    } catch(ex) {}
                    break;
                }
                target = target.parentElement;
            }
        });
    }
    function checkIfCheckoutReached(url) {
        if (url && url.indexOf('backoffice.buygoods.com') === -1 && url.indexOf('offer-details') === -1 &&
            (url.indexOf('buygoods.com') !== -1 || url.indexOf('checkout-ds24.com') !== -1 || url.indexOf('digistore24.com') !== -1 || url.indexOf('clickbank.net') !== -1 || url.indexOf('pay.clickbank.com') !== -1) && !window.__behaviorTracking.hasReachedCheckout) {
            window.__behaviorTracking.hasReachedCheckout = true;
            window.__behaviorTracking.checkoutUrl = url;
            window.__behaviorTracking.checkoutTime = Date.now();
            logBehaviorEvent('checkout_reached', { checkoutUrl: url.substring(0, 200), timeToCheckout: Math.floor((Date.now() - window.__behaviorTracking.landedAt) / 1000) });
        }
    }

    // Tab visibility
    function setupTabVisibilityTracking() {
        var visibleStart = Date.now();
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                logBehaviorEvent('tab_hidden', { hidden: true, visibleDuration: Date.now() - visibleStart });
                window.__behaviorTracking.isTabVisible = false;
            } else {
                logBehaviorEvent('tab_visible', { hidden: false });
                window.__behaviorTracking.isTabVisible = true;
                visibleStart = Date.now();
            }
        });
    }

    // Before unload
    function setupBeforeUnload() {
        window.addEventListener('beforeunload', function() {
            updateSessionMetrics();
            if (navigator.sendBeacon && window.__behaviorTracking.trafficId) {
                navigator.sendBeacon(API_URL, JSON.stringify({
                    action: 'update_session_metrics',
                    traffic_id: window.__behaviorTracking.trafficId,
                    session_duration: Math.floor((Date.now() - window.__behaviorTracking.landedAt) / 1000),
                    max_scroll_depth: window.__behaviorTracking.maxScrollDepth,
                    total_clicks: window.__behaviorTracking.clickCount,
                    redirect_clicks: window.__behaviorTracking.redirectClicks,
                    buynow_clicks: window.__behaviorTracking.buynowClicks,
                    footer_clicks: window.__behaviorTracking.footerClicks,
                    click_positions: window.__behaviorTracking.clickPositions.length > 0 ? JSON.stringify(window.__behaviorTracking.clickPositions) : null,
                    reached_checkout: window.__behaviorTracking.buynowClicks > 0 ? 1 : 0,
                    has_adblock: window.__behaviorTracking.hasAdblock,
                    js_error_count: window.__behaviorTracking.jsErrorCount,
                    js_errors: window.__behaviorTracking.jsErrors.length > 0 ? JSON.stringify(window.__behaviorTracking.jsErrors) : null
                }));
            }
        });
    }

    setInterval(updateSessionMetrics, 30000);

    // ── Vidalytics Video Watch Time Tracking ──
    function setupVideoWatchTracking() {
        window.__behaviorTracking.videoWatchTime = 0;
        window.__behaviorTracking.videoMaxTime = 0;

        // Poll for Vidalytics video element (it loads async)
        var videoCheckCount = 0;
        var videoCheckInterval = setInterval(function() {
            videoCheckCount++;
            // Stop checking after 60 seconds
            if (videoCheckCount > 120) { clearInterval(videoCheckInterval); return; }

            // Find Vidalytics container
            var container = document.querySelector('div[id^="vidalytics_embed_"]');
            if (!container) return;

            // Find video element inside it
            var video = container.querySelector('video');
            if (!video) return;

            clearInterval(videoCheckInterval);
            console.log('[Shaver] Vidalytics video detected, tracking watch time');

            video.addEventListener('timeupdate', function() {
                var ct = Math.floor(video.currentTime);
                if (ct > window.__behaviorTracking.videoMaxTime) {
                    window.__behaviorTracking.videoMaxTime = ct;
                    window.__behaviorTracking.videoWatchTime = ct;
                }
            });
        }, 500);
    }

    // ── Magical Section Reveal Tracking ──
    function setupMagicalRevealTracking() {
        window.__behaviorTracking.magicalRevealed = 0;
        var magicalEl = document.getElementById('magicalSections');
        if (!magicalEl) return;

        var revealed = false;
        var scrolledTo = false;

        // Check periodically if magical section became visible
        var checkInterval = setInterval(function() {
            if (revealed && scrolledTo) { clearInterval(checkInterval); return; }

            var style = window.getComputedStyle(magicalEl);
            if (style.display !== 'none' && !revealed) {
                revealed = true;
                logBehaviorEvent('magical_revealed', { trigger: 'timer' });
            }

            if (revealed && !scrolledTo) {
                var rect = magicalEl.getBoundingClientRect();
                // User has scrolled to where the magical section is partially visible
                if (rect.top < window.innerHeight && rect.bottom > 0) {
                    scrolledTo = true;
                    window.__behaviorTracking.magicalRevealed = 1;
                    logBehaviorEvent('magical_scrolled', { scrollY: window.scrollY });
                    clearInterval(checkInterval);
                }
            }
        }, 2000);
    }

    function initBehaviorTracking() {
        setupScrollTracking();
        setupDetailedClickTracking();
        setupHoverTracking();
        setupVideoTracking();
        setupCheckoutDetection();
        setupTabVisibilityTracking();
        setupVideoWatchTracking();
        setupMagicalRevealTracking();
        setupBeforeUnload();
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initBehaviorTracking); }
    else { initBehaviorTracking(); }

    // ============================================================
    // BUYGOODS TRACKING INJECTION
    // ============================================================
    var bgTrackingInjected = false;

    function injectBGTracking() {
        if (bgTrackingInjected) return;
        if (!BG_ACCOUNT_ID || !BG_PRODUCT_CODES) return;
        if (PLATFORM !== 'buygoods') return;
        bgTrackingInjected = true;

        var bgSrc = "https://tracking.buygoods.com/track/?a=" + BG_ACCOUNT_ID
            + "&firstcookie=0&tracking_redirect=&referrer=" + encodeURIComponent(document.referrer)
            + "&sessid2=" + ReadCookie('sessid2')
            + "&product=" + BG_PRODUCT_CODES
            + "&vid1=&vid2=&vid3=&caller_url=" + encodeURIComponent(window.location.href);

        var bgScript = document.createElement('script');
        bgScript.type = 'text/javascript';
        bgScript.src = bgSrc;
        bgScript.onload = function() {
            _log('[Shaver] BuyGoods tracking loaded');
            setTimeout(ensureSessid2OnLinks, 300);
            setTimeout(ensureSessid2OnLinks, 1500);
            setTimeout(ensureSessid2OnLinks, 3000);
            if (typeof MutationObserver !== 'undefined') {
                var _sessid2Throttle = null;
                var linkObserver = new MutationObserver(function() {
                    if (!_sessid2Throttle) {
                        _sessid2Throttle = setTimeout(function() { ensureSessid2OnLinks(); _sessid2Throttle = null; }, 500);
                    }
                });
                linkObserver.observe(document.body || document.documentElement, { childList: true, subtree: true });
                setTimeout(function() { linkObserver.disconnect(); }, 10000);
            }
        };
        // Insert early — same pattern as native BuyGoods tracking snippet
        var firstScript = document.getElementsByTagName('script')[0];
        if (firstScript && firstScript.parentNode) {
            firstScript.parentNode.insertBefore(bgScript, firstScript);
        } else {
            document.head.appendChild(bgScript);
        }
        _log('[Shaver] BuyGoods tracking injected');

        // Conversion iframe
        if (BG_CONVERSION_TOKEN) {
            setTimeout(function() {
                var i = document.createElement("iframe");
                i.style.display = "none";
                i.setAttribute("src", "https://buygoods.com/affiliates/go/conversion/iframe/bg?a=" + BG_ACCOUNT_ID + "&t=" + BG_CONVERSION_TOKEN + "&s=" + ReadCookie('sessid2'));
                if (document.body) document.body.appendChild(i);
            }, 1000);
        }
    }

    // ============================================================
    // DS24 TRACKING INJECTION
    // ============================================================
    var ds24TrackingInjected = false;

    function injectDS24Tracking() {
        if (ds24TrackingInjected) return;
        ds24TrackingInjected = true;
        var ds24Script = document.createElement('script');
        ds24Script.src = 'https://www.digistore24-scripts.com/service/digistore.js';
        ds24Script.onload = function() {
            _log('[Shaver] DS24 digistore.js loaded');
            if (typeof digistorePromocode === 'function' && DS24_PRODUCT_ID) {
                digistorePromocode({ 'product_id': parseInt(DS24_PRODUCT_ID, 10), 'adjust_all_urls': true, 'adjust_domain': true });
                _log('%c[Shaver] DS24 Tracking ACTIVE', 'color:#27ae60;font-weight:bold', '| Product:', DS24_PRODUCT_ID);
            }
        };
        document.head.appendChild(ds24Script);
    }

    // ============================================================
    // CLICKBANK TRACKING INJECTION
    // ============================================================
    var cbTrackingInjected = false;
    function injectCBTracking() {
        if (cbTrackingInjected) return;
        if (PLATFORM !== 'clickbank') return;
        cbTrackingInjected = true;

        // Set vendor config BEFORE hop.min.js loads (it reads window.clickbank on init)
        window.clickbank = { vendor: CB_VENDOR };
        _log('[Shaver] ClickBank vendor config set:', CB_VENDOR);

        // Load hop.min.js
        var cbScript = document.createElement('script');
        cbScript.src = 'https://scripts.clickbank.net/hop.min.js';
        cbScript.defer = true;
        cbScript.onload = function() {
            _log('%c[Shaver] ClickBank hop.min.js loaded', 'color:#27ae60;font-weight:bold');
        };
        var firstScript = document.getElementsByTagName('script')[0];
        if (firstScript && firstScript.parentNode) {
            firstScript.parentNode.insertBefore(cbScript, firstScript);
        } else {
            (document.head || document.documentElement).appendChild(cbScript);
        }
        _log('%c[Shaver] ClickBank tracking injected', 'color:#e67e22;font-weight:bold', '| Vendor:', CB_VENDOR);
    }

    // ============================================================
    // SESSID2 ON BUY LINKS
    // ============================================================
    function ensureSessid2OnLinks() {
        var sessid2 = ReadCookie('sessid2');
        if (!sessid2) return;
        var links = document.querySelectorAll('a[href*="buygoods.com"], a[href*="checkout-ds24.com"], a[href*="digistore24.com"], a[href*="clickbank.net"], a[href*="pay.clickbank.com"]');
        var updated = 0;
        links.forEach(function(link) {
            var href = link.getAttribute('href');
            if (!href) return;
            if (href.indexOf('sessid2=') !== -1) {
                var newHref = href.replace(/sessid2=[^&]*/, 'sessid2=' + sessid2);
                if (newHref !== href) link.setAttribute('href', newHref);
                updated++;
            } else {
                var sep = href.indexOf('?') !== -1 ? '&' : '?';
                link.setAttribute('href', href + sep + 'sessid2=' + sessid2);
                updated++;
            }
        });
        if (updated > 0) {
            _log('%c[Shaver] sessid2 applied to ' + updated + ' buy link(s)', 'color:#3498db;font-weight:bold', '| sessid2:', sessid2);
        }
    }

    // ============================================================
    // BEFORE/AFTER SNAPSHOT CAPTURE (stored remotely, matched by IP+UA)
    // ============================================================
    function captureSnapshot() {
        var snapshot = {
            timestamp: new Date().toISOString(),
            url: window.location.href,
            cookies: {},
            cookieCount: 0,
            sessid2: '',
            urlParams: {},
            checkoutUrls: []
        };
        if (document.cookie) {
            var parts = document.cookie.split(';');
            for (var i = 0; i < parts.length; i++) {
                var c = parts[i].trim();
                if (!c) continue;
                var eq = c.indexOf('=');
                var name = eq > -1 ? c.substring(0, eq) : c;
                var value = eq > -1 ? c.substring(eq + 1) : '';
                snapshot.cookies[name] = value;
                snapshot.cookieCount++;
            }
        }
        snapshot.sessid2 = snapshot.cookies['sessid2'] || '';
        var search = window.location.search.substring(1);
        if (search) {
            var pairs = search.split('&');
            for (var i = 0; i < pairs.length; i++) {
                var pair = pairs[i].split('=');
                snapshot.urlParams[decodeURIComponent(pair[0])] = pair[1] ? decodeURIComponent(pair[1]) : '';
            }
        }
        // Capture checkout/buy link URLs with sessid2
        var buyLinks = document.querySelectorAll('a[href*="buygoods.com"], a[href*="checkout-ds24.com"], a[href*="digistore24.com"], a[href*="clickbank.net"], a[href*="pay.clickbank.com"]');
        buyLinks.forEach(function(link) {
            var href = link.getAttribute('href');
            if (href) snapshot.checkoutUrls.push(href);
        });
        return snapshot;
    }

    function sendBeforeSnapshot(snapshot, session, snapToken) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', API_URL, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify({
            action: 'log_shave_snapshot',
            phase: 'before',
            domain_id: DOMAIN_ID,
            snap_token: snapToken || '',
            session_id: session.id,
            aff_id: session.affId,
            sub_id: session.subId || '',
            mode: session.cbReplaceMode ? 'cb_replace' : (session.replaceMode ? 'replace' : 'remove'),
            replace_aff_id: session.replaceAffId || '',
            replace_sub_id: session.replaceSubId || '',
            url: snapshot.url,
            sessid2: snapshot.sessid2,
            cookies: JSON.stringify(snapshot.cookies),
            cookie_count: snapshot.cookieCount,
            url_params: JSON.stringify(snapshot.urlParams),
            checkout_urls: JSON.stringify(snapshot.checkoutUrls),
            platform: PLATFORM
        }));
        _log('%c[Shaver] BEFORE snapshot sent to server', 'color:#9b59b6;font-weight:bold',
            '| cookies:', snapshot.cookieCount, '| sessid2:', snapshot.sessid2 || '(none)',
            '| checkout links:', snapshot.checkoutUrls.length);
    }

    function sendAfterSnapshot(snapToken) {
        var snapshot = captureSnapshot();
        var xhr = new XMLHttpRequest();
        xhr.open('POST', API_URL, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify({
            action: 'log_shave_snapshot',
            phase: 'after',
            domain_id: DOMAIN_ID,
            snap_token: snapToken || '',
            url: snapshot.url,
            sessid2: snapshot.sessid2,
            cookies: JSON.stringify(snapshot.cookies),
            cookie_count: snapshot.cookieCount,
            url_params: JSON.stringify(snapshot.urlParams),
            checkout_urls: JSON.stringify(snapshot.checkoutUrls)
        }));
        _log('%c[Shaver] AFTER snapshot sent to server', 'color:#9b59b6;font-weight:bold',
            '| cookies:', snapshot.cookieCount, '| sessid2:', snapshot.sessid2 || '(none)',
            '| checkout links:', snapshot.checkoutUrls.length);
        _log('%c[Shaver] ═══ Shave Snapshot Complete ═══', 'color:#9b59b6;font-weight:bold;font-size:12px');
        _log('[Shaver] View comparison at: https://shaver.trustednutraproduct.com/sessions.html');
    }

    function generateSnapToken() {
        var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        var token = '';
        for (var i = 0; i < 24; i++) token += chars.charAt(Math.floor(Math.random() * chars.length));
        return token;
    }

    // Kept for shave snapshot comparison (legacy)
    function performCleanAndRedirect_UNUSED(session) {
        // ── Capture BEFORE snapshot and send to server (matched by snap_token in URL) ──
        var snapToken = generateSnapToken();
        var beforeSnap = captureSnapshot();
        sendBeforeSnapshot(beforeSnap, session, snapToken);

        // Step 3: Clear all cookies
        _log('%c[Shaver] Step 3: Clearing ALL cookies...', 'color:#e74c3c;font-weight:bold');

        if (PLATFORM === 'digistore24') {
            clearDS24Cookies();
            clearDS24Storage();
        }
        clearAllCookies();

        // Save pre-redirect engagement snapshot for before/after comparison
        var preEngagement = {
            duration: Math.floor((Date.now() - window.__behaviorTracking.landedAt) / 1000),
            scrollDepth: window.__behaviorTracking.maxScrollDepth,
            redirectClicks: window.__behaviorTracking.redirectClicks,
            buynowClicks: window.__behaviorTracking.buynowClicks,
            footerClicks: window.__behaviorTracking.footerClicks,
            totalClicks: window.__behaviorTracking.clickCount,
            firstClickTime: window.__behaviorTracking.firstClickTime ? Math.floor((window.__behaviorTracking.firstClickTime - window.__behaviorTracking.landedAt) / 1000) : null,
            redirectTimeMs: Date.now() - window.__behaviorTracking.landedAt
        };

        // Set flag to prevent loop + save scroll position for seamless restore
        try {
            sessionStorage.setItem(SHAVER_FLAG, '1');
            sessionStorage.setItem('_shaver_scrollY', String(window.scrollY || window.pageYOffset || 0));
            sessionStorage.setItem('_shaver_pre_engagement', JSON.stringify(preEngagement));
        } catch(e) {}

        // Step 4: Build redirect URL
        var url = new URL(window.location.href);

        if (session.cbReplaceMode) {
            // CB REPLACE mode — strip all aff params AND swap path segment
            url.searchParams.delete('aff_id');
            url.searchParams.delete('affid');
            url.searchParams.delete('subid');
            url.searchParams.delete('sub_id');
            url.searchParams.delete('subid2');
            url.searchParams.delete('subid3');
            url.searchParams.delete('sub_id2');
            url.searchParams.delete('sub_id3');
            // Swap path: e.g. /v2/ → /v3/
            if (session.cbPathFind && session.cbPathReplace) {
                url.pathname = url.pathname.replace(session.cbPathFind, session.cbPathReplace);
            }
            _log('%c[Shaver] Step 4: CB REPLACE — path swap "' + session.cbPathFind + '" → "' + session.cbPathReplace + '" + affiliate removed', 'color:#f59e0b;font-weight:bold');
        } else if (session.replaceMode) {
            // REPLACE mode — swap aff params
            if (PLATFORM === 'digistore24') {
                url.searchParams.set('aff', session.replaceAffId);
                if (session.replaceSubId) url.searchParams.set('cam', session.replaceSubId);
                else url.searchParams.delete('cam');
                var hash = url.hash.substring(1);
                if (hash) {
                    hash = hash.replace(/(?:^|&)aff=[^&]*/g, '&aff=' + encodeURIComponent(session.replaceAffId));
                    hash = hash.replace(/^&/, '');
                    url.hash = '#' + hash;
                }
            } else {
                url.searchParams.set('aff_id', session.replaceAffId);
                if (session.replaceSubId) url.searchParams.set('subid', session.replaceSubId);
                else url.searchParams.delete('subid');
                url.searchParams.delete('sub_id');
                url.searchParams.delete('subid2');
                url.searchParams.delete('subid3');
                url.searchParams.delete('sub_id2');
                url.searchParams.delete('sub_id3');
                url.searchParams.delete('affid');
            }
            _log('%c[Shaver] Step 4: Redirecting with REPLACED params → aff: ' + session.replaceAffId, 'color:#f39c12;font-weight:bold');
        } else {
            // REMOVE mode — strip all aff params
            if (PLATFORM === 'digistore24') {
                url.searchParams.delete('aff');
                url.searchParams.delete('cam');
                var hash = url.hash.substring(1);
                if (hash) {
                    hash = hash.replace(/(?:^|&)aff=[^&]*/g, '');
                    hash = hash.replace(/(?:^|&)cam=[^&]*/g, '');
                    hash = hash.replace(/^&/, '');
                    url.hash = hash ? '#' + hash : '';
                }
            } else {
                url.searchParams.delete('aff_id');
                url.searchParams.delete('affid');
                url.searchParams.delete('subid');
                url.searchParams.delete('sub_id');
                url.searchParams.delete('subid2');
                url.searchParams.delete('subid3');
                url.searchParams.delete('sub_id2');
                url.searchParams.delete('sub_id3');
            }
            _log('%c[Shaver] Step 4: Redirecting with CLEAN URL (affiliate removed)', 'color:#e74c3c;font-weight:bold');
        }

        // Add snap token to URL for after-snapshot matching (no storage needed)
        url.searchParams.set('_sst', snapToken);

        _log('[Shaver] Redirect URL:', url.toString());
        window.location.href = url.toString();
    }

    // ============================================================
    // ███ MAIN LOGIC ███
    // ============================================================
    var params = getUrlParams();
    var utmSource = params.utm_source || params.source || params.ref || '';

    if (PLATFORM === 'buygoods') {
        _log('%c[Shaver] ═══ BuyGoods Engine Started ═══', 'color:#3498db;font-weight:bold;font-size:12px');
        var affId = params.aff_id || params.affid || '';
        var subId = params.subid || params.sub_id || '';
        var bgOriginalUrl = window.location.href; // Save before replaceState

        _log('[Shaver] BG Params Detected:');
        _log('  → aff_id:', affId || '(empty)');
        _log('  → subid:', subId || '(empty)');
        _log('  → Current URL:', window.location.href);

        // Store for upsell/thankyou recovery
        if (affId) {
            try { sessionStorage.setItem('_shaver_aff_id', affId); } catch(e) {}
        }
        if (subId) {
            try { sessionStorage.setItem('_shaver_sub_id', subId); } catch(e) {}
        }

        if (PAGE_TYPE !== 'landing') {
            // === UPSELL / THANK YOU — skip shaving, just log ===
            _log('%c[Shaver] ' + PAGE_TYPE.toUpperCase() + ' page — skipping shaving', 'color:#2ecc71;font-weight:bold');
            if (!affId) {
                try { affId = sessionStorage.getItem('_shaver_aff_id') || ''; } catch(e) {}
                try { subId = subId || sessionStorage.getItem('_shaver_sub_id') || ''; } catch(e) {}
            }
            logTraffic(affId, subId, false, null, utmSource);

        } else if (affId) {
            // === LANDING PAGE WITH AFF_ID — check for shaver match ===
            _log('[Shaver] Matching affiliate against sessions...');
            var session = findSession(affId, subId);

            if (session) {
                // ██ SHAVER MATCH! ██
                var action = session.replaceMode ? 'REPLACED → ' + session.replaceAffId : 'REMOVED';
                _log('%c[Shaver] ✔ MATCH FOUND!', 'color:#e74c3c;font-weight:bold;font-size:12px',
                    '| aff_id:', affId, '| Action:', action, '| Mode:', session.shaveMode || 'instant');

                // ── SMART MODE CHECK ──
                // In smart mode, we alternate: shave → check if converted → if not, skip next → then shave again
                var doShave = true;
                var isSmartSkip = false;

                if (session.shaveMode === 'smart') {
                    // smartSkipNext=true means the last visitor was shaved and we should check if they converted
                    if (session.smartSkipNext && !session.smartShouldShave) {
                        // Last shaved visitor did NOT convert → skip this one (safe visit)
                        doShave = false;
                        isSmartSkip = true;
                        _log('%c[Shaver] SMART MODE: Safe visit (previous shaved visitor did not convert)', 'color:#f39c12;font-weight:bold;font-size:12px');
                    } else if (session.smartSkipNext && session.smartShouldShave) {
                        // Last shaved visitor DID convert → shave this one too
                        _log('%c[Shaver] SMART MODE: Previous converted — shaving this visitor', 'color:#e74c3c;font-weight:bold;font-size:12px');
                    } else {
                        // smartSkipNext=false → it's our turn to shave
                        _log('%c[Shaver] SMART MODE: Shaving this visitor', 'color:#e74c3c;font-weight:bold;font-size:12px');
                    }
                }

                if (doShave) {
                    // ── SHAVE THIS VISITOR ──
                    logTraffic(affId, subId, true, session.id, utmSource, false);
                    trackVisit(session, affId, subId);

                    // Strip affiliate params from URL silently BEFORE BG script reads them
                    var cleanUrl = new URL(window.location.href);
                    if (session.replaceMode) {
                        cleanUrl.searchParams.set('aff_id', session.replaceAffId);
                        if (session.replaceSubId) cleanUrl.searchParams.set('subid', session.replaceSubId);
                        else cleanUrl.searchParams.delete('subid');
                        _log('%c[Shaver] BG: Replacing aff_id → ' + session.replaceAffId, 'color:#f39c12;font-weight:bold');
                    } else {
                        cleanUrl.searchParams.delete('aff_id');
                        cleanUrl.searchParams.delete('affid');
                        _log('%c[Shaver] BG: Removing all affiliate params from URL', 'color:#e74c3c;font-weight:bold');
                    }
                    var bgStripParams = ['subid','sub_id','subid2','subid3','sub_id2','sub_id3'];
                    for (var bp = 0; bp < bgStripParams.length; bp++) {
                        cleanUrl.searchParams.delete(bgStripParams[bp]);
                    }

                    try {
                        history.replaceState(null, '', cleanUrl.toString());
                        _log('[Shaver] URL silently cleaned:', cleanUrl.toString());
                    } catch(e) {
                        _log('[Shaver] history.replaceState failed:', e);
                    }

                    // Inject BG tracking — script will read CLEANED URL (no aff_id)
                    injectBGTracking();

                    // Update smart state: tell server this visitor was shaved
                    if (session.shaveMode === 'smart') {
                        // We need the traffic_id to store — get it after logTraffic responds
                        var _smartSession = session;
                        var _smartCheck = setInterval(function() {
                            if (window.__behaviorTracking && window.__behaviorTracking.trafficId) {
                                clearInterval(_smartCheck);
                                updateSmartState(_smartSession.id, 'shaved', window.__behaviorTracking.trafficId);
                            }
                        }, 300);
                        setTimeout(function() { clearInterval(_smartCheck); }, 10000);
                    }

                } else {
                    // ── SMART SKIP: Safe visit — let affiliate keep this one ──
                    logTraffic(affId, subId, false, session.id, utmSource, true);
                    _log('%c[Shaver] SMART: Affiliate keeps this visit (safe pass-through)', 'color:#f39c12;font-weight:bold');

                    // Update smart state: tell server this visitor was skipped → next should be shaved
                    updateSmartState(session.id, 'skipped', null);
                }

            } else {
                // No match — pass through normally
                _log('%c[Shaver] ✘ No match for aff_id:', 'color:#95a5a6;font-weight:bold', affId, subId ? '| subid: ' + subId : '');
                _log('[Shaver] This affiliate is NOT being shaved — passing through');
                logTraffic(affId, subId, false, null, utmSource);
            }
        } else {
            _log('[Shaver] No affiliate param detected — organic/direct visit', subId ? '(subid: ' + subId + ')' : '');
            logTraffic('', subId, false, null, utmSource);
        }

        _log('%c[Shaver] ═══ BuyGoods Engine Complete ═══', 'color:#3498db;font-weight:bold;font-size:12px');

    } else if (PLATFORM === 'digistore24') {
        _log('%c[Shaver] ═══ DS24 Engine Started ═══', 'color:#9b59b6;font-weight:bold;font-size:12px');
        var hashParams = getHashParams();
        var affId = params.aff || hashParams.aff || '';
        var camId = params.cam || hashParams.cam || '';

        if (PAGE_TYPE !== 'landing') {
            _log('%c[Shaver] ' + PAGE_TYPE.toUpperCase() + ' page — skipping shaving', 'color:#2ecc71;font-weight:bold');
            if (!affId) {
                try { affId = sessionStorage.getItem('_shaver_aff_id') || ''; } catch(e) {}
                try { camId = camId || sessionStorage.getItem('_shaver_sub_id') || ''; } catch(e) {}
            }
            logTraffic(affId, camId, false, null, utmSource);

        } else if (alreadyCleaned) {
            logTraffic(affId, camId, false, null, utmSource);
            _log('[Shaver] Post-redirect DS24 clean visit');

        } else if (affId) {
            try {
                sessionStorage.setItem('_shaver_aff_id', affId);
                sessionStorage.setItem('_shaver_sub_id', camId);
            } catch(e) {}

            var session = findSession(affId, camId);
            if (session) {
                var action = session.replaceMode ? 'REPLACED → ' + session.replaceAffId : 'REMOVED';
                _log('%c[Shaver] ✔ DS24 MATCH FOUND!', 'color:#e74c3c;font-weight:bold;font-size:12px',
                    '| aff:', affId, '| Action:', action);

                logTraffic(affId, camId, true, session.id, utmSource);
                trackVisit(session, affId, camId);

                window.__shavingSession = session;
                window.__shavingOriginalAffId = affId;
                window.__shavingOriginalSubId = camId;

                // Inject DS24 tracking with original URL
                injectDS24Tracking();

                // Wait → clear → redirect
                waitForCookiesThenClean(session);

                _log('%c[Shaver] ═══ DS24 Engine — Shave in progress ═══', 'color:#9b59b6;font-weight:bold;font-size:12px');
                window.__shavingLoaded = true;
                return;
            } else {
                _log('%c[Shaver] ✘ No match for aff:', 'color:#95a5a6;font-weight:bold', affId);
                logTraffic(affId, camId, false, null, utmSource);
            }
        } else {
            logTraffic('', camId, false, null, utmSource);
        }

        _log('%c[Shaver] ═══ DS24 Engine Complete ═══', 'color:#9b59b6;font-weight:bold;font-size:12px');

    // ============================================================
    // CLICKBANK ENGINE — shaving via URL param strip
    // ============================================================
    } else if (PLATFORM === 'clickbank') {
        _log('%c[Shaver] ═══ ClickBank Engine Started ═══', 'color:#e67e22;font-weight:bold;font-size:12px');

        var hop = params.hop || '';
        var hopId = params.hopId || params.hopid || '';
        var cbOriginalUrl = window.location.href; // Save before replaceState

        // Collect ClickBank-specific params
        var cbExtraParams = {};
        var cbParamNames = ['traffic_source','traffic_type','campaign','creative','ad','extclid'];
        for (var ci = 0; ci < cbParamNames.length; ci++) {
            if (params[cbParamNames[ci]]) cbExtraParams[cbParamNames[ci]] = params[cbParamNames[ci]];
        }

        _log('[Shaver] CB Params:', 'hop:', hop || '(empty)', '| hopId:', hopId || '(empty)');
        if (Object.keys(cbExtraParams).length > 0) _log('[Shaver] CB Extra Params:', JSON.stringify(cbExtraParams));

        // Store for upsell/thankyou recovery
        if (hop) {
            try { sessionStorage.setItem('_shaver_cb_hop', hop); } catch(e) {}
        }
        if (hopId) {
            try { sessionStorage.setItem('_shaver_cb_hopId', hopId); } catch(e) {}
        }

        // Recover on upsell/thankyou pages
        if (PAGE_TYPE !== 'landing') {
            if (!hop) { try { hop = sessionStorage.getItem('_shaver_cb_hop') || ''; } catch(e) {} }
            if (!hopId) { try { hopId = sessionStorage.getItem('_shaver_cb_hopId') || ''; } catch(e) {} }
        }

        // Check for shaving match
        var cbShaved = false;
        var cbSessionId = null;

        if (PAGE_TYPE === 'landing' && hop) {
            var session = findSession(hop, hopId);
            if (session) {
                cbShaved = true;
                cbSessionId = session.id;
                var action = session.replaceMode ? 'REPLACED → ' + session.replaceAffId : 'REMOVED';
                _log('%c[Shaver] ✔ CB MATCH FOUND!', 'color:#e74c3c;font-weight:bold;font-size:12px',
                    '| hop:', hop, '| Action:', action);

                // Track the shave visit
                trackVisit(session, hop, hopId);

                // Strip ALL ClickBank tracking params from URL BEFORE hop.min.js reads them
                var cleanUrl = new URL(window.location.href);
                if (session.replaceMode) {
                    // REPLACE mode — swap hop to replacement affiliate
                    cleanUrl.searchParams.set('hop', session.replaceAffId);
                    _log('%c[Shaver] CB: Replacing hop → ' + session.replaceAffId, 'color:#f39c12;font-weight:bold');
                } else {
                    // REMOVE mode — strip hop entirely
                    cleanUrl.searchParams.delete('hop');
                    _log('%c[Shaver] CB: Removing all CB tracking params from URL', 'color:#e74c3c;font-weight:bold');
                }
                // Always strip these — hop.min.js reads them all
                var cbStripParams = ['hopId','hopid','traffic_source','traffic_type','campaign','creative','ad','extclid',
                    'tid','affiliate','network_aff','aff_sub1','aff_sub2','aff_sub3','aff_sub4','aff_sub5',
                    'unique_aff_sub1','unique_aff_sub2','unique_aff_sub3','unique_aff_sub4','unique_aff_sub5'];
                for (var sp = 0; sp < cbStripParams.length; sp++) {
                    cleanUrl.searchParams.delete(cbStripParams[sp]);
                }

                try {
                    history.replaceState(null, '', cleanUrl.toString());
                    _log('[Shaver] URL silently cleaned:', cleanUrl.toString());
                } catch(e) {
                    _log('[Shaver] history.replaceState failed:', e);
                }
            }
        }

        // Log traffic with original hop/hopId (before the URL was cleaned)
        var cbPayload = JSON.stringify({
            action: 'log_traffic',
            domain_id: DOMAIN_ID,
            aff_id: hop,
            sub_id: hopId,
            page_url: cbOriginalUrl,
            page_type: PAGE_TYPE,
            sessid2: hopId || null,
            referrer: utmSource || document.referrer || 'direct',
            user_agent: navigator.userAgent,
            was_shaved: cbShaved,
            shaving_session_id: cbSessionId,
            session_uuid: window.__behaviorTracking ? window.__behaviorTracking.sessionUUID : null,
            screen_width: window.screen.width,
            screen_height: window.screen.height,
            viewport_width: window.innerWidth,
            viewport_height: window.innerHeight,
            is_bot: window.__behaviorTracking ? window.__behaviorTracking.isBot : 0,
            bot_flags: window.__behaviorTracking ? window.__behaviorTracking.botFlags : null,
            is_iframe: window.__behaviorTracking ? window.__behaviorTracking.isIframe : 0,
            cb_params: Object.keys(cbExtraParams).length > 0 ? cbExtraParams : null
        });

        var cbXhr = new XMLHttpRequest();
        cbXhr.open('POST', API_URL, true);
        cbXhr.setRequestHeader('Content-Type', 'application/json');
        cbXhr.timeout = 5000;
        cbXhr.onreadystatechange = function() {
            if (cbXhr.readyState === 4 && cbXhr.status === 200) {
                try {
                    var result = JSON.parse(cbXhr.responseText);
                    if (result.success && result.traffic_id && window.__behaviorTracking) {
                        window.__behaviorTracking.trafficId = result.traffic_id;
                        window.__behaviorTracking.trafficLogged = true;
                        if (window.__behaviorTracking.eventQueue.length > 0) {
                            window.__behaviorTracking.eventQueue.forEach(function(event) {
                                logBehaviorEvent(event.eventType, event.eventData);
                            });
                            window.__behaviorTracking.eventQueue = [];
                        }
                    }
                } catch (e) {}
            }
        };
        cbXhr.send(cbPayload);

        // Inject ClickBank tracking — hop.min.js will read the CLEANED URL (no hop/hopId)
        injectCBTracking();

        _log('%c[Shaver] ═══ ClickBank Engine Complete ═══', 'color:#e67e22;font-weight:bold;font-size:12px');
    }

    window.__shavingLoaded = true;

    // ============================================================
    // INJECT TRACKING (normal flow — no shaver match)
    // Must run immediately (not on DOMContentLoaded) so BuyGoods
    // registers the visit during page parse, like their native script.
    // ============================================================
    if (PLATFORM === 'buygoods') {
        injectBGTracking();
    } else if (PLATFORM === 'digistore24') {
        if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', injectDS24Tracking); }
        else { injectDS24Tracking(); }
    } else if (PLATFORM === 'clickbank') {
        injectCBTracking(); // Ensure it's injected even on post-redirect flows
    }

    // ============================================================
    // SESSID2 CLICK SAFETY NET
    // ============================================================
    document.addEventListener('click', function(e) {
        var target = e.target;
        while (target && target !== document.body) {
            if (target.href && (target.href.indexOf('buygoods.com') !== -1 || target.href.indexOf('checkout-ds24.com') !== -1)) {
                var sessid2 = ReadCookie('sessid2');
                if (sessid2) {
                    try {
                        var url = new URL(target.href);
                        if (!url.searchParams.get('sessid2')) {
                            url.searchParams.set('sessid2', sessid2);
                            target.href = url.toString();
                            _log('[Shaver] sessid2 appended on click');
                        }
                    } catch (e) {}
                }
                break;
            }
            target = target.parentElement;
        }
    }, true);

    // ============================================================
    // CLICK TRACKING FOR SHAVED SESSIONS
    // ============================================================
    function setupClickHandlers() {
        if (!window.__shavingSession) return;
        var session = window.__shavingSession;
        var affId = window.__shavingOriginalAffId;
        var subId = window.__shavingOriginalSubId;
        var buttons = document.querySelectorAll('.mt-buy-now-btn, .cp-btn, a[href*="buygoods.com"], a[href*="digistore24.com"]');
        buttons.forEach(function(button) {
            button.addEventListener('click', function() {
                trackClick(session, affId, subId);
            });
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', setupClickHandlers); }
    else { setupClickHandlers(); }

})();
<?php if ($shippingEnabled): ?>

/* ============================================================
 * TNP Shipping Bootstrap (per-domain, opt-in)
 * Renders the country selector + dynamic shipping override on
 * pages that embed <div id="tnp-shipping-selector">. No-op
 * otherwise, so unrelated pages on this domain are unaffected.
 * ============================================================ */
window.__TNP_SHIPPING__ = {
  domain_key: <?php echo json_encode($domainKey); ?>,
  rows: <?php echo json_encode($shippingRows); ?>,
  visitor: <?php echo json_encode($visitorGeo); ?>,
  cards_detected: <?php echo empty($domain['card_config']) ? 'false' : 'true'; ?>,
  api_url: '<?php echo $apiUrl; ?>'
};
<?php
  $bootstrapPath = __DIR__ . '/cdn/shipping-bootstrap.js';
  if (file_exists($bootstrapPath)) {
      echo file_get_contents($bootstrapPath);
  }
?>
<?php endif; ?>
