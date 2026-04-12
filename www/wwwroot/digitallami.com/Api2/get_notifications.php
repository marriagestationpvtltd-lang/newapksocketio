<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/database.php';

// Handle OPTIONS preflight for Flutter Web cross-origin requests.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// user_id is required; never fall back to a default value
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if ($user_id <= 0) {
    echo json_encode(["status" => "error", "message" => "user_id is required"]);
    exit;
}

// Get notifications
$query = "SELECT * FROM user_notifications WHERE user_id = :user_id ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();

$notifications_arr = array();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    extract($row);
    
    $notification_item = array(
        "id" => $id,
        "type" => $type,
        "title" => $title,
        "message" => $message,
        "is_read" => $is_read,
        "time" => date("Y-m-d h:i A", strtotime($created_at))
    );
    
    array_push($notifications_arr, $notification_item);
}

// Get notification settings
$settings_query = "SELECT * FROM user_notification_settings WHERE user_id = :user_id";
$settings_stmt = $db->prepare($settings_query);
$settings_stmt->bindParam(':user_id', $user_id);
$settings_stmt->execute();

$settings_arr = array();
if ($settings_stmt->rowCount() > 0) {
    $row = $settings_stmt->fetch(PDO::FETCH_ASSOC);
    $settings_arr = array(
        "push_enabled" => (bool)$row['push_enabled'],
        "email_enabled" => (bool)$row['email_enabled'],
        "sms_enabled" => (bool)$row['sms_enabled']
    );
} else {
    // Default settings if not found
    $settings_arr = array(
        "push_enabled" => true,
        "email_enabled" => true,
        "sms_enabled" => false
    );
}

$response = array(
    "status" => "success",
    "notifications" => $notifications_arr,
    "settings" => $settings_arr
);

echo json_encode($response);
?>