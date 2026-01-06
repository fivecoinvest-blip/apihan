<?php
/**
 * Check which games are under maintenance and optionally disable them
 */

require_once 'config.php';
require_once 'db_helper.php';
require_once 'api_request_builder.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// Get all active games
$stmt = $pdo->query("SELECT id, game_uid, name, provider, is_active FROM games ORDER BY name");
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Checking " . count($games) . " games for maintenance status...\n\n";
echo str_repeat("=", 80) . "\n";

$maintenanceGames = [];
$workingGames = [];
$errorGames = [];

foreach ($games as $game) {
    echo "Testing: {$game['name']} (ID: {$game['game_uid']})... ";
    
    // Test game launch
    $params = createGameLaunchRequest('1', '100', $game['game_uid']);
    $result = sendLaunchGameRequest($params);
    
    if ($result['success']) {
        echo "✅ Working\n";
        $workingGames[] = $game;
    } else {
        if (strpos($result['error'], 'under maintenance') !== false || (isset($result['error_code']) && $result['error_code'] == 9)) {
            echo "🔧 MAINTENANCE\n";
            $maintenanceGames[] = [
                'game' => $game,
                'error' => $result['error']
            ];
        } else {
            echo "❌ Error: {$result['error']}\n";
            $errorGames[] = [
                'game' => $game,
                'error' => $result['error'],
                'error_code' => $result['error_code'] ?? 'N/A'
            ];
        }
    }
    
    // Small delay to avoid overwhelming the API
    usleep(100000); // 0.1 second
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 80) . "\n";
echo "✅ Working games: " . count($workingGames) . "\n";
echo "🔧 Under maintenance: " . count($maintenanceGames) . "\n";
echo "❌ Other errors: " . count($errorGames) . "\n";

if (!empty($maintenanceGames)) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "GAMES UNDER MAINTENANCE:\n";
    echo str_repeat("=", 80) . "\n";
    foreach ($maintenanceGames as $item) {
        $g = $item['game'];
        $status = $g['is_active'] == 1 ? "ACTIVE" : "inactive";
        echo "• {$g['name']} (ID: {$g['game_uid']}) - Currently: {$status}\n";
    }
    
    echo "\n\nWould you like to disable these games? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    
    if (strtolower($line) === 'yes' || strtolower($line) === 'y') {
        foreach ($maintenanceGames as $item) {
            $g = $item['game'];
            $stmt = $pdo->prepare("UPDATE games SET is_active = 0 WHERE id = ?");
            $stmt->execute([$g['id']]);
            echo "✓ Disabled: {$g['name']}\n";
        }
        
        // Clear cache
        require_once 'redis_helper.php';
        $cache = CacheManager::getInstance();
        $cache->deletePattern('games:*');
        $cache->deletePattern('admin:games:*');
        echo "\n✓ Cache cleared\n";
        
        echo "\n✅ All maintenance games have been disabled!\n";
    } else {
        echo "\nNo changes made.\n";
    }
}

if (!empty($errorGames)) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "GAMES WITH OTHER ERRORS:\n";
    echo str_repeat("=", 80) . "\n";
    foreach ($errorGames as $item) {
        $g = $item['game'];
        echo "• {$g['name']} (ID: {$g['game_uid']})\n";
        echo "  Error: {$item['error']} (Code: {$item['error_code']})\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "Check complete!\n";
