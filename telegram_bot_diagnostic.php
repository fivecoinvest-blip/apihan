<?php
/**
 * Telegram Bot Diagnostic & Setup
 */
require_once 'config.php';
require_once 'telegram_bot.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

echo "🤖 Telegram Bot Diagnostic Tool\n";
echo "================================\n\n";

// 1. Check webhook status
echo "1️⃣  Checking Bot Webhook Status...\n";
$bot = new TelegramBot();

$ch = curl_init("https://api.telegram.org/bot8592166165:AAFbYYc0LyONdJSNARLPTfurhUT6jU6IKi4/getWebhookInfo");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

$webhookInfo = json_decode($result, true);
if ($webhookInfo['ok']) {
    $info = $webhookInfo['result'];
    echo "✅ Webhook Status: " . ($info['url'] ? "Active" : "Inactive") . "\n";
    echo "   URL: " . $info['url'] . "\n";
    echo "   Pending Updates: " . $info['pending_update_count'] . "\n";
} else {
    echo "❌ Failed to get webhook info\n";
}

echo "\n";

// 2. Create telegram_admins table
echo "2️⃣  Checking telegram_admins Table...\n";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS telegram_admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chat_id BIGINT NOT NULL UNIQUE,
            username VARCHAR(255),
            first_name VARCHAR(255),
            is_active BOOLEAN DEFAULT 1,
            registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ telegram_admins table ready\n";
} catch (Exception $e) {
    echo "❌ Error creating table: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. Check registered admins
echo "3️⃣  Registered Admins:\n";
$stmt = $pdo->query("SELECT chat_id, username, first_name, registered_at FROM telegram_admins WHERE is_active = 1");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($admins)) {
    echo "⚠️  No admins registered yet!\n";
    echo "\n📱 To register, open Telegram and:\n";
    echo "   1. Search for @paldowalletbot\n";
    echo "   2. Send /start\n";
    echo "   3. You'll be registered automatically\n";
} else {
    foreach ($admins as $admin) {
        echo "✅ Chat ID: {$admin['chat_id']}\n";
        echo "   Username: {$admin['username']}\n";
        echo "   Name: {$admin['first_name']}\n";
        echo "   Registered: {$admin['registered_at']}\n\n";
    }
}

echo "\n";

// 4. Check recent withdrawals
echo "4️⃣  Recent Pending Withdrawals:\n";
$stmt = $pdo->query("
    SELECT t.id, t.user_id, u.username, t.amount, t.status, t.created_at
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.type = 'withdrawal' AND t.status = 'pending'
    ORDER BY t.created_at DESC
    LIMIT 5
");
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($withdrawals)) {
    echo "✅ No pending withdrawals\n";
} else {
    echo "Found " . count($withdrawals) . " pending withdrawal(s):\n\n";
    foreach ($withdrawals as $w) {
        echo "🆔 #{$w['id']} - {$w['username']}\n";
        echo "   Amount: ₱{$w['amount']}\n";
        echo "   Created: {$w['created_at']}\n";
        echo "   Status: {$w['status']}\n\n";
    }
}

echo "\n";

// 5. Test notification (if admins exist)
if (!empty($admins) && !empty($withdrawals)) {
    echo "5️⃣  Testing Notification...\n";
    try {
        $testResult = $bot->notifyPendingWithdrawal($withdrawals[0]['id']);
        echo "✅ Test notification sent!\n";
    } catch (Exception $e) {
        echo "❌ Error sending test notification: " . $e->getMessage() . "\n";
    }
} else {
    echo "5️⃣  Testing Notification...\n";
    echo "⏭️  Skipped (need registered admins and pending withdrawals)\n";
}

echo "\n";
echo "================================\n";
echo "✅ Diagnostic complete!\n";
echo "\n📝 Next steps:\n";
if (empty($admins)) {
    echo "1. Open Telegram and search for @paldowalletbot\n";
    echo "2. Send /start to register as admin\n";
    echo "3. You'll receive notifications for new withdrawals\n";
} else {
    echo "1. Everything is configured!\n";
    echo "2. You'll receive Telegram notifications for withdrawals\n";
    echo "3. Use the bot commands: /pending, /help\n";
}
?>
