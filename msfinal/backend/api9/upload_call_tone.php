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

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    app_settings_response(false, 'No ringtone file uploaded.', [], 422);
}

$allowedMimeTypes = [
    'audio/mpeg',
    'audio/mp4',
    'audio/ogg',
    'audio/webm',
    'audio/aac',
    'audio/wav',
    'audio/x-wav',
    'audio/x-m4a',
];
$allowedExtensions = ['mp3', 'mp4', 'ogg', 'webm', 'aac', 'wav', 'm4a'];

$originalName = $_FILES['file']['name'] ?? 'custom-tone';
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
    app_settings_response(false, 'Unsupported ringtone file type.', [], 422);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($_FILES['file']['tmp_name']);
if (!in_array($mimeType, $allowedMimeTypes, true)) {
    app_settings_response(false, 'Unsupported ringtone mime type.', [], 422);
}

$maxBytes = 25 * 1024 * 1024;
if (($_FILES['file']['size'] ?? 0) > $maxBytes) {
    app_settings_response(false, 'Ringtone file is too large.', [], 422);
}

$uploadDir = __DIR__ . '/../../uploads/app_settings/call_tones/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    app_settings_response(false, 'Unable to prepare upload folder.', [], 500);
}

$filename = sprintf('call_tone_%s.%s', bin2hex(random_bytes(16)), $extension);
$destination = $uploadDir . $filename;

if (!move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
    app_settings_response(false, 'Failed to save ringtone file.', [], 500);
}

$publicUrl = app_settings_build_public_url('/uploads/app_settings/call_tones/' . $filename);
$displayName = trim((string) $originalName);
$displayName = preg_replace('/[^\w\-. ]+/u', '_', $displayName) ?: 'custom-tone.' . $extension;

try {
    $pdo = app_settings_pdo();
    $existingSettings = read_app_settings($pdo);
    $previousUrl = $existingSettings['custom_call_tone_url'] ?? '';

    upsert_app_settings($pdo, [
        'custom_call_tone_url' => $publicUrl,
        'custom_call_tone_name' => $displayName,
    ]);

    if ($previousUrl !== '' && $previousUrl !== $publicUrl) {
        delete_uploaded_call_tone($previousUrl);
    }

    $settings = read_app_settings($pdo);
    app_settings_response(true, 'Custom ringtone uploaded successfully.', $settings);
} catch (Throwable $e) {
    if (is_file($destination)) {
        @unlink($destination);
    }
    error_log('api9/upload_call_tone.php error: ' . $e->getMessage());
    app_settings_response(false, 'Unable to upload custom ringtone.', [], 500);
}
