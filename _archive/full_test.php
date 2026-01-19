<?php
/**
 * Complete Deposit & Withdrawal Test
 * Tests both flows without authentication requirement
 */

require_once 'session_config.php';
require_once 'wpay_config.php';
require_once 'wpay_helper.php';
require_once 'wpay_fee_messages.php';
require_once 'db_helper.php';

// Simulate logged-in user for testing
$_SESSION['user_id'] = 1;
$testUserId = 1;

$userModel = new User();
$balance = $userModel->getBalance($testUserId);

$depositError = '';
$depositSuccess = '';
$depositUrl = '';
$withdrawalError = '';
$withdrawalSuccess = '';

// Handle DEPOSIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deposit') {
    $amount = floatval($_POST['deposit_amount'] ?? 0);
    $payType = $_POST['deposit_type'] ?? '';
    
    if ($amount < WPAY_MIN_DEPOSIT) {
        $depositError = "Min: ₱" . number_format(WPAY_MIN_DEPOSIT);
    } elseif ($amount > WPAY_MAX_DEPOSIT) {
        $depositError = "Max: ₱" . number_format(WPAY_MAX_DEPOSIT);
    } elseif (empty($payType)) {
        $depositError = "Select payment method";
    } else {
        $wpay = new WPayHelper();
        $result = $wpay->createPayIn($testUserId, $amount, $payType);
        
        if ($result['success']) {
            $depositSuccess = "✓ Created! Order: " . $result['order_no'];
            $depositUrl = $result['payment_url'] ?? null;
        } else {
            $depositError = $result['error'] ?? 'Failed';
        }
    }
}

// Handle WITHDRAWAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'withdrawal') {
    $amount = floatval($_POST['withdrawal_amount'] ?? 0);
    $payType = $_POST['withdrawal_type'] ?? '';
    $account = trim($_POST['account'] ?? '');
    $accountName = trim($_POST['account_name'] ?? '');
    
    if ($amount < WPAY_MIN_WITHDRAWAL) {
        $withdrawalError = "Min: ₱" . number_format(WPAY_MIN_WITHDRAWAL);
    } elseif ($amount > WPAY_MAX_WITHDRAWAL) {
        $withdrawalError = "Max: ₱" . number_format(WPAY_MAX_WITHDRAWAL);
    } elseif ($amount > $balance) {
        $withdrawalError = "Insufficient balance";
    } elseif (empty($account)) {
        $withdrawalError = "Enter account/phone";
    } elseif (empty($accountName)) {
        $withdrawalError = "Enter account name";
    } elseif (empty($payType)) {
        $withdrawalError = "Select payment method";
    } else {
        $wpay = new WPayHelper();
        $result = $wpay->createPayOut($testUserId, $amount, $payType, $account, $accountName);
        
        if ($result['success']) {
            $withdrawalSuccess = "✓ Created! Order: " . $result['order_no'];
            $balance = $userModel->getBalance($testUserId);
        } else {
            $withdrawalError = $result['error'] ?? 'Failed';
        }
    }
}

