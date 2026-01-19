<?php
$ts = time();
$params = [
    'mchId' => '5047',
    'money' => '100',
    'notifyUrl' => 'https://paldo88.site/wpay_callback.php',
    'out_trade_no' => 'IPV4_RETEST_' . $ts,
    'pay_type' => 'GCASH',
    'phone' => '9171234567',
    'revers1' => 'test'
];
$signStr = $params['mchId'] . $params['money'] . $params['notifyUrl'] . $params['out_trade_no'] . $params['pay_type'] . $params['phone'] . $params['revers1'] . 'c05a23c7e62d158abe573a0cca660b12';
$params['sign'] = md5($signStr);
$qs = http_build_query($params);
file_put_contents('/tmp/wpay_payload.txt', $qs);
echo $qs;
