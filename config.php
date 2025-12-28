<?php
/**
 * SoftAPI Configuration
 * Store your API credentials here
 */

// API Credentials (from your SoftAPI account)
define('API_TOKEN', '5cd0be9827c469e7ce7d07abbb239e98');  // Your unique API token
define('API_SECRET', 'dc6b955933342d32d49b84c52b59184f');   // 32-character secret key (MUST BE 32 BYTES)

// API Endpoints
define('SERVER_URL', 'https://igamingapis.live/api/v1');

// Your Website URLs
define('RETURN_URL', 'https://31.97.107.21/');       // URL where user returns after playing
define('CALLBACK_URL', 'http://31.97.107.21/callback.php'); // URL where game sends results (HTTP for API access)

// Database Configuration (Optional - configure as needed)
define('DB_HOST', 'localhost');
define('DB_NAME', 'casino_db');
define('DB_USER', 'casino_user');
define('DB_PASS', 'casino123');

// Timezone
date_default_timezone_set('Asia/Manila');

?>
