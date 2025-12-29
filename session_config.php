<?php
/**
 * Session Configuration
 * Extended session timeout for game playing
 */

// Start session with custom settings
if (session_status() === PHP_SESSION_NONE) {
    // Set session to last 4 hours (14400 seconds)
    ini_set('session.gc_maxlifetime', 14400);
    
    // Set session cookie to expire when browser closes (0) or after 4 hours
    ini_set('session.cookie_lifetime', 14400);
    
    // Ensure session cookies are sent over HTTPS only (if using SSL)
    // Set to 0 for HTTP, 1 for HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
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
 * Check if session has expired (4 hours of inactivity)
 * Call this function at the start of protected pages
 */
function check_session_timeout() {
    $timeout = 14400; // 4 hours in seconds
    
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
?>
