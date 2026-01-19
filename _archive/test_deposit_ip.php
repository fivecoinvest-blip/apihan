<?php
/**
 * IP-based deposit test
 * Tests the full deposit flow via IP address
 */

// Simulate logged-in session
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['submit_deposit'] = '1';
$_POST['amount'] = '250';
$_POST['pay_type'] = 'GCASH';

// Start session as user ID 1
session_start();
$_SESSION['user_id'] = 1;

// Now run the deposit handler
require_once '/var/www/html/session_config.php';
require_once '/var/www/html/wpay_config.php';
require_once '/var/www/html/wpay_helper.php';
require_once '/var/www/html/wpay_fee_messages.php';
require_once '/var/www/html/db_helper.php';

echo "=== Deposit Test via IP ===\n";
echo "Callback Domain (from config): " . WPAY_NOTIFY_URL . "\n\n";

$userModel = new User();
$currentUser = $userModel->getById($_SESSION['user_id']);
$balance = $userModel->getBalance($_SESSION['user_id']);

$error = '';
$success = '';
$paymentUrl = '';

// Handle deposit request (copied from deposit_auto.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_deposit'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $payType = $_POST['pay_type'] ?? '';
    
    echo "Processing deposit: ₱{$amount} via {$payType}\n";
    
    // Validate amount
    if ($amount < WPAY_MIN_DEPOSIT) {
        $error = "Minimum deposit amount is ₱" . number_format(WPAY_MIN_DEPOSIT);
    } elseif ($amount > WPAY_MAX_DEPOSIT) {
        $error = "Maximum deposit amount is ₱" . number_format(WPAY_MAX_DEPOSIT);
    } elseif (empty($payType)) {
        $error = "Please select a payment method";
    } else {
        // Use WPayHelper to create deposit
        $wpay = new WPayHelper();
        $result = $wpay->createPayIn($_SESSION['user_id'], $amount, $payType);
        
        if ($result['success']) {
            $paymentUrl = $result['payment_url'] ?? null;
            $success = 'Deposit request created successfully!';
            echo "✓ SUCCESS\n";
            echo "Order: " . $result['order_no'] . "\n";
            echo "Payment URL: " . $paymentUrl . "\n";
        } else {
            $error = $result['error'] ?? 'Failed to process deposit. Please try again.';
            echo "✗ FAILED\n";
            echo "Error: " . $error . "\n";
            if (isset($result['technical_error'])) {
                echo "Technical: " . $result['technical_error'] . "\n";
            }
        }
    }
    
    if ($error) {
        echo "✗ ERROR: " . $error . "\n";
    }
}
?>
