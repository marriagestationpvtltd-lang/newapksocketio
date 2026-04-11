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
$userid  = $_GET['userid'] ?? null;
$privacy = $_GET['privacy'] ?? null;

// Validate input
$validPrivacy = ['free', 'paid', 'private', 'verified'];

if (!$userid || !$privacy) {
    echo json_encode([
        "status" => "error",
        "message" => "userid and privacy are required"
    ]);
    exit;
}

if (!in_array(strtolower($privacy), $validPrivacy)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid privacy value. Allowed: free, paid, private, verified"
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE users 
        SET privacy = ? 
        WHERE id = ?
    ");
    $stmt->execute([$privacy, $userid]);

    if ($stmt->rowCount() === 0) {
        echo json_encode([
            "status" => "error",
            "message" => "User not found or privacy already set"
        ]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "message" => "User privacy updated successfully",
        "data" => [
            "userid" => $userid,
            "privacy" => $privacy
        ]
    ]);
} catch (Exception $e) {
    error_log('privacy.php Exception: ' . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "Failed to update privacy"
    ]);
}
