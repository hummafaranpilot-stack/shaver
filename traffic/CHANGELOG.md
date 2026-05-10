# Traffic Detector — Changelog

## Fraud-scoring upgrade (capture.php)

The verdict logic was too forgiving: a Windows Chrome desktop UA-spoofing as
an iPhone (FB iOS app UA, but `Sec-Ch-Ua-Platform=Windows`, `navigator.platform=Win32`,
1920×1080 screen, Intel UHD GPU, Calibri/Segoe fonts, zero touches but 93 mouse moves)
scored **72/125 SUSPICIOUS** instead of being slammed as fraud.

This release adds 16 high-weight cross-checks that compare what the UA *claims*
against what the rest of the fingerprint actually shows. A coherent UA spoof
now scores deeply negative and triggers an auto-block recommendation.

### What changed

#### 1. New negative cross-checks (16, A–P)

Every check fires only when the UA claims iOS / iPhone / mobile but other
signals contradict it. Each contributes negative points when triggered.

| Code | Flag | Points | Trigger |
|------|------|-------:|---------|
| A | `IOS_UA_WITH_DESKTOP_HEADERS`     | -30 | iOS UA + any `Sec-Ch-Ua` header (real iOS WebKit never sends these) |
| B | `IOS_UA_WITH_WINDOWS_PLATFORM`    | -30 | iOS UA + `Sec-Ch-Ua-Platform: Windows` (smoking gun) |
| C | `IOS_UA_WITH_DESKTOP_MOBILE_FLAG` | -25 | iOS UA + `Sec-Ch-Ua-Mobile: ?0` |
| D | `NAVIGATOR_PLATFORM_MISMATCH`     | -25 | iPhone UA + `navigator.platform != "iPhone"` |
| E | `NAVIGATOR_VENDOR_MISMATCH`       | -25 | FBIOS UA + `navigator.vendor != "Apple Computer, Inc."` |
| F | `DESKTOP_RESOLUTION_ON_MOBILE_UA` | -20 | iPhone UA + `screen.width > 500` |
| G | `NON_RETINA_PIXEL_RATIO_ON_IPHONE`| -15 | iPhone UA + `devicePixelRatio < 2` |
| H | `NON_IOS_COLOR_DEPTH`             | -10 | iPhone UA + `screen.colorDepth != 30` |
| I | `NO_TOUCH_ON_MOBILE_UA`           | -25 | Mobile UA + 0 touch events + >10 mouse moves |
| J | `GPU_VENDOR_MISMATCH`             | -20 | iPhone UA + `webgl.unmasked_vendor != "Apple..."` |
| K | `DESKTOP_GPU_ON_MOBILE_UA`        | -20 | iPhone UA + Intel/NVIDIA/AMD/Direct3D/ANGLE in renderer |
| L | `WINDOWS_FONTS_ON_IPHONE`         | -15 | iPhone UA + Calibri/Cambria/Consolas/Segoe/MS Gothic/SimSun/Microsoft Sans Serif present |
| M | `PDF_VIEWER_ENABLED_ON_IOS`       | -10 | iPhone UA + `navigator.pdfViewerEnabled === true` |
| N | `DEVICEMEMORY_EXPOSED_ON_IOS`     | -10 | iPhone UA + `navigator.deviceMemory` is a value (iOS hides this) |
| O | `LINEAR_MOUSE_PATH_BOT_PATTERN`   | -10 | >20 mouse moves with `mouse_path_curvature == "linear"` |
| P | `CHROME_PLUGINS_ON_IOS`           | -15 | iOS UA + Chrome/Chromium/Edge PDF Viewer in plugins list |

#### 2. Existing checks 5/6/7 sharpened

