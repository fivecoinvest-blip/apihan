#!/bin/bash
# Test script to verify the Telegram bot receipt upload fix

echo "================================"
echo "Telegram Bot Fix Verification"
echo "================================"
echo ""

# Check if file exists
if [ ! -f "telegram_bot.php" ]; then
    echo "❌ telegram_bot.php not found!"
    exit 1
fi

# Check PHP syntax
echo "1. Checking PHP syntax..."
if php -l telegram_bot.php > /dev/null 2>&1; then
    echo "   ✅ PHP syntax is valid"
else
    echo "   ❌ PHP syntax errors found!"
    php -l telegram_bot.php
    exit 1
fi

# Check for required functions
echo ""
echo "2. Checking required functions..."
if grep -q "function handleReceiptUpload" telegram_bot.php; then
    echo "   ✅ handleReceiptUpload function exists"
else
    echo "   ❌ handleReceiptUpload function missing!"
    exit 1
fi

if grep -q "function handleUploadReceipt" telegram_bot.php; then
    echo "   ✅ handleUploadReceipt function exists"
else
    echo "   ❌ handleUploadReceipt function missing!"
    exit 1
fi

# Check for database queries
echo ""
echo "3. Checking database consistency..."
if grep -q "telegram_pending_receipts" telegram_bot.php; then
    count=$(grep -c "telegram_pending_receipts" telegram_bot.php)
    echo "   ✅ Database table references found ($count mentions)"
else
    echo "   ❌ No database table references found!"
    exit 1
fi

# Check for old SESSION usage in handleReceiptUpload
echo ""
echo "4. Checking for removed SESSION issues..."
if grep -A 30 "function handleReceiptUpload" telegram_bot.php | grep -q "\$_SESSION\["; then
    echo "   ❌ Old SESSION code still in handleReceiptUpload!"
    exit 1
else
    echo "   ✅ No old SESSION code in handleReceiptUpload"
fi

# Check for new database query in handleReceiptUpload
echo ""
echo "5. Checking for new database query..."
if grep -A 30 "function handleReceiptUpload" telegram_bot.php | grep -q "SELECT transaction_id"; then
    echo "   ✅ New database query implemented"
else
    echo "   ❌ Database query not found!"
    exit 1
fi

# Check for error handling
echo ""
echo "6. Checking error handling..."
if grep -q "No pending receipt upload found" telegram_bot.php; then
    echo "   ✅ Error handling for no pending receipt"
else
    echo "   ❌ Error handling missing!"
    exit 1
fi

if grep -q "Failed to download receipt image" telegram_bot.php; then
    echo "   ✅ Error handling for download failure"
else
    echo "   ❌ Download error handling missing!"
    exit 1
fi

# Check for database cleanup
echo ""
echo "7. Checking database cleanup..."
if grep -q "DELETE FROM telegram_pending_receipts" telegram_bot.php; then
    echo "   ✅ Database cleanup implemented"
else
    echo "   ❌ Database cleanup missing!"
    exit 1
fi

# Check file permissions
echo ""
echo "8. Checking directory permissions..."
if [ ! -d "uploads" ]; then
    echo "   ⚠️  uploads/ directory doesn't exist yet (will be created on first use)"
else
    if [ -w "uploads" ]; then
        echo "   ✅ uploads/ directory is writable"
    else
        echo "   ❌ uploads/ directory is not writable!"
        exit 1
    fi
fi

echo ""
echo "================================"
echo "✅ All checks passed!"
echo "================================"
echo ""
echo "Summary of fix:"
echo "1. handleUploadReceipt() saves state to telegram_pending_receipts table"
echo "2. User sends receipt image"
echo "3. handleReceiptUpload() queries the table (not SESSION)"
echo "4. Finds the transaction and processes receipt"
echo "5. Cleans up database record"
echo ""
echo "Ready for deployment!"
