# Telegram Bot Testing Checklist

## Scenario 1: Approval WITH Receipt Upload

### Steps
1. User initiates a withdrawal from the wallet
2. Admin receives Telegram notification with buttons:
   - ✅ Approve
   - ❌ Reject
3. Click "✅ Approve" button
4. Bot displays options:
   - 📷 With Receipt (upload image)
   - ⚠️ Without Receipt
   - 🔙 Cancel
5. Click "📷 With Receipt"
6. Bot responds: "Please send the receipt image as a reply to this message"
7. Send a photo/screenshot of receipt
8. Bot should respond: "✅ Withdrawal approved successfully with receipt!"
9. Original notification should update to show: "✅ Approved with Receipt"
10. Database should show:
    - Transaction status = 'completed'
    - receipt_image = '/path/to/uploaded/receipt'
    - telegram_pending_receipts table cleaned up

### Expected Result
✅ Transaction marked as completed with receipt attached

---

## Scenario 2: Approval WITHOUT Receipt

### Steps
1. User initiates a withdrawal
2. Admin receives Telegram notification
3. Click "✅ Approve" button
4. Click "⚠️ Without Receipt"
5. Bot responds: "✅ Withdrawal approved successfully!"
6. Notification updates to: "✅ Approved" with "⚠️ No receipt attached."
7. Database shows:
    - Transaction status = 'completed'
    - receipt_image = NULL

### Expected Result
✅ Transaction marked as completed without receipt

---

## Scenario 3: Receipt Upload Error Handling

### Edge Cases to Test

#### 3a: Upload Receipt without clicking "With Receipt" first
- Send photo without clicking "With Receipt" button
- Expected: "❌ No pending receipt upload found. Please click 'With Receipt' button first."

#### 3b: Cancel and retry
- Click "📷 With Receipt"
- Click "🔙 Cancel" 
- Click "✅ Approve" again
- Click "📷 With Receipt" again
- Send receipt
- Expected: Should work fine with retry

#### 3c: Failed image download (if applicable)
- Send corrupted/invalid image
- Expected: "❌ Failed to download receipt image. Please try again."

---

## Database Verification

### Check telegram_pending_receipts table
```sql
-- Should be empty when no receipt is pending
SELECT * FROM telegram_pending_receipts;

-- Should have entry when "With Receipt" is clicked, cleared when receipt is received
```

### Check transactions table
```sql
-- After approval with receipt
SELECT id, status, receipt_image FROM transactions WHERE id = [transaction_id];
-- Expected: status='completed', receipt_image='/path/to/receipt'

-- After approval without receipt
SELECT id, status, receipt_image FROM transactions WHERE id = [transaction_id];
-- Expected: status='completed', receipt_image=NULL
```

---

## System Requirements
- ✅ PHP > 7.2
- ✅ MySQL/MariaDB
- ✅ PDO extension
- ✅ Telegram Bot API access
- ✅ Write permission to `/uploads/receipts/` directory

---

## Common Issues & Solutions

### Issue: "No pending receipt upload found" when trying to upload
**Solution:** Make sure to click "📷 With Receipt" button BEFORE sending the image

### Issue: Receipt not saving
**Solution:** 
1. Check `/uploads/receipts/` directory exists and is writable
2. Check `telegram_pending_receipts` table exists
3. Check file_get_contents() can access Telegram URLs

### Issue: Session-related errors (from old code)
**Solution:** Already fixed - now uses database instead of SESSION

