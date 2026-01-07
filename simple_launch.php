<?php
/**
 * Simple Launch Game API Implementation
 * Matches the official documentation exactly
 * 
 * GET https://igamingapis.live/api/v1
 * 
 * This API is used to start a game session for a user.
 * You send user info, balance, and session ID to the server,
 * and it returns a URL to launch the game.
 * 
 * Note: The parameters currency_code and language are optional.
 * Your code will work perfectly fine without passing these parameters.
 */

// ==========================================
// API Credentials
// ==========================================
$TOKEN  = '5cd0be9827c469e7ce7d07abbb239e98';
$SECRET = 'dc6b955933342d32d49b84c52b59184f'; // Must be 32 bytes
$SERVER_URL = 'https://igamingapis.live/api/v1';
$RETURN_URL = 'https://31.97.107.21/return';
$CALLBACK_URL = 'https://31.97.107.21/callback.php';

// ==========================================
// Data to Send
// ==========================================
$PAYLOAD = [
    'user_id' => '23213',                           // Unique user ID in your system
    'balance' => '40',                              // User's current balance
    'game_uid' => '3978',                           // Unique ID for this game session
    'token' => $TOKEN,                              // Your API token
    'timestamp' => round(microtime(true) * 1000),   // Current timestamp in milliseconds
    'return' => $RETURN_URL,                        // Return URL after game closes
    'callback' => $CALLBACK_URL                     // Callback URL for game results
    
    // Optional parameters (can be omitted - code will work without them):
    // 'currency_code' => 'BDT',                    // Optional: Currency code
    // 'language' => 'bn'                           // Optional: Language code
];

// ==========================================
// Encryption Function using AES-256-ECB
// ==========================================
function ENCRYPT_PAYLOAD_ECB(array $DATA, string $KEY): string {
    $JSON = json_encode($DATA);
    $ENC  = openssl_encrypt($JSON, 'AES-256-ECB', $KEY, OPENSSL_RAW_DATA);
    return base64_encode($ENC);
}

// ==========================================
// Encrypt Payload
// ==========================================
$ENCRYPTED = ENCRYPT_PAYLOAD_ECB($PAYLOAD, $SECRET);

// ==========================================
// Prepare Full URL with Payload and Token
// ==========================================
$URL = $SERVER_URL . '?payload=' . urlencode($ENCRYPTED) . '&token=' . urlencode($TOKEN);

// ==========================================
// Send Request to API
// ==========================================
$response = file_get_contents($URL);

// ==========================================
// Show API Response
// ==========================================
echo "API Response:\n";
print_r(json_decode($response, true));

// ==========================================
// Expected Success Response:
// ==========================================
// {
//   "code": 0,
//   "msg": "Game launched successfully",
//   "data": {
//     "url": "https://igamingapis.live/launch?de=abcdef12345&game_name=Sweet+Magic"
//   }
// }

?>
