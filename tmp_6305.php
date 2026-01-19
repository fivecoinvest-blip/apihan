<?php
require_once 'config.php';
require_once 'api_request_builder.php';

$params = createGameLaunchRequest('1', '1000', '6305', 'PHP', 'ph');
$res = sendLaunchGameRequest($params);

if ($res['success']) {
    echo 'URL=' . $res['game_url'] . "\n";
    $ch = curl_init($res['game_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $content = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo 'HTTP ' . $http . "\n";
    echo 'Content size ' . strlen($content) . "\n";
    if (stripos($content, 'GAME NOT FOUND') !== false) {
        echo "Result: GAME NOT FOUND\n";
    } elseif (stripos($content, 'Currency restriction') !== false || stripos($content, 'cannot offer games') !== false) {
        echo "Result: Currency restriction\n";
    } elseif (stripos($content, 'System error') !== false) {
        echo "Result: System error\n";
    } else {
        echo 'Preview: ' . substr(strip_tags($content), 0, 200) . "\n";
    }
} else {
    echo 'API FAIL: ' . ($res['error'] ?? '');
}
