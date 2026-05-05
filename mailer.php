<?php
/**
 * Mail Sender - Uses PHPMailer with SendGrid SMTP
 * Sends order-related emails to customers
 *
 * NOTE: PHPMailer is loaded ONLY inside send functions so that
 * build*() functions can be used for preview without loading PHPMailer.
 */

require_once __DIR__ . '/config.php';

/**
 * Per-domain email branding lookup.
 *
 * Returns the brand pack (name, logo, bottle image, subject template, delay
 * reason text) used in delay/dispatch/comp emails for the given domain. Adding
 * a new branded domain = adding a case below — no template edits needed.
 *
 * Default fallback is MetaTrim BHB (the original brand the templates were
 * built for) so any unconfigured domain keeps the existing look.
 */
function getDomainEmailBranding($domainId) {
    $default = [
        'name'         => 'MetaTrim BHB',
        'logo_url'     => 'https://metatrim.trustednutraproduct.com/v2/lib/img/logo.png',
        'bottle_url'   => 'https://metatrim.trustednutraproduct.com/v2/lib/img/prod1.png',
        'subject'      => 'Your MetaTrim BHB Order Has Been Confirmed',
        'reason_short' => 'an upcoming warehouse audit',
        'reason_long'  => 'an upcoming audit at our warehouse',
        'theme' => [
            'header_bg'         => '#2d2d2d',
            'header_text'       => '#999999',
            'hero_bg'           => '#fdf8f3',
            'accent'            => '#2E7D32',
            'accent_soft_bg'    => '#e8f5e9',
            'footer_bg'         => '#2d2d2d',
            'footer_text'       => '#888888',
            'footer_meta_text'  => '#666666',
            'footer_divider'    => '#444444',
            'product_img_width' => 75,
            'product_img_pad'   => 18,
        ],
    ];

    if (empty($domainId)) return $default;

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT LOWER(TRIM(label)) AS label FROM domains WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$domainId]);
        $label = (string)$stmt->fetchColumn();
    } catch (Exception $e) {
        return $default;
    }

    switch ($label) {
        case 'metaflow sugar':
            return [
                'name'         => 'MetaFlow Sugar',
                'logo_url'     => 'https://metaflow.trustednutraproduct.com/cb/logo.webp?v=20260427d',
                'bottle_url'   => 'https://metaflow.trustednutraproduct.com/cb/product_images/5plus1_bottles.webp?v=20260429',
                'subject'      => 'Your MetaFlow Sugar Order Has Been Confirmed',
                'reason_short' => 'high demand and daily bulk orders',
                'reason_long'  => 'high demand and daily bulk orders',
                'theme' => [
                    // Header & footer use the landing page's dark palette so the
                    // logo's WEBP transparency (which some email clients fall back
                    // to as a dark fill) is hidden against an equally dark band.
                    // Body content stays light for readability.
                    'header_bg'         => '#14141c',
                    'header_text'       => '#94a3b8',
                    'hero_bg'           => '#f8fafc',
                    'accent'            => '#53CB9B',
                    'accent_soft_bg'    => '#e6faf2',
                    'footer_bg'         => '#14141c',
                    'footer_text'       => '#cbd5e1',
                    'footer_meta_text'  => '#94a3b8',
                    'footer_divider'    => '#334155',
                    'product_img_width' => 130,
                    'product_img_pad'   => 22,
                ],
            ];
    }

    return $default;
}

/**
 * Apply deliverability headers to a PHPMailer instance
 * Adds List-Unsubscribe, plain-text fallback, and proper encoding
 */
function applyMailHeaders($mail, $html) {
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'quoted-printable'; // More reliable than 8bit/base64 for HTML emails
    $mail->addCustomHeader('List-Unsubscribe', '<mailto:' . SMTP_FROM_EMAIL . '?subject=unsubscribe>');
    $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
    // Disable SendGrid click/open tracking (they modify HTML and break layout)
    $mail->addCustomHeader('X-SMTPAPI', json_encode([
        'filters' => [
            'clicktrack'  => ['settings' => ['enable' => 0]],
            'opentrack'   => ['settings' => ['enable' => 0]]
        ]
    ]));
    // Plain-text fallback (strip HTML tags)
    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>'], "\n", $html));
}

/**
 * Build the email HTML from order data (for preview or sending)
 *
 * @param array  $orders         All delayed orders for this customer
 * @param string $deliveryStart  Optional start date (Y-m-d)
 * @param string $deliveryEnd    Optional end date (Y-m-d)
 * @return string  Full HTML email
 */
function buildDelayMailFromOrders(array $orders, $deliveryStart = '', $deliveryEnd = '') {
    $primary = $orders[0];
    $brand   = getDomainEmailBranding($primary['domain_id'] ?? null);

    // Build order date from earliest order
    $orderDate = '';
    foreach ($orders as $o) {
        if (!empty($o['date_created'])) {
            $ts = strtotime($o['date_created']);
            if ($ts) {
                $orderDate = date('F jS, Y \a\t g:i A', $ts);
                break;
            }
        }
    }

    // Build delivery date text using brand-specific delay reason
    $reasonShort = $brand['reason_short']; // "Due to ..."
    $reasonLong  = $brand['reason_long'];  // "due to ... at our warehouse"
    if (!empty($deliveryStart) && !empty($deliveryEnd)) {
        $ds = strtotime($deliveryStart);
        $de = strtotime($deliveryEnd);
        $startFmt = date('F jS', $ds);
        $endFmt   = date('F jS, Y', $de);
        $rangeFmt = $startFmt . ' &ndash; ' . $endFmt;
        $deliveryEstimate = 'Estimated delivery: <strong style="color: #555555;">2-3 working days</strong>. Due to ' . $reasonShort . ', delivery may be delayed to <strong style="color: #555555;">' . $rangeFmt . '</strong>.';
        $deliveryNotice   = 'Your order is expected within <strong style="color: #555555;">2-3 working days</strong>. However, due to ' . $reasonLong . ', delivery may be delayed to <strong style="color: #555555;">' . $rangeFmt . '</strong>.';
    } elseif (!empty($deliveryStart)) {
        $ds = strtotime($deliveryStart);
        $startFmt = date('F jS, Y', $ds);
        $deliveryEstimate = 'Estimated delivery: <strong style="color: #555555;">2-3 working days</strong>. Due to ' . $reasonShort . ', delivery may be delayed to <strong style="color: #555555;">' . $startFmt . '</strong>.';
        $deliveryNotice   = 'Your order is expected within <strong style="color: #555555;">2-3 working days</strong>. However, due to ' . $reasonLong . ', delivery may be delayed to <strong style="color: #555555;">' . $startFmt . '</strong>.';
    } else {
        $deliveryEstimate = 'Estimated delivery: <strong style="color: #555555;">2-3 working days</strong>. Due to ' . $reasonShort . ', delivery may be delayed by <strong style="color: #555555;">1-2 weeks</strong>.';
        $deliveryNotice   = 'Your order is expected within <strong style="color: #555555;">2-3 working days</strong>. However, due to ' . $reasonLong . ', delivery may be delayed by <strong style="color: #555555;">1-2 weeks</strong>.';
    }

    // Build product rows HTML
    $productsHtml = '';
    foreach ($orders as $i => $o) {
        $productName = htmlspecialchars($o['product_names'] ?? $brand['name']);
        $orderId     = htmlspecialchars($o['order_id'] ?? '');
        $isUpsell    = ($o['flag_upsell'] == 1);

        $qty = 1;
        if (preg_match('/(\d+)\s*\+\s*(\d+)/', $productName, $m)) {
            $qty = intval($m[1]) + intval($m[2]);
        } elseif (preg_match('/(\d+)\s*Bottle/i', $productName, $m)) {
            $qty = intval($m[1]);
        }

        $imgUrl   = $brand['bottle_url'];
        $imgWidth = (int)($brand['theme']['product_img_width'] ?? 75);
        $imgPad   = (int)($brand['theme']['product_img_pad']   ?? 18);
        $cellW    = $imgWidth + 30;
        $accentInline = $brand['theme']['accent'] ?? '#2E7D32';

        $displayName = $brand['name'] . ' Bottle' . ($qty > 1 ? 's' : '');
        $padding = ($i === 0) ? '25px 40px 0 40px' : '12px 40px 0 40px';

        $upsellBadge = '';
        if ($isUpsell) {
            $upsellBadge = '
                        <td align="right" style="vertical-align: top;">
                          <span style="display: inline-block; background-color: #2E7D32; color: #ffffff; font-size: 10px; font-weight: 700; padding: 4px 10px; border-radius: 3px; text-transform: uppercase;">Upsell Discount</span>
                        </td>';
        }

        $nameCell = $isUpsell
            ? '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td><p style="margin: 0 0 4px 0; color: #1a1a1a; font-size: 15px; font-weight: 700;">' . $displayName . '</p></td>' . $upsellBadge . '</tr></table>'
            : '<p style="margin: 0 0 4px 0; color: #1a1a1a; font-size: 15px; font-weight: 700;">' . $displayName . '</p>';

        $productsHtml .= '
          <tr>
            <td class="email-padding" style="padding: ' . $padding . '; background-color: #ffffff;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border: 1px solid #eeeeee; border-radius: 8px;">
                <tr>
                  <td style="padding: ' . $imgPad . 'px; width: ' . $cellW . 'px; background-color: #fafafa; text-align: center; vertical-align: middle;">
                    <img src="' . $imgUrl . '" alt="' . $displayName . '" width="' . $imgWidth . '" style="display: block; margin: 0 auto; height: auto; max-width: ' . $imgWidth . 'px;">
                  </td>
                  <td style="padding: 18px 20px;">
                    ' . $nameCell . '
                    <p style="margin: 0 0 4px 0; color: #888888; font-size: 13px;">Quantity: ' . $qty . '</p>
                    <p style="margin: 0; color: #aaaaaa; font-size: 12px;">Order ID: <span style="color: ' . $accentInline . '; font-weight: 700;">' . $orderId . '</span></p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>';
    }

    // Shipping address
    $name    = htmlspecialchars($primary['customer_name'] ?? '');
    $address = htmlspecialchars($primary['address'] ?? '');
    $city    = htmlspecialchars($primary['city'] ?? '');
    $state   = htmlspecialchars($primary['state'] ?? '');
    $country = htmlspecialchars($primary['country'] ?? '');
    $zip     = htmlspecialchars($primary['zip'] ?? '');
    $phone   = htmlspecialchars($primary['customer_phone'] ?? '');

    $addressLines = $name;
    if ($address) $addressLines .= '<br>' . $address;
    if ($city || $state) $addressLines .= '<br>' . $city . ($city && $state ? ', ' : '') . $state;
    if ($country || $zip) $addressLines .= '<br>' . $country . ($zip ? ', ' . $zip : '');

    return buildDelayEmailHtml($orderDate, $productsHtml, $addressLines, $name, $phone, $deliveryEstimate, $deliveryNotice, $brand);
}

