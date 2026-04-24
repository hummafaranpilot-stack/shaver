<?php
require 'config.php';
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$dStmt = $pdo->query("SELECT id, cb_vendor FROM domains WHERE platform = 'clickbank' AND status != 'deleted' LIMIT 1");
$domain = $dStmt->fetch();
if (!$domain) { echo "No CB domain found"; exit; }

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Raw Shipping2 API Response</h2>";

$startDate = date('Y-m-d', strtotime('-30 days'));
$endDate = date('Y-m-d');
$url = 'https://api.clickbank.com/rest/1.3/shipping2/list?startDate=' . urlencode($startDate) . '&endDate=' . urlencode($endDate);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: ' . CB_API_KEY
    ],
    CURLOPT_SSL_VERIFYPEER => true
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $httpCode</p>";

if ($response) {
    $response = preg_replace('/^\xEF\xBB\xBF/', '', trim($response));
    $decoded = json_decode($response, true);
    if ($decoded === null) {
        $xmlStart = strpos($response, '<');
        $xmlContent = $xmlStart !== false ? substr($response, $xmlStart) : $response;
        $xml = @simplexml_load_string($xmlContent);
        if ($xml) $decoded = json_decode(json_encode($xml), true);
    }

    // Dump the ENTIRE raw response structure
    echo "<h3>Full API Response Structure:</h3>";
    echo "<pre style='background:#f8f8f8;padding:12px;border:1px solid #ddd;max-width:1000px;overflow:auto;font-size:12px;'>" . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
} else {
    echo "<p style='color:red;'>Empty response</p>";
}
