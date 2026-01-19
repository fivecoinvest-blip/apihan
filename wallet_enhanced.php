<?php
/**
 * Enhanced Wallet Page - Integrated Auto Deposit & Withdrawal
 * Manual deposit/withdrawal disabled
 */
require_once 'session_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once 'db_helper.php';
require_once 'wpay_config.php';
require_once 'wpay_helper.php';
require_once 'currency_helper.php';
require_once 'settings_helper.php';

// Load site settings
$siteSettings = SiteSettings::load();
$casinoName = $siteSettings['casino_name'] ?? 'Paldo88';
$themeColor = $siteSettings['theme_color'] ?? '#6366f1';

$userModel = new User();
$currentUser = $userModel->getById($_SESSION['user_id']);
$balance = $userModel->getBalance($_SESSION['user_id']);
$userCurrency = $currentUser['currency'] ?? 'PHP';
$username = $currentUser['username'] ?? '';
$phoneDisplay = $userModel->formatPhoneDisplay($currentUser['phone'] ?? '');

$db = Database::getInstance();
$pdo = $db->getConnection();

$error = '';
$success = '';
$paymentUrl = '';

// Handle Auto Deposit
if (isset($_POST['auto_deposit'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $payType = trim($_POST['pay_type'] ?? '');
    
    if ($amount < WPAY_MIN_DEPOSIT) {
        $error = "Minimum deposit is ₱" . number_format(WPAY_MIN_DEPOSIT);
    } elseif ($amount > WPAY_MAX_DEPOSIT) {
        $error = "Maximum deposit is ₱" . number_format(WPAY_MAX_DEPOSIT);
    } elseif (empty($payType) || !in_array($payType, ['GCASH', 'MAYA', 'QR'])) {
        $error = "Please select a valid payment method";
    } else {
        $wpay = new WPayHelper();
        $result = $wpay->createPayIn($_SESSION['user_id'], $amount, $payType);
        
        if ($result['success']) {
            $paymentUrl = $result['payment_url'] ?? null;
            $success = "Deposit created! Redirecting to payment...";
            
            if ($paymentUrl) {
                echo "<script>setTimeout(() => { window.location.href = '$paymentUrl'; }, 1500);</script>";
            }
        } else {
            $error = $result['error'] ?? 'Deposit failed';
        }
    }
}

// Handle Auto Withdrawal
if (isset($_POST['auto_withdrawal'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $payType = trim($_POST['pay_type'] ?? '');
    $account = trim($_POST['account'] ?? '');
    $accountName = trim($_POST['account_name'] ?? '');
    
    // Normalize phone number format: +639XXXXXXXXX -> 09XXXXXXXXX
    if (preg_match('/^\+63(\d{10})$/', $account, $matches)) {
        $account = '0' . $matches[1];
    }
    
    if ($amount < WPAY_MIN_WITHDRAWAL) {
        $error = "Minimum withdrawal is ₱" . number_format(WPAY_MIN_WITHDRAWAL);
    } elseif ($amount > WPAY_MAX_WITHDRAWAL) {
        $error = "Maximum withdrawal is ₱" . number_format(WPAY_MAX_WITHDRAWAL);
    } elseif ($amount > $balance) {
        $error = "Insufficient balance. You have ₱" . number_format($balance, 2);
    } elseif (empty($account)) {
        $error = 'Please enter your account/phone number';
    } elseif (empty($accountName)) {
        $error = 'Please enter the account holder name';
    } elseif (empty($payType) || !in_array($payType, ['GCASH', 'MAYA'])) {
        $error = 'Please select a valid payment method';
    } else {
        $wpay = new WPayHelper();
        $result = $wpay->createPayOut($_SESSION['user_id'], $amount, $payType, $account, $accountName);
        
        if ($result['success']) {
            $success = $result['message'] ?? 'Withdrawal submitted successfully!';
            $balance = $userModel->getBalance($_SESSION['user_id']);
        } else {
            $error = $result['error'] ?? 'Withdrawal failed';
        }
    }
}

// Get transaction history (last 50) - Auto deposits and withdrawals
$stmt = $pdo->prepare("
    (SELECT 
        'deposit' as type,
        amount,
        status,
        created_at,
        CONCAT('Auto Deposit via ', pay_type, ' - Order: ', out_trade_no) as description
    FROM payment_transactions 
    WHERE user_id = ?)
    UNION ALL
    (SELECT 
        'withdrawal' as type,
        amount,
        status,
        created_at,
        CONCAT('Auto Withdrawal via ', pay_type, ' to ', account, ' - Order: ', out_trade_no) as description
    FROM withdrawal_transactions 
    WHERE user_id = ?)
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
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

// Calculate user wager statistics
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END) as total_deposits,
        SUM(CASE WHEN type = 'bet' THEN amount ELSE 0 END) as total_wagered,
        SUM(CASE WHEN type = 'bonus' THEN amount ELSE 0 END) as total_bonuses
    FROM transactions 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$wagerStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get active bonuses with wager requirements
$stmt = $pdo->prepare("
    SELECT 
        bc.id,
        bc.bonus_program_id,
        bp.name,
        bc.amount as bonus_amount,
        bp.trigger_value,
        bc.claimed_at,
        CASE 
            WHEN bp.type = 'deposit' THEN bc.amount * 3
            WHEN bp.type = 'registration' THEN bc.amount * 2
            ELSE bc.amount * 2
        END as wager_requirement
    FROM bonus_claims bc
    JOIN bonus_programs bp ON bc.bonus_program_id = bp.id
    WHERE bc.user_id = ?
    AND bp.is_enabled = 1
");
$stmt->execute([$_SESSION['user_id']]);
$activeBonuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total wager requirement
$totalWagerRequired = 0;
$totalBonusAmount = 0;
foreach ($activeBonuses as $bonus) {
    $totalWagerRequired += $bonus['wager_requirement'];
    $totalBonusAmount += $bonus['bonus_amount'];
}

// Get wagering progress
$totalDeposits = floatval($wagerStats['total_deposits'] ?? 0);
$totalWagered = floatval($wagerStats['total_wagered'] ?? 0);
$minWagerRequirement = $totalDeposits * 1; // 1x deposit wager requirement
$totalRequiredWager = max($minWagerRequirement, $totalWagerRequired);
$wagerProgress = $totalRequiredWager > 0 ? ($totalWagered / $totalRequiredWager) * 100 : 100;
$wagerProgress = min($wagerProgress, 100);
$canWithdraw = $totalWagered >= $totalRequiredWager;

// Get active tab from URL parameter
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'deposit';
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
        }
        
        .back-link {
            color: #94a3b8;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .back-link:hover {
            background: #334155;
            color: #fff;
        }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
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
            text-decoration: none;
            display: inline-block;
        }
        
        .tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
        }
        
        .tab.withdrawal-tab.active {
            background: linear-gradient(135deg, #f093fb, #f5576c);
        }
        
        .tab:hover:not(.active) {
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
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .card-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #fff;
        }
        
        .card-subtitle {
            color: #94a3b8;
            margin-bottom: 25px;
            font-size: 14px;
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
            font-weight: 500;
        }
        
        input, select {
            width: 100%;
            padding: 14px;
            background: #0f172a;
            border: 2px solid #334155;
            border-radius: 10px;
            color: #fff;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        /* Quick Amount Buttons */
        .quick-amounts {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .quick-amount {
            padding: 12px;
            background: #334155;
            border: 2px solid transparent;
            border-radius: 10px;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .quick-amount:hover {
            background: #475569;
            transform: translateY(-2px);
        }
        
        .quick-amount.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: #667eea;
        }
        
        /* Payment Methods */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .payment-method {
            position: relative;
        }
        
        .payment-method input[type="radio"] {
            display: none;
        }
        
        .payment-method label {
            display: block;
            padding: 16px;
            background: #334155;
            border: 2px solid transparent;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            font-size: 16px;
        }
        
        .payment-method input[type="radio"]:checked + label {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .payment-method label:hover {
            transform: translateY(-2px);
            background: #475569;
        }
        
        .payment-method input[type="radio"]:checked + label:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        /* Buttons */
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-withdrawal {
            background: linear-gradient(135deg, #f093fb, #f5576c);
        }
        
        .btn-withdrawal:hover {
            box-shadow: 0 8px 20px rgba(240, 147, 251, 0.4);
        }
        
        .btn-disabled {
            background: #334155;
            color: #64748b;
            cursor: not-allowed;
            opacity: 0.5;
        }
        
        .btn-disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        /* Alerts */
        .alert {
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 2px solid #10b981;
            color: #10b981;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 2px solid #ef4444;
            color: #ef4444;
        }
        
        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            border: 2px solid #3b82f6;
            color: #3b82f6;
        }
        
        /* Transaction Table */
        .transaction-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .transaction-table th,
        .transaction-table td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #334155;
        }
        
        .transaction-table th {
            color: #94a3b8;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .transaction-table tbody tr {
            transition: all 0.2s;
        }
        
        .transaction-table tbody tr:hover {
            background: #334155;
        }
        
        .type-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .type-deposit { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .type-withdrawal { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .type-bet { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .type-win { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-completed { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .status-pending { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .status-failed { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .status-processing { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        
        .amount-positive { color: #10b981; font-weight: 600; }
        .amount-negative { color: #ef4444; font-weight: 600; }
        
        /* Info Box */
        .info-box {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #94a3b8;
        }
        
        .info-box strong {
            color: #fff;
        }
        
        /* Manual Options Disabled Notice */
        .disabled-notice {
            background: #334155;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            color: #64748b;
        }
        
        .disabled-notice h3 {
            margin-bottom: 10px;
            color: #94a3b8;
        }
        
        /* Custom Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal {
            background: #1e293b;
            border-radius: 15px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .modal-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #f093fb, #f5576c);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: bold;
            color: #fff;
        }
        
        .modal-body {
            margin-bottom: 25px;
        }
        
        .modal-info {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .modal-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .modal-info-row:last-child {
            margin-bottom: 0;
        }
        
        .modal-info-label {
            color: #94a3b8;
        }
        
        .modal-info-value {
            color: #fff;
            font-weight: 600;
        }
        
        .modal-amount {
            font-size: 32px;
            font-weight: bold;
            color: #f093fb;
            text-align: center;
            margin: 20px 0;
        }
        
        .modal-footer {
            display: flex;
            gap: 10px;
        }
        
        .modal-btn {
            flex: 1;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .modal-btn-cancel {
            background: #334155;
            color: #fff;
        }
        
        .modal-btn-cancel:hover {
            background: #475569;
            transform: translateY(-2px);
        }
        
        .modal-btn-confirm {
            background: linear-gradient(135deg, #f093fb, #f5576c);
            color: #fff;
        }
        
        .modal-btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(240, 147, 251, 0.4);
        }
        
        /* Wager Progress */
        .wager-section {
            background: linear-gradient(135deg, #1e293b, #334155);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #f59e0b;
        }
        
        .wager-title {
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .wager-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .wager-stat {
            background: rgba(0, 0, 0, 0.3);
            padding: 12px;
            border-radius: 8px;
            text-align: center;
        }
        
        .wager-stat-label {
            font-size: 12px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .wager-stat-value {
            font-size: 18px;
            font-weight: bold;
            color: #fff;
        }
        
        .progress-container {
            margin-bottom: 15px;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .progress-label-text {
            color: #cbd5e1;
            font-weight: 500;
        }
        
        .progress-label-percent {
            color: #10b981;
            font-weight: 600;
        }
        
        .progress-bar-bg {
            background: #0f172a;
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            border: 2px solid #334155;
        }
        
        .progress-bar-fill {
            background: linear-gradient(90deg, #f59e0b, #10b981);
            height: 100%;
            border-radius: 8px;
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 11px;
            font-weight: 600;
        }
        
        .progress-bar-fill.complete {
            background: linear-gradient(90deg, #10b981, #059669);
        }
        
        .bonus-list {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 15px;
        }
        
        .bonus-item {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
        }
        
        .bonus-item:last-child {
            border-bottom: none;
        }
        
        .bonus-name {
            color: #cbd5e1;
        }
        
        .bonus-amount {
            color: #10b981;
            font-weight: 600;
        }
        
        .wager-warning {
            background: rgba(245, 158, 11, 0.1);
            border-left: 4px solid #f59e0b;
            padding: 12px;
            border-radius: 8px;
            color: #fbbf24;
            font-size: 13px;
            margin-top: 15px;
        }
        
        .wager-success {
            background: rgba(16, 185, 129, 0.1);
            border-left: 4px solid #10b981;
            padding: 12px;
            border-radius: 8px;
            color: #10b981;
            font-size: 13px;
            margin-top: 15px;
        }
        
        .withdrawal-disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .quick-amounts {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .payment-methods {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .transaction-table {
                font-size: 13px;
            }
            
            .transaction-table th,
            .transaction-table td {
                padding: 10px 8px;
            }
        }
        
        /* Balance Wrapper and Dropdown */
        .balance-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .balance {
            cursor: pointer;
            background: linear-gradient(135deg, #10b981, #059669);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .balance:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .balance-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 10px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            min-width: 220px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            overflow: hidden;
        }

        .balance-dropdown.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .balance-dropdown a {
            display: block;
            padding: 12px 16px;
            color: white;
            text-decoration: none;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .balance-dropdown a:first-child {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .balance-dropdown a:last-child {
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        .balance-dropdown a:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .balance-dropdown-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 0;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <a href="index.php" class="logo">🎰 <?php echo htmlspecialchars($casinoName); ?></a>
        <div class="user-info">
            <div class="balance-wrapper">
                <div class="balance" id="balance-trigger" onclick="toggleBalanceDropdown()">
                    💰 <?php echo formatCurrency($balance, $userCurrency); ?>
                </div>
                <div class="balance-dropdown" id="balance-dropdown">
                    <a href="wallet.php?tab=deposit" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600;">
                        <span>⚡ Auto Deposit</span>
                    </a>
                    <a href="wallet.php?tab=withdrawal" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; font-weight: 600;">
                        <span>⚡ Auto Withdrawal</span>
                    </a>
                    <div class="balance-dropdown-divider"></div>
                    <a href="profile.php">
                        <span>Profile</span>
                    </a>
                    <a href="wallet.php">
                        <span>Wallet</span>
                    </a>
                    <a href="logout.php">
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Tabs -->
        <div class="tabs">
            <a href="?tab=deposit" class="tab <?php echo $activeTab === 'deposit' ? 'active' : ''; ?>">
                ⚡ Auto Deposit
            </a>
            <a href="?tab=withdrawal" class="tab withdrawal-tab <?php echo $activeTab === 'withdrawal' ? 'active' : ''; ?>">
                ⚡ Auto Withdrawal
            </a>
            <a href="?tab=history" class="tab <?php echo $activeTab === 'history' ? 'active' : ''; ?>">
                📜 Transaction History
            </a>
        </div>
        
        <!-- Alert Messages -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">✓ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">✗ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Auto Deposit Tab -->
        <div class="tab-content <?php echo $activeTab === 'deposit' ? 'active' : ''; ?>">
            <div class="card">
                <h2 class="card-title">⚡ Auto Deposit</h2>
                <p class="card-subtitle">Instant deposit with GCash, Maya, or QR code</p>
                
                <div class="info-box">
                    <strong>Processing Time:</strong> Instant (1-2 minutes)<br>
                    <strong>Min Amount:</strong> ₱<?php echo number_format(WPAY_MIN_DEPOSIT); ?> | 
                    <strong>Max Amount:</strong> ₱<?php echo number_format(WPAY_MAX_DEPOSIT); ?>
                </div>
                
                <form method="POST" id="depositForm">
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="number" name="amount" id="depositAmount" placeholder="Enter amount" required min="<?php echo WPAY_MIN_DEPOSIT; ?>" max="<?php echo WPAY_MAX_DEPOSIT; ?>" step="0.01">
                    </div>
                    
                    <div class="form-group">
                        <label>Quick Select</label>
                        <div class="quick-amounts">
                            <div class="quick-amount" data-amount="100">₱100</div>
                            <div class="quick-amount" data-amount="500">₱500</div>
                            <div class="quick-amount" data-amount="1000">₱1,000</div>
                            <div class="quick-amount" data-amount="5000">₱5,000</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Payment Method</label>
                        <div class="payment-methods">
                            <div class="payment-method">
                                <input type="radio" name="pay_type" value="GCASH" id="gcash_deposit" required>
                                <label for="gcash_deposit">GCash</label>
                            </div>
                            <div class="payment-method">
                                <input type="radio" name="pay_type" value="MAYA" id="maya_deposit">
                                <label for="maya_deposit">Maya</label>
                            </div>
                            <div class="payment-method">
                                <input type="radio" name="pay_type" value="QR" id="qr_deposit">
                                <label for="qr_deposit">QR Code</label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="auto_deposit" class="btn">
                        Proceed to Payment →
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Auto Withdrawal Tab -->
        <div class="tab-content <?php echo $activeTab === 'withdrawal' ? 'active' : ''; ?>">
            <div class="card">
                <h2 class="card-title">⚡ Auto Withdrawal</h2>
                <p class="card-subtitle">Fast withdrawal to your GCash or Maya account</p>
                
                <!-- Wager Requirements Section -->
                <div class="wager-section">
                    <div class="wager-title">
                        📊 Wagering Progress
                    </div>
                    
                    <div class="wager-stats">
                        <div class="wager-stat">
                            <div class="wager-stat-label">Total Deposited</div>
                            <div class="wager-stat-value">₱<?php echo number_format($totalDeposits, 2); ?></div>
                        </div>
                        <div class="wager-stat">
                            <div class="wager-stat-label">Total Wagered</div>
                            <div class="wager-stat-value">₱<?php echo number_format($totalWagered, 2); ?></div>
                        </div>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-label">
                            <span class="progress-label-text">Wager Requirement: ₱<?php echo number_format($totalRequiredWager, 2); ?></span>
                            <span class="progress-label-percent"><?php echo number_format($wagerProgress, 1); ?>%</span>
                        </div>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill <?php echo $canWithdraw ? 'complete' : ''; ?>" style="width: <?php echo $wagerProgress; ?>%">
                                <?php if ($wagerProgress >= 5) echo number_format($wagerProgress, 0) . '%'; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($activeBonuses)): ?>
                        <div class="bonus-list">
                            <strong style="color: #fff; font-size: 13px;">Active Bonuses:</strong>
                            <?php foreach ($activeBonuses as $bonus): ?>
                                <div class="bonus-item">
                                    <span class="bonus-name"><?php echo htmlspecialchars($bonus['name']); ?></span>
                                    <span class="bonus-amount">+₱<?php echo number_format($bonus['bonus_amount'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($canWithdraw): ?>
                        <div class="wager-success">
                            ✓ You've completed the wagering requirement! You can now withdraw.
                        </div>
                    <?php else: ?>
                        <div class="wager-warning">
                            ⚠️ You need to wager ₱<?php echo number_format($totalRequiredWager - $totalWagered, 2); ?> more before you can withdraw.
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="info-box">
                    <strong>Processing Time:</strong> 5-30 minutes<br>
                    <strong>Min Amount:</strong> ₱<?php echo number_format(WPAY_MIN_WITHDRAWAL); ?> | 
                    <strong>Max Amount:</strong> ₱<?php echo number_format(WPAY_MAX_WITHDRAWAL); ?>
                </div>
                
                <div class="alert alert-info">
                    ⚠️ <strong>Account Number:</strong> Withdrawals will be sent to your registered phone number (<?php echo htmlspecialchars($currentUser['phone'] ?? 'Not set'); ?>). To change your withdrawal account, please contact support.
                </div>
                
                <form method="POST" id="withdrawalForm" action="">
                    <input type="hidden" name="confirmed" id="withdrawalConfirmed" value="0">
                    <div class="form-group">
                        <label>Amount (Available: ₱<?php echo number_format($balance, 2); ?>)</label>
                        <input type="number" name="amount" id="withdrawalAmount" placeholder="Enter amount" required min="<?php echo WPAY_MIN_WITHDRAWAL; ?>" max="<?php echo min($balance, WPAY_MAX_WITHDRAWAL); ?>" step="0.01" <?php echo !$canWithdraw ? 'disabled' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>Quick Select</label>
                        <div class="quick-amounts">
                            <div class="quick-amount" data-amount="100" <?php echo !$canWithdraw ? 'style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>₱100</div>
                            <div class="quick-amount" data-amount="500" <?php echo !$canWithdraw ? 'style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>₱500</div>
                            <div class="quick-amount" data-amount="1000" <?php echo !$canWithdraw ? 'style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>₱1,000</div>
                            <div class="quick-amount" data-amount="<?php echo floor($balance); ?>" <?php echo !$canWithdraw ? 'style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>All</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Payment Method</label>
                        <div class="payment-methods">
                            <div class="payment-method">
                                <input type="radio" name="pay_type" value="GCASH" id="gcash_withdrawal" required <?php echo !$canWithdraw ? 'disabled' : ''; ?>>
                                <label for="gcash_withdrawal">GCash</label>
                            </div>
                            <div class="payment-method">
                                <input type="radio" name="pay_type" value="MAYA" id="maya_withdrawal" <?php echo !$canWithdraw ? 'disabled' : ''; ?>>
                                <label for="maya_withdrawal">Maya</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Account Number / Phone Number (Read-only)</label>
                        <input type="text" name="account" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>" readonly required title="Your registered phone number" style="background: #1e293b; cursor: not-allowed; opacity: 0.7;">
                    </div>
                    
                    <div class="form-group">
                        <label>Account Holder Name</label>
                        <input type="text" name="account_name" placeholder="Juan Dela Cruz" required <?php echo !$canWithdraw ? 'disabled' : ''; ?>>
                    </div>
                    
                    <button type="submit" name="auto_withdrawal" class="btn btn-withdrawal" <?php echo !$canWithdraw ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                        Submit Withdrawal →
                    </button>
                    
                    <?php if (!$canWithdraw): ?>
                        <div style="margin-top: 15px; padding: 15px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; color: #fbbf24; font-size: 13px; text-align: center;">
                            <strong>⚠️ Withdrawal Locked</strong><br>
                            Complete your wagering requirement to unlock withdrawal
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Transaction History Tab -->
        <div class="tab-content <?php echo $activeTab === 'history' ? 'active' : ''; ?>">
            <div class="card">
                <h2 class="card-title">📜 Transaction History</h2>
                <p class="card-subtitle">Your recent transactions (last 50)</p>
                
                <?php if (empty($transactions)): ?>
                    <div class="disabled-notice">
                        <p>No transactions yet</p>
                    </div>
                <?php else: ?>
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
                                <?php foreach ($transactions as $tx): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($tx['created_at'])); ?></td>
                                        <td>
                                            <span class="type-badge type-<?php echo htmlspecialchars($tx['type']); ?>">
                                                <?php echo ucfirst($tx['type']); ?>
                                            </span>
                                        </td>
                                        <td class="<?php echo in_array($tx['type'], ['deposit', 'win']) ? 'amount-positive' : 'amount-negative'; ?>">
                                            <?php echo in_array($tx['type'], ['deposit', 'win']) ? '+' : '-'; ?>₱<?php echo number_format($tx['amount'], 2); ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($tx['status']); ?>">
                                                <?php echo ucfirst($tx['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(substr($tx['description'] ?? '', 0, 50)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Withdrawal Confirmation Modal -->
    <div id="withdrawalModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-icon">⚠️</div>
                <div class="modal-title">Confirm Withdrawal</div>
            </div>
            <div class="modal-body">
                <p style="color: #cbd5e1; margin-bottom: 15px;">Please confirm your withdrawal details:</p>
                <div class="modal-amount" id="modalAmount">₱0.00</div>
                <div class="modal-info">
                    <div class="modal-info-row">
                        <span class="modal-info-label">Payment Method:</span>
                        <span class="modal-info-value" id="modalPayType">-</span>
                    </div>
                    <div class="modal-info-row">
                        <span class="modal-info-label">Account Number:</span>
                        <span class="modal-info-value" id="modalAccount">-</span>
                    </div>
                    <div class="modal-info-row">
                        <span class="modal-info-label">Processing Time:</span>
                        <span class="modal-info-value">5-30 minutes</span>
                    </div>
                </div>
                <p style="color: #94a3b8; font-size: 13px; margin-top: 15px; text-align: center;">⚡ Balance will be deducted immediately</p>
            </div>
            <div class="modal-footer">
                <button type="button" id="cancelWithdrawal" class="modal-btn modal-btn-cancel">Cancel</button>
                <button type="button" id="confirmWithdrawal" class="modal-btn modal-btn-confirm">Confirm Withdrawal</button>
            </div>
        </div>
    </div>
    
    <script>
        // Quick amount selection for deposit
        document.querySelectorAll('#depositForm .quick-amount').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#depositForm .quick-amount').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('depositAmount').value = this.dataset.amount;
            });
        });
        
        // Quick amount selection for withdrawal
        document.querySelectorAll('#withdrawalForm .quick-amount').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#withdrawalForm .quick-amount').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('withdrawalAmount').value = this.dataset.amount;
            });
        });
        
        // Deposit form validation
        document.getElementById('depositForm').addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('depositAmount').value);
            const minDeposit = <?php echo WPAY_MIN_DEPOSIT; ?>;
            const maxDeposit = <?php echo WPAY_MAX_DEPOSIT; ?>;
            
            if (amount < minDeposit) {
                e.preventDefault();
                alert('Minimum deposit is ₱' + minDeposit.toLocaleString());
                return false;
            }
            
            if (amount > maxDeposit) {
                e.preventDefault();
                alert('Maximum deposit is ₱' + maxDeposit.toLocaleString());
                return false;
            }
            
            const payType = document.querySelector('input[name="pay_type"]:checked');
            if (!payType) {
                e.preventDefault();
                alert('Please select a payment method');
                return false;
            }
        });
        
        // Custom withdrawal confirmation modal
        function showWithdrawalModal(amount, payType, account) {
            const modal = document.getElementById('withdrawalModal');
            document.getElementById('modalAmount').textContent = '₱' + amount.toFixed(2);
            document.getElementById('modalPayType').textContent = payType;
            document.getElementById('modalAccount').textContent = account;
            modal.classList.add('active');
            
            document.getElementById('confirmWithdrawal').onclick = function() {
                modal.classList.remove('active');
                // Set confirmed flag and submit
                document.getElementById('withdrawalConfirmed').value = '1';
                const form = document.getElementById('withdrawalForm');
                // Create a temporary form element to bypass validation
                const tempForm = document.createElement('form');
                tempForm.method = 'POST';
                tempForm.action = '';
                
                // Copy all form fields
                const formData = new FormData(form);
                formData.forEach((value, key) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    tempForm.appendChild(input);
                });
                
                // Add auto_withdrawal button
                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'auto_withdrawal';
                submitInput.value = '1';
                tempForm.appendChild(submitInput);
                
                document.body.appendChild(tempForm);
                tempForm.submit();
            };
            
            document.getElementById('cancelWithdrawal').onclick = function() {
                modal.classList.remove('active');
            };
            
            // Close on overlay click
            modal.onclick = function(e) {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            };
        }
        
        function handleWithdrawalSubmit(e) {
            const amount = parseFloat(document.getElementById('withdrawalAmount').value);
            const balance = <?php echo $balance; ?>;
            const minWithdrawal = <?php echo WPAY_MIN_WITHDRAWAL; ?>;
            const maxWithdrawal = <?php echo WPAY_MAX_WITHDRAWAL; ?>;
            
            if (amount < minWithdrawal) {
                e.preventDefault();
                alert('Minimum withdrawal is ₱' + minWithdrawal.toLocaleString());
                return false;
            }
            
            if (amount > maxWithdrawal) {
                e.preventDefault();
                alert('Maximum withdrawal is ₱' + maxWithdrawal.toLocaleString());
                return false;
            }
            
            if (amount > balance) {
                e.preventDefault();
                alert('Insufficient balance. You have ₱' + balance.toFixed(2));
                return false;
            }
            
            const payType = document.querySelector('input[name="pay_type"]:checked');
            if (!payType) {
                e.preventDefault();
                alert('Please select a payment method');
                return false;
            }
            
            // Show custom confirmation modal
            e.preventDefault();
            showWithdrawalModal(amount, payType.value, document.querySelector('input[name="account"]').value);
        }
        
        // Withdrawal form validation
        document.getElementById('withdrawalForm').addEventListener('submit', handleWithdrawalSubmit);
        
        // Toggle balance dropdown
        function toggleBalanceDropdown() {
            const dropdown = document.getElementById('balance-dropdown');
            dropdown.classList.toggle('active');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const balanceDropdown = document.getElementById('balance-dropdown');
            const balanceTrigger = document.getElementById('balance-trigger');
            if (!balanceDropdown.contains(e.target) && !balanceTrigger.contains(e.target)) {
                balanceDropdown.classList.remove('active');
            }
        });
    </script>
</body>
</html>
