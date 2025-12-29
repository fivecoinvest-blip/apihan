#!/bin/bash

echo "==================================="
echo "Testing Casino Callback & Balance System"
echo "==================================="
echo ""

# Test 1: Check callback.php accessibility
echo "1️⃣ Testing callback.php accessibility..."
CALLBACK_RESPONSE=$(ssh root@31.97.107.21 "curl -s -w '\nHTTP_CODE:%{http_code}' http://localhost/callback.php -X POST -H 'Content-Type: application/json' -d '{\"game_uid\":\"test\",\"game_round\":\"test123\",\"member_account\":\"1\",\"bet_amount\":10,\"win_amount\":20}'")

if echo "$CALLBACK_RESPONSE" | grep -q "HTTP_CODE:200"; then
    echo "✅ Callback endpoint is accessible (HTTP 200)"
else
    echo "❌ Callback endpoint failed"
    echo "$CALLBACK_RESPONSE"
fi
echo ""

# Test 2: Check if Redis is running
echo "2️⃣ Testing Redis connection..."
REDIS_PING=$(ssh root@31.97.107.21 "redis-cli ping 2>&1")
if [ "$REDIS_PING" = "PONG" ]; then
    echo "✅ Redis is running"
else
    echo "❌ Redis is not responding: $REDIS_PING"
fi
echo ""

# Test 3: Check callback logs
echo "3️⃣ Checking recent callback logs..."
ssh root@31.97.107.21 "tail -5 /var/www/html/logs/api_callback.log 2>/dev/null || echo 'No callback logs found'"
echo ""

# Test 4: Test get_balance.php endpoint
echo "4️⃣ Testing get_balance.php endpoint..."
ssh root@31.97.107.21 "curl -s http://localhost/get_balance.php" | head -3
echo ""

# Test 5: Check Apache error log for recent issues
echo "5️⃣ Checking Apache error log..."
ssh root@31.97.107.21 "tail -10 /var/log/apache2/error.log | grep -v 'Redis cache initialized' | tail -5"
echo ""

# Test 6: Verify database connection
echo "6️⃣ Testing database connection..."
DB_TEST=$(ssh root@31.97.107.21 "mysql -ucasino_user -pcasino123 casino_db -e 'SELECT COUNT(*) as user_count FROM users;' 2>&1")
if echo "$DB_TEST" | grep -q "user_count"; then
    echo "✅ Database connection successful"
    echo "$DB_TEST"
else
    echo "❌ Database connection failed"
fi
echo ""

echo "==================================="
echo "Test Complete!"
echo "==================================="
