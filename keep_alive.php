<?php
/**
 * Keep Session Alive - AJAX Endpoint
 * Called periodically from game page to prevent session timeout
 * Also fetches current balance to detect admin updates
 */
require_once 'session_config.php';
require_once 'db_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Update last activity
$_SESSION['last_activity'] = time();

// Get current balance (will use cache if available, fresh from DB if cache was invalidated)
$userModel = new User();
$currentBalance = $userModel->getBalanceFresh($_SESSION['user_id']);

echo json_encode([
    'success' => true,
    'message' => 'Session refreshed',
    'remaining_time' => 14400 - (time() - $_SESSION['last_activity']),
    'balance' => $currentBalance
]);
?>
