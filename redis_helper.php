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
    const CACHE_1_MINUTE = 60;
    const CACHE_5_MINUTES = 300;
    const CACHE_15_MINUTES = 900;
    const CACHE_1_HOUR = 3600;
    const CACHE_1_DAY = 86400;
    
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
            return $this->redis->get($key);
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
}
?>
