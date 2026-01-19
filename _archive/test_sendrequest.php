<?php
/**
 * Direct test without using createPayIn - just test sendRequest
 */
require_once 'wpay_helper.php';

echo "=== Testing WPayHelper sendRequest directly ===\n\n";

$wpay = new WPayHelper();

// Create simple test params
$params = [
    'mchId' => '5047',
    'currency' => 'PHP',
    'out_trade_no' => 'DIRECTTEST' . time(),
    'pay_type' => 'GCASH',
    'money' => 100,
    'notify_url' => 'https://paldo88.site/wpay_callback.php',
    'returnUrl' => 'https://paldo88.site/payment_status.php'
];

// Add signature manually
ksort($params);
$signStr = '';
foreach ($params as $key => $val) {
    if ($val !== '' && $val !== null) {
        $signStr .= "{$key}={$val}&";
    }
}
$signStr .= "key=c05a23c7e62d158abe573a0cca660b12";
$params['sign'] = md5($signStr);

echo "Params: " . json_encode($params) . "\n\n";

// Use reflection to call private sendRequest method
$reflection = new ReflectionClass($wpay);
$method = $reflection->getMethod('sendRequest');
$method->setAccessible(true);

$result = $method->invoke($wpay, '/v1/Collect', $params);

echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";

if ($result && isset($result['code']) && $result['code'] === 0) {
    echo "\n✅ SUCCESS\n";
} else {
    echo "\n❌ FAILED\n";
}
