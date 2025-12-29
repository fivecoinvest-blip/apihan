# SoftAPI Deployment Guide
# Deploy to Server: 31.97.107.21

## Quick Deployment (Recommended)

### Method 1: Using Deployment Script
```bash
# Make script executable
chmod +x deploy.sh

# Run deployment
./deploy.sh
```

### Method 2: Manual Upload via SCP
```bash
# Upload all files to server
scp -r /home/neng/Desktop/apihan/* root@31.97.107.21:/var/www/html/apihan/

# SSH into server and set permissions
ssh root@31.97.107.21
cd /var/www/html/apihan
mkdir -p logs
chmod 755 *.php
chmod 777 logs
```

### Method 3: Using SFTP
```bash
sftp root@31.97.107.21
put -r /home/neng/Desktop/apihan /var/www/html/
```

### Method 4: Using rsync (Best for updates)
```bash
rsync -avz --progress /home/neng/Desktop/apihan/ root@31.97.107.21:/var/www/html/apihan/
```

## After Upload - Server Setup

### 1. SSH into your server
```bash
ssh root@31.97.107.21
```

### 2. Navigate to project directory
```bash
cd /var/www/html/apihan
```

### 3. Check PHP version and extensions
```bash
php -v
php -m | grep -E 'openssl|curl|json'
```

### 4. Create logs directory and set permissions
```bash
mkdir -p logs
chmod 755 *.php
chmod 777 logs
chown -R www-data:www-data .  # For Apache
# OR
chown -R nginx:nginx .         # For Nginx
```

### 5. Test PHP files
```bash
php -l index.php
php -l callback.php
php -l launch_game.php
```

## Web Server Configuration

### For Apache (.htaccess)
Already included in project. Make sure mod_rewrite is enabled:
```bash
a2enmod rewrite
systemctl restart apache2
```

### For Nginx
Add this to your server block:
```nginx
server {
    listen 443 ssl;
    server_name 31.97.107.21;
    
    root /var/www/html/apihan;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.ht {
        deny all;
    }
}
```

## Testing After Deployment

### 1. Test in Browser
```
https://31.97.107.21/apihan/
```

### 2. Test API Endpoint
```bash
curl https://31.97.107.21/apihan/index.php
```

### 3. Test Simple Launch
```bash
curl "https://31.97.107.21/apihan/launch_game.php?user_id=23213&balance=40"
```

### 4. Check Logs
```bash
ssh root@31.97.107.21
tail -f /var/www/html/apihan/logs/api_$(date +%Y-%m-%d).log
```

## Troubleshooting

### If you see "Permission Denied":
```bash
chmod -R 755 /var/www/html/apihan
chmod 777 /var/www/html/apihan/logs
```

### If you see "OpenSSL not found":
```bash
# Ubuntu/Debian
apt-get install php-openssl php-curl

# CentOS/RHEL
yum install php-openssl php-curl

# Restart web server
systemctl restart apache2  # or nginx
```

### If callback.php returns 404:
```bash
# Check if file exists
ls -la /var/www/html/apihan/callback.php

# Check web server error log
tail -f /var/log/apache2/error.log  # Apache
tail -f /var/log/nginx/error.log    # Nginx
```

### If you see blank page:
```bash
# Enable PHP error display
echo "display_errors = On" >> /etc/php/8.1/apache2/php.ini
systemctl restart apache2

# Check PHP error log
tail -f /var/log/php/error.log
```

## Security Checklist

- [ ] SSL certificate installed (HTTPS)
- [ ] Firewall configured (allow ports 80, 443)
- [ ] File permissions set correctly (755 for PHP, 777 for logs)
- [ ] Database credentials secured
- [ ] API secrets not exposed in logs
- [ ] Callback endpoint accessible from SoftAPI servers

## URLs After Deployment

- **Main Interface**: https://31.97.107.21/apihan/
- **Launch API**: https://31.97.107.21/apihan/launch_game.php
- **Callback**: https://31.97.107.21/apihan/callback.php
- **Language Codes**: https://31.97.107.21/apihan/language_codes.php
- **Currency Codes**: https://31.97.107.21/apihan/currency_codes.php

## Contact

After deployment, test with:
```bash
curl -X GET "https://31.97.107.21/apihan/index.php"
```

If you see the web interface, deployment was successful! 🎉
