#!/bin/bash

echo "🧹 Cleaning up project files..."

# Create archive directory
mkdir -p _archive/{old_files,setup_scripts,test_files,server_scripts}

# Move old/unused files
echo "📦 Archiving old files..."
mv -v admin_backup.php _archive/old_files/ 2>/dev/null
mv -v casino.php _archive/old_files/ 2>/dev/null
mv -v index_old_test.php _archive/old_files/ 2>/dev/null
mv -v return.php _archive/old_files/ 2>/dev/null
mv -v balance_helper.php _archive/old_files/ 2>/dev/null
mv -v currency_codes.php _archive/old_files/ 2>/dev/null
mv -v language_codes.php _archive/old_files/ 2>/dev/null

# Move test files
echo "🧪 Archiving test files..."
mv -v example_launch.php _archive/test_files/ 2>/dev/null
mv -v simple_launch.php _archive/test_files/ 2>/dev/null
mv -v test_api_direct.php _archive/test_files/ 2>/dev/null
mv -v test_launch.php _archive/test_files/ 2>/dev/null
mv -v test_deployment.sh _archive/test_files/ 2>/dev/null
mv -v test_admin_login.php _archive/test_files/ 2>/dev/null

# Move one-time setup scripts
echo "⚙️  Archiving setup scripts..."
mv -v add_user_tracking.php _archive/setup_scripts/ 2>/dev/null
mv -v migrate_currency.php _archive/setup_scripts/ 2>/dev/null
mv -v set_all_php_currency.php _archive/setup_scripts/ 2>/dev/null
mv -v setup_game_plays_table.php _archive/setup_scripts/ 2>/dev/null
mv -v setup_games_table.php _archive/setup_scripts/ 2>/dev/null
mv -v setup_settings_table.php _archive/setup_scripts/ 2>/dev/null
mv -v rename_game_images.php _archive/setup_scripts/ 2>/dev/null
mv -v update_game_images.php _archive/setup_scripts/ 2>/dev/null
mv -v update_jili_games.php _archive/setup_scripts/ 2>/dev/null
mv -v database_schema.sql _archive/setup_scripts/ 2>/dev/null

# Move server setup scripts
echo "🖥️  Archiving server scripts..."
mv -v install_webserver.sh _archive/server_scripts/ 2>/dev/null
mv -v setup_server.sh _archive/server_scripts/ 2>/dev/null
mv -v quick_server_check.sh _archive/server_scripts/ 2>/dev/null
mv -v server_check.php _archive/server_scripts/ 2>/dev/null
mv -v deploy.sh _archive/server_scripts/ 2>/dev/null

# Remove DEPLOYMENT.md if content is in CASINO_SETUP_GUIDE.md
if [ -f "DEPLOYMENT.md" ]; then
    echo "📄 Archiving DEPLOYMENT.md (merged into CASINO_SETUP_GUIDE.md)..."
    mv -v DEPLOYMENT.md _archive/old_files/ 2>/dev/null
fi

# Create README in archive
cat > _archive/README.md << 'EOF'
# Archived Files

This directory contains files that are no longer actively used but kept for reference.

## Directory Structure

- **old_files/** - Deprecated or replaced files
- **test_files/** - Development test scripts
- **setup_scripts/** - One-time setup scripts (already executed)
- **server_scripts/** - Server configuration scripts

## Important Notes

- These files are not deployed to production
- Keep for historical reference or potential future use
- Can be safely deleted if disk space is needed

## Active Files Location

All active production files remain in the project root:
- Core files: index.php, admin.php, login.php, etc.
- Active helpers: db_helper.php, redis_helper.php, currency_helper.php
- Active setup: setup_admin.php, setup_database.php, update_jili_complete.php
- Documentation: CASINO_SETUP_GUIDE.md, API_DOCUMENTATION.md, README.md
EOF

echo ""
echo "✅ Cleanup complete!"
echo ""
echo "📊 Summary:"
find _archive -type f | wc -l | xargs echo "   Archived files:"
echo ""
echo "🗂️  Active production files remain in project root"
echo "📁 Archived files are in _archive/ directory"
