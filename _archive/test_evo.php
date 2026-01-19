<?php
require_once /var/www/html/config.php;
require_once /var/www/html/api_request_builder.php;
require_once /var/www/html/db_helper.php;

$db = getDB();
$game = $db->query("SELECT id, game_uid, name, provider FROM games WHERE game_uid = \x27 7615\x27 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    die("Game 7615 not found\n");
}

echo "Testing: " . $game["name"] . " (UID: " . $game["game_uid"] . ")\n\n";

$params = createGameLaunchRequest(userId: "999", balance: "1000", gameUid: $game["game_uid"]);
$result = sendLaunchGameRequest($params);

if ($result["success"]) {
    echo "SUCCESS\n";
} else {
    echo "FAILED - Error: " . $result["error"] . "\n";
}
?>
