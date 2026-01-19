<?php
/**
 * Admin - Login Attempts Management
 * Allows viewing and deleting login attempts (by IP, username/phone, date range, or all)
 */

session_start();
// Load configuration to ensure DB constants (DB_HOST, DB_NAME, DB_USER, DB_PASS) are defined
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_helper.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/geo_helper.php';

// Verify admin session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    die('Access denied. Please login to admin panel first.');
}

// Restrict admin access to PH IPs only
// Temporarily disabled for debugging - re-enable once working
// GeoHelper::enforceCountry('PH');

$db = Database::getInstance();
$pdo = $db->getConnection();

$success = null;
$error = null;

// Handle deletions (POST) with CSRF validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = 'Session expired. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'delete_by_ip') {
                $ip = trim($_POST['ip'] ?? '');
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    throw new Exception('Invalid IP address');
                }
                $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
                $stmt->execute([$ip]);
                $success = 'Deleted attempts for IP: ' . htmlspecialchars($ip);
            }
            elseif ($action === 'delete_by_user') {
                $user = trim($_POST['user'] ?? '');
                if ($user === '') {
                    throw new Exception('Username/phone is required');
                }
                $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE username_or_phone = ?");
                $stmt->execute([$user]);
                $success = 'Deleted attempts for user/phone: ' . htmlspecialchars($user);
            }
            elseif ($action === 'delete_today') {
                $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE DATE(attempt_time) = CURDATE()");
                $stmt->execute();
                $success = 'Deleted today\'s login attempts.';
            }
            elseif ($action === 'delete_range') {
                $start = trim($_POST['start'] ?? '');
                $end = trim($_POST['end'] ?? '');
                if ($start === '' || $end === '') {
                    throw new Exception('Start and end dates are required');
                }
                $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time BETWEEN ? AND ?");
                $stmt->execute([$start . ' 00:00:00', $end . ' 23:59:59']);
                $success = 'Deleted attempts from ' . htmlspecialchars($start) . ' to ' . htmlspecialchars($end) . '.';
            }
            elseif ($action === 'delete_all') {
                $stmt = $pdo->prepare("DELETE FROM login_attempts");
                $stmt->execute();
                $success = 'Deleted ALL login attempts.';
            } else {
                throw new Exception('Unknown action');
            }
        } catch (Exception $ex) {
            $error = $ex->getMessage();
        }
    }
}

// Fetch recent attempts (last 200)
$search = trim($_GET['search'] ?? '');
$query = "SELECT ip_address, username_or_phone, attempt_time FROM login_attempts";
$params = [];
if ($search !== '') {
    $query .= " WHERE ip_address LIKE ? OR username_or_phone LIKE ?";
    $params = ['%' . $search . '%', '%' . $search . '%'];
}
$query .= " ORDER BY attempt_time DESC LIMIT 200";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Stats: count today and total
$todayCount = (int)$pdo->query("SELECT COUNT(*) FROM login_attempts WHERE DATE(attempt_time) = CURDATE()")
    ->fetchColumn();
$totalCount = (int)$pdo->query("SELECT COUNT(*) FROM login_attempts")
    ->fetchColumn();

