<?php
/**
 * WPay Diagnostic Tool
 * Requires admin authentication
 */

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    die('Access denied. Please login to admin panel first.');
}

require_once 'wpay_config.php';
require_once 'wpay_helper.php';

echo "=== WPay Diagnostic Report ===\n";
echo date('Y-m-d H:i:s') . "\n\n";

// 1. Server Info
echo "SERVER INFORMATION:\n";
echo "  Server IP: " . shell_exec("curl -s https://api.ipify.org") . "\n";
echo "  PHP Version: " . phpversion() . "\n";
echo "  cURL Version: " . curl_version()['version'] . "\n";
echo "  OpenSSL Version: " . OPENSSL_VERSION_TEXT . "\n\n";

// 2. WPay Config
echo "WPAY CONFIGURATION:\n";
echo "  Environment: " . WPAY_ENV . "\n";
echo "  Merchant ID: " . WPAY_MCH_ID . "\n";
echo "  API Host: " . WPAY_HOST . "\n";
echo "  API Key (first 16 chars): " . substr(WPAY_KEY, 0, 16) . "...\n";
echo "  Callback IPs: " . json_encode(WPAY_PROD_CALLBACK_IPS) . "\n\n";

// 3. Test SSL/TLS Connection
echo "SSL/TLS TEST:\n";
$ch = curl_init("https://api.wpay.life");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_exec($ch);
$certInfo = curl_getinfo($ch, CURLINFO_CERTINFO);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "  Connection Status: " . ($httpCode ? "OK (HTTP {$httpCode})" : "FAILED") . "\n\n";

// 4. Test Simple API Call with Debugging
echo "API TEST - Query Balance:\n";
$wpay = new WPayHelper();
$params = [
    'mchId' => WPAY_MCH_ID,
];
$params['sign'] = $wpay->generateSign($params);

echo "  Params: " . json_encode($params) . "\n";
echo "  Signature: " . $params['sign'] . "\n";

$url = WPAY_HOST . '/v1/balance';
echo "  URL: " . $url . "\n";

// Manual curl test with maximum verbosity
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'User-Agent: WPay-PHP-Diagnostic/1.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErrno = curl_errno($ch);
$curlError = curl_error($ch);

echo "  HTTP Code: " . $httpCode . "\n";
if ($curlErrno) {
    echo "  cURL Error ({$curlErrno}): {$curlError}\n";
} else {
    echo "  cURL: OK\n";
}
echo "  Response: " . substr($response, 0, 200) . "...\n\n";

curl_close($ch);

// 5. Test Deposit Call
echo "DEPOSIT TEST:\n";
$depositParams = [
    'mchId' => WPAY_MCH_ID,
    'currency' => 'PHP',
    'out_trade_no' => 'TEST' . date('YmdHis'),
    'pay_type' => 'GCASH',
    'money' => 100,
    'notify_url' => 'https://example.com/callback',
    'returnUrl' => 'https://example.com/return',
];
$depositParams['sign'] = $wpay->generateSign($depositParams);

echo "  Params (sanitized): {mchId, currency, out_trade_no, pay_type, money, notify_url, returnUrl, sign}\n";
echo "  Merchant ID: " . $depositParams['mchId'] . "\n";
echo "  Signature: " . $depositParams['sign'] . "\n";

$url = WPAY_HOST . '/v1/Collect';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($depositParams));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'User-Agent: WPay-PHP-Diagnostic/1.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErrno = curl_errno($ch);

echo "  URL: " . $url . "\n";
echo "  HTTP Code: " . $httpCode . "\n";

if ($httpCode === 403) {
    echo "  ERROR: 403 Forbidden - IP may not be whitelisted\n";
    echo "  Server IP: " . shell_exec("curl -s https://api.ipify.org");
    echo "  Action: Contact WPay support with your server IP to get it whitelisted\n";
} elseif ($curlErrno) {
    echo "  cURL Error ({$curlErrno}): " . curl_error($ch) . "\n";
} else {
    $data = json_decode($response, true);
    echo "  Response: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

curl_close($ch);

echo "\n=== END DIAGNOSTIC ===\n";
?>
