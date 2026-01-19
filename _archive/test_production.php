<?php
require_once 'wpay_config.php';
require_once 'wpay_helper.php';

echo "=== Testing Production PayIn with Whitelisted IPs ===\n\n";
echo "Environment: " . WPAY_ENV . "\n";
echo "Merchant ID: " . WPAY_MCH_ID . "\n";
echo "API Host: " . WPAY_HOST . "\n\n";

$wpay = new WPayHelper();
$result = $wpay->createPayIn(1, 100, 'GCASH');

echo "Result:\n";
print_r($result);

if ($result['success']) {
    echo "\n✅ SUCCESS! Payment URL: " . $result['payment_url'] . "\n";
} else {
    echo "\n❌ FAILED: " . $result['error'] . "\n";
}
