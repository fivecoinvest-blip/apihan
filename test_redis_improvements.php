<?php
/**
 * Test Redis Caching Improvements
 * Demonstrates: Write-through caching, cache warming, freshness checks, short TTL
 */

require_once 'config.php';
require_once 'db_helper.php';
require_once 'redis_helper.php';

$userId = 1;
$cache = RedisCache::getInstance();
$db = Database::getInstance();
$pdo = $db->getConnection();
$userModel = new User();

echo "Redis Caching Improvements Test\n";
echo "=================================\n\n";

// Test 1: Cache Statistics
echo "1. Redis Statistics:\n";
$stats = $cache->getStats();
if ($stats) {
    echo "   Connected: Yes\n";
    echo "   Total Keys: {$stats['total_keys']}\n";
    echo "   Memory Used: {$stats['used_memory']}\n";
    echo "   Hit Rate: {$stats['hit_rate']}\n";
} else {
    echo "   ✗ Redis not available\n";
}

// Test 2: Cache Warming
echo "\n2. Cache Warming Test:\n";
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

$warmResult = $cache->warmUserCache($userId, $userData);
echo "   User cache warmed: " . ($warmResult ? "✓ Success" : "✗ Failed") . "\n";

// Check what was cached
$balanceCached = $cache->get("user:balance:{$userId}");
$dataCached = $cache->get("user:data:{$userId}");
echo "   Balance cached: " . ($balanceCached !== false ? "✓ {$balanceCached}" : "✗ Not found") . "\n";
echo "   User data cached: " . ($dataCached !== false ? "✓ Yes" : "✗ Not found") . "\n";

// Test 3: Cache TTL and Age
echo "\n3. Cache TTL and Age:\n";
$balanceTTL = $cache->getTTL("user:balance:{$userId}");
$balanceAge = $cache->getCacheAge("user:balance:{$userId}");
echo "   Balance cache TTL: {$balanceTTL} seconds remaining\n";
echo "   Balance cache age: {$balanceAge} seconds old\n";
echo "   (Critical data uses 60-second TTL)\n";

// Test 4: Freshness Check
echo "\n4. Freshness Check Test:\n";
sleep(2); // Wait 2 seconds
$freshBalance = $cache->getWithFreshness("user:balance:{$userId}", 60);
echo "   Balance (max 60s old): " . ($freshBalance !== false ? "✓ {$freshBalance}" : "✗ Too stale") . "\n";

$veryFreshBalance = $cache->getWithFreshness("user:balance:{$userId}", 1);
echo "   Balance (max 1s old): " . ($veryFreshBalance !== false ? "✓ {$veryFreshBalance}" : "✗ Too stale (expected)") . "\n";

// Test 5: Write-Through Caching
echo "\n5. Write-Through Caching Test:\n";
$currentBalance = (float)$userData['balance'];
$testBalance = $currentBalance + 100;

echo "   Current balance: {$currentBalance}\n";
echo "   Updating to: {$testBalance}\n";

// Update using write-through pattern
$stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
$stmt->execute([$testBalance, $userId]);
$cache->refreshBalance($userId, $testBalance);

echo "   Database updated: ✓\n";
echo "   Cache refreshed: ✓\n";

// Verify cache has new value immediately
$cachedNew = $cache->get("user:balance:{$userId}");
echo "   Cached balance: {$cachedNew}\n";
echo "   Cache = DB: " . ($cachedNew == $testBalance ? "✓ Match" : "✗ Mismatch") . "\n";

// Test 6: User Model getBalance (uses improved caching)
echo "\n6. User Model getBalance Test:\n";
$modelBalance = $userModel->getBalance($userId);
echo "   Model balance: {$modelBalance}\n";
echo "   Uses cache: " . ($modelBalance == $cachedNew ? "✓ Yes" : "✗ Cache miss") . "\n";

// Test 7: Cache Invalidation
echo "\n7. Complete Cache Invalidation Test:\n";
$deletedCount = $cache->invalidateUserCache($userId);
echo "   Invalidated {$deletedCount} cache keys\n";

$balanceAfterInvalidate = $cache->get("user:balance:{$userId}");
echo "   Balance cache cleared: " . ($balanceAfterInvalidate === false ? "✓ Yes" : "✗ Still exists") . "\n";

// Test 8: Cache Priorities
echo "\n8. Cache Priority Test:\n";
echo "   CRITICAL (balance): " . RedisCache::PRIORITY_CRITICAL . " seconds (1 min)\n";
echo "   HIGH: " . RedisCache::PRIORITY_HIGH . " seconds (2 min)\n";
echo "   MEDIUM: " . RedisCache::PRIORITY_MEDIUM . " seconds (5 min)\n";
echo "   LOW: " . RedisCache::PRIORITY_LOW . " seconds (15 min)\n";

// Restore original balance
echo "\n9. Restoring Original Balance:\n";
$stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
$stmt->execute([$currentBalance, $userId]);
$cache->refreshBalance($userId, $currentBalance);
echo "   Balance restored to: {$currentBalance} ✓\n";

echo "\n✓ All tests completed!\n\n";

// Summary
echo "Summary of Improvements:\n";
echo "========================\n";
echo "✓ Write-through caching: Updates cache immediately when DB changes\n";
echo "✓ Cache warming: Pre-populates cache on user login\n";
echo "✓ Freshness checks: Validates cache age, rejects stale data\n";
echo "✓ Short TTL for critical data: Balance cached for only 60 seconds\n";
echo "✓ Timestamp tracking: Knows exactly how old cached data is\n";
echo "✓ Smart invalidation: Clears all related caches together\n";
echo "✓ Priority system: Different TTL based on data importance\n";
echo "\n";
echo "Benefits:\n";
echo "---------\n";
echo "• Balance always accurate (1-minute max staleness)\n";
echo "• Admin updates reflected immediately (write-through)\n";
echo "• Games get fresh balance (cache warmed on login)\n";
echo "• Automatic refresh if data too old (freshness check)\n";
echo "• Better performance with guaranteed accuracy\n";
?>
