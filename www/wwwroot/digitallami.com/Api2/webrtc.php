<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

// Database configuration
$host = DB_HOST;
$db_name = DB_NAME;
$username = DB_USER;
$password = DB_PASS;
// Create connection
$conn = new mysqli($host, $username, $password, $db_name);

// Check connection
if ($conn->connect_error) {
    die(json_encode([
        "status" => "error",
        "message" => "Connection failed: " . $conn->connect_error
    ]));
}

// Query to fetch all records from webrtc table
$sql = "SELECT * FROM webrtc";
$result = $conn->query($sql);

$data = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode([
    "status" => "success",
    "data" => $data
]);

$conn->close();
?>
