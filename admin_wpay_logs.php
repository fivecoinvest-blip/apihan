<?php
/**
 * WPay Admin - Callback Logs Viewer
 * Access: /admin_wpay_logs.php (requires admin session)
 */

session_start();
require_once __DIR__ . '/wpay_config.php';
require_once __DIR__ . '/wpay_fee_messages.php';
require_once __DIR__ . '/db_helper.php';

// Verify admin session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    die('Access denied. Please login to admin panel first.');
}

$db = Database::getInstance();
$pdo = $db->getConnection();

// Get filter parameters
$filterStatus = $_GET['status'] ?? 'all';
$filterType = $_GET['type'] ?? 'all'; // 'deposits', 'withdrawals', 'callbacks'
$filterHours = intval($_GET['hours'] ?? 24);
$search = trim($_GET['search'] ?? '');

// Build queries
$sinceExpr = "NOW() - INTERVAL {$filterHours} HOUR";

// Deposits
if ($filterType === 'all' || $filterType === 'deposits') {
    $depositQuery = "SELECT pt.id, pt.user_id, pt.out_trade_no, pt.transaction_id, pt.amount, pt.currency, pt.pay_type, pt.status, pt.callback_count, pt.created_at, u.phone FROM payment_transactions pt LEFT JOIN users u ON pt.user_id = u.id WHERE pt.created_at >= {$sinceExpr}";
    if ($filterStatus !== 'all') {
        $depositQuery .= " AND pt.status = '" . $pdo->quote($filterStatus) . "'";
    }
    if (!empty($search)) {
        $depositQuery .= " AND (pt.out_trade_no LIKE " . $pdo->quote("%{$search}%") . " OR pt.transaction_id LIKE " . $pdo->quote("%{$search}%") . " OR pt.user_id = " . intval($search) . ")";
    }
    $depositQuery .= " ORDER BY pt.id DESC LIMIT 500";
    $stmt = $pdo->query($depositQuery);
    $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} else {
    $deposits = [];
}

// Withdrawals
if ($filterType === 'all' || $filterType === 'withdrawals') {
    $withdrawQuery = "SELECT id, user_id, out_trade_no, transaction_id, amount, currency, pay_type, account, collection_fee, processing_fee, total_fee, status, callback_count, created_at FROM withdrawal_transactions WHERE created_at >= {$sinceExpr}";
    if ($filterStatus !== 'all') {
        $withdrawQuery .= " AND status = '" . $pdo->quote($filterStatus) . "'";
    }
    if (!empty($search)) {
        $withdrawQuery .= " AND (out_trade_no LIKE " . $pdo->quote("%{$search}%") . " OR transaction_id LIKE " . $pdo->quote("%{$search}%") . " OR user_id = " . intval($search) . ")";
    }
    $withdrawQuery .= " ORDER BY id DESC LIMIT 500";
    $stmt = $pdo->query($withdrawQuery);
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} else {
    $withdrawals = [];
}

// Callback logs (read from file)
$callbackLogPath = __DIR__ . '/logs/wpay_callback.log';
$callbackLines = [];
if (file_exists($callbackLogPath) && ($filterType === 'all' || $filterType === 'callbacks')) {
    $lines = file($callbackLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines) {
        // Parse callback log entries
        $current = [];
        foreach (array_reverse($lines) as $line) {
            if (preg_match('/^===\s+(.+)\s+===$/', $line, $m)) {
                if (!empty($current)) {
                    $callbackLines[] = $current;
                    if (count($callbackLines) >= 100) break;
                }
                $current = ['timestamp' => $m[1], 'entries' => []];
            } else {
                if (!empty($current)) {
                    $current['entries'][] = $line;
                }
            }
        }
        if (!empty($current)) {
            $callbackLines[] = $current;
        }
    }
}

