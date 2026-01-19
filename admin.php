<?php
/**
 * Admin Panel - Game Management
 */
require_once 'session_config.php';
require_once 'config.php';
require_once 'db_helper.php';
require_once 'redis_helper.php';
require_once 'currency_helper.php';
require_once 'settings_helper.php';
require_once 'rank_helper.php';
require_once 'geo_helper.php';
require_once 'csrf_helper.php';

$cache = RedisCache::getInstance();

// Handle logout - MUST be early before any HTML output
if (isset($_GET['logout']) && isset($_GET['csrf_token'])) {
    if (CSRF::validateToken($_GET['csrf_token'])) {
        session_destroy();
        header('Location: admin.php');
        exit;
    } else {
        // Invalid CSRF token - redirect to prevent attack
        $_SESSION['error'] = 'Session expired or invalid request. Please try again.';
        header('Location: admin.php');
        exit;
    }
}

// Restrict admin access to PH IPs only
// Temporarily disabled for debugging - re-enable once working
// GeoHelper::enforceCountry('PH');

// Compress and resize uploaded images (used for banners)
function compressAndSaveImage($sourcePath, $targetDir, $baseName = 'banner', $maxWidth = 1600, $quality = 82) {
    if (!file_exists($sourcePath)) {
        return false;
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $info = @getimagesize($sourcePath);
    if (!$info || empty($info['mime'])) {
        return false;
    }

    $mime = $info['mime'];
    $createMap = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp'
    ];

    $saveQuality = max(50, min(95, intval($quality)));

    if (!isset($createMap[$mime]) || !function_exists($createMap[$mime])) {
        $ext = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg';
        $destPath = rtrim($targetDir, '/') . '/' . $baseName . '.' . $ext;
        if (@copy($sourcePath, $destPath)) {
            return $destPath;
        }
        return false;
    }

    $srcImage = @$createMap[$mime]($sourcePath);
    if (!$srcImage) {
        return false;
    }

    $width = $info[0];
    $height = $info[1];
    $newWidth = $width;
    $newHeight = $height;

    if ($width > $maxWidth) {
        $ratio = $maxWidth / $width;
        $newWidth = $maxWidth;
        $newHeight = max(1, (int)floor($height * $ratio));
    }

    $dstImage = imagecreatetruecolor($newWidth, $newHeight);

    if (in_array($mime, ['image/png', 'image/webp'])) {
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
    }

    imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $destBase = rtrim($targetDir, '/') . '/' . $baseName;
    $destPath = '';
    $saved = false;

    if (function_exists('imagewebp')) {
        $destPath = $destBase . '.webp';
        $saved = imagewebp($dstImage, $destPath, $saveQuality);
    }

    if (!$saved) {
        $destPath = $destBase . '.jpg';
        $saved = imagejpeg($dstImage, $destPath, $saveQuality);
    }

    imagedestroy($srcImage);
    imagedestroy($dstImage);

    return $saved ? $destPath : false;
}


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
        // If Google Authenticator (MFA) is enabled, require second step
        $mfaEnabled = isset($admin['mfa_enabled']) ? (int)$admin['mfa_enabled'] === 1 : false;
        $hasSecret = !empty($admin['mfa_secret'] ?? '');
        if ($mfaEnabled && $hasSecret) {
            // Stage pending admin until MFA verification
            $_SESSION['pending_admin_id'] = $admin['id'];
            $_SESSION['pending_admin_username'] = $admin['username'];
            // Do not mark logged in yet
            header("Location: admin_mfa_verify.php");
            exit;
        } else {
            // Standard login
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            
            // Update last login
            $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
            
            // Redirect to prevent resubmission
            header("Location: admin.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "Invalid username or password";
        header("Location: admin.php");
        exit;
    }
}

// Handle cache clear
if (isset($_GET['clear_cache'])) {
    $cache = RedisCache::getInstance();
    $cache->deletePattern('games:*');
    $cache->deletePattern('admin:games:*');
    $_SESSION['success'] = "All game caches cleared successfully!";
    header("Location: admin.php");
    exit;
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                min-height: 100vh; 
                display: flex; 
                align-items: center; 
                justify-content: center;
                padding: 15px;
            }
            
            .login-box { 
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                padding: 40px; 
                border-radius: 20px; 
                box-shadow: 0 20px 60px rgba(0,0,0,0.3); 
                width: 100%; 
                max-width: 440px;
            }
            
            .logo {
                text-align: center;
                margin-bottom: 30px;
            }
            
            .logo a {
                text-decoration: none;
                color: inherit;
                display: inline-block;
                transition: transform 0.3s;
            }
            
            .logo a:hover {
                transform: scale(1.05);
            }
            
            h1 { 
                text-align: center; 
                color: #667eea; 
                margin-bottom: 10px;
                font-size: 32px;
            }
            
            .subtitle {
                text-align: center;
                color: #666;
                font-size: 14px;
                margin-bottom: 30px;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            label {
                display: block;
                margin-bottom: 8px;
                color: #333;
                font-weight: 600;
                font-size: 14px;
            }
            
            input { 
                width: 100%; 
                padding: 14px 16px; 
                border: 2px solid #e5e7eb; 
                border-radius: 10px; 
                font-size: 15px;
                transition: all 0.3s;
                background: white;
            }
            
            input:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            
            button { 
                width: 100%; 
                padding: 15px; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                color: white; 
                border: none; 
                border-radius: 10px; 
                font-size: 16px; 
                font-weight: 600; 
                cursor: pointer;
                transition: all 0.3s;
                margin-top: 8px;
            }
            
            button:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
            }
            
            button:active {
                transform: translateY(0);
            }
            
            .error { 
                color: #dc2626;
                background: #fee2e2;
                border-left: 4px solid #dc2626;
                padding: 12px 16px;
                border-radius: 10px;
                text-align: center; 
                margin-bottom: 20px;
                font-size: 14px;
                line-height: 1.5;
            }
            
            .back-link {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                color: #667eea;
                text-decoration: none;
                font-size: 14px;
                font-weight: 600;
                margin-bottom: 20px;
                padding: 8px 12px;
                border-radius: 8px;
                transition: all 0.3s;
            }
            
            .back-link:hover {
                background: rgba(102, 126, 234, 0.1);
                transform: translateX(-3px);
            }
            
            /* Responsive Design */
            @media (max-width: 768px) {
                body {
                    padding: 10px;
                }
                
                .login-box {
                    padding: 30px 25px;
                    border-radius: 16px;
                }
                
                h1 {
                    font-size: 28px;
                }
            }
            
            @media (max-width: 480px) {
                body {
                    padding: 8px;
                    align-items: flex-start;
                    padding-top: 20px;
                }
                
                .login-box {
                    padding: 25px 20px;
                    border-radius: 16px;
                    max-width: 100%;
                }
                
                h1 {
                    font-size: 24px;
                    margin-bottom: 8px;
                }
                
                .subtitle {
                    font-size: 13px;
                    margin-bottom: 25px;
                }
                
                .back-link {
                    font-size: 13px;
                    padding: 6px 10px;
                }
                
                .form-group {
                    margin-bottom: 16px;
                }
                
                label {
                    font-size: 13px;
                }
                
                input {
                    padding: 12px 14px;
                    font-size: 16px; /* Prevents iOS zoom */
                }
                
                button {
                    padding: 14px;
                    font-size: 15px;
                }
                
                .error {
                    padding: 10px 14px;
                    font-size: 13px;
                }
            }
            
            @media (max-width: 360px) {
                .login-box {
                    padding: 20px 16px;
                }
                
                h1 {
                    font-size: 22px;
                }
                
                input, button {
                    font-size: 15px;
                }
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <a href="index.php" class="back-link">← Back to Games</a>
            <div class="logo">
                <a href="index.php">
                    <h1>🔐 Admin Login</h1>
                    <p class="subtitle">Secure Admin Access</p>
                </a>
            </div>
            <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Enter admin username" required autofocus>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter password" required>
                </div>
                <button type="submit" name="admin_login">Login to Admin Panel</button>
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
            
            // Invalidate game caches
            $cache->deletePattern('games:*');
            
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
    
    // Invalidate ALL game caches (both frontend and admin)
    $cache->deletePattern('games:*');
    $cache->deletePattern('admin:games:*');
    $cache->deletePattern('user:*:recent_plays');
    
    $_SESSION['success'] = "Game updated successfully! Cache cleared.";
    header("Location: admin.php");
    exit;
}

// Handle admin password change
if (isset($_POST['change_admin_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate new password
    if ($newPassword !== $confirmPassword) {
        $_SESSION['error'] = "New passwords do not match!";
    } elseif (strlen($newPassword) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long!";
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM admin_users WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($currentPassword, $admin['password'])) {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $_SESSION['admin_id']]);
            
            $_SESSION['success'] = "Password changed successfully!";
        } else {
            $_SESSION['error'] = "Current password is incorrect!";
        }
    }
    
    header("Location: admin.php");
    exit;
}

// Handle game delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM games WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    
    // Invalidate ALL game caches (both frontend and admin)
    $cache->deletePattern('games:*');
    $cache->deletePattern('admin:games:*');
    $cache->deletePattern('user:*:recent_plays');
    
    $_SESSION['success'] = "Game deleted successfully! Cache cleared.";
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
            
            // Invalidate ALL game caches (both frontend and admin)
            $cache->deletePattern('games:*');
            $cache->deletePattern('admin:games:*');
            $cache->deletePattern('user:*:recent_plays');
            
            $_SESSION['success'] = "Game added successfully! Cache cleared.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error adding game: " . $e->getMessage();
    }
    header("Location: admin.php");
    exit;
}

// Handle user balance update
if (isset($_POST['update_user_balance'])) {
    $userId = $_POST['user_id'];
    $newBalance = $_POST['new_balance'];
    $currency = $_POST['currency'];
    
    $stmt = $pdo->prepare("UPDATE users SET balance = ?, currency = ? WHERE id = ?");
    $stmt->execute([$newBalance, $currency, $userId]);
    
    // Write-through caching: immediately update cache with new balance
    $cache = RedisCache::getInstance();
    $cache->refreshBalance($userId, $newBalance);
    
    // Invalidate related caches
    $cache->delete("user:data:{$userId}");
    
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
    
    // Write-through caching: immediately update cache with new balance
    $cache = RedisCache::getInstance();
    $cache->refreshBalance($userId, $balance);
    
    // Invalidate all user caches to refresh with new data
    $cache->invalidateUserCache($userId);
    
    $_SESSION['success'] = "User information updated successfully!";
    header("Location: admin.php");
    exit;
}

