<?php
/**
 * ClickBank Tracking Pixel Endpoint
 * Fired by ClickBank ISR on Order Form visits and Confirmation Page purchases
 * Returns a 1x1 transparent GIF
 */

require_once __DIR__ . '/config.php';

header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ob_end_flush();
    flush();
}

try {
    $pdo = getDB();

    $pdo->exec("CREATE TABLE IF NOT EXISTS cb_pixel_hits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        domain_key VARCHAR(100) NOT NULL DEFAULT '',
        event_type VARCHAR(20) NOT NULL DEFAULT '',
        -- Shared (Order Form + Confirmation Page)
        verification_hash VARCHAR(255) DEFAULT NULL,
        role VARCHAR(20) DEFAULT NULL,
        affiliate_id VARCHAR(100) DEFAULT NULL,
        vendor VARCHAR(100) DEFAULT NULL,
        tracking_codes VARCHAR(255) DEFAULT NULL,
        product_title VARCHAR(255) DEFAULT NULL,
        upsell_flow_id VARCHAR(100) DEFAULT NULL,
        upsell_original_receipt VARCHAR(100) DEFAULT NULL,
        item_no VARCHAR(50) DEFAULT NULL,
        -- Confirmation Page only
        order_id VARCHAR(100) DEFAULT NULL,
        customer_billing_info TEXT DEFAULT NULL,
        customer_shipping_info TEXT DEFAULT NULL,
        transaction_type VARCHAR(50) DEFAULT NULL,
        currency VARCHAR(10) DEFAULT NULL,
        total_product_amount DECIMAL(10,2) DEFAULT NULL,
        total_tax_amount DECIMAL(10,2) DEFAULT NULL,
        total_shipping_amount DECIMAL(10,2) DEFAULT NULL,
        order_language VARCHAR(20) DEFAULT NULL,
        payment_method VARCHAR(50) DEFAULT NULL,
        transaction_time VARCHAR(100) DEFAULT NULL,
        -- Meta
        raw_params TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_domain_event (domain_key, event_type),
        INDEX idx_order_id (order_id),
        INDEX idx_affiliate (affiliate_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $event  = substr(trim($_GET['event']  ?? 'unknown'), 0, 20);
    $domain = substr(trim($_GET['domain'] ?? ''), 0, 100);

    // Shared params (Order Form + Confirmation Page)
    $vHash        = substr(trim($_GET['verificationHash']         ?? ''), 0, 255);
    $role         = substr(trim($_GET['role']                     ?? ''), 0, 20);
    $affId        = substr(trim($_GET['affiliate']                ?? ''), 0, 100);
    $vendor       = substr(trim($_GET['vendor']                   ?? ''), 0, 100);
    $trackingCodes= substr(trim($_GET['trackingCodes']            ?? ''), 0, 255);
    $productTitle = substr(trim($_GET['productTitle']             ?? ''), 0, 255);
    $upsellFlow   = substr(trim($_GET['upsellFlowId']             ?? ''), 0, 100);
    $upsellReceipt= substr(trim($_GET['upsellOriginalReceipt']    ?? ''), 0, 100);
    $itemNo       = substr(trim($_GET['itemNo']                   ?? ''), 0, 50);

    // Confirmation Page only
    $orderId        = substr(trim($_GET['receipt']               ?? ''), 0, 100);
    $billingInfo    = substr(trim($_GET['customerBillingInfo']   ?? ''), 0, 1000);
    $shippingInfo   = substr(trim($_GET['customerShippingInfo']  ?? ''), 0, 1000);
    $txType         = substr(trim($_GET['transactionType']       ?? ''), 0, 50);
    $currency       = substr(trim($_GET['currency']              ?? ''), 0, 10);
    $totalProduct   = is_numeric($_GET['totalProductAmount']  ?? '') ? floatval($_GET['totalProductAmount'])  : null;
    $totalTax       = is_numeric($_GET['totalTaxAmount']      ?? '') ? floatval($_GET['totalTaxAmount'])      : null;
    $totalShipping  = is_numeric($_GET['totalShippingAmount'] ?? '') ? floatval($_GET['totalShippingAmount']) : null;
    $orderLang      = substr(trim($_GET['orderLanguage']         ?? ''), 0, 20);
    $paymentMethod  = substr(trim($_GET['paymentMethod']         ?? ''), 0, 50);
    $txTime         = substr(trim($_GET['transactionTime']       ?? ''), 0, 100);

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
        ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]
        : ($_SERVER['REMOTE_ADDR'] ?? '');
    $ip = substr(trim($ip), 0, 45);
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

    $rawParams = json_encode($_GET);

    $stmt = $pdo->prepare("
        INSERT INTO cb_pixel_hits (
            domain_key, event_type,
            verification_hash, role, affiliate_id, vendor, tracking_codes,
            product_title, upsell_flow_id, upsell_original_receipt, item_no,
            order_id, customer_billing_info, customer_shipping_info,
            transaction_type, currency, total_product_amount, total_tax_amount,
            total_shipping_amount, order_language, payment_method, transaction_time,
            raw_params, ip_address, user_agent
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");
    $stmt->execute([
        $domain, $event,
        $vHash ?: null, $role ?: null, $affId ?: null, $vendor ?: null, $trackingCodes ?: null,
        $productTitle ?: null, $upsellFlow ?: null, $upsellReceipt ?: null, $itemNo ?: null,
        $orderId ?: null, $billingInfo ?: null, $shippingInfo ?: null,
        $txType ?: null, $currency ?: null, $totalProduct, $totalTax,
        $totalShipping, $orderLang ?: null, $paymentMethod ?: null, $txTime ?: null,
        $rawParams, $ip, $ua ?: null
    ]);

} catch (Exception $e) {
    error_log('cb-pixel error: ' . $e->getMessage());
}
