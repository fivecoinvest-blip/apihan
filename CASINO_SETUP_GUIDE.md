# 🎰 Casino Platform - Complete Setup Guide

## Project Overview
Full-featured casino platform with user authentication, game lobby, wallet management, and PWA support for PC, Mobile, Android, iOS.

## ✨ Features
- ✅ Phone Number Authentication (Philippines +639 format)
- ✅ Auto-generated Usernames
- ✅ Multi-Currency Support (Auto-detects location: PHP, USD, EUR, GBP, JPY, etc.)
- ✅ Real-time Balance Management (Database-backed with transactions)
- ✅ 206+ JILI Games (Database-managed with admin controls)
- ✅ Admin Dashboard:
  - User Management (view, edit balance, history)
  - Game Management (add, edit, upload images)
  - Betting History & Statistics
  - Revenue Tracking
- ✅ Responsive Design (PC, Tablet, Mobile)
- ✅ PWA Support (Install as App on Android/iOS)
- ✅ Transaction History & Audit Logs
- ✅ Secure API Integration with SoftAPI
- ✅ Real-time Balance Updates via Encrypted Callbacks
- ✅ Multiple Phone Number Format Support (09... or +639...)

---

## 📁 File Structure

```
casino/
├── login.php              # Login/Register page with currency detection
├── index.php              # Main game lobby (loads games from database)
├── play_game.php          # Game player page with draggable home button
├── logout.php             # Logout handler
├── admin.php              # Admin dashboard (games, users, history)
├── get_user_history.php   # AJAX endpoint for user transaction history
├── wallet.php             # Wallet management (to be created)
├── profile.php            # User profile (to be created)
├── db_helper.php          # Database functions
├── currency_helper.php    # Currency formatting and detection
├── setup_database.php     # Database setup script
├── setup_games_table.php  # Initial game database setup
├── update_jili_games.php  # Update JILI games (206 games)
├── setup_admin.php        # Admin account creation
├── migrate_currency.php   # Currency migration script
├── config.php             # API configuration
├── api_request_builder.php # SoftAPI integration
├── callback.php           # Game result handler (updates database)
├── balance_helper.php     # Balance management
├── manifest.json          # PWA manifest
├── service-worker.js      # PWA service worker
├── images/games/          # Game image uploads
└── logs/                  # Transaction logs
```

---

## 🚀 Installation Steps

### 1. Database Setup

Run the database setup scripts in order:

```bash
# Create main database and user tables
php setup_database.php

# Add JILI games to database (206 games)
php update_jili_games.php

# Create admin account (username: admin, password: admin123)
php setup_admin.php

# Add currency support (if updating existing installation)
php migrate_currency.php
```

This creates:
- `users` table - User accounts with phone auth and currency support
- `transactions` table - Financial transactions (bets, wins, deposits, withdrawals)
- `game_sessions` table - Game history
- `user_preferences` table - User settings
- `games` table - 206+ JILI games with metadata
- `admin_users` table - Admin authentication

### 2. Update config.php

```php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'casino_db');
define('DB_USER', 'casino_user');  // Use dedicated user, not root
define('DB_PASS', 'your_secure_password');

// SoftAPI Credentials
define('API_TOKEN', '5cd0be9827c469e7ce7d07abbb239e98');
define('API_SECRET', 'dc6b955933342d32d49b84c52b59184f');  // Must be exactly 32 bytes
define('SERVER_URL', 'https://igamingapis.live/api/v1');
define('RETURN_URL', 'https://31.97.107.21/');  // HTTPS for user-facing
define('CALLBACK_URL', 'http://31.97.107.21/callback.php');  // HTTP for API callbacks
```

### 3. Setup SSL Certificate

```bash
# Generate self-signed SSL certificate
ssh root@31.97.107.21 "openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/apache-selfsigned.key \
  -out /etc/ssl/certs/apache-selfsigned.crt \
  -subj '/C=PH/ST=Manila/L=Manila/O=Casino/OU=IT/CN=31.97.107.21'"

# Enable SSL module and site
ssh root@31.97.107.21 "a2enmod ssl && a2ensite default-ssl && systemctl reload apache2"
```

**Note:** Self-signed certificate will show browser warning. For production, use Let's Encrypt or commercial SSL.

### 4. Deploy to Server

