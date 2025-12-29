<?php
/**
 * Rename JILI game images to match game names
 * This script will scan the jili_image folder and rename files to match game names
 */

// Game list from update_jili_games.php
$games = [
    '1185' => '3 Charge Buffalo',
    '473' => '3 Coin Treasures',
    '555' => '3 Coin Treasures 2',
    '163' => '3 Coin Wild Horse',
    '552' => '3 LUCKY LION',
    '1026' => '3 Lucky Piggy',
    '671' => '3 Pot Dragons',
    '1191' => '3 Rich pigies',
    '264' => '7up7down',
    '634' => 'Agent Ace',
    '324' => 'AK47',
    '931' => 'Ali Baba',
    '728' => 'All-star Fishing',
    '505' => 'Andar Bahar',
    '519' => 'Arena Fighter',
    '480' => 'Aztec Priestess',
    '855' => 'Baccarat',
    '483' => 'Bangla Beauty',
    '641' => 'Bao boon chin',
    '162' => 'Big Small',
    '1477' => 'Bikini Lady',
    '148' => 'Bingo Adventure',
    '967' => 'Bingo Carnaval',
    '267' => 'Blackjack',
    '951' => 'Blackjack Lucky Ladies',
    '1041' => 'Bombing Fishing',
    '802' => 'Bone Fortune',
    '257' => 'Bonus Hunter',
    '482' => 'Book of Gold',
    '1088' => 'Boom Legend',
    '155' => 'Boxing Extravaganza',
    '699' => 'Boxing King',
    '782' => 'Bubble Beauty',
    '824' => 'Calaca Bingo',
    '663' => 'Callbreak',
    '801' => 'Callbreak Quick',
    '202' => 'Candy Baby',
    '518' => 'Candyland Bingo',
    '21' => 'Caribbean Stud Poker',
    '700' => 'Charge Buffalo',
    '182' => 'Charge Buffalo Ascent',
    '158' => 'Chin Shi Huang',
    '1780' => 'Coin Infinity Surge Reel',
    '923' => 'Coin Tree',
    '193' => 'Color Game',
    '334' => 'Color Prediction',
    '783' => 'Crash Bonus',
    '195' => 'Crash Cricket',
    '786' => 'Crash Goal',
    '4' => 'Crash Touchdown',
    '773' => 'Crazy FaFaFa',
    '468' => 'Crazy Hunter',
    '467' => 'Crazy Hunter 2',
    '3' => 'Crazy Pusher',
    '642' => 'Crazy777',
    '1014' => 'Cricket King 18',
    '842' => 'Cricket Roulette',
    '462' => 'Cricket Sah 75',
    '764' => 'Cricket War',
    '383' => 'Dabanggg',
    '113' => 'Devil Fire',
    '17' => 'Devil Fire 2',
    '327' => 'DiamondParty',
    '1082' => 'Dinosaur Tycoon',
    '869' => 'Dinosaur Tycoon II',
    '1061' => 'Dragon & Tiger',
    '76' => 'Dragon Fortune',
    '905' => 'Dragon Treasure',
    '1016' => 'Egypts Glow',
    '431' => 'Elf Bingo',
    '970' => 'European Roulette',
    '385' => 'Fa Fa Fa',
    '42' => 'Fengshen',
    '748' => 'Fish Prawn Crab',
    '216' => 'Fortune Bingo',
    '982' => 'Fortune Coins',
    '792' => 'Fortune Gems',
    '458' => 'Fortune Gems 2',
    '449' => 'Fortune Gems 3',
    '972' => 'Fortune Gems Scratch',
    '1098' => 'Fortune King Jackpot',
    '808' => 'Fortune Monkey',
    '607' => 'Fortune Roulette',
    '476' => 'Fortune Tree',
    '600' => 'FortunePig',
    '670' => 'Fruity Wheel',
    '532' => 'Gem Party',
    '306' => 'Go For Champion',
    '357' => 'Go Goal BIngo',
    '1081' => 'Go Rush',
    '142' => 'God Of Martial',
    '192' => 'Gold Rush',
    '896' => 'Golden Bank',
    '262' => 'Golden Bank 2',
    '329' => 'Golden Empire',
    '1100' => 'Golden Joker',
    '26' => 'Golden Land',
    '652' => 'Golden Queen',
    '694' => 'Golden Temple',
    '523' => 'Happy Fishing',
    '128' => 'Happy Taxi',
    '452' => 'Hawaii Beauty',
    '875' => 'HILO',
    '911' => 'Hot Chilli',
    '765' => 'Hyper Burst',
    '770' => 'iRich Bingo',
    '543' => 'Jackpot Bingo',
    '270' => 'Jackpot Fishing',
    '573' => 'Jackpot Joker',
    '259' => 'Jhandi Munda',
    '75' => 'JILI CAISHEN',
    '185' => 'Jogo do Bicho',
    '53' => 'Journey West M',
    '354' => 'Jungle King',
    '771' => 'Keno',
    '549' => 'Keno Bonus Number',
    '104' => 'Keno Extra Bet',
    '660' => 'Keno Super Chance',
    '1133' => 'King Arthur',
    '82' => 'Legacy of Egypt',
    '1070' => 'Limbo',
    '628' => 'Lucky Ball',
    '919' => 'Lucky Bingo',
    '860' => 'Lucky Coming',
    '348' => 'Lucky Doggy',
    '987' => 'Lucky Goldbricks',
    '525' => 'Lucky Jaguar',
    '865' => 'Ludo Quick',
    '400' => 'Magic Lamp',
    '601' => 'Magic Lamp Bingo',
    '962' => 'Master Tiger',
    '423' => 'Mayan Empire',
    '197' => 'Medusa',
    '1074' => 'Mega Ace',
    '925' => 'Mega Fishing',
    '524' => 'Mines',
    '347' => 'Mines Gold',
    '33' => 'MINI FLUSH',
    '1005' => 'Money Coming',
    '261' => 'Money Coming Expand Bets',
    '774' => 'Money Pot',
    '1145' => 'Monkey Party',
    '708' => 'Neko Fortune',
    '545' => 'Night City',
    '943' => 'Nightfall Hunting',
    '245' => 'Number King',
    '393' => 'Ocean King Jackpot',
    '1049' => 'PAPPU',
    '971' => 'Party Night',
    '882' => 'Party Star',
    '44' => 'Pearls of Bingo',
    '299' => 'Penalty Kick',
    '910' => 'Pharaoh Treasure',
    '515' => 'Pirate Queen',
    '315' => 'Pirate Queen 2',
    '1044' => 'Plinko',
    '793' => 'Poker King',
    '296' => 'Pool Rummy',
    '369' => 'Poseidon',
    '1139' => 'Potion Wizard',
    '1097' => 'Pusoy Go',
    '831' => 'Roma X Deluxe',
    '1053' => 'RomaX',
    '1059' => 'Royal Fishing',
    '810' => 'Rummy',
    '394' => 'Safari Mystery',
    '489' => 'Samba',
    '119' => 'Secret Treasure',
    '443' => 'SevenSevenSeven',
    '547' => 'Shanghai Beauty',
    '466' => 'Shōgun',
    '1017' => 'Sic Bo',
    '596' => 'Sin City',
    '727' => 'Speed Baccarat',
    '879' => 'Super Ace',
    '581' => 'Super Ace Deluxe',
    '189' => 'Super Ace Joker',
    '59' => 'Super Ace Scratch',
    '915' => 'Super Bingo',
    '513' => 'Super E-Sabong',
    '850' => 'Super Rich',
    '667' => 'Sweet Land',
    '3978' => 'Sweet Magic',
    '1119' => 'TeenPatti',
    '110' => 'TeenPatti 20-20',
    '107' => 'TeenPatti Joker',
    '1008' => 'Thai Hilo',
    '588' => 'The Pig House',
    '570' => 'Thor X',
    '170' => 'Tongits Go',
    '656' => 'Tower',
    '484' => 'Treasure Quest',
    '954' => 'Trial of Phoenix',
    '907' => 'TWIN WINS',
    '595' => 'Ultimate Texas Hold\'em',
    '183' => 'Video Poker',
    '340' => 'War Of Dragons',
    '645' => 'West Hunter Bingo',
    '497' => 'Wheel',
    '709' => 'Wild Ace',
    '211' => 'Wild Racer',
    '586' => 'Win Drop',
    '593' => 'Witches Night',
    '177' => 'World Cup',
    '412' => 'XiYangYang',
    '358' => 'Zeus',
];

