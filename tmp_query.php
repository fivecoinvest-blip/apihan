<?php
require 'config.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $pdo->query("SELECT provider, category, COUNT(*) c FROM games GROUP BY provider, category ORDER BY c DESC LIMIT 10");
foreach ($stmt as $r) {
    echo $r['provider'], '|', $r['category'], '|', $r['c'], "\n";
}
