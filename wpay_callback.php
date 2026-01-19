<?php
/**
 * WPay Payment Callback Handler
 * Receives notifications from WPay for deposit and withdrawal status updates
 */

require_once __DIR__ . '/wpay_config.php';
require_once __DIR__ . '/wpay_helper.php';
require_once __DIR__ . '/db_helper.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Log all incoming requests
$logFile = __DIR__ . '/logs/wpay_callback.log';
$requestData = file_get_contents('php://input');
$requestHeaders = getallheaders();

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Log request
file_put_contents($logFile, 
    "\n\n=== " . date('Y-m-d H:i:s') . " ===\n" .
    "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n" .
    "Method: " . $_SERVER['REQUEST_METHOD'] . "\n" .
    "Headers: " . json_encode($requestHeaders) . "\n" .
    "Raw Data: " . $requestData . "\n" .
    "POST Data: " . json_encode($_POST) . "\n" .
    "GET Data: " . json_encode($_GET) . "\n",
    FILE_APPEND
);

// Get callback data
$callbackData = $_POST;

// Validate IP address - Accept both test and production IPs
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
$allowedIPs = ['103.156.25.75', '43.224.224.185', '43.224.224.239']; // Test + Production IPs
if (!in_array($clientIP, $allowedIPs)) {
    file_put_contents($logFile, "WARNING: Unknown callback IP: {$clientIP} (but processing anyway)\n", FILE_APPEND);
    // Don't block - just log
}