// Handle transaction approval
if (isset($_POST['approve_transaction'])) {
    $transId = $_POST['transaction_id'];
    
    // Get transaction details
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending'");
    $stmt->execute([$transId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($transaction) {
        $userId = $transaction['user_id'];
        $amount = $transaction['amount'];
        $type = $transaction['type'];
        
        try {
            $pdo->beginTransaction();
            
            // Handle receipt upload for withdrawals
            $receiptPath = null;
            if ($type === 'withdrawal' && isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/receipts/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileExtension = strtolower(pathinfo($_FILES['receipt_image']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
                
                if (in_array($fileExtension, $allowedExtensions)) {
                    $fileName = 'receipt_' . $transId . '_' . time() . '.' . $fileExtension;
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $targetPath)) {
                        $receiptPath = $targetPath;
                    }
                }
            }
            
            // Get current balance
            $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $currentBalance = $stmt->fetchColumn();
            
            // Update balance based on transaction type
            if ($type === 'deposit') {
                $newBalance = $currentBalance + $amount;
            } elseif ($type === 'withdrawal') {
                $newBalance = $currentBalance - $amount;
                
                // Check if user has sufficient balance
                if ($newBalance < 0) {
                    throw new Exception('Insufficient balance for withdrawal');
                }
            } else {
                throw new Exception('Invalid transaction type');
            }
            
            // Update user balance
            $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $stmt->execute([$newBalance, $userId]);
            
            // Update transaction status, balance fields, and receipt
            $stmt = $pdo->prepare("
                UPDATE transactions 
                SET status = 'completed',
                    balance_before = ?,
                    balance_after = ?,
                    receipt_image = ?
                WHERE id = ?
            ");
            $stmt->execute([$currentBalance, $newBalance, $receiptPath, $transId]);
            
            // Commit transaction
            $pdo->commit();
            
            // Write-through cache update
            $cache = RedisCache::getInstance();
            $cache->refreshBalance($userId, $newBalance);
            $cache->invalidateUserCache($userId);
            
            $_SESSION['success'] = ucfirst($type) . " approved! User balance updated to " . formatCurrency($newBalance);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error approving transaction: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Transaction not found or already processed";
    }
    
    header("Location: admin.php");
    exit;
}

// Handle transaction rejection
if (isset($_POST['reject_transaction'])) {
    $transId = $_POST['transaction_id'];
    $reason = $_POST['rejection_reason'] ?? 'Rejected by admin';
    
    // Get transaction details
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending'");
    $stmt->execute([$transId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($transaction) {
        // Update transaction status
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET status = 'failed',
                description = CONCAT(description, ' - Rejected: ', ?)
            WHERE id = ?
        ");
        $stmt->execute([$reason, $transId]);
        
        $_SESSION['success'] = "Transaction rejected successfully";
    } else {
        $_SESSION['error'] = "Transaction not found or already processed";
    }
    
    header("Location: admin.php");
    exit;
}

// Handle bonus program creation
if (isset($_POST['create_bonus'])) {
    $name = $_POST['bonus_name'];
    $type = $_POST['bonus_type'];
    $amount = floatval($_POST['bonus_amount']);
    $description = $_POST['bonus_description'] ?? '';
    $triggerValue = $type === 'deposit' ? floatval($_POST['trigger_value']) : null;
    $maxClaims = intval($_POST['max_claims'] ?? 1);
    
    $stmt = $pdo->prepare("
        INSERT INTO bonus_programs (name, type, amount, description, trigger_value, max_claims_per_user, is_enabled)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([$name, $type, $amount, $description, $triggerValue, $maxClaims]);
    
    $_SESSION['success'] = "Bonus program created successfully!";
    header("Location: admin.php");
    exit;
}

// Handle bonus program update
if (isset($_POST['update_bonus'])) {
    $bonusId = $_POST['bonus_id'];
    $name = $_POST['bonus_name'];
    $amount = floatval($_POST['bonus_amount']);
    $description = $_POST['bonus_description'] ?? '';
    $triggerValue = $_POST['bonus_type'] === 'deposit' ? floatval($_POST['trigger_value']) : null;
    $maxClaims = intval($_POST['max_claims'] ?? 1);
    $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
    
    $stmt = $pdo->prepare("
        UPDATE bonus_programs 
        SET name = ?, amount = ?, description = ?, trigger_value = ?, max_claims_per_user = ?, is_enabled = ?
        WHERE id = ?
    ");
    $stmt->execute([$name, $amount, $description, $triggerValue, $maxClaims, $isEnabled, $bonusId]);
    
    $_SESSION['success'] = "Bonus program updated successfully!";
    header("Location: admin.php");
    exit;
}

// Handle bonus program deletion
if (isset($_POST['delete_bonus'])) {
    $bonusId = $_POST['bonus_id'];
    $stmt = $pdo->prepare("DELETE FROM bonus_programs WHERE id = ?");
    $stmt->execute([$bonusId]);
    
    $_SESSION['success'] = "Bonus program deleted successfully!";
    header("Location: admin.php");
    exit;
}

// Handle banners: add new
if (isset($_POST['add_banner'])) {
    $bannersJson = SiteSettings::get('banners', '[]');
    $banners = json_decode($bannersJson, true);
    if (!is_array($banners)) { $banners = []; }

    $title = trim($_POST['banner_title'] ?? '');
    $subtitle = trim($_POST['banner_subtitle'] ?? '');
    $buttonText = trim($_POST['banner_button_text'] ?? 'Play Now');
    $link = trim($_POST['banner_link'] ?? '');
    $enabled = isset($_POST['banner_enabled']) ? 1 : 0;
    $audience = in_array(($_POST['banner_audience'] ?? 'all'), ['all','logged_in','guest']) ? $_POST['banner_audience'] : 'all';

    $imagePath = '';
    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === 0) {
        $bannerDir = 'uploads/banners/';
        $fileBase = 'banner_' . time() . '_' . mt_rand(1000,9999);
        $compressedPath = compressAndSaveImage($_FILES['banner_image']['tmp_name'], $bannerDir, $fileBase, 1600, 82);
        if ($compressedPath) { $imagePath = $compressedPath; }
    }

    $banners[] = [
        'id' => (string)(time() . mt_rand(100,999)),
        'title' => $title,
        'subtitle' => $subtitle,
        'button_text' => $buttonText,
        'link' => $link,
        'enabled' => $enabled,
        'audience' => $audience,
        'image' => $imagePath,
        'created_at' => date('c')
    ];

    SiteSettings::set('banners', json_encode($banners));
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'banners' => $banners]);
        exit;
    }
    $_SESSION['success'] = "Banner added successfully!";
    header('Location: admin.php#tool-tab');
    exit;
}

// Handle banner update
if (isset($_POST['update_banner'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $index = intval($_POST['banner_index'] ?? -1);
    $bannersJson = SiteSettings::get('banners', '[]');
    $banners = json_decode($bannersJson, true);
    if (!is_array($banners)) { $banners = []; }

    if ($index >= 0 && $index < count($banners)) {
        $banners[$index]['title'] = trim($_POST['banner_title'] ?? '');
        $banners[$index]['subtitle'] = trim($_POST['banner_subtitle'] ?? '');
        $banners[$index]['button_text'] = trim($_POST['banner_button_text'] ?? 'Play Now');
        $banners[$index]['link'] = trim($_POST['banner_link'] ?? '');
        $banners[$index]['enabled'] = isset($_POST['banner_enabled']) ? 1 : 0;
        $banners[$index]['audience'] = in_array(($_POST['banner_audience'] ?? 'all'), ['all','logged_in','guest']) ? $_POST['banner_audience'] : 'all';

        if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === 0) {
            $bannerDir = 'uploads/banners/';
            $fileBase = 'banner_' . time() . '_' . mt_rand(1000,9999);
            $compressedPath = compressAndSaveImage($_FILES['banner_image']['tmp_name'], $bannerDir, $fileBase, 1600, 82);
            if ($compressedPath) { $banners[$index]['image'] = $compressedPath; }
        }

        SiteSettings::set('banners', json_encode($banners));
        if ($isAjax) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'banners' => $banners]);
            exit;
        }
        $_SESSION['success'] = "Banner updated successfully!";
    } else {
        if ($isAjax) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Banner index ' . $index . ' out of range (' . count($banners) . ' banners)']);
            exit;
        }
        $_SESSION['error'] = "Banner not found";
    }
    header('Location: admin.php#tool-tab');
    exit;
}

// Handle banner delete
if (isset($_POST['delete_banner'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $index = intval($_POST['banner_index'] ?? -1);
    $bannersJson = SiteSettings::get('banners', '[]');
    $banners = json_decode($bannersJson, true);
    if (!is_array($banners)) { $banners = []; }

    if ($index >= 0 && $index < count($banners)) {
        array_splice($banners, $index, 1);
        SiteSettings::set('banners', json_encode($banners));
        if ($isAjax) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'banners' => $banners]);
            exit;
        }
        $_SESSION['success'] = "Banner deleted";
    } else {
        if ($isAjax) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Banner index ' . $index . ' out of range (' . count($banners) . ' banners)']);
            exit;
        }
        $_SESSION['error'] = "Banner not found";
    }
    header('Location: admin.php#tool-tab');
    exit;
}

