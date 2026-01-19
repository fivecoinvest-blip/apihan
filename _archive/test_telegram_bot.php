<?php
/**
 * Test Telegram Bot Notification
 */
require_once 'telegram_bot.php';

$bot = new TelegramBot();

echo "🧪 Testing Telegram Bot...\n\n";

// Get admin chat IDs
$reflection = new ReflectionClass($bot);
$method = $reflection->getMethod('getAdminChatIds');
$method->setAccessible(true);
$chatIds = $method->invoke($bot);

if (empty($chatIds)) {
    echo "⚠️  No admin registered yet!\n";
    echo "Please send /start to @paldowalletbot first\n";
    exit;
}

echo "✅ Found " . count($chatIds) . " registered admin(s)\n";
echo "Chat IDs: " . implode(', ', $chatIds) . "\n\n";

// Send test message
foreach ($chatIds as $chatId) {
    echo "📤 Sending test notification to {$chatId}...\n";
    
    $result = $bot->sendMessage(
        $chatId,
        "🧪 <b>Test Notification</b>\n\n" .
        "Your Telegram bot is working correctly!\n\n" .
        "You will receive withdrawal notifications here.\n\n" .
        "<i>Bot: @paldowalletbot</i>"
    );
    
    if ($result['ok']) {
        echo "✅ Test message sent successfully!\n";
    } else {
        echo "❌ Failed to send test message\n";
        print_r($result);
    }
}

echo "\n✅ Test complete!\n";
?>