// Get stats
$depositStats = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as success, SUM(amount) as totalAmount, SUM(total_fee) as totalFees FROM payment_transactions WHERE created_at >= {$sinceExpr}")->fetch(PDO::FETCH_ASSOC);
$withdrawStats = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as success, SUM(amount) as totalAmount, SUM(total_fee) as totalFees FROM withdrawal_transactions WHERE created_at >= {$sinceExpr}")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WPay Logs - Admin Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #0f172a;
            color: #e5e7eb;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #2d3548;
        }
        h1 { font-size: 28px; font-weight: 700; }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #1a1f36 0%, #0f1626 100%);
            border: 1px solid #2d3548;
            border-radius: 8px;
            padding: 20px;
        }
        .stat-card h3 { font-size: 14px; color: #9ca3af; margin-bottom: 8px; text-transform: uppercase; }
        .stat-value { font-size: 28px; font-weight: 700; color: #3b82f6; }
        .filters {
            background: #1a1f36;
            border: 1px solid #2d3548;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filters select, .filters input, .filters button {
            padding: 8px 12px;
            border: 1px solid #374151;
            border-radius: 6px;
            background: #0f1626;
            color: #e5e7eb;
            font-size: 14px;
        }
        .filters button {
            background: #3b82f6;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }
        .filters button:hover { background: #2563eb; }
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            border-bottom: 1px solid #2d3548;
        }
        .tab-btn {
            padding: 12px 20px;
            background: transparent;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }
        .tab-btn.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .table-wrapper {
            background: #1a1f36;
            border: 1px solid #2d3548;
            border-radius: 8px;
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #0f1626;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #9ca3af;
            font-size: 12px;
            text-transform: uppercase;
            border-bottom: 1px solid #2d3548;
        }
        td {
            padding: 12px 16px;
            border-bottom: 1px solid #2d3548;
            font-size: 14px;
        }
        tr:hover { background: #2d3548; }
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-completed { background: #064e3b; color: #6ee7b7; }
        .badge-pending { background: #78350f; color: #fcd34d; }
        .badge-processing { background: #1e3a8a; color: #93c5fd; }
        .badge-failed { background: #7f1d1d; color: #fca5a5; }
        .order-no { font-family: 'Courier New', monospace; font-size: 12px; color: #93c5fd; }
        .log-entry {
            background: #0f1626;
            border: 1px solid #2d3548;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #9ca3af;
            max-height: 200px;
            overflow-y: auto;
        }
        .log-timestamp { color: #3b82f6; font-weight: 600; margin-bottom: 8px; }
        .btn-export {
            padding: 8px 16px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
        }
        .btn-export:hover { background: #059669; }
        .link-back {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            display: inline-block;
        }
        .link-back:hover { text-decoration: underline; }
        .fees-note {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            color: #92400e;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin.php" class="link-back">← Back to Admin Panel</a>
        
        <div class="header">
            <h1>💳 WPay Payment Logs</h1>
            <a href="export_wpay_logs.php?token=wpay-logs-20260113&hours=<?php echo $filterHours; ?>" class="btn-export">📥 Export CSV</a>
        </div>

        <div class="stats">
            <div class="stat-card">
                <h3>Deposits (<?php echo $filterHours; ?>h)</h3>
                <div class="stat-value"><?php echo $depositStats['total'] ?? 0; ?></div>
                <div style="font-size: 12px; color: #6ee7b7; margin-top: 8px;">✓ <?php echo $depositStats['success'] ?? 0; ?> completed</div>
            </div>
            <div class="stat-card">
                <h3>Withdrawals (<?php echo $filterHours; ?>h)</h3>
                <div class="stat-value"><?php echo $withdrawStats['total'] ?? 0; ?></div>
                <div style="font-size: 12px; color: #6ee7b7; margin-top: 8px;">✓ <?php echo $withdrawStats['success'] ?? 0; ?> completed</div>
            </div>
            <div class="stat-card">
                <h3>Total Deposit Vol.</h3>
                <div class="stat-value">₱<?php echo number_format($depositStats['totalAmount'] ?? 0, 2); ?></div>
                <div style="font-size: 11px; color: #fca5a5; margin-top: 8px;">Fees: ₱<?php echo number_format($depositStats['totalFees'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Withdrawal Vol.</h3>
                <div class="stat-value">₱<?php echo number_format($withdrawStats['totalAmount'] ?? 0, 2); ?></div>
                <div style="font-size: 11px; color: #fca5a5; margin-top: 8px;">Fees: ₱<?php echo number_format($withdrawStats['totalFees'] ?? 0, 2); ?></div>
            </div>
        </div>

        <div class="fees-note">
            <strong>📋 Fee Structure:</strong> Collection Fee: 1.6% | Withdrawal Processing Fee: ₱8
            <br>
            <strong>Current Status:</strong> 
            Deposits - <?php 
                $feeStatus = getAdminFeeStatus();
                echo '✅ ' . $feeStatus['deposit']; 
            ?> | Withdrawals - ✅ <?php echo $feeStatus['withdrawal']; ?>
            <br>
            <em>Fees are recorded for monitoring. Admin can enable customer charges by changing WPAY_CHARGE_*_FEE_TO_USER in wpay_config.php</em>
        </div>

        <div class="filters">
            <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                <select name="type">
                    <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="deposits" <?php echo $filterType === 'deposits' ? 'selected' : ''; ?>>Deposits Only</option>
                    <option value="withdrawals" <?php echo $filterType === 'withdrawals' ? 'selected' : ''; ?>>Withdrawals Only</option>
                    <option value="callbacks" <?php echo $filterType === 'callbacks' ? 'selected' : ''; ?>>Callbacks Only</option>
                </select>
                <select name="status">
                    <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>✓ Completed</option>
                    <option value="processing" <?php echo $filterStatus === 'processing' ? 'selected' : ''; ?>>⟳ Processing</option>
                    <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                    <option value="failed" <?php echo $filterStatus === 'failed' ? 'selected' : ''; ?>>✗ Failed</option>
                </select>
                <select name="hours">
                    <option value="1" <?php echo $filterHours === 1 ? 'selected' : ''; ?>>Last 1 hour</option>
                    <option value="24" <?php echo $filterHours === 24 ? 'selected' : ''; ?>>Last 24 hours</option>
                    <option value="72" <?php echo $filterHours === 72 ? 'selected' : ''; ?>>Last 72 hours</option>
                    <option value="168" <?php echo $filterHours === 168 ? 'selected' : ''; ?>>Last 7 days</option>
                </select>
                <input type="text" name="search" placeholder="Search order, txn ID, or user ID" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">🔍 Filter</button>
            </form>
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('deposits', this)">📥 Deposits (<?php echo count($deposits); ?>)</button>
            <button class="tab-btn" onclick="switchTab('withdrawals', this)">📤 Withdrawals (<?php echo count($withdrawals); ?>)</button>
            <button class="tab-btn" onclick="switchTab('callbacks', this)">📋 Callbacks</button>
        </div>

        <!-- Deposits Tab -->
        <div id="deposits" class="tab-content active">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Order No</th>
                            <th>User ID</th>
                            <th>Requested Number</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Callbacks</th>
                            <th>Created</th>
                            <th>TXN ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deposits as $d): ?>
                        <tr>
                            <td><span class="order-no"><?php echo htmlspecialchars($d['out_trade_no']); ?></span></td>
                            <td><?php echo $d['user_id']; ?></td>
                            <td style="font-size: 12px; color: #9ca3af;"><?php echo htmlspecialchars($d['phone'] ?? '-'); ?></td>
                            <td><strong>₱<?php echo number_format($d['amount'], 2); ?></strong></td>
                            <td><?php echo htmlspecialchars($d['pay_type']); ?></td>
                            <td><span class="badge badge-<?php echo $d['status']; ?>"><?php echo strtoupper($d['status']); ?></span></td>
                            <td><?php echo $d['callback_count']; ?>/5</td>
                            <td style="font-size: 12px;"><?php echo date('M d H:i', strtotime($d['created_at'])); ?></td>
                            <td><span style="font-size: 11px; color: #6ee7b7;"><?php echo $d['transaction_id'] ? substr($d['transaction_id'], 0, 12) . '...' : '-'; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($deposits)): ?>
                <div style="padding: 40px; text-align: center; color: #6b7280;">No deposits found</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Withdrawals Tab -->
        <div id="withdrawals" class="tab-content">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Order No</th>
                            <th>User ID</th>
                            <th>Requested Number</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Account</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Callbacks</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($withdrawals as $w): ?>
                        <tr>
                            <td><span class="order-no"><?php echo htmlspecialchars($w['out_trade_no']); ?></span></td>
                            <td><?php echo $w['user_id']; ?></td>
                            <td style="font-size: 12px; color: #9ca3af;"><?php echo htmlspecialchars($w['account'] ?? '-'); ?></td>
                            <td><strong>₱<?php echo number_format($w['amount'], 2); ?></strong></td>
                            <td><?php echo htmlspecialchars($w['pay_type']); ?></td>
                            <td style="font-size: 12px; color: #9ca3af;"><?php echo htmlspecialchars(substr($w['account'], -4, 4)); ?></td>
                            <td style="font-size: 12px; color: #fca5a5;">₱<?php echo number_format($w['total_fee'] ?? 0, 2); ?> <span style="color: #6b7280;">(<?php echo number_format($w['collection_fee'] ?? 0, 2); ?>+<?php echo number_format($w['processing_fee'] ?? 0, 2); ?>)</span></td>
                            <td><span class="badge badge-<?php echo $w['status']; ?>"><?php echo strtoupper($w['status']); ?></span></td>
                            <td><?php echo $w['callback_count']; ?>/5</td>
                            <td style="font-size: 12px;"><?php echo date('M d H:i', strtotime($w['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($withdrawals)): ?>
                <div style="padding: 40px; text-align: center; color: #6b7280;">No withdrawals found</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Callbacks Tab -->
        <div id="callbacks" class="tab-content">
            <div style="padding: 20px;">
                <h3 style="margin-bottom: 16px;">📋 Recent Callback Log Entries</h3>
                <?php foreach (array_slice($callbackLines, 0, 50) as $entry): ?>
                <div class="log-entry">
                    <div class="log-timestamp">🕐 <?php echo htmlspecialchars($entry['timestamp']); ?></div>
                    <?php foreach ($entry['entries'] as $logLine): ?>
                        <div><?php echo htmlspecialchars($logLine); ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
                <?php if (empty($callbackLines)): ?>
                <div style="padding: 40px; text-align: center; color: #6b7280;">No callback logs found</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabId, btn) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            btn.classList.add('active');
        }
    </script>
</body>
</html>
