<?php
/**
 * Test Evolution Live Game Launch
 * Tests API integration before adding to database
 */

require_once 'session_config.php';

if (!isset($_SESSION['user_id'])) {
    die('Please login first: <a href="login.php">Login</a>');
}

require_once 'config.php';
require_once 'db_helper.php';
require_once 'api_request_builder.php';
require_once 'currency_helper.php';

$userModel = new User();
$currentUser = $userModel->getById($_SESSION['user_id']);
$balance = $userModel->getBalanceFresh($_SESSION['user_id']);
$balanceToSend = $balance;
$userCurrency = isset($_GET['currency']) && is_string($_GET['currency'])
    ? strtoupper(trim($_GET['currency']))
    : 'PHP';
$currencySymbolMap = [
    'INR' => '₹',
    'PHP' => '₱',
    'USD' => '$',
];
$currencySymbol = $currencySymbolMap[$userCurrency] ?? '';
$userId = (string)$_SESSION['user_id'];

// Use passed currency in payload
$payloadCurrency = $userCurrency;

// Always use session user
$payloadUserId = $userId;

// Test Evolution Live Lobby
$gameId = isset($_GET['game_id']) && ctype_digit($_GET['game_id'])
    ? $_GET['game_id']
    : '8205';
$gameName = 'Evolution Live Lobby';

echo "<h2>Testing Evolution Live Game Launch</h2>";
echo "<p><strong>Game ID:</strong> {$gameId}</p>";
echo "<p><strong>Game Name:</strong> {$gameName}</p>";
echo "<p><strong>User ID:</strong> {$userId}</p>";
echo "<p><strong>Balance:</strong> " . ($currencySymbol ?: $userCurrency . ' ') . number_format($balance, 2) . "</p>";
echo "<p><strong>Payload Currency:</strong> {$payloadCurrency}</p>";echo "<hr>";

// Create launch request
$params = createGameLaunchRequest(
    userId: $payloadUserId,
    balance: $balanceToSend,
    gameUid: $gameId,
    currencyCode: $payloadCurrency,
    language: 'en'
);

echo "<h3>Request Parameters:</h3>";
echo "<pre>" . print_r($params, true) . "</pre>";
echo "<hr>";

// Send request
$result = sendLaunchGameRequest($params);

echo "<h3>API Response:</h3>";
echo "<pre>" . print_r($result, true) . "</pre>";
echo "<hr>";

if ($result['success']) {
    echo "<h3 style='color: green;'>✅ SUCCESS!</h3>";
    echo "<p>Game URL generated successfully.</p>";
    echo "<p><a href='{$result['game_url']}' target='_blank' style='padding: 10px 20px; background: #22c55e; color: white; text-decoration: none; border-radius: 8px;'>Launch Game in New Tab</a></p>";
    echo "<hr>";
    echo "<h4>Ready to add to database?</h4>";
    echo "<p>If the game loads correctly, you can add it to the games table.</p>";
} else {
    echo "<h3 style='color: red;'>❌ FAILED</h3>";
    echo "<p><strong>Error:</strong> {$result['error']}</p>";
    if (isset($result['error_code'])) {
        echo "<p><strong>Error Code:</strong> {$result['error_code']}</p>";
    }
    if (isset($result['http_code'])) {
        echo "<p><strong>HTTP Code:</strong> {$result['http_code']}</p>";
    }
}

echo "<br><br>";
echo "<a href='index.php'>← Back to Lobby</a>";
?>
