# FB Traffic Detector

Standalone visitor fingerprinting + bot-detection tool.

- **Lives at:** `https://shaver.trustednutraproduct.com/traffic/`
- **Embed snippet on any landing domain** — captures 9 sections of data, runs 16 verdict checks, saves a JSON log per visit.
- **Auto-deploy:** push to `main` → Hostinger pulls → instantly live.

---

## Quick start

1. Open `https://shaver.trustednutraproduct.com/traffic/`.
2. Click **Register a Domain**, enter:
   - **Label** — e.g. `Meta Trim Lander 1`
   - **Domain URL** — e.g. `https://your-lander.com`
   - **Pages** — optional, one per line, just for your own reference
3. Hit **Generate Snippet** → copy the line:
   ```html
   <script src="https://shaver.trustednutraproduct.com/traffic/snippet.js?d=YOUR_KEY" async></script>
   ```
4. Paste it in the `<head>` (or before `</body>`) of every page you want tracked.
5. Visit your lander → after 30 seconds, a visit is recorded. View it from the dashboard's **Logs** button.

---

## Debug mode

Add `?debug=1` to any lander URL. After 30 seconds, a green **Copy JSON** button appears bottom-right with the captured data ready to paste anywhere.

---

## What gets captured (9 sections)

1. **URL params** — utm_*, campaign_id, adset_id, ad_id, fbclid, sub1–5, full query
2. **HTTP headers** — host, UA, referer, sec-ch-* (with `"absent"` markers)
3. **UA decode** — iOS/Android version, FB-app fields (FBAV, FBDV, FBLC, …), browser, OS, device model
4. **IP intelligence** — IPQS lookup: ISP, ASN, geo, proxy/VPN/TOR/datacenter flags, fraud score
5. **Browser/device** — full navigator + screen + timezone + plugins
6. **WebGL / canvas / audio fingerprint + font list**
7. **30-sec behavioral** — touches, clicks, mouse path, scroll pattern, idle gaps, keystroke intervals, form focus times
8. **Facebook signals** — fbclid validation, l.facebook.com h-token, FB-app UA flags, campaign geo/device/objective from utm_campaign
9. **Bot detection checks** — webdriver, headless markers, real GPU, touch consistency, sec-ch-ua consistency, etc.

Each visit produces a JSON file at `logs/<domain_key>/visitor_YYYY-MM-DD_<ts>_<rand>.json` plus an entry in the dashboard.

---

## Verdict scoring

16 weighted checks (max 125 pts):

| Threshold       | Result         |
|-----------------|----------------|
| ≥ 95 pts        | **PASS** — real traffic |
| 60 – 94 pts     | **SUSPICIOUS** — manual review |
| < 60 pts        | **FAIL** — likely spoofed/bot |

The verdict object also includes `risk_score` (0–100), `flags` (failed check names), and `checks` (full per-check breakdown).

---

## Files

| File | Purpose |
|---|---|
| `index.php` | Domain registration UI + dashboard |
| `snippet.js` | Self-contained client capture (~40 KB) |
| `capture.php` | POST endpoint — IPQS, UA decode, verdict, save |
| `view.php` | Per-domain log viewer with filter + JSON modal |
| `config.php` | IPQS keys, paths, limits |
| `domains.json` | Registered domain list (auto-managed) |
| `.htaccess` | HTTPS, CORS, file protection |
| `logs/` | Visitor JSON logs (gitignored) |

## Limits + safety

- Body cap: 200 KB — anything larger is rejected.
- Rate limit: 100 req/IP/hour (file-based with `flock`).
- IPQS results cached per IP for 1 hour (saves the 70/day quota).
- `domains.json`, `*.log`, `.rate_*`, `.ipqs_*` blocked from public reads via `.htaccess`.
- Auto-trim: keeps last 10,000 visits per domain.

## Notes

- The IPQS keys are reused from the parent shaver project (`/config.php`). If you rotate them there, also update `traffic/config.php`.
- Logs are deliberately not committed — they live in `logs/` on the Hostinger server, surviving git pulls.
- View.php has **no authentication** by design (current request). Anyone with the URL can read logs. If you want auth later, ask.
