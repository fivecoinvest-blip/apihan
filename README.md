# SoftAPI Gaming Integration

Complete PHP implementation for integrating SoftAPI gaming platform with your website.

## 📁 Files Overview

- **config.php** - API credentials and configuration settings
- **helpers.php** - Encryption and utility functions
- **launch_game.php** - Launch game sessions with encrypted user data
- **callback.php** - Handle game result callbacks from SoftAPI
- **example_launch.php** - Usage examples for launching games

## 🚀 Quick Start

### 1. Configure API Credentials

Edit `config.php` and add your SoftAPI credentials:

```php
define('API_TOKEN', 'your_token_here');
define('API_SECRET', 'your_32_character_secret_key');
```

### 2. Launch a Game

```php
require_once 'launch_game.php';

$result = launchGame(
    userId: 101,           // User ID
    balance: 500,          // User balance
    gameUid: 784512,       // Game session ID
    currencyCode: 'BDT',   // Optional
    language: 'bn'         // Optional
);

if ($result['success']) {
    // Redirect user to game
    header('Location: ' . $result['game_url']);
}
```

### 3. Setup Callback Endpoint

Configure your callback URL in `config.php`:

```php
define('CALLBACK_URL', 'https://yoursite.com/callback.php');
```

The `callback.php` will automatically handle game results.

## 🔐 Security Features

✅ **AES-256-ECB Encryption** - All data encrypted before transmission  
✅ **Token Authentication** - API token validation  
✅ **Timestamp Verification** - Prevent replay attacks  
✅ **Input Validation** - Validate all callback data  
✅ **Error Logging** - Comprehensive logging system

## 📊 API Flow

```
User clicks "Play Game"
    ↓
Your Website → encrypt user data
    ↓
Send to SoftAPI → launch endpoint
    ↓
Receive game URL
    ↓
Redirect user to game
    ↓
User plays game
    ↓
Game sends callback → your callback.php
    ↓
Update user balance
    ↓
Return confirmation
```

## 🔄 Callback Data Format

When a user plays, SoftAPI sends:

```json
{
    "game_uid": "784512",
    "game_round": "12928475122950747877",
    "member_account": "101",
    "bet_amount": 50,
    "win_amount": 30,
    "timestamp": "2025-12-28 16:41:45"
}
```

Your callback must respond with:

```json
{
    "credit_amount": 480,
    "timestamp": 1735387305000
}
```

## 📝 Usage Examples

### Example 1: Simple Launch

```php
$result = launchGame(101, 500);
header('Location: ' . $result['game_url']);
```

### Example 2: With Optional Parameters

```php
$result = launchGame(
    userId: 101,
    balance: 500,
    gameUid: null,          // Auto-generated
    currencyCode: 'USD',
    language: 'en'
);
```

### Example 3: Via URL (Direct Access)

```
https://yoursite.com/launch_game.php?user_id=101&balance=500&currency_code=USD
```

## 🗄️ Database Integration

Update `callback.php` to connect to your database:

```php
function processGameCallback(string $userId, float $betAmount, float $winAmount): float {
    $db = connectDatabase();
    
    // Get current balance
    $stmt = $db->prepare("SELECT balance FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $currentBalance = $stmt->fetchColumn();
    
    // Calculate new balance
    $newBalance = $currentBalance - $betAmount + $winAmount;
    
    // Update balance
    $stmt = $db->prepare("UPDATE users SET balance = ? WHERE user_id = ?");
    $stmt->execute([$newBalance, $userId]);
    
    // Log transaction
    $stmt = $db->prepare("
        INSERT INTO transactions (user_id, bet_amount, win_amount, balance_after, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $betAmount, $winAmount, $newBalance]);
    
    return $newBalance;
}
```

## 📋 Required Parameters

### Launch Game API

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| user_id | int | ✅ | Player's unique ID |
| balance | float | ✅ | Player's wallet balance |
| game_uid | int | ❌ | Game session ID (auto-generated) |
| token | string | ✅ | API token (from config) |
| timestamp | int | ✅ | Current time in milliseconds |
| return | string | ✅ | Return URL |
| callback | string | ✅ | Callback URL |
| currency_code | string | ❌ | Currency code (BDT, USD, etc.) |
| language | string | ❌ | Language code (bn, en, etc.) |

### Callback Data

| Parameter | Type | Description |
|-----------|------|-------------|
| game_uid | string | Game session ID |
| game_round | string | Unique round ID |
| member_account | string | User ID |
| bet_amount | float | Amount bet |
| win_amount | float | Amount won |
| timestamp | string | Round timestamp |

## 🔍 Logs

All API activities are logged in the `logs/` directory:

```
logs/
  api_2025-12-28.log
  api_2025-12-29.log
```

## ⚠️ Important Notes

1. **Secret Key**: Must be exactly 32 characters long
2. **Callback URL**: Must be publicly accessible HTTPS endpoint
3. **Balance**: Callback must return user's CURRENT balance
4. **Error Handling**: Always validate and log all transactions
5. **Testing**: Test in sandbox environment before going live

## 🛠️ Requirements

- PHP 7.4 or higher
- OpenSSL extension enabled
- cURL extension enabled
- MySQL/MariaDB (for production)

## 📞 Support

For API issues, contact SoftAPI support or refer to their official documentation.

## 📄 License

This implementation is provided as-is for integration with SoftAPI services.
