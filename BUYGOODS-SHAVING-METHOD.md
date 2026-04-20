# BuyGoods Affiliate Shaving — Complete Technical Reference

> **Platform:** BuyGoods Only  
> **Script:** `shaving-check.php` (served as JavaScript)  
> **Method:** Cookie-Poll-Clear-Redirect (v3)  
> **BG Account:** `11943`  
> **Product Codes:** `met2v2, met3v2, met6v2, met2v2s, met6v2s, met3v2s`  
> **Conversion Token:** `6bea6c7c7a71a36b83e176af6f6189de`

---

## 1. How It Is Injected

The script is loaded as a `<script src="...">` tag on the landing page. It is served by PHP but outputs pure JavaScript. PHP pre-bakes the active shaving sessions directly into the JS bundle at request time — no extra API call needed to check sessions.

```
https://your-server.com/shaving-check.php
→ Content-Type: application/javascript
→ Active sessions embedded as JSON variable inside JS
```

---

## 2. Active Session Source

Sessions are pulled from the database **at PHP render time**, not at runtime:

```sql
SELECT id, aff_id, sub_id, replace_mode, replace_aff_id, replace_sub_id
FROM shaving_sessions
WHERE active = 1
```

Each session object injected into JS:

| JS Field | DB Column | Type | Purpose |
|---|---|---|---|
| `id` | `id` | varchar(50) | Session identifier |
| `affId` | `aff_id` | varchar(100) | Affiliate ID to shave |
| `subId` | `sub_id` | varchar(100) | Sub-ID filter (optional) |
| `replaceMode` | `replace_mode` | bool | `true` = replace, `false` = remove |
| `replaceAffId` | `replace_aff_id` | varchar(100) | Replacement affiliate ID |
| `replaceSubId` | `replace_sub_id` | varchar(100) | Replacement sub-ID |

---

## 3. Timing Configuration

| Constant | Value | Purpose |
|---|---|---|
| `POLL_MS` | `500 ms` | Cookie count check interval |
| `STABLE_SECS` | `3 seconds` | How long cookie count must be unchanged |
| `MAX_WAIT_MS` | `15,000 ms` | Hard timeout before proceeding regardless |
| `SHAVER_FLAG` | `_shaver_cleaned` | sessionStorage key — loop prevention |

---

## 4. URL Parameters Read

| Parameter | Also Checks | Description |
|---|---|---|
| `aff_id` | `affid` | Affiliate ID |
| `subid` | `sub_id` | Sub-ID / tracking sub-param |
| `utm_source` | `source`, `ref` | Traffic source (logged, not shaved) |
| `sessid2` | URL param fallback | BuyGoods session ID (if cookie not set yet) |

---

## 5. Full Shaving Flow (Step by Step)

```
Visitor lands on page with ?aff_id=XXXXX
          │
          ▼
[LOOP CHECK] sessionStorage._shaver_cleaned === '1' ?
    YES → POST-REDIRECT flow (see Section 8)
    NO  → continue
          │
          ▼
[PAGE TYPE CHECK]
    /upsell, /thankyou, /thank-you, /thank_you,
    /confirmation, /order-confirmation → SKIP shaving, just log traffic
          │
          ▼
[AFF_ID PRESENT?]
    NO  → inject BG tracking normally, log traffic (was_shaved=0)
    YES → continue
          │
          ▼
[SAVE TO sessionStorage]
    _shaver_aff_id = affId
    _shaver_sub_id = subId
          │
          ▼
[SESSION MATCH] compare affId (+subId if set) against active sessions
    NO MATCH → inject BG normally, log traffic (was_shaved=0)
    MATCH    → continue
          │
          ▼
[LOG TRAFFIC] → api.php  (was_shaved=1, shaving_session_id=session.id)
[TRACK VISIT] → api.php  (action: track_visit)
          │
          ▼
══════════ STEP 1 ══════════
[INJECT BG TRACKING with ORIGINAL dirty aff_id]
  URL: https://tracking.buygoods.com/track/?a=11943
       &firstcookie=0
       &tracking_redirect=
       &referrer={document.referrer}
       &sessid2={cookie:sessid2}
       &product=met2v2,met3v2,met6v2,met2v2s,met6v2s,met3v2s
       &vid1=&vid2=&vid3=
       &caller_url={window.location.href}

  → BuyGoods sets cookie: sessid2 (their session tracker)
  → Conversion iframe also fired (BG_CONVERSION_TOKEN)
          │
          ▼
══════════ STEP 2 ══════════
[COOKIE POLL — waitForCookiesThenClean()]
  Every 500ms: count document.cookie entries
  Wait until count is STABLE for 3 consecutive seconds
  OR 15 seconds max timeout → proceed anyway
          │
          ▼
══════════ STEP 3 ══════════
[SEND BEFORE SNAPSHOT] → api.php (action: log_shave_snapshot, phase: before)
  Captures: all cookies, cookie count, sessid2 value, full URL, URL params
          │
          ▼
[CLEAR ALL COOKIES — clearAllCookies()]
  Iterates every cookie name in document.cookie
  Expires each with: Thu, 01 Jan 1970 00:00:00 UTC
  Tries every domain variant:
    - '' (bare)
    - hostname (e.g. example.com)
    - .hostname (e.g. .example.com)
    - parent domains (e.g. .trustednutraproduct.com)
  Tries every path: '/', current pathname, ''
  Logs any cookies that survived (can't be cleared via JS)
          │
          ▼
[SET LOOP PREVENTION]
  sessionStorage.setItem('_shaver_cleaned', '1')
          │
          ▼
══════════ STEP 4 ══════════
[BUILD REDIRECT URL]

  MODE: REPLACE
    url.searchParams.set('aff_id', session.replaceAffId)
    url.searchParams.set('subid', session.replaceSubId)   ← if set
    url.searchParams.delete('subid')                       ← if not set
    → Visitor redirected with YOUR affiliate ID

  MODE: REMOVE
    url.searchParams.delete('aff_id')
    url.searchParams.delete('affid')
    url.searchParams.delete('subid')
    url.searchParams.delete('sub_id')
    → Visitor redirected with CLEAN URL (no affiliate credit)

[REDIRECT] → window.location.href = url.toString()
```

