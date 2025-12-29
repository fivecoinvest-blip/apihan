<?php
/**
 * Admin Panel - Game Management
 */
require_once 'session_config.php';
require_once 'config.php';
require_once 'db_helper.php';
require_once 'currency_helper.php';
require_once 'settings_helper.php';

// Handle success/error messages from session
$success = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);

// Also check for GET parameters (for backward compatibility)
if (isset($_GET['success'])) {
    $success = match($_GET['success']) {
        'game_added' => 'Game added successfully!',
        'game_updated' => 'Game updated successfully!',
        'game_deleted' => 'Game deleted successfully!',
        'image_uploaded' => 'Image uploaded successfully!',
        'user_updated' => 'User information updated successfully!',
        'balance_updated' => 'User balance updated successfully!',
        'settings_updated' => 'Settings updated successfully!',
        default => 'Operation completed successfully!'
    };
}
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

// Get database connection
$db = Database::getInstance();
$pdo = $db->getConnection();

// Handle login
if (isset($_POST['admin_login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        
        // Update last login
        $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
        
        // Redirect to prevent resubmission
        header("Location: admin.php");
        exit;
    } else {
        $_SESSION['error'] = "Invalid username or password";
        header("Location: admin.php");
        exit;
    }
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Admin Login</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .login-box { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); width: 90%; max-width: 400px; }
            h1 { text-align: center; color: #667eea; margin-bottom: 30px; }
            input { width: 100%; padding: 15px; border: 2px solid #ddd; border-radius: 10px; font-size: 16px; margin-bottom: 20px; }
            button { width: 100%; padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 10px; font-size: 18px; font-weight: bold; cursor: pointer; }
            .error { color: red; text-align: center; margin-bottom: 15px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>🔐 Admin Login</h1>
            <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" required autofocus>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="admin_login">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get database connection
$db = Database::getInstance();
$pdo = $db->getConnection();

// Handle file upload
if (isset($_POST['upload_image']) && isset($_FILES['game_image'])) {
    $gameId = $_POST['game_id'];
    $file = $_FILES['game_image'];
    
    if ($file['error'] === 0) {
        $uploadDir = 'images/games/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'game_' . $gameId . '_' . time() . '.' . $extension;
        $uploadPath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $stmt = $pdo->prepare("UPDATE games SET image = ? WHERE id = ?");
            $stmt->execute([$uploadPath, $gameId]);
            $_SESSION['success'] = "Image uploaded successfully!";
        } else {
            $_SESSION['error'] = "Failed to upload image.";
        }
    } else {
        $_SESSION['error'] = "Image upload error.";
    }
    header("Location: admin.php");
    exit;
}

// Handle game update
if (isset($_POST['update_game'])) {
    $stmt = $pdo->prepare("UPDATE games SET name = ?, provider = ?, category = ?, is_active = ?, sort_order = ? WHERE id = ?");
    $stmt->execute([
        $_POST['name'],
        $_POST['provider'],
        $_POST['category'],
        isset($_POST['is_active']) ? 1 : 0,
        $_POST['sort_order'],
        $_POST['game_id']
    ]);
    $_SESSION['success'] = "Game updated successfully!";
    header("Location: admin.php");
    exit;
}

// Handle game delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM games WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $_SESSION['success'] = "Game deleted successfully!";
    header("Location: admin.php");
    exit;
}

// Handle add new game
if (isset($_POST['add_game'])) {
    try {
        // Check if game_uid already exists
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM games WHERE game_uid = ?");
        $checkStmt->execute([$_POST['game_uid']]);
        
        if ($checkStmt->fetchColumn() > 0) {
            $_SESSION['error'] = "Game UID already exists! Please use a different Game UID.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO games (game_uid, name, provider, category, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['game_uid'],
                $_POST['name'],
                $_POST['provider'],
                $_POST['category'],
                isset($_POST['is_active']) ? 1 : 0,
                $_POST['sort_order']
            ]);
            $_SESSION['success'] = "Game added successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error adding game: " . $e->getMessage();
    }
    header("Location: admin.php");
    exit;
}

// Handle user balance update
if (isset($_POST['update_user_balance'])) {
    $stmt = $pdo->prepare("UPDATE users SET balance = ?, currency = ? WHERE id = ?");
    $stmt->execute([$_POST['new_balance'], $_POST['currency'], $_POST['user_id']]);
    $_SESSION['success'] = "User balance updated successfully!";
    header("Location: admin.php");
    exit;
}

// Handle user information update
if (isset($_POST['update_user_info'])) {
    $userId = $_POST['user_id'];
    $username = $_POST['username'];
    $phone = $_POST['phone'];
    $balance = $_POST['balance'];
    $currency = $_POST['currency'];
    $status = $_POST['status'];
    
    $stmt = $pdo->prepare("
        UPDATE users SET 
            username = ?,
            phone = ?,
            balance = ?,
            currency = ?,
            status = ?
        WHERE id = ?
    ");
    $stmt->execute([$username, $phone, $balance, $currency, $status, $userId]);
    $_SESSION['success'] = "User information updated successfully!";
    header("Location: admin.php");
    exit;
}

// Handle settings update
if (isset($_POST['update_settings'])) {
    $settingsToUpdate = [
        'casino_name', 'casino_tagline', 'default_currency', 'logo_path',
        'theme_color', 'min_bet', 'max_bet',
        'support_email', 'support_phone', 'facebook_url', 'twitter_url', 'instagram_url'
    ];
    
    foreach ($settingsToUpdate as $key) {
        if (isset($_POST[$key])) {
            SiteSettings::set($key, $_POST[$key]);
        }
    }
    
    // Handle logo upload
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === 0) {
        $uploadDir = 'images/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $extension = pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . time() . '.' . $extension;
        $uploadPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $uploadPath)) {
            SiteSettings::set('logo_path', $uploadPath);
        }
    }
    
    $_SESSION['success'] = "Settings updated successfully!";
    header("Location: admin.php");
    exit;
}

// Load site settings
$siteSettings = SiteSettings::load();

// Get statistics
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalGames = $pdo->query("SELECT COUNT(*) FROM games WHERE is_active = 1")->fetchColumn();
$totalBets = $pdo->query("SELECT COUNT(*) FROM transactions WHERE type = 'bet'")->fetchColumn();
$totalRevenue = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'bet'")->fetchColumn() ?? 0;

