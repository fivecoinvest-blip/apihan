<?php
/**
 * Test WPay with fresh file - no includes, direct class definition
 */

class SimpleWPay {
    private $host = 'https://api.wpay.life';
    private $mchId = '5047';
    private $key = 'c05a23c7e62d158abe573a0cca660b12';
    
    public function test() {
        $params = [
            'mchId' => $this->mchId,
            'currency' => 'PHP',
            'out_trade_no' => 'TEST' . time(),
            'pay_type' => 'GCASH',
            'money' => 100,
            'notify_url' => 'https://paldo88.site/wpay_callback.php',
            'returnUrl' => 'https://paldo88.site/payment_status.php'
        ];
        
        // Add signature
        ksort($params);
        $signStr = '';
        foreach ($params as $key => $val) {
            if ($val !== '' && $val !== null) {
                $signStr .= "{$key}={$val}&";
            }
        }
        $signStr .= "key=" . $this->key;
        $params['sign'] = md5($signStr);
        
        $url = $this->host . '/v1/Collect';
        $postData = http_build_query($params);
        
        echo "Testing from fresh class...\n";
        echo "URL: $url\n";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "HTTP Code: $httpCode\n";
        echo "Response: $response\n";
        
        return $httpCode === 200;
    }
}

$wpay = new SimpleWPay();
$result = $wpay->test();

echo "\n" . ($result ? "✅ SUCCESS" : "❌ FAILED") . "\n";
