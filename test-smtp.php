<?php
/**
 * SMTP diagnostic — run via browser to confirm what's wrong with SendGrid auth.
 *
 *   https://shaver.trustednutraproduct.com/test-smtp.php
 *
 * Does NOT send any actual email — just opens the SMTP connection and tries
 * to authenticate so PHPMailer's debug output tells you exactly where it
 * breaks. Delete this file after diagnosis.
 */

require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=================================\n";
echo " SMTP Diagnostic — SendGrid auth\n";
echo "=================================\n\n";

echo "Host: " . SMTP_HOST . "\n";
echo "Port: " . SMTP_PORT . "\n";
echo "User: " . SMTP_USER . "  (must be literal string 'apikey' for SendGrid)\n\n";

$keyFile = __DIR__ . '/.smtp_key';
echo ".smtp_key path:     $keyFile\n";
echo ".smtp_key exists:   " . (file_exists($keyFile) ? 'YES' : 'NO  ← THIS IS YOUR PROBLEM') . "\n";
echo ".smtp_key readable: " . (is_readable($keyFile) ? 'YES' : 'NO  ← chmod 644 it') . "\n";

if (file_exists($keyFile)) {
    $key = trim(file_get_contents($keyFile));
    echo ".smtp_key length:   " . strlen($key) . " chars (expected ~69)\n";
    echo ".smtp_key prefix:   " . substr($key, 0, 3) . "...\n";
    echo "Format OK:          " . (strpos($key, 'SG.') === 0 ? 'YES' : 'NO  ← key must start with SG.') . "\n";
    echo "Has whitespace:     " . (preg_match('/\s/', $key) ? 'YES  ← strip newlines/spaces' : 'NO') . "\n";
}

echo "\n--- Live SMTP handshake ---\n\n";

require_once __DIR__ . '/lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/src/SMTP.php';

$mail = new \PHPMailer\PHPMailer\PHPMailer(true);
try {
    $mail->SMTPDebug   = 3;  // verbose
    $mail->Debugoutput = function ($str, $level) { echo trim($str) . "\n"; };
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->Timeout    = 8;

    if ($mail->smtpConnect()) {
        echo "\n\n✓✓✓  SMTP connection + AUTH succeeded.\n";
        echo "If orders.html still fails, the issue is in the email content / recipient — not auth.\n";
        $mail->smtpClose();
    } else {
        echo "\n\n✗✗✗  smtpConnect returned false. Check the SendGrid response above.\n";
    }
} catch (Throwable $e) {
    echo "\n\n✗✗✗  EXCEPTION: " . $e->getMessage() . "\n";
    echo "\nMost common causes:\n";
    echo "  - API key revoked: log in to sendgrid.com → Settings → API Keys, create a new one with 'Mail Send' permission.\n";
    echo "  - Free tier daily limit (100/day) exhausted: check sendgrid.com → Activity.\n";
    echo "  - Sender 'contact@trustednutraproduct.com' not verified under that SendGrid account.\n";
    echo "  - Hostinger blocking outbound port 587 (rare; would also break previous sends).\n";
}

echo "\n\nDelete this file after diagnosis.\n";
