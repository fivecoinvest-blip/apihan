<?php
/**
 * Keep Session Alive - AJAX Endpoint
 * Called periodically from game page to prevent session timeout
 */
require_once 'session_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Update last activity
$_SESSION['last_activity'] = time();

echo json_encode([
    'success' => true,
    'message' => 'Session refreshed',
    'remaining_time' => 14400 - (time() - $_SESSION['last_activity'])
]);
?>
