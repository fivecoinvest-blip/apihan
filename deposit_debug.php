<?php
/**
 * Deposit Parameter Debugger
 * Shows exact parameters sent to WPay for each payment type
 */

require_once 'session_config.php';
require_once 'wpay_config.php';
require_once 'wpay_helper.php';
require_once 'db_helper.php';

// Simulate logged-in user
$_SESSION['user_id'] = 1;

$userModel = new User();
$balance = $userModel->getBalance(1);

$debugInfo = [];
$selectedType = $_GET['test'] ?? 'GCASH';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Deposit Parameter Debug</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: monospace; background: #1e1e1e; color: #e0e0e0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #4ec9b0; margin: 20px 0; }
        .test-button {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 5px 10px 0;
            background: #007acc;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .test-button:hover { background: #005a9e; }
        .test-button.active { background: #00ff00; color: #000; }
        .section {
            background: #252526;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #3e3e42;
            border-radius: 5px;
        }
        .section h2 { color: #4ec9b0; margin-bottom: 10px; }
        pre {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            padding: 15px;
            overflow-x: auto;
            border-radius: 3px;
        }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #3e3e42; }
        th { background: #2d2d30; color: #4ec9b0; font-weight: bold; }
        .param-name { color: #ce9178; }
        .param-value { color: #b5cea8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Deposit Parameter Debugger</h1>
        
        <div style="margin-bottom: 20px;">
            <strong>Test Payment Type:</strong>
            <a href="?test=GCASH" class="test-button <?php echo $selectedType === 'GCASH' ? 'active' : ''; ?>">GCASH</a>
            <a href="?test=MAYA" class="test-button <?php echo $selectedType === 'MAYA' ? 'active' : ''; ?>">MAYA</a>
            <a href="?test=QR" class="test-button <?php echo $selectedType === 'QR' ? 'active' : ''; ?>">QR</a>
        </div>

        <div class="section">
            <h2>📊 Current Settings</h2>
            <table>
                <tr>
                    <th>Setting</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td><span class="param-name">WPAY_ENV</span></td>
                    <td><?php echo WPAY_ENV; ?></td>
                </tr>
                <tr>
                    <td><span class="param-name">WPAY_MCH_ID</span></td>
                    <td><?php echo WPAY_MCH_ID; ?></td>
                </tr>
                <tr>
                    <td><span class="param-name">WPAY_CURRENCY</span></td>
                    <td><?php echo WPAY_CURRENCY; ?></td>
                </tr>
                <tr>
                    <td><span class="param-name">WPAY_NOTIFY_URL</span></td>
                    <td><?php echo WPAY_NOTIFY_URL; ?></td>
                </tr>
                <tr>
                    <td><span class="param-name">WPAY_RETURN_URL</span></td>
                    <td><?php echo WPAY_RETURN_URL; ?></td>
                </tr>
                <tr>
                    <td><span class="param-name">User Balance</span></td>
                    <td>₱<?php echo number_format($balance, 2); ?></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2>✅ Parameters for <?php echo htmlspecialchars($selectedType); ?></h2>
<?php
$wpay = new WPayHelper();
$amount = 100;
$outTradeNo = $selectedType . '_' . date('YmdHis') . rand(1000, 9999);

$params = [
    'mchId' => WPAY_MCH_ID,
    'currency' => WPAY_CURRENCY,
    'out_trade_no' => $outTradeNo,
    'pay_type' => $selectedType,
    'money' => (int)$amount,
    'notify_url' => WPAY_NOTIFY_URL,
    'returnUrl' => WPAY_RETURN_URL,
];

$params['sign'] = $wpay->generateSign($params);

echo "<h3>Request Parameters:</h3>";
echo "<pre>";
echo json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "</pre>";

echo "<h3>Parameter Details:</h3>";
echo "<table>";
foreach ($params as $key => $value) {
    echo "<tr>";
    echo "<td><span class='param-name'>" . htmlspecialchars($key) . "</span></td>";
    echo "<td><span class='param-value'>" . htmlspecialchars($value) . "</span></td>";
    echo "</tr>";
}
echo "</table>";

// Send to WPay and show response
echo "<h3>WPay API Response:</h3>";
$response = $wpay->sendRequest('/v1/Collect', $params);

if ($response['code'] == 0) {
    echo "<p class='success'>✓ Success</p>";
    echo "<pre>";
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo "</pre>";
    echo "<p><strong>Payment URL:</strong></p>";
    echo "<p><a href='" . htmlspecialchars($response['data']['url']) . "' target='_blank' style='color: #4ec9b0;'>Click here to open payment page</a></p>";
} else {
    echo "<p class='error'>✗ Failed</p>";
    echo "<pre>";
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo "</pre>";
}
?>
        </div>

        <div class="section">
            <h2>📋 Comparison Table</h2>
            <p>Here's how different payment types should appear:</p>
            <table>
                <tr>
                    <th>Payment Type</th>
                    <th>Code</th>
                    <th>API Param</th>
                    <th>Status</th>
                </tr>
                <tr>
                    <td>GCash</td>
                    <td>PH_GCASH</td>
                    <td><?php echo $selectedType === 'GCASH' ? '<span class="success">GCASH</span>' : 'GCASH'; ?></td>
                    <td><?php echo $selectedType === 'GCASH' ? '<span class="success">✓ Testing</span>' : ''; ?></td>
                </tr>
                <tr>
                    <td>Maya</td>
                    <td>PH_MYA</td>
                    <td><?php echo $selectedType === 'MAYA' ? '<span class="success">MAYA</span>' : 'MAYA'; ?></td>
                    <td><?php echo $selectedType === 'MAYA' ? '<span class="success">✓ Testing</span>' : ''; ?></td>
                </tr>
                <tr>
                    <td>QR Code</td>
                    <td>N/A</td>
                    <td><?php echo $selectedType === 'QR' ? '<span class="success">QR</span>' : 'QR'; ?></td>
                    <td><?php echo $selectedType === 'QR' ? '<span class="success">✓ Testing</span>' : ''; ?></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2>📌 Notes</h2>
            <ul style="list-style-position: inside;">
                <li>For <strong>DEPOSITS</strong> (Collect), use: <span class="success">GCASH, MAYA, QR</span></li>
                <li>For <strong>WITHDRAWALS</strong> (Payout), use: <span class="success">PH_GCASH, PH_MYA, PH_BDO</span>, etc.</li>
                <li>The signature is generated from all parameters in alphabetical order</li>
                <li>If WPay's checkout shows wrong payment type, it's a WPay UI issue</li>
            </ul>
        </div>
    </div>
</body>
</html>
