<?php
/**
 * Check all providers and their game UID patterns
 */
require_once 'config.php';
require_once 'db_helper.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

echo "=== GAME UID PATTERNS BY PROVIDER ===\n\n";

$stmt = $pdo->query("
    SELECT 
        provider,
        COUNT(*) as total,
        MIN(game_uid) as min_uid,
        MAX(game_uid) as max_uid,
        GROUP_CONCAT(DISTINCT game_uid ORDER BY game_uid LIMIT 5) as sample_uids
    FROM games
    WHERE is_active = 1
    GROUP BY provider
    ORDER BY provider
");

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    echo "Provider: {$row['provider']}\n";
    echo "  Total Games: {$row['total']}\n";
    echo "  UID Range: {$row['min_uid']} - {$row['max_uid']}\n";
    echo "  Sample UIDs: {$row['sample_uids']}\n\n";
}

echo "\n=== TESTING GAME LAUNCH FOR EACH PROVIDER ===\n\n";

require_once 'api_request_builder.php';

$providers = $pdo->query("SELECT DISTINCT provider FROM games WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);

foreach ($providers as $provider) {
    $stmt = $pdo->prepare("SELECT game_uid, name FROM games WHERE provider = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$provider]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($game) {
        echo "Testing {$provider}: {$game['name']} (UID: {$game['game_uid']})\n";
        
        $params = createGameLaunchRequest(
            userId: '1',
            balance: '1000',
            gameUid: $game['game_uid'],
            currencyCode: 'PHP',
            language: 'en'
        );
        
        $result = sendLaunchGameRequest($params);
        
        if ($result['success']) {
            echo "  ✅ Launch successful\n";
            echo "  URL: {$result['game_url']}\n";
            
            // Test if game URL actually works
            $ch = curl_init($result['game_url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if (stripos($content, 'GAME NOT FOUND') !== false) {
                echo "  ❌ Game URL returns: GAME NOT FOUND\n";
            } elseif ($httpCode == 200) {
                echo "  ✅ Game loads successfully\n";
            } else {
                echo "  ⚠️  HTTP {$httpCode}\n";
            }
        } else {
            echo "  ❌ Launch failed: {$result['error']}\n";
        }
        echo "\n";
    }
}

echo "=== TEST COMPLETE ===\n";
?>
