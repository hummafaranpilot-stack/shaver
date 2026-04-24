<?php
/**
 * Cron Mailer - Processes scheduled emails AND daily recurring reorder campaigns
 *
 * Setup cron (every 5 minutes):
 * # /5 * * * * php /home/u373133718/domains/shaver.trustednutraproduct.com/public_html/cron-mailer.php >> /tmp/cron-mailer.log 2>&1
 *
 * Or call via URL: https://shaver.trustednutraproduct.com/cron-mailer.php?key=shaver_cron_2026
 */

// Simple auth for web-based cron
if (php_sapi_name() !== 'cli') {
    if (($_GET['key'] ?? '') !== 'shaver_cron_2026') {
        http_response_code(403);
        die('Forbidden');
    }
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

$pdo = getDB();
$now = date('Y-m-d H:i:s');

echo "[" . $now . "] Cron mailer started\n";

// ================================================================
// PART 1: Process one-time scheduled emails
// ================================================================

// FIX: Use explicit column aliases to avoid collision between scheduled_emails and orders
$stmt = $pdo->prepare("SELECT
    se.id AS se_id, se.domain_id AS se_domain_id, se.order_id AS se_order_id,
    se.email_type, se.recipients AS se_recipients, se.scheduled_at,
    se.status AS se_status, se.coupon_code AS se_coupon_code,
    se.coupon_type AS se_coupon_type, se.coupon_amount AS se_coupon_amount,
    o.customer_name, o.customer_email, o.product_names, o.order_id,
    o.date_created, o.address, o.city, o.state, o.country, o.zip,
    o.customer_phone, o.total_amount, o.affiliate_id, o.affiliate_name,
    o.sku, o.product_codenames, o.domain_id
    FROM scheduled_emails se
    LEFT JOIN orders o ON o.order_id = se.order_id AND o.domain_id = se.domain_id
    WHERE se.status = 'pending' AND se.scheduled_at <= ?
    ORDER BY se.scheduled_at ASC
    LIMIT 20");
$stmt->execute([$now]);
$emails = $stmt->fetchAll();

if (empty($emails)) {
    echo "No pending scheduled emails.\n";
} else {
    echo "Found " . count($emails) . " scheduled email(s) to process.\n";

    foreach ($emails as $email) {
        $seId = $email['se_id'];
        $recipients = json_decode($email['se_recipients'], true);

        if (empty($recipients) || !is_array($recipients)) {
            $upd = $pdo->prepare("UPDATE scheduled_emails SET status = 'failed', error_message = 'Invalid recipients', sent_at = NOW() WHERE id = ?");
            $upd->execute([$seId]);
            echo "[FAIL] ID=$seId - Invalid recipients\n";
            continue;
        }

        $result = null;

        if ($email['email_type'] === 'reorder') {
            $coupon = [];
            if (!empty($email['se_coupon_code'])) {
                $coupon = [
                    'code'   => $email['se_coupon_code'],
                    'type'   => $email['se_coupon_type'] ?? 'percentage',
                    'amount' => $email['se_coupon_amount'] ?? 0
                ];
            }
            $result = sendReorderMail($email, $recipients, $coupon);
        }

        if ($result && $result['success']) {
            $upd = $pdo->prepare("UPDATE scheduled_emails SET status = 'sent', sent_at = NOW() WHERE id = ?");
            $upd->execute([$seId]);
            echo "[OK] ID=$seId Order=" . $email['order_id'] . " sent to " . implode(', ', $recipients) . "\n";
        } else {
            $error = $result['error'] ?? 'Unknown error';
            $upd = $pdo->prepare("UPDATE scheduled_emails SET status = 'failed', error_message = ?, sent_at = NOW() WHERE id = ?");
            $upd->execute([$error, $seId]);
            echo "[FAIL] ID=$seId Order=" . $email['order_id'] . " - " . $error . "\n";
        }
    }
}

// ================================================================
// PART 2: Process daily recurring reorder campaigns
// ================================================================

echo "\n--- Recurring campaigns ---\n";

// Find active campaigns where it's time to send (next_send_at <= NOW)
$stmt = $pdo->prepare("SELECT rc.*,
    o.customer_name, o.customer_email, o.product_names, o.date_created,
    o.address, o.city, o.state, o.country, o.zip, o.customer_phone,
    o.total_amount, o.affiliate_id, o.affiliate_name, o.sku, o.product_codenames
    FROM reorder_campaigns rc
    JOIN orders o ON o.order_id = rc.order_id AND o.domain_id = rc.domain_id
    WHERE rc.status = 'active' AND rc.next_send_at <= ?
    ORDER BY rc.next_send_at ASC
    LIMIT 20");
$stmt->execute([$now]);
$campaigns = $stmt->fetchAll();

if (empty($campaigns)) {
    echo "No recurring campaigns due.\n";
} else {
    echo "Found " . count($campaigns) . " campaign(s) to process.\n";

    foreach ($campaigns as $camp) {
        $campId = $camp['id'];
        $customerEmail = $camp['customer_email'];
        $domainId = $camp['domain_id'];
        $orderId = $camp['order_id'];
        $dayNum = (int)$camp['sends_count'] + 1;

        echo "  Campaign #$campId (Order $orderId, Day $dayNum)...\n";

        // CHECK: Has this customer placed a new order since the campaign started?
        $checkStmt = $pdo->prepare("SELECT order_id FROM orders
            WHERE domain_id = ? AND customer_email = ? AND order_id != ?
            AND date_created > ? AND was_canceled = 0
            ORDER BY date_created DESC LIMIT 1");
        $checkStmt->execute([$domainId, $customerEmail, $orderId, $camp['created_at']]);
        $reorder = $checkStmt->fetch();

        if ($reorder) {
            // Customer reordered! Stop campaign
            $upd = $pdo->prepare("UPDATE reorder_campaigns SET status = 'reordered', reorder_order_id = ?, stopped_at = NOW() WHERE id = ?");
            $upd->execute([$reorder['order_id'], $campId]);
            echo "  [REORDERED] Customer reordered (Order #{$reorder['order_id']}). Campaign stopped.\n";
            continue;
        }

        // Check max sends limit
        $maxSends = (int)($camp['max_sends'] ?: 30);
        if ($dayNum > $maxSends) {
            $upd = $pdo->prepare("UPDATE reorder_campaigns SET status = 'completed', stopped_at = NOW() WHERE id = ?");
            $upd->execute([$campId]);
            echo "  [COMPLETED] Max sends ($maxSends) reached. Campaign stopped.\n";
            continue;
        }

        // Build dynamic subject/title for this day
        $subjects = [
            'Your MetaTrim BHB Supply Update — Day ' . $dayNum,
            'Don\'t Run Out! Restock Your MetaTrim BHB',
            'Your MetaTrim BHB Bottles Should Be Finishing',
            'Stay On Track With MetaTrim BHB — Reorder Now',
            'MetaTrim BHB: Time For Your Next Supply',
        ];
        $subject = $subjects[($dayNum - 1) % count($subjects)];

        // Prepare recipients
        $recipients = json_decode($camp['recipients'], true);
        if (empty($recipients)) $recipients = [$customerEmail];

        // Build coupon
        $coupon = [];
        if (!empty($camp['coupon_code'])) {
            $coupon = [
                'code'   => $camp['coupon_code'],
                'type'   => $camp['coupon_type'] ?? 'percentage',
                'amount' => $camp['coupon_amount'] ?? 0
            ];
        }

        // Send with custom subject
        $result = sendReorderMail($camp, $recipients, $coupon, $subject);

        if ($result && $result['success']) {
            // Schedule next send (same time tomorrow)
            $nextSend = date('Y-m-d H:i:s', strtotime($camp['next_send_at'] . ' +1 day'));
            $upd = $pdo->prepare("UPDATE reorder_campaigns SET sends_count = sends_count + 1, last_sent_at = NOW(), next_send_at = ? WHERE id = ?");
            $upd->execute([$nextSend, $campId]);

            // Log the send
            $logStmt = $pdo->prepare("INSERT INTO reorder_mail_log (domain_id, order_id, recipients, subject, send_type, campaign_day, coupon_code, coupon_type, coupon_amount, sent_at)
                VALUES (?, ?, ?, ?, 'campaign', ?, ?, ?, ?, NOW())");
            $logStmt->execute([$domainId, $orderId, implode(', ', $recipients), $subject, $dayNum,
                $coupon['code'] ?? null, $coupon['type'] ?? null,
                !empty($coupon['amount']) ? $coupon['amount'] : null]);

            echo "  [OK] Day $dayNum sent to " . implode(', ', $recipients) . ". Next: $nextSend\n";
        } else {
            $error = $result['error'] ?? 'Unknown error';
            // Don't stop campaign on send failure, just log and retry next cycle
            $upd = $pdo->prepare("UPDATE reorder_campaigns SET last_error = ? WHERE id = ?");
            $upd->execute([$error, $campId]);
            echo "  [FAIL] Day $dayNum - $error (will retry)\n";
        }
    }
}

echo "\nDone.\n";
