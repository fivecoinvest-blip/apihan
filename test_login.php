<?php
// Test login attempt tracking

require_once 'config.php';
require_once 'db_helper.php';

$phoneOrUsername = '09972382805';
$password = 'wrongpassword123';

// Get database connection
$user = new User();
$loggedUser = $user->login($phoneOrUsername, $password);

echo "=== LOGIN TEST ===\n";
echo "Phone: $phoneOrUsername\n";
echo "Password: $password\n";
echo "Login Result: " . ($loggedUser ? "SUCCESS" : "FAILED") . "\n\n";

if (!$loggedUser) {
    echo "Login failed as expected. Now testing existence check...\n\n";
    
    // Test normalization
    $normalizedPhone = $phoneOrUsername;
    if (preg_match('/^[0-9+]/', $phoneOrUsername)) {
        $normalizedPhone = preg_replace('/[^0-9+]/', '', $phoneOrUsername);
        if (substr($normalizedPhone, 0, 1) == '0') {
            $normalizedPhone = '+639' . substr($normalizedPhone, 1);
        } elseif (substr($normalizedPhone, 0, 1) == '9') {
            $normalizedPhone = '+639' . $normalizedPhone;
        } elseif (substr($normalizedPhone, 0, 4) != '+639') {
            $normalizedPhone = '+639' . $normalizedPhone;
        }
    }
    
    echo "Original input: $phoneOrUsername\n";
    echo "Normalized: $normalizedPhone\n\n";
    
    // Check if user exists
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT id, username, phone, status FROM users 
        WHERE (username = ? OR phone = ? OR phone = ?) AND status = 'active'
    ");
    $stmt->execute([$phoneOrUsername, $phoneOrUsername, $normalizedPhone]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✅ USER FOUND:\n";
        echo "   ID: " . $user['id'] . "\n";
        echo "   Username: " . $user['username'] . "\n";
        echo "   Phone: " . $user['phone'] . "\n";
        echo "   Status: " . $user['status'] . "\n\n";
        echo "Expected behavior: Record failed attempt and show 'Wrong password' error\n";
    } else {
        echo "❌ USER NOT FOUND\n";
        echo "Expected behavior: Redirect to registration\n";
    }
}
?>
