/* ============================================================
 * TNP Shipping Bootstrap
 * Injected by check.php only when the requesting domain has
 * shipping_enabled = 1. Reads window.__TNP_SHIPPING__ (set by
 * check.php) and renders the country selector + per-card shipping
 * override on pages that embed <div id="tnp-shipping-selector">.
 *
 * Hard rule: this file is a NO-OP if the placeholder div is missing,
 * so other domains with the same tracker payload (or unrelated
 * pages on the opted-in domain) are never touched.
 * ============================================================ */
(function() {
    'use strict';

    var STORAGE_KEY = 'tnp_country'; // stores ISO alpha-2
    var FALLBACK_ISO = 'US';

    // Geo APIs tried in order. ipwho.is is unlimited free; ipapi.co is the
    // backup (1000/day rate limit). Each entry is a function that returns
    // a Promise resolving to { code, name } or null on failure.
    var GEO_PROVIDERS = [
        {
            url: 'https://ipwho.is/',
            parse: function(json) {
                if (!json || json.success === false) return null;
                return {
                    code: json.country_code || null,
                    name: json.country || null
                };
            }
        },
        {
            url: 'https://ipapi.co/json/',
            parse: function(json) {
                if (!json || json.error) return null;
                return {
                    code: json.country_code || null,
                    name: json.country_name || null
                };
            }
        }
    ];

    // Region key labels (used as <optgroup> labels)
    var REGION_NAMES = {
        'US': 'United States',
        'CA': 'Canada',
        'UK': 'UK and Ireland',
        'NE': 'Northern Europe',
        'WE': 'Western Europe',
        'SE': 'Southern Europe',
        'AU': 'Australasia'
    };

    // Full country list grouped by region. The ORDER here is the order
    // shown in the dropdown (regions and countries within them).
    var COUNTRY_LIST = [
        // United States
        { region: 'US', iso: 'US', name: 'United States' },
        // Canada
        { region: 'CA', iso: 'CA', name: 'Canada' },
        // UK and Ireland
        { region: 'UK', iso: 'GB', name: 'United Kingdom' },
        { region: 'UK', iso: 'IE', name: 'Ireland' },
        // Northern Europe
        { region: 'NE', iso: 'SE', name: 'Sweden' },
        { region: 'NE', iso: 'DK', name: 'Denmark' },
        { region: 'NE', iso: 'NO', name: 'Norway' },
        { region: 'NE', iso: 'FI', name: 'Finland' },
        { region: 'NE', iso: 'IS', name: 'Iceland' },
        { region: 'NE', iso: 'EE', name: 'Estonia' },
        { region: 'NE', iso: 'LV', name: 'Latvia' },
        { region: 'NE', iso: 'LT', name: 'Lithuania' },
        // Western Europe
        { region: 'WE', iso: 'DE', name: 'Germany' },
        { region: 'WE', iso: 'FR', name: 'France' },
        { region: 'WE', iso: 'CH', name: 'Switzerland' },
        { region: 'WE', iso: 'NL', name: 'Netherlands' },
        { region: 'WE', iso: 'AT', name: 'Austria' },
        { region: 'WE', iso: 'BE', name: 'Belgium' },
        { region: 'WE', iso: 'LU', name: 'Luxembourg' },
        { region: 'WE', iso: 'LI', name: 'Liechtenstein' },
        { region: 'WE', iso: 'MC', name: 'Monaco' },
        // Southern Europe
        { region: 'SE', iso: 'IT', name: 'Italy' },
        { region: 'SE', iso: 'ES', name: 'Spain' },
        { region: 'SE', iso: 'PT', name: 'Portugal' },
        { region: 'SE', iso: 'GR', name: 'Greece' },
        { region: 'SE', iso: 'MT', name: 'Malta' },
        { region: 'SE', iso: 'CY', name: 'Cyprus' },
        { region: 'SE', iso: 'SM', name: 'San Marino' },
        { region: 'SE', iso: 'VA', name: 'Vatican City' },
        { region: 'SE', iso: 'AD', name: 'Andorra' },
        { region: 'SE', iso: 'GI', name: 'Gibraltar' },
        // Australasia
        { region: 'AU', iso: 'AU', name: 'Australia' },
        { region: 'AU', iso: 'NZ', name: 'New Zealand' },
        { region: 'AU', iso: 'PG', name: 'Papua New Guinea' },
        { region: 'AU', iso: 'FJ', name: 'Fiji' },
        { region: 'AU', iso: 'SB', name: 'Solomon Islands' },
        { region: 'AU', iso: 'VU', name: 'Vanuatu' },
        { region: 'AU', iso: 'WS', name: 'Samoa' },
        { region: 'AU', iso: 'TO', name: 'Tonga' }
    ];

    // Build ISO → region and ISO → name lookups from COUNTRY_LIST
    var ISO_TO_REGION = {};
    var ISO_TO_NAME = {};
    for (var k = 0; k < COUNTRY_LIST.length; k++) {
        ISO_TO_REGION[COUNTRY_LIST[k].iso] = COUNTRY_LIST[k].region;
        ISO_TO_NAME[COUNTRY_LIST[k].iso] = COUNTRY_LIST[k].name;
    }

    function isoToRegion(iso) {
        if (!iso) return null;
        return ISO_TO_REGION[iso.toUpperCase()] || null;
    }
    function isoToName(iso) {
        if (!iso) return iso;
        return ISO_TO_NAME[iso.toUpperCase()] || iso;
    }

    function init() {
        var holder = document.getElementById('tnp-shipping-selector');
        if (!holder) return; // page not opted-in

        var data = window.__TNP_SHIPPING__;
        if (!data || !data.rows || !data.rows.length) return;

        // Build a quick lookup by region key (admin's row "country_code" IS a region)
        var byRegion = {};
        for (var i = 0; i < data.rows.length; i++) {
            var r = data.rows[i];
            r.ship_1_bottle = parseFloat(r.ship_1_bottle) || 0;
            r.ship_2_bottle = parseFloat(r.ship_2_bottle) || 0;
            r.ship_3_bottle = parseFloat(r.ship_3_bottle) || 0;
            r.ship_6_bottle = parseFloat(r.ship_6_bottle) || 0;
            byRegion[r.country_code] = r;
        }

        // Populate the dropdown: <optgroup> per configured region with all
        // ISO countries belonging to that region.
        var select = document.getElementById('tnp-shipping-country');
        if (!select) return;
        // Keep the existing "Auto Fetch Country" option as first; remove anything after it
        while (select.options.length > 1) select.remove(1);

        // Walk COUNTRY_LIST in order, grouping consecutive same-region entries.
        // Skip any region the admin hasn't configured.
        var currentGroup = null;
        var currentRegion = null;
        for (var j = 0; j < COUNTRY_LIST.length; j++) {
            var c = COUNTRY_LIST[j];
            if (!byRegion[c.region]) continue; // region not configured by admin
            if (c.region !== currentRegion) {
                currentGroup = document.createElement('optgroup');
                currentGroup.label = REGION_NAMES[c.region] || c.region;
                select.appendChild(currentGroup);
                currentRegion = c.region;
            }
            var opt = document.createElement('option');
            opt.value = c.iso;
            opt.textContent = c.name;
            currentGroup.appendChild(opt);
        }

        holder.style.display = '';

        // Determine starting ISO: localStorage > IP geo > fallback
        var stored = null;
        try { stored = localStorage.getItem(STORAGE_KEY); } catch (e) {}

        // Server-side visitor data (set by check.php). When present we
        // skip the client-side fetch entirely — no CORS, no rate limit.
        var serverVisitor = (data && data.visitor && data.visitor.code) ? data.visitor : null;

        if (stored && isoToRegion(stored) && byRegion[isoToRegion(stored)]) {
            // Manual selection from a previous visit takes precedence
            applyByIso(stored, false, byRegion, null);
        } else if (serverVisitor) {
            console.log('[TNP Shipping] using server-side geo:', serverVisitor);
            applyByIso(serverVisitor.code, true, byRegion, serverVisitor.name);
        } else {
            // Last-resort client-side fallback
            console.log('[TNP Shipping] no server geo, falling back to client-side');
            fetchGeoCountry().then(function(geo) {
                applyByIso(geo.code, true, byRegion, geo.name);
            });
        }

        // Listen for manual changes
        select.addEventListener('change', function() {
            var val = select.value;
            if (val === '__auto') {
                // Re-detect — bypass localStorage cache. Prefer server data
                // if we have it; only fetch client-side as a fallback.
                try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
                if (serverVisitor) {
                    applyByIso(serverVisitor.code, true, byRegion, serverVisitor.name);
                } else {
                    fetchGeoCountry().then(function(geo) {
                        applyByIso(geo.code, true, byRegion, geo.name);
                    });
                }
            } else {
                // val is an ISO code
                try { localStorage.setItem(STORAGE_KEY, val); } catch (e) {}
                applyByIso(val, false, byRegion, null);
            }
        });
    }

    // Try one geo provider; resolves to { code, name } or null on failure.
    function tryProvider(provider) {
        return new Promise(function(resolve) {
            try {
                var xhr = new XMLHttpRequest();
                xhr.open('GET', provider.url, true);
                xhr.timeout = 4000;
                xhr.onload = function() {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            var json = JSON.parse(xhr.responseText);
                            var parsed = provider.parse(json);
                            if (parsed && parsed.code) {
                                resolve(parsed);
                            } else {
                                console.warn('[TNP Shipping] geo provider returned no country:', provider.url, json);
                                resolve(null);
                            }
                        } catch (e) {
                            console.warn('[TNP Shipping] geo provider parse error:', provider.url, e);
                            resolve(null);
                        }
                    } else {
                        console.warn('[TNP Shipping] geo provider HTTP error:', provider.url, xhr.status);
                        resolve(null);
                    }
                };
                xhr.onerror = function() {
                    console.warn('[TNP Shipping] geo provider network error:', provider.url);
                    resolve(null);
                };
                xhr.ontimeout = function() {
                    console.warn('[TNP Shipping] geo provider timeout:', provider.url);
                    resolve(null);
                };
                xhr.send();
            } catch (e) {
                console.warn('[TNP Shipping] geo provider exception:', provider.url, e);
                resolve(null);
            }
        });
    }

    // Walk providers in order until one succeeds. Returns { code, name } or
    // { code: null, name: null } if all fail.
    function fetchGeoCountry() {
        var idx = 0;
        function next() {
            if (idx >= GEO_PROVIDERS.length) {
                console.warn('[TNP Shipping] all geo providers failed');
                return Promise.resolve({ code: null, name: null });
            }
            var p = GEO_PROVIDERS[idx++];
            return tryProvider(p).then(function(result) {
                if (result) {
                    console.log('[TNP Shipping] geo detected via', p.url, '→', result);
                    return result;
                }
                return next();
            });
        }
        return next();
    }

    // detectedName: ipapi-provided country name, used when we don't have a
    // local name for the ISO (e.g. visitor from Pakistan — not in our list)
    function applyByIso(detectedIso, isAuto, byRegion, detectedName) {
        var iso = (detectedIso || '').toUpperCase();
        var region = isoToRegion(iso);
        var row = region ? byRegion[region] : null;
        var fellBack = false;

        if (!row) {
            // Visitor's country not in any configured region — fall back to USA per spec
            fellBack = true;
            var fbRegion = isoToRegion(FALLBACK_ISO);
            row = fbRegion ? byRegion[fbRegion] : null;
            if (!row) return; // no USA row either, give up silently
        }

        // Pick the ISO that the dropdown should reflect
        var dropdownIso = fellBack ? FALLBACK_ISO : iso;

        // Update dropdown selection
        var select = document.getElementById('tnp-shipping-country');
        if (select && select.value !== dropdownIso) {
            select.value = dropdownIso;
        }

        // Update the label — be honest about what was detected vs. what's applied
        var label = document.getElementById('tnp-shipping-label');
        if (label) {
            if (isAuto) {
                if (fellBack) {
                    // Show the actual detected country (use ipapi name if we don't have one)
                    var displayedName = isoToName(iso);
                    if (displayedName === iso && detectedName) displayedName = detectedName;
                    if (!displayedName || displayedName === '') displayedName = 'Unknown';
                    label.textContent = 'Auto-detected: ' + displayedName + ' \u2014 using United States rates';
                } else {
                    label.textContent = 'Auto-detected: ' + isoToName(dropdownIso);
                }
            } else {
                label.textContent = 'Selected Country: ' + isoToName(dropdownIso);
            }
        }

        // Update each card's shipping line
        updateShipNodes('1', row.ship_1_bottle);
        updateShipNodes('2', row.ship_2_bottle);
        updateShipNodes('3', row.ship_3_bottle);
        updateShipNodes('6', row.ship_6_bottle);

        // Update the 3-bottle card header
        var headers = document.querySelectorAll('[data-tnp-ship-header="3"]');
        for (var i = 0; i < headers.length; i++) {
            headers[i].innerHTML = (row.ship_3_bottle > 0)
                ? ('+ $' + formatPrice(row.ship_3_bottle) + ' SHIPPING &bull; 90 DAY SUPPLY')
                : 'FREE SHIPPING &bull; 90 DAY SUPPLY';
        }
    }

    function updateShipNodes(slot, amount) {
        var nodes = document.querySelectorAll('[data-tnp-ship="' + slot + '"]');
        var text = (amount > 0)
            ? ('+ $' + formatPrice(amount) + ' Shipping')
            : 'FREE Shipping';
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].textContent = text;
        }
    }

    function formatPrice(n) {
        // Strip trailing .00 for cleanliness, otherwise show 2 decimals
        if (n === Math.floor(n)) return String(n);
        return n.toFixed(2);
    }

    // ============================================================
    // AUTO-DETECT CARD STRUCTURE
    // Scans the DOM for [data-tnp-ship] elements, walks up to the
    // parent card, and reads the <h3> header text as the label.
    // Reports once to shaver so the admin Shipping tab shows the
    // correct columns/labels for this domain.
    // ============================================================
    function detectAndReportCards(data) {
        if (!data || data.cards_detected || !data.domain_key || !data.api_url) return;

        var nodes = document.querySelectorAll('[data-tnp-ship]');
        if (!nodes.length) return;

        var seen = {};
        var cards = [];
        for (var i = 0; i < nodes.length; i++) {
            var slot = nodes[i].getAttribute('data-tnp-ship');
            if (seen[slot]) continue;
            seen[slot] = true;

            // Walk up to nearest card container to find the label
            var label = 'Card ' + slot;
            var parent = nodes[i].closest('.mt-product-card, .mt-tryit-banner');
            if (parent) {
                var h = parent.querySelector('h3') || parent.querySelector('h4');
                if (h) label = h.textContent.trim();
            }
            cards.push({ slot: slot, label: label });
        }

        if (!cards.length) return;

        // Send once to shaver
        try {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', data.api_url + '?action=save_card_config', true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.onload = function() {
                console.log('[TNP Shipping] card config reported:', cards);
            };
            xhr.send(JSON.stringify({ domain_key: data.domain_key, cards: cards }));
        } catch (e) {
            console.warn('[TNP Shipping] card config report failed:', e);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            init();
            detectAndReportCards(window.__TNP_SHIPPING__);
        });
    } else {
        init();
        detectAndReportCards(window.__TNP_SHIPPING__);
    }
})();
