<?php
/**
 * Database Helper Functions with Redis Caching
 */

require_once __DIR__ . '/redis_helper.php';

class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $dbname = defined('DB_NAME') ? DB_NAME : 'casino_db';
        $username = defined('DB_USER') ? DB_USER : 'root';
        $password = defined('DB_PASS') ? DB_PASS : '';
        
        try {
            $this->conn = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
}

class User {
    private $db;
    private $cache;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->cache = RedisCache::getInstance();
    }
    
    public function register($phone, $password, $countryCode = '+639', $currency = 'PHP') {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        // Normalize phone number
        $normalizedPhone = $this->normalizePhoneNumber($phone, $countryCode);
        
        // Auto-generate unique username
        $username = $this->generateUsername($normalizedPhone);
        
        $stmt = $this->db->prepare("
            INSERT INTO users (username, phone, password, balance, country_code, currency)
            VALUES (?, ?, ?, 0.00, ?, ?)
        ");
        
        try {
            $stmt->execute([$username, $normalizedPhone, $hashedPassword, $countryCode, $currency]);
            return [
                'id' => $this->db->lastInsertId(),
                'username' => $username
            ];
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Generate unique username from phone number
     * Format: user_XXXXXXXX (last 8 digits of phone)
     */
    private function generateUsername($phone) {
        // Remove country code and get last 8 digits
        $phoneDigits = preg_replace('/[^0-9]/', '', $phone);
        $baseUsername = 'user_' . substr($phoneDigits, -8);
        
        // Check if username exists, if yes add random suffix
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$baseUsername]);
        
        if ($stmt->fetch()) {
            // Add random suffix if username exists
            $baseUsername .= '_' . rand(100, 999);
        }
        
        return $baseUsername;
    }
    
    public function login($phoneOrUsername, $password) {
        // Try to normalize as phone number first
        $normalizedPhone = $this->normalizePhoneNumber($phoneOrUsername);
        
        $stmt = $this->db->prepare("
            SELECT * FROM users 
            WHERE (username = ? OR phone = ? OR phone = ?) AND status = 'active'
        ");
        $stmt->execute([$phoneOrUsername, $phoneOrUsername, $normalizedPhone]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Update last login
            $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                     ->execute([$user['id']]);
            return $user;
        }
        
        return false;
    }
    
    /**
     * Normalize phone number to international format
     * Accepts: +639XXXXXXXXX, 09XXXXXXXXX, 9XXXXXXXXX
     * Returns: +639XXXXXXXXX
     */
    private function normalizePhoneNumber($phone, $countryCode = '+639') {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // If starts with country code, return as is
        if (strpos($phone, $countryCode) === 0) {
            return $phone;
        }
        
        // If starts with 0, remove it and add country code
        if (strpos($phone, '0') === 0) {
            return $countryCode . substr($phone, 1);
        }
        
        // If just the number without prefix, add country code
        if (strlen($phone) === 10) {
            return $countryCode . $phone;
        }
        
        // Default: add country code
        return $countryCode . $phone;
    }
    
    /**
     * Format phone for display (hide country code)
     * +639123456789 -> 09123456789
     */
    public function formatPhoneDisplay($phone) {
        if (strpos($phone, '+639') === 0) {
            return '0' . substr($phone, 4);
        }
        return $phone;
    }
    
    public function getById($userId) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    public function getBalance($userId) {
        // Try to get from cache first with freshness check (max 60 seconds old)
        $cacheKey = "user:balance:{$userId}";
        $cachedBalance = $this->cache->getWithFreshness($cacheKey, 60);
        
        if ($cachedBalance !== false) {
            return (float)$cachedBalance;
        }
        
        // Cache miss or stale - get from database
        $stmt = $this->db->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        $balance = $result ? (float)$result['balance'] : 0;
        
        // Store in cache with timestamp for 1 minute (critical data)
        $this->cache->setWithTimestamp($cacheKey, $balance, RedisCache::PRIORITY_CRITICAL);
        
        return $balance;
    }
    
    public function updateBalance($userId, $newBalance) {
        // Update database first
        $stmt = $this->db->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $result = $stmt->execute([$newBalance, $userId]);
        
        if ($result) {
            // Write-through: immediately update cache with new value
            $this->cache->refreshBalance($userId, $newBalance);
            
            // Also invalidate related caches
            $this->cache->delete("user:data:{$userId}");
            
            error_log("Balance updated for user {$userId}: {$newBalance} (cache refreshed)");
        }
        
        return $result;
    }
    
    public function addBalance($userId, $amount, $type = 'deposit', $description = null) {
        $this->db->beginTransaction();
        
        try {
            $currentBalance = $this->getBalance($userId);
            $newBalance = $currentBalance + $amount;
            
            $this->updateBalance($userId, $newBalance);
            
            $stmt = $this->db->prepare("
                INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, description)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $type, $amount, $currentBalance, $newBalance, $description]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    public function deductBalance($userId, $amount, $type = 'bet', $gameUid = null) {
        $this->db->beginTransaction();
        
        try {
            $currentBalance = $this->getBalance($userId);
            
            if ($currentBalance < $amount) {
                $this->db->rollBack();
                return false;
            }
            
            $newBalance = $currentBalance - $amount;
            $this->updateBalance($userId, $newBalance);
            
            $stmt = $this->db->prepare("
                INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, game_uid)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $type, $amount, $currentBalance, $newBalance, $gameUid]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    public function getTransactions($userId, $limit = 50) {
        $stmt = $this->db->prepare("
            SELECT * FROM transactions 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
}
?>
