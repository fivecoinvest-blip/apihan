<?php
/**
 * Simplified deposit API endpoint for testing
 */
require_once 'session_config.php';
require_once 'wpay_config.php';
require_once 'wpay_helper.php';
require_once 'db_helper.php';

header('Content-Type: application/json');

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
    echo json_encode(['error' => 'Invalid amount']);
    exit;
}

if (!in_array($payType, ['GCASH', 'MAYA', 'QR'])) {
    echo json_encode(['error' => 'Invalid pay type']);
    exit;
}

// Create deposit
$wpay = new WPayHelper();
$result = $wpay->createPayIn($_SESSION['user_id'], $amount, $payType);

echo json_encode($result);
?>
