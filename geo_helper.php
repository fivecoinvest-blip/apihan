<?php
/**
 * Geo Helper - country gating by IP
 * Uses ip-api.com (free) to resolve countryCode with simple caching.
 */
require_once __DIR__ . '/redis_helper.php';

class GeoHelper {
    private static function getCfCountry() {
        $cf = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';
        if ($cf && preg_match('/^[A-Z]{2}$/', $cf)) return strtoupper($cf);
        $hdr = $_SERVER['HTTP_X_COUNTRY_CODE'] ?? '';
        if ($hdr && preg_match('/^[A-Z]{2}$/', $hdr)) return strtoupper($hdr);
        return '';
    }

    public static function getClientIp() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($parts[0]);
        }
        return $ip ?: '127.0.0.1';
    }

    public static function getCountryCode($ip) {
        $cacheKey = "geo:countryCode:" . $ip;
        $cache = RedisCache::getInstance();

        // Prefer edge-provided country first (Cloudflare, proxies)
        $edgeCode = self::getCfCountry();
        if ($edgeCode) {
            $cache->set($cacheKey, $edgeCode, 600);
            return $edgeCode;
        }

        // Try cache next
        $cached = $cache->get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Query external API (rate-limited to ~45/min free)
        $url = "http://ip-api.com/json/" . urlencode($ip) . "?fields=status,countryCode";
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 3,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ]
        ]);
        $resp = @file_get_contents($url, false, $ctx);
        $code = null;
        if ($resp) {
            $data = json_decode($resp, true);
            if (is_array($data) && ($data['status'] ?? '') === 'success') {
                $code = strtoupper($data['countryCode'] ?? '');
            }
        }

        if (!$code) {
            // Fallback unknown
            $code = 'XX';
        }

        // Cache for 10 minutes
        $cache->set($cacheKey, $code, 600);
        return $code;
    }

    public static function isAllowedCountry($requiredCode, $ip = null) {
        $ip = $ip ?: self::getClientIp();
        $code = self::getCountryCode($ip);
        return strtoupper($code) === strtoupper($requiredCode);
    }

    public static function enforceCountry($requiredCode) {
        $ip = self::getClientIp();
        if (!self::isIpAllowlisted($ip) && !self::isAllowedCountry($requiredCode, $ip)) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            echo "<!DOCTYPE html><html><head><title>Access Restricted</title><style>
                body{font-family:system-ui, -apple-system, Segoe UI, Roboto; background:#0f172a; color:#e2e8f0; display:flex; align-items:center; justify-content:center; height:100vh;}
                .card{background:#111827; padding:28px; border-radius:14px; box-shadow:0 20px 60px rgba(0,0,0,0.4); max-width:460px;}
                h1{margin:0 0 10px; font-size:22px;}
                p{margin:8px 0; color:#94a3b8;}
                .ip{color:#e5e7eb; font-weight:600;}
            </style></head><body><div class='card'>
            <h1>Access Restricted</h1>
            <p>Admin access is limited to Philippines IP addresses.</p>
            <p>Your IP: <span class='ip'>" . htmlspecialchars($ip) . "</span></p>
            <p>Detected Country: <span class='ip'>" . htmlspecialchars(self::getCountryCode($ip)) . "</span></p>
            <p>If you believe this is an error, contact support.</p>
            </div></body></html>";
            exit;
        }
    }

    private static function isIpAllowlisted($ip) {
        $file = __DIR__ . '/admin_allow_ips.txt';
        if (!is_file($file)) return false;
        $list = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$list) return false;
        foreach ($list as $line) {
            $allowed = trim($line);
            if ($allowed === '' || strpos($allowed, '#') === 0) continue;
            if (strcasecmp($allowed, $ip) === 0) return true;
        }
        return false;
    }
}

?>
