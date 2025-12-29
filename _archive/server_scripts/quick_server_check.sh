#!/bin/bash
# Quick SSH command to check server requirements

echo "🔍 Checking server requirements on 31.97.107.21..."
echo ""

ssh root@31.97.107.21 << 'ENDSSH'
echo "=== Server Check ==="
echo ""
echo "1. PHP Version:"
php -v | head -1
echo ""

echo "2. PHP Extensions:"
php -m | grep -E "openssl|curl|json|pdo|mysql" | sort
echo ""

echo "3. Web Server:"
if systemctl status apache2 2>/dev/null | grep -q Active; then
    echo "✅ Apache2 is running"
    systemctl status apache2 | grep Active
elif systemctl status httpd 2>/dev/null | grep -q Active; then
    echo "✅ Apache (httpd) is running"
    systemctl status httpd | grep Active
elif systemctl status nginx 2>/dev/null | grep -q Active; then
    echo "✅ Nginx is running"
    systemctl status nginx | grep Active
else
    echo "❌ No web server detected"
    echo "   Install: apt-get install apache2  OR  apt-get install nginx"
fi
echo ""

echo "4. HTTPS/SSL:"
ls -la /etc/letsencrypt/live/ 2>/dev/null || echo "No Let's Encrypt certificates found"
echo ""

echo "5. Directory Permissions:"
ls -ld /var/www/html/apihan 2>/dev/null || echo "/var/www/html/apihan not found"
echo ""

echo "6. PHP Configuration:"
php -i | grep -E "memory_limit|max_execution_time" | head -2
echo ""

echo "=== Recommendations ==="
echo ""
echo "If missing extensions, run:"
echo "  apt-get install php-openssl php-curl php-json php-pdo php-mysql  # Ubuntu"
echo "  yum install php-openssl php-curl php-json php-pdo php-mysqlnd   # CentOS"
echo ""
echo "Then restart web server:"
echo "  systemctl restart apache2  # or nginx"
ENDSSH

echo ""
echo "✅ Check complete!"
echo ""
echo "�� Next step: Upload server_check.php to server and access:"
echo "   https://31.97.107.21/apihan/server_check.php"
