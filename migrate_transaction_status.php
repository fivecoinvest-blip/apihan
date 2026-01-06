<?php
/**
 * Add status column to transactions table for deposit/withdrawal tracking
 */

require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "Adding status column to transactions table...\n";
    
    // Check if status column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'status'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $pdo->exec("
            ALTER TABLE transactions 
            ADD COLUMN status VARCHAR(20) DEFAULT 'completed' 
            AFTER description
        ");
        echo "✓ Status column added successfully!\n";
    } else {
        echo "✓ Status column already exists\n";
    }
    
    // Update existing transactions to have 'completed' status
    $pdo->exec("UPDATE transactions SET status = 'completed' WHERE status IS NULL OR status = ''");
    
    echo "\n✓ Migration completed successfully!\n";
    echo "\nStatus values:\n";
    echo "- 'completed' - Transaction processed\n";
    echo "- 'pending' - Awaiting admin approval (deposits/withdrawals)\n";
    echo "- 'failed' - Transaction failed\n";
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
