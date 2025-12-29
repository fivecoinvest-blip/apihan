<?php
/**
 * Add Currency Support to Users Table
 */
require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "Adding currency column to users table...\n";
    
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'currency'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Add currency column
        $pdo->exec("
            ALTER TABLE users 
            ADD COLUMN currency VARCHAR(10) DEFAULT 'PHP' AFTER country_code
        ");
        echo "✅ Currency column added successfully!\n\n";
    } else {
        echo "✅ Currency column already exists\n\n";
    }
    
    // Update existing users to PHP currency
    $updated = $pdo->exec("UPDATE users SET currency = 'PHP' WHERE currency IS NULL OR currency = ''");
    
    echo "✅ Updated $updated existing users to PHP currency\n\n";
    
    // Show current users
    $stmt = $pdo->query("SELECT id, username, phone, currency, balance FROM users LIMIT 10");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Sample users:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-5s %-20s %-15s %-10s %-10s\n", "ID", "Username", "Phone", "Currency", "Balance");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($users as $user) {
        printf("%-5s %-20s %-15s %-10s %-10.2f\n", 
            $user['id'], 
            $user['username'], 
            substr($user['phone'], 0, 15), 
            $user['currency'],
            $user['balance']
        );
    }
    
    echo "\n✅ Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
