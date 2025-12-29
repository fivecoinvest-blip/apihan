<?php
/**
 * Test Admin Login - Debugging Script
 */
require_once 'session_config.php';
require_once 'config.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "<h2>Admin Login Test</h2>";

// Test 1: Check admin user exists
echo "<h3>Test 1: Admin User Exists</h3>";
$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? AND is_active = 1");
$stmt->execute(['admin']);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($admin) {
    echo "✅ Admin user found<br>";
    echo "Username: " . $admin['username'] . "<br>";
    echo "Role: " . $admin['role'] . "<br>";
    echo "Active: " . $admin['is_active'] . "<br>";
} else {
    echo "❌ Admin user NOT found<br>";
    exit;
}

// Test 2: Verify password
echo "<h3>Test 2: Password Verification</h3>";
$testPassword = 'admin123';
if (password_verify($testPassword, $admin['password'])) {
    echo "✅ Password 'admin123' is CORRECT<br>";
} else {
    echo "❌ Password 'admin123' is WRONG<br>";
    echo "Hash in DB: " . $admin['password'] . "<br>";
}

// Test 3: Simulate login
echo "<h3>Test 3: Session Test</h3>";
$_SESSION['test_admin_logged_in'] = true;
$_SESSION['test_admin_id'] = $admin['id'];
$_SESSION['test_admin_username'] = $admin['username'];

if (isset($_SESSION['test_admin_logged_in'])) {
    echo "✅ Session variables can be set<br>";
    echo "Session ID: " . session_id() . "<br>";
} else {
    echo "❌ Session variables NOT working<br>";
}

echo "<h3>Test 4: Session Variables</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<hr>";
echo "<h3>Actual Login Test</h3>";
echo '<form method="POST" action="admin.php">';
echo '<input type="text" name="username" value="admin" placeholder="Username"><br><br>';
echo '<input type="password" name="password" value="admin123" placeholder="Password"><br><br>';
echo '<button type="submit" name="admin_login">Test Login</button>';
echo '</form>';
?>
