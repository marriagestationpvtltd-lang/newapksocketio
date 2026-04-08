<?php

require_once __DIR__ . '/../Api2/database.php';

const APP_SETTINGS_PUBLIC_BASE_URL = 'https://digitallami.com';

function app_settings_response(bool $success, string $message, array $data = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ]);
    exit;
}

function app_settings_pdo(): PDO
{
    $database = new Database();
    $pdo = $database->getConnection();
    if (!($pdo instanceof PDO)) {
        throw new RuntimeException('Database connection failed.');
    }
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function ensure_app_settings_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value TEXT DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function get_app_settings_defaults(): array
{
    return [
        'vat_enabled' => '0',
        'vat_rate' => '0',
        'call_tone_id' => 'default',
        'custom_call_tone_url' => '',
        'custom_call_tone_name' => '',
    ];
}

function read_app_settings(PDO $pdo): array
{
    ensure_app_settings_table($pdo);

    $defaults = get_app_settings_defaults();
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM app_settings');
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $key = $row['setting_key'] ?? null;
        if ($key === null || $key === '') {
            continue;
        }
        $defaults[$key] = $row['setting_value'];
    }

    return $defaults;
}

function upsert_app_settings(PDO $pdo, array $settings): void
{
    ensure_app_settings_table($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO app_settings (setting_key, setting_value)
        VALUES (:setting_key, :setting_value)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");

    foreach ($settings as $key => $value) {
        $stmt->execute([
            ':setting_key' => $key,
            ':setting_value' => $value,
        ]);
    }
}

function app_settings_build_public_url(string $path): string
{
    $normalizedPath = '/' . ltrim($path, '/');
    if (strpos($normalizedPath, '..') !== false || !preg_match('#^/uploads/app_settings/call_tones/[A-Za-z0-9_.-]+$#', $normalizedPath)) {
        throw new InvalidArgumentException('Invalid public asset path.');
    }
    return rtrim(APP_SETTINGS_PUBLIC_BASE_URL, '/') . $normalizedPath;
}

function delete_uploaded_call_tone(?string $publicUrl): void
{
    if (!is_string($publicUrl) || trim($publicUrl) === '') {
        return;
    }

    $urlPath = parse_url($publicUrl, PHP_URL_PATH);
    if (!is_string($urlPath)) {
        return;
    }
    $decodedPath = urldecode($urlPath);
    if (strpos($decodedPath, '..') !== false) {
        return;
    }
    if (strpos($decodedPath, '/uploads/app_settings/call_tones/') !== 0) {
        return;
    }

    $projectRoot = realpath(__DIR__ . '/../');
    $baseDir = realpath($projectRoot . '/uploads/app_settings/call_tones');
    if ($projectRoot === false || $baseDir === false) {
        return;
    }

    $relativePath = ltrim($decodedPath, '/');
    $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $parentDir = realpath(dirname($absolutePath));

    if ($parentDir === false || strpos($parentDir, $baseDir) !== 0) {
        return;
    }

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}
