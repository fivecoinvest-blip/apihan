<?php
/**
 * Create Game Plays Tracking Table
 */

require_once 'config.php';

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Create game_plays table to track each game launch
    $db->exec("
        CREATE TABLE IF NOT EXISTS game_plays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            game_uid VARCHAR(50) NOT NULL,
            game_name VARCHAR(100) NULL,
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_game_uid (game_uid),
            INDEX idx_started_at (started_at),
            FOREIGN KEY (user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    echo "✅ Game plays tracking table created successfully!\n";
    
} catch (PDOException $e) {
    die("❌ Error: " . $e->getMessage() . "\n");
}
?>
