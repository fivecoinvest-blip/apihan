<?php
/**
 * Redis Cache Helper
 * Provides caching functionality for improved performance
 */

class RedisCache {
    private static $instance = null;
    private $redis = null;
    private $enabled = false;
    
    // Cache durations
    const CACHE_30_SECONDS = 30;
    const CACHE_1_MINUTE = 60;
    const CACHE_2_MINUTES = 120;
    const CACHE_5_MINUTES = 300;
    const CACHE_15_MINUTES = 900;
    const CACHE_1_HOUR = 3600;
    const CACHE_1_DAY = 86400;
    
    // Cache priorities (shorter TTL = more critical data)
    const PRIORITY_CRITICAL = 60;    // 1 minute - balance, active sessions
    const PRIORITY_HIGH = 120;       // 2 minutes - user data during gameplay
    const PRIORITY_MEDIUM = 300;     // 5 minutes - general user data
    const PRIORITY_LOW = 900;        // 15 minutes - game lists, static data
    
    private function __construct() {
        try {
            $this->redis = new Redis();
            $this->redis->connect('127.0.0.1', 6379);
            $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
            $this->enabled = true;
            error_log("Redis cache initialized successfully");
        } catch (Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
            $this->enabled = false;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Check if Redis is enabled
     */
    public function isEnabled() {
        return $this->enabled;
    }
    
    /**
     * Get value from cache
     * @param string $key Cache key
     * @return mixed|false Returns false if not found or Redis disabled
     */
    public function get($key) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            $data = $this->redis->get($key);
            if ($data === false) {
                return false;
            }
            
            // Check if it's timestamped data and unwrap it
            if (is_array($data) && isset($data['value'])) {
                return $data['value'];
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("Redis GET error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set value in cache
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (default: 5 minutes)
     * @return bool Success status
     */
    public function set($key, $value, $ttl = self::CACHE_5_MINUTES) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->setex($key, $ttl, $value);
        } catch (Exception $e) {
            error_log("Redis SET error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set value with timestamp for freshness tracking
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     * @return bool Success status
     */
    public function setWithTimestamp($key, $value, $ttl = self::CACHE_5_MINUTES) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            $data = [
                'value' => $value,
                'timestamp' => time(),
                'ttl' => $ttl
            ];
            return $this->redis->setex($key, $ttl, $data);
        } catch (Exception $e) {
            error_log("Redis SET WITH TIMESTAMP error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get value with freshness check
     * @param string $key Cache key
     * @param int $maxAge Maximum age in seconds (default: 60)
     * @return mixed|false Returns false if not found, expired, or too old
     */
    public function getWithFreshness($key, $maxAge = 60) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            $data = $this->redis->get($key);
            if ($data === false) {
                return false;
            }
            
            // Check if it's timestamped data
            if (is_array($data) && isset($data['timestamp'])) {
                $age = time() - $data['timestamp'];
                if ($age > $maxAge) {
                    // Data too old, delete it
                    $this->delete($key);
                    return false;
                }
                return $data['value'];
            }
            
            // Regular data without timestamp
            return $data;
        } catch (Exception $e) {
            error_log("Redis GET WITH FRESHNESS error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete value from cache
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete($key) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->del($key) > 0;
        } catch (Exception $e) {
            error_log("Redis DELETE error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete multiple keys matching pattern
     * @param string $pattern Pattern to match (e.g., "user:*")
     * @return int Number of keys deleted
     */
    public function deletePattern($pattern) {
        if (!$this->enabled) {
            return 0;
        }
        
        try {
            $keys = $this->redis->keys($pattern);
            if (empty($keys)) {
                return 0;
            }
            return $this->redis->del($keys);
        } catch (Exception $e) {
            error_log("Redis DELETE PATTERN error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check if key exists
     * @param string $key Cache key
     * @return bool
     */
    public function exists($key) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->exists($key) > 0;
        } catch (Exception $e) {
            error_log("Redis EXISTS error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Increment a counter
     * @param string $key Cache key
     * @param int $amount Amount to increment (default: 1)
     * @return int|false New value or false on error
     */
    public function increment($key, $amount = 1) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->incrBy($key, $amount);
        } catch (Exception $e) {
            error_log("Redis INCREMENT error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get or set cached value (cache-aside pattern)
     * @param string $key Cache key
     * @param callable $callback Function to execute if cache miss
     * @param int $ttl Time to live in seconds
     * @return mixed Cached or fresh value
     */
    public function remember($key, $callback, $ttl = self::CACHE_5_MINUTES) {
        // Try to get from cache
        $value = $this->get($key);
        
        if ($value !== false) {
            return $value;
        }
        
        // Cache miss - execute callback
        $value = $callback();
        
        // Store in cache
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Flush all cache
     * WARNING: Clears entire Redis database
     * @return bool Success status
     */
    public function flushAll() {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->flushDB();
        } catch (Exception $e) {
            error_log("Redis FLUSH error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get cache statistics
     * @return array|false
     */
    public function getStats() {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            $info = $this->redis->info();
            return [
                'connected' => true,
                'total_keys' => $this->redis->dbSize(),
                'used_memory' => $info['used_memory_human'] ?? 'N/A',
                'hits' => $info['keyspace_hits'] ?? 0,
                'misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => $this->calculateHitRate($info)
            ];
        } catch (Exception $e) {
            error_log("Redis STATS error: " . $e->getMessage());
            return false;
        }
    }
    
    private function calculateHitRate($info) {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        if ($total == 0) {
            return '0%';
        }
        
        return round(($hits / $total) * 100, 2) . '%';
    }
    
    /**
     * Warm up cache with user data
     * Called on login to pre-populate cache
     * @param int $userId User ID
     * @param array $userData User data from database
     * @return bool Success status
     */
    public function warmUserCache($userId, $userData) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            // Cache user balance (critical - 1 minute TTL)
            $this->setWithTimestamp("user:balance:{$userId}", $userData['balance'], self::PRIORITY_CRITICAL);
            
            // Cache full user data (medium priority - 5 minutes TTL)
            $this->setWithTimestamp("user:data:{$userId}", $userData, self::PRIORITY_MEDIUM);
            
            // Cache user currency
            $this->set("user:currency:{$userId}", $userData['currency'], self::PRIORITY_MEDIUM);
            
            error_log("Cache warmed for user {$userId}");
            return true;
        } catch (Exception $e) {
            error_log("Cache warm failed for user {$userId}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Invalidate all user-related cache
     * @param int $userId User ID
     * @return int Number of keys deleted
     */
    public function invalidateUserCache($userId) {
        if (!$this->enabled) {
            return 0;
        }
        
        $deleted = 0;
        $keys = [
            "user:balance:{$userId}",
            "user:data:{$userId}",
            "user:currency:{$userId}",
            "user:stats:{$userId}"
        ];
        
        foreach ($keys as $key) {
            if ($this->delete($key)) {
                $deleted++;
            }
        }
        
        error_log("Invalidated {$deleted} cache keys for user {$userId}");
        return $deleted;
    }
    
    /**
     * Refresh user balance cache from database
     * Write-through pattern: update DB then cache
     * @param int $userId User ID
     * @param float $newBalance New balance value
     * @return bool Success status
     */
    public function refreshBalance($userId, $newBalance) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            // Write-through: cache the new balance immediately
            $this->setWithTimestamp("user:balance:{$userId}", $newBalance, self::PRIORITY_CRITICAL);
            error_log("Balance cache refreshed for user {$userId}: {$newBalance}");
            return true;
        } catch (Exception $e) {
            error_log("Balance cache refresh failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get time-to-live for a key
     * @param string $key Cache key
     * @return int|false TTL in seconds, -1 if no expiry, false if not found
     */
    public function getTTL($key) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            $ttl = $this->redis->ttl($key);
            return $ttl >= 0 ? $ttl : false;
        } catch (Exception $e) {
            error_log("Redis TTL error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get cache age (seconds since cached)
     * @param string $key Cache key
     * @return int|false Age in seconds or false if not found
     */
    public function getCacheAge($key) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            $data = $this->redis->get($key);
            if ($data === false) {
                return false;
            }
            
            // Check if it's timestamped data
            if (is_array($data) && isset($data['timestamp'])) {
                return time() - $data['timestamp'];
            }
            
            return false; // No timestamp available
        } catch (Exception $e) {
            error_log("Redis GET CACHE AGE error: " . $e->getMessage());
            return false;
        }
    }
}
?>
