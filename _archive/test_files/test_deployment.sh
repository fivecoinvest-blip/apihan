#!/bin/bash
#
# Quick Test Script for Deployed API
# Run this after deployment to verify everything works
#

SERVER="https://31.97.107.21/apihan"

echo "🧪 Testing SoftAPI Integration on Server"
echo "========================================="
echo ""

# Test 1: Check if main page loads
echo "Test 1: Main Page..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$SERVER/")
if [ "$HTTP_CODE" == "200" ]; then
    echo "✅ Main page accessible (HTTP $HTTP_CODE)"
else
    echo "❌ Main page failed (HTTP $HTTP_CODE)"
fi

# Test 2: Check launch_game.php
echo ""
echo "Test 2: Launch Game API..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$SERVER/launch_game.php")
if [ "$HTTP_CODE" == "200" ]; then
    echo "✅ Launch API accessible (HTTP $HTTP_CODE)"
else
    echo "❌ Launch API failed (HTTP $HTTP_CODE)"
fi

# Test 3: Check callback.php
echo ""
echo "Test 3: Callback Endpoint..."
RESPONSE=$(curl -s -X POST "$SERVER/callback.php" \
    -H "Content-Type: application/json" \
    -d '{"game_uid":"test","game_round":"123","member_account":"test","bet_amount":10,"win_amount":5,"timestamp":"2025-12-28 10:00:00"}')
if [[ $RESPONSE == *"credit_amount"* ]]; then
    echo "✅ Callback endpoint working"
    echo "   Response: $RESPONSE"
else
    echo "❌ Callback endpoint failed"
    echo "   Response: $RESPONSE"
fi

# Test 4: Check language codes
echo ""
echo "Test 4: Language Codes API..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$SERVER/language_codes.php")
if [ "$HTTP_CODE" == "200" ]; then
    echo "✅ Language codes accessible (HTTP $HTTP_CODE)"
else
    echo "❌ Language codes failed (HTTP $HTTP_CODE)"
fi

# Test 5: Check currency codes
echo ""
echo "Test 5: Currency Codes API..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$SERVER/currency_codes.php")
if [ "$HTTP_CODE" == "200" ]; then
    echo "✅ Currency codes accessible (HTTP $HTTP_CODE)"
else
    echo "❌ Currency codes failed (HTTP $HTTP_CODE)"
fi

# Test 6: PHP Extensions
echo ""
echo "Test 6: Checking PHP Extensions..."
echo "   Run on server: ssh root@31.97.107.21 'php -m | grep -E \"openssl|curl|json\"'"

echo ""
echo "========================================="
echo "Testing Complete!"
echo ""
echo "🌐 Access your application:"
echo "   $SERVER/"
echo ""
echo "📋 Next steps:"
echo "   1. Open $SERVER/ in your browser"
echo "   2. Fill in the form and test game launch"
echo "   3. Check logs: ssh root@31.97.107.21 'tail -f /var/www/html/apihan/logs/api_*.log'"
echo ""
