# SoftAPI Integration Documentation

Complete API integration for launching casino game sessions with real-time balance management and encrypted callbacks.

## 🎯 Overview

This integration provides a complete solution for launching casino games through SoftAPI with:
- **AES-256-ECB Encryption** for secure payload transmission
- **Real-time Balance Management** with automatic debit/credit
- **Encrypted Callback Handler** for game results
- **Transaction Logging** with full audit trail
- **Multi-user Support** with file-based storage

---

## 📋 Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Core Functions](#core-functions)
5. [Launch Game API](#launch-game-api)
6. [Callback Handler](#callback-handler)
7. [Balance Management](#balance-management)
8. [Code Examples](#code-examples)
9. [API Reference](#api-reference)
10. [Deployment](#deployment)
11. [Troubleshooting](#troubleshooting)

---

## 📦 Requirements

### Server Requirements
- **PHP**: 7.4 or higher
- **Web Server**: Apache 2.4+ or Nginx
- **Extensions**:
  - `php-openssl` (for AES-256 encryption)
  - `php-curl` (for API requests)
  - `php-json` (for JSON encoding/decoding)
  - `php-mbstring` (for string handling)

### SoftAPI Account
- Active SoftAPI account with API credentials
- Whitelisted domain/IP address
- Sufficient provider account balance (GGR)

---

## 🚀 Installation

### 1. Clone or Download Files

```bash
# Core files required:
config.php              # API credentials and settings
helpers.php             # Encryption and utility functions
api_request_builder.php # Game launch request builder
callback.php            # Callback handler for game results
balance_helper.php      # Balance management functions
```

### 2. Set Permissions

```bash
chmod 755 *.php
mkdir -p logs
chmod 777 logs
```

### 3. Configure Apache (if using .htaccess)

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
</IfModule>
```

---

## ⚙️ Configuration

### config.php

```php
<?php
// API Credentials (from SoftAPI dashboard)
define('API_TOKEN', 'your_api_token_here');
define('API_SECRET', 'your_32_character_secret_key');  // MUST BE 32 BYTES

// API Endpoints
define('SERVER_URL', 'https://igamingapis.live/api/v1');

// Your Website URLs
define('RETURN_URL', 'https://yourdomain.com/return.php');
define('CALLBACK_URL', 'https://yourdomain.com/callback.php');

// Timezone
date_default_timezone_set('Asia/Manila');
?>
```

### Important Notes:
- `API_SECRET` **must be exactly 32 characters** for AES-256-ECB encryption
- `CALLBACK_URL` must be whitelisted in your SoftAPI dashboard
- URLs must match **exactly** what's configured in SoftAPI settings

---

## 🔧 Core Functions

### 1. ENCRYPT_PAYLOAD_ECB()

Encrypts payload data using AES-256-ECB encryption.

**Location:** `helpers.php`

```php
function ENCRYPT_PAYLOAD_ECB(array $data, string $key): string
```

**Parameters:**
- `$data` (array): Data to encrypt
- `$key` (string): 32-character encryption key

**Returns:** Base64-encoded encrypted string

**Example:**
```php
$payload = [
    'user_id' => '12345',
    'balance' => '100',
    'game_uid' => '634'
];
$encrypted = ENCRYPT_PAYLOAD_ECB($payload, API_SECRET);
```

---

### 2. createGameLaunchRequest()

Creates a properly formatted game launch request.

**Location:** `api_request_builder.php`

```php
function createGameLaunchRequest(
    string $userId,
    $balance,
    ?string $gameUid = null,
    ?string $currencyCode = null,
    ?string $language = null
): array
```

**Parameters:**
- `$userId` (string, required): Player's unique ID
- `$balance` (float|string, required): Player's wallet balance
- `$gameUid` (string, optional): Game session ID (auto-generated if null)
- `$currencyCode` (string, optional): Currency code (e.g., 'USD', 'BDT')
- `$language` (string, optional): Language code (e.g., 'en', 'bn')

**Returns:** Array of request parameters

**Example:**
```php
$params = createGameLaunchRequest(
    userId: '12345',
    balance: 100,
    gameUid: '634',
    currencyCode: 'USD',
    language: 'en'
);
```

---

### 3. sendLaunchGameRequest()

Sends the game launch request to SoftAPI.

**Location:** `api_request_builder.php`

```php
function sendLaunchGameRequest(array $params): array
```

**Parameters:**
- `$params` (array): Request parameters from `createGameLaunchRequest()`

**Returns:**
```php
[
    'success' => true|false,
    'game_url' => 'https://...',  // Game URL (if success)
    'error' => 'Error message',   // Error message (if failed)
    'error_code' => 1              // Error code (if failed)
]
```

**Example:**
```php
$params = createGameLaunchRequest('12345', 100, '634');
$result = sendLaunchGameRequest($params);

if ($result['success']) {
    echo "Game URL: " . $result['game_url'];
} else {
    echo "Error: " . $result['error'];
}
```

---

## 🎮 Launch Game API

### Complete Launch Flow

```php
<?php
require_once 'config.php';
require_once 'helpers.php';
require_once 'api_request_builder.php';
require_once 'balance_helper.php';

// Step 1: Set player's initial balance
setUserBalance($userId, $balance);

// Step 2: Create launch request
$params = createGameLaunchRequest(
    userId: '12345',
    balance: 100,
    gameUid: '634',
    currencyCode: 'USD',
    language: 'en'
);

// Step 3: Send request to SoftAPI
$result = sendLaunchGameRequest($params);

// Step 4: Handle response
if ($result['success']) {
    // Redirect to game or display in iframe
    header('Location: ' . $result['game_url']);
} else {
    // Show error message
    echo "Failed to launch game: " . $result['error'];
}
?>
```

### Request Parameters Reference

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `user_id` | string | Yes | Player's unique identifier |
| `balance` | string | Yes | Player's wallet balance |
| `game_uid` | string | Yes | Unique game session ID |
| `token` | string | Yes | API token (from config) |
| `timestamp` | int | Yes | Current time in milliseconds |
| `return` | string | Yes | URL where player returns after game |
| `callback` | string | Yes | URL where game sends results |
| `currency_code` | string | No | Currency code (e.g., USD, EUR) |
| `language` | string | No | Language code (e.g., en, bn) |

---

## 📞 Callback Handler

### How It Works

1. Game sends bet/win results to your `callback.php`
2. Payload is **encrypted** with your API secret
3. Callback decrypts and processes the data
4. Updates user balance (deduct bet, add win)
5. Returns updated balance to game

### Callback Request Format

**POST to:** `https://yourdomain.com/callback.php`

**Encrypted Payload Example:**
```json
{
    "payload": "dnkcTo+Pnk7WM/ERlCCNg...",
    "timestamp": 1766895884408
}
```

**Decrypted Data:**
```json
{
    "game_uid": "634",
    "game_round": "12345",
    "member_account": "12345",
    "bet_amount": 1.0,
    "win_amount": 0.5,
    "timestamp": "2025-12-28 12:00:00"
}
```

### Callback Response Format

```json
{
    "credit_amount": 49.5,
    "timestamp": 1766895884500
}
```

- `credit_amount`: Player's **new balance** after transaction
- Must return actual balance from your system

### Custom Callback Implementation

```php
<?php
require_once 'config.php';
require_once 'helpers.php';

header('Content-Type: application/json');

// Get encrypted payload
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

// Decrypt payload
if (isset($data['payload'])) {
    $decrypted = openssl_decrypt(
        $data['payload'],
        'AES-256-ECB',
        API_SECRET,
        0
    );
    $data = json_decode($decrypted, true);
}

// Extract data
$userId = $data['member_account'];
$betAmount = (float)$data['bet_amount'];
$winAmount = (float)$data['win_amount'];

// Update balance in YOUR system
$currentBalance = getBalanceFromDatabase($userId);
$newBalance = $currentBalance - $betAmount + $winAmount;
updateBalanceInDatabase($userId, $newBalance);

// Return new balance
echo json_encode([
    'credit_amount' => $newBalance,
    'timestamp' => round(microtime(true) * 1000)
]);
?>
```

---

## 💰 Balance Management

### getUserBalance()

Get player's current balance.

```php
function getUserBalance($userId): float
```

**Example:**
```php
$balance = getUserBalance('12345');
echo "Balance: $" . $balance;
```

### setUserBalance()

Set or update player's balance.

```php
function setUserBalance($userId, $balance): float
```

**Example:**
```php
setUserBalance('12345', 100.00);
```

### Balance Storage

Default implementation uses JSON file storage:
- **File:** `logs/balances.json`
- **Format:**
```json
{
    "12345": 100.00,
    "67890": 250.50
}
```

### Database Integration

To use MySQL instead of file storage, modify `balance_helper.php`:

```php
function getUserBalance($userId) {
    $db = connectDatabase();
    $stmt = $db->prepare("SELECT balance FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return $user ? (float)$user['balance'] : 0;
}

function setUserBalance($userId, $balance) {
    $db = connectDatabase();
    $stmt = $db->prepare("
        INSERT INTO users (user_id, balance) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE balance = ?
    ");
    $stmt->execute([$userId, $balance, $balance]);
    return $balance;
}
```

---

## 💻 Code Examples

### Example 1: Basic Game Launch

```php
<?php
require_once 'config.php';
require_once 'api_request_builder.php';
require_once 'balance_helper.php';

// Initialize user
$userId = 'player_123';
$balance = 100.00;
setUserBalance($userId, $balance);

// Launch game
$params = createGameLaunchRequest($userId, $balance, '634');
$result = sendLaunchGameRequest($params);

if ($result['success']) {
    echo '<a href="' . $result['game_url'] . '">Play Game</a>';
}
?>
```

### Example 2: Launch with Currency & Language

```php
<?php
$params = createGameLaunchRequest(
    userId: 'player_123',
    balance: 100,
    gameUid: '634',
    currencyCode: 'EUR',
    language: 'es'
);
$result = sendLaunchGameRequest($params);
?>
```

### Example 3: Display Game in iFrame

```php
<?php
$result = sendLaunchGameRequest($params);
if ($result['success']) {
    echo '<iframe src="' . $result['game_url'] . '" 
          width="100%" height="600px" frameborder="0"></iframe>';
}
?>
```

### Example 4: Multiple Players

```php
<?php
$players = [
    ['id' => 'player1', 'balance' => 50],
    ['id' => 'player2', 'balance' => 100],
    ['id' => 'player3', 'balance' => 200]
];

foreach ($players as $player) {
    setUserBalance($player['id'], $player['balance']);
    
    $params = createGameLaunchRequest(
        $player['id'],
        $player['balance'],
        '634'
    );
    
    $result = sendLaunchGameRequest($params);
    if ($result['success']) {
        echo "Player {$player['id']}: {$result['game_url']}<br>";
    }
}
?>
```

---

## 📚 API Reference

### Supported Language Codes

See `language_codes.php` for 200+ supported languages including:
- `en` - English
- `es` - Spanish
- `fr` - French
- `de` - German
- `zh` - Chinese
- `ja` - Japanese
- `ko` - Korean
- `ar` - Arabic
- `bn` - Bengali
- And many more...

### Supported Currency Codes

See `currency_codes.php` for 170+ supported currencies including:
- `USD` - US Dollar
- `EUR` - Euro
- `GBP` - British Pound
- `JPY` - Japanese Yen
- `CNY` - Chinese Yuan
- `INR` - Indian Rupee
- `BDT` - Bangladeshi Taka
- `USDT` - Tether (Cryptocurrency)
- And many more...

### Error Codes

| Code | Message | Description |
|------|---------|-------------|
| 1 | GGR too low | Insufficient provider account balance |
| 9 | Invalid return URL | Return URL not whitelisted |
| 103 | Invalid return URL | MSG 103 - Domain mismatch |
| 305 | Single wallet balance not enough | Player balance insufficient |
| -1 | Various errors | Check error message for details |

---

## 🚀 Deployment

### Option 1: Manual Deployment

```bash
# 1. Upload files via FTP/SFTP
scp -r ./* user@yourserver.com:/var/www/html/apihan/

# 2. Set permissions
ssh user@yourserver.com "chmod 755 /var/www/html/apihan/*.php"
ssh user@yourserver.com "chmod 777 /var/www/html/apihan/logs"

# 3. Configure domain in SoftAPI dashboard
# - Domain: https://yourdomain.com/apihan/return.php
# - Callback: https://yourdomain.com/apihan/callback.php
```

### Option 2: Using Deployment Script

```bash
chmod +x deploy.sh
./deploy.sh
```

### Ngrok for Testing

```bash
# Start ngrok tunnel
ngrok http --domain=your-subdomain.ngrok-free.app 80

# Update config.php with ngrok URL
define('RETURN_URL', 'https://your-subdomain.ngrok-free.app/apihan/return.php');
define('CALLBACK_URL', 'https://your-subdomain.ngrok-free.app/apihan/callback.php');
```

---

## 🔍 Troubleshooting

### Common Issues

**1. "Invalid return URL" Error**
- **Cause:** URL not whitelisted in SoftAPI dashboard
- **Solution:** Add exact URL to Domain field in SoftAPI settings

**2. "GGR too low" Error**
- **Cause:** Insufficient provider account balance
- **Solution:** Add funds to your SoftAPI provider account

**3. "Single wallet balance not enough"**
- **Cause:** Player balance lower than bet amount
- **Solution:** Ensure player balance is sufficient before launching

**4. Callback not working**
- **Cause:** Callback URL not accessible or encryption issue
- **Solution:** Check callback URL is public and API_SECRET is 32 characters

**5. Balance not updating**
- **Cause:** Callback decryption failing
- **Solution:** Verify API_SECRET matches SoftAPI dashboard

### Debug Mode

Enable logging in `helpers.php`:

```php
function logMessage($message, $level = 'info') {
    $logFile = __DIR__ . '/logs/api_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(
        $logFile,
        "[{$timestamp}] [{$level}] {$message}\n",
        FILE_APPEND
    );
}
```

View logs:
```bash
tail -f logs/api_*.log
```

---

## 📞 Support

- **SoftAPI Documentation:** https://igamingapis.live/docs
- **Check Server Requirements:** Access `server_check.php` in browser
- **View Balances:** Access `view_balances.php` in browser
- **API Logs:** Check `logs/api_YYYY-MM-DD.log`

---

## 📄 License

This integration code is provided as-is for use with SoftAPI services.

---

## 🎯 Quick Start Checklist

- [ ] Install PHP 7.4+ with required extensions
- [ ] Configure `config.php` with SoftAPI credentials
- [ ] Set up web server (Apache/Nginx)
- [ ] Create `logs/` directory with write permissions
- [ ] Whitelist domain/callback URL in SoftAPI dashboard
- [ ] Test game launch with `index.php`
- [ ] Verify callback with test transactions
- [ ] Monitor logs for errors
- [ ] Ready for production!

---

**Last Updated:** December 28, 2025
