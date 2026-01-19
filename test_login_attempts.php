<?php
/**
 * Login Attempt Test & Debug Tool
 * Tests the login attempt tracking functionality
 */

session_start();
require_once 'config.php';
require_once 'db_helper.php';
require_once 'csrf_helper.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Login Test Tool</title>
    <style>
        body { font-family: monospace; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .test { margin: 20px 0; padding: 15px; border: 1px solid #ddd; background: #f9f9f9; }
        .test h3 { margin-top: 0; color: #333; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f4f4f4; font-weight: bold; }
    </style>
</head>
<body>
<div class=\"container\">
<h1>🧪 Login Attempt Test & Debug Tool</h1>";

// Test 1: Check database connection
echo "<div class='test info'>
<h3>Test 1: Database Connection</h3>";
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $result = $pdo->query("SELECT 1")->fetch();
    echo "<p class='success'>✅ Database connection: OK</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Database connection: FAILED - " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 2: Check user exists
echo "<div class='test info'>
<h3>Test 2: Check User 09972382805 Exists</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT id, username, phone, status FROM users 
        WHERE (username = '09972382805' OR phone = '09972382805' OR phone = '+639972382805') 
        AND status = 'active'
    ");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<p class='success'>✅ User found:</p>";
        echo "<table>
            <tr><th>Field</th><th>Value</th></tr>
            <tr><td>ID</td><td>" . $user['id'] . "</td></tr>
            <tr><td>Username</td><td>" . $user['username'] . "</td></tr>
            <tr><td>Phone</td><td>" . $user['phone'] . "</td></tr>
            <tr><td>Status</td><td>" . $user['status'] . "</td></tr>
        </table>";
    } else {
        echo "<p class='error'>❌ User not found</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 3: Check login_attempts table
echo "<div class='test info'>
<h3>Test 3: Login Attempts Table</h3>";
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM login_attempts");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "<p class='success'>✅ Table exists - Total attempts: " . $result['count'] . "</p>";
    
    if ($result['count'] > 0) {
        $stmt = $pdo->prepare("SELECT ip_address, username_or_phone, attempt_time FROM login_attempts ORDER BY attempt_time DESC LIMIT 5");
        $stmt->execute();
        $attempts = $stmt->fetchAll();
        
        echo "<p><strong>Recent attempts:</strong></p>";
        echo "<table>
            <tr><th>IP Address</th><th>Phone/Username</th><th>Attempt Time</th></tr>";
        foreach ($attempts as $attempt) {
            echo "<tr><td>" . $attempt['ip_address'] . "</td><td>" . $attempt['username_or_phone'] . "</td><td>" . $attempt['attempt_time'] . "</td></tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 4: Test password verification
echo "<div class='test info'>
<h3>Test 4: Password Verification</h3>";
if ($user) {
    $testPassword = 'wrongpassword';
    $correctPassword = 'password123'; // Assuming default password, adjust as needed
    
    echo "<p>Testing password verification for user ID " . $user['id'] . "</p>";
    
    // Get the actual password hash from database
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $dbUser = $stmt->fetch();
    
    if ($dbUser) {
        $wrongMatch = password_verify($testPassword, $dbUser['password']);
        echo "<p>Wrong password test ('$testPassword'): " . ($wrongMatch ? "❌ MATCHED (unexpected)" : "✅ NO MATCH (expected)") . "</p>";
        
        // Try a common password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $result = $stmt->fetch();
        echo "<p class='info'>Note: Actual password hash cannot be displayed for security reasons.</p>";
    }
} else {
    echo "<p class='error'>❌ Cannot test - user not found</p>";
}
echo "</div>";

// Test 5: Check CSRF session
echo "<div class='test info'>
<h3>Test 5: CSRF Token Session</h3>";
$csrf_token = CSRF::getToken();
if ($csrf_token) {
    echo "<p class='success'>✅ CSRF token in session: " . substr($csrf_token, 0, 10) . "...</p>";
} else {
    echo "<p class='info'>ℹ️  CSRF token not yet generated (will be created on page load)</p>";
    CSRF::generateToken();
    echo "<p class='success'>✅ CSRF token now generated: " . substr(CSRF::getToken(), 0, 10) . "...</p>";
}
echo "</div>";

// Test 6: Simulated login test
echo "<div class='test info'>
<h3>Test 6: Simulated Login Flow</h3>";
if ($user) {
    echo "<p><strong>Testing login flow for user: " . $user['username'] . "</strong></p>";
    
    // Get IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ipList[0]);
    }
    echo "<p>Current IP: $ip</p>";
    
    // Check current failed attempts for this IP
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM login_attempts WHERE ip_address = ? AND DATE(attempt_time) = ?");
    $stmt->execute([$ip, $today]);
    $attemptCount = $stmt->fetch()['count'];
    
    echo "<p>Failed login attempts from this IP today: <strong>$attemptCount</strong></p>";
    
    if ($attemptCount >= 3) {
        echo "<p class='error'>⚠️  IP is BLOCKED - Too many failed attempts</p>";
    } else {
        echo "<p class='success'>✅ IP is NOT blocked - Can attempt " . (3 - $attemptCount) . " more times</p>";
    }
    
    echo "<hr>";
    echo "<p><strong>What happens when login fails:</strong></p>";
    echo "<ol>
        <li>Check IP has not exceeded 3 failed attempts today</li>
        <li>Attempt authentication with credentials</li>
        <li>If fails: Record attempt in login_attempts table</li>
        <li>Show error: 'Wrong password' or 'Too many attempts'</li>
        <li>Redirect to login.php with error in session</li>
    </ol>";
}
echo "</div>";

// Test 7: Manual test instructions
echo "<div class='test info'>
<h3>Test 7: How to Test Login Attempts</h3>";
echo "<p><strong>Step 1: Open your browser</strong></p>
<p>Go to: <a href='http://31.97.107.21/login.php' target='_blank'>http://31.97.107.21/login.php</a></p>

<p><strong>Step 2: Try login with wrong password 3 times</strong></p>
<ul>
<li>Phone: 09972382805</li>
<li>Password: wrongpassword (or any wrong password)</li>
<li>Expected result on 1st-2nd attempt: \"Wrong password\" error</li>
<li>Expected result on 3rd attempt: \"Wrong password. 0 attempts remaining before lockout.\"</li>
<li>Expected result on 4th attempt: \"Too many failed login attempts...\"</li>
</ul>

<p><strong>Step 3: Refresh this page to see updated attempt count</strong></p>";
echo "</div>";

echo "</div></body></html>";
?>
