<?php
/**
 * Update JILI Games Database
 * This script will add/update all JILI games from the provided list
 */

// Direct database connection
$host = 'localhost';
$dbname = 'casino_db';
$username = 'casino_user';
$password = 'casino123';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// JILI Games data - extracted from the provided list
$games = [
    ['name' => '3 Charge Buffalo', 'game_uid' => '1185', 'category' => 'Slots'],
    ['name' => '3 Coin Treasures', 'game_uid' => '473', 'category' => 'Slots'],
    ['name' => '3 Coin Treasures 2', 'game_uid' => '555', 'category' => 'Slots'],
    ['name' => '3 Coin Wild Horse', 'game_uid' => '163', 'category' => 'Slots'],
    ['name' => '3 LUCKY LION', 'game_uid' => '552', 'category' => 'Slots'],
    ['name' => '3 Lucky Piggy', 'game_uid' => '1026', 'category' => 'Slots'],
    ['name' => '3 Pot Dragons', 'game_uid' => '671', 'category' => 'Slots'],
    ['name' => '3 Rich pigies', 'game_uid' => '1191', 'category' => 'Slots'],
    ['name' => '7up7down', 'game_uid' => '264', 'category' => 'Table'],
    ['name' => 'Agent Ace', 'game_uid' => '634', 'category' => 'Slots'],
    ['name' => 'AK47', 'game_uid' => '324', 'category' => 'Slots'],
    ['name' => 'Ali Baba', 'game_uid' => '931', 'category' => 'Slots'],
    ['name' => 'All-star Fishing', 'game_uid' => '728', 'category' => 'Fishing'],
    ['name' => 'Andar Bahar', 'game_uid' => '505', 'category' => 'Table'],
    ['name' => 'Arena Fighter', 'game_uid' => '519', 'category' => 'Arcade'],
    ['name' => 'Aztec Priestess', 'game_uid' => '480', 'category' => 'Slots'],
    ['name' => 'Baccarat', 'game_uid' => '855', 'category' => 'Table'],
    ['name' => 'Bangla Beauty', 'game_uid' => '483', 'category' => 'Slots'],
    ['name' => 'Bao boon chin', 'game_uid' => '641', 'category' => 'Slots'],
    ['name' => 'Big Small', 'game_uid' => '162', 'category' => 'Table'],
    ['name' => 'Bikini Lady', 'game_uid' => '1477', 'category' => 'Slots'],
    ['name' => 'Bingo Adventure', 'game_uid' => '148', 'category' => 'Arcade'],
    ['name' => 'Bingo Carnaval', 'game_uid' => '967', 'category' => 'Arcade'],
    ['name' => 'Blackjack', 'game_uid' => '267', 'category' => 'Table'],
    ['name' => 'Blackjack Lucky Ladies', 'game_uid' => '951', 'category' => 'Table'],
    ['name' => 'Bombing Fishing', 'game_uid' => '1041', 'category' => 'Fishing'],
    ['name' => 'Bone Fortune', 'game_uid' => '802', 'category' => 'Slots'],
    ['name' => 'Bonus Hunter', 'game_uid' => '257', 'category' => 'Slots'],
    ['name' => 'Book of Gold', 'game_uid' => '482', 'category' => 'Slots'],
    ['name' => 'Boom Legend', 'game_uid' => '1088', 'category' => 'Fishing'],
    ['name' => 'Boxing Extravaganza', 'game_uid' => '155', 'category' => 'Slots'],
    ['name' => 'Boxing King', 'game_uid' => '699', 'category' => 'Slots'],
    ['name' => 'Bubble Beauty', 'game_uid' => '782', 'category' => 'Slots'],
    ['name' => 'Calaca Bingo', 'game_uid' => '824', 'category' => 'Arcade'],
    ['name' => 'Callbreak', 'game_uid' => '663', 'category' => 'Table'],
    ['name' => 'Callbreak Quick', 'game_uid' => '801', 'category' => 'Table'],
    ['name' => 'Candy Baby', 'game_uid' => '202', 'category' => 'Slots'],
    ['name' => 'Candyland Bingo', 'game_uid' => '518', 'category' => 'Arcade'],
    ['name' => 'Caribbean Stud Poker', 'game_uid' => '21', 'category' => 'Table'],
    ['name' => 'Charge Buffalo', 'game_uid' => '700', 'category' => 'Slots'],
    ['name' => 'Charge Buffalo Ascent', 'game_uid' => '182', 'category' => 'Slots'],
    ['name' => 'Chin Shi Huang', 'game_uid' => '158', 'category' => 'Slots'],
    ['name' => 'Coin Infinity Surge Reel', 'game_uid' => '1780', 'category' => 'Slots'],
    ['name' => 'Coin Tree', 'game_uid' => '923', 'category' => 'Slots'],
    ['name' => 'Color Game', 'game_uid' => '193', 'category' => 'Arcade'],
    ['name' => 'Color Prediction', 'game_uid' => '334', 'category' => 'Arcade'],
    ['name' => 'Crash Bonus', 'game_uid' => '783', 'category' => 'Arcade'],
    ['name' => 'Crash Cricket', 'game_uid' => '195', 'category' => 'Arcade'],
    ['name' => 'Crash Goal', 'game_uid' => '786', 'category' => 'Arcade'],
    ['name' => 'Crash Touchdown', 'game_uid' => '4', 'category' => 'Arcade'],
    ['name' => 'Crazy FaFaFa', 'game_uid' => '773', 'category' => 'Slots'],
    ['name' => 'Crazy Hunter', 'game_uid' => '468', 'category' => 'Slots'],
    ['name' => 'Crazy Hunter 2', 'game_uid' => '467', 'category' => 'Slots'],
    ['name' => 'Crazy Pusher', 'game_uid' => '3', 'category' => 'Slots'],
    ['name' => 'Crazy777', 'game_uid' => '642', 'category' => 'Slots'],
    ['name' => 'Cricket King 18', 'game_uid' => '1014', 'category' => 'Arcade'],
    ['name' => 'Cricket Roulette', 'game_uid' => '842', 'category' => 'Table'],
    ['name' => 'Cricket Sah 75', 'game_uid' => '462', 'category' => 'Arcade'],
    ['name' => 'Cricket War', 'game_uid' => '764', 'category' => 'Arcade'],
    ['name' => 'Dabanggg', 'game_uid' => '383', 'category' => 'Slots'],
    ['name' => 'Devil Fire', 'game_uid' => '113', 'category' => 'Slots'],
    ['name' => 'Devil Fire 2', 'game_uid' => '17', 'category' => 'Slots'],
    ['name' => 'DiamondParty', 'game_uid' => '327', 'category' => 'Slots'],
    ['name' => 'Dinosaur Tycoon', 'game_uid' => '1082', 'category' => 'Fishing'],
    ['name' => 'Dinosaur Tycoon II', 'game_uid' => '869', 'category' => 'Fishing'],
    ['name' => 'Dragon & Tiger', 'game_uid' => '1061', 'category' => 'Table'],
    ['name' => 'Dragon Fortune', 'game_uid' => '76', 'category' => 'Fishing'],
    ['name' => 'Dragon Treasure', 'game_uid' => '905', 'category' => 'Slots'],
    ['name' => 'Egypts Glow', 'game_uid' => '1016', 'category' => 'Slots'],
    ['name' => 'Elf Bingo', 'game_uid' => '431', 'category' => 'Arcade'],
    ['name' => 'European Roulette', 'game_uid' => '970', 'category' => 'Table'],
    ['name' => 'Fa Fa Fa', 'game_uid' => '385', 'category' => 'Slots'],
    ['name' => 'Fengshen', 'game_uid' => '42', 'category' => 'Slots'],
    ['name' => 'Fish Prawn Crab', 'game_uid' => '748', 'category' => 'Table'],
    ['name' => 'Fortune Bingo', 'game_uid' => '216', 'category' => 'Arcade'],
    ['name' => 'Fortune Coins', 'game_uid' => '982', 'category' => 'Slots'],
    ['name' => 'Fortune Gems', 'game_uid' => '792', 'category' => 'Slots'],
    ['name' => 'Fortune Gems 2', 'game_uid' => '458', 'category' => 'Slots'],
    ['name' => 'Fortune Gems 3', 'game_uid' => '449', 'category' => 'Slots'],
    ['name' => 'Fortune Gems Scratch', 'game_uid' => '972', 'category' => 'Arcade'],
    ['name' => 'Fortune King Jackpot', 'game_uid' => '1098', 'category' => 'Slots'],
    ['name' => 'Fortune Monkey', 'game_uid' => '808', 'category' => 'Slots'],
    ['name' => 'Fortune Roulette', 'game_uid' => '607', 'category' => 'Table'],
    ['name' => 'Fortune Tree', 'game_uid' => '476', 'category' => 'Slots'],
    ['name' => 'FortunePig', 'game_uid' => '600', 'category' => 'Slots'],
    ['name' => 'Fruity Wheel', 'game_uid' => '670', 'category' => 'Arcade'],
    ['name' => 'Gem Party', 'game_uid' => '532', 'category' => 'Slots'],
    ['name' => 'Go For Champion', 'game_uid' => '306', 'category' => 'Arcade'],
    ['name' => 'Go Goal BIngo', 'game_uid' => '357', 'category' => 'Arcade'],
    ['name' => 'Go Rush', 'game_uid' => '1081', 'category' => 'Arcade'],
    ['name' => 'God Of Martial', 'game_uid' => '142', 'category' => 'Slots'],
    ['name' => 'Gold Rush', 'game_uid' => '192', 'category' => 'Slots'],
    ['name' => 'Golden Bank', 'game_uid' => '896', 'category' => 'Slots'],
    ['name' => 'Golden Bank 2', 'game_uid' => '262', 'category' => 'Slots'],
    ['name' => 'Golden Empire', 'game_uid' => '329', 'category' => 'Slots'],
    ['name' => 'Golden Joker', 'game_uid' => '1100', 'category' => 'Slots'],
    ['name' => 'Golden Land', 'game_uid' => '26', 'category' => 'Slots'],
    ['name' => 'Golden Queen', 'game_uid' => '652', 'category' => 'Slots'],
    ['name' => 'Golden Temple', 'game_uid' => '694', 'category' => 'Slots'],
    ['name' => 'Happy Fishing', 'game_uid' => '523', 'category' => 'Fishing'],
    ['name' => 'Happy Taxi', 'game_uid' => '128', 'category' => 'Arcade'],
    ['name' => 'Hawaii Beauty', 'game_uid' => '452', 'category' => 'Slots'],
    ['name' => 'HILO', 'game_uid' => '875', 'category' => 'Arcade'],
    ['name' => 'Hot Chilli', 'game_uid' => '911', 'category' => 'Slots'],
    ['name' => 'Hyper Burst', 'game_uid' => '765', 'category' => 'Slots'],
    ['name' => 'iRich Bingo', 'game_uid' => '770', 'category' => 'Arcade'],
    ['name' => 'Jackpot Bingo', 'game_uid' => '543', 'category' => 'Arcade'],
    ['name' => 'Jackpot Fishing', 'game_uid' => '270', 'category' => 'Fishing'],
    ['name' => 'Jackpot Joker', 'game_uid' => '573', 'category' => 'Slots'],
    ['name' => 'Jhandi Munda', 'game_uid' => '259', 'category' => 'Table'],
    ['name' => 'JILI CAISHEN', 'game_uid' => '75', 'category' => 'Slots'],
    ['name' => 'Jogo do Bicho', 'game_uid' => '185', 'category' => 'Arcade'],
    ['name' => 'Journey West M', 'game_uid' => '53', 'category' => 'Slots'],
    ['name' => 'Jungle King', 'game_uid' => '354', 'category' => 'Slots'],
    ['name' => 'Keno', 'game_uid' => '771', 'category' => 'Arcade'],
    ['name' => 'Keno Bonus Number', 'game_uid' => '549', 'category' => 'Arcade'],
    ['name' => 'Keno Extra Bet', 'game_uid' => '104', 'category' => 'Arcade'],
    ['name' => 'Keno Super Chance', 'game_uid' => '660', 'category' => 'Arcade'],
    ['name' => 'King Arthur', 'game_uid' => '1133', 'category' => 'Slots'],
    ['name' => 'Legacy of Egypt', 'game_uid' => '82', 'category' => 'Slots'],
    ['name' => 'Limbo', 'game_uid' => '1070', 'category' => 'Arcade'],
    ['name' => 'Lucky Ball', 'game_uid' => '628', 'category' => 'Slots'],
    ['name' => 'Lucky Bingo', 'game_uid' => '919', 'category' => 'Arcade'],
    ['name' => 'Lucky Coming', 'game_uid' => '860', 'category' => 'Slots'],
    ['name' => 'Lucky Doggy', 'game_uid' => '348', 'category' => 'Slots'],
    ['name' => 'Lucky Goldbricks', 'game_uid' => '987', 'category' => 'Slots'],
    ['name' => 'Lucky Jaguar', 'game_uid' => '525', 'category' => 'Slots'],
    ['name' => 'Ludo Quick', 'game_uid' => '865', 'category' => 'Arcade'],
    ['name' => 'Magic Lamp', 'game_uid' => '400', 'category' => 'Slots'],
    ['name' => 'Magic Lamp Bingo', 'game_uid' => '601', 'category' => 'Arcade'],
    ['name' => 'Master Tiger', 'game_uid' => '962', 'category' => 'Slots'],
    ['name' => 'Mayan Empire', 'game_uid' => '423', 'category' => 'Slots'],
    ['name' => 'Medusa', 'game_uid' => '197', 'category' => 'Slots'],
    ['name' => 'Mega Ace', 'game_uid' => '1074', 'category' => 'Slots'],
    ['name' => 'Mega Fishing', 'game_uid' => '925', 'category' => 'Fishing'],
    ['name' => 'Mines', 'game_uid' => '524', 'category' => 'Arcade'],
    ['name' => 'Mines Gold', 'game_uid' => '347', 'category' => 'Arcade'],
    ['name' => 'MINI FLUSH', 'game_uid' => '33', 'category' => 'Table'],
    ['name' => 'Money Coming', 'game_uid' => '1005', 'category' => 'Slots'],
    ['name' => 'Money Coming Expand Bets', 'game_uid' => '261', 'category' => 'Slots'],
    ['name' => 'Money Pot', 'game_uid' => '774', 'category' => 'Slots'],
    ['name' => 'Monkey Party', 'game_uid' => '1145', 'category' => 'Slots'],
    ['name' => 'Neko Fortune', 'game_uid' => '708', 'category' => 'Slots'],
    ['name' => 'Night City', 'game_uid' => '545', 'category' => 'Slots'],
    ['name' => 'Nightfall Hunting', 'game_uid' => '943', 'category' => 'Slots'],
    ['name' => 'Number King', 'game_uid' => '245', 'category' => 'Arcade'],
    ['name' => 'Ocean King Jackpot', 'game_uid' => '393', 'category' => 'Fishing'],
    ['name' => 'PAPPU', 'game_uid' => '1049', 'category' => 'Arcade'],
    ['name' => 'Party Night', 'game_uid' => '971', 'category' => 'Slots'],
    ['name' => 'Party Star', 'game_uid' => '882', 'category' => 'Slots'],
    ['name' => 'Pearls of Bingo', 'game_uid' => '44', 'category' => 'Arcade'],
    ['name' => 'Penalty Kick', 'game_uid' => '299', 'category' => 'Arcade'],
    ['name' => 'Pharaoh Treasure', 'game_uid' => '910', 'category' => 'Slots'],
    ['name' => 'Pirate Queen', 'game_uid' => '515', 'category' => 'Slots'],
    ['name' => 'Pirate Queen 2', 'game_uid' => '315', 'category' => 'Slots'],
    ['name' => 'Plinko', 'game_uid' => '1044', 'category' => 'Arcade'],
    ['name' => 'Poker King', 'game_uid' => '793', 'category' => 'Table'],
    ['name' => 'Pool Rummy', 'game_uid' => '296', 'category' => 'Table'],
    ['name' => 'Poseidon', 'game_uid' => '369', 'category' => 'Slots'],
    ['name' => 'Potion Wizard', 'game_uid' => '1139', 'category' => 'Slots'],
    ['name' => 'Pusoy Go', 'game_uid' => '1097', 'category' => 'Table'],
    ['name' => 'Roma X Deluxe', 'game_uid' => '831', 'category' => 'Slots'],
    ['name' => 'RomaX', 'game_uid' => '1053', 'category' => 'Slots'],
    ['name' => 'Royal Fishing', 'game_uid' => '1059', 'category' => 'Fishing'],
    ['name' => 'Rummy', 'game_uid' => '810', 'category' => 'Table'],
    ['name' => 'Safari Mystery', 'game_uid' => '394', 'category' => 'Slots'],
    ['name' => 'Samba', 'game_uid' => '489', 'category' => 'Slots'],
    ['name' => 'Secret Treasure', 'game_uid' => '119', 'category' => 'Slots'],
    ['name' => 'SevenSevenSeven', 'game_uid' => '443', 'category' => 'Slots'],
    ['name' => 'Shanghai Beauty', 'game_uid' => '547', 'category' => 'Slots'],
    ['name' => 'Shōgun', 'game_uid' => '466', 'category' => 'Slots'],
    ['name' => 'Sic Bo', 'game_uid' => '1017', 'category' => 'Table'],
    ['name' => 'Sin City', 'game_uid' => '596', 'category' => 'Slots'],
    ['name' => 'Speed Baccarat', 'game_uid' => '727', 'category' => 'Table'],
    ['name' => 'Super Ace', 'game_uid' => '879', 'category' => 'Slots'],
    ['name' => 'Super Ace Deluxe', 'game_uid' => '581', 'category' => 'Slots'],
    ['name' => 'Super Ace Joker', 'game_uid' => '189', 'category' => 'Slots'],
    ['name' => 'Super Ace Scratch', 'game_uid' => '59', 'category' => 'Arcade'],
    ['name' => 'Super Bingo', 'game_uid' => '915', 'category' => 'Arcade'],
    ['name' => 'Super E-Sabong', 'game_uid' => '513', 'category' => 'Arcade'],
    ['name' => 'Super Rich', 'game_uid' => '850', 'category' => 'Slots'],
    ['name' => 'Sweet Land', 'game_uid' => '667', 'category' => 'Slots'],
    ['name' => 'Sweet Magic', 'game_uid' => '3978', 'category' => 'Slots'],
    ['name' => 'TeenPatti', 'game_uid' => '1119', 'category' => 'Table'],
    ['name' => 'TeenPatti 20-20', 'game_uid' => '110', 'category' => 'Table'],
    ['name' => 'TeenPatti Joker', 'game_uid' => '107', 'category' => 'Table'],
    ['name' => 'Thai Hilo', 'game_uid' => '1008', 'category' => 'Arcade'],
    ['name' => 'The Pig House', 'game_uid' => '588', 'category' => 'Slots'],
    ['name' => 'Thor X', 'game_uid' => '570', 'category' => 'Slots'],
    ['name' => 'Tongits Go', 'game_uid' => '170', 'category' => 'Table'],
    ['name' => 'Tower', 'game_uid' => '656', 'category' => 'Arcade'],
    ['name' => 'Treasure Quest', 'game_uid' => '484', 'category' => 'Slots'],
    ['name' => 'Trial of Phoenix', 'game_uid' => '954', 'category' => 'Slots'],
    ['name' => 'TWIN WINS', 'game_uid' => '907', 'category' => 'Slots'],
    ['name' => 'Ultimate Texas Hold\'em', 'game_uid' => '595', 'category' => 'Table'],
    ['name' => 'Video Poker', 'game_uid' => '183', 'category' => 'Table'],
    ['name' => 'War Of Dragons', 'game_uid' => '340', 'category' => 'Slots'],
    ['name' => 'West Hunter Bingo', 'game_uid' => '645', 'category' => 'Arcade'],
    ['name' => 'Wheel', 'game_uid' => '497', 'category' => 'Arcade'],
    ['name' => 'Wild Ace', 'game_uid' => '709', 'category' => 'Slots'],
    ['name' => 'Wild Racer', 'game_uid' => '211', 'category' => 'Slots'],
    ['name' => 'Win Drop', 'game_uid' => '586', 'category' => 'Arcade'],
    ['name' => 'Witches Night', 'game_uid' => '593', 'category' => 'Slots'],
    ['name' => 'World Cup', 'game_uid' => '177', 'category' => 'Slots'],
    ['name' => 'XiYangYang', 'game_uid' => '412', 'category' => 'Slots'],
    ['name' => 'Zeus', 'game_uid' => '358', 'category' => 'Slots'],
];

