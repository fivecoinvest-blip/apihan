<?php
/**
 * JILI Games Complete Update Script
 * 1. Updates/inserts all JILI games into database
 * 2. Links game images to their respective records
 */

// Database connection
$host = 'localhost';
$db = 'casino_db';
$user = 'casino_user';
$pass = 'casino123';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🎮 JILI Games Complete Updater\n";
    echo "============================\n\n";
    
    // ========================================
    // STEP 1: UPDATE/INSERT GAMES
    // ========================================
    echo "📥 Step 1: Updating game database...\n";
    echo "------------------------------------\n";
    
    $games = [
        ['game_uid' => '115', 'name' => 'Agent Ace', 'category' => 'Slots'],
        ['game_uid' => '39', 'name' => 'Ali Baba', 'category' => 'Slots'],
        ['game_uid' => '141', 'name' => 'Bao boon chin', 'category' => 'Slots'],
        ['game_uid' => '205', 'name' => 'Bone Fortune', 'category' => 'Slots'],
        ['game_uid' => '87', 'name' => 'Bonus Hunter', 'category' => 'Slots'],
        ['game_uid' => '86', 'name' => 'Book of Gold', 'category' => 'Slots'],
        ['game_uid' => '109', 'name' => 'Boxing King', 'category' => 'Slots'],
        ['game_uid' => '56', 'name' => 'Bubble Beauty', 'category' => 'Slots'],
        ['game_uid' => '18', 'name' => 'Candy Baby', 'category' => 'Slots'],
        ['game_uid' => '125', 'name' => 'Charge Buffalo', 'category' => 'Slots'],
        ['game_uid' => '93', 'name' => 'Crazy FaFaFa', 'category' => 'Slots'],
        ['game_uid' => '88', 'name' => 'Crazy Hunter', 'category' => 'Slots'],
        ['game_uid' => '136', 'name' => 'Crazy Hunter 2', 'category' => 'Slots'],
        ['game_uid' => '12', 'name' => 'Crazy Pusher', 'category' => 'Arcade'],
        ['game_uid' => '14', 'name' => 'Crazy777', 'category' => 'Slots'],
        ['game_uid' => '107', 'name' => 'Devil Fire', 'category' => 'Slots'],
        ['game_uid' => '127', 'name' => 'Devil Fire 2', 'category' => 'Slots'],
        ['game_uid' => '69', 'name' => 'DiamondParty', 'category' => 'Slots'],
        ['game_uid' => '33', 'name' => 'Dinosaur Tycoon', 'category' => 'Fishing'],
        ['game_uid' => '55', 'name' => 'Dinosaur Tycoon II', 'category' => 'Fishing'],
        ['game_uid' => '201', 'name' => 'Dragon & Tiger', 'category' => 'Table'],
        ['game_uid' => '96', 'name' => 'Dragon Fortune', 'category' => 'Slots'],
        ['game_uid' => '15', 'name' => 'Dragon Treasure', 'category' => 'Fishing'],
        ['game_uid' => '264', 'name' => 'Egypts Glow', 'category' => 'Slots'],
        ['game_uid' => '106', 'name' => 'Fa Fa Fa', 'category' => 'Slots'],
        ['game_uid' => '17', 'name' => 'Fengshen', 'category' => 'Slots'],
        ['game_uid' => '24', 'name' => 'Fortune Coins', 'category' => 'Slots'],
        ['game_uid' => '1', 'name' => 'Fortune Gems', 'category' => 'Slots'],
        ['game_uid' => '20', 'name' => 'Fortune Gems 2', 'category' => 'Slots'],
        ['game_uid' => '19', 'name' => 'Fortune Gems 3', 'category' => 'Slots'],
        ['game_uid' => '98', 'name' => 'Fortune Gems Scratch', 'category' => 'Slots'],
        ['game_uid' => '112', 'name' => 'Fortune King Jackpot', 'category' => 'Slots'],
        ['game_uid' => '186', 'name' => 'Fortune Monkey', 'category' => 'Slots'],
        ['game_uid' => '25', 'name' => 'Fortune Tree', 'category' => 'Slots'],
        ['game_uid' => '2', 'name' => 'FortunePig', 'category' => 'Slots'],
        ['game_uid' => '21', 'name' => 'Gem Party', 'category' => 'Slots'],
        ['game_uid' => '22', 'name' => 'God Of Martial', 'category' => 'Slots'],
        ['game_uid' => '6', 'name' => 'Gold Rush', 'category' => 'Slots'],
        ['game_uid' => '30', 'name' => 'Golden Bank', 'category' => 'Slots'],
        ['game_uid' => '29', 'name' => 'Golden Bank 2', 'category' => 'Slots'],
        ['game_uid' => '78', 'name' => 'Golden Empire', 'category' => 'Slots'],
        ['game_uid' => '35', 'name' => 'Golden Joker', 'category' => 'Slots'],
        ['game_uid' => '36', 'name' => 'Golden Land', 'category' => 'Slots'],
        ['game_uid' => '27', 'name' => 'Golden Queen', 'category' => 'Slots'],
        ['game_uid' => '26', 'name' => 'Golden Temple', 'category' => 'Slots'],
        ['game_uid' => '34', 'name' => 'Happy Fishing', 'category' => 'Fishing'],
        ['game_uid' => '28', 'name' => 'Happy Taxi', 'category' => 'Slots'],
        ['game_uid' => '54', 'name' => 'Hawaii Beauty', 'category' => 'Slots'],
        ['game_uid' => '31', 'name' => 'Hot Chilli', 'category' => 'Slots'],
        ['game_uid' => '32', 'name' => 'Hyper Burst', 'category' => 'Slots'],
        ['game_uid' => '48', 'name' => 'Jackpot Fishing', 'category' => 'Fishing'],
        ['game_uid' => '47', 'name' => 'Jackpot Joker', 'category' => 'Slots'],
        ['game_uid' => '38', 'name' => 'JILI CAISHEN', 'category' => 'Slots'],
        ['game_uid' => '37', 'name' => 'Jungle King', 'category' => 'Fishing'],
        ['game_uid' => '100', 'name' => 'King Arthur', 'category' => 'Slots'],
        ['game_uid' => '52', 'name' => 'Lucky Ball', 'category' => 'Arcade'],
        ['game_uid' => '42', 'name' => 'Lucky Coming', 'category' => 'Slots'],
        ['game_uid' => '53', 'name' => 'Lucky Goldbricks', 'category' => 'Arcade'],
        ['game_uid' => '143', 'name' => 'Lucky Jaguar', 'category' => 'Slots'],
        ['game_uid' => '43', 'name' => 'Magic Lamp', 'category' => 'Slots'],
        ['game_uid' => '46', 'name' => 'Master Tiger', 'category' => 'Slots'],
        ['game_uid' => '102', 'name' => 'Mayan Empire', 'category' => 'Slots'],
        ['game_uid' => '95', 'name' => 'Medusa', 'category' => 'Slots'],
        ['game_uid' => '40', 'name' => 'Mega Ace', 'category' => 'Slots'],
        ['game_uid' => '58', 'name' => 'Mega Fishing', 'category' => 'Fishing'],
        ['game_uid' => '13', 'name' => 'Mines', 'category' => 'Arcade'],
        ['game_uid' => '99', 'name' => 'Mines Gold', 'category' => 'Arcade'],
        ['game_uid' => '41', 'name' => 'Money Coming', 'category' => 'Slots'],
        ['game_uid' => '101', 'name' => 'Money Coming Expand Bets', 'category' => 'Slots'],
        ['game_uid' => '44', 'name' => 'Money Pot', 'category' => 'Slots'],
        ['game_uid' => '114', 'name' => 'Neko Fortune', 'category' => 'Slots'],
        ['game_uid' => '60', 'name' => 'Night City', 'category' => 'Arcade'],
        ['game_uid' => '65', 'name' => 'Nightfall Hunting', 'category' => 'Fishing'],
        ['game_uid' => '64', 'name' => 'Ocean King Jackpot', 'category' => 'Fishing'],
        ['game_uid' => '111', 'name' => 'Pharaoh Treasure', 'category' => 'Slots'],
        ['game_uid' => '50', 'name' => 'Pirate Queen', 'category' => 'Fishing'],
        ['game_uid' => '73', 'name' => 'Pirate Queen 2', 'category' => 'Fishing'],
        ['game_uid' => '51', 'name' => 'Plinko', 'category' => 'Arcade'],
        ['game_uid' => '102', 'name' => 'RomaX', 'category' => 'Slots'],
        ['game_uid' => '517', 'name' => 'Roma X Deluxe', 'category' => 'Slots'],
        ['game_uid' => '63', 'name' => 'Royal Fishing', 'category' => 'Fishing'],
        ['game_uid' => '61', 'name' => 'Safari Mystery', 'category' => 'Arcade'],
        ['game_uid' => '48', 'name' => 'Samba', 'category' => 'Slots'],
        ['game_uid' => '49', 'name' => 'Secret Treasure', 'category' => 'Fishing'],
        ['game_uid' => '7', 'name' => 'SevenSevenSeven', 'category' => 'Slots'],
        ['game_uid' => '62', 'name' => 'Shanghai Beauty', 'category' => 'Slots'],
        ['game_uid' => '57', 'name' => 'Shōgun', 'category' => 'Slots'],
        ['game_uid' => '45', 'name' => 'Super Ace', 'category' => 'Slots'],
        ['game_uid' => '147', 'name' => 'Super Ace Deluxe', 'category' => 'Slots'],
        ['game_uid' => '146', 'name' => 'Super Ace Joker', 'category' => 'Slots'],
        ['game_uid' => '145', 'name' => 'Super Ace Scratch', 'category' => 'Slots'],
        ['game_uid' => '97', 'name' => 'Super Rich', 'category' => 'Slots'],
        ['game_uid' => '53', 'name' => 'Sweet Land', 'category' => 'Slots'],
        ['game_uid' => '72', 'name' => 'TeenPatti', 'category' => 'Table'],
        ['game_uid' => '161', 'name' => 'TeenPatti 20-20', 'category' => 'Table'],
        ['game_uid' => '159', 'name' => 'TeenPatti Joker', 'category' => 'Table'],
        ['game_uid' => '8', 'name' => 'Thor X', 'category' => 'Slots'],
        ['game_uid' => '16', 'name' => 'Tower', 'category' => 'Arcade'],
        ['game_uid' => '404', 'name' => 'Ultimate Texas Hold\'em', 'category' => 'Table'],
        ['game_uid' => '9', 'name' => 'Wild Ace', 'category' => 'Slots'],
        ['game_uid' => '74', 'name' => 'Wild Racer', 'category' => 'Slots'],
        ['game_uid' => '66', 'name' => '3 Coin Treasures', 'category' => 'Slots'],
        ['game_uid' => '67', 'name' => '3 Coin Treasures 2', 'category' => 'Slots'],
        ['game_uid' => '68', 'name' => '3 Coin Wild Horse', 'category' => 'Slots'],
        ['game_uid' => '69', 'name' => '3 LUCKY LION', 'category' => 'Slots'],
        ['game_uid' => '70', 'name' => '3 Lucky Piggy', 'category' => 'Slots'],
        ['game_uid' => '71', 'name' => '3 Pot Dragons', 'category' => 'Slots'],
        ['game_uid' => '72', 'name' => '3 Rich pigies', 'category' => 'Slots'],
        ['game_uid' => '73', 'name' => '7up7down', 'category' => 'Table'],
        ['game_uid' => '74', 'name' => 'AK47', 'category' => 'Slots'],
        ['game_uid' => '75', 'name' => 'All-star Fishing', 'category' => 'Fishing'],
        ['game_uid' => '76', 'name' => 'Andar Bahar', 'category' => 'Table'],
        ['game_uid' => '77', 'name' => 'Arena Fighter', 'category' => 'Slots'],
        ['game_uid' => '78', 'name' => 'Aztec Priestess', 'category' => 'Slots'],
        ['game_uid' => '79', 'name' => 'Baccarat', 'category' => 'Table'],
        ['game_uid' => '80', 'name' => 'Bangla Beauty', 'category' => 'Slots'],
        ['game_uid' => '81', 'name' => 'Big Small', 'category' => 'Table'],
        ['game_uid' => '82', 'name' => 'Bikini Lady', 'category' => 'Slots'],
        ['game_uid' => '83', 'name' => 'Bingo Adventure', 'category' => 'Arcade'],
        ['game_uid' => '84', 'name' => 'Bingo Carnaval', 'category' => 'Arcade'],
        ['game_uid' => '85', 'name' => 'Blackjack', 'category' => 'Table'],
        ['game_uid' => '86', 'name' => 'Blackjack Lucky Ladies', 'category' => 'Table'],
        ['game_uid' => '87', 'name' => 'Bombing Fishing', 'category' => 'Fishing'],
        ['game_uid' => '88', 'name' => 'Boom Legend', 'category' => 'Slots'],
        ['game_uid' => '89', 'name' => 'Boxing Extravaganza', 'category' => 'Slots'],
        ['game_uid' => '90', 'name' => 'Calaca Bingo', 'category' => 'Arcade'],
        ['game_uid' => '91', 'name' => 'Callbreak', 'category' => 'Table'],
        ['game_uid' => '92', 'name' => 'Callbreak Quick', 'category' => 'Table'],
        ['game_uid' => '93', 'name' => 'Candyland Bingo', 'category' => 'Arcade'],
        ['game_uid' => '94', 'name' => 'Caribbean Stud Poker', 'category' => 'Table'],
        ['game_uid' => '225', 'name' => '3 Charge Buffalo', 'category' => 'Slots'],
        ['game_uid' => '95', 'name' => 'Charge Buffalo Ascent', 'category' => 'Slots'],
        ['game_uid' => '96', 'name' => 'Chin Shi Huang', 'category' => 'Slots'],
        ['game_uid' => '97', 'name' => 'Coin Tree', 'category' => 'Slots'],
        ['game_uid' => '203', 'name' => 'Coin Infinity Surge Reel', 'category' => 'Slots'],
        ['game_uid' => '98', 'name' => 'Color Game', 'category' => 'Arcade'],
        ['game_uid' => '99', 'name' => 'Color Prediction', 'category' => 'Arcade'],
        ['game_uid' => '100', 'name' => 'Crash Bonus', 'category' => 'Arcade'],
        ['game_uid' => '101', 'name' => 'Crash Cricket', 'category' => 'Arcade'],
        ['game_uid' => '102', 'name' => 'Crash Goal', 'category' => 'Arcade'],
        ['game_uid' => '103', 'name' => 'Crash Touchdown', 'category' => 'Arcade'],
        ['game_uid' => '104', 'name' => 'Cricket King 18', 'category' => 'Arcade'],
        ['game_uid' => '105', 'name' => 'Cricket Roulette', 'category' => 'Arcade'],
        ['game_uid' => '106', 'name' => 'Cricket Sah 75', 'category' => 'Arcade'],
        ['game_uid' => '107', 'name' => 'Cricket War', 'category' => 'Arcade'],
        ['game_uid' => '108', 'name' => 'Dabanggg', 'category' => 'Slots'],
        ['game_uid' => '109', 'name' => 'Elf Bingo', 'category' => 'Arcade'],
        ['game_uid' => '110', 'name' => 'European Roulette', 'category' => 'Table'],
        ['game_uid' => '111', 'name' => 'Fish Prawn Crab', 'category' => 'Table'],
        ['game_uid' => '112', 'name' => 'Fortune Bingo', 'category' => 'Arcade'],
        ['game_uid' => '113', 'name' => 'Fortune Roulette', 'category' => 'Table'],
        ['game_uid' => '114', 'name' => 'Fruity Wheel', 'category' => 'Arcade'],
        ['game_uid' => '115', 'name' => 'Go For Champion', 'category' => 'Slots'],
        ['game_uid' => '116', 'name' => 'Go Goal BIngo', 'category' => 'Arcade'],
        ['game_uid' => '117', 'name' => 'Go Rush', 'category' => 'Arcade'],
        ['game_uid' => '118', 'name' => 'HILO', 'category' => 'Arcade'],
        ['game_uid' => '119', 'name' => 'iRich Bingo', 'category' => 'Arcade'],
        ['game_uid' => '120', 'name' => 'Jackpot Bingo', 'category' => 'Arcade'],
        ['game_uid' => '121', 'name' => 'Jhandi Munda', 'category' => 'Table'],
        ['game_uid' => '122', 'name' => 'Jogo do Bicho', 'category' => 'Arcade'],
        ['game_uid' => '123', 'name' => 'Journey West M', 'category' => 'Slots'],
        ['game_uid' => '124', 'name' => 'Keno', 'category' => 'Arcade'],
        ['game_uid' => '125', 'name' => 'Keno Bonus Number', 'category' => 'Arcade'],
        ['game_uid' => '126', 'name' => 'Keno Extra Bet', 'category' => 'Arcade'],
        ['game_uid' => '127', 'name' => 'Keno Super Chance', 'category' => 'Arcade'],
        ['game_uid' => '128', 'name' => 'Legacy of Egypt', 'category' => 'Slots'],
        ['game_uid' => '129', 'name' => 'Limbo', 'category' => 'Arcade'],
        ['game_uid' => '130', 'name' => 'Lucky Bingo', 'category' => 'Arcade'],
        ['game_uid' => '131', 'name' => 'Lucky Doggy', 'category' => 'Slots'],
        ['game_uid' => '132', 'name' => 'Ludo Quick', 'category' => 'Arcade'],
        ['game_uid' => '133', 'name' => 'Magic Lamp Bingo', 'category' => 'Arcade'],
        ['game_uid' => '134', 'name' => 'MINI FLUSH', 'category' => 'Table'],
        ['game_uid' => '135', 'name' => 'Monkey Party', 'category' => 'Slots'],
        ['game_uid' => '136', 'name' => 'Number King', 'category' => 'Arcade'],
        ['game_uid' => '137', 'name' => 'PAPPU', 'category' => 'Arcade'],
        ['game_uid' => '138', 'name' => 'Party Night', 'category' => 'Slots'],
        ['game_uid' => '139', 'name' => 'Party Star', 'category' => 'Slots'],
        ['game_uid' => '140', 'name' => 'Pearls of Bingo', 'category' => 'Arcade'],
        ['game_uid' => '141', 'name' => 'Penalty Kick', 'category' => 'Arcade'],
        ['game_uid' => '142', 'name' => 'Poker King', 'category' => 'Table'],
        ['game_uid' => '143', 'name' => 'Pool Rummy', 'category' => 'Table'],
        ['game_uid' => '144', 'name' => 'Poseidon', 'category' => 'Slots'],
        ['game_uid' => '145', 'name' => 'Potion Wizard', 'category' => 'Slots'],
        ['game_uid' => '146', 'name' => 'Pusoy Go', 'category' => 'Table'],
        ['game_uid' => '147', 'name' => 'Rummy', 'category' => 'Table'],
        ['game_uid' => '148', 'name' => 'Sic Bo', 'category' => 'Table'],
        ['game_uid' => '149', 'name' => 'Sin City', 'category' => 'Slots'],
        ['game_uid' => '150', 'name' => 'Speed Baccarat', 'category' => 'Table'],
        ['game_uid' => '151', 'name' => 'Super Bingo', 'category' => 'Arcade'],
        ['game_uid' => '152', 'name' => 'Super E-Sabong', 'category' => 'Arcade'],
        ['game_uid' => '153', 'name' => 'Sweet Magic', 'category' => 'Slots'],
        ['game_uid' => '154', 'name' => 'Thai Hilo', 'category' => 'Arcade'],
        ['game_uid' => '155', 'name' => 'The Pig House', 'category' => 'Slots'],
        ['game_uid' => '156', 'name' => 'Tongits Go', 'category' => 'Table'],
        ['game_uid' => '157', 'name' => 'Treasure Quest', 'category' => 'Slots'],
        ['game_uid' => '158', 'name' => 'Trial of Phoenix', 'category' => 'Slots'],
        ['game_uid' => '159', 'name' => 'TWIN WINS', 'category' => 'Slots'],
        ['game_uid' => '160', 'name' => 'Video Poker', 'category' => 'Table'],
        ['game_uid' => '161', 'name' => 'War Of Dragons', 'category' => 'Slots'],
        ['game_uid' => '162', 'name' => 'West Hunter Bingo', 'category' => 'Arcade'],
        ['game_uid' => '163', 'name' => 'Wheel', 'category' => 'Arcade'],
        ['game_uid' => '164', 'name' => 'Wild Racer', 'category' => 'Slots'],
        ['game_uid' => '165', 'name' => 'Win Drop', 'category' => 'Arcade'],
        ['game_uid' => '166', 'name' => 'Witches Night', 'category' => 'Slots'],
        ['game_uid' => '167', 'name' => 'World Cup', 'category' => 'Arcade'],
        ['game_uid' => '168', 'name' => 'XiYangYang', 'category' => 'Slots'],
        ['game_uid' => '169', 'name' => 'Zeus', 'category' => 'Slots']
    ];
    
    $new_games = 0;
    $updated_games = 0;
    $errors = 0;
    
    foreach ($games as $game) {
        try {
            // Check if game exists
            $checkStmt = $pdo->prepare("SELECT id FROM games WHERE game_uid = ? AND provider = 'JILI'");
            $checkStmt->execute([$game['game_uid']]);
            
            if ($checkStmt->fetch()) {
                // Update existing game
                $updateStmt = $pdo->prepare("
                    UPDATE games 
                    SET name = ?, category = ?
                    WHERE game_uid = ? AND provider = 'JILI'
                ");
                $updateStmt->execute([$game['name'], $game['category'], $game['game_uid']]);
                $updated_games++;
            } else {
                // Insert new game
                $insertStmt = $pdo->prepare("
                    INSERT INTO games (game_uid, name, provider, category)
                    VALUES (?, ?, 'JILI', ?)
                ");
                $insertStmt->execute([$game['game_uid'], $game['name'], $game['category']]);
                $new_games++;
            }
        } catch (PDOException $e) {
            echo "❌ Error with game {$game['name']}: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
    
    echo "✓ New games added: {$new_games}\n";
    echo "✓ Games updated: {$updated_games}\n";
    echo "✗ Errors: {$errors}\n\n";
    
    // ========================================
    // STEP 2: LINK GAME IMAGES
    // ========================================
    echo "🖼️  Step 2: Linking game images...\n";
    echo "------------------------------------\n";
    
    // Get all games from database
    $stmt = $pdo->query("SELECT id, name, image FROM games WHERE provider = 'JILI' ORDER BY name");
    $all_games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $images_linked = 0;
    $images_not_found = 0;
    
    foreach ($all_games as $game) {
        $game_name = $game['name'];
        $image_filename = $game_name . '.png';
        $image_path = 'images/games/' . $image_filename;
        
        // Check if image file exists on server
        $full_path = '/var/www/html/' . $image_path;
        if (file_exists($full_path)) {
            // Update database with image path
            $updateStmt = $pdo->prepare("UPDATE games SET image = ? WHERE id = ?");
            $updateStmt->execute([$image_path, $game['id']]);
            
            echo "✓ Linked: {$game_name}\n";
            $images_linked++;
        } else {
            echo "⊗ Not found: {$game_name}\n";
            $images_not_found++;
        }
    }
    
    echo "\n============================\n";
    echo "📊 FINAL SUMMARY\n";
    echo "============================\n";
    echo "Games Database:\n";
    echo "  ✓ New games: {$new_games}\n";
    echo "  ✓ Updated games: {$updated_games}\n";
    echo "  ✗ Errors: {$errors}\n\n";
    echo "Images Linked:\n";
    echo "  ✓ Images linked: {$images_linked}\n";
    echo "  ⊗ Images not found: {$images_not_found}\n";
    echo "\n✅ Complete update finished!\n";
    
} catch (PDOException $e) {
    die("❌ Database error: " . $e->getMessage() . "\n");
}
?>
