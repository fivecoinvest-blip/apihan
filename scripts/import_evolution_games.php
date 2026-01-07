<?php
/**
 * Evolution Games Importer
 * - Fetches Evolution games JSON from brand_id=59
 * - Upserts into `games` table with provider = 'Evolution'
 * - Downloads images OR generates default placeholder for missing images
 */

// Load config/constants if available
require_once __DIR__ . '/../config.php';

$dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
$dbName = defined('DB_NAME') ? DB_NAME : 'casino_db';
$dbUser = defined('DB_USER') ? DB_USER : 'casino_user';
$dbPass = defined('DB_PASS') ? DB_PASS : 'casino123';

$apiUrl = 'https://igamingapis.com/provider/brands.php?brand_id=59';

function curl_get($url, $timeout = 15) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Evolution-Importer/1.0'
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code >= 400) {
        throw new Exception('HTTP error fetching ' . $url . ' (code ' . $code . '): ' . $err);
    }
    return $body;
}

function ensure_dir($path) {
    if (!is_dir($path)) {
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new Exception('Failed to create directory: ' . $path);
        }
    }
}

function generate_default_image($outputPath, $gameName) {
    // Create a simple 400x300 placeholder image with game name
    $width = 400;
    $height = 300;
    $img = imagecreatetruecolor($width, $height);
    
    // Gradient background (dark blue to lighter blue)
    $color1 = imagecolorallocate($img, 30, 40, 80);
    $color2 = imagecolorallocate($img, 50, 80, 150);
    
    for ($y = 0; $y < $height; $y++) {
        $ratio = $y / $height;
        $r = (int)((1 - $ratio) * 30 + $ratio * 50);
        $g = (int)((1 - $ratio) * 40 + $ratio * 80);
        $b = (int)((1 - $ratio) * 80 + $ratio * 150);
        $color = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $width, $y, $color);
    }
    
    // Text colors
    $white = imagecolorallocate($img, 255, 255, 255);
    $accent = imagecolorallocate($img, 255, 215, 0); // Gold
    
    // Add "Evolution" watermark at top
    $fontPath = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
    if (!file_exists($fontPath)) {
        $fontPath = null; // Fallback to built-in font
    }
    
    if ($fontPath) {
        imagettftext($img, 12, 0, 20, 30, $accent, $fontPath, 'EVOLUTION');
    } else {
        imagestring($img, 3, 20, 15, 'EVOLUTION', $accent);
    }
    
    // Word wrap game name
    $maxLineLength = 30;
    $words = explode(' ', $gameName);
    $lines = [];
    $currentLine = '';
    
    foreach ($words as $word) {
        $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
        if (strlen($testLine) > $maxLineLength && $currentLine !== '') {
            $lines[] = $currentLine;
            $currentLine = $word;
        } else {
            $currentLine = $testLine;
        }
    }
    if ($currentLine !== '') {
        $lines[] = $currentLine;
    }
    
    // Limit to 3 lines
    $lines = array_slice($lines, 0, 3);
    
    // Center text vertically
    $lineHeight = 30;
    $totalTextHeight = count($lines) * $lineHeight;
    $startY = ($height - $totalTextHeight) / 2 + 20;
    
    foreach ($lines as $i => $line) {
        $y = $startY + ($i * $lineHeight);
        if ($fontPath) {
            $bbox = imagettfbbox(18, 0, $fontPath, $line);
            $textWidth = $bbox[2] - $bbox[0];
            $x = ($width - $textWidth) / 2;
            imagettftext($img, 18, 0, (int)$x, (int)$y, $white, $fontPath, $line);
        } else {
            $textWidth = strlen($line) * 10;
            $x = ($width - $textWidth) / 2;
            imagestring($img, 5, (int)$x, (int)$y, $line, $white);
        }
    }
    
    // Save as PNG
    imagepng($img, $outputPath);
    imagedestroy($img);
}

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "🎰 Evolution Games Importer\n";
    echo "===========================\n\n";

    echo "📥 Fetching Evolution games JSON...\n";
    $jsonRaw = curl_get($apiUrl, 30);
    $data = json_decode($jsonRaw, true);
    if (!is_array($data)) {
        throw new Exception('Unexpected API response');
    }
    // Extract games array from response
    $games = $data['games'] ?? $data ?? [];
    if (!is_array($games)) {
        throw new Exception('Games array not found in API response');
    }
    echo "✓ Found " . count($games) . " games in feed\n\n";

    // Prepare filesystem
    $baseRel = 'images/games/evolution';
    $baseAbs = __DIR__ . '/../images/games/evolution';
    ensure_dir($baseAbs);

    $new = 0; $updated = 0; $imgOk = 0; $imgGenerated = 0; $imgFail = 0; $errors = 0;

    // Prepare statements
    $checkStmt = $pdo->prepare("SELECT id FROM games WHERE game_uid = ? AND provider = 'Evolution'");
    $insertStmt = $pdo->prepare("INSERT INTO games (game_uid, name, provider, category, image) VALUES (?, ?, 'Evolution', ?, ?)");
    $updateStmt = $pdo->prepare("UPDATE games SET name = ?, category = ?, image = ? WHERE id = ?");

    foreach ($games as $g) {
        try {
            $code = (string)($g['game_code'] ?? $g['gameID'] ?? '');
            $name = $g['game_name'] ?? $g['gameNameEn'] ?? '';
            $name = preg_replace('/\s+/', ' ', trim($name));
            $category = 'Casino Live'; // Fixed category for all Evolution games
            $imgUrl = $g['img'] ?? $g['game_img'] ?? null;

            if ($code === '' || $name === '') {
                echo "- Skipping invalid entry (missing code or name)\n";
                continue;
            }

            // Determine local image filename
            $filename = 'evo_' . $code . '.png';
            $relPath = $baseRel . '/' . $filename;
            $absPath = $baseAbs . '/' . $filename;

            // Upsert record
            $checkStmt->execute([$code]);
            $row = $checkStmt->fetch();
            if ($row) {
                $updateStmt->execute([$name, $category, $relPath, $row['id']]);
                $updated++;
            } else {
                $insertStmt->execute([$code, $name, $category, $relPath]);
                $new++;
            }

            // Handle image: download if valid URL, else generate default
            $hasValidImage = false;
            if ($imgUrl && filter_var($imgUrl, FILTER_VALIDATE_URL) && 
                !preg_match('/^https:\/\/softapi2\.shop\/?$/i', $imgUrl)) {
                try {
                    $imgData = curl_get($imgUrl, 25);
                    file_put_contents($absPath, $imgData);
                    $imgOk++;
                    $hasValidImage = true;
                    echo "✓ {$name} (code {$code}) image downloaded\n";
                } catch (Exception $e) {
                    // Download failed, will generate default
                    $hasValidImage = false;
                }
            }
            
            if (!$hasValidImage) {
                // Generate default placeholder image
                try {
                    generate_default_image($absPath, $name);
                    $imgGenerated++;
                    echo "🖼 {$name} (code {$code}) default image generated\n";
                } catch (Exception $e) {
                    $imgFail++;
                    echo "⊗ {$name} (code {$code}) image generation failed: " . $e->getMessage() . "\n";
                }
            }

        } catch (Exception $e) {
            $errors++;
            echo "❌ Error processing game: " . $e->getMessage() . "\n";
        }
    }

    // Invalidate game-related caches if Redis is available
    @require_once __DIR__ . '/../redis_helper.php';
    if (class_exists('RedisCache')) {
        try {
            $cache = RedisCache::getInstance();
            if ($cache->isEnabled()) {
                $cache->deletePattern('games:*');
                $cache->deletePattern('admin:games:*');
                $cache->deletePattern('user:*:recent_plays');
                echo "\n🧹 Cache cleared (games and admin views).\n";
            }
        } catch (Exception $e) {
            // Non-fatal if cache isn't available
        }
    }

    echo "\n===========================\n";
    echo "📊 Import Summary\n";
    echo "===========================\n";
    echo "✓ New games: {$new}\n";
    echo "✓ Updated games: {$updated}\n";
    echo "✓ Images downloaded: {$imgOk}\n";
    echo "🖼 Default images generated: {$imgGenerated}\n";
    echo "⊗ Image failures: {$imgFail}\n";
    echo "✗ Errors: {$errors}\n";
    echo "\n✅ Evolution import complete.\n";

} catch (Exception $ex) {
    echo "❌ Import failed: " . $ex->getMessage() . "\n";
    http_response_code(500);
}

?>
