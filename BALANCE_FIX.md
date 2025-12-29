# Balance Update and Game Connectivity Issue - FIXED

## Problem Description

**Reported Issue**: "when we update or config the balance game is not working"

Users experiencing NetworkError (MSG 199) in games after admin updates their balance manually.

## Root Cause

When admin manually updates user balance through the admin panel:

1. **Database gets updated** → Balance = 100 (new value)
2. **Redis cache NOT invalidated** → Cached balance = 50 (old value)
3. **User launches game** → Game receives cached balance (50) instead of real balance (100)
4. **User plays and wins 10**:
   - Callback calculates: 50 - 0 + 10 = **60**
   - Should be: 100 - 0 + 10 = **110**
5. **Balance mismatch** → Game API detects inconsistency → Shows NetworkError

## Technical Flow

### Before Fix (Broken):
```
Admin Updates Balance
      ↓
  Database Updated (✓)
      ↓
  Redis Cache NOT Cleared (✗)
      ↓
  Game Launches
      ↓
  Gets Stale Cached Balance
      ↓
  Callback Uses Wrong Balance
      ↓
  Game Shows NetworkError
```

### After Fix (Working):
```
Admin Updates Balance
      ↓
  Database Updated (✓)
      ↓
  Redis Cache Invalidated (✓)
      ↓
  Game Launches
      ↓
  Fetches Fresh Balance from DB
      ↓
  Callback Uses Correct Balance
      ↓
  Game Works Properly! ✓
```

## Solution Implemented

### Fixed Files:

**1. admin.php** - Added cache invalidation on balance updates:

```php
// Handle user balance update
if (isset($_POST['update_user_balance'])) {
    $userId = $_POST['user_id'];
    $stmt = $pdo->prepare("UPDATE users SET balance = ?, currency = ? WHERE id = ?");
    $stmt->execute([$_POST['new_balance'], $_POST['currency'], $userId]);
    
    // ✓ NEW: Invalidate Redis cache for this user
    $cache = RedisCache::getInstance();
    $cache->delete("user:balance:{$userId}");
    $cache->delete("user:data:{$userId}");
    
    $_SESSION['success'] = "User balance updated successfully!";
    header("Location: admin.php");
    exit;
}
```

**2. User Information Update** - Also fixed:

```php
// Handle user information update
if (isset($_POST['update_user_info'])) {
    // ... update user info including balance ...
    
    // ✓ NEW: Invalidate Redis cache for this user
    $cache = RedisCache::getInstance();
    $cache->delete("user:balance:{$userId}");
    $cache->delete("user:data:{$userId}");
    
    $_SESSION['success'] = "User information updated successfully!";
    header("Location: admin.php");
    exit;
}
```

## Testing

Run the test script to verify cache invalidation:

```bash
ssh root@31.97.107.21 "cd /var/www/html && php test_balance_cache.php"
```

## Verification Steps

1. **Admin updates balance**:
   - Log in to admin panel: http://31.97.107.21/admin.php
   - Update a user's balance
   - Verify success message appears

2. **Check cache invalidation**:
   ```bash
   ssh root@31.97.107.21 "redis-cli GET 'user:balance:1'"
   ```
   Should return `(nil)` immediately after update

3. **Launch game**:
   - User logs in
   - Launches any game
   - Verify game loads without NetworkError
   - Play a few rounds
   - Verify balance updates correctly

4. **Check logs**:
   ```bash
   ssh root@31.97.107.21 "tail -20 /var/www/html/logs/api_$(date +%Y-%m-%d).log"
   ```
   Should show correct balance calculations

## Related Files

- `/var/www/html/admin.php` - Admin panel (FIXED)
- `/var/www/html/callback.php` - Game callback handler (already has cache invalidation)
- `/var/www/html/db_helper.php` - Database helper (already has cache invalidation)
- `/var/www/html/get_balance.php` - Balance API endpoint (working)
- `/var/www/html/redis_helper.php` - Redis cache manager

## Cache Invalidation Checklist

Any code that updates user balance MUST invalidate these cache keys:

```php
$cache = RedisCache::getInstance();
$cache->delete("user:balance:{$userId}");  // Balance cache
$cache->delete("user:data:{$userId}");     // User data cache
```

### Places Already Fixed:
- ✓ admin.php → update_user_balance
- ✓ admin.php → update_user_info
- ✓ callback.php → processGameCallback()
- ✓ db_helper.php → updateBalance()

### Future Additions:
- If you add deposit/withdrawal features, add cache invalidation
- If you add bonus/promotion systems, add cache invalidation
- If you add manual balance adjustments, add cache invalidation

## Best Practices

1. **Always invalidate cache** when updating balance in database
2. **Use db_helper.php methods** instead of direct SQL queries (they handle cache automatically)
3. **Monitor Redis cache** using system_status.php dashboard
4. **Test after changes** using test_balance_cache.php script

## Troubleshooting

### If games still show NetworkError:

1. Check if Redis is running:
   ```bash
   ssh root@31.97.107.21 "redis-cli PING"
   ```
   Should return: `PONG`

2. Clear all user caches:
   ```bash
   ssh root@31.97.107.21 "redis-cli KEYS 'user:*' | xargs redis-cli DEL"
   ```

3. Check callback logs:
   ```bash
   ssh root@31.97.107.21 "tail -50 /var/www/html/logs/api_$(date +%Y-%m-%d).log"
   ```

4. Verify balance consistency:
   ```bash
   ssh root@31.97.107.21 "mysql -u casino_user -p'g4vfGp3Rz!' casino_db -e 'SELECT id, username, balance FROM users'"
   ```

## Summary

✓ **Fixed**: Admin balance updates now properly invalidate Redis cache  
✓ **Fixed**: User info updates now properly invalidate Redis cache  
✓ **Result**: Games work correctly after manual balance changes  
✓ **Deployed**: Live on server 31.97.107.21  

**Date Fixed**: December 29, 2025  
**Status**: RESOLVED ✓
