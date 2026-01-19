<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'db_helper.php';
require_once 'settings_helper.php';
require_once 'csrf_helper.php';
require_once 'recaptcha_config.php';

// Load site settings
$casinoName = SiteSettings::get('casino_name', 'Casino PHP');
$casinoTagline = SiteSettings::get('casino_tagline', 'Play & Win Big!');

$user = new User();

// Generate CSRF token for forms
CSRF::generateToken();

// Handle success/error messages from session
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
unset($_SESSION['error'], $_SESSION['success']);

// Handle Login
if (isset($_POST['login'])) {
    // Verify CSRF token - STRICT
    if (!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        CSRF::regenerateToken();
        $_SESSION['error'] = 'Session expired. Please refresh and try again.';
        header('Location: login.php');
        exit;
    }
    
    // Get user IP address early (used in logs)
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ipList[0]);
    }

    // Verify reCAPTCHA token (if present) - NON-BLOCKING
    // Token gathering is async, so it might not be present immediately
    // If present, validate it; if missing, log but continue (allows testing without reCAPTCHA)
    if (!empty($_POST['recaptcha_token'] ?? '')) {
        if (!RecaptchaVerifier::verify($_POST['recaptcha_token'], 'login')) {
            error_log("reCAPTCHA verification failed for login attempt from IP: {$ip}");
            // Don't block login, just log the suspicious activity
        }
    }
    
    $phoneOrUsername = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($phoneOrUsername) || empty($password)) {
        $_SESSION['error'] = 'Phone/username and password are required.';
        header('Location: login.php');
        exit;
    }
    
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Check if IP is blocked due to too many failed attempts
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempts 
        FROM login_attempts 
        WHERE ip_address = ? 
        AND DATE(attempt_time) = ?
    ");
    $stmt->execute([$ip, $today]);
    $attemptCount = $stmt->fetchColumn();
    
    if ($attemptCount >= 3) {
        $_SESSION['error'] = 'Too many failed login attempts. Please try again tomorrow.';
        header('Location: login.php');
        exit;
    }
    
    $loggedUser = $user->login($phoneOrUsername, $password);
    
    if ($loggedUser) {
        // Clear failed login attempts on successful login
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
        $stmt->execute([$ip]);
        
        $_SESSION['user_id'] = $loggedUser['id'];
        $_SESSION['username'] = $loggedUser['username'];
        $_SESSION['phone'] = $loggedUser['phone'];
        
        // Get user agent and parse device/browser info
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $device = 'Unknown';
        $browser = 'Unknown';
        $os = 'Unknown';
        
        // Detect OS
        if (preg_match('/Windows/i', $userAgent)) $os = 'Windows';
        elseif (preg_match('/Mac|iPhone|iPad/i', $userAgent)) $os = 'iOS/Mac';
        elseif (preg_match('/Android/i', $userAgent)) $os = 'Android';
        elseif (preg_match('/Linux/i', $userAgent)) $os = 'Linux';
        
        // Detect Browser
        if (preg_match('/Chrome/i', $userAgent)) $browser = 'Chrome';
        elseif (preg_match('/Firefox/i', $userAgent)) $browser = 'Firefox';
        elseif (preg_match('/Safari/i', $userAgent)) $browser = 'Safari';
        elseif (preg_match('/Edge/i', $userAgent)) $browser = 'Edge';
        
        // Detect Device Type
        if (preg_match('/Mobile|Android|iPhone/i', $userAgent)) $device = 'Mobile';
        elseif (preg_match('/Tablet|iPad/i', $userAgent)) $device = 'Tablet';
        else $device = 'Desktop';
        
        // Update user tracking info
        $stmt = $pdo->prepare("
            UPDATE users SET 
                last_login = NOW(), 
                last_ip = ?,
                last_device = ?,
                last_browser = ?,
                last_os = ?,
                login_count = login_count + 1
            WHERE id = ?
        ");
        $stmt->execute([$ip, $device, $browser, $os, $loggedUser['id']]);
        
        // Log login to history table
        $stmt = $pdo->prepare("
            INSERT INTO login_history 
            (user_id, ip_address, device, browser, os, login_time) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$loggedUser['id'], $ip, $device, $browser, $os]);
        
        // Store session ID for logout tracking
        $_SESSION['login_history_id'] = $pdo->lastInsertId();
        
        // Device fingerprinting - Check for suspicious login patterns
        $suspiciousLogin = false;
        $suspiciousReasons = [];
        
        // Check 1: Multiple IPs in short time (different IP within last hour)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ip_address) as ip_count 
            FROM login_history 
            WHERE user_id = ? 
            AND login_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$loggedUser['id']]);
        $ipCount = $stmt->fetchColumn();
        
        if ($ipCount > 2) {
            $suspiciousLogin = true;
            $suspiciousReasons[] = 'Multiple IPs detected';
        }
        
        // Check 2: Different device type than usual
        $stmt = $pdo->prepare("
            SELECT device, COUNT(*) as count 
            FROM login_history 
            WHERE user_id = ? 
            GROUP BY device 
            ORDER BY count DESC 
            LIMIT 1
        ");
        $stmt->execute([$loggedUser['id']]);
        $usualDevice = $stmt->fetch();
        
        if ($usualDevice && $usualDevice['device'] !== $device && $usualDevice['count'] > 5) {
            $suspiciousLogin = true;
            $suspiciousReasons[] = 'Unusual device type';
        }
        
        // Check 3: Rapid login attempts from different locations
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM login_history 
            WHERE user_id = ? 
            AND login_time >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        $stmt->execute([$loggedUser['id']]);
        $recentLogins = $stmt->fetchColumn();
        
        if ($recentLogins > 3) {
            $suspiciousLogin = true;
            $suspiciousReasons[] = 'Rapid login attempts';
        }
        
        // Log suspicious activity
        if ($suspiciousLogin) {
            $stmt = $pdo->prepare("
                INSERT INTO suspicious_logins 
                (user_id, ip_address, device, browser, os, reasons, detected_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $loggedUser['id'], 
                $ip, 
                $device, 
                $browser, 
                $os, 
                implode(', ', $suspiciousReasons)
            ]);
            
            // Set warning flag in session
            $_SESSION['suspicious_login_warning'] = true;
        }
        
        // Warm up Redis cache with user data for fast access
        require_once 'redis_helper.php';
        $cache = RedisCache::getInstance();
        $cache->warmUserCache($loggedUser['id'], $loggedUser);
        
        header('Location: index.php');
        exit;
    } else {
        // Login failed - check if user exists to give appropriate error message
        $normalizedPhone = $phoneOrUsername;
        if (preg_match('/^[0-9+]/', $phoneOrUsername)) {
            // Normalize phone if input looks like a phone number
            $normalizedPhone = preg_replace('/[^0-9+]/', '', $phoneOrUsername);
            
            // Philippine format: 09XXXXXXXXX (11 digits) -> +639XXXXXXXXX
            if (substr($normalizedPhone, 0, 2) == '09' && strlen($normalizedPhone) == 11) {
                // Remove '0', keep '9XXXXXXXXX', add '+63'
                $normalizedPhone = '+63' . substr($normalizedPhone, 1);
            } elseif (substr($normalizedPhone, 0, 1) == '9' && strlen($normalizedPhone) == 10) {
                // Format: 9XXXXXXXXX -> +639XXXXXXXXX
                $normalizedPhone = '+639' . $normalizedPhone;
            } elseif (substr($normalizedPhone, 0, 4) != '+639' && substr($normalizedPhone, 0, 3) == '+63') {
                // Already has +63, keep as is
                $normalizedPhone = $normalizedPhone;
            } elseif (substr($normalizedPhone, 0, 4) != '+639') {
                // Fallback: add +639 prefix
                $normalizedPhone = '+639' . $normalizedPhone;
            }
        }
        
        // First check if user exists at all (regardless of status)
        $stmt = $pdo->prepare("
            SELECT status FROM users 
            WHERE (username = ? OR phone = ? OR phone = ?)
        ");
        $stmt->execute([$phoneOrUsername, $phoneOrUsername, $normalizedPhone]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            // User exists - check their status
            if ($existingUser['status'] === 'banned') {
                $_SESSION['error'] = 'Your account has been banned. Please contact support.';
                header('Location: login.php');
                exit;
            } elseif ($existingUser['status'] === 'suspended') {
                $_SESSION['error'] = 'Your account has been suspended. Please contact support.';
                header('Location: login.php');
                exit;
            }
            // Status is 'active' but wrong password - will be caught below
        } elseif (preg_match('/^[0-9+]/', $phoneOrUsername)) {
            // Phone number format but user doesn't exist - redirect to registration
            $_SESSION['reg_phone'] = $phoneOrUsername;
            $_SESSION['error'] = 'Phone number not registered. Please register first.';
            header('Location: login.php#register');
            exit;
        }
        
        // Record failed login attempt
        try {
            $stmt = $pdo->prepare("
                INSERT INTO login_attempts (ip_address, username_or_phone, attempt_time)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$ip, $phoneOrUsername]);
        } catch (Exception $e) {
            error_log("Failed to insert login attempt: " . $e->getMessage());
        }
        
        // Count today's attempts
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE ip_address = ? 
            AND DATE(attempt_time) = ?
        ");
        $stmt->execute([$ip, $today]);
        $attemptCount = $stmt->fetchColumn();
        
        $remainingAttempts = 3 - $attemptCount;
        
        if ($remainingAttempts <= 0) {
            $_SESSION['error'] = 'Too many failed login attempts. Your IP is now blocked until tomorrow.';
        } elseif ($remainingAttempts <= 3) {
            $_SESSION['error'] = "Wrong password. {$remainingAttempts} attempts remaining before lockout.";
        } else {
            $_SESSION['error'] = 'Wrong password. Please try again.';
        }
        
        header('Location: login.php');
        exit;
    }
}

