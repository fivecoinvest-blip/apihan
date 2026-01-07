<?php
/**
 * Direct API Test - Debug what we're sending
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// Test parameters
$payload = [
    'user_id'   => '12345',
    'balance'   => '1000',
    'game_uid'  => '634',
    'token'     => API_TOKEN,
    'timestamp' => round(microtime(true) * 1000),
    'return'    => RETURN_URL,
    'callback'  => CALLBACK_URL
];

echo "<h2>Testing SoftAPI Integration</h2>";
echo "<h3>1. Configuration Check:</h3>";
echo "<pre>";
echo "API Token: " . API_TOKEN . "\n";
echo "API Secret Length: " . strlen(API_SECRET) . " bytes\n";
echo "Server URL: " . SERVER_URL . "\n";
echo "Return URL: " . RETURN_URL . "\n";
echo "Callback URL: " . CALLBACK_URL . "\n";
echo "</pre>";

echo "<h3>2. Payload (before encryption):</h3>";
echo "<pre>" . json_encode($payload, JSON_PRETTY_PRINT) . "</pre>";

// Encrypt
$encrypted = ENCRYPT_PAYLOAD_ECB($payload, API_SECRET);
echo "<h3>3. Encrypted Payload:</h3>";
echo "<pre>" . htmlspecialchars($encrypted) . "</pre>";

// Build URL
$apiUrl = SERVER_URL . "?payload=" . urlencode($encrypted) . "&token=" . urlencode(API_TOKEN);
echo "<h3>4. Full API URL:</h3>";
echo "<pre>" . htmlspecialchars($apiUrl) . "</pre>";

// Make request
echo "<h3>5. API Response:</h3>";
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<strong>HTTP Code:</strong> " . $httpCode . "<br>";
if ($error) {
    echo "<strong style='color:red'>cURL Error:</strong> " . $error . "<br>";
}
echo "<strong>Response:</strong><br>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

$data = json_decode($response, true);
if ($data) {
    echo "<h3>6. Parsed Response:</h3>";
    echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
}
?>
