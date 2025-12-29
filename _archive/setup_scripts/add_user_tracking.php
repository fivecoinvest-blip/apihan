<?php
/**
 * Add user tracking fields to database
 * Run this once to add IP, device, and session tracking
 */

require_once 'config.php';
require_once 'db_helper.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

try {
    // Check and add tracking columns one by one
    $columns = [
        "last_ip VARCHAR(45) DEFAULT NULL",
        "last_device VARCHAR(255) DEFAULT NULL",
        "last_browser VARCHAR(255) DEFAULT NULL",
        "last_os VARCHAR(100) DEFAULT NULL",
        "login_count INT DEFAULT 0",
        "total_deposits DECIMAL(15,2) DEFAULT 0.00",
        "total_withdrawals DECIMAL(15,2) DEFAULT 0.00",
        "total_bets DECIMAL(15,2) DEFAULT 0.00",
        "total_wins DECIMAL(15,2) DEFAULT 0.00"
    ];
    
    foreach ($columns as $column) {
        $columnName = explode(' ', $column)[0];
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN $column");
            echo "✅ Added column: $columnName\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "⏭️  Column $columnName already exists\n";
            } else {
                echo "❌ Error adding $columnName: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Create login history table
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                ip_address VARCHAR(45),
                device VARCHAR(255),
                browser VARCHAR(255),
                os VARCHAR(100),
                country VARCHAR(100),
                city VARCHAR(100),
                login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                logout_time DATETIME DEFAULT NULL,
                session_duration INT DEFAULT 0,
                INDEX idx_user_id (user_id),
                INDEX idx_login_time (login_time)
            )
        ");
        echo "✅ Created login_history table\n";
    } catch (PDOException $e) {
        echo "⏭️  login_history table already exists\n";
    }
    
    echo "\n✅ Database migration completed!\n";
    echo "Added tracking fields to users table\n";
    echo "Created login_history table for session tracking\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