// Handle Registration
if (isset($_POST['register'])) {
    // Verify CSRF token - STRICT
    if (!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        CSRF::regenerateToken();
        $_SESSION['error'] = 'Session expired. Please refresh and try again.';
        header('Location: login.php#register');
        exit;
    }
    
    // Verify reCAPTCHA token (if present) - NON-BLOCKING
    // Token gathering is async, so it might not be present immediately
    // If present, validate it; if missing, log but continue (allows testing without reCAPTCHA)
    if (!empty($_POST['recaptcha_token'] ?? '')) {
        if (!RecaptchaVerifier::verify($_POST['recaptcha_token'], 'register')) {
            error_log("reCAPTCHA verification failed for registration attempt from IP: {$ip}");
            // Don't block registration, just log the suspicious activity
        }
    }
    
    $phone = $_POST['reg_phone'] ?? '';
    $password = $_POST['reg_password'] ?? '';
    $confirmPassword = $_POST['reg_confirm_password'] ?? '';
    
    if (empty($phone) || empty($password) || empty($confirmPassword)) {
        $_SESSION['error'] = 'All fields are required.';
        header('Location: login.php#register');
        exit;
    }
    
    // Get user IP address
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ipList[0]);
    }
    
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Check IP registration limit (max 1 account per IP)
    $stmt = $pdo->prepare("SELECT registration_count FROM ip_registrations WHERE ip_address = ?");
    $stmt->execute([$ip]);
    $ipData = $stmt->fetch();
    
    if ($ipData && $ipData['registration_count'] >= 1) {
        $_SESSION['error'] = 'Maximum registration limit reached from this IP address. Contact support if you need help.';
        header('Location: login.php');
        exit;
    }
    
    if ($password !== $confirmPassword) {
        $_SESSION['error'] = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $_SESSION['error'] = 'Password must be at least 6 characters';
    } elseif (strlen($phone) < 10) {
        $_SESSION['error'] = 'Invalid phone number';
    } else {
        // Default to PHP currency for Philippine users
        $result = $user->register($phone, $password, '+639', 'PHP');
        
        if ($result) {
            // Track IP registration
            $stmt = $pdo->prepare("
                INSERT INTO ip_registrations (ip_address, registration_count, first_registration, last_registration)
                VALUES (?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                    registration_count = registration_count + 1,
                    last_registration = NOW()
            ");
            $stmt->execute([$ip]);
            
            // Auto-login after successful registration
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$result['id']]);
            $newUser = $stmt->fetch();
            
            if ($newUser) {
                $_SESSION['user_id'] = $newUser['id'];
                $_SESSION['username'] = $newUser['username'];
                $_SESSION['phone'] = $newUser['phone'];
                $_SESSION['success'] = "Welcome! Your username is: <strong>{$result['username']}</strong>";
                
                // Redirect to homepage
                header('Location: index.php');
                exit;
            }
        } else {
            $_SESSION['error'] = 'Phone number already registered';
        }
    }
    header('Location: login.php');
    exit;
}

