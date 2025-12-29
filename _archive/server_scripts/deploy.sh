#!/bin/bash
#
# Deploy SoftAPI Integration to Production Server
# Server: 31.97.107.21
#

echo "🚀 SoftAPI Deployment Script"
echo "================================"
echo ""

# Configuration
SERVER_IP="31.97.107.21"
SERVER_USER="root"  # Change this to your SSH user
REMOTE_PATH="/var/www/html/apihan"  # Change to your web root path
LOCAL_PATH="/home/neng/Desktop/apihan"

echo "📋 Deployment Configuration:"
echo "   Server: $SERVER_IP"
echo "   User: $SERVER_USER"
echo "   Remote Path: $REMOTE_PATH"
echo ""

# Check if we can reach the server
echo "🔍 Checking server connectivity..."
if ping -c 1 $SERVER_IP &> /dev/null; then
    echo "✅ Server is reachable"
else
    echo "❌ Cannot reach server. Please check your connection."
    exit 1
fi

echo ""
echo "📦 Preparing files for deployment..."

# Create a temporary directory for deployment
TEMP_DIR="/tmp/apihan_deploy_$(date +%s)"
mkdir -p $TEMP_DIR

# Copy files to temp directory
cp -r $LOCAL_PATH/* $TEMP_DIR/

# Remove sensitive local files
rm -f $TEMP_DIR/.env 2>/dev/null
rm -rf $TEMP_DIR/logs/* 2>/dev/null

echo "✅ Files prepared in $TEMP_DIR"
echo ""

# Ask for confirmation
read -p "🚦 Ready to deploy to $SERVER_IP? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Deployment cancelled"
    rm -rf $TEMP_DIR
    exit 0
fi

echo ""
echo "🔐 Starting deployment (you may be asked for SSH password)..."
echo ""

# Create remote directory
ssh $SERVER_USER@$SERVER_IP "mkdir -p $REMOTE_PATH"

# Upload files using rsync (if available) or scp
if command -v rsync &> /dev/null; then
    echo "📤 Uploading files with rsync..."
    rsync -avz --progress \
        --exclude 'logs/*' \
        --exclude '.git' \
        --exclude '.env' \
        $TEMP_DIR/ $SERVER_USER@$SERVER_IP:$REMOTE_PATH/
else
    echo "📤 Uploading files with scp..."
    scp -r $TEMP_DIR/* $SERVER_USER@$SERVER_IP:$REMOTE_PATH/
fi

if [ $? -eq 0 ]; then
    echo "✅ Files uploaded successfully"
else
    echo "❌ Upload failed"
    rm -rf $TEMP_DIR
    exit 1
fi

echo ""
echo "🔧 Setting up permissions on server..."

# Set permissions
ssh $SERVER_USER@$SERVER_IP "cd $REMOTE_PATH && \
    mkdir -p logs && \
    chmod 755 *.php && \
    chmod 777 logs && \
    chown -R www-data:www-data . 2>/dev/null || chown -R apache:apache . 2>/dev/null || true"

echo "✅ Permissions set"

# Cleanup temp directory
rm -rf $TEMP_DIR

echo ""
echo "=========================================="
echo "✅ Deployment Complete!"
echo "=========================================="
echo ""
echo "🌐 Your application is now available at:"
echo "   https://$SERVER_IP/apihan/"
echo ""
echo "📋 Next Steps:"
echo "   1. Test the launch API: https://$SERVER_IP/apihan/index.php"
echo "   2. Verify callback endpoint: https://$SERVER_IP/apihan/callback.php"
echo "   3. Check logs at: $REMOTE_PATH/logs/"
echo ""
echo "🔑 Make sure your server has:"
echo "   ✓ PHP 7.4+ with OpenSSL and cURL extensions"
echo "   ✓ Web server (Apache/Nginx) configured"
echo "   ✓ HTTPS/SSL certificate (recommended)"
echo ""
