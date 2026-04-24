# Shaver Public REST API — v1

External projects can read Shaver data (orders, customers, analytics, traffic, sessions, domains) over HTTP using an API key created from the **API Access** page in the admin dashboard.

---

## Base URL

```
https://shaver.trustednutraproduct.com/api-v1.php
```

All requests are `GET`. Responses are JSON.

---

## Authentication

Every request must include your API key. Use **one** of these two methods:

**HTTP Header (recommended):**
```
Authorization: Bearer sk_your_api_key
```

**Query Parameter:**
```
?key=sk_your_api_key
```

Keys are created at `https://shaver.trustednutraproduct.com/api.html`. The full key is shown **once** at creation time — store it securely.

---

## Domain Scope

When creating a key you choose its scope:

- **Global** — can read any domain; pass `domain_id` in the query to filter, or omit it to get all domains
- **Domain-scoped** — locked to one domain; the `domain_id` query param is ignored

---

## Resources

All resources are routed via `?r=<resource>`. Valid resources:

| Resource    | Description                                        |
|-------------|----------------------------------------------------|
| `domains`   | List domains accessible to this key                |
| `analytics` | Traffic summary + top affiliates + daily breakdown |
| `orders`    | Paginated order list                               |
| `customers` | Aggregated customer list (unique by email)         |
| `sessions`  | Currently active shaving sessions                  |
| `traffic`   | Raw traffic log                                    |

---

### `GET ?r=domains`

List all domains accessible to this API key.

**Parameters:** none

**Example:**
```bash
curl "https://shaver.trustednutraproduct.com/api-v1.php?r=domains" \
  -H "Authorization: Bearer sk_your_key"
```

---

### `GET ?r=analytics`

Traffic summary, top 25 affiliates, and daily breakdown for a date range.

| Param       | Type       | Default    | Description                                  |
|-------------|------------|------------|----------------------------------------------|
| `domain_id` | integer    | —          | Filter to one domain (ignored for scoped keys) |
| `from`      | YYYY-MM-DD | today      | Date range start                             |
| `to`        | YYYY-MM-DD | today      | Date range end                               |
| `aff_id`    | string     | —          | Filter to one affiliate                      |

**Example:**
```bash
curl "https://shaver.trustednutraproduct.com/api-v1.php?r=analytics&domain_id=1&from=2025-01-01&to=2025-01-31" \
  -H "Authorization: Bearer sk_your_key"
```

**Response shape:**
```json
{
  "ok": true,
  "data": {
    "summary": {
      "total_visits": 1842,
      "unique_visitors": 1215,
      "unique_affiliates": 28,
      "shaved_visits": 412,
      "checkout_visits": 87,
      "confirmed_orders": 63,
      "bounces": 310,
      "avg_scroll_depth": 58.2,
      "avg_session_duration": 94.5,
      "total_clicks": 3127,
      "total_buynow_clicks": 312
    },
    "top_affiliates": [
      { "aff_id": "abc123", "visits": 540, "shaved": 120, "unique_ips": 498, "orders": 18 }
    ],
    "daily": [
      { "date": "2025-01-01", "visits": 62, "shaved": 14, "orders": 3 }
    ]
  },
  "meta": { "date_from": "2025-01-01", "date_to": "2025-01-31", "domain_id": 1, "generated_at": "..." }
}
```

---

### `GET ?r=orders`

Paginated order list with fulfillment status, amounts, and customer info.

| Param       | Type       | Default | Description                                       |
|-------------|------------|---------|---------------------------------------------------|
| `domain_id` | integer    | —       | Filter to one domain                              |
| `from`      | YYYY-MM-DD | —       | Created-date start                                |
| `to`        | YYYY-MM-DD | —       | Created-date end                                  |
| `status`    | string     | —       | e.g. `sale`, `refund`, `chargeback`               |
| `search`    | string     | —       | Search by name, email, or order ID                |
| `page`      | integer    | 1       | Page number                                       |
| `limit`     | integer    | 100     | Rows per page (max 1000)                          |

**Example:**
```bash
curl "https://shaver.trustednutraproduct.com/api-v1.php?r=orders&domain_id=1&from=2025-01-01&limit=50" \
  -H "Authorization: Bearer sk_your_key"
```