/**
 * All users default to PHP currency (Philippine Peso)
 * Targeting Philippine market
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#6366f1">
    <title><?php echo htmlspecialchars($casinoName); ?> - Login</title>
    <link rel="manifest" href="manifest.json">
    
    <!-- Google reCAPTCHA v3 -->
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo RECAPTCHA_SITE_KEY; ?>"></script>
    
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
        
        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 440px;
            padding: 40px;
            position: relative;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            padding: 8px 12px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .back-link:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(-3px);
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
        
        .logo h1 {
            font-size: 36px;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
        }
        
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 25px;
            background: #f5f5f5;
            border-radius: 12px;
            padding: 5px;
        }
        
        .tab {
            flex: 1;
            padding: 12px 10px;
            text-align: center;
            border: none;
            background: transparent;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            transition: all 0.3s;
            font-size: 15px;
        }
        
        .tab.active {
            background: white;
            color: #667eea;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-container {
            display: none;
        }
        
        .form-container.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 18px;
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
        
        .btn {
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
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #059669;
            border-left: 4px solid #059669;
        }
        
        small {
            display: block;
            color: #888;
            font-size: 12px;
            margin-top: 5px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .container {
                padding: 30px 25px;
                border-radius: 16px;
            }
            
            .logo {
                margin-bottom: 25px;
            }
            
            .logo h1 {
                font-size: 32px;
            }
            
            .back-link {
                font-size: 13px;
                padding: 6px 10px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 8px;
                align-items: flex-start;
                padding-top: 20px;
            }
            
            .container {
                padding: 25px 20px;
                border-radius: 16px;
                max-width: 100%;
            }
            
            .back-link {
                font-size: 13px;
                padding: 6px 10px;
                margin-bottom: 15px;
            }
            
            .logo {
                margin-bottom: 20px;
            }
            
            .tabs {
                gap: 6px;
                padding: 4px;
            }
            
            .tab {
                padding: 10px 8px;
                font-size: 14px;
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
            
            .btn {
                padding: 14px;
                font-size: 15px;
            }
            
            .alert {
                padding: 10px 14px;
                font-size: 13px;
            }
            
            small {
                font-size: 11px;
            }
        }
        
        @media (max-width: 360px) {
            .container {
                padding: 20px 16px;
            }
            
            .logo h1 {
                font-size: 24px;
            }
            
            input, .btn {
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Games</a>
        <div class="logo">
            <a href="index.php">
                <h1>🎰 <?php echo htmlspecialchars($casinoName); ?></h1>
                <p><?php echo htmlspecialchars($casinoTagline); ?></p>
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('login')">Login</button>
            <button class="tab" onclick="showTab('register')">Register</button>
        </div>
        
        <!-- Login Form -->
        <div id="login-form" class="form-container active">
            <form method="POST" id="loginForm">
                <?php echo CSRF::getTokenField(); ?>
                <input type="hidden" name="recaptcha_token" id="loginRecaptchaToken">
                <div class="form-group">
                    <label>Phone Number or Username</label>
                    <input type="text" name="phone" required placeholder="09XXXXXXXXX or username" 
                           pattern="^(09\d{9}|\+639\d{9}|[a-zA-Z0-9_]+)$">
                    <small style="color: #888; font-size: 12px; margin-top: 5px; display: block;">
                        📱 Format: 09XXXXXXXXX
                    </small>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="Enter your password">
                </div>
                <button type="submit" name="login" class="btn">Login</button>
            </form>
        </div>
        
        <!-- Register Form -->
        <div id="register-form" class="form-container">
            <form method="POST" id="registerForm">
                <?php echo CSRF::getTokenField(); ?>
                <input type="hidden" name="recaptcha_token" id="registerRecaptchaToken">
                <div class="form-group">
                    <label>Phone Number 🇵🇭</label>
                    <input type="tel" name="reg_phone" required placeholder="09XXXXXXXXX" 
                           pattern="^(09\d{9}|\+639\d{9})$" maxlength="13"
                           value="<?php echo isset($_SESSION['reg_phone']) ? htmlspecialchars($_SESSION['reg_phone']) : ''; 
                           unset($_SESSION['reg_phone']); ?>">
                    <small style="color: #888; font-size: 12px; margin-top: 5px; display: block;">
                        📱 Philippines: 09XXXXXXXXX (11 digits)<br>
                        👤 Username will be auto-generated
                    </small>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="reg_password" required placeholder="Choose a password" minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="reg_confirm_password" required placeholder="Confirm your password">
                </div>
                <button type="submit" name="register" class="btn">Register</button>
            </form>
        </div>
    </div>
    
    <script>
        function showTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.form-container').forEach(f => f.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tab + '-form').classList.add('active');
        }
        
        // Auto-format phone number input
        document.addEventListener('DOMContentLoaded', function() {
            // Check hash to show register tab (for #register anchor)
            if (window.location.hash === '#register') {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.form-container').forEach(f => f.classList.remove('active'));
                document.querySelectorAll('.tab')[1].classList.add('active'); // Register tab
                document.getElementById('register-form').classList.add('active');
            }
            
            // Check URL parameter to show register tab
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('tab') === 'register') {
                // Show register tab
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.form-container').forEach(f => f.classList.remove('active'));
                document.querySelectorAll('.tab')[1].classList.add('active'); // Register tab
                document.getElementById('register-form').classList.add('active');
            }
            
            const phoneInputs = document.querySelectorAll('input[type="tel"]');
            
            phoneInputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    
                    // If starts with 639, add +
                    if (value.startsWith('639')) {
                        value = '+' + value;
                    }
                    // If starts with 9 and length > 9, add 0
                    else if (value.startsWith('9') && value.length === 10) {
                        value = '0' + value;
                    }
                    // If doesn't start with 0, add it
                    else if (value && !value.startsWith('0') && value.length <= 10) {
                        value = '0' + value;
                    }
                    
                    e.target.value = value;
                });
            });
            
            // Detect user location and set country code (optional, non-blocking)
            // Location detection can fail - that's okay, it's not critical
            
            // Background async reCAPTCHA token gathering
            // This loads after user submission, doesn't block form
            function generateRecaptchaToken(action) {
                if (typeof grecaptcha === 'undefined') {
                    console.warn('reCAPTCHA not yet loaded, token will be empty');
                    return;
                }
                
                grecaptcha.ready(function() {
                    grecaptcha.execute('<?php echo RECAPTCHA_SITE_KEY; ?>', {action: action}).then(function(token) {
                        if (action === 'login') {
                            const field = document.getElementById('loginRecaptchaToken');
                            if (field) field.value = token;
                        } else if (action === 'register') {
                            const field = document.getElementById('registerRecaptchaToken');
                            if (field) field.value = token;
                        }
                    });
                });
            }
            
            // Generate tokens asynchronously after forms load
            // These run in background and don't block form submission
            setTimeout(() => generateRecaptchaToken('login'), 500);
            setTimeout(() => generateRecaptchaToken('register'), 1000);
            
            // Forms submit normally without waiting for reCAPTCHA
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    // Token gathering happens in background, allow form to submit
                    // If token is not ready, server will log but still process login
                });
            }
            
            if (registerForm) {
                registerForm.addEventListener('submit', function(e) {
                    // Token gathering happens in background, allow form to submit
                    // If token is not ready, server will log but still process registration
                });
            }
        });
    </script>
</body>
</html>
