#!/bin/bash
# Web Server Installation Script for 31.97.107.21

echo "🌐 Web Server Setup for SoftAPI Integration"
echo "============================================"
echo ""

# Detect OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VER=$VERSION_ID
else
    echo "❌ Cannot detect OS"
    exit 1
fi

echo "Detected OS: $OS $VER"
echo ""

# Check if any web server is running
echo "🔍 Checking for existing web servers..."
if systemctl is-active --quiet apache2; then
    echo "✅ Apache2 is already installed and running"
    WEB_SERVER="apache2"
elif systemctl is-active --quiet httpd; then
    echo "✅ Apache (httpd) is already installed and running"
    WEB_SERVER="httpd"
elif systemctl is-active --quiet nginx; then
    echo "✅ Nginx is already installed and running"
    WEB_SERVER="nginx"
else
    echo "❌ No web server detected"
    WEB_SERVER="none"
fi

echo ""
echo "Choose installation option:"
echo "1) Install Apache (Recommended for beginners)"
echo "2) Install Nginx (Better performance)"
echo "3) Skip (if web server already installed)"
echo ""
read -p "Enter choice (1-3): " choice

case $choice in
    1)
        echo ""
        echo "📦 Installing Apache..."
        if [ "$OS" = "ubuntu" ] || [ "$OS" = "debian" ]; then
            apt-get update
            apt-get install -y apache2 php libapache2-mod-php php-cli php-openssl php-curl php-json php-mysql php-mbstring
            
            # Enable required modules
            a2enmod rewrite
            a2enmod ssl
            a2enmod headers
            
            # Configure Apache
            cat > /etc/apache2/sites-available/apihan.conf << 'EOF'
<VirtualHost *:80>
    ServerName 31.97.107.21
    DocumentRoot /var/www/html
    
    <Directory /var/www/html/apihan>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/apihan_error.log
    CustomLog ${APACHE_LOG_DIR}/apihan_access.log combined
</VirtualHost>
EOF
            
            a2ensite apihan.conf
            systemctl restart apache2
            systemctl enable apache2
            
            echo "✅ Apache installed and configured"
            
        elif [ "$OS" = "centos" ] || [ "$OS" = "rhel" ]; then
            yum install -y httpd php php-cli php-openssl php-curl php-json php-mysqlnd php-mbstring
            
            systemctl start httpd
            systemctl enable httpd
            
            # Configure firewall
            firewall-cmd --permanent --add-service=http
            firewall-cmd --permanent --add-service=https
            firewall-cmd --reload
            
            echo "✅ Apache (httpd) installed and configured"
        fi
        ;;
        
    2)
        echo ""
        echo "📦 Installing Nginx..."
        if [ "$OS" = "ubuntu" ] || [ "$OS" = "debian" ]; then
            apt-get update
            apt-get install -y nginx php-fpm php-cli php-openssl php-curl php-json php-mysql php-mbstring
            
            # Configure Nginx
            cat > /etc/nginx/sites-available/apihan << 'EOF'
server {
    listen 80;
    server_name 31.97.107.21;
    root /var/www/html;
    index index.php index.html;
    
    location /apihan {
        try_files $uri $uri/ /apihan/index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
    }
    
    location ~ /\.ht {
        deny all;
    }
}
EOF
            
            ln -sf /etc/nginx/sites-available/apihan /etc/nginx/sites-enabled/
            rm -f /etc/nginx/sites-enabled/default
            
            systemctl restart nginx
            systemctl restart php*-fpm
            systemctl enable nginx
            
            echo "✅ Nginx installed and configured"
            
        elif [ "$OS" = "centos" ] || [ "$OS" = "rhel" ]; then
            yum install -y nginx php-fpm php-cli php-openssl php-curl php-json php-mysqlnd php-mbstring
            
            systemctl start nginx
            systemctl start php-fpm
            systemctl enable nginx
            systemctl enable php-fpm
            
            # Configure firewall
            firewall-cmd --permanent --add-service=http
            firewall-cmd --permanent --add-service=https
            firewall-cmd --reload
            
            echo "✅ Nginx installed and configured"
        fi
        ;;
        
    3)
        echo "⏭️  Skipping web server installation"
        ;;
        
    *)
        echo "❌ Invalid choice"
        exit 1
        ;;
esac

echo ""
echo "🔧 Installing additional PHP extensions..."
if [ "$OS" = "ubuntu" ] || [ "$OS" = "debian" ]; then
    apt-get install -y php-openssl php-curl php-json php-mysql php-mbstring php-zip
elif [ "$OS" = "centos" ] || [ "$OS" = "rhel" ]; then
    yum install -y php-openssl php-curl php-json php-mysqlnd php-mbstring php-zip
fi

echo ""
echo "📁 Creating web directory..."
mkdir -p /var/www/html/apihan
chown -R www-data:www-data /var/www/html 2>/dev/null || chown -R apache:apache /var/www/html 2>/dev/null || chown -R nginx:nginx /var/www/html

echo ""
echo "✅ Web server setup complete!"
echo ""
echo "📋 Next steps:"
echo "   1. Upload your files to /var/www/html/apihan/"
echo "   2. Visit http://31.97.107.21/apihan/server_check.php"
echo ""
echo "🔐 Optional: Install SSL certificate"
echo "   apt-get install certbot python3-certbot-apache  # For Apache"
echo "   apt-get install certbot python3-certbot-nginx   # For Nginx"
echo "   certbot --apache -d 31.97.107.21                # Run certbot"
echo ""
