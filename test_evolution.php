<?php
/**
 * Evolution Games API Test - Test Multiple Games
 * Disables Evolution games if "GAME NOT FOUND" error is detected
 */

require_once "/var/www/html/config.php";
require_once "/var/www/html/api_request_builder.php";
require_once "/var/www/html/db_helper.php";

echo "=== EVOLUTION GAMES COMPREHENSIVE API TEST ===\n";
echo "Time: " . date("Y-m-d H:i:s") . "\n\n";

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Get multiple Evolution games to test
    $stmt = $pdo->query("SELECT id, game_uid, name FROM games WHERE provider='Evolution' LIMIT 5");
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($games)) {
        echo "❌ No Evolution games found in database\n";
        exit(1);
    }
    
    echo "Testing " . count($games) . " Evolution games...\n\n";
    
    $successCount = 0;
    $failureCount = 0;
    $gameNotFoundCount = 0;
    $lastError = "";
    
    foreach ($games as $game) {
        echo "Testing: {$game['name']} (UID: {$game['game_uid']})...\n";
        
        $params = createGameLaunchRequest(
            userId: "999",
            balance: "1000",
            gameUid: $game['game_uid']
        );
        
        $result = sendLaunchGameRequest($params);
        
        if ($result['success']) {
            echo "  ✅ SUCCESS\n";
            $successCount++;
        } else {
            $error = $result['error'] ?? "";
            echo "  ❌ FAILED: {$error}\n";
            $failureCount++;
            $lastError = $error;
            
            if (stripos($error, "game not found") !== false) {
                $gameNotFoundCount++;
            }
        }
    }
    
    echo "\n=== RESULTS ===\n";
    echo "Success: {$successCount}\n";
    echo "Failed: {$failureCount}\n";
    echo "Game Not Found Errors: {$gameNotFoundCount}\n\n";
    
    // If any "GAME NOT FOUND" errors detected, disable all Evolution games
    if ($gameNotFoundCount > 0) {
        echo "⚠️  GAME NOT FOUND ERRORS DETECTED!\n";
        echo "🔴 DISABLING ALL EVOLUTION GAMES...\n\n";
        
        $updateStmt = $pdo->prepare("UPDATE games SET is_active=0 WHERE provider='Evolution'");
        $updateStmt->execute();
        $count = $updateStmt->rowCount();
        
        echo "✓ {$count} Evolution games have been DISABLED\n";
        echo "Status: EVOLUTION PROVIDER NOT SUPPORTED BY API\n";
        
        // Log this action
        @mkdir("/var/www/html/logs", 0755, true);
        $logMsg = date("Y-m-d H:i:s") . " | EVOLUTION DISABLED | Game Not Found: {$gameNotFoundCount}/{$failureCount} games | Disabled {$count} total games\n";
        file_put_contents("/var/www/html/logs/evolution_status.log", $logMsg, FILE_APPEND);
        
        exit(1);
    } else if ($successCount > 0) {
        echo "✅ Evolution games are working!\n";
        exit(0);
    } else {
        echo "⚠️  All tests failed but no GAME NOT FOUND errors\n";
        echo "Error: {$lastError}\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(2);
}
?>
