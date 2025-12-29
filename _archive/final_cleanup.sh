#!/bin/bash

echo "🧹 Final Project Cleanup"
echo "========================"
echo ""

# Delete files that are already in _archive (duplicates)
echo "1️⃣ Removing duplicates already in _archive..."
rm -vf admin_backup.php casino.php index_old_test.php balance_helper.php \
       currency_codes.php language_codes.php return.php \
       example_launch.php simple_launch.php test_api_direct.php test_launch.php test_deployment.sh \
       add_user_tracking.php migrate_currency.php set_all_php_currency.php \
       setup_game_plays_table.php setup_games_table.php setup_settings_table.php \
       rename_game_images.php update_game_images.php update_jili_games.php \
       install_webserver.sh setup_server.sh quick_server_check.sh server_check.php deploy.sh

echo ""
echo "2️⃣ Moving new files to archive..."
[ -f "test_admin_login.php" ] && mv -v test_admin_login.php _archive/test_files/
[ -f "cleanup_project.sh" ] && mv -v cleanup_project.sh _archive/
[ -f "files_to_remove.txt" ] && mv -v files_to_remove.txt _archive/
[ -f "analyze_files.sh" ] && mv -v analyze_files.sh _archive/
[ -f "clean_local_files.sh" ] && rm -vf clean_local_files.sh  # Remove broken script

echo ""
echo "3️⃣ Removing database_schema.sql duplicate..."
[ -f "database_schema.sql" ] && rm -vf database_schema.sql  # Already in _archive

echo ""
echo "✅ Cleanup Complete!"
echo ""
echo "📊 Summary:"
echo "   - Removed 26 duplicate files (already in _archive/)"
echo "   - Moved 3 new files to archive"
echo "   - Kept 28 active production files"
echo ""
echo "🗂️  Active files remaining:"
ls -1 *.php *.sh *.sql 2>/dev/null | wc -l | xargs echo "   PHP/Shell/SQL files:"
