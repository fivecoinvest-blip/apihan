<?php
/**
 * Get User Transaction History (AJAX endpoint)
 */
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once 'config.php';
require_once 'db_helper.php';

$userId = $_GET['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT 
            t.type, 
            t.amount, 
            t.balance_before, 
            t.balance_after, 
            t.description,
            t.game_uid,
            g.name as game_name,
            u.currency,
            DATE_FORMAT(t.created_at, '%M %d, %Y %H:%i:%s') as created_at
        FROM transactions t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN games g ON t.game_uid = g.game_uid
        WHERE t.user_id = ? 
        ORDER BY t.created_at DESC 
        LIMIT 50
    ");
    
    $stmt->execute([$userId]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
