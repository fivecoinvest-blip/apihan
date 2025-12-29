<?php
/**
 * Balance Management Functions
 */

/**
 * Get user's current balance
 */
function getUserBalance($userId) {
    $balanceFile = __DIR__ . '/logs/balances.json';
    
    if (file_exists($balanceFile)) {
        $balances = json_decode(file_get_contents($balanceFile), true) ?: [];
        return $balances[$userId] ?? 50;
    }
    
    return 50;
}

/**
 * Set user's balance (called before launching game)
 */
function setUserBalance($userId, $balance) {
    $balanceFile = __DIR__ . '/logs/balances.json';
    
    // Ensure directory exists
    if (!file_exists(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0777, true);
    }
    
    // Load existing balances
    $balances = [];
    if (file_exists($balanceFile)) {
        $content = file_get_contents($balanceFile);
        $balances = json_decode($content, true) ?: [];
    }
    
    // Set balance
    $balances[$userId] = $balance;
    file_put_contents($balanceFile, json_encode($balances, JSON_PRETTY_PRINT));
    
    return $balance;
}
?>
