<?php
session_start();
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'config.php';

if (!isset($_POST['game_id']) || !isset($_FILES['game_image'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

$gameId = (int)$_POST['game_id'];
$uploadDir = 'images/games/';

// Create directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$file = $_FILES['game_image'];
$fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// Validate file
if (!in_array($fileExt, $allowedExt)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: jpg, png, gif, webp']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) { // 5MB max
    echo json_encode(['success' => false, 'message' => 'File too large. Max 5MB']);
    exit;
}

// Generate unique filename
$newFileName = 'game_' . $gameId . '_' . time() . '.' . $fileExt;
$targetPath = $uploadDir . $newFileName;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Update database
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $stmt = $pdo->prepare("UPDATE games SET image = ? WHERE id = ?");
        $stmt->execute([$targetPath, $gameId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Image uploaded successfully',
            'image_path' => $targetPath
        ]);
    } catch (PDOException $e) {
        // Delete uploaded file on database error
        unlink($targetPath);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
}
?>
