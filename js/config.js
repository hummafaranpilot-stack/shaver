/**
 * Multi-Tenant Shaver - Configuration & Helpers
 */

// ── Password Gate ──────────────────────────────────────────────────────────
(function() {
    var AUTH_KEY = 'shaver_auth_ok';
    if (localStorage.getItem(AUTH_KEY) === '1') return; // already authenticated

    // Hide page content immediately
    document.documentElement.style.visibility = 'hidden';

    document.addEventListener('DOMContentLoaded', function() {
        document.documentElement.style.visibility = '';

        var overlay = document.createElement('div');
        overlay.id = 'shaver-auth-gate';
        overlay.innerHTML = [
            '<div style="position:fixed;inset:0;background:linear-gradient(135deg,#1e1b4b 0%,#312e81 50%,#1e1b4b 100%);display:flex;align-items:center;justify-content:center;z-index:99999;">',
            '  <div style="background:#fff;border-radius:20px;padding:48px 44px;width:100%;max-width:380px;box-shadow:0 25px 60px rgba(0,0,0,0.4);text-align:center;font-family:\'Segoe UI\',sans-serif;">',
            '    <div style="width:64px;height:64px;background:linear-gradient(135deg,#7c3aed,#4f46e5);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">',
            '      <svg width="28" height="28" fill="none" viewBox="0 0 24 24"><path fill="#fff" d="M12 1a5 5 0 0 1 5 5v2h1a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2h1V6a5 5 0 0 1 5-5zm0 2a3 3 0 0 0-3 3v2h6V6a3 3 0 0 0-3-3zm0 9a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/></svg>',
            '    </div>',
            '    <h2 style="margin:0 0 6px;font-size:1.4rem;font-weight:800;color:#1e1b4b;">Shaver Dashboard</h2>',
            '    <p style="margin:0 0 28px;color:#64748b;font-size:0.88rem;">Enter your password to continue</p>',
            '    <input id="shaverPwdInput" type="password" placeholder="Password" autocomplete="current-password"',
            '      style="width:100%;box-sizing:border-box;padding:12px 16px;border:2px solid #e2e8f0;border-radius:10px;font-size:1rem;font-family:inherit;outline:none;transition:border 0.2s;margin-bottom:8px;"',
            '      oninput="document.getElementById(\'shaverPwdErr\').style.display=\'none\'"',
            '      onkeydown="if(event.key===\'Enter\')shaverCheckPwd()">',
            '    <p id="shaverPwdErr" style="display:none;color:#ef4444;font-size:0.82rem;margin:0 0 12px;">Incorrect password. Try again.</p>',
            '    <button onclick="shaverCheckPwd()"',
            '      style="width:100%;padding:12px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;border:none;border-radius:10px;font-size:1rem;font-weight:700;cursor:pointer;font-family:inherit;transition:opacity 0.2s;"',
            '      onmouseover="this.style.opacity=\'0.9\'" onmouseout="this.style.opacity=\'1\'">',
            '      Unlock',
            '    </button>',
            '  </div>',
            '</div>'
        ].join('');

        document.body.insertBefore(overlay, document.body.firstChild);

        // Focus input
        setTimeout(function() {
            var inp = document.getElementById('shaverPwdInput');
            if (inp) inp.focus();
        }, 50);
    });

    window.shaverCheckPwd = function() {
        var val = (document.getElementById('shaverPwdInput') || {}).value || '';
        if (val === '547547') {
            localStorage.setItem(AUTH_KEY, '1');
            var gate = document.getElementById('shaver-auth-gate');
            if (gate) gate.remove();
        } else {
            var err = document.getElementById('shaverPwdErr');
            if (err) err.style.display = 'block';
            var inp = document.getElementById('shaverPwdInput');
            if (inp) { inp.value = ''; inp.focus(); inp.style.border = '2px solid #ef4444'; setTimeout(function(){ inp.style.border = '2px solid #e2e8f0'; }, 1200); }
        }
    };
})();
// ──────────────────────────────────────────────────────────────────────────

var SHAVER_VERSION = '2.8.0';
console.log('%c[Shaver] v' + SHAVER_VERSION + ' loaded', 'color:#392C70;font-weight:bold;font-size:12px');

var API_URL = 'api.php';

// Silent cron heartbeat — triggers cron-mailer.php on page load (max once per 5 min)
(function() {
    var CRON_KEY = 'shaver_cron_last';
    var CRON_INTERVAL = 5 * 60 * 1000; // 5 minutes
    var last = parseInt(localStorage.getItem(CRON_KEY) || '0', 10);
    if (Date.now() - last > CRON_INTERVAL) {
        localStorage.setItem(CRON_KEY, String(Date.now()));
        fetch('cron-mailer.php?key=shaver_cron_2026', { method: 'GET' }).catch(function() {});
    }
})();

