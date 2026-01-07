<?php
/**
 * Telegram Bot Webhook Handler
 * No session needed - using database for state management
 */
require_once 'telegram_bot.php';

$bot = new TelegramBot();
$bot->handleWebhook();