// Handle AJAX request for loading more games
if (isset($_GET['load_games'])) {
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $limit = 20;
    
    $stmt = $pdo->prepare("SELECT * FROM games ORDER BY sort_order ASC, name ASC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($games);
    exit;
}

// Initial load - get first 20 games
$gamesPerLoad = 20;
$totalGamesCount = $pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();
$stmt = $pdo->prepare("SELECT * FROM games ORDER BY sort_order ASC, name ASC LIMIT ?");
$stmt->bindValue(1, $gamesPerLoad, PDO::PARAM_INT);
$stmt->execute();
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users with tracking info
$users = $pdo->query("
    SELECT 
        id, username, phone, balance, currency, 
        created_at, last_login, status,
        last_ip, last_device, last_browser, last_os, login_count,
        total_deposits, total_withdrawals, total_bets, total_wins
    FROM users 
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get most active players (top 10 by total bets)
$topPlayers = $pdo->query("
    SELECT 
        u.id,
        u.username,
        u.phone,
        u.balance,
        u.currency,
        COUNT(t.id) as total_games,
        SUM(CASE WHEN t.type = 'bet' THEN t.amount ELSE 0 END) as total_bets,
        SUM(CASE WHEN t.type = 'win' THEN t.amount ELSE 0 END) as total_wins,
        MAX(t.created_at) as last_played
    FROM users u
    LEFT JOIN transactions t ON u.id = t.user_id
    WHERE t.type IN ('bet', 'win')
    GROUP BY u.id
    ORDER BY total_games DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get most bet games
$mostBetGames = $pdo->query("
    SELECT 
        g.id,
        g.game_uid,
        g.name,
        g.provider,
        g.category,
        g.image,
        COUNT(t.id) as bet_count,
        COALESCE(SUM(t.amount), 0) as total_bet_amount,
        COUNT(DISTINCT t.user_id) as unique_players,
        MAX(t.created_at) as last_played
    FROM games g
    LEFT JOIN transactions t ON g.game_uid COLLATE utf8mb4_unicode_ci = t.game_uid COLLATE utf8mb4_unicode_ci AND t.type = 'bet'
    GROUP BY g.id, g.game_uid, g.name, g.provider, g.category, g.image
    ORDER BY total_bet_amount DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent transactions (betting history)
$transactions = $pdo->query("
    SELECT t.*, u.username, u.phone, u.currency 
    FROM transactions t 
    LEFT JOIN users u ON t.user_id = u.id 
    ORDER BY t.created_at DESC 
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Game Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
            background: #0b1120; 
            color: #e5e7eb;
        }
        .header { 
            background: #1a1f36; 
            border-bottom: 1px solid #2d3548;
            padding: 12px 16px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        .header h1 { 
            font-size: 20px; 
            font-weight: 600;
            color: #ffffff;
        }
        .header a { 
            color: #9ca3af; 
            text-decoration: none; 
            padding: 8px 16px; 
            border: 1px solid #2d3548;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .header a:hover { background: #2d3548; color: #fff; }
        .container { max-width: 1400px; margin: 24px auto; padding: 0 24px; }
        .success { 
            background: #064e3b; 
            border: 1px solid #10b981;
            color: #6ee7b7; 
            padding: 12px 16px; 
            border-radius: 8px; 
            margin-bottom: 20px;
            font-size: 14px;
        }
        .stats { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
            gap: 16px; 
            margin-bottom: 24px; 
        }
        .stat-card { 
            background: #1a1f36; 
            padding: 20px; 
            border-radius: 8px; 
            border: 1px solid #2d3548;
        }
        .stat-card h3 { 
            color: #9ca3af; 
            font-size: 13px; 
            font-weight: 500;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-card .number { 
            font-size: 28px; 
            font-weight: 600; 
            color: #ffffff; 
        }
        .tabs { 
            display: flex; 
            gap: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 4px;
            margin-bottom: 24px;
            overflow-x: auto;
        }
        .tab { 
            padding: 10px 20px; 
            background: transparent; 
            border: none; 
            color: #6b7280; 
            cursor: pointer; 
            font-size: 14px; 
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .tab:hover { background: #2d3548; }
        .tab.active { 
            background: #3b82f6; 
            color: white;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .table-container { 
            background: #1a1f36; 
            border-radius: 8px; 
            border: 1px solid #2d3548;
            overflow: hidden;
        }
        .table-container h2 {
            padding: 20px 24px;
            border-bottom: 1px solid #2d3548;
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: #ffffff;
        }
        table { 
            width: 100%; 
            border-collapse: collapse;
        }
        th { 
            background: #0f1626; 
            padding: 12px 24px; 
            text-align: left; 
            font-weight: 500; 
            color: #9ca3af;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #2d3548;
        }
        td { 
            padding: 16px 24px; 
            border-bottom: 1px solid #2d3548;
            font-size: 14px;
            color: #e5e7eb;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: #2d3548; }
        .badge { 
            padding: 4px 10px; 
            border-radius: 6px; 
            font-size: 12px; 
            font-weight: 500;
            display: inline-block;
        }
        .badge-success { background: #064e3b; color: #6ee7b7; border: 1px solid #10b981; }
        .badge-danger { background: #7f1d1d; color: #fca5a5; border: 1px solid #ef4444; }
        .badge-warning { background: #78350f; color: #fcd34d; border: 1px solid #f59e0b; }
        .badge-info { background: #1e3a8a; color: #93c5fd; border: 1px solid #3b82f6; }
        .btn { 
            padding: 10px 18px; 
            background: #3b82f6; 
            color: white; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 14px;
            font-weight: 500;
            text-decoration: none; 
            display: inline-block;
            transition: all 0.2s;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }
        .btn:hover { background: #2563eb; }
        .btn-small { padding: 6px 12px; font-size: 13px; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        .game-grid { 
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            padding: 24px;
        }
        .game-card { 
            background: #1a1f36; 
            border-radius: 8px; 
            overflow: hidden;
            border: 1px solid #2d3548;
            transition: all 0.2s;
        }
        .game-card:hover { border-color: #3b82f6; transform: translateY(-2px); }
        .game-card.inactive { opacity: 0.5; }
        .game-image { 
            width: 100%;
            height: 160px; 
            object-fit: cover; 
            background: #0f1626;
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: #4b5563; 
            font-size: 36px;
            font-weight: 600;
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
        }
        .game-image:hover::after {
            content: '📷 Click to Upload';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            font-weight: 600;
        }
        .game-image img { width: 100%; height: 100%; object-fit: cover; }
        .game-info { padding: 16px; }
        .game-info h3 { 
            color: #ffffff; 
            margin-bottom: 8px;
            font-size: 16px;
            font-weight: 600;
        }
        .game-meta { 
            color: #9ca3af; 
            font-size: 13px; 
            margin-bottom: 12px;
        }
        .game-actions { 
            display: flex; 
            gap: 8px; 
            flex-wrap: wrap;
            padding: 12px 16px;
            background: #0f1626;
            border-top: 1px solid #2d3548;
        }
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.8); 
            z-index: 1000; 
            overflow-y: auto;
        }
        .modal-content { 
            background: #1a1f36; 
            max-width: 600px; 
            margin: 50px auto; 
            padding: 24px; 
            border-radius: 12px;
            border: 1px solid #2d3548;
        }
        .modal-content h2 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #ffffff;
        }
        .form-group { margin-bottom: 16px; }
        .form-group label { 
            display: block; 
            margin-bottom: 6px; 
            color: #e5e7eb; 
            font-weight: 500;
            font-size: 14px;
        }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; 
            padding: 10px 12px; 
            border: 1px solid #2d3548; 
            border-radius: 6px; 
            font-size: 14px;
            font-family: inherit;
            background: #0f1626;
            color: #e5e7eb;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .form-group input[type="checkbox"] { width: auto; }
        .form-group small { color: #9ca3af; font-size: 13px; }
        .close { 
            float: right; 
            font-size: 24px; 
            cursor: pointer; 
            color: #9ca3af;
            line-height: 1;
        }
        .close:hover { color: #ffffff; }
        @media (max-width: 1200px) {
            .game-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 900px) {
            .game-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            body { font-size: 14px; }
            .header { padding: 10px 12px; }
            .header h1 { font-size: 16px; }
            .header a { padding: 6px 12px; font-size: 12px; }
            .container { padding: 0 12px; margin: 16px auto; }
            .game-grid { 
                grid-template-columns: 1fr; 
                gap: 12px;
                padding: 16px;
            }
            .game-card {
                display: flex;
                flex-direction: row;
                align-items: stretch;
            }
            .game-image {
                width: 120px;
                min-width: 120px;
                height: auto;
            }
            .game-info {
                flex: 1;
                padding: 12px;
            }
            .game-info h3 { font-size: 14px; }
            .game-meta { font-size: 12px; }
            .game-actions {
                padding: 8px 12px;
                flex-direction: column;
                width: auto;
                min-width: 80px;
                border-left: 1px solid #2d3548;
                border-top: none;
            }
            .stats { 
                grid-template-columns: 1fr; 
                gap: 12px;
            }
            .stat-card { padding: 16px; }
            .stat-card h3 { font-size: 12px; }
            .stat-card .value { font-size: 24px; }
            .tabs { 
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }
            .tabs::-webkit-scrollbar { display: none; }
            .tab { 
                padding: 10px 16px;
                font-size: 13px;
                white-space: nowrap;
            }
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            table { min-width: 800px; }
            th, td { padding: 12px 16px; font-size: 13px; }
            .btn { padding: 8px 12px; font-size: 13px; min-height: 44px; }
            .btn-small { padding: 5px 10px; font-size: 12px; min-height: 36px; }
            .modal-content {
                margin: 20px;
                max-width: calc(100% - 40px);
                padding: 20px;
            }
            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 16px; /* Prevents iOS zoom */
            }
        }
        @media (max-width: 480px) {
            .header h1 { font-size: 14px; }
            .header a { padding: 5px 10px; font-size: 11px; }
            .container { padding: 0 8px; }
            .game-grid { padding: 12px; gap: 10px; }
            .game-image { width: 100px; min-width: 100px; }
            .game-info { padding: 10px; }
            .game-info h3 { font-size: 13px; }
            .game-actions { min-width: 70px; padding: 6px 10px; }
            .btn-small { padding: 4px 8px; font-size: 11px; min-height: 32px; }
            .stat-card .value { font-size: 20px; }
            .modal-content { margin: 10px; padding: 16px; }
            th, td { padding: 10px 12px; font-size: 12px; }
        }
            .btn-small { padding: 4px 8px; font-size: 11px; }
            .stat-card .value { font-size: 20px; }
            .modal-content { margin: 10px; padding: 16px; }
        }
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
            border-top: 3px solid #3b82f6;
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
    </style>
</head>
<body>
    <div class="header">
        <h1>Admin Dashboard</h1>
        <div>
            <span style="margin-right: 20px;"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            <a href="index.php">View Casino</a>
            <a href="?logout=1" style="margin-left: 10px;">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($success)): ?>
            <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
                ✅ <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="number"><?php echo $totalUsers; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Games</h3>
                <div class="number"><?php echo $totalGames; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Bets</h3>
                <div class="number"><?php echo number_format($totalBets); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="number">₱<?php echo number_format($totalRevenue, 2); ?></div>
            </div>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('games')">🎮 Games</button>
            <button class="tab" onclick="switchTab('users')">👥 Users</button>
            <button class="tab" onclick="switchTab('history')">📊 Betting History</button>
            <button class="tab" onclick="switchTab('topplayers')">🏆 Top Players</button>
            <button class="tab" onclick="switchTab('mostplayed')">🎯 Most Bets Games</button>
            <button class="tab" onclick="switchTab('settings')">⚙️ Settings</button>
        </div>

        <!-- Games Tab -->
        <div id="games-tab" class="tab-content active">
            <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <button class="btn" onclick="showModal('addGameModal')">➕ Add New Game</button>
                <span style="color: #9ca3af;" id="games-counter">Showing <span id="loaded-count"><?php echo count($games); ?></span> of <?php echo $totalGamesCount; ?> games</span>
            </div>

            <div class="game-grid" id="game-grid">
                <?php foreach ($games as $game): ?>
                    <div class="game-card <?php echo $game['is_active'] ? '' : 'inactive'; ?>">
                        <div class="game-image" onclick="uploadImage(<?php echo $game['id']; ?>, '<?php echo htmlspecialchars($game['name'], ENT_QUOTES); ?>')">
                            <?php if ($game['image'] && file_exists($game['image'])): ?>
                                <img src="<?php echo htmlspecialchars($game['image']); ?>" alt="<?php echo htmlspecialchars($game['name']); ?>">
                            <?php else: ?>
                                🎰
                            <?php endif; ?>
                        </div>
                        <div class="game-info">
                            <h3><?php echo htmlspecialchars($game['name']); ?></h3>
                            <div class="game-meta">
                                <strong>ID:</strong> <?php echo htmlspecialchars($game['game_uid']); ?><br>
                                <strong>Provider:</strong> <?php echo htmlspecialchars($game['provider']); ?><br>
                                <strong>Category:</strong> <?php echo htmlspecialchars($game['category']); ?><br>
                                <strong>Status:</strong> <?php echo $game['is_active'] ? '✅ Active' : '❌ Inactive'; ?>
                            </div>
                            <div class="game-actions">
                                <button class="btn btn-small" onclick="editGame(<?php echo htmlspecialchars(json_encode($game)); ?>)">✏️ Edit</button>
                                <a href="?delete=<?php echo $game['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Delete this game?')">🗑️ Delete</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Loading Indicator -->
            <div class="loading-indicator" id="loading-indicator">
                <div class="spinner"></div>
                <p>Loading more games...</p>
            </div>
        </div>
        
        <script>
        let gamesOffset = <?php echo count($games); ?>;
        let totalGames = <?php echo $totalGamesCount; ?>;
        let isLoading = false;
        
        // Infinite scroll for games
        window.addEventListener('scroll', function() {
            if (document.getElementById('games-tab').classList.contains('active') && !isLoading && gamesOffset < totalGames) {
                const scrollPosition = window.innerHeight + window.scrollY;
                const pageHeight = document.documentElement.scrollHeight;
                
                // Load more when user is 300px from bottom
                if (scrollPosition >= pageHeight - 300) {
                    loadMoreGames();
                }
            }
        });
        
        function loadMoreGames() {
            if (isLoading || gamesOffset >= totalGames) return;
            
            isLoading = true;
            document.getElementById('loading-indicator').classList.add('active');
            
            fetch('?load_games=1&offset=' + gamesOffset)
                .then(response => response.json())
                .then(games => {
                    if (games.length > 0) {
                        const gameGrid = document.getElementById('game-grid');
                        
                        games.forEach(game => {
                            const gameCard = createGameCard(game);
                            gameGrid.insertAdjacentHTML('beforeend', gameCard);
                        });
                        
                        gamesOffset += games.length;
                        document.getElementById('loaded-count').textContent = gamesOffset;
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
        
        function createGameCard(game) {
            const imageHtml = game.image ? 
                `<img src="${escapeHtml(game.image)}" alt="${escapeHtml(game.name)}">` : 
                '🎰';
            
            const statusText = game.is_active == 1 ? '✅ Active' : '❌ Inactive';
            const inactiveClass = game.is_active == 1 ? '' : ' inactive';
            
            return `
                <div class="game-card${inactiveClass}">
                    <div class="game-image" onclick="uploadImage(${game.id}, '${escapeHtml(game.name)}')">
                        ${imageHtml}
                    </div>
                    <div class="game-info">
                        <h3>${escapeHtml(game.name)}</h3>
                        <div class="game-meta">
                            <strong>ID:</strong> ${escapeHtml(game.game_uid)}<br>
                            <strong>Provider:</strong> ${escapeHtml(game.provider)}<br>
                            <strong>Category:</strong> ${escapeHtml(game.category)}<br>
                            <strong>Status:</strong> ${statusText}
                        </div>
                        <div class="game-actions">
                            <button class="btn btn-small" onclick='editGame(${JSON.stringify(game)})'>✏️ Edit</button>
                            <a href="?delete=${game.id}" class="btn btn-small btn-danger" onclick="return confirm('Delete this game?')">🗑️ Delete</a>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        </script>

        <!-- Users Tab -->
        <div id="users-tab" class="tab-content">
            <div class="table-container">
                <h2 style="margin-bottom: 20px;">User Management</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Phone</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Device</th>
                            <th>Last IP</th>
                            <th>Logins</th>
                            <th>Registered</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): 
                            $userCurrency = $user['currency'] ?? 'PHP';
                        ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td><strong><?php echo formatCurrency($user['balance'], $userCurrency); ?></strong></td>
                                <td>
                                    <span class="badge <?php echo $user['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['last_device']): ?>
                                        <span style="font-size: 12px;">
                                            <?php echo htmlspecialchars($user['last_device']); ?><br>
                                            <small style="color: #64748b;"><?php echo htmlspecialchars($user['last_browser'] ?? 'Unknown'); ?> | <?php echo htmlspecialchars($user['last_os'] ?? 'Unknown'); ?></small>
                                        </span>
                                    <?php else: ?>
                                        <small style="color: #64748b;">N/A</small>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo htmlspecialchars($user['last_ip'] ?? 'N/A'); ?></small></td>
                                <td><span class="badge badge-info"><?php echo $user['login_count'] ?? 0; ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td><?php echo $user['last_login'] ? date('M d, H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                <td>
                                    <button class="btn btn-small" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">✏️ Edit</button>
                                    <button class="btn btn-small" onclick="viewUserHistory(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">📊 History</button>
                                    <button class="btn btn-small" onclick="viewLoginHistory(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">🔐 Logins</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Betting History Tab -->
        <div id="history-tab" class="tab-content">
            <div class="table-container">
                <h2 style="margin-bottom: 20px;">Betting History</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>User</th>
                            <th>Phone</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Balance Before</th>
                            <th>Balance After</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $trans): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i:s', strtotime($trans['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($trans['username'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($trans['phone'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo $trans['type'] === 'bet' ? 'badge-warning' : 
                                             ($trans['type'] === 'win' ? 'badge-success' : 'badge-info'); 
                                    ?>">
                                        <?php echo strtoupper($trans['type']); ?>
                                    </span>
                                </td>
                                <td><strong>₱<?php echo number_format($trans['amount'], 2); ?></strong></td>
                                <td>₱<?php echo number_format($trans['balance_before'], 2); ?></td>
                                <td>₱<?php echo number_format($trans['balance_after'], 2); ?></td>
                                <td><?php echo htmlspecialchars($trans['description'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Players Tab -->
        <div id="topplayers-tab" class="tab-content">
            <div class="table-container">
                <h2 style="margin-bottom: 20px;">🏆 Most Active Players</h2>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>Phone</th>
                            <th>Total Games</th>
                            <th>Total Bets</th>
                            <th>Total Wins</th>
                            <th>Net P/L</th>
                            <th>Current Balance</th>
                            <th>Last Played</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        foreach ($topPlayers as $player): 
                            $netPL = $player['total_wins'] - $player['total_bets'];
                            $plClass = $netPL >= 0 ? 'badge-success' : 'badge-warning';
                            $currency = $player['currency'] ?? 'PHP';
                            $symbol = $currency === 'PHP' ? '₱' : ($currency . ' ');
                        ?>
                            <tr>
                                <td><strong><?php echo $rank++; ?></strong></td>
                                <td><?php echo htmlspecialchars($player['username']); ?></td>
                                <td><?php echo htmlspecialchars($player['phone']); ?></td>
                                <td><strong><?php echo number_format($player['total_games']); ?></strong></td>
                                <td><?php echo $symbol . number_format($player['total_bets'], 2); ?></td>
                                <td><?php echo $symbol . number_format($player['total_wins'], 2); ?></td>
                                <td>
                                    <span class="badge <?php echo $plClass; ?>">
                                        <?php echo $symbol . number_format($netPL, 2); ?>
                                    </span>
                                </td>
                                <td><?php echo $symbol . number_format($player['balance'], 2); ?></td>
                                <td><?php echo $player['last_played'] ? date('M d, Y H:i', strtotime($player['last_played'])) : 'Never'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topPlayers)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: #999;">No player activity yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Most Bets Games Tab -->
        <div id="mostplayed-tab" class="tab-content">
            <div class="table-container">
                <h2 style="margin-bottom: 20px;">🎯 Most Bets Games</h2>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Image</th>
                            <th>Game Name</th>
                            <th>Provider</th>
                            <th>Category</th>
                            <th>Total Bets (₱)</th>
                            <th>Bet Count</th>
                            <th>Unique Players</th>
                            <th>Last Played</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        foreach ($mostBetGames as $game): 
                        ?>
                            <tr>
                                <td><strong><?php echo $rank++; ?></strong></td>
                                <td>
                                    <?php if ($game['image'] && file_exists($game['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($game['image']); ?>" style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 50px; background: #667eea; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                            <?php echo strtoupper(substr($game['name'], 0, 2)); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($game['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($game['provider']); ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo htmlspecialchars($game['category']); ?>
                                    </span>
                                </td>
                                <td><strong style="color: #667eea; font-size: 18px;">₱<?php echo number_format($game['total_bet_amount'], 2); ?></strong></td>
                                <td><?php echo number_format($game['bet_count']); ?> bets</td>
                                <td><?php echo number_format($game['unique_players']); ?> players</td>
                                <td><?php echo $game['last_played'] ? date('M d, Y H:i', strtotime($game['last_played'])) : 'Never'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($mostBetGames)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: #999;">No bets placed yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="settings-tab" class="tab-content">
            <div class="table-container">
                <h2 style="margin-bottom: 20px;">⚙️ Site Settings</h2>
                
                <form method="POST" enctype="multipart/form-data" style="max-width: 800px;">
                    <h3 style="margin-top: 0;">General Settings</h3>
                    
                    <div class="form-group">
                        <label>Casino Name</label>
                        <input type="text" name="casino_name" value="<?php echo htmlspecialchars($siteSettings['casino_name'] ?? 'Casino PHP'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Tagline</label>
                        <input type="text" name="casino_tagline" value="<?php echo htmlspecialchars($siteSettings['casino_tagline'] ?? 'Play & Win Big!'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Default Currency</label>
                        <select name="default_currency">
                            <?php
                            $currencies = ['PHP' => '₱ PHP', 'USD' => '$ USD', 'EUR' => '€ EUR', 'GBP' => '£ GBP', 'JPY' => '¥ JPY'];
                            $currentCurrency = $siteSettings['default_currency'] ?? 'PHP';
                            foreach ($currencies as $code => $name) {
                                $selected = ($code === $currentCurrency) ? 'selected' : '';
                                echo "<option value=\"$code\" $selected>$name</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Theme Color</label>
                        <input type="color" name="theme_color" value="<?php echo htmlspecialchars($siteSettings['theme_color'] ?? '#6366f1'); ?>">
                    </div>
                    
                    <h3 style="margin-top: 30px;">Logo & Branding</h3>
                    
                    <div class="form-group">
                        <label>Current Logo</label>
                        <?php if (!empty($siteSettings['logo_path']) && file_exists($siteSettings['logo_path'])): ?>
                            <img src="<?php echo htmlspecialchars($siteSettings['logo_path']); ?>" style="max-width: 200px; display: block; margin: 10px 0;">
                        <?php else: ?>
                            <p style="color: #999;">No logo uploaded</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>Upload New Logo</label>
                        <input type="file" name="logo_file" accept="image/*">
                        <small style="color: #666;">Recommended: PNG or SVG, max 500KB</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Logo Path (URL)</label>
                        <input type="text" name="logo_path" value="<?php echo htmlspecialchars($siteSettings['logo_path'] ?? ''); ?>" placeholder="images/logo.png">
                    </div>
                    
                    <h3 style="margin-top: 30px;">Game Settings</h3>
                    
                    <div class="form-group">
                        <label>Minimum Bet</label>
                        <input type="number" step="0.01" name="min_bet" value="<?php echo htmlspecialchars($siteSettings['min_bet'] ?? '1.00'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Maximum Bet</label>
                        <input type="number" step="0.01" name="max_bet" value="<?php echo htmlspecialchars($siteSettings['max_bet'] ?? '10000.00'); ?>" required>
                    </div>
                    
                    <h3 style="margin-top: 30px;">Contact Information</h3>
                    
                    <div class="form-group">
                        <label>Support Email</label>
                        <input type="email" name="support_email" value="<?php echo htmlspecialchars($siteSettings['support_email'] ?? ''); ?>" placeholder="support@casino.com">
                    </div>
                    
                    <div class="form-group">
                        <label>Support Phone</label>
                        <input type="text" name="support_phone" value="<?php echo htmlspecialchars($siteSettings['support_phone'] ?? ''); ?>" placeholder="+639123456789">
                    </div>
                    
                    <h3 style="margin-top: 30px;">Social Media</h3>
                    
                    <div class="form-group">
                        <label>Facebook URL</label>
                        <input type="url" name="facebook_url" value="<?php echo htmlspecialchars($siteSettings['facebook_url'] ?? ''); ?>" placeholder="https://facebook.com/your-page">
                    </div>
                    
                    <div class="form-group">
                        <label>Twitter URL</label>
                        <input type="url" name="twitter_url" value="<?php echo htmlspecialchars($siteSettings['twitter_url'] ?? ''); ?>" placeholder="https://twitter.com/your-account">
                    </div>
                    
                    <div class="form-group">
                        <label>Instagram URL</label>
                        <input type="url" name="instagram_url" value="<?php echo htmlspecialchars($siteSettings['instagram_url'] ?? ''); ?>" placeholder="https://instagram.com/your-account">
                    </div>
                    
                    <button type="submit" name="update_settings" class="btn" style="margin-top: 20px;">💾 Save Settings</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Balance Modal -->
    <div id="editBalanceModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('editBalanceModal')">&times;</span>
            <h2>Edit User Balance</h2>
            <p id="balance_username" style="color: #666; margin-bottom: 20px;"></p>
            <form method="POST">
                <input type="hidden" name="user_id" id="balance_user_id">
                <input type="hidden" name="currency" id="balance_currency">
                <div class="form-group">
                    <label>Current Balance</label>
                    <input type="text" id="current_balance" disabled>
                </div>
                <div class="form-group">
                    <label>New Balance</label>
                    <input type="number" step="0.01" name="new_balance" id="new_balance" required>
                </div>
                <button type="submit" name="update_user_balance" class="btn">Update Balance</button>
            </form>
        </div>
    </div>

    <!-- View User History Modal -->
    <div id="userHistoryModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <span class="close" onclick="hideModal('userHistoryModal')">&times;</span>
            <h2>User Betting History</h2>
            <p id="history_username" style="color: #666; margin-bottom: 20px;"></p>
            <div id="user_history_content" style="max-height: 500px; overflow-y: auto;">
                Loading...
            </div>
        </div>
    </div>

    <!-- Edit User Info Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content" style="max-width: 850px;">
            <span class="close" onclick="hideModal('editUserModal')">&times;</span>
            <h2 style="margin-bottom: 25px; color: #1e293b; font-size: 24px;">✏️ Edit User Information</h2>
            <form method="POST">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <!-- User Details Card -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 12px; margin-bottom: 20px; color: white;">
                    <h3 style="margin: 0 0 15px 0; font-size: 16px; opacity: 0.9;">👤 Account Details</h3>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                        <div class="form-group">
                            <label style="color: white; opacity: 0.9; font-weight: 500;">Username</label>
                            <input type="text" name="username" id="edit_username" required style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 12px; border-radius: 8px; width: 100%; font-size: 14px;">
                        </div>
                        <div class="form-group">
                            <label style="color: white; opacity: 0.9; font-weight: 500;">Phone Number</label>
                            <input type="text" name="phone" id="edit_phone" required style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 12px; border-radius: 8px; width: 100%; font-size: 14px;">
                        </div>
                    </div>
                </div>

                <!-- Balance & Settings Card -->
                <div style="background: #0f1626; padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #2d3548;">
                    <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #e5e7eb;">💰 Balance & Settings</h3>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                        <div class="form-group">
                            <label style="font-weight: 500; color: #9ca3af; font-size: 13px; margin-bottom: 8px; display: block;">Balance</label>
                            <input type="number" step="0.01" name="balance" id="edit_balance" required style="padding: 12px; border: 2px solid #2d3548; border-radius: 8px; width: 100%; font-size: 16px; font-weight: 600; color: #10b981; background: #1a1f36;">
                        </div>
                        <div class="form-group">
                            <label style="font-weight: 500; color: #9ca3af; font-size: 13px; margin-bottom: 8px; display: block;">Currency</label>
                            <select name="currency" id="edit_currency" style="padding: 12px; border: 2px solid #2d3548; border-radius: 8px; width: 100%; font-size: 14px; background: #1a1f36; color: #e5e7eb;">
                                <option value="PHP">PHP (₱)</option>
                                <option value="USD">USD ($)</option>
                                <option value="EUR">EUR (€)</option>
                                <option value="GBP">GBP (£)</option>
                                <option value="JPY">JPY (¥)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="font-weight: 500; color: #9ca3af; font-size: 13px; margin-bottom: 8px; display: block;">Status</label>
                            <select name="status" id="edit_status" style="padding: 12px; border: 2px solid #2d3548; border-radius: 8px; width: 100%; font-size: 14px; background: #1a1f36; color: #e5e7eb;">
                                <option value="active">✅ Active</option>
                                <option value="suspended">⏸️ Suspended</option>
                                <option value="banned">🚫 Banned</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Tracking Information Card -->
                <div id="edit_tracking_info"></div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="update_user_info" class="btn" style="flex: 1; padding: 15px; font-size: 16px; font-weight: 600; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 10px; cursor: pointer; color: white;">
                        💾 Save Changes
                    </button>
                    <button type="button" onclick="hideModal('editUserModal')" style="padding: 15px 30px; font-size: 16px; background: #e2e8f0; border: none; border-radius: 10px; cursor: pointer; color: #475569; font-weight: 600;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Login History Modal -->
    <div id="loginHistoryModal" class="modal">
        <div class="modal-content" style="max-width: 1000px;">
            <span class="close" onclick="hideModal('loginHistoryModal')">&times;</span>
            <h2>User Login History</h2>
            <p id="login_history_username" style="color: #666; margin-bottom: 20px;"></p>
            <div id="login_history_content" style="max-height: 500px; overflow-y: auto;">
                Loading...
            </div>
        </div>
    </div>

    <!-- Add Game Modal -->
    <div id="addGameModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('addGameModal')">&times;</span>
            <h2>Add New Game</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Game UID (ID for launching)</label>
                    <input type="text" name="game_uid" required>
                </div>
                <div class="form-group">
                    <label>Game Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Provider</label>
                    <input type="text" name="provider" value="JILI" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" required>
                        <option value="Slots">Slots</option>
                        <option value="Table">Table</option>
                        <option value="Fishing">Fishing</option>
                        <option value="Arcade">Arcade</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" value="0">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" checked> Active
                    </label>
                </div>
                <button type="submit" name="add_game" class="btn">Add Game</button>
            </form>
        </div>
    </div>

    <!-- Edit Game Modal -->
    <div id="editGameModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('editGameModal')">&times;</span>
            <h2>Edit Game</h2>
            <form method="POST">
                <input type="hidden" name="game_id" id="edit_game_id">
                <div class="form-group">
                    <label>Game UID</label>
                    <input type="text" id="edit_game_uid" disabled>
                </div>
                <div class="form-group">
                    <label>Game Name</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Provider</label>
                    <input type="text" name="provider" id="edit_provider" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" id="edit_category" required>
                        <option value="Slots">Slots</option>
                        <option value="Table">Table</option>
                        <option value="Fishing">Fishing</option>
                        <option value="Arcade">Arcade</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" id="edit_sort_order">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="edit_is_active"> Active
                    </label>
                </div>
                <button type="submit" name="update_game" class="btn">Update Game</button>
            </form>
        </div>
    </div>

    <!-- Upload Image Modal -->
    <div id="uploadImageModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('uploadImageModal')">&times;</span>
            <h2>Upload Game Image</h2>
            <p id="upload_game_name" style="color: #666; margin-bottom: 20px;"></p>
            <form id="uploadImageForm" enctype="multipart/form-data">
                <input type="hidden" name="game_id" id="upload_game_id">
                <div class="form-group">
                    <label>Select Image</label>
                    <input type="file" name="game_image" id="game_image_input" accept="image/*" required>
                    <small style="color: #9ca3af;">Image will upload automatically when selected</small>
                </div>
                <div id="upload_progress" style="display: none; margin-bottom: 15px;">
                    <div style="background: #e5e7eb; border-radius: 4px; height: 6px; overflow: hidden;">
                        <div id="progress_bar" style="background: #2563eb; height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <p id="upload_status" style="font-size: 13px; color: #666; margin-top: 8px;"></p>
                </div>
                <button type="submit" id="upload_btn" class="btn" style="display: none;">Upload Image</button>
            </form>
        </div>
    </div>

    <script>
        // Check for success message in URL
        window.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === 'game_added') {
                alert('✅ Game added successfully!');
                // Remove the success parameter from URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            // Auto-upload on file selection
            const fileInput = document.getElementById('game_image_input');
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    if (this.files && this.files.length > 0) {
                        const form = document.getElementById('uploadImageForm');
                        const submitEvent = new Event('submit', {
                            bubbles: true,
                            cancelable: true
                        });
                        form.dispatchEvent(submitEvent);
                    }
                });
            }
        });
        
        function showModal(id) {
            document.getElementById(id).style.display = 'block';
        }
        
        function hideModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
        
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_phone').value = user.phone;
            document.getElementById('edit_balance').value = user.balance;
            document.getElementById('edit_currency').value = user.currency || 'PHP';
            document.getElementById('edit_status').value = user.status;
            
            const netPL = (parseFloat(user.total_wins || 0) - parseFloat(user.total_bets || 0));
            const plColor = netPL >= 0 ? '#10b981' : '#ef4444';
            const plIcon = netPL >= 0 ? '📈' : '📉';
            
            // Display enhanced tracking info
            document.getElementById('edit_tracking_info').innerHTML = `
                <div style="background: #1a1f36; padding: 20px; border-radius: 12px; border: 1px solid #2d3548;">
                    <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #e5e7eb;">📊 User Activity & Statistics</h3>
                    
                    <!-- Statistics Grid -->
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px;">
                        <div style="background: linear-gradient(135deg, #3b82f6, #2563eb); padding: 15px; border-radius: 10px; text-align: center; color: white;">
                            <div style="font-size: 24px; font-weight: bold; margin-bottom: 5px;">${user.login_count || 0}</div>
                            <div style="font-size: 12px; opacity: 0.9;">🔐 Total Logins</div>
                        </div>
                        <div style="background: linear-gradient(135deg, #f59e0b, #d97706); padding: 15px; border-radius: 10px; text-align: center; color: white;">
                            <div style="font-size: 20px; font-weight: bold; margin-bottom: 5px;">₱${parseFloat(user.total_bets || 0).toFixed(2)}</div>
                            <div style="font-size: 12px; opacity: 0.9;">🎲 Total Bets</div>
                        </div>
                        <div style="background: linear-gradient(135deg, #10b981, #059669); padding: 15px; border-radius: 10px; text-align: center; color: white;">
                            <div style="font-size: 20px; font-weight: bold; margin-bottom: 5px;">₱${parseFloat(user.total_wins || 0).toFixed(2)}</div>
                            <div style="font-size: 12px; opacity: 0.9;">🏆 Total Wins</div>
                        </div>
                        <div style="background: linear-gradient(135deg, ${plColor}, ${plColor}); padding: 15px; border-radius: 10px; text-align: center; color: white;">
                            <div style="font-size: 20px; font-weight: bold; margin-bottom: 5px;">₱${netPL.toFixed(2)}</div>
                            <div style="font-size: 12px; opacity: 0.9;">${plIcon} Net P/L</div>
                        </div>
                    </div>
                    
                    <!-- Device & Session Info -->
                    <div style="background: #0f1626; padding: 15px; border-radius: 10px; border: 1px solid #2d3548;">
                        <h4 style="margin: 0 0 12px 0; font-size: 14px; color: #e5e7eb; font-weight: 600;">🖥️ Last Session Information</h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                            <div style="padding: 10px; background: #1a1f36; border-radius: 6px; border: 1px solid #2d3548;">
                                <div style="font-size: 11px; color: #9ca3af; margin-bottom: 4px;">IP Address</div>
                                <div style="font-size: 13px; color: #e5e7eb; font-weight: 600; font-family: monospace;">${user.last_ip || 'Not available'}</div>
                            </div>
                            <div style="padding: 10px; background: #1a1f36; border-radius: 6px; border: 1px solid #2d3548;">
                                <div style="font-size: 11px; color: #9ca3af; margin-bottom: 4px;">Device Type</div>
                                <div style="font-size: 13px; color: #e5e7eb; font-weight: 600;">${user.last_device || 'Unknown'}</div>
                            </div>
                            <div style="padding: 10px; background: #1a1f36; border-radius: 6px; border: 1px solid #2d3548;">
                                <div style="font-size: 11px; color: #9ca3af; margin-bottom: 4px;">Browser</div>
                                <div style="font-size: 13px; color: #e5e7eb; font-weight: 600;">${user.last_browser || 'Unknown'}</div>
                            </div>
                            <div style="padding: 10px; background: #1a1f36; border-radius: 6px; border: 1px solid #2d3548;">
                                <div style="font-size: 11px; color: #9ca3af; margin-bottom: 4px;">Operating System</div>
                                <div style="font-size: 13px; color: #e5e7eb; font-weight: 600;">${user.last_os || 'Unknown'}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            showModal('editUserModal');
        }
        
        function viewLoginHistory(userId, username) {
            document.getElementById('login_history_username').textContent = 'Login History: ' + username;
            showModal('loginHistoryModal');
            
            // Fetch login history via AJAX
            fetch('get_login_history.php?user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.logins.length > 0) {
                        let html = '<table style="width: 100%;"><thead><tr>' +
                            '<th>Login Time</th><th>Logout Time</th><th>Duration</th><th>IP Address</th><th>Device</th><th>Browser</th><th>OS</th>' +
                            '</tr></thead><tbody>';
                        
                        data.logins.forEach(login => {
                            const duration = login.session_duration ? formatDuration(login.session_duration) : 'Active';
                            html += `<tr>
                                <td>${login.login_time}</td>
                                <td>${login.logout_time || '-'}</td>
                                <td>${duration}</td>
                                <td><small>${login.ip_address || 'N/A'}</small></td>
                                <td>${login.device || 'N/A'}</td>
                                <td>${login.browser || 'N/A'}</td>
                                <td>${login.os || 'N/A'}</td>
                            </tr>`;
                        });
                        
                        html += '</tbody></table>';
                        document.getElementById('login_history_content').innerHTML = html;
                    } else {
                        document.getElementById('login_history_content').innerHTML = '<p>No login history found.</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('login_history_content').innerHTML = '<p>Error loading login history.</p>';
                });
        }
        
        function formatDuration(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            
            if (hours > 0) return `${hours}h ${minutes}m`;
            if (minutes > 0) return `${minutes}m ${secs}s`;
            return `${secs}s`;
        }
        
        function editUserBalance(userId, username, currentBalance, currency) {
            currency = currency || 'PHP';
            const symbols = {'PHP': '₱', 'USD': '$', 'GBP': '£', 'EUR': '€', 'JPY': '¥', 'CNY': '¥', 'SGD': 'S$', 'MYR': 'RM', 'THB': '฿', 'VND': '₫', 'IDR': 'Rp'};
            const symbol = symbols[currency] || currency + ' ';
            
            document.getElementById('balance_user_id').value = userId;
            document.getElementById('balance_currency').value = currency;
            document.getElementById('balance_username').textContent = 'User: ' + username + ' (' + currency + ')';
            document.getElementById('current_balance').value = symbol + parseFloat(currentBalance).toFixed(2);
            document.getElementById('new_balance').value = currentBalance;
            showModal('editBalanceModal');
        }
        
        function viewUserHistory(userId, username) {
            document.getElementById('history_username').textContent = 'User: ' + username;
            showModal('userHistoryModal');
            
            // Fetch user transaction history via AJAX
            fetch('get_user_history.php?user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<table style="width: 100%;"><thead><tr>' +
                            '<th>Date/Time</th><th>Type</th><th>Game</th><th>Amount</th><th>Balance Before</th><th>Balance After</th>' +
                            '</tr></thead><tbody>';
                        
                        data.transactions.forEach(t => {
                            const badgeClass = t.type === 'bet' ? 'badge-warning' : (t.type === 'win' ? 'badge-success' : 'badge-info');
                            const currency = t.currency || 'PHP';
                            const symbol = currency === 'PHP' ? '₱' : currency + ' ';
                            const gameName = t.game_name || 'N/A';
                            html += `<tr>
                                <td>${t.created_at}</td>
                                <td><span class="badge ${badgeClass}">${t.type.toUpperCase()}</span></td>
                                <td><small>${gameName}</small></td>
                                <td><strong>${symbol}${parseFloat(t.amount).toFixed(2)}</strong></td>
                                <td>${symbol}${parseFloat(t.balance_before).toFixed(2)}</td>
                                <td>${symbol}${parseFloat(t.balance_after).toFixed(2)}</td>
                            </tr>`;
                        });
                        
                        html += '</tbody></table>';
                        document.getElementById('user_history_content').innerHTML = html;
                    } else {
                        document.getElementById('user_history_content').innerHTML = '<p>No transaction history found.</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('user_history_content').innerHTML = '<p>Error loading history.</p>';
                });
        }
        
        function editGame(game) {
            document.getElementById('edit_game_id').value = game.id;
            document.getElementById('edit_game_uid').value = game.game_uid;
            document.getElementById('edit_name').value = game.name;
            document.getElementById('edit_provider').value = game.provider;
            document.getElementById('edit_category').value = game.category;
            document.getElementById('edit_sort_order').value = game.sort_order;
            document.getElementById('edit_is_active').checked = game.is_active == 1;
            showModal('editGameModal');
        }
        
        function uploadImage(gameId, gameName) {
            document.getElementById('upload_game_id').value = gameId;
            document.getElementById('upload_game_name').textContent = gameName;
            document.getElementById('upload_progress').style.display = 'none';
            document.getElementById('uploadImageForm').reset();
            showModal('uploadImageModal');
        }
        
        // Handle image upload via AJAX
        document.getElementById('uploadImageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const uploadBtn = document.getElementById('upload_btn');
            const progressDiv = document.getElementById('upload_progress');
            const progressBar = document.getElementById('progress_bar');
            const statusText = document.getElementById('upload_status');
            const gameId = document.getElementById('upload_game_id').value;
            
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
            progressDiv.style.display = 'block';
            progressBar.style.width = '0%';
            statusText.textContent = 'Uploading...';
            
            fetch('upload_game_image.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                progressBar.style.width = '100%';
                
                if (data.success) {
                    statusText.textContent = '✓ Upload successful!';
                    statusText.style.color = '#10b981';
                    
                    // Update the game card image without refresh
                    const gameCard = document.querySelector(`[onclick*="uploadImage(${gameId}"]`).closest('.game-card');
                    const gameImage = gameCard.querySelector('.game-image');
                    
                    if (data.image_path) {
                        gameImage.innerHTML = `<img src="${data.image_path}?t=${Date.now()}" alt="Game Image" style="width: 100%; height: 100%; object-fit: cover;">`;
                    }
                    
                    setTimeout(() => {
                        hideModal('uploadImageModal');
                        uploadBtn.disabled = false;
                        uploadBtn.textContent = 'Upload Image';
                    }, 1000);
                } else {
                    statusText.textContent = '✗ ' + (data.message || 'Upload failed');
                    statusText.style.color = '#ef4444';
                    uploadBtn.disabled = false;
                    uploadBtn.textContent = 'Upload Image';
                }
            })
            .catch(error => {
                statusText.textContent = '✗ Upload error: ' + error.message;
                statusText.style.color = '#ef4444';
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Upload Image';
            });
        });
        
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
<?php
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
?>
