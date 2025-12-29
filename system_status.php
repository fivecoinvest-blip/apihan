<!DOCTYPE html>
<html>
<head>
    <title>Casino System Status</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: monospace; 
            background: #0f172a; 
            color: #fff; 
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { margin-bottom: 30px; color: #3b82f6; }
        .section { 
            background: #1e293b; 
            padding: 20px; 
            margin-bottom: 20px; 
            border-radius: 10px;
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            margin: 5px 0;
            background: #334155;
            border-radius: 5px;
        }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        pre { 
            background: #0a0f1e; 
            padding: 15px; 
            border-radius: 5px; 
            overflow-x: auto;
            margin-top: 10px;
        }
        .btn {
            background: #3b82f6;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        .btn:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎰 Casino System Status</h1>
        
        <div class="section">
            <h2>📊 System Health</h2>
            <?php
            require_once 'config.php';
            
            // Test Database
            try {
                $pdo = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                    DB_USER, DB_PASS
                );
                echo '<div class="status-item"><span>Database</span><span class="success">✅ Connected</span></div>';
                
                $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $gameCount = $pdo->query("SELECT COUNT(*) FROM games WHERE is_active = 1")->fetchColumn();
                echo '<div class="status-item"><span>Active Users</span><span>' . $userCount . '</span></div>';
                echo '<div class="status-item"><span>Active Games</span><span>' . $gameCount . '</span></div>';
            } catch (Exception $e) {
                echo '<div class="status-item"><span>Database</span><span class="error">❌ ' . $e->getMessage() . '</span></div>';
            }
            
            // Test Redis
            try {
                $redis = new Redis();
                $redis->connect('127.0.0.1', 6379);
                $redis->ping();
                echo '<div class="status-item"><span>Redis Cache</span><span class="success">✅ Running</span></div>';
                
                $info = $redis->info();
                echo '<div class="status-item"><span>Redis Memory</span><span>' . $info['used_memory_human'] . '</span></div>';
                echo '<div class="status-item"><span>Cache Hits</span><span>' . ($info['keyspace_hits'] ?? 0) . '</span></div>';
                echo '<div class="status-item"><span>Cache Misses</span><span>' . ($info['keyspace_misses'] ?? 0) . '</span></div>';
            } catch (Exception $e) {
                echo '<div class="status-item"><span>Redis Cache</span><span class="error">❌ Not Available</span></div>';
            }
            
            // Test Sessions
            session_start();
            echo '<div class="status-item"><span>PHP Sessions</span><span class="success">✅ Working</span></div>';
            echo '<div class="status-item"><span>Session ID</span><span>' . session_id() . '</span></div>';
            ?>
        </div>
        
        <div class="section">
            <h2>🔧 Configuration</h2>
            <div class="status-item"><span>API Token</span><span><?php echo substr(API_TOKEN, 0, 10); ?>...</span></div>
            <div class="status-item"><span>Server URL</span><span><?php echo SERVER_URL; ?></span></div>
            <div class="status-item"><span>Return URL</span><span><?php echo RETURN_URL; ?></span></div>
            <div class="status-item"><span>Callback URL</span><span><?php echo CALLBACK_URL; ?></span></div>
        </div>
        
        <div class="section">
            <h2>📡 Endpoint Tests</h2>
            <button class="btn" onclick="testEndpoint('callback.php', 'POST', {game_uid:'test',game_round:'test',member_account:'1'})">Test Callback</button>
            <button class="btn" onclick="testEndpoint('get_balance.php', 'GET')">Test Balance API</button>
            <button class="btn" onclick="testEndpoint('keep_alive.php', 'GET')">Test Keep-Alive</button>
            <pre id="test-result">Click a button to test endpoints...</pre>
        </div>
        
        <div class="section">
            <h2>📝 Recent Logs</h2>
            <h3>Callback Log:</h3>
            <pre><?php
            $logFile = __DIR__ . '/logs/api_callback.log';
            if (file_exists($logFile)) {
                $lines = file($logFile);
                echo htmlspecialchars(implode('', array_slice($lines, -10)));
            } else {
                echo 'No callback logs yet';
            }
            ?></pre>
        </div>
        
        <div class="section">
            <h2>⚙️ Actions</h2>
            <button class="btn" onclick="clearCache()">Clear Redis Cache</button>
            <button class="btn" onclick="location.reload()">Refresh Status</button>
            <button class="btn" onclick="location.href='index.php'">Back to Casino</button>
        </div>
    </div>
    
    <script>
        function testEndpoint(url, method, data = null) {
            const resultEl = document.getElementById('test-result');
            resultEl.textContent = `Testing ${url}...`;
            
            const options = {
                method: method,
                headers: {'Content-Type': 'application/json'}
            };
            
            if (data && method === 'POST') {
                options.body = JSON.stringify(data);
            }
            
            fetch(url, options)
                .then(response => response.text())
                .then(text => {
                    resultEl.textContent = `✅ ${url} (${method})\n\nResponse:\n${text}`;
                })
                .catch(error => {
                    resultEl.textContent = `❌ ${url} failed:\n${error.message}`;
                });
        }
        
        function clearCache() {
            if (confirm('Clear all Redis cache?')) {
                fetch('admin.php?action=clear_cache')
                    .then(() => alert('Cache cleared!'))
                    .catch(error => alert('Failed: ' + error.message));
            }
        }
    </script>
</body>
</html>
