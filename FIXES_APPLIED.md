# Telegram Bot Withdrawal System - Fixes Applied

## Issue: Receipt Upload Not Working

### Root Cause
The code had an inconsistency between how it stored and retrieved the pending receipt state:
1. **handleUploadReceipt()** - Stored pending receipt state in `telegram_pending_receipts` database table
2. **handleReceiptUpload()** - Was trying to find it in `$_SESSION['pending_approval_']`

This mismatch prevented the receipt upload from being matched with the pending transaction.

### Fix Applied
Updated `handleReceiptUpload()` function to:
1. Query the `telegram_pending_receipts` database table instead of SESSION
2. Get the most recent pending receipt for the user's chat_id
3. Process the approval with the receipt image
4. Clean up the database record after processing
5. Added proper error handling for various failure scenarios

### Changes Made
**File:** `/home/neng/Desktop/apihan/telegram_bot.php`

**Function:** `handleReceiptUpload()` (lines 472-531)

**Key Changes:**
```php
// OLD: foreach ($_SESSION as $key => $value) {
//     if (strpos($key, 'pending_approval_') === 0 && $value['chat_id'] == $chatId) {

// NEW: Query database for pending receipt
$stmt = $this->pdo->prepare("
    SELECT transaction_id, message_id FROM telegram_pending_receipts 
    WHERE chat_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->execute([$chatId]);
$pending = $stmt->fetch(PDO::FETCH_ASSOC);
```

### Flow After Fix
1. User clicks "✅ Approve" button on withdrawal notification
2. Bot shows options: "📷 With Receipt" or "⚠️ Without Receipt"
3. User clicks "📷 With Receipt"
4. State is saved to `telegram_pending_receipts` table
5. Bot requests receipt image
6. User sends receipt image
7. **NEW:** Bot checks `telegram_pending_receipts` table (not SESSION)
8. Finds matching transaction and processes approval
9. Receipt is saved and transaction is marked as approved
10. Pending receipt record is deleted from database

### Database Table
The system uses `telegram_pending_receipts` table with structure:
```sql
CREATE TABLE IF NOT EXISTS telegram_pending_receipts (
    transaction_id INT PRIMARY KEY,
    chat_id BIGINT NOT NULL,
    message_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

This table is auto-created if it doesn't exist when the bot first processes a receipt upload request.

### Error Handling
Added comprehensive error messages:
- If no pending receipt found: "❌ No pending receipt upload found. Please click 'With Receipt' button first."
- If receipt download fails: "❌ Failed to download receipt image. Please try again."
- If file info fails: "❌ Failed to get file info from Telegram."
- If database error: "❌ Database error: [error message]"

### Verification
- ✅ PHP syntax validation passed
- ✅ Database table creation handled
- ✅ Session removal cleaned up (replaced with database cleanup)
- ✅ All error paths handled

## Testing Recommendations
1. Start a new withdrawal
2. Click "✅ Approve" button
3. Select "📷 With Receipt"
4. Send a receipt image
5. Verify: Transaction should be approved with receipt attached
6. Check database for successful cleanup
