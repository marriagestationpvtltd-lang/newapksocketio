<?php
// Load CORS headers FIRST so that OPTIONS preflight requests (sent by browsers
// and Flutter Web before every cross-origin GET/POST) receive the correct
// Access-Control-Allow-* headers and the actual request is not blocked.
require_once __DIR__ . '/../shared/cors.php';
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.',
        'data' => [],
    ]);
    exit;
}

require_once __DIR__ . '/../shared/app_settings_helper.php';

try {
    $pdo = app_settings_pdo();
    $settings = read_app_settings($pdo);
    app_settings_response(true, 'Settings loaded successfully.', $settings);
} catch (Throwable $e) {
    error_log('Api2/app_settings.php error: ' . $e->getMessage());
    app_settings_response(false, 'Unable to load settings.', get_app_settings_defaults(), 500);
}
