<?php
/**
 * Game Callback Handler
 * 
 * This endpoint receives game results from SoftAPI after each bet or win.
 * It updates the user's balance and logs the transaction.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db_helper.php';

// Set response header
header('Content-Type: application/json; charset=utf-8');

try {
    // Get raw POST data
    $rawData = file_get_contents('php://input');
    
    // Log the incoming request
    logMessage("Received callback: " . $rawData, 'info');
    
    // Decode JSON data
    $data = json_decode($rawData, true);
    
    // Validate JSON
    if (!$data || !is_array($data)) {
        logMessage("Invalid JSON received in callback", 'error');
        echo json_encode([
            'credit_amount' => -1,
            'error' => 'Invalid JSON 无效JSON'
        ]);
        exit;
    }
    
    // Check if payload is encrypted
    if (isset($data['payload'])) {
        // Decrypt the payload
        $encryptedPayload = $data['payload'];
        $decrypted = openssl_decrypt($encryptedPayload, 'AES-256-ECB', API_SECRET, 0);
        
        if ($decrypted === false) {
            logMessage("Failed to decrypt callback payload", 'error');
            echo json_encode([
                'credit_amount' => -1,
                'error' => 'Decryption failed'
            ]);
            exit;
        }
        
        logMessage("Decrypted payload: " . $decrypted, 'info');
        $data = json_decode($decrypted, true);
        
        if (!$data || !is_array($data)) {
            logMessage("Invalid decrypted data", 'error');
            echo json_encode([
                'credit_amount' => -1,
                'error' => 'Invalid decrypted data'
            ]);
            exit;
        }
    }
    
    // Extract callback data
    $gameUid = $data['game_uid'] ?? '';
    $gameRound = $data['game_round'] ?? '';
    $memberAccount = $data['member_account'] ?? '';
    $betAmount = (float)($data['bet_amount'] ?? 0);
    $winAmount = (float)($data['win_amount'] ?? 0);
    $timestamp = $data['timestamp'] ?? '';
    
    // Validate required fields
    $required = ['game_uid', 'game_round', 'member_account'];
    if (!validateRequired($data, $required)) {
        logMessage("Missing required callback fields", 'error');
        echo json_encode([
            'credit_amount' => -1,
            'error' => 'Missing required fields'
        ]);
        exit;
    }
    
    // Log transaction details
    logMessage(
        "Game callback - User: {$memberAccount}, Round: {$gameRound}, " .
        "Bet: {$betAmount}, Win: {$winAmount}", 
        'info'
    );
    
    // ==========================================
    // YOUR DATABASE LOGIC HERE
    // ==========================================
    // Example:
    // 1. Get current user balance from database
    // $currentBalance = getUserBalance($memberAccount);
    //
    // 2. Calculate new balance
    // $newBalance = $currentBalance - $betAmount + $winAmount;
    //
    // 3. Update user balance in database
    // updateUserBalance($memberAccount, $newBalance);
    //
    // 4. Save bet history/transaction log
    // saveBetHistory([
    //     'user_id' => $memberAccount,
    //     'game_uid' => $gameUid,
    //     'game_round' => $gameRound,
    //     'bet_amount' => $betAmount,
    //     'win_amount' => $winAmount,
    //     'timestamp' => $timestamp
    // ]);
    
    // For demonstration purposes, calculate credit
    // In production, this should be the user's ACTUAL NEW BALANCE from DB
    $creditAmount = processGameCallback($memberAccount, $betAmount, $winAmount, $gameUid, $gameRound);
    
    // Prepare response
    $response = [
        'credit_amount' => $creditAmount, // Must be user's current balance after transaction
        'timestamp' => round(microtime(true) * 1000)
    ];
    
    logMessage("Callback processed successfully - Credit: {$creditAmount}", 'info');
    
    // Send response
    echo json_encode($response);
    
} catch (Exception $e) {
    logMessage("Callback error: " . $e->getMessage(), 'error');
    echo json_encode([
        'credit_amount' => -1,
        'error' => $e->getMessage()
    ]);
}

/**
 * Process game callback and update user balance
 * 
 * @param string $userId User/member account ID
 * @param float $betAmount Amount bet in this round
 * @param float $winAmount Amount won in this round
 * @return float User's new balance after transaction
 */
function processGameCallback(string $userId, float $betAmount, float $winAmount, string $gameUid = '', string $gameRound = ''): float {
    try {
        // Get database connection
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        // Get current user balance from database
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            logMessage("User {$userId} not found in database", 'error');
            return 0;
        }
        
        $currentBalance = (float)$user['balance'];
        logMessage("User {$userId} current balance: {$currentBalance}", 'info');
        
        // Calculate new balance
        $newBalance = $currentBalance - $betAmount + $winAmount;
        
        // Ensure balance doesn't go negative
        if ($newBalance < 0) {
            logMessage("Insufficient balance for user {$userId}", 'warning');
            $newBalance = 0;
        }
        
        // Update user balance and betting totals in database
        $stmt = $pdo->prepare("
            UPDATE users SET 
                balance = ?,
                total_bets = total_bets + ?,
                total_wins = total_wins + ?
            WHERE id = ?
        ");
        $stmt->execute([$newBalance, $betAmount, $winAmount, $userId]);
        
        // Save transaction to database
        $stmt = $pdo->prepare("
            INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, game_uid, game_round, description, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        if ($betAmount > 0) {
            $stmt->execute([
                $userId,
                'bet',
                $betAmount,
                $currentBalance,
                $currentBalance - $betAmount,
                $gameUid,
                $gameRound,
                'Game bet'
            ]);
        }
        
        if ($winAmount > 0) {
            $stmt->execute([
                $userId,
                'win',
                $winAmount,
                $currentBalance - $betAmount,
                $newBalance,
                $gameUid,
                $gameRound,
                'Game win'
            ]);
        }
        
        logMessage(
            "Balance updated in DB - User: {$userId}, " .
            "Bet: {$betAmount}, Win: {$winAmount}, " .
            "Old: {$currentBalance}, New: {$newBalance}",
            'info'
        );
        
        // Also update file-based storage for backward compatibility
        updateFileBalance($userId, $newBalance);
        
        return $newBalance;
        
    } catch (Exception $e) {
        logMessage("Error processing callback: " . $e->getMessage(), 'error');
        return 0;
    }
}

/**
 * Update file-based balance storage (backward compatibility)
 */
function updateFileBalance(string $userId, float $balance): void {
    $balanceFile = __DIR__ . '/logs/balances.json';
    
    // Ensure directory exists
    if (!file_exists(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0777, true);
    }
    
    // Load existing balances
    $balances = [];
    if (file_exists($balanceFile)) {
        $content = file_get_contents($balanceFile);
        $balances = json_decode($content, true) ?: [];
    }
    
    // Save updated balance
    $balances[$userId] = $balance;
    file_put_contents($balanceFile, json_encode($balances, JSON_PRETTY_PRINT));
}

/**
 * Example: Connect to database
 * 
 * @return PDO Database connection
 */
function connectDatabase(): PDO {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        logMessage("Database connection failed: " . $e->getMessage(), 'error');
        throw new Exception("Database connection failed");
    }
}

?>
