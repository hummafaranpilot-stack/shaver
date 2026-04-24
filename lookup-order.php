<?php
require 'config.php';
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

header('Content-Type: text/html; charset=utf-8');

// Show last 3 CB orders
$stmt = $pdo->query("
    SELECT order_id, customer_name, customer_email, customer_phone, address, city, state, country, zip, ip_address, order_details
    FROM orders
    ORDER BY date_created DESC LIMIT 3
");
$rows = $stmt->fetchAll();

echo "<h2>Last 3 Orders — After Shipping2 Enrichment</h2>";
foreach ($rows as $r) {
    echo "<h3 style='margin-top:20px;color:#2563eb;'>Order: " . htmlspecialchars($r['order_id']) . " — " . htmlspecialchars($r['customer_name']) . "</h3>";
    echo "<table border='1' cellpadding='6' cellspacing='0' style='font-family:monospace;font-size:13px;border-collapse:collapse;margin-bottom:10px;'>";
    $fields = ['address','city','state','country','zip','customer_phone','ip_address'];
    foreach ($fields as $f) {
        $val = $r[$f] ?? '';
        $style = empty($val) ? '<em style="color:red;">EMPTY</em>' : '<strong style="color:green;">' . htmlspecialchars($val) . '</strong>';
        echo "<tr><td style='background:#f5f5f5;font-weight:bold;'>$f</td><td>$style</td></tr>";
    }
    echo "</table>";
}
