<?php
session_start();

// Track logout time and session duration
if (isset($_SESSION['login_history_id'])) {
    require_once 'config.php';
    require_once 'db_helper.php';
    
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("
        UPDATE login_history SET 
            logout_time = NOW(),
            session_duration = TIMESTAMPDIFF(SECOND, login_time, NOW())
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['login_history_id']]);
}

session_destroy();
header('Location: login.php');
exit;
?>
