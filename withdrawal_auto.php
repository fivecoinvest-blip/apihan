<?php
/**
 * Simple Auto Withdrawal Form - Working Version
 */
require_once 'session_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'wpay_config.php';
require_once 'wpay_helper.php';
require_once 'db_helper.php';

$userModel = new User();
$balance = $userModel->getBalance($_SESSION['user_id']);

$error = '';
$success = '';

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);
    $payType = trim($_POST['pay_type'] ?? '');
    $account = trim($_POST['account'] ?? '');
    $accountName = trim($_POST['account_name'] ?? '');
    
    error_log("Withdrawal Form: Amount=$amount, PayType=$payType, Account=$account, UserID={$_SESSION['user_id']}");
    
    // Validate inputs
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
        // Process withdrawal
        $wpay = new WPayHelper();
        $result = $wpay->createPayOut(
            $_SESSION['user_id'], 
            $amount, 
            $payType, 
            $account, 
            $accountName
        );
        
        if ($result['success']) {
            $success = $result['message'] ?? 'Withdrawal submitted successfully!';
            error_log("Withdrawal SUCCESS: Order={$result['order_no']}");
            // Refresh balance
            $balance = $userModel->getBalance($_SESSION['user_id']);
        } else {
            $error = $result['error'] ?? 'Withdrawal failed';
            error_log("Withdrawal FAILED: " . json_encode($result));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal - Paldo88</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 500px;
            margin: 50px auto;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 24px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .balance {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .balance-label {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .balance-amount {
            font-size: 32px;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3a3;
            border: 1px solid #cfc;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        input[type="number"],
        input[type="text"],
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #f093fb;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 25px;
        }
        
        .payment-option {
            text-align: center;
        }
        
        .payment-option input[type="radio"] {
            display: none;
        }
        
        .payment-option label {
            display: block;
            padding: 15px 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            margin: 0;
            transition: all 0.2s;
        }
        
        .payment-option input[type="radio"]:checked + label {
            border-color: #f093fb;
            background: #fef5ff;
            font-weight: 600;
        }
        
        .payment-icon {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .payment-name {
            font-size: 12px;
        }
        
        .limits-info {
            background: #f9f9f9;
            border: 1px solid #eee;
            padding: 12px;
            border-radius: 6px;
            font-size: 12px;
            color: #666;
            margin-bottom: 20px;
        }
        
        .warning {
            background: #fef5e7;
            border: 1px solid #f9e79f;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #7d6608;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(240, 147, 251, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>💸 Withdraw Money</h1>
            <p class="subtitle">Fast and secure withdrawals</p>
            
            <div class="balance">
                <div class="balance-label">Available Balance</div>
                <div class="balance-amount">₱<?php echo number_format($balance, 2); ?></div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" onsubmit="return validateForm()">
                <div class="form-group">
                    <label>Select Withdrawal Method</label>
                    <div class="payment-methods">
                        <div class="payment-option">
                            <input type="radio" id="gcash" name="pay_type" value="GCASH">
                            <label for="gcash">
                                <div class="payment-icon">📱</div>
                                <div class="payment-name">GCash</div>
                            </label>
                        </div>
                        <div class="payment-option">
                            <input type="radio" id="maya" name="pay_type" value="MAYA">
                            <label for="maya">
                                <div class="payment-icon">💳</div>
                                <div class="payment-name">Maya</div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="account">Account/Phone Number</label>
                    <input type="text" 
                           id="account" 
                           name="account" 
                           placeholder="Enter account number or phone"
                           maxlength="50">
                </div>
                
                <div class="form-group">
                    <label for="account_name">Account Holder Name</label>
                    <input type="text" 
                           id="account_name" 
                           name="account_name" 
                           placeholder="Enter account holder name"
                           maxlength="100">
                </div>
                
                <div class="form-group">
                    <label for="amount">Withdrawal Amount (₱)</label>
                    <input type="number" 
                           id="amount" 
                           name="amount" 
                           min="<?php echo WPAY_MIN_WITHDRAWAL; ?>" 
                           max="<?php echo $balance; ?>" 
                           step="1" 
                           placeholder="Enter amount">
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 10px;">
                        <button type="button" class="btn" style="padding: 8px; font-size: 12px; background: #f0f0f0; color: #333;" onclick="setAmount(100)">₱100</button>
                        <button type="button" class="btn" style="padding: 8px; font-size: 12px; background: #f0f0f0; color: #333;" onclick="setAmount(500)">₱500</button>
                        <button type="button" class="btn" style="padding: 8px; font-size: 12px; background: #f0f0f0; color: #333;" onclick="setAmount(1000)">₱1,000</button>
                        <button type="button" class="btn" style="padding: 8px; font-size: 12px; background: #f0f0f0; color: #333;" onclick="setAmount(<?php echo (int)$balance; ?>)">All</button>
                    </div>
                </div>
                
                <div class="limits-info">
                    📋 Min: ₱<?php echo number_format(WPAY_MIN_WITHDRAWAL); ?> | 
                    Max: ₱<?php echo number_format(min(WPAY_MAX_WITHDRAWAL, $balance)); ?> | 
                    Fee: 1.6% + ₱8 processing
                </div>
                
                <div class="warning">
                    ⚠️ Ensure account details are correct. Withdrawals cannot be reversed.
                </div>
                
                <button type="submit" class="btn">Submit Withdrawal</button>
            </form>
        </div>
    </div>

    <script>
        function setAmount(amount) {
            const max = <?php echo (int)$balance; ?>;
            document.getElementById('amount').value = Math.min(amount, max);
        }
        
        function validateForm() {
            const amount = parseFloat(document.getElementById('amount').value);
            const payType = document.querySelector('input[name="pay_type"]:checked');
            const account = document.getElementById('account').value.trim();
            const accountName = document.getElementById('account_name').value.trim();
            const min = <?php echo WPAY_MIN_WITHDRAWAL; ?>;
            const max = <?php echo (int)$balance; ?>;
            
            if (!amount || amount < min || amount > max) {
                alert('Please enter amount between ₱' + min + ' and ₱' + max);
                return false;
            }
            
            if (!payType) {
                alert('Please select a payment method');
                return false;
            }
            
            if (!account) {
                alert('Please enter your account/phone number');
                return false;
            }
            
            if (!accountName) {
                alert('Please enter the account holder name');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>
