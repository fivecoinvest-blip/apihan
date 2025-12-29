# Casino Platform - Project Structure

## 📁 Active Production Files

### Core Application Files
- **index.php** - Main game lobby (206+ games, infinite scroll, Redis cached)
- **login.php** - User login/registration with phone authentication
- **admin.php** - Admin dashboard (user/game management, statistics)
- **play_game.php** - Game player page with session keep-alive
- **logout.php** - User logout handler
- **profile.php** - User profile page (to be developed)

### API & Integration
- **api_request_builder.php** - SoftAPI integration wrapper
- **callback.php** - Game result handler (bet/win callbacks)
- **launch_game.php** - Game launch endpoint
- **config.php** - Database & API credentials (⚠️ DO NOT COMMIT)

### Helper & Utility Files
- **db_helper.php** - Database operations (User, Transaction classes)
- **redis_helper.php** - Redis caching wrapper (cache-aside pattern)
- **currency_helper.php** - Currency formatting and detection
- **session_config.php** - Extended session management (4-hour timeout)
- **settings_helper.php** - System settings management
- **helpers.php** - General utility functions (used by API files)
- **keep_alive.php** - AJAX endpoint for session keep-alive

### Admin AJAX Endpoints
- **get_user_history.php** - Fetch user transaction history
- **get_login_history.php** - Fetch user login history
- **upload_game_image.php** - Handle game image uploads

### Setup & Maintenance Scripts
- **setup_database.php** - Initialize database tables
- **setup_admin.php** - Create/reset admin account
- **update_jili_complete.php** - Update game catalog + link images
- **optimize_database.sql** - Database performance indexes

### Development Tools
- **view_balances.php** - Debug tool for checking user balances
- **backup_server.sh** - Automated backup to GitHub

### Configuration Files
- **.htaccess** - Apache configuration (Gzip, security headers)
- **manifest.json** - PWA manifest for app installation
- **service-worker.js** - PWA offline support
- **package.json** - Project metadata

### Documentation
- **CASINO_SETUP_GUIDE.md** - Complete setup & deployment guide (v1.5.0)
- **API_DOCUMENTATION.md** - SoftAPI integration documentation
- **README.md** - Project overview
- **PROJECT_STRUCTURE.md** - This file

## 📦 Archived Files (_archive/)

Files moved to `_archive/` directory for reference:

### old_files/
- Deprecated or replaced files (casino.php, admin_backup.php, etc.)
- Legacy helper files (balance_helper.php, currency_codes.php)
- Old documentation (DEPLOYMENT.md - merged into CASINO_SETUP_GUIDE.md)

### test_files/
- Development test scripts (test_*.php, example_launch.php)
- Test deployment scripts

### setup_scripts/
- One-time migration scripts (migrate_currency.php, add_user_tracking.php)
- Legacy game update scripts (update_jili_games.php, update_game_images.php)
- Database schema backups

### server_scripts/
- Server setup scripts (install_webserver.sh, setup_server.sh)
- Deployment scripts (deploy.sh)

## 🚀 Deployment Files

### Production (31.97.107.21:/var/www/html/)
Only these files should be deployed:
```
index.php
login.php
admin.php
play_game.php
logout.php
profile.php
config.php (with production credentials)
api_request_builder.php
callback.php
launch_game.php
db_helper.php
redis_helper.php
currency_helper.php
session_config.php
settings_helper.php
helpers.php
keep_alive.php
get_user_history.php
get_login_history.php
upload_game_image.php
.htaccess
manifest.json
service-worker.js
images/
logs/
```

### Excluded from Deployment
- Test files (_archive/test_files/)
- Setup scripts (run manually when needed)
- Documentation (local reference)
- Git files (.git/, .gitignore)
- Development tools (view_balances.php)

## 📊 File Count Summary

- **Active production files**: 28 PHP files
- **Archived files**: 29 files
- **Total cleaned up**: ~50% reduction in active files

## 🔒 Security Notes

- **config.php** - Contains sensitive credentials, not in Git
- **.env** - Alternative for credentials (if using)
- **logs/** - Write-only directory (777 permissions)
- **images/games/** - Uploaded images (755 permissions)

## 📝 Maintenance

### Regular Updates
- **update_jili_complete.php** - Run monthly to sync game catalog
- **setup_admin.php** - Run to reset admin password if needed
- **backup_server.sh** - Run daily for backups

### Database Maintenance
- **optimize_database.sql** - Rerun if adding new tables/indexes
- Monitor Redis cache: `redis-cli INFO stats`
- Check slow queries: `tail /var/log/mysql/slow-query.log`

---

Last Updated: December 29, 2025
Version: 1.5.0 (Performance Optimization Release)
