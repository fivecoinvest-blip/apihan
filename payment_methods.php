<?php
/**
 * Available Payment Methods from WPay
 */

require_once 'wpay_helper.php';

$wpay = new WPayHelper();
$bankList = $wpay->getBankList('PHP');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Available Payment Methods</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { color: #333; }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            background: white; 
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #ddd; 
        }
        th { 
            background: #667eea; 
            color: white; 
            font-weight: bold; 
        }
        tr:hover { background: #f5f5f5; }
        .code { font-family: monospace; background: #f9f9f9; padding: 4px 8px; }
        .category {
            font-weight: bold;
            background: #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>💳 WPay Available Payment Methods (PHP)</h1>
        
        <p><strong>Total Methods:</strong> <?php echo count($bankList['data']); ?></p>
        
        <table>
            <thead>
                <tr>
                    <th>Bank Code</th>
                    <th>Bank Name</th>
                    <th>Category</th>
                </tr>
            </thead>
            <tbody>
<?php
$categories = [
    'GCASH' => ['PH_GCASH'],
    'Maya/GCash' => ['PH_MYA', 'PH_PPI'],
    'E-Wallets' => ['PH_GBY', 'PH_SPP', 'PH_APY', 'PH_SPY', 'PH_PPS', 'PH_DCP'],
    'Major Banks' => ['PH_BDO', 'PH_BPI', 'PH_MET', 'PH_PNB', 'PH_RCI', 'PH_UCB', 'PH_UCP', 'PH_BOC'],
    'Rural Banks' => ['PH_RBN', 'PH_PAS', 'PH_LSB', 'PH_LDB', 'PH_DAB', 'PH_CAB'],
];

$categorized = [];
foreach ($bankList['data'] as $bank) {
    $code = $bank['bankCode'];
    $found = false;
    
    foreach ($categories as $category => $codes) {
        if (in_array($code, $codes)) {
            if (!isset($categorized[$category])) $categorized[$category] = [];
            $categorized[$category][] = $bank;
            $found = true;
            break;
        }
    }
    
    if (!found) {
        if (!isset($categorized['Other'])) $categorized['Other'] = [];
        $categorized['Other'][] = $bank;
    }
}

foreach ($categorized as $category => $banks) {
    foreach ($banks as $bank) {
        echo "<tr>";
        echo "<td><span class='code'>" . htmlspecialchars($bank['bankCode']) . "</span></td>";
        echo "<td>" . htmlspecialchars($bank['bankName']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($category) . "</strong></td>";
        echo "</tr>";
    }
}
?>
            </tbody>
        </table>
        
        <h2>✅ Deposit Payment Methods (COLLECT)</h2>
        <p>For deposits, use these simple payment types in your form:</p>
        <ul>
            <li><code>GCASH</code> - GCash payments</li>
            <li><code>MAYA</code> - Maya wallet</li>
            <li><code>QR</code> - QR code payments</li>
        </ul>
        
        <h2>💰 Withdrawal Payment Methods (PAYOUT)</h2>
        <p>For withdrawals, use the bank codes from the table above:</p>
        <ul>
            <li><code>PH_GCASH</code> - GCash</li>
            <li><code>PH_MYA</code> - Maya</li>
            <li><code>PH_BDO</code> - BDO</li>
            <li><code>PH_BPI</code> - BPI</li>
            <li>... and many more from the list above</li>
        </ul>
    </div>
</body>
</html>
