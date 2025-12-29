<?php
/**
 * Server Requirements Check
 * Run this file on your server to verify all requirements are met
 * 
 * Access: https://31.97.107.21/apihan/server_check.php
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Requirements Check</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0a0e27; color: #fff; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #ffc107; margin-bottom: 10px; }
        .subtitle { color: #aaa; margin-bottom: 30px; }
        .card { background: #1a1f3a; border-radius: 8px; padding: 20px; margin-bottom: 20px; border: 1px solid #2a2f4a; }
        .check-item { display: flex; align-items: center; padding: 12px; margin: 8px 0; background: #0a0e27; border-radius: 4px; }
        .check-icon { font-size: 24px; margin-right: 15px; min-width: 30px; }
        .check-pass { color: #4caf50; }
        .check-fail { color: #f44336; }
        .check-warn { color: #ff9800; }
        .check-content { flex: 1; }
        .check-title { font-weight: 600; margin-bottom: 4px; }
        .check-detail { font-size: 13px; color: #aaa; }
        .summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; }
        .summary-box { background: #1a1f3a; padding: 20px; border-radius: 8px; text-align: center; border: 2px solid #2a2f4a; }
        .summary-number { font-size: 32px; font-weight: bold; margin-bottom: 5px; }
        .summary-label { color: #aaa; font-size: 14px; }
        .pass-box { border-color: #4caf50; }
        .fail-box { border-color: #f44336; }
        .warn-box { border-color: #ff9800; }
        .btn { display: inline-block; background: #4caf50; color: white; padding: 12px 24px; border-radius: 4px; text-decoration: none; margin-top: 10px; }
        .btn:hover { background: #45a049; }
        pre { background: #0a0e27; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Server Requirements Check</h1>
        <p class="subtitle">Verifying server configuration for SoftAPI Integration</p>

        <?php
        $checks = [];
        $passed = 0;
        $failed = 0;
        $warnings = 0;

        // Check 1: PHP Version
        $phpVersion = PHP_VERSION;
        $phpVersionCheck = version_compare($phpVersion, '7.4.0', '>=');
        $checks[] = [
            'status' => $phpVersionCheck ? 'pass' : 'fail',
            'title' => 'PHP Version',
            'detail' => "Current: PHP $phpVersion | Required: 7.4+"
        ];
        $phpVersionCheck ? $passed++ : $failed++;

        // Check 2: OpenSSL Extension
        $opensslLoaded = extension_loaded('openssl');
        $checks[] = [
            'status' => $opensslLoaded ? 'pass' : 'fail',
            'title' => 'OpenSSL Extension',
            'detail' => $opensslLoaded ? 'Loaded - Required for encryption' : 'NOT LOADED - Install php-openssl'
        ];
        $opensslLoaded ? $passed++ : $failed++;

        // Check 3: cURL Extension
        $curlLoaded = extension_loaded('curl');
        $checks[] = [
            'status' => $curlLoaded ? 'pass' : 'fail',
            'title' => 'cURL Extension',
            'detail' => $curlLoaded ? 'Loaded - Required for API requests' : 'NOT LOADED - Install php-curl'
        ];
        $curlLoaded ? $passed++ : $failed++;

        // Check 4: JSON Extension
        $jsonLoaded = extension_loaded('json');
        $checks[] = [
            'status' => $jsonLoaded ? 'pass' : 'fail',
            'title' => 'JSON Extension',
            'detail' => $jsonLoaded ? 'Loaded - Required for data processing' : 'NOT LOADED - Install php-json'
        ];
        $jsonLoaded ? $passed++ : $failed++;

        // Check 5: PDO Extension (for database)
        $pdoLoaded = extension_loaded('pdo') && extension_loaded('pdo_mysql');
        $checks[] = [
            'status' => $pdoLoaded ? 'pass' : 'warn',
            'title' => 'PDO MySQL Extension',
            'detail' => $pdoLoaded ? 'Loaded - Ready for database operations' : 'Optional - Install php-pdo-mysql for database support'
        ];
        $pdoLoaded ? $passed++ : $warnings++;

        // Check 6: HTTPS/SSL
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
        $checks[] = [
            'status' => $isHttps ? 'pass' : 'warn',
            'title' => 'HTTPS/SSL Certificate',
            'detail' => $isHttps ? 'Enabled - Secure connection active' : 'Not detected - Install SSL certificate for production'
        ];
        $isHttps ? $passed++ : $warnings++;

        // Check 7: Logs Directory
        $logsDir = __DIR__ . '/logs';
        $logsWritable = is_dir($logsDir) && is_writable($logsDir);
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0777, true);
            $logsWritable = is_writable($logsDir);
        }
        $checks[] = [
            'status' => $logsWritable ? 'pass' : 'fail',
            'title' => 'Logs Directory',
            'detail' => $logsWritable ? 'Writable - Logs can be saved' : "NOT WRITABLE - Run: chmod 777 $logsDir"
        ];
        $logsWritable ? $passed++ : $failed++;

        // Check 8: File Permissions
        $configReadable = is_readable(__DIR__ . '/config.php');
        $checks[] = [
            'status' => $configReadable ? 'pass' : 'fail',
            'title' => 'File Permissions',
            'detail' => $configReadable ? 'Correct - Files are accessible' : 'INCORRECT - Run: chmod 755 *.php'
        ];
        $configReadable ? $passed++ : $failed++;

        // Check 9: Memory Limit
        $memoryLimit = ini_get('memory_limit');
        $memoryValue = (int)$memoryLimit;
        $memoryCheck = $memoryValue >= 128 || $memoryLimit === '-1';
        $checks[] = [
            'status' => $memoryCheck ? 'pass' : 'warn',
            'title' => 'PHP Memory Limit',
            'detail' => "Current: $memoryLimit | Recommended: 128M+"
        ];
        $memoryCheck ? $passed++ : $warnings++;

        // Check 10: Max Execution Time
        $maxExecTime = ini_get('max_execution_time');
        $execCheck = $maxExecTime >= 30 || $maxExecTime == 0;
        $checks[] = [
            'status' => $execCheck ? 'pass' : 'warn',
            'title' => 'Max Execution Time',
            'detail' => "Current: {$maxExecTime}s | Recommended: 30s+"
        ];
        $execCheck ? $passed++ : $warnings++;

        // Check 11: Web Server
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
        $checks[] = [
            'status' => 'pass',
            'title' => 'Web Server',
            'detail' => $serverSoftware
        ];
        $passed++;

        // Check 12: Timezone
        $timezone = date_default_timezone_get();
        $checks[] = [
            'status' => 'pass',
            'title' => 'Timezone Configuration',
            'detail' => $timezone
        ];
        $passed++;

        // Calculate total
        $total = $passed + $failed + $warnings;
        ?>

        <!-- Summary -->
        <div class="summary">
            <div class="summary-box pass-box">
                <div class="summary-number check-pass"><?php echo $passed; ?></div>
                <div class="summary-label">Passed</div>
            </div>
            <div class="summary-box fail-box">
                <div class="summary-number check-fail"><?php echo $failed; ?></div>
                <div class="summary-label">Failed</div>
            </div>
            <div class="summary-box warn-box">
                <div class="summary-number check-warn"><?php echo $warnings; ?></div>
                <div class="summary-label">Warnings</div>
            </div>
        </div>

        <!-- Results -->
        <div class="card">
            <h2 style="color: #4caf50; margin-bottom: 20px;">📋 Detailed Results</h2>
            <?php foreach ($checks as $check): ?>
                <div class="check-item">
                    <div class="check-icon check-<?php echo $check['status']; ?>">
                        <?php 
                        echo $check['status'] === 'pass' ? '✅' : 
                             ($check['status'] === 'fail' ? '❌' : '⚠️');
                        ?>
                    </div>
                    <div class="check-content">
                        <div class="check-title"><?php echo $check['title']; ?></div>
                        <div class="check-detail"><?php echo $check['detail']; ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- PHP Info -->
        <div class="card">
            <h2 style="color: #2196f3; margin-bottom: 15px;">📊 Server Information</h2>
            <pre><?php
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Path: " . __DIR__ . "\n";
echo "Server IP: " . ($_SERVER['SERVER_ADDR'] ?? 'Unknown') . "\n";
echo "Timezone: " . date_default_timezone_get() . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "s\n";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";
            ?></pre>
        </div>

        <!-- Recommendations -->
        <?php if ($failed > 0 || $warnings > 0): ?>
        <div class="card" style="border-left: 4px solid #ff9800;">
            <h2 style="color: #ff9800; margin-bottom: 15px;">⚡ Recommendations</h2>
            
            <?php if ($failed > 0): ?>
            <h3 style="color: #f44336; margin: 15px 0 10px 0;">Critical Issues (Must Fix):</h3>
            <pre style="color: #f44336;">
<?php
if (!$phpVersionCheck) echo "❌ Upgrade PHP to 7.4 or higher\n";
if (!$opensslLoaded) echo "❌ Install OpenSSL: apt-get install php-openssl (Ubuntu) or yum install php-openssl (CentOS)\n";
if (!$curlLoaded) echo "❌ Install cURL: apt-get install php-curl (Ubuntu) or yum install php-curl (CentOS)\n";
if (!$jsonLoaded) echo "❌ Install JSON: apt-get install php-json (Ubuntu) or yum install php-json (CentOS)\n";
if (!$logsWritable) echo "❌ Fix logs directory: chmod 777 " . __DIR__ . "/logs\n";
if (!$configReadable) echo "❌ Fix file permissions: chmod 755 " . __DIR__ . "/*.php\n";
?>
After installing extensions, restart web server:
  systemctl restart apache2  # For Apache
  systemctl restart nginx     # For Nginx
            </pre>
            <?php endif; ?>

            <?php if ($warnings > 0): ?>
            <h3 style="color: #ff9800; margin: 15px 0 10px 0;">Warnings (Recommended):</h3>
            <pre style="color: #ff9800;">
<?php
if (!$pdoLoaded) echo "⚠️  Install PDO MySQL for database: apt-get install php-pdo php-mysql\n";
if (!$isHttps) echo "⚠️  Install SSL certificate: Use Let's Encrypt (certbot) for free SSL\n";
if (!$memoryCheck) echo "⚠️  Increase memory limit in php.ini: memory_limit = 128M\n";
if (!$execCheck) echo "⚠️  Increase max execution time in php.ini: max_execution_time = 30\n";
?>
            </pre>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Final Status -->
        <div class="card" style="border-left: 4px solid <?php echo $failed === 0 ? '#4caf50' : '#f44336'; ?>; text-align: center;">
            <?php if ($failed === 0): ?>
                <h2 style="color: #4caf50; margin-bottom: 10px;">🎉 Server Ready!</h2>
                <p style="color: #aaa; margin-bottom: 20px;">All critical requirements are met. You can proceed with testing.</p>
                <a href="index.php" class="btn">🚀 Go to Launch Interface</a>
            <?php else: ?>
                <h2 style="color: #f44336; margin-bottom: 10px;">⚠️ Server Not Ready</h2>
                <p style="color: #aaa; margin-bottom: 20px;"><?php echo $failed; ?> critical issue(s) must be fixed before proceeding.</p>
                <a href="server_check.php" class="btn">🔄 Refresh Check</a>
            <?php endif; ?>
        </div>

        <!-- Quick Commands -->
        <div class="card">
            <h2 style="color: #4caf50; margin-bottom: 15px;">🔧 Quick Fix Commands</h2>
            <h3 style="color: #aaa; margin: 10px 0;">Ubuntu/Debian:</h3>
            <pre>sudo apt-get update
sudo apt-get install php php-openssl php-curl php-json php-pdo php-mysql
sudo systemctl restart apache2</pre>
            
            <h3 style="color: #aaa; margin: 10px 0;">CentOS/RHEL:</h3>
            <pre>sudo yum install php php-openssl php-curl php-json php-pdo php-mysqlnd
sudo systemctl restart httpd</pre>
            
            <h3 style="color: #aaa; margin: 10px 0;">Set Permissions:</h3>
            <pre>cd /var/www/html/apihan
mkdir -p logs
chmod 755 *.php
chmod 777 logs
chown -R www-data:www-data .</pre>
        </div>
    </div>
</body>
</html>
