<?php
/**
 * Simple Auto Deposit Form - Working Version
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
$paymentUrl = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);
    $payType = trim($_POST['pay_type'] ?? '');
    
    error_log("Deposit Form: Amount=$amount, PayType=$payType, UserID={$_SESSION['user_id']}");
    
    // Validation
    if ($amount < WPAY_MIN_DEPOSIT) {
        $error = "Minimum deposit is ₱" . number_format(WPAY_MIN_DEPOSIT);
    } elseif ($amount > WPAY_MAX_DEPOSIT) {
        $error = "Maximum deposit is ₱" . number_format(WPAY_MAX_DEPOSIT);
    } elseif (empty($payType) || !in_array($payType, ['GCASH', 'MAYA', 'QR'])) {
        $error = "Please select a valid payment method";
    } else {
        // Process deposit
        $wpay = new WPayHelper();
        $result = $wpay->createPayIn($_SESSION['user_id'], $amount, $payType);
        
        if ($result['success']) {
            $paymentUrl = $result['payment_url'] ?? null;
            $success = "Deposit created! Redirecting to payment...";
            error_log("Deposit SUCCESS: Order={$result['order_no']}, URL=$paymentUrl");
            
            // Redirect after 1 second
            if ($paymentUrl) {
                echo "<script>setTimeout(() => { window.location.href = '$paymentUrl'; }, 1000);</script>";
            }
        } else {
            $error = $result['error'] ?? 'Deposit failed';
            error_log("Deposit FAILED: " . json_encode($result));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit - Paldo88</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        
        input[type="number"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        input[type="number"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .quick-amounts {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        
        .quick-btn {
            padding: 10px;
            background: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }
        
        .quick-btn:hover {
            background: #e0e0e0;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
            border-color: #667eea;
            background: #f5f5ff;
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
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
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
            <h1>💳 Deposit Money</h1>
            <p class="subtitle">Fast, secure, and easy deposits</p>
            
            <div class="balance">
                <div class="balance-label">Your Current Balance</div>
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
                    <label for="amount">Deposit Amount (₱)</label>
                    <input type="number" 
                           id="amount" 
                           name="amount" 
                           min="<?php echo WPAY_MIN_DEPOSIT; ?>" 
                           max="<?php echo WPAY_MAX_DEPOSIT; ?>" 
                           step="1" 
                           placeholder="Enter amount">
                    
                    <div class="quick-amounts">
                        <button type="button" class="quick-btn" onclick="setAmount(100)">₱100</button>
                        <button type="button" class="quick-btn" onclick="setAmount(500)">₱500</button>
                        <button type="button" class="quick-btn" onclick="setAmount(1000)">₱1,000</button>
                        <button type="button" class="quick-btn" onclick="setAmount(5000)">₱5,000</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Select Payment Method</label>
                    <div class="payment-methods">
                        <div class="payment-option">
                            <input type="radio" id="gcash" name="pay_type" value="GCASH">
                            <label for="gcash">
                                <div class="payment-icon">💰</div>
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
                        <div class="payment-option">
                            <input type="radio" id="qr" name="pay_type" value="QR">
                            <label for="qr">
                                <div class="payment-icon">📱</div>
                                <div class="payment-name">QR Code</div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="limits-info">
                    📋 Min: ₱<?php echo number_format(WPAY_MIN_DEPOSIT); ?> | 
                    Max: ₱<?php echo number_format(WPAY_MAX_DEPOSIT); ?> | 
                    Fee: 1.6% (covered by admin)
                </div>
                
                <button type="submit" class="btn">Proceed to Payment</button>
            </form>
        </div>
    </div>

    <script>
        function setAmount(amount) {
            document.getElementById('amount').value = amount;
        }
        
        function validateForm() {
            const amount = parseFloat(document.getElementById('amount').value);
            const payType = document.querySelector('input[name="pay_type"]:checked');
            
            if (!amount || amount < <?php echo WPAY_MIN_DEPOSIT; ?> || amount > <?php echo WPAY_MAX_DEPOSIT; ?>) {
                alert('Please enter amount between ₱<?php echo WPAY_MIN_DEPOSIT; ?> and ₱<?php echo WPAY_MAX_DEPOSIT; ?>');
                return false;
            }
            
            if (!payType) {
                alert('Please select a payment method');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>