/**
 * Send delay notification email to multiple recipients
 *
 * @param array  $orders         All delayed orders for this customer
 * @param array  $recipients     Array of email addresses to send to
 * @param string $deliveryStart  Optional start date (Y-m-d)
 * @param string $deliveryEnd    Optional end date (Y-m-d)
 * @return array  ['success' => bool, 'error' => string|null, 'sent_to' => []]
 */
function sendDelayMail(array $orders, array $recipients = [], $deliveryStart = '', $deliveryEnd = '') {
    if (empty($orders)) {
        return ['success' => false, 'error' => 'No orders provided'];
    }

    $primary = $orders[0];
    $brand   = getDomainEmailBranding($primary['domain_id'] ?? null);

    // If no recipients specified, use customer email
    if (empty($recipients)) {
        $recipients = [$primary['customer_email']];
    }

    $recipients = array_filter(array_unique($recipients));
    if (empty($recipients)) {
        return ['success' => false, 'error' => 'No valid recipients'];
    }

    $html = buildDelayMailFromOrders($orders, $deliveryStart, $deliveryEnd);

    // Load PHPMailer only when actually sending
    require_once __DIR__ . '/lib/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/lib/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/lib/PHPMailer/src/SMTP.php';

    // Send via PHPMailer
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        foreach ($recipients as $addr) {
            $mail->addAddress(trim($addr));
        }

        $mail->isHTML(true);
        $mail->Subject = $brand['subject'];
        $mail->Body    = $html;
        applyMailHeaders($mail, $html);

        $mail->send();
        return ['success' => true, 'sent_to' => $recipients];
    } catch (\Exception $e) {
        return ['success' => false, 'error' => 'Mail error: ' . $mail->ErrorInfo];
    }
}

/**
 * Build the full delay email HTML from template
 */
