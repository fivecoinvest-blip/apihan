<?php
/**
 * PG Games Importer
 * - Fetches PG games JSON from brand_id=45
 * - Upserts into `games` table with provider = 'PG'
 * - Downloads images to images/games/pg/pg_{game_code}.png and links them
 */

// Load config/constants if available
require_once __DIR__ . '/../config.php';

$dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
$dbName = defined('DB_NAME') ? DB_NAME : 'casino_db';
$dbUser = defined('DB_USER') ? DB_USER : 'casino_user';
$dbPass = defined('DB_PASS') ? DB_PASS : 'casino123';

$apiUrl = 'https://igamingapis.com/provider/brands.php?brand_id=45';

function curl_get($url, $timeout = 15) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'PG-Importer/1.0'
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

function safe_filename($name) {
    $name = preg_replace('/\s+/', ' ', trim($name));
    $name = strtolower($name);
    $name = preg_replace('/[^a-z0-9\-_. ]/', '', $name);
    $name = str_replace(' ', '-', $name);
    return $name ?: 'pg-game';
}

function ensure_dir($path) {
    if (!is_dir($path)) {
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new Exception('Failed to create directory: ' . $path);
        }
    }
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

    echo "🎮 PG Games Importer\n";
    echo "====================\n\n";

    echo "📥 Fetching PG games JSON...\n";
    $jsonRaw = curl_get($apiUrl, 30);
    $data = json_decode($jsonRaw, true);
    if (!is_array($data) || !isset($data['status']) || !$data['status']) {
        throw new Exception('Unexpected API response');
    }
    $games = $data['games'] ?? [];
    echo "✓ Found " . count($games) . " games in feed\n\n";

    // Prepare filesystem
    $baseRel = 'images/games/pg';
    $baseAbs = __DIR__ . '/../images/games/pg';
    ensure_dir($baseAbs);

    $new = 0; $updated = 0; $imgOk = 0; $imgFail = 0; $errors = 0;

    // Prepare statements
    $checkStmt = $pdo->prepare("SELECT id FROM games WHERE game_uid = ? AND provider = 'PG'");
    $insertStmt = $pdo->prepare("INSERT INTO games (game_uid, name, provider, category, image) VALUES (?, ?, 'PG', ?, ?)");
    $updateStmt = $pdo->prepare("UPDATE games SET name = ?, category = ?, image = ? WHERE id = ?");

    foreach ($games as $g) {
        try {
            $code = (string)($g['game_code'] ?? $g['gameID'] ?? '');
            $name = $g['game_name'] ?? $g['gameNameEn'] ?? '';
            $name = preg_replace('/\s+/', ' ', trim($name));
            $category = $g['category'] ?? 'Slots';
            $imgUrl = $g['img'] ?? $g['game_img'] ?? null;

            if ($code === '' || $name === '') {
                echo "- Skipping invalid entry (missing code or name)\n";
                continue;
            }

            // Determine local image filename
            $filename = 'pg_' . $code . '.png';
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

            // Download image if URL present
            if ($imgUrl) {
                try {
                    $imgData = curl_get($imgUrl, 25);
                    file_put_contents($absPath, $imgData);
                    $imgOk++;
                    echo "✓ {$name} (code {$code}) image saved\n";
                } catch (Exception $e) {
                    $imgFail++;
                    echo "⊗ {$name} (code {$code}) image failed: " . $e->getMessage() . "\n";
                }
            } else {
                echo "⊗ {$name} (code {$code}) no image URL\n";
                $imgFail++;
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
            // Non-fatal if cache isn’t available
        }
    }

    echo "\n====================\n";
    echo "📊 Import Summary\n";
    echo "====================\n";
    echo "✓ New games: {$new}\n";
    echo "✓ Updated games: {$updated}\n";
    echo "✓ Images saved: {$imgOk}\n";
    echo "⊗ Image failures: {$imgFail}\n";
    echo "✗ Errors: {$errors}\n";
    echo "\n✅ PG import complete.\n";

} catch (Exception $ex) {
    echo "❌ Import failed: " . $ex->getMessage() . "\n";
    http_response_code(500);
}

?>
