<?php
/**
 * WPay Payment Gateway Configuration
 * Provider: OKExPay / WPay
 */

// Load main config for database and app settings
require_once __DIR__ . '/config.php';

// Environment: 'sandbox' or 'production'
define('WPAY_ENV', 'production');

// Test Environment Credentials
define('WPAY_TEST_MCH_ID', '1000');
define('WPAY_TEST_KEY', '4035fcd2d720e1b06ea455bdde411012');
define('WPAY_TEST_HOST', 'https://sandbox.okexpay.dev');
define('WPAY_TEST_CALLBACK_IP', '103.156.25.75');

// Production Environment Credentials
define('WPAY_PROD_MCH_ID', '5047');
define('WPAY_PROD_KEY', 'c05a23c7e62d158abe573a0cca660b12');
define('WPAY_PROD_HOST', 'https://api.wpay.life');
define('WPAY_PROD_CALLBACK_IPS', ['43.224.224.185', '43.224.224.239']);

// Active Configuration based on environment
define('WPAY_MCH_ID', WPAY_ENV === 'production' ? WPAY_PROD_MCH_ID : WPAY_TEST_MCH_ID);
define('WPAY_KEY', WPAY_ENV === 'production' ? WPAY_PROD_KEY : WPAY_TEST_KEY);
define('WPAY_HOST', WPAY_ENV === 'production' ? WPAY_PROD_HOST : WPAY_TEST_HOST);

// Callback URLs - Using actual domain
define('WPAY_NOTIFY_URL', 'https://paldo88.site/wpay_callback.php');
define('WPAY_RETURN_URL', 'https://paldo88.site/dashboard.php');

// Payment Types
define('WPAY_PAY_TYPE_GCASH', 'GCASH');
define('WPAY_PAY_TYPE_MAYA', 'MAYA');
define('WPAY_PAY_TYPE_QR', 'QR'); // QR code payment
define('WPAY_PAY_TYPE_NATIVE', 'NATIVE'); // Bank transfer

// Currency
define('WPAY_CURRENCY', 'PHP');

// Transaction Limits
define('WPAY_MIN_DEPOSIT', 100); // Minimum deposit amount in PHP
define('WPAY_MAX_DEPOSIT', 50000); // Maximum deposit amount in PHP
define('WPAY_MIN_WITHDRAWAL', 100); // Minimum withdrawal amount in PHP
define('WPAY_MAX_WITHDRAWAL', 50000); // Maximum withdrawal amount in PHP

// Transaction Settings
define('WPAY_TRANSACTION_TIMEOUT', 1800); // 30 minutes in seconds

// Payment Provider Fees
define('WPAY_COLLECTION_FEE_PERCENT', 1.6); // 1.6% collection fee
define('WPAY_PROCESSING_FEE', 8); // 8 PHP processing fee (withdrawal)

// Fee Charging Settings (Admin can toggle in future)
define('WPAY_CHARGE_DEPOSIT_FEE_TO_USER', false); // false = admin covers deposit fees, true = charge to customer
define('WPAY_CHARGE_WITHDRAWAL_FEE_TO_USER', false); // false = admin covers withdrawal fees, true = charge to customer
define('WPAY_MAX_CALLBACK_RETRIES', 5); // Maximum callback retry attempts

/**
 * Get allowed callback IPs based on environment
 */
function getWPayCallbackIPs(): array {
    if (WPAY_ENV === 'production') {
        return WPAY_PROD_CALLBACK_IPS;
    }
    return [WPAY_TEST_CALLBACK_IP];
}

/**
 * Check if IP is from WPay callback
 */
function isWPayCallbackIP(string $ip): bool {
    $allowedIPs = getWPayCallbackIPs();
    return in_array($ip, $allowedIPs);
}
?>
