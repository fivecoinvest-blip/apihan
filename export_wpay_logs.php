<?php
// Export WPay payment logs (deposits, withdrawals, callbacks)
// Requires admin authentication

session_start();
require_once __DIR__ . '/wpay_config.php';
require_once __DIR__ . '/db_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $sinceHours = isset($_GET['hours']) ? max(1, intval($_GET['hours'])) : 72; // default 72h
    $sinceExpr = "NOW() - INTERVAL {$sinceHours} HOUR";

    $logsDir = __DIR__ . '/logs';
    if (!file_exists($logsDir)) {
        mkdir($logsDir, 0755, true);
    }

    $ts = date('Ymd_His');
    $depositCsv = $logsDir . "/deposits_{$ts}.csv";
    $withdrawCsv = $logsDir . "/withdrawals_{$ts}.csv";
    $callbackCopy = $logsDir . "/wpay_callback_{$ts}.log";
    $zipPath = $logsDir . "/wpay_logs_{$ts}.zip";

    // Helper to write CSV properly
    $writeCsv = function(string $path, array $headers, array $rows) {
        $fh = fopen($path, 'w');
        if (!$fh) throw new Exception('Cannot write file: ' . $path);
        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $h) {
                $val = $row[$h] ?? '';
                if (is_array($val)) $val = json_encode($val, JSON_UNESCAPED_SLASHES);
                // Normalize whitespace in notify_data to single-line
                if ($h === 'notify_data' && is_string($val)) {
                    $val = preg_replace('/[\r\n\t]+/', ' ', $val);
                }
                $line[] = $val;
            }
            fputcsv($fh, $line);
        }
        fclose($fh);
    };

    // Fetch deposits
    $stmt = $pdo->query("SELECT id,user_id,out_trade_no,transaction_id,amount,currency,pay_type,status,callback_count,created_at,updated_at,completed_at,notify_data FROM payment_transactions WHERE created_at >= {$sinceExpr} ORDER BY id DESC LIMIT 1000");
    $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $writeCsv($depositCsv, ['id','user_id','out_trade_no','transaction_id','amount','currency','pay_type','status','callback_count','created_at','updated_at','completed_at','notify_data'], $deposits);

    // Fetch withdrawals
    $stmt = $pdo->query("SELECT id,user_id,out_trade_no,transaction_id,amount,currency,pay_type,account,account_name,bank_code,status,callback_count,created_at,updated_at,completed_at,notify_data FROM withdrawal_transactions WHERE created_at >= {$sinceExpr} ORDER BY id DESC LIMIT 1000");
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $writeCsv($withdrawCsv, ['id','user_id','out_trade_no','transaction_id','amount','currency','pay_type','account','account_name','bank_code','status','callback_count','created_at','updated_at','completed_at','notify_data'], $withdrawals);

    // Copy callback log (last 5,000 lines max)
    $cbPath = __DIR__ . '/logs/wpay_callback.log';
    if (file_exists($cbPath)) {
        // Read efficiently
        $lines = @file($cbPath);
        if ($lines !== false) {
            $slice = array_slice($lines, -5000);
            file_put_contents($callbackCopy, implode('', $slice));
        }
    }

    // Create ZIP
    $filesToZip = [];
    if (file_exists($depositCsv)) $filesToZip[] = $depositCsv;
    if (file_exists($withdrawCsv)) $filesToZip[] = $withdrawCsv;
    if (file_exists($callbackCopy)) $filesToZip[] = $callbackCopy;

    $zipOk = false;
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($filesToZip as $fp) {
                $zip->addFile($fp, basename($fp));
            }
            $zip->close();
            $zipOk = true;
        }
    }

    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    $resp = [
        'success' => true,
        'hours' => $sinceHours,
        'files' => [
            'deposits_csv' => $baseUrl . '/logs/' . basename($depositCsv),
            'withdrawals_csv' => $baseUrl . '/logs/' . basename($withdrawCsv),
            'callback_log_copy' => file_exists($callbackCopy) ? $baseUrl . '/logs/' . basename($callbackCopy) : null,
            'zip_bundle' => $zipOk ? $baseUrl . '/logs/' . basename($zipPath) : null,
        ],
        'count' => [
            'deposits' => count($deposits),
            'withdrawals' => count($withdrawals)
        ]
    ];

    echo json_encode($resp, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
