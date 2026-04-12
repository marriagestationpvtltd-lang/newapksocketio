<?php
// Load CORS headers FIRST so that OPTIONS preflight requests receive the
// correct Access-Control-Allow-* headers before any method check exits early.
require_once __DIR__ . '/../shared/cors.php';
header("Content-Type: application/json; charset=UTF-8");

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
$payload = is_array($payload) ? $payload : [];

$rawToneId = $payload['call_tone_id'] ?? null;
$toneId = is_string($rawToneId) ? trim($rawToneId) : null;
$clearCustomCallTone = filter_var(
    $payload['clear_custom_call_tone'] ?? false,
    FILTER_VALIDATE_BOOLEAN
);
$allowedToneIds = ['classic', 'soft', 'modern', 'default'];

if ($toneId !== null && ($toneId === '' || !in_array($toneId, $allowedToneIds, true))) {
    app_settings_response(false, 'Invalid call tone.', [], 422);
}

if ($toneId === null && !$clearCustomCallTone) {
    app_settings_response(false, 'No settings provided.', [], 422);
}

try {
    $pdo = app_settings_pdo();
    $settingsToUpdate = [];

    if ($toneId !== null) {
        $settingsToUpdate['call_tone_id'] = $toneId;
    }

    if ($clearCustomCallTone) {
        $existingSettings = read_app_settings($pdo);
        delete_uploaded_call_tone($existingSettings['custom_call_tone_url'] ?? '');
        $settingsToUpdate['custom_call_tone_url'] = '';
        $settingsToUpdate['custom_call_tone_name'] = '';
    }

    upsert_app_settings($pdo, $settingsToUpdate);

    $settings = read_app_settings($pdo);
    app_settings_response(true, 'Settings updated successfully.', $settings);
} catch (Throwable $e) {
    error_log('api9/update_app_settings.php error: ' . $e->getMessage());
    app_settings_response(false, 'Unable to update settings.', [], 500);
}
