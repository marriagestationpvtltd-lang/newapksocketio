<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/database.php';

// Handle OPTIONS preflight for Flutter Web cross-origin requests.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->notification_id)) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "DELETE FROM user_notifications WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $data->notification_id);
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(array("message" => "Notification deleted successfully."));
    } else {
        http_response_code(503);
        echo json_encode(array("message" => "Unable to delete notification."));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Notification ID is required."));
}
?>