---

## 6. Cookies — Complete Reference

### BuyGoods Platform Cookies

| Cookie Name | Set By | Purpose | Where Used |
|---|---|---|---|
| `sessid2` | BuyGoods tracking script | BG's visitor session identifier | Appended to all `buygoods.com` links; read by `ReadCookie('sessid2')`; sent with conversion iframe |

> **Critical Note:** `sessid2` is what BuyGoods uses to attribute conversions. The entire shave works by:
> 1. Letting BG set `sessid2` under the target affiliate's aff_id
> 2. Wiping `sessid2` (along with all other cookies) before the visitor clicks buy
> 3. Reloading the page — BG re-issues a fresh `sessid2` under the new/no aff_id

### Shaver Internal Cookies / Storage

| Key | Storage Type | Value | Purpose |
|---|---|---|---|
| `_shaver_cleaned` | `sessionStorage` | `'1'` | Loop prevention — marks that shave has already fired; cleared on post-redirect visit |
| `_shaver_aff_id` | `sessionStorage` | Original aff_id string | Passed through to upsell/thankyou pages for logging |
| `_shaver_sub_id` | `sessionStorage` | Original sub_id string | Passed through to upsell/thankyou pages for logging |
| `_behavior_session_id` | `sessionStorage` | `sess_[timestamp]_[random]` | Unique behavioral session UUID for analytics |

### Window Globals (Runtime Only)

| Variable | Type | Purpose |
|---|---|---|
| `window.__shavingSession` | Object | Currently matched session object |
| `window.__shavingOriginalAffId` | String | Original affiliate ID before shave |
| `window.__shavingOriginalSubId` | String | Original sub-ID before shave |
| `window.__shavingLoaded` | Boolean | Marks script has executed |
| `window.__behaviorTracking` | Object | Full behavioral tracking state |
| `window.__pendingTrafficPayload` | String | Fallback sendBeacon payload |
| `window.ReadCookie` | Function | Exposed globally for BuyGoods compat |

---

## 7. Session Tracking Methods (6 Total)

### Method 1 — IP + User-Agent (Snapshot Matching)

Used to link the **before** and **after** snapshots across the redirect.

```sql
SELECT id FROM shave_snapshots
WHERE ip_address = ?
  AND domain_id = ?
  AND phase = 'before'
  AND matched_id IS NULL
  AND created_at >= NOW() - INTERVAL 60 SECOND
ORDER BY created_at DESC LIMIT 1
```

- Match window: **60 seconds**
- Triggered: 5 seconds after post-redirect page load
- Location: `api.php` lines 1651–1658

---

### Method 2 — BuyGoods `sessid2` Cookie

