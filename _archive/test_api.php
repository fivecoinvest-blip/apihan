<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SoftAPI - Launch Game Demo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0a0e27; color: #fff; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #ffc107; margin-bottom: 10px; font-size: 2em; }
        h2 { color: #4caf50; margin: 30px 0 15px 0; font-size: 1.5em; }
        h3 { color: #2196f3; margin: 20px 0 10px 0; }
        .subtitle { color: #aaa; margin-bottom: 30px; }
        .card { background: #1a1f3a; border-radius: 8px; padding: 20px; margin-bottom: 20px; border: 1px solid #2a2f4a; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #2a2f4a; }
        th { background: #2a2f4a; color: #ffc107; font-weight: 600; }
        td:first-child { color: #4caf50; font-family: 'Courier New', monospace; }
        td:nth-child(3) { color: #2196f3; font-family: 'Courier New', monospace; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #aaa; font-size: 14px; }
        input, select { width: 100%; padding: 10px; background: #0a0e27; border: 1px solid #2a2f4a; border-radius: 4px; color: #fff; font-size: 14px; }
        input:focus, select:focus { outline: none; border-color: #4caf50; }
        .btn { background: #4caf50; color: white; padding: 12px 30px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: 600; margin-top: 10px; }
        .btn:hover { background: #45a049; }
        .btn-secondary { background: #2196f3; }
        .btn-secondary:hover { background: #1976d2; }
        .result { background: #1a1f3a; border-left: 4px solid #4caf50; padding: 15px; margin-top: 20px; border-radius: 4px; }
        .error { border-left-color: #f44336; }
        pre { background: #0a0e27; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        .highlight { color: #ffc107; font-weight: 600; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-required { background: #f44336; }
        .badge-optional { background: #ff9800; }
        .code { background: #0a0e27; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; color: #4caf50; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎮 SoftAPI Game Launch Implementation</h1>
        <p class="subtitle">Complete API integration for launching game sessions with encrypted user data</p>

        <!-- What You Send in the Request -->
        <div class="card">
            <h2>📤 WHAT YOU SEND IN THE REQUEST</h2>
            <p style="color: #aaa; margin-bottom: 20px;">These parameters are encrypted and sent to SoftAPI to launch a game session</p>
            
            <table>
                <thead>
                    <tr>
                        <th>PARAMETER</th>
                        <th>MEANING</th>
                        <th>EXAMPLE</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>user_id <span class="badge badge-required">REQUIRED</span></td>
                        <td>Unique user ID in your system</td>
                        <td>23213</td>
                    </tr>
                    <tr>
                        <td>balance <span class="badge badge-required">REQUIRED</span></td>
                        <td>User's current balance</td>
                        <td>40</td>
                    </tr>
                    <tr>
                        <td>game_uid <span class="badge badge-required">REQUIRED</span></td>
                        <td>Unique ID for this game session</td>
                        <td>3978</td>
                    </tr>
                    <tr>
                        <td>token <span class="badge badge-required">REQUIRED</span></td>
                        <td>Your API token</td>
                        <td>tptogkzflsbmrmolhdpebzxvkxfsbekq</td>
                    </tr>
                    <tr>
                        <td>timestamp <span class="badge badge-required">REQUIRED</span></td>
                        <td>Current timestamp in milliseconds</td>
                        <td>1696329392000</td>
                    </tr>
                    <tr>
                        <td>return <span class="badge badge-required">REQUIRED</span></td>
                        <td>Return URL after game closes</td>
                        <td>https://google.com/return</td>
                    </tr>
                    <tr>
                        <td>callback <span class="badge badge-required">REQUIRED</span></td>
                        <td>Callback URL for game results</td>
                        <td>https://yourdomain.com/callback.php</td>
                    </tr>
                    <tr>
                        <td>currency_code <span class="badge badge-optional">OPTIONAL</span></td>
                        <td>Currency code (e.g., BDT). Code will work without this.</td>
                        <td>BDT</td>
                    </tr>
                    <tr>
                        <td>language <span class="badge badge-optional">OPTIONAL</span></td>
                        <td>Language code (e.g., bn). Code will work without this.</td>
                        <td>bn</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Launch Game Form -->
        <div class="card">
            <h2>🚀 Launch Game - Live Test</h2>
            <p style="color: #aaa; margin-bottom: 15px;">Note: <span class="code">currency_code</span> and <span class="code">language</span> are optional. Code will work without them.</p>
            <form id="launchForm" method="POST" action="">
                <div class="grid">
                    <div class="form-group">
                        <label>User ID <span class="badge badge-required">REQUIRED</span></label>
                        <input type="text" name="user_id" value="23213" required>
                    </div>
                    <div class="form-group">
                        <label>Balance <span class="badge badge-required">REQUIRED</span></label>
                        <input type="text" name="balance" value="50" required>
                    </div>
                    <div class="form-group">
                        <label>Game UID <span class="badge badge-required">REQUIRED</span></label>
                        <input type="text" name="game_uid" value="634" required>
                    </div>
                    <div class="form-group">
                        <label>Currency Code <span class="badge badge-optional">OPTIONAL</span></label>
                        <select name="currency_code">
                            <option value="">-- Select Currency --</option>
                            <?php
                            require_once __DIR__ . '/currency_codes.php';
                            $popularCurrencies = getPopularCurrencies();
                            foreach ($popularCurrencies as $code => $name) {
                                $selected = ($code === 'BDT') ? 'selected' : '';
                                echo "<option value=\"{$code}\" {$selected}>{$code} - {$name}</option>\n";
                            }
                            ?>
                        </select>
                        <small style="color: #888; display: block; margin-top: 5px;">
                            <a href="currency_codes.php" target="_blank" style="color: #4caf50;">View all 170+ supported currencies</a>
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Language <span class="badge badge-optional">OPTIONAL</span></label>
                        <select name="language">
                            <option value="">-- Select Language --</option>
                            <?php
                            require_once __DIR__ . '/language_codes.php';
                            $popular = getPopularLanguages();
                            foreach ($popular as $code => $name) {
                                $selected = ($code === 'bn') ? 'selected' : '';
                                echo "<option value=\"{$code}\" {$selected}>{$code} - {$name}</option>\n";
                            }
                            ?>
                        </select>
                        <small style="color: #888; display: block; margin-top: 5px;">
                            <a href="language_codes.php" target="_blank" style="color: #4caf50;">View all 200+ supported languages</a>
                        </small>
                    </div>
                </div>
                <button type="submit" class="btn" name="launch_game">🎮 Launch Game</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('debugInfo').style.display='block'">📊 Show Request Details</button>
            </form>
        </div>

        <?php
        require_once __DIR__ . '/api_request_builder.php';
        require_once __DIR__ . '/balance_helper.php';
        
        if (isset($_POST['launch_game'])) {
            $userId = $_POST['user_id'];
            $balance = $_POST['balance'];
            $gameUid = $_POST['game_uid'];
            $currencyCode = !empty($_POST['currency_code']) ? $_POST['currency_code'] : null;
            $language = !empty($_POST['language']) ? $_POST['language'] : null;
            
            // Initialize user balance before launching
            setUserBalance($userId, (float)$balance);
            
            // Create request
            $params = createGameLaunchRequest($userId, $balance, $gameUid, $currencyCode, $language);
            
            // Send request
            $result = sendLaunchGameRequest($params);
            
            if ($result['success']) {
                echo '<div class="card result">';
                echo '<h3>✅ Game Launched Successfully!</h3>';
                echo '<p><strong>Game URL:</strong></p>';
                echo '<pre>' . htmlspecialchars($result['game_url']) . '</pre>';
                echo '<a href="' . htmlspecialchars($result['game_url']) . '" target="_blank" class="btn" style="text-decoration: none;">🎮 Open Game in New Window</a>';
                echo '</div>';
            } else {
                echo '<div class="card result error">';
                echo '<h3>❌ Launch Failed</h3>';
                echo '<p><strong>Error:</strong> ' . htmlspecialchars($result['error']) . '</p>';
                if (isset($result['error_code'])) {
                    echo '<p><strong>Error Code:</strong> ' . $result['error_code'] . '</p>';
                }
                echo '</div>';
            }
            
            // Debug info
            echo '<div id="debugInfo" class="card" style="display: block;">';
            echo '<h3>📊 Request Details</h3>';
            echo '<h4>Parameters Sent:</h4>';
            echo '<pre>' . json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</pre>';
            echo '<h4>Full Response:</h4>';
            echo '<pre>' . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</pre>';
            echo '</div>';
        }
        ?>

        <div id="debugInfo" class="card" style="display: none;">
            <h3>📊 Current Configuration</h3>
            <pre><?php
echo "API Token: " . API_TOKEN . "\n";
echo "API Secret: " . substr(API_SECRET, 0, 8) . "************************\n";
echo "Server URL: " . SERVER_URL . "\n";
echo "Return URL: " . RETURN_URL . "\n";
echo "Callback URL: " . CALLBACK_URL . "\n";
echo "Timezone: " . date_default_timezone_get();
            ?></pre>
        </div>
    </div>
</body>
</html>
