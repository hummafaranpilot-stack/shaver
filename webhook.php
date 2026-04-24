<?php
/**
 * BuyGoods Analytics - Webhook Handler
 * Receives and processes webhooks from BuyGoods
 *
 * URL Format: webhook.php?type=new-order
 * Types: new-order, recurring, refund, cancel, chargeback, fulfilled
 */

require_once 'config.php';
require_once 'database.php';
require_once 'ipqs.php';
require_once 'smtp-mailer.php';

header('Content-Type: application/json');

// Get webhook type from URL
$type = $_GET['type'] ?? '';

// Get product token from URL (for multi-product tracking)
$productToken = $_GET['token'] ?? null;

// Get client IP
$ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Get raw POST data
$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

// If JSON decode failed, try parsing as form data
if ($payload === null && !empty($_POST)) {
    $payload = $_POST;
}

// If no data, create empty array (some tests send empty payloads)
if (empty($payload)) {
    $payload = ['test' => true, 'timestamp' => time()];
}

// Get database instance
$db = Database::getInstance();

// Look up product by token (if provided)
$trackedProductId = null;
$trackedProduct = null;
if ($productToken) {
    $trackedProduct = $db->getProductByToken($productToken);
    if ($trackedProduct) {
        $trackedProductId = $trackedProduct['id'];
    }
}

