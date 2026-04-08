<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.',
        'data' => [],
    ]);
    exit;
}

require_once __DIR__ . '/../shared/app_settings_helper.php';

$payload = json_decode(file_get_contents("php://input"), true);
$toneId = trim((string)($payload['call_tone_id'] ?? ''));
$allowedToneIds = ['classic', 'soft', 'modern', 'default'];

if ($toneId === '' || !in_array($toneId, $allowedToneIds, true)) {
    app_settings_response(false, 'Invalid call tone.', [], 422);
}

try {
    $pdo = app_settings_pdo();
    upsert_app_settings($pdo, [
        'call_tone_id' => $toneId,
    ]);

    $settings = read_app_settings($pdo);
    app_settings_response(true, 'Call tone updated successfully.', $settings);
} catch (Throwable $e) {
    error_log('api9/update_app_settings.php error: ' . $e->getMessage());
    app_settings_response(false, 'Unable to update call tone.', [], 500);
}