$csrfField = CSRF::getTokenField();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Login Attempts</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: #0f172a; color: #e5e7eb; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        h1 { font-size: 24px; font-weight: 700; }
        a { color: #60a5fa; text-decoration: none; }
        .cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
        .card { background: #111827; border: 1px solid #1f2937; border-radius: 10px; padding: 16px; }
        .card h3 { font-size: 14px; color: #9ca3af; margin-bottom: 8px; }
        .card .value { font-size: 24px; font-weight: 700; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .panel { background: #111827; border: 1px solid #1f2937; border-radius: 10px; padding: 16px; }
        .panel h2 { font-size: 18px; margin-bottom: 12px; }
        .row { display: flex; gap: 12px; margin-bottom: 10px; }
        input, select { padding: 8px 10px; border-radius: 8px; border: 1px solid #374151; background: #0b1220; color: #e5e7eb; }
        button { padding: 10px 14px; border-radius: 8px; border: none; background: #ef4444; color: white; cursor: pointer; font-weight: 600; }
        button.secondary { background: #374151; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 10px; border-bottom: 1px solid #1f2937; font-size: 14px; }
        th { color: #9ca3af; text-align: left; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 12px; background: #1f2937; color: #9ca3af; }
        .actions { display: flex; gap: 8px; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 12px; }
        .alert-success { background: #064e3b; color: #d1fae5; border: 1px solid #10b981; }
        .alert-error { background: #4c0519; color: #fee2e2; border: 1px solid #ef4444; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🛡️ Login Attempts Management</h1>
        <div>
            <a href="admin.php">← Back to Admin</a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="cards">
        <div class="card"><h3>Today</h3><div class="value"><?php echo number_format($todayCount); ?></div></div>
        <div class="card"><h3>Total</h3><div class="value"><?php echo number_format($totalCount); ?></div></div>
        <div class="card"><h3>Unique IPs (24h)</h3><div class="value"><?php echo number_format((int)$pdo->query("SELECT COUNT(DISTINCT ip_address) FROM login_attempts WHERE attempt_time >= NOW() - INTERVAL 1 DAY")->fetchColumn()); ?></div></div>
        <div class="card"><h3>Unique Users (24h)</h3><div class="value"><?php echo number_format((int)$pdo->query("SELECT COUNT(DISTINCT username_or_phone) FROM login_attempts WHERE attempt_time >= NOW() - INTERVAL 1 DAY")->fetchColumn()); ?></div></div>
    </div>

    <div class="grid">
        <div class="panel">
            <h2>Delete Attempts</h2>
            <form method="POST" class="row">
                <?php echo $csrfField; ?>
                <input type="text" name="ip" placeholder="IP address (e.g., 203.0.113.1)">
                <button type="submit" name="action" value="delete_by_ip">Delete by IP</button>
            </form>
            <form method="POST" class="row">
                <?php echo $csrfField; ?>
                <input type="text" name="user" placeholder="Username or phone">
                <button type="submit" name="action" value="delete_by_user">Delete by User/Phone</button>
            </form>
            <form method="POST" class="row">
                <?php echo $csrfField; ?>
                <button type="submit" name="action" value="delete_today">Delete Today</button>
                <button type="submit" name="action" value="delete_all" class="secondary" onclick="return confirm('Delete ALL login attempts? This cannot be undone.');">Delete ALL</button>
            </form>
            <form method="POST" class="row">
                <?php echo $csrfField; ?>
                <input type="date" name="start">
                <input type="date" name="end">
                <button type="submit" name="action" value="delete_range">Delete by Date Range</button>
            </form>
        </div>
        <div class="panel">
            <h2>Recent Attempts</h2>
            <form method="GET" class="row">
                <input type="text" name="search" placeholder="Search IP or user" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="secondary">Search</button>
            </form>
            <table>
                <thead>
                    <tr>
                        <th>IP Address</th>
                        <th>User/Phone</th>
                        <th>Attempt Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($attempts)): ?>
                    <tr><td colspan="4" style="color:#9ca3af;">No login attempts found.</td></tr>
                <?php else: foreach ($attempts as $row): ?>
                    <tr>
                        <td><span class="badge"><?php echo htmlspecialchars($row['ip_address']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['username_or_phone']); ?></td>
                        <td><?php echo htmlspecialchars($row['attempt_time']); ?></td>
                        <td class="actions">
                            <form method="POST" style="display:inline;">
                                <?php echo $csrfField; ?>
                                <input type="hidden" name="ip" value="<?php echo htmlspecialchars($row['ip_address']); ?>">
                                <button type="submit" name="action" value="delete_by_ip">Delete IP</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <?php echo $csrfField; ?>
                                <input type="hidden" name="user" value="<?php echo htmlspecialchars($row['username_or_phone']); ?>">
                                <button type="submit" name="action" value="delete_by_user">Delete User</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
