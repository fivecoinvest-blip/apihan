<?php
require_once '/var/www/html/wpay_helper.php';

$wpay = new WPayHelper();
$params = [
    'mchId' => '5047',
    'currency' => 'PHP',
    'out_trade_no' => 'TST' . time(),
    'pay_type' => 'GCASH',
    'money' => 100,
    'notify_url' => 'https://example.com/callback',
    'returnUrl' => 'https://example.com/return'
];
$params['sign'] = $wpay->generateSign($params);
$res = $wpay->sendRequest('/v1/Collect', $params);
echo ($res['code'] == 0 ? 'SUCCESS' : 'FAILED') . "\n";
?>
