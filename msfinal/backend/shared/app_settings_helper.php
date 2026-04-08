<?php

define('APP_SETTINGS_DB_HOST', 'localhost');
define('APP_SETTINGS_DB_NAME', 'ms');
define('APP_SETTINGS_DB_USER', 'ms');
define('APP_SETTINGS_DB_PASS', 'ms');

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
    return new PDO(
        "mysql:host=" . APP_SETTINGS_DB_HOST . ";dbname=" . APP_SETTINGS_DB_NAME,
        APP_SETTINGS_DB_USER,
        APP_SETTINGS_DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
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
    ];
}

function read_app_settings(PDO $pdo): array
{
    ensure_app_settings_table($pdo);

    $defaults = get_app_settings_defaults();
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM app_settings');
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $key = $row['setting_key'] ?? '';
        if ($key === '') {
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
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_at = CURRENT_TIMESTAMP
    ");

    foreach ($settings as $key => $value) {
        $stmt->execute([
            ':setting_key' => $key,
            ':setting_value' => $value,
        ]);
    }
}
