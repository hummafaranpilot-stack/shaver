<?php
/**
 * Shared geo helpers for TNP Shipping.
 * Used by both check.php (t.js tracker) and cdn/s.js.php (standalone shipping).
 */

/**
 * Resolve an ISO-3166 alpha-2 country code to a full English name.
 * Uses PHP intl if available; otherwise a small built-in dictionary
 * covering common countries; otherwise just returns the code.
 */
function tnp_country_name_from_code($code) {
    $code = strtoupper(trim((string)$code));
    if (strlen($code) !== 2) return null;
    if (extension_loaded('intl') && class_exists('Locale')) {
        $name = \Locale::getDisplayRegion('-' . $code, 'en');
        if ($name && $name !== $code) return $name;
    }
    static $fallback = [
        'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom',
        'IE' => 'Ireland', 'AU' => 'Australia', 'NZ' => 'New Zealand',
        'DE' => 'Germany', 'FR' => 'France', 'IT' => 'Italy', 'ES' => 'Spain',
        'PT' => 'Portugal', 'NL' => 'Netherlands', 'BE' => 'Belgium', 'CH' => 'Switzerland',
        'AT' => 'Austria', 'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark',
        'FI' => 'Finland', 'IS' => 'Iceland', 'PL' => 'Poland', 'GR' => 'Greece',
        'PK' => 'Pakistan', 'IN' => 'India', 'BD' => 'Bangladesh', 'LK' => 'Sri Lanka',
        'CN' => 'China', 'JP' => 'Japan', 'KR' => 'South Korea', 'SG' => 'Singapore',
        'MY' => 'Malaysia', 'TH' => 'Thailand', 'ID' => 'Indonesia', 'PH' => 'Philippines',
        'VN' => 'Vietnam', 'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia',
        'IL' => 'Israel', 'TR' => 'Turkey', 'EG' => 'Egypt', 'ZA' => 'South Africa',
        'NG' => 'Nigeria', 'KE' => 'Kenya', 'MX' => 'Mexico', 'BR' => 'Brazil',
        'AR' => 'Argentina', 'CL' => 'Chile', 'CO' => 'Colombia', 'PE' => 'Peru',
        'RU' => 'Russia', 'UA' => 'Ukraine'
    ];
    return isset($fallback[$code]) ? $fallback[$code] : $code;
}

/**
 * Detect the visitor's country server-side. Avoids client-side CORS /
 * rate-limit issues. Tries (in order):
 *   1. Cloudflare CF-IPCOUNTRY header (free, instant, when behind CF)
 *   2. ipwho.is server-side cURL with the visitor's IP
 *   3. ipapi.co server-side cURL with the visitor's IP
 *
 * Returns ['code' => 'PK', 'name' => 'Pakistan'] or null on total failure.
 */
function tnp_detect_visitor_country() {
    // 1. Cloudflare header
    if (!empty($_SERVER['HTTP_CF_IPCOUNTRY']) && $_SERVER['HTTP_CF_IPCOUNTRY'] !== 'XX') {
        $code = strtoupper($_SERVER['HTTP_CF_IPCOUNTRY']);
        return [
            'code' => $code,
            'name' => tnp_country_name_from_code($code)
        ];
    }

    // 2 + 3. Server-side cURL providers
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '';
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }
    if (!$ip) return null;

    $providers = [
        [
            'url' => 'https://ipwho.is/' . urlencode($ip),
            'parse' => function($json) {
                if (!$json || (isset($json['success']) && $json['success'] === false)) return null;
                return [
                    'code' => $json['country_code'] ?? null,
                    'name' => $json['country'] ?? null
                ];
            }
        ],
        [
            'url' => 'https://ipapi.co/' . urlencode($ip) . '/json/',
            'parse' => function($json) {
                if (!$json || !empty($json['error'])) return null;
                return [
                    'code' => $json['country_code'] ?? null,
                    'name' => $json['country_name'] ?? null
                ];
            }
        ]
    ];

    foreach ($providers as $p) {
        $ch = curl_init($p['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; TNP-Shipping-Geo/1.0)'
        ]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http !== 200 || !$resp) continue;
        $json = json_decode($resp, true);
        $parsed = $p['parse']($json);
        if ($parsed && !empty($parsed['code'])) {
            $code = strtoupper($parsed['code']);
            return [
                'code' => $code,
                'name' => $parsed['name'] ?: tnp_country_name_from_code($code)
            ];
        }
    }
    return null;
}
