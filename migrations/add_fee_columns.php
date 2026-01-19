<?php
/**
 * Migration: Add fee columns to payment and withdrawal transactions
 * Run once to add fee tracking columns
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_helper.php';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    echo "Adding fee columns to payment_transactions...\n";
    
    // Check if columns already exist
    $stmt = $pdo->query("SHOW COLUMNS FROM payment_transactions LIKE 'collection_fee'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("
            ALTER TABLE payment_transactions
            ADD COLUMN collection_fee DECIMAL(10,2) DEFAULT 0 COMMENT '1.6% collection fee',
            ADD COLUMN processing_fee DECIMAL(10,2) DEFAULT 0 COMMENT '8 PHP processing fee',
            ADD COLUMN total_fee DECIMAL(10,2) DEFAULT 0 COMMENT 'Total fees (collection + processing)',
            ADD COLUMN net_amount DECIMAL(10,2) DEFAULT 0 COMMENT 'Amount after fees (for withdrawals)'
        ");
        echo "✅ Added fee columns to payment_transactions\n";
    } else {
        echo "⚠️ Fee columns already exist in payment_transactions\n";
    }
    
    echo "Adding fee columns to withdrawal_transactions...\n";
    
    // Check if columns already exist
    $stmt = $pdo->query("SHOW COLUMNS FROM withdrawal_transactions LIKE 'collection_fee'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("
            ALTER TABLE withdrawal_transactions
            ADD COLUMN collection_fee DECIMAL(10,2) DEFAULT 0 COMMENT '1.6% collection fee',
            ADD COLUMN processing_fee DECIMAL(10,2) DEFAULT 0 COMMENT '8 PHP processing fee',
            ADD COLUMN total_fee DECIMAL(10,2) DEFAULT 0 COMMENT 'Total fees (collection + processing)',
            ADD COLUMN net_amount DECIMAL(10,2) DEFAULT 0 COMMENT 'Amount after fees'
        ");
        echo "✅ Added fee columns to withdrawal_transactions\n";
    } else {
        echo "⚠️ Fee columns already exist in withdrawal_transactions\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
