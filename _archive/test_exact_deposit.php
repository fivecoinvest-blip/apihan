<?php
/**
 * Test createPayIn exactly as deposit_auto.php would call it
 */
session_start();
$_SESSION['user_id'] = 1; // Set test user

require_once 'wpay_config.php';
require_once 'wpay_helper.php';

echo "=== Testing createPayIn (as deposit_auto.php calls it) ===\n\n";

$wpay = new WPayHelper();
$result = $wpay->createPayIn(1, 100, 'GCASH');

echo "Result:\n";
print_r($result);

if ($result['success']) {
    echo "\n✅ SUCCESS! Payment URL: " . $result['payment_url'] . "\n";
} else {
    echo "\n❌ FAILED: " . $result['error'] . "\n";
}
