<?php
/**
 * ClickBank Instant Payment Notification (IPN) Receiver — v8.0 (encrypted)
 * Handles: SALE, BILL, RFND, CGBK, CANCEL, DECLINE, TEST_SALE
 */

require_once __DIR__ . '/config.php';

// ClickBank IPN secret key — must match what you set in CB dashboard
define('CB_IPN_SECRET', 'IPNTNP2026');

// Always respond 200 immediately
http_response_code(200);
echo 'OK';
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
else { ob_end_flush(); flush(); }

try {
    $pdo = getDB();

    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS cb_ipn_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        notification_type VARCHAR(20) DEFAULT NULL,
        receipt VARCHAR(100) DEFAULT NULL,
        vendor VARCHAR(100) DEFAULT NULL,
        affiliate VARCHAR(100) DEFAULT NULL,
        product VARCHAR(255) DEFAULT NULL,
        sku VARCHAR(100) DEFAULT NULL,
        amount DECIMAL(10,2) DEFAULT NULL,
        currency VARCHAR(10) DEFAULT NULL,
        tracking_codes VARCHAR(255) DEFAULT NULL,
        txn_type VARCHAR(50) DEFAULT NULL,
        customer_email VARCHAR(255) DEFAULT NULL,
        customer_name VARCHAR(255) DEFAULT NULL,
        customer_address1 VARCHAR(255) DEFAULT NULL,
        customer_address2 VARCHAR(255) DEFAULT NULL,
        customer_city VARCHAR(100) DEFAULT NULL,
        customer_state VARCHAR(100) DEFAULT NULL,
        customer_zip VARCHAR(20) DEFAULT NULL,
        customer_country VARCHAR(10) DEFAULT NULL,
        customer_phone VARCHAR(50) DEFAULT NULL,
        shipping_name VARCHAR(255) DEFAULT NULL,
        shipping_address1 VARCHAR(255) DEFAULT NULL,
        shipping_city VARCHAR(100) DEFAULT NULL,
        shipping_state VARCHAR(100) DEFAULT NULL,
        shipping_zip VARCHAR(20) DEFAULT NULL,
        shipping_country VARCHAR(10) DEFAULT NULL,
        upsell_receipt VARCHAR(100) DEFAULT NULL,
        subscription_id VARCHAR(100) DEFAULT NULL,
        next_payment_date VARCHAR(50) DEFAULT NULL,
        payment_method VARCHAR(50) DEFAULT NULL,
        vendor_amount DECIMAL(10,2) DEFAULT NULL,
        verified TINYINT(1) DEFAULT 0,
        raw_post TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_receipt (receipt),
        INDEX idx_type (notification_type),
        INDEX idx_affiliate (affiliate),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add columns if they don't exist (for existing tables)
    try { $pdo->exec("ALTER TABLE cb_ipn_events ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL AFTER next_payment_date"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE cb_ipn_events ADD COLUMN vendor_amount DECIMAL(10,2) DEFAULT NULL AFTER payment_method"); } catch (Exception $e) {}

    // Read raw POST body
    $raw = file_get_contents('php://input');

    // IPN v8.0: encrypted JSON with { notification, iv }
    $envelope = json_decode($raw, true);
    $order = null;
    $verified = 0;

    if ($envelope && isset($envelope['notification']) && isset($envelope['iv'])) {
        // Decrypt AES-256-CBC
        $key = substr(sha1(CB_IPN_SECRET), 0, 32);
        $decrypted = openssl_decrypt(
            base64_decode($envelope['notification']),
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            base64_decode($envelope['iv'])
        );
        if ($decrypted !== false) {
            $decrypted = trim($decrypted, "\0..\32");
            $order = json_decode($decrypted, true);
            $verified = ($order && is_array($order)) ? 1 : 0;
        }
    }

    // Fallback: try plain POST / form-encoded (older IPN versions)
    if (!$order) {
        $post = $_POST;
        if (empty($post)) {
            $decoded = json_decode($raw, true);
            if ($decoded && is_array($decoded) && isset($decoded['receipt'])) {
                $order = $decoded;
            } else {
                parse_str($raw, $post);
                if (!empty($post['receipt'])) $order = $post;
            }
        } else {
            $order = $post;
        }
        // Legacy verification via cverify
        if ($order && !empty($order['cverify'])) {
            $txnType  = strtoupper(trim($order['txn_type'] ?? $order['transactionType'] ?? ''));
            $receipt  = trim($order['receipt'] ?? '');
            $amount   = trim($order['amount'] ?? $order['totalAccountAmount'] ?? '');
            $expected = strtoupper(substr(md5(strtoupper(md5(CB_IPN_SECRET) . $txnType . $receipt . $amount)), 0, 8));
            $verified = ($expected === strtoupper(trim($order['cverify']))) ? 1 : 0;
        }
    }

    if (!$order || !is_array($order)) {
        // Store raw for debugging even if we couldn't parse
        $pdo->prepare("INSERT INTO cb_ipn_events (raw_post, ip_address) VALUES (?, ?)")->execute([
            $raw, substr(trim($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)
        ]);
        exit;
    }

    // Extract fields from v8.0 nested structure
    $billing  = $order['customer']['billing'] ?? [];
    $shipping = $order['customer']['shipping'] ?? [];
    $billingAddr  = $billing['address'] ?? [];
    $shippingAddr = $shipping['address'] ?? [];
    $lineItems = $order['lineItems'] ?? [];
    $firstItem = $lineItems[0] ?? [];

    $txnType = strtoupper($order['transactionType'] ?? $order['txn_type'] ?? $order['notification_type'] ?? '');
    $receipt = $order['receipt'] ?? null;
    $productTitle = $firstItem['productTitle'] ?? ($order['product'] ?? null);
    $itemNo = $firstItem['itemNo'] ?? ($order['sku'] ?? null);
    $totalAmount = $order['totalOrderAmount'] ?? $order['amount'] ?? null;
    $vendorAmount = $order['totalAccountAmount'] ?? null;

    $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR'])
        ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]
        : ($_SERVER['REMOTE_ADDR'] ?? '');

    $trackingCodes = $order['trackingCodes'] ?? null;
    if (is_array($trackingCodes)) $trackingCodes = implode(',', $trackingCodes);

    $stmt = $pdo->prepare("
        INSERT INTO cb_ipn_events (
            notification_type, receipt, vendor, affiliate, product, sku,
            amount, currency, tracking_codes, txn_type,
            customer_email, customer_name,
            customer_address1, customer_address2, customer_city, customer_state,
            customer_zip, customer_country, customer_phone,
            shipping_name, shipping_address1, shipping_city, shipping_state,
            shipping_zip, shipping_country,
            upsell_receipt, subscription_id, next_payment_date,
            payment_method, vendor_amount,
            verified, raw_post, ip_address
        ) VALUES (
            ?,?,?,?,?,?, ?,?,?,?, ?,?,
            ?,?,?,?,?,?,?, ?,?,?,?,?,?,
            ?,?,?, ?,?, ?,?,?
        )
    ");
    $stmt->execute([
        $txnType,
        $receipt,
        $order['vendor'] ?? null,
        $order['affiliate'] ?? null,
        $productTitle,
        $itemNo,
        is_numeric($totalAmount) ? floatval($totalAmount) : null,
        $order['currency'] ?? null,
        $trackingCodes,
        $txnType,
        $billing['email'] ?? ($order['customerEmail'] ?? null),
        trim(($billing['firstName'] ?? '') . ' ' . ($billing['lastName'] ?? '')) ?: ($order['customerName'] ?? null),
        $billingAddr['address1'] ?? ($order['customerAddress1'] ?? null),
        $billingAddr['address2'] ?? ($order['customerAddress2'] ?? null),
        $billingAddr['city'] ?? ($order['customerCity'] ?? null),
        $billingAddr['state'] ?? ($order['customerState'] ?? null),
        $billingAddr['postalCode'] ?? ($order['customerZip'] ?? null),
        $billingAddr['country'] ?? ($order['customerCountry'] ?? null),
        $billing['phoneNumber'] ?? ($order['customerPhone'] ?? null),
        trim(($shipping['firstName'] ?? '') . ' ' . ($shipping['lastName'] ?? '')) ?: null,
        $shippingAddr['address1'] ?? null,
        $shippingAddr['city'] ?? null,
        $shippingAddr['state'] ?? null,
        $shippingAddr['postalCode'] ?? null,
        $shippingAddr['country'] ?? null,
        $order['upsell']['upsellOriginalReceipt'] ?? null,
        $order['subscriptionId'] ?? null,
        $order['nextPaymentDate'] ?? null,
        $order['paymentMethod'] ?? null,
        is_numeric($vendorAmount) ? floatval($vendorAmount) : null,
        $verified,
        $raw,
        substr(trim($ip), 0, 45),
    ]);

    // Auto-update order status
    $statusMap = [
        'RFND' => 'Refunded', 'CGBK' => 'Chargeback', 'INSF' => 'Chargeback',
        'CANCEL' => 'Canceled', 'CANCEL-REBILL' => 'Canceled'
    ];
    if ($receipt && isset($statusMap[$txnType])) {
        $upd = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $upd->execute([$statusMap[$txnType], $receipt]);
    }

} catch (Exception $e) {
    error_log('cb-ipn error: ' . $e->getMessage());
}