- Set by: `https://tracking.buygoods.com/track/?a=11943&...`
- Read by: `ReadCookie('sessid2')`
- Fallback: `URLSearchParams.get('sessid2')` or `URLSearchParams.get('sessid')`
- Appended to: every `buygoods.com` link on the page (via `ensureSessid2OnLinks()`)
- Also used in: conversion iframe `src` parameter
- **This is the primary BuyGoods attribution cookie — wiping it is the shave**

---

### Method 3 — sessionStorage Keys

| Key | Lifetime | Purpose |
|---|---|---|
| `_shaver_cleaned` | Tab session | Prevents the redirect from looping back through the shaver |
| `_shaver_aff_id` | Tab session | Carries original aff_id to upsell/thankyou pages |
| `_shaver_sub_id` | Tab session | Carries original sub_id to upsell/thankyou pages |
| `_behavior_session_id` | Tab session | Behavioral analytics session UUID |

---

### Method 4 — Session UUID (Behavioral)

- Format: `sess_` + `Date.now()` + `_` + `Math.random().toString(36).substr(2,9)`
- Example: `sess_1712345678901_a7xkp3z`
- Stored: `sessionStorage._behavior_session_id`
- Sent: with every `log_traffic`, `log_behavior_event`, `update_session_metrics` API call
- Links: `affiliate_traffic.session_uuid` → `behavior_tracking.session_uuid`

---

### Method 5 — Database Sessions

**Table: `shaving_sessions`**

| Column | Type | Description |
|---|---|---|
| `id` | varchar(50) | Session primary key |
| `aff_id` | varchar(100) | Target affiliate to shave |
| `sub_id` | varchar(100) | Optional sub-ID filter |
| `replace_mode` | tinyint(1) | 1 = replace, 0 = remove |
| `replace_aff_id` | varchar(100) | Replacement aff_id |
| `replace_sub_id` | varchar(100) | Replacement sub_id |
| `start_time` | bigint | Unix timestamp session started |
| `stop_time` | bigint | Unix timestamp session ended |
| `visits` | int | Total matched visits counter |
| `clicks` | int | Total matched clicks counter |
| `status` | enum | `active` or `stopped` |

**Table: `shave_snapshots`** (before/after comparison)

| Column | Type | Description |
|---|---|---|
| `id` | int | Row primary key |
| `domain_id` | int | Domain identifier |
| `ip_address` | varchar(45) | Visitor IP (used for matching) |
| `user_agent` | varchar(512) | Browser UA (used for matching) |
| `phase` | enum | `before` or `after` |
| `session_id` | int | Linked shaving session |
| `aff_id` | varchar(100) | Original affiliate ID |
| `sub_id` | varchar(100) | Original sub-ID |
| `mode` | varchar(20) | `replace` or `remove` |
| `replace_aff_id` | varchar(100) | What it was replaced with |
| `replace_sub_id` | varchar(100) | Sub-ID replacement |
| `platform` | varchar(20) | `buygoods` (hardcoded) |
| `url` | text | Full page URL |
| `sessid2` | varchar(200) | BG session cookie value at snapshot time |
| `cookies` | JSON | All cookies as key-value object |
| `cookie_count` | int | Count of cookies at snapshot time |
| `url_params` | JSON | All URL query parameters |
| `matched_id` | int | Links `after` row → `before` row |
| `created_at` | timestamp | Snapshot creation time |

---

### Method 6 — Browser Fingerprinting / Bot Detection

Captured in `window.__behaviorTracking` and sent with every traffic log.

| Signal | Flag Name | Detection Logic |
|---|---|---|
| WebDriver active | `webdriver` | `navigator.webdriver === true` |
| No plugins | `no_plugins` | `navigator.plugins.length === 0` |
| No languages | `no_languages` | `!navigator.languages \|\| length === 0` |
| Missing Chrome object | `missing_chrome` | Chrome UA but no `window.chrome` |
| Headless Chrome | `headless_chrome` | `/HeadlessChrome/.test(UA)` |
| PhantomJS | `phantomjs` | `window.callPhantom \|\| window._phantom` |

Additional signals captured:

| Field | How Detected |
|---|---|
| `isIframe` | `window.self !== window.top` |
| `hasAdblock` | Bait `<div class="ad ads adsbox">` visibility check |
| `pageLoadTime` | `performance.timing.loadEventEnd - navigationStart` |
| `screen_width/height` | `window.screen.width/height` |
| `viewport_width/height` | `window.innerWidth / window.innerHeight` |
| `jsErrorCount` | `window.addEventListener('error', ...)` counter |
| `jsErrors` | Last 5 JS errors (message, file, line) |

---

## 8. Post-Redirect Flow (After Shave)

