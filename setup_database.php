<?php
/**
 * Database Setup - User Management & Transactions
 */

// Database Configuration
$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$dbname = defined('DB_NAME') ? DB_NAME : 'casino_db';
$username = defined('DB_USER') ? DB_USER : 'root';
$password = defined('DB_PASS') ? DB_PASS : '';

try {
    $db = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $db->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    $db->exec("USE $dbname");
    
    // Users table
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            phone VARCHAR(20) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            balance DECIMAL(10,2) DEFAULT 0.00,
            currency VARCHAR(3) DEFAULT 'PHP',
            country_code VARCHAR(5) DEFAULT '+639',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            status ENUM('active', 'suspended', 'banned') DEFAULT 'active',
            INDEX idx_username (username),
            INDEX idx_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Transactions table
    $db->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type ENUM('deposit', 'withdrawal', 'bet', 'win', 'refund') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            balance_before DECIMAL(10,2) NOT NULL,
            balance_after DECIMAL(10,2) NOT NULL,
            game_uid VARCHAR(50) NULL,
            game_round VARCHAR(100) NULL,
            description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            INDEX idx_user_id (user_id),
            INDEX idx_type (type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Game sessions table
    $db->exec("
        CREATE TABLE IF NOT EXISTS game_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            game_uid VARCHAR(50) NOT NULL,
            game_name VARCHAR(100) NULL,
            start_balance DECIMAL(10,2) NOT NULL,
            end_balance DECIMAL(10,2) NULL,
            total_bets DECIMAL(10,2) DEFAULT 0.00,
            total_wins DECIMAL(10,2) DEFAULT 0.00,
            rounds_played INT DEFAULT 0,
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ended_at TIMESTAMP NULL,
            status ENUM('active', 'completed') DEFAULT 'active',
            FOREIGN KEY (user_id) REFERENCES users(id),
            INDEX idx_user_id (user_id),
            INDEX idx_game_uid (game_uid),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // User preferences table
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_preferences (
            user_id INT PRIMARY KEY,
            language VARCHAR(10) DEFAULT 'en',
            currency VARCHAR(3) DEFAULT 'PHP',
            theme VARCHAR(20) DEFAULT 'dark',
            notifications BOOLEAN DEFAULT true,
            sound_enabled BOOLEAN DEFAULT true,
            FOREIGN KEY (user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    echo "✅ Database setup completed successfully!\n";
    echo "Database: $dbname\n";
    echo "Tables created: users, transactions, game_sessions, user_preferences\n";
    
} catch (PDOException $e) {
    die("❌ Database setup failed: " . $e->getMessage() . "\n");
}
?>
