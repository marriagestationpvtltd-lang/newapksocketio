<?php
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json');

// Suppress PHP notices/warnings so they never corrupt the JSON response.
ini_set('display_errors', '0');
error_reporting(E_ERROR);

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Auto-create the app_versions table if it doesn't exist yet.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `app_versions` (
            `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `android_version` VARCHAR(20) NOT NULL DEFAULT '1.0.0',
            `ios_version`     VARCHAR(20) NOT NULL DEFAULT '1.0.0',
            `force_update`    TINYINT(1)  NOT NULL DEFAULT 0,
            `description`     TEXT,
            `app_link`        VARCHAR(500) DEFAULT '',
            `is_active`       TINYINT(1)  NOT NULL DEFAULT 1,
            `created_at`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Seed a default row so the table is never empty on a fresh install.
    $pdo->exec("
        INSERT IGNORE INTO `app_versions` (id, android_version, ios_version, force_update, description, app_link, is_active)
        VALUES (1, '1.0.0', '1.0.0', 0, 'Initial release', '', 1)
    ");

    $platform = isset($_GET['platform']) ? $_GET['platform'] : null;
    
    $stmt = $pdo->query("
        SELECT android_version, ios_version, force_update, description, app_link, updated_at
        FROM app_versions
        WHERE is_active = 1
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $version = $stmt->fetch();
    
    if ($version) {
        // Format response based on platform if specified
        $response = [
            'success' => true,
            'data' => [
                'android_version' => $version['android_version'],
                'ios_version' => $version['ios_version'],
                'force_update' => (bool)$version['force_update'],
                'description' => $version['description'],
                'app_link' => $version['app_link'],
                'last_updated' => $version['updated_at']
            ]
        ];
        
        // If platform is specified, return platform-specific info
        if ($platform === 'android') {
            $response['data']['current_version'] = $version['android_version'];
            $response['data']['store_link'] = $version['app_link'];
        } elseif ($platform === 'ios') {
            $response['data']['current_version'] = $version['ios_version'];
            $response['data']['store_link'] = $version['app_link'];
        }
        
        echo json_encode($response);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No version information found'
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log('app.php PDOException: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
?>