<?php
require 'config.php';
try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare("INSERT INTO games (game_uid, name, provider, category, is_active) VALUES (:uid,:name,:provider,:category,1)
        ON DUPLICATE KEY UPDATE name=VALUES(name), provider=VALUES(provider), category=VALUES(category), is_active=VALUES(is_active)");
    $stmt->execute([
        ':uid' => '6396',
        ':name' => 'BlackJack Vip AA',
        ':provider' => 'Evolution',
        ':category' => 'Casino Live'
    ]);
    echo "OK\n";
} catch (Exception $e) {
    echo "ERROR: ".$e->getMessage()."\n";
    exit(1);
}
