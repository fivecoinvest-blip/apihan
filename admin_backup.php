<?php
/**
 * Admin Panel - Game Management
 */
session_start();
require_once 'config.php';
require_once 'db_helper.php';

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
    } else {
        $error = "Invalid username or password";
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
            $success = "Image uploaded successfully!";
        }
    }
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
    $success = "Game updated successfully!";
}

// Handle game delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM games WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $success = "Game deleted successfully!";
}

// Handle add new game
if (isset($_POST['add_game'])) {
    $stmt = $pdo->prepare("INSERT INTO games (game_uid, name, provider, category, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['game_uid'],
        $_POST['name'],
        $_POST['provider'],
        $_POST['category'],
        isset($_POST['is_active']) ? 1 : 0,
        $_POST['sort_order']
    ]);
    $success = "Game added successfully!";
}

// Handle user balance update
if (isset($_POST['update_user_balance'])) {
    $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $stmt->execute([$_POST['new_balance'], $_POST['user_id']]);
    $success = "User balance updated successfully!";
}

// Get statistics
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalGames = $pdo->query("SELECT COUNT(*) FROM games WHERE is_active = 1")->fetchColumn();
$totalBets = $pdo->query("SELECT COUNT(*) FROM transactions WHERE type = 'bet'")->fetchColumn();
$totalRevenue = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'bet'")->fetchColumn() ?? 0;

// Get all games
$games = $pdo->query("SELECT * FROM games ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get all users
$users = $pdo->query("SELECT id, username, phone, balance, created_at, last_login, status FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get recent transactions (betting history)
$transactions = $pdo->query("
    SELECT t.*, u.username, u.phone 
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header h1 { font-size: 24px; }
        .header a { color: white; text-decoration: none; padding: 10px 20px; background: rgba(255,255,255,0.2); border-radius: 8px; }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-card h3 { color: #666; font-size: 14px; margin-bottom: 10px; }
        .stat-card .number { font-size: 32px; font-weight: bold; color: #667eea; }
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e5e7eb; }
        .tab { padding: 15px 30px; background: transparent; border: none; color: #666; cursor: pointer; font-size: 16px; font-weight: 500; border-bottom: 3px solid transparent; transition: all 0.3s; }
        .tab.active { color: #667eea; border-bottom-color: #667eea; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .table-container { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 12px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
        tr:hover { background: #f8fafc; }
        .badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .btn { padding: 10px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; text-decoration: none; display: inline-block; }
        .btn-small { padding: 5px 10px; font-size: 14px; }
        .btn-danger { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .game-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .game-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .game-card.inactive { opacity: 0.5; }
        .game-image { width: 100%; height: 150px; object-fit: cover; border-radius: 8px; margin-bottom: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 48px; }
        .game-image img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
        .game-info h3 { color: #333; margin-bottom: 5px; }
        .game-meta { color: #666; font-size: 14px; margin-bottom: 10px; }
        .game-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto; }
        .modal-content { background: white; max-width: 600px; margin: 50px auto; padding: 30px; border-radius: 15px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; color: #333; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px; }
        .form-group input[type="checkbox"] { width: auto; }
        .close { float: right; font-size: 28px; cursor: pointer; color: #999; }
        @media (max-width: 768px) {
            .game-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎮 Game Management</h1>
        <div>
            <span style="margin-right: 20px;">👤 <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            <a href="casino.php">View Casino</a>
            <a href="?logout=1" style="margin-left: 10px;">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($success)) echo "<div class='success'>✅ $success</div>"; ?>

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
                <div class="number">$<?php echo number_format($totalRevenue, 2); ?></div>
            </div>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('games')">🎮 Games</button>
            <button class="tab" onclick="switchTab('users')">👥 Users</button>
            <button class="tab" onclick="switchTab('history')">📊 Betting History</button>
        </div>

        <!-- Games Tab -->
        <div id="games-tab" class="tab-content active">
            <button class="btn" onclick="showModal('addGameModal')">➕ Add New Game</button>
        </div>

        <div class="game-grid">
            <?php foreach ($games as $game): ?>
                <div class="game-card <?php echo $game['is_active'] ? '' : 'inactive'; ?>">
                    <div class="game-image">
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
                            <button class="btn btn-small" onclick="uploadImage(<?php echo $game['id']; ?>, '<?php echo htmlspecialchars($game['name']); ?>')">📷 Upload Image</button>
                            <a href="?delete=<?php echo $game['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Delete this game?')">🗑️ Delete</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
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
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="game_id" id="upload_game_id">
                <div class="form-group">
                    <label>Select Image</label>
                    <input type="file" name="game_image" accept="image/*" required>
                </div>
                <button type="submit" name="upload_image" class="btn">Upload Image</button>
            </form>
        </div>
    </div>

    <script>
        function showModal(id) {
            document.getElementById(id).style.display = 'block';
        }
        
        function hideModal(id) {
            document.getElementById(id).style.display = 'none';
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
            showModal('uploadImageModal');
        }
        
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
