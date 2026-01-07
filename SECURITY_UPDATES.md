# Security Updates - Login & Registration

## Changes Made

### 1. IP-Based Registration Limit ✅
**Prevents bot registrations and multiple account abuse**

- **Limit**: Maximum 3 accounts per IP address
- **Database**: New table `ip_registrations` tracks registration count per IP
- **User Message**: "Maximum registration limit reached from this IP address. Contact support if you need help."
- **Tracking**: Records first_registration and last_registration timestamps

### 2. Login Attempt Limiting ✅
**Blocks brute force attacks**

- **Limit**: 10 failed attempts per IP per day
- **Reset**: Counter resets at midnight (new day)
- **Database**: New table `login_attempts` logs all failed attempts
- **User Feedback**:
  - When ≤ 3 attempts remaining: Shows warning "X attempts remaining before lockout"
  - When locked out: "Too many failed login attempts. Your IP is now blocked until tomorrow."
- **Auto-clear**: Successful login clears all failed attempts for that IP

## Database Tables Created

### `login_attempts`
```sql
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(50) NOT NULL,
    username_or_phone VARCHAR(100) NOT NULL,
    attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempt_time),
    INDEX idx_user_time (username_or_phone, attempt_time)
);
```

### `ip_registrations`
```sql
CREATE TABLE ip_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(50) NOT NULL,
    registration_count INT DEFAULT 1,
    first_registration DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_registration DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ip (ip_address)
);
```

## Files Modified

1. **login.php** - Added security checks for both login and registration

## How to Deploy

1. **Database tables are already created** on the server ✅
2. **Upload the modified login.php**:
   ```bash
   scp /home/neng/Desktop/apihan/login.php root@31.97.107.21:/var/www/html/
   ```
3. **Test the security features**:
   - Try registering 4 accounts from same IP (4th should fail)
   - Try 11 failed logins (11th should be blocked)
   - Wait until next day, login should work again

## Security Benefits

✅ **Prevents bot account creation** - Limits automated registration attacks  
✅ **Stops brute force attacks** - Blocks password guessing attempts  
✅ **Protects user accounts** - Limits unauthorized access attempts  
✅ **Reduces server load** - Fewer spam registrations and login attempts  
✅ **User-friendly warnings** - Shows remaining attempts before lockout  
✅ **Auto-recovery** - Resets daily, no manual intervention needed

## Notes

- IP detection handles proxy/CDN setups (checks `HTTP_X_FORWARDED_FOR`)
- Failed attempts are counted per calendar day (resets at midnight server time)
- Registration limit is cumulative (never resets unless manually cleared)
- Successful login clears all failed attempts for that IP