// Silent ping-cron heartbeat — triggers cron-pinger.php on page load (max once per minute).
// Runs on its own faster cadence so visitor IPs get pinged within ~1 min of arrival.
(function() {
    var PING_KEY = 'shaver_ping_cron_last';
    var PING_INTERVAL = 60 * 1000; // 1 minute
    var last = parseInt(localStorage.getItem(PING_KEY) || '0', 10);
    if (Date.now() - last > PING_INTERVAL) {
        localStorage.setItem(PING_KEY, String(Date.now()));
        fetch('cron-pinger.php?key=shaver_ping_2026', { method: 'GET' }).catch(function() {});
    }
})();

// Affiliate ID → Name lookup (loaded per domain from API)
var AFF_NAMES = {};

async function loadAffiliateNames(domainId) {
    if (!domainId) { AFF_NAMES = {}; return; }
    var result = await apiRequest(API_URL, 'get_affiliate_names', { domain_id: domainId });
    AFF_NAMES = (result.success && result.names) ? result.names : {};
}

function getAffName(affId) {
    if (!affId) return '';
    var name = AFF_NAMES[String(affId)];
    return name || '';
}

function formatAffDisplay(affId) {
    if (!affId || affId === '-') return '-';
    var name = getAffName(affId);
    if (name) return affId + ' <span style="color:#7f8c8d;font-weight:400;font-size:0.85em;">(' + name + ')</span>';
    return affId;
}

// Domain selector state
var currentDomainId = localStorage.getItem('selectedDomainId') || null;
var currentDomainLabel = localStorage.getItem('selectedDomainLabel') || '';
var currentDomainPlatform = localStorage.getItem('selectedDomainPlatform') || 'buygoods';

// API request helper
async function apiRequest(url, action, data = {}) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...data })
        });
        const result = await response.json().catch(function() { return {}; });
        if (!response.ok) throw new Error(result.error || 'HTTP ' + response.status);
        return result;
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, error: error.message };
    }
}

// Format helpers
function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num || 0);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

function formatTimeAgo(timestamp) {
    if (!timestamp) return '-';
    var ts = String(timestamp);
    // Database stores PKT (UTC+5) timestamps — tell JS the correct timezone
    if (!ts.includes('Z') && !ts.includes('+')) ts = ts.replace(' ', 'T') + '+05:00';
    var date = new Date(ts);
    var seconds = Math.floor((Date.now() - date.getTime()) / 1000);
    if (seconds < 0) seconds = 0;
    if (seconds < 5) return 'just now';
    if (seconds < 60) return seconds + ' seconds ago';
    var mins = Math.floor(seconds / 60);
    if (mins === 1) return '1 minute ago';
    if (mins < 60) return mins + ' minutes ago';
    var hrs = Math.floor(mins / 60);
    if (hrs === 1) return '1 hour ago';
    if (hrs < 24) return hrs + ' hours ago';
    var days = Math.floor(hrs / 24);
    if (days === 1) return '1 day ago';
    return days + ' days ago';
}

function formatDuration(seconds) {
    seconds = Math.round(seconds || 0);
    if (seconds < 60) return seconds + 's';
    if (seconds < 3600) return Math.floor(seconds / 60) + 'm ' + (seconds % 60) + 's';
    return Math.floor(seconds / 3600) + 'h ' + Math.floor((seconds % 3600) / 60) + 'm';
}

function showLoading(elementId) {
    const el = document.getElementById(elementId);
    if (el) el.innerHTML = '<div class="text-center p-3"><i class="fa fa-spinner fa-spin"></i> Loading...</div>';
}

function showError(elementId, message) {
    const el = document.getElementById(elementId);
    if (el) el.innerHTML = `<div class="alert alert-danger">${message}</div>`;
}