try {
    switch ($type) {
        case 'new-order':
            handleNewOrder($db, $payload, $ipAddress, $trackedProductId, $trackedProduct);
            break;

        case 'recurring':
            handleRecurring($db, $payload, $ipAddress, $trackedProductId);
            break;

        case 'refund':
            handleRefund($db, $payload, $ipAddress, $trackedProductId);
            break;

        case 'cancel':
            handleCancel($db, $payload, $ipAddress);
            break;

        case 'chargeback':
            handleChargeback($db, $payload, $ipAddress, $trackedProductId);
            break;

        case 'fulfilled':
            handleFulfilled($db, $payload, $ipAddress);
            break;

        case 'test':
            // Test endpoint - just log and return
            $db->logWebhook('test', $payload, $ipAddress, true);
            echo json_encode(['success' => true, 'message' => 'Test webhook received', 'data' => $payload, 'product_id' => $trackedProductId]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid webhook type']);
            exit;
    }

} catch (Exception $e) {
    $db->logWebhook($type, $payload, $ipAddress, false, $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ==================== WEBHOOK HANDLERS ====================

function handleNewOrder($db, $payload, $ipAddress, $trackedProductId = null, $trackedProduct = null) {
    $customerIp = $payload['customerIp'] ?? $ipAddress;

    // Get product SKU for pricing lookup
    $productSku = $payload['productSku'] ?? $payload['product_sku'] ?? $payload['sku'] ?? null;
    $skuPattern = $db->normalizeSkuPattern($productSku);

    // Get order date for date-range pricing lookup
    $orderDate = null;
    if (!empty($payload['orderDate'])) {
        $orderDate = date('Y-m-d', strtotime($payload['orderDate']));
    } elseif (!empty($payload['createdAt'])) {
        $orderDate = date('Y-m-d', strtotime($payload['createdAt']));
    }

    $orderData = [
        'tracked_product_id' => $trackedProductId,
        'order_id' => $payload['orderId'] ?? $payload['order_id'] ?? $payload['transactionId'] ?? null,
        'transaction_id' => $payload['transactionId'] ?? $payload['transaction_id'] ?? null,
        'product_id' => $payload['productId'] ?? $payload['product_id'] ?? null,
        'product_name' => $payload['productName'] ?? $payload['product_name'] ?? $payload['productTitle'] ?? null,
        'product_price' => floatval($payload['productPrice'] ?? $payload['product_price'] ?? $payload['amount'] ?? 0),
        'quantity' => intval($payload['quantity'] ?? 1),
        'customer_email' => $payload['email'] ?? $payload['customerEmail'] ?? $payload['customer_email'] ?? null,
        'customer_name' => $payload['customerName'] ?? $payload['customer_name'] ??
                          trim(($payload['firstName'] ?? '') . ' ' . ($payload['lastName'] ?? '')),
        'customer_phone' => $payload['phone'] ?? $payload['customerPhone'] ?? $payload['customer_phone'] ?? null,
        'customer_country' => $payload['country'] ?? $payload['customerCountry'] ?? null,
        'customer_state' => $payload['state'] ?? $payload['customerState'] ?? null,
        'customer_city' => $payload['city'] ?? $payload['customerCity'] ?? null,
        'customer_address' => $payload['address'] ?? $payload['customerAddress'] ?? null,
        'customer_zip' => $payload['zip'] ?? $payload['postalCode'] ?? $payload['customerZip'] ?? null,
        'affiliate_id' => $payload['affiliateId'] ?? $payload['affiliate_id'] ?? $payload['affId'] ?? null,
        'affiliate_name' => $payload['affiliateName'] ?? $payload['affiliate_name'] ?? null,
        'commission' => floatval($payload['commission'] ?? $payload['affiliateCommission'] ?? 0),
        'payment_method' => $payload['paymentMethod'] ?? $payload['payment_method'] ?? 'card',
        'currency' => $payload['currency'] ?? 'USD',
        'status' => 'completed',
        'ip_address' => $customerIp,
        'sku_pattern' => $skuPattern,
        'raw_data' => $payload
    ];

    // Calculate v2 financial fields if we have SKU pattern
    if ($skuPattern) {
        $financials = $db->calculateFinancials(
            $orderData['product_price'],
            $skuPattern,
            $orderData['commission'],
            $orderDate
        );

        if ($financials) {
            $orderData['base_price'] = $financials['base_price'];
            $orderData['taxes'] = $financials['taxes'];
            $orderData['processing_fee'] = $financials['processing_fee'];
            $orderData['allowance_hold'] = $financials['allowance_hold'];
            $orderData['net_amount'] = $financials['net_amount'];
            $orderData['is_upsell'] = $financials['is_upsell'];
        }
    }

    // Analyze IP for fraud if API key is configured (with fallback to second key)
    $fraudData = null;
    $apiKeys = [];
    if (defined('IPQS_API_KEY') && IPQS_API_KEY !== 'YOUR_API_KEY_HERE') {
        $apiKeys[] = IPQS_API_KEY;
    }
    if (defined('IPQS_API_KEY_2') && IPQS_API_KEY_2 !== 'YOUR_API_KEY_HERE') {
        $apiKeys[] = IPQS_API_KEY_2;
    }

    if (!empty($apiKeys) && !empty($customerIp)) {
        foreach ($apiKeys as $apiKey) {
            try {
                $ipqs = new IPQS($apiKey);
                $analysis = $ipqs->analyzeIP($customerIp);
                if ($analysis) {
                    $fraudData = $ipqs->extractFraudData($analysis);
                    $orderData['ip_country'] = $fraudData['country'];
                    $orderData['ip_city'] = $fraudData['city'];
                    $orderData['ip_region'] = $fraudData['region'];
                    $orderData['ip_proxy'] = $fraudData['proxy'] ? 1 : 0;
                    $orderData['ip_tor'] = $fraudData['tor'] ? 1 : 0;
                    $orderData['ip_fraud_score'] = $fraudData['fraud_score'];
                    $orderData['ip_analyzed'] = 1;
                    break; // Success, no need to try other keys
                }
            } catch (Exception $e) {
                error_log("IPQS Error with key: " . $e->getMessage());
                // Continue to try next key
            }
        }
    }

    $db->insertOrder($orderData);
    $db->logWebhook('new_order', $payload, $ipAddress, true);

    $response = [
        'success' => true,
        'message' => 'Order received and processed',
        'order_id' => $orderData['order_id']
    ];

    // Include financial calculations in response if available
    if (!empty($orderData['base_price'])) {
        $response['financials'] = [
            'total_collected' => $orderData['product_price'],
            'base_price' => $orderData['base_price'],
            'taxes' => $orderData['taxes'],
            'processing_fee' => $orderData['processing_fee'],
            'allowance_hold' => $orderData['allowance_hold'],
            'commission' => $orderData['commission'],
            'net_amount' => $orderData['net_amount'],
            'is_upsell' => (bool)$orderData['is_upsell']
        ];
    }

    // Send fraud alert email if conditions are met
    $alertSent = false;
    if ($fraudData) {
        $response['fraud_analysis'] = [
            'score' => $fraudData['fraud_score'],
            'risk_level' => IPQS::getRiskLevel($fraudData['fraud_score'])
        ];

        // Check if we need to send fraud alert
        $alertSent = sendFraudAlertEmail($orderData, $fraudData);
        if ($alertSent) {
            $response['fraud_alert_sent'] = true;
        }
    }

    echo json_encode($response);
}

function handleRecurring($db, $payload, $ipAddress, $trackedProductId = null) {
    $chargeData = [
        'tracked_product_id' => $trackedProductId,
        'charge_id' => $payload['chargeId'] ?? $payload['charge_id'] ?? $payload['transactionId'] ?? 'RC-' . time(),
        'order_id' => $payload['orderId'] ?? $payload['order_id'] ?? $payload['originalOrderId'] ?? null,
        'transaction_id' => $payload['transactionId'] ?? $payload['transaction_id'] ?? null,
        'product_id' => $payload['productId'] ?? $payload['product_id'] ?? null,
        'product_name' => $payload['productName'] ?? $payload['product_name'] ?? null,
        'amount' => floatval($payload['amount'] ?? $payload['chargeAmount'] ?? 0),
        'customer_email' => $payload['email'] ?? $payload['customerEmail'] ?? null,
        'customer_name' => $payload['customerName'] ?? $payload['customer_name'] ?? null,
        'affiliate_id' => $payload['affiliateId'] ?? $payload['affiliate_id'] ?? null,
        'currency' => $payload['currency'] ?? 'USD',
        'status' => ($payload['status'] ?? '') === 'failed' ? 'failed' : 'success',
        'raw_data' => $payload
    ];

    $db->insertRecurringCharge($chargeData);
    $db->logWebhook('recurring_charge', $payload, $ipAddress, true);

    echo json_encode([
        'success' => true,
        'message' => 'Recurring charge received and processed',
        'charge_id' => $chargeData['charge_id']
    ]);
}

function handleRefund($db, $payload, $ipAddress, $trackedProductId = null) {
    $refundData = [
        'tracked_product_id' => $trackedProductId,
        'refund_id' => $payload['refundId'] ?? $payload['refund_id'] ?? 'RF-' . time(),
        'order_id' => $payload['orderId'] ?? $payload['order_id'] ?? null,
        'transaction_id' => $payload['transactionId'] ?? $payload['transaction_id'] ?? null,
        'amount' => floatval($payload['amount'] ?? $payload['refundAmount'] ?? 0),
        'reason' => $payload['reason'] ?? $payload['refundReason'] ?? 'Customer request',
        'refund_type' => $payload['refundType'] ?? (isset($payload['partialRefund']) ? 'partial' : 'full'),
        'raw_data' => $payload
    ];

    $db->insertRefund($refundData);
    $db->logWebhook('refund', $payload, $ipAddress, true);

    echo json_encode([
        'success' => true,
        'message' => 'Refund received and processed',
        'refund_id' => $refundData['refund_id']
    ]);
}

function handleCancel($db, $payload, $ipAddress) {
    $cancelData = [
        'cancel_id' => $payload['cancelId'] ?? $payload['cancel_id'] ?? 'CN-' . time(),
        'order_id' => $payload['orderId'] ?? $payload['order_id'] ?? null,
        'reason' => $payload['reason'] ?? $payload['cancelReason'] ?? 'Customer request',
        'raw_data' => $payload
    ];

    $db->insertCancellation($cancelData);
    $db->logWebhook('cancellation', $payload, $ipAddress, true);

    echo json_encode([
        'success' => true,
        'message' => 'Cancellation received and processed',
        'cancel_id' => $cancelData['cancel_id']
    ]);
}

function handleChargeback($db, $payload, $ipAddress, $trackedProductId = null) {
    $chargebackData = [
        'tracked_product_id' => $trackedProductId,
        'chargeback_id' => $payload['chargebackId'] ?? $payload['chargeback_id'] ?? 'CB-' . time(),
        'order_id' => $payload['orderId'] ?? $payload['order_id'] ?? null,
        'transaction_id' => $payload['transactionId'] ?? $payload['transaction_id'] ?? null,
        'amount' => floatval($payload['amount'] ?? $payload['chargebackAmount'] ?? 0),
        'reason' => $payload['reason'] ?? $payload['chargebackReason'] ?? 'Chargeback filed',
        'raw_data' => $payload
    ];

    $db->insertChargeback($chargebackData);
    $db->logWebhook('chargeback', $payload, $ipAddress, true);

    echo json_encode([
        'success' => true,
        'message' => 'Chargeback received and processed',
        'chargeback_id' => $chargebackData['chargeback_id']
    ]);
}

function handleFulfilled($db, $payload, $ipAddress) {
    $fulfillmentData = [
        'fulfillment_id' => $payload['fulfillmentId'] ?? $payload['fulfillment_id'] ?? 'FL-' . time(),
        'order_id' => $payload['orderId'] ?? $payload['order_id'] ?? null,
        'tracking_number' => $payload['trackingNumber'] ?? $payload['tracking_number'] ?? null,
        'carrier' => $payload['carrier'] ?? $payload['shippingCarrier'] ?? null,
        'shipped_at' => $payload['shippedAt'] ?? $payload['shipped_at'] ?? date('Y-m-d H:i:s'),
        'raw_data' => $payload
    ];

    $db->insertFulfillment($fulfillmentData);
    $db->logWebhook('fulfillment', $payload, $ipAddress, true);

    echo json_encode([
        'success' => true,
        'message' => 'Fulfillment received and processed',
        'fulfillment_id' => $fulfillmentData['fulfillment_id']
    ]);
}

// ==================== FRAUD ALERT EMAIL ====================

function sendFraudAlertEmail($orderData, $fraudData) {
    if (!defined('FRAUD_ALERT_EMAIL')) {
        return false;
    }

    $ipqsScore = $fraudData['fraud_score'] ?? 0;

    // Calculate our custom analysis score
    $customScore = 0;
    $customFlags = [];

    $isAllCaps = function($str) {
        return $str && strlen($str) > 2 && $str === strtoupper($str) && preg_match('/[A-Z]/', $str);
    };

    $hasMiddleName = function($name) {
        return $name && count(preg_split('/\s+/', trim($name))) >= 3;
    };

    if ($isAllCaps($orderData['customer_name'] ?? '')) {
        $customFlags[] = 'CAPS Name (+35)';
        $customScore += 35;
    }
    if ($isAllCaps($orderData['customer_address'] ?? '')) {
        $customFlags[] = 'CAPS Address (+25)';
        $customScore += 25;
    }
    if ($isAllCaps($orderData['customer_city'] ?? '')) {
        $customFlags[] = 'CAPS City (+15)';
        $customScore += 15;
    }
    if (!empty($orderData['customer_email'])) {
        $emailLocal = explode('@', $orderData['customer_email'])[0];
        if ($isAllCaps($emailLocal)) {
            $customFlags[] = 'CAPS Email (+20)';
            $customScore += 20;
        }
    }
    if ($hasMiddleName($orderData['customer_name'] ?? '')) {
        $customFlags[] = 'Middle Name (+10)';
        $customScore += 10;
    }

    // Email Alert Conditions:
    // 1. IPQS >= 50 → Always send (priority to IPQS)
    // 2. IPQS 20-49 AND Our Analysis >= 50 → Send (our analysis catches fraud)
    // 3. IPQS < 20 AND Our Analysis < 50 → Don't send
    $shouldSend = false;
    $alertReason = '';

    if ($ipqsScore >= 50) {
        $shouldSend = true;
        $alertReason = "IPQS High Risk ({$ipqsScore})";
    } elseif ($ipqsScore >= 20 && $customScore >= 50) {
        $shouldSend = true;
        $alertReason = "Combined Risk - IPQS: {$ipqsScore}, Our Analysis: {$customScore}";
    }

    if (!$shouldSend) {
        return false;
    }

    // Determine risk levels
    $ipqsRisk = $ipqsScore >= 85 ? 'HIGH RISK' : ($ipqsScore >= 75 ? 'SUSPICIOUS' : ($ipqsScore >= 50 ? 'MODERATE' : 'LOW'));
    $customRisk = $customScore >= 50 ? 'HIGH' : ($customScore >= 25 ? 'MODERATE' : 'LOW');

    // Format times
    $estTime = date('M j, Y g:i A', strtotime('now')) . ' EST';
    $pktTime = date('g:i A', strtotime('+10 hours')) . ' PKT';

    // Build email
    $to = FRAUD_ALERT_EMAIL;
    $subject = "⚠️ FRAUD ALERT: Order #{$orderData['order_id']} - IPQS: {$ipqsScore} | OA: {$customScore}";

    $fromName = defined('FRAUD_ALERT_FROM_NAME') ? FRAUD_ALERT_FROM_NAME : 'BuyGoods Analytics';
    $fromEmail = defined('FRAUD_ALERT_FROM') ? FRAUD_ALERT_FROM : 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

    // HTML Email Body - Using tables for email client compatibility
    $proxyBadge = $fraudData['proxy'] ? "<span style='background:#fee2e2;color:#dc2626;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;'>YES</span>" : "<span style='background:#d1fae5;color:#059669;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;'>No</span>";
    $torBadge = $fraudData['tor'] ? "<span style='background:#fee2e2;color:#dc2626;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;'>YES</span>" : "<span style='background:#d1fae5;color:#059669;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;'>No</span>";
    $scoreBadgeColor = $ipqsScore >= 75 ? '#fee2e2;color:#dc2626' : ($ipqsScore >= 50 ? '#fef3c7;color:#d97706' : '#d1fae5;color:#059669');

    $flagsHtml = '';
    if (empty($customFlags)) {
        $flagsHtml = "<tr><td style='padding:12px;text-align:center;'><span style='background:#d1fae5;color:#059669;padding:6px 14px;border-radius:12px;font-size:12px;font-weight:600;'>✓ No suspicious patterns</span></td></tr>";
    } else {
        $flagsHtml = "<tr><td style='padding:12px;'>";
        foreach ($customFlags as $flag) {
            $flagsHtml .= "<span style='display:inline-block;background:#fef3c7;color:#92400e;padding:4px 8px;border-radius:4px;font-size:11px;margin:2px;'>⚠️ {$flag}</span> ";
        }
        $flagsHtml .= "</td></tr>";
    }

    $body = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#f1f5f9;margin:0;padding:20px;'>
    <table width='100%' cellpadding='0' cellspacing='0' style='max-width:600px;margin:0 auto;'>
        <tr>
            <td style='background:linear-gradient(135deg,#dc2626,#991b1b);color:#fff;padding:30px 20px;text-align:center;border-radius:12px 12px 0 0;'>
                <div style='font-size:20px;font-weight:600;margin-bottom:8px;'>⚠️ FRAUD ALERT</div>
                <div style='font-size:56px;font-weight:700;line-height:1;'>{$ipqsScore}</div>
                <div style='font-size:14px;opacity:0.9;margin-top:8px;'>IPQS Fraud Score - {$ipqsRisk}</div>
            </td>
        </tr>
        <tr>
            <td style='background:#ffffff;padding:24px;'>
                <!-- Score Boxes -->
                <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom:20px;'>
                    <tr>
                        <td width='48%' style='background:#fee2e2;padding:16px;text-align:center;border-radius:8px;'>
                            <div style='font-size:36px;font-weight:700;color:#dc2626;'>{$ipqsScore}</div>
                            <div style='font-size:12px;color:#64748b;margin-top:4px;'>IPQS Score</div>
                        </td>
                        <td width='4%'></td>
                        <td width='48%' style='background:#e0e7ff;padding:16px;text-align:center;border-radius:8px;'>
                            <div style='font-size:36px;font-weight:700;color:#4338ca;'>{$customScore}</div>
                            <div style='font-size:12px;color:#64748b;margin-top:4px;'>Our Analysis</div>
                        </td>
                    </tr>
                </table>

                <!-- Order Information -->
                <table width='100%' cellpadding='0' cellspacing='0' style='border:1px solid #e2e8f0;border-radius:8px;margin-bottom:16px;'>
                    <tr><td style='background:#f8fafc;padding:12px 16px;font-weight:600;font-size:14px;border-bottom:1px solid #e2e8f0;'>📦 Order Information</td></tr>
                    <tr><td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;'><span style='color:#64748b;'>Order ID:</span> <strong>{$orderData['order_id']}</strong></td></tr>
                    <tr><td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;'><span style='color:#64748b;'>Product:</span> {$orderData['product_name']}</td></tr>
                    <tr><td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;'><span style='color:#64748b;'>Amount:</span> <strong>\${$orderData['product_price']}</strong></td></tr>
                    <tr><td style='padding:12px 16px;'><span style='color:#64748b;'>Date:</span> {$estTime} <span style='color:#94a3b8;'>({$pktTime})</span></td></tr>
                </table>

                <!-- Customer Information -->
                <table width='100%' cellpadding='0' cellspacing='0' style='border:1px solid #e2e8f0;border-radius:8px;margin-bottom:16px;'>
                    <tr><td style='background:#f8fafc;padding:12px 16px;font-weight:600;font-size:14px;border-bottom:1px solid #e2e8f0;'>👤 Customer Information</td></tr>
                    <tr><td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;'><span style='color:#64748b;'>Name:</span> {$orderData['customer_name']}</td></tr>
                    <tr><td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;'><span style='color:#64748b;'>Email:</span> {$orderData['customer_email']}</td></tr>
                    <tr><td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;'><span style='color:#64748b;'>Phone:</span> " . ($orderData['customer_phone'] ?? 'N/A') . "</td></tr>
                    <tr><td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;'><span style='color:#64748b;'>Address:</span> " . ($orderData['customer_address'] ?? 'N/A') . "</td></tr>
                    <tr><td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;'><span style='color:#64748b;'>City/State:</span> " . ($orderData['customer_city'] ?? '') . ", " . ($orderData['customer_state'] ?? '') . "</td></tr>
                    <tr><td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;'><span style='color:#64748b;'>Country:</span> {$orderData['customer_country']}</td></tr>
                    <tr><td style='padding:12px 16px;'><span style='color:#64748b;'>ZIP:</span> " . ($orderData['customer_zip'] ?? 'N/A') . "</td></tr>
                </table>

                <!-- Affiliate Information -->
                <table width='100%' cellpadding='0' cellspacing='0' style='border:1px solid #e2e8f0;border-radius:8px;margin-bottom:16px;'>
                    <tr><td style='background:#f8fafc;padding:12px 16px;font-weight:600;font-size:14px;border-bottom:1px solid #e2e8f0;'>🤝 Affiliate Information</td></tr>
                    <tr><td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;'><span style='color:#64748b;'>Affiliate ID:</span> " . ($orderData['affiliate_id'] ?? 'Direct') . "</td></tr>
                    <tr><td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;'><span style='color:#64748b;'>Affiliate Name:</span> " . ($orderData['affiliate_name'] ?? 'N/A') . "</td></tr>
                    <tr><td style='padding:12px 16px;'><span style='color:#64748b;'>Commission:</span> \$" . number_format($orderData['commission'] ?? 0, 2) . "</td></tr>
                </table>

                <!-- IPQS Analysis -->
                <table width='100%' cellpadding='0' cellspacing='0' style='border:1px solid #e2e8f0;border-radius:8px;margin-bottom:16px;'>
                    <tr><td style='background:#f8fafc;padding:12px 16px;font-weight:600;font-size:14px;border-bottom:1px solid #e2e8f0;'>🛡️ IPQualityScore Analysis</td></tr>
                    <tr><td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;'><span style='color:#64748b;'>IP Address:</span> <code style='background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:13px;'>{$orderData['ip_address']}</code></td></tr>
                    <tr><td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;'><span style='color:#64748b;'>IP Location:</span> " . ($fraudData['city'] ?? 'Unknown') . ", " . ($fraudData['region'] ?? '') . ", " . ($fraudData['country'] ?? 'Unknown') . "</td></tr>
                    <tr><td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;'><span style='color:#64748b;'>Fraud Score:</span> <span style='background:{$scoreBadgeColor};padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;'>{$ipqsScore}</span></td></tr>
                    <tr><td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;'><span style='color:#64748b;'>Proxy:</span> {$proxyBadge}</td></tr>
                    <tr><td style='padding:12px 16px;'><span style='color:#64748b;'>TOR:</span> {$torBadge}</td></tr>
                </table>

                <!-- Our Analysis -->
                <table width='100%' cellpadding='0' cellspacing='0' style='border:1px solid #e2e8f0;border-radius:8px;'>
                    <tr><td style='background:#f8fafc;padding:12px 16px;font-weight:600;font-size:14px;border-bottom:1px solid #e2e8f0;'>🔍 Our Pattern Analysis (Score: {$customScore})</td></tr>
                    {$flagsHtml}
                </table>
            </td>
        </tr>
        <tr>
            <td style='background:#f8fafc;padding:20px;text-align:center;font-size:12px;color:#64748b;border-radius:0 0 12px 12px;border-top:1px solid #e2e8f0;'>
                <strong>Alert Reason:</strong> {$alertReason}<br><br>
                BuyGoods Analytics Fraud Alert System<br>
                This is an automated alert. Please review carefully.
            </td>
        </tr>
    </table>
</body>
</html>";

    // Send email via SMTP
    $sent = sendSMTPEmail($to, $subject, $body);

    // Log the alert
    error_log("Fraud Alert Email " . ($sent ? "SENT" : "FAILED") . " for Order #{$orderData['order_id']} - IPQS: {$ipqsScore}, Custom: {$customScore}");

    return $sent;
}
