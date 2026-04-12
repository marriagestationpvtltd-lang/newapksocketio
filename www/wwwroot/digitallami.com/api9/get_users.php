<?php
require_once __DIR__ . '/../config/db.php';
header("Content-Type: application/json");

// ================= DB CONFIG =================
// ✅ BASE URL FOR PROFILE PICTURES
define('PROFILE_BASE_URL', APP_API2_BASE_URL);

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare("
        SELECT
            id,
            firstName,
            lastName,
            email,
            isVerified,
            status,
            privacy,
            usertype,
            lastLogin,
            profile_picture,
            isOnline,
            isActive,
            pageno,
            gender
        FROM users
        ORDER BY id DESC
    ");

    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 🔥 ADD BASE URL TO PROFILE PICTURE
    foreach ($users as &$user) {
        if (!empty($user['profile_picture'])) {
            $user['profile_picture'] =
                PROFILE_BASE_URL . ltrim($user['profile_picture'], '/');
        } else {
            $user['profile_picture'] = null;
        }
    }

    echo json_encode([
        'success' => true,
        'count' => count($users),
        'data' => $users
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}
