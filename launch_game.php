<?php
/**
 * Launch Game API - Start a Game Session
 * 
 * This script launches a game session with encrypted user data.
 * It sends player information securely to SoftAPI and returns a game URL.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

/**
 * Launch a game for a specific user
 * 
 * @param string $userId Player's unique ID
 * @param float|string $balance Player's wallet balance
 * @param string|null $gameUid Optional unique game session ID (auto-generated if null)
 * @param string|null $currencyCode Optional currency code (e.g., BDT, USD) - Code will work without this
 * @param string|null $language Optional language code (e.g., bn, en) - Code will work without this
 * @return array Response with game URL or error
 */
function launchGame(
    string $userId, 
    $balance, 
    ?string $gameUid = null,
    ?string $currencyCode = null,
    ?string $language = null
): array {
    try {
        // Generate unique game UID if not provided
        if ($gameUid === null) {
            $gameUid = generateGameUID();
        }
        
        // Prepare the data payload
        $payload = [
            "user_id"   => $userId,
            "balance"   => $balance,
            "game_uid"  => $gameUid,
            "token"     => API_TOKEN,
            "timestamp" => round(microtime(true) * 1000), // current time in milliseconds
            "return"    => RETURN_URL,
            "callback"  => CALLBACK_URL
        ];
        
        // Add optional parameters if provided
        if ($currencyCode !== null) {
            $payload["currency_code"] = $currencyCode;
        }
        if ($language !== null) {
            $payload["language"] = $language;
        }
        
        // Encrypt the payload
        $encrypted = ENCRYPT_PAYLOAD_ECB($payload, API_SECRET);
        
        // Build the API URL
        $url = SERVER_URL . "?payload=" . urlencode($encrypted) . "&token=" . urlencode(API_TOKEN);
        
        // Log the request
        logMessage("Launching game for user {$userId}, game_uid: {$gameUid}", 'info');
        
        // Send request to SoftAPI
        $response = sendGetRequest($url);
        
        // Decode the response
        $data = json_decode($response, true);
        
        if ($data === null) {
            throw new Exception("Invalid JSON response from API");
        }
        
        // Check if successful
        if (isset($data["code"]) && $data["code"] == 0) {
            logMessage("Game launched successfully for user {$userId}", 'info');
            return [
                'success' => true,
                'game_url' => $data["data"]["url"],
                'game_uid' => $gameUid,
                'message' => 'Game launched successfully'
            ];
        } else {
            $errorMsg = $data["msg"] ?? "Unknown error";
            logMessage("Failed to launch game for user {$userId}: {$errorMsg}", 'error');
            return [
                'success' => false,
                'message' => $errorMsg,
                'code' => $data["code"] ?? -1
            ];
        }
        
    } catch (Exception $e) {
        logMessage("Exception launching game: " . $e->getMessage(), 'error');
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// ==========================================
// Example Usage (if accessed directly)
// ==========================================
if (php_sapi_name() !== 'cli' && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Get parameters from request
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $balance = isset($_GET['balance']) ? (float)$_GET['balance'] : null;
    $gameUid = isset($_GET['game_uid']) ? (int)$_GET['game_uid'] : null;
    $currencyCode = $_GET['currency_code'] ?? null;
    $language = $_GET['language'] ?? null;
    
    // Validate required parameters
    if ($userId === null || $balance === null) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters: user_id and balance are required'
        ]);
        exit;
    }
    
    // Launch the game
    $result = launchGame($userId, $balance, $gameUid, $currencyCode, $language);
    
    // Return JSON response
    echo json_encode($result, JSON_PRETTY_PRINT);
}

?>
