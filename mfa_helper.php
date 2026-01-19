<?php
/**
 * MFA Helper - Google Authenticator (TOTP)
 * Pure PHP implementation for generating secrets and verifying codes.
 */

class MFAHelper {
    // Base32 alphabet (RFC 4648)
    private static $base32Alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // Generate a random Base32-encoded secret (default 16 bytes => 26 chars)
    public static function generateSecret($lengthBytes = 16) {
        $random = random_bytes($lengthBytes);
        return self::base32Encode($random);
    }

    // Build otpauth URI for QR code
    public static function buildOtpAuthUri($issuer, $accountName, $secret, $period = 30, $digits = 6) {
        $label = rawurlencode($issuer . ':' . $accountName);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'period' => $period,
            'digits' => $digits,
        ]);
        return "otpauth://totp/{$label}?{$params}";
    }

    // Verify a TOTP code for a given secret
    public static function verifyCode($secret, $code, $window = 1, $period = 30, $digits = 6) {
        $code = trim($code);
        if (!preg_match('/^\d{'.$digits.'}$/', $code)) {
            return false;
        }

        $secretBytes = self::base32Decode($secret);
        if ($secretBytes === false) {
            return false;
        }

        $timeSlice = floor(time() / $period);

        // allow +/- window slices for clock drift
        for ($i = -$window; $i <= $window; $i++) {
            $calc = self::calculateCodeForSlice($secretBytes, $timeSlice + $i, $digits);
            if (hash_equals($calc, $code)) {
                return true;
            }
        }
        return false;
    }

    private static function calculateCodeForSlice($secretBytes, $slice, $digits) {
        $binaryTime = pack('N*', 0) . pack('N*', $slice);
        $hash = hash_hmac('sha1', $binaryTime, $secretBytes, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncatedHash = unpack('N', substr($hash, $offset, 4));
        $value = $truncatedHash[1] & 0x7FFFFFFF;
        $modulo = 10 ** $digits;
        $hotp = $value % $modulo;
        return str_pad((string)$hotp, $digits, '0', STR_PAD_LEFT);
    }

    // Base32 encode
    private static function base32Encode($data) {
        if ($data === '') return '';
        $alphabet = self::$base32Alphabet;
        $binaryString = '';
        foreach (str_split($data) as $char) {
            $binaryString .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $output = '';
        $chunks = str_split($binaryString, 5);
        foreach ($chunks as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $output .= $alphabet[bindec($chunk)];
        }
        // pad with '=' to multiple of 8 chars
        while (strlen($output) % 8 !== 0) {
            $output .= '=';
        }
        return $output;
    }

    // Base32 decode
    private static function base32Decode($data) {
        $data = strtoupper($data);
        $data = rtrim($data, '=');
        $alphabet = self::$base32Alphabet;
        $binaryString = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $chr = $data[$i];
            $pos = strpos($alphabet, $chr);
            if ($pos === false) {
                return false;
            }
            $binaryString .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        $chunks = str_split($binaryString, 8);
        foreach ($chunks as $chunk) {
            if (strlen($chunk) < 8) {
                continue;
            }
            $bytes .= chr(bindec($chunk));
        }
        return $bytes;
    }
}

?>