// Handle settings update
if (isset($_POST['update_settings'])) {
    $settingsToUpdate = [
        'casino_name', 'casino_tagline', 'default_currency', 'logo_path',
        'theme_color', 'min_bet', 'max_bet',
        'support_email', 'support_phone', 'facebook_url', 'twitter_url', 'instagram_url',
        'header_scripts', 'footer_scripts',
        'banner_title', 'banner_subtitle', 'banner_link', 'banner_button_text',
        'gcash_number', 'gcash_name', 'maya_number', 'maya_name', 
        'crypto_btc_address', 'crypto_eth_address', 'crypto_usdt_address'
    ];
    
    foreach ($settingsToUpdate as $key) {
        if (isset($_POST[$key])) {
            SiteSettings::set($key, $_POST[$key]);
        }
    }

    // Payment methods toggle
    $allowedMethodsInput = $_POST['allowed_methods'] ?? [];
    $validMethods = ['bank', 'gcash', 'paymaya', 'crypto'];
    $filteredMethods = array_values(array_intersect($validMethods, $allowedMethodsInput));
    if (empty($filteredMethods)) {
        $filteredMethods = $validMethods; // default to all enabled if none selected
    }
    SiteSettings::set('allowed_methods', json_encode($filteredMethods));
    
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

    // Note: Multiple banners are managed via dedicated forms above
    
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

// Get pending wallet transactions
$pendingTransactions = $pdo->query("
    SELECT t.*, u.username, u.phone, u.balance as current_balance, u.currency
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.status = 'pending' AND t.type IN ('deposit', 'withdrawal')
    ORDER BY t.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

$pendingCount = count($pendingTransactions);

// Recent approved/failed wallet transactions (latest 50 each)
$recentApproved = $pdo->query("
    SELECT t.*, u.username, u.phone, u.currency
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.type IN ('deposit', 'withdrawal') AND t.status = 'completed'
    ORDER BY t.created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$recentFailed = $pdo->query("
    SELECT t.*, u.username, u.phone, u.currency
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.type IN ('deposit', 'withdrawal') AND t.status = 'failed'
    ORDER BY t.created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Lightweight JSON endpoint for wallet auto-refresh
if (isset($_GET['wallet_json'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'pendingCount' => $pendingCount,
        'pendingTransactions' => $pendingTransactions,
        'recentApproved' => $recentApproved,
        'recentFailed' => $recentFailed,
    ]);
    exit;
}

// Ensure bonus tables exist
$pdo->exec("CREATE TABLE IF NOT EXISTS bonus_programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('registration', 'deposit', 'custom') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    description TEXT,
    trigger_value DECIMAL(15,2) NULL,
    is_enabled TINYINT(1) DEFAULT 1,
    max_claims_per_user INT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS bonus_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bonus_program_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    claimed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    balance_before DECIMAL(15,2) NOT NULL,
    balance_after DECIMAL(15,2) NOT NULL,
    INDEX idx_user (user_id),
    INDEX idx_bonus (bonus_program_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Get bonus programs
$bonusPrograms = $pdo->query("
    SELECT bp.*, COUNT(bc.id) as total_claims, COALESCE(SUM(bc.amount), 0) as total_claimed_amount
    FROM bonus_programs bp
    LEFT JOIN bonus_claims bc ON bp.id = bc.bonus_program_id
    GROUP BY bp.id
    ORDER BY bp.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent bonus claims
$recentBonusClaims = $pdo->query("
    SELECT bc.*, bp.name as bonus_name, u.username, u.currency
    FROM bonus_claims bc
    JOIN bonus_programs bp ON bc.bonus_program_id = bp.id
    JOIN users u ON bc.user_id = u.id
    ORDER BY bc.claimed_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$totalBonusClaims = $pdo->query("SELECT COUNT(*) FROM bonus_claims")->fetchColumn();
$totalBonusAmount = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM bonus_claims")->fetchColumn();

// Handle AJAX request for loading more games
if (isset($_GET['load_games'])) {
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $limit = 20;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Build query based on search
    if (!empty($search)) {
        // Search query - check cache
        $cacheKey = "admin:games:search:" . md5($search . $offset . $limit);
        $cachedGames = $cache->get($cacheKey);
        
        if ($cachedGames !== false) {
            header('Content-Type: application/json');
            echo json_encode($cachedGames);
            exit;
        }
        
        // Search by game_uid or name
        $stmt = $pdo->prepare("
            SELECT * FROM games 
            WHERE game_uid LIKE ? OR name LIKE ?
            ORDER BY 
                CASE WHEN game_uid = ? THEN 0 ELSE 1 END,
                CASE WHEN name LIKE ? THEN 0 ELSE 1 END,
                name ASC
            LIMIT ? OFFSET ?
        ");
        $searchParam = '%' . $search . '%';
        $stmt->execute([$searchParam, $searchParam, $search, $search . '%', $limit, $offset]);
        $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cache search results for 2 minutes
        $cache->set($cacheKey, $games, 120);
    } else {
        // Regular load - try cache first
        $cacheKey = "admin:games:list:{$offset}:{$limit}";
        $cachedGames = $cache->get($cacheKey);
        
        if ($cachedGames !== false) {
            header('Content-Type: application/json');
            echo json_encode($cachedGames);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM games ORDER BY sort_order ASC, name ASC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cache for 5 minutes (admins need fresher data)
        $cache->set($cacheKey, $games, RedisCache::CACHE_5_MINUTES);
    }
    
    header('Content-Type: application/json');
    echo json_encode($games);
    exit;
}

// Initial load - get first 20 games
$gamesPerLoad = 20;
$totalGamesCount = $pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();

// Try cache first
$cacheKey = "admin:games:initial:{$gamesPerLoad}";
$games = $cache->remember($cacheKey, function() use ($pdo, $gamesPerLoad) {
    $stmt = $pdo->prepare("SELECT * FROM games ORDER BY sort_order ASC, name ASC LIMIT ?");
    $stmt->bindValue(1, $gamesPerLoad, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}, RedisCache::CACHE_5_MINUTES);

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
    <!-- AdminLTE + Bootstrap (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4/dist/css/adminlte.min.css">
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
            position: sticky;
            top: 0;
            z-index: 999;
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
        /* Toasts */
        .toast-container {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .toast {
            background: #1f2937;
            color: #e5e7eb;
            border: 1px solid #374151;
            padding: 10px 14px;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
            opacity: 0;
            transform: translateY(-6px);
            transition: all 0.25s ease;
        }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { border-color: #10b981; }
        .toast.error { border-color: #ef4444; }
        .payment-methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
        }
        .payment-method-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border-radius: 8px;
            background: #0f1626;
            border: 1px solid #2d3548;
            color: #e5e7eb;
            transition: border-color 0.15s ease, background 0.15s ease;
        }
        .payment-method-card:hover, .payment-method-card:focus-within {
            border-color: #f97316;
        }
        .payment-method-card input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #f97316;
        }
        .payment-method-card span { font-weight: 600; }
        .payment-method-card input[type="checkbox"]:checked + span {
            color: #fefefe;
        }
        /* Payment details (compact) */
        .payment-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 12px;
            margin-top: 8px;
        }
        .payment-details-card {
            background: #0f1626;
            border: 1px solid #2d3548;
            border-radius: 8px;
            padding: 12px;
        }
        .payment-details-card h4 {
            margin: 0 0 8px 0;
            font-size: 16px;
        }
        .payment-details-card .form-group { margin-bottom: 10px; }
        .payment-details-card .form-group label { font-size: 13px; margin-bottom: 5px; }
        .payment-details-card .form-group input { padding: 8px 10px; font-size: 13px; }
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
        
        /* Game Preview Modal */
        .game-preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .game-preview-modal.active {
            display: flex;
        }
        .game-preview-container {
            background: #1a1f36;
            border-radius: 12px;
            width: 90%;
            max-width: 1200px;
            height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }
        .game-preview-header {
            background: #2d3548;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #374151;
        }
        .game-preview-header h3 {
            margin: 0;
            color: #ffffff;
            font-size: 18px;
        }
        .close-preview {
            background: #ef4444;
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .close-preview:hover {
            background: #dc2626;
        }
        .game-preview-iframe {
            flex: 1;
            border: none;
            width: 100%;
            background: #0f172a;
        }
    </style>
</head>
<body class="hold-transition layout-navbar-fixed">
    <!-- Game Preview Modal -->
    <div class="game-preview-modal" id="gamePreviewModal" onclick="closeGamePreview(event)">
        <div class="game-preview-container" onclick="event.stopPropagation()">
            <div class="game-preview-header">
                <h3 id="previewGameName">Game Preview</h3>
                <button class="close-preview" onclick="closeGamePreview()">✕</button>
            </div>
            <iframe class="game-preview-iframe" id="gamePreviewIframe" src="about:blank"></iframe>
        </div>
    </div>

    <div class="toast-container" id="toast-container"></div>
    <div class="header navbar navbar-expand navbar-dark" style="background:#1a1f36;border-bottom:1px solid #2d3548;">
        <h1 style="margin: 0; flex: 1;">Admin Dashboard</h1>
        <div style="display: flex; align-items: center; gap: 20px;">
            <span style="color: #cbd5e1;">👤 <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            <a href="index.php" style="color: #10b981; text-decoration: none; font-weight: 600;">🎰 View Casino</a>
            <a href="#" onclick="showModal('changePasswordModal'); return false;" style="color: #fbbf24; text-decoration: none; margin-left: 10px;">🔑 Change Password</a>
            <a href="?logout=1&csrf_token=<?php echo urlencode(CSRF::getToken()); ?>" style="color: #ef4444; text-decoration: none; margin-left: 10px;">Logout</a>
        </div>
    </div>

    <div style="display: flex; min-height: calc(100vh - 70px);">
        <!-- Sidebar Navigation -->
        <div class="sidebar" style="width: 250px; background: #0f172a; border-right: 1px solid #2d3548; padding: 20px 0; overflow-y: auto;">
            <nav style="display: flex; flex-direction: column; gap: 8px; padding: 0 12px;">
                <a onclick="switchTab('games', event); event.preventDefault();" class="sidebar-item active" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s;">
                    🎮 Games
                </a>
                <a onclick="switchTab('wallet', event); event.preventDefault();" class="sidebar-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #9ca3bf; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s; position: relative;">
                    💳 Wallet / Transactions
                    <?php if ($pendingCount > 0): ?>
                        <span style="position: absolute; right: 10px; background: #ef4444; color: white; border-radius: 20px; padding: 2px 8px; font-size: 11px; font-weight: bold;"><?php echo $pendingCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="admin_wpay_logs.php" class="sidebar-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #9ca3bf; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s;">
                    � Payment Logs
                </a>
                <a href="wpay_tools.php" class="sidebar-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #9ca3bf; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s;">
                    🛠️ WPay Tools
                </a>
                <a href="wpay_stats.php" class="sidebar-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #9ca3bf; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s;">
                    📊 WPay Stats
                </a>
                <a href="wpay_diagnostic.php" class="sidebar-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #9ca3bf; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s;">
                    🔍 WPay Diagnostic
                </a>
                <a href="export_wpay_logs.php" class="sidebar-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #9ca3bf; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s;">
                    💾 Export Logs
                </a>
                <a href="admin_login_attempts.php" class="sidebar-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #9ca3bf; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s;">
                    🛡️ Login Attempts
                </a>
                <a href="admin_mfa_setup.php" class="sidebar-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #9ca3bf; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s;">
                    🔒 Security (Google Authenticator)
                </a>
                <a onclick="switchTab('users', event); event.preventDefault();" class="sidebar-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #9ca3bf; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s;">
                    👥 User Management
                </a>
                <a onclick="switchTab('bonuses', event); event.preventDefault();" class="sidebar-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #9ca3bf; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s;">
                    🎁 Bonuses
                </a>
                
                <hr style="border: none; border-top: 1px solid #2d3548; margin: 8px 0;">
                
                <div style="padding: 8px 16px; font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">Analytics</div>
                <a onclick="switchTab('history', event); event.preventDefault();" class="sidebar-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #9ca3bf; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s;">
                    📊 Betting History
                </a>
                <a onclick="switchTab('topplayers', event); event.preventDefault();" class="sidebar-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #9ca3bf; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s;">
                    🏆 Top Players
                </a>
                <a onclick="switchTab('mostplayed', event); event.preventDefault();" class="sidebar-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #9ca3bf; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s;">
                    🎯 Most Played Games
                </a>
                <a onclick="switchTab('ranks', event); event.preventDefault();" class="sidebar-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #9ca3bf; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s;">
                    🏅 Player Ranks
                </a>
                
                <hr style="border: none; border-top: 1px solid #2d3548; margin: 8px 0;">
                
                <div style="padding: 8px 16px; font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">Configuration</div>
                <a onclick="switchTab('tool', event); event.preventDefault();" class="sidebar-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #9ca3bf; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s;">
                    🛠️ Tools & Banners
                </a>
                <a onclick="switchTab('settings', event); event.preventDefault();" class="sidebar-item" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #9ca3bf; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s;">
                    ⚙️ Settings
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div style="flex: 1; overflow-y: auto; padding: 20px;">
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

        <!-- Old Horizontal Tabs Navigation (Hidden) -->
        <div class="tabs nav nav-pills mb-3" role="tablist" style="display: none;">
            <button class="tab nav-link active" role="tab" onclick="switchTab('games', this)">🎮 Games</button>
            <button class="tab nav-link" role="tab" onclick="switchTab('wallet', this)" <?php if ($pendingCount > 0): ?>style="position: relative;"<?php endif; ?>>
                💳 Wallet
                <?php if ($pendingCount > 0): ?>
                    <span id="pendingBadge" style="position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; border-radius: 10px; padding: 2px 6px; font-size: 11px; font-weight: bold;"><?php echo $pendingCount; ?></span>
                <?php endif; ?>
            </button>
            <button class="tab nav-link" role="tab" onclick="window.location.href='admin_wpay_logs.php'">💰 Payments</button>
            <button class="tab nav-link" role="tab" onclick="switchTab('bonuses', this)">🎁 Bonuses</button>
            <button class="tab nav-link" role="tab" onclick="switchTab('users', this)">👥 Users</button>
            <button class="tab nav-link" role="tab" onclick="switchTab('history', this)">📊 Betting History</button>
            <button class="tab nav-link" role="tab" onclick="switchTab('topplayers', this)">🏆 Top Players</button>
            <button class="tab nav-link" role="tab" onclick="switchTab('mostplayed', this)">🎯 Most Bets Games</button>
            <button class="tab nav-link" role="tab" onclick="switchTab('ranks', this)">🏅 Ranks</button>
            <button class="tab nav-link" role="tab" onclick="switchTab('tool', this)">🛠️ Tool</button>
            <button class="tab nav-link" role="tab" onclick="switchTab('settings', this)">⚙️ Settings</button>
        </div>

        <!-- Ranks Tab -->
        <div id="ranks-tab" class="tab-content">
            <div class="table-container">
                <h2 style="margin-bottom: 20px;">🏅 Player Ranking System</h2>
                <p style="color: #9ca3af; margin-bottom: 24px;">Players are automatically ranked based on their total wagered amount. Higher ranks unlock exclusive benefits and recognition.</p>
                
                <div style="display: grid; gap: 12px;">
                    <?php 
                    $allRanks = UserRank::getAllRanks();
                    foreach ($allRanks as $rank): 
                    ?>
                        <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: linear-gradient(135deg, <?php echo $rank['color']; ?>, <?php echo UserRank::adjustBrightness($rank['color'], -30); ?>); border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                            <div style="font-size: 32px;"><?php echo $rank['icon']; ?></div>
                            <div style="flex: 1;">
                                <div style="font-size: 18px; font-weight: 600; color: white; margin-bottom: 4px;">
                                    <?php echo htmlspecialchars($rank['name']); ?>
                                </div>
                                <div style="font-size: 14px; color: rgba(255,255,255,0.9);">
                                    <?php echo UserRank::formatRankRange($rank['min'], $rank['max']); ?>
                                </div>
                            </div>
                            <div style="padding: 8px 16px; background: rgba(255,255,255,0.2); border-radius: 6px; color: white; font-weight: 600; font-size: 14px;">
                                Tier <?php echo count($allRanks) - array_search($rank, $allRanks); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="margin-top: 24px; padding: 20px; background: #1a1f36; border-radius: 8px; border: 1px solid #2d3548;">
                    <h3 style="margin: 0 0 12px; font-size: 16px;">💡 How Rankings Work</h3>
                    <ul style="margin: 0; padding-left: 20px; color: #9ca3af; line-height: 1.8;">
                        <li>Rankings are based on <strong>total amount wagered</strong> across all games</li>
                        <li>Ranks are automatically calculated and updated in real-time</li>
                        <li>Players can view their rank and progress on their profile page</li>
                        <li>Higher ranks provide recognition and status within the community</li>
                        <li>Ranks are permanent and cannot decrease</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Tool Tab -->
        <div id="tool-tab" class="tab-content">
            <div class="table-container">
                <h2 style="margin-bottom: 20px;">🛠️ Tools</h2>
                <h3 style="margin-top: 0;">Marketing Banners</h3>
                <?php 
                    $banners = json_decode($siteSettings['banners'] ?? '[]', true);
                    if (!is_array($banners)) { $banners = []; }
                ?>
                <div class="form-group" style="border: 1px solid #2d3548; border-radius: 8px; padding: 12px; background: #0f1626;">
                    <h4 style="margin: 0 0 10px;">Add New Banner</h4>
                    <form method="POST" enctype="multipart/form-data" style="display: grid; gap: 10px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <label>Title</label>
                                <input type="text" name="banner_title" placeholder="Limited Time Promotion">
                            </div>
                            <div>
                                <label>Button Text</label>
                                <input type="text" name="banner_button_text" value="Play Now" placeholder="See Offer">
                            </div>
                            <div>
                                <label>Link (URL)</label>
                                <input type="url" name="banner_link" placeholder="https://your-campaign-url.com">
                            </div>
                            <div>
                                <label>Audience</label>
                                <select name="banner_audience">
                                    <option value="all">All users</option>
                                    <option value="logged_in">Logged-in only</option>
                                    <option value="guest">Guest only</option>
                                </select>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <input type="checkbox" name="banner_enabled" value="1" checked>
                                <span>Enabled</span>
                            </div>
                        </div>
                        <div>
                            <label>Subtitle</label>
                            <textarea name="banner_subtitle" rows="2" placeholder="Describe the promotion or campaign"></textarea>
                        </div>
                        <div>
                            <label>Upload Image</label>
                            <input type="file" name="banner_image" accept="image/*" required>
                            <small style="color:#9ca3af;">Images are compressed to WebP/JPG (max width 1600px) for faster loading.</small>
                        </div>
                        <div>
                            <button type="submit" name="add_banner" class="btn">➕ Add Banner</button>
                        </div>
                    </form>
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <h4 style="margin: 0 0 10px;">Existing Banners</h4>
                    <?php if (empty($banners)): ?>
                        <div style="padding: 12px; border: 1px solid #2d3548; border-radius: 8px; color: #9ca3af;">No banners yet.</div>
                    <?php else: ?>
                        <?php foreach ($banners as $idx => $bn): ?>
                            <form method="POST" enctype="multipart/form-data" style="border: 1px solid #2d3548; border-radius: 8px; padding: 12px; margin-bottom: 12px; background:#0f1626; display: grid; gap: 10px;">
                                <input type="hidden" name="banner_index" value="<?php echo $idx; ?>">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <div>
                                        <label>Title</label>
                                        <input type="text" name="banner_title" value="<?php echo htmlspecialchars($bn['title'] ?? ''); ?>">
                                    </div>
                                    <div>
                                        <label>Button Text</label>
                                        <input type="text" name="banner_button_text" value="<?php echo htmlspecialchars($bn['button_text'] ?? 'Play Now'); ?>">
                                    </div>
                                    <div>
                                        <label>Link (URL)</label>
                                        <input type="url" name="banner_link" value="<?php echo htmlspecialchars($bn['link'] ?? ''); ?>">
                                    </div>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <input type="checkbox" name="banner_enabled" value="1" <?php echo !empty($bn['enabled']) ? 'checked' : ''; ?>>
                                        <span>Enabled</span>
                                    </div>
                                    <div>
                                        <label>Audience</label>
                                        <select name="banner_audience">
                                            <?php $aud = $bn['audience'] ?? 'all'; ?>
                                            <option value="all" <?php echo $aud==='all'?'selected':''; ?>>All users</option>
                                            <option value="logged_in" <?php echo $aud==='logged_in'?'selected':''; ?>>Logged-in only</option>
                                            <option value="guest" <?php echo $aud==='guest'?'selected':''; ?>>Guest only</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label>Subtitle</label>
                                    <textarea name="banner_subtitle" rows="2"><?php echo htmlspecialchars($bn['subtitle'] ?? ''); ?></textarea>
                                </div>
                                <div>
                                    <label>Current Image</label>
                                        <?php if (!empty($bn['image']) && (filter_var($bn['image'], FILTER_VALIDATE_URL) || file_exists($bn['image']))): ?>
                                            <img src="<?php echo htmlspecialchars($bn['image']); ?>" style="width: 220px; height: 140px; object-fit: cover; border-radius: 10px; border: 1px solid #2d3548; background: #0f1626;">
                                    <?php else: ?>
                                        <div style="color:#9ca3af;">No image</div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <label>Replace Image</label>
                                    <input type="file" name="banner_image" accept="image/*">
                                </div>
                                <div style="display:flex; gap:8px;">
                                    <button type="submit" name="update_banner" class="btn">💾 Save</button>
                                    <button type="submit" name="delete_banner" class="btn" style="background:#ef4444;">🗑️ Delete</button>
                                </div>
                            </form>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Games Tab -->
        <div id="games-tab" class="tab-content active">
            <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; gap: 10px;">
                    <button class="btn" onclick="showModal('addGameModal')">➕ Add New Game</button>
                    <button class="btn" onclick="if(confirm('Clear all game caches? This will refresh game data.')) window.location.href='admin.php?clear_cache=1'" style="background: #f59e0b;">🔄 Clear Cache</button>
                </div>
                <span style="color: #9ca3af;" id="games-counter">Showing <span id="loaded-count"><?php echo count($games); ?></span> of <?php echo $totalGamesCount; ?> games</span>
            </div>
            
            <!-- Search Box -->
            <div style="margin-bottom: 20px; position: relative; max-width: 500px;">
                <input 
                    type="text" 
                    id="admin-game-search" 
                    placeholder="🔍 Search by Game ID or Name..." 
                    style="width: 100%; padding: 12px 45px 12px 15px; border: 2px solid #374151; border-radius: 10px; background: #1f2937; color: white; font-size: 15px;"
                />
                <button 
                    id="clear-admin-search" 
                    onclick="clearAdminSearch()" 
                    style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #9ca3af; font-size: 20px; cursor: pointer; display: none; padding: 5px 10px;"
                >✕</button>
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
                                <button class="btn btn-small" 
                                        onclick="previewGame('<?php echo htmlspecialchars($game['game_uid'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($game['name'], ENT_QUOTES); ?>')"
                                        style="background: #10b981;">🎮 Test</button>
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
        let adminSearchQuery = '';
        let searchTimeout = null;
        
        // Setup admin search
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('admin-game-search');
            const clearBtn = document.getElementById('clear-admin-search');
            
            if (searchInput && clearBtn) {
                searchInput.addEventListener('input', function(e) {
                    const query = e.target.value.trim();
                    
                    // Show/hide clear button
                    if (query) {
                        clearBtn.style.display = 'block';
                    } else {
                        clearBtn.style.display = 'none';
                    }
                    
                    // Debounce search
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        adminSearchQuery = query;
                        performAdminSearch();
                    }, 300);
                });
                
                // Clear on Escape
                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        clearAdminSearch();
                    }
                });
            }
        });
        
        function performAdminSearch() {
            if (!adminSearchQuery) {
                reloadAllGames();
                return;
            }
            
            isLoading = true;
            document.getElementById('loading-indicator').classList.add('active');
            document.getElementById('games-counter').innerHTML = 'Searching...';
            
            fetch('?load_games=1&search=' + encodeURIComponent(adminSearchQuery) + '&offset=0')
                .then(response => response.json())
                .then(games => {
                    const gameGrid = document.getElementById('game-grid');
                    gameGrid.innerHTML = '';
                    
                    if (games.length > 0) {
                        games.forEach(game => {
                            const gameCard = createGameCard(game);
                            gameGrid.insertAdjacentHTML('beforeend', gameCard);
                        });
                        document.getElementById('games-counter').innerHTML = 
                            '<span id=\"loaded-count\">' + games.length + '</span> game(s) found';
                    } else {
                        gameGrid.innerHTML = '<div style=\"grid-column: 1/-1; text-align: center; padding: 40px; color: #9ca3af;\">No games found</div>';
                        document.getElementById('games-counter').innerHTML = '0 games found';
                    }
                    
                    gamesOffset = games.length;
                    isLoading = false;
                    document.getElementById('loading-indicator').classList.remove('active');
                })
                .catch(error => {
                    console.error('Search error:', error);
                    isLoading = false;
                    document.getElementById('loading-indicator').classList.remove('active');
                });
        }
        
        function clearAdminSearch() {
            adminSearchQuery = '';
            const searchInput = document.getElementById('admin-game-search');
            const clearBtn = document.getElementById('clear-admin-search');
            
            if (searchInput) searchInput.value = '';
            if (clearBtn) clearBtn.style.display = 'none';
            
            reloadAllGames();
        }
        
        function reloadAllGames() {
            isLoading = true;
            document.getElementById('loading-indicator').classList.add('active');
            
            fetch('?load_games=1&offset=0')
                .then(response => response.json())
                .then(games => {
                    const gameGrid = document.getElementById('game-grid');
                    gameGrid.innerHTML = '';
                    
                    games.forEach(game => {
                        const gameCard = createGameCard(game);
                        gameGrid.insertAdjacentHTML('beforeend', gameCard);
                    });
                    
                    gamesOffset = games.length;
                    document.getElementById('loaded-count').textContent = gamesOffset;
                    document.getElementById('games-counter').innerHTML = 
                        'Showing <span id=\"loaded-count\">' + gamesOffset + '</span> of ' + totalGames + ' games';
                    
                    isLoading = false;
                    document.getElementById('loading-indicator').classList.remove('active');
                })
                .catch(error => {
                    console.error('Error reloading games:', error);
                    isLoading = false;
                    document.getElementById('loading-indicator').classList.remove('active');
                });
        }
        
        // Infinite scroll for games
        window.addEventListener('scroll', function() {
            if (document.getElementById('games-tab').classList.contains('active') && !isLoading && !adminSearchQuery && gamesOffset < totalGames) {
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
                            <button class="btn btn-small" 
                                    onclick="previewGame('${escapeHtml(game.game_uid)}', '${escapeHtml(game.name)}')"
                                    style="background: #10b981;">🎮 Test</button>
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
        
        // Game Preview Functions
        function previewGame(gameId, gameName) {
            const modal = document.getElementById('gamePreviewModal');
            const iframe = document.getElementById('gamePreviewIframe');
            const nameEl = document.getElementById('previewGameName');
            
            nameEl.textContent = '🎮 ' + gameName;
            iframe.src = 'play_game.php?game_id=' + encodeURIComponent(gameId) + '&game_name=' + encodeURIComponent(gameName);
            modal.classList.add('active');
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        }
        
        function closeGamePreview(event) {
            // Only close if clicking the background or close button
            if (!event || event.target.id === 'gamePreviewModal' || event.currentTarget === event.target) {
                const modal = document.getElementById('gamePreviewModal');
                const iframe = document.getElementById('gamePreviewIframe');
                
                modal.classList.remove('active');
                iframe.src = 'about:blank'; // Stop the game
                
                // Restore body scroll
                document.body.style.overflow = '';
            }
        }
        
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('gamePreviewModal');
                if (modal.classList.contains('active')) {
                    closeGamePreview();
                }
            }
        });
        </script>

        <!-- Wallet Transactions Tab -->
        <div id="wallet-tab" class="tab-content">
            <div class="table-container">
                <h2 style="margin-bottom: 20px;">💳 Pending Wallet Transactions</h2>
                
                <?php if (empty($pendingTransactions)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: #64748b;">
                        <div style="font-size: 64px; margin-bottom: 20px;">✅</div>
                        <h3 style="margin-bottom: 10px;">No Pending Transactions</h3>
                        <p>All deposit and withdrawal requests have been processed.</p>
                    </div>
                <?php else: ?>
                    <div style="background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
                        ⚠️ <?php echo $pendingCount; ?> transaction(s) awaiting your approval
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>User</th>
                                <th>Phone</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Current Balance</th>
                                <th>New Balance</th>
                                <th>Description</th>
                                <th>Proof</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pendingTransactionsBody">
                            <?php foreach ($pendingTransactions as $trans): 
                                $currency = $trans['currency'] ?? 'PHP';
                                $isDeposit = $trans['type'] === 'deposit';
                                $newBalance = $isDeposit 
                                    ? $trans['current_balance'] + $trans['amount']
                                    : $trans['current_balance'] - $trans['amount'];
                                $hasEnoughBalance = $newBalance >= 0;
                            ?>
                                <tr style="<?php echo $hasEnoughBalance ? '' : 'background: #fee2e2;'; ?>">
                                    <td><?php echo date('M d, Y H:i', strtotime($trans['created_at'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($trans['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($trans['phone']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $isDeposit ? 'badge-success' : 'badge-warning'; ?>">
                                            <?php echo $isDeposit ? '📥 Deposit' : '📤 Withdrawal'; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo formatCurrency($trans['amount'], $currency); ?></strong></td>
                                    <td><?php echo formatCurrency($trans['current_balance'], $currency); ?></td>
                                    <td>
                                        <strong style="color: <?php echo $hasEnoughBalance ? ($isDeposit ? '#10b981' : '#f59e0b') : '#ef4444'; ?>">
                                            <?php echo formatCurrency($newBalance, $currency); ?>
                                        </strong>
                                        <?php if (!$hasEnoughBalance): ?>
                                            <br><small style="color: #ef4444;">⚠️ Insufficient Balance</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($trans['description']); ?></td>
                                    <td>
                                        <?php if (!empty($trans['receipt_image']) && file_exists($trans['receipt_image'])): ?>
                                            <a href="<?php echo htmlspecialchars($trans['receipt_image']); ?>" target="_blank" class="btn btn-small" style="background: #3b82f6; padding: 4px 8px; font-size: 12px;">
                                                📷 View Proof
                                            </a>
                                        <?php else: ?>
                                            <small style="color: #9ca3af;">No proof</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                            <?php if ($trans['type'] === 'withdrawal'): ?>
                                                <!-- Withdrawal needs receipt -->
                                                <button class="btn btn-small" style="background: #10b981;" onclick="showApproveWithReceiptModal(<?php echo $trans['id']; ?>, '<?php echo htmlspecialchars($trans['username'], ENT_QUOTES); ?>', <?php echo $trans['amount']; ?>, '<?php echo $currency; ?>', <?php echo $hasEnoughBalance ? 'true' : 'false'; ?>)" <?php echo !$hasEnoughBalance ? 'disabled' : ''; ?>>
                                                    ✅ Approve
                                                </button>
                                            <?php else: ?>
                                                <!-- Deposit doesn't need receipt -->
                                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Approve this deposit?');">
                                                    <input type="hidden" name="transaction_id" value="<?php echo $trans['id']; ?>">
                                                    <button type="submit" name="approve_transaction" class="btn btn-small" style="background: #10b981;">
                                                        ✅ Approve
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <button class="btn btn-small btn-danger" onclick="showRejectModal(<?php echo $trans['id']; ?>, '<?php echo htmlspecialchars($trans['username'], ENT_QUOTES); ?>', '<?php echo $trans['type']; ?>')">
                                                ❌ Reject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <h3 style="margin: 30px 0 15px;">Recent Approved</h3>
                <?php if (empty($recentApproved)): ?>
                    <div style="padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; color: #64748b;">No approved transactions yet.</div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody id="recentApprovedBody">
                        <?php foreach ($recentApproved as $tx): $currency = $tx['currency'] ?? 'PHP'; ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($tx['created_at'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($tx['username']); ?></strong><br><small><?php echo htmlspecialchars($tx['phone']); ?></small></td>
                                <td>
                                    <span class="badge <?php echo $tx['type'] === 'deposit' ? 'badge-success' : 'badge-warning'; ?>">
                                        <?php echo $tx['type'] === 'deposit' ? '📥 Deposit' : '📤 Withdrawal'; ?>
                                    </span>
                                </td>
                                <td><strong><?php echo formatCurrency($tx['amount'], $currency); ?></strong></td>
                                <td><?php echo htmlspecialchars($tx['description']); ?></td>
                                <td>
                                    <?php if (!empty($tx['receipt_image']) && file_exists($tx['receipt_image'])): ?>
                                        <a href="<?php echo htmlspecialchars($tx['receipt_image']); ?>" target="_blank" class="btn btn-small" style="background: #3b82f6;">
                                            📎 View Receipt
                                        </a>
                                    <?php else: ?>
                                        <small style="color: #9ca3af;">No receipt</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                
                <h3 style="margin: 30px 0 15px;">Recent Rejected</h3>
                <?php if (empty($recentFailed)): ?>
                    <div style="padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; color: #64748b;">No rejected transactions yet.</div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody id="recentFailedBody">
                        <?php foreach ($recentFailed as $tx): $currency = $tx['currency'] ?? 'PHP'; ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($tx['created_at'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($tx['username']); ?></strong><br><small><?php echo htmlspecialchars($tx['phone']); ?></small></td>
                                <td>
                                    <span class="badge badge-danger">❌ Rejected</span>
                                </td>
                                <td><strong><?php echo formatCurrency($tx['amount'], $currency); ?></strong></td>
                                <td><?php echo htmlspecialchars($tx['description']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bonuses Tab -->
        <div id="bonuses-tab" class="tab-content">
            <div class="table-container">
                <h2 style="margin-bottom: 20px;">🎁 Bonus Programs Management</h2>
                
                <div style="margin-bottom: 20px;">
                    <button class="btn" onclick="showModal('createBonusModal')">➕ Create New Bonus</button>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
                    <div style="background: linear-gradient(135deg, #10b981, #059669); padding: 20px; border-radius: 12px; color: white;">
                        <div style="font-size: 14px; opacity: 0.9;">Total Bonuses Given</div>
                        <div style="font-size: 28px; font-weight: bold; margin-top: 5px;">₱<?php echo number_format($totalBonusAmount, 2); ?></div>
                    </div>
                    <div style="background: linear-gradient(135deg, #6366f1, #4f46e5); padding: 20px; border-radius: 12px; color: white;">
                        <div style="font-size: 14px; opacity: 0.9;">Total Claims</div>
                        <div style="font-size: 28px; font-weight: bold; margin-top: 5px;"><?php echo number_format($totalBonusClaims); ?></div>
                    </div>
                    <div style="background: linear-gradient(135deg, #f59e0b, #d97706); padding: 20px; border-radius: 12px; color: white;">
                        <div style="font-size: 14px; opacity: 0.9;">Active Programs</div>
                        <div style="font-size: 28px; font-weight: bold; margin-top: 5px;"><?php echo count(array_filter($bonusPrograms, fn($b) => $b['is_enabled'])); ?></div>
                    </div>
                </div>
                
                <h3 style="margin: 30px 0 15px;">Bonus Programs</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Trigger</th>
                            <th>Max Claims</th>
                            <th>Total Claims</th>
                            <th>Total Given</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bonusPrograms)): ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 40px; color: #64748b;">
                                    No bonus programs yet. Create one to get started!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bonusPrograms as $bonus): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($bonus['name']); ?></strong></td>
                                    <td>
                                        <span class="badge" style="background: <?php 
                                            echo $bonus['type'] === 'registration' ? '#10b981' : 
                                                ($bonus['type'] === 'deposit' ? '#f59e0b' : '#6366f1'); 
                                        ?>;">
                                            <?php echo ucfirst($bonus['type']); ?>
                                        </span>
                                    </td>
                                    <td><strong>₱<?php echo number_format($bonus['amount'], 2); ?></strong></td>
                                    <td>
                                        <?php 
                                        if ($bonus['type'] === 'deposit' && $bonus['trigger_value']) {
                                            echo '₱' . number_format($bonus['trigger_value'], 2) . ' deposit';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $bonus['max_claims_per_user']; ?></td>
                                    <td><?php echo number_format($bonus['total_claims']); ?></td>
                                    <td>₱<?php echo number_format($bonus['total_claimed_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo $bonus['is_enabled'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $bonus['is_enabled'] ? '✅ Enabled' : '❌ Disabled'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($bonus['created_at'])); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                            <button class="btn btn-small" onclick='editBonus(<?php echo json_encode($bonus, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>✏️ Edit</button>
                                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Delete this bonus? All claim history will be lost.');">
                                                <input type="hidden" name="bonus_id" value="<?php echo $bonus['id']; ?>">
                                                <button type="submit" name="delete_bonus" class="btn btn-small btn-danger">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <h3 style="margin: 40px 0 15px;">Recent Bonus Claims</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Bonus</th>
                            <th>Amount</th>
                            <th>Balance Before</th>
                            <th>Balance After</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentBonusClaims)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
                                    No bonus claims yet
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentBonusClaims as $claim): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($claim['claimed_at'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($claim['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($claim['bonus_name']); ?></td>
                                    <td><strong style="color: #10b981;">+₱<?php echo number_format($claim['amount'], 2); ?></strong></td>
                                    <td>₱<?php echo number_format($claim['balance_before'], 2); ?></td>
                                    <td>₱<?php echo number_format($claim['balance_after'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

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
                            <th>Rank</th>
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
                            $userRank = UserRank::getRank($user['total_bets'] ?? 0);
                        ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td><?php echo UserRank::getRankBadge($userRank, 'small'); ?></td>
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

                    <!-- Marketing banners moved to Tool tab -->
                    
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
                    
                    <h3 style="margin-top: 30px;">Payment Methods</h3>
                    <div class="form-group payment-methods-grid">
                        <?php 
                            $validMethods = ['bank' => 'Bank Transfer', 'gcash' => 'GCash', 'paymaya' => 'PayMaya', 'crypto' => 'Cryptocurrency'];
                            $allowedMethods = json_decode($siteSettings['allowed_methods'] ?? '[]', true);
                            if (empty($allowedMethods)) { $allowedMethods = array_keys($validMethods); }
                        ?>
                        <?php foreach ($validMethods as $methodKey => $label): ?>
                            <label class="payment-method-card">
                                <input type="checkbox" name="allowed_methods[]" value="<?php echo $methodKey; ?>" <?php echo in_array($methodKey, $allowedMethods) ? 'checked' : ''; ?>>
                                <span><?php echo $label; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <small style="color: #666;">Uncheck to disable a payment method for users (deposit/withdraw).</small>

                    <h3 style="margin-top: 30px;">Payment Details</h3>
                    <p style="color: #9ca3af; margin-bottom: 12px;">Configure where users should send their deposits. These details will be shown to users.</p>
                    
                    <div class="payment-details-grid">
                        <div class="payment-details-card">
                            <h4 style="color: #3b82f6;">💳 GCash</h4>
                            <div class="form-group">
                                <label>GCash Number</label>
                                <input type="text" name="gcash_number" value="<?php echo htmlspecialchars($siteSettings['gcash_number'] ?? ''); ?>" placeholder="09123456789">
                            </div>
                            <div class="form-group">
                                <label>GCash Account Name</label>
                                <input type="text" name="gcash_name" value="<?php echo htmlspecialchars($siteSettings['gcash_name'] ?? ''); ?>" placeholder="Juan Dela Cruz">
                            </div>
                        </div>
                        
                        <div class="payment-details-card">
                            <h4 style="color: #10b981;">💚 Maya (PayMaya)</h4>
                            <div class="form-group">
                                <label>Maya Number</label>
                                <input type="text" name="maya_number" value="<?php echo htmlspecialchars($siteSettings['maya_number'] ?? ''); ?>" placeholder="09123456789">
                            </div>
                            <div class="form-group">
                                <label>Maya Account Name</label>
                                <input type="text" name="maya_name" value="<?php echo htmlspecialchars($siteSettings['maya_name'] ?? ''); ?>" placeholder="Juan Dela Cruz">
                            </div>
                        </div>
                        
                        <div class="payment-details-card">
                            <h4 style="color: #f59e0b;">₿ Cryptocurrency</h4>
                            <div class="form-group">
                                <label>Bitcoin (BTC) Address</label>
                                <input type="text" name="crypto_btc_address" value="<?php echo htmlspecialchars($siteSettings['crypto_btc_address'] ?? ''); ?>" placeholder="bc1q...">
                            </div>
                            <div class="form-group">
                                <label>Ethereum (ETH) Address</label>
                                <input type="text" name="crypto_eth_address" value="<?php echo htmlspecialchars($siteSettings['crypto_eth_address'] ?? ''); ?>" placeholder="0x...">
                            </div>
                            <div class="form-group">
                                <label>USDT (TRC20) Address</label>
                                <input type="text" name="crypto_usdt_address" value="<?php echo htmlspecialchars($siteSettings['crypto_usdt_address'] ?? ''); ?>" placeholder="T...">
                            </div>
                        </div>
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
                    
                    <h3 style="margin-top: 30px;">Custom Scripts</h3>
                    
                    <div class="form-group">
                        <label>Header Scripts (before &lt;/head&gt;)</label>
                        <textarea name="header_scripts" rows="5" style="font-family: monospace; font-size: 13px;"><?php echo htmlspecialchars($siteSettings['header_scripts'] ?? ''); ?></textarea>
                        <small style="color: #666;">Add tracking codes, custom CSS, or meta tags</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Footer Scripts (before &lt;/body&gt;)</label>
                        <textarea name="footer_scripts" rows="8" style="font-family: monospace; font-size: 13px;"><?php echo htmlspecialchars($siteSettings['footer_scripts'] ?? ''); ?></textarea>
                        <small style="color: #666;">Add chat widgets (Tawk.to, LiveChat), analytics, or custom JavaScript</small>
                    </div>
                    
                    <button type="submit" name="update_settings" class="btn" style="margin-top: 20px;">💾 Save Settings</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Bonus Modal -->
    <div id="createBonusModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('createBonusModal')">&times;</span>
            <h2>🎁 Create New Bonus Program</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Bonus Name *</label>
                    <input type="text" name="bonus_name" required placeholder="e.g., Welcome Bonus">
                </div>
                <div class="form-group">
                    <label>Bonus Type *</label>
                    <select name="bonus_type" id="create_bonus_type" required onchange="toggleTriggerField('create')">
                        <option value="registration">Registration Bonus (New users only, valid 7 days)</option>
                        <option value="deposit">Deposit Bonus (Requires deposit amount)</option>
                        <option value="custom">Custom Bonus (Always available)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Bonus Amount (PHP) *</label>
                    <input type="number" step="0.01" name="bonus_amount" required placeholder="50.00">
                </div>
                <div class="form-group" id="create_trigger_field" style="display: none;">
                    <label>Required Deposit Amount (PHP) *</label>
                    <input type="number" step="0.01" name="trigger_value" placeholder="100.00">
                    <small style="color: #666;">User must deposit this amount to claim the bonus</small>
                </div>
                <div class="form-group">
                    <label>Max Claims Per User *</label>
                    <input type="number" name="max_claims" value="1" min="1" required>
                    <small style="color: #666;">How many times each user can claim this bonus</small>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="bonus_description" rows="3" placeholder="Bonus details..."></textarea>
                </div>
                <button type="submit" name="create_bonus" class="btn">💾 Create Bonus</button>
            </form>
        </div>
    </div>

    <!-- Edit Bonus Modal -->
    <div id="editBonusModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('editBonusModal')">&times;</span>
            <h2>✏️ Edit Bonus Program</h2>
            <form method="POST">
                <input type="hidden" name="bonus_id" id="edit_bonus_id">
                <input type="hidden" name="bonus_type" id="edit_bonus_type_hidden">
                <div class="form-group">
                    <label>Bonus Name *</label>
                    <input type="text" name="bonus_name" id="edit_bonus_name" required>
                </div>
                <div class="form-group">
                    <label>Bonus Type</label>
                    <input type="text" id="edit_bonus_type_display" disabled style="background: #f3f4f6; cursor: not-allowed;">
                    <small style="color: #666;">Bonus type cannot be changed after creation</small>
                </div>
                <div class="form-group">
                    <label>Bonus Amount (PHP) *</label>
                    <input type="number" step="0.01" name="bonus_amount" id="edit_bonus_amount" required>
                </div>
                <div class="form-group" id="edit_trigger_field" style="display: none;">
                    <label>Required Deposit Amount (PHP) *</label>
                    <input type="number" step="0.01" name="trigger_value" id="edit_trigger_value">
                </div>
                <div class="form-group">
                    <label>Max Claims Per User *</label>
                    <input type="number" name="max_claims" id="edit_max_claims" min="1" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="bonus_description" id="edit_bonus_description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="is_enabled" id="edit_is_enabled" value="1">
                        <span>Enabled (Users can claim this bonus)</span>
                    </label>
                </div>
                <button type="submit" name="update_bonus" class="btn">💾 Update Bonus</button>
            </form>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('changePasswordModal')">&times;</span>
            <h2>🔑 Change Admin Password</h2>
            <form method="POST" onsubmit="return validatePasswordChange()">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" id="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" id="new_password" minlength="6" required>
                    <small style="color: #9ca3af;">Minimum 6 characters</small>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" minlength="6" required>
                </div>
                <div id="password-error" style="color: #ef4444; margin-bottom: 15px; display: none;"></div>
                <button type="submit" name="change_admin_password" class="btn">💾 Change Password</button>
            </form>
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
                    <select name="provider" required>
                        <option value="JILI">JILI</option>
                        <option value="PG">PG</option>
                        <option value="JDB">JDB</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" required>
                        <option value="Slots">Slots</option>
                        <option value="Table">Table</option>
                        <option value="Casino Live">Casino Live</option>
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
            
            <!-- Result Message -->
            <div id="edit-game-result" style="display: none; padding: 12px; border-radius: 8px; margin-bottom: 20px;"></div>
            
            <form id="editGameForm" method="POST">
                <input type="hidden" name="game_id" id="edit_game_id">
                <input type="hidden" name="update_game" value="1">
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
                    <select name="provider" id="edit_provider" required>
                        <option value="JILI">JILI</option>
                        <option value="PG">PG</option>
                        <option value="JDB">JDB</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" id="edit_category" required>
                        <option value="Slots">Slots</option>
                        <option value="Table">Table</option>
                        <option value="Casino Live">Casino Live</option>
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
                <button type="submit" class="btn" id="updateGameBtn">Update Game</button>
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
            // Server-side toasts for non-AJAX actions
            <?php
                $serverSuccess = $_SESSION['success'] ?? null;
                $serverError = $_SESSION['error'] ?? null;
                unset($_SESSION['success'], $_SESSION['error']);
            ?>
            const serverSuccess = <?php echo json_encode($serverSuccess); ?>;
            const serverError = <?php echo json_encode($serverError); ?>;
            if (serverSuccess) { showToast(serverSuccess, 'success'); }
            if (serverError) { showToast(serverError, 'error'); }
            
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
            // Initialize Tool tab autosave
            bindToolAutoSave();
            // Start wallet auto refresh every 10s
            setInterval(walletAutoRefresh, 10000);
            // Run once on load
            walletAutoRefresh();
        });
        
        function showModal(id) {
            document.getElementById(id).style.display = 'block';
        }
        
        function hideModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        function switchTab(tabName, el) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            if (el) { el.classList.add('active'); }
        }
        
        function showToast(message, type='success'){
            const cont = document.getElementById('toast-container');
            if (!cont) return;
            const el = document.createElement('div');
            el.className = 'toast ' + (type==='error' ? 'error' : 'success');
            el.textContent = message;
            cont.appendChild(el);
            requestAnimationFrame(()=> el.classList.add('show'));
            setTimeout(()=>{
                el.classList.remove('show');
                setTimeout(()=> el.remove(), 200);
            }, 3000);
        }

        function bindToolAutoSave(){
            // Auto-upload and auto-save in Tool tab using AJAX
            document.querySelectorAll('#tool-tab form').forEach(form => {
                // Only bind autosave for existing banners (forms with banner_index)
                const isExistingBanner = !!form.querySelector('input[name="banner_index"]');
                if (!isExistingBanner) {
                    return; // Skip the Add New Banner form
                }
                form.addEventListener('submit', function(ev){
                    // Prevent infinite loop from requestSubmit() retriggering
                    if (form.dataset.ajaxSubmitting === '1') return;
                    
                    // Intercept submit; send AJAX
                    ev.preventDefault();
                    const fd = new FormData(form);
                    // Ensure the server receives which action was intended (add/update/delete)
                    let submitName = '';
                    if (ev.submitter && ev.submitter.name) {
                        submitName = ev.submitter.name;
                    } else {
                        // Infer default action when auto-submitting
                        if (form.querySelector('input[name="banner_index"]')) {
                            submitName = 'update_banner';
                        } else if (form.querySelector('button[name="add_banner"]')) {
                            submitName = 'add_banner';
                        }
                    }
                    if (submitName) { fd.append(submitName, '1'); }
                    form.dataset.ajaxSubmitting = '1';
                    console.log('AJAX submit:', Array.from(fd.keys()));
                    
                    fetch('admin.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd
                    }).then(r=> {
                        if (!r.ok) throw new Error(`HTTP ${r.status}`);
                        return r.text();
                    }).then(text=>{
                        try {
                            const data = JSON.parse(text);
                            if (data && data.success){
                                showToast('Saved successfully');
                                // Update UI without full reload
                                if (submitName === 'delete_banner') {
                                    form.remove();
                                    return;
                                }
                                if (submitName === 'update_banner') {
                                    const idxInput = form.querySelector('input[name="banner_index"]');
                                    const idx = idxInput ? parseInt(idxInput.value, 10) : -1;
                                    if (!isNaN(idx) && data.banners && data.banners[idx]){
                                        const imgPath = data.banners[idx].image || '';
                                        if (imgPath){
                                            const previewImg = form.querySelector('img');
                                            const noImg = form.querySelector('div[style*="No image"]');
                                            if (previewImg){
                                                previewImg.src = imgPath + (imgPath.includes('?') ? '&' : '?') + 't=' + Date.now();
                                            } else {
                                                if (noImg && noImg.parentElement){
                                                    const img = document.createElement('img');
                                                    img.src = imgPath;
                                                    img.style.width = '220px';
                                                    img.style.height = '140px';
                                                    img.style.objectFit = 'cover';
                                                    img.style.borderRadius = '10px';
                                                    img.style.border = '1px solid #2d3548';
                                                    img.style.background = '#0f1626';
                                                    noImg.parentElement.replaceChild(img, noImg);
                                                }
                                            }
                                        }
                                    }
                                }
                                if (submitName === 'add_banner') {
                                    // Clear the add form fields
                                    try { form.reset(); } catch (e) {}
                                }
                            } else {
                                showToast(data && data.error ? data.error : 'Save failed', 'error');
                            }
                        } catch(e) {
                            showToast('Invalid response from server', 'error');
                                                        console.error('Parse error:', e, 'Response:', text.substring(0,500));
                        }
                                        }).catch(err=> showToast('Network error: ' + err.message, 'error'))
                                            .finally(()=> form.dataset.ajaxSubmitting = '0');
                });
                // Auto submit on file change
                form.querySelectorAll('input[type="file"]').forEach(inp => {
                    inp.addEventListener('change', () => form.requestSubmit());
                });
                // Auto save on input changes (debounced)
                let t;
                form.querySelectorAll('input[type="text"], input[type="url"], textarea, select, input[type="checkbox"]').forEach(inp => {
                    inp.addEventListener('change', ()=>{
                        clearTimeout(t);
                        t = setTimeout(()=> form.requestSubmit(), 600);
                    });
                });
            });
        }

        // Wallet auto-refresh: poll server and update tables/badge
        function walletAutoRefresh(){
            const pendingBody = document.getElementById('pendingTransactionsBody');
            const approvedBody = document.getElementById('recentApprovedBody');
            const failedBody = document.getElementById('recentFailedBody');
            const walletTabBtn = Array.from(document.querySelectorAll('.tabs .tab')).find(b => b.getAttribute('onclick') && b.getAttribute('onclick').includes("wallet"));
            fetch('admin.php?wallet_json=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
              .then(r=>r.json())
              .then(data=>{
                if (!data) return;
                // Update pending badge
                if (walletTabBtn){
                    let badge = walletTabBtn.querySelector('#pendingBadge');
                    if (data.pendingCount > 0){
                        if (!badge){
                            badge = document.createElement('span');
                            badge.id = 'pendingBadge';
                            badge.style.position = 'absolute';
                            badge.style.top = '-5px';
                            badge.style.right = '-5px';
                            badge.style.background = '#ef4444';
                            badge.style.color = 'white';
                            badge.style.borderRadius = '10px';
                            badge.style.padding = '2px 6px';
                            badge.style.fontSize = '11px';
                            badge.style.fontWeight = 'bold';
                            walletTabBtn.style.position = 'relative';
                            walletTabBtn.appendChild(badge);
                        }
                        badge.textContent = data.pendingCount;
                    } else if (badge) {
                        badge.remove();
                    }
                }
                // Render pending transactions
                if (pendingBody && data.pendingTransactions){
                    if (data.pendingTransactions.length === 0){
                        pendingBody.parentElement.parentElement.innerHTML = '<div style="text-align: center; padding: 60px 20px; color: #64748b;"><div style="font-size: 64px; margin-bottom: 20px;">✅</div><h3 style="margin-bottom: 10px;">No Pending Transactions</h3><p>All deposit and withdrawal requests have been processed.</p></div>';
                    } else {
                        const pendingRows = data.pendingTransactions.map(trans => {
                            const d = new Date(trans.created_at);
                            const dateStr = d.toLocaleString();
                            const isDeposit = trans.type === 'deposit';
                            const newBalance = isDeposit ? (parseFloat(trans.current_balance) + parseFloat(trans.amount)) : (parseFloat(trans.current_balance) - parseFloat(trans.amount));
                            const hasEnoughBalance = newBalance >= 0;
                            const currency = trans.currency || 'PHP';
                            const typeBadge = isDeposit ? '<span class="badge badge-success">📥 Deposit</span>' : '<span class="badge badge-warning">📤 Withdrawal</span>';
                            const rowStyle = hasEnoughBalance ? '' : ' style="background: #fee2e2;"';
                            return `<tr${rowStyle}>
                                <td>${dateStr}</td>
                                <td><strong>${escapeHtml(trans.username)}</strong></td>
                                <td>${escapeHtml(trans.phone || '')}</td>
                                <td>${typeBadge}</td>
                                <td><strong>${currency} ${parseFloat(trans.amount).toFixed(2)}</strong></td>
                                <td>${currency} ${parseFloat(trans.current_balance).toFixed(2)}</td>
                                <td><strong style="color: ${hasEnoughBalance ? (isDeposit ? '#10b981' : '#f59e0b') : '#ef4444'};">${currency} ${newBalance.toFixed(2)}</strong>${!hasEnoughBalance ? '<br><small style="color: #ef4444;">⚠️ Insufficient Balance</small>' : ''}</td>
                                <td>${escapeHtml(trans.description || '')}</td>
                                <td><div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    ${isDeposit ? 
                                        `<form method="POST" style="margin: 0;" onsubmit="return confirm('Approve this deposit?');">
                                            <input type="hidden" name="transaction_id" value="${trans.id}">
                                            <button type="submit" name="approve_transaction" class="btn btn-small" style="background: #10b981;">✅ Approve</button>
                                        </form>` :
                                        `<button class="btn btn-small" style="background: #10b981;" onclick="showApproveWithReceiptModal(${trans.id}, '${escapeHtml(trans.username)}', ${trans.amount}, '${currency}', ${hasEnoughBalance})" ${!hasEnoughBalance ? 'disabled' : ''}>✅ Approve</button>`
                                    }
                                    </form>
                                    <button class="btn btn-small btn-danger" onclick="showRejectModal(${trans.id}, '${escapeHtml(trans.username)}', '${trans.type}')">❌ Reject</button>
                                </div></td>
                            </tr>`;
                        }).join('');
                        pendingBody.innerHTML = pendingRows;
                    }
                }
                // Render tables
                function renderRows(rows, isApproved){
                    return rows.map(tx => {
                        const d = new Date(tx.created_at);
                        const dateStr = d.toLocaleString();
                        const typeBadge = isApproved ? (tx.type === 'deposit' ? '<span class="badge badge-success">📥 Deposit</span>' : '<span class="badge badge-warning">📤 Withdrawal</span>') : '<span class="badge badge-danger">❌ Rejected</span>';
                        const amount = Number(tx.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        const userCell = `<strong>${escapeHtml(tx.username || '')}</strong><br><small>${escapeHtml(tx.phone || '')}</small>`;
                        const receiptCell = tx.receipt_image ? `<a href="${escapeHtml(tx.receipt_image)}" target="_blank" class="btn btn-small" style="background: #3b82f6;">📎 View Receipt</a>` : '<small style="color: #9ca3af;">No receipt</small>';
                        return `<tr>
                            <td>${dateStr}</td>
                            <td>${userCell}</td>
                            <td>${typeBadge}</td>
                            <td><strong>${amount}</strong></td>
                            <td>${escapeHtml(tx.description || '')}</td>
                            ${isApproved ? `<td>${receiptCell}</td>` : ''}
                        </tr>`;
                    }).join('');
                }
                if (approvedBody){ approvedBody.innerHTML = renderRows(data.recentApproved || [], true); }
                if (failedBody){ failedBody.innerHTML = renderRows(data.recentFailed || [], false); }
              }).catch(()=>{});
        }

        // Simple HTML escape for dynamic inserts
        function escapeHtml(str){
            return String(str).replace(/[&<>"]+/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[s]));
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
        
        function viewUserHistory(userId, username, page = 1) {
            document.getElementById('history_username').textContent = 'User: ' + username;
            showModal('userHistoryModal');
            
            // Fetch user transaction history via AJAX
            fetch('get_user_history.php?user_id=' + userId + '&page=' + page)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<table style="width: 100%;"><thead><tr>' +
                            '<th>Date/Time</th><th>Type</th><th>Game</th><th>Amount</th><th>Balance Before</th><th>Balance After</th>' +
                            '</tr></thead><tbody>';
                        
                        if (data.transactions.length === 0) {
                            html += '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #999;">No transactions found</td></tr>';
                        } else {
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
                        }
                        
                        html += '</tbody></table>';
                        
                        // Add pagination controls
                        if (data.pagination && data.pagination.total_pages > 1) {
                            html += '<div style="margin-top: 20px; text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px;">';
                            html += `<span style="color: #64748b; font-size: 14px;">Page ${data.pagination.current_page} of ${data.pagination.total_pages} (${data.pagination.total_records} records)</span>`;
                            html += '<div style="display: flex; gap: 5px;">';
                            
                            // Previous button
                            if (data.pagination.current_page > 1) {
                                html += `<button class="btn btn-small" onclick="viewUserHistory(${userId}, '${username}', ${data.pagination.current_page - 1})">← Previous</button>`;
                            }
                            
                            // Page numbers (show 5 pages max)
                            let startPage = Math.max(1, data.pagination.current_page - 2);
                            let endPage = Math.min(data.pagination.total_pages, startPage + 4);
                            startPage = Math.max(1, endPage - 4);
                            
                            for (let i = startPage; i <= endPage; i++) {
                                const activeStyle = i === data.pagination.current_page ? 
                                    'background: linear-gradient(135deg, #667eea, #764ba2); color: white;' : 
                                    'background: #1e293b; color: #cbd5e1;';
                                html += `<button class="btn btn-small" style="${activeStyle}" onclick="viewUserHistory(${userId}, '${username}', ${i})">${i}</button>`;
                            }
                            
                            // Next button
                            if (data.pagination.current_page < data.pagination.total_pages) {
                                html += `<button class="btn btn-small" onclick="viewUserHistory(${userId}, '${username}', ${data.pagination.current_page + 1})">Next →</button>`;
                            }
                            
                            html += '</div></div>';
                        }
                        
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
            
            // Clear previous result message
            document.getElementById('edit-game-result').style.display = 'none';
            
            showModal('editGameModal');
        }
        
        // Handle edit game form submission via AJAX
        document.getElementById('editGameForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const btn = document.getElementById('updateGameBtn');
            const resultDiv = document.getElementById('edit-game-result');
            
            btn.disabled = true;
            btn.textContent = 'Updating...';
            resultDiv.style.display = 'none';
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Success - show message in modal
                resultDiv.style.display = 'block';
                resultDiv.style.background = '#d1fae5';
                resultDiv.style.border = '1px solid #10b981';
                resultDiv.style.color = '#065f46';
                resultDiv.innerHTML = '✅ Game updated successfully! Cache cleared.';
                
                btn.disabled = false;
                btn.textContent = 'Update Game';
                
                // Reload games list in background
                reloadAllGames();
                
                // Hide success message after 3 seconds
                setTimeout(() => {
                    resultDiv.style.display = 'none';
                }, 3000);
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.style.display = 'block';
                resultDiv.style.background = '#fee2e2';
                resultDiv.style.border = '1px solid #ef4444';
                resultDiv.style.color = '#991b1b';
                resultDiv.innerHTML = '❌ Failed to update game';
                
                btn.disabled = false;
                btn.textContent = 'Update Game';
            });
        });
        
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
        
        // Wallet transaction functions
        function showRejectModal(transactionId, username, type) {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.style.display = 'block';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 500px;">
                    <span class="close" onclick="this.closest('.modal').remove()">&times;</span>
                    <h2 style="margin-bottom: 20px;">❌ Reject ${type.charAt(0).toUpperCase() + type.slice(1)}</h2>
                    <form method="POST">
                        <input type="hidden" name="transaction_id" value="${transactionId}">
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">User: <strong>${username}</strong></label>
                        </div>
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">Rejection Reason:</label>
                            <textarea name="rejection_reason" rows="4" style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-family: inherit;" placeholder="Enter reason for rejection (optional)"></textarea>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="btn" onclick="this.closest('.modal').remove()" style="flex: 1; background: #64748b;">Cancel</button>
                            <button type="submit" name="reject_transaction" class="btn btn-danger" style="flex: 1;">Reject Transaction</button>
                        </div>
                    </form>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        function showApproveWithReceiptModal(transactionId, username, amount, currency, hasEnoughBalance) {
            if (!hasEnoughBalance) {
                alert('User has insufficient balance for withdrawal');
                return;
            }
            
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.style.display = 'block';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 600px;">
                    <span class="close" onclick="this.closest('.modal').remove()">&times;</span>
                    <h2 style="margin-bottom: 20px;">✅ Approve Withdrawal</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="transaction_id" value="${transactionId}">
                        <div style="margin-bottom: 20px; padding: 16px; background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px;">
                            <div style="margin-bottom: 8px;"><strong>User:</strong> ${username}</div>
                            <div style="margin-bottom: 8px;"><strong>Amount:</strong> <span style="color: #f59e0b; font-size: 18px; font-weight: 600;">${currency} ${parseFloat(amount).toFixed(2)}</span></div>
                        </div>
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">Upload Proof of Transaction (Receipt):</label>
                            <input type="file" name="receipt_image" accept="image/*,.pdf" style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: white;">
                            <small style="color: #64748b; display: block; margin-top: 6px;">📎 Upload screenshot, photo, or PDF of the transaction receipt (optional but recommended)</small>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="btn" onclick="this.closest('.modal').remove()" style="flex: 1; background: #64748b;">Cancel</button>
                            <button type="submit" name="approve_transaction" class="btn" style="flex: 1; background: #10b981;">✅ Approve Withdrawal</button>
                        </div>
                    </form>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        function validatePasswordChange() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const errorDiv = document.getElementById('password-error');
            
            if (newPassword !== confirmPassword) {
                errorDiv.textContent = 'New passwords do not match!';
                errorDiv.style.display = 'block';
                return false;
            }
            
            if (newPassword.length < 6) {
                errorDiv.textContent = 'Password must be at least 6 characters long!';
                errorDiv.style.display = 'block';
                return false;
            }
            
            errorDiv.style.display = 'none';
            return true;
        }
        
        // Bonus management functions
        function toggleTriggerField(mode) {
            const select = document.getElementById(mode + '_bonus_type');
            const triggerField = document.getElementById(mode + '_trigger_field');
            
            if (select.value === 'deposit') {
                triggerField.style.display = 'block';
                triggerField.querySelector('input').required = true;
            } else {
                triggerField.style.display = 'none';
                triggerField.querySelector('input').required = false;
            }
        }
        
        function editBonus(bonus) {
            document.getElementById('edit_bonus_id').value = bonus.id;
            document.getElementById('edit_bonus_name').value = bonus.name;
            document.getElementById('edit_bonus_amount').value = bonus.amount;
            document.getElementById('edit_bonus_description').value = bonus.description || '';
            document.getElementById('edit_max_claims').value = bonus.max_claims_per_user;
            document.getElementById('edit_is_enabled').checked = bonus.is_enabled == 1;
            document.getElementById('edit_bonus_type_hidden').value = bonus.type;
            document.getElementById('edit_bonus_type_display').value = bonus.type.charAt(0).toUpperCase() + bonus.type.slice(1);
            
            if (bonus.type === 'deposit') {
                document.getElementById('edit_trigger_field').style.display = 'block';
                document.getElementById('edit_trigger_value').value = bonus.trigger_value || '';
                document.getElementById('edit_trigger_value').required = true;
            } else {
                document.getElementById('edit_trigger_field').style.display = 'none';
                document.getElementById('edit_trigger_value').required = false;
            }
            
            showModal('editBonusModal');
        }
        
        // Update sidebar active state when switching tabs
        const originalSwitchTab = window.switchTab;
        window.switchTab = function(tabName, element) {
            // Update sidebar highlighting
            document.querySelectorAll('.sidebar-item').forEach(item => item.classList.remove('active'));
            if (element && element.target) {
                element.target.closest('.sidebar-item').classList.add('active');
            }
            originalSwitchTab(tabName, element);
        };
        
        // Add styles for sidebar
        const style = document.createElement('style');
        style.textContent = `
            .sidebar-item:hover {
                background: rgba(102, 126, 234, 0.15);
                color: #cbd5e1;
            }
            .sidebar-item.active {
                background: linear-gradient(135deg, #667eea, #764ba2) !important;
                color: white !important;
            }
            .sidebar {
                scrollbar-width: thin;
                scrollbar-color: #2d3548 transparent;
            }
            .sidebar::-webkit-scrollbar {
                width: 6px;
            }
            .sidebar::-webkit-scrollbar-track {
                background: transparent;
            }
            .sidebar::-webkit-scrollbar-thumb {
                background: #2d3548;
                border-radius: 3px;
            }
            .sidebar::-webkit-scrollbar-thumb:hover {
                background: #475569;
            }
        `;
        document.head.appendChild(style);
     </script>
     <!-- AdminLTE + Bootstrap JS (CDN) -->
     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
     <script src="https://cdn.jsdelivr.net/npm/admin-lte@4/dist/js/adminlte.min.js"></script>
        </div>
    </div>
 </body>
 </html>
