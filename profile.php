<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'db_helper.php';
require_once 'redis_helper.php';
require_once 'currency_helper.php';
require_once 'settings_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Load site settings
$casinoName = SiteSettings::get('casino_name', 'Casino PHP');
$themeColor = SiteSettings::get('theme_color', '#6366f1');

$userModel = new User();
$currentUser = $userModel->getById($_SESSION['user_id']);
$balance = $userModel->getBalance($_SESSION['user_id']);
$userCurrency = $currentUser['currency'] ?? 'PHP';
$username = $currentUser['username'] ?? '';
$phone = $currentUser['phone'] ?? '';

$db = Database::getInstance();
$pdo = $db->getConnection();

// Handle success/error messages
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);

// Handle profile update
if (isset($_POST['update_profile'])) {
    $newUsername = trim($_POST['username']);
    $newPhone = trim($_POST['phone']);
    
    if (empty($newUsername)) {
        $_SESSION['error'] = 'Username cannot be empty';
    } elseif (strlen($newUsername) < 3) {
        $_SESSION['error'] = 'Username must be at least 3 characters';
    } else {
        // Check if username is already taken (excluding current user)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$newUsername, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = 'Username is already taken';
        } else {
            // Update profile
            $stmt = $pdo->prepare("UPDATE users SET username = ?, phone = ? WHERE id = ?");
            $stmt->execute([$newUsername, $newPhone, $_SESSION['user_id']]);
            
            $_SESSION['success'] = 'Profile updated successfully!';
            $_SESSION['username'] = $newUsername;
        }
    }
    
    header('Location: profile.php');
    exit;
}

// Handle password change
if (isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Verify current password
    if (!password_verify($currentPassword, $currentUser['password'])) {
        $_SESSION['error'] = 'Current password is incorrect';
    } elseif (strlen($newPassword) < 6) {
        $_SESSION['error'] = 'New password must be at least 6 characters';
    } elseif ($newPassword !== $confirmPassword) {
        $_SESSION['error'] = 'New passwords do not match';
    } else {
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
        
        $_SESSION['success'] = 'Password changed successfully!';
    }
    
    header('Location: profile.php');
    exit;
}