```bash
# Upload all files to your server
scp -r ./* root@31.97.107.21:/var/www/html/

# Set permissions
ssh root@31.97.107.21 "chmod 755 /var/www/html/*.php"
ssh root@31.97.107.21 "chmod 777 /var/www/html/logs"
ssh root@31.97.107.21 "chown -R www-data:www-data /var/www/html"
```

### 4. Configure SoftAPI Dashboard

1. Go to SoftAPI Settings → API Configuration
2. Set Domain: `https://31.97.107.21/`
3. Set Callback URL: `http://31.97.107.21/callback.php` (HTTP for API access)
4. Server IP is whitelisted: 31.97.107.21
5. Save settings

**Note:** Use HTTP for callback URL to avoid SSL certificate issues with API callbacks.

---

## 📱 Platform Support

### PC/Web Browser
- Access: `https://31.97.107.21/login.php` or `https://31.97.107.21/`
- Fully responsive desktop interface with hover effects
- Supports all modern browsers (Chrome, Firefox, Safari, Edge)
- SSL certificate requires accepting security warning (self-signed)

### Mobile Web
- Access: `https://31.97.107.21/login.php`
- Mobile-optimized responsive design
- Touch-friendly interface with hover play buttons
- Draggable floating home button in games
- Full-screen game experience without navigation bars

### PWA (Progressive Web App)
- Install on **Android**: Open in Chrome → Menu → "Install App"
- Install on **iOS**: Open in Safari → Share → "Add to Home Screen"
- Works offline (cached games)
- Native app-like experience
- Push notifications support

### Android App
The PWA can be installed as a native-feeling app on Android:
1. Visit site in Chrome
2. Banner prompts "Add to Home Screen"
3. App installs with icon on home screen
4. Opens in standalone mode (no browser UI)

### iOS App
iOS users can install via Safari:
1. Visit site in Safari
2. Tap Share icon
3. Select "Add to Home Screen"
4. App appears on home screen
5. Opens in fullscreen mode

---

## 🎮 User Flow

### 1. Registration
```
Visit login.php → Click Register Tab → Enter phone number & password
↓
Default currency set to PHP (Philippine Peso) for PH market
↓
Username auto-generated: user_XXXXXXXX (from phone number)
↓
Account created with 100.00 PHP starting balance
↓
Redirect to login with generated username displayed
```

**Phone Number Formats Accepted:**
- `09123456789` (11 digits, starts with 09)
- `+639123456789` (with country code)
- `9123456789` (10 digits, auto-adds 0)

**Default Currency:**
- PHP (₱) - Philippine Peso (All users default to PHP)
- Currency displayed with peso symbol (₱) throughout the platform
- Multi-currency support available but defaults to PHP for PH market

### 2. Login
```
Enter phone number (09...) or username + password → Submit
↓
System normalizes phone format (+639...) and validates
↓
Create session with user_id, username, phone, currency
↓
Redirect to index.php (Main Game Lobby)
```

### 3. Play Game
```
Browse games in lobby → Hover over game card → Click "▶ Play"
↓
System checks balance → Launch game via SoftAPI
↓
Game loads in full-screen responsive iframe
↓
Draggable floating home button appears in top-right
↓
Player places bets → Callback receives encrypted results
↓
Balance updated in database → Transaction logged
↓
User sees updated balance in real-time
↓
Click/tap floating button to return home (without moving = click)
```

### 4. Balance Management
```
User balance stored in database (users.balance)
↓
On game launch: Balance sent to SoftAPI
↓
On bet/win: Encrypted callback updates database
↓
Transactions logged in transactions table
↓
Real-time balance display in header with currency symbol
```

---

## 🎯 Admin Panel

### Access Admin Dashboard

**URL:** `https://31.97.107.21/admin.php`

**Default Credentials:**
- Username: `admin`
- Password: `admin123`

### Admin Features

**1. Dashboard Statistics:**
- Total Users
- Active Games
- Total Bets Placed
- Total Revenue

**2. Game Management:**
- View all 206+ JILI games
- Add new games
- Edit game details (name, provider, category)
- Upload custom game images
- Enable/disable games
- Set sort order
- Delete games

**3. User Management:**
- View all registered users
- See user details (username, phone, balance, currency)
- Edit user balance
- View user registration date
- Check last login time
- User status (active/inactive)

**4. Betting History:**
- View all transactions system-wide
- Filter by user
- See bet/win amounts
- Track balance changes (before/after)
- View timestamps
- Export data capability