// Inject dropdown styles once (self-contained, no external CSS dependency)
(function() {
    if (document.getElementById('ds-dropdown-styles')) return;
    var s = document.createElement('style');
    s.id = 'ds-dropdown-styles';
    s.textContent = [
        '.ds-dropdown{position:relative;display:inline-block;min-width:240px;max-width:400px;font-family:inherit}',
        '.ds-dropdown-trigger{display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%;padding:9px 14px;border:2px solid #392C70;border-radius:8px;background:#fff;color:#2c3e50;font-size:13px;font-family:inherit;cursor:pointer;transition:border-color .2s,box-shadow .2s;white-space:nowrap;text-align:left}',
        '.ds-dropdown-trigger:hover{border-color:#5940a8;box-shadow:0 2px 8px rgba(57,44,112,.12)}',
        '.ds-dropdown.open .ds-dropdown-trigger{border-color:#5940a8;box-shadow:0 0 0 3px rgba(57,44,112,.15)}',
        '.ds-trigger-content{display:flex;align-items:center;gap:8px;overflow:hidden;text-overflow:ellipsis}',
        '.ds-chevron{flex-shrink:0;color:#392C70;transition:transform .2s}',
        '.ds-dropdown.open .ds-chevron{transform:rotate(180deg)}',
        '.ds-dropdown-menu{display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;min-width:100%;max-height:320px;overflow-y:auto;background:#fff;border:2px solid #392C70;border-radius:8px;box-shadow:0 8px 24px rgba(57,44,112,.18);z-index:9999;padding:4px 0}',
        '.ds-dropdown.open .ds-dropdown-menu{display:block}',
        '.ds-dropdown-group{padding:8px 14px 4px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7f8c8d;border-top:1px solid #f0f0f0;margin-top:2px}',
        '.ds-dropdown-item + .ds-dropdown-group{border-top:1px solid #f0f0f0}',
        '.ds-dropdown-group:first-child{border-top:none;margin-top:0}',
        '.ds-dropdown-item{display:flex;align-items:center;gap:8px;padding:8px 14px;cursor:pointer;font-size:13px;color:#2c3e50;transition:background .15s;white-space:nowrap}',
        '.ds-dropdown-item:hover{background:#f8f6ff}',
        '.ds-dropdown-item.active{background:#ede8ff;font-weight:600}',
        '.ds-badge{display:inline-block;padding:2px 7px;border-radius:10px;font-size:10px;font-weight:700;letter-spacing:.3px;line-height:1.4;flex-shrink:0}',
        '.ds-badge-bg{background:#e3f2fd;color:#1565c0}',
        '.ds-badge-ds{background:#f3e8ff;color:#7c3aed}',
        '.ds-badge-cb{background:#fef3e2;color:#e67e22}',
        '.ds-item-label{overflow:hidden;text-overflow:ellipsis}',
        '.ds-item-path{color:#95a5a6;font-size:12px;margin-left:2px}',
        '@media(max-width:768px){.ds-dropdown{max-width:100%;width:100%}}'
    ].join('\n');
    document.head.appendChild(s);
})();

