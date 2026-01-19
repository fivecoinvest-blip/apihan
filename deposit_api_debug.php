<?php
/**
 * Deposit API with enhanced debugging
 */
require_once 'session_config.php';
require_once 'wpay_config.php';
require_once 'wpay_helper.php';
require_once 'db_helper.php';

header('Content-Type: application/json');

// Log request details
$debug = [
    'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
    'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'timestamp' => date('Y-m-d H:i:s'),
    'merchant_id' => WPAY_MERCHANT_ID,
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST only']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$amount = floatval($_POST['amount'] ?? 0);
$payType = $_POST['pay_type'] ?? '';

// Validate
if ($amount < WPAY_MIN_DEPOSIT || $amount > WPAY_MAX_DEPOSIT) {
    echo json_encode(['error' => 'Invalid amount', 'debug' => $debug]);
    exit;
}

if (!in_array($payType, ['GCASH', 'MAYA', 'QR'])) {
    echo json_encode(['error' => 'Invalid pay type', 'debug' => $debug]);
    exit;
}

// Create deposit with debug
$wpay = new WPayHelper();
$result = $wpay->createPayIn($_SESSION['user_id'], $amount, $payType);

// Add debug info
if (!$result['success']) {
    $result['debug'] = $debug;
}

echo json_encode($result);
?>
