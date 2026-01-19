<?php
require_once 'session_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'wpay_helper.php';
require_once 'db_helper.php';

$orderNo = $_GET['order'] ?? '';
$error = '';
$transaction = null;
$type = '';

if ($orderNo) {
    $wpay = new WPayHelper();
    
    // Determine type based on prefix
    if (substr($orderNo, 0, 1) === 'D') {
        $type = 'deposit';
        $transaction = $wpay->getTransaction($orderNo, 'deposit');
    } else {
        $type = 'withdrawal';
        $transaction = $wpay->getTransaction($orderNo, 'withdrawal');
    }
    
    if (!$transaction) {
        $error = 'Transaction not found';
    }
}

$userModel = new User();
$balance = $userModel->getBalance($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status - Casino</title>
    <meta http-equiv="refresh" content="10">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            max-width: 500px;
            width: 100%;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .status-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .status-title {
            font-size: 28px;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 10px;
        }
        
        .status-message {
            font-size: 16px;
            color: #718096;
            margin-bottom: 30px;
        }
        
        .transaction-details {
            background: #f7fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #718096;
            font-size: 14px;
        }
        
        .detail-value {
            color: #2d3748;
            font-weight: 600;
            font-size: 14px;
        }
        
        .amount {
            font-size: 36px;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 20px;
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin-bottom: 10px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        .refresh-notice {
            font-size: 13px;
            color: #a0aec0;
            margin-top: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <?php if ($error): ?>
                <div class="status-icon">❌</div>
                <div class="status-title">Error</div>
                <div class="status-message"><?php echo htmlspecialchars($error); ?></div>
                <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                
            <?php elseif ($transaction): ?>
                <?php if ($transaction['status'] === 'pending' || $transaction['status'] === 'processing'): ?>
                    <div class="status-icon">⏳</div>
                    <div class="status-title">Processing...</div>
                    <div class="status-message">
                        Your <?php echo $type; ?> is being processed. Please wait...
                    </div>
                    <div class="spinner"></div>
                    
                <?php elseif ($transaction['status'] === 'completed'): ?>
                    <div class="status-icon">✅</div>
                    <div class="status-title">Success!</div>
                    <div class="status-message">
                        Your <?php echo $type; ?> has been completed successfully
                    </div>
                    
                <?php else: ?>
                    <div class="status-icon">❌</div>
                    <div class="status-title">Failed</div>
                    <div class="status-message">
                        Your <?php echo $type; ?> could not be processed
                    </div>
                <?php endif; ?>
                
                <div class="amount">₱<?php echo number_format($transaction['amount'], 2); ?></div>
                
                <div class="transaction-details">
                    <div class="detail-row">
                        <span class="detail-label">Order Number:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($transaction['out_trade_no']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Method:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($transaction['pay_type']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value"><?php echo strtoupper($transaction['status']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date:</span>
                        <span class="detail-value"><?php echo date('M d, Y h:i A', strtotime($transaction['created_at'])); ?></span>
                    </div>
                    <?php if ($transaction['status'] === 'completed'): ?>
                    <div class="detail-row">
                        <span class="detail-label">Current Balance:</span>
                        <span class="detail-value">₱<?php echo number_format($balance, 2); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                <?php if ($type === 'deposit'): ?>
                <a href="deposit_auto.php" class="btn btn-secondary">Make Another Deposit</a>
                <?php else: ?>
                <a href="withdrawal_auto.php" class="btn btn-secondary">Make Another Withdrawal</a>
                <?php endif; ?>
                
                <?php if ($transaction['status'] === 'pending' || $transaction['status'] === 'processing'): ?>
                <div class="refresh-notice">⟳ Auto-refreshing every 10 seconds...</div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="status-icon">❓</div>
                <div class="status-title">No Transaction</div>
                <div class="status-message">No transaction ID provided</div>
                <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
