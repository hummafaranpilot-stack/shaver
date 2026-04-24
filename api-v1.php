<?php
/**
 * Shaver Public REST API — v1
 *
 * Authentication (required on every request):
 *   Header:  Authorization: Bearer <api_key>
 *   — or —
 *   Query:   ?key=<api_key>
 *
 * Resource routing:
 *   ?r=domains    List domains accessible to this key
 *   ?r=analytics  Summary stats + top affiliates + daily breakdown
 *   ?r=orders     Paginated order list
 *   ?r=customers  Paginated customer list
 *   ?r=sessions   Active shaving sessions
 *   ?r=traffic    Paginated traffic log
 *
 * Common query params:
 *   domain_id=N          Filter by domain (ignored if key is domain-scoped)
 *   from=YYYY-MM-DD      Date range start  (analytics / orders / traffic)
 *   to=YYYY-MM-DD        Date range end
 *   page=N               Page number (default 1)
 *   limit=N              Rows per page (max 1000, default 100)
 *   search=text          Full-text search (orders / customers)
 *   aff_id=xxx           Filter by affiliate (analytics / traffic)
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { apiError(405, 'Only GET requests are supported.'); }

$pdo = getDB();

// ---- Helpers ----------------------------------------------------------------

function apiError(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg, 'code' => $code], JSON_UNESCAPED_UNICODE);
    exit;
}

function apiOk($data, array $meta = []): void {
    echo json_encode([
        'ok'   => true,
        'data' => $data,
        'meta' => array_merge(['generated_at' => date('Y-m-d H:i:s')], $meta),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function validDate(string $d): bool {
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

// ---- Auth -------------------------------------------------------------------

function resolveKey($pdo): array {
    $raw = '';
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', trim($auth), $m)) $raw = trim($m[1]);
    if (empty($raw) && !empty($_GET['key'])) $raw = trim($_GET['key']);
    if (empty($raw)) apiError(401, 'Missing API key. Use Authorization: Bearer <key> header or ?key= query param.');

    $hash = hash('sha256', $raw);
    $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE key_hash = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$hash]);
    $key = $stmt->fetch();
    if (!$key) apiError(401, 'Invalid or revoked API key.');

    $pdo->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?")->execute([$key['id']]);
    return $key;
}

// ---- Resolve auth & domain scope --------------------------------------------

$apiKey           = resolveKey($pdo);
$scopedDomainId   = $apiKey['domain_id'];           // null = global key
$requestedDomain  = isset($_GET['domain_id']) ? (int)$_GET['domain_id'] : null;
$effectiveDomain  = ($scopedDomainId !== null) ? (int)$scopedDomainId : $requestedDomain;

// ---- Route ------------------------------------------------------------------

$resource = strtolower(trim($_GET['r'] ?? ''));
if (empty($resource)) {
    apiError(400, 'Missing ?r= parameter. Valid resources: domains, analytics, orders, customers, sessions, traffic');
}

switch ($resource) {
    case 'domains':   routeDomains($pdo, $scopedDomainId);  break;
    case 'analytics': routeAnalytics($pdo, $effectiveDomain); break;
    case 'orders':    routeOrders($pdo, $effectiveDomain);    break;
    case 'customers': routeCustomers($pdo, $effectiveDomain); break;
    case 'sessions':  routeSessions($pdo, $effectiveDomain);  break;
    case 'traffic':   routeTraffic($pdo, $effectiveDomain);   break;
    default: apiError(404, 'Unknown resource "' . htmlspecialchars($resource) . '". Valid: domains, analytics, orders, customers, sessions, traffic');
}

// ---- Resource handlers ------------------------------------------------------

function routeDomains($pdo, $scopedDomainId): void {
    if ($scopedDomainId !== null) {
        $stmt = $pdo->prepare("SELECT id, label, domain_url, platform, status FROM domains WHERE id = ? AND status = 'active'");
        $stmt->execute([(int)$scopedDomainId]);
    } else {
        $stmt = $pdo->query("SELECT id, label, domain_url, platform, status FROM domains WHERE status = 'active' ORDER BY label");
    }
    $rows = $stmt->fetchAll();
    apiOk($rows, ['count' => count($rows)]);
}

function routeAnalytics($pdo, $effectiveDomain): void {
    $from = $_GET['from'] ?? date('Y-m-d');
    $to   = $_GET['to']   ?? date('Y-m-d');
    if (!validDate($from)) $from = date('Y-m-d');
    if (!validDate($to))   $to   = date('Y-m-d');
    $affId = trim($_GET['aff_id'] ?? '');

    $where  = "DATE(timestamp) BETWEEN ? AND ?";
    $params = [$from, $to];
    if ($effectiveDomain !== null) { $where .= " AND domain_id = ?"; $params[] = $effectiveDomain; }
    if ($affId !== '')             { $where .= " AND aff_id = ?";    $params[] = $affId; }

    // Summary
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*)                                                          AS total_visits,
            COUNT(DISTINCT ip_address)                                        AS unique_visitors,
            COUNT(DISTINCT aff_id)                                            AS unique_affiliates,
            COALESCE(SUM(was_shaved), 0)                                      AS shaved_visits,
            COALESCE(SUM(CASE WHEN buynow_clicks > 0 THEN 1 ELSE 0 END), 0)  AS checkout_visits,
            COALESCE(SUM(CASE WHEN matched_order_id IS NOT NULL AND matched_order_id != '' THEN 1 ELSE 0 END), 0) AS confirmed_orders,
            COALESCE(SUM(bounce), 0)                                          AS bounces,
            ROUND(AVG(NULLIF(max_scroll_depth, 0)), 1)                        AS avg_scroll_depth,
            ROUND(AVG(NULLIF(session_duration, 0)), 1)                        AS avg_session_duration,
            COALESCE(SUM(total_clicks), 0)                                    AS total_clicks,
            COALESCE(SUM(buynow_clicks), 0)                                   AS total_buynow_clicks
        FROM affiliate_traffic WHERE $where
    ");
    $stmt->execute($params);
    $summary = $stmt->fetch();

    // Top affiliates
    $stmt2 = $pdo->prepare("
        SELECT aff_id, COUNT(*) AS visits,
            COALESCE(SUM(was_shaved), 0) AS shaved,
            COUNT(DISTINCT ip_address)   AS unique_ips,
            COALESCE(SUM(CASE WHEN matched_order_id IS NOT NULL AND matched_order_id != '' THEN 1 ELSE 0 END), 0) AS orders
        FROM affiliate_traffic WHERE $where
        GROUP BY aff_id ORDER BY visits DESC LIMIT 25
    ");
    $stmt2->execute($params);
    $topAffiliates = $stmt2->fetchAll();

    // Daily breakdown
    $stmt3 = $pdo->prepare("
        SELECT DATE(timestamp) AS date, COUNT(*) AS visits,
            COALESCE(SUM(was_shaved), 0) AS shaved,
            COALESCE(SUM(CASE WHEN matched_order_id IS NOT NULL AND matched_order_id != '' THEN 1 ELSE 0 END), 0) AS orders
        FROM affiliate_traffic WHERE $where
        GROUP BY DATE(timestamp) ORDER BY date
    ");
    $stmt3->execute($params);
    $daily = $stmt3->fetchAll();

    apiOk(
        ['summary' => $summary, 'top_affiliates' => $topAffiliates, 'daily' => $daily],
        ['date_from' => $from, 'date_to' => $to, 'domain_id' => $effectiveDomain]
    );
}

function routeOrders($pdo, $effectiveDomain): void {
    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min(1000, max(1, (int)($_GET['limit'] ?? 100)));
    $offset = ($page - 1) * $limit;
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $from   = trim($_GET['from']   ?? '');
    $to     = trim($_GET['to']     ?? '');

    $where  = ['1=1'];
    $params = [];

    if ($effectiveDomain !== null) { $where[] = 'domain_id = ?'; $params[] = $effectiveDomain; }
    if ($search !== '') {
        $where[] = '(customer_name LIKE ? OR customer_email LIKE ? OR order_id LIKE ?)';
        $s = '%' . $search . '%';
        $params = array_merge($params, [$s, $s, $s]);
    }
    if ($status !== '')            { $where[] = 'status = ?'; $params[] = $status; }
    if (validDate($from))          { $where[] = 'DATE(date_created) >= ?'; $params[] = $from; }
    if (validDate($to))            { $where[] = 'DATE(date_created) <= ?'; $params[] = $to; }

    $wc = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE $wc");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $dataStmt = $pdo->prepare("SELECT * FROM orders WHERE $wc ORDER BY date_created DESC LIMIT ? OFFSET ?");
    $dataStmt->execute(array_merge($params, [$limit, $offset]));
    $rows = $dataStmt->fetchAll();

    apiOk($rows, [
        'total'     => $total,
        'page'      => $page,
        'limit'     => $limit,
        'pages'     => (int)ceil($total / max($limit, 1)),
        'domain_id' => $effectiveDomain,
    ]);
}

function routeCustomers($pdo, $effectiveDomain): void {
    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min(1000, max(1, (int)($_GET['limit'] ?? 100)));
    $offset = ($page - 1) * $limit;
    $search = trim($_GET['search'] ?? '');

    $where  = ["customer_email IS NOT NULL", "customer_email != ''"];
    $params = [];

    if ($effectiveDomain !== null) { $where[] = 'domain_id = ?'; $params[] = $effectiveDomain; }
    if ($search !== '') {
        $where[] = '(customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ?)';
        $s = '%' . $search . '%';
        $params = array_merge($params, [$s, $s, $s]);
    }

    $wc = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT customer_email) FROM orders WHERE $wc");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $dataStmt = $pdo->prepare("
        SELECT customer_email,
            MAX(customer_name)  AS name,
            MAX(customer_phone) AS phone,
            MAX(city)           AS city,
            MAX(state)          AS state,
            MAX(country)        AS country,
            COUNT(*)            AS total_orders,
            COALESCE(SUM(total_amount), 0) AS total_spent,
            MIN(date_created)   AS first_order,
            MAX(date_created)   AS last_order
        FROM orders WHERE $wc
        GROUP BY customer_email ORDER BY last_order DESC LIMIT ? OFFSET ?
    ");
    $dataStmt->execute(array_merge($params, [$limit, $offset]));
    $rows = $dataStmt->fetchAll();

    apiOk($rows, [
        'total'     => $total,
        'page'      => $page,
        'limit'     => $limit,
        'pages'     => (int)ceil($total / max($limit, 1)),
        'domain_id' => $effectiveDomain,
    ]);
}

function routeSessions($pdo, $effectiveDomain): void {
    $where  = "s.active = 1";
    $params = [];
    if ($effectiveDomain !== null) { $where .= " AND s.domain_id = ?"; $params[] = $effectiveDomain; }

    $stmt = $pdo->prepare("
        SELECT s.id, s.domain_id, s.aff_id, s.start_time, s.shave_percent, s.smart_mode,
            COALESCE(at_stats.traffic_count, 0) AS shaved_visits,
            COALESCE(at_stats.traffic_total, 0) AS total_visits
        FROM shaving_sessions s
        LEFT JOIN (
            SELECT shaving_session_id,
                SUM(was_shaved)  AS traffic_count,
                COUNT(*)         AS traffic_total
            FROM affiliate_traffic
            WHERE shaving_session_id IS NOT NULL
            GROUP BY shaving_session_id
        ) at_stats ON s.id = at_stats.shaving_session_id
        WHERE $where
        ORDER BY s.start_time DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    apiOk($rows, ['count' => count($rows), 'domain_id' => $effectiveDomain]);
}

function routeTraffic($pdo, $effectiveDomain): void {
    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min(1000, max(1, (int)($_GET['limit'] ?? 100)));
    $offset = ($page - 1) * $limit;
    $from   = trim($_GET['from']   ?? date('Y-m-d'));
    $to     = trim($_GET['to']     ?? date('Y-m-d'));
    $affId  = trim($_GET['aff_id'] ?? '');

    $where  = ['1=1'];
    $params = [];

    if ($effectiveDomain !== null) { $where[] = 'domain_id = ?'; $params[] = $effectiveDomain; }
    if (validDate($from))          { $where[] = 'DATE(timestamp) >= ?'; $params[] = $from; }
    if (validDate($to))            { $where[] = 'DATE(timestamp) <= ?'; $params[] = $to; }
    if ($affId !== '')             { $where[] = 'aff_id = ?'; $params[] = $affId; }

    $wc = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_traffic WHERE $wc");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $dataStmt = $pdo->prepare("
        SELECT id, domain_id, aff_id, ip_address, page_url, page_type,
            was_shaved, smart_skipped, matched_order_id,
            max_scroll_depth, session_duration, bounce,
            total_clicks, buynow_clicks, timestamp
        FROM affiliate_traffic WHERE $wc ORDER BY timestamp DESC LIMIT ? OFFSET ?
    ");
    $dataStmt->execute(array_merge($params, [$limit, $offset]));
    $rows = $dataStmt->fetchAll();

    apiOk($rows, [
        'total'     => $total,
        'page'      => $page,
        'limit'     => $limit,
        'pages'     => (int)ceil($total / max($limit, 1)),
        'domain_id' => $effectiveDomain,
        'date_from' => $from,
        'date_to'   => $to,
    ]);
}
