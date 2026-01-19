<?php
require_once 'session_config.php';

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

require_once 'wpay_config.php';
require_once 'wpay_helper.php';
require_once 'db_helper.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// Get WPay Statistics
$today = date('Y-m-d');
$thisMonth = date('Y-m-01');

// Deposits today
$stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM payment_transactions WHERE DATE(created_at) = ? AND status != 'failed'");
$stmt->execute([$today]);
$depositsToday = $stmt->fetch(PDO::FETCH_ASSOC);

// Deposits this month
$stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM payment_transactions WHERE created_at >= ? AND status != 'failed'");
$stmt->execute([$thisMonth]);
$depositsMonth = $stmt->fetch(PDO::FETCH_ASSOC);

// Withdrawals today
$stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM withdrawal_transactions WHERE DATE(created_at) = ? AND status != 'failed'");
$stmt->execute([$today]);
$withdrawalsToday = $stmt->fetch(PDO::FETCH_ASSOC);

// Withdrawals this month
$stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM withdrawal_transactions WHERE created_at >= ? AND status != 'failed'");
$stmt->execute([$thisMonth]);
$withdrawalsMonth = $stmt->fetch(PDO::FETCH_ASSOC);

// Total fees collected
$stmt = $pdo->prepare("SELECT SUM(collection_fee) as collection, SUM(processing_fee) as processing FROM payment_transactions WHERE created_at >= ?");
$stmt->execute([$thisMonth]);
$depositFees = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT SUM(collection_fee) as collection, SUM(processing_fee) as processing FROM withdrawal_transactions WHERE created_at >= ?");
$stmt->execute([$thisMonth]);
$withdrawalFees = $stmt->fetch(PDO::FETCH_ASSOC);