function buildDelayEmailHtml($orderDate, $productsHtml, $addressLines, $contactName, $phone, $deliveryEstimate, $deliveryNotice, $brand = null) {
    if (!$brand) $brand = getDomainEmailBranding(null);
    $brandName = htmlspecialchars($brand['name']);
    $brandLogo = htmlspecialchars($brand['logo_url']);
    $theme = $brand['theme'];
    $headerBg     = $theme['header_bg'];
    $headerText   = $theme['header_text'];
    $heroBg       = $theme['hero_bg'];
    $accent       = $theme['accent'];
    $accentSoftBg = $theme['accent_soft_bg'];
    $footerBg     = $theme['footer_bg'];
    $footerText   = $theme['footer_text'];
    $footerMeta   = $theme['footer_meta_text'];
    $footerDiv    = $theme['footer_divider'];
    return '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="color-scheme" content="light only">
  <meta name="supported-color-schemes" content="light only">
  <!--[if mso]>
  <style>body,table,td,h1,h2,h3,p,span{font-family:Arial,Helvetica,sans-serif!important;}</style>
  <![endif]-->
  <style>
    :root { color-scheme: light only; }
    [data-ogsc] .dark-bg { background-color: #2d2d2d !important; }
    [data-ogsb] .dark-bg { background-color: #2d2d2d !important; }
    @media only screen and (max-width: 620px) {
      .email-wrapper { width: 100% !important; }
      .email-padding { padding-left: 24px !important; padding-right: 24px !important; }
      .hero-title { font-size: 26px !important; }
      .tracker-padding { padding-left: 30px !important; padding-right: 30px !important; }
      .two-col td { display: block !important; width: 100% !important; padding-left: 0 !important; padding-right: 0 !important; padding-bottom: 15px !important; }
    }
  </style>
</head>
<body style="margin: 0; padding: 0; background-color: ' . $heroBg . '; font-family: Arial, Helvetica, sans-serif; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: ' . $heroBg . ';">
    <tr>
      <td align="center" style="padding: 0;">

        <!--[if mso]><table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" align="center"><tr><td><![endif]-->
        <table role="presentation" class="email-wrapper" cellspacing="0" cellpadding="0" border="0" style="width: 100%; max-width: 600px;">

          <!-- Top Nav Bar -->
          <tr>
            <td class="email-padding" style="padding: 16px 40px; background-color: ' . $headerBg . ';">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td style="vertical-align: middle;">
                    <img src="' . $brandLogo . '" alt="' . $brandName . '" width="120" style="display: block; height: auto; max-width: 120px;">
                  </td>
                  <td align="right" style="vertical-align: middle;">
                    <span style="color: ' . $headerText . '; font-size: 12px;">Order Confirmation</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Hero Section -->
          <tr>
            <td class="email-padding" style="padding: 50px 40px 35px 40px; text-align: center; background-color: ' . $heroBg . ';">
              <h1 class="hero-title" style="margin: 0 0 12px 0; color: #1a1a1a; font-size: 32px; font-weight: 900; line-height: 1.2;">Your order has been<br>confirmed!</h1>
              <p style="margin: 0; color: #777777; font-size: 14px; line-height: 1.6;">
                We\'re excited to get your ' . $brandName . ' products on their way to you.
              </p>
            </td>
          </tr>

          <!-- Order Progress Tracker -->
          <tr>
            <td class="tracker-padding" style="padding: 15px 50px 25px 50px; background-color: ' . $heroBg . ';">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td align="center" style="vertical-align: middle; width: 40px;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                      <tr>
                        <td style="width: 40px; height: 40px; background-color: ' . $accent . '; border-radius: 50%; text-align: center; vertical-align: middle;">
                          <span style="color: #ffffff; font-size: 18px; font-family: Arial, sans-serif;">&#10003;</span>
                        </td>
                      </tr>
                    </table>
                  </td>
                  <td style="vertical-align: middle;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                      <tr><td style="border-bottom: 3px solid #e0e0e0; font-size: 0; line-height: 0;">&nbsp;</td></tr>
                    </table>
                  </td>
                  <td align="center" style="vertical-align: middle; width: 40px;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                      <tr>
                        <td style="width: 40px; height: 40px; background-color: #e8e8e8; border-radius: 50%; text-align: center; vertical-align: middle;">
                          <span style="color: #bbbbbb; font-size: 14px; font-family: Arial, sans-serif;">&#9679;</span>
                        </td>
                      </tr>
                    </table>
                  </td>
                  <td style="vertical-align: middle;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                      <tr><td style="border-bottom: 3px solid #e0e0e0; font-size: 0; line-height: 0;">&nbsp;</td></tr>
                    </table>
                  </td>
                  <td align="center" style="vertical-align: middle; width: 40px;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                      <tr>
                        <td style="width: 40px; height: 40px; background-color: #e8e8e8; border-radius: 50%; text-align: center; vertical-align: middle;">
                          <span style="color: #bbbbbb; font-size: 14px; font-family: Arial, sans-serif;">&#9679;</span>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td align="center" style="padding-top: 8px;">
                    <p style="margin: 0; color: ' . $accent . '; font-size: 11px; font-weight: 700; line-height: 1.3;">Order<br>Confirmed</p>
                  </td>
                  <td>&nbsp;</td>
                  <td align="center" style="padding-top: 8px;">
                    <p style="margin: 0; color: #999999; font-size: 11px; font-weight: 600; line-height: 1.3;">Shipped</p>
                  </td>
                  <td>&nbsp;</td>
                  <td align="center" style="padding-top: 8px;">
                    <p style="margin: 0; color: #999999; font-size: 11px; font-weight: 600; line-height: 1.3;">Expected<br>Delivered</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Delivery Estimate -->
          <tr>
            <td class="email-padding" style="padding: 0 40px 15px 40px; text-align: center; background-color: ' . $heroBg . ';">
              <p style="margin: 0; color: ' . $footerText . '; font-size: 13px; line-height: 1.5;">
                ' . $deliveryEstimate . '
              </p>
            </td>
          </tr>

          <!-- FREE Gift Box Notice -->
          <tr>
            <td class="email-padding" style="padding: 10px 40px 40px 40px; background-color: ' . $heroBg . ';">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #E8F5E9; border-radius: 8px; border: 2px solid ' . $accent . ';">
                <tr>
                  <td style="padding: 25px; text-align: center;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                      <tr>
                        <td style="width: 50px; height: 50px; background-color: #4CAF50; border-radius: 50%; text-align: center; vertical-align: middle;">
                          <span style="color: white; font-size: 22px; font-family: Arial, sans-serif;">&#10004;</span>
                        </td>
                      </tr>
                    </table>
                    <h4 style="margin: 15px 0 10px 0; color: ' . $accent . '; font-size: 18px; font-weight: 800;">FREE Gift Box Added!</h4>
                    <p style="margin: 0; color: #555555; font-size: 14px; line-height: 1.6;">
                      As compensation for any delay, we\'ve added a <strong>complimentary gift box</strong> to your package at no extra cost!
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Divider -->
          <tr>
            <td class="email-padding" style="padding: 0 40px; background-color: #ffffff;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr><td style="border-top: 1px solid #eeeeee; font-size: 0; line-height: 0;">&nbsp;</td></tr>
              </table>
            </td>
          </tr>

          <!-- Order Details Heading -->
          <tr>
            <td class="email-padding" style="padding: 35px 40px 5px 40px; text-align: center; background-color: #ffffff;">
              <h2 style="margin: 0 0 8px 0; color: #1a1a1a; font-size: 24px; font-weight: 900;">Order details</h2>
              <p style="margin: 0; color: ' . $accent . '; font-size: 14px; font-weight: 700;">' . htmlspecialchars($orderDate) . '</p>
            </td>
          </tr>

          <!-- Products -->
          ' . $productsHtml . '

          <!-- Divider -->
          <tr>
            <td class="email-padding" style="padding: 25px 40px 0 40px; background-color: #ffffff;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr><td style="border-top: 1px solid #eeeeee; font-size: 0; line-height: 0;">&nbsp;</td></tr>
              </table>
            </td>
          </tr>

          <!-- Shipping Address + Contact -->
          <tr>
            <td class="email-padding" style="padding: 25px 40px 0 40px; background-color: #ffffff;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="two-col">
                <tr>
                  <td width="50%" style="vertical-align: top; padding-right: 15px;">
                    <p style="margin: 0 0 10px 0; color: #1a1a1a; font-size: 14px; font-weight: 800;">Shipping address</p>
                    <p style="margin: 0; color: ' . $footerMeta . '; font-size: 13px; line-height: 1.7;">
                      ' . $addressLines . '
                    </p>
                  </td>
                  <td width="50%" style="vertical-align: top; padding-left: 15px;">
                    <p style="margin: 0 0 10px 0; color: #1a1a1a; font-size: 14px; font-weight: 800;">Contact</p>
                    <p style="margin: 0; color: ' . $footerMeta . '; font-size: 13px; line-height: 1.7;">
                      ' . htmlspecialchars($contactName) . ($phone ? '<br>' . htmlspecialchars($phone) : '') . '
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Divider -->
          <tr>
            <td class="email-padding" style="padding: 25px 40px 0 40px; background-color: #ffffff;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr><td style="border-top: 1px solid #eeeeee; font-size: 0; line-height: 0;">&nbsp;</td></tr>
              </table>
            </td>
          </tr>

          <!-- Important Notice -->
          <tr>
            <td class="email-padding" style="padding: 25px 40px 0 40px; background-color: #ffffff;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #fff8f0; border-radius: 8px; border-left: 4px solid #e8900c;">
                <tr>
                  <td style="padding: 18px 20px;">
                    <p style="margin: 0 0 6px 0; color: #b45309; font-size: 14px; font-weight: 800;">Important Delivery Notice</p>
                    <p style="margin: 0; color: #777777; font-size: 13px; line-height: 1.6;">
                      ' . $deliveryNotice . '
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Closing Message -->
          <tr>
            <td class="email-padding" style="padding: 25px 40px 35px 40px; background-color: #ffffff;">
              <p style="margin: 0; color: #777777; font-size: 14px; line-height: 1.6;">
                We hope you enjoy your ' . $brandName . ' products! We sincerely apologize for any inconvenience caused by the potential delay.
              </p>
            </td>
          </tr>

          <!-- Any Questions Section -->
          <tr>
            <td class="email-padding" style="padding: 35px 40px; text-align: center; background-color: ' . $heroBg . ';">
              <p style="margin: 0 0 6px 0; color: #1a1a1a; font-size: 16px; font-weight: 800;">Any questions?</p>
              <p style="margin: 0; color: ' . $footerText . '; font-size: 13px; line-height: 1.6;">
                If you need any help whatsoever, simply reply to this email<br>and our support team will get back to you.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding: 30px 40px; text-align: center; background-color: ' . $footerBg . ';">
              <img src="' . $brandLogo . '" alt="' . $brandName . '" width="140" style="display: block; margin: 0 auto 4px auto; height: auto; max-width: 140px;">
              <p style="margin: 0 0 15px 0; color: ' . $footerText . '; font-size: 12px;">Product Support Team</p>
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr><td style="border-top: 1px solid ' . $footerDiv . '; font-size: 0; line-height: 0;">&nbsp;</td></tr>
              </table>
              <p style="margin: 12px 0 0 0; color: ' . $footerMeta . '; font-size: 11px; line-height: 1.5;">
                &copy; ' . date('Y') . ' ' . $brandName . '. All Rights Reserved.
              </p>
            </td>
          </tr>

        </table>
        <!--[if mso]></td></tr></table><![endif]-->

      </td>
    </tr>
  </table>
</body>
</html>';
}

// ================================================================
// REORDER REMINDER EMAIL
// ================================================================

/**
 * Build reorder reminder email HTML from order data
 */
function buildReorderMailFromOrder($order, $coupon = []) {
    $name      = htmlspecialchars($order['customer_name'] ?? '');
    $email     = htmlspecialchars($order['customer_email'] ?? '');
    $product   = htmlspecialchars($order['product_names'] ?? 'MetaTrim BHB');
    $orderId   = htmlspecialchars($order['order_id'] ?? '');
    $orderDate = '';
    if (!empty($order['date_created'])) {
        $ts = strtotime($order['date_created']);
        if ($ts) $orderDate = date('F jS, Y', $ts);
    }

    // Parse bottle count
    $bottles = 1;
    if (preg_match('/(\d+)\s*\+\s*(\d+)/', $product, $m)) {
        $bottles = intval($m[1]) + intval($m[2]);
    } elseif (preg_match('/(\d+)\s*Bottle/i', $product, $m)) {
        $bottles = intval($m[1]);
    }

    $bottleWord = $bottles > 1 ? $bottles . ' Bottles' : '1 Bottle';

    return buildReorderEmailHtml($name, $orderId, $orderDate, $bottleWord, $product, $coupon);
}

/**
 * Send reorder reminder email to multiple recipients
 */
function sendReorderMail($order, array $recipients = [], $coupon = [], $customSubject = '') {
    if (empty($order)) {
        return ['success' => false, 'error' => 'No order provided'];
    }

    if (empty($recipients)) {
        $recipients = [$order['customer_email']];
    }
    $recipients = array_filter(array_unique($recipients));
    if (empty($recipients)) {
        return ['success' => false, 'error' => 'No valid recipients'];
    }

    $html = buildReorderMailFromOrder($order, $coupon);

    require_once __DIR__ . '/lib/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/lib/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/lib/PHPMailer/src/SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        foreach ($recipients as $addr) {
            $mail->addAddress(trim($addr));
        }

        $mail->isHTML(true);
        $mail->Subject = $customSubject ?: 'Your MetaTrim BHB Supply Update';
        $mail->Body    = $html;
        applyMailHeaders($mail, $html);

        $mail->send();
        return ['success' => true, 'sent_to' => $recipients];
    } catch (\Exception $e) {
        return ['success' => false, 'error' => 'Mail error: ' . $mail->ErrorInfo];
    }
}

