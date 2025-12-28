<?php
/**
 * Create Site Settings Table
 */

require_once 'config.php';

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Create site_settings table
    $db->exec("
        CREATE TABLE IF NOT EXISTS site_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) UNIQUE NOT NULL,
            setting_value TEXT,
            setting_type VARCHAR(20) DEFAULT 'text',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Insert default settings
    $defaultSettings = [
        ['casino_name', 'Casino PHP', 'text'],
        ['casino_tagline', 'Play & Win Big!', 'text'],
        ['default_currency', 'PHP', 'text'],
        ['logo_path', 'images/logo.png', 'text'],
        ['favicon_path', 'images/favicon.ico', 'text'],
        ['theme_color', '#6366f1', 'color'],
        ['starting_balance', '100.00', 'number'],
        ['min_bet', '1.00', 'number'],
        ['max_bet', '10000.00', 'number'],
        ['maintenance_mode', '0', 'boolean'],
        ['support_email', 'support@casino.com', 'email'],
        ['support_phone', '+639123456789', 'text'],
        ['facebook_url', '', 'text'],
        ['twitter_url', '', 'text'],
        ['instagram_url', '', 'text'],
    ];
    
    $stmt = $db->prepare("
        INSERT INTO site_settings (setting_key, setting_value, setting_type) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE setting_key=setting_key
    ");
    
    foreach ($defaultSettings as $setting) {
        $stmt->execute($setting);
    }
    
    echo "✅ Site settings table created successfully!\n";
    echo "✅ Default settings inserted\n";
    
} catch (PDOException $e) {
    die("❌ Error: " . $e->getMessage() . "\n");
}
?>
