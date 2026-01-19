<?php
/**
 * CSRF Token Helper
 * Protects forms from Cross-Site Request Forgery attacks
 */

class CSRF {
    /**
     * Generate a new CSRF token
     * @return string The generated token
     */
    public static function generateToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Get the current CSRF token
     * @return string|null The current token or null if not set
     */
    public static function getToken() {
        return $_SESSION['csrf_token'] ?? null;
    }
    
    /**
     * Validate a CSRF token
     * @param string $token The token to validate
     * @return bool True if valid, false otherwise
     */
    public static function validateToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Regenerate CSRF token (call after successful form submission)
     */
    public static function regenerateToken() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    /**
     * Generate HTML hidden input field with CSRF token
     * @return string HTML input field
     */
    public static function getTokenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Verify CSRF token from POST request
     * Dies with error message if invalid
     */
    public static function verifyPostToken() {
        if (!isset($_POST['csrf_token']) || !self::validateToken($_POST['csrf_token'])) {
            http_response_code(403);
            die('CSRF token validation failed. Please refresh the page and try again.');
        }
    }
}
?>