function buildReorderEmailHtml($customerName, $orderId, $orderDate, $bottleWord, $productName, $coupon = []) {
    $couponCode   = !empty($coupon['code']) ? htmlspecialchars(strtoupper(trim($coupon['code']))) : '';
    $couponType   = ($coupon['type'] ?? '') === 'fixed' ? 'fixed' : 'percentage';
    $couponAmount = !empty($coupon['amount']) ? floatval($coupon['amount']) : 0;

    $couponHtml = '';
    if ($couponCode && $couponAmount > 0) {
        $discountLabel = $couponType === 'fixed'
            ? '$' . number_format($couponAmount, 2) . ' OFF'
            : intval($couponAmount) . '% OFF';

        $couponHtml = '
          <!-- Exclusive Coupon -->
          <tr>
            <td class="email-padding" style="padding: 0 40px 25px 40px; background-color: #fdf8f3;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #ecfdf5; border-radius: 12px; border: 2px dashed #16a34a;">
                <tr>
                  <td style="padding: 28px; text-align: center;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin-bottom: 14px;">
                      <tr>
                        <td style="width: 50px; height: 50px; background-color: #16a34a; border-radius: 50%; text-align: center; vertical-align: middle;">
                          <span style="color: #ffffff; font-size: 24px; font-family: Arial, sans-serif;">&#9733;</span>
                        </td>
                      </tr>
                    </table>
                    <p style="margin: 0 0 6px 0; color: #15803d; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Exclusive Reorder Discount</p>
                    <p style="margin: 0 0 6px 0; color: #14532d; font-size: 30px; font-weight: 900; letter-spacing: 3px; font-family: monospace;">' . $couponCode . '</p>
                    <p style="margin: 0 0 14px 0;">
                      <span style="display: inline-block; background-color: #16a34a; color: #ffffff; font-size: 14px; font-weight: 800; padding: 6px 18px; border-radius: 20px;">' . $discountLabel . '</span>
                    </p>
                    <p style="margin: 0; color: #166534; font-size: 13px; line-height: 1.5;">
                      Use this code at checkout on your next order to get <strong>' . $discountLabel . '</strong> your purchase!
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>';
    }

    return '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="color-scheme" content="light only">
  <meta name="supported-color-schemes" content="light only">
  <!--[if mso]>
  <style>body,table,td,h1,h2,h3,p,span{font-family:Arial,Helvetica,sans-serif!important;}</style>
  <![endif]-->
  <style>
    :root { color-scheme: light only; }
    [data-ogsc] .dark-bg { background-color: #2d2d2d !important; }
    [data-ogsb] .dark-bg { background-color: #2d2d2d !important; }
    @media only screen and (max-width: 620px) {
      .email-wrapper { width: 100% !important; }
      .email-padding { padding-left: 24px !important; padding-right: 24px !important; }
      .hero-title { font-size: 24px !important; }
    }
  </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f0eb; font-family: Arial, Helvetica, sans-serif; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f5f0eb;">
    <tr>
      <td align="center" style="padding: 0;">

        <!--[if mso]><table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" align="center"><tr><td><![endif]-->
        <table role="presentation" class="email-wrapper" cellspacing="0" cellpadding="0" border="0" style="width: 100%; max-width: 600px;">

          <!-- Top Nav Bar -->
          <tr>
            <td class="email-padding dark-bg" style="padding: 16px 40px; background-color: #2d2d2d;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td style="vertical-align: middle;">
                    <img src="https://metatrim.trustednutraproduct.com/v2/lib/img/logo.png" alt="MetaTrim BHB" width="120" style="display: block; height: auto; max-width: 120px;">
                  </td>
                  <td align="right" style="vertical-align: middle;">
                    <span style="color: #999999; font-size: 12px;">Reorder Reminder</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Hero Section -->
          <tr>
            <td class="email-padding" style="padding: 50px 40px 30px 40px; text-align: center; background-color: #fdf8f3;">
              <h1 class="hero-title" style="margin: 0 0 12px 0; color: #1a1a1a; font-size: 28px; font-weight: 900; line-height: 1.3;">Time to restock your<br>MetaTrim BHB!</h1>
              <p style="margin: 0; color: #777777; font-size: 14px; line-height: 1.6;">
                Hi ' . htmlspecialchars($customerName) . ', your supply should be running low. Don\'t miss a day!
              </p>
            </td>
          </tr>

          <!-- Order Recap -->
          <tr>
            <td class="email-padding" style="padding: 25px 40px; background-color: #fdf8f3;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #ffffff; border-radius: 8px; border: 1px solid #eeeeee;">
                <tr>
                  <td style="padding: 22px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                      <tr>
                        <td style="padding-bottom: 12px;">
                          <p style="margin: 0; color: #aaaaaa; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Your Previous Order</p>
                        </td>
                      </tr>
                      <tr>
                        <td style="padding-bottom: 8px;">
                          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                              <td style="width: 70px; vertical-align: middle;">
                                <img src="https://metatrim.trustednutraproduct.com/v2/lib/img/prod1.png" alt="MetaTrim BHB" width="60" style="display: block; height: auto; max-width: 60px;">
                              </td>
                              <td style="vertical-align: middle; padding-left: 14px;">
                                <p style="margin: 0 0 4px 0; color: #1a1a1a; font-size: 15px; font-weight: 700;">MetaTrim BHB &mdash; ' . htmlspecialchars($bottleWord) . '</p>
                                <p style="margin: 0 0 2px 0; color: #888888; font-size: 13px;">Ordered: <strong style="color: #555555;">' . htmlspecialchars($orderDate) . '</strong></p>
                                <p style="margin: 0; color: #aaaaaa; font-size: 12px;">Order ID: <span style="color: #2E7D32; font-weight: 700;">' . htmlspecialchars($orderId) . '</span></p>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Main Message -->
          <tr>
            <td class="email-padding" style="padding: 10px 40px 25px 40px; background-color: #fdf8f3;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #fff8f0; border-radius: 8px; border-left: 4px solid #e8900c;">
                <tr>
                  <td style="padding: 20px;">
                    <p style="margin: 0 0 8px 0; color: #b45309; font-size: 15px; font-weight: 800;">Your supply should be finishing!</p>
                    <p style="margin: 0; color: #666666; font-size: 13px; line-height: 1.7;">
                      Your order of <strong style="color: #1a1a1a;">' . htmlspecialchars($bottleWord) . '</strong> placed on <strong style="color: #1a1a1a;">' . htmlspecialchars($orderDate) . '</strong> should have been completed by now. If you\'ve been following the recommended dosage, your bottles should be finished.
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          ' . $couponHtml . '

          <!-- Recommendation -->
          <tr>
            <td class="email-padding" style="padding: 0 40px 30px 40px; background-color: #fdf8f3;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #E8F5E9; border-radius: 8px; border: 2px solid #4CAF50;">
                <tr>
                  <td style="padding: 25px; text-align: center;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                      <tr>
                        <td style="width: 50px; height: 50px; background-color: #4CAF50; border-radius: 50%; text-align: center; vertical-align: middle;">
                          <span style="color: white; font-size: 22px; font-family: Arial, sans-serif;">&#9733;</span>
                        </td>
                      </tr>
                    </table>
                    <h4 style="margin: 15px 0 10px 0; color: #2E7D32; font-size: 17px; font-weight: 800;">For Best Results: 4-6 Months</h4>
                    <p style="margin: 0; color: #555555; font-size: 14px; line-height: 1.6;">
                      For optimal results, MetaTrim BHB should be used consistently for <strong>4 to 6 months</strong>. Don\'t stop now &mdash; keep the momentum going!
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- CTA Button -->
          <tr>
            <td class="email-padding" style="padding: 0 40px 40px 40px; text-align: center; background-color: #fdf8f3;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                <tr>
                  <td style="background-color: #2E7D32; border-radius: 8px;">
                    <a href="https://metatrim.trustednutraproduct.com/v2/short/go/#buynow" target="_blank" style="display: inline-block; padding: 16px 40px; color: #ffffff; font-size: 16px; font-weight: 800; text-decoration: none; font-family: Arial, sans-serif;">
                      ' . ($couponCode ? 'Redeem Your Discount Now &rarr;' : 'Order Now Before Stock Runs Out &rarr;') . '
                    </a>
                  </td>
                </tr>
              </table>
              <p style="margin: 14px 0 0 0; color: #999999; font-size: 12px;">' . ($couponCode ? 'Apply code <strong>' . $couponCode . '</strong> at checkout!' : 'Limited stock available &mdash; order now to avoid waiting!') . '</p>
            </td>
          </tr>

          <!-- Divider -->
          <tr>
            <td class="email-padding" style="padding: 0 40px; background-color: #ffffff;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr><td style="border-top: 1px solid #eeeeee; font-size: 0; line-height: 0;">&nbsp;</td></tr>
              </table>
            </td>
          </tr>

          <!-- Any Questions -->
          <tr>
            <td class="email-padding" style="padding: 35px 40px; text-align: center; background-color: #ffffff;">
              <p style="margin: 0 0 6px 0; color: #1a1a1a; font-size: 16px; font-weight: 800;">Any questions?</p>
              <p style="margin: 0; color: #888888; font-size: 13px; line-height: 1.6;">
                If you need any help whatsoever, simply reply to this email<br>and our support team will get back to you.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td class="dark-bg" style="padding: 30px 40px; text-align: center; background-color: #2d2d2d;">
              <img src="https://metatrim.trustednutraproduct.com/v2/lib/img/logo.png" alt="MetaTrim BHB" width="140" style="display: block; margin: 0 auto 4px auto; height: auto; max-width: 140px;">
              <p style="margin: 0 0 15px 0; color: #888888; font-size: 12px;">Product Support Team</p>
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr><td style="border-top: 1px solid #444444; font-size: 0; line-height: 0;">&nbsp;</td></tr>
              </table>
              <p style="margin: 12px 0 0 0; color: #666666; font-size: 11px; line-height: 1.5;">
                &copy; ' . date('Y') . ' MetaTrim BHB. All Rights Reserved.
              </p>
            </td>
          </tr>

        </table>
        <!--[if mso]></td></tr></table><![endif]-->

      </td>
    </tr>
  </table>
</body>
</html>';
}

// ================================================================
// DISPATCH STATUS EMAIL
// ================================================================

/**
 * Build dispatch status email HTML from order data
 * Uses same design as the delay email template
 */
function buildDispatchMailFromOrder($order, $dispatchType = 'other') {
    $name      = htmlspecialchars($order['customer_name'] ?? '');
    $orderId   = htmlspecialchars($order['order_id'] ?? '');
    $product   = $order['product_names'] ?? 'MetaTrim BHB';
    $orderDate = '';
    if (!empty($order['date_created'])) {
        $ts = strtotime($order['date_created']);
        if ($ts) $orderDate = date('F jS, Y \a\t g:i A', $ts);
    }

    $bottles = 1;
    if (preg_match('/(\d+)\s*\+\s*(\d+)/', $product, $m)) {
        $bottles = intval($m[1]) + intval($m[2]);
    } elseif (preg_match('/(\d+)\s*Bottle/i', $product, $m)) {
        $bottles = intval($m[1]);
    }

    $isUpsell = ($order['flag_upsell'] == 1);

    $address = htmlspecialchars($order['address'] ?? '');
    $city    = htmlspecialchars($order['city'] ?? '');
    $state   = htmlspecialchars($order['state'] ?? '');
    $country = htmlspecialchars($order['country'] ?? '');
    $zip     = htmlspecialchars($order['zip'] ?? '');
    $phone   = htmlspecialchars($order['customer_phone'] ?? '');

    $addressLines = $name;
    if ($address) $addressLines .= '<br>' . $address;
    if ($city || $state) $addressLines .= '<br>' . $city . ($city && $state ? ', ' : '') . $state;
    if ($country || $zip) $addressLines .= '<br>' . $country . ($zip ? ', ' . $zip : '');

    if ($dispatchType === 'same') {
        $heroTitle = 'Your order has been<br>dispatched!';
        $heroSub = 'Great news! Your MetaTrim BHB order has been shipped from our warehouse and is on its way to you.';
        $statusColor = '#2E7D32';
        $deliveryEstimate = 'Estimated delivery: <strong style="color: #555555;">3-5 business days</strong>. Your order has been dispatched and is being processed by our fulfillment center.';
        $deliveryNotice = 'Your order has been dispatched and is on its way. You can expect delivery within <strong style="color: #555555;">3-5 business days</strong>.';
        $navLabel = 'Dispatch Confirmation';
    } else {
        $heroTitle = 'Your order has been<br>fulfilled!';
        $heroSub = 'Great news! Your MetaTrim BHB order has been processed by an alternative fulfillment partner to ensure faster delivery.';
        $statusColor = '#1565C0';
        $deliveryEstimate = 'Estimated delivery: <strong style="color: #555555;">5-7 business days</strong>. Your order has been transferred to an alternative fulfillment partner for faster processing.';
        $deliveryNotice = 'Your order has been transferred to an alternative fulfillment partner for faster processing. You can expect delivery within <strong style="color: #555555;">5-7 business days</strong>.';
        $navLabel = 'Fulfillment Update';
    }

    $displayName = 'MetaTrim BHB Bottle' . ($bottles > 1 ? 's' : '');
    $imgUrl = 'https://metatrim.trustednutraproduct.com/v2/lib/img/prod1.png';

    // Build product row (same style as delay email)
    $upsellBadge = '';
    if ($isUpsell) {
        $upsellBadge = '
                        <td align="right" style="vertical-align: top;">
                          <span style="display: inline-block; background-color: #2E7D32; color: #ffffff; font-size: 10px; font-weight: 700; padding: 4px 10px; border-radius: 3px; text-transform: uppercase;">Upsell Discount</span>
                        </td>';
    }
    $nameCell = $isUpsell
        ? '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td><p style="margin: 0 0 4px 0; color: #1a1a1a; font-size: 15px; font-weight: 700;">' . $displayName . '</p></td>' . $upsellBadge . '</tr></table>'
        : '<p style="margin: 0 0 4px 0; color: #1a1a1a; font-size: 15px; font-weight: 700;">' . $displayName . '</p>';

    $productsHtml = '
          <tr>
            <td class="email-padding" style="padding: 25px 40px 0 40px; background-color: #ffffff;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border: 1px solid #eeeeee; border-radius: 8px;">
                <tr>
                  <td style="padding: 18px; width: 90px; background-color: #fafafa; text-align: center; vertical-align: middle;">
                    <img src="' . $imgUrl . '" alt="' . $displayName . '" width="75" style="display: block; margin: 0 auto; height: auto; max-width: 75px;">
                  </td>
                  <td style="padding: 18px 20px;">
                    ' . $nameCell . '
                    <p style="margin: 0 0 4px 0; color: #888888; font-size: 13px;">Quantity: ' . $bottles . '</p>
                    <p style="margin: 0; color: #aaaaaa; font-size: 12px;">Order ID: <span style="color: #2E7D32; font-weight: 700;">' . $orderId . '</span></p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>';

    // Tracker: step 1 (Confirmed) + step 2 (Shipped) = colored, step 3 (Delivered) = grey
    $step1Color = '#2E7D32';
    $step2Color = $statusColor;

    return '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="color-scheme" content="light only">
  <meta name="supported-color-schemes" content="light only">
  <!--[if mso]>
  <style>body,table,td,h1,h2,h3,p,span{font-family:Arial,Helvetica,sans-serif!important;}</style>
  <![endif]-->
  <style>
    :root { color-scheme: light only; }
    [data-ogsc] .dark-bg { background-color: #2d2d2d !important; }
    [data-ogsb] .dark-bg { background-color: #2d2d2d !important; }
    @media only screen and (max-width: 620px) {
      .email-wrapper { width: 100% !important; }
      .email-padding { padding-left: 24px !important; padding-right: 24px !important; }
      .hero-title { font-size: 26px !important; }
      .tracker-padding { padding-left: 30px !important; padding-right: 30px !important; }
      .two-col td { display: block !important; width: 100% !important; padding-left: 0 !important; padding-right: 0 !important; padding-bottom: 15px !important; }
    }
  </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f0eb; font-family: Arial, Helvetica, sans-serif; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f5f0eb;">
    <tr>
      <td align="center" style="padding: 0;">

        <!--[if mso]><table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" align="center"><tr><td><![endif]-->
        <table role="presentation" class="email-wrapper" cellspacing="0" cellpadding="0" border="0" style="width: 100%; max-width: 600px;">

          <!-- Top Nav Bar -->
          <tr>
            <td class="email-padding dark-bg" style="padding: 16px 40px; background-color: #2d2d2d;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td style="vertical-align: middle;">
                    <img src="https://metatrim.trustednutraproduct.com/v2/lib/img/logo.png" alt="MetaTrim BHB" width="120" style="display: block; height: auto; max-width: 120px;">
                  </td>
                  <td align="right" style="vertical-align: middle;">
                    <span style="color: #999999; font-size: 12px;">' . htmlspecialchars($navLabel) . '</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Hero Section -->
          <tr>
            <td class="email-padding" style="padding: 50px 40px 35px 40px; text-align: center; background-color: #fdf8f3;">
              <h1 class="hero-title" style="margin: 0 0 12px 0; color: #1a1a1a; font-size: 32px; font-weight: 900; line-height: 1.2;">' . $heroTitle . '</h1>
              <p style="margin: 0; color: #777777; font-size: 14px; line-height: 1.6;">
                ' . $heroSub . '
              </p>
            </td>
          </tr>

          <!-- Order Progress Tracker -->
          <tr>
            <td class="tracker-padding" style="padding: 15px 50px 25px 50px; background-color: #fdf8f3;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td align="center" style="vertical-align: middle; width: 40px;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                      <tr>
                        <td style="width: 40px; height: 40px; background-color: ' . $step1Color . '; border-radius: 50%; text-align: center; vertical-align: middle;">
                          <span style="color: #ffffff; font-size: 18px; font-family: Arial, sans-serif;">&#10003;</span>
                        </td>
                      </tr>
                    </table>
                  </td>
                  <td style="vertical-align: middle;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                      <tr><td style="border-bottom: 3px solid ' . $step2Color . '; font-size: 0; line-height: 0;">&nbsp;</td></tr>
                    </table>
                  </td>
                  <td align="center" style="vertical-align: middle; width: 40px;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                      <tr>
                        <td style="width: 40px; height: 40px; background-color: ' . $step2Color . '; border-radius: 50%; text-align: center; vertical-align: middle;">
                          <span style="color: #ffffff; font-size: 18px; font-family: Arial, sans-serif;">&#10003;</span>
                        </td>
                      </tr>
                    </table>
                  </td>
                  <td style="vertical-align: middle;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                      <tr><td style="border-bottom: 3px solid #e0e0e0; font-size: 0; line-height: 0;">&nbsp;</td></tr>
                    </table>
                  </td>
                  <td align="center" style="vertical-align: middle; width: 40px;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                      <tr>
                        <td style="width: 40px; height: 40px; background-color: #e8e8e8; border-radius: 50%; text-align: center; vertical-align: middle;">
                          <span style="color: #bbbbbb; font-size: 14px; font-family: Arial, sans-serif;">&#9679;</span>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td align="center" style="padding-top: 8px;">
                    <p style="margin: 0; color: ' . $step1Color . '; font-size: 11px; font-weight: 700; line-height: 1.3;">Order<br>Confirmed</p>
                  </td>
                  <td>&nbsp;</td>
                  <td align="center" style="padding-top: 8px;">
                    <p style="margin: 0; color: ' . $step2Color . '; font-size: 11px; font-weight: 700; line-height: 1.3;">Shipped</p>
                  </td>
                  <td>&nbsp;</td>
                  <td align="center" style="padding-top: 8px;">
                    <p style="margin: 0; color: #999999; font-size: 11px; font-weight: 600; line-height: 1.3;">Expected<br>Delivered</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Delivery Estimate -->
          <tr>
            <td class="email-padding" style="padding: 0 40px 15px 40px; text-align: center; background-color: #fdf8f3;">
              <p style="margin: 0; color: #888888; font-size: 13px; line-height: 1.5;">
                ' . $deliveryEstimate . '
              </p>
            </td>
          </tr>

          <!-- Status Highlight Box -->
          <tr>
            <td class="email-padding" style="padding: 10px 40px 40px 40px; background-color: #fdf8f3;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #E8F5E9; border-radius: 8px; border: 2px solid #4CAF50;">
                <tr>
                  <td style="padding: 25px; text-align: center;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                      <tr>
                        <td style="width: 50px; height: 50px; background-color: ' . $statusColor . '; border-radius: 50%; text-align: center; vertical-align: middle;">
                          <span style="color: white; font-size: 22px; font-family: Arial, sans-serif;">&#10004;</span>
                        </td>
                      </tr>
                    </table>
                    <h4 style="margin: 15px 0 10px 0; color: #2E7D32; font-size: 18px; font-weight: 800;">Your Order Is On Its Way!</h4>
                    <p style="margin: 0; color: #555555; font-size: 14px; line-height: 1.6;">
                      Your MetaTrim BHB order has been processed and shipped. Sit tight &mdash; it\'ll be at your doorstep soon!
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Divider -->
          <tr>
            <td class="email-padding" style="padding: 0 40px; background-color: #ffffff;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr><td style="border-top: 1px solid #eeeeee; font-size: 0; line-height: 0;">&nbsp;</td></tr>
              </table>
            </td>
          </tr>

          <!-- Order Details Heading -->
          <tr>
            <td class="email-padding" style="padding: 35px 40px 5px 40px; text-align: center; background-color: #ffffff;">
              <h2 style="margin: 0 0 8px 0; color: #1a1a1a; font-size: 24px; font-weight: 900;">Order details</h2>
              <p style="margin: 0; color: #2E7D32; font-size: 14px; font-weight: 700;">' . htmlspecialchars($orderDate) . '</p>
            </td>
          </tr>

          <!-- Products -->
          ' . $productsHtml . '

          <!-- Divider -->
          <tr>
            <td class="email-padding" style="padding: 25px 40px 0 40px; background-color: #ffffff;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr><td style="border-top: 1px solid #eeeeee; font-size: 0; line-height: 0;">&nbsp;</td></tr>
              </table>
            </td>
          </tr>

          <!-- Shipping Address + Contact -->
          <tr>
            <td class="email-padding" style="padding: 25px 40px 0 40px; background-color: #ffffff;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="two-col">
                <tr>
                  <td width="50%" style="vertical-align: top; padding-right: 15px;">
                    <p style="margin: 0 0 10px 0; color: #1a1a1a; font-size: 14px; font-weight: 800;">Shipping address</p>
                    <p style="margin: 0; color: #666666; font-size: 13px; line-height: 1.7;">
                      ' . $addressLines . '
                    </p>
                  </td>
                  <td width="50%" style="vertical-align: top; padding-left: 15px;">
                    <p style="margin: 0 0 10px 0; color: #1a1a1a; font-size: 14px; font-weight: 800;">Contact</p>
                    <p style="margin: 0; color: #666666; font-size: 13px; line-height: 1.7;">
                      ' . $name . ($phone ? '<br>' . $phone : '') . '
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Divider -->
          <tr>
            <td class="email-padding" style="padding: 25px 40px 0 40px; background-color: #ffffff;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr><td style="border-top: 1px solid #eeeeee; font-size: 0; line-height: 0;">&nbsp;</td></tr>
              </table>
            </td>
          </tr>

          <!-- Delivery Notice -->
          <tr>
            <td class="email-padding" style="padding: 25px 40px 0 40px; background-color: #ffffff;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #E8F5E9; border-radius: 8px; border-left: 4px solid ' . $statusColor . ';">
                <tr>
                  <td style="padding: 18px 20px;">
                    <p style="margin: 0 0 6px 0; color: ' . $statusColor . '; font-size: 14px; font-weight: 800;">Delivery Update</p>
                    <p style="margin: 0; color: #555555; font-size: 13px; line-height: 1.6;">' . $deliveryNotice . '</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Closing Message -->
          <tr>
            <td class="email-padding" style="padding: 25px 40px 35px 40px; background-color: #ffffff;">
              <p style="margin: 0; color: #777777; font-size: 14px; line-height: 1.6;">
                Thank you for your patience, ' . $name . '! We appreciate your understanding and hope you enjoy your MetaTrim BHB products.
              </p>
            </td>
          </tr>

          <!-- Any Questions Section -->
          <tr>
            <td class="email-padding" style="padding: 35px 40px; text-align: center; background-color: #fdf8f3;">
              <p style="margin: 0 0 6px 0; color: #1a1a1a; font-size: 16px; font-weight: 800;">Any questions?</p>
              <p style="margin: 0; color: #888888; font-size: 13px; line-height: 1.6;">
                If you need any help whatsoever, simply reply to this email<br>and our support team will get back to you.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td class="dark-bg" style="padding: 30px 40px; text-align: center; background-color: #2d2d2d;">
              <img src="https://metatrim.trustednutraproduct.com/v2/lib/img/logo.png" alt="MetaTrim BHB" width="140" style="display: block; margin: 0 auto 4px auto; height: auto; max-width: 140px;">
              <p style="margin: 0 0 15px 0; color: #888888; font-size: 12px;">Product Support Team</p>
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr><td style="border-top: 1px solid #444444; font-size: 0; line-height: 0;">&nbsp;</td></tr>
              </table>
              <p style="margin: 12px 0 0 0; color: #666666; font-size: 11px; line-height: 1.5;">
                &copy; ' . date('Y') . ' MetaTrim BHB. All Rights Reserved.
              </p>
            </td>
          </tr>

        </table>
        <!--[if mso]></td></tr></table><![endif]-->

      </td>
    </tr>
  </table>
</body>
</html>';
}

/**
 * Send dispatch status email
 */
function sendDispatchMail($order, array $recipients = [], $dispatchType = 'other') {
    if (empty($order)) {
        return ['success' => false, 'error' => 'No order provided'];
    }

    if (empty($recipients)) {
        $recipients = [$order['customer_email']];
    }
    $recipients = array_filter(array_unique($recipients));
    if (empty($recipients)) {
        return ['success' => false, 'error' => 'No valid recipients'];
    }

    $html = buildDispatchMailFromOrder($order, $dispatchType);

    require_once __DIR__ . '/lib/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/lib/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/lib/PHPMailer/src/SMTP.php';

    $subject = $dispatchType === 'same'
        ? 'Your MetaTrim BHB Order Has Been Dispatched'
        : 'Your MetaTrim BHB Order Has Been Fulfilled';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        foreach ($recipients as $addr) {
            $mail->addAddress(trim($addr));
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        applyMailHeaders($mail, $html);

        $mail->send();
        return ['success' => true, 'sent_to' => $recipients];
    } catch (\Exception $e) {
        return ['success' => false, 'error' => 'Mail error: ' . $mail->ErrorInfo];
    }
}

// ================================================================
// COMPENSATION EMAIL
// ================================================================

/**
 * Build compensation email HTML from order data
 */
function buildCompensationMailFromOrder($order, $couponCode = '', $usageType = 'one-time', $limitedCount = '', $productName = 'MetaTrim BHB', $productLink = '') {
    $name      = htmlspecialchars($order['customer_name'] ?? '');
    $orderId   = htmlspecialchars($order['order_id'] ?? '');

    $couponDisplay = !empty($couponCode) ? htmlspecialchars(strtoupper($couponCode)) : 'YOUR-COUPON';
    $productDisplay = htmlspecialchars($productName ?: 'MetaTrim BHB');

    if ($usageType === 'lifetime') {
        $usageDesc = 'This coupon can be used <strong>unlimited times</strong> on future orders.';
    } elseif ($usageType === 'limited' && !empty($limitedCount)) {
        $usageDesc = 'This coupon can be used <strong>' . intval($limitedCount) . ' time' . (intval($limitedCount) > 1 ? 's' : '') . '</strong>.';
    } else {
        $usageDesc = 'This is a <strong>one-time use</strong> coupon.';
    }

    $ctaHtml = '';
    if (!empty($productLink)) {
        $ctaHtml = '
          <tr>
            <td class="email-padding" style="padding: 10px 40px 25px 40px; text-align: center; background-color: #ffffff;">
              <a href="' . htmlspecialchars($productLink) . '" style="display: inline-block; background-color: #2E7D32; color: #ffffff; font-size: 16px; font-weight: 700; padding: 14px 40px; border-radius: 8px; text-decoration: none;">Shop ' . $productDisplay . ' Now</a>
            </td>
          </tr>';
    }

    return '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    :root { color-scheme: light only; }
    @media only screen and (max-width: 620px) {
      .email-wrapper { width: 100% !important; }
      .email-padding { padding-left: 24px !important; padding-right: 24px !important; }
      .hero-title { font-size: 26px !important; }
    }
  </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f0eb; font-family: Arial, Helvetica, sans-serif;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f5f0eb;">
    <tr>
      <td align="center" style="padding: 0;">
        <table role="presentation" class="email-wrapper" cellspacing="0" cellpadding="0" border="0" style="width: 100%; max-width: 600px;">
          <!-- Top Nav -->
          <tr>
            <td class="email-padding" style="padding: 16px 40px; background-color: #2d2d2d;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td><img src="https://metatrim.trustednutraproduct.com/v2/lib/img/logo.png" alt="MetaTrim BHB" width="120" style="display: block; height: auto;"></td>
                  <td align="right"><span style="color: #999999; font-size: 12px;">Special Offer</span></td>
                </tr>
              </table>
            </td>
          </tr>
          <!-- Hero -->
          <tr>
            <td class="email-padding" style="padding: 50px 40px 30px 40px; text-align: center; background-color: #f0fdf4;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin-bottom: 20px;">
                <tr><td style="width: 60px; height: 60px; background-color: #16a34a; border-radius: 50%; text-align: center; vertical-align: middle;"><img src="https://img.icons8.com/ios-filled/30/ffffff/gift.png" alt="Gift" width="30" height="30" style="display: inline-block; vertical-align: middle;"></td></tr>
              </table>
              <h1 class="hero-title" style="margin: 0 0 12px 0; color: #1a1a1a; font-size: 28px; font-weight: 900; line-height: 1.3;">We have a special<br>offer for you!</h1>
              <p style="margin: 0; color: #777777; font-size: 14px; line-height: 1.6;">
                Hi <strong>' . $name . '</strong>, as a token of our appreciation for your patience with your recent order, we\'d like to offer you an exclusive discount.
              </p>
            </td>
          </tr>
          <!-- Coupon Code Box -->
          <tr>
            <td class="email-padding" style="padding: 0 40px 30px 40px; background-color: #f0fdf4;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #ecfdf5; border-radius: 12px; border: 2px dashed #16a34a;">
                <tr>
                  <td style="padding: 30px; text-align: center;">
                    <p style="margin: 0 0 8px 0; color: #15803d; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Your Exclusive Coupon Code</p>
                    <p style="margin: 0 0 12px 0; color: #14532d; font-size: 32px; font-weight: 900; letter-spacing: 3px; font-family: monospace;">' . $couponDisplay . '</p>
                    <p style="margin: 0; color: #166534; font-size: 13px; line-height: 1.5;">' . $usageDesc . '</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <!-- Product Info -->
          <tr>
            <td class="email-padding" style="padding: 30px 40px 15px 40px; text-align: center; background-color: #ffffff;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin-bottom: 8px;">
                <tr><td style="width: 36px; height: 36px; background-color: #dcfce7; border-radius: 50%; text-align: center; vertical-align: middle;"><img src="https://img.icons8.com/ios-filled/20/16a34a/shopping-bag.png" alt="Shop" width="20" height="20" style="display: inline-block; vertical-align: middle;"></td></tr>
              </table>
              <p style="margin: 0 0 6px 0; color: #1a1a1a; font-size: 16px; font-weight: 800;">Use it on: ' . $productDisplay . '</p>
              <p style="margin: 0; color: #888888; font-size: 13px;">Apply the coupon at checkout to get your discount.</p>
            </td>
          </tr>
          ' . $ctaHtml . '
          <!-- Order Reference -->
          <tr>
            <td class="email-padding" style="padding: 15px 40px 25px 40px; background-color: #ffffff;">
              <table role="presentation" width="100%" style="background-color: #f0fdf4; border-radius: 8px; border: 1px solid #bbf7d0;">
                <tr>
                  <td style="padding: 14px 18px;">
                    <p style="margin: 0; color: #64748b; font-size: 12px;"><img src="https://img.icons8.com/ios-filled/14/64748b/info.png" alt="Info" width="14" height="14" style="vertical-align: middle; margin-right: 4px;">This offer is related to your order <strong style="color: #1e293b;">#' . $orderId . '</strong>. We apologize for any inconvenience and hope this makes up for it!</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <!-- Thank You -->
          <tr>
            <td class="email-padding" style="padding: 10px 40px 35px 40px; background-color: #ffffff;">
              <p style="margin: 0; color: #777777; font-size: 14px; line-height: 1.6;">Thank you for being a valued customer, <strong>' . $name . '</strong>! We truly appreciate your patience and loyalty.</p>
            </td>
          </tr>
          <!-- Questions -->
          <tr>
            <td class="email-padding" style="padding: 35px 40px; text-align: center; background-color: #f0fdf4;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin-bottom: 8px;">
                <tr><td style="width: 36px; height: 36px; background-color: #dcfce7; border-radius: 50%; text-align: center; vertical-align: middle;"><img src="https://img.icons8.com/ios-filled/20/16a34a/help.png" alt="Help" width="20" height="20" style="display: inline-block; vertical-align: middle;"></td></tr>
              </table>
              <p style="margin: 0 0 6px 0; color: #1a1a1a; font-size: 16px; font-weight: 800;">Any questions?</p>
              <p style="margin: 0; color: #888888; font-size: 13px; line-height: 1.6;">If you need any help whatsoever, simply reply to this email<br>and our support team will get back to you.</p>
            </td>
          </tr>
          <!-- Footer -->
          <tr>
            <td style="padding: 30px 40px; text-align: center; background-color: #2d2d2d;">
              <img src="https://metatrim.trustednutraproduct.com/v2/lib/img/logo.png" alt="MetaTrim BHB" width="140" style="display: block; margin: 0 auto 4px auto; height: auto;">
              <p style="margin: 0 0 15px 0; color: #888888; font-size: 12px;">Product Support Team</p>
              <table role="presentation" width="100%"><tr><td style="border-top: 1px solid #444444;">&nbsp;</td></tr></table>
              <p style="margin: 12px 0 0 0; color: #666666; font-size: 11px;">&copy; ' . date('Y') . ' MetaTrim BHB. All Rights Reserved.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
}

/**
 * Send compensation email
 */
function sendCompensationMail($order, array $recipients, $couponCode, $usageType = 'one-time', $limitedCount = '', $productName = 'MetaTrim BHB', $productLink = '') {
    if (empty($order)) {
        return ['success' => false, 'error' => 'No order provided'];
    }

    $recipients = array_filter(array_unique($recipients));
    if (empty($recipients)) {
        return ['success' => false, 'error' => 'No valid recipients'];
    }

    $html = buildCompensationMailFromOrder($order, $couponCode, $usageType, $limitedCount, $productName, $productLink);

    require_once __DIR__ . '/lib/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/lib/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/lib/PHPMailer/src/SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        foreach ($recipients as $addr) {
            $mail->addAddress(trim($addr));
        }

        $mail->isHTML(true);
        $mail->Subject = 'Your MetaTrim BHB Coupon Code - Order #' . htmlspecialchars($order['order_id'] ?? '');
        $mail->Body    = $html;
        applyMailHeaders($mail, $html);

        $mail->send();
        return ['success' => true, 'sent_to' => $recipients];
    } catch (\Exception $e) {
        return ['success' => false, 'error' => 'Mail error: ' . $mail->ErrorInfo];
    }
}
