#!/usr/bin/env php
<?php
// Apply AdminLTE Sidebar Layout to admin_lte.php

echo "Applying AdminLTE sidebar layout...\n";

$file = 'admin_lte.php';
$content = file_get_contents($file);

// 1. Replace the header section with AdminLTE navbar and sidebar
$oldHeader = <<<'OLD'
    <div class="header navbar navbar-expand navbar-dark" style="background:#1a1f36;border-bottom:1px solid #2d3548;">
        <h1>Admin Dashboard</h1>
        <div>
            <a href="index.php">← Back to Games</a>
            <a href="?logout=1">Logout</a>
        </div>
    </div>
    <div class="container">
OLD;

$newLayout = <<<'NEW'
<div class="wrapper">
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-dark navbar-primary">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a href="index.php" class="nav-link"><i class="fas fa-home"></i> Back to Games</a>
            </li>
            <li class="nav-item">
                <a href="?logout=1" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </li>
        </ul>
    </nav>

    <!-- Main Sidebar -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="admin_lte.php" class="brand-link">
            <span class="brand-text font-weight-light"><i class="fas fa-gamepad"></i> Admin Dashboard</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="#" class="nav-link active" onclick="switchTab('games', this); return false;">
                            <i class="nav-icon fas fa-gamepad"></i>
                            <p>Games</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="switchTab('wallet', this); return false;">
                            <i class="nav-icon fas fa-wallet"></i>
                            <p>Wallet
                            <?php if ($pendingCount > 0): ?>
                                <span class="badge badge-danger right"><?php echo $pendingCount; ?></span>
                            <?php endif; ?>
                            </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="switchTab('bonuses', this); return false;">
                            <i class="nav-icon fas fa-gift"></i>
                            <p>Bonuses</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="switchTab('users', this); return false;">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Users</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="switchTab('history', this); return false;">
                            <i class="nav-icon fas fa-chart-line"></i>
                            <p>Betting History</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="switchTab('topplayers', this); return false;">
                            <i class="nav-icon fas fa-trophy"></i>
                            <p>Top Players</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="switchTab('mostplayed', this); return false;">
                            <i class="nav-icon fas fa-fire"></i>
                            <p>Most Played</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="switchTab('tool', this); return false;">
                            <i class="nav-icon fas fa-tools"></i>
                            <p>Tools</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="switchTab('settings', this); return false;">
                            <i class="nav-icon fas fa-cog"></i>
                            <p>Settings</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
NEW;

$content = str_replace($oldHeader, $newLayout, $content);

// 2. Remove the old tabs section
$content = preg_replace(
    '/<div class="tabs nav nav-pills mb-3".*?<\/div>\s*<!-- Games Tab -->/s',
    '<!-- Tabs removed, using sidebar navigation -->
        <!-- Stats Section -->',
    $content
);

// 3. Update switchTab function to work with sidebar
$oldSwitchTab = <<<'OLD'
        function switchTab(tabName, el) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            if (el) { el.classList.add('active'); }
        }
OLD;

$newSwitchTab = <<<'NEW'
        function switchTab(tabName, el) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.nav-sidebar .nav-link').forEach(link => link.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            if (el) { el.classList.add('active'); }
        }
NEW;

$content = str_replace($oldSwitchTab, $newSwitchTab, $content);

// 4. Wrap stats and content sections properly
$content = str_replace(
    '<div class="stats">',
    '</div></div><section class="content"><div class="container-fluid"><div class="stats">',
    $content
);

// 5. Close the wrapper at the end
$content = str_replace(
    '</body>',
    '</div></section></div></div></body>',
    $content
);

file_put_contents($file, $content);

echo "✓ Applied AdminLTE sidebar layout\n";
echo "✓ Converted top tabs to sidebar navigation with icons\n";
echo "✓ Updated switchTab() function for sidebar\n";
echo "✓ Wrapped content in AdminLTE structure\n";
