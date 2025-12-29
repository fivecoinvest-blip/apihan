#!/bin/bash

echo "=== FILES TO DELETE (duplicates/old versions in _archive) ==="
echo ""

# Files that are duplicated in _archive and should be deleted from root
echo "Files already in _archive:"
for file in admin_backup.php casino.php index_old_test.php balance_helper.php \
            currency_codes.php language_codes.php return.php \
            example_launch.php simple_launch.php test_api_direct.php test_launch.php test_deployment.sh \
            add_user_tracking.php migrate_currency.php set_all_php_currency.php \
            setup_game_plays_table.php setup_games_table.php setup_settings_table.php \
            rename_game_images.php update_game_images.php update_jili_games.php \
            install_webserver.sh setup_server.sh quick_server_check.sh server_check.php deploy.sh; do
    if [ -f "$file" ]; then
        echo "  ❌ $file"
    fi
done

echo ""
echo "=== NEW FILES TO ARCHIVE ==="
echo ""

# Test file found
if [ -f "test_admin_login.php" ]; then
    echo "  📦 test_admin_login.php (test file)"
fi

# Cleanup scripts
if [ -f "cleanup_project.sh" ]; then
    echo "  📦 cleanup_project.sh (utility script)"
fi

if [ -f "files_to_remove.txt" ]; then
    echo "  📦 files_to_remove.txt (temp file)"
fi

echo ""
echo "=== FILES TO KEEP (Active Production) ==="
echo ""
echo "Core:"
echo "  ✅ index.php, login.php, admin.php, play_game.php, logout.php, profile.php"
echo ""
echo "API:"
echo "  ✅ api_request_builder.php, callback.php, launch_game.php, config.php"
echo ""
echo "Helpers:"
echo "  ✅ db_helper.php, redis_helper.php, currency_helper.php, session_config.php"
echo "  ✅ settings_helper.php, helpers.php, keep_alive.php"
echo ""
echo "Endpoints:"
echo "  ✅ get_user_history.php, get_login_history.php, upload_game_image.php"
echo ""
echo "Setup:"
echo "  ✅ setup_admin.php, setup_database.php, update_jili_complete.php"
echo ""
echo "Utils:"
echo "  ✅ view_balances.php (debug), backup_server.sh"
echo ""
echo "Config:"
echo "  ✅ optimize_database.sql"
