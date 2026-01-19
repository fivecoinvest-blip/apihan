<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'db_helper.php';
require_once 'csrf_helper.php';
require_once 'mfa_helper.php';
require_once 'geo_helper.php';

// Restrict admin access to PH IPs only
// Temporarily disabled for debugging - re-enable once working
// GeoHelper::enforceCountry('PH');

$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['error']);

// Require pending admin session
if (empty($_SESSION['pending_admin_id'])) {
    header('Location: admin.php');
    exit;
}

CSRF::generateToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        CSRF::regenerateToken();
        $_SESSION['error'] = 'Session expired. Please try again.';
        header('Location: admin_mfa_verify.php');
        exit;
    }

    $code = trim($_POST['mfa_code'] ?? '');
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    // Safely attempt to read MFA secret; handle missing columns gracefully
    $admin = null;
    try {
        $stmt = $pdo->prepare('SELECT id, username, role, mfa_secret FROM admin_users WHERE id = ? AND is_active = 1');
        $stmt->execute([$_SESSION['pending_admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $admin = null;
    }

    if (!$admin || empty($admin['mfa_secret'])) {
        $_SESSION['error'] = 'MFA is not configured for this account.';
        header('Location: admin.php');
        exit;
    }

    if (MFAHelper::verifyCode($admin['mfa_secret'], $code)) {
        // Complete login
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];

        unset($_SESSION['pending_admin_id'], $_SESSION['pending_admin_username']);

        // Update last login
        $pdo->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?')->execute([$admin['id']]);

        header('Location: admin.php');
        exit;
    } else {
        $_SESSION['error'] = 'Invalid verification code.';
        header('Location: admin_mfa_verify.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin MFA Verification</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 15px; }
        .box { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 32px; border-radius: 18px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); width: 100%; max-width: 420px; }
        h1 { text-align: center; color: #667eea; font-size: 24px; margin-bottom: 12px; }
        p { color: #666; text-align: center; margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #333; font-weight: 600; font-size: 14px; }
        input { width: 100%; padding: 14px 16px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 16px; transition: all 0.3s; background: white; }
        input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        button { width: 100%; padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; margin-top: 10px; }
        button:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4); }
        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; line-height: 1.5; }
        .alert-error { background: #fee2e2; color: #dc2626; border-left: 4px solid #dc2626; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🔑 Verify Code</h1>
        <p>Enter the 6-digit code from Google Authenticator.</p>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <?php echo CSRF::getTokenField(); ?>
            <label>Authenticator Code</label>
            <input type="text" name="mfa_code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required pattern="^\d{6}$">
            <button type="submit">Verify & Continue</button>
        </form>
    </div>
    </body>
</html>
