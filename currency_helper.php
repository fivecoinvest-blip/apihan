<?php
/**
 * Currency Helper Functions
 */

/**
 * Get currency symbol by code
 */
function getCurrencySymbol($currency) {
    $symbols = [
        'PHP' => '₱',
        'USD' => '$',
        'GBP' => '£',
        'EUR' => '€',
        'JPY' => '¥',
        'CNY' => '¥',
        'SGD' => 'S$',
        'MYR' => 'RM',
        'THB' => '฿',
        'VND' => '₫',
        'IDR' => 'Rp',
    ];
    
    return $symbols[$currency] ?? $currency . ' ';
}

/**
 * Format currency amount
 */
function formatCurrency($amount, $currency = 'PHP') {
    $symbol = getCurrencySymbol($currency);
    
    // Different formatting for different currencies
    $decimals = in_array($currency, ['JPY', 'VND', 'IDR']) ? 0 : 2;
    
    // Format with decimals then remove trailing zeros
    $formatted = number_format($amount, $decimals, '.', ',');
    
    // Remove trailing zeros after decimal point
    if ($decimals > 0) {
        $formatted = rtrim(rtrim($formatted, '0'), '.');
    }
    
    return $symbol . $formatted;
}

/**
 * Get user currency from session or database
 */
function getUserCurrency($userId = null) {
    if (isset($_SESSION['currency'])) {
        return $_SESSION['currency'];
    }
    
    if ($userId) {
        require_once 'db_helper.php';
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        $stmt = $pdo->prepare("SELECT currency FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['currency'] = $user['currency'];
            return $user['currency'];
        }
    }
    
    return 'PHP'; // Default
}
?>
