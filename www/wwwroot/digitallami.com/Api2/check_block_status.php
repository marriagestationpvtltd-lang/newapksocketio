<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$input = json_decode(file_get_contents('php://input'), true);
$myId = intval($input['my_id'] ?? 0);
$userId = intval($input['user_id'] ?? 0);

if ($myId <= 0 || $userId <= 0) {
    echo json_encode(["status" => "error", "is_blocked" => false, "is_blocked_by" => false, "either_blocked" => false]);
    exit;
}

// Fetch both block directions in a single query.
$stmt = $conn->prepare("
    SELECT
        MAX(blocker_id = ? AND blocked_id = ?) AS is_blocked,
        MAX(blocker_id = ? AND blocked_id = ?) AS is_blocked_by
    FROM blocks
    WHERE (blocker_id = ? AND blocked_id = ?)
       OR (blocker_id = ? AND blocked_id = ?)
");
$stmt->bind_param("iiiiiiii", $myId, $userId, $userId, $myId, $myId, $userId, $userId, $myId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$isBlocked   = (bool)($row['is_blocked']   ?? false);
$isBlockedBy = (bool)($row['is_blocked_by'] ?? false);

echo json_encode([
    "status"        => "success",
    "is_blocked"    => $isBlocked,
    "is_blocked_by" => $isBlockedBy,
    "either_blocked" => $isBlocked || $isBlockedBy,
]);

$conn->close();
?>