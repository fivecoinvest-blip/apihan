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
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $perPage);
    
    // Get paginated results
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
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$userId, $perPage, $offset]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'per_page' => $perPage
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
