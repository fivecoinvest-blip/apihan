<?php
/**
 * Set all users to PHP currency (Philippine Peso)
 * Run this script to update existing users
 */

require_once 'config.php';

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Update all users to PHP currency
    $stmt = $db->prepare("UPDATE users SET currency = 'PHP'");
    $stmt->execute();
    $count = $stmt->rowCount();
    
    // Update user_preferences table if it exists
    $stmt = $db->prepare("UPDATE user_preferences SET currency = 'PHP'");
    $stmt->execute();
    
    // Also update the default column value for future users
    $db->exec("ALTER TABLE users MODIFY COLUMN currency VARCHAR(3) DEFAULT 'PHP'");
    
    // Check if user_preferences exists and update default
    $tableExists = $db->query("SHOW TABLES LIKE 'user_preferences'")->fetch();
    if ($tableExists) {
        $db->exec("ALTER TABLE user_preferences MODIFY COLUMN currency VARCHAR(3) DEFAULT 'PHP'");
    }
    
    echo "✅ Successfully updated $count users to PHP currency\n";
    echo "✅ Database defaults set to PHP for all new users\n";
    echo "✅ All users will now use Philippine Peso (₱)\n";
    
} catch (PDOException $e) {
    die("❌ Error: " . $e->getMessage() . "\n");
}
?>