// Domain selector initialization — custom dropdown with platform badges
async function initDomainSelector(onChangeCallback) {
    var nativeSelect = document.getElementById('domainSelector');
    if (!nativeSelect) return;

    var result = await apiRequest(API_URL, 'get_domains');
    if (!result.success || !result.domains.length) {
        nativeSelect.innerHTML = '<option value="">No domains registered</option>';
        return;
    }

    // Hide native select
    nativeSelect.style.display = 'none';

    // Group domains by platform
    var bgDomains = [];
    var ds24Domains = [];
    var cbDomains = [];
    result.domains.forEach(function(d) {
        var p = d.platform || 'buygoods';
        if (p === 'clickbank') cbDomains.push(d);
        else if (p === 'digistore24') ds24Domains.push(d);
        else bgDomains.push(d);
    });

    // Extract short path from URL for display
    function shortPath(url) {
        try {
            var u = new URL(url);
            var p = u.pathname.replace(/\/+$/, '');
            return p || '/';
        } catch (e) { return ''; }
    }

    // Build all items data
    var allItems = [{ id: '', label: 'All Domains', platform: '', path: '' }];
    bgDomains.forEach(function(d) {
        allItems.push({ id: d.id, label: d.label, platform: 'buygoods', path: shortPath(d.domain_url) });
    });
    ds24Domains.forEach(function(d) {
        allItems.push({ id: d.id, label: d.label, platform: 'digistore24', path: shortPath(d.domain_url) });
    });
    cbDomains.forEach(function(d) {
        allItems.push({ id: d.id, label: d.label, platform: 'clickbank', path: shortPath(d.domain_url) });
    });

    // Build badge HTML
    function badgeHTML(platform) {
        if (platform === 'buygoods') return '<span class="ds-badge ds-badge-bg">BG</span>';
        if (platform === 'digistore24') return '<span class="ds-badge ds-badge-ds">DS</span>';
        if (platform === 'clickbank') return '<span class="ds-badge ds-badge-cb">CB</span>';
        return '';
    }

    // Build item inner HTML
    function itemHTML(item) {
        var badge = badgeHTML(item.platform);
        var path = item.path ? '<span class="ds-item-path">' + item.path + '</span>' : '';
        return badge + '<span class="ds-item-label">' + item.label + '</span>' + path;
    }

    // Create custom dropdown
    var wrapper = document.createElement('div');
    wrapper.className = 'ds-dropdown';

    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'ds-dropdown-trigger';
    trigger.innerHTML = '<span class="ds-trigger-content">All Domains</span><svg class="ds-chevron" width="12" height="8" viewBox="0 0 12 8"><path d="M1 1l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

    var menu = document.createElement('div');
    menu.className = 'ds-dropdown-menu';

    // Build menu items grouped by platform
    // "All Domains" item
    var allDiv = document.createElement('div');
    allDiv.className = 'ds-dropdown-item';
    allDiv.dataset.id = '';
    allDiv.dataset.platform = '';
    allDiv.dataset.label = 'All Domains';
    allDiv.innerHTML = '<span class="ds-item-label" style="font-weight:600;">All Domains</span>';
    menu.appendChild(allDiv);

    function addGroup(title, domains, platform) {
        if (domains.length === 0) return;
        var header = document.createElement('div');
        header.className = 'ds-dropdown-group';
        header.textContent = title;
        menu.appendChild(header);
        domains.forEach(function(d) {
            var item = document.createElement('div');
            item.className = 'ds-dropdown-item';
            item.dataset.id = d.id;
            item.dataset.platform = platform;
            item.dataset.label = d.label;
            item.innerHTML = itemHTML({ label: d.label, platform: platform, path: shortPath(d.domain_url) });
            menu.appendChild(item);
        });
    }

    addGroup('BuyGoods', bgDomains, 'buygoods');
    addGroup('Digistore24', ds24Domains, 'digistore24');
    addGroup('ClickBank', cbDomains, 'clickbank');

    wrapper.appendChild(trigger);
    wrapper.appendChild(menu);
    nativeSelect.parentNode.insertBefore(wrapper, nativeSelect.nextSibling);

    // Selection logic
    async function selectItem(id, platform, label, triggerCallback) {
        currentDomainId = id || null;
        currentDomainLabel = label;
        currentDomainPlatform = platform || 'buygoods';
        localStorage.setItem('selectedDomainId', currentDomainId || '');
        localStorage.setItem('selectedDomainLabel', currentDomainLabel);
        localStorage.setItem('selectedDomainPlatform', currentDomainPlatform);

        // Update trigger display
        var badge = badgeHTML(platform);
        trigger.querySelector('.ds-trigger-content').innerHTML = badge + '<span class="ds-item-label">' + label + '</span>';

        // Update active state
        var items = menu.querySelectorAll('.ds-dropdown-item');
        for (var i = 0; i < items.length; i++) {
            items[i].classList.toggle('active', items[i].dataset.id === (id || ''));
        }

        // Load affiliate names for this domain
        await loadAffiliateNames(id);

        if (triggerCallback && onChangeCallback) onChangeCallback();
    }

    // Click handlers on items
    menu.addEventListener('click', async function(e) {
        var item = e.target.closest('.ds-dropdown-item');
        if (!item) return;
        wrapper.classList.remove('open');
        await selectItem(item.dataset.id, item.dataset.platform, item.dataset.label, true);
    });

    // Toggle dropdown
    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        // Close any other open dropdowns
        document.querySelectorAll('.ds-dropdown.open').forEach(function(d) {
            if (d !== wrapper) d.classList.remove('open');
        });
        wrapper.classList.toggle('open');
    });

    // Close on outside click
    document.addEventListener('click', function(e) {
        if (!wrapper.contains(e.target)) wrapper.classList.remove('open');
    });

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') wrapper.classList.remove('open');
    });

    // Restore previous selection or auto-select first
    if (currentDomainId) {
        var found = allItems.filter(function(i) { return String(i.id) === String(currentDomainId); })[0];
        if (found) {
            await selectItem(found.id, found.platform, found.label, false);
        } else {
            await selectItem('', '', 'All Domains', false);
        }
    } else if (result.domains.length > 0) {
        var first = bgDomains.length > 0 ? bgDomains[0] : ds24Domains.length > 0 ? ds24Domains[0] : cbDomains[0];
        await selectItem(first.id, first.platform || 'buygoods', first.label, false);
    }

    if (onChangeCallback) onChangeCallback();
}
