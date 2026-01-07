#!/bin/bash
# AdminLTE Migration Script for admin.php
# This script creates admin_lte.php with full AdminLTE integration

echo "Creating AdminLTE-enhanced admin panel..."

# Backup original
cp admin.php admin.php.backup
echo "✓ Backed up admin.php to admin.php.backup"

# Create the new file
cp admin.php admin_lte.php
echo "✓ Created admin_lte.php from admin.php"

# 1. Add Font Awesome for icons (after AdminLTE CSS)
sed -i '/<link rel="stylesheet" href="https:\/\/cdn.jsdelivr.net\/npm\/admin-lte@4\/dist\/css\/adminlte.min.css">/a\    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">' admin_lte.php

# 2. Change body class to AdminLTE sidebar layout
sed -i 's/<body class="hold-transition layout-navbar-fixed">/<body class="hold-transition sidebar-mini layout-fixed">/' admin_lte.php

# 3. Update table elements with Bootstrap classes
sed -i 's/<table>/<div class="table-responsive"><table class="table table-dark table-striped table-hover">/' admin_lte.php
sed -i 's/<\/table>/<\/table><\/div>/' admin_lte.php

echo "✓ Applied Bootstrap table classes"
echo "✓ AdminLTE migration complete!"
echo ""
echo "Next steps:"
echo "1. Review admin_lte.php"
echo "2. Test locally: php -S localhost:8000"
echo "3. Deploy: scp -P 22 admin_lte.php root@31.97.107.21:/var/www/html/"
echo ""
echo "Original backed up as: admin.php.backup"
