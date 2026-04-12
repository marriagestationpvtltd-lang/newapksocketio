<?php
/**
 * Admin endpoint: approve or reject a user's profile photo.
 *
 * POST /api9/approve_profile_photo.php
 * JSON body:
 *   userid – int    – target user ID
 *   action – string – 'approve' or 'reject'
 *   reason – string – (required when action='reject') rejection reason
 *
 * On approve: sets users.status = 'approved', users.isVerified = 1
 * On reject:  sets users.status = 'rejected', users.isVerified = 0,
 *             stores reject_reason in users.reject_reason
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../shared/admin_auth.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

admin_auth_guard();

$input  = json_decode(file_get_contents('php://input'), true);
$userid = isset($input['userid']) ? (int)$input['userid']           : 0;
$action = isset($input['action']) ? trim((string)$input['action'])  : '';
$reason = isset($input['reason']) ? trim((string)$input['reason'])  : '';

if ($userid <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'userid is required']);
    exit;
}

if (!in_array($action, ['approve', 'reject'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => "action must be 'approve' or 'reject'"]);
    exit;
}

if ($action === 'reject' && $reason === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'reason is required when rejecting']);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Verify user exists
    $check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND isDelete = 0");
    $check->execute([$userid]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    if ($action === 'approve') {
        $stmt = $pdo->prepare("
            UPDATE users
            SET status = 'approved', isVerified = 1, reject_reason = NULL
            WHERE id = ?
        ");
        $stmt->execute([$userid]);
        $msg = 'Profile photo approved successfully';
    } else {
        $stmt = $pdo->prepare("
            UPDATE users
            SET status = 'rejected', isVerified = 0, reject_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$reason, $userid]);
        $msg = 'Profile photo rejected';
    }

    echo json_encode(['success' => true, 'message' => $msg]);

} catch (PDOException $e) {
    error_log('approve_profile_photo.php DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