**5. User Transaction History:**
- Click "View History" on any user
- See individual user's betting activity
- Track all bets and wins
- Monitor balance flow

### Change Admin Password

Edit `setup_admin.php` and run:
```bash
php setup_admin.php
```

---

## 🔧 Customization

### Update JILI Games Database

To update all JILI games from the latest list:

```bash
php update_jili_games.php
```

This script will:
- Add new games from JILI catalog
- Update existing games with correct names/categories
- Show detailed import summary

### Add More Games via Admin Panel

1. Login to admin panel
2. Go to "Games" tab
3. Click "Add New Game"
4. Fill in:
   - Game UID (for API launch)
   - Game Name
   - Provider (JILI, Pragmatic, etc.)
   - Category (Slots, Table, Fishing, Arcade)
5. Upload game image
6. Set active status

### Or Add Games Manually

Edit `setup_games_table.php` and add to array:

```php
['game_uid', 'Game Name', 'Provider', 'Category'],
```

Then run:
```bash
php setup_games_table.php
```

### Change Starting Balance

Edit `db_helper.php` in `register()` function:

```php
VALUES (?, ?, ?, 100.00, ?, ?)  // Change 100.00 to desired amount
```

### Change Default Currency

All users default to **PHP (Philippine Peso)** for the PH market.

To change default currency, edit `login.php`:

```php
// In registration section, change 'PHP' to your preferred currency
$result = $user->register($phone, $password, '+639', 'USD'); // Change PHP to USD, EUR, etc.
```

Also update database default in `setup_database.php`:
```php
currency VARCHAR(3) DEFAULT 'USD',  // Change from 'PHP' to your preferred default
```

To update all existing users to a new currency:
```bash
ssh root@your-server
cd /var/www/html/apihan
mysql -ucasino_user -pcasino123 casino_db -e "UPDATE users SET currency = 'USD';"
```

### Change Phone Number Country Code

Edit `db_helper.php` to support different countries:

```php
// Change default from +639 to your country code
public function register($phone, $password, $countryCode = '+1') {  // +1 for USA
    // ...
}

private function normalizePhoneNumber($phone, $countryCode = '+1') {
    // Update logic for your country format
}
```

### Disable Auto-generated Username

If you want manual usernames, edit `db_helper.php`:

```php
// Change register function to accept username parameter
public function register($username, $phone, $password, $countryCode = '+639') {
    // Remove generateUsername() call
    // Use $username directly
}
```

### Customize Colors/Theme

Edit CSS in `login.php`, `casino.php`:

```css
/* Primary gradient */
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

/* Dark background */
background: #0f172a;

/* Card background */
background: #1e293b;
```

---

## 📊 Database Schema

### users
```sql
id, username (auto-generated), phone, password, balance, currency,
country_code, created_at, last_login, status
```

**Phone Number Format:**
- Accepts: `09XXXXXXXXX` or `+639XXXXXXXXX`
- Stores: `+639XXXXXXXXX` (normalized)
- Displays: `09XXXXXXXXX` (user-friendly)

**Username:**
- Auto-generated from phone: `user_XXXXXXXX` (last 8 digits)
- Example: Phone `09123456789` → Username `user_23456789`

**Currency:**
- Auto-detected from IP location during registration
- Stored as 3-letter code: PHP, USD, EUR, etc.
- Affects balance display and formatting

### games
```sql
id, game_uid, name, provider, category, image, is_active,
sort_order, created_at, updated_at
```

**Fields:**
- `game_uid`: Unique identifier for launching (e.g., '634')
- `name`: Display name (e.g., 'Agent Ace')
- `provider`: Game provider (e.g., 'JILI')
- `category`: Game type (e.g., 'Slots')
- `image`: Custom uploaded image path
- `is_active`: Enable/disable game (1/0)
- `sort_order`: Display order in lobby

### transactions
```sql
id, user_id, type, amount, balance_before, balance_after,
game_uid, game_round, description, created_at
```

### game_sessions
```sql
id, user_id, game_uid, game_name, start_balance, end_balance,
total_bets, total_wins, rounds_played, started_at, ended_at, status
```

---

## 🔐 Security Features

