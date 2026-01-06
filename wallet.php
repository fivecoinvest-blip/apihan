<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'db_helper.php';
require_once 'redis_helper.php';
require_once 'currency_helper.php';
require_once 'settings_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Load site settings
$casinoName = SiteSettings::get('casino_name', 'Casino PHP');
$themeColor = SiteSettings::get('theme_color', '#6366f1');

$userModel = new User();
$currentUser = $userModel->getById($_SESSION['user_id']);
$balance = $userModel->getBalance($_SESSION['user_id']);
$userCurrency = $currentUser['currency'] ?? 'PHP';
$username = $currentUser['username'] ?? '';

$db = Database::getInstance();
$pdo = $db->getConnection();

// Handle success/error messages
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);

// Handle deposit request
if (isset($_POST['deposit'])) {
    $amount = floatval($_POST['amount']);
    $method = $_POST['method'] ?? 'bank';
    
    if ($amount < 100) {
        $_SESSION['error'] = 'Minimum deposit amount is ' . formatCurrency(100, $userCurrency);
    } else {
        // Create pending deposit transaction
        $stmt = $pdo->prepare("
            INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, description, status, created_at) 
            VALUES (?, 'deposit', ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $amount,
            $balance,
            $balance,
            "Deposit request via {$method}"
        ]);
        
        $_SESSION['success'] = 'Deposit request submitted! Please wait for admin approval.';
    }
    
    header('Location: wallet.php');
    exit;
}

// Handle withdrawal request
if (isset($_POST['withdraw'])) {
    $amount = floatval($_POST['amount']);
    $method = $_POST['method'] ?? 'bank';
    $account = $_POST['account'] ?? '';
    
    if ($amount < 500) {
        $_SESSION['error'] = 'Minimum withdrawal amount is ' . formatCurrency(500, $userCurrency);
    } elseif ($amount > $balance) {
        $_SESSION['error'] = 'Insufficient balance for withdrawal';
    } elseif (empty($account)) {
        $_SESSION['error'] = 'Please provide account details for withdrawal';
    } else {
        // Create pending withdrawal transaction
        $stmt = $pdo->prepare("
            INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, description, status, created_at) 
            VALUES (?, 'withdrawal', ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $amount,
            $balance,
            $balance,
            "Withdrawal request to {$account} via {$method}"
        ]);
        
        $_SESSION['success'] = 'Withdrawal request submitted! Please wait for admin processing.';
    }
    
    header('Location: wallet.php');
    exit;
}

