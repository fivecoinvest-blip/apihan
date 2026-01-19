<?php
/**
 * Payment Type Verification
 * Shows exactly what payment type is sent to WPay
 */

require_once 'wpay_config.php';
require_once 'wpay_helper.php';

$payType = $_GET['type'] ?? 'GCASH';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Payment Type Test</title>
    <style>
        body { font-family: monospace; background: #f0f0f0; padding: 20px; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>
<h1>🔍 Payment Type Verification</h1>

<div class='box'>
<h2>Testing Payment Type: <span class='success'>{$payType}</span></h2>

<h3>Request Parameters:</h3>
<pre>";

$wpay = new WPayHelper();
$params = [
    'mchId' => WPAY_MCH_ID,
    'currency' => 'PHP',
    'out_trade_no' => $payType . '_' . date('YmdHis'),
    'pay_type' => $payType,
    'money' => 100,
    'notify_url' => WPAY_NOTIFY_URL,
    'returnUrl' => WPAY_RETURN_URL,
];
$params['sign'] = $wpay->generateSign($params);

echo json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "</pre>

<h3>WPay API Response:</h3>
<pre>";

$response = $wpay->sendRequest('/v1/Collect', $params);

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if ($response['code'] == 0) {
    echo "</pre>

<h3><span class='success'>✓ SUCCESS</span></h3>
<p><strong>Payment URL:</strong></p>
<p><a href='" . htmlspecialchars($response['data']['url']) . "' target='_blank'>" . htmlspecialchars($response['data']['url']) . "</a></p>
<p><em>Click the link above to see what payment interface is shown for {$payType}</em></p>";
} else {
    echo "</pre>

<h3><span class='error'>✗ FAILED</span></h3>";
}

echo "</div>

<div class='box'>
<h3>Quick Test Links:</h3>
<ul>
    <li><a href='?type=GCASH'>Test GCASH</a></li>
    <li><a href='?type=MAYA'>Test MAYA</a></li>
    <li><a href='?type=QR'>Test QR</a></li>
</ul>
</div>
</body>
</html>";
?>
