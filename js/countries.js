// Region groupings used by shipping.html (admin) AND by the
// shipping bootstrap on opted-in pages.
//
// Each REGION row is a single shipping rule that applies to ALL
// listed ISO countries — the bootstrap maps the visitor's detected
// ISO code to the matching region key.
//
// IMPORTANT: keys must be exactly 2 chars (DB column is CHAR(2)).
window.COUNTRIES = [
    { code: 'US', name: 'United States' },
    { code: 'CA', name: 'Canada' },
    { code: 'UK', name: 'UK and Ireland' },
    { code: 'NE', name: 'Northern Europe' },
    { code: 'WE', name: 'Western Europe' },
    { code: 'SE', name: 'Southern Europe' },
    { code: 'AU', name: 'Australasia' }
];

// ISO-3166 alpha-2 → region key. Used by the bootstrap to translate
// the visitor's ipapi.co country_code into the admin's region rule.
window.REGION_MAP = {
    // United States
    'US': 'US',
    // Canada
    'CA': 'CA',
    // UK and Ireland
    'GB': 'UK', 'IE': 'UK',
    // Northern Europe
    'SE': 'NE', 'DK': 'NE', 'NO': 'NE', 'FI': 'NE', 'IS': 'NE',
    'EE': 'NE', 'LV': 'NE', 'LT': 'NE',
    // Western Europe
    'DE': 'WE', 'FR': 'WE', 'CH': 'WE', 'NL': 'WE', 'AT': 'WE',
    'BE': 'WE', 'LU': 'WE', 'LI': 'WE', 'MC': 'WE',
    // Southern Europe
    'IT': 'SE', 'ES': 'SE', 'PT': 'SE', 'GR': 'SE', 'MT': 'SE',
    'CY': 'SE', 'SM': 'SE', 'VA': 'SE', 'AD': 'SE', 'GI': 'SE',
    // Australasia
    'AU': 'AU', 'NZ': 'AU', 'PG': 'AU', 'FJ': 'AU', 'SB': 'AU',
    'VU': 'AU', 'WS': 'AU', 'TO': 'AU'
};
