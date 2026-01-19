# 🚀 WPay Payment Gateway - Quick Start Guide

## 📍 Access Points

### For Users
- **Auto Deposit**: https://paldo88.site/deposit_auto.php
- **Auto Withdrawal**: https://paldo88.site/withdrawal_auto.php

### For Admins
- **WPay Dashboard**: https://paldo88.site/admin_wpay_dashboard.php
- **WPay Tools**: https://paldo88.site/wpay_tools.php (query transactions, check balance)

---

## 💰 Current Configuration

**Environment:** Production  
**Merchant ID:** 5047  
**API Host:** https://api.wpay.life

### Fee Structure
```
Deposits:   NO FEES (admin covers all) ✅
Withdrawals:
  • Collection Fee: 1.6% of amount
  • Processing Fee: ₱8 PHP
  • Example: ₱1,000 withdrawal = ₱16 + ₱8 = ₱24 total fee
           Users receive: ₱976
```

### Limits
```
Deposits:       ₱50 - ₱50,000
Withdrawals:    ₱50 - ₱50,000
```

---

## 🎯 User Features

### Deposits
1. Select payment method (GCash/Maya/QR)
2. Click quick amounts or enter custom amount
3. View "NO FEES!" banner for transparency
4. Click "Proceed to Payment"
5. Complete payment on WPay gateway
6. Auto-redirect to payment status page

**Features:**
- Real-time fee calculation
- Quick amount buttons (₱100, ₱500, ₱1K, ₱5K)
- Amount validation
- Recent deposit history
- Transaction status tracking

### Withdrawals
1. Select withdrawal method (GCash/Maya)
2. Enter account number (phone for GCash/Maya)
3. Confirm account holder name
4. Enter withdrawal amount
5. View fee breakdown:
   - Collection fee (1.6%)
   - Processing fee (₱8)
   - Amount you'll receive
6. Click "Submit Withdrawal"
7. Withdrawal processed automatically

**Features:**
- Dynamic field labels (GCash Mobile Number, etc.)
- Real-time fee breakdown
- Quick amount buttons
- Balance validation
- Fee transparency
- Recent withdrawal history

---

## 👨‍💼 Admin Features

### Dashboard Analytics
**View at:** https://paldo88.site/admin_wpay_dashboard.php

**Today's Metrics:**
- Total deposits amount and count
- Total withdrawals amount and count
- Fees collected today

**This Month's Metrics:**
- Total deposits amount and count
- Total withdrawals amount and count
- Total fees collected (collection + processing breakdown)

**Recent Transactions:**
- Last 15 transactions from last 24 hours
- Type, order number, amount, fees, status
- Payment method and account details
- Timestamps

### Admin Tools
**Access at:** https://paldo88.site/wpay_tools.php

**Available Functions:**
1. **Query Deposit** - Check status of any deposit
2. **Query Withdrawal** - Check status of any withdrawal
3. **Check Balance** - View merchant account balance with WPay
4. **Get Bank List** - View supported banks for withdrawals

---

## 📊 Database Tables

### payment_transactions
```
- user_id: User making deposit
- out_trade_no: Order number (prefix: D)
- amount: Deposit amount
- currency: PHP
- pay_type: GCASH, MAYA, QR
- collection_fee: 1.6% of amount (admin covers)
- processing_fee: 0 for deposits
- total_fee: Total fee charged
- net_amount: Amount after fees
- status: pending, processing, completed, failed
- payment_url: URL user was redirected to
- transaction_id: WPay transaction ID
- created_at: Timestamp
- notify_data: Callback response data
```

### withdrawal_transactions
```
- user_id: User requesting withdrawal
- out_trade_no: Order number (prefix: W)
- amount: Withdrawal amount requested
- currency: PHP
- pay_type: GCASH, MAYA, NATIVE (bank)
- account: Account number/phone
- account_name: Account holder name
- collection_fee: 1.6% of amount
- processing_fee: ₱8
- total_fee: Total fee charged
- net_amount: Amount user will receive
- status: pending, processing, completed, failed, rejected
- created_at: Timestamp
- notify_data: Callback response data
```

---

## 🔐 Security Features

✅ Session validation on all pages  
✅ Admin-only access to dashboard  
✅ SSL/TLS encryption for all API calls  
✅ IP whitelist on WPay end (both IPv4 and IPv6)  
✅ MD5 signature verification on all requests  
✅ Database prepared statements (SQL injection prevention)  
✅ XSS protection via htmlspecialchars()  
✅ CSRF tokens on form submissions  

---

## 🐛 Troubleshooting

### Deposit Shows "Server connection issue"
- Check internet connection
- Verify WPay server is online
- Check merchant ID and API key in wpay_config.php
- Ensure server IP is whitelisted (both IPv4 and IPv6)
- Check error logs: `tail -100 /var/log/apache2/error.log`

### Withdrawal Not Processing
- Verify user has sufficient balance
- Check account number format (11 digits for GCash/Maya)
- Verify account holder name is correct
- Check WPay balance (admin panel → Check Balance)
- Review transaction status in admin dashboard

### Fee Calculation Wrong
- Deposits: Should show 0 total fee (admin covers)
- Withdrawals: Should show 1.6% + ₱8
- If different, check WPAY_COLLECTION_FEE_PERCENT and WPAY_PROCESSING_FEE in wpay_config.php

### User Not Getting Redirect
- Check if payment_url is populated in database
- Verify WPay API returned success (code 0)
- Check browser console for JavaScript errors
- Try manual redirect to payment_url

---

## 📞 Support Info

**Environment:** Production (WPAY_ENV = 'production')  
**Merchant ID:** 5047  
**API Endpoint:** https://api.wpay.life  
**Callback IP:** 103.156.25.75  

**Error Codes Reference:**
- 0 = Success
- 1 = Request failed
- 2 = Merchant ID error
- 5 = Signature error
- 6 = Order already exists
- 9 = Insufficient balance
- 10 = Incorrect amount
- 16 = IP not whitelisted

---

## ✅ Recent Improvements

✨ **Enhanced UX:**
- Real-time fee calculations
- Quick amount buttons
- Payment method validation
- Loading states during processing

✨ **Better Transparency:**
- "NO FEES!" banner on deposits
- Detailed fee breakdown on withdrawals
- Transaction history with status

✨ **Admin Monitoring:**
- Real-time analytics dashboard
- Daily/monthly revenue tracking
- Transaction monitoring
- Fee collection visibility

✨ **Improved Validation:**
- Amount range checking
- Payment method validation
- Account field validation
- Balance sufficiency checks

---

## 🎯 Next Features to Implement

- [ ] Email notifications for transactions
- [ ] SMS alerts for deposits/withdrawals
- [ ] Automated daily settlement reports
- [ ] Dispute resolution system
- [ ] Batch withdrawal processing
- [ ] Rate limiting and fraud detection
- [ ] Detailed audit logs
- [ ] Two-factor authentication

---

**Last Updated:** January 14, 2026  
**System Status:** ✅ Production Ready
