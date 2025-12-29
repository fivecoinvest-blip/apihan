<?php
/**
 * Site Settings Helper with Redis Caching
 */

class SiteSettings {
    private static $settings = null;
    private static $cache = null;
    
    /**
     * Get Redis cache instance
     */
    private static function getCache() {
        if (self::$cache === null) {
            require_once __DIR__ . '/redis_helper.php';
            self::$cache = RedisCache::getInstance();
        }
        return self::$cache;
    }
    
    /**
     * Load all settings from cache or database
     */
    public static function load() {
        // Check memory cache first
        if (self::$settings !== null) {
            return self::$settings;
        }
        
        // Check Redis cache
        $cache = self::getCache();
        $cacheKey = 'site:settings:all';
        $cachedSettings = $cache->get($cacheKey);
        
        if ($cachedSettings !== false) {
            self::$settings = $cachedSettings;
            return self::$settings;
        }
        
        // Load from database
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            self::$settings = [];
            foreach ($rows as $row) {
                self::$settings[$row['setting_key']] = $row['setting_value'];
            }
            
            // Cache for 5 minutes
            $cache->set($cacheKey, self::$settings, RedisCache::PRIORITY_MEDIUM);
            
        } catch (Exception $e) {
            // If table doesn't exist, use defaults
            self::$settings = self::getDefaults();
        }
        
        return self::$settings;
    }
    
    /**
     * Get a specific setting value
     */
    public static function get($key, $default = '') {
        $settings = self::load();
        return $settings[$key] ?? $default;
    }
    
    /**
     * Update a setting value
     */
    public static function set($key, $value) {
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
            $stmt = $pdo->prepare("
                INSERT INTO site_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$key, $value, $value]);
            
            // Invalidate caches (both memory and Redis)
            self::clearCache();
            
            return true;
            
        } catch (Exception $e) {
            error_log("Settings update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear all settings caches
     */
    public static function clearCache() {
        // Clear memory cache
        self::$settings = null;
        
        // Clear Redis cache
        $cache = self::getCache();
        $cache->delete('site:settings:all');
        
        error_log("Settings cache cleared");
    }
    
    /**
     * Get default settings
     */
    private static function getDefaults() {
        return [
            'casino_name' => 'Casino PHP',
            'casino_tagline' => 'Play & Win Big!',
            'default_currency' => 'PHP',
            'logo_path' => 'images/logo.png',
            'favicon_path' => 'images/favicon.ico',
            'theme_color' => '#6366f1',
            'starting_balance' => '100.00',
            'min_bet' => '1.00',
            'max_bet' => '10000.00',
            'maintenance_mode' => '0',
            'support_email' => 'support@casino.com',
            'support_phone' => '+639123456789',
            'facebook_url' => '',
            'twitter_url' => '',
            'instagram_url' => '',
        ];
    }
}
?>
