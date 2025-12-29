<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'db_helper.php';

$user = new User();

// Handle success/error messages from session
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
unset($_SESSION['error'], $_SESSION['success']);

// Handle Login
if (isset($_POST['login'])) {
    $phoneOrUsername = $_POST['phone'];
    $password = $_POST['password'];
    
    $loggedUser = $user->login($phoneOrUsername, $password);
    
    if ($loggedUser) {
        $_SESSION['user_id'] = $loggedUser['id'];
        $_SESSION['username'] = $loggedUser['username'];
        $_SESSION['phone'] = $loggedUser['phone'];
        
        // Track user login activity
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        // Get user IP address
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        
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
        
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['error'] = 'Invalid phone number/username or password';
        header('Location: login.php');
        exit;
    }
}

// Handle Registration
if (isset($_POST['register'])) {
    $phone = $_POST['reg_phone'];
    $password = $_POST['reg_password'];
    $confirmPassword = $_POST['reg_confirm_password'];
    
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
            $_SESSION['success'] = "Registration successful! Your username: <strong>{$result['username']}</strong>. Please login.";
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
    <title>Casino - Login</title>
    <link rel="manifest" href="manifest.json">
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
                <h1>🎰 Casino</h1>
                <p>Play & Win Big</p>
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
            <form method="POST">
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
            <form method="POST">
                <div class="form-group">
                    <label>Phone Number 🇵🇭</label>
                    <input type="tel" name="reg_phone" required placeholder="09XXXXXXXXX" 
                           pattern="^(09\d{9}|\+639\d{9})$" maxlength="13">
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
            
            // Detect user location and set country code
            fetch('https://ipapi.co/json/')
                .then(res => res.json())
                .then(data => {
                    console.log('User location:', data.country_name);
                    // You can use data.country_code to change default prefix
                })
                .catch(err => console.log('Location detection failed'));
        });
    </script>
</body>
</html>
