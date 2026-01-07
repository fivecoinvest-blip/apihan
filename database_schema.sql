-- Database Schema for Gaming API Integration
-- Create these tables in your MySQL/MariaDB database

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) UNIQUE NOT NULL,
    username VARCHAR(100) NOT NULL,
    balance DECIMAL(15, 2) DEFAULT 0.00,
    currency_code VARCHAR(3) DEFAULT 'USD',
    language VARCHAR(5) DEFAULT 'en',
    status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactions/Bet History table
CREATE TABLE IF NOT EXISTS transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    game_uid VARCHAR(100),
    game_round VARCHAR(200),
    transaction_type ENUM('bet', 'win', 'refund') DEFAULT 'bet',
    bet_amount DECIMAL(15, 2) DEFAULT 0.00,
    win_amount DECIMAL(15, 2) DEFAULT 0.00,
    balance_before DECIMAL(15, 2) DEFAULT 0.00,
    balance_after DECIMAL(15, 2) DEFAULT 0.00,
    timestamp VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_game_uid (game_uid),
    INDEX idx_game_round (game_round),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Game Sessions table
CREATE TABLE IF NOT EXISTS game_sessions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    game_uid VARCHAR(100) UNIQUE NOT NULL,
    game_url TEXT,
    status ENUM('active', 'completed', 'expired') DEFAULT 'active',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    total_bet DECIMAL(15, 2) DEFAULT 0.00,
    total_win DECIMAL(15, 2) DEFAULT 0.00,
    rounds_played INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_game_uid (game_uid),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Logs table
CREATE TABLE IF NOT EXISTS api_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    log_type ENUM('request', 'response', 'callback', 'error') NOT NULL,
    endpoint VARCHAR(255),
    user_id VARCHAR(50),
    game_uid VARCHAR(100),
    request_data TEXT,
    response_data TEXT,
    status_code INT,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_type (log_type),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample users for testing
INSERT INTO users (user_id, username, balance, currency_code, language, status) VALUES
('101', 'Jhon', 500.00, 'USD', 'en', 'active'),
('102', 'Sarah', 1000.00, 'BDT', 'bn', 'active'),
('103', 'Mike', 750.00, 'USD', 'en', 'active');
