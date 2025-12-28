#!/bin/bash
# Backup server to GitHub
# Run this script to sync server changes to GitHub

echo "🔄 Syncing server to local..."
rsync -avz --progress --exclude='.git' root@31.97.107.21:/var/www/html/apihan/ /home/neng/Desktop/apihan/

echo ""
echo "📦 Committing changes..."
cd /home/neng/Desktop/apihan
git add .
git commit -m "Backup: $(date '+%Y-%m-%d %H:%M:%S')"

echo ""
echo "⬆️  Pushing to GitHub..."
git push origin main

echo ""
echo "✅ Backup complete! Repository: https://github.com/fivecoinvest-blip/apihan"
