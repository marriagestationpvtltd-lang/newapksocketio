<?php
require_once __DIR__ . '/../config/db.php';
header("Content-Type: application/json");

// ---------- DB CONNECTION ----------
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
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit;
}

// ---------- INPUT ----------
$userid = $_GET['userid'] ?? null;

if (!$userid) {
    echo json_encode([
        "status" => "error",
        "message" => "userid is required"
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT  privacy FROM users WHERE id = ?");
    $stmt->execute([$userid]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode([
            "status" => "error",
            "message" => "User not found"
        ]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "message" => "User data fetched successfully",
        "data" => $user
    ]);
} catch (Exception $e) {
    error_log('get_privacy.php Exception: ' . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "Failed to fetch user data"
    ]);
}
