<?php
require_once __DIR__ . '/../config/db.php';
header("Content-Type: application/json");
require 'common_fcm.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Database connection
$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$db = DB_NAME;
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(["status"=>false,"message"=>"DB connection failed"]);
    exit;
}

// Get parameters for call notification
$user_id = $_POST['user_id'] ?? '';
$title   = $_POST['title'] ?? 'Incoming Call';
$body    = $_POST['body'] ?? '';
$data_json = $_POST['data'] ?? '{}';
$data = json_decode($data_json, true);

// Validate
if (empty($user_id)) {
    echo json_encode(["status"=>false,"message"=>"user_id required"]);
    exit;
}

// Fetch FCM token from users table
$stmt = $conn->prepare("SELECT fcm_token FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($fcm_token);
$stmt->fetch();
$stmt->close();

if (empty($fcm_token)) {
    echo json_encode(["status"=>false,"message"=>"FCM token not found for user"]);
    exit;
}

// ── Block check ──────────────────────────────────────────────────────────────
// For peer-to-peer notification types (chat messages and calls), skip the FCM
// send if either party has blocked the other. Notification types such as
// 'request', 'profile_view', and other system events do not carry a meaningful
// sender/recipient relationship and are intentionally excluded from this check.
$type = $data['type'] ?? '';
$is_call = ($type === 'call' || $type === 'video_call');

$sender_id = 0;
if ($is_call) {
    $sender_id = intval($data['callerId'] ?? 0);
} elseif ($type === 'chat_message' || $type === 'chat') {
    $sender_id = intval($data['senderId'] ?? 0);
}

$recipient_id = intval($user_id);

if ($sender_id > 0 && $recipient_id > 0 && $sender_id !== $recipient_id) {
    $bstmt = $conn->prepare(
        "SELECT 1 FROM blocks
          WHERE (blocker_id = ? AND blocked_id = ?)
             OR (blocker_id = ? AND blocked_id = ?)
          LIMIT 1"
    );
    $bstmt->bind_param("iiii", $recipient_id, $sender_id, $sender_id, $recipient_id);
    if (!$bstmt->execute()) {
        // If the block check query fails, proceed to send the notification
        // rather than silently dropping it; the socket-level check is the
        // primary enforcement point.
        error_log("send_notification: block check query failed: " . $bstmt->error);
        $bstmt->close();
    } else {
        $bstmt->store_result();
        $is_blocked = $bstmt->num_rows > 0;
        $bstmt->close();

        if ($is_blocked) {
            $conn->close();
            // Return a generic success-shaped response to avoid revealing
            // block relationship to the caller.
            echo json_encode(["status" => true, "message" => "ok"]);
            exit;
        }
    }
}

if ($is_call) {
    $channel_id = 'calls_channel';
} elseif ($type === 'chat_message' || $type === 'chat') {
    $channel_id = 'messages_channel';
} else {
    $channel_id = 'general_notifications';
}

// Merge click_action and sound into data so Flutter can use them
$notification_data = array_merge($data, [
    "click_action" => "FLUTTER_NOTIFICATION_CLICK",
    "sound" => "default",
]);

// Send FCM
try {
    $response = sendFCM($fcm_token, $title, $body, $notification_data, $channel_id, $is_call);

    echo json_encode([
        "status" => true,
        "response" => $response,
        "data_sent" => $notification_data
    ]);

} catch(Exception $e) {
    echo json_encode(["status"=>false,"error"=>$e->getMessage()]);
}


$conn->close();
?>
