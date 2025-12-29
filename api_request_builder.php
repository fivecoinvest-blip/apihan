<?php
/**
 * API Request Builder - Launch Game with All Parameters
 * 
 * This file demonstrates how to send all request parameters to SoftAPI
 * as specified in the documentation.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

/**
 * Build and send complete API request with all parameters
 * 
 * @param array $params Request parameters
 * @return array API response
 */
function sendLaunchGameRequest(array $params): array {
    
    // ==========================================
    // STEP 1: Validate Required Parameters
    // ==========================================
    $required = ['user_id', 'balance', 'game_uid', 'token', 'timestamp', 'return', 'callback'];
    
    foreach ($required as $field) {
        if (!isset($params[$field]) || $params[$field] === '') {
            return [
                'success' => false,
                'error' => "Missing required parameter: {$field}"
            ];
        }
    }
    
    // ==========================================
    // STEP 2: Prepare Request Payload
    // ==========================================
    $payload = [
        'user_id'   => (string)$params['user_id'],   // The unique ID of the player
        'balance'   => (string)$params['balance'],    // The player's wallet balance
        'game_uid'  => (string)$params['game_uid'],   // A unique ID for this game session
        'token'     => $params['token'],              // Your API token
        'timestamp' => (int)$params['timestamp'],     // Current time (in milliseconds)
        'return'    => $params['return'],             // URL where user returns after playing
        'callback'  => $params['callback']            // URL where game sends result
    ];
    
    // Add optional parameters if provided
    if (!empty($params['currency_code'])) {
        $payload['currency_code'] = $params['currency_code']; // (Optional) Currency code
    }
    
    if (!empty($params['language'])) {
        $payload['language'] = $params['language'];           // (Optional) Language code
    }
    
    // ==========================================
    // STEP 3: Encrypt Payload using AES-256-ECB
    // ==========================================
    try {
        $encryptedPayload = ENCRYPT_PAYLOAD_ECB($payload, API_SECRET);
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Encryption failed: ' . $e->getMessage()
        ];
    }
    
    // ==========================================
    // STEP 4: Build API URL with Query Parameters
    // ==========================================
    $apiUrl = SERVER_URL . "?payload=" . urlencode($encryptedPayload) . "&token=" . urlencode($params['token']);
    
    // ==========================================
    // STEP 5: Send HTTP GET Request
    // ==========================================
    try {
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 60,           // Increased timeout to 60 seconds
            CURLOPT_CONNECTTIMEOUT => 30,    // Connection timeout
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }
        
        curl_close($ch);
        
        // ==========================================
        // STEP 6: Parse Response
        // ==========================================
        $data = json_decode($response, true);
        
        if ($data === null) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response',
                'raw_response' => $response
            ];
        }
        
        // ==========================================
        // STEP 7: Return Result
        // ==========================================
        if (isset($data['code']) && $data['code'] == 0) {
            return [
                'success' => true,
                'game_url' => $data['data']['url'] ?? '',
                'data' => $data,
                'http_code' => $httpCode
            ];
        } else {
            return [
                'success' => false,
                'error' => $data['msg'] ?? 'Unknown error',
                'error_code' => $data['code'] ?? -1,
                'http_code' => $httpCode
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Request failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Example: Create request with all parameters from documentation
 * 
 * @param string $userId User's unique ID in your system (e.g., '23213')
 * @param float|string $balance User's current balance (e.g., 40 or '40')
 * @param string|null $gameUid Unique ID for this game session (e.g., '3978')
 * @param string|null $currencyCode Optional: Currency code (e.g., 'BDT')
 * @param string|null $language Optional: Language code (e.g., 'bn')
 */
function createGameLaunchRequest(
    string $userId,
    $balance,
    ?string $gameUid = null,
    ?string $currencyCode = null,
    ?string $language = null
): array {
    
    // Use default game_uid if not provided
    if ($gameUid === null) {
        $gameUid = '634';
    }
    
    // Build request parameters exactly as shown in documentation
    $params = [
        'user_id'   => $userId,                          // Example: '23213'
        'balance'   => $balance,                         // Example: 40 or '40'
        'game_uid'  => $gameUid,                         // Example: '3978'
        'token'     => API_TOKEN,                        // Your API token
        'timestamp' => round(microtime(true) * 1000),    // Current timestamp in milliseconds
        'return'    => RETURN_URL,                       // Return URL after game closes
        'callback'  => CALLBACK_URL,                     // Callback URL for game results
    ];
    
    // Add optional parameters (code will work without these)
    if ($currencyCode !== null) {
        $params['currency_code'] = $currencyCode;        // Optional: Currency code
    }
    
    if ($language !== null) {
        $params['language'] = $language;                 // Optional: Language code
    }
    
    return $params;
}

// ==========================================
// Example Usage (Direct Access)
// ==========================================
if (php_sapi_name() !== 'cli' && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Example from documentation:
    // user_id: '23213'
    // balance: 40
    // game_uid: '3978'
    // currency_code: 'BDT' (optional)
    // language: 'bn' (optional)
    
    $params = createGameLaunchRequest(
        userId: '23213',
        balance: 40,
        gameUid: '3978',
        currencyCode: 'BDT',
        language: 'bn'
    );
    
    echo json_encode([
        'title' => 'Request Parameters',
        'description' => 'What you send in the request',
        'parameters' => $params,
        'parameter_descriptions' => [
            'user_id' => 'The unique ID of the player',
            'balance' => 'The player\'s wallet balance',
            'game_uid' => 'A unique ID for this game session',
            'token' => 'Your API token',
            'timestamp' => 'Current time (in milliseconds)',
            'return' => 'URL where the user returns after playing',
            'callback' => 'URL where the game sends result',
            'currency_code' => '(Optional) Currency code',
            'language' => '(Optional) Language code'
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

?>
