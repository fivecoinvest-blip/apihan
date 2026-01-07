<?php
/**
 * Test Launch Game with Specific Parameters
 * Demonstrates sending a request with all parameters as shown in documentation
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// Set response header for web access
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
}

echo "<h2>🎮 SoftAPI Game Launch Test</h2>\n";
echo "<hr>\n";

// ==========================================
// STEP 1: Prepare Request Parameters
// ==========================================
echo "<h3>Step 1: Request Parameters</h3>\n";

$parameters = [
    'user_id'       => 101,                    // The unique ID of the player
    'balance'       => 500,                    // The player's wallet balance
    'game_uid'      => 784512,                 // A unique ID for this game session
    'token'         => API_TOKEN,              // Your API token
    'timestamp'     => round(microtime(true) * 1000), // Current time (in milliseconds)
    'return'        => RETURN_URL,             // URL where the user returns after playing
    'callback'      => CALLBACK_URL,           // URL where the game sends result
    'currency_code' => 'BDT',                  // (Optional) Currency code
    'language'      => 'bn'                    // (Optional) Language code
];

echo "<pre>";
echo "user_id       : " . $parameters['user_id'] . " (The unique ID of the player)\n";
echo "balance       : " . $parameters['balance'] . " (The player's wallet balance)\n";
echo "game_uid      : " . $parameters['game_uid'] . " (A unique ID for this game session)\n";
echo "token         : " . $parameters['token'] . " (Your API token)\n";
echo "timestamp     : " . $parameters['timestamp'] . " (Current time in milliseconds)\n";
echo "return        : " . $parameters['return'] . " (URL where user returns after playing)\n";
echo "callback      : " . $parameters['callback'] . " (URL where game sends result)\n";
echo "currency_code : " . $parameters['currency_code'] . " (Optional - Currency code)\n";
echo "language      : " . $parameters['language'] . " (Optional - Language code)\n";
echo "</pre>\n";

// ==========================================
// STEP 2: Encrypt Payload
// ==========================================
echo "<h3>Step 2: Encrypt Payload using AES-256-ECB</h3>\n";

try {
    $encryptedPayload = ENCRYPT_PAYLOAD_ECB($parameters, API_SECRET);
    echo "<pre>";
    echo "✅ Payload encrypted successfully!\n";
    echo "Encrypted data (Base64): " . substr($encryptedPayload, 0, 100) . "...\n";
    echo "Length: " . strlen($encryptedPayload) . " characters\n";
    echo "</pre>\n";
} catch (Exception $e) {
    echo "<pre>❌ Encryption failed: " . $e->getMessage() . "</pre>\n";
    exit;
}

// ==========================================
// STEP 3: Build API URL
// ==========================================
echo "<h3>Step 3: Build API Request URL</h3>\n";

$apiUrl = SERVER_URL . "?payload=" . urlencode($encryptedPayload) . "&token=" . urlencode(API_TOKEN);
echo "<pre>";
echo "API Endpoint: " . SERVER_URL . "\n";
echo "Full URL: " . substr($apiUrl, 0, 150) . "...\n";
echo "</pre>\n";

// ==========================================
// STEP 4: Send Request to SoftAPI
// ==========================================
echo "<h3>Step 4: Send Request to SoftAPI Server</h3>\n";

try {
    logMessage("Sending test launch request for user {$parameters['user_id']}", 'info');
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: SoftAPI-Client/1.0'
    ]);
    
    echo "<pre>📡 Sending request to SoftAPI...</pre>\n";
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        throw new Exception("cURL Error: " . curl_error($ch));
    }
    
    curl_close($ch);
    
    echo "<pre>";
    echo "✅ Response received!\n";
    echo "HTTP Status Code: " . $httpCode . "\n";
    echo "</pre>\n";
    
} catch (Exception $e) {
    echo "<pre>❌ Request failed: " . $e->getMessage() . "</pre>\n";
    logMessage("Test launch request failed: " . $e->getMessage(), 'error');
    exit;
}

// ==========================================
// STEP 5: Process Response
// ==========================================
echo "<h3>Step 5: Process API Response</h3>\n";

$responseData = json_decode($response, true);

if ($responseData === null) {
    echo "<pre>❌ Invalid JSON response</pre>\n";
    echo "<pre>Raw response: " . htmlspecialchars($response) . "</pre>\n";
    logMessage("Invalid JSON response received", 'error');
    exit;
}

echo "<pre>";
echo "Response Data:\n";
echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n</pre>\n";

// ==========================================
// STEP 6: Check Result
// ==========================================
echo "<h3>Step 6: Launch Result</h3>\n";

if (isset($responseData['code']) && $responseData['code'] == 0) {
    // Success
    $gameUrl = $responseData['data']['url'] ?? '';
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>\n";
    echo "<h4 style='color: #155724; margin: 0 0 10px 0;'>✅ Game Launched Successfully!</h4>\n";
    echo "<p><strong>Game URL:</strong><br><a href='{$gameUrl}' target='_blank' style='word-break: break-all;'>{$gameUrl}</a></p>\n";
    echo "<p><strong>User ID:</strong> {$parameters['user_id']}</p>\n";
    echo "<p><strong>Game UID:</strong> {$parameters['game_uid']}</p>\n";
    echo "<p><strong>Balance:</strong> {$parameters['balance']} {$parameters['currency_code']}</p>\n";
    echo "<p><strong>Language:</strong> {$parameters['language']}</p>\n";
    echo "<p style='margin-top: 15px;'><a href='{$gameUrl}' target='_blank' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>🎮 Click Here to Play Game</a></p>\n";
    echo "</div>\n";
    
    logMessage("Test launch successful - Game URL: {$gameUrl}", 'info');
    
} else {
    // Error
    $errorMsg = $responseData['msg'] ?? 'Unknown error';
    $errorCode = $responseData['code'] ?? -1;
    
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px;'>\n";
    echo "<h4 style='color: #721c24; margin: 0 0 10px 0;'>❌ Launch Failed</h4>\n";
    echo "<p><strong>Error Code:</strong> {$errorCode}</p>\n";
    echo "<p><strong>Error Message:</strong> {$errorMsg}</p>\n";
    echo "</div>\n";
    
    logMessage("Test launch failed - Code: {$errorCode}, Message: {$errorMsg}", 'error');
}

echo "\n<hr>\n";
echo "<p><small>Test executed at: " . date('Y-m-d H:i:s') . "</small></p>\n";

?>