// Get bank list for withdrawal
$wpay = new WPayHelper();
$bankListResponse = $wpay->getBankList('PHP');
$bankList = $bankListResponse['data'] ?? [];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit & Withdrawal Test</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .balance {
            background: rgba(255,255,255,0.2);
            padding: 15px 30px;
            border-radius: 25px;
            display: inline-block;
            font-size: 18px;
            margin-top: 10px;
        }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .card h2 {
            color: #2d3748;
            margin-bottom: 20px;
            font-size: 24px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 600;
            font-size: 14px;
        }
        input[type="number"],
        input[type="text"],
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .payment-method input[type="radio"] { display: none; }
        .payment-method label {
            margin: 0;
            padding: 12px;
            text-align: center;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: normal;
        }
        .payment-method input:checked + label {
            border-color: #667eea;
            background: #f0f4ff;
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        button:hover { transform: translateY(-2px); }
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
            display: none;
        }
        .alert.show { display: block; }
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }
        .payment-url {
            margin-top: 15px;
            padding: 12px;
            background: #f7fafc;
            border-radius: 8px;
        }
        .payment-url a {
            color: #667eea;
            text-decoration: none;
            word-break: break-all;
        }
        .payment-url a:hover { text-decoration: underline; }
        small { color: #718096; display: block; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Deposit & Withdrawal Test</h1>
            <div class="balance">Balance: ₱<?php echo number_format($balance, 2); ?></div>
        </div>

        <div class="grid">
            <!-- DEPOSIT SECTION -->
            <div class="card">
                <h2>💳 Deposit</h2>

                <?php if ($depositError): ?>
                    <div class="alert alert-error show">❌ <?php echo htmlspecialchars($depositError); ?></div>
                <?php endif; ?>

                <?php if ($depositSuccess): ?>
                    <div class="alert alert-success show">
                        ✅ <?php echo htmlspecialchars($depositSuccess); ?>
                        <?php if ($depositUrl): ?>
                            <div class="payment-url">
                                <strong>Payment URL:</strong><br>
                                <a href="<?php echo htmlspecialchars($depositUrl); ?>" target="_blank">Open Payment Page</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="deposit">
                    
                    <div class="form-group">
                        <label>Payment Method</label>
                        <div class="payment-methods">
                            <div class="payment-method">
                                <input type="radio" name="deposit_type" id="gcash" value="GCASH" required>
                                <label for="gcash">💳 GCash</label>
                            </div>
                            <div class="payment-method">
                                <input type="radio" name="deposit_type" id="maya" value="MAYA">
                                <label for="maya">💰 Maya</label>
                            </div>
                            <div class="payment-method">
                                <input type="radio" name="deposit_type" id="qr" value="QR">
                                <label for="qr">📱 QR</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="deposit_amount">Amount (₱)</label>
                        <input type="number" id="deposit_amount" name="deposit_amount" min="<?php echo WPAY_MIN_DEPOSIT; ?>" max="<?php echo WPAY_MAX_DEPOSIT; ?>" step="1" placeholder="100" required>
                        <small>Min: ₱<?php echo number_format(WPAY_MIN_DEPOSIT); ?> | Max: ₱<?php echo number_format(WPAY_MAX_DEPOSIT); ?></small>
                    </div>

                    <button type="submit">Proceed to Payment</button>
                </form>
            </div>

            <!-- WITHDRAWAL SECTION -->
            <div class="card">
                <h2>💸 Withdrawal</h2>

                <?php if ($withdrawalError): ?>
                    <div class="alert alert-error show">❌ <?php echo htmlspecialchars($withdrawalError); ?></div>
                <?php endif; ?>

                <?php if ($withdrawalSuccess): ?>
                    <div class="alert alert-success show">✅ <?php echo htmlspecialchars($withdrawalSuccess); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="withdrawal">

                    <div class="form-group">
                        <label for="withdrawal_type">Bank/Wallet</label>
                        <select id="withdrawal_type" name="withdrawal_type" required>
                            <option value="">Select...</option>
                            <option value="PH_GCASH">GCash (PH_GCASH)</option>
                            <option value="PH_MYA">Maya (PH_MYA)</option>
                            <option value="PH_BDO">BDO Bank (PH_BDO)</option>
                            <option value="PH_BPI">BPI Bank (PH_BPI)</option>
                            <option value="PH_MET">MetroBank (PH_MET)</option>
                            <option value="PH_PNB">PNB Bank (PH_PNB)</option>
                            <option value="PH_RCI">RCBC Bank (PH_RCI)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="account">Account / Phone Number</label>
                        <input type="text" id="account" name="account" placeholder="09XXXXXXXXX or Account #" required>
                    </div>

                    <div class="form-group">
                        <label for="account_name">Account Name</label>
                        <input type="text" id="account_name" name="account_name" placeholder="Full Name" required>
                    </div>

                    <div class="form-group">
                        <label for="withdrawal_amount">Amount (₱)</label>
                        <input type="number" id="withdrawal_amount" name="withdrawal_amount" min="<?php echo WPAY_MIN_WITHDRAWAL; ?>" max="<?php echo WPAY_MAX_WITHDRAWAL; ?>" step="1" placeholder="100" required>
                        <small>Min: ₱<?php echo number_format(WPAY_MIN_WITHDRAWAL); ?> | Max: ₱<?php echo number_format(WPAY_MAX_WITHDRAWAL); ?></small>
                    </div>

                    <button type="submit">Submit Withdrawal</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
