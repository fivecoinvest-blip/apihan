<?php
require_once 'session_config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit;
}

require_once 'wpay_config.php';
require_once 'wpay_helper.php';

$wpay = new WPayHelper();
$result = null;
$error = null;

// Handle API calls
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'query_deposit':
                $orderNo = trim($_POST['order_no'] ?? '');
                if ($orderNo) {
                    $result = $wpay->queryDeposit($orderNo);
                }
                break;
                
            case 'query_withdrawal':
                $orderNo = trim($_POST['order_no'] ?? '');
                if ($orderNo) {
                    $result = $wpay->queryWithdrawal($orderNo);
                }
                break;
                
            case 'check_balance':
                $currency = trim($_POST['currency'] ?? '');
                $result = $wpay->getBalance($currency ?: null);
                break;
                
            case 'get_banks':
                $currency = trim($_POST['currency'] ?? 'PHP');
                $result = $wpay->getBankList($currency);
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WPay Tools - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #e5e7eb;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        h1 { font-size: 28px; color: white; }
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .tool-card {
            background: #1a1f36;
            border: 1px solid #2d3548;
            border-radius: 10px;
            padding: 25px;
        }
        .tool-card h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #3b82f6;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            color: #9ca3af;
        }
        input, select {
            width: 100%;
            padding: 10px;
            background: #0f1626;
            border: 1px solid #374151;
            border-radius: 6px;
            color: #e5e7eb;
            font-size: 14px;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #3b82f6;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover { background: #2563eb; }
        .result-box {
            background: #0f1626;
            border: 1px solid #374151;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
            max-height: 500px;
            overflow-y: auto;
        }
        .result-box h3 {
            color: #10b981;
            margin-bottom: 15px;
        }
        .result-box pre {
            background: #000;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            color: #22c55e;
            font-size: 13px;
            line-height: 1.6;
        }
        .error {
            background: #7f1d1d;
            color: #fca5a5;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .back-link {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 20px;
        }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin.php" class="back-link">← Back to Admin</a>
        
        <div class="header">
            <h1>🛠️ WPay API Tools</h1>
            <p style="margin-top: 10px; opacity: 0.9;">Query transactions, check balance, and manage payment methods</p>
        </div>

        <div class="tools-grid">
            <!-- Query Deposit -->
            <div class="tool-card">
                <h2>📥 Query Deposit Status</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="query_deposit">
                    <div class="form-group">
                        <label>Order Number (D...)</label>
                        <input type="text" name="order_no" placeholder="D2026011314315779344" required>
                    </div>
                    <button type="submit">🔍 Query Deposit</button>
                </form>
            </div>

            <!-- Query Withdrawal -->
            <div class="tool-card">
                <h2>📤 Query Withdrawal Status</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="query_withdrawal">
                    <div class="form-group">
                        <label>Order Number (W...)</label>
                        <input type="text" name="order_no" placeholder="W2026011307025081198" required>
                    </div>
                    <button type="submit">🔍 Query Withdrawal</button>
                </form>
            </div>

            <!-- Check Balance -->
            <div class="tool-card">
                <h2>💰 Check WPay Balance</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="check_balance">
                    <div class="form-group">
                        <label>Currency (optional - leave empty for all)</label>
                        <select name="currency">
                            <option value="">All Currencies</option>
                            <option value="PHP">PHP</option>
                            <option value="INR">INR</option>
                            <option value="JPY">JPY</option>
                        </select>
                    </div>
                    <button type="submit">💳 Check Balance</button>
                </form>
            </div>

            <!-- Get Bank List -->
            <div class="tool-card">
                <h2>🏦 Get Bank List</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="get_banks">
                    <div class="form-group">
                        <label>Currency</label>
                        <select name="currency" required>
                            <option value="PHP">PHP</option>
                            <option value="INR">INR</option>
                            <option value="JPY">JPY</option>
                        </select>
                    </div>
                    <button type="submit">📋 Get Banks</button>
                </form>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="error">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($result): ?>
            <div class="result-box">
                <h3>✅ API Response:</h3>
                <pre><?php echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
