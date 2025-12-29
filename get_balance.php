<?php
/**
 * Get User Balance - AJAX endpoint for real-time balance updates
 */
require_once 'config.php';
require_once 'session_config.php';
require_once 'db_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

try {
    $userModel = new User();
    $balance = $userModel->getBalance($_SESSION['user_id']);
    $user = $userModel->getById($_SESSION['user_id']);
    
    echo json_encode([
        'success' => true,
        'balance' => $balance,
        'currency' => $user['currency'] ?? 'PHP',
        'formatted' => number_format($balance, 2)
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
