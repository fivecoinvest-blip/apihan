<?php
/**
 * Get Login History for a User
 * AJAX endpoint for admin panel
 */

require_once 'config.php';
require_once 'db_helper.php';

header('Content-Type: application/json');

if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

$userId = (int)$_GET['user_id'];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Get login history for user
    $stmt = $pdo->prepare("
        SELECT 
            login_time,
            logout_time,
            session_duration,
            ip_address,
            device,
            browser,
            os,
            country,
            city
        FROM login_history
        WHERE user_id = ?
        ORDER BY login_time DESC
        LIMIT 50
    ");
    
    $stmt->execute([$userId]);
    $logins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format dates
    foreach ($logins as &$login) {
        $login['login_time'] = date('M d, Y H:i:s', strtotime($login['login_time']));
        if ($login['logout_time']) {
            $login['logout_time'] = date('M d, Y H:i:s', strtotime($login['logout_time']));
        }
    }
    
    echo json_encode([
        'success' => true,
        'logins' => $logins
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
