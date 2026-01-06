#!/bin/bash

echo "=========================================="
echo "SSL Setup for paldo88.site"
echo "=========================================="
echo ""

# Check if DNS has propagated
echo "Checking DNS propagation..."
DNS_CHECK=$(nslookup paldo88.site | grep "Address" | tail -1 | awk '{print $2}')

if [ "$DNS_CHECK" != "31.97.107.21" ]; then
    echo "❌ DNS not propagated yet!"
    echo "Expected: 31.97.107.21"
    echo "Got: $DNS_CHECK"
    echo ""
    echo "Please wait and try again in 10-30 minutes."
    exit 1
fi

echo "✅ DNS propagated successfully!"
echo ""

# Install SSL certificate
echo "Installing SSL certificate..."
ssh root@31.97.107.21 "certbot --apache -d paldo88.site -d www.paldo88.site --non-interactive --agree-tos --email admin@paldo88.site --redirect"

if [ $? -eq 0 ]; then
    echo ""
    echo "=========================================="
    echo "✅ SSL Setup Complete!"
    echo "=========================================="
    echo ""
    echo "Your site is now accessible at:"
    echo "  • https://paldo88.site"
    echo "  • https://www.paldo88.site (redirects to non-www)"
    echo ""
    echo "Features enabled:"
    echo "  ✓ SSL/TLS Certificate (Let's Encrypt)"
    echo "  ✓ Auto HTTP → HTTPS redirect"
    echo "  ✓ www → non-www redirect"
    echo "  ✓ Auto-renewal (every 90 days)"
    echo ""
else
    echo ""
    echo "❌ SSL setup failed!"
    echo "Please check the error messages above."
    exit 1
fi
