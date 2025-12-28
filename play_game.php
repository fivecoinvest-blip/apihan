<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once 'db_helper.php';
require_once 'api_request_builder.php';
require_once 'balance_helper.php';
require_once 'currency_helper.php';

$userModel = new User();
$currentUser = $userModel->getById($_SESSION['user_id']);
$balance = $userModel->getBalance($_SESSION['user_id']);
$userCurrency = $currentUser['currency'] ?? 'PHP';

$gameId = $_GET['game_id'] ?? '634';
$gameName = $_GET['game_name'] ?? 'Casino Game';

// Record game play to database
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare("INSERT INTO game_plays (user_id, game_uid, game_name) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $gameId, $gameName]);
} catch (Exception $e) {
    // Continue even if tracking fails
    error_log("Failed to record game play: " . $e->getMessage());
}

// Launch game - Use database user ID as string for SoftAPI
$userId = (string)$_SESSION['user_id'];
setUserBalance($userId, $balance);

$params = createGameLaunchRequest(
    userId: $userId,
    balance: $balance,
    gameUid: $gameId
);

$result = sendLaunchGameRequest($params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($gameName); ?> - Casino</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #fff;
            overflow: hidden;
        }
        
        .game-header {
            background: #1e293b;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        .back-btn {
            background: #334155;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .game-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .balance {
            background: linear-gradient(135deg, #10b981, #059669);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .game-container {
            width: 100%;
            height: calc(100vh - 60px);
        }
        
        .game-frame {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .error-container {
            display: flex;
            align-items: center;
            justify-content: center;
            height: calc(100vh - 60px);
            flex-direction: column;
            gap: 20px;
        }
        
        .error-message {
            background: #dc2626;
            padding: 20px;
            border-radius: 15px;
            max-width: 500px;
        }
    </style>
</head>
<body>
    <div class="game-header">
        <button class="back-btn" onclick="location.href='casino.php'">← Back</button>
        <div class="game-title"><?php echo htmlspecialchars($gameName); ?></div>
        <div class="balance">💰 <?php echo formatCurrency($balance, $userCurrency); ?></div>
    </div>
    
    <?php if ($result['success']): ?>
        <div class="game-container">
            <iframe src="<?php echo htmlspecialchars($result['game_url']); ?>" 
                    class="game-frame" 
                    allowfullscreen></iframe>
        </div>
    <?php else: ?>
        <div class="error-container">
            <div class="error-message">
                <h3>Failed to load game</h3>
                <p><?php echo htmlspecialchars($result['error']); ?></p>
            </div>
            <button class="back-btn" onclick="location.href='casino.php'">Return to Lobby</button>
        </div>
    <?php endif; ?>
</body>
</html>
