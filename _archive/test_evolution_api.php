<?php
/**
 * Test Evolution Game Launch - Debug API Response
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api_request_builder.php';
require_once __DIR__ . '/db_helper.php';

// Get a test Evolution game (game 7615)
 = getDB();
 = ->query(
