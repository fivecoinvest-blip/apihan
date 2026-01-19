<?php
/**
 * Test Evolution Game Launch - Check actual error
 */
require_once 'config.php';
require_once 'db_helper.php';
require_once 'api_request_builder.php';

echo "=== EVOLUTION GAME LAUNCH TEST ===\n\n";

// Test parameters
$testUserId = '1';
$testBalance = '1000.00';
$testCurrency = 'PHP';
$testLanguage = 'en';

// Get an Evolution game
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("SELECT game_uid, name FROM games WHERE provider = 'Evolution' AND is_active = 1 LIMIT 1");
    $stmt->execute();
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        die("❌ No active Evolution games found in database\n");
    }
    
    echo "🎮 Testing Game: {$game['name']} (UID: {$game['game_uid']})\n";
    echo "💰 Test Balance: PHP {$testBalance}\n";
    echo "🌏 Currency: {$testCurrency}\n";
    echo "🗣️ Language: {$testLanguage}\n\n";
    
    // Create launch request
    $params = createGameLaunchRequest(
        userId: $testUserId,
        balance: $testBalance,
        gameUid: $game['game_uid'],
        currencyCode: $testCurrency,
        language: $testLanguage
    );
    
    echo "📤 Sending launch request...\n";
    echo "API URL: " . SERVER_URL . "\n";
    echo "Token: " . API_TOKEN . "\n\n";
    
    // Send request
    $result = sendLaunchGameRequest($params);
    
    echo "📥 API Response:\n";
    echo "================\n";
    
    if ($result['success']) {
        echo "✅ SUCCESS!\n";
        echo "Game URL: " . $result['game_url'] . "\n";
    } else {
        echo "❌ FAILED!\n";
        echo "Error: " . $result['error'] . "\n";
        if (isset($result['error_code'])) {
            echo "Error Code: " . $result['error_code'] . "\n";
        }
        if (isset($result['http_code'])) {
            echo "HTTP Code: " . $result['http_code'] . "\n";
        }
    }
    
    echo "\n📋 Full Response Data:\n";
    print_r($result);
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>
