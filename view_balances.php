<?php
/**
 * Balance Viewer - See all user balances and transactions
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Balances</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 { color: #667eea; margin-bottom: 30px; }
        h2 { color: #764ba2; margin: 30px 0 15px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
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
        .balance { font-weight: bold; color: #28a745; font-size: 18px; }
        .transactions {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .btn:hover { background: #5568d3; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="btn">← Back to Launch</a>
        
        <h1>💰 User Balances</h1>
        
        <?php
        $balanceFile = __DIR__ . '/logs/balances.json';
        $transFile = __DIR__ . '/logs/transactions.log';
        
        // Load balances
        if (file_exists($balanceFile)) {
            $balances = json_decode(file_get_contents($balanceFile), true);
            
            if ($balances && count($balances) > 0) {
                echo '<table>';
                echo '<tr><th>User ID</th><th>Balance</th></tr>';
                foreach ($balances as $userId => $balance) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($userId) . '</td>';
                    echo '<td class="balance">$' . number_format($balance, 2) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p>No user balances found. Launch a game to create balance records.</p>';
            }
        } else {
            echo '<p>No balance file found. Launch a game first.</p>';
        }
        ?>
        
        <h2>📊 Transaction History</h2>
        <?php
        if (file_exists($transFile)) {
            $transactions = file_get_contents($transFile);
            $lines = array_reverse(explode("\n", trim($transactions)));
            $recent = array_slice($lines, 0, 50); // Show last 50 transactions
            
            echo '<div class="transactions">';
            echo htmlspecialchars(implode("\n", $recent));
            echo '</div>';
        } else {
            echo '<p>No transactions yet.</p>';
        }
        ?>
    </div>
</body>
</html>
