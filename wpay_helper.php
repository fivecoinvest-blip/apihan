<?php
/**
 * WPay Payment Gateway Helper
 * Handles PayIn (Deposits) and PayOut (Withdrawals)
 */

require_once __DIR__ . '/wpay_config.php';
require_once __DIR__ . '/db_helper.php';
require_once __DIR__ . '/wpay_error_codes.php';

class WPayHelper {
    
    private $mchId;
    private $key;
    private $host;
    private $redis;
    private $logPrefix = 'WPAY PAYIN';
    
    public function __construct() {
        $this->mchId = WPAY_MCH_ID;
        $this->key = WPAY_KEY;
        $this->host = WPAY_HOST;
        // Best-effort Redis for locks
        try {
            $this->redis = new Redis();
            $this->redis->connect('127.0.0.1', 6379);
        } catch (Exception $e) {
            $this->redis = null; // proceed without lock if Redis unavailable
        }
    }

    private function acquireLock(string $key, int $ttl = 10): bool {
        if (!$this->redis) return true;
        try {
            // NX with expiry
            return (bool)$this->redis->set($key, 1, ['nx', 'ex' => $ttl]);
        } catch (Exception $e) {
            return true; // fail open
        }
    }

    private function releaseLock(string $key): void {
        if (!$this->redis) return;
        try {
            $this->redis->del($key);
        } catch (Exception $e) {
            // ignore
        }
    }
    
    /**
     * Calculate transaction fees
     * Collection fee: 1.6%
     * Processing fee: 8 PHP (for withdrawals)
     */
    public function calculateFees(float $amount, string $type = 'deposit'): array {
        $collectionFee = round($amount * (WPAY_COLLECTION_FEE_PERCENT / 100), 2);
        $processingFee = ($type === 'withdrawal') ? WPAY_PROCESSING_FEE : 0;
        $totalFee = $collectionFee + $processingFee;
        $netAmount = $amount - $totalFee;
        
        return [
            'collection_fee' => $collectionFee,
            'processing_fee' => $processingFee,
            'total_fee' => $totalFee,
            'net_amount' => max(0, $netAmount) // Ensure net amount doesn't go negative
        ];
    }
    
    /**
     * Generate signature for API request
     * Sign algorithm: MD5(sorted key=value pairs + key)
     */
    public function generateSign(array $params): string {
        // Remove sign and empty values
        unset($params['sign']);
        $params = array_filter($params, function($value) {
            return $value !== '' && $value !== null;
        });
        
        // Sort by key
        ksort($params);
        
        // Build query string
        $signStr = '';
        foreach ($params as $key => $value) {
            $signStr .= $key . '=' . $value . '&';
        }
        
        // Add key at the end
        $signStr .= 'key=' . $this->key;
        
        // Generate MD5 hash
        return md5($signStr);
    }
    
    /**
     * Verify callback signature
     */
    public function verifySign(array $params): bool {
        if (!isset($params['sign'])) {
            return false;
        }
        
        $receivedSign = $params['sign'];
        $calculatedSign = $this->generateSign($params);
        
        return $receivedSign === $calculatedSign;
    }
    
    /**
     * Generate unique order number
     */
    public function generateOrderNo(string $prefix = ''): string {
        // Format: YYYYMMDDHHMMSS + random 6 digits
        // Last digit will be even for testing (to simulate success in sandbox)
        $orderNo = date('YmdHis') . str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
        
        // Ensure last digit is even for sandbox testing
        if (WPAY_ENV === 'sandbox') {
            $lastDigit = (int)substr($orderNo, -1);
            if ($lastDigit % 2 !== 0) {
                $orderNo = substr($orderNo, 0, -1) . ($lastDigit + 1);
            }
        }
        
        return $prefix . $orderNo;
    }
    
