<?php
/**
 * Setup Admin Account
 */
require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Create admin_users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            role VARCHAR(50) DEFAULT 'admin',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            INDEX idx_username (username),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✅ Admin users table created successfully!\n\n";

    // Create default admin account
    $defaultUsername = 'admin';
    $defaultPassword = 'admin123'; // Change this after first login!
    $hashedPassword = password_hash($defaultPassword, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("
        INSERT INTO admin_users (username, password, email, role) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            password = VALUES(password)
    ");
    
    $stmt->execute([
        $defaultUsername,
        $hashedPassword,
        'admin@casino.com',
        'super_admin'
    ]);

    echo "✅ Admin account created successfully!\n\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "   🔐 ADMIN CREDENTIALS\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "   Username: $defaultUsername\n";
    echo "   Password: $defaultPassword\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "⚠️  IMPORTANT: Change the password after first login!\n\n";
    echo "Access admin panel at:\n";
    echo "https://grizzly-inviting-peacock.ngrok-free.app/apihan/admin.php\n\n";

} catch (PDOException $e) {
    echo "❌ Setup failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