$sourceDir = '/home/neng/Desktop/apihan/jili_image';
$outputDir = '/home/neng/Desktop/apihan/images/games';

// Create output directory if it doesn't exist
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0777, true);
}

echo "🎮 JILI Game Image Renamer\n";
echo "==========================\n\n";

$renamed = 0;
$notFound = 0;
$errors = 0;

// Scan source directory
$files = scandir($sourceDir);

foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    
    $filePath = $sourceDir . '/' . $file;
    if (!is_file($filePath)) continue;
    
    // Get file extension
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
        echo "⊗ Skipped (not an image): $file\n";
        continue;
    }
    
    // Try to match with game name
    $matched = false;
    $fileName = pathinfo($file, PATHINFO_FILENAME);
    
    // Remove "code_" prefix if exists
    $cleanName = preg_replace('/^code_\d+\s*/', '', $fileName);
    $cleanName = preg_replace('/^不对外\s*/', '', $cleanName);
    
    // Try exact match first
    foreach ($games as $gameUid => $gameName) {
        $normalizedGame = str_replace(['_', '\''], [' ', ''], $gameName);
        $normalizedClean = str_replace(['_', '\''], [' ', ''], $cleanName);
        
        if (strcasecmp($normalizedGame, $normalizedClean) === 0) {
            // Found match!
            $newFileName = $gameName . '.' . $extension;
            // Sanitize filename
            $newFileName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $newFileName);
            $newFilePath = $outputDir . '/' . $newFileName;
            
            if (copy($filePath, $newFilePath)) {
                echo "✓ Renamed: $file → $newFileName\n";
                $renamed++;
                $matched = true;
                break;
            } else {
                echo "✗ Error copying: $file\n";
                $errors++;
                $matched = true;
                break;
            }
        }
    }
    
    if (!$matched) {
        echo "⊗ No match found: $file\n";
        $notFound++;
    }
}

echo "\n==========================\n";
echo "📊 Summary:\n";
echo "   Renamed: $renamed images\n";
echo "   Not matched: $notFound images\n";
echo "   Errors: $errors\n";
echo "   Total processed: " . ($renamed + $notFound + $errors) . " files\n";
echo "\n✅ Image renaming complete!\n";
echo "Images saved to: $outputDir\n";
?>
