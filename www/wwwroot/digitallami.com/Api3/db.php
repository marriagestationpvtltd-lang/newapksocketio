<?php
require_once __DIR__ . '/../config/db.php';
header("Content-Type: application/json");

$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$db = DB_NAME;
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit;
}
?>
