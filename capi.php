<?php
/**
 * Facebook Conversions API (CAPI) helper
 *
 * Sends server-side events to Facebook's Graph API. Used alongside (NOT
 * instead of) the browser-side Pixel — server events provide redundancy
 * when ad-blockers / iOS / cookie restrictions stop the pixel from firing.
 *
 * Both pixel and CAPI events should share the same event_id so Facebook
 * can deduplicate. We generate event_id server-side and (in future) pass
 * it back to the JS so the browser pixel uses the same one.
 *
 * Docs: https://developers.facebook.com/docs/marketing-api/conversions-api
 */

require_once __DIR__ . '/config.php';

/**
 * Hash a piece of PII per Facebook's spec: lowercase, trim, then SHA-256 hex.
 * Pass empty / null through unhashed (Facebook ignores empty user_data fields).
 */
function hashFBData($value): string {
    if ($value === null || $value === '') return '';
    return hash('sha256', strtolower(trim((string)$value)));
}

/**
 * Check whether a domain is whitelisted for CAPI. Matches by label (case-
 * insensitive) so configs don't depend on auto-increment IDs.
 *
 * Cached per-request — multiple events for the same domain in one PHP
 * lifetime hit the DB once.
 */
function fbIsWhitelistedDomain(int $domainId): bool {
    static $cache = [];
    if (isset($cache[$domainId])) return $cache[$domainId];

    if (!defined('FB_WEIGHTLOSS_DOMAINS') || empty(FB_WEIGHTLOSS_DOMAINS)) {
        return $cache[$domainId] = false;
    }

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT label FROM domains WHERE id = ? LIMIT 1");
        $stmt->execute([$domainId]);
        $label = strtolower(trim((string)$stmt->fetchColumn()));
    } catch (Exception $e) {
        return $cache[$domainId] = false;
    }

    foreach (FB_WEIGHTLOSS_DOMAINS as $wlLabel) {
        if ($label === strtolower(trim((string)$wlLabel))) {
            return $cache[$domainId] = true;
        }
    }
    return $cache[$domainId] = false;
}

/**
 * Send a single event to Facebook CAPI.
 *
 * @param string $eventName   "PageView" / "Lead" / "AddToCart" / "Purchase" / etc.
 * @param array  $data        {
 *   page_url      : event_source_url
 *   ip_address    : client_ip_address
 *   user_agent    : client_user_agent
 *   session_uuid  : used as external_id
 *   fbc, fbp      : Facebook click/browser cookies (raw, not hashed)
 *   email/phone/first_name/last_name/city/state/zip/country : auto-hashed
 *   value, currency, order_id : for purchase events (custom_data)
 *   event_id      : optional dedupe id; auto-generated if absent
 *   event_time    : optional unix timestamp; defaults to now
 * }
 * @return array {ok: bool, response: string, error?: string}
 */
function sendCAPIEvent(string $eventName, array $data): array {
    if (!defined('FB_PIXEL_ID') || !defined('FB_ACCESS_TOKEN') || FB_ACCESS_TOKEN === '') {
        return ['ok' => false, 'error' => 'CAPI not configured (missing token)'];
    }

    $userData = [];

    // Identifiers (raw — not hashed)
    if (!empty($data['ip_address']))   $userData['client_ip_address'] = $data['ip_address'];
    if (!empty($data['user_agent']))   $userData['client_user_agent'] = $data['user_agent'];
    if (!empty($data['fbc']))          $userData['fbc']               = $data['fbc'];
    if (!empty($data['fbp']))          $userData['fbp']               = $data['fbp'];

    // external_id IS hashed per FB docs (use session_uuid for cross-device dedupe)
    if (!empty($data['session_uuid'])) $userData['external_id']       = hashFBData($data['session_uuid']);

    // Hashed PII (FB ignores blanks)
    if (!empty($data['email']))      $userData['em'] = [hashFBData($data['email'])];
    if (!empty($data['phone']))      $userData['ph'] = [hashFBData(preg_replace('/\D/', '', $data['phone']))];
    if (!empty($data['first_name'])) $userData['fn'] = [hashFBData($data['first_name'])];
    if (!empty($data['last_name']))  $userData['ln'] = [hashFBData($data['last_name'])];
    if (!empty($data['city']))       $userData['ct'] = [hashFBData($data['city'])];
    if (!empty($data['state']))      $userData['st'] = [hashFBData($data['state'])];
    if (!empty($data['zip']))        $userData['zp'] = [hashFBData($data['zip'])];
    if (!empty($data['country']))    $userData['country'] = [hashFBData(substr($data['country'], 0, 2))];

    $event = [
        'event_name'        => $eventName,
        'event_time'        => $data['event_time']        ?? time(),
        'action_source'     => 'website',
        'event_source_url'  => $data['page_url']          ?? null,
        'event_id'          => $data['event_id']          ?? buildEventId($eventName, $data),
        'user_data'         => $userData,
    ];

    // Custom data (Purchase, AddToCart, etc.)
    $custom = [];
    if (isset($data['value']))    $custom['value']    = (float)$data['value'];
    if (isset($data['currency'])) $custom['currency'] = (string)$data['currency'];
    if (isset($data['order_id'])) $custom['order_id'] = (string)$data['order_id'];
    if (!empty($custom)) $event['custom_data'] = $custom;

    $payload = ['data' => [$event]];
    if (defined('FB_TEST_EVENT_CODE') && FB_TEST_EVENT_CODE !== '') {
        $payload['test_event_code'] = FB_TEST_EVENT_CODE;
    }

    $url = 'https://graph.facebook.com/v18.0/' . FB_PIXEL_ID . '/events?access_token=' . FB_ACCESS_TOKEN;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) return ['ok' => false, 'error' => 'curl: ' . $err];
    if ($code < 200 || $code >= 300) return ['ok' => false, 'error' => "http $code", 'response' => $resp];
    return ['ok' => true, 'response' => $resp];
}

/**
 * Stable per-event ID for FB pixel ↔ CAPI deduplication. If the browser
 * pixel ever fires the same event with the same id within 7 days, FB
 * treats them as one event.
 */
function buildEventId(string $eventName, array $data): string {
    $session = $data['session_uuid'] ?? bin2hex(random_bytes(8));
    return $eventName . '.' . $session . '.' . ($data['event_time'] ?? time());
}