// Get user statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN type = 'bet' THEN 1 END) as total_bets_count,
        COALESCE(SUM(CASE WHEN type = 'bet' THEN amount ELSE 0 END), 0) as total_bets,
        COALESCE(SUM(CASE WHEN type = 'win' THEN amount ELSE 0 END), 0) as total_wins,
        COALESCE(SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_deposits,
        COALESCE(SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_withdrawals,
        COUNT(DISTINCT game_uid) as games_played
    FROM transactions 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$netProfitLoss = $stats['total_wins'] - $stats['total_bets'];

// Get recent login history
$stmt = $pdo->prepare("
    SELECT * FROM login_history 
    WHERE user_id = ? 
    ORDER BY login_time DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$loginHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($casinoName); ?> - Profile</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #fff;
            min-height: 100vh;
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
            text-decoration: none;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .username {
            color: #94a3b8;
            font-size: 14px;
        }
        
        .balance-display {
            background: linear-gradient(135deg, #10b981, #059669);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 18px;
        }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Back Link */
        .back-link {
            display: inline-block;
            color: #94a3b8;
            text-decoration: none;
            margin-bottom: 20px;
            transition: color 0.3s;
            font-size: 15px;
        }
        
        .back-link:hover {
            color: #fff;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            overflow-x: auto;
            padding-bottom: 10px;
        }
        
        .tab {
            padding: 12px 24px;
            background: #1e293b;
            border: none;
            border-radius: 8px;
            color: #94a3b8;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .tab.active {
            background: linear-gradient(135deg, <?php echo $themeColor; ?>, #4f46e5);
            color: #fff;
        }
        
        .tab:hover {
            background: #334155;
        }
        
        /* Tab Content */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Cards */
        .card {
            background: #1e293b;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .card-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #fff;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #1e293b, #334155);
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid;
        }
        
        .stat-card.profile { border-color: #6366f1; }
        .stat-card.bets { border-color: #ef4444; }
        .stat-card.wins { border-color: #10b981; }
        .stat-card.profit { border-color: #8b5cf6; }
        .stat-card.games { border-color: #f59e0b; }
        .stat-card.deposits { border-color: #06b6d4; }
        
        .stat-label {
            color: #94a3b8;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
        }
        
        /* Profile Info Card */
        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            background: #0f172a;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #334155;
        }
        
        .info-label {
            color: #94a3b8;
            font-size: 13px;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #fff;
            font-size: 16px;
            font-weight: 600;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #cbd5e1;
            font-size: 14px;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            color: #fff;
            font-size: 15px;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: <?php echo $themeColor; ?>;
        }
        
        .btn {
            padding: 12px 30px;
            background: linear-gradient(135deg, <?php echo $themeColor; ?>, #4f46e5);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            width: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-secondary {
            background: #334155;
        }
        
        .btn-secondary:hover {
            background: #475569;
        }
        
        /* Alerts */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid #10b981;
            color: #10b981;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
        }
        
        /* Table */
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .history-table th,
        .history-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #334155;
        }
        
        .history-table th {
            color: #94a3b8;
            font-weight: 600;
            font-size: 14px;
        }
        
        .history-table tbody tr:hover {
            background: #334155;
        }
        
        .device-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(99, 102, 241, 0.2);
            color: #6366f1;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 10px;
            }
            
            .container {
                padding: 15px;
            }
            
            .tabs {
                gap: 5px;
            }
            
            .tab {
                padding: 10px 16px;
                font-size: 14px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-info {
                grid-template-columns: 1fr;
            }
            
            .stat-value {
                font-size: 20px;
            }
            
            .balance-display {
                font-size: 16px;
            }
            
            .history-table {
                font-size: 13px;
            }
            
            .history-table th,
            .history-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <a href="index.php" class="logo">🎰 <?php echo htmlspecialchars($casinoName); ?></a>
        <div class="user-info">
            <span class="username"><?php echo htmlspecialchars($username); ?></span>
            <div class="balance-display"><?php echo formatCurrency($balance, $userCurrency); ?></div>
        </div>
    </div>

    <div class="container">
        <a href="index.php" class="back-link">← Back to Games</a>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card profile">
                <div class="stat-label">👤 Account Status</div>
                <div class="stat-value" style="font-size: 18px; color: #10b981;">Active</div>
            </div>
            <div class="stat-card bets">
                <div class="stat-label">🎲 Total Bets</div>
                <div class="stat-value"><?php echo formatCurrency($stats['total_bets'], $userCurrency); ?></div>
            </div>
            <div class="stat-card wins">
                <div class="stat-label">🏆 Total Wins</div>
                <div class="stat-value"><?php echo formatCurrency($stats['total_wins'], $userCurrency); ?></div>
            </div>
            <div class="stat-card profit">
                <div class="stat-label"><?php echo $netProfitLoss >= 0 ? '📈' : '📉'; ?> Net P/L</div>
                <div class="stat-value" style="color: <?php echo $netProfitLoss >= 0 ? '#10b981' : '#ef4444'; ?>">
                    <?php echo formatCurrency($netProfitLoss, $userCurrency); ?>
                </div>
            </div>
            <div class="stat-card games">
                <div class="stat-label">🎮 Games Played</div>
                <div class="stat-value"><?php echo $stats['games_played']; ?></div>
            </div>
            <div class="stat-card deposits">
                <div class="stat-label">📊 Bets Placed</div>
                <div class="stat-value"><?php echo number_format($stats['total_bets_count']); ?></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('info')">📋 Account Info</button>
            <button class="tab" onclick="switchTab('edit')">✏️ Edit Profile</button>
            <button class="tab" onclick="switchTab('password')">🔐 Change Password</button>
            <button class="tab" onclick="switchTab('history')">📜 Login History</button>
        </div>

        <!-- Account Info Tab -->
        <div id="info-tab" class="tab-content active">
            <div class="card">
                <h2 class="card-title">📋 Account Information</h2>
                <div class="profile-info">
                    <div class="info-item">
                        <div class="info-label">Username</div>
                        <div class="info-value"><?php echo htmlspecialchars($username); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($phone); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Current Balance</div>
                        <div class="info-value" style="color: #10b981;"><?php echo formatCurrency($balance, $userCurrency); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Currency</div>
                        <div class="info-value"><?php echo htmlspecialchars($userCurrency); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Member Since</div>
                        <div class="info-value"><?php echo date('M d, Y', strtotime($currentUser['created_at'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Last Login</div>
                        <div class="info-value"><?php echo $currentUser['last_login'] ? date('M d, Y H:i', strtotime($currentUser['last_login'])) : 'Never'; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Profile Tab -->
        <div id="edit-tab" class="tab-content">
            <div class="card">
                <h2 class="card-title">✏️ Edit Profile</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required minlength="3">
                    </div>
                    <div class="form-group">
                        <label>Phone Number (Cannot be changed)</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($phone); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Currency (Cannot be changed)</label>
                        <input type="text" value="<?php echo htmlspecialchars($userCurrency); ?>" disabled>
                    </div>
                    <button type="submit" name="update_profile" class="btn">Update Profile</button>
                </form>
            </div>
        </div>

        <!-- Change Password Tab -->
        <div id="password-tab" class="tab-content">
            <div class="card">
                <h2 class="card-title">🔐 Change Password</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="6">
                    </div>
                    <button type="submit" name="change_password" class="btn">Change Password</button>
                </form>
            </div>
        </div>

        <!-- Login History Tab -->
        <div id="history-tab" class="tab-content">
            <div class="card">
                <h2 class="card-title">📜 Login History</h2>
                <?php if (empty($loginHistory)): ?>
                    <div style="text-align: center; padding: 40px; color: #64748b;">
                        <div style="font-size: 48px; margin-bottom: 15px;">📋</div>
                        <p>No login history available</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>IP Address</th>
                                    <th>Device</th>
                                    <th>Browser</th>
                                    <th>OS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($loginHistory as $login): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i:s', strtotime($login['login_time'])); ?></td>
                                        <td style="font-family: monospace;"><?php echo htmlspecialchars($login['ip_address'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="device-badge">
                                                <?php echo htmlspecialchars($login['device_type'] ?? 'Unknown'); ?>
                                            </span>
                                        </td>
                                        <td style="font-size: 13px;"><?php echo htmlspecialchars($login['browser'] ?? 'Unknown'); ?></td>
                                        <td style="font-size: 13px;"><?php echo htmlspecialchars($login['operating_system'] ?? 'Unknown'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