// Recent transactions
$stmt = $pdo->prepare("
    SELECT 'deposit' as type, user_id, amount, collection_fee, processing_fee, status, created_at, 
           out_trade_no as order_no, pay_type, NULL as account
    FROM payment_transactions 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    UNION ALL
    SELECT 'withdrawal' as type, user_id, amount, collection_fee, processing_fee, status, created_at,
           out_trade_no as order_no, pay_type, account
    FROM withdrawal_transactions
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC
    LIMIT 15
");
$stmt->execute();
$recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Environment info
$env = defined('WPAY_ENV') ? WPAY_ENV : 'unknown';
$mchId = defined('WPAY_MCH_ID') ? WPAY_MCH_ID : 'not set';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WPay Dashboard - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        h1 {
            color: #2d3748;
            font-size: 32px;
        }
        
        .env-badge {
            background: <?php echo $env === 'production' ? '#ef4444' : '#3b82f6'; ?>;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        
        .stat-card.deposit {
            border-left-color: #10b981;
        }
        
        .stat-card.withdrawal {
            border-left-color: #f59e0b;
        }
        
        .stat-card.fees {
            border-left-color: #8b5cf6;
        }
        
        .stat-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 4px;
        }
        
        .stat-detail {
            font-size: 12px;
            color: #a0aec0;
        }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .section h2 {
            font-size: 20px;
            color: #2d3748;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            font-size: 12px;
            text-transform: uppercase;
            border-bottom: 2px solid #e2e8f0;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }
        
        tr:hover {
            background: #f7fafc;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-pending {
            background: #fef3c7;
            color: #78350f;
        }
        
        .badge-failed {
            background: #fee2e2;
            color: #7f1d1d;
        }
        
        .badge-deposit {
            background: #dbeafe;
            color: #0c4a6e;
        }
        
        .badge-withdrawal {
            background: #fce7f3;
            color: #831843;
        }
        
        .amount {
            font-weight: 600;
            color: #2d3748;
        }
        
        .info-box {
            background: #eff6ff;
            border-left: 3px solid #3b82f6;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #1e40af;
        }
        
        .link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .link:hover {
            text-decoration: underline;
        }
        
        .back-btn {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            display: inline-block;
            margin-top: 20px;
        }
        
        .back-btn:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>💳 WPay Dashboard</h1>
                <p style="color: #718096; font-size: 14px; margin-top: 5px;">Payment Gateway Analytics</p>
            </div>
            <span class="env-badge"><?php echo strtoupper($env); ?></span>
        </div>
        
        <div class="info-box">
            <strong>📊 Merchant ID:</strong> <?php echo htmlspecialchars($mchId); ?> | 
            <strong>🌍 Environment:</strong> <?php echo htmlspecialchars($env); ?> | 
            <strong>ℹ️ Fee Structure:</strong> 1.6% collection + ₱<?php echo WPAY_PROCESSING_FEE; ?> processing (withdrawals)
        </div>
        
        <h2 style="color: #2d3748; margin-bottom: 15px; font-size: 18px;">📈 Today's Statistics</h2>
        <div class="stats-grid">
            <div class="stat-card deposit">
                <div class="stat-label">💰 Deposits Today</div>
                <div class="stat-value">₱<?php echo number_format($depositsToday['total'] ?? 0, 2); ?></div>
                <div class="stat-detail"><?php echo ($depositsToday['count'] ?? 0); ?> transactions</div>
            </div>
            
            <div class="stat-card withdrawal">
                <div class="stat-label">💸 Withdrawals Today</div>
                <div class="stat-value">₱<?php echo number_format($withdrawalsToday['total'] ?? 0, 2); ?></div>
                <div class="stat-detail"><?php echo ($withdrawalsToday['count'] ?? 0); ?> transactions</div>
            </div>
            
            <div class="stat-card fees">
                <div class="stat-label">💵 Fees Collected Today</div>
                <div class="stat-value">₱<?php 
                    $todayDepositFees = $pdo->query("SELECT SUM(collection_fee + processing_fee) as total FROM payment_transactions WHERE DATE(created_at) = '$today' AND status != 'failed'")->fetch()['total'] ?? 0;
                    $todayWithdrawalFees = $pdo->query("SELECT SUM(collection_fee + processing_fee) as total FROM withdrawal_transactions WHERE DATE(created_at) = '$today' AND status != 'failed'")->fetch()['total'] ?? 0;
                    echo number_format($todayDepositFees + $todayWithdrawalFees, 2);
                ?></div>
                <div class="stat-detail">Admin revenue (no user charge)</div>
            </div>
        </div>
        
        <h2 style="color: #2d3748; margin-bottom: 15px; font-size: 18px;">📅 This Month's Statistics</h2>
        <div class="stats-grid">
            <div class="stat-card deposit">
                <div class="stat-label">💰 Deposits This Month</div>
                <div class="stat-value">₱<?php echo number_format($depositsMonth['total'] ?? 0, 2); ?></div>
                <div class="stat-detail"><?php echo ($depositsMonth['count'] ?? 0); ?> transactions</div>
            </div>
            
            <div class="stat-card withdrawal">
                <div class="stat-label">💸 Withdrawals This Month</div>
                <div class="stat-value">₱<?php echo number_format($withdrawalsMonth['total'] ?? 0, 2); ?></div>
                <div class="stat-detail"><?php echo ($withdrawalsMonth['count'] ?? 0); ?> transactions</div>
            </div>
            
            <div class="stat-card fees">
                <div class="stat-label">💵 Total Fees This Month</div>
                <div class="stat-value">₱<?php 
                    $totalCollectionFees = ($depositFees['collection'] ?? 0) + ($withdrawalFees['collection'] ?? 0);
                    $totalProcessingFees = ($depositFees['processing'] ?? 0) + ($withdrawalFees['processing'] ?? 0);
                    $totalFees = $totalCollectionFees + $totalProcessingFees;
                    echo number_format($totalFees, 2);
                ?></div>
                <div class="stat-detail">₱<?php echo number_format($totalCollectionFees, 2); ?> + ₱<?php echo number_format($totalProcessingFees, 2); ?></div>
            </div>
        </div>
        
        <div class="section">
            <h2>📋 Recent Transactions (Last 24 Hours)</h2>
            <?php if (!empty($recentTransactions)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Order #</th>
                        <th>Amount</th>
                        <th>Fees</th>
                        <th>Status</th>
                        <th>Details</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTransactions as $txn): ?>
                    <tr>
                        <td>
                            <span class="badge badge-<?php echo $txn['type']; ?>">
                                <?php echo $txn['type'] === 'deposit' ? '📥 Deposit' : '📤 Withdrawal'; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($txn['order_no']); ?></td>
                        <td class="amount">₱<?php echo number_format($txn['amount'], 2); ?></td>
                        <td>₱<?php echo number_format(($txn['collection_fee'] ?? 0) + ($txn['processing_fee'] ?? 0), 2); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $txn['status']; ?>">
                                <?php echo strtoupper($txn['status']); ?>
                            </span>
                        </td>
                        <td>
                            <small>
                                <?php echo htmlspecialchars($txn['pay_type']); ?>
                                <?php if ($txn['account']): ?>
                                    - <?php echo htmlspecialchars(substr($txn['account'], 0, 8)); ?>...
                                <?php endif; ?>
                            </small>
                        </td>
                        <td style="font-size: 12px; color: #a0aec0;">
                            <?php echo date('M d, h:i A', strtotime($txn['created_at'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color: #718096; padding: 20px; text-align: center;">No transactions in the last 24 hours</p>
            <?php endif; ?>
        </div>
        
        <a href="dashboard.php" class="back-btn">← Back to Admin Dashboard</a>
    </div>
</body>
</html>
