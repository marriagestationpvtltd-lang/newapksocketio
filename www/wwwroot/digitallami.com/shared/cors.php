<?php
/**
 * CORS helper – must be included before any output.
 * Included automatically via config/db.php so every API endpoint
 * inherits correct cross-origin headers for Flutter Web.
 */
if (!defined('_CORS_LOADED')) {
    define('_CORS_LOADED', true);

    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
    header('Access-Control-Max-Age: 86400');

    // Handle browser CORS preflight immediately – no PHP logic needed.
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
