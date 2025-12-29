#!/bin/bash
# One-command setup for server 31.97.107.21

echo "🚀 One-Command Server Setup"
echo "============================"
echo ""
echo "This will install everything needed on 31.97.107.21"
echo ""
read -p "Continue? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    exit 0
fi

# Transfer and execute setup script
ssh root@31.97.107.21 'bash -s' << 'ENDSSH'
#!/bin/bash

echo "📦 Installing Web Server & PHP..."

# Detect OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
else
    echo "❌ Cannot detect OS"
    exit 1
fi

# Install based on OS
if [ "$OS" = "ubuntu" ] || [ "$OS" = "debian" ]; then
    echo "Installing on Ubuntu/Debian..."
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y -qq apache2 php libapache2-mod-php php-cli php-openssl php-curl php-json php-mysql php-mbstring > /dev/null 2>&1
    
    # Enable modules
    a2enmod rewrite ssl headers > /dev/null 2>&1
    
    # Start Apache
    systemctl start apache2
    systemctl enable apache2
    
    WEB_SERVER="apache2"
    
elif [ "$OS" = "centos" ] || [ "$OS" = "rhel" ] || [ "$OS" = "fedora" ]; then
    echo "Installing on CentOS/RHEL..."
    yum install -y httpd php php-cli php-openssl php-curl php-json php-mysqlnd php-mbstring > /dev/null 2>&1
    
    # Start Apache
    systemctl start httpd
    systemctl enable httpd
    
    # Firewall
    firewall-cmd --permanent --add-service=http > /dev/null 2>&1
    firewall-cmd --permanent --add-service=https > /dev/null 2>&1
    firewall-cmd --reload > /dev/null 2>&1
    
    WEB_SERVER="httpd"
fi

echo "✅ Web server installed"

# Create directory
echo "📁 Creating directories..."
mkdir -p /var/www/html/apihan/logs
chown -R www-data:www-data /var/www/html 2>/dev/null || chown -R apache:apache /var/www/html 2>/dev/null

echo "✅ Directories created"

# Verify PHP
echo "🔍 Verifying installation..."
php -v | head -1
echo ""

# Check extensions
echo "Extensions:"
php -m | grep -E "openssl|curl|json" | sort

echo ""
echo "✅ Server setup complete!"
echo ""
echo "Web Server: $WEB_SERVER"
echo "Document Root: /var/www/html"
echo "Upload files to: /var/www/html/apihan/"

ENDSSH

if [ $? -eq 0 ]; then
    echo ""
    echo "================================================"
    echo "✅ Server is ready!"
    echo "================================================"
    echo ""
    echo "Next step: Upload your files"
    echo ""
    echo "Run from local machine:"
    echo "  ./deploy.sh"
    echo ""
    echo "Or manually:"
    echo "  scp -r /home/neng/Desktop/apihan/* root@31.97.107.21:/var/www/html/apihan/"
    echo ""
else
    echo "❌ Setup failed. Check the errors above."
fi