Once the visitor is redirected back to the same URL (now with clean/replaced params):

1. Script loads again — detects `sessionStorage._shaver_cleaned === '1'`
2. Clears the flag: `sessionStorage.removeItem('_shaver_cleaned')`
3. Logs to console: `[Shaver] Post-redirect clean visit`
4. Waits **5 seconds** (for BG to re-set fresh cookies under the new/no aff_id)
5. Sends **AFTER snapshot** → `api.php` (action: `log_shave_snapshot`, phase: `after`)
6. Server matches AFTER → BEFORE by IP within 60 seconds → sets `matched_id`
7. Normal BG tracking fires with the clean/replaced aff_id

---

## 9. sessid2 Link Safety Net

After BG tracking loads, the script runs `ensureSessid2OnLinks()` at:
- **300ms** after load
- **1500ms** after load
- **3000ms** after load
- **On every DOM mutation** (MutationObserver watches `document.body` for 10 seconds)
- **On every click** to a `buygoods.com` link (as a final fallback)

It finds all `<a href="*buygoods.com*">` links and ensures `sessid2={cookie_value}` is in the query string. This prevents attribution loss if BG's own script doesn't rewrite links in time.

---

## 10. Traffic Logging — API Payload

Every visit (shaved or not) is logged to `affiliate_traffic` via `api.php`:

```json
{
  "action": "log_traffic",
  "aff_id": "XXXXX",
  "sub_id": "YYYYY",
  "page_url": "https://...",
  "page_type": "landing | upsell | thankyou",
  "sessid2": "value from cookie or URL param",
  "referrer": "https://... or 'direct'",
  "user_agent": "Mozilla/5.0 ...",
  "was_shaved": 1,
  "shaving_session_id": 42,
  "session_uuid": "sess_1712345678901_a7xkp3z",
  "screen_width": 1920,
  "screen_height": 1080,
  "viewport_width": 1280,
  "viewport_height": 800,
  "is_bot": 0,
  "bot_flags": null,
  "is_iframe": 0
}
```

**Fallback:** If the XHR fails (page navigating away), `navigator.sendBeacon()` fires the same payload via `beforeunload`.

---

## 11. Behavioral Events Tracked

Events are queued until `traffic_id` is returned from `log_traffic`, then flushed:

| Event Type | Triggered When | Key Data |
|---|---|---|
| `scroll` | 25%, 50%, 75%, 90% depth milestones | `scrollDepth`, `milestone: true` |
| `click` | Any `<a>`, `<button>`, `.cp-btn`, `.mt-buy-now-btn` | `buttonText`, `targetUrl`, `clickX/Y`, `scrollDepthAtClick`, `timeFromLanding` |
| `hover` | Mouse over buy buttons > 500ms | `element: 'buy-btn'`, `duration` (ms) |
| `checkout_reached` | Click on `buygoods.com` link | `checkoutUrl`, `timeToCheckout`, `scrollDepthAtCheckout`, `clicksBeforeCheckout` |
| `tab_hidden` | `visibilitychange` hidden | `visibleDuration` |
| `tab_visible` | `visibilitychange` visible | — |

Session metrics are updated every **30 seconds** via `setInterval(updateSessionMetrics, 30000)` and on `beforeunload`.

---

## 12. Two Shaving Modes Summary

| Mode | `replace_mode` DB | aff_id in redirect | sub_id in redirect | Result |
|---|---|---|---|---|
| **REPLACE** | `1` / `true` | `session.replaceAffId` | `session.replaceSubId` (or deleted) | Conversion goes to YOUR aff account |
| **REMOVE** | `0` / `false` | deleted | deleted | Conversion goes to merchant (no affiliate) |

---

## 13. Key File Locations

| File | Role |
|---|---|
| `shaving-check.php` | Primary shaver — served as JS to landing pages |
| `api.php` | Backend — handles all session/traffic/snapshot API calls |
| `DATABASE-SETUP.sql` | Full schema reference |
| `config.php` | DB credentials, CORS headers |

| Section | File : Lines |
|---|---|
| BG tracking injection | `shaving-check.php:594–630` |
| Cookie poll loop | `shaving-check.php:741–776` |
| Cookie clear + redirect | `shaving-check.php:778–808` |
| Before/After snapshot | `shaving-check.php:661–736` |
| Main decision logic | `shaving-check.php:813–878` |
| sessid2 link updater | `shaving-check.php:635–656` |
| Traffic logging (server) | `api.php:440–505` |
| Snapshot matching (server) | `api.php:1651–1658` |