---

### `GET ?r=customers`

Aggregated customer list (unique by email) with total orders and lifetime spend.

| Param       | Type    | Default | Description                             |
|-------------|---------|---------|-----------------------------------------|
| `domain_id` | integer | —       | Filter to one domain                    |
| `search`    | string  | —       | Search by name, email, or phone         |
| `page`      | integer | 1       | Page number                             |
| `limit`     | integer | 100     | Rows per page (max 1000)                |

**Example:**
```bash
curl "https://shaver.trustednutraproduct.com/api-v1.php?r=customers&domain_id=1&limit=100" \
  -H "Authorization: Bearer sk_your_key"
```

---

### `GET ?r=sessions`

All currently active shaving sessions with shaved-visit counts.

| Param       | Type    | Default | Description          |
|-------------|---------|---------|----------------------|
| `domain_id` | integer | —       | Filter to one domain |

**Example:**
```bash
curl "https://shaver.trustednutraproduct.com/api-v1.php?r=sessions&domain_id=1" \
  -H "Authorization: Bearer sk_your_key"
```

---

### `GET ?r=traffic`

Raw traffic log with shave status, scroll depth, click counts, and matched order IDs.

| Param       | Type       | Default | Description                      |
|-------------|------------|---------|----------------------------------|
| `domain_id` | integer    | —       | Filter to one domain             |
| `from`      | YYYY-MM-DD | today   | Start date                       |
| `to`        | YYYY-MM-DD | today   | End date                         |
| `aff_id`    | string     | —       | Filter to one affiliate          |
| `page`      | integer    | 1       | Page number                      |
| `limit`     | integer    | 100     | Rows per page (max 1000)         |

**Example:**
```bash
curl "https://shaver.trustednutraproduct.com/api-v1.php?r=traffic&domain_id=1&from=2025-01-01&to=2025-01-31&limit=200" \
  -H "Authorization: Bearer sk_your_key"
```

---

## Response Format

**Success:**
```json
{
  "ok": true,
  "data": [ /* array or object */ ],
  "meta": {
    "generated_at": "2025-01-15 14:32:00",
    "total": 1284,
    "page": 1,
    "limit": 100,
    "pages": 13,
    "domain_id": 1
  }
}
```

**Error:**
```json
{
  "ok": false,
  "error": "Invalid or revoked API key.",
  "code": 401
}
```

| Code | Meaning                                        |
|------|------------------------------------------------|
| 400  | Missing / invalid parameter                    |
| 401  | Missing, invalid, or revoked API key           |
| 404  | Unknown `r=` resource                          |
| 405  | Non-GET request                                |
| 500  | Server error                                   |

---

## Code Examples

**JavaScript (fetch):**
```js
const res = await fetch(
  'https://shaver.trustednutraproduct.com/api-v1.php?r=orders&domain_id=1&from=2025-01-01',
  { headers: { 'Authorization': 'Bearer sk_your_key' } }
);
const { ok, data, meta } = await res.json();
console.log(meta.total, 'orders, page', meta.page, 'of', meta.pages);
```

**PHP (cURL):**
```php
$ch = curl_init('https://shaver.trustednutraproduct.com/api-v1.php?r=analytics&from=2025-01-01&to=2025-01-31');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer sk_your_key'],
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);
```

**Python (requests):**
```python
import requests
r = requests.get(
    'https://shaver.trustednutraproduct.com/api-v1.php',
    params={'r': 'customers', 'domain_id': 1, 'limit': 500},
    headers={'Authorization': 'Bearer sk_your_key'}
)
data = r.json()['data']
```

**cURL (shell):**
```bash
curl -s "https://shaver.trustednutraproduct.com/api-v1.php?r=sessions" \
  -H "Authorization: Bearer sk_your_key" | jq .
```

---

## Security Notes

- Treat API keys like passwords. Never commit them to source control or expose them in frontend JS.
- Rotate keys from the admin page (`api.html`) if one is leaked — revoke the old key first, then create a new one.
- Prefer **domain-scoped** keys when sharing with third parties so a leak only exposes one brand's data.
- All requests go over HTTPS; the key is never sent in plaintext.
- Keys are hashed (SHA-256) in the database — the full key cannot be recovered after creation.