**`ip_clean` (#5)** kept at +15 for clean. Added:
- `ip_dirty` (-25) — proxy / VPN / datacenter / `fraud_score >= 75`
- `recent_abuse` (-15) — IPQS recent-abuse flag

**`country_matches_campaign` (#6)** kept at +15 for match. Added:
- `country_mismatch` (-20) — campaign target ≠ IP country
- `country_high_fraud_region` (-30) — campaign target ∈ {US, GB, UK, CA, AU} AND IP country ∈ {PK, IN, BD, NG, RU, VN, ID, PH}. Replaces (does not stack with) `country_mismatch`.

**`timezone_matches_ip` (#7)** kept at +10 for match — but now neutralised (no points awarded or subtracted) when `country_mismatch` already fired, so the IP-country signal isn't double-counted.

#### 3. New verdict thresholds

Total positive points still cap at 125, but with 16 new negative checks the
floor drops to roughly -270.

| Points | Result |
|--------|--------|
| `< 0`        | **CRITICAL_FRAUD** — auto-block |
| smoking gun¹ | **FAIL** (forced — overrides PASS / SUSPICIOUS) |
| `≥ 90`       | **PASS** |
| `50–89`      | **SUSPICIOUS** |
| `0–49`       | **FAIL** |

¹ Any single triggered check with `points <= -25` (i.e. checks A, B, C, D, E, I, plus `ip_dirty`, `country_high_fraud_region`).

#### 4. Auto-block plumbing

When `result == CRITICAL_FRAUD`:

- Response header `X-Fraud-Verdict: BLOCK` is set on the capture response. Snippet.js can read this via `Access-Control-Expose-Headers` (already wired) and redirect if desired.
- A JSON line is appended to `traffic/logs/blocked_ips.json` (JSONL format) with timestamp, IP, domain_key, points, risk score, negative flags, and UA.

#### 5. Cross-origin host audit

When `$_SERVER['HTTP_HOST']` arrives as something other than the expected
detector subdomain (`shaver.trustednutraproduct.com`), a line is appended to
`traffic/logs/cross_origin.log`. Informational only — does not block.

#### 6. Verdict object schema

```json
"verdict": {
  "result": "CRITICAL_FRAUD" | "FAIL" | "SUSPICIOUS" | "PASS",
  "label": "string",
  "risk_score": 0-100,
  "checks_passed": <num>,
  "checks_total": <num>,
  "points_earned": <can be negative>,
  "points_possible": 125,
  "smoking_gun_triggered": true|false,
  "block_recommended": true|false,
  "flags": ["..."],
  "negative_flags": ["..."],
  "checks": [{ name, points, triggered, pass, type }]
}
```

`flags` keeps its legacy meaning (positive checks that failed to fire).
`negative_flags` is new (negative checks that did fire).

### Storage notes

The `traffic_visits.verdict` column is still `ENUM('PASS','SUSPICIOUS','FAIL')`.
`CRITICAL_FRAUD` is mapped to `FAIL` for that indexed column; the full
`CRITICAL_FRAUD` value lives in the `full_data` JSON column (`verdict.result`).
Query `JSON_EXTRACT(full_data, '$.verdict.result')` if you need to filter on
the full result.

### Untouched

- `snippet.js` — already captures every signal these new checks read.
- `migrate-traffic-detector.php` — schema unchanged; no migration required.
- `api.php` traffic_* endpoints — unchanged.
- UI (`index.html`, `view.html`) — unchanged. The detail modal already shows
  the full verdict object including `negative_flags` and the per-check breakdown.

### Test scenario expectation

Given the spoofed-iPhone visitor described above, the new logic should produce:

- `result: "CRITICAL_FRAUD"`
- `points_earned: < -100`
- `smoking_gun_triggered: true`
- `block_recommended: true`
- `negative_flags` includes at minimum: `IOS_UA_WITH_WINDOWS_PLATFORM`, `IOS_UA_WITH_DESKTOP_MOBILE_FLAG`, `NAVIGATOR_PLATFORM_MISMATCH`, `NAVIGATOR_VENDOR_MISMATCH`, `DESKTOP_RESOLUTION_ON_MOBILE_UA`, `NO_TOUCH_ON_MOBILE_UA`, `DESKTOP_GPU_ON_MOBILE_UA`, `WINDOWS_FONTS_ON_IPHONE`
