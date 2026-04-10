<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$input = json_decode(file_get_contents('php://input'), true);
$myId = intval($input['my_id'] ?? 0);
$userId = intval($input['user_id'] ?? 0);

if ($myId <= 0 || $userId <= 0) {
    echo json_encode(["status" => "error", "is_blocked" => false, "is_blocked_by" => false, "either_blocked" => false]);
    exit;
}

// Check if I blocked the other user
$stmt = $conn->prepare("SELECT id FROM blocks WHERE blocker_id = ? AND blocked_id = ?");
$stmt->bind_param("ii", $myId, $userId);
$stmt->execute();
$isBlocked = $stmt->get_result()->num_rows > 0;
$stmt->close();

// Check if the other user blocked me
$stmt2 = $conn->prepare("SELECT id FROM blocks WHERE blocker_id = ? AND blocked_id = ?");
$stmt2->bind_param("ii", $userId, $myId);
$stmt2->execute();
$isBlockedBy = $stmt2->get_result()->num_rows > 0;
$stmt2->close();

echo json_encode([
    "status"        => "success",
    "is_blocked"    => $isBlocked,
    "is_blocked_by" => $isBlockedBy,
    "either_blocked" => $isBlocked || $isBlockedBy,
]);

$conn->close();
?>