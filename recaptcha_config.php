<?php
/**
 * Google reCAPTCHA v3 Configuration
 * Get your keys from: https://www.google.com/recaptcha/admin
 */

// reCAPTCHA v3 Keys
define('RECAPTCHA_SITE_KEY', '6LcYT00sAAAAAImhZHPhGNe6peEAJKCK6B5igNSy');  // Replace with your actual site key
define('RECAPTCHA_SECRET_KEY', '6LcYT00sAAAAAPO1EcO8ajantebQ1rmiTOq5bqrh');  // Replace with your actual secret key

// reCAPTCHA v3 score threshold (0.0 to 1.0)
// 0.0 = likely bot, 1.0 = likely human
// Recommended: 0.5 for login/register forms
define('RECAPTCHA_THRESHOLD', 0.5);

/**
 * Verify reCAPTCHA v3 token
 * @param string $token The reCAPTCHA token from the form
 * @param string $action The action name (login, register, etc.)
 * @return array ['success' => bool, 'score' => float, 'error' => string]
 */
function verifyRecaptcha($token, $action = 'submit') {
    if (empty($token)) {
        return [
            'success' => false,
            'score' => 0.0,
            'error' => 'No reCAPTCHA token provided'
        ];
    }
    
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return [
            'success' => false,
            'score' => 0.0,
            'error' => 'Failed to contact reCAPTCHA server'
        ];
    }
    
    $result = json_decode($response, true);
    
    if (!$result['success']) {
        return [
            'success' => false,
            'score' => 0.0,
            'error' => 'reCAPTCHA verification failed: ' . implode(', ', $result['error-codes'] ?? ['unknown'])
        ];
    }
    
    // Check action match
    if (isset($result['action']) && $result['action'] !== $action) {
        return [
            'success' => false,
            'score' => $result['score'] ?? 0.0,
            'error' => 'Action mismatch'
        ];
    }
    
    // Check score threshold
    $score = $result['score'] ?? 0.0;
    if ($score < RECAPTCHA_THRESHOLD) {
        return [
            'success' => false,
            'score' => $score,
            'error' => 'reCAPTCHA score too low (possible bot)'
        ];
    }
    
    return [
        'success' => true,
        'score' => $score,
        'error' => ''
    ];
}

/**
 * Class wrapper for reCAPTCHA verification
 * Provides object-oriented interface
 */
class RecaptchaVerifier {
    /**
     * Verify a reCAPTCHA token
     * @param string $token The reCAPTCHA token
     * @param string $action The action (login, register, etc.)
     * @return bool True if verification passes (score >= threshold)
     */
    public static function verify($token, $action = 'submit') {
        if (empty($token)) {
            return false;
        }
        
        $result = verifyRecaptcha($token, $action);
        return $result['success'] === true;
    }
    
    /**
     * Get detailed verification result
     * @param string $token The reCAPTCHA token
     * @param string $action The action (login, register, etc.)
     * @return array ['success' => bool, 'score' => float, 'error' => string]
     */
    public static function getResult($token, $action = 'submit') {
        if (empty($token)) {
            return [
                'success' => false,
                'score' => 0.0,
                'error' => 'No token provided'
            ];
        }
        
        return verifyRecaptcha($token, $action);
    }
}
?>
