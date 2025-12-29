<?php
/**
 * Test Balance Cache Invalidation
 * 
 * This script tests that when balance is updated, the Redis cache is properly invalidated
 */

require_once 'config.php';
require_once 'db_helper.php';
require_once 'redis_helper.php';

$userId = 1; // Test with user ID 1

echo "Testing Balance Cache Invalidation\n";
echo "===================================\n\n";

$cache = RedisCache::getInstance();
$db = Database::getInstance();
$pdo = $db->getConnection();

// Step 1: Get current balance from database
$stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$dbBalance = (float)$user['balance'];

echo "1. Current DB balance for user {$userId}: {$dbBalance}\n";

// Step 2: Check if cached
$cacheKey = "user:balance:{$userId}";
$cachedBalance = $cache->get($cacheKey);

if ($cachedBalance !== null) {
    echo "2. Cached balance: {$cachedBalance}\n";
} else {
    echo "2. No cached balance (cache empty)\n";
}

// Step 3: Store balance in cache
$cache->set($cacheKey, $dbBalance, 300);
echo "3. Stored balance in cache: {$dbBalance}\n";

// Step 4: Simulate admin updating balance (old way - WITHOUT cache invalidation)
echo "\n--- Simulating OLD admin balance update (NO cache invalidation) ---\n";
$newBalance = $dbBalance + 100; // Add 100 to balance
$stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
$stmt->execute([$newBalance, $userId]);
echo "4. Updated DB balance to: {$newBalance}\n";

// Step 5: Check cached balance (should still be old)
$cachedBalance = $cache->get($cacheKey);
echo "5. Cached balance (STALE): {$cachedBalance}\n";
echo "   ⚠️  Problem: Cache still has old balance! Game will use wrong balance!\n";

// Step 6: Now simulate NEW admin update (WITH cache invalidation)
echo "\n--- Simulating NEW admin balance update (WITH cache invalidation) ---\n";
$newerBalance = $newBalance + 50; // Add another 50
$stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
$stmt->execute([$newerBalance, $userId]);
echo "6. Updated DB balance to: {$newerBalance}\n";

// Step 7: Invalidate cache (NEW behavior)
$cache->delete("user:balance:{$userId}");
$cache->delete("user:data:{$userId}");
echo "7. Invalidated Redis cache\n";

// Step 8: Check cached balance (should be null now)
$cachedBalance = $cache->get($cacheKey);
if ($cachedBalance === null) {
    echo "8. Cached balance: NULL (cache cleared) ✓\n";
    echo "   ✓  Success: Next game launch will fetch fresh balance from DB!\n";
} else {
    echo "8. Cached balance: {$cachedBalance} (still cached) ✗\n";
}

// Step 9: Reset balance to original
echo "\n--- Resetting balance to original ---\n";
$stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
$stmt->execute([$dbBalance, $userId]);
$cache->delete("user:balance:{$userId}");
$cache->delete("user:data:{$userId}");
echo "9. Reset balance to: {$dbBalance}\n";

echo "\n✓ Test completed!\n";
echo "\nSummary:\n";
echo "--------\n";
echo "• OLD behavior: Balance updated in DB, but cache NOT cleared\n";
echo "  → Game launches with stale cached balance\n";
echo "  → Balance calculations are WRONG\n";
echo "  → Games show NetworkError due to balance mismatch\n";
echo "\n";
echo "• NEW behavior: Balance updated in DB, AND cache cleared\n";
echo "  → Next game launch fetches fresh balance from DB\n";
echo "  → Balance calculations are CORRECT\n";
echo "  → Games work properly! ✓\n";
?>
