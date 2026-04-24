<?php
/**
 * Checkout Tracking Pixel
 *
 * Loaded on BuyGoods checkout pages via:
 * <script src="https://shaver.trustednutraproduct.com/checkout-pixel.php"></script>
 *
 * Auto-reads context from checkout URL params (aff_id, account_id, product_codename, sessid2).
 * Monitors form fields to capture customer details and tracks purchase attempts.
 * Does NOT capture credit card details.
 */

header('Content-Type: application/javascript; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Build API URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$path = dirname($_SERVER['SCRIPT_NAME']);
$apiUrl = $protocol . '://' . $host . $path . '/api.php';
?>
/**
 * BuyGoods Checkout Tracking Pixel
 * https://shaver.trustednutraproduct.com/checkout-pixel.php
 * Generated: <?php echo date('Y-m-d H:i:s'); ?>

 */
(function() {
    'use strict';

    var API_URL = '<?php echo $apiUrl; ?>';

    // ============================================================
    // URL PARAMETER EXTRACTION
    // ============================================================
    function getUrlParams() {
        var params = {};
        var search = window.location.search.substring(1);
        if (!search) return params;
        var pairs = search.split('&');
        for (var i = 0; i < pairs.length; i++) {
            var pair = pairs[i].split('=');
            params[decodeURIComponent(pair[0])] = pair[1] ? decodeURIComponent(pair[1]) : '';
        }
        return params;
    }

    var urlParams = getUrlParams();
    var context = {
        sessid2: urlParams.sessid2 || urlParams.sessid || '',
        aff_id: urlParams.aff_id || urlParams.affid || '',
        sub_id: urlParams.subid || urlParams.sub_id || '',
        account_id: urlParams.account_id || '',
        product_codename: urlParams.product_codename || urlParams.product || ''
    };

    // Only run on BuyGoods checkout pages
    if (window.location.hostname.indexOf('buygoods.com') === -1) {
        return;
    }

    // ============================================================
    // SESSION UUID (dedup)
    // ============================================================
    var checkoutUUID;
    try {
        checkoutUUID = sessionStorage.getItem('_checkout_pixel_uuid');
    } catch (e) {}
    if (!checkoutUUID) {
        checkoutUUID = 'chk_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        try { sessionStorage.setItem('_checkout_pixel_uuid', checkoutUUID); } catch (e) {}
    }

    // ============================================================
    // STATE
    // ============================================================
    var state = {
        leadCreated: false,
        lastSentData: {},
        debounceTimer: null,
        fields: {
            email: '',
            phone: '',
            full_name: '',
            address: '',
            country: '',
            state: '',
            city: '',
            zip: '',
            payment_method: ''
        }
    };

    // ============================================================
    // FIELD DETECTION — multi-strategy to handle BuyGoods markup
    // ============================================================
    function findField(strategies) {
        for (var i = 0; i < strategies.length; i++) {
            var el = document.querySelector(strategies[i]);
            if (el) return el;
        }
        return null;
    }

    function findFieldByPlaceholder(text) {
        var inputs = document.querySelectorAll('input, select, textarea');
        text = text.toLowerCase();
        for (var i = 0; i < inputs.length; i++) {
            var ph = (inputs[i].placeholder || '').toLowerCase();
            if (ph.indexOf(text) !== -1) return inputs[i];
        }
        return null;
    }

    function findFieldByLabel(text) {
        var labels = document.querySelectorAll('label');
        text = text.toLowerCase();
        for (var i = 0; i < labels.length; i++) {
            if ((labels[i].textContent || '').toLowerCase().indexOf(text) !== -1) {
                var forAttr = labels[i].getAttribute('for');
                if (forAttr) {
                    var el = document.getElementById(forAttr);
                    if (el) return el;
                }
                // Check next sibling or child input
                var input = labels[i].querySelector('input, select, textarea');
                if (input) return input;
                var next = labels[i].nextElementSibling;
                if (next && (next.tagName === 'INPUT' || next.tagName === 'SELECT' || next.tagName === 'TEXTAREA')) return next;
            }
        }
        return null;
    }

    // Map field names to detection strategies
    var fieldMap = {
        email: function() {
            return findField(['input[type="email"]', 'input[name="email"]', 'input[name="customer_email"]', 'input[id="email"]'])
                || findFieldByPlaceholder('email')
                || findFieldByLabel('email');
        },
        phone: function() {
            return findField(['input[type="tel"]', 'input[name="phone"]', 'input[name="customer_phone"]', 'input[id="phone"]'])
                || findFieldByPlaceholder('phone')
                || findFieldByLabel('phone');
        },
        full_name: function() {
            return findField(['input[name="name"]', 'input[name="full_name"]', 'input[name="billing_name"]', 'input[name="customer_name"]', 'input[id="name"]'])
                || findFieldByPlaceholder('full name')
                || findFieldByPlaceholder('your name')
                || findFieldByLabel('full name')
                || findFieldByLabel('name');
        },
        address: function() {
            return findField(['input[name="address"]', 'input[name="billing_address"]', 'input[name="street"]', 'input[id="address"]'])
                || findFieldByPlaceholder('address')
                || findFieldByPlaceholder('billing address')
                || findFieldByLabel('address');
        },
        country: function() {
            // First select dropdown in the billing section is usually country
            return findField(['select[name="country"]', 'select[name="billing_country"]', 'select[id="country"]'])
                || findFieldByLabel('country');
        },
        state: function() {
            return findField(['select[name="state"]', 'select[name="billing_state"]', 'select[id="state"]', 'input[name="state"]'])
                || findFieldByLabel('state')
                || findFieldByPlaceholder('state');
        },
        city: function() {
            return findField(['input[name="city"]', 'input[name="billing_city"]', 'input[id="city"]'])
                || findFieldByPlaceholder('city')
                || findFieldByLabel('city');
        },
        zip: function() {
            return findField(['input[name="zip"]', 'input[name="zipcode"]', 'input[name="zip_code"]', 'input[name="postal"]', 'input[id="zip"]'])
                || findFieldByPlaceholder('zip')
                || findFieldByPlaceholder('postal')
                || findFieldByLabel('zip');
        }
    };

    // ============================================================
    // PAYMENT METHOD DETECTION
    // ============================================================
    function detectPaymentMethod() {
        // Look for active/selected payment tab
        var methods = ['credit_card', 'paypal', 'apple_pay'];
        var selectors = [
            // Active tab patterns
            '.payment-method .active',
            '.payment-tab.active',
            '.payment-option.selected',
            '[data-payment-method].active',
            '.tab-pane.active',
            // Radio buttons
            'input[name="payment_method"]:checked',
            'input[name="paymentMethod"]:checked'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var el = document.querySelector(selectors[i]);
            if (el) {
                var text = (el.textContent || el.value || el.getAttribute('data-payment-method') || '').toLowerCase();
                if (text.indexOf('paypal') !== -1) return 'paypal';
                if (text.indexOf('apple') !== -1) return 'apple_pay';
                if (text.indexOf('credit') !== -1 || text.indexOf('card') !== -1) return 'credit_card';
            }
        }

        // Fallback: check all payment-related elements for active state
        var allTabs = document.querySelectorAll('[class*="payment"], [class*="pay-method"], [data-payment]');
        for (var j = 0; j < allTabs.length; j++) {
            var tab = allTabs[j];
            var classList = (tab.className || '').toLowerCase();
            if (classList.indexOf('active') !== -1 || classList.indexOf('selected') !== -1) {
                var tabText = (tab.textContent || '').toLowerCase();
                if (tabText.indexOf('paypal') !== -1) return 'paypal';
                if (tabText.indexOf('apple') !== -1) return 'apple_pay';
                if (tabText.indexOf('credit') !== -1 || tabText.indexOf('card') !== -1) return 'credit_card';
            }
        }

        return 'credit_card'; // default
    }

    // ============================================================
    // COLLECT ALL FIELD VALUES
    // ============================================================
    function collectFields() {
        var data = {};
        var keys = Object.keys(fieldMap);
        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            var el = fieldMap[key]();
            if (el) {
                var val = (el.value || '').trim();
                // For select elements, use selected option text if value is a code
                if (el.tagName === 'SELECT' && el.selectedIndex > 0) {
                    var optText = el.options[el.selectedIndex].text;
                    data[key] = optText || val;
                } else {
                    data[key] = val;
                }
            } else {
                data[key] = state.fields[key] || '';
            }
        }
        data.payment_method = detectPaymentMethod();
        return data;
    }

    // ============================================================
    // CHECK IF DATA CHANGED
    // ============================================================
    function hasChanged(newData) {
        var keys = Object.keys(newData);
        for (var i = 0; i < keys.length; i++) {
            if (newData[keys[i]] !== (state.lastSentData[keys[i]] || '')) return true;
        }
        return false;
    }

    // Determine status based on fields filled
    function getStatus(data) {
        var filled = 0;
        if (data.email) filled++;
        if (data.phone) filled++;
        if (data.full_name) filled++;
        if (data.address) filled++;
        return filled >= 2 ? 'form_filled' : 'started';
    }

    // ============================================================
    // SEND DATA TO API
    // ============================================================
    function sendData(extraStatus) {
        var fields = collectFields();
        state.fields = fields;

        // Don't send if nothing meaningful captured
        if (!fields.email && !fields.phone && !fields.full_name && !state.leadCreated) return;

        // Don't send if nothing changed (unless forcing a status update)
        if (!extraStatus && state.leadCreated && !hasChanged(fields)) return;

        var status = extraStatus || getStatus(fields);
        var payload = {
            action: 'log_checkout_lead',
            checkout_uuid: checkoutUUID,
            sessid2: context.sessid2,
            aff_id: context.aff_id,
            sub_id: context.sub_id,
            account_id: context.account_id,
            product_codename: context.product_codename,
            email: fields.email,
            phone: fields.phone,
            full_name: fields.full_name,
            address: fields.address,
            country: fields.country,
            state: fields.state,
            city: fields.city,
            zip: fields.zip,
            payment_method: fields.payment_method,
            status: status,
            checkout_url: window.location.href,
            referrer: document.referrer || ''
        };

        var xhr = new XMLHttpRequest();
        xhr.open('POST', API_URL, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify(payload));

        state.leadCreated = true;
        state.lastSentData = JSON.parse(JSON.stringify(fields));
    }

    // ============================================================
    // DEBOUNCED SEND (2 seconds after last field change)
    // ============================================================
    function debouncedSend() {
        clearTimeout(state.debounceTimer);
        state.debounceTimer = setTimeout(function() {
            sendData();
        }, 2000);
    }

    // ============================================================
    // ATTACH LISTENERS TO ALL FORM FIELDS
    // ============================================================
    function attachListeners() {
        // Listen on all input/select fields in the form
        var inputs = document.querySelectorAll('input, select, textarea');
        for (var i = 0; i < inputs.length; i++) {
            var input = inputs[i];
            var type = (input.type || '').toLowerCase();

            // Skip credit card fields — NEVER capture these
            var name = (input.name || '').toLowerCase();
            var id = (input.id || '').toLowerCase();
            var ph = (input.placeholder || '').toLowerCase();
            var autocomplete = (input.getAttribute('autocomplete') || '').toLowerCase();

            if (type === 'password' ||
                name.indexOf('card') !== -1 || name.indexOf('cc_') !== -1 ||
                name.indexOf('cvv') !== -1 || name.indexOf('cvc') !== -1 ||
                name.indexOf('security') !== -1 || name.indexOf('expir') !== -1 ||
                id.indexOf('card') !== -1 || id.indexOf('cvv') !== -1 ||
                id.indexOf('cvc') !== -1 || id.indexOf('expir') !== -1 ||
                ph.indexOf('card number') !== -1 || ph.indexOf('credit card') !== -1 ||
                ph.indexOf('security code') !== -1 || ph.indexOf('expir') !== -1 ||
                ph.indexOf('cvv') !== -1 || ph.indexOf('cvc') !== -1 ||
                ph.indexOf('mm/yy') !== -1 || ph.indexOf('mm/yyyy') !== -1 ||
                autocomplete.indexOf('cc-') !== -1) {
                continue;
            }

            input.addEventListener('input', debouncedSend);
            input.addEventListener('change', debouncedSend);
        }

        // Payment method tab clicks
        var paymentTabs = document.querySelectorAll('[class*="payment"], [class*="pay-method"], [data-payment], [class*="tab"]');
        for (var j = 0; j < paymentTabs.length; j++) {
            paymentTabs[j].addEventListener('click', function() {
                setTimeout(debouncedSend, 500);
            });
        }
    }

    // ============================================================
    // BUY NOW BUTTON DETECTION
    // ============================================================
    function attachBuyNowListener() {
        // Multiple strategies to find the Buy Now button
        var buyBtn = null;
        var selectors = [
            'button[type="submit"]',
            'input[type="submit"]',
            '.buy-now-btn',
            '#buy-now-btn',
            '[class*="buy-now"]',
            '[class*="buynow"]',
            '[id*="buy-now"]',
            '[id*="buynow"]'
        ];

        for (var i = 0; i < selectors.length; i++) {
            buyBtn = document.querySelector(selectors[i]);
            if (buyBtn) break;
        }

        // Fallback: find button/a containing "Buy Now" text
        if (!buyBtn) {
            var allButtons = document.querySelectorAll('button, a, input[type="submit"]');
            for (var j = 0; j < allButtons.length; j++) {
                var text = (allButtons[j].textContent || allButtons[j].value || '').toLowerCase();
                if (text.indexOf('buy now') !== -1 || text.indexOf('complete order') !== -1 ||
                    text.indexOf('place order') !== -1 || text.indexOf('submit order') !== -1) {
                    buyBtn = allButtons[j];
                    break;
                }
            }
        }

        if (buyBtn) {
            buyBtn.addEventListener('click', function() {
                sendData('purchase_attempted');
            });
        }

        // Also catch form submission
        var forms = document.querySelectorAll('form');
        for (var k = 0; k < forms.length; k++) {
            forms[k].addEventListener('submit', function() {
                sendData('purchase_attempted');
            });
        }
    }

    // ============================================================
    // BEFORE UNLOAD — send final state via sendBeacon
    // ============================================================
    function setupBeforeUnload() {
        window.addEventListener('beforeunload', function() {
            if (!state.leadCreated) return;
            var fields = collectFields();
            var payload = JSON.stringify({
                action: 'log_checkout_lead',
                checkout_uuid: checkoutUUID,
                sessid2: context.sessid2,
                aff_id: context.aff_id,
                sub_id: context.sub_id,
                account_id: context.account_id,
                product_codename: context.product_codename,
                email: fields.email,
                phone: fields.phone,
                full_name: fields.full_name,
                address: fields.address,
                country: fields.country,
                state: fields.state,
                city: fields.city,
                zip: fields.zip,
                payment_method: fields.payment_method,
                status: state.fields.payment_method ? 'form_filled' : getStatus(fields),
                checkout_url: window.location.href,
                referrer: document.referrer || ''
            });
            if (navigator.sendBeacon) {
                navigator.sendBeacon(API_URL, payload);
            }
        });
    }

    // ============================================================
    // MUTATION OBSERVER — re-attach if DOM changes (SPA behavior)
    // ============================================================
    function setupMutationObserver() {
        if (!window.MutationObserver) return;
        var attached = false;
        var observer = new MutationObserver(function() {
            if (attached) return;
            var emailField = fieldMap.email();
            if (emailField) {
                attached = true;
                observer.disconnect();
                attachListeners();
                attachBuyNowListener();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
        // Auto-disconnect after 30 seconds
        setTimeout(function() { observer.disconnect(); }, 30000);
    }

    // ============================================================
    // INIT
    // ============================================================
    function init() {
        // Try attaching immediately
        var emailField = fieldMap.email();
        if (emailField) {
            attachListeners();
            attachBuyNowListener();
        } else {
            // Form not ready yet — use MutationObserver
            setupMutationObserver();
        }
        setupBeforeUnload();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
