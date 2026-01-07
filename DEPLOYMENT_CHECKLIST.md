# Deployment Checklist

## Pre-Deployment
- [x] Code syntax validated (PHP -l passed)
- [x] All database queries use prepared statements (secure)
- [x] Error handling implemented
- [x] Transaction processing protected (PDO transactions)
- [x] Database table auto-creation handled

## Deployment Steps
1. **Backup current version**
   ```bash
   cp telegram_bot.php telegram_bot.php.backup
   ```

2. **Deploy fixed version**
   - Copy the fixed `telegram_bot.php` to production

3. **Verify permissions**
   - `/uploads/receipts/` directory must be writable
   - Run: `mkdir -p uploads/receipts && chmod 755 uploads/receipts`

4. **Database table creation**
   - The table `telegram_pending_receipts` will be auto-created on first use
   - Or manually create:
   ```sql
   CREATE TABLE IF NOT EXISTS telegram_pending_receipts (
       transaction_id INT PRIMARY KEY,
       chat_id BIGINT NOT NULL,
       message_id INT NOT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );
   ```

## Post-Deployment Verification
1. **Monitor logs** for any errors
2. **Test Scenario 1:** Approval with receipt
   - Send withdrawal
   - Click Approve → With Receipt
   - Send image
   - Verify: Transaction marked complete with receipt

3. **Test Scenario 2:** Approval without receipt
   - Send withdrawal
   - Click Approve → Without Receipt
   - Verify: Transaction marked complete without receipt

4. **Database check**
   ```sql
   -- Check transactions table
   SELECT id, status, receipt_image FROM transactions 
   WHERE status = 'completed' 
   LIMIT 5;
   
   -- Check pending receipts (should be empty)
   SELECT * FROM telegram_pending_receipts;
   ```

## Rollback Plan
If issues occur:
```bash
cp telegram_bot.php.backup telegram_bot.php
```

## Support
If receipt uploads still don't work, check:
1. Telegram webhook is receiving messages (check logs)
2. `uploads/receipts/` directory is writable
3. Database connection is working
4. File download from Telegram works
5. Check error messages in Telegram bot chat

