<?php
/**
 * Setup Telegram Bot Webhook
 */
require_once 'telegram_bot.php';

$bot = new TelegramBot();
$webhookUrl = 'https://paldo88.site/telegram_webhook.php';

echo "🤖 Setting up Telegram Bot Webhook...\n\n";

// Set webhook
$result = $bot->setWebhook($webhookUrl);

if ($result['ok']) {
    echo "✅ Webhook set successfully!\n";
    echo "Webhook URL: {$webhookUrl}\n\n";
    echo "📱 Bot is ready to receive notifications!\n\n";
    echo "Next steps:\n";
    echo "1. Open Telegram and search for @paldowalletbot\n";
    echo "2. Send /start to register as admin\n";
    echo "3. You will receive notifications for new withdrawals\n";
} else {
    echo "❌ Failed to set webhook\n";
    echo "Error: " . ($result['description'] ?? 'Unknown error') . "\n";
}
?>
