<?php
require_once 'session_config.php';

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

$gameId = $_GET['game_id'] ?? '';
$gameName = $_GET['game_name'] ?? 'Casino Game';

// Validate game ID
if (empty($gameId)) {
    $result = [
        'success' => false,
        'error' => 'Invalid game ID provided',
        'error_code' => 'INVALID_GAME_ID'
    ];
    goto render_page;
}

// Check if game exists and is active
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("SELECT game_uid, name, is_active FROM games WHERE game_uid = ?");
    $stmt->execute([$gameId]);
    $gameData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If game not found or inactive, show error
    if (!$gameData) {
        $result = [
            'success' => false,
            'error' => 'Game not found in our database',
            'error_code' => 'GAME_NOT_FOUND'
        ];
        goto render_page;
    }
    
    if ($gameData['is_active'] != 1) {
        $result = [
            'success' => false,
            'error' => 'This game is currently inactive',
            'error_code' => 'GAME_INACTIVE'
        ];
        goto render_page;
    }
    
    // Update game name from database
    $gameName = $gameData['name'];
    
} catch (Exception $e) {
    error_log("Failed to check game: " . $e->getMessage());
}

// Record game play to database
try {
    $stmt = $pdo->prepare("INSERT INTO game_plays (user_id, game_uid, game_name) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $gameId, $gameName]);
} catch (Exception $e) {
    // Continue even if tracking fails
    error_log("Failed to record game play: " . $e->getMessage());
}

// Launch game - Use database user ID as string for SoftAPI
$userId = (string)$_SESSION['user_id'];

// Log current balance before game launch
error_log("Launching game for user {$userId} with balance: {$balance}");

$params = createGameLaunchRequest(
    userId: $userId,
    balance: $balance,
    gameUid: $gameId
);

$result = sendLaunchGameRequest($params);

render_page:
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($gameName); ?> - Casino</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            position: fixed;
            -webkit-overflow-scrolling: touch;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #fff;
        }
        
        .floating-home {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: move;
            box-shadow: 0 6px 25px rgba(59, 130, 246, 0.6);
            z-index: 10000;
            transition: all 0.3s ease;
            border: 3px solid rgba(255, 255, 255, 0.3);
            color: white;
            font-size: 26px;
            touch-action: none;
            user-select: none;
        }
        
        .floating-home:hover {
            box-shadow: 0 8px 35px rgba(59, 130, 246, 0.8);
            transform: translateX(-50%) scale(1.1);
        }
        
        .floating-home:active {
            transform: translateX(-50%) scale(0.95);
        }
        
        .floating-home.dragging {
            transition: none;
            opacity: 0.8;
            transform: none;
        }
        
        .home-tooltip {
            position: fixed;
            top: 90px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.85);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            z-index: 10001;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }
        
        .floating-home:hover + .home-tooltip {
            opacity: 1;
        }
        
        .game-container {
            width: 100%;
            height: 100vh;
            height: 100dvh;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }
        
        .game-frame {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }
        
        .error-container {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            height: 100dvh;
            flex-direction: column;
            gap: 20px;
            padding: 20px;
        }
        
        .error-message {
            background: #dc2626;
            padding: 20px;
            border-radius: 15px;
            max-width: 500px;
            text-align: center;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 20px;
            z-index: 9999;
            transition: opacity 0.3s ease;
        }
        
        .loading-overlay.hidden {
            opacity: 0;
            pointer-events: none;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(59, 130, 246, 0.3);
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading-text {
            color: #94a3b8;
            font-size: 16px;
        }
        
        @media (max-width: 768px) {
            .floating-home {
                width: 55px;
                height: 55px;
                font-size: 24px;
                top: 15px;
            }
            
            .home-tooltip {
                top: 80px;
                font-size: 12px;
                padding: 6px 12px;
            }
        }
    </style>
</head>
<body>
    
    <?php if ($result['success']): ?>
        <!-- Loading overlay - shown until game loads -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner"></div>
            <div class="loading-text">Loading game...</div>
        </div>
        
        <div class="game-container">
            <iframe src="<?php echo htmlspecialchars($result['game_url']); ?>" 
                    class="game-frame" 
                    id="gameFrame"
                    allowfullscreen></iframe>
        </div>
        <button class="floating-home" id="floatingBtn" title="Drag to move">🏠</button>
        <div class="home-tooltip">Back to Lobby</div>
    <?php else: ?>
        <div class="error-container" id="errorContainer">
            <div class="error-message">
                <?php if (isset($result['error_code']) && ($result['error_code'] === 'GAME_NOT_FOUND' || $result['error_code'] === 'GAME_INACTIVE')): ?>
                    <h3>🎮 Game Not Available</h3>
                    <p><?php echo htmlspecialchars($result['error']); ?></p>
                    <p style="margin-top: 15px; font-size: 14px; opacity: 0.9;">
                        This game may have been removed or temporarily disabled.
                    </p>
                <?php elseif (strpos($result['error'], 'under maintenance') !== false || (isset($result['error_code']) && $result['error_code'] == 9)): ?>
                    <h3>🔧 Game Under Maintenance</h3>
                    <p>This game is temporarily under maintenance by the provider.</p>
                    <p style="margin-top: 15px; font-size: 14px; opacity: 0.9;">
                        Please try another game or check back later.
                    </p>
                <?php else: ?>
                    <h3>⚠️ Failed to load game</h3>
                    <p><?php echo htmlspecialchars($result['error']); ?></p>
                    <?php if (strpos($result['error'], 'timeout') !== false || strpos($result['error'], 'deadline exceeded') !== false): ?>
                        <p style="margin-top: 15px; font-size: 14px; opacity: 0.9;">
                            <strong>Tip:</strong> The game server is responding slowly. This error may disappear if the game loads successfully.
                        </p>
                        <p style="margin-top: 10px; font-size: 13px; opacity: 0.8;">
                            Waiting for game to load...
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
                <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    <button onclick="location.href='index.php'" style="padding: 12px 24px; background: #3b82f6; border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        🏠 Back to Lobby
                    </button>
                </div>
            </div>
            <button class="floating-home" id="floatingBtn" title="Drag to move">🏠</button>
            <div class="home-tooltip">Back to Lobby</div>
        </div>
    <?php endif; ?>
    
    <script>
        // Hide loading overlay when game iframe loads
        const gameFrame = document.getElementById('gameFrame');
        const loadingOverlay = document.getElementById('loadingOverlay');
        
        if (gameFrame && loadingOverlay) {
            gameFrame.addEventListener('load', function() {
                loadingOverlay.classList.add('hidden');
                setTimeout(() => {
                    loadingOverlay.style.display = 'none';
                }, 300);
            });
            
            // Fallback: hide loading after 10 seconds even if load event doesn't fire
            setTimeout(() => {
                if (loadingOverlay && !loadingOverlay.classList.contains('hidden')) {
                    loadingOverlay.classList.add('hidden');
                    setTimeout(() => {
                        loadingOverlay.style.display = 'none';
                    }, 300);
                }
            }, 10000);
        }
        
        // Keep session alive during gameplay
        // Ping server every 2 minutes to prevent session timeout and detect balance changes
        setInterval(function() {
            fetch('keep_alive.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Session kept alive:', data);
                    // Log balance if provided (useful for debugging admin updates)
                    if (data.balance !== undefined) {
                        console.log('Current balance:', data.balance);
                    }
                })
                .catch(error => {
                    console.error('Keep-alive failed:', error);
                });
        }, 120000); // 2 minutes = 120,000 milliseconds
        
        const floatingBtn = document.getElementById('floatingBtn');
        if (floatingBtn) {
            let isDragging = false;
            let startX, startY, startLeft, startTop;
            let hasMoved = false;

            function handleStart(e) {
                isDragging = true;
                hasMoved = false;
                floatingBtn.classList.add('dragging');
                
                const touch = e.type === 'touchstart' ? e.touches[0] : e;
                startX = touch.clientX;
                startY = touch.clientY;
                
                const rect = floatingBtn.getBoundingClientRect();
                startLeft = rect.left;
                startTop = rect.top;
                
                e.preventDefault();
            }

            function handleMove(e) {
                if (!isDragging) return;
                
                const touch = e.type === 'touchmove' ? e.touches[0] : e;
                const deltaX = touch.clientX - startX;
                const deltaY = touch.clientY - startY;
                
                if (Math.abs(deltaX) > 5 || Math.abs(deltaY) > 5) {
                    hasMoved = true;
                }
                
                let newLeft = startLeft + deltaX;
                let newTop = startTop + deltaY;
                
                // Keep within viewport bounds
                const btnWidth = floatingBtn.offsetWidth;
                const btnHeight = floatingBtn.offsetHeight;
                newLeft = Math.max(10, Math.min(window.innerWidth - btnWidth - 10, newLeft));
                newTop = Math.max(10, Math.min(window.innerHeight - btnHeight - 10, newTop));
                
                floatingBtn.style.left = newLeft + 'px';
                floatingBtn.style.top = newTop + 'px';
                floatingBtn.style.transform = 'none';
                
                e.preventDefault();
            }

            function handleEnd(e) {
                if (!isDragging) return;
                isDragging = false;
                floatingBtn.classList.remove('dragging');
                
                // Only navigate if button wasn't moved
                if (!hasMoved) {
                    location.href = 'index.php';
                }
                
                e.preventDefault();
            }

            // Mouse events
            floatingBtn.addEventListener('mousedown', handleStart);
            document.addEventListener('mousemove', handleMove);
            document.addEventListener('mouseup', handleEnd);

            // Touch events
            floatingBtn.addEventListener('touchstart', handleStart, { passive: false });
            document.addEventListener('touchmove', handleMove, { passive: false });
            document.addEventListener('touchend', handleEnd, { passive: false });
        }
    </script>
</body>
</html>
