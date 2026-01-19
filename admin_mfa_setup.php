<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'db_helper.php';
require_once 'csrf_helper.php';
require_once 'mfa_helper.php';
require_once 'geo_helper.php';
require_once 'settings_helper.php';

// Restrict admin access to PH IPs only
// Temporarily disabled for debugging - re-enable once working
// GeoHelper::enforceCountry('PH');

// Must be logged in as admin
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$adminId = (int)$_SESSION['admin_id'];

// Ensure required columns exist (best-effort)
try {
    $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('mfa_secret', $cols)) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN mfa_secret VARCHAR(64) NULL AFTER password");
    }
    if (!in_array('mfa_enabled', $cols)) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN mfa_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER mfa_secret");
    }
} catch (Throwable $e) {
    // ignore - show guidance below
}

// Detect if MFA columns exist; avoid runtime errors on missing columns
$hasMfaColumns = false;
try {
    $colStmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users'");
    $cols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
    $hasMfaColumns = is_array($cols) && in_array('mfa_secret', $cols) && in_array('mfa_enabled', $cols);
} catch (Throwable $e) {
    $hasMfaColumns = false;
}

// Always fetch username; MFA fields only if present
$stmt = $pdo->prepare('SELECT username FROM admin_users WHERE id = ?');
$stmt->execute([$adminId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
unset($_SESSION['error'], $_SESSION['success']);

CSRF::generateToken();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        CSRF::regenerateToken();
        $_SESSION['error'] = 'Session expired. Please try again.';
        header('Location: admin_mfa_setup.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'generate') {
            $secret = MFAHelper::generateSecret(16);
            $stmt = $pdo->prepare('UPDATE admin_users SET mfa_secret = ?, mfa_enabled = 0 WHERE id = ?');
            $stmt->execute([$secret, $adminId]);
            $_SESSION['success'] = 'Secret generated. Scan QR and verify to enable.';
        } elseif ($action === 'enable') {
            $code = trim($_POST['mfa_code'] ?? '');
            $stmt = $pdo->prepare('SELECT mfa_secret FROM admin_users WHERE id = ?');
            $stmt->execute([$adminId]);
            $secret = $stmt->fetchColumn();
            if (!$secret) throw new Exception('No secret found. Generate first.');
            if (MFAHelper::verifyCode($secret, $code)) {
                $pdo->prepare('UPDATE admin_users SET mfa_enabled = 1 WHERE id = ?')->execute([$adminId]);
                $_SESSION['success'] = 'Google Authenticator enabled.';
            } else {
                throw new Exception('Invalid code. Try again.');
            }
        } elseif ($action === 'disable') {
            $pdo->prepare('UPDATE admin_users SET mfa_enabled = 0 WHERE id = ?')->execute([$adminId]);
            $_SESSION['success'] = 'Google Authenticator disabled.';
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header('Location: admin_mfa_setup.php');
    exit;
}

// Refresh admin data
// Refresh admin data (with MFA fields if available)
if ($hasMfaColumns) {
    try {
        $stmt = $pdo->prepare('SELECT username, mfa_secret, mfa_enabled FROM admin_users WHERE id = ?');
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC) ?: $admin;
    } catch (Throwable $e) {
        // keep minimal admin info
    }
}

$issuer = SiteSettings::get('casino_name', 'Casino PHP');
$account = $admin ? $admin['username'] : 'admin';
$secret = ($hasMfaColumns && $admin) ? ($admin['mfa_secret'] ?? '') : '';
// Clean secret: remove padding (=) and ensure uppercase for display
$displaySecret = $secret ? strtoupper(rtrim($secret, '=')) : '';
$enabled = ($hasMfaColumns && $admin) ? (int)($admin['mfa_enabled'] ?? 0) === 1 : false;
$otpUri = $secret ? MFAHelper::buildOtpAuthUri($issuer, $account, $secret) : '';
// Use qrserver.com instead of Google Charts (more reliable, fewer CSP issues)
$qrUrl = $otpUri ? ('https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($otpUri)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Security - Google Authenticator</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:15px}
        .box{background:rgba(255,255,255,.95);backdrop-filter:blur(10px);padding:28px;border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,.3);width:100%;max-width:520px}
        h1{color:#667eea;font-size:24px;margin-bottom:8px;text-align:center}
        p.sub{color:#666;text-align:center;margin-bottom:16px}
        .row{display:flex;gap:16px;align-items:flex-start;justify-content:center;margin:18px 0}
        .card{background:#fff;border:2px solid #e5e7eb;border-radius:14px;padding:16px;flex:1}
        label{display:block;margin-bottom:8px;color:#333;font-weight:600;font-size:14px}
        input{width:100%;padding:12px 14px;border:2px solid #e5e7eb;border-radius:10px;font-size:16px;background:#fff}
        button{padding:12px 16px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;border:none;border-radius:10px;font-weight:600;cursor:pointer}
        .muted{color:#64748b;font-size:13px;margin-top:8px}
        .alert{padding:12px 16px;border-radius:10px;margin-bottom:12px;font-size:14px}
        .alert-error{background:#fee2e2;color:#dc2626;border-left:4px solid #dc2626}
        .alert-success{background:#d1fae5;color:#059669;border-left:4px solid #059669}
        .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}
        a.back{display:inline-flex;gap:6px;color:#667eea;text-decoration:none;font-weight:600;margin-bottom:10px}
    </style>
</head>
<body>
    <div class="box">
        <a href="admin.php" class="back">← Back to Admin</a>
        <h1>🔒 Google Authenticator</h1>
        <p class="sub">Protect admin access with a second verification step.</p>
        <?php if (!empty($error)): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if (!empty($success)): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <div class="row">
            <div class="card" style="flex:0 0 260px;text-align:center">
                <div style="margin-bottom:12px;font-weight:600;font-size:16px">📱 Your Secret Key</div>
                <?php if ($secret): ?>
                    <div style="background:#f0f0f0;padding:14px;border-radius:8px;font-family:monospace;font-size:14px;color:#000;word-break:break-all;margin-bottom:10px;letter-spacing:2px">
                        <strong><?php echo htmlspecialchars($displaySecret); ?></strong>
                    </div>
                    <button type="button" onclick="copySecret()" style="background:#667eea;color:white;border:none;padding:8px 14px;border-radius:6px;cursor:pointer;font-weight:600;font-size:12px;width:100%;margin-bottom:12px">📋 Copy Key</button>
                    <p class="muted" style="font-size:12px;margin-bottom:12px">
                        Scan with Google Authenticator, or<br>tap "Can't scan it?" and paste the key
                    </p>
                    <?php if ($qrUrl): ?>
                        <img src="<?php echo htmlspecialchars($qrUrl); ?>" alt="QR Code" width="180" height="180" style="border-radius:8px;border:2px solid #e5e7eb;margin-bottom:8px" onerror="document.getElementById('qr-error').style.display='block';this.style.display='none'">
                        <div id="qr-error" style="display:none;padding:20px;background:#fee2e2;border-radius:8px;color:#dc2626;font-size:12px">
                            QR code unavailable.<br>Use manual entry instead.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="padding:40px 10px;background:#f5f5f5;border-radius:8px;color:#999">
                        <strong>No secret yet</strong><br>
                        <span style="font-size:12px">Click "Generate Secret" to create one</span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card">
                <form method="POST">
                    <?php echo CSRF::getTokenField(); ?>
                    <?php if (!$hasMfaColumns): ?>
                        <p class="muted">MFA columns not found. Please run:</p>
                        <pre style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:10px;white-space:pre-line;font-size:11px">ALTER TABLE admin_users ADD COLUMN mfa_secret VARCHAR(64) NULL AFTER password;
ALTER TABLE admin_users ADD COLUMN mfa_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER mfa_secret;</pre>
                    <?php elseif (!$secret): ?>
                        <p style="font-weight:600;margin-bottom:12px">Step 1: Create Secret</p>
                        <p class="muted">Generate a secret key for your authenticator app.</p>
                        <div class="actions" style="margin-top:16px">
                            <button type="submit" name="action" value="generate" style="background:linear-gradient(135deg,#10b981 0%,#059669 100%)">📝 Generate Secret</button>
                        </div>
                    <?php elseif (!$enabled): ?>
                        <p style="font-weight:600;margin-bottom:12px">Step 2: Verify Code</p>
                        <p class="muted">Enter the 6-digit code from your authenticator.</p>
                        <label style="margin-top:12px">6-Digit Code</label>
                        <input type="text" name="mfa_code" inputmode="numeric" autocomplete="one-time-code" placeholder="000000" required pattern="^\d{6}$" style="text-align:center;font-size:18px;letter-spacing:4px;margin-top:6px">
                        <div class="actions" style="margin-top:14px">
                            <button type="submit" name="action" value="enable" style="background:linear-gradient(135deg,#3b82f6 0%,#2563eb 100%)">✓ Verify & Enable</button>
                        </div>
                    <?php else: ?>
                        <p style="font-weight:600;margin-bottom:8px;color:#10b981">✅ Active</p>
                        <p class="muted">Google Authenticator is protecting your admin account.</p>
                        <div class="actions" style="margin-top:16px">
                            <button type="submit" name="action" value="disable" style="background:#ef4444;font-size:14px">🔓 Disable</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <script>
        function copySecret() {
            const secretText = document.querySelector('.card strong');
            if (!secretText) return;
            const text = secretText.textContent.trim();
            navigator.clipboard.writeText(text).then(() => {
                const btn = event.target;
                const original = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.background = '#10b981';
                setTimeout(() => {
                    btn.textContent = original;
                    btn.style.background = '#667eea';
                }, 2000);
            }).catch(() => {
                alert('Could not copy. Please copy manually: ' + text);
            });
        }
    </script>
</body>
</html>
