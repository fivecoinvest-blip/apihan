<?php
/**
 * Deposit Test Form - No Authentication Required
 */

require_once 'wpay_config.php';
require_once 'wpay_helper.php';
require_once 'wpay_fee_messages.php';
require_once 'db_helper.php';

// Simulate logged-in user for testing
$_SESSION['user_id'] = 1;

$userModel = new User();
$currentUser = $userModel->getById($_SESSION['user_id']);
$balance = $userModel->getBalance($_SESSION['user_id']);

$error = '';
$success = '';
$paymentUrl = '';
$submittedAmount = 0;
$submittedPayType = '';

// Handle deposit request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_deposit'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $payType = $_POST['pay_type'] ?? '';
    
    // Validate amount
    if ($amount < WPAY_MIN_DEPOSIT) {
        $error = "Minimum deposit amount is ₱" . number_format(WPAY_MIN_DEPOSIT);
    } elseif ($amount > WPAY_MAX_DEPOSIT) {
        $error = "Maximum deposit amount is ₱" . number_format(WPAY_MAX_DEPOSIT);
    } elseif (empty($payType)) {
        $error = "Please select a payment method";
    } else {
        $submittedAmount = $amount;
        $submittedPayType = $payType;
        
        // Use WPayHelper to create deposit - this handles everything
        $wpay = new WPayHelper();
        $result = $wpay->createPayIn($_SESSION['user_id'], $amount, $payType);
        
        if ($result['success']) {
            $paymentUrl = $result['payment_url'] ?? null;
            $success = 'Deposit request created successfully! Redirecting to payment...';
            
            // Auto-redirect after 2 seconds
            if ($paymentUrl) {
                header('refresh:2;url=' . $paymentUrl);
            }
        } else {
            // Handle error
            $error = $result['error'] ?? 'Failed to process deposit. Please try again.';
            // Log technical error for debugging
            if (isset($result['technical_error'])) {
                error_log("Deposit Error Details: " . $result['technical_error']);
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Deposit - Casino</title>
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
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #2d3748;
            font-size: 28px;
            margin-bottom: 20px;
        }
        
        .balance-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .balance-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .balance-amount {
            font-size: 32px;
            font-weight: bold;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert.show {
            display: block;
        }
        
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 600;
        }
        
        input[type="number"],
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
        }
        
        input[type="number"]:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        
        .payment-method {
            position: relative;
        }
        
        .payment-method input[type="radio"] {
            display: none;
        }
        
        .payment-method label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .payment-method input[type="radio"]:checked + label {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .payment-icon {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .payment-name {
            font-size: 12px;
            color: #4a5568;
        }
        
        .fee-info {
            background: #f7fafc;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
        }
        
        button:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>💰 Deposit</h1>
            
            <div class="balance-card">
                <div class="balance-label">Current Balance</div>
                <div class="balance-amount">₱<?php echo number_format($balance, 2); ?></div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error show">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success show">
                    ✅ <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Select Payment Method</label>
                    <div class="payment-methods">
                        <div class="payment-method">
                            <input type="radio" name="pay_type" id="gcash" value="GCASH" required>
                            <label for="gcash">
                                <div class="payment-icon">💳</div>
                                <div class="payment-name">GCash</div>
                            </label>
                        </div>
                        
                        <div class="payment-method">
                            <input type="radio" name="pay_type" id="maya" value="MAYA">
                            <label for="maya">
                                <div class="payment-icon">💰</div>
                                <div class="payment-name">Maya</div>
                            </label>
                        </div>
                        
                        <div class="payment-method">
                            <input type="radio" name="pay_type" id="qr" value="QR">
                            <label for="qr">
                                <div class="payment-icon">📱</div>
                                <div class="payment-name">QR Code</div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="amount">Deposit Amount (₱)</label>
                    <input type="number" id="amount" name="amount" min="<?php echo WPAY_MIN_DEPOSIT; ?>" max="<?php echo WPAY_MAX_DEPOSIT; ?>" step="1" placeholder="Enter amount" value="<?php echo $submittedAmount; ?>" required>
                    <small style="color: #718096; margin-top: 5px; display: block;">
                        Min: ₱<?php echo number_format(WPAY_MIN_DEPOSIT); ?> | Max: ₱<?php echo number_format(WPAY_MAX_DEPOSIT); ?>
                    </small>
                </div>
                
                <button type="submit" name="submit_deposit">Proceed to Payment</button>
            </form>
        </div>
    </div>
</body>
</html>
