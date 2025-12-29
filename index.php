<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'db_helper.php';
require_once 'currency_helper.php';

$loggedIn = isset($_SESSION['user_id']);
$balance = 0;
$userCurrency = 'PHP';
$username = '';

if ($loggedIn) {
    $userModel = new User();
    $currentUser = $userModel->getById($_SESSION['user_id']);
    $balance = $userModel->getBalance($_SESSION['user_id']);
    $userCurrency = $currentUser['currency'] ?? 'PHP';
    $username = $currentUser['username'] ?? '';
    $_SESSION['currency'] = $userCurrency;
}

// Get games from database
$db = Database::getInstance();
$pdo = $db->getConnection();

// Handle AJAX request for loading more games
if (isset($_GET['load_games'])) {
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $category = isset($_GET['category']) ? $_GET['category'] : 'all';
    $limit = 20;
    
    if ($category === 'all') {
        $stmt = $pdo->prepare("SELECT * FROM games WHERE is_active = 1 ORDER BY sort_order ASC, name ASC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM games WHERE is_active = 1 AND category = ? ORDER BY sort_order ASC, name ASC LIMIT ? OFFSET ?");
        $stmt->execute([$category, $limit, $offset]);
        $stmt->bindValue(1, $category, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = array_map(function($g) {
        return [
            'id' => $g['game_uid'],
            'name' => $g['name'],
            'image' => $g['image'] && file_exists($g['image']) ? $g['image'] : null,
            'category' => $g['category'],
            'provider' => $g['provider']
        ];
    }, $games);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Initial load - first 20 games
$stmt = $pdo->prepare("SELECT * FROM games WHERE is_active = 1 ORDER BY sort_order ASC, name ASC LIMIT 20");
$stmt->execute();
$gamesFromDb = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique categories for navigation
$categoriesStmt = $pdo->query("SELECT DISTINCT category FROM games WHERE is_active = 1 ORDER BY category");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);

// Get total games count
$totalGamesCount = $pdo->query("SELECT COUNT(*) FROM games WHERE is_active = 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e293b">
    <title>Casino - Game Lobby</title>
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="icon-192.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #fff;
        }
        
        /* Header */
        .header {
            background: #1e293b;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #fff;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .balance {
            background: linear-gradient(135deg, #10b981, #059669);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .auth-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
        }
        
        .btn-secondary {
            background: transparent;
            color: #9ca3af;
            border: 1px solid #374151;
        }
        
        .btn-secondary:hover {
            background: #374151;
            color: #fff;
        }
        
        /* Navigation */
        .nav {
            background: #1e293b;
            padding: 15px 20px;
            overflow-x: auto;
            white-space: nowrap;
        }
        
        .nav-tabs {
            display: inline-flex;
            gap: 10px;
        }
        
        .nav-tab {
            padding: 10px 20px;
            background: transparent;
            border: 2px solid #334155;
            border-radius: 10px;
            color: #94a3b8;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .nav-tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: transparent;
            color: white;
        }
        
        /* Main Content */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .section-title {
            font-size: 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Games Grid */
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .game-card {
            position: relative;
            background: #1e293b;
            border: 1px solid #2d3548;
            border-radius: 15px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .game-card:hover {
            transform: translateY(-5px);
            border-color: #2563eb;
        }
        
        .game-card:hover .play-overlay {
            opacity: 1;
        }
        
        .game-image {
            position: relative;
            width: 100%;
            height: 240px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #fff;
        }
        
        .game-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .play-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .play-btn {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 16px 32px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
            transition: all 0.2s;
        }
        
        .play-btn:hover {
            background: #1d4ed8;
            transform: scale(1.05);
        }
        
        .play-btn::before {
            content: '▶';
            font-size: 18px;
        }
        
        .game-info {
            padding: 16px;
            text-align: center;
        }
        
        .game-name {
            font-weight: 600;
            font-size: 15px;
            color: #fff;
        }
        
        /* Loading Indicator */
        .loading-indicator {
            display: none;
            text-align: center;
            padding: 40px;
            color: #9ca3af;
            font-size: 16px;
        }
        
        .loading-indicator.active {
            display: block;
        }
        
        .spinner {
            border: 3px solid #2d3548;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 12px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Mobile Menu */
        .mobile-menu {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #1e293b;
            padding: 10px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.3);
        }
        
        .mobile-menu-items {
            display: flex;
            justify-content: space-around;
        }
        
        .mobile-menu-item {
            text-align: center;
            color: #94a3b8;
            font-size: 24px;
            padding: 10px;
            cursor: pointer;
        }
        
        .mobile-menu-item.active {
            color: #667eea;
        }
        
        @media (max-width: 768px) {
            .games-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }
            
            .game-image {
                height: 140px;
                font-size: 48px;
            }
            
            .mobile-menu {
                display: block;
            }
            
            .container {
                padding-bottom: 80px;
            }
            
            .user-menu .username {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo">🎰 Casino</div>
        <div class="user-info">
            <?php if ($loggedIn): ?>
                <div class="balance">💰 <?php echo formatCurrency($balance, $userCurrency); ?></div>
                <div class="auth-buttons">
                    <a href="profile.php" class="btn btn-primary">Profile</a>
                    <a href="logout.php" class="btn btn-secondary">Logout</a>
                </div>
            <?php else: ?>
                <div class="auth-buttons">
                    <a href="login.php" class="btn btn-primary">Login</a>
                    <a href="register.php" class="btn btn-secondary">Sign Up</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Navigation -->
    <div class="nav">
        <div class="nav-tabs">
            <button class="nav-tab active" onclick="showCategory('all')">🎮 All Games</button>
            <?php 
            $categoryIcons = [
                'Slots' => '🎰',
                'Table' => '🃏',
                'Fishing' => '🎣',
                'Arcade' => '🕹️',
                'Live' => '📹'
            ];
            foreach ($categories as $cat): 
                $icon = $categoryIcons[$cat] ?? '🎲';
            ?>
                <button class="nav-tab" onclick="showCategory('<?php echo strtolower($cat); ?>')"><?php echo $icon . ' ' . htmlspecialchars($cat); ?></button>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container">
        <h2 class="section-title">
            <span id="section-emoji">🔥</span> 
            <span id="section-text">Popular Games</span>
            <span style="color: #9ca3af; font-size: 14px; font-weight: normal; margin-left: auto;" id="games-counter">Loading...</span>
        </h2>
        
        <div class="games-grid" id="games-grid">
            <!-- Games will be loaded here -->
        </div>
        
        <!-- Loading Indicator -->
        <div class="loading-indicator" id="loading-indicator">
            <div class="spinner"></div>
            <p>Loading more games...</p>
        </div>
    </div>
    
    <!-- Mobile Bottom Menu -->
    <div class="mobile-menu">
        <div class="mobile-menu-items">
            <div class="mobile-menu-item active">🎮</div>
            <div class="mobile-menu-item" onclick="location.href='wallet.php'">💰</div>
            <div class="mobile-menu-item" onclick="location.href='profile.php'">👤</div>
        </div>
    </div>
    
    <script>
        let gamesOffset = 0;
        let currentCategory = 'all';
        let isLoading = false;
        let hasMoreGames = true;
        let totalGames = <?php echo $totalGamesCount; ?>;
        const loggedIn = <?php echo $loggedIn ? 'true' : 'false'; ?>;
        
        // Initial games from PHP
        const initialGames = <?php echo json_encode(array_map(function($g) {
            return [
                'id' => $g['game_uid'],
                'name' => $g['name'],
                'image' => $g['image'] && file_exists($g['image']) ? $g['image'] : null,
                'category' => $g['category'],
                'provider' => $g['provider']
            ];
        }, $gamesFromDb)); ?>;
        
        // Render initial games
        renderGames(initialGames);
        gamesOffset = initialGames.length;
        updateCounter();
        
        // Infinite scroll
        window.addEventListener('scroll', function() {
            if (!isLoading && hasMoreGames) {
                const scrollPosition = window.innerHeight + window.scrollY;
                const pageHeight = document.documentElement.scrollHeight;
                
                if (scrollPosition >= pageHeight - 300) {
                    loadMoreGames();
                }
            }
        });
        
        function loadMoreGames() {
            if (isLoading || !hasMoreGames) return;
            
            isLoading = true;
            document.getElementById('loading-indicator').classList.add('active');
            
            fetch(`?load_games=1&offset=${gamesOffset}&category=${currentCategory}`)
                .then(response => response.json())
                .then(games => {
                    if (games.length > 0) {
                        appendGames(games);
                        gamesOffset += games.length;
                        updateCounter();
                    }
                    
                    if (games.length < 20) {
                        hasMoreGames = false;
                    }
                    
                    isLoading = false;
                    document.getElementById('loading-indicator').classList.remove('active');
                })
                .catch(error => {
                    console.error('Error loading games:', error);
                    isLoading = false;
                    document.getElementById('loading-indicator').classList.remove('active');
                });
        }
        
        function renderGames(games) {
            const grid = document.getElementById('games-grid');
            grid.innerHTML = games.map(createGameCard).join('');
        }
        
        function appendGames(games) {
            const grid = document.getElementById('games-grid');
            grid.insertAdjacentHTML('beforeend', games.map(createGameCard).join(''));
        }
        
        function createGameCard(game) {
            const imageHtml = game.image 
                ? `<img src="${game.image}" alt="${escapeHtml(game.name)}">` 
                : '<div style="font-size: 60px; display: flex; align-items: center; justify-content: center; height: 100%;">🎰</div>';
            
            return `
                <div class="game-card" onclick="playGame('${game.id}', '${escapeHtml(game.name)}')">
                    <div class="game-image">${imageHtml}</div>
                    <div class="game-info">
                        <div class="game-name">${escapeHtml(game.name)}</div>
                    </div>
                    <div class="play-overlay">
                        <button class="play-btn">Play</button>
                    </div>
                </div>
            `;
        }
        
        function showCategory(category) {
            document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            currentCategory = category;
            gamesOffset = 0;
            hasMoreGames = true;
            
            // Update section title
            const categoryTitles = {
                'all': { emoji: '🔥', text: 'Popular Games' },
                'slots': { emoji: '🎰', text: 'Slots Games' },
                'table': { emoji: '🃏', text: 'Table Games' },
                'fishing': { emoji: '🎣', text: 'Fishing Games' },
                'arcade': { emoji: '🕹️', text: 'Arcade Games' },
                'live': { emoji: '📹', text: 'Live Casino' }
            };
            
            const title = categoryTitles[category] || categoryTitles['all'];
            document.getElementById('section-emoji').textContent = title.emoji;
            document.getElementById('section-text').textContent = title.text;
            
            // Clear and load new games
            document.getElementById('games-grid').innerHTML = '';
            document.getElementById('games-counter').textContent = 'Loading...';
            
            isLoading = true;
            fetch(`?load_games=1&offset=0&category=${category}`)
                .then(response => response.json())
                .then(games => {
                    renderGames(games);
                    gamesOffset = games.length;
                    updateCounter();
                    
                    if (games.length < 20) {
                        hasMoreGames = false;
                    }
                    
                    isLoading = false;
                })
                .catch(error => {
                    console.error('Error loading games:', error);
                    isLoading = false;
                });
        }
        
        function updateCounter() {
            document.getElementById('games-counter').textContent = 
                `Showing ${gamesOffset} games`;
        }
        
        function playGame(gameId, gameName) {
            if (loggedIn) {
                window.location.href = `play_game.php?game_id=${gameId}&game_name=${encodeURIComponent(gameName)}`;
            } else {
                window.location.href = 'login.php';
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Install PWA prompt
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            console.log('PWA installable');
        });
    </script>
</body>
</html>
