<?php
/**
 * Payment Completion Page
 * Shown after user returns from WPay payment gateway
 */
require_once 'session_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once 'db_helper.php';
require_once 'settings_helper.php';

// Load site settings
$siteSettings = SiteSettings::load();
$casinoName = $siteSettings['casino_name'] ?? 'Paldo88';
$themeColor = $siteSettings['theme_color'] ?? '#6366f1';

$userModel = new User();
$currentUser = $userModel->getById($_SESSION['user_id']);
$balance = $userModel->getBalance($_SESSION['user_id']);
$userCurrency = $currentUser['currency'] ?? 'PHP';

$db = Database::getInstance();
$pdo = $db->getConnection();

// Check for recent successful deposits (last 60 seconds)
$stmt = $pdo->prepare("
    SELECT out_trade_no, amount, status FROM payment_transactions 
    WHERE user_id = ? AND status IN ('processing', 'completed') 
    AND created_at >= (NOW() - INTERVAL 60 SECOND)
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$recentDeposit = $stmt->fetch(PDO::FETCH_ASSOC);

$depositAmount = $recentDeposit['amount'] ?? 0;
$depositStatus = $recentDeposit['status'] ?? 'pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($casinoName); ?> - Payment Complete</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #fff;
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
        
        .success-card {
            background: #1e293b;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(16, 185, 129, 0.3);
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .success-title {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #10b981;
        }
        
        .success-subtitle {
            font-size: 16px;
            color: #94a3b8;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .deposit-info {
            background: rgba(16, 185, 129, 0.1);
            border: 2px solid rgba(16, 185, 129, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .info-row:last-child {
            margin-bottom: 0;
        }
        
        .info-label {
            color: #cbd5e1;
            font-size: 14px;
        }
        
        .info-value {
            font-size: 18px;
            font-weight: 600;
            color: #10b981;
        }
        
        .balance-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .balance-label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .balance-amount {
            font-size: 28px;
            font-weight: bold;
            color: #fff;
        }
        
        /* Floating Homepage Button */
        .floating-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 16px 24px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            text-align: center;
        }
        
        .floating-button:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.6);
        }
        
        .floating-button:active {
            transform: translateY(-2px);
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.5);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #10b981;
            margin-top: 10px;
        }
        
        @media (max-width: 600px) {
            .success-card {
                padding: 30px 20px;
            }
            
            .success-title {
                font-size: 24px;
            }
            
            .floating-button {
                bottom: 20px;
                right: 20px;
                padding: 12px 18px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-card">
            <div class="success-icon">✓</div>
            
            <h1 class="success-title">Payment Successful!</h1>
            <p class="success-subtitle">Your deposit has been processed and added to your account.</p>
            
            <?php if ($depositAmount > 0): ?>
                <div class="deposit-info">
                    <div class="info-row">
                        <span class="info-label">Deposit Amount</span>
                        <span class="info-value">₱<?php echo number_format($depositAmount, 2); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="info-value"><?php echo ucfirst($depositStatus); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="balance-display">
                <div class="balance-label">Current Balance</div>
                <div class="balance-amount"><?php echo formatCurrency($balance, $userCurrency); ?></div>
            </div>
            
            <p style="color: #94a3b8; font-size: 14px;">
                You can now enjoy your balance. Click the button below to return home.
            </p>
            
            <div class="status-badge">Processing</div>
        </div>
    </div>
    
    <!-- Floating Homepage Button -->
    <a href="index.php" class="floating-button">
        <span>🏠</span>
        <span>Home</span>
    </a>
</body>
</html>
