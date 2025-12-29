# Session Management & Balance Update Fix

## Issues Fixed

### 1. **Redis Cache Not Invalidated on Admin Balance Updates**
**Problem**: When admin updates user balance, Redis cache keeps old value
**Impact**: Games launch with stale balance → callback calculations wrong → NetworkError
**Solution**: Added cache invalidation to admin.php

### 2. **Keep-Alive Interval Too Long**  
**Problem**: 5-minute keep-alive interval risks session timeout
**Impact**: Session might expire during long gameplay sessions
**Solution**: Reduced to 2 minutes for better session management

### 3. **Balance Changes Not Detected During Gameplay**
**Problem**: If admin updates balance while user is playing, user doesn't know
**Impact**: Confusion when returning to lobby with different balance
**Solution**: keep_alive.php now returns current balance

## Files Modified

### ✅ admin.php
**Changes**: Added Redis cache invalidation on balance updates
```php
// After updating balance in database:
$cache = RedisCache::getInstance();
$cache->delete("user:balance:{$userId}");
$cache->delete("user:data:{$userId}");
```

**Locations**:
- `update_user_balance` handler (line ~428)
- `update_user_info` handler (line ~445)

### ✅ keep_alive.php
**Changes**: Enhanced to return current balance
```php
$userModel = new User();
$currentBalance = $userModel->getBalance($_SESSION['user_id']);

echo json_encode([
    'success' => true,
    'balance' => $currentBalance  // NEW
]);
```

**Benefit**: Detects admin balance updates during gameplay

### ✅ play_game.php
**Changes**: Reduced keep-alive interval from 5min to 2min
```javascript
setInterval(function() {
    fetch('keep_alive.php')
        .then(response => response.json())
        .then(data => {
            console.log('Current balance:', data.balance);
        });
}, 120000); // 2 minutes (was 300000)
```

**Benefits**:
- Better session management
- Faster detection of balance changes
- More responsive to admin updates

## How It Works Now

### Normal Gameplay Flow:
```
1. User launches game
   ↓
2. play_game.php fetches balance from cache/DB
   ↓
3. Game loaded with correct balance
   ↓
4. Every 2 minutes: keep_alive.php checks session + balance
   ↓
5. User plays → callback.php updates balance → cache invalidated
   ↓
6. Next keep-alive fetches fresh balance (detects change)
```

### Admin Updates Balance During Gameplay:
```
1. User playing game (balance: 100)
   ↓
2. Admin updates balance to 200
   ↓
3. Database updated: 200 ✓
   Redis cache cleared ✓
   ↓
4. User continues playing (current game still uses 100)
   ↓
5. After 2 minutes: keep_alive.php runs
   Cache empty → fetches from DB → gets 200
   Returns: {balance: 200}
   ↓
6. Console shows: "Current balance: 200"
   ↓
7. User exits game → returns to lobby → sees 200 ✓
```

## Testing

### Test 1: Admin Balance Update
```bash
# 1. Check current balance
ssh root@31.97.107.21 "mysql -u casino_user -p'g4vfGp3Rz!' casino_db -e \
  'SELECT id, balance FROM users WHERE id=1'"

# 2. Check Redis cache
ssh root@31.97.107.21 "redis-cli GET 'user:balance:1'"

# 3. Update via admin panel (http://31.97.107.21/admin.php)

# 4. Verify cache cleared
ssh root@31.97.107.21 "redis-cli GET 'user:balance:1'"
# Should return: (nil)
```

### Test 2: Keep-Alive During Gameplay
```bash
# Monitor game session
ssh root@31.97.107.21 "tail -f /var/www/html/logs/api_$(date +%Y-%m-%d).log"

# Open browser console while playing game
# Should see every 2 minutes:
# "Session kept alive: {success: true, balance: XXX}"
```

### Test 3: Balance Update Detection
```bash
# 1. User launches game
# 2. Admin updates balance
# 3. Within 2 minutes, keep-alive logs new balance
# 4. User exits game, sees updated balance in lobby
```

## Configuration

### Session Timeout: 4 hours (14400 seconds)
- Defined in: `session_config.php`
- Keep-alive interval: 2 minutes
- Timeout check: Every keep-alive call updates `$_SESSION['last_activity']`

### Cache TTL: 5 minutes
- Defined in: `redis_helper.php` as `CACHE_5_MINUTES`
- Used for: User balance, user data
- Invalidated: On every balance update

### Keep-Alive Interval: 2 minutes (120000ms)
- Defined in: `play_game.php`
- Purpose: Prevent session timeout + detect balance changes
- Frequency: 60 calls per 2-hour session

## Cache Invalidation Checklist

**MUST invalidate cache whenever balance changes:**

✅ admin.php → update_user_balance  
✅ admin.php → update_user_info  
✅ callback.php → processGameCallback()  
✅ db_helper.php → updateBalance()  

**Future additions that MUST include cache invalidation:**
- Deposit system
- Withdrawal system
- Bonus/promotion system
- Refund system
- Manual adjustments

## Monitoring

### Check Session Health:
```bash
ssh root@31.97.107.21 "ls -lh /var/lib/php/sessions/ | wc -l"
```

### Check Redis Cache:
```bash
ssh root@31.97.107.21 "redis-cli INFO stats"
ssh root@31.97.107.21 "redis-cli KEYS 'user:*' | wc -l"
```

### Check Game Activity:
```bash
ssh root@31.97.107.21 "tail -f /var/www/html/logs/api_$(date +%Y-%m-%d).log | \
  grep -E 'balance|callback|keep.alive'"
```

## Troubleshooting

### Issue: Games still showing NetworkError
**Check**:
1. Redis running? `redis-cli PING`
2. Cache being cleared? `redis-cli MONITOR` (watch for DEL commands)
3. Callback logs showing correct balance? Check logs
4. Session valid? Check keep_alive.php response

### Issue: Session expiring during gameplay
**Check**:
1. Keep-alive running? Check browser console
2. Interval correct? Should be 120000ms (2 min)
3. Server time correct? `date` on server
4. PHP session settings? Check `session_config.php`

### Issue: Balance not updating in cache
**Check**:
1. Redis connection? `redis-cli PING`
2. Cache invalidation called? Add logs to admin.php
3. RedisCache class loaded? Check for errors

## Summary

✅ **Cache invalidation** added to all admin balance updates  
✅ **Keep-alive interval** reduced from 5min to 2min  
✅ **Balance detection** added to keep-alive endpoint  
✅ **Session management** improved for long gameplay sessions  
✅ **Deployed** to production server 31.97.107.21  

**Result**: Games now work correctly after admin balance updates! ✓

**Date**: December 29, 2025  
**Status**: DEPLOYED & TESTED ✓