    /**
     * Create PayIn request (Deposit)
     * 
     * @param int $userId User ID
     * @param float $amount Amount to deposit
     * @param string $payType Payment type (GCASH, MAYA, QR)
     * @param string|null $attach Additional data
     * @return array Response with success status and payment URL
     */
    public function createPayIn(int $userId, float $amount, string $payType, ?string $attach = null): array {
        $lockKey = "lock:wpay:deposit:" . $userId;
        $lockAcquired = $this->acquireLock($lockKey, 10);
        try {
            error_log("{$this->logPrefix}: start user={$userId} amount={$amount} payType={$payType}");
            if (!$lockAcquired) {
                return [
                    'success' => false,
                    'error' => 'Please wait a few seconds before retrying (in-progress).'
                ];
            }
            // Validate amount
            if ($amount < WPAY_MIN_DEPOSIT || $amount > WPAY_MAX_DEPOSIT) {
                return [
                    'success' => false,
                    'error' => "Amount must be between ₱" . number_format(WPAY_MIN_DEPOSIT) . " and ₱" . number_format(WPAY_MAX_DEPOSIT)
                ];
            }
            
            // Idempotency: prevent duplicate rapid submissions (last 60s)
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            $dupStmt = $pdo->prepare("SELECT out_trade_no, payment_url, status FROM payment_transactions WHERE user_id = ? AND amount = ? AND pay_type = ? AND status IN ('pending','processing') AND created_at >= (NOW() - INTERVAL 60 SECOND) ORDER BY created_at DESC LIMIT 1");
            $dupStmt->execute([$userId, $amount, $payType]);
            $existing = $dupStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                error_log("{$this->logPrefix}: duplicate hit user={$userId} order={$existing['out_trade_no']} status={$existing['status']} url=" . ($existing['payment_url'] ?? 'null'));
                return [
                    'success' => true,
                    'order_no' => $existing['out_trade_no'],
                    'payment_url' => $existing['payment_url'] ?? null,
                    'amount' => $amount,
                    'pay_type' => $payType,
                    'message' => 'Duplicate submission detected; using existing transaction.'
                ];
            }

            // Generate unique order number
            $outTradeNo = $this->generateOrderNo('D');
            
            // Calculate fees (deposits typically have no fee for user)
            $fees = $this->calculateFees($amount, 'deposit');
            
            // Get database connection
            // (already initialized above for idempotency check)
            
            $stmt = $pdo->prepare("
                INSERT INTO payment_transactions 
                (user_id, out_trade_no, amount, currency, pay_type, collection_fee, processing_fee, total_fee, net_amount, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $userId, 
                $outTradeNo, 
                $amount, 
                WPAY_CURRENCY, 
                $payType,
                $fees['collection_fee'],
                $fees['processing_fee'],
                $fees['total_fee'],
                $fees['net_amount']
            ]);
            
            // Build request parameters
            $params = [
                'mchId' => $this->mchId,
                'currency' => WPAY_CURRENCY,
                'out_trade_no' => $outTradeNo,
                'pay_type' => $payType,
                'money' => (int)$amount, // Convert to integer
                'notify_url' => WPAY_NOTIFY_URL,
                'returnUrl' => WPAY_RETURN_URL,  // NOTE: WPay rejects URLs with query parameters
            ];
            
            if ($attach) {
                $params['attach'] = $attach;
            }
            
            // Generate signature
            $params['sign'] = $this->generateSign($params);
            
            // Send request
            error_log("{$this->logPrefix}: send Collect order={$outTradeNo} params=" . json_encode($params));
            $response = $this->sendRequest('/v1/Collect', $params);
            
            if (!$response) {
                error_log("{$this->logPrefix}: null response order={$outTradeNo}");
                throw new Exception('Failed to connect to payment gateway');
            }
            
            // Check response
            // Treat missing code with valid URL as success in sandbox
            $isSuccess = (isset($response['code']) && $response['code'] == 0) 
                || (!isset($response['code']) && isset($response['data']['url']));
            if ($isSuccess) {
                error_log("{$this->logPrefix}: success order={$outTradeNo} url=" . ($response['data']['url'] ?? 'null'));
                // Update transaction with payment URL and transaction ID
                $stmt = $pdo->prepare("
                    UPDATE payment_transactions 
                    SET payment_url = ?, transaction_id = ?, status = 'processing'
                    WHERE out_trade_no = ?
                ");
                $stmt->execute([
                    $response['data']['url'] ?? null,
                    $response['data']['transaction_id'] ?? ($response['data']['transaction_Id'] ?? null),
                    $outTradeNo
                ]);
                
                return [
                    'success' => true,
                    'order_no' => $outTradeNo,
                    'transaction_id' => $response['data']['transaction_id'] ?? ($response['data']['transaction_Id'] ?? null),
                    'payment_url' => $response['data']['url'] ?? null,
                    'amount' => $amount,
                    'pay_type' => $payType
                ];
            } else {
                error_log("{$this->logPrefix}: gateway error order={$outTradeNo} code=" . ($response['code'] ?? 'null') . " msg=" . ($response['msg'] ?? ''));
                // Update status to failed
                $errorCode = $response['code'] ?? -1;
                $errorMsg = getWPayUserMessage($errorCode);
                
                $stmt = $pdo->prepare("
                    UPDATE payment_transactions 
                    SET status = 'failed', notify_data = ?
                    WHERE out_trade_no = ?
                ");
                $stmt->execute([json_encode($response), $outTradeNo]);
                
                return [
                    'success' => false,
                    'error' => $errorMsg,
                    'code' => $errorCode,
                    'technical_error' => $response['msg'] ?? 'Unknown error'
                ];
            }
            
        } catch (Exception $e) {
            error_log("WPay PayIn Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'System error: ' . $e->getMessage()
            ];
        } finally {
            if ($lockAcquired) {
                $this->releaseLock($lockKey);
            }
        }
    }
    
    /**
     * Create PayOut request (Withdrawal)
     * 
     * @param int $userId User ID
     * @param float $amount Amount to withdraw
     * @param string $payType Payment type (GCASH, MAYA, NATIVE)
     * @param string $account Account number
     * @param string $userName Account holder name
     * @param string|null $bankCode Bank code (required for NATIVE)
     * @param string|null $attach Additional data
     * @return array Response with success status
     */
    public function createPayOut(int $userId, float $amount, string $payType, string $account, string $userName, ?string $bankCode = null, ?string $attach = null): array {
        $lockKey = "lock:wpay:withdraw:" . $userId;
        $lockAcquired = $this->acquireLock($lockKey, 10);
        try {
            if (!$lockAcquired) {
                return [
                    'success' => false,
                    'error' => 'Please wait a few seconds before retrying (in-progress).'
                ];
            }
            // Validate amount
            if ($amount < WPAY_MIN_WITHDRAWAL || $amount > WPAY_MAX_WITHDRAWAL) {
                return [
                    'success' => false,
                    'error' => "Amount must be between ₱" . number_format(WPAY_MIN_WITHDRAWAL) . " and ₱" . number_format(WPAY_MAX_WITHDRAWAL)
                ];
            }

            // Phone verification temporarily disabled
            $userModel = new User();
            $userData = $userModel->getById($userId);
            $phoneVerified = true; // allow withdrawals without phone verification for now
            
            // Validate pay type
            // Accept both simple types (GCASH, MAYA) and bank codes (PH_GCASH, PH_MYA, etc.)
            if (empty($payType) || strlen($payType) < 2) {
                return [
                    'success' => false,
                    'error' => 'Invalid payment type'
                ];
            }
            
            // Check if user has sufficient balance
            $userBalance = $userModel->getBalance($userId);
            
            if ($userBalance < $amount) {
                return [
                    'success' => false,
                    'error' => 'Insufficient balance'
                ];
            }
            
            // Generate order number
            $outTradeNo = $this->generateOrderNo('W');
            
            // Calculate fees
            $fees = $this->calculateFees($amount, 'withdrawal');
            
            // Create transaction record
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
            $stmt = $pdo->prepare("
                INSERT INTO withdrawal_transactions 
                (user_id, out_trade_no, amount, currency, pay_type, account, account_name, bank_code, collection_fee, processing_fee, total_fee, net_amount, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $userId, 
                $outTradeNo, 
                $amount, 
                WPAY_CURRENCY, 
                $payType, 
                $account, 
                $userName, 
                $bankCode,
                $fees['collection_fee'],
                $fees['processing_fee'],
                $fees['total_fee'],
                $fees['net_amount']
            ]);
            
            // DEDUCT BALANCE IMMEDIATELY to prevent race condition
            // If WPay rejects, we'll refund immediately
            $userModel->deductBalance($userId, $amount, 'withdrawal');
            
            // Build request parameters
            $params = [
                'mchId' => $this->mchId,
                'currency' => WPAY_CURRENCY,
                'out_trade_no' => $outTradeNo,
                'pay_type' => $payType,
                'account' => $account,
                'userName' => $userName,
                'money' => (int)$amount, // Convert to integer
                'notify_url' => WPAY_NOTIFY_URL,
            ];
            
            if ($payType === WPAY_PAY_TYPE_NATIVE && $bankCode) {
                $params['bankCode'] = $bankCode;
            }
            
            if ($attach) {
                $params['attach'] = $attach;
            }
            
            // Generate signature
            $params['sign'] = $this->generateSign($params);
            
            // Send request
            $response = $this->sendRequest('/v1/Payout', $params);
            
            if (!$response) {
                throw new Exception('Failed to connect to payment gateway');
            }
            
            // Check response
            $isSuccess = (isset($response['code']) && $response['code'] == 0);
            if ($isSuccess) {
                // Balance already deducted - just update status
                
                // Update transaction with transaction ID
                $stmt = $pdo->prepare("
                    UPDATE withdrawal_transactions 
                    SET transaction_id = ?, status = 'processing'
                    WHERE out_trade_no = ?
                ");
                $stmt->execute([
                    $response['data']['transaction_id'] ?? ($response['data']['transaction_Id'] ?? null),
                    $outTradeNo
                ]);
                
                return [
                    'success' => true,
                    'order_no' => $outTradeNo,
                    'transaction_id' => $response['data']['transaction_id'] ?? ($response['data']['transaction_Id'] ?? null),
                    'amount' => $amount,
                    'pay_type' => $payType,
                    'message' => 'Withdrawal request submitted successfully'
                ];
            } else {
                // WPay rejected - REFUND the balance we deducted
                $userModel->addBalance($userId, $amount, 'Withdrawal refund: ' . $outTradeNo);
                
                $errorCode = $response['code'] ?? -1;
                $errorMsg = getWPayUserMessage($errorCode);
                
                $stmt = $pdo->prepare("
                    UPDATE withdrawal_transactions 
                    SET status = 'failed', notify_data = ?
                    WHERE out_trade_no = ?
                ");
                $stmt->execute([json_encode($response), $outTradeNo]);
                
                return [
                    'success' => false,
                    'error' => $errorMsg,
                    'code' => $errorCode,
                    'technical_error' => $response['msg'] ?? 'Unknown error'
                ];
            }
            
        } catch (Exception $e) {
            error_log("WPay PayOut Error: " . $e->getMessage());
            
            // Refund balance if transaction was created
            if (isset($outTradeNo)) {
                $userModel = new User();
                $userModel->addBalance($userId, $amount, "Withdrawal error refund: {$outTradeNo}");
            }
            
            return [
                'success' => false,
                'error' => 'System error: ' . $e->getMessage()
            ];
        } finally {
            if ($lockAcquired) {
                $this->releaseLock($lockKey);
            }
        }
    }
    
    /**
     * Send HTTP POST request to WPay API
     */
    public function sendRequest(string $endpoint, array $params): ?array {
        $url = $this->host . $endpoint;
        
        // Log request details
        error_log("WPay Request URL: " . $url);
        error_log("WPay Request Params: " . json_encode($params));
        
        $postData = http_build_query($params);
        
        $ch = curl_init($url);
        
        // Essential options for production API
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        // SSL/TLS Configuration
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);   // Verify server certificate
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);      // Verify domain matches cert
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);  // Force TLS 1.2
        
        // Headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: WPay-PHP-Client/1.0'
        ]);
        
        // IPv4 only
        if (defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        
        // Log response
        error_log("WPay Response Code: " . $httpCode);
        error_log("WPay Response Body: " . substr($response, 0, 500));
        
        if ($curlErrno) {
            error_log("WPay cURL Error ({$curlErrno}): {$curlError}");
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        // Handle different HTTP response codes
        if ($httpCode === 403) {
            error_log("WPay HTTP 403: Access Denied. Possible: IP not whitelisted, auth invalid, or rate limited");
            $errorData = json_decode($response, true);
            return $errorData ?: ['code' => 403, 'msg' => 'Access denied'];
        }
        
        if ($httpCode === 401) {
            error_log("WPay HTTP 401: Unauthorized. Check merchant ID and signature");
            return ['code' => 401, 'msg' => 'Unauthorized - check credentials'];
        }
        
        if ($httpCode === 400) {
            error_log("WPay HTTP 400: Bad Request");
            $errorData = json_decode($response, true);
            return $errorData ?: ['code' => 400, 'msg' => 'Bad request'];
        }
        
        if ($httpCode !== 200) {
            error_log("WPay HTTP Error: {$httpCode}");
            return null;
        }
        
        $data = json_decode($response, true);
        
        if ($data === null) {
            error_log("WPay Invalid JSON Response: " . substr($response, 0, 500));
            return null;
        }
        
        return $data;
    }
    
    /**
     * Query deposit/collection status from WPay
     */
    public function queryDeposit(string $outTradeNo): ?array {
        $params = [
            'mchId' => $this->mchId,
            'out_trade_no' => $outTradeNo
        ];
        $params['sign'] = $this->generateSign($params);
        
        return $this->sendRequest('/v1/Query/Collect', $params);
    }
    
    /**
     * Query withdrawal/payout status from WPay
     */
    public function queryWithdrawal(string $outTradeNo): ?array {
        $params = [
            'mchId' => $this->mchId,
            'out_trade_no' => $outTradeNo
        ];
        $params['sign'] = $this->generateSign($params);
        
        return $this->sendRequest('/v1/Query/Payout', $params);
    }
    
    /**
     * Get balance from WPay
     * @param string|null $currency Currency code (empty for all currencies)
     */
    public function getBalance(?string $currency = null): ?array {
        $params = [
            'mchId' => $this->mchId
        ];
        
        if ($currency) {
            $params['currency'] = $currency;
        }
        
        $params['sign'] = $this->generateSign($params);
        
        return $this->sendRequest('/v1/balance', $params);
    }
    
    /**
     * Get bank list for payouts
     */
    public function getBankList(string $currency = 'PHP'): ?array {
        $params = [
            'mchId' => $this->mchId,
            'currency' => $currency
        ];
        $params['sign'] = $this->generateSign($params);
        
        return $this->sendRequest('/v1/PayOut_BankList', $params);
    }
    
    /**
     * Get transaction by order number
     */
    public function getTransaction(string $outTradeNo, string $type = 'deposit'): ?array {
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
            $table = $type === 'deposit' ? 'payment_transactions' : 'withdrawal_transactions';
            
            $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE out_trade_no = ?");
            $stmt->execute([$outTradeNo]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            
        } catch (Exception $e) {
            error_log("Get Transaction Error: " . $e->getMessage());
            return null;
        }
    }
}
?>