// Validate required fields
if (empty($callbackData['out_trade_no'])) {
    file_put_contents($logFile, "ERROR: Missing out_trade_no\n", FILE_APPEND);
    echo 'fail';
    exit;
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $outTradeNo = $callbackData['out_trade_no'];
    $wpay = new WPayHelper();
    
    // Verify signature
    if (!$wpay->verifySign($callbackData)) {
        file_put_contents($logFile, "ERROR: Invalid signature for order: {$outTradeNo}\n", FILE_APPEND);
        echo 'fail';
        exit;
    }
    
    // Log callback
    $stmt = $pdo->prepare("
        INSERT INTO payment_callbacks 
        (out_trade_no, transaction_type, callback_data, ip_address, is_verified) 
        VALUES (?, ?, ?, ?, 1)
    ");
    
    // Determine transaction type based on order number prefix
    $transactionType = (substr($outTradeNo, 0, 1) === 'D') ? 'deposit' : 'withdrawal';
    
    $stmt->execute([
        $outTradeNo,
        $transactionType,
        json_encode($callbackData),
        $clientIP
    ]);
    
    // Process based on transaction type
    if ($transactionType === 'deposit') {
        processDepositCallback($pdo, $callbackData, $outTradeNo);
    } else {
        processWithdrawalCallback($pdo, $callbackData, $outTradeNo);
    }
    
    // Respond with success - return plain text 'success' as WPay expects
    http_response_code(200);
    header('Content-Type: text/plain');
    echo 'success';
    file_put_contents($logFile, "SUCCESS: Processed callback for order: {$outTradeNo}, responded with 'success'\n", FILE_APPEND);
    
} catch (Exception $e) {
    file_put_contents($logFile, "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(200); // Still return 200 to stop retries
    header('Content-Type: text/plain');
    echo 'fail';
}

/**
 * Process deposit callback
 */
function processDepositCallback($pdo, $callbackData, $outTradeNo) {
    global $logFile;
    
    // Get transaction
    $stmt = $pdo->prepare("SELECT * FROM payment_transactions WHERE out_trade_no = ?");
    $stmt->execute([$outTradeNo]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        file_put_contents($logFile, "ERROR: Transaction not found: {$outTradeNo}\n", FILE_APPEND);
        return;
    }
    
    // Check if already processed
    if ($transaction['status'] === 'completed') {
        file_put_contents($logFile, "INFO: Transaction already completed: {$outTradeNo}\n", FILE_APPEND);
        return;
    }
    
    // Update callback count
    $stmt = $pdo->prepare("
        UPDATE payment_transactions 
        SET callback_count = callback_count + 1, notify_data = ? 
        WHERE out_trade_no = ?
    ");
    $stmt->execute([json_encode($callbackData), $outTradeNo]);
    
    // Determine status based on provider callback
    // Sandbox rule: even last digit of out_trade_no => success
    $codeVal = isset($callbackData['code']) ? (int)$callbackData['code'] : null;
    $statusVal = isset($callbackData['status']) ? strtolower((string)$callbackData['status']) : null;
    $success = false;
    if (defined('WPAY_ENV') && WPAY_ENV === 'sandbox') {
        $lastDigit = (int)substr($outTradeNo, -1);
        $success = ($lastDigit % 2 === 0);
    } else {
        $success = ($codeVal === 0) || ($statusVal === '1') || ($statusVal === 'success');
    }
    $callbackTxnId = $callbackData['transaction_id'] ?? $callbackData['transactionId'] ?? null;
    
    if ($success) {
        // Update transaction status to completed
        $stmt = $pdo->prepare("
            UPDATE payment_transactions 
            SET status = 'completed', 
                transaction_id = COALESCE(?, transaction_id),
                completed_at = NOW() 
            WHERE out_trade_no = ?
        ");
        $stmt->execute([
            $callbackTxnId,
            $outTradeNo
        ]);
        
        // Add balance to user
        $userModel = new User();
        $userModel->addBalance(
            $transaction['user_id'], 
            $transaction['amount'], 
            'deposit',
            "Deposit completed: {$outTradeNo}"
        );
        
        file_put_contents($logFile, "INFO: Deposit completed and balance added for order: {$outTradeNo}\n", FILE_APPEND);
        
    } else {
        // Mark as failed
        $stmt = $pdo->prepare("
            UPDATE payment_transactions 
            SET status = 'failed' 
            WHERE out_trade_no = ?
        ");
        $stmt->execute([$outTradeNo]);
        
        file_put_contents($logFile, "INFO: Deposit failed for order: {$outTradeNo}\n", FILE_APPEND);
    }
}

/**
 * Process withdrawal callback
 */
function processWithdrawalCallback($pdo, $callbackData, $outTradeNo) {
    global $logFile;
    
    // Get transaction
    $stmt = $pdo->prepare("SELECT * FROM withdrawal_transactions WHERE out_trade_no = ?");
    $stmt->execute([$outTradeNo]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        file_put_contents($logFile, "ERROR: Transaction not found: {$outTradeNo}\n", FILE_APPEND);
        return;
    }
    
    // Check if already processed
    if ($transaction['status'] === 'completed') {
        file_put_contents($logFile, "INFO: Transaction already completed: {$outTradeNo}\n", FILE_APPEND);
        return;
    }
    
    // Update callback count
    $stmt = $pdo->prepare("
        UPDATE withdrawal_transactions 
        SET callback_count = callback_count + 1, notify_data = ? 
        WHERE out_trade_no = ?
    ");
    $stmt->execute([json_encode($callbackData), $outTradeNo]);
    
    // Determine status based on provider callback
    $codeVal = isset($callbackData['code']) ? (int)$callbackData['code'] : null;
    $statusVal = isset($callbackData['status']) ? strtolower((string)$callbackData['status']) : null;
    $success = false;
    if (defined('WPAY_ENV') && WPAY_ENV === 'sandbox') {
        $lastDigit = (int)substr($outTradeNo, -1);
        $success = ($lastDigit % 2 === 0);
    } else {
        $success = ($codeVal === 0) || ($statusVal === '1') || ($statusVal === 'success');
    }
    $callbackTxnId = $callbackData['transaction_id'] ?? $callbackData['transactionId'] ?? null;
    
    if ($success) {
        // Update transaction status to completed
        $stmt = $pdo->prepare("
            UPDATE withdrawal_transactions 
            SET status = 'completed', 
                transaction_id = COALESCE(?, transaction_id),
                completed_at = NOW() 
            WHERE out_trade_no = ?
        ");
        $stmt->execute([
            $callbackTxnId,
            $outTradeNo
        ]);
        
        file_put_contents($logFile, "INFO: Withdrawal completed for order: {$outTradeNo}\n", FILE_APPEND);
        
    } else {
        // Mark as failed and refund balance
        $stmt = $pdo->prepare("
            UPDATE withdrawal_transactions 
            SET status = 'failed' 
            WHERE out_trade_no = ?
        ");
        $stmt->execute([$outTradeNo]);
        
        // Refund balance
        $userModel = new User();
        $userModel->addBalance(
            $transaction['user_id'], 
            $transaction['amount'], 
            "Withdrawal failed refund: {$outTradeNo}"
        );
        
        file_put_contents($logFile, "INFO: Withdrawal failed and balance refunded for order: {$outTradeNo}\n", FILE_APPEND);
    }
}
?>
