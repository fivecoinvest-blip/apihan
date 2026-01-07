<?php
/**
 * Example: Complete Game Launch Implementation
 * 
 * This demonstrates how to use the launch_game.php API
 * to start a game session for a user.
 */

require_once __DIR__ . '/launch_game.php';

// Example 1: Launch game for user Jhon
echo "=== Example 1: Launch Game for User Jhon ===\n";

$userId = 101;        // Jhon's user ID
$balance = 500;       // Jhon has ₹500 in wallet
$gameUid = 784512;    // Unique game session ID

$result = launchGame($userId, $balance, $gameUid);

if ($result['success']) {
    echo "✅ Game launched successfully!\n";
    echo "🎮 Game URL: " . $result['game_url'] . "\n";
    echo "🆔 Game UID: " . $result['game_uid'] . "\n\n";
    
    // In a real web application, you would redirect the user:
    // header('Location: ' . $result['game_url']);
    // exit;
} else {
    echo "❌ Error: " . $result['message'] . "\n\n";
}

// Example 2: Launch game with optional parameters
echo "=== Example 2: Launch with Currency and Language ===\n";

$userId2 = 102;
$balance2 = 1000;
$currencyCode = 'BDT';  // Bangladesh Taka
$language = 'bn';        // Bengali

$result2 = launchGame($userId2, $balance2, null, $currencyCode, $language);

if ($result2['success']) {
    echo "✅ Game launched successfully!\n";
    echo "🎮 Game URL: " . $result2['game_url'] . "\n";
    echo "💰 Currency: {$currencyCode}\n";
    echo "🌐 Language: {$language}\n\n";
} else {
    echo "❌ Error: " . $result2['message'] . "\n\n";
}

// Example 3: Auto-generated game UID
echo "=== Example 3: Auto-generated Game UID ===\n";

$userId3 = 103;
$balance3 = 750;

$result3 = launchGame($userId3, $balance3); // game_uid will be auto-generated

if ($result3['success']) {
    echo "✅ Game launched successfully!\n";
    echo "🎮 Game URL: " . $result3['game_url'] . "\n";
    echo "🆔 Auto-generated Game UID: " . $result3['game_uid'] . "\n";
} else {
    echo "❌ Error: " . $result3['message'] . "\n";
}

?>
