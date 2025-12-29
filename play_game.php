<?php
require_once 'session_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once 'db_helper.php';
require_once 'api_request_builder.php';
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

// Log current balance before game launch
error_log("Launching game for user {$userId} with balance: {$balance}");

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
            right: 20px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: move;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
            z-index: 10000;
            transition: box-shadow 0.3s ease;
            border: 3px solid rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 22px;
            touch-action: none;
            user-select: none;
        }
        
        .floating-home:hover {
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.6);
        }
        
        .floating-home.dragging {
            transition: none;
            opacity: 0.8;
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
                width: 45px;
                height: 45px;
                font-size: 20px;
                top: 15px;
                right: 15px;
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
    <?php else: ?>
        <div class="error-container" id="errorContainer">
            <div class="error-message">
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
                <button onclick="location.href='index.php'" style="margin-top: 20px; padding: 12px 24px; background: #3b82f6; border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer;">
                    ← Back to Lobby
                </button>
            </div>
            <button class="floating-home" id="floatingBtn" title="Drag to move">🏠</button>
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
        
        // Keep session alive and update balance during gameplay
        // Ping server every 10 seconds to prevent session timeout and get latest balance
        setInterval(function() {
            fetch('keep_alive.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Session kept alive:', data);
                })
                .catch(error => {
                    console.error('Keep-alive failed:', error);
                });
            
            // Also fetch updated balance every 10 seconds
            fetch('get_balance.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Balance updated:', data.formatted);
                        // Update balance in parent window if available
                        if (window.opener && !window.opener.closed) {
                            window.opener.postMessage({
                                type: 'balanceUpdate',
                                balance: data.balance,
                                formatted: data.formatted
                            }, '*');
                        }
                    }
                })
                .catch(error => {
                    console.error('Balance fetch failed:', error);
                });
        }, 10000); // 10 seconds = 10,000 milliseconds
        
        const floatingBtn = document.getElementById('floatingBtn');
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
            floatingBtn.style.right = 'auto';
            floatingBtn.style.bottom = 'auto';
            
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
    </script>
</body>
</html>
