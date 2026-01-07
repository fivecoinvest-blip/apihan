<?php
/**
 * Helper Functions for SoftAPI Integration
 */

/**
 * Encrypt payload using AES-256-ECB encryption
 * 
 * @param array $data The data to encrypt
 * @param string $key The 32-byte encryption key
 * @return string Base64 encoded encrypted data
 * @throws Exception If key length is not 32 bytes
 */
function ENCRYPT_PAYLOAD_ECB(array $data, string $key): string {
    if (strlen($key) !== 32) {
        throw new Exception("Encryption key must be exactly 32 bytes long");
    }
    
    // Convert data to JSON
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    
    // Encrypt using AES-256-ECB
    $encrypted = openssl_encrypt($json, "AES-256-ECB", $key, OPENSSL_RAW_DATA);
    
    // Return base64 encoded result
    return base64_encode($encrypted);
}

/**
 * Decrypt payload using AES-256-ECB decryption
 * 
 * @param string $encryptedData Base64 encoded encrypted data
 * @param string $key The 32-byte decryption key
 * @return array Decrypted data as array
 * @throws Exception If decryption fails
 */
function DECRYPT_PAYLOAD_ECB(string $encryptedData, string $key): array {
    if (strlen($key) !== 32) {
        throw new Exception("Decryption key must be exactly 32 bytes long");
    }
    
    // Decode from base64
    $decoded = base64_decode($encryptedData);
    
    // Decrypt using AES-256-ECB
    $decrypted = openssl_decrypt($decoded, "AES-256-ECB", $key, OPENSSL_RAW_DATA);
    
    if ($decrypted === false) {
        throw new Exception("Decryption failed");
    }
    
    // Parse JSON and return
    return json_decode($decrypted, true);
}

/**
 * Send HTTP GET request using cURL
 * 
 * @param string $url The URL to send request to
 * @return string Response body
 */
function sendGetRequest(string $url): string {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL Error: " . $error);
    }
    
    curl_close($ch);
    return $response;
}

/**
 * Log message to file
 * 
 * @param string $message Message to log
 * @param string $type Log type (info, error, debug)
 */
function logMessage(string $message, string $type = 'info'): void {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/api_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Generate unique game session ID
 * 
 * @return string Unique game UID
 */
function generateGameUID(): string {
    return time() . rand(100, 999);
}

/**
 * Validate required parameters
 * 
 * @param array $data Data to validate
 * @param array $required Required keys
 * @return bool True if all required keys exist
 */
function validateRequired(array $data, array $required): bool {
    foreach ($required as $key) {
        if (!isset($data[$key]) || $data[$key] === '') {
            return false;
        }
    }
    return true;
}

?>