1. **Password Hashing**: bcrypt algorithm
2. **Session Management**: PHP sessions with secure cookies
3. **SQL Injection Prevention**: PDO prepared statements
4. **XSS Protection**: htmlspecialchars() on outputs
5. **CSRF Protection**: Session-based validation
6. **API Encryption**: AES-256-ECB for SoftAPI

---

## 🐛 Troubleshooting

### "Failed to connect to database"
- Check DB credentials in `config.php`
- Ensure MySQL is running
- Run `setup_database.php` again

### "Invalid credentials" / "Invalid phone number"
- Phone format: `09XXXXXXXXX` (11 digits)
- Or use generated username: `user_XXXXXXXX`
- Password is case-sensitive
- Try registering a new account

### "Phone number already registered"
- Each phone can only register once
- Try logging in instead
- Contact support if you forgot credentials

### "Game failed to launch"
- Check SoftAPI credentials in `config.php`
- Verify domain is whitelisted in SoftAPI dashboard
- Ensure callback URL is accessible
- Check provider account balance (GGR)

### "Balance not updating"
- Check callback.php is publicly accessible
- Verify API_SECRET is 32 characters
- Check logs in `/var/www/html/casino/logs/`

### PWA Not Installing
- Requires HTTPS (use ngrok for testing)
- Clear browser cache
- Check manifest.json is accessible
- Ensure service-worker.js is in root directory

---

## � Backup System

### GitHub Repository

Backup repository: https://github.com/fivecoinvest-blip/apihan

### Automated Backup Script

```bash
# Run backup script (syncs server to local, commits to GitHub)
./backup_server.sh
```

**What it does:**
1. Syncs all files from server to local PC using rsync
2. Commits changes to Git with timestamp
3. Pushes to GitHub repository

**Script location:** `/home/neng/Desktop/apihan/backup_server.sh`

---

## �📈 Next Steps

1. **Add Wallet Page**: Deposits, withdrawals, transaction history
2. **Add Profile Page**: Edit user info, change password
3. **Add Game History**: Show past sessions and results
4. **Add Promotions**: Bonuses, free spins, cashback
5. **Add Live Chat**: Customer support integration
6. **Add Payment Gateway**: Stripe, PayPal integration
7. **Add Admin Panel**: Manage users, games, transactions
8. **Add Analytics**: Track gameplay, popular games, revenue

---

## 📱 Testing PWA

### Local Testing with ngrok:

```bash
# Start ngrok
ngrok http --domain=your-subdomain.ngrok-free.app 80

# Update config.php with ngrok URL
define('RETURN_URL', 'https://your-subdomain.ngrok-free.app/casino.php');
define('CALLBACK_URL', 'https://your-subdomain.ngrok-free.app/callback.php');

# Access on mobile
https://your-subdomain.ngrok-free.app/login.php

# Install PWA
Chrome: Menu → Install App
Safari: Share → Add to Home Screen
```

---

## 🎯 Production Checklist

- [ ] Database configured with secure credentials
- [ ] SoftAPI credentials updated in config.php
- [ ] Domain whitelisted in SoftAPI dashboard
- [ ] HTTPS enabled (SSL certificate installed)
- [ ] File permissions set correctly (755 for PHP, 777 for logs)
- [ ] Error reporting disabled in production
- [ ] Session security configured
- [ ] Backup system in place
- [ ] Monitoring/logging enabled
- [ ] Terms & Conditions added
- [ ] Privacy Policy added
- [ ] Responsible gaming information
- [ ] Payment gateway integrated
- [ ] Customer support system

---

## 📞 Support

- **SoftAPI Docs**: https://igamingapis.live/docs
- **Check Logs**: `logs/api_*.log`, `logs/transactions.log`
- **Database Issues**: Run `setup_database.php` again
- **Balance Issues**: Check `callback.php` logs

---

**Last Updated**: December 29, 2025
**Version**: 1.2.0

**Recent Changes:**
- **Updated JILI games catalog to 206+ games** (143 new, 63 updated)
- Added user tracking system (IP, device, browser, OS, login history)
- Enhanced admin panel with user activity statistics
- Improved edit user modal with dark theme
- Added session duration tracking
- Added betting totals tracking (total_bets, total_wins)
- Moved casino.php to index.php as main page
- Removed /apihan/ subdirectory
- Added SSL certificate (self-signed)
- Implemented draggable floating home button
- Enhanced mobile responsiveness
- Fixed balance tracking and callbacks
- Added GitHub backup system
