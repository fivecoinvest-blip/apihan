<?php
/**
 * Session Configuration
 * Extended session timeout for game playing
 */

// Start session with custom settings
if (session_status() === PHP_SESSION_NONE) {
    // Set session to last 24 hours (86400 seconds)
    ini_set('session.gc_maxlifetime', 86400);
    
    // Set session cookie to expire after 24 hours
    ini_set('session.cookie_lifetime', 86400);
    
    // Ensure session cookies are sent over HTTPS only (if using SSL)
    // Set to 0 for HTTP, 1 for HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    ini_set('session.cookie_secure', $isHttps ? 1 : 0);
    
    // Prevent JavaScript access to session cookie
    ini_set('session.cookie_httponly', 1);
    
    // Use strict session ID mode
    ini_set('session.use_strict_mode', 1);
    
    // More frequent garbage collection
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
    
    // Start the session
    session_start();
    
    // Check session timeout (24 hours of inactivity)
    if (isset($_SESSION['user_id'])) {
        $timeout = 86400; // 24 hours in seconds
        
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            // Session expired - logout user
            session_unset();
            session_destroy();
            
            // Redirect to login with timeout message
            if (!headers_sent()) {
                session_start();
                $_SESSION['error'] = 'Your session has expired due to inactivity. Please login again.';
                header('Location: /login.php');
                exit;
            }
        }
    }
    
    // Regenerate session ID periodically (every 30 minutes) to prevent fixation
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
}

/**
 * Check if session has expired (24 hours of inactivity)
 * Call this function at the start of protected pages
 */
function check_session_timeout() {
    $timeout = 86400; // 24 hours in seconds
    
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        // Session expired
        session_unset();
        session_destroy();
        header('Location: login.php?error=session_expired');
        exit;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}

/**
 * Keep session alive during gameplay
 * Call this via AJAX periodically from game page
 */
function keep_session_alive() {
    if (isset($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = time();
        return ['success' => true, 'message' => 'Session refreshed'];
    }
    return ['success' => false, 'message' => 'Not logged in'];
}

/**
 * Check if user account is banned or suspended
 * Call this on protected pages after verifying session
 */
function check_user_status($userId, $pdo) {
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && in_array($user['status'], ['banned', 'suspended'])) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['error'] = 'Your account has been ' . $user['status'] . '. Please contact support.';
        header('Location: login.php');
        exit;
    }
}
?>