// Get transaction history (last 50)
$stmt = $pdo->prepare("
    SELECT * FROM transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->execute([$_SESSION['user_id']]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END) as total_deposits,
        SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END) as total_withdrawals,
        SUM(CASE WHEN type = 'bet' THEN amount ELSE 0 END) as total_bets,
        SUM(CASE WHEN type = 'win' THEN amount ELSE 0 END) as total_wins
    FROM transactions 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($casinoName); ?> - Wallet</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #fff;
            min-height: 100vh;
        }
        
        /* Header */
        .header {
            background: #1e293b;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #fff;
            text-decoration: none;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .username {
            color: #94a3b8;
            font-size: 14px;
        }
        
        .balance-display {
            background: linear-gradient(135deg, #10b981, #059669);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 18px;
        }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            overflow-x: auto;
            padding-bottom: 10px;
        }
        
        .tab {
            padding: 12px 24px;
            background: #1e293b;
            border: none;
            border-radius: 8px;
            color: #94a3b8;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .tab.active {
            background: linear-gradient(135deg, <?php echo $themeColor; ?>, #4f46e5);
            color: #fff;
        }
        
        .tab:hover {
            background: #334155;
        }
        
        /* Tab Content */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Cards */
        .card {
            background: #1e293b;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .card-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #fff;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #1e293b, #334155);
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid;
        }
        
        .stat-card.deposits { border-color: #10b981; }
        .stat-card.withdrawals { border-color: #f59e0b; }
        .stat-card.bets { border-color: #ef4444; }
        .stat-card.wins { border-color: #8b5cf6; }
        
        .stat-label {
            color: #94a3b8;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #cbd5e1;
            font-size: 14px;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            color: #fff;
            font-size: 15px;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: <?php echo $themeColor; ?>;
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn {
            padding: 12px 30px;
            background: linear-gradient(135deg, <?php echo $themeColor; ?>, #4f46e5);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            width: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        /* Alerts */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid #10b981;
            color: #10b981;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
        }
        
        /* Transaction Table */
        .transaction-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .transaction-table th,
        .transaction-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #334155;
        }
        
        .transaction-table th {
            color: #94a3b8;
            font-weight: 600;
            font-size: 14px;
        }
        
        .transaction-table tbody tr:hover {
            background: #334155;
        }
        
        .type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .type-deposit { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .type-withdrawal { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .type-bet { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .type-win { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-completed { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .status-pending { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .status-failed { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        
        .amount-positive { color: #10b981; }
        .amount-negative { color: #ef4444; }
        
        /* Back Link */
        .back-link {
            display: inline-block;
            color: #94a3b8;
            text-decoration: none;
            margin-bottom: 20px;
            transition: color 0.3s;
        }
        
        .back-link:hover {
            color: #fff;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .transaction-table {
                font-size: 14px;
            }
            
            .transaction-table th,
            .transaction-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="index.php" class="logo">🎰 <?php echo htmlspecialchars($casinoName); ?></a>
        <div class="user-info">
            <span class="username"><?php echo htmlspecialchars($username); ?></span>
            <div class="balance-display"><?php echo formatCurrency($balance, $userCurrency); ?></div>
        </div>
    </div>
    
    <div class="container">
        <a href="index.php" class="back-link">← Back to Games</a>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card deposits">
                <div class="stat-label">Total Deposits</div>
                <div class="stat-value"><?php echo formatCurrency($stats['total_deposits'] ?? 0, $userCurrency); ?></div>
            </div>
            <div class="stat-card withdrawals">
                <div class="stat-label">Total Withdrawals</div>
                <div class="stat-value"><?php echo formatCurrency($stats['total_withdrawals'] ?? 0, $userCurrency); ?></div>
            </div>
            <div class="stat-card bets">
                <div class="stat-label">Total Bets</div>
                <div class="stat-value"><?php echo formatCurrency($stats['total_bets'] ?? 0, $userCurrency); ?></div>
            </div>
            <div class="stat-card wins">
                <div class="stat-label">Total Wins</div>
                <div class="stat-value"><?php echo formatCurrency($stats['total_wins'] ?? 0, $userCurrency); ?></div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('deposit')">💰 Deposit</button>
            <button class="tab" onclick="showTab('withdraw')">💸 Withdraw</button>
            <button class="tab" onclick="showTab('history')">📜 History</button>
        </div>
        
        <!-- Deposit Tab -->
        <div id="deposit-tab" class="tab-content active">
            <div class="card">
                <h2 class="card-title">Deposit Funds</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Amount (<?php echo $userCurrency; ?>)</label>
                        <input type="number" name="amount" min="100" step="0.01" required 
                               placeholder="Minimum: <?php echo formatCurrency(100, $userCurrency); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="method" required>
                            <option value="bank">Bank Transfer</option>
                            <option value="gcash">GCash</option>
                            <option value="paymaya">PayMaya</option>
                            <option value="crypto">Cryptocurrency</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="deposit" class="btn">Submit Deposit Request</button>
                </form>
                
                <div style="margin-top: 20px; padding: 15px; background: rgba(99, 102, 241, 0.1); border-radius: 8px; border-left: 4px solid <?php echo $themeColor; ?>;">
                    <strong>Note:</strong> Deposits are processed manually by admin. Please wait for approval. You will receive a notification once your deposit is confirmed.
                </div>
            </div>
        </div>
        
        <!-- Withdraw Tab -->
        <div id="withdraw-tab" class="tab-content">
            <div class="card">
                <h2 class="card-title">Withdraw Funds</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Amount (<?php echo $userCurrency; ?>)</label>
                        <input type="number" name="amount" min="500" max="<?php echo $balance; ?>" step="0.01" required 
                               placeholder="Minimum: <?php echo formatCurrency(500, $userCurrency); ?>, Available: <?php echo formatCurrency($balance, $userCurrency); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Withdrawal Method</label>
                        <select name="method" required>
                            <option value="bank">Bank Transfer</option>
                            <option value="gcash">GCash</option>
                            <option value="paymaya">PayMaya</option>
                            <option value="crypto">Cryptocurrency</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Account Details</label>
                        <textarea name="account" required placeholder="Enter your account number, name, and other required details"></textarea>
                    </div>
                    
                    <button type="submit" name="withdraw" class="btn">Submit Withdrawal Request</button>
                </form>
                
                <div style="margin-top: 20px; padding: 15px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; border-left: 4px solid #f59e0b;">
                    <strong>Note:</strong> Withdrawals are processed within 24-48 hours. Please ensure your account details are correct to avoid delays.
                </div>
            </div>
        </div>
        
        <!-- History Tab -->
        <div id="history-tab" class="tab-content">
            <div class="card">
                <h2 class="card-title">Transaction History</h2>
                <div style="overflow-x: auto;">
                    <table class="transaction-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #94a3b8; padding: 40px;">
                                        No transactions yet
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $tx): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($tx['created_at'])); ?></td>
                                        <td>
                                            <span class="type-badge type-<?php echo $tx['type']; ?>">
                                                <?php echo ucfirst($tx['type']); ?>
                                            </span>
                                        </td>
                                        <td class="<?php echo in_array($tx['type'], ['deposit', 'win']) ? 'amount-positive' : 'amount-negative'; ?>">
                                            <?php 
                                            $sign = in_array($tx['type'], ['deposit', 'win']) ? '+' : '-';
                                            echo $sign . formatCurrency($tx['amount'], $userCurrency); 
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $status = $tx['status'] ?? 'completed';
                                            ?>
                                            <span class="status-badge status-<?php echo $status; ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td style="color: #94a3b8;">
                                            <?php echo htmlspecialchars($tx['description'] ?? '-'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Deactivate all tab buttons
            document.querySelectorAll('.tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Activate clicked button
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