echo "🎮 JILI Games Database Update\n";
echo "============================\n\n";

$added = 0;
$updated = 0;
$errors = 0;

foreach ($games as $game) {
    try {
        // Check if game already exists
        $stmt = $pdo->prepare("SELECT id FROM games WHERE game_uid = ?");
        $stmt->execute([$game['game_uid']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing game
            $stmt = $pdo->prepare("
                UPDATE games 
                SET name = ?, category = ?, provider = 'JILI', is_active = 1 
                WHERE game_uid = ?
            ");
            $stmt->execute([$game['name'], $game['category'], $game['game_uid']]);
            echo "✓ Updated: {$game['name']} (ID: {$game['game_uid']})\n";
            $updated++;
        } else {
            // Insert new game
            $stmt = $pdo->prepare("
                INSERT INTO games (game_uid, name, provider, category, is_active, sort_order) 
                VALUES (?, ?, 'JILI', ?, 1, 0)
            ");
            $stmt->execute([$game['game_uid'], $game['name'], $game['category']]);
            echo "✓ Added: {$game['name']} (ID: {$game['game_uid']})\n";
            $added++;
        }
    } catch (PDOException $e) {
        echo "✗ Error with {$game['name']}: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n============================\n";
echo "📊 Summary:\n";
echo "   Added: $added games\n";
echo "   Updated: $updated games\n";
echo "   Errors: $errors\n";
echo "   Total: " . count($games) . " games\n";
echo "\n✅ Database update complete!\n";
?>
