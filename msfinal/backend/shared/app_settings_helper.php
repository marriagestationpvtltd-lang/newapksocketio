<?php

require_once __DIR__ . '/../Api2/database.php';

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

    $insertStmt = $pdo->prepare("
        INSERT INTO app_settings (setting_key, setting_value)
        VALUES (:setting_key, :setting_value)
    ");

    $updateStmt = $pdo->prepare("
        UPDATE app_settings
        SET setting_value = :setting_value,
            updated_at = CURRENT_TIMESTAMP
        WHERE setting_key = :setting_key
    ");

    foreach ($settings as $key => $value) {
        $params = [
            ':setting_key' => $key,
            ':setting_value' => $value,
        ];

        try {
            $insertStmt->execute($params);
        } catch (PDOException $e) {
            $errorInfo = $e->errorInfo;
            $sqlState = is_array($errorInfo) ? ($errorInfo[0] ?? null) : $e->getCode();
            $isDuplicateKey = $sqlState === '23000';
            if (!$isDuplicateKey) {
                throw $e;
            }
            $updateStmt->execute($params);
        }
    }
}

function app_settings_build_public_url(string $path): string
{
    $normalizedPath = '/' . ltrim($path, '/');
    $host = $_SERVER['HTTP_HOST'] ?? 'digitallami.com';
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $protocol = $isHttps ? 'https' : 'http';

    return $protocol . '://' . $host . $normalizedPath;
}

function delete_uploaded_call_tone(?string $publicUrl): void
{
    if (!is_string($publicUrl) || trim($publicUrl) === '') {
        return;
    }

    $urlPath = parse_url($publicUrl, PHP_URL_PATH);
    if (!is_string($urlPath) || strpos($urlPath, '/uploads/app_settings/call_tones/') !== 0) {
        return;
    }

    $projectRoot = realpath(__DIR__ . '/../../');
    $baseDir = realpath($projectRoot . '/uploads/app_settings/call_tones');
    if ($projectRoot === false || $baseDir === false) {
        return;
    }

    $relativePath = ltrim($urlPath, '/');
    $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $parentDir = realpath(dirname($absolutePath));

    if ($parentDir === false || strpos($parentDir, $baseDir) !== 0) {
        return;
    }

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}
