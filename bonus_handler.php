<?php
/**
 * Bonus Handler - Manages user bonuses and claims
 */
require_once 'session_config.php';
require_once 'config.php';
require_once 'db_helper.php';
require_once 'redis_helper.php';
require_once 'currency_helper.php';
require_once 'settings_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$userId = $_SESSION['user_id'];

// Create bonus tables if not exist (idempotent)
$pdo->exec("CREATE TABLE IF NOT EXISTS bonus_programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('registration', 'deposit', 'custom') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    description TEXT,
    trigger_value DECIMAL(15,2) NULL COMMENT 'Required deposit amount for deposit bonus',
    is_enabled TINYINT(1) DEFAULT 1,
    max_claims_per_user INT DEFAULT 1 COMMENT 'How many times user can claim',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS bonus_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bonus_program_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    claimed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    balance_before DECIMAL(15,2) NOT NULL,
    balance_after DECIMAL(15,2) NOT NULL,
    INDEX idx_user (user_id),
    INDEX idx_bonus (bonus_program_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bonus_program_id) REFERENCES bonus_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Handle actions
$action = $_GET['action'] ?? '';

if ($action === 'get_available') {
    // Get all enabled bonuses that user is eligible for
    $userModel = new User();
    $currentUser = $userModel->getById($userId);
    
    // Get user's registration date and total deposits
    $regDate = $currentUser['created_at'];
    $totalDepositsStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_deposits 
        FROM transactions 
        WHERE user_id = ? AND type = 'deposit' AND status = 'completed'
    ");
    $totalDepositsStmt->execute([$userId]);
    $totalDeposits = $totalDepositsStmt->fetchColumn();
    
    // Get all enabled bonuses
    $bonusesStmt = $pdo->prepare("
        SELECT bp.*, 
               COALESCE(COUNT(bc.id), 0) as times_claimed
        FROM bonus_programs bp
        LEFT JOIN bonus_claims bc ON bp.id = bc.bonus_program_id AND bc.user_id = ?
        WHERE bp.is_enabled = 1
        GROUP BY bp.id
        HAVING times_claimed < bp.max_claims_per_user
        ORDER BY bp.id ASC
    ");
    $bonusesStmt->execute([$userId]);
    $bonuses = $bonusesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $available = [];
    foreach ($bonuses as $bonus) {
        $canClaim = false;
        $reason = '';
        
        if ($bonus['type'] === 'registration') {
            // Registration bonus - check if user just registered (within 7 days)
            $daysSinceReg = (time() - strtotime($regDate)) / 86400;
            $canClaim = $daysSinceReg <= 7;
            $reason = $canClaim ? '' : 'Expired (registration bonus valid for 7 days)';
        } elseif ($bonus['type'] === 'deposit') {
            // Deposit bonus - check if user has made qualifying deposit
            $canClaim = $totalDeposits >= $bonus['trigger_value'];
            $reason = $canClaim ? '' : 'Deposit ' . formatCurrency($bonus['trigger_value'], $currentUser['currency']) . ' to unlock';
        } else {
            // Custom bonus - always available if enabled
            $canClaim = true;
        }
        
        if ($canClaim || !empty($reason)) {
            $available[] = [
                'id' => $bonus['id'],
                'name' => $bonus['name'],
                'type' => $bonus['type'],
                'amount' => $bonus['amount'],
                'description' => $bonus['description'],
                'can_claim' => $canClaim,
                'reason' => $reason,
                'times_claimed' => $bonus['times_claimed'],
                'max_claims' => $bonus['max_claims_per_user']
            ];
        }
    }
    
    echo json_encode(['bonuses' => $available]);
    exit;
}

if ($action === 'claim') {
    $bonusId = $_POST['bonus_id'] ?? 0;
    
    if (!$bonusId) {
        echo json_encode(['error' => 'Invalid bonus ID']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // Get bonus details
        $bonusStmt = $pdo->prepare("SELECT * FROM bonus_programs WHERE id = ? AND is_enabled = 1 FOR UPDATE");
        $bonusStmt->execute([$bonusId]);
        $bonus = $bonusStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bonus) {
            throw new Exception('Bonus not found or disabled');
        }
        
        // Check if user already claimed max times
        $claimsStmt = $pdo->prepare("SELECT COUNT(*) FROM bonus_claims WHERE user_id = ? AND bonus_program_id = ?");
        $claimsStmt->execute([$userId, $bonusId]);
        $timesClaimed = $claimsStmt->fetchColumn();
        
        if ($timesClaimed >= $bonus['max_claims_per_user']) {
            throw new Exception('You have already claimed this bonus the maximum number of times');
        }
        
        // Verify eligibility
        $userModel = new User();
        $currentUser = $userModel->getById($userId);
        
        if ($bonus['type'] === 'registration') {
            $daysSinceReg = (time() - strtotime($currentUser['created_at'])) / 86400;
            if ($daysSinceReg > 7) {
                throw new Exception('Registration bonus expired (valid for 7 days only)');
            }
        } elseif ($bonus['type'] === 'deposit') {
            $totalDepositsStmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as total_deposits 
                FROM transactions 
                WHERE user_id = ? AND type = 'deposit' AND status = 'completed'
            ");
            $totalDepositsStmt->execute([$userId]);
            $totalDeposits = $totalDepositsStmt->fetchColumn();
            
            if ($totalDeposits < $bonus['trigger_value']) {
                throw new Exception('You need to deposit ' . formatCurrency($bonus['trigger_value'], $currentUser['currency']) . ' to claim this bonus');
            }
        }
        
        // Get current balance directly from DB to avoid stale cache and lock row
        $balanceStmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $balanceStmt->execute([$userId]);
        $balanceRow = $balanceStmt->fetch(PDO::FETCH_ASSOC);
        $balance = $balanceRow ? (float)$balanceRow['balance'] : 0;
        $newBalance = $balance + $bonus['amount'];
        
        // Update user balance (will also refresh cache)
        $userModel->updateBalance($userId, $newBalance);
        
        // Record bonus claim
        $claimStmt = $pdo->prepare("
            INSERT INTO bonus_claims (user_id, bonus_program_id, amount, balance_before, balance_after)
            VALUES (?, ?, ?, ?, ?)
        ");
        $claimStmt->execute([$userId, $bonusId, $bonus['amount'], $balance, $newBalance]);
        
        // Create transaction record
        $transStmt = $pdo->prepare("
            INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, description, status)
            VALUES (?, 'bonus', ?, ?, ?, ?, 'completed')
        ");
        $transStmt->execute([
            $userId,
            $bonus['amount'],
            $balance,
            $newBalance,
            'Bonus claimed: ' . $bonus['name']
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'bonus_name' => $bonus['name'],
            'amount' => $bonus['amount'],
            'new_balance' => $newBalance
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        // Invalidate balance cache to avoid stale values if anything failed mid-transaction
        try {
            $cache = RedisCache::getInstance();
            $cache->delete("user:balance:{$userId}");
        } catch (Exception $cacheEx) {
            // ignore cache errors
        }
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['error' => 'Invalid action']);
