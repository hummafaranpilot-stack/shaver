# FB Traffic Detector

Standalone visitor fingerprinting + bot-detection tool inside the shaver dashboard.

- **Lives at:** `https://shaver.trustednutraproduct.com/traffic/`
- **Backend:** MySQL (shaver DB) — tables `traffic_domains` and `traffic_visits`
- **API endpoints:** added to `api.php` as `traffic_*` actions (called via `apiRequest`)
- **Style:** matches every other shaver page (Poppins, mint bg, gradient sidebar, custom dropdowns)
- **Auto-deploy:** push to `main` → Hostinger pulls → live

---

## First-time setup (run once after deploy)

1. Visit `https://shaver.trustednutraproduct.com/migrate-traffic-detector.php` — creates the two tables. You should see `[OK] Created table …` for both. Then **delete that file** from the repo.
2. (Optional) Confirm the parent shaver IPQS keys still work — they're reused as-is.

---

## Daily use

1. Open `https://shaver.trustednutraproduct.com/traffic/` (sidebar → Traffic Detector → Domain Registry).
2. **Register a Domain:**
   - Label (e.g. `Meta Trim Lander 1`)
   - Domain URL (e.g. `https://your-lander.com`)
   - Pages (optional, informational only)
3. Submit → snippet card appears at top with the embed line:
   ```html
   <script src="https://shaver.trustednutraproduct.com/traffic/snippet.js?d=YOUR_KEY" async></script>
   ```
4. Paste in the `<head>` (or before `</body>`) of every page you want tracked.
5. After a real visit, see it from the dashboard's **Logs** button or the **All Visits** sidebar item.

### Debug mode

Add `?debug=1` to any lander URL. After 30 seconds a green **Copy JSON** button appears bottom-right with the captured payload.

---

## What gets captured (9 sections)

| # | Section | Where |
|---|---------|-------|
| 1 | URL params (utm_*, fbclid, sub1-5, full query) | snippet.js |
| 2 | HTTP headers (host, UA, referer, sec-ch-* with `"absent"` markers) | capture.php |
| 3 | UA decode (iOS/Android version, FBAN/FBAV/FBDV/FBLC, browser, device model) | capture.php |
| 4 | IPQS lookup (ISP, ASN, geo, proxy/VPN/TOR/datacenter, fraud score) | capture.php |
| 5 | Browser/device (navigator + screen + timezone + plugins) | snippet.js |
| 6 | WebGL / canvas / audio fingerprint + font list | snippet.js |
| 7 | 30-sec behavioral (touches, scroll pattern, mouse path, idle gaps, keystroke intervals, form focus times) | snippet.js |
| 8 | Facebook signals (fbclid validation, l.facebook.com h-token, FB-app UA flags, campaign geo/device/objective) | snippet.js + capture.php |
| 9 | Bot detection (webdriver, headless markers, real GPU, touch consistency, sec-ch-ua consistency) | snippet.js + capture.php |

Indexable fields (verdict, country, fbclid, IP, captured_at, etc.) live as columns; the full 9-section payload is preserved in the `full_data` JSON column for the detail modal.

---

## Verdict scoring

16 weighted checks (max 125 pts):

| Threshold       | Result           |
|-----------------|------------------|
| ≥ 95 pts        | **PASS** — real traffic |
| 60 – 94 pts     | **SUSPICIOUS** — manual review |
| < 60 pts        | **FAIL** — likely spoofed/bot |

The verdict object includes `risk_score`, `flags` (failed check names), and per-check breakdown — all visible in the detail modal.

---

## Files

| File | Purpose |
|---|---|
| `index.html` | Domain registration UI + dashboard (matches shaver style) |
| `view.html` | Visit log viewer with verdict / period filters + JSON modal |
| `snippet.js` | Self-contained client capture (~40 KB) |
| `capture.php` | POST endpoint — IPQS, UA decode, verdict, INSERT into `traffic_visits` |
| `.htaccess` | HTTPS, CORS for snippet.js, DirectoryIndex |
| **`../api.php`** | All traffic_* endpoints live in the main shaver router |
| **`../migrate-traffic-detector.php`** | One-time table creator (delete after running) |

## Limits + safety

- Body cap on `capture.php`: 200 KB.
- IPQS uses parent shaver's failover (2 keys, 70 calls/day total) via `ipqs.php`.
- All visits scoped by `traffic_domain_id` — disabled or deleted domains stop accepting captures.
- Auth: relies on the existing shaver password gate via `js/config.js` (no separate auth setup needed).
