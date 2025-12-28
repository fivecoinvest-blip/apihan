<?php
/**
 * Setup Games Table and Insert JILI Games
 */
require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Create games table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS games (
            id INT AUTO_INCREMENT PRIMARY KEY,
            game_uid VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            provider VARCHAR(100) NOT NULL,
            category VARCHAR(100) NOT NULL,
            image VARCHAR(255) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_provider (provider),
            INDEX idx_category (category),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✅ Games table created successfully!\n\n";

    // Insert JILI games
    $games = [
        ['634', 'Agent Ace', 'JILI', 'Slots'],
        ['931', 'Ali Baba', 'JILI', 'Slots'],
        ['641', 'Bao boon chin', 'JILI', 'Slots'],
        ['802', 'Bone Fortune', 'JILI', 'Slots'],
        ['257', 'Bonus Hunter', 'JILI', 'Slots'],
        ['482', 'Book of Gold', 'JILI', 'Slots'],
        ['699', 'Boxing King', 'JILI', 'Slots'],
        ['782', 'Bubble Beauty', 'JILI', 'Slots'],
        ['202', 'Candy Baby', 'JILI', 'Slots'],
        ['700', 'Charge Buffalo', 'JILI', 'Slots'],
        ['158', 'Chin Shi Huang', 'JILI', 'Slots'],
        ['468', 'Crazy Hunter', 'JILI', 'Slots'],
        ['3', 'Crazy Pusher', 'JILI', 'Slots'],
        ['642', 'Crazy777', 'JILI', 'Slots'],
        ['905', 'Dragon Treasure', 'JILI', 'Slots'],
        ['385', 'Fa Fa Fa', 'JILI', 'Slots'],
        ['42', 'Fengshen', 'JILI', 'Slots'],
        ['792', 'Fortune Gems', 'JILI', 'Slots'],
        ['458', 'Fortune Gems 2', 'JILI', 'Slots'],
        ['476', 'Fortune Tree', 'JILI', 'Slots'],
        ['532', 'Gem Party', 'JILI', 'Slots'],
        ['142', 'God Of Martial', 'JILI', 'Slots'],
        ['192', 'Gold Rush', 'JILI', 'Slots'],
        ['896', 'Golden Bank', 'JILI', 'Slots'],
        ['329', 'Golden Empire', 'JILI', 'Slots'],
        ['1100', 'Golden Joker', 'JILI', 'Slots'],
        ['652', 'Golden Queen', 'JILI', 'Slots'],
        ['128', 'Happy Taxi', 'JILI', 'Slots'],
        ['452', 'Hawaii Beauty', 'JILI', 'Slots'],
        ['911', 'Hot Chilli', 'JILI', 'Slots'],
        ['765', 'Hyper Burst', 'JILI', 'Slots'],
        ['354', 'Jungle King', 'JILI', 'Slots'],
        ['628', 'Lucky Ball', 'JILI', 'Slots'],
        ['860', 'Lucky Coming', 'JILI', 'Slots'],
        ['987', 'Lucky Goldbricks', 'JILI', 'Slots'],
        ['525', 'Lucky Jaguar', 'JILI', 'Slots'],
        ['400', 'Magic Lamp', 'JILI', 'Slots'],
        ['962', 'Master Tiger', 'JILI', 'Slots'],
        ['197', 'Medusa', 'JILI', 'Slots'],
        ['1074', 'Mega Ace', 'JILI', 'Slots'],
        ['1005', 'Money Coming', 'JILI', 'Slots'],
        ['1145', 'Monkey Party', 'JILI', 'Slots'],
        ['708', 'Neko Fortune', 'JILI', 'Slots'],
        ['545', 'Night City', 'JILI', 'Slots'],
        ['971', 'Party Night', 'JILI', 'Slots'],
        ['910', 'Pharaoh Treasure', 'JILI', 'Slots'],
        ['515', 'Pirate Queen', 'JILI', 'Slots'],
        ['489', 'Samba', 'JILI', 'Slots'],
        ['119', 'Secret Treasure', 'JILI', 'Slots'],
        ['547', 'Shanghai Beauty', 'JILI', 'Slots'],
        ['879', 'Super Ace', 'JILI', 'Slots'],
        ['850', 'Super Rich', 'JILI', 'Slots'],
        ['667', 'Sweet Land', 'JILI', 'Slots'],
        ['570', 'Thor X', 'JILI', 'Slots'],
        ['340', 'War Of Dragons', 'JILI', 'Slots'],
        ['709', 'Wild Ace', 'JILI', 'Slots'],
        ['211', 'Wild Racer', 'JILI', 'Slots'],
        ['177', 'World Cup', 'JILI', 'Slots']
    ];

    $stmt = $pdo->prepare("
        INSERT INTO games (game_uid, name, provider, category) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            name = VALUES(name),
            provider = VALUES(provider),
            category = VALUES(category)
    ");

    $count = 0;
    foreach ($games as $game) {
        $stmt->execute($game);
        $count++;
    }

    echo "✅ Inserted/Updated $count JILI games!\n\n";
    echo "Games table structure:\n";
    echo "- game_uid: Unique game identifier (for launching)\n";
    echo "- name: Game name\n";
    echo "- provider: JILI\n";
    echo "- category: Slots\n";
    echo "- image: Custom image path (can be uploaded via admin)\n";
    echo "- is_active: Enable/disable game\n";
    echo "- sort_order: Custom ordering\n\n";
    echo "Access admin panel at: /apihan/admin.php\n";

} catch (PDOException $e) {
    echo "❌ Database setup failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
