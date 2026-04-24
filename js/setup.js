/**
 * Multi-Tenant Shaver - Setup Page Logic
 * Parses BuyGoods and Digistore24 tracking scripts and generates embed snippets
 *
 * NOTE: This file is kept in sync with the inline <script> in setup.html.
 * The inline version is what actually runs on the page.
 */

// Parse BuyGoods tracking script
function parseBGScript(rawScript) {
    const result = {
        accountId: null,
        productCodes: null,
        conversionToken: null,
        trackingScript: '',
        iframeScript: ''
    };

    if (!rawScript) return result;

    const accountMatch = rawScript.match(/[?&]a=(\d+)/);
    if (accountMatch) result.accountId = accountMatch[1];

    const productMatch = rawScript.match(/[&?]product=([a-zA-Z0-9_,]+)/);
    if (productMatch) result.productCodes = productMatch[1];

    const tokenMatch = rawScript.match(/[&?]t=([a-f0-9]{32})/);
    if (tokenMatch) result.conversionToken = tokenMatch[1];

    if (rawScript.includes('tracking.buygoods.com/track')) result.trackingScript = rawScript;
    if (rawScript.includes('conversion/iframe')) result.iframeScript = rawScript;
    if (result.trackingScript && rawScript.includes('conversion/iframe')) {
        result.trackingScript = rawScript;
        result.iframeScript = rawScript;
    }
    return result;
}

// Parse Digistore24 tracking script
function parseDS24Script(rawScript) {
    var result = { productId: null, hasDigistoreJs: false, hasPromocode: false };
    if (!rawScript) return result;
    result.hasDigistoreJs = rawScript.indexOf('digistore24-scripts.com/service/digistore.js') !== -1;
    var productMatch = rawScript.match(/["']product_id["']\s*:\s*(\d+)/);
    if (productMatch) result.productId = productMatch[1];
    result.hasPromocode = rawScript.indexOf('digistorePromocode') !== -1;
    return result;
}
