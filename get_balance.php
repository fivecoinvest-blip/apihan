<?php
/**
 * Get Current Balance - AJAX Endpoint
 * Returns real-time balance for logged-in user
 */
require_once 'session_config.php';
require_once 'db_helper.php';
require_once 'currency_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

try {
    $userModel = new User();
    
    // Get balance directly from database (bypassing cache for real-time accuracy)
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("SELECT balance, currency FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    $balance = (float)$user['balance'];
    $currency = $user['currency'] ?? 'PHP';
    
    // Format balance with currency
    $formattedBalance = formatCurrency($balance, $currency);
    
    echo json_encode([
        'success' => true,
        'balance' => $balance,
        'formatted' => $formattedBalance,
        'currency' => $currency
    ]);
    
} catch (Exception $e) {
    error_log("Get balance error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
