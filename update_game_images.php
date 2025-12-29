<?php
/**
 * Update Game Images Script
 * Updates database with image paths for all matched JILI games
 */

// Database connection
$host = 'localhost';
$db = 'casino_db';
$user = 'casino_user';
$pass = 'casino123';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🎮 JILI Game Image Updater\n";
    echo "============================\n\n";
    
    // Get all games from database
    $stmt = $pdo->query("SELECT id, name, image FROM games WHERE provider = 'JILI' ORDER BY name");
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updated = 0;
    $skipped = 0;
    $not_found = 0;
    
    foreach ($games as $game) {
        $game_name = $game['name'];
        $image_filename = $game_name . '.png';
        $image_path = 'images/games/' . $image_filename;
        
        // Check if image file exists on server
        $full_path = '/var/www/html/' . $image_path;
        if (file_exists($full_path)) {
            // Update database with image path
            $updateStmt = $pdo->prepare("UPDATE games SET image = ? WHERE id = ?");
            $updateStmt->execute([$image_path, $game['id']]);
            
            echo "✓ Updated: {$game_name}\n";
            $updated++;
        } else {
            echo "⊗ Not found: {$game_name} (looking for: {$image_filename})\n";
            $not_found++;
        }
    }
    
    echo "\n============================\n";
    echo "Summary:\n";
    echo "✓ Updated: {$updated} games\n";
    echo "⊗ Not found: {$not_found} images\n";
    echo "\n✅ Database update complete!\n";
    
} catch (PDOException $e) {
    die("❌ Database error: " . $e->getMessage() . "\n");
}
?>
