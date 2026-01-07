# 🤖 Telegram Bot for Paldo Wallet Management

## Bot Information
- **Bot Name:** @paldowalletbot
- **Bot Token:** `8592166165:AAFbYYc0LyONdJSNARLPTfurhUT6jU6IKi4`
- **Bot URL:** https://t.me/paldowalletbot

## Features

### ✅ Automated Notifications
- Receive instant Telegram notifications when users request withdrawals
- Shows complete withdrawal details:
  - User information (username, phone)
  - Amount requested
  - Current & new balance
  - Description
  - Timestamp

### ✅ Direct Approval/Rejection
- **Approve** withdrawals directly from Telegram
- **Reject** with custom reason
- Upload proof of payment receipts
- Inline buttons for quick actions

### ✅ Receipt Management
- Bot prompts for receipt upload when approving withdrawals
- Can approve without receipt (with warning)
- Receipts automatically saved to server
- View receipts in admin panel

## Setup Instructions

### 1. Upload Files to Server
```bash
scp telegram_bot.php telegram_webhook.php setup_telegram_bot.php wallet.php root@31.97.107.21:/var/www/html/
```

### 2. Configure Webhook
```bash
ssh root@31.97.107.21
cd /var/www/html
php setup_telegram_bot.php
```

### 3. Register as Admin
1. Open Telegram
2. Search for `@paldowalletbot`
3. Send `/start` command
4. You're now registered to receive notifications!

## Bot Commands

| Command | Description |
|---------|-------------|
| `/start` | Register as admin and start receiving notifications |
| `/pending` | View all pending withdrawals |
| `/help` | Show help message with instructions |

## Usage Workflow

### Approving Withdrawal WITH Receipt

1. **Receive notification** with withdrawal details
2. Click **✅ Approve** button
3. Bot asks for receipt upload
4. **Send receipt image** as photo in Telegram
5. Bot confirms approval with receipt attached
6. ✅ Done!

### Approving Withdrawal WITHOUT Receipt

1. **Receive notification** with withdrawal details
2. Click **✅ Approve** button
3. Bot asks for receipt upload
4. Click **✅ Approve Without Receipt**
5. ⚠️ Transaction approved without proof

### Rejecting Withdrawal

1. **Receive notification** with withdrawal details
2. Click **❌ Reject** button
3. Bot asks for rejection reason
4. **Type and send** the reason
5. ❌ Transaction rejected with reason

## Technical Details

### Webhook URL
```
https://paldo88.site/telegram_webhook.php
```

### Files Structure
- `telegram_bot.php` - Main bot class with all functionality
- `telegram_webhook.php` - Webhook handler for incoming updates
- `setup_telegram_bot.php` - One-time setup script
- `wallet.php` - Modified to send notifications on withdrawal

### Database Requirements
- `site_settings` table needs to store `telegram_admin_chat_ids`
- `transactions` table already has `receipt_image` column

### Security Features
- Only registered admins receive notifications
- Chat IDs stored in database
- Session-based state management
- Secure webhook validation

## Notification Format

```
🔔 New Withdrawal Request

📤 Type: Withdrawal
👤 User: john_doe
📱 Phone: +639123456789
💰 Amount: PHP 5,000.00
💳 Current Balance: PHP 10,000.00
📊 New Balance: PHP 5,000.00
📝 Description: GCash withdrawal
🕐 Time: Jan 7, 2026 14:30

Transaction ID: #123

[✅ Approve] [❌ Reject]
[📊 View Details]
```

## Troubleshooting

### Bot not responding?
1. Check webhook is set: Run `setup_telegram_bot.php`
2. Verify bot token is correct
3. Check webhook logs in server

### Not receiving notifications?
1. Send `/start` to bot to register
2. Check `site_settings` table has your chat ID
3. Verify `telegram_admin_chat_ids` setting

### Receipt upload not working?
1. Ensure `uploads/receipts/` directory exists
2. Check directory permissions (777)
3. Verify curl is enabled on server

## Admin Panel Integration

- Receipts uploaded via Telegram appear in admin panel
- "View Receipt" button in Recent Approved section
- Same receipt system as web-based upload
- Full audit trail maintained

## Support

For issues or questions:
- Check admin panel: https://paldo88.site/admin.php
- Review server logs: `/var/www/html/error.log`
- Test bot: Send `/help` command

---

**Last Updated:** January 7, 2026
**Version:** 1.0.0
