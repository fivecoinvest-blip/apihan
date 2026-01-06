<?php
/**
 * Fix Phone Numbers Migration Script
 * 
 * This script fixes users who have an extra "9" in their phone numbers.
 * Example: +6399972382805 should be +639972382805
 * 
 * The bug occurred when the old normalizePhoneNumber() function would:
 * 1. See "09972382805" 
 * 2. Remove only the first "0" → "9972382805"
 * 3. Add "+639" → "+6399972382805" (WRONG - extra 9!)
 * 
 * Correct format should be:
 * 1. See "09972382805"
 * 2. Remove "09" (two chars) → "972382805"
 * 3. Add "+639" → "+639972382805" (CORRECT)
 */

require_once 'config.php';

echo "🔧 Phone Number Migration Script\n";
echo "=================================\n\n";

try {
    // Connect to database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "✅ Connected to database\n\n";
    
    // Step 1: Find affected users
    echo "📊 Checking for affected users...\n";
    
    $stmt = $pdo->prepare("
        SELECT id, username, phone 
        FROM users 
        WHERE phone LIKE '%6399%'
        ORDER BY id
    ");
    $stmt->execute();
    $affected_users = $stmt->fetchAll();
    
    $count = count($affected_users);
    
    if ($count === 0) {
        echo "✅ No affected users found! All phone numbers are correct.\n";
        exit(0);
    }
    
    echo "Found {$count} user(s) with incorrect phone format:\n\n";
    
    // Show preview of changes
    echo "Preview of changes:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-5s %-20s %-20s %-20s\n", "ID", "Username", "Current Phone", "Fixed Phone");
    echo str_repeat("-", 80) . "\n";
    
    $fixes = [];
    foreach ($affected_users as $user) {
        $old_phone = $user['phone'];
        
        // Fix: Replace +6399 with +639
        $new_phone = preg_replace('/\+6399(\d{9})/', '+639$1', $old_phone);
        
        // Only add if it actually changed
        if ($old_phone !== $new_phone) {
            $fixes[] = [
                'id' => $user['id'],
                'old' => $old_phone,
                'new' => $new_phone
            ];
            
            printf("%-5s %-20s %-20s %-20s\n", 
                $user['id'], 
                substr($user['username'], 0, 18),
                $old_phone,
                $new_phone
            );
        }
    }
    
    echo str_repeat("-", 80) . "\n";
    echo "\n";
    
    $fix_count = count($fixes);
    echo "Total users to fix: {$fix_count}\n\n";
    
    if ($fix_count === 0) {
        echo "✅ No fixes needed!\n";
        exit(0);
    }
    
    // Ask for confirmation
    echo "⚠️  WARNING: This will update {$fix_count} user record(s) in the database.\n";
    echo "Do you want to proceed? (yes/no): ";
    
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($line) !== 'yes') {
        echo "❌ Migration cancelled.\n";
        exit(0);
    }
    
    echo "\n";
    echo "🔄 Applying fixes...\n\n";
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        $update_stmt = $pdo->prepare("UPDATE users SET phone = :new_phone WHERE id = :id");
        
        $success = 0;
        $failed = 0;
        
        foreach ($fixes as $fix) {
            try {
                $update_stmt->execute([
                    'new_phone' => $fix['new'],
                    'id' => $fix['id']
                ]);
                
                echo "✅ Fixed user ID {$fix['id']}: {$fix['old']} → {$fix['new']}\n";
                $success++;
                
            } catch (Exception $e) {
                echo "❌ Failed to fix user ID {$fix['id']}: " . $e->getMessage() . "\n";
                $failed++;
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo "\n";
        echo str_repeat("=", 80) . "\n";
        echo "✅ Migration completed!\n";
        echo "   - Successfully fixed: {$success} user(s)\n";
        
        if ($failed > 0) {
            echo "   - Failed: {$failed} user(s)\n";
        }
        
        echo "\n";
        echo "📝 Next steps:\n";
        echo "   1. Test user login with both formats (09... and +639...)\n";
        echo "   2. Verify profile page displays correct phone numbers\n";
        echo "   3. Check admin panel shows correct phone numbers\n";
        echo "\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "❌ Transaction failed: " . $e->getMessage() . "\n";
        echo "All changes have been rolled back.\n";
        exit(1);
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "🎉 Phone number migration complete!\n";
