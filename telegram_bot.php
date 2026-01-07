<?php
/**
 * Telegram Bot for Wallet Withdrawal Management
 * Bot: @paldowalletbot
 */
require_once 'config.php';
require_once 'db_helper.php';
require_once 'currency_helper.php';

class TelegramBot {
    private $token = '8592166165:AAFbYYc0LyONdJSNARLPTfurhUT6jU6IKi4';
    private $apiUrl;
    private $pdo;
    
    public function __construct() {
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}/";
        $db = Database::getInstance();
        $this->pdo = $db->getConnection();
    }
    
    /**
     * Send a message to Telegram
     */
    public function sendMessage($chatId, $text, $replyMarkup = null) {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        
        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }
        
        return $this->apiRequest('sendMessage', $data);
    }
    
    /**
     * Send photo to Telegram
     */
    public function sendPhoto($chatId, $photo, $caption = null) {
        $data = [
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ];
        
        return $this->apiRequest('sendPhoto', $data);
    }
    
    /**
     * Edit message text
     */
    public function editMessageText($chatId, $messageId, $text, $replyMarkup = null) {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        
        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }
        
        return $this->apiRequest('editMessageText', $data);
    }
    
    /**
     * Answer callback query
     */
    public function answerCallbackQuery($callbackId, $text = null, $showAlert = false) {
        $data = [
            'callback_query_id' => $callbackId,
            'text' => $text,
            'show_alert' => $showAlert
        ];
        
        return $this->apiRequest('answerCallbackQuery', $data);
    }
    
    /**
     * Make API request to Telegram
     */
    private function apiRequest($method, $data) {
        $ch = curl_init($this->apiUrl . $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true);
    }
    
    /**
     * Send notification about new pending withdrawal
     */
    public function notifyPendingWithdrawal($transactionId) {
        // Get transaction details
        $stmt = $this->pdo->prepare("
            SELECT t.*, u.username, u.phone, u.currency, u.balance as current_balance
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$transactionId]);
        $trans = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$trans) {
            return false;
        }
        
        $currency = $trans['currency'] ?? 'PHP';
        $amount = formatCurrency($trans['amount'], $currency);
        $currentBalance = formatCurrency($trans['current_balance'], $currency);
        $newBalance = formatCurrency($trans['current_balance'] - $trans['amount'], $currency);
        
        $message = "🔔 <b>New Withdrawal Request</b>\n\n";
        $message .= "📤 <b>Type:</b> Withdrawal\n";
        $message .= "👤 <b>User:</b> {$trans['username']}\n";
        $message .= "📱 <b>Phone:</b> {$trans['phone']}\n";
        $message .= "💰 <b>Amount:</b> <code>{$amount}</code>\n";
        $message .= "💳 <b>Current Balance:</b> {$currentBalance}\n";
        $message .= "📊 <b>New Balance:</b> {$newBalance}\n";
        $message .= "📝 <b>Description:</b> {$trans['description']}\n";
        $message .= "🕐 <b>Time:</b> " . date('M d, Y H:i', strtotime($trans['created_at'])) . "\n";
        $message .= "\n<i>Transaction ID: #{$transactionId}</i>";
        
        // Inline keyboard with Approve/Reject buttons
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Approve', 'callback_data' => "approve_{$transactionId}"],
                    ['text' => '❌ Reject', 'callback_data' => "reject_{$transactionId}"]
                ],
                [
                    ['text' => '📊 View Details', 'url' => "https://paldo88.site/admin.php"]
                ]
            ]
        ];
        
        // Get admin chat IDs from database or config
        $chatIds = $this->getAdminChatIds();
        
        foreach ($chatIds as $chatId) {
            $this->sendMessage($chatId, $message, $keyboard);
        }
        
        return true;
    }
    
    /**
     * Get admin chat IDs (stored in database or config)
     */
    private function getAdminChatIds() {
        // Try to get from telegram_admins table first
        try {
            $stmt = $this->pdo->query("SELECT chat_id FROM telegram_admins WHERE is_active = 1");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($results) {
                return array_column($results, 'chat_id');
            }
        } catch (Exception $e) {
            // Table doesn't exist yet, create it
            try {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS telegram_admins (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        chat_id BIGINT NOT NULL UNIQUE,
                        username VARCHAR(255),
                        first_name VARCHAR(255),
                        is_active BOOLEAN DEFAULT 1,
                        registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
            } catch (Exception $e) {
                // Silently fail
            }
        }
        
        return [];
    }
    
    /**
     * Register admin chat ID
     */
    public function registerAdmin($chatId, $username = null, $firstName = null) {
        try {
            // Create table if not exists
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS telegram_admins (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    chat_id BIGINT NOT NULL UNIQUE,
                    username VARCHAR(255),
                    first_name VARCHAR(255),
                    is_active BOOLEAN DEFAULT 1,
                    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Insert or update admin
            $stmt = $this->pdo->prepare("
                INSERT INTO telegram_admins (chat_id, username, first_name, is_active) 
                VALUES (?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE is_active = 1
            ");
            $stmt->execute([$chatId, $username, $firstName]);
            return true;
        } catch (Exception $e) {
            error_log("Telegram admin registration error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Handle incoming webhook updates
     */
    public function handleWebhook() {
        // Send 200 OK immediately to avoid timeout
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        
        $input = file_get_contents('php://input');
        $update = json_decode($input, true);
        
        if (!$update) {
            return;
        }
        
        // Handle callback queries (button clicks)
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        }
        
        // Handle regular messages
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }
    }
    
    /**
     * Handle callback query (button clicks)
     */
    private function handleCallbackQuery($callbackQuery) {
        $chatId = $callbackQuery['message']['chat']['id'];
        $messageId = $callbackQuery['message']['message_id'];
        $callbackId = $callbackQuery['id'];
        $data = $callbackQuery['data'];
        
        // Parse callback data - extract action and transaction ID
        if (preg_match('/^(upload_receipt|confirm_approve|approve|reject|cancel)_(.+)$/', $data, $matches)) {
            $action = $matches[1];
            $transactionId = $matches[2];
        } else {
            error_log("Unknown callback data format: $data");
            $this->answerCallbackQuery($callbackId, 'Invalid action');
            return;
        }
        
        if ($action === 'approve') {
            // Check if receipt is required
            $this->handleApprove($chatId, $messageId, $callbackId, $transactionId);
        } elseif ($action === 'reject') {
            $this->handleReject($chatId, $messageId, $callbackId, $transactionId);
        } elseif ($action === 'upload_receipt') {
            $this->handleUploadReceipt($chatId, $messageId, $callbackId, $transactionId);
        } elseif ($action === 'confirm_approve') {
            $this->confirmApprove($chatId, $messageId, $callbackId, $transactionId);
        } elseif ($action === 'cancel') {
            $this->answerCallbackQuery($callbackId, 'Action cancelled');
            // Restore original buttons
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Approve', 'callback_data' => "approve_{$transactionId}"],
                        ['text' => '❌ Reject', 'callback_data' => "reject_{$transactionId}"]
                    ]
                ]
            ];
            $this->editMessageText($chatId, $messageId, $callbackQuery['message']['text'], $keyboard);
        }
    }
    
    /**
     * Handle approve action
     */
    private function handleApprove($chatId, $messageId, $callbackId, $transactionId) {
        // Check if transaction exists and is pending
        $stmt = $this->pdo->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending'");
        $stmt->execute([$transactionId]);
        $trans = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$trans) {
            $this->answerCallbackQuery($callbackId, '❌ Transaction not found or already processed', true);
            return;
        }
        
        // Show options
        $text = "📤 <b>Approve Withdrawal</b>\n\n";
        $text .= "Transaction ID: #{$transactionId}\n";
        $text .= "Amount: ₱" . number_format($trans['amount'], 2) . "\n\n";
        $text .= "Choose an option:\n";
        $text .= "1️⃣ Approve WITH receipt (upload image)\n";
        $text .= "2️⃣ Approve WITHOUT receipt\n\n";
        $text .= "<i>Note: You can reply with an image to attach receipt</i>";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📷 With Receipt', 'callback_data' => "upload_receipt_{$transactionId}"]
                ],
                [
                    ['text' => '⚠️ Without Receipt', 'callback_data' => "confirm_approve_{$transactionId}"]
                ],
                [
                    ['text' => '🔙 Cancel', 'callback_data' => "cancel_{$transactionId}"]
                ]
            ]
        ];
        
        $this->editMessageText($chatId, $messageId, $text, $keyboard);
        $this->answerCallbackQuery($callbackId, 'Choose an option');
    }
    
    /**
     * Handle receipt upload request
     */
    private function handleUploadReceipt($chatId, $messageId, $callbackId, $transactionId) {
        $text = "📸 <b>Upload Receipt</b>\n\n";
        $text .= "Please send the receipt image as a reply to this message.\n\n";
        $text .= "You can send:\n";
        $text .= "• 📷 Photo from your phone\n";
        $text .= "• 🖼️ Screenshot\n";
        $text .= "• 📄 PDF document\n\n";
        $text .= "After sending the image, it will be automatically attached and the withdrawal will be approved.\n\n";
        $text .= "<i>Or click Cancel to go back</i>";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔙 Cancel', 'callback_data' => "approve_{$transactionId}"]
                ]
            ]
        ];
        
        $this->editMessageText($chatId, $messageId, $text, $keyboard);
        $this->answerCallbackQuery($callbackId);
        
        // Store state in database instead of SESSION
        error_log("Storing pending receipt: transaction=$transactionId, chat=$chatId, message=$messageId");
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO telegram_pending_receipts (transaction_id, chat_id, message_id) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE chat_id = ?, message_id = ?
            ");
            $stmt->execute([$transactionId, $chatId, $messageId, $chatId, $messageId]);
            error_log("Pending receipt stored successfully for transaction: $transactionId");
        } catch (Exception $e) {
            error_log("Error storing receipt state: " . $e->getMessage());
            // Table doesn't exist, create it
            try {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS telegram_pending_receipts (
                        transaction_id INT PRIMARY KEY,
                        chat_id BIGINT NOT NULL,
                        message_id INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                $stmt = $this->pdo->prepare("
                    INSERT INTO telegram_pending_receipts (transaction_id, chat_id, message_id) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$transactionId, $chatId, $messageId]);
                error_log("Table created and pending receipt stored for transaction: $transactionId");
            } catch (Exception $e2) {
                error_log("Fatal error storing receipt state: " . $e2->getMessage());
                $this->sendMessage($chatId, "❌ Database error: " . $e2->getMessage());
            }
        }
    }
    
    /**
     * Confirm approval without receipt
     */
    private function confirmApprove($chatId, $messageId, $callbackId, $transactionId) {
        $result = $this->processApproval($transactionId, null);
        
        if ($result['success']) {
            $this->answerCallbackQuery($callbackId, '✅ Withdrawal approved successfully!', true);
            $this->editMessageText($chatId, $messageId, 
                "✅ <b>Approved</b>\n\n" . 
                "Transaction #{$transactionId} has been approved.\n" .
                "⚠️ No receipt attached.", null);
        } else {
            $this->answerCallbackQuery($callbackId, '❌ Error: ' . $result['error'], true);
        }
    }
    
    /**
     * Handle reject action
     */
    private function handleReject($chatId, $messageId, $callbackId, $transactionId) {
        // Ask for rejection reason
        $text = "❌ <b>Reject Withdrawal</b>\n\n";
        $text .= "Please send the reason for rejection as a text message.\n\n";
        $text .= "<i>Or click Cancel to go back.</i>";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔙 Cancel', 'callback_data' => "cancel_{$transactionId}"]
                ]
            ]
        ];
        
        $this->editMessageText($chatId, $messageId, $text, $keyboard);
        $this->answerCallbackQuery($callbackId, 'Please send rejection reason');
        
        // Store pending rejection state in database instead of SESSION
        try {
            $this->pdo->prepare("
                INSERT INTO telegram_pending_rejections (transaction_id, chat_id, message_id) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE chat_id = ?, message_id = ?
            ")->execute([$transactionId, $chatId, $messageId, $chatId, $messageId]);
        } catch (Exception $e) {
            // Table doesn't exist, create it
            try {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS telegram_pending_rejections (
                        transaction_id INT PRIMARY KEY,
                        chat_id BIGINT NOT NULL,
                        message_id INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                $this->pdo->prepare("
                    INSERT INTO telegram_pending_rejections (transaction_id, chat_id, message_id) 
                    VALUES (?, ?, ?)
                ")->execute([$transactionId, $chatId, $messageId]);
            } catch (Exception $e) {
                $this->sendMessage($chatId, "❌ Error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Handle regular messages
     */
    private function handleMessage($message) {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $username = $message['from']['username'] ?? null;
        $firstName = $message['from']['first_name'] ?? null;
        
        // Handle commands
        if (strpos($text, '/start') === 0) {
            $this->registerAdmin($chatId, $username, $firstName);
            $this->sendMessage($chatId, 
                "👋 <b>Welcome to Paldo Wallet Bot!</b>\n\n" .
                "You will receive notifications for pending withdrawals.\n\n" .
                "<b>Commands:</b>\n" .
                "/pending - View pending withdrawals\n" .
                "/help - Show help message"
            );
        } elseif (strpos($text, '/pending') === 0) {
            $this->showPendingWithdrawals($chatId);
        } elseif (strpos($text, '/help') === 0) {
            $this->sendMessage($chatId,
                "<b>📖 Help - Paldo Wallet Bot</b>\n\n" .
                "<b>Features:</b>\n" .
                "• Receive real-time withdrawal notifications\n" .
                "• Approve/reject withdrawals directly from Telegram\n" .
                "• Upload proof of payment receipts\n\n" .
                "<b>Commands:</b>\n" .
                "/pending - View all pending withdrawals\n" .
                "/help - Show this help message\n\n" .
                "<b>How to approve with receipt:</b>\n" .
                "1. Click ✅ Approve on notification\n" .
                "2. Send receipt image\n" .
                "3. Reply with /confirm_{transaction_id}"
            );
        }
        
        // Handle photo uploads (receipts)
        if (isset($message['photo'])) {
            $this->handleReceiptUpload($chatId, $message);
            return; // Don't process as text message
        }
        
        // Handle rejection reasons (skip if it's a command or empty)
        if (!empty($text) && strpos($text, '/') !== 0) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT transaction_id, message_id FROM telegram_pending_rejections 
                    WHERE chat_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([$chatId]);
                $pending = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($pending) {
                    $transactionId = $pending['transaction_id'];
                    $messageId = $pending['message_id'];
                    
                    // Process rejection with reason
                    $result = $this->processRejection($transactionId, $text, $chatId);
                    
                    if ($result['success']) {
                        $this->sendMessage($chatId, "✅ <b>Rejection Processed</b>\n\n" . 
                            "Transaction #{$transactionId} has been rejected.\n" .
                            "Reason: {$text}");
                        
                        $this->editMessageText($chatId, $messageId, 
                            "❌ <b>Rejected</b>\n\n" . 
                            "Transaction #{$transactionId} has been rejected.\n" .
                            "Reason: {$text}", null);
                        
                        // Clean up pending rejection
                        $this->pdo->prepare("DELETE FROM telegram_pending_rejections WHERE transaction_id = ?")
                            ->execute([$transactionId]);
                    } else {
                        $this->sendMessage($chatId, "❌ Error: " . $result['error']);
                    }
                }
            } catch (Exception $e) {
                // Log error but continue
                error_log("Rejection handler error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Handle receipt upload
     */
    private function handleReceiptUpload($chatId, $message) {
        error_log("Receipt upload started for chat_id: $chatId");
        
        $photo = end($message['photo']); // Get largest photo
        $fileId = $photo['file_id'];
        
        // Get file path
        $fileInfo = $this->apiRequest('getFile', ['file_id' => $fileId]);
        
        if ($fileInfo['ok']) {
            $filePath = $fileInfo['result']['file_path'];
            $fileUrl = "https://api.telegram.org/file/bot{$this->token}/{$filePath}";
            error_log("File URL: $fileUrl");
            
            // Download and save file
            $receiptPath = $this->downloadReceipt($fileUrl);
            
            if ($receiptPath) {
                error_log("Receipt downloaded: $receiptPath");
                
                // Check if there's a pending receipt in database
                try {
                    $stmt = $this->pdo->prepare("
                        SELECT transaction_id, message_id FROM telegram_pending_receipts 
                        WHERE chat_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 1
                    ");
                    $stmt->execute([$chatId]);
                    $pending = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($pending) {
                        $transactionId = $pending['transaction_id'];
                        $messageId = $pending['message_id'];
                        error_log("Found pending receipt for transaction: $transactionId");
                        
                        // Process approval with receipt
                        $result = $this->processApproval($transactionId, $receiptPath);
                        
                        if ($result['success']) {
                            $this->sendMessage($chatId, "✅ Withdrawal approved successfully with receipt!");
                            $this->editMessageText($chatId, $messageId, 
                                "✅ <b>Approved with Receipt</b>\n\n" . 
                                "Transaction #{$transactionId} has been approved.", null);
                            
                            // Clean up pending receipt
                            $this->pdo->prepare("DELETE FROM telegram_pending_receipts WHERE transaction_id = ?")
                                ->execute([$transactionId]);
                        } else {
                            error_log("Process approval failed: " . $result['error']);
                            $this->sendMessage($chatId, "❌ Error: " . $result['error']);
                        }
                    } else {
                        error_log("No pending receipt found for chat_id: $chatId");
                        $this->sendMessage($chatId, "❌ No pending receipt upload found. Please click 'With Receipt' button first.");
                    }
                } catch (Exception $e) {
                    error_log("Receipt upload database error: " . $e->getMessage());
                    $this->sendMessage($chatId, "❌ Database error: " . $e->getMessage());
                }
            } else {
                error_log("Failed to download receipt from: $fileUrl");
                $this->sendMessage($chatId, "❌ Failed to download receipt image. Please try again.");
            }
        } else {
            error_log("Failed to get file info from Telegram. Response: " . json_encode($fileInfo));
            $this->sendMessage($chatId, "❌ Failed to get file info from Telegram.");
        }
    }
    
    /**
     * Download receipt from Telegram
     */
    private function downloadReceipt($fileUrl) {
        $uploadDir = 'uploads/receipts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = 'receipt_telegram_' . time() . '_' . rand(1000, 9999) . '.jpg';
        $targetPath = $uploadDir . $fileName;
        
        // Use curl for more reliable downloads
        $ch = curl_init($fileUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $fileContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($fileContent === false) {
            error_log("Curl error downloading receipt: $curlError");
            return null;
        }
        
        if ($httpCode !== 200) {
            error_log("HTTP error downloading receipt: HTTP $httpCode");
            return null;
        }
        
        if (file_put_contents($targetPath, $fileContent)) {
            error_log("Receipt saved successfully: $targetPath");
            return $targetPath;
        }
        
        error_log("Failed to save receipt to: $targetPath");
        return null;
    }
    
    /**
     * Process withdrawal approval
     */
    private function processApproval($transactionId, $receiptPath = null) {
        try {
            // Get transaction
            $stmt = $this->pdo->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending'");
            $stmt->execute([$transactionId]);
            $trans = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$trans) {
                return ['success' => false, 'error' => 'Transaction not found or already processed'];
            }
            
            $this->pdo->beginTransaction();
            
            // Get current balance
            $stmt = $this->pdo->prepare("SELECT balance FROM users WHERE id = ?");
            $stmt->execute([$trans['user_id']]);
            $currentBalance = $stmt->fetchColumn();
            
            $newBalance = $currentBalance - $trans['amount'];
            
            if ($newBalance < 0) {
                throw new Exception('Insufficient balance for withdrawal');
            }
            
            // Update user balance
            $stmt = $this->pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $stmt->execute([$newBalance, $trans['user_id']]);
            
            // Update transaction
            $stmt = $this->pdo->prepare("
                UPDATE transactions 
                SET status = 'completed',
                    balance_before = ?,
                    balance_after = ?,
                    receipt_image = ?
                WHERE id = ?
            ");
            $stmt->execute([$currentBalance, $newBalance, $receiptPath, $transactionId]);
            
            $this->pdo->commit();
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Process withdrawal rejection
     */
    private function processRejection($transactionId, $reason, $chatId = null) {
        try {
            // Get transaction to verify it exists
            $stmt = $this->pdo->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending'");
            $stmt->execute([$transactionId]);
            $trans = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$trans) {
                error_log("Transaction not found: ID=$transactionId");
                return ['success' => false, 'error' => 'Transaction not found or already processed'];
            }
            
            // If no chatId provided, try to get from pending rejection table
            if (!$chatId) {
                $stmt = $this->pdo->prepare("SELECT chat_id FROM telegram_pending_rejections WHERE transaction_id = ?");
                $stmt->execute([$transactionId]);
                $pending = $stmt->fetch(PDO::FETCH_ASSOC);
                $chatId = $pending['chat_id'] ?? null;
            }
            
            // Update transaction status
            $stmt = $this->pdo->prepare("
                UPDATE transactions 
                SET status = 'failed',
                    description = CONCAT(COALESCE(description, ''), ' - Rejected: ', ?)
                WHERE id = ? AND status = 'pending'
            ");
            $executed = $stmt->execute([$reason, $transactionId]);
            
            if (!$executed || $stmt->rowCount() === 0) {
                error_log("Failed to update transaction: ID=$transactionId");
                return ['success' => false, 'error' => 'Failed to update transaction status'];
            }
            
            error_log("Transaction rejected successfully: ID=$transactionId");
            
            return ['success' => true];
        } catch (Exception $e) {
            error_log("processRejection error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Show pending withdrawals
     */
    private function showPendingWithdrawals($chatId) {
        $stmt = $this->pdo->query("
            SELECT t.*, u.username, u.phone, u.currency
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            WHERE t.status = 'pending' AND t.type = 'withdrawal'
            ORDER BY t.created_at ASC
        ");
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($pending)) {
            $this->sendMessage($chatId, "✅ No pending withdrawals!");
            return;
        }
        
        $message = "📋 <b>Pending Withdrawals ({count})</b>\n\n";
        $message = str_replace('{count}', count($pending), $message);
        
        foreach ($pending as $trans) {
            $currency = $trans['currency'] ?? 'PHP';
            $amount = formatCurrency($trans['amount'], $currency);
            
            $message .= "━━━━━━━━━━━━━━━━\n";
            $message .= "🆔 #{$trans['id']}\n";
            $message .= "👤 {$trans['username']}\n";
            $message .= "💰 <code>{$amount}</code>\n";
            $message .= "🕐 " . date('M d, H:i', strtotime($trans['created_at'])) . "\n\n";
        }
        
        $this->sendMessage($chatId, $message);
    }
    
    /**
     * Set webhook URL
     */
    public function setWebhook($url) {
        return $this->apiRequest('setWebhook', ['url' => $url]);
    }
    
    /**
     * Delete webhook
     */
    public function deleteWebhook() {
        return $this->apiRequest('deleteWebhook', []);
    }
}
?>
