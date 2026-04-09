<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

// CORS headers are set by config/db.php -> shared/cors.php

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$token = trim($input['access_token'] ?? '');

if ($token === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'access_token required']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare("
        SELECT
            id,
            CONCAT(COALESCE(firstName,''), ' ', COALESCE(lastName,'')) AS name,
            profile_picture,
            isOnline
        FROM users
        WHERE isDelete = 0 OR isDelete IS NULL
        ORDER BY firstName ASC, lastName ASC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $baseUrl = rtrim(APP_API2_BASE_URL, '/');
    foreach ($rows as &$row) {
        $row['name'] = trim($row['name']);
        if (!empty($row['profile_picture'])) {
            $row['profile_picture'] = $baseUrl . '/' . ltrim($row['profile_picture'], '/');
        } else {
            $row['profile_picture'] = null;
        }
    }
    unset($row);

    echo json_encode(['success' => true, 'users' => $rows]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
