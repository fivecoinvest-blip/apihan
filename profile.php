<?php
session_start();
require_once 'config.php';
require_once 'db_helper.php';
require_once 'currency_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Get user information
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    // Get user statistics
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN type = 'bet' THEN 1 END) as total_bets_count,
            COALESCE(SUM(CASE WHEN type = 'bet' THEN amount ELSE 0 END), 0) as total_bets,
            COALESCE(SUM(CASE WHEN type = 'win' THEN amount ELSE 0 END), 0) as total_wins,
            COUNT(DISTINCT game_uid) as games_played
        FROM transactions 
        WHERE user_id = ?
    ");
    $statsStmt->execute([$userId]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent transactions
    $transStmt = $pdo->prepare("
        SELECT t.*, g.name as game_name 
        FROM transactions t
        LEFT JOIN games g ON t.game_uid = g.game_uid
        WHERE t.user_id = ?
        ORDER BY t.created_at DESC
        LIMIT 50
    ");
    $transStmt->execute([$userId]);
    $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update last login
    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$userId]);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$currency = $user['currency'] ?? 'PHP';
$netProfitLoss = $stats['total_wins'] - $stats['total_bets'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Casino</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            color: #1a1a1a;
            line-height: 1.6;
        }
        
        .header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 16px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }
        
        .header-actions {
            display: flex;
            gap: 12px;
        }
        
        .btn {
            padding: 10px 20px;
            background: #1a1a1a;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-block;
        }
        
        .btn:hover {
            background: #333;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: white;
            color: #1a1a1a;
            border: 1px solid #e5e7eb;
        }
        
        .btn-secondary:hover {
            background: #f9fafb;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .profile-header {
            background: white;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 30px;
            margin-bottom: 24px;
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: white;
            font-weight: bold;
        }
        
        .profile-details h2 {
            font-size: 24px;
            margin-bottom: 4px;
        }
        
        .profile-details p {
            color: #6b7280;
            font-size: 14px;
        }
        
        .balance-section {
            background: #f9fafb;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .balance-label {
            font-size: 14px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .balance-amount {
            font-size: 36px;
            font-weight: 700;
            color: #1a1a1a;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
        }
        
        .stat-label {
            font-size: 13px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
        }
        
        .stat-value.positive {
            color: #10b981;
        }
        
        .stat-value.negative {
            color: #ef4444;
        }
        
        .section {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section h3 {
            font-size: 18px;
            font-weight: 600;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f9fafb;
        }
        
        th {
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        td {
            padding: 16px 12px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        
        tr:hover {
            background: #f9fafb;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-bet {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-win {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-deposit {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }
        
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .profile-info {
                flex-direction: column;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 12px;
            }
            
            table {
                font-size: 12px;
            }
            
            th, td {
                padding: 8px 6px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>My Profile</h1>
            <div class="header-actions">
                <a href="index.php" class="btn btn-secondary">← Back to Games</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-info">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                </div>
                <div class="profile-details">
                    <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                    <p>📱 <?php echo htmlspecialchars($user['phone']); ?></p>
                    <p>📅 Member since <?php echo date('F d, Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>
            
            <div class="balance-section">
                <div class="balance-label">Current Balance</div>
                <div class="balance-amount"><?php echo formatCurrency($user['balance'], $currency); ?></div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Bets</div>
                <div class="stat-value"><?php echo formatCurrency($stats['total_bets'], $currency); ?></div>
                <p style="font-size: 13px; color: #9ca3af; margin-top: 4px;">
                    <?php echo number_format($stats['total_bets_count']); ?> bets placed
                </p>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Total Wins</div>
                <div class="stat-value"><?php echo formatCurrency($stats['total_wins'], $currency); ?></div>
                <p style="font-size: 13px; color: #9ca3af; margin-top: 4px;">
                    From all games
                </p>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Net Profit/Loss</div>
                <div class="stat-value <?php echo $netProfitLoss >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo formatCurrency(abs($netProfitLoss), $currency); ?>
                    <?php echo $netProfitLoss >= 0 ? '↑' : '↓'; ?>
                </div>
                <p style="font-size: 13px; color: #9ca3af; margin-top: 4px;">
                    <?php echo $netProfitLoss >= 0 ? 'Winning' : 'Losing'; ?>
                </p>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Games Played</div>
                <div class="stat-value"><?php echo $stats['games_played']; ?></div>
                <p style="font-size: 13px; color: #9ca3af; margin-top: 4px;">
                    Different games
                </p>
            </div>
        </div>

        <!-- Betting History -->
        <div class="section">
            <div class="section-header">
                <h3>Betting History</h3>
                <span style="color: #6b7280; font-size: 14px;">Last 50 transactions</span>
            </div>
            
            <?php if (empty($transactions)): ?>
                <div class="empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <p style="font-size: 16px;">No betting history yet</p>
                    <p style="font-size: 14px; margin-top: 8px;">Start playing to see your transactions here</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Game</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Balance Before</th>
                                <th>Balance After</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $trans): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($trans['created_at'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($trans['game_name'] ?? 'N/A'); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $trans['type']; ?>">
                                            <?php echo strtoupper($trans['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color: <?php echo $trans['type'] === 'win' ? '#10b981' : '#1a1a1a'; ?>">
                                            <?php echo formatCurrency($trans['amount'], $currency); ?>
                                        </strong>
                                    </td>
                                    <td><?php echo formatCurrency($trans['balance_before'], $currency); ?></td>
                                    <td><?php echo formatCurrency($trans['balance_after'], $currency); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Account Information -->
        <div class="section">
            <h3 style="margin-bottom: 20px;">Account Information</h3>
            
            <div style="display: grid; gap: 16px;">
                <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                    <span style="color: #6b7280;">Username</span>
                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                </div>
                
                <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                    <span style="color: #6b7280;">Phone Number</span>
                    <strong><?php echo htmlspecialchars($user['phone']); ?></strong>
                </div>
                
                <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                    <span style="color: #6b7280;">Currency</span>
                    <strong><?php echo $currency; ?> (<?php 
                        $symbols = ['PHP' => '₱', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥'];
                        echo $symbols[$currency] ?? $currency;
                    ?>)</strong>
                </div>
                
                <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                    <span style="color: #6b7280;">Account Status</span>
                    <strong style="color: <?php echo $user['status'] === 'active' ? '#10b981' : '#ef4444'; ?>">
                        <?php echo ucfirst($user['status']); ?>
                    </strong>
                </div>
                
                <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                    <span style="color: #6b7280;">Last Login</span>
                    <strong><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'First time'; ?></strong>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
